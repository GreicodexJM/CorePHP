<?php

declare(strict_types=1);

/**
 * PHP-FPM entrypoint — runs the shared workload once per request (the
 * traditional cold-start model: the interpreter re-initialises every request).
 */

require __DIR__ . '/app.php';

// Cold start: the process dies after every request, so bootstrap runs EVERY time.
$ctx = bench_bootstrap();

header('Content-Type: application/json');
echo json_encode(bench_handle($ctx));
