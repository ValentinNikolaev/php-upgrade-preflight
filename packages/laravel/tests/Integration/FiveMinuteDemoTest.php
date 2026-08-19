<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PhpUpgradePreflight\Tests\Support\FiveMinuteDemoAnalysis;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class FiveMinuteDemoTest extends TestCase
{
    public function testOfflineDemoUsesTheRealSolverAndKeepsTheTargetImmutable(): void
    {
        $target = $this->demoRoot() . '/target';
        $snapshot = FixtureSnapshot::capture($target);
        $previousDisableNetwork = getenv('COMPOSER_DISABLE_NETWORK');
        $previousRootVersion = getenv('COMPOSER_ROOT_VERSION');
        putenv('COMPOSER_DISABLE_NETWORK=1');
        putenv('COMPOSER_ROOT_VERSION=1.0.0');

        try {
            $report = (new DefaultUpgradeAnalyzer([new LaravelFrameworkIntegration()]))
                ->analyzeUpgrade(FiveMinuteDemoAnalysis::request());
        } finally {
            $this->restoreEnvironment('COMPOSER_DISABLE_NETWORK', $previousDisableNetwork);
            $this->restoreEnvironment('COMPOSER_ROOT_VERSION', $previousRootVersion);
        }

        $snapshot->assertUnchanged($this);
        self::assertSame('blocked', $report->resolutionStatus());
        $canonical = $report->toArray();
        $staged = $canonical['staged_resolution'];
        self::assertSame('evaluated', $staged['execution_state']);
        self::assertSame('blocked', $staged['status']);
        self::assertSame('blocking_registry_not_cleared', $staged['stop_reason']);
        self::assertCount(3, $staged['stages']);
        self::assertSame(
            ['laravel-10-to-11', 'laravel-11-to-12', 'laravel-12-to-13'],
            array_column($canonical['plan']['stages'], 'stage_id')
        );

        $first = $staged['stages'][0];
        self::assertSame('laravel-10-to-11', $first['id']);
        self::assertSame('feasible_with_changes', $first['resolution_status']);
        self::assertSame(3, $first['selected_attempt']);
        self::assertCount(3, $first['attempts']);
        self::assertNotSame([], $first['attempts'][0]['blocker_ids']);
        self::assertNotSame([], $first['attempts'][1]['blocker_ids']);
        self::assertSame([], $first['attempts'][2]['blocker_ids']);
        self::assertTrue($first['attempts'][2]['selected']);

        $middle = $staged['stages'][1];
        self::assertSame('laravel-11-to-12', $middle['id']);
        self::assertSame('feasible_with_changes', $middle['resolution_status']);
        self::assertSame($first['output_state']['state_sha256'], $middle['input_state']['state_sha256']);

        $last = $staged['stages'][2];
        self::assertSame('laravel-12-to-13', $last['id']);
        self::assertSame('blocked', $last['resolution_status']);
        self::assertNull($last['selected_attempt']);
        self::assertNull($last['output_state']);
        self::assertSame($middle['output_state']['state_sha256'], $last['input_state']['state_sha256']);
        self::assertSame('original_project', $last['source_snapshot']);
        foreach ($staged['stages'] as $stage) {
            self::assertStringContainsString('original project source snapshot', $stage['source_snapshot_note']);
            self::assertSame($stage['id'], $stage['risk']['stage_id']);
            self::assertSame($stage['id'], $stage['effort']['stage_id']);
        }
        self::assertNotSame([], $last['source_impact']);
        $stagedImpact = [];
        foreach ($staged['source_impact'] as $finding) {
            $stagedImpact[$finding['id']] = $finding;
        }
        self::assertSame(
            'tests/Feature/LegacyCsrfTest.php',
            $stagedImpact[$last['source_impact'][0]]['occurrences'][0]['file']
        );

        $registry = [];
        foreach ($staged['blocker_registry'] as $entry) {
            $registry[$entry['subject'] . ':' . ($entry['blocking_package'] ?? '')] = $entry;
        }
        self::assertContains(count($registry), [3, 4]);
        self::assertSame([], array_values(array_diff(array_keys($registry), [
            'laravel/framework:nunomaduro/collision',
            'laravel/framework:phpunit/phpunit',
            'php:nunomaduro/collision',
            'ext-preflight-stage:laravel/framework',
        ])));
        $registryIds = array_column($staged['blocker_registry'], 'id');
        foreach (array_slice($first['attempts'], 0, 2) as $attempt) {
            self::assertSame([], array_values(array_diff($attempt['blocker_ids'], $registryIds)));
        }
        self::assertSame('resolved', $registry['laravel/framework:nunomaduro/collision']['lifecycle']);
        self::assertSame(['detected', 'resolved'], array_column(
            $registry['laravel/framework:nunomaduro/collision']['lifecycle_history'],
            'status'
        ));
        self::assertSame('resolved', $registry['laravel/framework:phpunit/phpunit']['lifecycle']);
        self::assertSame(['detected', 'persists', 'resolved'], array_column(
            $registry['laravel/framework:phpunit/phpunit']['lifecycle_history'],
            'status'
        ));
        if (isset($registry['php:nunomaduro/collision'])) {
            self::assertSame('php-platform-too-low', $registry['php:nunomaduro/collision']['category']);
            self::assertSame('resolved', $registry['php:nunomaduro/collision']['lifecycle']);
            self::assertSame(['detected', 'resolved'], array_column(
                $registry['php:nunomaduro/collision']['lifecycle_history'],
                'status'
            ));
        }
        self::assertSame('persists', $registry['ext-preflight-stage:laravel/framework']['lifecycle']);

        self::assertCount(1, $report->frameworkGuidance());
        $guidance = $report->frameworkGuidance()[0];
        self::assertSame('supported', $guidance->status());
        self::assertSame(10, $guidance->sourceMajor());
        self::assertSame(13, $guidance->targetMajor());
        self::assertSame([
            ['from_major' => 10, 'to_major' => 11],
            ['from_major' => 11, 'to_major' => 12],
            ['from_major' => 12, 'to_major' => 13],
        ], $guidance->supportedHopReferences());

        self::assertCount(1, $report->actionableSourceImpact());
        $sourceImpact = $report->actionableSourceImpact()[0]->toArray();
        self::assertSame('framework_rule', $sourceImpact['relevance']);
        self::assertSame('high', $sourceImpact['severity']);
        self::assertSame('tests/Feature/LegacyCsrfTest.php', $sourceImpact['occurrences'][0]['file']);
        self::assertSame('Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken', $sourceImpact['occurrences'][0]['symbol']);

        $summaries = array_map(static fn ($finding): string => $finding->summary(), $report->frameworkFindings());
        self::assertContains(
            'phpunit/phpunit 10.0.0 is outside the encoded Laravel 11 review range `^11.0.1`; review its upgrade or replacement.',
            $summaries
        );
        self::assertContains(
            'nesbot/carbon 2.72.0 is outside the encoded Laravel 12 review range `^3.0`; review its upgrade or replacement.',
            $summaries
        );
        self::assertContains(
            'laravel/tinker 2.9.0 is outside the encoded Laravel 13 review range `^3.0`; review its upgrade or replacement.',
            $summaries
        );
        self::assertContains(
            'Replace 1 detected direct reference to VerifyCsrfToken or ValidateCsrfToken with PreventRequestForgery before targeting Laravel 13.',
            $summaries
        );

        $checkedIn = json_decode(
            $this->read($this->demoRoot() . '/reports/laravel-10-to-13.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($checkedIn);
        self::assertSame(
            $this->stageStateChain($checkedIn),
            $this->stageStateChain($canonical),
            'The checked-in demo report must use the current canonical candidate-state fingerprints.'
        );
    }

    public function testCheckedInReportsValidateAndProjectTheSameKeyFindings(): void
    {
        $jsonPath = $this->demoRoot() . '/reports/laravel-10-to-13.json';
        $markdownPath = $this->demoRoot() . '/reports/laravel-10-to-13.md';
        $json = $this->read($jsonPath);
        $markdown = $this->read($markdownPath);
        /** @var array<string, mixed> $canonical */
        $canonical = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('0.8', $canonical['metadata']['schema_version'] ?? null);
        self::assertSame('0.3.2', $canonical['metadata']['tool']['version'] ?? null);
        self::assertSame('blocked', $canonical['resolution']['status'] ?? null);
        self::assertSame('blocked', $canonical['staged_resolution']['status'] ?? null);
        self::assertSame('restricted', $canonical['composer_execution']['mode'] ?? null);
        self::assertTrue($canonical['composer_execution']['offline_requested'] ?? false);
        self::assertFalse($canonical['composer_execution']['process_os_isolation'] ?? true);
        self::assertSame('laravel-10-to-11', $canonical['staged_resolution']['stages'][0]['id'] ?? null);
        self::assertSame(3, $canonical['staged_resolution']['stages'][0]['selected_attempt'] ?? null);
        self::assertSame('feasible_with_changes', $canonical['staged_resolution']['stages'][1]['resolution_status'] ?? null);
        self::assertSame('blocked', $canonical['staged_resolution']['stages'][2]['resolution_status'] ?? null);
        self::assertSame('tests/Feature/LegacyCsrfTest.php', $canonical['source_impact'][0]['occurrences'][0]['file'] ?? null);
        self::assertSame(0, $canonical['resolution']['scenarios'][0]['exit_code'] ?? null);
        self::assertSame([
            ['from_major' => 10, 'to_major' => 11],
            ['from_major' => 11, 'to_major' => 12],
            ['from_major' => 12, 'to_major' => 13],
        ], array_map(
            static fn (array $hop): array => [
                'from_major' => $hop['from_major'],
                'to_major' => $hop['to_major'],
            ],
            $canonical['transition']['framework_guidance'][0]['hops'] ?? []
        ));

        $this->assertConformsToSchema($json);
        $this->assertMarkdownProjectsCanonicalFindings($canonical, $markdown);
        self::assertDoesNotMatchRegularExpression(
            '~(?:^|[\\s"\'])[A-Za-z]:[\\\\/]|/(?:app|home|Users)/~im',
            $json . $markdown
        );
        self::assertStringNotContainsString('--debug', $json . $markdown);
        foreach ($canonical['resolution']['scenarios'] ?? [] as $scenario) {
            self::assertNull($scenario['temp_path'] ?? null);
        }

        $rootReadme = $this->read(dirname($this->demoRoot(), 2) . '/README.md');
        self::assertStringContainsString(
            '(examples/five-minute-demo/README.md)',
            $rootReadme
        );

        $script = $this->read($this->demoRoot() . '/run-demo.sh');
        $tape = $this->read($this->demoRoot() . '/laravel-10-to-13.tape');
        self::assertStringContainsString('--without-extension=ext-preflight-stage', $script);
        self::assertStringContainsString('--composer-mode=restricted', $script);
        self::assertStringContainsString('reports/laravel-10-to-13.json', $script);
        self::assertStringContainsString('summarize-report.php', $script);
        self::assertStringContainsString('staged_resolution', $this->read($this->demoRoot() . '/summarize-report.php'));
        self::assertStringContainsString('bash examples/five-minute-demo/run-demo.sh', $tape);
        $gifSize = filesize($this->demoRoot() . '/laravel-10-to-13.gif');
        self::assertIsInt($gifSize);
        self::assertGreaterThan(0, $gifSize);
    }

    public function testDemoSummarizerExecutesAgainstTheCheckedInReport(): void
    {
        $json = FiveMinuteDemoAnalysis::canonicalJsonPath();
        $process = $this->runSummarizer($json, $json);
        $output = $process->getOutput() . $process->getErrorOutput();

        self::assertSame(0, $process->getExitCode(), $output);
        self::assertStringContainsString('Direct final target: BLOCKED', $output);
        self::assertStringContainsString('Staged resolution:   BLOCKED', $output);
        self::assertStringContainsString('Stage 10->11: FEASIBLE_WITH_CHANGES', $output);
        self::assertStringContainsString('Stage 12->13: BLOCKED', $output);
        self::assertStringContainsString(
            'Original-source finding: tests/Feature/LegacyCsrfTest.php:',
            $output
        );
        self::assertStringContainsString('VerifyCsrfToken', $output);
    }

    /**
     * The summarizer guards the recorded demo against reports that no longer match
     * the committed evidence. Passing the canonical report as both inputs cannot
     * exercise those guards, so each case here mutates a copy: a drifting stage is
     * compared against the canonical report, while a broken source-impact
     * reference is compared against itself so the projection matches and only the
     * reference guard can reject it.
     *
     * @return iterable<string, array{mutate: callable(array<string, mixed>): array<string, mixed>, compareAgainstCanonical: bool, expected: string}>
     */
    public static function summarizerRejectionProvider(): iterable
    {
        yield 'stage resolution drifts from the canonical report' => [
            'mutate' => static function (array $report): array {
                $report['staged_resolution']['stages'][1]['resolution_status'] = 'blocked';

                return $report;
            },
            'compareAgainstCanonical' => true,
            'expected' => 'differs from the checked-in canonical report',
        ];

        yield 'final stage references a missing source-impact finding' => [
            'mutate' => static function (array $report): array {
                $report['staged_resolution']['stages'][2]['source_impact'] = ['source-impact-does-not-exist'];

                return $report;
            },
            'compareAgainstCanonical' => false,
            'expected' => 'resolvable source-impact finding',
        ];

        yield 'final stage reports no source impact at all' => [
            'mutate' => static function (array $report): array {
                $report['staged_resolution']['stages'][2]['source_impact'] = [];

                return $report;
            },
            'compareAgainstCanonical' => false,
            'expected' => 'resolvable source-impact finding',
        ];
    }

    /**
     * @dataProvider summarizerRejectionProvider
     * @param callable(array<string, mixed>): array<string, mixed> $mutate
     */
    public function testDemoSummarizerRejectsReportsThatLoseTheCanonicalEvidence(
        callable $mutate,
        bool $compareAgainstCanonical,
        string $expected
    ): void {
        $canonicalPath = FiveMinuteDemoAnalysis::canonicalJsonPath();
        /** @var array<string, mixed> $canonical */
        $canonical = json_decode($this->read($canonicalPath), true, 512, JSON_THROW_ON_ERROR);

        $mutatedPath = tempnam(sys_get_temp_dir(), 'preflight-demo-drift-');
        self::assertIsString($mutatedPath);

        try {
            file_put_contents($mutatedPath, json_encode(
                $mutate($canonical),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            ));

            $process = $this->runSummarizer(
                $mutatedPath,
                $compareAgainstCanonical ? $canonicalPath : $mutatedPath
            );
            $output = $process->getOutput() . $process->getErrorOutput();

            self::assertNotSame(0, $process->getExitCode(), $output);
            self::assertStringContainsString($expected, $output);
        } finally {
            unlink($mutatedPath);
        }
    }

    private function runSummarizer(string $generatedPath, string $canonicalPath): Process
    {
        $process = new Process([
            PHP_BINARY,
            FiveMinuteDemoAnalysis::summarizerPath(),
            $generatedPath,
            $canonicalPath,
        ]);
        $process->run();

        return $process;
    }

    /** @param array<string, mixed> $canonical */
    private function assertMarkdownProjectsCanonicalFindings(array $canonical, string $markdown): void
    {
        self::assertStringContainsString('Resolution: **blocked** | Staged: **blocked** | Schema: `0.8`', $markdown);
        self::assertStringContainsString('## Staged Composer Resolution', $markdown);

        foreach ($canonical['staged_resolution']['blocker_registry'] ?? [] as $blocker) {
            self::assertIsArray($blocker);
            self::assertStringContainsString((string) ($blocker['id'] ?? ''), $markdown);
            self::assertStringContainsString((string) ($blocker['lifecycle'] ?? ''), $markdown);
        }

        foreach ($canonical['blockers'] ?? [] as $blocker) {
            self::assertIsArray($blocker);
            self::assertStringContainsString((string) ($blocker['type'] ?? ''), $markdown);
            self::assertStringContainsString((string) ($blocker['blocker'] ?? ''), $markdown);
            self::assertStringContainsString((string) ($blocker['conflict'] ?? ''), $markdown);
        }

        foreach ($canonical['framework_findings'] ?? [] as $finding) {
            self::assertIsArray($finding);
            self::assertStringContainsString((string) ($finding['summary'] ?? ''), $markdown);
        }

        foreach ($canonical['source_impact'] ?? [] as $finding) {
            self::assertIsArray($finding);
            foreach ($finding['occurrences'] ?? [] as $occurrence) {
                self::assertIsArray($occurrence);
                self::assertStringContainsString((string) ($occurrence['file'] ?? ''), $markdown);
                self::assertStringContainsString((string) ($occurrence['symbol'] ?? ''), $markdown);
            }
        }
    }

    /**
     * @param array<string, mixed> $report
     * @return list<array{id: string, input: string|null, output: string|null}>
     */
    private function stageStateChain(array $report): array
    {
        return array_map(
            static fn (array $stage): array => [
                'id' => (string) ($stage['id'] ?? ''),
                'input' => isset($stage['input_state']['state_sha256'])
                    ? (string) $stage['input_state']['state_sha256']
                    : null,
                'output' => isset($stage['output_state']['state_sha256'])
                    ? (string) $stage['output_state']['state_sha256']
                    : null,
            ],
            $report['staged_resolution']['stages'] ?? []
        );
    }

    private function assertConformsToSchema(string $json): void
    {
        $schemaContents = $this->read(dirname($this->demoRoot(), 2) . '/packages/core/resources/schema/upgrade-report-v0.8.schema.json');
        /** @var object $schema */
        $schema = json_decode($schemaContents, false, 512, JSON_THROW_ON_ERROR);
        /** @var mixed $report */
        $report = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        $result = (new Validator(null, 20, false))->validate($report, $schema);

        if ($result->hasError()) {
            $error = $result->error();
            self::assertNotNull($error);
            self::fail(json_encode(
                (new ErrorFormatter())->format($error),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ));
        }

        self::assertTrue($result->isValid());
    }

    /** @param string|false $value */
    private function restoreEnvironment(string $name, $value): void
    {
        putenv($value === false ? $name : sprintf('%s=%s', $name, $value));
    }

    private function demoRoot(): string
    {
        return dirname(__DIR__, 4) . '/examples/five-minute-demo';
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents, sprintf('Unable to read %s.', $path));

        return $contents;
    }
}
