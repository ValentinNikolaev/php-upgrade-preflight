<?php

declare(strict_types=1);

use PhpUpgradePreflight\Cli\AnalyzeCommand;
use PhpUpgradePreflight\Core\Analysis\BlockerGrouper;
use PhpUpgradePreflight\Core\Composer\JsonFileReader;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Core\Filesystem\WorkspaceCleanupException;
use PhpUpgradePreflight\Core\Model\ComposerDiagnostic;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EffortEstimate;
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
use PhpUpgradePreflight\Tools\SecretLeakVerifier;
use Symfony\Component\Filesystem\Filesystem;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, 'Report privacy verification could not load project dependencies.' . PHP_EOL);
    exit(1);
}

require $autoload;
require_once __DIR__ . '/SecretLeakVerifier.php';

$directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . 'php-upgrade-preflight-privacy-' . bin2hex(random_bytes(8));
$filesystem = new Filesystem();
$failed = false;
$stage = 'initialization';
$failedStage = $stage;

try {
    $filesystem->mkdir($directory, 0700);
    $stage = 'fixture';
    $contents = @file_get_contents($root . '/tests/fixtures/security/composer-output-with-secrets.json');
    if ($contents === false) {
        throw new RuntimeException('The privacy fixture could not be read.');
    }
    $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($fixture)
        || !is_array($fixture['canaries'] ?? null)
        || !is_string($fixture['stdout'] ?? null)
        || !is_string($fixture['stderr'] ?? null)
    ) {
        throw new RuntimeException('The privacy fixture is invalid.');
    }

    $sensitiveOutput = $fixture['stdout'] . "\n" . $fixture['stderr'];
    $stage = 'model';
    $projectPath = $directory . DIRECTORY_SEPARATOR . 'private-project-root-marker';
    $filesystem->mkdir($projectPath, 0700);
    $request = new UpgradeRequest(
        $projectPath,
        [new UpgradeTarget('vendor/private-package', '^2.0')],
        null,
        null,
        [],
        [],
        'json',
        $directory . DIRECTORY_SEPARATOR . 'report.json'
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
        new Scenario('credential-repository-failure', $request->targets()),
        2,
        $fixture['stdout'],
        $fixture['stderr'],
        null,
        null,
        ScenarioResult::FAILURE_SOLVER,
        '2.8.12',
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

    $stage = 'rendering';
    $json = (new JsonReportWriter())->render($report);
    $markdown = (new MarkdownReportWriter())->render($report);
    $importedCanonical = $report->toArray();
    $importedCanonical['request_summary']['project_path'] = $projectPath;
    $importedCanonical['project_state']['path'] = $projectPath;
    $importedCanonical['uncertainties'][] = $sensitiveOutput;
    $objectSecret = $fixture['canaries']['escaped_json_authorization'] ?? null;
    if (!is_string($objectSecret)
        || !isset($importedCanonical['evidence'][0]['context'])
        || !is_array($importedCanonical['evidence'][0]['context'])
    ) {
        throw new RuntimeException('The privacy fixture has no structured evidence canary.');
    }
    $importedCanonical['evidence'][0]['context'][$projectPath . '/credential-object'] = new class (
        $objectSecret,
        $projectPath
    ) {
        public string $authorization;
        public string $path;

        public function __construct(string $authorization, string $path)
        {
            $this->authorization = $authorization;
            $this->path = $path . '/composer.json';
        }
    };
    $encodedProjectPath = implode(
        '/',
        array_map('rawurlencode', explode('/', str_replace('\\', '/', $projectPath)))
    );
    $importedCanonical['uncertainties'][] = 'Encoded project path: '
        . str_ireplace('%3A', ':', $encodedProjectPath)
        . '/composer.json';
    $importedMarkdown = (new MarkdownReportWriter())->renderCanonical($importedCanonical);
    $stage = 'path-scan';
    $pathVariants = [
        'native' => $projectPath,
        'forward' => str_replace('\\', '/', $projectPath),
        'encoded' => $encodedProjectPath,
    ];
    foreach (['json' => $json, 'markdown' => $markdown, 'imported-markdown' => $importedMarkdown] as $surface => $contents) {
        foreach ($pathVariants as $variant => $privateRoot) {
            if ($privateRoot !== '' && str_contains($contents, $privateRoot)) {
                $stage = sprintf('path-%s-%s', $surface, $variant);
                throw new RuntimeException('A canonical report exposed a private project root.');
            }
        }
    }

    $stage = 'exception-debug';
    $pathCanary = $fixture['canaries']['npm_token'] ?? null;
    if (!is_string($pathCanary) || $pathCanary === '') {
        throw new RuntimeException('The privacy fixture has no path canary.');
    }
    $workspacePath = $directory . DIRECTORY_SEPARATOR . $pathCanary;
    $workspaceException = new WorkspaceCleanupException(
        $workspacePath,
        'Cleanup failed in ' . $workspacePath . ': ' . $sensitiveOutput,
        new RuntimeException('Native cleanup failure in ' . $workspacePath . ': ' . $sensitiveOutput)
    );
    $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
    $previousMaxLength = ini_get('zend.exception_string_param_max_len');
    if (!is_string($previousIgnoreArgs) || !is_string($previousMaxLength)) {
        throw new RuntimeException('Unable to inspect exception-rendering configuration.');
    }
    $jsonException = '';
    try {
        ini_set('zend.exception_ignore_args', '0');
        ini_set('zend.exception_string_param_max_len', '1000');
        try {
            (new JsonFileReader())->read($workspacePath . DIRECTORY_SEPARATOR . 'composer.json');
            throw new RuntimeException('The missing JSON privacy probe did not fail.');
        } catch (\PhpUpgradePreflight\Core\Composer\JsonFileException $exception) {
            $jsonException = (string) $exception;
        }
    } finally {
        ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
        ini_set('zend.exception_string_param_max_len', $previousMaxLength);
    }
    $debugResult = new ScenarioResult(
        new Scenario('debug-privacy', $request->targets()),
        1,
        $fixture['stdout'],
        $fixture['stderr'],
        null,
        $workspacePath,
        ScenarioResult::FAILURE_OPERATIONAL,
        null,
        [],
        0,
        null,
        [],
        ScenarioResult::OUTCOME_PROCESS_FAILURE,
        true
    );

    $stage = 'command-log';
    $stdout = fopen('php://temp', 'w+');
    $stderr = fopen('php://temp', 'w+');
    if (!is_resource($stdout) || !is_resource($stderr)) {
        throw new RuntimeException('Unable to create command capture streams.');
    }
    $throwingAnalyzer = new class ($sensitiveOutput) implements UpgradeAnalyzer {
        private string $message;

        public function __construct(string $message)
        {
            $this->message = $message;
        }

        public function analyzeUpgrade(UpgradeRequest $request): UpgradeReport
        {
            throw new RuntimeException($this->message);
        }
    };
    $command = new AnalyzeCommand($throwingAnalyzer, $stdout, $stderr);
    $command->run([
        'upgrade-intel',
        'analyze',
        '--path=' . $projectPath,
        '--target-php=8.2',
    ]);
    rewind($stdout);
    rewind($stderr);
    $commandLog = stream_get_contents($stdout) . stream_get_contents($stderr);
    fclose($stdout);
    fclose($stderr);

    $stage = 'artifact-write';
    $artifacts = [
        'report.json' => $json,
        'report.md' => $markdown,
        'imported-report.md' => $importedMarkdown,
        'exception.log' => (string) $workspaceException . PHP_EOL . $jsonException,
        'debug.log' => json_encode($debugResult->toArray(), JSON_THROW_ON_ERROR),
        'ci.log' => $commandLog,
    ];
    foreach ($artifacts as $name => $artifact) {
        $filesystem->dumpFile($directory . DIRECTORY_SEPARATOR . $name, $artifact);
    }

    $stage = 'canary-scan';
    $errors = SecretLeakVerifier::fromFixture(
        $root . '/tests/fixtures/security/composer-output-with-secrets.json'
    )->verify([$directory]);
    if ($errors !== []) {
        throw new RuntimeException('A privacy artifact contains a seeded canary.');
    }
} catch (Throwable) {
    $failed = true;
    $failedStage = $stage;
} finally {
    if (is_dir($directory)) {
        try {
            $filesystem->remove($directory);
        } catch (Throwable) {
            $failed = true;
            $failedStage = 'cleanup';
        }
    }
}

if ($failed) {
    fwrite(STDERR, sprintf('Report privacy verification failed safely (%s).%s', $failedStage, PHP_EOL));
    exit(1);
}

fwrite(STDOUT, 'Report privacy verification passed.' . PHP_EOL);
