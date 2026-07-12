<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;

class JournalEntriesHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('journal_entries');
    }

    // GET: قائمة القيود مع الفلترة
    public function list(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $params = $request->getQueryParams();
            $where = 'WHERE je.tenant_id = :tenant_id';
            $binds = [':tenant_id' => (int) $tenantId];

            if (!empty($params['date_from'])) {
                $where .= ' AND je.entry_date >= :date_from';
                $binds[':date_from'] = $params['date_from'] . ' 00:00:00';
            }

            if (!empty($params['date_to'])) {
                $nextDay = date('Y-m-d', strtotime($params['date_to'] . ' +1 day'));
                $where .= ' AND je.entry_date < :date_to';
                $binds[':date_to'] = $nextDay . ' 00:00:00';
            }

            if (!empty($params['reference'])) {
                $where .= ' AND je.reference LIKE :reference';
                $binds[':reference'] = '%' . $params['reference'] . '%';
            }

            $sql = "
                SELECT je.*, u.name AS created_by_name
                FROM journal_entries je
                LEFT JOIN users u ON je.created_by = u.id
                {$where}
                ORDER BY je.entry_date DESC, je.id DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($binds);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => '',
                'data' => ['items' => $items]
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('JournalEntriesHandler::list failed', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'فشل في جلب القيود المحاسبية', 500);
        }
    }

    // POST: إضافة قيد جديد مع خطوطه
    public function create(Request $request, Response $response): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $data = $request->getParsedBody() ?? [];

        if (!$data || empty($data['lines']) || !is_array($data['lines'])) {
            return $this->errorResponse($response, 'مطلوب إدخال أسطر القيد المحاسبي', 400);
        }

        if (empty($data['idempotency_key'])) {
            return $this->errorResponse($response, 'مطلوب حقل idempotency_key لحماية من التكرار', 400);
        }

        try {
            $this->applyDefaultCostCenter($data, $request);
        } catch (\Throwable $e) {
            $this->logger->warning('applyDefaultCostCenter failed in JournalEntriesHandler::create', [
                'message' => $e->getMessage()
            ]);
        }

        try {
            $lines = [];
            foreach ($data['lines'] as $line) {
                $lines[] = [
                    'account_id' => (int) ($line['account_id'] ?? 0),
                    'debit' => (float) ($line['debit_amount'] ?? $line['debit'] ?? 0),
                    'credit' => (float) ($line['credit_amount'] ?? $line['credit'] ?? 0),
                    'description' => $line['description'] ?? $line['notes'] ?? null
                ];
            }

            $entryDate = $data['entry_date'] ?? date('Y-m-d');

            $jeId = $this->accounting->postJournalEntry(
                (int) $tenantId,
                'manual',
                null,
                $data['reference'] ?? $data['notes'] ?? 'قيد يدوي',
                $lines,
                $entryDate,
                $this->extractUserId($request) ?? 1,
                (int) ($data['cost_center_id'] ?? 0) ?: null,
                $data['idempotency_key']
            );

            if (!$jeId) {
                return $this->errorResponse(
                    $response,
                    'فشل في إنشاء القيد المحاسبي. تحقق من توازن الخانات (Debit = Credit)',
                    400
                );
            }

            $data['id'] = (int) $jeId;

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'تم إنشاء القيد المحاسبي بنجاح',
                'data' => $data
            ], 201);
        } catch (\Throwable $e) {
            $this->logger->error('JournalEntriesHandler::create failed', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse($response, 'فشل في إنشاء القيد المحاسبي. تحقق من البيانات المدخلة.', 400);
        }
    }

    // GET: تفاصيل قيد محدد
    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $id = isset($args['id']) ? (int) $args['id'] : 0;
            if ($id <= 0) {
                return $this->errorResponse($response, 'مطلوب رقم القيد المحاسبي', 400);
            }

            $sql = "
                SELECT je.*, u.name AS created_by_name
                FROM journal_entries je
                LEFT JOIN users u ON je.created_by = u.id
                WHERE je.id = :id AND je.tenant_id = :tenant_id
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':tenant_id' => (int) $tenantId
            ]);

            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                return $this->errorResponse($response, 'لم يتم العثور على القيد المحاسبي', 404);
            }

            $linesStmt = $this->db->prepare("
                SELECT *
                FROM journal_entry_lines
                WHERE journal_entry_id = :id
            ");
            $linesStmt->execute([':id' => $id]);
            $item['lines'] = $linesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => '',
                'data' => $item
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('JournalEntriesHandler::get failed', [
                'message' => $e->getMessage(),
                'journal_entry_id' => $args['id'] ?? null
            ]);

            return $this->errorResponse($response, 'فشل في جلب تفاصيل القيد المحاسبي', 500);
        }
    }

    // DELETE: حذف قيد
    public function delete(Request $request, Response $response, array $args): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            return $this->errorResponse($response, 'مطلوب رقم القيد المحاسبي', 400);
        }

        try {
            // Use single source of truth: AccountingService::deleteJournalEntry()
            $success = $this->accounting->deleteJournalEntry((int) $tenantId, $id);

            if (!$success) {
                throw new \Exception('Failed to delete journal entry using AccountingService');
            }

            return $this->jsonResponse($response, [
                'status' => 'success',
                'message' => 'تم حذف القيد المحاسبي بنجاح'
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('JournalEntriesHandler::delete failed', [
                'tenant_id' => $tenantId,
                'journal_entry_id' => $id,
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'فشل في حذف القيد المحاسبي. تأكد من وجود القيد وحاول مرة أخرى.', 400);
        }
    }

    // POST /journal-entries/{id}/reverse
    public function reverse(Request $request, Response $response, array $args): Response
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
        }

        $jeId   = (int) ($args['id'] ?? 0);
        $userId = $this->extractUserId($request);

        if (!$jeId) {
            return $this->errorResponse($response, 'معرف القيد المحاسبي مطلوب', 400);
        }

        try {
            $reversalJeId = $this->accounting->reverseJournalEntry($jeId, (int) $tenantId, $userId);

            return $this->successResponse($response, [
                'original_journal_entry_id' => $jeId,
                'reversal_journal_entry_id' => $reversalJeId,
                'message'                   => 'تم عكس القيد المحاسبي بنجاح',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('JournalEntriesHandler::reverse failed', [
                'tenant_id'        => $tenantId,
                'journal_entry_id' => $jeId,
                'message'          => $e->getMessage(),
            ]);
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }
}
