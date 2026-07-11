<?php
/**
 * Comprehensive Phase 7 Test Scenario
 * 
 * Tests:
 * - 4 invoices with different payment states
 * - 2 returns with different allocation scenarios
 * - Status transitions (pending_payment -> paid, pending_payment -> closed_by_return)
 * - Outstanding calculations including payment_applications
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Get database connection
    $dbObj = new Database();
    $db = $dbObj->pdo;
    
    // Configuration
    $tenantId = 47;
    $customerId = 1;
    $userId = 1;
    
    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║           Phase 7 Comprehensive Test Scenario                  ║\n";
    echo "║  4 Invoices + 2 Returns (Testing Bug Fix)                     ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    // ═══════════════════════════════════════════════════════════════════════
    // SETUP: Create test customer if not exists
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "📋 SETUP: Ensuring test customer exists...\n";
    $stmt = $db->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$customerId, $tenantId]);
    if (!$stmt->fetch()) {
        // Create test customer
        $stmt = $db->prepare("
            INSERT INTO customers (tenant_id, name, phone, email, active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$tenantId, 'Test Customer - Phase 7', '+966500000000', 'test@example.com']);
        echo "✓ Created test customer ID: $customerId\n";
    } else {
        echo "✓ Test customer already exists\n";
    }
    
    // Create account for customer if not exists
    $stmt = $db->prepare("SELECT account_id FROM customers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$customerId, $tenantId]);
    $custData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$custData || !$custData['account_id']) {
        // Create حساب العميل account
        $stmt = $db->prepare("
            SELECT id FROM accounts WHERE tenant_id = ? AND code = ? LIMIT 1
        ");
        $stmt->execute([$tenantId, 'CUST-' . $customerId]);
        if (!$stmt->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO accounts (tenant_id, code, name, type, parent_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$tenantId, 'CUST-' . $customerId, 'حساب العميل - Phase 7', 'customer', 2]); // parent = 2 is typically assets
            $accountId = $db->lastInsertId();
            $stmt = $db->prepare("UPDATE customers SET account_id = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$accountId, $customerId, $tenantId]);
            echo "✓ Created customer account ID: $accountId\n";
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // SETUP: Get or create test branch and cost_center
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "📍 SETUP: Resolving test branch and cost_center...\n";
    
    // Get or create test branch
    $stmt = $db->prepare("SELECT id FROM branches WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $branchData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branchData) {
        $branchId = $branchData['id'];
        echo "✓ Using existing branch ID: $branchId\n";
    } else {
        // Create test branch
        $stmt = $db->prepare("
            INSERT INTO branches (tenant_id, name, location, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, 'Test Branch - Phase 7', 'Test Location']);
        $branchId = (int)$db->lastInsertId();
        echo "✓ Created test branch ID: $branchId\n";
    }
    
    // Get or create test cost_center
    $stmt = $db->prepare("SELECT id FROM cost_centers WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $ccData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ccData) {
        $costCenterId = $ccData['id'];
        echo "✓ Using existing cost_center ID: $costCenterId\n";
    } else {
        // Create test cost_center
        $stmt = $db->prepare("
            INSERT INTO cost_centers (tenant_id, name, description, active, created_at)
            VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$tenantId, 'Test Cost Center - Phase 7', 'Test Cost Center']);
        $costCenterId = (int)$db->lastInsertId();
        echo "✓ Created test cost_center ID: $costCenterId\n";
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // TEST DATA: Create 4 invoices
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "\n📝 STEP 1: Creating 4 test invoices...\n";
    echo "────────────────────────────────────────────────────────────────\n";
    
    $invoices = [];
    
    // Invoice #794 (نقدي - cash): 2000, paid 2000
    $stmt = $db->prepare("
        INSERT INTO sales (
            tenant_id, customer_id, invoice_number, sale_date, payment_method_id,
            net_total_amount, tax_amount, discount_value, total_amount,
            paid_amount, status, created_at, user_id, branch_id, cost_center_id
        ) VALUES (?, ?, ?, NOW(), 1, ?, ?, 0, ?, ?, 'pending', NOW(), ?, ?, ?)
    ");
    $stmt->execute([$tenantId, $customerId, 'INV-794-CASH', 2000, 0, 2000, 2000, $userId, $branchId, $costCenterId]);
    $invoices['794'] = [
        'id' => (int)$db->lastInsertId(),
        'type' => 'cash',
        'gross' => 2000,
        'paid' => 2000,
        'description' => 'Invoice #794 (نقدي - Full payment)'
    ];
    echo "✓ Invoice #794: {$invoices['794']['description']}\n";
    
    // Invoice #795 (بنكي - bank): 2000, paid 2000
    $stmt->execute([$tenantId, $customerId, 'INV-795-BANK', 2000, 0, 2000, 2000, $userId, $branchId, $costCenterId]);
    $invoices['795'] = [
        'id' => (int)$db->lastInsertId(),
        'type' => 'bank',
        'gross' => 2000,
        'paid' => 2000,
        'description' => 'Invoice #795 (بنكي - Bank transfer paid)'
    ];
    echo "✓ Invoice #795: {$invoices['795']['description']}\n";
    
    // Invoice #797 (جزئي - partial): 2000, paid 1000 → 1000 outstanding
    $stmt->execute([$tenantId, $customerId, 'INV-797-PARTIAL', 2000, 0, 2000, 1000, $userId, $branchId, $costCenterId]);
    $invoices['797'] = [
        'id' => (int)$db->lastInsertId(),
        'type' => 'partial',
        'gross' => 2000,
        'paid' => 1000,
        'description' => 'Invoice #797 (جزئي - Partial payment, 1000 outstanding)'
    ];
    echo "✓ Invoice #797: {$invoices['797']['description']}\n";
    
    // Invoice #798 (آجل - credit): 2000, paid 0 → 2000 outstanding
    $stmt->execute([$tenantId, $customerId, 'INV-798-CREDIT', 2000, 0, 2000, 0, $userId, $branchId, $costCenterId]);
    $invoices['798'] = [
        'id' => (int)$db->lastInsertId(),
        'type' => 'credit',
        'gross' => 2000,
        'paid' => 0,
        'description' => 'Invoice #798 (آجل - No payment, 2000 outstanding)'
    ];
    echo "✓ Invoice #798: {$invoices['798']['description']}\n";
    
    // ═══════════════════════════════════════════════════════════════════════
    // BASELINE: Check initial state
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "\n📊 BASELINE: Initial invoice states\n";
    echo "────────────────────────────────────────────────────────────────\n";
    
    $stmt = $db->prepare("
        SELECT id, invoice_number, net_total_amount, paid_amount, status,
               (net_total_amount - paid_amount) AS outstanding
        FROM sales
        WHERE id IN (?, ?, ?, ?) AND tenant_id = ?
        ORDER BY id
    ");
    $stmt->execute([
        $invoices['794']['id'], $invoices['795']['id'],
        $invoices['797']['id'], $invoices['798']['id'],
        $tenantId
    ]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $inv) {
        $outstanding = max(0, (float)$inv['net_total_amount'] - (float)$inv['paid_amount']);
        printf("  %s: Paid=%4.0f, Outstanding=%4.0f, Status=%-20s\n",
            $inv['invoice_number'], $inv['paid_amount'], $outstanding, $inv['status']
        );
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // RETURNS: Create 2 returns (Direct SQL approach)
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "\n📦 STEP 2: Creating 2 returns...\n";
    echo "────────────────────────────────────────────────────────────────\n";
    
    $returns = [];
    
    // Return #345 on Invoice #798: 2000 مرتجع (آجل invoice - unpaid)
    // This should: Create credit note that cancels the 2000 debt
    // Expected: Invoice #798 status → closed_by_return, outstanding → 0
    echo "Creating Return #345 on Invoice #798 (unpaid)...\n";
    
    $stmt = $db->prepare("
        INSERT INTO returns (
            tenant_id, customer_id, sale_id, return_type,
            grand_total, paid_amount, status, refund_mode, created_at
        ) VALUES (?, ?, ?, 'sale', ?, 0, 'completed', 'auto', NOW())
    ");
    $stmt->execute([$tenantId, $customerId, $invoices['798']['id'], 2000]);
    $returnId345 = (int)$db->lastInsertId();
    
    // Add payment_application for the allocation (use correct schema)
    // Use reference_type='sale' and reference_id=invoice_id
    $stmt = $db->prepare("
        INSERT INTO payment_applications (
            tenant_id, payment_id, reference_type, reference_id, amount, created_at
        ) VALUES (?, ?, 'sale', ?, ?, NOW())
    ");
    // Use payment_id = returnId345 (placeholder)
    $stmt->execute([$tenantId, $returnId345, $invoices['798']['id'], 2000]);
    
    $returns['345'] = [
        'id' => $returnId345,
        'sale_id' => $invoices['798']['id'],
        'mode' => 'auto',
        'amount' => 2000,
        'description' => 'Return on unpaid invoice (debt deduction)'
    ];
    echo "✓ Return #345 created: ID=$returnId345\n";
    
    // Return #347 on Invoice #795: 2000 مرتجع (paid invoice - full payment)
    // This should: Allocate 1000 to Invoice #797 (makes it paid), refund 1000 as cash
    // Expected: Invoice #797 status → paid, outstanding → 0
    //           Return #347 paid_amount → 1000 (refunded), allocated → 1000
    echo "Creating Return #347 on Invoice #795 (paid)...\n";
    
    $stmt = $db->prepare("
        INSERT INTO returns (
            tenant_id, customer_id, sale_id, return_type,
            grand_total, paid_amount, status, refund_mode, created_at
        ) VALUES (?, ?, ?, 'sale', ?, ?, 'completed', 'cash', NOW())
    ");
    $stmt->execute([$tenantId, $customerId, $invoices['795']['id'], 2000, 1000]);
    $returnId347 = (int)$db->lastInsertId();
    
    // Add payment_applications: 1000 to #797 (allocation), 1000 refunded
    $stmt = $db->prepare("
        INSERT INTO payment_applications (
            tenant_id, payment_id, reference_type, reference_id, amount, created_at
        ) VALUES (?, ?, 'sale', ?, ?, NOW())
    ");
    $stmt->execute([$tenantId, $returnId347, $invoices['797']['id'], 1000]);
    
    $returns['347'] = [
        'id' => $returnId347,
        'sale_id' => $invoices['795']['id'],
        'mode' => 'cash',
        'amount' => 2000,
        'description' => 'Return on paid invoice (allocate + refund)'
    ];
    echo "✓ Return #347 created: ID=$returnId347\n";
    
    // ═══════════════════════════════════════════════════════════════════════
    // VERIFICATION: Check results
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "\n✨ VERIFICATION: Results after returns\n";
    echo "════════════════════════════════════════════════════════════════\n";
    
    // Get updated invoice states
    $stmt = $db->prepare("
        SELECT id, invoice_number, net_total_amount, paid_amount, status,
               (net_total_amount - paid_amount) AS outstanding
        FROM sales
        WHERE id IN (?, ?, ?, ?) AND tenant_id = ?
        ORDER BY id
    ");
    $stmt->execute([
        $invoices['794']['id'], $invoices['795']['id'],
        $invoices['797']['id'], $invoices['798']['id'],
        $tenantId
    ]);
    
    $results = [];
    echo "\n📋 Invoice States:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $inv) {
        $outstanding = max(0, (float)$inv['net_total_amount'] - (float)$inv['paid_amount']);
        
        // Get payment_applications allocated to this invoice
        $stmtPa = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) AS allocated
            FROM payment_applications
            WHERE reference_type = 'sale' AND reference_id = ? AND tenant_id = ?
        ");
        $stmtPa->execute([$inv['id'], $tenantId]);
        $pa = $stmtPa->fetch(PDO::FETCH_ASSOC);
        $allocated = (float)$pa['allocated'];
        
        $totalCovered = (float)$inv['paid_amount'] + $allocated;
        $actualOutstanding = max(0, (float)$inv['net_total_amount'] - $totalCovered);
        
        $results[$inv['id']] = [
            'number' => $inv['invoice_number'],
            'gross' => (float)$inv['net_total_amount'],
            'paid' => (float)$inv['paid_amount'],
            'allocated' => $allocated,
            'status' => $inv['status'],
            'outstanding' => $actualOutstanding
        ];
        
        printf("  %s:\n", $inv['invoice_number']);
        printf("    Paid=%4.0f, Allocated=%4.0f, Total Covered=%4.0f, Outstanding=%4.0f\n",
            $inv['paid_amount'], $allocated, $totalCovered, $actualOutstanding
        );
        
        $statusSymbol = '❌';
        if (($actualOutstanding <= 0.01 && $inv['status'] === 'paid') ||
            ($actualOutstanding <= 0.01 && $inv['status'] === 'closed_by_return') ||
            ($actualOutstanding > 0 && $inv['status'] === 'pending_payment')) {
            $statusSymbol = '✅';
        }
        printf("    Status: %-20s %s\n", $inv['status'], $statusSymbol);
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // VALIDATION: Check expected outcomes
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "\n🎯 VALIDATION: Expected vs Actual\n";
    echo "════════════════════════════════════════════════════════════════\n";
    
    $passed = 0;
    $failed = 0;
    
    // Expected outcomes
    $expectations = [
        794 => ['status' => 'pending', 'outstanding' => 0, 'description' => 'Fully paid from start'],
        795 => ['status' => 'pending', 'outstanding' => 0, 'description' => 'Fully paid from start'],
        797 => ['status' => 'paid', 'outstanding' => 0, 'description' => '1000 allocated by Return #347'],
        798 => ['status' => 'closed_by_return', 'outstanding' => 0, 'description' => '2000 deducted by Return #345'],
    ];
    
    foreach ($expectations as $invNum => $expected) {
        $invId = $invoices[(string)$invNum]['id'];
        $actual = $results[$invId];
        
        $statusOk = $actual['status'] === $expected['status'];
        $outstandingOk = $actual['outstanding'] <= 0.01;
        
        $status = ($statusOk && $outstandingOk) ? '✅ PASS' : '❌ FAIL';
        printf("\n%s Invoice #%d: %s\n", $status, $invNum, $expected['description']);
        printf("  Expected Status: %-20s Actual: %-20s %s\n",
            $expected['status'], $actual['status'], $statusOk ? '✅' : '❌'
        );
        printf("  Expected Outstanding: %.0f          Actual: %.0f          %s\n",
            $expected['outstanding'], $actual['outstanding'], $outstandingOk ? '✅' : '❌'
        );
        
        if ($statusOk && $outstandingOk) {
            $passed++;
        } else {
            $failed++;
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // RETURNS CHECK
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "\n\n📦 Return Records:\n";
    echo "════════════════════════════════════════════════════════════════\n";
    
    foreach ($returns as $num => $ret) {
        $stmt = $db->prepare("
            SELECT id, sale_id, grand_total, paid_amount, status
            FROM returns
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$ret['id'], $tenantId]);
        $retData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($retData) {
            printf("\nReturn #%s (on Sale ID %d):\n", $num, $retData['sale_id']);
            printf("  Grand Total: %.0f, Paid Amount: %.0f, Status: %s\n",
                $retData['grand_total'], $retData['paid_amount'], $retData['status']
            );
            
            // Check payment_applications
            $stmtPa = $db->prepare("
                SELECT COALESCE(SUM(amount), 0) AS total_allocated
                FROM payment_applications
                WHERE return_id = ? AND tenant_id = ?
            ");
            $stmtPa->execute([$ret['id'], $tenantId]);
            $paData = $stmtPa->fetch(PDO::FETCH_ASSOC);
            printf("  Allocated by this return: %.0f\n", $paData['total_allocated']);
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // SUMMARY
    // ═══════════════════════════════════════════════════════════════════════
    
    echo "\n\n" . str_repeat("═", 64) . "\n";
    echo "TEST SUMMARY\n";
    echo str_repeat("═", 64) . "\n";
    printf("✅ PASSED: %d/4\n", $passed);
    printf("❌ FAILED: %d/4\n", $failed);
    
    if ($failed === 0) {
        echo "\n🎉 ALL TESTS PASSED! Phase 7 fixes are working correctly.\n";
    } else {
        echo "\n⚠️  Some tests failed. Review the results above.\n";
    }
    echo "\n";
    
} catch (\Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
