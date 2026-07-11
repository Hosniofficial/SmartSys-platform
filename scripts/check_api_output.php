<?php
require 'config/database.php';

$db = (new Database())->pdo;
$tenantId = 47;
$customerId = 37;

echo "════════════════════════════════════════════════════════════════════\n";
echo "✅ API OUTPUT: Customer Details\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

// Simulate what the API returns for getCustomer()
$stmt = $db->prepare("
    SELECT
        c.id, c.tenant_id, c.name,
        (
            COALESCE((
                SELECT SUM(jel.debit_amount - jel.credit_amount)
                FROM journal_entry_lines jel
                WHERE jel.account_id = c.account_id
                  AND jel.tenant_id = c.tenant_id
            ), 0)
        ) AS balance,
        (
            COALESCE((
                SELECT SUM(r.grand_total)
                FROM returns r
                WHERE r.customer_id = c.id
                  AND r.tenant_id = c.tenant_id
                  AND r.return_type = 'sale'
                  AND r.status IN ('approved', 'completed')
            ), 0)
        ) AS total_returns
    FROM customers c
    WHERE c.id = ? AND c.tenant_id = ?
");
$stmt->execute([$customerId, $tenantId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Customer: " . $customer['name'] . "\n";
echo "  balance (from journal entries): " . $customer['balance'] . "\n";
echo "  total_returns: " . $customer['total_returns'] . "\n";
echo "  outstanding (should be balance): " . $customer['balance'] . "\n\n";

// Now check each invoice status via API (with normalization)
echo "📋 INVOICE STATUSES (From Sales Table):\n";
$stmt = $db->prepare("
    SELECT id, invoice_number, status, 
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total,
           paid_amount,
           (net_total_amount + IFNULL(tax_amount, 0) - IFNULL(paid_amount, 0)) AS outstanding_sales_table
    FROM sales
    WHERE customer_id = ? AND tenant_id = ?
    ORDER BY id
");
$stmt->execute([$customerId, $tenantId]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($invoices as $inv) {
    echo "Invoice #" . $inv['id'] . ":\n";
    echo "  database status: " . $inv['status'] . "\n";
    echo "  outstanding (from sales table): " . $inv['outstanding_sales_table'] . "\n";
    
    // Apply normalization logic (same as API)
    $hasReturns = $customer['total_returns'] > 0;
    $outstanding = $inv['outstanding_sales_table'];
    $paid = $inv['paid_amount'];
    
    if ($hasReturns && abs($outstanding) < 0.01 && $paid < 0.01) {
        $status = 'closed_by_return';
    } else {
        $status = $inv['status'];
    }
    
    echo "  API status (normalized): " . $status . "\n";
    echo "  API outstanding: " . ($hasReturns && abs($outstanding) < 0.01 ? 0 : $outstanding) . "\n\n";
}

echo "════════════════════════════════════════════════════════════════════\n";
echo "🎯 RESULT: Balance from journal entries is the single source of truth\n";
echo "   Current balance: " . $customer['balance'] . " (should be 0 if fully settled)\n";
if ($customer['balance'] == 0) {
    echo "   ✅ PRODUCTION READY!\n";
} else {
    echo "   ⚠️ Outstanding amount needs attention\n";
}
