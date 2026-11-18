#!/usr/bin/env bash
# THE SAFETY GATE. Design §7.2 / D148 / D149 / D173.
#
# This is the control of record for "no PHI leaves, no egress leaks". It is a
# plain shell script on purpose, so CI can run it as its OWN blocking step
# BEFORE the (advisory) test step. Routing it through Playwright could not do
# that: the test step carries `continue-on-error: true`, which would green a
# leak (W4). Locally the same script is invoked from global-setup.ts, which
# `--no-deps` cannot skip (B-R1/D148) the way it skips the `setup` project.
#
#   ./safety-gate.sh          run every check, exit non-zero on any failure
#
# Checks, all of them mechanisms rather than assertions about mechanisms:
#   1. egress denial + internal allowlist        (egress-check.sh, H9)
#   2. TCP/22, TCP/25, UDP/53 to the public net  (W10)
#   3. `edge` network structure                  (proxy only; G6/I13)
#   4. PHI row counts, derived from information_schema, not hand-listed (W5)
#   5. Apache data-directory denies              (D147; extension allowlists leak)
set -uo pipefail
cd "$(dirname "$0")"

BASE_URL="${E2E_BASE_URL:-http://localhost}"
fails=0
# `< /dev/null` is load-bearing: `docker compose exec -T` consumes stdin, so a Q
# called from inside a `while read` loop eats the loop's own input. Measured: a
# 50-row scan silently stopped after 4.
Q() { docker compose exec -T db mariadb -uroot -proot lab -N -B -e "$1" < /dev/null; }
bad() { echo "  FAIL  $*"; fails=$((fails + 1)); }
ok()  { echo "  ok    $*"; }

echo "== 1. egress denial =="
if ./egress-check.sh | sed 's/^/  /'; then ok "egress-check.sh"; else bad "egress-check.sh"; fi

echo "== 2. TCP/22, TCP/25, UDP/53 (W10) =="
for probe in "22 ssh" "25 smtp"; do
  set -- $probe
  if docker compose exec -T wordpress timeout 5 bash -c "cat < /dev/null > /dev/tcp/1.1.1.1/$1" 2>/dev/null; then
    bad "TCP/$1 ($2) to 1.1.1.1 CONNECTED"
  else
    ok "TCP/$1 ($2) refused"
  fi
done
if docker compose exec -T wordpress timeout 5 bash -c \
     'exec 3<>/dev/udp/8.8.8.8/53 && printf "\x00" >&3 && timeout 3 head -c1 <&3' 2>/dev/null | read -r _; then
  bad "UDP/53 to 8.8.8.8 answered"
else
  ok "UDP/53 no answer"
fi

echo "== 3. edge network carries only the proxy (G6/I13) =="
edge=$(docker network inspect lis-e2e_edge \
        --format '{{range .Containers}}{{.Name}} {{end}}' 2>/dev/null | tr -s ' ')
case "$edge" in
  "lis-e2e-proxy-1 ") ok "edge members: $edge" ;;
  "")                 bad "edge network not found" ;;
  *)                  bad "edge network has extra members: $edge" ;;
esac

echo "== 4. PHI row counts (derived from information_schema, W5) =="
# Person-shaped by COLUMN NAME, so a table added by schema drift is covered
# automatically. A hand-written list is exactly what rotted in D100.
PERSON_RE='(^|_)(ssn|dob|birth|first|last|fname|lname|patient|phone|email|address|mrn|insurance)'
# Tables sql/30-strip-pii.sql empties. Non-zero here means the strip did not run
# (the D153 warm-volume failure) or a real dump was loaded.
MUST_BE_ZERO='ci_phlebotomist_logs wp_signups wp_registration_log wp_comments
              wp_commentmeta wp_wp_eStore_tbl wp_wp_eStore_sales_tbl wp_wp_eStore_customer_tbl'
# Tables the seed deliberately fills with obviously-fake rows, plus reference
# data that is person-shaped by column name only (payers are businesses).
# ROT WARNING, and the division of labour that limits it: MUST_BE_ZERO above is
# the actual production-dump detector -- those tables are populated in a real
# dump and empty in a clean seed, so they stay a HARD failure and are never
# widened. KNOWN_SYNTHETIC below is only "tables this suite legitimately writes".
# It WILL need extending as scenarios are added, and that is the whack-a-mole
# D147 warned about. Ceiling named: the durable fix is to fail on rows that are
# not attributable to the suite (run-key / e2e_* author / tenant lab) rather
# than on a table being non-empty. Do that if this list needs a third extension.
KNOWN_SYNTHETIC='ci_labs ci_clinic ci_doc ci_collector ci_patients ci_lab_reps
                 ci_batches ci_batches_pcr ci_batches_blood ci_batches_req
                 ci_insurances ci_messages ci_2_messages ci_reports ci_reports_blood
                 ci_reports_pcr ci_payer_list ci_test_groups ci_test_panels
                 ci_consumable_inv ci_receiving_batches ci_log_users
                 wp_users wp_usermeta wp_blogs wp_options wp_2_options wp_postmeta
                 ci_batch_billings ci_user_tokens ci_sync_api_orders
                 ci_batches_study ci_reports_study ci_saved_batches
                 ci_insurances_history ci_patients_history ci_pcr_qc ci_priors
                 ci_covid_questions tr_log_sms wp_2_comments wp_tr_file_logs
                 wp_tr_lock_ip
                 ci_clinic_manager ci_clinic_support ci_clinician_groups
                 ci_custom_providers ci_marketing_groups ci_saved_batches'

for t in $MUST_BE_ZERO; do
  n=$(Q "SELECT COUNT(*) FROM \`$t\`" 2>/dev/null | tr -d '\r')
  [ "${n:-x}" = "0" ] && ok "$t = 0 rows" || bad "$t has ${n:-?} rows (PII strip did not run?)"
done

# Anything person-shaped, non-empty and unknown to this file is a hard failure.
# One query returns table+count, because each `docker compose exec` is ~131 ms
# and there are ~40 candidates (D164). No `mapfile`: macOS ships bash 3.2.
candidates=$(Q "
  SELECT t.TABLE_NAME FROM information_schema.TABLES t
   WHERE t.TABLE_SCHEMA='lab' AND t.TABLE_TYPE='BASE TABLE'
     AND EXISTS (SELECT 1 FROM information_schema.COLUMNS c
                  WHERE c.TABLE_SCHEMA='lab' AND c.TABLE_NAME=t.TABLE_NAME
                    AND c.COLUMN_NAME REGEXP '$PERSON_RE')" 2>/dev/null | tr -d '\r')
# Re-join through word splitting: the lists above are written across several
# lines, so a raw `case " $LIST "` match fails on any entry followed by \n.
KNOWN=" $(echo $MUST_BE_ZERO $KNOWN_SYNTHETIC) "
seen=0; unknown=0
while IFS=$'\t' read -r t _; do
  [ -n "$t" ] || continue
  seen=$((seen + 1))
  case "$KNOWN" in *" $t "*) continue ;; esac
  n=$(Q "SELECT COUNT(*) FROM \`$t\`" 2>/dev/null | tr -d '\r')
  if [ "${n:-0}" != "0" ]; then
    bad "unclassified person-shaped table \`$t\` has $n rows -- classify it in safety-gate.sh"
    unknown=$((unknown + 1))
  fi
done <<EOF
$candidates
EOF
ok "$seen person-shaped tables scanned, $unknown unclassified non-empty"

echo "== 5. apache data-directory denies (D147) =="
deny_path() {
  c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL$1")
  [ "$c" = "403" ] || [ "$c" = "404" ] && ok "$1 -> $c" || bad "$1 -> $c (expected 403)"
}
deny_path /wp-content/log/logapi.txt
deny_path /wp-content/cache/
deny_path /.git/config
deny_path /tests/e2e/env/wp-config.php
deny_path /claudedocs/
c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/wp-login.php")
[ "$c" = "200" ] && ok "/wp-login.php -> 200 (app still served)" || bad "/wp-login.php -> $c"

if [ "$fails" -ne 0 ]; then
  echo "SAFETY GATE FAILED ($fails problem(s))"
  exit 1
fi
echo "SAFETY GATE PASSED"
