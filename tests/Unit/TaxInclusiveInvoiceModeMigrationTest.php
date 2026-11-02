<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/database/migrations/050_add_tax_inclusive_invoice_mode.php';

use PHPUnit\Framework\TestCase;

final class TaxInclusiveInvoiceModeMigrationTest extends TestCase
{
    public function testAddsOptInTaxInclusiveModeToSalesAndQuotationsIdempotently(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE sales (id INTEGER PRIMARY KEY)');
        $db->exec('CREATE TABLE sales_items (id INTEGER PRIMARY KEY)');
        $db->exec('CREATE TABLE sales_quotations (id INTEGER PRIMARY KEY)');
        $db->exec('INSERT INTO sales (id) VALUES (1)');
        $db->exec('INSERT INTO sales_items (id) VALUES (1)');

        $migration = new AddTaxInclusiveInvoiceMode($db);
        $migration->up();
        $migration->up();

        self::assertSame(
            0,
            (int) $db->query('SELECT tax_inclusive FROM sales WHERE id = 1')->fetchColumn()
        );
        self::assertSame(
            1,
            (int) $db->query('SELECT COUNT(*) FROM pragma_table_info(\'sales\') WHERE name = \'tax_inclusive\'')->fetchColumn()
        );
        self::assertSame(
            1,
            (int) $db->query('SELECT COUNT(*) FROM pragma_table_info(\'sales_quotations\') WHERE name = \'tax_inclusive\'')->fetchColumn()
        );
        self::assertSame(
            0.0,
            (float) $db->query('SELECT tax_amount FROM sales_items WHERE id = 1')->fetchColumn()
        );
        self::assertSame(
            1,
            (int) $db->query('SELECT COUNT(*) FROM pragma_table_info(\'sales_items\') WHERE name = \'tax_amount\'')->fetchColumn()
        );
    }
}
