<?php
/**
 * Reset product 5 to test opening balance posting
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->pdo;

$tenantId = 47;
$productId = 117;  // منتج 5
$branchId = 48;

echo "=== إعادة تعيين حالة منتج 5 ===\n";

// Reset product status
$db->prepare("
    UPDATE products
    SET opening_balance_posted = 0
    WHERE id = ? AND tenant_id = ?
")->execute([$productId, $tenantId]);

// Reset mapping status
$db->prepare("
    UPDATE product_branch_gl_mapping
    SET gl_reconciliation_status = 'ACTIVE_IN_BRANCH'
    WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
")->execute([$productId, $branchId, $tenantId]);

echo "✓ تم إعادة تعيين الحالة\n\n";

// Check current state
$stmt = $db->prepare("
    SELECT p.id, p.name, p.opening_balance_posted, pbm.gl_reconciliation_status
    FROM products p
    LEFT JOIN product_branch_gl_mapping pbm ON p.id = pbm.product_id AND pbm.branch_id = ? AND pbm.tenant_id = ?
    WHERE p.id = ? AND p.tenant_id = ?
");
$stmt->execute([$branchId, $tenantId, $productId, $tenantId]);
$before = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== الحالة بعد الإعادة ===\n";
echo "opening_balance_posted: " . ($before['opening_balance_posted'] ?? 'N/A') . "\n";
echo "gl_reconciliation_status: " . ($before['gl_reconciliation_status'] ?? 'N/A') . "\n";
echo "\n";

// Now test the service
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
    
    echo "✓ تم إنشاء قيد: $jeId\n\n";
    
} catch (\Throwable $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

// Check state after
$stmt = $db->prepare("
    SELECT p.id, p.name, p.opening_balance_posted, pbm.gl_reconciliation_status
    FROM products p
    LEFT JOIN product_branch_gl_mapping pbm ON p.id = pbm.product_id AND pbm.branch_id = ? AND pbm.tenant_id = ?
    WHERE p.id = ? AND p.tenant_id = ?
");
$stmt->execute([$branchId, $tenantId, $productId, $tenantId]);
$after = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== الحالة بعد الترصيد ===\n";
echo "opening_balance_posted: " . ($after['opening_balance_posted'] ?? 'N/A') . "\n";
echo "gl_reconciliation_status: " . ($after['gl_reconciliation_status'] ?? 'N/A') . "\n";
echo "\n";

if ($after['opening_balance_posted'] == 1) {
    echo "✅ نجح: تم تعيين opening_balance_posted إلى 1!\n";
} else {
    echo "❌ فشل: opening_balance_posted لا تزال " . ($after['opening_balance_posted'] ?? 'null') . "\n";
}
?>
