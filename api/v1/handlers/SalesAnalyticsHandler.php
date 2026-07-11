<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\MonologHandler;
use App\Services\CostingService;

/**
 * SalesAnalyticsHandler
 *
 * Handles sales-related analytics.
 * Real extraction from AnalyticsHandler — logic lives here, no delegation.
 */
class SalesAnalyticsHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('analytics');
    }

    // =========================================================
    // GET /analytics/trends
    // =========================================================

    public function analyzeTrends(Request $request, Response $response): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
        }

        $filters = $request->getQueryParams();
        $where   = ['s.tenant_id = ?'];
        $params  = [$tenantId];

        if (!empty($filters['start_date'])) { $where[] = 's.date >= ?'; $params[] = $filters['start_date']; }
        if (!empty($filters['end_date']))   { $where[] = 's.date <= ?'; $params[] = $filters['end_date']; }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT MONTH(s.date) as month, YEAR(s.date) as year,
                   COUNT(DISTINCT s.id) as total_orders,
                   SUM(si.quantity) as total_items,
                   SUM(si.quantity * si.sale_price) as total_revenue
            FROM sales s
            JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
            {$whereClause}
            GROUP BY YEAR(s.date), MONTH(s.date)
            ORDER BY year DESC, month DESC
        ");
        $stmt->execute($params);
        $seasonalTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT p.id, p.name, DATE_FORMAT(s.date, '%Y-%m') as month,
                   SUM(si.quantity) as total_quantity,
                   AVG(si.sale_price) as avg_price
            FROM sales s
            JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
            JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id
            {$whereClause}
            GROUP BY p.id, p.name, month
            ORDER BY p.id, month DESC
        ");
        $stmt->execute($params);
        $productTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->successResponse($response, [
            'seasonal_trends' => $seasonalTrends,
            'product_trends'  => $productTrends,
        ], 200);
    }

    // =========================================================
    // GET /reports/sales/daily-summary
    // =========================================================

    public function dailySalesSummary(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $params = $request->getQueryParams();
            $date   = isset($params['date']) ? trim((string) $params['date']) : null;
            if (!$date) {
                return $this->errorResponse($response, 'date (YYYY-MM-DD) is required', 400);
            }

            $ts = strtotime($date);
            if ($ts === false) {
                return $this->errorResponse($response, 'Invalid date format', 400);
            }

            $dayStart   = date('Y-m-d 00:00:00', $ts);
            $dayEnd     = date('Y-m-d 23:59:59', $ts);
            $posIds     = [];
            $paymentKind = null;
            $productId  = null;
            $categoryId = null;

            if (isset($params['pos_id'])) {
                $posIds = is_array($params['pos_id']) ? $params['pos_id'] : [$params['pos_id']];
            } elseif (isset($params['pos_ids'])) {
                $posIds = is_array($params['pos_ids']) ? $params['pos_ids'] : [$params['pos_ids']];
            }
            $posIds = array_values(array_filter(array_map('intval', $posIds), fn($v) => $v > 0));

            if (!empty($params['payment_kind'])) { $paymentKind = strtolower(trim((string) $params['payment_kind'])); }
            if (isset($params['product_id']))    { $productId  = (int) $params['product_id']; }
            if (isset($params['category_id']))   { $categoryId = (int) $params['category_id']; }

            // Total sales
            $whereSales  = ['s.tenant_id = ?', 's.created_at BETWEEN ? AND ?'];
            $salesParams = [$tenantId, $dayStart, $dayEnd];
            $joins       = ['JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id'];

            if ($productId)  { $whereSales[] = 'si.product_id = ?'; $salesParams[] = $productId; }
            if ($categoryId) {
                $joins[]      = 'JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id';
                $whereSales[] = 'p.category_id = ?';
                $salesParams[] = $categoryId;
            }
            if (!empty($posIds)) {
                $whereSales[] = 's.branch_id IN (' . implode(',', array_fill(0, count($posIds), '?')) . ')';
                foreach ($posIds as $pid) { $salesParams[] = $pid; }
            }
            if ($paymentKind) {
                $whereSales[] = "EXISTS (SELECT 1 FROM payments pmt LEFT JOIN payment_methods pm ON pm.id = pmt.payment_method_id AND pm.tenant_id = s.tenant_id WHERE pmt.sale_id = s.id AND pmt.is_draft = 0 AND pmt.status = 'completed' AND LOWER(pm.kind) = ?)";
                $salesParams[] = $paymentKind;
            }

            // Total sales + order count
            $sqlSales = 'SELECT COALESCE(SUM(si.quantity * si.sale_price), 0) AS total_sales_amount, COUNT(DISTINCT s.id) AS order_count FROM sales s ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $whereSales);
            $stmt = $this->db->prepare($sqlSales);
            $stmt->execute($salesParams);
            $salesRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalSalesAmount = (float) ($salesRow['total_sales_amount'] ?? 0);
            $orderCount = (int) ($salesRow['order_count'] ?? 0);

            // Total returns
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(ri.quantity * ri.unit_price), 0) AS total_returns_amount FROM returns r JOIN return_items ri ON ri.return_id = r.id WHERE r.tenant_id = ? AND r.return_type = 'sale' AND r.created_at BETWEEN ? AND ?");
            $stmt->execute([$tenantId, $dayStart, $dayEnd]);
            $totalReturnsAmount = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_returns_amount'] ?? 0);

            // COGS via WAC
            $cogsTotal = 0.0;
            try {
                $costing    = new CostingService($this->db);
                $whereIds   = ['s.tenant_id = ?', 's.created_at BETWEEN ? AND ?'];
                $idParams   = [$tenantId, $dayStart, $dayEnd];
                $idJoins    = [];

                if ($productId)  { $idJoins[] = 'JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id'; $whereIds[] = 'si.product_id = ?'; $idParams[] = $productId; }
                if ($categoryId) {
                    if (!in_array('JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id', $idJoins)) { $idJoins[] = 'JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id'; }
                    $idJoins[] = 'JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id';
                    $whereIds[] = 'p.category_id = ?'; $idParams[] = $categoryId;
                }
                if (!empty($posIds)) { $whereIds[] = 's.branch_id IN (' . implode(',', array_fill(0, count($posIds), '?')) . ')'; foreach ($posIds as $pid) { $idParams[] = $pid; } }
                if ($paymentKind)    { $whereIds[] = "EXISTS (SELECT 1 FROM payments pmt LEFT JOIN payment_methods pm ON pm.id = pmt.payment_method_id AND pm.tenant_id = s.tenant_id WHERE pmt.sale_id = s.id AND pmt.is_draft = 0 AND pmt.status = 'completed' AND LOWER(pm.kind) = ?)"; $idParams[] = $paymentKind; }

                $sqlIds = 'SELECT s.id, s.created_at FROM sales s ' . implode(' ', $idJoins) . ' WHERE ' . implode(' AND ', $whereIds);
                $stmt   = $this->db->prepare($sqlIds);
                $stmt->execute($idParams);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $s) {
                    $cogsTotal += (float) $costing->computeCOGSForSale((int) $tenantId, (int) $s['id'], $s['created_at'] ?? null);
                }
                $cogsTotal = round($cogsTotal, 2);
            } catch (\Throwable $e) {
                $this->logger->warning('Error computing COGS for daily summary', [
                    'message' => $e->getMessage()
                ]);
                $cogsTotal = 0.0;
            }

            // Sales by POS (Branch)
            $salesByPos = [];
            $sqlByPos = "SELECT s.branch_id, b.name as branch_name, 
                         COALESCE(SUM(si.quantity * si.sale_price), 0) AS total_sales,
                         COUNT(DISTINCT s.id) AS order_count
                         FROM sales s 
                         JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
                         LEFT JOIN branches b ON b.id = s.branch_id AND b.tenant_id = s.tenant_id
                         WHERE s.tenant_id = ? AND s.created_at BETWEEN ? AND ?
                         GROUP BY s.branch_id, b.name";
            $stmt = $this->db->prepare($sqlByPos);
            $stmt->execute([$tenantId, $dayStart, $dayEnd]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $salesByPos[] = [
                    'pos_id' => (int) $row['branch_id'],
                    'name' => $row['branch_name'] ?: 'فرع #' . $row['branch_id'],
                    'totalSales' => (float) $row['total_sales'],
                    'orderCount' => (int) $row['order_count']
                ];
            }

            // Sales by Payment Method
            $salesByPayment = [];
            $sqlByPayment = "SELECT pm.name, pm.kind,
                             COALESCE(SUM(pmt.amount), 0) AS total_amount
                             FROM payments pmt
                             LEFT JOIN payment_methods pm ON pm.id = pmt.payment_method_id AND pm.tenant_id = pmt.tenant_id
                             JOIN sales s ON s.id = pmt.sale_id AND s.tenant_id = pmt.tenant_id
                             WHERE pmt.tenant_id = ? 
                             AND pmt.is_draft = 0 
                             AND pmt.status = 'completed'
                             AND s.created_at BETWEEN ? AND ?
                             GROUP BY pm.id, pm.name, pm.kind";
            $stmt = $this->db->prepare($sqlByPayment);
            $stmt->execute([$tenantId, $dayStart, $dayEnd]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $salesByPayment[] = [
                    'name' => $row['name'] ?: ($row['kind'] ?: 'غير محدد'),
                    'totalSales' => (float) $row['total_amount']
                ];
            }

            // Recent Transactions (Last 20)
            $recentTransactions = [];
            $sqlRecent = "SELECT s.id, s.created_at, s.total_amount, b.name as pos_name,
                          pm.name as payment_method
                          FROM sales s
                          LEFT JOIN branches b ON b.id = s.branch_id AND b.tenant_id = s.tenant_id
                          LEFT JOIN payments pmt ON pmt.sale_id = s.id AND pmt.tenant_id = s.tenant_id AND pmt.is_draft = 0
                          LEFT JOIN payment_methods pm ON pm.id = pmt.payment_method_id AND pm.tenant_id = pmt.tenant_id
                          WHERE s.tenant_id = ? AND s.created_at BETWEEN ? AND ?
                          ORDER BY s.created_at DESC
                          LIMIT 20";
            $stmt = $this->db->prepare($sqlRecent);
            $stmt->execute([$tenantId, $dayStart, $dayEnd]);
            
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $saleId = (int) $row['id'];
                $saleDate = $row['created_at'];
                
                // Calculate COGS for this sale
                $saleCogs = 0.0;
                try {
                    $costing = new CostingService($this->db);
                    $saleCogs = (float) $costing->computeCOGSForSale($tenantId, $saleId, $saleDate);
                } catch (\Throwable $e) {
                    $this->logger->warning('Error computing COGS for individual sale', [
                        'sale_id' => $saleId,
                        'message' => $e->getMessage()
                    ]);
                    $saleCogs = 0.0;
                }
                
                $amount = (float) $row['total_amount'];
                $profit = $amount - $saleCogs;
                
                $recentTransactions[] = [
                    'id' => $saleId,
                    'time' => $row['created_at'],
                    'posName' => $row['pos_name'] ?: 'غير محدد',
                    'amount' => $amount,
                    'cogs' => round($saleCogs, 2),
                    'profit' => round($profit, 2),
                    'paymentMethod' => $row['payment_method'] ?: 'غير محدد'
                ];
            }

            return $this->successResponse($response, [
                'date'                 => date('Y-m-d', $ts),
                'total_sales_amount'   => $totalSalesAmount,
                'total_returns_amount' => $totalReturnsAmount,
                'cogs_total'           => $cogsTotal,
                'gross_profit'         => round(($totalSalesAmount - $totalReturnsAmount) - $cogsTotal, 2),
                'order_count'          => $orderCount,
                'salesByPos'           => $salesByPos,
                'salesByPayment'       => $salesByPayment,
                'recentTransactions'   => $recentTransactions,
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('dailySalesSummary error', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في حساب الملخص اليومي', 500);
        }
    }

    // =========================================================
    // GET /analytics/sales-payments-breakdown
    // =========================================================

    public function breakdownSalesPayments(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
            }

            $params      = $request->getQueryParams();
            $where       = ['s.tenant_id = ?'];
            $queryParams = [$tenantId];

            if (!empty($params['start_date'])) { $where[] = 'DATE(s.created_at) >= ?'; $queryParams[] = $params['start_date']; }
            if (!empty($params['end_date']))   { $where[] = 'DATE(s.created_at) <= ?'; $queryParams[] = $params['end_date']; }
            if (!empty($params['session_id'])) { $where[] = 's.session_id = ?';        $queryParams[] = $params['session_id']; }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            // Payment method kind map
            $stmt = $this->db->prepare("SELECT id, kind FROM payment_methods WHERE tenant_id = ? ORDER BY id ASC");
            $stmt->execute([$tenantId]);
            $methodKindMap = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $methodKindMap[$m['id']] = strtolower((string) ($m['kind'] ?? 'other'));
            }

            // Completed sales
            $stmt = $this->db->prepare("SELECT s.id as sale_id, s.payment_method_id, (s.total_amount + COALESCE(s.tax_amount,0)) as total FROM sales s {$whereSql} AND s.status != 'cancelled'");
            $stmt->execute($queryParams);
            $completedSalesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Credit sales (remaining balance)
            $creditSql = "SELECT s.id as sale_id, s.payment_method_id,
                                 (s.total_amount + COALESCE(s.tax_amount,0)) as total_with_tax,
                                 COALESCE((SELECT SUM(amount) FROM payments WHERE sale_id = s.id AND is_draft = 0 AND status = 'completed'), 0) as paid_amount,
                                 ((s.total_amount + COALESCE(s.tax_amount,0)) - COALESCE((SELECT SUM(amount) FROM payments WHERE sale_id = s.id AND is_draft = 0 AND status = 'completed'), 0)) as remaining
                          FROM sales s
                          LEFT JOIN payment_methods pm ON pm.id = s.payment_method_id AND pm.tenant_id = ?
                          {$whereSql} AND LOWER(COALESCE(pm.kind,'credit')) = 'credit'
                          HAVING remaining > 0";
            $stmt = $this->db->prepare($creditSql);
            $stmt->execute(array_merge([$tenantId], $queryParams));
            $creditSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Returns
            $stmt = $this->db->prepare("SELECT r.sale_id, s.payment_method_id, SUM(r.total_amount + COALESCE(r.tax_amount,0)) as total FROM returns r LEFT JOIN sales s ON s.id = r.sale_id {$whereSql} AND r.return_type = 'sale' AND r.status = 'completed' GROUP BY r.sale_id, s.payment_method_id");
            $stmt->execute($queryParams);
            $returnRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cashTotal = $cardTotal = $walletTotal = $creditTotal = $returnsTotal = 0;
            $returnsBySale = [];

            foreach ($returnRows as $row) {
                $returnsTotal += (float) $row['total'];
                $returnsBySale[$row['sale_id']] = ($returnsBySale[$row['sale_id']] ?? 0) + (float) $row['total'];
            }

            foreach ($completedSalesRows as $row) {
                $kind = $methodKindMap[$row['payment_method_id']] ?? 'other';
                if ($kind === 'credit') continue;
                $total        = (float) $row['total'];
                $returnAmount = $returnsBySale[$row['sale_id']] ?? 0;
                switch ($kind) {
                    case 'cash':   $cashTotal   += $total - $returnAmount; break;
                    case 'card':
                    case 'bank_card': $cardTotal += $total - $returnAmount; break;
                    case 'wallet':
                    case 'bank_wallet': $walletTotal += $total - $returnAmount; break;
                }
                unset($returnsBySale[$row['sale_id']]);
            }

            foreach ($creditSales as $sale) {
                $returnAmount  = $returnsBySale[$sale['sale_id']] ?? 0;
                $creditTotal  += (float) $sale['remaining'] - $returnAmount;
                unset($returnsBySale[$sale['sale_id']]);
            }

            // Remaining unmatched returns
            foreach ($returnsBySale as $saleId => $amount) {
                $stmt = $this->db->prepare("SELECT payment_method_id FROM sales WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$saleId, $tenantId]);
                $pmId = $stmt->fetchColumn();
                $kind = $methodKindMap[$pmId] ?? 'other';
                switch ($kind) {
                    case 'cash':   $cashTotal   -= $amount; break;
                    case 'credit': $creditTotal -= $amount; break;
                    case 'card':
                    case 'bank_card': $cardTotal -= $amount; break;
                    case 'wallet':
                    case 'bank_wallet': $walletTotal -= $amount; break;
                }
            }

            return $this->successResponse($response, [
                'cash'         => round($cashTotal, 2),
                'card'         => round($cardTotal, 2),
                'wallet'       => round($walletTotal, 2),
                'credit'       => round($creditTotal, 2),
                'returns'      => round($returnsTotal, 2),
                'cash_drawer'  => round($cashTotal + $walletTotal, 2),
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('breakdownSalesPayments error', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'حدث خطأ أثناء حساب تفاصيل المدفوعات', 400);
        }
    }

    // =========================================================
    // GET /analytics/sales
    // =========================================================

    public function analyzeSales(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
            }

            $params  = $request->getQueryParams();
            $filters = [];

            if (!empty($params['date']))       { $filters['start_date'] = $params['date']; $filters['end_date'] = $params['date']; }
            if (!empty($params['start_date'])) { $filters['start_date'] = $params['start_date']; }
            if (!empty($params['end_date']))   { $filters['end_date']   = $params['end_date']; }
            if (!empty($params['product_id'])) { $filters['product_id'] = $params['product_id']; }
            if (!empty($params['category_id'])){ $filters['category_id']= $params['category_id']; }

            $whereSales   = [];
            $whereReturns = [];
            $queryParams  = [];

            if (!empty($filters['start_date'])) { $whereSales[] = 'DATE(s.created_at) >= ?'; $whereReturns[] = 'DATE(r.created_at) >= ?'; $queryParams[] = $filters['start_date']; }
            if (!empty($filters['end_date']))   { $whereSales[] = 'DATE(s.created_at) <= ?'; $whereReturns[] = 'DATE(r.created_at) <= ?'; $queryParams[] = $filters['end_date']; }
            if (!empty($filters['product_id'])) { $whereSales[] = 'si.product_id = ?';  $queryParams[] = $filters['product_id']; }
            if (!empty($filters['category_id'])){ $whereSales[] = 'p.category_id = ?';  $queryParams[] = $filters['category_id']; }

            // POS filter
            $posIds = [];
            if (isset($params['pos_id']))  { $posIds = is_array($params['pos_id'])  ? $params['pos_id']  : [$params['pos_id']]; }
            elseif (isset($params['pos_ids'])) { $posIds = is_array($params['pos_ids']) ? $params['pos_ids'] : [$params['pos_ids']]; }
            $posIds = array_values(array_filter(array_map('intval', $posIds), fn($v) => $v > 0));
            if (!empty($posIds)) {
                $whereSales[] = 's.branch_id IN (' . implode(',', array_fill(0, count($posIds), '?')) . ')';
                foreach ($posIds as $pid) { $queryParams[] = $pid; }
            }

            // Payment kind filter
            $paymentKind = !empty($params['payment_kind']) ? strtolower(trim((string) $params['payment_kind'])) : null;
            if ($paymentKind) {
                $whereSales[] = "EXISTS (SELECT 1 FROM payments p LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id AND pm.tenant_id = s.tenant_id WHERE p.sale_id = s.id AND p.is_draft = 0 AND p.status = 'completed' AND LOWER(pm.kind) = ?)";
                $queryParams[] = $paymentKind;
            }

            $whereSales[]   = 's.tenant_id = ?';
            $whereReturns[] = 'r.tenant_id = ?';
            $clauseSales   = 'WHERE ' . implode(' AND ', $whereSales);
            $clauseReturns = 'WHERE ' . implode(' AND ', $whereReturns);

            // Build per-query params
            $paramsSales = [];
            if (!empty($filters['start_date'])) { $paramsSales[] = $filters['start_date']; }
            if (!empty($filters['end_date']))   { $paramsSales[] = $filters['end_date'] . ' 23:59:59'; }
            if (!empty($filters['product_id'])) { $paramsSales[] = $filters['product_id']; }
            if (!empty($filters['category_id'])){ $paramsSales[] = $filters['category_id']; }
            if (!empty($posIds)) { foreach ($posIds as $pid) { $paramsSales[] = $pid; } }
            if ($paymentKind)    { $paramsSales[] = $paymentKind; }
            $paramsSales[] = $tenantId;

            $paramsReturns = [];
            if (!empty($filters['start_date'])) { $paramsReturns[] = $filters['start_date']; }
            if (!empty($filters['end_date']))   { $paramsReturns[] = $filters['end_date'] . ' 23:59:59'; }
            $paramsReturns[] = $tenantId;

            // Total sales count
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_sales FROM sales s {$clauseSales}");
            $stmt->execute($paramsSales);
            $totalSales = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

            // Cash in drawer via breakdownSalesPayments (now lives in this class)
            $breakdownReq  = $request->withQueryParams(['start_date' => $filters['start_date'] ?? null, 'end_date' => $filters['end_date'] ?? null]);
            $breakdownResp = $this->breakdownSalesPayments($breakdownReq, $response);
            $breakdownData = json_decode($breakdownResp->getBody()->__toString(), true)['data'] ?? [];
            $cashInDrawer  = (float) ($breakdownData['cash_drawer'] ?? 0);

            // Daily sales
            $stmt = $this->db->prepare("SELECT DATE(s.created_at) as date, COUNT(DISTINCT s.id) as total_orders, SUM(si.quantity) as total_items, SUM(si.quantity * si.sale_price) as total_revenue FROM sales s JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id {$clauseSales} GROUP BY DATE(s.created_at) ORDER BY s.created_at DESC");
            $stmt->execute($paramsSales);
            $dailySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $selectedDate    = !empty($params['date']) ? $params['date'] : null;
            $todayOrders     = 0; $todayRevenue = 0.0; $todayAvgInvoice = 0.0;
            if ($selectedDate) {
                foreach ($dailySales as $row) {
                    if ($row['date'] === $selectedDate) {
                        $todayOrders     = (int)   $row['total_orders'];
                        $todayRevenue    = (float)  $row['total_revenue'];
                        $todayAvgInvoice = $todayOrders > 0 ? $todayRevenue / $todayOrders : 0.0;
                        break;
                    }
                }
            }

            // Top products
            $stmt = $this->db->prepare("SELECT p.id, p.name, p.product_code, COUNT(DISTINCT s.id) as order_count, SUM(si.quantity) as total_quantity, SUM(si.quantity * si.sale_price) as total_revenue FROM sales s JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id {$clauseSales} GROUP BY p.id, p.name, p.product_code ORDER BY total_quantity DESC LIMIT 10");
            $stmt->execute($paramsSales);
            $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Top categories
            $stmt = $this->db->prepare("SELECT c.id, c.name, COUNT(DISTINCT s.id) as order_count, SUM(si.quantity) as total_quantity, SUM(si.quantity * si.sale_price) as total_revenue FROM sales s JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id JOIN categories c ON c.id = p.category_id {$clauseSales} GROUP BY c.id, c.name ORDER BY total_quantity DESC");
            $stmt->execute($paramsSales);
            $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sales totals
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(s.total_amount),0) AS total_sales_amount, COALESCE(SUM(s.tax_amount),0) AS total_tax_amount, COALESCE(SUM(s.total_amount + s.tax_amount),0) AS grand_total FROM sales s {$clauseSales}");
            $stmt->execute($paramsSales);
            $salesTotals       = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalSalesAmount  = (float) $salesTotals['total_sales_amount'];
            $totalTaxAmount    = (float) $salesTotals['total_tax_amount'];
            $grandTotal        = (float) $salesTotals['grand_total'];

            // Returns totals
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(r.total_amount),0) AS total_returns_amount, COALESCE(SUM(r.tax_amount),0) AS total_returns_tax, COALESCE(SUM(r.total_amount + r.tax_amount),0) AS returns_grand_total FROM returns r {$clauseReturns}");
            $stmt->execute($paramsReturns);
            $returnsTotals      = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalReturnsAmount = (float) $returnsTotals['total_returns_amount'];
            $totalReturnsTax    = (float) $returnsTotals['total_returns_tax'];
            $returnsGrandTotal  = (float) $returnsTotals['returns_grand_total'];

            $netSales      = $totalSalesAmount - $totalReturnsAmount;
            $netGrandTotal = $grandTotal - $returnsGrandTotal;

            // by_payment from breakdownData
            $byPayment = []; $bestPaymentMethod = 'N/A';
            $pmAmounts = ['cash' => $breakdownData['cash'] ?? 0, 'card' => $breakdownData['card'] ?? 0, 'wallet' => $breakdownData['wallet'] ?? 0, 'credit' => $breakdownData['credit'] ?? 0];
            $max = -1;
            foreach ($pmAmounts as $method => $amount) {
                if ($amount > 0) {
                    $byPayment[] = ['method' => ucfirst($method), 'amount' => round($amount, 2)];
                    if ($amount > $max) { $max = $amount; $bestPaymentMethod = ucfirst($method); }
                }
            }

            // by_pos
            $byPos = [];
            try {
                $posWhere = ['s.tenant_id = ?']; $posParams = [$tenantId];
                if (!empty($filters['start_date'])) { $posWhere[] = 'DATE(s.created_at) >= ?'; $posParams[] = $filters['start_date']; }
                if (!empty($filters['end_date']))   { $posWhere[] = 'DATE(s.created_at) <= ?'; $posParams[] = $filters['end_date']; }
                $pkFilter = $paymentKind ? " AND EXISTS (SELECT 1 FROM payments p LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id AND pm.tenant_id = s.tenant_id WHERE p.sale_id = s.id AND p.is_draft = 0 AND p.status = 'completed' AND LOWER(pm.kind) = ?)" : '';
                $stmt = $this->db->prepare("SELECT s.branch_id as pos_id, b.name as pos_name, SUM(si.quantity * si.sale_price) as amount FROM sales s LEFT JOIN branches b ON b.id = s.branch_id AND (b.tenant_id = s.tenant_id OR b.tenant_id IS NULL) JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id WHERE " . implode(' AND ', $posWhere) . $pkFilter . " GROUP BY s.branch_id, b.name");
                $stmt->execute($paymentKind ? array_merge($posParams, [$paymentKind]) : $posParams);
                $byPos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $this->logger->warning('Error fetching sales by POS', [
                    'message' => $e->getMessage()
                ]);
                $byPos = [];
            }

            // Latest transactions
            $latestTx = [];
            try {
                $ltWhere = ['s.tenant_id = ?']; $ltParams = [$tenantId];
                if (!empty($filters['start_date'])) { $ltWhere[] = 'DATE(s.created_at) >= ?'; $ltParams[] = $filters['start_date']; }
                if (!empty($filters['end_date']))   { $ltWhere[] = 'DATE(s.created_at) <= ?'; $ltParams[] = $filters['end_date']; }
                $pkExist = $paymentKind ? " AND EXISTS (SELECT 1 FROM payments p LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id AND pm.tenant_id = s.tenant_id WHERE p.sale_id = s.id AND p.is_draft = 0 AND p.status = 'completed' AND LOWER(pm.kind) = ?)" : '';
                $stmt = $this->db->prepare("SELECT s.id as sale_id, s.created_at as time, s.branch_id as pos_id, (SELECT pm.name FROM payments p LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id AND pm.tenant_id = s.tenant_id WHERE p.sale_id = s.id AND p.is_draft = 0 AND p.status = 'completed' ORDER BY p.amount DESC LIMIT 1) as payment_method, (SELECT SUM(si2.quantity * si2.sale_price) FROM sales_items si2 WHERE si2.sale_id = s.id) as amount FROM sales s WHERE " . implode(' AND ', $ltWhere) . $pkExist . " ORDER BY s.created_at DESC LIMIT 10");
                $stmt->execute($paymentKind ? array_merge($ltParams, [$paymentKind]) : $ltParams);
                $latestTx = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // COGS per transaction
                $costing = new CostingService($this->db);
                foreach ($latestTx as &$tx) {
                    $sid = (int) ($tx['sale_id'] ?? 0);
                    if ($sid > 0) {
                        $c = (float) $costing->computeCOGSForSale((int) $tenantId, $sid, $tx['time'] ?? null);
                        if ($c <= 0.0000001) {
                            $fb = $this->db->prepare("SELECT COALESCE(SUM(si.quantity * si.purchase_price),0) FROM sales_items si WHERE si.sale_id = ? AND si.tenant_id = ?");
                            $fb->execute([$sid, (int) $tenantId]);
                            $pp = (float) $fb->fetchColumn();
                            if ($pp > 0) $c = $pp;
                        }
                        $tx['cogs']   = round($c, 2);
                        $tx['profit'] = round((float) ($tx['amount'] ?? 0) - $c, 2);
                    } else {
                        $tx['cogs'] = 0.0; $tx['profit'] = (float) ($tx['amount'] ?? 0);
                    }
                }
                unset($tx);
            } catch (\Throwable $e) {
                $this->logger->warning('Error fetching latest transactions', [
                    'message' => $e->getMessage()
                ]);
                $latestTx = [];
            }

            // Period COGS
            $cogsTotal = 0.0;
            try {
                $costing  = new CostingService($this->db);
                $siJoin   = (!empty($filters['product_id']) || !empty($filters['category_id'])) ? ' JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id ' : '';
                $pJoin    = !empty($filters['category_id']) ? ' JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id ' : '';
                $stmt     = $this->db->prepare("SELECT s.id, s.created_at FROM sales s {$siJoin}{$pJoin} {$clauseSales} GROUP BY s.id, s.created_at");
                $stmt->execute($paramsSales);
                $salesRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // ✅ Batch-fetch fallback COGS (purchase_price) for all sales in one query
                // to avoid N+1 when CostingService returns zero.
                $saleIds = array_column($salesRows, 'id');
                $fallbackCogs = []; // [sale_id => float]
                if (!empty($saleIds)) {
                    $placeholders = implode(',', array_fill(0, count($saleIds), '?'));
                    $fbBatch = $this->db->prepare(
                        "SELECT si.sale_id,
                                COALESCE(SUM(si.quantity * si.purchase_price), 0) AS cogs
                         FROM   sales_items si
                         WHERE  si.tenant_id = ?
                           AND  si.sale_id IN ($placeholders)
                         GROUP BY si.sale_id"
                    );
                    $fbBatch->execute(array_merge([(int) $tenantId], $saleIds));
                    foreach ($fbBatch->fetchAll(PDO::FETCH_ASSOC) as $fbRow) {
                        $fallbackCogs[(int) $fbRow['sale_id']] = (float) $fbRow['cogs'];
                    }
                }

                foreach ($salesRows as $row) {
                    $sid = (int) $row['id'];
                    $c   = (float) $costing->computeCOGSForSale((int) $tenantId, $sid, $row['created_at'] ?? null);
                    if ($c <= 0.0000001) {
                        // Use pre-fetched fallback — no extra DB round-trip per sale
                        $pp = $fallbackCogs[$sid] ?? 0.0;
                        if ($pp > 0) $c = $pp;
                    }
                    $cogsTotal += $c;
                }
                $cogsTotal = round($cogsTotal, 2);
            } catch (\Throwable $e) {
                $this->logger->warning('Error computing COGS in analyzeSales', [
                    'message' => $e->getMessage()
                ]);
                $cogsTotal = 0.0;
            }

            // Returns COGS calculation
            // Use single batch query instead of one query per return (N+1 fix)
            $returnsCOGS = 0.0;
            try {
                $stmt = $this->db->prepare("SELECT r.id, r.sale_id FROM returns r {$clauseReturns}");
                $stmt->execute($paramsReturns);
                $returnsRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                if (!empty($returnsRows)) {
                    $returnIds = array_column($returnsRows, 'id');
                    $saleMap   = array_column($returnsRows, 'sale_id', 'id'); // [return_id => sale_id]

                    $placeholders = implode(',', array_fill(0, count($returnIds), '?'));

                    // One query: fetch all return_items with their cost_price for the whole result set
                    $chkBatch = $this->db->prepare(
                        "SELECT
                             ri.return_id,
                             ri.quantity,
                             COALESCE(si.purchase_price, p.purchase_price, 0) AS cost_price
                         FROM   return_items ri
                         -- join to sales_items using the sale linked to this return
                         LEFT JOIN sales_items si
                             ON  si.sale_id    = ri.sale_id
                             AND si.product_id = ri.product_id
                             AND si.tenant_id  = ri.tenant_id
                         LEFT JOIN products p
                             ON  p.id         = ri.product_id
                             AND p.tenant_id  = ri.tenant_id
                         WHERE  ri.return_id IN ($placeholders)
                           AND  ri.tenant_id = ?"
                    );
                    $chkBatch->execute(array_merge($returnIds, [(int) $tenantId]));

                    foreach ($chkBatch->fetchAll(PDO::FETCH_ASSOC) as $item) {
                        $returnsCOGS += (float) $item['quantity'] * (float) $item['cost_price'];
                    }
                }
                $returnsCOGS = round($returnsCOGS, 2);
            } catch (\Throwable $e) {
                $this->logger->warning('Error computing returns COGS in analyzeSales', [
                    'message' => $e->getMessage()
                ]);
                $returnsCOGS = 0.0;
            }

            $netCOGS = $cogsTotal - $returnsCOGS;

            return $this->successResponse($response, [
                'total_sales_amount'   => $totalSalesAmount,
                'total_tax_amount'     => $totalTaxAmount,
                'grand_total'          => $grandTotal,
                'total_returns_amount' => $totalReturnsAmount,
                'total_returns_tax'    => $totalReturnsTax,
                'returns_grand_total'  => $returnsGrandTotal,
                'net_sales'            => $netSales,
                'net_grand_total'      => $netGrandTotal,
                'cash_in_drawer'       => $cashInDrawer,
                'cogs_total'           => $cogsTotal,
                'returns_cogs'         => $returnsCOGS,
                'net_cogs'             => $netCOGS,
                'gross_profit'         => round(($totalSalesAmount - $totalReturnsAmount) - $netCOGS, 2),
                'daily_sales'          => $dailySales,
                'top_products'         => $topProducts,
                'top_categories'       => $topCategories,
                'total_sales'          => $todayRevenue,
                'invoices_count'       => $todayOrders,
                'avg_invoice'          => $todayAvgInvoice,
                'best_payment_method'  => $bestPaymentMethod,
                'by_pos'               => $byPos,
                'by_payment'           => $byPayment,
                'latest'               => $latestTx,
                'payment_breakdown'    => [
                    'cash'         => round($breakdownData['cash']         ?? 0, 2),
                    'card'         => round($breakdownData['card']         ?? 0, 2),
                    'wallet'       => round($breakdownData['wallet']       ?? 0, 2),
                    'credit'       => round($breakdownData['credit']       ?? 0, 2),
                    'returns'      => round($breakdownData['returns']      ?? 0, 2),
                    'cash_drawer'  => round($breakdownData['cash_drawer']  ?? 0, 2),
                ],
            ], 200);
        } catch (\Exception $e) {
            $this->logger->error('analyzeSales error', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'حدث خطأ أثناء تحليل المبيعات', 400);
        }
    }
}
