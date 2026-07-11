<?php
/**
 * test_create_product_with_defaults.php
 * Tests creating a product with default expiry/batch/serial values
 */

// Direct database connection
$connection = new PDO(
    'mysql:host=localhost;dbname=inventory;charset=utf8mb4',
    'root',
    ''
);

echo "\n" . str_repeat('=', 80) . "\n";
echo "TEST: Creating Product with Default Values\n";
echo str_repeat('=', 80) . "\n\n";

// Get the first tenant
$tenantStmt = $connection->prepare("SELECT id FROM tenants LIMIT 1");
$tenantStmt->execute();
$tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
$tenantId = $tenant['id'] ?? 1;

echo "Using Tenant ID: {$tenantId}\n\n";

// Test data
$testData = [
    'name' => 'Test Product with Defaults - ' . date('Y-m-d H:i:s'),
    'sale_price' => 100.00,
    'purchase_price' => 50.00,
    'min_sale_price' => 80.00,
    'product_type' => 'stock',
    'has_expiry_date' => 1,
    'default_expiry_date' => date('Y-m-d', strtotime('+30 days')),
    'has_batch_number' => 1,
    'default_batch_number' => 'BATCH-' . date('Ymd'),
    'has_serial_number' => 1,
    'default_serial_number' => 'SN-' . time(),
    'tenant_id' => $tenantId,
];

// Step 1: Insert product using similar logic to ProductsHandler
echo "STEP 1: Insert Product\n";
echo str_repeat('-', 80) . "\n";

$stmt = $connection->prepare("
    INSERT INTO products (
        name, sale_price, purchase_price, min_sale_price, min_quantity,
        description, active, category_id, barcode, unit_id,
        has_expiry_date, has_serial_number, has_batch_number, tenant_id,
        product_type, default_expiry_date, default_batch_number, default_serial_number
    ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$result = $stmt->execute([
    $testData['name'],
    $testData['sale_price'],
    $testData['purchase_price'],
    $testData['min_sale_price'],
    null, // min_quantity
    null, // description
    null, // category_id
    null, // barcode
    null, // unit_id
    $testData['has_expiry_date'],
    $testData['has_serial_number'],
    $testData['has_batch_number'],
    $testData['tenant_id'],
    $testData['product_type'],
    $testData['default_expiry_date'],
    $testData['default_batch_number'],
    $testData['default_serial_number'],
]);

if ($result) {
    $productId = $connection->lastInsertId();
    echo "✅ Product created successfully!\n";
    echo "   Product ID: {$productId}\n";
} else {
    echo "❌ Failed to create product\n";
    print_r($stmt->errorInfo());
    exit(1);
}

// Auto-generate SKU
$sku = 'PRD-' . str_pad((string) $productId, 6, '0', STR_PAD_LEFT);
$connection->prepare("UPDATE products SET product_code = ? WHERE id = ?")->execute([$sku, $productId]);
echo "   SKU: {$sku}\n";

echo "\n";

// Step 2: Fetch product and verify values
echo "STEP 2: Verify Stored Values\n";
echo str_repeat('-', 80) . "\n";

$fetchStmt = $connection->prepare("
    SELECT 
        id, name, product_code,
        has_expiry_date, default_expiry_date,
        has_batch_number, default_batch_number,
        has_serial_number, default_serial_number
    FROM products 
    WHERE id = ? AND tenant_id = ?
");
$fetchStmt->execute([$productId, $tenantId]);
$product = $fetchStmt->fetch(PDO::FETCH_ASSOC);

if ($product) {
    echo "Retrieved product:\n";
    echo "  ID: {$product['id']}\n";
    echo "  Name: {$product['name']}\n";
    echo "  SKU: {$product['product_code']}\n";
    echo "\n  Expiry Date:\n";
    echo "    has_expiry_date: {$product['has_expiry_date']}\n";
    echo "    default_expiry_date: " . ($product['default_expiry_date'] ?? 'NULL') . "\n";
    echo "  Batch Number:\n";
    echo "    has_batch_number: {$product['has_batch_number']}\n";
    echo "    default_batch_number: " . ($product['default_batch_number'] ?? 'NULL') . "\n";
    echo "  Serial Number:\n";
    echo "    has_serial_number: {$product['has_serial_number']}\n";
    echo "    default_serial_number: " . ($product['default_serial_number'] ?? 'NULL') . "\n";
    
    // Verify values match
    echo "\n  Value Verification:\n";
    $matches = 0;
    if ($product['default_expiry_date'] === $testData['default_expiry_date']) {
        echo "    ✅ Expiry date matches\n";
        $matches++;
    } else {
        echo "    ❌ Expiry date mismatch: {$product['default_expiry_date']} vs {$testData['default_expiry_date']}\n";
    }
    
    if ($product['default_batch_number'] === $testData['default_batch_number']) {
        echo "    ✅ Batch number matches\n";
        $matches++;
    } else {
        echo "    ❌ Batch number mismatch\n";
    }
    
    if ($product['default_serial_number'] === $testData['default_serial_number']) {
        echo "    ✅ Serial number matches\n";
        $matches++;
    } else {
        echo "    ❌ Serial number mismatch\n";
    }
    
    echo "\n  Summary: {$matches}/3 values verified ✓\n";
} else {
    echo "❌ Failed to retrieve product\n";
}

echo "\n";
echo str_repeat('=', 80) . "\n";
echo "✅ Test complete!\n";
echo "   New test product ID: {$productId}\n";
echo str_repeat('=', 80) . "\n\n";
