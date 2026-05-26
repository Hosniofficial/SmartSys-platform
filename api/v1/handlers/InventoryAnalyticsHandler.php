<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\MonologHandler;

/**
 * InventoryAnalyticsHandler
 *
 * Handles inventory-related analytics.
 * Real extraction from AnalyticsHandler — logic lives here, no delegation.
 */
class InventoryAnalyticsHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('analytics');
    }

    // =========================================================
    // GET /analytics/inventory
    // =========================================================

    public function analyzeInventory(Request $request, Response $response): Response
    {
        $params    = $request->getQueryParams();
        $tenantId  = $this->extractTenantId($request);

        if (!$tenantId) {
            return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 400);
        }

        $where       = [];
        $queryParams = [];

        if (!empty($params['category_id'])) {
            $where[]       = 'p.category_id = ?';
            $queryParams[] = $params['category_id'];
        }

        $extraWhere = !empty($where) ? ' AND ' . implode(' AND ', $where) : '';

        // Stock levels per product (aggregated across all branches)
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.product_code,
                   COALESCE(SUM(wp.quantity), 0) as quantity,
                   p.min_quantity, p.maximum_quantity,
                   CASE
                       WHEN COALESCE(SUM(wp.quantity), 0) <= p.min_quantity     THEN 'low'
                       WHEN COALESCE(SUM(wp.quantity), 0) >= p.maximum_quantity THEN 'high'
                       ELSE 'normal'
                   END as stock_status
            FROM products p
            LEFT JOIN branch_products wp ON wp.product_id = p.id AND wp.tenant_id = ?
            WHERE p.tenant_id = ? AND p.active = 1 {$extraWhere}
            GROUP BY p.id, p.name, p.product_code, p.min_quantity, p.maximum_quantity
            ORDER BY
                CASE
                    WHEN COALESCE(SUM(wp.quantity), 0) <= p.min_quantity     THEN 1
                    WHEN COALESCE(SUM(wp.quantity), 0) >= p.maximum_quantity THEN 3
                    ELSE 2
                END, p.name
        ");
        $stmt->execute(array_merge([$tenantId, $tenantId], $queryParams));
        $stockLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stock movement from inventory_transactions
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.product_code,
                   COUNT(DISTINCT CASE WHEN it.movement_type = 'in'  THEN it.id END) as total_restocks,
                   COUNT(DISTINCT CASE WHEN it.movement_type = 'out' THEN it.id END) as total_withdrawals,
                   SUM(CASE WHEN it.movement_type = 'in'  THEN it.quantity ELSE 0 END) as total_in,
                   SUM(CASE WHEN it.movement_type = 'out' THEN it.quantity ELSE 0 END) as total_out,
                   AVG(CASE WHEN it.movement_type = 'out' THEN it.quantity END) as avg_withdrawal
            FROM products p
            LEFT JOIN inventory_transactions it ON it.product_id = p.id AND it.tenant_id = ?
            WHERE p.tenant_id = ? AND p.active = 1 {$extraWhere}
            GROUP BY p.id, p.name, p.product_code
            ORDER BY total_out DESC
        ");
        $stmt->execute(array_merge([$tenantId, $tenantId], $queryParams));
        $stockMovement = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->successResponse($response, [
            'branch_products' => $stockLevels,
            'stock_movement'  => $stockMovement,
        ], 200);
    }

    // =========================================================
    // Internal helper (not a route — called programmatically)
    // =========================================================

    public function forecastInventory(int $productId, int $days = 30): array
    {
        $tenantId = $_SESSION['tenant_id'] ?? ($_SERVER['HTTP_X_TENANT_ID'] ?? null);
        if (!$tenantId) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT AVG(daily_quantity) as avg_daily_consumption
            FROM (
                SELECT DATE(s.date) as date, SUM(si.quantity) as daily_quantity
                FROM sales s
                JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
                WHERE si.product_id = ? AND s.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY DATE(s.date)
            ) daily_sales
        ");
        $stmt->execute([$productId]);
        $avgConsumption = (float) ($stmt->fetchColumn() ?? 0);

        $stmt = $this->db->prepare("
            SELECT STDDEV(daily_quantity) as consumption_stddev
            FROM (
                SELECT DATE(s.date) as date, SUM(si.quantity) as daily_quantity
                FROM sales s
                JOIN sales_items si ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
                WHERE si.product_id = ? AND s.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY DATE(s.date)
            ) daily_sales
        ");
        $stmt->execute([$productId]);
        $stdDev = (float) ($stmt->fetchColumn() ?? 0);

        $stmt = $this->db->prepare("
            SELECT COALESCE(wp.quantity, 0) as current_stock,
                   p.minimum_stock, p.maximum_stock, p.reorder_point, p.lead_time_days
            FROM products p
            LEFT JOIN branch_products wp ON wp.product_id = p.id AND wp.tenant_id = ?
            WHERE p.id = ?
        ");
        $stmt->execute([$tenantId, $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [];
        }

        $safetyStock  = $stdDev * sqrt((float) ($product['lead_time_days'] ?? 0));
        $currentStock = (float) $product['current_stock'];
        $forecast     = [];

        for ($i = 1; $i <= $days; $i++) {
            $expectedStock = $currentStock - ($avgConsumption * $i);
            $forecast[] = [
                'day'                  => $i,
                'date'                 => date('Y-m-d', strtotime("+{$i} days")),
                'expected_stock'       => round($expectedStock),
                'expected_consumption' => round($avgConsumption),
                'needs_reorder'        => $expectedStock <= ((float) ($product['reorder_point'] ?? 0) + $safetyStock),
                'safety_stock'         => round($safetyStock),
            ];
        }

        return $forecast;
    }
}
