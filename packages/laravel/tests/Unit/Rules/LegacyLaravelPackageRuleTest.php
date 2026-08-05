<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Rules;

use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\Rules\LegacyLaravelPackageRule;
use PHPUnit\Framework\TestCase;

final class LegacyLaravelPackageRuleTest extends TestCase
{
    public function testItCreatesAFindingAndEvidenceForAPresentLegacyPackage(): void
    {
        $project = new ProjectState(
            __DIR__,
            new ComposerJson(['require-dev' => ['Facade/Ignition' => '^2.0']]),
            new ComposerLock(['packages-dev' => [['name' => 'facade/ignition', 'version' => '2.17.7']]])
        );
        $rule = new LegacyLaravelPackageRule('facade/ignition', 'Review Ignition.', 'medium');
        $evidence = [];

        $finding = $rule->evaluate($project, $this->request(), $evidence);

        self::assertNotNull($finding);
        self::assertSame('laravel', $finding->framework);
        self::assertSame('medium', $finding->severity);
        self::assertSame('Review Ignition.', $finding->summary);
        self::assertSame(['package-facade_ignition'], $finding->evidence);
        self::assertCount(1, $evidence);
        self::assertSame(Evidence::E2_PACKAGE_METADATA, $evidence[0]->class);
        self::assertSame('2.17.7', $evidence[0]->context['locked_version']);
        self::assertSame('^2.0', $evidence[0]->context['root_constraint']);
    }

    public function testItDoesNothingWhenThePackageIsAbsent(): void
    {
        $project = new ProjectState(__DIR__, new ComposerJson([]), new ComposerLock([]));
        $rule = new LegacyLaravelPackageRule('facade/ignition', 'Review Ignition.', 'medium');
        $evidence = [];

        self::assertNull($rule->evaluate($project, $this->request(), $evidence));
        self::assertSame([], $evidence);
    }

    private function request(): UpgradeRequest
    {
        return new UpgradeRequest(__DIR__, [UpgradeTarget::fromString('laravel/framework:^9.0')]);
    }
}
