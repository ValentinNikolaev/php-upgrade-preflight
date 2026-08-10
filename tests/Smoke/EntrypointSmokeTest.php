<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Smoke;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

final class EntrypointSmokeTest extends TestCase
{
    public function testGenericCliStartsWithoutNetworkAccess(): void
    {
        $process = $this->runProcess([
            PHP_BINARY,
            $this->repositoryRoot() . '/packages/cli/bin/upgrade-intel',
            '--help',
        ]);

        self::assertSame(Command::SUCCESS, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringStartsWith('Usage:', $process->getOutput());
    }

    public function testArtisanHarnessStartsWithoutNetworkAccess(): void
    {
        $process = $this->runProcess([
            PHP_BINARY,
            $this->repositoryRoot() . '/tests/fixtures/artisan-harness/artisan',
            'upgrade:analyze',
        ]);

        self::assertSame(Command::INVALID, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('Invalid invocation:', $process->getErrorOutput());
    }

    /** @param list<string> $command */
    private function runProcess(array $command): Process
    {
        $process = new Process($command, $this->repositoryRoot(), [
            'COMPOSER_DISABLE_NETWORK' => '1',
            'COMPOSER_NO_INTERACTION' => '1',
            'PHP_UPGRADE_PREFLIGHT_TEST_PROJECT_PATH' => $this->repositoryRoot()
                . '/tests/fixtures/path-repository/project',
        ]);
        $process->setTimeout(30);
        $process->run();

        return $process;
    }

    private function repositoryRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
