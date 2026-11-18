import { test as shared, expect, BEFORE_BOUNCE_MS } from '../fixtures/test';
import { batchIds } from '../fixtures/batch-id';
import { Db } from '../fixtures/db';
import { cronHandlers, dexec, mailSubjectsTo, runCron, wpEval } from '../fixtures/container';
import { infra, seedBloodRequisition } from '../fixtures/seed-domain';
import type { Tenant } from '../fixtures/tenant';
import type { APIRequestContext, Locator, Page } from '@playwright/test';

/**
 * DST -- result distribution (S-DST-01, -02, -06, -07).
 *
 * PERSONA, read from the RUNNING instance (D178), never install_roles.php, and
 * never through user_can() (D255 -- it answers true off level_0 for odd
 * capability names). Capabilities below are wp_options.wp_user_roles:
 *
 *   role            ncaps  what it actually holds
 *   results           12   read, level_1, level_0, ci_results, ci_results_blood,
 *                          ci_resutls_pcr (the misspelled one), ci_results_tox_ref,
 *                          ci_results_dna, ci_test_results, ci_patient_chart,
 *                          ci_patient_trends, ci_abx_report
 *   Lab_Reporting      7   read, level_1, level_0, ci_turnaround_time,
 *                          ci_processed_samples, ci_report_summary, ci_data_reports
 *
 * CATALOG CORRECTION. 10-scenarios.md:40 and personas.md 3.14 name `results`
 * and `Lab_Reporting` together as "reporting staff". Live they are not
 * interchangeable: `results` is the slug that carries the release capability
 * `ci_results` (and the per-btype result caps), while `Lab_Reporting` carries no
 * result, release or distribution capability at all -- it is an analytics
 * reader. The distribution channels themselves enforce no role: every auto push
 * runs from cron and stamps `created_by=-1` (flow-result-distribution.md:44).
 *
 * WHICH PERSONA THESE TESTS RUN AS. Neither `results` nor `Lab_Reporting` has a
 * seeded user (env/seed.php creates lab_sec, lab_tech, administrator,
 * subscriber, collector, doctor, office_manager, multi_site_manager, scientist,
 * pcr_verify, Billing, BillbyClinic and the e2e_lead* leadlabsecman pool). The
 * tenant fixture's `leadlabsecman` holds `ci_results` live, which is the one
 * capability the upstream half of this flow needs, so it drives the release.
 * That is a fixture gap, not a capability claim: nothing below asserts "the
 * reporting persona may do X".
 *
 * TENANCY. LAI, assigned by SPEC_LAB['dst'] (D239). The `tenant` fixture is
 * taken as given -- no pin, no override, and never a lab row of its own
 * (D161/D172).
 *
 * EGRESS IS DENIED AND THAT IS THE POINT (D091). RingCentral, Faxage, Box,
 * OpiSafe and every EMR transport are unreachable by design, and the fax and
 * EMR clients use raw curl / ssh2, so they never pass through the pre_http_request
 * stub either. Every assertion below is on the LEDGER THE APP WRITES --
 * ci_box_upload.status/etag/created_by, ci_reports.faxed, ci_sync_api_orders --
 * never on a third-party response. A ledger that claims delivery while nothing
 * was delivered is the defect these tests exist to catch.
 */

// ---------------------------------------------------------------- fixtures

/**
 * ci_batches.batch_id is int(10) NOT NULL. The allocator is shared (D243/D396):
 * seconds * 1000 + worker slot * 100 + THIS SPEC'S offset + sequence, so
 * neither another worker nor another spec file can hand out the same id and
 * one spec's queue render cannot select another's rows.
 */
const nextBatchId = batchIds('dst');

const TOX_RESULTS = '/wp-admin/admin.php?page=tox_results';

/** Synthetic destination fax. Obviously fake, and unreachable regardless. */
const FAX_NUMBER = '000-000-0199';

/**
 * The hardcoded personal address in the debug fax path
 * (api/ci_main_fax.php:80, 13-boundaries.md:240). Never a recipient of anything
 * this suite produces -- asserted, not assumed.
 */
const DEBUG_FAX_ADDR = 'pntrinh29@gmail.com';

/**
 * THE SCREEN THE FAX LEDGER IS VISIBLE ON, AND THE ONE PERSONA THAT REACHES IT.
 *
 * Every line reference in this block is to the PINNED blob and was read out of
 * it (`git show a7aa074d:<path>`), not out of the worktree -- which for these
 * two files is NOT the same text: `inc/admin.php` is +86/-17 and
 * `pages/admin_ci_wsdl_keys.php` is +804/-67 between the pin and the checked-out
 * submodule branch, and CI builds the pin.
 *
 * `parts/instrument/api_ringcentral.php:84-202` renders every `ci_box_upload`
 * row whose `type` matches `%fax%` as a 12-column table: `#`, Accession
 * (`:120`), Type, Patient, Clinic, Doctor, **To** (`$row->etag` -- the
 * destination fax number, `:182`), PDF, **Status** (`:184`, which prints the
 * literal `sent` if and only if `status == 1` and NOTHING otherwise), DOC,
 * Report Date, Date/time. This file is +7/-2 between the pin and the worktree,
 * all of it above the log block, so the WORKTREE numbers are these plus five.
 *
 * The migration plan's wave-4 note is right that this screen exists and stops
 * one step short. MEASURED at the pin, 2026-08-25, by rendering this exact URL:
 * `Administrat0r` gets the table (the `Accession` header is present); this
 * spec's own tenant persona is REFUSED; and `e2e_dir` -- an administrator --
 * reaches `page=ci_pdf_data` but is landed on a different tab with no fax
 * ledger on it. Three separate mechanisms, and it matters which one applies to
 * which persona because only one of them is what this test asserts:
 *
 *   1. **The tenant persona never gets the PAGE.** `inc/admin.php:184` registers
 *      `ci_pdf_data` (config at `:226-235`) only inside
 *      `tr_current_role_in(array('AdminCIDW','administrator','IT','ci_dev'))`.
 *      `leadlabsecman` is in none of those, so the menu page does not exist for
 *      it and WordPress CORE refuses the request -- core's own
 *      `'Sorry, you are not allowed to access this page.'`, via the
 *      `admin_page_access_denied` action the plugin hooks at `inc/admin.php`
 *      to add its 3-second meta-refresh bounce (D392). This is the gate the
 *      assertion in S-DST-01 lands on. **Granting `ci_dashboard_system` to lab
 *      staff would NOT open it** -- registration keys on the ROLE LIST above,
 *      so the regression that would open this screen to the bench is a role
 *      being ADDED to that array, and that is what the assertion catches.
 *   2. `pages/admin_ci_wsdl_keys.php:21-26` is a SECOND gate, behind the first:
 *      it wp_die()s anyone who is not `$is_ci_admin` and lacks
 *      `ci_dashboard_system`, with a DIFFERENT string
 *      (`'You do not have permission to access this page.'`) and, measured,
 *      WITH the admin chrome still rendered. It never fires for the tenant
 *      persona, which is stopped one layer earlier.
 *   3. `inc/admin.php:113` defines `$is_ci_admin` as the LITERAL login
 *      `Administrat0r`, widened only by an administrator carrying `_is_ci_user`
 *      user meta. No seeded user carries that meta (measured), and
 *      `inc/admin.php:193-206` additionally unsets the whole APIs tab for a
 *      non-`$is_ci_admin` administrator unless the request carries `trajax`
 *      -- which is why `e2e_dir` lands on a tab that is not this one.
 *
 * BECAUSE three mechanisms can refuse this URL and they do not share a string,
 * S-DST-01's denial assertion is deliberately MECHANISM-INDEPENDENT: it asserts
 * that the fax ledger did not render, with the same-URL positive control from
 * the administrator seat immediately above it, and accepts EITHER wp_die
 * wording as evidence that a refusal is what happened rather than a blank page.
 * An assertion pinned to one of the three strings would go green on a product
 * change that swapped which layer refuses.
 *
 * So the queue is driven below as `Administrat0r`, named explicitly for the
 * same reason pcr.spec.ts names its own persona rather than using `tenant`, and
 * the tenant persona's refusal is ASSERTED in S-DST-01 rather than assumed.
 *
 * NO EGRESS (D091 still holds on this screen). `CI_Fax_RingCentral::__construct`
 * only reads options, and the `refreshToken()` call at
 * `api/ci_fax_ringcentral.php`-consumer `:57` is behind
 * `$options['time'] < time() - 86400`, which the beforeAll token (`time` = now)
 * does not satisfy. Measured: the render returns in well under a second.
 *
 * `s=<accession>` is the app's OWN search box (`:87-99`). It resolves the
 * accession to one `mid` + `btype` and scopes what is otherwise a GLOBAL,
 * lab-unfiltered query (`$where = " type like '%fax%' "`, `:85`) down to that
 * one specimen. Mandatory here: every worker's fax rows share this one screen.
 */
const FAX_LOG = '/wp-admin/admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys'
  + '&tab=fax_sms&subtab=api_ringcentral&act=log';
const FAX_LOG_PERSONA = 'Administrat0r';

/**
 * Navigate the fax log to ONE accession and hand back that specimen's row.
 *
 * The columnheader check is not decoration: a denial page and a PHP fatal both
 * answer 200, and a `toHaveCount(0)` against either would pass for any product
 * behaviour (D141/D388). It is what makes the NEGATIVE use of this helper in
 * S-DST-02 mean something.
 */
async function faxLogRow(page: Page, assen: string): Promise<Locator> {
  await page.goto(`${FAX_LOG}&s=${assen}`);
  await expect(
    page.getByRole('columnheader', { name: 'Accession', exact: true }),
    'the fax log drew its column headers, so this is the queue and not a denial',
  ).toHaveCount(1);
  return page.getByRole('row', { name: assen });
}

interface Released { mid: string; assen: string; reportId: string; batchId: number; }

/**
 * A RELEASED tox report on a named clinic -- the universal precondition for
 * every distribution channel (flow-result-distribution.md:33, `estatus='C'`).
 *
 * Built through the app's own screens end to end (D085/D099), in the three
 * steps rev.spec.ts established:
 *   1. the requisition saver, with batch_status='resulted' so the review queue
 *      admits a batch that has no report row yet (ci_load_results.php:92);
 *   2. rendering the review queue, which is what CREATES the ci_reports row
 *      (ci_load_results.php:415) -- a GET that writes (D034);
 *   3. the Approve action, which sets estatus='C' and stamps approve_date.
 * There is no app path that produces a released tox report more directly.
 */
async function seedReleasedTox(
  seedRequisition: (p: string, o?: Record<string, string>) => Promise<{ status: number }>,
  request: APIRequestContext,
  db: Db,
  tenant: Tenant,
  patientLast: string,
  clinicId: string,
  docId: string,
): Promise<Released> {
  const batchId = nextBatchId();
  const seeded = await seedRequisition(patientLast, {
    'bat[batch_id]': String(batchId),
    'bat[batch_status]': 'resulted',
    'bat[cli_ID]': clinicId,
    'bat[doc_ID]': docId,
  });
  // Not toBeLessThan(400): a login redirect is 302 and would pass that (D226).
  expect(seeded.status, 'the requisition saver answers 200').toBe(200);

  const rows = db.query(
    `SELECT mid, assen, lab_id, lab, cli_ID FROM ci_batches
      WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
  );
  expect(rows.length, `exactly one ci_batches row for ${patientLast}`).toBe(1);
  const [mid, assen, labId, lab, cliId] = rows[0];
  // D160: the saver answers 200 with assen='' when the minter fails.
  expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
  // TENANCY, asserted rather than assumed (R10/D161/D172).
  expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
  expect(lab, 'ci_batches.lab').toBe(tenant.prefix);
  expect(cliId, 'ci_batches.cli_ID -- the specimen is on the clinic under test').toBe(clinicId);

  // THE RENDER THAT WRITES (D034).
  const render = await request.post(TOX_RESULTS, {
    form: { tr_action: 'ci_load_results', batch_id: String(batchId), lab_id: tenant.labId, ref_lab_id: '0' },
  });
  expect(render.status(), 'the tox results fragment answers 200').toBe(200);

  const draft = db.query(
    `SELECT id, estatus FROM ci_reports WHERE patient_id = ${Db.q(assen)}`,
  );
  expect(draft.length, `the queue render created exactly one ci_reports row for ${assen}`).toBe(1);
  expect(draft[0][1], 'a freshly created report is a draft').toBe('');
  const reportId = draft[0][0];

  const approve = await request.get(
    `${TOX_RESULTS}&tr_action=ci_results_approve&id=${reportId}`, { maxRedirects: 0 },
  );
  expect(approve.status(), 'the approve action redirects back to the queue').toBe(302);

  const [[estatus, approveDate, faxed]] = db.query(
    `SELECT estatus, approve_date, faxed FROM ci_reports WHERE id = ${Db.q(reportId)}`,
  );
  expect(estatus, 'ci_reports.estatus -- the report is released').toBe('C');
  expect(approveDate, 'ci_reports.approve_date is stamped, so the report is inside the fax window')
    .not.toBe('');
  expect(faxed, 'ci_reports.faxed starts at 0, so a later 1 can only come from a send').toBe('0');

  return { mid, assen, reportId, batchId };
}

const faxed = (db: Db, assen: string): string =>
  db.scalar(`SELECT faxed FROM ci_reports WHERE patient_id = ${Db.q(assen)}`);

/** Every fax-queue row for one specimen. Exact match on mid, never LIKE (D146). */
const queueRows = (db: Db, mid: string): string[][] => db.query(
  `SELECT type, status, etag, created_by, lab_ID, btype, batch_id, account_id, sender, uptime
     FROM ci_box_upload WHERE mid = ${Db.q(mid)} ORDER BY id`,
);

// --- the tox review queue AS A SCREEN (D380/D386) ---------------------------
//
// Lifted from rev.spec.ts:249-278 rather than imported: that file keeps them
// private and the two specs drive DIFFERENT labs. The behaviour they rely on is
// the app's, not the helper's -- `parts/tox/results_list.php:595-603` auto-
// expands the single `tr.parent` a one-accession search leaves behind, which
// fires the `ci_load_results` fragment with no click at all.
//
// BOTH OF THESE WRITE (D034): the fragment INSERTS the report row on its first
// render. Every precondition has to be read before the first call.

/** Filter the queue to ONE accession and hand back that specimen's row. */
async function openToxQueue(page: Page, assen: string) {
  await page.goto(TOX_RESULTS);
  // No label[for] and no accessible name on the search box (D383 fallback 3);
  // `name=` is what the user's own browser submits.
  await page.locator('div.tablenav form input[name="s"]').fill(assen);
  await page.getByRole('button', { name: 'Search' }).click();
  const row = page.locator('tr.childp').filter({ hasText: assen });
  await expect(row, `the reviewer's own queue shows ${assen}`).toHaveCount(1);
  return row;
}

/**
 * D386 -- RE-RENDER FROM THE SERVER before believing anything on this screen.
 *
 * `js/admin.js:1410-1428` rewrites the Approve/Reverse label and the
 * `td.statuscol` code on ANY HTTP 200 whose body contains `updated`, and
 * `parts/tox/results.php:158` emits that string straight after `$wpdb->update()`
 * without checking that the update touched a row. Post-XHR DOM here is markup
 * the CLIENT authored and it survives a write that did nothing.
 */
async function reloadToxQueue(page: Page, assen: string) {
  await page.reload();
  const row = page.locator('tr.childp').filter({ hasText: assen });
  await expect(row, `the reloaded queue still shows ${assen}`).toHaveCount(1);
  return row;
}

// ============================================================ S-DST-01/02
//
// AUTO-FAX. Sequential by necessity, not preference: every test here fires
// `ci_auto_fax_results`, and the cron's send leg selects the queue GLOBALLY
// (auto_fax_results.php:186-196, `limit 10`, no lab filter). Run in parallel,
// one test's cron pass consumes the row the other is about to assert on. Same
// reasoning as rev.spec.ts's sequential describe.
//
// MODE `default`, NOT `serial`, and rev.spec.ts's D239 block carries the full
// argument: both put this block on one worker in declaration order, and only
// `serial` also ABANDONS the rest of the block after a failure. The amended-
// report pin below is red until auto_fax_results.php:122 learns about
// `re.modified`, so under `serial` it took S-DST-02b, 02c and 02d out of every
// run. Position is free here -- the channel is armed once in `beforeAll` and
// every test seeds its own specimen -- so nothing is bought by the abandonment.

shared.describe('auto-fax @dst @mutates', () => {
  shared.describe.configure({ mode: 'default' });

  /**
   * ARMING THE CHANNEL is the Lab-admin half of this flow
   * (flow-result-distribution.md:43 -- AdminCIDW configures the provider,
   * the per-btype toggle and the per-clinic opt-in). Three writes, restored in
   * afterAll:
   *
   *  1. `ci_ringcentral_st` (blog 1) -- a token that merely LOOKS unexpired.
   *     Load-bearing, and not a shortcut: auto_fax_results.php:43-62 calls
   *     is_expired() and `return`s at :61 BEFORE the queue is built, and
   *     is_expired() reads options['time']+options['expires_in'], which are 0 on
   *     a fresh install. Without this the cron aborts at the token gate and both
   *     tests below would pass while proving nothing (D141).
   *  2. `ci_pdf_data.auto_fax_approve_tox` -- the per-btype toggle at :72. Read
   *     back through tr_get_option(), which caches into $option_key_cache at
   *     plugin load, so the cache is cleared in the same process as the write.
   *     Nothing else in the app reads `auto_fax_approve_tox`: the only other
   *     consumers of that option family are the blood/gene/pcr/req/study
   *     listings, each keyed to its OWN btype (measured: grep auto_fax_approve).
   *  3. The clinic opt-in on the SECOND clinic (`cli_faxres`, `cli_om-fax`).
   *     Deliberately not the first: seedRequisition puts every other spec's
   *     specimens on clinic 1, and the cron's queue query has no lab filter, so
   *     opting clinic 1 in would sweep every other worker's released reports
   *     into this spec's fax queue. Clinic 2 exists for exactly this (D204) and
   *     no other spec routes a RELEASED tox report to it.
   *
   *     CORRECTED 2026-08-26, and the qualifier is the whole content of the
   *     correction. `bil.spec.ts` S-BIL-06b now routes to clinic 2 as well
   *     (deliberately, to stay out of S-BIL-07's exclusion-list rebuild) and
   *     releases its specimen, so while this describe holds the opt-in armed
   *     that specimen IS fax-eligible and a pass of this cron can queue it.
   *     Harmless to both files and checked rather than assumed: every assertion
   *     here is scoped to `b.mid` or `b.assen` (`queueRows(db, mid)`,
   *     `faxed(db, assen)`, `faxLogRow(page, assen)`), and bil reads neither
   *     `ci_reports.faxed` nor `ci_box_upload`. What is NOT safe is a future
   *     total-count assertion on either table -- clinic 2 is no longer this
   *     spec's alone.
   */
  shared.beforeAll(async ({ db }) => {
    const f = infra(db);
    const armed = wpEval(
      'global $option_key_cache, $wpdb;'
      + 'update_blog_option(1,"ci_ringcentral_st", array("client_id"=>"e2e-not-a-key",'
      + '"client_secret"=>"e2e-not-a-secret","access_token"=>"e2e-not-a-token",'
      + '"refresh_token"=>"e2e-not-a-token","time"=>time(),"expires_in"=>86400));'
      + '$opt = get_option("ci_pdf_data");'
      + '$o = ["toxWasSet"=>array_key_exists("auto_fax_approve_tox",$opt)];'
      + '$opt["auto_fax_approve_tox"] = 1; update_option("ci_pdf_data",$opt);'
      + '$option_key_cache = array();'
      + `$wpdb->query("update ci_clinic set cli_faxres='1', \`cli_om-fax\`='${FAX_NUMBER}' where cID=${Number(f.clinic2Id)}");`
      + 'require_once(WP_PLUGIN_DIR."/ci-data-works-arc/api/ci_main_fax.php");'
      + '$fax = new CI_Main_Fax();'
      + '$o["provider"] = $fax->api_slug; $o["expired"] = $fax->is_expired();'
      + '$o["toxOn"] = (bool)tr_get_option("ci_pdf_data","auto_fax_approve_tox");',
    );
    // Each of these is a precondition the cron itself checks. If any is wrong
    // the cron short-circuits and every assertion downstream is vacuous.
    expect(armed.provider, 'fax provider (ci_pdf_data.fax_api, RingCentral by default)')
      .toBe('CI_Fax_RingCentral');
    expect(armed.expired, 'the token gate at auto_fax_results.php:43 must be open').toBe(false);
    expect(armed.toxOn, 'the per-btype toggle auto_fax_approve_tox must read back on').toBe(true);
  });

  shared.afterAll(async ({ db }) => {
    const f = infra(db);
    wpEval(
      'global $option_key_cache, $wpdb;'
      + 'update_blog_option(1,"ci_ringcentral_st", array());'
      + '$opt = get_option("ci_pdf_data"); unset($opt["auto_fax_approve_tox"]);'
      + 'update_option("ci_pdf_data",$opt); $option_key_cache = array();'
      + `$wpdb->query("update ci_clinic set cli_faxres='0', \`cli_om-fax\`='' where cID=${Number(f.clinic2Id)}");`
      + '$o = ["restored"=>1];',
    );
  });

  shared('S-DST-01 @smoke the hourly cron queues the released report to the clinic fax number', async ({
    seedRequisition, tenantRequest, pageAs, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);
    const patientLast = `Dstsyn${runKey}a`;
    const b = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, patientLast, f.clinic2Id, f.doc2Id,
    );
    expect(queueRows(db, b.mid).length, 'no queue row exists before the cron runs').toBe(0);

    // The D084 form. NEVER `wp cron event run --due-now`: that fans the due
    // queue out into D017's partner-mutating SFTP.
    runCron('ci_auto_fax_results');

    const rows = queueRows(db, b.mid);
    expect(rows.length, `exactly one ci_box_upload row for ${b.assen} (mid ${b.mid})`).toBe(1);
    const [type, status, etag, createdBy, labId, btype, batchId, accountId, sender, uptime] = rows[0];

    // The channel tag and the destination, from auto_fax_results.php:141-158.
    expect(type, 'ci_box_upload.type -- the tox fax channel tag').toBe('rs_efax');
    expect(etag, 'ci_box_upload.etag carries the DESTINATION FAX NUMBER').toBe(FAX_NUMBER);
    expect(createdBy, 'ci_box_upload.created_by -- the cron runs as system (-1)').toBe('-1');
    expect(btype, 'ci_box_upload.btype').toBe('tox');
    expect(batchId, 'ci_box_upload.batch_id').toBe(String(b.batchId));
    expect(accountId, 'ci_box_upload.account_id -- the ordering clinic').toBe(f.clinic2Id);
    expect(sender, 'ci_box_upload.sender -- the clinic name on the cover page')
      .toBe('E2E Test Clinic Two');
    // TENANCY (D172): the queue row is routed to this spec's lab, not any other.
    expect(labId, `ci_box_upload.lab_ID: queued under lab ${labId}, tenant is ${tenant.labId}`)
      .toBe(tenant.labId);

    // CONTAINMENT, asserted as an outcome. `status` is the whole story:
    //   5 = queued/held, the state the insert at :146 writes;
    //   1 = sent, written at :271 ONLY when the provider accepted the fax;
    //   6 = the send was attempted and recorded as failed (:281).
    // MEASURED: 6, not 5. The same cron pass that queues the row also runs the
    // send leg (:178-292) -- `fax_time` is unset, so status 5 stays inside
    // $needfax_status -- and trpdd_export_results_pdf DOES materialize the PDF
    // here, so the cron gets as far as CI_Fax_RingCentral::fax(), which is raw
    // curl to platform.ringcentral.com. That never passes through the
    // pre_http_request stub; the egress-denied network is what refuses it. So
    // the honest end state is "queued, attempted, recorded as failed", and a 1
    // would mean the ledger claims a delivery the network made impossible.
    expect(status, `ci_box_upload.status for ${b.assen}: 1 = the ledger records a fax that `
      + 'the egress-denied network cannot have sent; 5 = the send leg never ran, which '
      + 'would make the "faxed stays 0" assertion below vacuous')
      .toBe('6');
    expect(uptime, 'ci_box_upload.uptime is stamped only on a successful send (:271)').toBe('0');
    expect(
      faxed(db, b.assen),
      `ci_reports.faxed for ${b.assen} must stay 0: the send leg sets it to 1 only `
      + 'after the provider accepted (auto_fax_results.php:272-274)',
    ).toBe('0');
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_log_users WHERE user_action='fax' AND mid=${Db.q(b.mid)}`),
      'no fax audit row: trpdd_log_user(-1,"fax",...) is written only on a successful send',
    ).toBe('0');

    // THE DEBUG PATH, pinned unreachable (13-boundaries.md:240). Behind
    // `if ($is_debug)` at auto_fax_results.php:265, api/ci_main_fax.php:80 mails
    // the result PDF to a hardcoded personal address. `$is_debug` is never
    // assigned anywhere in that cron -- only the three manual efax/box actions
    // set it, from $_REQUEST['rsdb'] -- so the cron cannot reach it. Queried BY
    // RECIPIENT, never by total count: mailhog is shared with every other spec (D117).
    expect(
      mailSubjectsTo(DEBUG_FAX_ADDR),
      `the cron mailed a result to the hardcoded personal address ${DEBUG_FAX_ADDR}`,
    ).toEqual([]);

    // ------------------------------------------------------- WHAT THE USER SEES
    //
    // D382: every database assertion above is KEPT. This adds the ledger as the
    // lab administrator actually reads it. D386 is satisfied by construction --
    // the cron ran in a container, so there is no post-click DOM here at all;
    // `faxLogRow` is a fresh server GET.
    const adminPage = await pageAs(FAX_LOG_PERSONA);
    const row = await faxLogRow(adminPage, b.assen);
    await expect(row, `the fax log lists ${b.assen} exactly once`).toHaveCount(1);

    // THE DESTINATION, on screen. Not a restatement of the etag assertion above:
    // this is the value after it has travelled ci_clinic.`cli_om-fax` -> the
    // cron's queue insert -> ci_box_upload.etag -> the To column, and a break at
    // any link in that chain (wrong clinic column read, wrong row joined,
    // masked/blanked rendering) shows the operator the wrong fax number while
    // the ledger still reads correctly.
    await expect(
      row.getByRole('cell', { name: FAX_NUMBER, exact: true }),
      `the To column must show ${FAX_NUMBER}, the number this clinic opted in with`,
    ).toHaveCount(1);
    // WHOSE result is on that cover page. The queue re-joins the specimen by
    // `mid` against the table for `btype` (:139-151); a wrong btype or a wrong
    // mid puts SOMEBODY ELSE'S name against this fax number.
    await expect(
      row.getByRole('cell', { name: `${patientLast}, Synthetic`, exact: true }),
      'the row names the patient whose result is being faxed',
    ).toHaveCount(1);
    // The Clinic column is `ci_batches.requesting_practice` -- a DIFFERENT
    // column from the `ci_box_upload.sender` asserted above, and the two are
    // written by different code. Disagreement between them is a real defect.
    await expect(
      row.getByRole('cell', { name: 'E2E Test Clinic Two', exact: true }),
      'the row names the ordering clinic',
    ).toHaveCount(1);

    // THE HAZARD, MADE VISIBLE. `:184` prints the literal `sent` iff
    // `status == 1`. status is 6 -- attempted and failed -- so the operator is
    // shown a BLANK Status cell: the screen draws no difference between
    // "waiting", "attempted" and "failed". What it must never do is claim a
    // delivery the egress-denied network cannot have made. This goes red the
    // day the send leg marks a fax sent before the provider accepted it.
    await expect(
      row.getByRole('cell', { name: 'sent', exact: true }),
      `the fax log tells the operator ${b.assen} was SENT, on a network that cannot `
      + `have delivered it (ci_box_upload.status is ${status} -- attempted, failed)`,
    ).toHaveCount(0);

    // ------------------------------------------------------- WHO MAY SEE IT
    //
    // This one screen carries the patient name, the ordering clinic and the
    // destination fax number for every specimen in the NETWORK -- the query has
    // no lab filter. The persona that RELEASED this very report is refused it,
    // and that is asserted here rather than assumed.
    //
    // THE ASSERTION IS MECHANISM-INDEPENDENT ON PURPOSE. Three different layers
    // can refuse this URL and they do not agree on a string -- see the block
    // comment on FAX_LOG. What is asserted is therefore (a) the ledger did NOT
    // render, which is the security claim itself, against the SAME URL the
    // administrator seat rendered it from four assertions ago (D141: the
    // positive control is in-test and on the same screen), and (b) that the
    // page was a REFUSAL and not a blank or broken render, accepting either
    // wp_die wording. Pinning one string would go green the day the product
    // moves the refusal from one layer to the other.
    const leadPage = await pageAs(tenant.login);
    await leadPage.goto(`${FAX_LOG}&s=${b.assen}`);
    const fused = { timeout: BEFORE_BOUNCE_MS }; // D392 -- the denial is on a 3 s fuse.
    await expect(
      leadPage.getByRole('columnheader', { name: 'Accession', exact: true }),
      `${tenant.login} reached the fax log and can read every clinic's fax destinations`,
    ).toHaveCount(0, fused);
    await expect(
      leadPage.getByText(
        /Sorry, you are not allowed to access this page\.|You do not have permission to access this page\./,
      ),
      `${tenant.login} released this report and must be REFUSED the fax ledger -- neither `
      + 'wp_die is on this page, so the ledger is simply absent rather than denied',
    ).toHaveCount(1, fused);

    console.log(
      `S-DST-01 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
      + `mid=${b.mid} assen=${b.assen} queue.status=${status} etag=${etag}`,
    );
  });

  shared('S-DST-02 a clinic that did not opt in is skipped silently, with no ledger row at all', async ({
    seedRequisition, tenantRequest, pageAs, db, tenant, runKey,
  }) => {
    const f = infra(db);

    // THE CONTROL LIVES INSIDE THE TEST. A negative that only says "no row was
    // written" passes just as well when the cron never ran, when the token gate
    // shut it down, or when the toggle was off -- eleven such tests passed
    // against a fully broken app (D141). So one specimen goes to the opted-in
    // clinic and one to the clinic that did not opt in, they are released the
    // same way, and ONE cron pass judges both.
    const optedIn = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, `Dstsyn${runKey}b`, f.clinic2Id, f.doc2Id,
    );
    const notOptedIn = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, `Dstsyn${runKey}c`, f.clinicId, f.docId,
    );

    // The gate under test, read from the rows themselves rather than assumed:
    // auto_fax_results.php:113-114 requires cli_faxres='1' AND a non-empty
    // `cli_om-fax`. Clinic 1 satisfies neither.
    const [[faxres1, fax1, faxres2, fax2]] = db.query(
      `SELECT (SELECT cli_faxres FROM ci_clinic WHERE cID=${Number(f.clinicId)}),
              (SELECT \`cli_om-fax\` FROM ci_clinic WHERE cID=${Number(f.clinicId)}),
              (SELECT cli_faxres FROM ci_clinic WHERE cID=${Number(f.clinic2Id)}),
              (SELECT \`cli_om-fax\` FROM ci_clinic WHERE cID=${Number(f.clinic2Id)})`,
    );
    expect([faxres1, fax1], 'the subject clinic has NOT opted in to result faxing').toEqual(['0', '']);
    expect([faxres2, fax2], 'the control clinic HAS opted in').toEqual(['1', FAX_NUMBER]);

    runCron('ci_auto_fax_results');

    // The control proves the cron ran and reached the clinic gate.
    expect(
      queueRows(db, optedIn.mid).length,
      'control: the opted-in clinic was queued in this same cron pass -- without this '
      + 'the negative below proves nothing',
    ).toBe(1);

    // The scenario: silently skipped. No delivery, no error, no ledger row.
    expect(
      queueRows(db, notOptedIn.mid),
      `a clinic with cli_faxres='0' produced a fax-queue row for ${notOptedIn.assen}`,
    ).toEqual([]);
    expect(
      faxed(db, notOptedIn.assen),
      `ci_reports.faxed for ${notOptedIn.assen} -- a skipped report is never marked faxed`,
    ).toBe('0');
    // And nothing was recorded anywhere else either: no audit row, no error row.
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_log_users WHERE mid=${Db.q(notOptedIn.mid)} AND user_action='fax'`,
      ),
      'no fax audit row for the skipped specimen',
    ).toBe('0');

    // ------------------------------------------------------- WHAT THE USER SEES
    //
    // D382: the database assertions above are kept. "Silently skipped" is a
    // claim about a SCREEN, and this is the screen it is a claim about.
    //
    // Both halves come off ONE administrator session through the app's own
    // search box, in that order, so the negative cannot pass merely because the
    // page failed to draw: the positive proves the same URL shape, the same
    // search and the same table DO surface a queued fax (D141 -- the control
    // lives inside the test).
    const adminPage = await pageAs(FAX_LOG_PERSONA);
    await expect(
      await faxLogRow(adminPage, optedIn.assen),
      `control: the opted-in clinic's ${optedIn.assen} IS on the fax log after this cron pass`,
    ).toHaveCount(1);
    await expect(
      await faxLogRow(adminPage, notOptedIn.assen),
      `${notOptedIn.assen} appears on the fax log although its clinic never opted in -- `
      + 'the operator would see a result going out to a clinic that asked for none',
    ).toHaveCount(0);
  });

  /**
   * THE CORRECTION THAT NEVER LEAVES THE BUILDING.
   *
   * A fax-only clinic receives the FIRST release of a tox report. The lab then
   * finds the result wrong, reverses it, re-results it, marks the report
   * `final_results=3` ("Amended Report") and releases it again. Inside the LIS
   * every screen now reads correct: status `C`, an amended report on file, an
   * audit trail of `approve_rs, reverse_rs, editapprove_rs_cd`. The clinic still
   * holds the ORIGINAL, and nothing in the product will ever send it the
   * correction. That is the delivery half of a wrong-result event and it is
   * exactly the ledger-lies-about-delivery class this file exists for (D091).
   *
   * THE MECHANISM, one line: `inc/crons/auto_fax_results.php:122`
   *
   *     Left JOIn ci_box_upload bu ON bu.mid = ba.mid and bu.type='{$type_result}'
   *     ...
   *     and bu.id is null
   *
   * The queue leg admits a specimen only while it has NO `rs_efax` row at all.
   * The first pass writes one and never deletes it -- measured in S-DST-01, it
   * settles at `status=6` because the egress-denied network refuses the send --
   * so `bu.id is null` is false for that `mid` FOREVER. Re-release moves
   * `estatus` back to `C` and leaves `faxed=0`, which satisfies every OTHER
   * clause of the same query; the join is the only thing holding the correction
   * back. Nothing anywhere clears the row on reverse: `grep -n "ci_box_upload"`
   * over `parts/tox/results.php` returns nothing.
   *
   * WHAT WOULD BE CORRECT, and it is what the assertion states: the amended
   * report reaches the channel the original went out on -- one send raised
   * AFTER the amendment on the ledger the operator's fax log renders.
   * `shared.fail()` records that today it does not. The day the join learns
   * about `re.modified` -- or the day reverse clears the ledger row -- this
   * reports "Expected to fail, but passed".
   *
   * WHY A NEW-SEND ID AND NOT A ROW COUNT: those two fixes disagree about the
   * count. The join fix leaves the original row and adds a second (2 rows); the
   * ledger-clearing fix deletes the original and adds one (1 row), which is
   * byte-identical to today's broken 1. A count assertion would stay red under
   * the second fix, `shared.fail()` would absorb it, and the pin would report
   * green over a FIXED product. The id high-water mark is what both fixes move.
   * The operator's fax-log row count is not asserted for the same reason -- it
   * renders this same ledger, so it is 1 under one fix and 2 under the other.
   *
   * NOT DRIVEN: the batch row's manual `Fax PDF` control (`a.fax_pdf`). A lab
   * that knows to click it can re-send by hand; the defect is that the AUTOMATIC
   * channel, which is the one the clinic was opted in to, stays silent. Clicking
   * it here would write the second row this test asserts is missing.
   *
   * MARKER PLACEMENT (D416). `shared.fail()` is called AFTER the seed, the first
   * cron pass and the amendment, immediately before the second cron pass it pins.
   * A marker at the top of the body swallows every precondition below it: a
   * broken seed, a dead first pass or a refused amendment would each be reported
   * as the expected failure for as long as the file lives. Placed where it is,
   * every line above it reds as itself and still names what it proves.
   */
  shared('an amended report is delivered to the clinic that already holds the original', async ({
    seedRequisition, tenantRequest, authedPage, db, tenant, runKey,
  }) => {
    const f = infra(db);
    const patientLast = `Dstamd${runKey}f`;
    const b = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, patientLast, f.clinic2Id, f.doc2Id,
    );

    // ------------------------------------------------------- the FIRST send
    runCron('ci_auto_fax_results');
    const firstPass = queueRows(db, b.mid);
    expect(
      firstPass.length,
      `the original release of ${b.assen} queued exactly one fax to the opted-in clinic -- `
      + 'without this there is no "already delivered" and the pin below is about nothing',
    ).toBe(1);
    const originalRowType = firstPass[0][0];
    // The high-water mark of the ledger BEFORE the amendment. What gets pinned below is the
    // delivery EVENT, not a row count: the docblock names two admissible fixes, and under one
    // of them -- clearing the fax-queue row on reverse -- the first row is deleted and the
    // second cron writes one, leaving `queueRows().length` at 1, byte-identical to today's
    // broken value. A count assertion therefore cannot flip under that fix. A ci_box_upload
    // row created AFTER the amendment is a new send under BOTH fixes, and there is none today.
    const ledgerBeforeAmendment = db.scalar(
      `SELECT COALESCE(MAX(id), 0) FROM ci_box_upload WHERE mid = ${Db.q(b.mid)}`,
    );

    // ------------------------------------------- the amendment, on the screen
    // Driven end to end as the reviewer: reverse the released report from the
    // queue, re-open it through the Edit link the reversal restores, mark the
    // maturity Amended and sign it off again. rev.spec.ts S-REV-04 owns the
    // estatus C -> M -> C traversal itself; what is new here is what the
    // DISTRIBUTION side does with the result of that traversal.
    const released = await openToxQueue(authedPage, b.assen);
    await expect(
      released.getByText('Reverse', { exact: true }),
      'a released report offers the reviewer Reverse',
    ).toBeVisible();
    await released.getByText('Reverse', { exact: true }).click();

    // D386: the control's label flips client-side on any 200, so the Edit link
    // is taken off a SERVER render of the reopened row, not off that DOM.
    const reopened = await reloadToxQueue(authedPage, b.assen);
    await expect(
      reopened.getByRole('link', { name: 'Edit', exact: true }),
      'reversing the report restores the Edit link the reviewer amends through',
    ).toBeVisible();
    await reopened.getByRole('link', { name: 'Edit', exact: true }).click();

    const amended = authedPage.getByRole('radio', { name: 'Amended Report' });
    await expect(amended, 'the results-edit screen offers the Amended Report maturity')
      .toBeVisible();
    await amended.check();
    // `name="approval"`, not `name="submit"`: Submit saves the analytes and
    // leaves the report reopened; Approval is the sign-off that re-releases it
    // (inc/actions/ci_save_result_edit.php:113).
    await authedPage.getByRole('button', { name: 'Approve', exact: true }).click();

    // --------------------------------------------- what the reviewer sees now
    const rereleased = await openToxQueue(authedPage, b.assen);
    await expect(
      rereleased.locator('td.statuscol'),
      `${b.assen} reads as released again on the reviewer's queue -- which is the whole `
      + 'point: nothing on this screen tells the lab the clinic still holds the old result',
    ).toHaveText('C');
    await expect(
      rereleased.getByText('Reverse', { exact: true }),
      'and the row offers Reverse, the control only a released report carries',
    ).toBeVisible();

    // ------------------------------------------------------- and in the row
    const [[estatus, finalResults, faxedNow]] = db.query(
      `SELECT estatus, final_results, faxed FROM ci_reports WHERE id = ${Db.q(b.reportId)}`,
    );
    expect(
      [estatus, finalResults],
      'ci_reports [estatus, final_results] -- the report is released AND marked amended (3), '
      + 'so the cron\'s estatus and faxed clauses are both satisfied and the join is the only '
      + 'thing left that could hold the correction back',
    ).toEqual(['C', '3']);
    expect(
      faxedNow,
      'ci_reports.faxed is still 0, so nothing has been recorded as sent since the amendment',
    ).toBe('0');

    // ------------------------------------------------------------- THE PIN
    // Called HERE, not at the top of the body: the seed, the first fax pass, the
    // amendment cycle and the released/amended read-back above are preconditions
    // and controls, and each must be able to go RED on its own (D416).

    runCron('ci_auto_fax_results');

    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_box_upload
          WHERE mid = ${Db.q(b.mid)} AND id > ${ledgerBeforeAmendment}`,
      ),
      `${b.assen} was released, faxed to a fax-only clinic, then AMENDED and released again, `
      + `and a second ${originalRowType} pass has run. The clinic must be sent the correction, `
      + 'so the ledger the operator\'s fax log renders must carry one send raised after the '
      + 'amendment. It carries none, because auto_fax_results.php:122 admits a specimen only '
      + 'while it has NO fax-queue row at all and the row from the first send is never cleared. '
      + 'The clinic keeps treating the patient on the result the laboratory has already '
      + 'withdrawn, and no screen in the LIS says so -- the reviewer\'s queue, asserted above, '
      + 'reads a plain released C',
    ).toBe('1');
  });

  /**
   * S-DST-02b -- THE CLINIC THAT HAS A FAX NUMBER ON FILE AND SAID DON'T.
   *
   * D382: this ADDS to S-DST-02 above and it is not the same claim, because the
   * two clauses S-DST-02 puts under test are not separable there. The cron's
   * clinic gate is two conditions (`inc/crons/auto_fax_results.php:120-121`):
   *
   *     and cli.cli_faxres = '1'
   *     and cli.`cli_om-fax` != ''
   *
   * and S-DST-02's subject clinic fails BOTH -- it asserts exactly that, as a
   * precondition: `expect([faxres1, fax1], ...).toEqual(['0', ''])`. So EITHER
   * clause on its own is sufficient to produce the outcome S-DST-02 observes,
   * and deleting the opt-in clause leaves the empty-number clause doing all the
   * work. Measured: that deletion survives the suite.
   *
   * They are not the same rule and they do not fail the same way. An empty
   * `cli_om-fax` means the laboratory HAS NO NUMBER to fax to -- the channel is
   * unusable and the skip is arithmetic. `cli_faxres='0'` with a number on file
   * means the clinic CAN be faxed and has asked not to be: a practice that
   * takes results through the portal, or through its EMR, or that shares a fax
   * machine with a waiting room. Faxing that clinic is an unauthorised
   * disclosure of a patient's result to a destination the practice withdrew,
   * and the number sitting in `cli_om-fax` is exactly what makes it possible.
   * That is the rule this test isolates.
   *
   * HOW IT ISOLATES IT. One specimen, one clinic, ONE cron pass on each side of
   * a single-column change. Clinic 2 is armed by this describe's `beforeAll`
   * with both a number and the opt-in; this test switches the OPT-IN off and
   * leaves the NUMBER in place, so the second clause is satisfied throughout and
   * only the first can account for the difference.
   *
   * THE CONTROL IS THE SAME SPECIMEN, AFTER (D141). A negative that only says
   * "no row was written" passes just as well when the cron never ran, when the
   * token gate shut it down, or when the toggle was off -- so the opt-in is put
   * BACK and the cron is run AGAIN, and the row that appears is for the same
   * mid, the same clinic and the same fax number. Nothing differs between the
   * two passes except `cli_faxres`, which is what makes the first pass's silence
   * attributable to it. That also leaves the clinic in the state the rest of
   * this describe armed, so the ordering of this test is not load-bearing --
   * though it is written last, and the `afterAll` teardown sets `cli_faxres='0'`
   * regardless, so an abort mid-test cannot arm anything for anyone.
   *
   * ARMING, NOT DRIVING. The opt-in is a per-clinic configuration the lab
   * administrator maintains, and the subject here is the CRON, not the clinic
   * form -- so it is written the same way this describe's `beforeAll` writes it
   * and read back before it is relied on. What is driven through the app is
   * everything the claim is about: the specimen, its release, the cron, and the
   * fax log the operator reads.
   */
  shared('S-DST-02b a clinic that kept its fax number but switched result faxing OFF is not faxed', async ({
    seedRequisition, tenantRequest, pageAs, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);
    const clinic2 = Number(f.clinic2Id);
    const optIn = (): string[] => db.query(
      `SELECT cli_faxres, \`cli_om-fax\` FROM ci_clinic WHERE cID = ${clinic2}`,
    )[0];

    const b = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, `Dstoff${runKey}g`, f.clinic2Id, f.doc2Id,
    );
    expect(queueRows(db, b.mid).length, 'no queue row exists before either cron pass').toBe(0);
    expect(
      optIn(),
      'this describe armed clinic 2 with BOTH a fax number and the opt-in -- without that '
      + 'starting state there is nothing to switch off',
    ).toEqual(['1', FAX_NUMBER]);

    // --------------------------------------------- the practice opts back out
    db.query(`UPDATE ci_clinic SET cli_faxres = '0' WHERE cID = ${clinic2}`);
    expect(
      optIn(),
      'the clinic must still hold its fax number: with the number cleared as well, the '
      + 'second clause of the gate would account for the silence below and this test would '
      + 'be S-DST-02 again',
    ).toEqual(['0', FAX_NUMBER]);

    runCron('ci_auto_fax_results');

    // ------------------------------------------------------------- THE CLAIM
    expect(
      queueRows(db, b.mid),
      `${b.assen} was queued to fax ${FAX_NUMBER}. That clinic holds the number but has `
      + 'result faxing switched OFF, so this patient\'s result is on its way to a '
      + 'destination the practice withdrew -- and nothing about the number being on file '
      + 'is consent to use it',
    ).toEqual([]);
    expect(
      faxed(db, b.assen),
      `ci_reports.faxed for ${b.assen} -- a report the clinic opted out of is never marked faxed`,
    ).toBe('0');
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_log_users WHERE user_action='fax' AND mid=${Db.q(b.mid)}`),
      'no fax audit row for a specimen that was never eligible to be faxed',
    ).toBe('0');

    // ------------------------------------------------------- WHAT THE OPERATOR SEES
    const adminPage = await pageAs(FAX_LOG_PERSONA);
    await expect(
      await faxLogRow(adminPage, b.assen),
      `${b.assen} is on the fax log although its clinic switched result faxing off -- the `
      + 'operator is looking at a result going out to a practice that asked for none',
    ).toHaveCount(0);

    // ------------------------------------------- the control: switch it back
    db.query(`UPDATE ci_clinic SET cli_faxres = '1' WHERE cID = ${clinic2}`);
    expect(optIn(), 'the clinic is opted back in, number unchanged').toEqual(['1', FAX_NUMBER]);

    runCron('ci_auto_fax_results');

    const rows = queueRows(db, b.mid);
    expect(
      rows.length,
      `control: with ONLY cli_faxres changed back, the same specimen ${b.assen} on the same `
      + 'clinic queues on the very next cron pass. If this is 0 the cron was never going to '
      + 'queue it under any setting and the assertion above proved nothing',
    ).toBe(1);
    expect(
      rows[0][2],
      'ci_box_upload.etag -- the control row carries the number that was on file the whole time',
    ).toBe(FAX_NUMBER);
    await expect(
      await faxLogRow(adminPage, b.assen),
      `control: ${b.assen} IS on the operator's fax log once the clinic has opted back in`,
    ).toHaveCount(1);

    console.log(
      `S-DST-02b worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
      + `mid=${b.mid} assen=${b.assen} queue.status=${rows[0][1]}`,
    );
  });

  /**
   * S-DST-02c -- THE RESULT THAT GOES OUT TWICE.
   *
   * D382: this ADDS to S-DST-01/02/02b. Those three all turn on the CLINIC
   * gate; this one turns on the only clause in the queue query that is about
   * the REPORT'S OWN DELIVERY HISTORY (`inc/crons/auto_fax_results.php:118`):
   *
   *     where re.estatus='C' and re.faxed=0
   *
   * WHY IT IS NOT ALREADY COVERED, and the measurement is the point. In the
   * flow every other test in this file drives, `re.faxed` never leaves 0 --
   * `:276` sets it to 1 only after the provider ACCEPTED the fax, which an
   * egress-denied network never lets happen (S-DST-01 asserts exactly that).
   * So `re.faxed=0` is true for every specimen the suite has ever put through
   * this cron, and the clause has been dead weight from the suite's point of
   * view: deleting it changes nothing anywhere else in this file.
   *
   * WHAT IT GUARDS. `faxed=1` is the laboratory's record that this patient's
   * result has ALREADY been put on the clinic's fax machine. Sending it again
   * is a second, unasked-for disclosure of a patient's toxicology result to a
   * device that sits wherever the practice put it -- and on a channel with no
   * delivery confirmation, a duplicate is indistinguishable to the practice
   * from a NEW result on the same patient. `bu.id is null` (the ledger clause,
   * :122) normally hides this: the row from the first send is still there. It
   * is not always there -- the manual `Fax PDF` screen DELETES every
   * `rs_efax` row for a batch before it re-queues
   * (`inc/actions/ci_upload_result_pdf_to_efax.php:266`, unrestricted by the
   * `ids` the operator ticked), so an operator who hand-faxes ONE specimen in a
   * batch clears the ledger for all of them. With the ledger gone, `re.faxed=0`
   * is the only thing left between an already-delivered result and a second
   * send. This test puts the specimen in exactly that state.
   *
   * THE ONE VALUE THAT IS WRITTEN DIRECTLY, and why there is no alternative.
   * `ci_reports.faxed` is set to 1 in exactly three places in the whole plugin
   * (measured, `grep -rn "faxed = 1\|faxed=1"`): this cron's send leg at :276,
   * the manual efax screen at `ci_upload_result_pdf_to_efax.php:186`, and a
   * one-off maintenance script (`inc/import.php:446`,
   * `tr_action=script_import&fix_log_faxed`, pcr only). The first two are both
   * behind `CI_Fax_RingCentral::send()`, which is raw curl to
   * platform.ringcentral.com and can never succeed here (D091). So the state
   * "this result has already been faxed" has NO app path on this stack, and the
   * UPDATE below writes the same column to the same value the send leg writes,
   * nothing else. Everything the claim is about -- the specimen, its release,
   * the cron, and the operator's fax log -- is driven through the app.
   *
   * THE CONTROL IS A SECOND SPECIMEN, JUDGED BY THE SAME CRON PASS (D141).
   * A negative that only says "no row was written" passes just as well when the
   * cron never ran, when the token gate shut it down or when the toggle was
   * off. So two specimens go to the same opted-in clinic, are released the same
   * way, and differ in ONE column -- `ci_reports.faxed` -- and one cron pass
   * judges both. The ledger is asserted EMPTY for both beforehand, so `:122`
   * is satisfied on both sides and cannot be what separates them.
   */
  shared('S-DST-02c a report the laboratory has already faxed is never sent to the clinic twice', async ({
    seedRequisition, tenantRequest, pageAs, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);

    const alreadySent = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, `Dstfxd${runKey}h`, f.clinic2Id, f.doc2Id,
    );
    const neverSent = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, `Dstfxd${runKey}i`, f.clinic2Id, f.doc2Id,
    );

    // THE LEDGER CLAUSE IS SATISFIED ON BOTH SIDES. Without this the difference
    // below could be `bu.id is null` doing the work instead, which is a
    // different rule and has its own coverage.
    expect(
      [queueRows(db, alreadySent.mid), queueRows(db, neverSent.mid)],
      'neither specimen has a fax-queue row yet, so the ledger clause at :122 admits both '
      + 'and cannot account for the difference this test measures',
    ).toEqual([[], []]);

    // THE STATE WITH NO APP PATH: the laboratory's record that this result has
    // already gone out. The same column, set to the same value the send leg at
    // auto_fax_results.php:276 sets it to, and nothing else -- see the docblock.
    db.query(`UPDATE ci_reports SET faxed = '1' WHERE id = ${Db.q(alreadySent.reportId)}`);
    expect(
      [faxed(db, alreadySent.assen), faxed(db, neverSent.assen)],
      `ci_reports.faxed for [${alreadySent.assen}, ${neverSent.assen}] -- one result is on `
      + 'record as delivered and the other is not, and that is the ONLY column that differs '
      + 'between the two specimens',
    ).toEqual(['1', '0']);

    runCron('ci_auto_fax_results');

    // The control proves the cron ran, held its token, found the clinic opted
    // in and queued a fax on this very pass.
    const control = queueRows(db, neverSent.mid);
    expect(
      control.length,
      `control: ${neverSent.assen}, identical in every other column, was queued by this same `
      + 'cron pass. If this is 0 the cron queued nothing under any setting and the claim '
      + 'below proves nothing',
    ).toBe(1);
    expect(
      control[0][2],
      'ci_box_upload.etag -- the control really is routed to the fax number under test',
    ).toBe(FAX_NUMBER);

    // ------------------------------------------------------------- THE CLAIM
    expect(
      queueRows(db, alreadySent.mid),
      `${alreadySent.assen} is already on record as faxed to ${FAX_NUMBER}, and the cron has `
      + 'queued it again. The practice receives this patient\'s toxicology result a second '
      + 'time on a channel that carries no delivery confirmation, so it cannot tell a '
      + 'duplicate from a new result on the same patient -- and the laboratory has no record '
      + 'that it sent one, because nothing on the LIS side distinguishes the two sends',
    ).toEqual([]);
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_log_users WHERE user_action='fax' AND mid=${Db.q(alreadySent.mid)}`,
      ),
      `a second fax audit row for ${alreadySent.assen}`,
    ).toBe('0');

    // ------------------------------------------------------- WHAT THE OPERATOR SEES
    //
    // D382: the database assertions above are kept. The fax log is where a lab
    // administrator would notice a result going out twice, and both halves come
    // off ONE session through the app's own search box in this order, so the
    // negative cannot pass merely because the page failed to draw.
    const adminPage = await pageAs(FAX_LOG_PERSONA);
    await expect(
      await faxLogRow(adminPage, neverSent.assen),
      `control: ${neverSent.assen} IS on the operator's fax log after this cron pass`,
    ).toHaveCount(1);
    await expect(
      await faxLogRow(adminPage, alreadySent.assen),
      `${alreadySent.assen} is back on the operator's fax log although the laboratory already `
      + 'recorded it as faxed -- the same result is going to the same practice a second time',
    ).toHaveCount(0);

    console.log(
      `S-DST-02c worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
      + `alreadySent=${alreadySent.assen}(mid ${alreadySent.mid}) `
      + `control=${neverSent.assen}(mid ${neverSent.mid}) control.status=${control[0][1]}`,
    );
  });

  /**
   * S-DST-02d -- THE FAX THE OPERATOR STAGED AND NOBODY EVER SENT.
   *
   * `inc/crons/auto_fax_results.php` has TWO legs, and only the first has ever
   * been driven. The first INSERTS a queue row for a specimen that has none
   * (:133-161, what S-DST-01 asserts). The second is a RECOVERY leg (:167-171),
   * and the author's own comment at :66 states its purpose:
   *
   *     $outtimebyhand = strtotime($datetimenow) - 1000; //out 1000s then update status to 5
   *     ...
   *     } else if ($re['status'] == 0 && strtotime($re['created']) < $outtimebyhand) {
   *         $wpdb->query("update ci_box_upload set status = 5 where id = {$re['box_id']} ...");
   *
   * i.e. a queue row that has sat at status 0 for more than ~17 minutes is
   * promoted to 5, which is what the send leg at :193 selects on. Status 0 is
   * not a state this cron can produce -- its own insert writes 5. It is what
   * the MANUAL `Fax PDF` screen leaves behind: that screen runs in two phases,
   * and only the first (`inc/actions/ci_upload_result_pdf_to_efax.php:294-348`,
   * the frame document) stages the rows. The send phase is a SEPARATE XHR its
   * own script fires on load (:396-436, `upload: true`). Close the thickbox,
   * lose the tab, block the script -- the row is staged at the schema default
   * `status` of 0 (`inc/install.php:2023`) and no send was ever attempted.
   *
   * THE DEFECT. The recovery leg cannot run, because the same query's join
   * clause at :122 -- `and bu.id is null` -- admits a specimen only while it
   * has NO `rs_efax` row at all, and this specimen has one. So :167 is
   * unreachable for every specimen it was written for, and a result the
   * operator staged for the clinic silently never leaves the building. The
   * automatic channel is precisely the mechanism that exists so nobody has to
   * watch, and the clinic is left holding no result at all, on a specimen the
   * laboratory believes it has dealt with.
   *
   * WHAT `fail()` PINS. The assertion states the CORRECT behaviour -- the
   * staged fax leaves status 0 on the next cron pass, which is what :167 says
   * it should do -- and records that today it does not. The day :122 learns
   * about status 0, or the day the manual screen stops staging rows it may
   * never send, this reports "Expected to fail, but passed".
   *
   * THE TWO THINGS THAT ARE NOT DRIVEN THROUGH A BROWSER, and why:
   *
   *  1. THE FRAME LOAD is `tenantRequest.get()` on the exact URL
   *     `js/admin_result.js:129-138` builds from the `a.fax_pdf` control's
   *     `data-batch_id` (`parts/tox/results_list.php:329`, rendered whenever
   *     `can_fax` -- which `inc/functions.php:4988` sets true for every
   *     persona). Loading it in a page instead would let its own script fire
   *     the send XHR, which is the RACE this scenario is the losing side of;
   *     an HTTP GET is that same document with the script never running, which
   *     is deterministic and is exactly what "the operator closed it" means.
   *  2. THE ~17 MINUTES are arranged by moving `ci_box_upload.created` back.
   *     No test can wait them out, and `created` is the only value moved --
   *     `status` is left exactly where the app put it.
   *
   * EVERY PRECONDITION IS OUTSIDE THE MARKER (D416/mechanism 6): the release,
   * the staging, the status the staging left, the backdate, and the operator's
   * view of the staged row all have to be able to go red as themselves.
   */
  shared('S-DST-02d a fax the operator staged by hand and never sent is picked up again', async ({
    seedRequisition, tenantRequest, pageAs, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);
    const b = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, `Dststg${runKey}j`, f.clinic2Id, f.doc2Id,
    );
    expect(queueRows(db, b.mid).length, 'nothing is staged for this specimen yet').toBe(0);

    // ------------------------------------- the operator opens Fax PDF...
    const frame = await tenantRequest.get(
      `/wp-admin/admin.php?tr_action=ci_upload_result_pdf_to_efax`
      + `&batch_id=${b.batchId}&lab_id=0&ids=`,
    );
    // The staging phase opens with an UNRESTRICTED delete -- `:265-273` drops
    // every `rs_efax` row for this `batch_id` regardless of the `ids` the
    // operator ticked -- before it re-inserts. Contained here only because
    // `fixtures/batch-id.ts` gives every test its own batch id; a spec that
    // reused one across specimens would have the other specimen's ledger row
    // deleted out from under it by this GET.
    expect(frame.status(), 'the Fax PDF frame answers 200').toBe(200);
    // Not a status check alone: this screen answers 200 with a token-expired
    // notice instead of the queue when the provider is unauthorised (:391-393),
    // and that page stages nothing. The armed token is this describe's
    // beforeAll, and the string proves it held for THIS request.
    expect(
      await frame.text(),
      'the Fax PDF frame reported the RingCentral token expired, so it staged nothing and '
      + 'everything below would be about an empty queue',
    ).not.toContain('token is expired');

    // ...AND CLOSES IT BEFORE THE SEND. This is the state the whole test is
    // about, so it is read off the row rather than assumed.
    const staged = queueRows(db, b.mid);
    expect(
      staged.length,
      `opening Fax PDF stages exactly one fax for ${b.assen}`,
    ).toBe(1);
    const [stagedType, stagedStatus, stagedEtag] = staged[0];
    expect(stagedType, 'ci_box_upload.type -- the tox fax channel tag').toBe('rs_efax');
    expect(stagedEtag, 'ci_box_upload.etag carries the clinic fax number').toBe(FAX_NUMBER);
    expect(
      stagedStatus,
      'ci_box_upload.status must be 0 -- the schema default the staging phase leaves behind. '
      + '5 would mean the row is already queued for sending and 1/6 that a send was attempted, '
      + 'and under any of those the recovery leg this test is about is not the code in question',
    ).toBe('0');
    expect(
      faxed(db, b.assen),
      `ci_reports.faxed for ${b.assen} -- nothing has been sent, so the clinic holds nothing`,
    ).toBe('0');

    // ------------------------------------------------- WHAT THE OPERATOR SEES
    // The staged fax is on the ledger with an EMPTY Status cell: the screen
    // draws no difference between "staged and forgotten" and "waiting its turn",
    // which is why nobody notices.
    const adminPage = await pageAs(FAX_LOG_PERSONA);
    const row = await faxLogRow(adminPage, b.assen);
    await expect(row, `the fax log lists the staged fax for ${b.assen}`).toHaveCount(1);
    await expect(
      row.getByRole('cell', { name: 'sent', exact: true }),
      'a staged fax must not read as sent (parts/instrument/api_ringcentral.php:184 prints '
      + 'the literal `sent` only at status 1)',
    ).toHaveCount(0);

    // -------------------------------------------- seventeen minutes later
    //
    // ON THE APPLICATION'S CLOCK, WHICH IS NOT THE DATABASE'S. All four of
    // these were read in ONE `wp eval`, 2026-08-26, at the same instant:
    //
    //     date_i18n('Y-m-d H:i:s')          -> 2026-08-26 04:12:17
    //     date_i18n('Y-m-d H:i:s', time())  -> 2026-08-26 08:12:17
    //     date('Y-m-d H:i:s')               -> 2026-08-26 08:12:17
    //     SELECT NOW()                      -> 2026-08-26 08:12:17
    //
    // PHP's timezone is UTC and `gmt_offset` is -4, and `date_i18n()` applies
    // that offset ONLY when it is called with no timestamp -- given one it
    // assumes the caller already did (the legacy signature). So the app writes
    // SITE-LOCAL wall clock into DATETIME columns while `NOW()` and `date()`
    // are UTC, and the two frames are four hours apart.
    //
    // `ci_box_upload.created` is written by the NO-ARGUMENT form in both of its
    // writers (`auto_fax_results.php:158` `date_i18n()`,
    // `ci_upload_result_pdf_to_efax.php:329` `Tr_Base_Class_V4::date()`), and
    // :167 reads it back with `strtotime()` and compares it against
    // `$outtimebyhand`, which :66 derives from that same no-argument form. The
    // cron is therefore self-consistent inside the shifted frame -- and a
    // backdate taken from `DATE_SUB(NOW(), ...)` lands four hours in that
    // frame's FUTURE, so the row never ages at all. Measured: this test pinned
    // green under a mutation that removes :122 until the two frames were told
    // apart. The backdate below is computed in the exact arithmetic of :66 and
    // formatted with plain `date()`, which `strtotime()` round-trips exactly.
    const aged = wpEval(
      `global $wpdb; $mid = ${Number(b.mid)};`
      + '$base = strtotime(date_i18n("Y-m-d H:i:s"));'
      + '$t = date("Y-m-d H:i:s", $base - 1200);'
      + '$wpdb->query($wpdb->prepare("update ci_box_upload set created = %s where mid = %d", $t, $mid));'
      + '$r = $wpdb->get_row($wpdb->prepare("select status, created from ci_box_upload where mid = %d", $mid), ARRAY_A);'
      + '$o = ["status" => $r["status"], "created" => $r["created"],'
      + '      "outOfWindow" => strtotime($r["created"]) < (strtotime(date_i18n("Y-m-d H:i:s")) - 1000)];',
    );
    expect(
      [aged.status, aged.outOfWindow],
      `the staged row is untouched at status 0 and, on the clock auto_fax_results.php:66 uses, `
      + `is now older than its 1000 s recovery window (created ${aged.created})`,
    ).toEqual(['0', true]);

    // ------------------------------------------------------------- THE PIN

    runCron('ci_auto_fax_results');

    expect(
      db.scalar(`SELECT status FROM ci_box_upload WHERE mid = ${Db.q(b.mid)}`),
      `${b.assen} was released, the operator staged it for ${FAX_NUMBER} and never completed `
      + 'the send, and it has now sat unsent for longer than the cron\'s own recovery window. '
      + 'auto_fax_results.php:167 exists to pick exactly this row up -- the comment at :66 says '
      + 'so -- but :122 admits a specimen only while it has NO fax-queue row at all, so the '
      + 'recovery leg is unreachable for every row it was written for. The clinic is waiting '
      + 'for a result that will never be sent, and the fax log shows the operator a row that '
      + 'looks exactly like one still waiting its turn',
    ).not.toBe('0');

    console.log(
      `S-DST-02d worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
      + `mid=${b.mid} assen=${b.assen} staged.status=${stagedStatus}`,
    );
  });
});

// ================================================================= S-DST-06
//
// EMR/EHR push. The catalog's outcome is `ci_sync_api_orders.mo_status` moving
// 0 -> 1 on a successful send.

shared.describe('S-DST-06 @dst', () => {
  shared('no EMR connector is enabled, and nothing is recorded as delivered', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }) => {
    // The registry is inc/define_apis.php: each connector declares an option
    // blob (`st`) and the key inside it that switches the connector on
    // (`active`). Read live, with DOING_CRON defined at plugin LOAD time --
    // ci-data-works-arc includes inc/crons.php only then (plug.php:65), so
    // without it EVERY has_action() answers false and the wiring half of this
    // test would be vacuous (D141, the S-BIL-03 lesson).
    const enabled = wpEval(
      '$o = [];'
      + '$blobs = ["tr_hl7_ellkay_st"=>"enable","tr_hl7_athena_api_st"=>"enable",'
      + '"tr_sftp_experity_st"=>"enable","tr_sftp_emr_st"=>"enable",'
      + '"_tr_elink_settings"=>"active","tr_api_athena_intralab_st"=>"enable"];'
      + 'foreach ($blobs as $name=>$key) { $v = get_option($name, array());'
      + '$o[$name] = (bool)(is_array($v) && !empty($v[$key])); }',
    );
    expect(
      enabled,
      'an EMR connector has been switched on -- S-DST-06 is now reachable and this '
      + 'test plus its fixme must be rewritten against the real push',
    ).toEqual({
      tr_hl7_ellkay_st: false,
      tr_hl7_athena_api_st: false,
      tr_sftp_experity_st: false,
      tr_sftp_emr_st: false,
      _tr_elink_settings: false,
      tr_api_athena_intralab_st: false,
    });

    // The crons themselves ARE wired -- this is not a dead-cron flow like
    // S-BIL-05. What is missing is configuration, not code.
    const h = cronHandlers([
      'cron_api_sftp_emr',        // eClinicalWorks, api id -32
      'cron_api_elink',           // Elink, api id -2
      'cron_api_sftp_experity',   // Experity, api id -11
      'cron_all_api',             // the shared dispatcher (Ellkay, Athena Intralab, ...)
    ]);
    expect(h, 'the EMR connector crons are registered').toEqual({
      cron_api_sftp_emr: true,
      cron_api_elink: true,
      cron_api_sftp_experity: true,
      cron_all_api: true,
    });

    // The second half of the gate is per-clinic routing meta (define_apis.php
    // `add_fields`, flow-result-distribution.md:132). No clinic carries one, so
    // even an enabled connector would route nothing.
    expect(
      db.scalar(
        "SELECT COUNT(*) FROM ci_clinic WHERE COALESCE(cli_elink_id,'') <> '' "
        + "OR COALESCE(cli_sftp_emr_code,'') <> '' "
        + "OR COALESCE(cli_athena_departmentid,'') <> ''",
      ),
      'clinics carrying an EMR routing mapping',
    ).toBe('0');

    // End to end against a real released specimen, not just by introspection:
    // a report that IS eligible on every other count must still acquire no EMR
    // ledger row, and above all must never be recorded as delivered.
    const f = infra(db);
    const b = await seedReleasedTox(
      seedRequisition, tenantRequest, db, tenant, `Dstsyn${runKey}d`, f.clinicId, f.docId,
    );
    const [[mine, sent]] = db.query(
      `SELECT (SELECT COUNT(*) FROM ci_sync_api_orders WHERE mo_mid = ${Db.q(b.mid)}),
              (SELECT COUNT(*) FROM ci_sync_api_orders WHERE mo_mid = ${Db.q(b.mid)} AND mo_status = 1)`,
    );
    expect(mine, `an EMR ledger row exists for ${b.assen} with every connector off`).toBe('0');
    expect(sent, `${b.assen} is recorded as delivered to an EMR that cannot be reached`).toBe('0');
  });

  shared.fixme('S-DST-06 the connector moves ci_sync_api_orders.mo_status 0 -> 1 on a successful send @dst',
    () => {
      // Blocked on all three legs of the catalog's precondition, and the first
      // is not something a test may arrange here:
      //  1. No connector is enabled and no clinic carries routing meta (asserted
      //     above). Enabling one writes a NETWORK-WIDE option blob, which is
      //     @serial state, not main-project state -- and every connector's
      //     option carries endpoint credentials.
      //  2. No ci_sync_api_orders row at mo_status=0 can be produced: those rows
      //     are written by the connectors themselves on order import, so there
      //     is no app path to the precondition without a live connector.
      //  3. The flip to 1 sits behind a real transport in every implementation
      //     (ci_api_sftp_emr.php:1592, ci_api_elink.php:1204,
      //     ci_api_sftp_experity.php:1606, ...), i.e. an ssh2 SFTP session, a
      //     REST POST or a SOAP call on an egress-denied network. Firing
      //     `cron_api_sftp_emr` to try is expressly out of bounds: D017 --
      //     ci_api_sftp_emr.php:147 ssh2_sftp_rename and :178 ssh2_sftp_rmdir
      //     mutate a PARTNER'S server, which is the same hazard that bans
      //     `wp cron event run --due-now`.
    });

});

// ================================================================== DWA-E19
//
// OPISAFE RESULT UPLOAD -- the fourth distribution channel, and the one whose
// configuration screen is bound to no menu slug at all.
//
// `inc/crons.php:138-143` registers `ci_auto_upload_results` and includes
// `inc/crons/auto_upload_results_opisafe.php`, whose whole queue is
//
//     join ci_opisafe_key_relationships kc on (ba.cli_ID = kc.target_ID ...)
//     join ci_opisafe_keys opi ON opi.opi_ID = kc.opi_ID          (:18-19)
//
// -- two INNER joins, so with no key row the cron reaches `count($reports)==0`,
// echoes `notfound` and returns (:32-37). Nothing is uploaded and nothing is
// logged as failing to upload.
//
// The screen that maintains those rows EXISTS: `pages/admin_ci_opisafe_keys.php`
// is a full CRUD manager (insert :23, update :16, delete :65, listing :217,
// `<h2>Keys <a class="add-new-h2">Add New</a></h2>` at :227). It is registered
// NOWHERE. Measured on the pinned a7aa074d, 2026-08-25:
//
//     $ grep opisafe_keys inc/admin.php inc/form_define.php   -> no output, exit 1
//     $ grep -rn opisafe_keys .   -> install.php (CREATE TABLE), the cron's join,
//                                    and the page file's own queries. Nothing else.
//
// Its sibling `pages/admin_ci_wsdl_keys.php` IS registered, as the `APIs` entry
// of the `$setting_tabs` array at `inc/admin.php:186-197`, which is what the
// positive control below leans on: one line of that array away, and the
// difference between a configurable channel and a dead one.
//
// DWA-E19 IS TWO DEFECTS, NOT ONE, and this was measured rather than reasoned:
// adding the missing `$setting_tabs` entry makes the tab appear and the screen
// then dies on WordPress's own *"There has been a critical error on this
// website"* page. The cause is the line the sibling has and this file does not
// -- `admin_ci_wsdl_keys.php:2` opens with `global $wpdb, $ci_all_btypes;`,
// `admin_ci_opisafe_keys.php` opens with nothing -- and the settings framework
// includes a tab file inside FUNCTION scope, so `$wpdb` is null and
// `$wpdb->get_var()` at `:209` is a call on null. Proof both ways: including
// the same file from `wp eval` WITH `global $wpdb` in scope renders it in full
// (2103 bytes), and adding `global $wpdb;` to the file makes the registered tab
// render its `Keys / Add New` listing.
//
// That is why THIS TEST DOES NOT FLIP ON A REGISTRATION-ONLY FIX, and it should
// not: a tab that crashes is still no maintenance path. Both halves were
// injected together and the test then reported `Expected to fail, but passed`.
// It also means the screen has demonstrably never been reached by anyone --
// a page that fatals on its first render cannot have shipped working.
//
// WHY `Administrat0r` AND WHY THAT SETTLES "no persona". This settings page is
// the ceiling seat by construction: `inc/admin.php:113` defines `$is_ci_admin`
// as the LITERAL login `Administrat0r`, and `:193-206` unsets even the `APIs`
// tab for any OTHER administrator -- measured in the S-DST-01 block above,
// where `e2e_dir` (an administrator) lands on a different tab. A tab that this
// seat is not offered is offered to nobody, so the ceiling is the only persona
// this needs to ask.
//
// `shared.fail()` is INSIDE the body, as in the S-DST-07 pin below: at describe
// scope it would annotate every sibling test in the file's group and mask a
// real regression. It is called AFTER the two premise assertions and the
// positive control, immediately before the pin, so that a dropped cron, a
// missing table or a denied settings screen reds as itself (D416).

shared.describe('OpiSafe result upload @dst', () => {
  shared('the lab administrator can reach the OpiSafe key screen the upload cron depends on', async ({
    pageAs, db,
  }) => {
    // THE PREMISE, checked rather than claimed -- both halves of "a cron
    // depends on those rows". Deleting the cron, or dropping the tables, is a
    // realistic product change and reds these two lines -- for real, because the
    // marker is called below them and not above (D416).
    expect(
      cronHandlers(['ci_auto_upload_results']),
      'the nightly result-upload cron is registered, so the keys it joins on are load-bearing',
    ).toEqual({ ci_auto_upload_results: true });
    expect(
      db.scalar(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() '
        + "AND TABLE_NAME IN ('ci_opisafe_keys','ci_opisafe_key_relationships')",
      ),
      'the two tables the cron INNER-joins are real schema objects, not a stale reference',
    ).toBe('2');

    const page = await pageAs('Administrat0r');
    await page.goto('/wp-admin/admin.php?page=ci_pdf_data');

    // THE POSITIVE CONTROL, and it is what makes the assertion below mean
    // "OpiSafe is missing" rather than "this seat sees no settings tabs" or
    // "the render died" (D141/D388). Same seat, same URL, same nav bar, one
    // array entry across: `?page=ci_pdf_data&pagetab=ci_wsdl_keys`.
    await expect(
      page.getByRole('link', { name: 'APIs', exact: true }),
      'the CI Data Works settings tab bar rendered for this seat, so a missing tab below '
      + 'is a missing tab and not a denial',
    ).toHaveCount(1);

    // ---- THE PIN ------------------------------------------------------------
    // Called HERE: the cron/table premise and the APIs positive control above are
    // preconditions, and a marker at the top of the body reports a dropped cron or
    // a denied settings screen as the expected failure for ever (D416).

    // Deliberately NOT pinned to a slug: a fix is free to register
    // page as a settings tab, a submenu or a top level item, and any of those
    // puts a link naming it into this page's chrome (`#adminmenu` is rendered
    // here too). A slug literal would go on failing after a fix that used a
    // different one, which is a pin that never flips.
    const opisafe = page.getByRole('link', { name: /opisafe/i });
    await expect(
      opisafe.first(),
      'no link anywhere on the CI Data Works settings screen leads a lab administrator to '
      + 'the OpiSafe key manager, so the rows `ci_auto_upload_results` INNER-joins on have '
      + 'no maintenance path and the channel can never deliver a report (DWA-E19)',
    ).toBeVisible();

    // ... and following it reaches the manager, not some other screen. Reached
    // by CLICKING the link the assertion above found, so this leg follows the
    // fix wherever it puts the page.
    await opisafe.first().click();
    // `Key` and `Clinic` are columnheaders the APIs tab ALSO draws (measured:
    // `# Key Clinic Group Lab Access Notes Count Actions`), so these two do not
    // by themselves tell the two screens apart. They do not have to: the
    // navigation above was a CLICK on the link that names OpiSafe, so what is
    // being asserted here is that whatever that link leads to is a key manager
    // rather than a fatal, a denial or the settings landing tab.
    await expect(
      page.getByRole('columnheader', { name: 'Key', exact: true }),
      'the OpiSafe key manager lists the keys it holds',
    ).toHaveCount(1);
    await expect(
      page.getByRole('columnheader', { name: 'Clinic', exact: true }),
      'and shows which clinic each key routes -- `ci_opisafe_key_relationships`, the '
      + "cron's other join",
    ).toHaveCount(1);
    // The maintenance path itself. `pages/admin_ci_opisafe_keys.php:227`.
    await expect(
      page.getByRole('link', { name: 'Add New', exact: true }),
      'and offers the administrator a way to add the key that arms the channel',
    ).toBeVisible();
  });
});

// ================================================================= S-DST-07
//
// Reference-lab inbound return. The one distribution channel that is REACHABLE
// here, because the transport is a LOCAL directory rather than a network hop:
// inc/crons/lifetime.php resolves the drop as dirname(ABSPATH).'/lifetime/',
// i.e. /var/www/lifetime/ -- a sibling of the webroot, so nothing this test
// writes can reach the git checkout the teardown guards.

// SERIAL BY NECESSITY, same reason as the auto-fax pair above and measured the
// hard way. Both tests below fire the GLOBAL `cron_api_lifetime`, and the
// connector scans one shared drop directory for the whole network. Under
// `fullyParallel` the two land on two workers, and two overlapping passes both
// clear the check at ci_api_lifetime.php:339 before either insert at :370 --
// two `estatus='C'` reports for one accession, because nothing in the schema
// stops them (DWA-E13, and the third test below). CI run `32770142951` is that
// collision: `exactly one ci_reports_blood row for LAI1234571`, received 2,
// green at the same tip in `32769472783` and `32770943980`.
//
// Nothing else in the suite can write that row -- ruled out by measurement, not
// by reading: `ci_auto_upload_results` and `ci_auto_fax_results` write
// ci_box_upload and ci_reports (tox), never ci_reports_blood;
// `cron_add_manual_reports` needs a manual-edit group in
// `panel_tests_requested`, which seedBloodRequisition leaves NULL;
// ci_load_results_blood.php:307 joins on the accession alone, so it finds the
// connector's row and updates it; and `DISABLE_WP_CRON` (env/wp-config.php:42)
// means no HTTP request can spawn a cron run of its own. Serialising this
// describe therefore closes the suite-side collision completely.
shared.describe('S-DST-07 @dst @mutates', () => {
  // Sequential for the collision the note above describes, and `default` rather than
  // `serial` so that the pin in this block cannot abandon its siblings: each test
  // arms and restores `tr_lifetime_st` inside its own try/finally, so one worker in
  // declaration order is the whole requirement.
  shared.describe.configure({ mode: 'default' });

  const DROP = '/var/www/lifetime/results';
  /** `inc/crons/lifetime.php:11` -- the outbound half's CSV target. See the arming note below. */
  const ORDER_DROP = '/var/www/lifetime/orders';
  /** `inc/crons/lifetime.php:16` -- the file the 500 s throttle reads. */
  const LIFETIME_RUN_FILE = '/var/www/html/wp-content/log/log_api_lifetime_run.txt';

  shared('a returned reference-lab PDF completes the blood report for its accession', async ({
    tenantRequest, db, tenant, runKey,
  }, testInfo) => {
    // The connector is off by default; `tr_lifetime_st` is its own option and
    // nothing else reads it, so arming it touches no other spec's behaviour.
    // Restored at the end of the test.
    const armed = wpEval(
      '$o = ["before"=>get_option("tr_lifetime_st", array())];'
      + 'update_option("tr_lifetime_st", array("enable"=>1));'
      + '$o["dir"] = dirname(ABSPATH)."/lifetime/";',
    );
    expect(String(armed.dir), 'the drop directory is a sibling of the webroot, not inside it')
      .toBe('/var/www/lifetime/');

    try {
      const patientLast = `Dstsyn${runKey}e`;
      const seeded = await seedBloodRequisition(tenantRequest, db, tenant.labId, patientLast);
      expect(seeded.status, 'ci_save_blood answers 200').toBe(200);
      // D221: row-exact on this test's key, never a global count.
      expect(
        seeded.keyRowsAfter - seeded.keyRowsBefore,
        'ci_batches_blood gained no row for this patient key (D142/D100)',
      ).toBeGreaterThan(0);

      const [[mid, assen, labId, lab]] = db.query(
        `SELECT mid, assen, lab_id, lab FROM ci_batches_blood
          WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
      );
      expect(assen, 'accession is empty (D160)').not.toBe('');
      expect(labId, 'ci_batches_blood.lab_id').toBe(tenant.labId);
      expect(lab, 'ci_batches_blood.lab').toBe(tenant.prefix);

      // The send-out has no report and no ledger row before the return arrives.
      const [[reportsBefore, ledgerBefore]] = db.query(
        `SELECT (SELECT COUNT(*) FROM ci_reports_blood WHERE patient_id = ${Db.q(assen)}),
                (SELECT COUNT(*) FROM ci_sync_api_orders WHERE mo_mid = ${Db.q(mid)})`,
      );
      expect(reportsBefore, 'no ci_reports_blood row before the return').toBe('0');
      expect(ledgerBefore, 'no ci_sync_api_orders row before the return').toBe('0');

      // THE RETURN. api_lifetime scans the drop for <accession>.pdf and requires
      // more than 10 bytes (ci_api_lifetime.php:311). Synthetic content -- this
      // is a file the reference lab would have produced, and it carries nothing.
      //
      // THE OTHER TWO PATHS ARE ARMED HERE TOO, and neither is decoration.
      // `inc/crons/lifetime.php` is a wrapper around the connector with two
      // gates in front of the leg this test drives, and BOTH of them make this
      // test pass or fail on the wall clock rather than on the product (D427):
      //
      //  1. `:18-21` `if (time() - filemtime($log_run_file) < 500) return;` --
      //     an 8m20s throttle. Two suite runs inside that window, or an
      //     adm.spec.ts `cron_all_api` landing first (define_apis.php:198
      //     dispatches this same hook), and the connector returns having done
      //     NOTHING. The test then reds on "exactly one ci_reports_blood row --
      //     got []", which names the connector's insert and says nothing about
      //     the throttle. `touch -d @0` is the app's own reset (`:74`
      //     `touch($log_run_file, 0)`), applied up front instead of hoped for.
      //  2. `:40` runs `sync_reqs()` when the SITE-LOCAL hour is exactly 00 --
      //     and `sync_reqs()` fopen()s a CSV in `<dir>/orders/`, which nothing
      //     creates, so `ci_api_lifetime.php:182` fputcsv()s a `false` handle
      //     and fatals. The fatal kills the process before `:74`'s touch, so the
      //     throttle above then stays CLOSED for 500 s -- measured: three
      //     consecutive full-suite runs at 00:0x America/New_York, all red here.
      //     `mkdir -p` on the orders directory lets that leg complete.
      //
      // Both are arming the environment the connector expects, exactly as the
      // results drop is armed on the line above -- nothing about the returned
      // PDF, the report row or the ledger is touched.
      dexec('wordpress', 'sh', '-c',
        `mkdir -p ${DROP} ${ORDER_DROP} && touch -d @0 ${LIFETIME_RUN_FILE} && `
        + `printf '%%PDF-1.4 e2e synthetic reference-lab return, no content' `
        + `> ${DROP}/${assen}.pdf`);

      runCron('cron_api_lifetime');

      // CATALOG ANSWER (10-scenarios.md:46 and :178(b) record this as UNKNOWN --
      // "the doc names the table but not the exact column/value api_lifetime
      // writes for complete"). Measured: with no report row yet, the connector
      // INSERTS one at ci_api_lifetime.php:381-392 with estatus='C',
      // approve_date stamped, instrument='Lifetime' and author_id=0 -- the same
      // released state a human approval produces, reached with no human at all.
      const after = db.query(
        `SELECT estatus, approve_date, instrument, author_id, upload_files, faxed
           FROM ci_reports_blood WHERE patient_id = ${Db.q(assen)}`,
      );
      // The rows go in the message, not just the count: when this went red on
      // CI all the report said was "Received: 2", and identifying the second
      // writer cost an hour that `batch_id`/`lab_id`/`instrument` would have
      // answered outright.
      expect(after.length, `exactly one ci_reports_blood row for ${assen} -- got ${JSON.stringify(after)}`)
        .toBe(1);
      const [estatus, approveDate, instrument, authorId, uploadFiles, faxedFlag] = after[0];
      expect(estatus, 'ci_reports_blood.estatus -- "complete" is estatus=C').toBe('C');
      expect(approveDate, 'ci_reports_blood.approve_date is stamped').not.toBe('');
      expect(approveDate, 'ci_reports_blood.approve_date is a real timestamp')
        .not.toBe('0000-00-00 00:00:00');
      expect(instrument, 'ci_reports_blood.instrument names the returning reference lab')
        .toBe('Lifetime');
      expect(authorId, 'ci_reports_blood.author_id -- the return has no human author').toBe('0');
      // :330 lowercases the filename before it is stored.
      // D290: WordPress uniquifies an upload whose name already exists
      // (wp_unique_filename), and the uploads dir is tmpfs -- cleared when the
      // container restarts, NOT by reset.sh. The accession repeats across runs
      // because reset.sh restores the minter's counter, so a second run in the
      // same container lands `<assen>-2.pdf`. Anchor on the accession and allow
      // the suffix: the business outcome is that THIS specimen's return is
      // attached, and `-2` still satisfies it. An exact-equality assertion here
      // fails on every run after the first without anything being wrong.
      const returnedPdf = new RegExp(`^${assen.toLowerCase()}(-\\d+)?\\.pdf$`);
      expect(uploadFiles, 'ci_reports_blood.upload_files carries the returned PDF')
        .toMatch(returnedPdf);
      expect(faxedFlag, 'the return does not itself fax anything').toBe('0');

      // The PDF is attached to the SPECIMEN too, and the drop file is consumed
      // (renamed into wp-content/results/uploads, which is tmpfs).
      expect(
        db.scalar(`SELECT result_pdf FROM ci_batches_blood WHERE mid = ${Db.q(mid)}`),
        'ci_batches_blood.result_pdf names the attached return',
      ).toMatch(returnedPdf);
      expect(
        dexec('wordpress', 'sh', '-c', `ls -1 ${DROP}/${assen}.pdf 2>/dev/null || true`).trim(),
        'the returned file is consumed out of the drop, not reprocessed forever',
      ).toBe('');

      // The ledger. api_id -21 (ci_api_lifetime.php:9); mo_control_ID is the
      // stored filename, which is what makes a re-drop of the same file an
      // UPDATE rather than a duplicate.
      const ledger = db.query(
        `SELECT mo_file_id, mo_btype, mo_control_ID, mo_status
           FROM ci_sync_api_orders WHERE mo_mid = ${Db.q(mid)}`,
      );
      expect(ledger.length, `exactly one ci_sync_api_orders row for ${assen}`).toBe(1);
      expect(ledger[0], 'ci_sync_api_orders [mo_file_id, mo_btype, mo_control_ID, mo_status]')
        .toEqual(['-21', 'blood', expect.stringMatching(returnedPdf), '0']);

      // TENANCY (D172). ci_reports_blood.lab_id is NOT written by this path --
      // the insert at :381-392 omits it -- so the tenancy of the returned result
      // is only assertable through the specimen it was matched to.
      expect(
        db.scalar(`SELECT lab_id FROM ci_batches_blood WHERE mid = ${Db.q(mid)}`),
        `the returned result was matched to a specimen in lab ${tenant.labId}`,
      ).toBe(tenant.labId);

      console.log(
        `S-DST-07 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
        + `mid=${mid} assen=${assen} instrument=${instrument}`,
      );
    } finally {
      wpEval('$o=["restored"=>update_option("tr_lifetime_st", array())];');
    }
  });

  shared('a returned PDF whose accession matches nothing is not attached to any specimen', async ({
    db, runKey,
  }) => {
    // The other half of :477-486: an unmatched file is left in the drop (its
    // mtime pushed back a day) and only deleted after 15 days. The assertion
    // that matters is that it is never attached to somebody else's specimen.
    const orphan = `ZZZ${runKey}NOPE`.slice(0, 12).toUpperCase();
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_batches_blood WHERE assen = ${Db.q(orphan)}`),
      'precondition: the orphan accession matches no specimen',
    ).toBe('0');

    const armed = wpEval('update_option("tr_lifetime_st", array("enable"=>1)); $o=["ok"=>1];');
    expect(armed.ok, 'connector armed').toBe(1);
    try {
      dexec('wordpress', 'sh', '-c',
        `mkdir -p ${DROP} && printf '%%PDF-1.4 e2e synthetic orphan return' > ${DROP}/${orphan}.pdf`);
      runCron('cron_api_lifetime');

      expect(
        db.scalar(`SELECT COUNT(*) FROM ci_reports_blood WHERE patient_id = ${Db.q(orphan)}`),
        'an unmatched return created a report row out of thin air',
      ).toBe('0');
      expect(
        db.scalar(`SELECT COUNT(*) FROM ci_sync_api_orders WHERE mo_control_ID = ${Db.q(`${orphan.toLowerCase()}.pdf`)}`),
        'an unmatched return created an EMR ledger row',
      ).toBe('0');
      expect(
        dexec('wordpress', 'sh', '-c', `ls -1 ${DROP}/${orphan}.pdf 2>/dev/null || true`).trim(),
        'the unmatched file is left in the drop for a later retry, not consumed',
      ).toBe(`${DROP}/${orphan}.pdf`);
    } finally {
      dexec('wordpress', 'sh', '-c', `rm -f ${DROP}/${orphan}.pdf`);
      wpEval('$o=["restored"=>update_option("tr_lifetime_st", array())];');
    }
  });

  // -------------------------------------------------------------- DWA-E13
  //
  // THE DEFECT BEHIND THE FLAKE, pinned deterministically instead of by timing.
  //
  // `ci_reports_blood` carries exactly one uniqueness guard --
  // `UNIQUE KEY patient_batch (batch_id, patient_id, lab_id)` -- and the
  // connector's insert at ci_api_lifetime.php:354-362 omits BOTH `batch_id` and
  // `lab_id`. Every connector row therefore lands with `batch_id=0` and
  // `lab_id NULL`, and a UNIQUE index never collides on a NULL column. Measured
  // against this schema:
  //
  //     (0,'X',NULL) twice -> accepted, two rows
  //     (0,'X',12)   twice -> ERROR 1062 Duplicate entry '0-X-12' for key 'patient_batch'
  //
  // So the guard the table already has cannot fire for this write path, and the
  // check-then-insert at :339/:370 is the only interlock left -- no lock, no
  // `INSERT ... ON DUPLICATE KEY`. Demonstrated end to end: two
  // `cron_api_lifetime` processes released on a common clock produced two
  // `estatus='C'`, `approve_date`-stamped rows for one accession, one ledger
  // row, and one consumed PDF. That is one specimen reported twice, and every
  // downstream leg -- fax, EMR push, portal -- selects on the report row.
  //
  // Pinning the RACE would be as intermittent as the bug (measured: 0
  // duplicates in 20 unsynchronised two-cron runs, and 12 green iterations of
  // this whole describe at `--workers=2`). This pins the GUARD instead: the
  // connector's own payload, issued twice for one accession, must not leave two
  // rows behind. It goes green the day DWA-E13 lands, and Playwright then
  // reports the unexpected pass -- remove the marker at that point.
  //
  // shared.fail() is inside the body on purpose (D-rev precedent): at describe
  // scope it annotates every sibling, which would mask a real regression in the
  // two tests above. It is called AFTER the seed and the first accepted write,
  // so a broken seed or a malformed payload reds as itself (D416).
  shared('the connector cannot write a second completed report for one accession', async ({
    tenantRequest, db, tenant, runKey,
  }) => {
    const patientLast = `Dstdup${runKey}e`;
    const seeded = await seedBloodRequisition(tenantRequest, db, tenant.labId, patientLast);
    expect(seeded.status, 'ci_save_blood answers 200').toBe(200);
    const assen = db.scalar(
      `SELECT assen FROM ci_batches_blood WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
    );
    expect(assen, 'accession is empty (D160)').not.toBe('');

    // ci_api_lifetime.php:354-362 verbatim, issued twice through the app's own
    // $wpdb. Not a synthetic row: this is the payload the connector builds for
    // a matched return, and two overlapping cron passes issue exactly this.
    const written = wpEval(
      'global $wpdb; $o = ["accepted"=>0, "errors"=>[]];'
      + 'for ($i = 0; $i < 2; $i++) {'
      + '  $ok = $wpdb->insert("ci_reports_blood", array('
      + `    "patient_id" => "${assen}",`
      + '    "estatus" => "C",'
      + '    "author_id" => 0,'
      + '    "instrument" => "Lifetime",'
      + '    "created_date" => date_i18n("Y-m-d"),'
      + '    "created" => date_i18n("Y-m-d H:i:s"),'
      + `    "upload_files" => "${assen.toLowerCase()}.pdf",`
      + '    "approve_date" => date_i18n("Y-m-d H:i:s")'
      + '  ));'
      + '  if ($ok) { $o["accepted"]++; } else { $o["errors"][] = $wpdb->last_error; }'
      + '}',
    );
    // Not the assertion under test -- it only proves the payload is well formed
    // and that a refusal below came from the guard, not from a typo. OUTSIDE the
    // marker (D416), so a payload the app rejects outright reds as itself.
    expect(written.accepted, 'the first connector write is accepted').toBeGreaterThan(0);

    // ---- THE PIN ------------------------------------------------------------

    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_reports_blood WHERE patient_id = ${Db.q(assen)}`),
      `${assen} holds two completed reports -- the second write must be refused (DWA-E13). `
      + `errors seen: ${JSON.stringify(written.errors)}`,
    ).toBe('1');
  });
});

// =============================================================== S-DST-08
//
// THE INBOUND CHANNEL. Everything above this line asserts results going OUT.
// `/resultapi/put_result` is the door they come IN through -- the REST endpoint
// `inc/init.php:14-16` mounts for any URI starting `/resultapi/`, which the
// instrument bridges post analyte calls to.

/**
 * Mint a key for the result API. That endpoint's ENTIRE authorization is
 * `select wsdl_ID from ci_wsdl_keys where wsdl_key = '<key>'`
 * (`api/ci_result_api.php:24-31`, called from `restful_api.php:168` before any
 * endpoint runs): it reads neither `wsdl_active`, nor `wsdl_type`, nor
 * `wsdl_start`, nor `wsdl_lab_ID`. `wsdl_url=''` keeps the key out of
 * `inc/crons/wsdl_put_req.php:29`, so a key minted here can never make a cron
 * open an outbound connection (hub.spec.ts:55-62, same reasoning).
 */
function grantResultApiKey(db: Db, key: string, labId: string): void {
  db.query(
    `INSERT INTO ci_wsdl_keys (wsdl_url, wsdl_key, wsdl_lab_ID, wsdl_type, wsdl_active, wsdl_start)
     VALUES ('', ${Db.q(key)}, ${Number(labId)}, '', 1, '2000-01-01')`,
  );
}

/**
 * S-DST-08 -- A RESULT THAT RELEASES ITSELF.
 *
 * Every other release in this suite passes a reviewer: the queue render creates
 * the draft (`ci_load_results.php:412`, `estatus` empty), and a human presses
 * Approve. `request_put_result` (`api/ci_result_api.php:43-108`) creates the
 * report row ITSELF, and creates it already signed off:
 *
 *     $wpdb->insert($report_tbl, array(
 *         'patient_id'   => $assen,
 *         'estatus'      => 'C',                       // <- released
 *         'approve_date' => date_i18n('Y-m-d H:i:s'),  // <- and time-stamped
 *         ...
 *
 * `estatus='C'` is the predicate the whole downstream reads on: the fax cron
 * (`inc/crons/auto_fax_results.php:117`), the clinician's line list
 * (`ci_load_test_results.php:158`), the result PDF, the patient portal. So one
 * unauthenticated GET carrying a key string turns an unreviewed analyte into a
 * released clinical result, with no user, no capability, no nonce and no lab
 * check anywhere in the path -- and `approved_by_ID` stays 0, so the report
 * names nobody as its signer either. The row it writes carries neither the
 * specimen's `lab_id` nor its `batch_id`.
 *
 * WHAT WOULD BE CORRECT, and what the pin states: a result arriving from an
 * instrument lands as a DRAFT for the laboratory to review, exactly as the
 * bench's own path does. The day the endpoint stops writing `estatus='C'` --
 * or grows a review step -- this reports "Expected to fail, but passed".
 *
 * NOT A REWRITE-BEHIND-RELEASE TEST. rev.spec.ts S-REV-11u pins that fact
 * through the reviewer's own `&view=1` door and on a different table. This is
 * the report's BIRTH, and the two fixes are unrelated.
 *
 * NO EGRESS AND NO SESSION (D091). The request is issued through the built-in
 * `request` fixture, which every project configures with
 * `storageState: undefined` -- so it carries no cookie, and the key alone is
 * what gets the write through. That is the claim, not an accident of setup.
 */
shared.describe('the result API as an inbound channel @dst @mutates', () => {
  shared('S-DST-08 a result posted with an instrument key is not released without review', async ({
    seedRequisition, request, authedPage, db, tenant, runKey,
  }) => {
    // A real orderable analyte, resolved live: `put_result` stores the `test`
    // parameter verbatim as `ci_results.drug`, and a name the panel does not
    // know would make the row unreadable to every consumer downstream for a
    // reason that has nothing to do with this defect.
    const [analyte] = db.query(
      `SELECT te_ID, te_name FROM ci_test_panels
        WHERE te_type = 'tox' AND te_in_req = 1 ORDER BY te_ID LIMIT 1`,
    );
    expect(analyte, 'no orderable tox analyte in ci_test_panels').toBeTruthy();
    const drug = analyte[1];

    const batchId = nextBatchId();
    const patientLast = `Dstapi${runKey}`;
    const seeded = await seedRequisition(patientLast, {
      'bat[batch_id]': String(batchId),
      'bat[batch_status]': 'resulted',
      'bat[panel_tests_requested]': drug,
    });
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const [[mid, assen, labId]] = db.query(
      `SELECT mid, assen, lab_id FROM ci_batches
        WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
    );
    expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
    expect(labId, 'ci_batches.lab_id (D172)').toBe(tenant.labId);

    // THE PRECONDITION THE WHOLE TEST RESTS ON: nobody has opened this specimen
    // yet, so there is no report row and no draft. Whatever exists after the
    // call was made by the call.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_reports WHERE patient_id = ${Db.q(assen)}`),
      'no ci_reports row exists before the instrument reports',
    ).toBe('0');

    const key = `e2edst08${runKey}`;
    grantResultApiKey(db, key, tenant.labId);

    const posted = await request.get(
      `/resultapi/put_result?${new URLSearchParams({ key, accession: assen, test: drug, result: 'POS' })}`,
    );
    expect(posted.status(), 'the result API answers 200').toBe(200);
    expect(await posted.json(), 'the result API accepted the analyte')
      .toMatchObject({ status: 'success' });

    // CONTROLS. The call really did write this specimen's result -- so nothing
    // below is about a request that bounced, and the report row under
    // assertion is the one the API created.
    const stored = db.query(
      `SELECT r.drug, r.\`pos-neg\` FROM ci_results r
         JOIN ci_reports re ON re.id = r.report_id
        WHERE re.patient_id = ${Db.q(assen)}`,
    );
    expect(stored, `ci_results for ${assen} -- the analyte the instrument reported`)
      .toEqual([[drug, 'POS']]);
    const [[reportId, estatus, approveDate, reportLab, reportBatch, approvedBy]] = db.query(
      `SELECT id, estatus, approve_date, lab_id, batch_id, approved_by_ID
         FROM ci_reports WHERE patient_id = ${Db.q(assen)}`,
    );
    expect(reportId, 'the API created exactly one report row').not.toBe('');

    // WHAT THE REVIEWER SEES. The queue is this lab's own worklist; a specimen
    // whose results have just arrived belongs on it as work TO DO. The Approve
    // control is offered on an unreleased report and swapped for Reverse once
    // it is signed (`ci_load_results.php:644`, `parts/tox/results.php:139`), so
    // this is the reviewer's own reading of whether the report is still theirs
    // to sign. Read off a fresh server render, never post-XHR DOM (D386).
    //
    // Reached by URL, not through `openToxQueue`: that helper loads the
    // UNFILTERED queue first, and `results_list.php:595-603` auto-expands it
    // whenever the lab holds exactly one batch -- firing a `ci_load_results`
    // render, and therefore a WRITE (D034), against whatever specimen that is.
    // Its callers are all inside the serial auto-fax describe; this test is not.
    await authedPage.goto(`${TOX_RESULTS}&s=${assen}`);
    const row = authedPage.locator('tr.childp').filter({ hasText: assen });
    // OUTSIDE the marker below: that the queue lists the specimen at all is a
    // precondition, and a `fail()` body would report its absence as the
    // expected failure for ever.
    await expect(row, `the reviewer's queue shows ${assen}`).toHaveCount(1);

    // ------------------------------------------------------- THE PIN
    // Placed after the controls on purpose: a marker at the top of the body
    // absorbs a broken seed, a dead key or a rejected POST and reports each of
    // them as the expected failure for as long as the file lives.

    await expect(
      row.getByText('Approve', { exact: true }),
      `${assen} was resulted by an instrument key and the reviewer's queue offers no Approve: `
      + `the report was born released (estatus '${estatus}', approve_date '${approveDate}') by an `
      + 'unauthenticated GET, so there is no review step left to perform. It carries '
      + `lab_id '${reportLab}' and batch_id '${reportBatch}' -- neither the specimen's -- and `
      + `approved_by_ID '${approvedBy}', so no person is recorded as having signed it`,
    ).toBeVisible();
    expect(
      [estatus, approveDate === '' ? 'unstamped' : 'stamped'],
      `ci_reports [estatus, approve_date] for ${assen}: a result arriving from an instrument `
      + 'must land as a draft for the laboratory to review, the way the bench path does',
    ).toEqual(['', 'unstamped']);

    console.log(
      `S-DST-08 lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen} `
      + `report=${reportId} estatus=${estatus} report.lab_id=${reportLab}`,
    );
  });
});
