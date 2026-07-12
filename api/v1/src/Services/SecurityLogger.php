<?php

namespace App\Services;

use App\Repositories\SecurityEventRepository;
use PDO;
use PDOException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Utils\RequestHelper;

class SecurityLogger
{
    private SecurityEventRepository $eventRepository;
    private array $config;
    private ?LoggerInterface $logger;

    public function __construct(
        SecurityEventRepository $eventRepository,
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        $this->eventRepository = $eventRepository;
        $this->config = array_merge([
            'enabled' => true,
            'log_auth_attempts' => true,
            'log_failed_logins' => true,
            'log_sensitive_operations' => true,
        ], $config);
        $this->logger = $logger;
    }

    public function log(
        string $action,
        string $status = 'success',
        ?int $userId = null,
        ?int $tenantId = null,
        ?array $details = null,
        ?ServerRequestInterface $request = null,
        ?string $severity = null
    ): bool {
        if (!$this->config['enabled']) {
            return false;
        }

        // Skip logging if the action type is disabled
        if (($action === 'login_attempt' && !$this->config['log_auth_attempts']) ||
            (in_array($action, ['login_failed', 'login_blocked']) && !$this->config['log_failed_logins']) ||
            ($action === 'sensitive_operation' && !$this->config['log_sensitive_operations'])) {
            return false;
        }

        try {
            $ipAddress = $request ? $this->getClientIp($request) : null;
            $userAgent = $request ? $request->getHeaderLine('User-Agent') : null;

            // Determine severity if not provided
            if ($severity === null) {
                $severity = $this->determineSeverity($action, $status);
            }

            // Log the event using the repository
            $eventId = $this->eventRepository->logEvent(
                $action,
                $tenantId,
                $userId,
                $details['target_user_id'] ?? null,
                $ipAddress,
                $userAgent,
                $status,
                $severity,
                $details
            );

            return $eventId !== null;
        } catch (\Throwable $e) {
            $this->logError('Failed to log security event', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function logLoginAttempt(
        string $username,
        bool $success,
        ?int $userId = null,
        ?int $tenantId = null,
        ?ServerRequestInterface $request = null,
        ?string $reason = null
    ): bool {
        $action = $success ? 'user.login' : 'login.failed';
        $status = $success ? 'success' : 'failed';
        $severity = $success ? 'info' : 'warning';

        $details = [
            'username' => $username,
            'reason' => $reason,
            'ip_address' => $request ? $this->getClientIp($request) : null,
            'user_agent' => $request ? $request->getHeaderLine('User-Agent') : null,
        ];

        return $this->log($action, $status, $userId, $tenantId, $details, $request, $severity);
    }

    public function logLogout(
        int $userId,
        ?int $tenantId = null,
        ?ServerRequestInterface $request = null
    ): bool {
        $details = [
            'ip_address' => $request ? $this->getClientIp($request) : null,
            'user_agent' => $request ? $request->getHeaderLine('User-Agent') : null,
        ];

        return $this->log('user.logout', 'success', $userId, $tenantId, $details, $request, 'info');
    }

    public function logSensitiveOperation(
        string $operation,
        array $operationDetails = [],
        ?int $userId = null,
        ?int $tenantId = null,
        ?ServerRequestInterface $request = null,
        ?string $severity = 'warning'
    ): bool {
        $details = array_merge(
            ['operation' => $operation],
            $operationDetails,
            [
                'ip_address' => $request ? $this->getClientIp($request) : null,
                'user_agent' => $request ? $request->getHeaderLine('User-Agent') : null,
            ]
        );

        return $this->log(
            'security.' . str_replace(' ', '_', strtolower($operation)),
            'success',
            $userId,
            $tenantId,
            $details,
            $request,
            $severity
        );
    }

    /**
     * Unified Security Event Logging Method
     * Signature: logSecurityEvent(eventType, level, description, details, userId, tenantId)
     */
    public function logSecurityEvent(
        string $eventType,
        string $level = 'info',
        string $description = '',
        array $details = [],
        ?int $userId = null,
        ?int $tenantId = null,
        ?ServerRequestInterface $request = null
    ): bool {
        try {
            $ipAddress = $request ? $this->getClientIp($request) : null;
            $userAgent = $request ? $request->getHeaderLine('User-Agent') : null;

            $mergedDetails = array_merge(
                ['description' => $description],
                $details,
                [
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]
            );

            // Map generic severity levels to enum values in database
            $enumLevel = $this->mapSeverityToEnum($level);

            // Determine status from level
            $status = in_array($enumLevel, ['critical', 'error', 'high']) ? 'failed' : 'success';

            return $this->log(
                $eventType,
                $status,
                $userId,
                $tenantId,
                $mergedDetails,
                $request,
                $enumLevel
            );
        } catch (\Throwable $e) {
            $this->logError('Failed to log security event', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Legacy logSecurityEvent for backward compatibility
     * Use the new unified signature when possible
     */
    public function logSecurityEventLegacy(
        string $event,
        array $eventDetails = [],
        ?int $userId = null,
        ?int $tenantId = null,
        ?ServerRequestInterface $request = null,
        string $status = 'info',
        ?string $severity = null
    ): bool {
        $details = array_merge(
            ['event' => $event],
            $eventDetails,
            [
                'ip_address' => $request ? $this->getClientIp($request) : null,
                'user_agent' => $request ? $request->getHeaderLine('User-Agent') : null,
            ]
        );

        return $this->log(
            'security.' . str_replace(' ', '_', strtolower($event)),
            $status,
            $userId,
            $tenantId,
            $details,
            $request,
            $severity
        );
    }

    private function getClientIp(ServerRequestInterface $request): ?string
    {
        return RequestHelper::getClientIp($request);
    }

    /**
     * Log an error message if a logger is available
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * Map generic severity levels to database enum values
     * Database only accepts: low, medium, high, critical
     */
    private function mapSeverityToEnum(string $severity): string
    {
        $mapping = [
            'info' => 'low',
            'notice' => 'low',
            'low' => 'low',
            'warning' => 'medium',
            'medium' => 'medium',
            'error' => 'high',
            'high' => 'high',
            'critical' => 'critical',
            'alert' => 'critical',
            'emergency' => 'critical',
        ];

        return $mapping[strtolower($severity)] ?? 'low';
    }

    /**
     * Get the event repository instance
     */
    public function getEventRepository(): SecurityEventRepository
    {
        return $this->eventRepository;
    }

    /**
     * Determine the severity level based on event type and status
     */
    private function determineSeverity(string $eventType, string $status): string
    {
        // Default to info if we can't determine a better severity
        $severity = 'info';

        // Check for failed authentication events
        if (strpos($eventType, 'login.failed') === 0 ||
            strpos($eventType, 'auth.failed') === 0 ||
            strpos($eventType, 'auth.token_missing') === 0 ||
            strpos($eventType, 'auth.token_blacklisted') === 0) {
            $severity = 'warning';
        }

        // Check for security-related events
        if (strpos($eventType, 'security.') === 0) {
            $severity = 'warning';

            // Check for critical security events
            if (strpos($eventType, 'security.brute_force') !== false ||
                strpos($eventType, 'security.unauthorized') !== false) {
                $severity = 'critical';
            }
        }

        // Check for sensitive operations
        if (strpos($eventType, 'sensitive.') === 0) {
            $severity = 'notice';
        }

        // Adjust severity based on status
        if ($status === 'failed' || $status === 'error') {
            if ($severity === 'info') {
                $severity = 'error';
            } elseif ($severity === 'notice') {
                $severity = 'warning';
            }
        } elseif ($status === 'success' && $severity === 'warning') {
            // For successful operations that were previously marked as warnings
            $severity = 'notice';
        }

        return $severity;
    }
}
