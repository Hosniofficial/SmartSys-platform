<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "🔍 فحص جميع الفاتورات والمرتجعات\n";
echo "════════════════════════════════════════════\n\n";

// احصل على جميع الفاتورات
$stmt = $db->prepare("
    SELECT s.invoice_number, s.total_amount, s.paid_amount, j.id as entry_id
    FROM sales s
    LEFT JOIN journal_entries j ON j.reference_id = s.id AND j.reference_type = 'sale'
    WHERE s.tenant_id = 47 AND s.invoice_number LIKE 'S-260528-%'
    ORDER BY s.invoice_number
");
$stmt->execute();
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sales as $s) {
    echo "📌 {$s['invoice_number']} | Total: {$s['total_amount']} | Paid: {$s['paid_amount']}\n";
    
    if ($s['entry_id']) {
        $stmt2 = $db->prepare("
            SELECT acc.name, SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit
            FROM journal_entry_lines jel
            LEFT JOIN accounts acc ON acc.id = jel.account_id
            WHERE jel.journal_entry_id = ?
            GROUP BY jel.account_id, acc.name
            ORDER BY acc.name
        ");
        $stmt2->execute([$s['entry_id']]);
        $lines = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $totalDebit = 0;
        $totalCredit = 0;
        
        foreach ($lines as $l) {
            echo "     {$l['name']}: D={$l['total_debit']}, C={$l['total_credit']}\n";
            $totalDebit += floatval($l['total_debit']);
            $totalCredit += floatval($l['total_credit']);
        }
        
        echo "     ─────────────────────────────\n";
        echo "     Totals: D=$totalDebit, C=$totalCredit ";
        echo ($totalDebit == $totalCredit) ? "✅" : "❌";
        echo "\n\n";
    } else {
        echo "     ❌ لا يوجد قيد\n\n";
    }
}
