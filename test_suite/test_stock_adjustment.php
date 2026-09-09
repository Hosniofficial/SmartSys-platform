<?php
declare(strict_types=1);
require __DIR__ . '/autoload.php';

use App\Handlers\StockAdjustmentHandler;

$pdo = test_pdo();
$failures = 0;
function check4(bool $ok): void { global $failures; if (!$ok) $failures++; }

echo "=== Group F: StockAdjustmentHandler — throw-on-missing-accounts (Fix #8) ===\n";

// Reflection is used only to reach the PRIVATE createAdjustmentJournalEntry()
// method directly — the method body itself is the real, unmodified fixed code.
$handler = new StockAdjustmentHandler($pdo);
$ref     = new ReflectionMethod(StockAdjustmentHandler::class, 'createAdjustmentJournalEntry');
$ref->setAccessible(true);

// F1: zero-amount case (no unit cost known) — must still return null cleanly,
// NOT throw. This is the one legitimate silent no-op case (Dr=Cr=0 has no
// accounting value), and the fix must not have made this case throw too.
$pdo->exec("INSERT INTO products (id,tenant_id,name,purchase_price) VALUES (99,1,'Zero-cost product',0)");
try {
    $result = $ref->invoke($handler, 1, 1, 99, 5.0, 'test note', 1, 'adjustment');
    check4($result === null);
    echo (($result === null) ? "[PASS]" : "[FAIL]") . " F1 zero-cost product returns null cleanly (no exception, no JE)\n";
} catch (\Throwable $e) {
    check4(false);
    echo "[FAIL] F1 zero-cost product unexpectedly threw: {$e->getMessage()}\n";
}

// F2: non-zero amount, but tenant 2 has NO accounts configured at all (a
// realistic "misconfigured tenant" scenario). Before Fix #8 this silently
// returned null and the caller committed the stock mutation anyway with zero
// GL trace. After the fix, it must THROW instead.
$pdo->exec("INSERT INTO products (id,tenant_id,name,purchase_price) VALUES (100,2,'Real product',10.00)");
$threw = false;
$msg   = '';
try {
    $ref->invoke($handler, 2 /* tenant with no accounts seeded */, 1, 100, 5.0, 'test note', 1, 'adjustment');
} catch (\Throwable $e) {
    $threw = true;
    $msg   = $e->getMessage();
}
check4($threw);
echo ($threw ? "[PASS]" : "[FAIL]") . " F2 missing-accounts scenario now THROWS instead of silently returning null" . ($threw ? " (message: {$msg})" : "") . "\n";

$jeCountAfterThrow = (int) $pdo->query("SELECT COUNT(*) FROM journal_entries WHERE tenant_id=2")->fetchColumn();
check4($jeCountAfterThrow === 0);
echo (($jeCountAfterThrow === 0) ? "[PASS]" : "[FAIL]") . " F2 no partial journal_entries row was left behind after the throw\n";

// F3: non-zero amount, tenant 1 (accounts fully configured, matching seed.sql)
// -> must succeed, return a real journal_entry_id, and post a BALANCED entry.
$pdo->exec("INSERT INTO products (id,tenant_id,name,purchase_price) VALUES (101,1,'Tenant-1 product',10.00)");
$result3 = $ref->invoke($handler, 1, 1, 101, 5.0, 'test note', 1, 'adjustment');
check4(is_int($result3) && $result3 > 0);
echo ((is_int($result3) && $result3 > 0) ? "[PASS]" : "[FAIL]") . " F3 properly-configured tenant succeeds, returns journal_entry_id={$result3}\n";

$lines = $pdo->query("SELECT SUM(debit_amount) AS d, SUM(credit_amount) AS c FROM journal_entry_lines WHERE journal_entry_id={$result3}")->fetch(PDO::FETCH_ASSOC);
check4(assert_close((float)$lines['d'], (float)$lines['c'], 'F3 the posted journal entry is balanced (Dr = Cr)'));
check4(assert_close((float)$lines['d'], 50.0, 'F3 amount = 5 units x $10 purchase_price fallback = $50.00'));

echo "\n" . ($failures === 0 ? "ALL PASS" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
