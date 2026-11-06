<?php

declare(strict_types=1);

use App\Handlers\BranchInventoryReportHandler;
use App\Services\InventoryMovementNotes;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class BranchInventoryMovementLocalizationTest extends TestCase
{
    public function testOperationalInventoryNotesUseConsistentArabicTemplates(): void
    {
        self::assertSame('صرف مخزون - فاتورة مبيعات #42', InventoryMovementNotes::sale(42));
        self::assertSame('عكس صرف المخزون - فاتورة مبيعات #42', InventoryMovementNotes::saleCancellation(42));
        self::assertSame(
            'إضافة مخزون - فاتورة مشتريات #11 - ملاحظة: استلام كامل',
            InventoryMovementNotes::purchase(11, 'استلام كامل')
        );
        self::assertSame(
            'عكس استلام مخزون - فاتورة مشتريات #11 - ملاحظة: تعديل فاتورة',
            InventoryMovementNotes::purchase(11, 'تعديل فاتورة', 'out')
        );
        self::assertSame('مرتجع مبيعات #7 - إضافة مخزون', InventoryMovementNotes::return(7, 'sale'));
        self::assertSame('مرتجع مشتريات #8 - صرف مخزون', InventoryMovementNotes::return(8, 'purchase'));
        self::assertSame(
            'رصيد افتتاحي دفعي - منتج اختبار - ملاحظة: مراجعة يدوية',
            InventoryMovementNotes::openingBalance('opening_balance_bulk', 'منتج اختبار', 'مراجعة يدوية')
        );
        self::assertSame(
            'تسوية مخزون بالنقص - ملاحظة: جرد فعلي',
            InventoryMovementNotes::adjustment('adjustment_out', 'جرد فعلي')
        );
        self::assertSame(
            'تحويل مخزون إلى فرع آخر - ملاحظة: أمر نقل',
            InventoryMovementNotes::transfer('out', 'أمر نقل')
        );
        self::assertSame(
            'عكس رصيد افتتاحي - تم التحقق من عدم وجود حركات لاحقة.',
            InventoryMovementNotes::openingBalanceReversal()
        );
        $this->expectException(InvalidArgumentException::class);
        InventoryMovementNotes::return(7, 'unknown');
    }

    public function testMovementNotesAreArabicAndPreserveUserProvidedText(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, purchase_price REAL
        )');
        $db->exec('CREATE TABLE inventory_transactions (
            id INTEGER PRIMARY KEY, tenant_id INTEGER, movement_date TEXT, reference_type TEXT,
            reference_id INTEGER, movement_type TEXT, quantity REAL, notes TEXT, product_id INTEGER,
            unit_cost REAL, branch_from INTEGER, branch_to INTEGER
        )');
        $db->exec('CREATE TABLE branch_products (
            tenant_id INTEGER, branch_id INTEGER, product_id INTEGER, quantity REAL, quantity_cost REAL
        )');
        $db->exec("INSERT INTO products VALUES (1, 5, 'منتج اختبار', 100)");
        $db->exec("INSERT INTO branch_products VALUES (5, 2, 1, 4, 400)");
        $db->exec("INSERT INTO inventory_transactions VALUES
            (1, 5, '2026-11-01 09:00:00', 'sale', 42, 'out', 1, NULL, 1, 100, 2, NULL),
            (2, 5, '2026-11-01 10:00:00', 'purchase', 11, 'in', 2, '', 1, 100, NULL, 2),
            (3, 5, '2026-11-01 11:00:00', 'return', 7, 'in', 1, 'Return stock movement', 1, 100, NULL, 2),
            (4, 5, '2026-11-01 12:00:00', 'opening_balance_bulk', 9, 'opening_balance_bulk', 3,
             'Opening balance commit @ 2026-11-01 12:00:00', 1, 100, NULL, 2),
            (5, 5, '2026-11-01 13:00:00', 'adjustment', 3, 'adjustment_in', 1, 'مراجعة يدوية', 1, 100, NULL, 2),
            (6, 5, '2026-11-01 14:00:00', 'opening_balance_reversal', 2, 'adjustment_out', 1, NULL, 1, 100, 2, NULL),
            (7, 5, '2026-11-01 15:00:00', 'purchase', 11, 'out', -2, NULL, 1, 100, 2, NULL),
            (8, 5, '2026-11-01 16:00:00', 'sale', 42, 'sale_cancel', 1, NULL, 1, 100, NULL, 2)");

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/branches/reports/inventory/movements')
            ->withAttribute('tenant_id', 5)
            ->withQueryParams([
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-01',
                'branch_id' => 2,
            ]);

        $response = (new BranchInventoryReportHandler($db))
            ->inventoryMovementsReport($request, new Response(), []);
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('success', $body['status'] ?? null);
        $notes = array_column($body['data']['items'], 'notes', 'id');
        self::assertSame('صرف مخزون - فاتورة مبيعات #42', $notes[1]);
        self::assertSame('إضافة مخزون - فاتورة مشتريات #11', $notes[2]);
        self::assertSame('حركة مخزون ناتجة عن مرتجع #7', $notes[3]);
        self::assertSame('ترحيل رصيد افتتاحي بتاريخ 2026-11-01 12:00:00', $notes[4]);
        self::assertSame('مراجعة يدوية', $notes[5]);
        self::assertSame('عكس رصيد افتتاحي - تم التحقق من عدم وجود حركات لاحقة.', $notes[6]);
        self::assertSame('عكس استلام مخزون - فاتورة مشتريات #11', $notes[7]);
        self::assertSame('عكس صرف المخزون - فاتورة مبيعات #42', $notes[8]);
        $movementTypes = array_column($body['data']['items'], 'type', 'id');
        self::assertSame('opening_balance_reversal', $movementTypes[6]);
        $movementsById = array_column($body['data']['items'], null, 'id');
        self::assertEquals(2.0, $movementsById[7]['out']);
        self::assertEquals(0.0, $movementsById[7]['in']);
    }
}
