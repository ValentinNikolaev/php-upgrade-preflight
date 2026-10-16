#!/bin/sh
set -u
mkdir -p /output/composer
# Copy only our wrapper into writable scratch: source checkout need not have executable bits.
cp /harness/composer.sh /scratch/composer-r4
chmod 700 /scratch/composer-r4
export TMPDIR=/scratch/work
mkdir -p "$TMPDIR"
export COMPOSER_HOME=/composer-home
export COMPOSER_CACHE_DIR=/composer-cache
export COMPOSER_MEMORY_LIMIT="$R4_COMPOSER_MEMORY_LIMIT"
export COMPOSER_NO_INTERACTION=1
unset COMPOSER COMPOSER_AUTH
if [ "$R4_PROBE" = cache-failure ]; then
    export COMPOSER_CACHE_DIR=/target/.r4-unwritable-cache
fi
/usr/bin/time -o /output/analyzer.time -f 'elapsed_seconds=%e\nuser_seconds=%U\nsystem_seconds=%S\nmax_rss_kib=%M\nexit_code=%x' \
    php -d "memory_limit=$R4_MEMORY_LIMIT" /harness/collect.php >/dev/null 2>/scratch/analyzer.stderr
result=$?
php -d memory_limit=128M /harness/finalize.php "$result" >/dev/null 2>/dev/null
exit "$result"
