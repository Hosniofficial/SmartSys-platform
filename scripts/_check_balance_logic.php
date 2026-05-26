<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->pdo;
$tenantId = 44;

echo PHP_EOL . "=== PURCHASES: stored vs actual paid (last 15) ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT p.id, p.invoice_number, p.total_amount,
           p.paid_amount AS stored_paid, p.status AS stored_status,
           COALESCE((SELECT SUM(pm.amount) FROM payments pm
                     WHERE pm.purchase_id = p.id AND pm.tenant_id = p.tenant_id
                       AND pm.status = 'completed'), 0) AS actual_paid,
           s.name AS supplier_name
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id AND s.tenant_id = p.tenant_id
    WHERE p.tenant_id = ? ORDER BY p.id DESC LIMIT 15");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ok = 0; $bad = 0; $skip = 0;
foreach ($rows as $p) {
    $total  = (float)$p['total_amount'];
    $stored = round((float)$p['stored_paid'], 2);
    $actual = round((float)$p['actual_paid'], 2);
    $rem    = max(0, round($total - $actual, 2));
    $dyn    = $total<=0 ? 'paid' : ($actual<=0 ? 'due' : ($actual>=$total ? 'paid' : 'partial'));
    // Skip Opening Balance and non-payment-status invoices
    $isOB   = str_starts_with($p['invoice_number'] ?? '', 'OB-')
              || in_array($p['stored_status'], ['approved', 'draft', 'pending_approval'], true);
    if ($isOB) { $skip++;
        printf("  -- [#%d] %-16s | SKIP (status:%s = non-payment invoice)\n",
            $p['id'], $p['invoice_number'], $p['stored_status']);
        continue;
    }
    $match  = ($stored === $actual && $p['stored_status'] === $dyn);
    if ($match) $ok++; else $bad++;
    printf("  %s [#%d] %-16s | %-18s | total:%-8.2f | stored:%-8.2f | actual:%-8.2f | rem:%-8.2f | stored_st:%-8s | dyn:%-8s\n",
        $match?'OK':'XX', $p['id'], $p['invoice_number'], $p['supplier_name']??'---',
        $total, $stored, $actual, $rem, $p['stored_status'], $dyn);
}
echo "  >> Purchases: ok={$ok}  mismatch={$bad}  skipped={$skip}" . PHP_EOL;

echo PHP_EOL . "=== SALES: stored vs actual paid (last 15) ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT s.id, s.invoice_number,
           ROUND(s.net_total_amount + IFNULL(s.tax_amount,0), 2) AS grand_total,
           s.paid_amount AS stored_paid, s.status AS stored_status,
           COALESCE((SELECT SUM(pm.amount) FROM payments pm
                     WHERE pm.sale_id = s.id AND pm.tenant_id = s.tenant_id
                       AND pm.status = 'completed'), 0) AS actual_paid,
           c.name AS customer_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id AND c.tenant_id = s.tenant_id
    WHERE s.tenant_id = ? ORDER BY s.id DESC LIMIT 15");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ok = 0; $bad = 0;
foreach ($rows as $s) {
    $total  = (float)$s['grand_total'];
    $stored = round((float)$s['stored_paid'], 2);
    $actual = round((float)$s['actual_paid'], 2);
    $rem    = max(0, round($total - $actual, 2));
    $dyn    = $total<=0 ? 'paid' : ($actual<=0 ? 'due' : ($actual>=$total ? 'paid' : 'partial'));
    $match  = ($stored === $actual && $s['stored_status'] === $dyn);
    if ($match) $ok++; else $bad++;
    printf("  %s [#%d] %-16s | %-18s | total:%-8.2f | stored:%-8.2f | actual:%-8.2f | rem:%-8.2f | stored_st:%-8s | dyn:%-8s\n",
        $match?'OK':'XX', $s['id'], $s['invoice_number']??'---', $s['customer_name']??'---',
        $total, $stored, $actual, $rem, $s['stored_status'], $dyn);
}
echo "  >> Sales: ok={$ok}  mismatch={$bad}" . PHP_EOL;

echo PHP_EOL . "=== PAYMENTS linked to purchases (last 10) ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT pm.id, pm.purchase_id, pm.amount, pm.status, pm.type, pm.journal_entry_id,
           p.invoice_number AS purchase_inv, sup.name AS supplier_name
    FROM payments pm
    LEFT JOIN purchases p ON p.id = pm.purchase_id AND p.tenant_id = pm.tenant_id
    LEFT JOIN suppliers sup ON sup.id = pm.supplier_id AND sup.tenant_id = pm.tenant_id
    WHERE pm.tenant_id = ? AND pm.purchase_id IS NOT NULL ORDER BY pm.id DESC LIMIT 10");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) { echo "  (none)" . PHP_EOL; }
foreach ($rows as $r) {
    printf("  pay#%d | purchase#%s (%s) | %s | %.2f | %s | type:%-10s | JE:%s\n",
        $r['id'], $r['purchase_id'], $r['purchase_inv']??'?', $r['supplier_name']??'---',
        $r['amount'], $r['status'], $r['type'], $r['journal_entry_id']?'JE#'.$r['journal_entry_id']:'NULL');
}

echo PHP_EOL . "=== PAYMENTS linked to sales (last 10) ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT pm.id, pm.sale_id, pm.amount, pm.status, pm.type, pm.journal_entry_id,
           s.invoice_number AS sale_inv, c.name AS customer_name
    FROM payments pm
    LEFT JOIN sales s ON s.id = pm.sale_id AND s.tenant_id = pm.tenant_id
    LEFT JOIN customers c ON c.id = pm.customer_id AND c.tenant_id = pm.tenant_id
    WHERE pm.tenant_id = ? AND pm.sale_id IS NOT NULL ORDER BY pm.id DESC LIMIT 10");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) { echo "  (none)" . PHP_EOL; }
foreach ($rows as $r) {
    printf("  pay#%d | sale#%s (%s) | %s | %.2f | %s | type:%-10s | JE:%s\n",
        $r['id'], $r['sale_id'], $r['sale_inv']??'?', $r['customer_name']??'---',
        $r['amount'], $r['status'], $r['type'], $r['journal_entry_id']?'JE#'.$r['journal_entry_id']:'NULL');
}

echo PHP_EOL . "=== CASH VOUCHERS linked to invoices (via payments table, last 10) ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT cv.id, cv.type, cv.amount, cv.date, cv.reference, cv.journal_entry_id,
           pm.purchase_id, pm.sale_id, pm.id AS payment_id
    FROM cash_vouchers cv
    JOIN payments pm ON pm.journal_entry_id = cv.journal_entry_id
                     AND pm.tenant_id = cv.tenant_id
    WHERE cv.tenant_id = ? AND (pm.purchase_id IS NOT NULL OR pm.sale_id IS NOT NULL)
    ORDER BY cv.id DESC LIMIT 10");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) { echo "  (no vouchers found linked to invoices)" . PHP_EOL; }
foreach ($rows as $r) {
    $linked = $r['purchase_id'] ? "purchase#{$r['purchase_id']}" : "sale#{$r['sale_id']}";
    printf("  voucher#%d | type:%-8s | %.2f | %s | ref:%-15s | linked:%-18s | pay#%d | JE:JE#%s\n",
        $r['id'], $r['type'], $r['amount'], $r['date'], $r['reference']??'---',
        $linked, $r['payment_id'], $r['journal_entry_id']);
}

echo PHP_EOL . "=== PURCHASE payments missing journal_entry_id (backfill check) ===" . PHP_EOL;
$stmt = $db->prepare("
    SELECT pm.id, pm.purchase_id, pm.amount, pm.status, pm.type
    FROM payments pm
    WHERE pm.tenant_id = ? AND pm.purchase_id IS NOT NULL
      AND (pm.journal_entry_id IS NULL OR pm.journal_entry_id = 0)
    ORDER BY pm.id DESC LIMIT 10");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "  (none — all purchase payments have journal_entry_id)" . PHP_EOL;
} else {
    echo "  Found " . count($rows) . " payment(s) without journal_entry_id:" . PHP_EOL;
    foreach ($rows as $r) {
        printf("  pay#%d | purchase#%d | %.2f | %s | type:%s\n",
            $r['id'], $r['purchase_id'], $r['amount'], $r['status'], $r['type']);
    }
}

echo PHP_EOL . "=== CHECK: cash_vouchers columns (purchase_id, sale_id exist?) ===" . PHP_EOL;
$stmt = $db->prepare("SHOW COLUMNS FROM cash_vouchers LIKE 'purchase_id'");
$stmt->execute(); $col = $stmt->fetch();
echo "  purchase_id column: " . ($col ? "EXISTS (type:{$col['Type']})" : "MISSING!") . PHP_EOL;
$stmt = $db->prepare("SHOW COLUMNS FROM cash_vouchers LIKE 'sale_id'");
$stmt->execute(); $col = $stmt->fetch();
echo "  sale_id column:     " . ($col ? "EXISTS (type:{$col['Type']})" : "MISSING!") . PHP_EOL;

echo PHP_EOL . str_repeat('-',70) . PHP_EOL;
echo "Done. Tenant={$tenantId}" . PHP_EOL;
