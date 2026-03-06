<?php

declare(strict_types=1);

/**
 * CorePHP — PHP-CS-Fixer Configuration
 *
 * Enforces:
 *   - declare(strict_types=1) on every PHP file
 *   - PSR-12 code style
 *   - PHP 8.3 syntax standards
 *   - No mixed types, no short tags, ordered imports
 */

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/opt/corephp-vm/std/src',
        __DIR__ . '/opt/corephp-vm/std/tests',
    ])
    ->name('*.php')
    ->exclude([
        'vendor',
        'node_modules',
        '.git',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // ---------------------------------------------------------------------------
        // PSR-12 baseline
        // ---------------------------------------------------------------------------
        '@PSR12'                 => true,
        '@PHP83Migration'        => true,

        // ---------------------------------------------------------------------------
        // CRITICAL: Enforce strict_types on every file
        // ---------------------------------------------------------------------------
        'declare_strict_types'   => true,

        // ---------------------------------------------------------------------------
        // Imports
        // ---------------------------------------------------------------------------
        'ordered_imports'        => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'      => true,
        'global_namespace_import'=> [
            'import_classes'   => false,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // ---------------------------------------------------------------------------
        // Type safety
        // ---------------------------------------------------------------------------
        'void_return'                  => true,
        'return_type_declaration'      => ['space_before' => 'none'],
        'nullable_type_declaration_for_default_null_value' => true,

        // ---------------------------------------------------------------------------
        // Modern PHP syntax
        // ---------------------------------------------------------------------------
        'modernize_types_casting'      => true,
        'short_scalar_cast'            => true,
        'cast_spaces'                  => ['space' => 'single'],
        'no_short_bool_cast'           => true,
        'explicit_string_variable'     => true,

        // ---------------------------------------------------------------------------
        // Array / collection
        // ---------------------------------------------------------------------------
        'array_syntax'                 => ['syntax' => 'short'],
        'no_multiline_whitespace_around_double_arrow' => true,
        'trim_array_spaces'            => true,
        'normalize_index_brace'        => true,

        // ---------------------------------------------------------------------------
        // Strings
        // ---------------------------------------------------------------------------
        'single_quote'                 => true,
        'no_binary_string'             => true,

        // ---------------------------------------------------------------------------
        // Spacing & layout
        // ---------------------------------------------------------------------------
        'concat_space'                 => ['spacing' => 'one'],
        'object_operator_without_whitespace' => true,
        'binary_operator_spaces'       => [
            'default'   => 'single_space',
            'operators' => ['=>' => 'align_single_space_minimal'],
        ],
        'trailing_comma_in_multiline'  => [
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ],

        // ---------------------------------------------------------------------------
        // PHPDoc
        // ---------------------------------------------------------------------------
        'phpdoc_align'                 => ['align' => 'vertical'],
        'phpdoc_order'                 => true,
        'phpdoc_scalar'                => true,
        'phpdoc_separation'            => true,
        'phpdoc_summary'               => true,
        'phpdoc_trim'                  => true,
        'phpdoc_types'                 => true,
        'phpdoc_var_without_name'      => true,
        'no_superfluous_phpdoc_tags'   => ['allow_mixed' => false],

        // ---------------------------------------------------------------------------
        // Security
        // ---------------------------------------------------------------------------
        'no_alias_functions'           => true,
        'no_homoglyph_names'           => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
