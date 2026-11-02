<?php

declare(strict_types=1);

/** Store print configuration overrides for individual tenant branches. */
final class CreateBranchPrintSettings
{
    public function __construct(private PDO $pdo)
    {
    }

    public function up(): void
    {
        if ($this->tableExists()) {
            return;
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE branch_settings (
                    tenant_id INTEGER NOT NULL,
                    branch_id INTEGER NOT NULL,
                    key_name TEXT NOT NULL,
                    value TEXT NOT NULL,
                    type TEXT NOT NULL DEFAULT \'string\',
                    updated_by INTEGER NULL,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (tenant_id, branch_id, key_name),
                    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
                    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE,
                    FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
                )'
            );
            $this->pdo->exec(
                'CREATE INDEX idx_branch_settings_branch ON branch_settings (branch_id, tenant_id)'
            );
            return;
        }

        $this->pdo->exec(
            "CREATE TABLE branch_settings (
                tenant_id INT NOT NULL,
                branch_id INT NOT NULL,
                key_name VARCHAR(100) NOT NULL,
                value TEXT NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'string',
                updated_by INT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (tenant_id, branch_id, key_name),
                KEY idx_branch_settings_branch (branch_id, tenant_id),
                CONSTRAINT fk_branch_settings_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
                CONSTRAINT fk_branch_settings_branch
                    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE,
                CONSTRAINT fk_branch_settings_updated_by
                    FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function tableExists(): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'branch_settings' LIMIT 1"
            );
            return $stmt->fetchColumn() !== false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'branch_settings'
             LIMIT 1"
        );
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function down(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS branch_settings');
    }
}
