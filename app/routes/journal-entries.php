<?php

declare(strict_types=1);

use App\Handlers\JournalEntriesHandler;
use App\Security\Permissions;
use App\Middleware\PermissionMiddleware;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {

    $handler = $container->get(JournalEntriesHandler::class);
    $db      = $container->get(PDO::class);

    $group->group('/journal-entries', function (RouteCollectorProxy $group) use ($handler, $db) {

        // ── Read ──────────────────────────────────────────────────────────────
        $group->get('', [$handler, 'list'])
            ->add(PermissionMiddleware::require(Permissions::ACCOUNTING_VIEW, $db));

        $group->get('/{id}', [$handler, 'get'])
            ->add(PermissionMiddleware::require(Permissions::ACCOUNTING_VIEW, $db));

        // ── Write ─────────────────────────────────────────────────────────────
        $group->post('', [$handler, 'create'])
            ->add(PermissionMiddleware::require(Permissions::ACCOUNTING_JE_CREATE, $db));

        // ── Delete (irreversible — requires dedicated permission) ─────────────
        $group->delete('/{id}', [$handler, 'delete'])
            ->add(PermissionMiddleware::require(Permissions::ACCOUNTING_JE_DELETE, $db));

        // ── Reverse (creates counter-entry — treated as write) ────────────────
        $group->post('/{id}/reverse', [$handler, 'reverse'])
            ->add(PermissionMiddleware::require(Permissions::ACCOUNTING_JE_REVERSE, $db));
    });
};
