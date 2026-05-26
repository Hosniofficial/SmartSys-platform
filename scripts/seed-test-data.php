<?php
/**
 * Seed Test Data Script
 * إضافة بيانات اختبار أساسية لتطوير المشروع
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

use Firebase\JWT\JWT;

$db = new Database();
$pdo = $db->pdo;

try {
    echo "🔄 Seeding test data...\n\n";

    // 1. Check if test tenant exists
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE name = 'Test Tenant' LIMIT 1");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo "➕ Creating test tenant...\n";
        $stmt = $pdo->prepare("
            INSERT INTO tenants (name, slug, email, phone, active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            'Test Tenant',
            'test-tenant',
            'test@smartsys.local',
            '+966501234567',
            1
        ]);
        $tenantId = $pdo->lastInsertId();
        echo "✅ Tenant created: ID=$tenantId\n";
    } else {
        $tenantId = $tenant['id'];
        echo "✓ Tenant exists: ID=$tenantId\n";
    }

    // 2. Check if test user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'test@example.com' AND tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "➕ Creating test user...\n";
        $password = password_hash('password123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users (tenant_id, first_name, last_name, email, password, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenantId,
            'Test',
            'User',
            'test@example.com',
            $password,
            1
        ]);
        $userId = $pdo->lastInsertId();
        echo "✅ User created: ID=$userId, Email=test@example.com, Password=password123\n";
    } else {
        $userId = $user['id'];
        echo "✓ User exists: ID=$userId\n";
    }

    // 3. Create default branch
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE tenant_id = ? AND name = 'Main Branch' LIMIT 1");
    $stmt->execute([$tenantId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        echo "➕ Creating default branch...\n";
        $stmt = $pdo->prepare("
            INSERT INTO branches (tenant_id, name, code, address, phone, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenantId,
            'Main Branch',
            'MAIN',
            'Main Office',
            '+966501234567',
            1
        ]);
        $branchId = $pdo->lastInsertId();
        echo "✅ Branch created: ID=$branchId\n";
    } else {
        $branchId = $branch['id'];
        echo "✓ Branch exists: ID=$branchId\n";
    }

    // 4. Create default cost center
    $stmt = $pdo->prepare("SELECT id FROM cost_centers WHERE tenant_id = ? AND name = 'Default' LIMIT 1");
    $stmt->execute([$tenantId]);
    $costCenter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$costCenter) {
        echo "➕ Creating default cost center...\n";
        $stmt = $pdo->prepare("
            INSERT INTO cost_centers (tenant_id, name, code, description, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenantId,
            'Default',
            'DEFAULT',
            'Default Cost Center',
            1
        ]);
        $costCenterId = $pdo->lastInsertId();
        echo "✅ Cost Center created: ID=$costCenterId\n";
    } else {
        $costCenterId = $costCenter['id'];
        echo "✓ Cost Center exists: ID=$costCenterId\n";
    }

    echo "\n✅ Test data seeding completed!\n";
    echo "\n📝 Login Credentials:\n";
    echo "   Email: test@example.com\n";
    echo "   Password: password123\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
