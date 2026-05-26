<?php
/**
 * run_migration.php — runs a single SQL migration file.
 *
 * Usage:
 *   php scripts/run_migration.php database/migrations/W1_seed_permissions.sql
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$migrationFile = $argv[1] ?? null;
if (!$migrationFile) {
    echo "Usage: php scripts/run_migration.php <path-to-sql-file>" . PHP_EOL;
    exit(1);
}

$fullPath = realpath(__DIR__ . '/../' . $migrationFile)
         ?: realpath($migrationFile);

if (!$fullPath || !file_exists($fullPath)) {
    echo "File not found: {$migrationFile}" . PHP_EOL;
    exit(1);
}

// Connect
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST']     ?? 'localhost',
    $_ENV['DB_PORT']     ?? '3306',
    $_ENV['DB_DATABASE'] ?? 'inventory'
);

try {
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo "DB connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Parse and execute statements
// Split on semicolons that appear at end-of-line (handles multi-line statements)
$sql      = file_get_contents($fullPath);
$executed = 0;
$skipped  = 0;
$errored  = 0;

// Remove block comments /* ... */
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

// Split on semicolons
$rawParts = explode(';', $sql);

echo PHP_EOL . "Running: " . basename($fullPath) . PHP_EOL;
echo str_repeat('-', 55) . PHP_EOL;

foreach ($rawParts as $stmt) {
    // Strip line comments
    $stmt = preg_replace('/--[^\n]*/', '', $stmt);
    $stmt = trim((string)$stmt);

    if (empty($stmt)) {
        continue;
    }

    try {
        $pdo->exec($stmt);
        $executed++;
    } catch (PDOException $e) {
        // Duplicate key from INSERT IGNORE — expected, not an error
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            $skipped++;
        } else {
            echo "  [ERR] " . $e->getMessage() . PHP_EOL;
            echo "        " . substr($stmt, 0, 100) . PHP_EOL;
            $errored++;
        }
    }
}

echo PHP_EOL;
echo "  Executed : {$executed}" . PHP_EOL;
echo "  Skipped  : {$skipped}"  . PHP_EOL;
echo "  Errors   : {$errored}"  . PHP_EOL;
echo PHP_EOL;

if ($errored === 0) {
    echo "  Migration completed successfully." . PHP_EOL;
} else {
    echo "  Migration completed with {$errored} error(s). Review above." . PHP_EOL;
}

echo PHP_EOL;
exit($errored > 0 ? 1 : 0);
