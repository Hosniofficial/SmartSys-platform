<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * SuperAdminHelper
 *
 * Single source of truth for super-admin identity checks.
 *
 * Problem solved:
 *   The codebase had 6 different ways to detect a super-admin:
 *     - PermissionMiddleware  → role_id === 1  (SUPER_ADMIN_ROLE_ID constant)
 *     - SubscriptionMiddleware → role === 'super_admin'  (string)
 *     - SuperAdminMiddleware   → role !== 'super_admin'  (string)
 *     - AdminSettingsHandler   → in_array(role, ['super_admin','admin'])
 *     - RBACHandler            → in_array(role, ['super_admin','admin'])
 *     - UsersHandler           → role_id === 1  (hardcoded)
 *
 *   A change to the role name or ID in one place would silently break others.
 *
 * Usage:
 *   use App\Utils\SuperAdminHelper;
 *
 *   if (SuperAdminHelper::is($user)) { ... }
 *   if (SuperAdminHelper::isAdminOrAbove($user)) { ... }
 */
final class SuperAdminHelper
{
    /** The canonical role_id for super_admin in the database. */
    public const SUPER_ADMIN_ROLE_ID = 1;

    /** The canonical role string stored in the users table. */
    public const SUPER_ADMIN_ROLE = 'super_admin';

    /** Role strings that are considered "admin or above". */
    private const ADMIN_ROLES = ['super_admin', 'admin'];

    /**
     * Returns true if the given user payload represents a super-admin.
     *
     * Accepts both role_id (int) and role (string) checks so that
     * callers do not need to know which column is populated.
     *
     * @param array|null $user  Associative array from JWT / request attribute.
     *                          Expected keys: 'role_id' (int) and/or 'role' (string).
     */
    public static function is(?array $user): bool
    {
        if (empty($user)) {
            return false;
        }

        // Check by role_id (most reliable — not affected by string casing)
        if (isset($user['role_id']) && (int) $user['role_id'] === self::SUPER_ADMIN_ROLE_ID) {
            return true;
        }

        // Check by role string (case-insensitive for safety)
        if (isset($user['role']) && strtolower((string) $user['role']) === self::SUPER_ADMIN_ROLE) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the user is a super_admin OR a regular admin.
     *
     * Used by AdminSettingsHandler and RBACHandler which allow both roles.
     *
     * @param array|null $user
     */
    public static function isAdminOrAbove(?array $user): bool
    {
        if (empty($user)) {
            return false;
        }

        if (self::is($user)) {
            return true;
        }

        if (isset($user['role']) && in_array(strtolower((string) $user['role']), self::ADMIN_ROLES, true)) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the user is NOT a super-admin.
     * Convenience inverse used by SuperAdminMiddleware.
     *
     * @param array|null $user
     */
    public static function isNot(?array $user): bool
    {
        return !self::is($user);
    }
}
