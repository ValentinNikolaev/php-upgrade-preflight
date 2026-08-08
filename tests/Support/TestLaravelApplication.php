<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Support\ServiceProvider;

final class TestLaravelApplication extends Container implements ApplicationContract
{
    private string $basePath;
    /** @var list<ServiceProvider> */
    private array $providers = [];
    /** @var list<callable> */
    private array $bootingCallbacks = [];
    /** @var list<callable> */
    private array $bootedCallbacks = [];
    private bool $booted = false;
    private bool $bootstrapped = false;
    private string $locale = 'en';

    public function __construct(string $basePath)
    {
        $resolvedBasePath = realpath($basePath);
        if ($resolvedBasePath === false || !is_dir($resolvedBasePath)) {
            throw new \InvalidArgumentException(sprintf('Test application base path "%s" does not exist.', $basePath));
        }

        $this->basePath = $resolvedBasePath;
        $this->instance(ApplicationContract::class, $this);
        $this->instance(ContainerContract::class, $this);
        $this->instance('app', $this);
        self::setInstance($this);
    }

    public function version(): string
    {
        return '8.0-test';
    }

    /** @param string $path */
    public function basePath($path = ''): string
    {
        return $this->path($this->basePath, $path);
    }

    /** @param string $path */
    public function bootstrapPath($path = ''): string
    {
        return $this->path($this->basePath('bootstrap'), $path);
    }

    /** @param string $path */
    public function configPath($path = ''): string
    {
        return $this->path($this->basePath('config'), $path);
    }

    /** @param string $path */
    public function databasePath($path = ''): string
    {
        return $this->path($this->basePath('database'), $path);
    }

    /** @param string $path */
    public function resourcePath($path = ''): string
    {
        return $this->path($this->basePath('resources'), $path);
    }

    public function storagePath(): string
    {
        return $this->basePath('storage');
    }

    /** @param string|array<int, string> ...$environments @return string|bool */
    public function environment(...$environments)
    {
        if ($environments === []) {
            return 'testing';
        }

        foreach ($environments as $environment) {
            if (is_array($environment) ? in_array('testing', $environment, true) : $environment === 'testing') {
                return true;
            }
        }

        return false;
    }

    public function runningInConsole(): bool
    {
        return true;
    }

    public function runningUnitTests(): bool
    {
        return true;
    }

    public function isDownForMaintenance(): bool
    {
        return false;
    }

    public function registerConfiguredProviders(): void
    {
    }

    /** @param ServiceProvider|string $provider */
    public function register($provider, $force = false): ServiceProvider
    {
        unset($force);
        $instance = is_string($provider) ? $this->resolveProvider($provider) : $provider;
        $instance->register();
        $this->providers[] = $instance;

        return $instance;
    }

    /** @param string $provider @param string|null $service */
    public function registerDeferredProvider($provider, $service = null): void
    {
        unset($service);
        $this->register($provider);
    }

    /** @param class-string<ServiceProvider> $provider */
    public function resolveProvider($provider): ServiceProvider
    {
        return new $provider($this);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->bootingCallbacks as $callback) {
            $this->call($callback);
        }
        foreach ($this->providers as $provider) {
            $provider->callBootingCallbacks();
            $this->call([$provider, 'boot']);
            $provider->callBootedCallbacks();
        }
        $this->booted = true;
        foreach ($this->bootedCallbacks as $callback) {
            $this->call($callback);
        }
    }

    public function booting($callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    public function booted($callback): void
    {
        if ($this->booted) {
            $this->call($callback);

            return;
        }

        $this->bootedCallbacks[] = $callback;
    }

    public function bootstrapWith(array $bootstrappers): void
    {
        foreach ($bootstrappers as $bootstrapper) {
            $this->make($bootstrapper)->bootstrap($this);
        }
        $this->bootstrapped = true;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getNamespace(): string
    {
        return 'App\\';
    }

    /** @param ServiceProvider|string $provider @return list<ServiceProvider> */
    public function getProviders($provider): array
    {
        $class = is_string($provider) ? $provider : get_class($provider);

        return array_values(array_filter(
            $this->providers,
            static fn (ServiceProvider $registered): bool => $registered instanceof $class
        ));
    }

    public function hasBeenBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    public function loadDeferredProviders(): void
    {
    }

    public function setLocale($locale): void
    {
        $this->locale = (string) $locale;
    }

    public function shouldSkipMiddleware(): bool
    {
        return false;
    }

    public function terminate(): void
    {
    }

    private function path(string $basePath, string $path): string
    {
        return $path === '' ? $basePath : $basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
