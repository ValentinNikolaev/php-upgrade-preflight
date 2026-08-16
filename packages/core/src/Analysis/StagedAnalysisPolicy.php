<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Core\Analysis;

final class StagedAnalysisPolicy
{
    public const MAX_HOPS = 6;
    public const MAX_ATTEMPTS_PER_STAGE = 3;
    public const MAX_SCENARIOS = self::MAX_HOPS * self::MAX_ATTEMPTS_PER_STAGE;
    public const MAX_COMPOSER_PROCESSES = 128;
    public const SCENARIO_TIMEOUT_SECONDS = 300;
    public const STAGE_TIMEOUT_SECONDS = self::MAX_ATTEMPTS_PER_STAGE * self::SCENARIO_TIMEOUT_SECONDS;
    public const AGGREGATE_TIMEOUT_SECONDS = 1800;
    public const MEMORY_BUDGET_BYTES = 268435456;
    public const JSON_REPORT_BUDGET_BYTES = 524288;
    public const MARKDOWN_REPORT_BUDGET_BYTES = 262144;
}
