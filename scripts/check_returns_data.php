<?php
require_once __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO("mysql:host=localhost;dbname=inventory", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "🔍 Checking returns table for IDs 337, 338, 339:\n\n";

$stmt = $pdo->prepare("
    SELECT id, return_number, refund_amount, refund_method, tenant_id
    FROM returns 
    WHERE id IN (337, 338, 339)
    ORDER BY id ASC
");
$stmt->execute();
$returns = $stmt->fetchAll();

if (empty($returns)) {
    echo "❌ No returns found with IDs 337, 338, 339\n\n";
    
    echo "Available returns in database:\n";
    $stmtAll = $pdo->prepare("SELECT id, return_number, refund_amount, refund_method FROM returns ORDER BY id DESC LIMIT 5");
    $stmtAll->execute();
    $allReturns = $stmtAll->fetchAll();
    
    foreach ($allReturns as $r) {
        echo "  ID: " . $r['id'] . ", Number: " . $r['return_number'] . ", Refund: " . $r['refund_amount'] . ", Method: " . ($r['refund_method'] ?: 'NULL') . "\n";
    }
} else {
    foreach ($returns as $r) {
        echo "Return #" . $r['id'] . ":\n";
        echo "  return_number: " . $r['return_number'] . "\n";
        echo "  tenant_id: " . $r['tenant_id'] . "\n";
        echo "  refund_amount: " . $r['refund_amount'] . "\n";
        echo "  refund_method: " . ($r['refund_method'] ?: 'NULL') . "\n\n";
    }
}
