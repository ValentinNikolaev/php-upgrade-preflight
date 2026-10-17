<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Source;

use Composer\InstalledVersions;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/** Fingerprints the installed parser implementation, including its resolver/lexer. */
final class SourceFactsIdentity
{
    /** @var array<string, string> */
    private static array $defaultIdentities = [];

    public static function parser(Parser $parser, bool $defaultParser): ?string
    {
        try {
            $coreFiles = [
                __FILE__, __DIR__ . '/SourceUsageScanner.php', __DIR__ . '/SourceUsageVisitor.php', __DIR__ . '/ExplicitFullyQualifiedNameVisitor.php',
            ];
            $core = '';
            foreach ($coreFiles as $coreFile) {
                $digest = @hash_file('sha256', $coreFile);
                if ($digest === false) {
                    return null;
                }
                $core .= $digest;
            }
            if (!$defaultParser) {
                $key = $parser instanceof SourceUsageCacheKeyProvider ? $parser->sourceUsageCacheKey() : null;

                return $key === null || $key === '' ? null : get_class($parser) . ':' . $key . ':' . PHP_VERSION_ID . ':' . $core;
            }
            if (isset(self::$defaultIdentities[get_class($parser)])) {
                return self::$defaultIdentities[get_class($parser)] . ':' . $core;
            }
            $file = (new \ReflectionClass(ParserFactory::class))->getFileName();
            if ($file === false) {
                return null;
            }
            $files = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname($file), \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $entry) {
                if ($entry->isFile() && $entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
            sort($files, SORT_STRING);
            $context = hash_init('sha256');
            foreach ($files as $path) {
                $digest = @hash_file('sha256', $path);
                if ($digest === false) {
                    return null;
                }
                hash_update($context, basename($path) . ':' . $digest . "\n");
            }

            self::$defaultIdentities[get_class($parser)] = implode(':', [
                get_class($parser),
                (string) PHP_VERSION_ID,
                InstalledVersions::getVersion('nikic/php-parser') ?? '',
                InstalledVersions::getReference('nikic/php-parser') ?? '',
                hash_final($context),
            ]);

            return self::$defaultIdentities[get_class($parser)] . ':' . $core;
        } catch (\Throwable) {
            return null;
        }
    }
}
