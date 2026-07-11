<?php
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$tenantId = 47;

echo "=== التحقق من حالة جميع المنتجات بعد الإصلاح ===\n\n";

$stmt = $db->pdo->prepare("
    SELECT 
        p.id,
        p.name,
        p.opening_balance_posted,
        pbm.activation_status,
        pbm.gl_reconciliation_status
    FROM products p
    LEFT JOIN product_branch_gl_mapping pbm 
        ON p.id = pbm.product_id 
        AND pbm.branch_id = 48 
        AND pbm.tenant_id = ?
    WHERE p.tenant_id = ?
    ORDER BY p.id ASC
    LIMIT 10
");
$stmt->execute([$tenantId, $tenantId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $product) {
    echo "===================================\n";
    echo "منتج: {$product['name']} (ID: {$product['id']})\n";
    echo "opening_balance_posted: " . ($product['opening_balance_posted'] ?? 'NULL') . "\n";
    echo "activation_status: " . ($product['activation_status'] ?? 'N/A') . "\n";
    echo "gl_reconciliation_status: " . ($product['gl_reconciliation_status'] ?? 'N/A') . "\n";
    
    // Determine GL status based on the logic
    $status = 'draft';
    if ($product['opening_balance_posted'] == 1) {
        $status = 'posted';
    } elseif ($product['activation_status'] === 'RECONCILED' || $product['gl_reconciliation_status'] === 'RECONCILED') {
        $status = 'posted';
    } elseif ($product['activation_status'] === 'ACTIVE_IN_BRANCH') {
        $status = 'active';
    }
    echo "GL Status: $status\n";
    echo "-----------------------------------\n";
}
?>
