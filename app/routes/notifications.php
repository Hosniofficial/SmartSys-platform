<?php

use App\Handlers\NotificationHandler;
use DI\Container;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group, Container $container): void {
        $notificationHandler = $container->get(NotificationHandler::class);

        // Notification routes
        $group->group('/notifications', function (RouteCollectorProxy $group) use ($notificationHandler) {
            // Get notifications list
            $group->get('', [$notificationHandler, 'getNotifications']);
            
            // Mark notification as read
            $group->put('/{id}/read', [$notificationHandler, 'markAsRead']);
            
            // Get notification statistics
            $group->get('/statistics', [$notificationHandler, 'getStatistics']);
            
            // Notification preferences management
            $group->get('/preferences', [$notificationHandler, 'getPreferences']);
            $group->post('/preferences', [$notificationHandler, 'updatePreferences']);
            $group->put('/preferences', [$notificationHandler, 'updatePreferences']);
        });
};
