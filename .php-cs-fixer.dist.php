<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__.'/packages', __DIR__.'/tests/Release', __DIR__.'/tests/Support', __DIR__.'/tools'])
    ->name('*.php')
    ->append([__DIR__.'/packages/cli/bin/upgrade-intel'])
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
