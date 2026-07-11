<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;
use App\Services\AccountManagementService;

class PaymentMethodsHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('payment_methods');
    }

    /**
     * إرجاع طرق الدفع الخاصة بالـ tenant فقط.
     * الـ global records (tenant_id IS NULL) هي seed templates فقط ولا تُعرض للمستخدمين.
     */
    public function list(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $stmt = $this->db->prepare("
                SELECT id, name, kind, description, payment_terms, account_id,
                       created_at, updated_at,
                       0 AS is_global
                FROM payment_methods
                WHERE tenant_id = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$tenantId]);
            $methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse($response, $methods, 200);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to list payment methods', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء جلب طرق الدفع', 500);
        }
    }

    /**
     * تحديث طريقة الدفع (kind + account_id)
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $id = isset($args['id']) ? (int) $args['id'] : 0;
            if ($id <= 0) {
                return $this->errorResponse($response, 'معرف طريقة الدفع غير صالح', 400);
            }

            $body = $request->getParsedBody() ?? [];
            $kind = isset($body['kind']) ? strtolower(trim((string) $body['kind'])) : null;

            $allowed = ['cash', 'bank', 'card', 'credit', 'wallet', 'other'];
            if (!$kind || !in_array($kind, $allowed, true)) {
                return $this->errorResponse(
                    $response,
                    'نوع الطريقة غير صالح. القيم المسموحة: cash, bank, card, credit, wallet, other',
                    400
                );
            }

            // account_id is optional — null means "remove the link"
            $accountId = array_key_exists('account_id', $body)
                ? ($body['account_id'] !== null && $body['account_id'] !== '' ? (int) $body['account_id'] : null)
                : false; // false = not sent → don't change

            // Auto-provision wallet without account_id → create account 1003 automatically
            $walletKinds = ['wallet'];
            if (in_array($kind, $walletKinds, true) && ($accountId === false || $accountId === null)) {
                try {
                    $mgmt      = new AccountManagementService($this->db);
                    $accountId = $mgmt->provisionLiquidityAccount('1003', 'محفظة إلكترونية', $kind, $tenantId);
                } catch (\Throwable $e) {
                    $this->logger->warning('Auto-provision wallet account failed, proceeding without', [
                        'tenant_id' => $tenantId,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            // منع إزالة account_id من الأنواع غير الآجلة
            $creditKinds = ['credit'];
            if (!in_array($kind, $creditKinds, true) && $accountId === null) {
                return $this->errorResponse(
                    $response,
                    'لا يمكن إزالة الحساب المحاسبي من طريقة دفع من نوع ' . $kind,
                    400
                );
            }

            // التحقق أن السجل ينتمي لهذا الـ tenant — لا نقبل تعديل global records
            $tenantStmt = $this->db->prepare("
                SELECT id FROM payment_methods WHERE id = ? AND tenant_id = ? LIMIT 1
            ");
            $tenantStmt->execute([$id, $tenantId]);
            if (!$tenantStmt->fetchColumn()) {
                return $this->errorResponse($response, 'لم يتم العثور على طريقة الدفع أو ليست ملك هذا الحساب', 404);
            }

            // السجل خاص بالـ tenant → حدّثه مباشرة
            if ($accountId === false) {
                $stmt = $this->db->prepare("
                    UPDATE payment_methods
                    SET kind = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$kind, $id, $tenantId]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE payment_methods
                    SET kind = ?, account_id = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$kind, $accountId, $id, $tenantId]);
            }

            return $this->successResponse($response, [
                'id'         => $id,
                'kind'       => $kind,
                'account_id' => $accountId === false ? null : $accountId,
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update payment method', [
                'payment_method_id' => $args['id'] ?? null,
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء تحديث طريقة الدفع', 500);
        }
    }

    /**
     * إنشاء طريقة دفع جديدة
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $body = $request->getParsedBody() ?? [];

            $name = isset($body['name']) ? trim((string) $body['name']) : '';
            $kind = isset($body['kind']) ? strtolower(trim((string) $body['kind'])) : '';
            $description = isset($body['description']) ? trim((string) $body['description']) : null;
            $paymentTerms = isset($body['payment_terms']) ? trim((string) $body['payment_terms']) : null;
            $accountId = isset($body['account_id']) && $body['account_id'] !== ''
                ? (int) $body['account_id']
                : null;
            if ($name === '') {
                return $this->errorResponse($response, 'الاسم مطلوب', 400);
            }

            $allowed = ['cash', 'bank', 'card', 'credit', 'wallet', 'other'];
            if ($kind === '' || !in_array($kind, $allowed, true)) {
                return $this->errorResponse(
                    $response,
                    'نوع الطريقة غير صالح. القيم المسموحة: cash, bank, card, credit, wallet, other',
                    400
                );
            }

            // account_id مطلوب لكل الأنواع ماعدا credit (الذمم لا تحتاج حساب سيولة)
            $creditKinds = ['credit'];
            if (!in_array($kind, $creditKinds, true) && empty($accountId)) {
                return $this->errorResponse(
                    $response,
                    'الحساب المحاسبي (account_id) مطلوب لطريقة الدفع من نوع ' . $kind,
                    400
                );
            }

            $targetTenantId = $tenantId;

            $stmt = $this->db->prepare("
                SELECT id
                FROM payment_methods
                WHERE name = ? AND tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$name, $targetTenantId]);

            if ($stmt->fetch()) {
                return $this->errorResponse(
                    $response,
                    'طريقة دفع بنفس الاسم موجودة بالفعل في هذا النطاق',
                    409
                );
            }

            $stmt = $this->db->prepare("
                INSERT INTO payment_methods (
                    name,
                    kind,
                    description,
                    payment_terms,
                    account_id,
                    created_at,
                    updated_at,
                    tenant_id
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
            ");
            $stmt->execute([
                $name,
                $kind,
                $description,
                $paymentTerms,
                $accountId,
                $targetTenantId
            ]);

            $id = (int) $this->db->lastInsertId();

            return $this->successResponse($response, [
                'id' => $id,
                'name' => $name,
                'kind' => $kind,
                'description' => $description,
                'payment_terms' => $paymentTerms,
                'account_id' => $accountId,
                'tenant_id' => $targetTenantId
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create payment method', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'حدث خطأ أثناء إنشاء طريقة الدفع', 500);
        }
    }
}