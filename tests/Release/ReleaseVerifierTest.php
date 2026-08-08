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
        self::assertSame([], (new ReleaseVerifier($this->root))->verify('0.1.0'));
    }

    public function testRejectsInvalidVersionFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ReleaseVerifier($this->root))->verify('v0.1.0');
    }

    public function testAcceptsAnotherPatchOnTheActiveReleaseLine(): void
    {
        $this->writeConsistentFixture('0.1.1');

        self::assertSame([], (new ReleaseVerifier($this->root))->verify('0.1.1'));
    }

    /** @dataProvider lockedReleaseSeriesProvider */
    public function testRejectsMinorAndMajorReleaseIncreasesWhileThePatchLineIsLocked(string $version): void
    {
        $errors = (new ReleaseVerifier($this->root))->verify($version);

        self::assertCount(1, $errors);
        self::assertStringContainsString('only 0.1.x patch releases are allowed', $errors[0]);
    }

    /** @return list<array{string}> */
    public function lockedReleaseSeriesProvider(): array
    {
        return [
            ['0.2.0'],
            ['1.0.0'],
        ];
    }

    public function testRejectsMissingExpectedInternalDependency(): void
    {
        $manifest = $this->readJson($this->root . '/packages/laravel/composer.json');
        unset($manifest['require']['php-upgrade-preflight/core']);
        $this->writeJson($this->root . '/packages/laravel/composer.json', $manifest);

        $errors = (new ReleaseVerifier($this->root))->verify('0.1.0');

        self::assertStringContainsString(
            "php-upgrade-preflight/laravel require.php-upgrade-preflight/core must be '^0.1'; found NULL",
            implode("\n", $errors)
        );
    }

    public function testRejectsWrongInternalConstraint(): void
    {
        $manifest = $this->readJson($this->root . '/packages/cli/composer.json');
        $manifest['require']['php-upgrade-preflight/core'] = '^0.2';
        $this->writeJson($this->root . '/packages/cli/composer.json', $manifest);

        $errors = (new ReleaseVerifier($this->root))->verify('0.1.0');

        self::assertStringContainsString(
            "php-upgrade-preflight/cli require.php-upgrade-preflight/core must be '^0.1'; found '^0.2'",
            implode("\n", $errors)
        );
    }

    public function testRejectsUnexpectedInternalDependency(): void
    {
        $manifest = $this->readJson($this->root . '/packages/core/composer.json');
        $manifest['require']['php-upgrade-preflight/laravel'] = '^0.1';
        $this->writeJson($this->root . '/packages/core/composer.json', $manifest);

        $errors = (new ReleaseVerifier($this->root))->verify('0.1.0');

        self::assertStringContainsString(
            'php-upgrade-preflight/core must not require unexpected internal package php-upgrade-preflight/laravel',
            implode("\n", $errors)
        );
    }

    public function testRejectsReleaseNotesForAnotherVersion(): void
    {
        $this->filesystem->dumpFile(
            $this->root . '/docs/releases/v0.1.0.md',
            "# PHP Upgrade Preflight v0.2.0\n\nWrong release.\n"
        );

        $errors = (new ReleaseVerifier($this->root))->verify('0.1.0');

        self::assertStringContainsString(
            "release notes heading must be '# PHP Upgrade Preflight v0.1.0'; found '# PHP Upgrade Preflight v0.2.0'",
            implode("\n", $errors)
        );
    }

    public function testRejectsReleaseNotesWithoutContent(): void
    {
        $this->filesystem->dumpFile(
            $this->root . '/docs/releases/v0.1.0.md',
            "# PHP Upgrade Preflight v0.1.0\n"
        );

        $errors = (new ReleaseVerifier($this->root))->verify('0.1.0');

        self::assertStringContainsString(
            'release notes must contain content after the heading',
            implode("\n", $errors)
        );
    }

    private function writeConsistentFixture(string $version = '0.1.0'): void
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
            sprintf("<?php\nfinal class ReportMetadata { public const TOOL_VERSION = '%s'; }\n", $version)
        );
        $this->filesystem->dumpFile(
            $this->root . '/CHANGELOG.md',
            sprintf("# Changelog\n\n## [%s] - 2026-08-08\n", $version)
        );
        $this->filesystem->dumpFile(
            $this->root . '/docs/releases/v' . $version . '.md',
            sprintf("# PHP Upgrade Preflight v%s\n\nRelease notes.\n", $version)
        );
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
