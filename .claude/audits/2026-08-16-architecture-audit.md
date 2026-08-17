---
name: architecture-audit-2026-08-16
description: Standard-depth architecture and design-pattern audit of packages/*/src, graded against the project's own non-negotiable rules.
type: audit
audit_date: 2026-08-16
tool_version: 0.3.0-dev
report_schema: 0.8
commit: 91eac8f
scope: packages/{core,cli,laravel,test-adapter,legacy-test-adapter}/src
files_audited: 132
findings: 59
---

# Architecture Audit — PHP Upgrade Preflight

**Date:** 2026-08-16 · **Version:** 0.3.0-dev (schema `0.8`) · **Commit:** `91eac8f`
**Scope:** `packages/{core,cli,laravel,test-adapter,legacy-test-adapter}/src` — 132 PHP files
(core 96, of which 47 are Model DTOs; laravel 28; cli 5; test-adapter 2; legacy-test-adapter 1)
**Excluded:** `vendor/`, `tests/fixtures/`, `build/`, `tools/` (two `tools/` scripts are cited only
where they establish reachability)

**Severity totals:** 1 Critical · 6 High · 32 Medium · 20 Low · **59 total**

> Severity is graded against this product's actual threat surface, not a web-application template.
> Circuit Breaker, Saga, Outbox, Rate Limiter, Bulkhead and similar are marked **N/A** rather than
> "missing" — there is no network, database, broker or queue anywhere in this codebase.

An HTML rendering of this report is published at
<https://claude.ai/code/artifact/e865ddd6-26cd-4bdd-8ea8-7eb1d1023b90>. This Markdown file is the
canonical, agent-readable copy.

---

## 1. Verdict

This is a well-built codebase. Dependency direction is clean, the Composer failure taxonomy is
genuinely excellent, interface segregation is exemplary, and there is not one static mutable
property in 132 files. Most of what an audit normally finds is simply absent here.

The findings concentrate in three places, and all three matter because they contradict rules the
project wrote for itself — not style preferences imported from outside.

### Breach 1 — Framework neutrality

> `.claude/CLAUDE.md` — "The core package must not depend on Laravel or another framework package."
> "Keep framework-specific package families and migration guidance out of core."

There is no *compile-time* violation: `packages/core/src` contains zero `use Illuminate\`, zero
`use Laravel`, zero imports of any sibling package. That check passes cleanly. But
`ContextualSourceUsageVisitor` — a core class attached unconditionally to every scan — hardcodes the
Laravel application skeleton: the `bootstrap/providers.php` path, the `serviceprovider` base-class
name, the four Laravel HTTP-kernel middleware property names, `config/app.php`, and the
`['app','application','laravel']` container receivers.

Three independent auditors found this separately and converged on the same conclusion, and the
consumption side confirms it: the usage types this core class emits — `service_provider`,
`facade_alias`, `middleware_reference` — appear nowhere outside it except in `packages/laravel`.
Core generates a Laravel-shaped signal that only the Laravel adapter can read. A second adapter
would inherit dead Laravel heuristics on every file it parses.

### Breach 2 — Markdown must be a projection

> `.claude/CLAUDE.md` — "JSON is the canonical report. Markdown must be a projection of the same
> UpgradeReport, with no independent analysis logic."

The structural half of this rule holds: `MarkdownReportWriter` does no filtering, thresholding,
top-N, re-sorting, or severity re-derivation, and drops no evidence. But it *fabricates defaults
that render as facts*. When a canonical document lacks `composer_execution`, the writer invents
fifteen values — including `credentials_may_be_inherited: true` and hardcoded `300 s` / `60 s`
timeouts — and prints them unhedged as execution provenance.

This is reachable, not dead code: `renderCanonical()` is public and is called on externally supplied
JSON by `tools/render-markdown-report.php:47` and `tools/verify-report-privacy.php:153`. For a
product whose entire value proposition is *evidence you can trust*, a report that states
inherited-credential status it never measured is the most serious class of defect in this audit,
even though it breaks nothing.

### Breach 3 — A roadmap item marked complete that is not

> `.claude/DEVELOPMENT_PLAN.md:277` — "[x] Refactor phase boundaries if staged work would otherwise
> turn ComposerScenarioRunner, DefaultUpgradeAnalyzer, source-impact construction, or report
> assembly into orchestration monoliths."

Two of the four named targets were genuinely saved. `DefaultUpgradeAnalyzer::analyzeUpgrade()` is
129 lines with only 6 decision points and nesting depth 1 — a straight-line pipeline of named
delegations. `ReportAssembler::assemble()` has a single branch. That part of the claim is real.

But the orchestration mass was *relocated*, not eliminated. It landed in `StagedUpgradeOrchestrator`,
which was never on the watch list, and `ComposerScenarioRunner` was not refactored at all — it is
still 1,124 lines with a 251-line `run()`. **The checklist item should be reopened.**

### Recurring theme — domain vocabularies with no owner

Blocker types, severity, confidence, target normalization, advisory actions, CLI options and risk
thresholds are each defined in two to six places as bare strings or literals, with no value object
and no single source of truth. None is broken today; each is a place where two copies can drift
apart silently. One already has: the Laravel adapter normalizes targets with `strtolower()` but no
`trim`, unlike every other call site. Section 7 collects these.

Nothing found threatens correctness of the analysis, the read-only guarantee, the PHP 8.0 floor, or
the privacy boundary.

---

## 2. Constraint verification

Checked first, so nothing below contradicts the project's stated non-negotiables.

| Constraint | Result | Evidence |
| --- | --- | --- |
| PHP `^8.0` floor honoured | **HOLDS** | Zero hits for `readonly`, `enum `, `: never`, property hooks across all 132 files |
| Composer subprocess is the only external boundary | **HOLDS** | Exactly 2 `new Process` sites, both in `ComposerScenarioRunner`. Zero `exec`/`shell_exec`/`proc_open`, zero `curl_*`, `PDO`, Guzzle, Redis, AMQP |
| Core imports no framework | **HOLDS** | Zero `Illuminate`/`Laravel`/sibling-package imports; zero `app(`, `config(`, `env(` helpers |
| Core stays *semantically* framework-neutral | **BREACHED** | ARCH-1 — Laravel skeleton table in `Source/ContextualSourceUsageVisitor.php` |
| Laravel → core one-way; CLI → core clean | **HOLDS** | No circular package dependencies; `cli` imports only `Core\*` + `Composer\InstalledVersions` |
| Markdown carries no independent analysis | **PARTIAL** | No filtering/scoring, but fabricated defaults + one Markdown-only aggregation (V1–V4) |
| Composer solver not reimplemented | **HOLDS** | No solver logic; outcomes derive from real process exit codes and output |
| Analyzed project treated as immutable | **HOLDS** | All writes target analyzer-owned temp workspaces; `Analysis/` contains zero native filesystem or process calls |

---

## 3. Pattern detection matrix

"N/A" means the pattern has no structural surface in an offline, read-only, single-process analyzer
— not that it is missing.

| Pattern | Verdict | Evidence / note |
| --- | --- | --- |
| Strategy | **DETECTED** | `Framework/CompatibilityRule.php:13` — 1-method interface, 12 `final` stateless implementations |
| Visitor | **DETECTED** | 4 `NodeVisitorAbstract` subclasses in `core/src/Source`; marker pass + read-back |
| Facade | **DETECTED** | `Contracts/UpgradeAnalyzer.php:10` — 1 method over 14 collaborators; both entry points route through it |
| Adapter / Ports & Adapters | **DETECTED** | `Framework/FrameworkIntegration.php` (4 methods) + 3 *optional* capability ports |
| Registry (plugin discovery) | **DETECTED** | `cli/src/FrameworkIntegrationRegistry.php` — deterministic ordering, fails closed |
| Registry (evidence) | **DETECTED** | `EvidenceLedger` — rejects both dangling refs and orphaned evidence. Exemplary |
| Read Model / immutable DTO | **DETECTED** | 47 model classes, all `final`, private fields, zero setters, 15 `with*` withers |
| Factory Method | **DETECTED** | `cli/src/AnalyzerFactory.php` + `DefaultAnalyzerFactory.php` |
| Policy | **DETECTED** | `Support/PathExposurePolicy.php` — static utility form, stateless |
| Timeout | **DETECTED** | Enforced at 3 levels: scenario, per-stage, aggregate — but see CB-2, CB-6 |
| Builder | **MISNAMED** | Zero `return $this;` in the whole scope. The four "Builders" are stateless one-shot transforms — better than a GoF Builder here, but wrongly named (NAME-1) |
| Iterator | **PARTIAL** | Only `UpgradeTargetSet`. Peers return raw arrays — acceptable at a serialization boundary |
| Decorator | **DEGENERATE** | Delegation to a concrete collaborator, not decoration. Harmless |
| State | **ABSENT — by design** | Validated string constants; correct, since they must round-trip into a frozen JSON schema |
| Chain of Responsibility | **ABSENT — correctly** | `FrameworkRuleEngine` is a fan-out collector that never short-circuits. A chain would be wrong for evidence gathering |
| Template Method | **ABSENT** | One abstract class in scope, zero abstract methods. Composition applied consistently — a positive |
| Null Object · Specification · Composite · Bridge · Flyweight · Proxy | **ABSENT** | None needed at this scale; no self-recursive types, no static caches |
| Singleton (anti-pattern) | **NOT PRESENT** | Zero `getInstance`, zero `private static self`, zero static mutable properties |
| Circuit Breaker · Retry · Rate Limiter · Bulkhead · Fallback | **N/A** | No network boundary exists |
| Outbox · Saga · Idempotent Consumer · Dead Letter Queue | **N/A** | No broker, queue, or distributed transaction exists |
| Event Sourcing · CQRS · EDA | **N/A** | No write model, no event store, no bus. Single-process batch analysis |
| Object Pool · Distributed Lock · Read-Write Proxy | **N/A** | No pooled resource, no concurrency, no replica |

---

## 4. Critical and High findings

### F1 · Critical — A 395-line orchestration method carrying seven responsibilities

`packages/core/src/Analysis/StagedUpgradeOrchestrator.php:46–440`

Verified directly: `analyze()` starts at line 46 and the next method does not appear until line 441.
In one body it performs provider selection, plan validation, budget enforcement, evidence emission,
the Composer execution loop, blocker-registry lifecycle bookkeeping, and status state-machine
resolution — 40 branches, 51 distinct locals, three nested loops. It is the largest method in the
codebase by 140 lines.

The registry is mutated through by-reference parameters, so the loop's state cannot be inspected in
isolation, and its 1,073-line test file has no way to exercise the phases independently:

```php
private function observeBlockers(
    array &$registry,
    array &$registryOrder,
```

**Fix.** Split into three collaborators plus one real object.
`StagePlanResolver` takes lines 53–184 (provider selection, plan validation, hop and process
budgets) and returns either a `StagedResolution::skipped(...)` or the validated stage list.
`StageBlockerRegistry` becomes a `final class` with private `$entries`/`$order` and methods
`observe()` / `hasActiveBlocking()` / `ordered()`, deleting both `&` parameters. `StageExecutor` owns
the per-stage loop with the running state as private properties rather than loop-scoped locals.

Replace the 5-arm status cascade with a private `resolveStageStatus(): string` returning the existing
`StagedResolution::*` constants — *not* an enum, which the 8.0 floor forbids. `analyze()` then
reduces to roughly 40 lines of coordination.

### ARCH-1 / B3 / G1 · High — Laravel skeleton knowledge hardcoded inside framework-neutral core

`packages/core/src/Source/ContextualSourceUsageVisitor.php:45, 88, 105, 117, 126, 130, 140, 149, 305–318`

*Found independently by three auditors · verified in source.*

Attached unconditionally to every scan at `SourceUsageScanner.php:219`, this core class encodes the
Laravel application skeleton verbatim:

```php
if ($node instanceof Stmt\Return_ && strtolower($this->file) === 'bootstrap/providers.php' ...

if ($this->shortName($parent) === 'serviceprovider') {

if (in_array($name, ['middleware', 'middlewarealiases', 'middlewaregroups', 'routemiddleware'], true)) {

return in_array(strtolower($receiver->name), ['app', 'application', 'laravel'], true);
```

Plus `['registerprovider','withproviders']` (`:284`), `'mockery'` (`:255`), and `config()` detection
(`:262–275`). `.claude/memory/MEMORY.md` describes this visitor only in neutral vocabulary ("exact
config keys, service-provider subclasses…"), so this is undocumented drift rather than a recorded
trade-off.

**Fix — and its real cost.** Use the optional-capability-port pattern the codebase already applies
successfully for `FrameworkStageTargetProvider` and `FrameworkTransitionProvider`: add a core-side
`SourceUsageVisitorProvider` that integrations supply, have `SourceUsageScanner` attach contributed
visitors instead of hardcoding this one, and move the class to `packages/laravel/src/Source/`. Core
keeps `SourceUsageVisitor` and `ExplicitFullyQualifiedNameVisitor`, which are genuinely neutral.

**This is contract-affecting.** The `facade_alias` and `middleware_reference` usage types are frozen
byte-for-byte in `tests/fixtures/contracts/v0.1/` and `v0.2.1/`. Treat it as a refactor with
snapshot proof, not a behaviour change — and note it also alters emitted usage types for non-Laravel
projects, so fixture snapshots update in the same commit.

### V1 · High — Fabricated Composer provenance rendered as measured fact

`packages/core/src/Reporting/MarkdownReportWriter.php:31–47` → printed at `162–190`

Verified in source. When the key is absent, fifteen values are invented:

```php
$composerExecution = $canonical['composer_execution'] ?? [
    'mode' => 'compatible',
    'scenario_timeout_seconds' => 300,
    'diagnostic_timeout_seconds' => 60,
    'repository_source_mode' => 'project_and_global',
    'global_configuration_inherited' => true,
    'credentials_may_be_inherited' => true,
    ...
```

The real values come from `ComposerExecutionConfiguration::provenance()`, where every one of those
keys is *derived from* `isRestricted()`. The fallback hardcodes the compatible-mode answer to all of
them. Timeouts are user-configurable (1–3600 / 1–900), so Markdown asserts `300 s` / `60 s` for a run
whose timeouts it cannot know — duplicating two constants as bare literals, a drift bug the moment
either changes.

There is a concrete self-contradiction: line 78 reads the mode from `request_summary`. A canonical
carrying `request_summary.composer_execution.mode = "restricted"` but no top-level block renders
*"mode: restricted"* at line 78 and *"Mode: compatible"* at line 164 in the same document — and
neither matches the JSON, which carries no provenance at all.

**Fix.** Never synthesize. Keep it nullable and disclose absence:

```php
$composerExecution = isset($canonical['composer_execution'])
    && is_array($canonical['composer_execution'])
        ? $canonical['composer_execution']
        : null;

// ...
if ($composerExecution === null) {
    $lines[] = '- Not recorded by this report schema.';
} else {
    // existing sprintf() lines, each guarded with ?? 'not recorded'
}
```

### F2 · High — ComposerScenarioRunner::run() mixes eight abstraction levels

`packages/core/src/Composer/ComposerScenarioRunner.php:94` — 251 lines; class total 1,124 lines / 34 methods / 14 properties

In one method body: resolve the Composer version, validate platform capability, create a workspace,
serialize JSON manifests to disk, launch a child process, redact output strings, read a lockfile,
classify the failure taxonomy, run diagnostics, handle cleanup failure. Policy decisions and
byte-level work share a scope, and the failure taxonomy is an inline 6-arm cascade with two embedded
ternaries.

The evidence richness itself — command, version, duration, exit status, bounded excerpts, candidate
lock — is a product requirement and is *correctly captured*. The problem is that its assembly is
inlined rather than delegated.

**Fix — pure move-method, no behaviour change.** Extract `ScenarioOutcomeClassifier` (lines 199–225
plus `isSolverFailure`, `indicatesMissingComposer`, `indicatesUnavailableRepositoryMetadata`,
`exceptionOutcome`) returning a small `ScenarioOutcome` value object — PHP 8.0's `match(true)` reads
far better than the current if/elseif chain. Extract `ScenarioWorkspacePreparer` (`seedProjectState`,
`applyTemporaryComposerChanges`, `absolutePathRepositories`, `containsEnvironmentVariable`,
`processEnvironment`) — all filesystem/manifest work with no scenario-policy content. That removes
~200 lines and 6 methods, taking `run()` to roughly 110 lines.

### M-F2 · High — Blocker `type` is an unvalidated magic string with three owners

`packages/core/src/Model/Blocker.php:107` · `ComposerBlockerParser.php:154–482, 430–468` ·
`BlockerGrouper.php:74, 92` · `AbandonedPackageDetector.php:55`

The vocabulary has no constants and no owner. Roughly 18 string literals are emitted from
`ComposerBlockerParser`, two 11-key maps translate them to summaries and options, and two further
consumers re-compare the same literals independently. The type then gates the report:

```php
public function blocksResolution(): bool
{
    return !in_array($this->type, ['abandoned-package', 'extension-version-unknown'], true);
}
```

**An overclaim worth correcting.** The sub-auditor reported that a typo here "silently flips the
top-level report verdict". Re-reading `UpgradeReport.php:212–233` shows the real behaviour is
narrower and better than that: the blocker loop is reached only when no scenario succeeded or
operationally failed, so this is the tiebreaker between `blocked` and `unknown` — not the whole
verdict. And because the check is a *negative* allow-list, an unregistered type defaults to
**blocking**. It fails loud, which is the safe direction for a preflight tool.

It stays High regardless: the `summary()` and `options()` maps degrade silently to a generic
`?? 'Composer reported a dependency blocker.'` with no error, so an unregistered type loses its
guidance without any signal.

**Fix.** A `BlockerType` class in `Core\Model`: `public const` per type, `private __construct`, a
`fromString()` that throws on unknown, and `blocksResolution()` / `summary()` / `options()` as
instance methods. This matches the convention the codebase already uses successfully at
`ScenarioResult.php:16–26` — no enum needed, so the 8.0 floor holds.

### M-F3 · High — UpgradeTarget validates and normalizes nothing; 15+ call sites re-do it, inconsistently

`packages/core/src/Model/UpgradeTarget.php:12, 28–37`

```php
public function __construct(string $package, string $constraint)
{
    $this->package = $package;
    $this->constraint = $constraint;
}
```

`fromString()` checks only colon placement. The real validation lives in the *collection*
(`UpgradeTargetSet.php:158, 168`) — the wrong information expert — so every downstream consumer
re-normalizes, and they do not agree:

```text
UpgradeTargetSet.php:27               strtolower(trim(...))
UpgradeRequest.php:56                 strtolower(trim(...))
LaravelFrameworkIntegration.php:679   strtolower(...)      <-- no trim
ComposerBlockerParser.php:393         strtolower(...) === strtolower(...)
```

Plus `FrameworkStageTarget.php:71`, `LaravelTarget.php:34` and `TestFrameworkSourceRule.php:26`.
Meanwhile `cli/src/AnalyzeCommand.php:68` builds targets straight from raw `argv`, so unvalidated
targets travel until an `UpgradeTargetSet` happens to be constructed. **The missing `trim` in the
Laravel adapter is a live divergence, not a hypothetical one.**

**Fix.** Move `strtolower(trim(...))` and both `validate*` methods into
`UpgradeTarget::__construct()`, then delete the downstream normalization. Leave the set responsible
only for dedup and conflict-merge — which is genuinely collection-level work.

### G3 · High — A 125-line sequential regex-branch method parsing solver output

`packages/core/src/Analysis/ComposerBlockerParser.php:139–263, 425, 179`

Ten sequential `if (preg_match(...)) { return …; }` branches in one method, a 240-character single
line at `:179`, a 9-parameter private helper at `:425` with four consecutive `?string` arguments, and
a solver-relation array shape re-declared in four separate docblocks.

**The regex itself is not the finding.** The project rule prefers parsers over regex *for PHP
source*, and source inspection correctly uses `nikic/php-parser`. This code parses free-form Composer
console output, where regex is the appropriate tool. The problem is purely that ten independent parse
rules share one method body and one 9-argument constructor call.

**Fix.** One named `private function …(): ?Blocker` per branch, iterated in order, plus a
`SolverRelation` value class replacing the four duplicated docblock shapes. Each parse rule then
becomes independently testable against a captured transcript — which matters, because Milestone 6
requires separating parser drift from solver drift.

---

## 5. The Composer subprocess boundary

This is the product's one real reliability surface, and it is the strongest part of the codebase.
`ScenarioResult` defines 3 failure types and 11 outcomes and enforces the invariants between them;
every `\Throwable` becomes a structured result rather than aborting analysis; cleanup failures are
*surfaced* with the leaked path rather than hidden; and `provenance()` honestly publishes
`'process_os_isolation' => false` instead of overclaiming. Six defects sit inside an otherwise
exemplary design.

| ID | Sev | Finding | Location |
| --- | --- | --- | --- |
| CB-1 | Med | Workspace-preparation failure is reported as a Composer *process* failure. `$phase` is set to `'process'` one line before `processEnvironment()` — which can throw — so a consumer sees "Composer failed" when no process ever started. **The fix is swapping two lines.** | `ComposerScenarioRunner.php:169–170` |
| CB-2 | Med | Diagnostic time sits outside the staged time budget. Only the scenario timeout is clamped; `diagnosticTimeoutSeconds` is user-settable to 900 s and never clamped, while a solver-failing attempt runs one `composer prohibits` per target inside the same `run()`. The *process-count* budget models diagnostics; the *time* budget does not, so `AGGREGATE_TIMEOUT_SECONDS` can be overshot. | `StagedUpgradeOrchestrator.php:258–271, 678–689` |
| CB-3 | Med | Diagnostics carry no failure class. `ComposerDiagnostic` stores no outcome, and the runner fabricates `exitCode = 1` for unsupported-Composer *and* for every caught exception including timeout. In JSON, a 60-second timeout, a missing binary, and a genuine exit 1 are indistinguishable — the one place the excellent `ScenarioResult` outcome modelling was not applied. | `ComposerDiagnostic.php:21–41`; runner `:864–876, :911–926` |
| CB-4 / C2 | Med | *Corroborated.* Probe-directory cleanup fails silently and bypasses the injected `WorkspaceManager`. `(new Filesystem())->remove(...)` in a `finally` is swallowed by two `catch (\Throwable) → null` callers, giving a leaked temp directory *and* silently degraded version/platform detection. Two independent temp-directory lifecycles exist against a rule that says workspaces must be deleted. | `ComposerScenarioRunner.php:513–550, :531–533` |
| CB-5 | Low | Four copies of the literal `300`. `SCENARIO_TIMEOUT_SECONDS` at line 29 has no reader anywhere in `src` or `tests`. | `ComposerScenarioRunner.php:29` + 3 sites |
| CB-6 | Low | Metadata-probe timeout hardcoded to 30 s, not derived from configuration and absent from `provenance()` — which does publish the other two timeouts. | `ComposerScenarioRunner.php:499` |
| F3 | Med | `$phase` is a stringly-typed state machine spanning 600 lines — assigned at four sites, consumed far away, with nothing constraining the vocabulary. A typo at either end silently degrades every exception to `OUTCOME_WORKSPACE_FAILURE`. Fix with `private const`, not enums. | `ComposerScenarioRunner.php:152, 158, 169, 188 → 776` |
| F11 | Low | Constructor encodes test-seam policy in a nested ternary — whether version detection happens at all depends on a three-way relationship between two other constructor arguments. | `ComposerScenarioRunner.php:81` |

---

## 6. Report integrity

Grouped together because they share one failure mode: the report states something it did not
measure. For a decision-support tool sold on traceable evidence, this class outranks its nominal
severity.

| ID | Sev | Finding | Location |
| --- | --- | --- | --- |
| V2 | Med | A hardcoded sentence asserts "Side effects disabled: scripts, plugins, installation, audit, interaction, and progress" while the canonical report carries all six booleans and the writer never reads them. Today all six are `false`, so it happens to agree — it will state a falsehood the day any flips. | `MarkdownReportWriter.php:191` |
| V3 | Med | The only `count()` in the file emits "*N* unique occurrence(s)" — a number that exists nowhere in JSON. "unique" is also unbacked: the constructor stores occurrences with no dedup; only `merge()` deduplicates. | `MarkdownReportWriter.php:374` |
| V4 | Med | Fabricated `staged_resolution` defaults. `'unknown'` is a real domain status printed in the document headline, so a reader cannot distinguish "the analyzer concluded unknown" from "this document has no staged section". Partly mitigated: the invented `stop_reason` does disclose the schema gap. | `MarkdownReportWriter.php:51–58` |
| RPT-1 | Med | Three declared budgets — memory, JSON size, Markdown size — are published in the report's `budgets` block but never enforced anywhere. No `memory_get_usage`, no length check. Time and process budgets *are* enforced, so the block silently mixes guarantees with aspirations. | `StagedAnalysisPolicy.php:16–18` → `StagedResolution.php:222–224` |
| RPT-2 | Med | Truncation and redaction failure are both invisible. `OutputExcerpt` truncates at 4,000 bytes with no marker and no original-length field. Separately, `SensitiveOutputRedactor::replace()` returns the whole value as `[REDACTED]` when `preg_replace` returns null — failing closed is right, but because redaction runs before truncation, PCRE processes untruncated multi-megabyte solver output, so a backtrack-limit failure is reachable and would silently destroy all solver evidence while looking like ordinary redaction. | `OutputExcerpt.php:9–27`; `SensitiveOutputRedactor.php:487–492` |
| F6 | Med | `ReportAssembler` has an optional-argument fallback that builds source impact *without* the ownership index or evidence ledger, silently yielding findings with `ownership: 'unknown'` and no `E2` evidence. Against the rule that every meaningful finding must reference evidence, a second construction path emitting evidence-free findings is a hazard, not a convenience. Currently load-bearing for two tests only. | `ReportAssembler.php:62` |
| V5 | Low | A missing `source_snapshot_note` is replaced by a *different, weaker* methodological claim authored in the presentation layer — the canonical sentence explicitly adds "it does not assume edits from an earlier stage were applied". | `MarkdownReportWriter.php:281–282` |
| V6 | Low | Absent durations render as `0 ms`, indistinguishable from a real sub-millisecond stage. | `MarkdownReportWriter.php:293, 311` |
| V7 | Low | Synthesized identifiers (`legacy-source-impact`, `direct-final`) are typographically identical to genuine hashed IDs; multiple id-less findings collapse to one token. | `MarkdownReportWriter.php:374, 567, 625` |
| V8 | Low | A coupled `isset($a, $b)` suppresses a *present* risk level when effort is missing, and vice versa. Two independent checks fix it. | `MarkdownReportWriter.php:341` |

Two adjacent notes that are not writer defects: `staged_resolution.budgets` and top-level
`staged_resolution.evidence` are present in JSON and never rendered, so the Markdown is not a
*complete* projection; and `tools/render-markdown-report.php:44–45` mutates the canonical document
before rendering, so that tool's output intentionally reports different request facts than its
source JSON.

---

## 7. Model and vocabulary quality

The model layer is in good shape structurally: zero public or protected properties across all five
`src` trees (bar the two Laravel-mandated `$signature`/`$description`), zero setters, and all 16
`with*()` methods correctly return new instances. Array returns are copy-on-write and rightly not
treated as leaks. What is thin is *invariants*: several DTOs accept states the product rules say
cannot exist, and several domain vocabularies have no owning type.

| ID | Sev | Finding | Location |
| --- | --- | --- | --- |
| M-F5 | Med | `EffortEstimate` enforces nothing — `array{0:int,1:int}` is a docblock promise only. An inverted range `[8, 3]`, an empty range, or a nonsense confidence all serialize into the canonical report. Against the product rule that *effort is always a range with assumptions and confidence*, this is the DTO most in need of invariants. | `Model/EffortEstimate.php:22` |
| M-F4 | Med | `severity` is validated in one model and not in two siblings that write the same schema field — `CompatibilityFinding` rigorously validates `$appliesToHops` but assigns severity unchecked. The same allow-list is then duplicated a third time in the Laravel catalog validator. Identical shape for `confidence`. | `SourceImpactFinding.php:43` (validated); `CompatibilityFinding.php:42`, `RiskSummary.php:14` (not); `LaravelRuleCatalogValidator.php:188` |
| G9 | Med | Risk thresholds are magic numbers inside nested ternaries — and the stage-level variant at `:114` uses a *different rule set* for the same three levels, with effort hours as bare literals. These produce the report's headline `risk.level` and `effort.range_hours`, so global and stage risk can silently diverge. | `Analysis/RiskAndEffortEstimator.php:48, 114, 69–71` |
| M-F9 | Med | `StageBlockerEntry` re-declares 11 fields `Blocker` already owns and copies them one by one, giving the blocker schema two owners. Compose `private Blocker $blocker` plus the ~10 stage-lifecycle fields and delegate. | `Model/StageBlockerEntry.php:20–33, 56–79` |
| M-F8 | Med | Long positional constructors with adjacent same-typed parameters: `UpgradeReport` takes 18 params of which **11 are bare `array`**, so any swap is type-silent; `StageAnalysis` has three adjacent `?ProjectStateFingerprint` (predecessor/input/output) among 19, re-typed twice more in its withers. Extract parameter objects — `StageAnalysis` collapses 19 params to ~8. | `UpgradeReport.php:55–74`; `StageAnalysis.php:56–58, 190–210, 229–249` |
| M-F7 | Med | Lazy validation: a *query* method throws on contradictory duplicate platform packages, but only when some caller happens to invoke it — and the caller varies. Normalize in the constructor so an invalid `ComposerJson` cannot exist. | `Model/ComposerJson.php:55, 104`; `TargetPlatform.php:65` |
| M-F6 | Med | `PackageRef` defines `PACKAGE_NAME_PATTERN` and applies it only to the abandoned *alternative*, never to its own name — so `new PackageRef('not a package', '1.0')` reaches the report. | `Model/PackageRef.php:9, 31, 75` |
| G5 | Med | `LaravelFrameworkIntegration` is ~720 lines implementing 4 interfaces and acting as detector, rule factory, transition engine *and* stage planner. Split into a thin façade over `LaravelRuleFactory`, `LaravelTransitionAssessor`, `LaravelStagePlanner`. | `laravel/src/LaravelFrameworkIntegration.php:41, 59–470` |
| G4 | Med | No `ReportWriter` abstraction: both writers share `render(UpgradeReport): string` but implement no common interface and are `new`-ed inline, with byte-identical format-dispatch in two packages. The duplication is broader — both commands independently reimplement profile loading, option parsing, diagnostics, output-path validation and request assembly. | `cli/AnalyzeCommand.php:115` ≡ `laravel/Commands/AnalyzeUpgradeCommand.php:114` |
| G7 | Med | The CLI option vocabulary is declared **four times inside one method** (defaults, repeatable chain, allow-list, assignment switch) and twice more in the two usage texts. The author's own drift guard at `:141` — `throw new \LogicException('Validated CLI option was not assigned.')` — is evidence the risk was already felt. One `private const OPTIONS` table should drive all six. | `cli/src/CommandLineParser.php:50–139` |
| G6 | Med | Advisory `action` vocabulary switched in two classes plus a third allow-list, and they disagree: one has 6 cases, the other 7. The missing `PUBLISH_MIGRATIONS` case falls through to a silent `return null`. | `PackageAdvisoryDefinition.php:64`; `TargetedPackageAdvisoryRule.php:132`; `LaravelRuleCatalogValidator.php:113–121` |
| G8 | Med | `FrameworkIntegrationRegistry` mixes discovery, reflection, manifest validation and lookup — an 88-line `installed()` and a 74-line `discoverIntegrationClasses()` doing file I/O, JSON decoding and `extra.*` schema validation across six throw sites. Split out an `AdapterManifestReader`. | `cli/src/FrameworkIntegrationRegistry.php:43–284` |
| G10 / C3 | Med | DIP: 14 collaborators typed to concrete `final` classes, so *none* can be substituted by a test double — the nullable-injection escape hatch only permits preconfigured instances, not stubs. The process boundary *is* seamed, but via five `\Closure` properties with docblock-only contracts and two near-duplicate variants of the same concept. Start with an `interface ProcessRunner` + `ProcessResult`, not all 14. | `DefaultUpgradeAnalyzer.php:31–79`; `ComposerScenarioRunner.php:37–46` |
| M-F1 | Low | A process-wide shared `\stdClass` is handed out of the model via a `static` cache, so `$arr['components']->x = 1` would mutate every other report in the process. The sibling model does it correctly with `new \stdClass()` per call. Latent rather than observed — nothing currently mutates the array before `json_encode` — but it is also a cross-test contamination risk. One-line fix: drop the `static`. | `Model/EffortEstimate.php:64` (cf. `ProjectState.php:43`) |
| M-F12 | Low | ISP: `EvidenceLedger` — the one mutable model — is threaded through ~30 classes including third-party-implementable rules, which need only `add()`/`addOnce()` but also receive `register()`, `all()` and `validateReferences()`. Extract an `EvidenceRecorder` interface and type rule parameters against it. | `Model/EvidenceLedger.php:7`; `Framework/CompatibilityRule.php:19` |
| M-F11 | Low | Dead defensive copying — `copyTargets()` rebuilds N immutable `UpgradeTarget` objects on every `all()`/`toArray()`/`getIterator()`. The type has private state and no mutators, so the copy buys nothing and misleads readers. | `Model/UpgradeTargetSet.php:131` |
| G11 | Low | Raw Composer document traversal outside its information expert: `scripts` parsed in two places, the `['packages','packages-dev']` walk re-implemented in three (because `PackageRef` exposes no per-package `require` metadata), `repositories` parsed inside the path policy. Add `ComposerJson::hasScript()`, `localRepositoryPaths()`, `PackageRef::requirements()`. | `ComposerLock.php:54`; `PackageVersionRule.php:170`; `OldIlluminateSupportRule.php:176`; `PathExposurePolicy.php:74, 141–150` |

Two model categories came back clean and are worth stating: **LSP** — zero `NotImplemented` throws,
zero empty overrides, zero `parent::` type checks, with the riskiest candidate
(`LaravelPhpConstraintRule`, implementing both `evaluate()` and `evaluateForHop()`) verified by hand
to delegate to one shared method. And **ISP** — no interface in the codebase exceeds four methods.

---

## 8. Layering and cohesion

| ID | Sev | Finding | Location |
| --- | --- | --- | --- |
| B1 | Med | The one inward/outward inversion in the package: a `Model` DTO imports `Analysis\StagedAnalysisPolicy`. Constants-only, so no runtime coupling — but the arrow points the wrong way and the DTO cannot be reused without dragging `Analysis` along. Fix by moving the constants to a `Model\AnalysisBudget` and aliasing them from `Analysis`; report bytes unchanged, so snapshots stay stable. | `Model/StagedResolution.php:7, 215–224` |
| B2 / M-F10 | Med | Three DTOs reach the disk directly (`is_file`, `@file_get_contents`, `realpath`), so they cannot be constructed or unit-tested without a real filesystem. Keep the pure factories and relocate each `fromFile` to `Composer/JsonFileReader`, which already produces structured outcomes. **Note:** `UpgradeRequest` is on the public API, so that one is a deliberate B/C decision — flag it, do not change it silently. | `CandidateLockEvidence.php:29–38`; `TargetPlatformProfile.php:142–147`; `UpgradeRequest.php:45–49` |
| C1 | Med | `Reporting` performs raw filesystem probing and news up a concrete `Symfony\...\Filesystem`. The containment check that enforces the read-only-input contract — report output must stay outside the analyzed project — cannot be exercised against a stub. | `Reporting/ReportFileWriter.php:12–17, 40–73` |
| F4 / G2 | Med | *Corroborated.* `planStages()` — 145 lines, 30 branches — fuses plan policy with English prose in an 8-arm cascade, so every wording change forces re-reasoning about resolution semantics and vice versa. Its 10-parameter signature also duplicates 9 of `build()`'s 10. Split into a `dependencyPosture()` returning a token and a `dependencyStage()` owning only the sprintf; encode the chain as an ordered `[predicate, summary, action]` list. | `ReportSectionBuilder.php:122–266` |
| F5 | Med | `SourceImpactBuilder` carries correlation, dedup/merge, and prose generation. Not a monolith — the helper tier is well factored — but `finding()` is the pressure point: 62 lines, 13 branches, 8 ternaries, deciding severity, ownership confidence, relevance, evidence emission *and* calling the prose builder. | `SourceImpactBuilder.php:157, 95, 224` |
| F7 | Med | Duplicated test-guidance catalog: `StageAssessmentBuilder::tests()` and `ReportSectionBuilder::testGuidance()` emit the same four guidance IDs with the same commands and grading from copy-pasted code. Staged and non-staged reports can drift on a one-sided edit. | `StageAssessmentBuilder.php:169` ≡ `ReportSectionBuilder.php:510–512` |
| F8 | Med | `phpFiles()` reaches genuinely 5 levels of control nesting and fuses path resolution, project-containment security checks, exclusion policy, and uncertainty authoring. The class overall is fine — this is one over-long method. | `Source/SourceUsageScanner.php:115` |
| SRP-1 | Med | A 713-line single method: `renderCanonical()` spans lines 18–730, with the first private helper at 731. Verified directly. The architecture *around* it is right — `render()` is a one-line delegation that structurally guarantees projection — only the size is the problem. Mechanical, snapshot-verifiable extraction of one method per report section. | `Reporting/MarkdownReportWriter.php:18–730` |
| OCP-1 | Low | Two sites each branch over the same three `RuleDefinition` subtypes; adding a fourth means shotgun surgery. | `LaravelFrameworkIntegration.php:99–140`; `LaravelRuleCatalogValidator.php:70, 89, 111` |
| PARAM-1 | Low | Further parameter-list smells beyond M-F8: `ReportSectionBuilder::build()` 10, `ScenarioResult::__construct` 15, `ReportAssembler::assemble()` 15, `runTargetDiagnostic()` 8. `StageBlockerEntry`'s 22 are mitigated by a `private` constructor. | multiple |
| F9 | Low | `inputFailureReport()` hand-builds a 19-argument `UpgradeReport` including seven consecutive bare `[],`, bypassing `ReportAssembler` entirely — the exact coupling that class exists to prevent. The only real "executes" leak left in an otherwise clean pipeline class. | `DefaultUpgradeAnalyzer.php:249` |
| NAME-1 | Low | The four "Builder" classes are stateless one-shot transforms — zero `return $this;` in the entire scope, two have no properties at all. For a deterministic core that is *better* than a GoF Builder; only the name is wrong. Rename, or document. Do **not** convert them into stateful fluent builders. | `LockDiffBuilder`, `ReportSectionBuilder`, `ProjectStateBuilder`, `StageAssessmentBuilder` |
| PERF-1 | Low | O(n²) evidence deduplication — `addOnce()` linearly scans all evidence deep-comparing `$context` arrays. Key a parallel map by content hash. | `Model/EvidenceLedger.php:59–67` |
| DEAD-1 | Low | `LegacyLaravelPackageRule` is a valid `CompatibilityRule` never instantiated in any `src/` tree — its only constructions are in its own unit test. Wire it into the catalog or delete it. | `laravel/src/Rules/LegacyLaravelPackageRule.php:14` |
| A1 | Low | `symfony/finder` is declared in core's `require` but never used — directory walking uses native `RecursiveDirectoryIterator`. It widens every consumer's install footprint for nothing. | `packages/core/composer.json:28` |
| A2 | Low | Undeclared direct dependency: `Symfony\Component\Console\Output\OutputInterface` is imported but `symfony/console` is not in `require` — it resolves only because `illuminate/console` pulls it in transitively. | `laravel/src/Commands/AnalyzeUpgradeCommand.php:20` |
| F10 | Low | Dead loop variable — `$index` is assigned and never read; the only occurrence in a 690-line file. | `StagedUpgradeOrchestrator.php:199` |

---

## 9. What is genuinely strong

Recorded because an audit that only lists defects misrepresents a codebase, and because several of
these are the reason the defects above are as contained as they are.

- **Dependency direction is clean.** Verified by exhaustive import extraction, not filename
  inference: zero framework imports in core, zero circular package dependencies, zero global
  helpers. Core's only vendor dependencies are `nikic/php-parser`, `composer/semver`, and
  `symfony/{filesystem,process}`.
- **`Analysis/` contains zero native filesystem or process calls.** A grep across the whole namespace
  for `file_get_contents|fopen|mkdir|unlink|exec(|proc_open|new Process|realpath|is_dir|is_file`
  returns no matches. Every I/O path routes through a collaborator. This is the strongest structural
  result in the audit.
- **The failure taxonomy is exemplary.** Three failure types, eleven outcomes, constructor-enforced
  invariants between them — including that a failed outcome cannot carry a candidate lock. Every
  `\Throwable` becomes a structured result rather than aborting analysis.
- **Interface segregation is textbook.** `UpgradeAnalyzer` = 1 method; `CompatibilityRule` = 1
  method; `FrameworkIntegration` = 4 methods with three *optional* capability interfaces layered on
  top. `LegacyTestFrameworkIntegration` implements only the pre-v0.3 subset and still works — proof
  the segregation actually delivers backward compatibility rather than just looking tidy.
- **`EvidenceLedger` enforces the project's own hardest rule.** `validateReferences()` rejects both
  dangling references and orphaned evidence, exactly as the mission statement requires. Deliberately
  the one mutable object, created fresh per run.
- **Zero static mutable state in 132 files.** No `getInstance`, no `private static self`, no static
  caches. 47 model classes, all `final`, private fields, zero setters, mutation via 15 `with*`
  withers.
- **Honest self-reporting at the boundary.** `provenance()` publishes
  `'process_os_isolation' => false`. The limitation is disclosed as evidence rather than concealed —
  which is precisely the disposition the product sells.
- **Low coupling, measured.** 454 `use` statements across 132 files; 65 files import nothing at all;
  mean ≈3.4 imports per file. The three highest are all orchestrators, as expected.
- **Two of four monolith targets genuinely saved.** `DefaultUpgradeAnalyzer::analyzeUpgrade()` is 129
  lines of sequential named delegations with 6 branches and nesting depth 1.
  `ReportAssembler::assemble()` has one branch. Long-but-cohesive, and correctly so.

---

## 10. Prioritized actions

1. **Reopen `DEVELOPMENT_PLAN.md:277` and decompose `StagedUpgradeOrchestrator`** — F1, Critical.
   Split `analyze()` into `StagePlanResolver` + `StageBlockerRegistry` + `StageExecutor`. Highest-value
   change in the codebase, and it closes the actual gap behind a checklist item currently marked
   `[x]`. Per the project's plan-maintenance rule, reconcile the checklist in the same change.
2. **Stop the Markdown writer inventing facts** — V1 High, with V2/V4/V5/V6/V7. Replace every
   fabricated default with an explicit "not recorded by this report schema". Highest
   value-per-effort: small, purely additive, and it removes the only place where this product
   asserts something it did not measure.
3. **Take the two one-line reliability fixes now** — CB-1 and CB-5. Swapping two lines at
   `ComposerScenarioRunner.php:169–170` stops workspace failures being reported as Composer process
   failures. Deleting the unread constant at line 29 removes one of four copies of `300`.
4. **Pull normalization into `UpgradeTarget` and give blocker types an owner** — M-F3 and M-F2, High.
   The two cheapest High findings and the only ones touching a live inconsistency (the Laravel
   adapter's missing `trim`). Both are 8.0-valid without enums.
5. **Give diagnostics a failure class and make truncation visible** — CB-3 and RPT-2, Medium. Add a
   validated `outcome` to `ComposerDiagnostic` using the vocabulary `ScenarioResult` already defines;
   expose `truncated` / `original_bytes` plus a `[REDACTION_FAILED]` marker on PCRE failure.
6. **Plan the framework-neutrality refactor — don't rush it** — ARCH-1, High. The only finding that
   breaks a stated product rule outright, and the one that would actively obstruct the Symfony
   adapter recorded as the first post-v0.3 candidate. But the affected usage-type vocabulary is
   frozen in the signed v0.1 and v0.2.1 contract fixtures, so this needs a scheduled,
   snapshot-proven refactor. **Sequence it before adapter #2, not before the v0.3.0 release.**
7. **Collapse the duplicate construction paths** — F6 and F7, Medium. Make `ReportAssembler`'s
   source-impact argument required so the evidence-free path disappears; extract a shared
   `TestGuidanceCatalog`. Both permit silent divergence in report *content*.
8. **Fix the two `composer.json` metadata mismatches** — A1 and A2, Low. Worth doing before the
   v0.3.0 release, since Milestone 7 runs lowest-dependency consumer installs across every advertised
   Laravel host line — A2 is exactly the class of defect that gate exists to catch.

---

## 11. Method and limits

Six specialized auditors ran in parallel — structural boundaries, orchestration metrics, Markdown
projection, model quality, plus the full pattern and SOLID sweep. Findings carry each auditor's own
IDs; model-layer findings are prefixed `M-` because two auditors independently issued `F1`–`F3`.
Where two auditors reached the same finding by different routes it is marked *corroborated*:
ARCH-1/B3/G1 three times, CB-4/C2 and F4/G2 twice each.

The four highest-severity claims (F1, ARCH-1, V1, SRP-1) were re-verified directly against source
rather than accepted from agent output; the line spans for `analyze()` (46–440) and
`renderCanonical()` (18–730) were confirmed by method-boundary extraction. One sub-auditor overclaim
was caught and corrected during aggregation — see M-F2, where the reported "silently flips the
top-level report verdict" turned out to be a narrower and fail-safe behaviour. Findings not
individually re-read by the coordinating auditor are passed through with their file:line citations
intact.

**Not covered.** This is a static structural review. No tests were run, no coverage or mutation data
was gathered, and no performance measurement was taken — the one performance finding (PERF-1) is an
algorithmic reading, not a benchmark. Security was out of scope beyond the redaction path noted in
RPT-2. `UpgradeReport.php`, `SourceImpactBuilder.php` and `AutoloadOwnershipIndexBuilder.php` were
not read in full, so nothing here claims completeness about their internals beyond measured size and
import counts.

**Line numbers are valid as of commit `91eac8f`.** Re-verify before acting on any specific citation.
