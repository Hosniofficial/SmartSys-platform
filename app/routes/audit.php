<?php

declare(strict_types=1);

use App\Handlers\AuditHandler;
use App\Handlers\AuditTrailHandler;
use App\Middleware\PermissionMiddleware;
use App\Security\Permissions;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {

    $auditHandler      = $container->get(AuditHandler::class);
    $auditTrailHandler = $container->get(AuditTrailHandler::class);
    $db                = $container->get(PDO::class);

    // Audit trail — all require report.financial (sensitive financial audit data)
    $group->get('/audit/security-events', function (Request $request, Response $response) use ($auditTrailHandler) {
        return $auditTrailHandler->getSecurityEvents($request, $response);
    })->add(PermissionMiddleware::require(Permissions::REPORT_FINANCIAL, $db));

    $group->get('/audit/user-activity', function (Request $request, Response $response) use ($auditTrailHandler) {
        return $auditTrailHandler->getUserActivityLogs($request, $response);
    })->add(PermissionMiddleware::require(Permissions::REPORT_FINANCIAL, $db));

    $group->get('/audit/system-events', function (Request $request, Response $response) use ($auditTrailHandler) {
        return $auditTrailHandler->getSystemEventLogs($request, $response);
    })->add(PermissionMiddleware::require(Permissions::REPORT_FINANCIAL, $db));

    $group->post('/audit/report', function (Request $request, Response $response) use ($auditTrailHandler) {
        return $auditTrailHandler->generateAuditReport($request, $response);
    })->add(PermissionMiddleware::require(Permissions::REPORT_FINANCIAL, $db));

    $group->group('/audit', function (RouteCollectorProxy $group) use ($auditHandler, $db) {
        $group->get('/logs', [$auditHandler, 'getAuditLogs'])
            ->add(PermissionMiddleware::require(Permissions::REPORT_FINANCIAL, $db));
    });
};
