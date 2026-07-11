<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "🔍 فحص المرتجعات بعد التنظيف\n";
echo "════════════════════════════════════════════\n\n";

// احصل على جميع المرتجعات
$stmt = $db->prepare("
    SELECT r.return_number, r.grand_total, r.paid_amount, j.id as entry_id
    FROM returns r
    LEFT JOIN journal_entries j ON j.reference_id = r.id AND j.reference_type = 'sale_return'
    WHERE r.tenant_id = 47 AND r.return_number LIKE 'SR-260528-%'
    ORDER BY r.return_number
");
$stmt->execute();
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($returns as $r) {
    echo "📌 {$r['return_number']} | Grand: {$r['grand_total']} | Paid: {$r['paid_amount']}\n";
    
    if ($r['entry_id']) {
        $stmt2 = $db->prepare("
            SELECT acc.name, jel.debit_amount, jel.credit_amount
            FROM journal_entry_lines jel
            LEFT JOIN accounts acc ON acc.id = jel.account_id
            WHERE jel.journal_entry_id = ?
            ORDER BY acc.name
        ");
        $stmt2->execute([$r['entry_id']]);
        $lines = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $totalDebit = 0;
        $totalCredit = 0;
        
        foreach ($lines as $l) {
            echo "     {$l['name']}: D={$l['debit_amount']}, C={$l['credit_amount']}\n";
            $totalDebit += floatval($l['debit_amount']);
            $totalCredit += floatval($l['credit_amount']);
        }
        
        echo "     ─────────────────────────────\n";
        echo "     Totals: D=$totalDebit, C=$totalCredit ";
        echo ($totalDebit == $totalCredit) ? "✅" : "❌";
        echo "\n\n";
    } else {
        echo "     ❌ لا يوجد قيد\n\n";
    }
}
