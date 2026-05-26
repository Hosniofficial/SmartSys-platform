<?php
/**
 * generate_secrets.php — Secure secret generator for SmartSys ERP
 *
 * Usage:
 *   php scripts/generate_secrets.php
 *
 * Prints 3 freshly generated 256-bit secrets (64 hex chars each) to stdout.
 * Copy the values into your .env file. NEVER commit the output anywhere.
 *
 * Uses PHP's CSPRNG (random_bytes) — suitable for cryptographic use.
 */

declare(strict_types=1);

function makeSecret(): string {
    return bin2hex(random_bytes(32));
}

echo "# Generated " . date('c') . PHP_EOL;
echo "# Copy these lines into your .env — do not share or commit." . PHP_EOL;
echo PHP_EOL;
echo 'JWT_SECRET=' . makeSecret() . PHP_EOL;
echo 'JWT_REFRESH_SECRET=' . makeSecret() . PHP_EOL;
echo 'CRON_SECRET=' . makeSecret() . PHP_EOL;
