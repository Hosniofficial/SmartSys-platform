<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->pdo;
$tenantId = 44;

// Show journal_entries columns
$cols = $db->query('SHOW COLUMNS FROM journal_entries')->fetchAll(PDO::FETCH_ASSOC);
echo 'journal_entries columns: ' . implode('  ', array_column($cols, 'Field')) . PHP_EOL;

$payIds = [641, 642, 643, 644];
echo PHP_EOL . '=== Searching journal entries for payments without JE ===' . PHP_EOL;
foreach ($payIds as $pid) {
    $stmtPay = $db->prepare("SELECT purchase_id, amount, payment_date, payment_method_id FROM payments WHERE id=? AND tenant_id=?");
    $stmtPay->execute([$pid, $tenantId]);
    $pay = $stmtPay->fetch(PDO::FETCH_ASSOC);
    echo PHP_EOL . "--- payment#{$pid} | purchase#{$pay['purchase_id']} | amount:{$pay['amount']} ---" . PHP_EOL;

    // Try reference_type=payment
    $stmt = $db->prepare("SELECT id, reference_id, reference_type, description, created_at FROM journal_entries WHERE tenant_id=? AND reference_type='payment' AND reference_id=? ORDER BY id DESC LIMIT 3");
    $stmt->execute([$tenantId, $pid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) printf("  JE#%d ref:%s/%s %s\n", $r['id'], $r['reference_type'], $r['reference_id'], $r['description']);

    // Try reference_type=purchase
    $stmt2 = $db->prepare("SELECT id, reference_id, reference_type, description, created_at FROM journal_entries WHERE tenant_id=? AND reference_type='purchase' AND reference_id=? ORDER BY id DESC LIMIT 5");
    $stmt2->execute([$tenantId, $pay['purchase_id']]);
    $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows2 as $r) printf("  JE#%d ref:%s/%s %s\n", $r['id'], $r['reference_type'], $r['reference_id'], $r['description']);

    if (empty($rows) && empty($rows2)) echo "  (no JE found)" . PHP_EOL;
}

echo PHP_EOL . '=== journal_entry_lines with matching amounts ===' . PHP_EOL;
foreach ($payIds as $pid) {
    $stmtPay = $db->prepare("SELECT purchase_id, amount FROM payments WHERE id=? AND tenant_id=?");
    $stmtPay->execute([$pid, $tenantId]);
    $pay = $stmtPay->fetch(PDO::FETCH_ASSOC);
    $stmt = $db->prepare("SELECT jel.journal_entry_id, jel.amount, je.reference_type, je.reference_id, je.description FROM journal_entry_lines jel JOIN journal_entries je ON je.id=jel.journal_entry_id WHERE jel.tenant_id=? AND jel.amount=? AND je.reference_type IN ('payment','purchase') ORDER BY jel.id DESC LIMIT 5");
    $stmt->execute([$tenantId, $pay['amount']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo PHP_EOL . "pay#{$pid} purchase#{$pay['purchase_id']} amount:{$pay['amount']}:" . PHP_EOL;
    foreach ($rows as $r) printf("  JE#%d | %s | ref:%s/%s | %s\n", $r['journal_entry_id'], $r['amount'], $r['reference_type'], $r['reference_id'], $r['description']);
    if (empty($rows)) echo "  (no match)" . PHP_EOL;
}
