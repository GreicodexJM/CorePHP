<?php

declare(strict_types=1);

// Fixture for the SECURE pillar — dangerous primitives. Never executed; these
// exist only so the analyzer has something to flag.

function danger(string $code, string $blob): void
{
    eval($code);                 // SECURE: eval
    $obj = unserialize($blob);   // SECURE: unserialize
    exec('ls -la');              // SECURE: exec
    assert('1 === 1');           // SECURE: assert with string
}
