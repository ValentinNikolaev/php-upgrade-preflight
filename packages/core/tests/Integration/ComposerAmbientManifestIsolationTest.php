<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Integration;

use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Filesystem\TemporaryWorkspaceManager;
use PhpUpgradePreflight\Core\Filesystem\WorkspaceManager;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Tests\Support\FixtureSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ComposerAmbientManifestIsolationTest extends TestCase
{
    /**
     * @dataProvider requests
     */
    public function testAmbientManifestCannotRedirectOfflineScenario(
        string $mode,
        string $constraint,
        bool $debug
    ): void {
        if ((new ExecutableFinder())->find('composer') === null) {
            self::markTestSkipped('Real Composer is required.');
        }

        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'preflight-ambient-' . bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mkdir($root);
        $originalComposer = getenv('COMPOSER');
        $originalNetwork = getenv('COMPOSER_DISABLE_NETWORK');
        $originalComposerEnv = $_ENV['COMPOSER'] ?? null;
        $originalComposerServer = $_SERVER['COMPOSER'] ?? null;
        $originalNetworkEnv = $_ENV['COMPOSER_DISABLE_NETWORK'] ?? null;
        $originalNetworkServer = $_SERVER['COMPOSER_DISABLE_NETWORK'] ?? null;
        $workspace = null;

        try {
            $source = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures'
                . DIRECTORY_SEPARATOR . 'path-repository';
            $filesystem->mirror($source, $root . DIRECTORY_SEPARATOR . 'input');
            $projectPath = $root . DIRECTORY_SEPARATOR . 'input' . DIRECTORY_SEPARATOR . 'project';
            $manifestPath = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            $manifest['repositories'][1]['url'] = $root . DIRECTORY_SEPARATOR . 'input' . DIRECTORY_SEPARATOR . 'repository' . DIRECTORY_SEPARATOR . '*';
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
            $filesystem->mkdir($projectPath . DIRECTORY_SEPARATOR . 'src');
            file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'sentinel.php', "<?php // original source\n");
            $snapshot = FixtureSnapshot::capture($root . DIRECTORY_SEPARATOR . 'input');
            putenv('COMPOSER=' . $manifestPath);
            $_ENV['COMPOSER'] = $manifestPath;
            $_SERVER['COMPOSER'] = $_ENV['COMPOSER'];
            putenv('COMPOSER_DISABLE_NETWORK=1');
            $_ENV['COMPOSER_DISABLE_NETWORK'] = '1';
            $_SERVER['COMPOSER_DISABLE_NETWORK'] = '1';
            $environmentProbe = new Process(['php', '-r', 'echo getenv("COMPOSER");'], $root, [
                'COMPOSER_NO_INTERACTION' => '1',
                'COMPOSER_NO_AUDIT' => '1',
            ]);
            $environmentProbe->run();
            self::assertSame(0, $environmentProbe->getExitCode(), $environmentProbe->getErrorOutput());
            self::assertSame($manifestPath, $environmentProbe->getOutput());

            $project = (new ProjectStateBuilder())->build($projectPath);
            $request = new UpgradeRequest(
                $projectPath,
                [new UpgradeTarget('fixture/dependency', $constraint)],
                null,
                null,
                [],
                [],
                'json',
                null,
                $debug,
                [],
                null,
                $mode === ComposerExecutionConfiguration::MODE_RESTRICTED
                    ? ComposerExecutionConfiguration::restricted()
                    : ComposerExecutionConfiguration::compatible()
            );
            $workspaces = new class () implements WorkspaceManager {
                /** @var list<string> */
                public array $createdPaths = [];
                private TemporaryWorkspaceManager $delegate;

                public function __construct()
                {
                    $this->delegate = new TemporaryWorkspaceManager();
                }

                public function createFromProject(string $projectPath): string
                {
                    $path = $this->delegate->createFromProject($projectPath);
                    $this->createdPaths[] = $path;

                    return $path;
                }

                public function remove(string $path): void
                {
                    $this->delegate->remove($path);
                }
            };
            $result = (new ComposerScenarioRunner($workspaces))->run(
                $project,
                $request,
                new Scenario('ambient-manifest-isolation', $request->targets(), false)
            );
            $workspace = $result->tempPath();

            if ($constraint === '^2.0') {
                self::assertTrue($result->succeeded(), $result->stderr());
                $lock = $result->lock();
                self::assertNotNull($lock);
                $dependency = $lock->package('fixture/dependency');
                self::assertNotNull($dependency);
                self::assertSame('2.0.0', $dependency->version());
            } else {
                self::assertTrue($result->isSolverFailure(), $result->stderr());
                self::assertNotEmpty($result->diagnostics());
            }
            $snapshot->assertUnchanged($this);
            if ($debug) {
                self::assertNotNull($workspace);
                self::assertDirectoryExists($workspace);
            } else {
                self::assertNull($workspace);
            }
            foreach ($workspaces->createdPaths as $createdPath) {
                if ($debug && $createdPath === $workspace) {
                    continue;
                }
                self::assertDirectoryDoesNotExist($createdPath);
            }
        } finally {
            if ($workspace !== null && is_dir($workspace)) {
                (new TemporaryWorkspaceManager())->remove($workspace);
            }
            $originalComposer === false ? putenv('COMPOSER') : putenv('COMPOSER=' . $originalComposer);
            if ($originalComposerEnv === null) {
                unset($_ENV['COMPOSER']);
            } else {
                $_ENV['COMPOSER'] = $originalComposerEnv;
            }
            if ($originalComposerServer === null) {
                unset($_SERVER['COMPOSER']);
            } else {
                $_SERVER['COMPOSER'] = $originalComposerServer;
            }
            $originalNetwork === false ? putenv('COMPOSER_DISABLE_NETWORK') : putenv('COMPOSER_DISABLE_NETWORK=' . $originalNetwork);
            if ($originalNetworkEnv === null) {
                unset($_ENV['COMPOSER_DISABLE_NETWORK']);
            } else {
                $_ENV['COMPOSER_DISABLE_NETWORK'] = $originalNetworkEnv;
            }
            if ($originalNetworkServer === null) {
                unset($_SERVER['COMPOSER_DISABLE_NETWORK']);
            } else {
                $_SERVER['COMPOSER_DISABLE_NETWORK'] = $originalNetworkServer;
            }
            $filesystem->remove($root);
        }
    }

    /** @return array<string, array{string, string, bool}> */
    public function requests(): array
    {
        return [
            'compatible success cleanup' => ['compatible', '^2.0', false],
            'compatible failure debug' => ['compatible', '^3.0', true],
            'restricted success debug' => ['restricted', '^2.0', true],
            'restricted failure cleanup' => ['restricted', '^3.0', false],
        ];
    }
}
