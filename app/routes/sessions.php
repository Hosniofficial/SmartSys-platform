<?php

use App\Handlers\SessionsHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(SessionsHandler::class);

        $group->group('/sessions', function (RouteCollectorProxy $group) use ($handler) {
            $group->post('/open', [$handler, 'open']);
            $group->post('/close', [$handler, 'close']);
            $group->post('/{id}/close', [$handler, 'close']);
            $group->get('/current', [$handler, 'current']);
            $group->get('', [$handler, 'listSessions']);
            $group->get('/{id}/summary', [$handler, 'summary']);
            $group->get('/summary/daily', [$handler, 'dailySummary']);
            $group->post('/batch-summaries', [$handler, 'batchSummaries']);  // 🆕 Batch API
        });
};
