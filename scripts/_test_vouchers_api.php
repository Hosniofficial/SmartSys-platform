<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db  = (new Database())->pdo;
$tid = 44;

// ── Direct query same as getList ─────────────────────────────────────────────
echo "=== DIRECT QUERY (same as getList) ===" . PHP_EOL;
$sql = "
    SELECT cv.*,
           COALESCE(c.name, s.name, a.name, '-') AS account_name
    FROM cash_vouchers cv
    LEFT JOIN customers c  ON c.id = cv.customer_id  AND c.tenant_id  = cv.tenant_id
    LEFT JOIN suppliers s  ON s.id = cv.supplier_id  AND s.tenant_id  = cv.tenant_id
    LEFT JOIN accounts  a  ON a.id = cv.account_id   AND a.tenant_id  = cv.tenant_id
    WHERE cv.tenant_id = :tenant_id
    ORDER BY cv.date DESC, cv.id DESC
    LIMIT 10
";
$stmt = $db->prepare($sql);
$stmt->execute([':tenant_id' => $tid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

printf("  Found %d vouchers:\n", count($rows));
foreach ($rows as $r) {
    printf("  [#%d] type:%-10s | amount:%-10s | customer_id:%-5s | supplier_id:%-5s | account_name:%s\n",
        $r['id'], $r['type'], $r['amount'],
        $r['customer_id'] ?? 'NULL', $r['supplier_id'] ?? 'NULL',
        $r['account_name'] ?? '??'
    );
}

// ── Check cash_vouchers columns ───────────────────────────────────────────────
echo PHP_EOL . "=== cash_vouchers TABLE COLUMNS ===" . PHP_EOL;
$cols = $db->query("DESCRIBE cash_vouchers")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    printf("  %-20s | %-15s | null:%-4s | key:%-5s | default:%s\n",
        $c['Field'], $c['Type'], $c['Null'], $c['Key'], $c['Default'] ?? 'NULL');
}

// ── Simulate decorateVoucherRow (check 'description' field) ──────────────────
echo PHP_EOL . "=== CHECKING description/notes FIELDS ===" . PHP_EOL;
foreach ($rows as $r) {
    printf("  [#%d] notes:%-30s | description:%s\n",
        $r['id'],
        mb_substr($r['notes'] ?? 'NULL', 0, 30),
        mb_substr($r['description'] ?? 'NO_DESCRIPTION_COL', 0, 30)
    );
}
