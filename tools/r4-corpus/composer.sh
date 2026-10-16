#!/bin/sh
set -eu
kind=other
case "${1:-}" in
    --version|show|update|validate|prohibits|why-not) kind=$1 ;;
esac
probe=${R4_PROBE:-normal}
# Faults apply to actual scenarios, while metadata probes remain real and usable.
case "$kind" in --version|show) probe=normal ;; esac
# No arguments, paths, environment values, or Composer output enter instrumentation.
printf '%s\n%s\n%s\n' "$(date +%s.%N)" "$kind" "$probe" > "/output/composer/$$.start"
case "$probe" in
    timeout) exec /usr/bin/time -o "/output/composer/$$.time" -f 'elapsed_seconds=%e\nmax_rss_kib=%M\nexit_code=%x' sleep 3600 ;;
    child-failure) exec /usr/bin/time -o "/output/composer/$$.time" -f 'elapsed_seconds=%e\nmax_rss_kib=%M\nexit_code=%x' /bin/sh -c 'exit 42' ;;
esac
exec /usr/bin/time -o "/output/composer/$$.time" -f 'elapsed_seconds=%e\nmax_rss_kib=%M\nexit_code=%x' \
    php -d "memory_limit=${R4_COMPOSER_MEMORY_LIMIT:-1024M}" /usr/local/bin/composer "$@"
