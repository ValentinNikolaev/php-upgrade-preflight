# Repository instructions

## Temporary artifacts

- Prefer the operating system's temporary directory for disposable files. Use `build/` only when a repository command requires that location.
- Track every temporary path created during a task and remove it as soon as its output has been consumed or verified.
- Never remove `build/` wholesale. Delete only artifacts known to be reproducible and no longer needed by the current task.
- Preserve user-owned or reusable diagnostics. In particular, keep `build/integration-profile.xml` unless the user explicitly asks to remove it.
- Before deleting pre-existing or unfamiliar artifacts, determine which command created them and whether they are still needed. Ask the user when provenance or value remains uncertain.
- At the end of a task, inspect temporary locations used by the task and report any retained artifacts and why they were kept.

## Mandatory Wiki updates for release tags

- Any task that creates, prepares, or changes a release tag must update the GitHub Wiki before the release is considered complete.
- Follow `wiki/Release-Wiki-Strategy.md`: verify version and schema claims against source, update affected Wiki pages and examples, run `composer release:wiki:check`, add versioned four-destination Wiki evidence with real published or reviewed remote commit SHAs and link it from the release notes, validate links/sidebar coverage, and ensure the release process includes publication of the matching Wiki commit.
- This rule applies to Codex, Claude, and every other coding agent working in this repository. If the Wiki cannot be updated or published, report the release as blocked rather than silently shipping stale documentation.
