import { expect, type APIRequestContext } from '@playwright/test';
import { Db } from './db';
import type { Tenant } from './tenant';

/** Infrastructure ids seed.php created. Queried, never passed in, so a spec cannot go stale. */
export interface Infra {
  clinicId: string;
  docId: string;
  collectorId: string;
  testGroupId: string;
  /** D204: a SECOND clinic, so multi-clinic tenancy scenarios are not vacuous. */
  clinic2Id: string;
  /** Ordering provider attached to clinic 2. */
  doc2Id: string;
}

export function infra(db: Db): Infra {
  const [row] = db.query(
    `SELECT (SELECT cID    FROM ci_clinic      WHERE cli_clinic='E2E Test Clinic'),
            (SELECT doc_ID FROM ci_doc         WHERE doc_last='Testprovider'),
            (SELECT col_ID FROM ci_collector   WHERE col_last='Testcollector'),
            (SELECT tg_ID  FROM ci_test_groups WHERE tg_name='E2E Orderable Panel'),
            (SELECT cID    FROM ci_clinic      WHERE cli_clinic='E2E Test Clinic Two'),
            (SELECT doc_ID FROM ci_doc         WHERE doc_last='Testprovidertwo')`,
  );
  const [clinicId, docId, collectorId, testGroupId, clinic2Id, doc2Id] = row ?? [];
  if (!clinicId || !docId || !collectorId || !testGroupId || !clinic2Id || !doc2Id) {
    throw new Error(`seed.php infrastructure missing: ${JSON.stringify(row)}`);
  }
  return { clinicId, docId, collectorId, testGroupId, clinic2Id, doc2Id };
}

/**
 * PATIENT NAMES REAL PATIENTS HAVE -- the data that exercises failure mode 7.
 *
 * 170 of the 245 tables are `latin1_swedish_ci`, and every PHI table is among
 * them: `ci_patients.pa_address`, `pa_city`, `pa_first` and `pa_last` are all
 * `latin1` on this stack (read from information_schema 2026-08-26). The
 * connection is utf8mb4, so the server DOWN-CONVERTS on INSERT and substitutes
 * `?` for every character latin1 cannot hold -- and `wpdb::strip_invalid_text()`
 * short-circuits on a latin1 column ("latin1 can store any byte sequence"), so
 * nothing in PHP objects. The row is written, `insert()` returns 1, the saver
 * reports success, and the stored name is wrong. The other six failure modes
 * are all "nothing was written"; this one is "the wrong thing was written",
 * which no row-count or HTTP assertion can see.
 *
 * Measured through the same connection the app uses:
 *
 *   CONVERT(CONVERT('Иван-Way'   USING latin1) USING utf8mb4) -> '????-Way'
 *   CONVERT(CONVERT('Nguyễn-Way' USING latin1) USING utf8mb4) -> 'Nguy?n-Way'
 *   CONVERT(CONVERT('José-City'  USING latin1) USING utf8mb4) -> 'José-City'
 *   CONVERT(CONVERT("O'Brien"    USING latin1) USING utf8mb4) -> "O'Brien"
 *
 * WHICH IS WHY THE LAST TWO ARE HERE. `José` is NOT a mode-7 probe -- é is
 * latin1 0xE9 and survives intact -- and `O'Brien` is plain ASCII. They are the
 * CONTROLS: a test that only ever posts `Иван` and watches it break cannot
 * distinguish "the column charset mangles it" from "the form rejects anything
 * non-ASCII" or "the saver dropped the field". Post all of them in one
 * document, assert the controls UNPINNED, and the surviving values prove the
 * request, the validator and the saver are all fine.
 *
 * NEVER IN A LOOKUP KEY. `expectBatch` locates the row by
 * `WHERE patient_name = <the value the form posted>`, so a corrupted name
 * matches nothing and the test reds as "the saver wrote no row" -- mode 7
 * misreported as modes 1-6, which is worse than not testing it. Corrupting
 * bytes go in columns nothing looks a row up by (`pa_address`), and the
 * surname/first name stay ASCII.
 */
export const CHARSET = {
  /** Cyrillic. Not representable in latin1 -- every character becomes `?`. */
  cyrillic: 'Иван-Way',
  /** Vietnamese. `ễ` (U+1EC5) is not in latin1; the ASCII around it survives. */
  vietnamese: 'Nguyễn-Way',
  /** Latin-1 representable. A CONTROL: this one must round-trip intact. */
  latin1Safe: 'José-City',
  /** ASCII with an apostrophe. A CONTROL for quoting, not for charset. */
  apostrophe: "O'Brien",
} as const;

/**
 * A form value. An ARRAY is not a convenience here, it is a correctness
 * requirement: D226 case (4) -- `bat[panel_tests_requested]` must be an ARRAY
 * for blood and pcr, and a scalar silently stores `''` at HTTP 200.
 */
export type FormValue = string | string[];

/**
 * POST an application form, repeating keys where the value is an array.
 * Playwright's `form:` option cannot repeat a key, so the body is built by hand.
 */
function postForm(
  request: APIRequestContext,
  url: string,
  fields: Record<string, FormValue>,
) {
  const body = new URLSearchParams();
  for (const [k, v] of Object.entries(fields)) {
    if (Array.isArray(v)) for (const one of v) body.append(k, one);
    else body.append(k, v);
  }
  return request.post(url, {
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    data: body.toString(),
  });
}

export interface SeedRequisitionResult {
  /** patient surname, the per-test unique key */
  patientLast: string;
  /** `<surname>, Synthetic` -- the exact value the saver writes to patient_name */
  patientName: string;
  status: number;
  /**
   * D221 -- ROW-EXACT, not a global count.
   *
   * This used to be `SELECT COUNT(*) FROM ci_batches` with no WHERE, which is
   * RACY on a shared stack: any sibling worker saving a requisition between the
   * two reads inflates the delta, so D140's `rows gained > 0` invariant -- the
   * suite's primary defence against a vacuous test -- could be satisfied by
   * somebody else's row. Both counts are now scoped to THIS test's patient key,
   * so a positive delta can only mean the saver wrote OUR row. That is also
   * strictly stronger: a saver that writes a row under the wrong patient_name
   * now goes RED instead of green.
   */
  keyRowsBefore: number;
  keyRowsAfter: number;
  /**
   * THE SAVER'S OWN REPLY, verbatim (D426).
   *
   * These savers are FULL-PAGE POST handlers, not XHR endpoints: the response
   * body IS the screen the clerk is looking at when the save completes, and the
   * `.q_message` block in it is composed server-side from the row the app just
   * wrote (`ci_save_form_requisition.php:686` / `:955`, and the same two lines
   * in each sibling saver). So this is a SERVER RENDER, not post-XHR DOM --
   * D386 is satisfied by construction and asserting on it is asserting on what
   * the operator is told.
   *
   * WHY IT IS CARRIED BACK AT ALL, given `expectBatch` reads the database.
   * `expectBatch` opens with the rows-gained precondition, so any fault that
   * stops the row being written aborts the caller BEFORE the caller's own
   * assertion runs -- and the caller's assertion is the one that names the
   * scenario. Measured on `S-ORD-02`: deleting the `-01` duplicate suffix at
   * `ci_save_form_requisition.php:256` makes the second INSERT collide on the
   * UNIQUE `assen` index, so no row is gained, so the test reds on "the saver
   * wrote nothing" and NEVER REACHES the suffix assertion it exists for. Green
   * for the wrong reason is the loud failure mode; red for the wrong reason is
   * the quiet one, and it is how a test comes to be believed for something it
   * has never actually checked.
   *
   * A claim asserted against THIS string is reached whatever the database did,
   * which is what makes it a repair rather than a second copy of the same
   * check. It is never a substitute for the row: assert both.
   */
  body: string;
}

/** Rows carrying this test's exact patient key. Never LIKE -- D146. */
function countKeyRows(db: Db, table: string, patientName: string): number {
  return Number(
    db.scalar(`SELECT COUNT(*) FROM ${table} WHERE patient_name = ${Db.q(patientName)}`),
  );
}

/**
 * FAULT INJECTION HOOK (design §8, D160/R10). Nightly only -- never set in a
 * pre-merge run. `wrong-lab` routes the requisition to a DIFFERENT pool lab,
 * which is the CORRECTNESS fault the Gate-D injection set missed: all three of
 * its faults were write-path, so a wrong-lab regression stayed GREEN. The suite
 * must go RED on this, or the tenancy assertions have rotted.
 */
function routedLabId(db: Db, labId: string): string {
  if (process.env.E2E_FAULT !== 'wrong-lab') return labId;
  return db.scalar(
    `SELECT lab_ID FROM ci_labs
      WHERE lab_dba_init REGEXP '^LA[A-P]$' AND lab_ID <> ${Db.q(labId)}
      ORDER BY lab_dba_init LIMIT 1`,
  );
}

/**
 * Create one tox requisition through the APPLICATION'S OWN SAVER (D085 tier 3,
 * D099). Raw SQL cannot substitute: tr_get_new_assen() plus
 * replace_assen_by_lab() plus replace_assen_by_reflab() plus the `-NN`
 * collision loop are what mint the accession, and the accession is the thing
 * under test.
 *
 * No nonce and no referer are sent, because inc/actions/ci_save_req.php and the
 * tox saver carry neither (D043); an authenticated POST is the whole protocol.
 * Entry path is admin.php?page=tox_req_form, capability form_requisition
 * (D102) -- NOT req_req_form, whose capability form_req no role grants.
 */
export async function seedRequisition(
  request: APIRequestContext,
  db: Db,
  tenant: Tenant,
  patientLast: string,
  overrides: Record<string, FormValue> = {},
): Promise<SeedRequisitionResult> {
  const f = infra(db);
  const patientName = `${patientLast}, Synthetic`;
  const keyRowsBefore = countKeyRows(db, 'ci_batches', patientName);

  const res = await postForm(request, '/wp-admin/admin.php?page=tox_req_form', {
    tr_action: 'ci_save_form_requisition',
    auto_generated: '1',
    'bat[cli_ID]': f.clinicId,
    'bat[doc_ID]': f.docId,
    'bat[col_ID]': f.collectorId,
    'bat[lab_id]': routedLabId(db, tenant.labId),
    'bat[ref_lab_ID]': '0',
    'bat[batch_short]': '0',
    'bat[batch_status]': '',
      // D289: the real form ALWAYS posts this. The default 'Do Not Use Custom
      // Profile' is applied in the RENDER paths (parts/tox/req_form.php:253,
      // parts/tox/short_form.php:50, inc/ci_data_work_xml.php:1360) and NOT in
      // the saver, so a POST that omits it stores NULL -- and the nightly Box
      // and OpiSafe selectors use `custom_profile != 'Use Custom Profile' OR
      // (... AND cp.cus_ID > 0)`, which is NULL-vs-NULL three-valued logic.
      // A NULL therefore makes the specimen SILENTLY invisible to both legs.
      // Omitting it here made every seeded requisition undeliverable and hid a
      // whole class of distribution test.
      'bat[custom_profile]': 'Do Not Use Custom Profile',
    'bat[doc]': 'auto',
    'bat[type_of_specimen]': 'urine',
    'bat[panel_tests_requested]': `group_${f.testGroupId}`,
    'pa[pa_first]': 'Synthetic',
    'pa[pa_last]': patientLast,
    // WAVE 0.4 -- REACHABLE THROUGH THE UI. The DOB input is readonly behind a
    // jQuery-UI datepicker whose year select FLOORS AT 1906, so 1900-01-01 is a
    // value no user can enter and a UI-driven requisition cannot reproduce it.
    // Changing it here changes what sta.spec.ts and por.spec.ts assert; all five
    // sites move together or the suite ships red.
    'pa[pa_dob]': '1950-01-01',
    'pa[pa_gender]': 'F',
    ...overrides,
  });

  return {
    patientLast,
    patientName,
    status: res.status(),
    body: await res.text(),
    keyRowsBefore,
    keyRowsAfter: countKeyRows(db, 'ci_batches', patientName),
  };
}

/**
 * BLOOD requisition, through the app's own saver (D229 -- this was spec-local
 * in tst.spec.ts).
 *
 * `tr_action=ci_save_blood` on page=blood_req_form, capability `form_blood`.
 * `bat[panel_tests_requested][]` MUST be an array: ci_save_blood.php:439-442
 * replaces a scalar with array() and the panel silently stores `''` (D226).
 */
export async function seedBloodRequisition(
  request: APIRequestContext,
  db: Db,
  labId: string,
  patientLast: string,
  overrides: Record<string, FormValue> = {},
): Promise<SeedRequisitionResult> {
  const f = infra(db);
  const patientName = `${patientLast}, Synthetic`;
  const keyRowsBefore = countKeyRows(db, 'ci_batches_blood', patientName);

  const res = await postForm(request, '/wp-admin/admin.php?page=blood_req_form', {
    tr_action: 'ci_save_blood',
    auto_generated: '1',
    patient_signature: '',
    provider_signature: '',
    'bat[cli_ID]': f.clinicId,
    'bat[doc_ID]': f.docId,
    'bat[col_ID]': f.collectorId,
    'bat[lab_id]': routedLabId(db, labId),
    'bat[ref_lab_ID]': '0',
    'bat[batch_short]': '0',
    'bat[batch_status]': '',
      // D289: the real form ALWAYS posts this. The default 'Do Not Use Custom
      // Profile' is applied in the RENDER paths (parts/tox/req_form.php:253,
      // parts/tox/short_form.php:50, inc/ci_data_work_xml.php:1360) and NOT in
      // the saver, so a POST that omits it stores NULL -- and the nightly Box
      // and OpiSafe selectors use `custom_profile != 'Use Custom Profile' OR
      // (... AND cp.cus_ID > 0)`, which is NULL-vs-NULL three-valued logic.
      // A NULL therefore makes the specimen SILENTLY invisible to both legs.
      // Omitting it here made every seeded requisition undeliverable and hid a
      // whole class of distribution test.
      'bat[custom_profile]': 'Do Not Use Custom Profile',
    'bat[doc]': 'auto',
    'bat[type_of_specimen]': 'blood',
    'pa[pa_first]': 'Synthetic',
    'pa[pa_last]': patientLast,
    // WAVE 0.4, same reason as the tox saver above: the datepicker floors at 1906.
    'pa[pa_dob]': '1950-01-01',
    'pa[pa_gender]': 'F',
    ...overrides,
  });

  return {
    patientLast,
    patientName,
    status: res.status(),
    body: await res.text(),
    keyRowsBefore,
    keyRowsAfter: countKeyRows(db, 'ci_batches_blood', patientName),
  };
}

/**
 * PCR requisition, through the app's own saver (D229).
 *
 * THE VERB IS `ci_save_form_pcr`, NOT `ci_save_pcr` (D226 case 3). The latter is
 * the include filename, not the dispatch key: no `tradmin_action_ci_save_pcr`
 * exists, so posting it is a silent no-op at HTTP 200. The real value is what
 * the form itself emits (parts/pcr/req_form.php:1977, inc/admin_actions.php:2478).
 *
 * `bat[panel_tests_requested][]` must be an ARRAY, same reason as blood
 * (D226 case 4).
 *
 * Two keys are mutually exclusive with a default, and both are dropped for you
 * rather than sent twice:
 *   - pass `bat[assen]` to exercise the `-NN` duplicate suffix; that drops
 *     `auto_generated`, which the saver treats as the other branch;
 *   - pass `bat[type_of_specimen][]` (the array form some screens post) and the
 *     scalar `bat[type_of_specimen]` default is dropped, so the saver does not
 *     receive the same field in two shapes.
 */
export async function seedPcrRequisition(
  request: APIRequestContext,
  db: Db,
  labId: string,
  patientLast: string,
  overrides: Record<string, FormValue> = {},
): Promise<SeedRequisitionResult> {
  const f = infra(db);
  const patientName = `${patientLast}, Synthetic`;
  const keyRowsBefore = countKeyRows(db, 'ci_batches_pcr', patientName);

  const fields: Record<string, FormValue> = {
    tr_action: 'ci_save_form_pcr',
    auto_generated: '1',
    // WAVE 0.5 -- WITHOUT THIS THE SPECIMEN IS INVISIBLE ON EVERY PCR SCREEN.
    // `parts/pcr/results_list.php:40` filters on `ba.batch_id > 0` and the saver
    // leaves the column 0 unless the form posts a batch id, so a seeded PCR
    // specimen appeared in no listing for any persona, Administrat0r included.
    // `auto` is what the real form emits; the saver resolves it through
    // tr_pdd_get_current_batch_id() (inc/actions/ci_save_pcr.php:124-127).
    //
    // It does NOT disturb the `verified == '0'` precondition the pcr.spec.ts:569
    // e2e.fail() pin depends on: `verified` is written in exactly two places in
    // the whole plugin (parts/pcr/verification.php:16,29), the saver writes
    // neither, and the listing's own report insert
    // (inc/actions/ci_load_results_pcr.php:850-857) does not name the column, so
    // it lands on the schema default 0. An override still wins, so a spec that
    // needs an explicit batch id keeps it.
    'bat[batch_id]': 'auto',
    'bat[cli_ID]': f.clinicId,
    'bat[doc_ID]': f.docId,
    'bat[col_ID]': f.collectorId,
    'bat[lab_id]': routedLabId(db, labId),
    'bat[ref_lab_ID]': '0',
    'bat[batch_short]': '0',
    'bat[batch_status]': '',
      // D289: the real form ALWAYS posts this. The default 'Do Not Use Custom
      // Profile' is applied in the RENDER paths (parts/tox/req_form.php:253,
      // parts/tox/short_form.php:50, inc/ci_data_work_xml.php:1360) and NOT in
      // the saver, so a POST that omits it stores NULL -- and the nightly Box
      // and OpiSafe selectors use `custom_profile != 'Use Custom Profile' OR
      // (... AND cp.cus_ID > 0)`, which is NULL-vs-NULL three-valued logic.
      // A NULL therefore makes the specimen SILENTLY invisible to both legs.
      // Omitting it here made every seeded requisition undeliverable and hid a
      // whole class of distribution test.
      'bat[custom_profile]': 'Do Not Use Custom Profile',
    'bat[doc]': 'auto',
    'bat[type_of_specimen]': 'Nasopharyngeal Swab',
    'pa[pa_first]': 'Synthetic',
    'pa[pa_last]': patientLast,
    // WAVE 0.4, same reason as the tox saver above: the datepicker floors at 1906.
    'pa[pa_dob]': '1950-01-01',
    'pa[pa_gender]': 'F',
    // The address block, and it is NOT decoration. `sta.spec.ts` cross-checks
    // the state CSV against the reporter's screen column by column, and a
    // column that is empty on BOTH sides cannot fail that check. Measured: with
    // these three left unposted, transposing City and Zip in
    // tr_state_report_write_csv_rows() produced a byte-identical file and both
    // S-STA-01 tests stayed green.
    //
    // NEITHER A COMMA NOR A SPACE in any value: PHP's fputcsv() quotes a field
    // containing either, and the CSV assertions split on ',' then compare the
    // raw field against the unquoted screen cell. Measured on a CORRECT app --
    // '3 Test Way' leaves the file as `"3 Test Way"` and fails the cross-check.
    // env/seed.php:199-202 is the same block for the static patient, which no
    // CSV reads.
    'pa[pa_address]': '3-Test-Way',
    'pa[pa_city]': 'Testville',
    'pa[pa_zip]': '00000',
    ...overrides,
  };
  if (fields['bat[assen]'] !== undefined) delete fields.auto_generated;
  if (fields['bat[type_of_specimen][]'] !== undefined) delete fields['bat[type_of_specimen]'];

  const res = await postForm(request, '/wp-admin/admin.php?page=pcr_req_form', fields);

  return {
    patientLast,
    patientName,
    status: res.status(),
    body: await res.text(),
    keyRowsBefore,
    keyRowsAfter: countKeyRows(db, 'ci_batches_pcr', patientName),
  };
}

// ---------------------------------------------------------------- receiving

/** Receiving lanes, from `ci_receiving_positions` (live rows, read 2026-08-24). */
export const LANES = ['urine', 'oral', 'jvhl', 'jvhlo', 'blood', 'pcr'] as const;
export type Lane = (typeof LANES)[number];

export interface ScanResult {
  status: string;
  re_ID: number | string;
  created_receiving: boolean;
  message?: string;
  existid?: number | string;
}

/**
 * One barcode scan at the Receiving bench (D204/D238 -- this was spec-local in
 * acc.spec.ts; ACC, TST and REV all want it).
 *
 * `tr_action=ci_search_barcode_receiving` (11-ui-surface.md 2.1 ->
 * inc/admin_actions.php:1924). No nonce and no referer (D043); the dispatcher
 * runs on `admin_init` from admin/init.php:59, so an authenticated POST is the
 * whole protocol. `parentdata[...]` mirrors the `data-fname` inputs of the
 * parent package row (pages/admin_ci_receiving_form.php:271-317) -- those key
 * names are ci_receiving column names, and $wpdb->insert() fails on anything
 * else, so this payload cannot drift silently.
 *
 * An empty `parentdata[re_ID]` opens a NEW receiving session; passing the
 * `re_ID` from a previous scan keeps the same session.
 */
export async function scanBarcode(
  request: APIRequestContext,
  tenant: Tenant,
  code: string,
  opts: { lane: Lane; reId?: number | string; today: string },
): Promise<ScanResult> {
  const qty = Object.fromEntries(
    LANES.map((l) => [`parentdata[re_qty_${l}]`, l === opts.lane ? '1' : '0']),
  );
  const res = await request.post('/wp-admin/admin.php?page=ci_receiving', {
    form: {
      tr_action: 'ci_search_barcode_receiving',
      code,
      id: '0',
      ii: '1',
      btype: opts.lane,
      lab_id: tenant.labId,
      exist_positions: '',
      'parentdata[re_today]': opts.today,
      'parentdata[re_ID]': String(opts.reId ?? ''),
      'parentdata[re_tracking_number_barcode]': '',
      'parentdata[re_lab_ID]': tenant.labId,
      'parentdata[re_priority]': '',
      'parentdata[re_timing]': '',
      'parentdata[re_practice]': 'E2E Test Clinic',
      'parentdata[re_collector]': 'Testcollector',
      ...qty,
    },
  });
  // Not `toBeLessThan(400)`: a login redirect is 302 and would pass that.
  expect(res.status(), 'the receiving bench answers 200 on the scan POST').toBe(200);
  const parsed = JSON.parse(await res.text()) as ScanResult & { html?: string };
  delete parsed.html;
  return parsed;
}
