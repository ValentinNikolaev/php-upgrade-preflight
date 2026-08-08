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

    public function testOnlyATagPushCanEnterTagVerificationAndPublication(): void
    {
        $workflow = $this->readRootFile('.github/workflows/release.yml');
        $tagPushCondition = "github.event_name == 'push' && github.ref_type == 'tag'";

        self::assertStringContainsString('if: ' . $tagPushCondition, $workflow);
        self::assertStringContainsString(
            "RELEASE_VERSION: \${{ {$tagPushCondition} && github.ref_name || inputs.version }}",
            $workflow
        );
        self::assertStringNotContainsString("if: github.ref_type == 'tag'", $workflow);

        $publish = strstr($workflow, "\n  publish:");
        self::assertIsString($publish);
        self::assertStringContainsString('if: ' . $tagPushCondition, $publish);
        self::assertStringContainsString('gh run download "${GITHUB_RUN_ID}"', $publish);
        self::assertStringContainsString('sha256sum --check --strict SHA256SUMS', $publish);
        self::assertDoesNotMatchRegularExpression('/^\s+uses:/m', $publish);
        self::assertDoesNotMatchRegularExpression('/^\s{4}env:\R\s{6}GH_TOKEN:/m', $publish);
        self::assertMatchesRegularExpression('/^\s{8}env:\R\s{10}GH_TOKEN:/m', $publish);
    }

    public function testEveryExternalActionIsPinnedToAFullCommitSha(): void
    {
        foreach (['quality.yml', 'compatibility.yml', 'release.yml'] as $workflowName) {
            $workflow = $this->readRootFile('.github/workflows/' . $workflowName);
            preg_match_all('/^\s+uses:\s+([^\s#]+)/m', $workflow, $matches);
            self::assertNotEmpty($matches[1], sprintf('No action references found in %s.', $workflowName));

            foreach ($matches[1] as $reference) {
                if (str_starts_with($reference, './')) {
                    continue;
                }

                self::assertMatchesRegularExpression(
                    '~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+@[0-9a-f]{40}$~',
                    $reference,
                    sprintf('External action %s in %s is not pinned to a full commit SHA.', $reference, $workflowName)
                );
            }
        }
    }

    public function testQualityGateLintsWorkflowFiles(): void
    {
        $workflow = $this->readRootFile('.github/workflows/quality.yml');

        self::assertStringContainsString('rhysd/actionlint@sha256:', $workflow);
        self::assertStringContainsString('::add-mask::$value', $workflow);
        self::assertStringContainsString('extensions: zip', $workflow);
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
        self::assertStringContainsString(
            "php-version: '8.0'\n          coverage: none\n          extensions: zip\n\n      - name: Mask synthetic secret canaries",
            $workflow
        );
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
        foreach (['core', 'cli', 'laravel'] as $package) {
            self::assertStringContainsString(
                sprintf('options\\":{\\"versions\\":{\\"php-upgrade-preflight/%s\\":\\"0.1.x-dev\\"', $package),
                $workflow
            );
        }
    }

    private function readRootFile(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertNotFalse($contents);

        return $contents;
    }
}
