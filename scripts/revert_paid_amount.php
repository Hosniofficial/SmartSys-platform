<?php
require 'config/database.php';

$db = (new Database())->pdo;

echo "🔧 Reverting Invoice #802 paid_amount back to 1000...\n";

$stmt = $db->prepare("UPDATE sales SET paid_amount = 1000 WHERE id = 802 AND tenant_id = 47");
$stmt->execute();

echo "✅ Done. Rows affected: " . $stmt->rowCount() . "\n";

// Verify
$stmt = $db->prepare("SELECT paid_amount FROM sales WHERE id = 802 AND tenant_id = 47");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "✅ paid_amount is now: " . $row['paid_amount'] . "\n";
