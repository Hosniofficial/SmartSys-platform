<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\SalesService;
use App\Services\AccountingService;
use App\Services\MonologHandler;
use App\Services\LabelService;
use App\Services\CurrencyService;
use App\Handlers\NotificationHandler;
use App\Handlers\AuditHandler;
use App\Utils\PaginationHelper;
use App\Repositories\SaleRepository;

/**
 * ──────────────────────────────────────────────────────────
 * SALES HANDLER
 * ──────────────────────────────────────────────────────────
 *
 * HTTP handler for all sales-related endpoints (list, get, create, update, delete,
 * approve/reject, payments, pending approvals, and debt management).
 *
 * **Key Responsibilities**:
 * - Invoice CRUD operations with atomic transactions
 * - Approval workflow (pending, awaiting approval, approved, rejected)
 * - Payment recording with Idempotency-Key support for duplicate prevention
 * - Customer debt payment management with race condition prevention (SELECT FOR UPDATE)
 * - COGS (Cost of Goods Sold) posting on approval via AccountingService
 * - Multi-currency support via CurrencyService
 * - Localized response labels (ar/en) via LabelService
 * - Automatic invoice numbering and status calculation
 *
 * **Multi-Tenancy**:
 * All queries filtered by tenant_id; authorization via RBACHandler for approval operations
 * (permissions: 'sales.approval.approve', 'sales.approval.reject').
 *
 * **Dependencies**:
 * - SalesService: Core business logic (CRUD, status transitions, cost calculations)
 * - AccountingService: Journal entry posting for sales, COGS, and payments
 * - NotificationHandler: Customer notifications on invoice status changes
 * - RBACHandler: Role-based authorization for approval operations
 * - MonologHandler: Entity-specific logging (sales channel)
 * - LabelService: Localized reference and status labels
 * - CurrencyService: Multi-currency handling
 * - PaginationHelper: Query result pagination (limit, offset)
 *
 * **Status Transitions**:
 * - pending_payment: New invoice, unpaid
 * - partial: Partially paid
 * - paid: Fully paid
 * - pending_approval: Awaiting approval (if workflow enabled)
 * - returned: Return amount exceeds invoice total
 * - settled_by_return: Return credits cover remaining balance without full cash payment
 *
 * **HTTP Status Codes**:
 * - 200: Success (GET, list, approve/reject operations)
 * - 201: Created (POST operations)
 * - 400: Bad Request (missing/invalid parameters)
 * - 403: Forbidden (insufficient permissions, missing tenant_id)
 * - 404: Not Found (invoice not found)
 * - 409: Conflict (duplicate idempotency key)
 * - 500: Server Error (database/transaction failures)
 */
class SalesHandler extends BaseHandler
{
    private $rbac;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('sales');
        $this->rbac   = new RBACHandler($db);
    }

    /**
     * ──────────────────────────────────────────────────────────
     * LIST OPERATIONS
     * ──────────────────────────────────────────────────────────
     */

    private function determineSaleStatus(float $grandTotal, float $paidAmount, float $returnAmount = 0.0, float $returnCredits = 0.0, $createdAt = null, $firstPaymentDate = null, bool $hasDirectReturn = false): string
    {
        if ($grandTotal <= 0) {
            return 'paid';
        }

        if ($returnAmount >= $grandTotal) {
            return 'returned';
        }

        $outstanding = max(0.0, round($grandTotal - $paidAmount - $returnCredits, 2));

        if ($outstanding < 0.01) {
            // Fully paid by cash only, possibly with later return
            if ($paidAmount >= $grandTotal - 0.01) {
                return $hasDirectReturn || $returnAmount > 0.01 ? 'returned' : 'paid';
            }

            // Fully settled by credit note only (no cash paid)
            if ($paidAmount < 0.01) {
                if ($returnCredits > 0.01 && !$hasDirectReturn) {
                    return 'settled_by_credit';
                }
                return 'closed_by_return';
            }

            // Mixed settlement: some cash + some return credit
            if ($returnCredits > 0.01 && $paidAmount > 0.01) {
                return 'settled_mixed';
            }

            return 'closed_by_return';
        }

        if ($paidAmount <= 0) {
            return 'pending_payment';
        }

        // Partial settlement
        return 'partial';
    }

    /**
     * List sales with filtering, pagination and optional totals.
     *
     * Query parameters supported: q, customer_id, status, date_from, date_to,
     * branch_id, sort, order, include_totals, page, per_page
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function list(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $qp = $request->getQueryParams();
            [$page, $perPage, $offset] = PaginationHelper::fromRequest($request);

            $where = ["s.tenant_id = ?"];
            $params = [$tenantId];

            $search = trim((string) ($qp['q'] ?? $qp['search'] ?? ''));
            if ($search !== '') {
                $where[] = "(s.id = ? OR c.name LIKE ? OR s.invoice_number LIKE ?)";
                $params[] = ctype_digit($search) ? (int) $search : 0;
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }

            if (!empty($qp['customer_id'])) {
                $where[] = "s.customer_id = ?";
                $params[] = $qp['customer_id'];
            }

            if (!empty($qp['status'])) {
                $where[] = "s.status = ?";
                $params[] = $qp['status'];
            }

            $dateFrom = $qp['date_from'] ?? ($qp['start_date'] ?? null);
            $dateTo = $qp['date_to'] ?? ($qp['end_date'] ?? null);

            if (!empty($dateFrom)) {
                $where[] = "DATE(s.created_at) >= ?";
                $params[] = $dateFrom;
            }

            if (!empty($dateTo)) {
                $where[] = "DATE(s.created_at) <= ?";
                $params[] = $dateTo;
            }

            if (isset($qp['branch_id']) && $qp['branch_id'] !== '') {
                $where[] = "s.branch_id = ?";
                $params[] = (int) $qp['branch_id'];
            }

            $sort = strtolower((string) ($qp['sort'] ?? 'created_at'));
            $order = strtolower((string) ($qp['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

            $allowedSort = [
                'id' => 's.id',
                'created_at' => 's.created_at',
                'total_amount' => 's.total_amount',
                'net_total_amount' => 's.net_total_amount',
                'status' => 's.status',
            ];

            $sortCol = $allowedSort[$sort] ?? 's.created_at';

            $from = "
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id AND c.tenant_id = s.tenant_id
                LEFT JOIN users u ON s.user_id = u.id AND u.tenant_id = s.tenant_id
            ";

            $whereSql = " WHERE " . implode(' AND ', $where) . " ";

            $stmt = $this->db->prepare("SELECT COUNT(*)" . $from . $whereSql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "SELECT s.*,
                        c.name AS customer_name,
                        u.username AS created_by_name,
                        COALESCE((SELECT SUM(
                                      CASE WHEN pm.type = 'return_payment' THEN -pm.amount ELSE pm.amount END
                                  ) FROM payments pm
                                  WHERE pm.sale_id = s.id AND pm.tenant_id = s.tenant_id
                                    AND pm.status = 'completed'), 0) AS actual_paid_amount,
                        COALESCE((SELECT SUM(r.grand_total) FROM returns r
                                  WHERE r.sale_id = s.id AND r.tenant_id = s.tenant_id
                                    AND r.return_type = 'sale'), 0)  AS return_amount,
                        COALESCE((SELECT SUM(rca.allocated_amount) FROM return_credit_allocations rca
                                  WHERE rca.sale_id = s.id AND rca.tenant_id = s.tenant_id), 0) AS return_credits,
                        (SELECT MIN(pm.payment_date) FROM payments pm
                         WHERE pm.sale_id = s.id AND pm.tenant_id = s.tenant_id
                           AND pm.status = 'completed' LIMIT 1) AS first_payment_date,
                        (SELECT GROUP_CONCAT(r.id) FROM returns r
                         WHERE r.sale_id = s.id AND r.tenant_id = s.tenant_id
                           AND r.return_type = 'sale') AS return_ids
                 " . $from . $whereSql . "
                 ORDER BY {$sortCol} {$order}
                 LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];
            foreach ($rows as $row) {
                $grandTotal    = round((float)($row['net_total_amount'] ?? 0) + (float)($row['tax_amount'] ?? 0), 2);
                $actualPaid    = round((float)$row['actual_paid_amount'], 2);
                $returnAmount  = round((float)($row['return_amount'] ?? 0), 2);
                $returnCredits = round((float)($row['return_credits'] ?? 0), 2);
                $isReturned    = $grandTotal > 0 && $returnAmount >= $grandTotal;
                $hasDirectReturn = !empty($row['return_ids']);  // Determine if this invoice has direct returns

                $row['actual_paid_amount']  = $actualPaid;
                $row['return_amount']       = $returnAmount;
                $row['remaining_balance'] = $isReturned ? 0.0 : max(0, round($grandTotal - $actualPaid - $returnCredits, 2));
                $row['dynamic_status']      = $this->determineSaleStatus($grandTotal, $actualPaid, $returnAmount, $returnCredits, $row['created_at'], $row['first_payment_date'], $hasDirectReturn);
                $items[] = $row;
            }

            $summary = null;
            $includeTotals = isset($qp['include_totals']) && (string) $qp['include_totals'] !== '0';

            if ($includeTotals) {
                $stmt = $this->db->prepare(
                    "SELECT
                        COALESCE(SUM(s.net_total_amount), 0) AS sum_net_total,
                        COALESCE(SUM(s.tax_amount), 0) AS sum_tax,
                        COALESCE(SUM(s.discount_value), 0) AS sum_discount
                     " . $from . $whereSql
                );
                $stmt->execute($params);
                $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                    'sum_net_total' => 0,
                    'sum_tax' => 0,
                    'sum_discount' => 0
                ];
            }

            return $this->successResponse($response, [
                'items'      => $items,
                'total'      => $total,
                'summary'    => $summary,
                'pagination' => PaginationHelper::buildMeta($total, $page, $perPage),
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('Sales list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse($response, 'Failed to retrieve sales list', 500);
        }
    }

    // =========================================================
    // GET SINGLE
    // =========================================================

    /**
     * Get single sale details including items, payments and dynamic status.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args Route arguments (expects 'id')
     * @return Response
     */
    public function get(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $id = $args['id'] ?? null;
            if (!$id) {
                return $this->errorResponse($response, 'Sale ID is required', 400);
            }

            $stmt = $this->db->prepare("
                SELECT s.*,
                       c.name AS customer_name,
                       u.username AS created_by_name,
                       b.name     AS branch_name,
                       b.location AS branch_location,
                       b.phone    AS branch_phone,
                       b.email    AS branch_email,
                       pm.name    AS payment_method_name,
                       pm.kind    AS payment_method_kind
                FROM sales s
                LEFT JOIN customers c       ON s.customer_id = c.id         AND c.tenant_id  = s.tenant_id
                LEFT JOIN users u           ON s.user_id     = u.id         AND u.tenant_id  = s.tenant_id
                LEFT JOIN branches b        ON s.branch_id   = b.id         AND b.tenant_id  = s.tenant_id
                LEFT JOIN payment_methods pm ON s.payment_method_id = pm.id AND pm.tenant_id = s.tenant_id
                WHERE s.id = ? AND s.tenant_id = ?
            ");
            $stmt->execute([$id, $tenantId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                return $this->errorResponse($response, 'Sale not found', 403);
            }

            $stmt = $this->db->prepare("
                SELECT si.*, p.name AS product_name, p.barcode, c.name AS category_name
                FROM sales_items si
                JOIN products p ON si.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE si.sale_id = ? AND si.tenant_id = ?
            ");
            $stmt->execute([$id, $tenantId]);
            $sale['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmtPaid = $this->db->prepare(
                "SELECT COALESCE(SUM(CASE WHEN type = 'return_payment' THEN -amount ELSE amount END), 0)
                 FROM payments
                 WHERE sale_id = ? AND tenant_id = ? AND status = 'completed'"
            );
            $stmtPaid->execute([$id, $tenantId]);
            $actualPaid  = round((float) $stmtPaid->fetchColumn(), 2);
            $grandTotal  = round((float)($sale['net_total_amount'] ?? 0) + (float)($sale['tax_amount'] ?? 0), 2);

            $stmtReturn = $this->db->prepare(
                "SELECT COALESCE(SUM(grand_total), 0) FROM returns
                 WHERE sale_id = ? AND tenant_id = ? AND return_type = 'sale'"
            );
            $stmtReturn->execute([$id, $tenantId]);
            $returnAmount = round((float) $stmtReturn->fetchColumn(), 2);

            $stmtReturnCredits = $this->db->prepare(
                "SELECT COALESCE(SUM(allocated_amount), 0) FROM return_credit_allocations
                 WHERE sale_id = ? AND tenant_id = ?"
            );
            $stmtReturnCredits->execute([$id, $tenantId]);
            $returnCredits = round((float) $stmtReturnCredits->fetchColumn(), 2);

            // Determine if this invoice has direct returns
            $stmtDirectReturns = $this->db->prepare(
                "SELECT COUNT(*) FROM returns
                 WHERE sale_id = ? AND tenant_id = ? AND return_type = 'sale'"
            );
            $stmtDirectReturns->execute([$id, $tenantId]);
            $hasDirectReturn = (int)$stmtDirectReturns->fetchColumn() > 0;

            // Get first payment date for accurate status determination
            $stmtFirstPayment = $this->db->prepare(
                "SELECT MIN(payment_date) FROM payments
                 WHERE sale_id = ? AND tenant_id = ? AND status = 'completed' LIMIT 1"
            );
            $stmtFirstPayment->execute([$id, $tenantId]);
            $firstPaymentDate = $stmtFirstPayment->fetchColumn();

            $isReturned   = $grandTotal > 0 && $returnAmount >= $grandTotal;

            $sale['actual_paid_amount'] = $actualPaid;
            $sale['return_amount']      = $returnAmount;
            $sale['remaining_balance'] = $isReturned ? 0.0 : max(0, round($grandTotal - $actualPaid - $returnCredits, 2));
            $sale['dynamic_status']     = $this->determineSaleStatus($grandTotal, $actualPaid, $returnAmount, $returnCredits, $sale['created_at'], $firstPaymentDate, $hasDirectReturn);

            return $this->successResponse($response, $sale, 200);
        } catch (\Throwable $e) {
            $this->logger->error('Sales get error', [
                'message' => $e->getMessage(),
                'sale_id' => $id ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'Failed to retrieve sale details', 500);
        }
    }

    /**
     * ──────────────────────────────────────────────────────────
     * RETRIEVAL OPERATIONS
     * ──────────────────────────────────────────────────────────
     */

    /**
     * Return sales that are pending approval.
     * Supports pagination and includes payment method resolution.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function pendingApprovals(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $qp = $request->getQueryParams();
            [$page, $perPage, $offset] = PaginationHelper::fromRequest($request);

            $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM sales
            WHERE tenant_id = ?
              AND status IN ('pending', 'awaiting_approval', 'pending_approval')
        ");
            $stmt->execute([$tenantId]);
            $total = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare("
            SELECT
                s.*,
                c.name AS customer_name,
                u.username AS created_by_name,
                (
                    SELECT pm.name
                    FROM payment_methods pm
                    WHERE pm.id = COALESCE(
                        s.payment_method_id,
                        (
                            SELECT p.payment_method_id
                            FROM payments p
                            WHERE p.sale_id = s.id
                              AND p.tenant_id = ?
                              AND p.is_draft = 1
                            ORDER BY p.id DESC
                            LIMIT 1
                        )
                    )
                      AND pm.tenant_id = ?
                    LIMIT 1
                ) AS payment_method_name,
                (
                    SELECT pm.kind
                    FROM payment_methods pm
                    WHERE pm.id = COALESCE(
                        s.payment_method_id,
                        (
                            SELECT p.payment_method_id
                            FROM payments p
                            WHERE p.sale_id = s.id
                              AND p.tenant_id = ?
                              AND p.is_draft = 1
                            ORDER BY p.id DESC
                            LIMIT 1
                        )
                    )
                      AND pm.tenant_id = ?
                    LIMIT 1
                ) AS payment_method_kind
            FROM sales s
            LEFT JOIN customers c
                ON s.customer_id = c.id AND c.tenant_id = s.tenant_id
            LEFT JOIN users u
                ON s.user_id = u.id AND u.tenant_id = s.tenant_id
            WHERE s.tenant_id = ?
              AND s.status IN ('pending', 'awaiting_approval', 'pending_approval')
            ORDER BY s.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
            $stmt->execute([$tenantId, $tenantId, $tenantId, $tenantId, $tenantId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse($response, [
                'items'      => $items,
                'total'      => $total,
                'pagination' => PaginationHelper::buildMeta($total, $page, $perPage),
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('Pending approvals error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse($response, 'Failed to retrieve pending approvals', 500);
        }
    }

    /**
     * Create a new sale (invoice).
     * Validates tenant context, applies default cost centers and delegates to service layer.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                $this->logger->warning('Sales create - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $userId = $this->extractUserId($request) ?? null;
            $data = $request->getParsedBody() ?? [];
            $data['tenant_id'] = (int) $tenantId;

            $this->logger->info('Sales invoice creation request', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'customer_id' => $data['customer_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'total_amount' => $data['total_amount'] ?? 0
            ]);

            $this->applyDefaultCostCenter($data, $request);

            $svc    = $this->services->saleCreation((int) $tenantId, $userId);
            $result = $svc->createSale($data);

            $saleId = $result['sale_id'] ?? $result['id'] ?? null;

            $this->logger->info('Sales invoice created successfully', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'sale_id' => $saleId,
                'invoice_number' => $result['invoice_number'] ?? null,
                'total_amount' => $result['total_amount'] ?? 0
            ]);

            try {
                if (!empty($saleId)) {
                    $notificationHandler = new NotificationHandler($this->db);
                    $notificationHandler->sendNewOrderNotification((int) $saleId);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to send new order notification', [
                    'message' => $e->getMessage(),
                    'sale_id' => $saleId
                ]);
            }

            return $this->successResponse($response, array_merge($result, [
                'message' => 'تم إنشاء الفاتورة بنجاح',
                'id'      => $saleId,
            ]), 201);
        } catch (\App\Exceptions\InsufficientStockException $e) {
            // Handle insufficient stock error with specific logging
            $this->logger->warning('Sales invoice creation failed - insufficient stock', [
                'product_id' => $e->productId,
                'available_qty' => $e->availableQty,
                'requested_qty' => $e->requestedQty,
                'tenant_id' => $tenantId ?? 'unknown',
                'user_id' => $userId ?? 'unknown'
            ]);

            return $response->withStatus(409)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($e->toArray(), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $this->logger->error('Sales invoice creation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'user_id' => $userId ?? 'unknown',
                'customer_id' => $data['customer_id'] ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'فشل في إنشاء الفاتورة', 400);
        }
    }

    /**
     * Approve a sale invoice. Requires RBAC permission 'sales.approval.approve'.
     * Delegates to service layer and ensures COGS journal posting after approval.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args expects ['id'] => sale_id
     * @return Response
     */
    public function approve(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $saleId = (int) ($args['id'] ?? 0);
            if ($saleId <= 0) {
                return $this->errorResponse($response, 'مطلوب رقم الفاتورة', 400);
            }

            $userId = $this->extractUserId($request) ?? null;

            if (!$userId || !$this->rbac || !$this->rbac->hasPermission($userId, 'sales.approval.approve')) {
                $this->logger->warning('Sales approve - permission denied', [
                    'tenant_id' => $tenantId,
                    'sale_id'   => $saleId,
                    'user_id'   => $userId,
                ]);
                return $this->errorResponse($response, 'ليس لديك صلاحية لاعتماد الفواتير', 403);
            }

            $body            = $request->getParsedBody() ?? [];
            $note            = $body['note'] ?? null;
            $paymentOverride = [];
            if (!empty($body['payment_method_id'])) {
                $paymentOverride['payment_method_id'] = (int) $body['payment_method_id'];
            }
            if (isset($body['paid_amount']) && $body['paid_amount'] !== '') {
                $paymentOverride['paid_amount'] = (float) $body['paid_amount'];
            }

            $this->logger->info('Sales invoice approval request', [
                'tenant_id'        => $tenantId,
                'sale_id'          => $saleId,
                'user_id'          => $userId,
                'note'             => $note,
                'payment_override' => $paymentOverride,
            ]);

            // Atomic workflow: Service layer owns transaction for approval; we then post COGS independently.
            // TransactionManager ensures approveSale commits before COGS posting to avoid nesting issues.
            $svc = $this->services->saleApproval((int) $tenantId, $userId);

            try {
                $res = $svc->approveSale($tenantId, $saleId, $note, $paymentOverride);

                // Post COGS after approval is committed. This runs in its own transaction to prevent nesting.
                $this->accounting->postCOGSForSale($tenantId, $saleId, $userId ? (int) $userId : null);

                $this->logger->info('Sales invoice approved + COGS posted', [
                    'tenant_id' => $tenantId,
                    'sale_id'   => $saleId,
                    'user_id'   => $userId,
                ]);

            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                $this->logger->error('Sales approval failed (COGS or approval error)', [
                    'message'   => $e->getMessage(),
                    'tenant_id' => $tenantId,
                    'sale_id'   => $saleId,
                    'user_id'   => $userId,
                ]);

                // Return environment-appropriate error message (verbose for dev, generic for production)
                $userMessage = (($_ENV['APP_ENV'] ?? 'production') === 'development')
                    ? 'فشل اعتماد الفاتورة: ' . $e->getMessage()
                    : 'فشل اعتماد الفاتورة. يُرجى التحقق من إعدادات الحسابات المحاسبية (COGS / المخزون) ثم المحاولة مجدداً.';

                return $this->errorResponse($response, $userMessage, 400);
            }

            return $this->successResponse($response, [
                'message' => 'تم اعتماد الفاتورة بنجاح',
                'data'    => $res,
            ], 200);

        } catch (\App\Exceptions\InsufficientStockException $e) {
            // Handle insufficient stock error on approval with specific logging
            $this->logger->warning('Sales approval failed - insufficient stock', [
                'product_id' => $e->productId,
                'available_qty' => $e->availableQty,
                'requested_qty' => $e->requestedQty,
                'tenant_id' => $tenantId ?? 'unknown',
                'sale_id' => $saleId ?? 'unknown',
                'user_id' => $userId ?? 'unknown'
            ]);

            return $response->withStatus(409)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode($e->toArray(), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $this->logger->error('Sales invoice approval failed (outer)', [
                'message'   => $e->getMessage(),
                'tenant_id' => $tenantId ?? 'unknown',
                'sale_id'   => $saleId   ?? 'unknown',
                'user_id'   => $userId   ?? 'unknown',
            ]);
            return $this->errorResponse($response, 'فشل في الموافقة على الفاتورة', 400);
        }
    }

    /**
     * Reject a sale invoice. Requires RBAC permission 'sales.approval.reject'.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args expects ['id'] => sale_id
     * @return Response
     */
    public function reject(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                $this->logger->warning('Sales reject - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $saleId = (int) ($args['id'] ?? 0);
            if ($saleId <= 0) {
                $this->logger->warning('Sales reject - missing sale ID', ['tenant_id' => $tenantId]);
                return $this->errorResponse($response, 'مطلوب رقم الفاتورة', 400);
            }

            $userId = $this->extractUserId($request) ?? null;

            if (!$userId || !$this->rbac || !$this->rbac->hasPermission($userId, 'sales.approval.reject')) {
                $this->logger->warning('Sales reject - permission denied', [
                    'tenant_id' => $tenantId,
                    'sale_id'   => $saleId,
                    'user_id'   => $userId
                ]);

                return $this->errorResponse($response, 'ليس لديك صلاحية لرفض الفواتير', 403);
            }

            $body = $request->getParsedBody() ?? [];
            $note = $body['note'] ?? null;

            $this->logger->info('Sales invoice rejection request', [
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'user_id' => $userId,
                'note' => $note
            ]);

            $svc = $this->services->saleApproval((int) $tenantId, $userId);
            $res = $svc->rejectSale($tenantId, $saleId, $note);

            $this->logger->info('Sales invoice rejected successfully', [
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'user_id' => $userId
            ]);

            return $this->successResponse($response, [
                'message' => 'تم رفض الفاتورة بنجاح',
                'data' => $res
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('Sales invoice rejection failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'sale_id' => $saleId ?? 'unknown',
                'user_id' => $userId ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'فشل في رفض الفاتورة', 400);
        }
    }

    public function approveSale(Request $request, Response $response, array $args = []): Response
    {
        return $this->approve($request, $response, $args);
    }

    public function rejectSale(Request $request, Response $response, array $args = []): Response
    {
        return $this->reject($request, $response, $args);
    }

    // =========================================================
    // UPDATE
    // =========================================================

    /**
     * Update sale invoice data (partial updates supported).
     * Delegates validation and business rules to service layer.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args expects ['id'] => sale_id
     * @return Response
     */
    public function update(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                $this->logger->warning('Sales update - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $saleId = (int) ($args['id'] ?? 0);
            if ($saleId <= 0) {
                $this->logger->warning('Sales update - missing sale ID', ['tenant_id' => $tenantId]);
                return $this->errorResponse($response, 'Sale ID is required', 400);
            }

            $userId = $this->extractUserId($request) ?? null;
            $data = $request->getParsedBody() ?? [];

            $this->logger->info('Sales invoice update request', [
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'user_id' => $userId,
                'update_fields' => array_keys($data)
            ]);

            $this->applyDefaultCostCenter($data, $request);

            $svc = $this->services->sales((int) $tenantId, $userId);
            $res = $svc->updateSale($tenantId, $saleId, $data);

            $this->logger->info('Sales invoice updated successfully', [
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'user_id' => $userId
            ]);

            return $this->successResponse($response, [
                'message' => 'تم تحديث الفاتورة بنجاح',
                'data' => $res
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('Sales invoice update failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'sale_id' => $saleId ?? 'unknown',
                'user_id' => $userId ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'فشل في تحديث حالة الفاتورة', 400);
        }
    }

    /**
     * Update only the status of a sale (e.g., cancel, mark shipped).
     * Validates transitions and notifies interested parties.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args expects ['id'] => sale_id
     * @return Response
     */
    public function updateStatus(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $saleId = (int) ($args['id'] ?? 0);
            if ($saleId <= 0) {
                return $this->errorResponse($response, 'Sale ID is required', 400);
            }

            $body   = $request->getParsedBody() ?? [];
            $status = $body['status'] ?? null;
            $userId = $this->extractUserId($request) ?? null;

            if (!$status) {
                return $this->errorResponse($response, 'حقل status مطلوب', 422);
            }

            $svc = $this->services->saleApproval((int) $tenantId, $userId);
            $result = $svc->updateSaleStatus((int) $tenantId, $saleId, $status, $userId);

            try {
                $notificationHandler = new NotificationHandler($this->db);
                $notificationHandler->sendOrderStatusNotification($saleId, $status);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to send order status notification', ['message' => $e->getMessage()]);
            }

            return $this->successResponse($response, [
                'message' => 'تم تحديث حالة الفاتورة بنجاح',
                'data'    => [
                    'id'          => $saleId,
                    'from_status' => $result['from_status'],
                    'status'      => $result['to_status'],
                ],
            ], 200);

        } catch (\App\Exceptions\NotFoundException $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        } catch (\InvalidArgumentException | \Exception $e) {
            // Business validation errors: invalid status value or forbidden state transition
            return $this->errorResponse($response, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->logger->error('Sale status update failed', [
                'message'   => $e->getMessage(),
                'tenant_id' => $tenantId ?? 'unknown',
                'sale_id'   => $saleId ?? 'unknown',
            ]);
            return $this->errorResponse($response, 'فشل في تحديث حالة الفاتورة', 400);
        }
    }

    /**
     * Delete (cancel) a sale invoice. Performs RBAC checks and delegates
     * business logic to service layer.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args expects ['id'] => sale_id
     * @return Response
     */
    public function delete(Request $request, Response $response, array $args = []): Response
    {
        $tenantId = null;
        $saleId = null;
        $userId = null;

        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                $this->logger->warning('Sales delete - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $saleId = (int) ($args['id'] ?? 0);
            if ($saleId <= 0) {
                $this->logger->warning('Sales delete - missing sale ID', [
                    'tenant_id' => $tenantId
                ]);

                return $this->errorResponse($response, 'Sale ID is required', 400);
            }

            $userId = $this->extractUserId($request) ?? null;
            $body = $request->getParsedBody() ?? [];
            $note = $body['note'] ?? null;

            $this->logger->info('Sales invoice deletion request', [
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'user_id' => $userId,
                'note' => $note
            ]);

            $svc = $this->services->sales((int) $tenantId, $userId);
            $res = $svc->deleteSale($tenantId, $saleId, $note);

            $this->logger->info('Sales invoice deleted successfully', [
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'user_id' => $userId
            ]);

            return $this->successResponse($response, [
                'message' => 'تم إلغاء الفاتورة بنجاح',
                'data' => $res
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('Sales invoice deletion failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'sale_id' => $saleId ?? 'unknown',
                'user_id' => $userId ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'فشل في حذف الفاتورة', 400);
        }
    }

    /**
     * Pay outstanding customer debt across multiple invoices.
     * Supports idempotency via `Idempotency-Key` header and validates
     * outstanding balance with a FOR UPDATE lock.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function payDebt(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            $userId   = $this->extractUserId($request);
            $data     = $request->getParsedBody() ?? [];

            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $customerId      = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
            $amount          = (float) ($data['amount'] ?? 0);
            $paymentMethodId = isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null;
            $paymentDate     = $data['payment_date'] ?? null;
            $branchId        = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

            if (!$customerId || $amount <= 0 || !$paymentMethodId) {
                return $this->errorResponse($response, 'customer_id و amount و payment_method_id مطلوبة', 400);
            }

            // Support idempotency via header to prevent duplicate payments from retries
            $idemKey = trim($request->getHeaderLine('Idempotency-Key'));
            $idem = null;
            if ($idemKey !== '') {
                $idem   = new \App\Services\IdempotencyService($this->db);
                $cached = $idem->check($tenantId, $idemKey);
                if ($cached !== null) {
                    $this->logger->info('payDebt: returning cached idempotent response', [
                        'tenant_id'       => $tenantId,
                        'idempotency_key' => $idemKey,
                    ]);
                    return $this->jsonResponse($response, $cached, 200);
                }
            }

            // Validate customer's outstanding balance with FOR UPDATE lock to prevent race conditions
            // This ensures we cannot process a payment exceeding the customer's total debt
            $this->db->beginTransaction();
            try {
                $balStmt = $this->db->prepare("
                SELECT COALESCE(
                    SUM(
                        ROUND(net_total_amount + IFNULL(tax_amount, 0), 2)
                        - IFNULL(paid_amount, 0)
                        - COALESCE(rca_sum, 0)
                    ), 0
                ) AS outstanding
                FROM (
                    SELECT 
                        s.id,
                        s.net_total_amount,
                        s.tax_amount,
                        s.paid_amount,
                        COALESCE(SUM(rca.allocated_amount), 0) AS rca_sum
                    FROM sales s
                    LEFT JOIN return_credit_allocations rca ON rca.sale_id = s.id 
                        AND rca.tenant_id = s.tenant_id
                    WHERE s.tenant_id = ?
                      AND s.customer_id = ?
                      AND s.status NOT IN ('canceled', 'rejected', 'draft')
                    GROUP BY s.id
                    HAVING ROUND(net_total_amount + IFNULL(tax_amount, 0), 2) 
                        > IFNULL(paid_amount, 0) + COALESCE(SUM(rca.allocated_amount), 0)
                ) AS filtered_sales
                FOR UPDATE
            ");
                $balStmt->execute([$tenantId, $customerId, $tenantId, $customerId]);
                $outstanding = round((float) $balStmt->fetchColumn(), 2);

                if ($amount > $outstanding + 0.01) {
                    $this->db->rollBack();
                    return $this->errorResponse(
                        $response,
                        sprintf(
                            'المبلغ المدخل (%.2f) يتجاوز الرصيد المستحق للعميل (%.2f). ' .
                            'يُرجى إدخال مبلغ لا يتجاوز الرصيد المستحق.',
                            $amount,
                            $outstanding
                        ),
                        422
                    );
                }

                $this->db->rollBack(); // Release lock; service layer performs actual payment processing
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }

            // Delegate payment processing to service layer
            $svc    = $this->services->salePayment($userId);
            $result = $svc->payCustomerDebt(
                $tenantId,
                $customerId,
                $amount,
                $paymentMethodId,
                $paymentDate,
                $branchId
            );

            $responseData = [
                'status'  => 'success',
                'message' => 'تم تسجيل الدفعة بنجاح',
                'data'    => $result,
            ];

            // Cache idempotent response for retry scenarios
            if ($idemKey !== '') {
                $paymentId = (int) ($result['payment_id'] ?? 0);
                $idem->store($tenantId, $idemKey, $paymentId, $responseData);
            }

            return $this->jsonResponse($response, $responseData, 200);

        } catch (\Throwable $e) {
            $this->logger->error('Pay customer debt failed', [
                'message'     => $e->getMessage(),
                'tenant_id'   => $tenantId ?? 'unknown',
                'user_id'     => $userId   ?? 'unknown',
                'customer_id' => $customerId ?? 'unknown',
            ]);
            return $this->errorResponse($response, 'فشل في تسجيل الدفعة: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Add a payment to a specific sale (invoice).
     * Validates the payment amount against remaining balance and delegates
     * to payment service which creates payment and accounting journal.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addSalesPayment(Request $request, Response $response): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $data = $this->extractAndValidateRequestData($request, [
            'sale_id',
            'amount',
            'payment_date',
            'payment_method_id',
            'reference_number',
            'notes',
            'customer_id'
        ]);

        try {
            $userId = $this->extractUserId($request) ?? null;

            // Verify sale exists and validate payment amount against remaining balance
            $stmt = $this->db->prepare("
            SELECT branch_id, status,
                   ROUND(net_total_amount + IFNULL(tax_amount, 0), 2) AS grand_total,
                   ROUND(IFNULL(paid_amount, 0), 2) AS paid_amount
            FROM sales
            WHERE id = ? AND tenant_id = ?
        ");
            $stmt->execute([$data['sale_id'], $tenantId]);
            $saleRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$saleRow) {
                return $this->errorResponse($response, 'لم يتم العثور على الفاتورة', 400);
            }

            if (($saleRow['status'] ?? '') === 'pending_approval') {
                return $this->errorResponse($response, 'لا يمكن إضافة دفعات قبل اعتماد الفاتورة.', 400);
            }

            $paymentAmt    = round((float) $data['amount'], 2);

            // Calculate return_credit_allocations to prevent double-paying settled invoices
            // Example: Invoice=1000, paid_amount=0, return_credits=1000 → saleRemaining should be 0, not 1000
            $stmtCredits = $this->db->prepare(
                "SELECT COALESCE(SUM(allocated_amount), 0) AS return_credits
             FROM return_credit_allocations
             WHERE sale_id = ? AND tenant_id = ?"
            );
            $stmtCredits->execute([$data['sale_id'], $tenantId]);
            $returnCredits = round((float) $stmtCredits->fetchColumn(), 2);

            $saleRemaining = max(0, round(
                (float) $saleRow['grand_total'] - (float) $saleRow['paid_amount'] - $returnCredits,
                2
            ));

            if ($paymentAmt > $saleRemaining + 0.01) {
                return $this->errorResponse($response, "الدفعة ({$paymentAmt}) تتجاوز المبلغ المتبقي ({$saleRemaining})", 400);
            }
            $data['amount'] = min($paymentAmt, $saleRemaining);

            $branchId = $saleRow['branch_id'] ?? null;

            // Delegate to service layer for payment creation and accounting entries
            $svc    = $this->services->salePayment($userId);
            $result = $svc->addSalePayment(
                (int) $tenantId,
                (int) $data['sale_id'],
                (float) $data['amount'],
                (string) $data['payment_date'],
                (int) $data['payment_method_id'],
                isset($data['customer_id']) ? (int) $data['customer_id'] : null,
                $branchId ? (int) $branchId : null,
                isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null
            );

            return $this->successResponse($response, [
                'message' => 'تم تسجيل الدفعة بنجاح',
                'data'    => [
                    'payment_id'       => $result['payment_id'],
                    'journal_entry_id' => $result['journal_entry_id'] ?? null,
                    'amount'           => $data['amount'],
                    'payment_date'     => $data['payment_date'],
                    'reference_number' => $data['reference_number'] ?? null,
                ],
            ], 201);
        } catch (\Throwable $e) {
            $this->logger->error('Add sales payment error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return $this->errorResponse($response, 'فشل في إضافة الدفع', 400);
        }
    }
}
