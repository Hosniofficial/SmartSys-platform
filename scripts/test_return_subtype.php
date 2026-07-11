<?php
require 'config/database.php';

$customerId = 37;
$tenantId = 47;

$db = (new Database())->pdo;

// Simulate getCustomerReferences call
$sqlReturns = "
    SELECT 
        r.id,
        r.created_at AS date,
        r.return_number,
        r.invoice_number,
        r.status,
        r.grand_total AS total_amount,
        r.journal_entry_id,
        (SELECT COUNT(*) FROM payments WHERE return_id = r.id AND tenant_id = r.tenant_id AND amount > 0.01 LIMIT 1) AS has_refund
    FROM returns r
    WHERE r.tenant_id = ?
      AND r.return_type = 'sale'
      AND r.customer_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
";

$stmt = $db->prepare($sqlReturns);
$stmt->execute([$tenantId, $customerId]);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Returns for Customer #$customerId:\n";
echo "═══════════════════════════════════════════════════\n\n";

foreach ($returns as $r) {
    $hasRefund = isset($r['has_refund']) ? (int)$r['has_refund'] : 0;
    $subtype = $hasRefund > 0 ? 'sales_return_refund' : 'sales_return_only';
    
    echo "Return #" . $r['id'] . " (" . $r['return_number'] . ")\n";
    echo "  Status: " . $r['status'] . "\n";
    echo "  Amount: " . $r['total_amount'] . "\n";
    echo "  Has Refund Payment: " . ($hasRefund ? 'YES' : 'NO') . "\n";
    echo "  transaction_subtype: " . $subtype . "\n";
    echo "\n";
}
