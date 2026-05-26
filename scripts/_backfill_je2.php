<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->pdo;
$tenantId = 44;

$payIds = [641, 642, 643, 644];
$updated = 0;

foreach ($payIds as $pid) {
    $stmtP = $db->prepare("SELECT purchase_id FROM payments WHERE id=? AND tenant_id=? AND journal_entry_id IS NULL");
    $stmtP->execute([$pid, $tenantId]);
    $pay = $stmtP->fetch(PDO::FETCH_ASSOC);
    if (!$pay) { echo "pay#{$pid}: already has JE or not found, skip\n"; continue; }

    $stmtJe = $db->prepare("SELECT journal_entry_id FROM purchases WHERE id=? AND tenant_id=? LIMIT 1");
    $stmtJe->execute([$pay['purchase_id'], $tenantId]);
    $jeId = (int) $stmtJe->fetchColumn();

    if ($jeId <= 0) { echo "pay#{$pid}: purchase#{$pay['purchase_id']} has no JE, skip\n"; continue; }

    $db->prepare("UPDATE payments SET journal_entry_id=? WHERE id=? AND tenant_id=?")->execute([$jeId, $pid, $tenantId]);
    echo "pay#{$pid} <- JE#$jeId (from purchase#{$pay['purchase_id']})\n";
    $updated++;
}

echo "\nDone. Updated {$updated} payments.\n";

// Verify
$stmt = $db->prepare("SELECT id, purchase_id, journal_entry_id FROM payments WHERE id IN (641,642,643,644) AND tenant_id=?");
$stmt->execute([$tenantId]);
echo "\nVerify:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("  pay#%d | purchase#%d | JE:%s\n", $r['id'], $r['purchase_id'], $r['journal_entry_id']??'NULL');
}
