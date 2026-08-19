# Writing a Framework Adapter

Framework adapters add framework-specific detection, rules, source paths, and optional advanced capabilities without changing the CLI. This guide describes the v0.3 contract as verified on **2026-08-19**.

## What you are building

An adapter is a Composer library installed beside `php-upgrade-preflight/cli`. Its entry class must implement the required `FrameworkIntegration` interface:

```php
interface FrameworkIntegration
{
    public function name(): string;
    public function detect(ProjectState $project): FrameworkDetection;
    public function rules(): iterable;
    public function defaultSourcePaths(ProjectState $project): array;
}
```

The four methods answer four simple questions:

| Method | Question |
|---|---|
| `name()` | What stable name will users pass to `--framework`? |
| `detect()` | Does the target project use this framework, and what version evidence is available? |
| `rules()` | Which compatibility checks should run? |
| `defaultSourcePaths()` | Which project-relative directories should be scanned when the user supplies no `--source`? |

Core remains framework-neutral. Do not put framework rules into Core merely to avoid creating an adapter.

## Minimal package

`composer.json` advertises one or more integration classes:

```json
{
  "name": "acme/example-adapter",
  "type": "library",
  "require": {
    "php": "^8.0",
    "php-upgrade-preflight/core": "^0.3"
  },
  "autoload": {
    "psr-4": {
      "Acme\\ExampleAdapter\\": "src/"
    }
  },
  "extra": {
    "php-upgrade-preflight": {
      "framework-adapters": [
        "Acme\\ExampleAdapter\\ExampleFrameworkIntegration"
      ]
    }
  }
}
```

The list must be nonempty and contain trimmed, nonempty fully qualified class names. Each class must autoload, be instantiable without required constructor arguments, implement `FrameworkIntegration`, and return a trimmed nonempty name.

A minimal implementation can start conservatively:

```php
<?php

declare(strict_types=1);

namespace Acme\ExampleAdapter;

use PhpUpgradePreflight\Core\Framework\FrameworkDetection;
use PhpUpgradePreflight\Core\Framework\FrameworkIntegration;
use PhpUpgradePreflight\Core\Model\ProjectState;

final class ExampleFrameworkIntegration implements FrameworkIntegration
{
    public function name(): string
    {
        return 'example';
    }

    public function detect(ProjectState $project): FrameworkDetection
    {
        $constraint = $project->composerJson()->rootRequirements()['acme/framework'] ?? null;
        $locked = $project->composerLock()->package('acme/framework');

        return new FrameworkDetection(
            $this->name(),
            $constraint !== null || $locked !== null,
            $locked !== null ? $locked->version() : $constraint
        );
    }

    public function rules(): iterable
    {
        return [];
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['src', 'config', 'tests'];
    }
}
```

Detection should inspect metadata, not boot or execute the target application. Source paths must be project-relative and bounded.

## Discovery and activation

After installing both packages in one tools project:

```bash
composer require php-upgrade-preflight/cli acme/example-adapter
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.3
```

Without `--framework`, every discovered integration may detect the project; only positive detections become active. Explicit selection is case-insensitive, bypasses detection, and may be repeated:

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --framework=example \
  --target=acme/framework:^3.0
```

Composer packages are discovered in lexical package-name order. Active integrations are then ordered case-insensitively by adapter name, with class name as the tie-breaker. Do not rely on manifest declaration order.

An unreadable adapter manifest skips only that package and produces a diagnostic. An accepted manifest that advertises a missing, invalid, duplicate, or colliding class fails analyzer construction. An explicitly requested unavailable adapter is invalid invocation exit `2`.

## Add compatibility rules

Each value from `rules()` implements `CompatibilityRule`:

```php
public function evaluate(
    ProjectState $project,
    UpgradeRequest $request,
    EvidenceLedger $evidence,
    array $sourceUsages = []
): ?CompatibilityFinding;
```

Return `null` when the rule has no relevant result. A finding must be backed by evidence IDs. Severity and evidence confidence accept exactly `low`, `medium`, or `high`.

A good rule is deterministic and narrow:

```php
$locked = $project->composerLock()->package('acme/legacy-plugin');
if ($locked === null) {
    return null;
}

$evidenceId = $evidence->add(
    'example-legacy-plugin',
    Evidence::E2_PACKAGE_METADATA,
    'The project locks acme/legacy-plugin and its target compatibility needs review.',
    'high',
    ['package' => 'acme/legacy-plugin', 'version' => $locked->version()]
)->id();

return new CompatibilityFinding(
    'example',
    'medium',
    'Review the legacy plugin before upgrading Example Framework.',
    [$evidenceId]
);
```

Never copy credentials, repository URLs containing authentication, absolute local paths, or unnecessary source excerpts into evidence context. A throwing rule is contained as uncertainty so the report can finish, but this is degradation, not a supported operating mode.

Implement `HopAwareCompatibilityRule` when the result belongs to a specific supported transition. Its `evaluateForHop()` is called per hop when transition guidance exists; otherwise Core falls back to ordinary `evaluate()`.

## Optional capabilities

### Transition guidance

Implement `FrameworkTransitionProvider` to return an evidence-backed `FrameworkGuidance` from `assessTransition()`. Guidance says whether documented framework migration rules cover a route. It is independent of Composer feasibility.

Model gaps honestly. A covered prefix followed by a missing hop is partial support; do not silently jump across the gap.

### Package families

Implement `PackageFamilyClassifier` when report consumers benefit from adapter-owned families:

```php
public function packageFamilies(string $packageName): array
{
    return str_starts_with(strtolower($packageName), 'acme/')
        ? ['example-ecosystem']
        : [];
}
```

Return stable names and deterministic order.

### Framework-shaped source usage

Implement `SourceUsageVisitorProvider` and return fresh `SourceUsageCollector` instances for each project-relative file. A usage record is exactly:

```php
['symbol' => 'Acme\\Example\\Provider', 'usage_type' => 'service_provider', 'line' => 12]
```

The adapter owns the `usage_type` vocabulary; Core does not interpret it. Use lowercase underscore-separated values, report an exact line, omit guesses, and never share a stateful collector across files. Collectors see names after `NameResolver` and must not rewrite the shared AST.

### Staged Composer targets

Implement `FrameworkStageTargetProvider` only when the adapter can produce a complete, evidence-backed adjacent-hop plan. Every stage needs a stable lowercase ID, matching provider/framework identity, exact canonical package constraints, an exact analysis PHP value supported by request evidence, and referenced evidence for all decisions.

Broad minimum constraints are not exact platform evidence. If neither exact target PHP nor exact current PHP satisfies a hop, return an unavailable plan. Never edit the original project or run an independent unbounded solver. Core owns temporary workspaces and staged execution.

Only one active stage-target provider may run in v0.3. Multiple providers cause staged solving to be skipped while ordinary detection, rules, and guidance continue.

## Testing checklist

Use committed offline fixtures and prove:

- metadata-only discovery with no CLI source edit;
- automatic detection and explicit `--framework` selection;
- deterministic adapter, rule, evidence, and source-usage order;
- malformed metadata and class/name collisions fail as documented;
- every rule has positive, negative, and throwing-path coverage;
- transition gaps and ambiguous versions are explicit;
- stage IDs, exact constraints, PHP provenance, adjacency, and evidence validate;
- two active stage providers produce the documented collision result;
- source collectors are isolated and contained on failure;
- target files are byte-for-byte unchanged;
- canonical JSON and Markdown contain no synthetic secrets or absolute paths.

The repository's `packages/test-adapter` is the full v0.3 reference fixture. `packages/legacy-test-adapter` proves that the older required interfaces remain usable with Core `^0.3`, while staged resolution is unavailable.

## Documentation and release policy

Document the adapter name, package metadata, detected packages, default paths, rule vocabulary, supported transitions, staged limitations, privacy boundaries, and verified examples.

**Mandatory:** before creating any release tag, update every affected Wiki page in the same release change. Codex, Claude, and any other coding agent must treat Wiki updates as a required release deliverable. A changelog or `docs/releases/` update alone is not sufficient. As of 2026-08-19 this is a review policy; `verify-release.php` does not automatically prove Wiki freshness.

## Common mistakes

- Registering the class in CLI source instead of Composer metadata.
- Returning absolute paths from `defaultSourcePaths()`.
- Treating detection as proof of a known major version.
- Returning findings without ledger-backed evidence.
- Inventing `critical` or numeric severity values.
- Treating guidance as Composer or runtime feasibility.
- Deriving exact stage PHP from a minimum such as `^8.2`.
- Booting the target framework or modifying its Composer files.
- Letting a source collector rewrite the AST.

## End-to-end adapter example

Suppose `acme/framework` is the framework package and `acme/plugin` is a related ecosystem package.

A safe first release can implement only the base integration:

1. Detect `acme/framework` from root or lock metadata.
2. Return `acme` from `name()`.
3. Provide `src`, `app`, and `tests` as project-relative defaults.
4. Yield one evidence-backed compatibility rule.
5. Advertise the integration class in Composer metadata.

Do not add staged solving until exact adjacent targets and PHP requirements are maintained.

Do not add custom source vocabulary until it supports a real rule.

This incremental approach keeps unsupported capabilities visibly unavailable.

### Expected activation behavior

With the package installed, automatic activation occurs only when detection succeeds.

```bash
vendor/bin/upgrade-intel analyze \
  --path=. \
  --target=acme/framework:^2.0
```

Explicit selection requires the integration name to be available:

```bash
vendor/bin/upgrade-intel analyze \
  --path=. \
  --target=acme/framework:^2.0 \
  --framework=acme
```

If discovery skipped the package because its manifest was invalid, explicit selection fails and names the skipped package reason.

Automatic detection must never require booting the target framework.

## Evidence design checklist

Create evidence where the fact is observed.

Use E2 for Composer package metadata.

Use E3 for AST-derived project source.

Use E4 for maintained framework documentation encoded in the adapter.

Use E5 only for a clearly described heuristic.

Keep severity separate from confidence.

Use structured context fields for package, constraint, file, line, transition, and source URL data.

Reference every evidence ID from a finding, guidance item, hop, stage, plan item, or explicit uncertainty.

Core rejects missing references and orphan evidence when the final report is built.

## Compatibility evolution

New adapter capabilities should normally be optional Core interfaces.

The repository proves this with two fixture packages.

`test-adapter` implements current transition, staging, package-family, and source-rule behavior.

`legacy-test-adapter` implements only the older base and transition contracts.

When adding a capability, verify both fixtures:

- the current fixture exercises the new path;
- the legacy fixture still loads;
- unavailable capability is reported as skipped or absent, not as a broken adapter;
- existing detection and guidance remain useful.

See [Test Adapters](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Test-Adapters) for the exact comparison.

## Review handoff for managers

An adapter support statement should name:

- detected framework package families;
- covered source and target versions;
- direct versus adjacent guidance coverage;
- whether staged targets are available;
- source paths and custom usage vocabulary;
- important unsupported transitions;
- source documents and their review date;
- tests and fixtures proving the claims.

“Adapter installed” is not the same as “all upgrades supported.”

“Guidance supported” is not the same as “Composer resolution feasible.”

“Composer feasible” is not the same as “application runtime compatible.”

## Publication checklist

Before publishing an adapter package:

1. Install it with the generic CLI in a clean consumer project.
2. Confirm Composer metadata discovery without direct class registration.
3. Confirm automatic and explicit activation.
4. Test malformed project input and ambiguous framework versions.
5. Verify reports contain no absolute paths, credentials, or unbounded source excerpts.
6. Verify JSON and Markdown agree on findings and guidance.
7. Document the package's Core version constraint and supported adapter capabilities.
8. Document exact limitations and unsupported transitions.
9. Update Wiki examples when the adapter release changes behavior.

For repository release tags, follow [Release Wiki Strategy](https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Release-Wiki-Strategy) before declaring the release complete.
