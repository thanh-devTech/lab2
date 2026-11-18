#!/usr/bin/env bash
# H9: prove the app container has no route to the public internet, and that the
# internal allowlist (db, mailhog) still works. Exits non-zero if egress leaks.
#
# Tests both by-name and by-IP, so a pass cannot be an artefact of DNS alone.
set -uo pipefail
cd "$(dirname "$0")"

X() { docker compose exec -T wordpress "$@"; }
fails=0

deny() { # deny <label> <cmd...>
  local label="$1"; shift
  local out rc start end
  start=$(date +%s)
  out=$("$@" 2>&1); rc=$?
  end=$(date +%s)
  if [ "$rc" -eq 0 ]; then
    echo "  LEAK  $label -> succeeded (rc=0) $(echo "$out" | head -1)"
    fails=$((fails+1))
  else
    echo "  DENY  $label -> rc=$rc in $((end-start))s :: $(echo "$out" | tail -1 | cut -c1-90)"
  fi
}

allow() { # allow <label> <cmd...>
  local label="$1"; shift
  local out rc
  out=$("$@" 2>&1); rc=$?
  if [ "$rc" -eq 0 ]; then
    echo "  ALLOW $label -> rc=0"
  else
    echo "  BROKE $label -> rc=$rc :: $(echo "$out" | tail -1 | cut -c1-90)"
    fails=$((fails+1))
  fi
}

echo "-- must be DENIED (public internet) --"
deny "curl https://example.com (by name)"  X curl -sS -m 8 -o /dev/null https://example.com
deny "curl https://1.1.1.1 (by IP, no DNS)" X curl -sS -m 8 -o /dev/null https://1.1.1.1
deny "curl http://93.184.215.14 (by IP)"    X curl -sS -m 8 -o /dev/null http://93.184.215.14
deny "curl hub imt.ignitelabconnect.com"    X curl -sS -m 8 -o /dev/null https://imt.ignitelabconnect.com/
deny "curl lis-demo.com (file-scope call)"  X curl -sS -m 8 -o /dev/null https://lis-demo.com/lab_api/get_ip
deny "curl api.wordpress.org"               X curl -sS -m 8 -o /dev/null https://api.wordpress.org/
deny "raw TCP 8.8.8.8:53"                   X timeout 8 bash -c 'cat < /dev/null > /dev/tcp/8.8.8.8/53'
deny "PHP file_get_contents(lis-demo.com)"  X php -r '$c=@file_get_contents("https://lis-demo.com/lab_api/get_ip"); exit($c===false?1:0);'

echo "-- must be ALLOWED (internal only) --"
allow "curl http://mailhog:8025/"  X curl -fsS -m 8 -o /dev/null http://mailhog:8025/
allow "TCP db:3306"                X timeout 8 bash -c 'cat < /dev/null > /dev/tcp/db/3306'
allow "mysql SELECT 1"             X mysql -hdb -ulab -plab -N -e 'SELECT 1' lab

if [ "$fails" -ne 0 ]; then
  echo "EGRESS CHECK FAILED ($fails problem(s))"; exit 1
fi
echo "EGRESS CHECK PASSED: app container is egress-denied, internal allowlist intact"
