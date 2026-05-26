<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;

$queries = [
    "SELECT 'products' AS tbl, id, name, min_quantity, sale_price, purchase_price, active, product_status FROM products WHERE tenant_id = 44 ORDER BY id",
    "SELECT 'branch_products' AS tbl, id, branch_id, product_id, quantity, quantity_cost, gl_reconciled, last_gl_posting_date FROM branch_products WHERE tenant_id = 44 ORDER BY product_id, branch_id",
    "SELECT 'gl_mapping' AS tbl, id, product_id, branch_id, average_cost, activation_status, gl_balance, gl_reconciliation_status, last_gl_posting_date FROM product_branch_gl_mapping WHERE tenant_id = 44 ORDER BY product_id, branch_id",
    "SELECT 'inv_tx' AS tbl, id, product_id, branch_from, branch_to, quantity, unit_cost, total_cost, movement_type, reference_type, reference_id, journal_entry_id FROM inventory_transactions WHERE tenant_id = 44 ORDER BY id DESC",
    "SELECT 'journal_entries' AS tbl, id, description, status, entry_date, reference_type, reference_id FROM journal_entries WHERE tenant_id = 44 ORDER BY id",
    "SELECT 'je_lines' AS tbl, id, journal_entry_id, account_id, debit_amount, credit_amount, description FROM journal_entry_lines WHERE tenant_id = 44 ORDER BY journal_entry_id, id",
];

foreach ($queries as $sql) {
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $tbl = $rows[0]['tbl'] ?? parse_str($sql);
    echo "\n=== {$rows[0]['tbl']} (" . count($rows) . " rows) ===\n";
    if (empty($rows)) { echo "  (empty)\n"; continue; }
    $keys = array_keys($rows[0]);
    echo implode("\t", $keys) . "\n";
    echo str_repeat('-', 120) . "\n";
    foreach ($rows as $r) {
        echo implode("\t", array_map(fn($v) => $v ?? 'NULL', $r)) . "\n";
    }
}
