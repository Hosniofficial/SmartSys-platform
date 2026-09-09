<?php

declare(strict_types=1);

use App\Handlers\DocumentManagementHandler;
use App\Security\Permissions;
use App\Middleware\PermissionMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {

    $documentHandler = $container->get(DocumentManagementHandler::class);
    $db              = $container->get(PDO::class);

    $group->group('/documents', function (RouteCollectorProxy $group) use ($documentHandler, $db) {

        $group->post('', function (Request $request, Response $response) use ($documentHandler) {
            return $documentHandler->uploadDocument($request, $response);
        })->add(PermissionMiddleware::require(Permissions::DOCUMENT_UPLOAD, $db));

        $group->get('/{id}', function (Request $request, Response $response, array $args) use ($documentHandler) {
            return $documentHandler->getDocumentDetails($request, $response, $args);
        })->add(PermissionMiddleware::require(Permissions::DOCUMENT_VIEW, $db));

        $group->put('/{id}', function (Request $request, Response $response, array $args) use ($documentHandler) {
            return $documentHandler->updateDocumentSecurely($request, $response, $args);
        })->add(PermissionMiddleware::require(Permissions::DOCUMENT_EDIT, $db));

        $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($documentHandler) {
            return $documentHandler->deleteDocumentSecurely($request, $response, $args);
        })->add(PermissionMiddleware::require(Permissions::DOCUMENT_DELETE, $db));
    });
};
