<?php
$db = new PDO('mysql:host=localhost;dbname=inventory', 'root', '');

// Check invoice 782
$stmt = $db->query('SELECT id, invoice_number, net_total_amount, paid_amount, status FROM sales WHERE id = 782');
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Current state of invoice #782 in database:\n";
echo str_repeat("-", 60) . "\n";
foreach ($invoice as $k => $v) {
    echo "$k: $v\n";
}

// The issue: paid_amount should be 0 when status is closed_by_return
// and there's no actual payment, just a return credit

echo "\n\nAnalysis:\n";
echo "- Status is 'closed_by_return' ✓ (correct, return offset the debt)\n";
echo "- paid_amount is " . $invoice['paid_amount'] . " (should be 0 since no actual payment)\n";

if ($invoice['status'] === 'closed_by_return' && $invoice['paid_amount'] == 0) {
    echo "\n✓ Database is already in correct state\n";
} elseif ($invoice['status'] === 'closed_by_return' && $invoice['paid_amount'] > 0) {
    echo "\n⚠ Database state is inconsistent:\n";
    echo "  - Status says 'closed_by_return' (no real payment)\n";
    echo "  - But paid_amount = " . $invoice['paid_amount'] . " (indicates payment was made)\n";
    echo "\nFix: Update sales table to set paid_amount=0 for closed_by_return invoices\n";
}
