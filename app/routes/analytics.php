<?php

declare(strict_types=1);

use App\Handlers\AnalyticsHandler;
use App\Handlers\SalesAnalyticsHandler;
use App\Handlers\InventoryAnalyticsHandler;
use App\Handlers\PosAnalyticsHandler;
use App\Middleware\PermissionMiddleware;
use App\Security\Permissions;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {

    $handler     = $container->get(AnalyticsHandler::class);
    $salesH      = $container->get(SalesAnalyticsHandler::class);
    $inventoryH  = $container->get(InventoryAnalyticsHandler::class);
    $posH        = $container->get(PosAnalyticsHandler::class);
    $db          = $container->get(PDO::class);

    $group->group('/analytics', function (RouteCollectorProxy $group) use ($handler, $salesH, $inventoryH, $posH, $db) {

        // Cashier dashboard — any authenticated user
        $group->get('/cashier/dashboard-summary', [$posH, 'cashierDashboardSummary']);
        $group->get('/daily-cash', [$posH, 'getDailyCashDrawerSummary']);

        // Sales analytics
        $group->get('/sales', [$salesH, 'analyzeSales'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        $group->get('/sales-payments-breakdown', [$salesH, 'breakdownSalesPayments'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        $group->get('/pos-performance', [$posH, 'getPosPerformance'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        // Inventory analytics
        $group->get('/inventory', [$inventoryH, 'analyzeInventory'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_INVENTORY, $db));

        $group->get('/inventory/analysis', [$inventoryH, 'analyzeInventory'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_INVENTORY, $db));

        // Contacts analytics
        $group->get('/suppliers', [$handler, 'analyzeSuppliers'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_PURCHASES, $db));

        $group->get('/customers', [$handler, 'analyzeCustomers'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));

        // Financial analytics
        $group->get('/financials', [$handler, 'analyzeFinancials'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_FINANCIAL, $db));

        $group->get('/trends', [$salesH, 'analyzeTrends'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_SALES, $db));
    });
};
