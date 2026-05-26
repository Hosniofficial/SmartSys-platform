<?php

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

class SalesHandler extends BaseHandler
{
    private $rbac;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('sales');
        $this->rbac   = new RBACHandler($db);
    }

    // =========================================================
    // LIST
    // =========================================================

    private function determineSaleStatus(float $grandTotal, float $paidAmount, float $returnAmount = 0.0): string
    {
        if ($grandTotal <= 0)                 return 'paid';
        if ($returnAmount >= $grandTotal)     return 'returned';
        if ($paidAmount <= 0)                 return 'pending_payment';
        return $paidAmount >= $grandTotal     ? 'paid' : 'partial';
    }

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
                                    AND r.return_type = 'sale'), 0)  AS return_amount
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
                $isReturned    = $grandTotal > 0 && $returnAmount >= $grandTotal;
                $row['actual_paid_amount']  = $actualPaid;
                $row['return_amount']       = $returnAmount;
                $row['remaining_balance']   = $isReturned ? 0.0 : max(0, round($grandTotal - $actualPaid, 2));
                $row['dynamic_status']      = $this->determineSaleStatus($grandTotal, $actualPaid, $returnAmount);
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
            $isReturned   = $grandTotal > 0 && $returnAmount >= $grandTotal;

            $sale['actual_paid_amount'] = $actualPaid;
            $sale['return_amount']      = $returnAmount;
            $sale['remaining_balance']  = $isReturned ? 0.0 : max(0, round($grandTotal - $actualPaid, 2));
            $sale['dynamic_status']     = $this->determineSaleStatus($grandTotal, $actualPaid, $returnAmount);

            return $this->successResponse($response, $sale, 200);
        } catch (\Throwable $e) {
            $this->logger->error('Sales get error', [
                'message' => $e->getMessage(),
                'sale_id' => $id ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'Failed to retrieve sale details', 500);
        }
    }
// =========================================================
// PENDING APPROVALS
// =========================================================

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
    // =========================================================
    // CREATE
    // =========================================================

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

    // =========================================================
    // APPROVE
    // =========================================================

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

            // ── Atomic approval: approveSale + COGS in one transaction ────────
            // TransactionManager inside approveSale owns the transaction.
            // We do NOT open a separate beginTransaction() here to avoid nesting.
            $svc = $this->services->saleApproval((int) $tenantId, $userId);

            try {
                $res = $svc->approveSale($tenantId, $saleId, $note, $paymentOverride);

                // postCOGSForSale — called after approveSale commits its transaction.
                // Runs in its own transaction via postJournalEntry's $ownTransaction check.
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

                // Surface a clear message — don't expose internal details in production
                $userMessage = (($_ENV['APP_ENV'] ?? 'production') === 'development')
                    ? 'فشل اعتماد الفاتورة: ' . $e->getMessage()
                    : 'فشل اعتماد الفاتورة. يُرجى التحقق من إعدادات الحسابات المحاسبية (COGS / المخزون) ثم المحاولة مجدداً.';

                return $this->errorResponse($response, $userMessage, 400);
            }

            return $this->successResponse($response, [
                'message' => 'تم اعتماد الفاتورة بنجاح',
                'data'    => $res,
            ], 200);

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

    // =========================================================
    // UPDATE STATUS
    // =========================================================

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
            // Business rule violations (invalid status, forbidden transition)
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

    // =========================================================
    // DELETE
    // =========================================================

    public function delete(Request $request, Response $response, array $args = []): Response
{
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
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'user_id' => $userId
        ]);

        return $this->errorResponse($response, 'فشل في حذف الفاتورة', 400);
    }
}

// =========================================================
// PAY CUSTOMER DEBT
// =========================================================

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

        // ── Idempotency check ─────────────────────────────────────────────
        $idemKey = trim($request->getHeaderLine('Idempotency-Key'));
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

        // ── Outstanding balance validation ────────────────────────────────
        // Fetch the customer's total outstanding balance (sum of unpaid invoices).
        // We use FOR UPDATE to lock the rows and prevent concurrent over-payment.
        $this->db->beginTransaction();
        try {
            $balStmt = $this->db->prepare("
                SELECT COALESCE(
                    SUM(
                        ROUND(net_total_amount + IFNULL(tax_amount, 0), 2)
                        - IFNULL(paid_amount, 0)
                    ), 0
                ) AS outstanding
                FROM sales
                WHERE tenant_id   = ?
                  AND customer_id = ?
                  AND status NOT IN ('canceled', 'rejected', 'draft')
                  AND ROUND(net_total_amount + IFNULL(tax_amount, 0), 2) > IFNULL(paid_amount, 0)
                FOR UPDATE
            ");
            $balStmt->execute([$tenantId, $customerId]);
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

            $this->db->rollBack(); // Release the FOR UPDATE lock — actual work done in SalesService
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        // ── Process payment ───────────────────────────────────────────────
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

        // ── Store idempotency result ──────────────────────────────────────
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

// =========================================================
// ADD SALES PAYMENT
// =========================================================

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

        // ── Validation ────────────────────────────────────────────────────
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
        $saleRemaining = round((float) $saleRow['grand_total'] - (float) $saleRow['paid_amount'], 2);
        if ($paymentAmt > $saleRemaining + 0.01) {
            return $this->errorResponse($response, "الدفعة ({$paymentAmt}) تتجاوز المبلغ المتبقي ({$saleRemaining})", 400);
        }
        $data['amount'] = min($paymentAmt, $saleRemaining);

        $branchId = $saleRow['branch_id'] ?? null;

        // ── تفويض للـ Service ─────────────────────────────────────────────
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

