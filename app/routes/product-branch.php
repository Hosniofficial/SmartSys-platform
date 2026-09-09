<?php

use App\Handlers\ProductBranchHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(ProductBranchHandler::class);

        // Product-Branch GL Integration routes (SAP-style accounting)
        $group->group('/product-branch', function (RouteCollectorProxy $group) use ($handler) {
            // Get product-branch status with GL reconciliation info
            $group->get('/status', [$handler, 'getProductsStatus']);
            
            // Activate product in branch (DRAFT → ACTIVE_IN_BRANCH)
            $group->post('/activate', [$handler, 'activateProductInBranch']);
            
            // Post opening balance with GL entries (ACTIVE_IN_BRANCH → GL_POSTED)
            $group->post('/opening-balance/post', [$handler, 'postOpeningBalance']);
            
            // Get reconciliation status for specific product-branch mapping
            $group->get('/{id}/reconciliation', [$handler, 'getReconciliationStatus']);
        });
};
