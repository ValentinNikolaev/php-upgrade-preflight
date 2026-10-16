#!/bin/sh
set -eu
export LC_ALL=C
kind=other
case "${1:-}" in
    --version|show|update|validate|prohibits|why-not) kind=$1 ;;
esac
probe=${R4_PROBE:-normal}
# Faults apply to actual scenarios, while metadata probes remain real and usable.
case "$kind" in --version|show) probe=normal ;; esac
# No arguments, paths, environment values, or Composer output enter instrumentation.
printf '%s\n%s\n%s\n' "$(date +%s.%N)" "$kind" "$probe" > "/output/composer/$$.start"
export R4_INVOCATION_ID=$$ R4_APPLIED_PROBE=$probe
exec \
    php -d auto_prepend_file=/harness/composer-metrics.php -d "memory_limit=${R4_COMPOSER_MEMORY_LIMIT:-1024M}" /usr/local/bin/composer "$@"
