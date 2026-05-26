#!/usr/bin/env php
<?php
/**
 * SMARTSYS ERP - PRODUCTION READINESS VERIFICATION
 * 
 * Comprehensive pre-deployment checklist
 * Run: php scripts/production-check.php
 */

class ProductionReadinessChecker
{
    private array $results = [];
    private int $passCount = 0;
    private int $warnCount = 0;
    private int $failCount = 0;
    
    private const STATUS_PASS = '✅ PASS';
    private const STATUS_WARN = '⚠️  WARN';
    private const STATUS_FAIL = '❌ FAIL';
    
    public function run(): int
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  SMARTSYS ERP - PRODUCTION READINESS CHECK                ║\n";
        echo "║  Date: " . date('Y-m-d H:i:s') . "                             ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
        
        // Environment Checks
        $this->checkEnvironment();
        
        // Security Checks
        $this->checkSecurity();
        
        // Database Checks
        $this->checkDatabase();
        
        // File System Checks
        $this->checkFileSystem();
        
        // Dependencies Checks
        $this->checkDependencies();
        
        // Configuration Checks
        $this->checkConfiguration();
        
        // Performance Checks
        $this->checkPerformance();
        
        // Print Summary
        $this->printSummary();
        
        return $this->failCount > 0 ? 1 : 0;
    }
    
    private function checkEnvironment(): void
    {
        echo "📋 ENVIRONMENT CHECKS\n";
        echo "─────────────────────────────────────────────────────────────\n";
        
        // PHP Version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.0.0', '>=')) {
            $this->pass("PHP Version", "v$phpVersion");
        } else {
            $this->fail("PHP Version", "v$phpVersion (requires 8.0.0+)");
        }
        
        // APP_ENV
        $appEnv = $_ENV['APP_ENV'] ?? 'not-set';
        if ($appEnv === 'production') {
            $this->pass("APP_ENV", $appEnv);
        } else {
            $this->warn("APP_ENV", "$appEnv (should be 'production')");
        }
        
        // APP_DEBUG
        $appDebug = $_ENV['APP_DEBUG'] ?? 'not-set';
        if ($appDebug === 'false' || $appDebug === false || $appDebug === '0') {
            $this->pass("APP_DEBUG", "disabled");
        } else {
            $this->fail("APP_DEBUG", "enabled (MUST be disabled in production!)");
        }
        
        echo "\n";
    }
    
    private function checkSecurity(): void
    {
        echo "🔒 SECURITY CHECKS\n";
        echo "─────────────────────────────────────────────────────────────\n";
        
        // HTTPS
        $httpsRedirect = $_ENV['HTTPS_REDIRECT'] ?? 'false';
        if ($httpsRedirect === 'true' || $httpsRedirect === true) {
            $this->pass("HTTPS Redirect", "enabled");
        } else {
            $this->fail("HTTPS Redirect", "disabled (SHOULD be enabled)");
        }
        
        // JWT Secret
        $jwtSecret = $_ENV['JWT_SECRET'] ?? null;
        if ($jwtSecret && strlen($jwtSecret) >= 32) {
            $this->pass("JWT Secret", "configured (length: " . strlen($jwtSecret) . ")");
        } else {
            $this->fail("JWT Secret", "missing or too short (needs 32+ chars)");
        }
        
        // Rate Limiting
        $rateLimitEnabled = $_ENV['RATE_LIMIT_ENABLED'] ?? 'false';
        if ($rateLimitEnabled === 'true') {
            $this->pass("Rate Limiting", "enabled");
        } else {
            $this->warn("Rate Limiting", "disabled (should be enabled)");
        }
        
        // CORS Configuration
        $corsOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;
        if ($corsOrigins) {
            $this->pass("CORS Origins", "configured");
        } else {
            $this->warn("CORS Origins", "not configured");
        }
        
        // Security Headers File
        $securityConfigPath = __DIR__ . '/../config/security.php';
        if (file_exists($securityConfigPath)) {
            $this->pass("Security Config", "exists");
        } else {
            $this->fail("Security Config", "missing");
        }
        
        echo "\n";
    }
    
    private function checkDatabase(): void
    {
        echo "🗄️  DATABASE CHECKS\n";
        echo "─────────────────────────────────────────────────────────────\n";
        
        // Database Connection
        $dbHost = $_ENV['DB_HOST'] ?? null;
        $dbUser = $_ENV['DB_USER'] ?? null;
        $dbPass = $_ENV['DB_PASS'] ?? null;
        $dbName = $_ENV['DB_NAME'] ?? null;
        
        if (!$dbHost || !$dbUser || !$dbName) {
            $this->fail("Database Config", "missing required parameters");
            echo "\n";
            return;
        }
        
        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName}";
            $pdo = new \PDO($dsn, $dbUser, $dbPass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            
            // Connection successful
            $this->pass("Database Connection", "connected to {$dbName}");
            
            // Check for required tables
            $stmt = $pdo->query("SHOW TABLES");
            $tableCount = $stmt->rowCount();
            
            if ($tableCount > 0) {
                $this->pass("Database Tables", "$tableCount tables found");
            } else {
                $this->fail("Database Tables", "no tables found!");
            }
            
        } catch (\Exception $e) {
            $this->fail("Database Connection", $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function checkFileSystem(): void
    {
        echo "📁 FILE SYSTEM CHECKS\n";
        echo "─────────────────────────────────────────────────────────────\n";
        
        $checks = [
            'logs' => __DIR__ . '/../logs',
            'config' => __DIR__ . '/../config',
            'vendor' => __DIR__ . '/../vendor',
            '.env.production' => __DIR__ . '/../.env.production',
        ];
        
        foreach ($checks as $name => $path) {
            if (file_exists($path) || is_dir($path)) {
                if (is_dir($path)) {
                    $this->pass("Directory: $name", "exists");
                } else {
                    $this->pass("File: $name", "exists");
                }
            } else {
                $this->warn("File/Directory: $name", "not found");
            }
        }
        
        // Check write permissions for logs
        $logsDir = __DIR__ . '/../logs';
        if (is_dir($logsDir) && is_writable($logsDir)) {
            $this->pass("Logs Directory", "writable");
        } else {
            $this->fail("Logs Directory", "not writable");
        }
        
        echo "\n";
    }
    
    private function checkDependencies(): void
    {
        echo "📦 DEPENDENCIES CHECKS\n";
        echo "─────────────────────────────────────────────────────────────\n";
        
        $dependencies = [
            'Slim' => 'Slim\Factory\AppFactory',
            'JWT' => 'Firebase\JWT\JWT',
            'Monolog' => 'Monolog\Logger',
            'PHP-DI' => 'DI\Container',
            'Doctrine DBAL' => 'Doctrine\DBAL\Connection',
        ];
        
        foreach ($dependencies as $name => $class) {
            if (class_exists($class)) {
                $this->pass("Package: $name", "installed");
            } else {
                $this->warn("Package: $name", "not installed");
            }
        }
        
        // Check optional recommended
        $optional = [
            'Sentry' => 'Sentry\ClientBuilder',
        ];
        
        foreach ($optional as $name => $class) {
            if (class_exists($class)) {
                $this->pass("Optional: $name", "available");
            } else {
                $this->warn("Optional: $name", "not installed (recommended for production)");
            }
        }
        
        echo "\n";
    }
    
    private function checkConfiguration(): void
    {
        echo "⚙️  CONFIGURATION CHECKS\n";
        echo "─────────────────────────────────────────────────────────────\n";
        
        // Required ENV variables
        $required = [
            'APP_ENV',
            'APP_DEBUG',
            'JWT_SECRET',
            'DB_HOST',
            'DB_USER',
            'DB_NAME',
        ];
        
        $missing = [];
        foreach ($required as $var) {
            if (!isset($_ENV[$var])) {
                $missing[] = $var;
            }
        }
        
        if (empty($missing)) {
            $this->pass("Required ENV Variables", "all present");
        } else {
            $this->fail("Required ENV Variables", "missing: " . implode(', ', $missing));
        }
        
        // Recommended ENV variables
        $recommended = [
            'SENTRY_DSN',
            'LOGS_PATH',
            'BACKUP_ENABLED',
        ];
        
        $missingRecommended = [];
        foreach ($recommended as $var) {
            if (!isset($_ENV[$var])) {
                $missingRecommended[] = $var;
            }
        }
        
        if (!empty($missingRecommended)) {
            $this->warn("Recommended ENV Variables", "not set: " . implode(', ', $missingRecommended));
        } else {
            $this->pass("Recommended ENV Variables", "all configured");
        }
        
        echo "\n";
    }
    
    private function checkPerformance(): void
    {
        echo "⚡ PERFORMANCE CHECKS\n";
        echo "─────────────────────────────────────────────────────────────\n";
        
        // Check for APC/OPCache
        if (extension_loaded('opcache')) {
            $this->pass("OPCache", "enabled");
        } else {
            $this->warn("OPCache", "disabled (enable for production)");
        }
        
        // Check for Redis
        if (extension_loaded('redis')) {
            $this->pass("Redis Extension", "available");
        } else {
            $this->warn("Redis Extension", "not available (optional for cache)");
        }
        
        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        if (intval($memoryLimit) >= 256) {
            $this->pass("Memory Limit", $memoryLimit);
        } else {
            $this->warn("Memory Limit", "$memoryLimit (recommend 256M+)");
        }
        
        // Check max execution time
        $maxExecTime = ini_get('max_execution_time');
        if (intval($maxExecTime) >= 30 || $maxExecTime === '0') {
            $this->pass("Max Execution Time", $maxExecTime === '0' ? 'unlimited' : $maxExecTime);
        } else {
            $this->warn("Max Execution Time", "$maxExecTime (recommend 30+ or unlimited)");
        }
        
        echo "\n";
    }
    
    private function pass(string $check, string $detail): void
    {
        $this->results[] = [self::STATUS_PASS, $check, $detail];
        $this->passCount++;
        echo self::STATUS_PASS . " | $check: $detail\n";
    }
    
    private function warn(string $check, string $detail): void
    {
        $this->results[] = [self::STATUS_WARN, $check, $detail];
        $this->warnCount++;
        echo self::STATUS_WARN . " | $check: $detail\n";
    }
    
    private function fail(string $check, string $detail): void
    {
        $this->results[] = [self::STATUS_FAIL, $check, $detail];
        $this->failCount++;
        echo self::STATUS_FAIL . " | $check: $detail\n";
    }
    
    private function printSummary(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║                       SUMMARY                             ║\n";
        echo "╠════════════════════════════════════════════════════════════╣\n";
        echo "║ ✅ PASS:  " . str_pad($this->passCount, 2, ' ', STR_PAD_LEFT) . "                                               ║\n";
        echo "║ ⚠️  WARN:  " . str_pad($this->warnCount, 2, ' ', STR_PAD_LEFT) . "                                               ║\n";
        echo "║ ❌ FAIL:  " . str_pad($this->failCount, 2, ' ', STR_PAD_LEFT) . "                                               ║\n";
        echo "╠════════════════════════════════════════════════════════════╣\n";
        
        if ($this->failCount === 0) {
            echo "║  🟢 PRODUCTION READINESS: ✅ APPROVED                     ║\n";
            echo "║  📊 Score: " . number_format(($this->passCount / ($this->passCount + $this->warnCount)) * 100, 0) . "%                                                ║\n";
        } else {
            echo "║  🔴 PRODUCTION READINESS: ⚠️  REQUIRES FIXES             ║\n";
            echo "║  Please address all failures before deployment           ║\n";
        }
        
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
    }
}

// Run the checker
$checker = new ProductionReadinessChecker();
exit($checker->run());
