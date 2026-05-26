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
            $startDate = $params['start_date'] ?? date('Y-m-d 00:00:00');
            $endDate   = $params['end_date']   ?? date('Y-m-d 23:59:59');
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
                 FROM sales s WHERE s.tenant_id = ? AND s.created_at BETWEEN ? AND ?
                 {$branchFilter} {$sessionFilter} AND s.status = 'completed')
                UNION ALL
                (SELECT r.id, 'return' COLLATE utf8mb4_unicode_ci as type, r.id as reference_id,
                        CAST(r.return_number AS CHAR) COLLATE utf8mb4_unicode_ci as reference_code,
                        r.grand_total as amount, r.created_at,
                        CONCAT('مرتجع مبيعات #', r.return_number) COLLATE utf8mb4_unicode_ci as description
                 FROM returns r LEFT JOIN sales s2 ON s2.id = r.sale_id
                 WHERE r.tenant_id = ? AND r.created_at BETWEEN ? AND ?
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
                 WHERE ct.tenant_id = ? AND ct.created_at BETWEEN ? AND ?
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
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount + COALESCE(tax_amount,0)),0) as total_sales, COUNT(*) as sales_count FROM sales s WHERE s.tenant_id = ? AND s.created_at BETWEEN ? AND ? {$branchFilter} {$sessionFilter} AND s.status = 'completed'");
            $stmt->execute($queryParams);
            $salesData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Total returns — نستخدم JOIN مع sales للوصول لـ session_id (جدول returns لا يملك session_id مباشرة)
            $returnsParams = [$tenantId, $startDate, $endDate];
            $returnsWhere  = "r.tenant_id = ? AND r.created_at BETWEEN ? AND ?";
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
            $stmt = $this->db->prepare("SELECT s.payment_method_id, COALESCE(SUM(s.total_amount + COALESCE(s.tax_amount,0)),0) as total_amount, COUNT(*) as transaction_count FROM sales s WHERE s.tenant_id = ? AND s.created_at BETWEEN ? AND ? {$branchFilter} {$sessionFilter} AND s.status = 'completed' GROUP BY s.payment_method_id");
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
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) as total_expenses FROM cash_transactions WHERE tenant_id = ? AND created_at BETWEEN ? AND ? {$branchFilter} {$sessionFilter} AND type = 'withdrawal' AND status = 'completed'");
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

            if (!empty($params['start_date'])) { $where[] = 's.created_at >= ?'; $qp[] = $params['start_date']; }
            if (!empty($params['end_date']))   { $where[] = 's.created_at <= ?'; $qp[] = $params['end_date'] . ' 23:59:59'; }

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

            $posPerformance = array_map(fn($row) => [
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

    // =========================================================
    // GET (used internally by getCashierDashboardSummary)
    // =========================================================

    public function getCashierDashboardSummary(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $params    = $request->getQueryParams();
            $sessionId = isset($params['session_id']) ? (int) $params['session_id'] : null;
            $branchId  = isset($params['branch_id'])  ? (int) $params['branch_id']  : null;
            $startDate = $params['start_date'] ?? date('Y-m-d 00:00:00');
            $endDate   = $params['end_date']   ?? date('Y-m-d 23:59:59');
            $userId    = $request->getAttribute('user_id');

            $summary = ['openingBalance' => 0, 'totalSales' => 0, 'totalReturns' => 0, 'cashInDrawer' => 0, 'recentActivities' => []];

            if ($sessionId) {
                $stmt = $this->db->prepare("SELECT opening_cash_amount FROM cashier_sessions WHERE id = ? AND tenant_id = ? AND cashier_id = ?");
                $stmt->execute([$sessionId, $tenantId, $userId]);
                $summary['openingBalance'] = (float) ($stmt->fetchColumn() ?? 0);
            }

            $whereClauses = ['t.tenant_id = ?'];
            $paramsArray  = [$tenantId];
            if ($branchId)  { $whereClauses[] = 't.branch_id = ?';  $paramsArray[] = $branchId; }
            if ($sessionId) { $whereClauses[] = 't.session_id = ?'; $paramsArray[] = $sessionId; }
            $whereClauses[] = 't.created_at BETWEEN ? AND ?';
            $paramsArray[]  = $startDate;
            $paramsArray[]  = $endDate;
            $whereSql = implode(' AND ', $whereClauses);

            // For returns: session_id is on the linked sale, not on returns directly
            $returnsWhereClauses = ['r.tenant_id = ?'];
            $returnsParamsArray  = [$tenantId];
            if ($branchId)  { $returnsWhereClauses[] = 'r.branch_id = ?';    $returnsParamsArray[] = $branchId; }
            if ($sessionId) { $returnsWhereClauses[] = 's_link.session_id = ?'; $returnsParamsArray[] = $sessionId; }
            $returnsWhereClauses[] = 'r.created_at BETWEEN ? AND ?';
            $returnsParamsArray[]  = $startDate;
            $returnsParamsArray[]  = $endDate;
            $returnsWhereSql = implode(' AND ', $returnsWhereClauses);

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount),0) AS total_sales FROM sales t WHERE {$whereSql}");
            $stmt->execute($paramsArray);
            $summary['totalSales'] = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0);

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(r.total_amount),0) AS total_returns FROM returns r LEFT JOIN sales s_link ON s_link.id = r.sale_id WHERE {$returnsWhereSql} AND r.return_type = 'sale'");
            $stmt->execute($returnsParamsArray);
            $summary['totalReturns'] = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_returns'] ?? 0);

            // Cash in drawer — delegates to SalesAnalyticsHandler::breakdownSalesPayments
            // (same logic as original AnalyticsHandler::breakdownSalesPayments)
            $salesAnalytics = new SalesAnalyticsHandler($this->db);
            $breakdownReq   = $request->withQueryParams(array_merge($params, ['start_date' => $startDate, 'end_date' => $endDate]));
            $breakdownResp  = $salesAnalytics->breakdownSalesPayments($breakdownReq, $response);
            $breakdownData  = json_decode($breakdownResp->getBody()->__toString(), true)['data'] ?? [];
            $summary['cashInDrawer'] = (float) ($breakdownData['cash_drawer'] ?? 0);

            // Recent activities — returns uses LEFT JOIN to get session via linked sale
            $actSaleWhere    = $whereSql;
            $actReturnWhere  = $returnsWhereSql;
            $actCtWhere      = $whereSql;

            $unionSql = "
                (SELECT id, 'sale' as type, invoice_number as description, total_amount as amount, created_at as time FROM sales t WHERE {$actSaleWhere})
                UNION ALL
                (SELECT r.id, 'return' as type, CONCAT('مرتجع #', r.id) as description, -r.total_amount as amount, r.created_at as time FROM returns r LEFT JOIN sales s_link ON s_link.id = r.sale_id WHERE {$actReturnWhere} AND r.return_type = 'sale')
                UNION ALL
                (SELECT id, type, description, amount, created_at as time FROM cash_transactions t WHERE {$actCtWhere} AND type IN ('deposit','withdrawal'))
                ORDER BY time DESC LIMIT 10
            ";
            $stmt = $this->db->prepare($unionSql);
            $stmt->execute(array_merge($paramsArray, $returnsParamsArray, $paramsArray));
            $summary['recentActivities'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse($response, $summary, 200);
        } catch (\Throwable $e) {
            $this->logger->error('getCashierDashboardSummary error', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'حدث خطأ أثناء جلب ملخص لوحة تحكم الكاشير', 400);
        }
    }
}
