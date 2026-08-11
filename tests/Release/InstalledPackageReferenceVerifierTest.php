<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Tools\InstalledPackageReferenceVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(__DIR__, 2) . '/tools/InstalledPackageReferenceVerifier.php';

final class InstalledPackageReferenceVerifierTest extends TestCase
{
    private string $lockPath;

    protected function setUp(): void
    {
        $this->lockPath = sys_get_temp_dir() . '/published-package-lock-' . bin2hex(random_bytes(8)) . '.json';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->lockPath);
    }

    public function testAcceptsExactVersionsAndSignedTagReferences(): void
    {
        $this->writeLock();

        self::assertSame([], (new InstalledPackageReferenceVerifier())->verify(
            $this->lockPath,
            '0.2.1',
            $this->expectedReferences()
        ));
    }

    public function testRejectsStaleVersionsAndSourceOrDistReferences(): void
    {
        $this->writeLock([
            'php-upgrade-preflight/core' => [
                'version' => 'v0.2.0',
                'source' => ['reference' => 'stale-core'],
                'dist' => ['reference' => 'core-ref'],
            ],
            'php-upgrade-preflight/cli' => [
                'version' => 'v0.2.1',
                'source' => ['reference' => 'cli-ref'],
                'dist' => ['reference' => 'stale-cli'],
            ],
        ]);

        $errors = (new InstalledPackageReferenceVerifier())->verify(
            $this->lockPath,
            '0.2.1',
            $this->expectedReferences()
        );

        self::assertSame([
            'Published quick start installed php-upgrade-preflight/core at 0.2.0; expected 0.2.1.',
            'Published php-upgrade-preflight/core source reference is stale-core; expected signed-tag commit core-ref.',
            'Published php-upgrade-preflight/cli dist reference is stale-cli; expected signed-tag commit cli-ref.',
            'Published quick start did not install php-upgrade-preflight/laravel.',
        ], $errors);
    }

    public function testRejectsMissingExpectedReferences(): void
    {
        $this->writeLock();

        $errors = (new InstalledPackageReferenceVerifier())->verify($this->lockPath, '0.2.1', []);

        self::assertCount(3, $errors);
        self::assertStringContainsString('Missing expected signed-tag reference', implode("\n", $errors));
    }

    /** @param array<string, array<string, mixed>>|null $overrides */
    private function writeLock(?array $overrides = null): void
    {
        $packages = [];
        foreach ($this->expectedReferences() as $name => $reference) {
            if ($overrides !== null && !array_key_exists($name, $overrides)) {
                continue;
            }
            $packages[] = ['name' => $name] + ($overrides[$name] ?? [
                'version' => 'v0.2.1',
                'source' => ['reference' => $reference],
                'dist' => ['reference' => $reference],
            ]);
        }

        file_put_contents($this->lockPath, json_encode(['packages' => $packages], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, string> */
    private function expectedReferences(): array
    {
        return [
            'php-upgrade-preflight/core' => 'core-ref',
            'php-upgrade-preflight/cli' => 'cli-ref',
            'php-upgrade-preflight/laravel' => 'laravel-ref',
        ];
    }
}
