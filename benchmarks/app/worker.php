<?php

declare(strict_types=1);

/**
 * CorePHP entrypoint — runs the shared workload inside a long-lived RoadRunner
 * worker (the persistent model: the interpreter and autoloader initialise ONCE,
 * then handle many requests). bootstrap.php runs first via auto_prepend_file.
 */

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

// RoadRunner runtime deps live in the image at /app/vendor.
require '/app/vendor/autoload.php';
require __DIR__ . '/app.php';

$worker = Worker::create();
$factory = new Psr17Factory();
$psr7   = new PSR7Worker($worker, $factory, $factory, $factory);

// Persistent: bootstrap runs ONCE per worker, then is reused for every request.
$ctx = bench_bootstrap();

while (true) {
    $request = $psr7->waitRequest();
    if ($request === null) {
        break;
    }

    try {
        $psr7->respond(new Response(
            200,
            ['Content-Type' => 'application/json'],
            (string) json_encode(bench_handle($ctx)),
        ));
    } catch (\Throwable $e) {
        $psr7->respond(new Response(500, [], (string) json_encode(['error' => $e->getMessage()])));
    }
}
