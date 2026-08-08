<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase
{
    public function testReleaseRequiresVerifiedAnnotatedTagFromMain(): void
    {
        $workflow = $this->readRootFile('.github/workflows/release.yml');

        self::assertStringContainsString('tag_object_type', $workflow);
        self::assertStringContainsString('.verification.verified', $workflow);
        self::assertStringContainsString('git merge-base --is-ancestor', $workflow);
    }

    public function testQualityGateLintsWorkflowFiles(): void
    {
        $workflow = $this->readRootFile('.github/workflows/quality.yml');

        self::assertStringContainsString('rhysd/actionlint@sha256:', $workflow);
        self::assertStringContainsString('::add-mask::$value', $workflow);
    }

    public function testReleaseArchivesAreVersionedVerifiedInstalledAndScannedBeforePublication(): void
    {
        $workflow = $this->readRootFile('.github/workflows/release.yml');

        self::assertStringContainsString('composer config --working-dir="${staging}" version', $workflow);
        self::assertStringContainsString('artifact-consumer:', $workflow);
        self::assertStringContainsString('sha256sum --check --strict SHA256SUMS', $workflow);
        self::assertStringContainsString('php-upgrade-preflight/core:${RELEASE_VERSION}', $workflow);
        self::assertStringContainsString('php-upgrade-preflight/cli:${RELEASE_VERSION}', $workflow);
        self::assertStringContainsString('php-upgrade-preflight/laravel:${RELEASE_VERSION}', $workflow);
        self::assertStringContainsString('vendor/bin/upgrade-intel --help', $workflow);
        self::assertStringContainsString('--format=json', $workflow);
        self::assertStringContainsString('php tests/smoke.php', $workflow);
        self::assertStringContainsString('php tools/verify-secret-leaks.php dist', $workflow);
        self::assertStringContainsString('::add-mask::$value', $workflow);
        self::assertStringContainsString("needs:\n      - artifact-consumer", $workflow);
    }

    public function testLaravelCompatibilityBootsTheDiscoveredProviderAndCommandHarness(): void
    {
        $workflow = $this->readRootFile('.github/workflows/compatibility.yml');

        self::assertStringNotContainsString('class_exists(', $workflow);
        self::assertStringContainsString('tests/fixtures/laravel-app', $workflow);
        self::assertStringContainsString('php tests/smoke.php', $workflow);
        self::assertStringContainsString('$manifest["scripts"] = $fixture["scripts"]', $workflow);
    }

    private function readRootFile(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertNotFalse($contents);

        return $contents;
    }
}
