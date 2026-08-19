<?php

declare(strict_types=1);

const RELEASE_WIKI_SETS = [
    'cli' => ['repository' => 'ValentinNikolaev/php-upgrade-preflight-cli', 'purpose' => 'Standalone CLI package documentation'],
    'common' => ['repository' => 'ValentinNikolaev/php-upgrade-preflight', 'purpose' => 'Product-wide and monorepo documentation'],
    'core' => ['repository' => 'ValentinNikolaev/php-upgrade-preflight-core', 'purpose' => 'Framework-neutral Core package documentation'],
    'laravel' => ['repository' => 'ValentinNikolaev/php-upgrade-preflight-laravel', 'purpose' => 'Laravel adapter and Artisan documentation'],
];

/** @return never */
function releaseWikiUsage(): void
{
    fwrite(STDERR, "Usage:\n  php tools/materialize-release-wikis.php [--check]\n  php tools/materialize-release-wikis.php --check-published SET WIKI_CHECKOUT\n");
    exit(2);
}

/** @param list<string> $arguments */
function releaseWikiMain(array $arguments, string $root): int
{
    if ($arguments === []) {
        return materializeReleaseWikis($root, false);
    }
    if ($arguments === ['--check']) {
        return materializeReleaseWikis($root, true);
    }
    if (count($arguments) === 3 && $arguments[0] === '--check-published') {
        return checkPublishedReleaseWiki($root, $arguments[1], $arguments[2]);
    }
    releaseWikiUsage();
}

function materializeReleaseWikis(string $root, bool $check): int
{
    $errors = [];
    $plans = buildReleaseWikiPlans($root, $errors);
    if ($errors !== []) {
        return reportReleaseWikiErrors($errors);
    }

    if ($check) {
        foreach ($plans as $set => $plan) {
            checkReleaseWikiPlan($set, $plan, $errors);
            fwrite(STDOUT, sprintf("Checked %s: %d page(s) plus navigation and checksums.\n", $set, $plan['page_count']));
        }
        return $errors === [] ? 0 : reportReleaseWikiErrors($errors);
    }

    // No output is touched until all four sets pass manifest, source, link, and
    // existing-inventory validation.
    foreach ($plans as $set => $plan) {
        try {
            replaceReleaseWikiPlan($plan);
            fwrite(STDOUT, sprintf("Materialized %s: %d page(s) plus navigation and checksums.\n", $set, $plan['page_count']));
        } catch (Throwable $exception) {
            $errors[] = $set . ': ' . $exception->getMessage();
            break;
        }
    }
    return $errors === [] ? 0 : reportReleaseWikiErrors($errors);
}

/**
 * @param list<string> $errors
 * @return array<string, array{set_directory: string, pages_directory: string, expected: array<string, string>, page_count: int}>
 */
function buildReleaseWikiPlans(string $root, array &$errors): array
{
    $rootPath = realpath($root);
    $wikiPath = realpath($root . '/wiki');
    $releaseRootPath = realpath($root . '/release-wikis');
    if ($rootPath === false || $wikiPath === false || $releaseRootPath === false
        || !is_dir($wikiPath) || !is_dir($releaseRootPath)) {
        $errors[] = 'repository root must contain real wiki/ and release-wikis/ directories';
        return [];
    }
    if (!pathIsWithin($wikiPath, $rootPath) || !pathIsWithin($releaseRootPath, $rootPath)) {
        $errors[] = 'wiki source or release root escapes the repository root';
        return [];
    }

    $actualSets = [];
    foreach (scandir($releaseRootPath) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (is_dir($releaseRootPath . DIRECTORY_SEPARATOR . $name)) {
            $actualSets[] = $name;
        }
    }
    sort($actualSets, SORT_STRING);
    $expectedSets = array_keys(RELEASE_WIKI_SETS);
    sort($expectedSets, SORT_STRING);
    foreach (array_diff($expectedSets, $actualSets) as $missing) {
        $errors[] = 'missing required release Wiki set: ' . $missing;
    }
    foreach (array_diff($actualSets, $expectedSets) as $unknown) {
        $errors[] = 'unknown release Wiki set directory: ' . $unknown;
    }
    if ($errors !== []) {
        return [];
    }

    $plans = [];
    foreach (RELEASE_WIKI_SETS as $set => $contract) {
        try {
            $setDirectory = $releaseRootPath . DIRECTORY_SEPARATOR . $set;
            if (is_link($setDirectory)) {
                throw new RuntimeException('set directory must not be a symlink');
            }
            $setPath = realpath($setDirectory);
            if ($setPath === false || !pathIsWithin($setPath, $releaseRootPath)) {
                throw new RuntimeException('set directory escapes release-wikis');
            }
            $manifestPath = $setPath . DIRECTORY_SEPARATOR . 'wiki-manifest.json';
            if (!is_file($manifestPath) || is_link($manifestPath)) {
                throw new RuntimeException('missing regular wiki-manifest.json');
            }
            $manifestRealPath = realpath($manifestPath);
            if ($manifestRealPath === false || !pathIsWithin($manifestRealPath, $setPath)) {
                throw new RuntimeException('manifest escapes its release Wiki set');
            }
            $manifest = json_decode((string) file_get_contents($manifestRealPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['schema_version'] ?? null) !== 1) {
                throw new RuntimeException('unsupported manifest schema');
            }
            if (($manifest['destination_repository'] ?? null) !== $contract['repository']) {
                throw new RuntimeException('destination_repository does not match the allowlist');
            }
            if (($manifest['purpose'] ?? null) !== $contract['purpose']) {
                throw new RuntimeException('purpose does not match the allowlist');
            }
            $pages = $manifest['pages'] ?? null;
            $sidebar = $manifest['sidebar'] ?? null;
            if (!is_array($pages) || !is_array($sidebar) || !is_string($manifest['footer'] ?? null)) {
                throw new RuntimeException('invalid manifest structure');
            }

            $destinationSlugs = [];
            $destinationNames = [];
            $sourceAliases = [];
            $sourcePaths = [];
            foreach ($pages as $page) {
                if (!is_array($page) || !is_string($page['source'] ?? null) || !is_string($page['destination'] ?? null)) {
                    throw new RuntimeException('invalid page mapping');
                }
                $destination = $page['destination'];
                $foldedDestination = strtolower($destination);
                if (preg_match('/^[A-Za-z0-9_][A-Za-z0-9._-]*\.md$/D', $destination) !== 1
                    || in_array($foldedDestination, ['_sidebar.md', '_footer.md', '.source-checksums.json'], true)) {
                    throw new RuntimeException('invalid or reserved destination: ' . $destination);
                }
                if (isset($destinationNames[$foldedDestination])) {
                    throw new RuntimeException('case-insensitive duplicate destination: ' . $destination);
                }
                $destinationNames[$foldedDestination] = true;
                $slug = substr($destination, 0, -3);
                $destinationSlugs[$slug] = true;

                $source = $page['source'];
                if (str_contains($source, '\\') || !str_starts_with($source, 'wiki/') || !str_ends_with(strtolower($source), '.md')) {
                    throw new RuntimeException('source must be a forward-slash .md path under wiki/: ' . $source);
                }
                $sourcePath = realpath($rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source));
                if ($sourcePath === false || !is_file($sourcePath) || !pathIsWithin($sourcePath, $wikiPath)) {
                    throw new RuntimeException('source is missing, not regular, or escapes wiki/: ' . $source);
                }
                $sourcePaths[$source] = $sourcePath;
                $alias = pathinfo($source, PATHINFO_FILENAME);
                if (isset($sourceAliases[$alias]) && $sourceAliases[$alias] !== $slug) {
                    throw new RuntimeException('duplicate source alias: ' . $alias);
                }
                $sourceAliases[$alias] = $slug;
            }
            $sidebarItems = [];
            foreach ($sidebar as $slug) {
                if (!is_string($slug) || !isset($destinationSlugs[$slug])) {
                    throw new RuntimeException('sidebar references an unknown destination');
                }
                $sidebarItems[] = $slug;
            }

            $expected = [];
            $records = [];
            foreach ($pages as $page) {
                $source = file_get_contents($sourcePaths[$page['source']]);
                if ($source === false) {
                    throw new RuntimeException('unreadable source: ' . $page['source']);
                }
                $materialized = materializeLinks($source, $sourceAliases, $destinationSlugs);
                $expected[$page['destination']] = $materialized;
                $records[] = [
                    'source' => $page['source'],
                    'destination' => $page['destination'],
                    'source_sha256' => hash('sha256', $source),
                    'materialized_sha256' => hash('sha256', $materialized),
                ];
            }
            $expected['_Sidebar.md'] = sidebar($contract['purpose'], $sidebarItems);
            $expected['_Footer.md'] = $manifest['footer'] . " · [Common repository](https://github.com/ValentinNikolaev/php-upgrade-preflight)\n";
            ksort($expected, SORT_STRING);
            $setErrors = [];
            validateLinks($expected, $destinationSlugs, $setErrors, $set);
            if ($setErrors !== []) {
                throw new RuntimeException(implode('; ', $setErrors));
            }

            $expected['.source-checksums.json'] = json_encode([
                'schema_version' => 1,
                'set' => $set,
                'manifest_sha256' => hash_file('sha256', $manifestRealPath),
                'pages' => $records,
                'generated' => [
                    '_Sidebar.md' => hash('sha256', $expected['_Sidebar.md']),
                    '_Footer.md' => hash('sha256', $expected['_Footer.md']),
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

            $pagesDirectory = $setPath . DIRECTORY_SEPARATOR . 'pages';
            validatePagesDirectory($pagesDirectory, $setPath, array_keys($expected));
            $plans[$set] = [
                'set_directory' => $setPath,
                'pages_directory' => $pagesDirectory,
                'expected' => $expected,
                'page_count' => count($pages),
            ];
        } catch (Throwable $exception) {
            $errors[] = $set . ': ' . $exception->getMessage();
        }
    }
    return $plans;
}

/** @param list<string> $expectedNames */
function validatePagesDirectory(string $pagesDirectory, string $setPath, array $expectedNames): void
{
    if (!file_exists($pagesDirectory) && !is_link($pagesDirectory)) {
        return;
    }
    if (is_link($pagesDirectory) || !is_dir($pagesDirectory)) {
        throw new RuntimeException('pages path must be a real directory, not a symlink');
    }
    $pagesPath = realpath($pagesDirectory);
    if ($pagesPath === false || !pathIsWithin($pagesPath, $setPath)) {
        throw new RuntimeException('pages directory escapes its release Wiki set');
    }
    $actualNames = array_values(array_diff(scandir($pagesPath) ?: [], ['.', '..']));
    $unexpected = array_values(array_diff($actualNames, $expectedNames));
    if ($unexpected !== []) {
        throw new RuntimeException('unlisted materialized file(s): ' . implode(', ', $unexpected));
    }
    foreach ($actualNames as $name) {
        $path = $pagesPath . DIRECTORY_SEPARATOR . $name;
        if (is_link($path) || !is_file($path)) {
            throw new RuntimeException('materialized path must be a regular file: pages/' . $name);
        }
    }
}

/**
 * @param array{pages_directory: string, expected: array<string, string>} $plan
 * @param list<string>                                                   $errors
 */
function checkReleaseWikiPlan(string $set, array $plan, array &$errors): void
{
    foreach ($plan['expected'] as $name => $contents) {
        if (@file_get_contents($plan['pages_directory'] . DIRECTORY_SEPARATOR . $name) !== $contents) {
            $errors[] = $set . ': drift at pages/' . $name;
        }
    }
}

/** @param array{set_directory: string, pages_directory: string, expected: array<string, string>} $plan */
function replaceReleaseWikiPlan(array $plan): void
{
    $suffix = getmypid() . '-' . bin2hex(random_bytes(6));
    $stage = $plan['set_directory'] . DIRECTORY_SEPARATOR . '.pages-stage-' . $suffix;
    $backup = $plan['set_directory'] . DIRECTORY_SEPARATOR . '.pages-backup-' . $suffix;
    if (!mkdir($stage, 0777) && !is_dir($stage)) {
        throw new RuntimeException('could not create staging directory');
    }
    try {
        foreach ($plan['expected'] as $name => $contents) {
            if (file_put_contents($stage . DIRECTORY_SEPARATOR . $name, $contents, LOCK_EX) === false) {
                throw new RuntimeException('could not stage pages/' . $name);
            }
        }
        $hadPages = is_dir($plan['pages_directory']);
        if ($hadPages && !rename($plan['pages_directory'], $backup)) {
            throw new RuntimeException('could not stage existing pages directory');
        }
        if (!rename($stage, $plan['pages_directory'])) {
            if ($hadPages) {
                @rename($backup, $plan['pages_directory']);
            }
            throw new RuntimeException('could not atomically install pages directory');
        }
        if ($hadPages) {
            removeKnownReleaseWikiDirectory($backup, array_keys($plan['expected']));
        }
    } finally {
        if (is_dir($stage)) {
            removeKnownReleaseWikiDirectory($stage, array_keys($plan['expected']));
        }
    }
}

/** @param list<string> $knownNames */
function removeKnownReleaseWikiDirectory(string $directory, array $knownNames): void
{
    if (is_link($directory) || !is_dir($directory)) {
        throw new RuntimeException('refusing to clean a non-directory staging path');
    }
    $actual = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    if (array_diff($actual, $knownNames) !== []) {
        throw new RuntimeException('refusing to clean staging directory with unknown files');
    }
    foreach ($actual as $name) {
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_link($path) || !is_file($path) || !unlink($path)) {
            throw new RuntimeException('could not remove staged file ' . $name);
        }
    }
    if (!rmdir($directory)) {
        throw new RuntimeException('could not remove staging directory');
    }
}

function checkPublishedReleaseWiki(string $root, string $set, string $checkout): int
{
    $errors = [];
    $plans = buildReleaseWikiPlans($root, $errors);
    if ($errors !== []) {
        return reportReleaseWikiErrors($errors);
    }
    if (!isset($plans[$set])) {
        return reportReleaseWikiErrors(['unknown release Wiki set: ' . $set]);
    }
    $checkoutPath = realpath($checkout);
    if ($checkoutPath === false || !is_dir($checkoutPath) || is_link($checkout)) {
        return reportReleaseWikiErrors(['Wiki checkout must be a real directory: ' . $checkout]);
    }
    $expected = array_filter($plans[$set]['expected'], static fn (string $name): bool => str_ends_with($name, '.md'), ARRAY_FILTER_USE_KEY);
    $remoteNames = [];
    foreach (scandir($checkoutPath) ?: [] as $name) {
        if ($name !== '.' && $name !== '..' && str_ends_with(strtolower($name), '.md')) {
            $remoteNames[] = $name;
        }
    }
    $expectedNames = array_keys($expected);
    foreach (array_diff($remoteNames, $expectedNames) as $surplus) {
        $errors[] = $set . ': surplus remote page ' . $surplus . ' (review, then git rm explicitly)';
    }
    foreach (array_diff($expectedNames, $remoteNames) as $missing) {
        $errors[] = $set . ': missing remote page ' . $missing;
    }
    foreach (array_intersect($expectedNames, $remoteNames) as $name) {
        $path = $checkoutPath . DIRECTORY_SEPARATOR . $name;
        if (is_link($path) || !is_file($path)) {
            $errors[] = $set . ': remote page is not a regular file ' . $name;
        } elseif (file_get_contents($path) !== $expected[$name]) {
            $errors[] = $set . ': remote page differs ' . $name;
        }
    }
    if ($errors !== []) {
        return reportReleaseWikiErrors($errors);
    }
    fwrite(STDOUT, sprintf("Published Wiki checkout matches %s exactly: %d Markdown file(s).\n", $set, count($expected)));
    return 0;
}

function pathIsWithin(string $path, string $parent): bool
{
    $normalize = static function (string $value): string {
        $value = rtrim(str_replace('\\', '/', $value), '/');
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($value) : $value;
    };
    $path = $normalize($path);
    $parent = $normalize($parent);
    return $path !== $parent && str_starts_with($path, $parent . '/');
}

/** @param list<string> $errors */
function reportReleaseWikiErrors(array $errors): int
{
    foreach ($errors as $error) {
        fwrite(STDERR, 'ERROR: ' . $error . "\n");
    }
    return 1;
}

/**
 * @param array<string, string> $aliases
 * @param array<string, bool>   $destinations
 */
function materializeLinks(string $source, array $aliases, array $destinations): string
{
    $repository = 'https://github.com/ValentinNikolaev/php-upgrade-preflight/blob/main/';
    $commonWiki = 'https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/';
    $source = (string) preg_replace_callback('/\]\(\.\.\/([^)]+)\)/', static fn (array $match): string => '](' . $repository . $match[1] . ')', $source);
    return (string) preg_replace_callback(
        '/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/',
        static function (array $match) use ($aliases, $destinations, $commonWiki): string {
            $label = $match[1];
            $requested = $match[2] ?? $label;
            $target = $aliases[$requested] ?? $requested;
            return isset($destinations[$target])
                ? '[[' . $label . '|' . $target . ']]'
                : '[' . $label . '](' . $commonWiki . rawurlencode($requested) . ')';
        },
        $source
    );
}

/** @param list<string> $items */
function sidebar(string $purpose, array $items): string
{
    $lines = ['# ' . $purpose, ''];
    foreach ($items as $item) {
        $lines[] = '- [[' . str_replace('-', ' ', $item) . '|' . $item . ']]';
    }
    return implode("\n", $lines) . "\n";
}

/**
 * @param array<string, string> $files
 * @param array<string, bool>   $destinations
 * @param list<string>          $errors
 */
function validateLinks(array $files, array $destinations, array &$errors, string $set): void
{
    foreach ($files as $name => $contents) {
        preg_match_all('/\[\[[^\]|]+\|([^\]]+)\]\]/', $contents, $wikiLinks);
        foreach ($wikiLinks[1] as $target) {
            if (!isset($destinations[$target])) {
                $errors[] = sprintf('%s: %s has unresolved local Wiki link %s', $set, $name, $target);
            }
        }
        preg_match_all('/\]\(([^)]+)\)/', $contents, $markdownLinks);
        foreach ($markdownLinks[1] as $target) {
            if (preg_match('~^(?:https?://|mailto:|\#)~', $target) === 1) {
                continue;
            }
            $slug = preg_replace('/\.md(?:#.*)?$/', '', $target);
            if (!is_string($slug) || !isset($destinations[$slug])) {
                $errors[] = sprintf('%s: %s has unresolved relative link %s', $set, $name, $target);
            }
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(releaseWikiMain(array_slice($argv, 1), dirname(__DIR__)));
}
