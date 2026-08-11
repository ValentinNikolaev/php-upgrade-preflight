<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Tools\ReleaseArtifactMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(__DIR__, 2) . '/tools/ReleaseArtifactMetadata.php';

final class ReleaseArtifactMetadataTest extends TestCase
{
    private string $root;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->root = sys_get_temp_dir() . '/preflight-release-metadata-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir([$this->root . '/packages/core', $this->root . '/packages/cli', $this->root . '/packages/laravel', $this->root . '/dist']);

        foreach (['core', 'cli', 'laravel'] as $package) {
            $requirements = ['php' => '^8.0'];
            if ($package !== 'core') {
                $requirements['php-upgrade-preflight/core'] = '^0.2';
            }
            $this->filesystem->dumpFile(
                $this->root . '/packages/' . $package . '/composer.json',
                json_encode([
                    'name' => 'php-upgrade-preflight/' . $package,
                    'require' => $requirements,
                ], JSON_THROW_ON_ERROR)
            );
            $this->filesystem->dumpFile(
                sprintf('%s/dist/php-upgrade-preflight-%s-v0.2.0.zip', $this->root, $package),
                'archive-' . $package
            );
        }
        $this->filesystem->dumpFile($this->root . '/composer.lock', json_encode([
            'content-hash' => 'fixture-lock-hash',
            'packages' => [['name' => 'composer/semver', 'version' => '3.4.4']],
            'packages-dev' => [['name' => 'phpunit/phpunit', 'version' => '9.6.0']],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->root);
        parent::tearDown();
    }

    public function testItGeneratesAndVerifiesInventoryProvenanceAndChecksums(): void
    {
        $metadata = new ReleaseArtifactMetadata($this->root);
        $source = [
            'repository' => 'https://github.com/example/project',
            'commit' => str_repeat('a', 40),
            'ref' => 'refs/tags/v0.2.0',
            'workflow' => '.github/workflows/release.yml',
            'run_uri' => 'https://github.com/example/project/actions/runs/123',
        ];
        $metadata->generate('0.2.0', $this->root . '/dist', $source);

        self::assertSame([], $metadata->verify('0.2.0', $this->root . '/dist', $source));

        $inventory = $this->decode($this->root . '/dist/DEPENDENCY-INVENTORY.json');
        self::assertSame('0.2.0', $inventory['release_version']);
        self::assertCount(3, $inventory['released_packages']);
        self::assertSame('composer/semver', $inventory['locked_build_dependencies'][0]['name']);
        self::assertFalse($inventory['locked_build_dependencies'][0]['development']);

        $provenance = $this->decode($this->root . '/dist/ARTIFACT-PROVENANCE.json');
        self::assertSame(str_repeat('a', 40), $provenance['source']['commit']);
        self::assertSame('v0.2.0', $provenance['release_tag']);
        self::assertCount(3, $provenance['artifacts']);

        $checksums = file($this->root . '/dist/SHA256SUMS', FILE_IGNORE_NEW_LINES);
        self::assertIsArray($checksums);
        self::assertCount(5, $checksums);
    }

    public function testVerificationDetectsAnArchiveChangedAfterProvenanceWasGenerated(): void
    {
        $metadata = new ReleaseArtifactMetadata($this->root);
        $metadata->generate('0.2.0', $this->root . '/dist', [
            'repository' => 'https://github.com/example/project',
            'commit' => str_repeat('b', 40),
            'ref' => 'refs/heads/main',
            'workflow' => '.github/workflows/release.yml',
            'run_uri' => 'https://github.com/example/project/actions/runs/456',
        ]);

        $this->filesystem->appendToFile(
            $this->root . '/dist/php-upgrade-preflight-core-v0.2.0.zip',
            '-tampered'
        );

        self::assertContains(
            'Artifact digest mismatch: php-upgrade-preflight-core-v0.2.0.zip',
            $metadata->verify('0.2.0', $this->root . '/dist')
        );
    }

    public function testVerificationRejectsMalformedChecksumRecords(): void
    {
        $metadata = $this->generate();
        $this->filesystem->dumpFile(
            $this->root . '/dist/SHA256SUMS',
            "not-a-checksum\nstill-invalid\nthird\nfourth\nfifth\n"
        );

        $errors = $metadata->verify('0.2.0', $this->root . '/dist');

        self::assertContains('SHA256SUMS contains an invalid record: not-a-checksum', $errors);
        self::assertContains('SHA256SUMS must describe exactly the five release assets.', $errors);
    }

    public function testVerificationRejectsIncorrectChecksumDigest(): void
    {
        $metadata = $this->generate();
        $path = $this->root . '/dist/SHA256SUMS';
        $checksums = file($path, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($checksums);
        $checksums[0] = str_repeat('0', 64) . substr($checksums[0], 64);
        $this->filesystem->dumpFile($path, implode("\n", $checksums) . "\n");

        $errors = $metadata->verify('0.2.0', $this->root . '/dist');

        self::assertStringStartsWith('SHA256SUMS digest mismatch:', $this->firstMatchingError(
            $errors,
            'SHA256SUMS digest mismatch:'
        ));
    }

    public function testVerificationRejectsIncompleteInventoryAndProvenance(): void
    {
        $metadata = $this->generate();
        $inventoryPath = $this->root . '/dist/DEPENDENCY-INVENTORY.json';
        $inventory = $this->decode($inventoryPath);
        $inventory['released_packages'] = [];
        $this->filesystem->dumpFile($inventoryPath, json_encode($inventory, JSON_THROW_ON_ERROR));

        $provenancePath = $this->root . '/dist/ARTIFACT-PROVENANCE.json';
        $provenance = $this->decode($provenancePath);
        unset($provenance['source']);
        $provenance['artifacts'][0]['package'] = 'php-upgrade-preflight/wrong';
        $this->filesystem->dumpFile($provenancePath, json_encode($provenance, JSON_THROW_ON_ERROR));

        $errors = $metadata->verify('0.2.0', $this->root . '/dist');

        self::assertContains('Dependency inventory must describe exactly the three released packages.', $errors);
        self::assertContains('Artifact provenance source must be an object.', $errors);
        self::assertStringStartsWith('Artifact package mapping mismatch:', $this->firstMatchingError(
            $errors,
            'Artifact package mapping mismatch:'
        ));
    }

    public function testGenerationRejectsMismatchedTagReference(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source tag does not match the release version.');

        (new ReleaseArtifactMetadata($this->root))->generate('0.2.0', $this->root . '/dist', [
            'repository' => 'https://github.com/example/project',
            'commit' => str_repeat('c', 40),
            'ref' => 'refs/tags/v0.2.1',
            'workflow' => '.github/workflows/release.yml',
            'run_uri' => 'https://github.com/example/project/actions/runs/789',
        ]);
    }

    public function testVerificationRejectsRehashedButSemanticallyForgedMetadata(): void
    {
        $metadata = $this->generate();
        $inventoryPath = $this->root . '/dist/DEPENDENCY-INVENTORY.json';
        $inventory = $this->decode($inventoryPath);
        $inventory['locked_build_dependencies'][0]['version'] = 'forged-version';
        $this->filesystem->dumpFile($inventoryPath, json_encode($inventory, JSON_THROW_ON_ERROR));

        $provenancePath = $this->root . '/dist/ARTIFACT-PROVENANCE.json';
        $provenance = $this->decode($provenancePath);
        $provenance['source']['repository'] = 'https://github.com/example/forged';
        $provenance['build']['run_uri'] = 'https://github.com/example/forged/actions/runs/1';
        $this->filesystem->dumpFile($provenancePath, json_encode($provenance, JSON_THROW_ON_ERROR));
        $this->rewriteChecksums();

        $errors = $metadata->verify('0.2.0', $this->root . '/dist', $this->expectedSource());

        self::assertContains(
            'Dependency inventory does not match the checked-out manifests and Composer lock.',
            $errors
        );
        self::assertContains(
            'Artifact provenance source does not match the current release workflow inputs.',
            $errors
        );
        self::assertContains(
            'Artifact provenance build does not match the current release workflow inputs.',
            $errors
        );
        self::assertSame([], array_values(array_filter(
            $errors,
            static fn (string $error): bool => str_starts_with($error, 'SHA256SUMS')
        )));
    }

    private function generate(): ReleaseArtifactMetadata
    {
        $metadata = new ReleaseArtifactMetadata($this->root);
        $metadata->generate('0.2.0', $this->root . '/dist', $this->expectedSource());

        return $metadata;
    }

    /** @return array{repository:string, commit:string, ref:string, workflow:string, run_uri:string} */
    private function expectedSource(): array
    {
        return [
            'repository' => 'https://github.com/example/project',
            'commit' => str_repeat('d', 40),
            'ref' => 'refs/tags/v0.2.0',
            'workflow' => '.github/workflows/release.yml',
            'run_uri' => 'https://github.com/example/project/actions/runs/999',
        ];
    }

    private function rewriteChecksums(): void
    {
        $files = [
            'ARTIFACT-PROVENANCE.json',
            'DEPENDENCY-INVENTORY.json',
            'php-upgrade-preflight-cli-v0.2.0.zip',
            'php-upgrade-preflight-core-v0.2.0.zip',
            'php-upgrade-preflight-laravel-v0.2.0.zip',
        ];
        $lines = [];
        foreach ($files as $file) {
            $hash = hash_file('sha256', $this->root . '/dist/' . $file);
            self::assertIsString($hash);
            $lines[] = $hash . '  ' . $file;
        }
        $this->filesystem->dumpFile($this->root . '/dist/SHA256SUMS', implode("\n", $lines) . "\n");
    }

    /** @param list<string> $errors */
    private function firstMatchingError(array $errors, string $prefix): string
    {
        foreach ($errors as $error) {
            if (str_starts_with($error, $prefix)) {
                return $error;
            }
        }

        self::fail(sprintf('No error started with %s.', $prefix));
    }

    /** @return array<string, mixed> */
    private function decode(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
