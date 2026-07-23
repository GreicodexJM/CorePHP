<?php

declare(strict_types=1);

/**
 * Shared benchmark workload — deliberately DEPENDENCY-FREE so the PHP-FPM
 * baseline and the CorePHP persistent worker run byte-identical logic.
 *
 * The workload has two phases, mirroring a real application:
 *
 *   bench_bootstrap()  — framework-style initialisation (config tree, route
 *                        compilation, service container). A COLD-START runtime
 *                        (PHP-FPM) pays this on EVERY request; a PERSISTENT
 *                        runtime (CorePHP) pays it ONCE per worker, then reuses
 *                        the result. NOTE: opcache caches compiled *opcodes*, not
 *                        the *runtime cost* of building these structures — so
 *                        PHP-FPM pays this every request even with opcache on.
 *                        This is precisely why Laravel Octane / Swoole exist.
 *
 *   bench_handle($ctx) — the per-request work, using the bootstrapped context.
 *
 * The two runtimes produce byte-identical responses (verified by run.sh); the
 * only variable is WHERE bootstrap runs — once (CorePHP) vs per-request (FPM).
 */

final class Money
{
    public function __construct(
        public readonly int $cents,
        public readonly string $currency,
    ) {
    }

    public function formatted(): string
    {
        return sprintf('%s %0.2f', $this->currency, $this->cents / 100);
    }
}

/**
 * Framework-style bootstrap — the amortizable cost.
 *
 * @return array{config: array<string, mixed>, routes: list<array<string, string>>, services: array<string, Money>}
 */
function bench_bootstrap(): array
{
    // Config: parse a sizeable config tree (simulates many config files).
    $config = [];
    for ($i = 0; $i < 600; $i++) {
        $config["service.{$i}"] = [
            'id'      => $i,
            'token'   => hash('sha256', "config-{$i}"),
            'options' => range(0, 8),
            'enabled' => ($i % 2) === 0,
        ];
    }

    // Router: compile a route table of regex patterns.
    $routes = [];
    for ($i = 0; $i < 300; $i++) {
        $routes[] = [
            'method'  => 'GET',
            'pattern' => '#^/api/v1/resource' . $i . '/(?P<id>\d+)$#',
            'handler' => "App\\Controller\\Resource{$i}Controller@show",
        ];
    }

    // Service container: instantiate service value objects.
    $services = [];
    for ($i = 0; $i < 300; $i++) {
        $services["svc.{$i}"] = new Money($i * 97, 'USD');
    }

    // Config cache round-trip (simulates reading a serialised, cached config).
    $cached = json_decode((string) json_encode($config), true);

    return [
        'config'   => is_array($cached) ? $cached : $config,
        'routes'   => $routes,
        'services' => $services,
    ];
}

/**
 * Per-request work using the already-bootstrapped context.
 *
 * @param array{config: array<string, mixed>, routes: list<array<string, string>>, services: array<string, Money>} $ctx
 *
 * @return array<string, mixed>
 */
function bench_handle(array $ctx): array
{
    // Route a request against the compiled table.
    $path    = '/api/v1/resource42/12345';
    $matched = null;
    foreach ($ctx['routes'] as $route) {
        if (preg_match($route['pattern'], $path, $m) === 1) {
            $matched = ['handler' => $route['handler'], 'id' => $m['id']];
            break;
        }
    }

    // A little per-request compute.
    $items = [];
    for ($i = 1; $i <= 25; $i++) {
        $items[] = ['id' => $i, 'sku' => hash('sha256', "item-{$i}")];
    }

    $payload = [
        'matched'     => $matched,
        'services'    => count($ctx['services']),
        'config_keys' => count($ctx['config']),
        'items'       => $items,
    ];

    $decoded = json_decode((string) json_encode($payload), true);

    return is_array($decoded) ? $decoded : [];
}
