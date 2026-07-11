<?php
// Test script to verify the new refund columns are working correctly

try {
    $pdo = new PDO('mysql:host=localhost;dbname=inventory', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✓ اتصال قاعدة البيانات ناجح\n\n";

    // التحقق من البيانات الحالية
    $stmt = $pdo->query("
        SELECT 
            id,
            return_number,
            return_type,
            grand_total,
            refund_amount,
            refund_method,
            status
        FROM returns
        LIMIT 10
    ");
    
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 البيانات الحالية في جدول returns:\n";
    echo str_repeat("-", 100) . "\n";
    
    if (count($returns) > 0) {
        foreach ($returns as $r) {
            echo sprintf(
                "ID: %-3s | Return#: %-10s | Type: %-8s | Amount: %8.2f | Refund: %7.2f | Method: %-12s | Status: %-10s\n",
                $r['id'],
                $r['return_number'],
                $r['return_type'],
                $r['grand_total'],
                $r['refund_amount'] ?? 0,
                $r['refund_method'] ?? 'NULL',
                $r['status']
            );
        }
    } else {
        echo "لا توجد بيانات\n";
    }
    
    echo str_repeat("-", 100) . "\n";
    
    // إحصائيات
    $totalReturns = $pdo->query("SELECT COUNT(*) FROM returns")->fetchColumn();
    $salesReturns = $pdo->query("SELECT COUNT(*) FROM returns WHERE return_type = 'sale'")->fetchColumn();
    $purchaseReturns = $pdo->query("SELECT COUNT(*) FROM returns WHERE return_type = 'purchase'")->fetchColumn();
    $withRefunds = $pdo->query("SELECT COUNT(*) FROM returns WHERE refund_amount > 0")->fetchColumn();
    
    echo "\n📊 الإحصائيات:\n";
    echo "- إجمالي المرتجعات: $totalReturns\n";
    echo "- مرتجعات بيع: $salesReturns\n";
    echo "- مرتجعات شراء: $purchaseReturns\n";
    echo "- مرتجعات بها استرجاع نقدي: $withRefunds\n";
    
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
