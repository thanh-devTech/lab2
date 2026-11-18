import { expect } from '@playwright/test';
import type { APIRequestContext, Locator, Page } from '@playwright/test';
import { test as e2e } from '../fixtures/test';

import { expectBatch } from '../fixtures/assert-domain';
import { infra, scanBarcode, seedPcrRequisition } from '../fixtures/seed-domain';
import { Db } from '../fixtures/db';
import { wpEval } from '../fixtures/container';

/**
 * ACC -- lab accessioner / specimen receiver (personas.md 3.1).
 *
 * Persona: the worker's tenant persona, role `leadlabsecman`. Verified against
 * the RUNNING instance (D178 -- never from install_roles.php), the three
 * receiving role slugs hold:
 *
 *   lab_sec       (29 caps): ci_receiving, ci_custody_chain -- NO form_requisition
 *   labsecman     (24 caps): ci_receiving, ci_custody_chain -- NO form_requisition
 *   leadlabsecman (15 caps): ci_receiving, ci_custody_chain, form_requisition
 *
 * S-ACC-01/05/06 and S-ORD-02/03 all need a requisition as a precondition, so
 * only `leadlabsecman` can drive them end to end -- which is exactly the
 * persona the `tenant` fixture binds. S-ACC-02 needs `ci_receiving` alone and
 * runs on the same persona for tenancy symmetry.
 *
 * CATALOG CORRECTIONS proved against the running app (see the return notes):
 *  - btype is `tox` (`ci_batches`), not `pcr`. `pcr` has no order-entry saver
 *    reachable in this env, so a pcr precondition row cannot be created through
 *    the app (D085 forbids raw-SQL domain rows).
 *  - S-ACC-02's `initials1` is set only on a lane whose `ci_receiving_positions`
 *    subtype differs from its specimen. On the `urine` lane the app blanks it
 *    (ci_search_barcode_receiving.php:93-95). This spec scans the `jvhl` lane,
 *    where both catalog columns are genuinely written.
 *  - S-ORD-02's `ci_batches.has_duplicate_assen` column DOES NOT EXIST.
 *    `$has_duplicate_assen` is a local flag that only raises a session notice
 *    (ci_save_form_requisition.php:239,:954). The persisted outcome is the
 *    `-01` suffix, and that is what is asserted.
 *  - S-ORD-03's stub-completion is NOT `ci_save_req.php:106` (that branch is
 *    gated on `ci_batches_req`, and btype `req` is absent from
 *    CI_ALL_BATCH_TYPES here). The tox equivalent is
 *    ci_save_form_requisition.php:74-85, gated on `ci_batches`.
 *
 * Nothing here renders a results listing, so no test carries `@mutates`
 * (D034/D108 apply to the auto-approve-on-GET listings, not to the receiving
 * bench or the custody-chain reader). Every row written is run-keyed and
 * tenant-scoped, so these belong in `main`, not `serial`.
 */

/** A real datetime, not '' (NULL) and not the '0000-00-00' zero date. */
const DATETIME = /^2\d{3}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;

const RECEIVING_PAGE = '/wp-admin/admin.php?page=ci_receiving';
const CUSTODY_PAGE = '/wp-admin/admin.php?page=ci_custody_chain';

/**
 * The free-text practice on the SESSION row. Same string the HTTP fixture posts
 * (`scanBarcode`, seed-domain.ts:373) so the two entry points stay comparable.
 * It is `ci_receiving.re_practice`, not a clinic id -- nothing joins it.
 */
const SESSION_PRACTICE = 'E2E Test Clinic';

/**
 * Lane label AS THE OPERATOR READS IT, against the lane key the HTTP fixture
 * posts. `select.btype` renders `ci_receiving_positions.bname` and submits the
 * subtype (form_define.php:904-925 -> admin_ci_receiving_form.php:324-331).
 * Measured live 2026-08-25: Tox|ToxO|JVHL|JVHL-O|Blood|PCR.
 */
const LANE_LABEL = { urine: 'Tox', jvhl: 'JVHL' } as const;

// --- the receiving bench AS A SCREEN (D380/D383/D386) -----------------------

/**
 * Open the bench, make one lane ready to scan, and hand back the session row
 * and the lane row.
 *
 * WHY "Show All" AND NOT A DIRECT FILL. `tr.child` -- the whole lane form --
 * is `display:none` (css/admin.css:349) until the operator expands it, so the
 * barcode box is not in the accessibility tree and `getByRole` resolves 0 on
 * it. Measured: 0 before the click, 1 after. `a.show_hide_all` is the
 * operator's own control for that (admin_receiving.js:970, `.show_all` styled
 * in the page's own <style> block).
 *
 * WHY THE TWO FILLS. Tracking Number Barcode and Practice are `required` on
 * the session row and BOTH ARE EMPTY on a fresh bench (`re_practice` defaults
 * to `$default_clinic`, which admin_ci_receiving_form.php:31-35 leaves blank
 * whenever the install has more than one active clinic -- and wave 0.2 seeded
 * sixteen). admin_receiving.js:357-363 walks every `required` control in the
 * session row and refuses the scan with `alert('Please enter required
 * field!')` while either is blank. The HTTP fixture posts
 * `re_tracking_number_barcode: ''` straight past that gate
 * (seed-domain.ts:369), so this is coverage the HTTP twin does not have.
 */
async function openBench(page: Page, runKey: string, lane: keyof typeof LANE_LABEL) {
  await page.goto(RECEIVING_PAGE);
  await page.getByText('Show All', { exact: true }).click();

  // `.first()` HERE, and NOT in reloadBench -- the two are asking different
  // questions. This one picks a session to scan INTO, and any of today's
  // sessions in this lab will do; `tr.parent_c` and `tr.child` are emitted as a
  // matched pair on the same loop index (admin_ci_receiving_form.php:269,:322),
  // so `.first()` on both resolves to the same session and the lane the scan
  // posts into is the lane whose session id it carries. reloadBench is asking
  // which session the app CHOSE, which is not a question `.first()` can answer:
  // this file shares one lab and one persona across all workers
  // (`SPEC_LAB.acc`), so a concurrent worker's session may sort ahead of ours.
  const session = page.locator('tr.parent_c').first();
  const laneForm = page.locator('tr.child').first();

  // D383 fallback 4: `data-fname` is what admin_receiving.js:344-352 reads to
  // build the `parentdata` it posts. It is app wiring, not a test hook, and
  // these two inputs carry no label, no accessible name and no `name=`.
  const tracking = session.locator('input[data-fname="re_tracking_number_barcode"]');
  if ((await tracking.inputValue()) === '') await tracking.fill(`TRK${runKey}`);
  const practice = session.locator('input[data-fname="re_practice"]');
  if ((await practice.inputValue()) === '') await practice.fill(SESSION_PRACTICE);

  // Exactly one <select> lives in the lane row, and it is not a select2 (only
  // `.js_select2`/`.njs_select2` are upgraded -- admin.js:318-319), so the
  // native control is the visible one.
  await laneForm.getByRole('combobox').selectOption({ label: LANE_LABEL[lane] });
  return { session, laneForm };
}

/**
 * Type a barcode into the lane box and press Enter, exactly as a scanner does.
 *
 * `role` because the two benches type the same input differently: the
 * receiving bench emits `<input type="text" placeholder="Barcode">`
 * (admin_ci_receiving_form.php) -> role `textbox`, and the rack bench emits
 * `<input type="search" placeholder="Barcode" class="barcode_auto">`
 * (parts/rack/main.php:448) -> role `searchbox`. Measured: `textbox` resolves
 * 0 on the rack bench. The gesture is otherwise identical, so this stays ONE
 * helper rather than two.
 */
async function scan(laneForm: Locator, code: string, role: 'textbox' | 'searchbox' = 'textbox') {
  const box = laneForm.getByRole(role, { name: 'Barcode' });
  await box.fill(code);
  await box.press('Enter');
}

const benchRow = (page: Page, code: string) =>
  page.locator('tr.childp').filter({ hasText: code });

/**
 * D386 -- RE-RENDER FROM THE SERVER before believing anything on the bench.
 *
 * The scan's own row is `$tbl.prepend($(rs.html))` (admin_receiving.js:518-519)
 * and `rs.html` IS server markup, so unlike the review queue's Approve control
 * it is not client-authored text. It is still not proof the write landed: for a
 * requisitioned specimen the fragment renders `$batch`, a row read BEFORE
 * `$wpdb->update()` with the new values assigned onto it IN PHP MEMORY
 * (ci_search_barcode_receiving.php:497,506,616), so a `position` the update
 * dropped still prints. Reload, and read markup
 * admin_ci_receiving_form.php:493-568 produced from the stored row.
 *
 * Returns the specimen's row AND the session row that owns it -- looked up
 * through `data-id`/`data-p` rather than `.first()`, because two workers that
 * open a bench with no session on it both create one, and then `.first()` is
 * not necessarily the session this test scanned into.
 */
async function reloadBench(page: Page, code: string) {
  await page.reload();
  await page.getByText('Show All', { exact: true }).click();
  const row = benchRow(page, code);
  await expect(row, 'the reloaded bench still shows the specimen').toHaveCount(1);
  const laneForm = page.locator('tr.child').filter({ has: row });
  const session = page.locator(`tr.parent_c[data-p="batch_${await laneForm.getAttribute('data-id')}"]`);
  return { row, session };
}

/**
 * `td.assen`, `td.patient_name` and `td.col-position input` are the app's own
 * handles: admin_receiving.js:134-139 reads all three to build a print job, and
 * :539 keys the position editor on the third. D383 clause 4.
 */
const assenCell = (row: Locator) => row.locator('td.assen');
const patientCell = (row: Locator) => row.locator('td.patient_name');
const positionInput = (row: Locator) => row.locator('td.col-position input');

/** The lab the OPERATOR sees on the session holding the specimen. */
const sessionLab = (session: Locator) => session.locator('select[data-fname="re_lab_ID"]');

// --- the custody chain AS A SCREEN ------------------------------------------

/**
 * Search the custody chain for one accession, the way its reader does.
 *
 * The form is a plain GET (admin_ci_custody_chain.php:580-601), so every render
 * it produces is a server render. `<label>Accession</label>` carries no `for=`
 * and does not wrap the field, so `getByLabel` resolves nothing -- D383
 * fallback 3, and the field this rule was written for: `name="assen"` is what
 * the reader's own browser submits.
 */
async function searchCustodyChain(page: Page, assen: string) {
  await page.goto(CUSTODY_PAGE);
  await page.locator('input[name="assen"]').fill(assen);
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page.getByRole('heading', { name: 'Custody Chain' }),
    'the chain reader is on screen').toBeVisible();
}

/**
 * `tr_pdd_display_date($dt) . ' ' . tr_pdd_display_date($dt, 'h:i:s A')` --
 * how functions_custody_chain.php:91-124 prints the moment of a hop.
 * Built from the stored string, never from a Date: the value is a wall-clock
 * MySQL DATETIME written by PHP's `date_i18n`, and parsing it as UTC would
 * shift the assertion by the runner's offset.
 */
function displayDatetime(mysqlDatetime: string): string {
  const [d, t] = mysqlDatetime.split(' ');
  const [year, month, day] = d.split('-');
  const [hh, mm, ss] = t.split(':');
  const h24 = Number(hh);
  const h12 = String(h24 % 12 === 0 ? 12 : h24 % 12).padStart(2, '0');
  return `${month}/${day}/${year} ${h12}:${mm}:${ss} ${h24 < 12 ? 'AM' : 'PM'}`;
}

e2e.describe('S-ACC-01 @smoke @acc', () => {
  e2e('scanning a requisitioned specimen logs it in at the bench and takes a lane position', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);
    const today = db.scalar('SELECT CURDATE()');
    const uid = db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(tenant.login)}`);
    const patientLast = `Accrcv${runKey}A`;

    const seeded = await seedRequisition(patientLast);
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    const scan = await scanBarcode(tenantRequest, tenant, assen, { lane: 'urine', today });
    expect(scan.status, `bench refused ${assen}: ${scan.message ?? '(no message)'}`).toBe('ok');
    expect(scan.created_receiving, 'a new receiving session was opened').toBe(true);
    expect(Number(scan.re_ID), 'ci_receiving.re_ID').toBeGreaterThan(0);

    // The specimen side. One SELECT, exact match on the minted accession.
    const batch = db.query(
      `SELECT batch_status, spec_received, position, lab, lab_id
         FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    );
    expect(batch.length, `exactly one ci_batches row for ${assen}`).toBe(1);
    const [batchStatus, specReceived, position, lab, labId] = batch[0];
    expect(batchStatus, 'ci_batches.batch_status after receiving').toBe('received');
    expect(specReceived, 'ci_batches.spec_received').toMatch(DATETIME);
    expect(Number(position), 'the specimen took a lane position').toBeGreaterThan(0);
    // TENANCY, asserted -- receiving must not move the specimen out of its lab.
    expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
    expect(lab, 'ci_batches.lab').toBe(tenant.prefix);

    // The session side: one link row, joined to the session it belongs to.
    const links = db.query(
      `SELECT rb.re_ID, rb.btype, rb.created_by, re.re_type, re.re_author_id, re.re_lab_ID
         FROM ci_receiving_batches rb
         JOIN ci_receiving re ON re.re_ID = rb.re_ID
        WHERE rb.mid = ${Db.q(mid)}`,
    );
    expect(links.length, `exactly one ci_receiving_batches row for mid ${mid}`).toBe(1);
    const [reId, btype, createdBy, reType, reAuthor, reLab] = links[0];
    expect(reId, 'the link points at the session the scan reported').toBe(String(scan.re_ID));
    expect(btype, 'ci_receiving_batches.btype').toBe('tox');
    expect(createdBy, 'ci_receiving_batches.created_by is the accessioner').toBe(uid);
    expect(reType, 'ci_receiving.re_type=0 for a new session').toBe('0');
    expect(reAuthor, 'ci_receiving.re_author_id').toBe(uid);
    expect(reLab, 'ci_receiving.re_lab_ID').toBe(tenant.labId);

    console.log(
      `S-ACC-01 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) ` +
        `mid=${mid} assen=${assen} re_ID=${scan.re_ID} pos=${position}`,
    );
  });

  /**
   * S-ACC-01u -- S-ACC-01 AS THE ACCESSIONER SEES IT. D382: the HTTP test above
   * is KEPT, and it is not redundant. It is the only one of the pair that can
   * assert `created_receiving` -- the JSON flag saying a NEW session was opened
   * -- because a browser gets markup back, not the envelope; and it posts a
   * payload the bench cannot produce (explicit `lab_id`, empty tracking number,
   * no `next_positions`).
   *
   * Every S-ACC-01 database assertion is repeated here on this test's own
   * specimen. Two are re-expressed rather than dropped: `scan.re_ID` has no
   * browser equivalent, so the link row is checked against the session id the
   * BENCH is showing instead, and `re_type=0` is asserted off the same join.
   */
  e2e('S-ACC-01u the accessioner scans a requisitioned specimen and sees it logged in', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }, testInfo) => {
    const page = authedPage;
    const f = infra(db);
    const uid = db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(tenant.login)}`);
    const patientLast = `Accrcv${runKey}U`;

    const seeded = await seedRequisition(patientLast);
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    // PRECONDITION BEFORE THE SCAN: the specimen is ordered but not yet here.
    expect(
      db.scalar(`SELECT batch_status FROM ci_batches WHERE assen = ${Db.q(assen)}`),
      'the specimen has not been received yet',
    ).not.toBe('received');

    // The session the accessioner has open BEFORE they scan, and the highest
    // session id that existed before this test touched the bench. One of these
    // two is what the link row is checked against below; which one depends on
    // whether the bench had a session on it at all. Read now, because after the
    // scan there is no way to tell the two apart from the screen.
    const reHigh = db.scalar('SELECT COALESCE(MAX(re_ID),0) FROM ci_receiving');
    const { session: opened, laneForm } = await openBench(page, runKey, 'urine');
    const openedId = await opened.locator('input[data-fname="re_ID"]').inputValue();

    await scan(laneForm, assen);
    await expect(benchRow(page, assen), 'the scan puts the specimen on the bench')
      .toHaveCount(1, { timeout: 15_000 });

    // THE READ BACK, off a SERVER render (D386).
    const { row, session } = await reloadBench(page, assen);
    await expect(assenCell(row), 'the bench lists the specimen under its accession')
      .toHaveText(assen);
    // The contrast with S-ACC-02u, which asserts this cell is EMPTY: a
    // requisitioned specimen arrives carrying the patient it was ordered for,
    // and that is what tells the accessioner the order found the barcode.
    await expect(patientCell(row), 'the bench names the patient the specimen was ordered for')
      .toHaveText(`${patientLast}, Synthetic`);
    await expect(row.getByText(SESSION_PRACTICE, { exact: true }),
      'the ordering office came along with the requisition').toBeVisible();
    await expect(sessionLab(session).locator('option:checked'),
      "the specimen is filed under the accessioner's own lab").toHaveText(tenant.prefix);

    // THE DATABASE -- every S-ACC-01 assertion. One SELECT, exact match.
    const batch = db.query(
      `SELECT batch_status, spec_received, position, lab, lab_id
         FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    );
    expect(batch.length, `exactly one ci_batches row for ${assen}`).toBe(1);
    const [batchStatus, specReceived, position, lab, labId] = batch[0];
    expect(batchStatus, 'ci_batches.batch_status after receiving').toBe('received');
    expect(specReceived, 'ci_batches.spec_received').toMatch(DATETIME);
    expect(Number(position), 'the specimen took a lane position').toBeGreaterThan(0);
    // TENANCY, asserted -- receiving must not move the specimen out of its lab.
    expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
    expect(lab, 'ci_batches.lab').toBe(tenant.prefix);

    // RENDERED against STORED: the bench prints the position it has on file.
    await expect(positionInput(row), 'the rack position the operator reads is the one on file')
      .toHaveValue(position);

    // The session side: one link row, joined to the session it belongs to.
    const links = db.query(
      `SELECT rb.re_ID, rb.btype, rb.created_by, re.re_type, re.re_author_id, re.re_lab_ID
         FROM ci_receiving_batches rb
         JOIN ci_receiving re ON re.re_ID = rb.re_ID
        WHERE rb.mid = ${Db.q(mid)}`,
    );
    expect(links.length, `exactly one ci_receiving_batches row for mid ${mid}`).toBe(1);
    const [reId, btype, createdBy, reType, reAuthor, reLab] = links[0];
    expect(btype, 'ci_receiving_batches.btype').toBe('tox');
    expect(createdBy, 'ci_receiving_batches.created_by is the accessioner').toBe(uid);
    expect(reType, 'ci_receiving.re_type=0 for a bench session').toBe('0');
    expect(reAuthor, 'ci_receiving.re_author_id').toBe(uid);
    expect(reLab, 'ci_receiving.re_lab_ID').toBe(tenant.labId);
    // The browser twin of the HTTP test's `expect(reId).toBe(scan.re_ID)`, and
    // it is asserted against the session captured BEFORE the scan.
    //
    // WHY NOT AGAINST THE RELOADED SCREEN. The obvious form --
    // `expect(session.locator('input[data-fname="re_ID"]')).toHaveValue(reId)`
    // -- cannot fail. The bench groups specimens by the same join the DB read
    // uses: `admin_ci_receiving_form.php:249` lists a session's specimens with
    // `JOIN ci_receiving_batches rb ... where rb.re_ID = '{$re_ID}'`, and `:269`
    // / `:322` emit `tr.parent_c[data-p="batch_$i"]` and `tr.child[data-id=$i]`
    // on one loop index. So a `tr.childp` row can only ever render under the
    // `tr.parent_c` whose re_ID equals its own -- the assertion compared reId to
    // itself. The failure it named (a second session opened behind the
    // operator's back) is exactly the one it stayed green for: a fresh session
    // renders as a new `tr.parent_c`, reloadBench resolves to it, and
    // `reId === reId` still holds. Measured: S-ACC-02u's scan DOES open a
    // second session on this bench, and every u-test passed regardless.
    if (openedId !== '') {
      expect(reId, 'the specimen is filed under the session the accessioner had open')
        .toBe(openedId);
    } else {
      // The bench was empty, so it rendered the blank pseudo-session
      // (admin_ci_receiving_form.php:123-142, no re_ID key -> value ''). The
      // claim is then that the scan opened a NEW session rather than filing the
      // specimen into one that was already on file.
      expect(Number(reId), 'the scan opened a session of its own for the specimen')
        .toBeGreaterThan(Number(reHigh));
    }

    console.log(
      `S-ACC-01u worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) ` +
        `mid=${mid} assen=${assen} re_ID=${reId} onScreenBeforeScan=${openedId || '(new bench)'} pos=${position}`,
    );
  });
});

e2e.describe('S-ACC-02 @full @acc', () => {
  e2e('an unknown barcode still enters the lab as a stub specimen', async ({
    tenantRequest, db, tenant, runKey,
  }) => {
    const today = db.scalar('SELECT CURDATE()');
    // <=12 chars: ci_search_barcode_receiving.php:69 rejects anything longer
    // than barcode_length+5, and barcode_length is 7 here. The lab prefix keeps
    // it out of the all-digits branch at :236 that re-prefixes the code.
    const code = `${tenant.prefix}${runKey.slice(-7)}U`.toUpperCase();
    expect(code.length, 'barcode fits the app length gate').toBeLessThanOrEqual(12);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_batches WHERE assen = ${Db.q(code)}`),
      'the unknown-barcode precondition: no such specimen yet',
    ).toBe('0');

    // The `jvhl` lane, whose ci_receiving_positions row has subtype 'jvhl' and
    // specimen 'Urine'. On `urine` the app blanks initials1 because subtype and
    // specimen match case-insensitively (:93-95), so the catalog's "initials1
    // set" is only observable on a lane where they differ.
    const scan = await scanBarcode(tenantRequest, tenant, code, { lane: 'jvhl', today });
    expect(scan.status, `bench refused ${code}: ${scan.message ?? '(no message)'}`).toBe('ok');

    const rows = db.query(
      `SELECT mid, batch_type, batch_status, spec_received, type_of_specimen,
              initials1, cli_ID, lab, lab_id
         FROM ci_batches WHERE assen = ${Db.q(code)}`,
    );
    expect(rows.length, `exactly one stub ci_batches row for ${code}`).toBe(1);
    const [mid, batchType, batchStatus, specReceived, specType, initials1, cliId, lab, labId] =
      rows[0];
    expect(Number(mid), 'the stub has a real mid').toBeGreaterThan(0);
    expect(batchType, "batch_type 'M' -- created at the bench, not from a requisition").toBe('M');
    expect(batchStatus, 'ci_batches.batch_status').toBe('received');
    expect(specReceived, 'ci_batches.spec_received').toMatch(DATETIME);
    expect(specType, 'type_of_specimen comes from the lane').toBe('Urine');
    expect(initials1, 'initials1 comes from the lane subtype').toBe('jvhl');
    expect(cliId, 'a bench stub carries no clinic until a requisition completes it').toBe('0');
    expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
    expect(lab, 'ci_batches.lab').toBe(tenant.prefix);
  });

  /**
   * S-ACC-02u -- S-ACC-02 AS THE ACCESSIONER SEES IT. D382: the HTTP test above
   * is KEPT. It is not a dead twin -- it posts a payload no browser can produce
   * (`lab_id` explicitly, `re_tracking_number_barcode: ''` past the `required`
   * gate, and no `next_positions`), so it drives a different arm of the same
   * handler's position arithmetic (ci_search_barcode_receiving.php:207-227).
   *
   * Every database assertion of S-ACC-02 is repeated here against this test's
   * own barcode, and each one that a screen can show gains its rendered twin.
   */
  e2e('S-ACC-02u the accessioner scans an unknown barcode and sees the stub on the bench', async ({
    authedPage, db, tenant, runKey,
  }) => {
    const page = authedPage;
    // Distinct from the HTTP twin's `...U` code: both tests run in this lab.
    const code = `${tenant.prefix}${runKey.slice(-7)}V`.toUpperCase();
    expect(code.length, 'barcode fits the app length gate').toBeLessThanOrEqual(12);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_batches WHERE assen = ${Db.q(code)}`),
      'the unknown-barcode precondition: no such specimen yet',
    ).toBe('0');

    const { laneForm } = await openBench(page, runKey, 'jvhl');
    await scan(laneForm, code);

    // IMMEDIATE FEEDBACK ONLY (D386). The bench answers a scan by putting the
    // specimen on the list; everything load-bearing is read back below.
    await expect(benchRow(page, code), 'the scan puts the specimen on the bench')
      .toHaveCount(1, { timeout: 15_000 });
    // The exact negative of S-ACC-05u, on the same locator: a barcode nobody
    // has scanned is not reported as one that has been.
    await expect(laneForm.getByText('Duplicate barcode', { exact: true }),
      'a barcode the bench has never seen is not refused as a duplicate').toHaveCount(0);

    // THE READ BACK, off a SERVER render (D386).
    const { row, session } = await reloadBench(page, code);
    await expect(assenCell(row), 'the bench lists the specimen under the barcode that was scanned')
      .toHaveText(code);
    await expect(patientCell(row),
      'a bench stub shows the accessioner NO patient -- nothing ordered it yet')
      .toHaveText('');
    await expect(row.getByText('jvhl', { exact: true }),
      'the bench labels the specimen with the lane it came in on')
      .toBeVisible();
    await expect(sessionLab(session).locator('option:checked'),
      "the specimen is filed under the accessioner's own lab")
      .toHaveText(tenant.prefix);
    // D161's tripwire, stated as something the operator can see. When
    // CI_Base_Lab::get_user_lab_ids() falls through its empty branch the
    // persona silently widens to EVERY active lab, and the only outward sign is
    // this picker growing from {Select Lab, LAE} to sixteen entries. That
    // failure mode passed green once already because more data looks like more
    // coverage.
    await expect(sessionLab(session).getByRole('option'),
      'the bench offers this accessioner exactly one lab to receive into')
      .toHaveCount(2);

    // THE DATABASE -- every S-ACC-02 assertion, verbatim.
    const rows = db.query(
      `SELECT mid, batch_type, batch_status, spec_received, type_of_specimen,
              initials1, cli_ID, lab, lab_id, position
         FROM ci_batches WHERE assen = ${Db.q(code)}`,
    );
    expect(rows.length, `exactly one stub ci_batches row for ${code}`).toBe(1);
    const [mid, batchType, batchStatus, specReceived, specType, initials1, cliId, lab, labId,
      position] = rows[0];
    expect(Number(mid), 'the stub has a real mid').toBeGreaterThan(0);
    expect(batchType, "batch_type 'M' -- created at the bench, not from a requisition").toBe('M');
    expect(batchStatus, 'ci_batches.batch_status').toBe('received');
    expect(specReceived, 'ci_batches.spec_received').toMatch(DATETIME);
    // NO RENDERED TWIN, and this is the reason. The XHR fragment offers the
    // Urine/Oral radios from tr_pdd_get_type_of_specimens() (form_define.php:625),
    // but the RELOAD builds its radios from `ci_test_specimens`
    // (admin_ci_receiving_form.php:78-90), which holds pcr rows only -- so the
    // control the operator just used is gone from the re-rendered bench and the
    // stored specimen type is invisible on every reachable screen.
    expect(specType, 'type_of_specimen comes from the lane').toBe('Urine');
    expect(initials1, 'initials1 comes from the lane subtype').toBe('jvhl');
    expect(cliId, 'a bench stub carries no clinic until a requisition completes it').toBe('0');
    expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
    expect(lab, 'ci_batches.lab').toBe(tenant.prefix);

    // RENDERED against STORED, not rendered against itself: the bench prints
    // the position it has on file, so a dropped position write reads back here.
    expect(Number(position), 'the stub took a lane position').toBeGreaterThan(0);
    await expect(positionInput(row), "the rack position the operator reads is the one on file")
      .toHaveValue(position);
  });
});

e2e.describe('S-ACC-05 @full @acc', () => {
  // The catalog pairs two refusals under one id. Only the duplicate scan is
  // implemented: the other is the `receiving_w3dnum` age gate, and it is dead
  // on this instance -- ci_pdf_data.receiving_w3d is false, so
  // ci_search_barcode_receiving.php:333 never evaluates. Turning it on is a
  // global option write, which belongs in serial.spec.ts, not here.
  e2e('rescanning a specimen in the same session is refused and changes nothing', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }) => {
    const f = infra(db);
    const today = db.scalar('SELECT CURDATE()');
    const patientLast = `Accdup${runKey}B`;

    const seeded = await seedRequisition(patientLast);
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    const first = await scanBarcode(tenantRequest, tenant, assen, { lane: 'urine', today });
    expect(first.status, `first scan refused: ${first.message ?? '(no message)'}`).toBe('ok');

    const before = db.query(
      `SELECT batch_status, spec_received FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    )[0];
    const linkId = db.scalar(
      `SELECT ID FROM ci_receiving_batches WHERE mid = ${Db.q(mid)} AND btype = 'tox'`,
    );
    expect(Number(linkId), 'the first scan linked the specimen').toBeGreaterThan(0);

    // Same session -- re_ID carried forward, exactly as admin_receiving.js:365 does.
    const second = await scanBarcode(tenantRequest, tenant, assen, {
      lane: 'urine', reId: first.re_ID, today,
    });
    expect(second.status, 'the bench refuses the second scan').toBe('error');
    expect(second.message, 'refusal reason').toBe('This Barcode is duplicate');
    expect(String(second.existid), 'the refusal names the existing link row').toBe(linkId);
    expect(second.created_receiving, 'no session was opened by the refused scan').toBe(false);

    // The record is untouched: no second link, same status, same receive time.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_receiving_batches WHERE mid = ${Db.q(mid)}`),
      'still exactly one ci_receiving_batches row for the (mid, session) pair',
    ).toBe('1');
    const after = db.query(
      `SELECT batch_status, spec_received FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    )[0];
    expect(after[0], 'batch_status unchanged by the refused scan').toBe(before[0]);
    expect(after[1], 'spec_received unchanged by the refused scan').toBe(before[1]);
    expect(before[1], 'spec_received was a real timestamp to begin with').toMatch(DATETIME);
  });

  /**
   * S-ACC-05u -- THE REFUSAL A USER CAN ACTUALLY REACH, and a sentinel on the
   * plan's own exclusion claim.
   *
   * The HTTP test above stays VERBATIM and is not duplicated here, because the
   * two tests assert different refusals. The server's *"This Barcode is
   * duplicate"* (ci_search_barcode_receiving.php:318-326) sits behind a
   * client-side guard: admin_receiving.js:376-395 searches the WHOLE
   * `#rec_tbl_add` table for `.assen_<code>` and returns before any request is
   * sent, so no bench path reaches the server branch. What the accessioner
   * meets instead is an inline *"Duplicate barcode"* in the lane's warning
   * area, and nothing else changes.
   *
   * THE DIALOG ASSERTION IS THE POINT. If the client guard is ever removed or
   * its selector narrowed, the scan reaches the server, the server answers
   * `status != 'ok'`, and admin_receiving.js:503-510 raises an `alert()`.
   * Collecting dialogs and asserting the list is empty is what turns "the
   * server branch is unreachable" from a comment into a test.
   *
   * TWO CORRECTIONS, measured 2026-08-26.
   *
   * (1) "No bench path reaches the server branch" is true only of a bench whose
   * table already holds the row. The guard reads THIS page's DOM, so a second
   * workstation -- or this one, left open since before the tube was logged in
   * -- sails past it and meets the server. S-ACC-05v below is that path, and it
   * is where the server's refusal is asserted as a screen and not as JSON.
   *
   * (2) NO SINGLE-CLAUSE MUTATION OF THE CLIENT GUARD IS KILLABLE BY ANY TEST,
   * here or anywhere, because the guard is written twice inside one function:
   * admin_receiving.js:376-380 is the `.assen_<code>` selector and :382-393 is
   * a hand-rolled `.childp` loop applying the identical rule and setting the
   * identical warning. Delete either clause and the other still refuses the
   * scan. That is a mutation EQUIVALENCE in the app, not a gap in this test --
   * only deleting BOTH is observable, and this test kills that.
   */
  e2e('S-ACC-05u rescanning at the bench is refused inline and changes nothing', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }) => {
    const page = authedPage;
    const f = infra(db);
    const patientLast = `Accdup${runKey}U`;

    const seeded = await seedRequisition(patientLast);
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    const dialogs: string[] = [];
    page.on('dialog', async (d) => { dialogs.push(d.message()); await d.dismiss(); });

    const { laneForm } = await openBench(page, runKey, 'urine');
    await scan(laneForm, assen);
    await expect(benchRow(page, assen), 'the first scan puts the specimen on the bench')
      .toHaveCount(1, { timeout: 15_000 });

    const before = db.query(
      `SELECT batch_status, spec_received FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    )[0];
    expect(before[1], 'spec_received was a real timestamp to begin with').toMatch(DATETIME);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_receiving_batches WHERE mid = ${Db.q(mid)}`),
      'the first scan linked the specimen exactly once',
    ).toBe('1');

    // The same barcode, the same open session -- an operator scanning the tube
    // twice, which is the whole scenario.
    await scan(laneForm, assen);

    await expect(laneForm.getByText('Duplicate barcode', { exact: true }),
      'the bench tells the accessioner, in the lane they are scanning into, that the tube is already on it')
      .toBeVisible();
    await expect(benchRow(page, assen), 'the refused rescan adds no second line to the bench')
      .toHaveCount(1);
    expect(dialogs, 'the refusal is inline: no alert(), so the scan never reached the server')
      .toEqual([]);

    // THE READ BACK, off a SERVER render (D386): still one specimen, one line.
    const { row } = await reloadBench(page, assen);
    await expect(assenCell(row), 'the reloaded bench still lists the specimen once')
      .toHaveText(assen);

    // The record is untouched: no second link, same status, same receive time.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_receiving_batches WHERE mid = ${Db.q(mid)}`),
      'still exactly one ci_receiving_batches row for the (mid, session) pair',
    ).toBe('1');
    const after = db.query(
      `SELECT batch_status, spec_received FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    )[0];
    expect(after[0], 'batch_status unchanged by the refused scan').toBe(before[0]);
    expect(after[1], 'spec_received unchanged by the refused scan').toBe(before[1]);
  });

  /**
   * S-ACC-05v -- THE SERVER'S OWN REFUSAL, ON A SCREEN THAT ACTUALLY MEETS IT.
   *
   * S-ACC-05 proves the server branch refuses. S-ACC-05u proves the bench never
   * gets there. Neither says what the accessioner who DOES reach it sees, and
   * one of them does -- because the bench's guard is
   * `$('#rec_tbl_add .assen_' + code)` (admin_receiving.js:376-395), i.e. the
   * rows in THIS browser's own table and nothing else. A second workstation, or
   * the same one left open since before the tube was logged in, holds a table
   * that has never seen the row. Its scan goes out, the server answers
   * `status:'error'` with `This Barcode is duplicate`
   * (ci_search_barcode_receiving.php:318-336), and admin_receiving.js:502-510
   * beeps and raises an `alert()` carrying the server's own words.
   *
   * THE FIRST SCAN IS POSTED OVER HTTP ON PURPOSE. It is the OTHER workstation,
   * and it is what leaves this browser's table stale. Doing it through this
   * page instead would just re-run S-ACC-05u and never reach the server.
   *
   * WHY IT IS NOT A DUPLICATE OF S-ACC-05. That test asserts the JSON envelope;
   * this one asserts what the person holding the second tube is told, and that
   * their bench gains nothing. Neutering the server guard leaves S-ACC-05u
   * green -- the bench refuses it locally -- and turns this one red twice over:
   * no alert, and a second custody link.
   */
  e2e('S-ACC-05v a bench that never saw the first scan is refused by the server', async ({
    seedRequisition, tenantRequest, authedPage, db, tenant, runKey,
  }) => {
    const page = authedPage;
    const f = infra(db);
    const today = db.scalar('SELECT CURDATE()');
    const patientLast = `Accdup${runKey}V`;

    const seeded = await seedRequisition(patientLast);
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    const dialogs: string[] = [];
    page.on('dialog', async (d) => { dialogs.push(d.message()); await d.dismiss(); });

    // This workstation opens the bench while the tube is still unscanned...
    const { laneForm } = await openBench(page, runKey, 'urine');
    await expect(benchRow(page, assen), 'the specimen is not on this bench yet')
      .toHaveCount(0);

    // ...and the OTHER workstation logs it in, out of this browser's sight.
    const first = await scanBarcode(tenantRequest, tenant, assen, { lane: 'urine', today });
    expect(first.status,
      `the other workstation's scan was refused: ${first.message ?? '(no message)'}`).toBe('ok');
    const before = db.query(
      `SELECT batch_status, spec_received FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    )[0];
    expect(before[1], 'spec_received was a real timestamp to begin with').toMatch(DATETIME);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_receiving_batches WHERE mid = ${Db.q(mid)}`),
      'the other workstation linked the specimen exactly once',
    ).toBe('1');

    // The operator here scans the same tube. Their table holds no row for it,
    // so the bench passes the scan through and the SERVER is what answers.
    await scan(laneForm, assen);

    await expect.poll(() => dialogs,
      { message: 'the bench tells this accessioner, in the server\'s own words, that the tube '
        + 'is already logged in somewhere else' })
      .toEqual(['This Barcode is duplicate']);
    await expect(benchRow(page, assen), 'the refused scan puts no line on this bench')
      .toHaveCount(0);

    // THE READ BACK, off a SERVER render (D386): the specimen is on the bench
    // ONCE -- the other workstation's line, and no second one beside it.
    const { row } = await reloadBench(page, assen);
    await expect(assenCell(row), 'the reloaded bench lists the specimen once')
      .toHaveText(assen);

    // The record is untouched: no second custody link, same status, same time.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_receiving_batches WHERE mid = ${Db.q(mid)}`),
      'still exactly one ci_receiving_batches row -- the refused scan opened no second '
      + 'custody link for the same specimen',
    ).toBe('1');
    const after = db.query(
      `SELECT batch_status, spec_received FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    )[0];
    expect(after[0], 'batch_status unchanged by the refused scan').toBe(before[0]);
    expect(after[1], 'spec_received unchanged by the refused scan').toBe(before[1]);
  });
});

e2e.describe('S-ACC-06 @full @acc', () => {
  e2e('the receive hop is attributable to a named actor in the custody chain', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }) => {
    const f = infra(db);
    const today = db.scalar('SELECT CURDATE()');
    const uid = db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(tenant.login)}`);
    const patientLast = `Acccus${runKey}C`;

    const seeded = await seedRequisition(patientLast);
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    // Watermark, so the session-scoped log row can be matched exactly rather
    // than by a LIKE that another test's row would satisfy.
    const logHighWater = db.scalar('SELECT COALESCE(MAX(id),0) FROM ci_log_users');

    const scan = await scanBarcode(tenantRequest, tenant, assen, { lane: 'urine', today });
    expect(scan.status, `bench refused ${assen}: ${scan.message ?? '(no message)'}`).toBe('ok');

    // Stream 1: the session was opened by this actor.
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_log_users
          WHERE id > ${Db.q(logHighWater)} AND user_id = ${Db.q(uid)}
            AND user_action = 'receiving_add'`,
      ),
      'exactly one receiving_add row for the session this actor opened',
    ).toBe('1');

    // Stream 2: the specimen hop, keyed on mid and the accession itself.
    // trpdd_log_user() puts the accession in `data`, not in the `assen` column
    // (ci_search_barcode_receiving.php:537).
    const hop = db.query(
      `SELECT user_id, btype, data FROM ci_log_users
        WHERE mid = ${Db.q(mid)} AND user_action = 'receiving'`,
    );
    expect(hop.length, `exactly one receiving log row for mid ${mid}`).toBe(1);
    expect(hop[0][0], 'ci_log_users.user_id is the accessioner').toBe(uid);
    expect(hop[0][1], 'ci_log_users.btype').toBe('tox');
    expect(hop[0][2], 'ci_log_users.data carries the accession').toBe(assen);

    // The reader assembles it into a named, timed hop.
    const body = await (
      await tenantRequest.get(`/wp-admin/admin.php?page=ci_custody_chain&assen=${assen}`)
    ).text();
    expect(/Sorry, you are not allowed/.test(body), 'persona reaches the custody chain').toBe(false);
    expect(body, 'the chain names the specimen').toContain(assen);
    expect(body, 'the receive hop is attributed').toContain('Received by');
    expect(body, 'attributed to THIS accessioner by name').toContain(`E2E Lead Sec ${tenant.prefix}`);
  });

  /**
   * S-ACC-06u -- THE CUSTODY CHAIN AS ITS READER SEES IT, BEFORE AND AFTER.
   *
   * The chain is searched TWICE on purpose. A single after-the-fact read cannot
   * distinguish "the app recorded this hop" from "the app prints a Received-by
   * line for every specimen it can find a session for": both would pass. The
   * before/after pair is what carries the information -- the same query on the
   * same accession reads *Not Received* until the specimen is scanned and the
   * accessioner's name afterwards.
   *
   * D382: the HTTP test above is KEPT and is not weakened here. It owns the
   * `receiving_add` assertion, which the browser CANNOT make: `scanBarcode`
   * posts an empty `parentdata[re_ID]` and so opens a fresh session on every
   * call (ci_search_barcode_receiving.php:121), while the bench posts the
   * session id already on screen and joins the open one -- so no
   * `receiving_add` row is written when a session for today already exists.
   *
   * The chain reads a DIFFERENT stream from the assertions below it: the hop
   * is rendered from `ci_receiving_batches.created_by`/`.created`
   * (functions_custody_chain.php:56-123), while `ci_log_users` is a parallel
   * audit trail. Both are asserted; neither substitutes for the other.
   */
  e2e('S-ACC-06u the custody chain reader sees the receive hop appear, named and timed', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }) => {
    const page = authedPage;
    const f = infra(db);
    const uid = db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(tenant.login)}`);
    const accessioner = `E2E Lead Sec ${tenant.prefix}`;
    const patientLast = `Acccus${runKey}U`;

    const seeded = await seedRequisition(patientLast);
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    // BEFORE. The chain already knows the specimen was ordered, and says in as
    // many words that nobody has taken custody of it.
    await searchCustodyChain(page, assen);
    await expect(page.getByRole('heading', { name: assen, exact: false }),
      'the chain reader finds the specimen by accession').toBeVisible();
    await expect(page.getByText('Not Received', { exact: true }),
      'before anyone scans it, the chain says the specimen has not been received').toBeVisible();
    await expect(page.getByText(`Received by ${accessioner}`),
      'and it attributes no receive hop to anybody').toHaveCount(0);

    const { laneForm } = await openBench(page, runKey, 'urine');
    await scan(laneForm, assen);
    await expect(benchRow(page, assen), 'the scan puts the specimen on the bench')
      .toHaveCount(1, { timeout: 15_000 });

    // The hop, as the app stored it. The chain is rendered from these two
    // columns, so they are what the rendered line is checked against.
    const hopRow = db.query(
      `SELECT created_by, created FROM ci_receiving_batches
        WHERE mid = ${Db.q(mid)} AND btype = 'tox'`,
    );
    expect(hopRow.length, `exactly one ci_receiving_batches row for mid ${mid}`).toBe(1);
    expect(hopRow[0][0], 'ci_receiving_batches.created_by is the accessioner').toBe(uid);
    expect(hopRow[0][1], 'ci_receiving_batches.created is a real timestamp').toMatch(DATETIME);

    // AFTER, off a fresh SERVER render (D386) -- a second GET of the reader,
    // not a re-read of the page the scan left behind.
    await searchCustodyChain(page, assen);
    await expect(page.getByText('Not Received', { exact: true }),
      'the chain no longer says the specimen is unreceived').toHaveCount(0);
    await expect(
      page.getByText(`Received by ${accessioner} at ${displayDatetime(hopRow[0][1])}`),
      'the chain names the accessioner AND the moment they took custody, to the second',
    ).toBeVisible();

    // THE DATABASE -- S-ACC-06's audit-trail assertions on the specimen hop.
    // `receiving_add` stays on the HTTP twin; see the header comment.
    const hop = db.query(
      `SELECT user_id, btype, data FROM ci_log_users
        WHERE mid = ${Db.q(mid)} AND user_action = 'receiving'`,
    );
    expect(hop.length, `exactly one receiving log row for mid ${mid}`).toBe(1);
    expect(hop[0][0], 'ci_log_users.user_id is the accessioner').toBe(uid);
    expect(hop[0][1], 'ci_log_users.btype').toBe('tox');
    expect(hop[0][2], 'ci_log_users.data carries the accession').toBe(assen);
  });
});

e2e.describe('custody-chain page gate @full @acc', () => {
  // D105/D185: page-layer denials ARE enforced but answer HTTP 200 with a
  // denial body, so the assertion is on the rendered outcome, never the status.
  //
  // WAVE 2 / D388. This used to be a pair of regexes over raw HTML fetched by an
  // APIRequestContext. The plan supersedes that layer for the page gates, and
  // nothing weaker is lost: the browser issues the SAME GET against the SAME
  // handler with the SAME cookies, so no D382 twin is retained.
  //
  // The navigation comes FIRST. `pageAs()` hands back an about:blank page, and
  // a count assertion made there is true for ANY product behaviour.
  //
  // D250 -- menu registration is the ONLY gate on this screen, so the offer and
  // the URL are two separate facts and both are asserted.
  //
  // This is the negative control on the catalog's persona claim for S-ACC-06,
  // which says leadlabsecman is "the only receiving role holding
  // ci_custody_chain". Live, that is FALSE: lab_sec (29 caps) and labsecman
  // (24 caps) both hold it. `e2e_sec` is the lab_sec persona, and it is both
  // OFFERED the screen and able to open it. The day someone narrows the
  // capability, this test moves and the catalog's claim becomes true.
  //
  // Measured on this stack 2026-08-25: e2e_sec -- Custody Chain menu link 1
  // (top-level, form_define.php:893), heading and Search control visible,
  // denial notice 0.
  e2e('lab_sec is offered Custody Chain and reaches admin.php?page=ci_custody_chain', async ({ pageAs }) => {
    const page = await pageAs('e2e_sec');

    await page.goto('/wp-admin/');
    await expect(
      page.locator('#adminmenu').getByRole('link', { name: 'Custody Chain' }),
      'the menu link IS the gate (D250) -- lab_sec is offered the custody chain',
    ).toHaveCount(1);

    await page.goto('/wp-admin/admin.php?page=ci_custody_chain');
    await expect(
      page.getByText('Sorry, you are not allowed to access this page.'),
      'lab_sec is not stopped by the core denial notice',
    ).toHaveCount(0);
    await expect(
      page.getByRole('heading', { name: 'Custody Chain' }),
      'the custody chain screen itself is on the reader screen',
    ).toBeVisible();
    // The rendered twin of the old /name="assen"/ regex: the accession search
    // is what makes the screen usable, and S-ACC-06 drives exactly this form.
    await expect(
      page.getByRole('button', { name: 'Search' }),
      'the accession search the reader came for is offered',
    ).toBeVisible();
  });
});

e2e.describe('S-ORD-02 @full @ord @acc', () => {
  e2e('a second specimen on an already-used accession is suffixed, not collided', async ({
    seedRequisition, db, tenant, runKey,
  }) => {
    const f = infra(db);

    // The precondition is a COMPLETED row holding the base accession. A
    // non-completed row instead routes the second save into the stub-completion
    // branch (ci_save_form_requisition.php:74-85, `batch_status!='completed'`),
    // which UPDATES the first row and never reaches the -01 minting at :247.
    // Measured both ways; S-ORD-03 below is that other branch.
    const baseLast = `Orddup${runKey}D`;
    const base = await seedRequisition(baseLast, { 'bat[batch_status]': 'completed' });
    expect(base.status, 'the base requisition saves').toBe(200);

    // expectBatch with a NON-DEFAULT batch_status (D221). The status used to be
    // hard-coded to '', so this row -- which is deliberately seeded `completed`
    // -- had to be asserted by hand, less thoroughly than the shared helper
    // does it. Same assertions as the rest of the file now: rows gained on this
    // key, exactly one row, accession non-empty AND unique, batch_type, panels,
    // and tenancy on lab_id/lab/prefix.
    const { assen: baseAssen, mid: baseMid } = expectBatch(db, base, tenant, {
      testGroupId: f.testGroupId,
      batchStatus: 'completed',
    });

    const dupLast = `Orddup${runKey}E`;
    const dup = await seedRequisition(dupLast, { 'bat[assen]': baseAssen });
    expect(dup.status, 'the duplicate requisition saves').toBe(200);
    const { assen: dupAssen, mid: dupMid } = expectBatch(db, dup, tenant, {
      testGroupId: f.testGroupId,
    });

    expect(dupAssen, 'the second specimen is suffixed, not collided').toBe(`${baseAssen}-01`);
    expect(dupMid, 'a distinct batch was created').not.toBe(baseMid);
    // The base row is left alone -- a collision would have overwritten it.
    const baseAfter = db.query(
      `SELECT assen, batch_status, patient_name FROM ci_batches WHERE mid = ${Db.q(baseMid)}`,
    )[0];
    expect(baseAfter[0], 'base accession unchanged').toBe(baseAssen);
    expect(baseAfter[1], 'base status unchanged').toBe('completed');
    expect(baseAfter[2], 'base patient unchanged').toBe(`${baseLast}, Synthetic`);
  });
});

e2e.describe('S-ORD-03 @full @ord @acc', () => {
  e2e('a later form save completes the receiving stub instead of creating a second batch', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }) => {
    const f = infra(db);
    const today = db.scalar('SELECT CURDATE()');
    const code = `${tenant.prefix}${runKey.slice(-7)}S`.toUpperCase();
    expect(code.length, 'barcode fits the app length gate').toBeLessThanOrEqual(12);

    // Step 1 -- barcode receiving first. The bench mints the stub.
    const scan = await scanBarcode(tenantRequest, tenant, code, { lane: 'urine', today });
    expect(scan.status, `bench refused ${code}: ${scan.message ?? '(no message)'}`).toBe('ok');

    const stub = db.query(
      `SELECT mid, batch_status, cli_ID, patient_name FROM ci_batches WHERE assen = ${Db.q(code)}`,
    );
    expect(stub.length, `exactly one stub for ${code}`).toBe(1);
    const [stubMid, stubStatus, stubCli, stubPatient] = stub[0];
    expect(stubStatus, 'the stub is received').toBe('received');
    expect(stubCli, 'the stub has no clinic yet').toBe('0');
    expect(stubPatient, 'the stub has no patient yet').toBe('');

    // Step 2 -- the form save arrives carrying the same accession.
    const formLast = `Ordstb${runKey}F`;
    const seeded = await seedRequisition(formLast, { 'bat[assen]': code });
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);

    // The catalog's assertion: still exactly one row on that accession, and the
    // mid did not move. Exact matches -- no LIKE, which another row would satisfy.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_batches WHERE assen = ${Db.q(code)}`),
      'the accession still resolves to exactly one batch',
    ).toBe('1');
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_batches WHERE assen = ${Db.q(`${code}-01`)}`),
      'no -01 sibling was minted: this is completion, not duplication',
    ).toBe('0');

    const after = db.query(
      `SELECT mid, batch_status, cli_ID, patient_name, panel_tests_requested, lab, lab_id
         FROM ci_batches WHERE assen = ${Db.q(code)}`,
    )[0];
    expect(after[0], 'the stub mid is unchanged').toBe(stubMid);
    expect(after[1], 'the bench receive survives the form save').toBe('received');
    expect(after[2], 'the form save attached the clinic').toBe(f.clinicId);
    expect(after[3], 'the form save attached the patient').toBe(`${formLast}, Synthetic`);
    expect(after[4], 'the ordered panel landed on the stub').toContain(`group_${f.testGroupId}`);
    expect(after[6], 'ci_batches.lab_id').toBe(tenant.labId);
    expect(after[5], 'ci_batches.lab').toBe(tenant.prefix);
  });
});

// ===========================================================================
// S-ACC-03 / S-ACC-04 CASE B -- the rack bench and the plate, as the tech
// reads them
// ===========================================================================

/**
 * WHY THESE TWO LIVE HERE AND WHAT THEY ADD.
 *
 * The HTTP tests for both scenarios are in `tst.spec.ts` (they were filed with
 * the pcr bench role, whose persona is `e2e_tech`). D382: neither is touched,
 * and neither is redundant -- each drives an arm of the handler the screen
 * cannot reach. What is ADDED here is the half a user can actually reach.
 *
 * WHY ONLY CASE B. **DWA-E14 -- no rack can be created through the screen.**
 * When today's bench holds no rack, `parts/rack/main.php:289-294` synthesises a
 * row with no `re_ID`, and `:372` renders `<input type="hidden"
 * data-fname="re_ID" value="">`. `js/admin_racks.js:481-484` copies every
 * `data-fname` straight into `parentdata`, so the first scan posts
 * `parentdata[re_ID]=` (the empty string), and
 * `parts/rack/action_ci_search_barcode_rack.php:36` branches on `$re_ID == 0`
 * -- which on PHP 8.2 is FALSE for `''`. The create branch never runs, the
 * action answers `{"status":"error"}`, and nothing is written. So the rack
 * itself -- case A -- is built over HTTP below with the literal `'0'` the
 * screen never sends, and only the guard that follows it is driven as a user.
 * When DWA-E14 is fixed, the precondition becomes a second UI scan.
 *
 * DWA-E14 IS ITSELF PINNED, at the end of this file, as the tech's own gesture
 * under `e2e.fail()`. The day it is fixed that pin reports an unexpected pass,
 * and these two preconditions can drop their HTTP leg.
 *
 * PERSONA. `e2e_tech` (`lab_tech`), the only minted persona holding `form_pcr`,
 * bound to lab 35 outside the `e2e_lead*` pool -- read live, never hardcoded.
 * The HTTP precondition rides `page.request`, the browser context's OWN
 * request context, so both entry points are literally the same seat and the
 * same cookies; no second context is built.
 *
 * D396 -- WHAT THESE TESTS SHARE WITH THEIR HTTP TWINS. Both screens are
 * global surfaces: `parts/rack/main.php:268-273` selects racks by
 * `re_today='<today>'` with NO lab predicate, and `ci_plates.pl_number` is
 * `MAX(pl_number)+1` over the whole day (`action_add_plate.php:182`), so the
 * twins in `tst.spec.ts` -- same persona, same lab, same day -- put
 * indistinguishable rows beside these. Every assertion below is therefore keyed
 * on this test's OWN `re_ID`, `pl_ID` or accession. Not one is a count over a
 * shared listing, and no expected `pl_number` is written down.
 */

const RACKS_PAGE = '/wp-admin/admin.php?page=ci_receiving&tab=racks';
const PLATE_VIEW_PAGE = '/wp-admin/admin.php?page=ci_tecan_processing&tab=plate_view';

/** The lab `e2e_tech` (role lab_tech) is bound to, read live (D172). */
function techLab(db: Db) {
  const [row] = db.query(
    `SELECT m.meta_value, l.lab_dba_init
       FROM wp_usermeta m
       JOIN wp_users u ON u.ID = m.user_id AND u.user_login = 'e2e_tech'
       JOIN ci_labs l ON l.lab_ID = m.meta_value
      WHERE m.meta_key = '_lab_id'`,
  );
  if (!row) throw new Error('e2e_tech has no _lab_id -- run env/seed.sh');
  return { labId: row[0], prefix: row[1] };
}

/** One pcr requisition at a chosen status, saved by e2e_tech. */
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
  const rows = db.query(
    `SELECT mid, assen, batch_status, lab_id FROM ci_batches_pcr
      WHERE patient_name = ${Db.q(patientName)}`,
  );
  expect(rows.length, `exactly one ci_batches_pcr row for ${patientName}`).toBe(1);
  const [mid, assen, status, labId] = rows[0];
  expect(assen, 'the specimen was given an accession (D160)').not.toBe('');
  expect(status, 'precondition: ci_batches_pcr.batch_status').toBe(batchStatus);
  expect(labId, 'ci_batches_pcr.lab_id is the lab_tech persona lab').toBe(lab.labId);
  return { mid, assen, lab };
}

/**
 * The rack actions are `trajax` POSTs onto the Receiving page's Racks tab
 * (`pages/admin_ci_receiving.php:547-548` -> `parts/rack/main.php:229-232`),
 * the same URL the screen posts to. Used here ONLY for the case-A precondition
 * that DWA-E14 puts out of the screen's reach.
 */
async function rackPost(request: APIRequestContext, form: Record<string, string>) {
  const res = await request.post(RACKS_PAGE, { form: { trajax: '1', ...form } });
  expect(res.status(), `rack action ${form.act} answers 200`).toBe(200);
  const text = await res.text();
  try {
    return JSON.parse(text) as Record<string, unknown>;
  } catch {
    throw new Error(`rack action ${form.act} did not return JSON: ${text.slice(0, 400)}`);
  }
}

const scanIntoRack = (request: APIRequestContext, assen: string, reId: string) =>
  rackPost(request, {
    act: 'ci_search_barcode_rack', code: assen, btype: 'pcr', duplicate: '0',
    new_well: '0', pnid: '0', mixedvp: '',
    'parentdata[re_ID]': reId, 'parentdata[re_panel_id]': '0',
    'parentdata[re_subtitle]': '', 'parentdata[re_priority]': 'low',
  });

/**
 * Open the Racks tab and hand back THIS test's rack and its scan form.
 *
 * `Show All` for the same reason as the receiving bench: `tr.child` is
 * `display:none` until the operator expands it (`parts/rack/main.php:530-532`
 * styles `.show_all tr.child`), so the barcode box is not in the accessibility
 * tree before the click. Measured: 0 before, 1 after.
 *
 * The rack is found by ITS OWN re_ID and never by `.first()`: the listing is
 * every rack laid down today across every lab. `tr.parent_c[data-p="batch_N"]`
 * and `tr.child.p_batch_N` are emitted on one loop index
 * (`parts/rack/main.php:363,437`), so the scan form resolved here belongs to
 * the rack row resolved here.
 */
async function openRack(page: Page, reId: string, date?: string) {
  // `date` is the Racks tab's OWN query parameter: `parts/rack/main.php:29-33`
  // reads `$_REQUEST['date']` and, when it is absent, REDIRECTS to the same URL
  // carrying today's -- measured, the bare RACKS_PAGE lands on `&date=<today>`.
  // So this is the URL the page's own GET form and its datepicker produce, not
  // a shape invented here. Callers on today's bench pass nothing.
  await page.goto(date ? `${RACKS_PAGE}&date=${date}` : RACKS_PAGE);
  await page.getByText('Show All', { exact: true }).click();
  const rackRow = page.locator(`tr.parent_c:has(input[data-fname="re_ID"][value="${reId}"])`);
  await expect(rackRow, `rack ${reId} is on today's rack bench`).toHaveCount(1);
  const laneForm = page.locator(`tr.child.p_${await rackRow.getAttribute('data-p')}`);
  return { rackRow, laneForm };
}

/**
 * A specimen's line on a rack. `tr.childp.assen_<code>` is the app's OWN handle
 * -- `inc/functions_rack_plate.php:2231` writes the class and
 * `js/admin_racks.js:477` reads it back to decide whether a scan is a duplicate
 * -- so this is app wiring, not a test hook (D383 clause 4). `td.assennum_1` is
 * the first barcode slot, keyed the same way by `js/admin_racks.js:127`.
 */
const rackSample = (laneForm: Locator, assen: string) =>
  laneForm.locator(`table.subretbl tr.childp.assen_${assen}`);

/**
 * Ask the plate reader which plate a specimen is on, the way a tech does.
 *
 * `parts/plate/plate_view.php:120-131` is a plain GET form, so every render it
 * produces is a server render. `<label>Accession #:</label>` carries no `for=`
 * and does not wrap the field, so `getByLabel` resolves nothing -- D383
 * fallback 3; `name="assen"` is what the tech's own browser submits.
 */
async function searchPlateView(page: Page, assen: string) {
  await page.goto(PLATE_VIEW_PAGE);
  await page.locator('input[name="assen"]').fill(assen);
  await page.getByRole('button', { name: 'Go' }).click();
  await expect(page.getByRole('heading', { name: 'Plates', exact: true }),
    'the plate reader is on screen').toBeVisible();
}

e2e.describe('S-ACC-03 / S-ACC-04 case B @full @acc @mutates', () => {
  /**
   * S-ACC-03u -- CASE B AS THE TECH PERFORMS IT.
   *
   * The scenario's guard is `action_ci_search_barcode_rack.php:720`: the
   * advance to `racked` is gated on `batch_status` being `received` or empty,
   * so a specimen already at `plated` is racked WITHOUT being moved. Case A --
   * creating the rack and advancing a received specimen -- is the HTTP
   * precondition, for the DWA-E14 reason in the header.
   *
   * WHAT THE RENDERED HALF IS FOR, since `ci_batches_pcr.batch_status` has no
   * screen. Measured: the pcr requisition form's status control is built from
   * `tr_pdd_get_batch_statuses(...,'pcr')` (`parts/pcr/req_form.php:1634`),
   * and `ci_batches_status` holds five pcr rows -- Assigned, Collected, Not
   * Collected, Refused, Stopped. Neither `plated` nor `racked` is among them,
   * so neither value can render as a selected option, and no pcr listing prints
   * the column. The DB assertion below is therefore the ONLY evidence of the
   * guard, and it is kept in full.
   *
   * What the screen DOES carry is the other half of case B, and it is the half
   * that would break first: the guard must leave the scan itself ACCEPTED. The
   * obvious "hardening" of `:720` -- refuse a specimen that is already plated
   * rather than silently not moving it -- makes the action answer
   * `status != 'ok'`, `js/admin_racks.js:117-121` raise an `alert()`, and the
   * tube never reach the rack. Every rendered assertion below goes red on that
   * change and no database assertion does.
   */
  e2e('S-ACC-03u the tech racks an already-plated specimen and it is placed, not downgraded', async ({
    pageAs, db, runKey,
  }, testInfo) => {
    const page = await pageAs('e2e_tech');
    // The browser context's own request context: same cookies, same seat.
    const api = page.request;

    // Suffixes `k`/`l`: `tst.spec.ts` uses r/p/q/s on the same runKey when its
    // twins land on this worker index (D396).
    const received = await seedPcr(api, db, `Accrck${runKey}k`, 'received');
    const plated = await seedPcr(api, db, `Accrck${runKey}l`, 'plated');

    // CASE A, OVER HTTP. `'0'` is the string the screen never sends (DWA-E14).
    const created = await scanIntoRack(api, received.assen, '0');
    expect(created.status, `the rack scan was refused: ${JSON.stringify(created.message ?? created)}`)
      .toBe('ok');
    const reId = String(created.re_ID ?? '');
    expect(Number(reId), 'the precondition created a ci_racks row').toBeGreaterThan(0);

    // D381: registered BEFORE the click, and it is load-bearing here.
    // js/admin_racks.js:117-121 alerts on any answer that is not `ok`, and
    // Playwright auto-dismisses, so without this handler a refused scan is
    // indistinguishable from an accepted one.
    const dialogs: string[] = [];
    page.on('dialog', async (d) => { dialogs.push(d.message()); await d.dismiss(); });

    const { laneForm } = await openRack(page, reId);
    await expect(rackSample(laneForm, plated.assen),
      'precondition on the screen: the plated specimen is not on this rack yet').toHaveCount(0);

    // Exactly one <select> is visible in the scan form -- `select.btype`
    // (`parts/rack/main.php:439`). It is not upgraded to select2
    // (`js/admin.js:318-319` only touches `.js_select2`/`.njs_select2`), so the
    // native control is the one in the accessibility tree. Measured: count 1,
    // options Tox|ToxO|JVHL|JVHL-O|Blood|PCR.
    await laneForm.getByRole('combobox').selectOption({ label: 'PCR' });
    await scan(laneForm, plated.assen, 'searchbox');

    // IMMEDIATE FEEDBACK ONLY (D386); everything load-bearing is read back below.
    await expect(rackSample(laneForm, plated.assen),
      'the bench puts the already-plated specimen onto the rack').toHaveCount(1, { timeout: 15_000 });
    await expect(laneForm.locator('.warning_duplicate'),
      'a specimen this rack has never held is not refused as a duplicate').toHaveText('');
    expect(dialogs,
      'the bench accepted it silently -- an alert here means the action refused the scan')
      .toEqual([]);

    // THE DATABASE -- S-ACC-03 case B, kept in full. Read BEFORE the rendered
    // read-back because the rack row's own id is what the read-back is keyed
    // on; see below for why it may not be keyed on the accession.
    const rackBatch = db.query(
      `SELECT ID, btype FROM ci_rack_batches
        WHERE re_ID = ${Number(reId)} AND mid = ${Number(plated.mid)}`,
    );
    expect(rackBatch.length, `one ci_rack_batches row for mid ${plated.mid}`).toBe(1);
    const [rbid] = rackBatch[0];
    expect(rackBatch[0][1], 'ci_rack_batches.btype').toBe('pcr');
    expect(
      db.scalar(`SELECT batch_status FROM ci_batches_pcr WHERE mid = ${Number(plated.mid)}`),
      'a plated specimen is NOT downgraded to racked',
    ).toBe('plated');
    // THE NON-VACUITY CONTROL, and case B is worthless without it: if racking
    // stopped advancing ANYTHING, the assertion above would still be green.
    // The received specimen on the SAME rack did advance.
    expect(
      db.scalar(`SELECT batch_status FROM ci_batches_pcr WHERE mid = ${Number(received.mid)}`),
      'the guard is a guard, not a dead branch: the received specimen on this rack DID advance',
    ).toBe('racked');

    // THE READ BACK, off a SERVER render (D386): a fresh GET of the Racks tab,
    // whose sub-table is rebuilt by trrack_display_samples() from the stored
    // ci_rack_batches rows, not from the fragment the XHR left behind.
    //
    // D397 -- THE ROW IS KEYED ON `rbid_<ID>`, NOT ON `assen_<code>`. Both
    // classes are written from the same loop
    // (`inc/functions_rack_plate.php:2231-2233`), and `$batch->assen` fills the
    // `assen_` class AND the barcode cell -- so picking the row by accession and
    // then asserting the cell holds that accession compares one echo of a PHP
    // variable to another and cannot fail. Keyed on the rack-batch id instead,
    // the two values come from different tables: the id is `ci_rack_batches`,
    // the accession and the mid come through the LEFT JOIN onto
    // `ci_batches_pcr` (:2076-2079). A join that stopped resolving -- the D100
    // no-op this suite exists to catch -- renders the row with an EMPTY barcode
    // cell, and only this shape sees it.
    const { laneForm: reloaded } = await openRack(page, reId);
    const row = reloaded.locator(`table.subretbl tr.childp.rbid_${rbid}`);
    await expect(row, 'the reloaded rack still holds the line the scan wrote').toHaveCount(1);
    await expect(row.locator('td.assennum_1'),
      'the reloaded rack lists that line under the barcode that was scanned')
      .toHaveText(plated.assen);
    await expect(row.locator('td.assennum_1'),
      'and under the specimen the barcode belongs to')
      .toHaveAttribute('data-mid', plated.mid);
    // The received specimen was tube 1, so the plated one must be tube 2. The
    // ordinal is recomputed by the renderer from row order
    // (functions_rack_plate.php:2216), never stored, so a scan that appended
    // nowhere or inserted ahead of the first tube reads back here.
    await expect(row.locator('span.position'),
      'the plated specimen is the SECOND tube on this rack, behind the received one')
      .toHaveText('2');
    // The well the tech is told to pipette into. Asserted as a shape, not a
    // literal, because the geometry follows the `rack_ABC`/`rack_16` options:
    // the claim is that the bench names A well at all.
    await expect(row.locator('td').nth(2),
      'the bench tells the tech which well to put the tube in')
      .toHaveText(/^\s*[A-P]\d{1,2}\s*$/);

    console.log(
      `S-ACC-03u worker=${testInfo.workerIndex} lab=${plated.lab.prefix}(${plated.lab.labId}) ` +
        `rack=${reId} received_mid=${received.mid} plated_mid=${plated.mid}`,
    );
  });

  /**
   * S-ACC-04u -- THE RACK GOES ONTO A PLATE, AND THE TECH READS THE PLATE BACK.
   *
   * `31-USER-PERSPECTIVE-AUDIT.md:114` graded the HTTP twin **C** with one
   * stated gap: *"The plate number the tech reads off the screen is
   * unverified."* This closes exactly that, and closes it the way S-ACC-06u
   * closes the custody chain -- BEFORE and AFTER on the same search. A single
   * after-the-fact read cannot separate "the app plated this rack" from "the
   * plate reader prints a plate for anything it is asked about"; the pair can.
   *
   * D382: the HTTP twin in `tst.spec.ts` is kept and is not weakened. It owns
   * the `add_plate` JSON envelope, which a browser never sees.
   */
  e2e('S-ACC-04u the tech lays a rack onto a plate and reads the plate number back', async ({
    pageAs, db, runKey,
  }, testInfo) => {
    const page = await pageAs('e2e_tech');
    const api = page.request;

    const b1 = await seedPcr(api, db, `Accplt${runKey}m`, 'received');
    const b2 = await seedPcr(api, db, `Accplt${runKey}n`, 'received');

    // The rack, over HTTP for the DWA-E14 reason in the header.
    const first = await scanIntoRack(api, b1.assen, '0');
    expect(first.status, `rack scan refused: ${JSON.stringify(first.message ?? first)}`).toBe('ok');
    const reId = String(first.re_ID ?? '');
    expect(Number(reId), 'the precondition created a ci_racks row').toBeGreaterThan(0);
    const second = await scanIntoRack(api, b2.assen, reId);
    expect(second.status, `rack scan refused: ${JSON.stringify(second.message ?? second)}`).toBe('ok');
    // The rack number the plate map is expected to print, read from the row the
    // precondition created -- not a literal, and not the pl_number, which is a
    // per-day counter every worker shares.
    const reNumber = db.scalar(`SELECT re_number FROM ci_racks WHERE re_ID = ${Number(reId)}`);
    expect(Number(reNumber), 'the rack was given a number').toBeGreaterThan(0);

    // BEFORE. The tech asks where the specimen is and is told, in as many
    // words, that it is on no plate.
    await searchPlateView(page, b1.assen);
    await expect(page.getByText('Not found results', { exact: true }),
      'before the rack is plated, the plate reader finds the specimen on nothing').toBeVisible();
    await expect(page.locator('.plateblock'),
      'and draws no plate map at all').toHaveCount(0);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_plates WHERE pl_rack_ID = ${Number(reId)}`),
      'precondition: the rack has not been plated yet',
    ).toBe('0');

    // D381 again, and here the alert IS the app's only success feedback:
    // js/admin_racks.js:415-421 alerts 'Added to Plate Successfully!' on `ok`
    // and 'Error: <message>' otherwise, and changes nothing on the page either
    // way. Auto-dismiss would lose the difference silently.
    const dialogs: string[] = [];
    page.on('dialog', async (d) => { dialogs.push(d.message()); await d.dismiss(); });

    const { laneForm } = await openRack(page, reId);
    // `<a class="button addplatebtn">` with no href -> ARIA role `generic`, so
    // getByRole('button') resolves 0. Plan §2 clause 2.
    await laneForm.getByText('Add Plate', { exact: true }).click();
    await expect.poll(() => dialogs,
      { message: 'the tech is told the rack went onto a plate' })
      .toEqual(['Added to Plate Successfully!']);

    // THE DATABASE -- every S-ACC-04 assertion, scoped to this rack.
    const plates = db.query(
      `SELECT pl_ID, pl_number FROM ci_plates WHERE pl_rack_ID = ${Number(reId)}`,
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
      db.query(`SELECT DISTINCT batch_status FROM ci_batches_pcr WHERE mid IN (${mids.join(',')})`)
        .map((r) => r[0]),
      'both specimens advance to plated (action_add_plate.php:453)',
    ).toEqual(['plated']);

    // AFTER, off a fresh SERVER render (D386) -- a second GET of the reader,
    // not a re-read of the page the click left behind.
    await searchPlateView(page, b1.assen);
    await expect(page.getByText('Not found results', { exact: true }),
      'the plate reader no longer says the specimen is on nothing').toHaveCount(0);
    const block = page.locator('.plateblock');
    await expect(block,
      'searching the specimen finds exactly the one plate it was laid onto').toHaveCount(1);
    // THE AUDIT'S GAP, closed: the plate number the tech reads, against the one
    // the app minted. Rendered vs STORED, from two different code paths --
    // action_add_plate.php:182 computes it, parts/plate/view_96_print.php:34
    // prints it -- so a renderer that printed the rack number, the pl_ID or a
    // blank reads back here.
    await expect(block.locator('.title'),
      'the plate map names the plate number the app minted').toHaveText(`ELUTION PLATE ${plNumber}`);
    await expect(block.locator('.stitle'),
      'and names the rack the tech laid down').toContainText(`Rack #${reNumber}`);
    // The map is drawn, with the first tube in the first well.
    //
    // NO ACCESSION IS ASSERTED IN THE WELL, and this is the reason:
    // `parts/plate/view_96_print.php:107` rewrites every well's label with
    // `preg_match("/([a-zA-Z]*)([0-9]*)/", $assen, $matches)` and keeps only
    // `$matches[1] . '<br>' . $matches[2]`. The pattern is unanchored, so it
    // stops at the first letter following a digit: for this lab's accessions
    // (prefix `E2E`) the well reads `E`/`2` and the rest of the accession is
    // discarded. Filed as a defect card; asserting the truncated text would
    // pin the bug instead of the behaviour.
    await expect(block.locator('td[title="1"]'),
      'the first tube on the rack has a well drawn on the plate map').not.toBeEmpty();

    console.log(
      `S-ACC-04u worker=${testInfo.workerIndex} lab=${b1.lab.prefix}(${b1.lab.labId}) ` +
        `rack=${reId} re_number=${reNumber} plate=${plId} pl_number=${plNumber}`,
    );
  });
});

// ================================================================== DWA-E14
//
// NO RACK CAN BE CREATED THROUGH THE SCREEN UNDER PHP 8. Pinned as the gesture
// a bench operator makes at the start of every rack: open the Racks tab on a
// day that has no rack on it yet, and scan the first tube.
//
// THE MECHANISM, re-confirmed against the pinned tree 2026-08-25:
//
//  1. `parts/rack/main.php:279-285` -- when the day's query returns nothing the
//     page synthesises a rack sheet with `re_number`, `re_today` and
//     `re_priorty` ONLY, i.e. with no `re_ID` key at all.
//  2. `:372` renders that sheet's id as
//     `<input type="hidden" data-fname="re_ID" value="<?php echo isset($row['re_ID']) ? ... : '' ?>" />`
//     -- the EMPTY STRING. Measured live on this stack: on a rack-less date the
//     bench draws exactly one `tr.parent_c` and its `re_ID` input reads `""`.
//  3. `js/admin_racks.js:480-484` copies every `data-fname` in the sheet
//     straight into `$parentdata`, so the scan posts `parentdata[re_ID]=`.
//  4. `parts/rack/action_ci_search_barcode_rack.php:32` takes that string and
//     `:36` branches `if ($re_ID == 0)` to reach the `$wpdb->insert('ci_racks')`
//     at `:56`. On PHP 8 `'' == 0` is FALSE (measured in this container:
//     PHP 8.2.33, `var_dump("" == 0)` -> bool(false); it was TRUE on PHP 7), so
//     the create branch is skipped, the else at `:60` selects `ci_racks` by an
//     empty id and gets nothing, `:75` `if ($re_ID > 0)` is also false, and the
//     handler falls through to the final else: `{"status":"error","message":
//     "Error. Please try again"}`. `js/admin_racks.js:117-121` turns that into
//     an alert and the tube never reaches a rack.
//
// WHY THE DATE BOX. The rack listing is `where re_today='<date>'` with no lab
// predicate (`parts/rack/main.php:238`), so "today" is a bench SHARED with
// every other worker and with `tst.spec.ts`; the empty-sheet branch is only
// taken when the day holds no rack at all, which today does not once any sibling
// has scanned. The Racks tab's own Date control reaches a rack-less day
// deterministically, and it is the same `count($results) == 0` branch the first
// scan of any morning takes. The box is `readonly` (jQuery UI adds that when it
// attaches the datepicker), so the tech PICKS the date rather than typing it,
// and that is what this drives. The day is PROBED for emptiness against
// `ci_racks`, starting from a `runKey`-derived offset so two workers do not open
// on the same sheet (D396) and advancing past any day an earlier run left a rack
// on.
//
// `e2e.fail()` is INSIDE the body on purpose: at describe scope it annotates
// every sibling and would mask a real regression in the two tests above.

/**
 * Open the Racks tab on a day that HOLDS NO RACK and hand back that day's
 * (empty) rack sheet.
 *
 * The target date is built from the date the BENCH says it is, never from a
 * host `Date`: `$today` is `date_i18n('Y-m-d')` in the container's WordPress
 * timezone, and a host-side month rollover would pick a different day than the
 * one the query filters on.
 *
 * THE DAY IS PROBED, NOT HASHED, and the difference is what makes the pin below
 * usable more than once. A `runKey`-derived day is one of 28, so the moment a
 * rack exists on that day the pin's own precondition fails -- and it fails
 * INSIDE a `fail()` body, where Playwright counts it as the expected failure and
 * reports green. The day that reads as "flaky, ignore it" is the day after this
 * pin first flipped: proving a flip lays a rack down on exactly the day the next
 * run would pick. `reset.sh` clears it, so this only bites the
 * `--project=main --no-deps` loop D401 pushes fault injection into -- which is
 * the loop the flip proof runs in.
 *
 * The `runKey` offset is kept as the STARTING point so two workers do not open
 * on the same day, and the scan then advances to the first free one. Bounded at
 * 28 because the walk below moves exactly one month and every month has 28.
 */
async function openNewRackSheet(page: Page, db: Db, runKey: string) {
  await page.goto(RACKS_PAGE);
  const dateBox = page.locator('input[name="date"]');
  const [year, month] = (await dateBox.inputValue()).split('-').map(Number);
  const first = 1 + [...runKey].reduce((a, c) => (a * 31 + c.charCodeAt(0)) % 28, 7);
  const dateOf = (d: number) => `${month === 12 ? year + 1 : year}-`
    + `${String(month === 12 ? 1 : month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

  let day = 0;
  let target = '';
  for (let i = 0; i < 28; i += 1) {
    const candidate = 1 + ((first - 1 + i) % 28);
    if (db.scalar(`SELECT COUNT(*) FROM ci_racks WHERE re_today = ${Db.q(dateOf(candidate))}`) === '0') {
      day = candidate;
      target = dateOf(candidate);
      break;
    }
  }
  // Thrown, not asserted: this runs before the caller's `fail()` marker can
  // absorb it, and 28 occupied days is a fixture problem rather than a finding.
  if (!day) throw new Error(`every day of ${dateOf(1)}..${dateOf(28)} already holds a rack`);

  // jQuery UI's own widget, opened by clicking the field the way the tech does.
  // `#ui-datepicker-div` is the widget's id, not a test hook (D383 clause 4);
  // its month and year are `<select>`s inside the title, so the title carries no
  // readable text and `Next` is the control that moves a month.
  //
  // `getByText` for Next and `getByRole('link')` for the day, and the asymmetry
  // is the app's markup rather than a preference: jQuery UI emits
  // `<a class="ui-datepicker-next" title="Next"><span>Next</span></a>` with NO
  // href -> ARIA role `generic`, so `getByRole('link', { name: 'Next' })`
  // resolves 0 (measured; same trap as S-ACC-04u's `Add Plate`). The day cells
  // ARE `<a href="#" data-date="N">N</a>` -> role `link`, count 1 per month.
  await dateBox.click();
  const picker = page.locator('#ui-datepicker-div');
  await picker.getByText('Next', { exact: true }).click();
  await picker.getByRole('link', { name: String(day), exact: true }).click();
  // Picking a day fires `change`, and `js/admin.js:1907` submits the form on
  // `form input.submitchange` -- so this is a full server render of that day.
  await expect(dateBox, 'the bench is showing the day that was picked').toHaveValue(target);

  // Same reason as openBench/openRack: `tr.child` is display:none until the
  // operator expands it, so the barcode box is not in the accessibility tree.
  await page.getByText('Show All', { exact: true }).click();
  const sheet = page.locator('tr.parent_c');
  await expect(sheet, `the bench draws one rack sheet for ${target}`).toHaveCount(1);
  const laneForm = page.locator(`tr.child.p_${await sheet.getAttribute('data-p')}`);
  return { target, sheet, laneForm };
}

e2e.describe('rack creation @full @acc @mutates', () => {
  e2e('the tech scans the first tube of the day into a new rack and gets a rack', async ({
    pageAs, db, runKey,
  }, testInfo) => {

    const page = await pageAs('e2e_tech');
    const api = page.request;

    // Suffix `o`: `k`/`l`/`m`/`n` are taken by the two tests above on the same
    // runKey, and tst.spec.ts uses r/p/q/s (D396).
    const specimen = await seedPcr(api, db, `Accnew${runKey}o`, 'received');

    // D381: registered BEFORE the gesture. The refusal this pins is an
    // `alert()` (js/admin_racks.js:117-121) and Playwright auto-dismisses, so
    // without this the bench's own words are lost and the failure reads as an
    // unexplained empty table.
    const dialogs: string[] = [];
    page.on('dialog', async (d) => { dialogs.push(d.message()); await d.dismiss(); });

    // A `fail()` body swallows its OWN preconditions, which is why the day this
    // opens on is PROBED for emptiness rather than asserted empty (see
    // openNewRackSheet): an assertion here would be absorbed by the marker on
    // any run that reused a day, and the helper throws instead.
    const { target, laneForm } = await openNewRackSheet(page, db, runKey);

    // The tech picks the rack type, then scans. The sheet carries no
    // `re_panel_id`, so no option is preselected and the browser sits on the
    // first one (Tox) -- measured. `openRack`'s callers do not need this
    // because their rack row has `re_rack_type='pcr'` on file.
    await laneForm.getByRole('combobox').selectOption({ label: 'PCR' });
    await scan(laneForm, specimen.assen, 'searchbox');

    // THE PIN, AS THE TECH SEES IT. Polled as one object so the failure prints
    // what the bench SAID as well as what it drew -- today that is
    // `{ alerts: ['Error. Please try again'], tubes: 0 }`.
    await expect
      .poll(async () => ({
        alerts: dialogs.slice(),
        tubes: await rackSample(laneForm, specimen.assen).count(),
      }), {
        message: `scanning ${specimen.assen} as the first tube on a new rack must place it on `
          + 'a rack and raise no alert; under DWA-E14 the tube is refused and the tech has no '
          + 'way to start a rack at all',
        timeout: 15_000,
      })
      .toEqual({ alerts: [], tubes: 1 });

    // THE DATABASE. A rack for that day, and the tube linked to it.
    const racks = db.query(
      `SELECT re_ID, re_number, re_rack_type, re_author_id FROM ci_racks
        WHERE re_today = ${Db.q(target)}`,
    );
    expect(racks.length, `the scan lays down exactly one ci_racks row for ${target}`).toBe(1);
    const [reId, reNumber, rackType, author] = racks[0];
    expect(Number(reNumber), 'the rack is given a number (action_...rack.php:41)')
      .toBeGreaterThan(0);
    expect(rackType, 'ci_racks.re_rack_type is the lane the tech selected').toBe('pcr');
    expect(author, 'ci_racks.re_author_id is the tech who scanned').toBe(
      db.scalar("SELECT ID FROM wp_users WHERE user_login = 'e2e_tech'"),
    );

    const links = db.query(
      `SELECT re_ID, btype FROM ci_rack_batches WHERE mid = ${Number(specimen.mid)}`,
    );
    expect(links.length, `exactly one ci_rack_batches row for mid ${specimen.mid}`).toBe(1);
    expect(links[0][0], 'the tube is linked to the rack this scan created').toBe(reId);
    expect(links[0][1], 'ci_rack_batches.btype').toBe('pcr');
    expect(
      db.scalar(`SELECT batch_status FROM ci_batches_pcr WHERE mid = ${Number(specimen.mid)}`),
      'a received specimen advances to racked (action_...rack.php:720)',
    ).toBe('racked');

    // THE READ BACK, off a SERVER render (D386): a fresh GET of that day's
    // bench, rebuilt by trrack_display_samples() from the stored rows rather
    // than from the fragment the XHR left behind.
    const { rackRow, laneForm: reloaded } = await openRack(page, reId, target);
    await expect(rackSample(reloaded, specimen.assen),
      'the reloaded bench still holds the tube the scan placed').toHaveCount(1);
    await expect(rackSample(reloaded, specimen.assen).locator('td.assennum_1'),
      'and lists it under the barcode that was scanned').toHaveText(specimen.assen);
    // Rendered against STORED, from two different tables: the number comes from
    // `ci_racks.re_number` above, the text from the row the page re-queried.
    await expect(rackRow.locator('.racknum'),
      'the tech reads back the rack number the app minted').toHaveText(reNumber);

    console.log(
      `DWA-E14 worker=${testInfo.workerIndex} lab=${specimen.lab.prefix}(${specimen.lab.labId}) `
      + `date=${target} rack=${reId} mid=${specimen.mid} assen=${specimen.assen} `
      + `alerts=${JSON.stringify(dialogs)}`,
    );
  });
});

// ===========================================================================
// S-ACC-07 -- THE STALE-SPECIMEN GATE AT RECEIVING
// ===========================================================================

/**
 * A urine toxicology specimen degrades. Collect it, leave it in a courier bag
 * over a long weekend, and by the time it reaches the bench the analytes the
 * laboratory is about to report on may no longer be the ones that were in the
 * cup -- so the accessioner is meant to be STOPPED and made to decide, on the
 * record, whether this specimen is still fit to test. That decision point is
 * `inc/actions/ci_search_barcode_receiving.php:339-350`:
 *
 *     if ($receiving_w3d && $batch && !$pass_3days && !$verified_jejected) {
 *         $doc_t = strtotime($batch->doc);
 *         if ($doc_t > 1000 && $doc_t < $now_t - (86400 * $receiving_w3dnum)) {
 *             $return['status'] = 'm3days';
 *             $return['message'] = 'Sample is older than ' . $receiving_w3dnum . ' days!';
 *             wp_send_json($return);
 *
 * `wp_send_json` EXITS, so the specimen is not received: no `ci_batches_*`
 * update, no `ci_receiving_batches` link, nothing. The accessioner gets a
 * modal (`js/admin_receiving.js:478-497`) whose OK button re-scans the same
 * barcode with `pass_3days: 'yes'` -- the literal string, `:58` compares
 * against `'yes'` and NOT against a truthy value -- and whose Cancel button
 * clears the box and walks away.
 *
 * NOTHING IN THE SUITE HAS EVER DRIVEN THIS. Measured on the running instance
 * 2026-08-26: `ci_pdf_data.receiving_w3d` is `false`, so every receiving test
 * in this file and in tst.spec.ts has run with the gate switched OFF, and both
 * halves of the branch -- the warning and the override -- were unreachable.
 * Every specimen the suite seeds also posts `bat[doc]: 'auto'`, which
 * `ci_save_form_requisition.php:63-64` turns into NOW, so even with the option
 * on there was no specimen old enough to trip it.
 *
 * WHY @serial. `receiving_w3d` is a network-wide `ci_pdf_data` key, not a
 * per-lab or per-persona value: arming it changes the receiving bench for
 * every worker at once, and `fixtures/batch-id.ts` `seedDays()` hands out days
 * up to 16 back, so a main-project test scanning a day-allocated specimen
 * while this option is armed would be refused at the bench for a reason it
 * knows nothing about. The `serial` project (`workers: 1`,
 * `dependencies: ['main']`) is the only place that cannot happen.
 *
 * ONE COST OF THAT, and it is new: this is the suite's FIRST @serial app test,
 * so nothing has exercised the dependency edge before. A file filter does NOT
 * reach a dependency project, so `npx playwright test specs/acc.spec.ts` now
 * selects this test in `serial`, pulls in its dependency, and runs `main` IN
 * FULL -- measured 2026-08-26: 17 tests in 3 files became 203 in 19. To work on
 * this file alone, name the project: `--project=main` for everything else here,
 * `--project=serial --no-deps -g S-ACC-07` for this test.
 *
 * ARMING, NOT DRIVING -- the same posture as dst.spec.ts's auto-fax describe.
 * The subject is the BENCH, not the options form, and the options form posts
 * every key in `ci_pdf_data` at once, so driving it to flip one checkbox would
 * put the whole install's configuration through this test. The one key is
 * written here, read back through the app's own `tr_get_option()` before
 * anything depends on it, and restored to the exact prior state in `afterAll`.
 */
e2e.describe('S-ACC-07 stale specimen @serial @acc @mutates', () => {
  /** `receiving_w3dnum` is unset-or-3 on this install; `:9-11` floors it at 3. */
  const AGE_DAYS = 3;
  /** Comfortably past the gate, and past any PHP/JS timezone skew. */
  const COLLECTED_DAYS_AGO = 8;

  e2e.beforeAll(async ({}) => {
    const armed = wpEval(
      'global $option_key_cache;'
      + '$opt = get_option("ci_pdf_data");'
      + '$o = ["was" => isset($opt["receiving_w3d"]) ? $opt["receiving_w3d"] : null];'
      + '$opt["receiving_w3d"] = 1; update_option("ci_pdf_data", $opt);'
      + '$option_key_cache = array();'
      + '$o["w3d"] = (bool)tr_get_option("ci_pdf_data","receiving_w3d");'
      + '$o["w3dnum"] = (int)tr_get_option("ci_pdf_data","receiving_w3dnum");',
    );
    // The gate is OFF in the baseline, which is why nothing in the suite has
    // ever reached it -- and why `afterAll` puts back a FALSE rather than
    // unsetting the key: false is what the options checkbox writes when it is
    // unticked (`inc/ci_pdf_data-options.php:861`), and it is where this
    // install starts.
    expect(armed.was, 'ci_pdf_data.receiving_w3d before this describe armed it').toBe(false);
    // Both are preconditions the handler itself reads at `:7-11`. If either is
    // wrong the gate never fires and every assertion below is vacuous (D141).
    expect(armed.w3d, 'ci_pdf_data.receiving_w3d must read back ON through tr_get_option()')
      .toBe(true);
    expect(
      armed.w3dnum,
      'ci_pdf_data.receiving_w3dnum -- the age the warning quotes, and the multiplier at :344',
    ).toBe(AGE_DAYS);
  });

  e2e.afterAll(async ({}) => {
    const restored = wpEval(
      'global $option_key_cache;'
      + '$opt = get_option("ci_pdf_data"); $opt["receiving_w3d"] = false;'
      + 'update_option("ci_pdf_data", $opt); $option_key_cache = array();'
      + '$o = ["w3d" => (bool)tr_get_option("ci_pdf_data","receiving_w3d")];',
    );
    // Asserted, not assumed: a gate left armed refuses every day-allocated
    // specimen `fixtures/batch-id.ts` seedDays() hands out (its DAY AXIS block
    // names this option), so the next run's receiving tests would fail for a
    // reason nothing in them mentions.
    expect(restored.w3d, 'the stale-specimen gate is switched back off').toBe(false);
  });

  e2e('the bench refuses a specimen collected days ago until the accessioner overrides it', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }, testInfo) => {
    const page = authedPage;
    const f = infra(db);
    const uid = db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(tenant.login)}`);
    const patientLast = `Accold${runKey}S`;

    // THE COLLECTION DATE, posted explicitly. Every other seeder in the suite
    // posts `bat[doc]: 'auto'`; a back-dated collection is what the requisition
    // form's own date control produces for a specimen that was drawn last week
    // and mailed in, and it is the only column this test needs to differ.
    // NOT taken from `seedDays()`: that allocator's whole contract is
    // "a day nobody else is on", and what this test needs is "a day of a
    // stated AGE" -- see its DAY AXIS block, which names this gate.
    const collected = new Date();
    collected.setHours(12, 0, 0, 0);
    collected.setDate(collected.getDate() - COLLECTED_DAYS_AGO);
    const p = (n: number) => String(n).padStart(2, '0');
    const docValue = `${collected.getFullYear()}-${p(collected.getMonth() + 1)}`
      + `-${p(collected.getDate())} 12:00:00`;

    const seeded = await seedRequisition(patientLast, { 'bat[doc]': docValue });
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    // PRECONDITIONS, read from the row rather than assumed. `:344` compares
    // `strtotime($batch->doc)` against `now - 86400 * 3`, so a saver that
    // ignored the posted date -- or stored today -- would make the warning
    // impossible and the first leg below would fail for the wrong reason.
    const [[storedDoc, ageDays, statusBefore]] = db.query(
      `SELECT doc, DATEDIFF(NOW(), doc), batch_status
         FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    );
    expect(storedDoc, 'ci_batches.doc -- the saver kept the back-dated collection time')
      .toBe(docValue);
    expect(
      Number(ageDays),
      `the specimen must be older than the ${AGE_DAYS}-day threshold the bench warns at`,
    ).toBeGreaterThan(AGE_DAYS);
    expect(statusBefore, 'the specimen is ordered but has not arrived at the lab yet')
      .not.toBe('received');

    // ------------------------------------------- THE WARNING, ON THE SCREEN
    const { laneForm } = await openBench(page, runKey, 'urine');
    await scan(laneForm, assen);

    // jQuery UI moves the dialog to <body>, so it is NOT inside `laneForm`.
    const warning = page.getByRole('dialog');
    await expect(
      warning,
      `the bench must stop the accessioner: ${assen} was collected ${COLLECTED_DAYS_AGO} days `
      + `ago and the laboratory is about to test it as though it arrived fresh`,
    ).toBeVisible({ timeout: 15_000 });
    // The NUMBER, not just "a warning": `:347` interpolates $receiving_w3dnum,
    // so an accessioner told "older than 30 days" about a 4-day-old specimen
    // would wave it through. This is the value the option holds, asserted above.
    await expect(
      warning,
      'the warning names the threshold the specimen actually breached',
    ).toContainText(`Sample is older than ${AGE_DAYS} days!`);

    // ---------------------------------------- THE ACCESSIONER SAYS NO
    await warning.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(warning, 'declining closes the warning').toBeHidden();
    await expect(
      benchRow(page, assen),
      `${assen} was logged in at the bench although the accessioner declined the stale-specimen `
      + 'warning -- the laboratory now holds a specimen it decided not to accept',
    ).toHaveCount(0);

    // AND IN THE ROW. `wp_send_json` at `:349` exits before any of the
    // receiving writes, so a declined scan must leave the specimen exactly
    // where it was: not received, unlinked, un-positioned.
    const [[statusAfterCancel, receivedAfterCancel, posAfterCancel]] = db.query(
      `SELECT batch_status, COALESCE(spec_received,''), COALESCE(position,0)
         FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    );
    expect(statusAfterCancel, `ci_batches.batch_status for ${assen} after the accessioner declined`)
      .toBe(statusBefore);
    expect(receivedAfterCancel, 'ci_batches.spec_received is not stamped by a declined scan')
      .toBe('');
    expect(Number(posAfterCancel), 'a declined specimen takes no lane position').toBe(0);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_receiving_batches WHERE mid = ${Db.q(mid)}`),
      `a ci_receiving_batches link for mid ${mid} -- the specimen was filed into a session `
      + 'the accessioner cancelled out of',
    ).toBe('0');

    // ------------------------------------- THE ACCESSIONER OVERRIDES IT
    //
    // THE CONTROL, and it is the same specimen (D141). "Not received" on its
    // own passes just as well when the bench is broken, when the barcode never
    // matched a requisition, or when the scan never reached the server. The
    // second scan differs from the first in ONE value -- `pass_3days: 'yes'`,
    // which the OK button sets (`admin_receiving.js:486`) and `:58` compares
    // as a LITERAL string -- and it is what makes the refusal above
    // attributable to the age gate.
    await scan(laneForm, assen);
    const second = page.getByRole('dialog');
    await expect(second, 'the bench warns again on the re-scan').toBeVisible({ timeout: 15_000 });
    await second.getByRole('button', { name: 'OK', exact: true }).click();

    await expect(
      benchRow(page, assen),
      `the accessioner accepted the stale specimen and ${assen} must be logged in -- if it is `
      + 'not, the override does not work and a lab with this option on can never receive an '
      + 'aged specimen at all',
    ).toHaveCount(1, { timeout: 15_000 });

    // THE READ BACK, off a SERVER render (D386).
    const { row } = await reloadBench(page, assen);
    await expect(assenCell(row), 'the reloaded bench lists the specimen under its accession')
      .toHaveText(assen);
    await expect(patientCell(row), 'and names the patient it was ordered for')
      .toHaveText(`${patientLast}, Synthetic`);

    const [[statusAfterOk, receivedAfterOk, positionAfterOk, labId]] = db.query(
      `SELECT batch_status, spec_received, position, lab_id
         FROM ci_batches WHERE assen = ${Db.q(assen)}`,
    );
    expect(statusAfterOk, 'ci_batches.batch_status after the override').toBe('received');
    expect(receivedAfterOk, 'ci_batches.spec_received is stamped once the specimen is accepted')
      .toMatch(DATETIME);
    expect(Number(positionAfterOk), 'the accepted specimen takes a lane position')
      .toBeGreaterThan(0);
    // TENANCY (D172): an override must not move the specimen out of its lab.
    expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
    // RENDERED against STORED.
    await expect(positionInput(row), 'the rack position the operator reads is the one on file')
      .toHaveValue(positionAfterOk);

    const links = db.query(
      `SELECT rb.btype, rb.created_by, re.re_lab_ID
         FROM ci_receiving_batches rb
         JOIN ci_receiving re ON re.re_ID = rb.re_ID
        WHERE rb.mid = ${Db.q(mid)}`,
    );
    expect(links.length, `exactly one ci_receiving_batches row for mid ${mid} -- the declined `
      + 'scan left none, so this one is the override\'s').toBe(1);
    expect(links[0][1], 'ci_receiving_batches.created_by is the accessioner who overrode')
      .toBe(uid);
    expect(links[0][2], 'ci_receiving.re_lab_ID').toBe(tenant.labId);

    console.log(
      `S-ACC-07 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
      + `mid=${mid} assen=${assen} doc=${storedDoc} age=${ageDays}d pos=${positionAfterOk}`,
    );
  });
});
