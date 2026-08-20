<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli\Tests\Unit;

use PhpUpgradePreflight\Cli\CommandRunner;
use PhpUpgradePreflight\Cli\PackageTargetCandidateProvider;
use PhpUpgradePreflight\Cli\PackageTargetValidation;
use PhpUpgradePreflight\Cli\PackageTargetValidator;
use PhpUpgradePreflight\Cli\WizardCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

require_once __DIR__ . '/CliFilesystemFunctionOverrides.php';

final class WizardCommandTest extends TestCase
{
    private Filesystem $filesystem;
    private string $projectPath;
    /** @var resource */
    private $stdin;
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;
    private RecordingCommandRunner $analyze;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'php-upgrade-preflight-wizard-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->projectPath);
        $this->filesystem->dumpFile($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'require' => [
                'php' => '^8.1',
                'vendor/package' => '^1.0',
            ],
            'config' => ['platform' => ['php' => '8.1.0']],
        ], JSON_THROW_ON_ERROR));

        $stdin = fopen('php://memory', 'w+');
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdin);
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $this->stdin = $stdin;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->analyze = new RecordingCommandRunner($this->stdout);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectPath);
        fclose($this->stdin);
        fclose($this->stdout);
        fclose($this->stderr);

        parent::tearDown();
    }

    public function testItBuildsAPhpUpgradeAndDelegatesToAnalyzeWithoutCorruptingStdout(): void
    {
        $this->supplyInput([
            $this->projectPath,
            '1',
            '1',
            '8.3',
            '',
            '',
            '',
        ]);
        $command = $this->interactiveCommand();

        $exitCode = $command->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertSame('{"report":true}' . "\n", $this->contents($this->stdout));
        self::assertSame([[
            'upgrade-intel',
            'analyze',
            '--path=' . $this->resolvedProjectPath(),
            '--from-php=8.1.0',
            '--target-php=8.3.0',
            '--format=markdown',
            '--composer-mode=restricted',
        ]], $this->analyze->calls);

        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('Analyzer runtime: PHP ' . PHP_VERSION, $humanOutput);
        self::assertStringContainsString('analyzer runtime; default', $humanOutput);
        self::assertStringContainsString('Equivalent command:', $humanOutput);
        self::assertStringContainsString('--target-php=8.3.0', $humanOutput);
        self::assertStringNotContainsString('{"report":true}', $humanOutput);
    }

    public function testItBuildsALocallyVerifiedPackageUpgrade(): void
    {
        $this->supplyInput([
            $this->projectPath,
            '1',
            '2',
            '1',
            '1',
            'not a constraint',
            '^2.0',
            '',
            '',
            '',
            '',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertSame([[
            'upgrade-intel',
            'analyze',
            '--path=' . $this->resolvedProjectPath(),
            '--from-php=8.1.0',
            '--target=vendor/package:^2.0',
            '--format=markdown',
            '--composer-mode=restricted',
        ]], $this->analyze->calls);
        self::assertStringContainsString(
            'Package check [found]: Package is a root requirement in composer.json (require).',
            $this->contents($this->stderr)
        );
    }

    public function testAnalyzerRuntimePhpIsTheVisibleDefaultTarget(): void
    {
        $this->filesystem->dumpFile($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'require' => ['vendor/package' => '^1.0'],
        ], JSON_THROW_ON_ERROR));
        $this->supplyInput([
            $this->projectPath,
            '1',
            '1',
            '',
            '',
            '',
            '',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertSame([[
            'upgrade-intel',
            'analyze',
            '--path=' . $this->resolvedProjectPath(),
            '--target-php=' . $this->normalizedRuntimePhp(),
            '--format=markdown',
            '--composer-mode=restricted',
        ]], $this->analyze->calls);
        self::assertStringContainsString(
            $this->normalizedRuntimePhp() . ' (analyzer runtime; default)',
            $this->contents($this->stderr)
        );
    }

    public function testItUsesOnlyTheExplicitlySelectedPackageLookupSource(): void
    {
        $manifest = new RecordingPackageTargetValidator();
        $localCache = new RecordingPackageTargetValidator();
        $repositories = new RecordingPackageTargetValidator();
        $this->supplyInput([
            $this->projectPath,
            '2',
            '2',
            '2',
            '1',
            '^2.0',
            '',
            '',
            '',
            '',
        ]);
        $command = new WizardCommand(
            $this->analyze,
            $this->stdin,
            $this->stdout,
            $this->stderr,
            static fn ($input, $humanOutput): bool => true,
            $manifest,
            $localCache,
            $repositories
        );

        $exitCode = $command->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertSame([], $manifest->calls);
        self::assertCount(1, $localCache->calls);
        self::assertSame([], $repositories->calls);
        self::assertStringContainsString('Local Composer cache (no network)', $this->contents($this->stderr));
        self::assertContains('--composer-mode=compatible', $this->analyze->calls[0]);
    }

    public function testItOffersAndDefaultsToADiscoveredPackageConstraint(): void
    {
        $localCache = new CandidatePackageTargetValidator(
            new PackageTargetValidation(
                PackageTargetValidation::FOUND,
                'Package releases discovered.',
                ['^3.2', '3.2.1']
            )
        );
        $this->supplyInput([
            $this->projectPath,
            '2',
            '2',
            '2',
            '1',
            '',
            '',
            '',
            '',
            '',
        ]);
        $command = $this->commandWithPackageValidators(
            new RecordingPackageTargetValidator(),
            $localCache,
            new RecordingPackageTargetValidator()
        );

        $exitCode = $command->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertSame([[$this->resolvedProjectPath(), 'vendor/package']], $localCache->discoveries);
        self::assertSame([[$this->resolvedProjectPath(), 'vendor/package', '^3.2']], $localCache->validations);
        self::assertContains('--target=vendor/package:^3.2', $this->analyze->calls[0]);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('^3.2 (compatible release line)', $humanOutput);
        self::assertStringContainsString('3.2.1 (exact release)', $humanOutput);
    }

    public function testDiscoveredPackageChoicesPreserveACustomConstraintFallback(): void
    {
        $localCache = new CandidatePackageTargetValidator(
            new PackageTargetValidation(
                PackageTargetValidation::FOUND,
                'Package releases discovered.',
                ['^3.2', '3.2.1']
            )
        );
        $this->supplyInput([
            $this->projectPath,
            '2',
            '2',
            '2',
            '1',
            'c',
            '^4.0',
            '',
            '',
            '',
            '',
        ]);

        $exitCode = $this->commandWithPackageValidators(
            new RecordingPackageTargetValidator(),
            $localCache,
            new RecordingPackageTargetValidator()
        )->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertSame([[$this->resolvedProjectPath(), 'vendor/package', '^4.0']], $localCache->validations);
        self::assertContains('--target=vendor/package:^4.0', $this->analyze->calls[0]);
    }

    public function testNoMatchingVersionReturnsToConstraintSelectionBeforeReview(): void
    {
        $localCache = new CandidatePackageTargetValidator(
            new PackageTargetValidation(
                PackageTargetValidation::FOUND,
                'Package releases discovered.',
                ['^3.0']
            ),
            [
                '^3.0' => new PackageTargetValidation(
                    PackageTargetValidation::NO_MATCHING_VERSION,
                    'Package exists but no release matches.'
                ),
                '^2.0' => new PackageTargetValidation(PackageTargetValidation::FOUND, 'Target verified.'),
            ]
        );
        $this->supplyInput([
            $this->projectPath,
            '2',
            '2',
            '2',
            '1',
            '',
            'c',
            '^2.0',
            '',
            '',
            '',
            '',
        ]);

        $exitCode = $this->commandWithPackageValidators(
            new RecordingPackageTargetValidator(),
            $localCache,
            new RecordingPackageTargetValidator()
        )->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertSame(['^3.0', '^2.0'], array_column($localCache->validations, 2));
        self::assertContains('--target=vendor/package:^2.0', $this->analyze->calls[0]);
        self::assertStringContainsString('Choose another constraint.', $this->contents($this->stderr));
    }

    public function testUnverifiedDiscoveryFallsBackToManualConstraintAndMayProceed(): void
    {
        $localCache = new CandidatePackageTargetValidator(
            new PackageTargetValidation(
                PackageTargetValidation::UNVERIFIED,
                'Local metadata unavailable; enter a custom constraint.'
            ),
            [
                '^2.0' => new PackageTargetValidation(
                    PackageTargetValidation::UNVERIFIED,
                    'Constraint remains unverified; analysis may still proceed.'
                ),
            ]
        );
        $this->supplyInput([
            $this->projectPath,
            '2',
            '2',
            '2',
            '1',
            '^2.0',
            '',
            '',
            '',
            '',
        ]);

        $exitCode = $this->commandWithPackageValidators(
            new RecordingPackageTargetValidator(),
            $localCache,
            new RecordingPackageTargetValidator()
        )->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertContains('--target=vendor/package:^2.0', $this->analyze->calls[0]);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('Package discovery [unverified]', $humanOutput);
        self::assertStringContainsString('Package check [unverified]', $humanOutput);
    }

    public function testItCanSelectJsonTerminalOutputExplicitly(): void
    {
        $this->supplyInput([
            $this->projectPath,
            '1',
            '1',
            '8.3',
            '2',
            '',
            '',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertContains('--format=json', $this->analyze->calls[0]);
        self::assertStringContainsString('Report: terminal (json)', $this->contents($this->stderr));
    }

    public function testComposerExecutionPolicyHasNoImplicitDefaultAndAppearsInTheReviewedCommand(): void
    {
        $this->supplyInput([
            $this->projectPath,
            '',
            '2',
            '1',
            '8.3',
            '',
            '',
            '',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertContains('--composer-mode=compatible', $this->analyze->calls[0]);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('This security-sensitive choice has no default.', $humanOutput);
        self::assertStringContainsString(
            'Composer analysis: compatible (may use network, global configuration, and inherited credentials)',
            $humanOutput
        );
        self::assertStringContainsString("'--composer-mode=compatible'", $humanOutput);
    }

    public function testEquivalentCommandPreservesTheProjectLocalExecutable(): void
    {
        $this->supplyInput([
            $this->projectPath,
            '1',
            '1',
            '8.3',
            '',
            '',
            '',
        ]);

        $exitCode = $this->interactiveCommand()->run(['vendor/bin/upgrade-intel', 'wizard']);

        self::assertSame(0, $exitCode);
        self::assertSame('vendor/bin/upgrade-intel', $this->analyze->calls[0][0]);
        self::assertStringContainsString("'vendor/bin/upgrade-intel' analyze", $this->contents($this->stderr));
    }

    public function testEquivalentCommandQuotesWindowsShellArguments(): void
    {
        $method = new \ReflectionMethod(WizardCommand::class, 'shellArgument');
        $method->setAccessible(true);

        self::assertSame(
            "'--path=C:\\Program Files\\O''Brien'",
            $method->invoke($this->interactiveCommand(), "--path=C:\\Program Files\\O'Brien", true)
        );
    }

    public function testItKeepsTheReportOnStdoutAndAddsAnOptionalSavedCopy(): void
    {
        $copyPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php-upgrade-preflight-wizard-copy.md';
        $this->supplyInput([
            $this->projectPath,
            '1',
            '1',
            '8.3',
            '',
            'y',
            $this->projectPath . DIRECTORY_SEPARATOR . 'report.md',
            $copyPath,
            '',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);
        $validatedCopyPath = Path::canonicalize($copyPath);

        self::assertSame(0, $exitCode);
        self::assertSame('{"report":true}' . "\n", $this->contents($this->stdout));
        self::assertContains('--save-report=' . $validatedCopyPath, $this->analyze->calls[0]);
        self::assertNotContains('--output=' . $validatedCopyPath, $this->analyze->calls[0]);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('Report: terminal (markdown)', $humanOutput);
        self::assertStringContainsString('Saved copy: ' . $validatedCopyPath, $humanOutput);
        self::assertStringContainsString('outside the analyzed project', $humanOutput);
    }

    public function testItPrintsHelpAndRejectsUnexpectedWizardArguments(): void
    {
        $helpCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard', '--help']);

        self::assertSame(WizardCommand::SUCCESS, $helpCode);
        self::assertStringContainsString('Interactively builds and reviews', $this->contents($this->stdout));
        self::assertSame([], $this->analyze->calls);

        $invalidCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard', '--unexpected']);

        self::assertSame(WizardCommand::INVALID, $invalidCode);
        self::assertStringContainsString('Invalid invocation', $this->contents($this->stderr));
    }

    public function testDefaultTtyDetectionRejectsMemoryStreams(): void
    {
        $command = new WizardCommand($this->analyze, $this->stdin, $this->stdout, $this->stderr);

        $exitCode = $command->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::INVALID, $exitCode);
        self::assertSame([], $this->analyze->calls);
    }

    public function testItRecoversFromInvalidProjectCandidates(): void
    {
        $missingComposer = $this->projectPath . DIRECTORY_SEPARATOR . 'missing-composer';
        $malformedComposer = $this->projectPath . DIRECTORY_SEPARATOR . 'malformed-composer';
        $scalarComposer = $this->projectPath . DIRECTORY_SEPARATOR . 'scalar-composer';
        $this->filesystem->mkdir([$missingComposer, $malformedComposer, $scalarComposer]);
        $this->filesystem->dumpFile($malformedComposer . DIRECTORY_SEPARATOR . 'composer.json', '{');
        $this->filesystem->dumpFile($scalarComposer . DIRECTORY_SEPARATOR . 'composer.json', 'true');
        $this->supplyInput([
            $this->projectPath . DIRECTORY_SEPARATOR . 'does-not-exist',
            $missingComposer,
            $malformedComposer,
            $scalarComposer,
            $this->projectPath,
            '1',
            '1',
            '9.9',
            '',
            '',
            '',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::SUCCESS, $exitCode);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('Project path does not exist', $humanOutput);
        self::assertStringContainsString('No composer.json was found', $humanOutput);
        self::assertStringContainsString('composer.json is not valid JSON', $humanOutput);
        self::assertStringContainsString('composer.json must contain a JSON object', $humanOutput);
    }

    public function testItRepromptsForInvalidAndNonUpgradePhpTargets(): void
    {
        $this->supplyInput([
            $this->projectPath,
            'restricted',
            'php',
            'custom',
            'not-a-version',
            '8.1',
            '9.9',
            'md',
            'no',
            'yes',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::SUCCESS, $exitCode);
        self::assertContains('--target-php=9.9.0', $this->analyze->calls[0]);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('PHP target "not-a-version"', $humanOutput);
        self::assertStringContainsString('must be newer than the detected project PHP 8.1.0', $humanOutput);
    }

    public function testItHandlesBothTargetsAndRecoversAcrossMenus(): void
    {
        $requirements = ['php' => '^8.1'];
        for ($index = 1; $index <= 16; ++$index) {
            $requirements[sprintf('vendor/package-%02d', $index)] = '^1.0';
        }
        $this->filesystem->dumpFile($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'require' => $requirements,
        ], JSON_THROW_ON_ERROR));
        $repositories = new RecordingPackageTargetValidator();
        $this->supplyInput([
            $this->projectPath,
            '1',
            'invalid-mode',
            'both',
            '',
            '9',
            '3',
            '',
            '0',
            'vendor/package-01',
            '^2.0',
            'maybe',
            'yes',
            'vendor/package-16',
            '^3.0',
            'no',
            'yaml',
            'json',
            'no',
            'yes',
        ]);
        $command = $this->commandWithPackageValidators(
            new RecordingPackageTargetValidator(),
            new RecordingPackageTargetValidator(),
            $repositories
        );

        $exitCode = $command->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::SUCCESS, $exitCode);
        self::assertCount(2, $repositories->calls);
        self::assertContains('--target-php=' . $this->normalizedRuntimePhp(), $this->analyze->calls[0]);
        self::assertContains('--target=vendor/package-01:^2.0', $this->analyze->calls[0]);
        self::assertContains('--target=vendor/package-16:^3.0', $this->analyze->calls[0]);
        self::assertContains('--format=json', $this->analyze->calls[0]);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('Choose 1, 2, or 3.', $humanOutput);
        self::assertStringContainsString('... and 1 more', $humanOutput);
        self::assertStringContainsString('Enter yes or no.', $humanOutput);
        self::assertStringContainsString('Choose markdown or json.', $humanOutput);
    }

    public function testDiscoveryFailureSelectsAnotherPackageAndANumberedCandidate(): void
    {
        $localCache = new QueuedCandidatePackageTargetValidator([
            new PackageTargetValidation(PackageTargetValidation::NOT_FOUND, 'Package was not found.'),
            new PackageTargetValidation(
                PackageTargetValidation::FOUND,
                'Package releases discovered.',
                ['^3.2', '3.2.1']
            ),
        ]);
        $this->supplyInput([
            $this->projectPath,
            '2',
            '2',
            '2',
            '1',
            'vendor/other',
            '9',
            '2',
            'no',
            '',
            'no',
            'yes',
        ]);

        $exitCode = $this->commandWithPackageValidators(
            new RecordingPackageTargetValidator(),
            $localCache,
            new RecordingPackageTargetValidator()
        )->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::SUCCESS, $exitCode);
        self::assertSame(['vendor/package', 'vendor/other'], array_column($localCache->discoveries, 1));
        self::assertContains('--target=vendor/other:3.2.1', $this->analyze->calls[0]);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('Choose another package.', $humanOutput);
        self::assertStringContainsString('Choose a listed target or c', $humanOutput);
    }

    public function testItHandlesANonExactPlatformAndAnEmptyRootPackageList(): void
    {
        $this->filesystem->dumpFile($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'require' => ['php' => '^8.1', 'ext-json' => '*'],
            'require-dev' => 'not-an-object',
            'config' => ['platform' => ['php' => '^8.1']],
        ], JSON_THROW_ON_ERROR));
        $this->supplyInput([
            $this->projectPath,
            '1',
            '2',
            '',
            'vendor/package',
            '^2.0',
            'no',
            '',
            'no',
            'yes',
        ]);

        $exitCode = $this->commandWithPackageValidators(
            new RecordingPackageTargetValidator(),
            new RecordingPackageTargetValidator(),
            new RecordingPackageTargetValidator()
        )->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::SUCCESS, $exitCode);
        self::assertNotContains('--from-php=8.1.0', $this->analyze->calls[0]);
        $humanOutput = $this->contents($this->stderr);
        self::assertStringContainsString('non-exact config.platform.php value', $humanOutput);
        self::assertStringContainsString('No root packages found', $humanOutput);
    }

    public function testItUsesTheComposerConstraintAsPhpContextWhenPlatformIsAbsent(): void
    {
        $this->filesystem->dumpFile($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'require' => ['php' => '^8.1'],
        ], JSON_THROW_ON_ERROR));
        $this->supplyInput([
            $this->projectPath,
            '1',
            '1',
            '',
            '',
            '',
            '',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::SUCCESS, $exitCode);
        self::assertStringContainsString(
            'composer.json constraint; exact current version unknown',
            $this->contents($this->stderr)
        );
    }

    public function testDecliningTheReviewedPlanCancelsBeforeAnalysis(): void
    {
        $this->supplyInput([
            $this->projectPath,
            '1',
            '1',
            '9.9',
            '',
            '',
            'no',
        ]);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::CANCELLED, $exitCode);
        self::assertSame([], $this->analyze->calls);
        self::assertStringContainsString('Wizard cancelled', $this->contents($this->stderr));
    }

    public function testUnexpectedValidatorFailureReturnsFailureWithoutAnalysis(): void
    {
        $this->supplyInput([
            $this->projectPath,
            '1',
            '2',
            '',
            '1',
            '^2.0',
        ]);

        $exitCode = $this->commandWithPackageValidators(
            new ThrowingPackageTargetValidator(),
            new RecordingPackageTargetValidator(),
            new RecordingPackageTargetValidator()
        )->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::FAILURE, $exitCode);
        self::assertSame([], $this->analyze->calls);
        self::assertStringContainsString('Wizard failed before analysis: lookup exploded', $this->contents($this->stderr));
    }

    public function testItRecoversWhenAComposerJsonPassesTheFileCheckButCannotBeRead(): void
    {
        $unreadableProject = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'php-upgrade-preflight-unreadable-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($unreadableProject);
        $composerPath = $unreadableProject . DIRECTORY_SEPARATOR . 'composer.json';
        $this->filesystem->dumpFile($composerPath, '{}');
        $resolvedComposerPath = realpath($composerPath);
        self::assertNotFalse($resolvedComposerPath);
        $GLOBALS['php_upgrade_preflight_cli_unreadable_path'] = $resolvedComposerPath;
        $this->supplyInput([
            $unreadableProject,
            $this->projectPath,
            '1',
            '1',
            '9.9',
            '',
            '',
            '',
        ]);

        try {
            $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

            self::assertSame(WizardCommand::SUCCESS, $exitCode);
            self::assertStringContainsString('composer.json could not be read', $this->contents($this->stderr));
        } finally {
            unset($GLOBALS['php_upgrade_preflight_cli_unreadable_path']);
            $this->filesystem->remove($unreadableProject);
        }
    }

    public function testItFailsSafelyWhenTheWorkingDirectoryDisappears(): void
    {
        $GLOBALS['php_upgrade_preflight_cli_getcwd_failure'] = true;

        try {
            $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

            self::assertSame(WizardCommand::FAILURE, $exitCode);
            self::assertStringContainsString(
                'Unable to determine the current working directory',
                $this->contents($this->stderr)
            );
        } finally {
            unset($GLOBALS['php_upgrade_preflight_cli_getcwd_failure']);
        }
    }

    public function testItRefusesWhenInteractiveInputOrAVisibleHumanStreamIsUnavailable(): void
    {
        $command = new WizardCommand(
            $this->analyze,
            $this->stdin,
            $this->stdout,
            $this->stderr,
            static fn ($input, $humanOutput): bool => false
        );

        $exitCode = $command->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::INVALID, $exitCode);
        self::assertSame([], $this->analyze->calls);
        self::assertSame('', $this->contents($this->stdout));
        self::assertStringContainsString('requires interactive input', $this->contents($this->stderr));
    }

    public function testEofStopsBeforeAnalysis(): void
    {
        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::INVALID, $exitCode);
        self::assertSame([], $this->analyze->calls);
        self::assertStringContainsString('input ended before the plan was confirmed', $this->contents($this->stderr));
    }

    public function testCancellationStopsBeforeAnalysis(): void
    {
        $this->supplyInput(['cancel']);

        $exitCode = $this->interactiveCommand()->run(['upgrade-intel', 'wizard']);

        self::assertSame(WizardCommand::CANCELLED, $exitCode);
        self::assertSame([], $this->analyze->calls);
        self::assertStringContainsString('Wizard cancelled', $this->contents($this->stderr));
    }

    private function interactiveCommand(): WizardCommand
    {
        return new WizardCommand(
            $this->analyze,
            $this->stdin,
            $this->stdout,
            $this->stderr,
            static fn ($input, $humanOutput): bool => true
        );
    }

    private function commandWithPackageValidators(
        PackageTargetValidator $manifest,
        PackageTargetValidator $localCache,
        PackageTargetValidator $repositories
    ): WizardCommand {
        return new WizardCommand(
            $this->analyze,
            $this->stdin,
            $this->stdout,
            $this->stderr,
            static fn ($input, $humanOutput): bool => true,
            $manifest,
            $localCache,
            $repositories
        );
    }

    private function resolvedProjectPath(): string
    {
        $resolved = realpath($this->projectPath);
        self::assertNotFalse($resolved);

        return $resolved;
    }

    private function normalizedRuntimePhp(): string
    {
        return sprintf('%d.%d.%d', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION);
    }

    /** @param list<string> $lines */
    private function supplyInput(array $lines): void
    {
        fwrite($this->stdin, implode("\n", $lines) . "\n");
        rewind($this->stdin);
    }

    /** @param resource $stream */
    private function contents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        self::assertIsString($contents);

        return $contents;
    }
}

final class RecordingCommandRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $calls = [];
    /** @var resource */
    private $stdout;

    /** @param resource $stdout */
    public function __construct($stdout)
    {
        $this->stdout = $stdout;
    }

    public function run(array $argv): int
    {
        $this->calls[] = $argv;
        fwrite($this->stdout, '{"report":true}' . "\n");

        return 0;
    }
}

final class RecordingPackageTargetValidator implements PackageTargetValidator
{
    /** @var list<array{string, string, string}> */
    public array $calls = [];

    public function validate(string $projectPath, string $package, string $constraint): PackageTargetValidation
    {
        $this->calls[] = [$projectPath, $package, $constraint];

        return new PackageTargetValidation(PackageTargetValidation::FOUND, 'Package metadata verified.');
    }
}

final class CandidatePackageTargetValidator implements PackageTargetValidator, PackageTargetCandidateProvider
{
    private PackageTargetValidation $discovery;
    /** @var array<string, PackageTargetValidation> */
    private array $validationByConstraint;
    /** @var list<array{string, string}> */
    public array $discoveries = [];
    /** @var list<array{string, string, string}> */
    public array $validations = [];

    /** @param array<string, PackageTargetValidation> $validationByConstraint */
    public function __construct(PackageTargetValidation $discovery, array $validationByConstraint = [])
    {
        $this->discovery = $discovery;
        $this->validationByConstraint = $validationByConstraint;
    }

    public function discover(string $projectPath, string $package): PackageTargetValidation
    {
        $this->discoveries[] = [$projectPath, $package];

        return $this->discovery;
    }

    public function validate(string $projectPath, string $package, string $constraint): PackageTargetValidation
    {
        $this->validations[] = [$projectPath, $package, $constraint];

        return $this->validationByConstraint[$constraint]
            ?? new PackageTargetValidation(PackageTargetValidation::FOUND, 'Target verified.');
    }
}

final class QueuedCandidatePackageTargetValidator implements PackageTargetValidator, PackageTargetCandidateProvider
{
    /** @var list<PackageTargetValidation> */
    private array $discoveryResults;
    /** @var list<array{string, string}> */
    public array $discoveries = [];

    /** @param list<PackageTargetValidation> $discoveryResults */
    public function __construct(array $discoveryResults)
    {
        $this->discoveryResults = $discoveryResults;
    }

    public function discover(string $projectPath, string $package): PackageTargetValidation
    {
        $this->discoveries[] = [$projectPath, $package];
        $result = array_shift($this->discoveryResults);
        if (!$result instanceof PackageTargetValidation) {
            throw new \LogicException('No queued discovery result.');
        }

        return $result;
    }

    public function validate(string $projectPath, string $package, string $constraint): PackageTargetValidation
    {
        return new PackageTargetValidation(PackageTargetValidation::FOUND, 'Target verified.');
    }
}

final class ThrowingPackageTargetValidator implements PackageTargetValidator
{
    public function validate(string $projectPath, string $package, string $constraint): PackageTargetValidation
    {
        throw new \RuntimeException('lookup exploded');
    }
}
