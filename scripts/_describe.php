<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;
$cols = $db->query('DESCRIBE journal_entry_lines')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) echo $c['Field'] . "\n";
