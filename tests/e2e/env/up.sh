#!/usr/bin/env bash
# One command boots the whole E2E environment. No manual steps.
#   ./up.sh          boot (reuses an existing DB volume if present)
#   ./up.sh --fresh  destroy state first, then boot clean
set -euo pipefail
cd "$(dirname "$0")"

ADMIN_USER="${E2E_ADMIN_USER:-Administrat0r}"
ADMIN_PASS="${E2E_ADMIN_PASS:-e2e-admin-pw}"
BASE_URL="${E2E_BASE_URL:-http://localhost}"

# A buildx builder pinned to a foreign architecture (or a container-driver
# builder that has to pull buildkit first) turns a 3-minute build into a stall.
# Prefer the local docker-driver builder when the active one is not that.
if [ -z "${BUILDX_BUILDER:-}" ] \
   && ! docker buildx inspect 2>/dev/null | grep -q '^Driver: *docker$' \
   && docker buildx inspect desktop-linux >/dev/null 2>&1; then
  export BUILDX_BUILDER=desktop-linux
  echo "note: active buildx builder is not the local docker driver; using desktop-linux"
fi

ts() { date +%s; }
say() { printf '\n=== %s (t+%ss) ===\n' "$1" "$(( $(ts) - T0 ))"; }
T0=$(ts)

if [ "${1:-}" = "--fresh" ]; then
  say "tearing down previous state"
  docker compose down -v --remove-orphans
fi

# WordPress core is not committed to this repo (DEPENDENCIES.md). The script
# scripts/install-wp-core.sh installs the pinned version into the repo root.
# The webroot is a bind mount of that root, so it hides the core inside the
# image. Core must therefore exist on the host before the container starts.
# The `lis` network is `internal: true`, so the container cannot download core.
say "WordPress core"
ROOT=$(cd ../../.. && pwd)
WP_VERSION=$(sed -n 's/^WP_VERSION="\(.*\)"$/\1/p' "$ROOT/scripts/install-wp-core.sh")
[ -n "$WP_VERSION" ] || { echo "cannot read WP_VERSION from scripts/install-wp-core.sh" >&2; exit 1; }
if grep -q "wp_version = '$WP_VERSION'" "$ROOT/wp-includes/version.php" 2>/dev/null; then
  echo "core $WP_VERSION is already installed"
else
  "$ROOT/scripts/install-wp-core.sh"
  # Verify the fresh download against the official checksums (DEPENDENCIES.md).
  if command -v wp >/dev/null 2>&1; then
    wp core verify-checksums --version="$WP_VERSION" --locale=en_US --path="$ROOT"
  else
    echo "note: wp-cli is absent on the host; core checksums are not verified"
  fi
fi

say "build"
docker compose build

say "up"
docker compose up -d --wait

say "egress assertions (H9)"
# Only the network half of the gate runs here: the PHI row-count half needs the
# database already stripped and seeded, so the FULL safety-gate.sh runs after
# seed.sh (see the Makefile and .github/workflows/e2e.yml).
./egress-check.sh

# D159: a baseline older than 14 days restores auth tokens that have hit their
# hard absolute expiry, and every persona logs out. Warn at 7.
STATE_DIR="${E2E_STATE_DIR:-${TMPDIR:-/tmp}/lis-e2e-state}"
if [ -f "$STATE_DIR/baseline.sql" ] \
   && [ -n "$(find "$STATE_DIR/baseline.sql" -mtime +7 2>/dev/null)" ]; then
  echo "WARNING: $STATE_DIR/baseline.sql is more than 7 days old."
  echo "         Auth tokens expire hard at 14 days (D159). Re-run seed.sh," \
       "mint-session.sh for every persona, then reset.sh --snapshot."
fi

say "seed admin credentials"
docker compose exec -T wordpress wp --allow-root --skip-plugins --skip-themes \
  --url="$BASE_URL" user update "$ADMIN_USER" --user_pass="$ADMIN_PASS" --quiet
docker compose exec -T wordpress wp --allow-root --skip-plugins --skip-themes \
  --url="$BASE_URL" user set-role "$ADMIN_USER" administrator --quiet
docker compose exec -T wordpress wp --allow-root --skip-plugins --skip-themes \
  --url="$BASE_URL" super-admin add "$ADMIN_USER" --quiet || true

say "verify"
docker compose exec -T wordpress wp --allow-root --skip-plugins --skip-themes core is-installed --network
echo "core is-installed --network: OK"
docker compose exec -T wordpress wp --allow-root --skip-plugins --skip-themes --url="$BASE_URL" site list
docker compose exec -T wordpress wp --allow-root --skip-plugins --skip-themes --url="$BASE_URL" user list --fields=ID,user_login,roles

say "logged-in admin dashboard"
./login-check.sh

say "READY  ${BASE_URL}/wp-admin/  (${ADMIN_USER} / ${ADMIN_PASS})"
printf 'total boot: %ss\n' "$(( $(ts) - T0 ))"
