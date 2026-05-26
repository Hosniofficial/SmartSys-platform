<?php
/**
 * analyze_indexes.php — checks for missing indexes on high-traffic tables.
 *
 * Usage: php scripts/analyze_indexes.php
 *
 * Compares the recommended index list against what's actually in the DB
 * and prints a report with the missing ones.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../'))->load();

$pdo = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';port=' . $_ENV['DB_PORT'] . ';dbname=' . $_ENV['DB_DATABASE'] . ';charset=utf8mb4',
    $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ── Recommended indexes ────────────────────────────────────────────────────
// Format: [table, index_name, columns]
$recommended = [
    // sales — most queried table
    ['sales', 'idx_sales_tenant_status',      '(tenant_id, status)'],
    ['sales', 'idx_sales_tenant_customer',    '(tenant_id, customer_id)'],
    ['sales', 'idx_sales_tenant_branch_date', '(tenant_id, branch_id, sale_date)'],
    ['sales', 'idx_sales_created_at',         '(created_at)'],

    // purchases
    ['purchases', 'idx_purchases_tenant_status',   '(tenant_id, status)'],
    ['purchases', 'idx_purchases_tenant_supplier',  '(tenant_id, supplier_id)'],
    ['purchases', 'idx_purchases_tenant_branch',    '(tenant_id, branch_id)'],

    // returns
    ['returns', 'idx_returns_tenant_type',    '(tenant_id, return_type)'],
    ['returns', 'idx_returns_tenant_date',    '(tenant_id, created_at)'],

    // payments
    ['payments', 'idx_payments_tenant_customer', '(tenant_id, customer_id)'],
    ['payments', 'idx_payments_tenant_status',   '(tenant_id, status)'],
    ['payments', 'idx_payments_sale_id',         '(sale_id)'],
    ['payments', 'idx_payments_purchase_id',     '(purchase_id)'],

    // inventory_transactions — heavy on reports
    ['inventory_transactions', 'idx_inv_tx_tenant_product',  '(tenant_id, product_id)'],
    ['inventory_transactions', 'idx_inv_tx_tenant_branch',   '(tenant_id, branch_from)'],
    ['inventory_transactions', 'idx_inv_tx_movement_date',   '(movement_date)'],
    ['inventory_transactions', 'idx_inv_tx_reference',       '(reference_type, reference_id)'],

    // branch_products — POS stock lookup
    ['branch_products', 'idx_bp_tenant_branch_product', '(tenant_id, branch_id, product_id)'],

    // cashier_sessions
    ['cashier_sessions', 'idx_cs_tenant_branch_status', '(tenant_id, branch_id, status)'],
    ['cashier_sessions', 'idx_cs_cashier_status',       '(cashier_id, status)'],

    // audit_log
    ['audit_log', 'idx_audit_tenant_action', '(tenant_id, action)'],
    ['audit_log', 'idx_audit_created_at',    '(created_at)'],

    // security_events
    ['security_events', 'idx_se_tenant_type', '(tenant_id, event_type)'],
    ['security_events', 'idx_se_created_at',  '(created_at)'],

    // refresh_tokens
    ['refresh_tokens', 'idx_rt_user_revoked', '(user_id, is_revoked)'],
    ['refresh_tokens', 'idx_rt_expires_at',   '(expires_at)'],

    // payment_idempotency_keys (already has unique index — just verify)
    ['payment_idempotency_keys', 'idx_idem_expires', '(expires_at)'],
];

// ── Fetch existing indexes ─────────────────────────────────────────────────
$existing = [];
$stmt = $pdo->query("
    SELECT TABLE_NAME, INDEX_NAME
    FROM   information_schema.STATISTICS
    WHERE  TABLE_SCHEMA = DATABASE()
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing[$row['TABLE_NAME']][$row['INDEX_NAME']] = true;
}

// ── Report ─────────────────────────────────────────────────────────────────
$missing = [];
$present = 0;

foreach ($recommended as [$table, $index, $cols]) {
    if (!empty($existing[$table][$index])) {
        $present++;
    } else {
        $missing[] = [$table, $index, $cols];
    }
}

echo "\n";
echo "  ╔══════════════════════════════════════════════════════╗\n";
echo "  ║          Database Index Coverage Report              ║\n";
echo "  ╚══════════════════════════════════════════════════════╝\n\n";
echo "  Recommended : " . count($recommended) . "\n";
echo "  Present     : {$present}\n";
echo "  Missing     : " . count($missing) . "\n\n";

if (empty($missing)) {
    echo "  ✅  All recommended indexes are present.\n\n";
    exit(0);
}

echo "  ❌  Missing indexes (run W4_add_indexes.sql to add):\n\n";
foreach ($missing as [$table, $index, $cols]) {
    echo "    CREATE INDEX {$index} ON {$table} {$cols};\n";
}
echo "\n";
exit(1);
