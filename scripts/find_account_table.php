<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');
$stmt = $db->query("SHOW TABLES");
echo "جميع الجداول:\n";
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    if (strpos(strtolower($row[0]), 'account') !== false || strpos(strtolower($row[0]), 'chart') !== false) {
        echo "✓ " . $row[0] . "\n";
    }
}
