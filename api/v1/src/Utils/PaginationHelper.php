<?php

declare(strict_types=1);

namespace App\Utils;

use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * PaginationHelper
 *
 * Centralises pagination boilerplate used across 15+ handlers.
 *
 * Replaces the repeated pattern:
 *   $page    = max(1, (int)($qp['page'] ?? 1));
 *   $perPage = max(1, min(200, (int)($qp['per_page'] ?? 20)));
 *   $offset  = ($page - 1) * $perPage;
 *   ...
 *   'pagination' => ['total'=>$total, 'per_page'=>$perPage, ...]
 *
 * Usage:
 *   [$page, $perPage, $offset] = PaginationHelper::fromRequest($request);
 *   ...
 *   'pagination' => PaginationHelper::buildMeta($total, $page, $perPage)
 */
final class PaginationHelper
{
    /** Absolute maximum rows per page — prevents runaway queries. */
    public const MAX_PER_PAGE = 200;

    /** Default rows per page when not specified. */
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Extract page / perPage / offset from a PSR-7 request's query params.
     *
     * Supported query params:
     *   page      — 1-based page number          (default: 1)
     *   per_page  — rows per page                (default: 20, max: 200)
     *   limit     — alias for per_page
     *
     * @param Request $request
     * @param int     $defaultPerPage  Override the default rows-per-page.
     * @param int     $maxPerPage      Override the hard cap.
     *
     * @return array{0: int, 1: int, 2: int}  [$page, $perPage, $offset]
     */
    public static function fromRequest(
        Request $request,
        int $defaultPerPage = self::DEFAULT_PER_PAGE,
        int $maxPerPage = self::MAX_PER_PAGE
    ): array {
        $qp = $request->getQueryParams();
        return self::fromArray($qp, $defaultPerPage, $maxPerPage);
    }

    /**
     * Extract page / perPage / offset from a plain array (e.g. already-parsed query params).
     *
     * @param array $params
     * @param int   $defaultPerPage
     * @param int   $maxPerPage
     *
     * @return array{0: int, 1: int, 2: int}  [$page, $perPage, $offset]
     */
    public static function fromArray(
        array $params,
        int $defaultPerPage = self::DEFAULT_PER_PAGE,
        int $maxPerPage = self::MAX_PER_PAGE
    ): array {
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(
            $maxPerPage,
            (int) ($params['per_page'] ?? ($params['limit'] ?? $defaultPerPage))
        ));
        $offset  = ($page - 1) * $perPage;

        return [$page, $perPage, $offset];
    }

    /**
     * Build the standard pagination metadata array included in list responses.
     *
     * Returns:
     * [
     *   'total'        => 150,
     *   'per_page'     => 20,
     *   'current_page' => 3,
     *   'last_page'    => 8,
     * ]
     *
     * @param int $total    Total number of matching records.
     * @param int $page     Current page (1-based).
     * @param int $perPage  Rows per page.
     */
    public static function buildMeta(int $total, int $page, int $perPage): array
    {
        return [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / max(1, $perPage)),
        ];
    }
}
