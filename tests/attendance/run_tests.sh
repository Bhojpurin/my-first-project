#!/usr/bin/env bash
# Starts a throw-away MariaDB (needs mariadb-server installed), runs the attendance tests, stops it.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
W="$(mktemp -d /tmp/attdb.XXXX)"
mariadb-install-db --user="$(id -un)" --datadir="$W/data" >/dev/null
mariadbd --user="$(id -un)" --datadir="$W/data" --socket="$W/s" --pid-file="$W/pid" --skip-networking --skip-grant-tables \
  --log-error="$W/err.log" & 
trap 'kill $(cat "$W/pid" 2>/dev/null) 2>/dev/null || true; rm -rf "$W"' EXIT
for _ in $(seq 1 60); do mariadb --socket="$W/s" -e 'select 1' >/dev/null 2>&1 && break; sleep 0.5; done
ATT_TEST_SOCK="$W/s" php "$HERE/test.php"
