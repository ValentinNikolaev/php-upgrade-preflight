<?php

declare(strict_types=1);

namespace PhpUpgradePreflight\Laravel\Catalog;

use Composer\Semver\VersionParser;

final class LaravelRuleCatalogValidator
{
    /** @return list<string> */
    public function validate(LaravelRuleCatalog $catalog): array
    {
        /** @var list<string> $errors */
        $errors = [];
        $keys = [];
        $advice = [];
        $targetMajors = [];
        $transitionCoordinates = [];

        foreach ($catalog->targets() as $target) {
            $this->recordKey($target->key(), $keys, $errors);
            if (isset($targetMajors[$target->major()])) {
                $errors[] = sprintf('Duplicate target major: %d.', $target->major());
            }
            $targetMajors[$target->major()] = true;
            $this->validateConstraint($target->phpConstraint(), $target->key() . ' PHP', $errors);
            $this->validateSources($target->phpSources(), $target->key() . ' PHP', $errors);
            if ($target->symfonyConstraint() !== null) {
                $this->validateConstraint($target->symfonyConstraint(), $target->key() . ' Symfony', $errors);
                $this->validateSources($target->symfonySources(), $target->key() . ' Symfony', $errors);
            }
            foreach ($target->symfonyComponentConstraints() as $package => $constraint) {
                $this->validateConstraint($constraint, $target->key() . ' ' . $package, $errors);
            }
        }

        foreach ($catalog->transitions() as $transition) {
            $this->recordKey($transition->key(), $keys, $errors);
            $coordinate = $transition->kind() . '@' . $transition->sourceMajor() . ':' . $transition->targetMajor();
            if (isset($transitionCoordinates[$coordinate])) {
                $errors[] = sprintf('Duplicate transition coordinate: %s.', $coordinate);
            }
            $transitionCoordinates[$coordinate] = true;
            $this->validateSources($transition->sources(), $transition->key(), $errors);
        }

        $unsupportedGap = null;
        for ($major = $catalog->minimumMajor(); $major < $catalog->maximumMajor(); ++$major) {
            $adjacent = $catalog->transition($major, $major + 1, TransitionDefinition::ADJACENT);
            if ($adjacent === null) {
                $errors[] = sprintf('Catalog has an unsupported gap: adjacent transition %d:%d is not declared.', $major, $major + 1);
                $unsupportedGap = $unsupportedGap ?? ($major . ':' . ($major + 1));
            } elseif (!$adjacent->isSupported()) {
                $unsupportedGap = $unsupportedGap ?? ($major . ':' . ($major + 1));
            } elseif ($unsupportedGap !== null) {
                $errors[] = sprintf(
                    'Catalog supports adjacent transition %d:%d after unsupported gap %s.',
                    $major,
                    $major + 1,
                    $unsupportedGap
                );
            }
            if ($catalog->target($major + 1) === null) {
                $errors[] = sprintf('Catalog has an unsupported gap: target Laravel %d is not declared.', $major + 1);
            }
        }

        $ruleValidators = $this->ruleValidators($catalog, $keys, $advice, $errors);
        foreach ($catalog->rules() as $rule) {
            $ruleValidator = $ruleValidators[get_class($rule)] ?? null;
            if ($ruleValidator === null) {
                $errors[] = sprintf('Unsupported catalog rule definition: %s.', get_class($rule));

                continue;
            }
            $this->recordKey($rule->key(), $keys, $errors);
            $ruleValidator($rule);
        }

        foreach ($catalog->skeletonPatterns() as $pattern) {
            $this->recordKey($pattern->key(), $keys, $errors);
            if ($pattern->file() === '' || $pattern->usageTypes() === []) {
                $errors[] = sprintf('Skeleton pattern %s is incomplete.', $pattern->key());
            }
        }

        return $errors;
    }

    public function assertValid(LaravelRuleCatalog $catalog): void
    {
        $errors = $this->validate($catalog);
        if ($errors !== []) {
            throw new \LogicException("Invalid Laravel rule catalog:\n- " . implode("\n- ", $errors));
        }
    }

    /**
     * The one dispatch table over rule-definition subtypes. A new subtype needs a
     * single entry here, and an unmapped subtype is reported instead of skipped.
     *
     * @param array<string, true> $keys
     * @param array<string, string> $advice
     * @param list<string> $errors
     *
     * @return array<class-string<RuleDefinition>, \Closure(RuleDefinition): void>
     */
    private function ruleValidators(
        LaravelRuleCatalog $catalog,
        array &$keys,
        array &$advice,
        array &$errors
    ): array {
        return [
            BuiltinRuleDefinition::class => function (RuleDefinition $rule) use ($catalog, &$errors): void {
                if ($rule instanceof BuiltinRuleDefinition) {
                    $this->validateBuiltinRule($catalog, $rule, $errors);
                }
            },
            PackageRuleDefinition::class => function (RuleDefinition $rule) use ($catalog, &$keys, &$advice, &$errors): void {
                if ($rule instanceof PackageRuleDefinition) {
                    $this->validatePackageRule($catalog, $rule, $keys, $advice, $errors);
                }
            },
            PackageAdvisoryDefinition::class => function (RuleDefinition $rule) use ($catalog, &$advice, &$errors): void {
                if ($rule instanceof PackageAdvisoryDefinition) {
                    $this->validatePackageAdvisory($catalog, $rule, $advice, $errors);
                }
            },
        ];
    }

    /** @param list<string> $errors */
    private function validateBuiltinRule(
        LaravelRuleCatalog $catalog,
        BuiltinRuleDefinition $rule,
        array &$errors
    ): void {
        if (!in_array($rule->rule(), [
            BuiltinRuleDefinition::FRAMEWORK_CONSTRAINT,
            BuiltinRuleDefinition::PHP_CONSTRAINT,
            BuiltinRuleDefinition::SYMFONY_CONSTRAINT,
            BuiltinRuleDefinition::ILLUMINATE_SUPPORT,
            BuiltinRuleDefinition::SKELETON,
            BuiltinRuleDefinition::COMPOSER_VERSION,
            BuiltinRuleDefinition::CURL_EXTENSION,
            BuiltinRuleDefinition::HIGH_SIGNAL_SOURCE,
        ], true)) {
            $errors[] = sprintf('Unsupported built-in rule type for %s: %s.', $rule->key(), $rule->rule());
        }
        $this->validateApplicability($catalog, $rule->key(), $rule->applicability(), $errors);
    }

    /**
     * @param array<string, true> $keys
     * @param array<string, string> $advice
     * @param list<string> $errors
     */
    private function validatePackageRule(
        LaravelRuleCatalog $catalog,
        PackageRuleDefinition $rule,
        array &$keys,
        array &$advice,
        array &$errors
    ): void {
        if ($rule->guidance() === []) {
            $errors[] = sprintf('Package rule %s has no guidance.', $rule->key());
        }
        $package = null;
        foreach ($rule->guidance() as $guidance) {
            if ($package !== null && $package !== $guidance->package()) {
                $errors[] = sprintf('Package rule %s mixes guidance for multiple packages.', $rule->key());
            }
            $package = $guidance->package();
            $this->recordKey($guidance->key(), $keys, $errors);
            $this->validateConstraint($guidance->compatibleConstraint(), $guidance->key(), $errors);
            $this->validateSeverity($guidance->severity(), $guidance->key(), $errors);
            $this->validateSources($guidance->sources(), $guidance->key(), $errors);
            $this->validateAdviceApplicability($catalog, $guidance->key(), $guidance->applicability(), $errors);
            $this->recordAdvice($guidance->package(), $guidance->applicability(), $guidance->key(), $advice, $errors);
        }
    }

    /**
     * @param array<string, string> $advice
     * @param list<string> $errors
     */
    private function validatePackageAdvisory(
        LaravelRuleCatalog $catalog,
        PackageAdvisoryDefinition $rule,
        array &$advice,
        array &$errors
    ): void {
        if (!PackageAdvisoryDefinition::isSupportedAction($rule->action())) {
            $errors[] = sprintf('Unsupported package advisory action for %s: %s.', $rule->key(), $rule->action());
        }
        $expectedPackage = $rule->expectedPackage();
        if ($expectedPackage !== null && $rule->package() !== $expectedPackage) {
            $errors[] = sprintf(
                'Package advisory %s uses action %s for %s; expected %s.',
                $rule->key(),
                $rule->action(),
                $rule->package(),
                $expectedPackage
            );
        }
        $this->validateSeverity($rule->severity(), $rule->key(), $errors);
        $this->validateSources($rule->sources(), $rule->key(), $errors);
        $this->validateAdviceApplicability($catalog, $rule->key(), $rule->applicability(), $errors);
        if ($rule->isExclusivePackageAdvice()) {
            $this->recordAdvice($rule->package(), $rule->applicability(), $rule->key(), $advice, $errors);
        }
    }

    /**
     * @param array<string, true> $keys
     * @param list<string> $errors
     */
    private function recordKey(string $key, array &$keys, array &$errors): void
    {
        if (isset($keys[$key])) {
            $errors[] = sprintf('Duplicate catalog key: %s.', $key);
        }
        $keys[$key] = true;
    }

    /** @param list<string> $errors */
    private function validateConstraint(string $constraint, string $owner, array &$errors): void
    {
        try {
            (new VersionParser())->parseConstraints($constraint);
        } catch (\UnexpectedValueException $exception) {
            $errors[] = sprintf('Invalid SemVer constraint for %s: %s.', $owner, $constraint);
        }
    }

    /** @param list<string> $errors */
    private function validateSeverity(string $severity, string $owner, array &$errors): void
    {
        if (!in_array($severity, ['low', 'medium', 'high'], true)) {
            $errors[] = sprintf('Invalid severity for %s: %s.', $owner, $severity);
        }
    }

    /**
     * @param list<string> $sources
     * @param list<string> $errors
     */
    private function validateSources(array $sources, string $owner, array &$errors): void
    {
        if ($sources === []) {
            $errors[] = sprintf('Missing evidence source for %s.', $owner);

            return;
        }

        foreach ($sources as $source) {
            if (filter_var($source, FILTER_VALIDATE_URL) === false) {
                $errors[] = sprintf('Invalid evidence source for %s: %s.', $owner, $source);
            }
        }
    }

    /**
     * @param list<RuleApplicability> $applicability
     * @param list<string> $errors
     */
    private function validateApplicability(
        LaravelRuleCatalog $catalog,
        string $owner,
        array $applicability,
        array &$errors
    ): void {
        $seen = [];
        foreach ($applicability as $item) {
            if (isset($seen[$item->key()])) {
                $errors[] = sprintf('Duplicate applicability %s for %s.', $item->key(), $owner);
            }
            $seen[$item->key()] = true;
            $this->validateAdviceApplicability($catalog, $owner, $item, $errors);
        }
    }

    /** @param list<string> $errors */
    private function validateAdviceApplicability(
        LaravelRuleCatalog $catalog,
        string $owner,
        RuleApplicability $applicability,
        array &$errors
    ): void {
        $direct = $catalog->transition(
            $applicability->sourceMajor(),
            $applicability->targetMajor(),
            TransitionDefinition::DIRECT
        );
        $adjacent = $catalog->transition(
            $applicability->sourceMajor(),
            $applicability->targetMajor(),
            TransitionDefinition::ADJACENT
        );
        if (($direct === null || !$direct->isSupported()) && ($adjacent === null || !$adjacent->isSupported())) {
            $errors[] = sprintf('Rule %s applies to unsupported transition %s.', $owner, $applicability->key());
        }
    }

    /**
     * @param array<string, string> $advice
     * @param list<string> $errors
     */
    private function recordAdvice(
        string $package,
        RuleApplicability $applicability,
        string $owner,
        array &$advice,
        array &$errors
    ): void {
        $key = strtolower($package) . '@' . $applicability->key();
        if (isset($advice[$key])) {
            $errors[] = sprintf(
                'Contradictory package advice for %s on transition %s: %s and %s.',
                strtolower($package),
                $applicability->key(),
                $advice[$key],
                $owner
            );
        }
        $advice[$key] = $owner;
    }
}
