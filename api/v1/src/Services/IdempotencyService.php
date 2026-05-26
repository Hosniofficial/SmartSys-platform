<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * IdempotencyService — prevents double-submission of payment requests.
 *
 * How it works:
 *   1. Client sends `Idempotency-Key: <uuid>` header with every payment POST.
 *   2. Before processing, the handler calls `check()`.
 *      - If the key is new → proceed, then call `store()` with the result.
 *      - If the key exists and is not expired → return the cached response.
 *   3. Keys expire after 24 hours (configurable).
 *
 * Usage in a handler:
 *
 *   $idem = new IdempotencyService($this->db);
 *   $key  = $request->getHeaderLine('Idempotency-Key');
 *
 *   if ($key !== '') {
 *       $cached = $idem->check($tenantId, $key);
 *       if ($cached !== null) {
 *           // Return cached response — same result, no side effects
 *           return $this->jsonResponse($response, $cached, 200);
 *       }
 *   }
 *
 *   // ... process payment ...
 *   $result = [...];
 *
 *   if ($key !== '') {
 *       $idem->store($tenantId, $key, $paymentId, $result);
 *   }
 *
 *   return $this->successResponse($response, $result, 201);
 */
class IdempotencyService
{
    private PDO $db;

    /** TTL in seconds (default: 24 hours) */
    private int $ttl;

    public function __construct(PDO $db, int $ttl = 86400)
    {
        $this->db  = $db;
        $this->ttl = $ttl;
    }

    /**
     * Check if a key has already been processed.
     *
     * @return array|null  The cached response data, or null if key is new/expired.
     */
    public function check(int $tenantId, string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT response_json
                FROM   payment_idempotency_keys
                WHERE  tenant_id       = ?
                  AND  idempotency_key = ?
                  AND  expires_at      > NOW()
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['response_json'])) {
                return null;
            }

            $decoded = json_decode((string) $row['response_json'], true);
            return is_array($decoded) ? $decoded : null;

        } catch (\Throwable $e) {
            // On DB error, fail open (allow processing) — better than blocking
            return null;
        }
    }

    /**
     * Store the result of a successfully processed request.
     *
     * @param int    $tenantId
     * @param string $key        The Idempotency-Key from the request header
     * @param int    $paymentId  The created payment record ID
     * @param array  $response   The response data to cache
     */
    public function store(int $tenantId, string $key, int $paymentId, array $response): void
    {
        if ($key === '') {
            return;
        }

        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $this->ttl);

            $stmt = $this->db->prepare("
                INSERT INTO payment_idempotency_keys
                    (tenant_id, idempotency_key, payment_id, response_json, expires_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    payment_id    = VALUES(payment_id),
                    response_json = VALUES(response_json),
                    expires_at    = VALUES(expires_at)
            ");
            $stmt->execute([
                $tenantId,
                $key,
                $paymentId,
                json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $expiresAt,
            ]);
        } catch (\Throwable $e) {
            // Non-fatal — log but don't block the response
            error_log('[IdempotencyService] Failed to store key: ' . $e->getMessage());
        }
    }

    /**
     * Purge expired keys (run periodically via cron).
     *
     * @return int Number of rows deleted
     */
    public function purgeExpired(): int
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM payment_idempotency_keys
                WHERE expires_at < NOW()
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
