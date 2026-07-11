<?php
try {
    $db = new PDO('mysql:host=localhost;port=3306;dbname=inventory', 'root', '');
    
    echo "=== Users for Tenant 47 ===\n";
    $stmt = $db->prepare('SELECT u.id, u.username, u.name, u.role_id, u.branch_id, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = 47 ORDER BY u.id');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
        echo sprintf("ID: %d | Username: %s | Role: %s (ID: %d) | Branch: %s\n", 
            $row['id'], 
            $row['username'] ?? '—', 
            $row['role_name'] ?? '—', 
            $row['role_id'] ?? 0,
            $row['branch_id'] ?? 'NULL');
    }
    
    echo "\n=== All Branches for Tenant 47 ===\n";
    $stmt = $db->prepare('SELECT id, name FROM branches WHERE tenant_id = 47');
    $stmt->execute();
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($branches as $branch) {
        echo sprintf("ID: %d | Name: %s\n", $branch['id'], $branch['name']);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
