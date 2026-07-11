<?php

/**
 * Migration Script: Encrypt Existing 2FA Secrets
 * 
 * **Purpose:**
 * Encrypts all plaintext `two_fa_secret` values in the `users` table
 * using the new TwoFactorEncryptionService.
 * 
 * **When to run:**
 * - ONE TIME after deploying the encryption feature to production
 * - BEFORE running this script, ensure TWO_FA_ENCRYPTION_KEY is set in .env
 * 
 * **How to run:**
 * ```bash
 * php scripts/migrate_encrypt_2fa_secrets.php
 * ```
 * 
 * **Safety features:**
 * - Dry-run mode by default (set $dryRun = false to apply changes)
 * - Skips already-encrypted values (idempotent)
 * - Transaction-based (rolls back on any error)
 * - Detailed logging of all changes
 * 
 * **Rollback:**
 * If you need to rollback (decrypt back to plaintext), use the companion script:
 * `scripts/rollback_decrypt_2fa_secrets.php`
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
$batchSize = 100; // Process in batches to avoid memory issues

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

// Mode indicator
echo ($dryRun ? "🔍 DRY RUN MODE - No changes will be applied\n" : "⚠️  LIVE MODE - Changes will be applied to database\n");
echo str_repeat("=", 80) . "\n\n";

try {
    // Start transaction
    $pdo->beginTransaction();

    // Count total users with 2FA secrets
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM users
        WHERE two_fa_secret IS NOT NULL
          AND two_fa_secret != ''
    ");
    $totalCount = (int) $stmt->fetch()['total'];

    echo "📊 Found {$totalCount} users with 2FA secrets\n\n";

    if ($totalCount === 0) {
        echo "✅ No users to migrate\n";
        $pdo->rollBack();
        exit(0);
    }

    // Fetch all users with 2FA secrets in batches
    $offset = 0;
    $encryptedCount = 0;
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
                // Check if already encrypted
                if ($encryptionService->isEncrypted($currentSecret)) {
                    echo "⏭️  SKIP: User #{$userId} ({$username}) - already encrypted\n";
                    $skippedCount++;
                    continue;
                }

                // Encrypt the plaintext secret
                $encryptedSecret = $encryptionService->encrypt($currentSecret);

                // Verify we can decrypt it back (sanity check)
                $decrypted = $encryptionService->decrypt($encryptedSecret);
                if ($decrypted !== $currentSecret) {
                    throw new Exception("Decryption verification failed - encrypted value doesn't decrypt to original");
                }

                if ($dryRun) {
                    echo "🔍 WOULD ENCRYPT: User #{$userId} ({$username})\n";
                    echo "   Original length: " . strlen($currentSecret) . " bytes\n";
                    echo "   Encrypted length: " . strlen($encryptedSecret) . " bytes\n";
                } else {
                    // Update database with encrypted value
                    $updateStmt = $pdo->prepare("
                        UPDATE users
                        SET two_fa_secret = ?
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $updateStmt->execute([$encryptedSecret, $userId, $user['tenant_id']]);

                    echo "✅ ENCRYPTED: User #{$userId} ({$username})\n";
                }

                $encryptedCount++;
            } catch (Exception $e) {
                echo "❌ ERROR: User #{$userId} ({$username}) - " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }

        $offset += $batchSize;
        echo "\nProcessed {$offset}/{$totalCount} users...\n\n";
    }

    // Summary
    echo str_repeat("=", 80) . "\n";
    echo "📊 MIGRATION SUMMARY:\n";
    echo "   Total users with 2FA: {$totalCount}\n";
    echo "   Encrypted: {$encryptedCount}\n";
    echo "   Skipped (already encrypted): {$skippedCount}\n";
    echo "   Errors: {$errorCount}\n";
    echo str_repeat("=", 80) . "\n\n";

    if ($errorCount > 0) {
        echo "⚠️  Migration completed with errors. Review the log above.\n";
        echo "   Transaction will be rolled back.\n";
        $pdo->rollBack();
        exit(1);
    }

    if ($dryRun) {
        echo "🔍 DRY RUN COMPLETE - No changes applied to database\n";
        echo "   To apply changes, set \$dryRun = false in the script and run again.\n";
        $pdo->rollBack();
    } else {
        $pdo->commit();
        echo "✅ MIGRATION COMPLETE - All changes committed to database\n";
        echo "   {$encryptedCount} secrets encrypted successfully\n";
    }

    exit(0);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n❌ MIGRATION FAILED: " . $e->getMessage() . "\n";
    echo "   All changes have been rolled back.\n";
    exit(1);
}
