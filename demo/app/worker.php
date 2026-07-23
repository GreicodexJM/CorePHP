<?php

declare(strict_types=1);

/**
 * CorePHP entrypoint — runs the SAME unmodified app.php in a persistent worker.
 *
 * bootstrap.php (auto_prepend_file) has already installed the error handler that
 * turns PHP warnings/notices into typed exceptions. Here we simply catch any
 * Throwable, audit it with full context, return a clean 500, and keep serving.
 */

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

require '/app/vendor/autoload.php';
require __DIR__ . '/app.php';

$worker  = Worker::create();
$factory = new Psr17Factory();
$psr7    = new PSR7Worker($worker, $factory, $factory, $factory);

while (true) {
    $request = $psr7->waitRequest();
    if ($request === null) {
        break;
    }

    $path = $request->getUri()->getPath();

    try {
        $data = app_dispatch($path);
        $psr7->respond(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($data)));
    } catch (\Throwable $e) {
        // The CorePHP boot error handler converted the silent "Undefined array
        // key" warning into this typed, traceable exception. Audit it with the
        // request path and full stack trace — nothing fails silently.
        global $auditLogger;
        if ($auditLogger instanceof \Psr\Log\LoggerInterface) {
            $auditLogger->critical(
                sprintf('[%s] %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
                ['path' => $path, 'trace' => $e->getTraceAsString()],
            );
        }

        $psr7->respond(new Response(
            500,
            ['Content-Type' => 'application/json'],
            (string) json_encode([
                'error'  => 'Internal Server Error',
                'type'   => get_class($e),
                'detail' => $e->getMessage(),
                'at'     => basename($e->getFile()) . ':' . $e->getLine(),
            ]),
        ));
    }
}
