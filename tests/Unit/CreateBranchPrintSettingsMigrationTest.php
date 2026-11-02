<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/database/migrations/049_create_branch_print_settings.php';

use PHPUnit\Framework\TestCase;

final class CreateBranchPrintSettingsMigrationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $this->db->exec('CREATE TABLE branches (id INTEGER PRIMARY KEY)');
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $this->db->exec('INSERT INTO tenants VALUES (1)');
        $this->db->exec('INSERT INTO branches VALUES (2)');
        $this->db->exec('INSERT INTO users VALUES (3)');
    }

    public function testCreatesBranchScopedSettingsAndEnforcesBranchReferences(): void
    {
        (new CreateBranchPrintSettings($this->db))->up();
        $this->db->exec(
            "INSERT INTO branch_settings (tenant_id, branch_id, key_name, value, updated_by)
             VALUES (1, 2, 'print.template', 'a4-professional', 3)"
        );

        self::assertSame(
            'a4-professional',
            $this->db->query('SELECT value FROM branch_settings WHERE tenant_id = 1 AND branch_id = 2')
                ->fetchColumn()
        );
        self::assertCount(
            2,
            $this->db->query('PRAGMA index_list(branch_settings)')->fetchAll(PDO::FETCH_ASSOC)
        );

        $this->expectException(PDOException::class);
        $this->db->exec(
            "INSERT INTO branch_settings (tenant_id, branch_id, key_name, value)
             VALUES (1, 99, 'print.template', 'thermal-compact')"
        );
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new CreateBranchPrintSettings($this->db);
        $migration->up();
        $migration->up();

        self::assertSame(
            1,
            (int) $this->db->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'branch_settings'"
            )->fetchColumn()
        );
    }
}
