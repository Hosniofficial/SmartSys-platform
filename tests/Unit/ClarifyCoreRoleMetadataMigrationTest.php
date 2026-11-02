<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/database/migrations/048_clarify_core_role_metadata.php';

use PHPUnit\Framework\TestCase;

final class ClarifyCoreRoleMetadataMigrationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec(
            'CREATE TABLE roles (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                role_code TEXT NULL,
                display_name TEXT NULL,
                description TEXT NULL,
                tenant_id INTEGER NULL,
                updated_at TEXT NULL
            )'
        );
        $this->db->exec(
            "INSERT INTO roles (id, name, role_code, display_name, description, tenant_id) VALUES
                (1, 'super_admin', 'super_admin', 'Super Administrator', 'Old platform description', NULL),
                (2, 'admin', 'admin', 'مسؤول المنشأة', 'Old admin description', NULL),
                (3, 'manager', 'manager', 'Store Manager', 'Old manager description', NULL),
                (4, 'cashier', 'cashier', 'Cashier', 'Old cashier description', NULL),
                (5, 'inventory_clerk', 'inventory_clerk', 'Inventory Clerk', 'Old inventory description', NULL),
                (6, 'finance_officer', 'finance_officer', 'Finance Officer', 'Old finance description', NULL),
                (7, 'manager', 'tenant_7_role_7', 'Branch Supervisor', 'Legacy tenant manager', 7),
                (8, 'custom_123456', 'custom_123456', 'مبيعات خاصة', 'دور مخصص', 7)"
        );
        $this->db->exec(
            'CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER)'
        );
        $this->db->exec('INSERT INTO role_permissions VALUES (2, 99)');
    }

    public function testUpdatesBuiltInRoleMetadataAndPreservesCustomRolesAndPermissions(): void
    {
        (new ClarifyCoreRoleMetadata($this->db))->up();

        $roles = $this->db->query(
            'SELECT name, display_name, description FROM roles ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);

        self::assertSame('مدير المشروع', $roles[0]['display_name']);
        self::assertStringContainsString('الاشتراكات', $roles[0]['description']);
        self::assertSame('مدير المنشأة', $roles[1]['display_name']);
        self::assertStringContainsString('موظفيها', $roles[1]['description']);
        self::assertSame('مشرف الفروع', $roles[2]['display_name']);
        self::assertSame('موظف المبيعات والمرتجعات', $roles[3]['display_name']);
        self::assertSame('مسؤول المخزون', $roles[4]['display_name']);
        self::assertSame('المسؤول المالي', $roles[5]['display_name']);
        self::assertSame('مشرف الفروع', $roles[6]['display_name']);
        self::assertSame('مبيعات خاصة', $roles[7]['display_name']);
        self::assertSame('دور مخصص', $roles[7]['description']);
        self::assertSame(
            [['role_id' => 2, 'permission_id' => 99]],
            $this->db->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new ClarifyCoreRoleMetadata($this->db);
        $migration->up();
        $firstValue = $this->db->query(
            "SELECT description FROM roles WHERE name = 'manager' AND tenant_id IS NULL"
        )->fetchColumn();

        $migration->up();

        self::assertSame(
            $firstValue,
            $this->db->query(
                "SELECT description FROM roles WHERE name = 'manager' AND tenant_id IS NULL"
            )->fetchColumn()
        );
    }
}
