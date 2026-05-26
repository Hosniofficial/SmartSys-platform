<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;

$tables = [
    'inventory_transactions' => ['movement_type', 'reference_type'],
    'journal_entries'        => ['reference_type'],
];

foreach ($tables as $table => $cols) {
    foreach ($cols as $col) {
        $stmt = $db->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $col]);
        $type = $stmt->fetchColumn();
        echo "{$table}.{$col} => {$type}" . PHP_EOL;
    }
}
