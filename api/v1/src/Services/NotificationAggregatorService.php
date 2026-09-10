<?php

namespace App\Services;

use PDO;
use Psr\Log\LoggerInterface;

/**
 * NotificationAggregatorService
 * 
 * Smart notification batching and aggregation service.
 * Prevents notification spam by grouping similar events.
 * 
 * Features:
 * - Batch notifications by time intervals
 * - Aggregate similar events (e.g., multiple sales → "5 new orders")
 * - User-specific preferences (instant/batched/digest/disabled)
 * - Threshold filtering (only notify for important events)
 * - Deduplication (prevent duplicate notifications)
 * 
 * @package App\Services
 */
class NotificationAggregatorService
{
    private PDO $db;
    private $logger; // Accept both LoggerInterface and MonologHandler

    // Delivery modes
    public const MODE_INSTANT = 'instant';
    public const MODE_BATCHED = 'batched';
    public const MODE_DIGEST = 'digest';
    public const MODE_DISABLED = 'disabled';

    // Priority levels
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public function __construct(PDO $db, $logger = null)
    {
        $this->db = $db;
        // Accept both LoggerInterface and MonologHandler
        $this->logger = $logger ?? new \App\Services\MonologHandler();
    }

    /**
     * Queue a notification for smart delivery
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $type Notification type (new_order, low_stock_alert, etc.)
     * @param array $eventData Event data (order_id, amount, etc.)
     * @param string $priority Priority level (low/normal/high/urgent)
     * @return bool Success status
     */
    public function queueNotification(
        int $userId,
        int $tenantId,
        string $type,
        array $eventData,
        string $priority = self::PRIORITY_NORMAL
    ): bool {
        try {
            // Get user preferences for this notification type
            $prefs = $this->getUserPreferences($userId, $tenantId, $type);

            // Check if notifications are disabled
            if ($prefs['delivery_mode'] === self::MODE_DISABLED) {
                $this->logger->debug('Notification disabled by user preference', [
                    'user_id' => $userId,
                    'type' => $type
                ]);
                return true; // Not an error, user chose to disable
            }

            // Apply threshold filtering
            if ($prefs['threshold_enabled'] && isset($eventData['amount'])) {
                if ($eventData['amount'] < $prefs['threshold_value']) {
                    $this->logger->debug('Event below threshold, skipping notification', [
                        'user_id' => $userId,
                        'amount' => $eventData['amount'],
                        'threshold' => $prefs['threshold_value']
                    ]);
                    return true; // Below threshold, skip
                }
            }

            // Decide delivery strategy
            switch ($prefs['delivery_mode']) {
                case self::MODE_INSTANT:
                    return $this->sendInstantNotification($userId, $tenantId, $type, $eventData, $priority);

                case self::MODE_BATCHED:
                    return $this->addToBatch($userId, $tenantId, $type, $eventData, $prefs['batch_interval_minutes'], $priority);

                case self::MODE_DIGEST:
                    return $this->addToDigest($userId, $tenantId, $type, $eventData);

                default:
                    $this->logger->warning('Unknown delivery mode', [
                        'mode' => $prefs['delivery_mode']
                    ]);
                    return false;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to queue notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user notification preferences
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $type
     * @return array Preferences array
     */
    private function getUserPreferences(int $userId, int $tenantId, string $type): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                delivery_mode,
                batch_interval_minutes,
                threshold_enabled,
                threshold_value,
                enable_in_app,
                enable_email,
                enable_push
            FROM notification_preferences
            WHERE user_id = ? AND tenant_id = ? AND notification_type = ?
            LIMIT 1
        ");

        $stmt->execute([$userId, $tenantId, $type]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return defaults if no preferences found
        if (!$prefs) {
            return [
                'delivery_mode' => self::MODE_BATCHED,
                'batch_interval_minutes' => 30,
                'threshold_enabled' => false,
                'threshold_value' => null,
                'enable_in_app' => true,
                'enable_email' => false,
                'enable_push' => false
            ];
        }

        return $prefs;
    }

    /**
     * Send instant notification (no batching)
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $type
     * @param array $eventData
     * @param string $priority
     * @return bool
     */
    private function sendInstantNotification(
        int $userId,
        int $tenantId,
        string $type,
        array $eventData,
        string $priority
    ): bool {
        try {
            $message = $this->formatMessage($type, $eventData, 1);
            $title = $this->getNotificationTitle($type);

            $stmt = $this->db->prepare("
                INSERT INTO notifications (
                    tenant_id,
                    user_id,
                    type,
                    batch_id,
                    aggregation_key,
                    aggregated_count,
                    priority,
                    title,
                    message,
                    data,
                    is_read,
                    created_at
                ) VALUES (?, ?, ?, NULL, NULL, 1, ?, ?, ?, ?, 0, NOW())
            ");

            $stmt->execute([
                $tenantId,
                $userId,
                $type,
                $priority,
                $title,
                $message,
                json_encode($eventData, JSON_UNESCAPED_UNICODE)
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send instant notification', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Add event to batch
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $type
     * @param array $eventData
     * @param int $intervalMinutes
     * @param string $priority
     * @return bool
     */
    private function addToBatch(
        int $userId,
        int $tenantId,
        string $type,
        array $eventData,
        int $intervalMinutes,
        string $priority
    ): bool {
        try {
            $this->db->beginTransaction();

            // Find or create active batch
            $batchId = $this->findOrCreateBatch($userId, $tenantId, $type, $intervalMinutes);

            // Update batch count and total
            $amount = $eventData['amount'] ?? 0;
            
            $stmt = $this->db->prepare("
                UPDATE notification_batches
                SET event_count = event_count + 1,
                    total_amount = COALESCE(total_amount, 0) + ?,
                    updated_at = NOW()
                WHERE batch_id = ?
            ");
            $stmt->execute([$amount, $batchId]);

            // Store event data for later aggregation
            $aggregationKey = $this->generateAggregationKey($userId, $tenantId, $type, $batchId);
            
            // Check if we should update existing aggregated notification or create new
            $existingStmt = $this->db->prepare("
                SELECT id, aggregated_count, data
                FROM notifications
                WHERE aggregation_key = ? AND is_read = 0
                LIMIT 1
            ");
            $existingStmt->execute([$aggregationKey]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing aggregated notification
                $newCount = $existing['aggregated_count'] + 1;
                $existingData = json_decode($existing['data'], true) ?? [];
                
                // Merge event data
                if (!isset($existingData['events'])) {
                    $existingData['events'] = [];
                }
                $existingData['events'][] = $eventData;
                $existingData['total_amount'] = ($existingData['total_amount'] ?? 0) + $amount;

                $updateStmt = $this->db->prepare("
                    UPDATE notifications
                    SET aggregated_count = ?,
                        message = ?,
                        data = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $message = $this->formatMessage($type, $existingData, $newCount);
                $updateStmt->execute([
                    $newCount,
                    $message,
                    json_encode($existingData, JSON_UNESCAPED_UNICODE),
                    $existing['id']
                ]);
            } else {
                // Create new aggregated notification placeholder
                $message = $this->formatMessage($type, ['events' => [$eventData], 'total_amount' => $amount], 1);
                $title = $this->getNotificationTitle($type);

                $insertStmt = $this->db->prepare("
                    INSERT INTO notifications (
                        tenant_id,
                        user_id,
                        type,
                        batch_id,
                        aggregation_key,
                        aggregated_count,
                        priority,
                        title,
                        message,
                        data,
                        is_read,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 0, NOW())
                ");

                $insertStmt->execute([
                    $tenantId,
                    $userId,
                    $type,
                    $batchId,
                    $aggregationKey,
                    $priority,
                    $title,
                    $message,
                    json_encode(['events' => [$eventData], 'total_amount' => $amount], JSON_UNESCAPED_UNICODE)
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to add to batch', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Find or create batch for batching notifications
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $type
     * @param int $intervalMinutes
     * @return string Batch ID
     */
    private function findOrCreateBatch(int $userId, int $tenantId, string $type, int $intervalMinutes): string
    {
        // Look for pending batch scheduled within interval
        $stmt = $this->db->prepare("
            SELECT batch_id
            FROM notification_batches
            WHERE user_id = ? 
              AND tenant_id = ?
              AND notification_type = ?
              AND status = 'pending'
              AND scheduled_at > NOW()
            ORDER BY scheduled_at ASC
            LIMIT 1
        ");

        $stmt->execute([$userId, $tenantId, $type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['batch_id'];
        }

        // Create new batch
        $batchId = $this->generateBatchId($userId, $tenantId, $type);
        $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$intervalMinutes} minutes"));

        $insertStmt = $this->db->prepare("
            INSERT INTO notification_batches (
                batch_id,
                tenant_id,
                user_id,
                notification_type,
                event_count,
                total_amount,
                status,
                scheduled_at,
                created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 'pending', ?, NOW())
        ");

        $insertStmt->execute([
            $batchId,
            $tenantId,
            $userId,
            $type,
            $scheduledAt
        ]);

        return $batchId;
    }

    /**
     * Add event to daily digest
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $type
     * @param array $eventData
     * @return bool
     */
    private function addToDigest(int $userId, int $tenantId, string $type, array $eventData): bool
    {
        // Similar to batching but scheduled for end of day
        $intervalMinutes = $this->getMinutesUntilEndOfDay();
        return $this->addToBatch($userId, $tenantId, $type, $eventData, $intervalMinutes, self::PRIORITY_LOW);
    }

    /**
     * Get minutes until end of day (for digest mode)
     * 
     * @return int
     */
    private function getMinutesUntilEndOfDay(): int
    {
        $now = new \DateTime();
        $endOfDay = new \DateTime('tomorrow');
        $interval = $now->diff($endOfDay);
        return ($interval->h * 60) + $interval->i;
    }

    /**
     * Generate unique batch ID
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $type
     * @return string
     */
    private function generateBatchId(int $userId, int $tenantId, string $type): string
    {
        return sprintf(
            'batch_%s_%d_%d_%s',
            $type,
            $tenantId,
            $userId,
            date('YmdHi')
        );
    }

    /**
     * Generate aggregation key for deduplication
     * 
     * @param int $userId
     * @param int $tenantId
     * @param string $type
     * @param string $batchId
     * @return string
     */
    private function generateAggregationKey(int $userId, int $tenantId, string $type, string $batchId): string
    {
        return sprintf(
            'agg_%s_%d_%d_%s',
            $type,
            $tenantId,
            $userId,
            $batchId
        );
    }

    /**
     * Format notification message based on count
     * 
     * @param string $type
     * @param array $data
     * @param int $count
     * @return string
     */
    private function formatMessage(string $type, array $data, int $count): string
    {
        switch ($type) {
            case 'new_order':
                if ($count === 1) {
                    $event = $data['events'][0] ?? $data;
                    return sprintf(
                        'طلب جديد #%d من %s',
                        $event['order_id'] ?? 0,
                        $event['customer_name'] ?? 'عميل'
                    );
                } else {
                    $totalAmount = number_format($data['total_amount'] ?? 0, 2);
                    return sprintf(
                        '%d طلبات جديدة بإجمالي %s ريال',
                        $count,
                        $totalAmount
                    );
                }

            case 'low_stock_alert':
                if ($count === 1) {
                    $event = $data['events'][0] ?? $data;
                    return sprintf(
                        'المنتج %s (%s) = %s قطعة',
                        $event['product_name'] ?? '',
                        $event['sku'] ?? '',
                        $event['current_stock'] ?? 0
                    );
                } else {
                    return sprintf('%d منتجات بمخزون منخفض', $count);
                }

            case 'payment_received':
                if ($count === 1) {
                    $event = $data['events'][0] ?? $data;
                    return sprintf(
                        'دفعة جديدة %s ريال من %s',
                        number_format($event['amount'] ?? 0, 2),
                        $event['customer_name'] ?? 'عميل'
                    );
                } else {
                    $totalAmount = number_format($data['total_amount'] ?? 0, 2);
                    return sprintf(
                        '%d دفعات جديدة بإجمالي %s ريال',
                        $count,
                        $totalAmount
                    );
                }

            default:
                return sprintf('%d إشعار جديد', $count);
        }
    }

    /**
     * Get notification title by type
     * 
     * @param string $type
     * @return string
     */
    private function getNotificationTitle(string $type): string
    {
        $titles = [
            'new_order' => 'طلبات جديدة',
            'low_stock_alert' => 'تنبيه مخزون',
            'payment_received' => 'دفعات جديدة',
            'order_status_change' => 'تحديث حالة الطلب',
            'expiry_alert' => 'تنبيه صلاحية',
        ];

        return $titles[$type] ?? 'إشعار';
    }

    /**
     * Process pending batches (called by cron)
     * 
     * @return int Number of batches processed
     */
    public function processPendingBatches(): int
    {
        try {
            // Find batches ready to be sent
            $stmt = $this->db->prepare("
                SELECT batch_id, tenant_id, user_id, notification_type, event_count, total_amount
                FROM notification_batches
                WHERE status = 'pending'
                  AND scheduled_at <= NOW()
                ORDER BY scheduled_at ASC
                LIMIT 100
            ");

            $stmt->execute();
            $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $processed = 0;
            foreach ($batches as $batch) {
                if ($this->finalizeBatch($batch)) {
                    $processed++;
                }
            }

            return $processed;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process pending batches', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Finalize a batch and mark as sent
     * 
     * @param array $batch
     * @return bool
     */
    private function finalizeBatch(array $batch): bool
    {
        try {
            // Mark batch as sent
            $stmt = $this->db->prepare("
                UPDATE notification_batches
                SET status = 'sent',
                    sent_at = NOW()
                WHERE batch_id = ?
            ");

            $stmt->execute([$batch['batch_id']]);

            $this->logger->info('Batch finalized', [
                'batch_id' => $batch['batch_id'],
                'event_count' => $batch['event_count']
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to finalize batch', [
                'batch_id' => $batch['batch_id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
