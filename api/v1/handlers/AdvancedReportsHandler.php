<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Throwable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;

class AdvancedReportsHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('advanced_reports');
    }

    private function resolveAccountId(int $tenantId, string $settingKeyName, string $fallbackCode): ?int
    {
        return $this->accounting->resolveAccountId($tenantId, $settingKeyName, $fallbackCode);
    }

    public function getDailyPerformance(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');

            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(total_amount), 0)
                FROM sales
                WHERE created_at BETWEEN ? AND ?
                  AND status IN ('paid', 'completed')
                  AND tenant_id = ?
            ");
            $stmt->execute([$todayStart, $todayEnd, $tenantId]);
            $totalSales = (float) $stmt->fetchColumn();

            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(grand_total), 0)
                FROM returns
                WHERE created_at BETWEEN ? AND ?
                  AND return_type = 'sale'
                  AND tenant_id = ?
            ");
            $stmt->execute([$todayStart, $todayEnd, $tenantId]);
            $totalReturns = (float) $stmt->fetchColumn();

            $netSales = $totalSales - $totalReturns;

            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(s.total_amount), 0) AS cash_total
                FROM sales s
                LEFT JOIN payment_methods pm
                  ON pm.id = s.payment_method_id
                 AND pm.tenant_id = s.tenant_id
                WHERE s.created_at BETWEEN ? AND ?
                  AND s.status IN ('paid', 'completed')
                  AND s.tenant_id = ?
                  AND pm.kind = 'cash'
            ");
            $stmt->execute([$todayStart, $todayEnd, $tenantId]);
            $cashInDrawer = (float) $stmt->fetchColumn();

            $sql = "
                (SELECT
                    id,
                    total_amount AS amount,
                    created_at,
                    'بيع فاتورة #' AS description_prefix,
                    'sale' AS type
                 FROM sales
                 WHERE status IN ('paid', 'completed')
                   AND created_at BETWEEN :start_date1 AND :end_date1
                   AND tenant_id = :tenant_id1)
                UNION ALL
                (SELECT
                    id,
                    -grand_total AS amount,
                    created_at,
                    'مرتجع فاتورة #' AS description_prefix,
                    'return' AS type
                 FROM returns
                 WHERE created_at BETWEEN :start_date2 AND :end_date2
                   AND return_type = 'sale'
                   AND tenant_id = :tenant_id2)
                ORDER BY created_at DESC
                LIMIT 5
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':start_date1' => $todayStart,
                ':end_date1' => $todayEnd,
                ':tenant_id1' => $tenantId,
                ':start_date2' => $todayStart,
                ':end_date2' => $todayEnd,
                ':tenant_id2' => $tenantId
            ]);

            $rawActivities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $recentActivities = array_map(
                static fn(array $activity): array => [
                    'id' => $activity['type'] . '_' . $activity['id'],
                    'description' => $activity['description_prefix'] . $activity['id'],
                    'amount' => (float) $activity['amount'],
                    'time' => date('h:i A', strtotime($activity['created_at']))
                ],
                $rawActivities
            );

            return $this->successResponse($response, [
                'totalSales' => $totalSales,
                'totalReturns' => $totalReturns,
                'netSales' => $netSales,
                'cashInDrawer' => $cashInDrawer,
                'recentActivities' => $recentActivities
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Daily Performance Report Error', [
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId ?? null
            ]);

            return $this->errorResponse($response, 'تعذّر جلب تقرير الأداء اليومي.', 500);
        }
    }

    public function getSalesPerformance(array $params, int $tenantId): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');
        $groupBy = $params['group_by'] ?? 'day';

        $groupFormats = [
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'quarter' => '%Y-%m',
            'year' => '%Y'
        ];
        $groupFormat = $groupFormats[$groupBy] ?? $groupFormats['day'];

        $sql = "
            SELECT
                DATE_FORMAT(s.created_at, '{$groupFormat}') AS period,
                COUNT(DISTINCT s.id) AS total_sales,
                COALESCE(SUM(s.total_amount), 0) AS revenue,
                COALESCE(SUM(s.total_profit), 0) AS profit,
                COUNT(DISTINCT s.customer_id) AS unique_customers,
                ROUND(AVG(s.total_amount), 2) AS average_sale,
                (
                    SELECT GROUP_CONCAT(p.name)
                    FROM sales_items si
                    JOIN products p ON p.id = si.product_id
                    WHERE si.sale_id IN (SELECT id FROM sales WHERE DATE_FORMAT(created_at, '{$groupFormat}') = DATE_FORMAT(s.created_at, '{$groupFormat}') AND tenant_id = :tenant_id)
                    GROUP BY si.sale_id
                    ORDER BY COUNT(*) DESC
                    LIMIT 1
                ) AS top_products
            FROM sales s
            WHERE s.created_at >= :start_date
              AND s.created_at < :end_date_plus_one
              AND s.tenant_id = :tenant_id
            GROUP BY period
            ORDER BY period DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate . ' 00:00:00',
            ':end_date_plus_one' => date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00',
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getInventoryAnalysis(array $params, int $tenantId): array
    {
        $sql = "
            SELECT
                p.id,
                p.name,
                (
                    SELECT COALESCE(SUM(wp.quantity), 0)
                    FROM branch_products wp
                    WHERE wp.product_id = p.id
                      AND wp.tenant_id = p.tenant_id
                ) AS total_stock_quantity,
                p.minimum_stock,
                p.unit_price,
                p.cost_price,
                (
                    SELECT COUNT(*)
                    FROM sales_items si
                    JOIN sales s ON s.id = si.sale_id
                    WHERE si.product_id = p.id
                      AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      AND s.tenant_id = :tenant_id
                ) AS sales_last_30_days,
                (
                    SELECT COALESCE(SUM(si.quantity), 0)
                    FROM sales_items si
                    JOIN sales s ON s.id = si.sale_id
                    WHERE si.product_id = p.id
                      AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      AND s.tenant_id = :tenant_id
                ) AS units_sold_last_30_days,
                (
                    (SELECT COALESCE(SUM(wp.quantity), 0)
                     FROM branch_products wp
                     WHERE wp.product_id = p.id AND wp.tenant_id = p.tenant_id) * p.cost_price
                ) AS inventory_value,
                CASE
                    WHEN p.cost_price > 0 THEN ROUND((p.unit_price - p.cost_price) / p.cost_price * 100, 2)
                    ELSE 0
                END AS margin_percentage
            FROM products p
            WHERE p.active = 1
              AND p.tenant_id = :tenant_id
            ORDER BY sales_last_30_days DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCustomerInsights(array $params, int $tenantId): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-365 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');

        $sql = "
            SELECT
                c.id,
                c.name,
                c.email,
                c.phone,
                COUNT(DISTINCT s.id) AS total_purchases,
                COALESCE(SUM(s.total_amount), 0) AS total_spent,
                ROUND(AVG(s.total_amount), 2) AS average_purchase,
                MAX(s.created_at) AS last_purchase_date,
                (
                    SELECT GROUP_CONCAT(DISTINCT p.name)
                    FROM sales_items si
                    JOIN sales s2 ON s2.id = si.sale_id
                    JOIN products p ON p.id = si.product_id
                    WHERE s2.customer_id = c.id
                    AND s2.tenant_id = :tenant_id
                    GROUP BY s2.customer_id
                    LIMIT 5
                ) AS favorite_products,
                (
                    SELECT COUNT(DISTINCT MONTH(s3.created_at))
                    FROM sales s3
                    WHERE s3.customer_id = c.id
                    AND s3.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    AND s3.tenant_id = :tenant_id
                ) AS active_months,
                DATEDIFF(NOW(), MIN(s.created_at)) AS days_since_first_purchase,
                DATEDIFF(NOW(), MAX(s.created_at)) AS days_since_last_purchase
            FROM customers c
            LEFT JOIN sales s ON s.customer_id = c.id AND s.tenant_id = :tenant_id
            WHERE c.tenant_id = :tenant_id AND s.created_at BETWEEN :start_date AND :end_date
            GROUP BY c.id
            ORDER BY total_spent DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate . ' 00:00:00',
            ':end_date' => $endDate . ' 23:59:59',
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFinancialSummary(array $params, int $tenantId): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');

        $salesSql = "
            SELECT
                COUNT(*) AS total_sales,
                COALESCE(SUM(total_amount), 0) AS total_revenue,
                COALESCE(SUM(total_profit), 0) AS total_profit,
                ROUND(AVG(total_amount), 2) AS average_sale,
                COUNT(DISTINCT customer_id) AS unique_customers
            FROM sales
            WHERE created_at BETWEEN :start_date AND :end_date
              AND tenant_id = :tenant_id
        ";

        $stmt = $this->db->prepare($salesSql);
        $stmt->execute([
            ':start_date' => $startDate . ' 00:00:00',
            ':end_date' => $endDate . ' 23:59:59',
            ':tenant_id' => $tenantId
        ]);
        $salesSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $expensesSql = "
            SELECT
                COALESCE(SUM(amount), 0) AS total_expenses,
                COUNT(*) AS expense_count
            FROM account_transactions
            WHERE transaction_date BETWEEN :start_date AND :end_date
              AND tenant_id = :tenant_id
        ";

        $stmt = $this->db->prepare($expensesSql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':tenant_id' => $tenantId
        ]);
        $expensesSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $arSql = "
            SELECT
                COUNT(*) AS unpaid_invoices,
                COALESCE(SUM(total_amount - paid_amount), 0) AS total_receivable
            FROM sales
            WHERE created_at <= :end_date
              AND tenant_id = :tenant_id
              AND payment_status != 'paid'
        ";

        $stmt = $this->db->prepare($arSql);
        $stmt->execute([
            ':end_date' => $endDate . ' 23:59:59',
            ':tenant_id' => $tenantId
        ]);
        $arSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $inventorySql = "
            SELECT
                COUNT(DISTINCT p.id) AS total_products,
                COALESCE(SUM(wp.quantity * p.cost_price), 0) AS total_inventory_value
            FROM products p
            JOIN branch_products wp
              ON wp.product_id = p.id
             AND wp.tenant_id = p.tenant_id
            WHERE p.active = 1
              AND p.tenant_id = :tenant_id
        ";

        $stmt = $this->db->prepare($inventorySql);
        $stmt->execute([':tenant_id' => $tenantId]);
        $inventorySummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalProfit = (float) ($salesSummary['total_profit'] ?? 0);
        $totalExpenses = (float) ($expensesSummary['total_expenses'] ?? 0);
        $totalRevenue = (float) ($salesSummary['total_revenue'] ?? 0);

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'sales' => $salesSummary,
            'account_transactions' => $expensesSummary,
            'accounts_receivable' => $arSummary,
            'inventory' => $inventorySummary,
            'net_profit' => $totalProfit - $totalExpenses,
            'profit_margin' => $totalRevenue > 0
                ? round(($totalProfit / $totalRevenue) * 100, 2)
                : 0
        ];
    }

    public function getBranchPerformance(array $params, int $tenantId): array
    {
        $sql = "
            SELECT
                b.id,
                b.name,
                COUNT(DISTINCT wp.product_id) AS total_products,
                COALESCE(SUM(wp.quantity), 0) AS total_items,
                COALESCE(SUM(wp.quantity * p.cost_price), 0) AS inventory_value,
                (
                    SELECT COUNT(*)
                    FROM inventory_transactions it
                    WHERE it.branch_to = b.id
                      AND it.tenant_id = b.tenant_id
                      AND it.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ) AS inbound_last_30_days,
                (
                    SELECT COUNT(*)
                    FROM inventory_transactions it
                    WHERE it.branch_from = b.id
                      AND it.tenant_id = b.tenant_id
                      AND it.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ) AS outbound_last_30_days,
                (
                    SELECT COUNT(DISTINCT wp2.product_id)
                    FROM branch_products wp2
                    JOIN products p2 ON p2.id = wp2.product_id AND p2.tenant_id = wp2.tenant_id
                    WHERE wp2.branch_id = b.id AND wp2.tenant_id = b.tenant_id
                    GROUP BY wp2.product_id
                    HAVING SUM(wp2.quantity) <= p2.minimum_stock
                ) AS low_stock_items,
                (
                    SELECT AVG(DATEDIFF(
                        it.created_at,
                        LAG(it.created_at) OVER (ORDER BY it.created_at)
                    ))
                    FROM inventory_transactions it
                    WHERE (it.branch_from = b.id OR it.branch_to = b.id) AND it.tenant_id = b.tenant_id
                    AND it.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ) AS avg_days_between_transactions
            FROM branches b
            LEFT JOIN branch_products wp ON wp.branch_id = b.id AND wp.tenant_id = b.tenant_id
            LEFT JOIN products p ON p.id = wp.product_id AND p.tenant_id = b.tenant_id
            WHERE b.tenant_id = :tenant_id
            GROUP BY b.id
            ORDER BY inventory_value DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupplierPerformance(array $params, int $tenantId): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-365 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');

        $sql = "
            SELECT
                s.id,
                s.name,
                COUNT(DISTINCT p.id) AS total_orders,
                COALESCE(SUM(p.total), 0) AS total_spent,
                ROUND(AVG(p.total), 2) AS average_order,
                MAX(p.created_at) AS last_order_date,
                (
                    SELECT COUNT(*)
                    FROM products pr
                    WHERE pr.supplier_id = s.id
                    AND pr.active = 1
                ) AS active_products,
                (
                    SELECT AVG(DATEDIFF(
                        delivery_date,
                        order_date
                    ))
                    FROM purchases p2
                    WHERE p2.supplier_id = s.id
                    AND p2.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ) AS avg_delivery_days,
                (
                    SELECT COUNT(*)
                    FROM returns r
                    JOIN purchases p3 ON p3.id = r.purchase_id
                    WHERE p3.supplier_id = s.id
                    AND r.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
                ) AS returns_last_year
            FROM suppliers s
            LEFT JOIN purchases p ON p.supplier_id = s.id AND p.tenant_id = :tenant_id
            WHERE s.tenant_id = :tenant_id AND p.created_at BETWEEN :start_date AND :end_date
            GROUP BY s.id
            ORDER BY total_spent DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate . ' 00:00:00',
            ':end_date' => $endDate . ' 23:59:59',
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProfitLossReport(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();

            $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $params['end_date'] ?? date('Y-m-d');
            $mode = strtolower((string) ($params['mode'] ?? 'basic'));
            $expenseSource = strtolower((string) ($params['expense_source'] ?? 'vouchers'));

            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $revenueSql = "
                SELECT
                    COALESCE(SUM(s.total_amount), 0) AS total_sales,
                    COALESCE(SUM(s.tax_amount), 0) AS total_tax,
                    COALESCE(SUM(s.total_amount + s.tax_amount), 0) AS grand_total
                FROM sales s
                WHERE s.tenant_id = :tenant_id
                  AND DATE(s.created_at) BETWEEN :start_date AND :end_date
            ";

            $stmt = $this->db->prepare($revenueSql);
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
            $salesData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $returnsSql = "
                SELECT
                    COALESCE(SUM(r.total_amount), 0) AS total_returns,
                    COALESCE(SUM(r.tax_amount), 0) AS returns_tax,
                    COALESCE(SUM(r.total_amount + r.tax_amount), 0) AS returns_grand_total
                FROM returns r
                WHERE r.tenant_id = :tenant_id
                  AND DATE(r.created_at) BETWEEN :start_date AND :end_date
            ";

            $stmt = $this->db->prepare($returnsSql);
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
            $returnsData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalRevenueVal = (float) ($salesData['total_sales'] ?? 0) - (float) ($returnsData['total_returns'] ?? 0);

            $merged = [];

            if (in_array($expenseSource, ['vouchers', 'both'], true)) {
                $expensesSql1 = "
                    SELECT
                        COALESCE(a.name, 'مصروفات أخرى') AS expense_category,
                        COALESCE(SUM(cv.amount), 0) AS total
                    FROM cash_vouchers cv
                    LEFT JOIN accounts a
                      ON a.id = cv.account_id
                     AND a.tenant_id = cv.tenant_id
                    WHERE cv.tenant_id = :tenant_id
                      AND cv.type = 'payment'
                      AND (a.type = 'expense' OR a.type IS NULL)
                      AND cv.date BETWEEN :start_date AND :end_date
                    GROUP BY COALESCE(a.name, 'مصروفات أخرى')
                ";

                $stmt = $this->db->prepare($expensesSql1);
                $stmt->execute([
                    ':tenant_id' => $tenantId,
                    ':start_date' => $startDate,
                    ':end_date' => $endDate
                ]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $key = $row['expense_category'] ?? 'other';
                    if (!isset($merged[$key])) {
                        $merged[$key] = ['expense_category' => $key, 'total' => 0.0];
                    }
                    $merged[$key]['total'] += (float) $row['total'];
                }
            }

            if (in_array($expenseSource, ['transactions', 'both'], true)) {
                $expensesSql2 = "
                    SELECT
                        COALESCE(reference_type, 'other') AS expense_category,
                        COALESCE(SUM(amount), 0) AS total
                    FROM cash_transactions
                    WHERE tenant_id = :tenant_id
                      AND type = 'expense'
                      AND DATE(created_at) BETWEEN :start_date AND :end_date
                    GROUP BY COALESCE(reference_type, 'other')
                ";

                $stmt = $this->db->prepare($expensesSql2);
                $stmt->execute([
                    ':tenant_id' => $tenantId,
                    ':start_date' => $startDate,
                    ':end_date' => $endDate
                ]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $key = $row['expense_category'] ?? 'other';
                    if (!isset($merged[$key])) {
                        $merged[$key] = ['expense_category' => $key, 'total' => 0.0];
                    }
                    $merged[$key]['total'] += (float) $row['total'];
                }
            }

            $expenses = array_values($merged);
            usort($expenses, static fn(array $a, array $b) => $b['total'] <=> $a['total']);

            $totalExpenses = array_sum(array_column($expenses, 'total'));

            $cogsSource = 'derived_sales';
            $cogs = 0.0;
            $returnsCOGS = 0.0;

            if ($mode === 'cogs') {
                $cogsAccountId = $this->resolveAccountId((int) $tenantId, 'cogs_account_id', '5101');
                $cogsFromAccounting = null;

                if ($cogsAccountId) {
                    $stmt = $this->db->prepare("
                        SELECT COALESCE(SUM(jel.debit_amount - jel.credit_amount), 0) AS cogs
                        FROM journal_entry_lines jel
                        INNER JOIN journal_entries je
                            ON je.id = jel.journal_entry_id
                           AND je.tenant_id = jel.tenant_id
                        WHERE jel.account_id = :acc
                          AND je.tenant_id = :tenant
                          AND DATE(je.entry_date) BETWEEN :start AND :end
                    ");
                    $stmt->execute([
                        ':acc' => $cogsAccountId,
                        ':tenant' => $tenantId,
                        ':start' => $startDate,
                        ':end' => $endDate
                    ]);

                    $cogsFromAccounting = (float) $stmt->fetchColumn();
                }

                if ($cogsFromAccounting !== null && $cogsFromAccounting > 0) {
                    $cogs = $cogsFromAccounting;
                    $cogsSource = 'accounting';
                } else {
                    $stmt = $this->db->prepare("
                        SELECT COALESCE(SUM(si.quantity * si.purchase_price), 0) AS total_cogs
                        FROM sales s
                        JOIN sales_items si
                          ON si.sale_id = s.id
                         AND si.tenant_id = s.tenant_id
                        WHERE s.tenant_id = :tenant_id
                          AND DATE(s.created_at) BETWEEN :start_date AND :end_date
                    ");
                    $stmt->execute([
                        ':tenant_id' => $tenantId,
                        ':start_date' => $startDate,
                        ':end_date' => $endDate
                    ]);
                    $cogs = (float) $stmt->fetchColumn();

                    $stmt = $this->db->prepare("
                        SELECT COALESCE(SUM(ri.quantity * COALESCE(si.purchase_price, p.purchase_price, 0)), 0) AS returns_cogs
                        FROM returns r
                        JOIN return_items ri
                          ON ri.return_id = r.id
                         AND ri.tenant_id = r.tenant_id
                        LEFT JOIN sales_items si
                          ON si.sale_id = r.sale_id
                         AND si.product_id = ri.product_id
                         AND si.tenant_id = ri.tenant_id
                        LEFT JOIN products p
                          ON p.id = ri.product_id
                         AND p.tenant_id = ri.tenant_id
                        WHERE r.tenant_id = :tenant_id
                          AND DATE(r.created_at) BETWEEN :start_date AND :end_date
                    ");
                    $stmt->execute([
                        ':tenant_id' => $tenantId,
                        ':start_date' => $startDate,
                        ':end_date' => $endDate
                    ]);
                    $returnsCOGS = (float) $stmt->fetchColumn();

                    $cogsSource = 'derived_sales';
                }

                $netCOGS = $cogs - $returnsCOGS;
                $grossProfit = $totalRevenueVal - $netCOGS;
                $netProfit = $grossProfit - $totalExpenses;
            } else {
                $grossProfit = $totalRevenueVal;
                $cogs = 0.0;
                $netProfit = $totalRevenueVal - $totalExpenses;
            }

            $result = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'revenue' => [
                    'total_revenue' => $totalRevenueVal,
                    'gross_profit' => $grossProfit,
                    'cogs' => $cogs,
                    'cogs_source' => $cogsSource
                ],
                'account_transactions' => $expenses,
                'summary' => [
                    'total_revenue' => $totalRevenueVal,
                    'total_expenses' => (float) $totalExpenses,
                    'gross_profit' => $grossProfit,
                    'cogs' => $cogs,
                    'net_profit' => (float) $netProfit
                ]
            ];

            return $this->successResponse($response, $result);
        } catch (\PDOException $e) {
            $this->logger->error('getProfitLossReport - Database error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'فشل في جلب تقرير الأرباح والخسائر', 500);
        } catch (Throwable $e) {
            $this->logger->error('getProfitLossReport - General error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'حدث خطأ غير متوقع', 500);
        }
    }
}