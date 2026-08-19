<?php

declare(strict_types=1);

/*
 * Compares a generated five-minute-demo report against the checked-in canonical
 * report and prints the demo summary shown in the recorded GIF.
 *
 * The projection below covers the stable evidence only: schema, resolution
 * statuses, stage state chains, stage source-impact references, and blocker
 * lifecycles. Run-local values such as durations and raw candidate-lock hashes
 * are deliberately excluded. Any drift, including an unresolvable source-impact
 * reference, throws and exits non-zero so the demo cannot present stale or
 * divergent evidence.
 */

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php summarize-report.php <generated-report.json> <canonical-report.json>\n");
    exit(2);
}

/** @var array<string, mixed> $report */
$report = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
/** @var array<string, mixed> $canonical */
$canonical = json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);

$projection = static function (array $value): array {
    return [
        'schema' => $value['metadata']['schema_version'],
        'direct' => $value['resolution']['status'],
        'staged' => $value['staged_resolution']['status'],
        'stages' => array_map(static fn (array $stage): array => [
            $stage['id'],
            $stage['execution_state'],
            $stage['resolution_status'],
            $stage['selected_attempt'],
            $stage['input_state']['state_sha256'] ?? null,
            $stage['output_state']['state_sha256'] ?? null,
            $stage['source_impact'],
        ], $value['staged_resolution']['stages']),
        'blockers' => array_map(static fn (array $blocker): array => [
            $blocker['stage_id'],
            $blocker['category'],
            $blocker['subject'],
            $blocker['blocking_package'],
            $blocker['constraint'],
            $blocker['dependency_path'],
            $blocker['lifecycle'],
            array_column($blocker['lifecycle_history'], 'status'),
        ], $value['staged_resolution']['blocker_registry']),
    ];
};
if ($projection($report) !== $projection($canonical)) {
    throw new RuntimeException('Generated staged evidence differs from the checked-in canonical report.');
}

printf("\n\033[1;31mDirect final target: %s\033[0m\n", strtoupper($report['resolution']['status']));
printf("\033[1;31mStaged resolution:   %s\033[0m\n", strtoupper($report['staged_resolution']['status']));
printf("Composer execution:  %s (offline best effort; no OS sandbox)\n", strtoupper($report['composer_execution']['mode']));
foreach ($report['staged_resolution']['stages'] as $stage) {
    printf(
        "Stage %d->%d: %-21s attempts=%d selected=%s\n",
        $stage['from_major'],
        $stage['to_major'],
        strtoupper((string) $stage['resolution_status']),
        count($stage['attempts']),
        $stage['selected_attempt'] === null ? 'none' : (string) $stage['selected_attempt']
    );
}
foreach ($report['staged_resolution']['blocker_registry'] as $blocker) {
    printf(
        "Blocker %-9s %-22s %s\n",
        strtoupper($blocker['lifecycle']),
        $blocker['blocking_package'] ?? $blocker['subject'],
        $blocker['constraint'] ?? ''
    );
}
$stagedImpact = array_column($report['staged_resolution']['source_impact'], null, 'id');
$reference = $report['staged_resolution']['stages'][2]['source_impact'][0] ?? null;
if (!is_string($reference) || !isset($stagedImpact[$reference]['occurrences'][0])) {
    throw new RuntimeException('The final stage no longer references a resolvable source-impact finding.');
}
$occurrence = $stagedImpact[$reference]['occurrences'][0];
printf(
    "Original-source finding: %s:%d  %s\n",
    $occurrence['file'],
    $occurrence['line'],
    basename(str_replace('\\', '/', $occurrence['symbol']))
);
