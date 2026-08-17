<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

/**
 * Reads the framework-adapter manifest of one installed Composer package.
 *
 * The manifest lives in `extra.php-upgrade-preflight.framework-adapters` of the
 * package's `composer.json`. Reading fails closed: a package that advertises
 * adapters at all must advertise a non-empty list of trimmed class names.
 * Packages without a manifest file, without plugin metadata, or without an
 * adapter registration advertise nothing and are not an error.
 *
 * A rejection describes one package and never decides the fate of a run.
 * {@see FrameworkIntegrationRegistry} owns that policy: it skips the offending
 * package, keeps every other adapter, and reports what it left out.
 */
final class AdapterManifestReader
{
    private const COMPOSER_EXTRA_KEY = 'php-upgrade-preflight';
    private const ADAPTERS_KEY = 'framework-adapters';

    /**
     * Adapter class names advertised by one installed package, in manifest order.
     *
     * @return list<string>
     */
    public function advertisedClasses(string $packageName, string $installPath): array
    {
        $manifestPath = rtrim($installPath, '/\\') . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($manifestPath)) {
            return [];
        }

        $metadata = get_object_vars($this->readManifest($packageName, $manifestPath));
        if (!array_key_exists('extra', $metadata)) {
            return [];
        }

        if (!$metadata['extra'] instanceof \stdClass) {
            throw new \LogicException(sprintf(
                'Composer metadata extra for package "%s" must be an object.',
                $packageName
            ));
        }

        $composerExtra = get_object_vars($metadata['extra']);
        if (!array_key_exists(self::COMPOSER_EXTRA_KEY, $composerExtra)) {
            return [];
        }

        $extra = $composerExtra[self::COMPOSER_EXTRA_KEY];
        if (!$extra instanceof \stdClass) {
            throw new \LogicException(sprintf(
                'Composer metadata extra.%s for package "%s" must be an object.',
                self::COMPOSER_EXTRA_KEY,
                $packageName
            ));
        }
        $extra = get_object_vars($extra);
        if (!array_key_exists(self::ADAPTERS_KEY, $extra)) {
            return [];
        }

        return $this->adapterClasses($packageName, $extra[self::ADAPTERS_KEY]);
    }

    private function readManifest(string $packageName, string $manifestPath): \stdClass
    {
        $contents = @file_get_contents($manifestPath);
        if ($contents === false) {
            throw new \LogicException(sprintf('Could not read Composer metadata for installed package "%s".', $packageName));
        }

        try {
            $metadata = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \LogicException(sprintf(
                'Invalid Composer metadata for installed package "%s": %s',
                $packageName,
                $exception->getMessage()
            ), 0, $exception);
        }

        if (!$metadata instanceof \stdClass) {
            throw new \LogicException(sprintf('Composer metadata for installed package "%s" must be an object.', $packageName));
        }

        return $metadata;
    }

    /** @return list<string> */
    private function adapterClasses(string $packageName, mixed $registered): array
    {
        if (!is_array($registered) || $registered === [] || array_keys($registered) !== range(0, count($registered) - 1)) {
            throw new \LogicException(sprintf(
                'Composer metadata extra.%s.%s for package "%s" must be a non-empty list of class names.',
                self::COMPOSER_EXTRA_KEY,
                self::ADAPTERS_KEY,
                $packageName
            ));
        }

        $classes = [];
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

        return $classes;
    }
}
