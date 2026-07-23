<?php

declare(strict_types=1);

// Fixture for the SAFE pillar — silent-failure native calls. Never executed.

function load_user(string $raw): array
{
    $data   = json_decode($raw, true);        // SAFE: json_decode
    $config = file_get_contents('/etc/app');  // SAFE: file_get_contents
    $count  = intval($_GET['n'] ?? '0');       // SAFE: intval (LOW)

    return [$data, $config, $count];
}
