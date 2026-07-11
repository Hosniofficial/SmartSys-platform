<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "\n🔍 تحليل مشكلة debit lines في المرتجعات المذكورة\n";
echo str_repeat("═", 80) . "\n\n";

// المرتجعات المحددة
$returnIds = [339, 340, 342]; // SR-260528-005, 006, 008

foreach ($returnIds as $returnId) {
    $stmt = $db->prepare("SELECT return_number, tenant_id, id FROM returns WHERE id = ?");
    $stmt->execute([$returnId]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$return) continue;
    
    echo "📌 المرجع: {$return['return_number']} (Tenant: {$return['tenant_id']}, ID: {$returnId})\n";
    echo str_repeat("─", 80) . "\n";
    
    // احصل على journal entries
    $stmt = $db->prepare("
        SELECT 
            je.id,
            je.reference_type,
            je.reference_id,
            je.created_at
        FROM journal_entries je
        WHERE je.reference_id = ? AND je.reference_type = 'sale_return'
    ");
    $stmt->execute([$returnId]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "عدد القيود المحاسبية: " . count($entries) . "\n";
    
    foreach ($entries as $entry) {
        // احصل على السطور
        $stmt = $db->prepare("
            SELECT 
                jel.account_id,
                a.name as account_name,
                jel.debit_amount,
                jel.credit_amount
            FROM journal_entry_lines jel
            LEFT JOIN accounts a ON a.id = jel.account_id
            WHERE jel.journal_entry_id = ?
            ORDER BY jel.id
        ");
        $stmt->execute([$entry['id']]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n  القيد رقم {$entry['id']} ({$entry['created_at']}):\n";
        echo "  " . str_repeat("─", 76) . "\n";
        echo "  " . sprintf("%-40s | %12s | %12s\n", "الحساب", "مدين", "دائن");
        echo "  " . str_repeat("─", 76) . "\n";
        
        $totalDebit = 0;
        $totalCredit = 0;
        
        foreach ($lines as $line) {
            $debit = $line['debit_amount'];
            $credit = $line['credit_amount'];
            $totalDebit += $debit;
            $totalCredit += $credit;
            
            $accountName = $line['account_name'] ?? 'Unknown';
            if (strlen($accountName) > 38) {
                $accountName = substr($accountName, 0, 35) . '...';
            }
            
            echo "  " . sprintf("%-40s | %12.2f | %12.2f\n", $accountName, $debit, $credit);
        }
        
        echo "  " . str_repeat("─", 76) . "\n";
        echo "  " . sprintf("%-40s | %12.2f | %12.2f\n", "الإجمالي", $totalDebit, $totalCredit);
        
        // تحقق من التوازن
        $diff = abs($totalDebit - $totalCredit);
        if ($diff > 0.01) {
            echo "  ⚠️  تحذير: عدم توازن! الفرق: " . $diff . "\n";
        } else {
            echo "  ✅ القيد متوازن\n";
        }
    }
    
    echo "\n";
}

// تحليل شامل لكل tenants
echo "\n" . str_repeat("═", 80) . "\n";
echo "📊 تحليل شامل - عدد المرتجعات بـ dual lines لكل Tenant\n";
echo str_repeat("═", 80) . "\n\n";

$stmt = $db->prepare("
    SELECT 
        r.tenant_id,
        COUNT(DISTINCT r.id) as total_returns,
        SUM(CASE 
            WHEN EXISTS (
                SELECT 1 FROM journal_entries je
                INNER JOIN journal_entry_lines jel1 ON jel1.journal_entry_id = je.id AND jel1.debit_amount > 0
                INNER JOIN journal_entry_lines jel2 ON jel2.journal_entry_id = je.id AND jel2.credit_amount > 0
                WHERE je.reference_id = r.id AND je.reference_type = 'sale_return'
                  AND jel1.account_id = jel2.account_id
            ) THEN 1 ELSE 0 END
        ) as with_error
    FROM returns r
    GROUP BY r.tenant_id
    ORDER BY with_error DESC
");
$stmt->execute();
$tenantStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo sprintf("%-12s | %-15s | %-15s | %s\n", "Tenant", "إجمالي", "بها خطأ", "النسبة");
echo str_repeat("─", 60) . "\n";

foreach ($tenantStats as $stat) {
    $percentage = $stat['total_returns'] > 0 ? round(($stat['with_error'] / $stat['total_returns'] * 100)) : 0;
    echo sprintf(
        "%-12d | %15d | %15d | %3d%%\n",
        $stat['tenant_id'],
        $stat['total_returns'],
        $stat['with_error'],
        $percentage
    );
}

echo "\n";
