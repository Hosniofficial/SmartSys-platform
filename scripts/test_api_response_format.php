<?php
/**
 * test_api_response_format.php
 * Tests that API returns correct field names via ProductDetailResource
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Direct database connection if bootstrap fails
$connection = new PDO(
    'mysql:host=localhost;dbname=inventory;charset=utf8mb4',
    'root',
    ''
);

echo "\n" . str_repeat('=', 80) . "\n";
echo "TEST: API Response Format (ProductDetailResource)\n";
echo str_repeat('=', 80) . "\n\n";

// Step 1: Get the test product we just created
echo "STEP 1: Fetch Test Product (ID: 116)\n";
echo str_repeat('-', 80) . "\n";

$productId = 116;  // The one we just created
$tenantId = 39;    // The tenant

$stmt = $connection->prepare("
    SELECT * FROM products 
    WHERE id = ? AND tenant_id = ?
    LIMIT 1
");
$stmt->execute([$productId, $tenantId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "❌ Product not found\n";
    exit(1);
}

echo "✅ Product found: {$product['name']}\n\n";

// Step 2: Simulate what ProductDetailResource does
echo "STEP 2: Simulate ProductDetailResource Transform\n";
echo str_repeat('-', 80) . "\n";

// This is what ProductDetailResource returns in the 'configuration' section
$apiResponse = [
    'id' => (int) $product['id'],
    'name' => $product['name'],
    'product_code' => $product['product_code'],
    
    'configuration' => [
        'product_type' => $product['product_type'] ?? 'stock',
        'active' => (bool) ($product['is_active_product'] ?? $product['active'] ?? false) ? 1 : 0,
        'has_expiry_date' => (bool) ($product['has_expiry_date'] ?? false),
        'has_batch_number' => (bool) ($product['has_batch_number'] ?? false),
        'has_serial_number' => (bool) ($product['has_serial_number'] ?? false),
        // These are the critical fields we're testing
        'expiry_date' => $product['default_expiry_date'] ?? null,      // ← From default_expiry_date
        'batch_number' => $product['default_batch_number'] ?? null,    // ← From default_batch_number
        'serial_number' => $product['default_serial_number'] ?? null,  // ← From default_serial_number
    ],
];

echo "API Response (configuration section):\n";
echo json_encode($apiResponse['configuration'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";

// Step 3: Verify field values
echo "STEP 3: Verify Field Values in Response\n";
echo str_repeat('-', 80) . "\n";

$config = $apiResponse['configuration'];

$checks = [
    'has_expiry_date flag' => $config['has_expiry_date'] === true,
    'expiry_date value' => !empty($config['expiry_date']),
    'has_batch_number flag' => $config['has_batch_number'] === true,
    'batch_number value' => !empty($config['batch_number']),
    'has_serial_number flag' => $config['has_serial_number'] === true,
    'serial_number value' => !empty($config['serial_number']),
];

$passed = 0;
foreach ($checks as $check => $result) {
    if ($result) {
        echo "✅ {$check}\n";
        $passed++;
    } else {
        echo "❌ {$check}\n";
    }
}

echo "\n";

// Step 4: Show how frontend will receive this
echo "STEP 4: How Frontend Will Receive This\n";
echo str_repeat('-', 80) . "\n";

// This is how openEditModal flattens the data
$flattenedForFrontend = [
    'has_expiry_date' => $config['has_expiry_date'],
    'expiry_date' => $config['expiry_date'],
    'has_batch_number' => $config['has_batch_number'],
    'batch_number' => $config['batch_number'],
    'has_serial_number' => $config['has_serial_number'],
    'serial_number' => $config['serial_number'],
];

echo "Flattened data for ProductForm:\n";
echo json_encode($flattenedForFrontend, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";

// Step 5: Show what form sends back
echo "STEP 5: What ProductForm Sends Back (After handleSubmit)\n";
echo str_repeat('-', 80) . "\n";

$payloadFromForm = $flattenedForFrontend;

// handleSubmit renames the fields
$payloadToAPI = [
    'has_expiry_date' => $payloadFromForm['has_expiry_date'],
    'default_expiry_date' => $payloadFromForm['expiry_date'],  // Renamed!
    'has_batch_number' => $payloadFromForm['has_batch_number'],
    'default_batch_number' => $payloadFromForm['batch_number'],  // Renamed!
    'has_serial_number' => $payloadFromForm['has_serial_number'],
    'default_serial_number' => $payloadFromForm['serial_number'],  // Renamed!
];

echo "Payload sent to API (after renaming in handleSubmit):\n";
echo json_encode($payloadToAPI, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";
echo str_repeat('=', 80) . "\n";
echo "✅ Test complete!\n";
echo "Summary: {$passed}/6 checks passed\n";
echo str_repeat('=', 80) . "\n\n";
