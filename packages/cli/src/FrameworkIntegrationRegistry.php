<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use Composer\InstalledVersions;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;

final class FrameworkIntegrationRegistry
{
    private const COMPOSER_EXTRA_KEY = 'php-upgrade-preflight';
    private const ADAPTERS_KEY = 'framework-adapters';

    /** @var list<string>|null */
    private ?array $integrationClasses;
    /** @var array<string, string>|null */
    private ?array $packageInstallPaths;
    /** @var string */
    private string $installedVersionsClass;
    /** @var list<FrameworkIntegration>|null */
    private ?array $installed = null;

    /**
     * Passing integration classes directly is retained for embedding and tests. Missing
     * directly supplied classes are treated as unavailable optional integrations.
     *
     * @param list<string>|null          $integrationClasses
     * @param array<string, string>|null $packageInstallPaths Composer package name to install path
     * @param string|null                $installedVersionsClass
     */
    public function __construct(
        ?array $integrationClasses = null,
        ?array $packageInstallPaths = null,
        ?string $installedVersionsClass = null
    ) {
        $this->integrationClasses = $integrationClasses;
        $this->packageInstallPaths = $packageInstallPaths;
        $this->installedVersionsClass = $installedVersionsClass ?? InstalledVersions::class;
    }

    /** @return list<FrameworkIntegration> */
    public function installed(): array
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        $classes = $this->integrationClasses;
        $ignoreMissingClasses = $classes !== null;
        if ($classes === null) {
            $classes = $this->discoverIntegrationClasses();
        }

        $integrations = [];
        $registeredClasses = [];
        $registeredNames = [];

        foreach ($classes as $class) {
            $normalizedClass = strtolower(ltrim($class, '\\'));
            if (isset($registeredClasses[$normalizedClass])) {
                throw new \LogicException(sprintf('Framework integration class "%s" is registered more than once.', $class));
            }
            $registeredClasses[$normalizedClass] = true;

            if (!class_exists($class)) {
                if ($ignoreMissingClasses) {
                    continue;
                }

                throw new \LogicException(sprintf('Registered framework integration class "%s" could not be loaded.', $class));
            }

            try {
                $reflection = new \ReflectionClass($class);
                if (!$reflection->isInstantiable()) {
                    throw new \LogicException(sprintf('Registered framework integration class "%s" is not instantiable.', $class));
                }

                $constructor = $reflection->getConstructor();
                if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
                    throw new \LogicException(sprintf(
                        'Registered framework integration class "%s" must have a constructor with no required parameters.',
                        $class
                    ));
                }

                $integration = $reflection->newInstance();
            } catch (\LogicException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new \LogicException(sprintf(
                    'Registered framework integration class "%s" could not be instantiated: %s',
                    $class,
                    $exception->getMessage()
                ), 0, $exception);
            }

            if (!$integration instanceof FrameworkIntegration) {
                throw new \LogicException(sprintf('Framework integration "%s" must implement %s.', $class, FrameworkIntegration::class));
            }

            $name = $integration->name();
            if (trim($name) === '' || trim($name) !== $name) {
                throw new \LogicException(sprintf(
                    'Framework integration "%s" must provide a non-empty name without surrounding whitespace.',
                    $class
                ));
            }

            $normalizedName = strtolower($name);
            if (isset($registeredNames[$normalizedName])) {
                throw new \LogicException(sprintf(
                    'Framework integration name "%s" is provided by both "%s" and "%s".',
                    $name,
                    $registeredNames[$normalizedName],
                    $class
                ));
            }
            $registeredNames[$normalizedName] = $class;
            $integrations[] = $integration;
        }

        usort($integrations, static function (FrameworkIntegration $left, FrameworkIntegration $right): int {
            $nameOrder = strcmp(strtolower($left->name()), strtolower($right->name()));

            return $nameOrder !== 0 ? $nameOrder : strcmp(get_class($left), get_class($right));
        });

        return $this->installed = $integrations;
    }

    /** @param list<string> $requested */
    public function assertAvailable(array $requested): void
    {
        if ($requested === []) {
            return;
        }

        $available = array_map(
            static fn (FrameworkIntegration $integration): string => strtolower($integration->name()),
            $this->installed()
        );
        $unavailable = array_values(array_diff(array_map('strtolower', $requested), $available));

        if ($unavailable !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Requested framework integration%s unavailable: %s. Install the matching adapter package or remove the --framework option.',
                count($unavailable) === 1 ? ' is' : 's are',
                implode(', ', $unavailable)
            ));
        }
    }

    /** @return list<string> */
    private function discoverIntegrationClasses(): array
    {
        $packageInstallPaths = $this->packageInstallPaths ?? $this->composerPackageInstallPaths();
        ksort($packageInstallPaths, SORT_STRING);

        $classes = [];
        foreach ($packageInstallPaths as $packageName => $installPath) {
            $composerPath = rtrim($installPath, '/\\') . DIRECTORY_SEPARATOR . 'composer.json';
            if (!is_file($composerPath)) {
                continue;
            }

            $contents = @file_get_contents($composerPath);
            if ($contents === false) {
                throw new \LogicException(sprintf('Could not read Composer metadata for installed package "%s".', $packageName));
            }

            try {
                $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \LogicException(sprintf(
                    'Invalid Composer metadata for installed package "%s": %s',
                    $packageName,
                    $exception->getMessage()
                ), 0, $exception);
            }

            if (!is_array($metadata)) {
                throw new \LogicException(sprintf('Composer metadata for installed package "%s" must be an object.', $packageName));
            }

            $composerExtra = $metadata['extra'] ?? [];
            if (!is_array($composerExtra) || !array_key_exists(self::COMPOSER_EXTRA_KEY, $composerExtra)) {
                continue;
            }

            $extra = $composerExtra[self::COMPOSER_EXTRA_KEY];
            if (!is_array($extra)) {
                throw new \LogicException(sprintf(
                    'Composer metadata extra.%s for package "%s" must be an object.',
                    self::COMPOSER_EXTRA_KEY,
                    $packageName
                ));
            }
            if (!array_key_exists(self::ADAPTERS_KEY, $extra)) {
                continue;
            }

            $registered = $extra[self::ADAPTERS_KEY];
            if (!is_array($registered) || $registered === [] || array_keys($registered) !== range(0, count($registered) - 1)) {
                throw new \LogicException(sprintf(
                    'Composer metadata extra.%s.%s for package "%s" must be a non-empty list of class names.',
                    self::COMPOSER_EXTRA_KEY,
                    self::ADAPTERS_KEY,
                    $packageName
                ));
            }

            foreach ($registered as $class) {
                if (!is_string($class) || trim($class) === '' || trim($class) !== $class) {
                    throw new \LogicException(sprintf(
                        'Composer metadata extra.%s.%s for package "%s" must contain only non-empty class names without surrounding whitespace.',
                        self::COMPOSER_EXTRA_KEY,
                        self::ADAPTERS_KEY,
                        $packageName
                    ));
                }

                $classes[] = $class;
            }
        }

        return $classes;
    }

    /** @return array<string, string> */
    private function composerPackageInstallPaths(): array
    {
        $installedVersionsClass = $this->installedVersionsClass;
        if (!class_exists($installedVersionsClass)) {
            return [];
        }

        $paths = [];
        if (method_exists($installedVersionsClass, 'getAllRawData')) {
            $allInstalledData = $installedVersionsClass::getAllRawData();
        } elseif (method_exists($installedVersionsClass, 'getRawData')) {
            $allInstalledData = [$installedVersionsClass::getRawData()];
        } else {
            return [];
        }
        if (!is_array($allInstalledData)) {
            return [];
        }

        foreach ($allInstalledData as $installedData) {
            if (!is_array($installedData)) {
                continue;
            }

            $packages = $installedData['versions'] ?? [];
            if (!is_array($packages)) {
                $packages = [];
            }
            if (isset($installedData['root']['name'], $installedData['root']['install_path'])) {
                $packages[$installedData['root']['name']] = $installedData['root'];
            }

            foreach ($packages as $packageName => $package) {
                if (!is_string($packageName) || !is_array($package) || !isset($package['install_path']) || !is_string($package['install_path'])) {
                    continue;
                }

                $installPath = realpath($package['install_path']) ?: $package['install_path'];
                if (isset($paths[$packageName]) && $paths[$packageName] !== $installPath) {
                    throw new \LogicException(sprintf(
                        'Composer package "%s" is registered with conflicting install paths "%s" and "%s".',
                        $packageName,
                        $paths[$packageName],
                        $installPath
                    ));
                }

                $paths[$packageName] = $installPath;
            }
        }

        return $paths;
    }
}
