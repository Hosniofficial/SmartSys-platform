<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Pusher\Pusher;
use App\Services\MonologHandler;
use App\Utils\PaginationHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class NotificationHandler extends BaseHandler
{
    private ?Pusher $pusher = null;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('notification');
        $this->initializePusher();
    }

    private function initializePusher(): void
    {
        $key    = getenv('PUSHER_APP_KEY');
        $secret = getenv('PUSHER_APP_SECRET');
        $appId  = getenv('PUSHER_APP_ID');

        if (!$key || !$secret || !$appId) {
            $this->pusher = null;
            return;
        }

        try {
            $this->pusher = new Pusher(
                $key,
                $secret,
                $appId,
                [
                    'cluster' => getenv('PUSHER_APP_CLUSTER') ?: 'mt1',
                    'useTLS'  => true
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Pusher init failed', [
                'message' => $e->getMessage()
            ]);
            $this->pusher = null;
        }
    }

    private function createNotification(
        int $userId,
        int $tenantId,
        string $type,
        string $title,
        array $data = []
    ): ?int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (
                    tenant_id,
                    user_id,
                    type,
                    title,
                    message,
                    data,
                    is_read,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");

            $stmt->execute([
                $tenantId,
                $userId,
                $type,
                $title,
                $data['message'] ?? '',
                json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);

            $notificationId = (int) $this->db->lastInsertId();

            if ($this->pusher) {
                try {
                    $this->pusher->trigger('user-' . $userId, 'new-notification', [
                        'id' => $notificationId,
                        'type' => $type,
                        'title' => $title,
                        'data' => $data
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->warning('Pusher notification failed', [
                        'user_id' => $userId,
                        'notification_id' => $notificationId,
                        'message' => $e->getMessage()
                    ]);
                }
            }

            return $notificationId;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create notification', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'type' => $type,
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function sendLowStockAlert(int $productId): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    p.tenant_id,
                    p.name,
                    p.product_code AS sku,
                    (SELECT COALESCE(SUM(bp.quantity), 0)
                     FROM branch_products bp
                     WHERE bp.product_id = p.id
                       AND bp.tenant_id  = p.tenant_id) AS current_stock,
                    p.min_quantity AS minimum_stock,
                    u.id AS user_id
                FROM products p
                JOIN users u ON u.tenant_id = p.tenant_id
                JOIN roles r ON r.id = u.role_id
                WHERE p.id = ?
                  AND (r.name = 'admin' OR r.name = 'inventory_manager')
            ");

            $stmt->execute([$productId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!empty($results)) {
                $product = $results[0];

                foreach ($results as $row) {
                    $this->createNotification(
                        (int) $row['user_id'],
                        (int) $product['tenant_id'],
                        'low_stock_alert',
                        'تنبيه مخزون منخفض',
                        [
                            'message' => "المنتج {$product['name']} ({$product['sku']}) = {$product['current_stock']} قطعة",
                            'product_id' => $productId,
                            'current_stock' => $product['current_stock'],
                            'minimum_stock' => $product['minimum_stock']
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('sendLowStockAlert error', [
                'product_id' => $productId,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function sendExpiryAlert(int $productId): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    p.tenant_id,
                    p.name,
                    p.product_code AS sku,
                    b.expiry_date,
                    u.id AS user_id
                FROM products p
                JOIN product_batches b ON b.product_id = p.id
                JOIN users u ON u.tenant_id = p.tenant_id
                JOIN roles r ON r.id = u.role_id
                WHERE p.id = ?
                  AND (r.name = 'admin' OR r.name = 'inventory_manager')
                  AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ");

            $stmt->execute([$productId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!empty($results)) {
                $product = $results[0];

                foreach ($results as $row) {
                    $this->createNotification(
                        (int) $row['user_id'],
                        (int) $product['tenant_id'],
                        'product_expiry',
                        'تنبيه انتهاء الصلاحية',
                        [
                            'message' => "المنتج {$product['name']} ينتهي في {$product['expiry_date']}",
                            'product_id' => $productId,
                            'product_name' => $product['name'],
                            'sku' => $product['sku'],
                            'expiry_date' => $product['expiry_date']
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('sendExpiryAlert error', [
                'product_id' => $productId,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function sendNewOrderNotification(int $orderId): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    s.id,
                    s.tenant_id,
                    s.total_amount,
                    c.name AS customer_name,
                    u.id AS user_id
                FROM sales s
                JOIN customers c ON c.id = s.customer_id
                JOIN users u ON u.tenant_id = s.tenant_id
                JOIN roles r ON r.id = u.role_id
                WHERE s.id = ?
                  AND (r.name = 'admin' OR r.name = 'sales_manager')
            ");

            $stmt->execute([$orderId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!empty($results)) {
                $order = $results[0];

                foreach ($results as $row) {
                    $this->createNotification(
                        (int) $row['user_id'],
                        (int) $order['tenant_id'],
                        'new_order',
                        'طلب جديد',
                        [
                            'message' => "طلب جديد #{$order['id']} من {$order['customer_name']}",
                            'order_id' => $orderId,
                            'customer_name' => $order['customer_name'],
                            'total_amount' => $order['total_amount']
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('sendNewOrderNotification error', [
                'order_id' => $orderId,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function sendOrderStatusNotification(int $orderId, string $status): void
    {
        try {
            $statusMessages = [
                'processing' => 'جاري المعالجة',
                'shipped' => 'تم الشحن',
                'delivered' => 'تم التسليم',
                'cancelled' => 'تم الإلغاء',
                'paid' => 'تم الدفع'
            ];

            $statusLabel = $statusMessages[$status] ?? $status;

            $stmt = $this->db->prepare("
                SELECT
                    s.id,
                    s.tenant_id,
                    s.customer_id,
                    c.name AS customer_name,
                    u.id AS user_id
                FROM sales s
                JOIN customers c ON c.id = s.customer_id
                JOIN users u ON u.tenant_id = s.tenant_id
                WHERE s.id = ?
                  AND (u.role = 'admin' OR u.role = 'sales_manager')
            ");

            $stmt->execute([$orderId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!empty($results)) {
                $order = $results[0];

                foreach ($results as $row) {
                    $this->createNotification(
                        (int) $row['user_id'],
                        (int) $order['tenant_id'],
                        'order_status_change',
                        'تحديث حالة الطلب',
                        [
                            'message' => "الطلب #{$order['id']}: {$statusLabel}",
                            'order_id' => $orderId,
                            'customer_name' => $order['customer_name'],
                            'status' => $status
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('sendOrderStatusNotification error', [
                'order_id' => $orderId,
                'status' => $status,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getNotifications(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $userId = $this->extractUserId($request);
            if (!$userId) {
                return $this->errorResponse($response, 'User ID is required', 400);
            }

            $qp = $request->getQueryParams();
            [$page, $perPage, $offset] = PaginationHelper::fromArray($qp, 10, 50);

            $countStmt = $this->db->prepare("
                SELECT COUNT(*) AS total
                FROM notifications
                WHERE user_id = ? AND tenant_id = ?
            ");
            $countStmt->execute([(int) $userId, (int) $tenantId]);
            $total = (int) (($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));

            $stmt = $this->db->prepare("
                SELECT id, type, title, message, data, is_read, created_at, read_at
                FROM notifications
                WHERE user_id = ? AND tenant_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bindValue(1, (int) $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, (int) $tenantId, PDO::PARAM_INT);
            $stmt->bindValue(3, (int) $perPage, PDO::PARAM_INT);
            $stmt->bindValue(4, (int) $offset, PDO::PARAM_INT);
            $stmt->execute();

            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse($response, [
                'notifications' => $notifications,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $perPage)
                ]
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('getNotifications error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'Failed to retrieve notifications', 500);
        }
    }

    public function markAsRead(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $userId = $this->extractUserId($request);
            if (!$userId) {
                return $this->errorResponse($response, 'User ID is required', 400);
            }

            $notificationId = isset($args['id']) ? (int) $args['id'] : 0;
            if ($notificationId <= 0) {
                return $this->errorResponse($response, 'Notification ID is required', 400);
            }

            $stmt = $this->db->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND user_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$notificationId, (int) $userId, (int) $tenantId]);

            return $this->successResponse($response, [
                'notification' => [
                    'id' => $notificationId,
                    'is_read' => 1,
                    'read_at' => date('c'),
                ],
                'message' => 'Notification marked as read'
            ], 200);
        } catch (\Throwable $e) {
            $this->logger->error('markAsRead error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'Failed to mark notification as read', 500);
        }
    }

    public function markAllAsReadInternal(int $userId, int $tenantId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND tenant_id = ? AND is_read = 0
            ");

            return $stmt->execute([$userId, $tenantId]);
        } catch (\Throwable $e) {
            $this->logger->error('markAllAsReadInternal error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getUnreadCount(int $userId, int $tenantId): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS unread_count
                FROM notifications
                WHERE user_id = ? AND tenant_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId, $tenantId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($result['unread_count'] ?? 0);
        } catch (\Throwable $e) {
            $this->logger->error('getUnreadCount error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'message' => $e->getMessage()
            ]);

            return 0;
        }
    }

    public function deleteOldNotifications(int $tenantId, int $daysToKeep = 30): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications
                WHERE tenant_id = ?
                  AND is_read = 1
                  AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            return $stmt->execute([$tenantId, $daysToKeep]);
        } catch (\Throwable $e) {
            $this->logger->error('deleteOldNotifications error', [
                'tenant_id' => $tenantId,
                'days_to_keep' => $daysToKeep,
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }
}
