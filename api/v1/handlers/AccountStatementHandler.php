<?php
namespace App\Handlers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use App\Services\MonologHandler;
use App\Services\LabelService;
class AccountStatementHandler extends BaseHandler {
    // كاش لربط (tenant_id, code) -> account_id لتقليل الاستعلامات المتكررة
    private array $accountCache = [];

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('account_statement');
    }

    // GET: كشف حساب عميل/مورد/حساب
    public function getStatement(Request $request, Response $response, array $args = []): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID)', 403);
            }

            // allow account resolution by id or code
            $accountId = $args['account_id'] ?? null;

            // Optional: party context (e.g., customer/supplier) for reference sections
            // Prefer route args, but allow query params (frontend sends as query)
            $partyType = $args['party_type'] ?? null; // e.g., 'customer' | 'supplier'
            $partyId = $args['party_id'] ?? null;

            $params = $request->getQueryParams();
            if (!$partyType && isset($params['party_type'])) {
                $partyType = $params['party_type'];
            }
            if (!$partyId && isset($params['party_id'])) {
                $partyId = $params['party_id'];
            }
            $startDate = $params['start_date'] ?? date('Y-m-01'); // بداية الشهر الحالي إذا لم يتم التحديد
            $endDate = $params['end_date'] ?? date('Y-m-d'); // اليوم الحالي إذا لم يتم التحديد (وليس نهاية الشهر)
            // التحقق من صحة نطاق التاريخ قبل تنفيذ أي استعلامات
            if (!$this->validateDateRange($startDate, $endDate)) {
                return $this->errorResponse($response, 'نطاق التاريخ غير صالح', 400);
            }
            $statusAny = isset($params['status']) && strtolower($params['status']) === 'any';

            // Filters suitable for large datasets
            $includeTypes = isset($params['include_types']) ? $this->parseTypes($params['include_types']) : [];
            $excludeTypes = isset($params['exclude_types']) ? $this->parseTypes($params['exclude_types']) : [];

            // Optional cost center filter (null means no filtering)
            $costCenterId = null;
            if (isset($params['cost_center_id']) && $params['cost_center_id'] !== '') {
                $parsed = (int)$params['cost_center_id'];
                $costCenterId = $parsed > 0 ? $parsed : null;
            }

            // Pagination (optional)
            $perPage = isset($params['per_page']) ? max(1, (int)$params['per_page']) : null;
            $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
            $limit = $perPage;
            $offset = $perPage ? ($page - 1) * $perPage : null;

            // Resolve account by code if id not provided
            if (!$accountId) {
                $accountCode = $args['account_code'] ?? ($params['account_code'] ?? null);
                if ($accountCode) {
                    $accountId = $this->resolveAccountIdByCode($accountCode, $tenantId);
                }
            }
            if (!$accountId) {
                return $this->errorResponse($response, 'مطلوب رقم الحساب أو account_code', 400);
            }
            // Locale from headers (fallback to 'ar')
            $acceptLang = $request->getHeaderLine('Accept-Language');
            $locale = (stripos($acceptLang, 'ar') === 0) ? 'ar' : 'en';
            // 1. جلب بيانات الحساب
            $accountStmt = $this->db->prepare(
                "SELECT id, name, code, type FROM accounts WHERE id = :id AND tenant_id = :tenant_id"
            );
            $accountStmt->execute([':id' => $accountId, ':tenant_id' => $tenantId]);
            $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                return $this->errorResponse($response, 'لم يتم العثور على الحساب', 404);
            }

            // 2. حساب الرصيد الافتتاحي (قبل تاريخ البداية)
            $openingBalance = $this->calculateOpeningBalance($accountId, $startDate, $tenantId, $statusAny, $includeTypes, $excludeTypes, $costCenterId);

            // 3. جلب الحركات خلال الفترة المحددة مع الحسابات
            $transactionsData = $this->getTransactions($accountId, $startDate, $endDate, $tenantId, $statusAny, $includeTypes, $excludeTypes, $limit, $offset, $costCenterId);
            // Localize labels for transactions
            // Use transaction_type (actual type) instead of reference_type
            // This ensures settlement lines show correct labels (e.g., 'قبض' instead of 'فاتورة بيع')
            if (!empty($transactionsData['transactions'])) {
                foreach ($transactionsData['transactions'] as &$tx) {
                    // Use transaction_type which already accounts for settlement line detection
                    $type = isset($tx['transaction_type']) ? strtolower((string)$tx['transaction_type']) : null;
                    if (!$type && isset($tx['reference_type'])) {
                        $type = strtolower((string)$tx['reference_type']);
                    }
                    $statusCode = isset($tx['status_code']) ? strtolower((string)$tx['status_code']) : null;
                    $tx['reference_label'] = $this->referenceLabel($type, $locale);
                    // For payment vouchers (receipts/refunds), don't show the status
                    if (in_array($type, ['payment', 'receipt', 'refund', 'cash_voucher'])) {
                        $tx['status_label'] = $locale === 'ar' ? 'مُرحّل' : 'Posted';
                    } else {
                        $tx['status_label'] = $this->statusLabel($statusCode, $locale);
                    }
                }
                unset($tx);
            }
            $transactions = $transactionsData['transactions'];
            // Always fill daily gaps for consistent reporting across all systems
            // This ensures charts, analytics, and dashboards display uniformly (like SAP, Odoo, NetSuite)
            // All daily_balances arrays include every day in the date range, even with zero transactions
            $dailyBalances = $this->fillDailyGaps($transactionsData['daily_balances'], $startDate, $endDate);
            
            // Optional: Filter to show only days with activity (for specialized reports)
            $onlyNonZero = isset($params['only_nonzero']) && (string)$params['only_nonzero'] === '1';
            if ($onlyNonZero && !empty($dailyBalances)) {
                $dailyBalances = array_values(array_filter($dailyBalances, function($d) {
                    return isset($d['transaction_count']) && (int)$d['transaction_count'] > 0;
                }));
            }
            $closingBalance = !empty($dailyBalances) ? end($dailyBalances)['closing_balance'] : $openingBalance;

            // 4. أقسام مرجعية إضافية لا تؤثر على الأرصدة (حسب نوع الطرف)
            $extras = [];
            if ($partyType === 'customer' && $partyId) {
                $extras['sales_only'] = $this->getCustomerSalesOnly($partyId, $startDate, $endDate, $tenantId);
                $extras['references'] = $this->getCustomerReferences($partyId, $startDate, $endDate, $tenantId, $costCenterId);
            } elseif ($partyType === 'supplier' && $partyId) {
                $extras['references'] = $this->getSupplierReferences($partyId, $startDate, $endDate, $tenantId);
            }

            // 5. إرجاع النتيجة
            $responseData = [
                'status' => 'success',
                'data' => [
                    'account' => [
                        'id' => $account['id'],
                        'name' => $account['name'],
                        'code' => $account['code'],
                        'type' => $account['type']
                    ],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'opening_balance' => (float) $openingBalance,
                    'transactions' => $transactions,
                    'daily_balances' => $dailyBalances,
                    'total_debit' => $transactionsData['total_debit'],
                    'total_credit' => $transactionsData['total_credit'],
                    'transaction_count' => $transactionsData['transaction_count'],
                    '_transaction_count_note' => 'Includes all journal entry lines (debits and credits). For customer statements: this includes sales invoices, receipts, returns, and refunds. Each transaction may appear as multiple lines (debit + credit lines).',
                    'closing_balance' => (float) $closingBalance
                ]
            ];

            if ($perPage) {
                $responseData['data']['pagination'] = [
                    'page' => $page,
                    'per_page' => $perPage,
                    'returned' => count($transactions)
                ];
            }

            if (isset($extras['sales_only'])) {
                $responseData['data']['sales_only'] = $extras['sales_only'];
            }
            if (isset($extras['references'])) {
                // Localize reference items labels
                // The 'type' field from getCustomerReferences already contains
                // the correct type (e.g., 'receipt' for payments), so use it as-is
                if (!empty($extras['references']['items'])) {
                    foreach ($extras['references']['items'] as &$ref) {
                        // 'type' field already contains correct type from getCustomerReferences
                        // (e.g., 'receipt' for payments, not 'sale')
                        $type = isset($ref['type']) ? strtolower((string)$ref['type']) : null;
                        $statusCode = isset($ref['status']) ? strtolower((string)$ref['status']) : null;
                        $ref['reference_label'] = $this->referenceLabel($type, $locale);
                        // For payment vouchers (receipts/refunds), don't show the status
                        if (in_array($type, ['payment', 'receipt', 'refund', 'cash_voucher'])) {
                            $ref['status_label'] = $locale === 'ar' ? 'مُرحّل' : 'Posted';
                        } else {
                            $ref['status_label'] = $this->statusLabel($statusCode, $locale);
                        }
                    }
                    unset($ref);
                }
                $extras['references']['_note'] = 'Combined view: sales invoices, returns, receipts, and refunds. Use transaction_subtype and return_group_id to link related items (e.g., return #5 with refund #8 both having return_group_id=5).';
                $responseData['data']['references'] = $extras['references'];
            }

            return $this->successResponse($response, $responseData['data'], 200);
        } catch (\Throwable $e) {
            $this->logger->error('Account statement error', ['error' => $e->getMessage(), 'tenant_id' => $tenantId ?? null]);
            return $this->errorResponse($response, 'فشل في إنشاء كشف الحساب', 500);
        }
    }

    // التحقق من صحة نطاق التاريخ (YYYY-MM-DD) وأن البداية لا تتجاوز النهاية
    private function validateDateRange($startDate, $endDate): bool {
        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end   = \DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$start || !$end) {
            return false;
        }

        return $start <= $end;
    }

    /**
     * تطبيع حالة الفاتورة بناءً على البيانات المالية
     * 
     * يطبق منطق التطبيع الموحد على جميع الفواتير لضمان توحيد الـ status بين 
     * sales_only و references sections
     * 
     * @param string $status: الحالة الأصلية من قاعدة البيانات
     * @param float $paid: المبلغ المدفوع نقداً
     * @param float $returnCredits: مبلغ return credits المطبق
     * @param float $grandTotal: الإجمالي (net + tax)
     * @param bool $hasReturns: هل الفاتورة لها مرتجعات
     * @return string: الحالة المطبعة (normalized status)
     */
private function normalizeInvoiceStatus(
    string $status,
    float $paid,
    float $returnCredits,
    float $grandTotal,
    bool $hasReturns
): string {

    $outstanding = max(0.0, round($grandTotal - $paid - $returnCredits, 2));

    // 1. Fully settled by credit note only (no cash paid)
    if ($returnCredits > 0.01 && $outstanding < 0.01 && !$hasReturns) {
        return 'settled_by_credit';
    }

    // 2. Fully paid cash invoice, including invoices that later got returns
    if ($outstanding < 0.01 && $paid >= $grandTotal - 0.01) {
        return $hasReturns || $returnCredits > 0.01 ? 'returned' : 'paid';
    }

    // 3. Fully settled by mixed cash + return credits
    if ($outstanding < 0.01 && $paid > 0.01 && $returnCredits > 0.01) {
        return 'settled_mixed';
    }

    // 4. Fully settled by return credit allocation without any cash payment
    if ($outstanding < 0.01 && $paid < 0.01) {
        if ($returnCredits > 0.01 && !$hasReturns) {
            return 'settled_by_credit';
        }
        return 'closed_by_return';
    }

    // 5. Partial or unpaid invoice
    if ($paid <= 0.01) {
        return $status;
    }

    return $status;
}

    // حساب الرصيد الافتتاحي
    private function calculateOpeningBalance($accountId, $startDate, $tenantId, $statusAny = false, array $includeTypes = [], array $excludeTypes = [], $costCenterId = null) {
        $sql = "
            SELECT 
                COALESCE(SUM(jel.debit_amount), 0) - COALESCE(SUM(jel.credit_amount), 0) as balance
            FROM 
                journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE 
                jel.account_id = :account_id
                AND je.tenant_id = :tenant_id
                AND je.entry_date < :start_date
        ";
        if (!$statusAny) {
            $sql .= " AND (je.status IS NULL OR je.status = 'posted')";
        }
        $params = [
            ':account_id' => $accountId,
            ':tenant_id' => $tenantId,
            ':start_date' => $startDate . ' 00:00:00',
        ];
        if ($costCenterId !== null) {
            $sql .= " AND je.cost_center_id = :cost_center_id";
            $params[':cost_center_id'] = $costCenterId;
        }
        // include/exclude reference_type
        if (!empty($includeTypes)) {
            $inPlaceholders = [];
            foreach ($includeTypes as $i => $t) { $inPlaceholders[] = ":inc_$i"; $params[":inc_$i"] = $t; }
            $sql .= " AND (je.reference_type IN (" . implode(',', $inPlaceholders) . "))";
        } elseif (!empty($excludeTypes)) {
            $exPlaceholders = [];
            foreach ($excludeTypes as $i => $t) { $exPlaceholders[] = ":exc_$i"; $params[":exc_$i"] = $t; }
            $sql .= " AND (je.reference_type NOT IN (" . implode(',', $exPlaceholders) . "))";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    // جلب الحركات خلال الفترة مع حساب الأرصدة
    private function getTransactions($accountId, $startDate, $endDate, $tenantId, $statusAny = false, array $includeTypes = [], array $excludeTypes = [], $limit = null, $offset = null, $costCenterId = null) {
        $sql = "
            SELECT
                je.entry_date as date,
                je.reference_type as reference_type,
                je.reference_id as reference_id,
                je.status as status_code,
                CONCAT(COALESCE(je.reference_type, ''), '#', COALESCE(je.reference_id, '')) AS reference,
                jel.description,
                jel.debit_amount as debit,
                jel.credit_amount as credit,
                'journal' as source_type,
                je.id as source_id,
                je.cost_center_id as cost_center_id,
                -- Detect settlement lines directly based on debit/credit
                -- For a settlement line on customer account: it's a credit (money in/out)
                -- Classify based on transaction reference type and direction
                CASE 
                    -- Sale settlement line (credit > 0 = payment received from customer)
                    -- Using 0 instead of 0.01 to catch even small payments
                    WHEN je.reference_type = 'sale' AND jel.credit_amount > 0 THEN 'receipt'
                    -- Purchase settlement line (debit > 0 = payment made to supplier)
                    WHEN je.reference_type = 'purchase' AND jel.debit_amount > 0 THEN 'payment'
                    -- For receipt/payment/refund types, keep as-is
                    WHEN je.reference_type IN ('receipt', 'payment', 'refund', 'cash_voucher') THEN je.reference_type
                    -- Default: return null so it falls back to reference_type
                    ELSE NULL
                END AS actual_payment_type
            FROM
                journal_entry_lines AS jel
            JOIN
                journal_entries AS je ON jel.journal_entry_id = je.id
            WHERE
                jel.account_id = :account_id
                AND je.tenant_id = :tenant_id
                AND je.entry_date >= :start_date
                AND je.entry_date < :end_date_plus_one
        ";
        if (!$statusAny) {
            $sql .= " AND (je.status IS NULL OR je.status = 'posted')";
        }
        // include/exclude reference_type filters
        $params = [
            ':account_id' => $accountId,
            ':tenant_id' => $tenantId,
            ':start_date' => $startDate . ' 00:00:00',
            ':end_date_plus_one' => date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00',
        ];
        if ($costCenterId !== null) {
            $sql .= " AND je.cost_center_id = :cost_center_id";
            $params[':cost_center_id'] = $costCenterId;
        }
        if (!empty($includeTypes)) {
            $inPlaceholders = [];
            foreach ($includeTypes as $i => $t) { $inPlaceholders[] = ":inc_$i"; $params[":inc_$i"] = $t; }
            $sql .= " AND (je.reference_type IN (" . implode(',', $inPlaceholders) . "))";
        } elseif (!empty($excludeTypes)) {
            $exPlaceholders = [];
            foreach ($excludeTypes as $i => $t) { $exPlaceholders[] = ":exc_$i"; $params[":exc_$i"] = $t; }
            $sql .= " AND (je.reference_type NOT IN (" . implode(',', $exPlaceholders) . "))";
        }
        $sql .= " ORDER BY je.entry_date ASC, jel.id ASC";
        if ($limit !== null) {
            $sql .= " LIMIT :_limit";
            $params[':_limit'] = (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET :_offset";
                $params[':_offset'] = (int)$offset;
            }
        }
        $sql .= ";";

        try {
            $stmt = $this->db->prepare($sql);
            // ربط باراميترات limit/offset كأعداد صحيحة عند الحاجة
            foreach ($params as $k => $v) {
                if ($k === ':_limit' || $k === ':_offset') {
                    $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($k, $v);
                }
            }
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // في حال حدوث خطأ في الاستعلام نرجع هيكل فارغ آمن مع تسجيل الخطأ
            $this->logger->error('Error fetching account statement transactions', ['message' => $e->getMessage()]);
            return [
                'transactions' => [],
                'daily_balances' => [],
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'transaction_count' => 0,
            ];
        }
        
        // حساب الرصيد الجاري والملخص اليومي + اضافة transaction_type
        $runningBalance = $this->calculateOpeningBalance($accountId, $startDate, $tenantId, $statusAny, $includeTypes, $excludeTypes, $costCenterId);
        $dailyBalances = [];
        $currentDay = null;
        $dayTransactions = [];
        $dayStartBalance = $runningBalance;
        
        foreach ($transactions as &$transaction) {
            // Determine transaction type: prefer actual_payment_type if available
            // This fixes the issue where receipts/refunds show as 'sale' type
            $actualPaymentType = $transaction['actual_payment_type'] ?? null;
            
            // If SQL didn't return actual_payment_type, calculate it here
            if (!$actualPaymentType) {
                $refType = strtolower((string)($transaction['reference_type'] ?? ''));
                $credit = (float)($transaction['credit'] ?? 0);
                $debit = (float)($transaction['debit'] ?? 0);
                
                // Apply the same logic as SQL CASE
                if ($refType === 'sale' && $credit > 0) {
                    $actualPaymentType = 'receipt';
                } elseif ($refType === 'purchase' && $debit > 0) {
                    $actualPaymentType = 'payment';
                } elseif (in_array($refType, ['receipt', 'payment', 'refund', 'cash_voucher'])) {
                    $actualPaymentType = $refType;
                }
            }
            
            $refType = $actualPaymentType ? strtolower((string)$actualPaymentType) : strtolower((string)($transaction['reference_type'] ?? ''));
            $transaction['transaction_type'] = $refType ?: 'unknown';

            $transactionDate = $transaction['date'];
            
            // بداية يوم جديد
            if ($currentDay !== $transactionDate) {
                if ($currentDay !== null) {
                    // حفظ ملخص اليوم السابق
                    $dailyBalances[] = [
                        'date' => $currentDay,
                        'opening_balance' => $dayStartBalance,
                        'closing_balance' => $runningBalance,
                        'transaction_count' => count($dayTransactions),
                        'day_debit' => array_sum(array_column($dayTransactions, 'debit')),
                        'day_credit' => array_sum(array_column($dayTransactions, 'credit'))
                    ];
                }
                $currentDay = $transactionDate;
                $dayStartBalance = $runningBalance;
                $dayTransactions = [];
            }
            
            // حساب الرصيد الجاري
            $transaction['balance'] = $runningBalance + $transaction['debit'] - $transaction['credit'];
            
            // Determine balance nature (debit/credit)
            $balance = $transaction['balance'];
            if (abs($balance) < 0.01) {
                $transaction['balance_nature'] = 'zero';
            } elseif ($balance > 0) {
                $transaction['balance_nature'] = 'debit';
            } else {
                $transaction['balance_nature'] = 'credit';
            }
            
            $runningBalance = $transaction['balance'];
            $dayTransactions[] = $transaction;
        }
        
        // إضافة ملخص آخر يوم
        if ($currentDay !== null) {
            $dailyBalances[] = [
                'date' => $currentDay,
                'opening_balance' => $dayStartBalance,
                'closing_balance' => $runningBalance,
                'transaction_count' => count($dayTransactions),
                'day_debit' => array_sum(array_column($dayTransactions, 'debit')),
                'day_credit' => array_sum(array_column($dayTransactions, 'credit'))
            ];
        }
        
        return [
            'transactions' => $transactions,
            'daily_balances' => $dailyBalances,
            'total_debit' => (float)array_sum(array_column($transactions, 'debit')),
            'total_credit' => (float)array_sum(array_column($transactions, 'credit')),
            'transaction_count' => count($transactions)
        ];
}

    // قسم مرجعي: فواتير العميل خلال الفترة دون تأثير على الأرصدة
    private function getCustomerSalesOnly($customerId, $startDate, $endDate, $tenantId) {
        $sql = "
            SELECT 
                s.id,
                s.created_at AS date,
                s.invoice_number,
                s.status,
                s.total_amount,
                s.net_total_amount,
                s.tax_amount,
                s.discount_value,
                s.discount_type,
                s.branch_id,
                s.user_id,
                s.journal_entry_id,
                -- Calculate paid_amount from payments table, not from s.paid_amount
                -- s.paid_amount might not be updated correctly, so sum payments linked to this invoice
                COALESCE((
                    SELECT COALESCE(SUM(p.amount), 0)
                    FROM payments p
                    WHERE p.sale_id = s.id 
                      AND p.tenant_id = s.tenant_id
                      AND p.return_id IS NULL
                      AND p.is_draft = 0
                      AND p.status = 'completed'
                ), 0) AS calculated_paid_amount,
                -- ✅ CRITICAL: Add due_date calculation
                -- Default to +30 days from created_at
                DATE_ADD(s.created_at, INTERVAL 30 DAY) AS due_date,
                -- Calculate days overdue
                DATEDIFF(CURDATE(), DATE_ADD(s.created_at, INTERVAL 30 DAY)) AS days_overdue,
                (
                    SELECT COUNT(*) 
                    FROM sales_items si 
                    WHERE si.sale_id = s.id AND si.tenant_id = s.tenant_id
                ) AS items_count,
                (
                    SELECT GROUP_CONCAT(r.id) FROM returns r 
                    WHERE r.return_type = 'sale' 
                      AND r.sale_id = s.id 
                      AND r.tenant_id = s.tenant_id
                ) AS return_ids,
                COALESCE(SUM(rca.allocated_amount), 0) AS return_credits_applied
            FROM sales s
            LEFT JOIN return_credit_allocations rca ON rca.sale_id = s.id 
                AND rca.tenant_id = s.tenant_id
            WHERE s.tenant_id = :tenant_id 
              AND s.customer_id = :customer_id
              AND s.created_at >= :start_date AND s.created_at <= :end_date_full
            GROUP BY s.id
            ORDER BY s.created_at ASC, s.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':customer_id' => $customerId,
            ':start_date' => $startDate,
            ':end_date_full' => $endDate . ' 23:59:59',
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // طباعة مرجعية واضحة ولاحقة الربط مع القيود إن وجدت
        $items = array_map(function($r) {
            $net = isset($r['net_total_amount']) ? (float)$r['net_total_amount'] : 0.0;
            // Use calculated_paid_amount from query (sum of payments)
            // instead of s.paid_amount which might not be updated
            $paid = isset($r['calculated_paid_amount']) ? (float)$r['calculated_paid_amount'] : (isset($r['paid_amount']) ? (float)$r['paid_amount'] : 0.0);
            $returnCredits = isset($r['return_credits_applied']) ? (float)$r['return_credits_applied'] : 0.0;
            
            // الصيغة الصحيحة: outstanding = grand_total - paid_amount - return_credits_applied
            $grand_total = $net + ((float)($r['tax_amount'] ?? 0));
            $outstanding = max(0.0, $grand_total - $paid - $returnCredits);
            
            /**
             * STATUS NORMALIZATION LOGIC:
             * 
             * Case 1: Has returns + all credits applied + no actual payment
             *   → closed_by_return (settled by return credit allocations)
             *   → paid_amount stays 0 (no money exchanged)
             *   → outstanding = 0 (debt fully offset by returns)
             * 
             * Case 2: Outstanding cleared + actual payment received
             *   → paid (customer paid money)
             * 
             * Case 3: Outstanding exceeds 0 + some return credits
             *   → partial (partial settlement)
             * 
             * Case 4: Normal state
             *   → Use database status as-is
             */
            $status = $r['status'];
            $hasReturns = !empty($r['return_ids']);
            
            // Use the unified normalization function
            $status = $this->normalizeInvoiceStatus(
                $status,
                $paid,
                $returnCredits,
                $grand_total,
                $hasReturns
            );
            
            // Parse return_ids for return_group_id (use first return if multiple)
            $returnGroupId = null;
            if ($hasReturns) {
                $returnIds = explode(',', $r['return_ids']);
                if (!empty($returnIds)) {
                    $returnGroupId = (int)$returnIds[0];
                }
            }
            
            return [
                'id' => (int)$r['id'],
                'date' => $r['date'],
                'invoice_number' => $r['invoice_number'],
                'reference' => 'sale#' . $r['id'],
                'status' => $status,
                'total_amount' => isset($r['total_amount']) ? (float)$r['total_amount'] : 0.0,
                'net_total_amount' => $net,
                'discount_value' => isset($r['discount_value']) ? (float)$r['discount_value'] : 0.0,
                'discount_type' => $r['discount_type'] ?? null,
                'tax_amount' => isset($r['tax_amount']) ? (float)$r['tax_amount'] : 0.0,
                'paid_amount' => $paid,  // Calculated from payments table
                'outstanding' => $outstanding,  // Accurate based on actual payments
                'due_date' => isset($r['due_date']) ? $r['due_date'] : null,
                'days_overdue' => isset($r['days_overdue']) ? (int)$r['days_overdue'] : 0,
                'items_count' => isset($r['items_count']) ? (int)$r['items_count'] : 0,
                'has_returns' => !empty($r['return_ids']) ? true : false,
                'return_group_id' => $returnGroupId,
                'branch_id' => isset($r['branch_id']) ? (int)$r['branch_id'] : null,
                'user_id' => isset($r['user_id']) ? (int)$r['user_id'] : null,
                'has_journal' => !empty($r['journal_entry_id']),
                'journal_entry_id' => isset($r['journal_entry_id']) && $r['journal_entry_id'] ? (int)$r['journal_entry_id'] : null,
            ];
        }, $rows);

        return [
            'count' => count($items),
            'items' => $items,
            '_note' => 'Sales invoices only. Referenced payments (receipts/refunds) shown separately in "references" section.'
        ];
    }

    // مرجع موحد: فواتير مبيعات + مرتجعات مبيعات + إيصالات القبض للعميل خلال الفترة
    private function getCustomerReferences($customerId, $startDate, $endDate, $tenantId, $costCenterId = null) {
        // 1) فواتير المبيعات — مع جلب البيانات المطلوبة لتطبيع الـ status
        $sqlSales = "
            SELECT 
                s.id,
                s.created_at AS date,
                s.invoice_number,
                s.status,
                s.total_amount,
                s.net_total_amount,
                s.paid_amount,
                s.tax_amount,
                s.journal_entry_id,
                s.cost_center_id,
                (
                    SELECT COUNT(*) 
                    FROM returns r 
                    WHERE r.return_type = 'sale' 
                      AND r.sale_id = s.id 
                      AND r.tenant_id = s.tenant_id
                ) > 0 AS has_returns,
                COALESCE(SUM(rca.allocated_amount), 0) AS return_credits_applied
            FROM sales s
            LEFT JOIN return_credit_allocations rca ON rca.sale_id = s.id 
                AND rca.tenant_id = s.tenant_id
            WHERE s.tenant_id = :tenant_id 
              AND s.customer_id = :customer_id
              AND s.created_at >= :start_date AND s.created_at <= :end_date_full
            GROUP BY s.id
            ORDER BY s.created_at ASC, s.id ASC
        ";
        if ($costCenterId !== null) {
            $sqlSales .= " AND s.cost_center_id = :cost_center_id";
        }
        $stmtSales = $this->db->prepare($sqlSales);
        $salesParams = [
            ':tenant_id' => $tenantId,
            ':customer_id' => $customerId,
            ':start_date' => $startDate,
            ':end_date_full' => $endDate . ' 23:59:59',
        ];
        if ($costCenterId !== null) {
            $salesParams[':cost_center_id'] = $costCenterId;
        }
        $stmtSales->execute($salesParams);
        $sales = array_map(function($r) {
            // Normalize status using unified logic
            $net = isset($r['net_total_amount']) ? (float)$r['net_total_amount'] : 0.0;
            $paid = isset($r['paid_amount']) ? (float)$r['paid_amount'] : 0.0;
            $returnCredits = isset($r['return_credits_applied']) ? (float)$r['return_credits_applied'] : 0.0;
            $tax = isset($r['tax_amount']) ? (float)$r['tax_amount'] : 0.0;
            $grandTotal = $net + $tax;
            $hasReturns = (bool)$r['has_returns'];
            
            $normalizedStatus = $this->normalizeInvoiceStatus(
                $r['status'],
                $paid,
                $returnCredits,
                $grandTotal,
                $hasReturns
            );
            
            return [
                'id' => (int)$r['id'],
                'date' => $r['date'],
                'type' => 'sale',
                'invoice_number' => $r['invoice_number'],
                'reference' => 'sale#' . $r['id'],
                'status' => $normalizedStatus,  // ← استخدام الـ status المطبع
                'total_amount' => isset($r['total_amount']) ? (float)$r['total_amount'] : null,
                'net_total_amount' => isset($r['net_total_amount']) ? (float)$r['net_total_amount'] : null,
                'paid_amount' => isset($r['paid_amount']) ? (float)$r['paid_amount'] : null,
                'has_journal' => !empty($r['journal_entry_id']),
                'journal_entry_id' => $r['journal_entry_id'] ? (int)$r['journal_entry_id'] : null,
                'cost_center_id' => $r['cost_center_id'] ?? null,
            ];
        }, ($stmtSales->fetchAll(PDO::FETCH_ASSOC) ?: []));

        // 2) مرتجعات المبيعات
        $sqlReturns = "
            SELECT 
                r.id,
                r.created_at AS date,
                r.return_number,
                r.invoice_number,
                r.status,
                r.grand_total AS total_amount,
                r.journal_entry_id,
                EXISTS(
                    SELECT 1
                    FROM payments p
                    WHERE p.return_id = r.id
                      AND p.tenant_id = r.tenant_id
                      AND p.amount > 0.01
                ) AS has_refund
            FROM returns r
            WHERE r.tenant_id = :tenant_id 
              AND r.return_type = 'sale'
              AND r.customer_id = :customer_id
              AND r.created_at >= :start_date AND r.created_at <= :end_date_full
        ";
        $stmtReturns = $this->db->prepare($sqlReturns);
        $stmtReturns->execute([
            ':tenant_id' => $tenantId,
            ':customer_id' => $customerId,
            ':start_date' => $startDate,
            ':end_date_full' => $endDate . ' 23:59:59',
        ]);
        $returns = array_map(function($r) {
            /**
             * TRANSACTION SUBTYPE LOGIC (Dynamic v3 - Optimized)
             * 
             * Check if there's an actual refund payment issued for this return:
             * - sales_return_only: Return issued, no refund payment yet (has_refund = 0)
             * - sales_return_refund: Return + refund payment issued (has_refund = 1)
             */
            $subtype = 'sales_return_only';
            $hasRefund = isset($r['has_refund']) ? (int)$r['has_refund'] : 0;
            
            if ($hasRefund > 0) {
                $subtype = 'sales_return_refund';
            }
            
            // Map subtype to human-readable label
            $typeLabel = 'مرتجع بيع';
            if ($subtype === 'sales_return_refund') {
                $typeLabel = 'استرداد مرتجع';
            }
            
            return [
                'id' => (int)$r['id'],
                'date' => $r['date'],
                'type' => 'sales_return',
                'type_label' => $typeLabel,
                'invoice_number' => $r['invoice_number'] ?: $r['return_number'],
                'reference' => 'sales_return#' . $r['id'],
                'status' => $r['status'] ?? null,
                'total_amount' => isset($r['total_amount']) ? (float)$r['total_amount'] : null,
                'net_total_amount' => null,
                'paid_amount' => null,
                'has_journal' => !empty($r['journal_entry_id']),
                'journal_entry_id' => isset($r['journal_entry_id']) && $r['journal_entry_id'] ? (int)$r['journal_entry_id'] : null,
                'transaction_subtype' => $subtype,
                'return_group_id' => (int)$r['id'], // Link to itself as group identifier
            ];
        }, ($stmtReturns->fetchAll(PDO::FETCH_ASSOC) ?: []));

        // 3) إيصالات القبض والصرف (payments) - differentiate between receipts and refunds
        $sqlReceipts = "
            SELECT 
                p.id,
                p.created_at AS date,
                p.reference_number,
                p.amount,
                p.journal_entry_id,
                p.sale_id,
                p.return_id,
                CASE 
                    WHEN p.return_id IS NOT NULL THEN 'refund'
                    WHEN p.sale_id IS NOT NULL THEN 'receipt'
                    ELSE 'payment'
                END AS payment_type,
                r.refund_amount,
                r.refund_method,
                s.invoice_number AS related_sale_invoice_number
            FROM payments p
            LEFT JOIN returns r ON p.return_id = r.id AND p.tenant_id = r.tenant_id
            LEFT JOIN sales s ON p.sale_id = s.id AND p.tenant_id = s.tenant_id
            WHERE p.tenant_id = :tenant_id 
              AND p.customer_id = :customer_id
              AND p.created_at >= :start_date AND p.created_at <= :end_date_full
              AND p.is_draft = 0
              AND p.status = 'completed'
        ";
        $stmtReceipts = $this->db->prepare($sqlReceipts);
        $stmtReceipts->execute([
            ':tenant_id' => $tenantId,
            ':customer_id' => $customerId,
            ':start_date' => $startDate,
            ':end_date_full' => $endDate . ' 23:59:59',
        ]);
        $receipts = array_map(function($r) {
            $amount = isset($r['amount']) ? (float)$r['amount'] : 0.0;
            $paymentType = $r['payment_type'] ?? 'receipt';
            $referencePrefix = $paymentType === 'refund' ? 'refund' : 'receipt';
            
            // Only create refund record if amount > 0
            if ($paymentType === 'refund' && $amount < 0.01) {
                return null; // Skip empty refunds
            }
            
            /**
             * CRITICAL FIX: Determine transaction_subtype based on return's refund_amount and refund_method
             * 
             * NOT all refunds are sales_return_refund!
             * - If return.refund_amount < 0.01 → sales_return_only (no actual refund issued)
             * - If return.refund_amount >= 0.01 + refund_method = 'cash' → sales_return_refund
             * - If return.refund_amount >= 0.01 + refund_method = 'bank_transfer' → sales_return_bank_refund
             */
            $transactionSubtype = null;
            if ($paymentType === 'refund') {
                $refundAmount = isset($r['refund_amount']) ? (float)$r['refund_amount'] : 0;
                $refundMethod = isset($r['refund_method']) ? $r['refund_method'] : null;
                
                if ($refundAmount >= 0.01) {
                    if ($refundMethod === 'cash') {
                        $transactionSubtype = 'sales_return_refund';
                    } elseif ($refundMethod === 'bank_transfer') {
                        $transactionSubtype = 'sales_return_bank_refund';
                    }
                }
                // If refundAmount < 0.01, $transactionSubtype stays null (sales_return_only)
            }
            
            // Map payment type and subtype to human-readable labels
            $paymentTypeLabel = $paymentType === 'refund' ? 'سند صرف' : 'سند قبض';
            if ($paymentType === 'refund' && $transactionSubtype === 'sales_return_refund') {
                $paymentTypeLabel = 'استرجاع نقدي للعميل';
            }
            
            // Use related_sale_invoice_number if available (for receipts linked to sales)
            // Otherwise use reference_number (payment reference), or null if neither available
            $invoiceNumber = !empty($r['related_sale_invoice_number']) 
                ? $r['related_sale_invoice_number'] 
                : ($r['reference_number'] ?? null);
            
            return [
                'id' => (int)$r['id'],
                'date' => $r['date'],
                'type' => $paymentType,
                'type_label' => $paymentTypeLabel,
                'invoice_number' => $invoiceNumber,
                'reference' => $referencePrefix . '#' . $r['id'],
                'status' => null,
                'total_amount' => $amount,
                'net_total_amount' => $amount,
                'paid_amount' => $amount,
                'has_journal' => !empty($r['journal_entry_id']),
                'journal_entry_id' => isset($r['journal_entry_id']) && $r['journal_entry_id'] ? (int)$r['journal_entry_id'] : null,
                'transaction_subtype' => $transactionSubtype,
                'return_group_id' => $paymentType === 'refund' && $r['return_id'] ? (int)$r['return_id'] : null,
            ];
        }, ($stmtReceipts->fetchAll(PDO::FETCH_ASSOC) ?: []));
        
        // Filter out null entries (empty refunds)
        $receipts = array_filter($receipts, fn($item) => $item !== null);

        // دمج وفرز حسب التاريخ
        $all = array_merge($sales, $returns, $receipts);
        usort($all, function($a, $b) {
            if ($a['date'] === $b['date']) { return 0; }
            return ($a['date'] < $b['date']) ? -1 : 1;
        });

        return [
            'count' => count($all),
            'items' => $all,
        ];
    }

    // مرجع موحد للمورد: فواتير مشتريات + مرتجعات مشتريات + مدفوعات المورد خلال الفترة
    private function getSupplierReferences($supplierId, $startDate, $endDate, $tenantId) {
        // 1) فواتير المشتريات — مع جلب البيانات المطلوبة لتطبيع الـ status
        $sqlPurchases = "
            SELECT 
                p.id,
                p.invoice_date AS date,
                p.invoice_number,
                p.status,
                p.total_amount,
                p.net_total_amount,
                p.tax_amount,
                p.discount_value,
                p.paid_amount,
                p.journal_entry_id,
                (
                    SELECT COUNT(*) 
                    FROM returns r 
                    WHERE r.return_type = 'purchase' 
                      AND r.purchase_id = p.id 
                      AND r.tenant_id = p.tenant_id
                ) > 0 AS has_returns,
                COALESCE(SUM(rca.allocated_amount), 0) AS return_credits_applied
            FROM purchases p
            LEFT JOIN return_credit_allocations rca ON rca.purchase_id = p.id 
                AND rca.tenant_id = p.tenant_id
            WHERE p.tenant_id = :tenant_id
              AND p.supplier_id = :supplier_id
              AND p.invoice_date >= :start_date AND p.invoice_date <= :end_date_full
            GROUP BY p.id
        ";
        $stmtPurchases = $this->db->prepare($sqlPurchases);
        $stmtPurchases->execute([
            ':tenant_id' => $tenantId,
            ':supplier_id' => $supplierId,
            ':start_date' => $startDate,
            ':end_date_full' => $endDate . ' 23:59:59',
        ]);
        $purchases = array_map(function($r) {
            // Normalize status using unified logic
            $net = isset($r['net_total_amount']) ? (float)$r['net_total_amount'] : 0.0;
            $paid = isset($r['paid_amount']) ? (float)$r['paid_amount'] : 0.0;
            $returnCredits = isset($r['return_credits_applied']) ? (float)$r['return_credits_applied'] : 0.0;
            $tax = isset($r['tax_amount']) ? (float)$r['tax_amount'] : 0.0;
            $grandTotal = $net + $tax;
            $hasReturns = (bool)$r['has_returns'];
            
            $normalizedStatus = $this->normalizeInvoiceStatus(
                $r['status'],
                $paid,
                $returnCredits,
                $grandTotal,
                $hasReturns
            );
            
            return [
                'id' => (int)$r['id'],
                'date' => $r['date'],
                'type' => 'purchase',
                'invoice_number' => $r['invoice_number'],
                'reference' => 'purchase#' . $r['id'],
                'status' => $normalizedStatus,  // ← استخدام الـ status المطبع
                'total_amount' => isset($r['total_amount']) ? (float)$r['total_amount'] : null,
                'net_total_amount' => $net,
                'paid_amount' => isset($r['paid_amount']) ? (float)$r['paid_amount'] : null,
                'has_journal' => !empty($r['journal_entry_id']),
                'journal_entry_id' => isset($r['journal_entry_id']) && $r['journal_entry_id'] ? (int)$r['journal_entry_id'] : null,
            ];
        }, ($stmtPurchases->fetchAll(PDO::FETCH_ASSOC) ?: []));

        // 2) مرتجعات المشتريات
        $sqlPurchaseReturns = "
            SELECT 
                r.id,
                r.created_at AS date,
                r.return_number,
                r.invoice_number,
                r.status,
                r.grand_total AS total_amount,
                r.journal_entry_id,
                r.refund_amount,
                r.refund_method
            FROM returns r
            WHERE r.tenant_id = :tenant_id 
              AND r.return_type = 'purchase'
              AND r.supplier_id = :supplier_id
              AND r.created_at >= :start_date AND r.created_at <= :end_date_full
        ";
        $stmtPR = $this->db->prepare($sqlPurchaseReturns);
        $stmtPR->execute([
            ':tenant_id' => $tenantId,
            ':supplier_id' => $supplierId,
            ':start_date' => $startDate,
            ':end_date_full' => $endDate . ' 23:59:59',
        ]);
        $purchaseReturns = array_map(function($r) {
            /**
             * PURCHASE RETURN SUBTYPE LOGIC (Dynamic v2)
             * 
             * Using refund_amount and refund_method columns to determine:
             * - purchase_return_only: Return issued, no payment yet
             * - purchase_return_refund: Return + cash payment (to supplier)
             * - purchase_return_bank_refund: Return + bank transfer payment
             */
            $subtype = 'purchase_return_only';
            
            $refundAmount = isset($r['refund_amount']) ? (float)$r['refund_amount'] : 0;
            $refundMethod = isset($r['refund_method']) ? $r['refund_method'] : null;
            
            if ($refundAmount >= 0.01) {
                if ($refundMethod === 'cash') {
                    $subtype = 'purchase_return_refund';
                } elseif ($refundMethod === 'bank_transfer') {
                    $subtype = 'purchase_return_bank_refund';
                }
            }
            
            return [
                'id' => (int)$r['id'],
                'date' => $r['date'],
                'type' => 'purchase_return',
                'invoice_number' => $r['invoice_number'] ?: $r['return_number'],
                'reference' => 'purchase_return#' . $r['id'],
                'status' => $r['status'] ?? null,
                'total_amount' => isset($r['total_amount']) ? (float)$r['total_amount'] : null,
                'net_total_amount' => null,
                'paid_amount' => null,
                'has_journal' => !empty($r['journal_entry_id']),
                'journal_entry_id' => isset($r['journal_entry_id']) && $r['journal_entry_id'] ? (int)$r['journal_entry_id'] : null,
                'transaction_subtype' => $subtype,
                'return_group_id' => (int)$r['id'],
            ];
        }, ($stmtPR->fetchAll(PDO::FETCH_ASSOC) ?: []));

        // 3) مدفوعات المورد: استخدم supplier_id مباشرة من جدول payments - differentiate between payments and refunds
        $sqlSupplierPayments = "
            SELECT 
                pay.id,
                pay.created_at AS date,
                pay.reference_number,
                pay.amount,
                pay.journal_entry_id,
                pay.purchase_id,
                pay.return_id,
                CASE 
                    WHEN pay.return_id IS NOT NULL THEN 'refund'
                    WHEN pay.purchase_id IS NOT NULL THEN 'payment'
                    ELSE 'payment'
                END AS payment_type
            FROM payments pay
            WHERE pay.tenant_id = :tenant_id
              AND pay.supplier_id = :supplier_id
              AND pay.created_at >= :start_date AND pay.created_at <= :end_date_full
              AND pay.is_draft = 0
              AND pay.status = 'completed'
        ";
        $stmtSPay = $this->db->prepare($sqlSupplierPayments);
        $stmtSPay->execute([
            ':tenant_id' => $tenantId,
            ':supplier_id' => $supplierId,
            ':start_date' => $startDate,
            ':end_date_full' => $endDate . ' 23:59:59',
        ]);
        $supplierPayments = array_map(function($r) {
            $amount = isset($r['amount']) ? (float)$r['amount'] : 0.0;
            $paymentType = $r['payment_type'] ?? 'payment';
            $referencePrefix = $paymentType === 'refund' ? 'refund' : 'payment';
            
            // Only create refund record if amount > 0
            if ($paymentType === 'refund' && $amount < 0.01) {
                return null; // Skip empty refunds
            }
            
            return [
                'id' => (int)$r['id'],
                'date' => $r['date'],
                'type' => $paymentType,
                'invoice_number' => $r['reference_number'],
                'reference' => $referencePrefix . '#' . $r['id'],
                'status' => null,
                'total_amount' => $amount,
                'net_total_amount' => $amount,
                'paid_amount' => $amount,
                'has_journal' => !empty($r['journal_entry_id']),
                'journal_entry_id' => isset($r['journal_entry_id']) && $r['journal_entry_id'] ? (int)$r['journal_entry_id'] : null,
                'transaction_subtype' => $paymentType === 'refund' ? 'purchase_return_refund' : null,
                'return_group_id' => $paymentType === 'refund' && $r['return_id'] ? (int)$r['return_id'] : null,
            ];
        }, ($stmtSPay->fetchAll(PDO::FETCH_ASSOC) ?: []));
        
        // Filter out null entries (empty refunds)
        $supplierPayments = array_filter($supplierPayments, fn($item) => $item !== null);

        // دمج وفرز
        $all = array_merge($purchases, $purchaseReturns, $supplierPayments);
        usort($all, function($a, $b) {
            if ($a['date'] === $b['date']) { return 0; }
            return ($a['date'] < $b['date']) ? -1 : 1;
        });

        return [
            'count' => count($all),
            'items' => $all,
        ];
    }

    // جلب جميع الحسابات النشطة للـ tenant الحالي
        public function getAccounts(Request $request, Response $response, array $args = []): Response
        {
            try {
                $tenantId = $this->extractTenantId($request);
                if (!$tenantId) {
                    return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
                }

                // التعديل هنا: استخدام دالة SUBSTRING لجلب أول رقمين من عمود 'code'
                $stmt = $this->db->prepare("
                    SELECT 
                        id, 
                        name, 
                        code,
                        SUBSTRING(code, 1, 2) AS account_group_code
                    FROM 
                        accounts 
                    WHERE 
                        tenant_id = :tenant_id AND is_active = 1 
                    ORDER BY 
                        code ASC
                ");

                $stmt->execute(['tenant_id' => $tenantId]);
                $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                return $this->successResponse($response, $accounts);
            } catch (\Throwable $e) {
                $this->logger->error('Error fetching accounts', ['error' => $e->getMessage(), 'tenant_id' => $tenantId ?? null]);
                return $this->errorResponse($response, 'فشل في جلب الحسابات', 500);
            }
        }

    // Helpers for enterprise usage
    private function parseTypes($csv) {
        if (is_array($csv)) {
            return array_values(array_filter(array_map('trim', $csv)));
        }
        return array_values(array_filter(array_map('trim', explode(',', (string)$csv))));
    }

    private function resolveAccountIdByCode($code, $tenantId) {
        $cacheKey = $tenantId . '_' . $code;
        if (isset($this->accountCache[$cacheKey])) {
            return $this->accountCache[$cacheKey];
        }

        // Delegates to AccountingService for consistent resolution (tenant-specific → global fallback)
        $id = $this->accounting->getAccountByCode((int) $tenantId, (string) $code);

        $this->accountCache[$cacheKey] = $id;
        return $id;
    }

    private function fillDailyGaps(array $dailyBalances, $startDate, $endDate) {
        // Map existing by date for O(1) lookup
        $byDate = [];
        foreach ($dailyBalances as $row) {
            if (isset($row['date'])) {
                $byDate[$row['date']] = $row;
            }
        }

        $result = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        // Determine initial opening when no rows exist
        $initialOpening = 0.0;
        if (!empty($dailyBalances)) {
            $initialOpening = isset($dailyBalances[0]['opening_balance']) ? (float)$dailyBalances[0]['opening_balance'] : 0.0;
        }

        while ($current <= $end) {
            $d = $current->format('Y-m-d');
            if (isset($byDate[$d])) {
                $result[] = $byDate[$d];
            } else {
                $prev = end($result);
                $opening = $prev ? (float)$prev['closing_balance'] : $initialOpening;
                $result[] = [
                    'date' => $d,
                    'opening_balance' => $opening,
                    'closing_balance' => $opening,
                    'transaction_count' => 0,
                    'day_debit' => 0.0,
                    'day_credit' => 0.0,
                ];
            }
            $current->modify('+1 day');
        }
        return $result;
    }

    // --- Localization helpers -------------------------------------------------
    private function referenceLabel(?string $type, string $locale = 'ar'): string {
        return LabelService::refLabel($type, $locale);
    }

    private function statusLabel(?string $code, string $locale = 'ar'): string {
        return LabelService::statusLabel($code, $locale);
    }

    // GET: grouped accounts (tenant vs global)
    public function getGroupedAccounts(Request $request, Response $response): Response {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID)', 403);
            }

            // Get tenant-specific accounts
            $tenantStmt = $this->db->prepare(
                "SELECT id, code, name, type FROM accounts 
                 WHERE tenant_id = ? ORDER BY COALESCE(code, name) ASC"
            );
            $tenantStmt->execute([$tenantId]);
            $tenantAccounts = $tenantStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Get global accounts
            $globalStmt = $this->db->prepare(
                "SELECT id, code, name, type FROM accounts 
                 WHERE tenant_id IS NULL ORDER BY COALESCE(code, name) ASC"
            );
            $globalStmt->execute();
            $globalAccounts = $globalStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $data = [
                'tenant_accounts' => $tenantAccounts,
                'global_accounts' => $globalAccounts,
            ];
            return $this->successResponse($response, $data);
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching grouped accounts', ['error' => $e->getMessage(), 'tenant_id' => $tenantId ?? null]);
            return $this->errorResponse($response, 'فشل في جلب الحسابات المجمعة', 500);
        }
    }
}