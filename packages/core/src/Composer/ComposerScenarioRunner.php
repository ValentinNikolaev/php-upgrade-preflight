<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Composer;

use Composer\Semver\Semver;
use PhpUpgradePreflight\Core\Filesystem\TemporaryWorkspaceManager;
use PhpUpgradePreflight\Core\Filesystem\WorkspaceCleanupException;
use PhpUpgradePreflight\Core\Filesystem\WorkspaceManager;
use PhpUpgradePreflight\Core\Model\CandidateLockEvidence;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\ComposerDiagnostic;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\Scenario;
use PhpUpgradePreflight\Core\Model\ScenarioResult;
use PhpUpgradePreflight\Core\Model\TargetPlatform;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Support\PathExposurePolicy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class ComposerScenarioRunner
{
    public const SCENARIO_TIMEOUT_SECONDS = 300;
    private const COMPLETE_PLATFORM_MIN_COMPOSER_VERSION = '2.2.0';
    private const LOCKED_DIAGNOSTIC_MIN_COMPOSER_VERSION = '2.4.0';
    /** @var list<string> */
    private const COMPOSER_SAFETY_OPTIONS = ['--no-scripts', '--no-plugins'];

    private WorkspaceManager $workspaces;
    private JsonFileReader $reader;
    /** @var \Closure(list<string>, string): array{exit_code: int, stdout: string, stderr: string} */
    private \Closure $processRunner;
    /** @var \Closure(): ?string */
    private \Closure $composerVersionResolver;
    /** @var \Closure(list<string>, string, array<string, string|false>): array{exit_code: int, stdout: string, stderr: string} */
    private \Closure $composerVersionProcessRunner;
    /** @var \Closure(): float */
    private \Closure $clock;
    /** @var \Closure(): ?array<string, string> */
    private \Closure $platformPackageResolver;
    private bool $composerVersionResolved = false;
    private ?string $composerVersion = null;
    private bool $platformPackagesResolved = false;
    /** @var ?array<string, string> */
    private ?array $platformPackages = null;
    /** @var array<string, ComposerDiagnostic> */
    private array $diagnosticCache = [];

    /**
     * @param null|callable(list<string>, string): array{exit_code: int, stdout: string, stderr: string} $processRunner
     * @param null|callable(): ?string $composerVersionResolver
     * @param null|callable(): float $clock
     * @param null|callable(list<string>, string, array<string, string|false>): array{exit_code: int, stdout: string, stderr: string} $composerVersionProcessRunner
     * @param null|callable(): ?array<string, string> $platformPackageResolver
     */
    public function __construct(
        ?WorkspaceManager $workspaces = null,
        ?JsonFileReader $reader = null,
        ?callable $processRunner = null,
        ?callable $composerVersionResolver = null,
        ?callable $clock = null,
        ?callable $composerVersionProcessRunner = null,
        ?callable $platformPackageResolver = null
    ) {
        $this->workspaces = $workspaces ?? new TemporaryWorkspaceManager();
        $this->reader = $reader ?? new JsonFileReader();
        $this->processRunner = $processRunner === null
            ? \Closure::fromCallable([$this, 'runProcess'])
            : \Closure::fromCallable($processRunner);
        $this->composerVersionProcessRunner = $composerVersionProcessRunner === null
            ? \Closure::fromCallable([$this, 'runVersionProcess'])
            : \Closure::fromCallable($composerVersionProcessRunner);
        $this->composerVersionResolver = $composerVersionResolver === null
            ? ($processRunner === null || $composerVersionProcessRunner !== null
                ? \Closure::fromCallable([$this, 'detectComposerVersion'])
                : static fn (): ?string => null)
            : \Closure::fromCallable($composerVersionResolver);
        $this->clock = $clock === null
            ? static fn (): float => microtime(true)
            : \Closure::fromCallable($clock);
        $this->platformPackageResolver = $platformPackageResolver === null
            ? \Closure::fromCallable([$this, 'detectComposerPlatformPackages'])
            : \Closure::fromCallable($platformPackageResolver);
    }

    public function run(
        ProjectState $project,
        UpgradeRequest $request,
        Scenario $scenario,
        ?TargetPlatform $platform = null
    ): ScenarioResult {
        $platform = $platform ?? TargetPlatform::fromRequest($request, $project);
        $tempPath = null;
        $repositoryPaths = PathExposurePolicy::localRepositoryPaths(
            $project->composerJson()->data(),
            $project->path()
        );
        $command = $this->buildCommand($scenario);
        $composerVersion = $this->resolveComposerVersion();
        $analyzerPlatformPackages = null;
        if (!$scenario->isBaselineValidation() || $platform->isCompleteProfile()) {
            $capabilityFailure = $this->platformCapabilityFailure($platform, $composerVersion);
            if ($capabilityFailure !== null) {
                return $this->operationalResult($scenario, $composerVersion, $command, $capabilityFailure);
            }

            if ($platform->needsToolchainValidation()) {
                $analyzerPlatformPackages = $this->resolvePlatformPackages();
                if ($analyzerPlatformPackages === null) {
                    return $this->operationalResult(
                        $scenario,
                        $composerVersion,
                        $command,
                        $platform->isCompleteProfile()
                            ? 'Composer platform inventory could not be determined; the complete target-platform profile was not weakened to partial coverage.'
                            : 'Composer platform inventory could not be determined, so toolchain-bound target-platform values could not be validated.'
                    );
                }
                $toolchainFailure = $platform->toolchainValidationFailure($analyzerPlatformPackages);
                if ($toolchainFailure !== null) {
                    return $this->operationalResult(
                        $scenario,
                        $composerVersion,
                        $command,
                        $toolchainFailure
                    );
                }
            }
        }
        $durationMs = 0;
        $startedAt = null;
        $phase = 'workspace';
        $cleanupFailedDuringCreation = false;

        try {
            $tempPath = $this->workspaces->createFromProject($project->path());
            $this->seedProjectState($tempPath, $project);
            $phase = 'preparation';
            $candidateManifest = $project->composerJson();
            if (!$scenario->isBaselineValidation()) {
                $candidateManifest = $this->applyTemporaryComposerChanges(
                    $tempPath,
                    $project,
                    $scenario,
                    $platform,
                    $analyzerPlatformPackages
                );
            }
            $phase = 'process';
            $startedAt = ($this->clock)();
            $process = ($this->processRunner)($command, $tempPath);
            $process = $this->sanitizeProcessResult(
                $process,
                $project->path(),
                $tempPath,
                $repositoryPaths
            );
            $durationMs = $this->elapsedMilliseconds($startedAt);

            $lock = null;
            $candidateLockEvidence = null;
            $candidateProjectState = null;
            $lockPath = $tempPath . DIRECTORY_SEPARATOR . 'composer.lock';
            $phase = 'lockfile';
            if ($process['exit_code'] === 0 && is_file($lockPath)) {
                $lock = new ComposerLock($this->reader->read($lockPath), array_keys($candidateManifest->rootRequirements()));
                $candidateLockEvidence = CandidateLockEvidence::fromFile($lockPath, $lock);
                $candidateProjectState = new ProjectState(
                    $project->path(),
                    $candidateManifest,
                    $lock
                );
            }

            $failureType = null;
            $outcome = ScenarioResult::OUTCOME_SUCCESS;
            $diagnostics = [];
            if ($process['exit_code'] !== 0) {
                if ($this->indicatesMissingComposer($process['exit_code'], $process['stdout'], $process['stderr'])) {
                    $failureType = ScenarioResult::FAILURE_OPERATIONAL;
                    $outcome = ScenarioResult::OUTCOME_COMPOSER_MISSING;
                } elseif ($scenario->isBaselineValidation()) {
                    $failureType = ScenarioResult::FAILURE_VALIDATION;
                    $outcome = ScenarioResult::OUTCOME_VALIDATION_FAILURE;
                } else {
                    $failureType = $this->isSolverFailure($process['stdout'], $process['stderr'])
                        ? ScenarioResult::FAILURE_SOLVER
                        : ScenarioResult::FAILURE_OPERATIONAL;
                    $outcome = $failureType === ScenarioResult::FAILURE_SOLVER
                        ? ScenarioResult::OUTCOME_SOLVER_FAILURE
                        : ScenarioResult::OUTCOME_PROCESS_FAILURE;
                }
            } elseif ($lock === null) {
                $failureType = ScenarioResult::FAILURE_OPERATIONAL;
                $outcome = ScenarioResult::OUTCOME_LOCKFILE_MISSING;
            }

            if ($failureType === ScenarioResult::FAILURE_SOLVER) {
                $diagnostics = $this->runTargetDiagnostics(
                    $project,
                    $request,
                    $scenario,
                    $tempPath,
                    $platform,
                    $repositoryPaths
                );
            }

            $result = new ScenarioResult(
                $scenario,
                $process['exit_code'],
                $process['stdout'],
                $process['stderr'],
                $lock,
                $request->debug() ? $tempPath : null,
                $failureType,
                $composerVersion,
                $command,
                $durationMs,
                $candidateLockEvidence,
                $diagnostics,
                $outcome,
                $request->debug(),
                $candidateProjectState
            );
        } catch (\Throwable $exception) {
            if ($exception instanceof WorkspaceCleanupException) {
                $tempPath = $exception->workspacePath();
                $cleanupFailedDuringCreation = true;
            }

            if ($startedAt !== null && $durationMs === 0) {
                $durationMs = $this->elapsedMilliseconds($startedAt);
            }

            $stdout = '';
            $stderr = $exception->getMessage();
            $exitCode = 1;
            if ($exception instanceof ProcessTimedOutException) {
                $timedOutProcess = $exception->getProcess();
                try {
                    $stdout = $timedOutProcess->getOutput();
                    $stderr = $timedOutProcess->getErrorOutput();
                } catch (\Throwable) {
                    $stdout = '';
                    $stderr = '';
                }
                $stderr = trim($stderr . PHP_EOL . $exception->getMessage());
                $exitCode = $timedOutProcess->getExitCode() ?? 1;
            }

            $stdout = PathExposurePolicy::redactComposerText(
                $stdout,
                $project->path(),
                $tempPath,
                $repositoryPaths
            );
            $stderr = PathExposurePolicy::redactComposerText(
                $stderr,
                $project->path(),
                $tempPath,
                $repositoryPaths
            );

            $result = new ScenarioResult(
                $scenario,
                $exitCode,
                $stdout,
                $stderr,
                null,
                $request->debug() || $cleanupFailedDuringCreation ? $tempPath : null,
                ScenarioResult::FAILURE_OPERATIONAL,
                $composerVersion,
                $command,
                $durationMs,
                null,
                [],
                $this->exceptionOutcome($exception, $phase),
                $request->debug()
            );
        }

        if (!$request->debug() && $tempPath !== null && !$cleanupFailedDuringCreation) {
            try {
                $this->workspaces->remove($tempPath);
            } catch (\Throwable $exception) {
                return new ScenarioResult(
                    $scenario,
                    $result->exitCode(),
                    $result->stdout(),
                    trim($result->stderr() . PHP_EOL . sprintf(
                        'Temporary workspace cleanup failed: %s',
                        PathExposurePolicy::redactComposerText(
                            $exception->getMessage(),
                            $project->path(),
                            $tempPath,
                            $repositoryPaths
                        )
                    )),
                    null,
                    $tempPath,
                    ScenarioResult::FAILURE_OPERATIONAL,
                    $result->composerVersion(),
                    $result->command(),
                    $result->durationMs(),
                    $result->candidateLockEvidence(),
                    $result->diagnostics(),
                    ScenarioResult::OUTCOME_CLEANUP_FAILURE,
                    $request->debug()
                );
            }
        }

        return $result;
    }

    public function resetDiagnosticCache(): void
    {
        $this->diagnosticCache = [];
    }

    /** @return list<string> */
    private function buildCommand(Scenario $scenario): array
    {
        if ($scenario->isBaselineValidation()) {
            return array_merge([
                'composer',
                'validate',
                '--check-lock',
                '--no-check-publish',
            ], self::COMPOSER_SAFETY_OPTIONS, [
                '--no-interaction',
            ]);
        }

        $command = ['composer', 'update'];

        foreach ($scenario->targets()->packageTargets() as $target) {
            $command[] = $target->package();
        }

        if ($scenario->withAllDependencies()) {
            $command[] = '--with-all-dependencies';
        }

        if ($scenario->minimalChanges()) {
            $command[] = '--minimal-changes';
        }

        $command = array_merge($command, self::COMPOSER_SAFETY_OPTIONS);
        $command[] = '--no-install';
        $command[] = '--no-audit';
        $command[] = '--no-progress';
        $command[] = '--no-interaction';

        return $command;
    }

    /**
     * @param list<string> $command
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runProcess(array $command, string $workingDirectory): array
    {
        $process = new Process(
            $command,
            $workingDirectory,
            ['COMPOSER_NO_INTERACTION' => '1'],
            null,
            self::SCENARIO_TIMEOUT_SECONDS
        );
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }

    private function detectComposerVersion(): ?string
    {
        $process = $this->runComposerMetadataCommand(array_merge(
            ['composer', '--version', '--no-ansi'],
            self::COMPOSER_SAFETY_OPTIONS,
            ['--no-interaction']
        ));

        if ($process['exit_code'] !== 0) {
            return null;
        }

        $output = trim($process['stdout'] . "\n" . $process['stderr']);
        if (preg_match('/\bComposer(?:\s+version)?\s+([^\s]+)/i', $output, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /** @return ?array<string, string> */
    private function detectComposerPlatformPackages(): ?array
    {
        $process = $this->runComposerMetadataCommand(array_merge(
            ['composer', 'show', '--platform', '--format=json'],
            self::COMPOSER_SAFETY_OPTIONS,
            ['--no-interaction']
        ));
        if ($process['exit_code'] !== 0) {
            return null;
        }

        try {
            $decoded = json_decode($process['stdout'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }
        $inventory = $decoded['platform'] ?? $decoded['installed'] ?? null;
        if (!is_array($inventory)) {
            return null;
        }

        $packages = [];
        foreach ($inventory as $package) {
            if (!is_array($package)
                || !isset($package['name'], $package['version'])
                || !is_string($package['name'])
                || !is_string($package['version'])
            ) {
                return null;
            }
            $name = strtolower(trim($package['name']));
            if (TargetPlatform::isSupportedPackageName($name)) {
                $packages[$name] = trim($package['version']);
            }
        }
        ksort($packages, SORT_STRING);

        return $packages;
    }

    /**
     * @param list<string> $command
     * @param array<string, string|false> $environment
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runVersionProcess(array $command, string $workingDirectory, array $environment): array
    {
        $process = new Process($command, $workingDirectory, $environment, null, 30);
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }

    /**
     * @param list<string> $command
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runComposerMetadataCommand(array $command): array
    {
        $workingDirectory = $this->createComposerProbeDirectory();

        try {
            return ($this->composerVersionProcessRunner)(
                $command,
                $workingDirectory,
                [
                    'COMPOSER_NO_INTERACTION' => '1',
                    'COMPOSER' => false,
                    'COMPOSER_HOME' => $workingDirectory,
                ]
            );
        } finally {
            (new Filesystem())->remove($workingDirectory);
        }
    }

    private function createComposerProbeDirectory(): string
    {
        $temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $path = $temporaryRoot
                . DIRECTORY_SEPARATOR
                . 'php-upgrade-preflight-composer-probe-'
                . bin2hex(random_bytes(8));
            if (@mkdir($path, 0700)) {
                return $path;
            }
        }

        throw new \RuntimeException('Unable to create an isolated Composer platform probe directory.');
    }

    private function resolveComposerVersion(): ?string
    {
        if ($this->composerVersionResolved) {
            return $this->composerVersion;
        }

        $this->composerVersionResolved = true;

        try {
            $version = ($this->composerVersionResolver)();
            $this->composerVersion = $version === null || trim($version) === '' ? null : trim($version);
        } catch (\Throwable $exception) {
            $this->composerVersion = null;
        }

        return $this->composerVersion;
    }

    /** @return ?array<string, string> */
    private function resolvePlatformPackages(): ?array
    {
        if ($this->platformPackagesResolved) {
            return $this->platformPackages;
        }

        $this->platformPackagesResolved = true;
        try {
            $packages = ($this->platformPackageResolver)();
            if (!is_array($packages)) {
                return $this->platformPackages = null;
            }

            $normalized = [];
            foreach ($packages as $name => $version) {
                if (!is_string($name) || !is_string($version)) {
                    return $this->platformPackages = null;
                }
                $name = strtolower(trim($name));
                if (TargetPlatform::isSupportedPackageName($name)) {
                    $normalized[$name] = trim($version);
                }
            }
            ksort($normalized, SORT_STRING);

            return $this->platformPackages = $normalized;
        } catch (\Throwable) {
            return $this->platformPackages = null;
        }
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return max(0, (int) round(((float) ($this->clock)() - $startedAt) * 1000));
    }

    /** @param array<string, string>|null $analyzerPlatformPackages */
    private function applyTemporaryComposerChanges(
        string $tempPath,
        ProjectState $project,
        Scenario $scenario,
        TargetPlatform $platform,
        ?array $analyzerPlatformPackages = null
    ): ComposerJson {
        $composerPath = $tempPath . DIRECTORY_SEPARATOR . 'composer.json';
        $data = $project->composerJson()->data();

        foreach ($scenario->targets()->packageTargets() as $target) {
            if (isset($data['require-dev']) && is_array($data['require-dev']) && array_key_exists($target->package(), $data['require-dev'])
                && (!isset($data['require']) || !is_array($data['require']) || !array_key_exists($target->package(), $data['require']))) {
                $data['require-dev'][$target->package()] = $target->constraint();
                continue;
            }

            if (!isset($data['require']) || !is_array($data['require'])) {
                $data['require'] = [];
            }

            $data['require'][$target->package()] = $target->constraint();
        }

        if ($scenario->targets()->targetPhp() !== null) {
            $data['config']['platform']['php'] = $scenario->targets()->targetPhp();
        }

        foreach ($platform->composerPlatformOverrides($analyzerPlatformPackages ?? []) as $package => $value) {
            $data['config']['platform'][$package] = $value;
        }

        $candidateManifest = new ComposerJson($data);
        $data = $this->absolutePathRepositories($data, $project->path());

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (@file_put_contents($composerPath, $encoded) === false) {
            throw new \RuntimeException('Unable to write the temporary Composer manifest.');
        }

        return $candidateManifest;
    }

    private function seedProjectState(string $tempPath, ProjectState $project): void
    {
        $files = [
            'composer.json' => $project->composerJson()->data(),
            'composer.lock' => $project->composerLock()->data(),
        ];
        foreach ($files as $name => $data) {
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
            if (@file_put_contents($tempPath . DIRECTORY_SEPARATOR . $name, $encoded) === false) {
                throw new \RuntimeException(sprintf('Unable to seed the temporary %s.', $name));
            }
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function absolutePathRepositories(array $data, string $projectPath): array
    {
        if (!isset($data['repositories']) || !is_array($data['repositories'])) {
            return $data;
        }

        foreach ($data['repositories'] as $key => $repository) {
            if (!is_array($repository)
                || !in_array($repository['type'] ?? null, ['path', 'artifact'], true)
                || !isset($repository['url'])
                || !is_string($repository['url'])
            ) {
                continue;
            }

            $url = $repository['url'];
            if ($url === '' || Path::isAbsolute($url) || str_starts_with($url, '~') || $this->containsEnvironmentVariable($url)) {
                continue;
            }

            $repository['url'] = Path::makeAbsolute($url, $projectPath);
            $data['repositories'][$key] = $repository;
        }

        return $data;
    }

    private function containsEnvironmentVariable(string $path): bool
    {
        return preg_match('/\$(?:\{[A-Za-z_][A-Za-z0-9_]*\}|[A-Za-z_][A-Za-z0-9_]*)|%[A-Za-z_][A-Za-z0-9_]*%/', $path) === 1;
    }

    /**
     * @param array{exit_code: int, stdout: string, stderr: string} $process
     * @param list<string> $repositoryPaths
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function sanitizeProcessResult(
        array $process,
        string $projectPath,
        string $workspacePath,
        array $repositoryPaths
    ): array {
        return [
            'exit_code' => $process['exit_code'],
            'stdout' => PathExposurePolicy::redactComposerText(
                $process['stdout'],
                $projectPath,
                $workspacePath,
                $repositoryPaths
            ),
            'stderr' => PathExposurePolicy::redactComposerText(
                $process['stderr'],
                $projectPath,
                $workspacePath,
                $repositoryPaths
            ),
        ];
    }

    private function isSolverFailure(string $stdout, string $stderr): bool
    {
        $output = $stdout . "\n" . $stderr;

        return stripos($output, 'Your requirements could not be resolved to an installable set of packages') !== false
            || preg_match('/(?:^|\n)\s*- Root composer\.json requires /i', $output) === 1;
    }

    private function exceptionOutcome(\Throwable $exception, string $phase): string
    {
        if ($exception instanceof WorkspaceCleanupException) {
            return ScenarioResult::OUTCOME_CLEANUP_FAILURE;
        }

        if ($exception instanceof ProcessTimedOutException) {
            return ScenarioResult::OUTCOME_TIMEOUT;
        }

        if ($exception instanceof InvalidJsonException) {
            return ScenarioResult::OUTCOME_INVALID_JSON;
        }

        if ($phase === 'process' && $this->indicatesMissingComposer(1, '', $exception->getMessage())) {
            return ScenarioResult::OUTCOME_COMPOSER_MISSING;
        }

        if ($phase === 'process') {
            return ScenarioResult::OUTCOME_PROCESS_FAILURE;
        }

        return ScenarioResult::OUTCOME_WORKSPACE_FAILURE;
    }

    private function indicatesMissingComposer(int $exitCode, string $stdout, string $stderr): bool
    {
        if (in_array($exitCode, [127, 9009], true)) {
            return true;
        }

        $output = $stdout . "\n" . $stderr;

        return preg_match('/(?:composer(?:\.bat|\.phar)?(?: executable)? (?:was |is )?(?:unavailable|missing|not found)|composer:\s*(?:command\s+)?not found|[\'\"]composer[\'\"] is not recognized|could not open input file:\s*composer|createprocess failed[^\n]*error=2|the system cannot find the file specified)/i', $output) === 1;
    }

    /** @param list<string> $repositoryPaths @return list<ComposerDiagnostic> */
    private function runTargetDiagnostics(
        ProjectState $project,
        UpgradeRequest $request,
        Scenario $scenario,
        string $workingDirectory,
        TargetPlatform $platform,
        array $repositoryPaths
    ): array {
        $diagnostics = [];

        foreach ($scenario->targets()->all() as $target) {
            if (!$this->targetNeedsDiagnostic($project, $request, $target->package(), $target->constraint())) {
                continue;
            }

            $cacheKey = $this->diagnosticCacheKey(
                $project,
                $scenario,
                $target->package(),
                $target->constraint(),
                $platform
            );
            if (isset($this->diagnosticCache[$cacheKey])) {
                $diagnostics[] = $this->diagnosticCache[$cacheKey];
                continue;
            }

            $diagnostic = $this->runTargetDiagnostic(
                $target->package(),
                $target->constraint(),
                $workingDirectory,
                $platform,
                $project->path(),
                $repositoryPaths
            );
            $this->diagnosticCache[$cacheKey] = $diagnostic;
            $diagnostics[] = $diagnostic;
        }

        return $diagnostics;
    }

    /** @param list<string> $repositoryPaths */
    private function runTargetDiagnostic(
        string $package,
        string $constraint,
        string $workingDirectory,
        TargetPlatform $platform,
        string $projectPath,
        array $repositoryPaths
    ): ComposerDiagnostic {
        if (!$this->supportsLockedDiagnostics()) {
            return new ComposerDiagnostic(
                $package,
                $constraint,
                [],
                1,
                '',
                sprintf(
                    'Composer %s does not support locked prohibits diagnostics; Composer %s or newer is required.',
                    $this->composerVersion,
                    self::LOCKED_DIAGNOSTIC_MIN_COMPOSER_VERSION
                )
            );
        }

        $command = [
            'composer',
            'prohibits',
            $package,
            $constraint,
            '--tree',
            '--locked',
        ];
        $command = array_merge($command, self::COMPOSER_SAFETY_OPTIONS, ['--no-interaction']);

        try {
            $process = ($this->processRunner)($command, $workingDirectory);
            $process = $this->sanitizeProcessResult(
                $process,
                $projectPath,
                $workingDirectory,
                $repositoryPaths
            );

            return new ComposerDiagnostic(
                $package,
                $constraint,
                $command,
                $process['exit_code'],
                $process['stdout'],
                $process['stderr']
            );
        } catch (\Throwable $exception) {
            return new ComposerDiagnostic(
                $package,
                $constraint,
                $command,
                1,
                '',
                PathExposurePolicy::redactComposerText(
                    $exception->getMessage(),
                    $projectPath,
                    $workingDirectory,
                    $repositoryPaths
                )
            );
        }
    }

    private function supportsLockedDiagnostics(): bool
    {
        if ($this->composerVersion === null || preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $this->composerVersion) !== 1) {
            return true;
        }

        return version_compare($this->composerVersion, self::LOCKED_DIAGNOSTIC_MIN_COMPOSER_VERSION, '>=');
    }

    private function platformCapabilityFailure(TargetPlatform $platform, ?string $composerVersion): ?string
    {
        if (!$platform->isCompleteProfile() && !$platform->hasAbsentPlatformPackages()) {
            return null;
        }

        if ($composerVersion === null || preg_match('/^(\d+)\.(\d+)/', $composerVersion, $matches) !== 1) {
            return $platform->isCompleteProfile()
                ? 'Composer version could not be determined; Composer 2.2.0 or newer is required for a complete target-platform profile, which was not weakened to partial coverage.'
                : null;
        }

        $normalized = sprintf('%d.%d.0', (int) $matches[1], (int) $matches[2]);
        if (version_compare($normalized, self::COMPLETE_PLATFORM_MIN_COMPOSER_VERSION, '>=')) {
            return null;
        }

        return sprintf(
            'Composer %s cannot hide absent platform packages; Composer %s or newer is required%s.',
            $composerVersion,
            self::COMPLETE_PLATFORM_MIN_COMPOSER_VERSION,
            $platform->isCompleteProfile()
                ? ' for a complete target-platform profile, which was not weakened to partial coverage'
                : ''
        );
    }

    /** @param list<string> $command */
    private function operationalResult(
        Scenario $scenario,
        ?string $composerVersion,
        array $command,
        string $message
    ): ScenarioResult {
        return new ScenarioResult(
            $scenario,
            1,
            '',
            $message,
            null,
            null,
            ScenarioResult::FAILURE_OPERATIONAL,
            $composerVersion,
            $command,
            0,
            null,
            [],
            ScenarioResult::OUTCOME_PROCESS_FAILURE
        );
    }

    private function diagnosticCacheKey(
        ProjectState $project,
        Scenario $scenario,
        string $package,
        string $constraint,
        TargetPlatform $platform
    ): string {
        return hash('sha256', serialize([
            $project->path(),
            $project->composerJson()->data(),
            $project->composerLock()->data(),
            $scenario->targets()->toArray(),
            $package,
            $constraint,
            array_map(
                static fn ($assumption): array => $assumption->toArray(),
                $platform->extensionAssumptions()
            ),
            $platform->profileDigest(),
        ]));
    }

    private function targetNeedsDiagnostic(
        ProjectState $project,
        UpgradeRequest $request,
        string $package,
        string $constraint
    ): bool {
        if ($package === 'php') {
            return $constraint === $request->targets()->targetPhp();
        }

        $locked = $project->composerLock()->package($package);

        return $locked === null || !Semver::satisfies($locked->version(), $constraint);
    }
}
