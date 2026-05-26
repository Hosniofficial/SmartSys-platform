<?php
/**
 * Diagnose: roles, permissions, role_permissions — sales approvals
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db  = new Database();
$pdo = $db->pdo;

try {
    // ── 1. DESCRIBE tables ──────────────────────────────────────────
    echo "=== DESCRIBE permissions ===\n";
    foreach ($pdo->query("DESCRIBE permissions")->fetchAll(PDO::FETCH_ASSOC) as $c)
        echo "  {$c['Field']} ({$c['Type']})\n";

    echo "\n=== DESCRIBE roles ===\n";
    foreach ($pdo->query("DESCRIBE roles")->fetchAll(PDO::FETCH_ASSOC) as $c)
        echo "  {$c['Field']} ({$c['Type']})\n";

    echo "\n=== DESCRIBE role_permissions ===\n";
    foreach ($pdo->query("DESCRIBE role_permissions")->fetchAll(PDO::FETCH_ASSOC) as $c)
        echo "  {$c['Field']} ({$c['Type']})\n";

    // ── 2. All roles ────────────────────────────────────────────────
    echo "\n=== All Roles ===\n";
    foreach ($pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r)
        echo "  " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";

    // ── 3. Sales-related permissions ────────────────────────────────
    echo "\n=== Permissions LIKE 'sales%' ===\n";
    $stmt = $pdo->query("SELECT * FROM permissions WHERE name LIKE 'sales%' ORDER BY name");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p)
        echo "  " . json_encode($p, JSON_UNESCAPED_UNICODE) . "\n";

    // ── 4. role_permissions for role_id = 1 ─────────────────────────
    echo "\n=== role_permissions for role_id = 1 (admin) ===\n";
    $stmt = $pdo->query("
        SELECT rp.role_id, r.name AS role_name, p.name AS permission
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        JOIN roles r ON rp.role_id = r.id
        WHERE rp.role_id = 1
        ORDER BY p.name
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
        echo "  " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";

    // ── 5. Check specifically for approval permissions ───────────────
    echo "\n=== Checking sales.approval.* permissions existence ===\n";
    $stmt = $pdo->query("SELECT id, name FROM permissions WHERE name LIKE 'sales.approval%'");
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($existing)) {
        echo "  ❌ NONE FOUND — need to be created\n";
    } else {
        foreach ($existing as $p)
            echo "  ✅ id={$p['id']} name={$p['name']}\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
