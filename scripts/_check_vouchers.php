<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db  = (new Database())->pdo;
$tid = 44;

// ── 1. Latest cash vouchers ──────────────────────────────────────────────────
echo "=== CASH VOUCHERS (last 10) ===" . PHP_EOL;
$rows = $db->query("
    SELECT cv.id, cv.type, cv.date, cv.amount, cv.currency,
           cv.customer_id, cv.supplier_id, cv.journal_entry_id,
           c.name  AS customer_name,
           s.name  AS supplier_name,
           a.name  AS account_name,
           cv.notes
    FROM cash_vouchers cv
    LEFT JOIN customers c ON c.id  = cv.customer_id AND c.tenant_id = cv.tenant_id
    LEFT JOIN suppliers s ON s.id  = cv.supplier_id AND s.tenant_id = cv.tenant_id
    LEFT JOIN accounts  a ON a.id  = cv.account_id  AND a.tenant_id = cv.tenant_id
    WHERE cv.tenant_id = $tid
    ORDER BY cv.id DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    printf(
        "  [#%d] type:%-10s | amount:%-10s | customer:%-15s | supplier:%-15s | account:%-22s | JE:%s\n",
        $r['id'], $r['type'], $r['amount'],
        $r['customer_name'] ?? '-',
        $r['supplier_name'] ?? '-',
        $r['account_name']  ?? '-',
        $r['journal_entry_id'] ? 'JE#' . $r['journal_entry_id'] : 'NO_JE'
    );
}

// ── 2. JE lines for latest voucher ──────────────────────────────────────────
echo PHP_EOL . "=== JE LINES for latest voucher ===" . PHP_EOL;
if (!empty($rows) && $rows[0]['journal_entry_id']) {
    $jeId  = (int) $rows[0]['journal_entry_id'];
    $lines = $db->query("
        SELECT jel.id, a.code, a.name AS aname,
               jel.debit_amount, jel.credit_amount, jel.description
        FROM journal_entry_lines jel
        LEFT JOIN accounts a ON a.id = jel.account_id
        WHERE jel.journal_entry_id = $jeId AND jel.tenant_id = $tid
        ORDER BY jel.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $dr = array_sum(array_column($lines, 'debit_amount'));
    $cr = array_sum(array_column($lines, 'credit_amount'));
    foreach ($lines as $l) {
        printf("  [%s] %-30s | Dr:%-10s | Cr:%-10s | %s\n",
            $l['code'] ?? '----', $l['aname'] ?? '??',
            $l['debit_amount']  ?: '-',
            $l['credit_amount'] ?: '-',
            mb_substr($l['description'] ?? '', 0, 50));
    }
    printf("  --- TOTALS: Dr=%-10s Cr=%-10s %s\n",
        $dr, $cr, abs($dr - $cr) < 0.01 ? '✓ BALANCED' : '!! UNBALANCED !!');
} else {
    echo "  (لا يوجد قيد مرتبط بالسند الأخير)" . PHP_EOL;
}

// ── 3. Chart of accounts for tenant 44 ──────────────────────────────────────
echo PHP_EOL . "=== ACCOUNTS (tenant $tid) ===" . PHP_EOL;
$accs = $db->query("
    SELECT id, code, name, type, is_active
    FROM accounts
    WHERE tenant_id = $tid
    ORDER BY code
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($accs as $a) {
    printf("  [id:%-4d] %-14s %-35s type:%-15s active:%d\n",
        $a['id'], $a['code'], $a['name'],
        $a['type'] ?? '-', $a['is_active'] ?? 1);
}
