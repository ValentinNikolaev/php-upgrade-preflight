# Documentation instructions for coding agents

These instructions apply to Codex, Claude, and any other coding agent that changes files in this Wiki.

## Release-tag documentation gate

When work creates, prepares, or changes a release tag, updating the Wiki is mandatory. Do not declare the release task complete until all of these checks pass:

1. Compare the tag version, `ReportMetadata::TOOL_VERSION`, report schema, branch aliases, internal Composer constraints, changelog heading, and release-notes filename.
2. Update every Wiki statement affected by the release, especially `Home.md`, `Getting-Started.md`, `Report-Schema.md`, and `Roadmap-and-Status.md`.
3. Update commands, examples, supported-version claims, compatibility promises, and migration links from repository evidence. Never change only a version number while leaving old behavior claims.
4. Validate every Wiki link and verify that every page listed in `_Sidebar.md` exists.
5. Check examples against the tagged source and canonical report fixtures.
6. Record the Wiki update in the release checklist or release pull request.
7. Materialize and review all four release Wiki trees, publish the matching Wiki commits, and record the real remote commit SHAs before creating the release tag. If any Wiki cannot be published or verified, explicitly block the release.

The repository code is authoritative when it differs from prose. JSON is the canonical report; Markdown is only its projection. Never claim that the analyzer performs an upgrade, executes the application, or proves runtime compatibility.
