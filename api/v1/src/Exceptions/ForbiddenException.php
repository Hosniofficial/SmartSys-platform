<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a user lacks the required permission for an action.
 *
 * Caught by the global error handler (config/middleware.php) which returns
 * a 403 JSON response. Can also be caught inside handlers for custom messages.
 *
 * Usage:
 *   throw new ForbiddenException(Permissions::SALE_VOID);
 *   throw new ForbiddenException('sale.void', 'لا يمكنك إلغاء فاتورة معتمدة');
 */
class ForbiddenException extends \RuntimeException
{
    private string $requiredPermission;

    public function __construct(
        string $requiredPermission,
        string $message = '',
        \Throwable $previous = null
    ) {
        $this->requiredPermission = $requiredPermission;

        if ($message === '') {
            $message = "ليس لديك صلاحية تنفيذ هذا الإجراء. (مطلوب: {$requiredPermission})";
        }

        parent::__construct($message, 403, $previous);
    }

    public function getRequiredPermission(): string
    {
        return $this->requiredPermission;
    }
}
