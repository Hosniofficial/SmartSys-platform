<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "🔍 فحص جميع المرتجعات في Tenant 47\n";
echo "════════════════════════════════════════════\n\n";

$stmt = $db->prepare("
    SELECT r.id, r.return_number, r.grand_total, r.paid_amount,
           COUNT(DISTINCT CASE WHEN jel.debit_amount > 0 THEN jel.id END) as debit_count,
           COUNT(DISTINCT CASE WHEN jel.credit_amount > 0 THEN jel.id END) as credit_count
    FROM returns r
    LEFT JOIN journal_entries je ON je.reference_id = r.id AND je.reference_type = 'sale_return'
    LEFT JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id
    WHERE r.tenant_id = 47
    GROUP BY r.id, r.return_number, r.grand_total, r.paid_amount
    ORDER BY r.return_number
");
$stmt->execute();
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($returns as $r) {
    $status = "";
    if ($r['debit_count'] > 0 && $r['credit_count'] > 0) {
        $status = "⚠️ DUAL LINES (Debit + Credit)";
    } elseif ($r['debit_count'] > 0) {
        $status = "❌ ONLY DEBIT (Wrong)";
    } elseif ($r['credit_count'] > 0) {
        $status = "✅ ONLY CREDIT (Correct)";
    } else {
        $status = "⚪ NO LINES";
    }
    
    echo "{$r['return_number']} | paid={$r['paid_amount']} | Debit:{$r['debit_count']}/Credit:{$r['credit_count']} | {$status}\n";
}
