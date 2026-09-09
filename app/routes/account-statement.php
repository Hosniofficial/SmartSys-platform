<?php

use App\Handlers\AccountStatementHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(AccountStatementHandler::class);
        
        // Account Statement routes
        $group->group('/account-statement', function (RouteCollectorProxy $group) use ($handler) {
            $group->get('/{account_id}', [$handler, 'getStatement']);
        });
};
