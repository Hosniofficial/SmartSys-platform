<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->pdo;
$tenantId = 39;

// Products
$stmt = $db->prepare('
    SELECT p.id, p.name, p.product_type,
           COUNT(DISTINCT bp.branch_id) as branches,
           COALESCE(SUM(bp.quantity),0) as total_qty,
           COALESCE(SUM(bp.quantity_cost),0) as total_cost
    FROM products p
    LEFT JOIN branch_products bp ON bp.product_id = p.id AND bp.tenant_id = p.tenant_id
    WHERE p.tenant_id = ?
    GROUP BY p.id
    ORDER BY p.id DESC LIMIT 20
');
$stmt->execute([$tenantId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "=== PRODUCTS (" . count($products) . ") ===" . PHP_EOL;
foreach ($products as $p) {
    printf("  [%d] %-30s | type:%-8s | branches:%s | qty:%s | cost:%s\n",
        $p['id'], $p['name'], $p['product_type'], $p['branches'], $p['total_qty'], $p['total_cost']);
}

// GL Mapping
$stmt2 = $db->prepare('
    SELECT p.name as pname, pbgm.product_id, pbgm.branch_id,
           b.name as bname,
           pbgm.activation_status, pbgm.gl_reconciliation_status,
           pbgm.average_cost, pbgm.gl_balance
    FROM product_branch_gl_mapping pbgm
    JOIN products p ON p.id = pbgm.product_id
    LEFT JOIN branches b ON b.id = pbgm.branch_id
    WHERE pbgm.tenant_id = ?
    ORDER BY pbgm.product_id, pbgm.branch_id
');
$stmt2->execute([$tenantId]);
$mappings = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== GL MAPPING (" . count($mappings) . ") ===" . PHP_EOL;
foreach ($mappings as $m) {
    printf("  [p%d/b%d] %-25s | %-20s | %-16s | avg:%s | bal:%s\n",
        $m['product_id'], $m['branch_id'], $m['pname'],
        $m['activation_status'], $m['gl_reconciliation_status'],
        $m['average_cost'] ?? 'NULL', $m['gl_balance'] ?? 'NULL');
}

// Inventory transactions
$stmt3 = $db->prepare('
    SELECT p.name as pname, it.id, it.product_id, it.branch_to,
           b.name as bname,
           it.movement_type, it.reference_type,
           it.quantity, it.unit_cost, it.total_cost, it.journal_entry_id
    FROM inventory_transactions it
    JOIN products p ON p.id = it.product_id
    LEFT JOIN branches b ON b.id = it.branch_to
    WHERE it.tenant_id = ?
    ORDER BY it.id DESC LIMIT 30
');
$stmt3->execute([$tenantId]);
$txs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== INVENTORY TRANSACTIONS (" . count($txs) . ") ===" . PHP_EOL;
foreach ($txs as $tx) {
    $je = $tx['journal_entry_id'] ? 'JE#' . $tx['journal_entry_id'] : '---NO_JE---';
    printf("  [tx%d/p%d] %-25s | %-12s | mvt:%-16s | ref:%-16s | qty:%-5s | ucost:%-8s | total:%-10s | %s\n",
        $tx['id'], $tx['product_id'], $tx['pname'], $tx['bname'] ?? '--',
        $tx['movement_type'], $tx['reference_type'],
        $tx['quantity'], $tx['unit_cost'], $tx['total_cost'], $je);
}

// Check zero-qty products in GL mapping (Fix 1 check)
$stmt4 = $db->prepare('
    SELECT p.id, p.name, pbgm.branch_id, b.name as bname,
           pbgm.gl_reconciliation_status, bp.quantity
    FROM product_branch_gl_mapping pbgm
    JOIN products p ON p.id = pbgm.product_id
    LEFT JOIN branches b ON b.id = pbgm.branch_id
    LEFT JOIN branch_products bp ON bp.product_id = pbgm.product_id AND bp.branch_id = pbgm.branch_id AND bp.tenant_id = pbgm.tenant_id
    WHERE pbgm.tenant_id = ?
      AND (bp.quantity = 0 OR bp.quantity IS NULL)
      AND pbgm.gl_reconciliation_status != "RECONCILED"
');
$stmt4->execute([$tenantId]);
$zeroGl = $stmt4->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== FIX1 CHECK: GL mapping لمنتجات qty=0 (يجب ان يكون فارغا) (" . count($zeroGl) . ") ===" . PHP_EOL;
if (empty($zeroGl)) {
    echo "  OK - لا توجد منتجات بدون رصيد مفعّلة في GL" . PHP_EOL;
} else {
    foreach ($zeroGl as $z) {
        printf("  !! [p%d] %s | فرع:%s | status:%s | qty:%s\n",
            $z['id'], $z['name'], $z['bname'], $z['gl_reconciliation_status'], $z['quantity'] ?? 'NULL');
    }
}

// Journal entries
$stmt5 = $db->prepare('
    SELECT je.id, je.reference_type, je.reference_id, je.description,
           je.entry_date, je.status,
           SUM(COALESCE(jel.debit_amount,0)) as total_dr,
           SUM(COALESCE(jel.credit_amount,0)) as total_cr
    FROM journal_entries je
    LEFT JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id
    WHERE je.tenant_id = ?
      AND je.reference_type IN ("opening_balance","initial_stock")
    GROUP BY je.id
    ORDER BY je.id DESC LIMIT 20
');
$stmt5->execute([$tenantId]);
$jes = $stmt5->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== JOURNAL ENTRIES opening_balance/initial_stock (" . count($jes) . ") ===" . PHP_EOL;
foreach ($jes as $je) {
    printf("  [JE#%d] ref_type:%-16s | ref_id:%-5s | Dr:%-10s Cr:%-10s | %s\n",
        $je['id'], $je['reference_type'], $je['reference_id'],
        $je['total_dr'], $je['total_cr'], mb_substr($je['description'], 0, 50));
}
