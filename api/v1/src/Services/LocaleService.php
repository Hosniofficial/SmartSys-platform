<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * LocaleService
 *
 * Centralises locale/language detection used across handlers.
 * Replaces the duplicated getLocale() / inline Accept-Language checks that
 * existed independently in PurchasesHandler, ReturnsHandler, and others.
 *
 * Detection strategy:
 *   1. Accept-Language request header  (e.g. "ar", "ar-EG", "en-US")
 *   2. Query-string parameter          ?lang=ar  |  ?locale=en
 *   3. Default: 'ar'
 */
class LocaleService
{
    /**
     * Resolves the preferred locale from a PSR-7 request.
     *
     * Returns 'ar' or 'en'.
     *
     * @param Request $request
     * @return string
     */
    public static function fromRequest(Request $request): string
    {
        // 1. Explicit query-string override: ?lang=en or ?locale=en
        $qp = $request->getQueryParams();
        $explicit = $qp['lang'] ?? ($qp['locale'] ?? null);
        if ($explicit !== null) {
            return self::normalise((string) $explicit);
        }

        // 2. Accept-Language header
        $header = $request->getHeaderLine('Accept-Language');
        if ($header !== '') {
            return self::normalise($header);
        }

        // 3. Default
        return 'ar';
    }

    /**
     * Resolves locale from a raw Accept-Language string or locale code.
     *
     * @param string $value  e.g. 'ar', 'ar-EG,ar;q=0.9', 'en-US', 'en'
     * @return string 'ar' or 'en'
     */
    public static function fromString(string $value): string
    {
        return self::normalise($value);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Normalises any locale/Accept-Language string to 'ar' or 'en'.
     */
    private static function normalise(string $value): string
    {
        // Take only the primary language tag (before comma or semicolon)
        $primary = strtolower(trim(explode(',', explode(';', $value)[0])[0]));

        if (str_starts_with($primary, 'ar')) {
            return 'ar';
        }

        return 'en';
    }
}
