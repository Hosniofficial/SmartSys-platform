<?php
/**
 * Sales & Returns Verification Script
 * Tests: Cash Sales, Credit Sales, Sales Returns, and their journal entries
 *
 * Fixed issues:
 *  - je.is_posted         → je.status (enum: draft/posted/cancelled)
 *  - si.unit_cost         → si.purchase_price
 *  - si.total_price       → si.total
 *  - jel.debit/credit     → jel.debit_amount / jel.credit_amount
 *  - sales_returns        → returns (WHERE return_type = 'sale')
 *  - sales_return_items   → return_items
 *  - sri.sales_return_id  → sri.return_id
 *  - je reference_type    → 'return' (not 'sales_return')
 *  - $sale['total']       → stored from fetched sale row
 *  - Payment type         → based on payment_methods.kind not amount compare
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/database.php';

$db       = (new Database())->pdo;
$tenantId = 39;

// ── helpers ─────────────────────────────────────────────────────────────────

function section(string $title): void
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 60) . "\n";
}

function printRows(array $rows): void
{
    if (empty($rows)) {
        echo "  ⚠️  لا توجد نتائج\n";
        return;
    }

    $widths = [];
    foreach ($rows as $row) {
        foreach ($row as $col => $val) {
            $widths[$col] = max(
                $widths[$col] ?? 0,
                strlen((string) $col),
                min(strlen((string) $val), 40)
            );
        }
    }

    $border = fn() => "  +" . implode('+', array_map(fn($w) => str_repeat('-', $w + 2), $widths)) . "+\n";

    echo $border();
    echo "  |";
    foreach ($widths as $col => $w) {
        echo ' ' . str_pad($col, $w) . ' |';
    }
    echo "\n" . $border();

    foreach ($rows as $row) {
        echo "  |";
        foreach ($widths as $col => $w) {
            $val = (string) ($row[$col] ?? '');
            if (strlen($val) > 40) $val = substr($val, 0, 37) . '...';
            echo ' ' . str_pad($val, $w) . ' |';
        }
        echo "\n";
    }
    echo $border();
}

function checkBalance(array $lines): void
{
    // FIX: correct column names are debit_amount / credit_amount
    $debit  = array_sum(array_column($lines, 'debit_amount'));
    $credit = array_sum(array_column($lines, 'credit_amount'));
    $diff   = abs($debit - $credit);
    $status = $diff < 0.01 ? '✅ متوازن' : '❌ غير متوازن';
    echo sprintf(
        "  التوازن: مدين=%-12s | دائن=%-12s | الفرق=%-10s → %s\n",
        number_format($debit,  2),
        number_format($credit, 2),
        number_format($diff,   2),
        $status
    );
}

// ── resolve sale ID ──────────────────────────────────────────────────────────

$saleId = $_GET['sale_id'] ?? null;

if (!$saleId) {
    $latestSale = $db->query(
        "SELECT id, invoice_number, created_at
         FROM sales
         WHERE tenant_id = $tenantId
         ORDER BY id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if ($latestSale) {
        $saleId = $latestSale['id'];
        echo "=== آخر فاتورة: ID={$saleId} | رقم: {$latestSale['invoice_number']} | {$latestSale['created_at']} ===\n";
    } else {
        echo "⚠️ لا توجد فواتير بيع للمستأجر $tenantId\n";
        exit;
    }
} else {
    echo "=== فحص فاتورة البيع: ID=$saleId ===\n";
}

// ── 1. Sale header ───────────────────────────────────────────────────────────

section('1. بيانات الفاتورة (sales)');
$stmt = $db->prepare("
    SELECT s.id, s.invoice_number, s.sale_date, s.status,
           s.total_amount, s.net_total_amount, s.paid_amount,
           s.discount_type, s.discount_value,
           s.tax_rate, s.tax_amount,
           s.payment_method_id,         -- FIX: needed for invoice type detection
           b.name  AS branch_name,
           u.name  AS user_name,
           c.name  AS customer_name
    FROM   sales s
    LEFT JOIN branches  b ON b.id = s.branch_id  AND b.tenant_id = s.tenant_id
    LEFT JOIN users     u ON u.id = s.user_id    AND u.tenant_id = s.tenant_id
    LEFT JOIN customers c ON c.id = s.customer_id AND c.tenant_id = s.tenant_id
    WHERE s.tenant_id = ? AND s.id = ?
");
$stmt->execute([$tenantId, $saleId]);
$saleRow = $stmt->fetch(PDO::FETCH_ASSOC);   // FIX: store $saleRow so we can reference it later

if (!$saleRow) {
    echo "⚠️ الفاتورة ID=$saleId غير موجودة للمستأجر $tenantId\n";
    exit;
}
printRows([$saleRow]);

// ── 2. Sale items ────────────────────────────────────────────────────────────

section('2. أصناف الفاتورة (sales_items)');
$stmt = $db->prepare("
    SELECT si.id, si.product_id, p.name AS product_name,
           si.quantity, si.conversion_factor, si.unit_id,
           si.sale_price,
           si.purchase_price,                  -- FIX: was si.unit_cost (doesn't exist)
           si.discount_type, si.discount_value,
           si.net_price,
           si.total,                           -- FIX: was si.total_price (doesn't exist)
           si.net_total,
           si.profit
    FROM   sales_items si
    LEFT JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id
    WHERE  si.tenant_id = ? AND si.sale_id = ?
");
$stmt->execute([$tenantId, $saleId]);
$saleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
printRows($saleItems);

// ── 3. Payments ──────────────────────────────────────────────────────────────

section('3. المدفوعات (payments)');
$stmt = $db->prepare("
    SELECT p.id, p.amount, p.payment_date, p.status, p.is_draft,
           pm.name AS payment_method_name,
           pm.kind AS payment_method_kind    -- FIX: use kind to determine cash vs credit
    FROM   payments p
    LEFT JOIN payment_methods pm
           ON pm.id = p.payment_method_id
          AND (pm.tenant_id = p.tenant_id OR pm.tenant_id IS NULL)
    WHERE  p.tenant_id = ? AND p.sale_id = ? AND p.is_draft = 0
    ORDER BY p.id
");
$stmt->execute([$tenantId, $saleId]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
printRows($payments);

// FIX: determine payment type by kind, not amount comparison
$invoiceType = 'UNKNOWN';
if (!empty($payments)) {
    $kinds = array_unique(array_column($payments, 'payment_method_kind'));
    if (count($kinds) === 1 && $kinds[0] === 'cash') {
        $invoiceType = 'CASH';
    } elseif (in_array('credit', $kinds)) {
        $invoiceType = 'CREDIT';
    } else {
        $invoiceType = 'MIXED';
    }
} elseif (!empty($saleRow['payment_method_id'])) {
    // No payments yet — check the payment method assigned to the invoice
    // FIX: tenant_id can be NULL for default payment methods
    $pmStmt = $db->prepare("
        SELECT kind FROM payment_methods 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) 
        LIMIT 1
    ");
    $pmStmt->execute([$saleRow['payment_method_id'], $tenantId]);
    $pmKind = $pmStmt->fetchColumn();
    if ($pmKind === 'cash') {
        $invoiceType = 'CASH';
    } elseif ($pmKind === 'credit') {
        $invoiceType = 'CREDIT';
    }
}

$totalPaid = array_sum(array_column($payments, 'amount'));
echo "  نوع الفاتورة: $invoiceType | إجمالي المدفوع: " . number_format($totalPaid, 2) . " | إجمالي الفاتورة: " . number_format($saleRow['total_amount'], 2) . "\n";

// ── 4. Inventory transactions (COGS) ─────────────────────────────────────────

section('4. حركات المخزون (inventory_transactions) — COGS');
$stmt = $db->prepare("
    SELECT it.id, it.product_id, p.name AS product_name,
           it.quantity, it.unit_cost, it.total_cost,
           it.movement_type, it.movement_date,
           it.branch_from, it.branch_to
    FROM   inventory_transactions it
    LEFT JOIN products p ON p.id = it.product_id AND p.tenant_id = it.tenant_id
    WHERE  it.tenant_id = ? AND it.reference_type = 'sale' AND it.reference_id = ?
    ORDER BY it.id
");
$stmt->execute([$tenantId, $saleId]);
printRows($stmt->fetchAll(PDO::FETCH_ASSOC));

// ── 5. Journal entries for the sale ──────────────────────────────────────────

section('5. القيود المحاسبية للفاتورة (journal_entries)');
$stmt = $db->prepare("
    SELECT je.id, je.reference_type, je.reference_id,
           je.entry_date, je.status,           -- FIX: was je.is_posted (doesn't exist)
           je.idempotency_key, je.description
    FROM   journal_entries je
    WHERE  je.tenant_id = ?
      AND  je.reference_type = 'sale'          -- FIX: returns have their own reference_type
      AND  je.reference_id   = ?
    ORDER BY je.id
");
$stmt->execute([$tenantId, $saleId]);
$journalEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
printRows($journalEntries);

// ── 6. Journal entry lines ───────────────────────────────────────────────────

foreach ($journalEntries as $je) {
    section("6. تفاصيل القيد #{$je['id']} (journal_entry_lines)");
    $stmt = $db->prepare("
        SELECT jel.id,
               jel.account_id,
               a.code        AS account_code,
               a.name        AS account_name,
               a.type        AS account_type,
               jel.debit_amount,              -- FIX: was jel.debit (doesn't exist)
               jel.credit_amount,             -- FIX: was jel.credit (doesn't exist)
               jel.description
        FROM   journal_entry_lines jel
        LEFT JOIN accounts a ON a.id = jel.account_id AND a.tenant_id = jel.tenant_id
        WHERE  jel.tenant_id = ? AND jel.journal_entry_id = ?
        ORDER BY jel.id
    ");
    $stmt->execute([$tenantId, $je['id']]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    printRows($lines);
    checkBalance($lines);   // FIX: extracted to helper using correct column names
}

// ── 7. COGS verification ─────────────────────────────────────────────────────

section('7. التحقق من COGS (costing)');
$stmt = $db->prepare("
    SELECT si.product_id,
           p.name                                        AS product_name,
           si.quantity,
           si.conversion_factor,
           si.purchase_price                             AS unit_cost,       -- FIX: correct column
           ROUND(si.quantity * si.conversion_factor
                 * si.purchase_price, 4)                 AS calculated_cogs,
           si.total                                      AS sale_revenue,    -- FIX: correct column
           ROUND(si.total - (si.quantity * si.conversion_factor
                 * si.purchase_price), 4)                AS gross_profit
    FROM   sales_items si
    LEFT JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id
    WHERE  si.tenant_id = ? AND si.sale_id = ?
");
$stmt->execute([$tenantId, $saleId]);
$cogsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
printRows($cogsRows);

$totalCogs    = array_sum(array_column($cogsRows, 'calculated_cogs'));
$totalRevenue = array_sum(array_column($cogsRows, 'sale_revenue'));
echo "  إجمالي التكلفة (COGS): " . number_format($totalCogs,    2) . "\n";
echo "  إجمالي الإيراد:         " . number_format($totalRevenue, 2) . "\n";
echo "  إجمالي الربح الإجمالي:  " . number_format($totalRevenue - $totalCogs, 2) . "\n";

// ── 8. GL impact summary ─────────────────────────────────────────────────────

section('8. ملخص التأثير المحاسبي (GL Impact)');
echo "  نوع الفاتورة: $invoiceType\n";
echo "  ───────────────────────────────────────────────────────\n";

if ($invoiceType === 'CASH') {
    echo "  📌 القيود المتوقعة للبيع النقدي:\n";
    echo "     مدين:  النقدية         ← الزبون دفع نقداً\n";
    echo "     دائن:  إيرادات المبيعات ← قيمة البيع\n";
    echo "     مدين:  تكلفة البضاعة   ← COGS\n";
    echo "     دائن:  المخزون         ← خصم من المخزون\n";
} elseif ($invoiceType === 'CREDIT') {
    echo "  📌 القيود المتوقعة للبيع الآجل:\n";
    echo "     مدين:  ذمم مدينة (الزبون) ← عليه دين\n";
    echo "     دائن:  إيرادات المبيعات   ← قيمة البيع\n";
    echo "     مدين:  تكلفة البضاعة      ← COGS\n";
    echo "     دائن:  المخزون            ← خصم من المخزون\n";
} else {
    echo "  📌 دفع مختلط (نقدي + آجل) — يحتاج مراجعة يدوية\n";
}

// ── 9. Related returns ────────────────────────────────────────────────────────

section('9. المرتجعات المرتبطة (returns)');

// FIX: table is `returns` with return_type='sale', NOT `sales_returns`
$stmt = $db->prepare("
    SELECT r.id, r.return_number, r.return_date, r.return_type,
           r.total_amount, r.paid_amount, r.status, r.is_cash,
           r.journal_entry_id,
           s.invoice_number AS original_invoice
    FROM   returns r
    LEFT JOIN sales s ON s.id = r.sale_id AND s.tenant_id = r.tenant_id
    WHERE  r.tenant_id = ? AND r.sale_id = ? AND r.return_type = 'sale'
    ORDER BY r.id
");
$stmt->execute([$tenantId, $saleId]);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
printRows($returns);

foreach ($returns as $ret) {
    section("9a. أصناف المرتجع #{$ret['id']}");

    // FIX: table is `return_items`, column is `return_id` (not sales_return_id)
    $stmt = $db->prepare("
        SELECT ri.id, ri.product_id, p.name AS product_name,
               ri.quantity, ri.unit_price, ri.tax_rate, ri.tax_amount,
               ri.discount, ri.discount_amount, ri.subtotal
        FROM   return_items ri
        LEFT JOIN products p ON p.id = ri.product_id AND p.tenant_id = ri.tenant_id
        WHERE  ri.tenant_id = ? AND ri.return_id = ?   -- FIX: correct column name
    ");
    $stmt->execute([$tenantId, $ret['id']]);
    printRows($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Return journal entry
    // FIX: reference_type is 'return', NOT 'sales_return'
    $stmt = $db->prepare("
        SELECT je.id, je.description, je.entry_date, je.status
        FROM   journal_entries je
        WHERE  je.tenant_id = ?
          AND  je.reference_type = 'return'
          AND  je.reference_id   = ?
    ");
    $stmt->execute([$tenantId, $ret['id']]);
    $retJes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($retJes)) {
        echo "  القيود المحاسبية للمرتجع #{$ret['id']}:\n";
        printRows($retJes);

        foreach ($retJes as $rje) {
            section("9b. تفاصيل قيد المرتجع #{$rje['id']}");
            $stmt = $db->prepare("
                SELECT jel.id,
                       jel.account_id,
                       a.code       AS account_code,
                       a.name       AS account_name,
                       jel.debit_amount,     -- FIX: correct column name
                       jel.credit_amount,    -- FIX: correct column name
                       jel.description
                FROM   journal_entry_lines jel
                LEFT JOIN accounts a ON a.id = jel.account_id AND a.tenant_id = jel.tenant_id
                WHERE  jel.tenant_id = ? AND jel.journal_entry_id = ?
                ORDER BY jel.id
            ");
            $stmt->execute([$tenantId, $rje['id']]);
            $retLines = $stmt->fetchAll(PDO::FETCH_ASSOC);
            printRows($retLines);
            checkBalance($retLines);

            echo "\n  📌 القيود المتوقعة للمرتجع:\n";
            if ($ret['is_cash']) {
                echo "     مدين:  إيرادات المبيعات ← عكس الإيراد\n";
                echo "     دائن:  النقدية         ← رد النقدية للزبون\n";
            } else {
                echo "     مدين:  إيرادات المبيعات   ← عكس الإيراد\n";
                echo "     دائن:  ذمم مدينة (الزبون) ← تخفيض الدين\n";
            }
            echo "     مدين:  المخزون         ← إعادة البضاعة\n";
            echo "     دائن:  تكلفة البضاعة   ← عكس COGS\n";
        }
    } else {
        echo "  ⚠️  لا يوجد قيد محاسبي للمرتجع #{$ret['id']}\n";
    }
}

// ── 10. Full GL summary per sale ─────────────────────────────────────────────

section('10. ملخص كامل للحركات المحاسبية (sale + returns)');
$stmt = $db->prepare("
    SELECT je.id           AS journal_id,
           je.reference_type,
           je.reference_id,
           je.entry_date,
           je.status,
           SUM(jel.debit_amount)  AS total_debit,
           SUM(jel.credit_amount) AS total_credit
    FROM   journal_entries je
    JOIN   journal_entry_lines jel ON jel.journal_entry_id = je.id
                                   AND jel.tenant_id = je.tenant_id
    WHERE  je.tenant_id = ?
      AND (
            (je.reference_type = 'sale'   AND je.reference_id = ?)
         OR (je.reference_type = 'return' AND je.reference_id IN (
                SELECT id FROM returns
                WHERE tenant_id = ? AND sale_id = ? AND return_type = 'sale'
             ))
      )
    GROUP BY je.id, je.reference_type, je.reference_id, je.entry_date, je.status
    ORDER BY je.id
");
$stmt->execute([$tenantId, $saleId, $tenantId, $saleId]);
printRows($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n" . str_repeat('=', 60) . "\n";
echo "  ✅ اختبار مكتمل\n";
echo str_repeat('=', 60) . "\n";