<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/database.php';
$db = (new Database())->pdo;

// Check payment method 2 (card)
$stmt = $db->prepare('SELECT id, name, kind, account_id FROM payment_methods WHERE id = 2');
$stmt->execute();
$pm = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== طريقة الدفع ID 2 ===\n";
print_r($pm);

// Check if it has an account
if ($pm['account_id']) {
    $stmt2 = $db->prepare('SELECT id, name, code FROM accounts WHERE id = ?');
    $stmt2->execute([$pm['account_id']]);
    $acc = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "\n=== الحساب المرتبط ===\n";
    print_r($acc);
} else {
    echo "\n⚠️ لا يوجد حساب مرتبط بهذه الطريقة!\n";
}
