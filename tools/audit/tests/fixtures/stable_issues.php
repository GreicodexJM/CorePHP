<?php

declare(strict_types=1);

// Fixture for the STABLE pillar — persistent-worker footguns. Never executed.

class Registry
{
    /** @var array<string, mixed> */
    public static array $cache = [];   // STABLE: static property
}

function handle(): void
{
    global $config;                    // STABLE: global state

    static $counter = 0;               // STABLE: static variable
    $counter++;

    if ($counter > 100) {
        exit(1);                       // STABLE: worker-exit
    }
}
