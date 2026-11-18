import { request as playwrightRequest, type APIRequestContext } from '@playwright/test';
import * as path from 'node:path';
import { test as e2e, expect, BEFORE_BOUNCE_MS } from '../fixtures/test';
import { infra, seedRequisition } from '../fixtures/seed-domain';
import { expectBatch } from '../fixtures/assert-domain';
import { Db } from '../fixtures/db';
import { batchIds, seedDays } from '../fixtures/batch-id';
import { wpEval } from '../fixtures/container';
import type { Tenant } from '../fixtures/tenant';
import { STATE_DIR } from '../playwright.config';

/**
 * OFC -- office manager (clinic), personas.md 3.9. The axis under test is
 * CLINIC-SCOPED VISIBILITY, which is vacuous with one clinic; `seed.php` now
 * ships two (D258), so it is not vacuous here.
 *
 * PERSONAS, read from the RUNNING instance via `WP_User->allcaps` (D178), never
 * from install_roles.php and never through `user_can()` (D255):
 *
 *   e2e_officemgr  id 11521, role `office_manager`, 12 caps: read, level_1,
 *                  level_0, form_allergy, form_pcr, ci_test_results,
 *                  ci_patient_chart, ci_patient_trends, ci_data_forms,
 *                  form_blood, ci_phlebotomist, ci_abx_report.
 *                  personas.md 3.9 also lists form_requisition,
 *                  ci_received_list, clinic_statistics and tox_report for this
 *                  role; LIVE IT HOLDS NONE OF THEM. D178 again.
 *                  meta: _clinic_id=1, _lab_id=19, _lab_ids=19,
 *                  _use_current_clinic_id='' (empty, so the first scope branch
 *                  is not taken). NO `_only_one_clinic`.
 *   e2e_multisite  id 11522, role `multi_site_manager`, 18 caps: the above plus
 *                  clinic_statistics, tox_report, form_requisition,
 *                  form_lab_test_req, ci_data_store, ci_received_list.
 *                  meta: _clinic_id=1, _lab_id=19, _lab_ids=19.
 *                  NO `_ms_clinic_ids` -- see the D201 note below.
 *
 * WHICH SCREENS EACH ONE REACHES (measured; the guard test below is the
 * standing regression on it). Every results LISTING is denied to both at the
 * page layer -- `tox_results`, `blood_results` and `ci_received_list` all
 * answer HTTP 200 with the 2,629-byte `Sorry, you are not allowed` body
 * (D185/D250), not 403. What they do reach is `ci_test_results` (Test Results)
 * and `ci_patient_chart`; `ci_received_list` is reachable by the multi-site
 * manager only.
 *
 * SO THE SCOPE MAP IS EXERCISED WHERE THE PERSONA ACTUALLY IS. sta.spec.ts had
 * to `fixme` S-STA-06 because State Reporting is gated on `covid_reporting`
 * (D234). The same five-branch map -- doctor / collector / office_manager /
 * multi_site_manager / everyone-else-sees-all -- is duplicated verbatim in
 * `inc/actions/ci_load_test_results.php:87-160`, behind `ci_test_results`,
 * which BOTH personas hold. That is the reachable copy, and it is what these
 * tests drive.
 *
 * TENANCY. These personas are pinned to lab 19 (`E2E`) by `_lab_ids`, outside
 * the e2e_lead* pool, exactly like `e2e_tech` in tst.spec.ts -- so the lab is
 * read LIVE out of `wp_usermeta._lab_id` and never hardcoded, and every seeded
 * row asserts against it through `expectBatch` (D172). The `tenant` fixture is
 * neither pinned nor overridden (D241); this file simply does not use the pool
 * lab, because a batch in LAL is invisible to a persona scoped to lab 19 and
 * the test would pass vacuously (D032/D106). `E2E_FAULT=wrong-lab` still bites:
 * `seedRequisition` routes through `routedLabId()`, which sends the batch to a
 * pool lab, and every `expectBatch` here goes red.
 *
 * D201 ANALOGUE, CHECKED BEFORE CLAIMING A LEAK -- and it is REAL for one of
 * the two roles:
 *   - `office_manager` does NOT scope on `_clinic_id` directly. `_clinic_id` is
 *     only the right-hand side of a lookup in the `ci_clinic_manager` TABLE
 *     (`ci_load_test_results.php:98-101`, `ci_load_results.php:104-108`,
 *     `functions.php:5215`). That table is EMPTY in the baseline, so the list
 *     comes back empty, the clause degrades to `and ba.cli_ID in (  )` -- a SQL
 *     syntax error -- and the listing returns ZERO rows. A test that only
 *     asserted "the office manager does not see clinic 2" would pass on a
 *     broken query while proving nothing. The first half of the scope-map test
 *     below demonstrates that failure mode deliberately, then supplies the
 *     missing row and demonstrates real scoping.
 *   - `multi_site_manager` DOES scope on `_ms_clinic_ids`, but that meta is
 *     derived, not seeded: when absent the app rebuilds it from every active
 *     `ci_clinic` row and `update_user_meta()`s it back, ON A GET
 *     (`ci_load_test_results.php:112-117`). Seeding it is unnecessary; the test
 *     asserts the rebuilt value covers every active clinic.
 *
 * FIXTURE GAPS (reported, not patched -- five other agents share this stack):
 *   1. `ci_clinic_manager` has no seeded row, so `office_manager` scoping is
 *      un-demonstrable until a spec creates one. This file creates its own,
 *      keyed to its own user id and a marker e-mail.
 *   2. No `reflab_manager` persona is minted -- see the fixme at the bottom.
 */

// Every test here touches `ci_clinic_manager` or the two managers' user meta,
// which are shared BETWEEN THE TESTS IN THIS FILE. `fullyParallel: true` would
// otherwise run them in four workers and let one test's DELETE land inside
// another's render. A sequential mode keeps this file on one worker; it does
// NOT serialise against other spec files, and nothing here needs it to (D150).
//
// `default` AND NOT `serial`. Both give the one-worker, declaration-order
// execution the paragraph above asks for; `serial` additionally ABANDONS every
// later test in the file once one goes red, which is how eight tests in this
// suite stopped running without being reported as missing. Order is not
// load-bearing here: each test sets up the manager rows it needs and the
// file's afterAll restores them.
e2e.describe.configure({ mode: 'default' });

const OFC = 'e2e_officemgr';
const MSM = 'e2e_multisite';

/** Marker for the `ci_clinic_manager` row this file owns. Never a real address. */
const OFC_MANAGER_EMAIL = 'e2e-officemgr@example.invalid';

/** D185/D250: the page layer denies with HTTP 200 and this body, not with 403. */
const DENIAL = 'Sorry, you are not allowed to access this page';

function ctxFor(baseURL: string | undefined, login: string): Promise<APIRequestContext> {
  return playwrightRequest.newContext({
    baseURL: baseURL ?? 'http://localhost',
    storageState: path.join(STATE_DIR, `${login}.json`),
  });
}

/** The app's own local date -- date_i18n() honours gmt_offset, UTC_DATE() does not. */
/**
 * The app's OWN calendar day, resolved the way `wp_timezone()` resolves it.
 *
 * This used to read `gmt_offset` alone. WordPress stores `gmt_offset` EMPTY
 * whenever `timezone_string` is set (measured here: `timezone_string` =
 * America/New_York, `gmt_offset` = ''), so `COALESCE(MAX(option_value),0)`
 * collapsed to 0 and this returned the UTC day while `date_i18n()` -- which is
 * what every writer in the app stamps rows with -- returned the local one. The
 * two disagree for four hours of every day (20:00-24:00 America/New_York =
 * 00:00-04:00 UTC), and in that window `sta.spec.ts` S-STA-01 failed on
 * `from_date` and `ofc.spec.ts` S-STA-06 rendered "Not found" from a line list
 * filtered on the wrong day. Both failed on a clean tree, at 00:58 UTC.
 *
 * CONVERT_TZ returns NULL for an empty or unknown zone name, so the COALESCE
 * falls through to the historical gmt_offset branch on an install that uses it.
 */
function appToday(db: Db): string {
  return db.scalar(
    `SELECT DATE(COALESCE(
       CONVERT_TZ(UTC_TIMESTAMP(), 'UTC',
         NULLIF((SELECT option_value FROM wp_options WHERE option_name='timezone_string'), '')),
       UTC_TIMESTAMP() + INTERVAL
         COALESCE(NULLIF((SELECT option_value FROM wp_options WHERE option_name='gmt_offset'), ''), 0) HOUR))`,
  );
}

interface Manager {
  userId: string;
  /** `_clinic_id` -- the right-hand side of the ci_clinic_manager lookup. */
  clinicId: string;
  /** Lab 19 as a Tenant, so expectBatch asserts tenancy against it (D172). */
  tenant: Tenant;
}

/** Everything about a manager persona, read live in ONE select (D164). */
function manager(db: Db, login: string): Manager {
  const [row] = db.query(
    `SELECT u.ID,
            (SELECT meta_value FROM wp_usermeta WHERE user_id=u.ID AND meta_key='_clinic_id'),
            (SELECT meta_value FROM wp_usermeta WHERE user_id=u.ID AND meta_key='_lab_id'),
            (SELECT meta_value FROM wp_usermeta WHERE user_id=u.ID AND meta_key='_lab_ids'),
            l.lab_dba_init,
            (SELECT COUNT(*) FROM wp_usermeta WHERE user_id=u.ID AND meta_key='_only_one_clinic'),
            (SELECT COALESCE(meta_value,'') FROM wp_usermeta WHERE user_id=u.ID AND meta_key='_use_current_clinic_id')
       FROM wp_users u
       LEFT JOIN ci_labs l
         ON l.lab_ID = (SELECT meta_value FROM wp_usermeta WHERE user_id=u.ID AND meta_key='_lab_id')
      WHERE u.user_login = ${Db.q(login)}`,
  );
  expect(row, `${login} does not exist -- run env/seed.sh`).toBeTruthy();
  const [userId, clinicId, labId, labIds, prefix, onlyOne, useCurrent] = row;
  // D032/D106: absent `_lab_ids` means the user sees EVERY active non-ref lab,
  // so a scoping test would pass while proving the opposite. Both keys asserted.
  expect(labId, `${login} has no _lab_id -- tenancy would fall back to lab #1`).not.toBe('');
  expect(labIds, `${login} has no _lab_ids -- it would see every active non-ref lab (D032)`)
    .not.toBe('');
  expect(labIds, `${login} _lab_ids must pin the same lab as _lab_id`).toBe(labId);
  expect(clinicId, `${login} has no _clinic_id`).not.toBe('');
  // WHICH BRANCH IS ACTUALLY CARRYING THE TEST. `_use_current_clinic_id > 0` is
  // the FIRST branch of both scope maps and would short-circuit everything below
  // it; `_only_one_clinic` is the other half of the `off_see_all` gate. Neither
  // is written by seed.php, so both are asserted absent -- otherwise a green run
  // could be proving a branch this file does not claim to test.
  expect(Number(useCurrent || 0), `${login} _use_current_clinic_id must be unset`).toBe(0);
  expect(onlyOne, `${login} _only_one_clinic is not written by seed.php`).toBe('0');
  return {
    userId,
    clinicId,
    tenant: { labId, prefix, login, statePath: path.join(STATE_DIR, `${login}.json`) },
  };
}

/**
 * The two `ci_pdf_data` flags that decide whether the clinic scope applies at
 * all. Both are falsy live, so `!off_see_all` is true and the branch runs.
 * Asserted rather than assumed: flipping either silently changes what these
 * tests mean, and this turns them red instead (the tst.spec.ts tripwire form).
 */
function assertScopeFlagsOff(): void {
  const flags = wpEval(
    '$d=get_option("ci_pdf_data");$o=["off_see_all"=>!empty($d["off_see_all"]),' +
      '"msm_can_show_all"=>!empty($d["msm_can_show_all"])];',
  ) as { off_see_all: boolean; msm_can_show_all: boolean };
  expect(flags.off_see_all, 'precondition: ci_pdf_data.off_see_all is off').toBe(false);
  expect(flags.msm_can_show_all, 'precondition: ci_pdf_data.msm_can_show_all is off').toBe(false);
}

/**
 * The `ci_clinic_manager` row `office_manager` scoping is derived from. The
 * baseline ships none (fixture gap 1), so the spec owns this one: it is keyed
 * to this file's user id and marker e-mail, and `office_manager` is the only
 * role in the app that reads this table for scoping.
 */
function setClinicManager(db: Db, m: Manager, clinicId: string | null): void {
  db.query(
    `DELETE FROM ci_clinic_manager
      WHERE ma_email = ${Db.q(OFC_MANAGER_EMAIL)} OR ma_user_ID = ${Number(m.userId)}`,
  );
  if (clinicId !== null) {
    db.query(
      `INSERT INTO ci_clinic_manager (ma_cli_ID, ma_manager, ma_email, ma_user_ID)
       VALUES (${Number(clinicId)}, 'E2E Officemgr', ${Db.q(OFC_MANAGER_EMAIL)}, ${Number(m.userId)})`,
    );
  }
  // The exact list the app will build: `ma_user_ID = <uid> OR ma_cli_ID = <_clinic_id>`.
  const scoped = db.query(
    `SELECT ma_cli_ID FROM ci_clinic_manager
      WHERE ma_user_ID = ${Number(m.userId)} OR ma_cli_ID = ${Db.q(m.clinicId)}`,
  ).map((r) => r[0]);
  expect(
    scoped,
    `ci_clinic_manager rows visible to ${m.userId}: this is the list the app interpolates ` +
      'into `and ba.cli_ID in ( ... )`',
  ).toEqual(clinicId === null ? [] : [clinicId]);
}

/**
 * `ci_clinic_manager` MUST be empty again when this file finishes.
 *
 * env/safety-gate.sh derives its person-shaped table list from
 * information_schema and BLOCKS on any unclassified table that holds rows --
 * measured: leaving one row behind fails `globalSetup` for every agent's next
 * run, not just this file's. The row is a test precondition, not an outcome, so
 * it is removed here rather than classified in the gate (which is shared).
 */
e2e.afterAll(async ({ db }) => {
  db.query(`DELETE FROM ci_clinic_manager WHERE ma_email = ${Db.q(OFC_MANAGER_EMAIL)}`);
  expect(
    db.scalar('SELECT COUNT(*) FROM ci_clinic_manager'),
    'ci_clinic_manager must be empty again or env/safety-gate.sh blocks the next run',
  ).toBe('0');
});

/**
 * A batch_id nobody else in the suite will use. `ci_batches.batch_id` is
 * int(10). Taken from the SHARED allocator (D396): the open-coded
 * `1e9 + (Date.now() % 1e9)` this replaced reduces to the same base band
 * fixtures/batch-id.ts uses, and was measured landing inside another spec's
 * worker block.
 */
const nextBatchId = batchIds('ofc');
const privateBatchId = () => String(nextBatchId());

/**
 * Collection days nobody else in this run will seed on (fixtures/batch-id.ts).
 *
 * The tox worklist GROUPS BY `date(ba.doc)` and every seeder in the suite posts
 * `bat[doc]='auto'`, i.e. today -- so every persona has always been shown one
 * day row and a clause that changes WHICH DAYS appear could not be observed.
 * Two specimens on two allocated days is what turns that screen into a scope
 * assertion.
 */
const nextSeedDay = seedDays();

/**
 * One tox requisition into a NAMED CLINIC, through the app's own saver.
 *
 * The seeding persona is `e2e_multisite`: `form_requisition` is held live by
 * `multi_site_manager` and NOT by `office_manager`, so the office manager
 * cannot create the rows whose visibility it is being tested on.
 */
async function seedForClinic(
  request: APIRequestContext, db: Db, m: Manager,
  patientLast: string, clinicId: string, docId: string, overrides: Record<string, string> = {},
) {
  const f = infra(db);
  const seeded = await seedRequisition(request, db, m.tenant, patientLast, {
    'bat[cli_ID]': clinicId,
    'bat[doc_ID]': docId,
    ...overrides,
  });
  // D226: never the HTTP status. expectBatch asserts the ROW, including
  // lab_id / lab / accession prefix against lab 19 (D172).
  const { assen, mid } = expectBatch(db, seeded, m.tenant, { testGroupId: f.testGroupId });
  expect(
    db.scalar(`SELECT cli_ID FROM ci_batches WHERE mid = ${Number(mid)}`),
    `ci_batches.cli_ID for ${seeded.patientName} -- the axis under test`,
  ).toBe(clinicId);
  return { assen, mid, patientName: seeded.patientName };
}

// ===========================================================================
// Capability + reachability guard -- which screens each persona actually gets
// ===========================================================================

e2e.describe('S-STA-06 @ofc @mutates', () => {
  e2e('both manager personas reach Test Results and are denied every results listing', async ({
    db, baseURL,
  }) => {
    // D255: `user_can()` reroutes through WordPress's deprecated user-level
    // handling and answers true off `level_0`, so it is unusable here. Literal
    // key lookup in `WP_User->allcaps`, read from the RUNNING instance (D178).
    const caps = wpEval(
      `$o=[];foreach(${JSON.stringify([OFC, MSM])} as $l){$u=get_user_by("login",$l);` +
        '$o[$l]=$u?array_values(array_keys(array_filter($u->allcaps))):null;}',
    ) as Record<string, string[] | null>;

    expect(caps[OFC], `${OFC} does not exist`).toBeTruthy();
    expect(caps[MSM], `${MSM} does not exist`).toBeTruthy();

    // office_manager: holds the Test Results screen, holds NEITHER results
    // capability, and -- against personas.md 3.9 -- does not hold
    // form_requisition or ci_received_list either.
    expect(caps[OFC]).toEqual(expect.arrayContaining(['ci_test_results', 'ci_patient_chart', 'form_blood', 'form_pcr']));
    for (const cap of ['ci_results', 'ci_results_blood', 'ci_resutls_pcr', 'form_requisition', 'ci_received_list']) {
      expect(caps[OFC], `${OFC} must NOT hold ${cap}`).not.toContain(cap);
    }
    // multi_site_manager: strictly wider -- it adds the ordering and receiving
    // screens, but still holds no results capability.
    expect(caps[MSM]).toEqual(expect.arrayContaining(['ci_test_results', 'ci_received_list', 'form_requisition']));
    for (const cap of ['ci_results', 'ci_results_blood', 'ci_resutls_pcr']) {
      expect(caps[MSM], `${MSM} must NOT hold ${cap}`).not.toContain(cap);
    }

    // The rendered outcome, not the status code (D185/D226): every one of these
    // answers HTTP 200 and the difference is entirely in the body.
    const expected: Record<string, Record<string, boolean>> = {
      // page slug -> login -> reachable?
      ci_test_results: { [OFC]: true, [MSM]: true },
      ci_patient_chart: { [OFC]: true, [MSM]: true },
      // Denied to BOTH, and for different reasons -- office_manager does not
      // hold the capability, while multi_site_manager does and still cannot get
      // in: `ci_received_list` is granted at install_roles.php:290,:311 but NO
      // add_page() anywhere registers that slug, so personas.md 3.9's "Received
      // list" screen does not exist in this build. A dead capability.
      ci_received_list: { [OFC]: false, [MSM]: false },
      tox_results: { [OFC]: false, [MSM]: false },
      blood_results: { [OFC]: false, [MSM]: false },
    };
    for (const login of [OFC, MSM]) {
      const ctx = await ctxFor(baseURL, login);
      try {
        for (const [page, want] of Object.entries(expected)) {
          const res = await ctx.get(`/wp-admin/admin.php?page=${page}`);
          expect(res.status(), `${login} on ${page} answers 200 either way`).toBe(200);
          const body = await res.text();
          expect(
            body.includes(DENIAL),
            `${login} on page=${page}: expected ${want[login] ? 'the screen' : 'the denial body'}`,
          ).toBe(!want[login]);
        }
      } finally {
        await ctx.dispose();
      }
    }
    // Not asserted from `db`, but read here so a schema change is loud.
    expect(manager(db, OFC).tenant.labId, 'office manager lab').not.toBe('');
  });

  // =========================================================================
  // S-STA-06 -- the office_manager / multi_site_manager branches of the scope
  // map, over TWO clinics with DISJOINT row sets
  // =========================================================================

  e2e('the scope map narrows the Test Results line list to the reader\'s clinics', async ({
    db, baseURL, runKey,
  }, testInfo) => {
    assertScopeFlagsOff();
    const f = infra(db);
    expect(f.clinicId, 'two distinct clinics are seeded (D204/D258)').not.toBe(f.clinic2Id);

    const ofc = manager(db, OFC);
    const msm = manager(db, MSM);
    expect(ofc.clinicId, 'the office manager is bound to clinic 1').toBe(f.clinicId);

    const today = appToday(db);
    const msmCtx = await ctxFor(baseURL, MSM);
    let one: { assen: string }, two: { assen: string };
    try {
      one = await seedForClinic(msmCtx, db, msm, `Ofcsyn${runKey}a`, f.clinicId, f.docId);
      two = await seedForClinic(msmCtx, db, msm, `Ofcsyn${runKey}b`, f.clinic2Id, f.doc2Id);
    } finally {
      await msmCtx.dispose();
    }
    expect(one.assen, 'the two seeded accessions must differ').not.toBe(two.assen);

    /**
     * The day drill-down. `tr_action` on a GET, dispatched from `admin_init`
     * (admin/init.php:54-65) -- this is the `tr_action=` listing fragment form
     * that tst.spec.ts S-TST-04/05 use, NOT a `trajax=1&act=` POST, so the
     * D166/D050 predicate does not apply here.
     */
    const lineList = async (login: string) => {
      const ctx = await ctxFor(baseURL, login);
      try {
        const res = await ctx.get(
          `/wp-admin/admin.php?page=ci_test_results&tr_action=ci_load_test_results&doc=${today}`,
        );
        expect(res.status(), `${login} drill-down answers 200`).toBe(200);
        const body = await res.text();
        expect(body, `${login} got the page-layer denial, not the line list`).not.toContain(DENIAL);
        return body;
      } finally {
        await ctx.dispose();
      }
    };

    // ---- THE D201 ANALOGUE, DEMONSTRATED BEFORE IT IS ROUTED AROUND.
    // With no ci_clinic_manager row, `_clinic_id=1` alone yields an EMPTY list,
    // the clause degrades to `and ba.cli_ID in (  )`, and the office manager
    // sees NOTHING -- including its own clinic. Asserting only "clinic 2 is not
    // visible" would pass here and prove nothing.
    setClinicManager(db, ofc, null);
    const starved = await lineList(OFC);
    expect(
      starved.includes(one.assen) || starved.includes(two.assen),
      'D201 analogue: with ci_clinic_manager empty the office manager sees NEITHER clinic -- ' +
        'the scope list is derived from that table, not from _clinic_id',
    ).toBe(false);

    // ---- THE REAL SCOPING. One row, for clinic 1 only.
    setClinicManager(db, ofc, ofc.clinicId);
    const scoped = await lineList(OFC);
    expect(scoped, `office manager must see its OWN clinic's accession ${one.assen}`)
      .toContain(one.assen);
    expect(scoped, `office manager must NOT see clinic ${f.clinic2Id}'s accession ${two.assen}`)
      .not.toContain(two.assen);

    // ---- multi_site_manager: always all of ITS clinics, and `_ms_clinic_ids`
    // is rebuilt from ci_clinic by the render itself, not seeded.
    const wide = await lineList(MSM);
    expect(wide, `multi-site manager sees clinic ${f.clinicId}`).toContain(one.assen);
    expect(wide, `multi-site manager sees clinic ${f.clinic2Id}`).toContain(two.assen);

    const msCliIds = db.scalar(
      `SELECT meta_value FROM wp_usermeta
        WHERE user_id = ${Number(msm.userId)} AND meta_key='_ms_clinic_ids'`,
    );
    expect(msCliIds, '_ms_clinic_ids was written by the GET (D034-class mutation-on-GET)')
      .not.toBe('');
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_clinic c
          WHERE c.cli_status = 1
            AND ${Db.q(msCliIds)} NOT LIKE CONCAT('%"', c.cID, '"%')`,
      ),
      '_ms_clinic_ids is DERIVED: the render rebuilds it from every active ci_clinic row',
    ).toBe('0');

    console.log(
      `S-STA-06 worker=${testInfo.workerIndex} lab=${ofc.tenant.prefix}(${ofc.tenant.labId}) ` +
        `clinic${f.clinicId}=${one.assen} clinic${f.clinic2Id}=${two.assen} ms_cli_ids=${msCliIds}`,
    );
  });
});

// ===========================================================================
// S-TST-05 -- the multi-clinic half: the SCOPE OF THE LISTING decides WHICH
// REPORTS GET RELEASED (10-scenarios.md Tenancy axis; D034/D225)
// ===========================================================================

e2e.describe('S-TST-05 @ofc @mutates', () => {
  e2e('a narrower clinic scope releases fewer reports on the same GET', async ({
    db, baseURL, runKey,
  }, testInfo) => {
    assertScopeFlagsOff();
    const f = infra(db);
    const ofc = manager(db, OFC);
    const msm = manager(db, MSM);
    setClinicManager(db, ofc, ofc.clinicId);

    // ci_load_results.php:68 narrows the whole listing to `ba.batch_id =
    // '<batch_id>'`, so a private batch_id keeps this render on this test's two
    // rows and off every other agent's (the same isolation tst.spec.ts uses).
    const batchId = privateBatchId();

    // Saved at batch_status='' ON PURPOSE. The tox SAVER itself creates a
    // ci_reports row at estatus='C' when the POST already carries a rejected
    // status -- measured -- which would destroy the precondition. The batches
    // are moved to 'rejected' afterwards, so the ONLY thing that can create a
    // report from here on is a render.
    const msmCtx = await ctxFor(baseURL, MSM);
    let one: { assen: string }, two: { assen: string };
    try {
      one = await seedForClinic(msmCtx, db, msm, `Ofcsyn${runKey}c`, f.clinicId, f.docId,
        { 'bat[batch_id]': batchId });
      two = await seedForClinic(msmCtx, db, msm, `Ofcsyn${runKey}d`, f.clinic2Id, f.doc2Id,
        { 'bat[batch_id]': batchId });
    } finally {
      await msmCtx.dispose();
    }

    db.query(`UPDATE ci_batches SET batch_status='rejected' WHERE batch_id = ${Number(batchId)}`);
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_batches
          WHERE batch_id = ${Number(batchId)} AND batch_status='rejected'`),
      'precondition: exactly the two seeded batches are in a rejected status under this batch_id',
    ).toBe('2');
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_reports
          WHERE patient_id IN (${Db.q(one.assen)}, ${Db.q(two.assen)})`),
      'precondition: NO ci_reports row exists for either accession yet',
    ).toBe('0');

    /**
     * THE GET. No approve click and no POST body. `page=ci_test_results` is the
     * screen these personas hold; `tr_action=ci_load_results` is dispatched
     * from `admin_init` before any page-capability check runs (D250: the action
     * dispatcher is not gated by the page), so the tox results fragment renders
     * -- under the CALLER'S clinic scope (ci_load_results.php:97-126).
     */
    const renderResults = async (login: string) => {
      const ctx = await ctxFor(baseURL, login);
      try {
        const res = await ctx.get(
          `/wp-admin/admin.php?page=ci_test_results&tr_action=ci_load_results&batch_id=${batchId}`,
        );
        expect(res.status(), `${login} results fragment answers 200`).toBe(200);
        const body = await res.text();
        expect(body, `${login} got the page-layer denial, not the fragment`).not.toContain(DENIAL);
        return body;
      } finally {
        await ctx.dispose();
      }
    };

    /** The one thing under test: which reports exist, and at what estatus. */
    const reports = (assen: string) =>
      db.query(
        `SELECT id, estatus, approve_date, batch_id, lab_id FROM ci_reports
          WHERE patient_id = ${Db.q(assen)}`,
      );

    // ---- office manager: one clinic. The GET releases ONE report.
    const narrow = await renderResults(OFC);
    expect(narrow, 'the office manager fragment carries its own clinic\'s accession')
      .toContain(one.assen);
    expect(narrow, 'the office manager fragment must not carry clinic 2\'s accession')
      .not.toContain(two.assen);

    const inScope = reports(one.assen);
    expect(inScope.length, 'the GET created exactly one ci_reports row for the in-scope clinic').toBe(1);
    const [reportId, estatus, approveDate, reportBatchId, reportLabId] = inScope[0];
    expect(estatus, 'ci_reports.estatus released by the GET alone (ci_load_results.php:424-430)').toBe('C');
    expect(approveDate, 'ci_reports.approve_date').not.toBe('');
    expect(reportBatchId, 'ci_reports.batch_id').toBe(batchId);
    expect(reportLabId, 'ci_reports.lab_id -- tenancy on a row the GET created (D172)')
      .toBe(ofc.tenant.labId);

    // THE COUPLING. Same GET, same batch_id, same rejected status -- clinic 2's
    // report was NOT created, purely because the reader's listing did not
    // include clinic 2. Scope of the listing == scope of the release.
    expect(
      reports(two.assen).length,
      `S-TST-05 coupling: the out-of-scope clinic's report must NOT exist after the ` +
        `office manager's render of ${two.assen}`,
    ).toBe(0);

    // ---- multi-site manager: all its clinics. The SAME GET now releases the
    // second report as well.
    const wide = await renderResults(MSM);
    expect(wide, 'the multi-site fragment carries clinic 1').toContain(one.assen);
    expect(wide, 'the multi-site fragment carries clinic 2').toContain(two.assen);

    const nowInScope = reports(two.assen);
    expect(
      nowInScope.length,
      'the broader listing created the second clinic\'s report on render',
    ).toBe(1);
    expect(nowInScope[0][1], 'and released it: ci_reports.estatus').toBe('C');
    expect(nowInScope[0][2], 'ci_reports.approve_date').not.toBe('');
    expect(nowInScope[0][4], 'ci_reports.lab_id of the second clinic\'s report').toBe(msm.tenant.labId);
    // The first report is not duplicated by the second render.
    expect(reports(one.assen).length, 'clinic 1 still has exactly one report row').toBe(1);

    console.log(
      `S-TST-05 worker=${testInfo.workerIndex} batch_id=${batchId} ` +
        `ofc-released=${one.assen}(report ${reportId}) msm-released=${two.assen}(report ${nowInScope[0][0]})`,
    );
  });
});

// ===========================================================================
// Unreachable, named rather than faked (D141)
// ===========================================================================

e2e.fixme(
  'S-STA-06 @ofc the reflab_manager branch of the scope map',
  // No `reflab_manager` user exists in the baseline: `wp_usermeta.wp_capabilities`
  // matches zero rows for that slug, and the Makefile's PERSONAS list mints none,
  // so there is no storageState to drive it with. The role itself IS live (4 caps:
  // read, level_1, level_0, ci_test_results, ci_patient_chart, ci_patient_trends,
  // ci_abx_report), and it falls into `trpdd_get_batch_conditions()`'s
  // no-branch default -- i.e. NO clinic clause, so it would see BOTH clinics.
  // Fixture gap: add a `reflab_manager` persona to env/seed.php + the Makefile.
  async () => {},
);

e2e.fixme(
  'S-STA-06 @ofc the off_see_all / _only_one_clinic branch',
  // `ci_pdf_data.off_see_all` is a NETWORK-WIDE option: turning it on widens
  // every office_manager and multi_site_manager on the install at once, and
  // `_only_one_clinic` then narrows an individual back. Flipping a global option
  // mid-run is exactly the global-state mutation the README routes to
  // specs/serial.spec.ts (@serial, workers:1) -- this file runs in `main`,
  // concurrently with five other agents, and must not touch it.
  async () => {},
);

// ===========================================================================
// WAVE 5 -- the two manager seats AS THEY ARE ACTUALLY USED.
//
// D382: nothing above is deleted. What is added is the half no `.text()`
// assertion in this file could reach.
//
// CORRECTION, WAVE 8 -- "no UI caller" was measured WITHOUT `&tab=`, and the
// paragraph below is true only of the bare URL. `admin_ci_test_results.php:35-43`
// drops every tab whose `_<btype>` meta is falsy for `office_manager`, so
// `key($tabs)` is empty and the bare screen renders blank -- but `:62` reads
// `$_REQUEST['tab']` BEFORE falling back to `key($tabs)`, and `:102-104`
// includes `parts/tox/test_results.php` on that value alone. The strip hides
// the link; it does not gate the screen (D250, and doc.spec.ts:1120-1130 is the
// same finding for `doctor`). `page=ci_test_results&tab=tox` therefore hands
// this persona a full, working, CLINIC-SCOPED tox worklist -- which is the
// reachable driver of `trpdd_get_batch_conditions()`'s office_manager clause
// (`inc/functions.php:5206-5219`), a DIFFERENT copy of the scope map from the
// inline one `ci_load_test_results.php` carries. The last test in this describe
// drives it. Nothing below is deleted (D382): the blank-screen fact is still
// true of the URL the menu actually links to, and is still asserted.
//
// WHAT IS DELIBERATELY *NOT* CONVERTED, and why (the probe's budget rule):
// the scope map's OFFICE-MANAGER branch has NO UI CALLER. Measured on this
// stack 2026-08-25: `page=ci_test_results` renders this persona an EMPTY
// `<h2 class="nav-tab-wrapper"></h2>` and nothing else -- no tab, no search
// box, no table -- so there is no control anywhere in the product that fires
// `tr_action=ci_load_test_results` or `ci_load_results` for it. A "UI twin" of
// S-STA-06's scope map or of S-TST-05 would be `page.goto(<the same URL the
// HTTP test builds by hand>)`: the identical request with a browser attached,
// ~9-14 s of CI each (probe figures for these two tests) and zero detection
// power over the HTTP tests that already make those claims. They stay HTTP,
// and the SCREEN that has no caller is asserted directly below instead --
// which is the fact that was missing, not the fragment.
// ===========================================================================

e2e.describe('S-STA-06u @ofc', () => {
  /**
   * The rendered gate for both seats, and the discovery it turned up.
   *
   * `pages/admin_ci_test_results.php:34-42` (pinned a7aa074d) builds the tab
   * list from every btype whose report table has rows, then -- for
   * `office_manager` and `clinic` ONLY -- drops every tab whose `_<btype>` user
   * meta is falsy. `multi_site_manager` falls into the empty `else if` at :43
   * and is never filtered. NEITHER seeded persona carries `_tox` (asserted
   * below), so the same missing meta produces a working screen for one role and
   * a blank one for the other. That is the whole test: not "the office manager
   * sees fewer rows" but "the office manager, holding ci_test_results and
   * offered the menu entry, is handed a page with nothing on it".
   *
   * FIXTURE GAP, reported not patched (env/seed.php is shared by six agents):
   * `update_user_meta($ofc, '_tox', 1)` is what makes this seat usable. When it
   * lands, this test reds and names the reason rather than going vacuous.
   *
   * NAVIGATION BUDGET: three per persona -- the menu, one denied listing, and
   * the screen they hold. The other two denied listings the HTTP guard above
   * covers (`blood_results`, `ci_received_list`) are the SAME fuse-bounded
   * shape as `tox_results` and would buy nothing per ~0.5 s spent, so they stay
   * HTTP-only.
   */
  e2e('the menu offers both managers Test Results, and only one of them gets a screen', async ({
    pageAs, db,
  }) => {
    for (const login of [OFC, MSM]) {
      expect(
        db.scalar(
          `SELECT COUNT(*) FROM wp_usermeta m JOIN wp_users u ON u.ID = m.user_id
            WHERE u.user_login = ${Db.q(login)} AND m.meta_key = '_tox'`,
        ),
        `precondition: ${login} carries no _tox meta -- the tab filter's input`,
      ).toBe('0');
    }

    for (const login of [OFC, MSM]) {
      const page = await pageAs(login);

      // FACT ONE -- THE MENU IS THE GATE (D250). Both seats are OFFERED Test
      // Results and Patient Chart, and neither is offered a results listing:
      // the capability guard above proves they hold no `ci_results*`, this
      // proves the app never dangles the screen in front of them either.
      await page.goto('/wp-admin/');
      const menu = page.locator('#adminmenu');
      for (const [label, offered] of [
        ['Test Results', true], ['Patient Chart', true],
        ['Tox Results', false], ['Blood Results', false],
      ] as const) {
        await expect(
          menu.getByRole('link', { name: label, exact: true }),
          `${login} ${offered ? 'is offered' : 'is never offered'} "${label}" in the admin menu`,
        ).toHaveCount(offered ? 1 : 0);
      }

      // FACT TWO -- typing a listing URL anyway. D250's whole point, and the
      // only leg that separates "unlisted" from "denied".
      await page.goto('/wp-admin/admin.php?page=tox_results');
      await expect(
        page.getByText(DENIAL),
        `${login} typing the tox results URL is stopped at the page layer`,
        // D392: the denial page bounces to page=welcome on a 3 s fuse, and the
        // suite's 5 s expect timeout outlives it.
      ).toHaveCount(1, { timeout: BEFORE_BOUNCE_MS });
      await expect(
        page.locator('#adminmenu'),
        `${login} lands on a bare wp_die() page with no navigation`,
      ).toHaveCount(0, { timeout: BEFORE_BOUNCE_MS });

      // FACT THREE -- WHAT THE SCREEN THEY DO HOLD ACTUALLY CONTAINS. Reaching
      // it is not using it: `!body.includes(DENIAL)` is satisfied by a page
      // with nothing on it, which is exactly what one of these two gets.
      const usable = login === MSM;
      await page.goto('/wp-admin/admin.php?page=ci_test_results');
      const body = page.locator('#wpbody-content');
      await expect(
        body.getByRole('link', { name: 'Tox Results', exact: true }),
        `${login} ${usable ? 'gets' : 'is given no'} Tox Results tab ` +
          '(admin_ci_test_results.php:34-42 drops it for office_manager without _tox meta)',
      ).toHaveCount(usable ? 1 : 0);
      await expect(
        body.locator('input[name="s"]'),
        `${login} ${usable ? 'can search' : 'has no way to search'} for a patient or accession`,
      ).toHaveCount(usable ? 1 : 0);
      await expect(
        body.getByRole('columnheader', { name: 'Accession', exact: true }),
        `${login} ${usable ? 'gets the results worklist' : 'gets no worklist at all'}`,
      ).toHaveCount(usable ? 1 : 0);
    }
  });

  /**
   * S-STA-06u -- the ONE branch of the scope map that has a real UI caller, and
   * the only browser-driven read of it anywhere in the suite.
   *
   * The HTTP tests above build `tr_action=ci_load_test_results&doc=<today>` by
   * hand. A multi-site manager never does: it opens Test Results, types into
   * the search box, and clicks a DAY ROW to expand it. That path runs the
   * search form and `js/admin.js`'s accordion -- neither of which any test in
   * this suite touches, and `js/admin.js` is on the plan's "not byte-identical
   * at the pin" list (C4). So this is added coverage, not a re-spelling.
   *
   * D386, AND WHY THIS ONE IS ALLOWED TO ASSERT POST-XHR DOM. D386 bans
   * believing the DOM after a WRITE, because `js/admin.js` authors the
   * Approve/Reverse text itself on any HTTP 200 and so survives a write that
   * did nothing. This test asserts a READ.
   *
   * THE HANDLER, measured rather than assumed -- an earlier draft credited
   * `js/admin_accordion.js` (`trajax=1&act=load_table_child`), which is a
   * different control on a different screen and is NOT what runs here. Clicking
   * `tr.parent` runs `trcpp_load_results()`, inlined in
   * `parts/tox/test_results.php:272-300`, and the request measured on the wire
   * is `POST admin.php?page=ci_test_results` with
   * `tr_action=ci_load_test_results&doc=<day>`. The response is server-authored
   * HTML dropped into the day cell verbatim (`$obj.html($html)`), the client
   * composing nothing.
   *
   * IT IS NOT A DATA WRITE, but it is not inert either:
   * `inc/actions/ci_load_test_results.php` holds no `wpdb->insert/update`, and
   * `ci_reports` measured 1 -> 1 across the container GET, the day POST and a
   * `tr_action=ci_load_results` -- so the D034/D225 release-on-render hazard
   * does not fire over this manager's all-18-clinic scope. The one write on the
   * path is `:116`, which CACHES the manager's clinic list into
   * `_ms_clinic_ids` when that meta is empty. The HTTP tests above already POST
   * the same `tr_action` for the same persona, so this navigation adds no
   * exposure the file did not have -- but a future test that narrows the
   * manager's clinics must clear that meta or the cache will outlive it.
   *
   * A fault injection that narrows the manager's clinic scope reds the row
   * assertion -- measured, not argued.
   *
   * D397 -- NOTHING HERE COMPARES A VALUE TO ITSELF. The accession IS typed
   * into the search box, so the row is NOT identified by it echoing back;
   * identification is the patient name (never typed) and the View link's
   * `id=<mid>`, an AUTO_INCREMENT this test reads out of ci_batches and could
   * not have put on the page.
   */
  e2e('the multi-site manager finds its own specimen by searching and expanding the day', async ({
    pageAs, db, baseURL, runKey,
  }, testInfo) => {
    assertScopeFlagsOff();
    const f = infra(db);
    const msm = manager(db, MSM);

    const msmCtx = await ctxFor(baseURL, MSM);
    let one: { assen: string; mid: string; patientName: string };
    try {
      one = await seedForClinic(msmCtx, db, msm, `Ofcsyn${runKey}ue`, f.clinicId, f.docId);
    } finally {
      await msmCtx.dispose();
    }

    const page = await pageAs(MSM);
    await page.goto('/wp-admin/admin.php?page=ci_test_results');
    // D383 fallback 3: this screen carries no label[for], and `name="s"` is
    // what the manager's own browser submits.
    await page.locator('input[name="s"]').fill(one.assen);
    await page.getByRole('button', { name: 'Search', exact: true }).click();

    // THE SEARCH NARROWED. Without this the next assertion would pass on a
    // search box that ignores `s` entirely: the specimen would still be found,
    // just under one of several day rows. The stack always carries batches on
    // more than one date (the HTTP tests above seed on today, the baseline on
    // earlier days), so an ignored term yields more than one parent.
    await expect(
      page.locator('tr.parent'),
      'searching an accession leaves exactly one day group on the worklist',
    ).toHaveCount(1);

    // ...and that day is the APP'S OWN calendar day (D390). appToday() derives
    // it from wp_options.timezone_string; `data-p` is PHP's date_i18n() on the
    // batch this test has just created. Two independent derivations -- the four
    // hours a day they used to disagree is what D390 fixed.
    const day = page.locator(`tr.parent[data-p="doc_${appToday(db).replace(/-/g, '')}"]`);
    await expect(
      day, "the requisition lands on today's date group, in the app's own timezone",
    ).toHaveCount(1);

    // THE DRILL-DOWN -- the accordion, which is the only way a manager reaches
    // the line list. `tr.parent` carries no role and no accessible name
    // (D383 fallback 4).
    await day.click();
    const row = page.locator('tr.childp').filter({ hasText: one.assen });
    await expect(row, 'the specimen appears under the day it was collected').toHaveCount(1);
    await expect(
      row.getByRole('cell', { name: one.patientName, exact: true }),
      'the manager can read the patient off the line -- a name this test never typed',
    ).toBeVisible();
    await expect(
      row.getByRole('link', { name: 'View', exact: true }),
      'and open the requisition: the View link addresses the ci_batches row by its own mid, ' +
        'which no echo of the search term could produce',
    ).toHaveAttribute('href', new RegExp(`[?&]id=${one.mid}(&|$)`));

    // THE DATABASE, kept: seedForClinic already asserted the row through
    // expectBatch (lab_id / lab / accession prefix, D172) and its cli_ID. The
    // clinic the manager just read off the screen is the clinic on the row.
    expect(
      db.scalar(`SELECT cli_ID FROM ci_batches WHERE mid = ${Number(one.mid)}`),
      'the specimen the multi-site manager can see belongs to one of its clinics',
    ).toBe(f.clinicId);

    console.log(
      `S-STA-06u worker=${testInfo.workerIndex} lab=${msm.tenant.prefix}(${msm.tenant.labId}) ` +
        `mid=${one.mid} assen=${one.assen}`,
    );
  });

  /**
   * THE SHARED SCOPE BUILDER'S OFFICE-MANAGER CLAUSE -- a second copy of the
   * rule, on a second screen, and until now driven by nothing.
   *
   * There are TWO office-manager clinic clauses in this application and this
   * file only ever drove one of them. `inc/actions/ci_load_test_results.php:93-101`
   * carries an inline copy -- that is what S-STA-06's `tr_action=` line list
   * above exercises. `trpdd_get_batch_conditions()` (`inc/functions.php:5206-5219`)
   * carries the OTHER, and it is the one that feeds eleven-plus screens: the
   * tox/pcr/blood worklists, the requisition lists, the ABX report and the robot
   * exports all take `$conditions['where']` from it. Deleting its
   * `and ba.cli_ID in ( <cli_ids> )` changed no test in the suite.
   *
   * ITS REACHABLE DRIVER IS THIS SCREEN. `parts/tox/test_results.php:64-66`
   * calls `trpdd_get_batch_conditions(0)` for every role that is not
   * administrator / labmanager / lab_director, and the office manager reaches
   * that file by URL -- see the WAVE 8 correction above the describe.
   *
   * WHY TWO DAYS AND NOT TWO ROWS. The screen is a DAY LIST: it groups by
   * `date(ba.doc)` and renders one row per day (`:112-121`). Two specimens on
   * the same day produce one row whichever clinics the reader manages, so the
   * clinic clause is invisible. Days come from `seedDays()` so two tests cannot
   * land on one, and `s=` narrows the list to this test's own two patients --
   * without it a day row could be kept alive by somebody else's specimen and the
   * absence assertion would accuse the application of a leak it did not have.
   *
   * PRESENCE AND ABSENCE IN BOTH LEGS, which is what makes this a scope
   * assertion rather than an empty-screen assertion (D277). The same reader, the
   * same URL, the same two specimens: move the `ci_clinic_manager` row from one
   * clinic to the other and the two day rows SWAP. A render that returned
   * nothing -- the `and ba.cli_ID in (  )` syntax error this file demonstrates
   * at :402-408 -- fails the presence half of both legs.
   */
  e2e('the office manager tox worklist carries the days of the clinic it manages and no other', async ({
    pageAs, db, baseURL, runKey,
  }, testInfo) => {
    assertScopeFlagsOff();
    const f = infra(db);
    const ofc = manager(db, OFC);
    const msm = manager(db, MSM);
    expect(f.clinicId, 'two distinct clinics are seeded (D204/D258)').not.toBe(f.clinic2Id);
    expect(ofc.tenant.labId, 'both managers read the same lab, so the lab clause cannot separate them')
      .toBe(msm.tenant.labId);

    // Shared prefix, so `s=` selects exactly these two and nothing else in the
    // database. `seedForClinic` appends ', Synthetic' to each surname and the
    // screen matches `patient_name LIKE '%<s>%'` (test_results.php:21).
    const key = `Ofcsyn${runKey}n`;
    const stampA = nextSeedDay();
    const stampB = nextSeedDay();
    const dayA = stampA.slice(0, 10);
    const dayB = stampB.slice(0, 10);
    expect(dayB, 'the two specimens must sit on DIFFERENT day groups').not.toBe(dayA);

    const msmCtx = await ctxFor(baseURL, MSM);
    let one: { assen: string }, two: { assen: string };
    try {
      one = await seedForClinic(msmCtx, db, msm, `${key}a`, f.clinicId, f.docId,
        { 'bat[doc]': stampA });
      two = await seedForClinic(msmCtx, db, msm, `${key}b`, f.clinic2Id, f.doc2Id,
        { 'bat[doc]': stampB });
    } finally {
      await msmCtx.dispose();
    }

    // `completed` is what admits a specimen to this worklist at all
    // (test_results.php:70), and it is where the bench leaves a specimen, not
    // where the requisition saver does -- the same reason S-TST-05 above moves
    // its batches AFTER seeding rather than posting the status on the form.
    db.query(
      `UPDATE ci_batches SET batch_status='completed'
        WHERE assen IN (${Db.q(one.assen)}, ${Db.q(two.assen)})`);

    // The day IS the row identity on this screen, so it is read back off the
    // row rather than assumed from the value posted (the saver rewrites
    // `bat[doc]` whenever it arrives empty or as `auto`).
    for (const [assen, day, clinic] of [
      [one.assen, dayA, f.clinicId], [two.assen, dayB, f.clinic2Id],
    ] as const) {
      expect(
        db.query(
          `SELECT DATE(doc), cli_ID, batch_status FROM ci_batches WHERE assen = ${Db.q(assen)}`),
        `${assen} was collected on ${day} for clinic ${clinic} and is completed work`,
      ).toEqual([[day, clinic, 'completed']]);
    }

    const page = await pageAs(OFC);
    /**
     * The worklist as the office manager gets it, re-fetched from the server
     * each time (D386 -- nothing here is client-authored, and `goto` is the
     * fresh read). `nocache=1` skips the 16 400 s Tr_Cache_Query on the day
     * COUNT (`test_results.php:98-101`), which carries no per-run key.
     */
    const worklist = () => page.goto(
      `/wp-admin/admin.php?page=ci_test_results&tab=tox&nocache=1&s=${key}`);
    const dayRow = (day: string) => page.getByRole('cell', { name: day, exact: true });

    // ---- LEG ONE: the manager of clinic 1.
    setClinicManager(db, ofc, f.clinicId);
    await worklist();
    await expect(
      dayRow(dayA),
      `the office manager manages clinic ${f.clinicId}, so ${one.assen}'s collection day `
      + `${dayA} is on their worklist`,
    ).toHaveCount(1);
    await expect(
      dayRow(dayB),
      `${dayB} holds only ${two.assen}, clinic ${f.clinic2Id}'s work, which this office `
      + 'manager does not manage (inc/functions.php:5218)',
    ).toHaveCount(0);

    // ---- LEG TWO: the SAME reader, the SAME URL, the SAME two specimens --
    // only the clinic they manage changes, and the worklist swaps with it.
    setClinicManager(db, ofc, f.clinic2Id);
    await worklist();
    await expect(
      dayRow(dayB),
      `moved to clinic ${f.clinic2Id}, the office manager now sees ${two.assen}'s day ${dayB}`,
    ).toHaveCount(1);
    await expect(
      dayRow(dayA),
      `and ${dayA} -- clinic ${f.clinicId}'s work -- has left their worklist`,
    ).toHaveCount(0);

    console.log(
      `S-STA-06u-shared worker=${testInfo.workerIndex} lab=${ofc.tenant.prefix}(${ofc.tenant.labId}) `
      + `clinic${f.clinicId}=${one.assen}@${dayA} clinic${f.clinic2Id}=${two.assen}@${dayB}`,
    );
  });
});
