<?php
// Test script to verify improvements for account statement API

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $db = new PDO(
        'mysql:host=localhost;dbname=inventory',
        'root',
        ''
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ اتصال قاعدة البيانات ناجح\n\n";
    
    // Test 1: Check if sales have return_ids info
    echo "📝 Test 1: Sales invoices with return info\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            s.id,
            s.invoice_number,
            (SELECT GROUP_CONCAT(r.id) FROM returns r 
             WHERE r.return_type = 'sale' AND r.sale_id = s.id) AS return_ids
        FROM sales s
        WHERE s.tenant_id = 47 AND s.customer_id = 30
        LIMIT 3
    ");
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sales as $s) {
        $hasReturns = !empty($s['return_ids']) ? 'YES' : 'NO';
        $returnInfo = $s['return_ids'] ? "Return IDs: " . $s['return_ids'] : "(No returns)";
        echo sprintf("Invoice #%s - ID: %d - Has Returns: %s - %s\n", 
            $s['invoice_number'], $s['id'], $hasReturns, $returnInfo);
    }
    
    // Test 2: Check returns with refund_amount/method
    echo "\n📝 Test 2: Returns with refund tracking\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            id,
            return_number,
            refund_amount,
            refund_method,
            CASE 
                WHEN refund_amount < 0.01 THEN 'sales_return_only'
                WHEN refund_method = 'cash' THEN 'sales_return_refund'
                WHEN refund_method = 'bank_transfer' THEN 'sales_return_bank_refund'
                ELSE 'sales_return_only'
            END AS computed_subtype
        FROM returns
        WHERE tenant_id = 47 AND return_type = 'sale'
        LIMIT 5
    ");
    $stmt->execute();
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($returns as $r) {
        echo sprintf("Return #%s - ID: %d\n", $r['return_number'], $r['id']);
        echo sprintf("  - Refund Amount: %.2f | Method: %s\n", 
            $r['refund_amount'] ?? 0, $r['refund_method'] ?? 'NULL');
        echo sprintf("  - Computed Subtype: %s\n", $r['computed_subtype']);
    }
    
    // Test 3: Check payments with return linking
    echo "\n📝 Test 3: Payments with return linking (return_group_id)\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            id,
            reference_number,
            amount,
            sale_id,
            return_id,
            CASE 
                WHEN return_id IS NOT NULL THEN 'refund'
                WHEN sale_id IS NOT NULL THEN 'receipt'
                ELSE 'payment'
            END AS payment_type,
            CASE 
                WHEN return_id IS NOT NULL THEN return_id
                ELSE NULL
            END AS return_group_id
        FROM payments
        WHERE tenant_id = 47 AND customer_id = 30 AND status = 'completed'
        LIMIT 5
    ");
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payments as $p) {
        echo sprintf("Payment #%s - ID: %d\n", $p['reference_number'], $p['id']);
        echo sprintf("  - Type: %s | Amount: %.2f\n", $p['payment_type'], $p['amount']);
        if ($p['return_group_id']) {
            echo sprintf("  - Linked to Return ID: %d (return_group_id)\n", $p['return_group_id']);
        } else {
            echo sprintf("  - Linked to Sale ID: %d\n", $p['sale_id']);
        }
    }
    
    // Test 4: Summary statistics
    echo "\n📊 Summary Statistics\n";
    echo str_repeat("-", 80) . "\n";
    
    $salesCount = $db->query("SELECT COUNT(*) FROM sales WHERE tenant_id = 47 AND customer_id = 30")->fetchColumn();
    $returnsCount = $db->query("SELECT COUNT(*) FROM returns WHERE tenant_id = 47 AND return_type = 'sale' AND customer_id = 30")->fetchColumn();
    $paymentsCount = $db->query("SELECT COUNT(*) FROM payments WHERE tenant_id = 47 AND customer_id = 30 AND status = 'completed'")->fetchColumn();
    
    echo "Customer 30 Tenant 47:\n";
    echo "- Sales Invoices: $salesCount\n";
    echo "- Returns: $returnsCount\n";
    echo "- Payments (all): $paymentsCount\n";
    echo "\nNote: The transaction_count in the main ledger includes ALL journal entry lines\n";
    echo "(each transaction = multiple debit/credit lines)\n";
    
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
