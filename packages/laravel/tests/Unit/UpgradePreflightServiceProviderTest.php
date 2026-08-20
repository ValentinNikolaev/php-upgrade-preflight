<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use Illuminate\Contracts\Foundation\Application;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Laravel\Console\ArtisanAnalysisProgressReporter;
use PhpUpgradePreflight\Laravel\UpgradePreflightServiceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

final class UpgradePreflightServiceProviderTest extends TestCase
{
    public function testItSharesTheArtisanProgressReporterWithTheDefaultAnalyzer(): void
    {
        /** @var array<class-string, mixed> $bindings */
        $bindings = [];
        $application = $this->createMock(Application::class);
        $application->expects(self::exactly(2))
            ->method('singleton')
            ->willReturnCallback(static function (string $abstract, $concrete = null) use (&$bindings): void {
                $bindings[$abstract] = $concrete;
            });

        (new UpgradePreflightServiceProvider($application))->register();

        self::assertArrayHasKey(ArtisanAnalysisProgressReporter::class, $bindings);
        self::assertArrayHasKey(UpgradeAnalyzer::class, $bindings);
        self::assertNull($bindings[ArtisanAnalysisProgressReporter::class]);
        $analyzerFactory = $bindings[UpgradeAnalyzer::class];
        if (!is_callable($analyzerFactory)) {
            self::fail('The analyzer singleton must be registered through a factory.');
        }

        $reporter = new ArtisanAnalysisProgressReporter(static fn (OutputInterface $output): bool => false);
        $resolver = $this->createMock(Application::class);
        $resolver->expects(self::once())
            ->method('make')
            ->with(ArtisanAnalysisProgressReporter::class)
            ->willReturn($reporter);

        $analyzer = $analyzerFactory($resolver);

        self::assertInstanceOf(DefaultUpgradeAnalyzer::class, $analyzer);
    }
}
