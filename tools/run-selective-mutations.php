<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$configurationPath = $root . '/mutation.json';
$contents = file_get_contents($configurationPath);
if ($contents === false) {
    fwrite(STDERR, "Selective mutation configuration is missing.\n");
    exit(1);
}
$configuration = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($configuration) || ($configuration['schema_version'] ?? null) !== 1) {
    fwrite(STDERR, "Selective mutation configuration has an unsupported schema.\n");
    exit(1);
}
$mutations = $configuration['mutations'] ?? null;
if (!is_array($mutations)) {
    fwrite(STDERR, "Selective mutation configuration must define all six critical mutants.\n");
    exit(1);
}

$requiredMutations = [
    'scenario-selection-package-target-guard' => 'packages/core/src/Analysis/ScenarioSelector.php',
    'composer-blocker-platform-pattern' => 'packages/core/src/Analysis/ComposerBlockerParser.php',
    'schema-version-constant' => 'packages/core/resources/schema/upgrade-report-v0.7.schema.json',
    'risk-resolution-blocker-level' => 'packages/core/src/Analysis/RiskAndEffortEstimator.php',
    'laravel-transition-equal-major-guard' => 'packages/laravel/src/LaravelFrameworkIntegration.php',
    'release-series-lock-branch' => 'tools/ReleaseVerifier.php',
];
$configuredMutations = [];
foreach ($mutations as $mutation) {
    if (!is_array($mutation) || !is_string($mutation['name'] ?? null) || !is_string($mutation['file'] ?? null)) {
        fwrite(STDERR, "Selective mutation configuration contains an incomplete mutation identity.\n");
        exit(1);
    }
    if (array_key_exists($mutation['name'], $configuredMutations)) {
        fwrite(STDERR, sprintf("Selective mutation configuration repeats mutant %s.\n", $mutation['name']));
        exit(1);
    }
    $configuredMutations[$mutation['name']] = $mutation['file'];
}
ksort($requiredMutations);
ksort($configuredMutations);
if ($configuredMutations !== $requiredMutations) {
    fwrite(STDERR, "Selective mutation configuration must define each required critical mutant exactly once.\n");
    exit(1);
}

$lockPath = $root . '/build/selective-mutation.lock';
if (!is_dir(dirname($lockPath)) && !mkdir(dirname($lockPath), 0777, true) && !is_dir(dirname($lockPath))) {
    fwrite(STDERR, "Could not create the mutation lock directory.\n");
    exit(1);
}
$lock = fopen($lockPath, 'c+');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another selective mutation run is already active.\n");
    exit(1);
}

$restoreState = new class () {
    public ?string $path = null;
    public ?string $contents = null;
};
register_shutdown_function(static function () use ($restoreState): void {
    if ($restoreState->path !== null && $restoreState->contents !== null) {
        file_put_contents($restoreState->path, $restoreState->contents);
    }
});

$failures = [];
foreach ($mutations as $mutation) {
    $name = $mutation['name'] ?? null;
    $relativePath = $mutation['file'] ?? null;
    $search = $mutation['search'] ?? null;
    $replace = $mutation['replace'] ?? null;
    $testFilter = $mutation['test_filter'] ?? null;
    if (!is_string($name) || !is_string($relativePath) || !is_string($search)
        || !is_string($replace) || !is_string($testFilter)) {
        $failures[] = 'Configuration contains an incomplete mutation.';
        continue;
    }

    $path = $root . '/' . $relativePath;
    $original = file_get_contents($path);
    if ($original === false) {
        $failures[] = $name . ': source file is missing.';
        continue;
    }
    if (substr_count($original, $search) !== 1) {
        $failures[] = $name . ': mutation target must occur exactly once.';
        continue;
    }

    $mutated = str_replace($search, $replace, $original, $replacements);
    if ($replacements !== 1) {
        $failures[] = $name . ': mutation could not be applied.';
        continue;
    }

    $restoreState->path = $path;
    $restoreState->contents = $original;
    if (file_put_contents($path, $mutated) === false) {
        $failures[] = $name . ': mutated source could not be written.';
        $restoreState->path = null;
        $restoreState->contents = null;
        continue;
    }

    try {
        [$exitCode, $output] = runMutationTests($root, $testFilter);
    } finally {
        if (file_put_contents($path, $original) === false) {
            fwrite(STDERR, sprintf("Could not restore %s after mutation.\n", $relativePath));
            exit(1);
        }
        $restoreState->path = null;
        $restoreState->contents = null;
    }

    if ($exitCode === 0) {
        $failures[] = sprintf('%s survived focused test filter %s. Output: %s', $name, $testFilter, $output);
    } else {
        fwrite(STDOUT, sprintf("Killed mutation: %s\n", $name));
    }
}

flock($lock, LOCK_UN);
fclose($lock);
if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, sprintf("Selective mutation gate passed with %d killed mutants.\n", count($mutations)));

/** @return array{int, string} */
function runMutationTests(string $root, string $filter): array
{
    $command = [
        PHP_BINARY,
        $root . '/vendor/bin/phpunit',
        '--testsuite',
        'unit',
        '--filter',
        $filter,
        '--colors=never',
    ];
    $pipes = [];
    $process = proc_open($command, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the focused mutation test process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [$exitCode, trim((string) $stdout . "\n" . (string) $stderr)];
}
