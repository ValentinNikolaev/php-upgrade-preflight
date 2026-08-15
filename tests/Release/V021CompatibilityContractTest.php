<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;
use PHPUnit\Framework\TestCase;

final class V021CompatibilityContractTest extends TestCase
{
    private string $root;

    /** @var array<string, mixed> */
    private array $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
        $contents = file_get_contents($this->root . '/tests/fixtures/contracts/v0.2.1.json');
        self::assertIsString($contents);
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);
        $this->contract = $contract;
    }

    public function testSignedReleaseIdentityAndPublicOperationAreFrozen(): void
    {
        self::assertSame('0.2.1', $this->contract['released_version']);
        self::assertSame('v0.2.1', $this->contract['signed_tag']);
        self::assertSame('679885ea2be38f4f33cd6f4b96ff1de09244b36d', $this->contract['released_commit']);

        $operation = $this->contract['public_php_operation'];
        self::assertIsArray($operation);
        $interface = new \ReflectionClass(UpgradeAnalyzer::class);
        $method = $interface->getMethod((string) $operation['method']);
        $parameters = $method->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame($operation['interface'], $interface->getName());
        self::assertSame($operation['parameters'][0], (string) $parameters[0]->getType());
        self::assertSame($operation['return_type'], (string) $method->getReturnType());
    }

    public function testCliArtisanAdapterAndExitSurfacesRemainCompatible(): void
    {
        $cli = $this->contract['cli'];
        self::assertIsArray($cli);
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        try {
            $exit = (new AnalyzeCommand(null, $stdout, $stderr))->run(['upgrade-intel', '--help']);
            rewind($stdout);
            $help = stream_get_contents($stdout);
            self::assertIsString($help);
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
        self::assertSame(AnalyzeCommand::SUCCESS, $exit);
        foreach ($cli['options'] as $option) {
            self::assertStringContainsString((string) $option, $help);
        }

        $signature = (new \ReflectionClass(AnalyzeUpgradeCommand::class))->getDefaultProperties()['signature'];
        self::assertIsString($signature);
        foreach ($this->contract['artisan']['options'] as $option) {
            self::assertStringContainsString((string) $option, $signature);
        }

        $metadata = $this->readJson($this->root . '/packages/laravel/composer.json');
        self::assertContains(
            $this->contract['adapter_metadata']['laravel'],
            $metadata['extra']['php-upgrade-preflight']['framework-adapters']
        );
        self::assertSame($this->contract['exit_policy']['success'], AnalyzeCommand::SUCCESS);
        self::assertSame($this->contract['exit_policy']['failure'], AnalyzeCommand::FAILURE);
        self::assertSame($this->contract['exit_policy']['invalid'], AnalyzeCommand::INVALID);
        self::assertTrue($this->contract['exit_policy']['blocked_and_unknown_are_reports']);
    }

    public function testSchemaAndAllSignedCanonicalReportsRemainByteIdentical(): void
    {
        $this->assertLockedFile($this->contract['canonical_schema']);
        self::assertSame('0.7', $this->contract['canonical_schema']['version']);

        $reports = $this->contract['canonical_reports'];
        self::assertIsArray($reports);
        self::assertCount(13, $reports);
        foreach ($reports as $report) {
            self::assertIsArray($report);
            $this->assertLockedFile($report);
        }
    }

    public function testLaravelTransitionBehaviorRemainsAnIndependentGuidanceContract(): void
    {
        $behavior = $this->contract['laravel_transition_behavior'];
        self::assertSame(['7-8', '7-9'], $behavior['direct_hops']);
        self::assertSame(['8-9', '9-10', '10-11', '11-12', '12-13'], $behavior['adjacent_hops']);
        self::assertSame(['supported', 'partially_supported', 'unsupported'], $behavior['statuses']);
        self::assertTrue($behavior['composer_feasibility_independent']);
    }

    /** @param array{path: mixed, sha256: mixed} $file */
    private function assertLockedFile(array $file): void
    {
        self::assertIsString($file['path']);
        self::assertIsString($file['sha256']);
        $path = $this->root . '/' . $file['path'];
        self::assertFileExists($path);
        self::assertSame($file['sha256'], hash_file('sha256', $path), $file['path']);
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
