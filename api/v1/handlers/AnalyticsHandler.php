<?php

namespace App\Handlers;

use PDO;
use Exception;
use DateTime;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\CostingService;
use App\Services\MonologHandler;

class AnalyticsHandler extends BaseHandler {
    public function __construct(PDO $db) {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('analytics');
    }
    
    /**
     * Get daily cash drawer summary for when session mode is disabled
     */
public function analyzeSuppliers(Request $request, Response $response) {
    $tenantId = $this->extractTenantId($request);
    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
    }

    $queryParams = $request->getQueryParams();
    $where = ["po.tenant_id = ?"];
    $params = [$tenantId];

    if (!empty($queryParams['start_date'])) {
        $where[] = "po.order_date >= ?";
        $params[] = $queryParams['start_date'];
    }

    if (!empty($queryParams['end_date'])) {
        $where[] = "po.order_date <= ?";
        $params[] = $queryParams['end_date'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $this->db->prepare("
        SELECT 
            s.id,
            s.name,
            COUNT(po.id) as total_orders,
            SUM(po.total_amount) as total_spent,
            MAX(po.order_date) as last_order_date
        FROM suppliers s
        JOIN purchase_orders po ON po.supplier_id = s.id
        $whereClause
        GROUP BY s.id, s.name
        ORDER BY total_spent DESC
    ");

    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $this->successResponse($response, $suppliers
    , 200);
}


    /**
     * تحليل العملاء
     */
public function analyzeCustomers(Request $request, Response $response) {
    $tenantId = $this->extractTenantId($request);
    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
    }

    // جلب فلاتر البحث من الطلب
    $filters = $request->getQueryParams();

    $where = ["c.tenant_id = ?"];
    $params = [$tenantId];

    if (!empty($filters['start_date'])) {
        $where[] = "o.date >= ?";
        $params[] = $filters['start_date'];
    }

    if (!empty($filters['end_date'])) {
        $where[] = "o.date <= ?";
        $params[] = $filters['end_date'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $this->db->prepare("
        SELECT 
            c.id,
            c.name,
            c.email,
            COUNT(DISTINCT o.id) as total_orders,
            SUM(o.total_amount) as total_spent,
            AVG(o.total_amount) as avg_order_value,
            MAX(o.date) as last_order_date,
            (
                SELECT GROUP_CONCAT(DISTINCT p.category_id)
                FROM sales o2
                JOIN sales_items oi ON oi.sale_id = o2.id AND oi.tenant_id = o2.tenant_id
                JOIN products p ON p.id = oi.product_id AND p.tenant_id = oi.tenant_id
                WHERE o2.customer_id = c.id AND o2.tenant_id = ?
            ) as preferred_categories
        FROM customers c
        LEFT JOIN sales o ON o.customer_id = c.id
        $whereClause
        GROUP BY c.id, c.name, c.email
        ORDER BY total_spent DESC
    ");

    // نضيف tenantId مرتين: مرة في params العادي، ومرة لداخل السوب كويري
    $params[] = $tenantId;

    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $this->successResponse($response, $customers
    , 200);
}

    /**
     * تحليل الأداء المالي
     *
     * ملاحظة: تعتمد مؤشرات الأداء المالية على جداول المبيعات والمدفوعات والمعاملات الخارجية (payment_transactions).
     * يجب التأكد من أن أي دفعة خارجية ناجحة يتم ربطها بالحركات النقدية الداخلية ليظهر أثرها في التحليل المالي.
     */
public function analyzeFinancials(Request $request, Response $response) {
    $tenantId = $this->extractTenantId($request);
    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
    }

    $filters = $request->getQueryParams();

    $where = ["s.tenant_id = ?"];
    $params = [$tenantId];

    if (!empty($filters['start_date'])) {
        $where[] = "s.date >= ?";
        $params[] = $filters['start_date'];
    }

    if (!empty($filters['end_date'])) {
        $where[] = "s.date <= ?";
        $params[] = $filters['end_date'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // تحليل المبيعات والتكاليف والأرباح الشهرية
    $stmt = $this->db->prepare("
        SELECT 
            DATE_FORMAT(s.date, '%Y-%m') as month,
            SUM(si.quantity * si.sale_price) as total_sales,
            SUM(si.quantity * p.cost_price) as total_costs,
            SUM(si.quantity * si.sale_price) - SUM(si.quantity * p.cost_price) as gross_profit,
            CASE WHEN SUM(si.quantity * si.sale_price) > 0 THEN 
                (SUM(si.quantity * si.sale_price) - SUM(si.quantity * p.cost_price)) / SUM(si.quantity * si.sale_price) * 100
            ELSE 0 END as profit_margin
        FROM sales s
        JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
        JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id
        $whereClause
        GROUP BY month
        ORDER BY month DESC
    ");

    $stmt->execute($params);
    $financials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // مؤشرات الأداء الرئيسية (KPIs)
    $stmt = $this->db->prepare("
        SELECT 
            SUM(si.quantity * si.sale_price) as total_revenue,
            SUM(si.quantity * p.cost_price) as total_costs,
            SUM(si.quantity * si.sale_price) - SUM(si.quantity * p.cost_price) as gross_profit,
            CASE WHEN SUM(si.quantity * si.sale_price) > 0 THEN 
                (SUM(si.quantity * si.sale_price) - SUM(si.quantity * p.cost_price)) / SUM(si.quantity * si.sale_price) * 100
            ELSE 0 END as profit_margin,
            AVG(daily_sales) as avg_daily_sales
        FROM (
            SELECT 
                DATE(s.date) as date,
                SUM(si.quantity * si.sale_price) as daily_sales
            FROM sales s
            JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
            JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id
            WHERE s.tenant_id = ?
            GROUP BY DATE(s.date)
        ) daily
        WHERE date >= COALESCE(?, date) AND date <= COALESCE(?, date)
    ");

    $stmt->execute(array_merge([$tenantId], [$filters['start_date'] ?? null, $filters['end_date'] ?? null]));
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

    return $this->successResponse($response, [
        'status' => 'success',
        'monthly_financials' => $financials,
        'kpis' => $kpis
    ]);
}

    /**
     * تحليل الاتجاهات
     */
    public function getAnalytics(Request $request, Response $response): Response
    {
        // Delegates to the specialized handlers — each owns its domain logic
        // نستدعي الـ methods مباشرة ونستخرج البيانات بدون تضمين Response objects
        $salesH     = new SalesAnalyticsHandler($this->db);
        $inventoryH = new InventoryAnalyticsHandler($this->db);
        $trendsH    = new SalesAnalyticsHandler($this->db);

        $extractData = function (Response $resp): array {
            $body    = (string) $resp->getBody();
            $decoded = json_decode($body, true);
            return ($decoded['data'] ?? $decoded) ?: [];
        };

        $salesData      = $extractData($salesH->analyzeSales($request, $response));
        $inventoryData  = $extractData($inventoryH->analyzeInventory($request, $response));
        $suppliersData  = $extractData($this->analyzeSuppliers($request, $response));
        $customersData  = $extractData($this->analyzeCustomers($request, $response));
        $financialsData = $extractData($this->analyzeFinancials($request, $response));
        $trendsData     = $extractData($trendsH->analyzeTrends($request, $response));

        return $this->successResponse($response, [
            'sales'      => $salesData,
            'inventory'  => $inventoryData,
            'suppliers'  => $suppliersData,
            'customers'  => $customersData,
            'financials' => $financialsData,
            'trends'     => $trendsData,
        ], 200);
    }
}
