<?php
/**
 * audit_products_state.php
 * مراجعة حالة المنتجات وحركات المخزون والقيود المحاسبية
 * افتحه بعد كل سيناريو اختبار لمراجعة النتائج
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

const TENANT_ID = 44;

$database = new Database();
$db = $database->pdo;

?><!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<title>مراجعة حالة المنتجات</title>
<style>
  body{font-family:Tahoma,sans-serif;padding:20px;background:#f0f2f5;font-size:13px}
  h1{color:#2c3e50;margin-bottom:5px}
  h2{color:#2980b9;border-right:4px solid #2980b9;padding-right:10px;margin-top:30px}
  h3{color:#555;margin:10px 0 5px}
  .box{background:#fff;border-radius:8px;padding:16px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  table{border-collapse:collapse;width:100%;margin-bottom:10px}
  td,th{border:1px solid #ddd;padding:6px 10px;text-align:right;white-space:nowrap}
  th{background:#ecf0f1;font-weight:bold;color:#2c3e50}
  tr:hover{background:#f8f9fa}
  .ok{color:#27ae60;font-weight:bold}
  .warn{color:#e67e22;font-weight:bold}
  .err{color:#e74c3c;font-weight:bold}
  .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold}
  .b-ob{background:#d5f5e3;color:#1e8449}
  .b-is{background:#d6eaf8;color:#1a5276}
  .b-adj{background:#fdebd0;color:#784212}
  .b-out{background:#fadbd8;color:#922b21}
  .b-in{background:#e8daef;color:#6c3483}
  .b-tr{background:#d1f2eb;color:#0e6655}
  .b-ret{background:#fdfefe;color:#555;border:1px solid #ccc}
  .summary-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:10px}
  .kpi{background:#f8f9fa;border-radius:6px;padding:10px;text-align:center;border:1px solid #e9ecef}
  .kpi-num{font-size:22px;font-weight:bold;color:#2c3e50}
  .kpi-lbl{font-size:11px;color:#888;margin-top:3px}
  .refresh{float:left;background:#3498db;color:#fff;padding:6px 16px;border-radius:5px;text-decoration:none;font-weight:bold}
</style>
</head>
<body>
<h1>🔍 مراجعة حالة المنتجات والمخزون</h1>
<a href="?" class="refresh">🔄 تحديث</a>
<p style="color:#888">Tenant ID: <?= TENANT_ID ?> — <?= date('Y-m-d H:i:s') ?></p>

<?php

// ═══════════════════════════════════════════════════════════════════
// 1. ملخص عام
// ═══════════════════════════════════════════════════════════════════
echo '<div class="box"><h2>📊 ملخص عام</h2><div class="summary-grid">';

$kpis = [
    'إجمالي المنتجات'      => "SELECT COUNT(*) FROM products WHERE tenant_id = " . TENANT_ID,
    'منتجات مربوطة بفروع' => "SELECT COUNT(DISTINCT product_id) FROM branch_products WHERE tenant_id = " . TENANT_ID,
    'حركات مخزون'         => "SELECT COUNT(*) FROM inventory_transactions WHERE tenant_id = " . TENANT_ID,
    'أرصدة افتتاحية'      => "SELECT COUNT(*) FROM opening_balances WHERE tenant_id = " . TENANT_ID,
    'مبيعات'              => "SELECT COUNT(*) FROM sales WHERE tenant_id = " . TENANT_ID,
    'مشتريات'             => "SELECT COUNT(*) FROM purchases WHERE tenant_id = " . TENANT_ID,
    'مرتجعات'             => "SELECT COUNT(*) FROM `returns` WHERE tenant_id = " . TENANT_ID,
    'قيود محاسبية'        => "SELECT COUNT(*) FROM journal_entries WHERE tenant_id = " . TENANT_ID
              . " AND reference_type IN ('opening_balance','initial_stock','sale','purchase','return','adjustment')",
];

foreach ($kpis as $label => $sql) {
    try {
        $val = $db->query($sql)->fetchColumn();
        echo "<div class='kpi'><div class='kpi-num'>$val</div><div class='kpi-lbl'>$label</div></div>";
    } catch (\Throwable $e) {
        echo "<div class='kpi'><div class='kpi-num err'>!</div><div class='kpi-lbl'>$label</div></div>";
    }
}
echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// 2. تفاصيل المنتجات
// ═══════════════════════════════════════════════════════════════════
echo '<div class="box"><h2>📦 المنتجات وحالة المخزون</h2>';

$products = $db->query("
    SELECT p.id, p.name, p.barcode, p.product_type,
           c.name AS category,
           COALESCE(SUM(bp.quantity), 0) AS total_qty,
           COUNT(DISTINCT bp.branch_id) AS branches_count,
           p.created_at
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN branch_products bp ON bp.product_id = p.id AND bp.tenant_id = p.tenant_id
    WHERE p.tenant_id = " . TENANT_ID . "
    GROUP BY p.id
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (!$products) {
    echo '<p class="warn">⚠️ لا توجد منتجات حالياً</p>';
} else {
    echo '<table><tr>
        <th>ID</th><th>المنتج</th><th>النوع</th><th>الفئة</th>
        <th>إجمالي الكمية</th><th>عدد الفروع</th><th>تاريخ الإنشاء</th>
    </tr>';
    foreach ($products as $p) {
        $qtyClass = $p['total_qty'] > 0 ? 'ok' : 'warn';
        echo "<tr>
            <td>{$p['id']}</td>
            <td><strong>{$p['name']}</strong>" . ($p['barcode'] ? "<br><small style='color:#999'>{$p['barcode']}</small>" : "") . "</td>
            <td>{$p['product_type']}</td>
            <td>" . ($p['category'] ?? '—') . "</td>
            <td class='$qtyClass'>{$p['total_qty']}</td>
            <td>{$p['branches_count']}</td>
            <td style='color:#999'>{$p['created_at']}</td>
        </tr>";
    }
    echo '</table>';
}
echo '</div>';

// ═══════════════════════════════════════════════════════════════════
// 3. حركات المخزون لكل منتج
// ═══════════════════════════════════════════════════════════════════
echo '<div class="box"><h2>🔄 حركات المخزون (مجمّعة حسب المنتج)</h2>';

$movements = $db->query("
    SELECT 
        p.id AS product_id,
        p.name AS product_name,
        it.movement_type,
        COUNT(*) AS count,
        SUM(it.quantity) AS total_qty,
        MIN(it.movement_date) AS first_date,
        MAX(it.movement_date) AS last_date,
        GROUP_CONCAT(DISTINCT it.notes ORDER BY it.id SEPARATOR ' | ') AS notes_sample
    FROM inventory_transactions it
    JOIN products p ON p.id = it.product_id
    WHERE it.tenant_id = " . TENANT_ID . "
    GROUP BY p.id, it.movement_type
    ORDER BY p.id, it.movement_type
")->fetchAll(PDO::FETCH_ASSOC);

if (!$movements) {
    echo '<p class="warn">⚠️ لا توجد حركات مخزون</p>';
} else {
    $typeLabels = [
        'opening_balance' => ['رصيد افتتاحي', 'b-ob'],
        'initial_stock'   => ['إدخال أولي', 'b-is'],
        'adjustment'      => ['تسوية', 'b-adj'],
        'adjustment_in'   => ['تسوية+', 'b-adj'],
        'adjustment_out'  => ['تسوية-', 'b-adj'],
        'out'             => ['بيع', 'b-out'],
        'in'              => ['وارد', 'b-in'],
        'transfer_in'     => ['تحويل وارد', 'b-tr'],
        'transfer_out'    => ['تحويل صادر', 'b-tr'],
        'return'          => ['مرتجع', 'b-ret'],
        'return_in'       => ['مرتجع وارد', 'b-ret'],
    ];

    echo '<table><tr>
        <th>المنتج</th><th>نوع الحركة</th><th>عدد الحركات</th>
        <th>إجمالي الكمية</th><th>أول حركة</th><th>آخر حركة</th><th>الملاحظات</th>
    </tr>';
    foreach ($movements as $m) {
        [$label, $cls] = $typeLabels[$m['movement_type']] ?? [$m['movement_type'], 'b-ret'];
        echo "<tr>
            <td><strong>{$m['product_name']}</strong> <small style='color:#999'>#{$m['product_id']}</small></td>
            <td><span class='badge $cls'>$label</span></td>
            <td style='text-align:center'>{$m['count']}</td>
            <td style='text-align:center'>{$m['total_qty']}</td>
            <td style='color:#999'>{$m['first_date']}</td>
            <td style='color:#999'>{$m['last_date']}</td>
            <td style='max-width:250px;overflow:hidden;text-overflow:ellipsis;color:#666'>" . htmlspecialchars(substr($m['notes_sample'] ?? '—', 0, 100)) . "</td>
        </tr>";
    }
    echo '</table>';
}
echo '</div>';

// ═══════════════════════════════════════════════════════════════════
// 4. فحص GL Mapping (المحاسبة)
// ═══════════════════════════════════════════════════════════════════
echo '<div class="box"><h2>🧾 حالة الربط المحاسبي (GL Mapping)</h2>';

$glMappings = $db->query("
    SELECT 
        p.id, p.name,
        b.name AS branch_name,
        pbg.activation_status,
        pbg.gl_reconciliation_status,
        pbg.gl_balance,
        pbg.average_cost,
        je.id AS je_id,
        je.reference_type AS je_ref_type
    FROM product_branch_gl_mapping pbg
    JOIN products p ON p.id = pbg.product_id
    JOIN branches b ON b.id = pbg.branch_id
    LEFT JOIN journal_entries je ON je.reference_type IN ('opening_balance','initial_stock','product_opening_balance')
        AND je.tenant_id = pbg.tenant_id
        AND je.reference_id = pbg.id
    WHERE pbg.tenant_id = " . TENANT_ID . "
    ORDER BY p.id, b.id
")->fetchAll(PDO::FETCH_ASSOC);

if (!$glMappings) {
    echo '<p class="warn">⚠️ لا يوجد GL mapping</p>';
} else {
    echo '<table><tr>
        <th>المنتج</th><th>الفرع</th><th>حالة التفعيل</th>
        <th>حالة التسوية</th><th>رصيد GL</th><th>متوسط التكلفة</th><th>قيد محاسبي</th>
    </tr>';
    foreach ($glMappings as $g) {
        $reconcileClass = $g['gl_reconciliation_status'] === 'RECONCILED' ? 'ok' : 'warn';
        echo "<tr>
            <td><strong>{$g['name']}</strong> <small style='color:#999'>#{$g['id']}</small></td>
            <td>{$g['branch_name']}</td>
            <td>{$g['activation_status']}</td>
            <td class='$reconcileClass'>{$g['gl_reconciliation_status']}</td>
            <td>" . number_format((float)$g['gl_balance'], 2) . "</td>
            <td>" . number_format((float)$g['average_cost'], 2) . "</td>
            <td>" . ($g['je_id'] ? "<span class='ok'>✅ #{$g['je_id']} ({$g['je_ref_type']})</span>" : "<span class='warn'>—</span>") . "</td>
        </tr>";
    }
    echo '</table>';
}
echo '</div>';

// ═══════════════════════════════════════════════════════════════════
// 5. أحدث 20 حركة (Raw)
// ═══════════════════════════════════════════════════════════════════
echo '<div class="box"><h2>📋 أحدث 20 حركة مخزون (Raw)</h2>';

$raw = $db->query("
    SELECT it.id, p.name AS product, b_to.name AS branch_to, b_from.name AS branch_from,
           it.movement_type, it.quantity, it.unit_cost, it.total_cost,
           it.movement_date, it.reference_type, it.notes, it.journal_entry_id
    FROM inventory_transactions it
    JOIN products p ON p.id = it.product_id
    LEFT JOIN branches b_to ON b_to.id = it.branch_to
    LEFT JOIN branches b_from ON b_from.id = it.branch_from
    WHERE it.tenant_id = " . TENANT_ID . "
    ORDER BY it.id DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

if (!$raw) {
    echo '<p class="warn">⚠️ لا توجد حركات</p>';
} else {
    $typeLabels = [
        'opening_balance' => ['رصيد افتتاحي', 'b-ob'],
        'initial_stock'   => ['إدخال أولي', 'b-is'],
        'adjustment'      => ['تسوية', 'b-adj'],
        'out'             => ['بيع', 'b-out'],
        'in'              => ['وارد', 'b-in'],
        'transfer_in'     => ['تحويل وارد', 'b-tr'],
        'transfer_out'    => ['تحويل صادر', 'b-tr'],
        'return'          => ['مرتجع', 'b-ret'],
    ];
    echo '<table><tr>
        <th>ID</th><th>المنتج</th><th>الفرع</th><th>نوع الحركة</th>
        <th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th>
        <th>التاريخ</th><th>الملاحظات</th><th>قيد</th>
    </tr>';
    foreach ($raw as $r) {
        [$label, $cls] = $typeLabels[$r['movement_type']] ?? [$r['movement_type'], 'b-ret'];
        $branch = $r['branch_to'] ?? $r['branch_from'] ?? '—';
        echo "<tr>
            <td style='color:#999'>{$r['id']}</td>
            <td><strong>{$r['product']}</strong></td>
            <td>{$branch}</td>
            <td><span class='badge $cls'>$label</span></td>
            <td style='text-align:center'>{$r['quantity']}</td>
            <td>" . number_format((float)$r['unit_cost'], 2) . "</td>
            <td>" . number_format((float)$r['total_cost'], 2) . "</td>
            <td style='color:#999'>" . date('m/d H:i', strtotime($r['movement_date'])) . "</td>
            <td style='color:#666;max-width:180px'>" . htmlspecialchars(substr($r['notes'] ?? '—', 0, 60)) . "</td>
            <td>" . ($r['journal_entry_id'] ? "<span class='ok'>✅{$r['journal_entry_id']}</span>" : "<span style='color:#ccc'>—</span>") . "</td>
        </tr>";
    }
    echo '</table>';
}
echo '</div>';

echo '<p style="color:#bbb;font-size:11px;text-align:center">audit_products_state.php — للاستخدام في التطوير فقط</p>';
echo '</body></html>';
