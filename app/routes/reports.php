<?php

declare(strict_types=1);

use App\Handlers\AdvancedReportsHandler;
use App\Handlers\AnalyticsHandler;
use App\Handlers\SalesAnalyticsHandler;
use App\Handlers\InventoryAnalyticsHandler;
use App\Handlers\PosAnalyticsHandler;
use App\Middleware\PermissionMiddleware;
use App\Security\Permissions;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {

    $db = $container->get(PDO::class);

    $group->group('/reports', function (RouteCollectorProxy $group) use ($container, $db) {

        $analytics  = $container->get(AnalyticsHandler::class);
        $salesH     = $container->get(SalesAnalyticsHandler::class);
        $inventoryH = $container->get(InventoryAnalyticsHandler::class);
        $posH       = $container->get(PosAnalyticsHandler::class);
        $advanced   = $container->get(AdvancedReportsHandler::class);

        // ── Sales reports ─────────────────────────────────────────────────────
        $group->get('/sales/summary', [$salesH, 'analyzeSales'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        $group->get('/sales/daily-summary', [$salesH, 'dailySalesSummary'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        // ── Inventory reports ─────────────────────────────────────────────────
        $group->get('/inventory/summary', [$inventoryH, 'analyzeInventory'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_INVENTORY, $db));

        $group->get('/inventory/value', [$inventoryH, 'analyzeInventory'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_INVENTORY, $db));

        $group->get('/inventory/movements', [$inventoryH, 'analyzeInventory'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_INVENTORY, $db));

        $group->get('/inventory/advanced', [$advanced, 'getAdvancedInventoryReport'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_INVENTORY, $db));

        // ── POS ───────────────────────────────────────────────────────────────
        $group->get('/pos', [$posH, 'listPos'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        // ── Contacts ──────────────────────────────────────────────────────────
        $group->get('/suppliers/analysis', [$analytics, 'analyzeSuppliers'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_PURCHASES, $db));

        $group->get('/customers/analysis', [$analytics, 'analyzeCustomers'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        // ── Financial ─────────────────────────────────────────────────────────
        $group->get('/financials/summary', function (Request $request, Response $response) use ($advanced) {
            $params   = $request->getQueryParams();
            $tenantId = (int) $advanced->extractTenantId($request);

            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'status'  => 'error',
                    'message' => 'Tenant ID is missing or invalid',
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $data = $advanced->getFinancialSummary($params, $tenantId);
            $response->getBody()->write(json_encode(['status' => 'success', 'data' => $data]));
            return $response->withHeader('Content-Type', 'application/json');
        })->add(PermissionMiddleware::require(Permissions::REPORT_FINANCIAL, $db));

        $group->get('/profit-loss', [$advanced, 'getProfitLossReport'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_FINANCIAL, $db));

        $group->get('/trends/analysis', [$salesH, 'analyzeTrends'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        $group->get('/performance/daily', [$advanced, 'getDailyPerformance'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));
    });
};
