<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\MonologHandler;

/**
 * PosAnalyticsHandler
 *
 * Handles POS/cashier-related analytics.
 * Real extraction from AnalyticsHandler — logic lives here, no delegation.
 */
class PosAnalyticsHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('analytics');
    }

    // =========================================================
    // GET /analytics/daily-cash
    // =========================================================

    public function getDailyCashDrawerSummary(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $date = $request->getQueryParams()['date'] ?? date('Y-m-d');

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount + COALESCE(tax_amount, 0)), 0) as total_cash_sales, COUNT(*) as transaction_count FROM sales WHERE DATE(created_at) = DATE(:date) AND status = 'paid' AND tenant_id = :tenant_id");
            $stmt->execute([':date' => $date, ':tenant_id' => $tenantId]);
            $salesData = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) as total_receipts, COUNT(*) as receipt_count FROM cash_vouchers WHERE type = 'receipt' AND DATE(created_at) = DATE(:date) AND tenant_id = :tenant_id");
            $stmt->execute([':date' => $date, ':tenant_id' => $tenantId]);
            $receiptsData = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) as total_payments, COUNT(*) as payment_count FROM cash_vouchers WHERE type = 'payment' AND DATE(created_at) = DATE(:date) AND tenant_id = :tenant_id");
            $stmt->execute([':date' => $date, ':tenant_id' => $tenantId]);
            $paymentsData = $stmt->fetch(PDO::FETCH_ASSOC);

            $openingBalance = 0;
            $expectedCash   = $openingBalance
                + (float) $salesData['total_cash_sales']
                + (float) $receiptsData['total_receipts']
                - (float) $paymentsData['total_payments'];

            return $this->successResponse($response, [
                'date'            => $date,
                'opening_balance' => (float) $openingBalance,
                'cash_sales'      => ['amount' => (float) $salesData['total_cash_sales'], 'transactions' => (int) $salesData['transaction_count']],
                'receipts'        => ['amount' => (float) $receiptsData['total_receipts'], 'count' => (int) $receiptsData['receipt_count']],
                'payments'        => ['amount' => (float) $paymentsData['total_payments'], 'count' => (int) $paymentsData['payment_count']],
                'expected_cash'   => (float) $expectedCash,
                'closing_balance' => (float) $expectedCash,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('getDailyCashDrawerSummary error', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في جلب ملخص النقدية اليومية', 500);
        }
    }

    // =========================================================
    // GET /analytics/cashier/dashboard-summary
    // =========================================================

    public function cashierDashboardSummary(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
            }

            $params    = $request->getQueryParams();
            $sessionId = $params['session_id'] ?? null;
            // ⚠️ إصلاح: كانت القيم الافتراضية فقط ('00:00:00'/'23:59:59') تُطبَّق حين لا يُرسل
            // العميل start_date/end_date إطلاقاً. أما القيمة الشائعة الفعلية (تاريخ فقط بدون
            // وقت، كما ترسلها كل واجهات اختيار التاريخ الأخرى في النظام) كانت تمر بدون أي
            // تطبيع، فيصبح end_date = '2026-08-01' بدون وقت، ما يجعل
            // "created_at BETWEEN start AND end" يستبعد تقريباً كل سجلات اليوم الأخير
            // (لأن MySQL تقرأها كـ '2026-08-01 00:00:00'). استُبدل بـ DateRangeResolver
            // الموحّد الذي يطبّع الحالتين بنفس الطريقة دائماً.
            [$startDate, $endDateExclusive] = \App\Services\DateRangeResolver::resolve(
                $params['start_date'] ?? null,
                $params['end_date'] ?? null
            );
            $endDate   = $endDateExclusive; // نهاية نصف مفتوحة: يُستخدم مع '< ?' وليس BETWEEN بعد الآن
            $branchId  = $params['branch_id']  ?? null;

            $queryParams  = [$tenantId, $startDate, $endDate];
            $branchFilter  = '';
            $sessionFilter = '';
            // For returns: session_id is on the linked sale (s2), not on returns directly
            $returnSessionFilter = '';

            if ($branchId) {
                $branchFilter  = ' AND branch_id = ? ';
                $queryParams[] = $branchId;
            }
            if ($sessionId) {
                $sessionFilter       = ' AND session_id = ? ';
                $returnSessionFilter = ' AND s2.session_id = ? ';
                $queryParams[]       = $sessionId;
            }

            // Opening balance
            $openingBalance = 0;
            if ($sessionId) {
                $stmtBal = $this->db->prepare("SELECT opening_cash_amount AS opening_balance FROM cashier_sessions WHERE id = ? AND tenant_id = ?");
                $stmtBal->execute([$sessionId, $tenantId]);
                $openingBalance = (float) ($stmtBal->fetchColumn() ?: 0);
            }

            // Recent activities (UNION of sales + returns + cash_transactions)
            $recentActivitiesQuery = "
                (SELECT s.id, 'sale' COLLATE utf8mb4_unicode_ci as type, s.id as reference_id,
                        CAST(s.invoice_number AS CHAR) COLLATE utf8mb4_unicode_ci as reference_code,
                        (s.total_amount + COALESCE(s.tax_amount, 0)) as amount, s.created_at,
                        CONCAT('فاتورة مبيعات #', s.invoice_number) COLLATE utf8mb4_unicode_ci as description
                 FROM sales s WHERE s.tenant_id = ? AND s.created_at >= ? AND s.created_at < ?
                 {$branchFilter} {$sessionFilter} AND s.status IN ('paid', 'settled_by_credit', 'closed_by_return', 'settled_mixed'))
                UNION ALL
                (SELECT r.id, 'return' COLLATE utf8mb4_unicode_ci as type, r.id as reference_id,
                        CAST(r.return_number AS CHAR) COLLATE utf8mb4_unicode_ci as reference_code,
                        r.grand_total as amount, r.created_at,
                        CONCAT('مرتجع مبيعات #', r.return_number) COLLATE utf8mb4_unicode_ci as description
                 FROM returns r LEFT JOIN sales s2 ON s2.id = r.sale_id
                 WHERE r.tenant_id = ? AND r.created_at >= ? AND r.created_at < ?
                 {$branchFilter} {$returnSessionFilter} AND r.status = 'approved')
                UNION ALL
                (SELECT ct.id,
                        (CASE WHEN ct.type = 'expense' THEN 'withdrawal' ELSE 'deposit' END) COLLATE utf8mb4_unicode_ci as type,
                        ct.id as reference_id,
                        CAST(COALESCE(
                            CASE
                                WHEN ct.reference_type = 'cash_voucher' THEN cv.reference
                                WHEN ct.reference_type = 'sale'         THEN s_ct.invoice_number
                                WHEN ct.reference_type = 'return'       THEN r_ct.return_number
                                ELSE CONCAT('TX-', ct.id)
                            END,
                            CONCAT('TX-', ct.id)
                        ) AS CHAR) COLLATE utf8mb4_unicode_ci as reference_code,
                        ct.amount, ct.created_at,
                        (CASE WHEN ct.type = 'expense' THEN CONCAT('سحب نقدي #', COALESCE(cv.reference, CONCAT('TX-', ct.id))) ELSE CONCAT('إيداع نقدي #', COALESCE(cv.reference, CONCAT('TX-', ct.id))) END) COLLATE utf8mb4_unicode_ci as description
                 FROM cash_transactions ct
                 LEFT JOIN cash_vouchers cv    ON cv.id    = ct.reference_id AND ct.reference_type = 'cash_voucher'
                 LEFT JOIN sales s_ct          ON s_ct.id  = ct.reference_id AND ct.reference_type = 'sale'
                 LEFT JOIN returns r_ct        ON r_ct.id  = ct.reference_id AND ct.reference_type = 'return'
                 WHERE ct.tenant_id = ? AND ct.created_at >= ? AND ct.created_at < ?
                 {$branchFilter} {$sessionFilter} AND ct.status = 'completed')
                ORDER BY created_at DESC LIMIT 10
            ";

            // Build per-query params for UNION (returns uses returnSessionFilter)
            $salesParams  = [$tenantId, $startDate, $endDate];
            $returnParams = [$tenantId, $startDate, $endDate];
            $ctParams     = [$tenantId, $startDate, $endDate];

            if ($branchId) {
                $salesParams[]  = $branchId;
                $returnParams[] = $branchId;
                $ctParams[]     = $branchId;
            }
            if ($sessionId) {
                $salesParams[]  = $sessionId; // session_id on sales
                $returnParams[] = $sessionId; // s2.session_id on linked sale
                $ctParams[]     = $sessionId; // session_id on cash_transactions
            }

            $recentParams = array_merge($salesParams, $returnParams, $ctParams);
            $stmt = $this->db->prepare($recentActivitiesQuery);
            $stmt->execute($recentParams);
            $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total sales
            // ── AUDIT FIX #5 (HIGH) ──────────────────────────────────────────────
            // `sales.status` is never written as 'completed' anywhere in the
            // codebase (see AccountingService/SaleCreationService/SalePaymentService
            // — the values actually used are 'paid', 'partial', 'pending_payment',
            // 'pending_approval', 'cancelled'). This filter matched zero rows
            // unconditionally, so the cashier dashboard's "total sales" always
            // showed 0 regardless of actual sales volume. Aligned with the
            // already-correct pattern used in AdvancedReportsHandler.
            // ── AUDIT SELF-REVIEW FIX 15-A (vocabulary rollout) ──────────────────
            // Expanded further to include all fully-settled states
            // (settled_by_credit/closed_by_return/settled_mixed) now that status
            // carries that vocabulary — a sale this cashier rang up and that was
            // settled via return credit is still a real sale for this session.
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount + COALESCE(tax_amount,0)),0) as total_sales, COUNT(*) as sales_count FROM sales s WHERE s.tenant_id = ? AND s.created_at >= ? AND s.created_at < ? {$branchFilter} {$sessionFilter} AND s.status IN ('paid', 'settled_by_credit', 'closed_by_return', 'settled_mixed')");
            $stmt->execute($queryParams);
            $salesData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Total returns — نستخدم JOIN مع sales للوصول لـ session_id (جدول returns لا يملك session_id مباشرة)
            $returnsParams = [$tenantId, $startDate, $endDate];
            $returnsWhere  = "r.tenant_id = ? AND r.created_at >= ? AND r.created_at < ?";
            if ($branchId) {
                $returnsWhere   .= " AND r.branch_id = ?";
                $returnsParams[] = $branchId;
            }
            if ($sessionId) {
                $returnsWhere   .= " AND s2.session_id = ?";
                $returnsParams[] = $sessionId;
            }
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(r.grand_total),0) as total_returns, COUNT(*) as returns_count
                 FROM returns r
                 LEFT JOIN sales s2 ON s2.id = r.sale_id
                 WHERE {$returnsWhere} AND r.status = 'approved'"
            );
            $stmt->execute($returnsParams);
            $returnsData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Payment breakdown
            // ── AUDIT SELF-REVIEW FIX 15-A (vocabulary rollout) ───────────────────
            // Intentionally NOT expanded to settled_by_credit/closed_by_return: by
            // definition those sales received zero actual cash/card payment, so
            // attributing them to a payment_method_id here would misstate how much
            // money actually moved through each method. Stays scoped to 'paid'
            // (genuine full cash/card settlement) only — same reasoning as the
            // cash-drawer query in AdvancedReportsHandler.
            $stmt = $this->db->prepare("SELECT s.payment_method_id, COALESCE(SUM(s.total_amount + COALESCE(s.tax_amount,0)),0) as total_amount, COUNT(*) as transaction_count FROM sales s WHERE s.tenant_id = ? AND s.created_at >= ? AND s.created_at < ? {$branchFilter} {$sessionFilter} AND s.status = 'paid' GROUP BY s.payment_method_id");
            $stmt->execute($queryParams);
            $paymentBreakdown = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $methodId = $row['payment_method_id'];
                $stmtMethod = $this->db->prepare("SELECT name, kind FROM payment_methods WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtMethod->execute([$methodId, $tenantId]);
                $methodData = $stmtMethod->fetch(PDO::FETCH_ASSOC);
                if ($methodData) {
                    $kind = $methodData['kind'] ?? 'other';
                    $paymentBreakdown[$kind . '_total'] = ($paymentBreakdown[$kind . '_total'] ?? 0) + (float) $row['total_amount'];
                }
            }

            // Expenses
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) as total_expenses FROM cash_transactions WHERE tenant_id = ? AND created_at >= ? AND created_at < ? {$branchFilter} {$sessionFilter} AND type = 'withdrawal' AND status = 'completed'");
            $stmt->execute($queryParams);
            $expensesTotal = (float) $stmt->fetchColumn();

            return $this->successResponse($response, [
                'openingBalance'   => $openingBalance,
                'totalSales'       => (float) $salesData['total_sales'],
                'salesCount'       => (int) $salesData['sales_count'],
                'totalReturns'     => (float) $returnsData['total_returns'],
                'returnsCount'     => (int) $returnsData['returns_count'],
                'expenses'         => $expensesTotal,
                'paymentBreakdown' => $paymentBreakdown,
                'recentActivities' => $recentActivities,
            ], 200);
        } catch (\Exception $e) {
            $this->logger->error('cashierDashboardSummary error', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في جلب ملخص لوحة تحكم الكاشير', 400);
        }
    }

    // =========================================================
    // GET /reports/pos
    // =========================================================

    public function listPos(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $params = $request->getQueryParams();
            $where  = ['s.tenant_id = ?'];
            $qp     = [$tenantId];

            if (!empty($params['start_date'])) {
                $where[] = 's.created_at >= ?';
                $qp[] = $params['start_date'];
            }
            if (!empty($params['end_date'])) {
                $where[] = 's.created_at <= ?';
                $qp[] = $params['end_date'] . ' 23:59:59';
            }

            $sql = "SELECT s.branch_id AS pos_id, b.name AS pos_name,
                           COUNT(DISTINCT s.id) as orders,
                           COALESCE(SUM(si.quantity * si.sale_price), 0) as amount
                    FROM sales s
                    LEFT JOIN branches b ON b.id = s.branch_id AND (b.tenant_id = s.tenant_id OR b.tenant_id IS NULL)
                    JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY s.branch_id, b.name
                    ORDER BY amount DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($qp);
            return $this->successResponse($response, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 200);
        } catch (\Throwable $e) {
            $this->logger->error('listPos error', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في جلب قائمة نقاط البيع', 500);
        }
    }

    // =========================================================
    // GET /analytics/pos-performance
    // =========================================================

    public function getPosPerformance(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $params    = $request->getQueryParams();
            $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $endDate   = $params['end_date']   ?? date('Y-m-d');

            $sql = "
                SELECT s.user_id, u.name as cashier_name, u.username as user_name,
                       COUNT(DISTINCT s.id) as orders_count,
                       COALESCE(SUM(s.total_amount), 0) as total_sales,
                       COALESCE(SUM(CASE
                           WHEN s.discount_type = 'percentage' THEN s.total_amount * (1 - s.discount_value / 100)
                           WHEN s.discount_type = 'fixed'      THEN s.total_amount - s.discount_value
                           ELSE s.total_amount END), 0) as net_sales,
                       COALESCE(AVG(s.total_amount), 0) as avg_order_value
                FROM sales s
                LEFT JOIN users u ON u.id = s.user_id AND u.tenant_id = s.tenant_id
                WHERE s.tenant_id = ? AND DATE(s.created_at) BETWEEN ? AND ?
                GROUP BY s.user_id, u.name, u.username
                ORDER BY total_sales DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tenantId, $startDate, $endDate]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $posPerformance = array_map(fn ($row) => [
                'user_id'         => (int)   $row['user_id'],
                'cashier_name'    => $row['cashier_name'] ?? 'غير معروف',
                'user_name'       => $row['user_name']    ?? 'unknown',
                'device_name'     => 'POS-' . str_pad((string) ($row['user_id'] ?? '0'), 3, '0', STR_PAD_LEFT),
                'orders_count'    => (int)   $row['orders_count'],
                'total_sales'     => (float) $row['total_sales'],
                'net_sales'       => (float) $row['net_sales'],
                'avg_order_value' => (float) $row['avg_order_value'],
            ], $results);

            return $this->successResponse($response, [
                'data'   => $posPerformance,
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('getPosPerformance error', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'حدث خطأ أثناء جلب أداء نقاط البيع', 400);
        }
    }
}
