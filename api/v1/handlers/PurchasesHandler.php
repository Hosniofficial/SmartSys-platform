<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use PDOException;
use Throwable;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;
use App\Services\FinancialCalculationService;
use App\Services\LabelService;
use App\Services\LocaleService;
use App\Utils\PaginationHelper;
use App\Utils\DateHelper;

class PurchasesHandler extends BaseHandler
{
    private FinancialCalculationService $financialCalcService;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('purchases');
        $this->financialCalcService = new FinancialCalculationService($db);
    }

    private function getLocale(Request $request): string
    {
        return LocaleService::fromRequest($request);
    }

    private function refLabel(?string $type, string $locale = 'ar'): string
    {
        return LabelService::refLabel($type, $locale);
    }

    private function statusLabel(?string $code, string $locale = 'ar'): string
    {
        return LabelService::statusLabel($code, $locale);
    }

    private function normalizeDateTime(?string $value, bool $endOfDay = false): string
    {
        return DateHelper::normalize($value, $endOfDay);
    }

    private function determinePurchaseStatus(float $netTotal, float $paidAmount, float $returnAmount = 0.0): string
    {
        if ($netTotal <= 0)              return 'paid';
        if ($returnAmount >= $netTotal)  return 'returned';
        if ($paidAmount <= 0)            return 'due';
        return $paidAmount >= $netTotal  ? 'paid' : 'partial';
    }

    private function calculatePurchaseTotals(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            throw new Exception('يجب إضافة عناصر للفاتورة.');
        }

        $normalizedItems = [];
        $grossTotal = 0.0;

        foreach ($items as $item) {
            $item = array_merge([
                'unit_id' => 1,
                'price' => 0,
                'cost' => 0,
                'discount_amount' => 0,
                'tax_rate' => 0,
                'batch_number' => null,
                'expiry_date' => null,
                'serial' => null,
                'category_id' => null,
            ], is_array($item) ? $item : []);

            $qty = (float) ($item['quantity'] ?? 0);
            $cost = (float) ($item['cost'] ?? 0);

            if (($item['product_id'] ?? null) === null) {
                throw new Exception('كل عنصر يجب أن يحتوي على product_id.');
            }

            if ($qty <= 0 || $cost <= 0) {
                throw new Exception('يجب أن تكون الكمية والتكلفة لكل منتج أكبر من صفر.');
            }

            $itemTotal = round($qty * $cost, 2);
            $item['quantity'] = $qty;
            $item['cost'] = $cost;
            $item['price'] = $cost;
            $item['total'] = $itemTotal;

            $grossTotal += $itemTotal;
            $normalizedItems[] = $item;
        }

        $discountType = (string) ($data['discount_type'] ?? 'fixed');
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $paidAmount = (float) ($data['paid_amount'] ?? 0);

        $discountAmount = 0.0;
        if ($discountValue > 0) {
            if ($discountType === 'percentage') {
                $discountAmount = round($grossTotal * ($discountValue / 100), 2);
            } else {
                $discountAmount = round($discountValue, 2);
            }
        }

        if ($discountAmount > $grossTotal) {
            $discountAmount = $grossTotal;
        }

        $totalAfterDiscount = round($grossTotal - $discountAmount, 2);
        $taxAmount = $this->financialCalcService->calculateTax($totalAfterDiscount, $taxRate);
        $netTotal = round($totalAfterDiscount + $taxAmount, 2);

        $paidAmount = round($paidAmount, 2);
        if ($paidAmount > $netTotal) {
            throw new Exception("المبلغ المدفوع ({$paidAmount}) لا يمكن أن يتجاوز إجمالي الفاتورة ({$netTotal}).");
        }

        return [
            'items' => $normalizedItems,
            'gross_total' => round($grossTotal, 2),
            'discount_amount' => $discountAmount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'net_total' => $netTotal,
            'paid_amount' => $paidAmount,
            'status' => $this->determinePurchaseStatus($netTotal, $paidAmount),
            'total_items' => count($normalizedItems),
        ];
    }

    private function getSupplierAccountId(int $supplierId): int
    {
        return $this->services->purchase((int) $this->tenantId)->getSupplierAccountId($supplierId);
    }

    private function generatePurchaseInvoiceNumber(?string $invoiceDate = null): string
    {
        return $this->services->purchase((int) $this->tenantId)->generateInvoiceNumber($invoiceDate);
    }

    private function insertPurchaseItems(
        int $purchaseId,
        array $items,
        float $grossTotal,
        float $discountAmount,
        ?int $branchId,
        ?string $notes,
        ?int $userId,
        bool $includeTracking = true
    ): void {
        $this->services->purchase((int) $this->tenantId, $userId)->insertPurchaseItems(
            $purchaseId, $items, $grossTotal, $discountAmount, $branchId, $notes, $includeTracking
        );
    }

    private function createJournalEntryForPurchase(array $purchaseData, int $supplierAccountIdFromDb): int
    {
        $userId = isset($purchaseData['user_id']) ? (int) $purchaseData['user_id'] : null;
        return $this->services->purchase((int) $this->tenantId, $userId)->createJournalEntry($purchaseData, $supplierAccountIdFromDb);
    }

    private function recordPurchasePayment(
        int $purchaseId,
        int $supplierId,
        float $amount,
        string $paymentDate,
        int $paymentMethodId,
        ?string $referenceNumber,
        ?int $userId,
        ?int $branchId,
        ?int $costCenterId,
        ?int $supplierAccountId,
        bool $enforceCashierSession,
        ?Request $request = null,
        bool $purchaseJeAlreadyCreated = false
    ): array {
        // Resolve session ID here (HTTP layer concern) before delegating to service
        $sessionId = null;
        if ($enforceCashierSession && $request) {
            $tenantId          = (int) $this->tenantId;
            $isSessionsEnabled = $this->isSessionsEnabled($tenantId);
            $isExempt          = $this->isCashierSessionExempt($request);
            $isCash            = $this->isCashMethod($paymentMethodId, $tenantId);

            if ($isSessionsEnabled && $isCash && !$isExempt) {
                if (!$branchId) {
                    throw new Exception('يجب تحديد المخزن لإتمام الدفعة النقدية للكاشير.');
                }
                $sessionId = $this->requireOpenCashierSession($tenantId, (int) $branchId, $userId ? (int) $userId : null);
            } elseif (($isExempt || !$isSessionsEnabled) && $branchId) {
                $sessionId = $this->findOpenCashierSession($tenantId, (int) $branchId, null);
            }
        }

        return $this->services->purchase((int) $this->tenantId, $userId)->recordPayment(
            $purchaseId, $supplierId, $amount, $paymentDate, $paymentMethodId,
            $referenceNumber, $branchId, $costCenterId, $supplierAccountId,
            $sessionId, $purchaseJeAlreadyCreated
        );
    }

    private function auditSafe(
        string $action,
        string $entityType,
        int $entityId,
        array $payload,
        ?int $userId = null
    ): void {
        $this->services->purchase((int) $this->tenantId, $userId)->auditSafe(
            $action, $entityType, $entityId, $payload
        );
    }

    public function getNextInvoiceNumber(Request $request, Response $response): Response
    {
        try {
            $this->requireTenantContext($request);

            return $this->successResponse($response, [
                'invoice_number' => $this->generatePurchaseInvoiceNumber()
            ], 200);
        } catch (Throwable $e) {
            return $this->errorResponse($response, 'غير مصرح', 403);
        }
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $ctx = $this->requireTenantContext($request);
            $tenantId = $ctx['tenant_id'];

            $qp = $request->getQueryParams();
            [$page, $perPage, $offset] = PaginationHelper::fromArray($qp, 10);
            $search = trim((string) ($qp['q'] ?? $qp['search'] ?? ''));
            $supplierId = !empty($qp['supplier_id']) ? (int) $qp['supplier_id'] : null;
            $branchId = !empty($qp['branch_id']) ? (int) $qp['branch_id'] : null;
            $status = !empty($qp['status']) ? (string) $qp['status'] : null;
            $dateFrom = $qp['date_from'] ?? ($qp['start_date'] ?? null);
            $dateTo = $qp['date_to'] ?? ($qp['end_date'] ?? null);

            $sort = strtolower((string) ($qp['sort'] ?? 'invoice_date'));
            $order = strtolower((string) ($qp['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $allowedSort = [
                'id' => 'p.id',
                'invoice_date' => 'p.invoice_date',
                'total_amount' => 'p.total_amount',
                'status' => 'p.status'
            ];
            $sortCol = $allowedSort[$sort] ?? 'p.invoice_date';

            $where = ["p.tenant_id = ?", "p.invoice_number NOT LIKE 'OB-%'"];
            $params = [$tenantId];

            if ($search !== '') {
                $where[] = "(p.invoice_number LIKE ? OR s.name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }

            if ($supplierId) {
                $where[] = "p.supplier_id = ?";
                $params[] = $supplierId;
            }

            if ($branchId) {
                $where[] = "p.branch_id = ?";
                $params[] = $branchId;
            }

            if ($status) {
                $where[] = "p.status = ?";
                $params[] = $status;
            }

            if ($dateFrom) {
                $where[] = "p.invoice_date >= ?";
                $params[] = $this->normalizeDateTime($dateFrom, false);
            }

            if ($dateTo) {
                $where[] = "p.invoice_date <= ?";
                $params[] = $this->normalizeDateTime($dateTo, true);
            }

            $from = "
                FROM purchases p
                LEFT JOIN suppliers s ON p.supplier_id = s.id AND s.tenant_id = p.tenant_id
                LEFT JOIN users u ON p.user_id = u.id AND u.tenant_id = p.tenant_id
            ";
            $whereSql = " WHERE " . implode(' AND ', $where);

            $stmt = $this->db->prepare("SELECT COUNT(*) " . $from . $whereSql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $itemsSql = "
                SELECT p.*,
                    s.name AS supplier_name,
                    u.name AS created_by_name,
                    COALESCE((SELECT SUM(pm.amount) FROM payments pm
                              WHERE pm.purchase_id = p.id AND pm.tenant_id = p.tenant_id
                                AND pm.status = 'completed'), 0) AS actual_paid_amount,
                    COALESCE((SELECT SUM(r.grand_total) FROM returns r
                              WHERE r.purchase_id = p.id AND r.tenant_id = p.tenant_id
                                AND r.return_type = 'purchase'), 0) AS return_amount
                $from
                $whereSql
                ORDER BY $sortCol $order
                LIMIT $perPage OFFSET $offset
            ";
            $stmt = $this->db->prepare($itemsSql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $locale = $this->getLocale($request);
            foreach ($items as &$it) {
                $it['reference_type'] = 'purchase';
                $it['reference_id'] = $it['id'] ?? null;
                $it['reference'] = 'purchase#' . ($it['id'] ?? '');
                $it['reference_label'] = $this->refLabel('purchase', $locale);
                $actualPaid   = round((float) ($it['actual_paid_amount'] ?? $it['paid_amount'] ?? 0), 2);
                $totalAmt     = round((float) ($it['total_amount'] ?? 0), 2);
                $returnAmount = round((float) ($it['return_amount'] ?? 0), 2);
                $it['actual_paid_amount'] = $actualPaid;
                $it['return_amount']      = $returnAmount;
                $it['remaining_balance']  = $returnAmount >= $totalAmt
                    ? 0.0
                    : round(max(0.0, $totalAmt - $actualPaid), 2);
                $it['dynamic_status']     = $this->determinePurchaseStatus($totalAmt, $actualPaid, $returnAmount);
                $it['status_label'] = $this->statusLabel($it['dynamic_status'], $locale);
            }
            unset($it);

            $summary = null;
            $includeTotals = isset($qp['include_totals']) && (string) $qp['include_totals'] !== '0';
            if ($includeTotals) {
                $sumSql = "
                    SELECT
                        COALESCE(SUM(p.total_amount), 0) AS sum_total_amount,
                        COALESCE(SUM(p.tax_amount), 0) AS sum_tax,
                        COALESCE(SUM(p.discount_value), 0) AS sum_discount,
                        COALESCE(SUM(p.paid_amount), 0) AS sum_paid_amount,
                        COALESCE(SUM(p.total_amount - p.paid_amount), 0) AS sum_balance_due
                    $from
                    $whereSql
                ";
                $stmt = $this->db->prepare($sumSql);
                $stmt->execute($params);
                $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                    'sum_total_amount' => 0,
                    'sum_tax' => 0,
                    'sum_discount' => 0,
                    'sum_paid_amount' => 0,
                    'sum_balance_due' => 0
                ];
            }

            return $this->jsonResponse($response, [
                'status'     => 'success',
                'items'      => $items,
                'total'      => $total,
                'summary'    => $summary,
                'pagination' => PaginationHelper::buildMeta($total, $page, $perPage),
            ], 200);
        } catch (Throwable $e) {
            $this->logger->error('Purchases list error', [
                'message' => $e->getMessage(),
                'tenant_id' => $this->tenantId ?? null
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء جلب قائمة المشتريات', 500);
        }
    }

    public function get(Request $request, Response $response, array $args = []): Response
    {
        try {
            $ctx = $this->requireTenantContext($request);
            $tenantId = $ctx['tenant_id'];
            $id = !empty($args['id']) ? (int) $args['id'] : 0;

            if ($id <= 0) {
                return $this->errorResponse($response, 'مطلوب رقم فاتورة الشراء', 400);
            }

            $stmt = $this->db->prepare("
                SELECT
                    p.*,
                    s.name AS supplier_name,
                    s.phone AS supplier_phone,
                    u.name AS created_by_name
                FROM purchases p
                LEFT JOIN suppliers s ON p.supplier_id = s.id AND s.tenant_id = p.tenant_id
                LEFT JOIN users u ON p.user_id = u.id AND u.tenant_id = p.tenant_id
                WHERE p.id = ? AND p.tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $tenantId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) {
                return $this->errorResponse($response, 'لم يتم العثور على فاتورة الشراء', 404);
            }

            $stmt = $this->db->prepare("
                SELECT
                    pi.*,
                    p.name AS product_name,
                    p.barcode,
                    u.name AS unit_name,
                    u.code AS unit_code
                FROM purchase_items pi
                LEFT JOIN products p ON pi.product_id = p.id AND p.tenant_id = pi.tenant_id
                LEFT JOIN units u ON pi.unit_id = u.id AND (u.tenant_id = pi.tenant_id OR u.tenant_id IS NULL)
                WHERE pi.purchase_id = ? AND pi.tenant_id = ?
            ");
            $stmt->execute([$id, $tenantId]);
            $purchase['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("
                SELECT p.*, u.name AS created_by_name
                FROM payments p
                LEFT JOIN users u ON p.created_by = u.id AND u.tenant_id = p.tenant_id
                WHERE p.purchase_id = ? AND p.tenant_id = ? AND p.status = 'completed'
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([$id, $tenantId]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $locale = $this->getLocale($request);
            foreach ($payments as &$payment) {
                $payment['reference_type'] = 'payment';
                $payment['reference_id'] = $payment['id'] ?? null;
                $payment['reference'] = 'payment#' . ($payment['id'] ?? '');
                $payment['reference_label'] = $this->refLabel('payment', $locale);
                $payment['status_label'] = $this->statusLabel($payment['status'] ?? 'completed', $locale);
            }
            unset($payment);

            $actualPaid = round(array_sum(array_column($payments, 'amount')), 2);
            $totalAmt   = round((float) ($purchase['total_amount'] ?? 0), 2);

            $stmtRet = $this->db->prepare("
                SELECT COALESCE(SUM(grand_total), 0)
                FROM returns
                WHERE purchase_id = ? AND tenant_id = ? AND return_type = 'purchase'
            ");
            $stmtRet->execute([$id, $tenantId]);
            $returnAmount = round((float) $stmtRet->fetchColumn(), 2);

            $purchase['reference_type']   = 'purchase';
            $purchase['reference_id']     = $purchase['id'] ?? null;
            $purchase['reference']        = 'purchase#' . ($purchase['id'] ?? '');
            $purchase['reference_label']  = $this->refLabel('purchase', $locale);
            $purchase['actual_paid_amount'] = $actualPaid;
            $purchase['return_amount']      = $returnAmount;
            $purchase['remaining_balance']  = $returnAmount >= $totalAmt
                ? 0.0
                : round(max(0.0, $totalAmt - $actualPaid), 2);
            $purchase['dynamic_status']     = $this->determinePurchaseStatus($totalAmt, $actualPaid, $returnAmount);
            $purchase['status_label']     = $this->statusLabel($purchase['dynamic_status'], $locale);
            $purchase['payments']         = $payments;

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => '',
                'data' => $purchase
            ], 200);
        } catch (Throwable $e) {
            $this->logger->error('Purchase get error', [
                'message' => $e->getMessage(),
                'tenant_id' => $this->tenantId ?? null,
                'purchase_id' => $args['id'] ?? null
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء جلب تفاصيل فاتورة الشراء', 500);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        try {
            $ctx = $this->requireTenantContext($request);
            $userId = $ctx['user_id'];

            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];

            foreach (['supplier_id', 'branch_id', 'items', 'payment_method_id'] as $field) {
                if (empty($data[$field])) {
                    return $this->errorResponse($response, "حقل مطلوب: {$field}", 400);
                }
            }

            $data = array_merge([
                'invoice_date' => date('Y-m-d H:i:s'),
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'paid_amount' => 0,
                'notes' => '',
                'tax_rate' => 0,
            ], $data);

            $totals = $this->calculatePurchaseTotals($data);
            $invoiceDate = $this->normalizeDateTime((string) $data['invoice_date']);
            $this->applyDefaultCostCenter($data, $request);
            $costCenterId = $data['cost_center_id'] ?? 1;

            $invoiceNumber = !empty($data['invoice_number'])
                ? (string) $data['invoice_number']
                : $this->generatePurchaseInvoiceNumber($invoiceDate);

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO purchases (
                    tenant_id, supplier_id, invoice_number, invoice_date, total_amount,
                    paid_amount, discount_value, discount_type, tax_rate, tax_amount,
                    notes, payment_method_id, user_id, total_items, status, branch_id,
                    cost_center_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $maxAttempts = 5;
            $attempt = 0;

            while (true) {
                try {
                    $attempt++;

                    $stmt->execute([
                        $this->tenantId,
                        (int) $data['supplier_id'],
                        $invoiceNumber,
                        $invoiceDate,
                        $totals['net_total'],
                        $totals['paid_amount'],
                        $totals['discount_amount'],
                        $totals['discount_type'],
                        $totals['tax_rate'],
                        $totals['tax_amount'],
                        $data['notes'] ?? null,
                        (int) $data['payment_method_id'],
                        $userId,
                        $totals['total_items'],
                        $totals['status'],
                        (int) $data['branch_id'],
                        $costCenterId
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ($attempt >= $maxAttempts) {
                        throw $e;
                    }

                    $invoiceNumber = $this->generatePurchaseInvoiceNumber($invoiceDate) . '-' . date('His') . '-' . mt_rand(100, 999);
                    $this->logger->warning('Duplicate invoice detected, regenerating', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $purchaseId = (int) $this->db->lastInsertId();

            $this->insertPurchaseItems(
                $purchaseId,
                $totals['items'],
                $totals['gross_total'],
                $totals['discount_amount'],
                (int) $data['branch_id'],
                $data['notes'] ?? null,
                $userId,
                true
            );

            $supplierAccountId = $this->getSupplierAccountId((int) $data['supplier_id']);

            $this->createJournalEntryForPurchase([
                'id' => $purchaseId,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'total_amount' => $totals['net_total'],
                'paid_amount' => $totals['paid_amount'],
                'tax_amount' => $totals['tax_amount'],
                'payment_method_id' => (int) $data['payment_method_id'],
                'supplier_id' => (int) $data['supplier_id'],
                'user_id' => $userId,
                'branch_id' => (int) $data['branch_id'],
                'cost_center_id' => $costCenterId,
            ], $supplierAccountId);

            $initialPaymentId = null;
            $initialPaymentJeId = null;
            $sessionId = null;

            if ($totals['paid_amount'] > 0) {
                $paymentResult = $this->recordPurchasePayment(
                    $purchaseId,
                    (int) $data['supplier_id'],
                    (float) $totals['paid_amount'],
                    $invoiceDate,
                    (int) $data['payment_method_id'],
                    $data['reference_number'] ?? null,
                    $userId,
                    (int) $data['branch_id'],
                    $costCenterId,
                    $supplierAccountId,
                    true,
                    $request,
                    true
                );

                $initialPaymentId = $paymentResult['payment_id'] ?? null;
                $initialPaymentJeId = $paymentResult['journal_entry_id'] ?? null;
                $sessionId = $paymentResult['session_id'] ?? null;
            }

            $this->db->commit();

            $this->auditSafe('purchase_created', 'purchases', $purchaseId, [
                'tenant_id' => (int) $this->tenantId,
                'user_id' => $userId,
                'supplier_id' => (int) $data['supplier_id'],
                'branch_id' => (int) $data['branch_id'],
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'total_amount' => (float) $totals['net_total'],
                'tax_amount' => (float) $totals['tax_amount'],
                'discount_value' => (float) $totals['discount_amount'],
                'paid_amount' => (float) $totals['paid_amount'],
                'payment_method_id' => (int) $data['payment_method_id'],
                'total_items' => (int) $totals['total_items'],
                'status' => $totals['status']
            ], $userId);

            if ($initialPaymentId) {
                $this->auditSafe('purchase_payment_added', 'purchases', $purchaseId, [
                    'tenant_id' => (int) $this->tenantId,
                    'user_id' => $userId,
                    'supplier_id' => (int) $data['supplier_id'],
                    'branch_id' => (int) $data['branch_id'],
                    'session_id' => $sessionId,
                    'purchase_id' => $purchaseId,
                    'payment_id' => (int) $initialPaymentId,
                    'journal_entry_id' => $initialPaymentJeId,
                    'amount' => (float) $totals['paid_amount'],
                    'payment_method_id' => (int) $data['payment_method_id'],
                    'payment_date' => $invoiceDate
                ], $userId);
            }

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'تم إنشاء الفاتورة بنجاح',
                'data' => [
                    'id' => $purchaseId,
                    'invoice_number' => $invoiceNumber
                ]
            ], 201);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return $this->errorResponse($response, $e->getMessage() ?: 'فشل في إنشاء فاتورة الشراء', 400);
        }
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        try {
            $ctx = $this->requireTenantContext($request);
            $userId = $ctx['user_id'];
            $id = !empty($args['id']) ? (int) $args['id'] : 0;

            if ($id <= 0) {
                return $this->errorResponse($response, 'مطلوب رقم فاتورة الشراء', 400);
            }

            $stmt = $this->db->prepare(
                "SELECT * FROM purchases WHERE id = ? AND tenant_id = ? LIMIT 1"
            );
            $stmt->execute([$id, $this->tenantId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) {
                return $this->errorResponse($response, 'لم يتم العثور على فاتورة الشراء', 404);
            }

            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];

            foreach (['supplier_id', 'branch_id', 'items', 'payment_method_id'] as $field) {
                if (empty($data[$field])) {
                    return $this->errorResponse($response, "حقل مطلوب: {$field}", 400);
                }
            }

            $data = array_merge([
                'invoice_date'   => $purchase['invoice_date'] ?? date('Y-m-d H:i:s'),
                'discount_type'  => $purchase['discount_type']  ?? 'fixed',
                'discount_value' => $purchase['discount_value'] ?? 0,
                'paid_amount'    => $purchase['paid_amount']    ?? 0,
                'notes'          => $purchase['notes']          ?? '',
                'tax_rate'       => $purchase['tax_rate']       ?? 0,
            ], $data);

            $totals      = $this->calculatePurchaseTotals($data);
            $invoiceDate = $this->normalizeDateTime((string) $data['invoice_date']);
            $this->applyDefaultCostCenter($data, $request);
            $costCenterId = $data['cost_center_id'] ?? 1;

            $this->db->beginTransaction();

            $svc    = $this->services->purchase((int) $this->tenantId, $userId);
            $result = $svc->updatePurchase($id, $purchase, $data, $totals, $invoiceDate, $costCenterId);

            $this->db->commit();

            $this->auditSafe('purchase_updated', 'purchases', $id, [
                'tenant_id'        => (int) $this->tenantId,
                'user_id'          => $userId,
                'branch_id'        => (int) $data['branch_id'],
                'purchase_id'      => $id,
                'supplier_id'      => (int) $data['supplier_id'],
                'total_amount'     => (float) $totals['net_total'],
                'paid_amount'      => (float) $totals['paid_amount'],
                'tax_amount'       => (float) $totals['tax_amount'],
                'payment_method_id'=> (int) $data['payment_method_id'],
                'status'           => $totals['status'],
            ], $userId);

            return $this->jsonResponse($response, [
                'status'  => 'success',
                'message' => 'تم تحديث فاتورة الشراء بنجاح',
                'data'    => $result,
            ], 200);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->errorResponse($response, 'فشل تحديث فاتورة الشراء', 400);
        }
    }


    public function delete(Request $request, Response $response, array $args = []): Response
    {
        try {
            $ctx    = $this->requireTenantContext($request);
            $userId = $ctx['user_id'];
            $id     = !empty($args['id']) ? (int) $args['id'] : 0;

            if ($id <= 0) {
                return $this->errorResponse($response, 'Purchase ID is required', 400);
            }

            $stmt = $this->db->prepare(
                "SELECT * FROM purchases WHERE id = ? AND tenant_id = ? LIMIT 1"
            );
            $stmt->execute([$id, $this->tenantId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) {
                return $this->errorResponse($response, 'Purchase not found', 404);
            }

            $this->db->beginTransaction();

            $svc = $this->services->purchase((int) $this->tenantId, $userId);
            $svc->deletePurchase($id);

            $this->db->commit();

            $this->auditSafe('purchase_deleted', 'purchases', $id, [
                'tenant_id'      => (int) $this->tenantId,
                'user_id'        => $userId,
                'branch_id'      => $purchase['branch_id']      ?? null,
                'supplier_id'    => $purchase['supplier_id']    ?? null,
                'invoice_number' => $purchase['invoice_number'] ?? null,
            ], $userId);

            return $this->jsonResponse($response, [
                'status'  => 'success',
                'message' => 'تم حذف فاتورة الشراء بنجاح',
            ], 200);
        } catch (\App\Exceptions\NotFoundException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->errorResponse($response, $e->getMessage(), 404);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->errorResponse($response, $e->getMessage() ?: 'فشل حذف فاتورة الشراء', 400);
        }
    }

    public function addPayment(Request $request, Response $response, array $args = []): Response
    {
        try {
            $ctx        = $this->requireTenantContext($request);
            $userId     = $ctx['user_id'];
            $purchaseId = !empty($args['id']) ? (int) $args['id'] : 0;

            if ($purchaseId <= 0) {
                return $this->errorResponse($response, 'مطلوب رقم فاتورة الشراء', 400);
            }

            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];

            foreach (['amount', 'payment_date', 'payment_method_id'] as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    return $this->errorResponse($response, "حقل مطلوب: {$field}", 400);
                }
            }

            $stmt = $this->db->prepare("
                SELECT p.*, s.account_id AS supplier_account_id
                FROM purchases p
                INNER JOIN suppliers s ON s.id = p.supplier_id AND s.tenant_id = p.tenant_id
                WHERE p.id = ? AND p.tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$purchaseId, $this->tenantId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) {
                return $this->errorResponse($response, 'لم يتم العثور على فاتورة الشراء', 404);
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                return $this->errorResponse($response, 'قيمة الدفعة يجب أن تكون أكبر من صفر', 400);
            }

            $paymentDate     = $this->normalizeDateTime((string) $data['payment_date']);
            $paymentMethodId = (int) $data['payment_method_id'];
            $referenceNumber = $data['reference_number'] ?? null;
            $branchId        = !empty($data['branch_id']) ? (int) $data['branch_id'] : (int) ($purchase['branch_id'] ?? 0);
            $this->applyDefaultCostCenter($data, $request);
            $costCenterId = $data['cost_center_id'] ?? 1;

            // حل session_id (HTTP concern)
            $sessionId = null;
            $isCash    = $this->isCashMethod($paymentMethodId, (int) $this->tenantId);
            if ($isCash && $branchId) {
                $isSessionsEnabled = $this->isSessionsEnabled((int) $this->tenantId);
                $isExempt          = $this->isCashierSessionExempt($request);
                if ($isSessionsEnabled && !$isExempt) {
                    $sessionId = $this->requireOpenCashierSession((int) $this->tenantId, $branchId, $userId);
                } elseif ($isExempt || !$isSessionsEnabled) {
                    $sessionId = $this->findOpenCashierSession((int) $this->tenantId, $branchId, null);
                }
            }

            $this->db->beginTransaction();

            $svc    = $this->services->purchase((int) $this->tenantId, $userId);
            $result = $svc->addPaymentToInvoice(
                $purchaseId, $purchase, $amount, $paymentDate,
                $paymentMethodId, $referenceNumber, $branchId, $costCenterId, $sessionId
            );

            $this->db->commit();

            $this->auditSafe('purchase_payment_added', 'purchases', $purchaseId, [
                'tenant_id'         => (int) $this->tenantId,
                'user_id'           => $userId,
                'branch_id'         => $branchId,
                'session_id'        => $result['session_id']  ?? null,
                'purchase_id'       => $purchaseId,
                'payment_id'        => (int) ($result['payment_id'] ?? 0),
                'amount'            => $amount,
                'payment_method_id' => $paymentMethodId,
                'payment_date'      => $paymentDate,
                'status'            => $result['status']      ?? null,
                'paid_amount_total' => $result['new_paid']    ?? null,
            ], $userId);

            return $this->jsonResponse($response, [
                'status'  => 'success',
                'message' => 'تم تسجيل الدفعة بنجاح',
                'data'    => [
                    'payment_id'       => $result['payment_id']       ?? null,
                    'journal_entry_id' => $result['journal_entry_id'] ?? null,
                    'purchase_id'      => $purchaseId,
                    'status'           => $result['status']           ?? null,
                    'paid_amount'      => $result['new_paid']         ?? null,
                ],
            ], 201);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->errorResponse($response, $e->getMessage() ?: 'فشل تسجيل الدفعة', 400);
        }
    }

    public function paySupplierDebt(Request $request, Response $response): Response
    {
        try {
            $ctx      = $this->requireTenantContext($request);
            $tenantId = $ctx['tenant_id'];
            $userId   = $ctx['user_id'];

            $data        = $request->getParsedBody();
            $data        = is_array($data) ? $data : [];
            $supplierId  = !empty($data['supplier_id']) ? (int) $data['supplier_id'] : 0;
            $amount      = isset($data['amount']) ? (float) $data['amount'] : 0;
            $paymentDate = $this->normalizeDateTime($data['payment_date'] ?? date('Y-m-d'));
            $notes       = (string) ($data['notes'] ?? '');

            if ($supplierId <= 0 || $amount <= 0) {
                return $this->errorResponse($response, 'Supplier ID and amount are required', 400);
            }

            $stmt = $this->db->prepare(
                "SELECT id FROM suppliers WHERE id = ? AND tenant_id = ? LIMIT 1"
            );
            $stmt->execute([$supplierId, $tenantId]);
            if (!$stmt->fetch()) {
                return $this->errorResponse($response, 'Supplier not found or does not belong to this tenant', 403);
            }

            $this->db->beginTransaction();

            $svc    = $this->services->purchase((int) $tenantId, $userId);
            $result = $svc->recordSupplierDebtPayment($supplierId, $amount, $paymentDate, $notes);

            $this->db->commit();

            $this->auditSafe('supplier_payment_recorded', 'supplier_payments', $result['payment_id'], [
                'tenant_id'    => (int) $tenantId,
                'supplier_id'  => $supplierId,
                'amount'       => (float) $amount,
                'payment_date' => $paymentDate,
                'notes'        => $notes,
            ], $userId);

            return $this->jsonResponse($response, [
                'status'  => 'success',
                'message' => 'دفعة تم تسجيلها بنجاح',
                'data'    => [
                    'payment_id'   => $result['payment_id'],
                    'supplier_id'  => $supplierId,
                    'amount'       => (float) $amount,
                    'payment_date' => $paymentDate,
                ],
            ], 201);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logger->error('Failed to record supplier payment', [
                'error'     => $e->getMessage(),
                'tenant_id' => $this->tenantId ?? null,
            ]);
            return $this->errorResponse($response, 'فشل تسجيل الدفعة', 400);
        }
    }
}
