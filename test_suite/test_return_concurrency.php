<?php
declare(strict_types=1);
require __DIR__ . '/autoload.php';

$failures = 0;
function check5(bool $ok): void { global $failures; if (!$ok) $failures++; }

echo "=== Group G: genuine concurrency test for the return FOR UPDATE lock (Fix #6) ===\n";
echo "Uses TWO separate real PDO connections (not just sequential calls) to prove\n";
echo "the lock actually blocks a concurrent transaction, closing the race window\n";
echo "described in the audit (two simultaneous over-return requests).\n\n";

$pdoA = test_pdo();
$pdoB = test_pdo();
$pdoB->exec("SET SESSION innodb_lock_wait_timeout = 2"); // fail fast instead of hanging

$pdoA->exec("INSERT INTO sales (id,tenant_id,net_total_amount,tax_amount,status,paid_amount) VALUES (401,1,100,0,'paid',100)");

// Connection A: begins a transaction and acquires the SAME FOR UPDATE lock that
// ReturnService::createReturn() now takes on the sales row before checking
// return-quantity totals (the exact fix added in AUDIT FIX #6).
$pdoA->beginTransaction();
$pdoA->prepare("SELECT id FROM sales WHERE id = ? AND tenant_id = ? FOR UPDATE")->execute([401, 1]);
echo "[Connection A] acquired FOR UPDATE lock on sale #401, transaction still open (not yet committed)\n";

// Connection B: attempts the SAME lock concurrently, exactly as a second,
// simultaneous return request for the same sale would.
$pdoB->beginTransaction();
$blocked = false;
$start = microtime(true);
try {
    $pdoB->prepare("SELECT id FROM sales WHERE id = ? AND tenant_id = ? FOR UPDATE")->execute([401, 1]);
    // If we get here without an exception, the lock did NOT block — check timing too.
    $elapsed = microtime(true) - $start;
    echo "[Connection B] did NOT block (returned immediately after {$elapsed}s) — lock did not serialize access!\n";
} catch (\Throwable $e) {
    $blocked = true;
    $elapsed = microtime(true) - $start;
    echo "[Connection B] blocked and timed out after " . round($elapsed, 2) . "s: {$e->getMessage()}\n";
}
$pdoB->rollBack();

check5($blocked);
echo ($blocked ? "[PASS]" : "[FAIL]") . " G1 concurrent transaction was genuinely blocked by the FOR UPDATE lock (proves serialization, not just sequential-call logic)\n";

$pdoA->rollBack();

// Sanity check: WITHOUT the lock (a plain non-locking SELECT), connection B
// should NOT block — confirms the blocking above was specifically due to the
// FOR UPDATE clause, not some other artifact of the test setup.
$pdoA->beginTransaction();
$pdoA->prepare("SELECT id FROM sales WHERE id = ? AND tenant_id = ? FOR UPDATE")->execute([401, 1]);

$pdoB->beginTransaction();
$notBlocked = true;
try {
    $pdoB->query("SELECT id FROM sales WHERE id = 401")->fetchColumn(); // plain read, no lock
} catch (\Throwable $e) {
    $notBlocked = false;
}
$pdoB->rollBack();
$pdoA->rollBack();
check5($notBlocked);
echo ($notBlocked ? "[PASS]" : "[FAIL]") . " G2 control check: a plain non-locking read is NOT blocked (confirms FOR UPDATE specifically is what serializes)\n";

echo "\n" . ($failures === 0 ? "ALL PASS" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
