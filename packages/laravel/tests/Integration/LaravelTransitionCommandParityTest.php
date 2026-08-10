<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Integration;

use Illuminate\Contracts\Foundation\Application;
use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;
use PhpUpgradePreflight\Tests\Support\LaravelTransitionFixtureFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

final class LaravelTransitionCommandParityTest extends TestCase
{
    /** @dataProvider transitionCaseProvider */
    public function testCliAndArtisanHaveCanonicalJsonParityForEveryTransitionFixture(array $case): void
    {
        $projectPath = dirname(__DIR__, 4) . '/tests/fixtures/projects/' . $case['fixture'];
        [$cliExit, $cliJson, $cliError] = $this->runCli($case, $projectPath);
        [$artisanExit, $artisanJson, $artisanError] = $this->runArtisan($case, $projectPath);

        self::assertSame(Command::SUCCESS, $cliExit, $cliError);
        self::assertSame(Command::SUCCESS, $artisanExit, $artisanError);
        self::assertSame('', $cliError);
        self::assertSame('', $artisanError);

        $cli = $this->normalizeReport($this->decode($cliJson));
        $artisan = $this->normalizeReport($this->decode($artisanJson));
        self::assertSame($cli, $artisan);
        self::assertSame($case['resolution'], $cli['resolution']['status']);
        self::assertSame($case['guidance'], $cli['transition']['framework_guidance'][0]['status']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public function transitionCaseProvider(): iterable
    {
        $contents = file_get_contents(dirname(__DIR__, 4) . '/tests/fixtures/contracts/laravel-v0.2-transition-cases.json');
        if ($contents === false) {
            throw new \RuntimeException('Unable to read the Laravel transition fixture contract.');
        }
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($contract) || !is_array($contract['cases'] ?? null)) {
            throw new \RuntimeException('Invalid Laravel transition fixture contract.');
        }

        foreach ($contract['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /** @return array{int, string, string} */
    private function runCli(array $case, string $projectPath): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Unable to open CLI parity streams.');
        }

        try {
            $command = new AnalyzeCommand(
                LaravelTransitionFixtureFactory::analyzer($case['catalog']),
                $stdout,
                $stderr
            );
            $exitCode = $command->run([
                'upgrade-intel',
                'analyze',
                '--path=' . $projectPath,
                '--target=' . $case['target_package'] . ':' . $case['target_constraint'],
                '--target-php=' . $case['target_php'],
                '--framework=laravel',
                '--format=json',
            ]);

            return [$exitCode, $this->streamContents($stdout), $this->streamContents($stderr)];
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
    }

    /** @return array{int, string, string} */
    private function runArtisan(array $case, string $projectPath): array
    {
        $application = $this->createMock(Application::class);
        $application->method('basePath')->willReturn($projectPath);
        $application->method('make')->willReturnCallback(
            static fn (string $abstract, array $parameters): SymfonyStyle => new SymfonyStyle(
                $parameters['input'],
                $parameters['output']
            )
        );
        $application->method('call')->willReturnCallback(
            static fn (callable $callback): int => (int) $callback()
        );

        $command = new AnalyzeUpgradeCommand(LaravelTransitionFixtureFactory::analyzer($case['catalog']));
        $command->setLaravel($application);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--path' => $projectPath,
            '--target' => [$case['target_package'] . ':' . $case['target_constraint']],
            '--target-php' => $case['target_php'],
            '--format' => 'json',
        ], ['capture_stderr_separately' => true]);

        return [$exitCode, $tester->getDisplay(), $tester->getErrorOutput()];
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);

        return $report;
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    private function normalizeReport(array $report): array
    {
        foreach ($report['resolution']['scenarios'] as &$scenario) {
            $scenario['duration_ms'] = 0;
        }
        unset($scenario);

        return $report;
    }

    /** @param resource $stream */
    private function streamContents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read parity stream.');
        }

        return $contents;
    }
}
