<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
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

            $major = self::majorFromConstraint($target->constraint());
            if ($major === null || ($selectedMajor !== null && $selectedMajor !== $major)) {
                return null;
            }

            $selectedMajor = $major;
            if ($selectedConstraint === null || $package === 'laravel/framework') {
                $selectedConstraint = $target->constraint();
            }
            $requestedConstraints[$package] = $target->constraint();
        }

        return $selectedMajor === null || $selectedConstraint === null
            ? null
            : new self($selectedMajor, $selectedConstraint, $requestedConstraints);
    }

    public function major(): int
    {
        return $this->major;
    }

    public function requestedConstraint(): string
    {
        return $this->requestedConstraint;
    }

    /** @return array<string, string> */
    public function requestedConstraints(): array
    {
        return $this->requestedConstraints;
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

    public static function majorFromConstraint(string $constraint): ?int
    {
        try {
            $parser = new VersionParser();
            $candidate = $parser->parseConstraints($constraint);
            $intervals = Intervals::get($candidate);
            if ($intervals['numeric'] === [] || $intervals['branches']['exclude'] || $intervals['branches']['names'] !== []) {
                return null;
            }

            $startVersion = $intervals['numeric'][0]->getStart()->getVersion();
            if (preg_match('/^(0|[1-9]\d*)\./', $startVersion, $matches) !== 1) {
                return null;
            }

            $major = (int) $matches[1];
            $majorRange = $parser->parseConstraints(sprintf('>=%d.0.0 <%d.0.0', $major, $major + 1));

            return Intervals::isSubsetOf($candidate, $majorRange) ? $major : null;
        } catch (\UnexpectedValueException $exception) {
            return null;
        }
    }
}
