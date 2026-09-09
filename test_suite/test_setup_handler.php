<?php
declare(strict_types=1);
require __DIR__ . '/autoload.php';

use App\Handlers\SetupHandler;

$pdo = test_pdo();
$failures = 0;
function check10(bool $ok): void { global $failures; if (!$ok) $failures++; }

echo "=== Group L: SetupHandler::isOwner() tenant-scoping fix ===\n";
echo "Setup: Tenant A's 'owner' role gets the LOWER numeric id (inserted first),\n";
echo "Tenant B's 'owner' role gets a HIGHER numeric id (inserted second) — reproducing\n";
echo "the exact condition that triggered the bug (no ORDER BY / tenant scope on the\n";
echo "role-name subquery, so it resolved to whichever tenant's 'owner' role sorts first).\n\n";

// Tenant A's owner role: inserted first -> lower id
$pdo->exec("INSERT INTO roles (id,tenant_id,name,is_system_role) VALUES (100,1,'owner',1)");
// Tenant B's owner role: inserted second -> higher id
$pdo->exec("INSERT INTO roles (id,tenant_id,name,is_system_role) VALUES (101,2,'owner',1)");

// A legitimate Tenant B owner: correct role_id for THEIR tenant, is_owner flag
// NOT set (reflecting a real-world case where that flag wasn't reliably
// populated on every account — which is exactly what made the bug's impact
// real rather than theoretical).
$pdo->exec("INSERT INTO users (id,tenant_id,username,role_id,is_owner) VALUES (55,2,'tenant_b_owner',101,0)");

$handler = new SetupHandler($pdo);
$ref = new ReflectionMethod(SetupHandler::class, 'isOwner');
$ref->setAccessible(true);

$result = $ref->invoke($handler, 2, 55);
check10($result === true);
echo "[" . ($result === true ? "PASS" : "FAIL") . "] L1 legitimate Tenant B owner (role_id=101, their own tenant's 'owner' role) is correctly recognized (was FALSE under the bug, since the unscoped subquery resolved to Tenant A's role_id=100)\n";

// Control: a Tenant B user who is genuinely NOT the owner must still be denied.
$pdo->exec("INSERT INTO users (id,tenant_id,username,role_id,is_owner) VALUES (56,2,'tenant_b_employee',999,0)");
$result2 = $ref->invoke($handler, 2, 56);
check10($result2 === false);
echo "[" . ($result2 === false ? "PASS" : "FAIL") . "] L2 control: a genuine non-owner in Tenant B is still correctly denied\n";

// Control: the is_owner=1 flag path still works independently (defense in depth).
$pdo->exec("INSERT INTO users (id,tenant_id,username,role_id,is_owner) VALUES (57,2,'tenant_b_flagged_owner',999,1)");
$result3 = $ref->invoke($handler, 2, 57);
check10($result3 === true);
echo "[" . ($result3 === true ? "PASS" : "FAIL") . "] L3 control: is_owner=1 flag path still grants access independently of role_id\n";

echo "\n" . ($failures === 0 ? "ALL PASS" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
