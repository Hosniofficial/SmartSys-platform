<?php

use App\Handlers\AdminSubscriptionHandler;
use App\Middleware\SuperAdminMiddleware;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(AdminSubscriptionHandler::class);

        $group->group('/admin', function (RouteCollectorProxy $group) use ($handler) {
            $group->get('/subscriptions', [$handler, 'listSubscriptions']);
            $group->post('/subscriptions/{id:[0-9]+}/activate', [$handler, 'activateSubscription']);
            $group->post('/subscriptions/{id:[0-9]+}/change-plan', [$handler, 'changePlan']);
            $group->delete('/plans/{code}', [$handler, 'deletePlan']);
        })->add($container->get(SuperAdminMiddleware::class));
};
