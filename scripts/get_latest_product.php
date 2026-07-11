<?php
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$stmt = $db->pdo->prepare('SELECT id, name FROM products WHERE tenant_id = 47 ORDER BY id DESC LIMIT 1');
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'Latest product: ID=' . $product['id'] . ', Name=' . $product['name'] . PHP_EOL;
?>
