import { expect, type Page } from '@playwright/test';
import { test as e2e, BEFORE_BOUNCE_MS } from '../fixtures/test';

import { expectBatch } from '../fixtures/assert-domain';
import { CHARSET, infra } from '../fixtures/seed-domain';
import { Db } from '../fixtures/db';

/**
 * ORD -- staff ordering and requisition entry.
 *
 * S-ORD-01 is the vertical slice: nothing downstream in the catalog exists
 * until a batch with a minted accession exists.
 *
 * Persona: leadlabsecman, NOT lab_sec (R5/D162). Verified against the running
 * instance -- the INSTALLED lab_sec role carries 29 capabilities and
 * form_requisition is not one of them, so it 403s on tox_req_form. The source
 * at install_roles.php:344 does grant it, but Tr_Base_Class_V4::create_role
 * does not rewrite a role that already exists, so the stale definition baked
 * into default.sql is what is in force. leadlabsecman carries the capability in
 * both source and database. The second test below is the negative control that
 * keeps this honest.
 */

e2e.describe('S-ORD-01 @smoke @ord', () => {
  e2e('staff requisition entry mints an accession into the tenant lab', async ({
    seedRequisition, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);
    // Obviously synthetic, and unique per (run, worker) -- D136.
    const patientLast = `Ordsyn${runKey}`;

    const seeded = await seedRequisition(patientLast);

    // Not `toBeLessThan(400)`: a login redirect is 302 and would pass that.
    expect(seeded.status, 'the saver answers 200 on the full-document POST').toBe(200);

    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });
    console.log(
      `S-ORD-01 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen}`,
    );
  });
});

/**
 * WAVE 5 -- the requisition form driven as a user drives it.
 *
 * select2 4.x renders the native <select> with tabindex=-1 and aria-hidden=true
 * (measured on this stack for bat[doc_ID], pa[pa_state], pa[pa_gender],
 * ins1[ins_state], bat[custom_profile]), so `selectOption()` fails Playwright's
 * visibility check. D383 fallback 5: click the rendered combobox, then pick the
 * option -- select2 marks options `role=treeitem`, NOT `role=option`.
 *
 * LOCAL ON PURPOSE. There is exactly one caller. It moves to fixtures/ when a
 * second spec needs it, not before.
 */
async function pickSelect2(page: Page, name: string, label: string) {
  await page.locator(`select[name="${name}"] + span.select2`).getByRole('combobox').click();
  await page.getByRole('treeitem', { name: label, exact: true }).click();
}

/**
 * The DOB field is `readonly` behind a jQuery-UI datepicker (measured:
 * `input[name="pa[pa_dob]"].readOnly === true`), so typing into it is not a
 * user action that exists. The widget's year <select> FLOORS AT 1906 -- read
 * live, first option 1906, last 2026 -- which is the whole reason wave 0.4
 * moved the fixture default off `1900-01-01`: that date is unreachable through
 * the only control the operator has.
 *
 * `monthIndex` is 0-based, because jQuery UI's month <select> is. On the
 * current year the month list is TRUNCATED by the `limitfuture` maxDate
 * (measured: Jan..Aug on 2026-08-25), so the year is always chosen FIRST.
 */
async function pickDate(page: Page, name: string, year: string, monthIndex: string, day: string) {
  await page.locator(`input[name="${name}"]`).click();
  const dp = page.locator('#ui-datepicker-div');
  await dp.locator('select.ui-datepicker-year').selectOption(year);
  await dp.locator('select.ui-datepicker-month').selectOption(monthIndex);
  await dp.getByRole('link', { name: day, exact: true }).click();
}

/**
 * The clinic and ordering provider wave 0.2 created FOR THIS POOL LAB.
 *
 * `infra()` returns the two SHARED clinics, both of which carry
 * `cli_lab-ID` = the lab-35 E2E lab. `parts/tox/req_form.php:349` filters the
 * dropdown by the persona's own labs, so neither is offered to a pool persona
 * and neither can be what a pool clerk picks -- measured: the select holds
 * exactly one real option, `E2E Clinic <prefix>`. Resolved from the database so
 * this does not go stale if SPEC_LAB moves `ord` to another pool slot.
 */
function poolPractice(db: Db, prefix: string) {
  const [row] = db.query(
    `SELECT c.cID, d.doc_ID, CONCAT(d.doc_last, ' ', d.doc_first)
       FROM ci_clinic c JOIN ci_doc d ON d.cli_ID = c.cID
      WHERE c.cli_clinic = ${Db.q(`E2E Clinic ${prefix}`)}`,
  );
  if (!row) throw new Error(`no wave-0.2 clinic for pool lab ${prefix} -- run env/seed.sh`);
  const [clinicId, docId, docLabel] = row;
  return { clinicId, docId, docLabel };
}

e2e.describe('S-ORD-01u @ord @mutates', () => {
  /**
   * S-ORD-01u -- S-ORD-01 AS THE ORDERING CLERK PERFORMS IT.
   *
   * D382: this ADDS to S-ORD-01, it does not replace it. The two are not the
   * same request, and the difference is not cosmetic -- measured on the pinned
   * form for this persona:
   *
   *   - the form emits NO `bat[lab_id]` at all. `parts/tox/req_form.php:463-468`
   *     only renders the Lab select when the persona holds more than one lab, so
   *     for a single-lab clerk the destination lab is DERIVED, not posted. The
   *     HTTP twin hands the saver the lab id it wants and then asserts it came
   *     back; this test asserts the app ROUTED the specimen to the right lab
   *     with nothing but THE CLINIC to go on. That is the tenancy claim R10
   *     actually cares about, and no HTTP test in the suite makes it.
   *
   *     CORRECTED, and the old wording was wrong in a way that mattered. It
   *     read "with nothing but the clinic and the session to go on", which
   *     names two derivations where the app performs one. The saver's
   *     lab-derivation block is `ci_save_form_requisition.php:143-152` and it is
   *     TWO branches in sequence:
   *
   *         :145  if (@$batch['lab_id'] == 0 && $batch['cli_ID'] > 0)
   *                   $batch['lab_id'] = tr_get_lab_id_by_clinic($batch['cli_ID']);
   *         :150  if (empty($batch['lab_id']))
   *                   $batch['lab_id'] = CI_Base_Lab::get_current_lab_id();
   *
   *     `bat[cli_ID]` is `required` on this form and the fixture posts it too,
   *     so the FIRST branch always resolves and `:150` never executes on any
   *     flow this suite covers. Confirmed in the data as well as the code:
   *     `E2E Clinic LAA` carries `cli_lab-ID` = 4 = LAA (read 2026-08-26), and
   *     `tr_get_lab_id_by_clinic()` is a one-line read of that column
   *     (`inc/functions.php:310-314`). Every prefixed accession this test
   *     asserts is therefore CLINIC-derived, start to finish.
   *
   *     And `:150` is not "the session" either, which is the second half of the
   *     correction: `CI_Base_Lab::get_current_lab_id()` (`inc/ci_base_lab.php:224-237`)
   *     calls `get_user_lab_ids()` -- USER META, `_lab_ids` -- and only falls
   *     back to the blog id. Nothing about it is session-borne. Saying "the
   *     session" invited the reader to believe this test covers a second
   *     derivation; it covers one, and the line the old docblock advertised is
   *     dead code for every flow in this file.
   *   - the form emits `col[col_ID]`, and NO `bat[col_ID]` (measured: counts 1
   *     and 0). The fixture posts `bat[col_ID]`. Different key, different array.
   *   - the panel checkbox posts the UPPERCASED ANALYTE NAME
   *     (`E2E ANALYTE ONE`), where the fixture posts `group_<tg_ID>`.
   *     See `expectBatch`'s `panelContains`.
   *
   * NEW COVERAGE, not a re-skin. The fixture sends 14 fields. The form requires
   * 24 (31 controls counting each radio), and the ten it never sends are the
   * patient address block and the whole `ins1[...]` insurance block -- which is
   * why `ci_patients` and `ci_insurances` are asserted below and are asserted
   * NOWHERE in the ordering path today.
   *
   * D386 -- the read-back is a SERVER RENDER by construction: the requisition
   * form is a full-page POST (`parts/tox/req_form.php:415`, method=post, no
   * XHR), so the page asserted below IS the saver's own response. Nothing here
   * is DOM a click handler wrote.
   *
   * D396 -- this cannot race its HTTP twin. Every read is scoped to this test's
   * own patient key (`Ordsyn<runKey>u` vs the twin's `Ordsyn<runKey>`), exact
   * match, never LIKE, never a watermark over a shared table.
   */
  e2e('a clerk fills the requisition form and the accession on screen is the row the lab files', async ({
    authedPage, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);
    const practice = poolPractice(db, tenant.prefix);
    const patientLast = `Ordsyn${runKey}u`;
    const patientName = `${patientLast}, Synthetic`;
    const page = authedPage;

    // PRECONDITION BEFORE NAVIGATION (D034). Everything asserted afterwards has
    // to have been created by this test.
    const keyRowsBefore = Number(
      db.scalar(`SELECT COUNT(*) FROM ci_batches WHERE patient_name = ${Db.q(patientName)}`),
    );
    expect(keyRowsBefore, 'no batch for this patient key before the clerk starts').toBe(0);
    expect(db.scalar(`SELECT COUNT(*) FROM ci_patients WHERE pa_last = ${Db.q(patientLast)}`),
      'no patient record before the clerk starts').toBe('0');

    await page.goto('/wp-admin/admin.php?page=tox_req_form');
    // The screen DREW. A PHP fatal inside the render also answers 200.
    await expect(
      page.getByRole('heading', { name: 'THERAPEUTIC DRUG TEST REQUISITION FORM', exact: true }),
      'the clerk is looking at the tox requisition form',
    ).toBeVisible();

    // PRACTICE. Wave 0.2 gives this pool lab exactly one clinic, and
    // `parts/tox/req_form.php:530` pre-selects it when it is the only option --
    // so the clerk picks a provider, not a practice. Asserted rather than
    // assumed: an empty clinic select is the failure wave 0.2 exists to fix,
    // and `bat[cli_ID]` is `required`, so it would surface as a silent
    // jQuery-validate bounce with no error text of its own.
    await expect(page.locator('select[name="bat[cli_ID]"]'),
      'the clerk’s own clinic is pre-selected (wave 0.2)').toHaveValue(practice.clinicId);
    await pickSelect2(page, 'bat[doc_ID]', practice.docLabel);

    // SPECIMEN.
    await page.getByRole('radio', { name: 'Urine', exact: true }).check();

    // PATIENT. `pa[pa_last]`/`pa[pa_first]` are the only two fields the HTTP
    // fixture and this test have in common in this block.
    await page.locator('input[name="pa[pa_last]"]').fill(patientLast);
    await page.locator('input[name="pa[pa_first]"]').fill('Synthetic');
    // FAILURE MODE 7 -- the probe and its control, in the same document.
    // `ci_patients` is `latin1_swedish_ci` on this stack, so Address takes the
    // bytes latin1 CANNOT hold and City takes bytes it can (CHARSET, in
    // fixtures/seed-domain.ts). A patient whose street this clerk cannot type
    // is not an edge case in a laboratory.
    await page.locator('input[name="pa[pa_address]"]').fill(CHARSET.cyrillic);
    await page.locator('input[name="pa[pa_city]"]').fill(CHARSET.latin1Safe);
    await pickSelect2(page, 'pa[pa_state]', 'Illinois');
    await page.locator('input[name="pa[pa_zip]"]').fill('60601');
    await pickSelect2(page, 'pa[pa_gender]', 'Female');
    await pickDate(page, 'pa[pa_dob]', '1950', '0', '1');
    await page.locator('input[name="pa[pa_phone]"]').fill('3125550100');

    // INSURANCE. Ten controls the HTTP fixture sends none of.
    // `ins1[ins_relation]` is addressed by `name=` rather than by role: the
    // SECONDARY insurance block renders inside the same `#use_required_ins`
    // container with the identical three labels, so `getByRole('radio', {
    // name: 'Self' })` resolves 2 (measured). `name=` is what the browser
    // itself submits and is 1:1 with the column (D383 fallback 3).
    await page.locator('input[name="ins1[ins_last]"]').fill(patientLast);
    await page.locator('input[name="ins1[ins_first]"]').fill('Synthetic');
    await page.locator('input[name="ins1[ins_relation]"][value="Self"]').check();
    await page.locator('input[name="ins1[ins_company]"]').fill('E2E-Insurer');
    // THE SAME CYRILLIC BYTES, one form field lower, into a table that is
    // `utf8mb3` rather than `latin1` (information_schema, read 2026-08-26).
    // Asserted UNPINNED below, and that is the point: if these survive while
    // `pa_address` does not, the browser, jQuery-validate, the POST and the
    // saver are all demonstrably fine and the storage column is the fault.
    await page.locator('input[name="ins1[ins_address]"]').fill(CHARSET.cyrillic);
    await page.locator('input[name="ins1[ins_city]"]').fill(CHARSET.apostrophe);
    await pickSelect2(page, 'ins1[ins_state]', 'Illinois');
    await page.locator('input[name="ins1[ins_zip]"]').fill('60601');
    await page.locator('input[name="ins1[ins_member_id]"]').fill('E2EMEM0001');

    // ORDER. POC and the screening choice are `required` radios with unique
    // labels; `bat[custom_profile]` is the D289 field whose absence makes a
    // specimen silently undeliverable, and here it is chosen on screen.
    await page.getByRole('radio', { name: 'POC NOT PERFORMED', exact: true }).check();
    await pickSelect2(page, 'bat[custom_profile]', 'Do Not Use Custom Profile');
    await page.getByRole('radio', { name: 'No Screening', exact: true }).check();
    await page.getByRole('checkbox', { name: 'E2E Analyte One', exact: true }).check();

    // SUBMIT. Reaching the saver at all is the jQuery-validate assertion: the
    // form carries `class="need_validate"` and 31 required controls, and an
    // incomplete document never leaves the browser. There are three submits on
    // this form -- Submit, "Print Barcode / Submit" and Save -- so the name
    // match is exact.
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
    await page.waitForLoadState('load');

    // ---------------------------------------------------------------- the DB
    const seeded = {
      patientLast,
      patientName,
      status: 200,
      // The saver's own reply IS this page: the requisition form is a full-page
      // POST, so the document now loaded is the response the saver rendered
      // (see SeedRequisitionResult.body). Nothing below asserts on it -- the
      // confirmation is asserted through the DOM a few lines down -- but the
      // shape is the shared one, so it is filled honestly rather than stubbed.
      body: await page.content(),
      keyRowsBefore,
      keyRowsAfter: Number(
        db.scalar(`SELECT COUNT(*) FROM ci_batches WHERE patient_name = ${Db.q(patientName)}`),
      ),
    };
    const { assen, mid } = expectBatch(db, seeded, tenant, {
      testGroupId: f.testGroupId,
      panelContains: 'E2E ANALYTE ONE',
    });

    // ------------------------------------------------------------ the screen
    // THE OPERATOR-FACING NUMBER IS THE FILED NUMBER. Two independent server
    // renders carry it: the saver's own confirmation, composed in
    // `inc/actions/ci_save_req.php:569` from `$batch['assen']`, and the
    // accession field the form re-read for the saved row
    // (`parts/tox/req_form.php:1729`). Neither is a value this test typed --
    // the clerk left the accession to `auto_generated`, and the number is
    // minted server-side by tr_get_new_assen(). D397: the comparison is
    // rendered-vs-queried, not a value against itself.
    //
    // Why it matters rather than merely being true: the accession is what gets
    // printed onto the specimen barcode. A save that files LAA1234570 while
    // telling the clerk LAA1234569 mislabels the tube.
    const confirmation = page.locator('.q_message').filter({
      hasText: `Saved REQUISITION FORM ${assen}`,
    });
    await expect(confirmation,
      'the clerk is told, in the app’s own words, which accession was filed').toBeVisible();

    // THE SECOND RENDER, and the reason it is a second one. The saver's
    // response re-renders a BLANK form for the next order, and `req_form.php`'s
    // own script blanks `#number_assen` on load (`:2267` / `:2397`,
    // `.val('').attr('readonly','1')`) -- measured: value `""` on that page even
    // though the row exists. Asserting it there would be asserting DOM the
    // client authored, which is precisely what D386 forbids; the confirmation's
    // Edit link is the operator's own way back to the record, so follow it and
    // read the accession off the server's render of the saved row.
    await confirmation.getByRole('link', { name: 'Edit', exact: true }).click();
    await page.waitForLoadState('load');
    expect(page.url(), 'the Edit link points at the record that was just filed')
      .toContain(`id=${mid}`);
    // ...and because `assen` is already pinned to the tenant prefix by
    // expectBatch's tenancy triple, this one navigation is what carries the
    // whole rendered-tenancy claim: the clerk posted NO lab id (D404), so the
    // prefixed number the screen shows can only have come from the routing the
    // app performed for itself -- specifically from the CLINIC, via
    // `tr_get_lab_id_by_clinic()` at `ci_save_form_requisition.php:145`. See the
    // correction in this test's docblock: the fallback at `:150` is not reached
    // from here and this assertion says nothing about it.
    await expect(page.locator('#number_assen'),
      `the saved requisition reads back the same ${tenant.prefix} accession the lab filed`)
      .toHaveValue(assen);

    // ------------------------------------- what only the FORM ever posts
    const [ext] = db.query(
      `SELECT cli_ID, doc_ID, col_ID, custom_profile, type_of_specimen, poc_perform, has_screening
         FROM ci_batches WHERE mid = ${Db.q(mid)}`,
    );
    const [cliId, docId, colId, profile, specimen, poc, hasScreening] = ext;
    expect(cliId, 'ci_batches.cli_ID -- the clinic that routed the specimen')
      .toBe(practice.clinicId);
    expect(docId, 'ci_batches.doc_ID -- picked through select2').toBe(practice.docId);
    // The form posts col[col_ID]; the HTTP fixture posts bat[col_ID]. Only one
    // of those two keys is a thing a browser sends.
    expect(colId, 'ci_batches.col_ID -- written from col[col_ID], not bat[col_ID]')
      .toBe(f.collectorId);
    expect(profile, 'ci_batches.custom_profile (D289 -- NULL here hides the specimen from delivery)')
      .toBe('Do Not Use Custom Profile');
    expect(specimen, 'ci_batches.type_of_specimen').toBe('Urine');
    expect(poc, 'ci_batches.poc_perform').toBe('0');
    expect(hasScreening, 'ci_batches.has_screening -- the clerk chose No Screening').toBe('0');

    // THE PATIENT RECORD. Ten columns the ordering path has never asserted,
    // because the HTTP fixture sends four of them and no address at all.
    const patients = db.query(
      `SELECT pa_ID, pa_first, pa_dob, pa_gender, pa_address, pa_city, pa_state, pa_zip, pa_phone
         FROM ci_patients WHERE pa_last = ${Db.q(patientLast)}`,
    );
    expect(patients.length, `exactly one ci_patients row for ${patientLast}`).toBe(1);
    const [paId, paFirst, paDob, paGender, paAddress, paCity, paState, paZip, paPhone] = patients[0];
    // `pa_address` is held back for the mode-7 pin at the very end of this
    // test. Everything else round-trips HERE, unmarked -- including the
    // ACCENTED city: `é` is latin1 0xE9, so `José-City` coming back mangled
    // would be a transport or saver fault and not the column charset.
    expect([paFirst, paDob, paGender, paCity, paState, paZip, paPhone],
      'ci_patients [first, dob, gender, city, state, zip, phone]')
      .toEqual(['Synthetic', '1950-01-01', 'F', CHARSET.latin1Safe, 'IL', '60601', '3125550100']);

    // THE INSURANCE RECORD, and that it is attached to THIS patient and THIS
    // lab. `ins_company` is deliberately not compared: the app normalises it
    // (measured: `E2E-Insurer` stored as `E2e-insurer`), and an assertion on a
    // transform this test does not otherwise exercise would only ever break for
    // the wrong reason.
    const insurances = db.query(
      `SELECT lab_ID, ins_last, ins_first, ins_relation, ins_member_id,
              ins_address, ins_city, ins_state, ins_zip
         FROM ci_insurances WHERE pa_ID = ${Db.q(paId)}`,
    );
    expect(insurances.length, `exactly one ci_insurances row for pa_ID ${paId}`).toBe(1);
    expect(insurances[0], 'ci_insurances [lab_ID, last, first, relation, member_id, address, city, state, zip]')
      .toEqual([tenant.labId, patientLast, 'Synthetic', 'Self', 'E2EMEM0001',
                CHARSET.cyrillic, CHARSET.apostrophe, 'IL', '60601']);

    console.log(
      `S-ORD-01u worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen}`,
    );

    // ----------------------------------------- FAILURE MODE 7, PINNED (D418)
    //
    // The seventh way this app answers 200 while doing the wrong thing: the row
    // is ACCEPTED, STORED CORRUPTED, and reported as saved. `ci_patients` is
    // `latin1_swedish_ci`; the connection is utf8mb4; `wpdb::strip_invalid_text()`
    // short-circuits on a latin1 column ("latin1 can store any byte sequence"),
    // so the bytes reach MySQL unvalidated and MySQL substitutes `?` for every
    // character it cannot represent. Measured through the app's own connection:
    // `Иван-Way` stores as `????-Way`, `insert()` returns 1, the clerk is told
    // "Saved REQUISITION FORM <accession>". The other six modes all mean
    // "nothing was written" and a row count catches them; this one writes the
    // wrong patient and no row count ever will.
    //
    // THE MARKER IS HERE AND NOWHERE EARLIER. Everything above -- the accession,
    // the tenancy, the screen, the accented city, the identical Cyrillic bytes
    // that survived in `ci_insurances` -- runs UNMARKED, so a failure in any of
    // it is reported as the failure it is. Only the two lines below are
    // expected to fail, and only for this reason. Delete `e2e.fail()` the day
    // the PHI tables are converted to utf8mb4; the pin goes red the moment the
    // defect is fixed, which is what asks for the deletion.
    expect(paAddress,
      `the clerk typed ${CHARSET.cyrillic} into Address -- ci_patients.pa_address holds those `
      + 'characters, not question marks')
      .toBe(CHARSET.cyrillic);
    await expect(page.locator('input[name="pa[pa_address]"]'),
      'and the saved requisition shows the clerk back the address they typed')
      .toHaveValue(CHARSET.cyrillic);
  });
});

/**
 * THE NUMBER THAT GOES ON THE TUBE, WHEN THE NUMBER IS ALREADY TAKEN.
 *
 * A second specimen arrives on an accession the laboratory has already used --
 * a re-scanned pre-printed label, an interface replay, a short form carrying a
 * barcode from a previous visit. The app does not refuse it and does not
 * overwrite the first specimen: `ci_save_form_requisition.php:245-259` finds the
 * existing number and files the newcomer as `<accession>-01`, then tells the
 * clerk so at `:686` and warns at `:955`.
 *
 * D382 -- THIS ADDS TO `acc.spec.ts`'s S-ORD-02, IT DOES NOT REPLACE IT, and
 * the two assert different things. S-ORD-02 asserts the ROW: the sibling was
 * created, it is distinct, and the base row survived untouched. Everything
 * below is about the OPERATOR-FACING NUMBER, which no test in the suite has
 * ever looked at, and the gap between the two is a mislabelled tube: a save
 * that FILES `LAA1234567-01` while TELLING the clerk `LAA1234567` puts the
 * wrong number on the specimen and every downstream screen then resolves the
 * label to the OTHER patient's order.
 *
 * WHY IT IS ALSO THE REPAIR OF A TEST THAT PASSED BY LUCK. Measured: neutering
 * the suffix at `:256` does not make S-ORD-02 fail on its suffix assertion. It
 * makes the second INSERT collide on the UNIQUE `assen` index (verified against
 * the live schema: `SHOW INDEX FROM ci_batches` reports `Key_name: assen`,
 * `Non_unique: 0`), so no row is gained, so `expectBatch`'s rows-gained
 * PRECONDITION reds first and the test never executes the line it exists for.
 * The suite went red -- for a reason that names replayed `form_unique_id`s and
 * missing columns, neither of which happened. That is check (4) of the pass-2
 * assertion review: an earlier precondition failing under the SAME fault and
 * stopping the real assertion from ever being reached.
 *
 * The assertions below are reached WHATEVER the database did, because they are
 * made against the saver's own reply (`SeedRequisitionResult.body`) before any
 * row is read. Under the neutered suffix the reply is
 * `Error REQUISITION FORM. <duplicate-key error>` and the first assertion in
 * the test is the one that reds, naming the suffix and the tube.
 *
 * NOT DRIVEN THROUGH THE BROWSER, and that is a property of the product rather
 * than a shortcut: the accession field on this form is blanked and set
 * `readonly` by the form's own script on load (`parts/tox/req_form.php:2267`,
 * `:2397` -- see S-ORD-01u above), so a clerk sitting at the tox requisition
 * form has no control with which to submit an accession that already exists.
 * The duplicate arrives over the same POST interface this test uses.
 *
 * D396 -- cannot race a twin. Both patient keys are unique to this test, and
 * the accession it asserts on is one this test's own first save minted.
 */
e2e.describe('S-ORD-02r @ord @acc', () => {
  /** `Saved REQUISITION FORM <accession>`, as `Tr_Session_Class::add()` composed it. */
  const accessionTheClerkIsTold = (body: string): string | undefined =>
    body.match(/Saved REQUISITION FORM ([A-Za-z0-9-]+)/)?.[1];

  e2e('a duplicate accession is filed under a suffixed number, and that is the number the clerk is shown', async ({
    seedRequisition, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);
    const baseLast = `Ordsfx${runKey}A`;
    const dupLast = `Ordsfx${runKey}B`;

    // ---------------------------------------------------------- precondition
    // The first specimen has to be COMPLETED. A non-completed row routes the
    // second save into the stub-completion branch instead
    // (`ci_save_form_requisition.php:74-85`, `batch_status!='completed'`), which
    // UPDATES the first row and never reaches the suffix block at all --
    // acc.spec.ts's S-ORD-03 is that other branch.
    const base = await seedRequisition(baseLast, { 'bat[batch_status]': 'completed' });
    expect(base.status, 'the first requisition saves').toBe(200);
    const { assen: baseAssen } = expectBatch(db, base, tenant, {
      testGroupId: f.testGroupId,
      batchStatus: 'completed',
    });
    // The CONTROL for the assertion below, and it belongs here rather than
    // beside it: a first save that already reported the wrong number would make
    // the duplicate's confirmation unreadable as evidence of anything.
    expect(
      accessionTheClerkIsTold(base.body),
      `the first specimen's own confirmation names ${baseAssen}, the number filed for it -- `
      + 'without this the comparison below has no baseline',
    ).toBe(baseAssen);

    // ------------------------------- the second specimen, on the SAME number
    const dup = await seedRequisition(dupLast, { 'bat[assen]': baseAssen });
    expect(dup.status, 'the saver answers 200 on the duplicate document').toBe(200);

    // ------------------------------------------------------ WHAT THE CLERK SEES
    // FIRST, and deliberately so: this is the assertion the scenario is named
    // for, and it is stated where no database precondition can get in front of
    // it. Read off the saver's own full-page response -- the `.q_message` block
    // the clerk is looking at, composed server-side at `:686` from the row the
    // app has just written. Not post-XHR DOM: this saver is a plain form POST
    // with no XHR anywhere in it (D386 satisfied by construction).
    expect(
      accessionTheClerkIsTold(dup.body),
      `a second specimen was ordered on accession ${baseAssen}, which the laboratory has `
      + `already used. The clerk must be told to label this tube ${baseAssen}-01 -- the `
      + 'number the app actually filed it under. Being told anything else labels the tube '
      + 'with another patient\'s order; being told nothing means the specimen was refused '
      + 'outright and this patient has no order at all',
    ).toBe(`${baseAssen}-01`);
    // ...and the clerk is WARNED, not left to notice the trailing -01. This is a
    // different string from a different line (`:955`, behind
    // `$has_duplicate_assen`), so it fails independently of the confirmation:
    // a build that files the sibling silently reds here and nowhere else.
    expect(
      dup.body,
      `the clerk is not warned that ${baseAssen} was already in use -- the duplicate is `
      + 'filed silently and the bench has no way to know two tubes are involved',
    ).toContain(`Warning duplicate Accession #${baseAssen}-01 Added!`);

    // ------------------------------------------------------------- THE ROW
    // D382 again: the screen does not replace the row. `expectBatch` re-asserts
    // the full standard -- exactly one row on this patient key, a non-empty and
    // UNIQUE accession, batch_type, panel, and the tenancy triple -- and the
    // pin below adds the value the two halves must agree on.
    const { assen: dupAssen, mid: dupMid } = expectBatch(db, dup, tenant, {
      testGroupId: f.testGroupId,
    });
    expect(
      dupAssen,
      `ci_batches.assen for ${dupLast}: the second specimen must be FILED under `
      + `${baseAssen}-01, the same number the clerk was just shown. A row filed under a `
      + 'number the confirmation did not name is a tube the laboratory cannot find',
    ).toBe(`${baseAssen}-01`);

    console.log(
      `S-ORD-02r worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
      + `base=${baseAssen} dup=${dupAssen} mid=${dupMid}`,
    );
  });
});

e2e.describe('page-layer capability gate @smoke @ord', () => {
  // D105/D175: page-layer denials ARE enforced, so they are ordinary passing
  // assertions. (Only the tr_action DISPATCHER is ungated; those become
  // test.fail() expected-failures, never "the action succeeds".)
  //
  // This is also the regression guard on the persona choice above: the day
  // someone "fixes" install_roles.php and re-runs it, lab_sec starts reaching
  // this screen and this test tells us the S-ORD-01 persona rationale has moved.
  //
  // The denial is asserted on the RENDERED OUTCOME, not on a status code:
  // measured, admin.php answers HTTP 200 for both the allowed and the denied
  // persona and distinguishes them only by what it draws. D105 records this
  // denial as a 403; on this build it is a 200. Asserting the code would have
  // been a test that passes for the wrong reason in exactly the D140 way.
  //
  // WAVE 2 / D388 -- this SUPERSEDES the request-context/regex twin it replaces
  // rather than adding to it, and D382 does not apply: both forms issue the
  // SAME GET to the SAME handler (admin.php?page=tox_req_form), so the
  // conversion orphans no handler, and every assertion below is a strict
  // superset of the two regexes (/name="tr_action"/ and /Sorry, you are not
  // allowed/) that used to stand in for them.
  //
  // Navigation comes FIRST, and there is ONE boolean-driven assertion per fact:
  // pageAs() hands back a page sitting on about:blank, where toHaveCount(0) is
  // true for any product behaviour whatsoever, so a denied branch asserted
  // before the goto could never fail.
  for (const [login, allowed] of [['e2e_sec', false], ['e2e_lead0', true]] as const) {
    e2e(`${login} ${allowed ? 'reaches' : 'is denied'} admin.php?page=tox_req_form`, async ({ pageAs }) => {
      const page = await pageAs(login);

      // FACT ONE -- THE MENU LINK IS THE GATE (D250). Menu registration is the
      // only capability check these screens get, so the link the user can see
      // and the authorization decision are one and the same event. Measured on
      // this stack: "Toxicology Req" is the link whose href is page=tox_req_form.
      await page.goto('/wp-admin/');
      await expect(
        page.locator('#adminmenu').getByRole('link', { name: 'Toxicology Req', exact: true }),
        `${login} ${allowed ? 'finds' : 'never finds'} Forms > Toxicology Req in the admin menu`,
      ).toHaveCount(allowed ? 1 : 0);

      // FACT TWO -- WHAT HAPPENS WHEN THE PERSONA TYPES THE URL ANYWAY. A
      // different fact from fact one, and the one D250 exists for: a screen
      // that is merely unlisted is the least-guarded surface in the app. Never
      // drop this leg when converting a gate test.
      await page.goto('/wp-admin/admin.php?page=tox_req_form');
      // D392 -- BOUNDED ON THE DENIED BRANCH ONLY. The denial page carries a
      // 3 s meta-refresh to page=welcome; measured on this stack, t=0..2 denial
      // 1 / #adminmenu 0, t=3 the fuse fires, t=4+ the URL is page=welcome with
      // denial 0 / #adminmenu 1. The default 5 s expect timeout OUTLIVES the
      // page being asserted, so a slow first poll would grade the Welcome page
      // and report the wrong screen for an authz regression. The allowed screen
      // carries no refresh and is left on the default.
      const fused = allowed ? {} : { timeout: BEFORE_BOUNCE_MS };
      await expect(
        page.getByText('Sorry, you are not allowed to access this page.'),
        `${login} ${allowed ? 'is never shown' : 'is shown'} the core denial notice`,
      ).toHaveCount(allowed ? 0 : 1, fused);
      // wp_die() paints a bare error page -- no admin menu, nowhere to go next.
      // Separates a hard stop from a normal screen that merely carries a notice.
      await expect(
        page.locator('#adminmenu'),
        `the denied persona lands on a bare error page with no admin menu to navigate from`,
      ).toHaveCount(allowed ? 1 : 0, fused);

      // ...and the screen ACTUALLY DREW for the persona who is allowed in: a
      // PHP fatal inside the form render also answers 200 with no denial text.
      const body = page.locator('#wpbody-content');
      await expect(
        body.getByRole('heading', { name: 'THERAPEUTIC DRUG TEST REQUISITION FORM', exact: true }),
        `${login} ${allowed ? 'sees' : 'never sees'} the requisition form banner`,
      ).toHaveCount(allowed ? 1 : 0, fused);
      // The rendered twin of the old /name="tr_action"/ regex: the control that
      // actually posts the requisition, not just the hidden field behind it.
      await expect(
        body.getByRole('button', { name: 'Save', exact: true }),
        `${login} ${allowed ? 'can submit' : 'is offered no way to submit'} a requisition`,
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
