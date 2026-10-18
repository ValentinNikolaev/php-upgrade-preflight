# R4 published campaign evidence

Status: completed. Missing values are `null`; failed and unavailable probes are retained.
CSV includes every attempt and not-run slot; this table shows the latest declared slots.
publisher-verified portable projection/attestation. Published file/gzip hashes, original hashes of included reports, and included canonical content can be independently verified.
Original private plan, receipts, invocations, cache-lineage and host-cleanup checks cannot be independently rerun from this bundle because their metadata and private paths are excluded; these checks are publisher attestations.
diagnostic_calls, direct_diagnostic_calls and staged_diagnostic_calls count canonical diagnostic entries, including reused/cached entries, not unique spawned processes. Composer started_count and invocation kinds (--version, validate, update, prohibits and others) form a separate process inventory; do not derive process starts by summing report diagnostic entries.
Composer RSS scope is retained per invocation. `composer_php_process` means own Linux `/proc/self/status` VmHWM; absent scope is unknown, including legacy. Peaks are not concurrent sums.

| Run | Attempt | Status | Probe | Direct state | Staged state | Direct | Staged | Direct diagnostic entries | Staged diagnostic entries | Analysis seconds | Composer RSS KiB | RSS scopes |
| --- | --- | --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| crater-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 8 | 78.495473314 | 213920 | ["composer_php_process"] |
| crater-nearest-warm | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 8 | 70.850644875 | 214156 | ["composer_php_process"] |
| crater-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 8 | 85.290820909 | 213972 | ["composer_php_process"] |
| bookstack-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 8 | 50.35387105 | 144920 | ["composer_php_process"] |
| bookstack-nearest-warm | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 8 | 50.105384615 | 144400 | ["composer_php_process"] |
| bookstack-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 8 | 52.940045592 | 144904 | ["composer_php_process"] |
| lychee-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 5 | 3 | 6 | 8 | 164.22112714 | 193816 | ["composer_php_process"] |
| lychee-nearest-warm | attempt-0001 | completed | null | unknown | unknown | 5 | 1 | 0 | 0 | 29.876329856 | 49132 | ["composer_php_process"] |
| lychee-longest-cold | attempt-0001 | completed | null | blocked | blocked | 5 | 3 | 6 | 8 | 161.15995717 | 193760 | ["composer_php_process"] |
| snipeit-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 11 | 131.592168779 | 233904 | ["composer_php_process"] |
| snipeit-nearest-warm | attempt-0001 | completed | null | unknown | unknown | 6 | 1 | 0 | 0 | 21.571644547 | 49200 | ["composer_php_process"] |
| snipeit-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 11 | 133.837593016 | 233724 | ["composer_php_process"] |
| invoiceninja-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 5 | 3 | 6 | 9 | 212.111185817 | 262928 | ["composer_php_process"] |
| invoiceninja-nearest-warm | attempt-0001 | completed | null | unknown | unknown | 5 | 1 | 0 | 0 | 53.835172101 | 49876 | ["composer_php_process"] |
| invoiceninja-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 7 | 9 | 238.314150995 | 263048 | ["composer_php_process"] |
| akaunting-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 11 | 95.754464575 | 213768 | ["composer_php_process"] |
| akaunting-nearest-warm | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 11 | 93.001984523 | 214064 | ["composer_php_process"] |
| akaunting-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 11 | 98.115256741 | 213928 | ["composer_php_process"] |
| monica-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 5 | 3 | 7 | 8 | 98.134550164 | 270060 | ["composer_php_process"] |
| monica-nearest-warm | attempt-0001 | completed | null | blocked | blocked | 5 | 3 | 7 | 8 | 96.509917694 | 269784 | ["composer_php_process"] |
| monica-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 8 | 89.015478944 | 271948 | ["composer_php_process"] |
| koel-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 10 | 66.408612448 | 176396 | ["composer_php_process"] |
| koel-nearest-warm | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 10 | 62.603102288 | 176160 | ["composer_php_process"] |
| koel-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 10 | 65.157792163 | 176640 | ["composer_php_process"] |
| bagisto-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 10 | 62.916506509 | 197196 | ["composer_php_process"] |
| bagisto-nearest-warm | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 10 | 60.984566616 | 197592 | ["composer_php_process"] |
| bagisto-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 10 | 63.891391664 | 197428 | ["composer_php_process"] |
| firefly-nearest-cold | attempt-0001 | completed | null | blocked | blocked | 5 | 2 | 7 | 4 | 69.245875732 | 162404 | ["composer_php_process"] |
| firefly-nearest-warm | attempt-0001 | completed | null | blocked | blocked | 5 | 2 | 7 | 4 | 66.522847876 | 162524 | ["composer_php_process"] |
| firefly-longest-cold | attempt-0001 | completed | null | blocked | blocked | 5 | 2 | 7 | 4 | 69.877201161 | 162568 | ["composer_php_process"] |
| probe-memory-128 | attempt-0001 | completed | observed | blocked | blocked | 5 | 3 | 6 | 8 | 157.466483572 | 193792 | ["composer_php_process"] |
| probe-memory-1024 | attempt-0001 | completed | observed | blocked | blocked | 5 | 3 | 6 | 8 | 159.611699962 | 193968 | ["composer_php_process"] |
| probe-short-timeout | attempt-0001 | completed | observed | unknown | unknown | 5 | 1 | 0 | 0 | 298.401931872 | 31076 | ["composer_php_process","unknown"] |
| probe-network-denied | attempt-0001 | completed | observed | unknown | unknown | 5 | 1 | 0 | 0 | 29.56406102 | 49032 | ["composer_php_process"] |
| probe-child-failure | attempt-0001 | completed | observed | unknown | unknown | 5 | 1 | 0 | 0 | 27.514241582 | 31020 | ["composer_php_process"] |
| probe-cache-failure | attempt-0001 | completed | observed | unknown | unknown | 5 | 1 | 0 | 0 | 28.799949578 | 49216 | ["composer_php_process"] |
| monica7-longest-cold | attempt-0001 | completed | null | blocked | blocked | 6 | 3 | 8 | 9 | 113.107902917 | 226124 | ["composer_php_process"] |
