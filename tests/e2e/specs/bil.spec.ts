import { request as playwrightRequest, type Page } from '@playwright/test';
import * as path from 'node:path';
import { test, expect, BEFORE_BOUNCE_MS } from '../fixtures/test';

import { STATE_DIR } from '../playwright.config';
import { expectBatch } from '../fixtures/assert-domain';
import { cronHandlers, runCron, wpEval } from '../fixtures/container';
import { infra } from '../fixtures/seed-domain';
import { batchIds } from '../fixtures/batch-id';
import { Db } from '../fixtures/db';

/**
 * BIL -- billing staff: per-specimen ledger, bill status, eligibility, exports.
 *
 * PERSONA, measured against the RUNNING instance (D178), not install_roles.php:
 *
 *   Billing          11 caps: read, level_1, level_0, ci_data_forms,
 *                    ci_current_clients, form_custom_profiles,
 *                    ci_testing_profiles, BILLING_ADMIN, ci_custody_chain,
 *                    ci_payer_list, ci_ins_mix
 *   sales_accounting  7 caps: read, level_1, level_0, ci_manage_reps,
 *                    ci_data_reports, ci_data_store, ci_data_forms
 *   BillbyClinic      4 caps: read, level_1, level_0, and a capability literally
 *                    named "0" -- a numeric-key bug in the installed role.
 *   BillbyRefLab      ABSENT from wp_user_roles entirely.
 *
 * personas.md:179 says `Billing` holds "`ci_data_forms` only". That is the
 * source view and it is stale: the live role carries `billing_admin`, which is
 * the capability `trcbi_init_admin()` (ci-billing-indep/inc/admin.php:14) tests
 * to expose the Specimen Billing menu. So the capability that gates billing is
 * `billing_admin`, held live by `Billing`, `clms` and `AdminCIDW`;
 * `BillbyClinic` and `sales_accounting` get in on the role-slug branch at
 * admin.php:17 instead, holding no billing capability at all.
 *
 * WHICH PERSONA THESE TESTS RUN AS. **D204 is SUPERSEDED** -- the claim that no
 * billing persona exists was true of the old seed and is false now.
 * `env/seed.php:377-381` creates `e2e_billing` (role `Billing`) and
 * `e2e_billclin` (role `BillbyClinic`), the seed asserts the distinguishing
 * capability by LITERAL key against the running instance before it will
 * continue, and `setup/env.setup.ts:34` mints and identity-checks a
 * storageState for both. `pageAs('e2e_billing')` / `pageAs('e2e_billclin')`
 * therefore work, and S-BIL-07 below is no longer fixme'd.
 *
 * S-BIL-02/03/04/06 still run as the tenant fixture's `leadlabsecman`, and
 * deliberately: they exercise the WRITE path, where the tr_action DISPATCHER
 * applies no per-action capability check (D175, admin/init.php:55-64), so the
 * acting role is not the subject. S-BIL-07 is the one scenario where the seat
 * IS the subject, and it is the only test here that names a billing persona.
 *
 * `ci_batch_billings` SCHEMA (10-scenarios.md Gap 11). Checked live before
 * writing an assertion: the table carries the FULL engine column set --
 * id, patient, claim_no, assen(UNIQUE), servdate, date, allowed_amount,
 * insurance_payment, claim_date, billed_amount, received_amount, balance,
 * bill_status, patient_balance. The DWA-minimal-column trap does not bite on
 * this build, so `bill_status` writes are real writes, not no-ops.
 */

/** Module-scoped so the per-spec block is handed out in order (D243/D396). */
const nextBatchId = batchIds('bil');

test.describe('S-BIL-02 @smoke @bil', () => {
  test('a requisition saved with bb[] mints the billing ledger row and mirrors the claim number', async ({
    seedRequisition, db, tenant, runKey,
  }, testInfo) => {
    const patientLast = `Bilsyn${runKey}a`;
    // int(11) column; 7 digits keeps it inside the range and obviously synthetic.
    const claimNo = String(9000000 + (testInfo.workerIndex * 1000) + 1);

    // The ledger is written by trcdbi_saved_req() (ci-billing-indep/inc/admin.php:177),
    // hooked on `saved_req`, which the tox saver fires at
    // ci_save_form_requisition.php:963. There is no separate "create billing row"
    // endpoint -- the ONLY producer is the requisition saver carrying $_POST['bb'],
    // so the app's own saver is the only honest way in (D085/D099).
    // These four keys are exactly what parts/tox/req_form.php:1763-1779 posts.
    const seeded = await seedRequisition(patientLast, {
      'bb[claim_no]': claimNo,
      'bb[claim_date]': '2026-01-02',
      'bb[bill_status]': 'Need Info',
      'bb[billed_amount]': '125.00',
    });
    expect(seeded.status, 'the saver answers 200 on the full-document POST').toBe(200);

    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: infra(db).testGroupId });

    // One SELECT, not four (D164). Exact match on assen -- never LIKE.
    const rows = db.query(
      `SELECT bb.id, bb.claim_no, bb.claim_date, bb.bill_status, bb.billed_amount, ba.claim_id, ba.lab_id
         FROM ci_batch_billings bb
         JOIN ci_batches ba ON ba.assen = bb.assen
        WHERE bb.assen = ${Db.q(assen)}`,
    );
    expect(rows.length, `expected exactly one ci_batch_billings row for ${assen}`).toBe(1);
    const [, gotClaimNo, gotClaimDate, gotStatus, gotBilled, gotClaimId, gotLabId] = rows[0];

    expect(gotClaimNo, 'ci_batch_billings.claim_no').toBe(claimNo);
    expect(gotClaimDate, 'ci_batch_billings.claim_date').toBe('2026-01-02');
    expect(gotStatus, 'ci_batch_billings.bill_status').toBe('Need Info');
    expect(gotBilled, 'ci_batch_billings.billed_amount').toBe('125.00');

    // The mirror: admin.php:205-207 copies bb[claim_no] onto the batch.
    expect(gotClaimId, `ci_batches.claim_id must mirror the ledger claim number for ${assen}`)
      .toBe(claimNo);

    // TENANCY (D172). The ledger has no lab column of its own, so it is asserted
    // through the batch it joins to.
    expect(gotLabId, `ledger row for ${assen} hangs off lab ${gotLabId}, tenant is ${tenant.labId}`)
      .toBe(tenant.labId);

    console.log(
      `S-BIL-02 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) mid=${mid} assen=${assen} claim=${claimNo}`,
    );
  });
});

test.describe('S-BIL-06 @bil', () => {
  test('the worklist status action writes bill_status=Need Billing', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }) => {
    const patientLast = `Bilsyn${runKey}b`;
    const seeded = await seedRequisition(patientLast, {
      'bb[claim_no]': '0',
      'bb[bill_status]': '',
      'bb[billed_amount]': '0',
    });
    expect(seeded.status).toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: infra(db).testGroupId });

    // The row must start WITHOUT the target value, or the assertion below cannot
    // tell a write from a pre-existing state.
    expect(
      db.scalar(`SELECT bill_status FROM ci_batch_billings WHERE assen = ${Db.q(assen)}`),
      'ledger starts with an empty bill_status',
    ).toBe('');

    // The app's own control (admin_ci_billing_list.php:795 posts exactly this to
    // ajaxurl). The dispatcher runs it on admin_init and exit()s
    // (ci-billing-indep/inc/admin_actions.php:9-46).
    const res = await tenantRequest.post('/wp-admin/admin-ajax.php', {
      form: { tr_action: 'ci_change_batch_bill_status', action: 'ajax', id: mid, btype: 'tox', status: 'Need Billing' },
    });
    // Not toBeLessThan(400): a login redirect is 302 and would pass that.
    expect(res.status(), 'the status action answers 200').toBe(200);
    expect(await res.text(), 'the action echoes its own confirmation before exit()')
      .toContain('Changed Status Successfully');

    expect(
      db.scalar(`SELECT bill_status FROM ci_batch_billings WHERE assen = ${Db.q(assen)}`),
      `ci_batch_billings.bill_status for ${assen} -- if this is still empty the ` +
        'engine column set is missing and the write no-opped (Gap 11)',
    ).toBe('Need Billing');

    // The row must not have multiplied: assen is UNIQUE, so a second row is
    // impossible, but a WRONG-row update is not. Pin the count and the tenancy.
    const [[count, labId]] = db.query(
      `SELECT (SELECT COUNT(*) FROM ci_batch_billings WHERE bill_status='Need Billing' AND assen=${Db.q(assen)}),
              (SELECT lab_id FROM ci_batches WHERE assen=${Db.q(assen)})`,
    );
    expect(count, 'exactly one Need Billing row for this accession').toBe('1');
    expect(labId, 'tenancy of the batch behind the ledger row').toBe(tenant.labId);
  });
});

test.describe('S-BIL-03 @bil', () => {
  /**
   * The catalog's trigger for S-BIL-03 is `?tr_action=ci_xml_pverify_run&mid=&logid=`
   * (flow-billing-and-insurance.md:88). That entry point DOES NOT EXIST on this
   * build, and the reason is not egress:
   *
   *   - `tradmin_action_ci_xml_pverify_run` / `trfront_action_ci_xml_pverify_run`
   *     are defined nowhere in wp-content/plugins. The dispatcher at
   *     ci-data-works-arc/admin/init.php:59 resolves tr_action ONLY by that name
   *     prefix, so the two links at pages/admin_ci_log_pverify.php:103-104 post an
   *     action the dispatcher cannot resolve and nothing runs.
   *   - The other caller, inc/crons/pverify.php, is never `include`d and its hook
   *     `ci_cron_pverify` has no `add_action` -- the file's only reference to that
   *     hook is its own self-rescheduling call at :35-36. This is the documented
   *     "pVerify cron binding external" defect (flow-billing-and-insurance.md §7).
   *
   * So inc/actions/ci_xml_pverify_run.php is unreachable code from both HTTP and
   * cron. The test below is the negative control that keeps that claim honest: it
   * fails the day someone wires the dispatcher up.
   */
  test('the biller pVerify entry point is not wired, and posting it changes nothing', async ({
    seedRequisition, tenantRequest, db, tenant, runKey,
  }) => {
    const wiring = wpEval(
      '$o=["dispatcher"=>function_exists("tradmin_action_ci_xml_pverify_run"),'
      + '"front"=>function_exists("trfront_action_ci_xml_pverify_run"),'
      + '"cron"=>(bool)has_action("ci_cron_pverify")];',
      // docron is load-bearing on the `cron` leg: DWA includes inc/crons.php
      // only when DOING_CRON is defined at plugin LOAD time (plug.php:65).
      // Without it has_action() answers false for EVERY hook, and this
      // negative control would pass while proving nothing (D141).
      { docron: true },
    );
    expect(
      wiring,
      'ci_xml_pverify_run has acquired an entry point -- S-BIL-03 is now implementable '
        + 'and this fixme/negative-control pair must be replaced',
    ).toEqual({ dispatcher: false, front: false, cron: false });

    // Prove it end to end against a real specimen, not just by introspection.
    const patientLast = `Bilsyn${runKey}c`;
    // Synthetic payer name. ci_xml_pverify_run.php:19 needs a non-empty insurance
    // that is not 'No Insurance - Cash', so a run that DID happen would get past
    // the first guard -- which is what makes the non-effect below meaningful.
    const insurance = `E2E Synthetic Payer ${runKey}`;

    // trpdd_log_form() inserts with target_id=0 (inc/functions.php:1164), so the
    // saver's log row has no key back to the batch. Identify it the only exact
    // way available: newest row authored by this worker's own persona, and the
    // tenant fixture gives one persona per worker.
    const userId = db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(tenant.login)}`);
    const logBefore = db.scalar(
      `SELECT COALESCE(MAX(id),0) FROM ci_log_forms WHERE author_id = ${Db.q(userId)}`,
    );

    const seeded = await seedRequisition(patientLast, { 'bat[insurance]': insurance });
    expect(seeded.status).toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: infra(db).testGroupId });

    const logId = db.scalar(
      `SELECT id FROM ci_log_forms
        WHERE author_id = ${Db.q(userId)} AND id > ${Db.q(logBefore)} AND form = 'save_tox'
        ORDER BY id DESC LIMIT 1`,
    );
    expect(logId, 'the tox saver logs one save_tox row per save (ci_save_form_requisition.php:984)')
      .not.toBe('');

    await tenantRequest.post('/wp-admin/admin-ajax.php', {
      form: { tr_action: 'ci_xml_pverify_run', action: 'ajax', mid, logid: logId, need_update: '1' },
    });

    // The outcome is asserted, not the status code: the app writes the pVerify
    // ledger by UPDATING this very row to form='pverify' (ci_xml_pverify_run.php:160
    // -> trpdd_log_form). It is still the saver's row, so nothing ran.
    const [[form, batchStatus, labId]] = db.query(
      `SELECT (SELECT form FROM ci_log_forms WHERE id = ${Db.q(logId)}),
              ba.batch_status, ba.lab_id
         FROM ci_batches ba WHERE ba.assen = ${Db.q(assen)}`,
    );
    expect(form, `ci_log_forms.${logId}.form -- a pverify stamp here means the action ran`)
      .toBe('save_tox');
    expect(batchStatus, 'batch_status must not be pre-verify: no eligibility check ran')
      .not.toBe('pre-verify');
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_log_forms WHERE form='pverify' AND target_id=${Db.q(mid)}`),
      'no pverify ledger row exists for this specimen',
    ).toBe('0');
    expect(labId, 'tenancy').toBe(tenant.labId);
  });

  test.fixme('S-BIL-03 eligibility write-back: ci_insurances member/group id + batch_status=pre-verify + a pverify ci_log_forms row @bil',
    () => {
      // Blocked twice over. First, no entry point exists (see the control above).
      // Second, even wired up it is unreachable here: CI_PVerify_Client's
      // constructor builds `new SoapClient('https://www.pverify.net/...?WSDL')`
      // (inc/ci_pverify_client.php:34) with ext-soap, which never passes through
      // WP_Http and so is invisible to the pre_http_request stub; the
      // egress-denied network refuses it. The write-back branch runs only on a
      // real eligibility object from that call.
    });
});

test.describe('S-BIL-05 @bil', () => {
  test('the recurring HL7 billing cron has no handler -- the documented dead cron', () => {
    // flow-billing-and-insurance.md:156 / ci-billing-indep §9 item 1. Measured
    // with DOING_CRON defined at plugin load, which is when every cron handler
    // in this codebase registers; anything still false here is genuinely dead.
    const h = cronHandlers([
      'cron_api_billing_sftp',      // Telcor -- alive, registered at inc/crons.php:88
      'cron_api_billing_sftp_hl7',  // HL7 "Genius" -- scheduled at
                                    // admin_ci_apis.php:23, handled NOWHERE
      'ci_cron_send_webhook',       // billing webhook -- alive, inc/init.php:19
    ]);
    expect(h.cron_api_billing_sftp, 'Telcor cron handler').toBe(true);
    expect(h.ci_cron_send_webhook, 'webhook cron handler').toBe(true);
    expect(
      h.cron_api_billing_sftp_hl7,
      'cron_api_billing_sftp_hl7 has a handler -- the defect at ' +
        'flow-billing-and-insurance.md:156 has been fixed and S-BIL-05 needs rewriting',
    ).toBe(false);
  });

  test.fixme('S-BIL-05 HL7 ADT^A08 push writes ci_sync_api_orders.mo_file_id=-5 @bil', () => {
    // Needs the manual "Test Sync Reports" button (the cron above is dead), an
    // approved report per specimen, and an ssh2 SFTP session to the Genius
    // endpoint. ext-ssh2 IS installed in this image, so the push would attempt a
    // real connection -- which the egress-denied network refuses before any
    // dedup row is written.
  });
});

test.describe('S-BIL-04 @bil', () => {
  test('firing the Telcor billing cron leaves no export ledger row behind', async ({
    seedRequisition, db, tenant, runKey,
  }) => {
    const patientLast = `Bilsyn${runKey}d`;
    const seeded = await seedRequisition(patientLast);
    expect(seeded.status).toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: infra(db).testGroupId });

    const before = db.scalar("SELECT COUNT(*) FROM ci_sync_api_orders WHERE mo_file_id = -18");

    // Deterministic single-hook fire (D084). NOT --due-now (D017).
    runCron('cron_api_billing_sftp');

    // CONTAINMENT, asserted as an outcome and not assumed. telcor_billing_sftp.php
    // calls $client->connect() BEFORE $client->sync(), so on an egress-denied
    // network the exporter cannot reach the point where it would mark a specimen
    // as sent. No specimen may be flagged exported when nothing was exported --
    // a -18 row here would mean the ledger lies about delivery.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_sync_api_orders WHERE mo_file_id = -18 AND mo_mid = ${Db.q(mid)}`),
      `specimen ${assen} was marked Telcor-exported although the SFTP endpoint is unreachable`,
    ).toBe('0');
    expect(
      db.scalar("SELECT COUNT(*) FROM ci_sync_api_orders WHERE mo_file_id = -18"),
      'the Telcor cron created -18 ledger rows with no SFTP session',
    ).toBe(before);
  });

  test.fixme('S-BIL-04 Telcor export writes ci_sync_api_orders.mo_file_id=-18 + the 103-column CSV @bil',
    () => {
      // Three blockers, all outside this spec's reach: (1) the exporter is off --
      // option tr_billing_sftp_st is NULL, so telcor_billing_sftp.php:34 short-
      // circuits; (2) it needs a reachable SFTP endpoint, and connect() precedes
      // sync(); (3) it needs a report with estatus in ('C','F') and an
      // approve_date in the run day, which no BIL fixture creates.
      // Also unpinnable as written: api id -18 is shared by the Telcor exporter,
      // the insurance exporter and OpenText (10-scenarios.md:215), so a -18 row
      // does not identify WHICH exporter wrote it.
    });
});

test.describe('S-BIL-01 @bil', () => {
  test.fixme('approval hands the specimen to billing exactly once (mo_file_id=-47) @bil', () => {
    // The listener at ci-billing-indep/inc/init.php:12-17 schedules
    // ci_cron_send_webhook ONLY when tr_wsdl_apis_info_sts['billing_webhook'] is
    // set; that option is NULL on this build, and it is a network-wide option, so
    // setting it would change every other worker's approval behaviour -- that
    // makes it @serial state, not main-project state.
    // Even with it set, send_webhook.php:378 writes the -47 dedup row only when
    // wp_remote_post did not error, and the boundary stub
    // (env/mu-plugins/00-e2e-boundary-stub.php:64) returns WP_Error for every URL
    // outside its three-entry fixture list. It also needs an approved report
    // (estatus='C') with a non-cash ci_insurances row, which no BIL fixture
    // creates and no BIL action can produce.
  });
});

/**
 * S-BIL-07 -- READ-SIDE SCOPING, FROM TWO SEATS AT ONCE.
 *
 * The claim under test is a TENANCY claim, in the same family as the
 * cross-tenant `lab_id` assertions the suite already pins, except the axis here
 * is the CLINIC and not the lab:
 *
 *   - the bill-by-clinic seat sees the specimens of the clinics assigned to it,
 *     and nothing else;
 *   - the general Billing seat sees everything EXCEPT exactly those clinics.
 *
 * Both halves are read out of `ci_load_specimen_billings.php:90-117` and its
 * two copies (`admin_ci_billing_list.php:31-66` for the day list, `:220-260`
 * for the CSV export). `BillbyClinic` gets ` and ba.cli_ID in (<_bill_cli_IDs>)`;
 * `Billing` gets ` and ba.cli_ID not in (<option ci_bill_cli_IDs>)`. The two
 * predicates are complements of one another, which is what makes the two seats'
 * expected row sets PROVABLY DISJOINT rather than merely different.
 *
 * HOW THIS FAILS IF SCOPING IS REMOVED -- the question a scoping test has to
 * answer. Delete the `BillbyClinic` branch and that seat sees both specimens,
 * so its "does not see the other clinic" assertion goes red. Delete the
 * `Billing` branch and that seat sees both, so ITS exclusion assertion goes
 * red. Delete both and both go red. Neither seat's negative assertion can pass
 * on an empty screen either: the POSITIVE assertion for the same seat runs
 * FIRST and fails if the worklist rendered nothing at all.
 *
 * THE FIXTURE GAPS THE OLD FIXME LISTED, AND WHAT CLOSED THEM
 *   1. "no `Billing` / `BillbyClinic` persona exists" -- closed, see the header.
 *   2. "one clinic exists, so the exclusion is unobservable" -- closed. There
 *      are 18 seeded clinics. This test uses TWO: `E2E Clinic <prefix>`, the
 *      clinic wave 0.2 created for THIS spec's own pool lab and which no other
 *      spec routes a requisition to, and `E2E Test Clinic Two` (D204).
 *   3. "the worklist needs two approved reports" -- closed by driving the app's
 *      own review queue: save two requisitions carrying one shared `batch_id`
 *      and `batch_status='resulted'`, render the queue once (which is what
 *      inserts the draft `ci_reports` rows, `ci_load_results.php:415`), then
 *      fire the queue's own Approve All. No row is hand-inserted (D085/D099).
 *
 * AND THE TRAP THE OLD FIXME WARNED ABOUT: the exclusion list the `Billing`
 * seat reads is NOT the bill-by-clinic user's meta. It is the NETWORK OPTION
 * `ci_bill_cli_IDs`, which `ci-billing-indep/inc/admin.php:161` rebuilds from
 * every BillbyClinic user's `_bill_cli_IDs`. The precondition below therefore
 * calls the app's OWN rebuild function rather than hand-writing the option, and
 * asserts BOTH values back before it goes on.
 *
 * Measured while fault-injecting that trap, and worth writing down because it
 * makes the option UNDESYNCHRONISABLE on this build: `wp_options.TRCDBI_V` is
 * `2.7.90` while the plugin declares `2.8.16` (plug.php:15), so
 * `admin_ci_billings.php:10-13` re-runs `trcdbi_plugin_activate()` on EVERY
 * render of the billing screen, and `inc/install.php:469` rebuilds the option
 * from the meta again. Writing `ci_bill_cli_IDs` to `[]` by hand and then
 * loading the page puts it straight back. The explicit rebuild is kept anyway:
 * it is the app's own derivation, and it does not depend on that version drift
 * still being there tomorrow.
 *
 * WHY THE PRECONDITION IS SPEC-LOCAL AND NOT IN `serial.spec.ts` (D150/D170).
 * `ci_bill_cli_IDs` is a network option, but it is read on exactly one code
 * path: the `$current_role == 'Billing'` branch, in the six files above plus
 * two insurance-verification reports. `Billing` and `BillbyClinic` are held by
 * `e2e_billing` and `e2e_billclin`, which no other spec names (grepped), and
 * the only clinic it ever names is this spec's own. The write is also
 * CONVERGENT -- it always sets the same value -- so a second worker running the
 * same test cannot observe a different one.
 */
const BILLING = 'e2e_billing';
const BILLING_PAGE = '/wp-admin/admin.php?page=ci_billings';

/**
 * One specimen's worth of rendered worklist, from ONE seat.
 *
 * MODULE-SCOPED because it has two callers: S-BIL-07 below drives it from two
 * seats to prove clinic scoping, and S-BIL-06b drives it from the `Billing`
 * seat to reach the bill-status control. It was spec-local while there was one
 * caller and moved on the second, not before.
 *
 * D386 -- A DOCUMENTED DEVIATION, NOT A CLEAN CASE. READ THIS BEFORE COPYING
 * THIS SHAPE ONTO ANOTHER SCREEN. D386 requires a rendered read-back to come
 * from `page.reload()` or a fresh `goto`. Its callers have neither: the specimen
 * rows they assert on are POST-XHR DOM, injected by the day-row click. The
 * deviation is deliberate and it is bounded by two facts:
 *
 *   1. There is NO user-reachable GET that renders a specimen row. The rows
 *      exist only inside the `ci_load_specimen_billings` fragment, which the
 *      expander fetches; reloading the page gives the day list back, not the
 *      specimens. So the alternative to this shape is not a safer assertion,
 *      it is no assertion.
 *   2. What the expander injects is a SERVER render of the scoping query --
 *      `admin_ci_billing_list.php:890-905` posts
 *      `tr_action=ci_load_specimen_billings` back to the CURRENT url, carrying
 *      the `?s=` filter with it, and replaces the row with the server's answer.
 *      Nothing in the injected HTML was typed by this test, which is what D386
 *      exists to stop. Confirmed by fault injection: deleting either scoping
 *      branch in the plugin turns these assertions red.
 *
 * Every WRITE the callers make BEFORE they assert (requisitions, approvals, the
 * clinic assignment) happens over HTTP with the browser shut, and every read
 * starts from a fresh `goto` through this helper -- including S-BIL-06b's
 * read-back after it has changed a status on this very screen, which is why
 * that test calls this twice rather than re-reading the row it just clicked.
 * A screen where a reload DOES re-render the asserted rows must use the
 * reload -- see tst.spec.ts S-TST-05u.
 *
 * The `?s=` filter is not decoration either: the billing worklist is a shared
 * surface that every worker's specimens land on, so an UNFILTERED row count
 * here would be a cross-spec race. `s` is applied to the day query
 * (`admin_ci_billing_list.php:164`) and to the fragment
 * (`ci_load_specimen_billings.php:38`) alike.
 */
async function openWorklist(page: Page, seat: string, searchKey: string, day: string) {
  await page.goto(`${BILLING_PAGE}&s=${searchKey}`);
  // `tr.parent` is `<td>`-driven: the handler returns early unless the click
  // landed on a TD/TR/I (admin_ci_billing_list.php:891), so the day CELL is
  // the control, and it is the only cell on the page bearing that date.
  const dayCell = page.getByRole('cell', { name: day, exact: true });
  // Asserted BEFORE the click, and this is not belt-and-braces. A seat whose
  // scoping hides every specimen renders `Results not found.` and NO day row
  // at all (admin_ci_billing_list.php:668), and a bare click on an absent
  // locator then burns the whole 60 s test timeout and reports
  // `Target page, context or browser has been closed` -- measured, during the
  // fault-injection pass that excluded BOTH clinics from the Billing seat.
  // The bounded expect turns that into a 5 s red with the real reason.
  await expect(
    dayCell,
    `${seat} opened the billing worklist filtered to ${searchKey} and its day list has no `
      + `${day} row -- that seat can see none of this test's specimens at all`,
  ).toBeVisible();
  await dayCell.click();
}

test.describe('S-BIL-07 @bil', () => {
  /** The seat this test pairs with `BILLING`. Minted; see the header. */
  const BILLCLIN = 'e2e_billclin';

  test('the bill-by-clinic seat sees only its assigned clinic, and the Billing seat sees exactly the rest', async ({
    pageAs, tenantRequest, seedRequisition, db, tenant, runKey,
  }, testInfo) => {
    // Two clinics, both seeded, and named rather than hardcoded by id.
    // `E2E Clinic <prefix>` is wave 0.2's per-pool-lab clinic: bil owns LAG
    // outright (SPEC_LAB), so no sibling spec can route a specimen through it
    // and no sibling spec can be affected by its being excluded.
    const assignedName = `E2E Clinic ${tenant.prefix}`;
    const otherName = 'E2E Test Clinic Two';
    const [[assignedId, otherId]] = db.query(
      `SELECT (SELECT cID FROM ci_clinic WHERE cli_clinic = ${Db.q(assignedName)}),
              (SELECT cID FROM ci_clinic WHERE cli_clinic = ${Db.q(otherName)})`,
    );
    expect(assignedId, `seed.php clinic ${assignedName} is missing`).not.toBe('');
    expect(otherId, `seed.php clinic ${otherName} is missing (D204)`).not.toBe('');
    expect(otherId, 'the two clinics must be different or the scoping is unobservable')
      .not.toBe(assignedId);

    // ---------------------------------------------------------- precondition
    // Assign ONE clinic to the bill-by-clinic seat and let the app rebuild the
    // network exclusion list from it. `inc/admin.php` is included only under
    // `is_admin()` (ci-billing-indep/admin/init.php:45), which wp-cli is not,
    // so the file is included explicitly; the function is still the APP's, so a
    // change to how the exclusion list is derived moves this fixture with it
    // instead of leaving it silently stale.
    const scoping = wpEval(
      'include_once(WP_PLUGIN_DIR . "/ci-billing-indep/inc/admin.php");'
      + '$u = get_user_by("login", "e2e_billclin");'
      + `update_user_meta($u->ID, "_bill_cli_IDs", array(${Number(assignedId)}));`
      + 'trcdbi_update_exclude_billingcid();'
      + '$s = get_option("tr_billing_ind_st", array());'
      + '$o = ["meta" => get_user_meta($u->ID, "_bill_cli_IDs", true),'
      + '      "option" => get_option("ci_bill_cli_IDs"),'
      + '      "disabled" => !empty($s["disable_billbyclinic"])];',
    );
    // The product switch that turns the whole carve-out off:
    // `ci_load_specimen_billings.php:112` reads `$current_role == 'Billing' &&
    // !$settings['disable_billbyclinic']`. With it on, the Billing seat stops
    // excluding anything and the second half of this test is meaningless -- so
    // it is a precondition, not an assumption.
    expect(
      scoping.disabled,
      'tr_billing_ind_st.disable_billbyclinic is ON, so the Billing seat excludes nothing and '
        + 'the bill-by-clinic carve-out this test asserts is switched off at the product level',
    ).toBe(false);
    expect(
      scoping.meta,
      `${BILLCLIN}._bill_cli_IDs -- the seat is assigned exactly clinic ${assignedId}`,
    ).toEqual([Number(assignedId)]);
    expect(
      scoping.option,
      'ci_bill_cli_IDs -- the network exclusion list the Billing seat reads. If this is [] '
        + 'the Billing seat excludes NOTHING and the disjointness below would be fixture noise, '
        + 'not scoping (see the trap in the block comment)',
    ).toEqual([Number(assignedId)]);

    // ------------------------------------------------------------ specimens
    // Two specimens that differ in EXACTLY ONE THING: the clinic. Same lab,
    // same provider, same collector, same batch, same approval, same day. That
    // is what makes the split below attributable to clinic scoping and to
    // nothing else.
    const keyA = `Bilscope${runKey}A`;
    const keyB = `Bilscope${runKey}B`;
    const searchKey = `Bilscope${runKey}`;
    // int(10), from the suite's shared allocator (D243/D396). The worker slot
    // separates two workers and the `bil` offset separates this file from the
    // four other specs that mint batch ids -- which the slot alone does not,
    // and which is why the offset table lives in one place rather than being
    // open-coded here. See fixtures/batch-id.ts.
    const batchId = nextBatchId();

    // `batch_status='resulted'` is what makes ci_load_results.php:92 admit a
    // batch with no report row yet, and it is NOT a rejected code, so neither
    // the saver's auto-approve branch nor the billing worklist's
    // `tr_pdd_where_batch_rejected` exclusion fires.
    for (const [key, cliId] of [[keyA, assignedId], [keyB, otherId]] as const) {
      const seeded = await seedRequisition(key, {
        'bat[cli_ID]': cliId,
        'bat[batch_id]': String(batchId),
        'bat[batch_status]': 'resulted',
      });
      expect(seeded.status, `the saver answers 200 for ${key}`).toBe(200);
      expect(seeded.keyRowsAfter - seeded.keyRowsBefore, `exactly one ci_batches row for ${key}`)
        .toBe(1);
    }

    // Rendering the queue is what INSERTS the draft reports
    // (ci_load_results.php:415). One render covers both, because both carry the
    // same batch_id.
    const queue = await tenantRequest.post('/wp-admin/admin.php?page=tox_results', {
      form: { tr_action: 'ci_load_results', batch_id: String(batchId), lab_id: tenant.labId, ref_lab_id: '0' },
    });
    expect(queue.status(), 'the tox review queue fragment renders').toBe(200);

    // The queue's own Approve All (admin_actions.php:1850), scoped to this
    // lab + batch so it can touch nothing else. Fired as the medical director,
    // which is the seat that signs a report off (personas.md:99 -> administrator).
    const director = await playwrightRequest.newContext({
      baseURL: (testInfo.project.use as { baseURL?: string }).baseURL ?? 'http://localhost',
      storageState: path.join(STATE_DIR, 'e2e_dir.json'),
    });
    const approved = await director.post('/wp-admin/admin.php?page=tox_results', {
      form: { tr_action: 'ci_results_approve_all', lab_id: tenant.labId, batch_id: String(batchId), ids: '' },
    });
    // The `estatus === 'C'` assertions below are what actually prove the approve
    // landed (D185: a 200 proves nothing on this app). This is here so that a
    // transport-level failure reports itself instead of surfacing as a confusing
    // "expected C, got ''" two screens later.
    expect(approved.status(), 'the queue Approve All request reached the app').toBe(200);
    await director.dispose();

    // ---------------------------------------------------- the DB row set
    // One SELECT, not six (D164). Ordered by patient_name so A is row 0.
    const rows = db.query(
      `SELECT ba.patient_name, ba.assen, ba.cli_ID, ba.lab_id, re.estatus, DATE(re.approve_date)
         FROM ci_batches ba
         JOIN ci_reports re ON re.patient_id = ba.assen
        WHERE ba.patient_name IN (${Db.q(`${keyA}, Synthetic`)}, ${Db.q(`${keyB}, Synthetic`)})
        ORDER BY ba.patient_name`,
    );
    expect(rows.length, `expected exactly two approved specimens for ${searchKey}`).toBe(2);
    const [rowA, rowB] = rows;
    const [, assenA, cliA, labA, statusA, dayA] = rowA;
    const [, assenB, cliB, labB, statusB, dayB] = rowB;

    expect(cliA, `${assenA} is routed to the ASSIGNED clinic ${assignedId}`).toBe(assignedId);
    expect(cliB, `${assenB} is routed to the OTHER clinic ${otherId}`).toBe(otherId);
    expect(statusA, `ci_reports.estatus for ${assenA} -- the worklist only admits C/F`).toBe('C');
    expect(statusB, `ci_reports.estatus for ${assenB} -- the worklist only admits C/F`).toBe('C');
    // Tenancy of the underlying rows (D172): both specimens belong to THIS
    // spec's lab, so neither seat's view can be explained by lab routing.
    expect(labA, `ci_batches.lab_id for ${assenA}`).toBe(tenant.labId);
    expect(labB, `ci_batches.lab_id for ${assenB}`).toBe(tenant.labId);
    // The worklist groups by approval DAY and the fragment is fetched per day,
    // so two days would mean two expanders and a half-empty screen.
    expect(dayB, 'both specimens were approved on the same day').toBe(dayA);
    expect(dayA, 'approve_date is stamped').not.toBe('');

    // ANTI-VACUITY GUARD, not a product assertion -- the same shape as
    // S-BIL-06's "the ledger starts with an empty bill_status" above. It cannot
    // be broken by a product change; it can only be broken by a fixture change,
    // and that is the point.
    //
    // The billing worklist applies `ba.lab_id = $current_lab_id` only when
    // `$blog_id > 1` (admin_ci_billing_list.php:74,
    // ci_load_specimen_billings.php:119). This is blog 1, so the billing seats
    // read ACROSS labs and are scoped by CLINIC alone. The guard states that
    // the two seats are bound to a DIFFERENT lab from the specimens, so a green
    // result below cannot be explained away as lab routing: whatever splits the
    // two seats' screens, it is not the lab. If seed.php ever binds the billing
    // personas to the pool labs, this fails and the split has to be re-argued.
    const seatLab = db.scalar(
      `SELECT meta_value FROM wp_usermeta m JOIN wp_users u ON u.ID = m.user_id
        WHERE u.user_login = ${Db.q(BILLING)} AND m.meta_key = '_lab_id'`,
    );
    // Read the value BEFORE comparing it. `Db.scalar` answers '' both for a
    // missing row and for SQL NULL (db.ts:19,23-26), and '' is `not.toBe(labId)`
    // for every labId there is -- so without this line the guard passes exactly
    // when the fixture it guards has gone missing, which is the one case it was
    // written for.
    expect(
      Number(seatLab),
      `${BILLING}._lab_id is '${seatLab}' -- the seat has no lab binding at all, so the guard `
        + 'below cannot tell a clinic split from a lab split',
    ).toBeGreaterThan(0);
    expect(
      seatLab,
      `the billing seats are bound to lab ${seatLab} and the specimens to lab ${tenant.labId}; `
        + 'if those are ever the same, the clinic split asserted below could equally be a lab '
        + 'split and this test stops proving clinic scoping',
    ).not.toBe(tenant.labId);

    // ------------------------------------------------ what each seat SEES
    // Seat 1: bill-by-clinic. POSITIVE FIRST -- the "does not see" assertion
    // below would pass against a worklist that rendered nothing at all, so the
    // row it IS entitled to has to be on screen before absence means anything.
    const clinPage = await pageAs(BILLCLIN);
    await openWorklist(clinPage, BILLCLIN, searchKey, dayA);
    await expect(
      clinPage.getByRole('cell', { name: assenA, exact: true }),
      `${BILLCLIN} is assigned ${assignedName} and must see ${assenA} on its worklist`,
    ).toBeVisible();
    await expect(
      clinPage.getByRole('cell', { name: assignedName, exact: true }),
      `the row ${BILLCLIN} sees names its own clinic, ${assignedName}`,
    ).toBeVisible();
    await expect(
      clinPage.getByRole('cell', { name: assenB, exact: true }),
      `${assenB} belongs to ${otherName}, which ${BILLCLIN} is NOT assigned -- it must not `
        + 'appear on that seat\'s worklist',
    ).toHaveCount(0);
    await expect(
      clinPage.getByRole('cell', { name: otherName, exact: true }),
      `${otherName} must not appear anywhere on the ${BILLCLIN} worklist`,
    ).toHaveCount(0);

    // Seat 2: the general biller, whose view is the exact complement.
    const billPage = await pageAs(BILLING);
    await openWorklist(billPage, BILLING, searchKey, dayA);
    await expect(
      billPage.getByRole('cell', { name: assenB, exact: true }),
      `${BILLING} bills every clinic except the assigned ones, so it must see ${assenB}`,
    ).toBeVisible();
    await expect(
      billPage.getByRole('cell', { name: otherName, exact: true }),
      `the row ${BILLING} sees names ${otherName}`,
    ).toBeVisible();
    await expect(
      billPage.getByRole('cell', { name: assenA, exact: true }),
      `${assenA} belongs to ${assignedName}, which is carved out to ${BILLCLIN} via `
        + 'ci_bill_cli_IDs -- the general Billing seat must NOT see it',
    ).toHaveCount(0);
    await expect(
      billPage.getByRole('cell', { name: assignedName, exact: true }),
      `${assignedName} is excluded from the ${BILLING} worklist, name and all`,
    ).toHaveCount(0);

    console.log(
      `S-BIL-07 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
      + `assigned=${assignedName}(${assignedId})->${assenA} other=${otherName}(${otherId})->${assenB} day=${dayA}`,
    );
  });
});

/**
 * S-BIL-06b -- THE FIRST TIME A SPECIMEN IS EVER BILLED.
 *
 * D382: this ADDS to S-BIL-06 above, which drives the same action and does NOT
 * cover the same code. S-BIL-06 saves the requisition carrying `bb[...]`, so
 * `trcdbi_saved_req()` (ci-billing-indep/inc/admin.php:181-207) has already
 * minted the ledger row by the time the status action runs, and the action
 * takes its UPDATE branch. Every other billing test in this file starts from an
 * existing row too. So the INSERT branch -- the one that runs for every
 * specimen the laboratory has never billed before -- is executed by nothing.
 *
 * `ci-billing-indep/inc/admin_actions.php:9-46`:
 *
 *     $exist_id = $wpdb->get_var("select id from ci_batch_billings where {$where_exist}");
 *     if ($exist_id > 0) { $wpdb->update(...array('id' => $exist_id)); }
 *     else               { $wpdb->insert('ci_batch_billings', $insert_data); }
 *     echo 'Changed Status Successfully';
 *
 * Note where the echo sits: OUTSIDE the branch, after both arms, with nothing
 * between it and them that looks at what either arm returned. `Changed Status
 * Successfully` is printed whether a row moved or not -- failure mode 1, and
 * the reason neither the response text nor the client's `alert()` can stand in
 * for the assertions below.
 *
 * WHAT A BROKEN INSERT BRANCH COSTS. A specimen that acquires no ledger row is
 * not merely mislabelled: `ci_load_specimen_billings.php:269` LEFT JOINs the
 * ledger, so the specimen keeps rendering with an empty status, and the
 * worklist's own `bill_status` filter (`:163-165`, `and bb.bill_status = '...'`)
 * is an INNER predicate on that join -- so it is absent from every filtered
 * view a biller works from. The laboratory performed the test and never billed
 * for it, and the screen the biller uses to find unbilled work is exactly where
 * it does not appear.
 *
 * THE SEAT AND THE SCREEN. `Billing`, on the specimen worklist, which is the
 * only place this control exists: `admin_ci_billing_list.php:780-800` builds a
 * `<select>` into every `td.bill_status` from the row's own `data-status` and
 * posts `ci_change_batch_bill_status` on change. S-BIL-06 posts that form by
 * hand as the lab persona; this test picks the option.
 *
 * THE CLINIC IS `E2E Test Clinic Two`, deliberately. S-BIL-07 assigns
 * `E2E Clinic <prefix>` to the bill-by-clinic seat and rebuilds the network
 * exclusion list from it, so routing here would make this test's visibility
 * depend on whether that one has run yet. `Two` is in neither seat's
 * assignment, so the `Billing` seat sees it under both orderings.
 */
test.describe('S-BIL-06b @bil', () => {
  test('a specimen that has never been billed gets its ledger row the first time a biller sets a status', async ({
    seedRequisition, tenantRequest, pageAs, db, tenant, runKey,
  }, testInfo) => {
    const f = infra(db);
    const patientLast = `Bilfirst${runKey}`;
    const batchId = nextBatchId();
    const TOX_RESULTS = '/wp-admin/admin.php?page=tox_results';

    // ------------------------------------------------- an unbilled specimen
    // NO `bb[...]` IN THIS POST, and that single omission is the whole scenario:
    // `trcdbi_saved_req()` is gated on `isset($_POST['bb'])`, so the requisition
    // saver mints nothing and the specimen reaches the biller with no ledger row
    // behind it -- which is what a specimen ordered on any screen that does not
    // carry the billing block looks like.
    const seeded = await seedRequisition(patientLast, {
      'bat[cli_ID]': f.clinic2Id,
      'bat[doc_ID]': f.doc2Id,
      'bat[batch_id]': String(batchId),
      // `resulted` is what makes ci_load_results.php:92 admit a batch with no
      // report row yet, and it is not a rejected code, so the worklist's
      // `tr_pdd_where_batch_rejected` exclusion does not fire either.
      'bat[batch_status]': 'resulted',
    });
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, {
      testGroupId: f.testGroupId,
      batchStatus: 'resulted',
    });

    // The worklist only admits reports at estatus C or F
    // (`ci_load_specimen_billings.php:198`), so the specimen has to be resulted
    // and released before a biller can reach it at all. Both steps are the app's
    // own: rendering the review queue is what INSERTS the draft report
    // (`ci_load_results.php:415` -- a GET that writes, D034), and Approve is what
    // releases it.
    const queue = await tenantRequest.post(TOX_RESULTS, {
      form: { tr_action: 'ci_load_results', batch_id: String(batchId), lab_id: tenant.labId, ref_lab_id: '0' },
    });
    expect(queue.status(), 'the tox review queue fragment renders').toBe(200);
    const draft = db.query(`SELECT id, estatus FROM ci_reports WHERE patient_id = ${Db.q(assen)}`);
    expect(draft.length, `the queue render created exactly one ci_reports row for ${assen}`).toBe(1);
    const reportId = draft[0][0];
    const approve = await tenantRequest.get(
      `${TOX_RESULTS}&tr_action=ci_results_approve&id=${reportId}`, { maxRedirects: 0 },
    );
    expect(approve.status(), 'the approve action redirects back to the queue').toBe(302);
    const [[estatus, day]] = db.query(
      `SELECT estatus, DATE(approve_date) FROM ci_reports WHERE id = ${Db.q(reportId)}`,
    );
    expect(estatus, `ci_reports.estatus for ${assen} -- the worklist only admits C/F`).toBe('C');
    expect(day, 'approve_date is stamped, so the worklist has a day row to group under').not.toBe('');

    // ---------------------------------------------------------- PRECONDITION
    // The claim is about the FIRST time, so "there is no row" has to be a fact
    // and not an assumption. Under the fault this test exists to catch, this
    // still reads 0 -- it is not a precondition that can absorb the failure.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_batch_billings WHERE assen = ${Db.q(assen)}`),
      `${assen} already has a ci_batch_billings row before any biller has touched it, so `
      + 'this test would exercise the UPDATE branch S-BIL-06 already covers',
    ).toBe('0');

    // ------------------------------------------------- the biller's own move
    const page = await pageAs(BILLING);
    await openWorklist(page, BILLING, assen, day);
    // `tr.parent_c` is the SPECIMEN row (`ci_load_specimen_billings.php:373`).
    // The class is load-bearing rather than cosmetic: the fragment is a table
    // nested inside a `<td>` of the day row, so a bare `getByRole('row')` filter
    // resolves TWO -- the day row contains the accession cell as a descendant
    // (measured: 2 elements). `data-id` on this row is not used to address it:
    // `data-btype` is in `testIdAttribute`, and a literal `getByTestId` is a
    // blocking CI failure.
    const row = page.locator('tr.parent_c').filter({
      has: page.getByRole('cell', { name: assen, exact: true }),
    });
    await expect(
      row,
      `${BILLING} opened the specimen worklist and ${assen} is not on it -- an approved `
      + 'specimen with no ledger row must still be visible, or it can never be billed',
    ).toHaveCount(1);

    // The control is a plain <select>; it is the only combobox in the row
    // (`ci_load_specimen_billings.php` renders no other), and it reads empty --
    // `Select` -- because the LEFT JOIN found no ledger row to take a status
    // from. That empty value is the second half of the precondition and it is
    // read off the SCREEN, where the biller reads it.
    const status = row.getByRole('combobox');
    await expect(
      status,
      `the bill status ${BILLING} sees for ${assen} before touching it`,
    ).toHaveValue('');

    // Pick the option, and wait for the app's own round trip rather than for a
    // label: `admin_ci_billing_list.php:788-806` posts on `change` and then
    // `alert()`s whatever came back, so there is no DOM change to wait on and
    // the alert is dismissed by Playwright before anything can read it.
    const posted = page.waitForResponse(
      (r) => r.url().includes('admin-ajax.php') && r.request().method() === 'POST',
    );
    await status.selectOption('Need Billing');
    const res = await posted;
    expect(res.status(), 'the worklist status control reached the app').toBe(200);
    // Asserted so that a transport failure reports itself here instead of
    // surfacing as a confusing empty row two assertions later. NOT evidence
    // that anything was written -- see the echo's position in the docblock.
    expect(
      await res.text(),
      'the action echoes its own confirmation, which it prints for a no-op too',
    ).toContain('Changed Status Successfully');

    // ------------------------------------------------------------- THE ROW
    const ledger = db.query(
      `SELECT bb.id, bb.assen, bb.bill_status, ba.lab_id
         FROM ci_batch_billings bb
         JOIN ci_batches ba ON ba.assen = bb.assen
        WHERE bb.assen = ${Db.q(assen)}`,
    );
    expect(
      ledger.length,
      `${assen} had never been billed and a biller has just marked it Need Billing. The app `
      + 'answered "Changed Status Successfully" and ci_batch_billings still holds no row for '
      + 'it: the INSERT branch at ci-billing-indep/inc/admin_actions.php:29-35 did not run, so '
      + 'the specimen is absent from every bill_status-filtered worklist and the laboratory '
      + 'will never bill for a test it has already performed',
    ).toBe(1);
    expect(
      ledger[0][2],
      `ci_batch_billings.bill_status for ${assen} -- the status the biller chose`,
    ).toBe('Need Billing');
    // TENANCY (D172). The ledger carries no lab column, so it is asserted
    // through the batch its `assen` joins to -- and the join succeeding at all
    // is what proves the INSERT carried the accession rather than an empty one.
    expect(
      ledger[0][3],
      `the ledger row for ${assen} hangs off lab ${ledger[0][3]}, tenant is ${tenant.labId}`,
    ).toBe(tenant.labId);

    // ---------------------------------------------------- WHAT THE BILLER SEES
    // D386 -- a FRESH SESSION, not the row that was just clicked. The select the
    // biller changed keeps the value it was given client-side whatever the
    // server did with it, so re-reading it in place would assert this test's own
    // click. Re-rendered from the server, the option carries `selected` only if
    // `data-status` came back holding it (`admin_ci_billing_list.php:783`), and
    // `data-status` is `ci_load_specimen_billings.php:389` echoing the joined
    // ledger row.
    //
    // AND IT HAS TO BE A NEW CONTEXT, not a second `goto` on the same page.
    // Expanding a day row writes the COOKIE `active_billing`
    // (`admin_ci_billing_list.php:900`), and `:966-978` re-expands from that
    // cookie on the next load -- so a re-`goto` in the same session arrives with
    // the day ALREADY open and `data('showed')` already `'1'`, and
    // `openWorklist`'s click then TOGGLES IT SHUT. Measured: the day row is
    // present and `tr.parent_c` resolves to 0 elements, i.e. the read-back reds
    // with the product working. `fixtures/test.ts` calls this cookie out as the
    // reason contexts are disposed per test; it bites within one test too.
    const freshPage = await pageAs(BILLING);
    await openWorklist(freshPage, BILLING, assen, day);
    await expect(
      freshPage.locator('tr.parent_c')
        .filter({ has: freshPage.getByRole('cell', { name: assen, exact: true }) })
        .getByRole('combobox'),
      `${BILLING} came back to the worklist and ${assen} still reads as having no bill `
      + 'status -- the biller is shown their own work undone, with no error to explain it',
    ).toHaveValue('Need Billing');

    console.log(
      `S-BIL-06b worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) `
      + `mid=${mid} assen=${assen} day=${day} ledger=${ledger[0][0]}`,
    );
  });
});

/**
 * DWA-E22 -- THE HIDDEN BILLING APIs TAB STILL ACCEPTS SFTP CREDENTIALS.
 *
 * `pages/admin_ci_billings.php:84` is the whole guard:
 *
 *     if (!$settings['enable_api_sftp']
 *         || !in_array($current_role, array('administrator','Billing','BillbyClinic'))) {
 *         unset($tabs['apis']);
 *     }
 *
 * `$tabs` is consumed by exactly ONE thing -- the `foreach` at :113-140 that
 * echoes the `<a class="nav-tab">` strip. The screen itself is selected by the
 * separate `switch ($tab)` at :145, whose `case 'apis'` (:270-272) includes
 * `pages/admin_ci_apis.php` unconditionally; `$tab` comes straight out of
 * `$_REQUEST['tab']` at :104 with no membership test against `$tabs`. Removing
 * the link removes the link. This is D250 on a live credential surface.
 *
 * What that screen does with a POST (`pages/admin_ci_apis.php:11-25`):
 *
 *     if (isset($_POST['options'])) { update_option('tr_billing_hl7_sftp', $_POST['options']); ... }
 *
 * -- host, username, PASSWORD and results folder for the Genius HL7 SFTP
 * endpoint, stored in cleartext, with no capability check, no nonce
 * (`grep -n 'check_admin_referer\|wp_verify_nonce\|current_user_can'` over both
 * files returns nothing) and no second look at `enable_api_sftp`.
 *
 * MEASURED AT THE PIN, 2026-08-25, as `e2e_billing` with `enable_api_sftp='0'`:
 * the tab bar renders eight tabs and none of them is APIs; `&tab=apis` answers
 * 200 with 113,549 bytes carrying the Genius Settings form; a POST of the four
 * fields answers 200 with `Saved Settings Successfully` and
 * `get_option('tr_billing_hl7_sftp')` comes back holding all four values,
 * password included; and a FRESH GET of the same URL renders them back into the
 * form (D386 -- the read-back below is a server render, not post-XHR DOM).
 *
 * SHARED-STACK HYGIENE. `tr_billing_hl7_sftp` is absent on this build and is
 * read by NOTHING outside `admin_ci_apis.php` itself -- `grep -rn` over all
 * eight submodules returns three hits, all in that file. Its only would-be
 * consumer is the `cron_api_billing_sftp_hl7` hook, which S-BIL-05 above
 * measures as having no handler at all. The value written below is therefore
 * inert, and it is deleted in a `finally` before any assertion runs, so the
 * (expected) failure leaves nothing behind. `options[enable]` is never posted:
 * with it set, `admin_ci_apis.php:22` would schedule that handler-less cron.
 *
 * TWO DEFECTS, TWO PINS, and the split is the point rather than tidiness.
 * PIN A is about the SCREEN -- a URL serving a tab the app has just said does
 * not exist. PIN B is about the ENDPOINT -- an unauthenticated, unnonced
 * `update_option` of a cleartext credential. One test asserting both would only
 * ever run the first assertion (`expect` throws), so the second defect would be
 * carried by a title and by nothing else; and gating the include at
 * `admin_ci_billings.php:270` -- the one-line fix the mechanism above invites --
 * would retire that single pin while leaving `update_option` at
 * `admin_ci_apis.php:13` exactly as unguarded as it is today, for every seat the
 * tab IS offered to. PIN B therefore switches `enable_api_sftp` ON for its own
 * duration, so the screen is legitimately reachable and a nav-gate fix cannot
 * flip it: the only thing that can is a check on the POST.
 */
test.describe('DWA-E22 @bil', () => {
  // PIN B switches `tr_billing_ind_st.enable_api_sftp` ON for about a second and
  // the other two tests assert it is OFF, so under `fullyParallel` the three
  // would race each other onto two workers. D150/D170: this sequences WITHIN
  // the describe only -- which is the whole requirement here, because that key
  // is read by nothing else in the suite (grep over specs/, setup/ and env/:
  // this file only).
  //
  // `default` AND NOT `serial`, which was the mode here until the cost was
  // MEASURED rather than predicted. Both run these three on one worker in
  // declaration order; `serial` additionally SKIPS the rest of the block after
  // a failure. PIN A is red on every run until `case 'apis'` is gated -- not
  // "on the day PIN A flips" -- so `serial` took PIN B out of every run
  // permanently, and the DWA-E22 card's second marker had nothing reporting on
  // it. Order between the three is free: PIN B restores `enable_api_sftp` in a
  // `finally` and the other two only read it.
  test.describe.configure({ mode: 'default' });

  const BILLING = 'e2e_billing';
  const BILLING_TAB_BAR = '/wp-admin/admin.php?page=ci_billings';
  const APIS_SCREEN = '/wp-admin/admin.php?page=ci_billings&tab=apis';
  const OPTION = 'tr_billing_hl7_sftp';

  /**
   * The two facts :84 branches on, read through the app's OWN accessor
   * (`tr_billing_get_settings()`) rather than off the raw `tr_billing_ind_st`
   * option, so a change to how the setting is derived moves this with it.
   */
  const gateInputs = () => wpEval(
    '$s = tr_billing_get_settings();'
    + '$u = get_user_by("login", "e2e_billing");'
    + '$o = ["enable_api_sftp" => (string) $s["enable_api_sftp"],'
    + '      "roles" => array_values($u->roles)];',
  );

  test('the APIs tab is absent from the billing tab bar while the SFTP integration is off', async ({
    pageAs,
  }) => {
    const gate = gateInputs();
    // Both clauses of :84, so a red below names WHICH input moved. The role
    // clause ADMITS `Billing`, which is what makes `enable_api_sftp` the only
    // thing dropping the tab for this seat -- and therefore what makes PIN A's
    // "the URL still serves it" a statement about the setting.
    expect(
      gate.roles,
      'e2e_billing no longer holds the Billing role, so admin_ci_billings.php:84 now drops the '
        + 'APIs tab on its ROLE clause and this test stops being about enable_api_sftp',
    ).toEqual(['Billing']);
    expect(
      gate.enable_api_sftp,
      'tr_billing_ind_st.enable_api_sftp is switched ON, so the APIs tab is supposed to be '
        + 'offered and neither this test nor PIN A below is measuring anything',
    ).toBe('0');

    const page = await pageAs(BILLING);
    await page.goto(BILLING_TAB_BAR);

    // POSITIVE CONTROL FIRST. The absence below has to mean "this tab is not
    // offered", not "the tab bar did not render" or "the seat was denied the
    // screen" (D141/D388). Two tabs, so the bar is populated and not a stub.
    //
    // NOT `Specimen Billing`, which is the tab bar's first entry AND the
    // #adminmenu top-level label -- measured, it resolves 2 and a strict-mode
    // throw is not a readable failure (wave-5 review, 7.0 finding 7). Every
    // other tab name on this screen resolves exactly 1.
    await expect(
      page.getByRole('link', { name: 'Import', exact: true }),
      'the billing tab bar rendered for this seat, so a missing tab below is a missing tab',
    ).toBeVisible();
    await expect(
      page.getByRole('link', { name: 'Insurance Upload', exact: true }),
      'a second tab the seat IS offered, so the bar is populated and not a one-entry stub',
    ).toBeVisible();

    await expect(
      page.getByRole('link', { name: 'APIs', exact: true }),
      'the billing tab bar offers an APIs tab although tr_billing_ind_st.enable_api_sftp is 0 '
        + '-- the operator is being told the SFTP integration is available',
    ).toHaveCount(0);
  });

  /**
   * PIN A -- THE SCREEN. The assertion states what the app SHOULD do -- refuse
   * to serve a screen it has just told the operator does not exist -- and
   * `test.fail()` records that it does not. Playwright counts the failure as a
   * pass; the day someone gates `case 'apis'` this reports "Expected to fail,
   * but passed" and the run goes RED. PROVEN: gating the include at
   * admin_ci_billings.php:270 on `isset($tabs['apis'])` -- the whole fix, one
   * line -- turns this into an unexpected pass.
   *
   * NOTHING IS POSTED FROM HERE. The credential half of this defect is PIN B's,
   * measured through the endpoint rather than through this screen, so that a
   * fix to either one is reported on its own.
   */
  test('the hidden APIs screen is still served over its URL', async ({ pageAs }) => {

    const gate = gateInputs();

    const page = await pageAs(BILLING);
    await page.goto(APIS_SCREEN);

    // The screen the tab bar denies exists, identified by its own heading.
    const served = await page.getByRole('heading', { name: 'Genius Settings' }).count();

    expect(
      gate.enable_api_sftp,
      'tr_billing_ind_st.enable_api_sftp is ON, so this seat is entitled to the APIs screen '
        + 'and nothing below is a finding',
    ).toBe('0');

    expect(
      served,
      `${BILLING} opened ${APIS_SCREEN} directly. Its tab bar offers no APIs tab because `
        + 'enable_api_sftp is 0, yet the URL still renders the Genius Settings screen: '
        + 'admin_ci_billings.php:84 removes the nav entry and the switch at :270 includes the '
        + 'page anyway (DWA-E22)',
    ).toBe(0);
  });

  /**
   * PIN B -- THE ENDPOINT. What the app SHOULD do with a credential POST that
   * carries no nonce is refuse it; `test.fail()` records that it stores it.
   * Add `check_admin_referer()` (or any `current_user_can()`) ahead of
   * `admin_ci_apis.php:13` and this reports "Expected to fail, but passed".
   *
   * DELIBERATELY DECOUPLED FROM THE NAV GATE. `enable_api_sftp` is switched ON
   * for the duration, so this seat is legitimately entitled to the screen and
   * PIN A's one-line fix cannot flip this test. What is left is the CSRF shape
   * and nothing else: a session cookie, four form fields, no nonce, no Referer.
   *
   * A RAW POST RATHER THAN THE FORM, for the same reason. The rendered form
   * carries no nonce field to omit, so driving it in a browser would prove the
   * absence of a check the browser also happens to satisfy in other ways; the
   * request below satisfies none of them.
   *
   * NO POSITIVE CONTROL IS NEEDED INSIDE THIS BODY, and that is a property of
   * the assertion rather than an omission. Every way this test could go vacuous
   * -- the URL stops resolving, the option is renamed, the seat loses the screen
   * -- leaves the option ABSENT, which makes the assertion PASS and Playwright
   * report the unexpected pass. Vacuity here is RED, not silent green.
   */
  test('the Genius Settings endpoint stores a posted SFTP credential with no nonce', async ({
    runKey,
  }, testInfo) => {

    const secret = `e2e-${runKey}-not-a-real-secret`;
    let before: unknown = '<UNREAD>';
    let after: unknown = '<UNREAD>';

    // Read and switch in one round trip; the JSON round-trip restores the
    // original scalar with its original PHP type (measured: string "0"), and an
    // absent key restores to absent rather than to a literal 0.
    const wasJson = String(wpEval(
      '$s = get_option("tr_billing_ind_st", array());'
      + '$o = ["v" => json_encode(array_key_exists("enable_api_sftp", $s) ? $s["enable_api_sftp"] : null)];'
      + '$s["enable_api_sftp"] = 1; update_option("tr_billing_ind_st", $s);',
    ).v);

    try {
      before = wpEval(`$o = ["v" => get_option("${OPTION}", "<ABSENT>")];`).v;

      const ctx = await playwrightRequest.newContext({
        baseURL: (testInfo.project.use as { baseURL?: string }).baseURL ?? 'http://localhost',
        storageState: path.join(STATE_DIR, `${BILLING}.json`),
      });
      try {
        // Synthetic throughout. `.invalid` is reserved (RFC 2606), so no leg of
        // this can reach a real endpoint even if something later tries to
        // connect -- and `options[enable]` is absent, so nothing is scheduled.
        await ctx.post(APIS_SCREEN, {
          form: {
            'options[host]': `sftp.e2e-${runKey}.invalid`,
            'options[username]': `e2e-${runKey}-hl7`,
            'options[password]': secret,
            'options[results_folder]': `/e2e-${runKey}`,
          },
        });
      } finally {
        await ctx.dispose();
      }

      after = wpEval(`$o = ["v" => get_option("${OPTION}", "<ABSENT>")];`).v;
    } finally {
      // Restore FIRST, assert second (pcr.spec.ts:583 precedent). This test is
      // expected to fail, so anything after the assertion would never run.
      wpEval(
        `delete_option(${JSON.stringify(OPTION)});`
        + '$s = get_option("tr_billing_ind_st", array());'
        + `$v = json_decode(${JSON.stringify(wasJson)}, true);`
        + 'if ($v === null) { unset($s["enable_api_sftp"]); } else { $s["enable_api_sftp"] = $v; }'
        + 'update_option("tr_billing_ind_st", $s);'
        + '$o = ["restored" => true];',
      );
    }

    // Both ends in ONE assertion so that a leftover value from an earlier
    // interrupted run reports itself as a fixture problem instead of
    // masquerading as the defect -- and the `finally` above has already cleaned
    // it, so the next run starts honest either way.
    expect(
      [before, after],
      `a cookie-only POST carrying no nonce and no Referer wrote wp_options.${OPTION}: the `
        + 'handler at admin_ci_apis.php:13 has no capability check and no nonce check, so any '
        + 'page on the internet can make a signed-in billing operator\'s browser overwrite the '
        + 'laboratory\'s Genius HL7 SFTP host, username and cleartext password (DWA-E22)',
    ).toEqual(['<ABSENT>', '<ABSENT>']);
  });
});

/**
 * THE PATIENT EXPORT IS A PHI FIREHOSE ON A BARE URL.
 *
 * `?tr_action=billing_export_patients` streams one CSV row per released
 * specimen in the whole installation, and the columns are the ones a breach
 * notification is written about: `"Accession Number", "Result date", Clinic,
 * Doctor, SSN, "First name", "Last name", DOB, Insurance, "Member ID",
 * "Group #"` (inc/admin_actions.php:341-353).
 *
 * THREE GATES THAT ARE NOT THERE, all in `tradmin_action_billing_export_patients()`
 * (inc/admin_actions.php:248-379):
 *
 *  1. NO CAPABILITY CHECK. The function is bound by the tr_action dispatcher,
 *     which hooks `admin_init` for whatever `$_REQUEST['tr_action']` names and
 *     applies no per-action check of its own (D175, admin/init.php:55-64). Any
 *     seat that can load `/wp-admin/` at all reaches it.
 *  2. NO NONCE. A GET, with no `check_admin_referer()` anywhere in the function.
 *  3. NO LAB CLAUSE. `$where_ba` is built from the CALLER'S ROLE only --
 *     BillbyClinic, BillbyRefLab, office_manager and Billing each narrow it by
 *     clinic or ref-lab (`:257-283`) -- and no branch ever mentions `ba.lab_id`.
 *     A seat bound to one laboratory is handed every OTHER laboratory's
 *     patients on this multi-tenant install.
 *
 * THE APP KNOWS HOW TO DO THIS PROPERLY, in the very next function.
 * `tradmin_action_ci_export_data_batch()` (`:382-395`) opens with
 * `check_ajax_referer()`, a signed-in test and
 * `if (!$is_ci_admin && !current_user_can('ci_data_export')) { ... 'You do not
 * have permission to export patient testing data.' }`. So the export below is
 * not an old convention the codebase has yet to catch up with; it is the same
 * file, twenty lines apart, with the checks left out.
 *
 * WHY THE DEFECT HAS SURVIVED: the CONTROL is gated and the ENDPOINT is not.
 * `pages/admin_ci_billing_list.php:491` renders the `Export` link only when
 * `$user->user_login == 'administrat0r'` -- one hardcoded lowercase login -- so
 * nobody except that account has ever SEEN the link, and every other seat's
 * access is invisible until somebody types the URL. D250, on the widest PHI
 * surface in the plugin.
 *
 * THE SEAT THIS ASKS FROM. `e2e_collector` holds the `collector` role: no
 * billing capability, no billing role slug, and -- asserted below, on screen --
 * refused the Specimen Billing page outright. It is not a billing seat by any
 * reading, which is what makes the CSV it receives a statement about the
 * endpoint rather than about roles.
 *
 * KNOWN LIMIT of a `fail()` body (same one the DWA-E22 pins carry): every
 * precondition below is swallowed -- a broken seed, a failed release or a
 * changed denial page would fail the body and still be reported as the expected
 * failure. They are here to be READ when the pin flips. The PIN ITSELF has the
 * safe polarity: every way it could go vacuous -- endpoint renamed, seat locked
 * out, specimen never released -- leaves the SSN out of the response, which
 * makes the assertion PASS and Playwright report the unexpected pass. Vacuity
 * here is RED, not silent green.
 */
test.describe('patient export authorization @bil', () => {
  const EXPORT_URL = '/wp-admin/admin.php?tr_action=billing_export_patients';
  const BILLING_PAGE = '/wp-admin/admin.php?page=ci_billings';
  const OUTSIDER = 'e2e_collector';

  test('a seat with no billing entitlement cannot export the network\'s patients', async ({
    pageAs, seedRequisition, tenantRequest, db, tenant, runKey,
  }, testInfo) => {

    // ------------------------------------------------------ one released row
    // The export admits `estatus in ('C','F')` only (`:257`), so an unreleased
    // specimen would leave the CSV empty for reasons that have nothing to do
    // with authorization.
    const patientLast = `Bilexp${runKey}`;
    // THE NEEDLE. Obviously synthetic and unique per run -- a shared value would
    // let another run's leftovers answer for this one -- and CAPPED AT TWENTY
    // CHARACTERS, which is not cosmetic: `ci_patients.pa_ssn` is `varchar(20)`
    // under STRICT_TRANS_TABLES, so a longer value makes the saver's
    // `$wpdb->insert('ci_patients', $pa)` fail, `$patient_id` stay 0 and the
    // requisition save answer 200 with NO PATIENT ROW AT ALL (D100's class,
    // measured here: a 24-character needle produced ci_batches.patient_id = 0).
    // The export INNER-joins ci_patients, so that would have emptied the CSV
    // and turned this pin into a false green.
    const ssn = `900-${runKey}`.slice(0, 20);
    const batchId = nextBatchId();
    const seeded = await seedRequisition(patientLast, {
      'bat[batch_id]': String(batchId),
      'bat[batch_status]': 'resulted',
      'pa[pa_ssn]': ssn,
    });
    expect(seeded.status, 'the requisition saver answers 200').toBe(200);
    expect(seeded.keyRowsAfter - seeded.keyRowsBefore, `exactly one ci_batches row for ${patientLast}`)
      .toBe(1);

    // Read through a length check rather than destructured straight out of
    // db.query: a zero-row answer destructures to a TypeError, and a `fail()`
    // body reports a TypeError as the expected failure -- which is how a broken
    // fixture turns into a silent green (measured once already, see the needle
    // note above).
    const batchRows = db.query(
      `SELECT assen, lab_id FROM ci_batches
        WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
    );
    expect(batchRows.length, `exactly one ci_batches row for ${patientLast}`).toBe(1);
    const [assen, specimenLab] = batchRows[0] ?? ['<NO BATCH ROW>', '<NO BATCH ROW>'];
    expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing (D160)').not.toBe('');

    // The queue render is what INSERTS the report row (D034, ci_load_results.php:415).
    const queue = await tenantRequest.post('/wp-admin/admin.php?page=tox_results', {
      form: { tr_action: 'ci_load_results', batch_id: String(batchId), lab_id: tenant.labId, ref_lab_id: '0' },
    });
    expect(queue.status(), 'the tox review queue fragment renders').toBe(200);
    const reportId = db.scalar(`SELECT id FROM ci_reports WHERE patient_id = ${Db.q(assen)}`);
    expect(reportId, `the queue render created a report row for ${assen}`).not.toBe('');
    await tenantRequest.get(
      `/wp-admin/admin.php?page=tox_results&tr_action=ci_results_approve&id=${reportId}`,
      { maxRedirects: 0 },
    );

    // LEFT JOIN on purpose: an INNER join answers zero rows when the patient
    // link is missing, and a destructure of zero rows throws a TypeError that a
    // `fail()` body would report as the expected failure.
    const [estatus, batchPatientId, storedSsn] = db.query(
      `SELECT re.estatus, ba.patient_id, COALESCE(pa.pa_ssn, '<NO PATIENT ROW>')
         FROM ci_reports re
         JOIN ci_batches ba ON ba.assen = re.patient_id
         LEFT JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
        WHERE re.id = ${Db.q(reportId)}`,
    )[0] ?? ['<NO REPORT ROW>', '0', '<NO REPORT ROW>'];
    expect(
      [estatus, storedSsn],
      `[ci_reports.estatus, ci_patients.pa_ssn] for ${assen} -- the specimen has to be RELEASED `
        + "and carry the needle, or the export's own estatus filter, not authorization, is what "
        + 'keeps it out of the CSV',
    ).toEqual(['C', ssn]);
    expect(
      Number(batchPatientId),
      `ci_batches.patient_id for ${assen} is 0, so the requisition has no patient row behind it `
        + 'and the export\'s own `join ci_patients pa ON pa.pa_ID = ba.patient_id` would drop '
        + 'this specimen for a reason that has nothing to do with authorization',
    ).toBeGreaterThan(0);

    // ------------------------------------------------- CROSS-TENANCY, stated
    // Not a product assertion -- a guard, in the shape S-BIL-07 uses above. It
    // says the seat and the specimen belong to DIFFERENT laboratories, so a CSV
    // that contains this row cannot be explained as the seat reading its own
    // lab's data.
    const seatLab = db.scalar(
      `SELECT meta_value FROM wp_usermeta m JOIN wp_users u ON u.ID = m.user_id
        WHERE u.user_login = ${Db.q(OUTSIDER)} AND m.meta_key = '_lab_id'`,
    );
    expect(
      Number(seatLab),
      `${OUTSIDER}._lab_id is '${seatLab}' -- the seat has no lab binding at all, so this test `
        + 'cannot say the export crossed a tenancy boundary',
    ).toBeGreaterThan(0);
    expect(
      seatLab,
      `${OUTSIDER} is bound to lab ${seatLab} and the specimen to lab ${specimenLab}; if those `
        + 'are ever the same the CSV below stops being evidence of a missing lab clause',
    ).not.toBe(specimenLab);

    // ------------------------------------------- what this seat is ALLOWED to see
    // The positive control, and it is a control in the D141 sense: it proves
    // this seat is refused the billing surface through the front door, so the
    // CSV it receives through the side door is not something it was entitled to
    // anyway. Bounded by BEFORE_BOUNCE_MS -- the denied admin page carries a
    // three-second meta refresh to page=welcome (D392) and outlives an
    // unbounded poll.
    const fused = { timeout: BEFORE_BOUNCE_MS }; // D392 -- the denial is on a 3 s fuse.
    const outsiderPage = await pageAs(OUTSIDER);
    await outsiderPage.goto(BILLING_PAGE);
    await expect(
      outsiderPage.getByText(
        /Sorry, you are not allowed to access this page\.|You do not have permission to access this page\./,
      ),
      `${OUTSIDER} opened ${BILLING_PAGE} and was refused it: this seat is not a billing seat, `
        + 'which is the premise of everything below. Either wp_die wording counts -- pinning '
        + 'one would go green on a product change that swapped which layer refuses',
    ).toHaveCount(1, fused);
    await expect(
      // The tab strip, NOT the `Specimen Billing` heading: nothing in this suite ever shows
      // that heading resolving when the billing screen DOES render, so a 0 against it proves
      // nothing (D397). The control test at the foot of this file drives this exact locator
      // to a VISIBLE state for an entitled billing seat, so a 0 here is the screen withheld.
      outsiderPage.locator('h2.nav-tab-wrapper').getByRole('link', { name: 'Reports', exact: true }),
      'and no part of the billing screen rendered for it -- not even the tab bar that an '
        + 'entitled billing seat is served',
    ).toHaveCount(0, fused);

    // ------------------------------------------------------------- THE PIN
    // Read as a DOWNLOAD, not as a page: the handler `exit`s straight after
    // fputcsv, so there is no screen to assert on and the response body IS what
    // the operator receives (D185 -- the 200 proves nothing either way).
    const outsider = await playwrightRequest.newContext({
      baseURL: (testInfo.project.use as { baseURL?: string }).baseURL ?? 'http://localhost',
      storageState: path.join(STATE_DIR, `${OUTSIDER}.json`),
    });
    let csv = '';
    let contentType = '';
    try {
      const res = await outsider.get(EXPORT_URL);
      contentType = res.headers()['content-type'] ?? '';
      csv = await res.text();
    } finally {
      await outsider.dispose();
    }
    const header = csv.split('\n')[0].slice(0, 160);

    expect(
      csv,
      `${OUTSIDER} -- a collector, refused the billing screen one assertion ago -- typed `
        + `${EXPORT_URL} and was handed a ${contentType} download carrying the social security `
        + `number of a patient on lab ${specimenLab}, a laboratory it is not bound to `
        + `(seat lab ${seatLab}). Header served: ${header}. There is no capability check, no `
        + 'nonce and no lab clause in tradmin_action_billing_export_patients(); the only thing '
        + 'gating this data is that the Export link renders for one hardcoded login',
    ).not.toContain(ssn);
  });
});

/**
 * THE BILLING SETTINGS SCREEN IS NAV-GATED AND NOTHING ELSE. D250 again, on the
 * plugin's global configuration.
 *
 * `pages/admin_ci_billings.php:92` is the guard, and it is a guard on the MENU:
 *
 *     if (!in_array($current_role, array('administrator'))) { unset($tabs['settings']); }
 *
 * `$tabs` is consumed by exactly one thing, the `foreach` at `:116-140` that
 * echoes the tab strip. The screen is selected by the separate `switch ($tab)`
 * at `:144`, whose `case 'settings'` (`:249-251`) includes
 * `admin_ci_billing_settings.php` unconditionally; `$tab` comes out of
 * `$_REQUEST['tab']` at `:107` with no membership test against `$tabs`.
 *
 * WHAT THE SCREEN DOES, so that "renders" is not mistaken for "harmless":
 *
 *  - `admin_ci_billing_settings.php:32-56` takes `$_POST['options']`, merges it
 *    over `tr_billing_ind_st` and calls `update_option()` -- the network's
 *    billing configuration, including `disable_billbyclinic`, the switch that
 *    turns off the clinic carve-out S-BIL-07 asserts. No nonce, no capability
 *    check, and it will `ALTER TABLE ci_clinic` on the way through (`:47-49`).
 *  - `:6-30` truncates NINETEEN billing code tables on a bare
 *    `?reset_billing_codes` in the QUERY STRING. There is no rendered control
 *    for it -- measured, the string appears nowhere in the response -- so it is
 *    reachable only by typing it, by exactly the seats that were never meant to
 *    be on this screen.
 *
 * NEITHER TEST BELOW FIRES EITHER OF THOSE. This asserts the screen is SERVED,
 * which is the gate that is missing; the destructive halves stay unexercised on
 * a stack every other spec shares (D401 -- a `reset_billing_codes` pass belongs
 * in an isolated `--project=main --no-deps` run, not here). Measured at the pin
 * 2026-08-26: a `tab=settings` GET left `ci_bill_drug_classes` and
 * `ci_bill_bc_medicare` at their seeded row counts.
 *
 * THE SEAT. `e2e_billclin` holds `BillbyClinic`: a real billing seat, entitled
 * to the billing screen, and deliberately NOT one of the roles `:92` admits to
 * the configuration tab. That is what separates "this seat is not an
 * administrator" from "this seat is not a biller" -- the second is false, and
 * the screen is still not its to have.
 */
test.describe('billing Settings authorization @bil', () => {
  const BILLCLIN = 'e2e_billclin';
  const BILLING_PAGE = '/wp-admin/admin.php?page=ci_billings';
  const SETTINGS_SCREEN = '/wp-admin/admin.php?page=ci_billings&tab=settings';

  /**
   * THE CONTROL, as its own test rather than a preamble: it must go RED on its
   * own if the product ever admits this role to the tab, which is precisely the
   * change that would make the pin below stop being a defect.
   */
  test('the billing tab bar offers a bill-by-clinic seat no Settings tab', async ({ pageAs }) => {
    const page = await pageAs(BILLCLIN);
    await page.goto(BILLING_PAGE);
    // Scoped to the tab strip: `#adminmenu` carries labels of its own, and
    // `Specimen Billing` in particular resolves in both (DWA-E22's finding 7).
    const tabBar = page.locator('h2.nav-tab-wrapper');

    // POSITIVE FIRST. The absence below has to mean "this tab is not offered",
    // not "the bar did not draw" or "the seat was denied the screen" (D141/D388).
    await expect(
      tabBar.getByRole('link', { name: 'Reports', exact: true }),
      'the billing tab bar rendered for this seat, so a missing tab below is a missing tab',
    ).toBeVisible();
    await expect(
      tabBar.getByRole('link', { name: 'Insurance Reports', exact: true }),
      'a second tab the seat IS offered, so the bar is populated and not a one-entry stub',
    ).toBeVisible();

    await expect(
      tabBar.getByRole('link', { name: 'Settings', exact: true }),
      `${BILLCLIN} holds BillbyClinic, and admin_ci_billings.php:92 keeps the configuration tab `
        + 'to administrators. A Settings tab here means a billing clerk is being invited to '
        + "rewrite the network's billing configuration",
    ).toHaveCount(0);
  });

  /**
   * THE PIN. The assertion states what the app SHOULD do -- refuse a screen it
   * has just told this seat does not exist -- and `test.fail()` records that it
   * does not. Gating the include at `admin_ci_billings.php:249` on
   * `isset($tabs['settings'])` (the whole fix, one line, the same shape
   * DWA-E22's PIN A invites) turns this into "Expected to fail, but passed".
   *
   * VACUITY IS RED. Every way this could stop measuring anything -- the seat
   * losing its session, the heading being renamed, the URL ceasing to resolve --
   * drives both counts to 0, which is what the assertion expects, so Playwright
   * reports the unexpected pass instead of a quiet green.
   */
  test('the Settings screen is refused over its URL to the seat the tab bar denies', async ({
    pageAs,
  }) => {

    const page = await pageAs(BILLCLIN);
    await page.goto(SETTINGS_SCREEN);

    // Two facts, one assertion: the screen, and the control on it that writes.
    // Reported together because a fix that leaves either behind is not a fix.
    const heading = await page.getByRole('heading', { name: 'Settings', exact: true }).count();
    const save = await page.getByRole('button', { name: 'Save', exact: true }).count();

    expect(
      { heading, save },
      `${BILLCLIN} opened ${SETTINGS_SCREEN} directly. Its own tab bar offers no Settings tab `
        + '(asserted in the test above), yet the URL renders the billing configuration screen '
        + 'and its Save control -- a form whose POST handler merges straight into '
        + 'wp_options.tr_billing_ind_st with no nonce and no capability check, and whose page '
        + 'truncates nineteen billing-code tables on a bare ?reset_billing_codes in the query '
        + 'string. admin_ci_billings.php:92 removes the nav entry; :249 includes the page anyway',
    ).toEqual({ heading: 0, save: 0 });
  });
});
