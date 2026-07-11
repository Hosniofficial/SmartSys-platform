<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

// احصل على الفاتورة الأصلية
$stmt = $db->prepare("
    SELECT r.id, r.return_number, r.customer_id, r.grand_total, r.paid_amount,
           s.invoice_number, c.name as customer_name
    FROM returns r
    LEFT JOIN sales s ON s.id = r.sale_id
    LEFT JOIN customers c ON c.id = r.customer_id
    WHERE r.return_number IN ('SR-260528-001', 'SR-260528-005', 'SR-260528-006')
    AND r.tenant_id = 47
");
$stmt->execute();
$rets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📋 المرتجعات:\n";
foreach ($rets as $r) {
    echo "\n{$r['return_number']}:\n";
    echo "  Customer: {$r['customer_name']} (ID: {$r['customer_id']})\n";
    echo "  Grand Total: {$r['grand_total']}\n";
    echo "  Paid Amount: {$r['paid_amount']}\n";
    echo "  Invoice: {$r['invoice_number']}\n";
    
    // احصل على حسابات هذا العميل في Tenant 47
    $stmt2 = $db->prepare("
        SELECT id, name FROM accounts WHERE tenant_id = 47 AND type = 'asset' AND name LIKE CONCAT('%', ?, '%')
    ");
    $stmt2->execute([$r['customer_name']]);
    $accs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if ($accs) {
        echo "  Possible Accounts in Tenant 47:\n";
        foreach ($accs as $acc) {
            echo "    - {$acc['name']} (ID: {$acc['id']})\n";
        }
    }
}
