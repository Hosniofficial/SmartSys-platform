<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use Throwable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;
use App\Utils\SuperAdminHelper;
class AdminSettingsHandler extends BaseHandler
{
    private SettingsHandler $settingsHandler;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('admin_settings');
        $this->settingsHandler = new SettingsHandler($db);
    }

    /**
     * تحقق من صلاحيات الوصول للإعدادات
     */
    private function checkSettingsAccess(Request $request, bool $requireManage = false): ?array
    {
        $userData = $request->getAttribute('user');

        if (is_object($userData)) {
            $userData = (array) $userData;
        } elseif (!is_array($userData)) {
            $userData = [];
        }

        $userId = isset($userData['id']) ? (int) $userData['id'] : null;
        $roleId = isset($userData['role_id']) ? (int) $userData['role_id'] : null;
        $role = strtolower((string) ($userData['role'] ?? ''));

        if (!$userId) {
            return null;
        }

        try {
            $rbac = new RBACHandler($this->db);

            if ($requireManage) {
                $hasPermission = $rbac->hasPermission($userId, 'settings.manage');
            } else {
                $hasPermission =
                    $rbac->hasPermission($userId, 'settings.view') ||
                    $rbac->hasPermission($userId, 'settings.manage');
            }

            if (!$hasPermission) {
                $hasPermission = SuperAdminHelper::isAdminOrAbove(['role_id' => $roleId, 'role' => $role]);
            }

            return $hasPermission ? $userData : null;
        } catch (Throwable $e) {
            $this->logger->error('Error checking settings permissions', [
                'message' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return null;
        }
    }

    /**
     * GET /settings
     * الحصول على جميع الإعدادات
     */
    public function getSettings(Request $request, Response $response): Response
    {
        $userData = $this->checkSettingsAccess($request, false);

        if (!$userData) {
            return $this->errorResponse($response, 'ليس لديك إذن للوصول إلى الإعدادات', 403);
        }

        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $settings = $this->settingsHandler->getSettings((int) $tenantId);

            return $this->successResponse($response, $settings, 200);
        } catch (Throwable $e) {
            $this->logger->error('Failed to retrieve settings', [
                'message' => $e->getMessage(),
                'tenant_id' => $this->extractTenantId($request)
            ]);

            return $this->errorResponse($response, 'فشل في جلب الإعدادات', 500);
        }
    }

    /**
     * PUT /settings
     * تحديث الإعدادات
     */
    public function updateSettings(Request $request, Response $response): Response
    {
        $userData = $this->checkSettingsAccess($request, true);

        if (!$userData) {
            return $this->errorResponse($response, 'ليس لديك إذن لتحديث الإعدادات', 403);
        }

        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $parsed = $request->getParsedBody();
            $parsed = is_array($parsed) ? $parsed : [];
            $settings = $parsed['settings'] ?? [];

            if (!is_array($settings)) {
                return $this->errorResponse($response, 'بيانات الإعدادات غير صالحة', 400);
            }

            $userId = isset($userData['id']) ? (int) $userData['id'] : null;
            $this->settingsHandler->updateSettings($settings, (int) $tenantId, $userId);

            return $this->successResponse($response, [
                'updated' => true
            ], 200);
        } catch (Throwable $e) {
            $this->logger->error('Failed to update settings', [
                'message' => $e->getMessage(),
                'tenant_id' => $this->extractTenantId($request),
                'user_id' => $userData['id'] ?? null
            ]);

            return $this->errorResponse($response, 'فشل في تحديث الإعدادات', 500);
        }
    }

    /**
     * POST /settings/logo
     * رفع شعار الشركة وتحديث الإعدادات
     */
    public function uploadLogo(Request $request, Response $response): Response
    {
        $userData = $this->checkSettingsAccess($request, true);

        if (!$userData) {
            return $this->errorResponse($response, 'ليس لديك إذن لتحديث الإعدادات', 403);
        }

        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $directory = __DIR__ . '/../../../public/uploads/logos/';
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new Exception('تعذر إنشاء مجلد رفع الشعارات');
            }

            $uploadedFiles = $request->getUploadedFiles();
            $parsedBody = $request->getParsedBody();
            $parsedBody = is_array($parsedBody) ? $parsedBody : [];
            $userId = isset($userData['id']) ? (int) $userData['id'] : null;

            $settingsJson = $parsedBody['settings'] ?? '[]';
            $settings = json_decode((string) $settingsJson, true);

            if (!empty($settings)) {
                if (!is_array($settings)) {
                    return $this->errorResponse($response, 'تنسيق settings غير صالح', 400);
                }

                if (isset($settings['company.currency'])) {
                    $supportedCurrencies = ['EGP', 'USD', 'EUR', 'SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR'];
                    $currency = strtoupper((string) $settings['company.currency']);

                    if (!in_array($currency, $supportedCurrencies, true)) {
                        return $this->errorResponse($response, 'العملة غير مدعومة: ' . $currency, 400);
                    }

                    $settings['company.currency'] = $currency;
                    $settings['company.currency_code'] = $currency;

                    $currencySymbols = [
                        'EGP' => 'ج.م',
                        'USD' => '$',
                        'EUR' => '€',
                        'SAR' => 'ر.س',
                        'AED' => 'د.إ',
                        'KWD' => 'د.ك',
                        'QAR' => 'ر.ق',
                        'BHD' => 'د.ب',
                        'OMR' => 'ر.ع'
                    ];
                    $settings['company.currency_symbol'] = $currencySymbols[$currency] ?? '';
                }

                $this->settingsHandler->updateSettings($settings, (int) $tenantId, $userId);
            }

            if (isset($uploadedFiles['logo'])) {
                $logo = $uploadedFiles['logo'];

                if ($logo->getError() !== UPLOAD_ERR_OK) {
                    return $this->errorResponse($response, 'خطأ في رفع ملف الشعار', 400);
                }

                $clientFilename = (string) $logo->getClientFilename();
                $safeOriginal = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $clientFilename);
                $extension = strtolower(pathinfo($safeOriginal, PATHINFO_EXTENSION));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                if (!in_array($extension, $allowedExtensions, true)) {
                    return $this->errorResponse($response, 'نوع ملف الشعار غير مدعوم', 400);
                }

                $filename = uniqid('logo_', true) . '.' . $extension;
                $fullPath = $directory . $filename;

                $logo->moveTo($fullPath);

                $relativePath = 'uploads/logos/' . $filename;
                $this->settingsHandler->set('company.logo', $relativePath, null, (int) $tenantId, $userId);

                return $this->successResponse($response, [
                    'logo_path' => $relativePath
                ], 200);
            }

            return $this->successResponse($response, [
                'updated' => true
            ], 200);
        } catch (Throwable $e) {
            $this->logger->error('Failed to upload logo or update settings', [
                'message' => $e->getMessage(),
                'tenant_id' => $this->extractTenantId($request),
                'user_id' => $userData['id'] ?? null
            ]);

            return $this->errorResponse($response, 'فشل في رفع الشعار', 500);
        }
    }

    /**
     * Endpoint: GET /settings/currencies/supported
     */
    public function getSupportedCurrenciesAPI(Request $request, Response $response): Response
    {
        $userData = $this->checkSettingsAccess($request, false);

        if (!$userData) {
            return $this->errorResponse($response, 'ليس لديك إذن للوصول إلى الإعدادات', 403);
        }

        try {
            $currencies = $this->settingsHandler->getSupportedCurrencies();
            $currenciesList = array_values($currencies);

            return $this->successResponse($response, $currenciesList, 200);
        } catch (Throwable $e) {
            $this->logger->error('Failed to retrieve supported currencies', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'فشل في جلب العملات المدعومة', 500);
        }
    }
}