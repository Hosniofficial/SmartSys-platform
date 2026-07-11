<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use DomainException;
use UnexpectedValueException;
use App\Services\JwtBlacklistService;
use App\Services\MonologHandler;
use App\Services\SecurityLogger;

class JwtAuthMiddleware implements MiddlewareInterface
{
    private JwtBlacklistService $jwtBlacklistService;
    private ?SecurityLogger $securityLogger;
    private $logger;

    public function __construct(JwtBlacklistService $jwtBlacklistService, ?SecurityLogger $securityLogger = null)
    {
        $this->jwtBlacklistService = $jwtBlacklistService;
        $this->securityLogger      = $securityLogger;
        $this->logger              = MonologHandler::getInstance('jwt_auth');
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();

        try {
            // Determine path early and bypass auth endpoints before any token handling
            $path = $request->getUri()->getPath();
            $isAuthRoute = (strpos($path, '/auth/') !== false);

            if ($isAuthRoute) {
                return $handler->handle($request);
            }

            // For protected routes, require a token
            $token = $this->getTokenFromHeader($request);
            if (empty($token)) {
                $this->securityLogger?->logSecurityEvent(
                    'auth.token_missing',
                    'warning',
                    'Token missing in request',
                    ['path' => $path],
                    null,
                    null,
                    $request
                );
                throw new \Exception('Token not found in request');
            }

            if ($this->jwtBlacklistService->isBlacklisted($token)) {
                $this->securityLogger?->logSecurityEvent(
                    'auth.token_blacklisted',
                    'medium',
                    'Blacklisted token used',
                    ['token_prefix' => substr($token, 0, 20) . '...'],
                    null,
                    null,
                    $request
                );
                throw new \Exception('Token has been blacklisted');
            }

            // Load JWT secret from centralized config with ENV fallback
            $securityConfigPath = __DIR__ . '/../../../config/security.php';
            $securityConfig = file_exists($securityConfigPath) ? require $securityConfigPath : [];
            $jwtConfig = $securityConfig['jwt'] ?? [];

            $secret = $jwtConfig['secret'] ?? ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: null);
            if (empty($secret)) {
                throw new \Exception('JWT secret is not configured');
            }
            
            // Conditional debug logging (safe)
            if ($securityConfig['debug']['enabled'] ?? false) {
                $secretHash = substr(hash('sha256', (string)$secret), 0, 8);
                $tokenPrefix = substr($token, 0, 12);
                error_log('[JWT DEBUG][Middleware] secretHash=' . $secretHash . ' token.prefix=' . $tokenPrefix);
            }

            // Decode and verify token with leeway for clock skew
            try {
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));
                $decodedArray = (array)$decoded;
                
                // Additional token expiration check
                if (isset($decodedArray['exp']) && $decodedArray['exp'] < time()) {
                    throw new ExpiredException('Token has expired');
                }
            } catch (ExpiredException $e) {
                throw new \Exception('Token has expired');
            } catch (SignatureInvalidException $e) {
                throw new \Exception('Invalid token signature');
            } catch (BeforeValidException $e) {
                throw new \Exception('Token not yet valid');
            } catch (DomainException | UnexpectedValueException $e) {
                throw new \Exception('Invalid token format');
            }

            // Check if tenant_id exists in the token
            if (empty($decodedArray['tenant_id'])) {
                $this->logger->warning('Tenant ID is missing in the JWT token');
                throw new \Exception('Tenant ID missing in token');
            }

        // Ensure we have all required user data with consistent format
        $userData = [
            'id'         => $decodedArray['user_id'] ?? $decodedArray['id'] ?? null,
            'username'   => $decodedArray['username']  ?? null,
            'email'      => $decodedArray['email']     ?? null,
            'role'       => $decodedArray['role']      ?? null,
            'role_id'    => $decodedArray['role_id']   ?? null,
            'tenant_id'  => $decodedArray['tenant_id'] ?? null,
            'full_name'  => $decodedArray['full_name'] ?? $decodedArray['name'] ?? null,
            'is_owner'   => (int) ($decodedArray['is_owner'] ?? 0),
        ];

        // Safe debug logging (only in debug mode, no sensitive data)
        if ($securityConfig['debug']['enabled'] ?? false) {
            error_log('[JWT] Final user_id=' . ($userData['id'] ?? 'null') 
                . ' tenant=' . ($userData['tenant_id'] ?? 'null'));
        }

        // Attach user data and tenant_id to the request
        // Also attach user_id for backward compatibility with existing handlers
        $request = $request
            ->withAttribute('user', $userData)
            ->withAttribute('tenant_id', $userData['tenant_id'])
            ->withAttribute('user_id', $userData['id']);

        return $handler->handle($request);
    } catch (\Exception $e) {
        $statusCode = 401;
        $errorMessage = 'Unauthorized';
        
        // More specific error messages for different exception types
        if ($e instanceof ExpiredException) {
            $errorMessage = 'Session expired. Please log in again.';
        } elseif ($e instanceof SignatureInvalidException) {
            $errorMessage = 'Invalid token signature';
        } elseif ($e->getMessage() === 'Token has been blacklisted') {
            $errorMessage = 'Session has been terminated. Please log in again.';
            $statusCode = 403; // Forbidden
        }
        
        $this->logger->warning('JWT Authentication Error', ['error' => $e->getMessage()]);
        
        $response = $response->withStatus($statusCode);
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $errorMessage
        ], JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }
}

    /**
     * Get token from request header
     * @param ServerRequestInterface $request
     * @return string|null
     */
    private function getTokenFromHeader(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        
        if (empty($header)) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
    

}
