<?php
require 'config/database.php';

$db = (new Database())->pdo;

echo "🔧 Fixing Payment #704 linkage...\n";

// Update payment #704 to link to sale #802 (not #801)
$stmt = $db->prepare("UPDATE payments SET sale_id = 802 WHERE id = 704 AND tenant_id = 47");
$stmt->execute();

echo "✅ Payment #704 sale_id updated to 802\n";

// Verify
$stmt = $db->prepare("SELECT id, return_id, sale_id FROM payments WHERE id = 704 AND tenant_id = 47");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\nVerification:\n";
echo "  return_id: " . ($row['return_id'] ?? 'NULL') . "\n";
echo "  sale_id: " . ($row['sale_id'] ?? 'NULL') . "\n";

if ($row['sale_id'] == 802) {
    echo "\n✅ CORRECT: Payment linked to the right invoice\n";
}
