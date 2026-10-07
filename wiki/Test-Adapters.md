# Test Adapters

Two installable Composer packages exercise adapter contracts in tests:

- `php-upgrade-preflight/test-adapter` demonstrates current adapter capabilities.
- `php-upgrade-preflight/legacy-test-adapter` demonstrates backward compatibility with an older capability set.

They make discovery and capabilities easy to inspect. Their package names, findings, and transitions are synthetic test data, not support for a real framework.

## Why they are separate Composer packages

The generic CLI discovers adapters from installed-package Composer metadata. Keeping fixtures as real packages exercises the same boundary as a third-party adapter:

```json
{
  "extra": {
    "php-upgrade-preflight": {
      "framework-adapters": [
        "PhpUpgradePreflight\\TestAdapter\\TestFrameworkIntegration"
      ]
    }
  }
}
```

Tests exercise manifest discovery, autoloading, construction without required arguments, interface checks, names, and optional capabilities through the same path as a third-party adapter.

## Capability comparison

| Behavior | Current test adapter | Legacy test adapter |
| --- | --- | --- |
| Integration name | `test-framework` | `legacy-test-framework` |
| Synthetic framework package | `test-vendor/framework` | `legacy-vendor/framework` |
| Detects root or locked package | Yes | Yes |
| Default source path | `modules` | `legacy-modules` |
| Compatibility rules | `TestFrameworkSourceRule` | Empty iterable |
| Transition provider | Version 1→2 | Version 1→2 |
| Stage target provider | Yes | No |
| Package family classifier | `test-vendor/*` → `test-framework` | No |
| Source visitor provider | No | No |

Both implement `FrameworkIntegration` and `FrameworkTransitionProvider`. Only the current fixture adds `FrameworkStageTargetProvider` and `PackageFamilyClassifier`.

## Current test adapter

`TestFrameworkIntegration` detects the project when `test-vendor/framework` appears in root requirements or the lock file. The locked version wins over the root constraint as version evidence.

### Source rule

Its default source path is `modules`. `TestFrameworkSourceRule` runs only when the request targets `test-vendor/framework` and returns one medium finding only when source inventory contains a usage from:

```text
modules/Plugin.php
```

The rule records E3 project-source evidence with the file and symbol. The file alone is insufficient: the request must also target the synthetic framework before the rule emits a finding.

### Transition guidance

When the synthetic framework is targeted, the adapter returns supported guidance for major 1→2 and records E2 package-metadata evidence.

The fixture returns this fixed guidance:

```text
framework: test-framework
source major: 1
target major: 2
status: supported
rule pack: test-framework-1-to-2
```

### Staged target

The adapter can create one stage only when:

- the detected source major is `1`
- the exact requested constraint is `^2.0`
- the request contains an exact PHP 8.x value in `--target-php` or `--from-php`.

The final target PHP is considered before current PHP. The planned stage is:

| Field | Value |
| --- | --- |
| Stage ID | `test-framework-1-to-2` |
| Package target | `test-vendor/framework:^2.0` |
| PHP requirement implemented by fixture | Exact selected version whose major is `8` |
| Remediation targets | None |

If any condition is missing, it returns an explicit empty `FrameworkStagePlan` with a reason: missing target, unsupported transition, or analysis PHP unavailable.

### Package family

The adapter classifies any package name beginning with `test-vendor/`, ignoring case, as `test-framework`. Core tests use that label to check how package families appear in lock changes.

## Legacy test adapter

`LegacyTestFrameworkIntegration` deliberately implements only interfaces that existed before Core v0.3:

- `FrameworkIntegration`
- `FrameworkTransitionProvider`.

It detects `legacy-vendor/framework`, returns no compatibility rules, proposes `legacy-modules` as a source path, and supplies supported 1→2 guidance when the package is targeted.

It has no `FrameworkStageTargetProvider`, so Core skips staged analysis for it. Detection and transition guidance still work.

This proves a useful compatibility rule: an older adapter still loads when Core gains an optional interface.

## Example third-party adapter skeleton

The shared minimum looks like this:

```php
final class AcmeFrameworkIntegration implements FrameworkIntegration
{
    public function name(): string
    {
        return 'acme';
    }

    public function detect(ProjectState $project): FrameworkDetection
    {
        $locked = $project->composerLock()->package('acme/framework');
        $required = $project->composerJson()->rootRequirements()['acme/framework'] ?? null;

        return new FrameworkDetection(
            $this->name(),
            $locked !== null || $required !== null,
            $locked?->version() ?? $required
        );
    }

    public function rules(): iterable
    {
        return [];
    }

    public function defaultSourcePaths(ProjectState $project): array
    {
        return ['src', 'app'];
    }
}
```

Then advertise the class in the adapter package's `composer.json`. The class must be instantiable without required constructor arguments because CLI discovery constructs it through reflection.

Add an optional interface when the adapter can supply its evidence. A stage provider needs exact version and PHP inputs. Guessed values would make a staged result look stronger than it is.

## What not to copy into production

- Do not use fixture package names such as `test-vendor/framework`.
- Do not claim a transition is supported without maintained, attributable evidence.
- Do not infer source compatibility from a filename alone. The fixture rule is intentionally narrow test behavior.
- Do not parse framework versions with the fixture's simple regular expression when Composer Semver evidence is needed.
- Do not install either fixture as end-user framework support.

## Contributor checklist

When changing Core adapter interfaces:

1. Keep new capabilities optional unless every supported adapter must provide them.
2. Verify the current fixture exercises the new capability.
3. Verify the legacy fixture still loads and its existing guidance remains available.
4. Verify adapter discovery from Composer metadata, not only direct constructor injection.
5. Document the new capability on the adapter-authoring Wiki pages.
6. If the change is part of a release tag, update the Wiki before the release task is complete, as required by `wiki/AGENTS.md`.

## Related pages

- [[Package Map|Package-Map]]
- [[Core Analysis Pipeline|Core-Analysis-Pipeline]]
- [[CLI Package Internals|CLI-Package-Internals]]
- [[Laravel Package Internals|Laravel-Package-Internals]]
