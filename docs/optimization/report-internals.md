# Report evidence validation follow-up

An offline replay of the recorded Invoice Ninja candidate-first inventory found
repeated work in `UpgradeReport::evidenceReferences()`. Its 10,000 usages,
10,248 evidence items and 160 uncertainties made reference collection more
expensive than ledger registration or source-impact correlation in this replay.

Reference collection now appends to one local list instead of copying the growing
list for each finding. Uncertainty text is tokenized once using the existing
ASCII evidence-reference boundaries. Ordinary evidence IDs use that token index;
IDs containing other characters retain the existing quoted literal matching.
Validation still rejects empty references, missing evidence and orphaned evidence.
Reference order and duplicate handling remain unchanged.

## Measured scope

Baseline: `6fe3249686866e0b9764ceb7eb2b51c89aad88e6`. Three consecutive samples
per implementation, PHP 8.3.33 in offline Docker Desktop Linux containers with
Windows bind mounts and existing vendor dependencies:

| Replay phase | Baseline median seconds | Candidate median seconds |
| --- | ---: | ---: |
| Evidence-reference collection | 0.803035 | 0.004404 |
| Ledger registration | 0.026616 | 0.026192 |
| Source-impact correlation, without ownership index | 0.023037 | 0.024702 |

The ordered 10,268-reference result, including duplicates, has the same SHA-256
before and after. The source-impact result is also identical. The replay uses
the recorded inventory, framework findings, evidence, blockers, plan and
uncertainties; it omits framework guidance and replaces staged resolution with
a skipped state to isolate reference collection. It does not run project code,
rescan source or invoke Composer.

This local result does not explain the earlier 14.6-second composite analyzer
assembly phase or establish an end-to-end speedup. That phase also includes
ownership, staged assessment and input-consistency rechecks. There is no new
corpus comparison, memory claim or native-Linux measurement.

## Integrity checks

- The focused model, assembler, section, source-impact, schema and three writer
  suites pass on PHP 8.0 and 8.3: 126 tests, 793 assertions each.
- The 27 model tests also pass against the original implementation, confirming
  that the added boundary, order and integrity cases describe existing behavior.
- A separate reduced report replay retains all recorded inventory and evidence,
  attaches every evidence ID to a replay plan, and exercises blocked, unknown
  and feasible direct outcomes. All nine JSON, Markdown and summary hashes and
  byte counts match the original implementation. This is output parity for
  those fixtures, not a reconstruction of the original staged analysis.
- PHPStan development and production checks cover the changed files; PHP 8.0
  formatter checks pass. No full gate was repeated for this bounded change.

The public report return contract, eager validation, complete source inventory,
canonical writers, privacy processing and schema 0.8 are unchanged. No lazy
materialization or summary-specific evidence reduction was introduced. Raw
replay scripts and receipts are retained under the task's OS temporary evidence
directory, `preflight-performance-20261017/report-internals/`.
