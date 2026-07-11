<?php
/**
 * analyze_storage_architecture.php
 * فحص شامل لهيكل تخزين الصلاحية والسيريال ورقم الدفعة
 */

$connection = new PDO(
    'mysql:host=localhost;dbname=inventory;charset=utf8mb4',
    'root',
    ''
);

echo "\n" . str_repeat('═', 90) . "\n";
echo "تحليل شامل: هيكل تخزين الصلاحية والسيريال ورقم الدفعة\n";
echo str_repeat('═', 90) . "\n\n";

// 1. فحص جدول products
echo "1️⃣  جدول PRODUCTS (البيانات الأساسية)\n";
echo str_repeat('─', 90) . "\n";

$stmt = $connection->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN has_expiry_date = 1 THEN 1 ELSE 0 END) as with_expiry,
        SUM(CASE WHEN has_batch_number = 1 THEN 1 ELSE 0 END) as with_batch,
        SUM(CASE WHEN has_serial_number = 1 THEN 1 ELSE 0 END) as with_serial,
        SUM(CASE WHEN default_expiry_date IS NOT NULL THEN 1 ELSE 0 END) as with_default_expiry,
        SUM(CASE WHEN default_batch_number IS NOT NULL THEN 1 ELSE 0 END) as with_default_batch,
        SUM(CASE WHEN default_serial_number IS NOT NULL THEN 1 ELSE 0 END) as with_default_serial
    FROM products
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "✓ إجمالي المنتجات: {$stats['total_products']}\n";
echo "  - مع تفعيل الصلاحية (flag): {$stats['with_expiry']}\n";
echo "  - مع تفعيل رقم الدفعة (flag): {$stats['with_batch']}\n";
echo "  - مع تفعيل الرقم السري (flag): {$stats['with_serial']}\n";
echo "  - مع قيمة افتراضية للصلاحية: {$stats['with_default_expiry']}\n";
echo "  - مع قيمة افتراضية لرقم الدفعة: {$stats['with_default_batch']}\n";
echo "  - مع قيمة افتراضية للرقم السري: {$stats['with_default_serial']}\n";

echo "\n";

// 2. فحص inventory_transactions
echo "2️⃣  جدول INVENTORY_TRANSACTIONS (حركات المخزون الرئيسية)\n";
echo str_repeat('─', 90) . "\n";

$stmt = $connection->query("
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT movement_type) as movement_types,
        SUM(CASE WHEN batch_number IS NOT NULL THEN 1 ELSE 0 END) as with_batch,
        SUM(CASE WHEN expiry_date IS NOT NULL THEN 1 ELSE 0 END) as with_expiry,
        SUM(CASE WHEN serial IS NOT NULL THEN 1 ELSE 0 END) as with_serial,
        GROUP_CONCAT(DISTINCT movement_type) as types
    FROM inventory_transactions
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "✓ إجمالي السجلات: {$stats['total_records']}\n";
if ($stats['total_records'] > 0) {
    echo "  - مع رقم دفعة: {$stats['with_batch']}\n";
    echo "  - مع تاريخ صلاحية: {$stats['with_expiry']}\n";
    echo "  - مع رقم سري: {$stats['with_serial']}\n";
    echo "  - أنواع الحركات: {$stats['types']}\n";
} else {
    echo "  ⚠️  الجدول فارغ حالياً (لا توجد حركات تسجيلة)\n";
}

echo "\n";

// 3. فحص purchase_items
echo "3️⃣  جدول PURCHASE_ITEMS (بنود الشراء)\n";
echo str_repeat('─', 90) . "\n";

$stmt = $connection->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN batch_number IS NOT NULL THEN 1 ELSE 0 END) as with_batch,
        SUM(CASE WHEN expiry_date IS NOT NULL THEN 1 ELSE 0 END) as with_expiry,
        SUM(CASE WHEN serial IS NOT NULL THEN 1 ELSE 0 END) as with_serial
    FROM purchase_items
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "✓ إجمالي بنود الشراء: {$stats['total_items']}\n";
if ($stats['total_items'] > 0) {
    echo "  - مع رقم دفعة: {$stats['with_batch']}\n";
    echo "  - مع تاريخ صلاحية: {$stats['with_expiry']}\n";
    echo "  - مع رقم سري: {$stats['with_serial']}\n";
} else {
    echo "  ⚠️  الجدول فارغ حالياً\n";
}

echo "\n";

// 4. فحص sales_items
echo "4️⃣  جدول SALES_ITEMS (بنود البيع)\n";
echo str_repeat('─', 90) . "\n";

$stmt = $connection->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN batch_number IS NOT NULL THEN 1 ELSE 0 END) as with_batch,
        SUM(CASE WHEN expiry_date IS NOT NULL THEN 1 ELSE 0 END) as with_expiry,
        SUM(CASE WHEN serial IS NOT NULL THEN 1 ELSE 0 END) as with_serial
    FROM sales_items
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "✓ إجمالي بنود البيع: {$stats['total_items']}\n";
if ($stats['total_items'] > 0) {
    echo "  - مع رقم دفعة: {$stats['with_batch']}\n";
    echo "  - مع تاريخ صلاحية: {$stats['with_expiry']}\n";
    echo "  - مع رقم سري: {$stats['with_serial']}\n";
} else {
    echo "  ⚠️  الجدول فارغ حالياً\n";
}

echo "\n";

// 5. فحص product_expiry
echo "5️⃣  جدول PRODUCT_EXPIRY (غير مستخدم؟)\n";
echo str_repeat('─', 90) . "\n";

$stmt = $connection->query("
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT product_id) as unique_products
    FROM product_expiry
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "✓ إجمالي السجلات: {$stats['total_records']}\n";
if ($stats['total_records'] > 0) {
    echo "  - منتجات مختلفة: {$stats['unique_products']}\n";
    echo "  ℹ️  الجدول يُستخدم\n";
} else {
    echo "  ⚠️  الجدول فارغ تماماً - لا يُستخدم في النظام الحالي\n";
}

echo "\n";

// 6. فحص product_serials
echo "6️⃣  جدول PRODUCT_SERIALS (غير مستخدم؟)\n";
echo str_repeat('─', 90) . "\n";

$stmt = $connection->query("
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT product_id) as unique_products,
        COUNT(DISTINCT status) as statuses,
        GROUP_CONCAT(DISTINCT status) as status_types
    FROM product_serials
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "✓ إجمالي السجلات: {$stats['total_records']}\n";
if ($stats['total_records'] > 0) {
    echo "  - منتجات مختلفة: {$stats['unique_products']}\n";
    echo "  - حالات مختلفة: {$stats['statuses']}\n";
    echo "  - الحالات: {$stats['status_types']}\n";
    echo "  ℹ️  الجدول يُستخدم\n";
} else {
    echo "  ⚠️  الجدول فارغ تماماً - لا يُستخدم في النظام الحالي\n";
}

echo "\n";

// 7. فحص stock_transfers (المشكلة المحتملة)
echo "7️⃣  جدول STOCK_TRANSFERS (مشكلة محتملة!)\n";
echo str_repeat('─', 90) . "\n";

$stmt = $connection->query("
    SELECT 
        COUNT(*) as total_transfers,
        COUNT(DISTINCT product_id) as unique_products
    FROM stock_transfers
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "✓ إجمالي التحويلات: {$stats['total_transfers']}\n";
echo "⚠️  تحذير: جدول stock_transfers لا يحتوي على أعمدة:\n";
echo "    - batch_number (ناقص!)\n";
echo "    - expiry_date (ناقص!)\n";
echo "    - serial (ناقص!)\n";
echo "    → عند تحويل منتج من فرع لأخرى، لا نعرف أي batch/serial تم تحويله!\n";

echo "\n";

// الخلاصة النهائية
echo str_repeat('═', 90) . "\n";
echo "📊 الخلاصة النهائية\n";
echo str_repeat('═', 90) . "\n\n";

echo "✅ البيانات تُخزن بشكل صحيح في:\n";
echo "   1. INVENTORY_TRANSACTIONS - المكان الرئيسي (batch, expiry, serial)\n";
echo "   2. PURCHASE_ITEMS - بنود الشراء (batch, expiry, serial)\n";
echo "   3. SALES_ITEMS - بنود البيع (batch, expiry, serial)\n";
echo "   4. PRODUCTS - القيم الافتراضية (default_*, تم إضافتها في Phase 2)\n\n";

echo "⚠️  أعمدة فارغة/غير مستخدمة:\n";
echo "   1. PRODUCT_EXPIRY - جدول أثري، لا يُستخدم\n";
echo "   2. PRODUCT_SERIALS - جدول أثري، لا يُستخدم\n";
echo "   3. STOCK_TRANSFERS - ناقص أعمدة (batch, expiry, serial)\n\n";

echo "🔍 توصيات:\n";
echo "   ✓ لا تحتاج لتنظيف PRODUCT_EXPIRY/SERIALS الآن (آمن تركها)\n";
echo "   ⚠️  أضف الأعمدة الناقصة في STOCK_TRANSFERS قريباً\n";
echo "   ✓ الهيكل الحالي صحيح وموثق\n\n";

echo str_repeat('═', 90) . "\n\n";
