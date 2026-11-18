#!/usr/bin/env bash
# THE STATIC GATE -- everything that can red a build without booting the stack.
#
# WHY IT EXISTS AS A SCRIPT. Two of the three checks below already ran in CI
# (.github/workflows/e2e.yml) and NOWHERE ELSE. GitHub Actions is down
# account-wide, so a CI-only gate currently protects nothing -- `make e2e` could
# ship a suite that does not type-check. This is the one place both paths call.
#
# DELIBERATELY NOT FOLDED INTO safety-gate.sh: that script is the PHI/egress
# control of record (D148/D149/D173), and a selector-lint failure reporting as
# a compliance failure is a lie in an audit trail.
#
#   ./lint.sh          run every check, exit non-zero on any failure
#
# Checks:
#   1. copy-family counts vs meta/copies.txt   (regenerated and diffed, not read)
#   2. no literal getByTestId                  (D383 -- testids do not exist at the pin)
#   3. npx tsc --noEmit                        (D394 -- playwright never type-checks)
set -uo pipefail
cd "$(dirname "$0")/.."

PLUGINS="${E2E_PLUGIN_ROOT:-../../wp-content/plugins}"
fails=0
red() { printf 'FAIL: %s\n' "$*" >&2; fails=$((fails + 1)); }

# ---------------------------------------------------------------- 1. copies
# REGENERATED, NOT ASSERTED. The pattern is read out of copies.txt and re-run
# here, so the file cannot drift from the thing it describes; a hand-maintained
# table would go stale and give false confidence, which is the whole reason the
# dual-impl register was killed (52-PASS-3-PLAN Part 2).
if [[ ! -d $PLUGINS ]]; then
  red "plugin root $PLUGINS not found -- copy-family counts cannot be checked. " \
      "Run from tests/e2e with the submodules checked out, or set E2E_PLUGIN_ROOT."
else
  seen=0
  while IFS=$'\t' read -r want name pattern; do
    [[ -z ${want:-} || $want == \#* ]] && continue
    seen=$((seen + 1))
    got=$(grep -rEoh --include='*.php' -- "$pattern" "$PLUGINS" 2>/dev/null | wc -l | tr -d ' ')
    if [[ $got != "$want" ]]; then
      red "copy family '$name': meta/copies.txt says $want, source has $got"
      printf '       pattern: %s\n' "$pattern" >&2
      printf '       A count that MOVED means a copy was added or removed at the submodule pin.\n' >&2
      printf '       Fix the new copy'"'"'s siblings, or edit the count and say which bump added it.\n' >&2
    fi
  done < meta/copies.txt
  # A typo'd path or a mangled file yields zero rows and a silent pass -- the
  # exact shape of false-green #1 (continue-on-error hiding a zero-test run).
  (( seen > 0 )) || red "meta/copies.txt parsed 0 families -- the gate checked nothing"
  (( fails == 0 )) && echo "ok: $seen copy families at their pinned counts"
fi

# ------------------------------------------------------------- 2. selectors
if grep -rnE "getByTestId\(\s*['\"\`]" specs fixtures; then
  red "getByTestId with a literal -- PR #1197 testids do not exist at the submodule pin (D383)"
else
  echo "ok: no literal getByTestId"
fi

# ------------------------------------------------------------- 3. typecheck
if npx tsc --noEmit; then
  echo "ok: tsc --noEmit clean"
else
  red "the suite does not type-check -- a suite that does not compile cannot have tested anything (D394)"
fi

if (( fails )); then
  echo "LINT FAILED: $fails check(s)" >&2
  exit 1
fi
echo "LINT OK"
