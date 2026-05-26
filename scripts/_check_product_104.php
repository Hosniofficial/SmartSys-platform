<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;
$tenantId = 39;

// Find new zero-qty products (products without GL mapping)
$stmt = $db->prepare("
    SELECT p.id, p.name, p.product_type,
           bp.branch_id, b.name AS branch_name, bp.quantity, bp.quantity_cost,
           pbm.id AS mapping_id, pbm.activation_status, pbm.gl_reconciliation_status
    FROM products p
    LEFT JOIN branch_products bp ON bp.product_id = p.id AND bp.tenant_id = p.tenant_id
    LEFT JOIN branches b ON b.id = bp.branch_id
    LEFT JOIN product_branch_gl_mapping pbm
           ON pbm.product_id = p.id AND pbm.branch_id = bp.branch_id AND pbm.tenant_id = p.tenant_id
    WHERE p.tenant_id = ?
      AND p.id >= 104
    ORDER BY p.id, bp.branch_id
");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Products id >= 104 ===" . PHP_EOL;
printf("%-5s %-22s %-10s %-18s %-5s %-10s %-8s %-20s %-16s\n",
    'id','name','type','branch','qty','qty_cost','map_id','activation_status','recon_status');
echo str_repeat('-',120).PHP_EOL;
foreach ($rows as $r) {
    printf("%-5s %-22s %-10s %-18s %-5s %-10s %-8s %-20s %-16s\n",
        $r['id'], mb_substr($r['name'],0,20), $r['product_type'],
        mb_substr($r['branch_name']??'--',0,16), $r['quantity']??'--', $r['quantity_cost']??'--',
        $r['mapping_id']??'NULL', $r['activation_status']??'NO_MAPPING', $r['gl_reconciliation_status']??'--');
}

echo PHP_EOL . "=== inventory_transactions for id >= 104 ===" . PHP_EOL;
$stmt2 = $db->prepare("
    SELECT it.id, it.product_id, p.name, b.name AS branch,
           it.movement_type, it.reference_type, it.reference_id, it.quantity, it.journal_entry_id
    FROM inventory_transactions it
    JOIN products p ON p.id = it.product_id
    LEFT JOIN branches b ON b.id = it.branch_to
    WHERE it.tenant_id = ? AND it.product_id >= 104
");
$stmt2->execute([$tenantId]);
$txs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
if (empty($txs)) {
    echo "  لا توجد inventory_transactions لهذه المنتجات" . PHP_EOL;
} else {
    foreach ($txs as $t) {
        printf("  [tx%d] p%d %s | %s | mvt:%s | qty:%s | JE:%s\n",
            $t['id'], $t['product_id'], $t['name'], $t['branch'],
            $t['movement_type'], $t['quantity'], $t['journal_entry_id']??'--');
    }
}
