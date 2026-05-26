<?php
// Cleanup script: Delete test products 69, 70, 71 and all related data
// Run once then delete this file.

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->pdo;
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$productIds = [69, 70, 71];
$placeholders = implode(',', $productIds);

try {
    $db->beginTransaction();

    // 1. Get journal entry IDs linked to these products before deleting
    $jeStmt = $db->query("
        SELECT DISTINCT je.id
        FROM journal_entries je
        WHERE je.tenant_id = 39
          AND (
              (je.reference_type IN ('opening_balance','product_opening_balance','product_branch_opening')
               AND je.reference_id IN (
                   SELECT id FROM purchases WHERE tenant_id = 39
                   AND notes = 'opening_balance'
               ))
              OR je.idempotency_key REGEXP 'ob:t39:p(69|70|71):'
              OR (je.reference_type = 'product_opening_balance' AND je.reference_id IN ($placeholders))
          )
    ");
    $jeIds = $jeStmt->fetchAll(PDO::FETCH_COLUMN);

    // Also get JE IDs from inventory_transactions for these products
    $jeFromInv = $db->query("
        SELECT DISTINCT journal_entry_id
        FROM inventory_transactions
        WHERE tenant_id = 39 AND product_id IN ($placeholders)
          AND journal_entry_id IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    $allJeIds = array_unique(array_merge($jeIds, $jeFromInv));
    $allJeIds = array_filter($allJeIds); // remove nulls

    echo "<h3>بيانات سيتم حذفها:</h3><ul>";

    // 2. inventory_cost_snapshot
    $n = $db->exec("DELETE FROM inventory_cost_snapshot WHERE tenant_id = 39 AND product_id IN ($placeholders)");
    echo "<li>inventory_cost_snapshot: $n صف</li>";

    // 3. opening_balances
    $n = $db->exec("DELETE FROM opening_balances WHERE tenant_id = 39 AND product_id IN ($placeholders)");
    echo "<li>opening_balances: $n صف</li>";

    // 4. inventory_transactions
    $n = $db->exec("DELETE FROM inventory_transactions WHERE tenant_id = 39 AND product_id IN ($placeholders)");
    echo "<li>inventory_transactions: $n صف</li>";

    // 5. branch_products
    $n = $db->exec("DELETE FROM branch_products WHERE tenant_id = 39 AND product_id IN ($placeholders)");
    echo "<li>branch_products: $n صف</li>";

    // 6. product_activation_log
    $n = $db->exec("DELETE FROM product_activation_log WHERE tenant_id = 39 AND product_id IN ($placeholders)");
    echo "<li>product_activation_log: $n صف</li>";

    // 7. product_branch_gl_mapping
    $n = $db->exec("DELETE FROM product_branch_gl_mapping WHERE tenant_id = 39 AND product_id IN ($placeholders)");
    echo "<li>product_branch_gl_mapping: $n صف</li>";

    // 8. purchase_items for purchases linked to these products
    $purchaseIds = $db->query("
        SELECT DISTINCT purchase_id FROM purchase_items
        WHERE tenant_id = 39 AND product_id IN ($placeholders)
    ")->fetchAll(PDO::FETCH_COLUMN);

    if ($purchaseIds) {
        $pids = implode(',', array_map('intval', $purchaseIds));
        $n = $db->exec("DELETE FROM purchase_items WHERE tenant_id = 39 AND purchase_id IN ($pids)");
        echo "<li>purchase_items: $n صف</li>";
        $n = $db->exec("DELETE FROM purchases WHERE tenant_id = 39 AND id IN ($pids)");
        echo "<li>purchases: $n صف</li>";
    }

    // 9. journal_entry_lines + journal_entries
    if ($allJeIds) {
        $jeList = implode(',', array_map('intval', $allJeIds));
        $n = $db->exec("DELETE FROM journal_entry_lines WHERE journal_entry_id IN ($jeList)");
        echo "<li>journal_entry_lines: $n صف</li>";
        $n = $db->exec("DELETE FROM journal_entries WHERE id IN ($jeList) AND tenant_id = 39");
        echo "<li>journal_entries: $n صف</li>";
    }

    // 10. products themselves
    $n = $db->exec("DELETE FROM products WHERE tenant_id = 39 AND id IN ($placeholders)");
    echo "<li><strong>products: $n صف</strong></li>";

    echo "</ul>";

    $db->commit();
    echo "<p style='color:green;font-weight:bold'>✅ تم حذف جميع البيانات بنجاح. يمكنك الآن بدء سيناريوهات الاختبار من جديد.</p>";
    echo "<p style='color:red'>⚠️ احذف هذا الملف: <code>cleanup_test_products.php</code></p>";

} catch (\Throwable $e) {
    $db->rollBack();
    echo "<p style='color:red'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
}
