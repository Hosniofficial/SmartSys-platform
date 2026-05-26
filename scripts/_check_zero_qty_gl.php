<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;
$tenantId = 39;

echo "=== كل المنتجات qty=0 مع حالة GL ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT p.id, p.name,
           bp.branch_id, b.name AS branch_name, bp.quantity,
           pbm.id AS mapping_id, pbm.activation_status
    FROM products p
    JOIN branch_products bp ON bp.product_id = p.id AND bp.tenant_id = p.tenant_id
    JOIN branches b ON b.id = bp.branch_id
    LEFT JOIN product_branch_gl_mapping pbm
           ON pbm.product_id = p.id AND pbm.branch_id = bp.branch_id AND pbm.tenant_id = p.tenant_id
    WHERE p.tenant_id = ?
      AND COALESCE(bp.quantity, 0) = 0
    ORDER BY p.id
");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $glStatus = $r['mapping_id'] ? "HAS_MAPPING ({$r['activation_status']})" : "NO_MAPPING";
    $flag = $r['mapping_id'] ? " ⚠️ BUG" : " ✅ OK";
    printf("  p%d %-20s | branch:%d %-18s | qty:0 | GL: %s%s\n",
        $r['id'], mb_substr($r['name'],0,18), $r['branch_id'],
        mb_substr($r['branch_name'],0,16), $glStatus, $flag);
}

echo PHP_EOL . "=== المنتج الظاهر في POS (id=104 أو ايفون 11) ===" . PHP_EOL;
$stmt2 = $db->prepare("
    SELECT p.id, p.name, bp.branch_id, bp.quantity,
           pbm.id AS mapping_id, pbm.activation_status
    FROM products p
    JOIN branch_products bp ON bp.product_id = p.id AND bp.tenant_id = p.tenant_id
    LEFT JOIN product_branch_gl_mapping pbm
           ON pbm.product_id = p.id AND pbm.branch_id = bp.branch_id AND pbm.tenant_id = p.tenant_id
    WHERE p.tenant_id = ?
      AND (p.id = 104 OR p.name LIKE '%11%')
    ORDER BY p.id, bp.branch_id
");
$stmt2->execute([$tenantId]);
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("  p%d %-20s | branch:%d | qty:%s | map_id:%s | activation:%s\n",
        $r['id'], mb_substr($r['name'],0,18), $r['branch_id'],
        $r['quantity'], $r['mapping_id']??'NULL', $r['activation_status']??'NO_MAPPING');
}
