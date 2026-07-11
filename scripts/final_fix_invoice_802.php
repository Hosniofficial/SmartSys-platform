<?php
require 'config/database.php';
$db = (new Database())->pdo;

echo "🔧 Fixing Invoice #802 paid_amount...\n\n";

// When settled by return, paid_amount must equal grand_total
$stmt = $db->prepare("
    UPDATE sales 
    SET paid_amount = (net_total_amount + IFNULL(tax_amount, 0))
    WHERE id = 802 AND tenant_id = 47
");
$stmt->execute();
echo "✅ Updated paid_amount for Invoice #802\n\n";

// Verify
$stmt = $db->prepare("
    SELECT id, status, paid_amount, 
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total,
           (net_total_amount + IFNULL(tax_amount, 0) - paid_amount) AS outstanding
    FROM sales WHERE id = 802 AND tenant_id = 47
");
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Final State - Invoice #802:\n";
echo "  status: " . $invoice['status'] . "\n";
echo "  grand_total: " . $invoice['grand_total'] . "\n";
echo "  paid_amount: " . $invoice['paid_amount'] . "\n";
echo "  outstanding: " . $invoice['outstanding'] . "\n\n";

if ($invoice['status'] === 'closed_by_return' && $invoice['outstanding'] == 0) {
    echo "✅ CORRECT: Invoice settled by return credit with zero outstanding\n";
} else {
    echo "❌ Still has issues\n";
}
