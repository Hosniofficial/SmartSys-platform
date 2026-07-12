<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use Exception;
use App\Services\MonologHandler;
use DateTime;

/**
 * BalanceCalculationService
 *
 * Unified service for all balance calculations across the system.
 * Consolidates 4 different balance calculation patterns into single source of truth.
 *
 * Patterns unified:
 * 1. Journal Entry Balance (Debit - Credit) - for parties (customers, suppliers)
 * 2. Accounts Table Balance (debit_balance - credit_balance) - account master balance
 * 3. Transaction Running Balance - for transaction history
 * 4. Amount Due Balance (total - paid) - for documents
 */
class BalanceCalculationService
{
    private $pdo;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->logger = MonologHandler::getInstance('balance_calculation');
    }

    /**
     * Get account balance using journal entry lines for a specific account
     *
     * Used by: CustomersHandler::getCustomers(), SuppliersHandler::getSuppliers(), CashVouchersHandler
     *
     * @param int $accountId - Account ID
     * @param int $tenantId - Tenant ID
     * @param string $type - 'customer' (debit-credit) or 'supplier' (credit-debit)
     * @param DateTime|null $asOf - Optional: Calculate balance as of specific accounting date
     * @return float - Account balance
     */
    public function getAccountBalanceFromJournalEntries(
        int $accountId,
        int $tenantId,
        string $type = 'customer',
        ?DateTime $asOf = null
    ): float {
        try {
            // Determine calculation direction based on type
            if ($type === 'supplier') {
                // For suppliers: credit_amount - debit_amount
                $calculation = 'SUM(jel.credit_amount - jel.debit_amount)';
            } else {
                // For customers: debit_amount - credit_amount
                $calculation = 'SUM(jel.debit_amount - jel.credit_amount)';
            }

            // Build date filter if provided (uses accounting entry_date)
            $dateFilter = '';
            $params = [$accountId, $tenantId];

            if ($asOf && $asOf instanceof DateTime) {
                // Balance as of date: all entries BEFORE the next day at midnight
                $nextDay = (clone $asOf)->modify('+1 day')->format('Y-m-d');
                $dateFilter = ' AND je.entry_date < ?';
                $params[] = $nextDay . ' 00:00:00';
            }

            $sql = "
                SELECT COALESCE({$calculation}, 0) AS balance
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE jel.account_id = ?
                  AND je.tenant_id = ?
                  AND (je.status IS NULL OR je.status = 'posted')
                  {$dateFilter}
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $balance = (float) $stmt->fetchColumn();

            return $balance;

        } catch (PDOException $e) {
            $this->logger->error('Failed to calculate account balance from journal entries', [
                'account_id' => $accountId,
                'tenant_id' => $tenantId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Get account balance from accounts table (stored debit_balance and credit_balance fields)
     *
     * Used by: ReturnsHandler::create() - for faster lookup from normalized accounts table
     *
     * @param int $accountId - Account ID
     * @param int $tenantId - Tenant ID
     * @param string $type - 'customer' (debit-credit) or 'supplier' (credit-debit)
     * @return float - Account balance from stored fields
     */
    public function getAccountBalanceFromTable(
        int $accountId,
        int $tenantId,
        string $type = 'customer'
    ): float {
        try {
            // Determine calculation direction based on type
            if ($type === 'supplier') {
                // For suppliers: credit_balance - debit_balance
                $calculation = 'COALESCE(credit_balance, 0) - COALESCE(debit_balance, 0)';
            } else {
                // For customers: debit_balance - credit_balance
                $calculation = 'COALESCE(debit_balance, 0) - COALESCE(credit_balance, 0)';
            }

            $sql = "
                SELECT {$calculation} AS balance
                FROM accounts
                WHERE id = ? AND tenant_id = ?
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$accountId, $tenantId]);
            $balance = (float) $stmt->fetchColumn();

            return $balance;

        } catch (PDOException $e) {
            $this->logger->error('Failed to calculate account balance from table', [
                'account_id' => $accountId,
                'tenant_id' => $tenantId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Get running balance for transaction history (iterative calculation)
     *
     * Used by: CustomersHandler::getTransactions(), SuppliersHandler::getTransactions()
     * Calculates cumulative balance for each transaction in chronological order
     *
     * @param int $accountId - Account ID
     * @param int $tenantId - Tenant ID
     * @param string $type - 'customer' (debit-credit) or 'supplier' (credit-debit)
     * @return array - Array of [transaction_data, running_balance]
     */
    public function getTransactionHistoryWithRunningBalance(
        int $accountId,
        int $tenantId,
        string $type = 'customer'
    ): array {
        try {
            // Get all transactions for account in chronological order
            $sql = "
                SELECT jel.id, jel.journal_entry_id, jel.debit_amount, jel.credit_amount, 
                       jel.created_at, je.reference_type, je.reference_id, je.description
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE jel.account_id = ? AND jel.tenant_id = ?
                ORDER BY jel.created_at ASC, jel.id ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$accountId, $tenantId]);
            $allTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Calculate running balance
            $currentBalance = 0.0;
            $transactionsWithBalance = [];

            foreach ($allTransactions as $transaction) {
                // Calculate balance change based on type
                if ($type === 'supplier') {
                    $currentBalance += ((float)$transaction['credit_amount'] - (float)$transaction['debit_amount']);
                } else {
                    $currentBalance += ((float)$transaction['debit_amount'] - (float)$transaction['credit_amount']);
                }

                $transaction['running_balance'] = $currentBalance;
                $transactionsWithBalance[] = $transaction;
            }

            return $transactionsWithBalance;

        } catch (PDOException $e) {
            $this->logger->error('Failed to get transaction history with running balance', [
                'account_id' => $accountId,
                'tenant_id' => $tenantId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get amount due balance for documents (total amount - paid amount)
     *
     * Used by: PurchasesHandler for purchase balance calculations
     * Calculates outstanding/due balance for transaction documents
     *
     * @param string $documentType - 'purchase', 'sale', or other document type
     * @param int $documentId - Document ID (purchase_id, sale_id, etc.)
     * @param int $tenantId - Tenant ID
     * @return float - Amount due (total - paid)
     */
    public function getAmountDueBalance(
        string $documentType,
        int $documentId,
        int $tenantId
    ): float {
        try {
            $documentType = strtolower($documentType);

            // Map document types to appropriate tables and columns
            $mapping = [
                'purchase' => [
                    'table' => 'purchases',
                    'totalCol' => 'total_amount',
                    'paidCol' => 'paid_amount'
                ],
                'sale' => [
                    'table' => 'sales',
                    'totalCol' => 'net_total_amount',
                    'paidCol' => 'paid_amount'
                ],
                'sales_return' => [
                    'table' => 'returns',
                    'totalCol' => 'grand_total',
                    'paidCol' => 'paid_amount',
                    'returnTypeFilter' => 'sale'
                ],
                'purchase_return' => [
                    'table' => 'returns',
                    'totalCol' => 'grand_total',
                    'paidCol' => 'paid_amount',
                    'returnTypeFilter' => 'purchase'
                ]
            ];

            if (!isset($mapping[$documentType])) {
                $this->logger->warning('Unknown document type for amount due calculation', [
                    'document_type' => $documentType
                ]);
                return 0.0;
            }

            $config = $mapping[$documentType];
            $table = $config['table'];
            $totalCol = $config['totalCol'];
            $paidCol = $config['paidCol'];

            $sql = "
                SELECT COALESCE({$totalCol}, 0) - COALESCE({$paidCol}, 0) AS amount_due
                FROM {$table}
                WHERE id = ? AND tenant_id = ?";

            // Add return_type filter for returns table
            if (!empty($config['returnTypeFilter'])) {
                $sql .= " AND return_type = ?";
                $params = [$documentId, $tenantId, $config['returnTypeFilter']];
            } else {
                $params = [$documentId, $tenantId];
            }

            $sql .= " LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $amountDue = (float) $stmt->fetchColumn();

            return max(0.0, $amountDue); // Never negative

        } catch (PDOException $e) {
            $this->logger->error('Failed to calculate amount due balance', [
                'document_type' => $documentType,
                'document_id' => $documentId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Get aggregated balance due for multiple documents
     *
     * Used by: SummaryHandler, ReportingHandler - for balance summaries
     * Sums up outstanding amounts across multiple documents with filtering
     *
     * @param string $documentType - 'purchase', 'sale', etc.
     * @param int $tenantId - Tenant ID
     * @param array $filters - Optional filters ['supplier_id'=>X, 'status'=>'unpaid', 'date_from'=>'2026-01-01']
     * @return float - Total amount due across filtered documents
     */
    public function getAggregatedAmountDue(
        string $documentType,
        int $tenantId,
        array $filters = []
    ): float {
        try {
            $documentType = strtolower($documentType);

            // Map document types
            $mapping = [
                'purchase' => [
                    'table' => 'purchases',
                    'totalCol' => 'total_amount',
                    'paidCol' => 'paid_amount',
                    'partyCol' => 'supplier_id'
                ],
                'sale' => [
                    'table' => 'sales',
                    'totalCol' => 'net_total_amount',
                    'paidCol' => 'paid_amount',
                    'partyCol' => 'customer_id'
                ]
            ];

            if (!isset($mapping[$documentType])) {
                return 0.0;
            }

            $config = $mapping[$documentType];
            $table = $config['table'];
            $totalCol = $config['totalCol'];
            $paidCol = $config['paidCol'];

            $sql = "
                SELECT COALESCE(SUM({$totalCol} - {$paidCol}), 0) AS total_due
                FROM {$table}
                WHERE tenant_id = ?
            ";

            $params = [$tenantId];

            // Add optional filters
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['party_id'])) {
                $partyCol = $config['partyCol'];
                $sql .= " AND {$partyCol} = ?";
                $params[] = $filters['party_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND created_at <= ?";
                $params[] = $filters['date_to'];
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $totalDue = (float) $stmt->fetchColumn();

            return max(0.0, $totalDue);

        } catch (PDOException $e) {
            $this->logger->error('Failed to calculate aggregated amount due', [
                'document_type' => $documentType,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Unified balance getter - auto-detects best method based on context
     *
     * Convenience method that intelligently selects the best calculation method
     * 1. For parties with account_id → uses journal entry method (most accurate)
     * 2. Falls back to accounts table if needed (when journal entries not available)
     *
     * @param int $accountId - Account ID
     * @param int $tenantId - Tenant ID
     * @param string $type - 'customer', 'supplier', or document type
     * @return float - Best-effort balance calculation
     */
    public function getBalance(
        int $accountId,
        int $tenantId,
        string $type = 'customer'
    ): float {
        try {
            // Try journal entry method first (most accurate)
            $balance = $this->getAccountBalanceFromJournalEntries($accountId, $tenantId, $type);

            // If journal entries are available, return that
            if ($balance !== 0.0) {
                return $balance;
            }

            // Fallback to accounts table method
            return $this->getAccountBalanceFromTable($accountId, $tenantId, $type);

        } catch (Exception $e) {
            $this->logger->error('Error in unified balance getter', [
                'account_id' => $accountId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Get amount due for batch of documents (optimized for list views)
     *
     * Used by: List views, Summary reports that need multiple document balances
     * Returns array of [document_id => amount_due]
     *
     * @param string $documentType - 'purchase', 'sale', 'sales_return', 'purchase_return'
     * @param int $tenantId - Tenant ID
     * @param array $documentIds - Array of document IDs to fetch amounts for
     * @return array - Associative array [doc_id => amount_due]
     */
    public function getAmountDueBatch(
        string $documentType,
        int $tenantId,
        array $documentIds
    ): array {
        try {
            if (empty($documentIds)) {
                return [];
            }

            $documentType = strtolower($documentType);

            // Map document types
            $mapping = [
                'purchase' => [
                    'table' => 'purchases',
                    'idCol' => 'id',
                    'totalCol' => 'total_amount',
                    'paidCol' => 'paid_amount'
                ],
                'sale' => [
                    'table' => 'sales',
                    'idCol' => 'id',
                    'totalCol' => 'net_total_amount',
                    'paidCol' => 'paid_amount'
                ],
                'sales_return' => [
                    'table' => 'returns',
                    'idCol' => 'id',
                    'totalCol' => 'grand_total',
                    'paidCol' => 'paid_amount',
                    'returnTypeFilter' => 'sale'
                ],
                'purchase_return' => [
                    'table' => 'returns',
                    'idCol' => 'id',
                    'totalCol' => 'grand_total',
                    'paidCol' => 'paid_amount',
                    'returnTypeFilter' => 'purchase'
                ]
            ];

            if (!isset($mapping[$documentType])) {
                return [];
            }

            $config = $mapping[$documentType];
            $table = $config['table'];
            $idCol = $config['idCol'];
            $totalCol = $config['totalCol'];
            $paidCol = $config['paidCol'];

            // Build placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($documentIds), '?'));

            $sql = "
                SELECT {$idCol}, COALESCE({$totalCol}, 0) - COALESCE({$paidCol}, 0) AS amount_due
                FROM {$table}
                WHERE {$idCol} IN ({$placeholders}) AND tenant_id = ?";

            // Add return_type filter for returns table
            if (!empty($config['returnTypeFilter'])) {
                $sql .= " AND return_type = ?";
                $params = array_merge($documentIds, [$tenantId, $config['returnTypeFilter']]);
            } else {
                $params = array_merge($documentIds, [$tenantId]);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row[$idCol]] = max(0.0, (float)$row['amount_due']);
            }

            return $results;

        } catch (PDOException $e) {
            $this->logger->error('Failed to get batch amount due', [
                'document_type' => $documentType,
                'tenant_id' => $tenantId,
                'count' => count($documentIds),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all amounts due by customer across all unpaid documents
     *
     * Aggregates outstanding amounts for all sales/returns
     * Used by: Customer balance summaries, Risk assessment
     *
     * @param int $customerId - Customer ID
     * @param int $tenantId - Tenant ID
     * @param bool $includeReturns - Include sales returns in calculation
     * @return float - Total amount due from customer
     */
    public function getAllCustomerAmountsDue(
        int $customerId,
        int $tenantId,
        bool $includeReturns = true
    ): float {
        try {
            $sql = "
                SELECT COALESCE(SUM(net_total_amount - paid_amount), 0) AS total_due
                FROM sales
                WHERE customer_id = ? AND tenant_id = ?
            ";

            $params = [$customerId, $tenantId];

            // Get amounts from sales
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $due = (float)$stmt->fetchColumn();

            // Add amounts from returns if requested
            if ($includeReturns) {
                $sqlReturns = "
                    SELECT COALESCE(SUM(grand_total - paid_amount), 0) AS total_due
                    FROM returns
                    WHERE customer_id = ? AND tenant_id = ? AND return_type = 'sale'
                ";
                $stmtReturns = $this->pdo->prepare($sqlReturns);
                $stmtReturns->execute($params);
                $due += (float)$stmtReturns->fetchColumn();
            }

            return max(0.0, $due);

        } catch (PDOException $e) {
            $this->logger->error('Failed to get customer amount due', [
                'customer_id' => $customerId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Get all amounts due by supplier across all unpaid documents
     *
     * Aggregates outstanding payable amounts for all purchases/returns
     * Used by: Supplier balance summaries, Payable tracking
     *
     * @param int $supplierId - Supplier ID
     * @param int $tenantId - Tenant ID
     * @param bool $includeReturns - Include purchase returns in calculation
     * @return float - Total amount due to supplier
     */
    public function getAllSupplierAmountsDue(
        int $supplierId,
        int $tenantId,
        bool $includeReturns = true
    ): float {
        try {
            $sql = "
                SELECT COALESCE(SUM(total_amount - paid_amount), 0) AS total_due
                FROM purchases
                WHERE supplier_id = ? AND tenant_id = ?
            ";

            $params = [$supplierId, $tenantId];

            // Get amounts from purchases
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $due = (float)$stmt->fetchColumn();

            // Add amounts from returns if requested
            if ($includeReturns) {
                $sqlReturns = "
                    SELECT COALESCE(SUM(grand_total - paid_amount), 0) AS total_due
                    FROM returns
                    WHERE supplier_id = ? AND tenant_id = ? AND return_type = 'purchase'
                ";
                $stmtReturns = $this->pdo->prepare($sqlReturns);
                $stmtReturns->execute($params);
                $due += (float)$stmtReturns->fetchColumn();
            }

            return max(0.0, $due);

        } catch (PDOException $e) {
            $this->logger->error('Failed to get supplier amount due', [
                'supplier_id' => $supplierId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }
}
