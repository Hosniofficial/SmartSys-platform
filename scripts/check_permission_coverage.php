<?php

declare(strict_types=1);

/**
 * check_permission_coverage.php
 *
 * Compares the Permissions catalog (PHP constants) against the `permissions`
 * table in the database and reports any drift.
 *
 * Usage:
 *   php scripts/check_permission_coverage.php
 *
 * Exit codes:
 *   0 — perfect parity (code == DB)
 *   1 — drift detected (missing in DB or missing in code)
 *
 * Run this:
 *   - After adding a new constant to Permissions.php
 *   - After running a migration
 *   - In CI before deploying
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Security\Permissions;
use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Connect to DB
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST']     ?? 'localhost',
        $_ENV['DB_PORT']     ?? '3306',
        $_ENV['DB_DATABASE'] ?? 'inventory'
    );
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\PDOException $e) {
    echo "[ERROR] Cannot connect to database: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ── Fetch from DB ─────────────────────────────────────────────────────────────
$stmt = $pdo->query("SELECT name FROM permissions ORDER BY name");
$inDb = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ── Fetch from code ───────────────────────────────────────────────────────────
$inCode = Permissions::all();
sort($inCode);
sort($inDb);

// ── Compare ───────────────────────────────────────────────────────────────────
$missingInDb   = array_diff($inCode, $inDb);   // defined in code, not in DB
$missingInCode = array_diff($inDb, $inCode);   // in DB, not in code (orphans)

$hasDrift = !empty($missingInDb) || !empty($missingInCode);

// ── Report ────────────────────────────────────────────────────────────────────
echo PHP_EOL;
echo "╔══════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║       Permission Coverage Audit — SmartSys ERP       ║" . PHP_EOL;
echo "╚══════════════════════════════════════════════════════╝" . PHP_EOL;
echo PHP_EOL;
echo sprintf("  Code constants : %d", count($inCode)) . PHP_EOL;
echo sprintf("  DB rows        : %d", count($inDb))   . PHP_EOL;
echo PHP_EOL;

if (!$hasDrift) {
    echo "  ✅  Perfect parity — code and DB are in sync." . PHP_EOL . PHP_EOL;
    exit(0);
}

if (!empty($missingInDb)) {
    echo "  ❌  MISSING IN DB (" . count($missingInDb) . ") — run W1_seed_permissions.sql:" . PHP_EOL;
    foreach ($missingInDb as $p) {
        echo "       - {$p}" . PHP_EOL;
    }
    echo PHP_EOL;
}

if (!empty($missingInCode)) {
    echo "  ⚠️   IN DB BUT NOT IN CODE (" . count($missingInCode) . ") — orphaned rows:" . PHP_EOL;
    foreach ($missingInCode as $p) {
        echo "       - {$p}" . PHP_EOL;
    }
    echo PHP_EOL;
    echo "  These are either legacy permissions or ones added directly to DB." . PHP_EOL;
    echo "  Add them to Permissions.php if still in use, or clean them up." . PHP_EOL;
    echo PHP_EOL;
}

exit(1);
