<?php

use App\Handlers\SetupHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $setupHandler = $container->get(SetupHandler::class);

        $group->group('/setup', function (RouteCollectorProxy $group) use ($setupHandler) {
            // Get setup status and current settings
            $group->get('/status', function (Request $request, Response $response) use ($setupHandler) {
                return $setupHandler->getSetupStatus($request, $response);
            });

            // Save setup configuration
            $group->post('/save', function (Request $request, Response $response) use ($setupHandler) {
                return $setupHandler->saveSetup($request, $response);
            });

            // Skip setup (complete later)
            $group->post('/skip', function (Request $request, Response $response) use ($setupHandler) {
                return $setupHandler->skipSetup($request, $response);
            });
        });
};
