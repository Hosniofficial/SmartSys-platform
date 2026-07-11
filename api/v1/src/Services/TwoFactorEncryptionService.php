<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use RuntimeException;

/**
 * TwoFactorEncryptionService
 * 
 * Provides encryption/decryption for 2FA secrets at rest using Sodium (libsodium).
 * 
 * **BACKWARD COMPATIBILITY MODE:**
 * If TWO_FA_ENCRYPTION_KEY is not set, the service operates in "plaintext mode":
 * - encrypt() returns plaintext with a warning log
 * - decrypt() assumes plaintext and returns as-is
 * - This allows gradual migration without breaking existing deployments
 * 
 * **Security Rationale:**
 * Storing 2FA TOTP secrets in plaintext means a single database breach exposes
 * all user MFA seeds, eliminating the second factor entirely across all accounts.
 * Encrypting at-rest adds defense-in-depth: the attacker must compromise BOTH
 * the database AND the encryption key (stored separately in environment).
 * 
 * **Key Management:**
 * - Encryption key MUST be 32 bytes (SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
 * - Stored in TWO_FA_ENCRYPTION_KEY environment variable
 * - Key should be base64-encoded 32-byte random value (generated via sodium_crypto_secretbox_keygen())
 * - NEVER commit the real key to version control
 * - In production: use secrets manager (AWS Secrets Manager / Vault / etc.)
 * 
 * **Algorithm:**
 * - Uses `sodium_crypto_secretbox` (XSalsa20-Poly1305 authenticated encryption)
 * - Nonce is randomly generated per encryption and prepended to ciphertext
 * - Format: base64(nonce || ciphertext)
 * 
 * **Usage:**
 * ```php
 * $service = new TwoFactorEncryptionService();
 * 
 * // Check if encryption is enabled
 * if ($service->isEncryptionEnabled()) {
 *     // Encryption active
 * }
 * 
 * $encrypted = $service->encrypt($plainSecret);  // Store this in DB
 * $decrypted = $service->decrypt($encrypted);     // Use for TOTP validation
 * ```
 * 
 * **Key Generation (one-time setup):**
 * ```bash
 * php scripts/generate_2fa_key.php
 * ```
 * 
 * **Migration:**
 * ```bash
 * # Encrypt existing plaintext secrets
 * php scripts/migrate_encrypt_2fa_secrets.php
 * ```
 */
class TwoFactorEncryptionService
{
    private string $encryptionKey;

    private bool $encryptionEnabled = false;

    /**
     * Constructor - initializes encryption if key is available
     * 
     * @param bool $requireKey If true, throws exception when key is missing (default: false for backward compatibility)
     * @throws RuntimeException if $requireKey is true and encryption key is missing or invalid
     */
    public function __construct(bool $requireKey = false)
    {
        $keyBase64 = $_ENV['TWO_FA_ENCRYPTION_KEY'] ?? null;

        if (!$keyBase64) {
            if ($requireKey) {
                throw new RuntimeException(
                    'TWO_FA_ENCRYPTION_KEY environment variable is required for 2FA encryption. ' .
                    'Generate one using: php scripts/generate_2fa_key.php'
                );
            }
            // Encryption disabled - will use plaintext mode
            $this->encryptionEnabled = false;
            return;
        }

        $key = base64_decode($keyBase64, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            if ($requireKey) {
                throw new RuntimeException(
                    'TWO_FA_ENCRYPTION_KEY must be a valid base64-encoded 32-byte key. ' .
                    'Current length: ' . ($key === false ? 'invalid base64' : strlen($key)) . ' bytes, ' .
                    'required: ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes'
                );
            }
            // Invalid key - disable encryption
            $this->encryptionEnabled = false;
            return;
        }

        $this->encryptionKey = $key;
        $this->encryptionEnabled = true;
    }

    /**
     * Check if encryption is enabled
     * 
     * @return bool True if encryption key is valid and encryption is active
     */
    public function isEncryptionEnabled(): bool
    {
        return $this->encryptionEnabled;
    }

    /**
     * Encrypt a 2FA secret for storage
     * 
     * If encryption is disabled (no key configured), returns plaintext with a warning log.
     * 
     * @param string $plainSecret The plaintext TOTP secret (e.g., from Google Authenticator)
     * @return string Base64-encoded (nonce || ciphertext) if encrypted, or plaintext if encryption disabled
     * @throws Exception if encryption fails
     */
    public function encrypt(string $plainSecret): string
    {
        if (empty($plainSecret)) {
            throw new Exception('Cannot encrypt empty 2FA secret');
        }

        // If encryption is disabled, return plaintext (backward compatibility)
        if (!$this->encryptionEnabled) {
            error_log('WARNING: 2FA encryption is disabled (TWO_FA_ENCRYPTION_KEY not set). Storing secrets in plaintext.');
            return $plainSecret;
        }

        try {
            // Generate random nonce (24 bytes for secretbox)
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            // Encrypt with authenticated encryption
            $ciphertext = sodium_crypto_secretbox($plainSecret, $nonce, $this->encryptionKey);

            // Prepend nonce to ciphertext (nonce is not secret, just must be unique per encryption)
            $encrypted = $nonce . $ciphertext;

            // Encode for safe DB storage
            return base64_encode($encrypted);
        } catch (\Throwable $e) {
            throw new Exception('2FA secret encryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decrypt a 2FA secret from storage
     * 
     * Automatically detects if value is encrypted or plaintext.
     * If encryption is disabled, assumes plaintext and returns as-is.
     * 
     * @param string $encryptedSecret Base64-encoded (nonce || ciphertext) or plaintext
     * @return string The plaintext TOTP secret
     * @throws Exception if decryption fails (tampered data, wrong key, or invalid format)
     */
    public function decrypt(string $encryptedSecret): string
    {
        if (empty($encryptedSecret)) {
            throw new Exception('Cannot decrypt empty 2FA secret');
        }

        // If encryption is disabled, assume plaintext
        if (!$this->encryptionEnabled) {
            // Check if it LOOKS encrypted (heuristic)
            if ($this->isEncrypted($encryptedSecret)) {
                throw new Exception(
                    '2FA secret appears to be encrypted but TWO_FA_ENCRYPTION_KEY is not configured. ' .
                    'Set the encryption key in .env to decrypt.'
                );
            }
            return $encryptedSecret; // Return as plaintext
        }

        // Check if it's plaintext (not encrypted yet - migration scenario)
        if (!$this->isEncrypted($encryptedSecret)) {
            error_log('WARNING: 2FA secret appears to be plaintext. Run migration: php scripts/migrate_encrypt_2fa_secrets.php');
            return $encryptedSecret; // Return as-is for backward compatibility
        }

        try {
            // Decode from base64
            $decoded = base64_decode($encryptedSecret, true);
            if ($decoded === false) {
                throw new Exception('Invalid base64 encoding in encrypted 2FA secret');
            }

            // Extract nonce (first 24 bytes)
            if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new Exception('Encrypted 2FA secret is too short (corrupted or invalid format)');
            }

            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            // Decrypt with authentication verification
            $plainSecret = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey);

            if ($plainSecret === false) {
                throw new Exception('2FA secret decryption failed (wrong key or tampered ciphertext)');
            }

            return $plainSecret;
        } catch (\Throwable $e) {
            throw new Exception('2FA secret decryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a value is already encrypted (heuristic check)
     * 
     * This is a best-effort detection to help with migration:
     * - Encrypted values are base64 and longer than typical plaintext secrets
     * - Plaintext TOTP secrets are typically uppercase alphanumeric (Base32)
     * 
     * @param string $value Value to check
     * @return bool True if likely encrypted, false if likely plaintext
     */
    public function isEncrypted(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Encrypted format: base64(24-byte nonce + ciphertext)
        // Minimum length: base64(24 + 16) = 54 chars (approximately)
        if (strlen($value) < 40) {
            return false; // Too short to be encrypted
        }

        // Check if valid base64
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        // Check if decoded length is reasonable for encrypted format
        if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 16) {
            return false;
        }

        // If all checks pass, likely encrypted
        return true;
    }

    /**
     * Destructor: attempt to clear encryption key from memory on cleanup
     * Note: Memory zeroing requires native libsodium extension
     */
    public function __destruct()
    {
        // Only attempt memory zeroing if native libsodium is available
        if ($this->encryptionEnabled && isset($this->encryptionKey) && function_exists('sodium_memzero')) {
            try {
                sodium_memzero($this->encryptionKey);
            } catch (\Throwable $e) {
                // Silently fail if polyfill doesn't support memzero
            }
        }
    }
}
