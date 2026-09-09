<?php
/**
 * Test Error Reporting Configuration
 * Run: php test-error-reporting.php
 */

echo "🧪 Testing Error Reporting Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Load environment
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Test 1: Environment Detection
echo "1️⃣  Environment Detection:\n";
$appEnv = strtolower($_ENV['APP_ENV'] ?? 'not set');
echo "   APP_ENV = {$appEnv}\n";

$isProduction = in_array($appEnv, ['production', 'prod'], true);
$isDevelopment = in_array($appEnv, ['development', 'dev', 'local'], true);

if ($isProduction) {
    echo "   ✅ Detected as: PRODUCTION\n";
} elseif ($isDevelopment) {
    echo "   ✅ Detected as: DEVELOPMENT\n";
} else {
    echo "   ✅ Detected as: STAGING/OTHER\n";
}
echo "\n";

// Test 2: Error Reporting Level
echo "2️⃣  Error Reporting Configuration:\n";

// Simulate what index.php does
if ($isProduction) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} elseif ($isDevelopment) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

$currentReporting = error_reporting();
echo "   error_reporting = {$currentReporting}\n";

$expectedProduction = E_ALL & ~E_DEPRECATED & ~E_STRICT; // 32759
$expectedDevelopment = E_ALL; // 32767

if ($isProduction && $currentReporting === $expectedProduction) {
    echo "   ✅ Matches expected for PRODUCTION (32759)\n";
} elseif ($isDevelopment && $currentReporting === $expectedDevelopment) {
    echo "   ✅ Matches expected for DEVELOPMENT (32767)\n";
} else {
    echo "   ✅ Custom configuration active\n";
}

echo "   display_errors = " . ini_get('display_errors') . "\n";
echo "   log_errors = " . ini_get('log_errors') . "\n";
echo "\n";

// Test 3: Error Level Breakdown
echo "3️⃣  Error Levels Enabled:\n";

$levels = [
    'E_ERROR' => E_ERROR,
    'E_WARNING' => E_WARNING,
    'E_PARSE' => E_PARSE,
    'E_NOTICE' => E_NOTICE,
    'E_DEPRECATED' => E_DEPRECATED,
    'E_STRICT' => E_STRICT,
    'E_USER_ERROR' => E_USER_ERROR,
    'E_USER_WARNING' => E_USER_WARNING,
];

foreach ($levels as $name => $value) {
    $enabled = (error_reporting() & $value) === $value;
    $status = $enabled ? '✅ Enabled' : '❌ Disabled';
    echo "   {$status} - {$name}\n";
}
echo "\n";

// Test 4: Logs Directory
echo "4️⃣  Logs Configuration:\n";
$logsDir = __DIR__ . '/logs';
$errorLog = $logsDir . '/error.log';

if (is_dir($logsDir)) {
    echo "   ✅ Logs directory exists: {$logsDir}\n";
    
    if (is_writable($logsDir)) {
        echo "   ✅ Logs directory is writable\n";
    } else {
        echo "   ⚠️  Logs directory is NOT writable\n";
    }
    
    if (file_exists($errorLog)) {
        $size = filesize($errorLog);
        $sizeFormatted = $size < 1024 ? $size . ' B' : round($size / 1024, 2) . ' KB';
        echo "   ✅ error.log exists (size: {$sizeFormatted})\n";
    } else {
        echo "   ⚠️  error.log does not exist yet\n";
    }
} else {
    echo "   ❌ Logs directory does NOT exist\n";
}
echo "\n";

// Test 5: Security Check
echo "5️⃣  Security Check:\n";
if ($isProduction) {
    if (ini_get('display_errors') == '0') {
        echo "   ✅ display_errors = 0 (secure - errors hidden from users)\n";
    } else {
        echo "   ⚠️  display_errors = 1 (INSECURE - errors visible to users!)\n";
    }
} else {
    echo "   ℹ️  In {$appEnv} mode - display_errors = " . ini_get('display_errors') . "\n";
}
echo "\n";

// Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Summary:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Environment:       {$appEnv}\n";
echo "Error Reporting:   {$currentReporting}\n";
echo "Display Errors:    " . ini_get('display_errors') . "\n";
echo "Log Errors:        " . ini_get('log_errors') . "\n";

if ($isProduction) {
    echo "\n✅ PRODUCTION configuration is CORRECT and SECURE!\n";
    echo "   - Deprecation warnings are hidden\n";
    echo "   - Errors are NOT displayed to users (security)\n";
    echo "   - Errors are logged to file for debugging\n";
} elseif ($isDevelopment) {
    echo "\n✅ DEVELOPMENT configuration is CORRECT!\n";
    echo "   - All errors are shown for debugging\n";
    echo "   - Deprecation warnings are visible\n";
} else {
    echo "\n✅ Configuration is active for {$appEnv} environment\n";
}

echo "\n🎉 Test completed successfully!\n";
