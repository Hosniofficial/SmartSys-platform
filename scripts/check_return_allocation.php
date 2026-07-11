<?php
require 'config/database.php';
$db = (new Database())->pdo;

echo "════════════════════════════════════════════════════════════════════\n";
echo "🔍 PAYMENT APPLICATIONS - Where was the return credit applied?\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

$stmt = $db->prepare("
    SELECT payment_id, reference_type, reference_id 
    FROM payment_applications 
    WHERE tenant_id = 47
    ORDER BY id
");
$stmt->execute();
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "All Payment Applications:\n";
foreach ($apps as $app) {
    echo "  Payment #" . $app['payment_id'] . " → " . $app['reference_type'] . " #" . $app['reference_id'] . "\n";
}

echo "\n════════════════════════════════════════════════════════════════════\n";
echo "🔍 SPECIFICALLY: Payment #704 (Return #348)\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

$stmt = $db->prepare("
    SELECT payment_id, reference_type, reference_id 
    FROM payment_applications 
    WHERE payment_id = 704 AND tenant_id = 47
");
$stmt->execute();
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($apps)) {
    echo "❌ Payment #704 has NO payment_applications!\n";
} else {
    foreach ($apps as $app) {
        echo "✅ Payment #704 is applied to " . $app['reference_type'] . " #" . $app['reference_id'] . "\n";
    }
}

echo "\n════════════════════════════════════════════════════════════════════\n";
echo "🔍 WHERE was the 2000 return credit allocated?\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

$stmt = $db->prepare("
    SELECT reference_id, COUNT(*) as app_count
    FROM payment_applications 
    WHERE payment_id = 704 AND tenant_id = 47
    GROUP BY reference_id
");
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($groups) > 1) {
    echo "❌ PROBLEM: Return credit was split across multiple invoices!\n";
    foreach ($groups as $g) {
        echo "   Invoice #" . $g['reference_id'] . "\n";
    }
} else if (count($groups) == 1) {
    $inv = $groups[0]['reference_id'];
    echo "✅ Return credit applied to Invoice #" . $inv . " only\n";
} else {
    echo "❌ Return credit NOT applied to ANY invoice!\n";
}
