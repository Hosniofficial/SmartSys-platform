<?php
require 'config/database.php';

$db = (new Database())->pdo;
$tenantId = 47;

echo "════════════════════════════════════════════════════════════════════\n";
echo "🔍 TEST: Direct SQL UPDATE on Invoice #802\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

// First, get current state
echo "BEFORE UPDATE:\n";
$stmt = $db->prepare("
    SELECT id, status, paid_amount, (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total
    FROM sales WHERE id = 802 AND tenant_id = ?
");
$stmt->execute([$tenantId]);
$before = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  status: " . $before['status'] . "\n";
echo "  paid_amount: " . $before['paid_amount'] . "\n";
echo "  grand_total: " . $before['grand_total'] . "\n";
echo "  outstanding: " . ($before['grand_total'] - $before['paid_amount']) . "\n\n";

// Try the exact UPDATE statement
echo "Executing UPDATE statement...\n";
try {
    $stmt = $db->prepare("
        UPDATE sales 
         SET status = 'paid',
             paid_amount = net_total_amount + IFNULL(tax_amount, 0)
         WHERE id = ? AND tenant_id = ?
    ");
    $result = $stmt->execute([802, $tenantId]);
    echo "  ✅ UPDATE executed, affected rows: " . $stmt->rowCount() . "\n\n";
} catch (\Throwable $e) {
    echo "  ❌ UPDATE failed: " . $e->getMessage() . "\n\n";
}

// Check state after
echo "AFTER UPDATE:\n";
$stmt = $db->prepare("
    SELECT id, status, paid_amount, (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total
    FROM sales WHERE id = 802 AND tenant_id = ?
");
$stmt->execute([$tenantId]);
$after = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  status: " . $after['status'] . "\n";
echo "  paid_amount: " . $after['paid_amount'] . "\n";
echo "  grand_total: " . $after['grand_total'] . "\n";
echo "  outstanding: " . ($after['grand_total'] - $after['paid_amount']) . "\n\n";

// Compare
echo "COMPARISON:\n";
echo "  status changed: " . ($before['status'] !== $after['status'] ? "YES" : "NO") . "\n";
echo "  paid_amount changed: " . ($before['paid_amount'] != $after['paid_amount'] ? "YES ✅" : "NO ❌") . "\n";

if ($after['paid_amount'] == $after['grand_total']) {
    echo "\n✅ SUCCESS: paid_amount now equals grand_total\n";
    echo "   outstanding = 0 (correct!)\n";
} else {
    echo "\n❌ ISSUE: paid_amount still doesn't match grand_total\n";
    echo "   Difference: " . ($after['grand_total'] - $after['paid_amount']) . "\n";
}

echo "\n════════════════════════════════════════════════════════════════════\n";
