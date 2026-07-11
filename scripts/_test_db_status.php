<?php
/**
 * فحص شامل للفواتير والعملاء والمرتجعات
 */

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$db   = $_ENV['DB_DATABASE'] ?? 'inventory';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

$pdo = new PDO(
    "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          فحص بيانات الفواتير والعملاء والمرتجعات            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// 1. العملاء
echo "👥 العملاء:\n";
echo str_repeat("─", 70) . "\n";
$stmt = $pdo->query("SELECT id, name FROM customers WHERE tenant_id = 1 LIMIT 10");
$customers = $stmt->fetchAll();
foreach ($customers as $cust) {
    echo "  - #{$cust['id']}: {$cust['name']}\n";
}

// 2. الفواتير
echo "\n📄 الفواتير (آخر 10):\n";
echo str_repeat("─", 70) . "\n";
$stmt = $pdo->query("
    SELECT s.id, s.invoice_number, c.name, s.net_total_amount, s.tax_amount, s.paid_amount,
           (s.net_total_amount + IFNULL(s.tax_amount, 0)) as grand_total,
           (s.net_total_amount + IFNULL(s.tax_amount, 0)) - IFNULL(s.paid_amount, 0) as outstanding,
           s.status
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    WHERE s.tenant_id = 1
    ORDER BY s.id DESC
    LIMIT 20
");
$invoices = $stmt->fetchAll();
foreach ($invoices as $inv) {
    $outstand = $inv['outstanding'];
    $statusIcon = $outstand > 0 ? '🔴' : '✅';
    echo "  {$statusIcon} #{$inv['invoice_number']} - {$inv['name']}: المجموع={$inv['grand_total']}, المسدد={$inv['paid_amount']}, المستحق={$outstand}, الحالة={$inv['status']}\n";
}

// 3. المرتجعات
echo "\n🔄 المرتجعات:\n";
echo str_repeat("─", 70) . "\n";
$stmt = $pdo->query("
    SELECT r.id, r.return_number, r.return_type, s.invoice_number, r.total_amount, 
           r.paid_amount, r.status, r.created_at
    FROM returns r
    LEFT JOIN sales s ON r.sale_id = s.id
    WHERE r.tenant_id = 1
    ORDER BY r.id DESC
    LIMIT 20
");
$returns = $stmt->fetchAll();
if (!empty($returns)) {
    foreach ($returns as $ret) {
        echo "  #{$ret['return_number']} ({$ret['return_type']}) - الفاتورة #{$ret['invoice_number']}: المبلغ={$ret['total_amount']}, المدفوع={$ret['paid_amount']}, الحالة={$ret['status']}\n";
    }
} else {
    echo "  لا توجد مرتجعات\n";
}

// 4. الحسابات
echo "\n💰 الدفعات:\n";
echo str_repeat("─", 70) . "\n";
$stmt = $pdo->query("
    SELECT p.id, p.type, r.return_number, p.amount, p.payment_date, p.status
    FROM payments p
    LEFT JOIN returns r ON p.return_id = r.id
    WHERE p.tenant_id = 1
    ORDER BY p.id DESC
    LIMIT 20
");
$payments = $stmt->fetchAll();
if (!empty($payments)) {
    foreach ($payments as $pay) {
        echo "  #{$pay['id']} ({$pay['type']}) - المرتجع {$pay['return_number']}: {$pay['amount']}, الحالة={$pay['status']}\n";
    }
} else {
    echo "  لا توجد دفعات\n";
}

// 5. ملخص الحالة
echo "\n📊 الملخص:\n";
echo str_repeat("─", 70) . "\n";
echo "  عدد العملاء: " . count($customers) . "\n";
echo "  عدد الفواتير: " . count($invoices) . "\n";
echo "  عدد المرتجعات: " . count($returns) . "\n";
echo "  عدد الدفعات: " . count($payments) . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM sales WHERE tenant_id = 1 AND (net_total_amount + IFNULL(tax_amount, 0)) - IFNULL(paid_amount, 0) > 0");
echo "  عدد الفواتير المستحقة: " . $stmt->fetchColumn() . "\n";

echo "\n";
