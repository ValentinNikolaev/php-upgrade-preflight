<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Analysis;

use PhpUpgradePreflight\Core\Analysis\AnalysisTimings;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PHPUnit\Framework\TestCase;

final class AnalysisTimingsTest extends TestCase
{
    public function testOptionalTimingsPreserveTheReportAndResetForAnEarlyInputFailure(): void
    {
        $timings = new AnalysisTimings();
        $runner = new ComposerScenarioRunner(processRunner: static function (array $command, string $directory): array {
            if ($command[1] !== 'validate') {
                file_put_contents($directory . '/composer.lock', '{"packages":[{"name":"fixture/dependency","version":"2.0.0"}],"packages-dev":[]}');
            }

            return ['exit_code' => 0, 'stdout' => 'Resolved.', 'stderr' => ''];
        });
        $path = dirname(__DIR__, 5) . '/tests/fixtures/project-isolation';
        $request = new UpgradeRequest($path, [new UpgradeTarget('fixture/dependency', '^2.0')]);
        $analyzer = new DefaultUpgradeAnalyzer(scenarioRunner: $runner, timings: $timings);
        $profiled = $analyzer->analyzeUpgrade($request)->toArray();
        $plain = (new DefaultUpgradeAnalyzer(scenarioRunner: $runner))->analyzeUpgrade($request)->toArray();
        foreach (array_keys($plain['resolution']['scenarios']) as $index) {
            $plain['resolution']['scenarios'][$index]['duration_ms'] = 0;
            $profiled['resolution']['scenarios'][$index]['duration_ms'] = 0;
        }
        self::assertSame($plain, $profiled, 'Instrumentation must preserve canonical data and all checks.');
        $snapshot = $timings->snapshot();
        self::assertSame('completed', $snapshot['status']);
        self::assertGreaterThan(0, $snapshot['total_ms']);
        foreach ($snapshot['timings_ms'] as $duration) {
            self::assertGreaterThanOrEqual(0, $duration);
        }
        self::assertLessThanOrEqual($snapshot['total_ms'], array_sum($snapshot['timings_ms']));
        self::assertStringNotContainsString($path, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $empty = sys_get_temp_dir() . '/analysis-timings-' . bin2hex(random_bytes(8));
        mkdir($empty, 0700);
        try {
            $analyzer->analyzeUpgrade(new UpgradeRequest($empty, [new UpgradeTarget('fixture/dependency', '^2.0')]));
            self::assertSame('completed', $timings->snapshot()['status'], 'An input-failure report still completes analysis.');
            self::assertSame(array_fill_keys(array_keys($snapshot['timings_ms']), 0.0), $timings->snapshot()['timings_ms']);
        } finally {
            rmdir($empty);
        }
    }

    public function testFailureClosesActiveOperationsAndResetDiscardsEarlierMeasurements(): void
    {
        $timings = new AnalysisTimings();
        self::assertSame('not-started', $timings->snapshot()['status']);
        $timings->reset();
        $timings->begin('ownership');
        $timings->complete(false);
        $snapshot = $timings->snapshot();
        self::assertSame('failed', $snapshot['status']);
        self::assertGreaterThanOrEqual(0, $snapshot['timings_ms']['ownership']);
        $timings->end('ownership');
        self::assertSame($snapshot, $timings->snapshot());
        $timings->reset();
        self::assertSame('running', $timings->snapshot()['status']);
        self::assertSame(0.0, $timings->snapshot()['total_ms']);
        self::assertSame(array_fill_keys(array_keys($snapshot['timings_ms']), 0.0), $timings->snapshot()['timings_ms']);
    }

    public function testUnknownOperationsCannotCreateArbitraryMetricKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AnalysisTimings())->begin('/private/project');
    }

    public function testAnAnalyzerFailureIsRecordedWithoutMaskingTheOriginalException(): void
    {
        $timings = new AnalysisTimings();
        $request = new UpgradeRequest(
            dirname(__DIR__, 5) . '/tests/fixtures/project-isolation',
            [new UpgradeTarget('fixture/dependency', '^2.0')],
            frameworks: ['missing-framework']
        );
        try {
            (new DefaultUpgradeAnalyzer(timings: $timings))->analyzeUpgrade($request);
            self::fail('The missing framework must fail analysis.');
        } catch (\InvalidArgumentException $failure) {
            self::assertStringContainsString('missing-framework', $failure->getMessage());
        }
        self::assertSame('failed', $timings->snapshot()['status']);
        self::assertGreaterThan(0, $timings->snapshot()['total_ms']);
    }
}
