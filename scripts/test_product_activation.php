<?php
/**
 * Test: Product Activation GL Status Bug Fix
 * 
 * This script verifies that after activating a product in a branch,
 * the gl_status field is correctly updated to reflect the activation status
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

use PDO;

try {
    $database = new Database();
    $db = $database->pdo;
    
    // Get test data
    $tenantId = 47;  // Your test tenant
    
    // 1. Get all products for a branch
    echo "\nTest 1: Fetching products for branch...\n";
    
    $sql = "
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.active = 1 AND p.tenant_id = ? AND p.id = 1
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([(int) $tenantId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo "❌ No test product found\n";
        exit(1);
    }
    
    $productId = $product['id'];
    echo "✓ Product ID: " . $productId . "\n";
    
    // 2. Get first branch
    $branchStmt = $db->prepare("SELECT id FROM branches WHERE tenant_id = ? LIMIT 1");
    $branchStmt->execute([(int) $tenantId]);
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        echo "❌ No branch found\n";
        exit(1);
    }
    
    $branchId = $branch['id'];
    echo "✓ Branch ID: " . $branchId . "\n";
    
    // 3. Activate product in branch
    echo "\nTest 2: Activating product in branch...\n";
    
    $activateStmt = $db->prepare("
        INSERT INTO product_branch_gl_mapping (
            tenant_id, product_id, branch_id, 
            inventory_gl_account_id, purchase_gl_account_id, cogs_gl_account_id,
            activation_status, activation_date, created_by_user_id
        ) VALUES (?, ?, ?, 1, 1, 1, 'ACTIVE_IN_BRANCH', NOW(), 1)
        ON DUPLICATE KEY UPDATE 
            activation_status = 'ACTIVE_IN_BRANCH',
            activation_date = NOW()
    ");
    
    $activateStmt->execute([(int) $tenantId, $productId, $branchId]);
    echo "✓ Product activated\n";
    
    // 4. Get branch inventory data
    echo "\nTest 3: Fetching branch inventory...\n";
    
    $branchProdStmt = $db->prepare("
        SELECT 
            product_id,
            SUM(quantity) as quantity,
            MIN(minimum_quantity) as minimum_quantity,
            SUM(quantity_cost) as quantity_cost
        FROM branch_products
        WHERE product_id = ? AND tenant_id = ?
        GROUP BY product_id
    ");
    
    $branchProdStmt->execute([$productId, (int) $tenantId]);
    $branchProduct = $branchProdStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    echo "✓ Branch product data retrieved\n";
    
    // 5. Get product units
    echo "\nTest 3.5: Fetching product units...\n";
    $unitsStmt = $db->prepare("
        SELECT
            pu.unit_id,
            pu.conversion_factor,
            pu.is_main_unit,
            u.name as unit_name,
            u.code as unit_code
        FROM product_units pu
        JOIN units u ON pu.unit_id = u.id
        WHERE pu.product_id = ? AND pu.tenant_id = ?
        ORDER BY pu.is_main_unit DESC
    ");
    
    $unitsStmt->execute([$productId, (int) $tenantId]);
    $units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo "✓ Product units retrieved: " . count($units) . " units\n";
    
    // 6. Get GL mapping status
    echo "\nTest 4: Fetching GL mapping status...\n";
    
    $glStmt = $db->prepare("
        SELECT activation_status
        FROM product_branch_gl_mapping
        WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
    ");
    
    $glStmt->execute([$productId, $branchId, (int) $tenantId]);
    $glData = $glStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($glData) {
        echo "✓ GL Activation Status: " . $glData['activation_status'] . "\n";
    } else {
        echo "⚠ No GL mapping found (this is OK if product was never activated)\n";
    }
    
    // 7. Transform using ProductListResource
    echo "\nTest 5: Transforming product with ProductListResource...\n";
    
    require_once __DIR__ . '/../api/v1/src/Resources/ProductListResource.php';
    
    $transformed = \App\Resources\ProductListResource::transform(
        $product,
        $branchProduct,
        $units,
        $glData['activation_status'] ?? null
    );
    
    $glStatus = $transformed['gl_status'];
    echo "✓ GL Status from Resource: " . $glStatus . "\n";
    
    // 8. Verify
    echo "\nTest 6: Verification...\n";
    
    if ($glData && $glData['activation_status'] === 'ACTIVE_IN_BRANCH') {
        if ($glStatus === 'draft') {
            echo "✅ SUCCESS: GL Status correctly shows 'draft' after activation (not yet posted)\n";
        } else {
            echo "❌ FAIL: GL Status should be 'draft' after activation, got: " . $glStatus . "\n";
            exit(1);
        }
    }
    
    echo "\n✅ All tests passed!\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
