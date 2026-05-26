<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;
use PDOException;
use App\Services\AccountingService;
use App\Services\CostingService;
use App\Services\LabelService;
use App\Services\MonologHandler;
use App\Repositories\SettingsRepository;

/**
 * ReturnService
 *
 * Centralises return (مرتجع) business logic extracted from ReturnsHandler.
 * Handlers should delegate to this service instead of containing SQL/accounting logic.
 */
class ReturnService
{
    private PDO $db;
    private int $tenantId;
    private ?int $userId;
    private AccountingService $accounting;
    private SettingsRepository $settingsRepo;
    private $logger;

    public function __construct(PDO $db, int $tenantId, ?int $userId = null)
    {
        $this->db           = $db;
        $this->tenantId     = $tenantId;
        $this->userId       = $userId;
        $this->accounting   = new AccountingService($db);
        $this->settingsRepo = new SettingsRepository($db);
        $this->logger       = MonologHandler::getInstance('returns');
    }

    // -------------------------------------------------------------------------
    // Account resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve account ID via settings key then account code fallback.
     * Delegates to AccountingService (single source of truth).
     */
    public function resolveAccountId(string $settingKeyName, string $fallbackCode): ?int
    {
        return $this->accounting->resolveAccountId($this->tenantId, $settingKeyName, $fallbackCode);
    }

    /**
     * Resolve the inventory GL account for a specific branch.
     * Falls back to tenant setting then account code '1301'.
     */
    public function resolveBranchInventoryAccountId(int $branchId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT account_id FROM branches WHERE id = ? AND tenant_id = ? LIMIT 1"
            );
            $stmt->execute([$branchId, $this->tenantId]);
            $accountId = $stmt->fetchColumn();
            if ($accountId) return (int) $accountId;
        } catch (Throwable $e) {
        }

        try {
            $val = $this->settingsRepo->getInt(
                $this->tenantId,
                "branch.{$branchId}.inventory_account_id",
                0
            );
            if ($val > 0) return $val;

            $val = $this->settingsRepo->getInt(
                $this->tenantId,
                "inventory.branch.{$branchId}.account_id",
                0
            );
            if ($val > 0) return $val;
        } catch (Throwable $e) {
        }

        return $this->resolveAccountId('inventory_account_id', '1301');
    }

    /**
     * Get company currency.
     */
    public function getCompanyCurrency(): string
    {
        return $this->settingsRepo->get($this->tenantId, 'company.currency', 'EGP') ?: 'EGP';
    }

    /**
     * Adjust stock for a return item.
     *
     * For sale returns  → stock increases (in)
     * For purchase returns → stock decreases (out)
     *
     * @throws \Exception on invalid product/unit combination or return type
     */
    public function updateStock(
        int $productId,
        int $unitId,
        float $quantity,
        int $branchId,
        int $returnId,
        string $returnType,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        ?string $serial = null
    ): void {
        $quantity = round($quantity, 4);
        $userId   = $this->userId ?? 1;

        // Resolve conversion factor
        $stmt = $this->db->prepare(
            "SELECT conversion_factor FROM product_units
             WHERE product_id = ? AND unit_id = ? AND tenant_id = ? LIMIT 1"
        );
        $stmt->execute([$productId, $unitId, $this->tenantId]);
        $conversionFactor = $stmt->fetchColumn();

        if ($conversionFactor === false || (float) $conversionFactor <= 0) {
            throw new \Exception('Invalid product unit combination');
        }

        $conversionFactor = (float) $conversionFactor;
        $baseQuantity     = round($quantity * $conversionFactor, 4);

        if ($baseQuantity <= 0) {
            throw new \Exception('Invalid quantity after conversion');
        }

        if ($returnType === 'sale') {
            $change       = $baseQuantity;
            $movementType = 'in';
            $branchFrom   = null;
            $branchTo     = $branchId;
        } elseif ($returnType === 'purchase') {
            $change       = -$baseQuantity;
            $movementType = 'out';
            $branchFrom   = $branchId;
            $branchTo     = null;
        } else {
            throw new \Exception('Invalid return type');
        }

        // Calculate WAC cost
        $unitCost  = 0.0;
        $totalCost = 0.0;
        try {
            $costing        = new CostingService($this->db);
            $wacPerBaseUnit = $costing->getWeightedAverageCost($this->tenantId, $productId, date('Y-m-d H:i:s'));
            if ($wacPerBaseUnit !== null && (float) $wacPerBaseUnit > 0) {
                $unitCost  = round((float) $wacPerBaseUnit * $conversionFactor, 4);
                $totalCost = round($unitCost * $quantity, 4);
            }
        } catch (Throwable $e) {
        }

        $costAdjustment = $totalCost * ($returnType === 'sale' ? 1 : -1);

        // Update branch_products
        $this->db->prepare(
            "INSERT INTO branch_products (tenant_id, product_id, branch_id, quantity, quantity_cost)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 quantity      = quantity + VALUES(quantity),
                 quantity_cost = quantity_cost + VALUES(quantity_cost)"
        )->execute([$this->tenantId, $productId, $branchId, $change, $costAdjustment]);

        // Insert inventory transaction
        $this->db->prepare(
            "INSERT INTO inventory_transactions (
                tenant_id, product_id, unit_id, quantity, unit_cost, total_cost,
                movement_type, movement_date, batch_number, expiry_date, serial,
                reference_type, reference_id, notes, created_at, user_id, branch_from, branch_to
             ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, 'return', ?, ?, NOW(), ?, ?, ?)"
        )->execute([
            $this->tenantId, $productId, $unitId, $quantity, $unitCost, $totalCost,
            $movementType, $batchNumber, $expiryDate, $serial,
            $returnId, 'Return stock movement', $userId, $branchFrom, $branchTo,
        ]);

        $inventoryTransactionId = (int) $this->db->lastInsertId();

        // Handle expiry tracking for sale returns
        if ($returnType === 'sale' && !empty($expiryDate)) {
            $chk = $this->db->prepare(
                "SELECT has_expiry_date FROM products WHERE id = ? AND tenant_id = ?"
            );
            $chk->execute([$productId, $this->tenantId]);
            if ((int) $chk->fetchColumn()) {
                $this->db->prepare(
                    "INSERT INTO product_expiry (tenant_id, product_id, branch_id, expiry_date, quantity, batch_number)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
                )->execute([$this->tenantId, $productId, $branchId, $expiryDate, $baseQuantity, $batchNumber]);
            }
        }

        // Handle serial tracking for sale returns
        if ($returnType === 'sale' && !empty($serial)) {
            $chk = $this->db->prepare(
                "SELECT has_serial_number FROM products WHERE id = ? AND tenant_id = ?"
            );
            $chk->execute([$productId, $this->tenantId]);
            if ((int) $chk->fetchColumn()) {
                $this->db->prepare(
                    "UPDATE product_serials
                     SET status = 'instock', transaction_id = NULL
                     WHERE product_id = ? AND branch_id = ? AND serial_number = ?
                       AND tenant_id = ? AND status = 'sold'"
                )->execute([$productId, $branchId, $serial, $this->tenantId]);
            }
        }

        // Handle serial tracking for purchase returns
        if ($returnType === 'purchase' && !empty($serial)) {
            $chk = $this->db->prepare(
                "SELECT has_serial_number FROM products WHERE id = ? AND tenant_id = ?"
            );
            $chk->execute([$productId, $this->tenantId]);
            if ((int) $chk->fetchColumn()) {
                $this->db->prepare(
                    "UPDATE product_serials
                     SET status = 'damaged', transaction_id = ?
                     WHERE product_id = ? AND branch_id = ? AND serial_number = ?
                       AND tenant_id = ?"
                )->execute([$inventoryTransactionId, $productId, $branchId, $serial, $this->tenantId]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // allocateCustomerBalance
    // -------------------------------------------------------------------------

    /**
     * توزيع مبلغ خصم من رصيد العميل (من مرتجع مبيعات) على فواتير المبيعات المستحقة.
     *
     * الأولوية: الفاتورة الأصلية المرتبطة بالمرتجع أولاً، ثم أقدم الفواتير المفتوحة.
     *
     * @param int        $tenantId              معرف المستأجر
     * @param int        $customerId            معرف العميل
     * @param float      $amountToAllocate      المبلغ المراد توزيعه
     * @param int|null   $originalSaleId        الفاتورة الأصلية للمرتجع (أولوية عليا)
     * @param int|null   $paymentId             معرف الدفعة المرتبطة (لتسجيل payment_applications)
     * @param int|null   $createdBy             معرف المستخدم المنفّذ
     */
    public function allocateCustomerBalance(
        int   $customerId,
        float $amountToAllocate,
        ?int  $originalSaleId = null,
        ?int  $paymentId      = null,
        ?int  $createdBy      = null
    ): void {
        if ($amountToAllocate <= 0) {
            return;
        }

        $tenantId   = $this->tenantId;
        $toAllocate = $amountToAllocate;

        try {
            // 1. الفاتورة الأصلية أولاً
            if ($originalSaleId !== null) {
                $stmt = $this->db->prepare("
                    SELECT net_total_amount + IFNULL(tax_amount, 0) AS grand_total, paid_amount
                    FROM sales
                    WHERE id = ? AND tenant_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$originalSaleId, $tenantId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($row) {
                    $outstanding = max(0, (float) $row['grand_total'] - (float) $row['paid_amount']);
                    $apply       = min($toAllocate, $outstanding);

                    if ($apply > 0) {
                        $this->db->prepare(
                            "UPDATE sales SET paid_amount = paid_amount + ? WHERE id = ? AND tenant_id = ?"
                        )->execute([$apply, $originalSaleId, $tenantId]);

                        $this->insertPaymentApplication($paymentId, 'sale', $originalSaleId, $apply, $createdBy);
                        $toAllocate -= $apply;
                    }
                }
            }

            // 2. باقي الفواتير المفتوحة بالترتيب الزمني
            if ($toAllocate > 0) {
                $stmt = $this->db->prepare("
                    SELECT id,
                           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total,
                           paid_amount
                    FROM sales
                    WHERE customer_id = ?
                      AND tenant_id   = ?
                      AND (net_total_amount + IFNULL(tax_amount, 0) - IFNULL(paid_amount, 0)) > 0
                    ORDER BY sale_date ASC, id ASC
                    FOR UPDATE
                ");
                $stmt->execute([$customerId, $tenantId]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($rows as $r) {
                    if ($toAllocate <= 0) break;

                    // تخطّي الفاتورة الأصلية (تمت معالجتها أعلاه)
                    if ($originalSaleId !== null && (int) $r['id'] === $originalSaleId) {
                        continue;
                    }

                    $outstanding = max(0, (float) $r['grand_total'] - (float) $r['paid_amount']);
                    if ($outstanding <= 0) continue;

                    $apply = min($toAllocate, $outstanding);
                    if ($apply <= 0) continue;

                    $this->db->prepare(
                        "UPDATE sales SET paid_amount = paid_amount + ? WHERE id = ? AND tenant_id = ?"
                    )->execute([$apply, $r['id'], $tenantId]);

                    $this->insertPaymentApplication($paymentId, 'sale', (int) $r['id'], $apply, $createdBy);
                    $toAllocate -= $apply;
                }
            }
        } catch (\Throwable $e) {
            // نُسجّل الخطأ دون إيقاف العملية الرئيسية
            (MonologHandler::getInstance('returns'))->warning('allocateCustomerBalance: failed', [
                'tenant_id'   => $tenantId,
                'customer_id' => $customerId,
                'payment_id'  => $paymentId,
                'message'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper: إدراج سجل payment_application — best-effort (لا يوقف العملية عند الفشل).
     */
    private function insertPaymentApplication(
        ?int   $paymentId,
        string $refType,
        int    $refId,
        float  $amount,
        ?int   $createdBy
    ): void {
        if ($paymentId === null) return;
        try {
            $this->db->prepare("
                INSERT INTO payment_applications
                    (tenant_id, payment_id, reference_type, reference_id, amount, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ")->execute([$this->tenantId, $paymentId, $refType, $refId, $amount, $createdBy ?? 1]);
        } catch (\Throwable $e) {
            (MonologHandler::getInstance('returns'))->warning('insertPaymentApplication: failed', [
                'payment_id' => $paymentId,
                'ref_id'     => $refId,
                'message'    => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // getDetails — جلب تفاصيل المرتجع الكاملة (منقولة من ReturnsHandler::getReturnDetails)
    // -------------------------------------------------------------------------

    /**
     * جلب تفاصيل مرتجع واحد كاملاً مع القيود المحاسبية والمدفوعات.
     * يُصلح تلقائياً reference_type في journal_entries إذا كانت غير صحيحة.
     *
     * @return array|null  بيانات المرتجع أو null إذا لم يوجد
     */
    public function getDetails(int $returnId, string $locale = 'ar'): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM returns WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$returnId, $this->tenantId]);
        $return = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$return) {
            return null;
        }

        // طريقة الدفع
        if (!empty($return['payment_method_id'])) {
            $s = $this->db->prepare(
                "SELECT name FROM payment_methods WHERE id = ? AND tenant_id = ? LIMIT 1"
            );
            $s->execute([$return['payment_method_id'], $this->tenantId]);
            $return['payment_method_name'] = $s->fetchColumn() ?: null;
        } else {
            $return['payment_method_name'] = null;
        }

        // اسم العميل
        $return['customer_name'] = null;
        if (!empty($return['customer_id'])) {
            $s = $this->db->prepare(
                "SELECT name FROM customers WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)
                 ORDER BY (tenant_id IS NULL) ASC LIMIT 1"
            );
            $s->execute([$return['customer_id'], $this->tenantId]);
            $return['customer_name'] = $s->fetchColumn() ?: null;
        }

        // اسم المورد
        $return['supplier_name'] = null;
        if (!empty($return['supplier_id'])) {
            $s = $this->db->prepare(
                "SELECT name FROM suppliers WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)
                 ORDER BY (tenant_id IS NULL) ASC LIMIT 1"
            );
            $s->execute([$return['supplier_id'], $this->tenantId]);
            $return['supplier_name'] = $s->fetchColumn() ?: null;
        }

        // اسم المنشئ
        $return['created_by_name'] = null;
        if (!empty($return['created_by'])) {
            $s = $this->db->prepare(
                "SELECT name FROM users WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)
                 ORDER BY (tenant_id IS NULL) ASC LIMIT 1"
            );
            $s->execute([$return['created_by'], $this->tenantId]);
            $return['created_by_name'] = $s->fetchColumn() ?: null;
        }

        // عناصر المرتجع
        $s = $this->db->prepare(
            "SELECT ri.*, p.name AS product_name, p.product_code
             FROM return_items ri
             JOIN products p ON ri.product_id = p.id AND p.tenant_id = ri.tenant_id
             WHERE ri.return_id = ? AND ri.tenant_id = ?"
        );
        $s->execute([$returnId, $this->tenantId]);
        $return['items'] = $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // المدفوعات (cash_transactions)
        $s = $this->db->prepare(
            "SELECT ct.id, ct.amount, ct.type, ct.payment_method_id,
                    pm.name AS payment_method_name,
                    ct.description, ct.created_at, ct.status, ct.journal_entry_id
             FROM cash_transactions ct
             LEFT JOIN payment_methods pm
               ON pm.id = ct.payment_method_id
              AND (pm.tenant_id = ct.tenant_id OR pm.tenant_id IS NULL)
             WHERE ct.tenant_id = ? AND ct.return_id = ?
               AND ct.type = 'return_payment'
               AND (ct.status IS NULL OR ct.status IN ('completed', 'approved'))
             ORDER BY ct.created_at ASC"
        );
        $s->execute([$this->tenantId, $returnId]);
        $payments = $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($payments as &$pFmt) {
            if (isset($pFmt['amount'])) {
                $pFmt['amount'] = number_format((float) $pFmt['amount'], 2, '.', '');
            }
        }
        unset($pFmt);

        $paymentsTotal = 0.0;
        foreach ($payments as $p) {
            $paymentsTotal += (float) ($p['amount'] ?? 0);
        }

        $return['payments']       = $payments;
        $return['payments_total'] = number_format($paymentsTotal, 2, '.', '');

        $grandTotal = isset($return['grand_total'])
            ? (float) $return['grand_total']
            : ((float) ($return['total_amount'] ?? 0) + (float) ($return['tax_amount'] ?? 0));

        $return['net_due'] = number_format($grandTotal - $paymentsTotal, 2, '.', '');

        foreach (['total_amount', 'tax_amount', 'discount_amount', 'grand_total', 'paid_amount'] as $key) {
            if (isset($return[$key])) {
                $return[$key] = number_format((float) $return[$key], 2, '.', '');
            }
        }

        // القيد المحاسبي
        $this->attachJournalEntry($return, $returnId);

        // labels للمدفوعات
        if (!empty($return['payments'])) {
            foreach ($return['payments'] as &$payment) {
                $payment['reference_type']  = 'return_payment';
                $payment['reference_id']    = $payment['id'] ?? null;
                $payment['reference']       = 'return_payment#' . ($payment['id'] ?? '');
                $payment['reference_label'] = LabelService::refLabel('return_payment', $locale);
                $payment['status_label']    = LabelService::statusLabel($payment['status'] ?? 'completed', $locale);
                $payment['journal_entry']               = null;
                $payment['journal_reference_type']      = null;
                $payment['journal_reference_id']        = null;

                if (!empty($payment['journal_entry_id'])) {
                    $pJe = $this->fetchJournalEntryWithLines((int) $payment['journal_entry_id']);
                    if ($pJe) {
                        $payment['journal_entry']          = $pJe;
                        $payment['journal_reference_type'] = $pJe['reference_type'] ?? null;
                        $payment['journal_reference_id']   = $pJe['reference_id']   ?? null;
                    }
                }
            }
            unset($payment);
        }

        return $return;
    }

    /**
     * جلب قيد محاسبي مع سطوره — helper داخلي.
     */
    private function fetchJournalEntryWithLines(int $jeId): ?array
    {
        $s = $this->db->prepare(
            "SELECT je.* FROM journal_entries je WHERE je.id = ? AND je.tenant_id = ?"
        );
        $s->execute([$jeId, $this->tenantId]);
        $je = $s->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$je) return null;

        $s = $this->db->prepare(
            "SELECT jel.*, a.name AS account_name, a.code AS account_code
             FROM journal_entry_lines jel
             JOIN accounts a ON a.id = jel.account_id
             WHERE jel.journal_entry_id = ?"
        );
        $s->execute([$je['id']]);
        $je['lines'] = $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        return $je;
    }

    /**
     * يربط القيد المحاسبي بمصفوفة المرتجع — يُصلح reference_type إذا لزم.
     */
    private function attachJournalEntry(array &$return, int $returnId): void
    {
        if (!empty($return['journal_entry_id'])) {
            $je = $this->fetchJournalEntryWithLines((int) $return['journal_entry_id']);
            if ($je) {
                $je['date'] = $je['entry_date'] ?? $return['return_date'] ?? null;
            }
            $return['journal_entry']          = $je;
            $return['journal_reference_type'] = $je['reference_type'] ?? null;
            $return['journal_reference_id']   = $je['reference_id']   ?? null;
            return;
        }

        // بحث عن قيد مرتبط عبر reference_type الصحيح أو 'return'
        $desiredRefType = match ($return['return_type'] ?? '') {
            'sale'     => 'sale_return',
            'purchase' => 'purchase_return',
            default    => null,
        };

        $jeRef = null;
        if ($desiredRefType) {
            $s = $this->db->prepare(
                "SELECT je.* FROM journal_entries je
                 WHERE je.tenant_id = ? AND je.reference_type = ? AND je.reference_id = ?
                 ORDER BY je.id DESC LIMIT 1"
            );
            $s->execute([$this->tenantId, $desiredRefType, $returnId]);
            $jeRef = $s->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        if (!$jeRef) {
            $s = $this->db->prepare(
                "SELECT je.* FROM journal_entries je
                 WHERE je.tenant_id = ? AND je.reference_type = 'return' AND je.reference_id = ?
                 ORDER BY je.id DESC LIMIT 1"
            );
            $s->execute([$this->tenantId, $returnId]);
            $jeRef = $s->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        if ($jeRef) {
            $s = $this->db->prepare(
                "SELECT jel.*, a.name AS account_name, a.code AS account_code
                 FROM journal_entry_lines jel
                 JOIN accounts a ON a.id = jel.account_id
                 WHERE jel.journal_entry_id = ?"
            );
            $s->execute([$jeRef['id']]);
            $jeRef['lines'] = $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $jeRef['date']  = $jeRef['entry_date'] ?? $return['return_date'] ?? null;

            $return['journal_entry']          = $jeRef;
            $return['journal_reference_type'] = $jeRef['reference_type'] ?? null;
            $return['journal_reference_id']   = $jeRef['reference_id']   ?? null;
            $return['journal_entry_id']       = $jeRef['id'];

            // إصلاح journal_entry_id على المرتجع
            try {
                $this->db->prepare(
                    "UPDATE returns SET journal_entry_id = ?
                     WHERE id = ? AND tenant_id = ?
                       AND (journal_entry_id IS NULL OR journal_entry_id = 0)"
                )->execute([$jeRef['id'], $returnId, $this->tenantId]);
            } catch (\Throwable $e) { }

            // إصلاح reference_type على القيد
            if ($desiredRefType && ($jeRef['reference_type'] ?? null) !== $desiredRefType) {
                try {
                    $this->db->prepare(
                        "UPDATE journal_entries SET reference_type = ? WHERE id = ? AND tenant_id = ?"
                    )->execute([$desiredRefType, $jeRef['id'], $this->tenantId]);
                    $return['journal_reference_type']              = $desiredRefType;
                    $return['journal_entry']['reference_type']     = $desiredRefType;
                } catch (\Throwable $e) { }
            }
        } else {
            $return['journal_entry']          = null;
            $return['journal_reference_type'] = null;
            $return['journal_reference_id']   = null;
        }
    }

    // =========================================================================
    // createReturn — full return creation flow extracted from ReturnsHandler
    // =========================================================================

    /**
     * إنشاء مرتجع كامل (مبيعات أو مشتريات).
     *
     * @param  array  $data       بيانات المرتجع (return_type, items, paid_amount, ...)
     * @param  int    $tenantId
     * @param  int    $userId
     * @param  int|null $sessionId  جلسة الكاشير (تُحسب في الـ Handler)
     * @return array  ['return_id' => int, 'return_number' => string]
     * @throws \Exception on validation or DB error
     */
    public function createReturn(array $data, int $tenantId, int $userId, ?int $sessionId = null): array
    {
        // ── حساب الإجماليات ───────────────────────────────────────────────────
        $grossTotal = 0.0;
        $taxTotal   = 0.0;
        foreach ($data['items'] as $item) {
            $grossTotal += (float) ($item['subtotal'] ?? 0);
            if (isset($item['tax_amount'])) {
                $taxTotal += (float) $item['tax_amount'];
            }
        }

        $totalAfterDiscount    = $grossTotal;
        $providedDiscountValue = isset($data['discount_value']) ? (float) $data['discount_value'] : null;
        $providedDiscountType  = $data['discount_type'] ?? 'fixed';
        $discountAmount        = 0.0;

        if ($providedDiscountValue !== null && $providedDiscountValue > 0) {
            $discountAmount = ($providedDiscountType === 'percentage')
                ? ($grossTotal * ($providedDiscountValue / 100))
                : $providedDiscountValue;
            $totalAfterDiscount = max(0, $grossTotal - $discountAmount);
        }

        $grandTotal = $totalAfterDiscount + $taxTotal;

        if ($data['paid_amount'] > $grandTotal) {
            throw new \Exception('paid_amount cannot exceed total amount');
        }

        // ── رقم المرتجع ───────────────────────────────────────────────────────
        $timestamp = strtotime((string) $data['return_date']);
        $datePart  = date('ymd', $timestamp);
        $prefix    = $data['return_type'] === 'sale' ? 'SR' : 'PR';

        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(return_number, '-', -1) AS UNSIGNED)), 0) + 1 AS next_number
            FROM returns
            WHERE return_type = ? AND return_number LIKE ? AND tenant_id = ?
        ");
        $stmt->execute([$data['return_type'], "{$prefix}-{$datePart}-%", $tenantId]);
        $returnNumber = sprintf('%s-%s-%03d', $prefix, $datePart, $stmt->fetchColumn());

        // ── بيانات الفاتورة الأصلية ───────────────────────────────────────────
        $saleId      = null;
        $purchaseId  = null;
        $invoiceNumber = null;
        $branchId    = null;
        $deductFromCustomerBalance = 0.0;
        $refundMode  = $data['refund_mode'] ?? 'auto';
        $saleOutstanding     = null;
        $purchaseOutstanding = null;

        if ($data['return_type'] === 'sale') {
            $stmt = $this->db->prepare("
                SELECT id, invoice_number, branch_id,
                       total_amount AS original_total_amount,
                       discount_type AS original_discount_type,
                       discount_value AS original_discount_value,
                       net_total_amount, paid_amount, tax_rate, tax_amount
                FROM sales WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$data['invoice_id'], $tenantId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sale) {
                $saleId        = $sale['id'];
                $invoiceNumber = $sale['invoice_number'];
                $branchId      = $sale['branch_id'];

                // تطبيق خصم الفاتورة الأصلية إذا لم يُمرَّر خصم صريح
                if (($providedDiscountValue === null || $providedDiscountValue == 0) && !empty($sale['original_total_amount'])) {
                    $origTotal     = (float) $sale['original_total_amount'];
                    $origDiscType  = $sale['original_discount_type'] ?? 'fixed';
                    $origDiscValue = (float) ($sale['original_discount_value'] ?? 0);
                    $origDiscAmount = ($origDiscType === 'percentage')
                        ? ($origTotal * ($origDiscValue / 100))
                        : $origDiscValue;
                    $saleNetTotal = (float) ($sale['net_total_amount'] ?? $origTotal);
                    if ($origTotal > 0 && $origDiscAmount > 0 && abs($grossTotal - $saleNetTotal) > 0.01 && $grossTotal > $saleNetTotal) {
                        $proRatedDisc       = ($grossTotal / $origTotal) * $origDiscAmount;
                        $totalAfterDiscount = max(0, $grossTotal - $proRatedDisc);
                    }
                }

                $saleGrandTotal  = (float) $sale['net_total_amount'] + (float) ($sale['tax_amount'] ?? 0);
                $saleOutstanding = max(0, $saleGrandTotal - (float) $sale['paid_amount']);

                if ($saleOutstanding > 0) {
                    if ($refundMode === 'auto' || $refundMode === 'deduct_and_return') {
                        $amountToDeductFromDebt    = min($saleOutstanding, $grandTotal);
                        $excessToReturn            = max(0, $grandTotal - $amountToDeductFromDebt);
                        $data['paid_amount']        = $excessToReturn;
                        $deductFromCustomerBalance  = $amountToDeductFromDebt;
                    } elseif ($refundMode === 'cash') {
                        throw new \Exception('لا يمكن رد نقدي لفاتورة آجلة أو غير مسددة بالكامل. سيتم خصم قيمة المرتجع من ذمة العميل.');
                    } else {
                        $data['paid_amount']       = 0;
                        $deductFromCustomerBalance = min($saleOutstanding, $grandTotal);
                    }
                } else {
                    if ($refundMode === 'credit_note' || $refundMode === 'deduct_and_return') {
                        $data['paid_amount'] = 0;
                    }
                }

                // إعادة حساب الضريبة إذا لم تُمرَّر
                if ($taxTotal <= 0) {
                    $effectiveTaxRate = isset($data['tax_rate']) ? (float) $data['tax_rate']
                        : (isset($sale['tax_rate']) ? (float) $sale['tax_rate'] : null);
                    if ($effectiveTaxRate && $effectiveTaxRate > 0) {
                        $taxTotal = round($totalAfterDiscount * ($effectiveTaxRate / 100), 2);
                    }
                }
                $grandTotal = $totalAfterDiscount + $taxTotal;

                if ($saleOutstanding === 0) {
                    // حساب outstanding عبر كل فواتير العميل
                    $customerTotalOutstanding = $this->getCustomerTotalOutstanding((int) $data['party_id'], $tenantId);
                    if (in_array(strtolower((string) $refundMode), ['deduct_and_return', 'auto'], true)) {
                        $deductFromCustomerBalance = min($customerTotalOutstanding, $grandTotal);
                        $data['paid_amount']        = round(max(0, $grandTotal - $deductFromCustomerBalance), 2);
                    } elseif ($refundMode === 'cash') {
                        $data['paid_amount'] = $grandTotal;
                    } elseif ($refundMode === 'credit_note') {
                        $data['paid_amount'] = 0;
                    }
                }

                if ($data['paid_amount'] > $grandTotal) {
                    $data['paid_amount'] = $grandTotal;
                }
            }
        } else {
            // مرتجع مشتريات
            $stmt = $this->db->prepare("
                SELECT id, invoice_number, branch_id,
                       (total_amount - discount_value + tax_amount) AS net_total_amount,
                       paid_amount, tax_rate
                FROM purchases WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$data['invoice_id'], $tenantId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($purchase) {
                $purchaseId          = $purchase['id'];
                $invoiceNumber       = $purchase['invoice_number'];
                $branchId            = $purchase['branch_id'];
                $purchaseOutstanding = max(0, (float) $purchase['net_total_amount'] - (float) $purchase['paid_amount']);

                if ($purchaseOutstanding > 0) {
                    if (($data['refund_mode'] ?? null) === 'cash') {
                        throw new \Exception('لا يمكن استلام نقدي لمرتجع على فاتورة مشتريات آجلة أو غير مسددة بالكامل. سيتم خصم قيمة المرتجع من ذمة المورد.');
                    }
                    $data['paid_amount'] = 0;
                } else {
                    $refundModePurchase = $data['refund_mode'] ?? 'auto';
                    if ($refundModePurchase === 'credit_note') {
                        $data['paid_amount'] = 0;
                    } elseif ($refundModePurchase === 'deduct_and_return') {
                        $data['paid_amount'] = $purchaseOutstanding > 0
                            ? max(0, $grandTotal - min($purchaseOutstanding, $grandTotal))
                            : $grandTotal;
                    }
                }

                if ($taxTotal <= 0) {
                    $effectiveTaxRate = isset($data['tax_rate']) ? (float) $data['tax_rate']
                        : (isset($purchase['tax_rate']) ? (float) $purchase['tax_rate'] : 0);
                    if ($effectiveTaxRate > 0) {
                        $taxTotal = round($totalAfterDiscount * ($effectiveTaxRate / 100), 2);
                    }
                }
                $grandTotal = $totalAfterDiscount + $taxTotal;

                if (in_array(($data['refund_mode'] ?? 'auto'), ['auto', 'cash'], true) && $purchaseOutstanding === 0) {
                    $data['paid_amount'] = $grandTotal;
                }
                if ($data['paid_amount'] > $grandTotal) {
                    $data['paid_amount'] = $grandTotal;
                }
            }
        }

        // ── التحقق من الكميات ─────────────────────────────────────────────────
        if ($saleId || $purchaseId) {
            foreach ($data['items'] as $index => $item) {
                $productId    = (int) $item['product_id'];
                $requestedQty = (float) $item['quantity'];

                $stmtProd = $this->db->prepare("SELECT name FROM products WHERE id = ? AND tenant_id = ?");
                $stmtProd->execute([$productId, $tenantId]);
                $productName = $stmtProd->fetchColumn() ?: '#' . $productId;

                if ($data['return_type'] === 'sale' && $saleId) {
                    $stmtOrig = $this->db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM sales_items WHERE sale_id = ? AND product_id = ? AND tenant_id = ?");
                    $stmtOrig->execute([$saleId, $productId, $tenantId]);
                    $originalQty = (float) $stmtOrig->fetchColumn();
                    if ($originalQty <= 0) throw new \Exception("الصنف {$productName} غير موجود في الفاتورة الأصلية، لا يمكن إرجاعه");

                    $stmtPrev = $this->db->prepare("SELECT COALESCE(SUM(ri.quantity), 0) FROM return_items ri JOIN returns r ON r.id = ri.return_id WHERE r.tenant_id = ? AND r.return_type = 'sale' AND r.sale_id = ? AND ri.product_id = ?");
                    $stmtPrev->execute([$tenantId, $saleId, $productId]);
                    $remainingQty = max(0.0, $originalQty - (float) $stmtPrev->fetchColumn());

                    if (bccomp((string) $remainingQty, '0', 2) <= 0) throw new \Exception("تم إرجاع كامل الكمية للصنف {$productName} سابقاً، لا يمكن إرجاع كمية إضافية");
                    if ($requestedQty > $remainingQty) throw new \Exception("لا يمكن إرجاع كمية أكبر من الكمية المباعة للصنف {$productName}. الكمية المسموح بها حالياً: {$remainingQty}");
                } elseif ($data['return_type'] === 'purchase' && $purchaseId) {
                    $stmtOrig = $this->db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM purchase_items WHERE purchase_id = ? AND product_id = ? AND tenant_id = ?");
                    $stmtOrig->execute([$purchaseId, $productId, $tenantId]);
                    $originalQty = (float) $stmtOrig->fetchColumn();
                    if ($originalQty <= 0) throw new \Exception("الصنف {$productName} غير موجود في فاتورة المشتريات الأصلية، لا يمكن إرجاعه");

                    $stmtPrev = $this->db->prepare("SELECT COALESCE(SUM(ri.quantity), 0) FROM return_items ri JOIN returns r ON r.id = ri.return_id WHERE r.tenant_id = ? AND r.return_type = 'purchase' AND r.purchase_id = ? AND ri.product_id = ?");
                    $stmtPrev->execute([$tenantId, $purchaseId, $productId]);
                    $remainingQty = max(0.0, $originalQty - (float) $stmtPrev->fetchColumn());

                    if (bccomp((string) $remainingQty, '0', 2) <= 0) throw new \Exception("تم إرجاع كامل الكمية للصنف {$productName} سابقاً، لا يمكن إرجاع كمية إضافية");
                    if ($requestedQty > $remainingQty) throw new \Exception("لا يمكن إرجاع كمية أكبر من الكمية المشتراة للصنف {$productName}. الكمية المسموح بها حالياً: {$remainingQty}");
                }
            }
        }

        // ── INSERT returns ────────────────────────────────────────────────────
        $methodIsCash = $this->isCashMethodInternal((int) $data['payment_method_id'], $tenantId);
        $isCash       = $methodIsCash && $data['paid_amount'] > 0;

        $costCenterId = $data['cost_center_id'] ?? null;

        $sql = "INSERT INTO returns (
                    tenant_id, return_type, customer_id, supplier_id, return_number, return_date,
                    total_amount, tax_amount, discount_amount, paid_amount, notes,
                    status, payment_method_id, created_by, cost_center_id, created_at,
                    invoice_number, sale_id, purchase_id, branch_id, is_cash
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, NOW(), ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $maxAttempts = 5;
        $attempt     = 0;

        while (true) {
            try {
                $attempt++;
                $stmt->execute([
                    $tenantId,
                    $data['return_type'],
                    $data['return_type'] === 'sale'     ? $data['party_id'] : null,
                    $data['return_type'] === 'purchase' ? $data['party_id'] : null,
                    $returnNumber,
                    $data['return_date'],
                    $totalAfterDiscount,
                    $taxTotal,
                    $discountAmount,
                    $data['paid_amount'],
                    $data['notes'] ?? null,
                    $data['payment_method_id'],
                    $userId,
                    $costCenterId,
                    $invoiceNumber,
                    $saleId,
                    $purchaseId,
                    $branchId,
                    $isCash,
                ]);
                break;
            } catch (PDOException $e) {
                if ($attempt >= $maxAttempts) throw $e;
                $returnNumber  = $returnNumber  . '-' . date('His') . '-' . mt_rand(100, 999);
                $invoiceNumber = ($invoiceNumber ?? '') . '-' . date('His') . '-' . mt_rand(100, 999);
            }
        }

        $returnId = (int) $this->db->lastInsertId();

        // ── INSERT return_items + stock ───────────────────────────────────────
        $stmtItems = $this->db->prepare("
            INSERT INTO return_items (
                return_id, product_id, unit_id, quantity,
                unit_price, tax_rate, tax_amount, discount, discount_amount, subtotal,
                batch_number, expiry_date, serial, created_at, tenant_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");

        foreach ($data['items'] as $item) {
            $stmtItems->execute([
                $returnId,
                $item['product_id'],
                $item['unit_id'],
                $item['quantity'],
                $item['unit_price'],
                isset($item['tax_rate'])       ? (float) $item['tax_rate']       : 0,
                isset($item['tax_amount'])     ? (float) $item['tax_amount']     : 0,
                isset($item['discount'])       ? (float) $item['discount']       : 0,
                isset($item['discount_amount'])? (float) $item['discount_amount']: 0,
                $item['subtotal'],
                $item['batch_number'] ?? null,
                $item['expiry_date']  ?? null,
                $item['serial']       ?? null,
                $tenantId,
            ]);

            $this->updateStock(
                (int) $item['product_id'],
                (int) $item['unit_id'],
                (float) $item['quantity'],
                (int) $branchId,
                $returnId,
                $data['return_type'],
                $item['batch_number'] ?? null,
                $item['expiry_date']  ?? null,
                $item['serial']       ?? null
            );
        }

        // ── القيد المحاسبي ────────────────────────────────────────────────────
        $originalOutstanding = $data['return_type'] === 'sale' ? $saleOutstanding : $purchaseOutstanding;

        $journalEntryId = $this->accounting->postReturnJournalEntry(
            $returnId,
            $tenantId,
            (string) $data['return_date'],
            $returnNumber,
            $data['return_type'],
            $data['party_id'] ?? null,
            $totalAfterDiscount,
            (float) $data['paid_amount'],
            (int) $data['payment_method_id'],
            $taxTotal,
            $userId,
            (string) ($refundMode ?? 'auto'),
            (float) ($deductFromCustomerBalance ?? 0),
            $originalOutstanding,
            $costCenterId
        );

        $this->db->prepare("UPDATE returns SET journal_entry_id = ? WHERE id = ? AND tenant_id = ?")
            ->execute([$journalEntryId, $returnId, $tenantId]);

        // ── INSERT payments ───────────────────────────────────────────────────
        $paymentType  = $data['return_type'] === 'sale' ? 'return_payment' : 'payment_for_return';
        $paymentNotes = $data['notes'] ?? ('مرتجع ' . ($data['return_type'] === 'sale' ? 'مبيعات' : 'مشتريات') . ' #' . $returnNumber);
        $currency     = $this->settingsRepo->get($tenantId, 'company.currency', 'EGP') ?: 'EGP';

        $this->db->prepare("
            INSERT INTO payments (
                tenant_id, payment_date, return_id, amount,
                payment_method_id, notes, created_by, created_at, type,
                sale_id, purchase_id, customer_id, supplier_id, session_id, cost_center_id, journal_entry_id, currency
            ) VALUES (?, NOW(), ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $tenantId,
            $returnId,
            $data['paid_amount'],
            $data['payment_method_id'],
            $paymentNotes,
            $userId,
            $paymentType,
            $saleId,
            $purchaseId,
            $data['return_type'] === 'sale'     ? ($data['party_id'] ?? null) : null,
            $data['return_type'] === 'purchase' ? ($data['party_id'] ?? null) : null,
            $sessionId,
            $costCenterId,
            $journalEntryId,
            $currency,
        ]);

        $paymentId = (int) $this->db->lastInsertId();

        // ── cash_transactions ─────────────────────────────────────────────────
        if ($isCash && $data['paid_amount'] > 0) {
            $cashTransactionType = $data['return_type'] === 'sale' ? 'return_payment' : 'return_receipt';
            $customerId  = $data['return_type'] === 'sale'     ? ($data['party_id'] ?? null) : null;
            $supplierId  = $data['return_type'] === 'purchase' ? ($data['party_id'] ?? null) : null;

            $this->db->prepare("
                INSERT INTO cash_transactions (
                    tenant_id, type, amount, payment_method_id, notes, created_by,
                    created_at, return_id, reference_type, reference_id,
                    customer_id, supplier_id, session_id, cost_center_id, journal_entry_id, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $tenantId,
                $cashTransactionType,
                $data['paid_amount'],
                $data['payment_method_id'],
                $data['notes'] ?? ('مرتجع ' . ($data['return_type'] === 'sale' ? 'مبيعات' : 'مشتريات') . ' #' . $returnNumber),
                $userId,
                $returnId,
                'return',
                $returnId,
                $customerId,
                $supplierId,
                $sessionId,
                $costCenterId,
                $journalEntryId,
                $branchId,
            ]);
        }

        // ── توزيع رصيد العميل ─────────────────────────────────────────────────
        if ($data['return_type'] === 'sale' && !empty($data['party_id']) && $deductFromCustomerBalance > 0) {
            $this->allocateCustomerBalance(
                (int) $data['party_id'],
                (float) $deductFromCustomerBalance,
                $saleId ? (int) $saleId : null,
                $paymentId,
                $userId
            );
        }

        return [
            'return_id'     => $returnId,
            'return_number' => $returnNumber,
            'journal_entry_id' => $journalEntryId,
            'payment_id'    => $paymentId,
        ];
    }

    // ── Private helpers for createReturn ─────────────────────────────────────

    private function isCashMethodInternal(int $paymentMethodId, int $tenantId): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT kind FROM payment_methods WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$paymentMethodId, $tenantId]);
            return strtolower((string) ($stmt->fetchColumn() ?: '')) === 'cash';
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getCustomerTotalOutstanding(int $customerId, int $tenantId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM((net_total_amount + IFNULL(tax_amount,0)) - IFNULL(paid_amount,0)), 0)
            FROM sales
            WHERE customer_id = ? AND tenant_id = ? AND status = 'active' AND is_draft = 0
              AND ((net_total_amount + IFNULL(tax_amount,0)) - IFNULL(paid_amount,0)) > 0
        ");
        $stmt->execute([$customerId, $tenantId]);
        return (float) $stmt->fetchColumn();
    }
}
