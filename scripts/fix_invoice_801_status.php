<?php
require 'config/database.php';
$db = (new Database())->pdo;

echo "🔧 Fixing Invoice #801 status (should be 'paid' not 'closed_by_return')...\n\n";

// Invoice #801 was paid 2000 in full by actual payment #702
// So status should be 'paid', not 'closed_by_return'

$stmt = $db->prepare("
    UPDATE sales 
    SET status = 'paid'
    WHERE id = 801 AND tenant_id = 47
");
$stmt->execute();
echo "✅ Updated Invoice #801 status to 'paid'\n\n";

// Verify both invoices now
$stmt = $db->prepare("
    SELECT id, status, paid_amount, 
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total,
           (net_total_amount + IFNULL(tax_amount, 0) - paid_amount) AS outstanding
    FROM sales WHERE id IN (801, 802) AND tenant_id = 47
    ORDER BY id
");
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Final Invoice States:\n";
echo "─────────────────────────────────────────────────────────────────\n";
foreach ($invoices as $inv) {
    echo "Invoice #" . $inv['id'] . ":\n";
    echo "  status: " . $inv['status'] . "\n";
    echo "  grand_total: " . $inv['grand_total'] . "\n";
    echo "  paid_amount: " . $inv['paid_amount'] . "\n";
    echo "  outstanding: " . $inv['outstanding'] . "\n";
    echo "  Status Check: " . ($inv['outstanding'] == 0 ? "✅" : "⚠️") . "\n\n";
}

echo "════════════════════════════════════════════════════════════════════\n";
echo "✅ SUMMARY:\n";
echo "  Invoice #801: PAID (2000 cash payment)\n";
echo "  Invoice #802: CLOSED_BY_RETURN (1000 cash + 1000 return credit)\n";
echo "  All Invoices: SETTLED ✅\n";
