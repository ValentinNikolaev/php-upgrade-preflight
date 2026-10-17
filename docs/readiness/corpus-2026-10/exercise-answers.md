# Report-backed exercise answers — engineering preparation

Status: **WITHHELD FACILITATOR KEYS; NOT READY FOR AN UNCORRECTED LIVE R3 STUDY** (2026-10-17).
These are bounded engineering answers to sealed baseline reports, not participant answers or a normal-workflow comparison.
The existing [exercise draft](r3-engineering-materials.md) remains unchanged; the [source oracle](manual-oracle.md)
and independent reviews [A](case-review-a.md), [B](case-review-b.md), and [C](case-review-c.md) qualify these keys.
All seven bound application receipts completed with exit 0; their direct and staged conclusions are **blocked**.
The separately injected timeout receipt also completed with exit 0, but its direct/staged conclusions are **unknown**. Report production is not a successful upgrade.

The baseline contains **four known attribution/provenance defect classes**: A-001 through A-003 and C-009,
with repairs handled separately. B/C reviews record recurrences, not a claim that the live reports were fixed.
C-009 mislabels Firefly candidate `2.0.10` as locked instead of actual pinned `v2.0.8`; it does not prove target incompatibility.
Do not train remediation on copied paths, misassigned diagnostic constraints, false candidate blockers, or candidate-as-locked metadata.
Do not expose these uncorrected reports as ordinary live R3 stimuli. Use a separately frozen, reviewed fixed-report
cohort for an actual study, or explicitly design an annotated evidence-review task without claiming a normal tool comparison.
Keep original artifacts, report/tool revisions and cohort identities; do not rewrite this baseline or pool unlike cohorts.

## Participant-facing prompts — distribute separately from everything below

For the assigned frozen project/request, write a short pre-implementation decision memo:

1. Identify the first recorded dependency blocker and supporting evidence, or explain an honest unknown.
2. Recommend the next safe action and explain why the evidence supports it.
3. Explain direct versus staged scope, including any stop or skipped stage.
4. Identify unobserved runtime, migration, deployment and business work and proposed validation.
5. Recommend a bounded scope/sequence with prerequisites and a condition for proceeding.

Task C is Lychee 12→13/PHP 8.3→8.3; task E is supplemental Monica7 7→13/PHP 7.4→8.3 from the draft.
Additional tasks N (Invoice Ninja 11→12/PHP 8.2→8.2) and K (Koel 10→11/PHP 8.1→8.2) use the same five prompts.
Original cards A (Crater 8->9/PHP 7.4->8.1), B (BookStack 10->11/PHP 8.1->8.2), and D (Bagisto 10->11/PHP 8.1->8.2)
are bound below alongside C and the supplements. These are distinct tasks, not prevalidated equivalent pairs.
Card U is a separately labeled controlled timeout exercise bound to its sealed receipt below.
For U, apply the same five prompts to distinguish operational unknowns from dependency proof; exclude injected-fault timing from ordinary comparisons.

## Facilitator-only provenance and readiness

Withhold the following keys and defect explanations from an ordinary task participant; annotate them explicitly in a rehearsal packet.
Use [canonical R3 criteria/scoring](../decision-study.md#predeclared-decision-criteria), the draft's counterbalancing procedure
and [record form](../decision-study.md#sanitized-study-record-template). No new score, threshold or acceptance rule is introduced.
Use correct/partial/incorrect/unavailable per check with evidence; honest supported uncertainty is acceptable.
Do not reward following a known wrong attribution; retain the participant's original response and incident record if one occurs.
Apply the canonical critical-conclusion stop/correct/remediate procedure to actual attempts, including incomplete/corrected ones.

Report pointers below are RFC 6901 pointers in the named run's canonical JSON. Evidence IDs resolve in its `/evidence` array.
Schema is `0.8`, tool version `0.3.5`; reviewed tool head `6a2251901725321d4206e008ac42205b32ddfa8d`.
Report SHA-256 values below hash the **uncompressed original JSON bytes**, verified against each completion inventory.

| Report ID | JSON SHA-256 | Published portable canonical destination |
| --- | --- | --- |
| `lychee-nearest-cold/attempt-0001` | `9dc6878e05b2f77e22fc4c3662db7d9cca7aa157c9c10b7c9f9bff260333c425` | `evidence/reports/campaign/lychee-nearest-cold/attempt-0001/report.json.gz` |
| `invoiceninja-nearest-cold/attempt-0001` | `e85d4e0da593758d1ad67d977a324be6a9f6eb792a558c8a1feb8ed977a41a01` | `evidence/reports/campaign/invoiceninja-nearest-cold/attempt-0001/report.json.gz` |
| `koel-nearest-cold/attempt-0001` | `d46a28f210a5fb0a2f5f049f472778f6be16a5c5ca83e5e7a5514088302cc0ad` | `evidence/reports/campaign/koel-nearest-cold/attempt-0001/report.json.gz` |
| `monica7-longest-cold/attempt-0001` | `456e52b6fc306cc364a29be623f6b3e3c8fff23751686ba477474473176a1b41` | `evidence/reports/campaign/monica7-longest-cold/attempt-0001/report.json.gz` |
| `crater-nearest-cold/attempt-0001` | `efbf1944065636667248e2ac014a3b0800cb03a34b08ff8afce810505f16ab9a` | `evidence/reports/campaign/crater-nearest-cold/attempt-0001/report.json.gz` |
| `bookstack-nearest-cold/attempt-0001` | `a9a96b959c18d2ade102d87decc5754903effde7e4f73bc3e51fe4cac96dbab4` | `evidence/reports/campaign/bookstack-nearest-cold/attempt-0001/report.json.gz` |
| `bagisto-nearest-cold/attempt-0001` | `53d9b28713b4f47b0d32b6f882ab5c593bedb7f6e6fc4030b28466d695c2d801` | `evidence/reports/campaign/bagisto-nearest-cold/attempt-0001/report.json.gz` |
| `probe-short-timeout/attempt-0001` | `fdf002b77097df4b1104ffd9ec08926a396312b15f9b7485df52626d3a0191d0` | `evidence/reports/campaign/probe-short-timeout/attempt-0001/report.json.gz` |

Current local bodies: `<TASK_TEMP>/campaign-final/runs/<run>/attempt-0001/raw/report.json`, where `<TASK_TEMP>` is
the task-owned operating-system temporary directory `preflight-r4-20261017`.
Portable destination for each row: `evidence/reports/campaign/<run>/attempt-0001/report.json.gz`.
Resolve published artifacts through the [report index](evidence/reports.json), with separate [publication verification](publication-verification.json).
A gzip file has a different compressed-byte hash.
The paired `raw/report.md` is presentation; use the canonical JSON/evidence and the case review to resolve attribution errors.

## Withheld key A - Crater nearest-cold

Pin `05d5ce26fdd8d9466009163444e944259bc0cc2a`; report `crater-nearest-cold/attempt-0001`; [review B](case-review-b.md).

1. **First recorded blocker.** `/blockers/0` names Illuminate support `^9.0`, blocked by Dompdf wrapper `v0.9.0`
   requiring `^5.5|^6|^7|^8`; `solver-1`, `solver-2`, `solver-3`, `solver-5` cite diagnostics, not the first raw error.
   [Pinned wrapper lock](https://github.com/crater-invoice-inc/crater/blob/05d5ce26fdd8d9466009163444e944259bc0cc2a/composer.lock#L207)
   `/packages/3/require/illuminate~1support` confirms it; framework replaces that component at its own version.
2. **Next action.** Review wrapper candidates/replacement and `/transition/root_constraint_changes/0` (`^8.0` to `^9.0`),
   preserving other platform/advisory failures. `/staged_resolution/blocker_registry/1`, `stage-blocker-7cb45ed9670ff76113bd`,
   is the first active Dompdf row (`solver-6` through `solver-8`); registry 0 is nonblocking abandonment metadata.
   **B-001/A-003:** registry 2 assigns S3 adapter target `^3.0` to Flysystem. In
   `/staged_resolution/stages/0/attempts/1/scenario/diagnostics/1`, the target is `league/flysystem-aws-s3-v3`,
   not a root Flysystem request. Candidate 3.35.3 requires Flysystem ^3.10.0 versus locked 1.1.9: preserve that genuine
   old-lock issue, but do not claim ^3.0 and ^3.10.0 are disjoint. Choosing S3 3.x exceeds original root ^1.0.
3. **Direct/staged meaning.** Both status fields are blocked; all five post-baseline direct scenarios fail.
   `/staged_resolution/stages/0` has three failed attempts, null output, no selected candidate and
   `/staged_resolution/stop_reason=blocking_registry_not_cleared`. No transition completed or later hop was requested.
4. **Unseen work.** `/source_inventory/1862` and `/source_inventory/1864` (`source-1876`, `source-1878`) detect Fideloper use.
   [TrustProxies.php:5](https://github.com/crater-invoice-inc/crater/blob/05d5ce26fdd8d9466009163444e944259bc0cc2a/app/Http/Middleware/TrustProxies.php#L5)
   confirms import/inheritance. Pin historical 8->9 guidance and review trust/header behavior before edits; empty `/source_impact`
   is not proxy/CSRF validation. Inspect migrations, mail/storage behavior, tests, target platform and deployment separately.
5. **Scope/sequence.** Investigate the bounded dependency/platform remediation, repair attribution, then rerun before changing the lock.
   Retained 7,997 usages with no cap warning do not establish complete recall; absent Modules/vendor and ownership gaps remain limits.
   A direct strategy named `staged-targets` is not a completed stage, and replacement of abandoned Swiftmailer alone cannot clear Dompdf.

## Withheld key B - BookStack nearest-cold

Pin `07e45a20e55fc955e9ef252d0653f23193a71a77`; report `bookstack-nearest-cold/attempt-0001`; [review B](case-review-b.md).

1. **First recorded blocker.** `/blockers/0` identifies locked Collision `v7.11.0` conflicting with framework `>=11.0.0`
   against target `^11.0`; `solver-1`, `solver-2`, `solver-3`, `solver-5` contain the diagnostic relation.
   [Pinned Collision lock](https://github.com/BookStackApp/BookStack/blob/07e45a20e55fc955e9ef252d0653f23193a71a77/composer.lock#L8491)
   `/packages-dev/7/conflict/laravel~1framework` confirms it; primary output instead begins with advisory exclusions.
2. **Next action.** Review joint Collision/framework/mailer/testing candidates and the intended PHP/platform conditions while retaining advisory policy.
   **B-002/A-001/A-003:** `/staged_resolution/blocker_registry/1` calls PHP requested `^11.0` and concatenates sibling
   diagnostic lines into Collision->framework->PHP ancestry. Actual `/request_summary/target_php` and stage `analysis_php` are 8.2.0;
   the diagnostic's old 8.1.0 platform is not proof that modeled target 8.2 is below ^8.2. Reconstruct identity/scope before using that row.
   **B-003/A-003:** registry 2 borrows Collision ^8.1 as the framework request. Actual framework ^11.0 partly overlaps
   candidate Collision conflict `<11.48.0 || >=14.0.0`; do not call this a wholly nonoverlapping A-002 case or a proven viable subset.
3. **Direct/staged meaning.** Both statuses are blocked; five post-baseline direct scenarios fail. The one
   `/staged_resolution/stages/0` has three failed attempts, no selection, null output and the registry-not-cleared stop.
   The superseded original Collision row does not clear the stage or validate its later candidate rows.
4. **Unseen work.** Review the custom mailer fork's replacement/autoload strategy: [pinned lock:5571](https://github.com/BookStackApp/BookStack/blob/07e45a20e55fc955e9ef252d0653f23193a71a77/composer.lock#L5571)
   `/packages/79/replace/symfony~1mailer=^6.0` does not itself supply Mailer ^7.0.3 required by the observed framework candidate.
   Runtime/coinstallation remain unverified. `/framework_findings/2` conditions DBAL removal on Laravel-only use;
   independently validate SAML/CSRF, database/migrations, mail, tests and deployment before removal or rollout.
5. **Scope/sequence.** Start with corrected attribution and joint candidate investigation, then rerun and validate a candidate before implementation.
   Locked PHPUnit 10.5.38 fits the pinned Laravel 11 review range, so Laravel alone does not require PHPUnit 11;
   observed Collision 8.9.5 separately conflicts with PHPUnit <11.5.50. Investigate alternatives without declaring a solution.
   `source-limit-1` records 10,000 retained/692 omitted usages; config lives in `app/Config/`, not absent root `config/`.
   Neither retained tests nor Composer's `phpunit` script is a test result; no completed transition or full recall is established.

## Withheld key D - Bagisto nearest-cold

Pin `8c33fbbd89b64043d98697f5590d6de658a9241b`; report `bagisto-nearest-cold/attempt-0001`; [review C](case-review-c.md).

1. **First recorded blocker.** `/blockers/0` identifies Breadcrumbs `v8.1.1` requiring framework majors 6-10 against `^11.0`;
   `solver-1` and `solver-6` support matching direct/staged ancestry. [Pinned lock:534](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/composer.lock#L534)
   `/packages/7/require/laravel~1framework` confirms it; `/staged_resolution/blocker_registry/2` is first active
   (`stage-blocker-9e3e5f06abdb381696f9`), after two nonblocking metadata rows. Primary errors also include missing Calendar
   in the experiment's platform; this is not verified absence in production.
2. **Next action.** Review allowed candidates and root framework adjustment without treating the whole Breadcrumbs ^8.0 root as unsatisfiable.
   **C-007/A-001:** `/blockers/1/dependency_path` and registry 3 reuse Breadcrumbs ancestry for the separately rooted OpenAI adapter;
   blocker 2/registry 4 do likewise for Collision. Reconstruct relationships, preserving genuine locked restrictions.
   **C-008/A-003:** registry 5 calls console requested ^4.0, but
   `/staged_resolution/stages/0/attempts/1/scenario/diagnostics/1` targets Sanctum ^4.0; registry 6 borrows Collision ^8.1 for framework.
   Do not select console 4 or follow those wrong requests. Preserve genuine Pest conflict `>10.5.5` at registry 7
   (`stage-blocker-045a4e8743643114640a`); review Pest/PHPUnit jointly rather than force PHPUnit 11 or dismiss valid constraints.
3. **Direct/staged meaning.** Both statuses are blocked; five post-baseline direct scenarios fail. The sole stage has
   three failed attempts, null output, no selection and `blocking_registry_not_cleared`. Collision registry 4 being resolved
   does not make a successful transition; the direct `staged-targets` strategy is not another executed stage.
4. **Unseen work.** `/request_summary/source_paths=[]` chose default directories; `/source_inventory` retains **598** usages,
   **zero `packages/` entries and no usage-cap warning**. All **31 Webkul source roots are excluded** from this default analysis.
   [Pinned root autoload](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/composer.json#L66)
   maps first-party classes directly; there is **no root path repository**. This is a documented default-selection limit with
   missing project-specific qualification, not full application profiling or proof of absent first-party code.
   [Customer.php:10](https://github.com/bagisto/bagisto/blob/8c33fbbd89b64043d98697f5590d6de658a9241b/packages/Webkul/Customer/src/Models/Customer.php#L10)
   independently anchors Sanctum use outside inventory. `/framework_findings/6` (`laravel-package-advisory-2`) supports migration review,
   not customer/admin auth validation. Explicitly inspect package roots, checkout/payment/order behavior, migrations, tests and deployment.
5. **Scope/sequence.** Resolve supported platform/dependency questions and corrected attribution, then arrange separately authorized
   explicit source-scope review before claiming application-wide coverage. No extra scan is performed here. `/source_impact=[]`
   and the 598-usage resource receipt describe this conventional-directory analysis and dependency work only.
   Locked Sanctum excludes 11; the locked Pest Laravel plugin admits 11 but does not clear Pest's separate PHPUnit restriction.
   Rerun only after reviewed remediation, then validate each applied transition and application behavior before rollout.

## Withheld key C — Lychee nearest-cold

Pin `bf5495c706a9971ebfcd03a88c36e336fc0261d5`; report `lychee-nearest-cold/attempt-0001`.

1. **First recorded blocker.** `/blockers/0` identifies framework `^13.0` blocked by locked
   `spatie/laravel-image-optimizer` `1.8.2`, whose requirement permits Laravel 8–12. Inspect `solver-1`, `solver-2`, `solver-3`;
   `solver-2` includes the matching Spatie error. This is the first summarized finding, not an exhaustive causal ordering.
2. **Next action.** Review candidate package metadata and allowed update scope for Spatie and other genuine constraints,
   alongside `/transition/root_constraint_changes/0` (`^12.0`→`^13.0`). Do not assume root Spatie `^1.8` is unsatisfiable.
   **A-001:** `/blockers/1/dependency_path` and `/staged_resolution/blocker_registry/2/dependency_path` wrongly reuse
   Spatie ancestry for Collision (`stage-blocker-768a66087f489bed28c0`). Collision has a genuine locked conflict,
   but rebuild its path from root require-dev/lock evidence; do not remediate it through Spatie or treat the cited excerpts as Collision proof.
3. **Direct/staged meaning.** `/resolution/status` and `/staged_resolution/status` are blocked.
   `/resolution/scenarios/4` is a successful PHP-only scenario, not Laravel 13 resolution. The single
   `/staged_resolution/stages/0` is evaluated/blocked after three attempts with null `output_state`;
   `/staged_resolution/stop_reason` is `blocking_registry_not_cleared`. No transition completed.
4. **Unseen work.** `source-limit-1` records 10,000 retained usages/8,613 omitted; retained inventory is under `app/`.
   Other declared roots and absent vendor code prevent a full-coverage claim. Review CSRF overrides and inherited behavior
   (`/source_impact/0/occurrences/0`, `source-6245`, `ownership-1`, `laravel-request-forgery-guidance-1`), then test authentication,
   migrations, target runtime/platform and deployment separately. Deprecated aliases are not asserted removed.
5. **Scope/sequence.** Begin with constraint/candidate investigation and attribution correction, then rerun isolated analysis
   after reviewed remediation; only a validated candidate can precede application checks. `/framework_findings/2`
   supports reviewing PHPUnit 11.5.42 against the reviewed 11.5.50 floor, not a mandatory PHPUnit 12 upgrade.
   Do not authorize rollout, infer calibrated effort, or call the identical longest target extra transition coverage.

## Withheld key N — Invoice Ninja nearest-cold

Pin `c064f1c3e9163ca0758eb36df5d7947b5c8e19c6`; report `invoiceninja-nearest-cold/attempt-0001`.

1. **First recorded blocker.** `/blockers/0` identifies locked Collision `v8.1.1` conflicting with framework
   `<11.0.0 || >=12.0.0` against requested `^12.0`. `solver-2` and `solver-3` contain the relation;
   `solver-1` instead starts with a locked cron-expression partial-update failure and is not independent Collision proof.
2. **Next action.** Review compatible Collision candidates and the root framework change at `/transition/root_constraint_changes/0`.
   Preserve the genuine Compoships restriction `>=5.6 <12.0` at `/staged_resolution/blocker_registry/2`
   (`stage-blocker-9176a97e40dacb9bf974`, `solver-5`, `solver-6`) and pinned einvoice collections 10/11 constraint from the oracle.
   Candidate availability within existing root ranges/branch remains unverified; no blanket removal or root widening follows.
   **A-002:** `/staged_resolution/blocker_registry/3` (`stage-blocker-d186482ac1bcca896c79`) wrongly makes candidate
   Collision conflict `<11.48.0 || >=14.0.0` block `^12.0`; that interval does not exclude Laravel 12.
   `/staged_resolution/stages/0/attempts/1/scenario/diagnostics/1` shows candidate provenance. Do not remove Collision solely for that row;
   distinguish it from the original locked conflict in the superseded registry entry 1.
3. **Direct/staged meaning.** Both status fields are blocked. `/resolution/scenarios/4` succeeds only for PHP;
   `/staged_resolution/stages/0` has three failed attempts, null output state and the registry-not-cleared stop.
   The false candidate row does not erase independent blockage, and neither report production nor PHP-only success clears Laravel 12.
4. **Unseen work.** `source-limit-1` records 10,000 retained/43,607 omitted usages, all retained inventory under `app/`;
   `/source_impact` is empty, not a compatibility certificate. `/source_inventory/0`, `source-1`, records an EncryptedCast import.
   Inspect omitted roots and separately validate custom casts, billing, integrations, migration data, target platform and deployment.
   The source oracle identifies an Integration suite; absence of a Composer test script is not absence of tests.
5. **Scope/sequence.** Investigate genuine root/locked constraints and candidate metadata, correct attribution, then rerun before lock changes.
   `/framework_findings/3` supports reviewing PHPUnit 10.5.24 against a 10.5.35+ patch floor within root `^10`;
   it does not mandate a new PHPUnit major or establish billing behavior. Advance only after dependency and application validation.

## Withheld key K — Koel nearest-cold

Pin `ad9d1b57e15f1e14d915ac5c7ada7206bf9a3b71`; report `koel-nearest-cold/attempt-0001`.

1. **First recorded blocker.** `/blockers/0` identifies Collision `v7.10.0` conflicting with framework `>=11.0.0`
   against target `^11.0`; `solver-1`, `solver-2`, `solver-3`, `solver-5` support it through framework diagnostics.
   The primary failures also include security-advisory exclusions and missing `ext-xsl`; this is not a complete failure ordering.
2. **Next action.** Inspect those platform/advisory conditions and compatible dependency candidates while preserving advisory policy.
   `/transition/root_constraint_changes/0` changes framework `^10.0`→`^11.0`; `/framework_findings/3` reviews Sanctum
   `v3.3.3` against `^4.0` (`laravel-package-laravel_sanctum-1`, `laravel-package-guidance-2`). Choosing ^4 deliberately changes
   root Sanctum ^3.3; the locked Tinker permits 11 and is not a nearest-hop incompatibility.
   **A-003:** `/staged_resolution/blocker_registry/1` (`stage-blocker-3dd0578d3e3e0b845bc9`) attaches requested `^4.0`
   to `illuminate/console`. `/staged_resolution/stages/0/attempts/1/scenario/diagnostics/1` targets **Sanctum ^4.0**;
   candidate v4.3.3 requires console 11/12/13. Do not select console 4 or bypass advisories/extensions to follow that row.
3. **Direct/staged meaning.** Both statuses are blocked; `/resolution/scenarios/4` PHP-only and `/resolution/scenarios/5`
   named `staged-targets` fail. That historical strategy name is not an executed stage. The real `/staged_resolution/stages/0`
   has three failed attempts, null output state and the registry-not-cleared stop; no transition succeeded.
4. **Unseen work.** `/source_inventory/2224`, `source-2237`, records Sanctum HasApiTokens at User.php:19;
   `/framework_findings/6` requests migration review (`laravel-advisory-laravel_sanctum-1`, `laravel-package-advisory-2`).
   Review auth.php:40, sanctum.php:41 and custom/default middleware; validate migrations and stateful auth on the target runtime.
   `/source_impact` is empty; 7,697 retained usages without a usage-cap warning still do not prove full recall or deployment readiness.
5. **Scope/sequence.** Resolve platform/advisory investigation and candidate selection with corrected target identity,
   then rerun analysis; review Sanctum configuration/migrations and application tests before rollout. Preserve case-distinct song fixtures.
   Do not interpret a successful baseline validation as successful upgrade feasibility or the report's range as measured upgrade cost.

## Withheld key E — supplemental Monica7 longest-cold

Pin `6d809fda1d59048521c6d5c7e8c3306039b18d4c`; report `monica7-longest-cold/attempt-0001`.

1. **First recorded blocker.** `/blockers/0` identifies WebAuthn `0.9.0` requiring framework
   `^5.8 || ^6.0 || ^7.0 || ^8.0`, excluding final `^13.0`; `solver-1`, `solver-2`, `solver-3`, `solver-5`
   include matching diagnostics. This requirement admits the first Laravel 8 hop and is not itself the first-hop blocker.
   Primary errors start with PHP mismatch; `/blockers/3` records root PHP `^7.2.5` excluding requested 8.3.
2. **Next action.** Review root PHP/framework and locked-package constraints, then the first actual hop's Testbench restriction:
   `/staged_resolution/blocker_registry/2` (`stage-blocker-783fe94604c33188f02c`) requires framework `^7.10`
   against `^8.0`, supported by `solver-6`, `solver-7`, `solver-8`. Locked Cashier/UI also require separate candidate work.
   **A-001 also affects Monica7:** `/blockers/1/dependency_path` and `/blockers/2/dependency_path` reuse WebAuthn ancestry
   for Sabre and Testbench. Preserve genuine constraints but reconstruct each relationship independently; do not follow those paths.
3. **Direct/staged meaning.** Both statuses are blocked. `/staged_resolution/stages/0` (7→8) is evaluated/blocked
   with three failed attempts and null output state; stages 1–5 are skipped with zero attempts and `previous_stage_blocked`.
   **Six planned hops mean one blocked/five skipped, zero completed transitions and no successful full chain.**
   All stages use original-project source; first-hop analysis PHP is 8.3, not an observed runtime migration from 7.4.
4. **Unseen work.** `source-limit-1` records 10,000 retained/9,223 omitted usages; zero retained test usages does not mean no tests.
   `/source_impact/0/occurrences/0` and `/source_impact/0/occurrences/1` (`source-122`, `source-3498`) detect dispatch_now
   in ImportVCards.php:95 and SettingsController.php:192. `/framework_findings/18` carries 9→10 guidance, but that hop never executed.
   The coordinator's [historical source pin supplement](historical-guide-check.json) supports reviewing synchronous replacements;
   it is post-review engineering evidence, not participant data or a rewritten report. Resolve helper ownership, preserve returned SQL-export
   paths/exception behavior, and separately validate billing/auth, migrations, tests, runtime and deployment. Preserve case-distinct fixtures.
5. **Scope/sequence.** Start with the blocked 7→8 dependency/platform assessment, not an instruction to implement all six hops.
   Investigate compatible candidates and legitimate root changes, correct ancestry, rerun, then validate each applied hop before advancing.
   Treat later-hop source guidance as original-snapshot review questions; no post-upgrade source state, candidate chain or runtime outcome exists.

## Withheld key U - controlled engineering timeout exercise

Report `probe-short-timeout/attempt-0001`; Lychee pin `bf5495c706a9971ebfcd03a88c36e336fc0261d5`, Laravel 12->13/PHP 8.3->8.3.
This is an **injected fault**, not an ordinary upgrade task or an actual participant outcome. Its sealed completion receipt says
completed/exit 0, no external watchdog timeout and cleanup verified; the analyzer's subprocesses still timed out.
The invocation records `--probe timeout`, scenario limit 45 seconds, diagnostic limit 15 seconds and overall watchdog 540 seconds.
Do not mistake the run label "short-timeout" for a one-second configured timeout or an observed dependency result.

1. **First blocker or honest unknown.** No resolution-blocking dependency was established by these timed-out solves.
   `/resolution/status=unknown`; `/resolution/scenarios/0` through scenario 4 all have `outcome=timeout`, exit -1,
   operational failure type and null candidate lock. `/blockers/0` is only abandoned `php-http/message-factory` metadata
   (`lock-metadata-1`), not proof it caused the timeout or blocks Laravel 13. Do not import the different nearest-cold report's blocker.
2. **Next safe action.** Restore ordinary execution conditions by removing the injected timeout behavior, verify the declared toolchain,
   platform, transport/cache and execution limits, then obtain a separately identified rerun if authorized. Do not change dependency
   constraints merely to fix this operational failure or bypass security/platform policy. `/composer_execution/scenario_timeout_seconds`
   and `/request_summary/composer_execution/scenario_timeout_seconds` both record 45; increasing a limit alone does not remove an injected fault.
3. **Direct/staged meaning.** `/staged_resolution/status=unknown`; `/staged_resolution/stop_reason=timeout`.
   `/staged_resolution/stages/0` is evaluated/unknown, with one `target_only` attempt, no selection and null output state.
   `/staged_resolution/stages/0/attempts/0/scenario/stderr_excerpt` records the 45-second process timeout;
   `stage-attempt-1` independently labels its outcome timeout. No later stage was requested and no transition completed.
   `laravel-stage-target-1` supplies the requested target, not a successful candidate or runtime validation.
4. **Unseen work and unchanged inputs.** `raw/run.json` records `immutable=true` and identical `/input_before/sha256`
   and `/input_after/sha256`: `1bc0805d4cd631d132eeda4072cbf0d5c62161187da8fb298fbbec05d5b4a1d6`.
   These are sealed harness observations, not a newly computed source-tree hash. The manifest and lock before/after values also match.
   `source-limit-1` records 10,000 retained/8,613 omitted usages; `/source_impact/0/occurrences/0` detects original-source
   CSRF inheritance despite failed execution. Neither source guidance nor unchanged bytes establishes runtime behavior.
   Application tests, auth/CSRF, migrations, deployment, target platform and business behavior all remain unobserved.
5. **Scope/sequence.** Scope the next step as execution recovery and a reviewable new analysis, then dependency decision-making only
   after valid evidence exists. Keep this injected-fault cohort separate from genuine task records: it cannot stand for normal
   participant workflow timing, be pooled into planning-benefit comparisons, or establish a compatibility/pass rate.
   Preserve this report and receipt as operational evidence; do not rewrite unknown into feasible, blocked or zero-risk.

## Human-study work still required

Participant answers, normal-workflow timings, comprehension/pass rates, paired quality/time benefit, demand, voluntary second-decision
reuse and Symfony-owner cases all remain **UNAVAILABLE**. No zero incidents, zero cost or observed safety rate follows from absent study records.
The user's stated **>5 hours/week** maintenance capacity is a stated lower bound, not observed maintenance cost.
A real study still needs a reviewed frozen cohort, consented intended-user tasks, equivalence rationale, counterbalanced assignments,
actual timed observations and evidence-backed scoring under the unchanged canonical protocol; real voluntary reuse and rooted Symfony cases
must be observed separately. No outreach, application execution or new solve is authorized by this answer-key preparation.
