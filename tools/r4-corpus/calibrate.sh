#!/bin/sh
set -eu
mkdir -p /output/composer /scratch/success /scratch/failure
cp /harness/composer.sh /scratch/composer-r4
chmod 700 /scratch/composer-r4
export COMPOSER_HOME=/composer-home COMPOSER_CACHE_DIR=/composer-cache
export COMPOSER_DISABLE_NETWORK=1 R4_COMPOSER_MEMORY_LIMIT=128M R4_PROBE=normal
unset COMPOSER COMPOSER_AUTH
printf '%s\n' '{"name":"r4/success","repositories":[{"packagist.org":false}],"require":{"php":">=8.0"}}' > /scratch/success/composer.json
printf '%s\n' '{"name":"r4/failure","repositories":[{"packagist.org":false}],"require":{"php":"^99.0"}}' > /scratch/failure/composer.json
cd /scratch/success
/scratch/composer-r4 update --no-plugins --no-scripts --no-install --no-interaction >/dev/null 2>/dev/null
test -f composer.lock
cd /scratch/failure
set +e
/scratch/composer-r4 update --no-plugins --no-scripts --no-install --no-interaction >/dev/null 2>/dev/null
failed=$?
R4_PROBE=timeout timeout --signal=TERM --kill-after=2 1 /scratch/composer-r4 update >/dev/null 2>/dev/null
signal=$?
set -e
test "$failed" -ne 0
test "$signal" -eq 124
printf '{"real_composer_success":true,"real_composer_failure_exit":%s,"signal_timeout_exit":%s}\n' "$failed" "$signal" > /output/calibration.json
