<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Tests\Unit\Rules;

use PhpUpgradePreflight\Core\Analysis\FrameworkRuleEngine;
use PhpUpgradePreflight\Core\Model\ComposerJson;
use PhpUpgradePreflight\Core\Model\ComposerLock;
use PhpUpgradePreflight\Core\Model\Evidence;
use PhpUpgradePreflight\Core\Model\EvidenceLedger;
use PhpUpgradePreflight\Core\Model\FrameworkHop;
use PhpUpgradePreflight\Core\Model\ProjectState;
use PhpUpgradePreflight\Core\Model\SourceUsage;
use PhpUpgradePreflight\Core\Model\UpgradeRequest;
use PhpUpgradePreflight\Core\Model\UpgradeTarget;
use PhpUpgradePreflight\Laravel\Catalog\BuiltinRuleDefinition;
use PhpUpgradePreflight\Laravel\Catalog\RuleApplicability;
use PhpUpgradePreflight\Laravel\LaravelFrameworkIntegration;
use PhpUpgradePreflight\Laravel\Rules\LaravelHighSignalSourceRule;
use PHPUnit\Framework\TestCase;

final class LaravelHighSignalSourceRuleTest extends TestCase
{
    /** @dataProvider removedLegacySymbolProvider */
    public function testRemovedLegacySymbolsHaveIndependentSourceFindings(int $from, int $to, string $symbol, string $usageType, string $replacement): void
    {
        $evidence = new EvidenceLedger();
        $source = $this->usage($evidence, $symbol, $usageType);
        $finding = $this->removedSymbolRule($from, $to)->evaluate($this->project($from), $this->request($to), $evidence, [$source]);

        self::assertNotNull($finding);
        self::assertSame('high', $finding->severity());
        self::assertStringContainsString($replacement, $finding->summary());
        self::assertContains($source->evidence()[0], $finding->evidence());
        $documentation = array_values(array_filter($evidence->all(), static fn (Evidence $item): bool => $item->evidenceClass() === Evidence::E4_MAINTAINER_DOCUMENTATION));
        self::assertCount(1, $documentation);
        self::assertStringStartsWith('https://github.com/laravel/docs/blob/', $documentation[0]->context()['source']);
        self::assertContains($documentation[0]->id(), $finding->evidence());
    }

    /** @return iterable<string, array{int, int, string, string, string}> */
    public function removedLegacySymbolProvider(): iterable
    {
        yield 'removed global Elixir helper' => [7, 8, 'elixir', 'deprecated_asset_helper', 'mix'];
        yield 'instantiated closure factory' => [8, 9, 'Illuminate\\Queue\\SerializableClosureFactory', 'instantiated_class', 'laravel/serializable-closure'];
        yield 'instantiated queue closure' => [8, 9, 'Illuminate\\Queue\\SerializableClosure', 'instantiated_class', 'laravel/serializable-closure'];
        yield 'removed closure class constant' => [8, 9, 'Illuminate\\Queue\\SerializableClosure', 'class_constant_access', 'laravel/serializable-closure'];
        yield 'removed closure inheritance' => [8, 9, 'Illuminate\\Queue\\SerializableClosure', 'inheritance', 'laravel/serializable-closure'];
        yield 'removed testing trait' => [9, 10, 'Illuminate\\Foundation\\Testing\\Concerns\\MocksApplicationServices', 'trait_reference', 'Event::fake'];
    }

    /** @dataProvider unrelatedLegacySymbolProvider */
    public function testImportsAndUnrelatedLegacyNamesAreNotRemovalFindings(int $from, int $to, string $symbol, string $usageType): void
    {
        $evidence = new EvidenceLedger();
        $source = $this->usage($evidence, $symbol, $usageType);

        self::assertNull($this->removedSymbolRule($from, $to)->evaluate($this->project($from), $this->request($to), $evidence, [$source]));
        self::assertCount(1, $evidence->all());
    }

    /** @return iterable<string, array{int, int, string, string}> */
    public function unrelatedLegacySymbolProvider(): iterable
    {
        yield 'application Elixir function' => [7, 8, 'App\\elixir', 'function_call'];
        yield 'generic function inventory cannot prove the helper identity' => [7, 8, 'elixir', 'function_call'];
        yield 'unused Elixir import' => [7, 8, 'elixir', 'function_import'];
        yield 'unused closure import' => [8, 9, 'Illuminate\\Queue\\SerializableClosure', 'namespace_import'];
        yield 'unrelated closure class' => [8, 9, 'App\\SerializableClosure', 'instantiated_class'];
        yield 'replacement closure class' => [8, 9, 'Laravel\\SerializableClosure\\SerializableClosure', 'instantiated_class'];
        yield 'unused testing-trait import' => [9, 10, 'Illuminate\\Foundation\\Testing\\Concerns\\MocksApplicationServices', 'namespace_import'];
        yield 'unrelated testing trait' => [9, 10, 'Tests\\MocksApplicationServices', 'trait_reference'];
    }

    public function testQueueDispatchAndRemovedTestingTraitBothProduceFindings(): void
    {
        $evidence = new EvidenceLedger();
        $sources = [
            $this->usage($evidence, 'Illuminate\\Foundation\\Testing\\Concerns\\MocksApplicationServices', 'trait_reference'),
            $this->usage($evidence, 'dispatch_now', 'function_call'),
        ];
        $framework = new LaravelFrameworkIntegration();
        $findings = (new FrameworkRuleEngine())->evaluate([$framework], $this->project(9), $this->request(10), $evidence, $sources);
        $sourceFindings = array_values(array_filter($findings, static fn ($finding): bool => strpos($finding->summary(), 'dispatch_now') !== false || strpos($finding->summary(), 'MocksApplicationServices') !== false));

        self::assertCount(2, $sourceFindings);
        self::assertStringContainsString('dispatch_now', $sourceFindings[0]->summary());
        self::assertStringContainsString('MocksApplicationServices', $sourceFindings[1]->summary());
    }

    public function testRemovedSymbolRuleDoesNotGuessSymbolsForAnUnmodeledHop(): void
    {
        $evidence = new EvidenceLedger();
        $source = $this->usage($evidence, 'Illuminate\\Queue\\SerializableClosure', 'instantiated_class');

        self::assertNull($this->removedSymbolRule(10, 11)->evaluate($this->project(10), $this->request(11), $evidence, [$source]));
        self::assertCount(1, $evidence->all());
    }

    public function testRemovedUuidTraitHasSourceAndPinnedGuideEvidence(): void
    {
        $evidence = new EvidenceLedger();
        $source = $this->usage($evidence, 'Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids', 'trait_reference');
        $finding = $this->rule(11, 12)->evaluate($this->project(11), $this->request(12), $evidence, [$source]);

        self::assertNotNull($finding);
        self::assertSame('high', $finding->severity());
        self::assertStringContainsString('HasVersion7Uuids', $finding->summary());
        self::assertStringContainsString('HasUuids', $finding->summary());
        self::assertStringContainsString('removed', $finding->summary());
        self::assertContains($source->evidence()[0], $finding->evidence());
        $documentation = array_values(array_filter($evidence->all(), static fn (Evidence $item): bool => $item->evidenceClass() === Evidence::E4_MAINTAINER_DOCUMENTATION));
        self::assertCount(1, $documentation);
        self::assertStringContainsString('5b8c610735c8af96a3bda4e37a820b27dc40aee9', $documentation[0]->context()['source']);
        self::assertContains($documentation[0]->id(), $finding->evidence());
    }

    /** @dataProvider csrfReferenceProvider */
    public function testDeprecatedCsrfReferencesRemainAdvisories(string $symbol, string $usageType): void
    {
        $evidence = new EvidenceLedger();
        $source = $this->usage($evidence, $symbol, $usageType);
        $finding = $this->rule(12, 13)->evaluate($this->project(12), $this->request(13), $evidence, [$source]);

        self::assertNotNull($finding);
        self::assertSame('medium', $finding->severity());
        self::assertStringContainsString('Review', $finding->summary());
        self::assertStringContainsString('deprecated aliases remain available', $finding->summary());
        self::assertStringNotContainsString('before targeting', $finding->summary());
        self::assertContains($source->evidence()[0], $finding->evidence());
    }

    /** @return iterable<string, array{string, string}> */
    public function csrfReferenceProvider(): iterable
    {
        yield 'legacy middleware registration' => ['Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken', 'middleware_reference'];
        yield 'deprecated validation alias registration' => ['Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken', 'middleware_reference'];
        yield 'application middleware extends alias' => ['Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken', 'inheritance'];
        yield 'application middleware extends validation alias' => ['Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken', 'inheritance'];
    }

    /** @dataProvider unrelatedSourceProvider */
    public function testOnlyExactActiveFrameworkReferencesTriggerFindings(int $from, int $to, string $symbol, string $usageType): void
    {
        $evidence = new EvidenceLedger();
        $source = $this->usage($evidence, $symbol, $usageType);

        self::assertNull($this->rule($from, $to)->evaluate($this->project($from), $this->request($to), $evidence, [$source]));
        self::assertCount(1, $evidence->all());
    }

    /** @return iterable<string, array{int, int, string, string}> */
    public function unrelatedSourceProvider(): iterable
    {
        yield 'supported UUID trait' => [11, 12, 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids', 'trait_reference'];
        yield 'version-four UUID trait' => [11, 12, 'Illuminate\\Database\\Eloquent\\Concerns\\HasVersion4Uuids', 'trait_reference'];
        yield 'unrelated trait with same short name' => [11, 12, 'App\\Concerns\\HasVersion7Uuids', 'trait_reference'];
        yield 'unused UUID import' => [11, 12, 'Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids', 'namespace_import'];
        yield 'replacement middleware' => [12, 13, 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery', 'middleware_reference'];
        yield 'unrelated middleware with same short name' => [12, 13, 'App\\Http\\Middleware\\VerifyCsrfToken', 'inheritance'];
        yield 'unused middleware import' => [12, 13, 'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken', 'namespace_import'];
    }

    public function testRemovedTraitAppliesOnlyToItsAdjacentHopDuringMultiMajorAnalysis(): void
    {
        $evidence = new EvidenceLedger();
        $source = $this->usage($evidence, 'Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids', 'trait_reference');
        $rule = $this->rule(11, 12);
        $project = $this->project(10);
        $request = $this->request(13);

        self::assertNotNull($rule->evaluateForHop($project, $request, $evidence, new FrameworkHop(11, 12, FrameworkHop::SUPPORTED, 'laravel-11-to-12', [$source->evidence()[0]]), null, [$source]));
        self::assertNull($rule->evaluateForHop($project, $request, $evidence, new FrameworkHop(12, 13, FrameworkHop::SUPPORTED, 'laravel-12-to-13', [$source->evidence()[0]]), null, [$source]));
        self::assertNull($rule->evaluate($project, $request, $evidence, [$source]));
    }

    private function rule(int $from, int $to): LaravelHighSignalSourceRule
    {
        return new LaravelHighSignalSourceRule(new BuiltinRuleDefinition('test-source-rule', BuiltinRuleDefinition::HIGH_SIGNAL_SOURCE, [new RuleApplicability($from, $to)]));
    }

    private function removedSymbolRule(int $from, int $to): LaravelHighSignalSourceRule
    {
        return new LaravelHighSignalSourceRule(new BuiltinRuleDefinition('test-removed-source-rule', 'removed_source_symbols', [new RuleApplicability($from, $to)]));
    }

    private function usage(EvidenceLedger $evidence, string $symbol, string $usageType): SourceUsage
    {
        $source = $evidence->add('source-rule-test', Evidence::E3_PROJECT_SOURCE, 'A parser-derived source reference.', 'high', ['file' => 'app/Example.php', 'symbol' => $symbol, 'usage_type' => $usageType, 'line' => 8]);

        return new SourceUsage('app/Example.php', $symbol, $usageType, [$source->id()], 8);
    }

    private function project(int $major): ProjectState
    {
        return new ProjectState(__DIR__, new ComposerJson(['require' => ['laravel/framework' => '^' . $major . '.0']]), new ComposerLock(['packages' => [['name' => 'laravel/framework', 'version' => $major . '.0.0']]]));
    }

    private function request(int $major): UpgradeRequest
    {
        return new UpgradeRequest(__DIR__, [new UpgradeTarget('laravel/framework', '^' . $major . '.0')]);
    }
}
