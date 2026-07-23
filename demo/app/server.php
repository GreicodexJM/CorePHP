<?php

declare(strict_types=1);

/**
 * Stock-PHP entrypoint (php -S built-in server) — models the traditional
 * runtime with default error handling: warnings are logged and swallowed,
 * execution continues, and the app returns whatever it computed.
 */

require __DIR__ . '/app.php';

$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

header('Content-Type: application/json');
echo json_encode(app_dispatch($path));
