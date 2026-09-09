<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Test-only stub replacing the real MonologHandler (which requires the
 * monolog/monolog composer package, unreachable in this sandboxed network).
 * Same public interface, just prints to stdout so test output stays readable.
 * NOT used anywhere in the actual source tree — swapped in only by the test
 * autoloader below, purely for exercising real business-logic classes.
 */
class MonologHandler
{
    private static array $instances = [];
    private string $channel;

    public function __construct($channel = 'erp') { $this->channel = $channel; }

    public static function getInstance($channel = 'erp'): self
    {
        if (!isset(self::$instances[$channel])) {
            self::$instances[$channel] = new self($channel);
        }
        return self::$instances[$channel];
    }

    private function log(string $level, string $msg, array $ctx = []): void
    {
        $ctxStr = $ctx ? (' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE)) : '';
        fwrite(STDERR, "[{$this->channel}.{$level}] {$msg}{$ctxStr}\n");
    }

    public function debug($m, array $c = []): void { $this->log('debug', $m, $c); }
    public function info($m, array $c = []): void { $this->log('info', $m, $c); }
    public function warning($m, array $c = []): void { $this->log('warning', $m, $c); }
    public function error($m, array $c = []): void { $this->log('error', $m, $c); }
    public function critical($m, array $c = []): void { $this->log('critical', $m, $c); }
    public function alert($m, array $c = []): void { $this->log('alert', $m, $c); }
    public function emergency($m, array $c = []): void { $this->log('emergency', $m, $c); }
}
