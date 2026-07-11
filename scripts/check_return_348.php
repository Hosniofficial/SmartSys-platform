<?php
require 'config/database.php';
$db = (new Database())->pdo;

// Check return #348
echo "=== Return #348 ===\n";
$stmt = $db->prepare('
    SELECT id, return_number, status, grand_total, created_at
    FROM returns 
    WHERE id = 348 AND tenant_id = 47 
    LIMIT 1
');
$stmt->execute();
$ret = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($ret, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Check if there's a refund payment for return #348
echo "=== Refund Payments for Return #348 ===\n";
$stmt = $db->prepare('
    SELECT p.id, p.reference_number, p.amount, p.payment_method_id
    FROM payments p
    WHERE p.return_id = 348 AND p.tenant_id = 47
');
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($payments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";


