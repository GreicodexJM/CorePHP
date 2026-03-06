<?php

/**
 * CorePHP (PHP-JVM) — PhpStorm Meta File
 *
 * Provides IDE autocomplete and type inference for:
 *   - Global class aliases (ArrayList, Dict, BaseObject, Safe, IO)
 *   - TypedCollection / Vec / Dict constructor type resolution
 *
 * This file is ONLY read by PhpStorm — it is never executed.
 * Drop it in your project root to enable zero-import DX.
 *
 * @see https://www.jetbrains.com/help/phpstorm/ide-advanced-metadata.html
 */

namespace PHPSTORM_META {

    // =========================================================================
    // TypedCollection — return type inference based on constructor argument
    // =========================================================================

    // std\Internal\Array\TypedCollection::__construct(Type::class)
    // When you write new TypedCollection(User::class), IDE knows the type is User
    override(
        \std\Internal\Array\TypedCollection::__construct(0),
        map(['' => '@'])
    );

    // std\Vec — same inference
    override(
        \std\Vec::__construct(0),
        map(['' => '@'])
    );

    // Global alias ArrayList — same inference
    override(
        \ArrayList::__construct(0),
        map(['' => '@'])
    );

    // =========================================================================
    // Dict — value type inference based on constructor argument
    // =========================================================================

    override(
        \std\Dict::__construct(0),
        map(['' => '@'])
    );

    override(
        \Dict::__construct(0),
        map(['' => '@'])
    );

    // =========================================================================
    // Safe::jsonDecode — return type hint
    // =========================================================================
    // PhpStorm will infer the return type as mixed (no better option for JSON)

    // =========================================================================
    // IO::json — reads JSON from file, returns mixed
    // =========================================================================

    // =========================================================================
    // Global alias → class mapping
    // This tells PhpStorm what class each global alias resolves to,
    // enabling full autocomplete without `use` statements.
    // =========================================================================
    // Note: class_alias() calls in bootstrap.php are sufficient for runtime.
    // The entries here provide design-time IDE support only.

    registerArgumentsSet(
        'corephp_type_names',
        'string',
        'int',
        'float',
        'bool',
        \stdClass::class,
    );

    expectedArguments(
        \std\Internal\Array\TypedCollection::__construct(),
        0,
        argumentsSet('corephp_type_names')
    );

    expectedArguments(
        \std\Vec::__construct(),
        0,
        argumentsSet('corephp_type_names')
    );

    expectedArguments(
        \ArrayList::__construct(),
        0,
        argumentsSet('corephp_type_names')
    );

    expectedArguments(
        \std\Dict::__construct(),
        0,
        argumentsSet('corephp_type_names')
    );

    expectedArguments(
        \Dict::__construct(),
        0,
        argumentsSet('corephp_type_names')
    );
}
