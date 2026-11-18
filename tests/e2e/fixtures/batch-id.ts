/**
 * `ci_batches.batch_id` / `ci_batches_blood.batch_id` allocation (D243, D396).
 *
 * WHY THIS IS NOT `Date.now()`. The column is `int(10) NOT NULL`, it is the key
 * the review queues select on, and rendering a queue for a batch RELEASES the
 * drafts it finds (D034/D225). So two tests that happen to share a batch_id do
 * not merely collide on a fixture value -- one of them silently approves the
 * other's report on a GET, and the victim fails an assertion it had no part in.
 * Every worker in a run starts inside the same SECOND, so a time-only base
 * hands every worker the identical id; that is D243's measured root cause.
 *
 * THE LAYOUT, and the third term is what wave 4's adversarial review added:
 *
 *     seconds * 1000  +  worker slot * STRIDE  +  spec offset  +  sequence
 *
 * The worker slot separates two workers. The SPEC OFFSET separates two spec
 * FILES that load in the same second on the same worker -- which the slot alone
 * does not, and which four of the suite's five allocators were exposed to
 * because they all used offset 0. `SPEC_OFFSET` is the whole invariant, stated
 * in one place: distinct keys, `BLOCK` ids apart, so no two specs can overlap
 * without this table saying so.
 *
 * A SPEC THAT OPEN-CODES ITS OWN BASE IS NOT COVERED BY ANY OF THAT, and two
 * did. `ofc` used `1e9 + (Date.now() % 1e9)`, which reduces to
 * `1e9 + (t_s % 1e6) * 1000 + <ms>` -- the SAME base band as this file whenever
 * `t_s % 2e6 >= 1e6`, i.e. about half of every 23.1-day cycle, with the ms
 * fraction landing wherever it likes inside the slot/offset space. Computed
 * live 2026-08-25: `ofc` minted `1_677_885_101` while this file's base was
 * `1_677_885_000`, i.e. inside worker-slot-1's `rev` block (...100-115). `doc`
 * open-coded `1.700e9 + (Date.now() % 14e6) * 3`, which enters the band
 * whenever `t_s % 2e6` falls in [1_700_000, 1_742_000). Both now take their ids
 * from here, which is the only reason the table below is checkable.
 *
 * Max id 1_999_999_999 -- inside signed int(10). FOUR worker slots per run
 * (`workers` is 2 on CI and 4 locally), `BLOCK` batches per spec per worker per
 * run.
 */

/**
 * ids reserved for each spec inside its worker's stride.
 *
 * RAISED 16 -> 24 (wave 6), and the old value was ALREADY BEING EXCEEDED.
 * Measured on this tree, `npx playwright test specs/rev.spec.ts --workers=1`:
 * that file allocates 17 ids in one worker process (15 inside the lifecycle
 * describe, 2 in the dispatcher describe), so the 17th call threw -- and it
 * threw inside `rev.spec.ts`'s dispatcher `e2e.fail()` body, where Playwright
 * counts the throw as the expected failure and reports GREEN. The tell is the
 * duration: that test ran in 2 ms in the full-file run and 940 ms when run
 * alone. An overflow that hides inside a `fail()` marker is exactly the silent
 * degradation this allocator exists to prevent, so the budget moves rather than
 * the tests being spread thinner.
 *
 * RAISED AGAIN 24 -> 28 (wave 7 review). `rev.spec.ts` mints 22 ids per worker process --
 * 20 call sites, one of them inside a 3-iteration loop -- so 24 left two ids of headroom
 * on the file that is growing fastest.
 */
const BLOCK = 28;

/**
 * Per-worker stride. 8 specs * BLOCK = 224 <= 250, and 4 * 250 = 1000.
 *
 * RE-SPACED 200 -> 250 when `xtw` became the EIGHTH spec in the table below.
 * The two constants are not independent: `SPEC_OFFSET` hands the last spec
 * `7 * BLOCK`, so a worker's ids run to `7 * BLOCK + BLOCK - 1` = 223, and at
 * STRIDE 200 that reaches 24 ids INTO the next worker slot -- `xtw` on slot 0
 * and `rev` on slot 1 would have shared ids 200..223, which is D243 exactly.
 * The invariant is `SPEC_COUNT * BLOCK <= STRIDE`; raise STRIDE whenever a spec
 * is added or BLOCK moves.
 *
 * SLOTS is 4, which still MEETS every configured worker count (`workers` is 2
 * on CI and 4 locally) and the constructor refuses loudly rather than colliding
 * if that ever stops being true.
 */
const STRIDE = 250;
const SLOTS = 1000 / STRIDE;

/**
 * Every spec that mints batch ids. Add a spec here rather than open-coding a
 * base in the file -- an offset that is not in this table cannot be checked
 * against the others.
 */
const SPEC_OFFSET = {
  rev: 0 * BLOCK,
  tst: 1 * BLOCK,
  dst: 2 * BLOCK,
  adm: 3 * BLOCK,
  bil: 4 * BLOCK,
  ofc: 5 * BLOCK,
  doc: 6 * BLOCK,
  xtw: 7 * BLOCK,
} as const;

export type BatchIdSpec = keyof typeof SPEC_OFFSET;

const WORKER_SLOT = Number(process.env.TEST_PARALLEL_INDEX ?? 0);

/**
 * A per-spec allocator. Call ONCE at module scope; the returned function hands
 * out the spec's ids in order.
 *
 * It refuses rather than wrapping past `BLOCK`: running out means the spec now
 * mints more batches than the table reserves for it, and quietly stepping into
 * the neighbouring spec's ids is exactly the failure this file exists to stop.
 *
 * OVERFLOW ENDS THE WORKER, it does not throw. MEASURED: inside a `test.fail()` body
 * Playwright counts a throw -- any throw, including a TimeoutError -- as the expected
 * failure and reports GREEN, which is how the 16 -> 24 overflow hid in `rev.spec.ts`'s
 * dispatcher pin for a whole wave. Five of `rev`'s last six tests are `fail()` bodies,
 * so a throw here is a coin flip on whether anyone ever sees it. A worker exit is not
 * absorbable: Playwright reports `worker process exited unexpectedly (code=1)` as a
 * FAILED test and re-runs the siblings in a fresh worker (measured, wave 7).
 */
export function batchIds(spec: BatchIdSpec): () => number {
  if (WORKER_SLOT >= SLOTS) {
    throw new Error(
      `[batch-id] worker slot ${WORKER_SLOT} is outside the ${SLOTS} slots this layout `
      + `reserves. Lower \`workers\` or re-space STRIDE/BLOCK -- slot ${SLOTS} starts where `
      + "the next SECOND's ids begin.",
    );
  }
  const base = (Math.floor(Date.now() / 1000) % 2_000_000) * 1000
    + WORKER_SLOT * STRIDE + SPEC_OFFSET[spec];
  let seq = 0;
  return () => {
    if (seq >= BLOCK) {
      // eslint-disable-next-line no-console
      console.error(
        `[batch-id] ${spec}.spec.ts asked for more than ${BLOCK} batch ids in one run. `
        + 'Raise BLOCK and re-space SPEC_OFFSET in fixtures/batch-id.ts -- do not let this '
        + "spec run into its neighbour's ids. Ending this worker so the run cannot report "
        + 'green: a throw here is absorbed by any `fail()` body it lands in.',
      );
      process.exit(1);
    }
    return base + seq++;
  };
}

/**
 * THE DAY AXIS -- `ci_batches.doc`, partitioned the way `batch_id` is.
 *
 * WHY THIS EXISTS. Every seeder in this suite posts `'bat[doc]': 'auto'`, and
 * `ci_save_form_requisition.php:63-64` turns that into `date_i18n('Y-m-d
 * H:i:s')`. So EVERY specimen the suite has ever created sits on today. That
 * is one of the five monotonous fixture axes (52-PASS-3-PLAN Part 1.1): the
 * day-grouped screens -- `parts/tox/test_results.php:112-121` groups by
 * `date(ba.doc)` and is the reachable driver of `trpdd_get_batch_conditions()`
 * -- render a single row for every persona, so a scope clause that changes
 * WHICH DAYS appear cannot be observed and a mutation in it survives.
 *
 * WHY IT IS NOT `new Date(Date.now() - 86400e3)` AT THE CALL SITE. The day is
 * a SHARED namespace, exactly like `batch_id` was before D243. Nothing narrows
 * that day query by batch: if a test asserts "this day holds only clinic 2"
 * and a concurrently-running test in the same spec file seeds a clinic-1
 * specimen on the same date, the absence assertion flakes -- and it flakes as
 * a scope leak, i.e. it accuses the application. Days are handed out here so
 * two tests cannot land on one.
 *
 * WHAT MAKES (slot, seq) ENOUGH, and WHERE IT IS NOT ENOUGH. Days only have to
 * be unique WITHIN A LAB, because every scoped read carries a lab predicate.
 * Worker slot separates two tests running at once; `seq` separates two days
 * inside one test. `tenant.ts` SPEC_LAB then gives each spec file its own pool
 * lab -- so for anything driven as that file's `e2e_lead<n>`, (slot, seq) is a
 * private day namespace and nothing more is needed.
 *
 *   TWO PLACES WHERE THE LAB IS *NOT* PRIVATE, both measured 2026-08-26:
 *
 *   1. EVERY NON-LEAD PERSONA SHARES ONE LAB. `_lab_id` is 35 (`E2E`) for all
 *      twelve of e2e_sec, e2e_tech, e2e_dir, e2e_patient, e2e_billing,
 *      e2e_billclin, e2e_doctor, e2e_collector, e2e_officemgr, e2e_multisite,
 *      e2e_pcrverify and e2e_scientist -- only `e2e_lead0..15` hold LAA..LAP.
 *      So a test driven as a ROLE persona is in a lab every other spec file's
 *      role personas are also in, and two spec files in one worker slot get
 *      the SAME days from their own module-scope allocators.
 *   2. LAO, the foreign lab `allocateTenant(db, SPARE_POOL_INDEX)` hands to any
 *      spec that asks. Rows seeded there are cross-tenant bait, discriminated
 *      by LAB and not by day.
 *
 *   In either case a day-discriminated ABSENCE assertion needs a second
 *   narrowing term or it flakes AS A SCOPE LEAK, i.e. it accuses the
 *   application. `ofc.spec.ts:889` is the worked example: it runs as
 *   `e2e_officemgr` in lab 35 and adds `s=<runKey>` so the day list holds only
 *   its own two patients.
 *
 * CEILINGS, both real:
 *   - The day list paginates at 30 rows (`test_results.php:105`) ordered
 *     `bdoc desc`. `SLOTS * DAY_BLOCK` = 16 days back keeps every allocated day
 *     on page 1 alongside today's. Raising DAY_BLOCK past 7 breaks that.
 *   - Anything 4+ days back is STALE to the receiving age gate
 *     (`ci_search_barcode_receiving.php` `m3days`, armed by `receiving_w3d`).
 *     A specimen seeded on an allocated day and then scanned at receiving will
 *     trip that warning if the option is on. Pass an explicit date instead when
 *     the specimen's AGE is the thing under test.
 *
 * Returns `Y-m-d H:i:s` at midday -- not midnight, so a timezone skew between
 * PHP's `date_i18n` and this process cannot move the row across a date boundary
 * and change which group it lands in.
 */
const DAY_BLOCK = 4;

export function seedDays(): () => string {
  let seq = 0;
  return () => {
    if (seq >= DAY_BLOCK) {
      // Same reasoning as the batch-id overflow above: a throw is absorbed by
      // any `fail()` body it lands in, so end the worker instead.
      // eslint-disable-next-line no-console
      console.error(
        `[batch-id] seedDays() asked for more than ${DAY_BLOCK} days in one worker. `
        + 'Raise DAY_BLOCK -- but SLOTS * DAY_BLOCK must stay under the 30-row page of '
        + 'parts/tox/test_results.php, or an allocated day falls off page 1 and every '
        + 'presence assertion built on it goes red for the wrong reason.',
      );
      process.exit(1);
    }
    const back = 1 + WORKER_SLOT * DAY_BLOCK + seq++;
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() - back);
    const p = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} 12:00:00`;
  };
}
