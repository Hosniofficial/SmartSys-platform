<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use Throwable;
use App\Services\MonologHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class SettingsHandler extends BaseHandler
{
    private array $cache = [];

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('settings');
    }

    /**
     * تحديد tenant ID
     */
    private function resolveTenantId(?int $tenantIdParam = null): ?int
    {
        if (!empty($tenantIdParam)) {
            return (int) $tenantIdParam;
        }

        return null;
    }

    /**
     * الحصول على قيمة إعداد
     */
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

    /**
     * تحديث قيمة إعداد
     */
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

    /**
     * حذف إعداد
     */
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

    /**
     * الحصول على مجموعة من الإعدادات
     */
    public function getGroup($prefix, $tenantId = null): array
    {
        $resolvedTenantId = $this->resolveTenantId($tenantId);

        if (!$resolvedTenantId) {
            $this->logger->warning('Settings group get - missing tenant ID', [
                'prefix' => $prefix
            ]);
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT key_name, value, type
             FROM settings
             WHERE key_name LIKE ? AND tenant_id = ?
             ORDER BY key_name"
        );
        $stmt->execute([$prefix . '%', $resolvedTenantId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $settings = [];

        foreach ($results as $result) {
            $key = $result['key_name'];
            $value = $this->castValue($result['value'], $result['type']);

            $settings[$key] = $value;
            $this->cache[$resolvedTenantId . ':' . $key] = $value;
        }

        return $settings;
    }

    /**
     * تحديث مجموعة من الإعدادات
     */
    public function setGroup($settings, $tenantId = null, $userId = null): bool
    {
        $resolvedTenantId = $this->resolveTenantId($tenantId);

        if (!$resolvedTenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }

        // ✅ Check if already in transaction to avoid nested transactions
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

    /**
     * الحصول على إعدادات الشركة
     */
    public function getCompanySettings($tenantId = null): array
    {
        return $this->getGroup('company.', $tenantId);
    }

    /**
     * الحصول على إعدادات الفواتير
     */
    public function getInvoiceSettings($tenantId = null): array
    {
        return $this->getGroup('invoice.', $tenantId);
    }

    /**
     * تحديث إعدادات الفواتير
     */
    public function updateInvoiceSettings($settings, $tenantId = null, $userId = null): bool
    {
        $validSettings = [
            'invoice.prefix',
            'invoice.next_number',
            'invoice.template',
            'invoice.terms',
            'invoice.notes',
            'invoice.due_days',
            'invoice.tax_rate',
            'invoice.show_tax',
            'invoice.show_discount',
            'invoice.show_shipping',
            'invoice.footer'
        ];

        $filteredSettings = array_intersect_key($settings, array_flip($validSettings));

        return $this->setGroup($filteredSettings, $tenantId, $userId);
    }

    /**
     * الحصول على إعدادات الأمان
     */
    public function getSecuritySettings($tenantId = null): array
    {
        return $this->getGroup('security.', $tenantId);
    }

    /**
     * تحديث إعدادات الأمان
     */
    public function updateSecuritySettings($settings, $tenantId = null, $userId = null): bool
    {
        $validSettings = [
            'security.password_policy',
            'security.password_expiry_days',
            'security.session_timeout',
            'security.max_login_attempts',
            'security.lockout_duration',
            'security.two_factor_enabled',
            'security.allowed_ips',
            'security.api_rate_limit',
            'security.jwt_expiry',
            'security.cors_origins'
        ];

        $filteredSettings = array_intersect_key($settings, array_flip($validSettings));

        return $this->setGroup($filteredSettings, $tenantId, $userId);
    }

    /**
     * الحصول على إعدادات الإشعارات
     */
    public function getNotificationSettings($tenantId = null): array
    {
        return $this->getGroup('notification.', $tenantId);
    }

    /**
     * تحديث إعدادات الإشعارات
     */
    public function updateNotificationSettings($settings, $tenantId = null, $userId = null): bool
    {
        $validSettings = [
            'notification.email_enabled',
            'notification.sms_enabled',
            'notification.push_enabled',
            'notification.low_stock_threshold',
            'notification.order_status_change',
            'notification.payment_received',
            'notification.new_customer',
            'notification.product_expiry'
        ];

        $filteredSettings = array_intersect_key($settings, array_flip($validSettings));

        return $this->setGroup($filteredSettings, $tenantId, $userId);
    }

    /**
     * الحصول على إعدادات المخزون
     */
    public function getInventorySettings($tenantId = null): array
    {
        return $this->getGroup('inventory.', $tenantId);
    }

    /**
     * تحديث إعدادات المخزون
     */
    public function updateInventorySettings($settings, $tenantId = null, $userId = null): bool
    {
        $validSettings = [
            'inventory.low_stock_threshold',
            'inventory.reorder_point_calculation',
            'inventory.expiry_notification_days',
            'inventory.allow_backorder',
            'inventory.auto_reorder',
            'inventory.stock_count_interval',
            'inventory.batch_tracking',
            'inventory.serial_tracking'
        ];

        $filteredSettings = array_intersect_key($settings, array_flip($validSettings));

        return $this->setGroup($filteredSettings, $tenantId, $userId);
    }

    /**
     * تحديث الإعدادات
     */
    public function updateSettings($settings, $tenantId = null, $userId = null): bool
    {
        return $this->setGroup($settings, $tenantId, $userId);
    }

    /**
     * تحويل القيمة إلى النوع المناسب
     */
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

    /**
     * تحويل القيمة إلى نص
     */
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

    /**
     * اكتشاف نوع القيمة
     */
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

    /**
     * الحصول على جميع الإعدادات
     */
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

    /**
     * الحصول على الإعدادات الافتراضية
     */
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
            'company.timezone' => 'Africa/Cairo',
            'company.date_format' => 'Y-m-d',
            'company.time_format' => 'H:i:s',
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

    /**
     * الحصول على قائمة العملات المدعومة
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'EGP' => [
                'code' => 'EGP',
                'name' => 'جنيه مصري',
                'nameEn' => 'Egyptian Pound',
                'symbol' => 'ج.م',
                'country' => 'Egypt'
            ],
            'USD' => [
                'code' => 'USD',
                'name' => 'دولار أمريكي',
                'nameEn' => 'US Dollar',
                'symbol' => '$',
                'country' => 'United States'
            ],
            'EUR' => [
                'code' => 'EUR',
                'name' => 'يورو',
                'nameEn' => 'Euro',
                'symbol' => '€',
                'country' => 'Europe'
            ],
            'SAR' => [
                'code' => 'SAR',
                'name' => 'ريال سعودي',
                'nameEn' => 'Saudi Riyal',
                'symbol' => 'ر.س',
                'country' => 'Saudi Arabia'
            ],
            'AED' => [
                'code' => 'AED',
                'name' => 'درهم إماراتي',
                'nameEn' => 'UAE Dirham',
                'symbol' => 'د.إ',
                'country' => 'United Arab Emirates'
            ],
            'KWD' => [
                'code' => 'KWD',
                'name' => 'دينار كويتي',
                'nameEn' => 'Kuwaiti Dinar',
                'symbol' => 'د.ك',
                'country' => 'Kuwait'
            ],
            'QAR' => [
                'code' => 'QAR',
                'name' => 'ريال قطري',
                'nameEn' => 'Qatari Riyal',
                'symbol' => 'ر.ق',
                'country' => 'Qatar'
            ],
            'BHD' => [
                'code' => 'BHD',
                'name' => 'دينار بحريني',
                'nameEn' => 'Bahraini Dinar',
                'symbol' => 'د.ب',
                'country' => 'Bahrain'
            ],
            'OMR' => [
                'code' => 'OMR',
                'name' => 'ريال عماني',
                'nameEn' => 'Omani Rial',
                'symbol' => 'ر.ع',
                'country' => 'Oman'
            ]
        ];
    }

    /**
     * تسجيل تغيير الإعدادات
     */
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
