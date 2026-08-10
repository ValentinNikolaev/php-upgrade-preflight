<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

final class BuiltinRuleDefinition implements RuleDefinition
{
    public const FRAMEWORK_CONSTRAINT = 'framework_constraint';
    public const PHP_CONSTRAINT = 'php_constraint';
    public const SYMFONY_CONSTRAINT = 'symfony_constraint';
    public const ILLUMINATE_SUPPORT = 'illuminate_support';
    public const SKELETON = 'skeleton';

    private string $key;
    private string $rule;
    /** @var list<RuleApplicability> */
    private array $applicability;

    /** @param list<RuleApplicability> $applicability */
    public function __construct(string $key, string $rule, array $applicability)
    {
        $this->key = $key;
        $this->rule = $rule;
        $this->applicability = $applicability;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function rule(): string
    {
        return $this->rule;
    }

    /** @return list<RuleApplicability> */
    public function applicability(): array
    {
        return $this->applicability;
    }

    public function appliesTo(int $sourceMajor, int $targetMajor): bool
    {
        foreach ($this->applicability as $applicability) {
            if ($applicability->matches($sourceMajor, $targetMajor)) {
                return true;
            }
        }

        return false;
    }
}
