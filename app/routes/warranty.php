<?php

use App\Handlers\WarrantyHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $handler = $container->get(WarrantyHandler::class);

        // Warranty routes (protected)
        $group->group('/warranty', function (RouteCollectorProxy $group) use ($handler) {
            // Alias: GET /warranty → same as GET /warranty/requests
            $group->get('', [$handler, 'list']);

            // List warranty requests
            $group->get('/requests', [$handler, 'list']);

            // Create warranty request
            $group->post('/requests', [$handler, 'create']);

            // Get single warranty request with items, attachments, notes
            $group->get('/requests/{id}', [$handler, 'get']);

            // Update warranty request fields
            $group->patch('/requests/{id}', [$handler, 'update']);

            // Change status
            $group->post('/requests/{id}/status', [$handler, 'changeStatus']);

            // Add note
            $group->post('/requests/{id}/notes', [$handler, 'addNote']);

            // Upload attachment
            $group->post('/requests/{id}/attachments', [$handler, 'uploadAttachment']);

            // Delete attachment
            $group->delete('/attachments/{attachmentId}', [$handler, 'deleteAttachment']);
        });
};
