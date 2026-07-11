<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

echo "\nالمرتجعات وقيم paid_amount:\n";
echo str_repeat("═", 80) . "\n\n";

$returnIds = [335, 339, 340, 342, 343]; // IDs
$stmt = $db->prepare("
    SELECT 
        id,
        return_number,
        grand_total,
        paid_amount,
        refund_method,
        refund_amount
    FROM returns
    WHERE id IN (" . implode(',', $returnIds) . ")
    ORDER BY created_at
");
$stmt->execute();
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($returns as $r) {
    echo "• {$r['return_number']}:\n";
    echo "  Grand Total: {$r['grand_total']}\n";
    echo "  Paid Amount: {$r['paid_amount']}\n";
    echo "  Refund Method: {$r['refund_method']}\n";
    echo "  Refund Amount: {$r['refund_amount']}\n";
    echo "\n";
}
