<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;

final class ProductPositioningTest extends TestCase
{
    private const COMMERCIAL_LICENSE_REQUEST_URL = 'https://docs.google.com/forms/d/e/1FAIpQLSfUlJJnSoqgUuJnKUCGzQQpIeXZtz471iD_XiPTjdnODbooYw/viewform';

    private const CODE_CONTRIBUTION_POLICY = 'External code contributions are not currently accepted.';

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
        self::assertStringContainsString('The released v0.3.x line', $readme);
        self::assertStringContainsString('v0.3.1 is the latest published release', $readme);
        self::assertStringContainsString(
            'composer require php-upgrade-preflight/cli:^0.3 php-upgrade-preflight/laravel:^0.3',
            $readme
        );
        self::assertStringNotContainsString('not yet a published package release', $readme);

        $status = $this->read('docs/project-status.md');
        self::assertStringContainsString('## v0.2.x compatibility commitment', $status);
        self::assertStringContainsString('## v0.3.x compatibility commitment', $status);
        self::assertStringContainsString('archival compatibility line', $status);
        self::assertStringContainsString('## v0.3 change boundary', $status);
        self::assertStringContainsString('schema `0.8`', $status);
        self::assertStringContainsString('It is not distributed or described as Open Source', $status);

        $security = $this->read('SECURITY.md');
        self::assertStringContainsString('latest published `0.3.x` release line', $security);
        self::assertStringContainsString('archival', $security);
        self::assertStringNotContainsString('release candidate', $security);

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

    public function testCommercialLicenseContactIsCanonicalAcrossPublicSurfaces(): void
    {
        foreach (
            [
                'README.md',
                'LICENSE',
                'docs/project-status.md',
                '.github/ISSUE_TEMPLATE/config.yml',
            ] as $relativePath
        ) {
            self::assertSame(
                1,
                substr_count($this->read($relativePath), self::COMMERCIAL_LICENSE_REQUEST_URL),
                sprintf('%s must contain the canonical commercial-license contact exactly once.', $relativePath)
            );
        }

        self::assertStringContainsString(
            'Submitting the form does not grant a license or authorize commercial use.',
            $this->read('docs/project-status.md')
        );
    }

    public function testContributorSurfacesKeepExternalCodeClosedAndDocumentationOpen(): void
    {
        $contributing = $this->read('CONTRIBUTING.md');
        self::assertStringContainsString('Documentation-only contributions are welcome.', $contributing);
        self::assertStringContainsString(self::CODE_CONTRIBUTION_POLICY, $contributing);
        self::assertStringContainsString('legally reviewed contributor license agreement', $contributing);
        self::assertStringNotContainsString('By contributing, you agree that your contribution is licensed', $contributing);
        self::assertStringContainsString('Bug reports and product feedback remain welcome', $contributing);
        self::assertStringContainsString('Report security vulnerabilities privately', $contributing);

        $pullRequestTemplate = $this->read('.github/pull_request_template.md');
        self::assertStringContainsString('documentation-only contributions', $pullRequestTemplate);
        self::assertStringContainsString(self::CODE_CONTRIBUTION_POLICY, $pullRequestTemplate);
        self::assertStringContainsString('Report security vulnerabilities privately', $pullRequestTemplate);

        $issueForms = array_merge(
            glob($this->root . '/.github/ISSUE_TEMPLATE/*.yml') ?: [],
            glob($this->root . '/.github/ISSUE_TEMPLATE/*.yaml') ?: []
        );
        $issueForms = array_values(array_filter(
            $issueForms,
            static fn (string $path): bool => !in_array(basename($path), ['config.yml', 'config.yaml'], true)
        ));
        self::assertNotEmpty($issueForms, 'At least one issue form must preserve the non-code reporting path.');

        foreach ($issueForms as $issueForm) {
            $contents = file_get_contents($issueForm);
            self::assertIsString($contents, sprintf('Unable to read %s.', $issueForm));
            self::assertStringContainsString(
                self::CODE_CONTRIBUTION_POLICY,
                $contents,
                sprintf('%s must not invite external code contributions.', $issueForm)
            );
        }
    }

    public function testThirdPartyAdapterFixturesRemainExplicitlyOutsideTheProductPackages(): void
    {
        foreach (['test-adapter', 'legacy-test-adapter'] as $package) {
            $manifest = json_decode(
                $this->read('packages/' . $package . '/composer.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            self::assertIsArray($manifest);
            self::assertSame('MIT', $manifest['license'] ?? null);
            self::assertStringStartsWith('Test-only ', $manifest['description'] ?? '');
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, sprintf('Unable to read %s.', $relativePath));

        return $contents;
    }
}
