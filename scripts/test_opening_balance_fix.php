<?php
// Test the postOpeningBalance endpoint with new parameters
require 'vendor/autoload.php';

$db = new PDO('mysql:host=localhost;port=3306;dbname=inventory', 'root', '');

// Get tenant 47 details
$tenantId = 47;
$stmt = $db->prepare('SELECT id FROM branches WHERE tenant_id = ? LIMIT 1');
$stmt->execute([$tenantId]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);
$branchId = $branch['id'];

// Get product 117 (منتج 5) 
$stmt = $db->prepare('SELECT id, name FROM products WHERE tenant_id = ? AND id = 117 LIMIT 1');
$stmt->execute([$tenantId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
$productId = $product['id'];

// Check if mapping exists
$stmt = $db->prepare('SELECT id, activation_status FROM product_branch_gl_mapping WHERE product_id = ? AND branch_id = ? AND tenant_id = ?');
$stmt->execute([$productId, $branchId, $tenantId]);
$mapping = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== Testing postOpeningBalance endpoint fix ===\n\n";
echo "Product ID: $productId (name: {$product['name']})\n";
echo "Branch ID: $branchId\n";
echo "Tenant ID: $tenantId\n\n";

if ($mapping) {
    echo "✓ Mapping exists:\n";
    echo "  - Mapping ID: {$mapping['id']}\n";
    echo "  - Activation Status: {$mapping['activation_status']}\n\n";
    echo "Backend can now accept:\n";
    echo "  1. Old mode: mapping_id = {$mapping['id']}\n";
    echo "  2. New mode: product_id = $productId + branch_id = $branchId\n";
} else {
    echo "✗ Mapping not found!\n";
}
?>
