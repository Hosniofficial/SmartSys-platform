<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db = new Database();

$tables = ['sales', 'returns', 'payment_applications', 'return_items'];

foreach ($tables as $table) {
    $stmt = $db->pdo->query('DESCRIBE ' . $table);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n$table columns:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . "\n";
    }
}
?>
