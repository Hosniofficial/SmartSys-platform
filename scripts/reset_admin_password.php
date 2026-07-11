<?php
require_once __DIR__ . '/../config/database.php';

$db = new Database();

// Set password to 'admin123'
$password = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $db->pdo->prepare('UPDATE users SET password = ? WHERE username = ? AND tenant_id = 47');
$result = $stmt->execute([$password, 'admin0']);

if ($result) {
    echo "✓ Password updated for admin0 to: admin123\n";
} else {
    echo "❌ Failed to update password\n";
}
?>
