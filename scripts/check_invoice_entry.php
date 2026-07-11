<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "🔍 فحص قيد فاتورة واحدة\n";
echo "════════════════════════════════════════════════\n\n";

// احصل على فاتورة
$stmt = $db->prepare("
    SELECT s.id, s.invoice_number, s.total_amount, j.id as entry_id
    FROM sales s
    LEFT JOIN journal_entries j ON j.reference_id = s.id AND j.reference_type = 'sale'
    WHERE s.invoice_number = 'S-260528-003' AND s.tenant_id = 47
");
$stmt->execute();
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📌 الفاتورة: {$sale['invoice_number']}\n";
echo "   الإجمالي: {$sale['total_amount']}\n";
echo "   قيد ID: {$sale['entry_id']}\n\n";

if ($sale['entry_id']) {
    $stmt = $db->prepare("
        SELECT jel.account_id, acc.name, jel.debit_amount, jel.credit_amount, jel.description
        FROM journal_entry_lines jel
        LEFT JOIN accounts acc ON acc.id = jel.account_id
        WHERE jel.journal_entry_id = ?
        ORDER BY jel.id
    ");
    $stmt->execute([$sale['entry_id']]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "السطور:\n";
    foreach ($lines as $l) {
        echo "  {$l['name']}: D={$l['debit_amount']}, C={$l['credit_amount']}\n";
    }
} else {
    echo "❌ لا يوجد قيد لهذه الفاتورة!\n";
}
