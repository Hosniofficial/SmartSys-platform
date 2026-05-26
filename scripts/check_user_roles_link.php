<?php
/**
 * Check if users are properly linked in users_role table vs users.role_id
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db  = new Database();
$pdo = $db->pdo;

try {
    // Check users_role table structure
    echo "=== DESCRIBE users_role ===\n";
    foreach ($pdo->query("DESCRIBE users_role")->fetchAll(PDO::FETCH_ASSOC) as $c)
        echo "  {$c['Field']} ({$c['Type']})\n";

    // Users with their role_id from users table
    echo "\n=== users.role_id (tenant_id=44) ===\n";
    $stmt = $pdo->query("SELECT id, username, role_id FROM users WHERE tenant_id = 44 ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u)
        echo "  user_id={$u['id']} username={$u['username']} role_id={$u['role_id']}\n";

    // What's in users_role for these users
    echo "\n=== users_role entries for tenant_id=44 users ===\n";
    $stmt = $pdo->query("
        SELECT ur.user_id, u.username, ur.role_id, r.name AS role_name
        FROM users_role ur
        JOIN users u ON ur.user_id = u.id
        JOIN roles  r ON ur.role_id = r.id
        WHERE u.tenant_id = 44
        ORDER BY ur.user_id
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "  ❌ NO ROWS — users_role is EMPTY for tenant 44!\n";
    } else {
        foreach ($rows as $r)
            echo "  user_id={$r['user_id']} username={$r['username']} role_id={$r['role_id']} role={$r['role_name']}\n";
    }

    // Test hasPermission query directly for the first user
    if (!empty($users)) {
        $testUserId = $users[0]['id'];
        echo "\n=== hasPermission test: user_id={$testUserId} perm=sales.approval.approve ===\n";
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS has_permission
            FROM users u
            JOIN users_role ur ON u.id = ur.user_id
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.name = ?
        ");
        $stmt->execute([$testUserId, 'sales.approval.approve']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  Result: " . ($result['has_permission'] > 0 ? "✅ HAS permission" : "❌ NO permission") . "\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
