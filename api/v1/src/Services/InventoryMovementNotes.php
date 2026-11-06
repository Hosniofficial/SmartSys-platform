<?php

declare(strict_types=1);

namespace App\Services;

final class InventoryMovementNotes
{
    public static function sale(int $saleId): string
    {
        return "صرف مخزون - فاتورة مبيعات #{$saleId}";
    }

    public static function saleCancellation(int $saleId): string
    {
        return "عكس صرف المخزون - فاتورة مبيعات #{$saleId}";
    }

    public static function purchase(
        int $purchaseId,
        ?string $details = null,
        string $direction = 'in'
    ): string
    {
        $label = match ($direction) {
            'in' => "إضافة مخزون - فاتورة مشتريات #{$purchaseId}",
            'out' => "عكس استلام مخزون - فاتورة مشتريات #{$purchaseId}",
            default => throw new \InvalidArgumentException('اتجاه حركة المشتريات غير صالح.'),
        };

        return self::withDetails($label, $details);
    }

    public static function return(int $returnId, string $returnType): string
    {
        $type = strtolower($returnType);
        if (!in_array($type, ['sale', 'purchase'], true)) {
            throw new \InvalidArgumentException('نوع المرتجع غير صالح.');
        }
        $typeLabel = $type === 'sale' ? 'مبيعات' : 'مشتريات';
        $movementLabel = $type === 'sale' ? 'إضافة مخزون' : 'صرف مخزون';

        return "مرتجع {$typeLabel} #{$returnId} - {$movementLabel}";
    }

    public static function openingBalance(
        string $movementType,
        string $productName,
        ?string $details = null
    ): string {
        $typeLabel = match ($movementType) {
            'initial_stock' => 'رصيد افتتاحي عند إنشاء المنتج',
            'opening_balance_manual' => 'رصيد افتتاحي يدوي',
            'opening_balance_bulk' => 'رصيد افتتاحي دفعي',
            default => 'رصيد افتتاحي',
        };

        return self::withDetails("{$typeLabel} - {$productName}", $details);
    }

    public static function adjustment(string $movementType, ?string $details = null): string
    {
        $direction = match (strtolower($movementType)) {
            'adjustment_in' => 'بالزيادة',
            'adjustment_out' => 'بالنقص',
            default => throw new \InvalidArgumentException('نوع تسوية المخزون غير صالح.'),
        };

        return self::withDetails("تسوية مخزون {$direction}", $details);
    }

    public static function transfer(string $direction, ?string $details = null): string
    {
        $directionLabel = match ($direction) {
            'in' => 'من فرع آخر',
            'out' => 'إلى فرع آخر',
            default => throw new \InvalidArgumentException('اتجاه التحويل غير صالح.'),
        };

        return self::withDetails("تحويل مخزون {$directionLabel}", $details);
    }

    public static function openingBalanceReversal(): string
    {
        return 'عكس رصيد افتتاحي - تم التحقق من عدم وجود حركات لاحقة.';
    }

    private static function withDetails(string $label, ?string $details): string
    {
        $details = trim((string) $details);
        if ($details === '') {
            return $label;
        }

        return $label . ' - ملاحظة: ' . $details;
    }
}
