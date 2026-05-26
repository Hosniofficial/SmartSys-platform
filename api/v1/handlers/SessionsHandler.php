<?php

namespace App\Handlers;

use PDO;
use Exception;
use Throwable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;
use App\Services\CashierSessionService;
use App\Services\SessionDeniedException;
use App\Utils\RequestHelper;
use App\Utils\PaginationHelper;

/**
 * SessionsHandler
 *
 * Thin HTTP handler for cashier session endpoints.
 * All business logic delegated to CashierSessionService.
 */
class SessionsHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('sessions');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function sessionService(): CashierSessionService
    {
        static $svc = null;
        return $svc ??= new CashierSessionService($this->db);
    }

    private function logAction(int $tenantId, string $action, array $details = []): void
    {
        try {
            $userId   = isset($details['user_id'])    ? (int)$details['user_id']    : null;
            $entityId = isset($details['session_id']) ? (int)$details['session_id'] : null;

            $details['ip']         = RequestHelper::getClientIpFromServer();
            $details['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $this->audit->logAction($action, 'cashier_sessions', $entityId, $details, $tenantId, $userId);
        } catch (Throwable $e) {
            // Ignore audit failures
        }
    }

    // =========================================================================
    // POST /sessions/open
    // =========================================================================

    public function open(Request $request, Response $response): Response
    {
        $tenantId  = null;
        $jwtUserId = null;

        try {
            $tenantId = $request->getAttribute('tenant_id');
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
            }

            $data      = $request->getParsedBody() ?? [];
            $user      = $request->getAttribute('user');
            $jwtUserId = is_array($user) ? ($user['id']      ?? null) : null;
            $jwtRoleId = is_array($user) ? ($user['role_id'] ?? null) : null;

            $result = $this->sessionService()->openSession(
                (int) $tenantId,
                $data,
                $jwtUserId ? (int)$jwtUserId : null,
                $jwtRoleId ? (int)$jwtRoleId : null
            );

            $this->logAction((int)$tenantId, 'pos_session_opened', [
                'user_id'    => $jwtUserId,
                'session_id' => $result['session_id'],
            ]);

            return $this->successResponse($response, [
                'id'                 => $result['session_id'],
                'session_type'       => $result['session_type'],
                'session_type_label' => $result['session_type_label'],
                'shift_id'           => $result['shift_id'],
                'auto_closed'        => $result['auto_closed'],
            ], 200);
        } catch (SessionDeniedException $e) {
            // سجّل الرفض مع الـ context التفصيلي من الـ Service
            $this->logAction((int)($tenantId ?? 0), $e->auditEvent, array_merge(
                $e->auditContext,
                ['user_id' => $jwtUserId ?? null]
            ));
            return $this->errorResponse($response, $e->getMessage(), $e->httpCode);
        } catch (Exception $e) {
            $this->logger->error('Session open failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل فتح الجلسة', 500);
        }
    }

    // =========================================================================
    // POST /sessions/close  OR  POST /sessions/{id}/close
    // =========================================================================

    public function close(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $data        = $request->getParsedBody() ?? [];
            $sessionId   = $args['id'] ?? $data['session_id'] ?? null;
            $closingCash = isset($data['closing_cash_amount']) ? (float)$data['closing_cash_amount'] : null;

            $user      = $request->getAttribute('user');
            $jwtUserId = is_array($user) ? ($user['id'] ?? null) : null;
            $closedBy  = $data['closed_by'] ?? $jwtUserId;

            if (!$sessionId) {
                return $this->errorResponse($response, 'مطلوب معرف الجلسة (session_id).', 400);
            }
            if ($closingCash === null || $closingCash < 0) {
                return $this->errorResponse($response, 'حقل closing_cash_amount مطلوب ويجب أن يكون رقمًا غير سالب.', 400);
            }

            $svc     = $this->sessionService();
            $summary = $svc->closeSession(
                (int)$tenantId,
                (int)$sessionId,
                $closingCash,
                $closedBy  ? (int)$closedBy  : null,
                $data['variance_reason'] ?? null
            );

            // Audit — حقول صريحة كما في الأصل (pre-close snapshot مع variance)
            try {
                $closing = $summary['closing'] ?? [];
                $this->audit->logAction(
                    'session_closed',
                    'cashier_sessions',
                    (int)$sessionId,
                    [
                        'session_id'          => (int)$sessionId,
                        'tenant_id'           => (int)$tenantId,
                        'user_id'             => $jwtUserId,
                        'closed_by'           => $closedBy,
                        'closing_cash_amount' => $closing['closing_cash_amount'] ?? $closingCash,
                        'expected_cash'       => $closing['expected_cash']       ?? null,
                        'variance'            => $closing['variance']            ?? null,
                        'variance_reason'     => $closing['variance_reason']     ?? null,
                        'closed_at'           => $closing['closed_at']           ?? null,
                        'summary'             => $summary,
                    ],
                    (int)$tenantId,
                    $jwtUserId ? (int)$jwtUserId : null
                );
            } catch (Throwable $e) {}

            return $this->successResponse($response, $summary, 200);
        } catch (SessionDeniedException $e) {
            return $this->errorResponse($response, $e->getMessage(), $e->httpCode);
        } catch (Exception $e) {
            $this->logger->error('Session close failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل إغلاق الجلسة', 500);
        }
    }

    // =========================================================================
    // GET /sessions/current
    // =========================================================================

    public function current(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $this->sessionService()->checkAndCloseInactiveAdminSessions((int)$tenantId);

            $q         = $request->getQueryParams();
            $branchId  = $q['branch_id']  ?? null;
            $jwtUser   = $request->getAttribute('user');
            $jwtUserId = is_array($jwtUser) ? ($jwtUser['id'] ?? null) : null;
            $cashierId = $q['cashier_id'] ?? $q['user_id'] ?? $jwtUserId ?? null;
            $deviceId  = $q['device_id']  ?? null;

            $baseSql = "SELECT * FROM cashier_sessions WHERE tenant_id = ? AND status = 'open'";
            $session = null;

            if ($branchId !== null && $branchId !== '' && $cashierId !== null && $cashierId !== '' && $deviceId !== null && $deviceId !== '') {
                $s = $this->db->prepare($baseSql . " AND branch_id = ? AND cashier_id = ? AND device_id = ? ORDER BY start_time DESC LIMIT 1");
                $s->execute([$tenantId, $branchId, $cashierId, $deviceId]);
                $session = $s->fetch(PDO::FETCH_ASSOC);
            }
            if (!$session && $branchId !== null && $branchId !== '' && $cashierId !== null && $cashierId !== '') {
                $s = $this->db->prepare($baseSql . " AND branch_id = ? AND cashier_id = ? ORDER BY start_time DESC LIMIT 1");
                $s->execute([$tenantId, $branchId, $cashierId]);
                $session = $s->fetch(PDO::FETCH_ASSOC);
            }
            if (!$session && $cashierId !== null && $cashierId !== '') {
                $s = $this->db->prepare($baseSql . " AND cashier_id = ? ORDER BY start_time DESC LIMIT 1");
                $s->execute([$tenantId, $cashierId]);
                $session = $s->fetch(PDO::FETCH_ASSOC);
            }
            if (!$session && $deviceId !== null && $deviceId !== '') {
                $s = $this->db->prepare($baseSql . " AND device_id = ?");
                $s->execute([$tenantId, $deviceId]);
                $all = $s->fetchAll(PDO::FETCH_ASSOC);
                if ($cashierId !== null && $cashierId !== '') {
                    foreach ($all as $ds) {
                        if ((int)$ds['cashier_id'] === (int)$cashierId) { $session = $ds; break; }
                    }
                }
            }

            if ($session) {
                $session['session_type_label'] = $this->sessionService()->getSessionTypeLabel($session['session_type'] ?? 'manual');
            }

            return $this->successResponse($response, $session ?: null, 200);
        } catch (Exception $e) {
            $this->logger->error('Get current session failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في جلب الجلسة الحالية', 500);
        }
    }

    // =========================================================================
    // GET /sessions
    // =========================================================================

    public function listSessions(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $q         = $request->getQueryParams();
            $status    = $q['status']    ?? null;
            $branchId  = $q['branch_id'] ?? null;
            $cashierId = $q['cashier_id'] ?? null;
            [$page, $limit, $offset] = PaginationHelper::fromArray($q, 20, 100);

            $selectFields = "
                cs.*,
                u.name AS cashier_name,
                u2.name AS closed_by_name,
                (
                    cs.closing_cash_amount - cs.opening_cash_amount +
                    (SELECT COALESCE(SUM(CASE WHEN type IN ('income','return_receipt','sale','deposit') THEN amount ELSE 0 END), 0)
                     FROM cash_transactions WHERE tenant_id = cs.tenant_id AND session_id = cs.id) -
                    (SELECT COALESCE(SUM(CASE WHEN type IN ('expense','return_payment','purchase','withdrawal') THEN amount ELSE 0 END), 0)
                     FROM cash_transactions WHERE tenant_id = cs.tenant_id AND session_id = cs.id)
                ) AS variance_amount
            ";
            $baseSql = "
                FROM cashier_sessions cs
                LEFT JOIN users u  ON cs.cashier_id = u.id  AND u.tenant_id  = cs.tenant_id
                LEFT JOIN users u2 ON cs.closed_by  = u2.id AND u2.tenant_id = cs.tenant_id
                WHERE cs.tenant_id = ?
            ";
            $params = [$tenantId];

            if ($status !== null && $status !== '')   { $baseSql .= " AND cs.status = ?";     $params[] = $status; }
            if ($branchId !== null && $branchId !== '') { $baseSql .= " AND cs.branch_id = ?";  $params[] = $branchId; }
            if ($cashierId !== null && $cashierId !== '') { $baseSql .= " AND cs.cashier_id = ?"; $params[] = $cashierId; }

            $stmt = $this->db->prepare("SELECT $selectFields $baseSql ORDER BY cs.start_time DESC LIMIT ? OFFSET ?");
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $svc = $this->sessionService();
            foreach ($items as &$item) {
                $item['session_type_label'] = $svc->getSessionTypeLabel($item['session_type'] ?? 'manual');
                if (empty($item['cashier_name'])) $item['cashier_name'] = 'مستخدم #' . ($item['cashier_id'] ?? '');
                if (!empty($item['closed_by']) && empty($item['closed_by_name'])) $item['closed_by_name'] = 'مستخدم #' . $item['closed_by'];
                $item['variance_amount'] = isset($item['variance_amount']) ? (float)$item['variance_amount'] : null;
            }
            unset($item);

            $countStmt = $this->db->prepare("SELECT COUNT(*) $baseSql");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            return $this->successResponse($response, [
                'items' => $items, 'total' => $total,
                'page' => $page, 'limit' => $limit,
                'total_pages' => (int)ceil($total / $limit),
            ], 200);
        } catch (Exception $e) {
            $this->logger->error('List sessions failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في جلب قائمة الجلسات', 500);
        }
    }

    // =========================================================================
    // GET /sessions/{id}/summary
    // =========================================================================

    public function summary(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId  = $request->getAttribute('tenant_id');
            $sessionId = $args['id'] ?? null;

            if (!$tenantId) return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            if (!$sessionId) return $this->errorResponse($response, 'مطلوب معرف الجلسة.', 400);

            $summary = $this->sessionService()->buildSessionSummary((int)$tenantId, (int)$sessionId);

            if (!$summary['session']) {
                return $this->errorResponse($response, 'لم يتم العثور على الجلسة.', 404);
            }

            return $this->successResponse($response, $summary, 200);
        } catch (Exception $e) {
            $this->logger->error('Session summary failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في جلب ملخص الجلسة', 500);
        }
    }

    // =========================================================================
    // GET /sessions/summary  (daily list with variance filter)
    // =========================================================================

    public function dailySummary(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $query = $request->getQueryParams();

            $varianceSql = "
                cs.opening_cash_amount
                + COALESCE((SELECT SUM(amount) FROM cash_transactions WHERE tenant_id = cs.tenant_id AND session_id = cs.id AND type IN ('income','return_receipt','sale','deposit')),0)
                - COALESCE((SELECT SUM(amount) FROM cash_transactions WHERE tenant_id = cs.tenant_id AND session_id = cs.id AND type IN ('expense','return_payment','purchase','withdrawal')),0)
            ";

            $sql = "
                SELECT cs.id, DATE(cs.start_time) AS session_date,
                       cs.start_time, cs.end_time, cs.status, cs.session_type,
                       cs.opening_cash_amount, cs.closing_cash_amount, cs.variance_reason,
                       cs.branch_id, b.name AS branch_name,
                       cs.terminal_id, t.name AS terminal_name,
                       cs.cashier_id, u.name AS cashier_name, u2.name AS closed_by_name,
                       cs.device_name,
                       ($varianceSql) AS expected_cash,
                       cs.closing_cash_amount AS actual_cash,
                       cs.closing_cash_amount - ($varianceSql) AS variance_amount
                FROM cashier_sessions cs
                LEFT JOIN branches b  ON cs.branch_id   = b.id  AND b.tenant_id  = cs.tenant_id
                LEFT JOIN terminals t ON cs.terminal_id = t.id  AND t.tenant_id  = cs.tenant_id
                LEFT JOIN users u     ON cs.cashier_id  = u.id  AND u.tenant_id  = cs.tenant_id
                LEFT JOIN users u2    ON cs.closed_by   = u2.id AND u2.tenant_id = cs.tenant_id
                WHERE cs.tenant_id = ?
            ";
            $params     = [$tenantId];
            $countSql   = "SELECT COUNT(*) FROM cashier_sessions cs WHERE cs.tenant_id = ?";
            $countParams = [$tenantId];

            $this->applyDailySummaryFilters($query, $sql, $params, $countSql, $countParams);

            [$page, $limit, $offset] = PaginationHelper::fromArray($query, 50, 100);
            $sql    .= " ORDER BY cs.start_time DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($countParams);
            $total = (int)$countStmt->fetchColumn();

            return $this->successResponse($response, [
                'items' => $sessions, 'total' => $total,
                'page' => $page, 'limit' => $limit,
                'total_pages' => (int)ceil($total / $limit),
            ], 200);
        } catch (Exception $e) {
            $this->logger->error('Daily summary failed', ['message' => $e->getMessage()]);
            return $this->errorResponse($response, 'فشل في إنشاء الملخص اليومي', 400);
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function applyDailySummaryFilters(
        array  $query,
        string &$sql,
        array  &$params,
        string &$countSql,
        array  &$countParams
    ): void {
        if (!empty($query['from_date'])) {
            $clause = " AND cs.start_time >= ?"; $val = $query['from_date'] . ' 00:00:00';
            $sql .= $clause; $params[] = $val;
            $countSql .= $clause; $countParams[] = $val;
        }
        if (!empty($query['to_date'])) {
            $nd = date('Y-m-d', strtotime($query['to_date'] . ' +1 day'));
            $clause = " AND cs.start_time < ?"; $val = $nd . ' 00:00:00';
            $sql .= $clause; $params[] = $val;
            $countSql .= $clause; $countParams[] = $val;
        }
        foreach (['branch_id' => 'cs.branch_id', 'cashier_id' => 'cs.cashier_id', 'terminal_id' => 'cs.terminal_id'] as $qKey => $col) {
            if (!empty($query[$qKey])) {
                $clause = " AND $col = ?"; $val = $query[$qKey];
                $sql .= $clause; $params[] = $val;
                $countSql .= $clause; $countParams[] = $val;
            }
        }
        if (isset($query['has_variance'])) {
            $varianceInner = "cs.closing_cash_amount - (cs.opening_cash_amount
                + COALESCE((SELECT SUM(amount) FROM cash_transactions WHERE tenant_id=cs.tenant_id AND session_id=cs.id AND type IN ('income','return_receipt','sale','deposit')),0)
                - COALESCE((SELECT SUM(amount) FROM cash_transactions WHERE tenant_id=cs.tenant_id AND session_id=cs.id AND type IN ('expense','return_payment','purchase','withdrawal')),0))";
            if ($query['has_variance'] === 'true') {
                $clause = " AND cs.closing_cash_amount IS NOT NULL AND ABS($varianceInner) > 0.01";
            } else {
                $clause = " AND (cs.closing_cash_amount IS NULL OR ABS($varianceInner) <= 0.01)";
            }
            $sql .= $clause; $countSql .= $clause;
        }
    }
}
