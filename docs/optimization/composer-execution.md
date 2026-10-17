# Adaptive Composer execution

`UpgradeRequest` accepts an optional final `string $analysisMode = 'standard'`
argument, after `$composerExecution`. `analysisMode()` returns `standard` or
`full`; other values throw `InvalidArgumentException`. This execution policy is
not added to the serialized request: report schema `0.8` is unchanged.

Standard mode validates the unchanged baseline, then runs the exact target. If
that attempt fails, it still runs the all-dependencies (`-W`) alternative for
package targets. PHP-only updates already update the entire dependency graph,
so their ineffective `-W` alternative remains deduplicated. A successful exact
target is sufficient to stop further full-target alternatives.

Standard mode omits the minimal-change comparison and the optional PHP-only and
current-PHP package ordering probes. Existing uncertainties identify every
omitted candidate and state that no outcome was inferred. Standard mode does
not claim to select the smallest-change lock. A failed baseline never makes a
target feasible, and an operational failure never becomes a solver block.

Full mode retains the exhaustive direct alternatives and their candidate-lock
comparison. Both modes retain the requested framework stage plan, its real
Composer attempts, remediation and candidate-state chain. A staged attempt is
not replaced by an invented execution record, even if it resembles a direct
attempt. Partial ordering probes and framework stage transitions are different
operations.

## Diagnostic memoization

Only locked `prohibits` diagnostics are memoized, with at most 256 entries per
analysis. The key includes the exact effective workspace manifest and lock,
project/repository path context, executable and execution configuration, Composer
version, platform assumptions/profile and environment. A failed or unavailable
diagnostic is retried. Equivalent direct and staged diagnostic inputs may share
an answer; changed manifests, locks, repositories, platform or execution settings
cannot. Solver results are not cached, and a new analysis resets all probe and
diagnostic caches. Persistent solver caching remains deferred until repository
metadata freshness can be established.

## Measurements and verification

Completed phase and analysis progress events expose `durationMs()`. Scenario
events retain the existing duration scope, including workspace preparation,
diagnostics and cleanup. `ComposerScenarioRunner::measurements()` provides
per-analysis process counts and elapsed milliseconds separately for scenario,
diagnostic and metadata adapters, plus workspace creation/preparation/cleanup
time and diagnostic cache hits. A failed process start counts as an adapter
invocation. Custom metadata resolvers are not counted as child processes. These
observations do not add machine stdout or report fields. Workspace and process
measurements exclude other analysis work and must not be presented as total
analysis duration.

`AdaptiveComposerExecutionTest` uses fresh copies of the committed offline
path-repository fixture and restricted Composer execution. It verifies input
immutability, full/standard resolution and lock-diff agreement for these cases,
process reduction and equal first/repeat process counts. A separate real Composer
fixture proves that a locked-root conflict fails exact-target and succeeds with
`-W`. Unit tests cover failed baseline validation, solver versus operating
failures, cached/uncached diagnostic equivalence and cache identity changes.

An illustrative Docker PHP 8.3.33 run on 2026-10-17, using package target
`fixture/dependency`, current PHP `8.0` and target PHP `8.3`, observed:

| Offline target | Full processes (scenario/diagnostic/metadata) | Standard processes | Process reduction |
| --- | --- | --- | --- |
| Available `^2.0` | 6 / 0 / 1 | 2 / 0 / 1 | 7 to 3 (57%) |
| Unavailable `^3.0` | 6 / 3 / 1 | 3 / 2 / 1 | 10 to 6 (40%) |

Repeated analyses used the same counts because no solver result persists across
runs. The corresponding repeat wall times were approximately 955 vs 366 ms for
the available target and 1272 vs 848 ms for the unavailable target. These are
single illustrative observations on a small local fixture, with fewer requested
comparisons in standard mode; they are not like-for-like solver speedups or proof
of the broader 30% cold/50% repeat performance goals. No public-corpus performance
claim follows from these measurements.
