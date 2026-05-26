<?php
/**
 * Seed: assign sales.approval.* permissions to admin, manager, cashier, finance_officer
 *
 * Permission IDs (confirmed):
 *   33 => sales.approval.view
 *   34 => sales.approval.approve
 *   35 => sales.approval.reject
 *
 * Target roles:
 *   1 => super_admin   (already has them — skip)
 *   2 => admin
 *   3 => manager
 *   4 => cashier
 *   9 => finance_officer
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

$db  = new Database();
$pdo = $db->pdo;

$approvalPermissions = [33, 34, 35]; // view, approve, reject
$targetRoles         = [2, 3, 4, 9]; // admin, manager, cashier, finance_officer

$roleNames = [
    2 => 'admin',
    3 => 'manager',
    4 => 'cashier',
    9 => 'finance_officer',
];

$permNames = [
    33 => 'sales.approval.view',
    34 => 'sales.approval.approve',
    35 => 'sales.approval.reject',
];

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare("
        SELECT COUNT(*) FROM role_permissions
        WHERE role_id = ? AND permission_id = ?
    ");

    $insert = $pdo->prepare("
        INSERT INTO role_permissions (role_id, permission_id, created_at, created_by, tenant_id)
        VALUES (?, ?, NOW(), NULL, NULL)
    ");

    $added   = 0;
    $skipped = 0;

    foreach ($targetRoles as $roleId) {
        foreach ($approvalPermissions as $permId) {
            $check->execute([$roleId, $permId]);
            if ((int) $check->fetchColumn() > 0) {
                echo "  ⏭️  SKIP  role={$roleNames[$roleId]} perm={$permNames[$permId]}\n";
                $skipped++;
            } else {
                $insert->execute([$roleId, $permId]);
                echo "  ✅ ADDED  role={$roleNames[$roleId]} perm={$permNames[$permId]}\n";
                $added++;
            }
        }
    }

    $pdo->commit();

    echo "\n✅ Done. Added={$added}  Skipped={$skipped}\n";

    // ── Verify ─────────────────────────────────────────────────────
    echo "\n=== Verification — all roles with approval permissions ===\n";
    $stmt = $pdo->query("
        SELECT r.id, r.name AS role, p.name AS permission
        FROM role_permissions rp
        JOIN roles r       ON rp.role_id       = r.id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE p.name LIKE 'sales.approval%'
        ORDER BY r.id, p.name
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
        echo "  role={$row['role']} ({$row['id']})  perm={$row['permission']}\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
