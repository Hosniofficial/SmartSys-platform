<?php

namespace App\Services;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Listeners\SecurityEventListener;

class SecurityEventDispatcher
{
    private ContainerInterface $container;
    private array $listeners = [];
    private ?LoggerInterface $logger;
    private bool $initialized = false;

    // Event types
    public const EVENT_USER_LOGIN = 'user.login';
    public const EVENT_LOGIN_FAILED = 'login.failed';
    public const EVENT_USER_LOGOUT = 'user.logout';
    public const EVENT_PASSWORD_CHANGE = 'user.password_change';
    public const EVENT_ROLE_CHANGE = 'user.role_change';
    public const EVENT_PERMISSION_CHANGE = 'user.permission_change';
    public const EVENT_TENANT_CREATED = 'tenant.created';
    public const EVENT_TENANT_UPDATED = 'tenant.updated';
    public const EVENT_TENANT_DELETED = 'tenant.deleted';
    public const EVENT_POLICY_VIOLATION = 'security.policy_violation';
    public const EVENT_RATE_LIMIT_EXCEEDED = 'security.rate_limit_exceeded';
    public const EVENT_SUSPICIOUS_ACTIVITY = 'security.suspicious_activity';
    public const EVENT_API_KEY_CREATED = 'api_key.created';
    public const EVENT_API_KEY_REVOKED = 'api_key.revoked';
    public const EVENT_ACCOUNT_LOCKED = 'account.locked';
    public const EVENT_ACCOUNT_UNLOCKED = 'account.unlocked';
    public const EVENT_DATA_EXPORT = 'data.export';
    public const EVENT_DATA_IMPORT = 'data.import';

    public function __construct(ContainerInterface $container, ?LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * Initialize the event dispatcher with default listeners
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Register the security event listener
        $this->addListener(SecurityEventListener::class);

        $this->initialized = true;
    }

    /**
     * Add an event listener
     */
    public function addListener(string $listenerClass, ?string $eventType = null, int $priority = 0): void
    {
        $key = $eventType ?? '*';
        $this->listeners[$key][$priority][] = $listenerClass;

        // Sort listeners by priority (higher priority first)
        krsort($this->listeners[$key]);
    }

    /**
     * Dispatch an event
     */
    public function dispatch(string $eventType, array $eventData = []): void
    {
        try {
            $this->initialize();

            // Get listeners for this specific event type and wildcard listeners
            $listeners = array_merge(
                $this->getListenersForEvent($eventType),
                $this->getListenersForEvent('*')
            );

            foreach ($listeners as $listenerClass) {
                try {
                    $listener = $this->container->get($listenerClass);
                    $method = $this->getHandlerMethod($eventType);

                    if (method_exists($listener, $method)) {
                        $listener->$method(array_merge(['event_type' => $eventType], $eventData));
                    } elseif (method_exists($listener, '__invoke')) {
                        $listener->__invoke(array_merge(['event_type' => $eventType], $eventData));
                    }
                } catch (\Throwable $e) {
                    $this->logError('Error dispatching event to listener: ' . $e->getMessage(), [
                        'event_type' => $eventType,
                        'listener' => $listenerClass,
                        'exception' => $e,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->logError('Error in event dispatcher: ' . $e->getMessage(), [
                'event_type' => $eventType,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Get the handler method name for an event type
     */
    private function getHandlerMethod(string $eventType): string
    {
        // Convert event type to method name (e.g., 'user.login' -> 'onUserLogin')
        $parts = explode('.', $eventType);
        $method = 'on' . str_replace(' ', '', ucwords(str_replace('_', ' ', $parts[0])));

        if (count($parts) > 1) {
            $method .= str_replace(' ', '', ucwords(str_replace('_', ' ', $parts[1])));
        }

        return $method;
    }

    /**
     * Get all listeners for a specific event type
     */
    private function getListenersForEvent(string $eventType): array
    {
        $listeners = [];

        if (isset($this->listeners[$eventType])) {
            foreach ($this->listeners[$eventType] as $priorityListeners) {
                $listeners = array_merge($listeners, $priorityListeners);
            }
        }

        return $listeners;
    }

    /**
     * Log an error message
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * Helper methods for common event types
     */

    public function dispatchUserLogin($user, $request = null): void
    {
        $this->dispatch(self::EVENT_USER_LOGIN, [
            'user' => $user,
            'request' => $request,
        ]);
    }

    public function dispatchLoginFailed($username, $reason = 'Invalid credentials', $userId = null, $tenantId = null, $request = null): void
    {
        $this->dispatch(self::EVENT_LOGIN_FAILED, [
            'username' => $username,
            'reason' => $reason,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'request' => $request,
        ]);
    }

    public function dispatchUserLogout($user, $request = null): void
    {
        $this->dispatch(self::EVENT_USER_LOGOUT, [
            'user' => $user,
            'request' => $request,
        ]);
    }

    public function dispatchPasswordChange($userId, $targetUserId = null, $tenantId = null, $request = null): void
    {
        $this->dispatch(self::EVENT_PASSWORD_CHANGE, [
            'user_id' => $userId,
            'target_user_id' => $targetUserId ?: $userId,
            'tenant_id' => $tenantId,
            'request' => $request,
            'details' => [
                'changed_by_self' => $targetUserId === null || $userId === $targetUserId,
            ],
        ]);
    }

    public function dispatchRoleChange($userId, $targetUserId, $oldRole, $newRole, $tenantId = null, $request = null): void
    {
        $this->dispatch(self::EVENT_ROLE_CHANGE, [
            'user_id' => $userId,
            'target_user_id' => $targetUserId,
            'tenant_id' => $tenantId,
            'request' => $request,
            'details' => [
                'old_role' => $oldRole,
                'new_role' => $newRole,
            ],
        ]);
    }

    public function dispatchTenantEvent(string $action, $tenantId, $userId = null, array $details = [], $request = null): void
    {
        $this->dispatch('tenant.' . $action, [
            'action' => $action,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'details' => $details,
            'request' => $request,
        ]);
    }

    public function dispatchPolicyViolation(string $policy, string $violation, $userId = null, $tenantId = null, string $severity = 'medium', array $details = [], $request = null): void
    {
        $this->dispatch(self::EVENT_POLICY_VIOLATION, [
            'policy' => $policy,
            'violation' => $violation,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'severity' => $severity,
            'details' => $details,
            'request' => $request,
        ]);
    }
}
