<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/database/migrations/049_create_branch_print_settings.php';

use App\Handlers\SettingsHandler;
use PHPUnit\Framework\TestCase;

final class SettingsHandlerBranchPrintSettingsTest extends TestCase
{
    private PDO $db;
    private SettingsHandler $handler;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $this->db->exec('CREATE TABLE branches (id INTEGER PRIMARY KEY)');
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $this->db->exec(
            'CREATE TABLE settings (
                tenant_id INTEGER NOT NULL,
                key_name TEXT NOT NULL,
                value TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT \'string\',
                PRIMARY KEY (tenant_id, key_name)
            )'
        );
        $this->db->exec('INSERT INTO tenants VALUES (1)');
        $this->db->exec('INSERT INTO branches VALUES (2), (3)');
        $this->db->exec('INSERT INTO users VALUES (4)');
        (new CreateBranchPrintSettings($this->db))->up();
        $this->db->exec(
            "INSERT INTO settings (tenant_id, key_name, value, type)
             VALUES (1, 'print.template', 'thermal-detailed', 'string')"
        );
        $this->handler = new SettingsHandler($this->db);
    }

    public function testRejectsSettingsOutsidePrintConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->handler->updateBranchPrintSettings(1, 2, 3, ['company.name' => 'Branch']);
    }

    public function testRejectsUnsupportedTemplatesAndToggleValues(): void
    {
        try {
            $this->handler->updateBranchPrintSettings(1, 2, 3, ['print.template' => 'unknown']);
            self::fail('Unsupported template should be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('قالب الطباعة', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->handler->updateBranchPrintSettings(1, 2, 3, ['printer.print_header' => 'enabled']);
    }

    public function testRejectsOversizedPrintText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->handler->updateBranchPrintSettings(
            1,
            2,
            3,
            ['print.terms_text' => str_repeat('x', 5001)]
        );
    }

    public function testRejectsEmptyUpdate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->handler->updateBranchPrintSettings(1, 2, 3, []);
    }

    public function testBranchOverrideIsIsolatedAndResetRestoresTenantSettings(): void
    {
        $this->handler->updateBranchPrintSettings(
            1,
            2,
            4,
            ['print.template' => 'a4-simple']
        );

        $branchSettings = $this->handler->getBranchPrintSettings(1, 2);
        self::assertSame('a4-simple', $branchSettings['settings']['print.template']);
        self::assertSame(['print.template'], $branchSettings['overrides']);
        self::assertSame(
            'thermal-detailed',
            $this->handler->getBranchPrintSettings(1, 3)['settings']['print.template']
        );

        $this->handler->resetBranchPrintSettings(1, 2, 4);
        $restored = $this->handler->getBranchPrintSettings(1, 2);
        self::assertSame('thermal-detailed', $restored['settings']['print.template']);
        self::assertSame([], $restored['overrides']);
    }

    public function testBranchMayOverrideLegacyTenantTemplateWithoutDatabaseRow(): void
    {
        $this->db->exec(
            "DELETE FROM settings WHERE tenant_id = 1 AND key_name = 'print.template'"
        );

        $this->handler->updateBranchPrintSettings(
            1,
            3,
            4,
            ['print.template' => 'thermal-compact']
        );

        $branchSettings = $this->handler->getBranchPrintSettings(1, 3);
        self::assertSame('thermal-compact', $branchSettings['settings']['print.template']);
        self::assertSame(['print.template'], $branchSettings['overrides']);
        self::assertFalse($branchSettings['tenant_template_configured']);
    }
}
