import { expect, request as playwrightRequest } from '@playwright/test';
import type { APIRequestContext, APIResponse, Page } from '@playwright/test';
import { test as shared, BEFORE_BOUNCE_MS } from '../fixtures/test';
import { batchIds } from '../fixtures/batch-id';
import { expectScopeExcludes } from '../fixtures/assert-domain';
import { Db } from '../fixtures/db';
import { dexec } from '../fixtures/container';
import { allocateTenant, type Tenant } from '../fixtures/tenant';
import { seedRequisition as seedRequisitionAs } from '../fixtures/seed-domain';
import { STATE_DIR } from '../playwright.config';
import * as path from 'node:path';

// D220, fixed at the ENV layer: report release writes an HTML snapshot into
// wp-content/results/html/<assen>/ whenever final_results > 0, and that path is
// the git-tracked webroot. env/docker-compose.yml now mounts a tmpfs over
// wp-content/results (alongside cache/log/tmp), so the snapshot never reaches
// the working tree and no spec needs an afterAll to sweep up after itself.
// The afterAll that used to live here was also the D240 flake: it globbed
// `rm -rf .../lac*` and deleted snapshots a sibling worker was mid-assertion on.

/**
 * REV -- report release, reversal and amendment (S-REV-01 .. S-REV-08).
 *
 * PERSONA. The catalog names "medical director / approver" and "scientist /
 * bench reviewer". Verified against the RUNNING instance (D178 -- capabilities
 * come from wp_options.wp_user_roles, never from install_roles.php, because
 * Tr_Base_Class_V4::create_role does not rewrite an existing role):
 *
 *   role            ncaps  ci_results
 *   administrator     139   YES   <- e2e_dir, the medical-director persona (personas.md:99)
 *   AdminCIDW          76   YES
 *   scientist           6   YES   <- NO SEEDED USER HOLDS IT (fixture gap)
 *   labmanager         20   YES
 *   lab_tech           20   YES   <- e2e_tech
 *   leadlabsecman      15   YES   <- e2e_lead*, the tenant personas
 *   lab_sec            29   no    <- e2e_sec, the negative control
 *   administrator_p    46   no
 *
 * So `ci_results` -- the release capability (flow-result-review-and-release.md:36)
 * -- is held live by administrator, AdminCIDW, scientist, labmanager, lab_tech
 * and leadlabsecman. `ci_edit_statuses`, which personas.md:110 attributes to
 * AdminCIDW from install_roles.php:492, is held by NO live role at all.
 *
 * TENANCY. Lab LAC (lab_ID 6, persona e2e_lead2), pinned by pool index rather
 * than worker index so this file can run alone at --workers=1 without colliding
 * with the other flows' labs. Never a lab row of its own (D161/D172).
 *
 * MUTATION ON GET/RENDER (D034). There is no app path that creates a tox
 * ci_reports row for an ordinary panel: ci_save_form_requisition.php:852 inserts
 * one only when the ordered group carries tg_manual_edit, and the seeded panel
 * does not. The row is created by RENDERING the queue --
 * inc/actions/ci_load_results.php:415 inserts it, and auto-approves it outright
 * when the batch sits in a rejected status. Every test below therefore tags
 * @mutates. The mutation is scoped to this spec's own batches, not to global or
 * persona state, so these stay in the `main` project (no @serial).
 */

// LAA..LAH, ordered by lab_dba_init in allocateTenant(); LAC is index 2.
const LAC_POOL_INDEX = 2;

/**
 * LAO. `SPEC_LAB` (fixtures/tenant.ts) allocates LAA..LAN and calls LAO/LAP
 * spare, so a specimen seeded here can contaminate no sibling spec. Used as
 * the FOREIGN lab in S-REV-09.
 */
const SPARE_POOL_INDEX = 14;

const e2e = shared.extend<{}, { tenant: Tenant; director: APIRequestContext }>({
  // The approver. personas.md:99 -- the medical director has no dedicated role
  // and maps to `administrator`; e2e_dir carries exactly that role.
  director: [
    async ({}, use, workerInfo) => {
      const ctx = await playwrightRequest.newContext({
        baseURL: workerInfo.project.use.baseURL ?? 'http://localhost',
        storageState: path.join(STATE_DIR, 'e2e_dir.json'),
      });
      await use(ctx);
      await ctx.dispose();
    },
    { scope: 'worker' },
  ],
});

const ADMIN_PAGE = '/wp-admin/admin.php?page=tox_results';

// ci_batches.batch_id is int(10) NOT NULL, so it cannot be a string key. Unique
// across runs the way runKey is (D136), and scoped to this lab besides: every
// queue query filters lab_id as well as batch_id.
//
// D243, ROOT CAUSE. The worker slot is load-bearing, not decoration. This used
// to be `(Date.now()/1000 % 20_000_000) * 100` with a per-process `batchSeq`
// starting at 0 -- and every worker in a run starts within the same SECOND, so
// every worker computed the IDENTICAL base and then handed out the identical
// ids. The two `.serial` describes in this file land on different workers, both
// on lab LAC, so worker B's `renderQueue(BASE+1)` selected worker A's S-REV-02
// row as well and auto-approved its rejected draft on GET (D034/D225) --
// failing "still a draft before the approve" in a test that had done nothing
// wrong. `.serial` cannot fix that: it orders tests WITHIN a describe, and the
// collision is BETWEEN describes.
//
// The allocator itself now lives in fixtures/batch-id.ts, because the slot term
// separates two WORKERS and not two SPEC FILES -- and five specs had open-coded
// the same base with the same offset. The spec name is the offset key.
const nextBatchId = batchIds('rev');

interface Seeded { mid: string; assen: string; batchId: number; }

/**
 * One tox batch, through the app's own requisition saver (D085/D099), carrying
 * a batch_id and a batch_status so the review queue will pick it up.
 *
 * `batch_status='resulted'` is what makes ci_load_results.php:92 admit a batch
 * that has no report row yet; it is NOT a rejected code, so the saver's
 * auto-approve branch (ci_save_form_requisition.php:829) stays shut and the
 * report is born a draft.
 */
async function seedBatch(
  seedRequisition: (p: string, o?: Record<string, string>) => Promise<{ status: number }>,
  db: Db,
  tenant: Tenant,
  patientLast: string,
  batchStatus = 'resulted',
  // ADDITIVE (D382): every existing call site keeps allocating its own batch id
  // and posting nothing extra. `batchId` is what lets TWO specimens share one
  // batch -- the only way to build a twin the queue's own `batch_id` filter
  // cannot separate -- and `overrides` reaches the requisition fields the
  // queue's lab chain reads (`ref_lab_ID`).
  opts: { batchId?: number; overrides?: Record<string, string> } = {},
): Promise<Seeded> {
  const batchId = opts.batchId ?? nextBatchId();
  const seeded = await seedRequisition(patientLast, {
    'bat[batch_id]': String(batchId),
    'bat[batch_status]': batchStatus,
    ...opts.overrides,
  });
  // Not toBeLessThan(400): a login redirect is 302 and would pass that.
  expect(seeded.status, 'the requisition saver answers 200').toBe(200);

  const rows = db.query(
    `SELECT mid, assen, lab_id, lab, batch_status, batch_id FROM ci_batches
      WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
  );
  expect(rows.length, `exactly one ci_batches row for ${patientLast}`).toBe(1);
  const [mid, assen, labId, lab, status, bid] = rows[0];

  // D160: the saver answers 200 with assen='' when the minter fails.
  expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
  // Tenancy, asserted rather than assumed (R10/D161/D172).
  expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
  expect(lab, 'ci_batches.lab').toBe(tenant.prefix);
  expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(tenant.prefix);
  expect(status, 'ci_batches.batch_status as seeded').toBe(batchStatus);
  expect(bid, 'ci_batches.batch_id as seeded').toBe(String(batchId));

  return { mid, assen, batchId };
}

/**
 * Render the tox review queue fragment. THIS WRITES (D034).
 *
 * The body is the SCREEN, not a status code: `ci_load_results.php:560` echoes
 * `ba.assen` into the accession cell the reviewer clicks. Every `expectAssens`
 * entry is asserted present, so a queue that renders zero rows, another lab's
 * rows, or fatals after the write goes red here instead of passing on a 200.
 */
async function renderQueue(
  ctx: APIRequestContext,
  tenant: Tenant,
  batchId: number,
  ...expectAssens: string[]
): Promise<string> {
  const res = await ctx.post(ADMIN_PAGE, {
    form: {
      tr_action: 'ci_load_results',
      batch_id: String(batchId),
      lab_id: tenant.labId,
      ref_lab_id: '0',
    },
  });
  expect(res.status(), 'tox results fragment').toBe(200);
  const body = await res.text();
  for (const assen of expectAssens) {
    expect(body, `the tox review queue shows ${assen} to the reviewer`).toContain(assen);
  }
  return body;
}

/**
 * The same POST as `renderQueue`, with the form written out in full.
 *
 * `renderQueue` always sends `lab_id` and always sends `ref_lab_id=0`, and so do
 * dst.spec.ts:125 and adm.spec.ts:177 -- every render in the suite. But
 * `ci_load_results.php:70-79` is one if / else-if / else-if keyed on exactly
 * those two fields:
 *
 *     if ($ref_lab_id > 0)       :71  the reference-lab view
 *     else if ($lab_id > 0)      :74  the posted lab wins
 *     else if (has_user_labs())  :78  the reviewer's OWN lab binding
 *
 * so the suite only ever drove the MIDDLE arm and the other two were dead code
 * from its point of view. This helper is what lets a test choose the arm.
 *
 * Deliberately no `expectAssens`: the two tests below assert presence AND
 * absence on the same body, and a helper that only knows how to assert presence
 * would have to be called twice to say one thing.
 */
async function postQueue(
  ctx: APIRequestContext, form: Record<string, string>,
): Promise<string> {
  const res = await ctx.post(ADMIN_PAGE, { form: { tr_action: 'ci_load_results', ...form } });
  expect(res.status(), 'tox results fragment').toBe(200);
  return res.text();
}

/**
 * One rendered queue row, sliced on the report id the row carries
 * (`ci_load_results.php:556` -- `<tr class="childp ..." data-id="<id>">`).
 * Slicing first is what makes the value assertions below row-exact: `>C</td>`
 * anywhere in a multi-row fragment would otherwise match a sibling specimen.
 */
function queueRow(body: string, reportId: string): string {
  const i = body.indexOf(`data-id="${reportId}"`);
  expect(i, `the queue rendered no row for report ${reportId}`).toBeGreaterThan(-1);
  const end = body.indexOf('</tr>', i);
  expect(end, `the queue row for report ${reportId} is unterminated`).toBeGreaterThan(i);
  return body.slice(i, end);
}

interface Report {
  id: string; estatus: string; approveDate: string; finalResults: string;
  approvedBy: string; labId: string; modified: string; faxed: string;
}

function report(db: Db, assen: string): Report {
  const rows = db.query(
    `SELECT id, estatus, approve_date, final_results, approved_by_ID, lab_id, modified, faxed
       FROM ci_reports WHERE patient_id = ${Db.q(assen)}`,
  );
  expect(rows.length, `exactly one ci_reports row for ${assen}`).toBe(1);
  const [id, estatus, approveDate, finalResults, approvedBy, labId, modified, faxed] = rows[0];
  return { id, estatus, approveDate, finalResults, approvedBy, labId, modified, faxed };
}

const batchStatusOf = (db: Db, mid: string): string =>
  db.scalar(`SELECT CONCAT('[', batch_status, ']') FROM ci_batches WHERE mid = ${Db.q(mid)}`)
    .replace(/^\[|\]$/g, '');

/** Audit rows for one accession. Exact match on assen, never LIKE (D146). */
function auditRows(db: Db, action: string, assen: string): string[][] {
  return db.query(
    `SELECT user_id, mid, btype, assen FROM ci_log_users
      WHERE user_action = ${Db.q(action)} AND assen = ${Db.q(assen)}`,
  );
}

const act = (ctx: APIRequestContext, params: Record<string, string>) =>
  ctx.get(`${ADMIN_PAGE}&${new URLSearchParams(params)}`, { maxRedirects: 0 });

/** The same action, off the dashboard -- a wp-admin URL every logged-in user reaches. */
const actOnDashboard = (ctx: APIRequestContext, params: Record<string, string>) =>
  ctx.get(`/wp-admin/index.php?${new URLSearchParams(params)}`, { maxRedirects: 0 });

// --- the review queue AS A SCREEN (D380/D386/D387) --------------------------

/**
 * Open the tox review queue in Chromium and filter it down to ONE accession.
 *
 * Driven as the SPEC'S OWN tenant persona, never as e2e_dir (D387). `e2e_dir`
 * is bound to lab 35 and renders `tr.parent` count 0 on this screen; widening
 * its `_lab_ids` to reach a pool lab would hand one shared persona a view over
 * every spec's specimens, which is the D239 isolation this suite pays for.
 * `leadlabsecman` holds `ci_results` live (see the persona table above), so the
 * tenant persona is a legitimate reviewer seat and is already lab-scoped.
 *
 * The search is a GET (`?page=tox_results&tab&s=<assen>`), so `page.reload()`
 * replays the filter -- which is what makes the D386 server read-back cheap.
 * Exactly one `tr.parent` then matches, and `parts/tox/results_list.php:595-603`
 * auto-expands it with no click, firing the `ci_load_results` fragment that
 * renders the reviewer's row.
 *
 * THIS WRITES (D034), same as renderQueue(): the fragment inserts the report
 * row on first render. Read every precondition BEFORE calling this.
 */
async function openQueueFor(page: Page, assen: string) {
  await page.goto(ADMIN_PAGE);
  // No label[for] and no accessible name on this control (D383 fallback 3):
  // the name= attribute is what the user's own browser submits.
  await page.locator('div.tablenav form input[name="s"]').fill(assen);
  await page.getByRole('button', { name: 'Search' }).click();
  const row = page.locator('tr.childp').filter({ hasText: assen });
  await expect(row, "the reviewer's own queue shows the specimen").toHaveCount(1);
  return row;
}

/**
 * The same filtered queue, reached BY URL instead of by typing in the search
 * box -- the search form carries no other query parameter, and `&view=1` has to
 * ride along for S-REV-11u. `s` is a plain request param
 * (`parts/tox/results_list.php:72`), so this is the identical filter.
 *
 * The row is NOT asserted here: two of the callers below are `fail()` pins
 * whose whole statement is about whether that row is still there, and an
 * assertion in the helper would either duplicate the pin or pre-empt it.
 *
 * THIS WRITES (D034), for the same reason openQueueFor does.
 */
async function gotoQueueFor(page: Page, assen: string) {
  await page.goto(`${ADMIN_PAGE}&s=${assen}`);
  return page.locator('tr.childp').filter({ hasText: assen });
}

/**
 * D386 -- RE-RENDER FROM THE SERVER before believing anything on screen.
 *
 * `js/admin.js:1416` sets the Approve/Reverse control's new text on ANY HTTP
 * 200, and the `td.statuscol` rewrite at :1421-1428 is gated only on the
 * substring `updated` -- which `parts/tox/results.php:158` queues immediately
 * after `$wpdb->update()` without checking that the update affected a row. So
 * every post-XHR assertion here is markup the CLIENT authored: it survives a
 * write that silently did nothing (the D100 missing-column no-op), which is the
 * exact failure class this suite exists to catch. Reload, and assert on markup
 * `ci_load_results.php` produced.
 */
async function reloadQueue(page: Page, assen: string) {
  await page.reload();
  const row = page.locator('tr.childp').filter({ hasText: assen });
  await expect(row, 'the reloaded queue still shows the specimen').toHaveCount(1);
  return row;
}

/** wp_users.ID of the acting persona -- the audit row IS the sign-off (S-REV-06). */
const userIdOf = (db: Db, login: string): string =>
  db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(login)}`);

// ---------------------------------------------------------------------------

/**
 * Mint an instrument key for the result API. The endpoint's whole authorization
 * is `select wsdl_ID from ci_wsdl_keys where wsdl_key = '<key>'`
 * (`api/ci_result_api.php:24-31`) -- it reads neither `wsdl_active`,
 * `wsdl_type`, `wsdl_start` nor `wsdl_lab_ID`. `wsdl_url=''` keeps the key out
 * of `inc/crons/wsdl_put_req.php:29`, so a key minted here can never make a
 * cron open an outbound connection (hub.spec.ts:55-62, same reasoning).
 */
function grantResultApiKey(db: Db, key: string, labId: string): void {
  db.query(
    `INSERT INTO ci_wsdl_keys (wsdl_url, wsdl_key, wsdl_lab_ID, wsdl_type, wsdl_active, wsdl_start)
     VALUES ('', ${Db.q(key)}, ${Number(labId)}, '', 1, '2000-01-01')`,
  );
}

// ===========================================================================
// WHAT THE AMENDMENT AND THE POSITIVE FLAG LOOK LIKE FROM OUTSIDE THE BENCH
//
// Everything above this line asserts the reviewer's own screen. These two
// assert the two clinical signals a REPORT CONSUMER acts on -- "this result
// was corrected" and "this result is positive" -- and neither survives the
// trip out of the review queue.
// ===========================================================================

/**
 * The lab the seeded CLINIC-FACING personas are bound to.
 *
 * `e2e_doctor`, `e2e_officemgr`, `e2e_multisite` and `e2e_dir` all carry
 * `_lab_ids = <this lab>` (measured), and the pool labs LAA..LAP belong to the
 * spec files. `ci_load_test_results.php:23-29` scopes the clinician's line list
 * by `ba.lab_id in (get_user_lab_ids())`, so a specimen seeded into a POOL lab
 * is invisible to every one of those seats -- which is why the two tests below
 * seed here instead of into `tenant`.
 *
 * Resolved from the persona rather than hardcoded: the binding IS the fixture.
 */
function reportingLab(db: Db): Tenant {
  const [row] = db.query(
    `SELECT l.lab_ID, l.lab_dba_init
       FROM ci_labs l
       JOIN wp_usermeta m ON m.meta_key = '_lab_id' AND m.meta_value = l.lab_ID
       JOIN wp_users    u ON u.ID = m.user_id AND u.user_login = 'e2e_dir'
      WHERE l.lab_status = 1`,
  );
  if (!row) throw new Error('e2e_dir carries no _lab_id -> ci_labs binding (env/seed.php)');
  const [labId, prefix] = row;
  return { labId, prefix, login: 'e2e_dir', statePath: path.join(STATE_DIR, 'e2e_dir.json') };
}

/** The clinician's report list. `tab=tox` because the amended report is a tox report. */
const TEST_RESULTS = '/wp-admin/admin.php?page=ci_test_results&tab=tox';

/**
 * D239 -- SEQUENTIAL BY NECESSITY, not by preference.
 *
 * Every test here calls renderQueue(), and rendering the tox results listing
 * AUTO-APPROVES reports whose batch is in a rejected status (D034/D225,
 * flow-result-review-and-release.md:133 -- "a view is not idempotent"). Run in
 * parallel against one lab, test A's render releases test B's draft, and
 * S-REV-02 fails on "still a draft before the approve" in ~2 of 3 runs.
 *
 * Pinning the spec to its own lab (SPEC_LAB) was necessary but NOT sufficient:
 * the contamination is between tests INSIDE this file, so they must also run
 * one at a time. This is the cost of an app whose GETs mutate; it is not a
 * defect in any individual test here.
 *
 * MODE `default`, NOT `serial`, AND THE DIFFERENCE IS THE WHOLE REASON THIS
 * BLOCK IS READABLE. Both modes put every test below on ONE worker in
 * declaration order, which is the mutual exclusion the paragraphs above ask
 * for: playwright's own grouping treats a `default` suite as an
 * "outerMostSequentialSuite" exactly as it treats a `serial` one, so the
 * isolation is byte-for-byte the same (and D150/D170 still applies to both --
 * neither serialises against another spec file).
 *
 * `serial` adds exactly one thing on top, and it is not isolation: it ABANDONS
 * every remaining test in the block the moment one fails. This block holds
 * EIGHT defect pins that are RED until the product is fixed. Under `serial`
 * the first of them took the other seven -- plus two ordinary tenancy tests --
 * out of every run, reported as "did not run" rather than as coverage lost.
 * MEASURED: 9 of this file's 22 lifecycle tests executed in no run at all, and
 * seven confirmed defects were in that silence. `default` keeps the isolation
 * and drops the abandonment. Do not put `.serial` back.
 */
e2e.describe('report release lifecycle @rev @mutates', () => {
  e2e.describe.configure({ mode: 'default' });
  /**
   * D382 -- KEPT, not replaced by S-REV-01u below. The queue's Approve control
   * fires `trajax act=results_approve` (`parts/tox/results.php:140`), a
   * DIFFERENT handler with a different write set, so converting this test would
   * silently retire the `tr_action` path while the report still said the
   * scenario was covered.
   *
   * CORRECTION, measured 2026-08-26: the `@nouicaller` tag in the title is
   * WRONG as a statement about the app, and it is left in place only to avoid
   * renaming a test other notes cite. `tr_action=ci_results_approve`
   * (`inc/admin_actions.php:1104`) DOES have a UI caller --
   * `parts/tox/results_pending.php:431-434` binds `a.approve_link` to exactly
   * that URL, and `results_pending.php:435-441` binds `a.delete_link` to
   * `tr_action=ci_results_delete`. The Pending Results tab those links live on
   * is offered to `lab_sec`, `labsecman`, `leadlabsecman` and `labb`
   * (`parts/tox/results.php:274-279`) -- and `leadlabsecman` is this spec's own
   * tenant persona. What is true is narrower: no test here can DRIVE that
   * screen, because its query requires a `ci_results` row with `pos-neg='PEN'`
   * behind the report (`results_pending.php:109`) and `re.author_id` equal to
   * the viewer for any role outside the privileged list (`:32-35`, the `and
   * re.author_id` clause at `:34`), and
   * `ci_load_results.php:415` writes neither. Reaching it needs a fixture that
   * imports instrument results; until then this file drives the handler by URL
   * and says so.
   */
  e2e('S-REV-01 @smoke @nouicaller the director releases a report and the act is attributable', async ({
    seedRequisition, tenantRequest, director, db, tenant, runKey,
  }, testInfo) => {
    const patientLast = `Revsyn${runKey}a`;
    const b = await seedBatch(seedRequisition, db, tenant, patientLast);

    const queue = await renderQueue(tenantRequest, tenant, b.batchId, b.assen);
    const draft = report(db, b.assen);
    expect(draft.estatus, 'a freshly created report is a draft').toBe('');
    // The screen, not the column: a draft offers the reviewer Approve and an
    // empty status cell (ci_load_results.php:606/629).
    expect(queueRow(queue, draft.id), 'the queue offers Approve on a draft')
      .toContain('>Approve<');
    expect(draft.labId, 'ci_reports.lab_id -- the report is routed to the tenant lab').toBe(tenant.labId);

    const res = await act(director, { tr_action: 'ci_results_approve', id: draft.id });
    expect(res.status(), 'the approve action redirects back to the queue').toBe(302);

    const after = report(db, b.assen);
    expect(after.estatus, 'ci_reports.estatus').toBe('C');
    expect(after.approveDate, 'ci_reports.approve_date is stamped').not.toBe('');
    expect(after.approveDate, 'ci_reports.approve_date is a real timestamp')
      .not.toBe('0000-00-00 00:00:00');
    expect(Number(after.modified), 'ci_reports.modified is stamped').toBeGreaterThan(0);
    expect(batchStatusOf(db, b.mid), 'ci_batches.batch_status').toBe('completed');

    // THE READ BACK. The release is only real if the reviewer can see it: the
    // status cell carries the released code and the action flips to Reverse.
    const releasedRow = queueRow(
      await renderQueue(tenantRequest, tenant, b.batchId, b.assen), after.id,
    );
    expect(releasedRow, "the reviewer's status column shows the released code C")
      .toContain('>C</td>');
    expect(releasedRow, 'a released report offers Reverse, never Approve')
      .toContain('>Reverse<');

    // The audit row IS the sign-off: the report row itself records no approver
    // (see S-REV-06), so `approve_rs` is the only place the acting user lands.
    const dirId = db.scalar("SELECT ID FROM wp_users WHERE user_login = 'e2e_dir'");
    const rows = auditRows(db, 'approve_rs', b.assen);
    expect(rows.length, 'exactly one approve_rs audit row').toBe(1);
    expect(rows[0], 'ci_log_users [user_id, mid, btype, assen]')
      .toEqual([dirId, b.mid, 'tox', b.assen]);

    console.log(
      `S-REV-01 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) ` +
      `mid=${b.mid} assen=${b.assen} report=${draft.id}`,
    );
  });

  e2e('S-REV-02 approving a rejected specimen does not reopen the batch', async ({
    seedRequisition, tenantRequest, director, db, tenant, runKey,
  }) => {
    const patientLast = `Revsyn${runKey}b`;
    const b = await seedBatch(seedRequisition, db, tenant, patientLast);
    await renderQueue(tenantRequest, tenant, b.batchId, b.assen);
    const draft = report(db, b.assen);

    // Reject the specimen through the app's own results-edit saver, which is the
    // screen a reviewer uses to set batch status (ci_save_result_edit.php:527).
    // No `approval`/`reverse` field is posted, so estatus is untouched here.
    const rejected = await director.post(`${ADMIN_PAGE}&tr_action=ci_save_result_edit&id=${draft.id}`, {
      form: { 'info[final_results]': '0', 'updatebat[batch_status]': 're_spilled' },
    });
    expect(rejected.status(), 'results-edit saver').toBe(200);
    expect(batchStatusOf(db, b.mid), 'batch is rejected before the approve').toBe('re_spilled');
    expect(report(db, b.assen).estatus, 'still a draft before the approve').toBe('');

    await act(director, { tr_action: 'ci_results_approve', id: draft.id });

    const after = report(db, b.assen);
    expect(after.estatus, 'ci_reports.estatus -- the report still releases').toBe('C');
    expect(after.approveDate, 'ci_reports.approve_date is stamped').not.toBe('');
    // The guard: admin_actions.php:1126 skips the completion write for a
    // rejected batch, so a rejected specimen is not silently reopened.
    expect(batchStatusOf(db, b.mid), 'ci_batches.batch_status stays rejected')
      .toBe('re_spilled');
  });

  /**
   * S-REV-02u -- S-REV-02 AGAINST THE *OTHER* COPY OF THE SAME RULE.
   *
   * The rejected-batch guard is written TWICE. `inc/admin_actions.php:1125-1127`
   * carries it for `tr_action=ci_results_approve`, which is what S-REV-02 above
   * drives. The review queue's own Approve control fires a DIFFERENT handler --
   * `trajax act=results_approve`, `parts/tox/results.php:140-172` -- and that
   * handler carries its own copy of the guard at `:152-154`. Until this test,
   * no test drove a REJECTED specimen through the screen's copy, so deleting
   * that copy changed nothing any test could see.
   *
   * WHAT THE GUARD ACTUALLY GUARDS -- narrower than its name suggests, and this
   * is what the assertions below are shaped around. `results.php:146-151`
   * releases the report -- `estatus='C'`, `approve_date`, `faxed=0` --
   * UNCONDITIONALLY, before the guard is read at all. A rejected specimen is
   * signed out to the ordering clinic either way. The guard stops exactly one
   * thing: `ci_batches.batch_status` being moved to `completed`. A rejected
   * specimen whose batch reads `completed` has left the review queue and has
   * stopped rendering its rejection marker, so the reviewer who comes looking
   * for it sees an ordinary finished specimen. That is the harm, and the batch
   * status plus the marker are therefore the two assertions that discriminate;
   * the estatus/approve_date pair is carried as a CONTROL that the click
   * reached the handler at all.
   *
   * WHY THE REJECTION IS POSTED WITH THE QUEUE ALREADY OPEN. Rendering the
   * queue for a batch that is ALREADY in a rejected status auto-approves the
   * report outright (`ci_load_results.php:434-438`, D034/D225), so a reviewer
   * who opens the queue after the rejection is never offered an Approve control
   * to click. The reachable path -- and the realistic one -- is the reviewer
   * holding the queue open while the specimen is rejected at the bench, then
   * clicking the Approve their screen is still showing them. That is the same
   * order of events S-REV-02 uses over HTTP; only the caller differs.
   */
  e2e('S-REV-02u the reviewer approves a rejected specimen from the queue and it stays rejected', async ({
    seedRequisition, authedPage, director, db, tenant, runKey,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}ub`);
    // PRECONDITION BEFORE NAVIGATION (D034): opening the queue CREATES the row.
    expect(db.scalar(`SELECT COUNT(*) FROM ci_reports WHERE patient_id = ${Db.q(b.assen)}`),
      'no report row before the reviewer opens the queue').toBe('0');

    const row = await openQueueFor(authedPage, b.assen);
    const draft = report(db, b.assen);
    expect(draft.estatus, 'a freshly created report is a draft').toBe('');
    await expect(row.getByText('Approve', { exact: true }),
      'the reviewer is offered Approve while the specimen is still good').toBeVisible();

    // The bench rejects the specimen while the reviewer's queue stays open.
    // Same saver S-REV-02 uses (ci_save_result_edit.php:527); no approval or
    // reverse field is posted, so estatus is untouched by this.
    const rejected = await director.post(
      `${ADMIN_PAGE}&tr_action=ci_save_result_edit&id=${draft.id}`,
      { form: { 'info[final_results]': '0', 'updatebat[batch_status]': 're_spilled' } },
    );
    expect(rejected.status(), 'the results-edit saver answers the bench').toBe(200);
    expect(batchStatusOf(db, b.mid), 'the batch is rejected before the reviewer clicks')
      .toBe('re_spilled');
    expect(report(db, b.assen).estatus, 'still a draft before the approve').toBe('');

    // The reviewer clicks the Approve their screen is still offering.
    await row.getByText('Approve', { exact: true }).click();
    // IMMEDIATE FEEDBACK ONLY -- admin.js:1416 writes this on any HTTP 200. It
    // is used here as the sync point that stops the reload racing the XHR.
    await expect(row.getByText('Reverse', { exact: true }),
      'the control flips to Reverse').toBeVisible();

    // THE READ BACK, off a SERVER render (D386). Re-rendering is safe now:
    // `ci_load_results.php:434` auto-approves only a report that is neither C
    // nor M, and this one now reads C.
    const after = await reloadQueue(authedPage, b.assen);
    await expect(after.getByRole('cell', { name: 'C', exact: true }),
      'the report is signed out -- results.php:147 releases before it reads the guard')
      .toBeVisible();
    // `ci_load_results.php:461-463` renders this marker from the BATCH status,
    // so it IS the guard's outcome as the reviewer reads it. It is the app's own
    // tooltip on `<span class="ricon rejected" title="Rejected">X</span>`; the
    // cell carries no text a role query can name (D383 fallback 3).
    await expect(after.getByTitle('Rejected', { exact: true }),
      'the reviewer still sees this specimen flagged as rejected, not as an ordinary '
      + 'finished one').toBeVisible();

    // THE DATABASE. estatus/approve_date/faxed hold either side of the guard --
    // they are the control proving the click reached the screen's own handler
    // (the tr_action twin at admin_actions.php:1116 writes no `faxed` at all).
    const rpt = report(db, b.assen);
    expect(rpt.estatus, 'ci_reports.estatus -- the report still releases').toBe('C');
    expect(rpt.approveDate, 'ci_reports.approve_date is stamped').not.toBe('');
    expect(rpt.faxed, 'ci_reports.faxed is reset by the queue Approve').toBe('0');
    // THE DISCRIMINATOR: results.php:152-154 skips the completion write for a
    // rejected batch, so the specimen is not silently moved out of review.
    expect(batchStatusOf(db, b.mid), 'ci_batches.batch_status stays rejected')
      .toBe('re_spilled');
  });

  /** D382, same as S-REV-01: `tr_action=ci_results_reverse` has zero UI callers. */
  e2e('S-REV-03 @nouicaller reverse reopens the report and drops the batch out of completed', async ({
    seedRequisition, tenantRequest, director, db, tenant, runKey,
  }) => {
    const patientLast = `Revsyn${runKey}c`;
    const b = await seedBatch(seedRequisition, db, tenant, patientLast);
    await renderQueue(tenantRequest, tenant, b.batchId, b.assen);
    const r = report(db, b.assen);

    await act(director, { tr_action: 'ci_results_approve', id: r.id });
    expect(report(db, b.assen).estatus, 'released before the reverse').toBe('C');
    expect(batchStatusOf(db, b.mid), 'completed before the reverse').toBe('completed');

    await act(director, { tr_action: 'ci_results_reverse', id: r.id });

    expect(report(db, b.assen).estatus, 'ci_reports.estatus').toBe('M');
    expect(batchStatusOf(db, b.mid), 'ci_batches.batch_status').toBe('resulted');

    const dirId = db.scalar("SELECT ID FROM wp_users WHERE user_login = 'e2e_dir'");
    const rows = auditRows(db, 'reverse_rs', b.assen);
    expect(rows.length, 'exactly one reverse_rs audit row').toBe(1);
    expect(rows[0], 'ci_log_users [user_id, mid, btype, assen]')
      .toEqual([dirId, b.mid, 'tox', b.assen]);
  });

  /**
   * S-REV-01u -- S-REV-01 AS A USER SEES IT. Adds a rendered fact at every step;
   * every database assertion of S-REV-01 is kept, and two are ADDED because the
   * handler the screen fires is not the handler S-REV-01 drives (D382/C10):
   * the trajax approve also writes `faxed = 0` and guards the ci_batches
   * completion write behind `estatus != 'C'`.
   */
  e2e('S-REV-01u the reviewer releases a report from the queue and sees it released', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}ua`);

    // PRECONDITION BEFORE NAVIGATION (D034). Opening the queue CREATES the
    // report row; anything asserted afterwards is the app's own side effect.
    expect(db.scalar(`SELECT COUNT(*) FROM ci_reports WHERE patient_id = ${Db.q(b.assen)}`),
      'no report row before the reviewer opens the queue').toBe('0');

    const row = await openQueueFor(authedPage, b.assen);

    // What the reviewer sees on a DRAFT: an empty status cell, Approve offered,
    // and the Edit link that lets the report still be changed.
    const draft = report(db, b.assen);
    expect(draft.estatus, 'a freshly created report is a draft').toBe('');
    expect(draft.labId, 'ci_reports.lab_id -- the report is routed to the tenant lab')
      .toBe(tenant.labId);
    await expect(row.locator('td.statuscol'), 'a draft renders no status code').toHaveText('');
    // `<a class="approvebtn">` carries no href, so its ARIA role is `generic`
    // and getByRole('link'|'button') resolves 0 (D383 fallback 2).
    await expect(row.getByText('Approve', { exact: true }),
      'a draft offers the reviewer Approve').toBeVisible();
    await expect(row.getByRole('link', { name: 'Edit' }),
      'a draft can still be edited').toBeVisible();

    await row.getByText('Approve', { exact: true }).click();
    // IMMEDIATE FEEDBACK ONLY -- admin.js:1416 writes this on any HTTP 200.
    await expect(row.getByText('Reverse', { exact: true }),
      'the control flips to Reverse').toBeVisible();

    // THE READ BACK, off a SERVER render (D386). Client-authored DOM survives a
    // write that did nothing; this does not.
    const released = await reloadQueue(authedPage, b.assen);
    await expect(released.getByRole('cell', { name: 'C', exact: true }),
      "the reviewer's status column shows the released code C").toBeVisible();
    await expect(released.getByText('Reverse', { exact: true }),
      'a released report offers Reverse').toBeVisible();
    await expect(released.getByText('Approve', { exact: true }),
      'a released report never offers Approve').toHaveCount(0);
    await expect(released.getByRole('link', { name: 'PDF' }),
      'a released report can be handed out as a PDF').toBeVisible();

    // THE DATABASE -- every S-REV-01 assertion kept, plus the two the trajax
    // handler adds.
    const after = report(db, b.assen);
    expect(after.estatus, 'ci_reports.estatus').toBe('C');
    expect(after.approveDate, 'ci_reports.approve_date is stamped').not.toBe('');
    expect(after.approveDate, 'ci_reports.approve_date is a real timestamp')
      .not.toBe('0000-00-00 00:00:00');
    expect(Number(after.modified), 'ci_reports.modified is stamped').toBeGreaterThan(0);
    // results.php:147 -- the trajax approve resets the fax flag; the tr_action
    // variant at admin_actions.php:1116 does not write this column at all.
    expect(after.faxed, 'ci_reports.faxed is reset by the queue Approve').toBe('0');
    expect(batchStatusOf(db, b.mid), 'ci_batches.batch_status').toBe('completed');

    // The audit row IS the sign-off: ci_reports records no approver (S-REV-06).
    const rows = auditRows(db, 'approve_rs', b.assen);
    expect(rows.length, 'exactly one approve_rs audit row').toBe(1);
    expect(rows[0], 'ci_log_users [user_id, mid, btype, assen]')
      .toEqual([userIdOf(db, tenant.login), b.mid, 'tox', b.assen]);
  });

  /**
   * S-REV-03u -- S-REV-03 AS A USER SEES IT. Same rule: every S-REV-03 database
   * assertion is kept, and `modified` is added because the trajax reverse
   * (`parts/tox/results.php:182`) stamps it where the tr_action variant does not.
   */
  e2e('S-REV-03u the reviewer reopens a released report from the queue', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}uc`);
    expect(db.scalar(`SELECT COUNT(*) FROM ci_reports WHERE patient_id = ${Db.q(b.assen)}`),
      'no report row before the reviewer opens the queue').toBe('0');

    const row = await openQueueFor(authedPage, b.assen);
    await row.getByText('Approve', { exact: true }).click();
    await expect(row.getByText('Reverse', { exact: true })).toBeVisible();

    const releasedRow = await reloadQueue(authedPage, b.assen);
    await expect(releasedRow.getByRole('cell', { name: 'C', exact: true }),
      'released before the reverse, on screen').toBeVisible();
    expect(report(db, b.assen).estatus, 'released before the reverse').toBe('C');
    expect(batchStatusOf(db, b.mid), 'completed before the reverse').toBe('completed');
    const beforeModified = Number(report(db, b.assen).modified);

    await releasedRow.getByText('Reverse', { exact: true }).click();
    await expect(releasedRow.getByText('Approve', { exact: true }),
      'the control flips back to Approve').toBeVisible();

    // THE READ BACK, off a SERVER render (D386).
    const reopened = await reloadQueue(authedPage, b.assen);
    await expect(reopened.getByRole('cell', { name: 'M', exact: true }),
      'the status column reopens to M').toBeVisible();
    await expect(reopened.getByText('Approve', { exact: true }),
      'a reopened report offers Approve again').toBeVisible();
    await expect(reopened.getByRole('link', { name: 'Edit' }),
      'the Edit link returns so the report can be amended').toBeVisible();

    // THE DATABASE -- S-REV-03's assertions, verbatim, plus `modified`.
    const after = report(db, b.assen);
    expect(after.estatus, 'ci_reports.estatus').toBe('M');
    expect(Number(after.modified), 'ci_reports.modified is re-stamped by the reverse')
      .toBeGreaterThanOrEqual(beforeModified);
    expect(batchStatusOf(db, b.mid), 'ci_batches.batch_status').toBe('resulted');

    const rows = auditRows(db, 'reverse_rs', b.assen);
    expect(rows.length, 'exactly one reverse_rs audit row').toBe(1);
    expect(rows[0], 'ci_log_users [user_id, mid, btype, assen]')
      .toEqual([userIdOf(db, tenant.login), b.mid, 'tox', b.assen]);
  });

  e2e('S-REV-04 the amendment cycle drives estatus C -> M -> C and marks final_results=3', async ({
    seedRequisition, tenantRequest, director, db, tenant, runKey,
  }) => {
    const patientLast = `Revsyn${runKey}d`;
    const b = await seedBatch(seedRequisition, db, tenant, patientLast);
    await renderQueue(tenantRequest, tenant, b.batchId, b.assen);
    const r = report(db, b.assen);

    const trail: string[] = [report(db, b.assen).estatus];
    await act(director, { tr_action: 'ci_results_approve', id: r.id });
    trail.push(report(db, b.assen).estatus);
    await act(director, { tr_action: 'ci_results_reverse', id: r.id });
    trail.push(report(db, b.assen).estatus);

    // Amend: the reviewer edits the results and marks the maturity Amended.
    // `3` is what drives the "Amended Results" banner (reports/rejected_default.php:18).
    const edited = await director.post(`${ADMIN_PAGE}&tr_action=ci_save_result_edit&id=${r.id}`, {
      form: { 'info[final_results]': '3' },
    });
    expect(edited.status(), 'results-edit saver').toBe(200);

    await act(director, { tr_action: 'ci_results_approve', id: r.id });
    trail.push(report(db, b.assen).estatus);

    expect(trail, 'ci_reports.estatus traversal draft -> C -> M -> C').toEqual(['', 'C', 'M', 'C']);
    const after = report(db, b.assen);
    expect(after.finalResults, 'ci_reports.final_results (3 = Amended)').toBe('3');
    expect(after.approveDate, 're-release stamps approve_date').not.toBe('');
    expect(batchStatusOf(db, b.mid), 'ci_batches.batch_status after re-release').toBe('completed');
  });

  e2e('S-REV-05 the guarded reverse_report() only moves a released report; the admin reverse forces M', async ({
    seedRequisition, tenantRequest, director, db, tenant, runKey,
  }) => {
    // The catalog phrases this as three reports on ONE assen. The app cannot
    // produce that: ci_reports is keyed on patient_id = assen and every writer
    // upserts on it. The distinguishing behaviour is per-report, so the three
    // states live on three accessions in one lab, which is what
    // `Update ci_reports set estatus='M' where patient_id=... and estatus='C'`
    // (run/db.php:392) actually discriminates on.
    const states = ['', 'C', 'M'] as const;
    const seeded: Record<string, Seeded> = {};
    for (const s of states) {
      const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}e${s || 'd'}`);
      await renderQueue(tenantRequest, tenant, b.batchId, b.assen);
      const r = report(db, b.assen);
      if (s === 'C' || s === 'M') {
        await act(director, { tr_action: 'ci_results_approve', id: r.id });
      }
      if (s === 'M') {
        await act(director, { tr_action: 'ci_results_reverse', id: r.id });
      }
      expect(report(db, b.assen).estatus, `precondition estatus for the '${s}' row`).toBe(s);
      seeded[s] = b;
    }

    // The GUARDED path, invoked exactly as the instrument daemons invoke it
    // (run/*.php -> $db->reverse_report($assen, $btype)). run/db.php refuses to
    // load outside PHP_SAPI 'cli', so this is a container-side CLI call into the
    // app's own class -- not a re-implementation of its SQL.
    const assens = states.map((s) => seeded[s].assen);
    const phpAssens = assens.map((a) => `'${a}'`).join(',');
    const out = dexec('wordpress', 'php', '-r',
      `require "/var/www/html/wp-content/plugins/ci-data-works-arc/run/db.php";` +
      `$db = new DB(array("host"=>"db","user"=>"root","pass"=>"root","name"=>"lab"));` +
      `foreach ([${phpAssens}] as $a) { $db->reverse_report($a, "tox"); }` +
      `echo "ok";`,
    );
    expect(out.trim(), 'reverse_report() ran under the CLI SAPI').toContain('ok');

    expect(report(db, seeded[''].assen).estatus, "guarded reverse leaves a draft ('') alone").toBe('');
    expect(report(db, seeded.C.assen).estatus, 'guarded reverse moves the released report C -> M').toBe('M');
    expect(report(db, seeded.M.assen).estatus, 'guarded reverse leaves an already-reopened report at M').toBe('M');

    // CONTRAST: the admin reverse carries no estatus guard at all
    // (admin_actions.php:1156) and forces M over the untouched draft.
    const draft = report(db, seeded[''].assen);
    await act(director, { tr_action: 'ci_results_reverse', id: draft.id });
    expect(report(db, seeded[''].assen).estatus, 'the admin reverse forces M unconditionally').toBe('M');
  });

  e2e('S-REV-06 the Approve link records no approver on the report row', async ({
    seedRequisition, tenantRequest, director, db, tenant, runKey,
  }) => {
    const patientLast = `Revsyn${runKey}f`;
    const b = await seedBatch(seedRequisition, db, tenant, patientLast);
    await renderQueue(tenantRequest, tenant, b.batchId, b.assen);
    const r = report(db, b.assen);
    expect(r.approvedBy, 'approved_by_ID starts at its default').toBe('0');

    await act(director, { tr_action: 'ci_results_approve', id: r.id });

    const after = report(db, b.assen);
    expect(after.estatus, 'the report did release').toBe('C');
    // The sign-off gap, pinned as a real assertion: approved_by_ID becomes
    // non-zero only when a reviewer picks a name on the results-edit form
    // (parts/tox/results_edit.php:930). Release through the Approve link leaves
    // the report with no recorded approver; the acting user survives only in the
    // ci_log_users approve_rs row.
    expect(after.approvedBy, 'ci_reports.approved_by_ID after the Approve link').toBe('0');
    expect(auditRows(db, 'approve_rs', b.assen).length,
      'the acting user is recorded in the audit log instead').toBe(1);
  });

  e2e('S-REV-07 @nightly bulk reverse leaves the batch and the audit log behind', async ({
    seedRequisition, tenantRequest, director, db, tenant, runKey,
  }) => {
    // Two released reports on one lab, sharing one batch id -- the shape
    // ci_results_reverse_all is invoked with.
    const shared_bid = nextBatchId();
    const set: Seeded[] = [];
    for (const suffix of ['g1', 'g2']) {
      const patientLast = `Revsyn${runKey}${suffix}`;
      const seededRes = await seedRequisition(patientLast, {
        'bat[batch_id]': String(shared_bid),
        'bat[batch_status]': 'resulted',
      });
      expect(seededRes.status, 'the requisition saver answers 200').toBe(200);
      const [row] = db.query(
        `SELECT mid, assen, lab_id FROM ci_batches
          WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
      );
      expect(row, `ci_batches row for ${patientLast}`).toBeTruthy();
      expect(row[2], 'ci_batches.lab_id').toBe(tenant.labId);
      set.push({ mid: row[0], assen: row[1], batchId: shared_bid });
    }
    await renderQueue(tenantRequest, tenant, shared_bid, ...set.map((b) => b.assen));

    const ids = set.map((b) => report(db, b.assen).id);
    for (const id of ids) await act(director, { tr_action: 'ci_results_approve', id });
    for (const b of set) {
      expect(report(db, b.assen).estatus, 'released before the bulk reverse').toBe('C');
      expect(batchStatusOf(db, b.mid), 'completed before the bulk reverse').toBe('completed');
    }

    // D221 -- KEYED, not a global count. `mid IS NULL` is the whole point of this
    // assertion (the orphan row carries no accession to key on), so the key has to
    // come from somewhere else: the row's own id must be newer than the high-water
    // mark taken immediately before the call, AND the row must belong to the
    // persona that made it. Unkeyed, this counted every reverse_rs orphan in the
    // database and a sibling worker's row satisfied the delta below.
    const directorId = Number(userIdOf(db, 'e2e_dir'));
    const orphanHighWater = Number(db.scalar(
      "SELECT COALESCE(MAX(id), 0) FROM ci_log_users WHERE user_action='reverse_rs'",
    ));
    const orphanLogs = () => Number(db.scalar(
      "SELECT COUNT(*) FROM ci_log_users WHERE user_action='reverse_rs' AND mid IS NULL"
      + ` AND user_id = ${directorId} AND id > ${orphanHighWater}`,
    ));

    const res = await act(director, { tr_action: 'ci_results_reverse_all', ids: `${ids.join(',')},` });
    expect(res.status(), 'the bulk reverse renders its own thickbox body').toBe(200);
    expect(await res.text(), 'the action reports success to the operator')
      .toContain('Reversed Successfully');

    // THE DEFECT, pinned. admin_actions.php:1820 writes
    // `select re.* , ... join ci_batches ... from ci_reports re where ...` --
    // the JOIN precedes the FROM, so the statement is a syntax error, $report is
    // null, and both the batch downgrade and the audit write become no-ops.
    // Only the estatus update, which uses $id directly, survives.
    for (const b of set) {
      expect(report(db, b.assen).estatus, 'ci_reports.estatus does move to M').toBe('M');
      expect(batchStatusOf(db, b.mid),
        'ci_batches.batch_status is left at completed -- the downgrade is a no-op').toBe('completed');
      expect(auditRows(db, 'reverse_rs', b.assen).length,
        'no reverse_rs audit row carries this accession').toBe(0);
    }
    // CATALOG CORRECTION. The row is written -- trpdd_log_user() is reached --
    // but with $report null it carries mid=NULL and assen=NULL, so it identifies
    // nothing. And exactly ONE lands for the whole batch, not one per report,
    // because trpdd_log_user()'s in-request dedup key is user+action+mid and
    // every mid is null (functions_user.php:457).
    expect(orphanLogs(),
      'one unattributable reverse_rs row (mid NULL) for the whole bulk call').toBe(1);
  });

  e2e('S-REV-08 @nightly the archival snapshot exists only for a matured report', async ({
    seedRequisition, tenantRequest, director, db, tenant, runKey,
  }) => {
    // functions_file.php:1532 writes the history snapshot only when
    // final_results > 0. The docs name no table that indexes the file, so the
    // DB proxy is final_results and the file itself is read off disk.
    const snapshotDir = (assen: string) => `wp-content/results/html/${assen.toLowerCase()}`;
    const lsSnapshots = (assen: string): string => {
      try {
        return dexec('wordpress', 'sh', '-c',
          `ls -1 /var/www/html/${snapshotDir(assen)} 2>/dev/null || true`).trim();
      } catch { return ''; }
    };

    /**
     * D221, IN THE FILESYSTEM -- and this leg used to be an absolute.
     *
     * `wp-content/results` is a tmpfs that OUTLIVES reset.sh while reset.sh
     * restores the accession counter, and `tr_get_new_assen()` mints from ONE
     * network-wide counter that `replace_assen_by_lab()` only re-prefixes. So
     * which number this test is handed depends on how many accessions every
     * other test in the run minted first, and consecutive runs land on
     * directories an EARLIER run wrote. `toBe('')` therefore reds for somebody
     * else's snapshot: measured, adding two tests to the END of this describe
     * was enough to move this test onto a used number and turn it red, taking
     * the eight serial tests behind it down as "did not run".
     *
     * Both legs are now DELTAS over the same listing, which is also strictly
     * stronger for (b): the old `toMatch(/.../m)` was satisfied by a residual
     * line from a previous run even if this release had written nothing.
     */
    // (a) maturity unset -- released, but no snapshot.
    const unset = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}h`);
    const unsetBefore = lsSnapshots(unset.assen);
    await renderQueue(tenantRequest, tenant, unset.batchId, unset.assen);
    const ru = report(db, unset.assen);
    await act(director, { tr_action: 'ci_results_approve', id: ru.id });

    // (b) matured to Final through the app's own force-final action.
    const matured = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}i`);
    const maturedBefore = lsSnapshots(matured.assen).split('\n').filter(Boolean);
    await renderQueue(tenantRequest, tenant, matured.batchId, matured.assen);
    const rm = report(db, matured.assen);
    const finalcopy = await director.post(`${ADMIN_PAGE}&tr_action=ci_changeto_finalcopy`, {
      form: { ids: rm.id },
    });
    expect(finalcopy.status(), 'force-final action').toBe(200);
    await act(director, { tr_action: 'ci_results_approve', id: rm.id });

    expect(report(db, unset.assen).estatus, '(a) released').toBe('C');
    expect(report(db, unset.assen).finalResults, '(a) ci_reports.final_results').toBe('0');
    expect(report(db, matured.assen).estatus, '(b) released').toBe('C');
    expect(report(db, matured.assen).finalResults, '(b) ci_reports.final_results').toBe('1');

    expect(lsSnapshots(unset.assen),
      'releasing an unmatured report wrote no snapshot -- the directory listing for '
      + `${unset.assen} is exactly what it was before the release`).toBe(unsetBefore);
    const gained = lsSnapshots(matured.assen).split('\n')
      .filter((f) => f && !maturedBefore.includes(f));
    expect(gained, `exactly one new snapshot file for the matured report ${matured.assen}`)
      .toHaveLength(1);
    expect(gained[0], 'the snapshot is named for the moment the report matured')
      .toMatch(/^\d{4}-\d{2}-\d{2}_\d{2}_\d{2}_\d{2}\.html$/);
  });

  // ORDER MATTERS, and only here: S-REV-08 asserts that an unmatured report has
  // NO archival snapshot, and `wp-content/results` is a tmpfs that outlives a
  // run while the accession counter is restored to the baseline by reset.sh.
  // Consecutive runs therefore re-mint the same accessions over a directory
  // that still holds the previous run's snapshots, so any test that mints an
  // extra accession AHEAD of S-REV-08 shifts it onto a used one and reds it.
  // The two tenancy tests go last for that reason.

  /**
   * S-REV-09 -- READ-SIDE TENANCY. The write-side triple (ci_batches.lab_id,
   * .lab, the accession prefix) is asserted on every row this suite creates and
   * `E2E_FAULT=wrong-lab` reds it. None of that says anything about the READ:
   * `renderQueue` above posts `lab_id` as a FORM FIELD, and so do
   * dst.spec.ts:125 and adm.spec.ts:177. The test tells the app which lab to
   * show it, and then asserts what it was shown.
   *
   * MEASURED (ci_load_results.php:70-77). The posted `lab_id` does not narrow
   * the session's lab set -- it REPLACES it:
   *
   *     if ($lab_id > 0)      $where .= " and (lab.lab_ID = {$lab_id} or ...)";
   *     else if (CI_Base_Lab::has_user_labs())
   *                           $where .= " and (ba.lab_id in ({$lab_str}) ...)";
   *
   * The session-derived branch is an `else`. Any value in the field wins, and
   * the two legs below are what separate "the server derives the scope" from
   * "the client declares it".
   */
  e2e('S-REV-09 the tox queue answers a foreign lab_id, not this lab', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}t`);
    await renderQueue(tenantRequest, tenant, b.batchId, b.assen);

    const foreign = allocateTenant(db, SPARE_POOL_INDEX);
    expect(foreign.labId, 'the spare pool lab must not be this spec own lab')
      .not.toBe(tenant.labId);

    // The SAME reviewer and the SAME batch_id, under someone else's lab_id.
    // The specimen disappears -- which is the point: the posted field, not the
    // session, decided what the screen contains.
    const crossed = await renderQueue(tenantRequest, { ...tenant, labId: foreign.labId }, b.batchId);
    expect(crossed, `${b.assen} belongs to ${tenant.prefix}, never to ${foreign.prefix}`)
      .not.toContain(b.assen);
  });

  /**
   * The leak direction, and the reason S-REV-09 above is not reassurance.
   *
   * EXPECTED FAILURE, pinned as one. This lab's reviewer posts the FOREIGN
   * lab's lab_id and batch_id, holds no meta binding them to that lab, and
   * reads that lab's accession off the queue. `e2e.fail()` is inside the body
   * so it annotates this test alone; it goes green the day ci_load_results.php
   * intersects the posted lab_id with CI_Base_Lab::get_user_lab_ids() instead
   * of branching past it -- which is exactly when this file should be told.
   */
  e2e('a reviewer reads another lab queue by changing the posted lab_id', async ({
    tenantRequest, db, tenant, runKey, baseURL,
  }) => {
    const foreign = allocateTenant(db, SPARE_POOL_INDEX);
    expect(foreign.labId, 'the spare pool lab must not be this spec own lab')
      .not.toBe(tenant.labId);

    // Seeded AS the foreign lab persona, so the row is genuinely not ours.
    const fctx = await playwrightRequest.newContext({ baseURL, storageState: foreign.statePath });
    let fb: Seeded;
    try {
      fb = await seedBatch(
        (patientLast, overrides) => seedRequisitionAs(fctx, db, foreign, patientLast, overrides),
        db, foreign, `Revfor${runKey}`,
      );
    } finally {
      await fctx.dispose();
    }

    // ---- THE PIN -----------------------------------------------------------
    // Called HERE, not at the top of the body: the foreign tenant allocation and
    // the foreign-persona seed above are PRECONDITIONS and must be able to go RED
    // on their own. A marker at the top absorbs a broken seed and reports it as
    // the expected failure for ever (D416).

    const leaked = await renderQueue(tenantRequest, foreign, fb.batchId);
    expect(leaked, `${tenant.login} must not read ${foreign.prefix} queue by changing one form field`)
      .not.toContain(fb.assen);
  });

  /**
   * THE ARM OF THE QUEUE'S LAB CHAIN NOTHING IN THIS SUITE HAD EVER REACHED --
   * and the only one a reviewer cannot talk the server out of.
   *
   * `ci_load_results.php:76-79` is the LAST arm of the chain quoted on
   * `postQueue` above: with neither `ref_lab_id` nor `lab_id` in the request,
   * the scope comes from the session --
   * `and (ba.lab_id in (<_lab_ids>) or ba.ref_lab_ID in (<_lab_ids>))`. That is
   * the tenancy boundary of the review queue for every request that does not
   * declare its own lab, and deleting the line changed no test in the suite,
   * because `renderQueue` (and dst, and adm) always post `lab_id` and take the
   * middle arm at `:73` instead.
   *
   * WHY IT COULD NOT BE SEEN, precisely. It is not that `:78` never ran -- it
   * runs for any request without `lab_id`. It is that no FOREIGN-LAB ROW HAS
   * EVER EXISTED INSIDE A BATCH A TEST RENDERED, so widening the clause to
   * every lab selected exactly the same rows. The fixture was monotonous along
   * the lab axis WITHIN a batch_id; the discriminator is a twin that differs on
   * that axis and on nothing else.
   *
   * THE TWIN. Same `batch_id`, same clinic, same provider, same batch_status,
   * same `ref_lab_ID` -- asserted column by column below -- and seeded by the
   * OTHER lab's own persona through the app's own saver, so it is genuinely
   * that lab's specimen and not a row this test relabelled.
   *
   * PRESENCE THEN ABSENCE, both legs. The scoped render asserts this lab's
   * accession IS on the queue before asserting the twin is not, so an empty or
   * fatally-broken fragment cannot satisfy it; and the widening leg re-renders
   * the same batch under the twin's own lab_id and shows the twin appearing,
   * so the absence above cannot be the twin being unrenderable for some reason
   * that has nothing to do with lab scope.
   *
   * NOT A DUPLICATE OF S-REV-09 ABOVE. That test posts a lab_id and shows the
   * posted field deciding the answer -- the `:73` arm. This one posts no lab_id
   * at all, which is the arm S-REV-09 proves the app will happily skip.
   */
  e2e('the tox queue scopes to the reviewer own lab when the request names none', async ({
    seedRequisition, tenantRequest, db, tenant, runKey, baseURL,
  }) => {
    const foreign = allocateTenant(db, SPARE_POOL_INDEX);
    expect(foreign.labId, 'the spare pool lab must not be this spec own lab')
      .not.toBe(tenant.labId);

    // ONE batch id for both specimens: the queue narrows to `ba.batch_id` before
    // anything else (`:68`), so two rows in different batches could never
    // discriminate a lab clause.
    const batchId = nextBatchId();
    const mine = await seedBatch(
      seedRequisition, db, tenant, `Revown${runKey}`, 'resulted', { batchId },
    );

    const fctx = await playwrightRequest.newContext({ baseURL, storageState: foreign.statePath });
    let theirs: Seeded;
    try {
      theirs = await seedBatch(
        (patientLast, overrides) => seedRequisitionAs(fctx, db, foreign, patientLast, overrides),
        db, foreign, `Revoth${runKey}`, 'resulted', { batchId },
      );
    } finally {
      await fctx.dispose();
    }

    // THE TWIN IS A TWIN. Read from the rows, not assumed from the payloads.
    const scopedCols = 'batch_id, cli_ID, doc_ID, batch_status, ref_lab_ID';
    const [mineRow] = db.query(
      `SELECT ${scopedCols} FROM ci_batches WHERE mid = ${Db.q(mine.mid)}`);
    const [theirsRow] = db.query(
      `SELECT ${scopedCols} FROM ci_batches WHERE mid = ${Db.q(theirs.mid)}`);
    expect(
      theirsRow,
      `${theirs.assen} must match ${mine.assen} on batch_id, clinic, provider, status and `
      + 'reference lab -- otherwise its absence below could be about any of them',
    ).toEqual(mineRow);
    expect(
      db.scalar(`SELECT lab_id FROM ci_batches WHERE mid = ${Db.q(theirs.mid)}`),
      `and sit in ${foreign.prefix} -- the ONE column that differs`,
    ).toBe(foreign.labId);

    // THE ARM UNDER TEST: batch_id and nothing else. The only thing that can
    // narrow this render is the reviewer's own `_lab_ids`.
    const own = await postQueue(tenantRequest, { batch_id: String(batchId) });
    // THE WIDENED READ, taken before anything is asserted: the same reviewer,
    // the same batch, the twin's lab named in the form. Both bodies therefore
    // come off one database state, and the guard below can refuse to assert
    // absence until it has seen a presence in each.
    const widened = await postQueue(tenantRequest, {
      batch_id: String(batchId), lab_id: foreign.labId, ref_lab_id: '0',
    });

    // WHY THE GUARD AND NOT THREE HAND-WRITTEN `expect`s. Those three were
    // correct here, and `expectScopeExcludes` (fixtures/assert-domain.ts) makes
    // exactly the same three claims -- the widened read shows the twin, the
    // scoped read shows the reviewer's own specimen, the scoped read does NOT
    // show the twin -- plus the marker-aliasing check none of them had
    // ('LAL1234' contains 'LAL123', so adjacent accessions make `toContain`
    // undecidable). It is called from here because a guard with no production
    // caller is a green light nobody looks behind: this is what keeps
    // specs/fixtures.spec.ts measuring something the suite depends on (D432).
    expectScopeExcludes({
      arm: 'ci_load_results.php:76-79 -- and (ba.lab_id in (<_lab_ids>) '
        + 'or ba.ref_lab_ID in (<_lab_ids>))',
      observed: `${tenant.login}'s tox review queue for batch ${batchId}, opened without a lab_id`,
      widened,
      scoped: own,
      own: mine.assen,
      foreign: theirs.assen,
    });
  });

  /**
   * THE FIRST ARM: the reference-lab view, `ci_load_results.php:70-71`.
   *
   * A specimen sent out to a reference lab carries `ci_batches.ref_lab_ID`, and
   * the queue has a view for exactly that -- post `ref_lab_id` and the whole
   * listing narrows to the work that belongs to that reference lab, ahead of
   * both the posted `lab_id` and the session binding. Every render in the suite
   * posts `ref_lab_id=0`, so this arm was never entered, and deleting `:71`
   * changed no test: with the line gone the branch adds NO predicate at all and
   * the reference-lab view silently becomes a view of the whole batch.
   *
   * The discriminator is the same shape as the test above, one axis over: two
   * specimens in THIS lab, one batch, same clinic, same provider, same status,
   * differing only in `ref_lab_ID` -- a value the requisition form itself posts,
   * so both rows are made the way the app makes them.
   *
   * THE BASELINE RENDER IS THE PRESENCE HALF and it is deliberately the
   * ORDINARY queue: `renderQueue` asserts both accessions are on it, which
   * proves the out-of-scope row is in this batch and renderable before the
   * reference-lab view is asked to leave it out.
   */
  e2e('the reference-lab view answers for the reference lab it was asked about', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }) => {
    const refLab = allocateTenant(db, SPARE_POOL_INDEX);
    expect(refLab.labId, 'the reference lab must not be this spec own lab')
      .not.toBe(tenant.labId);

    const batchId = nextBatchId();
    const sentOut = await seedBatch(
      seedRequisition, db, tenant, `Revref${runKey}`, 'resulted',
      { batchId, overrides: { 'bat[ref_lab_ID]': refLab.labId } },
    );
    const kept = await seedBatch(
      seedRequisition, db, tenant, `Revkep${runKey}`, 'resulted', { batchId },
    );

    // The form's reference lab actually landed on the row, and the twin's did
    // not -- the one column these two specimens differ on.
    expect(
      db.scalar(`SELECT ref_lab_ID FROM ci_batches WHERE mid = ${Db.q(sentOut.mid)}`),
      `${sentOut.assen} was sent out to reference lab ${refLab.prefix}`,
    ).toBe(refLab.labId);
    expect(
      db.scalar(`SELECT ref_lab_ID FROM ci_batches WHERE mid = ${Db.q(kept.mid)}`),
      `${kept.assen} was never sent out -- ci_batches.ref_lab_ID`,
    ).toBe('0');
    expect(
      db.query(
        `SELECT cli_ID, doc_ID, batch_status, lab_id FROM ci_batches
          WHERE mid IN (${Db.q(sentOut.mid)}, ${Db.q(kept.mid)})`).length,
      'both specimens exist',
    ).toBe(2);

    // BASELINE / PRESENCE: the reviewer's ordinary queue for this batch carries
    // BOTH accessions. renderQueue asserts each one it is handed.
    await renderQueue(tenantRequest, tenant, batchId, sentOut.assen, kept.assen);

    // THE ARM UNDER TEST.
    const refView = await postQueue(tenantRequest, {
      batch_id: String(batchId), ref_lab_id: refLab.labId,
    });
    expect(
      refView,
      `the ${refLab.prefix} reference-lab view must still show ${sentOut.assen}, the specimen `
      + 'that was sent there',
    ).toContain(sentOut.assen);
    expect(
      refView,
      `${kept.assen} was never sent to ${refLab.prefix} and must not appear in its `
      + 'reference-lab view (ci_load_results.php:70-71)',
    ).not.toContain(kept.assen);
  });

  // ==========================================================================
  // WAVE 6 -- FIVE PINS ON THE RELEASE SCREEN'S BATCH-ACTION ROW AND ITS
  // AFTER-RELEASE SURFACES.
  //
  // Every test below is an `e2e.fail()` EXPECTED FAILURE: it asserts what the
  // product SHOULD do, fails today because the product does something else, and
  // goes RED the day the defect it names is fixed. None of them asserts the
  // defect as if it were correct.
  //
  // THEY GO AFTER S-REV-08 for the reason the note above it gives -- they mint
  // accessions and must not shift the ones it depends on. They add no snapshot
  // of their own to that tmpfs either: functions_file.php:1532 writes one only
  // when `final_results > 0`, and none of these matures a report.
  //
  // A `fail()` BODY SWALLOWS ITS OWN PRECONDITIONS (acc.spec.ts:1421), so in
  // every pin below the marker is called AFTER the seed, the preconditions and
  // the positive controls, immediately before the attempt it pins (D416). Those
  // lines therefore DO carry detection power: if the seed, the queue render, the
  // Approve or the `C` read-back breaks, the test goes RED here rather than
  // being absorbed. S-REV-01u/S-REV-03u cover the same fixture as non-fail()
  // tests, so a broken fixture reds there loudly as well.

  /** What the reviewer types into the Hold Reason box. */
  const HOLD_REASON = 'E2E: clinic called, specimen query';

  /** `.bs_hold td { background:#e9e8c6 }` -- css/admin.min.css. */
  const HOLD_COLOUR = 'rgb(233, 232, 198)';

  /** The reviewer's action row under an expanded batch (`td.bactions`). */
  const batchActions = (page: Page) => page.locator('td.bactions');

  /**
   * S-REV-10u -- THE HOLD THAT NEVER COMES OFF.
   *
   * A reviewer parks a specimen with a reason ("Hold") and later releases it
   * back to the bench ("Remove Hold"). The specimen must come back at the
   * status it was holding -- `resulted` -- because that status is the whole of
   * what puts it in front of a reviewer again.
   *
   * MEASURED DEFECT, `parts/tox/results.php:84,86` at the pin:
   *
   *     $hold_status = $wpdb->get_var("... where mid={$re->$mid} ...") ?: '';
   *     $wpdb->update($table_batch, ['batch_status' => $hold_status], ...);
   *     $wpdb->delete($table_batch . '_meta', ['mid' => $re->$mid, ...]);
   *
   * `$re->$mid` is a VARIABLE VARIABLE and `$mid` is unset in that scope, so the
   * property name is the empty string: the remembered status comes back empty,
   * `?: ''` launders that into a blank, and the specimen's status is ERASED
   * instead of restored. The meta row that remembers it is not deleted either
   * (same expression), so a second cycle cannot self-correct. On screen the row
   * merely loses its hold colour and reads as an ordinary specimen -- which is
   * the lie, and why the DB assertion and the rendered one are both here.
   *
   * `hold_rs` / `removehold_rs` are still written, so the audit log says the
   * specimen was handled correctly while its status says nothing at all.
   */
  e2e('S-REV-10u taking a hold off a specimen gives back the status it was holding', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}uh`);
    const row = await openQueueFor(authedPage, b.assen);
    const actions = batchActions(authedPage);
    await expect(actions, 'the expanded batch offers the reviewer one action row')
      .toHaveCount(1);
    // The positive control for the closing assertion (D397): the row IS drawn
    // `bs_resulted` before anyone touches it, so `bs_resulted` at the end is a
    // class this listing can actually emit rather than one it never emits.
    await expect(row, 'the specimen starts at Resulted -- the status the hold owes it back')
      .toHaveClass(/bs_resulted/);

    // `a.hold_btn` is an hrefless `<a>` -- ARIA role `generic`, so
    // getByRole('link'|'button') resolves 0 (D383 clause 2). `exact` is what
    // keeps this off "Remove Hold" two controls along.
    await actions.getByText('Hold', { exact: true }).click();

    // The gate is a jQuery-UI MODAL, not a native confirm (js/admin_result.js:30,
    // D381 clause 4): no dialog handler can see it and Playwright would sail
    // past it, so it is driven as the widget it is. jQuery UI puts the title on
    // the wrapper via aria-labelledby, which is where the accessible name below
    // comes from.
    const dialog = authedPage.getByRole('dialog', { name: 'Hold Reason' });
    await expect(dialog, 'the reviewer is asked why before a specimen is parked')
      .toBeVisible();
    await dialog.getByRole('combobox').selectOption({ label: 'Lab Issue' });
    await dialog.locator('#reason_hold').fill(HOLD_REASON);
    await dialog.getByRole('button', { name: 'Submit', exact: true }).click();

    // Client-authored, and used ONLY to wait for the XHR to land (D386): the
    // success callback adds `bs_hold` in the browser whatever the server did.
    await expect(row, 'the row picks up the hold flag').toHaveClass(/bs_hold/);

    // THE READ BACK, off a SERVER render. `$classes .= ' bs_' . $row->batch_status`
    // (ci_load_results.php:531), so this class IS the stored status rendered.
    const held = await reloadQueue(authedPage, b.assen);
    await expect(held, 'the reloaded queue draws the specimen as held')
      .toHaveClass(/bs_hold/);
    await expect(held.locator('td').first(),
      'and shows the reviewer the hold colour the bench recognises')
      .toHaveCSS('background-color', HOLD_COLOUR);

    expect(batchStatusOf(db, b.mid), 'ci_batches.batch_status while held').toBe('hold');
    expect(
      db.scalar(`SELECT meta_value FROM ci_batches_meta
                  WHERE mid = ${Number(b.mid)} AND meta_key = 'hold_batch_status'`),
      'the app writes down the status it owes the specimen back',
    ).toBe('resulted');
    const holdLog = db.query(
      `SELECT user_id, data FROM ci_log_users
        WHERE user_action = 'hold_rs' AND assen = ${Db.q(b.assen)}`,
    );
    expect(holdLog.length, 'exactly one hold_rs audit row').toBe(1);
    expect(holdLog[0], 'the hold is attributable and carries the typed reason')
      .toEqual([userIdOf(db, tenant.login), HOLD_REASON]);

    // ---- THE PIN -----------------------------------------------------------
    // Called HERE, not at the top of the body: everything above is a seed, a
    // precondition or a positive control and must be able to go RED on its own.
    // A marker at the top of the body absorbs a broken seed, a refused gesture or
    // a dead control and reports each of them as the expected failure for as long
    // as the file lives (D416).

    // No confirm on this control at the pin (js/admin_result.js:74-113 fires the
    // XHR straight away). The handler is registered anyway and BEFORE the click
    // (D381): Playwright auto-DISMISSES, so the day a confirm is added the
    // gesture would otherwise become a silent no-op.
    authedPage.on('dialog', (d) => d.accept());
    await actions.getByText('Remove Hold', { exact: true }).click();
    await expect(row, 'the row drops the hold flag').not.toHaveClass(/bs_hold/);

    // THE PIN. Both halves, because the screen and the row disagree today: the
    // row looks like an ordinary specimen while its status has been blanked.
    const back = await reloadQueue(authedPage, b.assen);
    expect(batchStatusOf(db, b.mid),
      'Remove Hold must hand the specimen back the status it was holding. A blank '
      + 'status is a specimen no bench queue and no reviewer worklist selects on -- '
      + 'the hold is over and the result silently stops being anybody\'s work',
    ).toBe('resulted');
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_batches_meta
                  WHERE mid = ${Number(b.mid)} AND meta_key = 'hold_batch_status'`),
      'and must forget the remembered status, so a second hold can remember a new one',
    ).toBe('0');
    await expect(back, 'the reviewer sees the specimen back at Resulted')
      .toHaveClass(/bs_resulted/);
  });

  /**
   * S-REV-11u -- A RELEASED REPORT, REWRITTEN IN PLACE.
   *
   * The queue hides Edit once a report is released (`ci_load_results.php:644`
   * puts `style="display:none"` on `span.editlink` when `estatus == 'C'`), and
   * the app's own way to change a released report is the amendment cycle
   * S-REV-04 drives: Reverse, edit, mark Amended, re-release.
   *
   * MEASURED DEFECT. That same line reads `$row->estatus == 'C' && !$admin_view`,
   * and `$admin_view` is `isset($_REQUEST['view'])` (`:16`) -- ANY caller who
   * appends `&view=1` to the queue URL gets the Edit control back on a released
   * report. It is not decoration either: the listing's XHR posts to
   * `location.href` (`parts/tox/results_list.php:419`, no `url:` key), so the
   * flag rides along into the fragment. Following that link and pressing the
   * form's plain **Submit** -- not Approve -- rewrites the stored analyte while
   * `estatus`, `approve_date`, `final_results` and `faxed` all stay exactly as
   * the release left them. The report keeps its `C`, keeps its release time,
   * carries no amendment marker, and nothing re-delivers it: the PDF the clinic
   * already holds and the row the LIS now shows are two different results.
   *
   * THE PIN is the value itself, on the bench's own screen and in the table
   * behind it: a released result must not change while it is released.
   */
  e2e('S-REV-11u a released result cannot be rewritten behind the release', async ({
    seedRequisition, tenantRequest, authedPage, db, tenant, runKey,
  }) => {
    // The validity grid the results-edit screen renders, read live so a
    // dictionary change cannot leave this asserting a row the app stopped
    // drawing (same source as S-TST-04u).
    const [validity] = db.query(
      `SELECT u_ID, u_name FROM ci_urine_validity
        WHERE u_status = 1 AND u_showinreq = 0 ORDER BY u_position ASC, u_ID ASC`,
    );
    // OUTSIDE the marker (D416), so a dictionary with no such row reds this test
    // for the reason it actually broke instead of being absorbed as the pin.
    if (!validity) throw new Error('ci_urine_validity holds no active u_showinreq=0 row');
    const [uId, uName] = validity;
    const conc = (page: Page) =>
      page.locator(`input[name="data_specimen[${Number(uId)}][conc]"]`);

    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}uv`);
    const row = await openQueueFor(authedPage, b.assen);
    const r = report(db, b.assen);
    const editUrl = `${ADMIN_PAGE}&act=edit&id=${r.id}`;

    // THE BENCH RESULT, through the app's own saver. A PRECONDITION, not the
    // flow under test -- S-TST-04u drives this same screen as a user and is not
    // a fail() test, so the UI path is covered where it can actually red.
    const benched = await tenantRequest.post(editUrl, {
      form: {
        tr_action: 'ci_save_result_edit',
        btype: 'tox',
        id: r.id,
        'info[instrument]': '',
        [`data_specimen[${Number(uId)}][conc]`]: '1.23',
        [`data_specimen[${Number(uId)}][outcome]`]: 'POS',
        [`data_specimen[${Number(uId)}][comments]`]: '',
      },
    });
    expect(benched.status(), 'the results-edit saver answers 200').toBe(200);

    // THE RELEASE, as the reviewer performs it.
    const draft = await reloadQueue(authedPage, b.assen);
    // The positive control for the released case below (D397): the SAME locator
    // matches while the report is still a draft, so the count of 0 after the
    // release is the screen taking the control away and not a locator that
    // never matches anything.
    await expect(draft.getByRole('link', { name: 'Edit' }),
      'a draft is editable, and the reviewer is offered the control').toHaveCount(1);
    await draft.getByText('Approve', { exact: true }).click();
    const released = await reloadQueue(authedPage, b.assen);
    await expect(released.getByRole('cell', { name: 'C', exact: true }),
      'the queue shows the report released').toBeVisible();
    expect(report(db, b.assen).estatus, 'ci_reports.estatus').toBe('C');
    // The control the screen takes away on release. A hidden `span.editlink` is
    // out of the accessibility tree, so this resolves 0 rather than 1-invisible.
    await expect(released.getByRole('link', { name: 'Edit' }),
      'a released report is offered no Edit control').toHaveCount(0);

    // ---- THE PIN -----------------------------------------------------------
    // Called HERE, not at the top of the body: everything above is a seed, a
    // precondition or a positive control and must be able to go RED on its own.
    // A marker at the top of the body absorbs a broken seed, a refused gesture or
    // a dead control and reports each of them as the expected failure for as long
    // as the file lives (D416).

    // ...and the same screen with `&view=1`, which is the whole of the defect.
    // Reached by URL because that is how a caller reaches it -- the search box
    // does not carry the flag.
    await authedPage.goto(`${ADMIN_PAGE}&view=1&s=${b.assen}`);
    const shown = authedPage.locator('tr.childp').filter({ hasText: b.assen });
    await expect(shown, 'the filtered queue still shows the released specimen')
      .toHaveCount(1);
    // NO PRE-CHECK, and no action timeout, DELIBERATELY. Under the fix this test names
    // (dropping `&& !$admin_view` at ci_load_results.php:644) the admin view stops offering
    // Edit and this click hangs until the TEST timeout -- which is the one failure mode a
    // fail() body reports as unexpected (RED). MEASURED: an expect(...).toHaveCount(1)
    // guard, a plain throw, and a locator-level `{ timeout }` TimeoutError are ALL absorbed
    // as the expected failure, so every "friendlier message" here converts a correct RED
    // into a green run over a fixed product. The 60 s wait is the price of the signal.
    await shown.getByRole('link', { name: 'Edit' }).click();
    await expect(authedPage.getByRole('heading', { name: 'SPECIMEN VALIDITY TEST' }),
      'the released report opens on the editable results form').toBeVisible();

    await conc(authedPage).fill('7.77');
    await authedPage.getByRole('button', { name: 'Submit', exact: true }).click();

    // THE READ BACK, off a fresh GET (D386 -- reload() would re-POST the form).
    await authedPage.goto(editUrl);
    const savedRow = authedPage.locator('div.specimen_row').filter({ hasText: String(uName) });
    await expect(savedRow, `exactly one grid row for ${uName}`).toHaveCount(1);

    // THE PIN, on screen first: the bench must still be reading the result the
    // reviewer signed.
    await expect(savedRow.locator(`input[name="data_specimen[${Number(uId)}][conc]"]`),
      'the value that was released is the value the screen still shows -- a released '
      + 'report may only change through the amendment cycle (Reverse, edit, mark '
      + 'Amended, re-release), never under the release',
    ).toHaveValue('1.23');
    expect(
      db.scalar(`SELECT conc FROM ci_result_test_speciments
                  WHERE mid = ${Number(b.mid)} AND testid = ${Number(uId)}`),
      'ci_result_test_speciments.conc -- the stored result behind that screen',
    ).toBe('1.23');
  });

  /**
   * S-REV-12u -- APPROVE ALL, TWICE.
   *
   * The batch-action row offers "Approve All" for the whole lab's slice of a
   * batch. It is the only control there that carries an href, and it rides the
   * ungated `tr_action` dispatcher: `tradmin_action_ci_results_approve_all`
   * (`inc/admin_actions.php:1850`).
   *
   * MEASURED DEFECT. The single-row Approve refuses a report that is already
   * released -- `if ($report['estatus'] != 'C')` at `parts/tox/results.php:139`
   * -- and the screen swaps its control to Reverse to say so. Approve All has
   * NO such test: it re-writes `estatus`, restamps `approve_date` to now and
   * writes a SECOND `approve_rs` audit row over a report nobody re-reviewed.
   * The control is offered on a fully released batch too, because it is
   * rendered on `$can_approve` alone (`parts/tox/results_list.php:359`).
   *
   * Both consequences are clinical, not cosmetic: `approve_date` is the release
   * time the report and every downstream consumer carry, and `approve_rs` is
   * the ONLY place the releasing user is recorded at all (S-REV-06).
   */
  e2e('S-REV-12u Approve All does not re-release a report that is already released', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}ua2`);
    await openQueueFor(authedPage, b.assen);
    const approveAll = batchActions(authedPage).getByRole('link', { name: /Approve\s*All/ });
    await expect(approveAll, 'the batch offers the reviewer Approve All').toHaveCount(1);

    // D381 -- registered BEFORE the gesture. `confirm('Do you want Approve all?')`
    // is native here (results_list.php:443) and Playwright auto-dismisses, so
    // without this the click is a silent no-op and the whole test reads as an
    // empty batch. Both clicks below need it, hence `on` rather than `once`.
    authedPage.on('dialog', (d) => d.accept());
    await Promise.all([
      authedPage.waitForURL(/[?&]m=1/),
      approveAll.click(),
    ]);

    const released = await gotoQueueFor(authedPage, b.assen);
    await expect(released.getByRole('cell', { name: 'C', exact: true }),
      'the batch action released the report').toBeVisible();
    const first = report(db, b.assen);
    expect(first.estatus, 'ci_reports.estatus').toBe('C');
    expect(first.approveDate, 'ci_reports.approve_date is stamped').not.toBe('');
    expect(auditRows(db, 'approve_rs', b.assen).length,
      'one release, one approve_rs row').toBe(1);

    // ---- THE PIN -----------------------------------------------------------
    // Called HERE, not at the top of the body: everything above is a seed, a
    // precondition or a positive control and must be able to go RED on its own.
    // A marker at the top of the body absorbs a broken seed, a refused gesture or
    // a dead control and reports each of them as the expected failure for as long
    // as the file lives (D416).

    // ...and the reviewer, or anyone who reloads and clicks again, fires it a
    // second time over the released report.
    await Promise.all([
      authedPage.waitForURL(/[?&]m=1/),
      batchActions(authedPage).getByRole('link', { name: /Approve\s*All/ }).click(),
    ]);
    const after = await gotoQueueFor(authedPage, b.assen);
    await expect(after.getByRole('cell', { name: 'C', exact: true }),
      'the report is still released').toBeVisible();

    // THE PIN. `approve_rs` is the report's only sign-off record, so a second
    // row is a second signature nobody gave.
    expect(auditRows(db, 'approve_rs', b.assen).length,
      'Approve All must skip a report that already reads C, exactly as the row\'s own '
      + 'Approve does -- a second approve_rs row is a release event that never happened',
    ).toBe(1);
    expect(report(db, b.assen).approveDate,
      'and the release time the report carries must not move under it',
    ).toBe(first.approveDate);
  });

  /**
   * S-REV-13u -- BULK DELETE EATS A RELEASED REPORT.
   *
   * The app's own rule is in its own markup: the per-row Delete is rendered
   * only `if ($row->estatus != 'C' && ...)` (`ci_load_results.php:648`), so a
   * released report is not deletable from this screen.
   *
   * MEASURED DEFECT. The batch row's bulk Delete (`a.delete_selected`) collects
   * the ticked rows and posts `trajax act=delete_selected`, whose handler
   * (`parts/tox/results.php:215-236`) reads no `estatus` at all: it deletes the
   * `ci_reports` row and every `ci_results` analyte behind it, released or not.
   * What it leaves is worse than the deletion -- `ci_batches` keeps
   * `batch_status='completed'` and gets `is_saved=2`, so the specimen still
   * claims a finished, released report that no longer exists anywhere.
   *
   * A `delete_rs` audit row is written, so the log records that a patient's
   * released result was destroyed -- it just cannot bring it back.
   */
  e2e('S-REV-13u the bulk Delete does not destroy a released patient result', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}ud`);
    const row = await openQueueFor(authedPage, b.assen);
    await row.getByText('Approve', { exact: true }).click();
    const released = await reloadQueue(authedPage, b.assen);
    await expect(released.getByRole('cell', { name: 'C', exact: true }),
      'the report is released before anyone reaches for Delete').toBeVisible();
    expect(report(db, b.assen).estatus, 'ci_reports.estatus').toBe('C');
    // The app's own rule, on screen: the per-row Delete is gone once it reads C.
    await expect(released.getByText('Delete', { exact: true }),
      'the released row itself offers no Delete').toHaveCount(0);

    // The tick box the listing script prepends into `td.cid`
    // (results_list.php:429) -- it carries the report id the bulk handler acts on.
    await released.locator('input[type="checkbox"]').check();
    // The control for the count of 0 above (D397): the identical locator finds
    // the BATCH row's Delete, so the row's own missing one is the app's rule and
    // not a locator that matches nothing.
    const bulkDelete = batchActions(authedPage).getByText('Delete', { exact: true });
    await expect(bulkDelete, 'the batch action row still offers a bulk Delete')
      .toHaveCount(1);
    // ---- THE PIN -----------------------------------------------------------
    // Called HERE, not at the top of the body: everything above is a seed, a
    // precondition or a positive control and must be able to go RED on its own.
    // A marker at the top of the body absorbs a broken seed, a refused gesture or
    // a dead control and reports each of them as the expected failure for as long
    // as the file lives (D416).

    authedPage.on('dialog', (d) => d.accept());
    // Serialised on the XHR, NEVER on the DOM the handler writes. results_list.php:471-476
    // removes the <tr> only for ids echoed back in `json.ids`, so a handler that REFUSES
    // (the fixed build) returns `ids: []`, the row stays, and a `toHaveCount(0)` wait here
    // would fail -- inside a fail() body that failure is absorbed as the expected failure
    // and the pin below is never reached. Measured: with the estatus guard added to
    // delete_selected the test reported green. The response wait is fix-invariant.
    await Promise.all([
      authedPage.waitForResponse((r) => r.request().method() === 'POST'
        && r.url().includes('page=tox_results')),
      bulkDelete.click(),
    ]);

    // THE PIN, off a server render and off the tables behind it.
    const back = await gotoQueueFor(authedPage, b.assen);
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_reports WHERE patient_id = ${Db.q(b.assen)}`),
      'a released report is a signed-out patient result: the bulk Delete must refuse it '
      + 'exactly as the row\'s own Delete control does, and this batch must not be left '
      + 'reading `completed` over a report that no longer exists',
    ).toBe('1');
    await expect(back, 'the reviewer can still see the released specimen in the queue')
      .toHaveCount(1);
  });

  /**
   * S-REV-14u -- THE PDF OF A RETRACTED REPORT.
   *
   * Reverse is the reviewer's retraction: the report goes back to `M`, the
   * batch drops out of `completed`, and the queue TAKES THE PDF CONTROL AWAY
   * (`span.pdflink` gets `style="display:none"` whenever `estatus != 'C'`,
   * `ci_load_results.php:639`).
   *
   * MEASURED DEFECT, and it is D250 in one line: only the control is taken
   * away. `tradmin_action_ci_results_pdf` (`inc/admin_actions.php:1188`) reads
   * `id` and renders, and asks nothing about `estatus`, so the URL the row was
   * offering one screen earlier still returns the retracted report as a PDF --
   * the document a reviewer has just declared must not be relied on. It is
   * logged (`download_rs`) and never refused.
   */
  e2e('S-REV-14u a retracted report stops handing out its PDF', async ({
    seedRequisition, authedPage, db, tenant, runKey,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}up`);
    const row = await openQueueFor(authedPage, b.assen);
    await row.getByText('Approve', { exact: true }).click();
    const released = await reloadQueue(authedPage, b.assen);
    const pdfLink = released.getByRole('link', { name: 'PDF' });
    await expect(pdfLink, 'a released report can be handed out as a PDF').toHaveCount(1);
    const href = await pdfLink.getAttribute('href');
    // A control with no href is a fixture problem, not the defect, and it is OUTSIDE
    // the marker (D416) so it reds as itself. Inside a fail() body a plain `throw` is
    // absorbed exactly as an expect failure is -- only a TEST-level timeout, or the
    // body passing, reports RED -- which is why the marker is placed below.
    if (!href) throw new Error('the released row offered a PDF control with no href');

    // The control, exercised as the reviewer's own browser does -- `page.request`
    // carries this persona's cookies.
    const magic = async (res: APIResponse) =>
      (await res.body()).subarray(0, 4).toString('latin1');
    expect(await magic(await authedPage.request.get(href)),
      'the released report downloads as a PDF').toBe('%PDF');

    await released.getByText('Reverse', { exact: true }).click();
    const reopened = await reloadQueue(authedPage, b.assen);
    await expect(reopened.getByRole('cell', { name: 'M', exact: true }),
      'the report is retracted').toBeVisible();
    expect(report(db, b.assen).estatus, 'ci_reports.estatus').toBe('M');
    await expect(reopened.getByRole('link', { name: 'PDF' }),
      'the queue takes the PDF control away from a retracted report').toHaveCount(0);

    // ---- THE PIN: the URL the screen was offering a moment ago. --------------
    // Called HERE, not at the top of the body: the seed, the release, the first
    // %PDF fetch and the retraction above are all preconditions and must be able
    // to go RED on their own (D416).

    expect(await magic(await authedPage.request.get(href)),
      'a report the reviewer has retracted must not still be downloadable at the URL the '
      + 'queue was offering: hiding the control leaves the document reachable by anyone '
      + 'who kept the link, and a retracted result is one the lab has said not to use',
    ).not.toBe('%PDF');
  });

  // THE TWO TESTS BELOW ARE DECLARED LAST, AND THE POSITION IS LOAD-BEARING FOR
  // A SIBLING. S-REV-08 above asserts that an unmatured report has NO archival
  // snapshot, and it is fragile across consecutive runs: `wp-content/results`
  // is a tmpfs that outlives a run while reset.sh restores the accession
  // counter (the note after S-REV-08 says so). `tr_get_new_assen()` mints from
  // ONE counter for the whole network and `replace_assen_by_lab()` only swaps
  // the 3-char prefix afterwards, so an accession minted in ANY lab -- S-REV-15u
  // seeds into the clinician-facing lab, not this one -- still shifts S-REV-08
  // onto a number some earlier run already wrote a snapshot under.
  //
  // MEASURED, not reasoned: with these two in a describe of their own, running
  // in parallel with this one, S-REV-08 reddened on "no snapshot for the
  // unmatured report" and took the eight serial tests behind it down as "did
  // not run"; the same file with `--grep-invert` over the two was 21/21 green.
  // Declared here and last, the serial describe fixes the order and S-REV-08
  // keeps the number it has always had.

  /**
   * S-REV-16u -- THE POSITIVE THAT IS NEVER FLAGGED POSITIVE.
   *
   * `ci_reports.has_pos` is the positive flag. It bars the reviewer's status
   * cell in red (`css/admin_new.css:2215` -- `.report_tbl .has_pos td.statuscol
   * {border-left:12px solid #da1414 !important}`, which is loaded after
   * `admin.css:1445` and outranks it) and it is the entire predicate behind the
   * clinician's
   * "Pos Results Only" filter (`parts/tox/test_results.php:86-88`,
   * `ci_load_test_results.php:63-65`: `and re.has_pos = 1`).
   *
   * MEASURED DEFECT -- A ONE-SHOT COMPUTATION. `inc/actions/ci_load_results.php:505`
   * recomputes the flag only `if (is_null($row->has_pos) || $row->has_pos === '')`.
   * The FIRST render of the review queue creates the report row (`:412-431`,
   * D034) and, in the same pass, writes `has_pos = 0` because no analyte exists
   * yet (`:508-520`). From that moment the column is `0` and no code path in the
   * app ever recomputes it:
   *   - the result API (`api/ci_result_api.php:79-96`) writes `ci_results` and
   *     never touches `ci_reports.has_pos`;
   *   - `inc/actions/ci_save_result_edit.php` -- the bench's own results form --
   *     DOES compute it (`:171`, `:267-272`, `$has_pos = 1` on POS/POL/POM/POH/
   *     PVH/PVL) and then never writes it anywhere. `grep -n has_pos` over that
   *     file returns those five lines and nothing else: the value is assigned and
   *     dropped.
   * So a specimen whose results arrive AFTER a reviewer first opened the queue --
   * which is the ordinary order of work -- is a positive that no screen marks as
   * positive and that the clinician's positives-only list omits.
   *
   * THE CONTROL IS THE SAME DEFECT RUN BACKWARDS. Two specimens, one lab, one
   * analyte, one POSITIVE result posted through one API call each, both released,
   * differing in NOTHING but whether the queue was rendered before or after the
   * result arrived. The control reports positive and is red; the pin does not.
   * That is what makes the red-cell assertion non-vacuous (D397) -- it is proven
   * to match markup this very screen produces -- and it is why a locator typo
   * cannot pass here.
   *
   * NOT DRIVEN THROUGH A SCREEN, deliberately: no screen on this build can add a
   * confirmatory analyte (tst.spec.ts S-TST-04u, measured -- the grid renders
   * `new<N>` inputs only for a `tg_manual_edit` group and this database holds
   * none). `/resultapi/put_result` is the door the instrument bridge uses, it
   * needs no user and no session, and it is where analytes actually come from.
   */
  e2e('S-REV-16u a positive analyte flags the report positive whenever it arrives', async ({
    seedRequisition, tenantRequest, authedPage, db, tenant, runKey, baseURL,
  }) => {
    // The analyte the panel orders BY NAME. `ci_load_results.php:508` counts
    // POS rows whose `drug` is in `explode(',', strtoupper(panel_tests_requested))`,
    // so a `group_<id>` panel (seedRequisition's default) would exclude every
    // real analyte and the flag could not go up for a reason that has nothing
    // to do with this defect -- the same trap S-TST-04u documents.
    const [analyte] = db.query(
      `SELECT te_ID, te_name FROM ci_test_panels
        WHERE te_type = 'tox' AND te_in_req = 1 ORDER BY te_ID LIMIT 1`,
    );
    expect(analyte, 'no orderable tox analyte in ci_test_panels').toBeTruthy();
    const drug = analyte[1];

    const key = `e2erev16${runKey}`;
    grantResultApiKey(db, key, tenant.labId);
    // ANONYMOUS on purpose: the endpoint is authorized by the key alone, and a
    // logged-in context would leave it ambiguous which of the two let the write
    // through.
    const instrument = await playwrightRequest.newContext({ baseURL: baseURL ?? 'http://localhost' });

    /** One analyte result, exactly as the instrument bridge posts it. */
    const postResult = async (assen: string, result: string) => {
      const res = await instrument.get(
        `/resultapi/put_result?${new URLSearchParams({ key, accession: assen, test: drug, result })}`,
      );
      expect(res.status(), `put_result answers 200 for ${assen}`).toBe(200);
      expect(await res.json(), `put_result accepted the ${result} for ${assen}`)
        .toMatchObject({ status: 'success' });
    };

    const seedPanel = (patientLast: string) => seedBatch(
      (p, o) => seedRequisition(p, { ...o, 'bat[panel_tests_requested]': drug }),
      db, tenant, patientLast,
    );

    const posOf = (assen: string) => db.scalar(
      // 'unset', never 'NULL': Db.query maps the CLI's literal NULL to '', so a
      // NULL column and an empty one would read the same.
      `SELECT IFNULL(has_pos, 'unset') FROM ci_reports WHERE patient_id = ${Db.q(assen)}`,
    );
    // `css/admin_new.css:2215` -- `.report_tbl .has_pos td.statuscol
    // { border-left: 12px solid #da1414 !important }`. Loaded after admin.css
    // (whose :1445 sets a background instead) and therefore what the reviewer
    // actually sees: a red bar down the left edge of the status cell. Asserted
    // as the COMPUTED value, so a stylesheet that stops loading, a rule that is
    // outweighed, or a class that is never applied all read the same here --
    // the marker is not on the screen.
    const POSITIVE_BAR = '12px solid rgb(218, 20, 20)';

    try {
      // ---- CONTROL: the result arrives BEFORE anyone opens the queue --------
      // put_result creates the report row itself (`ci_result_api.php:66-77`),
      // leaving has_pos NULL, so the first queue render computes it with the
      // analyte already in place.
      const early = await seedPanel(`Revpos${runKey}a`);
      await postResult(early.assen, 'POS');
      expect(posOf(early.assen), 'the API leaves has_pos unset for the render to compute')
        .toBe('unset');

      // `gotoQueueFor`, never `openQueueFor`: the latter loads the UNFILTERED
      // queue first, and `results_list.php:595-603` auto-expands it whenever the
      // lab happens to hold exactly one batch -- firing a `ci_load_results`
      // render, and therefore a WRITE (D034), against whatever specimen that is.
      // Every other caller in this file is inside the serial describe; this test
      // is not, so it reaches its row by URL and touches nothing else.
      const earlyRow = await gotoQueueFor(authedPage, early.assen);
      await expect(earlyRow, `the reviewer's queue shows ${early.assen}`).toHaveCount(1);
      await expect(
        earlyRow.locator('td.statuscol'),
        `${early.assen} carries a positive analyte and the reviewer's status cell is barred red`,
      ).toHaveCSS('border-left', POSITIVE_BAR);
      expect(posOf(early.assen), 'ci_reports.has_pos for the early result').toBe('1');

      // ---- THE SPECIMEN UNDER TEST: same result, one render earlier ---------
      const late = await seedPanel(`Revpos${runKey}b`);
      // THE FREEZE. This is the ordinary act of a reviewer looking at the day's
      // work before the instrument has reported.
      await renderQueue(tenantRequest, tenant, late.batchId, late.assen);
      expect(posOf(late.assen), 'the first render writes has_pos = 0 on a report with no analytes')
        .toBe('0');

      await postResult(late.assen, 'POS');

      // PRECONDITIONS, outside the marker: the two specimens now hold the
      // SAME stored result, so nothing below is about a write that failed.
      const stored = db.query(
        `SELECT r.drug, r.\`pos-neg\` FROM ci_results r
           JOIN ci_reports re ON re.id = r.report_id
          WHERE re.patient_id = ${Db.q(late.assen)}`,
      );
      expect(stored, `ci_results for ${late.assen} -- the analyte the instrument reported`)
        .toEqual([[drug, 'POS']]);

      // Released by the lab's own reviewer, so the two rows differ in estatus
      // by nothing either.
      const lateRow = await gotoQueueFor(authedPage, late.assen);
      await expect(lateRow, `the reviewer's queue shows ${late.assen}`).toHaveCount(1);
      await lateRow.getByText('Approve', { exact: true }).click();
      const released = await reloadQueue(authedPage, late.assen);
      await expect(released.getByRole('cell', { name: 'C', exact: true }),
        'the late specimen is released, like the control').toBeVisible();

      // ---- THE PIN ---------------------------------------------------------
      // After the controls, never before them: a marker at the top of the body
      // would swallow a broken seed, a dead key or a failed release and report
      // them as the expected failure for good.

      await expect(
        released.locator('td.statuscol'),
        `${late.assen} holds the identical POSITIVE ${drug} result as ${early.assen}, which is `
        + 'red on this same screen, but its status cell is unmarked: ci_reports.has_pos was '
        + `frozen at ${posOf(late.assen)} by the queue render that happened before the `
        + 'instrument reported, and neither the result API nor the bench results form ever '
        + 'recomputes it. The reviewer is shown no positive, and the clinician\'s "Pos Results '
        + 'Only" list -- which is `re.has_pos = 1` and nothing else -- omits the specimen',
      ).toHaveCSS('border-left', POSITIVE_BAR);
      expect(posOf(late.assen), `ci_reports.has_pos for ${late.assen}`).toBe('1');
    } finally {
      await instrument.dispose();
    }
  });

  /**
   * S-REV-15u -- THE CORRECTION THE CLINICIAN CANNOT FIND.
   *
   * dst.spec.ts pins the DELIVERY half of this: a clinic that was faxed the
   * original is never sent the correction. This is the PULL half. A clinician who
   * goes looking for corrections has exactly one control for it -- the "revised
   * results" checkbox on the Test Results screen -- and it can never return
   * anything at all.
   *
   * TWO INDEPENDENT BREAKS, ONE COLUMN. `ci_reports` HAS NO `internal_notes`
   * COLUMN (measured: `show columns from ci_reports` -- it is on `ci_batches`).
   *
   *   1. THE FILTER. `parts/tox/test_results.php:46-49` (the day list) and
   *      `inc/actions/ci_load_test_results.php:58-61` (the drill-down) both add
   *      `and re.internal_notes != '' and re.estatus = 'C'`, where `re` is
   *      `ci_reports` (`test_results.php:117`, `ci_load_test_results.php:186`).
   *      Measured against this database: `ERROR 1054 (42S22) Unknown column
   *      're.internal_notes' in 'WHERE'`. `$wpdb->get_results()` returns an empty
   *      array, `WP_DEBUG_DISPLAY` is false (env/wp-config.php:75), so the screen
   *      draws an empty table and the clinician reads it as "no corrections".
   *   2. THE MARKER. `ci_load_test_results.php:224` sets the `revised_results`
   *      class from `$row->internal_notes`, and the SELECT at :186-198 lists
   *      `re.*` plus eleven named `ba.` columns -- `internal_notes` among neither.
   *      The property is always undefined, so the marker is never drawn either:
   *      an amended report is rendered byte-identical to the original.
   *
   * ONE FIX MOVES BOTH, which is why this is one pin and not two: the filter and
   * the marker read the same expression.
   *
   * WHAT WOULD BE CORRECT, and what the pin states: a report the laboratory has
   * amended is listed when the clinician asks for revised results. The day the
   * filter keys on the amendment the app actually records -- `final_results = 3`,
   * which S-REV-04 pins and which the assertion below reads back -- this reports
   * "Expected to fail, but passed".
   *
   * WHY THE MARKER IS *NOT* ASSERTED AS AN ABSENCE. `toHaveCount(0)` on
   * `.revised_results` would pass today and would keep passing under a fix that
   * repaired the filter and forgot the marker. The filter is the falsifiable half.
   *
   * PERSONA. Driven as `e2e_dir`, and the choice is forced rather than preferred:
   * the filter clause is appended BEFORE any role branch runs (`:58` vs the role
   * map at `:80-160`), so it is broken identically for every seat -- but of the
   * seeded personas that reach this screen, `doctor` carries no `_doc_id` (its
   * clause degrades to `and ba.doc_ID = `, a parse error, so it sees NOTHING),
   * and `office_manager` needs a `ci_clinic_manager` row it does not have
   * (ofc.spec.ts:398-410). Both are global persona state another spec owns and
   * this test will not write. The administrator seat reaches the same screen with
   * no clinic predicate at all.
   *
   * THE SPECIMEN IS SEEDED OUTSIDE `tenant` (see reportingLab). Consequence,
   * stated because it is not obvious: for the few seconds it sits at
   * `batch_status='resulted'` it is also visible on `e2e_dir`'s Tox Results
   * queue. That is harmless -- `results_list.php:66` admits any batch that has a
   * report row, so ofc.spec.ts's lab-35 renders already put rows on that screen,
   * and a batch outside a rejected status cannot be auto-approved by a render
   * (D034/D225).
   */
  e2e('S-REV-15u an amended report is listed under the clinician "revised results" filter', async ({
    director, db, pageAs, runKey,
  }) => {
    const reporting = reportingLab(db);
    expect(reporting.prefix, 'the clinician-facing lab must not be one of the pool labs')
      .not.toMatch(/^LA[A-P]$/);

    const b = await seedBatch(
      (patientLast, overrides) => seedRequisitionAs(director, db, reporting, patientLast, overrides),
      db, reporting, `Revamd${runKey}`,
    );

    // The amendment cycle S-REV-04 owns, driven through the same handlers: the
    // queue render that creates the report row (D034), release, reverse, mark
    // the maturity Amended, release again.
    await renderQueue(director, reporting, b.batchId, b.assen);
    const r = report(db, b.assen);
    await act(director, { tr_action: 'ci_results_approve', id: r.id });
    await act(director, { tr_action: 'ci_results_reverse', id: r.id });
    const edited = await director.post(`${ADMIN_PAGE}&tr_action=ci_save_result_edit&id=${r.id}`, {
      form: { 'info[final_results]': '3' },
    });
    expect(edited.status(), 'the results-edit saver answers 200').toBe(200);
    await act(director, { tr_action: 'ci_results_approve', id: r.id });

    // PRECONDITION, deliberately outside the marker: without a released,
    // amended report there is nothing for the filter to be wrong about.
    const after = report(db, b.assen);
    expect(
      [after.estatus, after.finalResults],
      'ci_reports [estatus, final_results] -- the report is released AND marked amended (3), '
      + 'which is the entire record the app keeps of a correction',
    ).toEqual(['C', '3']);

    // The day the line list groups this specimen under, read from the row the
    // saver wrote rather than from the host clock (the screen dates in the
    // app's timezone, `date_i18n`).
    const docDate = db.scalar(`SELECT DATE(doc) FROM ci_batches WHERE mid = ${Db.q(b.mid)}`);
    expect(docDate, 'ci_batches.doc is dated').not.toBe('');

    // ---- THE CONTROL, ON THE SAME SCREEN, WITH THE FILTER OFF ---------------
    // Everything the pin below depends on except the filter itself: this seat
    // reaches the screen, this lab is in scope, this day is listed, and the
    // drill-down names the accession. Without it, an empty filtered list would
    // be indistinguishable from a specimen that was never visible here at all
    // (D141) -- eleven such tests once passed against a fully broken app.
    const unfiltered = await pageAs(reporting.login);
    await unfiltered.goto(TEST_RESULTS);
    const day = unfiltered.locator('tr.parent').filter({ hasText: docDate });
    await expect(day, `the clinician's Test Results screen lists the day ${docDate}`)
      .toHaveCount(1);
    // The day row expands on a click on its own cell -- the handler ignores any
    // target that is not TD/TR/I (test_results.php:362) -- and POSTs
    // tr_action=ci_load_test_results for that date.
    await day.getByRole('cell').first().click();
    await expect(
      unfiltered.getByRole('cell', { name: b.assen, exact: true }),
      `the clinician's line list for ${docDate} names ${b.assen}`,
    ).toHaveCount(1);

    // ---- THE PIN -----------------------------------------------------------
    // Called HERE, not at the top of the body: everything above is a control or
    // a precondition and must be able to go RED on its own. A marker at the top
    // absorbs a broken seed and reports it as the expected failure forever.

    // A SECOND CONTEXT, not a second navigation. `test_results.php:400-410`
    // re-opens the last expanded day from the `active_test_results` cookie the
    // control click just set, which would expand the day for us and make the
    // click below a COLLAPSE. A fresh context carries no cookie.
    const revised = await pageAs(reporting.login);
    await revised.goto(TEST_RESULTS);
    await revised.getByRole('checkbox', { name: 'revised results' }).check();
    await revised.getByRole('button', { name: 'Search' }).click();

    const revisedDay = revised.locator('tr.parent').filter({ hasText: docDate });
    await expect(
      revisedDay,
      `the laboratory amended ${b.assen} and released the correction, and the clinician's `
      + `"revised results" filter offers no ${docDate} at all: the one control this screen `
      + 'gives a clinic for finding corrected results returns an empty table for every '
      + 'query, because both halves of the filter read ci_reports.internal_notes and that '
      + 'column does not exist (ERROR 1054). A superseded result is read as current',
    ).toHaveCount(1);
    await revisedDay.getByRole('cell').first().click();
    await expect(
      revised.getByRole('cell', { name: b.assen, exact: true }),
      `${b.assen} was amended and must be listed among the clinician's revised results`,
    ).toHaveCount(1);
  });
});

// ---------------------------------------------------------------------------

e2e.describe('release authorization @rev', () => {
  /**
   * WAVE 2 / D388 -- the page gate as the OPERATOR meets it.
   *
   * SUPERSEDES the HTTP twin, and this is the one place in the migration where
   * that is right rather than a D382 violation. D382 protects a URL-only
   * handler that no screen calls; here both layers issue the IDENTICAL
   * `GET admin.php?page=tox_results` against the IDENTICAL guard, and the
   * browser asserts a strict superset of the regex it replaces (same denial
   * text, plus the menu registration and the absence of admin chrome). A
   * retained twin would assert nothing the other does not, and it could not
   * honestly carry `@nouicaller`: the menu link IS a UI caller. Plan §Wave 2,
   * "each becomes two rendered facts INSTEAD OF two regexes over raw HTML".
   *
   * D250 -- BOTH facts, not one. Menu registration is the ONLY gate on these
   * screens, so (a) the link is present exactly when the capability is; and
   * (b) an unregistered screen is the least-guarded surface in the app, so the
   * persona is walked to the URL DIRECTLY anyway. The direct-URL leg is the
   * whole of the test this replaces and it is kept verbatim in shape.
   *
   * SYMMETRIC BY CONSTRUCTION. One boolean-driven assertion per fact, never a
   * denied-branch/allowed-branch pair: a branch that runs before a navigation
   * asserts against `about:blank`, where toHaveCount(0) holds for ANY product
   * behaviour. `pageAs()` returns a page at about:blank, hence the goto first.
   *
   * MEASURED on this build, 2026-08-25. e2e_sec (lab_sec, 29 live caps, no
   * `ci_results`): 0 menu links, and a ~2.6 KB wp_die body carrying the denial
   * and NO `#adminmenu`. e2e_dir (administrator, holds `ci_results`): 1 menu
   * link, the real screen two orders of magnitude larger, no denial. Both
   * answer HTTP 200, so nothing here asserts a status code -- and nothing
   * asserts a body size either, which drifts build to build (D185).
   *
   * NOT @mutates, AND THAT IS NOW ENFORCED RATHER THAN OBSERVED. It used to rest
   * on "e2e_dir is bound to lab 35, which renders 0 parents" -- false since
   * S-REV-15u began seeding into lab 35 through `reportingLab()`. At a count of
   * exactly ONE, `results_list.php:595-603` auto-expands and fires a
   * `ci_load_results` fragment, which is a WRITE (D034/C2b) against another
   * test's specimen. The URL below therefore carries a search token no accession
   * can match, so the list renders zero parents and the branch cannot fire.
   */
  for (const [login, allowed] of [['e2e_sec', false], ['e2e_dir', true]] as const) {
    e2e(`${login} ${allowed ? 'reaches' : 'is denied'} the Tox Results screen`, async ({ pageAs }) => {
      const page = await pageAs(login);

      // REQUIRED: pageAs() hands back about:blank. Assert nothing before this.
      await page.goto('/wp-admin/');
      const menuLink = page.locator('#adminmenu')
        .getByRole('link', { name: 'Tox Results', exact: true });
      await expect(menuLink,
        `${login} ${allowed ? 'finds' : 'never finds'} Tox Results in the admin sidebar -- ` +
        'menu registration IS the gate on this screen (D250)').toHaveCount(allowed ? 1 : 0);

      // ...and then walks to the URL anyway, which is the surface an
      // unregistered screen leaves wide open.
      //
      // BOUNDED ON PURPOSE (D392). The denial page is on a THREE-SECOND FUSE. The
      // plugin hooks core's own `admin_page_access_denied` action
      // (`inc/admin.php:1989` at the pin) and, ahead of core's wp_die() body,
      // `parts/global/admin_page_access_denied.php` echoes
      // `<a href="?page=welcome">Welcome Page</a>` (:25) and
      // `<meta http-equiv="refresh" content="3;...page=welcome">` (:37) -- the
      // ELSE branch, because its $moved_pages table is keyed on the OLD slug
      // `ci_results`, not `tox_results`. Both files verified at the pinned
      // a7aa074d, not just in the working tree: admin_page_access_denied.php is
      // byte-identical there, and inc/admin.php's 86-line drift touches neither
      // the hook nor `add_page('tox_results', 'Tox Results', 'ci_results', ...)`
      // (:555 at the pin), which is where the menu label above comes from.
      // Measured, one poll per second:
      // t=0..2 denial 1 / #adminmenu 0, t=3 the refresh fires, t=4+ the URL is
      // page=welcome with denial 0 / #adminmenu 1. The suite's default 5 s
      // expect timeout therefore OUTLIVES the page under assertion, and a
      // polling assertion that started late would grade the Welcome page
      // instead. Caught exactly that way: inverting these two expectations to
      // prove they can fail made the denied test pass the first and fail the
      // second, against a page it was never meant to see.
      // `&s=` IS LOAD-BEARING, and it is what makes the "not @mutates" claim above
      // true by construction rather than by a count that drifts. The unfiltered
      // queue auto-expands whenever the lab holds exactly ONE batch
      // (results_list.php:595-603), firing a `ci_load_results` fragment and
      // therefore a WRITE (D034) against whichever specimen that is. e2e_dir is
      // bound to lab 35, which S-REV-15u seeds into through `reportingLab()` -- so
      // immediately after a reset.sh the first such seed makes the count exactly 1
      // and this test would render over another test's specimen mid-cycle. A search
      // token no accession can carry lists ZERO parents, so the branch cannot fire.
      // Access, not content, is what this test asserts; the filter changes nothing
      // it grades.
      await page.goto(`${ADMIN_PAGE}&s=E2ENOSUCHACCESSION`);
      await expect(page.getByText('Sorry, you are not allowed to access this page.'),
        `typing the URL ${allowed ? 'lets ' + login + ' in' : 'still stops ' + login} ` +
        'at the core denial page').toHaveCount(allowed ? 0 : 1, { timeout: BEFORE_BOUNCE_MS });
      await expect(page.locator('#adminmenu'),
        allowed
          ? 'the real screen renders inside wp-admin, sidebar and all'
          : 'wp_die() renders a bare error page with no admin chrome at all',
      ).toHaveCount(allowed ? 1 : 0, { timeout: BEFORE_BOUNCE_MS });

      // ...and the FUSE ITSELF, pinned rather than waited out. The 3 and the
      // destination are what BEFORE_BOUNCE_MS is derived from, so this is the
      // line that reds if the fuse is retimed. It replaced a
      // toHaveURL(/page=welcome/) that cost 3.2 s per denied test and could NOT
      // catch a fuse shortened to 1 s -- the bounce still happens, the 2 s bound
      // above silently stops holding. See D392.
      await expect(
        page.locator('meta[http-equiv="refresh"][content^="3;"][content*="page=welcome"]'),
        allowed
          ? `${login} is on a live screen -- nothing is counting down to bounce them off it`
          : `${login} is bounced to the Welcome page after 3 s -- the denial is a detour, not a dead end`,
      ).toHaveCount(allowed ? 0 : 1, { timeout: BEFORE_BOUNCE_MS });
    });
  }
});

// Same reason as above: these mutate through the same listing surface.
e2e.describe('release authorization -- dispatcher @rev @mutates', () => {
  // Sequential for the same reason as the lifecycle block above, and `default`
  // rather than `serial` for the same reason too: the pin below is red until the
  // dispatcher grows a per-action gate, and under `serial` a red anywhere in this
  // block abandons the rest of it. Both tests seed their own batch, so one worker
  // in declaration order is all the isolation either of them needs.
  e2e.describe.configure({ mode: 'default' });

  /**
   * ci-data-works-arc/admin/init.php:59 maps any `tr_action` on any wp-admin URL
   * straight onto tradmin_action_<name> at admin_init, with no capability check
   * and no nonce (D175). wp-admin/admin.php:176 fires admin_init BEFORE it
   * resolves the plugin page, so the action runs first and `wp_redirect(); exit;`
   * before any page gate could speak.
   *
   * MEASURED CORRECTION to the catalog's Gap 4 and to D185's reading. The
   * ungated dispatcher is not uniformly exploitable: which URL carries the
   * `tr_action` decides the outcome, because the plugin's own menu guard runs on
   * `admin_menu` -- from wp-admin/admin.php:159, i.e. still ahead of admin_init
   * -- and wp_die()s a persona who cannot reach the requested page slug. So the
   * two tests below differ only in the URL the identical action rides on, and
   * they get opposite results.
   */
  const secContext = (baseURL: string | undefined) => playwrightRequest.newContext({
    baseURL, storageState: path.join(STATE_DIR, 'e2e_sec.json'),
  });

  e2e('the page guard stops the release when the action rides on page=tox_results', async ({
    seedRequisition, tenantRequest, db, tenant, runKey, baseURL,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}y`);
    await renderQueue(tenantRequest, tenant, b.batchId, b.assen);
    const r = report(db, b.assen);

    const sec = await secContext(baseURL);
    const res = await act(sec, { tr_action: 'ci_results_approve', id: r.id });
    const status = res.status();
    const body = await res.text();
    await sec.dispose();

    // Not a 403: this build answers 200 with a wp_die body (D185), so the
    // denial is asserted on the rendered outcome and on the DB, never on a code.
    expect(status, 'admin.php answers 200 for the denied persona too').toBe(200);
    expect(/Sorry, you are not allowed/.test(body), 'core denial notice').toBe(true);
    expect(report(db, b.assen).estatus, 'the report is still a draft').toBe('');
    expect(auditRows(db, 'approve_rs', b.assen).length, 'no approve_rs audit row').toBe(0);
  });

  // The dispatcher itself. lab_sec holds no `ci_results`, yet the identical
  // action off the dashboard releases the report. The expected value today is
  // therefore "the release succeeds", so the denial below is an EXPECTED
  // FAILURE, never a passing assertion. It goes green the day the dispatcher
  // grows a per-action gate -- which is exactly when this file should be told.
  //
  // e2e.fail() is called INSIDE the test body on purpose: at describe scope it
  // annotates every sibling, which would have masked a genuine regression in
  // the page-guard test above.
  e2e('a persona without ci_results releases the report anyway off /wp-admin/index.php', async ({
    seedRequisition, tenantRequest, db, tenant, runKey, baseURL,
  }) => {
    const b = await seedBatch(seedRequisition, db, tenant, `Revsyn${runKey}z`);
    await renderQueue(tenantRequest, tenant, b.batchId, b.assen);
    const r = report(db, b.assen);

    const sec = await secContext(baseURL);
    await actOnDashboard(sec, { tr_action: 'ci_results_approve', id: r.id });
    await sec.dispose();

    expect(report(db, b.assen).estatus,
      'lab_sec holds no ci_results, so the report must still be a draft').toBe('');
  });
});


