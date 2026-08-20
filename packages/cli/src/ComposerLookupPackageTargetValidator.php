<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Composer\ComposerPackageMetadataLookup;
use PhpUpgradePreflight\Core\Composer\PackageMetadataLookupMode;
use PhpUpgradePreflight\Core\Composer\PackageMetadataLookupResult;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;

final class ComposerLookupPackageTargetValidator implements PackageTargetValidator, PackageTargetCandidateProvider
{
    private ComposerPackageMetadataLookup $lookup;
    private string $mode;
    private ComposerExecutionConfiguration $execution;

    public function __construct(
        string $mode,
        ?ComposerPackageMetadataLookup $lookup = null,
        ?ComposerExecutionConfiguration $execution = null
    ) {
        PackageMetadataLookupMode::assertSupported($mode);
        $this->mode = $mode;
        $this->lookup = $lookup ?? new ComposerPackageMetadataLookup();
        $this->execution = $execution ?? ComposerExecutionConfiguration::compatible();
    }

    public function validate(string $projectPath, string $package, string $constraint): PackageTargetValidation
    {
        $result = $this->lookup->lookup($projectPath, $package, $constraint, $this->execution, $this->mode);

        return $this->validationFromResult($result, $constraint);
    }

    public function discover(string $projectPath, string $package): PackageTargetValidation
    {
        $result = $this->lookup->lookup($projectPath, $package, '*', $this->execution, $this->mode);

        return $this->validationFromResult($result, '*', $this->candidateConstraints($result));
    }

    /** @param list<string> $candidateConstraints */
    private function validationFromResult(
        PackageMetadataLookupResult $result,
        string $constraint,
        array $candidateConstraints = []
    ): PackageTargetValidation {

        if ($result->status() === PackageMetadataLookupResult::STATUS_INVALID) {
            return new PackageTargetValidation(
                PackageTargetValidation::INVALID,
                'Composer rejected the package name or target constraint.'
            );
        }
        if ($result->status() === PackageMetadataLookupResult::STATUS_NOT_FOUND) {
            return new PackageTargetValidation(
                PackageTargetValidation::NOT_FOUND,
                'Composer reported that the package does not exist in the configured project repositories.'
            );
        }
        if ($result->status() === PackageMetadataLookupResult::STATUS_UNVERIFIED) {
            return new PackageTargetValidation(
                PackageTargetValidation::UNVERIFIED,
                'Composer metadata could not be verified (' . $result->reason() . '); analysis may still proceed.'
            );
        }
        if ($result->hasMatchingVersion() === false) {
            return new PackageTargetValidation(
                PackageTargetValidation::NO_MATCHING_VERSION,
                sprintf(
                    'Package exists, but none of its %d discovered versions matches %s.',
                    $result->availableVersionCount(),
                    $constraint
                )
            );
        }

        return new PackageTargetValidation(
            PackageTargetValidation::FOUND,
            sprintf(
                'Package exists and %d discovered version(s) match %s.',
                $result->matchingVersionCount(),
                $constraint
            ),
            $candidateConstraints
        );
    }

    /** @return list<string> */
    private function candidateConstraints(PackageMetadataLookupResult $result): array
    {
        if ($result->status() !== PackageMetadataLookupResult::STATUS_FOUND) {
            return [];
        }

        $constraints = [];
        foreach ($result->versions() as $version) {
            $normalized = ltrim(trim($version), 'vV');
            if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $normalized, $matches) !== 1) {
                continue;
            }

            $compatibleLine = sprintf('^%d.%d', (int) $matches[1], (int) $matches[2]);
            $constraints[$compatibleLine] = true;
            $constraints[$normalized] = true;
            if (count($constraints) >= 6) {
                break;
            }
        }

        return array_slice(array_keys($constraints), 0, 6);
    }
}
