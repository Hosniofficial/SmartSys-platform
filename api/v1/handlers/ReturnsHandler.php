<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;
use App\Services\CostCenter\CostCenterService;
use App\Services\LabelService;
use App\Utils\PaginationHelper;

/**
 * ──────────────────────────────────────────────────────────
 * RETURNS HANDLER
 * ──────────────────────────────────────────────────────────
 *
 * HTTP handler for return management (sales returns and purchase returns)
 * with inventory tracking and accounting journal posting.
 *
 * **Key Responsibilities**:
 * - Sales return CRUD operations (returned quantities per invoice)
 * - Purchase return CRUD operations (returned quantities per purchase)
 * - Inventory adjustment tracking on return creation/update
 * - Returnable quantity calculations (available stock for return per product)
 * - Journal entry posting via AccountingService for return transactions
 * - Multi-tenancy filtering and branch/warehouse isolation
 * - Localized response labels (ar/en) via LabelService
 *
 * **Multi-Tenancy**:
 * All queries filtered by tenant_id and branch_id; per-user cost center resolution
 * for financial transaction tracking.
 *
 * **Dependencies**:
 * - ReturnService: Core business logic (CRUD, inventory calculations, accounting)
 * - CostCenterService: Cost center resolution for return transactions
 * - AccountingService: Journal entry posting for return inventory adjustments
 * - MonologHandler: Entity-specific logging (returns channel)
 * - LabelService: Localized reference and status labels
 * - PaginationHelper: Query result pagination (limit, offset)
 *
 * **Return Types Supported**:
 * - sale: Returns from customer sales (decreases A/R, increases inventory)
 * - purchase: Returns to supplier purchases (decreases A/P, increases inventory)
 *
 * **HTTP Status Codes**:
 * - 200: Success (GET, list operations)
 * - 201: Created (POST operations)
 * - 400: Bad Request (missing/invalid parameters, insufficient returnable qty)
 * - 403: Forbidden (insufficient permissions, missing tenant_id)
 * - 404: Not Found (return/sale/purchase not found)
 * - 500: Server Error (database/transaction failures)
 */
class ReturnsHandler extends BaseHandler
{
    protected $logger;
    private $costCenterService;

    /**
     * Lazy cache: reuse the same ReturnService instance within a single request
     * instead of constructing a new one (+ AccountingService + SettingsRepository)
     * on every helper call inside create().
     *
     * Key format: "{tenantId}:{userId}"
     */
    private array $returnServiceCache = [];

    public function __construct($pdo, $logger = null)
    {
        parent::__construct($pdo);
        $this->logger = $logger ?: MonologHandler::getInstance('returns');
        $this->costCenterService = new CostCenterService($pdo, 'returns');
    }

    /**
     * Returns a cached ReturnService instance for (tenantId, userId).
     * Avoids repeated instantiation of AccountingService + SettingsRepository
     * on every helper call within the same request.
     */
    private function returnService(int $tenantId, ?int $userId): \App\Services\ReturnService
    {
        $key = $tenantId . ':' . ($userId ?? 'null');
        return $this->returnServiceCache[$key]
            ??= $this->services->returns($tenantId, $userId);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * HELPER METHODS
     * ──────────────────────────────────────────────────────────
     */

    private function refLabel(?string $type, string $locale = 'ar'): string
    {
        return LabelService::refLabel($type, $locale);
    }

    private function statusLabel(?string $code, string $locale = 'ar'): string
    {
        return LabelService::statusLabel($code, $locale);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * RETRIEVAL & INVENTORY OPERATIONS
     * ──────────────────────────────────────────────────────────
     */

    public function getReturnedQuantities(Request $request, Response $response): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $params = $request->getQueryParams();
        $type = $params['type'] ?? null;
        $invoiceId = isset($params['invoice_id']) ? (int) $params['invoice_id'] : 0;

        if (!$type || !in_array($type, ['sale', 'purchase'], true)) {
            return $this->errorResponse($response, 'قيمة النوع (type) يجب أن تكون sale أو purchase', 400);
        }

        if ($invoiceId <= 0) {
            return $this->errorResponse($response, 'حقل invoice_id مطلوب ويجب أن يكون رقمًا صحيحًا موجبًا', 400);
        }

        if ($type === 'sale') {
            $stmt = $this->db->prepare("SELECT id FROM sales WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$invoiceId, $tenantId]);

            if (!$stmt->fetchColumn()) {
                return $this->errorResponse($response, 'لم يتم العثور على فاتورة المبيعات المطلوبة', 403);
            }

            $stmtOrig = $this->db->prepare("
                SELECT product_id, COALESCE(SUM(quantity), 0) AS original_qty
                FROM sales_items
                WHERE sale_id = ? AND tenant_id = ?
                GROUP BY product_id
            ");
            $stmtOrig->execute([$invoiceId, $tenantId]);
            $rows = $stmtOrig->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM purchases WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$invoiceId, $tenantId]);

            if (!$stmt->fetchColumn()) {
                return $this->errorResponse($response, 'لم يتم العثور على فاتورة المشتريات المطلوبة', 403);
            }

            $stmtOrig = $this->db->prepare("
                SELECT product_id, COALESCE(SUM(quantity), 0) AS original_qty
                FROM purchase_items
                WHERE purchase_id = ? AND tenant_id = ?
                GROUP BY product_id
            ");
            $stmtOrig->execute([$invoiceId, $tenantId]);
            $rows = $stmtOrig->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmtProd = $this->db->prepare("
            SELECT 
                p.name, 
                p.barcode,
                COALESCE(u.name, 'قطعة') AS unit_name
            FROM products p
            LEFT JOIN product_units pu ON p.id = pu.product_id AND pu.is_main_unit = 1 AND pu.tenant_id = ?
            LEFT JOIN units u ON pu.unit_id = u.id
            WHERE p.id = ? AND p.tenant_id = ?
        ");

        if ($type === 'sale') {
            $stmtPrev = $this->db->prepare("
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM return_items ri
                JOIN returns r ON r.id = ri.return_id
                WHERE r.tenant_id = ?
                  AND r.return_type = 'sale'
                  AND r.sale_id = ?
                  AND ri.product_id = ?
            ");
        } else {
            $stmtPrev = $this->db->prepare("
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM return_items ri
                JOIN returns r ON r.id = ri.return_id
                WHERE r.tenant_id = ?
                  AND r.return_type = 'purchase'
                  AND r.purchase_id = ?
                  AND ri.product_id = ?
            ");
        }

        $result = [];

        foreach ($rows as $r) {
            $productId = (int) $r['product_id'];
            $originalQty = (float) $r['original_qty'];

            $stmtProd->execute([$tenantId, $productId, $tenantId]);
            $prod = $stmtProd->fetch(PDO::FETCH_ASSOC) ?: [
                'name' => null,
                'barcode' => null,
                'unit_name' => 'قطعة'
            ];

            $stmtPrev->execute([$tenantId, $invoiceId, $productId]);
            $returnedQty = (float) $stmtPrev->fetchColumn();

            $remainingQty = max(0.0, $originalQty - $returnedQty);

            $result[] = [
                'product_id' => $productId,
                'product_name' => $prod['name'],
                'barcode' => $prod['barcode'],
                'unit' => $prod['unit_name'],
                'original_qty' => $originalQty,
                'returned_qty' => $returnedQty,
                'remaining_qty' => $remainingQty
            ];
        }

        return $this->successResponse($response, $result, 200);
    }

    /**
     * List returns with pagination and filtering by type (sale or purchase).
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $queryParams = $request->getQueryParams();
        $branchId = $queryParams['branch_id'] ?? null;
        $isExempt = $this->isCashierSessionExempt($request);

        // المستخدم المقيّد بفرع فقط يُطالَب بتحديد branch_id إجباريًا
        if (!$isExempt && (!$branchId || $branchId === '')) {
            return $this->errorResponse($response, 'مطلوب تحديد المخزن (branch_id) لعرض المرتجعات.', 400);
        }

        [$page, $limit, $offset] = PaginationHelper::fromArray($queryParams, 10);
        $search = trim((string) ($queryParams['search'] ?? ''));
        $type = $queryParams['type'] ?? null;
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;

        $query = "
            SELECT
                r.*,
                r.return_number,
                r.invoice_number,
                r.return_date,
                r.grand_total AS total_amount,
                r.status,
                r.notes,
                r.payment_method_id,
                r.is_cash,
                r.branch_id,
                r.created_by,
                r.sale_id,
                r.purchase_id,
                r.customer_id,
                r.supplier_id,
                CASE
                    WHEN r.return_type = 'sale' THEN c.name
                    WHEN r.return_type = 'purchase' THEN s.name
                END AS party_name,
                u.name AS created_by_name
            FROM returns r
            LEFT JOIN customers c
                ON r.return_type = 'sale' AND r.customer_id = c.id AND c.tenant_id = ?
            LEFT JOIN suppliers s
                ON r.return_type = 'purchase' AND r.supplier_id = s.id AND s.tenant_id = ?
            LEFT JOIN users u
                ON r.created_by = u.id AND (u.tenant_id = r.tenant_id OR u.tenant_id IS NULL)
            WHERE r.tenant_id = ?
        ";

        $params = [$tenantId, $tenantId, $tenantId];
        $countParams = [$tenantId, $tenantId, $tenantId];

        $countQuery = "
            SELECT COUNT(*)
            FROM returns r
            LEFT JOIN customers c
                ON r.return_type = 'sale' AND r.customer_id = c.id AND c.tenant_id = ?
            LEFT JOIN suppliers s
                ON r.return_type = 'purchase' AND r.supplier_id = s.id AND s.tenant_id = ?
            WHERE r.tenant_id = ?
        ";

        // فلتر الفرع اختياري الآن — يُطبَّق فقط إن وُجد فعليًا في الطلب
        if ($branchId !== null && $branchId !== '') {
            $query .= " AND r.branch_id = ?";
            $countQuery .= " AND r.branch_id = ?";
            $params[] = (int) $branchId;
            $countParams[] = (int) $branchId;
        }

        if ($search !== '') {
            $query .= " AND (r.return_number LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
            $countQuery .= " AND (r.return_number LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";

            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";

            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
        }

        if ($type) {
            $query .= " AND r.return_type = ?";
            $countQuery .= " AND r.return_type = ?";
            $params[] = $type;
            $countParams[] = $type;
        }

        if ($startDate) {
            $query .= " AND r.created_at >= ?";
            $countQuery .= " AND r.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
            $countParams[] = $startDate . ' 00:00:00';
        }

        if ($endDate) {
            $nextDay = date('Y-m-d', strtotime($endDate . ' +1 day'));
            $query .= " AND r.created_at < ?";
            $countQuery .= " AND r.created_at < ?";
            $params[] = $nextDay . ' 00:00:00';
            $countParams[] = $nextDay . ' 00:00:00';
        }

        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $acceptLang = $request->getHeaderLine('Accept-Language');
        $locale = (stripos($acceptLang, 'ar') === 0) ? 'ar' : 'en';

        foreach ($returns as &$r) {
            $rt = strtolower((string) ($r['return_type'] ?? '')) === 'purchase'
                ? 'purchase_return'
                : 'sales_return';

            $r['reference_type'] = $rt;
            $r['reference_id'] = $r['id'] ?? null;
            $r['reference'] = $rt . '#' . ($r['id'] ?? '');
            $r['reference_label'] = $this->refLabel($rt, $locale);
            $r['status_label'] = $this->statusLabel($r['status'] ?? null, $locale);
        }
        unset($r);

        $stmt = $this->db->prepare($countQuery);
        $stmt->execute($countParams);
        $total = (int) $stmt->fetchColumn();

        return $this->successResponse($response, [
            'items' => $returns,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int) ceil($total / $limit)
        ], 200);
    }

    /**
     * Get single return details with associated items and localized labels.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args expects ['id'] => return_id, ['type'] => 'sale'|'purchase'
     * @return Response
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->errorResponse($response, 'معرّف المرتجع غير صالح', 400);
        }

        $stmt = $this->db->prepare("
            SELECT
                r.*,
                CASE
                    WHEN r.return_type = 'sale' THEN c.name
                    WHEN r.return_type = 'purchase' THEN s.name
                END AS party_name,
                CASE
                    WHEN r.return_type = 'sale' THEN c.phone
                    WHEN r.return_type = 'purchase' THEN s.phone
                END AS party_phone,
                u.name AS created_by_name,
                CASE
                    WHEN r.return_type = 'sale' THEN sa.invoice_number
                    WHEN r.return_type = 'purchase' THEN pu.invoice_number
                END AS original_invoice_number,
                CASE
                    WHEN r.return_type = 'sale' THEN 'مرتجع مبيعات'
                    WHEN r.return_type = 'purchase' THEN 'مرتجع مشتريات'
                END AS return_type_label
            FROM returns r
            LEFT JOIN customers c
                ON r.return_type = 'sale' AND r.customer_id = c.id AND c.tenant_id = ?
            LEFT JOIN suppliers s
                ON r.return_type = 'purchase' AND r.supplier_id = s.id AND s.tenant_id = ?
            LEFT JOIN users u
                ON r.created_by = u.id AND (u.tenant_id = r.tenant_id OR u.tenant_id IS NULL)
            LEFT JOIN sales sa
                ON r.return_type = 'sale' AND r.sale_id = sa.id AND sa.tenant_id = ?
            LEFT JOIN purchases pu
                ON r.return_type = 'purchase' AND r.purchase_id = pu.id AND pu.tenant_id = ?
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$tenantId, $tenantId, $tenantId, $tenantId, $id, $tenantId]);
        $return = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$return) {
            return $this->errorResponse($response, 'لم يتم العثور على المرتجع', 404);
        }

        $stmt = $this->db->prepare("
            SELECT
                ri.*,
                p.name AS product_name,
                p.barcode,
                u.name AS unit_name,
                u.code AS unit_code
            FROM return_items ri
            LEFT JOIN products p ON ri.product_id = p.id AND p.tenant_id = ri.tenant_id
            LEFT JOIN units u ON ri.unit_id = u.id
            WHERE ri.return_id = ? AND ri.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $return['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $acceptLang = $request->getHeaderLine('Accept-Language');
        $locale = (stripos($acceptLang, 'ar') === 0) ? 'ar' : 'en';
        $rt = strtolower((string) ($return['return_type'] ?? '')) === 'purchase'
            ? 'purchase_return'
            : 'sales_return';

        $return['reference_type'] = $rt;
        $return['reference_id'] = $return['id'] ?? null;
        $return['reference'] = $rt . '#' . ($return['id'] ?? '');
        $return['reference_label'] = $this->refLabel($rt, $locale);
        $return['status_label'] = $this->statusLabel($return['status'] ?? null, $locale);

        return $this->successResponse($response, $return, 200);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * CONVENIENCE WRAPPERS
     * ──────────────────────────────────────────────────────────
     */

    public function listSale(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $params['type'] = 'sale';
        $request = $request->withQueryParams($params);

        return $this->list($request, $response);
    }

    public function listPurchase(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $params['type'] = 'purchase';
        $request = $request->withQueryParams($params);

        return $this->list($request, $response);
    }

    public function getSale(Request $request, Response $response, array $args): Response
    {
        $args['type'] = 'sale';
        return $this->get($request, $response, $args);
    }

    public function getPurchase(Request $request, Response $response, array $args): Response
    {
        $args['type'] = 'purchase';
        return $this->get($request, $response, $args);
    }

    /**
     * Get detailed return information with items and calculations.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args expects ['id'] => return_id
     * @return Response
     */
    public function getReturnDetails(Request $request, Response $response, array $args): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $returnId = (int) ($args['id'] ?? 0);
        if ($returnId <= 0) {
            return $this->errorResponse($response, 'معرّف المرتجع غير صالح', 400);
        }

        $acceptLang = $request->getHeaderLine('Accept-Language');
        $locale     = (stripos($acceptLang, 'ar') === 0) ? 'ar' : 'en';

        $userId  = $this->extractUserId($request);
        $details = $this->returnService((int) $tenantId, $userId)->getDetails($returnId, $locale);

        if ($details === null) {
            return $this->errorResponse($response, 'لم يتم العثور على المرتجع', 403);
        }

        return $this->successResponse($response, $details, 200);
    }

   public function create(Request $request, Response $response): Response
{
    $data = $request->getParsedBody();

    if (!$data) {
        return $this->errorResponse($response, 'بيانات الطلب غير صالحة', 400);
    }

    $requiredFields = ['return_type', 'return_date', 'items', 'paid_amount', 'payment_method_id', 'invoice_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (!in_array($field, ['paid_amount', 'discount_value'], true) && empty($data[$field]))) {
            return $this->errorResponse($response, "الحقل المطلوب مفقود: {$field}", 400);
        }
    }

    if (!in_array($data['return_type'], ['sale', 'purchase'], true)) {
        return $this->errorResponse($response, 'نوع المرتجع غير صالح (return_type)، يجب أن يكون sale أو purchase', 400);
    }

    $tenantId = $this->extractTenantId($request);
    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
    }

    try {
        $this->applyDefaultCostCenter($data, $request);
    } catch (\Throwable $e) {
        $this->logger->warning('applyDefaultCostCenter failed in ReturnsHandler::create', ['message' => $e->getMessage()]);
    }

    // استخراج party_id من الفاتورة إذا لم يُمرَّر
    if (empty($data['party_id'])) {
        if ($data['return_type'] === 'sale') {
            $stmt = $this->db->prepare("SELECT customer_id FROM sales WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$data['invoice_id'], $tenantId]);
            $data['party_id'] = $stmt->fetchColumn() ?: null;
        } else {
            $stmt = $this->db->prepare("SELECT supplier_id FROM purchases WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$data['invoice_id'], $tenantId]);
            $data['party_id'] = $stmt->fetchColumn() ?: null;
        }
    }

    if (!is_array($data['items']) || count($data['items']) === 0) {
        return $this->errorResponse($response, 'قائمة الأصناف (items) يجب أن تكون مصفوفة غير فارغة', 400);
    }

    $requiredItemFields = ['product_id', 'unit_id', 'quantity', 'unit_price', 'subtotal'];
    foreach ($data['items'] as $index => $item) {
        foreach ($requiredItemFields as $field) {
            if (!isset($item[$field]) || $item[$field] === '' || $item[$field] === null) {
                return $this->errorResponse($response, "حقل مفقود أو غير صالح '{$field}' في العنصر رقم {$index}", 400);
            }
        }
    }

    if (!is_numeric($data['paid_amount']) || $data['paid_amount'] < 0) {
        return $this->errorResponse($response, 'paid_amount يجب أن يكون رقمًا غير سالب', 400);
    }

    if (!is_numeric($data['payment_method_id']) || $data['payment_method_id'] <= 0) {
        return $this->errorResponse($response, 'payment_method_id يجب أن يكون رقمًا موجبًا صالحًا', 400);
    }

    $returnDateRaw = trim((string) ($data['return_date'] ?? ''));
    $timestamp     = strtotime($returnDateRaw);
    if ($timestamp === false) {
        return $this->errorResponse($response, 'تاريخ المرتجع غير صالح. يرجى استخدام الصيغة YYYY-MM-DD.', 400);
    }
    $data['return_date'] = date('Y-m-d', $timestamp);

    $userId = $request->getAttribute('user_id') ?? $this->extractUserId($request) ?? 1;

    // حل cost_center_id
    try {
        $data['cost_center_id'] = $this->costCenterService->resolve($tenantId, $userId, $data['cost_center_id'] ?? null);
    } catch (\Exception $e) {
        $this->logger->error('Cost center resolution failed for return', [
            'tenant_id' => $tenantId, 'user_id' => $userId, 'error' => $e->getMessage(),
        ]);
        return $this->errorResponse($response, 'فشل في إنشاء المرتجع', 400);
    }

    // حل session_id (HTTP concern — يبقى في الـ Handler)
    $sessionId       = null;
    $methodIsCash    = $this->isCashMethod((int) $data['payment_method_id'], (int) $tenantId);
    $isCash          = $methodIsCash && $data['paid_amount'] > 0;
    $isSessionsEnabled = $this->isSessionsEnabled((int) $tenantId);
    $isExempt        = $this->isCashierSessionExempt($request);

    // نحتاج branch_id لحل الجلسة — نجلبه من الفاتورة مؤقتاً
    $branchIdForSession = null;
    if ($data['return_type'] === 'sale') {
        $stmtBr = $this->db->prepare("SELECT branch_id FROM sales WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmtBr->execute([$data['invoice_id'], $tenantId]);
        $branchIdForSession = $stmtBr->fetchColumn() ?: null;
    } else {
        $stmtBr = $this->db->prepare("SELECT branch_id FROM purchases WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmtBr->execute([$data['invoice_id'], $tenantId]);
        $branchIdForSession = $stmtBr->fetchColumn() ?: null;
    }

    if ($isSessionsEnabled && $isCash && !$isExempt) {
        if (empty($branchIdForSession)) {
            return $this->errorResponse($response, 'يجب تحديد المخزن لإتمام الدفعة النقدية للكاشير.', 400);
        }
        try {
            $sessionId = $this->requireOpenCashierSession((int) $tenantId, (int) $branchIdForSession, $userId);
        } catch (\Exception $ex) {
            return $this->errorResponse($response, $ex->getMessage(), 400);
        }
    } elseif (($isExempt || !$isSessionsEnabled) && !empty($branchIdForSession)) {
        $sessionId = $this->findOpenCashierSession((int) $tenantId, (int) $branchIdForSession, null);
    }

    // ── تفويض كل المنطق للـ Service ──────────────────────────────────────────
    try {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }

        $svc    = $this->returnService((int) $tenantId, $userId);
        $result = $svc->createReturn($data, $tenantId, $userId, $sessionId);

        // Audit
        try {
            $this->audit->logAction('return_created', 'returns', $result['return_id'], [
                'tenant_id'        => $tenantId,
                'user_id'          => $userId,
                'return_id'        => $result['return_id'],
                'return_type'      => $data['return_type'],
                'return_number'    => $result['return_number'],
                'invoice_id'       => $data['invoice_id']        ?? null,
                'return_date'      => $data['return_date']        ?? null,
                'paid_amount'      => $data['paid_amount'],
                'payment_method_id'=> $data['payment_method_id'],
                'session_id'       => $sessionId,
                'cost_center_id'   => $data['cost_center_id']    ?? null,
                'journal_entry_id' => $result['journal_entry_id'] ?? null,
            ], $tenantId, $userId);
        } catch (\Throwable $auditError) {
            $this->logger->warning('Failed to log return_created to audit', ['error' => $auditError->getMessage()]);
        }

        $this->db->commit();

        return $this->successResponse($response, [
            'return_id' => $result['return_id'],
            'message'   => 'تم إنشاء المرتجع بنجاح.',
        ], 200);

    } catch (\PDOException $e) {
        if ($this->db->inTransaction()) $this->db->rollBack();
        $this->logger->error('Return creation database error', ['message' => $e->getMessage(), 'tenant_id' => $tenantId]);
        return $this->errorResponse($response, 'حدث خطأ في قاعدة البيانات أثناء إنشاء المرتجع', 500);
    } catch (\Throwable $e) {
        if ($this->db->inTransaction()) $this->db->rollBack();
        $this->logger->error('Return creation failed', ['message' => $e->getMessage(), 'tenant_id' => $tenantId]);
        $msg = $e->getMessage();
        if (strpos($msg, 'لا يمكن') !== false || strpos($msg, 'فشل') !== false) {
            return $this->errorResponse($response, $msg, 400);
        }
        return $this->errorResponse($response, 'فشل في إنشاء المرتجع', 400);
    }
}

public function approve(Request $request, Response $response, array $args): Response
{
    $tenantId = $this->extractTenantId($request);
    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
    }

    $id = $args['id'];
    $stmt = $this->db->prepare("
        UPDATE returns
        SET status = 'approved'
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenantId]);

    return $this->successResponse($response, ['message' => 'تم اعتماد المرتجع بنجاح.'], 200);
}

public function reject(Request $request, Response $response, array $args): Response
{
    $tenantId = $this->extractTenantId($request);
    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
    }

    $id = $args['id'];
    $stmt = $this->db->prepare("
        UPDATE returns
        SET status = 'rejected'
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenantId]);

    return $this->successResponse($response, ['message' => 'تم رفض المرتجع بنجاح.'], 200);
}

public function getPrintData(Request $request, Response $response, array $args): Response
{
    $tenantId = $this->extractTenantId($request);
    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
    }

    $id = $args['id'];

    $stmt = $this->db->prepare("
        SELECT
            r.*,
            CASE
                WHEN r.return_type = 'sale' THEN c.name
                WHEN r.return_type = 'purchase' THEN s.name
            END AS party_name,
            CASE
                WHEN r.return_type = 'sale' THEN c.phone
                WHEN r.return_type = 'purchase' THEN s.phone
            END AS party_phone,
            CASE
                WHEN r.return_type = 'sale' THEN c.address
                WHEN r.return_type = 'purchase' THEN s.address
            END AS party_address,
            u.name AS created_by_name
        FROM returns r
        LEFT JOIN customers c
            ON r.return_type = 'sale' AND r.customer_id = c.id AND c.tenant_id = ?
        LEFT JOIN suppliers s
            ON r.return_type = 'purchase' AND r.supplier_id = s.id AND s.tenant_id = ?
        LEFT JOIN users u
            ON r.created_by = u.id
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$tenantId, $tenantId, $id, $tenantId]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$return) {
        return $this->errorResponse($response, 'لم يتم العثور على المرتجع', 404);
    }

    $stmt = $this->db->prepare("
        SELECT
            ri.*,
            p.name AS product_name,
            p.barcode,
            u.name AS unit_name,
            u.code AS unit_code
        FROM return_items ri
        LEFT JOIN products p ON ri.product_id = p.id AND p.tenant_id = ?
        LEFT JOIN units u ON ri.unit_id = u.id
        WHERE ri.return_id = ? AND ri.tenant_id = ?
    ");
    $stmt->execute([$tenantId, $id, $tenantId]);
    $return['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $this->db->prepare("
        SELECT *
        FROM settings
        WHERE category = 'company' AND tenant_id = ?
    ");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    return $this->successResponse($response, [
        'return' => $return,
        'company' => $settings
    ], 200);
}
public function searchInvoice(Request $request, Response $response): Response
{
    $tenantId = $this->extractTenantId($request);

    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
    }

    $queryParams = $request->getQueryParams();
    $invoiceNumber = trim((string)($queryParams['q'] ?? ''));
    $returnType = trim((string)($queryParams['type'] ?? ''));
    $fromDate = trim((string)($queryParams['from_date'] ?? ''));
    $toDate = trim((string)($queryParams['to_date'] ?? ''));

    if (!in_array($returnType, ['sale', 'purchase'], true)) {
        return $this->errorResponse($response, 'نوع المرتجع غير صالح، يجب أن يكون sale أو purchase', 400);
    }

    $isSale = $returnType === 'sale';
    $table = $isSale ? 'sales' : 'purchases';
    $dateField = $isSale ? 'sale_date' : 'created_at';

    $partySelect = $isSale
        ? 'c.name AS customer_name, c.id AS customer_id'
        : 's.name AS supplier_name, s.id AS supplier_id';

    $partyJoin = $isSale
        ? 'LEFT JOIN customers c ON i.customer_id = c.id AND c.tenant_id = :tenant_id_party'
        : 'LEFT JOIN suppliers s ON i.supplier_id = s.id AND s.tenant_id = :tenant_id_party';

    if ($isSale) {
        $selectFields = "
            i.total_amount,
            i.discount_type,
            i.discount_value,
            i.tax_amount,
            i.net_total_amount,
            IFNULL(i.paid_amount, 0) AS paid_amount,
            (i.net_total_amount + IFNULL(i.tax_amount, 0)) AS grand_total,
            COALESCE(SUM(rca.allocated_amount), 0) AS return_credits_applied,
            ((i.net_total_amount + IFNULL(i.tax_amount, 0)) - IFNULL(i.paid_amount, 0)) AS outstanding,
            ((i.net_total_amount + IFNULL(i.tax_amount, 0)) - IFNULL(i.paid_amount, 0)) AS outstanding_amount,
            GREATEST(0, (i.net_total_amount + IFNULL(i.tax_amount, 0)) - IFNULL(i.paid_amount, 0) - COALESCE(SUM(rca.allocated_amount), 0)) AS net_outstanding,
            CASE
                WHEN IFNULL(i.paid_amount, 0) <= 0 AND COALESCE(SUM(rca.allocated_amount), 0) > 0 THEN 'credit'
                WHEN IFNULL(i.paid_amount, 0) >= (i.net_total_amount + IFNULL(i.tax_amount, 0)) THEN 'cash'
                WHEN IFNULL(i.paid_amount, 0) > 0
                     AND IFNULL(i.paid_amount, 0) < (i.net_total_amount + IFNULL(i.tax_amount, 0)) THEN 'partial'
                ELSE 'unknown'
            END AS payment_status,
            CASE
                WHEN IFNULL(i.discount_value, 0) > 0 THEN 1
                ELSE 0
            END AS has_discount,
            i.branch_id,
            i.payment_method_id,
            pm.name AS payment_method_name,
            pm.kind AS payment_method_kind,
            (SELECT COUNT(*) FROM sales_items WHERE sale_id = i.id AND tenant_id = :tenant_id_subquery) AS items_count
        ";
    } else {
        $selectFields = "
            i.total_amount,
            NULL AS discount_type,
            IFNULL(i.discount_value, 0) AS discount_value,
            i.tax_amount,
            (i.total_amount - IFNULL(i.discount_value, 0)) AS net_total_amount,
            IFNULL(i.paid_amount, 0) AS paid_amount,
            (i.total_amount + IFNULL(i.tax_amount, 0) - IFNULL(i.discount_value, 0)) AS grand_total,
            0 AS return_credits_applied,
            (
                (i.total_amount + IFNULL(i.tax_amount, 0) - IFNULL(i.discount_value, 0))
                - IFNULL(i.paid_amount, 0)
            ) AS outstanding,
            (
                (i.total_amount + IFNULL(i.tax_amount, 0) - IFNULL(i.discount_value, 0))
                - IFNULL(i.paid_amount, 0)
            ) AS outstanding_amount,
            (
                (i.total_amount + IFNULL(i.tax_amount, 0) - IFNULL(i.discount_value, 0))
                - IFNULL(i.paid_amount, 0)
            ) AS net_outstanding,
            CASE
                WHEN IFNULL(i.paid_amount, 0) <= 0 THEN 'credit'
                WHEN IFNULL(i.paid_amount, 0) >= (i.total_amount + IFNULL(i.tax_amount, 0) - IFNULL(i.discount_value, 0)) THEN 'cash'
                WHEN IFNULL(i.paid_amount, 0) > 0
                     AND IFNULL(i.paid_amount, 0) < (i.total_amount + IFNULL(i.tax_amount, 0) - IFNULL(i.discount_value, 0)) THEN 'partial'
                ELSE 'unknown'
            END AS payment_status,
            CASE
                WHEN IFNULL(i.discount_value, 0) > 0 THEN 1
                ELSE 0
            END AS has_discount,
            i.branch_id,
            i.payment_method_id,
            pm.name AS payment_method_name,
            pm.kind AS payment_method_kind,
            (SELECT COUNT(*) FROM purchases_items WHERE purchase_id = i.id AND tenant_id = :tenant_id_subquery) AS items_count
        ";
    }

    $sql = "
        SELECT
            i.id,
            i.invoice_number,
            i.{$dateField} AS invoice_date,
            {$selectFields},
            {$partySelect},
            b.name AS branch_name
        FROM {$table} i
        {$partyJoin}
        LEFT JOIN payment_methods pm
            ON i.payment_method_id = pm.id
           AND pm.tenant_id = :tenant_id_payment_method
        LEFT JOIN branches b
            ON i.branch_id = b.id
           AND b.tenant_id = :tenant_id_branch
        " . ($isSale ? "LEFT JOIN return_credit_allocations rca
            ON i.id = rca.sale_id
           AND i.tenant_id = rca.tenant_id" : "") . "
        WHERE i.tenant_id = :tenant_id_where
    ";

    $params = [
        ':tenant_id_where' => $tenantId,
        ':tenant_id_party' => $tenantId,
        ':tenant_id_payment_method' => $tenantId,
        ':tenant_id_branch' => $tenantId,
        ':tenant_id_subquery' => $tenantId
    ];

    if ($invoiceNumber !== '') {
        $sql .= " AND (i.invoice_number LIKE :invoice_number";

        if (ctype_digit($invoiceNumber)) {
            $sql .= " OR i.id = :invoice_id";
            $params[':invoice_id'] = (int)$invoiceNumber;
        }

        if ($isSale) {
            $sql .= " OR (c.name IS NOT NULL AND c.name LIKE :customer_name)";
            $sql .= " OR (c.phone IS NOT NULL AND c.phone LIKE :phone)";
            $params[':customer_name'] = "%{$invoiceNumber}%";
            $params[':phone'] = "%{$invoiceNumber}%";
        } else {
            $sql .= " OR (s.name IS NOT NULL AND s.name LIKE :supplier_name)";
            $sql .= " OR (s.phone IS NOT NULL AND s.phone LIKE :phone)";
            $params[':supplier_name'] = "%{$invoiceNumber}%";
            $params[':phone'] = "%{$invoiceNumber}%";
        }

        $sql .= ")";
        $params[':invoice_number'] = "%{$invoiceNumber}%";
    }

    if ($fromDate !== '') {
        $sql .= " AND DATE(i.{$dateField}) >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate !== '') {
        $sql .= " AND DATE(i.{$dateField}) <= :to_date";
        $params[':to_date'] = $toDate;
    }

    if ($isSale) {
        $sql .= " GROUP BY i.id";
    }

    $sql .= " ORDER BY i.{$dateField} DESC LIMIT 50";

    try {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->successResponse(
            $response,
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            200
        );
    } catch (\Throwable $e) {
        $this->logger->error('Error in searchInvoice', [
            'tenant_id' => $tenantId,
            'return_type' => $returnType,
            'invoice_number' => $invoiceNumber,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'message' => $e->getMessage()
        ]);

        return $this->errorResponse($response, 'حدث خطأ أثناء البحث عن الفاتورة', 500);
    }
}

public function getInvoiceItems(Request $request, Response $response): Response
{
    $tenantId = $this->extractTenantId($request);

    if (!$tenantId) {
        return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
    }

    $queryParams = $request->getQueryParams();
    $invoiceId = (int)($queryParams['invoice_id'] ?? 0);
    $returnType = trim((string)($queryParams['type'] ?? ''));

    if ($invoiceId <= 0 || !in_array($returnType, ['sale', 'purchase'], true)) {
        return $this->errorResponse($response, 'معرّف الفاتورة أو نوع المرتجع غير صالح', 400);
    }

    try {
        if ($returnType === 'sale') {
            $itemsStmt = $this->db->prepare("
                SELECT
                    si.id,
                    p.id          AS product_id,
                    p.name        AS product_name,
                    p.barcode,
                    p.product_code,
                    si.unit_id,
                    si.quantity,
                    si.sale_price AS unit_price,
                    si.discount_value AS discount,
                    si.discount_value AS discount_amount,
                    si.net_total  AS subtotal,
                    NULL          AS batch_number,
                    NULL          AS expiry_date
                FROM sales_items si
                JOIN products p
                    ON si.product_id = p.id
                   AND p.tenant_id = :tenant_id
                WHERE si.sale_id = :invoice_id
                  AND si.tenant_id = :tenant_id
            ");

            $invoiceStmt = $this->db->prepare("
                SELECT
                    customer_id,
                    invoice_number,
                    sale_date AS invoice_date
                FROM sales
                WHERE id = :invoice_id
                  AND tenant_id = :tenant_id
            ");
        } else {
            $itemsStmt = $this->db->prepare("
                SELECT
                    pi.id,
                    p.id AS product_id,
                    p.name AS product_name,
                    p.barcode,
                    p.product_code,
                    pi.unit_id,
                    pi.quantity,
                    pi.cost AS unit_price,
                    pi.tax_rate,
                    pi.tax_amount,
                    pi.discount_amount AS discount,
                    pi.discount_amount AS discount_amount,
                    pi.subtotal,
                    pi.batch_number,
                    pi.expiry_date
                FROM purchase_items pi
                JOIN products p
                    ON pi.product_id = p.id
                   AND p.tenant_id = :tenant_id
                WHERE pi.purchase_id = :invoice_id
                  AND pi.tenant_id = :tenant_id
            ");

            $invoiceStmt = $this->db->prepare("
                SELECT
                    supplier_id,
                    invoice_number,
                    created_at AS invoice_date
                FROM purchases
                WHERE id = :invoice_id
                  AND tenant_id = :tenant_id
            ");
        }

        $params = [
            ':invoice_id' => $invoiceId,
            ':tenant_id' => $tenantId
        ];

        $itemsStmt->execute($params);
        $invoiceStmt->execute($params);

        $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) {
            return $this->errorResponse($response, 'الفاتورة غير موجودة', 404);
        }

        return $this->successResponse($response, [
            'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC),
            'invoice' => $invoice
        ], 200);
    } catch (\Throwable $e) {
        $this->logger->error('Error in getInvoiceItems', [
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'return_type' => $returnType,
            'message' => $e->getMessage()
        ]);

        return $this->errorResponse($response, 'حدث خطأ أثناء جلب بيانات الفاتورة', 500);
    }
  }
}


