# Claude repository instructions

Preserve and follow the repository rules in `AGENTS.md`.

When creating, preparing, or changing a release tag, updating the GitHub Wiki is mandatory. Follow `wiki/Release-Wiki-Strategy.md`, verify all version/schema/examples against the tagged source, run `composer release:wiki:check`, add versioned four-destination Wiki evidence with real published or reviewed remote commit SHAs and link it from the release notes, validate Wiki navigation and links, and do not mark the release complete until the matching Wiki update is published or explicitly recorded as unchanged after review. If that cannot be done, report the release as blocked.
