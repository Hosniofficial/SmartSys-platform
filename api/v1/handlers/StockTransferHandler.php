<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\CostingService;
use App\Services\MonologHandler;

/**
 * StockTransferHandler
 *
 * Handles inter-branch stock transfer operations extracted from BranchHandler:
 *   - transferStock()                    — execute a stock transfer between branches
 *   - listTransfers()                    — list transfers with optional filters
 *   - getTransferById()                  — get transfer details + inventory transactions
 *   - listBranchTransfersByInventory()   — transfers for a branch from inventory_transactions
 *   - getTransferHistory()               — transfer history for a product
 */
class StockTransferHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('branch');
    }

    // =========================================================================
    // Public Endpoints
    // =========================================================================

    /**
     * نقل المخزون بين الفروع
     * POST /branches/transfer
     */
    public function transferStock(Request $request, Response $response, array $args): Response
    {
        $tenantId = $this->extractTenantId($request);
        $data     = $request->getParsedBody();
        $userAttr = $request->getAttribute('user');
        $userId   = $request->getAttribute('user_id') ?? ($userAttr['id'] ?? null);

        $required = ['from_branch', 'to_branch', 'product_id', 'quantity'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse($response, "حقل مطلوب مفقود: $field", 400);
            }
        }

        try {
            $this->db->beginTransaction();

            // 1. التأكد من وجود كمية كافية في الفرع المصدر
            $checkStmt = $this->db->prepare(
                "SELECT quantity FROM branch_products
                 WHERE branch_id = ? AND product_id = ? AND tenant_id = ?"
            );
            $checkStmt->execute([$data['from_branch'], $data['product_id'], $tenantId]);
            $currentStock = $checkStmt->fetchColumn();

            if ($currentStock === false) {
                throw new \Exception('المنتج غير موجود في الفرع المصدر');
            }
            if ($currentStock < $data['quantity']) {
                throw new \Exception('الكمية المتوفرة غير كافية للنقل');
            }

            // 2. خصم الكمية والتكلفة من الفرع المصدر (نسبة WAC)
            $this->db->prepare(
                "UPDATE branch_products
                 SET quantity      = quantity - ?,
                     quantity_cost = GREATEST(0, quantity_cost - (quantity_cost / NULLIF(quantity, 0) * ?))
                 WHERE branch_id = ? AND product_id = ? AND tenant_id = ?"
            )->execute([$data['quantity'], $data['quantity'], $data['from_branch'], $data['product_id'], $tenantId]);

            // 3. إضافة الكمية والتكلفة إلى الفرع الوجهة
            $transferUnitCost  = (float) ($data['unit_cost'] ?? 0);
            $transferTotalCost = (float) $data['quantity'] * $transferUnitCost;
            $this->db->prepare(
                "INSERT INTO branch_products (branch_id, product_id, quantity, quantity_cost, tenant_id)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     quantity      = quantity + VALUES(quantity),
                     quantity_cost = quantity_cost + VALUES(quantity_cost)"
            )->execute([$data['to_branch'], $data['product_id'], $data['quantity'], $transferTotalCost, $tenantId]);

            // 4. سجل عملية النقل في stock_transfers
            $this->db->prepare(
                "INSERT INTO stock_transfers (tenant_id, from_branch, to_branch, product_id, quantity, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $tenantId, $data['from_branch'], $data['to_branch'],
                $data['product_id'], $data['quantity'], $data['notes'] ?? null, $userId,
            ]);
            $transferId = (int) $this->db->lastInsertId();

            // 5 + 6. سجل حركات المخزون (صادر + وارد)
            $unitId      = (int)   ($data['unit_id']      ?? 1);
            $unitCost    = (float) ($data['unit_cost']     ?? 0);
            $totalCost   = (float) $data['quantity'] * $unitCost;
            $batchNumber = $data['batch_number'] ?? null;
            $expiryDate  = $data['expiry_date']  ?? null;
            $serial      = $data['serial']       ?? null;

            $txInsert = $this->db->prepare(
                "INSERT INTO inventory_transactions
                 (tenant_id, product_id, unit_id, branch_from, branch_to, movement_type,
                  quantity, unit_cost, total_cost, batch_number, expiry_date, serial,
                  notes, user_id, reference_type, reference_id, journal_entry_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'transfer', ?, NULL)"
            );

            $txInsert->execute([
                $tenantId, $data['product_id'], $unitId,
                $data['from_branch'], $data['to_branch'], 'transfer_out',
                $data['quantity'], $unitCost, $totalCost,
                $batchNumber, $expiryDate, $serial,
                'نقل إلى فرع آخر: ' . ($data['notes'] ?? ''),
                $userId, $transferId,
            ]);
            $transferOutId = (int) $this->db->lastInsertId();

            $txInsert->execute([
                $tenantId, $data['product_id'], $unitId,
                $data['from_branch'], $data['to_branch'], 'transfer_in',
                $data['quantity'], $unitCost, $totalCost,
                $batchNumber, $expiryDate, $serial,
                'نقل من فرع آخر: ' . ($data['notes'] ?? ''),
                $userId, $transferId,
            ]);
            $transferInId = (int) $this->db->lastInsertId();

            // 7. القيد المحاسبي
            $accStmt = $this->db->prepare(
                "SELECT id, account_id FROM branches WHERE id IN (?, ?) AND tenant_id = ?"
            );
            $accStmt->execute([(int) $data['from_branch'], (int) $data['to_branch'], (int) $tenantId]);
            $accRows = $accStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $fromAccountId = null;
            $toAccountId   = null;
            foreach ($accRows as $r) {
                if ((int) $r['id'] === (int) $data['from_branch']) $fromAccountId = $r['account_id'] ? (int) $r['account_id'] : null;
                if ((int) $r['id'] === (int) $data['to_branch'])   $toAccountId   = $r['account_id'] ? (int) $r['account_id'] : null;
            }

            if ($fromAccountId && $toAccountId) {
                $wacCost = 0.0;
                try {
                    $wacCost = (float) (new CostingService($this->db))
                        ->getWeightedAverageCost((int) $tenantId, (int) $data['product_id'], date('Y-m-d H:i:s'));
                } catch (\Throwable $e) { $wacCost = 0.0; }

                if ($wacCost <= 0.0) {
                    $costStmt = $this->db->prepare(
                        "SELECT COALESCE(purchase_price, 0) FROM products WHERE id = ? AND tenant_id = ?"
                    );
                    $costStmt->execute([(int) $data['product_id'], (int) $tenantId]);
                    $wacCost = (float) ($costStmt->fetchColumn() ?: 0);
                }

                $amount = round((float) $data['quantity'] * $wacCost, 2);

                if ($amount > 0) {
                    $desc = 'نقل مخزون من مخزن #' . (int) $data['from_branch']
                          . ' إلى مخزن #' . (int) $data['to_branch']
                          . ' - منتج #' . (int) $data['product_id']
                          . ' - كمية ' . (float) $data['quantity'];

                    $journalEntryId = $this->accounting->postJournalEntry(
                        (int) $tenantId,
                        'stock_transfer',
                        $transferId,
                        $desc,
                        [
                            ['account_id' => $toAccountId,   'debit' => $amount, 'credit' => 0,       'description' => 'نقل مخزون وارد'],
                            ['account_id' => $fromAccountId, 'debit' => 0,       'credit' => $amount, 'description' => 'نقل مخزون صادر'],
                        ],
                        date('Y-m-d'),
                        $userId,
                        null
                    );

                    if ($journalEntryId) {
                        $this->db->prepare(
                            "UPDATE inventory_transactions
                             SET journal_entry_id = ?
                             WHERE tenant_id = ? AND reference_type = 'transfer' AND reference_id = ?"
                        )->execute([$journalEntryId, (int) $tenantId, $transferId]);
                    }
                } else {
                    $this->logger->warning('[StockTransfer] Zero amount — journal entry skipped.', [
                        'from_branch' => $data['from_branch'], 'to_branch' => $data['to_branch'],
                        'product_id'  => $data['product_id'],  'quantity'  => $data['quantity'],
                    ]);
                }
            } else {
                $this->logger->warning('[StockTransfer] Missing branch account_id — journal entry skipped.', [
                    'from_branch' => $data['from_branch'], 'to_branch' => $data['to_branch'],
                ]);
            }

            $this->db->commit();

            return $this->jsonResponse($response, [
                'status'  => 'success',
                'message' => 'تم نقل المخزون بنجاح',
                'data'    => [
                    'transfer_out_tx_id' => $transferOutId,
                    'transfer_in_tx_id'  => $transferInId,
                    'stock_transfer_id'  => $transferId,
                ],
            ]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logger->error('فشل نقل المخزون: ' . $e->getMessage());
            return $this->errorResponse($response, 'فشل نقل المخزون', 400);
        }
    }

    /**
     * قائمة عمليات نقل المخزون مع فلاتر اختيارية
     */
    public function listTransfers(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $qp        = $request->getQueryParams();
            $branchId  = isset($qp['branch_id'])  ? (int) $qp['branch_id']  : null;
            $productId = isset($qp['product_id']) ? (int) $qp['product_id'] : null;
            $dateFrom  = $qp['date_from'] ?? null;
            $dateTo    = $qp['date_to']   ?? null;

            $where  = ['st.tenant_id = ?'];
            $params = [$tenantId];

            if ($branchId) {
                $where[]  = '(st.from_branch = ? OR st.to_branch = ?)';
                $params[] = $branchId;
                $params[] = $branchId;
            }
            if ($productId) { $where[] = 'st.product_id = ?';  $params[] = $productId; }
            if ($dateFrom)  { $where[] = 'st.created_at >= ?'; $params[] = $dateFrom; }
            if ($dateTo) {
                $nextDay  = date('Y-m-d', strtotime($dateTo . ' +1 day'));
                $where[]  = 'st.created_at < ?';
                $params[] = $nextDay . ' 00:00:00';
            }

            $sql = "
                SELECT
                    st.id, st.product_id, p.name AS product_name, p.barcode,
                    st.from_branch, w1.name AS from_branch_name,
                    st.to_branch,   w2.name AS to_branch_name,
                    st.quantity, st.notes,
                    st.created_by, u.name AS created_by_name, st.created_at
                FROM stock_transfers st
                LEFT JOIN products p  ON p.id  = st.product_id  AND (p.tenant_id  = st.tenant_id OR p.tenant_id  IS NULL)
                LEFT JOIN branches w1 ON w1.id = st.from_branch
                LEFT JOIN branches w2 ON w2.id = st.to_branch
                LEFT JOIN users u     ON u.id  = st.created_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY st.created_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->successResponse($response, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 200);
        } catch (PDOException $e) {
            $this->logger->error('خطأ في جلب تحويلات الفرع: ' . $e->getMessage());
            return $this->errorResponse($response, 'فشل في جلب تحويلات الفرع', 500);
        }
    }

    /**
     * تفاصيل عملية نقل واحدة
     */
    public function getTransferById(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }
            $id = (int) ($args['id'] ?? 0);
            if ($id <= 0) {
                return $this->errorResponse($response, 'معرّف عملية النقل غير صالح', 400);
            }

            $stmt = $this->db->prepare("
                SELECT st.*, p.name AS product_name, p.barcode,
                       b1.name AS from_branch_name, b2.name AS to_branch_name,
                       u.name AS created_by_name
                FROM stock_transfers st
                LEFT JOIN products p  ON p.id  = st.product_id  AND (p.tenant_id  = st.tenant_id OR p.tenant_id  IS NULL)
                LEFT JOIN branches b1 ON b1.id = st.from_branch
                LEFT JOIN branches b2 ON b2.id = st.to_branch
                LEFT JOIN users u     ON u.id  = st.created_by
                WHERE st.tenant_id = ? AND st.id = ?
            ");
            $stmt->execute([$tenantId, $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $this->errorResponse($response, 'لم يتم العثور على عملية النقل', 404);
            }

            $stmtTx = $this->db->prepare(
                "SELECT it.* FROM inventory_transactions it
                 WHERE it.tenant_id = ? AND it.reference_type = 'transfer' AND it.reference_id = ?
                 ORDER BY it.created_at ASC"
            );
            $stmtTx->execute([$tenantId, $id]);
            $row['inventory_transactions'] = $stmtTx->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse($response, $row, 200);
        } catch (PDOException $e) {
            $this->logger->error('خطأ في جلب تفاصيل التحويل: ' . $e->getMessage());
            return $this->errorResponse($response, 'فشل في جلب تفاصيل التحويل', 500);
        }
    }

    /**
     * تحويلات فرع من جدول inventory_transactions (transfer_in / transfer_out)
     */
    public function listBranchTransfersByInventory(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            $branchId = (int) ($args['id'] ?? 0);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $stmt = $this->db->prepare("
                SELECT it.id, it.product_id, p.name AS product_name, p.barcode,
                       it.branch_from AS from_branch, w1.name AS from_branch_name,
                       it.branch_to   AS to_branch,   w2.name AS to_branch_name,
                       it.quantity, it.movement_type, it.notes,
                       it.user_id AS created_by, u.name AS created_by_name, it.created_at
                FROM inventory_transactions it
                LEFT JOIN products p  ON p.id  = it.product_id AND (p.tenant_id  = it.tenant_id OR p.tenant_id  IS NULL)
                LEFT JOIN branches w1 ON w1.id = it.branch_from
                LEFT JOIN branches w2 ON w2.id = it.branch_to
                LEFT JOIN users u     ON u.id  = it.user_id
                WHERE it.tenant_id = ?
                  AND (it.branch_from = ? OR it.branch_to = ?)
                  AND it.movement_type IN ('transfer_out', 'transfer_in')
                ORDER BY it.created_at DESC
            ");
            $stmt->execute([$tenantId, $branchId, $branchId]);
            return $this->successResponse($response, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 200);
        } catch (PDOException $e) {
            $this->logger->error('خطأ في جلب تحويلات الفرع: ' . $e->getMessage());
            return $this->errorResponse($response, 'فشل في جلب التحويلات', 500);
        }
    }

    /**
     * تاريخ نقل منتج معين عبر الفروع
     */
    public function getTransferHistory(Request $request, Response $response, array $args): Response
    {
        $tenantId  = $this->extractTenantId($request);
        $productId = $args['id'];

        try {
            $stmt = $this->db->prepare("
                SELECT t.*,
                       w1.name AS from_branch_name,
                       w2.name AS to_branch_name,
                       u.name  AS created_by_name
                FROM stock_transfers t
                LEFT JOIN branches w1 ON t.from_branch = w1.id
                LEFT JOIN branches w2 ON t.to_branch   = w2.id
                LEFT JOIN users u     ON t.created_by  = u.id
                WHERE t.product_id = ? AND t.tenant_id = ?
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$productId, $tenantId]);
            return $this->successResponse($response, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 200);
        } catch (PDOException $e) {
            $this->logger->error('خطأ في جلب تاريخ النقل: ' . $e->getMessage());
            return $this->errorResponse($response, 'فشل في جلب تاريخ النقل', 500);
        }
    }
}
