<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

// البحث عن المرتجعات المذكورة
$stmt = $db->prepare("SELECT * FROM returns WHERE return_number IN ('SR-260528-005', 'SR-260528-006', 'SR-260528-008') LIMIT 5");
$stmt->execute();
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "المرتجعات المذكورة:\n";
foreach ($returns as $r) {
    echo "- {$r['return_number']} (Tenant: {$r['tenant_id']}, ID: {$r['id']})\n";
}

if (empty($returns)) {
    echo "لا توجد مرتجعات بهذه الأرقام\n";
    echo "\nالمرتجعات الموجودة (آخر 10):\n";
    $stmt = $db->query("SELECT return_number, tenant_id, id FROM returns ORDER BY created_at DESC LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['return_number']} (Tenant: {$row['tenant_id']})\n";
    }
}
