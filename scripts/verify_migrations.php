<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;

$checks = [
    ['table' => 'accounting_periods', 'col' => null],
    ['table' => 'journal_entries',    'col' => 'reversal_of'],
];

foreach ($checks as $c) {
    if ($c['col'] === null) {
        $s = $db->query("SHOW TABLES LIKE 'accounting_periods'");
        echo ($s->fetchColumn() ? '✅' : '❌') . ' TABLE accounting_periods' . PHP_EOL;
    } else {
        $s = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $s->execute([$c['table'], $c['col']]);
        echo ($s->fetchColumn() > 0 ? '✅' : '❌') . " COLUMN {$c['table']}.{$c['col']}" . PHP_EOL;
    }
}
