<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use Composer\Semver\Semver;
use FilesystemIterator;
use Illuminate\Contracts\Foundation\Application;
use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

final class LaravelExcludedTransitionTest extends TestCase
{
    private string $fixtureCopy;
    private FixtureSnapshot $original;

    protected function setUp(): void
    {
        $source = dirname(__DIR__, 4) . '/tests/fixtures/laravel-exclusions';
        $this->original = FixtureSnapshot::capture($source);
        $this->fixtureCopy = sys_get_temp_dir() . '/php-upgrade-exclusions-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->fixtureCopy, 0700));
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($files as $file) {
            $destination = $this->fixtureCopy . '/' . $files->getSubPathname();
            if ($file->isDir()) {
                self::assertTrue(mkdir($destination));
            } else {
                self::assertTrue(copy($file->getPathname(), $destination));
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->original->assertUnchanged($this);
        } finally {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->fixtureCopy, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    self::assertTrue(rmdir($file->getPathname()));
                } else {
                    self::assertTrue(unlink($file->getPathname()));
                }
            }
            self::assertTrue(rmdir($this->fixtureCopy));
            self::assertDirectoryDoesNotExist($this->fixtureCopy);
        }
    }

    /**
     * @dataProvider excludedTransitionProvider
     * @param array<string, string> $targets
     */
    public function testDirectComposerResolutionRemainsIndependentOfStagedRefusal(
        string $fixture,
        array $targets,
        string $resolution,
        string $guidance,
        string $stopReason
    ): void {
        $snapshot = FixtureSnapshot::capture($this->fixtureCopy);
        $request = new UpgradeRequest(
            $this->projectPath($fixture),
            $this->targets($targets),
            '8.3.0',
            '8.3.0',
            [],
            ['laravel'],
            'json',
            null,
            false,
            [],
            null,
            ComposerExecutionConfiguration::restricted()
        );
        $report = $this->analyzer()->analyzeUpgrade($request);

        $this->assertExcludedOutcome($report->toArray(), $resolution, $guidance, $stopReason);
        $successfulTargets = 0;
        foreach ($report->scenarios() as $result) {
            self::assertNotNull($result->composerVersion());
            self::assertSame('composer', $result->command()[0]);
            if ($result->tempPath() !== null) {
                self::assertDirectoryDoesNotExist($result->tempPath());
            }
            if (!$result->scenario()->determinesTargetFeasibility()) {
                continue;
            }
            self::assertSame($request->targets()->toArray(), $result->scenario()->targets()->toArray());
            foreach (array_keys($targets) as $package) {
                self::assertContains($package, $result->command());
            }
            if (!$result->succeeded()) {
                self::assertSame('solver_failure', $result->outcome(), $result->stderr());
                continue;
            }
            ++$successfulTargets;
            self::assertNotNull($result->candidateLockEvidence());
            $candidate = $result->candidateProjectState();
            self::assertNotNull($candidate);
            foreach ($targets as $package => $constraint) {
                self::assertSame($constraint, $candidate->composerJson()->rootRequirements()[$package]);
                $locked = $candidate->composerLock()->package($package);
                self::assertNotNull($locked);
                self::assertTrue(Semver::satisfies($locked->version(), $constraint));
            }
            if ($fixture === 'illuminate' && !isset($targets['laravel/framework'])) {
                self::assertArrayNotHasKey('laravel/framework', $candidate->composerJson()->rootRequirements());
                self::assertNull($candidate->composerLock()->package('laravel/framework'));
            }
        }
        if ($resolution === 'blocked') {
            self::assertSame(0, $successfulTargets);
        } else {
            self::assertGreaterThan(0, $successfulTargets);
        }

        $json = json_decode((new JsonReportWriter())->render($report), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($report->toArray(), $json);
        $writer = new MarkdownReportWriter();
        self::assertSame($writer->renderCanonical($json), $writer->render($report));
        self::assertStringContainsString($stopReason, $writer->render($report));
        $snapshot->assertUnchanged($this);
    }

    /** @return iterable<string, array{string, array<string, string>, string, string, string}> */
    public function excludedTransitionProvider(): iterable
    {
        yield 'Illuminate-only single target' => [
            'illuminate', ['illuminate/support' => '^13.0'],
            'feasible_with_changes', 'supported', 'guidance_gap',
        ];
        yield 'Illuminate-only all rooted members' => [
            'illuminate', ['illuminate/console' => '^13.0', 'illuminate/support' => '^13.0'],
            'feasible_with_changes', 'supported', 'guidance_gap',
        ];
        yield 'mixed framework and component targets' => [
            'mixed', ['laravel/framework' => '^13.0', 'illuminate/support' => '^13.0'],
            'feasible_with_changes', 'supported', 'guidance_gap',
        ];
        yield 'same-major minor target' => [
            'mixed', ['laravel/framework' => '^12.1'],
            'feasible_with_changes', 'unsupported', 'unsupported_transition',
        ];
        yield 'downgrade target' => [
            'mixed', ['laravel/framework' => '^11.0'],
            'feasible_with_changes', 'unsupported', 'unsupported_transition',
        ];
        yield 'inconsistent rooted source majors' => [
            'inconsistent', ['illuminate/console' => '^13.0', 'illuminate/support' => '^13.0'],
            'feasible_with_changes', 'unsupported', 'ambiguous_transition',
        ];
        yield 'Composer rejects conflicting family majors' => [
            'mixed', ['laravel/framework' => '^13.0', 'illuminate/support' => '^12.0'],
            'blocked', 'unsupported', 'ambiguous_transition',
        ];
        yield 'framework target does not create a rooted framework source' => [
            'illuminate', ['laravel/framework' => '^13.0'],
            'blocked', 'supported', 'guidance_gap',
        ];
        yield 'ambiguous framework target' => [
            'mixed', ['laravel/framework' => '^12.0|^13.0'],
            'feasible_with_changes', 'unsupported', 'ambiguous_transition',
        ];
    }

    /**
     * @dataProvider parityProvider
     * @param array<string, string> $targets
     */
    public function testCliAndArtisanProduceTheSameCanonicalExclusion(
        string $fixture,
        array $targets,
        string $resolution,
        string $guidance,
        string $stopReason
    ): void {
        $snapshot = FixtureSnapshot::capture($this->fixtureCopy);
        $path = $this->projectPath($fixture);
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        try {
            $arguments = [
                'upgrade-intel', 'analyze', '--path=' . $path, '--from-php=8.3.0',
                '--target-php=8.3.0', '--framework=laravel', '--format=json', '--composer-mode=restricted',
            ];
            $targetArguments = [];
            foreach ($targets as $package => $constraint) {
                $targetArguments[] = $package . ':' . $constraint;
                $arguments[] = '--target=' . $package . ':' . $constraint;
            }
            self::assertSame(0, (new AnalyzeCommand($this->analyzer(), $stdout, $stderr))->run($arguments));
            rewind($stdout);
            rewind($stderr);
            self::assertSame('', stream_get_contents($stderr));
            $cli = json_decode((string) stream_get_contents($stdout), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            fclose($stdout);
            fclose($stderr);
        }

        $application = $this->createMock(Application::class);
        $application->method('basePath')->willReturn($path);
        $application->method('make')->willReturnCallback(
            static fn (string $abstract, array $parameters): SymfonyStyle => new SymfonyStyle(
                $parameters['input'],
                $parameters['output']
            )
        );
        $application->method('call')->willReturnCallback(static fn (callable $callback): int => (int) $callback());
        $command = new AnalyzeUpgradeCommand($this->analyzer());
        $command->setLaravel($application);
        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute([
            '--path' => $path, '--target' => $targetArguments, '--from-php' => '8.3.0',
            '--target-php' => '8.3.0', '--format' => 'json', '--composer-mode' => 'restricted',
        ], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getErrorOutput());
        $artisan = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($this->normalizeDurations($cli), $this->normalizeDurations($artisan));
        $this->assertExcludedOutcome($cli, $resolution, $guidance, $stopReason);
        $snapshot->assertUnchanged($this);
    }

    /** @return iterable<string, array{string, array<string, string>, string, string, string}> */
    public function parityProvider(): iterable
    {
        foreach ($this->excludedTransitionProvider() as $name => $case) {
            if (in_array($name, [
                'Illuminate-only single target',
                'mixed framework and component targets',
                'same-major minor target',
                'Composer rejects conflicting family majors',
            ], true)) {
                yield $name => $case;
            }
        }
    }

    /** @param array<string, mixed> $canonical */
    private function assertExcludedOutcome(array $canonical, string $resolution, string $guidance, string $stopReason): void
    {
        self::assertSame($resolution, $canonical['resolution']['status']);
        self::assertSame($guidance, $canonical['transition']['framework_guidance'][0]['status']);
        self::assertSame('skipped', $canonical['staged_resolution']['execution_state']);
        self::assertSame('unknown', $canonical['staged_resolution']['status']);
        self::assertSame($stopReason, $canonical['staged_resolution']['stop_reason']);
        self::assertSame([], $canonical['staged_resolution']['stages']);
        self::assertNotSame([], $canonical['staged_resolution']['evidence']);
        $evidence = array_column($canonical['evidence'], null, 'id');
        foreach ($canonical['staged_resolution']['evidence'] as $reference) {
            self::assertArrayHasKey($reference, $evidence);
            self::assertSame($stopReason, $evidence[$reference]['context']['reason']);
        }
    }

    private function analyzer(): DefaultUpgradeAnalyzer
    {
        return new DefaultUpgradeAnalyzer([new LaravelFrameworkIntegration()]);
    }

    private function projectPath(string $fixture): string
    {
        return $this->fixtureCopy . '/projects/' . $fixture;
    }

    /** @param array<string, string> $targets
     * @return list<UpgradeTarget>
     */
    private function targets(array $targets): array
    {
        $result = [];
        foreach ($targets as $package => $constraint) {
            $result[] = new UpgradeTarget($package, $constraint);
        }

        return $result;
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function normalizeDurations(array $report): array
    {
        foreach ($report as $key => &$value) {
            if ($key === 'duration_ms') {
                $value = 0;
            } elseif (is_array($value)) {
                $value = $this->normalizeDurations($value);
            }
        }
        unset($value);

        return $report;
    }
}
