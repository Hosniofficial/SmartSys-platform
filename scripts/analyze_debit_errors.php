<?php
// Database connection
$dsn = 'mysql:host=localhost;dbname=inventory;charset=utf8mb4';
$user = 'root';
$pass = '';
$db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "\n📊 تحليل مشكلة debit lines الزيادة في المرتجعات\n";
echo str_repeat("═", 70) . "\n\n";

// احسب عدد المرتجعات الكلي
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT r.id) as count
    FROM returns r
    WHERE r.tenant_id = 1
");
$stmt->execute();
$totalReturns = $stmt->fetchColumn();

// احسب المرتجعات بـ dual lines (credit + debit لنفس العميل)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT je.reference_id) as count
    FROM journal_entries je
    INNER JOIN journal_entry_lines jel1 ON jel1.journal_entry_id = je.id AND jel1.debit_amount > 0
    INNER JOIN journal_entry_lines jel2 ON jel2.journal_entry_id = je.id AND jel2.credit_amount > 0
    WHERE je.reference_type = 'sale_return' 
      AND je.tenant_id = 1
      AND jel1.account_id = jel2.account_id
");
$stmt->execute();
$returnWithDualLines = $stmt->fetchColumn();

echo "📈 الإحصائيات:\n";
echo str_repeat("─", 70) . "\n";
echo "  إجمالي المرتجعات (Tenant 1):        $totalReturns\n";
echo "  المرتجعات بـ dual lines (الخطأ):    $returnWithDualLines\n";
if ($totalReturns > 0) {
    echo "  نسبة التأثر:                        " . round(($returnWithDualLines / $totalReturns * 100)) . "%\n";
}
echo "\n";

// احسب التفاصيل - عينة
echo "📋 عينة من المرتجعات بالمشكلة:\n";
echo str_repeat("─", 70) . "\n";
echo sprintf("%-15s | %-12s | %-12s | %-12s\n", "المرجع", "التاريخ", "Debit", "Credit");
echo str_repeat("─", 70) . "\n";

$stmt = $db->prepare("
    SELECT 
        r.id,
        r.return_number,
        r.created_at,
        SUM(CASE WHEN jel.debit_amount > 0 THEN jel.debit_amount ELSE 0 END) as total_debit,
        SUM(CASE WHEN jel.credit_amount > 0 THEN jel.credit_amount ELSE 0 END) as total_credit
    FROM returns r
    INNER JOIN journal_entries je ON je.reference_id = r.id AND je.reference_type = 'sale_return'
    INNER JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id
    WHERE r.tenant_id = 1
    GROUP BY r.id
    HAVING total_debit > 0 AND total_credit > 0
    ORDER BY r.created_at DESC
    LIMIT 15
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo sprintf(
        "%-15s | %12s | %12.2f | %12.2f\n",
        $row['return_number'],
        substr($row['created_at'], 0, 10),
        $row['total_debit'],
        $row['total_credit']
    );
}

echo "\n";

// تحليل الأوقات
echo "🕐 تحليل متى بدأت المشكلة:\n";
echo str_repeat("─", 70) . "\n";

$stmt = $db->prepare("
    SELECT 
        DATE(r.created_at) as date,
        COUNT(r.id) as total_returns,
        SUM(CASE 
            WHEN EXISTS (
                SELECT 1 FROM journal_entries je2
                INNER JOIN journal_entry_lines jel1 ON jel1.journal_entry_id = je2.id AND jel1.debit_amount > 0
                INNER JOIN journal_entry_lines jel2 ON jel2.journal_entry_id = je2.id AND jel2.credit_amount > 0
                WHERE je2.reference_id = r.id AND je2.reference_type = 'sale_return'
                  AND jel1.account_id = jel2.account_id
            ) THEN 1 ELSE 0 END
        ) as with_error
    FROM returns r
    WHERE r.tenant_id = 1
    GROUP BY DATE(r.created_at)
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute();
$dateResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo sprintf("%-12s | %-15s | %-15s | %s\n", "التاريخ", "إجمالي", "بها خطأ", "النسبة");
echo str_repeat("─", 70) . "\n";

foreach ($dateResults as $row) {
    $percentage = $row['total_returns'] > 0 ? round(($row['with_error'] / $row['total_returns'] * 100)) : 0;
    echo sprintf(
        "%-12s | %15d | %15d | %3d%%\n",
        $row['date'],
        $row['total_returns'],
        $row['with_error'],
        $percentage
    );
}

echo "\n";

// النتيجة
echo "✅ الخلاصة:\n";
echo str_repeat("─", 70) . "\n";

if ($returnWithDualLines > 0) {
    if ($returnWithDualLines / $totalReturns >= 0.8) {
        echo "  ⚠️  المشكلة تؤثر على معظم المرتجعات (80%+)\n";
        echo "  🔴 هذا يدل على أن status='active' كان الفلتر الأساسي الخاطئ\n";
        echo "  ✅ التصحيح الذي أجريناه سيحل المشكلة للمرتجعات الجديدة\n";
    } else {
        echo "  ⚠️  المشكلة تؤثر على بعض المرتجعات ($returnWithDualLines من $totalReturns)\n";
        echo "  🔍 قد تكون هناك حالات حدية أخرى\n";
    }
} else {
    echo "  ✅ لا توجد مرتجعات بهذا الخطأ (قد تكون المشكلة محصورة في tenant معين)\n";
}

echo "\n";
