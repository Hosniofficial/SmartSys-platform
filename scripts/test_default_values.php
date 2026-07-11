<?php
/**
 * test_default_values.php - Test script for default expiry/batch/serial values
 * Tests that the new columns are correctly stored and retrieved
 */

// Direct database connection for testing
try {
    $connection = new PDO(
        'mysql:host=localhost;dbname=inventory;charset=utf8mb4',
        'root',
        ''
    );
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test product ID (use first product with these flags)
echo "\n" . str_repeat('=', 80) . "\n";
echo "TEST: Default Values for Expiry/Batch/Serial\n";
echo str_repeat('=', 80) . "\n\n";

// Step 1: Check column existence
echo "STEP 1: Verify columns exist in products table\n";
echo str_repeat('-', 80) . "\n";

$cols = [
    'default_expiry_date',
    'default_batch_number', 
    'default_serial_number'
];

$stmt = $connection->prepare("
    SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'products' AND COLUMN_NAME IN (?, ?, ?)
");
$stmt->execute($cols);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($result) === 3) {
    echo "✅ All columns exist:\n";
    foreach ($result as $col) {
        echo "  - {$col['COLUMN_NAME']}: {$col['COLUMN_TYPE']} (Nullable: {$col['IS_NULLABLE']})\n";
    }
} else {
    echo "❌ Some columns are missing! Found " . count($result) . "/3\n";
}

echo "\n";

// Step 2: Check a sample product
echo "STEP 2: Fetch a sample product with new columns\n";
echo str_repeat('-', 80) . "\n";

$tenantId = 1;  // Default tenant

$stmt = $connection->prepare("
    SELECT 
        id, name, product_code,
        has_expiry_date, default_expiry_date,
        has_batch_number, default_batch_number,
        has_serial_number, default_serial_number
    FROM products 
    WHERE tenant_id = ? AND active = 1
    LIMIT 1
");
$stmt->execute([$tenantId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if ($product) {
    echo "Found product:\n";
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
} else {
    echo "❌ No product found for tenant {$tenantId}\n";
}

echo "\n";

// Step 3: Summary
echo "STEP 3: Summary\n";
echo str_repeat('-', 80) . "\n";

$totalProducts = $connection->query(
    "SELECT COUNT(*) FROM products WHERE tenant_id = 1 AND active = 1"
)->fetchColumn();

$productsWithDefaults = $connection->query(
    "SELECT COUNT(*) FROM products WHERE tenant_id = 1 AND active = 1 
     AND (default_expiry_date IS NOT NULL 
          OR default_batch_number IS NOT NULL 
          OR default_serial_number IS NOT NULL)"
)->fetchColumn();

echo "Total products: {$totalProducts}\n";
echo "Products with at least one default value: {$productsWithDefaults}\n";
echo "\n✅ Test complete!\n";
echo str_repeat('=', 80) . "\n\n";
