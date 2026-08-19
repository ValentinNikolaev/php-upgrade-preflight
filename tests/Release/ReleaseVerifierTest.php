<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Tools\ReleaseVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(__DIR__, 2) . '/tools/ReleaseVerifier.php';

final class ReleaseVerifierTest extends TestCase
{
    private Filesystem $filesystem;
    private string $root;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->root = sys_get_temp_dir() . '/php-upgrade-preflight-release-verifier-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->root);
        $this->writeConsistentFixture();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->root);
    }

    public function testAcceptsConsistentReleaseMetadata(): void
    {
        self::assertSame([], (new ReleaseVerifier($this->root))->verify('0.3.0'));
    }

    /** @dataProvider invalidVersionProvider */
    public function testRejectsInvalidVersionFormat(string $version): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Release version must use MAJOR.MINOR.PATCH format.');

        (new ReleaseVerifier($this->root))->verify($version);
    }

    /** @return list<array{string}> */
    public function invalidVersionProvider(): array
    {
        return [
            [''],
            ['v0.3.0'],
            ['0.3'],
            ['0.3.0.0'],
            ['00.1.0'],
            ['0.01.0'],
            ['0.3.00'],
            ['0.3.0-beta'],
        ];
    }

    public function testAcceptsAnotherPatchOnTheActiveReleaseLine(): void
    {
        $this->writeConsistentFixture('0.3.1');

        self::assertSame([], (new ReleaseVerifier($this->root))->verify('0.3.1'));
    }

    /** @dataProvider lockedReleaseSeriesProvider */
    public function testRejectsInactiveReleaseSeries(string $version): void
    {
        $errors = (new ReleaseVerifier($this->root))->verify($version);

        self::assertCount(1, $errors);
        self::assertStringContainsString('only 0.3.x releases are currently allowed', $errors[0]);
    }

    /** @return list<array{string}> */
    public function lockedReleaseSeriesProvider(): array
    {
        return [
            ['0.1.1'],
            ['0.2.1'],
            ['1.0.0'],
        ];
    }

    /** @dataProvider invalidMetadataProvider */
    public function testRejectsEveryInconsistentReleaseMetadataBranch(string $case, string $expectedError): void
    {
        $this->makeFixtureInvalid($case);

        $errors = (new ReleaseVerifier($this->root))->verify('0.3.0');

        self::assertStringContainsString(
            $expectedError,
            implode("\n", $errors),
            sprintf('The %s fixture did not exercise its expected verifier branch.', $case)
        );
    }

    public function testRejectsUnreadableReleaseNotes(): void
    {
        $notesPath = $this->root . '/docs/releases/v0.3.0.md';
        $verifier = new ReleaseVerifier(
            $this->root,
            static fn (string $path) => $path === $notesPath ? false : file_get_contents($path)
        );

        $errors = $verifier->verify('0.3.0');

        self::assertStringContainsString('could not read release notes:', implode("\n", $errors));
    }

    /** @return list<array{string, string}> */
    public function invalidMetadataProvider(): array
    {
        return [
            ['missing-root-manifest', 'composer.json: could not read file'],
            ['invalid-root-json', 'composer.json: Syntax error'],
            ['non-array-root-json', 'composer.json: root value is not an object'],
            [
                'wrong-root-requirement',
                "root require.php-upgrade-preflight/core must be '0.3.x-dev'; found '0.4.x-dev'",
            ],
            [
                'missing-root-repository',
                "root path repository version for php-upgrade-preflight/cli must be '0.3.x-dev'; found NULL",
            ],
            ['missing-package-manifest', 'packages/core/composer.json: could not read file'],
            ['invalid-package-json', 'packages/core/composer.json: Syntax error'],
            [
                'wrong-branch-alias',
                "php-upgrade-preflight/laravel branch alias must be '0.3.x-dev'; found '0.4.x-dev'",
            ],
            [
                'explicit-package-version',
                'php-upgrade-preflight/core must not declare composer.json version',
            ],
            [
                'non-array-requirements',
                "php-upgrade-preflight/cli require.php-upgrade-preflight/core must be '^0.3'; found NULL",
            ],
            [
                'missing-internal-dependency',
                "php-upgrade-preflight/laravel require.php-upgrade-preflight/core must be '^0.3'; found NULL",
            ],
            [
                'wrong-internal-constraint',
                "php-upgrade-preflight/cli require.php-upgrade-preflight/core must be '^0.3'; found '^0.4'",
            ],
            [
                'unexpected-internal-dependency',
                'php-upgrade-preflight/core must not require unexpected internal package php-upgrade-preflight/laravel',
            ],
            ['missing-tool-version', 'could not find TOOL_VERSION'],
            ['missing-tool-version-file', 'could not find TOOL_VERSION'],
            [
                'wrong-tool-version',
                "ReportMetadata::TOOL_VERSION must be '0.3.0'; found '0.2.1'",
            ],
            ['missing-schema-version', 'could not find SCHEMA_VERSION'],
            [
                'wrong-schema-version',
                "ReportMetadata::SCHEMA_VERSION must be '0.8'; found '0.7'",
            ],
            ['missing-changelog-heading', 'CHANGELOG.md must contain a dated [0.3.0] release heading'],
            ['missing-changelog-file', 'CHANGELOG.md must contain a dated [0.3.0] release heading'],
            ['missing-release-notes', 'missing release notes:'],
            [
                'wrong-release-notes-heading',
                "release notes heading must be '# PHP Upgrade Preflight v0.3.0'; found '# PHP Upgrade Preflight v0.4.0'",
            ],
            ['empty-release-notes-body', 'release notes must contain content after the heading'],
            [
                'missing-wiki-evidence-link',
                'release notes must link machine-readable Wiki evidence v0.3.0-wiki-evidence.json',
            ],
            ['missing-wiki-evidence', 'v0.3.0-wiki-evidence.json: could not read file'],
            ['invalid-wiki-evidence-json', 'v0.3.0-wiki-evidence.json: Syntax error'],
            ['wrong-wiki-evidence-schema', "Wiki evidence schema_version must be '1'; found '2'"],
            [
                'wrong-wiki-evidence-mode',
                "Wiki evidence evidence_mode must be 'release-candidate'; found 'historical-baseline'",
            ],
            ['wrong-wiki-evidence-release', "Wiki evidence release must be '0.3.0'; found '0.3.1'"],
            [
                'wrong-wiki-materialization-gate',
                "Wiki evidence materialization_gate must be 'php tools/materialize-release-wikis.php --check'",
            ],
            ['missing-wiki-destination', 'Wiki evidence is missing required laravel destination'],
            ['duplicate-wiki-destination', 'Wiki evidence contains duplicate common destination'],
            ['unknown-wiki-destination', "Wiki evidence destinations[3] has unknown set 'unknown'"],
            [
                'wrong-wiki-repository',
                "Wiki evidence cli destination_repository must be 'ValentinNikolaev/php-upgrade-preflight-cli'",
            ],
            ['unknown-wiki-result', 'Wiki evidence core result status must be published or unchanged-after-review'],
            ['invalid-published-wiki-sha', 'Wiki evidence common wiki_commit must be a full lowercase 40-character Git SHA'],
            ['invalid-reviewed-wiki-sha', 'Wiki evidence core reviewed_remote_commit must be a full lowercase 40-character Git SHA'],
            ['failed-wiki-inventory-check', "Wiki evidence core inventory_check must be 'passed'; found 'failed'"],
            ['extra-wiki-result-field', 'Wiki evidence common published result must contain exactly [status, wiki_commit]'],
        ];
    }

    private function makeFixtureInvalid(string $case): void
    {
        if ($case === 'missing-root-manifest') {
            $this->filesystem->remove($this->root . '/composer.json');

            return;
        }
        if ($case === 'invalid-root-json') {
            $this->filesystem->dumpFile($this->root . '/composer.json', '{');

            return;
        }
        if ($case === 'non-array-root-json') {
            $this->filesystem->dumpFile($this->root . '/composer.json', "\"invalid-root\"\n");

            return;
        }
        if ($case === 'wrong-root-requirement') {
            $manifest = $this->readJson($this->root . '/composer.json');
            $manifest['require']['php-upgrade-preflight/core'] = '0.4.x-dev';
            $this->writeJson($this->root . '/composer.json', $manifest);

            return;
        }
        if ($case === 'missing-root-repository') {
            $manifest = $this->readJson($this->root . '/composer.json');
            $manifest['repositories'] = array_values(array_filter(
                $manifest['repositories'],
                static fn (array $repository): bool => $repository['url'] !== 'packages/cli'
            ));
            $this->writeJson($this->root . '/composer.json', $manifest);

            return;
        }
        if ($case === 'missing-package-manifest') {
            $this->filesystem->remove($this->root . '/packages/core/composer.json');

            return;
        }
        if ($case === 'invalid-package-json') {
            $this->filesystem->dumpFile($this->root . '/packages/core/composer.json', '{');

            return;
        }
        if ($case === 'wrong-branch-alias') {
            $manifest = $this->readJson($this->root . '/packages/laravel/composer.json');
            $manifest['extra']['branch-alias']['dev-main'] = '0.4.x-dev';
            $this->writeJson($this->root . '/packages/laravel/composer.json', $manifest);

            return;
        }
        if ($case === 'explicit-package-version') {
            $manifest = $this->readJson($this->root . '/packages/core/composer.json');
            $manifest['version'] = '0.3.0';
            $this->writeJson($this->root . '/packages/core/composer.json', $manifest);

            return;
        }
        if ($case === 'non-array-requirements') {
            $manifest = $this->readJson($this->root . '/packages/cli/composer.json');
            $manifest['require'] = 'invalid';
            $this->writeJson($this->root . '/packages/cli/composer.json', $manifest);

            return;
        }
        if ($case === 'missing-internal-dependency') {
            $manifest = $this->readJson($this->root . '/packages/laravel/composer.json');
            unset($manifest['require']['php-upgrade-preflight/core']);
            $this->writeJson($this->root . '/packages/laravel/composer.json', $manifest);

            return;
        }
        if ($case === 'wrong-internal-constraint') {
            $manifest = $this->readJson($this->root . '/packages/cli/composer.json');
            $manifest['require']['php-upgrade-preflight/core'] = '^0.4';
            $this->writeJson($this->root . '/packages/cli/composer.json', $manifest);

            return;
        }
        if ($case === 'unexpected-internal-dependency') {
            $manifest = $this->readJson($this->root . '/packages/core/composer.json');
            $manifest['require']['php-upgrade-preflight/laravel'] = '^0.3';
            $this->writeJson($this->root . '/packages/core/composer.json', $manifest);

            return;
        }
        if ($case === 'missing-tool-version') {
            $this->filesystem->dumpFile(
                $this->root . '/packages/core/src/Model/ReportMetadata.php',
                "<?php\nfinal class ReportMetadata {}\n"
            );

            return;
        }
        if ($case === 'missing-tool-version-file') {
            $this->filesystem->remove($this->root . '/packages/core/src/Model/ReportMetadata.php');

            return;
        }
        if ($case === 'wrong-tool-version') {
            $this->filesystem->dumpFile(
                $this->root . '/packages/core/src/Model/ReportMetadata.php',
                "<?php\nfinal class ReportMetadata { public const SCHEMA_VERSION = '0.8'; public const TOOL_VERSION = '0.2.1'; }\n"
            );

            return;
        }
        if ($case === 'missing-schema-version') {
            $this->filesystem->dumpFile(
                $this->root . '/packages/core/src/Model/ReportMetadata.php',
                "<?php\nfinal class ReportMetadata { public const TOOL_VERSION = '0.3.0'; }\n"
            );

            return;
        }
        if ($case === 'wrong-schema-version') {
            $this->filesystem->dumpFile(
                $this->root . '/packages/core/src/Model/ReportMetadata.php',
                "<?php\nfinal class ReportMetadata { public const SCHEMA_VERSION = '0.7'; public const TOOL_VERSION = '0.3.0'; }\n"
            );

            return;
        }
        if ($case === 'missing-changelog-heading') {
            $this->filesystem->dumpFile($this->root . '/CHANGELOG.md', "# Changelog\n\nUnreleased.\n");

            return;
        }
        if ($case === 'missing-changelog-file') {
            $this->filesystem->remove($this->root . '/CHANGELOG.md');

            return;
        }
        if ($case === 'missing-release-notes') {
            $this->filesystem->remove($this->root . '/docs/releases/v0.3.0.md');

            return;
        }
        if ($case === 'wrong-release-notes-heading') {
            $this->filesystem->dumpFile(
                $this->root . '/docs/releases/v0.3.0.md',
                "# PHP Upgrade Preflight v0.4.0\n\nWrong release.\n"
            );

            return;
        }
        if ($case === 'empty-release-notes-body') {
            $this->filesystem->dumpFile(
                $this->root . '/docs/releases/v0.3.0.md',
                "# PHP Upgrade Preflight v0.3.0\n"
            );

            return;
        }
        if ($case === 'missing-wiki-evidence-link') {
            $this->filesystem->dumpFile(
                $this->root . '/docs/releases/v0.3.0.md',
                "# PHP Upgrade Preflight v0.3.0\n\nRelease notes without Wiki evidence.\n"
            );

            return;
        }
        if ($case === 'missing-wiki-evidence') {
            $this->filesystem->remove($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');

            return;
        }
        if ($case === 'invalid-wiki-evidence-json') {
            $this->filesystem->dumpFile($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', '{');

            return;
        }
        if ($case === 'wrong-wiki-evidence-schema') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['schema_version'] = 2;
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'wrong-wiki-evidence-mode') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['evidence_mode'] = 'historical-baseline';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'wrong-wiki-evidence-release') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['release'] = '0.3.1';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'wrong-wiki-materialization-gate') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['materialization_gate'] = 'skipped';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'missing-wiki-destination') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            array_pop($evidence['destinations']);
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'duplicate-wiki-destination') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['destinations'][3] = $evidence['destinations'][0];
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'unknown-wiki-destination') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['destinations'][3]['set'] = 'unknown';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'wrong-wiki-repository') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['destinations'][2]['destination_repository'] = 'ValentinNikolaev/wrong';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'unknown-wiki-result') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['destinations'][1]['result']['status'] = 'ready-to-publish';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'invalid-published-wiki-sha') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['destinations'][0]['result']['wiki_commit'] = 'abc123';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'invalid-reviewed-wiki-sha') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['destinations'][1]['result']['reviewed_remote_commit'] = strtoupper(str_repeat('b', 40));
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'failed-wiki-inventory-check') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['destinations'][1]['result']['inventory_check'] = 'failed';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }
        if ($case === 'extra-wiki-result-field') {
            $evidence = $this->readJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json');
            $evidence['destinations'][0]['result']['note'] = 'not allowed';
            $this->writeJson($this->root . '/docs/releases/v0.3.0-wiki-evidence.json', $evidence);

            return;
        }

        self::fail(sprintf('Unknown invalid release fixture case %s.', $case));
    }

    private function writeConsistentFixture(string $version = '0.3.0'): void
    {
        $parts = explode('.', $version);
        $series = $parts[0] . '.' . $parts[1];
        $packageNames = [
            'core' => 'php-upgrade-preflight/core',
            'cli' => 'php-upgrade-preflight/cli',
            'laravel' => 'php-upgrade-preflight/laravel',
        ];
        $repositories = [];
        $rootRequirements = ['php' => '^8.0'];

        foreach ($packageNames as $directory => $packageName) {
            $repositories[] = [
                'type' => 'path',
                'url' => 'packages/' . $directory,
                'options' => ['versions' => [$packageName => $series . '.x-dev']],
            ];
            $rootRequirements[$packageName] = $series . '.x-dev';

            $requirements = ['php' => '^8.0'];
            if ($directory !== 'core') {
                $requirements['php-upgrade-preflight/core'] = '^' . $series;
            }

            $this->writeJson($this->root . '/packages/' . $directory . '/composer.json', [
                'name' => $packageName,
                'require' => $requirements,
                'extra' => ['branch-alias' => ['dev-main' => $series . '.x-dev']],
            ]);
        }

        $this->writeJson($this->root . '/composer.json', [
            'repositories' => $repositories,
            'require' => $rootRequirements,
        ]);
        $this->filesystem->dumpFile(
            $this->root . '/packages/core/src/Model/ReportMetadata.php',
            sprintf(
                "<?php\nfinal class ReportMetadata { public const SCHEMA_VERSION = '0.8'; public const TOOL_VERSION = '%s'; }\n",
                $version
            )
        );
        $this->filesystem->dumpFile(
            $this->root . '/CHANGELOG.md',
            sprintf("# Changelog\n\n## [%s] - 2026-08-08\n", $version)
        );
        $this->filesystem->dumpFile(
            $this->root . '/docs/releases/v' . $version . '.md',
            sprintf(
                "# PHP Upgrade Preflight v%s\n\nRelease notes.\n\n[Wiki release evidence](v%s-wiki-evidence.json)\n",
                $version,
                $version
            )
        );
        $this->writeJson(
            $this->root . '/docs/releases/v' . $version . '-wiki-evidence.json',
            $this->wikiEvidence($version)
        );
    }

    /** @return array<string, mixed> */
    private function wikiEvidence(string $version): array
    {
        $repositories = [
            'common' => 'ValentinNikolaev/php-upgrade-preflight',
            'core' => 'ValentinNikolaev/php-upgrade-preflight-core',
            'cli' => 'ValentinNikolaev/php-upgrade-preflight-cli',
            'laravel' => 'ValentinNikolaev/php-upgrade-preflight-laravel',
        ];
        $destinations = [];
        foreach ($repositories as $set => $repository) {
            $destinations[] = [
                'set' => $set,
                'destination_repository' => $repository,
                'wiki_repository' => 'https://github.com/' . $repository . '.wiki.git',
                'manifest' => 'release-wikis/' . $set . '/wiki-manifest.json',
                'result' => $set === 'common' || $set === 'cli'
                    ? ['status' => 'published', 'wiki_commit' => str_repeat('a', 40)]
                    : [
                        'status' => 'unchanged-after-review',
                        'reviewed_remote_commit' => str_repeat('b', 40),
                        'inventory_check' => 'passed',
                    ],
            ];
        }

        return [
            '$schema' => 'wiki-evidence.schema.json',
            'schema_version' => 1,
            'evidence_mode' => 'release-candidate',
            'release' => $version,
            'materialization_gate' => 'php tools/materialize-release-wikis.php --check',
            'destinations' => $destinations,
        ];
    }

    /** @param array<string, mixed> $contents */
    private function writeJson(string $path, array $contents): void
    {
        $json = json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->filesystem->dumpFile($path, $json . "\n");
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertNotFalse($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
