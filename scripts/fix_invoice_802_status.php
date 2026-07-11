<?php
require 'config/database.php';

$db = (new Database())->pdo;

echo "🔧 Fixing Invoice #802 status...\n";

// Update to closed_by_return (settled by return credit, not actual payment)
$stmt = $db->prepare("UPDATE sales SET status = 'closed_by_return' WHERE id = 802 AND tenant_id = 47");
$stmt->execute();

echo "✅ Invoice #802 status updated to 'closed_by_return'\n";

// Verify
$stmt = $db->prepare("
    SELECT id, status, paid_amount, 
           (net_total_amount + IFNULL(tax_amount, 0) - IFNULL(paid_amount, 0)) AS outstanding
    FROM sales WHERE id = 802 AND tenant_id = 47
");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\nVerification:\n";
echo "  status: " . $row['status'] . "\n";
echo "  paid_amount: " . $row['paid_amount'] . "\n";
echo "  outstanding: " . $row['outstanding'] . "\n";

if ($row['status'] === 'closed_by_return' && $row['outstanding'] == 0) {
    echo "\n✅ CORRECT: Invoice settled by return credit with zero outstanding\n";
} else {
    echo "\n❌ Issue remains\n";
}
