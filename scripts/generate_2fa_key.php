<?php

/**
 * 2FA Encryption Key Generator
 * 
 * Generates a secure 32-byte encryption key for 2FA secrets.
 * 
 * Usage:
 *   php scripts/generate_2fa_key.php
 * 
 * Output:
 *   Base64-encoded key suitable for TWO_FA_ENCRYPTION_KEY environment variable
 */

declare(strict_types=1);

echo "══════════════════════════════════════════════════════════════\n";
echo " 🔐 2FA Encryption Key Generator\n";
echo "══════════════════════════════════════════════════════════════\n\n";

if (!extension_loaded('sodium')) {
    die("❌ Error: Sodium extension is not loaded.\n" .
        "   Install it with: apt-get install php-sodium (Ubuntu/Debian)\n" .
        "   Or enable in php.ini: extension=sodium\n");
}

try {
    // Generate cryptographically secure 32-byte key
    $key = sodium_crypto_secretbox_keygen();
    
    // Encode in base64 for easy storage in .env
    $keyBase64 = base64_encode($key);
    
    // Display key
    echo "✅ Generated secure 32-byte encryption key:\n\n";
    echo "TWO_FA_ENCRYPTION_KEY={$keyBase64}\n\n";
    
    // Instructions
    echo "══════════════════════════════════════════════════════════════\n";
    echo "📋 Next Steps:\n";
    echo "══════════════════════════════════════════════════════════════\n\n";
    echo "1. Copy the key above\n";
    echo "2. Add it to your .env file:\n";
    echo "   echo \"TWO_FA_ENCRYPTION_KEY={$keyBase64}\" >> .env\n\n";
    echo "3. For production, use a secrets manager instead:\n";
    echo "   - AWS Secrets Manager\n";
    echo "   - HashiCorp Vault\n";
    echo "   - Azure Key Vault\n\n";
    echo "⚠️  CRITICAL WARNINGS:\n";
    echo "   - NEVER commit this key to Git\n";
    echo "   - Use different keys for dev/staging/production\n";
    echo "   - Back up the key securely (losing it = losing all 2FA secrets)\n";
    echo "   - Store in a password manager or encrypted backup\n\n";
    echo "══════════════════════════════════════════════════════════════\n";
    echo "📚 Documentation: docs/2FA_ENCRYPTION_IMPLEMENTATION.md\n";
    echo "══════════════════════════════════════════════════════════════\n";
    
    // Security info
    echo "\n🔒 Key Properties:\n";
    echo "   Length: " . strlen($key) . " bytes (raw)\n";
    echo "   Length: " . strlen($keyBase64) . " characters (base64)\n";
    echo "   Algorithm: XSalsa20-Poly1305 (via sodium_crypto_secretbox)\n";
    echo "   Security: 256-bit encryption\n\n";
    
    // Clear key from memory
    sodium_memzero($key);
    
} catch (Exception $e) {
    echo "❌ Error generating key: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
