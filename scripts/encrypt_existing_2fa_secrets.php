<?php

/**
 * One-time migration script: encrypt existing plaintext 2FA secrets in DB.
 *
 * Usage (run once after deploying the TwoFactorEncryptionService):
 *   php scripts/encrypt_existing_2fa_secrets.php
 *
 * Safe to re-run: already-encrypted rows (prefix "enc:v1:") are skipped.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\TwoFactorEncryptionService;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// DB connection
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_PORT'] ?? '3306',
    $_ENV['DB_DATABASE'] ?? 'inventory'
);
$pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$enc = new TwoFactorEncryptionService();

// Fetch all users with a 2FA secret set
$rows = $pdo->query(
    "SELECT id, tenant_id, two_fa_secret FROM users WHERE two_fa_secret IS NOT NULL AND two_fa_secret != ''"
)->fetchAll();

$total     = count($rows);
$encrypted = 0;
$skipped   = 0;
$errors    = 0;

echo "Found $total user(s) with 2FA secret.\n";

foreach ($rows as $row) {
    $secret = $row['two_fa_secret'];

    if ($enc->isEncrypted($secret)) {
        $skipped++;
        continue;
    }

    try {
        $encryptedSecret = $enc->encrypt($secret);
        $stmt = $pdo->prepare(
            "UPDATE users SET two_fa_secret = ? WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$encryptedSecret, $row['id'], $row['tenant_id']]);
        $encrypted++;
        echo "  ✅ user id={$row['id']} — encrypted\n";
    } catch (Throwable $e) {
        $errors++;
        echo "  ❌ user id={$row['id']} — ERROR: {$e->getMessage()}\n";
    }
}

echo "\nDone. encrypted=$encrypted | skipped(already encrypted)=$skipped | errors=$errors / total=$total\n";
