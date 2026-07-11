<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');
$stmt = $db->query('DESCRIBE journal_entries');
echo "Columns in journal_entries:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n";

// تحقق من أول 3 صفوف
$stmt = $db->query('SELECT * FROM journal_entries LIMIT 3');
echo "\nSample data:\n";
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "Columns: " . implode(", ", array_keys($row)) . "\n";
}
