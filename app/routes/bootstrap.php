<?php

declare(strict_types=1);

use DI\Container;
use Slim\Routing\RouteCollectorProxy;
use App\Handlers\BootstrapHandler;

return function (RouteCollectorProxy $group, Container $container): void {
    
    // Bootstrap endpoints for aggregated data
    $group->group('/bootstrap', function (RouteCollectorProxy $group) {
        
        // POS (Point of Sale) page data
        $group->get('/pos', BootstrapHandler::class . ':getPosData');
        
        // Payments page data
        $group->get('/payments', BootstrapHandler::class . ':getPaymentsPageData');
        
        // Sessions page data
        $group->get('/sessions', BootstrapHandler::class . ':getSessionsPageData');
        
        // Management pages data (purchase/sale)
        $group->get('/management/{type}', BootstrapHandler::class . ':getManagementData');
        
    });
};
