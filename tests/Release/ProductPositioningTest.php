<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;

final class ProductPositioningTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testPublishedProductPackagesUseThePolyFormNoncommercialLicense(): void
    {
        foreach (
            [
                'composer.json',
                'packages/core/composer.json',
                'packages/cli/composer.json',
                'packages/laravel/composer.json',
            ] as $relativePath
        ) {
            $manifest = json_decode($this->read($relativePath), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($manifest);
            self::assertSame(
                'PolyForm-Noncommercial-1.0.0',
                $manifest['license'] ?? null,
                sprintf('%s must retain the product licensing decision.', $relativePath)
            );
        }
    }

    public function testPublicDocumentationKeepsTheProductPositioningExplicit(): void
    {
        $readme = $this->read('README.md');
        self::assertStringContainsString('Project status: Public beta', $readme);
        self::assertStringContainsString('source-available software', $readme);
        self::assertStringContainsString('free for noncommercial use', $readme);
        self::assertStringContainsString('Commercial use requires a separate license', $readme);
        self::assertStringContainsString('It is not distributed as Open Source', $readme);
        self::assertStringContainsString('Public beta is not a production-readiness claim', $readme);

        $status = $this->read('docs/project-status.md');
        self::assertStringContainsString('## v0.2.x compatibility commitment', $status);
        self::assertStringContainsString('## v0.3 change boundary', $status);
        self::assertStringContainsString('schema `0.8`', $status);
        self::assertStringContainsString('It is not distributed or described as Open Source', $status);

        $contributing = $this->read('CONTRIBUTING.md');
        self::assertStringContainsString('source-available public beta, not an Open Source project', $contributing);

        $limitations = $this->read('docs/limitations.md');
        self::assertStringContainsString('does not perform an upgrade, certify application compatibility', $limitations);
        self::assertStringContainsString('does not prove runtime or production compatibility', $limitations);
    }

    public function testLicenseKeepsTheSourceAvailableNoticeAndPolyFormBody(): void
    {
        $license = $this->read('LICENSE');

        self::assertStringStartsWith("Source-Available and Commercial Licensing Notice\n", $license);
        self::assertStringContainsString(
            'This software is source-available under the PolyForm Noncommercial License 1.0.0 below.',
            $license
        );
        $normalized = str_replace(["\r\n", "\r"], "\n", $license);
        $bodyStart = strpos($normalized, '# PolyForm Noncommercial License 1.0.0');
        self::assertNotFalse($bodyStart);
        $polyFormBody = rtrim(substr($normalized, $bodyStart), "\n") . "\n";
        self::assertSame(
            'ffcca38841adb694b6f380647e15f17c446a4d1656fed51a1e2041d064c94cc8',
            hash('sha256', $polyFormBody),
            'The standard PolyForm license body must remain byte-stable after line-ending normalization.'
        );
        self::assertStringContainsString('https://polyformproject.org/licenses/noncommercial/1.0.0', $license);
    }

    public function testThirdPartyAdapterFixtureRemainsExplicitlyOutsideTheProductPackages(): void
    {
        $manifest = json_decode(
            $this->read('packages/test-adapter/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($manifest);
        self::assertSame('MIT', $manifest['license'] ?? null);
        self::assertStringStartsWith('Test-only third-party framework adapter fixture', $manifest['description'] ?? '');
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, sprintf('Unable to read %s.', $relativePath));

        return $contents;
    }
}
