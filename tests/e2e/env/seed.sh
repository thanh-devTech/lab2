#!/usr/bin/env bash
# Programmatic seed for the E2E environment. No DB dump, no UI clicking.
#
#   ./seed.sh              seed infrastructure + create one tox requisition
#   ./seed.sh --no-req     seed infrastructure only
#
# Three tiers, per decision D085:
#   reference data  -> already in wp-content/default.sql (nothing to do here)
#   infrastructure  -> WP-CLI / PHP           (steps 1-4)
#   domain rows     -> HTTP POST through the app's own saver (step 6), because
#                     tr_get_new_assen() + two lab-rewrite passes + a -NN
#                     collision loop mint the accession; raw SQL cannot.
set -euo pipefail
cd "$(dirname "$0")"

ADMIN_USER="${E2E_ADMIN_USER:-Administrat0r}"
ADMIN_PASS="${E2E_ADMIN_PASS:-e2e-admin-pw}"
BASE_URL="${E2E_BASE_URL:-http://localhost}"
DO_REQ=1
[ "${1:-}" = "--no-req" ] && DO_REQ=0

wp()  { docker compose exec -T wordpress wp --allow-root --skip-plugins --skip-themes "$@"; }
wpp() { docker compose exec -T wordpress wp --allow-root --skip-themes "$@"; }   # plugins loaded
q()   { wp db query "$1" --skip-column-names; }
sql() { docker compose exec -T db mariadb -uroot -proot lab < "$1"; }

ts()  { python3 -c 'import time;print(time.time())'; }
lap() { python3 -c "print('  [%.2fs] $1' % ($(ts) - $T0))"; }
T0=$(ts)

# --- 0. boundary-stub intercept log ---------------------------------------
# Two processes write this file: the mu-plugin appends to it from inside a web
# request (www-data), and fixtures/stub-http.ts truncates it through
# `docker compose exec` (root). Both opens carry O_CREAT and /tmp is a sticky,
# world-writable tmpfs, so `fs.protected_regular` decides the outcome:
# may_create_in_sticky() in fs/namei.c returns **-EACCES unless the file is
# owned by the caller or by the DIRECTORY's owner**. The mode is never
# consulted, and root does not get a pass -- the check runs before any
# capability does.
#
# That is the CI-only `cannot create /tmp/e2e-http-intercepts.log: Permission
# denied` at fixtures/container.ts:20 (measured, run 32768389955: uid=0,
# /tmp drwxrwxrwt root, file -rw-rw-rw- www-data, still EACCES). It passes on a
# Docker Desktop VM only because LinuxKit leaves fs.protected_regular at 0.
#
# /tmp is root-owned, so a ROOT-owned file satisfies the rule for BOTH writers:
# root is the caller, and root is also the directory owner. 0666 is then what
# lets www-data write it. World-writable is fine here -- a synthetic log of
# attempted outbound calls, in a disposable, egress-denied, PHI-free container,
# on a tmpfs that dies with the container.
echo "=== 0. http intercept log ==="
docker compose exec -T wordpress sh -c '
  touch  /tmp/e2e-http-intercepts.log
  chown  root:root /tmp/e2e-http-intercepts.log
  chmod  666 /tmp/e2e-http-intercepts.log'
lap "/tmp/e2e-http-intercepts.log root:root 0666"

# --- 1. schema: run the app's own installer -------------------------------
# The 242-table default.sql snapshot predates the current plugin schema, e.g.
# ci_batches has no `is_saved` column while ci_save_form_requisition.php:35-39
# writes one unconditionally -- every tox requisition save fails without this.
# dbDelta only maintains the batch tables for btypes in CI_ALL_BATCH_TYPES,
# so wp-config.php must list every btype the suite exercises.
echo "=== 1. app installer (schema sync) ==="
wpp --url="$BASE_URL" eval \
  'global $wpdb,$blog_id; ob_start(); include(TRCPDD_PATH."inc/install.php"); ob_end_clean();' \
  >/dev/null 2>&1
lap "ci-data-works-arc installer"

# --- 1b. schema manifest gate (R17) ---------------------------------------
# The installer wraps dbDelta in ob_start/ob_end_clean, so it swallows its own
# errors. D100 was one instance of that class (`ci_batches.is_saved` missing,
# saves failing silently at HTTP 200). This gate closes the class: every column
# a smoke assertion reads must exist after the installer, or the run stops here
# instead of going green on zero rows.
echo "=== 1b. schema manifest ==="
MANIFEST="ci_batches:assen ci_batches:batch_type ci_batches:batch_status
          ci_batches:is_saved ci_batches:lab ci_batches:lab_id
          ci_batches:panel_tests_requested ci_batches:patient_name
          ci_batches_pcr:batch_status ci_batches_pcr:assen ci_batches_pcr:lab_id
          ci_labs:lab_dba_init ci_labs:lab_status"
miss=0
for entry in $MANIFEST; do
  tbl=${entry%%:*}; col=${entry##*:}
  n=$(q "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl' AND COLUMN_NAME='$col'" | tr -d '\r')
  [ "$n" = "1" ] || { echo "  MISSING $tbl.$col"; miss=$((miss+1)); }
done
[ "$miss" = "0" ] || { echo "FAIL: $miss manifest column(s) missing after the installer"; exit 1; }
lap "manifest: $(printf '%s\n' $MANIFEST | wc -l | tr -d ' ') columns present"

# --- 2. PII strip (D153) --------------------------------------------------
# Runs HERE, not from docker-entrypoint-initdb.d: that directory fires only on
# an empty datadir, so a warm `./up.sh` skipped the strip entirely. It must also
# run BEFORE seed.php, which recreates the synthetic clinic/provider/collector/
# patient/rep rows this truncates.
echo "=== 2. strip person-shaped rows from default.sql ==="
sql sql/30-strip-pii.sql
lap "sql/30-strip-pii.sql"

# --- 3. tenancy pool (D172) -----------------------------------------------
echo "=== 3. lab pool ==="
sql sql/25-lab-pool.sql
lap "lab pool: $(q "SELECT COUNT(*) FROM ci_labs WHERE lab_dba_init REGEXP '^LA[A-P]$'") labs"

# --- 3b. pcr reference data (D238) ----------------------------------------
# ci_test_groups / ci_test_panels have many NOT NULL no-default columns, so the
# rows are cloned from live ones. Lives in the seed, not in a spec body.
echo "=== 3b. pcr reference data ==="
sql sql/26-pcr-reference.sql
STA_TG=$(q "SELECT tg_ID FROM ci_test_groups WHERE tg_name='E2E STA PCR Panel'")
STA_TE=$(q "SELECT te_ID FROM ci_test_panels WHERE te_name='E2E STA Covid Analyte'")
[ -n "$STA_TG" ] && [ -n "$STA_TE" ] || { echo "FAIL: pcr reference rows missing"; exit 1; }
lap "pcr reference: tg=$STA_TG te=$STA_TE"

# --- 4. infrastructure rows + users ---------------------------------------
echo "=== 4. infrastructure (labs, clinic, provider, collector, rep, panel, patient, users) ==="
SEED_OUT=$(wp --url="$BASE_URL" eval-file /var/www/html/tests/e2e/env/seed.php)
eval "$SEED_OUT"
lap "seed.php"

# --- 5. ci_patients activation so ci_user_tokens exists (D097) ------------
# The table is created by the activation hook only. A plugin that is already
# flagged active never re-fires it, so cycle it.
echo "=== 5. ci_patients activation (creates ci_user_tokens) ==="
if [ -z "$(q "SHOW TABLES LIKE 'ci_user_tokens'")" ]; then
  wpp --url="$BASE_URL/patients/" plugin deactivate ci_patients >/dev/null 2>&1 || true
  wpp --url="$BASE_URL/patients/" plugin activate   ci_patients >/dev/null 2>&1
fi
[ -n "$(q "SHOW TABLES LIKE 'ci_user_tokens'")" ] || { echo "FAIL: ci_user_tokens missing"; exit 1; }
lap "ci_user_tokens present"

if [ "$DO_REQ" = "0" ]; then echo "done (infrastructure only)"; exit 0; fi

# --- 6. domain row: one tox requisition, through the app's own saver ------
echo "=== 6. S-ORD-01 requisition via HTTP POST ==="
JAR=$(mktemp); LOGIN_BODY=$(mktemp); trap 'rm -f "$JAR" "$LOGIN_BODY"' EXIT
# W8: the password goes through a file, not argv -- `curl -d pwd=...` is visible
# in `ps` to every user on the box for the life of the request. `-d @file` reads
# an already-url-encoded body, so the encoding happens here.
E2E_U="$ADMIN_USER" E2E_P="$ADMIN_PASS" E2E_B="$BASE_URL" python3 -c '
import os, urllib.parse, sys
sys.stdout.write(urllib.parse.urlencode({
    "log": os.environ["E2E_U"], "pwd": os.environ["E2E_P"],
    "wp-submit": "Log In", "redirect_to": os.environ["E2E_B"] + "/wp-admin/",
    "testcookie": "1"}))' > "$LOGIN_BODY"
code=$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" \
  -d "@$LOGIN_BODY" \
  -H 'Cookie: wordpress_test_cookie=WP+Cookie+check' \
  "$BASE_URL/wp-login.php")
[ "$code" = "302" ] || { echo "FAIL: login returned $code"; exit 1; }

BEFORE=$(q "SELECT COUNT(*) FROM ci_batches")
# No nonce is scraped: inc/actions/ci_save_req.php and the tox saver carry no
# nonce and no referer check (D043), so an authenticated POST is enough.
post=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" \
  "$BASE_URL/wp-admin/admin.php?page=tox_req_form" \
  -d 'tr_action=ci_save_form_requisition' -d 'auto_generated=1' \
  -d "bat[cli_ID]=$E2E_CLINIC_ID" -d "bat[doc_ID]=$E2E_DOC_ID" \
  -d "bat[col_ID]=$E2E_COLLECTOR_ID" -d "bat[lab_id]=$E2E_LAB_ID" \
  -d 'bat[ref_lab_ID]=0' -d 'bat[batch_short]=0' -d 'bat[batch_status]=' \
  -d 'bat[doc]=auto' -d 'bat[type_of_specimen]=urine' \
  -d "bat[panel_tests_requested]=group_${E2E_TESTGROUP_ID}" \
  --data-urlencode 'pa[pa_first]=Testpatient' --data-urlencode 'pa[pa_last]=Alpha' \
  -d 'pa[pa_dob]=1900-01-01' -d 'pa[pa_gender]=F')
AFTER=$(q "SELECT COUNT(*) FROM ci_batches")
lap "requisition POST (HTTP $post, ci_batches $BEFORE -> $AFTER)"

[ "$AFTER" -gt "$BEFORE" ] || { echo "FAIL: no ci_batches row was created"; exit 1; }

wp db query "SELECT mid,assen,batch_type,CONCAT('[',batch_status,']') AS batch_status,
             lab,lab_id,cli_ID,doc_ID,col_ID,patient_id,patient_name,author_ID
             FROM ci_batches ORDER BY mid DESC LIMIT 1\G"

ASSEN=$(q "SELECT assen FROM ci_batches ORDER BY mid DESC LIMIT 1")
[ -n "$ASSEN" ] || { echo "FAIL: assen is empty"; exit 1; }
echo "PASS: requisition $ASSEN created programmatically"
python3 -c "print('total seed: %.2fs' % ($(ts) - $T0))"
