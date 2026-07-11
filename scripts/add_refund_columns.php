<?php
// Script to add refund_amount and refund_method columns to returns table

try {
    $pdo = new PDO('mysql:host=localhost;dbname=inventory', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // إضافة الأعمدة إذا لم تكن موجودة
    $sql = "
        ALTER TABLE returns 
        ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(12,2) DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS refund_method VARCHAR(50) DEFAULT NULL;
    ";

    $pdo->exec($sql);
    echo "✓ تم إضافة الأعمدة بنجاح\n";
    echo "✓ refund_amount (DECIMAL(12,2), default: 0.00)\n";
    echo "✓ refund_method (VARCHAR(50), default: NULL)\n";

    // التحقق من الأعمدة
    $stmt = $pdo->query('DESCRIBE returns');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📋 أعمدة جدول returns:\n";
    echo str_repeat("-", 60) . "\n";
    foreach ($columns as $col) {
        $field = str_pad($col['Field'], 20);
        $type = str_pad($col['Type'], 20);
        $null = str_pad($col['Null'], 5);
        $key = str_pad($col['Key'] ?: '-', 5);
        $default = $col['Default'] !== null ? $col['Default'] : 'NULL';
        
        echo sprintf("%-20s %-20s Null:%-4s Key:%-4s Default: %s\n", 
            $col['Field'], $col['Type'], $col['Null'], $col['Key'], $default);
    }
    echo str_repeat("-", 60) . "\n";
    
    // عرض الإحصائيات
    $count = $pdo->query('SELECT COUNT(*) FROM returns')->fetchColumn();
    echo "\n📊 إجمالي السجلات في جدول returns: $count\n";
    
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
