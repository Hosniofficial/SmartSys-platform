<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Exception;
use App\Services\AccountingService;
use App\Services\MonologHandler;
use App\Repositories\SettingsRepository;

/**
 * SalePaymentService
 *
 * Handles payment operations for sales invoices.
 *
 * Methods with real logic extracted from SalesService:
 *   - addSalePayment()   ✅ real logic
 *   - payCustomerDebt()  ✅ real logic
 */
class SalePaymentService
{
    private PDO $pdo;
    private ?int $userId;
    private AccountingService $accounting;
    private SettingsRepository $settingsRepo;
    private $logger;

    public function __construct(PDO $pdo, ?int $userId = null)
    {
        $this->pdo          = $pdo;
        $this->userId       = $userId;
        $this->accounting   = new AccountingService($pdo);
        $this->settingsRepo = new SettingsRepository($pdo);
        $this->logger       = MonologHandler::getInstance('sales');
    }

    // -------------------------------------------------------------------------

    private function findOpenCashierSession(int $tenantId, int $branchId, ?int $cashierId = null): ?int
    {
        $sql    = "SELECT id FROM cashier_sessions WHERE tenant_id = ? AND branch_id = ? AND status = 'open'";
        $params = [$tenantId, $branchId];
        if ($cashierId) {
            $sql .= ' AND cashier_id = ?';
            $params[] = $cashierId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: null;
    }

    private function findOpenGlobalCashierSession(int $tenantId, ?int $cashierId = null): ?int
    {
        $sql    = "SELECT id FROM cashier_sessions WHERE tenant_id = ? AND branch_id IS NULL AND status = 'open'";
        $params = [$tenantId];
        if ($cashierId) {
            $sql .= ' AND cashier_id = ?';
            $params[] = $cashierId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: null;
    }

    private function getCurrentUserRoleId(): ?int
    {
        $stmt = $this->pdo->prepare("SELECT role_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$this->userId]);
        $rid = $stmt->fetchColumn();
        return $rid !== false ? (int) $rid : null;
    }

    private function isRoleEnforced(int $tenantId, ?int $roleId): bool
    {
        if (!$roleId) {
            return false;
        }
        $raw = (string) $this->settingsRepo->get($tenantId, 'pos.sessions.enforce_for_roles', '');
        if ($raw === '') {
            return false;
        }
        $trim    = trim($raw);
        $enforce = strpos($trim, '[') === 0
            ? (array) json_decode($trim, true)
            : array_filter(array_map('trim', explode(',', $trim)));
        return in_array((int) $roleId, array_map('intval', $enforce), true);
    }

    private function getCompanyCurrency(int $tenantId): string
    {
        return $this->settingsRepo->get($tenantId, 'company.currency', 'EGP') ?: 'EGP';
    }

    private function logAudit(string $action, string $entity, int $entityId, array $details, int $tenantId): void
    {
        try {
            (new \App\Handlers\AuditHandler($this->pdo))->logAction(
                $action,
                $entity,
                $entityId,
                $details,
                $tenantId,
                $this->userId
            );
        } catch (\Throwable $e) {
        }
    }

    // -------------------------------------------------------------------------
    // addSalePayment — real logic extracted from SalesService
    // -------------------------------------------------------------------------

    public function addSalePayment(
        int $tenantId,
        int $saleId,
        float $amount,
        string $paymentDate,
        int $paymentMethodId,
        ?int $customerId = null,
        ?int $branchId = null,
        ?int $costCenterId = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('قيمة الدفعة يجب أن تكون أكبر من صفر');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT status, branch_id, customer_id FROM sales WHERE id = ? AND tenant_id = ? FOR UPDATE"
            );
            $stmt->execute([$saleId, $tenantId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sale) {
                throw new Exception('Sale not found');
            }

            $branchId   = $branchId   ?? ($sale['branch_id']   ?? null);
            $customerId = $customerId ?? ($sale['customer_id'] ?? null);

            // Cashier session
            $sessionId = null;
            $isCash    = $this->accounting->isCashMethod($paymentMethodId, $tenantId);
            if ($isCash) {
                if (!$branchId) {
                    throw new Exception('branch_id مطلوب لإتمام دفعة نقدية.');
                }
                $sessionId = $this->findOpenCashierSession($tenantId, (int) $branchId, (int) $this->userId);
                if (!$sessionId) {
                    $roleId   = $this->getCurrentUserRoleId();
                    $enforced = $this->isRoleEnforced($tenantId, $roleId);
                    if (!$enforced) {
                        $sessionId = $this->findOpenGlobalCashierSession($tenantId, (int) $this->userId);
                    }
                }
                if (!$sessionId) {
                    throw new Exception('لا توجد جلسة كاشير مفتوحة.');
                }
            }

            if ($costCenterId === null && $branchId) {
                $costCenterId = $this->accounting->resolveCostCenterForService($tenantId, $this->userId, null, (int) $branchId);
            }

            $journalEntryId = $this->accounting->postSalePaymentJournalEntry(
                $tenantId,
                $saleId,
                $amount,
                $paymentDate,
                $paymentMethodId,
                (int) $this->userId,
                $costCenterId
            );

            $currency = $this->getCompanyCurrency($tenantId);
            $stmt = $this->pdo->prepare(
                "INSERT INTO payments (tenant_id, sale_id, amount, payment_date, payment_method_id, created_by,
                                       customer_id, type, status, created_at, session_id, cost_center_id, journal_entry_id, currency)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'sale', 'completed', NOW(), ?, ?, ?, ?)"
            );
            $stmt->execute([
                $tenantId, $saleId, $amount, $paymentDate, $paymentMethodId, $this->userId,
                $customerId, $sessionId, $costCenterId, $journalEntryId, $currency,
            ]);
            $paymentId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare(
                "UPDATE sales
                 SET paid_amount = paid_amount + ?,
                     status = CASE WHEN (paid_amount + ?) >= (net_total_amount + tax_amount) THEN 'paid' ELSE status END,
                     updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?"
            )->execute([$amount, $amount, $saleId, $tenantId]);

            if ($isCash) {
                $this->pdo->prepare(
                    "INSERT INTO cash_transactions (customer_id, amount, type, reference_type, reference_id,
                                                    payment_method_id, created_by, created_at, tenant_id, status,
                                                    session_id, cost_center_id, journal_entry_id)
                     VALUES (?, ?, 'income', 'sale', ?, ?, ?, NOW(), ?, 'completed', ?, ?, ?)"
                )->execute([$customerId, $amount, $saleId, $paymentMethodId, $this->userId, $tenantId, $sessionId, $costCenterId, $journalEntryId]);
            }

            $this->logAudit('sale_payment_added', 'sales', $saleId, [
                'tenant_id'         => (int) $tenantId,
                'user_id'           => (int) $this->userId,
                'branch_id'         => $branchId,
                'session_id'        => $sessionId,
                'sale_id'           => (int) $saleId,
                'payment_id'        => $paymentId,
                'amount'            => $amount,
                'payment_method_id' => $paymentMethodId,
                'payment_date'      => $paymentDate,
            ], (int) $tenantId);

            $this->pdo->commit();
            return ['payment_id' => $paymentId, 'journal_entry_id' => $journalEntryId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // payCustomerDebt — real logic extracted from SalesService
    // -------------------------------------------------------------------------

    public function payCustomerDebt(
        int $tenantId,
        int $customerId,
        float $paymentAmount,
        int $paymentMethodId,
        ?string $paymentDate = null,
        ?int $branchId = null
    ): array {
        if ($paymentAmount <= 0) {
            throw new Exception('مبلغ الدفع غير صالح.');
        }

        $paymentDate = $paymentDate ?? date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $isCash    = $this->accounting->isCashMethod($paymentMethodId, $tenantId);
            $sessionId = null;

            if ($isCash) {
                if (!$branchId) {
                    $roleId   = $this->getCurrentUserRoleId();
                    $enforced = $this->isRoleEnforced($tenantId, $roleId);
                    if ($enforced) {
                        throw new Exception('branch_id مطلوب لربط دفعة الدين النقدية بجلسة الكاشير.');
                    }
                    $sessionId = $this->findOpenGlobalCashierSession($tenantId, (int) $this->userId);
                } else {
                    $sessionId = $this->findOpenCashierSession($tenantId, (int) $branchId, (int) $this->userId);
                    if (!$sessionId) {
                        $roleId   = $this->getCurrentUserRoleId();
                        $enforced = $this->isRoleEnforced($tenantId, $roleId);
                        if (!$enforced) {
                            $sessionId = $this->findOpenGlobalCashierSession($tenantId, (int) $this->userId);
                        }
                    }
                }
                if (!$sessionId) {
                    $roleId   = $this->getCurrentUserRoleId();
                    $enforced = $this->isRoleEnforced($tenantId, $roleId);
                    if ($enforced) {
                        throw new Exception('لا توجد جلسة كاشير مفتوحة لهذا المخزن.');
                    }
                    $this->pdo->prepare(
                        "INSERT INTO cashier_sessions (tenant_id, branch_id, cashier_id, session_type, start_time, end_time, status, created_by, created_at, updated_at)
                         VALUES (?, ?, ?, 'admin', NOW(), NOW(), 'closed', ?, NOW(), NOW())"
                    )->execute([$tenantId, $branchId ?? null, $this->userId, $this->userId]);
                    $sessionId = (int) $this->pdo->lastInsertId();
                }
            }

            $custStmt = $this->pdo->prepare("SELECT account_id FROM customers WHERE id = ? AND tenant_id = ?");
            $custStmt->execute([$customerId, $tenantId]);
            $customerAccountId = $custStmt->fetchColumn();
            if (!$customerAccountId) {
                throw new Exception("Account ID not found for customer {$customerId}.");
            }

            $journalEntryId = $this->accounting->postDebtPaymentJournalEntry(
                $tenantId,
                $customerId,
                (int) $customerAccountId,
                $paymentAmount,
                $paymentDate,
                $paymentMethodId,
                (int) $this->userId,
                null
            );

            $currency = $this->getCompanyCurrency($tenantId);
            $stmt = $this->pdo->prepare(
                "INSERT INTO payments (tenant_id, amount, payment_date, payment_method_id, customer_id,
                                       type, status, created_at, created_by, session_id, journal_entry_id, currency)
                 VALUES (?, ?, ?, ?, ?, 'debt_payment', 'completed', NOW(), ?, ?, ?, ?)"
            );
            $stmt->execute([
                $tenantId, $paymentAmount, $paymentDate, $paymentMethodId, $customerId,
                $this->userId, $sessionId, $journalEntryId, $currency,
            ]);
            $paymentId = $this->pdo->lastInsertId();

            if ($isCash) {
                $this->pdo->prepare(
                    "INSERT INTO cash_transactions (customer_id, amount, type, reference_type, reference_id,
                                                    payment_method_id, created_by, created_at, tenant_id, status, session_id, journal_entry_id)
                     VALUES (?, ?, 'income', 'debt_payment', ?, ?, ?, NOW(), ?, 'completed', ?, ?)"
                )->execute([$customerId, $paymentAmount, $paymentId, $paymentMethodId, $this->userId, $tenantId, $sessionId, $journalEntryId]);
            }

            $this->pdo->commit();
            return ['payment_id' => $paymentId, 'journal_entry_id' => $journalEntryId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
