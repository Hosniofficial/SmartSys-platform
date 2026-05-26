<?php
/**
 * Sync users_role from users.role_id
 * Fixes: users who have role_id set in users table but no entry in users_role junction table
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db  = new Database();
$pdo = $db->pdo;

try {
    // Find all users who have role_id set but missing from users_role
    $stmt = $pdo->query("
        SELECT u.id AS user_id, u.username, u.role_id, u.tenant_id, r.name AS role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN users_role ur ON ur.user_id = u.id AND ur.role_id = u.role_id
        WHERE u.role_id IS NOT NULL
          AND ur.user_id IS NULL
        ORDER BY u.tenant_id, u.id
    ");
    $missing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== Users missing from users_role ===\n";
    if (empty($missing)) {
        echo "  ✅ None — all users already synced.\n";
        exit(0);
    }

    foreach ($missing as $u)
        echo "  user_id={$u['user_id']} username={$u['username']} role={$u['role_name']} tenant_id={$u['tenant_id']}\n";

    echo "\nInserting " . count($missing) . " rows into users_role...\n";

    $pdo->beginTransaction();

    $insert = $pdo->prepare("
        INSERT INTO users_role (user_id, role_id, created_at, created_by, tenant_id)
        VALUES (?, ?, NOW(), NULL, ?)
    ");

    $count = 0;
    foreach ($missing as $u) {
        $insert->execute([$u['user_id'], $u['role_id'], $u['tenant_id']]);
        echo "  ✅ Linked user_id={$u['user_id']} ({$u['username']}) → role={$u['role_name']}\n";
        $count++;
    }

    $pdo->commit();
    echo "\n✅ Done. Synced {$count} users.\n";

    // Verify
    echo "\n=== Verification: hasPermission test for fixed users ===\n";
    foreach ($missing as $u) {
        $check = $pdo->prepare("
            SELECT COUNT(*) AS has_perm
            FROM users u2
            JOIN users_role ur ON u2.id = ur.user_id
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u2.id = ? AND p.name = 'sales.approval.approve'
        ");
        $check->execute([$u['user_id']]);
        $res = $check->fetch(PDO::FETCH_ASSOC);
        $ok  = (int)$res['has_perm'] > 0;
        echo "  user={$u['username']} sales.approval.approve → " . ($ok ? "✅ OK" : "❌ STILL MISSING") . "\n";
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
