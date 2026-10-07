<?php

declare(strict_types=1);

require_once __DIR__ . '/ConsumerUpgrade.php';

test('the installed analyzer preserves the current PHP target', function (): void {
    $report = analyzeCurrentConsumer();

    expect($report->request()->targetPhp())->toBe('8.5.11');
    expect($report->resolutionStatus())->toBe('feasible_with_changes');
    expect($report->lockDiff()->packageChanges()[0]->toVersion())->toBe('2.0.0');
    foreach ($report->scenarios() as $scenario) {
        expect($scenario->succeeded())->toBeTrue();
    }
});
