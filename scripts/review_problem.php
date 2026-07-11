<?php
require 'config/database.php';
$db = (new Database())->pdo;

echo "════════════════════════════════════════════════════════════════════\n";
echo "🔍 ACTUAL STATE CHECK\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

$stmt = $db->prepare("
    SELECT id, status, paid_amount, 
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total
    FROM sales 
    WHERE id IN (801, 802) AND tenant_id = 47
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $outstanding = $r['grand_total'] - $r['paid_amount'];
    echo "Invoice #" . $r['id'] . ":\n";
    echo "  status: " . $r['status'] . "\n";
    echo "  grand_total: " . $r['grand_total'] . "\n";
    echo "  paid_amount: " . $r['paid_amount'] . "\n";
    echo "  outstanding: " . $outstanding . "\n";
    echo "  Problem: " . ($outstanding == 0 ? "✅ NONE" : "❌ Outstanding should be 0 for closed_by_return") . "\n\n";
}

// Check what allocateCustomerBalance was supposed to update
echo "════════════════════════════════════════════════════════════════════\n";
echo "🔍 THE REAL ISSUE\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

echo "When allocateCustomerBalance() executes for a return allocation:\n\n";

echo "1. It receives:\n";
echo "   - customerId = 37\n";
echo "   - amountToAllocate = 2000 (return credit)\n";
echo "   - originalSaleId = 802\n\n";

echo "2. It calculates:\n";
echo "   - Invoice #802 outstanding = 2000 - 1000 = 1000\n";
echo "   - apply = min(2000, 1000) = 1000\n";
echo "   - newOutstanding = 1000 - 1000 = 0 ✅\n\n";

echo "3. It updates:\n";
echo "   - UPDATE sales SET status = 'closed_by_return' WHERE id = 802\n";
echo "   - But does NOT update paid_amount! ❌\n\n";

echo "4. Result:\n";
echo "   - status = 'closed_by_return' (correct)\n";
echo "   - paid_amount = 1000 (unchanged)\n";
echo "   - outstanding = 2000 - 1000 = 1000 (WRONG!)\n\n";

echo "════════════════════════════════════════════════════════════════════\n";
echo "🎯 THE FIX NEEDED\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

echo "When settlement_type = 'closed_by_return' AND newOutstanding <= 0.01:\n";
echo "  MUST UPDATE paid_amount = grand_total\n";
echo "  So that outstanding = grand_total - paid_amount = 0\n\n";

echo "This reflects: 'Invoice settled by return credit' (status)\n";
echo "               'Debt fully offset' (outstanding = 0)\n";
