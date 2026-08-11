<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

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
        $workflowPaths = glob(dirname(__DIR__, 2) . '/.github/workflows/*.yml');
        self::assertIsArray($workflowPaths);
        self::assertNotEmpty($workflowPaths);

        foreach ($workflowPaths as $workflowPath) {
            $workflowName = basename($workflowPath);
            $workflow = $this->parseYamlFile('.github/workflows/' . $workflowName);
            $references = $this->collectUsesReferences($workflow);
            self::assertNotEmpty($references, sprintf('No action references found in %s.', $workflowName));

            foreach ($references as $reference) {
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

    public function testDependencyAuditIsScheduledAndBlocksReleasePackaging(): void
    {
        $security = $this->parseYamlFile('.github/workflows/security.yml');
        self::assertIsArray($security['on'] ?? null);
        self::assertArrayHasKey('workflow_call', $security['on']);
        self::assertArrayHasKey('workflow_dispatch', $security['on']);
        self::assertNotEmpty($security['on']['schedule'] ?? []);

        $audit = $security['jobs']['dependency-audit'] ?? null;
        self::assertIsArray($audit);
        $runs = array_values(array_filter(array_column($audit['steps'] ?? [], 'run'), 'is_string'));
        self::assertContains('composer audit --locked --no-interaction --no-ansi', $runs);

        $release = $this->parseYamlFile('.github/workflows/release.yml');
        self::assertSame(
            './.github/workflows/security.yml',
            $release['jobs']['dependency-audit']['uses'] ?? null
        );
        self::assertContains('dependency-audit', $release['jobs']['package']['needs'] ?? []);
    }

    public function testDependencyUpdateAutomationCoversComposerAndGitHubActions(): void
    {
        $dependabot = $this->parseYamlFile('.github/dependabot.yml');
        self::assertSame(2, $dependabot['version'] ?? null);
        self::assertIsArray($dependabot['updates'] ?? null);

        $ecosystems = [];
        foreach ($dependabot['updates'] as $update) {
            self::assertIsArray($update);
            self::assertSame('/', $update['directory'] ?? null);
            self::assertSame('weekly', $update['schedule']['interval'] ?? null);
            $ecosystems[] = $update['package-ecosystem'] ?? null;
        }

        sort($ecosystems);
        self::assertSame(['composer', 'github-actions'], $ecosystems);
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
        self::assertStringContainsString(
            '"php-upgrade-preflight/cli:${RELEASE_VERSION}"' . " \\\n" .
            '            "php-upgrade-preflight/laravel:${RELEASE_VERSION}"',
            $workflow
        );
        self::assertStringContainsString('--framework=laravel', $workflow);
        self::assertStringContainsString(
            'Archive-installed Laravel adapter did not contribute findings.',
            $workflow
        );
        self::assertStringContainsString('--format=json', $workflow);
        self::assertStringContainsString('php tests/smoke.php', $workflow);
        self::assertStringContainsString('php tools/verify-secret-leaks.php dist', $workflow);
        self::assertStringContainsString('DEPENDENCY-INVENTORY.json', $workflow);
        self::assertStringContainsString('ARTIFACT-PROVENANCE.json', $workflow);
        self::assertStringContainsString('php tools/release-artifact-metadata.php generate', $workflow);
        self::assertStringContainsString('php tools/release-artifact-metadata.php verify', $workflow);
        self::assertStringContainsString('"0.7"', $workflow);
        self::assertStringNotContainsString('!== "0.6"', $workflow);
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
        self::assertStringContainsString('composer test:fixtures', $freshClone);
        self::assertStringContainsString('checkout --detach $env:GITHUB_SHA', $freshClone);
        self::assertStringNotContainsString('composer check', $freshClone);
        self::assertSame(1, substr_count($freshClone, "'analyze',"));
        self::assertStringContainsString('php tools/render-markdown-report.php', $freshClone);
    }

    public function testReleaseHardeningCoversSupportedRuntimesAndBothConsumerPlatforms(): void
    {
        $quality = $this->parseYamlFile('.github/workflows/quality.yml');
        $includes = $quality['jobs']['quality']['strategy']['matrix']['include'] ?? null;
        self::assertIsArray($includes);

        $linuxPhp = [];
        $windowsPhp = [];
        foreach ($includes as $case) {
            self::assertIsArray($case);
            if (($case['os'] ?? null) === 'ubuntu-latest') {
                $linuxPhp[] = $case['php'] ?? null;
            }
            if (($case['os'] ?? null) === 'windows-latest') {
                $windowsPhp[] = $case['php'] ?? null;
            }
        }
        self::assertSame(['8.0', '8.1', '8.2', '8.3', '8.4', '8.5'], $linuxPhp);
        self::assertContains('8.3', $windowsPhp);

        $release = $this->parseYamlFile('.github/workflows/release.yml');
        self::assertSame(
            ['ubuntu-latest', 'windows-latest'],
            $release['jobs']['fresh-clone-audit']['strategy']['matrix']['os'] ?? null
        );
        self::assertSame(
            ['ubuntu-latest', 'windows-latest'],
            $release['jobs']['artifact-consumer']['strategy']['matrix']['os'] ?? null
        );

        $artifactConsumer = $release['jobs']['artifact-consumer']['steps'] ?? null;
        self::assertIsArray($artifactConsumer);
        $artifactConsumerRuns = implode(
            "\n",
            array_values(array_filter(array_column($artifactConsumer, 'run'), 'is_string'))
        );
        self::assertStringContainsString('artifact_repository="$(php -r', $artifactConsumerRuns);
        self::assertStringContainsString('str_replace("\\\\", "/", $argv[1])', $artifactConsumerRuns);
        self::assertStringContainsString('JSON_THROW_ON_ERROR', $artifactConsumerRuns);

        $artifactConsumerSetup = array_values(array_filter(
            $artifactConsumer,
            static fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'shivammathur/setup-php@')
        ));
        self::assertCount(1, $artifactConsumerSetup);
        self::assertSame('zip, fileinfo', $artifactConsumerSetup[0]['with']['extensions'] ?? null);
    }

    public function testPublishingRequiresSignedDistributionTagsAndPublishedPackageSmoke(): void
    {
        $workflow = $this->readRootFile('.github/workflows/release.yml');

        self::assertStringContainsString('distribution-preflight:', $workflow);
        self::assertStringContainsString('php-upgrade-preflight-${package}', $workflow);
        self::assertStringContainsString('.verification.verified', $workflow);
        self::assertStringContainsString('repos/${repository}/tarball/${GITHUB_REF_NAME}', $workflow);
        self::assertStringContainsString('verify-distribution-payload.php', $workflow);
        self::assertStringContainsString('cp -R "packages/${package}/."', $workflow);
        self::assertStringContainsString('published-quick-start:', $workflow);
        self::assertStringContainsString('Packagist did not expose all', $workflow);
        self::assertStringContainsString('php-upgrade-preflight/cli:${version}', $workflow);
        self::assertStringContainsString('php-upgrade-preflight/laravel:${version}', $workflow);
        self::assertStringContainsString('verify-installed-package-references.php', $workflow);
        self::assertStringContainsString('git/ref/tags/v${version}', $workflow);
        self::assertStringContainsString('git/tags/${tag_object}', $workflow);
        self::assertStringContainsString('"${expected_references[@]}"', $workflow);
        self::assertStringContainsString('published quick start target', $workflow);
        self::assertStringContainsString('before="$(fixture_digest "${target}")"', $workflow);
        self::assertStringContainsString('after="$(fixture_digest "${target}")"', $workflow);
        self::assertStringContainsString('The published quick start modified the analyzed target.', $workflow);

        $publish = strstr($workflow, "\n  publish:");
        self::assertIsString($publish);
        self::assertStringContainsString('- published-quick-start', $publish);
    }

    public function testLaravelCompatibilityBootsTheDiscoveredProviderAndCommandHarness(): void
    {
        $workflow = $this->parseYamlFile('.github/workflows/compatibility.yml');
        $job = $workflow['jobs']['installability'] ?? null;
        self::assertIsArray($job);
        self::assertSame(['normal', 'lowest'], $job['strategy']['matrix']['resolution'] ?? null);

        $cases = $job['strategy']['matrix']['case'] ?? null;
        self::assertIsArray($cases);
        $laravelHosts = [];
        foreach ($cases as $case) {
            self::assertIsArray($case);
            if (($case['smoke'] ?? null) === 'laravel') {
                $laravelHosts[$case['framework']] = $case['php'];
            }
        }
        self::assertSame([
            'laravel/framework:^8.0' => '8.0',
            'laravel/framework:^9.0' => '8.0',
            'laravel/framework:^10.0' => '8.1',
            'laravel/framework:^11.0' => '8.2',
            'laravel/framework:^12.0' => '8.2',
            'laravel/framework:^13.0' => '8.3',
        ], $laravelHosts);

        $runs = implode("\n", array_values(array_filter(array_column($job['steps'], 'run'), 'is_string')));
        self::assertStringNotContainsString('class_exists(', $runs);
        self::assertStringContainsString('tests/fixtures/laravel-app', $runs);
        self::assertStringContainsString('php tests/smoke.php', $runs);
        self::assertStringContainsString('$manifest["scripts"] = $fixture["scripts"]', $runs);
        foreach (['core', 'cli', 'laravel'] as $package) {
            self::assertStringContainsString(
                sprintf('options\\":{\\"versions\\":{\\"php-upgrade-preflight/%s\\":\\"0.2.x-dev\\"', $package),
                $runs
            );
        }
    }

    public function testCoverageIsMeasuredAndRatchetedBeforeSelectiveMutation(): void
    {
        $workflow = $this->parseYamlFile('.github/workflows/quality.yml');
        $coverage = $workflow['jobs']['coverage'] ?? null;
        self::assertIsArray($coverage);

        $steps = $coverage['steps'] ?? null;
        self::assertIsArray($steps);
        $runs = array_values(array_filter(array_column($steps, 'run'), 'is_string'));
        self::assertContains('composer test:coverage', $runs);
        self::assertContains('composer test:mutation', $runs);
        self::assertLessThan(
            array_search('composer test:mutation', $runs, true),
            array_search('composer test:coverage', $runs, true)
        );

        $setup = array_values(array_filter(
            $steps,
            static fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'shivammathur/setup-php@')
        ));
        self::assertCount(1, $setup);
        self::assertSame('pcov', $setup[0]['with']['coverage'] ?? null);
    }

    private function readRootFile(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertNotFalse($contents);

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYamlFile(string $relativePath): array
    {
        $workflow = Yaml::parseFile(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsArray($workflow, sprintf('%s must contain a YAML mapping.', $relativePath));

        return $workflow;
    }

    /**
     * @param array<mixed> $value
     *
     * @return list<string>
     */
    private function collectUsesReferences(array $value): array
    {
        $references = [];
        foreach ($value as $key => $child) {
            if ($key === 'uses' && is_string($child)) {
                $references[] = $child;
                continue;
            }

            if (is_array($child)) {
                array_push($references, ...$this->collectUsesReferences($child));
            }
        }

        return $references;
    }
}
