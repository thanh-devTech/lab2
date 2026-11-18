# LIS E2E suite (Playwright)

End-to-end tests for the Ignite Medical LIS, running the real WordPress
multisite and the real `ci-*` plugins against a disposable, **egress-denied**
Docker stack.

Status: **123 tests, 99 passing and 24 skipped** across 14 spec files — one per
documented persona, covering all 9 business flows. Measured on CI, from the step
log of run `32796031461`: `Running 123 tests using 2 workers`, `99 passed
(2.5m)`. ~30 scenarios carry `test.fixme()` naming the specific blocker rather
than a stub, and six carry `test.fail()` pinning a filed product defect — those
count as passing today and turn **red** the day the defect is fixed, which is
the point of them. Local full suite: **53.6s** idle.

Two of those tests drive Chromium (`rev.spec.ts` S-REV-01u / S-REV-03u, via the
`authedPage` / `pageAs` fixtures). Their CI cost is **below run-to-run variance**
and cannot be quoted as a number yet: 138 s for 120 tests before them, then
150 s and 135 s for 123 tests in two consecutive runs. Locally they measure
2.7 s and 2.9 s against 1.7 s and 1.4 s for the HTTP tests they sit beside —
about **+1.3 s each**, most of it browser-context creation. A browser test
asserts what the screen shows AND the database row it came from, never one or
the other, and it reads the screen back off a `page.reload()`: the DOM
immediately after a click in this app is written by the click handler, which
flips the control on any HTTP 200 whether or not the write landed.

## The one rule this suite is built on

**Assert a database row, never an HTTP status.**

This application returns `200` on all of the following, every one measured:

| What went wrong | What the app returned |
|---|---|
| A column the saver writes does not exist | `200`, zero rows written |
| The action verb does not match any handler | `200`, silent no-op |
| A scalar passed where an array is required | `200`, stores `''` |
| An `INSERT` rejected by `STRICT_TRANS_TABLES` | `200 {"success":1}` |
| Authorization refused the request | `200 <div class="ajaxstatus">ok</div>` |
| The user lacks the capability for the page | `200` + a 2,629-byte denial body |

A suite built on status assertions passes against a fully broken app — that was
demonstrated during review, where dropping one column left 11 tests green. So
every spec asserts real columns and real values, and each was checked with a
deliberate fault injection to confirm it goes red when the app is wrong.

Two corollaries worth knowing before you write a spec:

- **Capabilities come from the RUNNING instance, never `install_roles.php`.**
  `create_role` does not rewrite an existing role, so source and live disagree —
  11 documented cases so far. And `user_can()` is unusable here: it answers true
  off `level_0` for oddly-named capabilities. Read `WP_User->allcaps` directly.
- **Rendering a listing MUTATES data and authorization state.** A GET can create
  a report, release it, and grant the visiting user a role. Navigation is not
  read-only; tag such tests `@mutates`.

---

## Run it

```bash
cd tests/e2e
npm ci                  # once
make e2e                # boot -> seed -> mint -> snapshot -> safety gate -> test -> teardown
```

`make e2e` installs `trap env/down.sh EXIT INT TERM`, so Ctrl-C still destroys
the stack. Playwright's own `teardown:` project does **not** run on SIGINT.

To keep the stack up and iterate:

```bash
make up seed mint snapshot   # once
npx playwright test          # as often as you like
npx playwright test --headed # or --ui, or --debug
make down                    # when finished
```

Other targets: `make gate` (safety gate alone), `make test ARGS='--grep @smoke'`,
`make clean` (down + delete state + reports).

### The bring-up order is not negotiable

```
up.sh → seed.sh → mint-session.sh (EVERY persona) → reset.sh --snapshot
```

`reset.sh` restores whatever `wp_usermeta.session_tokens` the baseline contains,
so a snapshot taken **before** minting logs every persona out at the first
reset. Proven in both directions (D139/D158): wrong order → all personas land on
`wp-login.php`; right order → three consecutive resets, all personas
authenticated. Re-snapshot and re-mint always move together.

### Baseline shelf life: 14 days

Auth tokens carry a hard absolute expiry (measured `expires=1788764820`, exactly
14 days — D159). Past that, `reset.sh` restores dead tokens and the whole suite
logs out. It fails **loudly**, at the `setup` project's identity guard, with a
message that says so. `up.sh` warns at 7 days. The fix is always the same: rerun
`make seed mint snapshot` (or just `make e2e`, which does it every time).

---

## Layout

| Path | What |
|---|---|
| `playwright.config.ts` | Five projects, wired by `dependencies:` / `teardown:`. |
| `global-setup.ts` | Runs the safety gate locally. `--no-deps` cannot skip it. |
| `Makefile` | The one-command entry, with the trap-based teardown. |
| `fixtures/` | The only first-party test code: `test.ts`, `db.ts`, `run-key.ts`, `tenant.ts`, `seed-domain.ts`, `assert-domain.ts`, `stub-http.ts`. |
| `setup/env.setup.ts` | Reset, then a per-persona **identity** assertion. |
| `setup/teardown.ts` | Reset, then assert the suite added no git dirt. |
| `specs/*.spec.ts` | One file per flow (`ord`, `acc`, `tst`, …). |
| `env/` | Docker stack, seeds, session minting, safety gate. |
| `QUARANTINE.md` | Flake register and the tag inventory. |
| `${TMPDIR}/lis-e2e-state/` | storageState + `baseline.sql`. **Outside the checkout** — the checkout is the webroot. |

### Project graph

```
globalSetup (safety gate) ─ runs before every project
  setup ──▶ main ──▶ serial ──▶ (teardown, via teardown: on setup)
```

`serial` is sequenced with `dependencies:`, **not** `test.describe.serial` —
that does not give `workers: 1`, it serialises within one describe while
another spec runs on the other worker (D150).

**Both projects match EVERY spec file and split on the tag alone**: `main`
grep-inverts `@serial`, `serial` greps it in. Do not narrow either `testMatch`
to a filename — that is what left an `@serial` test running in *no* project
(`specs/serial.spec.ts` asserts the pairing and goes red if it comes back).

There is no `real-login` project. It matched `specs/auth.spec.ts`, which has
never existed here, and driving `wp-login.php` is not something this suite can
do — the form carries a CAPTCHA (R3/R6/D152), which is why every project runs
off a minted storageState. Restore the project *with* the spec, never ahead of
it.

---

## Writing the next spec

1. Add `specs/<flow>.spec.ts` and import the shared test object:

   ```ts
   import { test, expect } from '../fixtures/test';
   import { expectBatch } from '../fixtures/assert-domain';
   ```

2. Tag it: `test.describe('S-XXX-01 @smoke @<flow>', …)`. Tags come from the
   inventory in `QUARANTINE.md`; `env/select-suite.sh` maps changed paths onto
   them.

3. Take what you need from the fixtures — `db`, `tenant`, `runKey`,
   `seedRequisition`, `stubHttp`. `tenant` gives you a lab from the baked pool
   and the `leadlabsecman` persona bound to it.

4. Build every per-test key out of `runKey`. It is unique across runs, not just
   within one (D136). It is **worker**-scoped, so if a spec seeds more than one
   row, append your own per-test suffix.

5. Assert the **business outcome** from `10-scenarios.md`, plus tenancy:

   ```ts
   const { assen } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });
   ```

   Never `expect(res.status()).toBeLessThan(400)` — a login redirect is 302 and
   passes it. Never a `LIKE '%key%'` match — another test's row satisfies it.
   Never an AUTO_INCREMENT delta — the counter moves even when the saver writes
   nothing (D142). The invariant is **rows gained > 0**.

6. Batch your DB reads. Each `docker compose exec` costs ~131 ms; one `SELECT`
   with all the columns beats five scalars. There is no host-side `mysql2`
   option — publishing 3306 on an `internal: true` network is silently dropped
   by Docker (D164).

7. If the flow mutates global or persona state (auto-approve on GET, role grant
   on GET, `ALTER` on POST, the no-unique-index receiving races), it belongs in
   `specs/serial.spec.ts` with `@serial`, not in `main`.

8. Waiting on a fragment round trip? The predicate is `trajax=1&act=<name>`.
   `tr_act=` occurs **zero** times in the app (D166).

   ```ts
   page.waitForResponse(r => r.request().postData()?.includes('act=load_results'))
   ```

---

## Debugging a failure

```bash
npx playwright test --headed --workers=1 --grep "S-ORD-01"
npx playwright test --ui
```

Use `--headed` / `--ui`, **not** traces. `trace`, `screenshot` and `video` are
all `off` and must stay off: Playwright 1.62.1 has no redaction, and
`snapshots:{dom:false}` still leaves `aria:true` carrying the same accessible
names (D063).

Read what the app actually did:

```bash
cd env
docker compose exec -T db mariadb -uroot -proot lab -e \
  "SELECT mid,assen,batch_type,batch_status,lab,lab_id FROM ci_batches ORDER BY mid DESC LIMIT 5"
docker compose logs --no-color --tail=200 wordpress | sed -E 's/\?[^ ]*//'
docker compose exec -T wordpress cat /tmp/wp-debug.log
docker compose exec -T wordpress cat /tmp/e2e-http-intercepts.log   # outbound calls the stub saw
```

Common causes, in the order they actually occur:

| Symptom | Cause |
|---|---|
| Every persona fails the identity guard | Snapshot taken before mint, or baseline >14 days old. `make seed mint snapshot`. |
| `ci_batches gained no row` at HTTP 200 | A column the saver writes is missing (D100) — the schema-manifest gate in `seed.sh` should have caught it — or `form_unique_id` was replayed (D142). |
| `assen` is empty at HTTP 200 | `tr_get_new_assen()` returned nothing. Real app behaviour, not a harness bug (D160). |
| `lab_id` is not the tenant | Routing regression, or `_lab_ids` was deleted from the persona (D161). |
| Suite passes but nothing happened | The assertion is wrong. Run the fault-injection pass below. |

### Fault injection — the only detector of assertion rot

```bash
# routing / correctness fault
E2E_FAULT=wrong-lab npx playwright test --project=main --no-deps   # MUST go red

# write-path fault
docker compose -f env/docker-compose.yml exec -T db \
  mariadb -uroot -proot lab -e "ALTER TABLE ci_batches DROP COLUMN is_saved"
npx playwright test --project=main --no-deps                        # MUST go red
env/reset.sh                                                        # restores the column
```

A suite that stays green under either of these is broken, whatever it says.

---

## Reproducing a CI failure locally

CI runs exactly the steps in `.github/workflows/e2e.yml`. To match it:

```bash
cd tests/e2e
git submodule status wp-content/plugins   # every line must start with a space
CI=1 E2E_STATE_DIR=/tmp/lis-e2e-state make up seed mint snapshot gate
CI=1 E2E_STATE_DIR=/tmp/lis-e2e-state npx playwright test --workers=2
```

`CI=1` switches workers 4→2 and the reporter to `github` + `html`, and makes
`globalSetup` skip the gate (CI runs it as its own blocking step instead — see
below). Everything else is identical: same images, pinned by digest, same seed,
same mint order.

**Check the submodules first.** CI checks out the pinned commits; a working tree
does not have to. A leading `+` from `git submodule status` means that plugin is
checked out somewhere else, and you are not running the code CI runs. It shows up
as an assertion that reads live state — `adm.spec.ts` pins live capability counts,
and one extra capability in `tr_pdd_lis_caps()` moves two of them.

CI uploads `tests/e2e/test-results/` on failure with **`retention-days: 1`**
(D063 PHI policy). It never uploads `$E2E_STATE_DIR` — those are bearer
credentials and an 8 MB database dump.

### What blocks a merge

Only **`Safety gate (BLOCKING)`**. The test step carries
`continue-on-error: true`, consistent with the rest of this repo (D025), and is
advisory. That split is deliberate (D149/D173): a containment leak fails the
run, a red test does not. Revisit after ~2 weeks of stable smoke.

---

## Safety properties you must not weaken

- `networks: lis: internal: true` in `env/docker-compose.yml` is the egress
  boundary. The app self-registers against the **production** hub at plugin-load
  file scope. `WP_HTTP_BLOCK_EXTERNAL` covers 150 of 267 call sites and zero
  SFTP; it is defence in depth, not the boundary.
- `env/apache-e2e.conf` denies whole data directories, not file extensions. The
  extension allowlist that preceded it served `/wp-content/log/logapi.txt` —
  HTTP 200, with an auth token in the body (D147).
- `env/sql/30-strip-pii.sql` runs from `seed.sh`, never from
  `docker-entrypoint-initdb.d`, which fires only on an empty datadir (D153).
- `trace/screenshot/video: 'off'`.
- Fixtures use obviously fake names and `1900-01-01` DOBs. No real-looking SSN,
  DOB, phone or address, ever.
