<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Integration;

use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Cli\AnalyzerFactory;
use PhpUpgradePreflight\Cli\FrameworkIntegrationRegistry;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
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
        self::assertSame('skipped', $report['staged_resolution']['execution_state']);
        self::assertSame('unknown', $report['staged_resolution']['status']);
        self::assertSame('stage_target_provider_unavailable', $report['staged_resolution']['stop_reason']);
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
    public function create(array $integrations): UpgradeAnalyzer
    {
        $runner = new ComposerScenarioRunner(null, null, static function (array $command, string $directory): array {
            if (($command[1] ?? null) === 'validate') {
                return ['exit_code' => 0, 'stdout' => 'Valid.', 'stderr' => ''];
            }

            file_put_contents($directory . DIRECTORY_SEPARATOR . 'composer.lock', json_encode([
                'packages' => [[
                    'name' => 'test-vendor/framework',
                    'version' => '2.0.0',
                ]],
                'packages-dev' => [],
            ], JSON_THROW_ON_ERROR));

            return ['exit_code' => 0, 'stdout' => 'Resolved.', 'stderr' => ''];
        });

        return new DefaultUpgradeAnalyzer($integrations, null, $runner);
    }
}
