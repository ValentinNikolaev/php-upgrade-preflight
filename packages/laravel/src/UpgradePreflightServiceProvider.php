<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;
use PhpUpgradePreflight\Laravel\Console\ArtisanAnalysisProgressReporter;

final class UpgradePreflightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ArtisanAnalysisProgressReporter::class);
        $this->app->singleton(UpgradeAnalyzer::class, static function (Application $app): UpgradeAnalyzer {
            return new DefaultUpgradeAnalyzer(
                [new LaravelFrameworkIntegration()],
                progressReporter: $app->make(ArtisanAnalysisProgressReporter::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([AnalyzeUpgradeCommand::class]);
        }
    }
}
