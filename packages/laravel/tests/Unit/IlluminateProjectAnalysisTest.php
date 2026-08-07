<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Composer\ComposerScenarioRunner;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class IlluminateProjectAnalysisTest extends TestCase
{
    public function testDetectedIlluminateProjectScansItsSrcDirectory(): void
    {
        $filesystem = new Filesystem();
        $projectPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'php-upgrade-preflight-illuminate-'
            . bin2hex(random_bytes(8));

        $filesystem->mkdir($projectPath . DIRECTORY_SEPARATOR . 'src');
        $filesystem->dumpFile($projectPath . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'require' => ['illuminate/support' => '^7.0'],
        ], JSON_THROW_ON_ERROR));
        $filesystem->dumpFile($projectPath . DIRECTORY_SEPARATOR . 'composer.lock', json_encode([
            'packages' => [['name' => 'illuminate/support', 'version' => 'v7.30.7']],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR));
        $filesystem->dumpFile(
            $projectPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php',
            <<<'PHP'
<?php

namespace Fixture;

use Illuminate\Support\Facades\Config;

final class Example
{
    public function name(): string
    {
        return (string) Config::get('app.name');
    }
}
PHP
        );

        $runner = new ComposerScenarioRunner(null, null, static function (): array {
            return ['exit_code' => 0, 'stdout' => 'Resolved.', 'stderr' => ''];
        });

        try {
            $request = new UpgradeRequest(
                $projectPath,
                [new UpgradeTarget('illuminate/support', '^8.0')]
            );
            $report = (new DefaultUpgradeAnalyzer(
                [new LaravelFrameworkIntegration()],
                null,
                $runner
            ))->analyzeUpgrade($request);

            $sourceFiles = array_map(
                static fn ($usage): string => str_replace('\\', '/', $usage->file()),
                $report->sourceImpact()
            );

            self::assertContains('src/Example.php', $sourceFiles);
            self::assertContains(
                'Illuminate\Support\Facades\Config',
                array_map(static fn ($usage): string => $usage->symbol(), $report->sourceImpact())
            );
        } finally {
            $filesystem->remove($projectPath);
        }
    }
}
