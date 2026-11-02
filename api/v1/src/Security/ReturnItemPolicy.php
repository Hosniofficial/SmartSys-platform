<?php

declare(strict_types=1);

namespace App\Security;

use DomainException;

/** Validates requested return quantities and recalculates returned line amounts. */
final class ReturnItemPolicy
{
    /**
     * Rebuild return line values from the original invoice instead of trusting
     * prices and subtotals supplied by the client.
     *
     * @return array{items: array<int, array<string, mixed>>, subtotal: float, tax: float}
     */
    public static function normalize(
        string $type,
        array $requestedItems,
        array $sourceItems,
        array $previousQuantities,
        float $saleTaxRate = 0.0,
        bool $saleTaxInclusive = false
    ): array {
        if (!in_array($type, ['sale', 'purchase'], true)) {
            throw new DomainException('نوع المرتجع غير صالح.');
        }

        $groups = [];
        $sourceById = [];
        foreach ($sourceItems as $source) {
            $productId = (int) ($source['product_id'] ?? 0);
            $unitId = (int) ($source['unit_id'] ?? 0);
            $quantity = (float) ($source['quantity'] ?? 0);
            if ($productId <= 0 || $unitId <= 0 || $quantity <= 0) {
                throw new DomainException('بيانات بند الفاتورة الأصلية غير صالحة للإرجاع.');
            }

            $key = self::groupKey($productId, $unitId);
            $groups[$key] ??= [
                'product_id' => $productId,
                'unit_id' => $unitId,
                'quantity' => 0.0,
                'items' => [],
            ];
            $groups[$key]['quantity'] += $quantity;
            $groups[$key]['items'][] = $source;

            $sourceId = (int) ($source['id'] ?? 0);
            if ($sourceId > 0) {
                $sourceById[$sourceId] = ['key' => $key, 'item' => $source];
            }
        }

        $requestedByGroup = [];
        $requestedBySource = [];
        foreach ($requestedItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $unitId = (int) ($item['unit_id'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($productId <= 0 || $unitId <= 0 || !is_finite($quantity) || $quantity <= 0) {
                throw new DomainException('كمية الصنف المرتجع أو وحدة قياسه غير صالحة.');
            }

            $key = self::groupKey($productId, $unitId);
            if (!isset($groups[$key])) {
                throw new DomainException('الصنف أو الوحدة غير موجودين في الفاتورة الأصلية.');
            }

            $itemIdField = $type === 'sale' ? 'sale_item_id' : 'purchase_item_id';
            if (!empty($item[$itemIdField])) {
                $sourceId = (int) $item[$itemIdField];
                if (($sourceById[$sourceId]['key'] ?? null) !== $key) {
                    throw new DomainException('بند المرتجع لا يتبع الفاتورة الأصلية.');
                }
                $requestedBySource[$sourceId] = ($requestedBySource[$sourceId] ?? 0.0) + $quantity;
            } elseif (count($groups[$key]['items']) > 1) {
                throw new DomainException('يجب تحديد بند الفاتورة الأصلي لهذا الصنف قبل إرجاعه.');
            }

            $requestedByGroup[$key] = ($requestedByGroup[$key] ?? 0.0) + $quantity;
        }

        foreach ($requestedByGroup as $key => $quantity) {
            $remaining = $groups[$key]['quantity'] - (float) ($previousQuantities[$key] ?? 0.0);
            if ($quantity > $remaining + 0.0001) {
                throw new DomainException('كمية الإرجاع تتجاوز الكمية المتبقية في الفاتورة الأصلية.');
            }
        }

        $remainingBySource = [];
        foreach ($groups as $key => $group) {
            $previousRemaining = max(0.0, (float) ($previousQuantities[$key] ?? 0.0));
            foreach ($group['items'] as $sourceItem) {
                $sourceId = (int) ($sourceItem['id'] ?? 0);
                if ($sourceId <= 0) {
                    continue;
                }
                $sourceQuantity = (float) $sourceItem['quantity'];
                $previousForSource = min($sourceQuantity, $previousRemaining);
                $remainingBySource[$sourceId] = max(0.0, $sourceQuantity - $previousForSource);
                $previousRemaining -= $previousForSource;
            }
        }
        foreach ($requestedBySource as $sourceId => $quantity) {
            if ($quantity > ($remainingBySource[$sourceId] ?? 0.0) + 0.0001) {
                throw new DomainException('كمية الإرجاع تتجاوز الكمية المتبقية في بند الفاتورة الأصلي.');
            }
        }

        $normalized = [];
        $subtotal = 0.0;
        $tax = 0.0;
        foreach ($requestedItems as $item) {
            $key = self::groupKey((int) $item['product_id'], (int) $item['unit_id']);
            $group = $groups[$key];
            $itemIdField = $type === 'sale' ? 'sale_item_id' : 'purchase_item_id';
            $sourceItem = !empty($item[$itemIdField])
                ? $sourceById[(int) $item[$itemIdField]]['item']
                : $group['items'][0];
            $sourceQuantity = (float) $sourceItem['quantity'];
            $sourceAmount = $type === 'sale'
                ? (float) ($sourceItem['net_total'] ?? 0)
                : max(0.0, (float) ($sourceItem['subtotal'] ?? 0) - (float) ($sourceItem['tax_amount'] ?? 0));
            $sourceTaxAmount = $sourceItem['tax_amount'] ?? null;
            if ($type === 'sale' && $saleTaxInclusive && $saleTaxRate > 0) {
                $lineGross = round(
                    ($sourceAmount / $sourceQuantity) * (float) $item['quantity'],
                    2
                );
                $lineTax = $sourceTaxAmount !== null
                    ? round(((float) $sourceTaxAmount / $sourceQuantity) * (float) $item['quantity'], 2)
                    : round($lineGross - round($lineGross / (1 + ($saleTaxRate / 100)), 2), 2);
                $lineNet = round($lineGross - $lineTax, 2);
                $unitNet = $lineNet / (float) $item['quantity'];
            } else {
                $unitNet = $sourceAmount / $sourceQuantity;
                $lineNet = round($unitNet * (float) $item['quantity'], 2);
                $lineTax = $type === 'sale'
                    ? ($sourceTaxAmount !== null
                        ? round(((float) $sourceTaxAmount / $sourceQuantity) * (float) $item['quantity'], 2)
                        : round($lineNet * max(0.0, $saleTaxRate) / 100, 2))
                    : round(((float) ($sourceTaxAmount ?? 0) / $sourceQuantity) * (float) $item['quantity'], 2);
            }

            $normalized[] = array_merge($item, [
                'product_id' => $group['product_id'],
                'unit_id' => $group['unit_id'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => round($unitNet, 4),
                'subtotal' => $lineNet,
                'tax_amount' => $lineTax,
                'tax_rate' => $type === 'sale' ? $saleTaxRate : null,
                'discount' => 0,
                'discount_amount' => 0,
                'batch_number' => $sourceItem['batch_number'] ?? null,
                'expiry_date' => $sourceItem['expiry_date'] ?? null,
                'serial' => $sourceItem['serial'] ?? null,
            ]);
            $subtotal += $lineNet;
            $tax += $lineTax;
        }

        return [
            'items' => $normalized,
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
        ];
    }

    /** Build the product/unit key used to aggregate invoice and returned quantities. */
    public static function groupKey(int $productId, int $unitId): string
    {
        return $productId . ':' . $unitId;
    }
}
