<?php

declare(strict_types=1);

/** Add lifecycle metadata required for tenant-owned custom roles. */
final class AddCustomRoleMetadata
{
    public function __construct(private PDO $pdo)
    {
    }

    public function up(): void
    {
        $this->ensureColumn('created_by', 'INT NULL');
        $this->ensureColumn('is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->ensureColumn('deleted_at', 'DATETIME NULL');

        $this->ensureIndex(
            'idx_roles_tenant_active',
            'CREATE INDEX idx_roles_tenant_active ON roles (tenant_id, is_active, sort_order, id)'
        );
    }

    private function ensureColumn(string $name, string $definition): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute(['roles', $name]);
        if ($stmt->fetchColumn() === false) {
            $this->pdo->exec("ALTER TABLE roles ADD COLUMN {$name} {$definition}");
        }
    }

    private function ensureIndex(string $name, string $sql): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1'
        );
        $stmt->execute(['roles', $name]);
        if ($stmt->fetchColumn() === false) {
            $this->pdo->exec($sql);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Migration 047 is forward-only to preserve role history.');
    }
}
