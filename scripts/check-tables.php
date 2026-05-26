<?php
/**
 * Check Table Structure
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$pdo = $db->pdo;

try {
    echo "🔍 Table Structures:\n\n";

    // Check tenants table
    echo "📋 TENANTS table columns:\n";
    $stmt = $pdo->query("DESCRIBE tenants");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  • {$col['Field']} ({$col['Type']})\n";
    }

    echo "\n👤 USERS table columns:\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  • {$col['Field']} ({$col['Type']})\n";
    }

    echo "\n\n📊 Sample Users Data:\n";
    $stmt = $pdo->query("SELECT * FROM users LIMIT 3");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        echo "  • Email: {$u['email']}\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
