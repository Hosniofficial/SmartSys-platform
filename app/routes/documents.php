<?php

use App\Handlers\DocumentManagementHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $documentHandler = $container->get(DocumentManagementHandler::class);

        // Document routes
        $group->group('/documents', function (RouteCollectorProxy $group) use ($documentHandler) {
            // Upload document
            $group->post('', function (Request $request, Response $response) use ($documentHandler) {
                return $documentHandler->uploadDocument($request, $response);
            });
            
            // Get document
            $group->get('/{id}', function (Request $request, Response $response, array $args) use ($documentHandler) {
                return $documentHandler->getDocumentDetails($request, $response, $args);
            });
            
            // Update document
            $group->put('/{id}', function (Request $request, Response $response, array $args) use ($documentHandler) {
                return $documentHandler->updateDocumentSecurely($request, $response, $args);
            });
            
            // Delete document
            $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($documentHandler) {
                return $documentHandler->deleteDocumentSecurely($request, $response, $args);
            });
        });
};
