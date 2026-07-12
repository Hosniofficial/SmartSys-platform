<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;
use App\Utils\RequestHelper;
use App\Utils\PaginationHelper;

/**
 * Audit Trail Handler
 *
 * Provides comprehensive audit trail functionality:
 * - Event logging and tracking
 * - Security event monitoring
 * - User activity logging
 * - System event recording
 * - Audit report generation
 */
class AuditTrailHandler extends BaseHandler
{
    private array $config;

    public function __construct(PDO $db, array $config = [])
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('audit_trail');
        $this->config = array_merge([
            'max_log_entries' => 1000000,
            'cleanup_days' => 365,
            'enable_compression' => true,
            'enable_indexing' => true,
            'log_level' => 'INFO'
        ], $config);
    }

    /**
     * Log security event using BaseHandler unified method
     */
    public function logSecurityEvent(string $eventType, string $severity, string $description, array $context = []): void
    {
        try {
            parent::logSecurityEvent($eventType, $severity, $description, $context);

            if (strtolower($severity) === 'critical') {
                $this->handleCriticalEvent($eventType, $context);
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to log security event', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log user activity
     */
    public function logUserActivity(int $userId, int $tenantId, string $action, array $details = []): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_logs (
                    user_id,
                    tenant_id,
                    action,
                    details,
                    ip_address,
                    user_agent,
                    timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $tenantId,
                $action,
                json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $details['ip'] ?? $this->getClientIp(),
                $details['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null)
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to log user activity', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log system event — delegates to AuditHandler (single source of truth)
     */
    public function logSystemEvent(string $eventType, string $level, string $message, array $context = [], ?int $tenantId = null): void
    {
        try {
            $action = match($level) {
                'CRITICAL' => 'system_critical_event',
                'ERROR'    => 'system_error_event',
                'WARNING'  => 'system_warning_event',
                default    => 'system_info_event'
            };

            $this->audit->logAction(
                $action,
                'system_event',
                null,
                [
                    'event_type' => $eventType,
                    'level'      => $level,
                    'message'    => $message,
                    'context'    => $context,
                ],
                $tenantId ?? 0,
                null
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to log system event', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get security events with filtering and pagination
     */
    public function getSecurityEvents(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->requireTenantId($request, $response);
            if ($tenantId === null) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $params = $request->getQueryParams();

            // Build filtered query using helper
            $filtered = $this->buildFilteredQuery($tenantId, [
                'event_type' => 'se.event_type',
                'severity' => 'se.event_severity',
                'user_id' => 'se.user_id',
                'date_from' => ['se.occurred_at', '>='],
                'date_to' => ['se.occurred_at', '<=']
            ], $params);

            // Count total matching records
            $countSql = "
                SELECT COUNT(*)
                FROM security_events se
                WHERE {$filtered['where']}
            ";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($filtered['bindings']);
            $total = (int) $stmt->fetchColumn();

            // Get paginated results
            [$page, $perPage, $offset] = PaginationHelper::fromArray($params, 20, 100);

            $sql = "
                SELECT 
                    se.*,
                    u.username,
                    u.email,
                    t.name AS tenant_name
                FROM security_events se
                LEFT JOIN users u ON se.user_id = u.id
                LEFT JOIN tenants t ON se.tenant_id = t.id
                WHERE {$filtered['where']}
                ORDER BY se.occurred_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $bindings = array_merge($filtered['bindings'], [$perPage, $offset]);

            foreach ($bindings as $i => $binding) {
                $paramType = is_int($binding) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($i + 1, $binding, $paramType);
            }

            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse($response, [
                'events' => array_map(static fn ($e) => [
                    'id' => (int) $e['id'],
                    'event_type' => $e['event_type'],
                    'severity' => $e['event_severity'],
                    'description' => $e['description'],
                    'user_id' => $e['user_id'] ? (int) $e['user_id'] : null,
                    'username' => $e['username'],
                    'ip_address' => $e['ip_address'],
                    'status' => $e['status'],
                    'occurred_at' => $e['occurred_at']
                ], $events),
                'pagination' => PaginationHelper::buildMeta($total, $page, $perPage),
            ]);
        } catch (Exception $e) {
            $this->logger->error('getSecurityEvents error', [
                'message' => $e->getMessage()
            ]);
            return $this->errorResponse($response, 'فشل في جلب سجلات الأمان.', 500);
        }
    }

    /**
     * Get user activity logs
     */
    public function getUserActivityLogs(Request $request, Response $response): Response
    {
        try {
            $currentTenantId = $this->extractTenantId($request);
            if (!$currentTenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $params = $request->getQueryParams();
            $filters = $this->parseFilters($params);

            $sql = "
                SELECT ual.*, u.username, u.email
                FROM user_activity_logs ual
                JOIN users u ON ual.user_id = u.id
                WHERE ual.tenant_id = ?
            ";

            $bindings = [$currentTenantId];

            if (!empty($filters['user_id'])) {
                $sql .= " AND ual.user_id = ?";
                $bindings[] = (int) $filters['user_id'];
            }

            if (!empty($filters['action'])) {
                $sql .= " AND ual.action = ?";
                $bindings[] = $filters['action'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND ual.timestamp >= ?";
                $bindings[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND ual.timestamp <= ?";
                $bindings[] = $filters['date_to'];
            }

            $sql .= " ORDER BY ual.timestamp DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);

            $i = 1;
            foreach ($bindings as $binding) {
                $stmt->bindValue($i++, $binding);
            }
            $stmt->bindValue($i++, $filters['limit'], PDO::PARAM_INT);
            $stmt->bindValue($i, $filters['offset'], PDO::PARAM_INT);
            $stmt->execute();

            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return parent::successResponse($response, [
                'logs' => $logs,
                'limit' => $filters['limit'],
                'offset' => $filters['offset']
            ], 200);
        } catch (Exception $e) {
            return parent::errorResponse(
                $response,
                'Failed to retrieve user activity logs: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get system event logs
     */
    public function getSystemEventLogs(Request $request, Response $response): Response
    {
        try {
            $currentTenantId = $this->extractTenantId($request);
            if (!$currentTenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $params = $request->getQueryParams();
            $filters = $this->parseFilters($params);

            $sql = "
                SELECT
                    id,
                    user_id,
                    action,
                    entity,
                    entity_id,
                    details,
                    tenant_id,
                    created_at AS timestamp
                FROM audit_log
                WHERE tenant_id = ?
                  AND action LIKE 'system_%'
            ";

            $bindings = [$currentTenantId];

            if (!empty($filters['event_type'])) {
                $sql .= " AND event_type = ?";
                $bindings[] = $filters['event_type'];
            }

            if (!empty($filters['level'])) {
                $sql .= " AND level = ?";
                $bindings[] = $filters['level'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND timestamp >= ?";
                $bindings[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND timestamp <= ?";
                $bindings[] = $filters['date_to'];
            }

            $sql .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);

            $i = 1;
            foreach ($bindings as $binding) {
                $stmt->bindValue($i++, $binding);
            }
            $stmt->bindValue($i++, $filters['limit'], PDO::PARAM_INT);
            $stmt->bindValue($i, $filters['offset'], PDO::PARAM_INT);
            $stmt->execute();

            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return parent::successResponse($response, [
                'logs' => $logs,
                'limit' => $filters['limit'],
                'offset' => $filters['offset']
            ], 200);
        } catch (Exception $e) {
            return parent::errorResponse(
                $response,
                'Failed to retrieve system event logs: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Generate audit report
     */
    public function generateAuditReport(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            $userId = $this->extractUserId($request);

            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];

            $reportType = $data['report_type'] ?? 'security';
            $dateFrom = $data['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $data['date_to'] ?? date('Y-m-d');
            $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];

            switch ($reportType) {
                case 'security':
                    $report = $this->generateSecurityReport((int) $tenantId, $dateFrom, $dateTo, $filters);
                    break;

                case 'user_activity':
                    $report = $this->generateUserActivityReport((int) $tenantId, $dateFrom, $dateTo, $filters);
                    break;

                case 'system_events':
                    $report = $this->generateSystemEventsReport($dateFrom, $dateTo, $filters, (int) $tenantId);
                    break;

                case 'comprehensive':
                    $report = $this->generateComprehensiveReport((int) $tenantId, $dateFrom, $dateTo, $filters);
                    break;

                default:
                    return $this->errorResponse($response, 'Invalid report type', 400);
            }

            if ($userId) {
                $this->logUserActivity(
                    $userId,
                    (int) $tenantId,
                    'audit_report_generated',
                    [
                        'report_type' => $reportType,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'filters' => $filters
                    ]
                );
            }

            return parent::successResponse($response, $report, 200);
        } catch (Exception $e) {
            return parent::errorResponse(
                $response,
                'Failed to generate audit report: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Generate security report
     */
    private function generateSecurityReport(int $tenantId, string $dateFrom, string $dateTo, array $filters): array
    {
        $sql = "
            SELECT
                event_type,
                event_severity,
                COUNT(*) AS count,
                COUNT(DISTINCT ip_address) AS unique_ips,
                COUNT(DISTINCT user_id) AS unique_users
            FROM security_events
            WHERE tenant_id = ?
              AND occurred_at BETWEEN ? AND ?
        ";

        $bindings = [$tenantId, $dateFrom, $dateTo];

        if (!empty($filters['event_types']) && is_array($filters['event_types'])) {
            $placeholders = implode(',', array_fill(0, count($filters['event_types']), '?'));
            $sql .= " AND event_type IN ($placeholders)";
            $bindings = array_merge($bindings, $filters['event_types']);
        }

        $sql .= " GROUP BY event_type, event_severity ORDER BY count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $trendSql = "
            SELECT
                DATE(occurred_at) AS date,
                COUNT(*) AS events,
                SUM(CASE WHEN LOWER(event_severity) = 'critical' THEN 1 ELSE 0 END) AS critical,
                SUM(CASE WHEN LOWER(event_severity) = 'high' THEN 1 ELSE 0 END) AS high,
                SUM(CASE WHEN LOWER(event_severity) = 'medium' THEN 1 ELSE 0 END) AS medium,
                SUM(CASE WHEN LOWER(event_severity) = 'low' THEN 1 ELSE 0 END) AS low
            FROM security_events
            WHERE tenant_id = ?
              AND occurred_at BETWEEN ? AND ?
            GROUP BY DATE(occurred_at)
            ORDER BY date
        ";

        $trendStmt = $this->db->prepare($trendSql);
        $trendStmt->execute([$tenantId, $dateFrom, $dateTo]);
        $trends = $trendStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'report_type' => 'security',
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => $summary,
            'trends' => $trends,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate user activity report
     */
    private function generateUserActivityReport(int $tenantId, string $dateFrom, string $dateTo, array $filters): array
    {
        $sql = "
            SELECT
                action,
                COUNT(*) AS count,
                COUNT(DISTINCT user_id) AS unique_users,
                COUNT(DISTINCT tenant_id) AS unique_tenants
            FROM user_activity_logs
            WHERE tenant_id = ?
              AND timestamp BETWEEN ? AND ?
        ";

        $bindings = [$tenantId, $dateFrom, $dateTo];

        if (!empty($filters['actions']) && is_array($filters['actions'])) {
            $placeholders = implode(',', array_fill(0, count($filters['actions']), '?'));
            $sql .= " AND action IN ($placeholders)";
            $bindings = array_merge($bindings, $filters['actions']);
        }

        $sql .= " GROUP BY action ORDER BY count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'report_type' => 'user_activity',
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => $summary,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate system events report with multi-tenant isolation
     * Queries audit_log table and extracts system event details from JSON
     */
    private function generateSystemEventsReport(string $dateFrom, string $dateTo, array $filters, int $tenantId): array
    {
        $sql = "
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(details, '$.event_type')) AS event_type,
                JSON_UNQUOTE(JSON_EXTRACT(details, '$.level')) AS level,
                COUNT(*) AS count
            FROM audit_log
            WHERE tenant_id = ?
              AND action LIKE 'system_%'
              AND created_at BETWEEN ? AND ?
        ";

        $bindings = [$tenantId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

        if (!empty($filters['event_types']) && is_array($filters['event_types'])) {
            $placeholders = implode(',', array_fill(0, count($filters['event_types']), '?'));
            $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.event_type')) IN ($placeholders)";
            $bindings = array_merge($bindings, $filters['event_types']);
        }

        $sql .= " GROUP BY event_type, level ORDER BY count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'report_type' => 'system_events',
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => $summary,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate comprehensive report
     */
    private function generateComprehensiveReport(int $tenantId, string $dateFrom, string $dateTo, array $filters): array
    {
        return [
            'report_type' => 'comprehensive',
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'security' => $this->generateSecurityReport($tenantId, $dateFrom, $dateTo, $filters),
            'user_activity' => $this->generateUserActivityReport($tenantId, $dateFrom, $dateTo, $filters),
            'system_events' => $this->generateSystemEventsReport($dateFrom, $dateTo, $filters, $tenantId),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Handle critical security events
     */
    private function handleCriticalEvent(string $eventType, array $context): void
    {
        switch ($eventType) {
            case 'multiple_failed_logins':
                if (!empty($context['ip'])) {
                    $this->blockSuspiciousIP((string) $context['ip']);
                }
                break;

            case 'suspicious_activity_detected':
                $this->notifySecurityTeam($eventType, $context);
                break;

            case 'data_breach_attempt':
                $this->triggerEmergencyResponse($context);
                break;
        }
    }

    /**
     * Block suspicious IP
     */
    private function blockSuspiciousIP(string $ip): void
    {
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO blocked_ips (ip_address, reason, blocked_until, is_permanent)
                VALUES (?, 'Critical security event', DATE_ADD(NOW(), INTERVAL 24 HOUR), 0)
                ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$ip]);
        } catch (Exception $e) {
            $this->logger->error('Failed to block IP', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify security team
     */
    private function notifySecurityTeam(string $eventType, array $context): void
    {
        $this->logger->critical('SECURITY ALERT', [
            'event_type' => $eventType,
            'context' => $context
        ]);
    }

    /**
     * Trigger emergency response
     */
    private function triggerEmergencyResponse(array $context): void
    {
        $this->logger->critical('EMERGENCY: Data breach attempt detected', [
            'context' => $context
        ]);
    }

    /**
     * Parse filters from query parameters
     */
    private function parseFilters(array $params): array
    {
        $limit = (int) ($params['limit'] ?? 100);
        $offset = (int) ($params['offset'] ?? 0);

        return [
            'event_type' => $params['event_type'] ?? null,
            'severity' => $params['severity'] ?? null,
            'level' => $params['level'] ?? null,
            'user_id' => isset($params['user_id']) ? (int) $params['user_id'] : null,
            'tenant_id' => isset($params['tenant_id']) ? (int) $params['tenant_id'] : null,
            'action' => $params['action'] ?? null,
            'date_from' => $params['date_from'] ?? null,
            'date_to' => $params['date_to'] ?? null,
            'limit' => max(1, min(500, $limit)),
            'offset' => max(0, $offset)
        ];
    }

    /**
     * Get client IP address — delegates to RequestHelper (single source of truth).
     * Falls back to $_SERVER when no Request object is available.
     */
    private function getClientIp(): string
    {
        return RequestHelper::getClientIpFromServer();
    }

    /**
     * Cleanup old logs
     */
    public function cleanup(): void
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$this->config['cleanup_days']} days"));

            $stmt = $this->db->prepare("DELETE FROM security_events WHERE occurred_at < ?");
            $stmt->execute([$cutoffDate]);

            $stmt = $this->db->prepare("DELETE FROM user_activity_logs WHERE timestamp < ?");
            $stmt->execute([$cutoffDate]);

            // Cleanup system event logs from audit_log (marked with action LIKE 'system_%')
            $stmt = $this->db->prepare("DELETE FROM audit_log WHERE action LIKE 'system_%' AND created_at < ?");
            $stmt->execute([$cutoffDate]);
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup audit logs', [
                'message' => $e->getMessage()
            ]);
        }
    }
}
