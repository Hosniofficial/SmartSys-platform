<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->pdo;
$tenantId = 44;

$checks = [
    ['label' => 'PURCHASE #284 (PUR-260508-001)', 'type' => 'purchase', 'id' => 284],
    ['label' => 'SALE #762 (S-260508-003)',        'type' => 'sale',     'id' => 762],
];

function showJE($db, $tenantId, $jeId, $label) {
    echo PHP_EOL . "  JE#{$jeId} -- {$label}" . PHP_EOL;
    $stmt = $db->prepare("SELECT description, entry_date, reference_type, reference_id FROM journal_entries WHERE id=? AND tenant_id=?");
    $stmt->execute([$jeId, $tenantId]);
    $je = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$je) { echo "    (JE not found)" . PHP_EOL; return; }
    echo "    ref:{$je['reference_type']}/{$je['reference_id']}  date:{$je['entry_date']}" . PHP_EOL;

    $stmt2 = $db->prepare(
        "SELECT jel.debit_amount, jel.credit_amount, a.code, a.name
         FROM journal_entry_lines jel
         LEFT JOIN accounts a ON a.id = jel.account_id AND a.tenant_id = jel.tenant_id
         WHERE jel.journal_entry_id = ? AND jel.tenant_id = ?
         ORDER BY jel.id"
    );
    $stmt2->execute([$jeId, $tenantId]);
    $lines = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $totDr = 0; $totCr = 0;
    foreach ($lines as $l) {
        $totDr += (float)$l['debit_amount'];
        $totCr += (float)$l['credit_amount'];
        printf("    DR:%-10.2f  CR:%-10.2f  acc:%-8s %s\n",
            $l['debit_amount'], $l['credit_amount'], $l['code'] ?? '???', $l['name'] ?? '');
    }
    $balanced = abs($totDr - $totCr) < 0.01;
    printf("    TOTAL     DR:%-10.2f  CR:%-10.2f  [%s]\n", $totDr, $totCr, $balanced ? 'BALANCED' : '!! UNBALANCED !!');
}

foreach ($checks as $chk) {
    echo PHP_EOL . str_repeat('=', 70) . PHP_EOL;
    echo $chk['label'] . PHP_EOL;

    if ($chk['type'] === 'purchase') {
        $stmt = $db->prepare("SELECT id, invoice_number, total_amount, paid_amount, status, journal_entry_id FROM purchases WHERE id=? AND tenant_id=?");
    } else {
        $stmt = $db->prepare("SELECT id, invoice_number, ROUND(net_total_amount+IFNULL(tax_amount,0),2) AS total_amount, paid_amount, status, journal_entry_id FROM sales WHERE id=? AND tenant_id=?");
    }
    $stmt->execute([$chk['id'], $tenantId]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inv) { echo "  (not found)" . PHP_EOL; continue; }
    printf("  invoice:%s | total:%.2f | paid:%.2f | status:%s | JE:%s\n",
        $inv['invoice_number'], $inv['total_amount'], $inv['paid_amount'], $inv['status'],
        $inv['journal_entry_id'] ? 'JE#'.$inv['journal_entry_id'] : 'NULL');

    if ($inv['journal_entry_id']) {
        showJE($db, $tenantId, (int)$inv['journal_entry_id'], 'Invoice JE');
    }

    $col = $chk['type'] === 'purchase' ? 'purchase_id' : 'sale_id';
    $stmt2 = $db->prepare("SELECT id, amount, type, journal_entry_id, payment_date FROM payments WHERE {$col}=? AND tenant_id=? AND status='completed' ORDER BY id");
    $stmt2->execute([$chk['id'], $tenantId]);
    $pays = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo PHP_EOL . "  Payments:" . PHP_EOL;
    foreach ($pays as $p) {
        printf("    pay#%d | %.2f | type:%-10s | JE:%s | date:%s\n",
            $p['id'], $p['amount'], $p['type'],
            $p['journal_entry_id'] ? 'JE#'.$p['journal_entry_id'] : 'NULL', $p['payment_date']);
        if ($p['journal_entry_id'] && (int)$p['journal_entry_id'] !== (int)$inv['journal_entry_id']) {
            showJE($db, $tenantId, (int)$p['journal_entry_id'], 'Payment JE');
        }
    }
}

echo PHP_EOL . str_repeat('-', 70) . PHP_EOL . "Done." . PHP_EOL;
