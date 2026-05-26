<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * HTTPS Enforcement Middleware
 * 
 * Redirects all HTTP requests to HTTPS in production
 * Adds HSTS headers for secure connections
 */
class HttpsEnforcementMiddleware implements MiddlewareInterface
{
    private bool $enabled;
    private bool $redirectToHttps;
    private array $excludePaths = [];
    
    public function __construct(
        bool $enabled = true,
        bool $redirectToHttps = true,
        array $excludePaths = []
    ) {
        $this->enabled = $enabled;
        $this->redirectToHttps = $redirectToHttps;
        $this->excludePaths = $excludePaths ?? [
            '/health',
            '/status',
            '/metrics',
        ];
    }
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        $scheme = $request->getUri()->getScheme();
        $path = $request->getUri()->getPath();
        $host = $request->getUri()->getHost();
        
        // Skip HTTPS enforcement for excluded paths
        if ($this->isPathExcluded($path)) {
            return $handler->handle($request);
        }
        
        // Skip HTTPS enforcement for localhost (development)
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'])) {
            return $handler->handle($request);
        }
        
        // Redirect HTTP → HTTPS without executing the handler first
        if ($this->enabled && $this->redirectToHttps && $scheme === 'http') {
            $httpsUri = $request->getUri()->withScheme('https')->withPort(null);
            return (new SlimResponse())
                ->withStatus(301)
                ->withHeader('Location', (string)$httpsUri);
        }

        // Process request
        $response = $handler->handle($request);
        
        // Add HSTS header for HTTPS connections only
        if ($scheme === 'https') {
            // Strict-Transport-Security header
            // max-age: 1 year (31536000 seconds)
            // includeSubDomains: apply to all subdomains
            // preload: opt-in to HSTS preload list
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }
        
        return $response;
    }
    
    /**
     * Check if path should be excluded from HTTPS enforcement
     */
    private function isPathExcluded(string $path): bool
    {
        foreach ($this->excludePaths as $excludePath) {
            if ($path === $excludePath || strpos($path, $excludePath) === 0) {
                return true;
            }
        }
        return false;
    }
}
