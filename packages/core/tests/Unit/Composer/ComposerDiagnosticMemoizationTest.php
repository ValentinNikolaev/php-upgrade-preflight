<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Composer;

use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ComposerDiagnosticMemoizationTest extends TestCase
{
    public function testEquivalentDirectAndStagedDiagnosticsReuseOnlyWithinTheCurrentAnalysis(): void
    {
        $this->withFixture(function (string $path): void {
            $runner = $this->runner();
            $project = (new ProjectStateBuilder())->build($path);
            $request = new UpgradeRequest($path, [new UpgradeTarget('fixture/dependency', '^2.0')]);
            $direct = $runner->run($project, $request, new Scenario('exact-target', $request->targets(), false));
            $stage = $runner->run($project, $request, new Scenario('stage-attempt-1', $request->targets(), false));
            self::assertSame($direct->diagnostics()[0]->toArray(), $stage->diagnostics()[0]->toArray());
            self::assertSame(['scenario' => 2, 'diagnostic' => 1, 'metadata' => 0], $runner->measurements()['process_counts']);
            self::assertSame(1, $runner->measurements()['diagnostic_cache_hits']);
            self::assertGreaterThanOrEqual(0, $runner->measurements()['workspace_ms']);

            $runner->resetDiagnosticCache();
            $uncached = $runner->run($project, $request, new Scenario('stage-attempt-1', $request->targets(), false));
            $cachedData = $stage->toArray();
            $uncachedData = $uncached->toArray();
            $cachedData['duration_ms'] = $uncachedData['duration_ms'] = 0;
            self::assertSame($cachedData, $uncachedData, 'Memoization must not change published scenario semantics.');
            self::assertSame(2, $runner->measurements()['process_counts']['diagnostic']);

            $runner->resetAnalysisCaches();
            self::assertSame(['scenario' => 0, 'diagnostic' => 0, 'metadata' => 0], $runner->measurements()['process_counts']);
            $runner->run($project, $request, new Scenario('new-analysis', $request->targets(), false));
            self::assertSame(1, $runner->measurements()['process_counts']['diagnostic']);
            self::assertSame(0, $runner->measurements()['diagnostic_cache_hits']);
        });
    }

    /** @dataProvider differentContexts */
    public function testDifferentEffectiveInputsDoNotShareDiagnostics(string $context): void
    {
        $this->withFixture(function (string $path) use ($context): void {
            $workspaceState = new \stdClass();
            $workspaceState->changed = false;
            $runner = $this->runner(static function (string $directory) use ($workspaceState, $context): void {
                if ($workspaceState->changed && in_array($context, ['effective-manifest', 'effective-lock'], true)) {
                    $file = $directory . ($context === 'effective-manifest' ? '/composer.json' : '/composer.lock');
                    file_put_contents($file, file_get_contents($file) . "\n");
                }
            });
            $project = (new ProjectStateBuilder())->build($path);
            $request = new UpgradeRequest($path, [new UpgradeTarget('fixture/dependency', '^2.0')]);
            $runner->run($project, $request, new Scenario('direct', $request->targets(), false));
            $workspaceState->changed = true;
            if ($context === 'manifest' || $context === 'repository') {
                $manifest = $project->composerJson()->data();
                $manifest['description'] = 'Different effective manifest.';
                if ($context === 'repository') {
                    $manifest['repositories'] = [['packagist.org' => false]];
                }
                $project = new ProjectState($path, new ComposerJson($manifest), $project->composerLock());
            } elseif ($context === 'lock') {
                $lock = $project->composerLock()->data();
                $lock['packages'][] = ['name' => 'fixture/other', 'version' => '1.0.0'];
                $project = new ProjectState($path, $project->composerJson(), new ComposerLock($lock));
            } elseif ($context === 'execution') {
                $request = $request->withComposerExecution(new ComposerExecutionConfiguration('different-composer'));
            } elseif ($context === 'timeout') {
                $request = $request->withComposerExecution(new ComposerExecutionConfiguration(scenarioTimeoutSeconds: 60));
            } elseif ($context === 'platform') {
                $request = new UpgradeRequest($path, $request->targets()->packageTargets(), targetPhp: '8.2');
            }
            $runner->run($project, $request, new Scenario('stage', $request->targets(), true));
            self::assertSame(0, $runner->measurements()['diagnostic_cache_hits']);
            self::assertGreaterThanOrEqual(2, $runner->measurements()['process_counts']['diagnostic']);
        });
    }

    /** @return list<array{string}> */
    public function differentContexts(): array
    {
        return array_map(static fn (string $context): array => [$context], [
            'manifest', 'repository', 'lock', 'execution', 'timeout', 'platform', 'effective-manifest', 'effective-lock',
        ]);
    }

    public function testFailedDiagnosticIsRetriedInsteadOfCached(): void
    {
        $this->withFixture(function (string $path): void {
            $runner = new ComposerScenarioRunner(processRunner: static fn (array $command): array => [
                'exit_code' => $command[1] === 'prohibits' ? 127 : 2,
                'stdout' => '',
                'stderr' => $command[1] === 'prohibits'
                    ? 'composer not found'
                    : 'Your requirements could not be resolved to an installable set of packages.',
            ]);
            $project = (new ProjectStateBuilder())->build($path);
            $request = new UpgradeRequest($path, [new UpgradeTarget('fixture/dependency', '^2.0')]);
            foreach (['direct', 'stage'] as $name) {
                $result = $runner->run($project, $request, new Scenario($name, $request->targets()));
                self::assertSame(ScenarioResult::OUTCOME_COMPOSER_MISSING, $result->diagnostics()[0]->outcome());
            }
            self::assertSame(2, $runner->measurements()['process_counts']['diagnostic']);
            self::assertSame(0, $runner->measurements()['diagnostic_cache_hits']);
        });
    }

    public function testChangedAmbientEnvironmentDoesNotReuseAnEarlierDiagnostic(): void
    {
        $previous = getenv('PREFLIGHT_MEMO_TEST_CONTEXT');
        try {
            $this->withFixture(function (string $path): void {
                $runner = $this->runner();
                $project = (new ProjectStateBuilder())->build($path);
                $request = new UpgradeRequest($path, [new UpgradeTarget('fixture/dependency', '^2.0')]);
                putenv('PREFLIGHT_MEMO_TEST_CONTEXT=first');
                $runner->run($project, $request, new Scenario('direct', $request->targets()));
                putenv('PREFLIGHT_MEMO_TEST_CONTEXT=second');
                $runner->run($project, $request, new Scenario('stage', $request->targets()));
                self::assertSame(2, $runner->measurements()['process_counts']['diagnostic']);
                self::assertSame(0, $runner->measurements()['diagnostic_cache_hits']);
            });
        } finally {
            putenv($previous === false ? 'PREFLIGHT_MEMO_TEST_CONTEXT' : 'PREFLIGHT_MEMO_TEST_CONTEXT=' . $previous);
        }
    }

    public function testDiagnosticMemoizationIsBoundedForWideRequests(): void
    {
        $this->withFixture(function (string $path): void {
            $runner = $this->runner();
            $project = (new ProjectStateBuilder())->build($path);
            $targets = [];
            for ($index = 0; $index < 257; ++$index) {
                $targets[] = new UpgradeTarget(sprintf('fixture/item-%03d', $index), '^2.0');
            }
            $request = new UpgradeRequest($path, $targets);
            $scenario = new Scenario('wide', $request->targets());
            self::assertCount(257, $runner->run($project, $request, $scenario)->diagnostics());
            self::assertSame(257, $runner->measurements()['process_counts']['diagnostic']);
            // With 257 keys and a 256-entry FIFO, revisiting the ordered request
            // evicts the next key each time; an unbounded cache would hit all 257.
            self::assertCount(257, $runner->run($project, $request, $scenario)->diagnostics());
            self::assertSame(514, $runner->measurements()['process_counts']['diagnostic']);
            self::assertSame(0, $runner->measurements()['diagnostic_cache_hits']);
        });
    }

    /** @dataProvider unavailableEffectiveFiles */
    public function testUnreadableEffectiveInputsBypassMemoizationWithoutSuppressingDiagnostics(string $file): void
    {
        $this->withFixture(function (string $path) use ($file): void {
            $runner = $this->runner(static function (string $directory) use ($file): void {
                unlink($directory . '/' . $file);
            });
            $project = (new ProjectStateBuilder())->build($path);
            $request = new UpgradeRequest($path, [new UpgradeTarget('fixture/dependency', '^2.0')]);
            foreach (['direct', 'stage'] as $name) {
                $result = $runner->run($project, $request, new Scenario($name, $request->targets()));
                self::assertTrue($result->isSolverFailure());
                self::assertCount(1, $result->diagnostics());
                self::assertSame(ScenarioResult::OUTCOME_SUCCESS, $result->diagnostics()[0]->outcome());
            }
            self::assertSame(2, $runner->measurements()['process_counts']['diagnostic']);
            self::assertSame(0, $runner->measurements()['diagnostic_cache_hits']);
        });
    }

    /** @return list<array{string}> */
    public function unavailableEffectiveFiles(): array
    {
        return [['composer.json'], ['composer.lock']];
    }

    /** @param null|callable(string): void $beforeFailure */
    private function runner(?callable $beforeFailure = null): ComposerScenarioRunner
    {
        return new ComposerScenarioRunner(processRunner: static function (array $command, string $directory) use ($beforeFailure): array {
            if ($command[1] === 'prohibits') {
                return ['exit_code' => 1, 'stdout' => 'fixture/consumer 1.0.0 requires fixture/dependency (^1.0)', 'stderr' => ''];
            }
            if ($beforeFailure !== null) {
                $beforeFailure($directory);
            }
            return ['exit_code' => 2, 'stdout' => '', 'stderr' => 'Your requirements could not be resolved to an installable set of packages.'];
        });
    }

    /** @param callable(string): void $test */
    private function withFixture(callable $test): void
    {
        $path = sys_get_temp_dir() . '/diagnostic-memo-' . bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mirror(dirname(__DIR__, 5) . '/tests/fixtures/project-isolation', $path);
        $snapshot = FixtureSnapshot::capture($path);
        try {
            $test($path);
            $snapshot->assertUnchanged($this);
        } finally {
            $filesystem->remove($path);
        }
    }
}
