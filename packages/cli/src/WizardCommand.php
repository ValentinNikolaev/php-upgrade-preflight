<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Cli;

use PhpUpgradePreflight\Core\Composer\PackageMetadataLookupMode;
use PhpUpgradePreflight\Core\Model\ComposerExecutionConfiguration;
use PhpUpgradePreflight\Core\Model\ReportFormat;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Core\Model\UpgradeTargetSet;
use PhpUpgradePreflight\Core\Reporting\ReportFileWriter;

final class WizardCommand implements CommandRunner
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;
    public const CANCELLED = 130;

    private CommandRunner $analyzeCommand;
    /** @var resource */
    private $stdin;
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;
    /** @var \Closure(resource, resource): bool */
    private \Closure $isInteractive;
    private PackageTargetValidator $manifestPackageValidator;
    private PackageTargetValidator $localCachePackageValidator;
    private PackageTargetValidator $projectRepositoriesPackageValidator;

    /**
     * @param resource|null $stdin
     * @param resource|null $stdout
     * @param resource|null $stderr
     * @param callable(resource, resource): bool|null $isInteractive
     */
    public function __construct(
        ?CommandRunner $analyzeCommand = null,
        mixed $stdin = null,
        mixed $stdout = null,
        mixed $stderr = null,
        ?callable $isInteractive = null,
        ?PackageTargetValidator $packageValidator = null,
        ?PackageTargetValidator $localCachePackageValidator = null,
        ?PackageTargetValidator $projectRepositoriesPackageValidator = null
    ) {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
        $this->analyzeCommand = $analyzeCommand ?? new AnalyzeCommand(null, $this->stdout, $this->stderr);
        $this->isInteractive = $isInteractive === null
            ? static function ($input, $humanOutput): bool {
                return function_exists('stream_isatty')
                    && stream_isatty($input)
                    && stream_isatty($humanOutput);
            }
        : \Closure::fromCallable($isInteractive);
        $this->manifestPackageValidator = $packageValidator ?? new LocalPackageTargetValidator();
        $this->localCachePackageValidator = $localCachePackageValidator
            ?? new ComposerLookupPackageTargetValidator(PackageMetadataLookupMode::LOCAL_CACHE_ONLY);
        $this->projectRepositoriesPackageValidator = $projectRepositoriesPackageValidator
            ?? new ComposerLookupPackageTargetValidator(PackageMetadataLookupMode::PROJECT_REPOSITORIES);
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
            fwrite($this->stdout, $this->usage());

            return self::SUCCESS;
        }

        if (count($argv) !== 2 || $argv[1] !== 'wizard') {
            $this->diagnostic('Invalid invocation: use "upgrade-intel wizard" without options.');

            return self::INVALID;
        }

        if (!(($this->isInteractive)($this->stdin, $this->stderr))) {
            $this->diagnostic(
                'The wizard requires interactive input and a visible diagnostic terminal. '
                . 'Use "upgrade-intel analyze" with explicit options in scripts and redirected sessions.'
            );

            return self::INVALID;
        }

        try {
            return $this->interact($argv[0]);
        } catch (WizardInputException $exception) {
            $this->diagnostic($exception->getMessage());

            return $exception->isCancellation() ? self::CANCELLED : self::INVALID;
        } catch (\Throwable $exception) {
            $this->diagnostic('Wizard failed before analysis: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function interact(string $invokedAs): int
    {
        $this->write("PHP Upgrade Preflight wizard\n\n");
        $project = $this->chooseProject();
        $composer = $project['composer'];
        $projectPath = $project['path'];
        $currentPhp = $this->currentPhp($composer);

        $this->showProjectContext($projectPath, $composer, $currentPhp);
        $composerMode = $this->chooseComposerExecutionMode();
        $mode = $this->chooseMode();
        $targetPhp = in_array($mode, ['php', 'both'], true)
            ? $this->chooseTargetPhp($currentPhp)
            : null;
        $targets = in_array($mode, ['package', 'both'], true)
            ? $this->choosePackageTargets($projectPath, $composer)
            : [];
        $output = $this->chooseOutput($projectPath);

        $arguments = [$invokedAs, 'analyze', '--path=' . $projectPath];
        if ($currentPhp['exact'] !== null) {
            $arguments[] = '--from-php=' . $currentPhp['exact'];
        }
        if ($targetPhp !== null) {
            $arguments[] = '--target-php=' . $targetPhp;
        }
        foreach ($targets as $target) {
            $arguments[] = '--target=' . $target->package() . ':' . $target->constraint();
        }
        $arguments[] = '--format=' . $output['format'];
        $arguments[] = '--composer-mode=' . $composerMode;
        if ($output['path'] !== null) {
            $arguments[] = '--save-report=' . $output['path'];
        }

        $this->reviewPlan(
            $projectPath,
            $currentPhp,
            $targetPhp,
            $targets,
            $output,
            $composerMode,
            $arguments
        );
        if (!$this->confirm('Run analysis? [Y/n]: ', true)) {
            throw WizardInputException::cancelled();
        }

        $this->write("\nStarting analysis...\n");

        return $this->analyzeCommand->run($arguments);
    }

    /** @return array{path: string, composer: array<string, mixed>} */
    private function chooseProject(): array
    {
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            throw new \RuntimeException('Unable to determine the current working directory.');
        }

        while (true) {
            $answer = $this->prompt(sprintf('Project path [%s]: ', $workingDirectory));
            $path = $answer === '' ? $workingDirectory : $answer;
            $resolved = realpath($path);
            if ($resolved === false || !is_dir($resolved)) {
                $this->write("Project path does not exist. Enter a directory containing composer.json.\n");
                continue;
            }

            $composerPath = $resolved . DIRECTORY_SEPARATOR . 'composer.json';
            if (!is_file($composerPath)) {
                $this->write("No composer.json was found in that directory.\n");
                continue;
            }

            $contents = file_get_contents($composerPath);
            if ($contents === false) {
                $this->write("composer.json could not be read.\n");
                continue;
            }

            try {
                $composer = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $this->write('composer.json is not valid JSON: ' . $exception->getMessage() . "\n");
                continue;
            }
            if (!is_array($composer)) {
                $this->write("composer.json must contain a JSON object.\n");
                continue;
            }

            /** @var array<string, mixed> $composer */
            return ['path' => $resolved, 'composer' => $composer];
        }
    }

    /**
     * @param array<string, mixed> $composer
     * @param array{exact: ?string, display: string, provenance: string} $currentPhp
     */
    private function showProjectContext(string $projectPath, array $composer, array $currentPhp): void
    {
        $this->write("\nProject detected\n");
        $this->write('  Path: ' . $projectPath . "\n");
        $this->write('  Analyzer runtime: PHP ' . PHP_VERSION . "\n");
        $this->write(sprintf(
            "  Project PHP: %s (%s)\n",
            $currentPhp['display'],
            $currentPhp['provenance']
        ));
        $this->write(sprintf("  Root packages: %d\n\n", count($this->rootRequirements($composer))));
    }

    private function chooseMode(): string
    {
        $this->write("What do you want to analyze?\n");
        $this->write("  1) Upgrade PHP\n");
        $this->write("  2) Upgrade Composer package(s)\n");
        $this->write("  3) Upgrade PHP and Composer package(s)\n");

        while (true) {
            $answer = strtolower($this->prompt('Choose [1]: '));
            if ($answer === '' || in_array($answer, ['1', 'php'], true)) {
                return 'php';
            }
            if (in_array($answer, ['2', 'package', 'packages'], true)) {
                return 'package';
            }
            if (in_array($answer, ['3', 'both'], true)) {
                return 'both';
            }

            $this->write("Choose 1, 2, or 3.\n");
        }
    }

    private function chooseComposerExecutionMode(): string
    {
        $this->write("How should Composer run the analysis?\n");
        $this->write("  1) Restricted (fresh state, no inherited credentials, best-effort offline)\n");
        $this->write("  2) Compatible (may use network, global configuration, and inherited credentials)\n");

        while (true) {
            $answer = strtolower($this->prompt('Choose 1 or 2 (no default): '));
            if (in_array($answer, ['1', ComposerExecutionConfiguration::MODE_RESTRICTED], true)) {
                return ComposerExecutionConfiguration::MODE_RESTRICTED;
            }
            if (in_array($answer, ['2', ComposerExecutionConfiguration::MODE_COMPATIBLE], true)) {
                return ComposerExecutionConfiguration::MODE_COMPATIBLE;
            }

            $this->write("Choose restricted or compatible. This security-sensitive choice has no default.\n");
        }
    }

    /** @param array{exact: ?string, display: string, provenance: string} $currentPhp */
    private function chooseTargetPhp(array $currentPhp): string
    {
        $runtime = sprintf('%d.%d.%d', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION);
        $choices = $this->phpTargetChoices($runtime, $currentPhp);

        $this->write("\nChoose a target PHP version.\n");
        foreach ($choices as $index => $choice) {
            $suffix = $index === 0 ? ' (analyzer runtime; default)' : '';
            $this->write(sprintf("  %d) %s%s\n", $index + 1, $choice, $suffix));
        }
        $this->write("  c) Enter a custom exact version\n");

        while (true) {
            $answer = strtolower($this->prompt('Choose [1]: '));
            if ($answer === '') {
                $answer = '1';
            }

            if (in_array($answer, ['c', 'custom'], true)) {
                $answer = $this->prompt('Custom target PHP version (for example 8.4): ');
            } elseif (ctype_digit($answer) && isset($choices[(int) $answer - 1])) {
                $answer = $choices[(int) $answer - 1];
            }

            $target = $this->normalizePhpTarget($answer);
            if ($target === null) {
                continue;
            }
            if ($currentPhp['exact'] !== null && version_compare($target, $currentPhp['exact'], '<=')) {
                $this->write(sprintf(
                    "Target PHP %s must be newer than the detected project PHP %s. Choose another option or custom version.\n",
                    $target,
                    $currentPhp['exact']
                ));
                continue;
            }

            return $target;
        }
    }

    /**
     * @param array{exact: ?string, display: string, provenance: string} $currentPhp
     * @return list<string>
     */
    private function phpTargetChoices(string $runtime, array $currentPhp): array
    {
        $choices = [$runtime => true];
        $baseline = $currentPhp['exact'];
        if ($baseline === null && preg_match('/(?:^|[^0-9])(\d+)\.(\d+)/', $currentPhp['display'], $matches) === 1) {
            $baseline = sprintf('%d.%d.0', (int) $matches[1], (int) $matches[2]);
        }

        if ($baseline !== null) {
            [$baselineMajor, $baselineMinor] = array_map('intval', explode('.', $baseline));
            [$runtimeMajor, $runtimeMinor] = array_map('intval', explode('.', $runtime));
            if ($baselineMajor === $runtimeMajor && $baselineMinor < $runtimeMinor) {
                for ($minor = $baselineMinor + 1; $minor < $runtimeMinor; ++$minor) {
                    $choices[sprintf('%d.%d.0', $baselineMajor, $minor)] = true;
                }
            }
        }

        return array_keys($choices);
    }

    private function normalizePhpTarget(string $answer): ?string
    {
        try {
            return (new UpgradeTargetSet([], $answer))->targetPhp();
        } catch (\InvalidArgumentException $exception) {
            $this->write($exception->getMessage() . "\n");

            return null;
        }
    }

    /**
     * @param array<string, mixed> $composer
     * @return list<UpgradeTarget>
     */
    private function choosePackageTargets(string $projectPath, array $composer): array
    {
        $validator = $this->choosePackageValidationSource();
        $requirements = $this->rootRequirements($composer);
        $choices = array_keys($requirements);
        $visibleChoices = array_slice($choices, 0, 15);

        $this->write("\nRoot Composer packages\n");
        foreach ($visibleChoices as $index => $package) {
            $this->write(sprintf("  %d) %s (%s)\n", $index + 1, $package, $requirements[$package]));
        }
        if (count($choices) > count($visibleChoices)) {
            $this->write(sprintf("  ... and %d more; enter a package name directly.\n", count($choices) - count($visibleChoices)));
        }
        if ($visibleChoices === []) {
            $this->write("  No root packages found; enter a package name directly.\n");
        }

        $firstTarget = $this->choosePackageTarget($projectPath, $visibleChoices, $validator);
        /** @var array<string, UpgradeTarget> $targets */
        $targets = [$firstTarget->package() => $firstTarget];
        while ($this->confirm('Add another package target? [y/N]: ', false)) {
            $target = $this->choosePackageTarget($projectPath, $visibleChoices, $validator);
            $targets[$target->package()] = $target;
        }

        ksort($targets, SORT_STRING);

        return array_values($targets);
    }

    private function choosePackageValidationSource(): PackageTargetValidator
    {
        $this->write("\nHow should package metadata be checked?\n");
        $this->write("  1) composer.json only (default; no Composer lookup)\n");
        $this->write("  2) Local Composer cache (no network)\n");
        $this->write("  3) Configured project repositories (may use network and credentials)\n");

        while (true) {
            $answer = $this->prompt('Choose [1]: ');
            if ($answer === '' || $answer === '1') {
                return $this->manifestPackageValidator;
            }
            if ($answer === '2') {
                return $this->localCachePackageValidator;
            }
            if ($answer === '3') {
                return $this->projectRepositoriesPackageValidator;
            }

            $this->write("Choose 1, 2, or 3.\n");
        }
    }

    /** @return array{format: string, path: ?string} */
    private function chooseOutput(string $projectPath): array
    {
        $format = $this->chooseReportFormat();
        if (!$this->confirm('Save an additional report copy to a file? [y/N]: ', false)) {
            return ['format' => $format, 'path' => null];
        }

        while (true) {
            $path = $this->prompt('Report copy path (must be outside the project): ');
            try {
                $validated = (new ReportFileWriter())->validateDestination($projectPath, $path);

                return ['format' => $format, 'path' => $validated];
            } catch (\InvalidArgumentException $exception) {
                $this->write($exception->getMessage() . "\n");
            }
        }
    }

    private function chooseReportFormat(): string
    {
        $this->write("\nWhich report format should be printed to the terminal?\n");
        $this->write("  1) Markdown (default)\n");
        $this->write("  2) JSON\n");

        while (true) {
            $answer = strtolower($this->prompt('Choose [1]: '));
            if ($answer === '' || in_array($answer, ['1', 'markdown', 'md'], true)) {
                return ReportFormat::MARKDOWN;
            }
            if (in_array($answer, ['2', 'json'], true)) {
                return ReportFormat::JSON;
            }

            $this->write("Choose markdown or json.\n");
        }
    }

    /** @param list<string> $visibleChoices */
    private function choosePackageTarget(
        string $projectPath,
        array $visibleChoices,
        PackageTargetValidator $validator
    ): UpgradeTarget {
        while (true) {
            $package = $this->choosePackageName($visibleChoices);
            $candidateConstraints = [];
            if ($validator instanceof PackageTargetCandidateProvider) {
                $discovery = $validator->discover($projectPath, $package);
                $this->write(sprintf(
                    "Package discovery [%s]: %s\n",
                    $discovery->status(),
                    $discovery->message()
                ));
                if (!$discovery->permitsAnalysis()) {
                    $this->write("Choose another package.\n");
                    continue;
                }
                $candidateConstraints = $discovery->candidateConstraints();
            }

            while (true) {
                $constraint = $this->choosePackageConstraint($package, $candidateConstraints);
                try {
                    $target = new UpgradeTarget($package, $constraint);
                } catch (\InvalidArgumentException $exception) {
                    $this->write($exception->getMessage() . "\n");
                    continue;
                }

                $validation = $validator->validate(
                    $projectPath,
                    $target->package(),
                    $target->constraint()
                );
                $this->write(sprintf(
                    "Package check [%s]: %s\n",
                    $validation->status(),
                    $validation->message()
                ));
                if ($validation->permitsAnalysis()) {
                    return $target;
                }

                $this->write("Choose another constraint.\n");
            }
        }
    }

    /** @param list<string> $candidateConstraints */
    private function choosePackageConstraint(string $package, array $candidateConstraints): string
    {
        if ($candidateConstraints === []) {
            return $this->prompt(sprintf('Custom target constraint for %s: ', $package));
        }

        $this->write(sprintf("Available targets for %s\n", $package));
        foreach ($candidateConstraints as $index => $constraint) {
            $kind = str_starts_with($constraint, '^') ? 'compatible release line' : 'exact release';
            $this->write(sprintf("  %d) %s (%s)\n", $index + 1, $constraint, $kind));
        }
        $this->write("  c) Enter a custom constraint\n");

        while (true) {
            $answer = strtolower($this->prompt('Choose [1]: '));
            if ($answer === '') {
                return $candidateConstraints[0];
            }
            if (in_array($answer, ['c', 'custom'], true)) {
                return $this->prompt(sprintf('Custom target constraint for %s: ', $package));
            }
            if (ctype_digit($answer) && isset($candidateConstraints[(int) $answer - 1])) {
                return $candidateConstraints[(int) $answer - 1];
            }

            $this->write("Choose a listed target or c for a custom constraint.\n");
        }
    }

    /** @param list<string> $visibleChoices */
    private function choosePackageName(array $visibleChoices): string
    {
        while (true) {
            $answer = strtolower($this->prompt('Package name or number: '));
            if (ctype_digit($answer)) {
                $index = (int) $answer - 1;
                if (isset($visibleChoices[$index])) {
                    return $visibleChoices[$index];
                }
            }
            if ($answer !== '') {
                try {
                    return (new UpgradeTarget($answer, '*'))->package();
                } catch (\InvalidArgumentException $exception) {
                    $this->write($exception->getMessage() . "\n");
                    continue;
                }
            }

            $this->write("Enter a listed number or a Composer package name such as vendor/package.\n");
        }
    }

    /**
     * @param array{exact: ?string, display: string, provenance: string} $currentPhp
     * @param list<UpgradeTarget> $targets
     * @param array{format: string, path: ?string} $output
     * @param list<string> $arguments
     */
    private function reviewPlan(
        string $projectPath,
        array $currentPhp,
        ?string $targetPhp,
        array $targets,
        array $output,
        string $composerMode,
        array $arguments
    ): void {
        $this->write("\nAnalysis plan\n");
        $this->write('  Project: ' . $projectPath . "\n");
        $this->write(sprintf("  Current PHP: %s (%s)\n", $currentPhp['display'], $currentPhp['provenance']));
        $this->write('  Target PHP: ' . ($targetPhp ?? 'unchanged') . "\n");
        if ($targets === []) {
            $this->write("  Package targets: none\n");
        } else {
            $this->write("  Package targets:\n");
            foreach ($targets as $target) {
                $this->write(sprintf("    - %s:%s\n", $target->package(), $target->constraint()));
            }
        }
        $this->write(sprintf(
            "  Report: terminal (%s)\n",
            $output['format']
        ));
        $this->write('  Saved copy: ' . ($output['path'] ?? 'none') . "\n");
        $this->write('  Composer analysis: ' . $this->composerExecutionSummary($composerMode) . "\n");
        $this->write("  Project files will not be modified. Composer analysis uses temporary workspaces.\n");
        $this->write("\nEquivalent command:\n  " . $this->shellCommand($arguments) . "\n\n");
    }

    private function composerExecutionSummary(string $mode): string
    {
        if ($mode === ComposerExecutionConfiguration::MODE_RESTRICTED) {
            return 'restricted (fresh state, no inherited credentials, best-effort offline)';
        }

        return 'compatible (may use network, global configuration, and inherited credentials)';
    }

    /** @param list<string> $arguments */
    private function shellCommand(array $arguments): string
    {
        return implode(' ', array_map(
            fn (string $argument): string => $this->shellArgument($argument, DIRECTORY_SEPARATOR === '\\'),
            $arguments
        ));
    }

    private function shellArgument(string $argument, bool $windows): string
    {
        if ($argument === 'upgrade-intel' || $argument === 'analyze') {
            return $argument;
        }

        if ($windows) {
            return "'" . str_replace("'", "''", $argument) . "'";
        }

        return "'" . str_replace("'", "'\\''", $argument) . "'";
    }

    /**
     * @param array<string, mixed> $composer
     * @return array{exact: ?string, display: string, provenance: string}
     */
    private function currentPhp(array $composer): array
    {
        $config = $composer['config'] ?? null;
        $platform = is_array($config) ? ($config['platform'] ?? null) : null;
        $platformPhp = is_array($platform) ? ($platform['php'] ?? null) : null;
        if (is_string($platformPhp)) {
            try {
                $exact = (new UpgradeTargetSet([], $platformPhp))->targetPhp();
                if ($exact !== null) {
                    return [
                        'exact' => $exact,
                        'display' => $exact,
                        'provenance' => 'config.platform.php',
                    ];
                }
            } catch (\InvalidArgumentException) {
                // A non-exact platform value is still useful context, but it is
                // not safe to pass to --from-php as an observed runtime.
            }

            return [
                'exact' => null,
                'display' => $platformPhp,
                'provenance' => 'non-exact config.platform.php value',
            ];
        }

        $requirements = $composer['require'] ?? null;
        $constraint = is_array($requirements) ? ($requirements['php'] ?? null) : null;
        if (is_string($constraint)) {
            return [
                'exact' => null,
                'display' => $constraint,
                'provenance' => 'composer.json constraint; exact current version unknown',
            ];
        }

        return [
            'exact' => null,
            'display' => 'unknown',
            'provenance' => 'not declared locally',
        ];
    }

    /**
     * @param array<string, mixed> $composer
     * @return array<string, string>
     */
    private function rootRequirements(array $composer): array
    {
        $requirements = [];
        foreach (['require', 'require-dev'] as $section) {
            $values = $composer[$section] ?? null;
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $package => $constraint) {
                if (!is_string($package) || !is_string($constraint) || strtolower($package) === 'php') {
                    continue;
                }
                if (!str_contains($package, '/')) {
                    continue;
                }
                $requirements[strtolower($package)] = $constraint;
            }
        }
        ksort($requirements, SORT_STRING);

        return $requirements;
    }

    private function confirm(string $question, bool $default): bool
    {
        while (true) {
            $answer = strtolower($this->prompt($question));
            if ($answer === '') {
                return $default;
            }
            if (in_array($answer, ['y', 'yes'], true)) {
                return true;
            }
            if (in_array($answer, ['n', 'no'], true)) {
                return false;
            }

            $this->write("Enter yes or no.\n");
        }
    }

    private function prompt(string $question): string
    {
        $this->write($question);
        $answer = fgets($this->stdin);
        if ($answer === false) {
            throw WizardInputException::endOfInput();
        }

        $answer = trim($answer);
        if (in_array(strtolower($answer), ['cancel', 'quit', 'q'], true)) {
            throw WizardInputException::cancelled();
        }

        return $answer;
    }

    private function write(string $message): void
    {
        fwrite($this->stderr, $message);
    }

    private function diagnostic(string $message): void
    {
        fwrite($this->stderr, $message . PHP_EOL);
    }

    private function usage(): string
    {
        return "Usage:\n"
            . "  upgrade-intel wizard\n\n"
            . "Interactively builds and reviews an upgrade analysis request.\n"
            . "The wizard requires an interactive input and diagnostic terminal.\n"
            . "Composer execution policy is an explicit choice with no default.\n"
            . "Use upgrade-intel analyze with explicit options for automation.\n"
            . "Enter 'cancel' at any prompt to exit without running analysis.\n";
    }
}
