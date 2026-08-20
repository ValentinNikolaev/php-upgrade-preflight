<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\ComposerLookupPackageTargetValidator;
use PhpUpgradePreflight\Cli\PackageTargetValidation;
use PhpUpgradePreflight\Core\Composer\ComposerPackageMetadataLookup;
use PhpUpgradePreflight\Core\Composer\PackageMetadataLookupMode;
use PHPUnit\Framework\TestCase;

final class ComposerLookupPackageTargetValidatorTest extends TestCase
{
    public function testItMapsAFoundPackageWithMatchingVersions(): void
    {
        $validator = $this->validatorReturning(0, json_encode([
            'name' => 'vendor/package',
            'versions' => ['3.0.0', '2.1.0', '2.0.0'],
        ], JSON_THROW_ON_ERROR));

        $result = $validator->validate($this->projectPath(), 'vendor/package', '^2.0');

        self::assertSame(PackageTargetValidation::FOUND, $result->status());
        self::assertTrue($result->permitsAnalysis());
        self::assertStringContainsString('2 discovered version(s) match ^2.0', $result->message());
    }

    public function testItDerivesBoundedCompatibleAndExactChoicesFromStableReleases(): void
    {
        $validator = $this->validatorReturning(0, json_encode([
            'name' => 'vendor/package',
            'versions' => ['dev-main', 'v3.2.1', '3.2.0', '3.1.4', '2.9.0'],
        ], JSON_THROW_ON_ERROR));

        $result = $validator->discover($this->projectPath(), 'vendor/package');

        self::assertSame(PackageTargetValidation::FOUND, $result->status());
        self::assertSame(['^3.2', '3.2.1', '3.2.0', '^3.1', '3.1.4', '^2.9'], $result->candidateConstraints());
    }

    public function testItDistinguishesAnExistingPackageWithoutAMatchingVersion(): void
    {
        $validator = $this->validatorReturning(0, json_encode([
            'name' => 'vendor/package',
            'versions' => ['2.1.0', '2.0.0'],
        ], JSON_THROW_ON_ERROR));

        $result = $validator->validate($this->projectPath(), 'vendor/package', '^3.0');

        self::assertSame(PackageTargetValidation::NO_MATCHING_VERSION, $result->status());
        self::assertFalse($result->permitsAnalysis());
        self::assertStringContainsString('Package exists', $result->message());
        self::assertStringNotContainsString('does not exist', $result->message());
    }

    public function testItMapsExplicitRepositoryNotFoundSeparatelyFromUnverified(): void
    {
        $notFound = $this->validatorReturning(1, '', 'Package "vendor/package" not found.');
        $unverified = $this->validatorReturning(1, '', 'Could not resolve host repo.example.test.');

        $notFoundResult = $notFound->validate($this->projectPath(), 'vendor/package', '^2.0');
        $unverifiedResult = $unverified->validate($this->projectPath(), 'vendor/package', '^2.0');

        self::assertSame(PackageTargetValidation::NOT_FOUND, $notFoundResult->status());
        self::assertFalse($notFoundResult->permitsAnalysis());
        self::assertSame(PackageTargetValidation::UNVERIFIED, $unverifiedResult->status());
        self::assertTrue($unverifiedResult->permitsAnalysis());
        self::assertStringContainsString('analysis may still proceed', $unverifiedResult->message());
    }

    public function testItMapsInvalidLookupInputWithoutRunningComposer(): void
    {
        $validator = $this->validatorReturning(0, '');

        $result = $validator->validate($this->projectPath(), 'not-a-package', '^2.0');

        self::assertSame(PackageTargetValidation::INVALID, $result->status());
        self::assertFalse($result->permitsAnalysis());
        self::assertStringContainsString('Composer rejected', $result->message());
    }

    public function testDiscoveryReturnsNoCandidatesWhenMetadataIsUnverified(): void
    {
        $validator = $this->validatorReturning(1, '', 'Could not resolve host repo.example.test.');

        $result = $validator->discover($this->projectPath(), 'vendor/package');

        self::assertSame(PackageTargetValidation::UNVERIFIED, $result->status());
        self::assertSame([], $result->candidateConstraints());
    }

    private function validatorReturning(
        int $exitCode,
        string $stdout,
        string $stderr = ''
    ): ComposerLookupPackageTargetValidator {
        $lookup = new ComposerPackageMetadataLookup(
            static fn (): array => [
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]
        );

        return new ComposerLookupPackageTargetValidator(
            PackageMetadataLookupMode::PROJECT_REPOSITORIES,
            $lookup
        );
    }

    private function projectPath(): string
    {
        return dirname(__DIR__, 4);
    }
}
