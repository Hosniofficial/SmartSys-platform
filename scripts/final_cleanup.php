<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "🔧 تنظيف شامل لجميع المرتجعات\n";
echo "════════════════════════════════════════════\n\n";

$returns = ['SR-260528-001', 'SR-260528-002', 'SR-260528-003', 'SR-260528-004', 
            'SR-260528-005', 'SR-260528-006', 'SR-260528-007', 'SR-260528-008', 
            'SR-260528-009', 'SR-260529-001'];

$successCount = 0;
$errorCount = 0;

foreach ($returns as $retNum) {
    try {
        echo "📍 معالجة $retNum...\n";
        
        // احصل على المرتجع
        $stmt = $db->prepare("
            SELECT r.id, r.customer_id, r.grand_total FROM returns r 
            WHERE r.return_number = ? AND r.tenant_id = 47
        ");
        $stmt->execute([$retNum]);
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ret) {
            echo "   ❌ لم يتم العثور على المرتجع\n\n";
            $errorCount++;
            continue;
        }
        
        // احصل على قيد
        $stmt = $db->prepare("
            SELECT id FROM journal_entries 
            WHERE reference_id = ? AND reference_type = 'sale_return' AND tenant_id = 47
            LIMIT 1
        ");
        $stmt->execute([$ret['id']]);
        $entryId = $stmt->fetchColumn();
        
        if (!$entryId) {
            echo "   ❌ لم يتم العثور على القيد\n\n";
            $errorCount++;
            continue;
        }
        
        // احصل على حساب العميل
        $stmt = $db->prepare("
            SELECT id FROM accounts 
            WHERE tenant_id = 47 AND type = 'asset' AND name LIKE CONCAT('%', (SELECT name FROM customers WHERE id = ?), '%')
            LIMIT 1
        ");
        $stmt->execute([$ret['customer_id']]);
        $customerAccId = $stmt->fetchColumn();
        
        if (!$customerAccId) {
            echo "   ⚠️  حساب العميل غير موجود\n";
            // بدلاً من الرجوع، استخدم الحساب الافتراضي
            $stmt = $db->query("SELECT id FROM accounts WHERE tenant_id = 47 AND name LIKE '%حساب العميل%' LIMIT 1");
            $customerAccId = $stmt->fetchColumn();
        }
        
        if (!$customerAccId) {
            echo "   ❌ لا يمكن العثور على أي حساب عميل\n\n";
            $errorCount++;
            continue;
        }
        
        // احصل على cost_center_id
        $stmt = $db->query("SELECT id FROM cost_centers LIMIT 1");
        $costCenterId = $stmt->fetchColumn() ?: 1;
        
        // احذف جميع السطور
        $stmt = $db->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?");
        $stmt->execute([$entryId]);
        
        // أدرج فقط السطور الضرورية والصحيحة
        $stmt = $db->prepare("
            INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit_amount, credit_amount, description, tenant_id, cost_center_id)
            VALUES (?, ?, ?, ?, ?, 47, ?)
        ");
        
        // سطر 1: الرصيد العميل (إلغاء الدين)
        $stmt->execute([$entryId, $customerAccId, 0, round($ret['grand_total'], 2), "إلغاء دين - $retNum", $costCenterId]);
        
        // سطر 2: مردودات المبيعات
        $stmt2 = $db->prepare("SELECT id FROM accounts WHERE tenant_id = 47 AND name LIKE '%مردودات%' LIMIT 1");
        $stmt2->execute();
        $returnAccId = $stmt2->fetchColumn();
        
        if ($returnAccId) {
            $stmt->execute([$entryId, $returnAccId, round($ret['grand_total'], 2), 0, "مردودات - $retNum", $costCenterId]);
        }
        
        // Update paid_amount = 0
        $stmt = $db->prepare("UPDATE returns SET paid_amount = 0 WHERE id = ?");
        $stmt->execute([$ret['id']]);
        
        echo "   ✅ تم التنظيف بنجاح (paid_amount=0)\n\n";
        $successCount++;
        
    } catch (Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n\n";
        $errorCount++;
    }
}

echo "════════════════════════════════════════════\n";
echo "✅ النتائج:\n";
echo "   نجح: $successCount\n";
echo "   فشل: $errorCount\n";
