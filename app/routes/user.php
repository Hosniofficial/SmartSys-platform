<?php

use App\Handlers\UsersHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(UsersHandler::class);

        // Current User routes (authenticated user endpoints)
        $group->group('/user', function (RouteCollectorProxy $group) use ($handler) {
            $group->get('/preferences', [$handler, 'getUserPreferences']);
            $group->post('/preferences', [$handler, 'saveUserPreferences']);
        });
};
