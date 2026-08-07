<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;

final class LaravelTarget
{
    private int $major;
    private string $requestedConstraint;
    /** @var array<string, string> */
    private array $requestedConstraints;

    /** @param array<string, string> $requestedConstraints */
    private function __construct(int $major, string $requestedConstraint, array $requestedConstraints)
    {
        $this->major = $major;
        $this->requestedConstraint = $requestedConstraint;
        $this->requestedConstraints = $requestedConstraints;
    }

    public static function fromRequest(UpgradeRequest $request): ?self
    {
        $selectedMajor = null;
        $selectedConstraint = null;
        $requestedConstraints = [];

        foreach ($request->targets()->packageTargets() as $target) {
            $package = strtolower($target->package());
            if ($package !== 'laravel/framework' && !str_starts_with($package, 'illuminate/')) {
                continue;
            }

            $major = self::singleSupportedMajor($target->constraint());
            if ($major === null || ($selectedMajor !== null && $selectedMajor !== $major)) {
                return null;
            }

            $selectedMajor = $major;
            $selectedConstraint = $target->constraint();
            $requestedConstraints[$package] = $target->constraint();
        }

        return $selectedMajor === null || $selectedConstraint === null
            ? null
            : new self($selectedMajor, $selectedConstraint, $requestedConstraints);
    }

    public static function isLaravel7Project(ProjectState $project): bool
    {
        $lockedFramework = $project->composerLock()->package('laravel/framework');
        if ($lockedFramework !== null) {
            return self::versionSatisfies($lockedFramework->version(), '^7.0');
        }

        $requirements = $project->composerJson()->rootRequirements();
        $observations = [];
        foreach ($requirements as $package => $constraint) {
            if ($package !== 'laravel/framework' && !str_starts_with($package, 'illuminate/')) {
                continue;
            }

            $locked = $project->composerLock()->package($package);
            if ($locked !== null) {
                $observations[] = self::versionSatisfies($locked->version(), '^7.0');
                continue;
            }

            $observations[] = self::constraintsIntersect($constraint, '^7.0')
                && !self::constraintsIntersect($constraint, '^8.0|^9.0');
        }

        return $observations !== [] && !in_array(false, $observations, true);
    }

    public function major(): int
    {
        return $this->major;
    }

    public function requestedConstraint(): string
    {
        return $this->requestedConstraint;
    }

    public function frameworkRange(): string
    {
        return '^' . $this->major . '.0';
    }

    public function intersectsRequestedFrameworkRange(string $constraint): bool
    {
        foreach ($this->requestedConstraints as $requestedConstraint) {
            if (!self::constraintsIntersect($constraint, $requestedConstraint)) {
                return false;
            }
        }

        return $this->requestedConstraints !== [];
    }

    public function phpRange(): string
    {
        return $this->major === 8 ? '^7.3|^8.0' : '^8.0.2';
    }

    public static function constraintsIntersect(string $left, string $right): bool
    {
        try {
            $parser = new VersionParser();

            return Intervals::haveIntersections(
                $parser->parseConstraints($left),
                $parser->parseConstraints($right)
            );
        } catch (\UnexpectedValueException $exception) {
            return false;
        }
    }

    public static function versionSatisfies(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies(ltrim($version, 'vV'), $constraint);
        } catch (\UnexpectedValueException $exception) {
            return false;
        }
    }

    private static function singleSupportedMajor(string $constraint): ?int
    {
        try {
            $parser = new VersionParser();
            $candidate = $parser->parseConstraints($constraint);

            foreach ([8, 9] as $major) {
                $supportedMajor = $parser->parseConstraints('^' . $major . '.0');
                if (Intervals::isSubsetOf($candidate, $supportedMajor)) {
                    return $major;
                }
            }
        } catch (\UnexpectedValueException $exception) {
            return null;
        }

        return null;
    }
}
