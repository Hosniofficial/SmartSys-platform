<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

// احصل على السطور الخاطئة
$stmt = $db->prepare("
    SELECT 
        jel.id,
        jel.description,
        jel.debit_amount,
        jel.credit_amount,
        a.name as account_name
    FROM journal_entry_lines jel
    LEFT JOIN accounts a ON a.id = jel.account_id
    WHERE jel.journal_entry_id IN (904, 916, 918, 923, 925)
    ORDER BY jel.journal_entry_id, jel.id
");
$stmt->execute();
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "السطور في القيود المتأثرة:\n";
echo str_repeat("─", 100) . "\n";

foreach ($lines as $line) {
    echo "ID: {$line['id']} | Debit: {$line['debit_amount']} | Credit: {$line['credit_amount']}\n";
    echo "  الحساب: " . $line['account_name'] . "\n";
    echo "  الوصف: " . $line['description'] . "\n";
}
