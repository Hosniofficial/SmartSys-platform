<?php

namespace App\Services\CostCenter;

use PDO;
use App\Services\MonologHandler;

class CostCenterService
{
    private PDO $pdo;
    private MonologHandler $logger;
    private ?string $moduleTag;

    /**
     * @param string|null $moduleTag اسم الوحدة المستدعية (لأغراض الـ logging فقط، مثال: 'purchases', 'returns').
     *                                كان يُمرَّر سابقاً في بعض الاستدعاءات كمعامل ثانٍ لكنه لم يكن مُعرَّفاً
     *                                في الـ constructor فكان يُتجاهل بصمت (PHP لا ترفض argument زائد).
     */
    public function __construct(PDO $pdo, ?string $moduleTag = null)
    {
        $this->pdo = $pdo;
        $this->moduleTag = $moduleTag;
        $this->logger = MonologHandler::getInstance('cost-center');
    }

    /**
     * 🎯 المرجع الوحيد (Single Source of Truth) لحل مركز التكلفة في كامل النظام.
     * يُستخدم مباشرة من هذه الخدمة، ومن AccountingService::resolveCostCenterForService()
     * الذي أصبح الآن wrapper رقيق حول هذه الدالة (بدل تكرار نفس المنطق بخوارزمية مختلفة).
     *
     * ترتيب الأولوية:
     *   1) القيمة المُعطاة صراحةً (يجب أن تكون موجودة فعلياً وإلا تُرفض)
     *   2) فرع المستند المُحدَّد صراحةً (مثال: فرع فاتورة البيع/الشراء/المرتجع/السند)
     *   3) فرع المستخدم صاحب الجلسة
     *   4) إعداد افتراضي على مستوى tenant
     *   5) أول مركز تكلفة متاح للمستأجر
     *
     * @throws \Exception إذا فشل الحل بكل الطرق أعلاه (تُستخدم من الاستدعاءات التي يجب أن تمنع
     *                    العملية بالكامل عند غياب مراكز التكلفة). الاستدعاءات التي تفضّل عدم
     *                    منع العملية — كحال AccountingService — تلتقط هذا الاستثناء وتُرجِع null.
     */
    public function resolve(int $tenantId, ?int $userId = null, ?int $provided = null, ?int $branchId = null): int
    {
        $logCtx = ['tenant_id' => $tenantId, 'module' => $this->moduleTag ?? 'default'];

        // 1️⃣ الأولوية 1: القيمة المُعطاة مباشرة (إذا كانت صحيحة وموجودة فعلياً)
        if ($provided && $provided > 0) {
            if ($this->validateExists($tenantId, $provided)) {
                $this->logger->debug('Cost center resolved from provided value', $logCtx + [
                    'cost_center_id' => $provided
                ]);
                return $provided;
            }
            throw new \Exception("Cost center {$provided} غير موجود للمستأجر {$tenantId}");
        }

        // 2️⃣ الأولوية 2: فرع المستند المُحدَّد صراحةً (مثال: فرع فاتورة الشراء أو المرتجع)
        // ملاحظة: هذه الخطوة كانت غائبة تماماً سابقاً في هذه الخدمة رغم توفرها في
        // AccountingService::resolveCostCenterForService — ما كان يؤدي لنسب عمليات
        // المشتريات/المرتجعات لمركز تكلفة فرع المستخدم بدل فرع المستند الفعلي.
        if ($branchId) {
            $ccId = $this->getFromBranch($tenantId, $branchId);
            if ($ccId > 0) {
                $this->logger->debug('Cost center resolved from document branch', $logCtx + [
                    'branch_id' => $branchId,
                    'cost_center_id' => $ccId
                ]);
                return $ccId;
            }
        }

        // 3️⃣ الأولوية 3: من branch المستخدم
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

        // 4️⃣ الأولوية 4: من إعدادات المستأجر
        $ccId = $this->getFromSettings($tenantId);
        if ($ccId > 0) {
            $this->logger->debug('Cost center resolved from settings', $logCtx + [
                'cost_center_id' => $ccId
            ]);
            return $ccId;
        }

        // 5️⃣ الأولوية 5: أول cost center متاح
        $ccId = $this->getFirstAvailable($tenantId);
        if ($ccId > 0) {
            $this->logger->debug('Cost center resolved as fallback', $logCtx + [
                'cost_center_id' => $ccId
            ]);
            return $ccId;
        }

        // 🔴 الخطأ الحاسم: لا توجد cost centers على الإطلاق!
        $this->logger->error('No cost centers found for tenant', $logCtx + [
            'user_id' => $userId
        ]);

        throw new \Exception(
            "لا توجد مراكز تكلفة للمستأجر {$tenantId}. " .
            "يرجى إنشاء على الأقل مركز تكلفة واحد قبل المتابعة."
        );
    }

    private function validateExists(int $tenantId, int $ccId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM cost_centers 
            WHERE id = ? AND tenant_id = ? LIMIT 1
        ");
        $stmt->execute([$ccId, $tenantId]);
        return (bool)$stmt->fetchColumn();
    }

    private function getFromBranch(int $tenantId, int $branchId): ?int
    {
        try {
            // (tenant_id = ? OR tenant_id IS NULL) لدعم الفروع/الحسابات العالمية المشتركة،
            // بنفس السلوك المطابق تماماً لـ AccountingService::resolveCostCenterForService سابقاً.
            $stmt = $this->pdo->prepare("
                SELECT cost_center_id FROM branches
                WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) LIMIT 1
            ");
            $stmt->execute([$branchId, $tenantId]);
            $ccId = $stmt->fetchColumn();
            return $ccId ? (int)$ccId : null;
        } catch (\Throwable $e) {
            $this->logger->warning('Error getting cost center from document branch', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function getFromUserBranch(int $tenantId, int $userId): ?int
    {
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

    private function getFromSettings(int $tenantId): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT value FROM settings 
            WHERE tenant_id = ? AND key_name = 'accounting.default_cost_center_id'
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $val = $stmt->fetchColumn();
        return $val ? (int)$val : null;
    }

    private function getFirstAvailable(int $tenantId): ?int
    {
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
    public function validateRequiredAccounts(int $tenantId, array $requiredKeys): array
    {
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
