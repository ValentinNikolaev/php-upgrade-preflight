<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use Composer\InstalledVersions;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;

/**
 * Discovers installed framework adapters and answers availability lookups.
 *
 * Manifest reading and validation belong to {@see AdapterManifestReader}; this
 * registry owns install-path enumeration, discovery ordering, adapter
 * instantiation, duplicate rejection, and the `--framework` lookup.
 *
 * Discovery is per package: a rejected manifest skips only its own package and is
 * reported through {@see discoveryDiagnostics()}, so one broken dependency cannot
 * stop an otherwise valid analysis. Registration of an advertised class stays
 * fail-fast, and an explicitly requested framework that discovery lost is still an
 * error naming the packages it skipped.
 */
final class FrameworkIntegrationRegistry
{
    /** @var list<string>|null */
    private ?array $integrationClasses;
    /** @var array<string, string>|null */
    private ?array $packageInstallPaths;
    /** @var string */
    private string $installedVersionsClass;
    private AdapterManifestReader $manifests;
    /** @var list<FrameworkIntegration>|null */
    private ?array $installed = null;
    /**
     * Installed packages whose adapter manifest could not be read, keyed by package
     * name in discovery order, with the reader's rejection as the value.
     *
     * @var array<string, string>
     */
    private array $skippedPackages = [];

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
        ?string $installedVersionsClass = null,
        ?AdapterManifestReader $manifests = null
    ) {
        $this->integrationClasses = $integrationClasses;
        $this->packageInstallPaths = $packageInstallPaths;
        $this->installedVersionsClass = $installedVersionsClass ?? InstalledVersions::class;
        $this->manifests = $manifests ?? new AdapterManifestReader();
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

            $integration = $this->instantiateIntegration($class);
            $name = $this->integrationName($class, $integration);

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

    /**
     * Installed packages that advertise adapters but were skipped as unreadable.
     *
     * Discovery degrades instead of failing, so the CLI has to report what it left
     * out. The list is empty for a healthy installation and for directly supplied
     * integration classes, which bypass Composer metadata entirely.
     *
     * @return list<string>
     */
    public function discoveryDiagnostics(): array
    {
        $this->installed();

        $diagnostics = [];
        foreach ($this->skippedPackages as $packageName => $reason) {
            $diagnostics[] = sprintf(
                'Skipped framework adapter discovery for installed package "%s": %s',
                $packageName,
                $reason
            );
        }

        return $diagnostics;
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
                'Requested framework integration%s unavailable: %s. Install the matching adapter package or remove the --framework option.%s',
                count($unavailable) === 1 ? ' is' : 's are',
                implode(', ', $unavailable),
                $this->skippedPackageHint()
            ));
        }
    }

    /**
     * Explains an unavailable explicit selection that a skipped package may have caused.
     *
     * A skipped manifest never names its adapters, so the registry cannot prove which
     * package owned a requested framework. Naming every skipped package keeps the
     * explicit path a hard, actionable error instead of a bare "not installed".
     */
    private function skippedPackageHint(): string
    {
        if ($this->skippedPackages === []) {
            return '';
        }

        $details = [];
        foreach ($this->skippedPackages as $packageName => $reason) {
            $details[] = sprintf('"%s" (%s)', $packageName, $reason);
        }

        return sprintf(
            ' Adapter discovery also skipped %s with an unreadable adapter manifest: %s',
            count($details) === 1 ? 'one installed package' : 'installed packages',
            implode('; ', $details)
        );
    }

    /** @param class-string $class */
    private function instantiateIntegration(string $class): FrameworkIntegration
    {
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

        return $integration;
    }

    private function integrationName(string $class, FrameworkIntegration $integration): string
    {
        $name = $integration->name();
        if (trim($name) === '' || trim($name) !== $name) {
            throw new \LogicException(sprintf(
                'Framework integration "%s" must provide a non-empty name without surrounding whitespace.',
                $class
            ));
        }

        return $name;
    }

    /** @return list<string> */
    private function discoverIntegrationClasses(): array
    {
        $packageInstallPaths = $this->packageInstallPaths ?? $this->composerPackageInstallPaths();
        ksort($packageInstallPaths, SORT_STRING);

        $classes = [];
        $this->skippedPackages = [];
        foreach ($packageInstallPaths as $packageName => $installPath) {
            $packageName = (string) $packageName;

            try {
                $classes = array_merge($classes, $this->manifests->advertisedClasses($packageName, $installPath));
            } catch (\Throwable $exception) {
                // Discovery reads every installed package, so an unrelated dependency with a
                // broken adapter manifest must not end analysis of a project the user does
                // control. That package is skipped and named in a diagnostic; an explicitly
                // requested adapter that went missing this way still fails in assertAvailable().
                $this->skippedPackages[$packageName] = $exception->getMessage();
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
