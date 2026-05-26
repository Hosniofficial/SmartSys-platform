<?php
/**
 * reset_all_products.php
 * حذف جميع المنتجات وكل البيانات المرتبطة بها لإعادة الاختبار من الصفر
 * ⚠️ يُنفَّذ فقط في بيئة التطوير!
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

// ── إعدادات ────────────────────────────────────────────────────────────────
const TENANT_ID = 39;
$dryRun = isset($_GET['dry']) || isset($argv[1]) && $argv[1] === '--dry';
$confirmed = isset($_GET['confirm']) || isset($argv[1]) && $argv[1] === '--confirm';

// ── اتصال DB ────────────────────────────────────────────────────────────────
$database = new Database();
$db = $database->pdo;
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── HTML header ─────────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<title>إعادة تعيين المنتجات</title>
<style>
  body{font-family:Tahoma,sans-serif;padding:30px;background:#f5f5f5}
  h1{color:#c0392b} h2{color:#2c3e50;border-bottom:2px solid #ecf0f1;padding-bottom:6px}
  .box{background:#fff;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
  .warn{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:15px;margin-bottom:20px}
  .ok{color:green;font-weight:bold} .err{color:red;font-weight:bold} .info{color:#555}
  table{border-collapse:collapse;width:100%} td,th{border:1px solid #ddd;padding:8px;text-align:right}
  th{background:#ecf0f1} tr:hover{background:#f9f9f9}
  .btn{display:inline-block;padding:12px 30px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:15px;margin:5px}
  .btn-danger{background:#e74c3c;color:#fff} .btn-safe{background:#3498db;color:#fff} .btn-grey{background:#95a5a6;color:#fff}
</style>
</head>
<body>
<?php

echo '<h1>🗑️ إعادة تعيين جميع المنتجات</h1>';
echo '<p class="info">Tenant ID: <strong>' . TENANT_ID . '</strong></p>';

// ── عدّ ما سيُحذف (معاينة دائماً) ──────────────────────────────────────────
$counts = [];

$tables = [
    'products'                => 'tenant_id = ' . TENANT_ID,
    'product_units'           => 'tenant_id = ' . TENANT_ID,
    'branch_products'         => 'tenant_id = ' . TENANT_ID,
    'product_branch_gl_mapping' => 'tenant_id = ' . TENANT_ID,
    'opening_balances'        => 'tenant_id = ' . TENANT_ID,
    'inventory_transactions'  => 'tenant_id = ' . TENANT_ID,
    'inventory_cost_snapshot' => 'tenant_id = ' . TENANT_ID,
    'product_activation_log'  => 'tenant_id = ' . TENANT_ID,
];

echo '<div class="box"><h2>📊 معاينة البيانات التي ستُحذف</h2><table>';
echo '<tr><th>الجدول</th><th>عدد الصفوف</th></tr>';

foreach ($tables as $table => $where) {
    try {
        $n = $db->query("SELECT COUNT(*) FROM `$table` WHERE $where")->fetchColumn();
        $counts[$table] = (int)$n;
        echo "<tr><td>$table</td><td>" . number_format($n) . "</td></tr>";
    } catch (\Throwable $e) {
        echo "<tr><td>$table</td><td class='err'>خطأ: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
        $counts[$table] = 0;
    }
}

// sales
try {
    $n = $db->query("SELECT COUNT(*) FROM sales WHERE tenant_id = " . TENANT_ID)->fetchColumn();
    $counts['sales'] = (int)$n;
    echo "<tr><td>sales</td><td>" . number_format($n) . "</td></tr>";
} catch (\Throwable $e) { $counts['sales'] = 0; }

// returns
try {
    $n = $db->query("SELECT COUNT(*) FROM returns WHERE tenant_id = " . TENANT_ID)->fetchColumn();
    $counts['returns'] = (int)$n;
    echo "<tr><td>returns</td><td>" . number_format($n) . "</td></tr>";
} catch (\Throwable $e) { $counts['returns'] = 0; }

// return_items
try {
    $n = $db->query("SELECT COUNT(*) FROM return_items WHERE tenant_id = " . TENANT_ID)->fetchColumn();
    $counts['return_items'] = (int)$n;
    echo "<tr><td>return_items</td><td>" . number_format($n) . "</td></tr>";
} catch (\Throwable $e) { $counts['return_items'] = 0; }

// purchase_items
try {
    $n = $db->query("SELECT COUNT(*) FROM purchase_items WHERE tenant_id = " . TENANT_ID)->fetchColumn();
    $counts['purchase_items'] = (int)$n;
    echo "<tr><td>purchase_items</td><td>" . number_format($n) . "</td></tr>";
} catch (\Throwable $e) { $counts['purchase_items'] = 0; }

// purchases
try {
    $n = $db->query("SELECT COUNT(*) FROM purchases WHERE tenant_id = " . TENANT_ID)->fetchColumn();
    $counts['purchases'] = (int)$n;
    echo "<tr><td>purchases</td><td>" . number_format($n) . "</td></tr>";
} catch (\Throwable $e) { $counts['purchases'] = 0; }

// journal entries linked to products
try {
    $n = $db->query("
        SELECT COUNT(DISTINCT je.id) FROM journal_entries je
        WHERE je.tenant_id = " . TENANT_ID . "
          AND je.reference_type IN ('opening_balance','product_opening_balance','sale','purchase','return','adjustment')
    ")->fetchColumn();
    $counts['journal_entries'] = (int)$n;
    echo "<tr><td>journal_entries (مرتبطة)</td><td>" . number_format($n) . "</td></tr>";
} catch (\Throwable $e) { $counts['journal_entries'] = 0; }

echo '</table></div>';

// ── إذا dry run أو غير مؤكد: عرض أزرار ──────────────────────────────────
if ($dryRun || !$confirmed) {
    echo '<div class="warn">
        <strong>⚠️ تحذير:</strong> هذه العملية <strong>لا يمكن التراجع عنها</strong>. ستُحذف جميع المنتجات والمبيعات والمشتريات والمرتجعات وحركات المخزون وقيودها المحاسبية للـ Tenant ' . TENANT_ID . '.
    </div>';
    echo '<div class="box">';
    echo '<h2>🔘 اختر الإجراء</h2>';
    echo '<a href="?dry=1" class="btn btn-safe">🔍 معاينة فقط (Dry Run)</a>';
    echo '<a href="?confirm=1" class="btn btn-danger" onclick="return confirm(\'هل أنت متأكد تماماً؟ سيتم حذف كل البيانات!\')">🗑️ تأكيد الحذف الكامل</a>';
    echo '<a href="../" class="btn btn-grey">↩️ إلغاء</a>';
    echo '</div>';
    if ($dryRun) {
        echo '<p class="ok">ℹ️ وضع المعاينة: لم يُحذف أي شيء.</p>';
    }
    exit;
}

// ── تنفيذ الحذف ─────────────────────────────────────────────────────────────
echo '<div class="box"><h2>🚀 جاري الحذف...</h2><ul>';

try {
    $db->beginTransaction();

    // ── 1. جمع journal_entry_ids المرتبطة (من journal_entries مباشرة) ─────
    $allJeIds = $db->query("
        SELECT id FROM journal_entries
        WHERE tenant_id = " . TENANT_ID . "
          AND reference_type IN (
              'opening_balance', 'product_opening_balance', 'product_branch_opening',
              'sale', 'purchase', 'return', 'adjustment', 'initial_stock'
          )
    ")->fetchAll(PDO::FETCH_COLUMN);

    // أضف أي IDs مرتبطة بـ inventory_transactions لم تُغطَّ بالأنواع أعلاه
    try {
        $invJeIds = $db->query("
            SELECT DISTINCT journal_entry_id FROM inventory_transactions
            WHERE tenant_id = " . TENANT_ID . " AND journal_entry_id IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN);
        $allJeIds = array_values(array_filter(array_unique(array_merge($allJeIds, $invJeIds))));
    } catch (\Throwable $e) {}

    try {
        $salesJeIds = $db->query("
            SELECT DISTINCT journal_entry_id FROM sales
            WHERE tenant_id = " . TENANT_ID . " AND journal_entry_id IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN);
        $allJeIds = array_values(array_filter(array_unique(array_merge($allJeIds, $salesJeIds))));
    } catch (\Throwable $e) {}

    try {
        $purchasesJeIds = $db->query("
            SELECT DISTINCT journal_entry_id FROM purchases
            WHERE tenant_id = " . TENANT_ID . " AND journal_entry_id IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN);
        $allJeIds = array_values(array_filter(array_unique(array_merge($allJeIds, $purchasesJeIds))));
    } catch (\Throwable $e) {}

    // ── 2. return_items ──────────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM return_items WHERE tenant_id = " . TENANT_ID);
        echo "<li>return_items: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>return_items: " . $e->getMessage() . "</li>"; }

    // ── 3. returns ───────────────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM `returns` WHERE tenant_id = " . TENANT_ID);
        echo "<li>returns: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>returns: " . $e->getMessage() . "</li>"; }

    // ── 5. sales ─────────────────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM sales WHERE tenant_id = " . TENANT_ID);
        echo "<li>sales: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>sales: " . $e->getMessage() . "</li>"; }

    // ── 6. purchase_items ────────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM purchase_items WHERE tenant_id = " . TENANT_ID);
        echo "<li>purchase_items: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>purchase_items: " . $e->getMessage() . "</li>"; }

    // ── 7. purchases ─────────────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM purchases WHERE tenant_id = " . TENANT_ID);
        echo "<li>purchases: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>purchases: " . $e->getMessage() . "</li>"; }

    // ── 8. inventory_cost_snapshot ───────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM inventory_cost_snapshot WHERE tenant_id = " . TENANT_ID);
        echo "<li>inventory_cost_snapshot: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>inventory_cost_snapshot: " . $e->getMessage() . "</li>"; }

    // ── 9. opening_balances ──────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM opening_balances WHERE tenant_id = " . TENANT_ID);
        echo "<li>opening_balances: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>opening_balances: " . $e->getMessage() . "</li>"; }

    // ── 10. inventory_transactions ───────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM inventory_transactions WHERE tenant_id = " . TENANT_ID);
        echo "<li>inventory_transactions: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>inventory_transactions: " . $e->getMessage() . "</li>"; }

    // ── 11. branch_products ──────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM branch_products WHERE tenant_id = " . TENANT_ID);
        echo "<li>branch_products: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>branch_products: " . $e->getMessage() . "</li>"; }

    // ── 12. product_activation_log ───────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM product_activation_log WHERE tenant_id = " . TENANT_ID);
        echo "<li>product_activation_log: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { /* قد لا يوجد هذا الجدول */ }

    // ── 13. product_branch_gl_mapping ───────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM product_branch_gl_mapping WHERE tenant_id = " . TENANT_ID);
        echo "<li>product_branch_gl_mapping: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>product_branch_gl_mapping: " . $e->getMessage() . "</li>"; }

    // ── 14. product_units ────────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM product_units WHERE tenant_id = " . TENANT_ID);
        echo "<li>product_units: <strong>$n</strong> صف</li>";
    } catch (\Throwable $e) { echo "<li class='err'>product_units: " . $e->getMessage() . "</li>"; }

    // ── 15. products ─────────────────────────────────────────────────────────
    try {
        $n = $db->exec("DELETE FROM products WHERE tenant_id = " . TENANT_ID);
        echo "<li><strong>products: <span style='color:#e74c3c'>$n</span> صف ✅</strong></li>";
    } catch (\Throwable $e) { echo "<li class='err'>products: " . $e->getMessage() . "</li>"; }

    // ── 16. journal_entry_lines + journal_entries ────────────────────────────
    if ($allJeIds) {
        $jeList = implode(',', array_map('intval', $allJeIds));
        try {
            $n = $db->exec("DELETE FROM journal_entry_lines WHERE journal_entry_id IN ($jeList)");
            echo "<li>journal_entry_lines: <strong>$n</strong> صف</li>";
        } catch (\Throwable $e) { echo "<li class='err'>journal_entry_lines: " . $e->getMessage() . "</li>"; }

        // قطع FK من الجداول المرتبطة بـ journal_entries قبل الحذف
        foreach (['cash_transactions', 'cashier_sessions', 'payments'] as $fkTable) {
            try {
                $db->exec("UPDATE `$fkTable` SET journal_entry_id = NULL WHERE journal_entry_id IN ($jeList)");
            } catch (\Throwable $e) { /* الجدول قد لا يوجد */ }
        }

        try {
            $n = $db->exec("DELETE FROM journal_entries WHERE id IN ($jeList) AND tenant_id = " . TENANT_ID);
            echo "<li>journal_entries: <strong>$n</strong> صف</li>";
        } catch (\Throwable $e) { echo "<li class='err'>journal_entries: " . $e->getMessage() . "</li>"; }
    } else {
        echo "<li class='info'>journal_entries: لا توجد قيود مرتبطة</li>";
    }

    $db->commit();

    echo '</ul>';
    echo '<p class="ok" style="font-size:18px">✅ تم حذف جميع البيانات بنجاح! يمكنك الآن بدء الاختبار من الصفر.</p>';

} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo '</ul>';
    echo '<p class="err">❌ خطأ أثناء الحذف — تم التراجع عن جميع التغييرات.<br>' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</div>';
echo '<p style="color:#999;font-size:12px">⚠️ بعد الانتهاء من الاختبارات، احذف هذا الملف: <code>reset_all_products.php</code></p>';
echo '</body></html>';
