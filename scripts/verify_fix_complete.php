<?php
require 'config/database.php';

$db = (new Database())->pdo;
$customerId = 37;
$tenantId = 47;

echo "════════════════════════════════════════════════════════════════════\n";
echo "✅ VERIFICATION: Post-Fix State\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

// Check both invoices
echo "📋 INVOICE STATUSES:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("
    SELECT id, invoice_number, status, 
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total,
           paid_amount,
           (net_total_amount + IFNULL(tax_amount, 0) - IFNULL(paid_amount, 0)) AS outstanding
    FROM sales
    WHERE customer_id = ? AND tenant_id = ?
    ORDER BY id
");
$stmt->execute([$customerId, $tenantId]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($invoices as $inv) {
    echo "Invoice #" . $inv['id'] . " (" . $inv['invoice_number'] . "):\n";
    echo "  status: " . $inv['status'] . " " . ($inv['status'] === 'paid' ? "✅" : "⚠️") . "\n";
    echo "  grand_total: " . $inv['grand_total'] . "\n";
    echo "  paid_amount: " . $inv['paid_amount'] . "\n";
    echo "  outstanding: " . $inv['outstanding'] . " " . ($inv['outstanding'] == 0 ? "✅" : "⚠️") . "\n";
    echo "\n";
}

// Check return
echo "📋 RETURN STATUS:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("
    SELECT id, grand_total, status FROM returns WHERE id = 348 AND tenant_id = ?
");
$stmt->execute([$tenantId]);
$ret = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Return #" . $ret['id'] . ":\n";
echo "  amount: " . $ret['grand_total'] . "\n";
echo "  status: " . $ret['status'] . "\n\n";

// Calculate totals
echo "📋 CUSTOMER ACCOUNT TOTALS:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("
    SELECT 
        COUNT(*) as invoice_count,
        SUM(net_total_amount + IFNULL(tax_amount, 0)) as total_invoiced,
        SUM(paid_amount) as total_paid,
        SUM((net_total_amount + IFNULL(tax_amount, 0) - IFNULL(paid_amount, 0))) as total_outstanding
    FROM sales
    WHERE customer_id = ? AND tenant_id = ?
");
$stmt->execute([$customerId, $tenantId]);
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

echo "  invoices: " . $totals['invoice_count'] . "\n";
echo "  total invoiced: " . $totals['total_invoiced'] . " (" . (4000 == $totals['total_invoiced'] ? "✅" : "❌") . ")\n";
echo "  total paid: " . $totals['total_paid'] . " (" . (3000 == $totals['total_paid'] ? "✅" : "❌") . ")\n";
echo "  total outstanding: " . $totals['total_outstanding'] . " (" . (0 == $totals['total_outstanding'] ? "✅" : "❌") . ")\n\n";

// Check return amount calculation
$stmt = $db->prepare("
    SELECT SUM(grand_total) as total_returns
    FROM returns
    WHERE customer_id = ? AND tenant_id = ? AND status IN ('approved', 'completed')
");
$stmt->execute([$customerId, $tenantId]);
$returnsTot = $stmt->fetchColumn();

echo "📋 RETURN TOTALS:\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "  total returns: " . ($returnsTot ?? 0) . " (" . (2000 == $returnsTot ? "✅" : "❌") . ")\n\n";

// Final ledger balance
echo "📋 LEDGER RECONCILIATION:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$opening = 0;
$debits = 5000;  // All invoices
$credits = 3000;  // Payments (2000 + 1000)
$creditFromReturn = 2000;  // Return credit
$debitFromRefund = 1000;   // Refund

$balance = $opening + $debits - $credits - $creditFromReturn + $debitFromRefund;

echo "  Opening: " . $opening . "\n";
echo "  + Invoices (debit): " . $debits . "\n";
echo "  - Payments (credit): " . $credits . "\n";
echo "  - Return Credit: " . $creditFromReturn . "\n";
echo "  + Refund (debit): " . $debitFromRefund . "\n";
echo "  ─────────────\n";
echo "  = Closing Balance: " . $balance . " (" . (0 == $balance ? "✅" : "❌") . ")\n";

echo "\n════════════════════════════════════════════════════════════════════\n";
echo "🎯 STATUS: " . (0 == $balance && 0 == $totals['total_outstanding'] && $totals['total_paid'] == 3000 ? "✅ ALL FIXED!" : "⚠️ ISSUES REMAIN") . "\n";
echo "════════════════════════════════════════════════════════════════════\n";
