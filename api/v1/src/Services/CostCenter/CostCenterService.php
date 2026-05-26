<?php

namespace App\Services\CostCenter;

use PDO;
use App\Services\MonologHandler;

class CostCenterService {
    private PDO $pdo;
    private MonologHandler $logger;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->logger = MonologHandler::getInstance('cost-center');
    }

    /**
     * 🎯 توحيد resolution logic هنا
     * @throws \Exception إذا فشل الحل
     */
    public function resolve(int $tenantId, ?int $userId = null, ?int $provided = null): int {
        // 1️⃣ الأولوية 1: القيمة المُعطاة مباشرة (إذا كانت صحيحة)
        if ($provided && $provided > 0) {
            if ($this->validateExists($tenantId, $provided)) {
                $this->logger->debug('Cost center resolved from provided value', [
                    'tenant_id' => $tenantId,
                    'cost_center_id' => $provided
                ]);
                return $provided;
            }
            throw new \Exception("Cost center {$provided} غير موجود للمستأجر {$tenantId}");
        }

        // 2️⃣ الأولوية 2: من branch المستخدم
        if ($userId) {
            $ccId = $this->getFromUserBranch($tenantId, $userId);
            if ($ccId > 0) {
                $this->logger->debug('Cost center resolved from user branch', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'cost_center_id' => $ccId
                ]);
                return $ccId;
            }
        }

        // 3️⃣ الأولوية 3: من إعدادات المستأجر
        $ccId = $this->getFromSettings($tenantId);
        if ($ccId > 0) {
            $this->logger->debug('Cost center resolved from settings', [
                'tenant_id' => $tenantId,
                'cost_center_id' => $ccId
            ]);
            return $ccId;
        }

        // 4️⃣ الأولوية 4: أول cost center متاح
        $ccId = $this->getFirstAvailable($tenantId);
        if ($ccId > 0) {
            $this->logger->debug('Cost center resolved as fallback', [
                'tenant_id' => $tenantId,
                'cost_center_id' => $ccId
            ]);
            return $ccId;
        }

        // 🔴 الخطأ الحاسم: لا توجد cost centers على الإطلاق!
        $this->logger->error('No cost centers found for tenant', [
            'tenant_id' => $tenantId,
            'user_id' => $userId
        ]);
        
        throw new \Exception(
            "لا توجد مراكز تكلفة للمستأجر {$tenantId}. " .
            "يرجى إنشاء على الأقل مركز تكلفة واحد قبل المتابعة."
        );
    }

    private function validateExists(int $tenantId, int $ccId): bool {
        $stmt = $this->pdo->prepare("
            SELECT id FROM cost_centers 
            WHERE id = ? AND tenant_id = ? LIMIT 1
        ");
        $stmt->execute([$ccId, $tenantId]);
        return (bool)$stmt->fetchColumn();
    }

    private function getFromUserBranch(int $tenantId, int $userId): ?int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.cost_center_id FROM users u
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE u.id = ? AND u.tenant_id = ? LIMIT 1
            ");
            $stmt->execute([$userId, $tenantId]);
            $ccId = $stmt->fetchColumn();
            return $ccId ? (int)$ccId : null;
        } catch (\Throwable $e) {
            $this->logger->warning('Error getting cost center from user branch', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function getFromSettings(int $tenantId): ?int {
        $stmt = $this->pdo->prepare("
            SELECT value FROM settings 
            WHERE tenant_id = ? AND key_name = 'accounting.default_cost_center_id'
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $val = $stmt->fetchColumn();
        return $val ? (int)$val : null;
    }

    private function getFirstAvailable(int $tenantId): ?int {
        $stmt = $this->pdo->prepare("
            SELECT id FROM cost_centers 
            WHERE tenant_id = ? ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    /**
     * الحصول على قائمة الحسابات المطلوبة للمستأجر
     */
    public function validateRequiredAccounts(int $tenantId, array $requiredKeys): array {
        $missing = [];

        foreach ($requiredKeys as $key => $fallbackCode) {
            try {
                $this->resolve($tenantId, null, $fallbackCode);
            } catch (\Exception $e) {
                $missing[$key] = $fallbackCode;
            }
        }

        return $missing;
    }
}
