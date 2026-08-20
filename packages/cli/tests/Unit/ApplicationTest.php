<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\Application;
use PhpUpgradePreflight\Cli\CommandRunner;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testItRoutesOnlyTheExplicitWizardCommandToTheWizard(): void
    {
        $analyze = new RouteRecordingCommandRunner(11);
        $wizard = new RouteRecordingCommandRunner(12);
        $application = new Application($analyze, $wizard);

        self::assertSame(12, $application->run(['upgrade-intel', 'wizard']));
        self::assertSame(11, $application->run(['upgrade-intel', 'analyze', '--target-php=8.3']));
        self::assertSame(11, $application->run(['upgrade-intel', '--help']));
        self::assertCount(1, $wizard->calls);
        self::assertCount(2, $analyze->calls);
    }
}

final class RouteRecordingCommandRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $calls = [];
    private int $exitCode;

    public function __construct(int $exitCode)
    {
        $this->exitCode = $exitCode;
    }

    public function run(array $argv): int
    {
        $this->calls[] = $argv;

        return $this->exitCode;
    }
}
