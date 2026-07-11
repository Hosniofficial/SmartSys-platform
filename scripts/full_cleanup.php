<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "🔧 تنظيف جميع المرتجعات الـ 10\n";
echo "════════════════════════════════════════════════════\n\n";

$allReturns = ['SR-260528-001', 'SR-260528-002', 'SR-260528-003', 'SR-260528-004', 
               'SR-260528-005', 'SR-260528-006', 'SR-260528-007', 'SR-260528-008', 
               'SR-260528-009', 'SR-260529-001'];

foreach ($allReturns as $retNum) {
    echo "📍 معالجة $retNum...\n";
    
    // احصل على معرف المرتجع
    $stmt = $db->prepare("SELECT id FROM returns WHERE return_number = ? AND tenant_id = 47");
    $stmt->execute([$retNum]);
    $retId = $stmt->fetchColumn();
    
    if (!$retId) {
        echo "   ❌ لم يتم العثور على المرتجع\n";
        continue;
    }
    
    // احصل على معرف القيد
    $stmt = $db->prepare("SELECT id FROM journal_entries WHERE reference_id = ? AND reference_type = 'sale_return'");
    $stmt->execute([$retId]);
    $entryId = $stmt->fetchColumn();
    
    if (!$entryId) {
        echo "   ❌ لم يتم العثور على القيد\n";
        continue;
    }
    
    // احصل على جميع السطور
    $stmt = $db->prepare("
        SELECT jel.id, acc.name, jel.debit_amount, jel.credit_amount
        FROM journal_entry_lines jel
        LEFT JOIN accounts acc ON acc.id = jel.account_id
        WHERE jel.journal_entry_id = ?
        ORDER BY jel.id
    ");
    $stmt->execute([$entryId]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   سطور موجودة: " . count($lines) . "\n";
    
    // احذف جميع السطور
    $stmt = $db->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?");
    $stmt->execute([$entryId]);
    echo "   ✅ تم حذف جميع السطور\n";
    
    // احصل على بيانات المرتجع
    $stmt = $db->prepare("
        SELECT r.sale_id, r.grand_total FROM returns r WHERE r.id = ?
    ");
    $stmt->execute([$retId]);
    $retData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$retData) {
        echo "   ❌ بيانات المرتجع غير موجودة\n";
        continue;
    }
    
    // احصل على الفاتورة الأصلية
    $stmt = $db->prepare("
        SELECT s.id, s.customer_id FROM sales s WHERE s.id = ?
    ");
    $stmt->execute([$retData['sale_id']]);
    $saleData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // احصل على معرف حساب العميل
    $stmt = $db->prepare("
        SELECT id FROM accounts WHERE customer_id = ? AND tenant_id = 47 LIMIT 1
    ");
    $stmt->execute([$saleData['customer_id']]);
    $customerAccountId = $stmt->fetchColumn();
    
    if (!$customerAccountId) {
        echo "   ❌ حساب العميل غير موجود\n";
        continue;
    }
    
    // احصل على cost_center_id من قيود أخرى
    $stmt = $db->query("SELECT DISTINCT cost_center_id FROM journal_entry_lines LIMIT 1");
    $costCenterId = $stmt->fetchColumn() ?: 1;
    
    try {
        // أدرج السطور الصحيحة فقط
        // 1. Debit: مردودات المبيعات + Credit: حساب العميل (إلغاء الدين)
        $stmt = $db->prepare("
            INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit_amount, credit_amount, description, tenant_id, cost_center_id)
            VALUES (?, ?, ?, 0, ?, 47, ?)
        ");
        
        // احصل على معرف حساب مردودات المبيعات
        $stmt2 = $db->prepare("SELECT id FROM accounts WHERE name LIKE '%مردودات%' AND tenant_id = 47 LIMIT 1");
        $stmt2->execute();
        $returnAccId = $stmt2->fetchColumn();
        
        if ($returnAccId) {
            $stmt->execute([$entryId, $returnAccId, $retData['grand_total'], "مردودات مبيعات - $retNum", $costCenterId]);
            echo "   ✅ Debit: مردودات المبيعات\n";
        }
        
        // 2. Credit: حساب العميل
        $stmt = $db->prepare("
            INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit_amount, credit_amount, description, tenant_id, cost_center_id)
            VALUES (?, ?, 0, ?, ?, 47, ?)
        ");
        $stmt->execute([$entryId, $customerAccountId, $retData['grand_total'], "إلغاء دين - $retNum", $costCenterId]);
        echo "   ✅ Credit: حساب العميل\n";
        
        // 3. ابحث عن سطور المخزون والـ COGS من قيود أخرى
        $stmt = $db->prepare("
            SELECT jel.account_id, jel.debit_amount, jel.credit_amount, acc.name
            FROM journal_entry_lines jel
            LEFT JOIN accounts acc ON acc.id = jel.account_id
            WHERE jel.account_id IN (
                SELECT id FROM accounts WHERE name LIKE '%مخزون%' OR name LIKE '%تكلفة%'
            )
            LIMIT 2
        ");
        $stmt->execute();
        $costsLines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($costsLines as $costLine) {
            $stmt = $db->prepare("
                INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit_amount, credit_amount, description, tenant_id, cost_center_id)
                VALUES (?, ?, ?, ?, ?, 47, ?)
            ");
            $stmt->execute([
                $entryId, 
                $costLine['account_id'], 
                $costLine['debit_amount'], 
                $costLine['credit_amount'],
                $costLine['name'],
                $costCenterId
            ]);
        }
        echo "   ✅ تم إضافة سطور المخزون والـ COGS\n";
        
    } catch (Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "════════════════════════════════════════════════════\n";
echo "✅ انتهى التنظيف!\n";
