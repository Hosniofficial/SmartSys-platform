<?php

namespace App\Exceptions;

/**
 * استثناء يُرمى عند محاولة البيع بكمية تتجاوز المخزون المتوفر
 *
 * يحتوي على بيانات إضافية تساعد الواجهة الأمامية على التعامل مع الخطأ بذكاء
 */
class InsufficientStockException extends \Exception
{
    public readonly int $productId;
    public readonly float $availableQty;
    public readonly float $requestedQty;

    public function __construct(
        string $message,
        int $productId,
        float $availableQty,
        float $requestedQty = 0
    ) {
        parent::__construct($message);
        $this->productId = $productId;
        $this->availableQty = $availableQty;
        $this->requestedQty = $requestedQty;
    }

    /**
     * تحويل الاستثناء إلى مصفوفة للإرجاع عبر API
     */
    public function toArray(): array
    {
        return [
            'status' => 'error',
            'error_code' => 'insufficient_stock',
            'product_id' => $this->productId,
            'available_qty' => $this->availableQty,
            'requested_qty' => $this->requestedQty,
            'message' => $this->getMessage(),
        ];
    }
}
