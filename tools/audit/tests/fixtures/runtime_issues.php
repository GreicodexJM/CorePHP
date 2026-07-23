<?php

declare(strict_types=1);

// Fixture for the new STABLE global-runtime-mutation rule and the additional
// SAFE silent-failure functions. Never executed.

function boot(): void
{
    date_default_timezone_set('UTC');   // STABLE: global-runtime-mutation
    ini_set('memory_limit', '256M');    // STABLE: global-runtime-mutation
    putenv('APP_ENV=prod');             // STABLE: global-runtime-mutation
    $level = error_reporting();         // NOT flagged — getter (no arguments)
    $GLOBALS['registry'] = $level;      // STABLE: globals-write
}

function read(): void
{
    $body = stream_get_contents(STDIN); // SAFE: stream_get_contents
    $home = getenv('HOME');             // SAFE: getenv (LOW)
    $when = strtotime('tomorrow');      // SAFE: strtotime (LOW)
    unset($body, $home, $when);
}
