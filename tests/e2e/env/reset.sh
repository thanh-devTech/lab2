#!/usr/bin/env bash
# Baseline snapshot / restore for the E2E database (H5).
#
#   ./reset.sh --snapshot   capture the CURRENT db as the baseline
#   ./reset.sh              restore the baseline
#
# ORDER IS LOAD-BEARING (D139, verified bidirectionally in D158):
#     up.sh -> seed.sh -> mint-session.sh (ALL personas) -> reset.sh --snapshot
# wp_usermeta.session_tokens is part of the reset surface, so a snapshot taken
# BEFORE minting restores empty token rows and every persona is logged out after
# the first reset. Re-snapshot and re-mint move together, always.
#
# SHELF LIFE: 14 days (D159). Auth tokens carry a hard absolute expiry (measured
# `expires=1788764820`), so a baseline older than that restores dead tokens and
# the whole suite logs out. It fails loudly at the setup project's identity
# guard, not silently. up.sh warns past 7 days.
#
# Why DROP DATABASE and not a plain import (D083's known gap, measured in H5):
# mysqldump's default --opt emits DROP TABLE IF EXISTS per table, so a plain
# import does undo D035's runtime `ALTER TABLE ... ADD COLUMN`. It does NOT undo
# a table CREATED after the snapshot -- measured: ci_pw_ghost survived a plain
# import (tables 246) and did not survive DROP DATABASE (tables 245).
# The extra cost of dropping the schema is ~0.05 s on this dataset.
set -euo pipefail
cd "$(dirname "$0")"

# R12/R18/D-refB-R12: the state directory lives OUTSIDE the checkout. The old
# `../state/` resolved to lab/tests/e2e/state/, which is inside the
# `../../../:/var/www/html` bind mount -- i.e. inside the webroot. The apache
# deny was the only thing between a live admin session cookie and an HTTP GET.
STATE_DIR="${E2E_STATE_DIR:-${TMPDIR:-/tmp}/lis-e2e-state}"
HOST_SQL="$STATE_DIR/baseline.sql"
CTR_SQL="/tmp/baseline.sql"
DBQ() { docker compose exec -T db mariadb -uroot -proot "$@"; }

if [ "${1:-}" = "--snapshot" ]; then
  mkdir -p "$STATE_DIR"
  docker compose exec -T db sh -c \
    "mariadb-dump -uroot -proot --no-tablespaces --single-transaction --add-drop-table lab > $CTR_SQL"
  docker compose cp "db:$CTR_SQL" "$HOST_SQL" >/dev/null
  echo "baseline: $(wc -c < "$HOST_SQL") bytes -> $HOST_SQL (+ db:$CTR_SQL)"
  exit 0
fi

# The in-container copy is the fast path. It does not survive `compose down -v`
# (container recreate wipes /tmp), so fall back to streaming the host copy in.
if docker compose exec -T db test -f "$CTR_SQL" 2>/dev/null; then
  docker compose exec -T db sh -c \
    "mariadb -uroot -proot -e 'DROP DATABASE lab; CREATE DATABASE lab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' \
     && mariadb -uroot -proot lab < $CTR_SQL"
else
  [ -f "$HOST_SQL" ] || { echo "no baseline; run ./reset.sh --snapshot first" >&2; exit 1; }
  docker compose cp "$HOST_SQL" "db:$CTR_SQL" >/dev/null
  DBQ -e 'DROP DATABASE lab; CREATE DATABASE lab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
  docker compose exec -T db sh -c "mariadb -uroot -proot lab < $CTR_SQL"
fi
