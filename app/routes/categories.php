<?php

use App\Handlers\CategoriesHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(CategoriesHandler::class);

        $group->group('/categories', function (RouteCollectorProxy $group) use ($handler) {
            $group->get('', [$handler, 'getAll']);
            $group->get('/{id:[0-9]+}', [$handler, 'get']);
            $group->post('', [$handler, 'create']);
            $group->put('/{id:[0-9]+}', [$handler, 'update']);
            $group->delete('/{id:[0-9]+}', [$handler, 'delete']);
        });
};
