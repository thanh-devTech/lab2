#!/usr/bin/env bash
# Mint a Playwright storageState JSON for one user, with no login UI and no OTP.
#
#   ./mint-session.sh e2e_sec                       -> stdout
#   ./mint-session.sh e2e_sec ../state/lab_sec.json -> file
#
# Runs under --skip-plugins --skip-themes so none of the app's login filters
# (captcha, disabled-role logout, hub SSO) participate: the artefact is exactly
# the pair of core auth cookies, which is all any of the login paths produce.
set -euo pipefail
cd "$(dirname "$0")"

LOGIN="${1:?usage: mint-session.sh <user_login> [outfile]}"
OUT="${2:-}"
BASE_URL="${E2E_BASE_URL:-http://localhost}"

# H4: pin one PHPSESSID across every write-capable persona, or parallel workers
# race tr_get_new_assen() and lose rows silently. See 15-hypotheses/H4.md.
PIN="${E2E_PIN_PHPSESSID:-e2eshared0000000000000000000000ab}"

JSON=$(docker compose exec -T -e "E2E_PIN_PHPSESSID=$PIN" wordpress \
        wp --allow-root --skip-plugins --skip-themes \
        --url="$BASE_URL" eval-file /var/www/html/tests/e2e/env/mint-session.php "$LOGIN")

if [ -n "$OUT" ]; then
  mkdir -p "$(dirname "$OUT")"
  printf '%s\n' "$JSON" > "$OUT"
  echo "wrote $OUT"
else
  printf '%s\n' "$JSON"
fi
