<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db  = new Database();
$pdo = $db->pdo;

$userId = 58;

echo "=== users_role for user_id={$userId} ===\n";
$stmt = $pdo->prepare("SELECT * FROM users_role WHERE user_id = ?");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "  ❌ EMPTY — users_role still has no entry!\n";
} else {
    foreach ($rows as $r) echo "  " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== hasPermission query result ===\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS has_permission
    FROM users u
    JOIN users_role ur ON u.id = ur.user_id
    JOIN role_permissions rp ON ur.role_id = rp.role_id
    JOIN permissions p ON rp.permission_id = p.id
    WHERE u.id = ? AND p.name = ?
");
$stmt->execute([$userId, 'sales.approval.approve']);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Result: " . ($r['has_permission'] > 0 ? "✅ HAS permission" : "❌ NO permission") . " (count={$r['has_permission']})\n";

echo "\n=== Check \$this->rbac initialization in SalesHandler ===\n";
// Check if RBACHandler class exists and can be instantiated
if (class_exists('App\Handlers\RBACHandler')) {
    echo "  ✅ RBACHandler class found\n";
    try {
        $rbac = new App\Handlers\RBACHandler($pdo);
        $result = $rbac->hasPermission($userId, 'sales.approval.approve');
        echo "  rbac->hasPermission({$userId}, 'sales.approval.approve') = " . ($result ? "✅ true" : "❌ false") . "\n";
    } catch (Throwable $e) {
        echo "  ❌ RBACHandler error: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ❌ RBACHandler class NOT found — check namespace\n";
}
