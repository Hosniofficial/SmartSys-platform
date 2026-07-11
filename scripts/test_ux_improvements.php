<?php
require 'config/database.php';

$customerId = 37;
$tenantId = 47;

$db = (new Database())->pdo;

echo "════════════════════════════════════════════════════════════════════\n";
echo "TEST: Transaction Type Labels and Descriptions\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

// Test 1: Check return #348 with transaction_subtype label
echo "✅ TEST 1: Return #348 with transaction_subtype label\n";
echo "─────────────────────────────────────────────────────────────────\n";

$sqlReturns = "
    SELECT 
        r.id,
        r.return_number,
        r.status,
        r.grand_total AS total_amount,
        EXISTS(
            SELECT 1
            FROM payments p
            WHERE p.return_id = r.id
              AND p.tenant_id = r.tenant_id
              AND p.amount > 0.01
        ) AS has_refund
    FROM returns r
    WHERE r.tenant_id = ?
      AND r.return_type = 'sale'
      AND r.id = 348
    LIMIT 1
";

$stmt = $db->prepare($sqlReturns);
$stmt->execute([$tenantId]);
$ret = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ret) {
    $hasRefund = isset($ret['has_refund']) ? (int)$ret['has_refund'] : 0;
    $subtype = $hasRefund > 0 ? 'sales_return_refund' : 'sales_return_only';
    $typeLabel = $subtype === 'sales_return_refund' ? 'استرداد مرتجع' : 'مرتجع بيع';
    
    echo "  Return #" . $ret['id'] . " (" . $ret['return_number'] . ")\n";
    echo "  Status: " . $ret['status'] . "\n";
    echo "  Amount: " . $ret['total_amount'] . "\n";
    echo "  Has Refund Payment: " . ($hasRefund ? 'YES' : 'NO') . "\n";
    echo "  transaction_subtype: " . $subtype . "\n";
    echo "  type_label: " . $typeLabel . " ✅\n";
} else {
    echo "  Return #348 not found\n";
}

echo "\n";

// Test 2: Check descriptions for sale payments
echo "✅ TEST 2: Sale Payment Descriptions (from journal entries)\n";
echo "─────────────────────────────────────────────────────────────────\n";

$sqlPayments = "
    SELECT 
        jel.id,
        je.entry_date,
        je.description,
        jel.debit_amount,
        jel.credit_amount
    FROM journal_entry_lines jel
    JOIN journal_entries je ON jel.journal_entry_id = je.id AND jel.tenant_id = je.tenant_id
    WHERE je.tenant_id = ?
      AND je.reference_type = 'sale'
      AND jel.debit_amount = 0  -- This is the credit line (payment side)
      AND jel.credit_amount > 0
      AND je.entry_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY je.created_at DESC
    LIMIT 3
";

$stmt = $db->prepare($sqlPayments);
$stmt->execute([$tenantId]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($payments)) {
    foreach ($payments as $idx => $p) {
        echo "  Payment #" . ($idx+1) . ":\n";
        echo "    Description: " . $p['description'] . "\n";
        echo "    Credit Amount: " . $p['credit_amount'] . "\n";
    }
} else {
    echo "  No recent sale payments found\n";
}

echo "\n";

// Test 3: Return transaction descriptions
echo "✅ TEST 3: Return Transaction Descriptions\n";
echo "─────────────────────────────────────────────────────────────────\n";

$sqlReturns = "
    SELECT 
        jel.id,
        je.description,
        jel.debit_amount,
        jel.credit_amount
    FROM journal_entry_lines jel
    JOIN journal_entries je ON jel.journal_entry_id = je.id AND jel.tenant_id = je.tenant_id
    WHERE je.tenant_id = ?
      AND je.reference_type = 'sale_return'
      AND je.entry_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY je.created_at DESC
    LIMIT 5
";

$stmt = $db->prepare($sqlReturns);
$stmt->execute([$tenantId]);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($returns)) {
    foreach ($returns as $idx => $r) {
        echo "  Line #" . ($idx+1) . ":\n";
        echo "    Description: " . $r['description'] . "\n";
        echo "    Debit: " . $r['debit_amount'] . " | Credit: " . $r['credit_amount'] . "\n";
    }
} else {
    echo "  No recent return entries found\n";
}

echo "\n════════════════════════════════════════════════════════════════════\n";
