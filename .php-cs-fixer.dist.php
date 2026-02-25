<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

return (new Config())
    ->setRules([
        '@PER-CS' => true,
        'declare_strict_types' => true,
        'strict_param' => false,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw'],
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
