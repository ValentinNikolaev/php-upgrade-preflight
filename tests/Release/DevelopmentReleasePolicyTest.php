<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Core\Model\ReportMetadata;
use PhpUpgradePreflight\Tools\ReleaseVerifier;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/ReleaseVerifier.php';

final class DevelopmentReleasePolicyTest extends TestCase
{
    private string $root;

    /** @var array<string, mixed> */
    private array $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
        $contract = $this->readJson($this->root . '/tests/fixtures/contracts/v0.3.json');
        $this->identity = $contract['development_identity'];
    }

    public function testMainUsesOneAtomicV03DevelopmentIdentity(): void
    {
        self::assertSame($this->identity['tool_version'], ReportMetadata::TOOL_VERSION);
        self::assertSame($this->identity['schema'], ReportMetadata::SCHEMA_VERSION);
        self::assertSame('0.3', $this->identity['release_series']);
        self::assertSame('main', $this->identity['release_branch']);
        self::assertSame('0.2.x', $this->identity['maintenance_branch']);

        $root = $this->readJson($this->root . '/composer.json');
        foreach (['core', 'cli', 'laravel'] as $directory) {
            $name = 'php-upgrade-preflight/' . $directory;
            self::assertSame($this->identity['package_branch_alias'], $root['require'][$name]);
            $repositoryVersion = null;
            foreach ($root['repositories'] as $repository) {
                if (($repository['url'] ?? null) === 'packages/' . $directory) {
                    $repositoryVersion = $repository['options']['versions'][$name] ?? null;
                }
            }
            self::assertSame($this->identity['package_branch_alias'], $repositoryVersion);

            $package = $this->readJson($this->root . '/packages/' . $directory . '/composer.json');
            self::assertSame($this->identity['package_branch_alias'], $package['extra']['branch-alias']['dev-main']);
            if ($directory !== 'core') {
                self::assertSame($this->identity['internal_constraint'], $package['require']['php-upgrade-preflight/core']);
            }
        }
    }

    public function testReleaseVerifierAndWorkflowPermitOnlyV03FromMain(): void
    {
        $reflection = new \ReflectionClass(ReleaseVerifier::class);
        self::assertSame('0.3', $reflection->getConstant('ACTIVE_RELEASE_SERIES'));
        self::assertSame('0.8', $reflection->getConstant('ACTIVE_SCHEMA_VERSION'));
        $errors = (new ReleaseVerifier($this->root))->verify('0.2.1');
        self::assertCount(1, $errors);
        self::assertStringContainsString('only 0.3.x releases are currently allowed', $errors[0]);

        $workflow = $this->read($this->root . '/.github/workflows/release.yml');
        self::assertStringContainsString('default: 0.3.0', $workflow);
        self::assertStringContainsString("release_branch='main'", $workflow);
        self::assertStringContainsString("release_branch='0.2.x'", $workflow);
        self::assertStringContainsString('!== "0.8"', $workflow);
    }

    public function testLiveReleaseDocumentationAndCoveragePolicyAreSeparateFromV02History(): void
    {
        $checklist = $this->read($this->root . '/docs/release-checklist.md');
        self::assertStringStartsWith('# Release checklist', $checklist);
        foreach (['VERSION', 'SERIES', 'DEV_VERSION', '`0.2.x`', '`main`', '`0.3.x`'] as $needle) {
            self::assertStringContainsString($needle, $checklist);
        }
        self::assertStringNotContainsString('- [x]', $checklist);

        $versioning = $this->read($this->root . '/docs/versioning.md');
        self::assertStringContainsString('active `0.3.x` release line', $versioning);
        self::assertStringContainsString('archival `0.2.x` line', $versioning);
        self::assertStringContainsString('`0.2.x` maintenance branch', $versioning);

        $root = $this->readJson($this->root . '/composer.json');
        $commands = $root['scripts']['test:coverage'];
        self::assertCount(3, $commands);
        self::assertStringContainsString("unlink('build/coverage/clover.xml')", $commands[0]);
        self::assertStringContainsString('--coverage-clover build/coverage/clover.xml', $commands[1]);
        self::assertSame('php tools/verify-coverage.php build/coverage/clover.xml', $commands[2]);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $decoded = json_decode($this->read($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
