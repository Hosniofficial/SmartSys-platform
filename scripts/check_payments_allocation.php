<?php
require 'config/database.php';

$db = (new Database())->pdo;
$tenantId = 47;

echo "════════════════════════════════════════════════════════════════════\n";
echo "🔍 PAYMENT & ALLOCATION DETAILS\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

// Check payments
echo "📋 PAYMENTS:\n";
$stmt = $db->prepare("
    SELECT id, amount, return_id, sale_id FROM payments 
    WHERE tenant_id = ? 
    ORDER BY id
");
$stmt->execute([$tenantId]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($payments as $p) {
    echo "Payment #" . $p['id'] . ":\n";
    echo "  amount: " . $p['amount'] . "\n";
    echo "  return_id: " . ($p['return_id'] ?? 'NULL') . "\n";
    echo "  sale_id: " . ($p['sale_id'] ?? 'NULL') . "\n\n";
}

// Check payment applications
echo "📋 PAYMENT APPLICATIONS:\n";
$stmt = $db->prepare("
    SELECT payment_id, reference_type, reference_id 
    FROM payment_applications 
    WHERE tenant_id = ? 
    ORDER BY id
");
$stmt->execute([$tenantId]);
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($apps as $app) {
    echo "Payment #" . $app['payment_id'] . " → " . $app['reference_type'] . " #" . $app['reference_id'] . "\n";
}

echo "\n════════════════════════════════════════════════════════════════════\n";
