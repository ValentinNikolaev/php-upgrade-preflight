<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Support;

use Composer\Semver\Semver;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use Symfony\Component\Process\Process;

final class LaravelTransitionFixtureRunner
{
    public static function create(): ComposerScenarioRunner
    {
        return new ComposerScenarioRunner(null, null, static function (array $command, string $workingDirectory): array {
            $manifest = self::readJson($workingDirectory . DIRECTORY_SEPARATOR . 'composer.json');
            $fixture = $manifest['extra']['php-upgrade-preflight-transition-fixture'] ?? null;
            $status = $manifest['extra']['php-upgrade-preflight-resolution'] ?? null;
            if (!is_string($fixture) || !is_string($status)) {
                throw new \RuntimeException('Transition fixture metadata is missing.');
            }

            if (in_array('validate', $command, true)) {
                return ['exit_code' => 0, 'stdout' => 'Fixture baseline is valid.', 'stderr' => ''];
            }

            if (($manifest['extra']['php-upgrade-preflight-composer-mode'] ?? null) === 'real') {
                return self::runComposer($command, $workingDirectory);
            }

            if (in_array('prohibits', $command, true)) {
                return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'No additional fixture diagnostic.'];
            }

            if ($status === 'blocked') {
                return [
                    'exit_code' => 2,
                    'stdout' => '',
                    'stderr' => implode("\n", [
                        'Your requirements could not be resolved to an installable set of packages.',
                        '- fixture/laravel-12-consumer 1.0.0 requires laravel/framework ^12.0 -> it conflicts with the requested Laravel 13 target.',
                    ]),
                ];
            }

            $violations = self::writeCandidateLock($workingDirectory, $manifest);
            if ($violations !== []) {
                return [
                    'exit_code' => 2,
                    'stdout' => '',
                    'stderr' => implode("\n", array_merge(
                        ['Your requirements could not be resolved to an installable set of packages.'],
                        array_map(static fn (string $violation): string => '- ' . $violation, $violations)
                    )),
                ];
            }

            return ['exit_code' => 0, 'stdout' => 'Fixture target resolved.', 'stderr' => ''];
        }, null, static function (): float {
            static $milliseconds = 0;

            return $milliseconds++ / 1000;
        }, static fn (array $command): array => self::runComposer($command));
    }

    /** @return array<string, mixed> */
    private static function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read transition fixture file: %s.', basename($path)));
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Transition fixture JSON must decode to an object.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private static function writeCandidateLock(string $workingDirectory, array $manifest): array
    {
        $lockPath = $workingDirectory . DIRECTORY_SEPARATOR . 'composer.lock';
        $lock = self::readJson($lockPath);
        $targetVersion = self::targetFrameworkVersion($manifest);
        $targetVersions = [
            'laravel/framework' => $targetVersion,
            'illuminate/console' => $targetVersion,
            'illuminate/support' => $targetVersion,
        ];
        $requirements = array_merge(
            is_array($manifest['require'] ?? null) ? $manifest['require'] : [],
            is_array($manifest['require-dev'] ?? null) ? $manifest['require-dev'] : []
        );

        foreach (['packages', 'packages-dev'] as $section) {
            if (!is_array($lock[$section] ?? null)) {
                continue;
            }
            foreach ($lock[$section] as &$package) {
                if (!is_array($package) || !is_string($package['name'] ?? null)) {
                    continue;
                }
                $name = strtolower($package['name']);
                if (isset($targetVersions[$name], $requirements[$name])) {
                    $package['version'] = $targetVersions[$name];
                }
            }
            unset($package);
        }

        $violations = self::candidateLockViolations($manifest, $lock);
        if ($violations !== []) {
            return $violations;
        }

        $lock['content-hash'] = 'laravel-transition-candidate';
        $encoded = json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($lockPath, $encoded) === false) {
            throw new \RuntimeException('Unable to write transition fixture candidate lock.');
        }

        return [];
    }

    /** @param array<string, mixed> $manifest */
    private static function targetFrameworkVersion(array $manifest): string
    {
        $requirements = array_change_key_case(array_merge(
            is_array($manifest['require'] ?? null) ? $manifest['require'] : [],
            is_array($manifest['require-dev'] ?? null) ? $manifest['require-dev'] : []
        ), CASE_LOWER);
        $constraint = null;
        foreach ($requirements as $package => $requirement) {
            if (($package === 'laravel/framework' || str_starts_with($package, 'illuminate/'))
                && is_string($requirement)) {
                $constraint = $requirement;
                break;
            }
        }
        if ($constraint === null) {
            throw new \RuntimeException('Transition fixture has no Laravel or Illuminate target constraint.');
        }

        $matchingMajors = [];
        for ($major = 8; $major <= 13; ++$major) {
            if (Semver::satisfies($major . '.0.0', $constraint)) {
                $matchingMajors[] = $major;
            }
        }
        if ($matchingMajors === []) {
            throw new \RuntimeException(sprintf(
                'Transition fixture target constraint `%s` does not select a modeled Laravel major.',
                $constraint
            ));
        }

        return sprintf('v%d.0.0', end($matchingMajors));
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $lock
     * @return list<string>
     */
    public static function candidateLockViolations(array $manifest, array $lock): array
    {
        $requirements = array_change_key_case(array_merge(
            is_array($manifest['require'] ?? null) ? $manifest['require'] : [],
            is_array($manifest['require-dev'] ?? null) ? $manifest['require-dev'] : []
        ), CASE_LOWER);
        $versions = [];
        foreach (['packages', 'packages-dev'] as $section) {
            foreach (is_array($lock[$section] ?? null) ? $lock[$section] : [] as $package) {
                if (is_array($package)
                    && is_string($package['name'] ?? null)
                    && is_string($package['version'] ?? null)) {
                    $versions[strtolower($package['name'])] = $package['version'];
                }
            }
        }

        $violations = [];
        $platform = is_array($manifest['config']['platform'] ?? null)
            ? $manifest['config']['platform']
            : [];
        $platformPhp = $platform['php'] ?? null;
        $rootPhp = $requirements['php'] ?? null;
        if (is_string($platformPhp)
            && is_string($rootPhp)
            && !Semver::satisfies($platformPhp, $rootPhp)) {
            $violations[] = sprintf(
                'Target platform PHP %s does not satisfy root constraint `%s`.',
                $platformPhp,
                $rootPhp
            );
        }

        foreach ($requirements as $package => $constraint) {
            if (!is_string($constraint)
                || !isset($versions[$package])
                || Semver::satisfies($versions[$package], $constraint)) {
                continue;
            }

            $violations[] = sprintf(
                'Candidate lock selects %s %s, which does not satisfy root constraint `%s`.',
                $package,
                $versions[$package],
                $constraint
            );
        }

        sort($violations);

        return $violations;
    }

    /**
     * @param list<string> $command
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private static function runComposer(array $command, ?string $workingDirectory = null): array
    {
        $process = new Process(
            $command,
            $workingDirectory,
            ['COMPOSER_NO_INTERACTION' => '1', 'COMPOSER_DISABLE_NETWORK' => '1'],
            null,
            300
        );
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
