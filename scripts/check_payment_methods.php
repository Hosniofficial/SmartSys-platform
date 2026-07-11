<?php
// Load environment
$env_file = __DIR__ . '/../.env';
$env = [];
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
}

$pdo = new PDO(
    "mysql:host=" . $env['DB_HOST'] . ";dbname=" . $env['DB_NAME'],
    $env['DB_USER'],
    $env['DB_PASSWORD']
);

echo "=== Payment Methods for Tenant 47 ===\n";
$stmt = $pdo->query("SELECT id, name, tenant_id, kind, status FROM payment_methods WHERE tenant_id = 47 OR tenant_id IS NULL ORDER BY id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Tenant: " . ($row['tenant_id'] ?? 'NULL') . " | Kind: " . $row['kind'] . " | Status: " . $row['status'] . "\n";
}

echo "\n=== Checking ID 18 specifically ===\n";
$stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = 18");
$stmt->execute();
if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($result);
} else {
    echo "Payment method ID 18 does not exist!\n";
}

echo "\n=== Checking Tenant 47 Default Payment Methods ===\n";
$stmt = $pdo->prepare("SELECT default_payment_method_id FROM tenants WHERE id = 47");
$stmt->execute();
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Default payment method ID: " . ($row['default_payment_method_id'] ?? 'NULL') . "\n";
}
