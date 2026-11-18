import { expect, request as playwrightRequest } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';
import { test as shared } from '../fixtures/test';
import { batchIds } from '../fixtures/batch-id';
import { Db } from '../fixtures/db';
import { runCron, wpEval } from '../fixtures/container';
import { infra } from '../fixtures/seed-domain';
import { STATE_DIR } from '../playwright.config';
import * as path from 'node:path';

/**
 * ADM -- laboratory administrator: the person who configures the delivery
 * channels every other persona depends on (personas.md:183-196).
 *
 * PERSONA, measured against the RUNNING instance (D178) by reading
 * `WP_User->allcaps` directly -- `user_can()` is unusable here (D255):
 *
 * Counts are the PINNED ci-data-works-arc commit's; a submodule checked out
 * ahead of the pin reads administrator 151 / AdminCIDW 88 (see :326 below).
 *
 *   role             caps  manage_options  ci_results  ci_data_forms
 *   administrator     150        YES          YES          YES     <- e2e_dir
 *   AdminCIDW          87        no           YES          YES
 *   IT                 63        no           YES          YES
 *   administrator_p    46        no           no           YES
 *   lab_sec            29        no           no           YES
 *   labmanager         19        no           YES          YES
 *   leadlabsecman      15        no           YES          YES     <- tenant persona
 *   user_management    11        no           no           YES
 *
 * personas.md:190 says `administrator`, `administrator_p`, `AdminCIDW` and `IT`
 * all "hold the full LIS capability set". Live they do not: only
 * `administrator` carries `manage_options`, and `administrator_p` has lost
 * `ci_results` entirely. `administrator` is the only slug in the pool that
 * holds the admin capabilities live, and `e2e_dir` is the only seeded user
 * carrying it (env/seed.php:236).
 *
 * THE CAPABILITY ON THE DELIVERY-CONFIG SCREEN IS SELF-SATISFYING.
 * `inc/admin.php:229` registers `page=ci_pdf_data` with
 * `'capability' => $current_role` -- the viewer's OWN role slug. WP_User::get_role_caps()
 * puts every role slug into `allcaps`, so `current_user_can($current_role)` is
 * true for whoever is asking. What actually keeps `lab_sec` out is that the
 * menu is never REGISTERED for it, so WP answers its own "sufficient
 * permissions" denial -- HTTP 200 with a denial body, not 403 (D185/D250).
 * Measured both ways below, because the capability check itself would let
 * anyone through.
 *
 * NOT a D216-family tab gap: tr_admin_page_class_v11.php:410 includes
 * `$this->args['tabs'][$tab]['file']` with `$tab` from `$_REQUEST['pagetab']`,
 * but `args['tabs']` is a per-request WHITELIST -- the admin-only tabs
 * (inc/admin.php:244) are never added to it for a lesser role, so an
 * unregistered `pagetab` resolves to nothing rather than to a file.
 *
 * EGRESS IS DENIED AND THAT IS CORRECT (D091). Box, OpiSafe, RingCentral and
 * Faxage are unreachable. Every assertion below is on the LEDGER
 * (`ci_reports.box_uploaded` / `.opi_id` / `.info` / `.faxed`,
 * `ci_batches.box_uploaded`, `ci_box_upload`, `ci_box_folder`), never on a
 * third-party response. Box does not even reach the network: `CI_Box_Works::ready()`
 * (inc/ci_box_works.php:291) returns false on an empty `access_token`, and the
 * `ci_box_settings` option does not exist on this build -- so the Box legs stop
 * LOCALLY and deterministically, not on a timeout.
 *
 * NETWORK OPTIONS. `ci_pdf_data` is a SHARED option carrying `barcode_key`,
 * `barcode_length` and the auto-fax toggles in one serialized blob; a
 * read-modify-write on it races every other spec. Nothing here writes it. The
 * gate tests read the live values and assert what the app does under them.
 *
 * SERIAL BY NECESSITY. `runCron()` fires a GLOBAL hook: the Box/OpiSafe cron
 * selects up to 10 (Box) / 5 (OpiSafe) reports network-wide, so two of these
 * tests running concurrently would have each one's cron consume the other's
 * report. One describe, `.serial`, one worker.
 *
 * NO `@serial` TAG, and the reason has CHANGED. It used to be that the `serial`
 * project matched only `specs/serial.spec.ts`, so a tagged test here would have
 * run in NO project at all. That trap is closed: both projects now match every
 * spec file and split on the tag alone (playwright.config.ts). The tag is still
 * withheld deliberately -- `serial` carries `dependencies: ['main']`, so tagging
 * these would move them behind the whole run and serialise them against every
 * other `@serial` test rather than merely against each other, which is all this
 * describe needs.
 */

const e2e = shared.extend<{}, { admin: APIRequestContext }>({
  // The laboratory administrator. `administrator` is the slug that holds the
  // admin capabilities live; e2e_dir is the only seeded user with it.
  admin: [
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

const RESULTS_PAGE = '/wp-admin/admin.php?page=tox_results';

// ci_batches.batch_id is int(10) NOT NULL. Shared disjoint-block allocator
// (D243/D396): every worker in a run starts inside the same second, so a
// time-only base hands two workers identical ids and one test's queue render
// auto-approves the other's draft on GET (D034/D225) -- and the same is true of
// two SPEC FILES on one worker, which is what the spec offset separates.
const nextBatchId = batchIds('adm');

interface Specimen { mid: string; assen: string; batchId: number; reportId: string; docId: string; }

/**
 * One tox specimen, released to `estatus='C'` -- the universal precondition for
 * every distribution channel (flow-result-distribution.md:56).
 *
 * There is no app path that CREATES a tox report row for an ordinary panel:
 * rendering the results queue inserts it (inc/actions/ci_load_results.php:415),
 * which is why every test here is @mutates. `batch_status='resulted'` is what
 * admits a batch with no report row (ci_load_results.php:92) while keeping the
 * saver's auto-approve branch shut, so the report is born a draft and the
 * release below is a real state change.
 */
async function releasedSpecimen(
  seedRequisition: (p: string, o?: Record<string, string>) => Promise<{ status: number }>,
  ctx: APIRequestContext,
  admin: APIRequestContext,
  db: Db,
  tenant: { labId: string; prefix: string },
  patientLast: string,
  overrides: Record<string, string> = {},
  /**
   * THE THREE-VALUED-LOGIC TRAP. Both nightly legs carry
   * `( ba.custom_profile != 'Use Custom Profile' OR (… AND cp.cus_ID>0) )`
   * (auto_upload_results.php:15, auto_upload_results_opisafe.php:12). On a NULL
   * custom_profile BOTH sides evaluate to NULL, so the specimen is silently
   * invisible to Box AND to OpiSafe. `seedRequisition` omits the field, which
   * leaves exactly that NULL; the tox form always posts a value, defaulting to
   * 'Do Not Use Custom Profile' (parts/tox/req_form.php:252-253,:312), so that
   * is what goes on the wire here. Pass `null` to reproduce the NULL case.
   */
  customProfile: string | null = 'Do Not Use Custom Profile',
): Promise<Specimen> {
  const batchId = nextBatchId();
  const seeded = await seedRequisition(patientLast, {
    'bat[batch_id]': String(batchId),
    'bat[batch_status]': 'resulted',
    ...(customProfile === null ? {} : { 'bat[custom_profile]': customProfile }),
    ...overrides,
  });
  // Not toBeLessThan(400): a login redirect is 302 and would pass that.
  expect(seeded.status, 'the requisition saver answers 200').toBe(200);

  // D289/D290: `seedRequisition` now posts custom_profile, because the real form
  // always does (parts/tox/req_form.php:253 fills it at RENDER time; the saver
  // applies no default). Omitting the key therefore no longer yields NULL, so
  // the NULL hazard is reproduced explicitly here instead.
  //
  // This is the MORE faithful reproduction anyway: the writer that actually
  // produces NULL in production is one that bypasses the saver -- the DrChrono
  // pcr iframe inserts straight into the batch table
  // (flow-ordering-and-requisition.md:166,:190). A direct UPDATE models that
  // writer; relying on a fixture's omission modelled nothing real.
  if (customProfile === null) {
    db.query(`UPDATE ci_batches SET custom_profile = NULL WHERE batch_id = ${Db.q(String(batchId))}`);
  }

  const rows = db.query(
    `SELECT mid, assen, lab_id, lab, batch_id, doc_ID, box_uploaded,
            CONCAT('[', COALESCE(custom_profile,'<NULL>'), ']')
       FROM ci_batches WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
  );
  expect(rows.length, `exactly one ci_batches row for ${patientLast}`).toBe(1);
  const [mid, assen, labId, lab, bid, docId, batchBox, profile] = rows[0];
  expect(profile, 'ci_batches.custom_profile as posted')
    .toBe(customProfile === null ? '[<NULL>]' : `[${customProfile}]`);

  // D160: the saver answers 200 with assen='' when the minter fails.
  expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
  // TENANCY, asserted rather than assumed (D172/D161). This is also the leg the
  // E2E_FAULT=wrong-lab injection has to turn red.
  expect(labId, 'ci_batches.lab_id').toBe(tenant.labId);
  expect(lab, 'ci_batches.lab').toBe(tenant.prefix);
  expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(tenant.prefix);
  expect(bid, 'ci_batches.batch_id as seeded').toBe(String(batchId));
  expect(batchBox, 'ci_batches.box_uploaded starts at its default').toBe('0');

  // Render the queue -- THIS WRITES the report row (D034).
  const render = await ctx.post(RESULTS_PAGE, {
    form: {
      tr_action: 'ci_load_results', batch_id: String(batchId), lab_id: tenant.labId, ref_lab_id: '0',
    },
  });
  expect(render.status(), 'tox results fragment').toBe(200);

  const draft = db.query(
    `SELECT id, estatus, lab_id FROM ci_reports WHERE patient_id = ${Db.q(assen)}`,
  );
  expect(draft.length, `exactly one ci_reports row for ${assen}`).toBe(1);
  const [reportId, draftStatus, reportLab] = draft[0];
  expect(draftStatus, 'the report is born a draft').toBe('');
  expect(reportLab, 'ci_reports.lab_id -- the report is routed to the tenant lab')
    .toBe(tenant.labId);

  const approve = await admin.get(
    `${RESULTS_PAGE}&${new URLSearchParams({ tr_action: 'ci_results_approve', id: reportId })}`,
    { maxRedirects: 0 },
  );
  expect(approve.status(), 'the approve action redirects back to the queue').toBe(302);
  expect(
    db.scalar(`SELECT estatus FROM ci_reports WHERE id = ${Db.q(reportId)}`),
    'ci_reports.estatus after release',
  ).toBe('C');

  return { mid, assen, batchId, reportId, docId };
}

/** Ledger columns for one report, with NULL kept distinguishable from ''. */
function ledger(db: Db, reportId: string) {
  const [[box, faxed, opi, info, labId]] = db.query(
    `SELECT box_uploaded, faxed,
            CONCAT('[', COALESCE(opi_id, '<NULL>'), ']'),
            CONCAT('[', COALESCE(info,   '<NULL>'), ']'),
            lab_id
       FROM ci_reports WHERE id = ${Db.q(reportId)}`,
  );
  return {
    boxUploaded: box,
    faxed,
    opiId: opi.slice(1, -1),
    info: info.slice(1, -1),
    labId,
  };
}

/**
 * Fire the hourly fax cron and report whether it SURVIVED.
 *
 * On this stack it does not. `auto_fax_results.php:43` calls `$fax->is_expired()`
 * and, on an expired/absent RingCentral token, `:45` calls `refreshToken()`.
 * That reaches `api/ci_fax_ringcentral.php:420 -> authCall() -> inflateAuthData()`,
 * and `authCall()` returns NULL when the HTTPS call to platform.ringcentral.com
 * does not land -- which on an egress-denied network is always. `:91` then does
 * `array_key_exists($k, NULL)` and the request dies with an uncaught TypeError.
 *
 * So the documented "Fax token refresh fails -> cron aborts"
 * (flow-result-distribution.md:224) is not an abort, it is a PHP FATAL: the
 * cron never reaches the btype loop, the queue builder, or the six-part gate.
 * Tolerated rather than asserted-on, because a sibling spec that plants a
 * non-expired token would make it survive; the LEDGER assertions after the call
 * hold either way, which is the point.
 */
function fireFaxCron(): string {
  try {
    runCron('ci_auto_fax_results');
    return '';
  } catch (e) {
    const msg = String((e as Error).message);
    const fatal = (msg.match(/Fatal error: [^\n]*/) ?? [''])[0];
    // No PHP fatal in there means wpEval itself broke -- that is a harness
    // failure and must not be swallowed (D141).
    if (!fatal) throw e;
    return fatal;
  }
}

/** The Box cron's OWN selector (inc/crons/auto_upload_results.php:13-25), scoped. */
const BOX_SELECTOR = (assen: string) =>
  `SELECT re.id FROM ci_reports re
     JOIN ci_batches ba ON ba.assen = re.patient_id
     LEFT JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
    WHERE re.estatus='C' AND ba.ref_from_lab_ID = 0
      AND (re.re_import_ID=0 OR re.re_import_ID IS NULL)
      AND (ba.custom_profile!='Use Custom Profile'
           OR (ba.custom_profile='Use Custom Profile' AND cp.cus_ID>0))
      AND re.box_uploaded = 0
      AND re.patient_id = ${Db.q(assen)}`;

/**
 * `re.box_uploaded = 0` IS THE BOX CRON'S IDEMPOTENCY GUARD, AND NOTHING CAN
 * SEE IT ON THIS STACK -- recorded here so the mutation nightly EXPECTS
 * `inc/crons/auto_upload_results.php:18` to survive rather than counting it a
 * coverage gap, and so the next pass does not re-derive this.
 *
 * The clause is what stops a report already on the practice's Box folder being
 * uploaded a second time. To observe it a test needs a report at
 * `box_uploaded = 1`, and only `:135` writes that -- after a SUCCESSFUL upload.
 * `:41` returns unless `CI_Box_Works::ready()` is true, and `ready()`
 * (`inc/ci_box_works.php:289-300`) calls Box's `/users/me`. Every Box call goes
 * through `inc/ci_box_content_api.php:66` `curl_init`, which is the ONLY
 * request primitive in that class -- measured -- so it never reaches
 * `WP_Http::request()` and the suite's `pre_http_request` stub
 * (fixtures/stub-http.ts, D066/D116) cannot see it, let alone answer it. There
 * is no second stub, and there is no route to a fake `api.box.com` on an
 * `internal: true` network. So `box_uploaded` never leaves 0 under EITHER
 * variant of the clause and the two are indistinguishable: blocked(egress,
 * D091), not unseeded.
 *
 * NOTE THAT THE COPY ABOVE DOES NOT HELP. This selector mirrors the clause
 * test-side, so deleting it from the application moves nothing here -- a
 * mirrored predicate is a restatement of the app's intent, never a check on it.
 *
 * WHAT WOULD UNBLOCK IT: a stubbable successful upload, which means the Box
 * client issuing `wp_remote_*` rather than raw curl.
 */

/** The OpiSafe cron's OWN selector (inc/crons/auto_upload_results_opisafe.php:11-22), scoped. */
const OPI_SELECTOR = (assen: string) =>
  `SELECT re.id FROM ci_reports re
     JOIN ci_batches ba ON ba.assen = re.patient_id
     LEFT JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
     JOIN ci_opisafe_key_relationships kc
       ON (ba.cli_ID = kc.target_ID AND kc.cType='C') OR (ba.doc_ID = kc.target_ID AND kc.cType='D')
     JOIN ci_opisafe_keys opi ON opi.opi_ID = kc.opi_ID
    WHERE re.estatus='C'
      AND (ba.custom_profile!='Use Custom Profile'
           OR (ba.custom_profile='Use Custom Profile' AND cp.cus_ID>0))
      AND re.opi_id = ''
      AND re.patient_id = ${Db.q(assen)}`;

// ---------------------------------------------------------------------------

e2e.describe('result distribution configuration and delivery ledger @adm @mutates', () => {
  // Sequential because these tests arm and read the SAME delivery configuration
  // (ci_pdf_data toggles, the Box/fax provider options); run in parallel they
  // overwrite each other's arming. `default` rather than `serial` for the reason
  // rev.spec.ts's D239 block sets out in full: both put this block on one worker in
  // declaration order, and only `serial` ALSO abandons the rest of the block after a
  // failure. Nothing here is order-dependent -- the block has no beforeAll and no
  // block-level mutable state -- so the abandonment buys nothing and costs coverage.
  e2e.describe.configure({ mode: 'default' });

  e2e('the admin capabilities and the delivery-config gate come from the RUNNING instance', async ({ admin, tenantRequest }) => {
    // D255: user_can() is unusable, so allcaps is read directly. D178: source
    // install_roles.php and the live role disagree, and LIVE wins.
    const caps = wpEval(
      '$o=[];foreach(["administrator","administrator_p","AdminCIDW","IT","user_management",'
      + '"labmanager","lab_sec","leadlabsecman"] as $s){$r=get_role($s);'
      + '$o[$s]=$r?count(array_filter($r->capabilities)):-1;}'
      + '$o["admin_has_culture_antibiogram"]='
      + '!empty(get_role("administrator")->capabilities["ci_report_culture_antibiogram"]);'
      + '$u=get_user_by("login","e2e_dir");'
      + '$o["dir_roles"]=implode(",",$u->roles);'
      + '$o["dir_manage_options"]=!empty($u->allcaps["manage_options"]);'
      + '$o["dir_role_slug_is_a_cap"]=!empty($u->allcaps["administrator"]);'
      + '$s=get_user_by("login","e2e_sec");'
      + '$o["sec_manage_options"]=!empty($s->allcaps["manage_options"]);'
      + '$o["sec_role_slug_is_a_cap"]=!empty($s->allcaps["lab_sec"]);',
    );

    expect(caps.dir_roles, 'the seeded lab-administrator persona').toBe('administrator');
    expect(caps.dir_manage_options, 'administrator holds manage_options live').toBe(true);
    expect(caps.sec_manage_options, 'lab_sec does not hold manage_options').toBe(false);

    // personas.md:190 claims administrator_p / AdminCIDW / IT carry "the full
    // LIS capability set". Live they carry 46 / 87 / 63 against administrator's
    // 150. Pinned so the doc claim cannot quietly become true or drift further.
    //
    // WHICH ROLES CAN MOVE, AND WHY. install_roles.php:519-527 is the only place
    // that hands $all_lis_caps to a role unconditionally, and it does so for
    // exactly two: administrator and AdminCIDW. Every capability added to
    // tr_pdd_lis_caps() (inc/form_define.php) therefore moves those two counts by
    // +1 each and leaves the other six alone. IT (:498) and administrator_p (:446)
    // declare the same set through create_role, which is first-write-only
    // (DWA-E05), so their stored rows never pick it up.
    //
    // The numbers below are the PINNED ci-data-works-arc commit's -- what CI
    // checks out. A working tree whose submodule is checked out AHEAD of the pin
    // reads 151 / 88 instead; `git submodule status` prints a leading `+` for it.
    // The assertion immediately below names that case, so an off-pin checkout
    // fails with the cause rather than with a bare integer mismatch.
    expect(
      caps.admin_has_culture_antibiogram,
      'ci_report_culture_antibiogram is not in tr_pdd_lis_caps() at the pinned '
        + 'ci-data-works-arc commit. If this is true, the submodule is checked out '
        + 'ahead of its pin and administrator / AdminCIDW are each +1 below.',
    ).toBe(false);
    expect(
      { administrator: caps.administrator, AdminCIDW: caps.AdminCIDW, IT: caps.IT,
        administrator_p: caps.administrator_p, user_management: caps.user_management,
        labmanager: caps.labmanager, lab_sec: caps.lab_sec, leadlabsecman: caps.leadlabsecman },
      'live capability counts (wp_options.wp_user_roles), not install_roles.php',
    ).toEqual({
      administrator: 150, AdminCIDW: 87, IT: 63, administrator_p: 46,
      user_management: 11, labmanager: 19, lab_sec: 29, leadlabsecman: 15,
    });

    // NOT PINNED HERE, and deliberately: manage_network_users. inc/admin.php:1769
    // hooks trpdd_user_new_form() on `user_new_form`, a RENDER hook, and its body
    // (:1773-1776) calls WP_Role::add_cap('manage_network_users') for any role
    // whose slug matches *admin*, which writes through to wp_options.wp_user_roles
    // -- so loading /wp-admin/user-new.php permanently grants it (DWA-E12,
    // measured). An absence assertion cannot guard that here: wp-content/default.sql
    // already ships the capability on administrator, administrator_p and AdminCIDW,
    // and every role the hook can reach is a *admin* role that already holds it.
    // The counts above are the guard -- a NEW *admin*-slugged role would move them.

    // WHY THE CONFIG SCREEN'S CAPABILITY IS NOT A GATE: inc/admin.php:229
    // registers page=ci_pdf_data with 'capability' => $current_role, and WP puts
    // every role slug into allcaps -- so the check passes for whoever is asking,
    // for the administrator AND for lab_sec.
    expect(caps.dir_role_slug_is_a_cap, 'the role slug is itself a capability in allcaps')
      .toBe(true);
    expect(
      caps.sec_role_slug_is_a_cap,
      'lab_sec also satisfies a capability check written as its own role slug, which is '
        + 'what inc/admin.php:229 does for page=ci_pdf_data',
    ).toBe(true);

    // ... and what DOES keep lab_sec out is menu registration, so the refusal is
    // WordPress's own, at HTTP 200 with a denial body -- never 403 (D185/D250).
    const sec = await playwrightRequest.newContext({
      baseURL: 'http://localhost',
      storageState: path.join(STATE_DIR, 'e2e_sec.json'),
    });
    const [adminView, secView] = await Promise.all([
      admin.get('/wp-admin/admin.php?page=ci_pdf_data'),
      sec.get('/wp-admin/admin.php?page=ci_pdf_data'),
    ]);
    const tenantView = await tenantRequest.get('/wp-admin/admin.php?page=ci_pdf_data');
    // Bodies reduced to booleans on the way out: these pages are ~136 KB and a
    // failed toContain() would print the whole thing into the report.
    const shape = async (r: { text(): Promise<string> }) => {
      const t = await r.text();
      return { settings: t.includes('CI Data Works') && t.includes('pagetab='),
        denied: t.includes('&rsaquo; Error') && t.includes('page=welcome') };
    };
    const [adminShape, secShape, tenantShape] = await Promise.all(
      [adminView, secView, tenantView].map(shape),
    );
    await sec.dispose();

    expect(adminView.status(), 'the administrator reaches the delivery-config screen').toBe(200);
    expect(adminShape, 'the administrator gets the settings screen')
      .toEqual({ settings: true, denied: false });
    expect(secView.status(), 'the page layer answers 200 even when it refuses (D185/D250)').toBe(200);
    expect(
      secShape,
      'lab_sec reached the delivery-config screen although the menu is not registered for it',
    ).toEqual({ settings: false, denied: true });
    // The tenant persona is a leadlabsecman, not an administrator: it seeds the
    // specimens below but cannot configure the channels.
    expect(tenantShape, 'the leadlabsecman tenant persona is refused the same screen')
      .toEqual({ settings: false, denied: true });
  });

  e2e('S-DST-03 the nightly Box cron marks nothing delivered while the Box account is unauthorized',
    async ({ seedRequisition, tenantRequest, admin, db, tenant, runKey }) => {
      const s = await releasedSpecimen(
        seedRequisition, tenantRequest, admin, db, tenant, `Admsyn${runKey}a`,
      );

      // THE BLOCKER, MEASURED rather than assumed. ready() short-circuits on an
      // empty access_token (inc/ci_box_works.php:291) with no HTTP call at all,
      // so this leg is deterministic, not a timeout.
      const box = wpEval(
        '$s=get_blog_option(1,"ci_box_settings",array());'
        + '$o=["option_exists"=>(bool)$s,"token"=>!empty($s["access_token"]),'
        + '"ready"=>class_exists("CI_Box_Works")?(bool)(new CI_Box_Works(false))->ready():"NOCLASS"];',
      );
      expect(
        box,
        'the Box account has been authorized -- S-DST-03 is now implementable end to end '
          + 'and this containment control must be replaced by the positive assertion',
      ).toEqual({ option_exists: false, token: false, ready: false });

      // NON-VACUITY: the report IS eligible. Run the cron's own selector, so a
      // green result below cannot mean "the cron simply had nothing to do".
      expect(
        db.query(BOX_SELECTOR(s.assen)).length,
        `report ${s.reportId} (${s.assen}) is not selectable by the Box cron -- the negative `
          + 'assertion that follows would prove nothing',
      ).toBe(1);

      const foldersBefore = db.scalar('SELECT COUNT(*) FROM ci_box_folder');

      // Deterministic single-hook fire (D084). NEVER --due-now (D017): that fans
      // the due queue out into the partner-mutating SFTP crons.
      runCron('ci_auto_upload_results');

      const after = ledger(db, s.reportId);
      expect(
        after.boxUploaded,
        `report ${s.reportId} was marked Box-delivered although the Box API is unreachable -- `
          + 'the ledger would be claiming a delivery that never happened',
      ).toBe('0');
      expect(after.info, 'ci_reports.info carries no autobox breadcrumb').toBe('<NULL>');
      expect(
        db.scalar('SELECT COUNT(*) FROM ci_box_folder'),
        'the cron cached a Box folder id without a Box session',
      ).toBe(foldersBefore);
      expect(after.labId, 'tenancy of the report behind the ledger row').toBe(tenant.labId);
    });

  e2e('S-DST-04 the manual and automatic Box paths mark different tables, and nothing keeps them in sync',
    async ({ seedRequisition, tenantRequest, admin, db, tenant, runKey }) => {
      // Seeded WITHOUT custom_profile -- the shape `seedRequisition` produces by
      // default, and the one that exposes the second trap in this scenario (see
      // the NULL assertion below). Nothing here needs the specimen to be
      // selectable: every assertion is that no column moved.
      const s = await releasedSpecimen(
        seedRequisition, tenantRequest, admin, db, tenant, `Admsyn${runKey}b`, {}, null,
      );

      // A NULL custom_profile is silently excluded from the Box cron: both sides
      // of its OR evaluate to NULL, so the specimen never enters the automatic
      // channel at all. Not a fixture quirk -- `ci_batches.custom_profile` has no
      // default and any writer that omits the field lands here.
      expect(
        db.query(BOX_SELECTOR(s.assen)).length,
        'a NULL custom_profile is now selectable -- auto_upload_results.php:15 has been fixed '
          + 'and this three-valued-logic pin must be rewritten',
      ).toBe(0);

      // BOTH COLUMNS, EXPLICITLY, in one SELECT (D164). This is the audit trap:
      //   automatic  inc/crons/auto_upload_results.php:135
      //              -> UPDATE ci_reports SET box_uploaded=1 WHERE id=<report id>
      //   manual     pages/admin_ci_upload_result_pdf_to_box.php:195
      //              -> UPDATE ci_batches SET box_uploaded=1 WHERE mid=<batch id>
      //              (the sibling uploader used by dna/allergy,
      //               inc/actions/ci_upload_result_pdf_to_box_upload.php:137,
      //               writes the batch table UNCONDITIONALLY)
      // Two tables, two rows, one column name -- flow-result-distribution.md:236.
      const both = () => db.query(
        `SELECT ba.box_uploaded, re.box_uploaded
           FROM ci_batches ba JOIN ci_reports re ON re.patient_id = ba.assen
          WHERE ba.assen = ${Db.q(s.assen)}`,
      )[0];

      expect(both(), 'ci_batches.box_uploaded / ci_reports.box_uploaded both start at 0')
        .toEqual(['0', '0']);

      // The trap is structural, and this is the DB-level proof of it: they are
      // two independent columns on two tables, and the schema has NOTHING that
      // keeps them consistent. A trigger or a merged column would make this red.
      const [[batchCol, reportCol, triggers]] = db.query(
        `SELECT (SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema=DATABASE() AND table_name='ci_batches' AND column_name='box_uploaded'),
                (SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema=DATABASE() AND table_name='ci_reports' AND column_name='box_uploaded'),
                (SELECT COUNT(*) FROM information_schema.triggers
                  WHERE trigger_schema=DATABASE()
                    AND event_object_table IN ('ci_batches','ci_reports'))`,
      );
      expect(batchCol, 'ci_batches.box_uploaded exists').toBe('1');
      expect(reportCol, 'ci_reports.box_uploaded exists').toBe('1');
      expect(
        triggers,
        'a trigger now links ci_batches.box_uploaded and ci_reports.box_uploaded -- the '
          + 'audit divergence at flow-result-distribution.md:236 has been fixed and this test '
          + 'must be rewritten',
      ).toBe('0');

      // Drive the MANUAL button the reporting staff actually click
      // (parts/tox/results_pending.php:365). It renders a page, so it answers
      // HTTP 200 with a denial body, not 403 (D185/D250) -- the outcome is read
      // out of the database, never out of the status code (D226).
      const manual = await admin.get(
        `${RESULTS_PAGE}&${new URLSearchParams({
          tr_action: 'ci_upload_result_pdf_to_box',
          type: 'tox',
          batch_id: String(s.batchId),
          lab_id: tenant.labId,
          kt: String(Math.floor(Date.now() / 1000)),
        })}`,
      );
      expect(manual.status(), 'the manual Box uploader renders').toBe(200);
      expect(
        await manual.text(),
        'the manual uploader got past the unauthorized-Box guard at '
          + 'pages/admin_ci_upload_result_pdf_to_box.php:269',
      ).toContain('ReAuthorize');

      // Nothing was queued and neither column moved: the manual path stops at
      // ready() BEFORE $box->add_file() (:353), so there is no ci_box_upload row
      // and therefore no `sender` value to decide which table gets the flag.
      // Scoped by lab_ID + batch_id, NOT by `mid`: ci_box_upload.mid means the
      // BATCH mid on the fax channel and the REPORT id on the Box channel
      // (add_file's second argument differs per caller), so a bare `mid IN (...)`
      // match collides with a sibling spec's rows. Measured, not theoretical.
      expect(
        db.scalar(
          `SELECT COUNT(*) FROM ci_box_upload
            WHERE lab_ID = ${Db.q(tenant.labId)} AND batch_id = ${Db.q(s.batchId)}`,
        ),
        'the manual uploader queued a Box row with no Box session',
      ).toBe('0');
      expect(both(), 'neither box_uploaded column moved on the manual path').toEqual(['0', '0']);

      // ... and the automatic path does not move the batch column either.
      runCron('ci_auto_upload_results');
      expect(both(), 'neither box_uploaded column moved on the automatic path')
        .toEqual(['0', '0']);
      expect(
        db.scalar(`SELECT lab_id FROM ci_batches WHERE assen = ${Db.q(s.assen)}`),
        'tenancy',
      ).toBe(tenant.labId);
    });

  e2e.fixme('S-DST-04 positive leg: a manual upload sets ci_batches.box_uploaded=1 while ci_reports.box_uploaded stays 0 @adm',
    () => {
      // Unreachable on an egress-denied stack, and the blocker is the boundary
      // itself (D091), not a fixture gap:
      //   - pages/admin_ci_upload_result_pdf_to_box.php:269 refuses to enqueue
      //     until CI_Box_Works::ready() is true, and ready() needs a live
      //     `access_token` plus a successful GET /users/me against api.box.com;
      //   - even with the token option planted, :195 writes box_uploaded=1 only
      //     when $box->upload_file() returned a response object, i.e. after a
      //     real upload.box.com round trip.
      // Box is a raw-curl client (13-boundaries.md #26): it never passes through
      // WP_Http, so the pre_http_request stub cannot shape it, and the only
      // runtime seam is the $api_url/$upload_url properties on
      // inc/ci_box_content_api.php:6,:7 -- which needs a local mock host and an
      // application-code (or mu-plugin) change to point at it.
      // The negative + schema control above is what keeps the trap honest until
      // then: it goes red the day the two columns are reconciled.
    });

  e2e('S-DST-05 a provider with no NPI stamps the no_npi sentinel and the report never re-arms',
    async ({ seedRequisition, tenantRequest, admin, db, tenant, runKey }) => {
      const f = infra(db);
      const docLast = `Nonpi${runKey}`;

      // A provider with NO NPI, created by the APPLICATION'S OWN saver: posting
      // bat[doc_ID]=0 with a requesting_physician the clinic has never seen sends
      // ci_save_form_requisition.php:487-495 down its tr_data_update_doc() branch,
      // which inserts a ci_doc row with doc_npi unset. No raw provider insert.
      const s = await releasedSpecimen(
        seedRequisition, tenantRequest, admin, db, tenant, `Admsyn${runKey}c`,
        { 'bat[doc_ID]': '0', 'bat[requesting_physician]': `${docLast}, Synthetic` },
      );

      const [[gotLast, gotNpi, gotCli]] = db.query(
        `SELECT doc_last, CONCAT('[', COALESCE(doc_npi,'<NULL>'), ']'), cli_ID
           FROM ci_doc WHERE doc_ID = ${Db.q(s.docId)}`,
      );
      expect(gotLast.toLowerCase(), 'the saver minted a new provider for this requisition')
        .toBe(docLast.toLowerCase());
      expect(
        ['[]', '[<NULL>]'].includes(gotNpi),
        `ci_doc.doc_npi for the new provider is ${gotNpi} -- the no_npi branch at `
          + 'inc/ci_data_opisafe.php:173 needs it empty',
      ).toBe(true);
      expect(gotCli, 'the new provider hangs off the seeded clinic').toBe(f.clinicId);

      // OpiSafe linkage. FIXTURE GAP, and it is why these two rows are raw
      // inserts rather than an app POST: pages/admin_ci_opisafe_keys.php is the
      // only writer of ci_opisafe_keys / ci_opisafe_key_relationships and NOTHING
      // in the plugin includes or registers it -- there is no menu entry, no
      // tr_action, no page slug. cType='D' against THIS test's own provider, so
      // the cron's network-wide selector cannot reach any other spec's report.
      const labKey = `e2e-${runKey}`;
      db.query(`DELETE r FROM ci_opisafe_key_relationships r
                  JOIN ci_opisafe_keys k ON k.opi_ID = r.opi_ID
                 WHERE k.opi_labkey = ${Db.q(labKey)}`);
      db.query(`DELETE FROM ci_opisafe_keys WHERE opi_labkey = ${Db.q(labKey)}`);
      db.query(
        `INSERT INTO ci_opisafe_keys (opi_key, opi_secret, opi_labkey, opi_lab_ID, opi_desc)
         VALUES ('e2e-synthetic-key', 'e2e-synthetic-secret', ${Db.q(labKey)},
                 ${Db.q(tenant.labId)}, 'e2e adm.spec S-DST-05')`,
      );
      const opiId = db.scalar(`SELECT opi_ID FROM ci_opisafe_keys WHERE opi_labkey = ${Db.q(labKey)}`);
      expect(opiId, 'the OpiSafe key row was created').not.toBe('');
      db.query(
        `INSERT INTO ci_opisafe_key_relationships (opi_ID, target_ID, cType)
         VALUES (${Db.q(opiId)}, ${Db.q(s.docId)}, 'D')`,
      );
      // TENANCY of the rows this test created (D172).
      expect(
        db.scalar(`SELECT opi_lab_ID FROM ci_opisafe_keys WHERE opi_ID = ${Db.q(opiId)}`),
        'the OpiSafe key is bound to the tenant lab',
      ).toBe(tenant.labId);

      // THE ARMING DEFECT, pinned before the sentinel. ci_load_results.php:415
      // inserts a report WITHOUT opi_id, so the column defaults to NULL -- while
      // the cron's selector demands `re.opi_id = ''`, which NULL never satisfies.
      // A report born through the app is therefore invisible to the OpiSafe leg
      // until somebody writes an empty string into it.
      expect(
        ledger(db, s.reportId).opiId,
        'ci_reports.opi_id after the app created the report',
      ).toBe('<NULL>');
      expect(
        db.query(OPI_SELECTOR(s.assen)).length,
        'a NULL opi_id already satisfies the cron selector -- the arming defect is fixed and '
          + 'this assertion must be rewritten',
      ).toBe(0);

      // Arm it the way the runbook says an administrator re-arms a stalled
      // report (flow-result-distribution.md:230, "Clear opi_id to re-arm"). This
      // is the SCENARIO'S STATED PRECONDITION (10-scenarios.md:44, `opi_id=''`),
      // not the outcome under test -- the sentinel below is written by the app.
      db.query(`UPDATE ci_reports SET opi_id = '' WHERE id = ${Db.q(s.reportId)}`);
      expect(
        db.query(OPI_SELECTOR(s.assen)).length,
        `report ${s.reportId} is still not selectable by the OpiSafe cron -- the sentinel `
          + 'assertion that follows would prove nothing',
      ).toBe(1);

      runCron('ci_auto_upload_results');

      // inc/ci_data_opisafe.php:173-176 returns no_npi from a LOCAL check on the
      // provider row, before any api.opisafe.com call, and
      // inc/crons/auto_upload_results_opisafe.php:90 writes the sentinel.
      const stalled = ledger(db, s.reportId);
      expect(
        stalled.opiId,
        `ci_reports.opi_id for ${s.assen}. '' means the cron never reached the provider check; `
          + "'fail' means send_report() got past the NPI branch",
      ).toBe('no_npi');
      expect(stalled.labId, 'tenancy of the stalled report').toBe(tenant.labId);

      // THE STALL. The sentinel is not a retry marker: opi_id is no longer '',
      // so the selector never sees this report again. Delivery is stopped until
      // an administrator clears it by hand.
      expect(
        db.query(OPI_SELECTOR(s.assen)).length,
        'the sentinel still satisfies the selector -- the report would be retried',
      ).toBe(0);
      runCron('ci_auto_upload_results');
      expect(
        ledger(db, s.reportId).opiId,
        'a second cron pass changed the sentinel -- it is not permanent',
      ).toBe('no_npi');

      // This test's own infrastructure rows, removed so a re-run under --no-deps
      // (no reset) does not accumulate linkages.
      db.query(`DELETE FROM ci_opisafe_key_relationships WHERE opi_ID = ${Db.q(opiId)}`);
      db.query(`DELETE FROM ci_opisafe_keys WHERE opi_ID = ${Db.q(opiId)}`);
    });

  e2e('the six auto-fax gate conditions, read live, and the delivery that does not happen',
    async ({ seedRequisition, tenantRequest, admin, db, tenant, runKey }) => {
      const s = await releasedSpecimen(
        seedRequisition, tenantRequest, admin, db, tenant, `Admsyn${runKey}d`,
      );

      // Conditions 1 and 2 live in `ci_pdf_data` -- a SHARED serialized option
      // that also carries barcode_key/barcode_length. READ ONLY: a
      // read-modify-write here races every other spec running against this stack.
      const opt = wpEval(
        '$d=get_blog_option(1,"ci_pdf_data",array());'
        + '$o=["fax_api"=>isset($d["fax_api"])?(string)$d["fax_api"]:"<ABSENT>",'
        + '"fax_time"=>isset($d["fax_time"])?(string)$d["fax_time"]:"<ABSENT>",'
        + '"tox"=>array_key_exists("auto_fax_approve_tox",$d)?(bool)$d["auto_fax_approve_tox"]:"<ABSENT>",'
        + '"blood"=>array_key_exists("auto_fax_approve_blood",$d)?(bool)$d["auto_fax_approve_blood"]:"<ABSENT>",'
        + '"pcr"=>array_key_exists("auto_fax_approve_pcr",$d)?(bool)$d["auto_fax_approve_pcr"]:"<ABSENT>",'
        + '"btypes"=>array_values(tr_pdd_get_batch_types())];',
      );

      const [[faxres, omfax]] = db.query(
        `SELECT cli_faxres, CONCAT('[', COALESCE(\`cli_om-fax\`,'<NULL>'), ']')
           FROM ci_clinic WHERE cID = ${Db.q(infra(db).clinicId)}`,
      );
      const rep = ledger(db, s.reportId);

      // The gate, condition by condition (flow-result-distribution.md:69-77).
      // 1 -- provider selected. OPEN (the build ships defaulted to RingCentral).
      expect(opt.fax_api, 'gate 1: ci_pdf_data.fax_api').toBe('CI_Fax_RingCentral');
      // 2 -- per-btype toggle. READ AND REPORTED, NOT equality-asserted: it lives
      // in the shared `ci_pdf_data` blob and a sibling spec arming it for its own
      // scenario would otherwise turn this test red for no fault of the app.
      // Gates 3-4 below are what close the composite here, and they are enough.
      console.log(`auto_fax_approve_<btype> read live: ${JSON.stringify({ tox: opt.tox, blood: opt.blood, pcr: opt.pcr })}`);
      expect(opt.btypes, 'the btypes the cron iterates').toEqual(['tox', 'blood', 'pcr']);
      // 3 -- clinic opt-in. UNSET on the seeded clinic every requisition uses.
      expect(faxres, 'gate 3: ci_clinic.cli_faxres for the ordering clinic').not.toBe('1');
      // 4 -- clinic fax number. UNSET.
      expect(omfax, 'gate 4: ci_clinic.`cli_om-fax` for the ordering clinic').toBe('[]');
      // 5 -- the report itself. OPEN: released, never faxed.
      expect(rep.faxed, 'gate 5: ci_reports.faxed').toBe('0');
      // 6 -- no queue row yet. OPEN.
      expect(
        db.scalar(
          `SELECT COUNT(*) FROM ci_box_upload
            WHERE lab_ID = ${Db.q(tenant.labId)} AND batch_id = ${Db.q(s.batchId)}`,
        ),
        'gate 6: no ci_box_upload queue row exists for this batch',
      ).toBe('0');

      // The composite outcome: three of the six are closed, so the report is
      // never selected. Run the cron's own clinic-side selector to prove the
      // skip is the CLINIC gate and not an accident of the fixture.
      expect(
        db.query(
          `SELECT re.id FROM ci_batches ba
             JOIN ci_reports re ON ba.assen = re.patient_id
             JOIN ci_clinic cli ON ba.cli_ID = cli.cID
            WHERE re.estatus='C' AND re.faxed=0
              AND cli.cli_faxres = '1' AND cli.\`cli_om-fax\` != ''
              AND re.patient_id = ${Db.q(s.assen)}`,
        ).length,
        'the fax queue builder now selects this report although the clinic never opted in',
      ).toBe(0);

      const fatal = fireFaxCron();
      console.log(`ci_auto_fax_results: ${fatal || 'completed without a PHP fatal'}`);
      if (fatal) {
        // Pin WHERE it died, so a NEW fatal somewhere else cannot hide behind
        // this tolerance.
        expect(
          fatal,
          'the fax cron fatals somewhere other than the RingCentral token refresh',
        ).toContain('ci_fax_ringcentral.php');
      }

      // A non-opted-in clinic is skipped SILENTLY: no queue row, no failure row,
      // no faxed flag. Asserted on the ledger, never on the cron's exit status.
      expect(
        db.scalar(
          `SELECT COUNT(*) FROM ci_box_upload
            WHERE lab_ID = ${Db.q(tenant.labId)} AND batch_id = ${Db.q(s.batchId)}`,
        ),
        `the fax cron queued specimen ${s.assen} although the ordering clinic never opted in `
          + '(gates 3 and 4 unset)',
      ).toBe('0');
      const after = ledger(db, s.reportId);
      expect(after.faxed, 'ci_reports.faxed after the fax cron').toBe('0');
      expect(after.labId, 'tenancy').toBe(tenant.labId);
    });

  e2e.fixme('S-DST-01 the armed gate queues rs_efax[tox] at status 5 and sends it to status 1 + faxed=1 @adm',
    () => {
      // Two blockers, and the first one is why this file flips nothing:
      //  1. Gates 1-2 live in `ci_pdf_data`, ONE serialized network option shared
      //     with barcode_key/barcode_length and ~280 other keys. Arming
      //     auto_fax_approve_tox is a read-modify-write of that whole blob, which
      //     races every other spec running against this stack; and gates 3-4 live
      //     on ci_clinic cID 1, the single clinic every spec's requisitions point
      //     at, so opting it in would arm auto-fax for THEIR reports too.
      //     A dedicated clinic + a dedicated per-spec option would fix this; both
      //     are fixture work, not spec work.
      //  2. Even fully armed, the send leg needs RingCentral. auto_fax_results.php:43
      //     calls $fax->is_expired() and aborts the whole run when refreshToken()
      //     fails, and the egress-denied network refuses platform.ringcentral.com
      //     (13-boundaries.md #32/#33). FaxAge has no sandbox at all
      //     (api/ci_fax_faxage.php:5 hardcodes the production host), so there is
      //     no safe provider to substitute.
    });
});

/**
 * DWA-E17 -- THE DELIVERY-CONFIG GATE IS A LOGIN STRING AND A STYLESHEET.
 *
 * `inc/ci_pdf_data-options.php:67`, at the pin:
 *
 *     $user     = wp_get_current_user();
 *     $is_admin = $user->user_login == 'Administrat0r' && !isset($_GET['testnotadmin']);
 *     if (!$is_admin) { ?><style> .onlyadmin_tab, .efaxinfo_tab, ... { display: none !important; } </style><?php }
 *
 * Three separate facts live in those two lines, and they are worth keeping
 * apart because only the first two are pinned below.
 *
 * 1. THE GATE IS AN IDENTITY, NOT A CAPABILITY. It compares a LOGIN STRING.
 *    `e2e_dir` holds the `administrator` role, `manage_options`, and the
 *    largest live capability set in the install (150, pinned at :330 above) --
 *    and is refused, because its login is not the literal `Administrat0r`.
 *    Fourteen sections go with the Fax tab: For Admin, Forms, Req Form,
 *    Inbound, Covid, Reports, Prefill, Results & PDF, ABX, Receiving,
 *    Rack & Plate, APIs, Backup & Restore, Supply Store.
 *
 * 2. `?testnotadmin` DOWNGRADES, AND ONLY DOWNGRADES. Be exact about this, and
 *    do not let it be read as an escalation: the clause is `&& !isset(...)`, so
 *    the parameter can only turn a TRUE `$is_admin` false. It changes the view
 *    of exactly one login -- the one the gate already admits -- and grants
 *    nothing to anybody else. Measured all three ways on this stack: `e2e_dir`
 *    Fax link 0 with the parameter and 0 without; `Administrat0r` 1 without and
 *    0 with. What it proves is not a privilege bug but that the "gate" is a
 *    per-request DISPLAY TOGGLE driven by request input, which an authorization
 *    boundary is not.
 *
 * 3. AND IT IS A STYLESHEET. The refused seat is served the whole panel. The
 *    menu entry is emitted either way -- measured, `e2e_dir` renders
 *    `<li class="fax efaxinfo_tab"><a class="nav_tab_link" href="#eFaxInfo_tab">Fax</a></li>`
 *    verbatim -- and the `<style>` block is the only thing that removes it
 *    (`TabsListing` lowercases the tab id into the class, tr_admin_page_class_v11.php:786,
 *    which is why `.efaxinfo_tab` matches `eFaxInfo_tab`). The controls
 *    themselves -- `auto_fax_approve_tox|blood|pcr`, `fax_time`, `fax_api`,
 *    :267-288 -- are in the refused seat's DOM, and the save at :146
 *    (`if (isset($_POST['action'])) { include install_options.php; }`) carries
 *    no gate of any kind. That third fact is NOT driven below and the reason is
 *    a fixture one, not a doubt: the save rewrites `ci_pdf_data`, ONE shared
 *    serialized network option carrying ~287 keys including `barcode_key` and
 *    every other spec's gate inputs (see this file's header). Posting it from a
 *    test would corrupt the whole stack. The DOM-presence assertion below is
 *    what pins the mechanism instead.
 *
 * REACHING THE SCREEN. The panel is `?page=ci_pdf_data&pagetab=this`. Its
 * "Other Settings" tab link is itself gated on the same literal login one layer
 * up -- `inc/admin.php:113` sets `$is_ci_admin` from `user_login`, and :183
 * drops `$setting_tabs['this']` without it -- so `Administrat0r` reaches the
 * panel by CLICKING the app's own link (done below), while `e2e_dir` reaches
 * the same panel, fully rendered, only by URL. That second gate is already
 * measured and recorded in `dst.spec.ts` (the OpiSafe block, :687-693) and is
 * not re-filed here.
 *
 * NOT A SHARED-STATE WRITE. Rendering this panel runs `buildOptions()` for
 * every field (tr_admin_page_class_v11.php:937-951), which CAN
 * `update_option('ci_pdf_data', ...)`. Measured before believing it: md5 of the
 * serialized option across three panel renders, `7eb9f336...` -> `7eb9f336...`,
 * 287 keys and 219 literal-`false` values unchanged. These tests are reads.
 */
e2e.describe('delivery-config visibility @adm', () => {
  const PANEL = '/wp-admin/admin.php?page=ci_pdf_data&pagetab=this';
  /** The one login `inc/ci_pdf_data-options.php:67` admits. */
  const MAGIC = 'Administrat0r';
  /**
   * The auto-fax toggle, and the only fax control on the panel that HAS an
   * accessible name: `fax_time` and `fax_api` are `<label for="fax_time">` over
   * a `<select name="fax_time">` with no `id`, so `getByLabel` resolves 0 for
   * both (DWA-E16). The checkboxes carry ids and resolve 1.
   */
  const AUTO_FAX_TOX = 'Auto Fax when Approved TOX';

  e2e('the fax controls are shipped to the lab administrator with only the menu link suppressed', async ({
    pageAs,
  }) => {
    // THE POSITIVE CONTROL FOR BOTH PINS BELOW, and it lives here rather than
    // inside either of them because an assertion that fails inside a `fail()`
    // body is indistinguishable from the pinned failure: if the Fax tab vanished
    // for EVERY seat, a control placed in a pinned body would go on reporting an
    // expected failure and say nothing. Here it goes red.
    //
    // Reached the way the ceiling seat actually reaches it -- the app's own
    // settings tab strip, not a typed URL -- so this also proves the link goes
    // where the pins read.
    //
    // SCOPED TO THE CONTENT REGION, and it is load-bearing: unscoped,
    // `Other Settings` resolves 2, because ci-cache-and-security contributes an
    // unrelated entry (`?page=trcs_settings&pagetab=trcs_otherst`) to
    // #adminmenu, and the strict-mode throw is not a readable failure.
    // `getByRole('main')` is #wpbody, count 1.
    const magic = await pageAs(MAGIC);
    await magic.goto('/wp-admin/admin.php?page=ci_pdf_data');
    await magic.getByRole('main').getByRole('link', { name: 'Other Settings', exact: true }).click();
    await expect(
      magic.getByRole('link', { name: 'Fax', exact: true }),
      'the one login the gate admits is offered the Fax tab -- the control that makes both pins '
        + 'below mean "this seat was refused it" rather than "this panel has no Fax tab"',
    ).toBeVisible();

    // The precondition that makes this a capability finding rather than a
    // naming coincidence: this seat holds the admin capability and is not the
    // login the gate names. D255 -- allcaps, never user_can().
    const who = wpEval(
      '$u = get_user_by("login", "e2e_dir");'
      + '$o = ["login" => $u->user_login,'
      + '      "manage_options" => !empty($u->allcaps["manage_options"]),'
      + '      "roles" => array_values($u->roles)];',
    );
    expect(who.roles, 'the seeded lab-administrator persona').toEqual(['administrator']);
    expect(who.manage_options, 'and it holds manage_options live').toBe(true);
    expect(
      who.login,
      'e2e_dir has been renamed to the literal login inc/ci_pdf_data-options.php:67 admits, '
        + 'so this file can no longer tell a capability gate from an identity gate',
    ).not.toBe(MAGIC);

    const page = await pageAs('e2e_dir');
    await page.goto(PANEL);

    // The panel really did render for this seat -- otherwise "hidden" below
    // would just mean "denied" (D141/D388).
    await expect(
      page.getByRole('link', { name: 'General', exact: true }),
      'the settings panel rendered its tab strip for this seat, so a suppressed tab below is '
        + 'a suppressed tab and not a denial',
    ).toBeVisible();

    // THE MECHANISM. `toHaveCount(1)` before `toBeHidden()` on purpose:
    // toBeHidden() also passes on an absent node, so on its own it would assert
    // nothing about a control that had simply been deleted (wave-5 review, 7.0
    // finding 6). Together they say: the app SENT this seat the auto-fax
    // toggle, and then hid it with CSS.
    const tox = page.getByLabel(AUTO_FAX_TOX);
    await expect(
      tox,
      'the auto-fax toggle is not in the refused seat\'s DOM at all -- the gate has become a '
        + 'server-side one and the CSS-only mechanism this file pins is gone',
    ).toHaveCount(1);
    await expect(
      tox,
      `${AUTO_FAX_TOX} is visible to a seat whose Fax tab link is suppressed`,
    ).toBeHidden();

    // DOWNGRADE-ONLY, leg two. Same seat, same URL, `?testnotadmin` appended:
    // nothing changes, because the clause it lives in can only turn a TRUE
    // $is_admin false. This is the assertion that reds if the clause is ever
    // inverted or OR-ed into an escape hatch.
    await page.goto(`${PANEL}&testnotadmin=1`);
    await expect(
      page.getByRole('link', { name: 'Fax', exact: true }),
      '?testnotadmin handed the Fax tab to a seat the gate refuses -- the parameter has stopped '
        + 'being a one-way downgrade and is now a way to widen what a user is offered',
    ).toHaveCount(0);
    await expect(
      page.getByLabel(AUTO_FAX_TOX),
      'and the panel itself still renders for this seat with the parameter set',
    ).toHaveCount(1);
  });

  /**
   * PIN 1. The assertion states what the app SHOULD do -- offer the delivery
   * configuration to the seat that holds the capability -- and `e2e.fail()`
   * records that it does not. Replace the login comparison at
   * `inc/ci_pdf_data-options.php:67` with a capability check and this reports
   * "Expected to fail, but passed", turning the run RED.
   *
   * e2e.fail() is INSIDE the body (rev.spec.ts:970, dst.spec.ts:886): at
   * describe scope it annotates every sibling and would mask a real regression
   * in the measured test above.
   */
  e2e('a lab administrator holding manage_options cannot reach the fax delivery configuration', async ({
    pageAs,
  }) => {

    const page = await pageAs('e2e_dir');
    await page.goto(PANEL);

    await expect(
      page.getByRole('link', { name: 'General', exact: true }),
      'the settings panel rendered for this seat, so a missing Fax tab is a missing tab',
    ).toBeVisible();

    await expect(
      page.getByRole('link', { name: 'Fax', exact: true }),
      'the lab administrator -- `administrator` role, manage_options, the largest capability '
        + 'set in the install -- is not offered the Fax tab, so nobody who is not literally '
        + 'logged in as Administrat0r can configure fax delivery for the laboratory (DWA-E17, '
        + 'inc/ci_pdf_data-options.php:67)',
    ).toBeVisible();
  });

  /**
   * PIN 2, and it is separate from PIN 1 on purpose. A fix that swaps the login
   * comparison for a capability check but leaves `&& !isset($_GET['testnotadmin'])`
   * standing flips PIN 1 and leaves this one failing -- which is the correct
   * report, and is exactly what merging the two into one test would hide.
   */
  e2e('a query parameter strips the fax configuration from the one login the gate admits', async ({
    pageAs,
  }) => {

    // ONE ASSERTION AND NOTHING ELSE IN THIS BODY. The control that gives it
    // meaning -- the ceiling seat IS offered the Fax tab -- is the measured test
    // above, where a failure reds instead of being absorbed by this marker.
    const page = await pageAs(MAGIC);
    await page.goto(`${PANEL}&testnotadmin=1`);
    await expect(
      page.getByRole('link', { name: 'Fax', exact: true }),
      'appending ?testnotadmin to the URL removed the Fax tab, and thirteen other sections '
        + 'with it, from the only login inc/ci_pdf_data-options.php:67 admits -- what the app '
        + 'offers an operator is decided by a query parameter, which is a debug toggle wired '
        + 'into a configuration gate (DWA-E17)',
    ).toBeVisible();
  });
});

// ---------------------------------------------------------------------------

e2e.describe('ci_user_edit privilege inversion @adm', () => {
  /**
   * `page=ci_user_edit` is a HIDDEN subpage registered under `index.php` with
   * capability `read` (`inc/admin.php:782`), so every authenticated staff seat
   * -- `e2e_tech` is a `lab_tech` holding ONLY `read` -- passes the menu gate and
   * reaches `parts/user/edit.php`. That file's own guard (`:24-32`) grants edit
   * access when the TARGET user holds `administrator`/`clms`/`AdminCIDW`, so the
   * check is INVERTED: the more privileged the target, the more editable it is.
   * A `lab_tech` is DENIED editing a peer but HANDED the superadmin's editable
   * profile -- email field and a "set new password" field included.
   *
   * CORRECT behaviour, asserted here: a seat with no user-management capability
   * is DENIED editing the administrator. The product allows it, so the admin
   * leg fails today; `e2e.fail()` records that and reds the run when
   * `parts/user/edit.php:24-28` is replaced with a capability check on the
   * ACTOR. The peer-denial leg (which passes today) is the non-vacuity control:
   * it proves the screen CAN deny, so the admin leg is a real inversion.
   *
   * DB anchor (D178/D255): `WP_User->allcaps` read from the RUNNING instance
   * confirms `e2e_tech` genuinely lacks `list_users`/`edit_users`/`manage_options`
   * -- so reaching an editable admin profile is an escalation, not a mis-seeded
   * super-tech. The write path (`trajax=save_profile`, no nonce) is NOT driven:
   * it would rewrite a real admin's password. The read leg is damning on its own.
   */
  e2e('a lab_tech is denied editing a peer but reaches the superadmin edit form', async ({
    pageAs,
  }) => {
    // INSIDE the body, not at describe scope: at describe scope the marker annotates every
    // sibling this describe ever gains and would mask a real regression (this file's own
    // convention, stated two screens up).
    const EDIT = (uid: string | number) =>
      `/wp-admin/admin.php?page=ci_user_edit&user_id=${uid}`;
    const DENIED = 'not allowed to access this page';

    // DB anchor: the actor holds none of the user-management capabilities.
    const caps = wpEval(
      '$u=get_user_by("login","e2e_tech");'
      + '$o=["roles"=>implode(",",$u->roles),'
      + '"list_users"=>!empty($u->allcaps["list_users"]),'
      + '"edit_users"=>!empty($u->allcaps["edit_users"]),'
      + '"manage_options"=>!empty($u->allcaps["manage_options"]),'
      + '"read"=>!empty($u->allcaps["read"]),'
      // ids resolved here so the test carries no hardcoded user ids.
      + '"admin_id"=>(int)(get_user_by("login","Administrat0r")->ID),'
      + '"peer_id"=>(int)(get_user_by("login","e2e_doctor")->ID)];',
    ) as Record<string, unknown>;
    expect(caps.roles, 'the actor is a lab_tech').toBe('lab_tech');
    expect(caps.read, 'and holds read, so it clears the ci_user_edit menu gate').toBe(true);
    expect(caps.list_users, 'but cannot list users').toBe(false);
    expect(caps.edit_users, 'nor edit users').toBe(false);
    expect(caps.manage_options, 'nor manage options').toBe(false);
    const adminId = String(caps.admin_id);
    const peerId = String(caps.peer_id);
    expect(Number(adminId), 'Administrat0r id resolved').toBeGreaterThan(0);
    expect(Number(peerId), 'e2e_doctor id resolved').toBeGreaterThan(0);

    const page = await pageAs('e2e_tech');

    // CONTROL (passes today): editing a NON-privileged peer is refused. Read
    // back from a fresh navigation (D386), assert on the wp_die body text.
    await page.goto(EDIT(peerId));
    await expect(
      page.getByText(DENIED, { exact: false }),
      'a lab_tech is refused editing a peer (e2e_doctor) -- the guard works when the target '
        + 'is not privileged',
    ).toBeVisible();
    await expect(
      page.locator('#new_password'),
      'and the peer edit form -- including its password field -- is not rendered',
    ).toHaveCount(0);

    // CORRECT behaviour, fails today: editing the SUPERADMIN must be refused
    // just the same. It is not -- the form renders, password field and all.
    await page.goto(EDIT(adminId));
    // ONE assertion, both halves, because this is a fail() body: two sequential expects
    // would let the FIRST one fail under the fix, be absorbed as the expected failure, and
    // never reach the second -- green over a fixed product. Read off a fresh server render
    // (D386). The peer leg above drives both locators to the opposite values, so neither
    // half is vacuous: it is the same screen, refusing, with no password field.
    expect(
      {
        denied: await page.getByText(DENIED, { exact: false }).count() > 0,
        passwordFieldServed: await page.locator('#new_password').count() > 0,
      },
      'a lab_tech (read only, no list_users/edit_users/manage_options) must be refused editing '
        + 'the superadmin, but parts/user/edit.php:24-28 gates on the TARGET role, so the admin '
        + 'edit form is served -- password field and all. That field IS the escalation: a seat '
        + 'with no user-management capability is handed the control that resets the superadmin '
        + 'credential (privilege inversion, DWA user-edit)',
    ).toEqual({ denied: true, passwordFieldServed: false });
  });
});
