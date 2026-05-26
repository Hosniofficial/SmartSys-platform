<?php

use App\Handlers\PaymentMethodsHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(PaymentMethodsHandler::class);

        $group->group('/payment-methods', function (RouteCollectorProxy $group) use ($handler) {
            // List payment methods
            $group->get('', [$handler, 'list']);

            // Create payment method
            $group->post('', [$handler, 'create']);

            // Update payment method kind
            $group->put('/{id:[0-9]+}', [$handler, 'update']);
        });
};