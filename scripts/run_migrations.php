<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;

$sql = file_get_contents(__DIR__ . '/migrations/001_accounting_periods.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $q) {
    if ($q === '' || strpos(ltrim($q), '--') === 0) continue;
    try {
        $db->exec($q);
        echo 'OK: ' . substr(trim($q), 0, 80) . PHP_EOL;
    } catch (Exception $e) {
        echo 'ERR: ' . $e->getMessage() . PHP_EOL;
        echo '  SQL: ' . substr($q, 0, 100) . PHP_EOL;
    }
}
// Add reversal_of to journal_entries (manual check for older MySQL)
$chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_entries' AND COLUMN_NAME='reversal_of'");
$chk->execute();
if ($chk->fetchColumn() == 0) {
    try {
        $db->exec("ALTER TABLE journal_entries ADD COLUMN reversal_of INT NULL DEFAULT NULL COMMENT 'FK to original JE that this entry reverses'");
        $db->exec("ALTER TABLE journal_entries ADD KEY idx_je_reversal_of (reversal_of)");
        echo 'OK: ALTER TABLE journal_entries ADD COLUMN reversal_of' . PHP_EOL;
    } catch (Exception $e) {
        echo 'ERR reversal_of: ' . $e->getMessage() . PHP_EOL;
    }
} else {
    echo 'SKIP: journal_entries.reversal_of already exists' . PHP_EOL;
}

echo 'Done.' . PHP_EOL;
