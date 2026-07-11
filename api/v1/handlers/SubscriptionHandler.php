<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;

class SubscriptionHandler extends BaseHandler
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('subscription');
    }

    /**
     * Get current subscription for authenticated user
     *
     * Returns the active/trial subscription with safe defaults for expired/missing subscriptions.
     * Does NOT block access - returns empty data structure to prevent infinite redirects.
     */
    public function getMySubscription(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);

            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $stmt = $this->db->prepare(
                "SELECT
                    s.*,
                    p.code AS plan_code,
                    p.name AS plan_name,
                    p.price,
                    p.currency,
                    p.billing_cycle_days
                 FROM subscriptions s
                 JOIN plans p ON p.id = s.plan_id
                 WHERE s.tenant_id = ?
                 ORDER BY (s.status IN ('trial','active')) DESC, s.end_date DESC, s.id DESC
                 LIMIT 1"
            );
            $stmt->execute([$tenantId]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$sub) {
                $result = [
                    'current' => [
                        'status' => 'inactive',
                        'plan_code' => null,
                        'plan_name' => null,
                        'price' => 0,
                        'currency' => 'USD',
                        'billing_cycle_days' => 30,
                        'end_date' => null,
                        'days_left' => 0
                    ]
                ];

                return $this->successResponse($response, $result);
            }

            $now = new DateTimeImmutable('now');
            $end = new DateTimeImmutable($sub['end_date']);
            $daysLeft = max(0, (int) $now->diff($end)->format('%a'));

            $result = [
                'current' => array_merge($sub, [
                    'days_left' => $daysLeft
                ])
            ];

            return $this->successResponse($response, $result);
        } catch (\Exception $e) {
            $this->logger->error('SubscriptionHandler::getMySubscription error', [
                'message' => $e->getMessage()
            ]);

            $result = [
                'current' => [
                    'status' => 'inactive',
                    'plan_code' => null,
                    'plan_name' => null,
                    'price' => 0,
                    'currency' => 'USD',
                    'billing_cycle_days' => 30,
                    'end_date' => null,
                    'days_left' => 0
                ]
            ];

            return $this->successResponse($response, $result);
        }
    }

    /**
     * Get subscription status for dashboard display
     * Includes plan details and renewal information
     */
    public function getSubscriptionStatus(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);

            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $stmt = $this->db->prepare(
                "SELECT
                    s.*,
                    p.code AS plan_code,
                    p.name AS plan_name,
                    p.price,
                    p.currency,
                    p.billing_cycle_days
                 FROM subscriptions s
                 JOIN plans p ON p.id = s.plan_id
                 WHERE s.tenant_id = ?
                   AND s.status IN ('trial', 'active')
                 LIMIT 1"
            );
            $stmt->execute([$tenantId]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sub) {
                return $this->errorResponse($response, 'No active subscription found', 404);
            }

            $now = new DateTimeImmutable('now');
            $end = new DateTimeImmutable($sub['end_date']);
            $daysLeft = max(0, (int) $now->diff($end)->format('%a'));

            $data = array_merge($sub, [
                'days_left' => $daysLeft,
                'is_expiring_soon' => $daysLeft <= 3,
                'formatted_end_date' => $end->format('Y-m-d')
            ]);

            return $this->successResponse($response, $data);
        } catch (\Exception $e) {
            $this->logger->error('SubscriptionHandler::getSubscriptionStatus error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'Failed to fetch subscription status', 500);
        }
    }

    /**
     * Get subscription history for user account page
     */
    public function getSubscriptionHistory(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);

            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $stmt = $this->db->prepare(
                "SELECT
                    s.id,
                    s.status,
                    s.start_date,
                    s.end_date,
                    s.payment_status,
                    p.code AS plan_code,
                    p.name AS plan_name,
                    p.price,
                    p.currency
                 FROM subscriptions s
                 JOIN plans p ON p.id = s.plan_id
                 WHERE s.tenant_id = ?
                 ORDER BY s.id DESC
                 LIMIT 20"
            );
            $stmt->execute([$tenantId]);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse($response, [
                'subscriptions' => $subscriptions
            ]);
        } catch (\Exception $e) {
            $this->logger->error('SubscriptionHandler::getSubscriptionHistory error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'Failed to fetch subscription history', 500);
        }
    }

    // GET: list all active plans (public, no auth required)
    public function listPublicPlans(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->db->query(
                "SELECT code, name, price, currency, billing_cycle_days, is_active
                 FROM plans
                 WHERE is_active = 1
                 ORDER BY FIELD(code, 'trial', 'monthly', 'yearly'), name"
            );

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return $this->successResponse($response, $rows, 200);
        } catch (\Exception $e) {
            $this->logger->error('SubscriptionHandler::listPublicPlans error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'Failed to fetch plans', 500);
        }
    }

    // GET: list available plans (excluding trial for existing users)
    public function listAvailablePlans(Request $request, Response $response): Response
    {
        try {
            $tenantId = $this->extractTenantId($request);
            if (!$tenantId) {
                return $this->errorResponse($response, 'مطلوب معرف المستأجر (Tenant ID).', 403);
            }

            $stmt = $this->db->prepare(
                "SELECT code, name, price, currency, billing_cycle_days, is_active
                 FROM plans
                 WHERE is_active = 1
                   AND code != 'trial'
                 ORDER BY FIELD(code, 'monthly', 'yearly'), name"
            );
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return $this->successResponse($response, $rows, 200);
        } catch (\Exception $e) {
            $this->logger->error('SubscriptionHandler::listAvailablePlans error', [
                'message' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 'Failed to fetch available plans', 500);
        }
    }
}