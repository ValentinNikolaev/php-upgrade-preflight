<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use Illuminate\Contracts\Foundation\Application;
use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;
use PhpUpgradePreflight\Tests\Support\LaravelTransitionFixtureFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

final class LaravelTransitionCommandParityTest extends TestCase
{
    /**
     * Number of Windows shards the 16 transition cases are dealt across.
     *
     * Round-robin rather than contiguous: adjacent cases in the contract pair a
     * cheap "feasible" fixture with an expensive "advisory-heavy" one, so dealing
     * by index keeps the per-shard cost even. Raising this needs a matching
     * provider, a `windows-parity-N` group, and a CI shard to run it.
     */
    private const TRANSITION_SHARDS = 3;

    /**
     * @group windows-parity-1
     * @dataProvider transitionCaseShardAProvider
     * @param array<string, mixed> $case
     */
    public function testCliAndArtisanHaveCanonicalJsonParityForTransitionFixtureShardA(array $case): void
    {
        $this->assertCanonicalParity($case);
    }

    /**
     * @group windows-parity-2
     * @dataProvider transitionCaseShardBProvider
     * @param array<string, mixed> $case
     */
    public function testCliAndArtisanHaveCanonicalJsonParityForTransitionFixtureShardB(array $case): void
    {
        $this->assertCanonicalParity($case);
    }

    /**
     * @group windows-parity-3
     * @dataProvider transitionCaseShardCProvider
     * @param array<string, mixed> $case
     */
    public function testCliAndArtisanHaveCanonicalJsonParityForTransitionFixtureShardC(array $case): void
    {
        $this->assertCanonicalParity($case);
    }

    /**
     * @group windows-staged
     * @dataProvider stagedParityCaseProvider
     * @param array<string, mixed> $case
     */
    public function testCliAndArtisanHaveCanonicalJsonParityForStagedAnalysis(array $case): void
    {
        $this->assertCanonicalParity($case);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public function transitionCaseShardAProvider(): iterable
    {
        yield from $this->transitionCasesForShard(0);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public function transitionCaseShardBProvider(): iterable
    {
        yield from $this->transitionCasesForShard(1);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public function transitionCaseShardCProvider(): iterable
    {
        yield from $this->transitionCasesForShard(2);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public function stagedParityCaseProvider(): iterable
    {
        yield 'complete target-platform profile' => [[
            'fixture' => 'laravel-12-to-13-feasible',
            'target_package' => 'laravel/framework',
            'target_constraint' => '^13.0',
            'target_php' => '8.3.0',
            'target_platform_profile' => 'complete-php-83.json',
            'composer_mode' => 'compatible',
            'resolution' => 'feasible_with_changes',
            'guidance' => 'supported',
            'catalog' => 'default',
            'staged_execution_state' => 'evaluated',
            'staged_status' => 'feasible_with_changes',
            'staged_stop_reason' => null,
            'stage_ids' => ['laravel-12-to-13'],
        ]];

        yield 'restricted Composer execution' => [[
            'fixture' => 'laravel-10-to-11-feasible',
            'target_package' => 'laravel/framework',
            'target_constraint' => '^11.0',
            'target_php' => '8.2.0',
            'composer_mode' => 'restricted',
            'resolution' => 'feasible_with_changes',
            'guidance' => 'supported',
            'catalog' => 'default',
            'staged_execution_state' => 'evaluated',
            'staged_status' => 'feasible_with_changes',
            'staged_stop_reason' => null,
            'stage_ids' => ['laravel-10-to-11'],
        ]];

        yield 'feasible single hop' => [[
            'fixture' => 'laravel-8-to-9-feasible',
            'target_package' => 'laravel/framework',
            'target_constraint' => '^9.0',
            'target_php' => '8.0.2',
            'composer_mode' => 'compatible',
            'resolution' => 'feasible_with_changes',
            'guidance' => 'supported',
            'catalog' => 'default',
            'staged_execution_state' => 'evaluated',
            'staged_status' => 'feasible_with_changes',
            'staged_stop_reason' => null,
            'stage_ids' => ['laravel-8-to-9'],
        ]];

        yield 'feasible multi hop' => [[
            'fixture' => 'laravel-10-to-13',
            'target_package' => 'laravel/framework',
            'target_constraint' => '^13.0',
            'target_php' => '8.3',
            'composer_mode' => 'compatible',
            'resolution' => 'feasible_with_changes',
            'guidance' => 'supported',
            'catalog' => 'default',
            'staged_execution_state' => 'evaluated',
            'staged_status' => 'feasible_with_changes',
            'staged_stop_reason' => null,
            'stage_ids' => ['laravel-10-to-11', 'laravel-11-to-12', 'laravel-12-to-13'],
        ]];

        yield 'blocked stage' => [[
            'fixture' => 'laravel-12-to-13',
            'target_package' => 'laravel/framework',
            'target_constraint' => '^13.0',
            'target_php' => '8.3',
            'composer_mode' => 'compatible',
            'resolution' => 'blocked',
            'guidance' => 'supported',
            'catalog' => 'default',
            'staged_execution_state' => 'evaluated',
            'staged_status' => 'blocked',
            'staged_stop_reason' => 'blocking_registry_not_cleared',
            'stage_ids' => ['laravel-12-to-13'],
        ]];

        yield 'skipped stages after guidance gap' => [[
            'fixture' => 'laravel-missing-hop',
            'target_package' => 'laravel/framework',
            'target_constraint' => '^13.0',
            'target_php' => '8.3',
            'composer_mode' => 'compatible',
            'resolution' => 'feasible_with_changes',
            'guidance' => 'partially_supported',
            'catalog' => 'missing-11-to-12',
            'staged_execution_state' => 'skipped',
            'staged_status' => 'unknown',
            'staged_stop_reason' => 'guidance_gap',
            'stage_ids' => [],
        ]];
    }

    /** @param array<string, mixed> $case */
    private function assertCanonicalParity(array $case): void
    {
        $projectPath = dirname(__DIR__, 4) . '/tests/fixtures/projects/' . $case['fixture'];
        [$cliExit, $cliJson, $cliError] = $this->runCli($case, $projectPath);
        [$artisanExit, $artisanJson, $artisanError] = $this->runArtisan($case, $projectPath);

        self::assertSame(Command::SUCCESS, $cliExit, $cliError);
        self::assertSame(Command::SUCCESS, $artisanExit, $artisanError);
        self::assertSame('', $cliError);
        self::assertSame('', $artisanError);

        $cli = $this->normalizeReport($this->decode($cliJson));
        $artisan = $this->normalizeReport($this->decode($artisanJson));
        self::assertSame($cli, $artisan);
        self::assertSame($case['resolution'], $cli['resolution']['status']);
        self::assertSame($case['guidance'], $cli['transition']['framework_guidance'][0]['status']);

        if (isset($case['staged_execution_state'])) {
            $this->assertStagedCoverage($case, $cli);
        }
    }

    /**
     * @param array<string, mixed> $case
     * @param array<string, mixed> $report
     */
    private function assertStagedCoverage(array $case, array $report): void
    {
        self::assertSame($case['composer_mode'], $report['composer_execution']['mode']);
        self::assertSame($case['staged_execution_state'], $report['staged_resolution']['execution_state']);
        self::assertSame($case['staged_status'], $report['staged_resolution']['status']);
        self::assertSame($case['staged_stop_reason'], $report['staged_resolution']['stop_reason']);
        self::assertSame(
            $case['stage_ids'],
            array_column($report['staged_resolution']['stages'], 'id')
        );

        if (isset($case['target_platform_profile'])) {
            self::assertSame('complete', $report['platform']['profile']['completeness']);
            self::assertTrue($report['platform']['profile']['closed_world']);
            self::assertNotNull($report['request_summary']['target_platform_profile']);
        }

        foreach ($report['staged_resolution']['stages'] as $stage) {
            self::assertSame(
                $case['composer_mode'],
                $stage['composer_execution']['configuration']['mode']
            );
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    private function transitionCasesForShard(int $shard): iterable
    {
        $contents = file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/contracts/laravel-v0.2-transition-cases.json');
        if ($contents === false) {
            throw new \RuntimeException('Unable to read the Laravel transition fixture contract.');
        }
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($contract) || !is_array($contract['cases'] ?? null)) {
            throw new \RuntimeException('Invalid Laravel transition fixture contract.');
        }

        foreach (array_values($contract['cases']) as $index => $case) {
            if ($index % self::TRANSITION_SHARDS !== $shard) {
                continue;
            }
            yield $case['name'] => [$case];
        }
    }

    /**
     * @param array<string, mixed> $case
     * @return array{int, string, string}
     */
    private function runCli(array $case, string $projectPath): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Unable to open CLI parity streams.');
        }

        try {
            $command = new AnalyzeCommand(
                LaravelTransitionFixtureFactory::analyzer($case['catalog']),
                $stdout,
                $stderr
            );
            $arguments = [
                'upgrade-intel',
                'analyze',
                '--path=' . $projectPath,
                '--target=' . $case['target_package'] . ':' . $case['target_constraint'],
                '--target-php=' . $case['target_php'],
                '--framework=laravel',
                '--format=json',
                '--composer-mode=' . ($case['composer_mode'] ?? 'compatible'),
            ];
            if (isset($case['target_platform_profile'])) {
                $arguments[] = '--target-platform-profile=' . $this->profilePath($case['target_platform_profile']);
            }
            $exitCode = $command->run($arguments);

            return [$exitCode, $this->streamContents($stdout), $this->streamContents($stderr)];
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
    }

    /**
     * @param array<string, mixed> $case
     * @return array{int, string, string}
     */
    private function runArtisan(array $case, string $projectPath): array
    {
        $application = $this->createMock(Application::class);
        $application->method('basePath')->willReturn($projectPath);
        $application->method('make')->willReturnCallback(
            static fn (string $abstract, array $parameters): SymfonyStyle => new SymfonyStyle(
                $parameters['input'],
                $parameters['output']
            )
        );
        $application->method('call')->willReturnCallback(
            static fn (callable $callback): int => (int) $callback()
        );

        $command = new AnalyzeUpgradeCommand(LaravelTransitionFixtureFactory::analyzer($case['catalog']));
        $command->setLaravel($application);
        $tester = new CommandTester($command);
        $arguments = [
            '--path' => $projectPath,
            '--target' => [$case['target_package'] . ':' . $case['target_constraint']],
            '--target-php' => $case['target_php'],
            '--format' => 'json',
            '--composer-mode' => $case['composer_mode'] ?? 'compatible',
        ];
        if (isset($case['target_platform_profile'])) {
            $arguments['--target-platform-profile'] = [$this->profilePath($case['target_platform_profile'])];
        }
        $exitCode = $tester->execute($arguments, ['capture_stderr_separately' => true]);

        return [$exitCode, $tester->getDisplay(), $tester->getErrorOutput()];
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function normalizeReport(array $report): array
    {
        foreach ($report as $key => &$value) {
            if ($key === 'duration_ms') {
                $value = 0;
                continue;
            }
            if (is_array($value)) {
                $value = $this->normalizeReport($value);
            }
        }
        unset($value);

        return $report;
    }

    private function profilePath(string $fixture): string
    {
        return dirname(__DIR__, 4) . '/tests/fixtures/platform-profiles/' . $fixture;
    }

    /** @param resource $stream */
    private function streamContents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read parity stream.');
        }

        return $contents;
    }
}
