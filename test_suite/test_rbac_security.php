<?php
declare(strict_types=1);
require __DIR__ . '/autoload.php';
require __DIR__ . '/stubs/psr7_stubs.php';

use App\Handlers\RBACHandler;

$pdo = test_pdo();
$failures = 0;
function check9(bool $ok): void { global $failures; if (!$ok) $failures++; }

echo "=== Group K: RBAC security fix — global permissions catalog now requires true super_admin ===\n";
echo "Before this fix: any ordinary tenant's own 'admin'-role user could create/edit/delete\n";
echo "entries in the 'permissions' table — a single catalog shared across ALL tenants on the\n";
echo "platform (confirmed: the table has no tenant_id column at all).\n\n";

$handler = new RBACHandler($pdo);

// ── K1: an ordinary tenant admin (role='admin', NOT super_admin) attempting to
// create a new global permission -> must now be REJECTED (403).
$tenantAdminUser = ['id' => 42, 'role_id' => 5, 'role' => 'admin'];
$req = new StubServerRequest(
    ['user' => $tenantAdminUser],
    [],
    ['name' => 'malicious.permission', 'category' => 'test']
);
$res = $handler->createPermission($req, new StubResponse());
check9($res->getStatusCode() === 403);
echo "[" . ($res->getStatusCode() === 403 ? "PASS" : "FAIL") . "] K1 ordinary tenant 'admin' role blocked from createPermission (status=" . $res->getStatusCode() . ", expected 403)\n";

$countAfterK1 = (int) $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
check9($countAfterK1 === 0);
echo "[" . ($countAfterK1 === 0 ? "PASS" : "FAIL") . "] K1b no row was actually inserted into the global permissions table\n";

// ── K2: same tenant-admin user attempting updatePermission/deletePermission on
// an existing permission -> also rejected.
$pdo->exec("INSERT INTO permissions (id,name,category) VALUES (1,'sales.create','sales')");

$req2 = new StubServerRequest(['user' => $tenantAdminUser], [], ['name' => 'renamed']);
$res2 = $handler->updatePermission($req2, new StubResponse(), ['id' => 1]);
check9($res2->getStatusCode() === 403);
echo "[" . ($res2->getStatusCode() === 403 ? "PASS" : "FAIL") . "] K2 ordinary tenant 'admin' blocked from updatePermission (status=" . $res2->getStatusCode() . ")\n";

$nameUnchanged = $pdo->query("SELECT name FROM permissions WHERE id=1")->fetchColumn();
check9(assert_eq($nameUnchanged, 'sales.create', 'K2b permission name unchanged after blocked attempt'));

$req3 = new StubServerRequest(['user' => $tenantAdminUser]);
$res3 = $handler->deletePermission($req3, new StubResponse(), ['id' => 1]);
check9($res3->getStatusCode() === 403);
echo "[" . ($res3->getStatusCode() === 403 ? "PASS" : "FAIL") . "] K3 ordinary tenant 'admin' blocked from deletePermission (status=" . $res3->getStatusCode() . ")\n";

$stillExists = (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE id=1")->fetchColumn();
check9($stillExists === 1);
echo "[" . ($stillExists === 1 ? "PASS" : "FAIL") . "] K3b permission #1 was NOT deleted\n";

// ── K4: a TRUE platform super_admin performing the same actions -> must still
// succeed (the fix must not have locked out legitimate platform operators).
$superAdminUser = ['id' => 1, 'role_id' => 1, 'role' => 'super_admin'];
$req4 = new StubServerRequest(
    ['user' => $superAdminUser],
    [],
    ['name' => 'legit.permission', 'category' => 'test']
);
$res4 = $handler->createPermission($req4, new StubResponse());
check9($res4->getStatusCode() === 200);
echo "[" . ($res4->getStatusCode() === 200 ? "PASS" : "FAIL") . "] K4 true super_admin CAN still create a permission (status=" . $res4->getStatusCode() . ", expected 200)\n";

$countAfterK4 = (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE name='legit.permission'")->fetchColumn();
check9($countAfterK4 === 1);
echo "[" . ($countAfterK4 === 1 ? "PASS" : "FAIL") . "] K4b legitimate permission was actually inserted\n";

// ── K5: control check — updateRole (a legitimately tenant-scoped resource) must
// remain reachable by an ordinary tenant admin, confirming the fix did NOT
// over-broadly lock down unrelated, correctly-scoped endpoints.
$pdo->exec("INSERT INTO roles (id,tenant_id,name,is_system_role) VALUES (10,7,'Custom Role',0)");
$req5 = new StubServerRequest([
    'user'      => ['id' => 42, 'role_id' => 5, 'role' => 'admin'],
    'tenant_id' => 7,
], [], ['name' => 'Renamed Role']);
$res5 = $handler->updateRole($req5, new StubResponse(), ['id' => 10]);
check9($res5->getStatusCode() === 200);
echo "[" . ($res5->getStatusCode() === 200 ? "PASS" : "FAIL") . "] K5 control check: ordinary tenant admin CAN still update their own tenant's custom role (status=" . $res5->getStatusCode() . ", expected 200) — confirms the fix is narrowly scoped, not an over-broad lockdown\n";

$roleNameAfter = $pdo->query("SELECT name FROM roles WHERE id=10")->fetchColumn();
check9(assert_eq($roleNameAfter, 'Renamed Role', 'K5b role was actually renamed (legitimate tenant-scoped action still works)'));

echo "\n" . ($failures === 0 ? "ALL PASS" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
