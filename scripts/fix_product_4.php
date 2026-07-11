<?php
/**
 * Fix Product 4 - Set opening_balance_posted flag
 * 
 * This script sets opening_balance_posted = 1 for product 4
 * to ensure it displays as 'posted' in the GL status
 */

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
} else {
    $env = $_ENV;
}

try {
    echo "=== Fixing Product 4 GL Status ===\n";
    
    // Get database connection
    $host = $env['DB_HOST'] ?? 'localhost';
    $port = $env['DB_PORT'] ?? 3306;
    $database = $env['DB_DATABASE'] ?? 'inventory';
    $user = $env['DB_USERNAME'] ?? 'root';
    $password = $env['DB_PASSWORD'] ?? '';
    
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Product ID to fix
    // The user referred to "Product 4" (منتج 4) which has ID 122 in tenant 47
    $productId = 122;
    $tenantId = 47;
    
    echo "Fixing Product (منتج 4), ID: $productId, Tenant: $tenantId\n";
    
    // Check current status
    echo "\nBefore fix:\n";
    $checkBefore = $db->prepare("
        SELECT id, name, opening_balance_posted, created_at
        FROM products
        WHERE id = ? AND tenant_id = ?
    ");
    $checkBefore->execute([$productId, $tenantId]);
    $productBefore = $checkBefore->fetch(PDO::FETCH_ASSOC);
    
    if ($productBefore) {
        echo "- Product ID: {$productBefore['id']}\n";
        echo "- Name: {$productBefore['name']}\n";
        echo "- opening_balance_posted: {$productBefore['opening_balance_posted']} (currently: " . 
             ($productBefore['opening_balance_posted'] ? 'posted' : 'not posted') . ")\n";
        echo "- Created: {$productBefore['created_at']}\n";
    } else {
        echo "❌ Product 4 not found with tenant_id $tenantId\n";
        exit(1);
    }
    
    // Check GL mapping status
    echo "\nGL Mapping Status:\n";
    $glCheck = $db->prepare("
        SELECT product_id, branch_id, gl_reconciliation_status
        FROM product_branch_gl_mapping
        WHERE product_id = ? AND branch_id = 48
    ");
    $glCheck->execute([$productId]);
    $glMappings = $glCheck->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($glMappings) > 0) {
        foreach ($glMappings as $mapping) {
            echo "- Product {$mapping['product_id']}, Branch {$mapping['branch_id']}: {$mapping['gl_reconciliation_status']}\n";
        }
    } else {
        echo "- No GL mappings found\n";
    }
    
    // Now fix the flag
    echo "\n--- Applying Fix ---\n";
    $fix = $db->prepare("
        UPDATE products
        SET opening_balance_posted = 1,
            updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $fix->execute([$productId, $tenantId]);
    
    if ($fix->rowCount() > 0) {
        echo "✅ Updated product $productId: opening_balance_posted = 1\n";
    } else {
        echo "⚠️  No rows updated (product may not exist or already has flag)\n";
    }
    
    // Verify after fix
    echo "\nAfter fix:\n";
    $checkAfter = $db->prepare("
        SELECT id, name, opening_balance_posted, updated_at
        FROM products
        WHERE id = ? AND tenant_id = ?
    ");
    $checkAfter->execute([$productId, $tenantId]);
    $productAfter = $checkAfter->fetch(PDO::FETCH_ASSOC);
    
    if ($productAfter) {
        echo "- Product ID: {$productAfter['id']}\n";
        echo "- Name: {$productAfter['name']}\n";
        echo "- opening_balance_posted: {$productAfter['opening_balance_posted']} (now: " . 
             ($productAfter['opening_balance_posted'] ? '✓ posted' : 'not posted') . ")\n";
        echo "- Updated: {$productAfter['updated_at']}\n";
    }
    
    echo "\n✅ Fix completed successfully!\n";
    echo "Product 4 should now display GL status = 'posted' in the UI\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
