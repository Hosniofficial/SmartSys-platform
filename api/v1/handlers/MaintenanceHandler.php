<?php

namespace App\Handlers;

use PDO;
use Exception;
use DateTime;
use App\Services\MonologHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class MaintenanceHandler extends BaseHandler {

    public function __construct(PDO $db) {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('maintenance');
    }

    /**
     * جدولة صيانة جديدة
     */
    public function scheduleMaintenance(Request $request, Response $response): Response {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $data = $request->getParsedBody();
            if (!is_array($data)) {
                return $this->errorResponse($response, 'Invalid request data', 400);
            }
            
            // التحقق من البيانات المطلوبة
            $requiredFields = ['asset_id', 'type', 'frequency', 'next_date', 'description', 'assigned_to'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return $this->errorResponse($response, "الحقل '{$field}' مطلوب"
                    , 400);
                }
            }
            
            // إنشاء جدول الصيانة
            $scheduleId = $this->createMaintenanceSchedule($data, $request);
            
            // إعداد الاستجابة
            return $this->successResponse(
                $response,
                [
                    'message' => 'تم إنشاء جدول الصيانة بنجاح',
                    'data' => [
                        'schedule_id' => $scheduleId
                    ]
                ],
                200
            );

        } catch (\Throwable $e) {
            $this->logger->error('Failed to create maintenance schedule', [
                'message' => $e->getMessage()
            ]);
            return $this->errorResponse($response, 'فشل في إنشاء جدول الصيانة', 400);
        }
    }

    /**
     * إنشاء جدول صيانة
     */
    private function createMaintenanceSchedule($data, Request $request) {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }
        $stmt = $this->db->prepare("
            INSERT INTO maintenance_schedules (
                tenant_id,
                asset_id,
                type,
                frequency,
                next_date,
                description,
                assigned_to,
                created_by,
                created_at,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
        ");

        // Get user_id from JWT attribute (safe, verified by middleware)
        $userId = $request->getAttribute('user_id') ?? $data['created_by'] ?? null;
        
        $stmt->execute([
            $tenantId,
            $data['asset_id'],
            $data['type'],
            $data['frequency'],
            $data['next_date'],
            $data['description'],
            $data['assigned_to'],
            $userId
        ]);

        $scheduleId = $this->db->lastInsertId();

        // إضافة سجل تدقيق
        $this->audit->logAction(
            'maintenance_scheduled',
            'maintenance_schedules',
            $scheduleId,
            $data
        );

        return $scheduleId;
    }

    /**
     * تحديث جدول صيانة
     */
    public function updateMaintenanceSchedule($scheduleId, $request) {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }
        $data = $request->getParsedBody();
        
        $stmt = $this->db->prepare("
            UPDATE maintenance_schedules
            SET 
                type = ?,
                frequency = ?,
                next_date = ?,
                description = ?,
                assigned_to = ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");

        return $stmt->execute([
            $data['type'],
            $data['frequency'],
            $data['next_date'],
            $data['description'],
            $data['assigned_to'],
            $scheduleId,
            $tenantId
        ]);
    }

    /**
     * تسجيل عملية صيانة
     */
    public function logMaintenance($request) {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }
        $data = $request->getParsedBody();
        
        $this->db->beginTransaction();

        try {
            // تسجيل الصيانة
            $stmt = $this->db->prepare("
                INSERT INTO maintenance_logs (
                    tenant_id,
                    schedule_id,
                    asset_id,
                    maintenance_date,
                    type,
                    description,
                    cost,
                    performed_by,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $tenantId,
                $data['schedule_id'],
                $data['asset_id'],
                $data['maintenance_date'],
                $data['type'],
                $data['description'],
                $data['cost'],
                $data['performed_by'],
                $data['status']
            ]);

            $logId = $this->db->lastInsertId();

            // تحديث موعد الصيانة القادم
            if ($data['schedule_id']) {
                $schedule = $this->getMaintenanceSchedule($data['schedule_id']);
                $nextDate = $this->calculateNextMaintenanceDate(
                    $data['maintenance_date'],
                    $schedule['frequency']
                );

                $stmt = $this->db->prepare("
                    UPDATE maintenance_schedules
                    SET next_date = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$nextDate, $data['schedule_id'], $tenantId]);
            }

            $this->db->commit();
            return $logId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * إنشاء مطالبة ضمان
     */
    public function createWarrantyClaim($request) {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }
        $data = $request->getParsedBody();
        
        $stmt = $this->db->prepare("
            INSERT INTO warranty_claims (
                tenant_id,
                asset_id,
                claim_date,
                description,
                status,
                submitted_by,
                warranty_provider,
                claim_number,
                created_at
            ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $tenantId,
            $data['asset_id'],
            $data['claim_date'],
            $data['description'],
            $data['submitted_by'],
            $data['warranty_provider'],
            $this->generateClaimNumber()
        ]);

        $claimId = $this->db->lastInsertId();

        // إرفاق المستندات
        if (!empty($data['documents'])) {
            foreach ($data['documents'] as $document) {
                $this->attachClaimDocument($claimId, $document);
            }
        }

        return $claimId;
    }

    /**
     * تحديث حالة مطالبة الضمان
     */
    public function updateWarrantyClaim($claimId, $request) {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }
        $data = $request->getParsedBody();
        
        $stmt = $this->db->prepare("
            UPDATE warranty_claims
            SET 
                status = ?,
                resolution = ?,
                resolution_date = ?,
                cost = ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");

        $success = $stmt->execute([
            $data['status'],
            $data['resolution'],
            $data['resolution_date'],
            $data['cost'],
            $claimId,
            $tenantId
        ]);

        if ($success) {
            $this->logger->info('Warranty claim resolved', [
                'claim_id' => $claimId,
                'tenant_id' => $tenantId,
                'new_status' => $data['status']
            ]);
        }

        return $success;
    }

   
    private function attachClaimDocument($claimId, $document) {
        $stmt = $this->db->prepare("
            INSERT INTO warranty_claim_documents (
                claim_id,
                document_type,
                file_path,
                uploaded_by,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $claimId,
            $document['type'],
            $document['path'],
            $document['uploaded_by']
        ]);
    }

    /**
     * جلب جداول الصيانة
     */
    public function getMaintenanceSchedules($filters = [], $page = 1, $perPage = 20, $tenantId = null) {
        if (!$tenantId) {
            return [];
        }

        $offset = ($page - 1) * $perPage;
        $where = ["s.tenant_id = ?"];
        $params = [$tenantId];

        if (!empty($filters['asset_id'])) {
            $where[] = "s.asset_id = ?";
            $params[] = $filters['asset_id'];
        }

        if (!empty($filters['type'])) {
            $where[] = "s.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = "s.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->db->prepare("
            SELECT 
                s.*,
                a.name as asset_name,
                u.name as assigned_to_name
            FROM maintenance_schedules s
            JOIN assets a ON a.id = s.asset_id
            JOIN users u ON u.id = s.assigned_to
            WHERE {$whereClause}
            ORDER BY s.next_date ASC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * جلب سجلات الصيانة
     */
    public function getMaintenanceLogs($filters = [], $page = 1, $perPage = 20, $tenantId = null) {
        if (!$tenantId) {
            return [];
        }

        $offset = ($page - 1) * $perPage;
        $where = ["l.tenant_id = ?"];
        $params = [$tenantId];

        if (!empty($filters['asset_id'])) {
            $where[] = "l.asset_id = ?";
            $params[] = $filters['asset_id'];
        }

        if (!empty($filters['type'])) {
            $where[] = "l.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $where[] = "l.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "l.maintenance_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "l.maintenance_date <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->db->prepare("
            SELECT 
                l.*,
                a.name as asset_name,
                u.name as performed_by_name
            FROM maintenance_logs l
            JOIN assets a ON a.id = l.asset_id
            JOIN users u ON u.id = l.performed_by
            WHERE {$whereClause}
            ORDER BY l.maintenance_date DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * جلب مطالبات الضمان
     */
    public function getWarrantyClaims($filters = [], $page = 1, $perPage = 20, $tenantId = null) {
        if (!$tenantId) {
            return [];
        }

        $offset = ($page - 1) * $perPage;
        $where = ["w.tenant_id = ?"];
        $params = [$tenantId];

        if (!empty($filters['asset_id'])) {
            $where[] = "w.asset_id = ?";
            $params[] = $filters['asset_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "w.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "w.claim_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "w.claim_date <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(" AND ", $where);

        $stmt = $this->db->prepare("
            SELECT 
                w.*,
                a.name as asset_name,
                u.name as submitted_by_name
            FROM warranty_claims w
            JOIN assets a ON a.id = w.asset_id
            JOIN users u ON u.id = w.submitted_by
            WHERE {$whereClause}
            ORDER BY w.claim_date DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * جلب تفاصيل جدول صيانة
     */
    public function getMaintenanceSchedule($scheduleId, $tenantId = null) {
        if (!$tenantId) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT 
                s.*,
                a.name as asset_name,
                u.name as assigned_to_name
            FROM maintenance_schedules s
            JOIN assets a ON a.id = s.asset_id
            JOIN users u ON u.id = s.assigned_to
            WHERE s.id = ? AND s.tenant_id = ?
        ");

        $stmt->execute([$scheduleId, $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * جلب تفاصيل مطالبة ضمان
     */
    public function getWarrantyClaim($claimId, $tenantId = null) {
        if (!$tenantId) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT 
                w.*,
                a.name as asset_name,
                u.name as submitted_by_name
            FROM warranty_claims w
            JOIN assets a ON a.id = w.asset_id
            JOIN users u ON u.id = w.submitted_by
            WHERE w.id = ? AND w.tenant_id = ?
        ");

        $stmt->execute([$claimId, $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * حساب موعد الصيانة القادم
     */
    private function calculateNextMaintenanceDate($currentDate, $frequency) {
        $date = new DateTime($currentDate);
        
        switch ($frequency) {
            case 'daily':
                $date->modify('+1 day');
                break;
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'monthly':
                $date->modify('+1 month');
                break;
            case 'quarterly':
                $date->modify('+3 months');
                break;
            case 'semi_annual':
                $date->modify('+6 months');
                break;
            case 'annual':
                $date->modify('+1 year');
                break;
        }

        return $date->format('Y-m-d');
    }

    private function generateClaimNumber() {
        return 'WC-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * الحصول على اسم الأصل
     */
    private function getAssetName($assetId, $tenantId = null) {
        if (!$tenantId) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT name FROM assets WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$assetId, $tenantId]);
        return $stmt->fetchColumn();
    }
} 
