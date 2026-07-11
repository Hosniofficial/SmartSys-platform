<?php

namespace App\Handlers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;
use App\Services\BalanceCalculationService;
use App\Services\CostCenter\CostCenterService;
use App\Services\AccountManagementService;
use App\Services\CurrencyService;
use App\Handlers\AuditHandler;
use App\Repositories\PurchaseRepository;
use App\Repositories\PaymentRepository;
class SuppliersHandler extends BaseContactHandler
{
    protected string $contactType = 'supplier';
    private $costCenterService;
    private $balanceCalcService;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('suppliers');
        $this->costCenterService = new CostCenterService($db);
        $this->balanceCalcService = new BalanceCalculationService($db);
    }

    /**
     * Note: listMissingAccounts() and ensureAccount() inherited from BaseContactHandler
     * ملاحظة: الدوال listMissingAccounts() و ensureAccount() موروثة من BaseContactHandler
     */

    /**
     * Returns detailed account statement for a supplier by delegating to AccountStatementHandler.
     */
    public function getStatement(Request $request, Response $response, array $args)
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $supplierId = $args['id'] ?? null;
        if (!$supplierId) {
            return $this->errorResponse($response, 'مطلوب رقم المورد', 400);
        }

        $stmt = $this->db->prepare(
            "SELECT account_id
             FROM suppliers
             WHERE id = :id AND tenant_id = :tenant_id
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $supplierId,
            ':tenant_id' => $tenantId
        ]);
        $accountId = $stmt->fetchColumn();

        if (!$accountId) {
            return $this->errorResponse($response, 'لم يتم العثور على حساب المورد', 403);
        }

        $accountStatementHandler = new AccountStatementHandler($this->db);
        return $accountStatementHandler->getStatement($request, $response, ['account_id' => $accountId]);
    }

    private function logAction(string $action, array $details = []): void
    {
        try {
            $tenantId = isset($details['tenant_id']) ? (int) $details['tenant_id'] : null;
            $userId   = isset($details['user_id'])   ? (int) $details['user_id']   : null;
            $entityId = isset($details['supplier_id']) ? (int) $details['supplier_id'] : null;

            $this->logger->info('Supplier action logged', [
                'action'      => $action,
                'tenant_id'   => $tenantId,
                'user_id'     => $userId,
                'supplier_id' => $entityId,
            ]);

            $this->audit->logAction(
                $action,
                'suppliers',
                $entityId,
                $details,
                $tenantId,
                $userId
            );
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }

    private function getCompanyCurrency(int $tenantId): string
    {
        return (new CurrencyService($this->db))->getCompanyCurrency($tenantId);
    }

    /**
     * Retrieves all active suppliers for a specific tenant, with their correct balance.
     * The balance is calculated from the journal entries linked to the supplier's specific account.
     */
    public function getSuppliers(Request $request, Response $response)
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $queryParams = $request->getQueryParams();
        $branchId = isset($queryParams['branch_id']) && $queryParams['branch_id'] !== ''
            ? (int) $queryParams['branch_id']
            : null;

        $sql = "
            SELECT
                s.id,
                s.tenant_id,
                s.branch_id,
                s.name,
                s.phone,
                s.email,
                s.address,
                s.active,
                s.discount_percentage,
                s.credit_limit,
                s.account_id,
                (
                    COALESCE((
                        SELECT SUM(jel.credit_amount - jel.debit_amount)
                        FROM journal_entry_lines jel
                        WHERE jel.account_id = s.account_id
                          AND jel.tenant_id = s.tenant_id
                    ), 0)
                ) AS balance
            FROM suppliers s
            WHERE s.active = 1
              AND s.tenant_id = :tenant_id
        ";

        $bindings = [':tenant_id' => $tenantId];

        if ($branchId !== null) {
            $sql .= ' AND s.branch_id = :branch_id';
            $bindings[':branch_id'] = $branchId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->jsonResponse($response, [
            'status' => 'success',
            'message' => '',
            'data' => $suppliers
        ], 200);
    }

    /**
     * Creates a new supplier and its corresponding sub-account in the chart of accounts.
     */
    public function createSupplier(Request $request, Response $response)
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $data = json_decode($request->getBody()->getContents(), true);
        if (!is_array($data)) {
            $data = [];
        }

        try {
            $this->db->beginTransaction();

            // Use single source of truth: AccountManagementService::createPartyAccount()
            $accountMgmt = new AccountManagementService($this->db);
            $accountId = $accountMgmt->createPartyAccount('supplier', $data['name'] ?? '', $tenantId);
            if (!$accountId) {
                throw new \Exception('Failed to create a sub-account for the supplier.');
            }

            $branchId = isset($data['branch_id']) && $data['branch_id'] !== '' && $data['branch_id'] !== null
                ? (int) $data['branch_id']
                : null;

            $sql = "
                INSERT INTO suppliers (
                    tenant_id, branch_id, name, phone, email, address,
                    active, discount_percentage, credit_limit, account_id
                ) VALUES (
                    :tenant_id, :branch_id, :name, :phone, :email, :address,
                    :active, :discount_percentage, :credit_limit, :account_id
                )
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':branch_id' => $branchId,
                ':name' => $data['name'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':email' => $data['email'] ?? null,
                ':address' => $data['address'] ?? null,
                ':active' => $data['active'] ?? 1,
                ':discount_percentage' => $data['discount_percentage'] ?? 0,
                ':credit_limit' => $data['credit_limit'] ?? 0,
                ':account_id' => $accountId
            ]);

            $data['id'] = $this->db->lastInsertId();

            $this->db->commit();

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'تم إنشاء المورد وحسابه بنجاح',
                'data' => $data
            ], 201);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->logger->error('Failed to create supplier', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'فشل في إنشاء المورد', 500);
        }
    }

    /**
     * Updates an existing supplier's information.
     */
    public function updateSupplier(Request $request, Response $response, $args)
    {
        $id = $args['id'];
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $data = json_decode($request->getBody()->getContents(), true);
        if (!is_array($data)) {
            $data = [];
        }
        
        $userId = $this->extractUserId($request);
        
        try {
            $this->db->beginTransaction();

            $branchId = isset($data['branch_id']) && $data['branch_id'] !== '' && $data['branch_id'] !== null
                ? (int) $data['branch_id']
                : null;

            $sql = "
                UPDATE suppliers
                SET branch_id = :branch_id,
                    name = :name,
                    phone = :phone,
                    email = :email,
                    address = :address,
                    active = :active,
                    discount_percentage = :discount_percentage,
                    credit_limit = :credit_limit
                WHERE id = :id
                  AND tenant_id = :tenant_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':branch_id' => $branchId,
                ':name' => $data['name'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':email' => $data['email'] ?? null,
                ':address' => $data['address'] ?? null,
                ':active' => $data['active'] ?? 1,
                ':discount_percentage' => $data['discount_percentage'] ?? 0,
                ':credit_limit' => $data['credit_limit'] ?? 0,
                ':id' => $id,
                ':tenant_id' => $tenantId
            ]);
            
            $this->db->commit();
            
            try {
                $this->logAction('supplier_updated', [
                    'supplier_id' => $id,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'supplier_name' => $data['name'] ?? null
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('logAction failed', ['message' => $e->getMessage()]);
            }
            
            $this->logger->info('Supplier updated successfully', [
                'tenant_id' => $tenantId,
                'supplier_id' => $id,
                'user_id' => $userId
            ]);

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => "تم تحديث المورد #$id بنجاح",
                'data' => $data
            ]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                    $this->logger->info('Supplier update transaction rolled back successfully', [
                        'tenant_id' => $tenantId,
                        'supplier_id' => $id
                    ]);
                } catch (\Throwable $rollbackError) {
                    $this->logger->error('Error during rollback', [
                        'message' => $rollbackError->getMessage()
                    ]);
                }
            }
            
            $this->logger->error('Supplier update failed', [
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'supplier_id' => $id
            ]);
            
            return $this->errorResponse($response, 'فشل في تحديث المورد', 500);
        }
    }

    /**
     * Deletes a supplier and its associated sub-account, only if there are no active transactions.
     */
    public function deleteSupplier(Request $request, Response $response, array $args): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        try {
            if (!isset($args['id'])) {
                return $this->errorResponse($response, 'مطلوب رقم المورد', 400);
            }

            $id = $args['id'];

            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "SELECT account_id
                 FROM suppliers
                 WHERE id = :id AND tenant_id = :tenant_id"
            );
            $stmt->execute([
                ':id' => $id,
                ':tenant_id' => $tenantId
            ]);
            $supplierAccountId = $stmt->fetchColumn();

            if (!$supplierAccountId) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return $this->errorResponse($response, 'لم يتم العثور على المورد', 403);
            }

            $stmt = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM journal_entry_lines
                 WHERE account_id = :account_id
                   AND tenant_id = :tenant_id"
            );
            $stmt->execute([
                ':account_id' => $supplierAccountId,
                ':tenant_id' => $tenantId
            ]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return $this->errorResponse($response, 'لا يمكن حذف المورد لوجود معاملات نشطة مرتبطة به', 400);
            }

            $stmt = $this->db->prepare(
                "DELETE FROM accounts
                 WHERE id = :account_id AND tenant_id = :tenant_id"
            );
            $stmt->execute([
                ':account_id' => $supplierAccountId,
                ':tenant_id' => $tenantId
            ]);

            $stmt = $this->db->prepare(
                "DELETE FROM suppliers
                 WHERE id = :id AND tenant_id = :tenant_id"
            );
            if (!$stmt->execute([
                ':id' => $id,
                ':tenant_id' => $tenantId
            ])) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new \Exception('Failed to delete supplier');
            }

            $this->db->commit();

            try {
                $this->logAction('supplier_deleted', [
                    'supplier_id' => $id,
                    'tenant_id' => $tenantId
                ]);
            } catch (\Exception $e) {
                $this->logger->error('logAction failed', [
                    'message' => $e->getMessage()
                ]);
            }

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'تم حذف المورد وحسابه بنجاح'
            ]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                    $this->logger->info('Supplier deletion transaction rolled back successfully', [
                        'tenant_id' => $tenantId,
                        'supplier_id' => $id
                    ]);
                } catch (\Throwable $rollbackError) {
                    $this->logger->error('Error during rollback', [
                        'message' => $rollbackError->getMessage(),
                        'tenant_id' => $tenantId
                    ]);
                }
            }
            
            $this->logger->error('Supplier deletion failed', [
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'supplier_id' => $id
            ]);

            return $this->errorResponse($response, 'فشل في حذف المورد', 500);
        }
    }

    /**
     * Retrieves all transactions for a specific supplier based on their sub-account.
     */
    public function getTransactions(Request $request, Response $response, $args)
    {
        $id = $args['id'];
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'لم يتم تحديد المستأجر', 403);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT account_id
                 FROM suppliers
                 WHERE id = :id AND tenant_id = :tenant_id"
            );
            $stmt->execute([
                ':id' => $id,
                ':tenant_id' => $tenantId
            ]);
            $supplierAccountId = $stmt->fetchColumn();

            if (!$supplierAccountId) {
                return $this->errorResponse($response, 'لم يتم العثور على حساب المورد', 403);
            }

            // Use unified service for transaction history with running balance
            $transactionRows = $this->balanceCalcService->getTransactionHistoryWithRunningBalance(
                $supplierAccountId,
                $tenantId,
                'supplier'
            );

            if (empty($transactionRows)) {
                return $this->successResponse($response, [], 200);
            }

            // Get additional transaction details (reference numbers, etc.)
            $transactionIds = array_column($transactionRows, 'journal_entry_id');
            $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
            
            $sql = "
                SELECT
                    je.id,
                    je.reference_type,
                    je.reference_id,
                    COALESCE(p.invoice_number, cv.reference, r.return_number) AS reference_number,
                    je.description
                FROM journal_entries je
                LEFT JOIN purchases p ON je.reference_type = 'purchase' AND je.reference_id = p.id AND p.tenant_id = ?
                LEFT JOIN cash_vouchers cv ON je.reference_type = 'cash_voucher' AND je.reference_id = cv.id AND cv.tenant_id = ?
                LEFT JOIN returns r ON je.reference_type IN ('return', 'sale_return', 'purchase_return')
                    AND je.reference_id = r.id
                    AND r.tenant_id = ?
                    AND (
                        je.reference_type = 'return'
                        OR (je.reference_type = 'sale_return'     AND r.return_type = 'sale')
                        OR (je.reference_type = 'purchase_return' AND r.return_type = 'purchase')
                    )
                WHERE je.id IN ({$placeholders})
            ";

            $params = array_merge([$tenantId, $tenantId, $tenantId], $transactionIds);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $referenceData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_UNIQUE);

            $processedTransactions = [];

            foreach ($transactionRows as $row) {
                $type = $row['reference_type'] ?? 'unknown';
                $referenceId = $row['reference_id'] ?? 0;
                $referenceNumber = $referenceData[$row['journal_entry_id']]['reference_number'] ?? null;
                $description = $referenceData[$row['journal_entry_id']]['description'] ?? $row['description'];

                switch ($type) {
                    case 'purchase':
                        $type = 'فاتورة مشتريات';
                        $description = 'استحقاق للمورد لفاتورة رقم #' . ($referenceNumber ?: $referenceId);
                        break;

                    case 'cash_voucher':
                        $stmtVoucher = $this->db->prepare(
                            "SELECT type, notes
                             FROM cash_vouchers
                             WHERE id = ? AND tenant_id = ?
                             LIMIT 1"
                        );
                        $stmtVoucher->execute([$referenceId, $tenantId]);
                        $voucherData = $stmtVoucher->fetch(PDO::FETCH_ASSOC);

                        if ($voucherData) {
                            $type = ($voucherData['type'] == 'receipt') ? 'سند قبض' : 'سند صرف';
                            $description = $voucherData['notes'] ?: ($type . ' رقم ' . ($referenceNumber ?: $referenceId));
                        } else {
                            $type = 'سند (غير معروف)';
                            $description = 'سند رقم ' . ($referenceNumber ?: $referenceId);
                        }
                        break;

                    case 'return':
                        $stmtReturn = $this->db->prepare(
                            "SELECT return_type, return_number, notes
                             FROM returns
                             WHERE id = ? AND tenant_id = ?
                             LIMIT 1"
                        );
                        $stmtReturn->execute([$referenceId, $tenantId]);
                        $returnData = $stmtReturn->fetch(PDO::FETCH_ASSOC);

                        if ($returnData && $returnData['return_type'] === 'purchase') {
                            $type = 'مرتجع مشتريات';
                            $description = $returnData['notes'] ?: 'مرتجع مشتريات رقم ' . ($returnData['return_number'] ?: $referenceId);
                        } else {
                            $type = 'مرتجع (غير معروف)';
                            $description = 'مرتجع رقم ' . ($referenceNumber ?: $referenceId);
                        }
                        break;

                    case 'reversal':
                        $type = 'عكس قيد';
                        $description = 'عكس قيد السند رقم ' . $referenceId;
                        break;

                    default:
                        $type = $type ?: 'أخرى';
                        $description = $description ?? 'بدون وصف';
                        break;
                }

                $processedTransactions[] = [
                    'id' => $row['id'],
                    'date' => $row['created_at'],
                    'type' => $type,
                    'debit_amount' => $row['debit_amount'],
                    'credit_amount' => $row['credit_amount'],
                    'description' => $description,
                    'reference' => $referenceNumber ?: $referenceId,
                    'balance' => $row['running_balance']
                ];
            }

            return $this->successResponse($response, $processedTransactions, 200);
        } catch (\PDOException $e) {
            $this->logger->error('Supplier Transaction Fetch Error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse(
                $response,
                'فشل في جلب معاملات المورد: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Add payment to supplier (without requiring a specific purchase)
     */
    public function addPayment(Request $request, Response $response, array $args = []): Response
    {
        $this->validateAuth();

        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $this->tenantId = $tenantId;
        $tenantId = (int) $tenantId;

        $supplierId = $args['id'] ?? null;
        if (!$supplierId) {
            return $this->errorResponse($response, 'مطلوب رقم المورد', 400);
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        $required = ['amount', 'payment_method_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->errorResponse($response, "حقل مطلوب: {$field}", 400);
            }
        }

        $stmt = $this->db->prepare(
            "SELECT s.*, s.account_id AS supplier_account_id
             FROM suppliers s
             WHERE s.id = ? AND s.tenant_id = ?"
        );
        $stmt->execute([$supplierId, $tenantId]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            return $this->errorResponse($response, 'لم يتم العثور على المورد', 403);
        }

        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            return $this->errorResponse($response, 'قيمة الدفعة يجب أن تكون أكبر من صفر', 400);
        }

        $paymentDate = $data['payment_date'] ?? date('Y-m-d');
        $paymentMethodId = (int) $data['payment_method_id'];
        $referenceNumber = $data['reference_number'] ?? ('SP-' . date('ymdHis'));
        $userId = $this->extractUserId($request);
        $branchId = $data['branch_id'] ?? null;
        $purchaseId = !empty($data['purchase_id']) ? (int) $data['purchase_id'] : null;

        if ($purchaseId !== null) {
            $stmtP = $this->db->prepare(
                "SELECT id FROM purchases WHERE id = ? AND supplier_id = ? AND tenant_id = ? LIMIT 1"
            );
            $stmtP->execute([$purchaseId, $supplierId, $tenantId]);
            if (!$stmtP->fetchColumn()) {
                return $this->errorResponse($response, 'فاتورة الشراء المحددة غير موجودة أو لا تنتمي لهذا المورد.', 400);
            }
        }

        try {
            $this->db->beginTransaction();

            $isSessionsEnabled = $this->isSessionsEnabled($tenantId);
            $isExempt = $this->isCashierSessionExempt($request);
            $isCash = $this->isCashMethod($paymentMethodId, $tenantId);
            $sessionId = null;

            if ($isSessionsEnabled && $isCash && $amount > 0 && !$isExempt) {
                if (!$branchId) {
                    $this->db->rollBack();
                    return $this->errorResponse($response, 'يجب تحديد المخزن لإتمام الدفعة النقدية للكاشير.', 400);
                }

                $sessionId = $this->requireOpenCashierSession(
                    $tenantId,
                    (int) $branchId,
                    $userId ? (int) $userId : null
                );
            } elseif (($isExempt || !$isSessionsEnabled) && $branchId) {
                $sessionId = $this->findOpenCashierSession($tenantId, (int) $branchId, null);
            }

            try {
                $costCenterId = $this->costCenterService->resolve(
                    $tenantId,
                    $userId ? (int) $userId : null,
                    isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null
                );
            } catch (\Exception $e) {
                return $this->errorResponse(
                    $response,
                    'لا توجد مراكز تكلفة. يرجى إنشاء مركز تكلفة واحد على الأقل.',
                    400
                );
            }

            $currency = $this->getCompanyCurrency((int)$this->tenantId);
            $stmt = $this->db->prepare(
                "INSERT INTO payments (
                    tenant_id, supplier_id, amount, payment_date,
                    payment_method_id, reference_number, created_by,
                    is_draft, status, type, created_at, cost_center_id, purchase_id, currency
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, 0, 'completed', 'supplier', NOW(), ?, ?, ?
                )"
            );
            $stmt->execute([
                $this->tenantId,
                $supplierId,
                $amount,
                $paymentDate,
                $paymentMethodId,
                $referenceNumber,
                $userId,
                $costCenterId,
                $purchaseId,
                $currency
            ]);
            $paymentId = $this->db->lastInsertId();

            // Resolve the liquidity account for the payment method
            $liquidityAccountId = $this->accounting->resolveLiquidityAccount($paymentMethodId, (int) $tenantId);

            if ($liquidityAccountId === null) {
                throw new \Exception('طريقة الدفع آجلة (credit) — لا سطر سيولة مطلوب لهذه الدفعة.');
            }

            // مدين: المورد (custom account)
            $supplierAccountId = $supplier['supplier_account_id'] ?? null;
            if (!$supplierAccountId) {
                throw new \Exception('لم يتم العثور على حساب المورد.');
            }

            // ✅ Single Source of Truth: Use AccountingService::postPayment()
            $jeId = $accSvc->postPayment(
                (int) $tenantId,
                $paymentId,
                $amount,
                'supplier_payment',
                null,                          // saleId
                $purchaseId,                   // purchaseId
                null,                          // returnId
                $userId,
                $costCenterId,
                $supplierAccountId,            // debitAccountId (supplier account - AP)
                $liquidityAccountId,           // creditAccountId (cash/bank)
                "دفعة مورد - " . $supplier['name']
            );

            if (!$jeId) {
                throw new \Exception('فشل في تسجيل القيد المحاسبي للدفعة.');
            }

            if ($sessionId) {
                $stmt = $this->db->prepare(
                    "UPDATE payments
                     SET session_id = ?
                     WHERE id = ? AND tenant_id = ?"
                );
                $stmt->execute([$sessionId, $paymentId, $tenantId]);
            }

            $this->db->commit();

            if ($purchaseId !== null) {
                $purchaseRepo = new PurchaseRepository($this->db);
                $payRepo      = new PaymentRepository($this->db);
                $totalPaid    = $payRepo->getTotalPaidForPurchase($purchaseId, $tenantId);
                $totalAmount  = $purchaseRepo->getTotalAmount($purchaseId, $tenantId);
                $newStatus    = $totalAmount <= 0 ? 'paid'
                    : ($totalPaid <= 0 ? 'due'
                    : ($totalPaid >= $totalAmount ? 'paid' : 'partial'));
                $purchaseRepo->updateBalance($purchaseId, $tenantId, $totalPaid, $newStatus);
            }

            try {
                $audit = $this->audit;
                $audit->logAction(
                    'supplier_payment_added',
                    'suppliers',
                    (int) $supplierId,
                    [
                        'tenant_id' => (int) $this->tenantId,
                        'user_id' => $userId ?? null,
                        'branch_id' => $branchId ?? null,
                        'session_id' => $sessionId ?? null,
                        'supplier_id' => (int) $supplierId,
                        'payment_id' => (int) $paymentId,
                        'amount' => (float) $amount,
                        'payment_method_id' => (int) $paymentMethodId,
                        'payment_date' => $paymentDate
                    ],
                    (int) $this->tenantId,
                    $userId
                );
            } catch (\Throwable $e) {
                // ignore audit errors
            }

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'تم تسجيل الدفعة بنجاح',
                'data' => [
                    'payment_id' => $paymentId,
                    'journal_entry_id' => $jeId,
                    'supplier_id' => $supplierId,
                    'amount' => $amount
                ]
            ], 201);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return $this->errorResponse(
                $response,
                'فشل تسجيل الدفعة: ' . $e->getMessage(),
                400
            );
        }
    }
}
