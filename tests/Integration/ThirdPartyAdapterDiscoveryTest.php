<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Integration;

use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Cli\AnalyzerFactory;
use PhpUpgradePreflight\Cli\FrameworkIntegrationRegistry;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Framework\FrameworkStageTargetProvider;
use PhpUpgradePreflight\LegacyTestAdapter\LegacyTestFrameworkIntegration;
use PHPUnit\Framework\TestCase;

final class ThirdPartyAdapterDiscoveryTest extends TestCase
{
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;

    protected function setUp(): void
    {
        parent::setUp();

        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Unable to create in-memory CLI streams.');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);

        parent::tearDown();
    }

    public function testComposerDiscoveredAdapterParticipatesInAutomaticCliAnalysis(): void
    {
        $command = new AnalyzeCommand(
            null,
            $this->stdout,
            $this->stderr,
            null,
            null,
            new FrameworkIntegrationRegistry(),
            new DeterministicThirdPartyAnalyzerFactory()
        );

        $exitCode = $command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__) . '/fixtures/projects/third-party-adapter',
            '--target=test-vendor/framework:^2.0',
            '--target-php=8.1.0',
        ]);

        $stderr = $this->streamContents($this->stderr);
        self::assertSame(AnalyzeCommand::SUCCESS, $exitCode, $stderr);
        self::assertSame('', $stderr);

        /** @var array<string, mixed> $report */
        $report = json_decode($this->streamContents($this->stdout), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('feasible_with_changes', $report['resolution']['status']);
        self::assertSame('modules/Plugin.php', $report['source_inventory'][0]['file']);
        self::assertSame('test-framework', $report['framework_findings'][0]['framework']);
        self::assertSame(
            'Test framework source usage requires review.',
            $report['framework_findings'][0]['summary']
        );
        self::assertSame(
            [['from_major' => 1, 'to_major' => 2]],
            $report['framework_findings'][0]['applies_to_hops']
        );
        self::assertSame('test-framework', $report['transition']['framework_guidance'][0]['framework']);
        self::assertSame('supported', $report['transition']['framework_guidance'][0]['status']);
        self::assertSame(
            ['test-framework'],
            $report['transition']['package_changes'][0]['package_families']
        );
        self::assertSame('evaluated', $report['staged_resolution']['execution_state']);
        self::assertSame('feasible_with_changes', $report['staged_resolution']['status']);
        self::assertSame('test-framework', $report['staged_resolution']['provider']);
        self::assertNull($report['staged_resolution']['stop_reason']);
        self::assertSame('test-framework-1-to-2', $report['staged_resolution']['stages'][0]['id']);
        self::assertSame('8.1.0', $report['staged_resolution']['stages'][0]['analysis_php']);
        self::assertSame([
            ['package' => 'php', 'constraint' => '8.1.0'],
            ['package' => 'test-vendor/framework', 'constraint' => '^2.0'],
        ], $report['staged_resolution']['stages'][0]['targets']);
        self::assertNotSame([], $report['staged_resolution']['stages'][0]['target_evidence']);

        $targetEvidence = array_values(array_filter(
            $report['evidence'],
            static fn (array $item): bool => $item['id'] === $report['staged_resolution']['stages'][0]['target_evidence'][0]
        ));
        self::assertCount(1, $targetEvidence);
        self::assertSame('^8.0', $targetEvidence[0]['context']['minimum_php_constraint']);
        self::assertSame(
            'final_target_php_exact_value_checked_against_adapter_constraint',
            $targetEvidence[0]['context']['analysis_php_provenance']
        );
    }

    public function testOldStyleComposerDiscoveredAdapterContributesGuidanceWithoutStagedClaims(): void
    {
        self::assertNotContains(
            FrameworkStageTargetProvider::class,
            class_implements(LegacyTestFrameworkIntegration::class) ?: []
        );

        $registry = new FrameworkIntegrationRegistry(null, [
            'php-upgrade-preflight/legacy-test-adapter' => dirname(__DIR__, 2) . '/packages/legacy-test-adapter',
        ]);
        $integrations = $registry->installed();
        self::assertCount(1, $integrations);
        self::assertInstanceOf(LegacyTestFrameworkIntegration::class, $integrations[0]);

        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/packages/legacy-test-adapter/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertSame('^0.3', $manifest['require']['php-upgrade-preflight/core']);

        $command = new AnalyzeCommand(
            null,
            $this->stdout,
            $this->stderr,
            null,
            null,
            $registry,
            new DeterministicThirdPartyAnalyzerFactory('legacy-vendor/framework')
        );

        $exitCode = $command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__) . '/fixtures/projects/legacy-third-party-adapter',
            '--target=legacy-vendor/framework:^2.0',
            '--target-php=8.1.0',
        ]);

        $stderr = $this->streamContents($this->stderr);
        self::assertSame(AnalyzeCommand::SUCCESS, $exitCode, $stderr);
        self::assertSame('', $stderr);

        /** @var array<string, mixed> $report */
        $report = json_decode($this->streamContents($this->stdout), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('legacy-test-framework', $report['transition']['framework_guidance'][0]['framework']);
        self::assertSame('supported', $report['transition']['framework_guidance'][0]['status']);
        self::assertSame('skipped', $report['staged_resolution']['execution_state']);
        self::assertSame('unknown', $report['staged_resolution']['status']);
        self::assertNull($report['staged_resolution']['provider']);
        self::assertSame('stage_target_provider_unavailable', $report['staged_resolution']['stop_reason']);
        self::assertSame([], $report['staged_resolution']['stages']);
    }

    /** @param resource $stream */
    private function streamContents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        self::assertIsString($contents);

        return $contents;
    }
}

final class DeterministicThirdPartyAnalyzerFactory implements AnalyzerFactory
{
    private string $frameworkPackage;

    public function __construct(string $frameworkPackage = 'test-vendor/framework')
    {
        $this->frameworkPackage = $frameworkPackage;
    }

    public function create(array $integrations): UpgradeAnalyzer
    {
        $frameworkPackage = $this->frameworkPackage;
        $runner = new ComposerScenarioRunner(null, null, static function (array $command, string $directory) use ($frameworkPackage): array {
            if (($command[1] ?? null) === 'validate') {
                return ['exit_code' => 0, 'stdout' => 'Valid.', 'stderr' => ''];
            }

            file_put_contents($directory . DIRECTORY_SEPARATOR . 'composer.lock', json_encode([
                'packages' => [[
                    'name' => $frameworkPackage,
                    'version' => '2.0.0',
                ]],
                'packages-dev' => [],
            ], JSON_THROW_ON_ERROR));

            return ['exit_code' => 0, 'stdout' => 'Resolved.', 'stderr' => ''];
        });

        return new DefaultUpgradeAnalyzer($integrations, null, $runner);
    }
}
