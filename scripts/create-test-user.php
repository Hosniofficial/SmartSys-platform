<?php
/**
 * Create Test User Account
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$pdo = $db->pdo;

try {
    echo "🔧 Creating Test User...\n\n";

    // Get first active tenant
    $stmt = $pdo->query("SELECT id FROM tenants WHERE status = 'active' LIMIT 1");
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        throw new Exception("No active tenant found!");
    }
    
    $tenantId = $tenant['id'];
    echo "✓ Using Tenant ID: $tenantId\n";

    // Check if test user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ?");
    $stmt->execute(['test@smartsys.local', $tenantId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✓ Test user already exists\n";
    } else {
        // Create test user
        $password = password_hash('password123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users (tenant_id, username, email, name, password, role_id, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        // Assuming role_id 1 is admin/owner
        $result = $stmt->execute([
            $tenantId,
            'test_user',
            'test@smartsys.local',
            'Test User',
            $password,
            1,  // role_id (admin)
            'active'
        ]);
        
        if ($result) {
            echo "✅ Test user created successfully!\n";
        }
    }

    echo "\n📝 Login Credentials:\n";
    echo "   Email: test@smartsys.local\n";
    echo "   Password: password123\n";
    echo "   Tenant ID: $tenantId\n";
    
    echo "\n✅ You can now try logging in!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
