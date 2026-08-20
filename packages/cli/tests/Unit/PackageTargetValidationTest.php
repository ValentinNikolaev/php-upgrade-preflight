<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\PackageTargetValidation;
use PHPUnit\Framework\TestCase;

final class PackageTargetValidationTest extends TestCase
{
    public function testItNormalizesMessageAndCandidateConstraints(): void
    {
        $validation = new PackageTargetValidation(
            PackageTargetValidation::FOUND,
            '  verified  ',
            ['^2.0', '^2.0', '2.1.0']
        );

        self::assertSame('verified', $validation->message());
        self::assertSame(['^2.0', '2.1.0'], $validation->candidateConstraints());
        self::assertTrue($validation->permitsAnalysis());
    }

    public function testItRejectsAnUnknownStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown package validation status');

        new PackageTargetValidation('maybe', 'Unknown state.');
    }

    /** @dataProvider nonPermittingStatusProvider */
    public function testOnlyFoundAndUnverifiedPermitAnalysis(string $status): void
    {
        $validation = new PackageTargetValidation($status, 'Cannot proceed.');

        self::assertFalse($validation->permitsAnalysis());
    }

    /** @return list<array{string}> */
    public function nonPermittingStatusProvider(): array
    {
        return [
            [PackageTargetValidation::NOT_FOUND],
            [PackageTargetValidation::NO_MATCHING_VERSION],
            [PackageTargetValidation::INVALID],
        ];
    }
}
