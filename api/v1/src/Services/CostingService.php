<?php

namespace App\Services;

use PDO;

class CostingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── getWeightedAverageCost ───────────────────────────────────────────────
    // WAC لمنتج واحد — يطرح مردودات الشراء من التكلفة الكلية
    public function getWeightedAverageCost(int $tenantId, int $productId, ?string $upToDate = null): ?float
    {
        $wacMap = $this->computeBatchWAC($tenantId, [$productId], $upToDate);
        $wac    = $wacMap[$productId] ?? null;

        if ($wac !== null) {
            return $wac;
        }

        // Fallback إلى product.purchase_price
        $p = $this->pdo->prepare("SELECT COALESCE(purchase_price,0) FROM products WHERE id = ? AND tenant_id = ?");
        $p->execute([$productId, $tenantId]);
        $fallback = (float)$p->fetchColumn();
        return $fallback > 0 ? $fallback : null;
    }

    // ─── computeBatchWAC ──────────────────────────────────────────────────────
    // Compute WAC (Weighted Average Cost) for batch of products in two queries
    // Deducts purchase returns (return_type='purchase') from total inventory
    // Returns: [product_id => float|null]  — null = no purchases, needs fallback
    public function computeBatchWAC(int $tenantId, array $productIds, ?string $upToDate = null): array
    {
        if (empty($productIds)) {
            return [];
        }

        $productIds   = array_values(array_unique(array_map('intval', $productIds)));
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $dateFilter = '';
        $dateParam  = [];
        if ($upToDate) {
            $nextDay      = date('Y-m-d', strtotime($upToDate . ' +1 day'));
            $dateFilter   = ' AND p.invoice_date < ?';
            $dateParam     = [$nextDay . ' 00:00:00'];
        }

        // Query 1: مجموع المشتريات لكل منتج
        $purchaseSql = "
            SELECT pi.product_id,
                   COALESCE(SUM(pi.quantity * pi.cost), 0) AS total_cost,
                   COALESCE(SUM(pi.quantity), 0)           AS total_qty
            FROM purchase_items pi
            INNER JOIN purchases p ON p.id = pi.purchase_id AND p.tenant_id = pi.tenant_id
            WHERE pi.tenant_id = ?
              AND pi.product_id IN ($placeholders)
              $dateFilter
              AND (p.status IS NULL OR p.status NOT IN ('canceled','cancelled'))
            GROUP BY pi.product_id
        ";
        $stmt = $this->pdo->prepare($purchaseSql);
        $stmt->execute(array_merge([$tenantId], $productIds, $dateParam));
        $purchases = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $purchases[(int)$row['product_id']] = [
                'cost' => (float)$row['total_cost'],
                'qty'  => (float)$row['total_qty'],
            ];
        }

        // Query 2: Calculate WAC deducting purchase returns
        $returnDateFilter = '';
        $returnDateParam  = [];
        if ($upToDate) {
            $returnDateFilter = ' AND r.return_date < ?';
            $returnDateParam  = [$nextDay . ' 00:00:00'];
        }
        $returnSql = "
            SELECT ri.product_id,
                   COALESCE(SUM(ri.quantity * ri.unit_price), 0) AS total_cost,
                   COALESCE(SUM(ri.quantity), 0)                 AS total_qty
            FROM return_items ri
            INNER JOIN returns r ON r.id = ri.return_id AND r.tenant_id = ri.tenant_id
            WHERE ri.tenant_id = ?
              AND ri.product_id IN ($placeholders)
              AND r.return_type = 'purchase'
              $returnDateFilter
              AND (r.status IS NULL OR r.status NOT IN ('canceled','cancelled'))
            GROUP BY ri.product_id
        ";
        $stmt2 = $this->pdo->prepare($returnSql);
        $stmt2->execute(array_merge([$tenantId], $productIds, $returnDateParam));
        $returns = [];
        foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $returns[(int)$row['product_id']] = [
                'cost' => (float)$row['total_cost'],
                'qty'  => (float)$row['total_qty'],
            ];
        }

        // حساب WAC الصافي لكل منتج
        $result = [];
        foreach ($productIds as $pid) {
            $pCost = $purchases[$pid]['cost'] ?? 0.0;
            $pQty  = $purchases[$pid]['qty']  ?? 0.0;
            $rCost = $returns[$pid]['cost']   ?? 0.0;
            $rQty  = $returns[$pid]['qty']    ?? 0.0;

            $netCost = max(0.0, $pCost - $rCost);
            $netQty  = max(0.0, $pQty  - $rQty);

            $result[$pid] = ($netQty > 0.0000001) ? ($netCost / $netQty) : null;
        }

        return $result;
    }

    // ─── computeCOGSForSale ───────────────────────────────────────────────────
    // Compute COGS using batch WAC + batch fallback for constant query count regardless of product count
    public function computeCOGSForSale(int $tenantId, int $saleId, ?string $saleDate = null): float
    {
        $stmt = $this->pdo->prepare("SELECT sale_date FROM sales WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$saleId, $tenantId]);
        $date = $saleDate ?: $stmt->fetchColumn();

        $itemsStmt = $this->pdo->prepare("SELECT product_id, quantity, conversion_factor FROM sales_items WHERE sale_id = ? AND tenant_id = ?");
        $itemsStmt->execute([$saleId, $tenantId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($items)) {
            return 0.0;
        }

        // ✅ batch WAC — 2 queries لكل المنتجات معاً
        $productIds = array_values(array_unique(array_map(fn ($it) => (int)$it['product_id'], $items)));
        $wacMap     = $this->computeBatchWAC($tenantId, $productIds, $date);

        // ✅ batch fallback لـ product.purchase_price دفعة واحدة
        $nullPids = array_values(array_filter($productIds, fn ($pid) => $wacMap[$pid] === null));
        if (!empty($nullPids)) {
            $ph  = implode(',', array_fill(0, count($nullPids), '?'));
            $fbS = $this->pdo->prepare("SELECT id, COALESCE(purchase_price,0) FROM products WHERE id IN ($ph) AND tenant_id = ?");
            $fbS->execute(array_merge($nullPids, [$tenantId]));
            foreach ($fbS->fetchAll(PDO::FETCH_KEY_PAIR) as $pid => $price) {
                $wacMap[(int)$pid] = (float)$price > 0 ? (float)$price : null;
            }
        }

        $total = 0.0;
        foreach ($items as $it) {
            $pid     = (int)$it['product_id'];
            $qty     = (float)$it['quantity'];
            $conv    = (float)($it['conversion_factor'] ?? 1.0);
            $baseQty = $qty * ($conv > 0 ? $conv : 1.0);
            if ($baseQty <= 0) {
                continue;
            }
            $wac = $wacMap[$pid] ?? null;
            if ($wac === null) {
                continue;
            }
            $total += $baseQty * $wac;
        }
        return $total;
    }
}
