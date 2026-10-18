# Methodology decision review

Before measured application runs, three independent advisor passes examined the campaign from contrarian, first-principles and execution perspectives. A further pass reviewed anonymized summaries. These are correlated AI engineering opinions, not independent empirical confirmation or intended-user results.

The shared recommendation was a bounded pilot followed by ten applications with repeated, attributable measurements. The strongest concern was that many blocked networked solves could produce a large run count without demonstrating successful staged candidate chaining. Controlled real offline success/failure/termination calibration and explicit reporting of successful live stages therefore remain separate acceptance evidence.

The review identified four changes, reflected in the protocol and inventory:

- Make project diversity auditable through exact commits, original lock hashes, source/dependency sizes, layouts and explicit omissions. The inventory was not yet present when the peer inspected the first protocol commit; `projects.json` now supplies it.
- Count top-level analyzer runs separately from internal scenarios and Composer invocations. The frozen matrix has 37 slots, and failed/retried/pilot attempts retain separate identities.
- Distinguish PHP heap, analyzer RSS, per-child RSS and container peak, with verified attribution and unavailable measurements left unknown. Validate instrumentation before broad execution.
- Keep the budget boundary explicit: nine analysis hours including pilots and retries; acquisition, calibration and review are separate. Preserve partial results rather than quietly expanding or claiming completed R4.

The narrowest residual weakness is selection and oracle coverage. This convenience sample cannot establish market representativeness, general false-negative rates, runtime compatibility or maintainer capacity. A failed six-hop request does not establish the cost of six successful hops. These limits remain open even if every invocation produces a valid report.

Decision: proceed with the calibration and pilot under the predeclared limits. Revise or stop the campaign if instrumentation cannot attribute resource use, input immutability fails, cleanup is unsafe, or the aggregate budget is exhausted. Use the resulting observations, rather than advisor agreement, to decide what technical claims R4 can support. Keep all participant-dependent R3 gates open.
