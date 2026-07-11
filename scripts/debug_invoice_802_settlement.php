<?php
require 'config/database.php';

$db = (new Database())->pdo;
$tenantId = 47;

echo "════════════════════════════════════════════════════════════════════\n";
echo "🔴 DEBUG: allocateCustomerBalance() Execution Trace\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

// Check Invoice #802 current state
echo "📋 CURRENT STATE - Invoice #802:\n";
echo "─────────────────────────────────────────────────────────────────\n";
$stmt = $db->prepare("
    SELECT id, status, paid_amount, net_total_amount, tax_amount,
           (net_total_amount + IFNULL(tax_amount, 0) - IFNULL(paid_amount, 0)) AS calculated_outstanding
    FROM sales
    WHERE id = 802 AND tenant_id = ?
");
$stmt->execute([$tenantId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if ($invoice) {
    echo "  status: " . $invoice['status'] . "\n";
    echo "  paid_amount: " . $invoice['paid_amount'] . "\n";
    echo "  net_total_amount: " . $invoice['net_total_amount'] . "\n";
    echo "  tax_amount: " . ($invoice['tax_amount'] ?? 0) . "\n";
    echo "  calculated_outstanding: " . $invoice['calculated_outstanding'] . "\n";
} else {
    echo "  ❌ Invoice not found!\n";
}

echo "\n";

// Check return allocation details
echo "📋 RETURN ALLOCATION - Return #348:\n";
echo "─────────────────────────────────────────────────────────────────\n";
$stmt = $db->prepare("
    SELECT id, grand_total, status, refund_amount
    FROM returns
    WHERE id = 348 AND tenant_id = ?
");
$stmt->execute([$tenantId]);
$ret = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ret) {
    echo "  grand_total: " . $ret['grand_total'] . "\n";
    echo "  status: " . $ret['status'] . "\n";
    echo "  refund_amount: " . ($ret['refund_amount'] ?? 0) . "\n";
} else {
    echo "  ❌ Return not found!\n";
}

echo "\n";

// Check payment_applications for invoice #802
echo "📋 PAYMENT APPLICATIONS - For Invoice #802:\n";
echo "─────────────────────────────────────────────────────────────────\n";
$stmt = $db->prepare("
    SELECT 
        pa.id,
        pa.payment_id,
        pa.reference_type,
        pa.reference_id,
        pa.tenant_id
    FROM payment_applications pa
    WHERE pa.reference_type = 'sale'
      AND pa.reference_id = 802
      AND pa.tenant_id = ?
");
$stmt->execute([$tenantId]);
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($apps)) {
    foreach ($apps as $app) {
        echo "  ID: " . $app['id'] . "\n";
        echo "    payment_id: " . ($app['payment_id'] ?? 'NULL') . "\n";
    }
} else {
    echo "  ❌ NO payment_applications found for invoice #802!\n";
}

echo "\n";

// Check if return was marked as "settled" during allocation
echo "📋 SETTLEMENT CHECK - Was Invoice #802 settled?\n";
echo "─────────────────────────────────────────────────────────────────\n";

// Calculate what allocateCustomerBalance SHOULD have done:
$invoiceOutstanding = 1000;
$amountToAllocate = 2000;
$apply = min($amountToAllocate, $invoiceOutstanding);  // Should be 1000
$newOutstanding = $invoiceOutstanding - $apply;  // Should be 0

echo "  Outstanding before: " . $invoiceOutstanding . "\n";
echo "  Amount to allocate: " . $amountToAllocate . "\n";
echo "  Apply amount: " . $apply . "\n";
echo "  New outstanding (calculated): " . $newOutstanding . "\n";
echo "  Settlement condition (newOutstanding <= 0.01): " . ($newOutstanding <= 0.01 ? "YES ✅" : "NO ❌") . "\n";

if ($newOutstanding <= 0.01) {
    echo "\n  ⚠️ Invoice SHOULD have been settled!\n";
    echo "  ⚠️ But status in DB shows: " . $invoice['status'] . "\n";
    echo "  ⚠️ Expected status should be: 'paid'\n";
    echo "  ⚠️ And paid_amount should be: " . ($invoice['net_total_amount'] + ($invoice['tax_amount'] ?? 0)) . "\n";
} else {
    echo "\n  OK: Settlement condition NOT met\n";
}

echo "\n";

// Check journal entries for clues
echo "📋 JOURNAL ENTRIES - For Return #348:\n";
echo "─────────────────────────────────────────────────────────────────\n";
$stmt = $db->prepare("
    SELECT 
        je.id,
        je.entry_date,
        je.description,
        je.reference_type,
        je.reference_id,
        COUNT(jel.id) AS line_count
    FROM journal_entries je
    LEFT JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id AND jel.tenant_id = je.tenant_id
    WHERE je.reference_type = 'sale_return'
      AND je.reference_id = 348
      AND je.tenant_id = ?
    GROUP BY je.id
");
$stmt->execute([$tenantId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($entries)) {
    foreach ($entries as $entry) {
        echo "  JE #" . $entry['id'] . " (" . $entry['entry_date'] . ")\n";
        echo "    Description: " . $entry['description'] . "\n";
        echo "    Lines: " . $entry['line_count'] . "\n";
    }
} else {
    echo "  ❌ No journal entries found for return!\n";
}

echo "\n";

// Final diagnostic
echo "🔍 DIAGNOSIS:\n";
echo "─────────────────────────────────────────────────────────────────\n";

if ($invoice['status'] === 'paid' && $invoice['calculated_outstanding'] == 1000) {
    echo "  🔴 CRITICAL: Status mismatch!\n";
    echo "  - Status is 'paid' but outstanding is still 1000\n";
    echo "  - This violates accounting principles\n";
    echo "  - The UPDATE statement in allocateCustomerBalance() likely didn't execute\n";
    echo "\n  Possible causes:\n";
    echo "    1. Settlement condition not triggered (check allocation logic)\n";
    echo "    2. UPDATE statement had syntax error (check logs)\n";
    echo "    3. Database transaction rolled back\n";
    echo "    4. Another process reverted the status\n";
} else if ($invoice['status'] !== 'paid' && $invoice['calculated_outstanding'] == 1000) {
    echo "  ✅ Status is consistent with outstanding\n";
} else {
    echo "  ⚠️ Unexpected state combination\n";
}

echo "\n════════════════════════════════════════════════════════════════════\n";
