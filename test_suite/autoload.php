<?php
declare(strict_types=1);

// Loads the REAL, unmodified SmartSys source files directly from the repo,
// so tests exercise the actual production code (including today's fixes) —
// except MonologHandler, which is swapped for a dependency-free stub because
// the real one requires monolog/monolog, unreachable via composer/packagist
// in this sandboxed network. This is the ONLY substitution; everything else
// (AccountingService, SalePaymentService, SaleApprovalService, ReturnService,
// CashVoucherService, CostingService, IdempotencyService, TransactionManager,
// SettingsRepository, CostCenterService, StockTransferHandler, PurchaseService,
// Handlers, Middleware)
// loads verbatim from /home/claude/smartsys/api/v1/src (PSR-4 compliant structure).

define('SMARTSYS_SRC', '/home/claude/smartsys/api/v1/src');
define('TEST_STUBS', __DIR__ . '/stubs');
define('PSR_HTTP_MESSAGE_SRC', __DIR__ . '/psr-http-message/src');

spl_autoload_register(function (string $class) {
    if ($class === 'App\\Services\\MonologHandler') {
        require TEST_STUBS . '/Services/MonologHandler.php';
        return;
    }

    if (str_starts_with($class, 'Psr\\Http\\Message\\')) {
        $rel  = substr($class, strlen('Psr\\Http\\Message\\'));
        $path = PSR_HTTP_MESSAGE_SRC . '/' . $rel . '.php';
        if (is_file($path)) { require $path; return; }
    }

    if (str_starts_with($class, 'App\\')) {
        $rel  = substr($class, strlen('App\\'));
        $path = SMARTSYS_SRC . '/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($path)) { require $path; return; }
    }
});

function test_pdo(): PDO
{
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=smarttest;charset=utf8mb4', 'testuser', 'testpass', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function assert_close(float $actual, float $expected, string $label, float $tol = 0.01): bool
{
    $ok = abs($actual - $expected) <= $tol;
    printf("[%s] %s — expected=%.2f actual=%.2f\n", $ok ? 'PASS' : 'FAIL', $label, $expected, $actual);
    return $ok;
}

function assert_eq(string $actual, string $expected, string $label): bool
{
    $ok = $actual === $expected;
    printf("[%s] %s — expected='%s' actual='%s'\n", $ok ? 'PASS' : 'FAIL', $label, $expected, $actual);
    return $ok;
}
