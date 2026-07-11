<?php
/**
 * Test Scenario #2: Return #347 on Invoice #795 (paid via bank)
 * 
 * Scenario Details:
 * - Invoice #795: 2000 total, paid via bank transfer
 * - Invoice #797: 2000 total, partially paid (1000 only), outstanding=1000
 * - Return #347: 2000 refund on #795
 * 
 * Expected Behavior:
 * - Allocate 1000 from return to settle #797 (pending_payment → paid)
 * - Refund remaining 1000 as cash (paid_amount=1000)
 * - Invoice #795 marked as closed_by_return
 * - Invoice #797 marked as paid
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "═════════════════════════════════════════════════════════════════\n";
echo "🧪 TEST SCENARIO #2: Return on Paid Invoice with Debt Allocation\n";
echo "═════════════════════════════════════════════════════════════════\n\n";

// ── Test Data ────────────────────────────────────────────────────────

$invoiceData = [
    ['number' => '#794', 'total' => 2000, 'paid' => 2000, 'method' => 'نقدي',        'status' => 'paid'],
    ['number' => '#795', 'total' => 2000, 'paid' => 2000, 'method' => 'تحويل بنكي', 'status' => 'paid'],
    ['number' => '#797', 'total' => 2000, 'paid' => 1000, 'method' => 'جزئي',        'status' => 'pending_payment'],
    ['number' => '#798', 'total' => 2000, 'paid' => 0,    'method' => 'آجل',         'status' => 'pending_payment'],
];

$returnData = [
    ['number' => '#345', 'on_invoice' => '#798', 'total' => 2000, 'expected_settlement' => 'إلغاء دين كامل'],
    ['number' => '#347', 'on_invoice' => '#795', 'total' => 2000, 'expected_settlement' => 'خصم من #797 + رد نقدي'],
];

echo "📋 البيانات الأساسية:\n";
echo "────────────────────────────────────────────────────────────────\n\n";

echo "الفواتير:\n";
foreach ($invoiceData as $inv) {
    $outstanding = $inv['total'] - $inv['paid'];
    echo sprintf(
        "  %s | الإجمالي: %7.0f | المدفوع: %7.0f | المتبقي: %7.0f | الحالة: %s\n",
        $inv['number'],
        $inv['total'],
        $inv['paid'],
        $outstanding,
        $inv['status']
    );
}

echo "\nالمرتجعات (قبل المعالجة):\n";
foreach ($returnData as $ret) {
    echo sprintf(
        "  %s على %s | القيمة: 2000 | التسوية المتوقعة: %s\n",
        $ret['number'],
        $ret['on_invoice'],
        $ret['expected_settlement']
    );
}

echo "\n\n───────────────────────────────────────────────────────────────────\n";
echo "🔍 الفحصوصات الفعلية من قاعدة البيانات:\n";
echo "───────────────────────────────────────────────────────────────────\n\n";

// ── Query 1: Check Invoice #797 Status (Should be pending_payment before) ────

echo "1️⃣ فاتورة #797 قبل المرتجع:\n";
$stmt = $db->prepare("
    SELECT invoice_number, 
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total,
           paid_amount,
           status
    FROM sales
    WHERE invoice_number = 'S-260528-008' AND tenant_id = 47
");
$stmt->execute();
$inv797 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($inv797) {
    $outstanding = floatval($inv797['grand_total']) - floatval($inv797['paid_amount']);
    echo sprintf(
        "   المبلغ الإجمالي: %.2f | المدفوع: %.2f | المتبقي: %.2f | الحالة: %s\n",
        $inv797['grand_total'],
        $inv797['paid_amount'],
        $outstanding,
        $inv797['status']
    );
    
    if ($outstanding > 100 && $inv797['status'] !== 'pending_payment') {
        echo "   ❌ ERROR: حالة الفاتورة يجب تكون 'pending_payment' لأن هناك متبقي\n";
    } elseif ($outstanding > 100) {
        echo "   ✅ Status صحيح: pending_payment (لأن عندنا متبقي)\n";
    }
} else {
    echo "   ⚠️ لم يتم العثور على الفاتورة\n";
}

// ── Query 2: Check Return #347 Details ────

echo "\n2️⃣ مرتجع #347 (على الفاتورة #795):\n";
$stmt = $db->prepare("
    SELECT r.return_number,
           r.grand_total,
           r.paid_amount,
           s.invoice_number
    FROM returns r
    LEFT JOIN sales s ON r.sale_id = s.id
    WHERE r.return_number IN ('SR-260528-008', 'SR-260529-001') AND r.tenant_id = 47
    ORDER BY r.return_number DESC
    LIMIT 1
");
$stmt->execute();
$ret347 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ret347) {
    echo sprintf(
        "   المبلغ الإجمالي: %.2f | المصروف (paid_amount): %.2f\n",
        $ret347['grand_total'],
        $ret347['paid_amount']
    );
    
    if ($ret347['paid_amount'] == 1000) {
        echo "   ✅ paid_amount = 1000 (الرد النقدي الفائض من الخصم)\n";
    } elseif ($ret347['paid_amount'] == 0) {
        echo "   ⚠️ paid_amount = 0 (لم يتم حساب الفائض بعد)\n";
    } else {
        echo "   ❌ paid_amount = " . $ret347['paid_amount'] . " (غير متوقع)\n";
    }
} else {
    echo "   ⚠️ لم يتم العثور على المرتجع\n";
}

// ── Query 3: Check Payment Applications (allocation to #797) ────

echo "\n3️⃣ تخصيصات الدفع (payment_applications) - هل تم تخصيص للفاتورة #797:\n";
$stmt = $db->prepare("
    SELECT pa.id, pa.sale_id, s.invoice_number, pa.amount
    FROM payment_applications pa
    LEFT JOIN sales s ON pa.sale_id = s.id
    WHERE pa.sale_id IN (
        SELECT id FROM sales WHERE invoice_number = 'S-260528-008' AND tenant_id = 47
    )
");
$stmt->execute();
$allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($allocations)) {
    echo sprintf("   ✅ وجدنا %d تخصيص(ات):\n", count($allocations));
    foreach ($allocations as $alloc) {
        echo sprintf(
            "      - للفاتورة %s: %.2f\n",
            $alloc['invoice_number'],
            $alloc['amount']
        );
    }
} else {
    echo "   ⚠️ لم يتم العثور على تخصيصات للفاتورة #797\n";
}

// ── Query 4: Check #797 Status After Fix ────

echo "\n4️⃣ فاتورة #797 بعد الإصلاح (يجب تكون 'paid' الآن):\n";
$stmt = $db->prepare("
    SELECT invoice_number,
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total,
           paid_amount,
           status
    FROM sales
    WHERE invoice_number = 'S-260528-008' AND tenant_id = 47
");
$stmt->execute();
$inv797_after = $stmt->fetch(PDO::FETCH_ASSOC);

if ($inv797_after) {
    $outstanding = floatval($inv797_after['grand_total']) - floatval($inv797_after['paid_amount']);
    echo sprintf(
        "   المبلغ الإجمالي: %.2f | المدفوع: %.2f | المتبقي: %.2f | الحالة: %s\n",
        $inv797_after['grand_total'],
        $inv797_after['paid_amount'],
        $outstanding,
        $inv797_after['status']
    );
    
    if ($inv797_after['status'] === 'paid' && abs($outstanding) < 0.01) {
        echo "   ✅ PERFECT: Status = paid والمتبقي ≈ 0\n";
    } elseif ($inv797_after['status'] === 'paid') {
        echo "   ⚠️ Status = paid لكن المتبقي = " . $outstanding . " (inconsistent)\n";
    } else {
        echo "   ❌ Status = " . $inv797_after['status'] . " (يجب تكون 'paid')\n";
    }
} else {
    echo "   ⚠️ لم يتم العثور على الفاتورة\n";
}

// ── Query 5: Check Invoice #795 (closed_by_return) ────

echo "\n5️⃣ فاتورة #795 (يجب تكون 'closed_by_return'):\n";
$stmt = $db->prepare("
    SELECT invoice_number,
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total,
           paid_amount,
           status
    FROM sales
    WHERE invoice_number = 'S-260528-010' AND tenant_id = 47
");
$stmt->execute();
$inv795 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($inv795) {
    echo sprintf(
        "   المبلغ الإجمالي: %.2f | المدفوع: %.2f | الحالة: %s\n",
        $inv795['grand_total'],
        $inv795['paid_amount'],
        $inv795['status']
    );
    
    if ($inv795['status'] === 'closed_by_return') {
        echo "   ✅ Status = closed_by_return (صحيح)\n";
    } else {
        echo "   ⚠️ Status = " . $inv795['status'] . " (يجب تكون closed_by_return)\n";
    }
} else {
    echo "   ⚠️ لم يتم العثور على الفاتورة\n";
}

// ── Summary ────

echo "\n\n═════════════════════════════════════════════════════════════════\n";
echo "📊 الملخص:\n";
echo "═════════════════════════════════════════════════════════════════\n\n";

$issues = [];
$passes = [];

// Check #797 status
if ($inv797_after && $inv797_after['status'] === 'paid') {
    $passes[] = "✅ فاتورة #797 عُدّلت من pending_payment إلى paid";
} else {
    $issues[] = "❌ فاتورة #797 تبقى بـ status غير صحيح";
}

// Check #795 status
if ($inv795 && $inv795['status'] === 'closed_by_return') {
    $passes[] = "✅ فاتورة #795 مُعلّمة كـ closed_by_return";
} else {
    $issues[] = "❌ فاتورة #795 لم تُعلّم كـ closed_by_return";
}

// Check return paid_amount
if ($ret347 && $ret347['paid_amount'] > 0) {
    $passes[] = "✅ المرتجع حسب الفائض النقدي (paid_amount > 0)";
} else {
    $issues[] = "⚠️ المرتجع قد لا يكون حسب الفائض النقدي بشكل صحيح";
}

foreach ($passes as $p) {
    echo "$p\n";
}

if (!empty($issues)) {
    echo "\n";
    foreach ($issues as $issue) {
        echo "$issue\n";
    }
}

echo "\n═════════════════════════════════════════════════════════════════\n";

if (empty($issues)) {
    echo "🎉 ALL TESTS PASSED! السيناريو #2 يعمل بشكل صحيح\n";
} else {
    echo "⚠️ هناك " . count($issues) . " مشكلة تحتاج معالجة\n";
}

echo "═════════════════════════════════════════════════════════════════\n";
