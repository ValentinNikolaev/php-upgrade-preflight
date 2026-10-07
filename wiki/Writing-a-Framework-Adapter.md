# Writing a Framework Adapter

An adapter supplies framework knowledge to Core: detection, rules, default source paths, and any optional capabilities it can support. The generic CLI discovers it from Composer metadata, so an adapter package does not need a CLI source change. This guide covers the repository's v0.3 adapter contract.

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

The four methods give Core the minimum it needs:

| Method | Question |
|---|---|
| `name()` | What stable name will users pass to `--framework`? |
| `detect()` | Does the target project use this framework, and what version evidence is available? |
| `rules()` | Which compatibility checks should run? |
| `defaultSourcePaths()` | Which project-relative directories should be scanned when the user supplies no `--source`? |

Keep framework rules in the adapter so Core can use the same contracts for any framework.

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

Detection reads Composer metadata without booting the target application. Return project-relative source paths that the analyzer can scan within its bounds.

## Discovery and activation

After installing both packages in one tools project:

```bash
composer require php-upgrade-preflight/cli acme/example-adapter
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.3
```

Without `--framework`, Core asks discovered integrations to detect the project and activates positive matches. Explicit names are case-insensitive, can be repeated, and activate available integrations without a positive detection:

```bash
vendor/bin/upgrade-intel analyze \
  --path=/work/app \
  --framework=example \
  --target=acme/framework:^3.0
```

Discovery visits Composer packages in lexical package-name order. Active integrations are sorted by name without regard to case, then by class name. Manifest order is not an execution order.

An unreadable manifest skips that package and produces a diagnostic. Once a manifest is accepted, a missing or invalid class, duplicate class, or colliding name fails analyzer construction. Requesting an unavailable adapter explicitly is an invalid invocation with exit code `2`.

Installed adapters run as PHP code in the analyzer process. They have its filesystem, network, environment, and credential privileges, so install only adapters you trust with that access. Catching an exception can preserve the report, but it cannot isolate an adapter or undo side effects.

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

Return `null` when the rule has no relevant result. A finding cites registered evidence IDs. Severity and evidence confidence each use `low`, `medium`, or `high`, but answer different questions.

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

Keep credentials, authenticated repository URLs, absolute local paths, and unnecessary source excerpts out of evidence context. Core contains a throwing rule as uncertainty and continues, but its check has not succeeded.

Core contains runtime failures from automatic detection, default source paths, transition assessment, package-family classification, and source collectors. It omits the affected contribution, records evidence-backed uncertainty, and continues with other adapters. Explicit selection bypasses `detect()`. Invalid registration and unavailable explicitly requested names still fail immediately as input errors.

Use `HopAwareCompatibilityRule` when a finding belongs to a particular transition. Core calls `evaluateForHop()` for each hop with guidance and falls back to `evaluate()` when that guidance is absent.

## Optional capabilities

### Transition guidance

`FrameworkTransitionProvider::assessTransition()` returns evidence-backed `FrameworkGuidance` about the route covered by the adapter's migration rules. Composer feasibility is a separate result.

If guidance covers the first hops but misses a later one, report partial support at the gap. A jump across it would overstate coverage.

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

Keep family names and their order stable so repeated reports remain comparable.

### Framework-shaped source usage

Implement `SourceUsageVisitorProvider` and return fresh `SourceUsageCollector` instances for each project-relative file. A usage record is exactly:

```php
['symbol' => 'Acme\\Example\\Provider', 'usage_type' => 'service_provider', 'line' => 12]
```

The adapter defines `usage_type`. Core stores it without interpreting its framework meaning. Use lowercase underscore-separated values and exact source lines. Give each file a fresh collector, omit guessed usages, and leave the shared AST unchanged after `NameResolver` has processed names.

### Staged Composer targets

Implement `FrameworkStageTargetProvider` only when the adapter can produce a complete, evidence-backed adjacent-hop plan. Every stage needs a stable lowercase ID, matching provider/framework identity, exact canonical package constraints, an exact analysis PHP value supported by request evidence, and referenced evidence for all decisions.

A minimum such as `^8.2` does not identify the exact PHP value to simulate. If neither exact target nor current PHP supports a hop, return an unavailable plan. Core owns the temporary workspaces and bounded staged execution.

The v0.3 staging path accepts one active stage-target provider. With more than one, Core skips staged solving but still runs detection, rules, and guidance.

## Testing checklist

Use committed offline fixtures and prove:

- metadata-only discovery with no CLI source edit
- automatic detection and explicit `--framework` selection
- deterministic adapter, rule, evidence, and source-usage order
- malformed metadata and class/name collisions fail as documented
- every rule has positive, negative, and throwing-path coverage
- transition gaps and ambiguous versions are explicit
- stage IDs, exact constraints, PHP provenance, adjacency, and evidence validate
- two active stage providers produce the documented collision result
- source collectors are isolated and contained on failure
- target files are byte-for-byte unchanged
- canonical JSON and Markdown contain no synthetic secrets or absolute paths.

The repository's `packages/test-adapter` is the full v0.3 reference fixture. `packages/legacy-test-adapter` proves that the older required interfaces remain usable with Core `^0.3`, while staged resolution is unavailable.

## Documentation and release policy

Document the adapter name, Composer metadata, detected packages, default paths, rule vocabulary, covered transitions, staging limits, privacy boundaries, and examples you have tested.

Before creating a release tag, update the affected Wiki pages and follow [[Release Wiki Strategy|Release-Wiki-Strategy]]. The release process needs published or reviewed Wiki evidence alongside the code and release notes. A changelog entry alone does not establish that the Wiki matches the release.

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

That way, the report can show which capabilities the adapter actually supplies.

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

Create evidence at the point where you observe the fact.

Use E2 for Composer package metadata.

Use E3 for AST-derived project source.

Use E4 for maintained framework documentation encoded in the adapter.

Use E5 only for a clearly described heuristic.

Keep severity separate from confidence.

Use structured context fields for package, constraint, file, line, transition, and source URL data.

Reference every evidence ID from a finding, guidance item, hop, stage, plan item, or explicit uncertainty.

The final report rejects references to missing IDs and evidence with no report claim.

## Compatibility evolution

New adapter capabilities should normally be optional Core interfaces.

The repository proves this with two fixture packages.

`test-adapter` implements current transition, staging, package-family, and source-rule behavior.

`legacy-test-adapter` implements only the older base and transition contracts.

When adding a capability, test both fixtures:

- the current fixture exercises the new path
- the legacy fixture still loads
- unavailable capability is reported as skipped or absent, not as a broken adapter
- existing detection and guidance remain useful.

See [[Test Adapters|Test-Adapters]] for the exact comparison.

## Review handoff for managers

An adapter support statement should name:

- detected framework package families
- covered source and target versions
- direct versus adjacent guidance coverage
- whether staged targets are available
- source paths and custom usage vocabulary
- important unsupported transitions
- source documents and their review date
- tests and fixtures proving the claims.

An installed adapter may cover only some transitions. Its guidance describes maintained migration knowledge. Composer scenarios test dependency resolution. Application tests establish runtime behavior. State which of these a support claim covers.

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

For repository release tags, follow [[Release Wiki Strategy|Release-Wiki-Strategy]] before declaring the release complete.
