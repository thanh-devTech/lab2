import { expect } from '@playwright/test';
import type { APIRequestContext, APIResponse, Locator, Page } from '@playwright/test';
import { test as e2e } from '../fixtures/test';
import { batchIds } from '../fixtures/batch-id';
import { Db } from '../fixtures/db';
import { allocateTenant, type Tenant } from '../fixtures/tenant';
import { seedRequisition as seedRequisitionAs } from '../fixtures/seed-domain';

/**
 * XTW -- THE CROSS-TENANT WRITE AXIS.
 *
 * WHY THIS FILE EXISTS. The suite already asserts tenancy on the READ side --
 * `rev.spec.ts` S-REV-09 shows the tox queue answering a foreign `lab_id`, and
 * the pin below it shows a reviewer READING another lab's accession by changing
 * one form field. Five read-side tenancy assertions were in the tree and ZERO
 * write-side ones. Reading another lab's specimen is a breach; WRITING one is
 * the same breach plus a patient result that is now wrong or gone, in a lab
 * whose staff never touched it and cannot see who did.
 *
 * WHAT A TEST HERE DOES. Two labs from the baked pool, each with its own
 * reviewer persona. The VICTIM lab seeds and reviews its own specimen through
 * the app. The ATTACKER lab -- an ordinary, legitimate lab user of a DIFFERENT
 * lab, not an outsider and not an administrator -- then issues the exact
 * request its OWN screen issues, with the victim's keys substituted, and the
 * victim's report must come through it untouched.
 *
 * THE POSITIVE CONTROL IS THE POINT. Every test first fires the identical
 * request at the ATTACKER'S OWN specimen and asserts it worked -- in the
 * database and on the attacker's own screen. Without it a pin here could pass
 * for the most boring reason in this codebase: a request that does nothing at
 * all still leaves the victim's row unchanged (signature modes 1-6 -- wrong
 * slug, wrong verb, missing column, all of them HTTP 200 and no write). The
 * control runs BEFORE `e2e.fail()`, so a broken request shape is a RED here and
 * is never absorbed as "the expected failure" (false-green mechanism 6).
 *
 * NO CAPABILITY GATE EXISTS TO EXPLAIN THESE AWAY. `admin/init.php:59` maps
 * `tr_action=<x>` straight onto `tradmin_action_<x>` on `admin_init` with no
 * capability check and no nonce, and `parts/tox/results.php` reads `act` the
 * same way. The role names in the markup (`ci_load_results.php:648` renders the
 * Delete control only for lab/lab_tech/AdminCIDW/administrator) gate the
 * CONTROL, never the handler -- D250 in one line.
 *
 * TENANCY. LAO and LAP, the two SPARE pool labs (`fixtures/tenant.ts` allocates
 * LAA..LAN to the fourteen spec files). Reached through `allocateTenant`
 * directly rather than through the `tenant` fixture, because this spec needs
 * TWO labs and `tenantForSpec` hands out exactly one. `rev.spec.ts` borrows LAO
 * as its foreign lab; every action below is keyed on a `batch_id` from
 * `fixtures/batch-id.ts` or on a specific report id, so the two files cannot
 * select each other's rows.
 *
 * MUTATION ON RENDER (D034). `ci_load_results.php:415` INSERTS the ci_reports
 * row the first time the queue is rendered for a batch -- and it inserts a
 * fresh one, with a NEW id, if the previous one has been deleted. That is why
 * every database read after a delete happens BEFORE the next navigation, and
 * why the rendered assertions below are keyed on the REPORT ID rather than on
 * "a row is present": a re-render restores a row, it does not restore the
 * report.
 */

const ADMIN_PAGE = '/wp-admin/admin.php?page=tox_results';

/** LAO -- the lab whose reviewer performs the action. */
const ATTACKER_POOL_INDEX = 14;
/** LAP -- the lab whose patient result is at stake. */
const VICTIM_POOL_INDEX = 15;

const nextBatchId = batchIds('xtw');

// ---------------------------------------------------------------------------
// THE MATRIX
// ---------------------------------------------------------------------------

interface Specimen {
  /** ci_batches.mid */
  mid: string;
  /** ci_batches.assen -- also ci_reports.patient_id */
  assen: string;
  /** ci_batches.batch_id */
  batchId: number;
  /** ci_reports.id, as it stood when the reviewer last looked at the queue */
  reportId: string;
}

/** Everything a request needs in order to name one lab's specimen. */
interface Target {
  tenant: Tenant;
  spec: Specimen;
}

/**
 * `approve` releases a report; `delete` destroys one. The two kinds differ only
 * in what SUCCESS looks like on the acting lab's own specimen, which is what
 * the positive control asserts.
 */
type Kind = 'approve' | 'delete';

interface Vector {
  /** the id the improvement plan and the defect cards use */
  id: string;
  /** what the person doing it would call it */
  what: string;
  kind: Kind;
  /** must the report be RELEASED before the action can touch it? */
  needsReleased: boolean;
  /** the response the app's own control gets back */
  status: number;
  /** where the handler lives, for the failure message */
  handler: string;
  /** the request the app itself issues, with THIS target's keys substituted */
  fire(ctx: APIRequestContext, t: Target): Promise<APIResponse>;
}

const VECTORS: Vector[] = [
  {
    id: 'A1',
    what: 'Approve All on the batch action row',
    kind: 'approve',
    needsReleased: false,
    status: 302,
    handler: 'inc/admin_actions.php:1849 tradmin_action_ci_results_approve_all',
    // The href `parts/tox/results_list.php:360` renders into `a.approve_all`,
    // character for character. The handler interpolates `$_REQUEST['lab_id']`
    // raw into `where lab.lab_ID = {$lab_id}` and never intersects it with
    // CI_Base_Lab::get_user_lab_ids().
    fire: (ctx, t) => ctx.get(
      `${ADMIN_PAGE}&${new URLSearchParams({
        tr_action: 'ci_results_approve_all',
        lab_id: t.tenant.labId,
        ref_lab_id: '0',
        batch_id: String(t.spec.batchId),
      })}`,
      { maxRedirects: 0 },
    ),
  },
  {
    id: 'A1b',
    what: 'Delete All on the pending-results screen',
    kind: 'delete',
    needsReleased: true,
    status: 302,
    handler: 'inc/admin_actions.php:1891 tradmin_action_ci_results_delete_all',
    // `parts/tox/results_pending.php:251` renders this link WITHOUT `ids`, and
    // that branch of the handler is a SQL syntax error (`where re.estatus='C'
    // lab.lab_ID=...`, no `and`), so the screen's own Delete All deletes
    // nothing at all. The `ids` branch below is the one that runs -- and it
    // carries no lab clause whatsoever, so the lab_id in the URL is decoration.
    fire: (ctx, t) => ctx.get(
      `${ADMIN_PAGE}&${new URLSearchParams({
        tr_action: 'ci_results_delete_all',
        lab_id: t.tenant.labId,
        batch_id: String(t.spec.batchId),
        ids: t.spec.reportId,
      })}`,
      { maxRedirects: 0 },
    ),
  },
  {
    id: 'A2',
    what: 'the Delete control on a result row',
    kind: 'delete',
    needsReleased: false,
    status: 200,
    handler: 'parts/tox/results.php:195 act=pdelete',
    // The form `js/admin.js:1330-1338` builds when `a.pdelete` is clicked:
    // a plain POST of `act=pdelete` and `idd` to the page the row is on. The
    // handler asks nothing -- not the lab, not the estatus, not a capability,
    // not a nonce -- and hard-deletes the report and its analytes.
    fire: (ctx, t) => ctx.post(ADMIN_PAGE, {
      form: { act: 'pdelete', idd: t.spec.reportId },
    }),
  },
  {
    id: 'A3',
    what: 'the Delete link on the Pending Results tab',
    kind: 'delete',
    needsReleased: false,
    status: 302,
    handler: 'inc/admin_actions.php:1172 tradmin_action_ci_results_delete',
    // THE DISPATCHER TWIN OF A2, and the pair is the reason this row exists:
    // one rule -- "destroy this report" -- implemented twice, and until now the
    // suite drove one copy. `parts/tox/results_pending.php:435-441` sets
    // `location` to exactly this URL with the row's `data-id` appended, and the
    // Pending Results tab it lives on is offered to `lab_sec`, `labsecman`,
    // `leadlabsecman` and `labb` (`parts/tox/results.php:274-279`) -- ordinary
    // lab seats, not administrators.
    //
    // The handler reads `id` and `btype` and NOTHING else: no lab, no estatus,
    // no capability, no nonce. Its write set differs from A2's as well -- where
    // `act=pdelete` marks the batch `is_saved=2` and clears `result_pdf`, this
    // one never touches ci_batches, so the batch goes on advertising a result
    // PDF for a report that no longer exists. fingerprint() carries both
    // ci_batches columns, so that difference is visible in the failure message
    // rather than assumed.
    fire: (ctx, t) => ctx.get(
      `${ADMIN_PAGE}&${new URLSearchParams({
        tr_action: 'ci_results_delete',
        id: t.spec.reportId,
        btype: 'tox',
      })}`,
      { maxRedirects: 0 },
    ),
  },
];

// ---------------------------------------------------------------------------
// READING THE ROWS
// ---------------------------------------------------------------------------

/**
 * Everything the three handlers under test can write about one specimen, in ONE
 * container exec (fixtures/db.ts -- a query costs ~131 ms, so a spec asks once).
 *
 * NAMED COLUMNS, not `SELECT *`, and that is a deliberate trade. A whole-row
 * fingerprint would go red on any unrelated column an approve cron happens to
 * touch -- and a snapshot that stays red for an unrelated reason keeps a `fail()`
 * pin GREEN after the defect is fixed, which is the false-green this file is
 * meant to be free of. These are exactly the columns
 * `ci_results_approve_all`, `ci_results_delete_all` and `act=pdelete` assign.
 *
 * A deleted report reads `<<the report row is gone>>` rather than an empty
 * string, so the difference between "nothing changed" and "there is nothing
 * left to change" is visible in the failure message.
 *
 * THE ci_results COUNT IS 0 EITHER SIDE TODAY, and is carried anyway rather
 * than claimed as coverage. `act=pdelete` deletes the analyte rows behind the
 * report, but nothing in this suite's fixtures creates any: the requisition
 * saver writes none, `ci_load_results.php:415` inserts only the ci_reports row,
 * and the bench values the results-edit screen saves land in
 * `ci_result_test_speciments` (keyed on mid), not in `ci_results`. It becomes a
 * real assertion the day a fixture imports instrument results; until then the
 * report row itself is what carries the pin.
 */
function fingerprint(db: Db, s: Specimen): string[] {
  // COALESCE PER COLUMN, not around the CONCAT_WS. `ci_load_results.php:415`
  // inserts a draft report with NO estatus, so the column is genuinely NULL --
  // and CONCAT_WS SKIPS a NULL argument rather than emitting an empty field,
  // which would slide every later value one position left and make two
  // different changes produce the same string. `~` stands in for NULL so the
  // field count is fixed; the OUTER coalesce then fires only when the row is
  // gone, which is the one case worth its own words.
  const nn = (col: string) => `COALESCE(\`${col}\`, '~')`;
  const [row] = db.query(
    `SELECT COALESCE((SELECT CONCAT_WS('|', ${nn('estatus')}, ${nn('approve_date')},
                                       ${nn('approved_by_ID')}, ${nn('modified')}, ${nn('lab_id')})
                        FROM ci_reports WHERE id = ${Db.q(s.reportId)}),
                     '<<the report row is gone>>'),
            (SELECT CONCAT_WS('|', ${nn('batch_status')}, ${nn('is_saved')}, ${nn('result_pdf')})
               FROM ci_batches WHERE mid = ${Db.q(s.mid)}),
            (SELECT COUNT(*) FROM ci_results WHERE report_id = ${Db.q(s.reportId)})`,
  );
  return row;
}

/** Does the report the reviewer was looking at still exist, under its own id? */
const reportRows = (db: Db, s: Specimen): string =>
  db.scalar(`SELECT COUNT(*) FROM ci_reports WHERE id = ${Db.q(s.reportId)}`);

const userIdOf = (db: Db, login: string): string =>
  db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(login)}`);

/**
 * ci_reports.estatus / approve_date / batch_status, read fresh. Separate from
 * fingerprint() because the approve pin states these as ABSOLUTE values -- "the
 * report is still a draft" -- and not merely as "the same as before".
 *
 * A draft report carries estatus NULL, which fixtures/db.ts renders as `''` --
 * the same value rev.spec.ts asserts a draft by. Row PRESENCE is asked
 * separately (reportRows), never inferred from a NULL column.
 */
function releaseState(db: Db, s: Specimen): {
  estatus: string; approveDate: string; batchStatus: string;
} {
  const [row] = db.query(
    `SELECT (SELECT estatus      FROM ci_reports WHERE id = ${Db.q(s.reportId)}),
            (SELECT approve_date FROM ci_reports WHERE id = ${Db.q(s.reportId)}),
            (SELECT batch_status FROM ci_batches WHERE mid = ${Db.q(s.mid)})`,
  );
  const [estatus, approveDate, batchStatus] = row;
  return { estatus, approveDate, batchStatus };
}

// ---------------------------------------------------------------------------
// DRIVING THE SCREENS
// ---------------------------------------------------------------------------

/**
 * One tox specimen, seeded through the app's own requisition saver as the named
 * lab's own reviewer (D085/D099), then OPENED ON THAT REVIEWER'S QUEUE.
 *
 * `batch_status='resulted'` is what admits a batch with no report row yet
 * (`ci_load_results.php:92`) while keeping the saver's auto-approve branch shut,
 * so the report is born a draft. The queue navigation is what creates the report
 * row at all (D034), which is why the id is read after it and not before.
 *
 * Everything here is asserted for real: this runs before any `fail()` marker in
 * every test below, so a seeding or persona regression reds loudly instead of
 * being absorbed.
 */
async function seedAndOpen(
  db: Db, page: Page, ctx: APIRequestContext, t: Tenant, patientLast: string,
): Promise<{ spec: Specimen; row: Locator }> {
  const batchId = nextBatchId();
  const seeded = await seedRequisitionAs(ctx, db, t, patientLast, {
    'bat[batch_id]': String(batchId),
    'bat[batch_status]': 'resulted',
  });
  expect(seeded.status, `the requisition saver answers 200 for ${t.prefix}`).toBe(200);

  const rows = db.query(
    `SELECT mid, assen, lab_id FROM ci_batches
      WHERE patient_name = ${Db.q(`${patientLast}, Synthetic`)}`,
  );
  expect(rows.length, `exactly one ci_batches row for ${patientLast}`).toBe(1);
  const [mid, assen, labId] = rows[0];
  // D160: the saver answers 200 with assen='' when the minter fails.
  expect(assen, `the specimen for ${patientLast} was given no accession`).not.toBe('');
  expect(labId, `ci_batches.lab_id -- ${patientLast} belongs to ${t.prefix}`).toBe(t.labId);

  const row = await queueRow(page, assen);
  await expect(row, `${t.login} sees ${assen} on their own review queue`).toHaveCount(1);

  const reportId = db.scalar(`SELECT id FROM ci_reports WHERE patient_id = ${Db.q(assen)}`);
  expect(reportId, `rendering the queue created no report row for ${assen}`).not.toBe('');

  return { spec: { mid, assen, batchId, reportId }, row };
}

/**
 * The review queue filtered to ONE accession, off a fresh GET.
 *
 * `s` is a plain request parameter (`parts/tox/results_list.php:72`), so this is
 * the same filter the search box submits, and a fresh `goto` is the D386
 * read-back: `js/admin.js:1416` rewrites the row's status cell on ANY HTTP 200,
 * so post-XHR markup is the CLIENT's opinion and survives a write that did
 * nothing.
 */
async function queueRow(page: Page, assen: string): Promise<Locator> {
  await page.goto(`${ADMIN_PAGE}&s=${assen}`);
  return page.locator('tr.childp').filter({ hasText: assen });
}

/**
 * The queue row FOR ONE REPORT ID (`ci_load_results.php:556` --
 * `<tr class="childp ..." data-id="<id>">`).
 *
 * Keying on the id rather than on the accession is what makes a deletion
 * visible on screen at all: navigating back to this queue re-inserts a report
 * row for the batch (D034), so "a row for this specimen is present" is restored
 * by the act of looking. The ID is not -- the replacement gets a fresh one.
 */
const rowForReport = (page: Page, s: Specimen): Locator =>
  page.locator(`tr.childp[data-id="${s.reportId}"]`);

/** The reviewer's own release, performed the way the reviewer performs it. */
async function releaseAsOwner(db: Db, page: Page, row: Locator, s: Specimen): Promise<void> {
  await row.getByText('Approve', { exact: true }).click();
  // The CLIENT's acknowledgement, and it is used ONLY as a sync point: waiting
  // for the control to flip is what stops the navigation below from racing the
  // XHR. It proves nothing on its own -- `js/admin.js:1416` rewrites this text
  // on ANY HTTP 200 (D386) -- which is why the release is then re-read off a
  // fresh render and off the table.
  await expect(row.getByText('Reverse', { exact: true }),
    `the queue acknowledged the release of ${s.assen}`).toBeVisible();
  await queueRow(page, s.assen);
  await expect(rowForReport(page, s).getByRole('cell', { name: 'C', exact: true }),
    `${s.assen} is released before anyone reaches for it`).toBeVisible();
  expect(releaseState(db, s).estatus, 'ci_reports.estatus after the reviewer approves')
    .toBe('C');
}

// ---------------------------------------------------------------------------
// THE GENERATED TESTS
// ---------------------------------------------------------------------------

for (const v of VECTORS) {
  /**
   * Every test in this loop is an EXPECTED FAILURE pin. It asserts what the
   * product MUST do -- the other lab's report comes through untouched -- and it
   * goes RED the day the handler intersects the request's lab with
   * CI_Base_Lab::get_user_lab_ids(). Nothing below asserts the defect.
   *
   * THE MARKER IS THE LAST THING BEFORE THE ATTEMPT. Playwright applies
   * `e2e.fail()` at the point it is called, so every line above it -- the two
   * seeded specimens, the victim's own release, and the whole positive control
   * -- is a REAL assertion that reds this test. That is the fix for false-green
   * mechanism 6: a precondition that fails for the wrong reason must not be
   * absorbed as "the expected failure".
   */
  e2e(`S-XTW-${v.id} ${v.what} does not reach another lab specimen @xtw @mutates`, async ({
    db, pageAs, runKey,
  }) => {
    const victim = allocateTenant(db, VICTIM_POOL_INDEX);
    const attacker = allocateTenant(db, ATTACKER_POOL_INDEX);
    expect(attacker.labId, 'the two labs in this test must be different labs')
      .not.toBe(victim.labId);

    // --- THE OTHER LAB'S PATIENT RESULT ------------------------------------
    const vPage = await pageAs(victim.login);
    const v1 = await seedAndOpen(
      db, vPage, vPage.request, victim, `Xtwvic${runKey}${v.id}`,
    );
    if (v.needsReleased) await releaseAsOwner(db, vPage, v1.row, v1.spec);
    const victimBefore = fingerprint(db, v1.spec);
    const victimState = releaseState(db, v1.spec);
    expect(victimState.estatus, `${victim.prefix} report starts ${v.needsReleased ? 'released' : 'a draft'}`)
      .toBe(v.needsReleased ? 'C' : '');

    // --- THE POSITIVE CONTROL, on the acting lab's OWN specimen ------------
    // Same persona, same request, same query string shape -- only the keys
    // differ. If this stops working the request below is inert and the pin
    // below it would pass for no reason at all.
    const aPage = await pageAs(attacker.login);
    const a1 = await seedAndOpen(
      db, aPage, aPage.request, attacker, `Xtwown${runKey}${v.id}`,
    );
    if (v.needsReleased) await releaseAsOwner(db, aPage, a1.row, a1.spec);

    const own = await v.fire(aPage.request, { tenant: attacker, spec: a1.spec });
    expect(own.status(), `${v.what} answers ${attacker.login} on their own specimen`)
      .toBe(v.status);
    // Guarded, because `?? ''` made this row assert NOTHING on the vector that
    // answers 200: there is no Location header there, and `''` can never contain
    // `wp-login`. On that row the login-bounce control is the STATUS assertion
    // above -- a bounce is a 302, so a 200 is already proof the persona was served.
    const ownLocation = own.headers().location;
    if (ownLocation !== undefined) {
      expect(ownLocation, `${attacker.login} was bounced to the login form`)
        .not.toContain('wp-login');
    }

    // Read the tables BEFORE navigating: opening the queue re-inserts a report
    // row for a batch that has lost one (D034), so a navigation here would hide
    // the very deletion the control has to prove happened.
    if (v.kind === 'delete') {
      expect(reportRows(db, a1.spec),
        `${v.what} left ${attacker.login} own report in place -- the request is inert, `
        + 'so this test can say nothing about the other lab')
        .toBe('0');
      await queueRow(aPage, a1.spec.assen);
      await expect(rowForReport(aPage, a1.spec),
        `${attacker.login} own queue still offers the report they deleted`)
        .toHaveCount(0);
    } else {
      const after = releaseState(db, a1.spec);
      expect(after.estatus,
        `${v.what} did not release ${attacker.login} own report -- the request is inert, `
        + 'so this test can say nothing about the other lab')
        .toBe('C');
      expect(after.approveDate, 'ci_reports.approve_date is stamped on the release')
        .not.toBe('');
      expect(after.batchStatus, 'ci_batches.batch_status follows the release')
        .toBe('completed');
      await queueRow(aPage, a1.spec.assen);
      await expect(rowForReport(aPage, a1.spec).getByRole('cell', { name: 'C', exact: true }),
        `${attacker.login} sees their own report released on their own queue`).toBeVisible();
    }

    // --- THE PIN ------------------------------------------------------------

    const crossed = await v.fire(aPage.request, { tenant: victim, spec: v1.spec });
    expect(crossed.status(), `${v.what} answered ${attacker.login} for a ${victim.prefix} specimen`)
      .toBe(v.status);

    // The tables first, and before any navigation, for the D034 reason above.
    if (v.kind === 'delete') {
      expect(reportRows(db, v1.spec),
        `${attacker.login} belongs to ${attacker.prefix} and holds nothing that names `
        + `${victim.prefix}: ${v.what} must not destroy ${victim.prefix}'s patient report `
        + `${v1.spec.assen}. It is not recoverable from this screen and ${victim.prefix}'s `
        + `own staff are not told (${v.handler})`)
        .toBe('1');
    } else {
      const state = releaseState(db, v1.spec);
      expect(state.estatus,
        `${attacker.login} belongs to ${attacker.prefix}: ${v.what} must not release `
        + `${victim.prefix}'s unreviewed report ${v1.spec.assen} to the ordering clinic. `
        + `${victim.prefix}'s reviewer never saw it and is recorded as nothing (${v.handler})`)
        .toBe('');
      expect(state.approveDate, `${v1.spec.assen} was stamped with an approval date`)
        .toBe('');
      expect(state.batchStatus, `${v1.spec.assen} was moved out of the review queue`)
        .toBe('resulted');
    }

    expect(fingerprint(db, v1.spec),
      `${v.what} moved something on ${victim.prefix}'s specimen ${v1.spec.assen}`)
      .toEqual(victimBefore);

    // The audit trail is the other half of the harm: the acting user is
    // recorded against a specimen in a lab they have no standing in, and the
    // lab that owns it has no row naming them at all.
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_log_users
                  WHERE assen = ${Db.q(v1.spec.assen)}
                    AND user_id = ${Db.q(userIdOf(db, attacker.login))}`),
      `${attacker.login} is recorded in ${victim.prefix}'s audit trail as having handled `
      + `${v1.spec.assen}`,
    ).toBe('0');

    // ...and what the lab that owns the specimen sees when it next looks.
    await queueRow(vPage, v1.spec.assen);
    await expect(rowForReport(vPage, v1.spec),
      `${victim.prefix}'s reviewer opens their own queue and the report they were `
      + `reviewing (#${v1.spec.reportId}) is no longer the report on it`)
      .toHaveCount(1);
    if (v.needsReleased) {
      await expect(rowForReport(vPage, v1.spec).getByRole('cell', { name: 'C', exact: true }),
        `${victim.prefix}'s reviewer no longer sees ${v1.spec.assen} as released`).toBeVisible();
    } else {
      await expect(rowForReport(vPage, v1.spec).getByText('Approve', { exact: true }),
        `${victim.prefix}'s reviewer is no longer offered the Approve control on `
        + `${v1.spec.assen} -- somebody else has already signed it out`).toBeVisible();
    }
  });
}
