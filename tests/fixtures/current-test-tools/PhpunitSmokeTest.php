<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ConsumerUpgrade.php';

final class PhpunitSmokeTest extends TestCase
{
    public function testInstalledAnalyzerPreservesTheCurrentPhpTarget(): void
    {
        $report = analyzeCurrentConsumer();

        self::assertSame('8.5.11', $report->request()->targetPhp());
        self::assertSame('feasible_with_changes', $report->resolutionStatus());
        self::assertSame('2.0.0', $report->lockDiff()->packageChanges()[0]->toVersion());
        foreach ($report->scenarios() as $scenario) {
            self::assertTrue($scenario->succeeded(), $scenario->stderr());
        }
    }
}
