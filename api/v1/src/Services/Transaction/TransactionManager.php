<?php

namespace App\Services\Transaction;

use PDO;
use Throwable;
use App\Services\MonologHandler;

class TransactionManager
{
    private PDO $pdo;
    private MonologHandler $logger;
    private int $depth = 0; // لدعم nested transactions

    public function __construct(PDO $pdo, string $logChannel = 'transactions')
    {
        $this->pdo = $pdo;
        $this->logger = MonologHandler::getInstance($logChannel);
    }

    /**
     * تنفيذ عملية بكاملها ضمن transaction واحد
     *
     * @param callable $callback الدالة التي تحتوي على المنطق
     * @param string $operationName اسم العملية للـ logging
     * @param array $context بيانات إضافية للـ logging
     * @return mixed نتيجة الـ callback
     * @throws Throwable
     */
    public function execute(callable $callback, string $operationName = '', array $context = []): mixed
    {
        $startTime = microtime(true);

        if ($this->depth === 0) {
            $this->pdo->beginTransaction();
            $this->logger->info("Transaction started: $operationName", $context);
        }

        $this->depth++;

        try {
            $result = $callback();
            $this->depth--;

            if ($this->depth === 0) {
                $this->pdo->commit();
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $this->logger->info("Transaction committed: $operationName", array_merge($context, [
                    'duration_ms' => $duration,
                    'status' => 'success'
                ]));
            }

            return $result;
        } catch (Throwable $e) {
            $this->depth--;

            if ($this->depth === 0) {
                $this->pdo->rollBack();
                $duration = round((microtime(true) - $startTime) * 1000, 2);

                // Business rule violations (validation errors, not-found, etc.)
                // are expected — log as warning without stack trace to reduce noise.
                // Only unexpected errors (PDOException, RuntimeException, etc.) get ERROR + trace.
                $isExpected = $e instanceof \Exception
                    && !($e instanceof \PDOException)
                    && !($e instanceof \RuntimeException);

                if ($isExpected) {
                    $this->logger->warning("Transaction rolled back: $operationName", array_merge($context, [
                        'duration_ms' => $duration,
                        'error'       => $e->getMessage(),
                    ]));
                } else {
                    $this->logger->error("Transaction rolled back: $operationName", array_merge($context, [
                        'duration_ms' => $duration,
                        'error'       => $e->getMessage(),
                        'code'        => $e->getCode(),
                        'trace'       => $e->getTraceAsString(),
                    ]));
                }
            }

            throw $e;
        }
    }

    /**
     * الفحص إذا كانت هناك transaction مفتوحة
     */
    public function isActive(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * الحصول على عمق الـ nesting الحالي
     */
    public function getDepth(): int
    {
        return $this->depth;
    }
}
