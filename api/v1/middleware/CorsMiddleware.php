<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private int $maxAge;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;

    public function __construct()
    {
        // Load CORS configuration from security config with safe defaults
        $config = require __DIR__ . '/../../../config/security.php';
        $corsConfig = is_array($config) ? ($config['cors'] ?? []) : [];

        // Origins
        $originConfig = $corsConfig['origin'] ?? '*';
        if ($originConfig === '*' || (is_string($originConfig) && trim($originConfig) === '*')) {
            $this->allowedOrigins = ['*'];
        } else {
            $this->allowedOrigins = (array)$originConfig;
        }

        // Methods
        $this->allowedMethods = $corsConfig['methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

        // Headers (ensure common defaults if not provided)
        $this->allowedHeaders = $corsConfig['headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Tenant-ID'];

        // Max age and credentials
        $this->maxAge = isset($corsConfig['max_age']) ? (int)$corsConfig['max_age'] : 86400;
        $this->allowCredentials = (bool)($corsConfig['credentials'] ?? false);

    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $method = $request->getMethod();

        if ($method === 'OPTIONS') {
            return $this->handlePreflight($request, $origin);
        }

        try {
            $response = $handler->handle($request);

            if ($this->isOriginAllowed($origin)) {
                $response = $this->addCorsHeaders($response, $origin);
            }

            return $response;
        } catch (\Throwable $e) {
            $response = new SlimResponse();

            if ($this->isOriginAllowed($origin)) {
                $response = $this->addCorsHeaders($response, $origin);
            }

            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 599) ? $e->getCode() : 500;
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => $statusCode >= 500 ? 'Internal server error' : $e->getMessage(),
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($statusCode);
        }
    }

    private function handlePreflight(Request $request, string $origin): Response
    {
        $response = new SlimResponse();
        
        if ($this->isOriginAllowed($origin)) {
            $requestedMethod = $request->getHeaderLine('Access-Control-Request-Method');
            $requestedHeaders = $request->getHeaderLine('Access-Control-Request-Headers');
            
            // If specific method is requested, use it, otherwise use all allowed methods
            $allowedMethods = $requestedMethod ? [$requestedMethod] : $this->allowedMethods;
            
            // Use specific origin instead of wildcard when credentials are enabled
            if ($this->allowCredentials && !in_array('*', $this->allowedOrigins, true)) {
                $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
            } else {
                $allowedOrigin = in_array('*', $this->allowedOrigins, true) ? '*' : $origin;
                $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
            }
            
            $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
            
            // Add allowed headers if requested
            if ($requestedHeaders) {
                $response = $response->withHeader('Access-Control-Allow-Headers', $requestedHeaders);
            } elseif (!empty($this->allowedHeaders)) {
                $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
            }
            
            $response = $response->withHeader('Access-Control-Max-Age', (string)$this->maxAge);
            
            if ($this->allowCredentials) {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }
            
            // Expose headers if needed
            if (!empty($this->allowedHeaders)) {
                $response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $this->allowedHeaders));
            }
        }
        
        return $response->withStatus(204); // No Content
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        // Allow all origins
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }
        
        // Check for exact match
        if (in_array($origin, $this->allowedOrigins, true)) {
            return true;
        }
        
        // Check for wildcard subdomains (e.g., *.example.com)
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if (strpos($allowedOrigin, '*') !== false) {
                $pattern = '/^' . str_replace('\*', '.*', preg_quote($allowedOrigin, '/')) . '$/';
                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function addCorsHeaders(Response $response, string $origin): Response
    {
        // Use specific origin instead of wildcard when credentials are enabled
        if ($this->allowCredentials && !in_array('*', $this->allowedOrigins, true)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        } else {
            // Fallback to wildcard if no credentials or wildcard allowed
            $allowedOrigin = in_array('*', $this->allowedOrigins, true) ? '*' : $origin;
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
        }
        
        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        if (!empty($this->allowedHeaders)) {
            $response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $this->allowedHeaders));
        }
        
        // Add Vary header to prevent caching of CORS responses
        if ($response->hasHeader('Vary')) {
            $vary = $response->getHeaderLine('Vary');
            if (stripos($vary, 'Origin') === false) {
                $vary = empty($vary) ? 'Origin' : $vary . ', Origin';
                $response = $response->withHeader('Vary', $vary);
            }
        } else {
            $response = $response->withHeader('Vary', 'Origin');
        }
        
        return $response;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        return $this->process($request, $handler);
    }
}
