# Actionable terminal summary

The generic CLI, Artisan command and wizard now default to `summary`. Select it
explicitly with `--format=summary`. This intentionally changes the previous CLI
and Artisan JSON default and the wizard Markdown default. Scripts that consume
JSON must pass `--format=json`; readers who need the full human report must pass
`--format=markdown`. Both existing full writers and schema `0.8` retain their
canonical semantics. Summary is a presentation choice; its backing canonical
request format remains JSON because schema `0.8` permits only `json` and `markdown`.
The summary display identifies the presentation independently. No release or
version identity changes with this work.

The summary reads typed report objects without calling `UpgradeReport::toArray()`.
It does not serialize raw source usages, evidence contexts, candidate lockfiles or
Composer output. It shows the independent direct and staged results, grouped
recorded dependency causes, blocking versus advisory status, recorded versions,
package provenance, inline evidence references and suggested next actions. These
suggestions come from existing blocker options; the summary does not choose new
versions or infer Composer success. A selected successful stage attempt may
identify its root changes as dependency-checked candidates. Source edits and
application compatibility always require separate review and tests.

The display budgets are two direct blocking groups, six active staged groups and
one advisory group, with one representative per stage before additional causes
from the same stage. Resolved or superseded stage entries are excluded. Additional
groups are counted only when some are hidden. Empty dependency categories,
checked-candidate rows and manual-work sections are omitted, as is PHP provenance
when no PHP change was requested. A simple blocked case fits in about 10–15 lines.

When manual work exists, up to two findings include representative source
locations and framework guidance, with one of each when both are present. The
original source snapshot remains explicit. Incomplete framework coverage, source
omissions, input drift, real operational failures and unmodeled platform limits
remain visible. Recorded uncertainty categories each retain a representative
and discovery of additional entries. Routine Composer execution policy stays in
full details; one application-compatibility qualification ends the summary.
No inventory, transcripts or heuristic effort hours appear by default. Lines are
limited to 320 bytes after complete-value credential/path redaction and
terminal-control removal. Known internal stage stop reasons use plain wording;
their canonical codes remain in full reports.

`--output` and `--save-report` retain their existing routing policies and write
the selected projection. Noninteractive output needs no color or terminal
features. Valid blocked and unknown reports still exit `0`; invalid invocation
and internal failures still exit `2` and `1` respectively, with diagnostics on
stderr where supported.

`--analysis-mode=standard|full` independently selects the Composer execution
policy, defaulting to `standard`. Full report formatting does not imply exhaustive
Composer execution; select `--analysis-mode=full` when that execution is needed.
The request-policy interface comes from the Composer optimization component;
its adaptive execution semantics are verified there and during integration.

The signed historical contract fixtures and report fingerprints remain unchanged.
Current compatibility harnesses explicitly account for this authorized default
migration and request JSON when testing canonical JSON behavior. Focused tests
also verify the new CLI/Artisan/wizard defaults, explicit full formats, later-stage
blockers behind early-stage floods, checked-candidate labels, source omissions,
error paths, privacy, and constant output for an inventory of 50,000 raw usages.
