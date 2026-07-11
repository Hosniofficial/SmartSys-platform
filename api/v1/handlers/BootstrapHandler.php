<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Handlers\BaseHandler;
use PDO;

/**
 * Bootstrap Handler
 * Provides aggregated API endpoints to reduce HTTP requests and improve page load performance
 */
class BootstrapHandler extends BaseHandler
{
    /**
     * Helper: Get active branches
     * Reusable across multiple bootstrap methods
     */
    protected function getActiveBranches($tenantId)
    {
        $query = "SELECT id, name FROM branches WHERE tenant_id = :tenant_id AND active = 1 ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Helper: Get active payment methods
     */
    protected function getActivePaymentMethods($tenantId)
    {
        $query = "SELECT id, name, kind FROM payment_methods WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Helper: Get active users
     */
    protected function getActiveUsers($tenantId, $fieldsArray = ['id', 'name'])
    {
        $fields = implode(', ', $fieldsArray);
        $query = "SELECT {$fields} FROM users WHERE tenant_id = :tenant_id AND status = 'active' ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get POS (Point of Sale) page data
     * Aggregates all data needed for SalesPoint.vue in a single request
     * 
     * @return array
     */
    public function getPosData($request, $response)
    {
        try {
            // استخدام الطريقة الصحيحة من BaseHandler
            $user = $request->getAttribute('user');
            $userId = is_array($user) && isset($user['id']) ? (int) $user['id'] : null;
            $userBranchId = is_array($user) && isset($user['branch_id']) ? (int) $user['branch_id'] : null;
            $tenantId = $this->extractTenantId($request);
            
            if (!$tenantId) {
                return $this->errorResponse($response, 'معرف المستأجر مطلوب', 400);
            }
            
            // Get branches using helper
            $branches = $this->getActiveBranches($tenantId);
            
            // Get active categories
            $categoriesQuery = "SELECT id, name FROM categories WHERE tenant_id = :tenant_id AND active = 1 ORDER BY name";
            $categoriesStmt = $this->db->prepare($categoriesQuery);
            $categoriesStmt->execute(['tenant_id' => $tenantId]);
            $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get active payment methods using helper
            $paymentMethods = $this->getActivePaymentMethods($tenantId);
            
            // Get current session for this device/branch
            $currentSession = null;
            if ($userBranchId) {
                $sessionQuery = "SELECT * FROM cashier_sessions 
                                WHERE branch_id = :branch_id 
                                AND user_id = :user_id 
                                AND status = 'open' 
                                ORDER BY start_time DESC 
                                LIMIT 1";
                $sessionStmt = $this->db->prepare($sessionQuery);
                $sessionStmt->execute([
                    'branch_id' => $userBranchId,
                    'user_id' => $userId
                ]);
                $currentSession = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            
            // Get POS settings
            $settingsQuery = "SELECT key_name, value FROM settings 
                             WHERE tenant_id = :tenant_id 
                             AND key_name IN ('tax_rate', 'require_approval', 'sessions_enabled', 'sessions_mode')";
            $settingsStmt = $this->db->prepare($settingsQuery);
            $settingsStmt->execute(['tenant_id' => $tenantId]);
            $settingsRows = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $settings = [];
            foreach ($settingsRows as $row) {
                $settings[$row['key_name']] = $row['value'];
            }
            
            return $this->jsonResponse($response, [
                'status' => 'success',
                'data' => [
                    'branches' => $branches,
                    'categories' => $categories,
                    'paymentMethods' => $paymentMethods,
                    'currentSession' => $currentSession,
                    'settings' => $settings,
                    'userBranch' => $userBranchId
                ],
                'meta' => [
                    'cached_at' => date('Y-m-d H:i:s'),
                    'ttl' => 300 // 5 minutes
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Bootstrap POS Data Error: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'فشل في تحميل بيانات نقطة البيع',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get Payments page data
     * Aggregates all data needed for PaymentsList.vue in a single request
     * 
     * @return array
     */
    public function getPaymentsPageData($request, $response)
    {
        try {
            $tenantId = $this->extractTenantId($request);
            
            if (!$tenantId) {
                return $this->errorResponse($response, 'معرف المستأجر مطلوب', 400);
            }
            
            // Get payment methods using helper
            $paymentMethods = $this->getActivePaymentMethods($tenantId);
            
            // Get customers (limit to active or recent)
            $customersQuery = "SELECT id, name, phone, email FROM customers 
                              WHERE tenant_id = :tenant_id 
                              AND active = 1 
                              ORDER BY name 
                              LIMIT 500";
            $customersStmt = $this->db->prepare($customersQuery);
            $customersStmt->execute(['tenant_id' => $tenantId]);
            $customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get suppliers (limit to active or recent)
            $suppliersQuery = "SELECT id, name, phone, email FROM suppliers 
                              WHERE tenant_id = :tenant_id 
                              AND active = 1 
                              ORDER BY name 
                              LIMIT 500";
            $suppliersStmt = $this->db->prepare($suppliersQuery);
            $suppliersStmt->execute(['tenant_id' => $tenantId]);
            $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get users using helper
            $users = $this->getActiveUsers($tenantId, ['id', 'name', 'email', 'role_id']);
            
            // Get relevant settings
            $settingsQuery = "SELECT key_name, value FROM settings 
                             WHERE tenant_id = :tenant_id 
                             AND key_name IN ('currency', 'currency_symbol', 'tax_rate')";
            $settingsStmt = $this->db->prepare($settingsQuery);
            $settingsStmt->execute(['tenant_id' => $tenantId]);
            $settingsRows = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $settings = [];
            foreach ($settingsRows as $row) {
                $settings[$row['key_name']] = $row['value'];
            }
            
            return $this->jsonResponse($response, [
                'status' => 'success',
                'data' => [
                    'paymentMethods' => $paymentMethods,
                    'customers' => $customers,
                    'suppliers' => $suppliers,
                    'users' => $users,
                    'settings' => $settings
                ],
                'meta' => [
                    'cached_at' => date('Y-m-d H:i:s'),
                    'ttl' => 300
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Bootstrap Payments Data Error: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'فشل في تحميل بيانات الدفعات',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get Sessions page data
     * Aggregates all data needed for SessionsList.vue in a single request
     * 
     * @return array
     */
    public function getSessionsPageData($request, $response)
    {
        try {
            $tenantId = $this->extractTenantId($request);
            
            if (!$tenantId) {
                return $this->errorResponse($response, 'معرف المستأجر مطلوب', 400);
            }
            
            // Get branches using helper
            $branches = $this->getActiveBranches($tenantId);
            
            // Get users using helper
            $users = $this->getActiveUsers($tenantId);
            
            return $this->jsonResponse($response, [
                'status' => 'success',
                'data' => [
                    'branches' => $branches,
                    'users' => $users
                ],
                'meta' => [
                    'cached_at' => date('Y-m-d H:i:s'),
                    'ttl' => 300
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Bootstrap Sessions Data Error: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'فشل في تحميل بيانات الجلسات',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get Management pages data (Purchase/Sale)
     * Aggregates common data needed for management pages
     * 
     * @param string $type 'purchase' or 'sale'
     * @return array
     */
    public function getManagementData($request, $response, $args)
    {
        try {
            $type = $args['type'] ?? 'purchase';
            $tenantId = $this->extractTenantId($request);
            
            if (!$tenantId) {
                return $this->errorResponse($response, 'معرف المستأجر مطلوب', 400);
            }
            
            // Get branches and payment methods using helpers
            $branches = $this->getActiveBranches($tenantId);
            $paymentMethods = $this->getActivePaymentMethods($tenantId);
            
            $data = [
                'branches' => $branches,
                'paymentMethods' => $paymentMethods
            ];
            
            // Type-specific data
            if ($type === 'purchase') {
                $suppliersQuery = "SELECT id, name, phone, email FROM suppliers WHERE tenant_id = :tenant_id AND active = 1 ORDER BY name LIMIT 500";
                $suppliersStmt = $this->db->prepare($suppliersQuery);
                $suppliersStmt->execute(['tenant_id' => $tenantId]);
                $data['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'sale') {
                $customersQuery = "SELECT id, name, phone, email FROM customers WHERE tenant_id = :tenant_id AND active = 1 ORDER BY name LIMIT 500";
                $customersStmt = $this->db->prepare($customersQuery);
                $customersStmt->execute(['tenant_id' => $tenantId]);
                $data['customers'] = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $this->jsonResponse($response, [
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'type' => $type,
                    'cached_at' => date('Y-m-d H:i:s'),
                    'ttl' => 300
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Bootstrap Management Data Error: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'فشل في تحميل بيانات الإدارة',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
