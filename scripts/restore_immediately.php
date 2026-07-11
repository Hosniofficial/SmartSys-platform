<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "\n🚨 رجوع فوري - استرجاع البيانات المحذوفة\n";
echo str_repeat("═", 80) . "\n\n";

$db->beginTransaction();

try {
    // أولاً: استرجع paid_amount إلى 2000
    $stmt = $db->prepare("UPDATE returns SET paid_amount = 2000 WHERE id IN (335, 339, 340, 342, 343) AND tenant_id = 47");
    $stmt->execute();
    
    echo "✅ استرجاع paid_amount = 2000\n\n";
    
    // احصل على المرتجعات
    $stmt = $db->prepare("SELECT id, return_number FROM returns WHERE id IN (335, 339, 340, 342, 343)");
    $stmt->execute();
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // احصل على account_id للعميل (واحد فقط يكفي)
    $stmt = $db->prepare("SELECT id FROM accounts WHERE UPPER(name) LIKE '%عميل%' LIMIT 1");
    $stmt->execute();
    $customerAccountId = $stmt->fetchColumn();
    
    $restoredCount = 0;
    
    foreach ($returns as $return) {
        // احصل على آخر journal_entry لهذا المرتجع
        $stmt = $db->prepare("
            SELECT id FROM journal_entries
            WHERE reference_id = ? AND reference_type = 'sale_return'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$return['id']]);
        $entryId = $stmt->fetchColumn();
        
        if ($entryId && $customerAccountId) {
            // احصل على cost_center_id من أول سطر في هذا القيد
            $stmt = $db->prepare("SELECT cost_center_id FROM journal_entry_lines WHERE journal_entry_id = ? LIMIT 1");
            $stmt->execute([$entryId]);
            $costCenterId = $stmt->fetchColumn();
            
            // تحقق إذا كان الـ Credit موجود بالفعل
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM journal_entry_lines
                WHERE journal_entry_id = ? AND account_id = ? AND credit_amount = 2000
            ");
            $stmt->execute([$entryId, $customerAccountId]);
            
            if ($stmt->fetchColumn() == 0) {
                // أدرج الـ Credit line
                $stmt = $db->prepare("
                    INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit_amount, credit_amount, description, tenant_id, cost_center_id)
                    VALUES (?, ?, 0, 2000, ?, 47, ?)
                ");
                $stmt->execute([$entryId, $customerAccountId, 'إلغاء دين - المرجع ' . $return['return_number'], $costCenterId]);
                
                echo "✅ " . $return['return_number'] . " - Credit restored\n";
                $restoredCount++;
            }
        }
    }
    
    $db->commit();
    
    echo "\n════════════════════════════════════════════════════════════════════\n";
    echo "✅ تم الاسترجاع الفوري!\n";
    echo "   • المرتجعات المستعادة: $restoredCount\n";
    echo "   • paid_amount = 2000 (استرجاع)\n";
    echo "   • Credit restored (إلغاء الديون)\n";
    echo "   • Debit محذوف (الصرف النقدي الخاطئ - تم مسبقاً)\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n";
