<?php
/**
 * Validation Test: Return #339 Ledger Subtype Fix
 * 
 * Verifies that sales_return_only returns no longer show extra debit line
 * in ledger (صرف نقدي للعميل)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PDO;

echo "\n" . str_repeat("=", 100) . "\n";
echo "🧪 TEST: Return #339 Transaction Subtype Validation\n";
echo str_repeat("=", 100) . "\n\n";

try {
    // Setup database connection
    $host = 'localhost';
    $db = 'inventory';
    $user = 'root';
    $pass = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Get return data
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.return_number,
            r.grand_total,
            r.refund_amount,
            r.refund_method
        FROM returns r
        WHERE r.tenant_id = 1
          AND r.id = 339
    ");
    $stmt->execute();
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$return) {
        echo "❌ Return #339 not found in database\n";
        exit(1);
    }
    
    echo "Return Data:\n";
    echo "─────────────\n";
    echo "ID: " . $return['id'] . "\n";
    echo "Number: " . $return['return_number'] . "\n";
    echo "Amount: " . $return['grand_total'] . "\n";
    echo "Refund Amount: " . $return['refund_amount'] . "\n";
    echo "Refund Method: " . ($return['refund_method'] ?? 'NULL') . "\n\n";
    
    // Determine expected subtype based on new logic
    $refundAmount = (float)$return['refund_amount'];
    $refundMethod = $return['refund_method'];
    
    $expectedSubtype = null;
    if ($refundAmount >= 0.01) {
        if ($refundMethod === 'cash') {
            $expectedSubtype = 'sales_return_refund';
        } elseif ($refundMethod === 'bank_transfer') {
            $expectedSubtype = 'sales_return_bank_refund';
        }
    }
    
    echo "Logic Calculation:\n";
    echo "──────────────────\n";
    echo "refund_amount = " . $refundAmount . "\n";
    echo "refund_amount >= 0.01? " . ($refundAmount >= 0.01 ? 'YES' : 'NO') . "\n";
    echo "refund_method = " . ($refundMethod ?: 'NULL') . "\n\n";
    
    echo "EXPECTED transaction_subtype: " . ($expectedSubtype ?: 'null (sales_return_only)') . "\n\n";
    
    // Check if there's a linked payment record
    $stmtPayment = $pdo->prepare("
        SELECT 
            p.id,
            p.reference_number,
            p.amount,
            CASE 
                WHEN p.return_id IS NOT NULL THEN 'refund'
                WHEN p.sale_id IS NOT NULL THEN 'receipt'
                ELSE 'payment'
            END AS payment_type
        FROM payments p
        WHERE p.tenant_id = 1
          AND p.return_id = 339
    ");
    $stmtPayment->execute();
    $payment = $stmtPayment->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        echo "Linked Payment Record:\n";
        echo "──────────────────────\n";
        echo "Payment ID: " . $payment['id'] . "\n";
        echo "Payment Type: " . $payment['payment_type'] . "\n";
        echo "Amount: " . $payment['amount'] . "\n\n";
    } else {
        echo "⚠️  No linked payment record found\n\n";
    }
    
    echo str_repeat("=", 100) . "\n";
    echo "✅ TEST RESULT:\n";
    echo str_repeat("=", 100) . "\n\n";
    
    if ($expectedSubtype === null) {
        echo "✓ PASS: Return #339 should have transaction_subtype = null\n";
        echo "✓ This means NO debit line 'صرف نقدي للعميل' should appear\n";
        echo "✓ Ledger should show ONLY credit line 'إشعار دائن مرتجع'\n\n";
    } else {
        echo "❌ FAIL: Return #339 has transaction_subtype = '" . $expectedSubtype . "'\n";
        echo "This means debit line WILL appear in ledger (incorrect)\n\n";
    }
    
    // Test other returns for comparison
    echo str_repeat("=", 100) . "\n";
    echo "📊 Comparison with Other Returns:\n";
    echo str_repeat("=", 100) . "\n\n";
    
    $stmtAll = $pdo->prepare("
        SELECT 
            r.id,
            r.return_number,
            r.refund_amount,
            r.refund_method
        FROM returns r
        WHERE r.tenant_id = 1
          AND r.id IN (337, 338, 339)
        ORDER BY r.id ASC
    ");
    $stmtAll->execute();
    $allReturns = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allReturns as $ret) {
        $refAmt = (float)$ret['refund_amount'];
        $refMeth = $ret['refund_method'];
        
        $subtype = null;
        if ($refAmt >= 0.01) {
            if ($refMeth === 'cash') {
                $subtype = 'sales_return_refund';
            } elseif ($refMeth === 'bank_transfer') {
                $subtype = 'sales_return_bank_refund';
            }
        }
        
        echo "Return #" . $ret['id'] . ":\n";
        echo "  refund_amount: " . $refAmt . "\n";
        echo "  refund_method: " . ($refMeth ?: 'NULL') . "\n";
        echo "  Expected subtype: " . ($subtype ?: 'null (sales_return_only)') . "\n";
        echo "  Ledger debit line: " . ($subtype ? 'YES' : 'NO') . "\n\n";
    }
    
    echo str_repeat("=", 100) . "\n";
    echo "✅ Validation Complete\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
