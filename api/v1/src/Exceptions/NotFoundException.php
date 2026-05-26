<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a requested resource does not exist.
 *
 * Caught by handlers to return a 404 JSON response without fragile
 * string-matching on exception messages.
 *
 * Usage:
 *   throw new NotFoundException('الفاتورة غير موجودة');
 *   throw new NotFoundException('المرتجع غير موجود');
 */
class NotFoundException extends \RuntimeException
{
    public function __construct(
        string $message = 'السجل المطلوب غير موجود',
        \Throwable $previous = null
    ) {
        parent::__construct($message, 404, $previous);
    }
}
