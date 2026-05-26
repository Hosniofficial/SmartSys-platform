<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;
use DateTime;
use App\Services\AccountingService;
use App\Services\CurrencyService;
use App\Services\BalanceCalculationService;
use App\Services\MonologHandler;
use App\Repositories\SaleRepository;
use App\Repositories\PurchaseRepository;
use App\Repositories\PaymentRepository;

/**
 * CashVoucherService
 *
 * Business logic for cash vouchers extracted from CashVouchersHandler.
 */
class CashVoucherService
{
    private PDO $db;
    private int $tenantId;
    private ?int $userId;
    private AccountingService $accounting;
    private BalanceCalculationService $balanceCalc;
    private $logger;

    public function __construct(PDO $db, int $tenantId, ?int $userId = null)
    {
        $this->db         = $db;
        $this->tenantId   = $tenantId;
        $this->userId     = $userId;
        $this->accounting = new AccountingService($db);
        $this->balanceCalc = new BalanceCalculationService($db);
        $this->logger     = MonologHandler::getInstance('cash_vouchers');
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Create a cash voucher — full atomic flow.
     * @throws \Exception on validation or DB error
     */
    public function createVoucher(array $data, ?int $sessionId = null): array
    {
        $saleId     = $data['sale_id']          ?? null;
        $purchaseId = $data['purchase_id']       ?? null;

        $this->db->beginTransaction();
        try {
            $journalEntryId = $this->postToJournal($data, null);
            if (!$journalEntryId) {
                throw new \Exception('Failed to create journal entry.');
            }

            $voucherReference = $this->generateVoucherReference($data['date'], (int) $journalEntryId);
            $notes = $data['notes'] ?? ($data['description'] ?? null);

            $stmt = $this->db->prepare("
                INSERT INTO cash_vouchers (
                    tenant_id, type, date, amount, currency,
                    customer_id, supplier_id, account_id, reference, notes,
                    created_by, journal_entry_id, cost_center_id, idempotency_key
                ) VALUES (
                    :tenant_id, :type, :date, :amount, :currency,
                    :customer_id, :supplier_id, :account_id, :reference, :notes,
                    :created_by, :journal_entry_id, :cost_center_id, :idempotency_key
                )
            ");
            $stmt->execute([
                ':tenant_id'        => $this->tenantId,
                ':type'             => $data['type'],
                ':date'             => $data['date'],
                ':amount'           => $data['amount'],
                ':currency'         => $data['currency'],
                ':customer_id'      => $data['customer_id'],
                ':supplier_id'      => $data['supplier_id'],
                ':account_id'       => $data['account_id'],
                ':reference'        => $voucherReference,
                ':notes'            => $notes,
                ':created_by'       => $this->userId,
                ':journal_entry_id' => (int) $journalEntryId,
                ':cost_center_id'   => $data['cost_center_id'],
                ':idempotency_key'  => $data['idempotency_key'] ?? null,
            ]);

            $voucherId = (int) $this->db->lastInsertId();

            $this->db->prepare("
                UPDATE journal_entries
                SET reference_id = :reference_id, status = 'posted'
                WHERE id = :id AND tenant_id = :tenant_id
            ")->execute([':reference_id' => $voucherId, ':id' => (int) $journalEntryId, ':tenant_id' => $this->tenantId]);

            $this->db->prepare("
                INSERT INTO cash_transactions (
                    tenant_id, customer_id, supplier_id, amount, type,
                    reference_type, reference_id, payment_method_id, description,
                    created_by, created_at, return_id, status, notes,
                    journal_entry_id, session_id, cost_center_id
                ) VALUES (
                    :tenant_id, :customer_id, :supplier_id, :amount, :type,
                    :reference_type, :reference_id, :payment_method_id, :description,
                    :created_by, :created_at, :return_id, :status, :notes,
                    :journal_entry_id, :session_id, :cost_center_id
                )
            ")->execute([
                ':tenant_id'         => $this->tenantId,
                ':customer_id'       => $data['customer_id'],
                ':supplier_id'       => $data['supplier_id'],
                ':amount'            => $data['amount'],
                ':type'              => $this->mapVoucherTypeToCashTransactionType($data['type']),
                ':reference_type'    => 'cash_voucher',
                ':reference_id'      => $voucherId,
                ':payment_method_id' => $data['payment_method_id'],
                ':description'       => $notes,
                ':created_by'        => $this->userId,
                ':created_at'        => $data['date'],
                ':return_id'         => $data['return_id'],
                ':status'            => $data['status'] ?? 'approved',
                ':notes'             => $notes,
                ':journal_entry_id'  => (int) $journalEntryId,
                ':session_id'        => $sessionId,
                ':cost_center_id'    => $data['cost_center_id'],
            ]);

            // ── ربط بالفاتورة داخل نفس الـ transaction لضمان الـ atomicity ──
            // إذا فشل linkToSale/linkToPurchase يتم rollback للكل
            if ($saleId !== null && !empty($data['customer_id'])) {
                $this->linkToSale((int) $saleId, $data, $voucherReference, (int) $journalEntryId);
            }
            if ($purchaseId !== null && !empty($data['supplier_id'])) {
                $this->linkToPurchase((int) $purchaseId, $data, $voucherReference, (int) $journalEntryId);
            }

            $this->db->commit();

            return [
                'id'               => $voucherId,
                'reference'        => $voucherReference,
                'notes'            => $notes,
                'journal_entry_id' => (int) $journalEntryId,
                'session_id'       => $sessionId,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                try { $this->db->rollBack(); } catch (\Throwable $rb) {}
            }
            throw $e;
        }
    }

    // =========================================================================
    // Validation & Normalization
    // =========================================================================

    public function normalizeVoucherPayload(array $data, bool $isUpdate = false, array $existing = []): array
    {
        $merged = $isUpdate ? array_merge($existing, $data) : $data;

        $merged['type']     = isset($merged['type'])   ? trim((string) $merged['type']) : null;
        $merged['date']     = !empty($merged['date'])  ? (string) $merged['date']       : date('Y-m-d H:i:s');
        $merged['amount']   = isset($merged['amount']) ? round((float) $merged['amount'], 2) : 0;
        $merged['currency'] = !empty($merged['currency']) ? (string) $merged['currency'] : $this->getCompanyCurrency();

        foreach (['customer_id','supplier_id','account_id','expense_account_id','branch_id',
                  'payment_method_id','cost_center_id','return_id','purchase_id','sale_id'] as $k) {
            $merged[$k] = !empty($merged[$k]) ? (int) $merged[$k] : null;
        }

        $merged['notes'] = array_key_exists('notes', $merged)
            ? $merged['notes']
            : ($merged['description'] ?? null);

        return $merged;
    }

    public function validateVoucherPayload(array $data, bool $isUpdate = false): ?string
    {
        if (empty($data['type'])) return 'حقل type مطلوب';
        if (!in_array($data['type'], ['قبض','receipt','صرف','payment'], true)) return 'نوع السند غير مدعوم';
        if (empty($data['date'])) return 'حقل date مطلوب';
        if (!isset($data['amount']) || (float) $data['amount'] <= 0) return 'قيمة amount يجب أن تكون أكبر من صفر';
        if (($data['type'] === 'قبض' || $data['type'] === 'receipt') && empty($data['customer_id']))
            return 'مطلوب رقم العميل لسندات القبض';
        if (($data['type'] === 'صرف' || $data['type'] === 'payment') && empty($data['supplier_id']) && empty($data['expense_account_id']))
            return 'مطلوب رقم المورد أو حساب المصروف لسندات الصرف';
        if (!$isUpdate && empty($data['payment_method_id'])) return 'مطلوب تحديد طريقة الدفع';
        return null;
    }

    public function validateVoucherBusinessRules(array $data, ?int $excludeVoucherId = null): void
    {
        $type   = $data['type'];
        $amount = (float) $data['amount'];
        $date   = $data['date'];

        if ($type === 'قبض' || $type === 'receipt') {
            $customerId        = (int) $data['customer_id'];
            $customerAccountId = $this->getPartyAccountId('customer', $customerId);
            if (!$customerAccountId) {
                throw new \Exception('لم يتم العثور على حساب العميل لترحيل سند القبض');
            }
            $asOfDate       = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime($date);
            $currentBalance = $this->balanceCalc->getAccountBalanceFromJournalEntries(
                $customerAccountId, $this->tenantId, 'customer', $asOfDate
            );
            if ($currentBalance <= 0) {
                throw new \Exception('لا يمكن إنشاء سند قبض لهذا العميل لأنه ليس عليه مديونية حالية (رصيده 0). استخدم سند صرف إذا كنت ترد مبلغًا للعميل.');
            }
            if ($amount > $currentBalance && !$excludeVoucherId) {
                $this->logger->warning('Receipt amount exceeds current customer balance', [
                    'tenant_id' => $this->tenantId, 'customer_id' => $customerId,
                    'balance' => $currentBalance, 'amount' => $amount,
                ]);
            }
        }
    }

    public function findVoucherByIdempotencyKey(string $idempotencyKey): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, reference FROM cash_vouchers WHERE tenant_id = ? AND idempotency_key = ? LIMIT 1"
        );
        $stmt->execute([$this->tenantId, $idempotencyKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findDuplicateVoucher(string $type, string $date, float $amount, ?int $customerId, ?int $supplierId): ?int
    {
        $sql   = "SELECT id FROM cash_vouchers WHERE tenant_id = :t AND type = :type AND date = :date
                    AND amount = :amount AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $binds = [':t' => $this->tenantId, ':type' => $type, ':date' => $date, ':amount' => $amount];

        $sql .= $customerId === null ? " AND customer_id IS NULL" : " AND customer_id = :customer_id";
        if ($customerId !== null) $binds[':customer_id'] = $customerId;

        $sql .= $supplierId === null ? " AND supplier_id IS NULL" : " AND supplier_id = :supplier_id";
        if ($supplierId !== null) $binds[':supplier_id'] = $supplierId;

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function generateVoucherReference(string $date, int $journalEntryId): string
    {
        return 'CV-' . date('Ymd', strtotime($date)) . '-' . $journalEntryId;
    }

    public function mapVoucherTypeToCashTransactionType(string $type): string
    {
        return ($type === 'قبض' || $type === 'receipt') ? 'income' : 'expense';
    }

    public function reverseJournalEntryByVoucherIdIfNeeded(int $voucherId): void
    {
        $stmt = $this->db->prepare(
            "SELECT journal_entry_id FROM cash_vouchers WHERE id = ? AND tenant_id = ? LIMIT 1"
        );
        $stmt->execute([$voucherId, $this->tenantId]);
        $journalEntryId = (int) $stmt->fetchColumn();
        if ($journalEntryId <= 0) return;

        $chk = $this->db->prepare(
            "SELECT id FROM journal_entries WHERE tenant_id = ? AND reference_type = 'reversal' AND reference_id = ? LIMIT 1"
        );
        $chk->execute([$this->tenantId, $journalEntryId]);
        if ($chk->fetchColumn()) return;

        $this->reverseJournalEntryByVoucherId($voucherId);
    }

    public function reverseJournalEntryByVoucherId(int $voucherId): void
    {
        $stmtJE = $this->db->prepare("
            SELECT * FROM journal_entries
            WHERE reference_type = 'cash_voucher' AND reference_id = :ref_id AND tenant_id = :tenant_id
            ORDER BY id DESC LIMIT 1
        ");
        $stmtJE->execute([':ref_id' => $voucherId, ':tenant_id' => $this->tenantId]);
        $originalJE = $stmtJE->fetch(PDO::FETCH_ASSOC);
        if (!$originalJE) {
            $this->logger->warning('Journal entry not found for voucher reversal', ['voucher_id' => $voucherId]);
            return;
        }

        $stmtLines = $this->db->prepare("SELECT * FROM journal_entry_lines WHERE journal_entry_id = :je_id");
        $stmtLines->execute([':je_id' => $originalJE['id']]);
        $originalLines = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

        $desc          = 'عكس قيد السند رقم ' . $voucherId . ' - بسبب التعديل/الحذف';
        $reversalLines = [];
        foreach ($originalLines as $line) {
            $reversalLines[] = [
                'account_id'  => $line['account_id'],
                'debit'       => (float) $line['credit_amount'],
                'credit'      => (float) $line['debit_amount'],
                'description' => $desc,
            ];
        }

        $reversalJeId = $this->accounting->postJournalEntry(
            $this->tenantId, 'reversal', (int) $originalJE['id'],
            $desc, $reversalLines, date('Y-m-d'), $this->userId, null,
            'reversal_' . $voucherId . '_' . time()
        );

        if ($reversalJeId) {
            $this->logger->info('Journal entry reversal created', [
                'original_entry_id' => $originalJE['id'],
                'reversal_entry_id' => $reversalJeId,
                'voucher_id'        => $voucherId,
            ]);
        }
    }

    // =========================================================================
    // postToJournal — Single Source of Truth for journal entry creation
    // =========================================================================

    public function postToJournal(array $voucherData, ?int $voucherId): int|false
    {
        if ($voucherId) {
            $stmtEx = $this->db->prepare(
                "SELECT id FROM journal_entries WHERE reference_type = 'cash_voucher' AND reference_id = ? AND tenant_id = ? LIMIT 1"
            );
            $stmtEx->execute([$voucherId, $this->tenantId]);
            $existingJeId = $stmtEx->fetchColumn();
            if ($existingJeId) {
                $this->logger->info('Journal entry already exists for this voucher', [
                    'tenant_id' => $this->tenantId, 'voucher_id' => $voucherId, 'existing_je_id' => $existingJeId,
                ]);
                return (int) $existingJeId;
            }
        }

        $type   = $voucherData['type']   ?? '';
        $amount = (float) ($voucherData['amount'] ?? 0);
        $date   = $voucherData['date']   ?? date('Y-m-d');
        $notes  = (string) ($voucherData['notes'] ?? '');

        // account_id → resolveLiquidity → fallback 1001
        $cashBankAccountId = $voucherData['account_id'] ?? null;
        if (!$cashBankAccountId && !empty($voucherData['payment_method_id'])) {
            try {
                $cashBankAccountId = $this->accounting->resolveLiquidityAccount(
                    (int) $voucherData['payment_method_id'], $this->tenantId
                );
            } catch (\Throwable $e) {
                $this->logger->warning('CashVoucher: resolveLiquidityAccount failed, falling back to 1001', [
                    'payment_method_id' => $voucherData['payment_method_id'], 'error' => $e->getMessage(),
                ]);
            }
        }
        if (!$cashBankAccountId) $cashBankAccountId = $this->getAccountIdByCode('1001');
        if (!$cashBankAccountId) {
            $this->logger->error('Journal entry failed - Cash/Bank account not found', ['tenant_id' => $this->tenantId]);
            return false;
        }

        $debitAccountId  = null;
        $creditAccountId = null;
        $description     = '';

        if ($type === 'قبض' || $type === 'receipt') {
            $customerId = $voucherData['customer_id'] ?? null;
            if (!$customerId) { $this->logger->error('Failed to post receipt voucher - customer ID missing'); return false; }
            $debitAccountId  = $cashBankAccountId;
            $creditAccountId = $this->getPartyAccountId('customer', (int) $customerId);
            $description     = "سند قبض من عميل رقم {$customerId} - {$notes}";
        } elseif ($type === 'صرف' || $type === 'payment') {
            $supplierId       = $voucherData['supplier_id']        ?? null;
            $expenseAccountId = $voucherData['expense_account_id'] ?? null;
            if (!$supplierId && !$expenseAccountId) { $this->logger->error('Failed to post payment voucher - missing supplier/expense'); return false; }
            if ($supplierId) {
                $debitAccountId = $this->getPartyAccountId('supplier', (int) $supplierId);
                $description    = "سند صرف لمورد رقم {$supplierId} - {$notes}";
            } else {
                $debitAccountId = (int) $expenseAccountId;
                $description    = "سند صرف مصروفات - {$notes}";
            }
            $creditAccountId = $cashBankAccountId;
        } else {
            $this->logger->error('Unsupported voucher type', ['type' => $type]);
            return false;
        }

        if (!$debitAccountId || !$creditAccountId) {
            $this->logger->error('Journal entry failed - party account IDs missing', ['tenant_id' => $this->tenantId]);
            return false;
        }

        try {
            $jeId = $this->accounting->postJournalEntry(
                $this->tenantId, 'cash_voucher', $voucherId, $description,
                [
                    ['account_id' => $debitAccountId,  'debit' => $amount, 'credit' => 0,       'description' => "[Dr] {$description}"],
                    ['account_id' => $creditAccountId, 'debit' => 0,       'credit' => $amount, 'description' => "[Cr] {$description}"],
                ],
                $date, $this->userId,
                $voucherData['cost_center_id']  ?? null,
                $voucherData['idempotency_key'] ?? null
            );
            if (!$jeId) { $this->logger->error('Failed to post cash voucher to journal'); return false; }
            return $jeId;
        } catch (\Throwable $e) {
            $this->logger->error('Error posting cash voucher journal entry', ['voucher_id' => $voucherId, 'message' => $e->getMessage()]);
            return false;
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function linkToSale(int $saleId, array $data, string $voucherReference, int $journalEntryId): void
    {
        $currency = $this->getCompanyCurrency();
        $this->db->prepare("
            INSERT INTO payments (tenant_id, sale_id, customer_id, amount, payment_date,
                payment_method_id, reference_number, created_by, is_draft,
                status, type, created_at, cost_center_id, journal_entry_id, currency)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'completed', 'voucher', NOW(), ?, ?, ?)
        ")->execute([
            $this->tenantId, $saleId, $data['customer_id'], $data['amount'], $data['date'],
            $data['payment_method_id'], $voucherReference, $this->userId,
            $data['cost_center_id'], $journalEntryId, $currency,
        ]);

        $saleRepo   = new SaleRepository($this->db);
        $payRepo    = new PaymentRepository($this->db);
        $totalPaid  = $payRepo->getTotalPaidForSale($saleId, $this->tenantId);
        $grandTotal = $saleRepo->getGrandTotal($saleId, $this->tenantId);
        $newStatus  = $grandTotal <= 0 ? 'paid' : ($totalPaid <= 0 ? 'due' : ($totalPaid >= $grandTotal ? 'paid' : 'partial'));
        $saleRepo->updateBalance($saleId, $this->tenantId, $totalPaid, $newStatus);
    }

    private function linkToPurchase(int $purchaseId, array $data, string $voucherReference, int $journalEntryId): void
    {
        $currency = $this->getCompanyCurrency();
        $this->db->prepare("
            INSERT INTO payments (tenant_id, purchase_id, supplier_id, amount, payment_date,
                payment_method_id, reference_number, created_by, is_draft,
                status, type, created_at, cost_center_id, journal_entry_id, currency)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'completed', 'voucher', NOW(), ?, ?, ?)
        ")->execute([
            $this->tenantId, $purchaseId, $data['supplier_id'], $data['amount'], $data['date'],
            $data['payment_method_id'], $voucherReference, $this->userId,
            $data['cost_center_id'], $journalEntryId, $currency,
        ]);

        $purchaseRepo = new PurchaseRepository($this->db);
        $payRepo      = new PaymentRepository($this->db);
        $totalPaid    = $payRepo->getTotalPaidForPurchase($purchaseId, $this->tenantId);
        $totalAmount  = $purchaseRepo->getTotalAmount($purchaseId, $this->tenantId);
        $newStatus    = $totalAmount <= 0 ? 'paid' : ($totalPaid <= 0 ? 'due' : ($totalPaid >= $totalAmount ? 'paid' : 'partial'));
        $purchaseRepo->updateBalance($purchaseId, $this->tenantId, $totalPaid, $newStatus);
    }

    private function getPartyAccountId(string $type, int $partyId): ?int
    {
        $table   = $type === 'customer' ? 'customers' : 'suppliers';
        $fallback = $type === 'customer' ? '1101' : '2101';
        $prefix   = $type === 'customer' ? '110'  : '210';
        $name     = $type === 'customer' ? 'العملاء' : 'الموردون';

        $stmt = $this->db->prepare("SELECT account_id FROM {$table} WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$partyId, $this->tenantId]);
        $accId = $stmt->fetchColumn();

        if ($accId) {
            $chk = $this->db->prepare("SELECT code FROM accounts WHERE id = ? AND tenant_id = ?");
            $chk->execute([$accId, $this->tenantId]);
            $code = $chk->fetchColumn();
            if (!$code || strpos((string)$code, $prefix) !== 0) $accId = null;
        }
        if (!$accId) $accId = $this->getAccountIdByCode($fallback) ?: $this->getAccountIdByName($name);
        return $accId ? (int) $accId : null;
    }

    private function getAccountIdByCode(string $code): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM accounts WHERE code = ? AND tenant_id = ? AND is_active = 1");
        $stmt->execute([$code, $this->tenantId]);
        $result = $stmt->fetchColumn();
        if (!$result) $this->logger->warning('Account not found by code', ['code' => $code, 'tenant_id' => $this->tenantId]);
        return $result ? (int) $result : null;
    }

    private function getAccountIdByName(string $name): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM accounts WHERE name = ? AND tenant_id = ? AND is_active = 1");
        $stmt->execute([$name, $this->tenantId]);
        $result = $stmt->fetchColumn();
        return $result ? (int) $result : null;
    }

    private function getCompanyCurrency(): string
    {
        return (new CurrencyService($this->db))->getCompanyCurrency($this->tenantId);
    }
}
