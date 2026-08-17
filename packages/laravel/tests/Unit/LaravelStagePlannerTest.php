<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit;

use PhpUpgradePreflight\Core\Analysis\StagePlanResolver;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PhpUpgradePreflight\Laravel\Catalog\PackageConstraintDefinition;
use PhpUpgradePreflight\Laravel\Catalog\PackageRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\RuleApplicability;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PhpUpgradePreflight\Laravel\LaravelStagePlanner;
use PHPUnit\Framework\TestCase;

final class LaravelStagePlannerTest extends TestCase
{
    private const REMEDIATED_PACKAGE = 'vendor/staged-remediation';

    public function testItReferencesEveryEvidenceIdMintedForRepeatedGuidanceOnOnePackage(): void
    {
        $ledger = new EvidenceLedger();

        $plan = (new LaravelStagePlanner($this->catalogWithRepeatedGuidance()))->planStages(
            $this->project(),
            $this->request(),
            $ledger
        );

        self::assertTrue($plan->isAvailable());
        self::assertSame(['laravel-10-to-11'], array_map(
            static fn ($stage): string => $stage->id(),
            $plan->stages()
        ));
        $stage = $plan->stages()[0];
        self::assertSame([self::REMEDIATED_PACKAGE], array_map(
            static fn (UpgradeTarget $target): string => $target->package(),
            $stage->remediationTargets()
        ));
        self::assertCount(2, $stage->remediationEvidence(self::REMEDIATED_PACKAGE));
        // Throws on any ledger entry the returned plan does not reference, which is
        // the orphan StagePlanResolver rejects as unreferenced_provider_evidence.
        $ledger->validateReferences(array_merge(
            $plan->evidence(),
            $stage->evidence(),
            $stage->remediationEvidence(self::REMEDIATED_PACKAGE)
        ));
    }

    public function testRepeatedGuidanceStillProducesAnExecutableStagedChain(): void
    {
        $resolution = (new StagePlanResolver())->resolve(
            [new LaravelFrameworkIntegration(null, $this->catalogWithRepeatedGuidance())],
            $this->project(),
            $this->request(),
            new EvidenceLedger()
        );

        self::assertFalse($resolution->isSkipped());
        self::assertSame('laravel', $resolution->provider());
        self::assertCount(1, $resolution->stages());
    }

    /**
     * A catalog whose only difference from the shipped one is a package rule that
     * carries two guidance entries for the same package on the same transition.
     * The shipped catalog cannot express this, but the public catalog constructor
     * can, and each entry makes the planner mint its own evidence.
     */
    private function catalogWithRepeatedGuidance(): LaravelRuleCatalog
    {
        $applies10To11 = new RuleApplicability(10, 11);
        $source = 'https://laravel.com/docs/11.x/upgrade';
        $catalog = LaravelRuleCatalog::v0_2();
        $rules = $catalog->rules();
        $rules[] = new PackageRuleDefinition('rule-package-repeated-guidance', [
            new PackageConstraintDefinition(
                'package-repeated-guidance-first',
                self::REMEDIATED_PACKAGE,
                $applies10To11,
                '^2.0',
                'medium',
                [$source]
            ),
            new PackageConstraintDefinition(
                'package-repeated-guidance-second',
                self::REMEDIATED_PACKAGE,
                $applies10To11,
                '^2.1',
                'medium',
                [$source]
            ),
        ]);

        return new LaravelRuleCatalog(
            $catalog->version(),
            $catalog->minimumMajor(),
            $catalog->maximumMajor(),
            $catalog->targets(),
            $catalog->transitions(),
            $rules,
            $catalog->skeletonPatterns()
        );
    }

    private function project(): ProjectState
    {
        return new ProjectState(
            __DIR__,
            new ComposerJson([
                'require' => [
                    'laravel/framework' => '^10.0',
                    self::REMEDIATED_PACKAGE => '^1.0',
                ],
            ]),
            new ComposerLock(['packages' => [
                ['name' => 'laravel/framework', 'version' => 'v10.48.28'],
                ['name' => self::REMEDIATED_PACKAGE, 'version' => '1.0.0'],
            ]])
        );
    }

    private function request(): UpgradeRequest
    {
        return new UpgradeRequest(
            __DIR__,
            [new UpgradeTarget('laravel/framework', '^11.0')],
            '8.1',
            '8.2.0'
        );
    }
}
