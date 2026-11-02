<?php

declare(strict_types=1);

/** Preserve whether sales and quotations were priced tax-inclusive. */
final class AddTaxInclusiveInvoiceMode
{
    public function __construct(private PDO $pdo)
    {
    }

    public function up(): void
    {
        $definition = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INTEGER NOT NULL DEFAULT 0'
            : 'TINYINT(1) NOT NULL DEFAULT 0';

        $this->ensureColumn('sales', 'tax_inclusive', $definition);
        $this->ensureColumn('sales_items', 'tax_amount', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
        $this->ensureColumn('sales_quotations', 'tax_inclusive', $definition);
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($this->pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['name'] === $column) {
                    return;
                }
            }
        } else {
            $statement = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
            );
            $statement->execute([$table, $column]);
            if ($statement->fetchColumn() !== false) {
                return;
            }
        }

        $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }

    public function down(): void
    {
        throw new RuntimeException('Migration 050 is forward-only to preserve invoice tax treatment.');
    }
}
