<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Reporting;

/** Display buckets for recorded uncertainty prose, without deriving new findings. */
final class SummaryUncertainties
{
    /** @var list<string> */
    private array $lines = [];

    /** @param list<string> $uncertainties */
    public function __construct(array $uncertainties)
    {
        $patterns = [
            'input' => '/input|drift|changed during|inconsisten|lock.*(?:invalid|missing|skip|unusable)/i',
            // Keep source caps' exact counts and evidence separate from ordinary source warnings.
            'source/usage cap' => '/^Source scanning reached the [0-9]+-usage safety limit;/i',
            'source/file cap' => '/^Source scanning reached the [0-9]+-file safety limit;/i',
            'source/per-file bytes' => '/^Source scanning reached the [0-9]+-byte per-file byte limit;/i',
            'source/aggregate bytes' => '/^Source scanning reached the [0-9]+-byte aggregate byte limit;/i',
            'source/discovery paths' => '/^Composer source root "autoload" was not discovered: the [0-9]+-path metadata safety limit was reached;/i',
            'source' => '/source|scan|pars(?:e|er)|syntax|usage/i',
            'operational' => '/timeout|workspace|cleanup|process|repository.*(?:fail|unavailable)|Composer.*(?:missing|unavailable|not found)/i',
            'stage/coverage' => '/stage|hop|framework|rule.?pack|unsupported/i',
            'platform' => '/platform|extension/i',
        ];
        /** @var array<string, list<string>> $groups */
        $groups = [];
        $recorded = 0;
        foreach ($uncertainties as $uncertainty) {
            if (str_starts_with($uncertainty, 'Restricted Composer execution uses sanitized analyzer-owned Composer state')
                || str_starts_with($uncertainty, 'Compatible Composer execution may inherit')
                || str_starts_with($uncertainty, 'Dependency resolution does not prove application runtime compatibility')) {
                continue;
            }
            ++$recorded;
            $category = 'other';
            foreach ($patterns as $label => $pattern) {
                if (preg_match($pattern, $uncertainty) === 1) {
                    $category = $label;
                    break;
                }
            }
            $groups[$category][] = $uncertainty;
        }
        foreach (array_merge(array_keys($patterns), ['other']) as $category) {
            $group = $groups[$category] ?? [];
            if ($group === []) {
                continue;
            }
            $representative = $group[0];
            foreach ($group as $uncertainty) {
                if (preg_match('/omit|incomplete|limit|truncat|fail|timeout|timed out|unavailable|missing|not scan|not.*pars|drift|changed|unsupported/i', $uncertainty) === 1) {
                    $representative = $uncertainty;
                    break;
                }
            }
            $this->lines[] = sprintf('- Limit [%s%s]: %s', $category, count($group) > 1 ? '; ' . count($group) . ' recorded' : '', $representative);
        }
        $hidden = $recorded - count($this->lines);
        if ($hidden > 0) {
            $this->lines[] = sprintf('Additional uncertainties in full details: %d.', $hidden);
        }
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->lines;
    }
}
