<?php
require 'config/database.php';

$db = (new Database())->pdo;

$stmt = $db->prepare('SELECT id, payment_type, amount, return_id, sale_id FROM payments WHERE id = 704 AND tenant_id = 47');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Payment #704:\n";
echo "  type: " . $row['payment_type'] . "\n";
echo "  amount: " . $row['amount'] . "\n";
echo "  return_id: " . ($row['return_id'] ?? 'NULL') . "\n";
echo "  sale_id: " . ($row['sale_id'] ?? 'NULL') . "\n";
