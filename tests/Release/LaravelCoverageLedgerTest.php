<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Tests\Release;

use PhpUpgradePreflight\Laravel\Catalog\LaravelRuleCatalog;
use PHPUnit\Framework\TestCase;

final class LaravelCoverageLedgerTest extends TestCase
{
    public function testGuideAccountingUsesPinnedSourcesAndExistingRuleKeys(): void
    {
        $keys = array_map(static fn ($rule): string => $rule->key(), LaravelRuleCatalog::v0_2()->rules());
        $legacy = $this->read('legacy-guide-audit.json');
        $modern = $this->read('modern-guide-audit.json');
        $counts = [];

        foreach ($legacy['guides'] as $guide) {
            $this->assertPinnedGuide($guide['source_url'], $guide['source_commit']);
            $counts[$guide['target_major']] = count($guide['coverage_items']);
            foreach ($guide['coverage_items'] as $item) {
                $this->assertItem($item['heading'], $item['classification'], $item['rule_keys'], $item['manual_verification_reason'], $keys);
            }
        }
        foreach ($modern['guides'] as $guide) {
            $this->assertPinnedGuide($guide['guide_url'], $guide['guide_commit']);
            $counts[$guide['to_major']] = count($guide['sections']);
            foreach ($guide['sections'] as $item) {
                $this->assertItem($item['heading'], $item['classification'], $item['rule_keys'], $item['reason'], $keys);
            }
        }

        self::assertSame([8 => 44, 9 => 79, 10 => 40, 11 => 55, 12 => 29, 13 => 52], $counts);
    }

    /**
     * @param list<string> $ruleKeys
     * @param list<string> $catalogKeys
     */
    private function assertItem(string $heading, string $classification, array $ruleKeys, string $reason, array $catalogKeys): void
    {
        self::assertNotSame('', trim($heading));
        self::assertContains($classification, ['implemented', 'patch-correction', 'manual-review', 'not-applicable', 'contract-dependent']);
        self::assertNotSame('', trim($reason), $heading);
        foreach ($ruleKeys as $key) {
            self::assertContains($key, $catalogKeys, $heading . ': ' . $key);
        }
    }

    private function assertPinnedGuide(string $url, string $commit): void
    {
        self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $commit);
        self::assertSame('https://github.com/laravel/docs/blob/' . $commit . '/upgrade.md', $url);
    }

    /** @return array<string, mixed> */
    private function read(string $name): array
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/laravel-coverage/' . $name);
        self::assertNotFalse($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['schema_version']);

        return $decoded;
    }
}
