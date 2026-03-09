<?php

declare(strict_types=1);

/**
 * CorePHP (PHP-JVM) — RoadRunner Worker
 *
 * This is the long-lived PHP process — the equivalent of a JVM process.
 * It starts once, handles many requests, and NEVER dies due to application errors.
 *
 * Flow:
 *   1. bootstrap.php is loaded automatically via auto_prepend_file (before this script)
 *   2. Worker connects to RoadRunner via spiral/roadrunner-http
 *   3. Request loop starts — each request is handled in a try/catch
 *   4. On Throwable: error is audited, 500 response sent, worker continues
 *   5. Worker restarts gracefully after max_jobs (configured in .rr.yaml)
 */

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

// Composer autoload — loads std library + application dependencies
require_once __DIR__ . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Bootstrap RoadRunner PSR-7 worker
// ---------------------------------------------------------------------------
$worker    = Worker::create();
$factory   = new Psr17Factory();
$psr7      = new PSR7Worker($worker, $factory, $factory, $factory);

// ---------------------------------------------------------------------------
// Request loop — this runs for the lifetime of the worker process
// NEVER allow an unhandled exception to reach here without a response
// ---------------------------------------------------------------------------
while (true) {
    try {
        $request = $psr7->waitRequest();

        // Null means RoadRunner is shutting down this worker gracefully
        if ($request === null) {
            break;
        }

        // -----------------------------------------------------------------------
        // APPLICATION DISPATCH POINT
        // Replace the block below with your application's front controller.
        // Example: $response = $app->handle($request);
        // -----------------------------------------------------------------------
        $response = handleRequest($request);

        $psr7->respond($response);

    } catch (\Throwable $e) {
        // ------------------------------------------------------------------
        // GLOBAL SAFETY NET — The worker MUST NOT die
        // Log the error via the audit handler (registered in bootstrap.php)
        // then return a clean 500 response to the client
        // ------------------------------------------------------------------
        auditThrowable($e);

        try {
            $psr7->respond(
                new Response(
                    500,
                    ['Content-Type' => 'application/json'],
                    s_enc([
                        'error'   => 'Internal Server Error',
                        'message' => getenv('APP_ENV') === 'development'
                            ? $e->getMessage()
                            : 'An unexpected error occurred.',
                    ])
                )
            );
        } catch (\Throwable $responseError) {
            // Even responding failed — try to send a minimal response
            $worker->error((string) $responseError);
        }
    }
}

// ---------------------------------------------------------------------------
// Application dispatch function
// Replace with your router / framework front controller
// ---------------------------------------------------------------------------
function handleRequest(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
{
    // Default health-check endpoint
    if ($request->getUri()->getPath() === '/health') {
        return new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}');
    }

    // Default response — replace with actual routing
    return new Response(
        200,
        ['Content-Type' => 'application/json'],
        s_enc([
            'runtime' => 'CorePHP PHP-JVM',
            'php'     => PHP_VERSION,
            'path'    => $request->getUri()->getPath(),
        ])
    );
}

// ---------------------------------------------------------------------------
// Audit helper — delegates to the Monolog handler installed in bootstrap.php
// The global $auditLogger is set by bootstrap.php
// ---------------------------------------------------------------------------
function auditThrowable(\Throwable $e): void
{
    global $auditLogger;

    if ($auditLogger instanceof \Psr\Log\LoggerInterface) {
        $auditLogger->critical(
            sprintf(
                '[%s] %s in %s:%d',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ),
            ['trace' => $e->getTraceAsString()]
        );
    } else {
        // Fallback: write to PHP error log
        error_log(sprintf(
            'UNCAUGHT [%s] %s in %s:%d%s%s',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
            $e->getTraceAsString()
        ));
    }
}
