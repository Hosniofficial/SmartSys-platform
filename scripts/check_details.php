<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "📊 تفاصيل الـ Journal Entries لكل مرتجع\n";
echo "════════════════════════════════════════════════════════\n\n";

// المرتجعات الخمسة التي استرجعناها
$returns = ['SR-260528-001', 'SR-260528-005', 'SR-260528-006', 'SR-260528-008', 'SR-260528-009'];

foreach ($returns as $retNum) {
    $stmt = $db->prepare("
        SELECT je.id, jel.account_id, acc.name, jel.debit_amount, jel.credit_amount
        FROM journal_entries je
        JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id
        LEFT JOIN accounts acc ON acc.id = jel.account_id
        WHERE je.reference_type = 'sale_return' AND je.reference_id IN (
            SELECT id FROM returns WHERE return_number = ? AND tenant_id = 47
        )
        ORDER BY jel.account_id, jel.debit_amount DESC
    ");
    $stmt->execute([$retNum]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📍 $retNum:\n";
    foreach ($lines as $line) {
        $debit = $line['debit_amount'] > 0 ? "Debit: {$line['debit_amount']}" : "";
        $credit = $line['credit_amount'] > 0 ? "Credit: {$line['credit_amount']}" : "";
        $amount = trim($debit . " " . $credit);
        echo "   - Account: {$line['name']} | $amount\n";
    }
    echo "\n";
}
