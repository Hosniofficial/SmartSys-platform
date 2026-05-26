<?php
/**
 * يصحّح reference_id القديم في inventory_transactions
 * يربط كل سجل بـ mapping_id الخاص به بدلاً من NULL أو purchase_id
 * آمن للتشغيل مرة واحدة فقط
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;
$tenantId = 39;

$updated = $db->prepare("
    UPDATE inventory_transactions it
    JOIN product_branch_gl_mapping pbm
        ON  pbm.tenant_id  = it.tenant_id
        AND pbm.product_id = it.product_id
        AND pbm.branch_id  = it.branch_to
    SET it.reference_id = pbm.id
    WHERE it.tenant_id     = ?
      AND it.movement_type IN ('opening_balance','initial_stock')
      AND (it.reference_id IS NULL OR it.reference_id != pbm.id)
");
$updated->execute([$tenantId]);
$count = $updated->rowCount();
echo "Updated {$count} inventory_transactions rows → reference_id = mapping_id" . PHP_EOL;
