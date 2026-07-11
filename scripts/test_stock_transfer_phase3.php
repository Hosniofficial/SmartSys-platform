<?php
/**
 * Test Stock Transfer with Batch, Expiry Date, and Serial - Phase 3
 * 
 * This script tests the new Phase 3 functionality for stock_transfers
 * that now includes batch_number, expiry_date, and serial columns.
 */

declare(strict_types=1);

echo "================================================\n";
echo "Phase 3: Stock Transfer Batch/Serial Testing\n";
echo "================================================\n\n";

try {
    // Direct database connection
    $host = 'localhost';
    $db_name = 'inventory';
    $user = 'root';
    $pass = '';
    
    $db = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // 1. Verify columns exist in stock_transfers
    echo "[1] Verifying stock_transfers columns...\n";
    $checkCols = $db->query("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'stock_transfers'
        AND COLUMN_NAME IN ('batch_number', 'expiry_date', 'serial')
        ORDER BY ORDINAL_POSITION
    ");
    $cols = $checkCols->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($cols) !== 3) {
        throw new Exception("Expected 3 columns, found " . count($cols));
    }
    
    foreach ($cols as $col) {
        echo "  ✓ {$col['COLUMN_NAME']}: {$col['COLUMN_TYPE']} (Nullable: {$col['IS_NULLABLE']})\n";
    }
    
    // 2. Create test stock transfer with batch/serial
    echo "\n[2] Creating test stock transfer...\n";
    
    // Get sample data
    $tenantId = 1; // Assuming tenant 1 exists
    $branchId = 1;
    $tobranchId = 2;
    $productId = 1;
    
    // Get available inventory
    $checkInv = $db->prepare("
        SELECT quantity, quantity_cost FROM branch_products 
        WHERE branch_id = ? AND product_id = ? AND tenant_id = ?
    ");
    $checkInv->execute([$branchId, $productId, $tenantId]);
    $inv = $checkInv->fetch(PDO::FETCH_ASSOC);
    
    if (!$inv || $inv['quantity'] <= 0) {
        echo "  ⚠ No inventory available for test\n";
        echo "  Skipping stock transfer creation test\n";
    } else {
        $quantityToTransfer = min(1, (float)$inv['quantity']);
        
        // Insert stock transfer
        $insertTransfer = $db->prepare("
            INSERT INTO stock_transfers 
            (tenant_id, from_branch, to_branch, product_id, quantity, batch_number, expiry_date, serial, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insertTransfer->execute([
            $tenantId,
            $branchId,
            $tobranchId,
            $productId,
            $quantityToTransfer,
            'BATCH-001', // Test batch
            date('Y-m-d', strtotime('+6 months')), // 6 months from now
            'SN-TEST-001', // Test serial
            'Phase 3 Test Transfer',
            1 // user_id
        ]);
        
        $transferId = (int)$db->lastInsertId();
        echo "  ✓ Created stock transfer ID: {$transferId}\n";
        
        // 3. Verify the data was saved
        echo "\n[3] Verifying saved data...\n";
        $verify = $db->prepare("
            SELECT id, product_id, quantity, batch_number, expiry_date, serial, notes
            FROM stock_transfers
            WHERE id = ? AND tenant_id = ?
        ");
        $verify->execute([$transferId, $tenantId]);
        $result = $verify->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "  ✓ Transfer ID: {$result['id']}\n";
            echo "  ✓ Product ID: {$result['product_id']}\n";
            echo "  ✓ Quantity: {$result['quantity']}\n";
            echo "  ✓ Batch Number: {$result['batch_number']}\n";
            echo "  ✓ Expiry Date: {$result['expiry_date']}\n";
            echo "  ✓ Serial: {$result['serial']}\n";
            echo "  ✓ Notes: {$result['notes']}\n";
        }
    }
    
    // 4. Check stock_transfers table summary
    echo "\n[4] Stock Transfers Summary:\n";
    $summary = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN batch_number IS NOT NULL THEN 1 ELSE 0 END) as with_batch,
            SUM(CASE WHEN expiry_date IS NOT NULL THEN 1 ELSE 0 END) as with_expiry,
            SUM(CASE WHEN serial IS NOT NULL THEN 1 ELSE 0 END) as with_serial
        FROM stock_transfers
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "  Total transfers: {$summary['total']}\n";
    echo "  With batch_number: {$summary['with_batch']}\n";
    echo "  With expiry_date: {$summary['with_expiry']}\n";
    echo "  With serial: {$summary['with_serial']}\n";
    
    echo "\n================================================\n";
    echo "✅ Phase 3 Stock Transfer Testing Complete\n";
    echo "================================================\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    exit(1);
}
?>
