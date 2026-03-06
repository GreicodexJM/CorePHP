<?php

declare(strict_types=1);

/**
 * CorePHP (PHP-JVM) — Prepend Sandbox (bootstrap.php)
 *
 * Registered via auto_prepend_file in php.ini.
 * Runs BEFORE any user code on every script execution.
 *
 * Responsibilities:
 *   1. Convert ALL PHP errors/warnings/notices into ErrorException (no silent failures)
 *   2. Register a global uncaught Throwable handler for audit logging
 *   3. Initialize the Monolog global audit logger
 *   4. Install runkit7 native function overrides via FunctionOverrider
 *   5. Register global class aliases (ArrayList, BaseObject, Dict, Safe)
 *      so JVM-style classes are available without `use` statements
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
$stdAutoload = '/opt/php-jvm/std/vendor/autoload.php';
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
            exit(1);
        }
    }
);

// ---------------------------------------------------------------------------
// 4. FUNCTION OVERRIDER — Install runkit7 native function overrides
//    Replaces PHP's silent-failure built-ins with exception-throwing versions.
//    Only runs if runkit7 extension is loaded (Docker/VPS mode).
// ---------------------------------------------------------------------------
if (extension_loaded('runkit7') && class_exists(\std\Engine\FunctionOverrider::class)) {
    \std\Engine\FunctionOverrider::install();
}

// ---------------------------------------------------------------------------
// 5. GLOBAL CLASS ALIASES — JVM-style imports
//
//    Register short, global aliases for the most common std classes so they
//    are available in ANY file without a `use` statement.
//
//    Java:    import java.util.ArrayList;
//    CorePHP: (nothing required — ArrayList is always available)
//
//    Full class → Global alias:
//      std\Vec                    → ArrayList
//      std\Dict                   → Dict   (already short; exposes to global ns)
//      std\Any                    → BaseObject
//      std\StrictObject           → StrictObject (global alias)
//      std\Security\Safe\Safe     → Safe
//      std\IO                     → IO (exposes to global ns)
// ---------------------------------------------------------------------------
if (class_exists(\std\Vec::class)) {
    class_alias(\std\Vec::class,                    'ArrayList');
}
if (class_exists(\std\Dict::class)) {
    class_alias(\std\Dict::class,                   'Dict');
}
if (class_exists(\std\Any::class)) {
    class_alias(\std\Any::class,                    'BaseObject');
}
if (class_exists(\std\StrictObject::class)) {
    class_alias(\std\StrictObject::class,           'StrictObject');
}
if (class_exists(\std\Security\Safe\Safe::class)) {
    class_alias(\std\Security\Safe\Safe::class,     'Safe');
}
if (class_exists(\std\IO::class)) {
    class_alias(\std\IO::class,                     'IO');
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

    $logger  = new \Monolog\Logger('php-jvm');
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
