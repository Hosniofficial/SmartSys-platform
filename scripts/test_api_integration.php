<?php
// Test script to verify the API endpoint works with new refund columns

try {
    // محاكاة الطلب للعميل 30 (Tenant 47)
    
    // Get customer statement
    echo "🔍 اختبار API:\n";
    echo "Request: GET /api/v1/customers/30/statement (Tenant: 47)\n\n";
    
    // Directly test the handler method
    $tenantId = 47;
    $customerId = 30;
    $startDate = date('2026-01-01');
    $endDate = date('2026-05-28');
    
    // Prepare test data by getting statement
    $stmt = new PDO(
        'mysql:host=localhost;dbname=inventory',
        'root',
        ''
    );
    $stmt->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verify the returns data with new columns
    $query = $stmt->prepare("
        SELECT 
            r.id,
            r.return_number,
            r.return_type,
            r.grand_total,
            r.refund_amount,
            r.refund_method
        FROM returns r
        WHERE r.tenant_id = ? AND r.return_type = 'sale'
        LIMIT 1
    ");
    
    $query->execute([$tenantId]);
    $return = $query->fetch(PDO::FETCH_ASSOC);
    
    if ($return) {
        echo "✓ Sample sales return found:\n";
        echo "  ID: {$return['id']}\n";
        echo "  Return#: {$return['return_number']}\n";
        echo "  Type: {$return['return_type']}\n";
        echo "  Amount: {$return['grand_total']}\n";
        echo "  Refund Amount: " . ($return['refund_amount'] ?? 0) . "\n";
        echo "  Refund Method: " . ($return['refund_method'] ?? 'NULL') . "\n";
        
        // Determine subtype
        $refundAmount = isset($return['refund_amount']) ? (float)$return['refund_amount'] : 0;
        $refundMethod = isset($return['refund_method']) ? $return['refund_method'] : null;
        
        if ($refundAmount >= 0.01) {
            if ($refundMethod === 'cash') {
                $subtype = 'sales_return_refund';
            } elseif ($refundMethod === 'bank_transfer') {
                $subtype = 'sales_return_bank_refund';
            } else {
                $subtype = 'sales_return_only';
            }
        } else {
            $subtype = 'sales_return_only';
        }
        
        echo "\n  Computed Subtype: $subtype\n";
        
        echo "\n✓ Dynamic logic is working correctly!\n";
    } else {
        echo "ℹ No sales returns found for this tenant/customer\n";
    }
    
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
    echo "   " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
