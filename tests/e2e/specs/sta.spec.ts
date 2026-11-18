import { expect, request as playwrightRequest } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';
import { test as e2e, BEFORE_BOUNCE_MS } from '../fixtures/test';
import { Db } from '../fixtures/db';
import { seedPcrRequisition, type FormValue } from '../fixtures/seed-domain';
import { runCron } from '../fixtures/container';
import { allocateTenant, type Tenant } from '../fixtures/tenant';
import { STATE_DIR } from '../playwright.config';
import * as fs from 'node:fs';
import * as path from 'node:path';

/**
 * STA -- COVID / state reporting (S-STA-01 … 06).
 *
 * PERSONA, verified against the RUNNING instance (D178: never derive a
 * capability from install_roles.php).
 *
 *   `covid_reporting` is held live by exactly 7 roles:
 *   administrator, clms, labsecman, lab_sec, administrator_p, AdminCIDW, IT.
 *
 *   NONE of the slugs the persona catalogue names for this operator hold it:
 *   `data_export` DOES NOT EXIST as a role at all, and `Lab_Reporting` (7 caps),
 *   `ResultsUpload` (4 caps) and `results` (12 caps) all lack it. The live
 *   holder this suite already mints is `e2e_sec` / role `lab_sec`, which also
 *   holds `form_pcr`, so the same persona can both create the PCR specimens and
 *   open State Reporting. Measured: e2e_sec gets 156 kB of screen, e2e_lead5
 *   (leadlabsecman) gets the 2 629-byte denial body. The capability gate test
 *   below is the regression guard on that choice.
 *
 * SCREEN. The catalogue cites `pages/admin_covid_reporting.php:412-557`. That
 * file is NOT what the registered slug renders. `Tr_Admin_Page::callback()`
 * resolves `ci_reports_state_report` to `parts/reports/state_report.php`
 * (pages/admin_ci_reports_state_report.php does not exist, so the `ci_` prefix
 * is stripped and btype/slug split). The export and the `download_report`
 * audit row are at `parts/reports/state_report.php:702-722`.
 *
 * BOUNDARY. `imt.ignitelabconnect.com` is unreachable by design (D091). The
 * watchdog therefore never gets its callback, so nothing writes
 * `ci_hub_statistics` on its own; the hub's own callback endpoint
 * `POST /lab_api/sync_state_sent` (`ci_lab_client.php:1490-1510`) is driven
 * locally instead. Every assertion is on the local ledger and the app's own
 * state, never on a hub response.
 */

// ---------------------------------------------------------------- constants

/** Pool is `ORDER BY lab_dba_init` (LAA…LAH), so index 5 is LAF -- this file's lab. */
const LAF_POOL_INDEX = 5;

/** lab_sec. Live holder of BOTH covid_reporting and form_pcr. */
const OPERATOR = 'e2e_sec';
/** lab_tech. Live holder of ci_resutls_pcr -- the S-STA-02 render actor. */
const TECH = 'e2e_tech';
/** leadlabsecman. No covid_reporting: the page-layer negative control. */
const DENIED = 'e2e_lead5';

/** Hardcoded in the client at `ci-lab-client/api/ci_lab_client.php:17`. */
const HUB_PUBLIC_KEY = 'tinptciq349vbc12xpmaecfj8';

const STA_GROUP = 'E2E STA PCR Panel';
const STA_PANEL = 'E2E STA Covid Analyte';
const STA_ABREV = 'E2ESTA1';
/** Reference data already in the seed: tg_is_covid=1, tg_type='pcr'. */
const COVID_GROUP = 'nCoV-2019';

// ---------------------------------------------------------------- fixtures

/**
 * TENANCY. D241: the lab is resolved from the SPEC FILENAME by fixtures/test.ts
 * (SPEC_LAB), so this file gets its own lab with nothing to pin. The former
 * pool-index override is gone; the fixture gap it worked around is closed.
 */
const sta = e2e;

// ---------------------------------------------------------------- helpers

function ctxFor(baseURL: string | undefined, login: string): Promise<APIRequestContext> {
  return playwrightRequest.newContext({
    baseURL: baseURL ?? 'http://localhost',
    storageState: path.join(STATE_DIR, `${login}.json`),
  });
}

/** The app's own local date -- date_i18n() honours gmt_offset, UTC_DATE() does not. */
/**
 * The app's OWN calendar day, resolved the way `wp_timezone()` resolves it.
 *
 * This used to read `gmt_offset` alone. WordPress stores `gmt_offset` EMPTY
 * whenever `timezone_string` is set (measured here: `timezone_string` =
 * America/New_York, `gmt_offset` = ''), so `COALESCE(MAX(option_value),0)`
 * collapsed to 0 and this returned the UTC day while `date_i18n()` -- which is
 * what every writer in the app stamps rows with -- returned the local one. The
 * two disagree for four hours of every day (20:00-24:00 America/New_York =
 * 00:00-04:00 UTC), and in that window `sta.spec.ts` S-STA-01 failed on
 * `from_date` and `ofc.spec.ts` S-STA-06 rendered "Not found" from a line list
 * filtered on the wrong day. Both failed on a clean tree, at 00:58 UTC.
 *
 * CONVERT_TZ returns NULL for an empty or unknown zone name, so the COALESCE
 * falls through to the historical gmt_offset branch on an install that uses it.
 */
function appToday(db: Db): string {
  return db.scalar(
    `SELECT DATE(COALESCE(
       CONVERT_TZ(UTC_TIMESTAMP(), 'UTC',
         NULLIF((SELECT option_value FROM wp_options WHERE option_name='timezone_string'), '')),
       UTC_TIMESTAMP() + INTERVAL
         COALESCE(NULLIF((SELECT option_value FROM wp_options WHERE option_name='gmt_offset'), ''), 0) HOUR))`,
  );
}

/**
 * Resolve the STA panel reference data and arm the state-reporting option.
 *
 * The rows themselves are SEED data now (env/sql/26-pcr-reference.sql, D238) --
 * this used to clone ci_test_groups/ci_test_panels rows in-file because those
 * tables have many NOT NULL no-default columns. Reference data belongs in the
 * seed tier (D085), so all that is left here is the lookup plus the option
 * write, which is scenario configuration rather than reference data.
 */
function staPanels(db: Db): { tg: string; te: string; covidTg: string; covidTe: string } {
  const [row] = db.query(
    `SELECT (SELECT tg_ID FROM ci_test_groups WHERE tg_name=${Db.q(STA_GROUP)}),
            (SELECT te_ID FROM ci_test_panels WHERE te_name=${Db.q(STA_PANEL)}),
            (SELECT tg_ID FROM ci_test_groups WHERE tg_name=${Db.q(COVID_GROUP)} AND tg_type='pcr'),
            (SELECT te_ID FROM ci_test_panels WHERE te_name=${Db.q(COVID_GROUP)} AND te_type='pcr')`,
  );
  const [tg, te, covidTg, covidTe] = row ?? [];
  expect(tg && te, `${STA_GROUP}/${STA_PANEL} are missing -- run env/seed.sh (sql/26-pcr-reference.sql)`)
    .toBeTruthy();
  expect(covidTg && covidTe, `seed reference data lacks the pcr ${COVID_GROUP} group/panel`).toBeTruthy();

  // The state-reporting screen and the watchdog both key on this option: a
  // configured {tid, state} pair is what makes a specimen reportable.
  db.query(
    `INSERT INTO wp_options (option_name, option_value, autoload)
     VALUES ('ci_send_hub_tests',
             'a:1:{i:0;a:3:{s:3:"tid";s:${te.length}:"${te}";s:5:"state";s:2:"IL";s:5:"btype";s:3:"pcr";}}',
             'yes')
     ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)`,
  );
  return { tg, te, covidTg, covidTe };
}

interface PcrSeed { assen: string; mid: string; reportId: string; patientLast: string }

/**
 * One authenticated POST to the app's own PCR saver builds the whole
 * precondition chain (D085/D099, no nonce -- D043): ci_batches_pcr +
 * ci_reports_pcr + ci_results_pcr, and auto-approves to estatus='C' when every
 * ordered group carries tg_auto_approve_pos_neg and every posted call is
 * POS/NEG (`inc/actions/ci_save_pcr.php:1011-1017`).
 *
 * The action name is `ci_save_form_pcr` (`inc/admin_actions.php:2478`), not
 * `ci_save_pcr` -- that is the include, not the dispatch key.
 */
async function seedPcr(
  req: APIRequestContext,
  db: Db,
  tenant: Tenant,
  patientLast: string,
  opts: {
    groups: string[];            // 'GROUP_<tg>' | '<te>' entries
    results: Record<string, Record<string, string>>;
    assen?: string;              // explicit accession -> exercises the -NN duplicate suffix
    state?: string;
  },
): Promise<PcrSeed> {
  const overrides: Record<string, FormValue> = {
    // The array form: the pcr screens post type_of_specimen as a list.
    'bat[type_of_specimen][]': ['nasal'],
    'bat[panel_tests_requested][]': opts.groups,
    'pa[pa_state]': opts.state ?? 'IL',
  };
  if (opts.assen) overrides['bat[assen]'] = opts.assen;
  for (const [key, vals] of Object.entries(opts.results)) {
    for (const [field, v] of Object.entries(vals)) overrides[`results[${key}][${field}]`] = v;
  }

  const seeded = await seedPcrRequisition(req, db, tenant.labId, patientLast, overrides);
  // Not toBeLessThan(400): a login redirect is 302 and would pass that.
  expect(seeded.status, `PCR saver answered on ${patientLast}`).toBe(200);
  // D221: row-exact on this test's key, never a racy global table count.
  expect(
    seeded.keyRowsAfter - seeded.keyRowsBefore,
    `ci_batches_pcr gained no row for ${seeded.patientName} (D226: the verb is ci_save_form_pcr)`,
  ).toBeGreaterThan(0);

  const patientName = seeded.patientName;
  const rows = db.query(
    `SELECT ba.mid, ba.assen, ba.lab_ID, ba.lab, COALESCE(re.id,'')
       FROM ci_batches_pcr ba
       LEFT JOIN ci_reports_pcr re ON re.patient_id = ba.assen
      WHERE ba.patient_name = ${Db.q(patientName)}`,
  );
  expect(rows.length, `expected exactly one ci_batches_pcr row for ${patientName}`).toBe(1);
  const [mid, assen, labId, lab, reportId] = rows[0];

  // D160: the saver answers 200 with assen='' when the minter fails.
  expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
  // TENANCY, asserted, never assumed (D172/D161).
  expect(labId, `ci_batches_pcr.lab_ID for ${assen}`).toBe(tenant.labId);
  expect(lab, 'ci_batches_pcr.lab').toBe(tenant.prefix);
  expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(tenant.prefix);

  return { assen, mid, reportId, patientLast };
}

/**
 * The hub's callback into this lab. Egress is denied (D091), so the hub can
 * never make this call itself; driving it locally is the only way to reach
 * the ledger write at `ci_lab_client.php:1500`.
 */
async function hubSyncStateSent(
  baseURL: string | undefined, db: Db, entries: Record<string, string>,
): Promise<{ status: number; list: string[] }> {
  const opt = db.scalar(`SELECT option_value FROM wp_options WHERE option_name='ci_lab_client_st'`);
  const key = /s:3:"key";s:\d+:"([^"]+)"/.exec(opt)?.[1];
  expect(key, 'ci_lab_client_st carries no lab key -- check_key() would refuse').toBeTruthy();

  const ctx = await playwrightRequest.newContext({ baseURL: baseURL ?? 'http://localhost' });
  const body = new URLSearchParams();
  for (const [assen, when] of Object.entries(entries)) body.append(`assens_covid[${assen}]`, when);
  const res = await ctx.post('/lab_api/sync_state_sent', {
    headers: {
      'pub-key': HUB_PUBLIC_KEY,
      'api-key': key as string,
      'content-type': 'application/x-www-form-urlencoded',
    },
    data: body.toString(),
  });
  const status = res.status();
  const json = JSON.parse(await res.text()) as { list?: string[] };
  await ctx.dispose();
  return { status, list: json.list ?? [] };
}

/**
 * The COVID call AS DISPLAYED, read off the results screen the render
 * materialises it on.
 *
 * `parts/pcr/results_edit.php:1351-1358` renders the interpreted call as the
 * SELECTED option of the group's `pos_neg` control, and the covid group labels
 * its own options "Detected" / "Not Detected". Returned as `CODE=Label` so an
 * inverted call, a relabelled option and a raw Ct in the call cell are
 * distinct failures. Sliced to the covid group's table first, because every
 * ordered group renders a `pos_neg` control of its own. '' when the group
 * renders no call at all -- which is what a PEN specimen does.
 */
function displayedCovidCall(body: string, groupName: string): string {
  const start = body.indexOf(`>${groupName}</h4>`);
  if (start < 0) return '';
  const table = body.slice(start, body.indexOf('</table>', start));
  const m = /<option value="([A-Z]*)" selected="selected">([^<]*)<\/option>/.exec(table);
  return m ? `${m[1]}=${m[2]}` : '';
}

/** Pull the CSV out of the export response. state_report.php calls ob_clean()
 *  after the admin header has already been flushed, so the CSV is appended to
 *  the page rather than replacing it -- a real product wart, not a harness one. */
function csvRows(body: string): string[] {
  const i = body.indexOf('Accession,');
  expect(i, 'export response carries no CSV header row').toBeGreaterThan(-1);
  return body.slice(i).split('\n').slice(1).map((l) => l.trim()).filter(Boolean);
}

// ---------------------------------------------------------------- S-STA-01

sta.describe('S-STA-01 @sta', () => {
  /**
   * SERIAL, and the reason is measured. Both tests below export the SAME report
   * type over the SAME window as the SAME user, so the `download_report` rows
   * they write are byte-identical -- `data.file_name` included. Nothing in the
   * row distinguishes them, so each test's `id > logHigh AND
   * user_action='download_report'` window can only be made exact by keeping the
   * two exports from overlapping. Measured on this stack with
   * `fullyParallel: true`: `--workers=2`, `specs/sta.spec.ts` alone, the u-test
   * failed 2 of 5 runs with `Expected: 1 / Received: 2` -- it always loses the
   * race, because its window spans `pageAs` -> `goto` -> `waitForResponse` ->
   * two clicks while the HTTP twin's is one `req.get`.
   *
   * `toBe(1)` is NOT relaxed to `toBeGreaterThanOrEqual(1)`: `audit[0]` would
   * then be an arbitrary row and the four assertions hanging off it (`user_id`,
   * `type`, `from_date`, `to_date`, `file_name`) would all be unmoored.
   *
   * Serialising this pair is sufficient and minimal -- `grep -rn
   * "download_report" tests/e2e/` matches these two tests and nothing else.
   */
  // `default`, not `serial`: both keep this pair on one worker in declaration order,
  // which is what the audit-row `toBe(1)` above needs. Only `serial` also abandons
  // the second test when the first goes red, and neither depends on the other.
  sta.describe.configure({ mode: 'default' });

  sta('the state-reporting export is auditable and carries only approved rows', async ({
    db, tenant, runKey, baseURL,
  }) => {
    const p = staPanels(db);
    const today = appToday(db);
    const req = await ctxFor(baseURL, OPERATOR);

    // Approved: the STA group alone carries tg_auto_approve_pos_neg, and the
    // single posted call is NEG, so the saver approves -> estatus='C'.
    const approved = await seedPcr(req, db, tenant, `Staok${runKey}`, {
      groups: [`GROUP_${p.tg}`, p.te],
      results: { [`${p.te}_${STA_ABREV}_${p.tg}_`]: { 'pos-neg': 'NEG' } },
    });
    // Not approved: adding the nCoV-2019 group (tg_auto_approve_pos_neg=0)
    // turns auto-approval off. Same reportable tid, same clinic, same window --
    // estatus is the only difference between the two specimens.
    const pending = await seedPcr(req, db, tenant, `Stapend${runKey}`, {
      groups: [`GROUP_${p.tg}`, `GROUP_${p.covidTg}`, p.te],
      results: { [`${p.te}_${STA_ABREV}_${p.tg}_`]: { 'pos-neg': 'NEG' } },
    });

    const estatus = db.query(
      `SELECT patient_id, COALESCE(estatus,'') FROM ci_reports_pcr
        WHERE patient_id IN (${Db.q(approved.assen)}, ${Db.q(pending.assen)})`,
    );
    const byAssen = new Map(estatus.map(([id, st]) => [id, st] as const));
    expect(byAssen.get(approved.assen), 'approved specimen estatus').toBe('C');
    expect(byAssen.get(pending.assen), 'pending specimen estatus').not.toBe('C');

    const opId = db.scalar(`SELECT ID FROM wp_users WHERE user_login=${Db.q(OPERATOR)}`);
    const logHigh = db.scalar(`SELECT COALESCE(MAX(id),0) FROM ci_log_users`);

    const res = await req.get(
      `/wp-admin/admin.php?page=ci_reports_state_report&from=${today}&to=${today}&act=export`,
    );
    expect(res.status(), 'export request').toBe(200);
    const rows = csvRows(await res.text());

    // The catalogue's business outcome: only estatus='C' is exported.
    const exported = rows.map((l) => l.split(',')[0]);
    expect(exported, 'the approved specimen is in the line list').toContain(approved.assen);
    expect(exported, 'a non-approved specimen must never be exported').not.toContain(pending.assen);

    // …and the LINE, not just its first column. This file goes to a public
    // health department, so a correct accession carrying the wrong patient,
    // the wrong DOB or the wrong state is the defect that matters.
    // tr_state_report_csv_headers() (parts/reports/state_report.php:637-653) is
    // [Accession, First Name, Last Name, Birthday, Age, Sex, Address, City,
    //  Zip, State, …]. A plain split is exact for columns 0-9 because no SEEDED
    // value in that range carries a comma OR A SPACE -- PHP's fputcsv() quotes
    // on both, and the space half of that rule is why `seed-domain.ts` posts
    // `3-Test-Way` rather than `3 Test Way` (measured: the latter leaves the
    // file as `"3 Test Way"`).
    const line = rows.find((l) => l.startsWith(`${approved.assen},`));
    expect(line, `the export carries no line for ${approved.assen}`).toBeTruthy();
    const cols = (line as string).split(',');
    expect(cols[1], 'CSV column 1 -- First Name').toBe('Synthetic');
    expect(cols[2], 'CSV column 2 -- Last Name').toBe(approved.patientLast);
    expect(cols[3], 'CSV column 3 -- Birthday').toBe('1950-01-01');
    expect(cols[9], 'CSV column 9 -- State, the reporting jurisdiction').toBe('IL');

    // …and the export is audited. Keyed on the new row's id, not on a LIKE.
    const audit = db.query(
      `SELECT user_id,
              JSON_UNQUOTE(JSON_EXTRACT(data,'$.type')),
              JSON_UNQUOTE(JSON_EXTRACT(data,'$.from_date')),
              JSON_UNQUOTE(JSON_EXTRACT(data,'$.to_date'))
         FROM ci_log_users
        WHERE id > ${logHigh} AND user_action='download_report'`,
    );
    expect(audit.length, 'the export wrote exactly one download_report audit row').toBe(1);
    expect(audit[0][0], 'ci_log_users.user_id').toBe(opId);
    expect(audit[0][1], 'ci_log_users.data.type').toBe('State Report');
    expect(audit[0][2], 'ci_log_users.data.from_date').toBe(today);
    expect(audit[0][3], 'ci_log_users.data.to_date').toBe(today);

    await req.dispose();
  });

// ------------------------------------------------------------- S-STA-01u

/**
 * S-STA-01u -- S-STA-01 AS THE REPORTER PERFORMS IT.
 *
 * The reporter opens State Reporting, reads the line list, picks
 * `Export Report` -> `Download CSV`, and the browser saves a file. Every
 * S-STA-01 database assertion is kept; the screen, the saved file and the
 * audit row are added, and the three are cross-checked against each other.
 *
 * D382 -- WHY S-STA-01 IS KEPT AND NOT REPLACED. The two entry points reach
 * the same `act=export` branch and return DIFFERENT RESPONSES, because the
 * screen's own JS appends `trajax=1` before it submits
 * (`parts/reports/state_report.php:1805-1811`). Measured on this stack,
 * same cookies, same from/to, one seeded row:
 *
 *   act=export             38 904 bytes  text/html   no Content-Disposition,
 *                                        the CSV glued on at offset 38 620
 *   act=export&trajax=1       284 bytes  text/csv    attachment; filename=...
 *
 * So the URL an operator can type or bookmark hands back a PAGE, and only the
 * screen's own control hands back a FILE. Deleting S-STA-01 to "convert" it
 * would retire the 38 kB variant -- the one whose `header()` calls are no-ops
 * because the admin chrome already flushed -- while the report still claimed
 * the scenario was covered. `csvRows()` exists for exactly that variant and
 * stays with it; this test never calls it, because a real download needs no
 * `ob_clean` workaround.
 *
 * D386 -- WHERE THE RENDERED READ-BACK COMES FROM. It comes from the `goto`,
 * never from a click. Note that this screen's line list is ALWAYS fetched by
 * XHR: `$should_load_report_data` (`state_report.php:258-261`) is false for a
 * plain page load, so the server-rendered `<tbody>` says "No Results Found."
 * and the rows arrive from the `trajax=loadData` handler at `:954`, which is
 * the same PHP running the same query and answering `wp_send_json`. The JS at
 * `:1556` only formats that payload -- it cannot invent a row the server did
 * not send -- and a `page.reload()` replays exactly this pair. The test waits
 * on that response rather than on a timeout, so the assertion cannot grade an
 * empty table.
 *
 * DOWNLOAD SAFETY. The CSV is patient-shaped by construction, so:
 *  (a) `saveAs()` is deliberately NOT called -- it COPIES rather than moves,
 *      so choosing a "safer" destination would leave TWO files where there was
 *      one. The file stays where Playwright puts it,
 *      `tests/e2e/test-results/.playwright-artifacts-<worker>/<uuid>`: no
 *      extension, no name, inside the checkout but ignored by
 *      `tests/e2e/.gitignore:2` (so it cannot be committed, and the teardown's
 *      git-dirt check would not see it either) and served to nobody -- apache
 *      answers 403 for anything under `/tests/`, measured, which is the same
 *      deny `env/safety-gate.sh` check 5 pins on `/tests/e2e/env/wp-config.php`.
 *      Nothing here is visible to the safety gate at all: that script reads the
 *      DATABASE, and MUST_BE_ZERO is a production-dump detector on tables this
 *      test does not touch. It needs no widening and must not get any.
 *  (b) that landing spot is `tests/e2e/test-results/`, which IS the path
 *      `.github/workflows/e2e.yml` uploads on failure (D063: no redaction in
 *      1.62.1, capture off, ONE DAY retention, none of which is to be
 *      relaxed). So the file is deleted here rather than left to Playwright's
 *      own artifacts cleanup, and the deletion is ASSERTED below.
 *  (c) `download.delete()` unlinks that one UUID inside this worker's own
 *      artifacts directory. No glob, no directory removal, nothing another
 *      worker can be holding -- and the `finally` runs even if the read
 *      throws.
 */
  sta('S-STA-01u the reporter downloads the state file and the screen agrees with it', async ({
    db, tenant, runKey, baseURL, pageAs,
  }) => {
    const p = staPanels(db);
    // D390: the app stamps rows with date_i18n(), and the CSV filename carries
    // the same day. A UTC day is wrong for four hours out of every 24 here.
    const today = appToday(db);
    const req = await ctxFor(baseURL, OPERATOR);

    // Same precondition pair as S-STA-01: identical reportable tid, clinic and
    // window; estatus is the only difference between the two specimens.
    const approved = await seedPcr(req, db, tenant, `Staokui${runKey}`, {
      groups: [`GROUP_${p.tg}`, p.te],
      results: { [`${p.te}_${STA_ABREV}_${p.tg}_`]: { 'pos-neg': 'NEG' } },
    });
    const pending = await seedPcr(req, db, tenant, `Stapendui${runKey}`, {
      groups: [`GROUP_${p.tg}`, `GROUP_${p.covidTg}`, p.te],
      results: { [`${p.te}_${STA_ABREV}_${p.tg}_`]: { 'pos-neg': 'NEG' } },
    });
    await req.dispose();

    const byAssen = new Map(db.query(
      `SELECT patient_id, COALESCE(estatus,'') FROM ci_reports_pcr
        WHERE patient_id IN (${Db.q(approved.assen)}, ${Db.q(pending.assen)})`,
    ).map(([id, st]) => [id, st] as const));
    expect(byAssen.get(approved.assen), 'approved specimen estatus').toBe('C');
    expect(byAssen.get(pending.assen), 'pending specimen estatus').not.toBe('C');

    const opId = db.scalar(`SELECT ID FROM wp_users WHERE user_login=${Db.q(OPERATOR)}`);
    const logHigh = db.scalar(`SELECT COALESCE(MAX(id),0) FROM ci_log_users`);

    // ---------------------------------------------------------- the screen
    const page = await pageAs(OPERATOR);
    const listed = page.waitForResponse((r) => r.url().includes('trajax=loadData'));
    await page.goto(
      `/wp-admin/admin.php?page=ci_reports_state_report&from=${today}&to=${today}`,
    );
    await listed;

    // The Reportable Tests table is the one with an Accession column; the
    // summary table above it has six columns and no accessions.
    const lineList = page.getByRole('table')
      .filter({ has: page.getByRole('columnheader', { name: 'Accession' }) });
    const onScreen = lineList.getByRole('row').filter({ hasText: approved.assen });
    await expect(onScreen, 'the reporter sees exactly one line for the approved specimen')
      .toHaveCount(1);
    // The screen twin of "a non-approved specimen must never be exported": the
    // reporter is not shown it either, so it cannot be chased or force-sent.
    await expect(
      lineList.getByRole('row').filter({ hasText: pending.assen }),
      'a specimen that is not approved is not on the reporter\'s line list',
    ).toHaveCount(0);
    // Nothing has been sent to the state yet, so the last cell offers the
    // manual escape hatch rather than a "Sent to IL ..." stamp.
    await expect(
      onScreen.getByRole('button', { name: 'Force Send' }),
      'an un-uploaded specimen offers the reporter Force Send',
    ).toBeVisible();

    const screenCells = (await onScreen.getByRole('cell').allTextContents())
      .map((c) => c.trim());
    const screenColumns = await lineList.getByRole('columnheader').count();

    // ------------------------------------------------------- the download
    const downloading = page.waitForEvent('download');
    await page.getByRole('button', { name: /Export Report/ }).click();
    await page.getByRole('menuitem', { name: 'Download CSV' }).click();
    const download = await downloading;

    // generate_report_filename() (`inc/functions.php:5677`) is
    // <lab init>-RPT-StateReport-<Ymd>, and drops the to-date when it equals
    // the from-date. The init is the OPERATOR's own lab, resolved through
    // CI_Base_Lab::get_current_lab_init() from wp_usermeta._lab_id -- NOT the
    // lab the exported rows belong to. Measured here: the file is named E2E-*
    // while every line in it is an LAF accession, so the name of a file going
    // to a health department does not identify the lab that produced it. The
    // assertion pins today's behaviour; it is not an endorsement of it.
    const opLabInit = db.scalar(
      `SELECT SUBSTRING(lab_dba_init,1,3) FROM ci_labs WHERE lab_ID =
         (SELECT meta_value FROM wp_usermeta
           WHERE user_id = ${Db.q(opId)} AND meta_key = '_lab_id')`,
    );
    // (b)/(c): read it, then unlink it -- see the header. The path is captured
    // BEFORE the delete because path() throws once the file is gone.
    //
    // THE DELETE COMES BEFORE EVERY ASSERTION ABOUT THE FILE, deliberately.
    // The filename assertion below is the one this test expects to go red on a
    // D390/UTC regression or a change to generate_report_filename(), and a
    // throw above the `finally` would leave the patient-shaped CSV sitting in
    // `tests/e2e/test-results/`, which is verbatim the `path:` of the
    // upload-artifact step (`.github/workflows/e2e.yml:234`) -- i.e. the one
    // branch that leaves the file behind would also be the branch that uploads
    // it. `suggestedFilename()` is a plain string and stays valid after
    // `delete()`.
    const landed = await download.path();
    let csv: string;
    try {
      csv = fs.readFileSync(landed, 'utf8');
    } finally {
      await download.delete();
    }
    expect(fs.existsSync(landed), 'the saved CSV was removed from tests/e2e/test-results/')
      .toBe(false);

    const expectedName = `${opLabInit}-RPT-StateReport-${today.replace(/-/g, '')}.csv`;
    expect(download.suggestedFilename(), 'the name the file is saved under')
      .toBe(expectedName);

    // THE WHOLE POINT OF A REAL DOWNLOAD: the operator's file is a CSV and
    // nothing else. The typed-URL variant answers 38 kB of admin HTML with the
    // CSV appended, which is what csvRows() has to dig through.
    expect(csv.indexOf('Accession,'), 'the saved file begins with the CSV header row')
      .toBe(0);

    const [header, ...rows] = csv.split('\n').map((l) => l.trim()).filter(Boolean);
    // Columns 0-9 carry neither a comma nor a space in any seeded value, so a
    // plain split is exact for that range (fputcsv quotes on BOTH -- see the
    // note in seed-domain.ts). The header row does quote its multi-word labels,
    // which adds no commas, so counting fields on it stays right.
    expect(header.split(',').length,
      'the file has one column per column the reporter sees on screen')
      .toBe(screenColumns);

    const exported = rows.map((l) => l.split(',')[0]);
    expect(exported, 'the approved specimen is in the file the reporter saved')
      .toContain(approved.assen);
    expect(exported, 'a non-approved specimen must never leave the lab')
      .not.toContain(pending.assen);

    const line = rows.find((l) => l.startsWith(`${approved.assen},`)) as string;
    expect(line, `the export carries no line for ${approved.assen}`).toBeTruthy();
    const cols = line.split(',');
    expect(cols[1], 'CSV column 1 -- First Name').toBe('Synthetic');
    expect(cols[2], 'CSV column 2 -- Last Name').toBe(approved.patientLast);
    expect(cols[3], 'CSV column 3 -- Birthday').toBe('1950-01-01');
    expect(cols[9], 'CSV column 9 -- State, the reporting jurisdiction').toBe('IL');
    // The screen's twin of the last column, which the reporter reads as the
    // Force Send button asserted above.
    expect(line.endsWith(',-'),
      `the file records ${approved.assen} as not yet uploaded: ${line}`).toBe(true);

    // THE FILE AND THE SCREEN ARE THE SAME FACT. Either alone can be right
    // while the other is wrong -- a state department chasing a patient the
    // reporter cannot see on screen, or a reporter signing off a line the file
    // does not carry.
    expect(screenCells.slice(0, 10),
      'every column the reporter reads on screen is the column the file carries')
      .toEqual(cols.slice(0, 10));

    // ---------------------------------------------------------- the audit
    const audit = db.query(
      `SELECT user_id,
              JSON_UNQUOTE(JSON_EXTRACT(data,'$.type')),
              JSON_UNQUOTE(JSON_EXTRACT(data,'$.from_date')),
              JSON_UNQUOTE(JSON_EXTRACT(data,'$.to_date')),
              JSON_UNQUOTE(JSON_EXTRACT(data,'$.file_name'))
         FROM ci_log_users
        WHERE id > ${logHigh} AND user_action='download_report'`,
    );
    expect(audit.length, 'the download wrote exactly one download_report audit row').toBe(1);
    expect(audit[0][0], 'ci_log_users.user_id -- the reporter who clicked').toBe(opId);
    expect(audit[0][1], 'ci_log_users.data.type').toBe('State Report');
    expect(audit[0][2], 'ci_log_users.data.from_date').toBe(today);
    expect(audit[0][3], 'ci_log_users.data.to_date').toBe(today);
    // The audit trail names the file that is now on the reporter's disk. If
    // these two ever disagree, no PHI export can be traced back to its record.
    expect(audit[0][4], 'ci_log_users.data.file_name is the file the browser saved')
      .toBe(download.suggestedFilename());
  });
});

sta.describe('State Reporting page-layer capability gate @sta', () => {
  // D105/D175: page-layer denials ARE enforced, so these are ordinary passing
  // assertions. D185: the denial is HTTP 200 with a denial BODY, never a 403 --
  // asserting the status code would pass for the wrong reason.
  //
  // WAVE 2 / D388. These two used to be regexes over raw HTML fetched by an
  // APIRequestContext. The plan supersedes that layer explicitly for the page
  // gates ("each becomes two rendered facts instead of two regexes"), and there
  // is no weaker surface to lose here: the browser issues the SAME GET against
  // the SAME handler with the SAME cookies, so no D382 twin is retained.
  //
  // The navigation comes FIRST, always. `pageAs()` hands back an about:blank
  // page, where toHaveCount(0) is true for ANY product behaviour -- asserting
  // the denied branch before navigating makes the negative case unfalsifiable.
  // One boolean-driven assertion per fact, never a denied/allowed branch pair.
  //
  // D250 -- menu registration is the ONLY gate on this screen, so the offer and
  // the URL are TWO different facts and both are asserted: what the operator is
  // offered, and what happens when a denied operator types the URL anyway.
  //
  // This is also the regression guard on the persona choice at the top of this
  // file: the day leadlabsecman gains covid_reporting, this goes red.
  //
  // Measured on this stack 2026-08-25 -- e2e_sec: menu link 1, heading and
  // Export Report present, denial 0. e2e_lead5: menu link 0, denial 1,
  // #adminmenu 0 (wp_die renders no admin chrome at all).
  for (const [login, allowed] of [[DENIED, false], [OPERATOR, true]] as const) {
    sta(`${login} ${allowed ? 'reaches' : 'is denied'} page=ci_reports_state_report`, async ({ pageAs }) => {
      const page = await pageAs(login);

      // FACT 1 -- the offer. The link lives under Dashboard as
      // index.php?page=ci_reports_state_report (form_define.php:753,
      // 'State Reporting'), and Dashboard is the open menu on /wp-admin/.
      await page.goto('/wp-admin/');
      await expect(
        page.locator('#adminmenu').getByRole('link', { name: 'State Reporting' }),
        'the menu link IS the gate (D250) -- the operator is offered the screen',
      ).toHaveCount(allowed ? 1 : 0);

      // FACT 2 -- the URL, typed anyway. An UNREGISTERED screen is the least
      // guarded surface in this app, so "not in the menu" is not evidence that
      // the screen is closed.
      await page.goto('/wp-admin/admin.php?page=ci_reports_state_report');
      // D392 -- BOUNDED ON THE DENIED BRANCH ONLY. The denial page carries a
      // 3 s meta-refresh to page=welcome; measured on this stack, t=0..2 denial
      // 1 / #adminmenu 0, t=3 the fuse fires, t=4+ the URL is page=welcome with
      // denial 0 / #adminmenu 1. The default 5 s expect timeout OUTLIVES the
      // page being asserted, so a slow first poll would grade the Welcome page
      // and report the wrong screen for an authz regression. The ALLOWED branch
      // is deliberately left on the default: this screen self-navigates to its
      // own filtered URL (&from=&to=&date_mode=approved&state=ALL&cli_ID=all)
      // inside 1 s, and a tight bound would buy a flake for nothing.
      const fused = allowed ? {} : { timeout: BEFORE_BOUNCE_MS };
      await expect(
        page.getByText('Sorry, you are not allowed to access this page.'),
        'the operator is stopped with the core denial notice',
      ).toHaveCount(allowed ? 0 : 1, fused);
      await expect(
        page.locator('#adminmenu'),
        'the denial is a bare wp_die page -- no admin chrome to navigate on from',
      ).toHaveCount(allowed ? 1 : 0, fused);
      await expect(
        page.getByRole('heading', { name: 'State Reporting' }),
        'the State Reporting screen itself is on the operator screen',
      ).toHaveCount(allowed ? 1 : 0, fused);
      // The rendered twin of the old /name="from"/ regex, which matched a
      // type=hidden input the operator can never see. This is the control the
      // operator actually came for, and the one S-STA-01 drives.
      await expect(
        page.getByRole('button', { name: /Export Report/ }),
        'the export control is offered to the reporter',
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

// ---------------------------------------------------------------- S-STA-02

sta.describe('S-STA-02 @sta', () => {
  sta('the COVID call is materialized on render, and PEN is never persisted', async ({
    db, tenant, runKey, baseURL,
  }) => {
    const p = staPanels(db);
    const seedReq = await ctxFor(baseURL, OPERATOR);
    const tech = await ctxFor(baseURL, TECH);

    const chan = (drug: string) => `${p.covidTe}_${drug}_${p.covidTg}_`;

    // Channel values present -> trpdd_cal_ncovid19() can compute a call.
    // conc 20 sits under the built-in Ct cutoff of 34 (functions_covid.php:441).
    const called = await seedPcr(seedReq, db, tenant, `Stacall${runKey}`, {
      groups: [`GROUP_${p.tg}`, `GROUP_${p.covidTg}`, p.te, p.covidTe],
      results: {
        [`${p.te}_${STA_ABREV}_${p.tg}_`]: { 'pos-neg': 'NEG' },
        [chan('COVID-19')]: { conc: '20' },
        [chan('IC')]: { conc: '25' },
      },
    });
    // No concentrations at all -> conc is NULL on both channels, the
    // is_null() branch fires and the call is 'PEN'.
    const pen = await seedPcr(seedReq, db, tenant, `Stapen${runKey}`, {
      groups: [`GROUP_${p.tg}`, `GROUP_${p.covidTg}`, p.te, p.covidTe],
      results: {
        [`${p.te}_${STA_ABREV}_${p.tg}_`]: { 'pos-neg': 'NEG' },
        [chan('COVID-19')]: { 'pos-neg': 'INC' },
        [chan('IC')]: { 'pos-neg': 'INC' },
      },
    });

    const covidDrug = db.scalar(
      `SELECT UPPER(te_name) FROM ci_test_panels WHERE te_ID = ${Db.q(p.covidTe)}`,
    );
    const covidRows = (reportId: string) => db.query(
      `SELECT \`pos-neg\` FROM ci_results_pcr
        WHERE report_id = ${Db.q(reportId)} AND drug = ${Db.q(covidDrug)}`,
    );

    // Part A runs on render, not on save: nothing exists before the screen is
    // opened. `parts/pcr/results_edit.php:633` -> trpdd_cal_ncovid19().
    expect(covidRows(called.reportId).length, 'no COVID row exists before render').toBe(0);

    const screen: Record<string, string> = {};
    for (const s of [called, pen]) {
      const r = await tech.get(`/wp-admin/admin.php?page=pcr_results&act=edit&id=${s.reportId}`);
      expect(r.status(), `results screen for ${s.assen}`).toBe(200);
      screen[s.assen] = await r.text();
    }

    // THE CALL, ON THE SCREEN. The column assertion below is a five-value
    // domain check; this is the value the verifier actually reads.
    expect(
      displayedCovidCall(screen[called.assen], COVID_GROUP),
      `${called.assen}: Ct 20 under the cutoff must READ as Detected`,
    ).toBe('POS=Detected');
    expect(
      displayedCovidCall(screen[pen.assen], COVID_GROUP),
      `${pen.assen}: a PEN specimen is neither persisted NOR shown a call`,
    ).toBe('');

    const got = covidRows(called.reportId);
    expect(got.length, `render materialized one ${covidDrug} row on ${called.assen}`).toBe(1);
    expect(['POS', 'NEG', 'RER', 'INV', 'INC'], `${covidDrug} pos-neg`).toContain(got[0][0]);

    // functions_covid.php:129 guards the write with `pos-neg != 'PEN'`.
    expect(covidRows(pen.reportId).length, 'PEN must never be persisted').toBe(0);

    await seedReq.dispose();
    await tech.dispose();
  });
});

// ---------------------------------------------------------------- S-STA-03

sta.describe('S-STA-03 @sta', () => {
  sta('the watchdog reports a specimen exactly once, on the ledger alone', async ({
    db, tenant, runKey, baseURL, stubHttp,
  }) => {
    const p = staPanels(db);
    const req = await ctxFor(baseURL, OPERATOR);

    const s = await seedPcr(req, db, tenant, `Stahub${runKey}`, {
      groups: [`GROUP_${p.tg}`, p.te],
      results: { [`${p.te}_${STA_ABREV}_${p.tg}_`]: { 'pos-neg': 'NEG' } },
    });
    // The watchdog only looks at reports approved more than an hour ago
    // (`check_unsent_state_report.php:70`). The saver stamps approve_date=now,
    // so the clock -- and only the clock -- is moved back. This is a
    // precondition, not the outcome under test.
    db.query(
      `UPDATE ci_reports_pcr SET approve_date = DATE_SUB(approve_date, INTERVAL 2 HOUR)
        WHERE patient_id = ${Db.q(s.assen)}`,
    );
    const ledger = () => db.query(
      `SELECT assen, date_report FROM ci_hub_statistics WHERE assen = ${Db.q(s.assen)}`,
    );
    expect(ledger().length, 'ledger starts empty for this specimen').toBe(0);

    // 1. The watchdog runs and tries to reach the hub. Egress is denied and
    //    that is correct (D091), so nothing is recorded as reported.
    stubHttp.clear();
    runCron('cron_check_unsent_state_report');
    const tried = stubHttp.intercepts().filter((i) => i.url.includes('send_unsent_state_reports'));
    expect(tried.length, 'the watchdog posted the unsent set to the hub').toBe(1);
    expect(tried[0].action, 'the hub is unreachable by design').toBe('refused');
    expect(ledger().length, 'no hub callback -> no ledger row').toBe(0);

    // 2. The hub calls back. That, and only that, writes the ledger.
    const when = db.scalar(`SELECT DATE_FORMAT(NOW(),'%Y-%m-%d %H:%i:%s')`);
    const ack = await hubSyncStateSent(baseURL, db, { [s.assen]: when });
    expect(ack.status, 'sync_state_sent').toBe(200);
    expect(ack.list, 'the callback acknowledged this accession').toContain(s.assen);
    const after = ledger();
    expect(after.length, 'exactly one ci_hub_statistics row').toBe(1);
    expect(after[0][1], 'ci_hub_statistics.date_report').toBe(when);

    // 3. Re-running the watchdog produces no second row. Presence in
    //    ci_hub_statistics is the sole "already reported" source of truth.
    runCron('cron_check_unsent_state_report');
    runCron('cron_check_unsent_state_report');
    const twice = ledger();
    expect(twice.length, 're-running the watchdog must not add a second ledger row').toBe(1);
    expect(twice[0][1], 'date_report is unchanged by the re-runs').toBe(when);

    await req.dispose();
  });
});

// ---------------------------------------------------------------- S-STA-04

sta.fixme(
  'S-STA-04 @sta the state CSV drop only happens outside the working window',
  // Unreachable without collateral: the channel is gated on `e_export_csvsftp`
  // and `export_csv_folder` INSIDE the shared `ci_pdf_data` option, whose
  // read-modify-write races the other workers and whose barcode_key /
  // barcode_length drive every worker's accession minting; the "a file was
  // written" half additionally needs $_GET['today'], which a do_action cron
  // trigger cannot supply; and the catalogue records no DB ledger for the drop.
  async () => {},
);

// ---------------------------------------------------------------- S-STA-05

sta.describe('S-STA-05 @sta', () => {
  sta('an accession longer than 12 characters can never be recorded as reported', async ({
    db, tenant, runKey, baseURL,
  }) => {
    const p = staPanels(db);
    const req = await ctxFor(baseURL, OPERATOR);
    const order = {
      groups: [`GROUP_${p.tg}`, p.te],
      results: { [`${p.te}_${STA_ABREV}_${p.tg}_`]: { 'pos-neg': 'NEG' } },
      // A state the option does NOT configure, so these two specimens can never
      // enter another test's unsent set.
      state: 'CA',
    };

    // Control: an app-minted accession is inside varchar(12).
    const short = await seedPcr(req, db, tenant, `Stashort${runKey}`, order);
    expect(short.assen.length, `${short.assen} is within the ledger column width`)
      .toBeLessThanOrEqual(12);

    // Re-posting the same accession makes the saver append `-NN`
    // (`ci_save_req.php:246-258`), which pushes it past 12 characters.
    const long = await seedPcr(req, db, tenant, `Stalong${runKey}`, {
      ...order, assen: short.assen,
    });
    expect(long.assen, 'the duplicate suffix was applied').toBe(`${short.assen}-01`);
    expect(long.assen.length, 'the accession now exceeds ci_hub_statistics.assen varchar(12)')
      .toBeGreaterThan(12);

    const when = db.scalar(`SELECT DATE_FORMAT(NOW(),'%Y-%m-%d %H:%i:%s')`);
    const ack = await hubSyncStateSent(baseURL, db, {
      [short.assen]: when, [long.assen]: when,
    });

    // THE DEFECT. The endpoint answers 200 and reports success for both, but
    // acknowledges only the short one: `$wpdb->insert` of the 13-character
    // accession is rejected by the column (ERROR 1406 under
    // STRICT_TRANS_TABLES), `request_sync_state_sent` swallows the failure
    // (`ci_lab_client.php:1500-1508`), and its `where assen = %s` fallback
    // cannot match either.
    expect(ack.status, 'sync_state_sent').toBe(200);
    expect(ack.list, 'the short accession is acknowledged').toContain(short.assen);
    expect(ack.list, 'the long accession is silently dropped, at HTTP 200 success:1')
      .not.toContain(long.assen);

    const stored = db.query(
      `SELECT assen FROM ci_hub_statistics
        WHERE assen IN (${Db.q(short.assen)}, ${Db.q(long.assen)}, ${Db.q(long.assen.slice(0, 12))})`,
    ).map((r) => r[0]);
    expect(stored, 'the short accession is recorded').toContain(short.assen);
    // Not truncated (the catalogue's first branch) and not collided (its
    // second): under STRICT_TRANS_TABLES the row simply never exists, so the
    // specimen stays "unreported" forever and the watchdog re-posts it on
    // every hourly run.
    expect(stored, 'no truncated ledger row was written either')
      .not.toContain(long.assen.slice(0, 12));
    expect(stored.length, 'only the short accession reached ci_hub_statistics').toBe(1);

    const batchAssen = db.scalar(
      `SELECT assen FROM ci_batches_pcr WHERE mid = ${Db.q(long.mid)}`,
    );
    expect(batchAssen, 'ci_batches_pcr still holds the full accession').toBe(long.assen);

    await req.dispose();
  });
});

// ---------------------------------------------------------------- S-STA-06

sta.fixme(
  'S-STA-06 @sta the state-reporting line list is scoped to the operator role',
  // Unreachable as specified: the page is gated on `covid_reporting`
  // (inc/admin.php:417), and NONE of the five roles the scope map branches on
  // -- doctor, collector, office_manager, multi_site_manager -- holds it live
  // (only administrator, clms, labsecman, lab_sec, administrator_p, AdminCIDW
  // and IT do), so every branch 200-denies before the scope map is reached.
  // The second blocker the catalogue names is also real: ci_clinic holds one
  // clinic, so with the gate lifted the branches would still be vacuous.
  async () => {},
);
