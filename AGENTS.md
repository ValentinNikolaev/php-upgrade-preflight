# Repository instructions

## Temporary artifacts

- Prefer the operating system's temporary directory for disposable files. Use `build/` only when a repository command requires that location.
- Track every temporary path created during a task and remove it as soon as its output has been consumed or verified.
- Never remove `build/` wholesale. Delete only artifacts known to be reproducible and no longer needed by the current task.
- Preserve user-owned or reusable diagnostics. In particular, keep `build/integration-profile.xml` unless the user explicitly asks to remove it.
- Before deleting pre-existing or unfamiliar artifacts, determine which command created them and whether they are still needed. Ask the user when provenance or value remains uncertain.
- At the end of a task, inspect temporary locations used by the task and report any retained artifacts and why they were kept.
