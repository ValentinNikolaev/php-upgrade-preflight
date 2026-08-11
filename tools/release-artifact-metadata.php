<?php

declare(strict_types=1);

require __DIR__ . '/ReleaseArtifactMetadata.php';

use PhpUpgradePreflight\Tools\ReleaseArtifactMetadata;

$arguments = [];
foreach (array_slice($argv, 2) as $argument) {
    if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
        fwrite(STDERR, "Every option must use --name=value syntax.\n");
        exit(2);
    }
    [$name, $value] = explode('=', substr($argument, 2), 2);
    $arguments[$name] = $value;
}

$mode = $argv[1] ?? '';
$version = $arguments['version'] ?? '';
$dist = $arguments['dist'] ?? '';
$metadata = new ReleaseArtifactMetadata(dirname(__DIR__));

try {
    if ($mode === 'generate') {
        $metadata->generate($version, $dist, [
            'repository' => $arguments['repository'] ?? '',
            'commit' => $arguments['commit'] ?? '',
            'ref' => $arguments['ref'] ?? '',
            'workflow' => $arguments['workflow'] ?? '',
            'run_uri' => $arguments['run-uri'] ?? '',
        ]);
        fwrite(STDOUT, "Generated dependency inventory, archive provenance, and checksums.\n");
        exit(0);
    }

    if ($mode === 'verify') {
        $sourceOptions = ['repository', 'commit', 'ref', 'workflow', 'run-uri'];
        $providedSourceOptions = array_intersect($sourceOptions, array_keys($arguments));
        $expectedSource = null;
        if ($providedSourceOptions !== []) {
            if (count($providedSourceOptions) !== count($sourceOptions)) {
                throw new InvalidArgumentException('Verification provenance options must be provided together.');
            }
            $expectedSource = [
                'repository' => $arguments['repository'],
                'commit' => $arguments['commit'],
                'ref' => $arguments['ref'],
                'workflow' => $arguments['workflow'],
                'run_uri' => $arguments['run-uri'],
            ];
        }
        $errors = $metadata->verify($version, $dist, $expectedSource);
        if ($errors !== []) {
            foreach ($errors as $error) {
                fwrite(STDERR, 'ERROR: ' . $error . "\n");
            }
            exit(1);
        }
        fwrite(STDOUT, "Verified release archive metadata.\n");
        exit(0);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}

fwrite(STDERR, "Usage: php tools/release-artifact-metadata.php generate|verify --version=X.Y.Z --dist=PATH [provenance options]\n");
exit(2);
