<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

// البنية
echo "ACCOUNTS TABLE COLUMNS:\n";
$stmt = $db->query("DESCRIBE accounts");
while ($col = $stmt->fetch()) {
    echo "  {$col[0]}: {$col[1]}\n";
}

echo "\nSALES TABLE COLUMNS:\n";
$stmt = $db->query("DESCRIBE sales");
while ($col = $stmt->fetch()) {
    echo "  {$col[0]}: {$col[1]}\n";
}

echo "\nRETURNS TABLE COLUMNS:\n";
$stmt = $db->query("DESCRIBE returns");
while ($col = $stmt->fetch()) {
    echo "  {$col[0]}: {$col[1]}\n";
}

echo "\n\nSAMPLE: Customer account for محمد حسني 2:\n";
$stmt = $db->query("SELECT * FROM accounts WHERE name LIKE '%محمد%' LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE) . "\n";
