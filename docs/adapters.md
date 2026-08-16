# Framework adapters

The standalone CLI discovers framework adapters from Composer metadata. An adapter package does not require a CLI source change or a central registry entry.

## Package metadata

An installed adapter package declares one or more integration classes under `extra.php-upgrade-preflight.framework-adapters`:

```json
{
  "name": "vendor/example-adapter",
  "require": {
    "php-upgrade-preflight/core": "^0.3"
  },
  "autoload": {
    "psr-4": {
      "Vendor\\ExampleAdapter\\": "src/"
    }
  },
  "extra": {
    "php-upgrade-preflight": {
      "framework-adapters": [
        "Vendor\\ExampleAdapter\\ExampleFrameworkIntegration"
      ]
    }
  }
}
```

`framework-adapters` must be a nonempty JSON list of nonempty, fully qualified class-name strings. Every advertised class must be autoloadable, instantiable without constructor arguments, and implement `PhpUpgradePreflight\Core\Framework\FrameworkIntegration`. Its `name()` must return a nonempty adapter name.

The required interface supplies framework detection, compatibility rules, and default source paths. An integration may additionally implement `FrameworkTransitionProvider` to contribute transition guidance and `PackageFamilyClassifier` to classify changed packages into adapter-defined families.

Install the adapter in the same Composer project as `php-upgrade-preflight/cli`. Composer then supplies both its metadata and autoloader to `upgrade-intel`:

```bash
composer require php-upgrade-preflight/cli vendor/example-adapter
vendor/bin/upgrade-intel analyze --path=/work/app --target-php=8.2
```

## Discovery and activation

Discovery considers packages known to the running Composer installation. Packages that are not installed, or that do not declare the metadata key, do not register an adapter. Package names are processed in lexical order. The resulting integrations are ordered case-insensitively by adapter name, with the class name as the deterministic tie-breaker. Metadata declaration order therefore does not control cross-adapter execution; within one integration, compatibility rules retain the order returned by `rules()`.

With no `--framework` option, every discovered integration may inspect the target project and only integrations whose `detect()` result is positive become active. Explicit `--framework=NAME` selection is case-insensitive, activates only the requested installed adapters, and bypasses their automatic detection. Repeat the option to select multiple adapters.

Laravel keeps the same automatic behavior: the Laravel adapter detects `laravel/framework` or `illuminate/*` in the target project's root requirements or lock data. Its default source paths, rules, transition guidance, and package-family classification are unchanged by metadata-based registration.

## Invalid registrations and collisions

Registration is fail-fast. The CLI does not choose a winner for an ambiguous or broken installation.

- Repeating the same integration class, including a case-only variant, is an error.
- Two classes returning the same adapter name, including case-only variants, are an error.
- Malformed metadata, an advertised class that cannot be autoloaded, a non-instantiable class, a constructor that requires arguments, a class that does not implement `FrameworkIntegration`, or a blank adapter name is an installation/configuration error. Analysis stops rather than silently omitting that adapter.
- A package that is not installed is simply absent from discovery. If its adapter name is explicitly requested, the request fails as unavailable.

An unavailable explicit `--framework` value is an invalid invocation: the CLI writes a diagnostic naming the unavailable adapter and returns exit code `2`. Remove the option or install its adapter package in the CLI's Composer project. Discovery or registration defects are operational failures and return exit code `1` because the CLI cannot safely construct the analyzer.

## CLI and Artisan

Composer metadata generalizes standalone CLI registration; it does not replace Laravel package discovery. Installing `php-upgrade-preflight/laravel` still registers `upgrade:analyze` through its Laravel service provider, and that command still enables the Laravel integration directly. CLI and Artisan use the same analyzer pipeline, Laravel integration, request semantics, source-path defaults, report writers, and exit policy. The entry-point parity suite verifies equivalent canonical reports.

## Regression fixture

The repository's test-only `php-upgrade-preflight/test-adapter` package is deliberately outside CLI source. Its Composer metadata is the only production registration path. The `third-party-adapter` fixture proves automatic package detection, its `modules` default source path, a compatibility rule, `test-vendor/*` package-family classification, and a deterministic staged plan in a complete CLI analysis.

The separate `php-upgrade-preflight/legacy-test-adapter` package keeps an old-style implementation that uses only the required v0.2 interfaces. Its package constraint explicitly permits Core `^0.3`; its fixture proves that detection and guidance still work while staged resolution is reported as unavailable. Neither test package is part of the three published v0.3 packages.

## Optional v0.3 staged targets

An adapter may implement `FrameworkStageTargetProvider` in addition to the unchanged required `FrameworkIntegration` contract. `FrameworkTransitionProvider` remains optional and supplies guidance independently from staged Composer feasibility. A provider has one method:

```php
public function planStages(
    ProjectState $project,
    UpgradeRequest $request,
    EvidenceLedger $evidence
): FrameworkStagePlan;
```

For an available plan, return one `FrameworkStageTarget` per ascending adjacent framework hop. Each stage must contain:

- a stable lowercase ID that is unchanged for the same framework hop;
- provider and framework identities equal to `FrameworkIntegration::name()`;
- exact, adapter-selected Composer package constraints in canonical package-name order;
- one exact analysis PHP version, also present as the stage's Composer PHP target;
- zero or more package-sorted root-remediation candidates, each with its own evidence;
- ledger-backed evidence for the package targets and PHP decision.

Return an empty plan with a canonical unavailable reason when there is no unambiguous, fully evidenced chain. Do not manufacture a stage from a broad target, a guidance gap, or a minimum PHP constraint. Plans must be contiguous and ordered from the detected source major toward the requested target. Duplicate stage IDs, duplicate remediation packages, conflicting package targets, undeclared remediation evidence, provider or framework mismatches, missing evidence, provider failures, and non-contiguous output are invalid. The analyzer records deterministic invalid-provider evidence and does not run Composer for that plan.

Only one active adapter may provide stage targets in v0.3. If several detected or explicitly selected adapters implement the provider, ordinary rules and guidance still run, but staged solving is skipped with the providers listed in lexical order. Adapters must not attempt to win this collision through metadata order.

### Platform and PHP evidence

The analysis PHP value is a Composer simulation input, not a deployment recommendation. It must be an exact value supported by request evidence, such as exact current PHP or exact target-profile PHP, and it must satisfy the adapter's documented requirement for that hop. Record both the value and its provenance in evidence. A minimum such as `^8.2` is a compatibility boundary, not evidence that `8.2.0` is the application's intended platform, so a provider must return `analysis_php_unavailable` when no safe exact value exists.

Platform-profile completeness remains a Core request/report property. An adapter may use effective profile decisions when selecting a stage, but must not claim that host-derived partial inputs are closed-world evidence or that toolchain-bound Composer platform values were simulated. Never add host values that contradict the request profile.

### Guidance and source scope

Detection, direct final-target Composer resolution, transition guidance, and staged resolution are independent report dimensions. A stage provider does not turn guidance into feasibility, and Composer success does not prove source or runtime compatibility. Continue to implement `FrameworkTransitionProvider` when users need a documented hop matrix, including explicit unsupported or partially supported gaps.

`defaultSourcePaths()` must return project-relative paths and should stay bounded to the framework's conventional application source. Rules inspect the original source snapshot for every stage; the analyzer does not apply source changes between stages. Stage-aware rules should attach exact hop references, while global inventory remains a record of the original project. Do not execute or boot the analyzed application to improve detection.

### Privacy and trust boundary

Treat manifest data, source text, Composer diagnostics, repository URLs, credentials, and local paths as untrusted input. Put only the minimum reproducible fact in evidence context. Do not emit authentication data, query-string credentials, absolute project or workspace paths, raw environment variables, or unnecessary source excerpts. Core applies publication-boundary redaction, but adapters remain responsible for not creating new secret-bearing fields or bypassing the canonical report writers.

Provider output is metadata consumed by analyzer-owned temporary workspaces. It must never edit the original `composer.json`, `composer.lock`, source tree, or `vendor/`; must not run its own unbounded solver process; and must not claim that analyzer-only remediation candidates were applied to the project.

### Conformance fixtures

Adapter authors should keep committed, offline fixtures for at least:

- metadata-only discovery with no CLI source registration;
- stable IDs and identical canonical output across repeated runs;
- exact target constraints and PHP provenance for every supported adjacent hop;
- canonical ordering plus identical-duplicate normalization and conflicting-duplicate rejection;
- missing targets, ambiguous transitions, guidance gaps, and unavailable exact PHP;
- invalid evidence, provider identity, framework identity, ordering, and adjacency;
- collision with a second active provider;
- source-snapshot immutability and privacy redaction;
- an old-style implementation test when supporting migration from v0.2.

Use committed local Composer `path` repositories or a deterministic runner for solver tests. Snapshot every input byte before analysis and compare it afterward. The repository's test adapter, legacy adapter, orchestrator conformance tests, and Laravel CLI/Artisan parity suite are reference fixtures, not production packages.

The exact PHP value must come from request evidence and satisfy adapter metadata. Laravel prefers the exact final target PHP and falls back to the exact current PHP only when no compatible final value is available; it never derives an exact value from a minimum constraint. Its provider covers rooted `laravel/framework` adjacent paths from 7 through 13. A direct 7→9 request becomes two staged solves, while its direct guidance and direct final-target resolution remain separate report dimensions.

## Migrating an old-style adapter

The required v0.2 PHP interfaces were not extended. An unchanged implementation can migrate by testing against v0.3 and releasing with a Composer constraint that explicitly permits Core v0.3, for example `"php-upgrade-preflight/core": "^0.3"` or a deliberately supported range. An adapter still pinned to Core `^0.2` is not install-compatible with the v0.3 package line and must not be described as such.

Without `FrameworkStageTargetProvider`, the migrated adapter continues to contribute detection, rules, source paths, optional transition guidance, and optional package-family classification. The report honestly uses `stage_target_provider_unavailable`; it does not infer staged feasibility from the adapter's guidance.

## Post-v0.3 adapter roadmap

Symfony is the first adapter candidate after the optional staged-target contract has production evidence. v0.3 does not add a Symfony or CodeIgniter package, a fourth distribution repository, or another published adapter.
