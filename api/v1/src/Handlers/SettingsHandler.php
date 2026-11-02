<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use Throwable;
use App\Services\MonologHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Persists and retrieves typed, tenant-scoped settings.
 *
 * Settings are stored in the settings table, cached per handler instance, and
 * audited on writes. AdminSettingsHandler uses this class for API persistence;
 * StrictSubscriptionHandler also reads settings internally.
 * Authorization for settings endpoints is handled by AdminSettingsHandler and
 * app/routes/settings.php.
 */
class SettingsHandler extends BaseHandler
{
    private const BRANCH_PRINT_DEFAULTS = [
        'print.template' => 'thermal-compact',
        'printer.print_header' => '1',
        'printer.print_footer' => '1',
        'print.header_text' => '',
        'print.footer_text' => 'شكراً لتعاملكم معنا',
        'print.terms_text' => '',
    ];

    private const BRANCH_PRINT_TEMPLATES = [
        'thermal-compact',
        'thermal-detailed',
        'a4-simple',
        'a4-professional',
    ];

    private array $cache = [];

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('settings');
    }

    private function resolveTenantId(?int $tenantIdParam = null): ?int
    {
        if (!empty($tenantIdParam)) {
            return (int) $tenantIdParam;
        }

        return null;
    }

    /** Read a typed setting from the instance cache or tenant-scoped database row. */
    public function get($key, $default = null, $tenantId = null)
    {
        $resolvedTenantId = null;

        try {
            $resolvedTenantId = $this->resolveTenantId($tenantId);

            if (!$resolvedTenantId) {
                $this->logger->warning('Settings get - missing tenant ID', [
                    'key' => $key
                ]);
                return $default;
            }

            $cacheKey = $resolvedTenantId . ':' . $key;

            if (array_key_exists($cacheKey, $this->cache)) {
                $this->logger->debug('Settings retrieved from cache', [
                    'tenant_id' => $resolvedTenantId,
                    'key' => $key
                ]);
                return $this->cache[$cacheKey];
            }

            $stmt = $this->db->prepare(
                "SELECT value, type
                 FROM settings
                 WHERE key_name = ? AND tenant_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$key, $resolvedTenantId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $this->logger->debug('Settings not found, returning default', [
                    'tenant_id' => $resolvedTenantId,
                    'key' => $key
                ]);
                return $default;
            }

            $value = $this->castValue($result['value'], $result['type']);
            $this->cache[$cacheKey] = $value;

            $this->logger->debug('Settings retrieved successfully', [
                'tenant_id' => $resolvedTenantId,
                'key' => $key,
                'type' => $result['type']
            ]);

            return $value;
        } catch (Throwable $e) {
            $this->logger->error('Settings get error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $resolvedTenantId ?? 'unknown',
                'key' => $key ?? 'unknown'
            ]);

            return $default;
        }
    }

    /** Serialize, persist, cache, and audit one tenant setting. */
    public function set($key, $value, $type = null, $tenantId = null, $userId = null): bool
    {
        $resolvedTenantId = null;

        try {
            $resolvedTenantId = $this->resolveTenantId($tenantId);

            if (!$resolvedTenantId) {
                $this->logger->error('Settings set - missing tenant ID', [
                    'key' => $key
                ]);
                throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
            }

            if ($type === null) {
                $type = $this->detectValueType($value);
            }

            $serializedValue = $this->serializeValue($value);

            $this->logger->info('Settings update request', [
                'tenant_id' => $resolvedTenantId,
                'user_id' => $userId,
                'key' => $key,
                'type' => $type
            ]);

            $stmt = $this->db->prepare(
                "INSERT INTO settings (key_name, value, type, tenant_id, updated_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    type = VALUES(type),
                    updated_at = NOW()"
            );

            $success = $stmt->execute([
                $key,
                $serializedValue,
                $type,
                $resolvedTenantId
            ]);

            if (!$success) {
                $this->logger->error('Settings update failed', [
                    'tenant_id' => $resolvedTenantId,
                    'key' => $key
                ]);
                return false;
            }

            $cacheKey = $resolvedTenantId . ':' . $key;
            $this->cache[$cacheKey] = $value;

            $this->logSettingChange($key, $value, 'update', $resolvedTenantId, $userId);

            $this->logger->info('Settings updated successfully', [
                'tenant_id' => $resolvedTenantId,
                'user_id' => $userId,
                'key' => $key,
                'type' => $type
            ]);

            return true;
        } catch (Throwable $e) {
            $this->logger->error('Settings set error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $resolvedTenantId ?? 'unknown',
                'key' => $key ?? 'unknown'
            ]);

            throw $e;
        }
    }

    /** Delete a tenant setting, evict its cache entry, and audit the change. */
    public function delete($key, $tenantId = null, $userId = null): bool
    {
        $resolvedTenantId = null;

        try {
            $resolvedTenantId = $this->resolveTenantId($tenantId);

            if (!$resolvedTenantId) {
                $this->logger->error('Settings delete - missing tenant ID', [
                    'key' => $key
                ]);
                throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
            }

            $this->logger->info('Settings delete request', [
                'tenant_id' => $resolvedTenantId,
                'user_id' => $userId,
                'key' => $key
            ]);

            $stmt = $this->db->prepare(
                "DELETE FROM settings
                 WHERE key_name = ? AND tenant_id = ?"
            );
            $success = $stmt->execute([$key, $resolvedTenantId]);

            if (!$success) {
                $this->logger->error('Settings delete failed', [
                    'tenant_id' => $resolvedTenantId,
                    'key' => $key
                ]);
                return false;
            }

            unset($this->cache[$resolvedTenantId . ':' . $key]);
            $this->logSettingChange($key, null, 'delete', $resolvedTenantId, $userId);

            $this->logger->info('Settings deleted successfully', [
                'tenant_id' => $resolvedTenantId,
                'key' => $key
            ]);

            return true;
        } catch (Throwable $e) {
            $this->logger->error('Settings delete error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $resolvedTenantId ?? 'unknown',
                'key' => $key ?? 'unknown'
            ]);

            throw $e;
        }
    }

    /** Persist a settings map, reusing an active database transaction. */
    public function setGroup($settings, $tenantId = null, $userId = null): bool
    {
        $resolvedTenantId = $this->resolveTenantId($tenantId);

        if (!$resolvedTenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }

        // Reuse an active transaction instead of opening a nested transaction.
        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            foreach ($settings as $key => $value) {
                $this->set($key, $value, null, $resolvedTenantId, $userId);
            }

            if (!$inTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function updateSettings($settings, $tenantId = null, $userId = null): bool
    {
        return $this->setGroup($settings, $tenantId, $userId);
    }

    /** Return tenant defaults merged with the selected branch's print overrides. */
    public function getBranchPrintSettings(int $tenantId, int $branchId): array
    {
        $effective = [];
        foreach (self::BRANCH_PRINT_DEFAULTS as $key => $default) {
            $value = $this->get($key, $default, $tenantId);
            $effective[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        $tenantTemplateStmt = $this->db->prepare(
            "SELECT 1 FROM settings WHERE tenant_id = ? AND key_name = 'print.template' LIMIT 1"
        );
        $tenantTemplateStmt->execute([$tenantId]);
        $tenantTemplateConfigured = $tenantTemplateStmt->fetchColumn() !== false;

        $stmt = $this->db->prepare(
            'SELECT key_name, value
             FROM branch_settings
             WHERE tenant_id = ? AND branch_id = ?'
        );
        $stmt->execute([$tenantId, $branchId]);
        $overrides = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string) $row['key_name'];
            if (array_key_exists($key, self::BRANCH_PRINT_DEFAULTS)) {
                $effective[$key] = (string) $row['value'];
                $overrides[] = $key;
            }
        }

        return [
            'settings' => $effective,
            'overrides' => $overrides,
            'tenant_template_configured' => $tenantTemplateConfigured,
        ];
    }

    /** Validate and persist the supported print settings for one tenant branch. */
    public function updateBranchPrintSettings(
        int $tenantId,
        int $branchId,
        int $userId,
        array $settings
    ): void {
        if ($settings === []) {
            throw new \InvalidArgumentException('يجب إرسال إعداد طباعة واحد على الأقل.');
        }

        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, self::BRANCH_PRINT_DEFAULTS)) {
                throw new \InvalidArgumentException('إعداد الفرع غير مدعوم: ' . $key);
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('قيمة إعداد الطباعة غير صالحة: ' . $key);
            }

            $value = (string) $value;
            if ($key === 'print.template' && !in_array($value, self::BRANCH_PRINT_TEMPLATES, true)) {
                throw new \InvalidArgumentException('قالب الطباعة المحدد غير صالح.');
            }
            if (in_array($key, ['printer.print_header', 'printer.print_footer'], true)
                && !in_array($value, ['0', '1'], true)
            ) {
                throw new \InvalidArgumentException('قيمة خيار الطباعة يجب أن تكون 0 أو 1.');
            }
            if (strlen($value) > 5000) {
                throw new \InvalidArgumentException('نص إعداد الطباعة يتجاوز الحد المسموح.');
            }
            $settings[$key] = $value;
        }

        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $tenantTemplateStmt = $this->db->prepare(
                "SELECT 1 FROM settings
                 WHERE tenant_id = ? AND key_name = 'print.template'
                 LIMIT 1"
            );
            $tenantTemplateStmt->execute([$tenantId]);
            $tenantTemplateConfigured = $tenantTemplateStmt->fetchColumn() !== false;

            $upsertSql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT INTO branch_settings
                    (tenant_id, branch_id, key_name, value, type, updated_by, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                   ON CONFLICT (tenant_id, branch_id, key_name) DO UPDATE SET
                    value = excluded.value,
                    type = excluded.type,
                    updated_by = excluded.updated_by,
                    updated_at = CURRENT_TIMESTAMP'
                : 'INSERT INTO branch_settings
                    (tenant_id, branch_id, key_name, value, type, updated_by, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                   ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    type = VALUES(type),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP';
            $upsert = $this->db->prepare($upsertSql);
            $delete = $this->db->prepare(
                'DELETE FROM branch_settings
                 WHERE tenant_id = ? AND branch_id = ? AND key_name = ?'
            );
            $auditChanges = [];
            foreach ($settings as $key => $value) {
                $tenantDefault = $this->get($key, self::BRANCH_PRINT_DEFAULTS[$key], $tenantId);
                $tenantDefault = is_bool($tenantDefault)
                    ? ($tenantDefault ? '1' : '0')
                    : (string) $tenantDefault;
                if ($value === $tenantDefault && ($key !== 'print.template' || $tenantTemplateConfigured)) {
                    $delete->execute([$tenantId, $branchId, $key]);
                    $auditChanges[] = [$key, null, 'delete'];
                    continue;
                }

                $upsert->execute([$tenantId, $branchId, $key, $value, 'string', $userId]);
                $auditChanges[] = [$key, $value, 'update'];
            }
            if (!$inTransaction) {
                $this->db->commit();
            }

            foreach ($auditChanges as [$key, $value, $action]) {
                $this->logSettingChange($key, $value, $action, $tenantId, $userId);
            }
        } catch (Throwable $e) {
            if (!$inTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Remove all branch print overrides so the branch inherits tenant defaults. */
    public function resetBranchPrintSettings(int $tenantId, int $branchId, int $userId): void
    {
        $keys = array_keys(self::BRANCH_PRINT_DEFAULTS);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $select = $this->db->prepare(
            "SELECT key_name
             FROM branch_settings
             WHERE tenant_id = ? AND branch_id = ? AND key_name IN ({$placeholders})"
        );
        $select->execute(array_merge([$tenantId, $branchId], $keys));
        $removedKeys = $select->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $stmt = $this->db->prepare(
            "DELETE FROM branch_settings
             WHERE tenant_id = ? AND branch_id = ? AND key_name IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$tenantId, $branchId], $keys));
        foreach ($removedKeys as $key) {
            $this->logSettingChange($key, null, 'delete', $tenantId, $userId);
        }
        $this->logger->info('Branch print settings reset to tenant defaults', [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
        ]);
    }

    private function castValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

            case 'integer':
                return (int) $value;

            case 'float':
                return (float) $value;

            case 'array':
                return json_decode($value, true) ?? [];

            case 'object':
                return json_decode($value);

            default:
                return $value;
        }
    }

    private function serializeValue($value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function detectValueType($value): string
    {
        switch (true) {
            case is_bool($value):
                return 'boolean';

            case is_int($value):
                return 'integer';

            case is_float($value):
                return 'float';

            case is_array($value):
                return 'array';

            case is_object($value):
                return 'object';

            default:
                return 'string';
        }
    }

    /** Load tenant settings, falling back to built-in defaults when unavailable. */
    public function getSettings($tenantId = null): array
    {
        $resolvedTenantId = $this->resolveTenantId($tenantId);

        try {
            if (!$resolvedTenantId) {
                return $this->getDefaultSettings();
            }

            $stmt = $this->db->prepare(
                "SELECT key_name, value, type
                 FROM settings
                 WHERE tenant_id = ?
                 ORDER BY key_name"
            );
            $stmt->execute([$resolvedTenantId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $settings = [];

            foreach ($results as $result) {
                $key = $result['key_name'];
                $value = $this->castValue($result['value'], $result['type']);

                $settings[$key] = $value;
                $this->cache[$resolvedTenantId . ':' . $key] = $value;
            }

            if (empty($settings)) {
                return $this->getDefaultSettings();
            }

            return $settings;
        } catch (Throwable $e) {
            $this->logger->error('Error getting settings', [
                'message' => $e->getMessage(),
                'tenant_id' => $resolvedTenantId ?? 'unknown'
            ]);

            return $this->getDefaultSettings();
        }
    }

    private function getDefaultSettings(): array
    {
        return [
            'company.name' => 'SmartSys',
            'company.address' => '',
            'company.phone' => '',
            'company.email' => '',
            'company.website' => '',
            'company.tax_number' => '',
            'company.registration_number' => '',
            'company.logo' => '',
            'company.currency' => 'EGP',
            // app.* is the canonical namespace for locale/format settings (used by frontend).
            // company.timezone / company.date_format / company.time_format are kept as aliases
            // only for backward compatibility with any legacy DB rows.
            'app.timezone'    => 'Africa/Cairo',
            'app.date_format' => 'DD/MM/YYYY',
            'app.time_format' => '12',
            'system.language' => 'ar',
            'system.theme' => 'light',
            'inventory.low_stock_alert' => 10,
            'inventory.auto_reorder' => false,
            'sales.tax_rate' => 15.0,
            'sales.discount_enabled' => true,
            'notifications.email_enabled' => true,
            'notifications.sms_enabled' => false
        ];
    }

    /** Audit a setting change without failing the persistence operation on audit errors. */
    private function logSettingChange($key, $value, $action = 'update', $tenantId = null, $userId = null): void
    {
        try {
            $this->logger->debug('Logging setting change', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'key' => $key,
                'action' => $action
            ]);

            $auditHandler = $this->audit;
            $auditHandler->logAction(
                $action,
                'settings',
                null,
                [
                    'key' => $key,
                    'value' => $value
                ],
                $tenantId,
                $userId
            );

            $this->logger->debug('Setting change logged successfully', [
                'tenant_id' => $tenantId,
                'key' => $key,
                'action' => $action
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Settings audit log failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'key' => $key ?? 'unknown',
                'action' => $action ?? 'unknown'
            ]);
        }
    }
}
