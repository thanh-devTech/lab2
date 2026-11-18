import { request as playwrightRequest, type APIRequestContext } from '@playwright/test';
import * as path from 'node:path';
import { test as sharedTest, expect, BEFORE_BOUNCE_MS } from '../fixtures/test';
import { seedBloodRequisition, seedPcrRequisition } from '../fixtures/seed-domain';
import { batchIds } from '../fixtures/batch-id';
import { Db } from '../fixtures/db';
import { STATE_DIR } from '../playwright.config';

/**
 * TST -- laboratory analyst / technologist (personas.md 3.2, role slugs
 * `lab_tech` / `ref_lab_tech`), plus the two rack/plate ACC scenarios that
 * belong to the same bench role.
 *
 * PERSONA, read from the RUNNING instance (D178), not install_roles.php:
 *
 *   lab_tech       20 caps: read, level_1, level_0, ci_receiving,
 *                  ci_prep_station, form_custom_profiles, ci_batches_view,
 *                  ci_tecan_processing, ci_results, form_requisition,
 *                  form_blood, ci_results_blood, form_lab_test_req,
 *                  ci_consumable_inv, ci_phlebotomist, form_blood_edit,
 *                  ci_standing_orders, form_pcr, ci_resutls_pcr,
 *                  ci_standing_orders_pcr.
 *                  Note ci_resutls_pcr -- the MISSPELLED capability. lab_tech
 *                  does NOT hold the correctly-spelled ci_results_pcr.
 *   ref_lab_tech   13 caps, including ci_results_pcr (correct spelling) but
 *                  NOT form_gene / ci_results_gene, which 10-scenarios.md
 *                  attributes to it for S-TST-06.
 *   leadlabsecman  15 caps, including form_blood, ci_results_blood, ci_results
 *                  and form_requisition -- every capability the blood and tox
 *                  scenarios below exercise, but NOT form_pcr.
 *
 * Read with `get_role($slug)->capabilities` inside the wordpress container. The
 * `page-layer capability gate` describe at the bottom is the standing
 * regression guard on that reading.
 *
 * WHICH PERSONA WRITES WHAT
 *   blood + tox  -> the tenant-pool persona (leadlabsecman in lab LAB). It
 *                   holds form_blood / form_requisition / ci_results.
 *   pcr          -> `e2e_tech`, the baseline's actual `lab_tech` user, because
 *                   it is the only minted persona holding form_pcr. It is bound
 *                   to lab 19, outside the e2e_lead* pool, so its tenancy
 *                   assertions are against lab 19 -- read live from its own
 *                   `_lab_id`, never hardcoded. See the fixture gap noted in
 *                   the return: the pool has no lab_tech-in-LAB persona.
 *
 * TENANCY. No test here creates a lab (D161). Pool labs come from the
 * `tenant` fixture; lab 19 is read out of `wp_usermeta._lab_id`.
 */
// D241: the lab is resolved from the SPEC FILENAME by fixtures/test.ts (SPEC_LAB),
// so there is nothing to pin here. The old override re-registered a worker-scoped
// `tenant` over the test-scoped one, which Playwright rejects.
const e2e = sharedTest;

const statePath = (login: string) => path.join(STATE_DIR, `${login}.json`);

/** ci_test_groups this file depends on, resolved live so a spec cannot go stale. */
function bloodGroups(db: Db) {
  const [manual] = db.query(
    `SELECT tg_ID FROM ci_test_groups WHERE tg_type='blood' AND tg_manual_edit=1 ORDER BY tg_ID LIMIT 1`,
  );
  const [plain] = db.query(
    `SELECT tg_ID FROM ci_test_groups WHERE tg_type='blood' AND tg_manual_edit=0 ORDER BY tg_ID LIMIT 1`,
  );
  if (!manual || !plain) throw new Error('blood test groups missing from ci_test_groups');
  return { manualGroupId: manual[0], plainGroupId: plain[0] };
}

/** The lab `e2e_tech` (role lab_tech) is bound to, read live. */
function techLab(db: Db) {
  const [row] = db.query(
    `SELECT m.meta_value, l.lab_dba_init
       FROM wp_usermeta m
       JOIN wp_users u ON u.ID = m.user_id AND u.user_login='e2e_tech'
       JOIN ci_labs l ON l.lab_ID = m.meta_value
      WHERE m.meta_key='_lab_id'`,
  );
  if (!row) throw new Error('e2e_tech has no _lab_id -- run env/seed.sh');
  return { labId: row[0], prefix: row[1] };
}

/**
 * A batch_id nobody else in the suite will use, so a listing render touches only our rows.
 *
 * D396 / D243 -- THE WORKER SLOT IS LOAD-BEARING. `Date.now()` alone hands two
 * tests in this file the SAME id whenever they start in the same millisecond,
 * and `fullyParallel: true` puts them on different workers that start together.
 * A shared batch_id means one test's listing render releases the other's report
 * on GET (D034) before that test has read its own "no report row yet"
 * precondition -- exactly the class of flake D243 root-caused in rev.spec.ts.
 * The shared allocator adds the term the slot does not cover -- a per-SPEC
 * offset, because rev/tst/dst all sat at offset 0 -- and keeps the id inside
 * ci_batches.batch_id's signed int(10). See fixtures/batch-id.ts.
 */
const nextBatchId = batchIds('tst');
const privateBatchId = () => String(nextBatchId());

/** The one ci_batches* row a test seeded, matched on the exact per-test key -- never LIKE. */
function oneBatch(db: Db, table: string, patientName: string, columns: string) {
  const rows = db.query(
    `SELECT ${columns} FROM ${table} WHERE patient_name = ${Db.q(patientName)}`,
  );
  expect(rows.length, `expected exactly one ${table} row for ${patientName}`).toBe(1);
  return rows[0];
}

// ===========================================================================
// S-TST-01 -- manual blood analyte entry
// ===========================================================================

e2e.describe('S-TST-01 @smoke @tst', () => {
  e2e('manual blood analyte entry stores one ci_results_blood row per analyte', async ({
    tenantRequest, db, tenant, runKey,
  }, testInfo) => {
    const { manualGroupId } = bloodGroups(db);
    const patientLast = `Tstsyn${runKey}a`;
    const patientName = `${patientLast}, Synthetic`;

    // The analyte is read live: ci_load_results_blood.php matches
    // ci_results_blood.drug against ci_test_panels.te_name, so the drug string
    // is not ours to invent. A name containing '_' would break the saver's
    // explode('_', $key) at ci_save_blood.php:1010, so exclude those.
    const [analyte] = db.query(
      `SELECT te_ID, te_name FROM ci_test_panels
        WHERE te_type='blood' AND te_group_id=${Number(manualGroupId)} AND te_name NOT LIKE '%\\_%'
        ORDER BY te_ID LIMIT 1`,
    );
    expect(analyte, `no underscore-free analyte in blood group ${manualGroupId}`).toBeTruthy();
    const [teId, teName] = analyte;
    const rsKey = `${teId}_${teName.toUpperCase()}_${manualGroupId}_`;

    const seeded = await seedBloodRequisition(tenantRequest, db, tenant.labId, patientLast, {
      // The saver expects an ARRAY here and replaces a scalar with array()
      // (ci_save_blood.php:439-442) -- a string silently stores ''.
      'bat[panel_tests_requested][]': [`GROUP_${manualGroupId}`],
      // results[<te_ID>_<DRUG>_<tg_ID>_<existing rs id>] -- the key shape the
      // app itself emits (parts/blood/short_form.php:551).
      [`results[${rsKey}][conc]`]: '95',
      [`results[${rsKey}][pos-neg]`]: 'NEG',
      [`results[${rsKey}][comments]`]: '',
    });
    // Not toBeLessThan(400): a login redirect is 302 and would pass that.
    expect(seeded.status, 'ci_save_blood answers 200').toBe(200);
    // D221: row-exact on this test's key, not a racy global table count.
    expect(
      seeded.keyRowsAfter - seeded.keyRowsBefore,
      'ci_batches_blood gained no row for this patient key (D142/D100)',
    ).toBeGreaterThan(0);

    const [mid, assen, batchStatus, lab, labId, panels] = oneBatch(
      db, 'ci_batches_blood', patientName,
      'mid, assen, batch_status, lab, lab_id, panel_tests_requested',
    );
    expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing (D160)').not.toBe('');
    expect(batchStatus, 'batch_status of a fresh blood requisition').toBe('');
    expect(panels.toUpperCase(), 'panel_tests_requested').toContain(`GROUP_${manualGroupId}`);
    // TENANCY (D172), on every row this test created.
    expect(labId, 'ci_batches_blood.lab_id').toBe(tenant.labId);
    expect(lab, 'ci_batches_blood.lab').toBe(tenant.prefix);
    expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(tenant.prefix);

    // The report is created LAZILY by the saver because the ordered group
    // carries tg_manual_edit=1 (ci_save_blood.php:972-985).
    const reports = db.query(
      `SELECT id FROM ci_reports_blood WHERE patient_id = ${Db.q(assen)}`,
    );
    expect(reports.length, `exactly one ci_reports_blood row for assen ${assen}`).toBe(1);
    const reportId = reports[0][0];

    const results = db.query(
      `SELECT drug, \`pos-neg\`, conc, group_id, has_order
         FROM ci_results_blood WHERE report_id = ${Number(reportId)}`,
    );
    expect(results.length, `exactly one ci_results_blood row for report ${reportId}`).toBe(1);
    const [drug, posNeg, conc, groupId, hasOrder] = results[0];
    expect(drug.toUpperCase(), 'ci_results_blood.drug').toBe(teName.toUpperCase());
    expect(['POS', 'NEG', 'INV'], 'ci_results_blood.pos-neg domain').toContain(posNeg);
    expect(posNeg, 'ci_results_blood.pos-neg as posted').toBe('NEG');
    expect(conc, 'ci_results_blood.conc as posted').toBe('95');
    expect(groupId, 'ci_results_blood.group_id').toBe(String(manualGroupId));
    expect(hasOrder, 'ci_results_blood.has_order').toBe('1');

    console.log(
      `S-TST-01 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen} report=${reportId} drug=${drug}`,
    );
  });
});

// ===========================================================================
// S-TST-04 -- tox screening results are stored FLAT, not as analytes
// ===========================================================================

e2e.describe('S-TST-04 @tst @mutates', () => {
  e2e('screening specimen values land in ci_result_test_speciments and NOT in ci_results', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }, testInfo) => {
    const patientLast = `Tstsyn${runKey}d`;
    const patientName = `${patientLast}, Synthetic`;
    const batchId = privateBatchId();

    // A screening test from the ci_urine_validity DICTIONARY -- this is the
    // table 10-scenarios.md names as the key space for `testid`.
    const [validity] = db.query(
      `SELECT u_ID, u_name FROM ci_urine_validity WHERE u_status=1 ORDER BY u_ID LIMIT 1`,
    );
    expect(validity, 'ci_urine_validity has no active row').toBeTruthy();
    const [uId, uName] = validity;

    // `only_screening=1` is what puts a report-less tox batch into the queue at
    // all: ci_load_results.php:91 admits `re.id is null` rows only when the
    // batch is rejected, `resulted`, or screening-only. It is also exactly what
    // this scenario is about, so it is the precondition, not a workaround.
    // Both keys are real ci_batches columns -- an unknown bat[<key>] would make
    // the saver ALTER the table (10-scenarios.md Gap 10).
    const seeded = await seedRequisition(patientLast, {
      'bat[batch_id]': batchId,
      'bat[only_screening]': '1',
    });
    expect(seeded.status, 'the tox saver answers 200').toBe(200);
    expect(
      seeded.keyRowsAfter - seeded.keyRowsBefore,
      'ci_batches gained no row for this patient key (D221)',
    ).toBeGreaterThan(0);

    const [mid, assen, seededBatchId, labId, onlyScreening] = oneBatch(
      db, 'ci_batches', patientName, 'mid, assen, batch_id, lab_id, only_screening',
    );
    expect(assen, 'accession is empty (D160)').not.toBe('');
    expect(seededBatchId, 'ci_batches.batch_id').toBe(batchId);
    expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
    expect(onlyScreening, 'ci_batches.only_screening').toBe('1');

    // The tox report row does not exist until the queue is rendered: the tox
    // listing inserts it on GET (ci_load_results.php:415-431). That is the same
    // D034 mutation-on-GET this file asserts head-on in S-TST-05; here it is
    // just how the precondition comes into being.
    const render = await tenantRequest.get(
      `/wp-admin/admin.php?page=tox_results&tr_action=ci_load_results&batch_id=${batchId}`,
    );
    expect(render.status(), 'the tox listing fragment answers 200').toBe(200);
    expect(await render.text(), 'the rendered fragment carries our accession').toContain(assen);

    const reports = db.query(`SELECT id, estatus FROM ci_reports WHERE patient_id = ${Db.q(assen)}`);
    expect(reports.length, `exactly one ci_reports row for assen ${assen}`).toBe(1);
    const [reportId, estatus] = reports[0];
    expect(estatus, 'a non-rejected tox batch is left unapproved by the render').toBe('');

    // THE SAVE. data_specimen[<u_ID>][...] -- the screening channel
    // (ci_save_result_edit.php:285-317). `info[...]` must be present: the saver
    // dereferences $_POST['info'] unconditionally at :25 and feeds it straight
    // to $wpdb->update() at :489.
    const save = await tenantRequest.post(
      `/wp-admin/admin.php?page=tox_results&act=edit&id=${reportId}`,
      {
        form: {
          tr_action: 'ci_save_result_edit',
          btype: 'tox',
          id: reportId,
          'info[instrument]': '',
          [`data_specimen[${uId}][conc]`]: '1.23',
          [`data_specimen[${uId}][outcome]`]: 'POS',
          [`data_specimen[${uId}][comments]`]: '',
        },
      },
    );
    expect(save.status(), 'ci_save_result_edit answers 200').toBe(200);

    // The catalog outcome, positive half: keyed (mid, testid) where testid is a
    // ci_urine_validity.u_ID.
    const spec = db.query(
      `SELECT testid, conc, outcome FROM ci_result_test_speciments
        WHERE mid = ${Number(mid)}`,
    );
    expect(spec.length, `exactly one ci_result_test_speciments row for mid ${mid}`).toBe(1);
    expect(spec[0][0], 'ci_result_test_speciments.testid is the ci_urine_validity u_ID').toBe(String(uId));
    expect(spec[0][1], 'ci_result_test_speciments.conc').toBe('1.23');
    expect(spec[0][2], 'ci_result_test_speciments.outcome').toBe('POS');

    // The catalog outcome, negative half: the screening value is NOT mistaken
    // for a confirmatory analyte. ci_results is written only from
    // $_POST['data_results'] (ci_save_result_edit.php:172-268), which this POST
    // does not carry.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_results WHERE report_id = ${Number(reportId)}`),
      'a screening save must create ZERO ci_results analyte rows',
    ).toBe('0');

    console.log(
      `S-TST-04 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen} report=${reportId} testid=${uId}(${uName})`,
    );
  });

  /**
   * S-TST-04u -- S-TST-04 AS THE BENCH SEES IT (wave 4).
   *
   * ADDED beside S-TST-04, never in place of it (D382). The two are not
   * redundant: S-TST-04 posts ONE `data_specimen[<u_ID>]` key and can therefore
   * assert `exactly one ci_result_test_speciments row for mid`. The real screen
   * submits EVERY rendered validity row at once, so the UI path writes one row
   * per dictionary entry -- a different write set through the same saver, and
   * the reason this test keys on `(mid, testid)` instead of counting.
   *
   * THE PANEL IS ORDERED BY ANALYTE NAME, NOT `group_<id>`. Measured on this
   * stack, 2026-08-25: `tr_pdd_parse_panel_tests_requested('group_81')` returns
   * `['GROUP_81']` verbatim, and `tr_pdd_get_drug_class_comments`
   * (inc/functions.php:700-711) then marks every real analyte `exclude` because
   * the analyte name is not in that list -- so the confirmatory grid is empty
   * for a reason that has NOTHING to do with results existing. Ordering the
   * analyte by name makes the grid ELIGIBLE, which is what turns "no
   * `tr.result_row` on screen" into a statement about missing results.
   *
   * D397 -- the closing positive control is not decoration. `toHaveCount(0)`
   * on a class that never renders passes forever; the last step inserts one
   * confirmatory analyte through the app's own saver and proves the SAME
   * locator matches. Both directions are exercised inside one test.
   */
  e2e('S-TST-04u the bench sees the validity grid flag an abnormal value, and no confirmatory grid', async ({
    seedRequisition, tenantRequest, authedPage, db, tenant, runKey,
  }) => {
    const patientLast = `Tstsyn${runKey}du`;
    const patientName = `${patientLast}, Synthetic`;
    const batchId = privateBatchId();

    // The SPECIMEN VALIDITY TEST grid is `tr_pdd_get_result_validity(0, ...)`:
    // the ci_urine_validity rows with u_showinreq=0, in the screen's own order.
    // Read live so a dictionary change cannot leave this test asserting a row
    // the app no longer renders.
    const validity = db.query(
      `SELECT u_ID, u_name FROM ci_urine_validity
        WHERE u_status=1 AND u_showinreq=0 ORDER BY u_position ASC, u_ID ASC`,
    );
    expect(validity.length, 'ci_urine_validity has no active u_showinreq=0 row').toBeGreaterThan(0);
    const [uId, uName] = validity[0];

    // The confirmatory analyte the panel orders BY NAME -- see the header note.
    const [analyte] = db.query(
      `SELECT te_ID, te_name FROM ci_test_panels
        WHERE te_type='tox' AND te_in_req=1 ORDER BY te_ID LIMIT 1`,
    );
    expect(analyte, 'no orderable tox analyte in ci_test_panels').toBeTruthy();
    const [, teName] = analyte;

    const seeded = await seedRequisition(patientLast, {
      'bat[batch_id]': batchId,
      'bat[only_screening]': '1',
      'bat[panel_tests_requested]': teName,
    });
    expect(seeded.status, 'the tox saver answers 200').toBe(200);
    expect(
      seeded.keyRowsAfter - seeded.keyRowsBefore,
      'ci_batches gained no row for this patient key (D221)',
    ).toBeGreaterThan(0);

    const [mid, assen, seededBatchId, labId, onlyScreening] = oneBatch(
      db, 'ci_batches', patientName, 'mid, assen, batch_id, lab_id, only_screening',
    );
    expect(assen, 'accession is empty (D160)').not.toBe('');
    expect(seededBatchId, 'ci_batches.batch_id').toBe(batchId);
    expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
    expect(onlyScreening, 'ci_batches.only_screening').toBe('1');

    // Same precondition as S-TST-04: the tox report row is created by RENDERING
    // the queue (ci_load_results.php:415-431), not by the requisition saver.
    const render = await tenantRequest.get(
      `/wp-admin/admin.php?page=tox_results&tr_action=ci_load_results&batch_id=${batchId}`,
    );
    expect(render.status(), 'the tox listing fragment answers 200').toBe(200);
    expect(await render.text(), 'the rendered fragment carries our accession').toContain(assen);

    const reports = db.query(`SELECT id, estatus FROM ci_reports WHERE patient_id = ${Db.q(assen)}`);
    expect(reports.length, `exactly one ci_reports row for assen ${assen}`).toBe(1);
    const [reportId, estatus] = reports[0];
    expect(estatus, 'a non-rejected tox batch is left unapproved by the render').toBe('');

    const editUrl = `/wp-admin/admin.php?page=tox_results&act=edit&id=${reportId}`;
    const page = authedPage;

    // PAGE LOAD 1 of 3 -- the results-edit screen the technologist opens.
    await page.goto(editUrl);
    await expect(page.getByRole('heading', { name: 'SPECIMEN VALIDITY TEST' }),
      'the bench is offered the specimen-validity grid').toBeVisible();

    // No label[for] anywhere on this form and `div.specimen_row` carries no
    // role, so the row is reached by its own test name and the fields by the
    // name= attribute the browser submits (D383 fallbacks 3/4).
    const grid = page.locator('div.specimen_row');
    await expect(grid, 'the grid renders one row per active validity test')
      .toHaveCount(validity.length);
    const row = grid.filter({ hasText: String(uName) });
    await expect(row, `exactly one grid row for ${uName}`).toHaveCount(1);
    const value = row.locator(`input[name="data_specimen[${Number(uId)}][conc]"]`);
    const outcome = row.getByRole('combobox');

    // THE ABSENCE, on screen and in the database, BEFORE anything is saved.
    // `tr.result_row` (parts/tox/results_edit.php:649) is the confirmatory
    // analyte grid; the closing positive control proves this locator can match.
    await expect(page.locator('tr.result_row'),
      'a report with no analyte results offers the bench no confirmatory grid')
      .toHaveCount(0);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_results WHERE report_id = ${Number(reportId)}`),
      'no ci_results analyte row exists yet',
    ).toBe('0');

    // The abnormal flag is `.specimen_row.isred { color: red }`
    // (results_edit.php:244-246). Asserting the COMPUTED COLOUR rather than the
    // class name is what makes this a fact about what the bench sees.
    await expect(value, 'an unresulted validity value is not flagged abnormal')
      .not.toHaveCSS('color', 'rgb(255, 0, 0)');

    // THE SAVE, through the form's own Submit button.
    await value.fill('1.23');
    // The screen's own wording: option value POS is labelled ABNORMAL.
    await outcome.selectOption({ label: 'ABNORMAL' });
    await page.getByRole('button', { name: 'Submit', exact: true }).click();

    // THE DATABASE, keyed on (mid, testid) -- the UI submits every rendered row,
    // so this asserts OUR row, not a table count.
    const spec = db.query(
      `SELECT conc, outcome FROM ci_result_test_speciments
        WHERE mid = ${Number(mid)} AND testid = ${Number(uId)}`,
    );
    expect(spec.length, `exactly one ci_result_test_speciments row for (mid ${mid}, testid ${uId})`).toBe(1);
    expect(spec[0][0], 'ci_result_test_speciments.conc as typed').toBe('1.23');
    expect(spec[0][1], 'ci_result_test_speciments.outcome -- ABNORMAL stores POS').toBe('POS');
    // S-TST-04's negative half, kept verbatim: the screening channel
    // (ci_save_result_edit.php:285-317 at the pin) writes ci_result_test_speciments,
    // ci_results is written ONLY from $_POST['data_results'], which the
    // validity grid does not carry.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_results WHERE report_id = ${Number(reportId)}`),
      'a screening save must create ZERO ci_results analyte rows',
    ).toBe('0');

    // THE POSITIVE CONTROL for the absence above (D397). No screen on this
    // build can add a confirmatory analyte: the grid renders `new<N>` inputs
    // only for a tg_manual_edit group and this database holds none, so the row
    // is inserted through the saver's own `new` branch
    // (ci_save_result_edit.php:228-237) -- the same code path the instrument
    // import lands on.
    const confirm = await tenantRequest.post(editUrl, {
      form: {
        tr_action: 'ci_save_result_edit',
        btype: 'tox',
        id: reportId,
        'info[instrument]': '',
        'data_results[new1][drug]': String(teName).toUpperCase(),
        'data_results[new1][conc]': '42',
        'data_results[new1][pos-neg]': 'POS',
      },
    });
    expect(confirm.status(), 'the confirmatory save answers 200').toBe(200);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_results WHERE report_id = ${Number(reportId)}`),
      'the confirmatory save created exactly one ci_results analyte row',
    ).toBe('1');

    // PAGE LOAD 3 of 3 -- D386. (Load 2 was the form's own POST response, which
    // this test deliberately asserts nothing on: `<form method="post">` posts
    // back to the same URL, so page.reload() would re-submit.) A fresh GET is
    // markup results_edit.php produced from the STORED rows, so it cannot
    // survive a save that silently wrote nothing.
    await page.goto(editUrl);
    const savedRow = page.locator('div.specimen_row').filter({ hasText: String(uName) });
    await expect(savedRow.locator(`input[name="data_specimen[${Number(uId)}][conc]"]`),
      'the bench reads back the value it entered').toHaveValue('1.23');
    await expect(savedRow.getByRole('combobox'),
      'the outcome reads back as the abnormal option').toHaveValue('POS');
    await expect(savedRow.locator(`input[name="data_specimen[${Number(uId)}][conc]"]`),
      'an abnormal validity result is shown to the bench in red').toHaveCSS('color', 'rgb(255, 0, 0)');

    // The other half of the control: with one ci_results row the SAME locator
    // that read 0 above now matches, so the absence assertion was real.
    const confirmRow = page.locator('tr.result_row');
    await expect(confirmRow, 'one ci_results row renders one confirmatory grid row')
      .toHaveCount(1);
    await expect(confirmRow.locator('input[name$="[drug]"]'),
      'the confirmatory row names the analyte that was resulted')
      .toHaveValue(String(teName).toUpperCase());
  });
});

// ===========================================================================
// S-TST-05 -- the auto-approve-on-GET hazard (D034/D108)
// ===========================================================================

e2e.describe('S-TST-05 @tst @mutates', () => {
  e2e('rendering the blood listing creates AND releases a report', async ({
    tenantRequest, db, tenant, runKey,
  }, testInfo) => {
    const { plainGroupId } = bloodGroups(db);
    const patientLast = `Tstsyn${runKey}e`;
    const patientName = `${patientLast}, Synthetic`;
    // ci_load_results_blood.php:83 narrows the whole listing to
    // `ba.batch_id = '<batch_id>'`, so a private batch_id keeps this test's
    // render on this test's row and off every other agent's.
    const batchId = privateBatchId();

    // Precondition: a blood batch in a REJECTED status ordering a group that
    // does NOT carry tg_manual_edit, so the saver leaves no report behind.
    const seeded = await seedBloodRequisition(tenantRequest, db, tenant.labId, patientLast, {
      'bat[batch_id]': batchId,
      'bat[batch_status]': 'rejected',
      'bat[panel_tests_requested][]': [`GROUP_${plainGroupId}`],
    });
    expect(seeded.status, 'ci_save_blood answers 200').toBe(200);
    expect(
      seeded.keyRowsAfter - seeded.keyRowsBefore,
      'ci_batches_blood gained no row for this patient key (D221)',
    ).toBeGreaterThan(0);

    const [mid, assen, seededBatchId, seededStatus, seededLabId] = oneBatch(
      db, 'ci_batches_blood', patientName, 'mid, assen, batch_id, batch_status, lab_id',
    );
    expect(assen, 'accession is empty (D160)').not.toBe('');
    expect(seededBatchId, 'ci_batches_blood.batch_id').toBe(batchId);
    expect(seededStatus, 'precondition: batch is in a rejected status').toBe('rejected');
    expect(seededLabId, 'ci_batches_blood.lab_id').toBe(tenant.labId);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_reports_blood WHERE patient_id = ${Db.q(assen)}`),
      'precondition: NO ci_reports_blood row exists yet',
    ).toBe('0');

    // THE GET. No approve click and no POST body -- this is the listing
    // fragment the Blood Results screen loads for one batch
    // (parts/blood/results_list.php:330-334). admin/init.php:44 reads tr_action
    // from $_REQUEST, so the fragment dispatches on GET too.
    const render = await tenantRequest.get(
      `/wp-admin/admin.php?page=blood_results&tr_action=ci_load_results_blood&batch_id=${batchId}`,
    );
    expect(render.status(), 'the listing fragment answers 200').toBe(200);
    // Prove the row we seeded is the row that rendered, so a denial page or an
    // empty listing cannot be mistaken for a pass.
    expect(await render.text(), 'the rendered fragment carries our accession').toContain(assen);

    const after = db.query(
      `SELECT id, estatus, final_results, approve_date FROM ci_reports_blood
        WHERE patient_id = ${Db.q(assen)}`,
    );
    // Mutation 1: the GET CREATED the report row (ci_load_results_blood.php:482-494).
    expect(after.length, 'the GET created exactly one ci_reports_blood row').toBe(1);
    const [reportId, estatus, finalResults, approveDate] = after[0];
    // Mutation 2: the same GET RELEASED it (ci_load_results_blood.php:496-506).
    expect(estatus, 'ci_reports_blood.estatus released by the GET alone').toBe('C');
    expect(finalResults, 'ci_reports_blood.final_results').toBe('1');
    expect(approveDate, 'ci_reports_blood.approve_date').not.toBe('');

    // 10-scenarios.md also predicts ci_batches_blood.batch_status='completed'.
    // On this build that write is nested inside `if ($auto_fax)`
    // (ci_load_results_blood.php:588-596) and ci_pdf_data.auto_fax_approve_blood
    // is b:0, so the batch does NOT advance. Both halves are asserted, so
    // turning the option on turns this red instead of leaving a silent gap.
    expect(
      db.scalar(
        `SELECT option_value LIKE '%"auto_fax_approve_blood";b:0;%'
           FROM wp_options WHERE option_name='ci_pdf_data'`,
      ),
      'precondition: ci_pdf_data.auto_fax_approve_blood is off',
    ).toBe('1');
    expect(
      db.scalar(`SELECT batch_status FROM ci_batches_blood WHERE mid = ${Number(mid)}`),
      'batch_status is NOT advanced to completed while auto_fax_approve_blood is off',
    ).toBe('rejected');

    console.log(
      `S-TST-05 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen} report=${reportId} estatus=${estatus}`,
    );
  });

  /**
   * S-TST-05u -- THE RELEASE-ON-RENDER HAZARD AS AN ON-SCREEN FACT (wave 4).
   *
   * ADDED beside S-TST-05, never in place of it (D382). S-TST-05 proves the
   * columns move; this proves the reviewer is never offered the decision. The
   * row already reads C, the only control is Reverse, and Approve is never
   * rendered -- so a released report is indistinguishable, on screen, from one
   * a human signed off.
   *
   * THE REJECTED STATUS IS SEEDED OVER HTTP AND THAT IS NOT A SHORTCUT. No
   * screen on this build can set it: `ci_batches_status` holds 11 active rows
   * and ZERO rejected ones, so every status control in the app offers only
   * {None, Plated, Assigned, Collected, Not Collected, Refused, Stopped}. The
   * guards still recognise `rejected` because tr_pdd_get_batch_statuses()
   * falls back to a hardcoded list when its query comes back empty
   * (inc/functions.php:2789-2811). Adding the rows would change global app
   * config every worker shares, which by D150/D170 belongs in serial.spec.ts.
   *
   * D396 -- this test and S-TST-05 write byte-similar `approve_auto_rs` audit
   * rows and identically-shaped ci_reports_blood rows. Every assertion below is
   * keyed on THIS test's own accession or mid, and privateBatchId() now carries
   * the worker slot (see its comment), so neither test's listing render can
   * reach the other's batch.
   */
  e2e('S-TST-05u the reviewer is never offered Approve -- the row already reads C', async ({
    tenantRequest, authedPage, db, tenant, runKey,
  }) => {
    const { plainGroupId } = bloodGroups(db);
    const patientLast = `Tstsyn${runKey}eu`;
    const patientName = `${patientLast}, Synthetic`;
    const batchId = privateBatchId();

    const seeded = await seedBloodRequisition(tenantRequest, db, tenant.labId, patientLast, {
      'bat[batch_id]': batchId,
      'bat[batch_status]': 'rejected',
      'bat[panel_tests_requested][]': [`GROUP_${plainGroupId}`],
    });
    expect(seeded.status, 'ci_save_blood answers 200').toBe(200);
    expect(
      seeded.keyRowsAfter - seeded.keyRowsBefore,
      'ci_batches_blood gained no row for this patient key (D221)',
    ).toBeGreaterThan(0);

    const [mid, assen, seededBatchId, seededStatus, seededLabId] = oneBatch(
      db, 'ci_batches_blood', patientName, 'mid, assen, batch_id, batch_status, lab_id',
    );
    expect(assen, 'accession is empty (D160)').not.toBe('');
    expect(seededBatchId, 'ci_batches_blood.batch_id').toBe(batchId);
    expect(seededStatus, 'precondition: batch is in a rejected status').toBe('rejected');
    expect(seededLabId, 'ci_batches_blood.lab_id').toBe(tenant.labId);

    // PRECONDITION BEFORE ANY NAVIGATION (D034). parts/blood/results_list.php:487
    // auto-clicks the batch accordion whenever the listing renders exactly one
    // `tr.parent`, which fires the ci_load_results_blood fragment with no user
    // gesture at all -- so simply opening the Blood Results screen is already a
    // write. Nothing after the first goto() can be read as a precondition.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_reports_blood WHERE patient_id = ${Db.q(assen)}`),
      'precondition: NO ci_reports_blood row exists yet',
    ).toBe('0');

    // PAGE LOAD 1 of 3, straight to the accession-filtered listing. The search
    // control is `<form method="get">` carrying page/tab/s
    // (parts/blood/results_list.php:181-188), so this URL is exactly what
    // typing the accession and pressing Search produces -- and stopping on the
    // UNFILTERED listing first would be a hazard, not coverage: if a sibling
    // test's batch happens to be the only `tr.parent` at that instant, the
    // accordion auto-click renders and RELEASES that test's report (D034).
    // Loads 2 and 3 are the two D386 read-backs below.
    const page = authedPage;
    const listing = `/wp-admin/admin.php?page=blood_results&tab=&s=${assen}`;
    await page.goto(listing);
    await expect(page.getByRole('heading', { name: 'Blood Results', exact: true }),
      'the reviewer is on the Blood Results screen').toBeVisible();
    // The filter actually filtered: one batch accordion, and it is ours.
    // Without this the row assertion below would still pass on an unfiltered
    // listing that happened to contain our specimen among many.
    //
    // CAVEAT, and it is a real one: `s` is matched with LIKE, not `=`
    // (parts/blood/results_list.php:110-118 searches `ba.assen like '%<s>%'`).
    // A second blood specimen in THIS tenant lab whose accession CONTAINS this
    // one -- an S-ORD-02-style `<assen>-01` sibling -- would make this two, and
    // would also kill the D034 auto-click at results_list.php:487, which only
    // fires when there is exactly one `tr.parent`. No test in this file mints a
    // suffixed blood accession today; one that does must give this test a
    // narrower filter rather than loosening the count.
    await expect(page.locator(`tr.parent[data-p="batch_${batchId}"]`),
      "the listing is narrowed to this test's own batch").toHaveCount(1);
    await expect(page.locator('tr.parent'),
      'and to nothing else').toHaveCount(1);

    const row = page.locator('tr.childp').filter({ hasText: assen });
    await expect(row, "the reviewer's own listing shows the specimen").toHaveCount(1);

    // THE HAZARD, AT THE MOMENT IT HAPPENS. The reviewer searched for the
    // specimen and did nothing else; the row is already released.
    await expect(row.getByRole('cell', { name: 'C', exact: true }),
      'the status column already reads the released code C').toBeVisible();
    await expect(row.getByText('Approve', { exact: true }),
      'the reviewer is never offered Approve').toHaveCount(0);

    // THE DATABASE, read BEFORE any reload so the mutation is attributable to
    // rendering the listing and to nothing else. Same two writes S-TST-05
    // asserts over HTTP (ci_load_results_blood.php:482-506).
    const after = db.query(
      `SELECT id, estatus, final_results, approve_date FROM ci_reports_blood
        WHERE patient_id = ${Db.q(assen)}`,
    );
    expect(after.length, 'rendering the listing created exactly one ci_reports_blood row').toBe(1);
    const [reportId, estatus, finalResults, approveDate] = after[0];
    expect(estatus, 'ci_reports_blood.estatus released by the render alone').toBe('C');
    expect(finalResults, 'ci_reports_blood.final_results').toBe('1');
    expect(approveDate, 'ci_reports_blood.approve_date').not.toBe('');
    // No ci_log_users approve_rs row can exist: nobody approved anything. The
    // release is logged as approve_auto_rs instead -- and, measured, under the
    // REVIEWER'S OWN LOGIN; see the trail enumeration at the end of this test
    // and DWA-E25. This count keys on a LITERAL, so on its own a rename would
    // make it pass forever; the enumeration is where that literal gets its
    // control.
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_log_users
          WHERE assen = ${Db.q(assen)} AND user_action = 'approve_rs'`,
      ),
      'no human sign-off was recorded, because no human signed off',
    ).toBe('0');

    // THE READ BACK, off a SERVER render (D386). The search is a GET, so a
    // reload replays the filter and the row is rebuilt by results_list.php.
    await page.reload();
    const reloaded = page.locator('tr.childp').filter({ hasText: assen });
    await expect(reloaded, 'the reloaded listing still shows the specimen').toHaveCount(1);

    await expect(reloaded.getByRole('cell', { name: 'C', exact: true }),
      "the reviewer's status column shows the released code C").toBeVisible();
    await expect(reloaded.getByText('Reverse', { exact: true }),
      'the only action offered is Reverse -- the release has already happened')
      .toBeVisible();
    await expect(reloaded.getByText('Approve', { exact: true }),
      'Approve is never shown, so the release is not a decision the reviewer can make')
      .toHaveCount(0);
    // ci_load_results_blood.php:803 hides the Edit link once estatus is C and
    // :798 reveals the PDF: the specimen is out the door.
    await expect(reloaded.locator('span.editlink'),
      'a released report can no longer be edited from the listing').toBeHidden();
    await expect(reloaded.getByRole('link', { name: 'PDF' }),
      'a released report can be handed out as a PDF').toBeVisible();
    // The one honest signal the reviewer gets: the rejected marker
    // (ci_load_results_blood.php:614) still sits in the mstatus column.
    await expect(reloaded.locator('td.mstatus span.rejected'),
      'the rejected marker is the only warning the reviewer is given')
      .toHaveText('X');

    // THE EXIT FROM THE HAZARD, AND THE PROOF THAT THE ABSENCE ABOVE IS REAL.
    // `Approve` is a negative assertion, so the same locator must be shown
    // capable of matching on THIS screen or it passes forever (D397). Reversing
    // is the one action that puts the decision back in front of the reviewer --
    // which is the hazard restated: releasing was never a decision, un-releasing
    // is. parts/blood/results.php:283-310, byte-identical at the pin.
    await reloaded.getByText('Reverse', { exact: true }).click();
    // Immediate feedback only -- admin.js:1417 writes this text client-side.
    // Used to await the XHR, never as the read-back (D386).
    await expect(reloaded.getByText('Approve', { exact: true })).toBeVisible();

    await page.reload();
    const reopened = page.locator('tr.childp').filter({ hasText: assen });
    await expect(reopened, 'the reopened listing still shows the specimen').toHaveCount(1);
    await expect(reopened.getByRole('cell', { name: 'M', exact: true }),
      'the status column reopens to M').toBeVisible();
    await expect(reopened.getByText('Approve', { exact: true }),
      'only after an explicit Reverse is the reviewer offered Approve at all')
      .toBeVisible();
    await expect(reopened.locator('span.editlink'),
      'a reopened report can be edited again').toBeVisible();
    expect(
      db.scalar(`SELECT estatus FROM ci_reports_blood WHERE id = ${Number(reportId)}`),
      'the reverse landed in the database, not only on the screen',
    ).toBe('M');

    // THE WHOLE AUDIT TRAIL FOR THIS SPECIMEN, ENUMERATED -- and that is the
    // point of reading it this way rather than counting a literal. Every
    // assertion above that names an action string ('approve_rs' absent) would
    // survive a rename of that string; this one cannot, because it states the
    // COMPLETE set of actions the app recorded and the exact order it recorded
    // them in.
    //
    // AND IT IS HOW THE THIRD FACE OF THIS HAZARD WAS FOUND -- DWA-E25. The
    // release call SAYS it is a system action: `ci_load_results_blood.php:587`
    // passes a literal `0` as the user id, where `parts/blood/results.php:305`
    // passes `get_current_user_id()` for the reverse. But
    // `inc/functions_user.php:450-452` rewrites a zero user id to
    // `get_current_user_id()`, so the automatic release is filed in the audit
    // trail UNDER THE LOGIN OF WHOEVER HAPPENED TO LOAD THE LISTING. That is
    // asserted here as the current product behaviour, deliberately: the
    // reviewer who merely searched for a specimen is on record as having
    // released it, and nothing distinguishes that row from the deliberate
    // `reverse_rs` two lines below it. When DWA-E25 is fixed this expectation
    // must be updated to `'#0'` -- the card says so. All line numbers read out
    // of the pinned blob.
    //
    // The save row's `_cd` suffix is data-dependent (`ci_save_blood.php:1080`
    // appends it when the saver recorded field changes), so it is matched
    // loosely; the two release-cycle actions are matched exactly.
    expect(
      db.query(
        `SELECT l.user_action, COALESCE(u.user_login, CONCAT('#', l.user_id))
           FROM ci_log_users l
           LEFT JOIN wp_users u ON u.ID = l.user_id
          WHERE l.assen = ${Db.q(assen)}
          ORDER BY l.id`,
      ),
      "the audit trail is exactly: the requisition save, an automatic release the reviewer "
        + 'never asked for but which is nonetheless recorded in their name (DWA-E25), and their '
        + 'own reverse. An explicit sign-off appears nowhere in it',
    ).toEqual([
      [expect.stringMatching(/^save_req(_cd)?$/), tenant.login],
      ['approve_auto_rs', tenant.login],
      ['reverse_rs', tenant.login],
    ]);

    console.log(
      `S-TST-05u lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen} report=${reportId}`,
    );
  });

  e2e('the all-negative auto-release branch has no data to fire on', async ({ db }) => {
    // ci_load_results_blood.php:508-560 releases a report when every ordered
    // test reads NEG and at least one of their groups carries
    // tg_auto_approve_neg. Zero blood groups carry that flag in this database,
    // so the branch is dead here and 10-scenarios.md's stated S-TST-05
    // precondition ("ci_test_groups row with the all-negative auto-approve
    // flag") cannot be met without a config change. This is the tripwire: set
    // the flag on any blood group and this test says the branch is now live.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_test_groups WHERE tg_type='blood' AND tg_auto_approve_neg=1`),
      'blood groups carrying tg_auto_approve_neg',
    ).toBe('0');
  });
});

// ===========================================================================
// S-ACC-03 / S-ACC-04 -- rack scan and rack->plate promotion (pcr)
// ===========================================================================

/**
 * Both rack actions are `trajax` POSTs to the Receiving page's Racks tab
 * (`pages/admin_ci_receiving.php:547-548` -> `parts/rack/main.php:229-232`).
 * Predicate note (D166/D050): these key on `trajax=1&act=<name>`; `tr_act=`
 * occurs zero times in the app.
 */
async function rackPost(request: APIRequestContext, form: Record<string, string>) {
  const res = await request.post('/wp-admin/admin.php?page=ci_receiving&tab=racks', {
    form: { trajax: '1', ...form },
  });
  expect(res.status(), `rack action ${form.act} answers 200`).toBe(200);
  const text = await res.text();
  let json: Record<string, unknown>;
  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(`rack action ${form.act} did not return JSON: ${text.slice(0, 400)}`);
  }
  return json;
}

async function scanIntoRack(
  request: APIRequestContext, assen: string, reId: string,
): Promise<Record<string, unknown>> {
  return rackPost(request, {
    act: 'ci_search_barcode_rack',
    code: assen,
    btype: 'pcr',
    duplicate: '0',
    new_well: '0',
    pnid: '0',
    mixedvp: '',
    'parentdata[re_ID]': reId,
    'parentdata[re_panel_id]': '0',
    'parentdata[re_subtitle]': '',
    'parentdata[re_priority]': 'low',
  });
}

e2e.describe('S-ACC-03 / S-ACC-04 @tst @acc @mutates', () => {
  /** One pcr requisition at a chosen status, saved by e2e_tech (lab_tech). */
  async function seedPcr(
    request: APIRequestContext, db: Db, patientLast: string, batchStatus: string,
  ) {
    const lab = techLab(db);
    const patientName = `${patientLast}, Synthetic`;
    const seeded = await seedPcrRequisition(request, db, lab.labId, patientLast, {
      'bat[batch_status]': batchStatus,
    });
    expect(seeded.status, 'ci_save_form_pcr answers 200').toBe(200);
    expect(
      seeded.keyRowsAfter - seeded.keyRowsBefore,
      'ci_batches_pcr gained no row for this patient key (D221/D226)',
    ).toBeGreaterThan(0);
    const [mid, assen, status, labId] = oneBatch(
      db, 'ci_batches_pcr', patientName, 'mid, assen, batch_status, lab_id',
    );
    expect(assen, 'accession is empty (D160)').not.toBe('');
    expect(status, `precondition: ci_batches_pcr.batch_status`).toBe(batchStatus);
    // TENANCY (D172): lab_tech's own lab, read live -- not a literal.
    expect(labId, 'ci_batches_pcr.lab_id is the lab_tech persona lab').toBe(lab.labId);
    expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(lab.prefix);
    return { mid, assen, lab };
  }

  e2e('S-ACC-03 racking advances a received specimen and never downgrades a plated one', async ({
    db, runKey, baseURL,
  }, testInfo) => {
    const ctx = await playwrightRequest.newContext({ baseURL, storageState: statePath('e2e_tech') });
    try {
      const received = await seedPcr(ctx, db, `Accsyn${runKey}r`, 'received');
      const plated = await seedPcr(ctx, db, `Accsyn${runKey}p`, 'plated');

      // Case A -- a 'received' specimen into a brand new rack (re_ID=0 makes
      // action_ci_search_barcode_rack.php:36-58 create the ci_racks row).
      const a = await scanIntoRack(ctx, received.assen, '0');
      expect(a.status, `scan A rejected: ${JSON.stringify(a.message ?? a)}`).toBe('ok');
      const reId = String(a.re_ID ?? '');
      expect(Number(reId), 'the scan created a ci_racks row').toBeGreaterThan(0);
      expect(Number(a.rbid ?? 0), 'the scan inserted a ci_rack_batches row').toBeGreaterThan(0);

      const rackRowA = db.query(
        `SELECT ID, btype, re_ID FROM ci_rack_batches
          WHERE re_ID = ${Number(reId)} AND mid = ${Number(received.mid)}`,
      );
      expect(rackRowA.length, `one ci_rack_batches row for mid ${received.mid}`).toBe(1);
      expect(rackRowA[0][0], 'the rbid the action returned is the row it wrote').toBe(String(a.rbid));
      expect(rackRowA[0][1], 'ci_rack_batches.btype').toBe('pcr');
      expect(
        db.scalar(`SELECT batch_status FROM ci_batches_pcr WHERE mid = ${Number(received.mid)}`),
        'a received specimen advances to racked (action_ci_search_barcode_rack.php:720-721)',
      ).toBe('racked');

      // Case B -- the SAME rack, a specimen already at 'plated'. The advance is
      // gated to received/empty, so this one must not move.
      const b = await scanIntoRack(ctx, plated.assen, reId);
      expect(b.status, `scan B rejected: ${JSON.stringify(b.message ?? b)}`).toBe('ok');
      expect(Number(b.rbid ?? 0), 'the plated specimen is still racked').toBeGreaterThan(0);
      expect(
        db.scalar(`SELECT COUNT(*) FROM ci_rack_batches WHERE re_ID = ${Number(reId)} AND mid = ${Number(plated.mid)}`),
        'ci_rack_batches row for the plated specimen',
      ).toBe('1');
      expect(
        db.scalar(`SELECT batch_status FROM ci_batches_pcr WHERE mid = ${Number(plated.mid)}`),
        'a plated specimen is NOT downgraded to racked',
      ).toBe('plated');

      console.log(
        `S-ACC-03 worker=${testInfo.workerIndex} lab=${received.lab.prefix}(${received.lab.labId}) rack=${reId} received_mid=${received.mid} plated_mid=${plated.mid}`,
      );
    } finally {
      await ctx.dispose();
    }
  });

  e2e('S-ACC-04 rack-to-plate promotion writes ci_plates, ci_plates_batches and plated', async ({
    db, runKey, baseURL,
  }, testInfo) => {
    const ctx = await playwrightRequest.newContext({ baseURL, storageState: statePath('e2e_tech') });
    try {
      // Self-contained: this test builds its own rack rather than depending on
      // S-ACC-03's, because the two run on different workers under the suite's
      // fullyParallel default.
      const b1 = await seedPcr(ctx, db, `Accsyn${runKey}q`, 'received');
      const b2 = await seedPcr(ctx, db, `Accsyn${runKey}s`, 'received');

      const first = await scanIntoRack(ctx, b1.assen, '0');
      expect(first.status, `scan rejected: ${JSON.stringify(first.message ?? first)}`).toBe('ok');
      const reId = String(first.re_ID ?? '');
      expect(Number(reId), 'rack created').toBeGreaterThan(0);
      const second = await scanIntoRack(ctx, b2.assen, reId);
      expect(second.status, `scan rejected: ${JSON.stringify(second.message ?? second)}`).toBe('ok');

      expect(
        db.scalar(`SELECT COUNT(*) FROM ci_plates WHERE pl_rack_ID = ${Number(reId)}`),
        'precondition: the rack has not been plated yet',
      ).toBe('0');

      const plate = await rackPost(ctx, { act: 'add_plate', re_ID: reId });
      expect(plate.status, `add_plate rejected: ${JSON.stringify(plate.message ?? plate)}`).toBe('ok');

      const plates = db.query(
        `SELECT pl_ID, pl_number, pl_today FROM ci_plates WHERE pl_rack_ID = ${Number(reId)}`,
      );
      expect(plates.length, `exactly one ci_plates row for rack ${reId}`).toBe(1);
      const [plId, plNumber] = plates[0];
      // action_add_plate.php:182 -- MAX(pl_number)+1 over pl_today, so it is
      // always a positive integer, never left blank.
      expect(Number(plNumber), 'ci_plates.pl_number is set').toBeGreaterThan(0);

      const mids = [Number(b1.mid), Number(b2.mid)];
      expect(
        db.scalar(
          `SELECT COUNT(*) FROM ci_plates_batches WHERE pl_ID = ${Number(plId)} AND mid IN (${mids.join(',')})`,
        ),
        'one ci_plates_batches row per specimen on the rack',
      ).toBe('2');
      expect(
        db.query(
          `SELECT DISTINCT batch_status FROM ci_batches_pcr WHERE mid IN (${mids.join(',')})`,
        ).map((r) => r[0]),
        'both specimens advance to plated (action_add_plate.php:453)',
      ).toEqual(['plated']);

      console.log(
        `S-ACC-04 worker=${testInfo.workerIndex} lab=${b1.lab.prefix}(${b1.lab.labId}) rack=${reId} plate=${plId} pl_number=${plNumber}`,
      );
    } finally {
      await ctx.dispose();
    }
  });
});

// ===========================================================================
// Capability gate -- the live capability reading, made executable
// ===========================================================================

/**
 * The screen each gated slug draws, measured on this stack 2026-08-25.
 *
 *   menu -- the #adminmenu link name whose href is page=<slug> (verified by
 *           reading the hrefs, not by guessing from the label).
 *   seen -- ONE thing inside #wpbody-content that only exists when the screen
 *           actually rendered. `ci_receiving` has no <h1>/<h2> whose accessible
 *           name is just "Receiving" (the nav tabs are inside the heading), so
 *           it is pinned on the Racks tab instead.
 */
const SCREENS = {
  blood_results: {
    menu: 'Blood Results',
    role: 'heading', name: 'Blood Results',
    seen: 'the Blood Results worklist heading',
  },
  pcr_results: {
    menu: 'PCR Results',
    role: 'heading', name: 'PCR Results',
    seen: 'the PCR Results worklist heading',
  },
  ci_receiving: {
    menu: 'Receiving',
    role: 'link', name: 'Racks',
    seen: "the receiving bench's Racks tab",
  },
} as const;

e2e.describe('page-layer capability gate @tst', () => {
  // D105/D185: page-layer authz IS enforced and it answers HTTP 200 with a
  // denial BODY, not 403 -- so the assertion is on the rendered outcome, never
  // on a status code.
  //
  // pcr_results is registered against the MISSPELLED capability
  // `ci_resutls_pcr` (inc/admin.php:643), which lab_tech holds and
  // leadlabsecman does not. The misspelling personas.md:79 documents for
  // pcr_verify governs this persona's PCR access too.
  //
  // WAVE 2 / D388 -- driven through a real browser. This SUPERSEDES the
  // request-context/regex twin rather than adding to it, and D382 does not
  // apply: both forms issue the SAME GET to the SAME handler (admin.php?page=
  // <slug>), so the conversion orphans no handler and every assertion below is
  // a strict superset of the single /Sorry, you are not allowed/ regex it
  // replaces. Navigation comes FIRST: pageAs() returns a page on about:blank,
  // where toHaveCount(0) holds for any product behaviour at all, so a denied
  // branch asserted before the goto could never fail.
  for (const [login, slug, allowed] of [
    ['e2e_tech', 'blood_results', true],   // lab_tech, ci_results_blood
    ['e2e_tech', 'pcr_results', true],     // lab_tech, ci_resutls_pcr (misspelled)
    ['e2e_tech', 'ci_receiving', true],    // lab_tech, ci_receiving -- the rack tab
    ['e2e_sec', 'blood_results', false],   // lab_sec holds neither
    ['e2e_sec', 'pcr_results', false],
  ] as const) {
    e2e(`${login} ${allowed ? 'reaches' : 'is denied'} admin.php?page=${slug}`, async ({ pageAs }) => {
      const screen = SCREENS[slug];
      const page = await pageAs(login);

      // FACT ONE -- THE MENU LINK IS THE GATE (D250). Menu registration is the
      // only capability check these screens carry, so the link the analyst can
      // see and the authorization decision are the same event.
      await page.goto('/wp-admin/');
      await expect(
        page.locator('#adminmenu').getByRole('link', { name: screen.menu, exact: true }),
        `${login} ${allowed ? 'finds' : 'never finds'} "${screen.menu}" in the admin menu`,
      ).toHaveCount(allowed ? 1 : 0);

      // FACT TWO -- WHAT THE ANALYST GETS BY TYPING THE URL ANYWAY. A separate
      // fact from fact one, and the reason D250 exists: a screen that is merely
      // unlisted is the least-guarded surface in this app.
      await page.goto(`/wp-admin/admin.php?page=${slug}`);
      // D392 -- BOUNDED ON THE DENIED BRANCH ONLY. The denial page carries a
      // 3 s meta-refresh to page=welcome; measured on this stack for both
      // denied slugs, t=0..2 denial 1 / #adminmenu 0, t=3 the fuse fires, t=4+
      // the URL is page=welcome with denial 0 / #adminmenu 1. The default 5 s
      // expect timeout OUTLIVES the page being asserted, so a slow first poll
      // would grade the Welcome page and report the wrong screen for an authz
      // regression. The allowed screens carry no refresh (measured: refresh
      // count 0 on all three) and are left on the default.
      const fused = allowed ? {} : { timeout: BEFORE_BOUNCE_MS };
      await expect(
        page.getByText('Sorry, you are not allowed to access this page.'),
        `${login} ${allowed ? 'is never shown' : 'is shown'} the core denial notice on ${slug}`,
      ).toHaveCount(allowed ? 0 : 1, fused);
      // wp_die() paints a bare error page -- no admin menu, nowhere to go next.
      // Separates a hard stop from a normal screen carrying a notice.
      await expect(
        page.locator('#adminmenu'),
        'the denied analyst lands on a bare error page with no admin menu to navigate from',
      ).toHaveCount(allowed ? 1 : 0, fused);

      // ...and the screen ACTUALLY DREW for the persona who is allowed in: a
      // PHP fatal inside the tab render also answers 200 with no denial text.
      await expect(
        page.locator('#wpbody-content').getByRole(screen.role, { name: screen.name, exact: true }),
        `${login} ${allowed ? 'sees' : 'never sees'} ${screen.seen}`,
      ).toHaveCount(allowed ? 1 : 0, fused);

      // ...and the FUSE ITSELF, pinned rather than waited out. The 3 and the
      // destination are what BEFORE_BOUNCE_MS is derived from, so this is the
      // line that reds if the fuse is retimed -- see D392 for why this replaced
      // a toHaveURL(/page=welcome/) that waited the bounce out.
      await expect(
        page.locator('meta[http-equiv="refresh"][content^="3;"][content*="page=welcome"]'),
        allowed
          ? `${login} is on a live screen -- nothing is counting down to bounce them off it`
          : `${login} is bounced to the Welcome page after 3 s -- the denial is a detour, not a dead end`,
      ).toHaveCount(allowed ? 0 : 1, fused);
    });
  }
});

// ===========================================================================
// Scenarios blocked by the environment, not by the test
// ===========================================================================

e2e.describe('blocked @tst', () => {
  // CI_ALL_BATCH_TYPES is ['tox','blood','pcr'] on the running instance (D101),
  // so dbDelta never created ci_batches_gene / ci_reports_gene /
  // ci_results_gene -- SHOW TABLES returns none of the three -- and gene_results
  // is gated on ci_results_gene, which neither lab_tech nor ref_lab_tech holds
  // live. No precondition row can be created by any path.
  e2e.fixme('S-TST-06 gene panel self-release: gene absent from CI_ALL_BATCH_TYPES, no gene tables exist', () => {});

  // Same gate for allergy: ci_batches_allergy does not exist, so the
  // scenario's own precondition row cannot be created.
  e2e.fixme('S-TST-07 allergy PDF-only result: allergy absent from CI_ALL_BATCH_TYPES, no ci_batches_allergy table', () => {});

  // ci_results_antibiotics rows are keyed (report_id, btype, te_ID, ta_ID)
  // where te_ID is a pathogen from ci_test_panels te_type='cupat' and ta_ID an
  // antibiotic from te_type='cuant' (parts/blood/results_edit.php:646-665).
  // This database holds ZERO rows of either type and ZERO ci_test_groups with
  // tg_manual_antibiotic=1, so the AST grid has no identifiers to post; any
  // te_ID/ta_ID would be invented and would not join back for rendering.
  // Separately: 10-scenarios.md's regression case is wrong on this build. The
  // saver always writes `'btype' => $btype` from $_POST['btype']
  // (ci_save_result_edit.php:410); omitting the key defaults $btype to 'tox',
  // not 'pcr' (:23). The 'pcr' default exists only in the column definition.
  e2e.fixme('S-TST-08 antibiotic susceptibility partition: no cuant/cupat reference rows exist', () => {});
});
