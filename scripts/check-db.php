<?php
/**
 * Database Tables Check
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$pdo = $db->pdo;

try {
    echo "🔍 Checking database tables...\n\n";

    // List all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Tables found: " . count($tables) . "\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "  • $table: $count records\n";
    }

    echo "\n✅ Check completed!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
