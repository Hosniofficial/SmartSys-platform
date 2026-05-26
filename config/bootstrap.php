<?php

use DI\Container;

return function (Container $container): void {
    // Environment variables already loaded in container.php
    
    // Initialize Sentry for error tracking (if configured and available)
    if (($_ENV['SENTRY_ENABLED'] ?? false) && @function_exists('\\Sentry\\init')) {
        $sentryDsn = $_ENV['SENTRY_DSN'] ?? null;
        if ($sentryDsn) {
            try {
                /** @phpstan-ignore-next-line */
                @\Sentry\init([
                    'dsn' => $sentryDsn,
                    'environment' => $_ENV['APP_ENV'] ?? 'production',
                    'traces_sample_rate' => (float)($_ENV['SENTRY_TRACES_SAMPLE_RATE'] ?? 0.1),
                    'profiles_sample_rate' => (float)($_ENV['SENTRY_PROFILES_SAMPLE_RATE'] ?? 0.1),
                    'attach_stacktrace' => true,
                    'max_breadcrumbs' => 50,
                ]);
            } catch (\Throwable $e) {
                error_log('Sentry initialization failed: ' . $e->getMessage());
            }
        }
    }

    // Set error handler for Sentry
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (($_ENV['SENTRY_ENABLED'] ?? false) && @function_exists('\\Sentry\\captureException')) {
            try {
                /** @phpstan-ignore-next-line */
                @\Sentry\captureException(new ErrorException($errstr, 0, $errno, $errfile, $errline));
            } catch (\Throwable $e) {
                // Silently fail if Sentry is unavailable
            }
        }
        return false; // Continue with PHP's internal error handler
    });

    // Set exception handler for Sentry
    set_exception_handler(function(Throwable $exception) {
        if (($_ENV['SENTRY_ENABLED'] ?? false) && @function_exists('\\Sentry\\captureException')) {
            try {
                /** @phpstan-ignore-next-line */
                @\Sentry\captureException($exception);
            } catch (\Throwable $e) {
                // Silently fail if Sentry is unavailable
            }
        }
        throw $exception; // Re-throw for normal handling
    });
};
