<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * DateHelper
 *
 * Centralises date/time normalisation used across handlers and services.
 *
 * Replaces the duplicated normalizeDateTime() private method that existed
 * in PurchasesHandler (and inline date logic scattered elsewhere).
 *
 * Usage:
 *   use App\Utils\DateHelper;
 *
 *   $start = DateHelper::normalize($qp['date_from']);           // 'Y-m-d 00:00:00'
 *   $end   = DateHelper::normalize($qp['date_to'], true);       // 'Y-m-d 23:59:59'
 *   $ts    = DateHelper::normalize('2024-03-15T10:30:00');      // 'Y-m-d H:i:s'
 */
final class DateHelper
{
    /**
     * Normalise any date/datetime string to a MySQL-compatible 'Y-m-d H:i:s' string.
     *
     * Rules:
     *  - null / empty  → current datetime (NOW equivalent)
     *  - date-only     → 'Y-m-d 00:00:00'  (or 'Y-m-d 23:59:59' when $endOfDay = true)
     *  - datetime      → 'Y-m-d H:i:s'
     *
     * @param string|null $value     Raw date string from request / payload.
     * @param bool        $endOfDay  When true and value is date-only, returns 23:59:59.
     *
     * @throws \InvalidArgumentException  When the value cannot be parsed.
     */
    public static function normalize(?string $value, bool $endOfDay = false): string
    {
        if ($value === null || trim($value) === '') {
            return date('Y-m-d H:i:s');
        }

        $ts = strtotime($value);
        if ($ts === false) {
            throw new \InvalidArgumentException('تنسيق التاريخ غير صالح: ' . $value);
        }

        // Date-only string (≤ 10 chars: 'YYYY-MM-DD')
        if (strlen(trim($value)) <= 10) {
            return date($endOfDay ? 'Y-m-d 23:59:59' : 'Y-m-d 00:00:00', $ts);
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Convenience: start-of-day normalisation.
     *
     * @param string|null $value
     * @return string  'Y-m-d 00:00:00'
     */
    public static function startOfDay(?string $value): string
    {
        return self::normalize($value, false);
    }

    /**
     * Convenience: end-of-day normalisation.
     *
     * @param string|null $value
     * @return string  'Y-m-d 23:59:59'
     */
    public static function endOfDay(?string $value): string
    {
        return self::normalize($value, true);
    }

    /**
     * Returns today's date as 'Y-m-d'.
     */
    public static function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * Returns current datetime as 'Y-m-d H:i:s'.
     */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
