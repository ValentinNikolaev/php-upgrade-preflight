# Portable R4 evidence

publisher-verified portable projection/attestation.

Published file/gzip hashes, original hashes of included reports, and included canonical content can be independently verified.

Original private plan, receipts, invocations, cache-lineage and host-cleanup checks cannot be independently rerun from this bundle because their metadata and private paths are excluded; these checks are publisher attestations.

Hashes and sizes of omitted malformed artifacts attest to privately observed bytes; their contents are unavailable for independent recomputation.

diagnostic_calls, direct_diagnostic_calls and staged_diagnostic_calls count canonical diagnostic entries, including reused/cached entries, not unique spawned processes. Composer started_count and invocation kinds (--version, validate, update, prohibits and others) form a separate process inventory; do not derive process starts by summing report diagnostic entries.

Canonical gzip contents are unchanged. Malformed unsealed content is omitted and indexed as unavailable with original hash/size; no bytes are repaired or promoted. Missing numeric values remain null.

Legacy diagnostics are explicit unverified extras excluded from campaign slots. The path scan covers common Unix/Windows host paths and known input/temp roots; it is not a general secrets detector.
