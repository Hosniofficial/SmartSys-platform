<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$pdo = $database->pdo;

// Use tenant 47 which has data
$tenantId  = 47;

// Auto-detect latest product for this tenant - but allow override
$productIdArg = isset($argv[1]) ? (int)$argv[1] : null;
$latest = $pdo->prepare("SELECT id, name, created_at FROM products WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
$latest->execute([$tenantId]);
$latestProduct = $latest->fetch(PDO::FETCH_ASSOC);

if ($productIdArg) {
  // Override with command line argument
  $productId = $productIdArg;
  $productCheck = $pdo->prepare("SELECT id, name, created_at FROM products WHERE id = ? AND tenant_id = ?");
  $productCheck->execute([$productId, $tenantId]);
  $latestProduct = $productCheck->fetch(PDO::FETCH_ASSOC);
} else {
  // Use latest
  $productId = $latestProduct ? (int) $latestProduct['id'] : 0;
}

echo "=== التينانت: {$tenantId} | آخر منتج: ID={$productId} | {$latestProduct['name']} | {$latestProduct['created_at']} ===\n\n";

// ─── ALL BRANCHES for this product ────────────────────────────────────────────
section("0. جميع الفروع في product_branch_gl_mapping");
$stmt0 = $pdo->prepare("
    SELECT pbm.id AS mapping_id, pbm.branch_id, b.name AS branch_name,
           pbm.activation_status, pbm.gl_reconciliation_status,
           pbm.gl_balance, pbm.average_cost
    FROM product_branch_gl_mapping pbm
    JOIN branches b ON b.id = pbm.branch_id AND b.tenant_id = pbm.tenant_id
    WHERE pbm.tenant_id = ? AND pbm.product_id = ?
    ORDER BY pbm.branch_id
");
$stmt0->execute([$tenantId, $productId]);
printRows($stmt0->fetchAll());

section("0b. branch_products لجميع الفروع");
$stmt0b = $pdo->prepare("
    SELECT branch_id, quantity, quantity_cost, gl_reconciled
    FROM branch_products WHERE tenant_id = ? AND product_id = ?
    ORDER BY branch_id
");
$stmt0b->execute([$tenantId, $productId]);
printRows($stmt0b->fetchAll());

section("0c. inventory_transactions لجميع الفروع");
$stmt0c = $pdo->prepare("
    SELECT id, branch_to AS branch_id, quantity, unit_cost, total_cost,
           movement_type, journal_entry_id
    FROM inventory_transactions WHERE tenant_id = ? AND product_id = ?
    ORDER BY id
");
$stmt0c->execute([$tenantId, $productId]);
printRows($stmt0c->fetchAll());

section("0d. opening_balances لجميع الفروع");
$stmt0d = $pdo->prepare("
    SELECT id, branch_id, quantity, unit_cost, entry_date
    FROM opening_balances WHERE tenant_id = ? AND product_id = ?
    ORDER BY branch_id
");
$stmt0d->execute([$tenantId, $productId]);
printRows($stmt0d->fetchAll());

section("0e. journal_entries لجميع الفروع (opening_balance)");
$stmt0e = $pdo->prepare("
    SELECT je.id, je.reference_type, je.reference_id, je.entry_date,
           je.idempotency_key, je.description
    FROM journal_entries je
    WHERE je.tenant_id = ?
      AND je.idempotency_key LIKE CONCAT('ob:t', ?, ':p', ?, ':%')
    ORDER BY je.id
");
$stmt0e->execute([$tenantId, $tenantId, $productId]);
printRows($stmt0e->fetchAll());

function section(string $title): void {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 60) . "\n";
}

function printRows(array $rows): void {
    if (empty($rows)) {
        echo "  ⚠️  لا توجد نتائج\n";
        return;
    }
    // Print header
    $headers = array_keys($rows[0]);
    $widths  = array_fill_keys($headers, 0);
    foreach ($rows as $row) {
        foreach ($headers as $h) {
            $widths[$h] = max($widths[$h], mb_strlen((string)($row[$h] ?? ''), 'UTF-8'), mb_strlen($h, 'UTF-8'));
        }
    }
    $line = '  +';
    foreach ($widths as $w) { $line .= str_repeat('-', $w + 2) . '+'; }
    echo $line . "\n  |";
    foreach ($headers as $h) { printf(" %-{$widths[$h]}s |", $h); }
    echo "\n$line\n";
    foreach ($rows as $row) {
        echo '  |';
        foreach ($headers as $h) {
            $val = (string)($row[$h] ?? 'NULL');
            $pad = $widths[$h] - mb_strlen($val, 'UTF-8');
            echo ' ' . $val . str_repeat(' ', $pad) . ' |';
        }
        echo "\n";
    }
    echo "$line\n";
}

// ─── 1. المنتج ───────────────────────────────────────────────────────────────
section("1. المنتج (id=$productId)");
$stmt = $pdo->prepare("SELECT id, name, created_at FROM products WHERE tenant_id=? AND id=?");
$stmt->execute([$tenantId, $productId]);
printRows($stmt->fetchAll());

// ─── 2. حركات المخزون ────────────────────────────────────────────────────────
section("2. inventory_transactions (ALL movements)");
$stmt = $pdo->prepare("SELECT id, branch_to AS branch_id, quantity, unit_cost, total_cost, movement_type, movement_date FROM inventory_transactions WHERE tenant_id=? AND product_id=? ORDER BY id DESC");
$stmt->execute([$tenantId, $productId]);
printRows($stmt->fetchAll());

// ─── 3. branch_products ──────────────────────────────────────────────────────
section("3. branch_products");
$stmt = $pdo->prepare("SELECT branch_id, product_id, quantity, quantity_cost, gl_reconciled FROM branch_products WHERE tenant_id=? AND product_id=?");
$stmt->execute([$tenantId, $productId]);
printRows($stmt->fetchAll());

// ─── 3b. opening_balances table ──────────────────────────────────────────────
section("3b. opening_balances");
$stmt = $pdo->prepare("SELECT id, branch_id, quantity, unit_cost, entry_date FROM opening_balances WHERE tenant_id=? AND product_id=? ORDER BY id DESC");
$stmt->execute([$tenantId, $productId]);
printRows($stmt->fetchAll());

// ─── 3c. purchases (opening balance via OpeningBalanceHandler) ───────────────
section("3c. purchases (OB- invoices for this product)");
$stmt = $pdo->prepare("
    SELECT p.id, p.invoice_number, p.total_amount, p.status, p.created_at, p.journal_entry_id
    FROM purchases p
    INNER JOIN purchase_items pi ON pi.purchase_id = p.id AND pi.product_id = ?
    WHERE p.tenant_id = ? AND p.notes = 'opening_balance'
    ORDER BY p.id DESC LIMIT 5");
$stmt->execute([$productId, $tenantId]);
$purchases = $stmt->fetchAll();
printRows($purchases);

// ─── 3d. قيد opening_balance عبر OpeningBalanceHandler ───────────────────────
section("3d. journal_entries → opening_balance (reference_id=purchase_id)");
$obJes = [];
if (!empty($purchases)) {
    foreach ($purchases as $pur) {
        $purId = $pur['id'];
        $stmt3 = $pdo->prepare("SELECT id, description, entry_date, reference_type, reference_id FROM journal_entries WHERE tenant_id=? AND reference_type='opening_balance' AND reference_id=? ORDER BY id DESC");
        $stmt3->execute([$tenantId, $purId]);
        $rows3 = $stmt3->fetchAll();
        echo "  purchase_id=$purId → ";
        printRows($rows3);
        $obJes = array_merge($obJes, $rows3);
    }
} else {
    echo "  ⚠️  لا توجد مشتريات opening_balance لهذا المنتج\n";
}

// ─── 4a. القيد (product_opening_balance عبر ProductsHandler) ──────────────────
section("4a. journal_entries → product_opening_balance (reference_id=product_id)");
$stmt = $pdo->prepare("SELECT id, description, entry_date, reference_type, reference_id FROM journal_entries WHERE tenant_id=? AND reference_type='product_opening_balance' AND reference_id=? ORDER BY id DESC");
$stmt->execute([$tenantId, $productId]);
$jes = $stmt->fetchAll();
printRows($jes);

// ─── 4b. القيد (product_branch_opening عبر ProductBranchHandler) ──────────────
section("4b. journal_entries → product_branch_opening (reference_id=mapping_id)");
$mapStmt = $pdo->prepare("SELECT id FROM product_branch_gl_mapping WHERE tenant_id=? AND product_id=?");
$mapStmt->execute([$tenantId, $productId]);
$mappingId = $mapStmt->fetchColumn();
if ($mappingId) {
    $stmt2 = $pdo->prepare("SELECT id, description, entry_date, reference_type, reference_id FROM journal_entries WHERE tenant_id=? AND reference_type='product_branch_opening' AND reference_id=? ORDER BY id DESC");
    $stmt2->execute([$tenantId, $mappingId]);
    $jes2 = $stmt2->fetchAll();
    echo "  mapping_id=$mappingId\n";
    printRows($jes2);
    if (!empty($jes2)) $jes = array_merge($jes, $jes2);
} else {
    echo "  ⚠️  لا يوجد mapping لهذا المنتج\n";
}

// ─── 4b2. القيد (opening_balance عبر InventoryOpeningBalanceService) ───────────
section("4b2. journal_entries → opening_balance via Service (reference_id=mapping_id)");
if ($mappingId) {
    $stmt4 = $pdo->prepare("SELECT id, description, entry_date, reference_type, reference_id, idempotency_key FROM journal_entries WHERE tenant_id=? AND reference_type='opening_balance' AND reference_id=? ORDER BY id DESC");
    $stmt4->execute([$tenantId, $mappingId]);
    $jes4 = $stmt4->fetchAll();
    echo "  mapping_id=$mappingId\n";
    printRows($jes4);
    if (!empty($jes4)) $jes = array_merge($jes, $jes4);
} else {
    echo "  ⚠️  لا يوجد mapping\n";
}

// ─── 4b3. inventory_transactions.journal_entry_id ────────────────────────────
section("4b3. inventory_transactions.journal_entry_id");
$stmt5 = $pdo->prepare("SELECT id, movement_type, journal_entry_id FROM inventory_transactions WHERE tenant_id=? AND product_id=? ORDER BY id DESC");
$stmt5->execute([$tenantId, $productId]);
$itRows = $stmt5->fetchAll();
printRows($itRows);
// If JE found via inventory_transactions, add to list
foreach ($itRows as $itr) {
    if (!empty($itr['journal_entry_id'])) {
        $stmt6 = $pdo->prepare("SELECT id, description, entry_date, reference_type, reference_id FROM journal_entries WHERE id=? LIMIT 1");
        $stmt6->execute([$itr['journal_entry_id']]);
        $jeRow = $stmt6->fetch();
        if ($jeRow && !in_array($jeRow['id'], array_column($jes, 'id'))) {
            $jes[] = $jeRow;
        }
    }
}

// ─── 4c. inventory_cost_snapshot ─────────────────────────────────────────────
section("4c. inventory_cost_snapshot");
$stmt = $pdo->prepare("SELECT id, layer_date, unit_cost, quantity_received, quantity_remaining, source_type FROM inventory_cost_snapshot WHERE tenant_id=? AND product_id=? ORDER BY id DESC");
$stmt->execute([$tenantId, $productId]);
printRows($stmt->fetchAll());

// ─── 5. سطور القيد ───────────────────────────────────────────────────────────
section("5. journal_entry_lines");
$allJes = array_merge($jes, $obJes ?? []);
if (!empty($allJes)) {
    $jes = $allJes;
}
if (!empty($jes)) {
    $jeId = $jes[0]['id'];
    $cols = $pdo->query("DESCRIBE journal_entry_lines")->fetchAll(PDO::FETCH_COLUMN, 0);
    $debitCol  = in_array('debit_amount', $cols)  ? 'debit_amount'  : (in_array('debit', $cols)  ? 'debit'  : 'amount_debit');
    $creditCol = in_array('credit_amount', $cols) ? 'credit_amount' : (in_array('credit', $cols) ? 'credit' : 'amount_credit');
    $stmt = $pdo->prepare("SELECT jel.id, a.code, a.name AS account_name, jel.$debitCol AS debit, jel.$creditCol AS credit, jel.description FROM journal_entry_lines jel JOIN accounts a ON a.id=jel.account_id WHERE jel.journal_entry_id=?");
    $stmt->execute([$jeId]);
    printRows($stmt->fetchAll());

    // التحقق من التوازن
    $stmt2 = $pdo->prepare("SELECT SUM($debitCol) AS total_debit, SUM($creditCol) AS total_credit FROM journal_entry_lines WHERE journal_entry_id=?");
    $stmt2->execute([$jeId]);
    $bal = $stmt2->fetch();
    $ok  = abs($bal['total_debit'] - $bal['total_credit']) < 0.01;
    echo "\n  توازن القيد: مدين=" . $bal['total_debit'] . " | دائن=" . $bal['total_credit'] . " → " . ($ok ? "✅ متوازن" : "❌ غير متوازن") . "\n";
} else {
    echo "  ⚠️  لا يوجد قيد محاسبي — لم يُنشأ القيد أو totalCost=0\n";
}

// ─── 6. product_branch_gl_mapping ────────────────────────────────────────────
section("6. product_branch_gl_mapping");
$stmt = $pdo->prepare("SELECT product_id, branch_id, gl_reconciliation_status, gl_balance, average_cost, inventory_gl_account_id, cogs_gl_account_id FROM product_branch_gl_mapping WHERE tenant_id=? AND product_id=?");
$stmt->execute([$tenantId, $productId]);
printRows($stmt->fetchAll());

echo "\n";
