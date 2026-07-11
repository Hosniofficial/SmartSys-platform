<?php
require 'config/database.php';
$db = (new Database())->pdo;
$tenantId = 47;

echo "════════════════════════════════════════════════════════════════════\n";
echo "📊 COMPLETE DATA PICTURE\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

echo "All Sales:\n";
$stmt = $db->prepare("
    SELECT id, customer_id, status, paid_amount, 
           (net_total_amount + IFNULL(tax_amount, 0)) AS grand_total
    FROM sales 
    WHERE tenant_id = ? 
    ORDER BY customer_id, id
");
$stmt->execute([$tenantId]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$salesByCustomer = [];
foreach ($sales as $s) {
    if (!isset($salesByCustomer[$s['customer_id']])) {
        $salesByCustomer[$s['customer_id']] = [];
    }
    $salesByCustomer[$s['customer_id']][] = $s;
}

foreach ($salesByCustomer as $custId => $cusSales) {
    echo "  Customer #" . $custId . ":\n";
    foreach ($cusSales as $s) {
        echo "    Invoice #" . $s['id'] . ": " . $s['grand_total'] . " | paid: " . $s['paid_amount'] . " | status: " . $s['status'] . "\n";
    }
}

echo "\nAll Returns:\n";
$stmt = $db->prepare("
    SELECT id, customer_id, status, grand_total 
    FROM returns 
    WHERE tenant_id = ? 
    ORDER BY customer_id, id
");
$stmt->execute([$tenantId]);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$returnsByCustomer = [];
foreach ($returns as $r) {
    if (!isset($returnsByCustomer[$r['customer_id']])) {
        $returnsByCustomer[$r['customer_id']] = [];
    }
    $returnsByCustomer[$r['customer_id']][] = $r;
}

foreach ($returnsByCustomer as $custId => $cusReturns) {
    echo "  Customer #" . $custId . ":\n";
    foreach ($cusReturns as $r) {
        echo "    Return #" . $r['id'] . ": " . $r['grand_total'] . " | status: " . $r['status'] . "\n";
    }
}

echo "\nFor Customer #37 Specifically:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$totalSaleAmount = 0;
$totalPaid = 0;
foreach ($salesByCustomer[37] ?? [] as $s) {
    $totalSaleAmount += $s['grand_total'];
    $totalPaid += $s['paid_amount'];
}

$totalReturnAmount = 0;
foreach ($returnsByCustomer[37] ?? [] as $r) {
    $totalReturnAmount += $r['grand_total'];
}

echo "Total Invoiced: " . $totalSaleAmount . "\n";
echo "Total Paid (from paid_amount): " . $totalPaid . "\n";
echo "Total Returns: " . $totalReturnAmount . "\n";
echo "Net Outstanding: " . ($totalSaleAmount - $totalPaid - ($totalReturnAmount > 0 ? $totalReturnAmount : 0)) . "\n";
