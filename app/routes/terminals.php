<?php

use App\Handlers\TerminalsHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(TerminalsHandler::class);
        
        $group->group('/terminals', function (RouteCollectorProxy $group) use ($handler) {
            $group->get('', [$handler, 'list']);
            $group->post('', [$handler, 'create']);
            $group->put('/{id}', [$handler, 'update']);
        });
};
