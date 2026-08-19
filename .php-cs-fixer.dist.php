<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__.'/packages', __DIR__.'/tests/Release', __DIR__.'/tests/Support', __DIR__.'/tools'])
    ->name('*.php')
    // Individual demo scripts, never the examples/ tree: the demo target is
    // immutable fixture input whose bytes the committed reports fingerprint, so
    // its PHP files must not be rewritten by the fixer.
    ->append([
        __DIR__.'/packages/cli/bin/upgrade-intel',
        __DIR__.'/examples/five-minute-demo/regenerate-reports.php',
        __DIR__.'/examples/five-minute-demo/summarize-report.php',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'native_function_invocation' => false,
        'single_quote' => true,
    ])
    ->setFinder($finder);
