#!/usr/bin/env bash
# Changed-path -> suite selection (H7 / D075 / D080, artifact per R15).
#
# Prints the --grep argument for `npx playwright test`, or nothing at all when
# the whole suite must run. Consume it as:
#
#     npx playwright test $(env/select-suite.sh)
#
# Contract, from H7:
#   * diff against `git merge-base origin/main HEAD`, never `git show` (D076).
#   * first matching rule wins, per changed path; the flow tags union.
#   * a path matching NO rule selects ALL (D080) -- absence of a rule is
#     absence of knowledge, not evidence of narrow blast radius.
#   * @smoke is never subsetted (D079), so it is always in the grep.
#
# The value here is signal quality, not wall clock (~54 s saved, D121).
# `--only-changed` is deliberately unused: it is a no-op for this app and throws
# on the shallow clone GitHub Actions produces (D062).
set -uo pipefail

BASE="${E2E_DIFF_BASE:-}"
if [ -z "$BASE" ]; then
  BASE=$(git merge-base origin/main HEAD 2>/dev/null) || BASE=""
fi
[ -n "$BASE" ] || { echo ""; exit 0; }   # no base -> run everything

# pattern<TAB>tags   (tags 'ALL' short-circuits). Order is significant: the
# narrow overrides come before the submodule catch-alls, exactly as in H7.
RULES=$(cat <<'EOF'
.gitmodules	ALL
.github/*	ALL
*/composer.json	ALL
*/composer.lock	ALL
wp-config*.php	ALL
tests/e2e/*	ALL
*/plug.php	ALL
wp-content/plugins/ci-cache-and-security/*	ALL
wp-content/plugins/ci-data-works-arc/inc/init.php	ALL
wp-content/plugins/ci-data-works-arc/inc/install*.php	ALL
wp-content/plugins/ci-data-works-arc/inc/form_define.php	ALL
wp-content/plugins/ci-data-works-arc/inc/admin.php	ALL
wp-content/plugins/ci-data-works-arc/inc/admin_actions.php	ALL
wp-content/plugins/ci-data-works-arc/inc/functions.php	ALL
wp-content/plugins/ci-data-works-arc/inc/ci_base*.php	ALL
wp-content/plugins/ci-data-works-arc/admin/classes/*	ALL
wp-content/plugins/ci-data-works-arc/run/db.php	ALL
wp-content/plugins/ci-data-works-arc/js/admin*.js	ALL
wp-content/plugins/ci_patients/inc/init.php	ALL
wp-content/plugins/ci-dompdf/*	@rev @dst @tst @por
wp-content/plugins/ci-data-works-arc/css/abx*.css	@rev @dst @tst @por
wp-content/plugins/ci-data-works-arc/reports/abx*.php	@rev @dst @tst @por
wp-content/plugins/ci-data-works-arc/inc/actions/ci_save_req.php	@ord @bil
wp-content/plugins/ci-data-works-arc/inc/actions/ci_save_form_requisition.php	@ord @bil
wp-content/plugins/ci-data-works-arc/inc/actions/saved_req.php	@ord @bil
wp-content/plugins/ci-data-works-arc/inc/actions/ci_check_number_assen.php	@ord
wp-content/plugins/ci-data-works-arc/parts/*/req_form*.php	@ord @bil
wp-content/plugins/ci-data-works-arc/inc/autocomplete.php	@ord @bil
wp-content/plugins/ci-data-works-arc/pages/admin_ci_receiving*.php	@acc
wp-content/plugins/ci-data-works-arc/js/admin_receiving.js	@acc
wp-content/plugins/ci-data-works-arc/parts/rack/*	@acc
wp-content/plugins/ci-barcode-qrcode-generator/*	@acc
wp-content/plugins/ci-data-works-arc/pages/admin_ci_batches_view.php	@acc @rev
wp-content/plugins/ci-data-works-arc/inc/define_instruments.php	@tst @acc
wp-content/plugins/ci-data-works-arc/parts/instrument/ins_*.php	@tst @acc
wp-content/plugins/ci-data-works-arc/parts/instrument/api_*.php	@dst @bil
wp-content/plugins/ci-billing-indep/*	@bil
wp-content/plugins/ci-otp-authenticator/*	@por @auth
wp-content/plugins/ci_patients/*	@ord @por
EOF
)

tags=""
while IFS= read -r path; do
  [ -n "$path" ] || continue
  matched=0
  while IFS=$'\t' read -r pat rtags; do
    [ -n "$pat" ] || continue
    # shellcheck disable=SC2254  -- glob match is the point
    case "$path" in
      $pat|*/$pat) matched=1
        [ "$rtags" = "ALL" ] && { echo ""; exit 0; }
        tags="$tags $rtags"
        break ;;
    esac
  done <<< "$RULES"
  # D080: no rule means unknown blast radius means everything.
  [ "$matched" = "1" ] || { echo ""; exit 0; }
done < <(git diff --name-only "$BASE"..HEAD)

# D079: the smoke floor is never subsetted.
uniq_tags=$(printf '%s\n' $tags @smoke | sort -u | paste -sd'|' -)
[ -n "$uniq_tags" ] && printf -- '--grep=%s\n' "$uniq_tags" || echo ""
