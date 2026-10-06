<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class PrepareDistributionModesTest extends TestCase
{
    private Filesystem $filesystem;
    private string $temporaryRoot;
    private string $repository;
    private string $git;

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('The executable-bit simulation requires a POSIX filesystem and Bash.');
        }

        $finder = new ExecutableFinder();
        $git = $finder->find('git');
        self::assertNotNull($git, 'The Linux release gate requires Git.');
        self::assertNotNull($finder->find('bash'), 'The Linux release gate requires Bash.');
        $this->git = $git;
        $this->filesystem = new Filesystem();
        $this->temporaryRoot = sys_get_temp_dir() . '/preflight-export-modes-' . bin2hex(random_bytes(8));
        $this->repository = $this->temporaryRoot . '/monorepo with spaces';
        $this->filesystem->mkdir($this->repository . '/tools');
    }

    protected function tearDown(): void
    {
        if (isset($this->temporaryRoot)) {
            $this->filesystem->remove($this->temporaryRoot);
        }
    }

    /** @dataProvider filesystemModeProvider */
    public function testStagedPayloadUsesCommittedModesAndBytesInsteadOfFilesystemModes(int $filesystemMode): void
    {
        $this->filesystem->copy(
            dirname(__DIR__, 2) . '/tools/prepare-distribution.sh',
            $this->repository . '/tools/prepare-distribution.sh'
        );
        foreach (['LICENSE', 'README.md', 'CHANGELOG.md', 'SECURITY.md', 'docs/guide with spaces.md'] as $path) {
            $this->filesystem->dumpFile($this->repository . '/' . $path, 'shared ' . $path . "\n");
        }
        foreach (['core', 'cli', 'laravel'] as $package) {
            $this->filesystem->dumpFile($this->repository . '/packages/' . $package . '/composer.json', "{}\n");
            $this->filesystem->dumpFile($this->repository . '/packages/' . $package . '/src/file with spaces.php', "<?php\n");
        }
        $this->filesystem->dumpFile($this->repository . '/packages/cli/bin/upgrade-intel', "#!/usr/bin/env php\n<?php\n");
        self::assertTrue(symlink('file with spaces.php', $this->repository . '/packages/core/src/linked file.php'));
        $this->initializeRepository($this->repository);
        $this->runCommand([$this->git, 'add', '-A'], $this->repository);
        foreach ($this->index($this->repository) as $path => $entry) {
            if ($entry['mode'] !== '120000') {
                $this->runCommand([$this->git, 'update-index', '--chmod=-x', '--', $path], $this->repository);
            }
        }
        $this->runCommand([$this->git, 'update-index', '--chmod=+x', '--', 'packages/cli/bin/upgrade-intel'], $this->repository);
        $this->runCommand([$this->git, '-c', 'commit.gpgsign=false', 'commit', '-qm', 'authoritative release input'], $this->repository);
        $sourceIndex = $this->index($this->repository);
        $sourceCommit = trim($this->runCommand([$this->git, 'rev-parse', 'HEAD'], $this->repository));

        // Model an NTFS bind mount: stat modes disagree with the committed tree,
        // but the source checkout remains clean because core.filemode is false.
        $this->runCommand([$this->git, 'config', 'core.filemode', 'false'], $this->repository);
        foreach ($sourceIndex as $path => $entry) {
            if ($entry['mode'] !== '120000') {
                self::assertTrue(chmod($this->repository . '/' . $path, $filesystemMode));
            }
        }
        self::assertSame('', $this->runCommand([$this->git, 'status', '--porcelain'], $this->repository));

        foreach (['core', 'cli', 'laravel'] as $package) {
            $seed = $this->temporaryRoot . '/seeds/' . $package;
            $this->filesystem->mkdir($seed);
            $this->initializeRepository($seed);
            $this->filesystem->dumpFile($seed . '/obsolete.txt', "old distribution payload\n");
            $this->runCommand([$this->git, 'add', '-A'], $seed);
            $this->runCommand([$this->git, '-c', 'commit.gpgsign=false', 'commit', '-qm', 'local distribution seed'], $seed);
        }
        $wrapper = <<<'BASH'
git() {
  if [[ "${1:-}" == clone ]]; then
    if [[ "$#" != 4 || "$2" != --quiet ]]; then
      echo 'Unexpected clone invocation' >&2
      return 71
    fi
    case "$3" in
      https://github.com/ValentinNikolaev/php-upgrade-preflight-core.git) package=core ;;
      https://github.com/ValentinNikolaev/php-upgrade-preflight-cli.git) package=cli ;;
      https://github.com/ValentinNikolaev/php-upgrade-preflight-laravel.git) package=laravel ;;
      *) echo 'Network clone is forbidden in this test' >&2; return 72 ;;
    esac
    printf '%s\n' "$package" >> "$EXPORT_TEST_ROOT/clone-log"
    "$EXPORT_TEST_GIT" -c protocol.file.allow=always clone --quiet "$EXPORT_TEST_ROOT/seeds/$package" "$4"
  else
    "$EXPORT_TEST_GIT" "$@"
  fi
}
BASH;
        $this->filesystem->dumpFile($this->temporaryRoot . '/git-wrapper.bash', $wrapper);
        $destination = $this->temporaryRoot . '/distributions with spaces';
        $this->runCommand(['bash', 'tools/prepare-distribution.sh', $destination], $this->repository, [
            'BASH_ENV' => $this->temporaryRoot . '/git-wrapper.bash',
            'EXPORT_TEST_ROOT' => $this->temporaryRoot,
            'EXPORT_TEST_GIT' => $this->git,
        ]);

        self::assertSame("core\ncli\nlaravel\n", file_get_contents($this->temporaryRoot . '/clone-log'));
        self::assertSame($sourceCommit, trim((string) file_get_contents($destination . '/.source-commit')));
        foreach (['core', 'cli', 'laravel'] as $package) {
            $expected = [];
            foreach ($sourceIndex as $path => $entry) {
                $prefix = 'packages/' . $package . '/';
                if (strpos($path, $prefix) === 0) {
                    $expected[substr($path, strlen($prefix))] = $entry;
                } elseif (strpos($path, 'docs/') === 0 || in_array($path, ['LICENSE', 'README.md', 'CHANGELOG.md', 'SECURITY.md'], true)) {
                    $expected[$path] = $entry;
                }
            }
            ksort($expected, SORT_STRING);
            self::assertSame(
                $expected,
                $this->index($destination . '/' . $package),
                $package . ': exported modes and blob bytes must match the source commit, including executable CLI and symlinks.'
            );
        }
        self::assertSame($sourceIndex, $this->index($this->repository), 'Export must not mutate the source index.');
        self::assertSame('', $this->runCommand([$this->git, 'status', '--porcelain'], $this->repository));
    }

    /** @return list<array{int}> */
    public function filesystemModeProvider(): array
    {
        return [[0777], [0666]];
    }

    private function initializeRepository(string $directory): void
    {
        $this->runCommand([$this->git, 'init', '-q'], $directory);
        $this->runCommand([$this->git, 'config', 'user.name', 'Offline release test'], $directory);
        $this->runCommand([$this->git, 'config', 'user.email', 'release-test@example.invalid'], $directory);
        $this->runCommand([$this->git, 'config', 'core.autocrlf', 'false'], $directory);
        $this->runCommand([$this->git, 'config', 'core.filemode', 'true'], $directory);
    }

    /** @return array<string, array{mode: string, blob: string}> */
    private function index(string $directory): array
    {
        $result = [];
        foreach (explode("\0", $this->runCommand([$this->git, 'ls-files', '--stage', '-z'], $directory)) as $record) {
            if ($record === '') {
                continue;
            }
            [$metadata, $path] = explode("\t", $record, 2);
            [$mode, $blob] = explode(' ', $metadata);
            $result[$path] = ['mode' => $mode, 'blob' => $blob];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /**
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    private function runCommand(array $command, string $directory, array $environment = []): string
    {
        $process = new Process($command, $directory, $environment + [
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_TERMINAL_PROMPT' => '0',
        ]);
        $process->setTimeout(60);
        $process->run();
        self::assertTrue($process->isSuccessful(), $process->getCommandLine() . "\n" . $process->getErrorOutput());

        return $process->getOutput();
    }
}
