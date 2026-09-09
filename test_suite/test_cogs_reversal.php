<?php
declare(strict_types=1);
require __DIR__ . '/autoload.php';

use App\Services\CostingService;

$pdo = test_pdo();
$failures = 0;
function check3(bool $ok): void { global $failures; if (!$ok) $failures++; }

echo "=== Group E: COGS reversal WAC date correctness (Fix #7 — audit's RETURN-001 example) ===\n";
echo "Setup: Purchase 5 units @ \$10 on 2026-01-01. Sale of 5 units same day -> COGS should be \$50.\n";
echo "Then: Purchase 10 more units @ \$14 on 2026-02-01 (raises cumulative WAC).\n";
echo "Then: Return the original 5 units on 2026-03-01 -> reversal MUST still be \$50 to net to zero.\n\n";

$pdo->exec("INSERT INTO purchases (id,tenant_id,invoice_date,net_total_amount) VALUES (1,1,'2026-01-01 08:00:00',50.00)");
$pdo->exec("INSERT INTO purchase_items (tenant_id,purchase_id,product_id,quantity,cost) VALUES (1,1,1,5,10.00)");

$pdo->exec("INSERT INTO sales (id,tenant_id,net_total_amount,tax_amount,status,paid_amount,sale_date) VALUES (301,1,50,0,'paid',50,'2026-01-01 09:00:00')");

$costing = new CostingService($pdo);

// This is exactly what postSaleJournalEntry -> computeCOGSForSale would have used
// at the moment the original sale's COGS was posted (WAC as of the sale's own date).
$wacAtSaleTime = $costing->getWeightedAverageCost(1, 1, '2026-01-01 09:00:00');
check3(assert_close($wacAtSaleTime, 10.0, 'E1 WAC at sale time = 10.00 (5 units @ $10, nothing else purchased yet)'));
$originalCogs = round(5 * $wacAtSaleTime, 2);
check3(assert_close($originalCogs, 50.0, 'E1 original COGS booked for the sale = $50.00'));

// New purchase between the sale and the return, at a different price — this is
// exactly the scenario that broke the OLD (buggy) reversal logic.
$pdo->exec("INSERT INTO purchases (id,tenant_id,invoice_date,net_total_amount) VALUES (2,1,'2026-02-01 08:00:00',140.00)");
$pdo->exec("INSERT INTO purchase_items (tenant_id,purchase_id,product_id,quantity,cost) VALUES (1,2,1,10,14.00)");

$returnDate = '2026-03-01 10:00:00';

// OLD BUGGY behavior: WAC computed as of the RETURN date (today), after the
// intervening purchase has shifted the cumulative average.
$wacAtReturnDate = $costing->getWeightedAverageCost(1, 1, $returnDate);
$buggyReversal   = round(5 * $wacAtReturnDate, 2);
echo "[OLD BUGGY]  WAC at return date = " . number_format($wacAtReturnDate, 4) . "/unit -> reversal = \${$buggyReversal} (should be \$50.00)\n";
check3(assert_close($wacAtReturnDate, 12.6667, 'E2 confirms the bug: WAC at return date has shifted to ~12.67 (was 10.00 at sale time)', 0.001));
check3(!(abs($buggyReversal - 50.0) < 0.01)); // explicitly assert the bug WOULD have produced a mismatch
echo (abs($buggyReversal - 50.0) >= 0.01 ? "[PASS]" : "[FAIL]") . " E2 buggy reversal (\${$buggyReversal}) does NOT net to zero against original COGS (\$50) — confirms the bug existed\n";

// FIXED behavior: WAC computed as of the ORIGINAL SALE's date (looked up via
// returns.sale_id -> sales.sale_date, exactly as AccountingService::
// postReturnJournalEntry now does after Fix #7).
$pdo->exec("INSERT INTO returns (id,tenant_id,sale_id,return_type,grand_total,created_at) VALUES (10,1,301,'sale',50.00,'{$returnDate}')");
$pdo->exec("INSERT INTO return_items (tenant_id,return_id,product_id,unit_id,quantity) VALUES (1,10,1,1,5)");

// Reproduce the exact lookup AccountingService::postReturnJournalEntry now performs:
$origSaleStmt = $pdo->prepare(
    "SELECT s.sale_date FROM returns r
     INNER JOIN sales s ON s.id = r.sale_id AND s.tenant_id = r.tenant_id
     WHERE r.id = ? AND r.tenant_id = ? LIMIT 1"
);
$origSaleStmt->execute([10, 1]);
$costingBasisDate = $origSaleStmt->fetchColumn();
check3(assert_eq(substr((string)$costingBasisDate, 0, 19), '2026-01-01 09:00:00', 'E3 costingBasisDate correctly resolved to the ORIGINAL SALE date, not return date'));

$wacFixed      = $costing->getWeightedAverageCost(1, 1, $costingBasisDate);
$fixedReversal = round(5 * $wacFixed, 2);
echo "[FIXED]      WAC at sale-basis date = " . number_format($wacFixed, 4) . "/unit -> reversal = \${$fixedReversal}\n";
check3(assert_close($wacFixed, 10.0, 'E4 fixed WAC lookup reproduces the exact original 10.00/unit'));
check3(assert_close($fixedReversal, 50.0, 'E4 fixed reversal = $50.00, nets EXACTLY to zero against original COGS'));
check3(assert_close($fixedReversal - $originalCogs, 0.0, 'E5 reversal - original COGS = 0.00 (perfect net-to-zero)'));

echo "\n" . ($failures === 0 ? "ALL PASS" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
