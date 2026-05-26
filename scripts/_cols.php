<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;
echo "journal_entry_lines: " . implode(', ', $db->query('SHOW COLUMNS FROM journal_entry_lines')->fetchAll(PDO::FETCH_COLUMN)) . PHP_EOL;
