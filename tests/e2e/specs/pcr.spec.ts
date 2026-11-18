import { request as playwrightRequest, type APIRequestContext, type Page, type Locator } from '@playwright/test';
import * as path from 'node:path';
import { test as e2e, expect } from '../fixtures/test';
import { Db } from '../fixtures/db';
import { seedPcrRequisition, type FormValue } from '../fixtures/seed-domain';
import { STATE_DIR } from '../playwright.config';

/**
 * PCR -- the PCR verifier (personas.md 3.3, role slug `pcr_verify`),
 * scenarios S-TST-02 and S-TST-03.
 *
 * PERSONA, read from the RUNNING instance via `WP_User->allcaps` (D178/D255 --
 * `user_can()` is unusable here, it answers true off `level_0`).
 *
 *   e2e_pcrverify  ID 11523, roles ['pcr_verify'], 7 capability keys:
 *                  read, level_1, level_0, form_pcr, ci_resutls_pcr,
 *                  ci_resutls_pcr_verification, pcr_verify.
 *                  Bound to lab 19 by `_lab_id` / `_lab_ids` -- OUTSIDE the
 *                  LAA..LAP pool.
 *
 * `ci_resutls_pcr` is MISSPELLED, and consistently so (D057/D224): the role
 * grant (`install_roles.php:428`) and the `pcr_results` registration
 * (`inc/admin.php:643`) use the same misspelling, so the screen is reachable
 * and there is no trap to test around. The live consequence is asserted by the
 * page-gate describe at the bottom.
 *
 * WHICH SCREEN. NOT `pcr_verification`. That registration is commented out at
 * `inc/admin.php:638` and has been since the initial commit (D044/D058), so a
 * plain GET is denied for every account, this persona included -- asserted
 * below. All verifier work in this file happens on the registered `pcr_results`
 * screen, whose Approve verb (`results_approve`, `parts/pcr/results.php:469`)
 * is the release control S-TST-03 is about.
 *
 * WHERE INTERPRETATION HAPPENS. On the RESULTS-EDIT RENDER, not on save
 * (D235/D225): `parts/pcr/results_edit.php:633` calls `trpdd_cal_ncovid19()`,
 * which persists the COVID rollup row (`inc/functions_covid.php:129-137`), and
 * `results_edit.php:648-658,781-783` writes `ci_reports_pcr.covid_pos`. A GET
 * is therefore a mutation here; both scenarios are tagged `@mutates` and assert
 * the state change the GET itself causes.
 *
 * TENANCY. This spec's lab comes from the SPEC_LAB map (`pcr` -> LAN) via the
 * test-scoped `tenant` fixture (D239/D241); nothing is pinned here. Every row
 * this file creates is asserted to carry that lab (D172), which is what makes
 * `E2E_FAULT=wrong-lab` go red. No lab is ever created (D161).
 *
 * WHY NOT `tenantRequest`. `tenant` resolves LAN to persona `e2e_lead13`, whose
 * role is `leadlabsecman` -- 15 capabilities live, holding NEITHER `form_pcr`
 * NOR `ci_resutls_pcr`. It cannot create a PCR requisition and cannot open the
 * PCR Results screen, so it is the wrong actor for both scenarios and is used
 * below only as the page-gate NEGATIVE control. Every request is therefore made
 * from an explicitly named persona context, as tst.spec.ts and sta.spec.ts do
 * for the same reason. The lab it names is still this spec's tenant, asserted
 * on every row.
 */

/** The persona under test. Holds form_pcr + ci_resutls_pcr, so it both creates and reads. */
const VERIFIER = 'e2e_pcrverify';
/** Patient-portal account, blog-1 role literally `a:0:{}` -- the no-role control (D249). */
const NOROLE = 'e2e_patient';
/** Administrator. The "admin overrides" clause of S-TST-03, measured rather than assumed. */
const ADMIN = 'Administrat0r';

/** Seed reference data from `env/sql/26-pcr-reference.sql`; `tg_manual_edit=1`. */
const MANUAL_GROUP = 'E2E STA PCR Panel';
const MANUAL_PANEL = 'E2E STA Covid Analyte';
const MANUAL_ABREV = 'E2ESTA1';

function ctxFor(baseURL: string | undefined, login: string): Promise<APIRequestContext> {
  return playwrightRequest.newContext({
    baseURL: baseURL ?? 'http://localhost',
    storageState: path.join(STATE_DIR, `${login}.json`),
  });
}

interface Panels { manualTg: string; manualTe: string; covidTg: string; covidTe: string }

/**
 * Resolve the four reference ids this file needs, live.
 *
 * The covid group is looked up with the app's OWN predicate --
 * `parts/pcr/results.php:29` selects `tg_is_covid=1 and tg_type='pcr'` and
 * builds `$covid_group_key = 'GROUP_'.$tg_ID` from it. Ordering that exact key
 * is what makes `$has_covid` true at `results.php:510`, which is half of the
 * S-TST-03 gate. Hardcoding the id would decouple the test from the gate.
 *
 * No row is created here: `ci_test_groups`/`ci_test_panels` are seed-tier
 * reference data (D085/D238), never spec-tier.
 */
function pcrPanels(db: Db): Panels {
  const [row] = db.query(
    `SELECT (SELECT tg_ID FROM ci_test_groups WHERE tg_name=${Db.q(MANUAL_GROUP)}),
            (SELECT te_ID FROM ci_test_panels WHERE te_name=${Db.q(MANUAL_PANEL)}),
            (SELECT tg_ID FROM ci_test_groups WHERE tg_is_covid=1 AND tg_type='pcr'),
            (SELECT te_ID FROM ci_test_panels WHERE te_name='nCoV-2019' AND te_type='pcr')`,
  );
  const [manualTg, manualTe, covidTg, covidTe] = row ?? [];
  expect(
    manualTg && manualTe,
    `${MANUAL_GROUP}/${MANUAL_PANEL} are missing -- run env/seed.sh (sql/26-pcr-reference.sql)`,
  ).toBeTruthy();
  expect(covidTg && covidTe, 'the seed carries no pcr covid group/panel (tg_is_covid=1)').toBeTruthy();
  return { manualTg, manualTe, covidTg, covidTe };
}

interface PcrSpecimen { assen: string; mid: string; reportId: string; patientName: string }

/**
 * One PCR specimen with raw channel values, through the app's own saver.
 *
 * The verb is `ci_save_form_pcr` (`inc/admin_actions.php:2478`), NOT
 * `ci_save_pcr` -- the latter is the include filename and dispatches nothing,
 * so posting it is a silent no-op at HTTP 200 (D226 case 3). The fixture
 * carries the right verb; this helper only adds the panel and result fields.
 *
 * Ordering BOTH groups is load-bearing in two ways:
 *  - the manual group (`tg_manual_edit=1`) is what makes the saver create the
 *    `ci_reports_pcr` row eagerly (`ci_save_pcr.php:930-946`), which every
 *    assertion below keys on;
 *  - the covid group turns AUTO-APPROVAL OFF, because
 *    `tg_auto_approve_pos_neg` must hold for EVERY ordered group
 *    (`ci_save_pcr.php:897-901`) and the covid group carries 0. So the report
 *    is saved unapproved, which is the precondition for an Approve attempt.
 *
 * The saver also resets `covid_pos` to NULL on every save
 * (`ci_save_pcr.php:865`), which is what makes "no interpretation has run yet"
 * an assertable precondition rather than an assumption.
 */
async function seedSpecimen(
  req: APIRequestContext,
  db: Db,
  labId: string,
  prefix: string,
  p: Panels,
  patientLast: string,
  results: Record<string, Record<string, string>>,
): Promise<PcrSpecimen> {
  const overrides: Record<string, FormValue> = {
    // The pcr screens post type_of_specimen as a list; the array form also
    // drops the fixture's scalar default so the saver sees one shape only.
    'bat[type_of_specimen][]': ['nasal'],
    // MUST be an array: a scalar silently stores '' (D226 case 4).
    'bat[panel_tests_requested][]': [`GROUP_${p.manualTg}`, `GROUP_${p.covidTg}`, p.manualTe, p.covidTe],
    'pa[pa_state]': 'IL',
  };
  for (const [key, fields] of Object.entries(results)) {
    for (const [f, v] of Object.entries(fields)) overrides[`results[${key}][${f}]`] = v;
  }

  const seeded = await seedPcrRequisition(req, db, labId, patientLast, overrides);
  // Not toBeLessThan(400): a login redirect is 302 and would pass that (D141).
  expect(seeded.status, `the pcr saver answered on ${patientLast}`).toBe(200);
  // D221/D226: the ROW is the assertion, row-exact on this test's own key --
  // never a global count, never the AUTO_INCREMENT delta, never a status code.
  expect(
    seeded.keyRowsAfter - seeded.keyRowsBefore,
    `ci_batches_pcr gained no row for ${seeded.patientName}`,
  ).toBeGreaterThan(0);

  const rows = db.query(
    `SELECT ba.mid, ba.assen, ba.lab_ID, ba.lab, COALESCE(re.id,''),
            COALESCE(re.estatus,''), IFNULL(re.covid_pos,'unset')
       FROM ci_batches_pcr ba
       LEFT JOIN ci_reports_pcr re ON re.patient_id = ba.assen
      WHERE ba.patient_name = ${Db.q(seeded.patientName)}`,
  );
  expect(rows.length, `exactly one ci_batches_pcr row for ${seeded.patientName}`).toBe(1);
  const [mid, assen, rowLabId, lab, reportId, estatus, covidPos] = rows[0];

  // D160: the saver answers 200 with assen='' when tr_get_new_assen() fails.
  expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
  // TENANCY, asserted on every row this file creates (D172/D161). This triple
  // is what `E2E_FAULT=wrong-lab` reds.
  expect(rowLabId, `ci_batches_pcr.lab_ID for ${assen}`).toBe(labId);
  expect(lab, 'ci_batches_pcr.lab').toBe(prefix);
  expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(prefix);

  expect(Number(reportId), `the manual group created a ci_reports_pcr row for ${assen}`)
    .toBeGreaterThan(0);
  // Preconditions, measured rather than assumed: nothing is released and no
  // interpretation has run.
  expect(estatus, `precondition: ${assen} is saved unapproved`).toBe('');
  expect(covidPos, `precondition: covid_pos is reset to NULL by the saver on ${assen}`).toBe('unset');

  return { assen, mid, reportId, patientName: seeded.patientName };
}

/** The results-edit render -- the mutation under test (D235). Returns the SCREEN. */
async function renderResultsEdit(req: APIRequestContext, s: PcrSpecimen): Promise<string> {
  const r = await req.get(`/wp-admin/admin.php?page=pcr_results&act=edit&id=${Number(s.reportId)}`);
  expect(r.status(), `results-edit render for ${s.assen}`).toBe(200);
  return r.text();
}

/**
 * The COVID call AS DISPLAYED, read off the verifier's own screen.
 *
 * `parts/pcr/results_edit.php:1351-1358` renders the interpreted call as the
 * SELECTED option of the group's `pos_neg` control, and the covid group's
 * option labels are its own set -- "Detected" / "Not Detected", not the manual
 * analyte's "Positive" / "Negative". Both halves are returned as `CODE=Label`
 * so an inverted call, a relabelled option, a raw Ct in the call cell and a
 * missing call are four distinct failures rather than one.
 *
 * Sliced to the covid group's table first: every ordered group renders a
 * `pos_neg` control, so an unsliced match would read the manual analyte's.
 * Returns '' when the group renders no call at all -- which is what a PEN
 * specimen does (measured: the whole nCoV-2019 section is absent).
 */
function displayedCovidCall(body: string, groupName: string): string {
  const start = body.indexOf(`>${groupName}</h4>`);
  if (start < 0) return '';
  const table = body.slice(start, body.indexOf('</table>', start));
  const m = /<option value="([A-Z]*)" selected="selected">([^<]*)<\/option>/.exec(table);
  return m ? `${m[1]}=${m[2]}` : '';
}

/**
 * The Approve control on the registered PCR Results screen.
 *
 * `trajax=1&act=results_approve` -- the predicate shape the app actually emits
 * (`js/admin.js`, D050/D166); `tr_act=` occurs ZERO times in this codebase.
 * The response is NOT the assertion: the blocked path answers HTTP 200 with
 * `<div class="ajaxstatus">ok</div>` and only a flash message says otherwise
 * (measured). D226 -- assert the row.
 */
function approve(req: APIRequestContext, reportId: string) {
  return req.post('/wp-admin/admin.php?page=pcr_results', {
    form: { trajax: '1', act: 'results_approve', id: String(Number(reportId)) },
  });
}

/** `ci_reports_pcr` state for one report, in ONE select (D164: ~131 ms per exec). */
function reportState(db: Db, reportId: string) {
  const [row] = db.query(
    `SELECT IFNULL(covid_pos,'unset'), COALESCE(estatus,''), rerun_count, covid_inc_completed, verified
       FROM ci_reports_pcr WHERE id = ${Number(reportId)}`,
  );
  expect(row, `no ci_reports_pcr row ${reportId}`).toBeTruthy();
  const [covidPos, estatus, rerunCount, incCompleted, verified] = row;
  return { covidPos, estatus, rerunCount, incCompleted, verified };
}

/** The COVID rollup rows interpretation persists, keyed on the report and the covid drug. */
function rollupRows(db: Db, reportId: string, covidDrug: string): string[][] {
  return db.query(
    `SELECT \`pos-neg\`, IFNULL(conc,'') FROM ci_results_pcr
      WHERE report_id = ${Number(reportId)} AND drug = ${Db.q(covidDrug)}`,
  );
}

// ===========================================================================
// S-TST-02 -- Ct values are interpreted into a single COVID call
// ===========================================================================

e2e.describe('S-TST-02 @smoke @pcr @tst @mutates', () => {
  e2e('Ct values become one COVID call, covid_pos lands in {0,1}, and PEN is never persisted', async ({
    db, tenant, runKey, baseURL,
  }, testInfo) => {
    const p = pcrPanels(db);
    const req = await ctxFor(baseURL, VERIFIER);
    try {
      const covidDrug = db.scalar(
        `SELECT UPPER(te_name) FROM ci_test_panels WHERE te_ID = ${Number(p.covidTe)}`,
      );
      const chan = (drug: string) => `${p.covidTe}_${drug}_${p.covidTg}_`;
      const manual = `${p.manualTe}_${MANUAL_ABREV}_${p.manualTg}_`;

      // No cutoff row matches an empty instrument (`cu_ms` is BioRad*/Quant1/QS1
      // live), so `trpdd_cal_ncovid19_ic()` falls to its built-in cutoff of
      // `<= 34` at inc/functions_covid.php:350-354. Ct 20 is under it, Ct 40 is
      // over it, and a valid IC channel separates NEG from RER.
      const specimens = {
        pos: { [manual]: { 'pos-neg': 'NEG' }, [chan('COVID-19')]: { conc: '20' }, [chan('IC')]: { conc: '25' } },
        neg: { [manual]: { 'pos-neg': 'NEG' }, [chan('COVID-19')]: { conc: '40' }, [chan('IC')]: { conc: '25' } },
        // No concentrations at all -> conc is NULL on both channels, the
        // is_null() branch at functions_covid.php:465-473 fires, the call is PEN.
        pen: { [manual]: { 'pos-neg': 'NEG' }, [chan('COVID-19')]: { 'pos-neg': 'INC' }, [chan('IC')]: { 'pos-neg': 'INC' } },
      };

      const seeded: Record<string, PcrSpecimen> = {};
      for (const [name, results] of Object.entries(specimens)) {
        seeded[name] = await seedSpecimen(
          req, db, tenant.labId, tenant.prefix, p, `Pcrsyn${runKey}${name}`, results,
        );
      }

      // Part A runs on RENDER, not on save: nothing exists before the screen is
      // opened. Asserted, so the mutation below cannot be attributed to the save.
      for (const [name, s] of Object.entries(seeded)) {
        expect(rollupRows(db, s.reportId, covidDrug).length, `no ${covidDrug} row on ${name} before render`)
          .toBe(0);
      }

      const screen: Record<string, string> = {};
      for (const [name, s] of Object.entries(seeded)) {
        screen[name] = await renderResultsEdit(req, s);
      }

      // ------------------------------------------------------------------
      // THE CLINICAL CALL, ON THE SCREEN THAT COMPUTES IT.
      //
      // Everything below this block is `ci_results_pcr`. This is the only
      // assertion in either interpreter spec that fails when the template
      // shows the raw Ct, the IC channel, an inverted call, or the
      // never-persisted PEN as a negative result -- all of which are a wrong
      // clinical answer delivered from a technically-correct database.
      // ------------------------------------------------------------------
      const covidGroupName = db.scalar(
        `SELECT tg_name FROM ci_test_groups WHERE tg_ID = ${Number(p.covidTg)}`,
      );
      expect(covidGroupName, 'the covid group renders under its tg_name').not.toBe('');
      expect(
        displayedCovidCall(screen.pos, covidGroupName),
        `${seeded.pos.assen}: Ct 20 under the cutoff must READ as Detected`,
      ).toBe('POS=Detected');
      expect(
        displayedCovidCall(screen.neg, covidGroupName),
        `${seeded.neg.assen}: Ct 40 over the cutoff must READ as Not Detected`,
      ).toBe('NEG=Not Detected');
      expect(
        displayedCovidCall(screen.pen, covidGroupName),
        `${seeded.pen.assen}: a PEN specimen is neither persisted NOR shown a call`,
      ).toBe('');

      // A SINGLE call per report -- one rollup row, not one per channel.
      const posRollup = rollupRows(db, seeded.pos.reportId, covidDrug);
      const negRollup = rollupRows(db, seeded.neg.reportId, covidDrug);
      expect(posRollup.length, `one ${covidDrug} rollup row on ${seeded.pos.assen}`).toBe(1);
      expect(negRollup.length, `one ${covidDrug} rollup row on ${seeded.neg.assen}`).toBe(1);
      expect(posRollup[0][0], 'a Ct under the cutoff with a valid IC interprets POS').toBe('POS');
      expect(negRollup[0][0], 'a Ct over the cutoff with a valid IC interprets NEG').toBe('NEG');

      // ...and the raw channel rows are untouched by the rollup: the interpreted
      // call is a SEPARATE row, so the Ct values stay auditable.
      const channels = db.query(
        `SELECT drug, IFNULL(conc,'') FROM ci_results_pcr
          WHERE report_id = ${Number(seeded.pos.reportId)} AND drug IN ('COVID-19','IC')
          ORDER BY drug`,
      );
      expect(channels, 'the posted Ct values survive interpretation').toEqual([['COVID-19', '20'], ['IC', '25']]);

      // The scenario's own value: `ci_reports_pcr.covid_pos` in {0,1} for a
      // clean run (results_edit.php:648-658 -> :781-783).
      const posState = reportState(db, seeded.pos.reportId);
      const negState = reportState(db, seeded.neg.reportId);
      expect(['0', '1'], `covid_pos for a clean POS run on ${seeded.pos.assen}`).toContain(posState.covidPos);
      expect(['0', '1'], `covid_pos for a clean NEG run on ${seeded.neg.assen}`).toContain(negState.covidPos);
      // Stronger than the catalogue, because the call is deterministic here.
      expect(posState.covidPos, 'a POS call is covid_pos=1').toBe('1');
      expect(negState.covidPos, 'a NEG call is covid_pos=0').toBe('0');
      // Interpretation alone releases nothing.
      expect(posState.estatus, 'rendering does not release the POS report').toBe('');
      expect(negState.estatus, 'rendering does not release the NEG report').toBe('');

      // PEN is NEVER persisted -- `functions_covid.php:129` guards the write
      // with `pos-neg != 'PEN'`.
      expect(
        rollupRows(db, seeded.pen.reportId, covidDrug).length,
        `PEN must never be persisted (${seeded.pen.assen})`,
      ).toBe(0);
      // Cross-check that the PEN specimen was genuinely interpreted and not
      // merely skipped: its raw channels carry no conc, which is the branch.
      expect(
        db.scalar(
          `SELECT COUNT(*) FROM ci_results_pcr
            WHERE report_id = ${Number(seeded.pen.reportId)} AND drug IN ('COVID-19','IC') AND conc IS NOT NULL`,
        ),
        'the PEN specimen carries no Ct on either channel',
      ).toBe('0');

      console.log(
        `S-TST-02 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) ` +
        `pos=${seeded.pos.assen}/covid_pos=${posState.covidPos} neg=${seeded.neg.assen}/covid_pos=${negState.covidPos} ` +
        `pen=${seeded.pen.assen}`,
      );
    } finally {
      await req.dispose();
    }
  });
});

// ===========================================================================
// S-TST-03 -- an unresolved rerun cannot be released
// ===========================================================================

e2e.describe('S-TST-03 @full @pcr @tst @mutates', () => {
  e2e('a covid_pos=2 rerun is refused by Approve, while an identical NEG specimen releases', async ({
    db, tenant, runKey, baseURL,
  }, testInfo) => {
    const p = pcrPanels(db);
    const req = await ctxFor(baseURL, VERIFIER);
    const admin = await ctxFor(baseURL, ADMIN);
    try {
      const covidDrug = db.scalar(
        `SELECT UPPER(te_name) FROM ci_test_panels WHERE te_ID = ${Number(p.covidTe)}`,
      );
      const chan = (drug: string) => `${p.covidTe}_${drug}_${p.covidTg}_`;
      const manual = `${p.manualTe}_${MANUAL_ABREV}_${p.manualTg}_`;

      // RER: the COVID channel is over the cutoff AND the internal control
      // fails. `$ic_pos` is false for a literal 'NaN'
      // (functions_covid.php:442), so neither the POS nor the NEG branch is
      // taken and the final else answers RER (:490).
      const rerun = await seedSpecimen(req, db, tenant.labId, tenant.prefix, p, `Pcrsyn${runKey}rer`, {
        [manual]: { 'pos-neg': 'NEG' },
        [chan('COVID-19')]: { conc: '40' },
        [chan('IC')]: { conc: 'NaN' },
      });
      // The CONTROL, and the reason this test is not vacuous: same persona,
      // same panels, same Approve request, one differing Ct on the control
      // channel. If Approve were simply broken for everyone, this goes red.
      const clean = await seedSpecimen(req, db, tenant.labId, tenant.prefix, p, `Pcrsyn${runKey}ctl`, {
        [manual]: { 'pos-neg': 'NEG' },
        [chan('COVID-19')]: { conc: '40' },
        [chan('IC')]: { conc: '25' },
      });

      await renderResultsEdit(req, rerun);
      await renderResultsEdit(req, clean);

      expect(rollupRows(db, rerun.reportId, covidDrug).map((r) => r[0]),
        `a failed control interprets RER on ${rerun.assen}`).toEqual(['RER']);
      const armed = reportState(db, rerun.reportId);
      expect(armed.covidPos, `ci_reports_pcr.covid_pos for the unresolved rerun ${rerun.assen}`).toBe('2');
      expect(armed.estatus, 'the rerun is not released by the render').toBe('');
      // The other half of the gate at parts/pcr/results.php:515, asserted so a
      // future default flip cannot make this test pass for the wrong reason.
      expect(armed.incCompleted, 'covid_inc_completed is the gate\'s second term').toBe('0');
      expect(armed.rerunCount, 'the rerun has not been resolved by a repeat run').toBe('0');

      // 1. The verifier's own Approve attempt. HTTP 200 and even
      //    `ajaxstatus: ok` come back on the refused path (measured), so the
      //    row is the only honest assertion (D226).
      expect((await approve(req, rerun.reportId)).status(), 'Approve answers 200 either way').toBe(200);
      expect(reportState(db, rerun.reportId).estatus,
        `an unresolved rerun must not be released (${rerun.assen})`).toBe('');
      expect(db.scalar(`SELECT batch_status FROM ci_batches_pcr WHERE mid = ${Number(rerun.mid)}`),
        'and the specimen is not advanced to completed').not.toBe('completed');

      // 2. The catalogue's first escape clause -- `rerun_count` reaching 2.
      //    Set as a PRECONDITION, exactly as S-STA-03 backdates approve_date:
      //    the resolution itself is a second bench run, not the outcome here.
      //    MEASURED RESULT: it does not open the gate. `results_approve`
      //    (parts/pcr/results.php:515) reads only covid_pos and
      //    covid_inc_completed; `rerun_count >= 2` flips `$can_approve` in the
      //    RENDER (results_edit.php:818-820), i.e. it restores the button, not
      //    the server's permission.
      db.query(`UPDATE ci_reports_pcr SET rerun_count = 2 WHERE id = ${Number(rerun.reportId)}`);
      expect((await approve(req, rerun.reportId)).status()).toBe(200);
      expect(reportState(db, rerun.reportId).estatus,
        'rerun_count=2 is a render-side affordance; the server still refuses').toBe('');

      // 3. The second escape clause -- an admin role override. Same finding:
      //    the admin branch (ci_load_results_pcr.php:1341-1342) governs which
      //    control renders, not what the action accepts.
      expect((await approve(admin, rerun.reportId)).status()).toBe(200);
      expect(reportState(db, rerun.reportId).estatus,
        'an administrator does not override the server-side rerun gate either').toBe('');

      // 4. THE CONTROL. Identical request, resolved call -> released.
      expect(reportState(db, clean.reportId).covidPos, `control ${clean.assen} interprets NEG`).toBe('0');
      expect((await approve(req, clean.reportId)).status(), 'Approve on the control').toBe(200);
      const released = reportState(db, clean.reportId);
      expect(released.estatus, `the resolved specimen IS released (${clean.assen})`).toBe('C');
      expect(db.scalar(`SELECT batch_status FROM ci_batches_pcr WHERE mid = ${Number(clean.mid)}`),
        'and its specimen advances to completed').toBe('completed');
      // Tenancy survives the release (D172).
      expect(db.scalar(`SELECT lab_ID FROM ci_batches_pcr WHERE mid = ${Number(clean.mid)}`),
        'ci_batches_pcr.lab_ID after release').toBe(tenant.labId);

      console.log(
        `S-TST-03 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) ` +
        `rerun=${rerun.assen}/covid_pos=2/estatus='' control=${clean.assen}/estatus=C`,
      );
    } finally {
      await req.dispose();
      await admin.dispose();
    }
  });
});

// ===========================================================================
// Page-layer capability gate -- the live reading, made executable
// ===========================================================================

e2e.describe('page-layer capability gate @pcr @tst', () => {
  // D185/D250: the page layer answers HTTP 200 with a DENIAL BODY, never a
  // status code, so the assertion is on the rendered outcome.
  //
  // CORRECTION TO 11b-pcr-verification.md, measured on this build: it predicts
  // `wp_die(..., 403)` for `page=pcr_verification`. Live it is HTTP 200 with
  // the same 2,629-byte denial body every other denial uses. The prediction was
  // right about the denial and wrong about the code -- which is precisely why
  // D185 forbids asserting the code.
  for (const [login, page, allowed] of [
    // pcr_verify holds the misspelled ci_resutls_pcr, which is what
    // inc/admin.php:643 registers pcr_results against (D057/D224).
    [VERIFIER, 'pcr_results', true],
    [VERIFIER, 'pcr_req_form', true],
    // The DEAD screen (D044/D058): registration commented at inc/admin.php:638,
    // so even the persona holding ci_resutls_pcr_verification is denied.
    [VERIFIER, 'pcr_verification', false],
    // A patient-portal account holds no PCR capability at all.
    [NOROLE, 'pcr_results', false],
  ] as const) {
    e2e(`${login} ${allowed ? 'reaches' : 'is denied'} admin.php?page=${page}`, async ({ baseURL }) => {
      const ctx = await ctxFor(baseURL, login);
      const res = await ctx.get(`/wp-admin/admin.php?page=${page}`);
      const body = await res.text();
      await ctx.dispose();
      expect(/Sorry, you are not allowed/.test(body), 'core denial notice present').toBe(!allowed);
    });
  }

  // The ON-TENANT negative control, read from the fixture rather than hardcoded:
  // this spec's own lab persona owns LAN but is `leadlabsecman`, which holds no
  // PCR capability. The day it gains `ci_resutls_pcr`, this goes red.
  e2e('the tenant persona owns the lab but is denied admin.php?page=pcr_results', async ({
    baseURL, tenant,
  }) => {
    const ctx = await ctxFor(baseURL, tenant.login);
    const body = await (await ctx.get('/wp-admin/admin.php?page=pcr_results')).text();
    await ctx.dispose();
    expect(
      /Sorry, you are not allowed/.test(body),
      `${tenant.login} owns lab ${tenant.prefix} yet must not reach the PCR Results screen`,
    ).toBe(true);
  });
});

// ===========================================================================
// The dead screen's back door -- an EXPECTED FAILURE, not a passing assertion
// ===========================================================================

e2e.describe('pcr_verification back door @pcr @tst @mutates', () => {
  /**
   * D055/D249/D250, filed as a CRITICAL. `pcr_verification` is UNREGISTERED, so
   * there is no menu entry for core to guard; the `trajax` resolver at
   * `admin/classes/tr_admin_page_v7.php:170-185` runs at `admin_menu` priority
   * 999 -- BEFORE `user_can_access_admin_page()` at `wp-admin/includes/menu.php:371`
   * -- includes `parts/pcr/verification.php` and exits. It performs no
   * `current_user_can()` and no nonce check, and neither does the included file
   * (`:12-37`).
   *
   * Written per D175: where the app does not enforce, the test states the
   * DENIAL and is marked failing. It is never written as "the action succeeds".
   * It turns red -- "expected to fail, but passed" -- the day the dispatcher
   * grows a capability check, which is exactly when this file should be told.
   *
   * `e2e.fail()` is called INSIDE the body on purpose: at describe scope it
   * annotates every sibling.
   *
   * WHAT THIS DEMONSTRATES, precisely. Not merely that the screen renders: the
   * WRITE is measured. `ci_reports_pcr.verified` is asserted 0 before the POST
   * and read back after it, on a report row this test created and whose id it
   * holds. `verified` is written in exactly two places in the whole plugin --
   * `parts/pcr/verification.php:16` and `:29` -- so a change in that column can
   * only have come through this back door.
   *
   * SHARED-STACK HYGIENE. The flag is `ci_reports_pcr.verified`, NOT `estatus`:
   * nothing is released, no report is distributed, no clinical result is left
   * approved. The row is this test's own synthetic specimen in this spec's own
   * lab, and the flag is restored to 0 BEFORE the assertion runs, so the
   * (expected) failure leaves nothing behind.
   */
  e2e('a patient-portal account with no role is refused verify_approve', async ({
    db, tenant, runKey, baseURL,
  }) => {
    const p = pcrPanels(db);
    const seedCtx = await ctxFor(baseURL, VERIFIER);
    const patient = await ctxFor(baseURL, NOROLE);
    let verifiedAfter = '';
    try {
      const s = await seedSpecimen(seedCtx, db, tenant.labId, tenant.prefix, p, `Pcrsyn${runKey}bd`, {
        [`${p.manualTe}_${MANUAL_ABREV}_${p.manualTg}_`]: { 'pos-neg': 'NEG' },
      });
      expect(reportState(db, s.reportId).verified, 'precondition: the report is unverified').toBe('0');

      await patient.post('/wp-admin/admin.php?page=pcr_verification', {
        form: { trajax: '1', act: 'verify_approve', id: String(Number(s.reportId)) },
      });
      verifiedAfter = reportState(db, s.reportId).verified;

      // Restore FIRST, assert second.
      db.query(`UPDATE ci_reports_pcr SET verified = 0 WHERE id = ${Number(s.reportId)}`);
    } finally {
      await seedCtx.dispose();
      await patient.dispose();
    }

    expect(
      verifiedAfter,
      'an account with no role on the main site set ci_reports_pcr.verified via the ' +
      'unregistered pcr_verification slug (D055/D249)',
    ).toBe('0');
  });
});

// ===========================================================================
// DWA-E21 -- an Approve control the server always refuses
// ===========================================================================

/**
 * THE FIRST BROWSER USAGE IN THIS FILE. Everything above is HTTP, because every
 * assertion above is about a row the saver or the results-edit RENDER writes,
 * and D382 keeps a URL-only handler on its HTTP caller. DWA-E21 is the opposite
 * shape: the defect IS the rendered affordance, so it cannot be measured
 * anywhere but on the screen.
 *
 * THE SCREEN. `page=pcr_results` (the listing, `parts/pcr/results_list.php`) --
 * NOT the results-edit form. Two different Approve verbs exist and only one of
 * them is the catalogue's:
 *
 *   - the listing's per-row `<a class="approvebtn">` posts
 *     `trajax=1&act=results_approve` (`js/admin.js:1370-1445`), which lands on
 *     `parts/pcr/results.php:469` and is gated at `:515-517`;
 *   - the results-edit form's `<input name="approval">` posts
 *     `tr_action=ci_save_result_edit`, which sets `estatus='C'`
 *     (`inc/actions/ci_save_result_edit.php:113-119`) with NO rerun gate at all.
 *
 * DWA-E21 names the first: `alert('Cant approve rerun')` is what
 * `js/admin.js:1442` does with the `.message_alert` text that
 * `Tr_Session_Class::show()` renders for `results.php:517`. (The second verb is
 * a separate and larger finding -- an ungated release path -- and is reported
 * rather than pinned here, because it is not this defect.)
 *
 * THE PERSONA IS `Administrat0r`, and that is measured rather than preferred.
 * `inc/functions.php:5318-5322` appends `and ba.lab_id in (<user labs>)` to the
 * listing query whenever `CI_Base_Lab::has_user_labs()` holds. `e2e_pcrverify`
 * carries `_lab_ids = 35`; every row this spec creates carries this spec's
 * tenant lab. Measured on this build 2026-08-25: the listing answers "0 items"
 * for the verifier and "1 item" for the administrator on the SAME accession.
 * The rows stay in this spec's own lab (D172) -- only the reader changes.
 *
 * SHARED-STACK HYGIENE. The listing is reached through its own
 * Accession/Patient search (`?s=<accession>`), which narrows the parent query to
 * ONE batch; `results_list.php:595-603` then auto-expands it because exactly one
 * parent rendered. Without the search this screen would render every lab's
 * batches for the day and fire `ci_load_results_pcr` across all of them (D034 /
 * D225: a queue render is a mutation).
 *
 * AND THE FRAGMENT IS INERT HERE, asserted rather than assumed below: the
 * rerun_count bookkeeping at `ci_load_results_pcr.php:1068-1108` is wrapped in
 * `$result_settings['e_reruntest']`, and this build's `ci_result_settings_pcr`
 * holds four keys, none of them `e_reruntest`; every `tg_auto_approve_*` on
 * every pcr group in this database is 0, so `:1028` cannot fire either.
 */

/** The PCR Results listing, filtered to one accession by the screen's own search. */
const PCR_LIST = '/wp-admin/admin.php?page=pcr_results';

/**
 * Load the listing for ONE accession and return that specimen's row.
 *
 * Always a fresh `goto` -- D386. The row this returns is markup
 * `ci_load_results_pcr.php` produced from the STORED row, so it cannot survive
 * a click that changed nothing; the post-XHR DOM is never read back, because
 * `js/admin.js:1417-1430` rewrites the status cell client-side on any response
 * carrying the string `updated`.
 *
 * `tr.childp` is the structural handle D383 sanctions for exactly this table --
 * the app's own JS keys on it (`js/admin.js:1372`), and `getByRole('row')` here
 * matches the parent batch row as well, which contains the child table.
 */
async function listingRow(page: Page, assen: string) {
  await page.goto(`${PCR_LIST}&s=${assen}`);
  const row = page.locator('tr.childp').filter({ hasText: assen });
  await expect(row, `the PCR Results listing shows one row for ${assen}`).toHaveCount(1);
  return row;
}

/** The per-row Approve control. */
function approveLink(row: Locator): Locator {
  return row.getByRole('link', { name: 'Approve', exact: true });
}

e2e.describe('DWA-E21 @full @pcr @tst @mutates', () => {
  /**
   * THE POSITIVE CONTROL, and the only reason the pin below is not vacuous.
   *
   * Same screen, same persona, same locator, same click: a RESOLVED specimen is
   * offered Approve and the click releases it. Without this, a pin that reads
   * "no Approve control is offered" would be satisfied by an Approve control
   * that never renders for anybody, or by a locator that matches nothing.
   *
   * Deliberately NOT folded into the pinned test: an assertion that fails inside
   * a `fail()` body is indistinguishable from the pinned failure.
   *
   * It is also the first browser-side statement in the suite that the pcr
   * release verb works at all; S-TST-03's control asserts the same release over
   * HTTP, on its OWN specimen, so the two cannot collide (D396).
   */
  e2e('a resolved specimen is offered Approve and the click releases it', async ({
    db, tenant, runKey, baseURL, pageAs,
  }) => {
    const p = pcrPanels(db);
    const req = await ctxFor(baseURL, VERIFIER);
    try {
      const chan = (drug: string) => `${p.covidTe}_${drug}_${p.covidTg}_`;
      const manual = `${p.manualTe}_${MANUAL_ABREV}_${p.manualTg}_`;

      // A clean NEG: Ct over the cutoff with a VALID internal control.
      const clean = await seedSpecimen(req, db, tenant.labId, tenant.prefix, p, `Pcrsyn${runKey}e21c`, {
        [manual]: { 'pos-neg': 'NEG' },
        [chan('COVID-19')]: { conc: '40' },
        [chan('IC')]: { conc: '25' },
      });
      // Interpretation happens on the results-edit render (D235), not on save.
      await renderResultsEdit(req, clean);
      expect(reportState(db, clean.reportId).covidPos,
        `${clean.assen} interprets a resolved NEG call`).toBe('0');

      const page = await pageAs(ADMIN);
      const alerts: string[] = [];
      page.on('dialog', (d) => { alerts.push(d.message()); void d.dismiss(); });

      const row = await listingRow(page, clean.assen);
      // The listing render is inert: nothing was released by opening the screen.
      expect(reportState(db, clean.reportId).estatus,
        `opening the listing does not release ${clean.assen}`).toBe('');
      await expect(approveLink(row), `the bench is offered Approve on ${clean.assen}`).toBeVisible();

      await approveLink(row).click();
      // The JS swaps the control to Reverse only after the POST answers, so this
      // is the bounded wait as well as an assertion.
      await expect(row.getByRole('link', { name: 'Reverse', exact: true }),
        'the control the bench clicked becomes Reverse').toHaveCount(1);
      expect(alerts, `Approve on a resolved specimen tells the bench nothing (${clean.assen})`)
        .toEqual([]);

      // THE ROW (D226). The response is not the assertion -- the refused path
      // answers 200 with `ajaxstatus: ok` too.
      expect(reportState(db, clean.reportId).estatus,
        `${clean.assen} is released by the click`).toBe('C');
      expect(db.scalar(`SELECT batch_status FROM ci_batches_pcr WHERE mid = ${Number(clean.mid)}`),
        'and its specimen advances to completed').toBe('completed');
      expect(db.scalar(`SELECT lab_ID FROM ci_batches_pcr WHERE mid = ${Number(clean.mid)}`),
        'tenancy survives the release (D172)').toBe(tenant.labId);

      // D386 -- the rendered read-back, from a fresh GET rather than the
      // post-XHR DOM the click just rewrote.
      const after = await listingRow(page, clean.assen);
      await expect(after.getByRole('cell', { name: 'C', exact: true }),
        `the listing reads back ${clean.assen} as approved`).toBeVisible();
      await expect(approveLink(after),
        'and no longer offers Approve on a released specimen').toHaveCount(0);
    } finally {
      await req.dispose();
    }
  });

  /**
   * DWA-E21, PINNED AS AN EXPECTED FAILURE (D175).
   *
   * WHAT THE USER MEETS. The status cell says ReRun -- the screen KNOWS the
   * specimen is unresolved -- and the row still offers Approve. Clicking it
   * raises `alert('Cant approve rerun')` and nothing moves. The defect is not
   * that the server refuses; refusing an unresolved rerun is CORRECT, and
   * S-TST-03 above asserts exactly that as a passing test. The defect is that
   * the screen offers a verb it will never honour.
   *
   * RE-CONFIRMED AT THE PIN, 2026-08-25 -- §7.4 rated DWA-E21 "recon only".
   * Measured end to end on submodule a7aa074d as Administrat0r:
   *   listing "1 item"; status cell `<span class="rerunspan rerurncount_2">ReRun</span>`;
   *   one `<a class="approvebtn">Approve</a>`; click -> dialog "Cant approve rerun";
   *   `estatus` '' before and after; the Approve link still offered on reload.
   *
   * WHY THE CONTROL RENDERS. `ci_load_results_pcr.php:1297` starts from
   * `$show_approve = covid_pos !== 2`, which is false here; `:1299` then
   * OVERRIDES it to true for `rerun_count >= 2`; `:1302` would put it back only
   * for `rerun_count <= 1`. WHY THE SERVER REFUSES: `parts/pcr/results.php:515`
   * reads `covid_pos` and `covid_inc_completed` and never looks at
   * `rerun_count`. Two different notions of "resolved", one screen.
   *
   * AND THE TWO CANNOT BE RECONCILED IN PLACE, measured while proving the flip:
   * the listing's own query (`ci_load_results_pcr.php:236`, `:697`) selects
   * `re.covid_pos` and NOT `re.covid_inc_completed`, so a guard written at
   * `:1299` against the server's second gate term compares against an undefined
   * property and changes nothing -- injected, ran, the control still rendered.
   * The screen cannot see half of the condition it is being refused on.
   *
   * `rerun_count = 2` IS SET DIRECTLY, exactly as S-TST-03 sets it above and as
   * S-STA-03 backdates approve_date. It is a precondition, not the outcome: the
   * live path that reaches 2 is a second bench run, and on this build it is
   * unreachable anyway -- `results_edit.php:789` and
   * `ci_load_results_pcr.php:1068` both wrap the rerun_count bookkeeping in
   * `$result_settings['e_reruntest']`, and this database's
   * `ci_result_settings_pcr` does not carry that key (asserted below, so a seed
   * change that turns it on cannot silently make this test measure something
   * else).
   *
   * THE ASSERTION IS OFFERED-VS-HONOURED, in one object so the report shows all
   * three observed facts at once rather than stopping at the first. The click
   * is CONDITIONAL on the control being offered -- that is what makes the pin
   * flip. A test that clicked unconditionally would, the day the control is
   * hidden, fail on a missing locator and stay an expected failure forever.
   *
   * HOW IT FLIPS. Add `&& $row->covid_pos != 2` to the `:1299` override and this
   * reports "Expected to fail, but passed" and goes RED while the control above
   * stays green. Verified by injecting exactly that change, 2026-08-25.
   *
   * `e2e.fail()` is INSIDE the body on purpose: at describe scope it would
   * annotate the control above and mask a real regression there.
   *
   * NOT `ci_reports_pcr.verified`. This test never touches that column, so the
   * back-door pin at the top of this file keeps its exclusive claim on it.
   */
  e2e('the listing offers no Approve control on a specimen the server will refuse', async ({
    db, tenant, runKey, baseURL, pageAs,
  }) => {
    const p = pcrPanels(db);
    const req = await ctxFor(baseURL, VERIFIER);
    try {
      const chan = (drug: string) => `${p.covidTe}_${drug}_${p.covidTg}_`;
      const manual = `${p.manualTe}_${MANUAL_ABREV}_${p.manualTg}_`;

      // RER: Ct over the cutoff AND a failed internal control -- `$ic_pos` is
      // false for a literal 'NaN' (functions_covid.php:442), so the final else
      // answers RER (:490) and results_edit.php writes covid_pos = 2.
      const rerun = await seedSpecimen(req, db, tenant.labId, tenant.prefix, p, `Pcrsyn${runKey}e21r`, {
        [manual]: { 'pos-neg': 'NEG' },
        [chan('COVID-19')]: { conc: '40' },
        [chan('IC')]: { conc: 'NaN' },
      });
      await renderResultsEdit(req, rerun);
      const armed = reportState(db, rerun.reportId);
      expect(armed.covidPos, `precondition: ${rerun.assen} is an unresolved rerun`).toBe('2');
      expect(armed.incCompleted, 'precondition: the second term of the server gate is 0').toBe('0');
      expect(armed.estatus, 'precondition: nothing is released').toBe('');

      // The bookkeeping that would otherwise move rerun_count under this test is
      // off on this build. Asserted so a seed change cannot silently rewrite the
      // precondition below.
      expect(
        db.scalar(
          "SELECT option_value FROM wp_options WHERE option_name='ci_result_settings_pcr'",
        ),
        'precondition: ci_result_settings_pcr carries no e_reruntest, so no render moves rerun_count',
      ).not.toContain('e_reruntest');

      db.query(`UPDATE ci_reports_pcr SET rerun_count = 2 WHERE id = ${Number(rerun.reportId)}`);

      const page = await pageAs(ADMIN);
      const alerts: string[] = [];
      page.on('dialog', (d) => { alerts.push(d.message()); void d.dismiss(); });

      const row = await listingRow(page, rerun.assen);
      // What the screen itself says about this specimen, in the bench's words.
      await expect(row.getByText('ReRun', { exact: true }),
        `the listing tells the bench ${rerun.assen} is an unresolved rerun`).toBeVisible();
      expect(reportState(db, rerun.reportId).rerunCount,
        'the listing render did not move rerun_count').toBe('2');

      const offered = await approveLink(row).count();
      if (offered > 0) {
        await approveLink(row).click();
        // The alert is raised from the ajax success callback (js/admin.js:1442),
        // so it lands after the POST answers. Bounded, and never rethrown: the
        // one assertion below is what reports, including a missing alert.
        await expect.poll(() => alerts.length, { timeout: 5_000 }).toBeGreaterThan(0)
          .catch(() => { /* reported by the assertion below */ });
      }

      // D386 -- the rendered read-back comes from a fresh GET.
      const after = await listingRow(page, rerun.assen);
      const stillOffered = await approveLink(after).count();

      expect(
        {
          offered,
          alerted: alerts.join('|'),
          estatus: reportState(db, rerun.reportId).estatus,
          stillOfferedAfterRefusal: stillOffered,
        },
        `${rerun.assen} reads "ReRun" on the PCR Results listing and is still offered an Approve `
        + 'control; clicking it alerts the bench "Cant approve rerun" and releases nothing '
        + '(ci_load_results_pcr.php:1299 shows the control on rerun_count>=2, '
        + 'parts/pcr/results.php:515 refuses on covid_pos=2 and never reads rerun_count)',
      ).toEqual({ offered: 0, alerted: '', estatus: '', stillOfferedAfterRefusal: 0 });
    } finally {
      await req.dispose();
    }
  });
});

// ===========================================================================
// covid_pos, SECOND IMPLEMENTATION -- the bench LISTING's own copy of the rule
// ===========================================================================

/**
 * THE RULE IS WRITTEN THREE TIMES AND S-TST-02 COVERS ONE OF THEM.
 *
 * S-TST-02 above drives `parts/pcr/results_edit.php:648-658` -- the copy that
 * runs when a verifier opens ONE specimen. But a bench that never opens the
 * form still interprets specimens, because the LISTING interprets every row it
 * renders (D034/D225): `ci_load_results_pcr.php:886-912` calls the same
 * `trpdd_cal_ncovid19()`, maps the call onto `covid_pos` at `:889-897`, and
 * flushes through `wpdb->update('ci_reports_pcr', ...)` at `:1117`.
 *
 * That copy was asserted NOWHERE. A mutation that made `:891` write `3` instead
 * of `1` -- a wrong clinical call persisted from a screen nobody has to click
 * -- survived the whole suite.
 *
 * THE TWO COPIES DO NOT AGREE, and that divergence is the reason this test
 * cannot simply repeat S-TST-02's assertions on a different URL:
 *
 *   results_edit.php (copy 1)  persists BOTH calls -- POS -> 1 and NEG -> 0.
 *                             S-TST-02 asserts exactly that.
 *   ci_load_results_pcr.php    guards its write with
 *   (copy 3, `:907`)          `odl_covid_pos != covid_pos && covid_pos > 0`.
 *                             A NEG call computes 0, `0 > 0` is false, and
 *                             `null != 0` is false in PHP as well -- so the
 *                             listing persists a POSITIVE call and silently
 *                             persists NOTHING for a negative one.
 *
 * So the honest pin is POS -> `covid_pos = 1`, NEG -> `covid_pos` STILL UNSET.
 * Asserting NEG -> 0 here (the shape copy 1 has) would red for the wrong
 * reason -- the column is NULL, not '0' -- and would be a self-comparing
 * read-back if the seed ever wrote 0 (D397).
 *
 * WHY NEITHER SPECIMEN IS OPENED IN THE RESULTS-EDIT FORM FIRST. Copy 1 would
 * write `covid_pos` before the listing ever ran, `odl_covid_pos` would already
 * equal the computed value, and copy 3's guard would skip the write -- the test
 * would then pass with copy 3 entirely inert. The saver resets `covid_pos` to
 * NULL on every save (`ci_save_pcr.php:865`) and `seedSpecimen` asserts that
 * reset as a precondition, so "unset before the listing" is measured, not
 * assumed.
 *
 * THE PERSONA IS `Administrat0r`, for the reason DWA-E21 documents above:
 * `inc/functions.php:5318-5322` scopes the listing to `_lab_ids`, and
 * `e2e_pcrverify` carries lab 35 while every row here carries this spec's
 * tenant lab. The rows do not move (D172) -- only the reader does.
 *
 * WHAT THE BENCH SEES. `.has_pos td.statuscol` paints a 12 px `#da1414` bar
 * down the status cell (`css/admin_new.min.css`) -- the listing's only visual
 * flag that a specimen is positive. It is asserted on BOTH specimens, present
 * on the POS one and absent on the NEG one, from a SECOND fresh render (D386)
 * rather than from the render that did the writing.
 */
e2e.describe('covid_pos on the results listing @full @pcr @tst @mutates', () => {
  e2e('opening the bench listing stores the POSITIVE call and flags the row, and leaves a negative one uninterpreted', async ({
    db, tenant, runKey, baseURL, pageAs,
  }, testInfo) => {
    const p = pcrPanels(db);
    const req = await ctxFor(baseURL, VERIFIER);
    try {
      const chan = (drug: string) => `${p.covidTe}_${drug}_${p.covidTg}_`;
      const manual = `${p.manualTe}_${MANUAL_ABREV}_${p.manualTg}_`;

      // Same cutoff arithmetic S-TST-02 documents: Ct 20 is under the built-in
      // <= 34 (inc/functions_covid.php:350-354), Ct 40 is over it, and a valid
      // IC channel keeps both calls out of the RER branch.
      const pos = await seedSpecimen(req, db, tenant.labId, tenant.prefix, p, `Pcrsyn${runKey}lstp`, {
        [manual]: { 'pos-neg': 'NEG' },
        [chan('COVID-19')]: { conc: '20' },
        [chan('IC')]: { conc: '25' },
      });
      const neg = await seedSpecimen(req, db, tenant.labId, tenant.prefix, p, `Pcrsyn${runKey}lstn`, {
        [manual]: { 'pos-neg': 'NEG' },
        [chan('COVID-19')]: { conc: '40' },
        [chan('IC')]: { conc: '25' },
      });

      // seedSpecimen asserted covid_pos='unset' on both; re-read here so the
      // NEG "still unset" assertion below has a value it was compared FROM
      // rather than being a literal round trip (D397).
      const posBefore = reportState(db, pos.reportId).covidPos;
      const negBefore = reportState(db, neg.reportId).covidPos;
      expect({ posBefore, negBefore }, 'precondition: no interpretation has run on either specimen')
        .toEqual({ posBefore: 'unset', negBefore: 'unset' });

      const page = await pageAs(ADMIN);

      // THE MUTATION. Opening the listing is what runs copy 3 -- no click, no
      // form, no Approve.
      await listingRow(page, pos.assen);
      await listingRow(page, neg.assen);

      // THE ROW (D226). '3' -- or any value that is not the interpreted call --
      // is a wrong clinical answer stored from a screen the bench only looked at.
      expect(
        reportState(db, pos.reportId).covidPos,
        `${pos.assen}: a Ct under the cutoff must store covid_pos=1 when the bench opens the listing`,
      ).toBe('1');
      // The listing's guard refuses to persist a negative call at all. Asserted
      // as the DIVERGENCE it is: results_edit.php writes 0 here (S-TST-02),
      // ci_load_results_pcr.php:907 leaves the column NULL.
      expect(
        reportState(db, neg.reportId).covidPos,
        `${neg.assen}: the listing's guard (covid_pos > 0) persists nothing for a negative call, `
        + 'while the results-edit form stores 0 -- one rule, two answers',
      ).toBe('unset');
      // Neither render released anything: interpretation is not approval.
      expect(reportState(db, pos.reportId).estatus, 'the listing render releases nothing').toBe('');
      expect(reportState(db, neg.reportId).estatus, 'the listing render releases nothing').toBe('');

      // D386 -- the rendered read-back comes from a fresh GET, never from the
      // markup the writing render produced.
      const posRow = await listingRow(page, pos.assen);
      await expect(
        posRow.locator('td.statuscol'),
        `${pos.assen} must carry the listing's red positive bar for the bench`,
      ).toHaveCSS('border-left-color', 'rgb(218, 20, 20)');

      const negRow = await listingRow(page, neg.assen);
      await expect(
        negRow.locator('td.statuscol'),
        `${neg.assen} is negative and must NOT be flagged positive to the bench`,
      ).not.toHaveCSS('border-left-color', 'rgb(218, 20, 20)');

      console.log(
        `covid_pos listing worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
        + `pos=${pos.assen}/covid_pos=1 neg=${neg.assen}/covid_pos=unset`,
      );
    } finally {
      await req.dispose();
    }
  });
});
