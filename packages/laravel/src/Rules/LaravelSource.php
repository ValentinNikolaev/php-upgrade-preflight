<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Rules;

use PhpUpgradePreflight\Core\Model\ProjectState;

final class LaravelSource
{
    private ?int $major;
    /** @var array<string, array{root_constraint: ?string, locked_version: ?string, major: ?int}> */
    private array $observations;
    /** @var list<string> */
    private array $uncertainties;

    /**
     * @param array<string, array{root_constraint: ?string, locked_version: ?string, major: ?int}> $observations
     * @param list<string> $uncertainties
     */
    private function __construct(?int $major, array $observations, array $uncertainties)
    {
        $this->major = $major;
        $this->observations = $observations;
        $this->uncertainties = $uncertainties;
    }

    public static function fromProject(ProjectState $project): self
    {
        $requirements = $project->composerJson()->rootRequirements();
        $lockedFramework = $project->composerLock()->package('laravel/framework');

        if ($lockedFramework !== null) {
            $major = self::majorFromLockedVersion($lockedFramework->version());
            $observations = [
                'laravel/framework' => [
                    'root_constraint' => $requirements['laravel/framework'] ?? null,
                    'locked_version' => $lockedFramework->version(),
                    'major' => $major,
                ],
            ];

            return new self(
                $major,
                $observations,
                $major === null
                    ? [sprintf('The locked laravel/framework version `%s` does not identify one stable major.', $lockedFramework->version())]
                    : []
            );
        }

        $rootedIlluminate = [];
        foreach ($requirements as $package => $constraint) {
            if (str_starts_with($package, 'illuminate/')) {
                $rootedIlluminate[$package] = $constraint;
            }
        }
        ksort($rootedIlluminate);

        if ($rootedIlluminate === []) {
            return new self(
                null,
                [],
                ['No locked laravel/framework package or rooted Illuminate component versions identify the current Laravel major.']
            );
        }

        $observations = [];
        $majors = [];
        $unresolved = [];
        foreach ($rootedIlluminate as $package => $constraint) {
            $locked = $project->composerLock()->package($package);
            $version = $locked === null ? null : $locked->version();
            $major = $version === null
                ? LaravelTarget::majorFromConstraint($constraint)
                : self::majorFromLockedVersion($version);
            $observations[$package] = [
                'root_constraint' => $constraint,
                'locked_version' => $version,
                'major' => $major,
            ];

            if ($major === null) {
                $unresolved[] = $package;
            } else {
                $majors[$major] = true;
            }
        }

        if ($unresolved !== []) {
            return new self(null, $observations, [sprintf(
                'The current Laravel major is uncertain because rooted Illuminate versions or constraints do not identify one stable major: %s.',
                implode(', ', $unresolved)
            )]);
        }

        $detectedMajors = array_keys($majors);
        sort($detectedMajors, SORT_NUMERIC);
        if (count($detectedMajors) !== 1) {
            return new self(null, $observations, [sprintf(
                'Rooted Illuminate component versions are inconsistent across majors: %s.',
                implode(', ', $detectedMajors)
            )]);
        }

        return new self($detectedMajors[0], $observations, []);
    }

    public function major(): ?int
    {
        return $this->major;
    }

    /** @return array<string, array{root_constraint: ?string, locked_version: ?string, major: ?int}> */
    public function observations(): array
    {
        return $this->observations;
    }

    /** @return list<string> */
    public function uncertainties(): array
    {
        return $this->uncertainties;
    }

    private static function majorFromLockedVersion(string $version): ?int
    {
        if (preg_match('/^[vV]?(0|[1-9]\d*)(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.-]+)?$/', trim($version), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
