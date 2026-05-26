<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;

// Fix 1: Add idempotency_key to cash_vouchers
$cols = array_column($db->query('DESCRIBE cash_vouchers')->fetchAll(PDO::FETCH_ASSOC), 'Field');
if (!in_array('idempotency_key', $cols)) {
    $db->exec('ALTER TABLE cash_vouchers ADD COLUMN idempotency_key VARCHAR(255) NULL DEFAULT NULL');
    echo "✓ Added idempotency_key to cash_vouchers" . PHP_EOL;
} else {
    echo "  idempotency_key already exists in cash_vouchers" . PHP_EOL;
}
