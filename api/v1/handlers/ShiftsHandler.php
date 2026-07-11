<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;

class ShiftsHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('shifts');
    }

    /**
     * GET /shifts/current
     * إرجاع الشفت المفتوح الحالي لمستأجر/مخزن/ترمينال (إن وجد).
     */
    public function current(Request $request, Response $response): Response
    {
        $tenantId = null;
        $branchId = null;
        $terminalId = null;

        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                $this->logger->warning('Shifts current - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $queryParams = $request->getQueryParams() ?? [];
            $branchId = $queryParams['branch_id'] ?? null;
            $terminalId = $queryParams['terminal_id'] ?? null;

            $this->logger->info('Current shift request', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId
            ]);

            if (!$branchId || !$terminalId) {
                $this->logger->warning('Shifts current - missing required parameters', [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'terminal_id' => $terminalId
                ]);

                return $this->errorResponse(
                    $response,
                    'مطلوب كل من branch_id و terminal_id لاسترجاع الشفت الحالي.',
                    400
                );
            }

            $this->logger->debug('Querying current shift', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId
            ]);

            $sql = "
                SELECT *
                FROM cashier_shifts
                WHERE tenant_id = ?
                  AND branch_id = ?
                  AND terminal_id = ?
                  AND status = 'open'
                ORDER BY start_time DESC
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                (int) $tenantId,
                (int) $branchId,
                (int) $terminalId
            ]);

            $shift = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $this->logger->info('Current shift retrieved successfully', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId,
                'shift_found' => $shift !== null,
                'shift_id' => $shift['id'] ?? null
            ]);

            return $this->successResponse($response, $shift, 200);
        } catch (Exception $e) {
            $this->logger->error('Current shift retrieval failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'branch_id' => $branchId ?? 'unknown',
                'terminal_id' => $terminalId ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'فشل في استرجاع الشفت الحالي', 400);
        }
    }

    /**
     * POST /shifts/open
     * فتح شفت جديد لمستأجر/مخزن/ترمينال مع منع أكثر من شفت مفتوح لنفس التكوين.
     */
    public function open(Request $request, Response $response): Response
    {
        $tenantId = null;
        $userId = null;
        $branchId = null;
        $terminalId = null;
        $cashierId = null;
        $openingCash = 0.0;

        try {
            $tenantId = $request->getAttribute('tenant_id');
            if (!$tenantId) {
                $this->logger->warning('Shifts open - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $user = $request->getAttribute('user');
            $userId = is_array($user) ? ($user['id'] ?? null) : null;

            $data = $request->getParsedBody() ?? [];
            $branchId = $data['branch_id'] ?? null;
            $terminalId = $data['terminal_id'] ?? null;
            $cashierId = $data['cashier_id'] ?? null;
            $openingCash = isset($data['opening_cash_amount']) ? (float) $data['opening_cash_amount'] : 0.0;
            $notes = $data['notes'] ?? null;

            $this->logger->info('Shift open request', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId,
                'cashier_id' => $cashierId,
                'opening_cash' => $openingCash
            ]);

            if (!$branchId) {
                $this->logger->warning('Shifts open - missing branch ID', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId
                ]);

                return $this->errorResponse($response, 'مطلوب معرف المخزن (branch_id) لفتح الشفت.', 400);
            }

            if (!$terminalId) {
                $this->logger->warning('Shifts open - missing terminal ID', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'branch_id' => $branchId
                ]);

                return $this->errorResponse(
                    $response,
                    'مطلوب اختيار جهاز نقطة البيع (terminal_id) لفتح الشفت.',
                    400
                );
            }

            $this->logger->debug('Validating terminal', [
                'tenant_id' => $tenantId,
                'terminal_id' => $terminalId
            ]);

            $tStmt = $this->db->prepare(
                "SELECT id, tenant_id, branch_id, status
                 FROM terminals
                 WHERE id = ? AND tenant_id = ?
                 LIMIT 1"
            );
            $tStmt->execute([(int) $terminalId, (int) $tenantId]);
            $terminal = $tStmt->fetch(PDO::FETCH_ASSOC);

            if (!$terminal) {
                $this->logger->warning('Shifts open - terminal not found', [
                    'tenant_id' => $tenantId,
                    'terminal_id' => $terminalId
                ]);

                return $this->errorResponse(
                    $response,
                    'الترمينال المحدد غير موجود أو لا يتبع هذا المستأجر.',
                    400
                );
            }

            if (($terminal['status'] ?? 'inactive') !== 'active') {
                $this->logger->warning('Shifts open - terminal inactive', [
                    'tenant_id' => $tenantId,
                    'terminal_id' => $terminalId,
                    'status' => $terminal['status']
                ]);

                return $this->errorResponse(
                    $response,
                    'لا يمكن فتح شفت على جهاز نقطة بيع غير نشط.',
                    400
                );
            }

            $terminalBranchId = $terminal['branch_id'] !== null ? (int) $terminal['branch_id'] : null;
            if ($terminalBranchId !== null && (int) $branchId !== $terminalBranchId) {
                $this->logger->warning('Shifts open - branch mismatch', [
                    'tenant_id' => $tenantId,
                    'requested_branch_id' => $branchId,
                    'terminal_branch_id' => $terminalBranchId,
                    'terminal_id' => $terminalId
                ]);

                return $this->errorResponse(
                    $response,
                    'المخزن المحدد لا يطابق المخزن المرتبط بجهاز نقطة البيع.',
                    400
                );
            }

            $this->logger->debug('Checking for existing open shift', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId
            ]);

            $sql = "
                SELECT id
                FROM cashier_shifts
                WHERE tenant_id = ?
                  AND branch_id = ?
                  AND terminal_id = ?
                  AND status = 'open'
                LIMIT 1
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                (int) $tenantId,
                (int) $branchId,
                (int) $terminalId
            ]);

            if ($stmt->fetch()) {
                $this->logger->warning('Shifts open - shift already open', [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'terminal_id' => $terminalId
                ]);

                return $this->errorResponse(
                    $response,
                    'يوجد بالفعل شفت مفتوح لهذا المخزن وهذا جهاز نقطة البيع. يجب إغلاقه قبل فتح شفت جديد.',
                    403
                );
            }

            $this->logger->debug('Creating new shift', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId,
                'cashier_id' => $cashierId,
                'opening_cash' => $openingCash
            ]);

            // ✅ Begin transaction to prevent race conditions on concurrent open requests
            $this->db->beginTransaction();

            try {
                $insertSql = "
                    INSERT INTO cashier_shifts (
                        tenant_id, branch_id, terminal_id, cashier_id,
                        start_time, opening_cash_amount, status, notes,
                        created_by, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, NOW(), ?, 'open', ?, ?, NOW(), NOW()
                    )
                ";

                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([
                    (int) $tenantId,
                    (int) $branchId,
                    (int) $terminalId,
                    $cashierId !== null ? (int) $cashierId : null,
                    $openingCash,
                    $notes,
                    $userId
                ]);

                $shiftId = (int) $this->db->lastInsertId();

                $selectStmt = $this->db->prepare(
                    "SELECT *
                     FROM cashier_shifts
                     WHERE id = ? AND tenant_id = ?
                     LIMIT 1"
                );
                $selectStmt->execute([$shiftId, (int) $tenantId]);
                $shift = $selectStmt->fetch(PDO::FETCH_ASSOC);

                $this->db->commit();

                $this->logger->info('Shift opened successfully', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'shift_id' => $shiftId,
                    'branch_id' => $branchId,
                    'terminal_id' => $terminalId,
                    'cashier_id' => $cashierId,
                    'opening_cash' => $openingCash
                ]);

                return $this->successResponse($response, [
                    'message' => 'تم فتح الشفت بنجاح.',
                    'shift' => $shift
                ], 201);
            } catch (Exception $txe) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $txe;
            }
        } catch (Exception $e) {
            $this->logger->error('Shift open failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'user_id' => $userId ?? 'unknown',
                'branch_id' => $branchId ?? 'unknown',
                'terminal_id' => $terminalId ?? 'unknown'
            ]);

            return $this->errorResponse($response, 'فشل فتح الشفت', 400);
        }
    }

    /**
     * GET /shifts/{id}/sessions
     * قائمة الجلسات المرتبطة بشفت معين — مكافئ لـ SAP Shift Drill-down
     */
    public function sessions(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $shiftId = (int)($args['id'] ?? 0);

            $stmt = $this->db->prepare("
                SELECT cs.id, cs.cashier_id, cs.session_type, cs.status,
                       cs.start_time, cs.end_time, cs.opening_cash_amount,
                       cs.device_name, cs.terminal_id,
                       u.name AS cashier_name
                FROM cashier_sessions cs
                LEFT JOIN users u ON u.id = cs.cashier_id
                WHERE cs.tenant_id = ? AND cs.shift_id = ?
                ORDER BY cs.start_time ASC
            ");
            $stmt->execute([(int)$tenantId, $shiftId]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse($response, $sessions, 200);
        } catch (Exception $e) {
            $this->logger->error('Shift sessions fetch failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في جلب جلسات الشفت.', 400);
        }
    }

    /**
     * POST /shifts/close
     * إغلاق شفت مفتوح وتسجيل الرصيد الفعلي.
     * ✅ Protected by transaction to prevent race conditions
     */
    public function close(Request $request, Response $response): Response
    {
        $tenantId = null;
        $userId = null;
        $shiftId = null;

        try {
            $tenantId = $request->getAttribute('tenant_id');
            if (!$tenantId) {
                $this->logger->warning('Shifts close - missing tenant ID');
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $user = $request->getAttribute('user');
            $userId = is_array($user) ? ($user['id'] ?? null) : null;

            $data = $request->getParsedBody() ?? [];
            $shiftId = $data['shift_id'] ?? null;
            $closingCash = isset($data['closing_cash_amount']) ? (float) $data['closing_cash_amount'] : null;
            $notes = $data['notes'] ?? null;

            $this->logger->info('Shift close request', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'shift_id' => $shiftId,
                'closing_cash' => $closingCash
            ]);

            if (!$shiftId) {
                $this->logger->warning('Shifts close - missing shift ID', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId
                ]);

                return $this->errorResponse($response, 'مطلوب معرف الشفت (shift_id).', 400);
            }

            if ($closingCash === null || $closingCash < 0) {
                $this->logger->warning('Shifts close - invalid closing cash', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'shift_id' => $shiftId,
                    'closing_cash' => $closingCash
                ]);

                return $this->errorResponse(
                    $response,
                    'حقل closing_cash_amount مطلوب ويجب أن يكون رقمًا غير سالب.',
                    400
                );
            }

            // ✅ Begin transaction to prevent race conditions on concurrent close requests
            $this->db->beginTransaction();

            try {
                $this->logger->debug('Validating shift for closing', [
                    'tenant_id' => $tenantId,
                    'shift_id' => $shiftId
                ]);

                $stmt = $this->db->prepare(
                    "SELECT id
                     FROM cashier_shifts
                     WHERE id = ? AND tenant_id = ? AND status = 'open'
                     LIMIT 1"
                );
                $stmt->execute([(int) $shiftId, (int) $tenantId]);

                if (!$stmt->fetch()) {
                    $this->logger->warning('Shifts close - shift not found or not open', [
                        'tenant_id' => $tenantId,
                        'shift_id' => $shiftId
                    ]);

                    throw new Exception('لم يتم العثور على شفت مفتوح بهذا المعرف.', 403);
                }

                $this->logger->debug('Closing shift', [
                    'tenant_id' => $tenantId,
                    'shift_id' => $shiftId,
                    'closing_cash' => $closingCash,
                    'user_id' => $userId
                ]);

                // Fetch shift data before closing (need terminal_id for cascade)
                $shiftData = $this->db->prepare("SELECT terminal_id, branch_id FROM cashier_shifts WHERE id = ? AND tenant_id = ? LIMIT 1");
                $shiftData->execute([(int)$shiftId, (int)$tenantId]);
                $shiftRow = $shiftData->fetch(PDO::FETCH_ASSOC);

                $updateSql = "
                    UPDATE cashier_shifts
                    SET end_time = NOW(),
                        closing_cash_amount = ?,
                        status = 'closed',
                        notes = IFNULL(?, notes),
                        closed_by = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ";

                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([
                    $closingCash,
                    $notes,
                    $userId,
                    (int) $shiftId,
                    (int) $tenantId
                ]);

                // ✅ Cascade: close all open cashier_sessions on this terminal — aligned with SAP/Oracle shift-close behavior
                if ($shiftRow && $shiftRow['terminal_id']) {
                    $cascadeStmt = $this->db->prepare("
                        UPDATE cashier_sessions
                        SET status = 'closed',
                            end_time = NOW(),
                            closed_by = ?,
                            close_reason = 'shift_closed',
                            updated_at = NOW()
                        WHERE tenant_id = ?
                          AND terminal_id = ?
                          AND shift_id = ?
                          AND status = 'open'
                    ");
                    $cascadeStmt->execute([$userId, (int)$tenantId, (int)$shiftRow['terminal_id'], (int)$shiftId]);

                    $closedSessionCount = $cascadeStmt->rowCount();
                    $this->logger->info('Cascade: closed open sessions on shift close', [
                        'shift_id'     => $shiftId,
                        'terminal_id'  => $shiftRow['terminal_id'],
                        'sessions_closed' => $closedSessionCount,
                    ]);
                }

                $selectStmt = $this->db->prepare(
                    "SELECT *
                     FROM cashier_shifts
                     WHERE id = ? AND tenant_id = ?
                     LIMIT 1"
                );
                $selectStmt->execute([(int) $shiftId, (int) $tenantId]);
                $shift = $selectStmt->fetch(PDO::FETCH_ASSOC);

                $this->db->commit();

                $this->logger->info('Shift closed successfully', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'shift_id' => $shiftId,
                    'closing_cash' => $closingCash,
                    'opening_cash' => $shift['opening_cash_amount'] ?? 0
                ]);

                return $this->successResponse($response, [
                    'message' => 'تم إغلاق الشفت بنجاح.',
                    'shift' => $shift
                ], 200);
            } catch (Exception $txe) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $txe;
            }
        } catch (Exception $e) {
            $code = (int) $e->getCode();
            $status = $code >= 400 && $code < 600 ? $code : 500;

            $this->logger->error('Shift close failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenantId ?? 'unknown',
                'user_id' => $userId ?? 'unknown',
                'shift_id' => $shiftId ?? 'unknown',
                'code' => $code
            ]);

            return $this->errorResponse($response, 'فشل في العملية', $status);
        }
    }
}