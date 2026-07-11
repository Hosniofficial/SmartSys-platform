<?php
try {
    $db = new PDO('mysql:host=localhost;port=3306;dbname=inventory', 'root', '');
    
    echo "=== جداول متعلقة بـ Opening Balance ===\n";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (stripos($table, 'opening') !== false || stripos($table, 'balance') !== false || stripos($table, 'journal') !== false) {
            echo "- $table\n";
        }
    }
    
    echo "\n=== منتج 1 و منتج 5 - تحليل GL Status ===\n\n";
    
    $productIds = [1, 117]; // 117 = منتج 5
    
    foreach ($productIds as $pid) {
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.opening_balance_posted,
                   pbm.id as mapping_id, pbm.activation_status
            FROM products p
            LEFT JOIN product_branch_gl_mapping pbm ON p.id = pbm.product_id AND pbm.branch_id = 48 AND pbm.tenant_id = 47
            WHERE p.id = ? AND p.tenant_id = 47
        ");
        $stmt->execute([$pid]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo "منتج $pid: لم يتم العثور عليه\n\n";
            continue;
        }
        
        echo "منتج ID: {$product['id']} ({$product['name']})\n";
        echo "  opening_balance_posted: {$product['opening_balance_posted']}\n";
        echo "  mapping_id: " . ($product['mapping_id'] ?? 'NULL') . "\n";
        echo "  activation_status: {$product['activation_status']}\n";
        
        // Check journal entries related to opening balance
        $stmt2 = $db->prepare("
            SELECT COUNT(*) as je_count, MAX(je.date) as last_je_date
            FROM journal_entries je
            WHERE je.tenant_id = 47
            AND je.related_document_type = 'opening_balance'
            AND (je.description LIKE CONCAT('%', ?, '%'))
        ");
        $stmt2->execute([$product['name']]);
        $je = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo "  Journal Entries (opening_balance): {$je['je_count']}\n";
        if ($je['je_count'] > 0) {
            echo "  Last JE Date: {$je['last_je_date']}\n";
        }
        
        // Check branch_products
        $stmt3 = $db->prepare("
            SELECT quantity, quantity_cost, updated_at
            FROM branch_products
            WHERE product_id = ? AND branch_id = 48 AND tenant_id = 47
        ");
        $stmt3->execute([$pid]);
        $bp = $stmt3->fetch(PDO::FETCH_ASSOC);
        if ($bp) {
            echo "  branch_products quantity: {$bp['quantity']}\n";
            echo "  branch_products quantity_cost: {$bp['quantity_cost']}\n";
        }
        
        // Logic that ProductListResource uses
        $glStatus = 'draft';
        if ((bool) ($product['opening_balance_posted'] ?? false)) {
            $glStatus = 'posted';
        } elseif ($product['activation_status'] === 'GL_POSTED' || $product['activation_status'] === 'RECONCILED') {
            $glStatus = 'posted';
        } elseif ($product['activation_status'] === 'ACTIVE_IN_BRANCH') {
            $glStatus = 'active';
        }
        
        echo "  → gl_status (from logic): '$glStatus'\n";
        echo "  ✓ يظهر في الواجهة: " . ($glStatus === 'active' ? 'ترصيد' : '✓ مُرصّد') . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
