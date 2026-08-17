<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Source;

use PhpUpgradePreflight\Core\Composer\ProjectStateBuilder;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Source\SourceUsageScanner;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The Laravel skeleton vocabulary reaches a scan only through the integration's
 * SourceUsageVisitorProvider port. These cases moved here from the core scanner
 * test when the visitor moved out of core; the assertions are unchanged apart
 * from supplying the active integration.
 */
final class LaravelSourceUsageVisitorTest extends TestCase
{
    public function testContextualInspectionClassifiesUpgradeSensitiveSourceUsages(): void
    {
        $projectPath = $this->createProject(<<<'PHP'
<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

config('services.mailgun.domain');
config(['services.mailgun.secret' => 'secret']);
config()->get('cache.default');
Config::get('app.timezone');
config($dynamicKey);
PHP);

        $sources = [
            'config/app.php' => <<<'PHP'
<?php

return [
    'providers' => [
        Vendor\Package\PackageServiceProvider::class,
    ],
    'aliases' => [
        'Package' => Vendor\Package\Facades\Package::class,
        'Legacy' => 'Vendor\\Package\\Facades\\Legacy',
    ],
];
PHP,
            'bootstrap/providers.php' => <<<'PHP'
<?php

return [App\Providers\BootstrapServiceProvider::class];
PHP,
            'app/Providers/AppServiceProvider.php' => <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider {}
PHP,
            'app/Http/Kernel.php' => <<<'PHP'
<?php

namespace App\Http;

final class Kernel
{
    protected $middleware = [\App\Http\Middleware\TrustHosts::class];
    protected $middlewareGroups = ['web' => [\App\Http\Middleware\EncryptCookies::class]];

    public function configure($route): void
    {
        $route->middleware([\App\Http\Middleware\Authenticate::class]);
    }
}
PHP,
            'app/Console/Kernel.php' => <<<'PHP'
<?php

namespace App\Console;

final class Kernel
{
    protected $commands = [\App\Console\Commands\RebuildIndex::class];

    public function register($app): void
    {
        $app->register(\Vendor\Package\RuntimeServiceProvider::class);
        $this->commands([\App\Console\Commands\WarmCache::class]);
    }
}
PHP,
            'app/Console/Commands/RebuildIndex.php' => <<<'PHP'
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class RebuildIndex extends Command {}
PHP,
            'tests/ExampleTest.php' => <<<'PHP'
<?php

namespace Tests;

use App\Contracts\Gateway;
use App\Services\Mailer;
use Mockery;

$this->createMock(Gateway::class);
$this->mock(Mailer::class);
Mockery::mock('overload:App\Services\LegacyClient');
Mailer::shouldReceive('send');
PHP,
        ];

        foreach ($sources as $path => $source) {
            $fullPath = $projectPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            mkdir(dirname($fullPath), 0700, true);
            file_put_contents($fullPath, $source);
        }

        $evidence = new EvidenceLedger();
        $uncertainties = [];

        try {
            $project = (new ProjectStateBuilder())->build($projectPath);
            $usages = (new SourceUsageScanner())->scan(
                $project,
                ['src', 'app', 'bootstrap', 'config', 'tests'],
                $evidence,
                $uncertainties,
                true,
                [new LaravelFrameworkIntegration()]
            );
            $usageTriples = array_map(
                static fn (SourceUsage $usage): array => [$usage->file(), $usage->symbol(), $usage->usageType()],
                $usages
            );

            self::assertContains(['src/Example.php', 'services.mailgun.domain', 'config_reference'], $usageTriples);
            self::assertContains(['src/Example.php', 'services.mailgun.secret', 'config_reference'], $usageTriples);
            self::assertContains(['src/Example.php', 'cache.default', 'config_reference'], $usageTriples);
            self::assertContains(['src/Example.php', 'app.timezone', 'config_reference'], $usageTriples);
            self::assertNotContains(['src/Example.php', 'dynamicKey', 'config_reference'], $usageTriples);
            self::assertContains(['config/app.php', 'Vendor\Package\PackageServiceProvider', 'service_provider'], $usageTriples);
            self::assertContains(['config/app.php', 'Vendor\Package\Facades\Package', 'facade_alias'], $usageTriples);
            self::assertContains(['config/app.php', 'Vendor\Package\Facades\Legacy', 'facade_alias'], $usageTriples);
            self::assertContains(['bootstrap/providers.php', 'App\Providers\BootstrapServiceProvider', 'service_provider'], $usageTriples);
            self::assertContains(['app/Providers/AppServiceProvider.php', 'App\Providers\AppServiceProvider', 'service_provider'], $usageTriples);
            self::assertContains(['app/Console/Kernel.php', 'Vendor\Package\RuntimeServiceProvider', 'service_provider'], $usageTriples);
            self::assertContains(['app/Http/Kernel.php', 'App\Http\Middleware\TrustHosts', 'middleware_reference'], $usageTriples);
            self::assertContains(['app/Http/Kernel.php', 'App\Http\Middleware\EncryptCookies', 'middleware_reference'], $usageTriples);
            self::assertContains(['app/Http/Kernel.php', 'App\Http\Middleware\Authenticate', 'middleware_reference'], $usageTriples);
            self::assertContains(['app/Console/Kernel.php', 'App\Console\Commands\RebuildIndex', 'console_command'], $usageTriples);
            self::assertContains(['app/Console/Kernel.php', 'App\Console\Commands\WarmCache', 'console_command'], $usageTriples);
            self::assertContains(['app/Console/Commands/RebuildIndex.php', 'App\Console\Commands\RebuildIndex', 'console_command'], $usageTriples);
            self::assertContains(['tests/ExampleTest.php', 'App\Contracts\Gateway', 'test_double'], $usageTriples);
            self::assertContains(['tests/ExampleTest.php', 'App\Services\Mailer', 'test_double'], $usageTriples);
            self::assertContains(['tests/ExampleTest.php', 'App\Services\LegacyClient', 'test_double'], $usageTriples);
            self::assertSame([], $uncertainties);

            $evidenceById = [];
            foreach ($evidence->all() as $item) {
                $evidenceById[$item->id()] = $item;
            }

            foreach ($usages as $usage) {
                if (in_array($usage->usageType(), ['config_reference', 'service_provider', 'facade_alias', 'middleware_reference', 'console_command', 'test_double'], true)) {
                    self::assertNotNull($usage->line());
                    self::assertNotEmpty($usage->evidence());

                    foreach ($usage->evidence() as $evidenceId) {
                        self::assertArrayHasKey($evidenceId, $evidenceById);
                        self::assertSame($usage->file(), $evidenceById[$evidenceId]->context()['file']);
                        self::assertSame($usage->usageType(), $evidenceById[$evidenceId]->context()['usage_type']);
                        self::assertIsInt($evidenceById[$evidenceId]->context()['line']);
                    }
                }
            }
        } finally {
            (new Filesystem())->remove($projectPath);
        }
    }

    public function testConfigArrayReadsAndWritesPreserveLiteralKeys(): void
    {
        $projectPath = $this->createProject(<<<'PHP'
<?php

namespace App;

use Illuminate\Support\Facades\Config;

Config::get(['app.name', 'app.env']);
Config::getMany(['cache.default', 'queue.default']);
Config::get(['mail.default' => 'smtp']);
Config::set(['app.debug' => false]);
PHP);
        $evidence = new EvidenceLedger();
        $uncertainties = [];

        try {
            $project = (new ProjectStateBuilder())->build($projectPath);
            $usages = $this->scan($project, $evidence, $uncertainties);
            $configReferences = array_values(array_filter(
                $usages,
                static fn (SourceUsage $usage): bool => $usage->usageType() === 'config_reference'
            ));

            self::assertSame(
                ['app.name', 'app.env', 'cache.default', 'queue.default', 'mail.default', 'app.debug'],
                array_map(static fn (SourceUsage $usage): string => $usage->symbol(), $configReferences)
            );
            self::assertSame([], $uncertainties);
        } finally {
            (new Filesystem())->remove($projectPath);
        }
    }

    public function testPhpUnitAndFacadeTestDoubleApisAreClassified(): void
    {
        $projectPath = $this->createProject(<<<'PHP'
<?php

namespace Tests;

use App\Contracts\AbstractGateway;
use App\Services\PartialMailer;
use App\Support\ReusableBehavior;
use Illuminate\Support\Facades\Event;

$this->createPartialMock(PartialMailer::class, ['send']);
$this->getMockForAbstractClass(AbstractGateway::class);
$this->getMockForTrait(ReusableBehavior::class);
Event::fake();
PHP);
        $evidence = new EvidenceLedger();
        $uncertainties = [];

        try {
            $project = (new ProjectStateBuilder())->build($projectPath);
            $usages = $this->scan($project, $evidence, $uncertainties);
            $testDoubles = array_values(array_filter(
                $usages,
                static fn (SourceUsage $usage): bool => $usage->usageType() === 'test_double'
            ));

            self::assertSame(
                [
                    'App\Services\PartialMailer',
                    'App\Contracts\AbstractGateway',
                    'App\Support\ReusableBehavior',
                    'Illuminate\Support\Facades\Event',
                ],
                array_map(static fn (SourceUsage $usage): string => $usage->symbol(), $testDoubles)
            );
            self::assertSame([], $uncertainties);
        } finally {
            (new Filesystem())->remove($projectPath);
        }
    }

    public function testRegisterRequiresApplicationContextOrAServiceProviderTarget(): void
    {
        $projectPath = $this->createProject(<<<'PHP'
<?php

namespace App;

$serializer->register(\App\Serialization\JsonNormalizer::class);
$container->register(\Vendor\Package\PackageServiceProvider::class);
$app->register(\App\Providers\CustomProvider::class);
$this->application->register(\App\Providers\OtherProvider::class);
Application::register(\App\Providers\StaticProvider::class);
PHP);
        $evidence = new EvidenceLedger();
        $uncertainties = [];

        try {
            $project = (new ProjectStateBuilder())->build($projectPath);
            $usages = $this->scan($project, $evidence, $uncertainties);
            $serviceProviders = array_values(array_filter(
                $usages,
                static fn (SourceUsage $usage): bool => $usage->usageType() === 'service_provider'
            ));
            $symbols = array_map(static fn (SourceUsage $usage): string => $usage->symbol(), $serviceProviders);

            self::assertNotContains('App\Serialization\JsonNormalizer', $symbols);
            self::assertSame(
                [
                    'Vendor\Package\PackageServiceProvider',
                    'App\Providers\CustomProvider',
                    'App\Providers\OtherProvider',
                    'App\Providers\StaticProvider',
                ],
                $symbols
            );
            self::assertSame([], $uncertainties);
        } finally {
            (new Filesystem())->remove($projectPath);
        }
    }

    /**
     * Report-order contract: within one file the framework-neutral collector runs
     * first, so every contributed usage follows every core usage. Snapshot stability
     * depends on this, so it is asserted rather than assumed.
     */
    public function testCoreUsagesArePreservedAndPrecedeContributedUsages(): void
    {
        $projectPath = $this->createProject(<<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider {}
PHP);
        $evidence = new EvidenceLedger();
        $uncertainties = [];

        try {
            $project = (new ProjectStateBuilder())->build($projectPath);
            $usages = $this->scan($project, $evidence, $uncertainties);
            $usageTypes = array_map(static fn (SourceUsage $usage): string => $usage->usageType(), $usages);

            self::assertContains('namespace_import', $usageTypes, 'The core collector must still run.');
            self::assertContains('service_provider', $usageTypes, 'The Laravel collector must still run.');

            $seenContributed = false;
            foreach ($usageTypes as $usageType) {
                if ($usageType === 'service_provider') {
                    $seenContributed = true;

                    continue;
                }

                self::assertFalse(
                    $seenContributed,
                    'Core usages must not appear after a contributed usage for the same file.'
                );
            }

            self::assertSame([], $uncertainties);
        } finally {
            (new Filesystem())->remove($projectPath);
        }
    }

    /**
     * @param list<string> $uncertainties
     * @return list<SourceUsage>
     */
    private function scan(
        ProjectState $project,
        EvidenceLedger $evidence,
        array &$uncertainties
    ): array {
        return (new SourceUsageScanner())->scan(
            $project,
            ['src'],
            $evidence,
            $uncertainties,
            true,
            [new LaravelFrameworkIntegration()]
        );
    }

    private function createProject(string $source): string
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-source-usage-' . bin2hex(random_bytes(8));
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'src', 0700, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'composer.json', "{\"require\":{}}\n");
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'composer.lock', "{\"packages\":[],\"packages-dev\":[]}\n");
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Example.php', $source);

        return $projectPath;
    }
}
