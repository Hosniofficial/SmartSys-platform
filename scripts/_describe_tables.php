<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->pdo;

// Columns that may be redundant/misleading after new logic
$flagged = [
    'sales'     => ['status', 'paid_amount'],
    'returns'   => ['remaining_amount', 'status'],
    'purchases' => ['status', 'paid_amount'],
    'products'  => ['quantity', 'stock_quantity'],
    'payments'  => [],
    'customers' => ['balance', 'credit_limit'],
    'suppliers' => ['balance'],
    'journal_entry_lines' => [],
    'journal_entries'     => [],
];

$tables = [
    'sales', 'returns', 'purchases', 'products',
    'payments', 'customers', 'suppliers',
    'journal_entries', 'journal_entry_lines',
    'cash_vouchers', 'sale_items', 'purchase_items',
];

foreach ($tables as $table) {
    try {
        $rows = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        echo PHP_EOL . "══════════════════════════════════" . PHP_EOL;
        echo "📋 TABLE: $table  (" . count($rows) . " columns)" . PHP_EOL;
        echo "══════════════════════════════════" . PHP_EOL;
        foreach ($rows as $r) {
            $flag = in_array($r['Field'], $flagged[$table] ?? []) ? '  ⚠️  REVIEW' : '';
            $null = $r['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $r['Default'] !== null ? " DEFAULT={$r['Default']}" : '';
            echo sprintf("  %-35s %-20s %-8s%s%s\n",
                $r['Field'], $r['Type'], $null, $default, $flag);
        }
    } catch (\Throwable $e) {
        echo "  ⚠️  Table '$table' not found or error: " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "══════════════════════════════════" . PHP_EOL;
echo "✅ Done" . PHP_EOL;
