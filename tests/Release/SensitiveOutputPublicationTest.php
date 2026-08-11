<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Core\Analysis\BlockerGrouper;
use PhpUpgradePreflight\Core\Model\ComposerDiagnostic;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Reporting\JsonReportWriter;
use PhpUpgradePreflight\Core\Reporting\MarkdownReportWriter;
use PhpUpgradePreflight\Core\Support\SensitiveOutputRedactor;
use PHPUnit\Framework\TestCase;

final class SensitiveOutputPublicationTest extends TestCase
{
    public function testReleasePackageSourcesDoNotEmbedSyntheticCanaries(): void
    {
        $fixture = $this->fixture();
        $packageRoot = dirname(__DIR__, 2) . '/packages';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($packageRoot));

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertNotFalse($contents, 'Unable to inspect release package source ' . $file->getPathname());
            $this->assertNoCanaries($fixture['canaries'], $contents, $file->getPathname());
        }
    }

    public function testSyntheticComposerSecretsCannotReachReportsEvidenceOrCapturedLogs(): void
    {
        $fixture = $this->fixture();
        $projectPath = dirname(__DIR__, 2);
        $request = new UpgradeRequest(
            $projectPath,
            [new UpgradeTarget('vendor/private-package', '^2.0')]
        );
        $diagnostic = new ComposerDiagnostic(
            'vendor/private-package',
            '^2.0',
            ['composer', 'prohibits', 'vendor/private-package', '^2.0'],
            1,
            $fixture['stdout'],
            $fixture['stderr']
        );
        $scenario = new ScenarioResult(
            new Scenario('synthetic-secret-output', $request->targets()),
            2,
            $fixture['stdout'],
            $fixture['stderr'],
            null,
            null,
            ScenarioResult::FAILURE_SOLVER,
            '2.10.2',
            ['composer', 'update', 'vendor/private-package'],
            1,
            null,
            [$diagnostic]
        );
        $evidence = new EvidenceLedger();
        $blockers = (new BlockerGrouper())->group(
            [$scenario],
            $evidence,
            new ComposerLock([]),
            ['vendor/private-package' => '^2.0']
        );
        self::assertNotEmpty($blockers);
        self::assertNotEmpty($evidence->all());

        $report = new UpgradeReport(
            $request,
            new ProjectState($projectPath, new ComposerJson([]), new ComposerLock([])),
            [$scenario],
            new LockDiff([]),
            $blockers,
            [],
            [],
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'high', [], []),
            [],
            $evidence->all()
        );

        $surfaces = [
            'scenario stdout accessor' => $scenario->stdout(),
            'scenario stderr accessor' => $scenario->stderr(),
            'diagnostic stdout accessor' => $diagnostic->stdout(),
            'diagnostic stderr accessor' => $diagnostic->stderr(),
            'solver evidence context' => json_encode(
                array_map(static fn (Evidence $item): array => $item->toArray(), $evidence->all()),
                JSON_THROW_ON_ERROR
            ),
            'canonical JSON' => (new JsonReportWriter())->render($report),
            'Markdown report' => (new MarkdownReportWriter())->render($report),
            'captured CI diagnostic' => SensitiveOutputRedactor::redact(
                'Analysis failed: ' . $fixture['stdout'] . "\n" . $fixture['stderr']
            ),
        ];

        foreach ($surfaces as $surfaceName => $surface) {
            $this->assertNoCanaries($fixture['canaries'], $surface, $surfaceName);
        }

        self::assertStringContainsString(
            '- vendor/blocker 1.0.0 requires vendor/private-package (^1.0).',
            $scenario->stdout()
        );
    }

    /** @return array{canaries: array<string, string>, stdout: string, stderr: string} */
    private function fixture(): array
    {
        $contents = file_get_contents(dirname(__DIR__) . '/fixtures/security/composer-output-with-secrets.json');
        self::assertNotFalse($contents);
        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);

        /** @var array{canaries: array<string, string>, stdout: string, stderr: string} $fixture */
        return $fixture;
    }

    /** @param array<string, string> $canaries */
    private function assertNoCanaries(array $canaries, string $surface, string $surfaceName): void
    {
        foreach ($canaries as $label => $canary) {
            if (str_contains($surface, $canary)) {
                self::fail(sprintf('Sensitive canary %s reached %s.', $label, $surfaceName));
            }
        }
    }
}
