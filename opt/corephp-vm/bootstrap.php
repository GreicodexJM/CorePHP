<?php

declare(strict_types=1);

/**
 * CorePHP — Prepend Sandbox (bootstrap.php)
 *
 * Registered via auto_prepend_file in php.ini.
 * Runs BEFORE any user code on every script execution.
 *
 * Responsibilities:
 *   1. Convert ALL PHP errors/warnings/notices into ErrorException (no silent failures)
 *   2. Register a global uncaught Throwable handler for audit logging
 *   3. Initialize the Monolog global audit logger
 *   4. Register global class aliases (ArrayList, BaseObject, Dict, IO)
 *      so JVM-style classes are available without `use` statements
 *
 * NOTE: CorePHP does not transparently override native functions (the runkit7
 * Layer-2 mechanism was removed — it segfaulted on PHP 8.4). Safety comes from
 * the error handler here, the pure-PHP s_*() shims, disable_functions, and PHPStan.
 */

// ---------------------------------------------------------------------------
// Guard: prevent double-execution in RoadRunner worker restarts
// ---------------------------------------------------------------------------
if (defined('PHP_JVM_BOOTSTRAP_LOADED')) {
    return;
}
define('PHP_JVM_BOOTSTRAP_LOADED', true);

// ---------------------------------------------------------------------------
// Autoload: std library + Monolog
// ---------------------------------------------------------------------------
$stdAutoload = '/opt/corephp-vm/std/vendor/autoload.php';
if (file_exists($stdAutoload)) {
    require_once $stdAutoload;
}

// Application autoload (if running inside an app container)
$appAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($appAutoload)) {
    require_once $appAutoload;
}

// ---------------------------------------------------------------------------
// 1. ERROR HANDLER — Convert all PHP errors to ErrorException
//    Ensures E_WARNING, E_NOTICE, E_DEPRECATED, E_STRICT are catchable.
// ---------------------------------------------------------------------------
set_error_handler(
    static function (
        int    $severity,
        string $message,
        string $file,
        int    $line
    ): bool {
        // Honour the @ suppression operator
        if ((error_reporting() & $severity) === 0) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    },
    E_ALL
);

// ---------------------------------------------------------------------------
// 2. GLOBAL AUDIT LOGGER — Monolog
// ---------------------------------------------------------------------------
$auditLogger = buildAuditLogger();

// ---------------------------------------------------------------------------
// 3. EXCEPTION HANDLER — Last-resort uncaught Throwable audit
// ---------------------------------------------------------------------------
set_exception_handler(
    static function (\Throwable $e) use ($auditLogger): void {
        $auditLogger->critical(
            sprintf(
                '[UNCAUGHT][%s] %s in %s:%d',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ),
            ['trace' => $e->getTraceAsString()]
        );
        if (PHP_SAPI === 'cli') {
            // Last-resort handler for a Throwable that escaped the worker loop (e.g. a
            // boot/startup failure). Exiting is correct — the worker cannot safely
            // continue; RoadRunner's supervisor respawns it.
            // corephp-audit-ignore
            exit(1);
        }
    }
);

// ---------------------------------------------------------------------------
// SAFE FUNCTIONS — pure-PHP, no engine hacks
//
//    CorePHP does NOT transparently override native functions. (The old runkit7
//    Layer-2 override was removed — the unofficial PHP 8.4 build segfaulted the
//    process at shutdown on any internal-function redefine.) Instead, SAFE
//    behaviour comes from:
//      - the Layer-3 error handler above (warnings/notices → ErrorException),
//      - the pure-PHP s_*() shims + azjezz/psl (typed, throwing safe API),
//      - dangerous primitives disabled in php.ini (disable_functions),
//      - PHPStan Level 9 (static enforcement).
//    Use s_json()/s_file()/s_int()/s_b64()/s_replace()/... instead of the raw
//    native functions, which still fail silently.
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// 4. GLOBAL CLASS ALIASES — JVM-style imports
//
//    Register short, global aliases for the most common core classes so they
//    are available in ANY file without a `use` statement.
//
//    Java:    import java.util.ArrayList;
//    CorePHP: (nothing required — ArrayList is always available)
//
//    Full class → Global alias:
//      core\Vec                    → ArrayList
//      core\Dict                   → Dict   (already short; exposes to global ns)
//      core\Any                    → BaseObject
//      core\StrictObject           → StrictObject (global alias)
//      core\IO                     → IO (exposes to global ns)
//
//    Note: core\Security\Safe\Safe was deleted — alias removed.
//          Use s_*() shims or azjezz/psl directly.
// ---------------------------------------------------------------------------
if (class_exists(\core\Vec::class)) {
    class_alias(\core\Vec::class,          'ArrayList');
}
if (class_exists(\core\Dict::class)) {
    class_alias(\core\Dict::class,         'Dict');
}
if (class_exists(\core\Any::class)) {
    class_alias(\core\Any::class,          'BaseObject');
}
if (class_exists(\core\StrictObject::class)) {
    class_alias(\core\StrictObject::class, 'StrictObject');
}
if (class_exists(\core\IO::class)) {
    class_alias(\core\IO::class,           'IO');
}

// ---------------------------------------------------------------------------
// Helper: Build the Monolog audit logger
// ---------------------------------------------------------------------------
function buildAuditLogger(): \Psr\Log\LoggerInterface
{
    if (!class_exists(\Monolog\Logger::class)) {
        // Monolog not available — return a null logger that writes to error_log
        return new class implements \Psr\Log\LoggerInterface {
            public function emergency(string|\Stringable $message, array $context = []): void { $this->log('emergency', $message, $context); }
            public function alert(string|\Stringable $message, array $context = []): void     { $this->log('alert',     $message, $context); }
            public function critical(string|\Stringable $message, array $context = []): void  { $this->log('critical',  $message, $context); }
            public function error(string|\Stringable $message, array $context = []): void     { $this->log('error',     $message, $context); }
            public function warning(string|\Stringable $message, array $context = []): void   { $this->log('warning',   $message, $context); }
            public function notice(string|\Stringable $message, array $context = []): void    { $this->log('notice',    $message, $context); }
            public function info(string|\Stringable $message, array $context = []): void      { $this->log('info',      $message, $context); }
            public function debug(string|\Stringable $message, array $context = []): void     { $this->log('debug',     $message, $context); }
            public function log(mixed $level, string|\Stringable $message, array $context = []): void {
                error_log(sprintf('[%s] %s', strtoupper((string) $level), $message));
            }
        };
    }

    $logger  = new \Monolog\Logger('corephp');
    $handler = new \Monolog\Handler\StreamHandler(
        '/var/log/php/audit.log',
        \Monolog\Level::Debug
    );
    $handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
    $logger->pushHandler($handler);

    // Also log to stderr so RoadRunner captures it
    $stderrHandler = new \Monolog\Handler\StreamHandler('php://stderr', \Monolog\Level::Warning);
    $stderrHandler->setFormatter(new \Monolog\Formatter\LineFormatter());
    $logger->pushHandler($stderrHandler);

    return $logger;
}
