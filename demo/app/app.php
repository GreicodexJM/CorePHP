<?php

declare(strict_types=1);

/**
 * An ordinary, UNMODIFIED PHP application.
 *
 * Plain native functions — no CorePHP APIs, no s_*() shims, no try/catch. This
 * is the code you already have. The ONLY thing that changes between runtimes is
 * what happens when the latent bug on /order fires.
 *
 * The bug: a recent ops deploy renamed the pricing key `unit_price` → `price`
 * (classic config drift). This app was written against `unit_price`, so it now
 * reads an array key that no longer exists.
 *
 *   - On stock PHP: "Undefined array key" is just a WARNING. Execution continues
 *     with null, null * 3 = 0, and the app cheerfully returns an order total of
 *     $0.00 with HTTP 200. You just shipped a widget for free — silently.
 *
 *   - On CorePHP: the boot error handler turns that warning into a typed,
 *     traceable exception at the exact line; the worker catches it, logs it with
 *     a full stack trace + request path, and returns a clean 500. The bug is
 *     caught loudly, at its source — and the worker keeps serving.
 */

/**
 * @return array<string, mixed>
 */
function app_dispatch(string $path): array
{
    return match ($path) {
        '/health' => ['status' => 'ok'],
        '/order'  => handle_order(),
        default   => ['app' => 'drop-in demo', 'routes' => ['/health', '/order']],
    };
}

/**
 * @return array<string, mixed>
 */
function handle_order(): array
{
    $raw    = file_get_contents(__DIR__ . '/data/pricing.json');
    $config = json_decode((string) $raw, true);

    // BUG: the pricing config drifted — 'unit_price' no longer exists.
    $unitPrice = $config['catalog']['widget']['unit_price'];

    $qty   = 3;
    $total = $qty * $unitPrice;

    return [
        'item'        => 'widget',
        'qty'         => $qty,
        'unit_price'  => $unitPrice,
        'total_cents' => $total,
    ];
}
