<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase
{
    public function testReleaseRequiresVerifiedAnnotatedTagFromTheApprovedReleaseLine(): void
    {
        $workflow = $this->readRootFile('.github/workflows/release.yml');

        self::assertStringContainsString('tag_object_type', $workflow);
        self::assertStringContainsString('.verification.verified', $workflow);
        self::assertStringContainsString('git merge-base --is-ancestor', $workflow);
        self::assertStringContainsString("release_branch='main'", $workflow);
        self::assertStringContainsString("release_branch='0.1.x'", $workflow);
        self::assertStringContainsString('refs/remotes/origin/${release_branch}', $workflow);
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
        self::assertStringContainsString(
            'gh release view "${RELEASE_VERSION}" --repo "${GITHUB_REPOSITORY}"',
            $publish
        );
        self::assertStringContainsString(
            "gh release upload \"\${RELEASE_VERSION}\" dist/* \\\n              --repo \"\${GITHUB_REPOSITORY}\"",
            $publish
        );
        self::assertStringContainsString(
            "gh release edit \"\${RELEASE_VERSION}\" \\\n              --repo \"\${GITHUB_REPOSITORY}\"",
            $publish
        );
        self::assertStringContainsString(
            "gh release create \"\${RELEASE_VERSION}\" \\\n              dist/* \\\n              --repo \"\${GITHUB_REPOSITORY}\"",
            $publish
        );
        self::assertSame(5, substr_count($publish, '--repo "${GITHUB_REPOSITORY}"'));
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

    public function testQualityGateLintsWorkflowFilesAndAvoidsRepeatedStaticChecks(): void
    {
        $workflow = $this->readRootFile('.github/workflows/quality.yml');

        self::assertStringContainsString('rhysd/actionlint@sha256:', $workflow);
        self::assertStringContainsString('static-analysis:', $workflow);
        self::assertStringContainsString('php tools/mask-secret-canaries.php', $workflow);
        self::assertStringContainsString('extensions: zip', $workflow);
        self::assertStringContainsString('composer validate:all', $workflow);
        self::assertStringContainsString('composer analyse', $workflow);
        self::assertStringContainsString('composer lint', $workflow);
        self::assertStringContainsString("if: runner.os == 'Windows'", $workflow);
        self::assertStringContainsString("if: runner.os != 'Windows'", $workflow);
        self::assertStringContainsString('composer install --prefer-dist --no-interaction --no-progress', $workflow);
        self::assertStringContainsString('run: composer test', $workflow);
        self::assertStringContainsString('run: php tools/verify-report-privacy.php', $workflow);
        self::assertGreaterThan(
            strpos($workflow, 'run: composer test'),
            strpos($workflow, 'run: php tools/verify-report-privacy.php')
        );
        self::assertStringNotContainsString('composer check', $workflow);
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
            "php-version: '8.0'\n          coverage: none\n          extensions: zip\n\n      - name: Mask synthetic secret canaries\n        run: php tools/mask-secret-canaries.php",
            $workflow
        );
        self::assertStringContainsString('vendor/bin/upgrade-intel --help', $workflow);
        self::assertStringContainsString('--format=json', $workflow);
        self::assertStringContainsString('php tests/smoke.php', $workflow);
        self::assertStringContainsString('php tools/verify-secret-leaks.php dist', $workflow);
        self::assertStringContainsString('php tools/mask-secret-canaries.php', $workflow);
        self::assertStringContainsString('php tools/render-markdown-report.php', $workflow);
        self::assertStringContainsString("needs:\n      - artifact-consumer", $workflow);
    }

    public function testFreshCloneAuditDoesNotRepeatTheFullQualityOrSolverWork(): void
    {
        $workflow = $this->readRootFile('.github/workflows/release.yml');
        $freshClone = strstr($workflow, "\n  fresh-clone-audit:");
        self::assertIsString($freshClone);
        $freshClone = strstr($freshClone, "\n  package:", true);
        self::assertIsString($freshClone);

        self::assertStringContainsString('composer validate:all', $freshClone);
        self::assertStringNotContainsString('composer check', $freshClone);
        self::assertSame(1, substr_count($freshClone, "'analyze',"));
        self::assertStringContainsString('php tools/render-markdown-report.php', $freshClone);
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
