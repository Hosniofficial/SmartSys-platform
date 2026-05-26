<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use App\Services\MonologHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class DocumentManagementHandler extends BaseHandler
{
    private string $uploadDir;
    private int $maxFileSize;
    private array $allowedTypes;
    private array $allowedExtensions;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('document_management');
        $this->uploadDir = rtrim((string) (getenv('DOCUMENT_UPLOAD_DIR') ?: 'uploads/documents/'), '/') . '/';
        $this->maxFileSize = (int) (getenv('MAX_FILE_SIZE') ?: 10485760);
        $this->allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png'
        ];
        $this->allowedExtensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'
        ];
    }

    /**
     * رفع مستند جديد
     */
    public function uploadDocument(Request $request, Response $response): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID)', 403);
        }

        try {
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($uploadedFiles['document'])) {
                return $this->errorResponse($response, 'لم يتم رفع أي ملف', 400);
            }

            $uploadedFile = $uploadedFiles['document'];
            if (!$uploadedFile instanceof UploadedFileInterface) {
                return $this->errorResponse($response, 'ملف غير صالح', 400);
            }

            $data = $request->getParsedBody();
            if (!is_array($data)) {
                return $this->errorResponse($response, 'بيانات الطلب غير صالحة', 400);
            }

            if (
                !isset($data['name'], $data['category'], $data['user_id']) ||
                $data['name'] === '' ||
                $data['category'] === '' ||
                $data['user_id'] === ''
            ) {
                return $this->errorResponse($response, 'الحقول المطلوبة مفقودة: name, category, user_id', 400);
            }

            $validation = $this->validateUploadedFile($uploadedFile);
            if ($validation !== true) {
                return $this->errorResponse($response, $validation, 400);
            }

            $tenantDir = $this->ensureTenantDirectory((int) $tenantId);

            $originalName = (string) $uploadedFile->getClientFilename();
            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            $fileName = $this->generateStoredFileName($extension);
            $filePath = $tenantDir . $fileName;

            $this->db->beginTransaction();

            $uploadedFile->moveTo($filePath);
            @chmod($filePath, 0644);

            $fileType = (string) $uploadedFile->getClientMediaType();
            $fileSize = (int) $uploadedFile->getSize();

            $stmt = $this->db->prepare("
                INSERT INTO documents (
                    tenant_id,
                    name,
                    file_path,
                    file_type,
                    file_size,
                    category,
                    description,
                    uploaded_by,
                    version,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");

            $stmt->execute([
                (int) $tenantId,
                $data['name'],
                $filePath,
                $fileType,
                $fileSize,
                $data['category'],
                $data['description'] ?? '',
                (int) $data['user_id']
            ]);

            $documentId = (int) $this->db->lastInsertId();

            $this->logDocumentActivity($documentId, 'upload', (int) $data['user_id']);
            $this->db->commit();

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'تم رفع المستند بنجاح',
                'data' => [
                    'document_id' => $documentId,
                    'file_name' => $fileName,
                    'file_path' => $filePath
                ]
            ]);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if (!empty($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }

            $this->logger->error('uploadDocument failed', [
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            return $this->errorResponse($response, 'فشل رفع المستند', 400);
        }
    }

    /**
     * تحديث مستند موجود
     */
    public function updateDocument(int $documentId, ?array $file, array $data): bool
    {
        $tenantId = (int) ($data['tenant_id'] ?? $this->tenantId ?? 0);
        if ($tenantId <= 0) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }

        $currentDoc = $this->getDocument($documentId, $tenantId);
        if (!$currentDoc) {
            throw new Exception('المستند غير موجود أو ليس لديك صلاحية');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO document_versions (
                    document_id,
                    file_path,
                    version,
                    modified_by,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $documentId,
                $currentDoc['file_path'],
                $currentDoc['version'],
                $data['user_id']
            ]);

            $filePath = $currentDoc['file_path'];
            $fileType = $currentDoc['file_type'];
            $fileSize = $currentDoc['file_size'];
            $oldFileToDelete = null;

            if ($file) {
                $this->validateLegacyFileArray($file);

                $ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $this->allowedExtensions, true)) {
                    throw new Exception('امتداد الملف غير مسموح');
                }

                if (!in_array((string) $file['type'], $this->allowedTypes, true)) {
                    throw new Exception('نوع الملف غير مدعوم');
                }

                if ((int) $file['size'] > $this->maxFileSize) {
                    throw new Exception('حجم الملف كبير جداً');
                }

                $tenantDir = $this->ensureTenantDirectory($tenantId);
                $storedName = $this->generateStoredFileName($ext);
                $newFilePath = $tenantDir . $storedName;

                if (!move_uploaded_file($file['tmp_name'], $newFilePath)) {
                    throw new Exception('فشل في رفع الملف');
                }

                @chmod($newFilePath, 0644);

                $filePath = $newFilePath;
                $fileType = $file['type'];
                $fileSize = (int) $file['size'];
                $oldFileToDelete = $currentDoc['file_path'];
            }

            $stmt = $this->db->prepare("
                UPDATE documents
                SET
                    name = ?,
                    file_path = ?,
                    file_type = ?,
                    file_size = ?,
                    category = ?,
                    description = ?,
                    version = version + 1,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");

            $stmt->execute([
                $data['name'],
                $filePath,
                $fileType,
                $fileSize,
                $data['category'],
                $data['description'] ?? '',
                $documentId,
                $tenantId
            ]);

            $this->logDocumentActivity($documentId, 'update', (int) $data['user_id']);
            $this->db->commit();

            if ($oldFileToDelete && is_file($oldFileToDelete)) {
                @unlink($oldFileToDelete);
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->logger->error('updateDocument failed', [
                'message' => $e->getMessage(),
                'document_id' => $documentId,
                'tenant_id' => $tenantId
            ]);

            throw $e;
        }
    }

    /**
     * مشاركة مستند
     */
    public function shareDocument(int $documentId, array $data): bool
    {
        $tenantId = (int) ($data['tenant_id'] ?? $this->tenantId ?? 0);
        $doc = $this->getDocument($documentId, $tenantId);

        if (!$doc) {
            throw new Exception('المستند غير موجود أو ليس لديك صلاحية');
        }

        $stmt = $this->db->prepare("
            INSERT INTO document_shares (
                tenant_id,
                document_id,
                shared_with,
                shared_by,
                permission_level,
                expires_at,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            (int) $tenantId,
            (int) $documentId,
            (int) $data['shared_with'],
            (int) $data['user_id'],
            $data['permission_level'],
            $data['expires_at'] ?? null
        ]);

        $this->logDocumentActivity(
            $documentId,
            'share',
            (int) $data['user_id'],
            sprintf('تمت المشاركة مع المستخدم %d', (int) $data['shared_with'])
        );

        return true;
    }

    /**
     * إلغاء مشاركة مستند
     */
    public function unshareDocument(int $documentId, int $userId, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? $this->tenantId;
        $doc = $this->getDocument($documentId, $tenantId);

        if (!$doc) {
            throw new Exception('المستند غير موجود أو ليس لديك صلاحية');
        }

        $stmt = $this->db->prepare("
            DELETE FROM document_shares
            WHERE document_id = ? AND shared_with = ? AND tenant_id = ?
        ");
        $stmt->execute([$documentId, $userId, $tenantId]);

        $this->logDocumentActivity(
            $documentId,
            'unshare',
            $userId,
            sprintf('تم إلغاء المشاركة مع المستخدم %d', $userId)
        );

        return true;
    }

    /**
     * جلب تفاصيل مستند
     */
    public function getDocument(int $documentId, ?int $tenantId = null): ?array
    {
        $tenantId = $tenantId ?? $this->tenantId;
        if (!$tenantId) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                d.*,
                u.name AS uploaded_by_name
            FROM documents d
            JOIN users u
              ON u.id = d.uploaded_by
             AND (u.tenant_id = d.tenant_id OR u.tenant_id IS NULL)
            WHERE d.id = ? AND d.tenant_id = ?
        ");

        $stmt->execute([$documentId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * جلب قائمة المستندات
     */
    public function getDocuments(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $tenantId = $filters['tenant_id'] ?? $this->tenantId;
        if (!$tenantId) {
            return [];
        }

        $offset = ($page - 1) * $perPage;
        $where = ["d.tenant_id = ?"];
        $params = [(int) $tenantId];

        if (!empty($filters['category'])) {
            $where[] = "d.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(d.name LIKE ? OR d.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        $stmt = $this->db->prepare("
            SELECT
                d.*,
                u.name AS uploaded_by_name
            FROM documents d
            JOIN users u
              ON u.id = d.uploaded_by
             AND (u.tenant_id = d.tenant_id OR u.tenant_id IS NULL)
            {$whereClause}
            ORDER BY d.created_at DESC
            LIMIT ? OFFSET ?
        ");

        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, (int) $perPage, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, (int) $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * جلب إصدارات المستند
     */
    public function getDocumentVersions(int $documentId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? $this->tenantId;
        if (!$tenantId) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                v.*,
                u.name AS modified_by_name
            FROM document_versions v
            JOIN documents d
              ON d.id = v.document_id
             AND d.tenant_id = ?
            JOIN users u
              ON u.id = v.modified_by
            WHERE v.document_id = ?
            ORDER BY v.version DESC
        ");

        $stmt->execute([(int) $tenantId, $documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * استعادة إصدار سابق
     */
    public function restoreVersion(int $documentId, int $versionId, int $userId, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? $this->tenantId;
        $currentDoc = $this->getDocument($documentId, $tenantId);

        if (!$currentDoc) {
            throw new Exception('المستند غير موجود أو ليس لديك صلاحية');
        }

        $stmt = $this->db->prepare("
            SELECT v.*
            FROM document_versions v
            JOIN documents d
              ON d.id = v.document_id
             AND d.tenant_id = ?
            WHERE v.id = ? AND v.document_id = ?
        ");
        $stmt->execute([(int) $tenantId, $versionId, $documentId]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$version) {
            throw new Exception('الإصدار غير موجود');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO document_versions (
                    document_id,
                    file_path,
                    version,
                    modified_by,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $documentId,
                $currentDoc['file_path'],
                $currentDoc['version'],
                $userId
            ]);

            $stmt = $this->db->prepare("
                UPDATE documents
                SET
                    file_path = ?,
                    version = version + 1,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$version['file_path'], $documentId, (int) $tenantId]);

            $this->logDocumentActivity(
                $documentId,
                'restore',
                $userId,
                sprintf('تمت استعادة الإصدار %d', (int) $version['version'])
            );

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * حذف مستند
     */
    public function deleteDocument(int $documentId, int $userId, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? $this->tenantId;
        $document = $this->getDocument($documentId, $tenantId);

        if (!$document) {
            throw new Exception('المستند غير موجود أو ليس لديك صلاحية');
        }

        $versions = $this->getDocumentVersions($documentId, $tenantId);

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                DELETE FROM document_shares
                WHERE document_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$documentId, (int) $tenantId]);

            $stmt = $this->db->prepare("
                DELETE FROM document_versions
                WHERE document_id = ?
            ");
            $stmt->execute([$documentId]);

            $stmt = $this->db->prepare("
                DELETE FROM document_audit_log
                WHERE document_id = ?
            ");
            $stmt->execute([$documentId]);

            $stmt = $this->db->prepare("
                DELETE FROM documents
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$documentId, (int) $tenantId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        if (is_file($document['file_path'])) {
            @unlink($document['file_path']);
        }

        foreach ($versions as $version) {
            if (!empty($version['file_path']) && is_file($version['file_path'])) {
                @unlink($version['file_path']);
            }
        }

        return true;
    }

    /**
     * تسجيل نشاط المستند
     */
    private function logDocumentActivity(int $documentId, string $action, int $userId, ?string $details = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO document_audit_log (
                    document_id,
                    action,
                    user_id,
                    details,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");

            return $stmt->execute([
                $documentId,
                $action,
                $userId,
                $details
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to log document activity', [
                'message' => $e->getMessage(),
                'document_id' => $documentId,
                'action' => $action,
                'user_id' => $userId
            ]);

            return false;
        }
    }

    /**
     * التحقق من صلاحيات المستخدم للمستند
     */
    public function checkDocumentPermission(int $documentId, int $userId, string $requiredPermission): bool
    {
        $stmt = $this->db->prepare("
            SELECT
                CASE
                    WHEN d.uploaded_by = ? THEN 'owner'
                    WHEN s.permission_level IS NOT NULL THEN s.permission_level
                    ELSE NULL
                END AS permission_level
            FROM documents d
            LEFT JOIN document_shares s
              ON s.document_id = d.id
             AND s.shared_with = ?
             AND (s.expires_at IS NULL OR s.expires_at > NOW())
            WHERE d.id = ?
        ");

        $stmt->execute([$userId, $userId, $documentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['permission_level']) {
            return false;
        }

        if ($result['permission_level'] === 'owner') {
            return true;
        }

        $permissionLevels = [
            'read' => 1,
            'comment' => 2,
            'edit' => 3
        ];

        return ($permissionLevels[$result['permission_level']] ?? 0) >= ($permissionLevels[$requiredPermission] ?? PHP_INT_MAX);
    }

    /**
     * Wrapper for getDocument with validation and response handling
     */
    public function getDocumentDetails(Request $request, Response $response, array $args): Response
    {
        try {
            $documentId = (int) ($args['id'] ?? 0);
            if ($documentId <= 0) {
                return $this->errorResponse($response, 'Invalid document ID', 400);
            }

            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $document = $this->getDocument($documentId, (int) $tenantId);
            if (!$document) {
                return $this->errorResponse($response, 'Document not found', 404);
            }

            return $this->successResponse($response, $document, 200);
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve document', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في جلب المستند', 400);
        }
    }

    /**
     * Wrapper for deleteDocument with validation and response handling
     */
    public function deleteDocumentSecurely(Request $request, Response $response, array $args): Response
    {
        try {
            $documentId = (int) ($args['id'] ?? 0);
            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];

            if ($documentId <= 0) {
                return $this->errorResponse($response, 'Invalid document ID', 400);
            }

            if (!isset($data['user_id'])) {
                return $this->errorResponse($response, 'User ID is required', 400);
            }

            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $userId = (int) $data['user_id'];
            $this->deleteDocument($documentId, $userId, (int) $tenantId);

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'Document deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to delete document', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في حذف المستند', 400);
        }
    }

    /**
     * Wrapper for updateDocument with validation and response handling
     */
    public function updateDocumentSecurely(Request $request, Response $response, array $args): Response
    {
        try {
            $documentId = (int) ($args['id'] ?? 0);
            if ($documentId <= 0) {
                return $this->errorResponse($response, 'Invalid document ID', 400);
            }

            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];

            $uploadedFiles = $request->getUploadedFiles();
            $file = !empty($uploadedFiles['document']) ? $uploadedFiles['document'] : null;

            $fileArray = null;
            if ($file instanceof UploadedFileInterface) {
                $validation = $this->validateUploadedFile($file);
                if ($validation !== true) {
                    return $this->errorResponse($response, $validation, 400);
                }

                $fileArray = [
                    'name' => $file->getClientFilename(),
                    'type' => $file->getClientMediaType(),
                    'size' => $file->getSize(),
                    'tmp_name' => $file->getStream()->getMetadata('uri')
                ];
            }

            $data['tenant_id'] = (int) $tenantId;
            $this->updateDocument($documentId, $fileArray, $data);

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'Document updated successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update document', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في تحديث المستند', 400);
        }
    }

    private function validateUploadedFile(UploadedFileInterface $uploadedFile): bool|string
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return 'فشل في رفع الملف';
        }

        $fileType = (string) $uploadedFile->getClientMediaType();
        $originalName = (string) $uploadedFile->getClientFilename();
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        $blockedExt = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'cgi'];

        if (in_array($extension, $blockedExt, true)) {
            return 'امتداد الملف غير مسموح';
        }

        if (!in_array($extension, $this->allowedExtensions, true)) {
            return 'امتداد الملف غير مدعوم';
        }

        if (!in_array($fileType, $this->allowedTypes, true)) {
            return 'نوع الملف غير مدعوم';
        }

        if ((int) $uploadedFile->getSize() > $this->maxFileSize) {
            return 'حجم الملف كبير جدًا';
        }

        return true;
    }

    private function validateLegacyFileArray(array $file): void
    {
        foreach (['name', 'type', 'size', 'tmp_name'] as $key) {
            if (!array_key_exists($key, $file)) {
                throw new Exception("بيانات الملف غير مكتملة: {$key}");
            }
        }

        $blockedExt = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'cgi'];
        $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $blockedExt, true)) {
            throw new Exception('امتداد الملف غير مسموح');
        }
    }

    private function ensureTenantDirectory(int $tenantId): string
    {
        $tenantDir = $this->uploadDir . $tenantId . '/';

        if (!is_dir($tenantDir) && !mkdir($tenantDir, 0775, true) && !is_dir($tenantDir)) {
            throw new Exception('تعذر إنشاء مجلد رفع المستندات');
        }

        return $tenantDir;
    }

    private function generateStoredFileName(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
    }
}