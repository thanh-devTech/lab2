import { test, expect, request as playwrightRequest } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';
import * as path from 'node:path';
import { test as sharedTest, BEFORE_BOUNCE_MS } from '../fixtures/test';
import { seedBloodRequisition } from '../fixtures/seed-domain';
import { Db } from '../fixtures/db';
import { STATE_DIR } from '../playwright.config';

/**
 * COL -- collector / phlebotomist (personas.md 3.7), role slugs `collector` and
 * `Phlebotomist`.
 *
 * PERSONA, read from the RUNNING instance with get_role() (D178), never from
 * install_roles.php:
 *
 *   collector       8 caps: read, level_1, level_0, form_pcr, ci_test_results,
 *                   form_blood, ci_phlebotomist, ci_abx_report
 *   Phlebotomist    6 caps: read, level_1, level_0, form_blood,
 *                   ci_standing_orders, ci_phlebotomist
 *   leadlabsecman  15 caps, incl. form_blood and ci_phlebotomist, NOT
 *                   ci_standing_orders -- the contrast persona below
 *   lab_tech       20 caps, incl. ci_standing_orders -- the only MINTED
 *                   persona holding it
 *
 * CATALOG CORRECTIONS, all measured against this instance:
 *
 *  1. `personas.md:135` credits `collector` with form_requisition,
 *     form_lab_test_req, form_gene, ci_results_blood, ci_results_dna,
 *     ci_results_gene, ci_results_ref_tox_upload and ci_data_store. LIVE IT
 *     HOLDS NONE OF THEM, and it holds two the doc does not name
 *     (ci_test_results, ci_abx_report). Consequence for this flow: a collector
 *     is 200-DENIED on the tox Requisition screen. Eighth confirmation of D178.
 *  2. S-ORD-06 is written against btype `req`. `CI_ALL_BATCH_TYPES` is
 *     `tox, blood, pcr` here, so `ci_batches_req` and `ci_batches_req_meta`
 *     DO NOT EXIST (verified: SHOW TABLES LIKE 'ci_batches%' lists
 *     ci_batches{,_blood,_blood_meta,_meta,_notes,_pcr,_pcr_meta,_status,
 *     _study,_uploaded} and no `_req` family). The scenario is implemented on
 *     `blood`, whose saver carries the IDENTICAL standing-order block --
 *     `ci_save_blood.php:725-746` is a line-for-line sibling of the cited
 *     `ci_save_req.php:570-593`, writing `stand` + `rl` rows into
 *     `ci_batches_blood_meta` and minting a fresh accession per scheduled date.
 *     `col.fixme` below records the literal `req` target as unreachable.
 *  3. S-STA-06's collector branch narrows by CLINIC, not by `col_ID` on the
 *     specimen: `admin_covid_reporting.php:80-86` reads `_collector_id`, joins
 *     `ci_clinic_collector`, and filters the CLINIC list. No `ci_batches_pcr`
 *     column is involved. It is unreachable regardless -- see that describe.
 *  4. `personas.md:129` says both slugs are "scoped by user meta
 *     `_collector_id`". The seeded `e2e_collector` carries `_lab_id=19`,
 *     `_lab_ids=19` and `_clinic_id=1` but NO `_collector_id`. Per
 *     `12-auth-and-tenancy.md` 2, `_collector_id` only fires when `_lab_id` is
 *     EMPTY (`ci_base_lab.php:68-69,:90-95`), so on this persona the meta is
 *     doubly inert. Where `_collector_id` is read directly -- the Phlebotomy
 *     menu gate -- its absence is observable, and that is the scope test below.
 *
 * WHO WRITES THE ROWS. `e2e_collector` itself: it live-holds `form_blood`, the
 * capability the Blood Req Form is registered under, and the saver takes the
 * destination lab from the posted `bat[lab_id]` (`ci_save_blood.php:24-26` ->
 * `tr_get_new_assen('blood', false, '', $lab)` at `:192,:736`), which is how
 * seedBloodRequisition is already parameterised. So every row lands in this
 * spec's pool lab and D172's tenancy invariant is asserted, not assumed.
 *
 * TENANCY. SPEC_LAB maps `col` -> pool index 10 -> LAK. No lab is created here
 * (D161). The `tenant` fixture is taken as given and never overridden.
 */
const col = sharedTest;

/** The persona under test. Role `collector`, seeded by env/seed.php. */
const COLLECTOR = 'e2e_collector';
/** lab_tech -- the only minted persona holding `ci_standing_orders`. */
const STANDING = 'e2e_tech';

function ctxFor(baseURL: string | undefined, login: string): Promise<APIRequestContext> {
  return playwrightRequest.newContext({
    baseURL: baseURL ?? 'http://localhost',
    storageState: path.join(STATE_DIR, `${login}.json`),
  });
}

/** A plain blood panel, resolved live so the spec cannot go stale on tg_ID. */
function bloodGroupId(db: Db): string {
  const id = db.scalar(
    `SELECT tg_ID FROM ci_test_groups WHERE tg_type='blood' AND tg_manual_edit=0 ORDER BY tg_ID LIMIT 1`,
  );
  if (!id) throw new Error('no plain blood test group in ci_test_groups -- run env/seed.sh');
  return id;
}

// ---------------------------------------------------------------- S-ORD-06

col.describe('S-ORD-06 @full @col @ord', () => {
  // D113: tr_get_new_assen() is not thread-safe, and this one request mints
  // FOUR accessions in a server-side loop with no mutex but PHP's session lock.
  // Nothing else in this file writes a batch, so the file is internally
  // sequential; run it with --workers=1.
  col('a standing order mints one blood batch per scheduled date, each with a distinct accession', async ({
    db, tenant, runKey, baseURL,
  }, testInfo) => {
    const groupId = bloodGroupId(db);

    // Dates come from the DB so the schedule is expressed in the stack's own
    // calendar. +30d keeps the window clear of any "today" listing and of the
    // autumn DST change, across which `strtotime(start) + n*86400` -- the
    // arithmetic ci_save_blood.php:733 actually performs -- would slip a day.
    const [d] = db.query(
      `SELECT DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 32 DAY),
              DATE_ADD(CURDATE(), INTERVAL 34 DAY), DATE_ADD(CURDATE(), INTERVAL 36 DAY)`,
    );
    const scheduled = d;             // [start, +2, +4, +6]
    const [start, , , end] = scheduled;
    const FREQUENCY = '2';           // every 2 days -> 3 children, not "one a day"

    const patientLast = `Colsto${runKey}A`;
    const req = await ctxFor(baseURL, COLLECTOR);
    const seeded = await seedBloodRequisition(req, db, tenant.labId, patientLast, {
      // D226 case 4: an ARRAY, or ci_save_blood.php:439-442 silently stores ''.
      'bat[panel_tests_requested][]': [`group_${groupId}`],
      add_standing_order: '1',
      standing_start: start,
      standing_end: end,
      standing_frequency: FREQUENCY,
    });
    await req.dispose();

    // Not toBeLessThan(400): a login redirect is 302 and would pass that.
    expect(seeded.status, 'the blood saver answers 200 on the standing-order POST').toBe(200);

    // D140/D142: rows GAINED on THIS test's key. The AUTO_INCREMENT delta moves
    // even when the saver writes nothing, and a global count is satisfied by a
    // sibling worker's row.
    expect(
      seeded.keyRowsAfter - seeded.keyRowsBefore,
      `no ci_batches_blood rows for ${seeded.patientName} at HTTP ${seeded.status} -- the saver ` +
        'returns 200 and writes nothing when a written column is missing (D100) or the ' +
        'panel arrived as a scalar (D226)',
    ).toBe(scheduled.length);

    const rows = db.query(
      `SELECT mid, assen, DATE(doc), batch_type, batch_status, lab, lab_id, panel_tests_requested
         FROM ci_batches_blood WHERE patient_name = ${Db.q(seeded.patientName)} ORDER BY mid`,
    );
    expect(rows.length, 'one batch per scheduled date').toBe(scheduled.length);

    // THE ASSERTION THAT MATTERS: N accessions, all non-empty, all DISTINCT,
    // and each unique across the whole table. The minter under repetition is
    // the thing under test -- a loop that reused one accession, or that emitted
    // '' because tr_get_new_assen() failed (D160), still answers HTTP 200.
    const assens = rows.map((r) => r[1]);
    for (const a of assens) expect(a, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
    expect(new Set(assens).size, `accessions are not distinct: ${assens.join(', ')}`).toBe(scheduled.length);
    const dupes = db.query(
      `SELECT assen, COUNT(*) FROM ci_batches_blood
        WHERE assen IN (${assens.map((a) => Db.q(a)).join(',')}) GROUP BY assen`,
    );
    expect(dupes.length, 'every minted accession resolves to exactly one row').toBe(scheduled.length);
    for (const [a, n] of dupes) expect(n, `accession ${a} is not unique`).toBe('1');

    // ...one per SCHEDULED DATE, at the requested frequency.
    expect(rows.map((r) => r[2]), 'ci_batches_blood.doc, one row per scheduled date').toEqual(scheduled);

    // TENANCY, asserted on every row (D172). E2E_FAULT=wrong-lab reds here.
    for (const [mid, assen, , batchType, batchStatus, lab, labId, panels] of rows) {
      expect(labId, `ci_batches_blood.lab_id on mid ${mid}`).toBe(tenant.labId);
      expect(lab, `ci_batches_blood.lab on mid ${mid}`).toBe(tenant.prefix);
      expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(tenant.prefix);
      expect(batchType, `batch_type on mid ${mid}`).toBe('E');
      expect(batchStatus, `batch_status on mid ${mid} -- a standing order is not yet received`).toBe('');
      expect(panels, `panel_tests_requested on mid ${mid}`).toContain(`group_${groupId}`);
    }

    // The schedule and its children are linked through the meta table, hung off
    // the PARENT mid (ci_save_blood.php:727-742). Exact ids, never a LIKE.
    const parentMid = rows[0][0];
    const childMids = rows.slice(1).map((r) => r[0]);
    const meta = db.query(
      `SELECT meta_key, meta_value FROM ci_batches_blood_meta
        WHERE mid = ${Db.q(parentMid)} ORDER BY meta_key, meta_value+0`,
    );
    const stand = meta.filter((m) => m[0] === 'stand').map((m) => m[1]);
    const links = meta.filter((m) => m[0] === 'rl').map((m) => m[1]);
    expect(stand, 'exactly one `stand` row, carrying start|end|frequency').toEqual(
      [`${start}|${end}|${FREQUENCY}`],
    );
    expect(links, 'one `rl` row per scheduled child, pointing at its mid').toEqual(childMids);
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_batches_blood_meta WHERE mid IN (${childMids.map((m) => Db.q(m)).join(',')})`,
      ),
      'the children carry no schedule of their own -- the link is one-way from the parent',
    ).toBe('0');

    console.log(
      `S-ORD-06 worker=${testInfo.workerIndex} lab=${tenant.prefix}(${tenant.labId}) ` +
        `parent=${parentMid} children=${childMids.join(',')} assens=${assens.join(',')}`,
    );
  });
});

col.fixme(
  'S-ORD-06 @col @ord the same schedule on btype `req`',
  // Unreachable, not unwritten. CI_ALL_BATCH_TYPES is `tox, blood, pcr` on this
  // instance, so dbDelta never created `ci_batches_req` or `ci_batches_req_meta`
  // and `admin.php?page=req_req_form` is registered under capability `form_req`,
  // which NO live role grants (D102). Both halves of the catalog row -- the
  // table pair and the entry screen -- are absent. The behaviour it describes is
  // covered above on `blood`, whose saver is the same code.
  async () => {},
);

// ------------------------------------------------ collector capability surface

test.describe('collector page-layer capability surface @full @col', () => {
  // D185/D250: a page-layer denial is HTTP 200 with a 2,629-byte denial body,
  // never a 403. Asserting the status code would pass for the wrong reason.
  //
  // This is the standing regression guard on correction 1 in the header: the
  // day someone re-runs install_roles.php against a rebuilt role, `collector`
  // gains form_requisition and the first row here goes red.
  const CASES = [
    ['tox_req_form', false, 'Requisition -- needs form_requisition, which live `collector` LACKS'],
    ['blood_req_form', true, 'Blood Req Form -- form_blood'],
    ['pcr_req_form', true, 'PCR Requisition Form -- form_pcr'],
  ] as const;

  for (const [page, allowed, why] of CASES) {
    test(`${COLLECTOR} ${allowed ? 'reaches' : 'is denied'} page=${page} (${why})`, async ({ baseURL }) => {
      const ctx = await ctxFor(baseURL, COLLECTOR);
      const body = await (await ctx.get(`/wp-admin/admin.php?page=${page}`)).text();
      expect(/Sorry, you are not allowed/.test(body), 'core denial notice present').toBe(!allowed);
      expect(/name="tr_action"/.test(body), 'the requisition saver form is present').toBe(allowed);
      await ctx.dispose();
    });
  }

  test(`${COLLECTOR} reaches the blood saver this flow posts to`, async ({ baseURL }) => {
    // Not decoration: it is what makes the S-ORD-06 actor choice legitimate.
    // The screen the collector reaches is the one carrying `ci_save_blood`.
    const ctx = await ctxFor(baseURL, COLLECTOR);
    const body = await (await ctx.get('/wp-admin/admin.php?page=blood_req_form')).text();
    expect(body, 'the Blood Req Form posts tr_action=ci_save_blood').toContain('ci_save_blood');
    await ctx.dispose();
  });
});

// --------------------------------------------- collector-scoped Phlebotomy

col.describe('collector-scoped Phlebotomy visibility @full @col', () => {
  // The one place `_collector_id` is read on a path this persona can reach.
  //
  // `inc/admin.php:604-609`: for `$current_role == 'collector'` ONLY, the
  // Phlebotomy page is registered just when the collector's own clinics --
  // resolved `_collector_id` -> ci_clinic_collector -> ci_clinic -- carry
  // `cli_phlebotomist = 1`. Holding `ci_phlebotomist` is necessary and NOT
  // sufficient, and the contrast persona proves the capability alone IS
  // sufficient for every other role.
  //
  // Both halves of the collector's zero-result are asserted from the DB, so
  // this cannot quietly become a test of something else: seeding
  // `_collector_id`, or flipping cli_phlebotomist on a seeded clinic, reds the
  // precondition rather than silently changing what the denial means.
  col('the collector is denied Phlebotomy while a non-collector holding the same capability reaches it', async ({
    db, tenant, baseURL,
  }) => {
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM wp_usermeta m JOIN wp_users u ON u.ID = m.user_id
          WHERE u.user_login = ${Db.q(COLLECTOR)} AND m.meta_key = '_collector_id'`,
      ),
      'precondition: the seeded collector carries no _collector_id',
    ).toBe('0');
    expect(
      db.scalar('SELECT COUNT(*) FROM ci_clinic WHERE cli_status = 1 AND cli_phlebotomist = 1'),
      'precondition: no seeded clinic is flagged as a phlebotomy site',
    ).toBe('0');

    const denied = await ctxFor(baseURL, COLLECTOR);
    const deniedBody = await (await denied.get('/wp-admin/admin.php?page=blood_phlebotomist')).text();
    await denied.dispose();
    expect(
      /Sorry, you are not allowed/.test(deniedBody),
      'the collector is denied Phlebotomy despite holding ci_phlebotomist',
    ).toBe(true);

    // Same capability, different role -> the role-specific clinic gate never
    // runs. Without this half the denial above could be a plain capability miss.
    const allowed = await ctxFor(baseURL, tenant.login);
    const allowedBody = await (await allowed.get('/wp-admin/admin.php?page=blood_phlebotomist')).text();
    await allowed.dispose();
    expect(
      /Sorry, you are not allowed/.test(allowedBody),
      `${tenant.login} (leadlabsecman) holds ci_phlebotomist and is NOT a collector, so it reaches Phlebotomy`,
    ).toBe(false);
    expect(allowedBody, 'the Phlebotomist Log rendered').toContain('Phlebotomist Log');
  });
});

// ------------------------------------------- ci_standing_orders is a tab, not a gate

col.describe('standing-orders capability surface @full @col', () => {
  // `ci_standing_orders` is the capability that distinguishes the `Phlebotomist`
  // slug from `collector`. Measured, it hides a TAB and gates nothing:
  //
  //   parts/blood/phlebotomist.php:32-37 unsets the `standing_orders` tab when
  //   current_user_can('ci_standing_orders') is false -- but :59-61 then
  //   includes that very screen straight from `$_REQUEST['tab']` with no
  //   re-check, and parts/blood/standing_orders.php:47-56 falls back to
  //   `$btypes = ['blood']` when the capability is absent. So the screen renders
  //   in full for a persona the menu deliberately hid it from. Same shape as
  //   D216: authorization is incidental to menu registration, not enforced at
  //   the screen.
  //
  // Not hypothetical for this spec: the persona that WROTE the standing order in
  // S-ORD-06 above -- and the whole `ci_save_blood` standing-order block -- runs
  // without this capability being consulted anywhere.
  col('the tab is capability-gated but the screen behind it is not', async ({ tenant, baseURL }) => {
    const without = await ctxFor(baseURL, tenant.login);   // leadlabsecman: no ci_standing_orders
    const withCap = await ctxFor(baseURL, STANDING);       // lab_tech:      holds it

    const base = '/wp-admin/admin.php?page=blood_phlebotomist';
    const [plain, direct, capPlain] = await Promise.all([
      without.get(base).then((r) => r.text()),
      without.get(`${base}&tab=standing_orders`).then((r) => r.text()),
      withCap.get(base).then((r) => r.text()),
    ]);
    await Promise.all([without.dispose(), withCap.dispose()]);

    expect(/tab=standing_orders/.test(capPlain), `${STANDING} holds ci_standing_orders, so the tab is offered`)
      .toBe(true);
    expect(/tab=standing_orders/.test(plain), `${tenant.login} lacks it, so the tab is hidden`).toBe(false);
    expect(plain, 'the persona still lands on the Phlebotomist Log').toContain('Phlebotomist Log');

    // ...and the hidden screen serves itself anyway, at HTTP 200, no denial.
    expect(/Sorry, you are not allowed/.test(direct), 'no denial on the hidden tab').toBe(false);
    expect(direct, 'the Standing Orders screen rendered for a persona without the capability')
      .toContain('change_standing_status');
  });
});

// ------------------------------------------------------- S-STA-06 collector

test.describe('S-STA-06 collector branch @full @col @sta', () => {
  // The scope map at flow-covid-and-state-reporting.md:56-63 branches on
  // `collector` at admin_covid_reporting.php:80-86. It is dead code for this
  // role: State Reporting is registered under `covid_reporting`
  // (inc/admin.php:417), and live that capability is held by administrator,
  // clms, labsecman, lab_sec, administrator_p, AdminCIDW and IT (D233) --
  // `collector` is not among them. The branch 200-denies before the scope map
  // is reached, which is what this asserts.
  test(`${COLLECTOR} is denied page=ci_reports_state_report`, async ({ baseURL }) => {
    const ctx = await ctxFor(baseURL, COLLECTOR);
    const body = await (await ctx.get('/wp-admin/admin.php?page=ci_reports_state_report')).text();
    expect(/Sorry, you are not allowed/.test(body), 'core denial notice present').toBe(true);
    expect(/name="from"/.test(body), 'the reporting date filter must not render').toBe(false);
    await ctx.dispose();
  });
});

// ===========================================================================
// WAVE 5 -- the four gates above AS THE PERSONA MEETS THEM.
//
// D382: nothing above is deleted. Every HTTP test in this file keeps running;
// these are added beside them, and they are not redundant -- the raw-HTML
// tests regex a response body, while a gate in this app is a MENU ENTRY
// (D250). `add_page()` is the only capability check these screens get, so the
// link the collector can see and the authorization decision are the same
// event, and no `.text()` assertion has ever covered it.
//
// Cost note (D403/probe): these are the page-gate shape, measured at ~1 s of
// CI per test, not the ~14 s requisition shape. The collector never submits a
// requisition anywhere in this file, so nothing here pays that price.
// ===========================================================================

col.describe('collector page-layer capability surface, on screen @full @col', () => {
  /**
   * page slug, allowed, the admin-menu entry that IS the gate, the form banner.
   *
   * Menu labels read off `inc/admin.php` at the pin and confirmed live on this
   * stack 2026-08-25: e2e_collector's menu is
   * [Dashboard, Welcome, igniteABX..., Blood Req Form, PCR Req Form, Test
   * Results] -- "Toxicology Req" is absent, which is correction 1 in the header
   * stated as something a person can see.
   *
   * Banners are pinned blobs, not the working tree: `parts/tox/req_form.php:416`,
   * `parts/blood/req_form.php:475`, `parts/pcr/req_form.php:463` at a7aa074d.
   * (The working tree carries `data-testid="req-submit"` on these forms from the
   * unmerged hooks branch; it does not exist at the pin and is banned by D383.)
   */
  const SCREENS = [
    ['tox_req_form', false, 'Toxicology Req', 'THERAPEUTIC DRUG TEST REQUISITION FORM'],
    ['blood_req_form', true, 'Blood Req Form', 'Blood Requisition Form'],
    ['pcr_req_form', true, 'PCR Req Form', 'PCR REQUISITION FORM'],
  ] as const;

  for (const [slug, allowed, menuLabel, banner] of SCREENS) {
    col(`${COLLECTOR} ${allowed ? 'is offered' : 'is never offered'} ${menuLabel} and ${allowed ? 'reaches' : 'is denied'} page=${slug}`, async ({
      pageAs,
    }) => {
      const page = await pageAs(COLLECTOR);

      // FACT ONE -- THE MENU IS THE GATE, read from a NEUTRAL SCREEN.
      //
      // NOT `/wp-admin/`, which the plugin bounces to page=welcome: for THIS
      // persona that page hides the whole navigation, so every menu assertion
      // there resolves 0 and the loop's allowed rows would fail for a reason
      // that has nothing to do with capabilities (see the lockout test below).
      // `profile.php` is core, reachable by every role, and renders the
      // plugin's menu untouched -- measured on this stack 2026-08-25.
      await page.goto('/wp-admin/profile.php');
      await expect(
        page.locator('#adminmenu').getByRole('link', { name: menuLabel, exact: true }),
        `${COLLECTOR} ${allowed ? 'finds' : 'never finds'} "${menuLabel}" in the admin menu`,
      ).toHaveCount(allowed ? 1 : 0);

      // FACT TWO -- WHAT HAPPENS WHEN THE PERSONA TYPES THE URL ANYWAY. The
      // separate fact D250 exists for: an unlisted screen is the least-guarded
      // surface in the app, and only this leg can tell "hidden" from "denied".
      await page.goto(`/wp-admin/admin.php?page=${slug}`);
      // D392 -- bounded on the denied branch ONLY. Measured on this stack for
      // all three of this file's denied pages (tox_req_form, blood_phlebotomist,
      // ci_reports_state_report): t=0,1,2 all show denial 1 / #adminmenu 0 /
      // the 3 s meta-refresh present. The default 5 s expect timeout outlives
      // the page, so an unbounded poll could grade page=welcome instead.
      const fused = allowed ? {} : { timeout: BEFORE_BOUNCE_MS };
      await expect(
        page.getByText('Sorry, you are not allowed to access this page.'),
        `${COLLECTOR} ${allowed ? 'is never shown' : 'is shown'} the core denial notice`,
      ).toHaveCount(allowed ? 0 : 1, fused);
      await expect(
        page.locator('#adminmenu'),
        'the denied persona lands on a bare error page with no admin menu to navigate from',
      ).toHaveCount(allowed ? 1 : 0, fused);

      // ...and the screen ACTUALLY DREW for the persona who is allowed in. A
      // PHP fatal inside the form render also answers 200 with no denial text,
      // which is one of the six ways this app returns 200 and does nothing.
      //
      // SYMMETRY IS WHAT KEEPS THE DENIED ROWS HONEST: a `toHaveCount(0)` on a
      // banner string that exists nowhere would pass for any product change.
      // Two of the three banners are asserted at count 1 by the allowed rows of
      // this same loop. The third -- the tox one, which this persona can never
      // see -- is asserted at count 1 by ord.spec.ts for the personas that can,
      // and is `parts/tox/req_form.php:416` at the pin.
      const body = page.locator('#wpbody-content');
      await expect(
        body.getByRole('heading', { name: banner, exact: true }),
        `${COLLECTOR} ${allowed ? 'sees' : 'never sees'} the ${banner} banner`,
      ).toHaveCount(allowed ? 1 : 0, fused);
      // The rendered twin of the old /name="tr_action"/ regex: the control the
      // collector would actually press, not the hidden field behind it. Value
      // "Submit" at the pin; "Print Barcode / Submit" and "Save temporary" are
      // siblings that `exact` keeps out.
      await expect(
        body.getByRole('button', { name: 'Submit', exact: true }),
        `${COLLECTOR} ${allowed ? 'can submit' : 'is offered no way to submit'} on page=${slug}`,
      ).toHaveCount(allowed ? 1 : 0, fused);

      // The fuse itself, pinned rather than waited out -- the line that reds if
      // it is retimed, which is what BEFORE_BOUNCE_MS is derived from (D392).
      await expect(
        page.locator('meta[http-equiv="refresh"][content^="3;"][content*="page=welcome"]'),
        'the denial page carries the 3 s bounce to page=welcome',
      ).toHaveCount(allowed ? 0 : 1, fused);
    });
  }
});

col.describe('collector-scoped Phlebotomy visibility, on screen @full @col', () => {
  /**
   * The rendered twin of the denial test above, and the one place in this file
   * where the menu leg is not merely a second fact but THE mechanism:
   * `inc/admin.php:607-612` (pinned) does not register Phlebotomy for a
   * `collector` unless that collector's own clinics -- `_collector_id` ->
   * ci_clinic_collector -> ci_clinic -- carry `cli_phlebotomist = 1`. There is
   * no capability check to fail; the page simply never exists for this user.
   *
   * Both halves are asserted, because either alone is ambiguous: the collector
   * holding `ci_phlebotomist` and still getting nothing, next to a persona
   * holding the SAME capability that gets the menu entry and the screen.
   */
  col('the collector is offered no Phlebotomy menu entry while a non-collector holding the same capability is', async ({
    pageAs, db, tenant,
  }) => {
    // Same two preconditions as the HTTP test: they are what makes the denial
    // mean "the clinic gate closed", not "the capability is missing". Seeding
    // `_collector_id` or flipping cli_phlebotomist reds these, not the assertions.
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM wp_usermeta m JOIN wp_users u ON u.ID = m.user_id
          WHERE u.user_login = ${Db.q(COLLECTOR)} AND m.meta_key = '_collector_id'`,
      ),
      'precondition: the seeded collector carries no _collector_id',
    ).toBe('0');
    expect(
      db.scalar('SELECT COUNT(*) FROM ci_clinic WHERE cli_status = 1 AND cli_phlebotomist = 1'),
      'precondition: no seeded clinic is flagged as a phlebotomy site',
    ).toBe('0');

    const denied = await pageAs(COLLECTOR);
    await denied.goto('/wp-admin/profile.php');   // neutral vantage, see above
    await expect(
      denied.locator('#adminmenu').getByRole('link', { name: 'Phlebotomy', exact: true }),
      `${COLLECTOR} holds ci_phlebotomist and is still offered no Phlebotomy entry -- ` +
        'the collector branch of the menu gate closed on its clinics',
    ).toHaveCount(0);

    await denied.goto('/wp-admin/admin.php?page=blood_phlebotomist');
    await expect(
      denied.getByText('Sorry, you are not allowed to access this page.'),
      'and typing the URL is denied too, so the entry is missing because the page is not registered',
    ).toHaveCount(1, { timeout: BEFORE_BOUNCE_MS });

    // THE CONTRAST. Same capability, different role -> the role-specific clinic
    // gate never runs. Without this half the denial above could be read as a
    // plain capability miss, which is exactly what it is not.
    const allowed = await pageAs(tenant.login);
    await allowed.goto('/wp-admin/profile.php');
    await expect(
      allowed.locator('#adminmenu').getByRole('link', { name: 'Phlebotomy', exact: true }),
      `${tenant.login} (leadlabsecman) holds ci_phlebotomist and is NOT a collector, ` +
        'so the entry is offered',
    ).toHaveCount(1);

    // Scoped to #adminmenu like the count above it: an unscoped click that
    // found a second match anywhere on the page would be a strict-mode throw
    // rather than a readable failure.
    await allowed.locator('#adminmenu').getByRole('link', { name: 'Phlebotomy', exact: true }).click();
    await expect(
      allowed.getByRole('heading', { name: 'Phlebotomist Log', exact: true }),
      'and following it lands on the Phlebotomist Log (parts/blood/phlebotomist.php h2#phlebotop)',
    ).toBeVisible();
  });
});

col.describe('standing-orders capability surface, on screen @full @col', () => {
  /**
   * D216-class, stated as two things a person can see: the tab is hidden from a
   * persona without `ci_standing_orders`, and the screen behind it serves that
   * same persona in full when the URL is typed.
   *
   * TAB LABEL. `parts/blood/phlebotomist.php:19-26` (pinned) names the tab
   * "Standing Orders" and RENAMES it "Orders" when
   * ci_req_settings_blood.Phlebotomist_showallblood or .show_all_standing_order
   * is set -- which it is on this stack, so "Orders" is what renders (measured
   * for e2e_tech, 2026-08-25). It is scoped to #wpbody-content: neither
   * persona's admin menu contains an "Orders" entry, and this keeps it that way.
   *
   * The whole nav-tab-wrapper is EMPTY for the persona without the capability
   * (`:41` only emits tabs when more than one survives), so the hidden case is
   * not "a tab that is greyed out" -- there is no tab strip at all.
   */
  const ORDERS_TAB = 'Orders';
  const PHLEB = '/wp-admin/admin.php?page=blood_phlebotomist';

  col('the tab is capability-gated but the screen behind it is not', async ({ pageAs, tenant }) => {
    const withCap = await pageAs(STANDING);          // lab_tech: holds it
    await withCap.goto(PHLEB);
    await expect(
      withCap.locator('#wpbody-content').getByRole('link', { name: ORDERS_TAB, exact: true }),
      `${STANDING} holds ci_standing_orders, so the tab is offered`,
    ).toHaveCount(1);

    const without = await pageAs(tenant.login);      // leadlabsecman: does not
    await without.goto(PHLEB);
    await expect(
      without.locator('#wpbody-content').getByRole('link', { name: ORDERS_TAB, exact: true }),
      `${tenant.login} lacks ci_standing_orders, so the tab is hidden`,
    ).toHaveCount(0);
    await expect(
      without.getByRole('heading', { name: 'Phlebotomist Log', exact: true }),
      'the persona still lands on the Phlebotomist Log',
    ).toBeVisible();

    // ...and the hidden screen serves itself anyway, at HTTP 200, no denial.
    // The rendered twin of the old /change_standing_status/ substring: the
    // listing a person can actually read and act on. `parts/blood/
    // standing_orders.php:393,409-424` at the pin.
    await without.goto(`${PHLEB}&tab=standing_orders`);
    await expect(
      without.getByText('Sorry, you are not allowed to access this page.'),
      'no denial on the hidden tab',
    ).toHaveCount(0);
    await expect(
      without.getByText('From Date:', { exact: true }),
      `${tenant.login} gets the Standing Orders date filter it was never offered a tab to`,
    ).toBeVisible();
    for (const header of ['Accession', 'Patient Name', 'Panels', 'Status']) {
      await expect(
        without.getByRole('columnheader', { name: header, exact: true }),
        `...and the full standing-order worklist, column "${header}"`,
      ).toHaveCount(1);
    }
  });
});

col.describe('S-STA-06 collector branch, on screen @full @col @sta', () => {
  // The rendered twin of the denial above. State Reporting is registered as a
  // SUBPAGE under `covid_reporting` (inc/admin.php:417,:537 at the pin, label
  // "State Reporting"), a capability `collector` does not hold -- so the branch
  // at admin_covid_reporting.php:80-86 is dead code for this role and the
  // persona never gets as far as the scope map.
  //
  // POSITIVE CONTROLS, in another file: no persona in THIS file holds
  // covid_reporting, so all three locators below would sit at 0 forever if any
  // were misspelled. sta.spec.ts asserts each at count 1 for e2e_sec on this
  // same slug -- the menu link at :637, the 'State Reporting' heading at :664
  // and the Export Report control at :669 -- so a label or slug change reds
  // there and is caught. The menu label is `inc/admin.php:417,:537` at the pin.
  col(`${COLLECTOR} is offered no State Reporting entry and is denied page=ci_reports_state_report`, async ({
    pageAs,
  }) => {
    const page = await pageAs(COLLECTOR);
    await page.goto('/wp-admin/profile.php');     // neutral vantage, see above
    await expect(
      page.locator('#adminmenu').getByRole('link', { name: 'State Reporting', exact: true }),
      `${COLLECTOR} lacks covid_reporting, so State Reporting is never in its menu`,
    ).toHaveCount(0);

    await page.goto('/wp-admin/admin.php?page=ci_reports_state_report');
    await expect(
      page.getByText('Sorry, you are not allowed to access this page.'),
      'and typing the URL is denied',
    ).toHaveCount(1, { timeout: BEFORE_BOUNCE_MS });
    // The rendered twin of the old /name="from"/ regex. NOT `input[name="from"]`
    // itself: measured, that is `<input type="hidden" name="from" value="...">`,
    // which the operator can never see -- and sta.spec.ts:667-671 retired the
    // same locator for the same reason. This is the control the reporter
    // actually came for, and sta.spec.ts:669 pins it at count 1 for e2e_sec,
    // which is the positive control this file's header says it lacks.
    await expect(
      page.getByRole('button', { name: /Export Report/ }),
      'the export control must not render for a denied persona',
    ).toHaveCount(0, { timeout: BEFORE_BOUNCE_MS });
  });
});

col.describe('collector landing page @full @col', () => {
  /**
   * THE VISIBLE CONSEQUENCE OF CORRECTION 4, and the reason every menu
   * assertion in this file reads from `profile.php` instead of `/wp-admin/`.
   *
   * `pages/admin_welcome.php:138-148` (pinned): for `$current_role ==
   * 'collector'`, if `_collector_id` resolves no `ci_collector` row AND no row
   * matches the user's e-mail, `$account_error` is set. At `:190-192` that
   * prints a red banner and injects
   *
   *     <style>#adminmenu,#adminmenuback{display:none !important}</style>
   *
   * so the persona's own landing page carries NO navigation at all -- it is not
   * a denial, not a 403, and not visible in any response-body regex, which is
   * why no HTTP test in this suite could ever have caught it. The seeded
   * `e2e_collector` has neither the meta nor a matching `ci_collector` row, so
   * this is what a collector on this stack actually lands on.
   *
   * FIXTURE GAP, reported not patched (env/seed.php is shared): seeding a
   * `ci_collector` row for e2e_collector -- which the Phlebotomy gate also
   * wants -- clears the banner, and this test reds and says so rather than
   * going quietly vacuous.
   */
  col('a collector with no ci_collector record lands on a locked-out welcome page', async ({
    pageAs, db,
  }) => {
    expect(
      db.scalar(
        `SELECT COUNT(*) FROM ci_collector c JOIN wp_users u ON u.user_email = c.col_email
          WHERE u.user_login = ${Db.q(COLLECTOR)}`,
      ),
      'precondition: no ci_collector row matches the collector\'s e-mail either -- ' +
        'admin_welcome.php:142 falls back to that lookup before giving up',
    ).toBe('0');

    const page = await pageAs(COLLECTOR);
    await page.goto('/wp-admin/');
    await expect(page, 'the plugin lands every persona on its own welcome screen')
      .toHaveURL(/page=welcome/);
    await expect(
      page.getByRole('heading', { name: 'Your Account not available. Please contact admin' }),
      'the collector is told its account is unusable',
    ).toBeVisible();
    // The `display:none !important` rule, as a person experiences it: not one
    // link to anywhere. TWO assertions, not one, because Playwright's
    // `toBeHidden()` ALSO passes when the locator resolves to no DOM node at
    // all -- so on its own it cannot tell "hidden by the style rule" from
    // "menu gone". The count pins presence (which is why a body regex sees a
    // normal admin page), the visibility pins the rule.
    await expect(
      page.locator('#adminmenu'),
      'the admin menu is still in the DOM -- this is a style rule, not a missing menu',
    ).toHaveCount(1);
    await expect(
      page.locator('#adminmenu'),
      'and is left with no navigation at all -- the whole admin menu is hidden by ' +
        'the style rule admin_welcome.php:191 injects',
    ).toBeHidden();

    // The contrast that stops this reading as "the menu is hidden for everyone
    // on this screen": a persona with no account error keeps its navigation on
    // the very same page.
    const ok = await pageAs(STANDING);
    await ok.goto('/wp-admin/');
    await expect(ok, 'same screen').toHaveURL(/page=welcome/);
    await expect(
      ok.locator('#adminmenu'),
      `${STANDING} reaches the same welcome page with its navigation VISIBLE -- ` +
        'the half that makes the toBeHidden above discriminate',
    ).toBeVisible();
    await expect(
      ok.locator('#adminmenu').getByRole('link', { name: 'Blood Req Form', exact: true }),
      `${STANDING} reaches the same welcome page with its navigation intact`,
    ).toHaveCount(1);
  });
});
