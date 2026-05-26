<?php

use App\Handlers\JournalEntriesHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(JournalEntriesHandler::class);

        // Journal Entries routes
        $group->group('/journal-entries', function (RouteCollectorProxy $group) use ($handler) {
            $group->get('', [$handler, 'list']);
            $group->post('', [$handler, 'create']);
            $group->get('/{id}', [$handler, 'get']);
            $group->delete('/{id}', [$handler, 'delete']);
            $group->post('/{id}/reverse', [$handler, 'reverse']);
        });
};
