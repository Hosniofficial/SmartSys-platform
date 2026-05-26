<?php

use App\Handlers\AccountStatementHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(AccountStatementHandler::class);

        // Accounts routes
        $group->group('/accounts', function (RouteCollectorProxy $group) use ($handler) {
            // legacy accounts list
            $group->get('', [$handler, 'getAccounts']);

            // grouped accounts (tenant vs global)
            $group->get('/grouped', [$handler, 'getGroupedAccounts']);
        });
};
