<?php

/**
 * Rollback Script: Decrypt 2FA Secrets Back to Plaintext
 * 
 * **Purpose:**
 * Emergency rollback script to decrypt all encrypted `two_fa_secret` values
 * back to plaintext if needed (e.g., reverting the encryption feature).
 * 
 * **When to use:**
 * - ONLY if you need to rollback the encryption feature completely
 * - NOT for normal operations (encryption is the secure default)
 * 
 * **How to run:**
 * ```bash
 * php scripts/rollback_decrypt_2fa_secrets.php
 * ```
 * 
 * **Warning:**
 * This script removes encryption and stores secrets in plaintext.
 * Use only for emergency rollback scenarios.
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\TwoFactorEncryptionService;

// Load environment
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname($envPath));
    $dotenv->load();
}

// Configuration
$dryRun = true; // Set to false to actually apply changes
$batchSize = 100;

// Confirmation prompt for live mode
if (!$dryRun) {
    echo "⚠️  WARNING: This will decrypt all 2FA secrets back to PLAINTEXT\n";
    echo "   This reduces security and should only be done for rollback purposes.\n\n";
    echo "   Type 'DECRYPT' to confirm: ";
    $confirmation = trim(fgets(STDIN));
    if ($confirmation !== 'DECRYPT') {
        die("❌ Rollback cancelled\n");
    }
    echo "\n";
}

// Database connection
try {
    $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
    $dbPort = $_ENV['DB_PORT'] ?? '3306';
    $dbName = $_ENV['DB_NAME'] ?? 'inventory';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASSWORD'] ?? '';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ Connected to database: {$dbName}\n\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Initialize encryption service
try {
    $encryptionService = new TwoFactorEncryptionService();
    echo "✅ Encryption service initialized\n\n";
} catch (Exception $e) {
    die("❌ Encryption service initialization failed: " . $e->getMessage() . "\n");
}

echo ($dryRun ? "🔍 DRY RUN MODE\n" : "⚠️  LIVE MODE\n");
echo str_repeat("=", 80) . "\n\n";

try {
    $pdo->beginTransaction();

    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM users
        WHERE two_fa_secret IS NOT NULL
          AND two_fa_secret != ''
    ");
    $totalCount = (int) $stmt->fetch()['total'];

    echo "📊 Found {$totalCount} users with 2FA secrets\n\n";

    if ($totalCount === 0) {
        echo "✅ No users to rollback\n";
        $pdo->rollBack();
        exit(0);
    }

    $offset = 0;
    $decryptedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;

    while ($offset < $totalCount) {
        $stmt = $pdo->prepare("
            SELECT id, tenant_id, username, two_fa_secret
            FROM users
            WHERE two_fa_secret IS NOT NULL
              AND two_fa_secret != ''
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$batchSize, $offset]);
        $users = $stmt->fetchAll();

        foreach ($users as $user) {
            $userId = $user['id'];
            $username = $user['username'];
            $currentSecret = $user['two_fa_secret'];

            try {
                // Check if encrypted
                if (!$encryptionService->isEncrypted($currentSecret)) {
                    echo "⏭️  SKIP: User #{$userId} ({$username}) - already plaintext\n";
                    $skippedCount++;
                    continue;
                }

                // Decrypt to plaintext
                $plaintextSecret = $encryptionService->decrypt($currentSecret);

                if ($dryRun) {
                    echo "🔍 WOULD DECRYPT: User #{$userId} ({$username})\n";
                    echo "   Encrypted length: " . strlen($currentSecret) . " bytes\n";
                    echo "   Plaintext length: " . strlen($plaintextSecret) . " bytes\n";
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE users
                        SET two_fa_secret = ?
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $updateStmt->execute([$plaintextSecret, $userId, $user['tenant_id']]);

                    echo "✅ DECRYPTED: User #{$userId} ({$username})\n";
                }

                $decryptedCount++;
            } catch (Exception $e) {
                echo "❌ ERROR: User #{$userId} ({$username}) - " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }

        $offset += $batchSize;
        echo "\nProcessed {$offset}/{$totalCount} users...\n\n";
    }

    echo str_repeat("=", 80) . "\n";
    echo "📊 ROLLBACK SUMMARY:\n";
    echo "   Total users: {$totalCount}\n";
    echo "   Decrypted: {$decryptedCount}\n";
    echo "   Skipped: {$skippedCount}\n";
    echo "   Errors: {$errorCount}\n";
    echo str_repeat("=", 80) . "\n\n";

    if ($errorCount > 0) {
        $pdo->rollBack();
        exit(1);
    }

    if ($dryRun) {
        echo "🔍 DRY RUN COMPLETE - No changes applied\n";
        $pdo->rollBack();
    } else {
        $pdo->commit();
        echo "✅ ROLLBACK COMPLETE - {$decryptedCount} secrets decrypted\n";
    }

    exit(0);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ROLLBACK FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
