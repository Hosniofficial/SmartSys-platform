<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use App\Utils\SuperAdminHelper;

class SuperAdminMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (SuperAdminHelper::isNot(is_array($user) ? $user : null)) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Forbidden: super_admin only'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        return $handler->handle($request);
    }
}
