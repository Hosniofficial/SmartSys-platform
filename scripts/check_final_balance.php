<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "📊 حالة الحساب الحالية للعميل محمد حسني 2 (ID: 31)\n";
echo "════════════════════════════════════════════════════════\n\n";

// احصل على حساب العميل
$stmt = $db->prepare("
    SELECT * FROM accounts WHERE tenant_id = 47 AND name LIKE '%محمد حسني 2%' LIMIT 1
");
$stmt->execute();
$acc = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📌 حساب العميل:\n";
echo "   ID: {$acc['id']}\n";
echo "   Name: {$acc['name']}\n";
echo "   Type: {$acc['type']}\n";
echo "   Debit Balance: {$acc['debit_balance']}\n";
echo "   Credit Balance: {$acc['credit_balance']}\n";
echo "   Net: " . ($acc['debit_balance'] - $acc['credit_balance']) . "\n\n";

// احصل على آخر 20 حركة
$stmt = $db->prepare("
    SELECT je.id, je.created_at, je.reference_type, je.description,
           SUM(CASE WHEN jel.account_id = ? AND jel.debit_amount > 0 THEN jel.debit_amount ELSE 0 END) as debit,
           SUM(CASE WHEN jel.account_id = ? AND jel.credit_amount > 0 THEN jel.credit_amount ELSE 0 END) as credit
    FROM journal_entries je
    LEFT JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id
    WHERE je.tenant_id = 47 AND jel.account_id = ?
    GROUP BY je.id, je.created_at, je.reference_type, je.description
    ORDER BY je.created_at DESC
    LIMIT 20
");
$stmt->execute([$acc['id'], $acc['id'], $acc['id']]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📋 آخر 20 حركة:\n";
$balance = 0;
$entries = array_reverse($entries); // أقدم الأولى

foreach ($entries as $e) {
    $debit = floatval($e['debit']) ?: 0;
    $credit = floatval($e['credit']) ?: 0;
    $balance += ($debit - $credit);
    
    $type = $e['reference_type'] ?? 'unknown';
    $description = $e['description'] ?? '';
    
    echo sprintf("   %s | D:%7.2f | C:%7.2f | Balance: %7.2f | %s\n",
        $e['created_at'], $debit, $credit, $balance, $type);
}

echo "\n✅ الرصيد النهائي: " . $balance . " (";
echo ($balance > 0) ? "دين على العميل" : (($balance < 0) ? "رصيد دائن" : "متساوي");
echo ")\n";
