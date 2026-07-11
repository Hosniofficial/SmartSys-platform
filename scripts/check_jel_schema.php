<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');
$stmt = $db->query('DESCRIBE journal_entry_lines');
echo "Columns in journal_entry_lines:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
}
