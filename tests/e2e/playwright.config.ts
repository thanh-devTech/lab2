import { defineConfig } from '@playwright/test';
import * as os from 'node:os';
import * as path from 'node:path';

const CI = !!process.env.CI;

/**
 * State (storageState JSON + baseline.sql) lives OUTSIDE the checkout.
 * `tests/e2e/state/` resolved to a path inside the `../../../:/var/www/html`
 * bind mount, i.e. inside the webroot: the apache deny was the only thing
 * between a live admin session cookie and an anonymous HTTP GET (R12/R18).
 */
export const STATE_DIR =
  process.env.E2E_STATE_DIR ?? path.join(os.tmpdir(), 'lis-e2e-state');

export default defineConfig({
  testDir: '.',
  // The safety gate on the LOCAL path. `--no-deps` skips project dependencies
  // but CANNOT skip globalSetup (B-R1/D148), so this is the one place a local
  // run cannot route around. CI runs env/safety-gate.sh as its own blocking
  // step instead, because the test step is advisory (D149/D173).
  globalSetup: './global-setup.ts',
  forbidOnly: CI,
  // Without this, parallelism is per FILE and `workers` is a no-op for a
  // single-file project -- measured: 5 tests, workers:4, "using 2 workers".
  // Safe here because isolation is per-worker tenant lab + per-test runKey
  // (§3.4), not per-file ordering.
  fullyParallel: true,
  // A retry replays form_unique_id, which makes the saver `return` at HTTP 200
  // with no row (D142) -- so a retry converts a real failure into a green
  // no-op. Flakes go to QUARANTINE.md instead.
  retries: 0,
  // D114: 2 on the 2-core CI runner, 4 locally. The design pinned 2 everywhere;
  // the brief keeps D114's local value.
  workers: CI ? 2 : 4,
  // ['list'] IS ON CI DELIBERATELY, and it is the instrument three cost waves
  // did without. The github reporter prints failures and a one-line tally, so
  // the only CI number ever available was the SUITE TOTAL -- and the runner's
  // run-to-run spread (16 s at wave 3, 51 s at wave 4) swallows any single
  // conversion, which is why D399's 8x local->CI penalty and D402's "cannot
  // resolve" could not be reconciled. `list` prints a per-test duration for
  // every test, so the penalty for a SHAPE can be read straight off the step
  // log instead of being inferred from a total under a noise floor.
  reporter: CI ? [['github'], ['list'], ['html', { open: 'never' }]] : [['list']],
  timeout: 60_000,
  // Bounded on purpose: an `expect.poll` that inherits the 60 s test timeout
  // turns the fault-injection pass from 54 s into 330 s (R7/D155).
  expect: { timeout: 5_000 },
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost',
    // Comma list, verified working in 1.62.1 (R11/D167): getByTestId() then
    // matches the STABLE Batches/Inbound rows' data-mid
    // (admin_ci_batches_view.php:1342) without hand-written CSS.
    testIdAttribute: 'data-testid,data-mid,data-btype',
    // D063: 1.62.1 has NO trace/snapshot redaction -- `snapshots:{dom:false}`
    // still leaves `aria:true`, carrying the same accessible names. The control
    // is to capture nothing. Debug with --headed/--ui instead.
    trace: 'off',
    screenshot: 'off',
    video: 'off',
  },
  projects: [
    // reset -> per-persona IDENTITY guard. `teardown:` (not a root project) is
    // what makes teardown run LAST and survive --grep (R5/R9/D151).
    { name: 'setup', testMatch: /setup\/env\.setup\.ts/, teardown: 'teardown' },
    { name: 'teardown', testMatch: /setup\/teardown\.ts/ },

    // EVERY spec file, minus the @serial tests. The exclusion used to be
    // `(?!auth|serial)`, which orphaned any UNTAGGED test written in
    // specs/serial.spec.ts -- excluded here by filename, and grep-excluded from
    // `serial` below by having no tag. Matching all files and splitting the two
    // projects on the TAG ALONE is the only shape in which no test can fall
    // between them.
    { name: 'main',
      testMatch: /specs\/.*\.spec\.ts/,
      grepInvert: /@serial/,
      dependencies: ['setup'],
      teardown: 'serial',
      use: { storageState: undefined } },

    // The GLOBAL/PERSONA-mutating families. Running AFTER the whole of `main`,
    // alone, is the requirement: test.describe.serial does NOT give workers=1,
    // it sequences within one describe while another spec runs on the other
    // worker (D150/D170).
    //
    // `teardown:` ON `main`, NOT `dependencies: ['main']` HERE, and the swap is
    // a false-green fix rather than a style choice. Both orderings are the same
    // -- a teardown project waits for its setup AND everything that depends on
    // it, so the phases stay setup -> main -> serial -> teardown -- but
    // `dependencies` ALSO gates execution on the dependency PASSING
    // (playwright/lib/runner: `hasFailedDeps` drops the whole project's test
    // groups, and a project counts as successful only if EVERY test in it is
    // ok()). This suite is built to carry red defect pins, so `main` is red by
    // design and the `serial` project was therefore never scheduled at all:
    // both of its tests -- including the guard in specs/serial.spec.ts that
    // exists to catch @serial tests running in no project -- reported "did not
    // run" on every run. A teardown edge carries the ordering without the gate.
    // specs/serial.spec.ts asserts the absence of `dependencies` here so the
    // gate cannot come back quietly.
    //
    // testMatch IS THE SAME REGEX AS `main`, and narrowing it back to
    // specs/serial.spec.ts re-opens a measured false-green trap: while it read
    // that way, specs/serial.spec.ts DID NOT EXIST, so this project matched
    // ZERO files and an `@serial` tag written anywhere else ran in NO
    // PROJECT -- grep-inverted out of `main`, filename-excluded from here, and
    // reported by nobody. specs/serial.spec.ts:1 asserts this pairing so the
    // narrowing cannot come back silently.
    { name: 'serial',
      testMatch: /specs\/.*\.spec\.ts/, grep: /@serial/,
      workers: 1,
      use: { storageState: undefined } },

    // THERE IS NO `real-login` PROJECT. It matched specs/auth.spec.ts, which
    // has never existed in this tree, so it was a fourth project matching zero
    // files -- and, being `dependencies: ['serial']`, it was also the only
    // reader of a chain nothing else needed. Driving wp-login.php is not a
    // thing this suite can do anyway: the form carries a CAPTCHA (R3/R6/D152),
    // which is why every project above runs off a minted storageState. Restore
    // the project WITH the spec, never ahead of it.
  ],
});
