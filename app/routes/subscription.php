<?php

use App\Handlers\StrictSubscriptionHandler;
use App\Handlers\SubscriptionHandler;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
    $group->get('/plans', [$container->get(SubscriptionHandler::class), 'listPublicPlans']);

    $group->group('/plans', function (RouteCollectorProxy $group) use ($container) {
        $group->get('/available', [$container->get(SubscriptionHandler::class), 'listAvailablePlans']);
    });

    $group->group('/subscription', function (RouteCollectorProxy $group) use ($container) {
        $group->get('/me', function (Request $request, Response $response) use ($container) {
            return $container->get(SubscriptionHandler::class)->getMySubscription($request, $response);
        });

        $group->get('/status', function (Request $request, Response $response) use ($container) {
            return $container->get(SubscriptionHandler::class)->getSubscriptionStatus($request, $response);
        });

        $group->get('/history', function (Request $request, Response $response) use ($container) {
            return $container->get(SubscriptionHandler::class)->getSubscriptionHistory($request, $response);
        });
    });

    $group->post('/subscription/upgrade', function (Request $request, Response $response) use ($container) {
        return $container->get(StrictSubscriptionHandler::class)->upgradeSubscription($request, $response);
    });
};
