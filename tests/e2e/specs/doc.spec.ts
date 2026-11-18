import { expect, request as playwrightRequest, type Page, type Locator } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';
import { test as e2e, BEFORE_BOUNCE_MS } from '../fixtures/test';
import { Db } from '../fixtures/db';
import { batchIds } from '../fixtures/batch-id';
import { wpEval } from '../fixtures/container';
import { CHARSET, infra, seedPcrRequisition, seedBloodRequisition } from '../fixtures/seed-domain';
import { STATE_DIR } from '../playwright.config';
import * as path from 'node:path';

/**
 * DOC -- clinic provider / ordering physician (personas.md 3.6, slug `doctor`).
 *
 * This file exists for ONE axis: TENANCY. Every other spec in the suite runs as
 * a lab-scoped staff persona, for which the provider dimension is invisible.
 *
 * ---------------------------------------------------------------- PERSONA
 *
 * Read out of the RUNNING instance (D178), never from install_roles.php, and
 * via `WP_User->allcaps` rather than user_can() (D255). The live `doctor` role
 * carries exactly ten capabilities:
 *
 *   read, level_1, level_0, form_pcr, form_blood, ci_test_results,
 *   ci_patient_chart, ci_patient_trends, ci_phlebotomist, ci_abx_report
 *
 * The catalogue (personas.md:123) additionally claims form_requisition,
 * form_lab_test_req, form_gene, clinic_statistics and tox_report. NONE of those
 * is live. The consequences are load-bearing and both are asserted below:
 *   - the ordering path a provider can actually reach is PCR and BLOOD, not
 *     tox: admin.php?page=tox_req_form answers a 2 629-byte denial body;
 *   - `covid_reporting` is absent, so the doctor branch of the S-STA-06 scope
 *     map is unreachable. See the fixme at the end of this file.
 *
 * ---------------------------------------- THE SEEDED PERSONA, AS IT ACTUALLY IS
 *
 * `e2e_doctor` (env/seed.php:335) is created with role `doctor` and with
 * `_lab_id` / `_lab_ids` pointing at the seed's own `E2E` lab plus
 * `_clinic_id`. It carries **no `_doc_id` at all**, and lab-wise it is bound to
 * the E2E lab, not to this spec's pool lab. Both gaps are closed here, at test
 * time, because a `doctor` with no `_doc_id` is not a scoped provider: the
 * listing interpolates the empty value straight into `and ba.doc_ID = ` and the
 * query dies. See armProvider().
 *
 * ------------------------------------------- WHY `_lab_ids` IS THE REAL LEVER
 *
 * D032/D106. The lab predicate on both screens under test is written only when
 * `CI_Base_Lab::has_user_labs()` is true, and that is true only when the
 * PLURAL `_lab_ids` meta is non-empty:
 *
 *   inc/functions.php:5317-5321   (Test Results, via trpdd_get_batch_conditions)
 *   parts/pcr/patient_chart.php:19-22            (Patient Chart)
 *
 * With `_lab_ids` absent there is no lab predicate at all, so the provider sees
 * every active non-reference lab and a tenancy assertion passes while proving
 * the opposite of what it claims. This file therefore sets BOTH keys, and the
 * two scoping tests each end by DELETING `_lab_ids` and re-running the very
 * same query to show the foreign-lab row appearing. That step is the
 * non-vacuity proof; without it the disjointness above could hold for the wrong
 * reason.
 *
 * ------------------------------------------------------------ PERSONA STATE
 *
 * The two scoping tests rewrite `_doc_id` on `e2e_doctor` between queries --
 * that is the axis, and re-pointing the provider is the only way to hold the
 * query constant while the provider changes. The driven ordering test rewrites
 * it once more, to the POOL provider, because that is the only provider whose
 * clinic the PCR screen will offer this persona (see poolPractice). Two containment facts make this
 * safe without `specs/serial.spec.ts`:
 *   1. `e2e_doctor` is named by no other spec; the only other reference in the
 *      tree is the identity guard in setup/env.setup.ts, which runs before
 *      `main` and only reads profile.php.
 *   2. describe.configure({ mode: 'default' }) below pins this FILE to one
 *      worker, so its own tests cannot race each other. (That is all a
 *      sequential mode does -- it does not serialise against other spec files,
 *      D150 -- and here that is exactly the scope required.)
 *
 *      `default` AND NOT `serial`, and the difference is not cosmetic: both
 *      run this file on one worker in declaration order, and only `serial`
 *      ALSO abandons every later test after the first failure. The driven
 *      ordering pin below is red until the PCR requisition routes on the
 *      clinic, so under `serial` it took the three provider/lab scoping tests
 *      -- the only tests in the suite that assert this persona sees its own
 *      patients and nobody else's -- out of every run, reported as "did not
 *      run". Nothing here depends on order: every test arms `_doc_id` and
 *      `_lab_ids` for itself through armProvider().
 * afterAll puts the three meta rows back to their seeded values, so a
 * `--no-deps` run leaves the shared stack as it found it.
 *
 * ------------------------------------------------------------------ TENANCY
 *
 * The tenant lab is LAJ, resolved from this filename by SPEC_LAB (D239).
 * Nothing here pins or overrides it. One deliberate exception to "every row I
 * create is in the tenant lab" (D172): each scoping test also creates a single
 * CONTROL specimen in the spare pool lab LAP, whose only purpose is to be
 * invisible. Its lab is asserted just as explicitly as the tenant rows'.
 */

e2e.describe.configure({ mode: 'default' });

// ---------------------------------------------------------------- constants

/** role `doctor`. env/seed.php:335. */
const PROVIDER = 'e2e_doctor';

/**
 * Spare pool lab (fixtures/tenant.ts: LAO and LAP carry no SPEC_LAB entry), used
 * as the foreign tenant the provider must not see. Never created here -- D161:
 * a lab created per test vanishes at the next reset, get_user_lab_ids() then
 * PERMANENTLY deletes `_lab_ids`, and the persona silently widens to every lab.
 */
const FOREIGN_LAB_PREFIX = 'LAP';

/** This file's band in the shared batch_id allocator (D396). */
const nextBatchId = batchIds('doc');

/** Auto-approving PCR reference data from the seed (env/sql/26-pcr-reference.sql). */
const PCR_GROUP = 'E2E STA PCR Panel';
const PCR_PANEL = 'E2E STA Covid Analyte';
const PCR_ABREV = 'E2ESTA1';

// ------------------------------------------------------------------ helpers

function ctxFor(baseURL: string | undefined, login: string): Promise<APIRequestContext> {
  return playwrightRequest.newContext({
    baseURL: baseURL ?? 'http://localhost',
    storageState: path.join(STATE_DIR, `${login}.json`),
  });
}

/** Everything this file needs from the DB in one exec (D164). */
function docInfra(db: Db): {
  uid: string; seedLabId: string; foreignLabId: string;
  docId: string; doc2Id: string; clinicId: string; clinic2Id: string;
  collectorId: string; tg: string; te: string;
} {
  const f = infra(db);
  const [row] = db.query(
    `SELECT (SELECT ID FROM wp_users WHERE user_login=${Db.q(PROVIDER)}),
            (SELECT lab_ID FROM ci_labs WHERE lab_dba_init='E2E'),
            (SELECT lab_ID FROM ci_labs WHERE lab_dba_init=${Db.q(FOREIGN_LAB_PREFIX)}),
            (SELECT tg_ID FROM ci_test_groups WHERE tg_name=${Db.q(PCR_GROUP)}),
            (SELECT te_ID FROM ci_test_panels WHERE te_name=${Db.q(PCR_PANEL)})`,
  );
  const [uid, seedLabId, foreignLabId, tg, te] = row ?? [];
  expect(uid, `${PROVIDER} does not exist -- run env/seed.sh`).toBeTruthy();
  expect(seedLabId, 'the seed E2E lab is missing -- run env/seed.sh').toBeTruthy();
  expect(foreignLabId, `pool lab ${FOREIGN_LAB_PREFIX} is missing -- run env/seed.sh`).toBeTruthy();
  expect(tg && te, `${PCR_GROUP}/${PCR_PANEL} are missing -- run env/seed.sh`).toBeTruthy();
  return {
    uid, seedLabId, foreignLabId, tg, te,
    docId: f.docId, doc2Id: f.doc2Id, clinicId: f.clinicId, clinic2Id: f.clinic2Id,
    collectorId: f.collectorId,
  };
}

/**
 * Point the persona at one lab and one provider, through the app's own meta API.
 *
 * NOT raw SQL: core's wp_usermeta has no unique index on (user_id, meta_key), so
 * `INSERT ... ON DUPLICATE KEY UPDATE` silently appends a SECOND row for the
 * same key and get_user_meta() then answers with whichever the storage engine
 * hands back first. update_user_meta() is the only writer with the right
 * semantics.
 *
 * Returns what the instance reads back, so a write that did not land is caught
 * here rather than surfacing as an empty result set three assertions later.
 */
function armProvider(
  uid: string, labId: string | null, docId: string | null,
): { lab_id: string; lab_ids: string; doc_id: string; use_current_clinic: string } {
  const set = (key: string, v: string | null) =>
    v === null
      ? `delete_user_meta($u,'${key}');`
      : `update_user_meta($u,'${key}',${JSON.stringify(v)});`;
  return wpEval(
    `$u=${uid};` +
    (labId === null
      // `_lab_id` is left alone: the singular key is not what gates the lab
      // predicate on either screen, and clearing it would change a second
      // variable at the same time as `_lab_ids`.
      ? set('_lab_ids', null)
      : set('_lab_id', labId) + set('_lab_ids', labId)) +
    set('_doc_id', docId) +
    `$o=['lab_id'=>(string)get_user_meta($u,'_lab_id',true),` +
    `'lab_ids'=>(string)get_user_meta($u,'_lab_ids',true),` +
    `'doc_id'=>(string)get_user_meta($u,'_doc_id',true),` +
    `'use_current_clinic'=>(string)get_user_meta($u,'_use_current_clinic_id',true)];`,
  ) as { lab_id: string; lab_ids: string; doc_id: string; use_current_clinic: string };
}

/** One ci_batches* row on this test's exact patient key -- never LIKE (D146). */
function oneBatch(db: Db, table: string, patientName: string, columns: string): string[] {
  const rows = db.query(
    `SELECT ${columns} FROM ${table} WHERE patient_name = ${Db.q(patientName)}`,
  );
  expect(rows.length, `expected exactly one ${table} row for ${patientName}`).toBe(1);
  return rows[0];
}

/**
 * Patient-name cells of the Patient Chart grid
 * (parts/pcr/patient_chart.php:141). Every fixture patient in this suite is
 * `<surname>, Synthetic`, so this reads the rendered result set exactly --
 * which is the D226 requirement: the outcome, never the status code.
 */
function chartPatients(body: string): string[] {
  return [...new Set([...body.matchAll(/<td>([^<]*, Synthetic)<\/td>/g)].map((m) => m[1]))].sort();
}

/** Batch rows of the Test Results grid (parts/pcr/results_list.php:392). */
function resultBatchIds(body: string): string[] {
  return [...new Set([...body.matchAll(/data-batch_id="(\d+)"/g)].map((m) => m[1]))].sort();
}

interface PcrSpec { patientLast: string; patientName: string; assen: string; batchId: string }

/**
 * One APPROVED PCR specimen, created through the app's own saver (D085/D099,
 * no nonce -- D043) BY THE PROVIDER, which is also the ordering path under test.
 *
 * The verb is `ci_save_form_pcr`, not `ci_save_pcr` (D226 case 3), and
 * `bat[panel_tests_requested][]` must be an ARRAY (D226 case 4) -- both are
 * handled by the shared fixture. The single NEG call on a group carrying
 * tg_auto_approve_pos_neg auto-approves the report to estatus='C'
 * (inc/actions/ci_save_pcr.php:1011-1017), which is what makes the specimen
 * visible on both screens: the chart requires estatus='C', the Test Results
 * listing requires a report row and `ba.batch_id > 0`.
 */
async function seedApprovedPcr(
  req: APIRequestContext, db: Db, i: {
    labId: string; labPrefix: string; docId: string; clinicId: string;
    patientLast: string; batchId?: string; tg: string; te: string;
  },
): Promise<PcrSpec> {
  const overrides: Record<string, string | string[]> = {
    'bat[cli_ID]': i.clinicId,
    'bat[doc_ID]': i.docId,
    'bat[type_of_specimen][]': ['nasal'],
    'bat[panel_tests_requested][]': [`GROUP_${i.tg}`, i.te],
    'pa[pa_state]': 'IL',
    [`results[${i.te}_${PCR_ABREV}_${i.tg}_][pos-neg]`]: 'NEG',
  };
  if (i.batchId) overrides['bat[batch_id]'] = i.batchId;

  const seeded = await seedPcrRequisition(req, db, i.labId, i.patientLast, overrides);
  // Not toBeLessThan(400): a login redirect is 302 and would pass that.
  expect(seeded.status, `PCR saver answered on ${i.patientLast}`).toBe(200);
  // D142/D221: rows GAINED on THIS test's key, never an AUTO_INCREMENT delta
  // and never a global table count.
  expect(
    seeded.keyRowsAfter - seeded.keyRowsBefore,
    `ci_batches_pcr gained no row for ${seeded.patientName}`,
  ).toBeGreaterThan(0);

  // One SELECT, not seven (D164).
  const [assen, labId, lab, docId, cliId, batchId, estatus, dupes] = oneBatch(
    db, 'ci_batches_pcr', seeded.patientName,
    `assen, lab_id, lab, doc_ID, cli_ID, batch_id,
     COALESCE((SELECT re.estatus FROM ci_reports_pcr re WHERE re.patient_id = assen),''),
     (SELECT COUNT(*) FROM ci_batches_pcr d WHERE d.assen = ci_batches_pcr.assen)`,
  );

  // D160: the saver answers 200 with assen='' when the minter fails, so
  // "non-empty" is the assertion, not "a row exists".
  expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
  expect(dupes, `accession ${assen} is not unique`).toBe('1');
  // TENANCY, asserted on every row (D172/D161). For the foreign control row
  // this asserts the CONTROL lab just as strictly.
  expect(labId, `ci_batches_pcr.lab_id for ${i.patientLast}`).toBe(i.labId);
  expect(lab, 'ci_batches_pcr.lab').toBe(i.labPrefix);
  expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(i.labPrefix);
  // The provider dimension, asserted at the row -- the whole file turns on it.
  expect(docId, 'ci_batches_pcr.doc_ID').toBe(i.docId);
  expect(cliId, 'ci_batches_pcr.cli_ID').toBe(i.clinicId);
  if (i.batchId) expect(batchId, 'ci_batches_pcr.batch_id').toBe(i.batchId);
  expect(estatus, 'the auto-approving panel left the report unapproved').toBe('C');

  return { patientLast: i.patientLast, patientName: seeded.patientName, assen, batchId };
}

// ===========================================================================
// Capability surface and page-layer gate
// ===========================================================================

e2e.describe('provider capability surface @doc', () => {
  e2e('the live doctor role reaches PCR/blood ordering and the two viewing screens, and nothing else', async ({
    db, pageAs,
  }) => {
    const f = docInfra(db);

    // D178 + D255: the RUNNING instance, read straight out of WP_User->allcaps.
    // user_can() is unusable here -- a numeric-key capability reroutes through
    // WordPress's deprecated user-LEVEL handling and answers true off level_0.
    const caps = wpEval(`$o=(new WP_User(${f.uid}))->allcaps;`) as Record<string, unknown>;
    const held = (c: string) => !!caps[c];

    expect(held('ci_test_results'), 'ci_test_results').toBe(true);
    expect(held('ci_patient_chart'), 'ci_patient_chart').toBe(true);
    expect(held('form_pcr'), 'form_pcr').toBe(true);
    expect(held('form_blood'), 'form_blood').toBe(true);
    // personas.md:123 claims these five. The live role has none of them, and
    // the two rendered denials below are the consequence.
    for (const c of ['form_requisition', 'form_lab_test_req', 'form_gene',
                     'clinic_statistics', 'tox_report']) {
      expect(held(c), `personas.md:123 claims ${c}; the running role does not grant it`).toBe(false);
    }
    // The blocker behind the S-STA-06 fixme at the bottom of this file.
    expect(held('covid_reporting'), 'covid_reporting').toBe(false);

    // ------------------------------------------------- the gate, as a UI fact
    //
    // WAVE 5 / D388 -- this SUPERSEDES the request-context/regex twin rather
    // than adding to it, and D382 does not apply: every line below issues the
    // SAME GET to the SAME handler the regexes did, so the conversion orphans
    // no caller. Each assertion is a strict superset of the regex it replaces.
    //
    // The regexes could not distinguish a screen from a stack trace: a PHP
    // fatal inside `pcr_req_form` answers 200 with no `name="tr_action"` in it
    // either, so `expect(marker.test(body)).toBe(true)` was carrying the whole
    // allowed branch on the presence of one attribute in an unexamined string.
    //
    // MENU REGISTRATION IS THE GATE (D250), so the menu is asserted too -- and
    // it is keyed on the PAGE SLUG IN THE href, not on the link's accessible
    // name. That is deliberate and it is the only shape that works with ONE
    // persona: a name-keyed `toHaveCount(0)` on the denied branch is true for
    // any product behaviour AND for any name typo, whereas the same href
    // locator is proven to resolve 1 on the four allowed rows in this very
    // loop. The href is what the browser navigates to and is 1:1 with the slug
    // under test -- app markup, not a test hook (D383).
    //
    // ENDS-WITH, not equals, and a fault injection is why. A page can be hung
    // under more than one parent: `ci_reports_state_report` is registered at
    // `inc/admin.php:404` under `index.php` and again at `:530` under
    // `ci_reports_main`, so it surfaces as `index.php?page=...` for a persona
    // that has no Reports parent. An `=` match on the `admin.php?page=` form
    // alone stayed GREEN with the page's capability swapped to `read` --
    // measured -- i.e. it could not see a menu entry that had appeared. The
    // four allowed rows' hrefs are exactly `admin.php?page=<slug>`, which ends
    // with `page=<slug>`, so the ends-with form keeps their proof and closes
    // the hole.
    const page = await pageAs(PROVIDER);
    await page.goto('/wp-admin/');
    // COUNT, not visibility -- and NOT because of a collapsible sidebar, which
    // is what an earlier draft of this comment claimed and is measurably wrong.
    // `pages/admin_welcome.php:171-185` runs the same `$account_error` branch
    // for `doctor` that `:138-148` runs for `collector`: the seeded provider
    // resolves no `_doc_id` and no `ci_doc.doc_email` match, so `:190-192`
    // prints a red banner and injects
    //     <style>#adminmenu,#adminmenuback{display:none !important}</style>
    // Measured on this stack, same 1440x900 context, /wp-admin/ -> page=welcome:
    // e2e_tech 1/visible, e2e_lead0 1/visible, e2e_multisite 1/visible,
    // e2e_collector 1/HIDDEN + banner, e2e_doctor 1/HIDDEN + banner. So the menu
    // IS registered and IS in the DOM -- which is what count reads, and what the
    // per-slug rows below need -- while the provider cannot actually see it.
    //
    // SAME FIXTURE GAP col.spec.ts reports for e2e_collector, second instance:
    // seeding a `ci_doc` row whose doc_email matches e2e_doctor clears it, and
    // both belong on one card. The banner is asserted rather than described, so
    // closing the gap reds this line instead of going quietly vacuous.
    //
    // LATENT, recorded so the next reader does not trip over it: closing that
    // gap turns this GET into a WRITE. `admin_welcome.php:178-182` runs
    // `update_user_meta($user->ID, '_doc_id', $doc_id)` on an e-mail match, and
    // `_doc_id` is this file's entire scoping axis. Harmless today (no match,
    // hence the banner) and this test is first in a serial file so nothing is
    // armed yet -- but a seed change makes a menu read a scope write.
    await expect(
      page.getByRole('heading', { name: 'Your Account not available. Please contact admin' }),
      'the seeded provider matches no ci_doc row, so its landing page is the locked-out one',
    ).toBeVisible();
    await expect(page.locator('#adminmenu'), 'the admin menu is registered and in the DOM')
      .toHaveCount(1);

    /**
     * Per screen: the slug, its query string, whether the provider may reach
     * it, the screen's own heading, and ONE CONTROL a person would press on it.
     * Every locator is asserted at `allowed ? 1 : 0` -- one boolean-driven
     * assertion rather than a denied/allowed branch pair (D388), which is what
     * forces each locator through both directions.
     *
     * THE DENIED ROWS CARRY REAL HEADINGS AND REAL CONTROLS, and that is the
     * leg an earlier draft dropped. This app hooks `admin_page_access_denied`
     * and ECHOES BEFORE core's wp_die (`parts/global/admin_page_access_denied
     * .php:37`), so a regression that prints the denial notice and then renders
     * the screen underneath it passes a test that only counts the notice.
     * col.spec.ts:452-462, ord.spec.ts:392-403 and sta.spec.ts:664-671 all keep
     * this leg; this file was the outlier.
     *
     * POSITIVE CONTROLS -- no `toHaveCount(0)` below is a string that exists
     * nowhere. Rows 1-4 assert their own heading and control at 1 in this same
     * loop. The tox banner is asserted at 1 by ord.spec.ts and its Submit by
     * rows 1-2 here; 'State Reporting' and 'Export Report' are asserted at 1 for
     * e2e_sec by sta.spec.ts:664 and :669. Measured here for Administrat0r on
     * all six slugs: heading 1/1/1/1/1/1, control 1/1/1/1/1/1.
     */
    type Screen = {
      slug: string; query: string; allowed: boolean; heading: string;
      control: string; locate: (body: Locator) => Locator;
    };
    const submit = (b: Locator) => b.getByRole('button', { name: 'Submit', exact: true });
    const screens: Screen[] = [
      { slug: 'pcr_req_form', query: '', allowed: true,
        heading: 'PCR REQUISITION FORM', control: 'a Submit button', locate: submit },
      { slug: 'blood_req_form', query: '', allowed: true,
        heading: 'Blood Requisition Form', control: 'a Submit button', locate: submit },
      { slug: 'ci_patient_chart', query: '&tab=pcr', allowed: true,
        heading: 'Patient Chart', control: 'the patient picker',
        // The heading and #patient_id are twenty lines apart in
        // `pages/admin_ci_patient_chart.php` (h2 at :61, input at :81), which is
        // the point: a fatal between them answers 200 with the heading present.
        locate: b => b.locator('#patient_id') },
      { slug: 'ci_test_results', query: '&tab=pcr', allowed: true,
        heading: 'PCR Results', control: 'the search box',
        locate: b => b.locator('input[name="s"]') },
      // form_requisition is not held: the tox ordering path personas.md
      // attributes to this persona does not exist for it.
      { slug: 'tox_req_form', query: '', allowed: false,
        heading: 'THERAPEUTIC DRUG TEST REQUISITION FORM', control: 'a Submit button',
        locate: submit },
      // covid_reporting is not held: this is S-STA-06's doctor branch, denied
      // before the scope map at parts/reports/state_report.php:167 is reached.
      { slug: 'ci_reports_state_report', query: '', allowed: false,
        heading: 'State Reporting', control: 'the Export Report control',
        locate: b => b.getByRole('button', { name: /Export Report/ }) },
    ];

    for (const { slug, allowed } of screens) {
      await expect(
        page.locator('#adminmenu').locator(`a[href$="page=${slug}"]`),
        `${allowed ? 'the provider can click through to' : 'nothing in the menu offers'} ${slug}`,
      ).toHaveCount(allowed ? 1 : 0);
    }

    // D185/D250: the page layer denies with HTTP 200 and a denial BODY, so the
    // status code decides nothing. What separates the two cases is what is drawn.
    for (const { slug, query, allowed, heading, control, locate } of screens) {
      await page.goto(`/wp-admin/admin.php?page=${slug}${query}`);
      // D392 -- the denied page is on a THREE-SECOND FUSE and the suite's
      // default 5 s expect timeout OUTLIVES it, so an unbounded assertion on a
      // loaded worker can grade the welcome page it bounces to. The allowed
      // rows keep the default: nothing on them is racing anything.
      const fused = allowed ? {} : { timeout: BEFORE_BOUNCE_MS };
      // Measured: `#wpbody-content` count 0 on both denied slugs, so a
      // body-scoped locator reads 0 for the reason the assertion states.
      const body = page.locator('#wpbody-content');
      await expect(
        page.getByText('Sorry, you are not allowed to access this page.'),
        `${slug} ${allowed ? 'shows the provider no denial' : "is refused in the provider's face"}`,
      ).toHaveCount(allowed ? 0 : 1, fused);
      await expect(
        body.getByRole('heading', { name: heading, exact: true }),
        `${slug} ${allowed ? 'draws' : 'never draws'} "${heading}" for the provider`,
      ).toHaveCount(allowed ? 1 : 0, fused);
      await expect(
        locate(body),
        `${slug} ${allowed ? 'offers the provider' : 'offers the provider no'} ${control}`,
      ).toHaveCount(allowed ? 1 : 0, fused);
      await expect(
        page.locator('#adminmenu'),
        `${slug} ${allowed ? 'keeps the admin chrome' : 'denies before any admin chrome is drawn'}`,
      ).toHaveCount(allowed ? 1 : 0, fused);
      // The fuse itself, pinned rather than waited out -- the line that reds if
      // it is retimed, which is what BEFORE_BOUNCE_MS is derived from (D392).
      await expect(
        page.locator('meta[http-equiv="refresh"][content^="3;"][content*="page=welcome"]'),
        `page=${slug} ${allowed ? 'carries no bounce' : 'carries the 3 s bounce to page=welcome'}`,
      ).toHaveCount(allowed ? 0 : 1, fused);
    }
  });
});

// ===========================================================================
// The ordering path a provider can reach
// ===========================================================================

e2e.describe('provider ordering @doc @mutates', () => {
  e2e('a provider orders PCR and blood into the tenant lab, authored by that provider', async ({
    db, tenant, runKey, baseURL,
  }) => {
    const f = docInfra(db);
    const armed = armProvider(f.uid, tenant.labId, f.docId);
    expect(armed.lab_ids, '_lab_ids after arming').toBe(tenant.labId);

    const req = await ctxFor(baseURL, PROVIDER);

    // PCR: the saver the doctor's `form_pcr` unlocks.
    const pcr = await seedApprovedPcr(req, db, {
      labId: tenant.labId, labPrefix: tenant.prefix, docId: f.docId, clinicId: f.clinicId,
      patientLast: `Docord${runKey}p`, tg: f.tg, te: f.te,
    });

    // BLOOD: the second and last ordering path the live role reaches.
    const bloodLast = `Docord${runKey}b`;
    const blood = await seedBloodRequisition(req, db, tenant.labId, bloodLast, {
      'bat[cli_ID]': f.clinicId,
      'bat[doc_ID]': f.docId,
    });
    expect(blood.status, 'the blood saver answers 200').toBe(200);
    expect(
      blood.keyRowsAfter - blood.keyRowsBefore,
      `ci_batches_blood gained no row for ${blood.patientName}`,
    ).toBeGreaterThan(0);

    const [bAssen, bLabId, bLab, bDoc, bAuthor, bDupes] = oneBatch(
      db, 'ci_batches_blood', blood.patientName,
      `assen, lab_id, lab, doc_ID, author_ID,
       (SELECT COUNT(*) FROM ci_batches_blood d WHERE d.assen = ci_batches_blood.assen)`,
    );
    expect(bAssen, 'blood accession is empty').not.toBe('');
    expect(bDupes, `blood accession ${bAssen} is not unique`).toBe('1');
    expect(bLabId, 'ci_batches_blood.lab_id').toBe(tenant.labId);
    expect(bLab, 'ci_batches_blood.lab').toBe(tenant.prefix);
    expect(bAssen.slice(0, 3), `accession prefix of ${bAssen}`).toBe(tenant.prefix);
    expect(bDoc, 'ci_batches_blood.doc_ID').toBe(f.docId);
    // The provider is the author of what the provider ordered.
    expect(bAuthor, 'ci_batches_blood.author_ID is the provider user').toBe(f.uid);

    const [pAuthor] = oneBatch(db, 'ci_batches_pcr', pcr.patientName, 'author_ID');
    expect(pAuthor, 'ci_batches_pcr.author_ID is the provider user').toBe(f.uid);

    await req.dispose();
  });
});

// ===========================================================================
// The ordering path a provider can reach, DRIVEN
// ===========================================================================

/**
 * select2 4.x renders the native <select> `aria-hidden` with `tabindex=-1`
 * (measured on this stack for `bat[cli_ID]`, `bat[doc_ID]`,
 * `dagnostic_code_select`, `pa[pa_state]`, `pa[pa_gender]`, `ins1[ins_state]`),
 * so `selectOption()` fails Playwright's visibility check. D383 fallback 5:
 * click the rendered combobox, then pick the option -- select2 marks options
 * `role=treeitem`, NOT `role=option`.
 *
 * DUPLICATED from ord.spec.ts on purpose. That copy's comment says it moves to
 * fixtures/ when a second spec needs one, and this is that second spec -- but
 * `fixtures/` is shared with the other conversions running concurrently, so the
 * move is REPORTED rather than made. Two twelve-line helpers is the cheaper
 * mistake than a fixture collision.
 */
async function pickSelect2(page: Page, name: string, label: string) {
  await page.locator(`select[name="${name}"] + span.select2`).getByRole('combobox').click();
  await page.getByRole('treeitem', { name: label, exact: true }).click();
}

/**
 * The DOB field is `readonly` behind a jQuery-UI datepicker, so typing into it
 * is not a user action that exists. `monthIndex` is 0-based because jQuery UI's
 * month <select> is, and the year is chosen FIRST because the current year's
 * month list is truncated by the `limitfuture` maxDate.
 */
async function pickDate(page: Page, name: string, year: string, monthIndex: string, day: string) {
  await page.locator(`input[name="${name}"]`).click();
  const dp = page.locator('#ui-datepicker-div');
  await dp.locator('select.ui-datepicker-year').selectOption(year);
  await dp.locator('select.ui-datepicker-month').selectOption(monthIndex);
  await dp.getByRole('link', { name: day, exact: true }).click();
}

/**
 * The clinic and ordering provider wave 0.2 created FOR THIS POOL LAB, and the
 * ONLY (provider, clinic, lab) triple the PCR screen will offer this persona.
 *
 * `docInfra()` hands back the two SHARED practices (clinic 1 / doc 1, clinic 2 /
 * doc 2), both of which carry `cli_lab-ID` = the lab-35 E2E lab. The screen
 * intersects TWO predicates for a `doctor`: `cID in (select cli_ID from
 * ci_clinic_doc where doc_ID = <_doc_id>)` (`inc/functions.php:5155`) and
 * ``cli_lab-ID` in (<_lab_ids>)`` (`:5324`). With `_lab_ids` pointing at the
 * pool lab, as every test in this file sets it, that intersection with a shared
 * clinic is EMPTY -- measured: `bat[cli_ID]` renders one option, "Select
 * Clinic", and `bat[cli_ID]` is `required`, so the form cannot be submitted at
 * all. The pool pair is the only one that survives both predicates.
 */
function poolPractice(db: Db, prefix: string) {
  const [row] = db.query(
    `SELECT c.cID, d.doc_ID, CONCAT(d.doc_last, ' ', d.doc_first)
       FROM ci_clinic c
       JOIN ci_doc d ON d.cli_ID = c.cID
       JOIN ci_clinic_doc cd ON cd.doc_ID = d.doc_ID AND cd.cli_ID = c.cID
      WHERE c.cli_clinic = ${Db.q(`E2E Clinic ${prefix}`)}`,
  );
  if (!row) throw new Error(`no wave-0.2 practice for pool lab ${prefix} -- run env/seed.sh`);
  const [clinicId, docId, docLabel] = row;
  return { clinicId, docId, docLabel };
}

e2e.describe('provider ordering, driven @doc @mutates', () => {
  /**
   * THE PROVIDER'S ORDER AS THE PROVIDER PLACES IT.
   *
   * D382: this ADDS to the HTTP ordering test above, it does not replace it.
   * The two are not the same request and the difference is not cosmetic --
   * measured on the pinned form (`ci-data-works-arc` a7aa074d,
   * `parts/pcr/req_form.php`; the working tree is OFF-PIN on this submodule and
   * the diff there is `data-testid`/`data-btype` hooks plus subgroup JS, so
   * every selector below is one that exists at BOTH revisions -- notably the
   * submit is addressed as `value="Submit"`, never the local-only
   * `data-testid="req-submit"`):
   *
   *   - the form emits NO `bat[lab_id]`, asserted below as a rendered fact. The
   *     destination lab is DERIVED from the clinic. The HTTP twin hands the
   *     saver the lab id it then asserts came back; this test asserts the app
   *     ROUTED the specimen with nothing but the clinic and the session.
   *   - the form emits `col[col_ID]`; the fixture never sends a collector at
   *     all for PCR, so `ci_batches_pcr.col_ID` has never been asserted.
   *   - the screen offers exactly ONE clinic, and it is NOT the clinic the HTTP
   *     twin posts. The twin posts `bat[cli_ID]` = the shared lab-35 clinic
   *     while `_lab_ids` says the pool lab, and the saver takes it. No screen
   *     can produce that combination (see poolPractice above). The twin
   *     therefore exercises a routing the product's own UI forbids -- which is
   *     worth keeping, and is exactly why it is kept.
   *
   * NEW COVERAGE, not a re-skin. The fixture sends 8 fields. The form requires
   * 25 controls, and the ones it never sends are the patient address block, the
   * whole `ins1[...]` block and the diagnostic code -- which is why
   * `ci_patients` and `ci_insurances` are asserted below and are asserted
   * NOWHERE in the PCR ordering path today.
   *
   * D386 -- the read-back is a SERVER RENDER by construction: `#requisition_form`
   * is `method=post` with no XHR, so the confirmation asserted below IS the
   * saver's own response, and the accession is re-read off a SECOND server
   * render reached through the confirmation's own Edit link.
   *
   * D396 -- this cannot race the HTTP twin. Its patient key is
   * `Docordu<runKey>` against the twin's `Docord<runKey>p`/`Docord<runKey>b`,
   * and every read is an exact match on that key. This file is
   * sequential (`mode: 'default'`, top of file), so it cannot race the other
   * tests here either.
   */
  e2e('a provider fills the PCR requisition and the app routes it to the provider\'s own lab', async ({
    db, tenant, runKey, pageAs,
  }) => {
    const f = docInfra(db);
    const practice = poolPractice(db, tenant.prefix);
    const patientLast = `Docordu${runKey}`;
    const patientName = `${patientLast}, Synthetic`;

    const armed = armProvider(f.uid, tenant.labId, practice.docId);
    expect(armed.lab_ids, '_lab_ids after arming').toBe(tenant.labId);
    expect(armed.doc_id, '_doc_id after arming').toBe(practice.docId);

    // PRECONDITION BEFORE NAVIGATION (D034). Everything asserted afterwards has
    // to have been created by this test.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_batches_pcr WHERE patient_name = ${Db.q(patientName)}`),
      'no PCR specimen for this patient key before the provider starts',
    ).toBe('0');
    expect(db.scalar(`SELECT COUNT(*) FROM ci_patients WHERE pa_last = ${Db.q(patientLast)}`),
      'no patient record before the provider starts').toBe('0');
    expect(db.scalar(`SELECT COUNT(*) FROM ci_insurances WHERE ins_last = ${Db.q(patientLast)}`),
      'no insurance record before the provider starts').toBe('0');

    const page = await pageAs(PROVIDER);
    await page.goto('/wp-admin/admin.php?page=pcr_req_form');
    // The screen DREW. A PHP fatal inside the render also answers 200.
    await expect(
      page.getByRole('heading', { name: 'PCR REQUISITION FORM', exact: true }),
      'the provider is looking at the PCR requisition form',
    ).toBeVisible();

    // TENANCY, ON SCREEN, BEFORE ANYTHING IS TYPED. Two facts, and together
    // they are the claim no HTTP test in this suite can make: the provider is
    // offered its own practice and nothing else, and there is no lab control at
    // all, so whatever lab the row lands in was chosen by the app.
    await expect(page.locator('select[name="bat[cli_ID]"] option'),
      'the provider is offered exactly one practice -- its own -- plus the placeholder')
      .toHaveCount(2);
    await expect(page.locator('select[name="bat[cli_ID]"]'),
      'and it is pre-selected, so the provider never chooses a lab').toHaveValue(practice.clinicId);
    // POSITIVE CONTROL for the count-0 below, so a field RENAME cannot turn it
    // green everywhere: measured 2026-08-25, `Administrat0r` renders this exact
    // locator with 19 options on both pcr_req_form and tox_req_form, while
    // e2e_dir and e2e_lead0 render 0 -- the multi-lab branch at
    // parts/tox/req_form.php:463-468 (D404). No test asserts the 19 (no
    // multi-lab persona is seeded); the measurement is the control.
    await expect(page.locator('[name="bat[lab_id]"]'),
      'the provider posts NO lab id -- routing is the app\'s job').toHaveCount(0);

    // ORDER. `dagnostic_code_select` is `required` and the fixture sends no
    // diagnostic code at all.
    await page.getByRole('checkbox', { name: 'E2E STA PCR Panel', exact: true }).check();
    await pickSelect2(page, 'dagnostic_code_select',
      'Z20.822 - Contact with and (suspected) exposure to COVID-19');

    // PATIENT. `pa_last`/`pa_first` are the only two fields the HTTP fixture
    // and this test have in common in this block.
    await page.locator('input[name="pa[pa_last]"]').fill(patientLast);
    await page.locator('input[name="pa[pa_first]"]').fill('Synthetic');
    // FAILURE MODE 7 -- the probe and its control, in the same document.
    // `ci_batches_pcr` and `ci_patients` are both `latin1_swedish_ci` on this
    // stack, so Address takes the bytes latin1 CANNOT hold and City takes bytes
    // it can (CHARSET, in fixtures/seed-domain.ts). A Vietnamese surname is not
    // an edge case in a laboratory; `ễ` is U+1EC5 and latin1 has no such byte.
    await page.locator('input[name="pa[pa_address]"]').fill(CHARSET.vietnamese);
    await page.locator('input[name="pa[pa_city]"]').fill(CHARSET.latin1Safe);
    await pickSelect2(page, 'pa[pa_state]', 'Illinois');
    await page.locator('input[name="pa[pa_zip]"]').fill('60601');
    await pickSelect2(page, 'pa[pa_gender]', 'Female');
    await pickDate(page, 'pa[pa_dob]', '1950', '0', '1');
    await page.locator('input[name="pa[pa_phone]"]').fill('3125550100');

    // INSURANCE. Ten controls the HTTP fixture sends none of.
    // `ins1[ins_relation]` is addressed by `name=` rather than by role: the
    // SECONDARY insurance block renders the identical three labels, so
    // `getByRole('radio', { name: 'Self' })` is ambiguous. `name=` is what the
    // browser itself submits and is 1:1 with the column (D383 fallback 3).
    await page.locator('input[name="ins1[ins_last]"]').fill(patientLast);
    await page.locator('input[name="ins1[ins_first]"]').fill('Synthetic');
    await page.locator('input[name="ins1[ins_relation]"][value="Self"]').check();
    await page.locator('input[name="ins1[ins_company]"]').fill('E2E-Insurer');
    // THE SAME BYTES, one form field lower, into a table that is `utf8mb3`
    // rather than `latin1` (information_schema, read 2026-08-26). Asserted
    // UNPINNED below, and that is the point: if these survive while
    // `pa_address` does not, the browser, jQuery-validate, the POST and the
    // saver are all demonstrably fine and the storage column is the fault.
    await page.locator('input[name="ins1[ins_address]"]').fill(CHARSET.vietnamese);
    await page.locator('input[name="ins1[ins_city]"]').fill(CHARSET.apostrophe);
    await pickSelect2(page, 'ins1[ins_state]', 'Illinois');
    await page.locator('input[name="ins1[ins_zip]"]').fill('60601');
    await page.locator('input[name="ins1[ins_member_id]"]').fill('E2EMEM0001');
    // A plain <select>, NOT select2 -- measured, it has no `span.select2`
    // sibling, so pickSelect2 would hang on it.
    await page.locator('select[name="ins1[ins_authorization]"]').selectOption({ label: 'Yes' });

    // THE ACCESSION IS LEFT TO THE APP, and doing that is a click on this
    // build. `req_form.php:1782` renders "Auto Generated" CHECKED, but the
    // clinic-select handler at `:2258-2267` re-reads the clinic's
    // `cli_auto_assen` flag and, when it is falsy -- which it is for the
    // wave-0.2 pool clinics -- UNCHECKS the box and makes both accession fields
    // `required`. Measured on load: checked === false. So the provider must
    // tick it, and only then is the number minted server-side by
    // tr_get_new_assen() rather than typed by this test. That is what keeps the
    // read-back below rendered-vs-queried and not a value compared to itself
    // (D397).
    const auto = page.getByRole('checkbox', { name: 'Auto Generated', exact: true });
    await auto.check();
    await expect(page.locator('#number_assen'),
      'ticking Auto Generated clears the accession box the provider would otherwise fill')
      .toHaveValue('');

    // SUBMIT. Reaching the saver at all is the jQuery-validate assertion: the
    // form carries `class="need_validate"` and 25 required controls, and an
    // incomplete document never leaves the browser -- it raises
    // "Please complete all required fields!" in an alert instead, measured.
    // There are three submits on this form -- Submit, "Print Barcode / Submit"
    // and "Save temporary" -- so the name match is exact.
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
    await page.waitForLoadState('load');

    // ---------------------------------------------------------------- the DB
    const [mid, assen, labId, lab, docId, cliId, colId, author, panels, paId, dupes] = oneBatch(
      db, 'ci_batches_pcr', patientName,
      `mid, assen, lab_id, lab, doc_ID, cli_ID, col_ID, author_ID, panel_tests_requested,
       patient_id,
       (SELECT COUNT(*) FROM ci_batches_pcr d WHERE d.assen = ci_batches_pcr.assen)`,
    );
    // D160: the saver answers 200 with assen='' when the minter fails.
    expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
    expect(dupes, `accession ${assen} is not unique`).toBe('1');
    // THE ROUTING CLAIM. The provider posted no lab id; these three columns can
    // only have come from the clinic the screen offered and the session.
    expect(labId, 'ci_batches_pcr.lab_id -- derived, not posted').toBe(tenant.labId);
    expect(lab, 'ci_batches_pcr.lab').toBe(tenant.prefix);
    // The DB row, not the screen -- `assen` comes from oneBatch(). Kept anyway
    // and NOT redundant: the tuple above asserts no prefix, and this test's
    // path never reaches fixtures/assert-domain.ts:85.
    expect(assen.slice(0, 3), `ci_batches_pcr.assen carries the ${tenant.prefix} prefix`)
      .toBe(tenant.prefix);
    expect(cliId, 'ci_batches_pcr.cli_ID -- the practice that routed the specimen')
      .toBe(practice.clinicId);
    expect(docId, 'ci_batches_pcr.doc_ID -- the provider the screen pre-selected')
      .toBe(practice.docId);
    // Written from col[col_ID]. The PCR fixture sends no collector key at all,
    // so nothing in the suite has ever asserted this column.
    expect(colId, 'ci_batches_pcr.col_ID -- written from col[col_ID]').toBe(f.collectorId);
    expect(author, 'ci_batches_pcr.author_ID is the provider user').toBe(f.uid);
    expect(panels.split(',').sort(), 'the panel and its analyte, as the checkbox posts them')
      .toEqual([`GROUP_${f.tg}`, f.te].sort());

    // ------------------------------------------------------------ the screen
    // THE OPERATOR-FACING NUMBER IS THE FILED NUMBER. The saver composes this
    // line itself; the provider left the accession to `auto_generated`, so the
    // number is not one this test typed. A save that files LAJ1234570 while
    // telling the provider LAJ1234569 mislabels the tube.
    const confirmation = page.locator('.q_message').filter({
      hasText: `Saved REQUISITION FORM ${assen}`,
    });
    await expect(confirmation,
      'the provider is told, in the app\'s own words, which accession was filed').toBeVisible();

    // THE SECOND RENDER. The saver's response re-renders a BLANK form for the
    // next order, so reading the accession off it would be reading a field the
    // client blanked. The confirmation's Edit link is the provider's own way
    // back to the record: follow it and read the number off the server's render
    // of the SAVED row.
    await confirmation.getByRole('link', { name: 'Edit', exact: true }).click();
    await page.waitForLoadState('load');
    expect(page.url(), 'the Edit link points at the record that was just filed')
      .toContain(`id=${mid}`);
    await expect(page.locator('#number_assen'),
      'the saved requisition reads back the same accession').toHaveValue(assen);

    // THE PATIENT RECORD. Nine columns the PCR ordering path has never
    // asserted, because the fixture sends two of them and no address at all.
    const patients = db.query(
      `SELECT pa_ID, pa_first, pa_dob, pa_gender, pa_address, pa_city, pa_state, pa_zip, pa_phone
         FROM ci_patients WHERE pa_last = ${Db.q(patientLast)}`,
    );
    expect(patients.length, `exactly one ci_patients row for ${patientLast}`).toBe(1);
    const [patientRowId, paFirst, paDob, paGender, paAddress, paCity, paState, paZip, paPhone] =
      patients[0];
    expect(patientRowId, 'the specimen is linked to that patient record').toBe(paId);
    // `pa_address` is held back for the mode-7 pin at the very end of this
    // test. Everything else round-trips HERE, unmarked -- including the
    // ACCENTED city: `é` is latin1 0xE9, so `José-City` coming back mangled
    // would be a transport or saver fault and not the column charset.
    expect([paFirst, paDob, paGender, paCity, paState, paZip, paPhone],
      'ci_patients [first, dob, gender, city, state, zip, phone]')
      .toEqual(['Synthetic', '1950-01-01', 'F', CHARSET.latin1Safe, 'IL', '60601', '3125550100']);

    // THE INSURANCE RECORD. Ten controls, one row, and nothing in this suite
    // asserts a PCR insurance row today. `ins_company` is asserted as the app
    // WRITES it, not as the provider typed it: the saver title-cases the value,
    // so "E2E-Insurer" is stored "E2e-insurer" -- measured. Asserting the typed
    // string would fail; asserting a lowercased comparison would hide the
    // mangling of a payer name that goes onto a claim.
    const insurances = db.query(
      `SELECT ins_first, ins_relation, ins_company, ins_address, ins_city, ins_state,
              ins_zip, ins_member_id
         FROM ci_insurances WHERE ins_last = ${Db.q(patientLast)}`,
    );
    expect(insurances.length, `exactly one ci_insurances row for ${patientLast}`).toBe(1);
    expect(insurances[0], 'ci_insurances [first, relation, company, address, city, state, zip, member]')
      .toEqual(['Synthetic', 'Self', 'E2e-insurer', CHARSET.vietnamese, CHARSET.apostrophe, 'IL',
                '60601', 'E2EMEM0001']);

    console.log(`DOC-ORD-u lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen}`);

    // ----------------------------------------- FAILURE MODE 7, PINNED (D418)
    //
    // The seventh way this app answers 200 while doing the wrong thing: the row
    // is ACCEPTED, STORED CORRUPTED, and reported as saved. `ci_patients` is
    // `latin1_swedish_ci`; the connection is utf8mb4; `wpdb::strip_invalid_text()`
    // short-circuits on a latin1 column ("latin1 can store any byte sequence"),
    // so the bytes reach MySQL unvalidated and MySQL substitutes `?` for every
    // character it cannot represent. Measured through the app's own connection:
    // `Nguyễn-Way` stores as `Nguy?n-Way`, `insert()` returns 1, and the
    // provider is told "Saved REQUISITION FORM <accession>". The other six modes
    // all mean "nothing was written" and a row count catches them; this one
    // writes the wrong patient and no row count ever will.
    //
    // THE MARKER IS HERE AND NOWHERE EARLIER. Everything above -- the accession,
    // the routing claim, the screen, the accented city, the identical bytes that
    // survived in `ci_insurances` -- runs UNMARKED, so a failure in any of it is
    // reported as the failure it is. Only the two lines below are expected to
    // fail, and only for this reason. Delete `e2e.fail()` the day the PHI tables
    // are converted to utf8mb4; the pin goes red the moment the defect is fixed,
    // which is what asks for the deletion.
    expect(paAddress,
      `the provider typed ${CHARSET.vietnamese} into Address -- ci_patients.pa_address holds `
      + 'those characters, not a question mark')
      .toBe(CHARSET.vietnamese);
    await expect(page.locator('input[name="pa[pa_address]"]'),
      'and the saved requisition shows the provider back the address they typed')
      .toHaveValue(CHARSET.vietnamese);
  });
});

// ===========================================================================
// Provider-scoped visibility -- Patient Chart
// ===========================================================================

e2e.describe('Patient Chart provider scoping @doc @mutates', () => {
  e2e('two providers running the identical chart search get disjoint result sets', async ({
    db, tenant, runKey, baseURL,
  }) => {
    const f = docInfra(db);
    const req = await ctxFor(baseURL, PROVIDER);
    const key = `Docchart${runKey}`;

    armProvider(f.uid, tenant.labId, f.docId);

    // Three specimens, one search key. Only the provider and the lab differ.
    const own = await seedApprovedPcr(req, db, {
      labId: tenant.labId, labPrefix: tenant.prefix,
      docId: f.docId, clinicId: f.clinicId, patientLast: `${key}a`, tg: f.tg, te: f.te,
    });
    const other = await seedApprovedPcr(req, db, {
      labId: tenant.labId, labPrefix: tenant.prefix,
      docId: f.doc2Id, clinicId: f.clinic2Id, patientLast: `${key}b`, tg: f.tg, te: f.te,
    });
    // The CONTROL: same provider as `own`, different LAB. Invisible only if the
    // `_lab_ids` predicate is really written (D032/D106).
    const foreign = await seedApprovedPcr(req, db, {
      labId: f.foreignLabId, labPrefix: FOREIGN_LAB_PREFIX,
      docId: f.docId, clinicId: f.clinicId, patientLast: `${key}c`, tg: f.tg, te: f.te,
    });

    const chart = async () => chartPatients(
      await (await req.get(
        `/wp-admin/admin.php?page=ci_patient_chart&tab=pcr&s=${key}`)).text(),
    );

    const armedA = armProvider(f.uid, tenant.labId, f.docId);
    expect(armedA.doc_id, '_doc_id for provider A').toBe(f.docId);
    // `_use_current_clinic_id` would add a second predicate
    // (parts/pcr/patient_chart.php has none, but ci_load_test_results.php:122
    // does) and would make "the provider is the only variable" untrue.
    expect(Number(armedA.use_current_clinic) > 0, '_use_current_clinic_id is unset').toBe(false);
    const seenA = await chart();

    const armedB = armProvider(f.uid, tenant.labId, f.doc2Id);
    expect(armedB.doc_id, '_doc_id for provider B').toBe(f.doc2Id);
    const seenB = await chart();

    // The scoping is REAL: same query, two providers, disjoint result sets --
    // not merely "each provider sees its own row".
    expect(seenA, 'provider A sees exactly its own patient').toEqual([own.patientName]);
    expect(seenB, 'provider B sees exactly its own patient').toEqual([other.patientName]);
    expect(seenA.filter((n) => seenB.includes(n)), 'the two result sets overlap').toEqual([]);
    // Neither provider sees the foreign-lab specimen, even though its doc_ID
    // is provider A's.
    expect([...seenA, ...seenB], 'the foreign-lab specimen leaked into a chart')
      .not.toContain(foreign.patientName);

    // ------------------------------------------------------------ D032/D106
    // Non-vacuity. With `_lab_ids` gone, has_user_labs() is false, the
    // `ba.lab_id in (...)` predicate at parts/pcr/patient_chart.php:19-22 is
    // never written, and the SAME query now returns the foreign-lab row. If
    // this leg did not go green, the assertions above would be passing on a
    // query that never had a lab predicate to begin with.
    try {
      const widened = armProvider(f.uid, null, f.docId);
      expect(widened.lab_ids, '_lab_ids was cleared for the widening check').toBe('');
      expect(
        await chart(),
        'clearing _lab_ids must widen the chart to every active non-ref lab (D032/D106)',
      ).toEqual([own.patientName, foreign.patientName].sort());
    } finally {
      armProvider(f.uid, tenant.labId, f.docId);
    }

    await req.dispose();
  });
});

// ===========================================================================
// Provider-scoped visibility -- Test Results
// ===========================================================================

e2e.describe('Test Results provider scoping @doc @mutates', () => {
  e2e('two providers opening the identical Test Results tab get disjoint batch sets', async ({
    db, tenant, runKey, baseURL,
  }) => {
    const f = docInfra(db);
    const req = await ctxFor(baseURL, PROVIDER);
    const key = `Doclist${runKey}`;

    // The Test Results listing groups by batch_id and drops `ba.batch_id = 0`
    // (parts/pcr/results_list.php:40), so each specimen needs its own batch id.
    // From the SHARED allocator (D396): the open-coded base this replaced --
    // `1.700e9 + (Date.now() % 14e6) * 3` -- was not in SPEC_OFFSET and entered
    // fixtures/batch-id.ts's own band whenever `t_s % 2e6` fell in
    // [1_700_000, 1_742_000). Three successive calls are contiguous by
    // construction, which is what this test's `.sort()` comparisons want.
    const [bidOwn, bidOther, bidForeign] = [nextBatchId(), nextBatchId(), nextBatchId()]
      .map(String);

    armProvider(f.uid, tenant.labId, f.docId);

    await seedApprovedPcr(req, db, {
      labId: tenant.labId, labPrefix: tenant.prefix, docId: f.docId, clinicId: f.clinicId,
      patientLast: `${key}a`, batchId: bidOwn, tg: f.tg, te: f.te,
    });
    await seedApprovedPcr(req, db, {
      labId: tenant.labId, labPrefix: tenant.prefix, docId: f.doc2Id, clinicId: f.clinic2Id,
      patientLast: `${key}b`, batchId: bidOther, tg: f.tg, te: f.te,
    });
    await seedApprovedPcr(req, db, {
      labId: f.foreignLabId, labPrefix: FOREIGN_LAB_PREFIX, docId: f.docId, clinicId: f.clinicId,
      patientLast: `${key}c`, batchId: bidForeign, tg: f.tg, te: f.te,
    });

    // `nocache=1` bypasses the 3 600 s Tr_Cache_Query on the row COUNT
    // (parts/pcr/results_list.php:215-221), whose SQL carries no per-run key and
    // would otherwise be shared across runs. The row list itself is keyed on the
    // batch ids, which are per-run.
    const listed = async () => {
      const body = await (await req.get(
        '/wp-admin/admin.php?page=ci_test_results&tab=pcr&nocache=1')).text();
      // Only this run's batches -- the screen legitimately shows anything else
      // the provider is entitled to, and a set-equality on the whole grid would
      // be a cross-run race.
      return resultBatchIds(body).filter((b) => [bidOwn, bidOther, bidForeign].includes(b));
    };

    armProvider(f.uid, tenant.labId, f.docId);
    const seenA = await listed();
    armProvider(f.uid, tenant.labId, f.doc2Id);
    const seenB = await listed();

    // The scope comes from trpdd_get_batch_conditions() (inc/functions.php:5153
    // `and ba.doc_ID = <_doc_id>`, live-gated on the ci_pdf_data option
    // `doctor_see_all`, which reads false on this instance) -- a different code
    // path from the chart's, asserted the same way.
    expect(seenA, 'provider A sees exactly its own batch').toEqual([bidOwn]);
    expect(seenB, 'provider B sees exactly its own batch').toEqual([bidOther]);
    expect(seenA.filter((b) => seenB.includes(b)), 'the two batch sets overlap').toEqual([]);
    expect([...seenA, ...seenB], 'the foreign-lab batch leaked into Test Results')
      .not.toContain(bidForeign);

    // D032/D106 again, on the other screen: the lab predicate here is written by
    // inc/functions.php:5317-5321 and is likewise conditional on `_lab_ids`.
    try {
      armProvider(f.uid, null, f.docId);
      expect(
        await listed(),
        'clearing _lab_ids must widen Test Results to every active non-ref lab (D032/D106)',
      ).toEqual([bidOwn, bidForeign].sort());
    } finally {
      armProvider(f.uid, tenant.labId, f.docId);
    }

    await req.dispose();
  });
});

// ===========================================================================
// Test Results LAB scoping -- the drill-down's own copy of the rule
// ===========================================================================

/**
 * THE SCREEN ENFORCES LAB SCOPE TWICE AND THE SUITE COVERED THE OUTER HALF.
 *
 * Tox Results is two queries. The day rows come from `parts/tox/test_results.php`,
 * which writes its own lab predicate at `:4-8`. Expanding a day fires
 * `tr_action=ci_load_test_results` (`:284`), and THAT handler writes the
 * predicate a second time, in its own words:
 *
 *   inc/actions/ci_load_test_results.php:22-26
 *     if (CI_Base_Lab::has_user_labs()) {
 *         $lab_str = CI_Base_Lab::get_user_lab_ids_str();
 *         $where .= " and (ba.lab_id in ( {$lab_str} ) or ba.ref_lab_ID in ( {$lab_str} ) )";
 *
 * Deleting that clause changed no test in the suite. Nothing drove the
 * drill-down at all: the two scoping tests above read the PCR tab's grid, which
 * is a different query in a different file, and the tox day rows were only ever
 * counted, never opened.
 *
 * WHY THE FOREIGN SPECIMEN IS SEEDED ON THE PROVIDER'S OWN CLINIC AND PROVIDER.
 * `ci_load_test_results.php:127` adds `and ba.doc_ID = <_doc_id>` for this role.
 * A foreign-lab specimen belonging to some OTHER provider is excluded by that
 * clause whether the lab clause exists or not, so it proves nothing about lab
 * scope -- which is exactly why the clause could be deleted unnoticed. The
 * specimen below is identical to the provider's own in every scoped column
 * (`cli_ID`, `doc_ID`, `doc`, `batch_status`) and carries `ref_lab_ID = 0`, so
 * the ONLY predicate that can hold it back is the lab clause.
 *
 * AND THAT IS EXACTLY WHY THE PROVIDER CLAUSE ITSELF WAS UNREACHABLE.
 * Seeding both rows on the reader's OWN `doc_ID` is what makes the lab clause
 * the sole separator -- and it leaves `:127` with nothing to exclude, so
 * deleting `and ba.doc_ID = <_doc_id>` changed no assertion in this suite
 * either. The fixture was monotonous along the provider axis on a screen whose
 * whole job is to be scoped by provider. The THIRD specimen below closes it: a
 * twin of the tenant row in lab, clinic, day, status and reference lab,
 * differing only in `doc_ID`. Two legs then read the SAME screen on the SAME
 * day in the SAME lab under the two providers and get disjoint sets -- each leg
 * asserting a presence as well as an absence, so neither can be satisfied by an
 * empty drill-down.
 *
 * WHY CLEARING `_lab_ids` IS THE NON-VACUITY PROOF, and not merely a nicety.
 * `TRPDD_SAME_LAB` is hard-defined true (`plug.php:25-26`), so the `else if` at
 * `:27` is dead code: a persona with no `_lab_ids` gets NO lab predicate at
 * all -- which is the same state the deleted clause would leave the app in.
 * The last leg therefore reproduces the fault deliberately and shows the
 * foreign accession appearing, so the assertion above it cannot be passing
 * because the specimen was invisible for some unrelated reason.
 *
 * DRIVEN, because the drill-down is not reachable any other way: it is a POST
 * fired by the click handler at `parts/tox/test_results.php:303-321`, and what
 * the provider sees is a table of accessions belonging to other people's
 * patients. Each read opens a FRESH context (D386) -- the screen also
 * auto-expands from the `active_test_results` cookie (`:403-411`), so a reused
 * page would either re-load the day behind the assertion or collapse it on the
 * next click.
 */
e2e.describe('Test Results lab scoping @doc @mutates', () => {
  e2e('the provider expanding a day sees their own specimens and not the identical ones in another lab or under another provider', async ({
    db, tenant, runKey, baseURL, pageAs, seedRequisition,
  }) => {
    const f = docInfra(db);
    const key = `Doclab${runKey}`;

    // Both specimens are created by the LAB persona, not the provider: the live
    // `doctor` role holds no `form_requisition` (asserted by the first test in
    // this file), so a provider cannot raise a tox requisition at all. The
    // provider is the READER here, which is the axis under test.
    const shared = {
      'bat[cli_ID]': f.clinicId,
      'bat[doc_ID]': f.docId,
      // Tox Results lists only completed/approved work
      // (ci_load_test_results.php:160).
      'bat[batch_status]': 'completed',
      // ref_lab_ID is the OTHER half of the clause under test. Left at 0 so the
      // foreign specimen cannot be pulled in through the reference-lab branch.
      'bat[ref_lab_ID]': '0',
    };
    const own = await seedRequisition(`${key}own`, shared);
    const foreign = await seedRequisition(`${key}for`, {
      ...shared, 'bat[lab_id]': f.foreignLabId,
    });
    // THE PROVIDER TWIN. Same lab, same clinic, same day, same status, same
    // reference lab as `own` -- the ONLY column that differs is `doc_ID`, which
    // is the one `ci_load_test_results.php:127` scopes on. Until this row
    // existed that clause had nothing to exclude on this screen.
    const twin = await seedRequisition(`${key}two`, {
      ...shared, 'bat[doc_ID]': f.doc2Id,
    });
    expect(own.status, 'the tox saver answered on the tenant specimen').toBe(200);
    expect(foreign.status, 'the tox saver answered on the foreign specimen').toBe(200);
    expect(twin.status, 'the tox saver answered on the second provider\'s specimen').toBe(200);

    // THE ROWS (D226/D172), in one exec each. Everything the two queries scope
    // on is asserted, so "invisible" below can only be about the lab.
    const cols = 'assen, lab_ID, ref_lab_ID, doc_ID, cli_ID, batch_status, DATE(doc)';
    const [ownAssen, ownLab, ownRef, ownDoc, ownCli, ownStatus, ownDay] =
      oneBatch(db, 'ci_batches', own.patientName, cols);
    const [forAssen, forLab, forRef, forDoc, forCli, forStatus, forDay] =
      oneBatch(db, 'ci_batches', foreign.patientName, cols);

    expect(ownAssen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
    expect(forAssen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
    expect(ownLab, 'the tenant specimen is in this spec\'s lab').toBe(tenant.labId);
    expect(forLab, `the foreign specimen is in ${FOREIGN_LAB_PREFIX}`).toBe(f.foreignLabId);
    expect(forLab, 'the two specimens are in DIFFERENT labs').not.toBe(ownLab);
    // Identical in every other scoped column -- this is what makes the lab
    // clause the only thing that can separate them.
    expect(
      { ref: forRef, doc: forDoc, cli: forCli, status: forStatus, day: forDay },
      'the foreign specimen matches the tenant one on every predicate except lab',
    ).toEqual({ ref: ownRef, doc: ownDoc, cli: ownCli, status: ownStatus, day: ownDay });
    expect(ownDoc, 'both specimens are authored by the provider under test').toBe(f.docId);
    expect(ownRef, 'and neither carries a reference lab').toBe('0');

    // The provider twin, asserted the same way: identical on every predicate
    // EXCEPT the provider, so "invisible" below can only be about `doc_ID`.
    const [twinAssen, twinLab, twinRef, twinDoc, twinCli, twinStatus, twinDay] =
      oneBatch(db, 'ci_batches', twin.patientName, cols);
    expect(twinAssen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
    expect(twinDoc, `the twin is ordered by the OTHER provider (doc_ID ${f.doc2Id})`)
      .toBe(f.doc2Id);
    expect(twinDoc, 'the two providers are DIFFERENT providers').not.toBe(ownDoc);
    expect(
      { lab: twinLab, ref: twinRef, cli: twinCli, status: twinStatus, day: twinDay },
      'the provider twin matches the tenant specimen on every predicate except the provider',
    ).toEqual({ lab: ownLab, ref: ownRef, cli: ownCli, status: ownStatus, day: ownDay });

    /** Open Tox Results in a FRESH context and expand the day both specimens sit on. */
    const openDay = async () => {
      const page = await pageAs(PROVIDER);
      // `nocache=1` bypasses the 16 400 s Tr_Cache_Query on the day COUNT
      // (parts/tox/test_results.php:97-101), which carries no per-run key.
      // `&tab=tox` IS REQUIRED, and finding out why is half of what this test
      // learned. `pages/admin_ci_test_results.php:44-58` STRIPS the tox tab for
      // role `doctor` unless the user holds `form_requisition`, which the live
      // role does not (asserted by the first test in this file) -- so the nav
      // link is absent. But `:60` reads `$_REQUEST['tab']` before falling back
      // to `key($tabs)`, and `:104` includes `parts/tox/test_results.php` on
      // that value alone: the strip hides the link, it does not gate the
      // screen. Without the parameter this landed on the pcr tab and the day
      // row was simply the wrong query.
      await page.goto('/wp-admin/admin.php?page=ci_test_results&tab=tox&nocache=1');
      const dayRow = page.getByRole('row').filter({ hasText: ownDay });
      await expect(dayRow, `Tox Results lists one day row for ${ownDay}`).toHaveCount(1);
      await dayRow.getByRole('cell', { name: ownDay, exact: true }).click();
      return page;
    };

    try {
      armProvider(f.uid, tenant.labId, f.docId);
      const scoped = await openDay();
      // The presence assertion FIRST: it is what proves the drill-down answered
      // at all, so the absence assertion below is not satisfied by an empty
      // table (the handler prints "Not found" when it matches nothing).
      await expect(
        scoped.getByRole('cell', { name: ownAssen, exact: true }),
        `the provider must see their own specimen ${ownAssen} in the expanded day`,
      ).toBeVisible();
      await expect(
        scoped.getByRole('cell', { name: forAssen, exact: true }),
        `${forAssen} belongs to lab ${FOREIGN_LAB_PREFIX} and must not appear on a provider `
        + `scoped to lab ${tenant.prefix} (ci_load_test_results.php:22-26)`,
      ).toHaveCount(0);
      await expect(
        scoped.getByRole('cell', { name: twinAssen, exact: true }),
        `${twinAssen} was ordered by another provider in the SAME lab, clinic and day, and `
        + `must not appear for provider ${f.docId} (ci_load_test_results.php:127)`,
      ).toHaveCount(0);

      // THE OTHER HALF OF THE PROVIDER AXIS, which is also its non-vacuity
      // proof: re-point the persona at the second provider and the identical
      // screen -- same day, same lab, same clinic -- swaps which specimen is on
      // it. Disjoint sets, not merely "each provider sees a row".
      const armedTwo = armProvider(f.uid, tenant.labId, f.doc2Id);
      expect(armedTwo.doc_id, '_doc_id now points at the second provider').toBe(f.doc2Id);
      const asTwo = await openDay();
      await expect(
        asTwo.getByRole('cell', { name: twinAssen, exact: true }),
        `provider ${f.doc2Id} must see their own specimen ${twinAssen} in the expanded day`,
      ).toBeVisible();
      await expect(
        asTwo.getByRole('cell', { name: ownAssen, exact: true }),
        `${ownAssen} was ordered by provider ${f.docId} and must not appear for provider `
        + `${f.doc2Id} -- the two providers share a lab, a clinic and a day`,
      ).toHaveCount(0);

      // THE NON-VACUITY PROOF, and the fault reproduced: with `_lab_ids` gone
      // the handler writes no lab predicate (TRPDD_SAME_LAB makes `:27` dead),
      // and the foreign accession appears on the same screen, same provider,
      // same day.
      armProvider(f.uid, null, f.docId);
      const wide = await openDay();
      await expect(
        wide.getByRole('cell', { name: ownAssen, exact: true }),
        'the provider still sees their own specimen with _lab_ids cleared',
      ).toBeVisible();
      await expect(
        wide.getByRole('cell', { name: forAssen, exact: true }),
        `clearing _lab_ids must widen the drill-down to every lab, exposing ${forAssen} `
        + '(D032/D106) -- if it does not, the assertion above proved nothing',
      ).toBeVisible();
    } finally {
      armProvider(f.uid, tenant.labId, f.docId);
    }
  });
});

// ===========================================================================
// S-STA-06 -- the doctor branch
// ===========================================================================

e2e.fixme(
  'S-STA-06 @doc @sta the state-reporting line list is scoped to the ordering provider',
  // Unreachable, and the blocker is a capability rather than a fixture gap.
  //
  // The scope map DOES exist and DOES branch on this persona:
  // parts/reports/state_report.php:167 `case 'doctor':` narrows the clinic list
  // through `_doc_id` -> ci_clinic_doc -> ci_clinic. But the page slug
  // `ci_reports_state_report` is registered with capability `covid_reporting`
  // (inc/admin.php:418), and the live `doctor` role's ten capabilities do not
  // include it -- asserted, not assumed, in the first test of this file. The
  // request therefore ends at the page layer with a 2 629-byte denial body
  // (HTTP 200, D185) and the switch at :154 is never evaluated.
  //
  // A second, independent blocker sits behind it: that branch resolves clinics
  // through `ci_clinic_doc`. Measured after wave 0.2, which added one clinic and
  // one provider per pool lab: the table holds 17 rows, (doc_ID, cli_ID) pairs
  // (1,1) and (3,3)…(18,18) -- one per provider EXCEPT `Testprovidertwo`, which
  // is doc_ID=2 and has no ci_clinic_doc row at all. So even with the capability
  // granted the doctor branch would return the same clinic list for both
  // providers this spec uses, and the test would pass vacuously -- the
  // 10-scenarios.md:160 objection, still open.
  //
  // What would close it: `covid_reporting` on the live `doctor` role (a product
  // decision, not a test-fixture one), plus the one ci_clinic_doc row wave 0.2
  // did not write: doc_ID=2 -> cli_ID=2, in env/seed.php. The provider-scoping half of the
  // scenario is covered meanwhile by the two tests above, which exercise the
  // same `_doc_id` axis on the two screens this persona can actually reach.
  async () => {},
);

// ---------------------------------------------------------------- restore

/**
 * Put the persona back exactly as env/seed.php leaves it: bound to the seed's
 * own E2E lab, with no `_doc_id`. The teardown project's reset.sh would do this
 * too, but `--no-deps` runs skip it and five other specs share this stack.
 */
e2e.afterAll(async ({ db }) => {
  const f = docInfra(db);
  armProvider(f.uid, f.seedLabId, null);
});
