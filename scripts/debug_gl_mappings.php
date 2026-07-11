<?php
// Debug GL Mappings to understand activation issue

// Database connection
$host = 'localhost';
$db_name = 'inventory';
$user = 'root';
$pass = '';

$db = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $user, $pass);

try {
    echo "=== Debugging GL Mappings ===\n\n";
    
    $tenantId = 47; // From the screenshots
    $mainBranchId = 1; // الفرع الرئيسي
    
    // Check what's in product_branch_gl_mapping table
    $query = "
        SELECT 
            pbm.id,
            pbm.product_id,
            pbm.branch_id,
            pbm.activation_status,
            pbm.activation_date,
            p.name as product_name
        FROM product_branch_gl_mapping pbm
        LEFT JOIN products p ON pbm.product_id = p.id
        WHERE pbm.tenant_id = ?
        ORDER BY pbm.branch_id, pbm.product_id
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$tenantId]);
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "ALL GL MAPPINGS FOR TENANT $tenantId:\n";
    echo str_repeat("-", 100) . "\n";
    foreach ($mappings as $m) {
        echo sprintf(
            "Product ID: %d (%s) | Branch: %d | Status: %s | Activated: %s\n",
            $m['product_id'],
            $m['product_name'] ?? 'DELETED',
            $m['branch_id'],
            $m['activation_status'],
            $m['activation_date']
        );
    }
    
    // Now check products with opening_balance_posted = 1
    echo "\n\nPRODUCTS WITH opening_balance_posted = 1:\n";
    echo str_repeat("-", 100) . "\n";
    
    $query2 = "
        SELECT id, name, opening_balance_posted
        FROM products
        WHERE tenant_id = ? AND opening_balance_posted = 1
    ";
    $stmt2 = $db->prepare($query2);
    $stmt2->execute([$tenantId]);
    $postedProducts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($postedProducts as $p) {
        echo sprintf("Product %d: %s (opening_balance_posted=1)\n", $p['id'], $p['name']);
    }
    
    // Test the query that should be run when branch_id=1 is provided
    echo "\n\nTEST QUERY - What API should get for branch_id=$mainBranchId:\n";
    echo str_repeat("-", 100) . "\n";
    
    $testQuery = "
        SELECT product_id, activation_status
        FROM product_branch_gl_mapping
        WHERE branch_id = ?
          AND tenant_id = ?
    ";
    
    $stmt3 = $db->prepare($testQuery);
    $stmt3->execute([$mainBranchId, $tenantId]);
    $result = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    echo "GL Mappings for branch_id=$mainBranchId, tenant_id=$tenantId:\n";
    if (empty($result)) {
        echo "❌ NO MAPPINGS FOUND FOR BRANCH $mainBranchId!\n";
    } else {
        foreach ($result as $r) {
            echo sprintf("- Product %d: %s\n", $r['product_id'], $r['activation_status']);
        }
    }
    
    // List all branches in the system
    echo "\n\nAVAILABLE BRANCHES:\n";
    echo str_repeat("-", 100) . "\n";
    
    $branchQuery = "SELECT id, name FROM branches WHERE tenant_id = ?";
    $branchStmt = $db->prepare($branchQuery);
    $branchStmt->execute([$tenantId]);
    $branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($branches as $b) {
        echo sprintf("Branch %d: %s\n", $b['id'], $b['name']);
    }
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
