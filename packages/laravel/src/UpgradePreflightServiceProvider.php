<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel;

use Illuminate\Support\ServiceProvider;
use PhpUpgradePreflight\Core\Analysis\DefaultUpgradeAnalyzer;
use PhpUpgradePreflight\Core\Contracts\UpgradeAnalyzer;
use PhpUpgradePreflight\Laravel\Commands\AnalyzeUpgradeCommand;

final class UpgradePreflightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UpgradeAnalyzer::class, static function (): UpgradeAnalyzer {
            return new DefaultUpgradeAnalyzer([new LaravelFrameworkIntegration()]);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([AnalyzeUpgradeCommand::class]);
        }
    }
}
