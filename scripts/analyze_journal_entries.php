<?php
require 'config/database.php';

$db = (new Database())->pdo;
$customerId = 37;
$tenantId = 47;

echo "════════════════════════════════════════════════════════════════════\n";
echo "🔍 JOURNAL ENTRY ANALYSIS\n";
echo "════════════════════════════════════════════════════════════════════\n\n";

// Get customer account
echo "📋 Customer Account:\n";
$stmt = $db->prepare("SELECT account_id FROM customers WHERE id = ? AND tenant_id = ?");
$stmt->execute([$customerId, $tenantId]);
$accountId = $stmt->fetchColumn();
echo "  Account ID: " . $accountId . "\n\n";

// Get all journal entries for this account
echo "📋 Journal Entry Lines for Account #" . $accountId . ":\n";
echo "─────────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("
    SELECT 
        je.id as je_id,
        je.reference_type,
        je.reference_id,
        je.entry_date,
        je.description,
        SUM(CASE WHEN jel.debit_amount > 0 THEN jel.debit_amount ELSE 0 END) as total_debit,
        SUM(CASE WHEN jel.credit_amount > 0 THEN jel.credit_amount ELSE 0 END) as total_credit
    FROM journal_entries je
    LEFT JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id AND jel.tenant_id = je.tenant_id AND jel.account_id = ?
    WHERE je.tenant_id = ?
    GROUP BY je.id
    HAVING total_debit > 0 OR total_credit > 0
    ORDER BY je.id
");
$stmt->execute([$accountId, $tenantId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDebit = 0;
$totalCredit = 0;

foreach ($entries as $entry) {
    echo "JE #" . $entry['je_id'] . " (" . $entry['reference_type'] . " #" . $entry['reference_id'] . ")\n";
    echo "  Date: " . $entry['entry_date'] . "\n";
    echo "  Debit: " . ($entry['total_debit'] ?? 0) . " | Credit: " . ($entry['total_credit'] ?? 0) . "\n";
    echo "  Description: " . $entry['description'] . "\n\n";
    
    $totalDebit += ($entry['total_debit'] ?? 0);
    $totalCredit += ($entry['total_credit'] ?? 0);
}

echo "─────────────────────────────────────────────────────────────────\n";
echo "TOTALS:\n";
echo "  Total Debit: " . $totalDebit . "\n";
echo "  Total Credit: " . $totalCredit . "\n";
echo "  Balance (Debit - Credit): " . ($totalDebit - $totalCredit) . "\n";
echo "  Outstanding (balance should be 0 if settled): " . ($totalDebit - $totalCredit) . "\n";

echo "\n════════════════════════════════════════════════════════════════════\n";

// Now check if return was actually posted
echo "\n📋 RETURN STATUS:\n";
$stmt = $db->prepare("SELECT id, grand_total, status FROM returns WHERE id = 348 AND tenant_id = ?");
$stmt->execute([$tenantId]);
$ret = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Return #348: " . $ret['grand_total'] . " - status: " . $ret['status'] . "\n";

// Check if journal entry for return exists
$stmt = $db->prepare("
    SELECT id FROM journal_entries 
    WHERE reference_type = 'sale_return' 
    AND reference_id = 348 
    AND tenant_id = ?
");
$stmt->execute([$tenantId]);
$hasJE = $stmt->fetchColumn();
echo "  Journal Entry exists: " . ($hasJE ? "YES ✅" : "NO ❌") . "\n";
