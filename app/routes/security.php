<?php

use App\Handlers\AuthHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $authHandler = $container->get(AuthHandler::class);

        $group->group('/security', function (RouteCollectorProxy $group) use ($authHandler) {
            $group->post('/2fa/setup', function (Request $request, Response $response) use ($authHandler) {
                return $authHandler->setup2FA($request, $response);
            });

            $group->post('/2fa/verify', function (Request $request, Response $response) use ($authHandler) {
                return $authHandler->verify2FA($request, $response);
            });

            $group->post('/2fa/enable', function (Request $request, Response $response) use ($authHandler) {
                return $authHandler->enable2FA($request, $response);
            });
        });
};
