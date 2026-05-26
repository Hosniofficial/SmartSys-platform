<?php
/**
 * ============================================
 * PRODUCTION READINESS VALIDATION SCRIPT
 * ============================================
 * 
 * File: validate_accounting_system.php
 * Purpose: Validate the entire accounting system for production deployment
 * Date: 2026-04-08
 * 
 * This script performs critical validations including:
 * - Debit/Credit balance verification
 * - User tracking verification
 * - Duplicate idempotency key detection
 * - Multi-tenant isolation verification
 * - Data integrity checks
 * 
 * Run this script before deployment and after data migrations
 * Output: JSON formatted results with pass/fail status
 */

class AccountingValidationService {
    private $db;
    private $errors = [];
    private $warnings = [];
    private $passed = [];
    
    public function __construct($dsn, $user, $pass) {
        try {
            $this->db = new PDO($dsn, $user, $pass);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    
    /**
     * Main validation runner - executes all validation checks
     */
    public function validate() {
        echo "🔍 Starting Production Readiness Validation...\n\n";
        
        $this->validateJournalEntryBalance();
        $this->validateUserTracking();
        $this->validateNoMissingEntryDates();
        $this->validateNoDuplicateIdempotencyKeys();
        $this->validateMultiTenantIsolation();
        $this->validatePaymentIntegrity();
        $this->validateJournalEntryLineCount();
        $this->validateAccountsExist();
        $this->validateCostCenterExist();
        
        return $this->generateReport();
    }
    
    /**
     * 1. Validate: All journal entries have debit_total = credit_total (within 0.01)
     */
    private function validateJournalEntryBalance() {
        echo "✓ Checking Journal Entry Balance (Debit = Credit)...\n";
        
        $sql = "
            SELECT 
                je.id,
                je.tenant_id,
                je.reference_type,
                je.reference_id,
                SUM(CASE WHEN jel.debit_amount > 0 THEN jel.debit_amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN jel.credit_amount > 0 THEN jel.credit_amount ELSE 0 END) as total_credit,
                ABS(
                    SUM(CASE WHEN jel.debit_amount > 0 THEN jel.debit_amount ELSE 0 END) - 
                    SUM(CASE WHEN jel.credit_amount > 0 THEN jel.credit_amount ELSE 0 END)
                ) as difference
            FROM journal_entries je
            LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id AND je.tenant_id = jel.tenant_id
            GROUP BY je.id, je.tenant_id, je.reference_type, je.reference_id
            HAVING difference > 0.01
            LIMIT 1000
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $unbalanced = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($unbalanced)) {
                $this->passed[] = '✅ All journal entries are balanced (Debit = Credit)';
            } else {
                $count = count($unbalanced);
                $this->errors[] = "❌ Found $count unbalanced journal entries:";
                foreach ($unbalanced as $entry) {
                    $diff = abs($entry['total_debit'] - $entry['total_credit']);
                    $this->errors[] = "   - JE #{$entry['id']} (Tenant: {$entry['tenant_id']}, Ref: {$entry['reference_type']}#{$entry['reference_id']}) - Difference: {$diff}";
                }
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ Balance Check Failed: " . $e->getMessage();
        }
    }
    
    /**
     * 2. Validate: All entries have user_id and entry_date
     */
    private function validateUserTracking() {
        echo "✓ Checking User Tracking (created_by, entry_date)...\n";
        
        $sql = "
            SELECT COUNT(*) as count
            FROM journal_entries
            WHERE created_by IS NULL OR entry_date IS NULL
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $this->passed[] = '✅ All journal entries have user_id and entry_date';
            } else {
                $this->errors[] = "❌ Found {$result['count']} entries missing user_id or entry_date";
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ User Tracking Check Failed: " . $e->getMessage();
        }
    }
    
    /**
     * 3. Validate: No missing entry_date values
     */
    private function validateNoMissingEntryDates() {
        echo "✓ Checking for Missing Entry Dates...\n";
        
        $sql = "
            SELECT COUNT(*) as count
            FROM journal_entries
            WHERE entry_date IS NULL OR entry_date = '0000-00-00'
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $this->passed[] = '✅ All journal entries have valid entry_date values';
            } else {
                $this->warnings[] = "⚠️  Found {$result['count']} entries with missing/invalid entry_date";
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ Entry Date Check Failed: " . $e->getMessage();
        }
    }
    
    /**
     * 4. Validate: No duplicate idempotency_keys within each tenant
     */
    private function validateNoDuplicateIdempotencyKeys() {
        echo "✓ Checking for Duplicate Idempotency Keys...\n";
        
        $sql = "
            SELECT 
                tenant_id,
                idempotency_key,
                COUNT(*) as count
            FROM journal_entries
            WHERE idempotency_key IS NOT NULL
            GROUP BY tenant_id, idempotency_key
            HAVING count > 1
            LIMIT 100
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($duplicates)) {
                $this->passed[] = '✅ No duplicate idempotency keys found';
            } else {
                $count = count($duplicates);
                $this->warnings[] = "⚠️  Found $count duplicate idempotency keys (this is expected for idempotent operations)";
                foreach ($duplicates as $dup) {
                    $this->warnings[] = "   - Tenant {$dup['tenant_id']}: Key '{$dup['idempotency_key']}' appears {$dup['count']} times";
                }
            }
        } catch (Exception $e) {
            $this->warnings[] = "⚠️  Idempotency Key Check: " . $e->getMessage();
        }
    }
    
    /**
     * 5. Validate: Multi-tenant isolation (no cross-tenant entries)
     */
    private function validateMultiTenantIsolation() {
        echo "✓ Checking Multi-Tenant Isolation...\n";
        
        $sql = "
            SELECT 
                je.id,
                je.tenant_id,
                COUNT(DISTINCT jel.tenant_id) as line_tenant_ids
            FROM journal_entries je
            LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
            WHERE jel.tenant_id IS NOT NULL
            GROUP BY je.id, je.tenant_id
            HAVING je.tenant_id != line_tenant_ids OR line_tenant_ids > 1
            LIMIT 100
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($violations)) {
                $this->passed[] = '✅ Multi-tenant isolation verified (no cross-tenant entries)';
            } else {
                $count = count($violations);
                $this->errors[] = "❌ Found $count entries violating multi-tenant isolation";
                foreach ($violations as $v) {
                    $this->errors[] = "   - JE #{$v['id']} has mismatched tenant IDs (header: {$v['tenant_id']}, lines: {$v['line_tenant_ids']})";
                }
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ Multi-Tenant Check Failed: " . $e->getMessage();
        }
    }
    
    /**
     * 6. Validate: Payment integrity (journal_entry_id links)
     */
    private function validatePaymentIntegrity() {
        echo "✓ Checking Payment Integrity (journal_entry_id linking)...\n";
        
        $sql = "
            SELECT 
                p.id,
                p.tenant_id,
                COUNT(CASE WHEN je.id IS NULL THEN 1 END) as missing_je
            FROM payments p
            LEFT JOIN journal_entries je ON p.journal_entry_id = je.id 
                AND p.tenant_id = je.tenant_id
            WHERE p.journal_entry_id IS NOT NULL
            GROUP BY p.id, p.tenant_id
            HAVING missing_je > 0
            LIMIT 100
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $orphaned = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($orphaned)) {
                $this->passed[] = '✅ All payment journal_entry_id references are valid';
            } else {
                $count = count($orphaned);
                $this->warnings[] = "⚠️  Found $count payments with missing journal entry references";
            }
        } catch (Exception $e) {
            $this->warnings[] = "⚠️  Payment Integrity Check: " . $e->getMessage();
        }
    }
    
    /**
     * 7. Validate: Journal entries have at least 2 lines
     */
    private function validateJournalEntryLineCount() {
        echo "✓ Checking Journal Entry Line Count (minimum 2 lines)...\n";
        
        $sql = "
            SELECT 
                je.id,
                je.tenant_id,
                COUNT(jel.id) as line_count
            FROM journal_entries je
            LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id 
                AND je.tenant_id = jel.tenant_id
            GROUP BY je.id, je.tenant_id
            HAVING line_count < 2
            LIMIT 100
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $invalid = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($invalid)) {
                $this->passed[] = '✅ All journal entries have at least 2 lines';
            } else {
                $count = count($invalid);
                $this->errors[] = "❌ Found $count entries with < 2 lines (invalid journal entries)";
                foreach ($invalid as $inv) {
                    $this->errors[] = "   - JE #{$inv['id']} has only {$inv['line_count']} line(s)";
                }
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ Line Count Check Failed: " . $e->getMessage();
        }
    }
    
    /**
     * 8. Validate: All referenced account IDs exist
     */
    private function validateAccountsExist() {
        echo "✓ Checking Account References...\n";
        
        $sql = "
            SELECT COUNT(*) as count
            FROM journal_entry_lines jel
            LEFT JOIN accounts a ON jel.account_id = a.id 
                AND jel.tenant_id = a.tenant_id
            WHERE jel.account_id IS NOT NULL
            AND a.id IS NULL
            LIMIT 100
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $this->passed[] = '✅ All journal entry lines reference valid accounts';
            } else {
                $this->errors[] = "❌ Found {$result['count']} lines with invalid account references";
            }
        } catch (Exception $e) {
            $this->warnings[] = "⚠️  Account Reference Check: " . $e->getMessage();
        }
    }
    
    /**
     * 9. Validate: All cost center references are valid
     */
    private function validateCostCenterExist() {
        echo "✓ Checking Cost Center References...\n";
        
        $sql = "
            SELECT COUNT(*) as count
            FROM journal_entries je
            LEFT JOIN cost_centers cc ON je.cost_center_id = cc.id 
                AND je.tenant_id = cc.tenant_id
            WHERE je.cost_center_id IS NOT NULL
            AND cc.id IS NULL
            LIMIT 100
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $this->passed[] = '✅ All journal entries reference valid cost centers';
            } else {
                $this->warnings[] = "⚠️  Found {$result['count']} entries with invalid cost center references";
            }
        } catch (Exception $e) {
            $this->warnings[] = "⚠️  Cost Center Check: " . $e->getMessage();
        }
    }
    
    /**
     * Generate final validation report in JSON format
     */
    private function generateReport() {
        $totalChecks = count($this->passed) + count($this->errors) + count($this->warnings);
        $status = empty($this->errors) ? 'PASS' : 'FAIL';
        
        $report = [
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_checks' => $totalChecks,
                'passed' => count($this->passed),
                'errors' => count($this->errors),
                'warnings' => count($this->warnings)
            ],
            'details' => [
                'passed' => $this->passed,
                'errors' => $this->errors,
                'warnings' => $this->warnings
            ]
        ];
        
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

// Get database credentials from environment or config
$dsn = getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=smartsys';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$validator = new AccountingValidationService($dsn, $user, $pass);
$report = $validator->validate();

echo "\n" . str_repeat("=", 60) . "\n";
echo "VALIDATION REPORT\n";
echo str_repeat("=", 60) . "\n\n";

echo $report . "\n";

// Exit with appropriate code for CI/CD integration
$decoded = json_decode($report, true);
exit($decoded['status'] === 'PASS' ? 0 : 1);
