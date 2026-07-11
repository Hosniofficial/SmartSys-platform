<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "🔍 فحص العلاقات\n";
echo "════════════════════════════════════════════\n\n";

// احصل على مرتجع من الـ 5
$stmt = $db->prepare("
    SELECT r.id, r.return_number, r.customer_id, r.sale_id, s.customer_id as sale_customer_id
    FROM returns r
    LEFT JOIN sales s ON s.id = r.sale_id
    WHERE r.return_number = 'SR-260528-001' AND r.tenant_id = 47
");
$stmt->execute();
$ret = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Return SR-260528-001:\n";
echo "  customer_id: {$ret['customer_id']}\n";
echo "  sale_id: {$ret['sale_id']}\n";
echo "  sale.customer_id: {$ret['sale_customer_id']}\n\n";

// احصل على الحساب الصحيح
$stmt = $db->prepare("
    SELECT * FROM accounts WHERE tenant_id = 47 AND name LIKE '%محمد%'
");
$stmt->execute();
$accs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Accounts in Tenant 47 with محمد:\n";
foreach ($accs as $acc) {
    echo "  ID: {$acc['id']}, Name: {$acc['name']}, Tenant: {$acc['tenant_id']}\n";
}

echo "\n\n📊 عدد حسابات العملاء في tenant 47:\n";
$stmt = $db->query("SELECT COUNT(*) FROM accounts WHERE tenant_id = 47 AND name LIKE '%حساب العميل%'");
echo "  " . $stmt->fetchColumn() . " حساب\n";

echo "\n📊 عدد حسابات العملاء في tenant 39:\n";
$stmt = $db->query("SELECT COUNT(*) FROM accounts WHERE tenant_id = 39 AND name LIKE '%حساب العميل%'");
echo "  " . $stmt->fetchColumn() . " حساب\n";
