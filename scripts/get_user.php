<?php
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$stmt = $db->pdo->prepare('SELECT id, username, email FROM users WHERE tenant_id = 47 LIMIT 1');
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'User: ' . json_encode($user) . PHP_EOL;
?>
