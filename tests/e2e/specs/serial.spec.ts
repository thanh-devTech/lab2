import { test, expect } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';

/**
 * SERIAL -- the home of the @serial tests, and the guard that keeps it a home.
 *
 * WHY THE FILE EXISTS AT ALL. `playwright.config.ts` has carried a `serial`
 * project since the framework was written: `workers: 1`, `dependencies:
 * ['main']`, for the families that mutate GLOBAL or PERSONA state and therefore
 * cannot run beside anything. Its `testMatch` was `/specs\/serial\.spec\.ts/`
 * and THIS FILE DID NOT EXIST, so the project matched zero files. That is not
 * merely a dead project: `main` grep-INVERTS `@serial`, so between the two
 * projects an `@serial` test written anywhere in the suite was excluded from
 * `main` by its tag and from `serial` by its filename, and RAN IN NO PROJECT.
 * It would have been reported by nobody, at a green exit code -- the seventh
 * false-green mechanism this project has produced, and the cheapest to have
 * shipped. ofc.spec.ts:596 already routes a fixme'd test here by name, so the
 * trap had a first customer waiting.
 *
 * The fix is in the config (both projects now match every spec file and split
 * on the TAG ALONE). The test below is what stops the fix being undone: a
 * `testMatch` narrowed back to this filename, or a grep pair that stops
 * agreeing about `@serial`, goes RED here instead of going quiet.
 *
 * NO APP STATE. This test reads the config and the directory listing, nothing
 * else, which is why it is safe to run beside anything else being written into
 * this file. Add @serial app tests below it, not into it.
 */

/** Every spec the collector can see, from disk -- not from a list kept here. */
const SPEC_FILES = fs
  .readdirSync(__dirname)
  .filter((f) => f.endsWith('.spec.ts'))
  .sort();

/**
 * Does one project's `testMatch` claim this file?
 *
 * Playwright matches `testMatch` against the absolute path with forward
 * slashes. Only RegExp patterns are understood here: a glob string would need
 * minimatch to evaluate, and silently returning `false` for one would turn this
 * guard into a false alarm. It throws instead, loudly, naming what to do.
 */
function claims(testMatch: string | RegExp | Array<string | RegExp>, file: string): boolean {
  for (const p of Array.isArray(testMatch) ? testMatch : [testMatch]) {
    if (!(p instanceof RegExp)) {
      throw new Error(
        `[serial.spec] testMatch ${JSON.stringify(p)} is a glob string. This guard only `
        + 'evaluates RegExp patterns -- teach it minimatch or keep testMatch a RegExp.',
      );
    }
    p.lastIndex = 0;
    if (p.test(file.replace(/\\/g, '/'))) return true;
  }
  return false;
}

/** Would `grep`/`grepInvert` select a title carrying this tag? */
function selects(grep: RegExp | RegExp[] | undefined, title: string): boolean {
  if (!grep) return false;
  return (Array.isArray(grep) ? grep : [grep]).some((g) => { g.lastIndex = 0; return g.test(title); });
}

test('@serial an @serial test written in any spec file runs in exactly one project', async ({}, testInfo) => {
  // THE CONTROL, and it is the whole reason this test is tagged. If the
  // `serial` project were still matching zero files this line would never
  // execute at all; reaching it as project `serial` -- rather than as `main`,
  // which also matches this filename now -- is the tag being honoured end to
  // end. Everything below is about OTHER files.
  expect(testInfo.project.name,
    'this @serial test executed, and the project that ran it is `serial`').toBe('serial');

  const main = testInfo.config.projects.find((p) => p.name === 'main');
  const serial = testInfo.config.projects.find((p) => p.name === 'serial');
  expect(main, 'a project named `main` is configured').toBeTruthy();
  expect(serial, 'a project named `serial` is configured').toBeTruthy();
  // Not vacuous on an empty directory: a readdir that returned nothing would
  // make every filter below trivially satisfied.
  expect(SPEC_FILES.length, 'specs/ holds the suite').toBeGreaterThan(1);

  // 1. NO SPEC FILE MAY BE OUTSIDE THE serial PROJECT. This is the invariant
  //    that failed: an @serial tag in any of these files needs `serial` to
  //    collect the file before its grep can select the test.
  const unreachable = SPEC_FILES.filter((f) => !claims(serial!.testMatch, path.join(__dirname, f)));
  expect(unreachable,
    'an @serial test in these files would be collected by NO project: `main` grep-inverts the '
    + 'tag and `serial` does not match the filename. Widen the serial project\'s testMatch.')
    .toEqual([]);

  // 2. AND NONE OUTSIDE `main` EITHER -- the same trap with the tag removed.
  //    While `main` read `(?!auth|serial)`, an UNTAGGED test in this very file
  //    ran in no project.
  const unclaimed = SPEC_FILES.filter((f) => !claims(main!.testMatch, path.join(__dirname, f)));
  expect(unclaimed, 'an untagged test in these files would be collected by NO project')
    .toEqual([]);

  // 3. AND IT MUST ACTUALLY BE SCHEDULED. `dependencies: ['main']` used to
  //    carry the ordering, and it also gates: playwright drops a project's
  //    whole test-group list when any test in a dependency FAILED
  //    (`hasFailedDeps` in the runner's test-suite task). This suite is built
  //    to carry red defect pins, so `main` is red by design -- and this very
  //    test, the guard against @serial tests running in no project, was itself
  //    running in no project, reported only as "did not run". The ordering now
  //    rides on `main`'s `teardown: 'serial'`, which waits the same way and
  //    does not gate. Reaching this line at all is half the proof; the
  //    assertion is the other half, so the gate cannot be reinstated quietly.
  expect(
    serial!.dependencies ?? [],
    'the `serial` project has a project DEPENDENCY again. Playwright will not schedule it at '
    + 'all on any run where a dependency project has a failing test, and this suite pins live '
    + 'defects red on purpose -- so every @serial test would silently stop running. Order it '
    + "with `teardown: 'serial'` on the project it must follow instead.",
  ).toEqual([]);

  // 4. EXACTLY ONE, not both. The two projects overlap on every file now, so
  //    the tag is the only thing separating them: `serial` must select it and
  //    `main` must reject it, or an @serial test runs twice -- concurrently,
  //    against the global state it was tagged to protect.
  const tagged = 'some test title @serial';
  expect(selects(serial!.grep as RegExp | RegExp[], tagged),
    'the `serial` project greps @serial in').toBe(true);
  expect(selects(main!.grepInvert as RegExp | RegExp[], tagged),
    'the `main` project greps @serial OUT -- otherwise it runs in both, in parallel').toBe(true);
});
