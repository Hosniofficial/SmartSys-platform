<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->pdo;
$tenantId = 44;

// ── 1. Latest purchases ──────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT p.id, p.invoice_number, p.invoice_date, p.total_amount, p.paid_amount,
           p.status, p.notes, p.branch_id, p.journal_entry_id,
           s.name AS supplier_name
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id AND s.tenant_id = p.tenant_id
    WHERE p.tenant_id = ?
    ORDER BY p.id DESC LIMIT 10
");
$stmt->execute([$tenantId]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "=== PURCHASES (last 10) ===" . PHP_EOL;
foreach ($purchases as $p) {
    printf("  [#%d] %-20s | supplier:%-20s | total:%-10s | paid:%-10s | status:%-10s | JE:%s\n",
        $p['id'], $p['invoice_number'], $p['supplier_name'] ?? 'NULL',
        $p['total_amount'], $p['paid_amount'], $p['status'],
        $p['journal_entry_id'] ? 'JE#' . $p['journal_entry_id'] : '---NO_JE---');
}

if (empty($purchases)) { echo "  (لا توجد فواتير)" . PHP_EOL; exit; }

// ── 2. Purchase items for latest invoice ────────────────────────────────────
$latestId = $purchases[0]['id'];
$stmt2 = $db->prepare("
    SELECT pi.id, pi.product_id, pr.name AS product_name,
           pi.quantity, pi.cost, pi.price, pi.total, pi.branch_id
    FROM purchase_items pi
    LEFT JOIN products pr ON pr.id = pi.product_id
    WHERE pi.purchase_id = ? AND pi.tenant_id = ?
    ORDER BY pi.id
");
$stmt2->execute([$latestId, $tenantId]);
$items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== PURCHASE ITEMS for purchase #{$latestId} (" . count($items) . " items) ===" . PHP_EOL;
foreach ($items as $it) {
    printf("  [item%d] p%-4d %-25s | qty:%-6s | cost:%-8s | price:%-8s | total:%s\n",
        $it['id'], $it['product_id'], $it['product_name'] ?? '??',
        $it['quantity'], $it['cost'], $it['price'], $it['total']);
}

// ── 3. Inventory transactions linked to latest purchase ──────────────────────
$stmt3 = $db->prepare("
    SELECT it.id, it.product_id, pr.name AS pname, it.branch_to,
           it.movement_type, it.quantity, it.unit_cost, it.total_cost,
           it.journal_entry_id
    FROM inventory_transactions it
    LEFT JOIN products pr ON pr.id = it.product_id
    WHERE it.tenant_id = ?
      AND it.reference_type = 'purchase'
      AND it.reference_id = ?
    ORDER BY it.id
");
$stmt3->execute([$tenantId, $latestId]);
$txs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== INVENTORY TRANSACTIONS for purchase #{$latestId} (" . count($txs) . ") ===" . PHP_EOL;
foreach ($txs as $tx) {
    $je = $tx['journal_entry_id'] ? 'JE#' . $tx['journal_entry_id'] : '---NO_JE---';
    printf("  [tx%d] p%-4d %-25s | mvt:%-12s | qty:%-6s | ucost:%-8s | total:%-10s | %s\n",
        $tx['id'], $tx['product_id'], $tx['pname'] ?? '??',
        $tx['movement_type'], $tx['quantity'], $tx['unit_cost'], $tx['total_cost'], $je);
}

// ── 4. Journal entry for latest purchase ─────────────────────────────────────
$jeId = $purchases[0]['journal_entry_id'];
if ($jeId) {
    $stmt4 = $db->prepare("
        SELECT jel.id, a.code, a.name AS account_name,
               jel.debit_amount, jel.credit_amount, jel.description
        FROM journal_entry_lines jel
        LEFT JOIN accounts a ON a.id = jel.account_id
        WHERE jel.journal_entry_id = ? AND jel.tenant_id = ?
        ORDER BY jel.id
    ");
    $stmt4->execute([$jeId, $tenantId]);
    $lines = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    $totalDr = array_sum(array_column($lines, 'debit_amount'));
    $totalCr = array_sum(array_column($lines, 'credit_amount'));
    echo PHP_EOL . "=== JOURNAL ENTRY JE#{$jeId} (" . count($lines) . " lines) ===" . PHP_EOL;
    foreach ($lines as $l) {
        printf("  [%s] %-30s | Dr:%-10s | Cr:%-10s | %s\n",
            $l['code'] ?? '----', $l['account_name'] ?? '??',
            $l['debit_amount'] ?: '-', $l['credit_amount'] ?: '-',
            mb_substr($l['description'] ?? '', 0, 40));
    }
    printf("  --- TOTALS: Dr=%-10s Cr=%-10s %s\n",
        $totalDr, $totalCr,
        abs($totalDr - $totalCr) < 0.01 ? '✓ BALANCED' : '!! UNBALANCED !!');
} else {
    echo PHP_EOL . "=== JOURNAL ENTRY: ---NO_JE--- للفاتورة الأخيرة ===" . PHP_EOL;
}

// ── 5. branch_products updated? ──────────────────────────────────────────────
if (!empty($items)) {
    $productIds = array_unique(array_column($items, 'product_id'));
    $in = implode(',', array_fill(0, count($productIds), '?'));
    $stmt5 = $db->prepare("
        SELECT bp.product_id, pr.name, bp.branch_id, bp.quantity, bp.quantity_cost, bp.gl_reconciled
        FROM branch_products bp
        LEFT JOIN products pr ON pr.id = bp.product_id
        WHERE bp.tenant_id = ? AND bp.product_id IN ({$in})
        ORDER BY bp.product_id, bp.branch_id
    ");
    $stmt5->execute(array_merge([$tenantId], $productIds));
    $bps = $stmt5->fetchAll(PDO::FETCH_ASSOC);
    echo PHP_EOL . "=== BRANCH_PRODUCTS for purchased items ===" . PHP_EOL;
    foreach ($bps as $bp) {
        printf("  [p%d/b%d] %-25s | qty:%-8s | qty_cost:%-10s | gl_reconciled:%s\n",
            $bp['product_id'], $bp['branch_id'], $bp['name'] ?? '??',
            $bp['quantity'], $bp['quantity_cost'], $bp['gl_reconciled']);
    }
}
