<?php
// Fix invoice 782: Set paid_amount=0 for closed_by_return invoices without actual payments

$db = new PDO('mysql:host=localhost;dbname=inventory', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "🔧 Database Correction: Fix paid_amount for closed_by_return invoices\n";
echo str_repeat("-", 80) . "\n";

try {
    // Find all invoices with status='closed_by_return' that have no actual payments
    $stmt = $db->prepare("
        SELECT s.id, s.invoice_number, s.paid_amount
        FROM sales s
        WHERE s.status = 'closed_by_return'
        AND s.paid_amount > 0
        AND s.tenant_id = 47
    ");
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($invoices) . " invoice(s) with inconsistent data:\n\n";
    
    foreach ($invoices as $inv) {
        echo "Invoice #" . $inv['invoice_number'] . " (ID: " . $inv['id'] . ")\n";
        echo "  - Current paid_amount: " . $inv['paid_amount'] . "\n";
        echo "  - Status: closed_by_return (no actual payment)\n";
        echo "  - Should be paid_amount: 0.00\n\n";
    }
    
    // Fix the data
    $updateStmt = $db->prepare("
        UPDATE sales
        SET paid_amount = 0
        WHERE status = 'closed_by_return'
        AND paid_amount > 0
        AND tenant_id = 47
    ");
    
    $result = $updateStmt->execute();
    $affectedRows = $updateStmt->rowCount();
    
    echo "✅ Updated $affectedRows invoice(s):\n";
    echo "   - Set paid_amount = 0 for all closed_by_return invoices\n\n";
    
    // Verify the fix
    $verifyStmt = $db->prepare("
        SELECT id, invoice_number, paid_amount, status
        FROM sales s
        WHERE s.id IN (782, 775, 776, 777, 778, 779, 781)
        ORDER BY s.id ASC
    ");
    $verifyStmt->execute();
    $fixed = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Verification - Current state:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($fixed as $inv) {
        $statusCheck = $inv['status'] === 'closed_by_return' && $inv['paid_amount'] == 0 ? '✓' : '✗';
        echo sprintf("%s Invoice #%-10s | paid_amount: %7.2f | status: %-18s\n",
            $statusCheck, $inv['invoice_number'], $inv['paid_amount'], $inv['status']);
    }
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
