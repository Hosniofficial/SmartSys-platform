<?php

use App\Handlers\AccountingPeriodsHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
    $handler = $container->get(AccountingPeriodsHandler::class);

    $group->group('/accounting-periods', function (RouteCollectorProxy $group) use ($handler) {
        $group->get('',          [$handler, 'list']);
        $group->post('',         [$handler, 'create']);
        $group->put('/{id}/close',  [$handler, 'close']);
        $group->put('/{id}/reopen', [$handler, 'reopen']);
        $group->delete('/{id}',  [$handler, 'delete']);
    });
};
