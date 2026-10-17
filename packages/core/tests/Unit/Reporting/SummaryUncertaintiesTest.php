<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Tests\Unit\Reporting;

use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Reporting\SummaryReportWriter;
use PhpUpgradePreflight\Core\Reporting\SummaryUncertainties;
use PHPUnit\Framework\TestCase;

final class SummaryUncertaintiesTest extends TestCase
{
    private const CAPS = [
        'Source scanning reached the 5000-file safety limit; 75 additional PHP file(s) were omitted. (source-limit-1)',
        'Source scanning reached the 1048576-byte per-file byte limit; 3 file(s) were not scanned. (source-limit-2)',
        'Source scanning reached the 16777216-byte aggregate byte limit; 27 file(s) were not scanned. (source-limit-3)',
        'Source scanning reached the 10000-usage safety limit; 8672 additional unique usage(s) were omitted. (source-limit-4)',
        'Composer source root "autoload" was not discovered: the 256-path metadata safety limit was reached; modular source coverage may be incomplete. (source-discovery-1)',
    ];

    /** @dataProvider materialUsageCaps */
    public function testSourceWarningsCannotHideMaterialUsageCaps(string $warning, int $omitted, bool $capFirst): void
    {
        $cap = sprintf('Source scanning reached the 10000-usage safety limit; %d additional unique usage(s) were omitted. (source-limit-1)', $omitted);
        $lines = (new SummaryUncertainties($capFirst ? [$cap, $warning] : [$warning, $cap]))->lines();
        $summary = implode("\n", $lines);

        self::assertCount(2, $lines);
        self::assertStringContainsString($warning, $summary);
        self::assertStringContainsString($cap, $summary);
        self::assertStringNotContainsString('Additional uncertainties', $summary);
        self::assertStringNotContainsString($omitted . ' additional PHP file', $summary);
        self::assertStringNotContainsString('%', $summary);
    }

    /** @return array<string, array{string, int, bool}> */
    public static function materialUsageCaps(): array
    {
        return [
            'Lychee missing root before cap' => ['Source path "tests/" does not exist and was not scanned.', 8672, false],
            'Snipe-IT parse failure before cap' => ['Source file "app/ActionlogController.php" could not be parsed and was not scanned (source-1).', 8284, false],
            'cap before missing root' => ['Source path "tests/" does not exist and was not scanned.', 8672, true],
            'cap before parse failure' => ['Source file "app/ActionlogController.php" could not be parsed and was not scanned (source-1).', 8284, true],
        ];
    }

    public function testDiscoveryMetadataCapRemainsSeparateFromMissingRootAndParseWarnings(): void
    {
        $cap = self::CAPS[4];
        foreach ([
            'Source path "tests/" does not exist and was not scanned.',
            'Source file "app/Parse.php" could not be parsed and was not scanned (source-1).',
        ] as $warning) {
            $lines = (new SummaryUncertainties([$warning, $cap]))->lines();
            $summary = implode("\n", $lines);

            self::assertCount(2, $lines);
            self::assertStringContainsString($warning, $summary);
            self::assertStringContainsString($cap, $summary);
            self::assertStringNotContainsString('256 file', $summary);
            self::assertStringNotContainsString('256 omitted', $summary);
        }
    }

    public function testMixedCapKindsAndExactEvidenceSurviveLargeWarningFloodsWithBoundedOutput(): void
    {
        $warnings = [];
        for ($index = 1; $index <= 5000; ++$index) {
            $warnings[] = sprintf('Source path "missing-%d/" does not exist and was not scanned.', $index);
            $warnings[] = sprintf('Source file "app/Parse%d.php" could not be parsed and was not scanned (source-%d).', $index, $index);
        }
        $otherCategories = [
            'Project input changed during analysis.',
            'Composer process timed out.',
            'Framework hop unsupported.',
            'Composer extension checks used the analyzer runtime.',
            'An additional recorded uncertainty.',
        ];
        $lines = (new SummaryUncertainties(array_merge($warnings, self::CAPS, $otherCategories)))->lines();
        $summary = implode("\n", $lines);

        self::assertCount(12, $lines);
        foreach (self::CAPS as $cap) {
            self::assertSame(1, substr_count($summary, $cap));
        }
        foreach ($otherCategories as $uncertainty) {
            self::assertStringContainsString($uncertainty, $summary);
        }
        self::assertStringContainsString('Source path "missing-1/"', $summary);
        self::assertStringContainsString('source; 10000 recorded', $summary);
        self::assertStringContainsString('Additional uncertainties in full details: 9999.', $summary);
        self::assertStringNotContainsString('Parse5000.php', $summary);
        self::assertStringNotContainsString('%', $summary);
    }

    public function testCapFactsRemainVisibleThroughPrivacyUtf8ControlAndByteBoundaries(): void
    {
        $root = dirname(__DIR__, 5);
        $warning = 'Source path "' . $root . "/private\xB1" . '" is missing https://user:password@example.test/repo ' . "\x1b[31m\u{0085}\n" . str_repeat('é', 300);
        $warnings = array_map(static fn (int $index): string => $warning . ' warning-' . $index, range(1, 1000));
        $warnings[] = 'Source file "app/Parse.php" could not be parsed and was not scanned (source-1).';
        $report = $this->report(array_merge($warnings, self::CAPS));
        $summary = (new SummaryReportWriter())->render($report);

        foreach (self::CAPS as $cap) {
            self::assertStringContainsString($cap, $summary);
        }
        self::assertStringContainsString("[PROJECT_ROOT]/private\u{FFFD}", $summary);
        self::assertStringNotContainsString($root, $summary);
        self::assertStringNotContainsString('password', $summary);
        self::assertSame(1, preg_match('//u', $summary));
        self::assertStringContainsString('[TRUNCATED:', $summary);
        self::assertStringContainsString('Additional uncertainties in full details: 1000.', $summary);
        self::assertLessThanOrEqual(25, substr_count($summary, PHP_EOL));
        foreach (explode(PHP_EOL, $summary) as $line) {
            self::assertSame(0, preg_match('/\p{Cc}/u', $line));
            self::assertLessThanOrEqual(320, strlen($line));
        }
    }

    public function testSummaryGroupingLeavesFullJsonAndMarkdownFactsUnchanged(): void
    {
        $uncertainties = array_merge([
            'Source path "tests/" does not exist and was not scanned.',
            'Source file "app/Parse.php" could not be parsed and was not scanned (source-1).',
        ], self::CAPS);
        $report = $this->report($uncertainties);
        $beforeUncertainties = $report->uncertainties();
        $json = new JsonReportWriter();
        $markdown = new MarkdownReportWriter();
        $beforeJson = $json->render($report);
        /** @var array{uncertainties: list<string>} $beforeJsonFacts */
        $beforeJsonFacts = json_decode($beforeJson, true, 512, JSON_THROW_ON_ERROR);
        $beforeMarkdown = $markdown->render($report);

        (new SummaryReportWriter())->render($report);

        self::assertSame($beforeJson, $json->render($report));
        self::assertSame($beforeMarkdown, $markdown->render($report));
        foreach (self::CAPS as $cap) {
            self::assertContains($cap, $beforeJsonFacts['uncertainties']);
            self::assertStringContainsString($cap, $beforeMarkdown);
        }
        self::assertSame($beforeUncertainties, $report->uncertainties());
    }

    /** @param list<string> $uncertainties */
    private function report(array $uncertainties): UpgradeReport
    {
        $root = dirname(__DIR__, 5);
        $request = new UpgradeRequest($root, [new UpgradeTarget('vendor/legacy', '^2.0')], targetPhp: '8.3.0');
        $state = new ProjectState($root, new ComposerJson(['require' => ['vendor/legacy' => '^1.0']]), new ComposerLock([]));
        $evidence = [
            new Evidence('source-1', Evidence::E3_PROJECT_SOURCE, 'Recorded source parse failure.'),
            new Evidence('source-discovery-1', Evidence::E2_PACKAGE_METADATA, 'A Composer autoload source root was excluded from default discovery.', 'high', [
                'path' => 'autoload',
                'reason' => 'the 256-path metadata safety limit was reached',
            ]),
        ];
        foreach ([['file_count', 5000, 75], ['file_bytes', 1048576, 3], ['total_bytes', 16777216, 27], ['usage_count', 10000, 8672]] as $index => [$type, $limit, $omitted]) {
            $evidence[] = new Evidence('source-limit-' . ($index + 1), Evidence::E3_PROJECT_SOURCE, 'Source scan output was bounded by an operational safety limit.', 'high', [
                'limit_type' => $type,
                'limit' => $limit,
                'omitted_count' => $omitted,
            ]);
        }

        return new UpgradeReport($request, $state, [], new LockDiff([]), [], [], [], new RiskSummary('low', []), new EffortEstimate([0, 0], 'low', [], []), $uncertainties, $evidence);
    }
}
