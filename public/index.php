<?php

declare(strict_types=1);

// ══════════════════════════════════════════════════════════
// Static files handler — Dev only (PHP built-in server)
// يخدّم الصور والـ CSS والـ JS مباشرةً بدون routing
// ══════════════════════════════════════════════════════════
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$staticFile = __DIR__ . $uri;
if ($uri !== '/' && is_file($staticFile)) {
    return false;
}
// ══════════════════════════════════════════════════════════

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use Slim\Factory\AppFactory;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    $config = require __DIR__ . '/../config/security.php';
    $corsConfig = is_array($config) ? ($config['cors'] ?? []) : [];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = (array) ($corsConfig['origin'] ?? []);

    if ($origin !== '' && (in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true))) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    $requestedMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '';
    $methods = $requestedMethod !== '' ? $requestedMethod : implode(', ', (array) ($corsConfig['methods'] ?? []));
    if ($methods !== '') {
        header('Access-Control-Allow-Methods: ' . $methods);
    }

    $requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
    if ($requestedHeaders !== '') {
        header('Access-Control-Allow-Headers: ' . $requestedHeaders);
    } else {
        $headers = (array) ($corsConfig['headers'] ?? []);
        if (!empty($headers)) {
            header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
        }
    }

    if (!empty($corsConfig['credentials'])) {
        header('Access-Control-Allow-Credentials: true');
    }

    if (isset($corsConfig['max_age'])) {
        header('Access-Control-Max-Age: ' . (string) (int) $corsConfig['max_age']);
    }

    http_response_code(204);
    exit;
}

// ── normalize URI: /api/something → /api/v1/something ────────────────────
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (preg_match('#^/api/(?!v1/)(.*)#', $uri, $m)) {
    $_SERVER['REQUEST_URI'] = '/api/v1/' . $m[1];
}

// ── Container ─────────────────────────────────────────────────────────────
$container = (require __DIR__ . '/../config/container.php')();
(require __DIR__ . '/../config/bootstrap.php')($container);

// ── App ───────────────────────────────────────────────────────────────────
AppFactory::setContainer($container);
$app = AppFactory::create();

// ── Middleware ────────────────────────────────────────────────────────────
(require __DIR__ . '/../config/middleware.php')($app, $container);

// ── Routes ────────────────────────────────────────────────────────────────
(require __DIR__ . '/../config/routes.php')($app, $container);

// ── Run ───────────────────────────────────────────────────────────────────
$app->run();