<?php
/**
 * List Available Users for Testing
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$pdo = $db->pdo;

try {
    echo "👥 Available Users & Tenants:\n\n";

    // Get tenants
    $stmt = $pdo->query("SELECT id, name, slug, active FROM tenants LIMIT 5");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📌 Tenants (showing 5):\n";
    foreach ($tenants as $t) {
        echo "  • ID={$t['id']}, Name={$t['name']}, Slug={$t['slug']}, Active={$t['active']}\n";
    }

    echo "\n👤 Users (showing 10):\n";
    
    // Get users with tenant info
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.tenant_id, u.active, t.name as tenant_name
        FROM users u
        LEFT JOIN tenants t ON t.id = u.tenant_id
        LIMIT 10
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $u) {
        echo "  • Email={$u['email']}, Name={$u['first_name']} {$u['last_name']}, Tenant={$u['tenant_name']}, Active={$u['active']}\n";
    }

    echo "\n\n💡 Try logging in with:\n";
    if (!empty($users)) {
        $firstUser = $users[0];
        echo "   Email: {$firstUser['email']}\n";
        echo "   (Password: check your records)\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
