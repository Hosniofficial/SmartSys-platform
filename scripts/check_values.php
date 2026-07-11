<?php
$db = new PDO('mysql:host=localhost;dbname=inventory;charset=utf8mb4', 'root', '');

// احصل على cost_center_id الموجود
$stmt = $db->query("SELECT id FROM cost_centers LIMIT 1");
$costCenterId = $stmt->fetchColumn();

echo "cost_center_id: " . ($costCenterId ?: "لا يوجد") . "\n";

// احصل على account_id للعميل
$stmt = $db->query("SELECT id FROM accounts WHERE UPPER(name) LIKE '%عميل%' LIMIT 1");
$accountId = $stmt->fetchColumn();

echo "customer account_id: " . ($accountId ?: "لا يوجد") . "\n";

// احصل على مثال من journal_entry_lines لنرى القيم الصحيحة
$stmt = $db->query("SELECT journal_entry_id, account_id, cost_center_id FROM journal_entry_lines LIMIT 1");
$example = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nمثال من journal_entry_lines:\n";
echo "  cost_center_id: " . ($example['cost_center_id'] ?? "NULL") . "\n";
