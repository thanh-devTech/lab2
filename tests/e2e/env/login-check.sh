#!/usr/bin/env bash
# Proves a real logged-in admin session: POST wp-login.php, keep the cookie jar,
# then fetch admin pages and assert admin-only markup AND the identity of the
# logged-in user. A login redirect or an anonymous hit fails every assertion.
set -euo pipefail
cd "$(dirname "$0")"

ADMIN_USER="${E2E_ADMIN_USER:-Administrat0r}"
ADMIN_PASS="${E2E_ADMIN_PASS:-e2e-admin-pw}"
BASE_URL="${E2E_BASE_URL:-http://localhost}"

JAR=$(mktemp)
trap 'rm -f "$JAR"' EXIT

code=$(curl -s -o /tmp/e2e-login.html -w '%{http_code}' -c "$JAR" -b "$JAR" \
  --data-urlencode "log=$ADMIN_USER" \
  --data-urlencode "pwd=$ADMIN_PASS" \
  --data-urlencode "wp-submit=Log In" \
  --data-urlencode "redirect_to=$BASE_URL/wp-admin/" \
  --data-urlencode "testcookie=1" \
  -H 'Cookie: wordpress_test_cookie=WP+Cookie+check' \
  "$BASE_URL/wp-login.php")
echo "POST /wp-login.php -> HTTP $code (302 = credentials accepted)"
[ "$code" = "302" ] || { echo "FAIL: expected 302"; sed -n '1,25p' /tmp/e2e-login.html; exit 1; }

grep -q 'wordpress_logged_in' "$JAR" \
  || { echo "FAIL: no wordpress_logged_in cookie was set"; exit 1; }
echo "cookie jar has wordpress_logged_in_<hash>: OK"

fail=0
check() { # check <path> <expect_final_code>
  local path="$1" expect="$2"
  local out res code url
  out="/tmp/e2e-$(printf '%s' "$path" | tr -c 'a-zA-Z0-9' '_').html"
  res=$(curl -sL -o "$out" -w '%{http_code} %{url_effective}' -b "$JAR" "$BASE_URL$path")
  code=${res%% *}; url=${res#* }
  echo "GET $path -> HTTP $code (final: $url, $(wc -c < "$out" | tr -d ' ') bytes)"
  [ "$code" = "$expect" ] || { echo "  FAIL: expected $expect"; fail=1; return; }
  for needle in 'id="wpadminbar"' 'id="adminmenumain"' 'id="adminmenuwrap"' 'id="wpbody-content"'; do
    grep -qF "$needle" "$out" && echo "  found  $needle" || { echo "  MISSING $needle"; fail=1; }
  done
  grep -qF 'id="loginform"' "$out" && { echo "  FAIL: this is the login form"; fail=1; } \
                                   || echo "  absent id=\"loginform\" (not a login page)"
  grep -qF "$ADMIN_USER" "$out"    && echo "  identity confirmed: $ADMIN_USER appears in the page" \
                                   || { echo "  FAIL: logged-in user not named in page"; fail=1; }
}

check /wp-admin/ 200            # app redirects this to admin.php?page=welcome
check /wp-admin/profile.php 200 # plain core admin screen, no app redirect

# Negative control: the same URLs must NOT look like this without the cookie.
anon=$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/wp-admin/profile.php")
echo "anonymous GET /wp-admin/profile.php -> HTTP $anon (expect 302)"
[ "$anon" = "302" ] || { echo "  FAIL: admin is reachable without auth"; fail=1; }

[ "$fail" = "0" ] || { echo "LOGIN CHECK FAILED"; exit 1; }
echo "PASS: logged-in admin dashboard reached at $BASE_URL/wp-admin/ as $ADMIN_USER"
