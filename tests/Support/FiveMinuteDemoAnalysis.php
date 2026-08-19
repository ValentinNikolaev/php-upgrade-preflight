<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Support;

use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\ExtensionAssumption;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;

/**
 * The single definition of the five-minute demo's analysis request.
 *
 * The deterministic test gate and `examples/five-minute-demo/regenerate-reports.php`
 * must analyze exactly the same request: the committed demo reports carry
 * candidate-state fingerprints, so a request that differs in any modeled input
 * silently produces reports the gate then rejects. Constructing the request here
 * also keeps it inside the statically analyzed paths, which `examples/` is not.
 */
final class FiveMinuteDemoAnalysis
{
    public const ABSENT_EXTENSION = 'ext-preflight-stage';

    public static function root(): string
    {
        return dirname(__DIR__, 2) . '/examples/five-minute-demo';
    }

    public static function targetPath(): string
    {
        return self::root() . '/target';
    }

    public static function canonicalJsonPath(): string
    {
        return self::root() . '/reports/laravel-10-to-13.json';
    }

    public static function canonicalMarkdownPath(): string
    {
        return self::root() . '/reports/laravel-10-to-13.md';
    }

    public static function summarizerPath(): string
    {
        return self::root() . '/summarize-report.php';
    }

    public static function request(?string $outputPath = null): UpgradeRequest
    {
        return new UpgradeRequest(
            self::targetPath(),
            [new UpgradeTarget('laravel/framework', '^13.0')],
            '8.1',
            '8.3',
            [],
            ['laravel'],
            ReportFormat::JSON,
            $outputPath ?? self::canonicalJsonPath(),
            false,
            [ExtensionAssumption::fromAbsenceInput(self::ABSENT_EXTENSION)],
            null,
            ComposerExecutionConfiguration::restricted()
        );
    }
}
