<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use App\Services\MonologHandler;
use App\Services\BalanceCalculationService;
use App\Services\CostCenter\CostCenterService;
use App\Services\AccountManagementService;
use App\Services\CurrencyService;
use App\Handlers\AuditHandler;
use App\Repositories\SaleRepository;
use App\Repositories\PaymentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CustomersHandler extends BaseContactHandler
{
    protected string $contactType = 'customer';
    private CostCenterService $costCenterService;
    private $balanceCalcService;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('customers');
        $this->costCenterService = new CostCenterService($db);
        $this->balanceCalcService = new BalanceCalculationService($db);
    }

    /**
     * Note: listMissingAccounts() and ensureAccount() inherited from BaseContactHandler
     * ملاحظة: الدوال listMissingAccounts() و ensureAccount() موروثة من BaseContactHandler
     */

    /**
     * Returns detailed account statement for a customer by delegating to AccountStatementHandler.
     */
    public function getStatement(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                $this->logger->warning('Customer statement - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $customerId = isset($args['id']) ? (int) $args['id'] : 0;
            if ($customerId <= 0) {
                $this->logger->warning('Customer statement - missing customer ID', ['tenant_id' => $tenantId]);
                return $this->errorResponse($response, 'مطلوب رقم العميل', 400);
            }

            $this->logger->info('Customer statement request', [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId
            ]);

            $stmt = $this->db->prepare("
                SELECT account_id
                FROM customers
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $customerId,
                ':tenant_id' => $tenantId
            ]);

            $accountId = $stmt->fetchColumn();

            if (!$accountId) {
                $this->logger->warning('Customer statement - account not found', [
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId
                ]);

                return $this->errorResponse($response, 'لم يتم العثور على كشف حساب العميل', 404);
            }

            $this->logger->info('Customer statement account resolved', [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'account_id' => $accountId
            ]);

            $accountStatementHandler = new AccountStatementHandler($this->db);

            return $accountStatementHandler->getStatement($request, $response, [
                'account_id' => $accountId,
                'party_type' => 'customer',
                'party_id' => $customerId
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Customer statement error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'customer_id' => $customerId ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء جلب كشف حساب العميل', 500);
        }
    }

    private function logAction(string $action, array $details = []): void
    {
        try {
            $tenantId = isset($details['tenant_id']) ? (int) $details['tenant_id'] : null;
            $userId   = isset($details['user_id'])   ? (int) $details['user_id']   : null;
            $entityId = isset($details['customer_id']) ? (int) $details['customer_id'] : null;

            $this->logger->info('Customer action logged', [
                'action'      => $action,
                'tenant_id'   => $tenantId,
                'user_id'     => $userId,
                'customer_id' => $entityId,
            ]);

            $this->audit->logAction(
                $action,
                'customers',
                $entityId,
                $details,
                $tenantId,
                $userId
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to log customer action', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function getCompanyCurrency(int $tenantId): string
    {
        return (new CurrencyService($this->db))->getCompanyCurrency($tenantId);
    }

    /**
     * Retrieves all active customers for a specific tenant, with their correct balance.
     * The balance is calculated from the journal entries linked to the customer's specific account.
     */
    public function getCustomers(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                $this->logger->warning('Customers list - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $this->logger->info('Customers list request', ['tenant_id' => $tenantId]);

            $queryParams = $request->getQueryParams();
            $branchId = isset($queryParams['branch_id']) && $queryParams['branch_id'] !== ''
                ? (int) $queryParams['branch_id']
                : null;

            $sql = "
                SELECT
                    c.id,
                    c.tenant_id,
                    c.branch_id,
                    c.name,
                    c.phone,
                    c.tax_number,
                    c.credit_limit,
                    c.address,
                    c.email,
                    c.active,
                    c.account_id,
                    (
                        COALESCE((
                            SELECT SUM(jel.debit_amount - jel.credit_amount)
                            FROM journal_entry_lines jel
                            WHERE jel.account_id = c.account_id
                              AND jel.tenant_id = c.tenant_id
                        ), 0)
                    ) AS balance
                FROM customers c
                WHERE c.active = 1
                  AND c.tenant_id = :tenant_id
            ";

            $bindings = [':tenant_id' => $tenantId];

            if ($branchId !== null) {
                $sql .= ' AND c.branch_id = :branch_id';
                $bindings[':branch_id'] = $branchId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($customers as &$c) {
                $c['balance'] = (float)($c['balance'] ?? 0);
            }
            unset($c);

            $this->logger->info('Customers list retrieved successfully', [
                'tenant_id' => $tenantId,
                'count' => count($customers)
            ]);

            return $this->successResponse($response, $customers, 200);
        } catch (\Throwable $e) {
            $this->logger->error('Customers list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء جلب قائمة العملاء', 500);
        }
    }

    /**
     * Retrieves a single customer by ID with balance.
     */
    public function getCustomer(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        $tenantId = $this->extractTenantId($request);

        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }
        if ($id <= 0) {
            return $this->errorResponse($response, 'مطلوب رقم العميل', 400);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT
                    c.id, c.tenant_id, c.branch_id, c.name, c.phone,
                    c.tax_number, c.credit_limit, c.address, c.email,
                    c.active, c.account_id,
                    (
                        COALESCE((
                            SELECT SUM(jel.debit_amount - jel.credit_amount)
                            FROM journal_entry_lines jel
                            WHERE jel.account_id = c.account_id
                              AND jel.tenant_id = c.tenant_id
                        ), 0)
                    ) AS balance,
                    (
                        COALESCE((
                            SELECT SUM(r.grand_total)
                            FROM returns r
                            WHERE r.customer_id = c.id
                              AND r.tenant_id = c.tenant_id
                              AND r.return_type = 'sale'
                              AND r.status IN ('approved', 'completed')
                        ), 0)
                    ) AS total_returns
                FROM customers c
                WHERE c.id = :id AND c.tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                return $this->errorResponse($response, 'لم يتم العثور على العميل', 404);
            }

            $customer['balance'] = (float)($customer['balance'] ?? 0);
            $customer['total_returns'] = (float)($customer['total_returns'] ?? 0);
            return $this->successResponse($response, $customer, 200);
        } catch (\Throwable $e) {
            $this->logger->error('Get customer error', [
                'message' => $e->getMessage(),
                'customer_id' => $id,
                'tenant_id' => $tenantId
            ]);
            return $this->errorResponse($response, 'حدث خطأ أثناء جلب بيانات العميل', 500);
        }
    }

    /**
     * Creates a new customer and its corresponding sub-account in the chart of accounts.
     */
    public function createCustomer(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                $this->logger->warning('Customer create - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $data = json_decode($request->getBody()->getContents(), true);
            $data = is_array($data) ? $data : [];
            $userId = $this->extractUserId($request);

            if (empty($data['name'])) {
                return $this->errorResponse($response, 'اسم العميل مطلوب', 400);
            }

            $this->logger->info('Customer creation request', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'customer_name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null
            ]);

            $this->db->beginTransaction();

            // ✅ استخدام Single Source of Truth: AccountManagementService::createPartyAccount()
            $accountMgmt = new AccountManagementService($this->db);
            $accountId = $accountMgmt->createPartyAccount('customer', (string) $data['name'], (int) $tenantId);
            if (!$accountId) {
                throw new \Exception('Failed to create a sub-account for the customer.');
            }

            $this->logger->debug('Customer account created', [
                'tenant_id' => $tenantId,
                'account_id' => $accountId,
                'customer_name' => $data['name']
            ]);

            $branchId = isset($data['branch_id']) && $data['branch_id'] !== '' && $data['branch_id'] !== null
                ? (int) $data['branch_id']
                : null;

            $sql = "
                INSERT INTO customers (
                    tenant_id,
                    branch_id,
                    name,
                    phone,
                    tax_number,
                    credit_limit,
                    address,
                    email,
                    active,
                    account_id
                ) VALUES (
                    :tenant_id,
                    :branch_id,
                    :name,
                    :phone,
                    :tax_number,
                    :credit_limit,
                    :address,
                    :email,
                    :active,
                    :account_id
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':branch_id' => $branchId,
                ':name' => $data['name'],
                ':phone' => $data['phone'] ?? null,
                ':tax_number' => $data['tax_number'] ?? null,
                ':credit_limit' => $data['credit_limit'] ?? 0,
                ':address' => $data['address'] ?? null,
                ':email' => $data['email'] ?? null,
                ':active' => $data['active'] ?? 1,
                ':account_id' => $accountId
            ]);

            $data['id'] = (int) $this->db->lastInsertId();
            $data['account_id'] = (int) $accountId;

            $this->db->commit();

            $this->logAction('customer_created', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'customer_id' => $data['id'],
                'customer_name' => $data['name'],
                'account_id' => $accountId
            ]);

            $this->logger->info('Customer created successfully', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'customer_id' => $data['id'],
                'customer_name' => $data['name'],
                'account_id' => $accountId
            ]);

            return $this->successResponse($response, $data, 201);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                $this->logger->warning('Customer creation transaction rolled back', [
                    'tenant_id' => $tenantId ?? 'unknown',
                    'customer_name' => $data['name'] ?? 'unknown'
                ]);
            }

            $this->logger->error('Customer creation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'user_id' => $userId ?? 'unknown',
                'customer_name' => $data['name'] ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء إنشاء العميل', 500);
        }
    }

    /**
     * Updates an existing customer's information.
     * Note: This function does not handle updates to the linked account.
     */
    public function updateCustomer(Request $request, Response $response, array $args): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $this->errorResponse($response, 'مطلوب رقم العميل', 400);
        }

        $data = json_decode($request->getBody()->getContents(), true);
        $data = is_array($data) ? $data : [];
        $userId = $this->extractUserId($request);

        try {
            $this->db->beginTransaction();

            $branchId = isset($data['branch_id']) && $data['branch_id'] !== '' && $data['branch_id'] !== null
                ? (int) $data['branch_id']
                : null;

            $sql = "
                UPDATE customers
                SET
                    branch_id = :branch_id,
                    name = :name,
                    phone = :phone,
                    tax_number = :tax_number,
                    credit_limit = :credit_limit,
                    address = :address,
                    email = :email,
                    active = :active
                WHERE id = :id
                  AND tenant_id = :tenant_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':branch_id' => $branchId,
                ':name' => $data['name'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':tax_number' => $data['tax_number'] ?? null,
                ':credit_limit' => $data['credit_limit'] ?? 0,
                ':address' => $data['address'] ?? null,
                ':email' => $data['email'] ?? null,
                ':active' => $data['active'] ?? 1,
                ':id' => $id,
                ':tenant_id' => $tenantId
            ]);

            $this->db->commit();

            try {
                $this->logAction('customer_updated', [
                    'customer_id' => $id,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'customer_name' => $data['name'] ?? null
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('logAction failed', ['message' => $e->getMessage()]);
            }

            $this->logger->info('Customer updated successfully', [
                'tenant_id' => $tenantId,
                'customer_id' => $id,
                'user_id' => $userId
            ]);

            return $this->successResponse($response, $data, 200);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                    $this->logger->info('Customer update transaction rolled back successfully', [
                        'tenant_id' => $tenantId,
                        'customer_id' => $id
                    ]);
                } catch (\Throwable $rollbackError) {
                    $this->logger->error('Error during rollback', [
                        'message' => $rollbackError->getMessage()
                    ]);
                }
            }

            $this->logger->error('Customer update failed', [
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'customer_id' => $id
            ]);

            return $this->errorResponse($response, 'فشل في تحديث العميل', 500);
        }
    }

    /**
     * Deletes a customer and its associated sub-account, only if there are no active transactions.
     */
    public function deleteCustomer(Request $request, Response $response, array $args): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        try {
            $id = isset($args['id']) ? (int) $args['id'] : 0;
            if ($id <= 0) {
                return $this->errorResponse($response, 'مطلوب رقم العميل', 400);
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT account_id
                FROM customers
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $stmt->execute([
                ':id' => $id,
                ':tenant_id' => $tenantId
            ]);

            $customerAccountId = $stmt->fetchColumn();

            if (!$customerAccountId) {
                throw new \Exception('لم يتم العثور على العميل');
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM journal_entry_lines
                WHERE account_id = :account_id
                  AND tenant_id = :tenant_id
            ");
            $stmt->execute([
                ':account_id' => $customerAccountId,
                ':tenant_id' => $tenantId
            ]);

            $count = (int) $stmt->fetchColumn();

            if ($count > 0) {
                throw new \Exception('لا يمكن حذف العميل لوجود معاملات نشطة مرتبطة به');
            }

            $stmt = $this->db->prepare("
                DELETE FROM accounts
                WHERE id = :account_id
                  AND tenant_id = :tenant_id
            ");
            $stmt->execute([
                ':account_id' => $customerAccountId,
                ':tenant_id' => $tenantId
            ]);

            $stmt = $this->db->prepare("
                DELETE FROM customers
                WHERE id = :id
                  AND tenant_id = :tenant_id
            ");
            $stmt->execute([
                ':id' => $id,
                ':tenant_id' => $tenantId
            ]);

            $this->db->commit();

            try {
                $this->logAction('customer_deleted', [
                    'customer_id' => $id,
                    'tenant_id' => $tenantId
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('logAction failed', ['message' => $e->getMessage()]);
            }

            return $this->successResponse($response, [], 200);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                    $this->logger->info('Customer deletion transaction rolled back successfully', [
                        'tenant_id' => $tenantId,
                        'customer_id' => $id ?? null
                    ]);
                } catch (\Throwable $rollbackError) {
                    $this->logger->error('Error during rollback', [
                        'message' => $rollbackError->getMessage(),
                        'tenant_id' => $tenantId
                    ]);
                }
            }

            $this->logger->error('Customer deletion failed', [
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'customer_id' => $id ?? null
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء حذف العميل', 500);
        }
    }

    /**
     * Retrieves all transactions for a specific customer based on their sub-account.
     */
    public function getTransactions(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        $tenantId = $this->extractTenantId($request);

        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        if ($id <= 0) {
            return $this->errorResponse($response, 'مطلوب رقم العميل', 400);
        }

        try {
            // Resolve customer's dedicated AR account
            $stmt = $this->db->prepare("
                SELECT account_id FROM customers WHERE id = :id AND tenant_id = :tenant_id
            ");
            $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
            $customerAccountId = $stmt->fetchColumn();

            if (!$customerAccountId) {
                return $this->errorResponse($response, 'لم يتم العثور على حساب العميل', 404);
            }

            // Read from the AR ledger — now fully correct because:
            // • SalesService always writes Dr grossAmount + Cr paidAmount to customer AR
            // • ReturnsHandler always writes Cr grandTotal + Dr paidAmount to customer AR
            $transactionRows = $this->balanceCalcService->getTransactionHistoryWithRunningBalance(
                $customerAccountId,
                $tenantId,
                'customer'
            );

            if (empty($transactionRows)) {
                return $this->successResponse($response, [], 200);
            }

            // Enrich with human-readable reference numbers
            $journalIds   = array_column($transactionRows, 'journal_entry_id');
            $placeholders = implode(',', array_fill(0, count($journalIds), '?'));

            $refStmt = $this->db->prepare("
                SELECT je.id,
                       je.reference_type,
                       je.reference_id,
                       COALESCE(s.invoice_number, cv.reference, r.return_number) AS reference_number,
                       je.description
                FROM journal_entries je
                LEFT JOIN sales        s  ON je.reference_type = 'sale'         AND je.reference_id = s.id  AND s.tenant_id  = ?
                LEFT JOIN cash_vouchers cv ON je.reference_type = 'cash_voucher' AND je.reference_id = cv.id AND cv.tenant_id = ?
                LEFT JOIN returns      r  ON je.reference_type IN ('return', 'sale_return', 'purchase_return')
                    AND je.reference_id = r.id
                    AND r.tenant_id  = ?
                    AND (
                        je.reference_type = 'return'
                        OR (je.reference_type = 'sale_return'     AND r.return_type = 'sale')
                        OR (je.reference_type = 'purchase_return' AND r.return_type = 'purchase')
                    )
                WHERE je.id IN ({$placeholders})
            ");
            $refStmt->execute(array_merge([$tenantId, $tenantId, $tenantId], $journalIds));
            $refData = [];
            foreach ($refStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $refData[$row['id']] = $row;
            }

            $result = [];
            foreach ($transactionRows as $row) {
                $jeId        = $row['journal_entry_id'];
                $refType     = $refData[$jeId]['reference_type'] ?? '';
                $refId       = $refData[$jeId]['reference_id']   ?? 0;
                $refNumber   = $refData[$jeId]['reference_number'] ?? null;
                $description = $refData[$jeId]['description']     ?? ($row['description'] ?? '');
                $debit       = (float)$row['debit_amount'];
                $credit      = (float)$row['credit_amount'];

                switch ($refType) {
                    case 'sale':
                        if ($debit > 0) {
                            $type        = 'فاتورة مبيعات';
                            $description = 'دين على العميل لفاتورة رقم ' . ($refNumber ?: $refId);
                        } else {
                            // Check if it's a full or partial payment
                            $type        = 'دفعة';
                            $description = 'دفعة على فاتورة رقم ' . ($refNumber ?: $refId);
                        }
                        break;

                    case 'sale_payment':
                    case 'cash_voucher':
                        $type        = 'سند قبض';
                        $description = 'سداد من العميل - ' . ($refNumber ?: ('رقم ' . $refId));
                        break;

                    case 'return':
                        if ($credit > 0) {
                            $type        = 'إشعار دائن';
                            $description = 'إشعار دائن (مرتجع) رقم ' . ($refNumber ?: $refId);
                        } else {
                            $type        = 'استرجاع نقدي';
                            $description = 'استرداد قيمة مرتجع للعميل رقم ' . ($refNumber ?: $refId);
                        }
                        break;

                    case 'sale_return':
                        if ($credit > 0) {
                            $type        = 'إشعار دائن';
                            $description = 'إشعار دائن (مرتجع بيع) رقم ' . ($refNumber ?: $refId);
                        } else {
                            $type        = 'استرجاع نقدي';
                            $description = 'استرداد قيمة مرتجع بيع للعميل رقم ' . ($refNumber ?: $refId);
                        }
                        break;

                    case 'purchase_return':
                        if ($credit > 0) {
                            $type        = 'مرتجع مشتريات';
                            $description = 'إشعار دائن لمرتجع مشتريات رقم ' . ($refNumber ?: $refId);
                        } else {
                            $type        = 'استرجاع مشتريات';
                            $description = 'استرجاع نقدي لمرتجع شراء رقم ' . ($refNumber ?: $refId);
                        }
                        break;

                    case 'reversal':
                        $type        = 'عكس قيد';
                        $description = 'عكس قيد رقم ' . $refId;
                        break;

                    default:
                        $type = $refType ?: 'أخرى';
                        break;
                }

                $result[] = [
                    'id'            => $row['id'],
                    'date'          => $row['created_at'],
                    'type'          => $type,
                    'debit_amount'  => number_format($debit,  2, '.', ''),
                    'credit_amount' => number_format($credit, 2, '.', ''),
                    'description'   => $description,
                    'reference'     => $refNumber ?: $refId,
                    'balance'       => $row['running_balance'],
                ];
            }

            return $this->successResponse($response, $result, 200);
        } catch (\Throwable $e) {
            $this->logger->error('Transaction Fetch Error', [
                'message'     => $e->getMessage(),
                'tenant_id'   => $tenantId,
                'customer_id' => $id,
            ]);

            return $this->errorResponse($response, 'فشل في جلب المعاملات', 500);
        }
    }

    /**
     * Add payment to customer (without requiring a specific sale)
     */
    public function addPayment(Request $request, Response $response, array $args = []): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $customerId = isset($args['id']) ? (int) $args['id'] : 0;
        if ($customerId <= 0) {
            return $this->errorResponse($response, 'مطلوب رقم العميل', 400);
        }

        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        foreach (['amount', 'payment_method_id'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->errorResponse($response, "حقل مطلوب: {$field}", 400);
            }
        }

        $stmt = $this->db->prepare("
            SELECT c.*, c.account_id AS customer_account_id
            FROM customers c
            WHERE c.id = ? AND c.tenant_id = ?
        ");
        $stmt->execute([$customerId, $tenantId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            return $this->errorResponse($response, 'لم يتم العثور على العميل', 404);
        }

        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            return $this->errorResponse($response, 'قيمة الدفعة يجب أن تكون أكبر من صفر', 400);
        }

        $saleId = !empty($data['sale_id']) ? (int) $data['sale_id'] : null;
        if ($saleId !== null) {
            $stmtChk = $this->db->prepare(
                "SELECT id FROM sales WHERE id = ? AND customer_id = ? AND tenant_id = ? LIMIT 1"
            );
            $stmtChk->execute([$saleId, $customerId, $tenantId]);
            if (!$stmtChk->fetchColumn()) {
                return $this->errorResponse($response, 'فاتورة المبيعات المحددة غير موجودة أو لا تنتمي لهذا العميل.', 400);
            }
        }

        $paymentDate = $data['payment_date'] ?? date('Y-m-d');
        $paymentMethodId = (int) $data['payment_method_id'];
        $referenceNumber = $data['reference_number'] ?? ('CP-' . date('ymdHis'));
        $userId = $this->extractUserId($request);
        $branchId = $data['branch_id'] ?? null;

        try {
            $this->db->beginTransaction();

            $isSessionsEnabled = $this->isSessionsEnabled((int) $tenantId);
            $isExempt = $this->isCashierSessionExempt($request);
            $isCash = $this->isCashMethod($paymentMethodId, (int) $tenantId);
            $sessionId = null;

            if ($isSessionsEnabled && $isCash && $amount > 0 && !$isExempt) {
                if (!$branchId) {
                    throw new \Exception('يجب تحديد المخزن لإتمام الدفعة النقدية للكاشير.');
                }

                $sessionId = $this->requireOpenCashierSession(
                    (int) $tenantId,
                    (int) $branchId,
                    $userId ? (int) $userId : null
                );
            } elseif (($isExempt || !$isSessionsEnabled) && $branchId) {
                $sessionId = $this->findOpenCashierSession((int) $tenantId, (int) $branchId, null);
            }

            try {
                $costCenterId = $this->costCenterService->resolve(
                    (int) $tenantId,
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

            $currency = $this->getCompanyCurrency((int)$tenantId);
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    tenant_id,
                    customer_id,
                    sale_id,
                    amount,
                    payment_date,
                    payment_method_id,
                    reference_number,
                    created_by,
                    is_draft,
                    status,
                    type,
                    created_at,
                    cost_center_id,
                    currency
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'completed', 'customer', NOW(), ?, ?)
            ");
            $stmt->execute([
                (int) $tenantId,
                $customerId,
                $saleId,
                $amount,
                $paymentDate,
                $paymentMethodId,
                $referenceNumber,
                $userId,
                $costCenterId,
                $currency
            ]);

            $paymentId = (int) $this->db->lastInsertId();
            // ✅ Single Source of Truth: resolveLiquidityAccount يتعامل مع cash/bank/card/wallet/credit
            $liquidityAccountId = $this->accounting->resolveLiquidityAccount($paymentMethodId, (int) $tenantId);

            if ($liquidityAccountId === null) {
                throw new \Exception('طريقة الدفع آجلة (credit) — لا سطر سيولة مطلوب لهذه الدفعة.');
            }

            $customerAccountId = $customer['account_id'] ?? null;
            if (!$customerAccountId) {
                throw new \Exception('لم يتم العثور على حساب العميل.');
            }

            $jeId = $this->accounting->postPayment(
                (int) $tenantId,
                $paymentId,
                $amount,
                'customer_payment',
                null,
                null,
                null,
                $userId,
                $costCenterId,
                $liquidityAccountId,
                (int) $customerAccountId,
                'دفعة عميل - ' . $customer['name']
            );

            if (!$jeId) {
                throw new \Exception('فشل في تسجيل القيد المحاسبي للدفعة.');
            }

            if ($sessionId) {
                $stmt = $this->db->prepare("
                    UPDATE payments
                    SET session_id = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$sessionId, $paymentId, (int) $tenantId]);
            }

            $this->db->commit();

            if ($saleId !== null) {
                $saleRepo   = new SaleRepository($this->db);
                $payRepo    = new PaymentRepository($this->db);
                $totalPaid  = $payRepo->getTotalPaidForSale($saleId, $tenantId);
                $grandTotal = $saleRepo->getGrandTotal($saleId, $tenantId);
                $newStatus  = $grandTotal <= 0 ? 'paid' : ($totalPaid <= 0 ? 'due' : ($totalPaid >= $grandTotal ? 'paid' : 'partial'));
                $saleRepo->updateBalance($saleId, $tenantId, $totalPaid, $newStatus);
            }

            try {
                $audit = $this->audit;
                $audit->logAction(
                    'customer_payment_added',
                    'customers',
                    $customerId,
                    [
                        'tenant_id' => (int) $tenantId,
                        'user_id' => $userId ?? null,
                        'branch_id' => $branchId ?? null,
                        'session_id' => $sessionId ?? null,
                        'customer_id' => $customerId,
                        'payment_id' => $paymentId,
                        'amount' => $amount,
                        'payment_method_id' => $paymentMethodId,
                        'payment_date' => $paymentDate
                    ],
                    (int) $tenantId,
                    $userId
                );
            } catch (\Throwable $e) {
                // Log audit failure but don't fail the payment
                $this->logger->warning('Failed to log audit trail for customer payment', [
                    'payment_id' => $paymentId,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }

            return $this->successResponse($response, [
                'payment_id' => $paymentId,
                'journal_entry_id' => $jeId,
                'customer_id' => $customerId,
                'amount' => $amount
            ], 201);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->logger->error('Customer payment failed', [
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'customer_id' => $customerId
            ]);

            return $this->errorResponse($response, 'فشل تسجيل الدفعة', 500);
        }
    }
}
