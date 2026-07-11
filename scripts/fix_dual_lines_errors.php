<?php
require 'config/bootstrap.php';

$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "\n🔧 اسكريبت تصحيح المرتجعات بـ dual lines\n";
echo str_repeat("═", 80) . "\n\n";

// احصل على المرتجعات بـ dual lines في Tenant 47
$stmt = $db->prepare("
    SELECT DISTINCT
        r.id,
        r.return_number,
        r.tenant_id,
        je.id as entry_id
    FROM returns r
    INNER JOIN journal_entries je ON je.reference_id = r.id AND je.reference_type = 'sale_return'
    INNER JOIN journal_entry_lines jel1 ON jel1.journal_entry_id = je.id AND jel1.debit_amount > 0
    INNER JOIN journal_entry_lines jel2 ON jel2.journal_entry_id = je.id AND jel2.credit_amount > 0
    WHERE r.tenant_id = 47
      AND jel1.account_id = jel2.account_id
    ORDER BY r.created_at
");
$stmt->execute();
$affectedReturns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📋 المرتجعات المتأثرة: " . count($affectedReturns) . "\n";
echo str_repeat("─", 80) . "\n";

foreach ($affectedReturns as $idx => $return) {
    echo "\n" . ($idx + 1) . ". " . $return['return_number'] . " (Entry ID: " . $return['entry_id'] . ")\n";
    
    // احصل على تفاصيل القيد
    $stmt = $db->prepare("
        SELECT 
            jel.id as line_id,
            jel.account_id,
            a.name as account_name,
            jel.debit_amount,
            jel.credit_amount
        FROM journal_entry_lines jel
        LEFT JOIN accounts a ON a.id = jel.account_id
        WHERE jel.journal_entry_id = ?
        ORDER BY jel.id
    ");
    $stmt->execute([$return['entry_id']]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // اعثر على حساب العميل
    $customerAccountId = null;
    $debitLineId = null;
    $debitAmount = 0;
    
    foreach ($lines as $line) {
        if ($line['debit_amount'] > 0 && stripos($line['account_name'], 'عميل') !== false) {
            $customerAccountId = $line['account_id'];
            $debitLineId = $line['line_id'];
            $debitAmount = $line['debit_amount'];
            break;
        }
    }
    
    if ($debitLineId && $debitAmount > 0) {
        echo "  ❌ حساب العميل (debit خاطئ): " . $debitAmount . "\n";
    }
}

echo "\n\n";

// ============================================================================
// التصحيح: عكس القيود الخاطئة وإنشاء قيود صحيحة
// ============================================================================

echo "🔄 بدء التصحيح...\n";
echo str_repeat("─", 80) . "\n\n";

$db->beginTransaction();
$corrected = 0;
$errors = [];

try {
    foreach ($affectedReturns as $return) {
        $returnId = $return['id'];
        $entryId = $return['entry_id'];
        $returnNumber = $return['return_number'];
        
        try {
            // احصل على جميع سطور القيد الأصلي
            $stmt = $db->prepare("
                SELECT 
                    jel.id as line_id,
                    jel.account_id,
                    jel.debit_amount,
                    jel.credit_amount,
                    jel.description,
                    a.name as account_name
                FROM journal_entry_lines jel
                LEFT JOIN accounts a ON a.id = jel.account_id
                WHERE jel.journal_entry_id = ?
                ORDER BY jel.id
            ");
            $stmt->execute([$entryId]);
            $originalLines = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ابحث عن account_id الذي يظهر مرتين (مرة debit ومرة credit)
            $accountDebitCredit = [];
            foreach ($originalLines as $line) {
                $key = $line['account_id'];
                if (!isset($accountDebitCredit[$key])) {
                    $accountDebitCredit[$key] = ['debit' => 0, 'credit' => 0];
                }
                $accountDebitCredit[$key]['debit'] += $line['debit_amount'];
                $accountDebitCredit[$key]['credit'] += $line['credit_amount'];
            }
            
            // ابحث عن الحساب الذي يحتوي على debit و credit معاً
            $deletedCount = 0;
            foreach ($accountDebitCredit as $accountId => $amounts) {
                if ($amounts['debit'] > 0 && $amounts['credit'] > 0) {
                    // هذا هو الحساب الذي به الخطأ - احذف سطر الـ debit منه
                    foreach ($originalLines as $line) {
                        if ($line['account_id'] == $accountId && $line['debit_amount'] > 0) {
                            $stmt = $db->prepare("DELETE FROM journal_entry_lines WHERE id = ?");
                            $stmt->execute([$line['line_id']]);
                            $deletedCount++;
                        }
                    }
                }
            }
            
            echo "✅ " . $returnNumber . "\n";
            echo "   القيد: #" . $entryId . "\n";
            echo "   السطور المحذوفة (debit خاطئ): $deletedCount\n";
            
            $corrected++;
            
        } catch (Exception $e) {
            $errors[] = [
                'return' => $returnNumber,
                'error' => $e->getMessage()
            ];
            echo "❌ " . $returnNumber . " - خطأ: " . $e->getMessage() . "\n";
        }
    }
    
    if ($corrected > 0) {
        $db->commit();
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ تم التصحيح بنجاح!\n";
        echo "   عدد المرتجعات المُصححة: $corrected\n";
        echo "   عدد الأخطاء: " . count($errors) . "\n";
    } else {
        $db->rollBack();
        echo "\n❌ لم يتم تصحيح أي مرتجعات\n";
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ خطأ في التصحيح: " . $e->getMessage() . "\n";
}

echo "\n";
