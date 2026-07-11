<?php
/**
 * Test script to verify opening_balance_posted flag is updated when posting opening balance
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->pdo;

$tenantId = 47;
$productId = 117;  // منتج 5
$branchId = 48;

// Check current state
$stmt = $db->prepare("
    SELECT p.id, p.name, p.opening_balance_posted, pbm.activation_status
    FROM products p
    LEFT JOIN product_branch_gl_mapping pbm ON p.id = pbm.product_id AND pbm.branch_id = ? AND pbm.tenant_id = ?
    WHERE p.id = ? AND p.tenant_id = ?
");
$stmt->execute([$branchId, $tenantId, $productId, $tenantId]);
$before = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== منتج 5 الحالة قبل الاختبار ===\n";
echo "opening_balance_posted: " . ($before['opening_balance_posted'] ?? 'N/A') . "\n";
echo "activation_status: " . ($before['activation_status'] ?? 'N/A') . "\n";
echo "\n";

// Now let's manually call the service to test
try {
    $service = new App\Services\InventoryOpeningBalanceService($db);
    
    $jeId = $service->post(
        $tenantId,
        $productId,
        $branchId,
        1,  // unit_id
        10,  // quantity
        100,  // unit_cost
        date('Y-m-d'),
        61,  // user_id (admin0)
        'منتج 5',
        'الفرع الرئيسي',
        null,
        null,
        'opening_balance_manual'
    );
    
    echo "✓ Journal entry created: $jeId\n\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Check state after
$stmt = $db->prepare("
    SELECT p.id, p.name, p.opening_balance_posted, pbm.activation_status
    FROM products p
    LEFT JOIN product_branch_gl_mapping pbm ON p.id = pbm.product_id AND pbm.branch_id = ? AND pbm.tenant_id = ?
    WHERE p.id = ? AND p.tenant_id = ?
");
$stmt->execute([$branchId, $tenantId, $productId, $tenantId]);
$after = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== منتج 5 الحالة بعد الاختبار ===\n";
echo "opening_balance_posted: " . ($after['opening_balance_posted'] ?? 'N/A') . "\n";
echo "activation_status: " . ($after['activation_status'] ?? 'N/A') . "\n";
echo "\n";

if ($after['opening_balance_posted'] == 1) {
    echo "✅ SUCCESS: opening_balance_posted is now 1!\n";
} else {
    echo "❌ FAIL: opening_balance_posted is still " . ($after['opening_balance_posted'] ?? 'null') . "\n";
}
?>
