<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;
$tenantId = 39;

$stmt = $db->prepare("
    SELECT
        it.id          AS tx_id,
        p.name         AS product,
        b.name         AS branch,
        it.movement_type,
        it.reference_type,
        it.reference_id AS it_ref_id,
        pbm.id         AS mapping_id,
        CASE
            WHEN it.reference_id = pbm.id THEN 'OK = mapping_id'
            WHEN it.reference_id IS NULL   THEN 'NULL !!'
            ELSE CONCAT('OTHER: ', it.reference_id)
        END            AS ref_check,
        it.journal_entry_id,
        je.reference_id AS je_ref_id
    FROM inventory_transactions it
    JOIN products p   ON p.id  = it.product_id  AND p.tenant_id  = it.tenant_id
    LEFT JOIN branches b ON b.id = it.branch_to
    LEFT JOIN product_branch_gl_mapping pbm
           ON pbm.tenant_id = it.tenant_id
          AND pbm.product_id = it.product_id
          AND pbm.branch_id  = it.branch_to
    LEFT JOIN journal_entries je ON je.id = it.journal_entry_id
    WHERE it.tenant_id = ?
      AND it.movement_type IN ('opening_balance','initial_stock')
    ORDER BY it.id DESC
");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== reference_id CHECK (inventory_transactions) ===" . PHP_EOL;
printf("%-6s %-22s %-18s %-16s %-16s %-10s %-10s %-22s %-8s %-10s\n",
    'tx_id','product','branch','movement_type','reference_type',
    'it_ref_id','mapping_id','ref_check','JE#','je_ref_id');
echo str_repeat('-', 130) . PHP_EOL;
foreach ($rows as $r) {
    printf("%-6s %-22s %-18s %-16s %-16s %-10s %-10s %-22s %-8s %-10s\n",
        $r['tx_id'],
        mb_substr($r['product'], 0, 20),
        mb_substr($r['branch'] ?? '--', 0, 16),
        $r['movement_type'],
        $r['reference_type'],
        $r['it_ref_id'] ?? 'NULL',
        $r['mapping_id'] ?? 'NULL',
        $r['ref_check'],
        $r['journal_entry_id'] ?? '--',
        $r['je_ref_id'] ?? '--'
    );
}
