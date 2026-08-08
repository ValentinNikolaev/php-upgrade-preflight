<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class CommandEntryPointInstallLayoutTest extends TestCase
{
    private Filesystem $filesystem;
    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/php-upgrade-preflight-installed-cli-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory . '/vendor/php-upgrade-preflight/cli/bin');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testExecutableFindsTheConsumerAutoloaderFromAnInstalledPackageLayout(): void
    {
        $root = dirname(__DIR__, 4);
        $bin = $this->directory . '/vendor/php-upgrade-preflight/cli/bin/upgrade-intel';
        $this->filesystem->copy($root . '/packages/cli/bin/upgrade-intel', $bin);
        $this->filesystem->dumpFile(
            $this->directory . '/vendor/autoload.php',
            sprintf("<?php\nrequire %s;\n", var_export($root . '/vendor/autoload.php', true))
        );

        $process = new Process([PHP_BINARY, $bin, '--help']);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringStartsWith('Usage:', $process->getOutput());
    }
}
