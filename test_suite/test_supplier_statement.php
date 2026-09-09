<?php
declare(strict_types=1);
require __DIR__ . '/autoload.php';

use App\Services\PurchaseService;
use App\Handlers\AccountStatementHandler;

$pdo = test_pdo();
$failures = 0;
function check7(bool $ok): void { global $failures; if (!$ok) $failures++; }

echo "=== Group I: purchase returns now correctly adjust purchase status (new finding) ===\n";
echo "Before this fix: ReturnService::createReturn() never updated purchases.paid_amount/status\n";
echo "at all for purchase returns — the GL correctly reduced Accounts Payable, but the supplier\n";
echo "statement kept showing the full original amount as outstanding forever.\n\n";

$pdo->exec("INSERT INTO suppliers (id,tenant_id,name) VALUES (1,1,'Test Supplier')");

$ps = new PurchaseService($pdo, 1, 1);

// I1: FULL return, no payment -> 'returned'
$pdo->exec("INSERT INTO purchases (id,tenant_id,supplier_id,invoice_date,total_amount,net_total_amount,tax_amount,paid_amount,status) VALUES (601,1,1,'2026-05-01',1000,1000,0,0,'due')");
$pdo->exec("INSERT INTO returns (id,tenant_id,purchase_id,return_type,grand_total,created_at) VALUES (20,1,601,'purchase',1000,'2026-05-05')");
$ps->recalculateBalance(601);
$s1 = $pdo->query("SELECT status, paid_amount FROM purchases WHERE id=601")->fetch(PDO::FETCH_ASSOC);
check7(assert_eq($s1['status'], 'returned', 'I1 full return -> status=returned'));

// I2: PARTIAL return, no payment -> outstanding correctly reduced, status stays 'due'
// (matches the audit's own worked example: $1000 purchase, $300 returned, $0 paid -> owe $700, not $1000)
$pdo->exec("INSERT INTO purchases (id,tenant_id,supplier_id,invoice_date,total_amount,net_total_amount,tax_amount,paid_amount,status) VALUES (602,1,1,'2026-05-01',1000,1000,0,0,'due')");
$pdo->exec("INSERT INTO returns (id,tenant_id,purchase_id,return_type,grand_total,created_at) VALUES (21,1,602,'purchase',300,'2026-05-05')");
$ps->recalculateBalance(602);
$s2 = $pdo->query("SELECT status, paid_amount FROM purchases WHERE id=602")->fetch(PDO::FETCH_ASSOC);
check7(assert_eq($s2['status'], 'due', 'I2 partial return, no payment -> status stays due (700 still genuinely owed)'));
$returned2 = $ps->getReturnedAmount(602);
check7(assert_close($returned2, 300.0, 'I2 getReturnedAmount correctly reports 300'));

// I3: PARTIAL return (300) + payment covering the rest (700) -> fully settled, mixed
$pdo->exec("INSERT INTO purchases (id,tenant_id,supplier_id,invoice_date,total_amount,net_total_amount,tax_amount,paid_amount,status) VALUES (603,1,1,'2026-05-01',1000,1000,0,0,'due')");
$pdo->exec("INSERT INTO returns (id,tenant_id,purchase_id,return_type,grand_total,created_at) VALUES (22,1,603,'purchase',300,'2026-05-05')");
$pdo->exec("INSERT INTO payments (tenant_id,purchase_id,amount,payment_date,payment_method_id,status,is_draft,type) VALUES (1,603,700,'2026-05-06 10:00:00',1,'completed',0,'purchase')");
$ps->recalculateBalance(603);
$s3 = $pdo->query("SELECT status, paid_amount FROM purchases WHERE id=603")->fetch(PDO::FETCH_ASSOC);
check7(assert_eq($s3['status'], 'settled_mixed', 'I3 partial return + payment covering rest -> settled_mixed'));
check7(assert_close((float)$s3['paid_amount'], 700.0, 'I3 paid_amount = 700 (actual cash only, return not counted as cash)'));

// I4: partial return, NO payment, doesn't fully cover -> 'due' with correct outstanding
$pdo->exec("INSERT INTO purchases (id,tenant_id,supplier_id,invoice_date,total_amount,net_total_amount,tax_amount,paid_amount,status) VALUES (604,1,1,'2026-05-01',1000,1000,0,0,'due')");
$pdo->exec("INSERT INTO returns (id,tenant_id,purchase_id,return_type,grand_total,created_at) VALUES (23,1,604,'purchase',300,'2026-05-05')");
$pdo->exec("INSERT INTO payments (tenant_id,purchase_id,amount,payment_date,payment_method_id,status,is_draft,type) VALUES (1,604,200,'2026-05-06 10:00:00',1,'completed',0,'purchase')");
$ps->recalculateBalance(604);
$s4 = $pdo->query("SELECT status, paid_amount FROM purchases WHERE id=604")->fetch(PDO::FETCH_ASSOC);
check7(assert_eq($s4['status'], 'partial', 'I4 return + partial payment, still short -> partial'));

echo "\n--- Group I2: AccountStatementHandler supplier statement reflects the fix ---\n";

// Verify via Reflection (private method) that the supplier statement itself
// now reports the correct returned_amount and normalized status, matching
// what PurchaseService just computed independently above.
$handler = new AccountStatementHandler($pdo);
$ref = new ReflectionMethod(AccountStatementHandler::class, 'getSupplierReferences');
$ref->setAccessible(true);
$refs = $ref->invoke($handler, 1, '2026-05-01', '2026-05-31', 1);

$byId = [];
foreach ($refs['items'] as $item) {
    if (($item['type'] ?? null) === 'purchase') {
        $byId[$item['id']] = $item;
    }
}

check7(isset($byId[602]) && assert_close($byId[602]['returned_amount'], 300.0, 'I5 statement reports returned_amount=300 for purchase #602'));
check7(isset($byId[602]) && assert_eq($byId[602]['status'], 'due', 'I5 statement normalized status = due (matches PurchaseService)'));
check7(isset($byId[603]) && assert_eq($byId[603]['status'], 'settled_mixed', 'I5 statement normalized status = settled_mixed for #603'));
check7(isset($byId[601]) && assert_eq($byId[601]['status'], 'returned', 'I5 statement normalized status = returned for #601'));

echo "\n" . ($failures === 0 ? "ALL PASS" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
