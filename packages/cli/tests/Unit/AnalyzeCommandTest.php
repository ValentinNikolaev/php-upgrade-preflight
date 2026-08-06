<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
use PhpUpgradePreflight\Core\Model\LockDiff;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\RiskSummary;
use PhpUpgradePreflight\Core\Model\UpgradeReport;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PHPUnit\Framework\TestCase;

final class AnalyzeCommandTest extends TestCase
{
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;
    private FakeUpgradeAnalyzer $analyzer;
    private AnalyzeCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Unable to open in-memory test streams.');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->analyzer = new FakeUpgradeAnalyzer();
        $this->command = new AnalyzeCommand($this->analyzer, $this->stdout, $this->stderr);
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);

        parent::tearDown();
    }

    public function testItParsesOptionsAndPassesARequestToTheAnalyzer(): void
    {
        $exitCode = $this->command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . dirname(__DIR__, 4),
            '--target=laravel/framework:^9.0',
            '--target=php:8.1',
            '--from-php=7.4',
            '--source=app',
            '--framework=laravel',
            '--format=markdown',
            '--debug',
        ]);

        self::assertSame(0, $exitCode);
        self::assertNotNull($this->analyzer->request);
        self::assertSame(['laravel/framework', 'php'], array_map(
            static fn ($target): string => $target->package,
            $this->analyzer->request->targets->all()
        ));
        self::assertSame('8.1.0', $this->analyzer->request->targetPhp);
        self::assertSame('7.4', $this->analyzer->request->fromPhp);
        self::assertSame(['app'], $this->analyzer->request->sourcePaths);
        self::assertSame(['laravel'], $this->analyzer->request->frameworks);
        self::assertSame(ReportFormat::MARKDOWN, $this->analyzer->request->format);
        self::assertTrue($this->analyzer->request->debug);
        self::assertStringStartsWith('# PHP Upgrade Preflight', $this->streamContents($this->stdout));
        self::assertSame('', $this->streamContents($this->stderr));
    }

    public function testItReturnsFailureForInvalidInputWithoutCallingTheAnalyzer(): void
    {
        $exitCode = $this->command->run(['upgrade-intel', 'analyze']);

        self::assertSame(1, $exitCode);
        self::assertNull($this->analyzer->request);
        self::assertStringContainsString('At least one --target', $this->streamContents($this->stderr));
    }

    public function testItPrintsHelpWithoutCallingTheAnalyzer(): void
    {
        $exitCode = $this->command->run(['upgrade-intel', '--help']);

        self::assertSame(0, $exitCode);
        self::assertNull($this->analyzer->request);
        self::assertStringStartsWith('Usage:', $this->streamContents($this->stdout));
    }

    public function testItRejectsAnOutputPathInsideTheAnalyzedProject(): void
    {
        $projectPath = dirname(__DIR__, 4);
        $composerPath = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';
        $before = file_get_contents($composerPath);

        $exitCode = $this->command->run([
            'upgrade-intel',
            'analyze',
            '--path=' . $projectPath,
            '--target=fixture/dependency:^2.0',
            '--output=' . $composerPath,
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame($before, file_get_contents($composerPath));
        self::assertStringContainsString('outside the analyzed project', $this->streamContents($this->stderr));
    }

    /** @param resource $stream */
    private function streamContents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);

        self::assertIsString($contents);

        return $contents;
    }
}

final class FakeUpgradeAnalyzer implements UpgradeAnalyzer
{
    public ?UpgradeRequest $request = null;

    public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
    {
        $this->request = $request;

        return new UpgradeReport(
            $request,
            new ProjectState($request->projectPath, new ComposerJson([]), new ComposerLock([])),
            [],
            new LockDiff([]),
            [],
            [],
            [],
            new RiskSummary('low', []),
            new EffortEstimate([0, 0], 'high', [], []),
            [],
            []
        );
    }
}
