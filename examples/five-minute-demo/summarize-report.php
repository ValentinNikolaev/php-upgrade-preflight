<?php

declare(strict_types=1);

/*
 * Compares a generated five-minute-demo report against the checked-in canonical
 * report and prints the demo summary shown in the recorded GIF.
 *
 * Compare stable findings across Composer versions, which may write different
 * lock metadata. Validate each report's state chain independently; require exact
 * candidate fingerprints only when both reports used the same Composer version.
 */

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php summarize-report.php <generated-report.json> <canonical-report.json>\n");
    exit(2);
}

/** @var array<string, mixed> $report */
$report = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
/** @var array<string, mixed> $canonical */
$canonical = json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);

$sameComposer = is_string($report['composer_execution']['composer_version'] ?? null)
    && $report['composer_execution']['composer_version'] === ($canonical['composer_execution']['composer_version'] ?? null);
$stateProjection = static function (?array $state) use ($sameComposer): ?array {
    if ($state === null || $sameComposer) {
        return $state;
    }

    return array_intersect_key($state, array_flip(['manifest_sha256', 'platform_sha256', 'execution_policy_sha256']));
};
$validateState = static function (?array $state): void {
    if ($state === null) {
        return;
    }
    $components = [];
    foreach (['execution_policy', 'lock', 'manifest', 'platform'] as $component) {
        $hash = $state[$component . '_sha256'] ?? null;
        if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new RuntimeException('The report has an invalid candidate-state fingerprint.');
        }
        $components[$component] = $hash;
    }
    if (($state['state_sha256'] ?? null) !== hash('sha256', json_encode($components, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
        throw new RuntimeException('The report has an invalid candidate-state fingerprint.');
    }
};
$projection = static function (array $value) use ($stateProjection, $validateState): array {
    $previousOutput = null;
    foreach ($value['staged_resolution']['stages'] as $index => $stage) {
        if ($index > 0 && ($previousOutput === null || $stage['input_state'] !== $previousOutput)) {
            throw new RuntimeException('The report has a broken candidate-state chain.');
        }
        $selectedOutput = null;
        foreach ($stage['attempts'] as $attempt) {
            if ($attempt['input_state'] !== $stage['input_state']) {
                throw new RuntimeException('An attempt does not use its stage input state.');
            }
            if ($attempt['selected']) {
                if ($selectedOutput !== null || $attempt['number'] !== $stage['selected_attempt'] || $attempt['output_state'] === null) {
                    throw new RuntimeException('The report has an invalid selected attempt state.');
                }
                $selectedOutput = $attempt['output_state'];
            }
        }
        if ($selectedOutput !== $stage['output_state'] || (($stage['selected_attempt'] === null) !== ($selectedOutput === null))) {
            throw new RuntimeException('The report has an invalid selected attempt state.');
        }
        $validateState($stage['input_state']);
        $validateState($stage['output_state']);
        $previousOutput = $stage['output_state'];
    }

    return [
        'schema' => $value['metadata']['schema_version'],
        'direct' => $value['resolution']['status'],
        'staged' => $value['staged_resolution']['status'],
        'initial_state' => $value['staged_resolution']['stages'][0]['input_state'],
        'stages' => array_map(static fn (array $stage): array => [
            $stage['id'],
            $stage['execution_state'],
            $stage['resolution_status'],
            $stage['selected_attempt'],
            $stateProjection($stage['input_state']),
            $stateProjection($stage['output_state']),
            $stage['package_changes'],
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
