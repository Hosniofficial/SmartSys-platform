<?php

namespace App\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Monolog Handler - Professional Logging System
 * Environment-aware logging with Monolog library
 */
class MonologHandler
{
    private static $instances = [];
    private $logger;
    private $env;
    private $logLevel;

    // Monolog level mapping
    private static $levelMap = [
        'debug' => Logger::DEBUG,
        'info' => Logger::INFO,
        'warning' => Logger::WARNING,
        'error' => Logger::ERROR,
        'critical' => Logger::CRITICAL,
        'alert' => Logger::ALERT,
        'emergency' => Logger::EMERGENCY,
    ];

    public function __construct($channel = 'erp')
    {
        $this->env = getenv('APP_ENV') ?: 'production';
        $this->logLevel = getenv('LOG_LEVEL') ?: ($this->env === 'production' ? 'error' : 'debug');

        $this->logger = new Logger($channel);
        $this->setupHandlers();
    }

    /**
     * Get singleton instance for channel
     */
    public static function getInstance($channel = 'erp'): self
    {
        if (!isset(self::$instances[$channel])) {
            self::$instances[$channel] = new self($channel);
        }
        return self::$instances[$channel];
    }

    /**
     * Setup Monolog handlers based on environment
     */
    private function setupHandlers(): void
    {
        // Custom formatter with structured data
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            'Y-m-d H:i:s',
            true,
            true
        );

        if ($this->env === 'production') {
            // Production: Error log file with rotation
            $logFile = __DIR__ . '/../../logs/erp-error.log';
            $errorHandler = new RotatingFileHandler($logFile, 30, Logger::ERROR);
            $errorHandler->setFormatter($formatter);
            $this->logger->pushHandler($errorHandler);

            // Critical errors to separate file
            $criticalFile = __DIR__ . '/../../logs/erp-critical.log';
            $criticalHandler = new RotatingFileHandler($criticalFile, 30, Logger::CRITICAL);
            $criticalHandler->setFormatter($formatter);
            $this->logger->pushHandler($criticalHandler);

        } else {
            // Development: All logs to console and file
            $logFile = __DIR__ . '/../../logs/erp-debug.log';
            $debugHandler = new RotatingFileHandler($logFile, 7, Logger::DEBUG);
            $debugHandler->setFormatter($formatter);
            $this->logger->pushHandler($debugHandler);

            // Console output for development
            $consoleHandler = new StreamHandler('php://stdout', Logger::DEBUG);
            $consoleHandler->setFormatter($formatter);
            $this->logger->pushHandler($consoleHandler);
        }

        // Add context processor
        $this->logger->pushProcessor(function ($record) {
            $record['extra']['tenant_id'] = $_SERVER['HTTP_X_TENANT_ID'] ?? null;
            $record['extra']['user_id'] = $_SESSION['user_id'] ?? null;
            $record['extra']['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $record['extra']['request_uri'] = $_SERVER['REQUEST_URI'] ?? 'unknown';
            $record['extra']['method'] = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
            return $record;
        });
    }

    /**
     * Check if logging should be enabled for the given level
     */
    private function shouldLog($level)
    {
        if (!isset(self::$levelMap[$level]) || !isset(self::$levelMap[$this->logLevel])) {
            return false;
        }

        return self::$levelMap[$level] >= self::$levelMap[$this->logLevel];
    }

    /**
     * Log a message with structured context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        // Add environment info to context
        $context['env'] = $this->env;
        $context['timestamp'] = date('Y-m-d H:i:s');

        $this->logger->log(self::$levelMap[$level], $message, $context);
    }

    /**
     * Convenience methods for different log levels
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Log authentication events
     */
    public function auth(string $action, ?int $userId = null, ?int $tenantId = null, array $context = []): void
    {
        $this->info("Auth: {$action}", array_merge([
            'action' => $action,
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ], $context));
    }

    /**
     * Log API requests
     */
    public function api(string $method, string $endpoint, ?int $userId = null, array $context = []): void
    {
        $this->info("API: {$method} {$endpoint}", array_merge([
            'method' => $method,
            'endpoint' => $endpoint,
            'user_id' => $userId
        ], $context));
    }

    /**
     * Log database operations
     */
    public function db(string $operation, string $table, array $context = []): void
    {
        $this->debug("DB: {$operation} on {$table}", array_merge([
            'operation' => $operation,
            'table' => $table
        ], $context));
    }

    /**
     * Log business events
     */
    public function business(string $event, array $context = []): void
    {
        $this->info("Business: {$event}", $context);
    }

    /**
     * Log performance metrics
     */
    public function performance(string $operation, float $duration, array $context = []): void
    {
        if ($this->env !== 'production') {
            $this->debug("Performance: {$operation}", array_merge([
                'operation' => $operation,
                'duration_ms' => $duration,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ], $context));
        }
    }

    /**
     * Log security events (always logged)
     */
    public function security(string $event, array $context = []): void
    {
        $this->warning("Security: {$event}", array_merge([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => time(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ], $context));
    }

    /**
     * Get the underlying Monolog logger instance
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Create logs directory if it doesn't exist
     */
    public static function ensureLogsDirectory(): void
    {
        $logsDir = __DIR__ . '/../../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }
}

// Auto-create logs directory
MonologHandler::ensureLogsDirectory();
