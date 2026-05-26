<?php

use App\Handlers\OpeningBalanceHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(OpeningBalanceHandler::class);
        
        $group->group('/setup/opening-balance', function (RouteCollectorProxy $group) use ($handler) {
            // Download CSV template
            $group->get('/template', function (Request $request, Response $response) use ($handler) {
                return $handler->template($request, $response);
            });

            // Preview opening balance payload
            $group->post('/preview', function (Request $request, Response $response) use ($handler) {
                return $handler->preview($request, $response);
            });

            // Commit opening balance to inventory_transactions and branch_products
            $group->post('/commit', function (Request $request, Response $response) use ($handler) {
                return $handler->commit($request, $response);
            });
        });
};
