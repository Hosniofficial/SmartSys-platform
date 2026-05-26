<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/database.php';
$db = (new Database())->pdo;
$stmt = $db->prepare('SELECT id, total_amount, net_total_amount, paid_amount, status, payment_method_id, journal_entry_id FROM sales WHERE id = 749');
$stmt->execute();
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== بيانات الفاتورة 749 ===\n";
echo "ID: " . $sale['id'] . "\n";
echo "Total: " . $sale['total_amount'] . "\n";
echo "Net Total: " . $sale['net_total_amount'] . "\n";
echo "Paid: " . $sale['paid_amount'] . "\n";
echo "Status: " . $sale['status'] . "\n";
echo "Payment Method ID: " . $sale['payment_method_id'] . "\n";
echo "Journal Entry ID: " . $sale['journal_entry_id'] . "\n";

// Check payments
$stmt2 = $db->prepare('SELECT id, amount, payment_method_id, status, payment_method_id FROM payments WHERE sale_id = 749');
$stmt2->execute();
$payments = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== الدفعات ===\n";
print_r($payments);

// Check journal entry lines
$stmt3 = $db->prepare('
    SELECT jel.account_id, a.name, jel.debit_amount, jel.credit_amount 
    FROM journal_entry_lines jel
    JOIN accounts a ON a.id = jel.account_id
    WHERE jel.journal_entry_id = ' . ($sale['journal_entry_id'] ?? 0) . '
');
$stmt3->execute();
echo "\n=== تفاصيل القيد المحاسبي ===\n";
while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-30s | %10s | %10s\n", $row['name'], $row['debit_amount'], $row['credit_amount']);
}

// Check payment methods details
$stmt4 = $db->prepare('
    SELECT pm.name, pm.kind, p.amount 
    FROM payments p
    JOIN payment_methods pm ON pm.id = p.payment_method_id
    WHERE p.sale_id = 749
');
$stmt4->execute();
echo "\n=== طرق الدفع المستخدمة ===\n";
while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-20s (%s) | %10s\n", $row['name'], $row['kind'], $row['amount']);
}
