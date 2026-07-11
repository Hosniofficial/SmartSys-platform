<?php
/**
 * اختبار وضع refund_mode='auto' 
 */
$config = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'], $config['user'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // تحقق من البيانات للعميل #31
    echo "════════════════════════════════════════════════════════\n";
    echo "Sales for Customer #31:\n";
    echo "════════════════════════════════════════════════════════\n";
    
    $stmt = $pdo->prepare('
        SELECT id, invoice_number, net_total_amount, tax_amount, paid_amount, status,
               (net_total_amount + IFNULL(tax_amount, 0)) - IFNULL(paid_amount, 0) as outstanding
        FROM sales
        WHERE customer_id = 31 AND tenant_id = 1 
        ORDER BY id DESC LIMIT 10
    ');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
        $total = $row['net_total_amount'] + $row['tax_amount'];
        echo "Invoice #{$row['invoice_number']}: ";
        echo "Total={$total}, Paid={$row['paid_amount']}, Outstanding={$row['outstanding']}, Status={$row['status']}\n";
    }
    
    // إجمالي الديون (حسب الدالة في ReturnService)
    echo "\n════════════════════════════════════════════════════════\n";
    echo "Total Customer Outstanding (should be used for 'auto' mode):\n";
    echo "════════════════════════════════════════════════════════\n";
    
    $stmt2 = $pdo->prepare('
        SELECT COALESCE(SUM((net_total_amount + IFNULL(tax_amount,0)) - IFNULL(paid_amount,0)), 0) as total_outstanding
        FROM sales
        WHERE customer_id = 31 AND tenant_id = 1 AND status = "active" 
          AND ((net_total_amount + IFNULL(tax_amount,0)) - IFNULL(paid_amount,0)) > 0
    ');
    $stmt2->execute();
    $totalDebt = $stmt2->fetchColumn();
    echo "Total Outstanding: {$totalDebt}\n";
    
    // اختبار السيناريو
    echo "\n════════════════════════════════════════════════════════\n";
    echo "TEST SCENARIO - Create return for paid invoice with auto mode:\n";
    echo "════════════════════════════════════════════════════════\n";
    
    // فترض أن الفاتورة الأخيرة المسددة هي رقم 784
    $stmt3 = $pdo->prepare('
        SELECT id, invoice_number, net_total_amount, tax_amount, paid_amount,
               (net_total_amount + IFNULL(tax_amount, 0)) as grand_total,
               (net_total_amount + IFNULL(tax_amount, 0)) - IFNULL(paid_amount, 0) as outstanding
        FROM sales
        WHERE customer_id = 31 AND tenant_id = 1
        ORDER BY id DESC LIMIT 1
    ');
    $stmt3->execute();
    $lastInvoice = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    if ($lastInvoice) {
        $saleOutstanding = max(0, (float)$lastInvoice['grand_total'] - (float)$lastInvoice['paid_amount']);
        $grandTotal = (float)$lastInvoice['grand_total']; // return amount = invoice total
        
        echo "Last Invoice #{$lastInvoice['invoice_number']}:\n";
        echo "  - Grand Total: {$lastInvoice['grand_total']}\n";
        echo "  - Paid Amount: {$lastInvoice['paid_amount']}\n";
        echo "  - Outstanding on this invoice: {$saleOutstanding}\n";
        
        echo "\nReturn Parameters:\n";
        echo "  - Return Amount (grandTotal): {$grandTotal}\n";
        echo "  - Sale Outstanding: {$saleOutstanding}\n";
        echo "  - Customer Total Outstanding: {$totalDebt}\n";
        echo "  - refund_mode: auto\n";
        
        echo "\nLogic Flow:\n";
        if ($saleOutstanding > 0) {
            echo "  saleOutstanding > 0 → Branch A\n";
        } else {
            echo "  saleOutstanding == 0 → Branch B (fetching customer total outstanding)\n";
            $deductFromCustomerBalance = min($totalDebt, $grandTotal);
            $paidAmount = round(max(0, $grandTotal - $deductFromCustomerBalance), 2);
            
            echo "  - deductFromCustomerBalance = min({$totalDebt}, {$grandTotal}) = {$deductFromCustomerBalance}\n";
            echo "  - paid_amount = max(0, {$grandTotal} - {$deductFromCustomerBalance}) = {$paidAmount}\n";
            
            echo "\nExpected Result:\n";
            if ($paidAmount == 0) {
                echo "  ✓ CORRECT: paid_amount = 0 (debt will be deducted, no cash refund)\n";
            } else {
                echo "  ✗ WRONG: paid_amount = {$paidAmount} (cash refund will be given!)\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
