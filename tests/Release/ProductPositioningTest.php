<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;

final class ProductPositioningTest extends TestCase
{
    private const RETIRED_COMMERCIAL_LICENSE_REQUEST_URL = 'https://docs.google.com/forms/d/e/1FAIpQLSfUlJJnSoqgUuJnKUCGzQQpIeXZtz471iD_XiPTjdnODbooYw/viewform';

    private const INBOUND_OUTBOUND_POLICY = 'By contributing, you agree that your contribution is licensed';

    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testPublishedProductPackagesUseTheMitLicense(): void
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
                'MIT',
                $manifest['license'] ?? null,
                sprintf('%s must retain the product licensing decision.', $relativePath)
            );
        }
    }

    public function testPublicDocumentationKeepsTheProductPositioningExplicit(): void
    {
        $readme = $this->read('README.md');
        self::assertStringContainsString('Project status: Public beta', $readme);
        self::assertStringContainsString('Open Source software under the [MIT License](LICENSE)', $readme);
        self::assertStringContainsString('Public beta is not a production-readiness claim', $readme);
        self::assertStringContainsString('The released v0.3.x line', $readme);
        self::assertStringContainsString('v0.3.1 is the latest published release', $readme);
        self::assertStringContainsString(
            'composer require php-upgrade-preflight/cli:^0.3 php-upgrade-preflight/laravel:^0.3',
            $readme
        );
        self::assertStringNotContainsString('not yet a published package release', $readme);
        self::assertStringNotContainsString('PolyForm Noncommercial License 1.0.0](LICENSE)', $readme);

        $status = $this->read('docs/project-status.md');
        self::assertStringContainsString('## v0.2.x compatibility commitment', $status);
        self::assertStringContainsString('## v0.3.x compatibility commitment', $status);
        self::assertStringContainsString('archival compatibility line', $status);
        self::assertStringContainsString('## v0.3 change boundary', $status);
        self::assertStringContainsString('schema `0.8`', $status);
        self::assertStringContainsString('## Open Source licensing', $status);
        self::assertStringContainsString('Open Source software under the [MIT License](../LICENSE)', $status);
        self::assertStringContainsString(
            'Releases up to and including v0.3.1 were published under the PolyForm Noncommercial License 1.0.0',
            $status
        );

        $security = $this->read('SECURITY.md');
        self::assertStringContainsString('latest published `0.3.x` release line', $security);
        self::assertStringContainsString('archival', $security);
        self::assertStringNotContainsString('release candidate', $security);

        $contributing = $this->read('CONTRIBUTING.md');
        self::assertStringContainsString('Open Source public beta under the [MIT License](LICENSE)', $contributing);

        $limitations = $this->read('docs/limitations.md');
        self::assertStringContainsString('does not perform an upgrade, certify application compatibility', $limitations);
        self::assertStringContainsString('does not prove runtime or production compatibility', $limitations);
    }

    public function testLicenseIsTheByteStableMitText(): void
    {
        $license = $this->read('LICENSE');

        self::assertStringStartsWith('MIT License', $license);
        self::assertStringContainsString('Copyright (c) 2026 Valentin Nikolaev', $license);
        $normalized = rtrim(str_replace(["\r\n", "\r"], "\n", $license), "\n") . "\n";
        self::assertSame(
            hash('sha256', $this->expectedMitText()),
            hash('sha256', $normalized),
            'The MIT license text must remain byte-stable after line-ending normalization.'
        );
    }

    public function testRetiredCommercialLicenseContactIsAbsentFromPublicSurfaces(): void
    {
        foreach (
            [
                'README.md',
                'LICENSE',
                'CONTRIBUTING.md',
                'SECURITY.md',
                'docs/project-status.md',
                '.github/ISSUE_TEMPLATE/config.yml',
                '.github/pull_request_template.md',
            ] as $relativePath
        ) {
            self::assertStringNotContainsString(
                self::RETIRED_COMMERCIAL_LICENSE_REQUEST_URL,
                $this->read($relativePath),
                sprintf('%s must not reference the retired commercial-license contact.', $relativePath)
            );
        }
    }

    public function testContributorSurfacesKeepCodeContributionsOpenUnderInboundOutbound(): void
    {
        $contributing = $this->read('CONTRIBUTING.md');
        self::assertStringContainsString('Code, test, fixture, and documentation contributions are welcome.', $contributing);
        self::assertStringContainsString(self::INBOUND_OUTBOUND_POLICY, $contributing);
        self::assertStringContainsString('inbound=outbound', $contributing);
        self::assertStringContainsString('No contributor license agreement is required', $contributing);
        self::assertStringNotContainsString('External code contributions are not currently accepted', $contributing);
        self::assertStringContainsString('Bug reports and product feedback are welcome', $contributing);
        self::assertStringContainsString('Report security vulnerabilities privately', $contributing);

        $pullRequestTemplate = $this->read('.github/pull_request_template.md');
        self::assertStringContainsString('inbound=outbound', $pullRequestTemplate);
        self::assertStringContainsString('licensed under the repository\'s MIT License', $pullRequestTemplate);
        self::assertStringNotContainsString('External code contributions are not currently accepted', $pullRequestTemplate);
        self::assertStringContainsString('security policy', $pullRequestTemplate);

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
            self::assertStringNotContainsString(
                'External code contributions are not currently accepted',
                $contents,
                sprintf('%s must not repeat the retired closed-contribution policy.', $issueForm)
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

    private function expectedMitText(): string
    {
        return <<<'TEXT'
MIT License

Copyright (c) 2026 Valentin Nikolaev

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

TEXT;
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, sprintf('Unable to read %s.', $relativePath));

        return $contents;
    }
}
