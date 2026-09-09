<?php

declare(strict_types=1);

use App\Handlers\UsersHandler;
use App\Middleware\PermissionMiddleware;
use App\Security\Permissions;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {

    $handler = $container->get(UsersHandler::class);
    $db      = $container->get(PDO::class);

    $group->group('/users', function (RouteCollectorProxy $group) use ($handler, $db) {

        // ── Self-service (no permission required beyond being authenticated) ──
        // Any logged-in user can read their own profile and change their password.
        $group->get('/me', [$handler, 'getMe']);
        $group->post('/update-profile', [$handler, 'updateProfile']);
        $group->post('/change-password', [$handler, 'changePassword']);

        // ── Admin operations ──────────────────────────────────────────────────
        $group->get('/list', [$handler, 'list'])
            ->add(PermissionMiddleware::require(Permissions::USER_VIEW, $db));

        $group->get('/{id:[0-9]+}', [$handler, 'get'])
            ->add(PermissionMiddleware::require(Permissions::USER_VIEW, $db));

        $group->post('', [$handler, 'create'])
            ->add(PermissionMiddleware::require(Permissions::USER_CREATE, $db));

        $group->put('/{id:[0-9]+}', [$handler, 'update'])
            ->add(PermissionMiddleware::require(Permissions::USER_EDIT, $db));

        $group->delete('/{id:[0-9]+}', [$handler, 'delete'])
            ->add(PermissionMiddleware::require(Permissions::USER_DELETE, $db));

        $group->post('/{id:[0-9]+}/change-password', [$handler, 'changePassword'])
            ->add(PermissionMiddleware::require(Permissions::USER_EDIT, $db));

        $group->post('/{id:[0-9]+}/toggle-status', [$handler, 'toggleStatus'])
            ->add(PermissionMiddleware::require(Permissions::USER_EDIT, $db));

        $group->get('/{id:[0-9]+}/permissions', [$handler, 'getUserPermissions'])
            ->add(PermissionMiddleware::require(Permissions::USER_VIEW, $db));

        $group->post('/{id:[0-9]+}/permissions', [$handler, 'updateUserPermissions'])
            ->add(PermissionMiddleware::require(Permissions::PERMISSION_ASSIGN, $db));

        $group->get('/{id:[0-9]+}/activity', [$handler, 'getUserActivity'])
            ->add(PermissionMiddleware::require(Permissions::USER_VIEW, $db));
    });
};
