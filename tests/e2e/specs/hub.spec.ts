import { test, expect } from '../fixtures/test';

import { Db } from '../fixtures/db';
import { expectBatch } from '../fixtures/assert-domain';
import { soap, soapField, soapMapField } from '../fixtures/soap';
import { infra } from '../fixtures/seed-domain';

/**
 * HUB -- hub operator (central, personas.md:197-210) and satellite-site
 * operator (personas.md:211-224).
 *
 * TOPOLOGY, measured on this instance, and it decides which half of the family
 * is writable:
 *
 *  - The hub's OWN SOAP handler runs locally. `ci-data-works-arc/inc/init.php:2`
 *    mounts `inc/server.php` when REQUEST_URI starts with `/wsdl`, and
 *    `http://localhost/wsdl?wsdl` answers 200 with the full contract (29
 *    operations, rpc/encoded, `soap:address location="http://localhost/wsdl"`).
 *    So S-HUB-03/04/05/06 -- the authorisation scenarios -- are ordinary local
 *    HTTP and are implemented below.
 *
 *  - The SECOND SITE CANNOT PLAY HUB. Two independent reasons, both asserted by
 *    'the satellite site cannot serve the hub WSDL endpoint' below:
 *      (a) the gate is `strpos($_SERVER['REQUEST_URI'], '/wsdl') === 0` -- it
 *          must START with `/wsdl`. Blog 2 is path-based at `/patients/`
 *          (D090), so `/patients/wsdl?wsdl` never reaches server.php; measured
 *          HTTP 404, WordPress "Page not found", no `wsdl:definitions`.
 *      (b) the domain tables are unprefixed literals -- `ci_batches`,
 *          `ci_reports`, `ci_wsdl_keys` (e.g. `ci_data_work_xml.php:77`). Only
 *          WP core tables carry the `wp_2_` blog prefix. A "hub" and a
 *          "satellite" on this instance would read and write ONE dataset, so
 *          the catalog's hub-side assertion ("hub `ci_batches` row exists for
 *          the pushed assen") would be satisfied by the satellite's own row --
 *          a green test that proves nothing (D141).
 *
 *  - The REAL hub is out of reach and must stay that way.
 *    `ci-lab-client/api/ci_lab_client.php:52` self-registers against
 *    production, sending domain, lab name, IP and CLIA (D016/D091). The
 *    `internal: true` network is the only thing stopping it.
 *
 * Hence S-HUB-01/02/07 are test.fixme() with their reasons, not fabricated
 * passes. Nothing in this file relaxes containment: every request goes to
 * `http://localhost`, and every `ci_wsdl_keys` row it creates carries
 * `wsdl_url=''`, which excludes it from `inc/crons/wsdl_put_req.php:29`
 * (`wsdl_url != ''`), so a key this spec mints can never make any cron open a
 * SOAP connection.
 *
 * The machine channel carries NO WP role (personas.md:236-237): every SOAP call
 * below is made by the anonymous `request` fixture (`main` runs with
 * `storageState: undefined`). The hub operator's authority in these scenarios
 * is the `ci_wsdl_keys` row, not a capability.
 */

/**
 * Mint one hub key. `wsdl_url=''` and a key string built from runKey keep the
 * row inert and collision-free; reset.sh removes it with the rest of the run.
 */
function grantKey(db: Db, key: string, labId: string, wsdlType: string): void {
  db.query(
    `INSERT INTO ci_wsdl_keys (wsdl_url, wsdl_key, wsdl_lab_ID, wsdl_type, wsdl_active, wsdl_start)
     VALUES ('', ${Db.q(key)}, ${Number(labId)}, ${Db.q(wsdlType)}, 1, '2000-01-01')`,
  );
}

/**
 * The btypes the catalog scopes S-HUB-03/04/05 to. `pcr` IS available here --
 * verified live, not inferred: CI_ALL_BATCH_TYPES is `tox, blood, pcr`
 * (env/wp-config.php:58-62), `tr_pdd_get_batch_types()` returns all three, and
 * `tr_pdd_get_table_by_btype('pcr')`/`..._report_by_btype('pcr')` resolve to
 * ci_batches_pcr / ci_reports_pcr.
 *
 * The pcr ORDER-ENTRY screen is a different question and does not gate this
 * file: `pcr_req_form` needs `form_pcr`, which the running instance grants to
 * administrator, AdminCIDW and lab_tech but NOT to `leadlabsecman`, the tenant
 * fixture's persona (checked with get_role() against the live DB, D178). It is
 * irrelevant here because the hub↔satellite channel authenticates a
 * ci_wsdl_keys row, not a WP role (personas.md:236-237) -- so the pcr cases
 * below reach the same handler with no capability involved at all.
 */
const FAMILY = [
  { btype: 'tox', op: 'put_req', batches: 'ci_batches', reports: 'ci_reports' },
  { btype: 'pcr', op: 'put_pcr', batches: 'ci_batches_pcr', reports: 'ci_reports_pcr' },
] as const;

/** The put_* payload shape, trimmed to what import_req actually reads. */
const reqXml = (assen: string, patientLast: string) =>
  `<req><accession_number>${assen}</accession_number>` +
  '<clinic>E2E Test Clinic</clinic><type_of_specimen>urine</type_of_specimen>' +
  `<patient><pa_first>Synthetic</pa_first><pa_last>${patientLast}</pa_last>` +
  '<pa_dob>1900-01-01</pa_dob><pa_gender>F</pa_gender></patient></req>';

/** Accession that provably does not exist, unique per (run, worker, test). */
const probeAssen = (prefix: string, runKey: string, tag: string) =>
  `${prefix}${tag}${runKey}`.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 26);

test.describe('hub WSDL authorisation @hub', () => {
  for (const f of FAMILY) {
    test(`S-HUB-03 (${f.btype}) an invalid wsdl_key writes nothing @smoke`, async ({
      request, db, tenant, runKey,
    }) => {
      // Precondition from the catalog: the hub holds at least one VALID key, and
      // the supplied one is not it. Without the first half, "WRONG_KEY" would
      // only prove the table is empty.
      const valid = `e2ehub3${f.btype}${runKey}`;
      grantKey(db, valid, tenant.labId, '');
      const bad = `${valid}-notakey`;
      const [[validRows, badRows]] = db.query(
        `SELECT (SELECT COUNT(*) FROM ci_wsdl_keys WHERE wsdl_key = ${Db.q(valid)}),
                (SELECT COUNT(*) FROM ci_wsdl_keys WHERE wsdl_key = ${Db.q(bad)})`,
      );
      expect(validRows, 'a valid hub key exists').toBe('1');
      expect(badRows, 'the attacker key is not in ci_wsdl_keys').toBe('0');

      const assen = probeAssen(tenant.prefix, runKey, `H3${f.btype}`);
      const body = await soap(request, f.op, {
        xml: reqXml(assen, `Hubthree${f.btype}${runKey}`),
        key: bad,
      });

      // Both halves. The response alone is not enough -- a handler that answered
      // WRONG_KEY *after* inserting would still satisfy it. That the write path
      // is genuinely live is proved by S-HUB-05 (pcr) below, which reaches the
      // same handler with a valid key and DOES get a row.
      expect(soapField(body, 'status'), `hub answered: ${body.slice(0, 400)}`).toBe('WRONG_KEY');
      expect(
        db.scalar(`SELECT COUNT(*) FROM ${f.batches} WHERE assen = ${Db.q(assen)}`),
        `${f.batches} rows for the attempted accession`,
      ).toBe('0');
    });

    test(`S-HUB-04 (${f.btype}) a key cannot exceed its wsdl_type @full`, async ({
      request, db, tenant, runKey,
    }) => {
      // 'get_orders' is a real granted action; _put_req permits only
      // ('', 'put_req', 'put_req_<btype>', '<btype>') --
      // ci_data_work_server.php:3172-3181. DENY is reachable ONLY past the key
      // check, so it cannot be produced by a malformed call (that answers
      // WRONG_XML) or by an unknown key (WRONG_KEY).
      const key = `e2ehub4${f.btype}${runKey}`;
      grantKey(db, key, tenant.labId, 'get_orders');

      const assen = probeAssen(tenant.prefix, runKey, `H4${f.btype}`);
      const body = await soap(request, f.op, {
        xml: reqXml(assen, `Hubfour${f.btype}${runKey}`),
        key,
      });

      expect(soapField(body, 'status'), `hub answered: ${body.slice(0, 400)}`).toBe('DENY');
      expect(soapField(body, 'msg')).toBe('Not allow');
      expect(
        db.scalar(`SELECT COUNT(*) FROM ${f.batches} WHERE assen = ${Db.q(assen)}`),
        'the target table is unchanged for that accession',
      ).toBe('0');
    });
  }

  test('S-HUB-05 (tox) a replayed order does not overwrite a completed report @full', async ({
    request, db, tenant, runKey, seedRequisition,
  }) => {
    const f = infra(db);
    const patientLast = `Hubfive${runKey}`;
    const seeded = await seedRequisition(patientLast);
    expect(seeded.status, 'the saver answers 200 on the full-document POST').toBe(200);
    const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    // The precondition the scenario is about: the specimen is already resulted.
    db.query(`INSERT INTO ci_reports (patient_id, estatus) VALUES (${Db.q(assen)}, 'C')`);

    const key = `e2ehub5${runKey}`;
    grantKey(db, key, tenant.labId, '');

    // Same accession, DIFFERENT patient -- so an overwrite would be visible.
    const replayLast = `Hubfiveredo${runKey}`;
    const body = await soap(request, 'put_req', { xml: reqXml(assen, replayLast), key });

    // ci_data_work_xml.php:585-592 returns before any write.
    expect(soapField(body, 'status'), `hub answered: ${body.slice(0, 400)}`).toBe('OK');
    expect(soapField(body, 'msg'), 'the hub skipped the re-import').toBe('Req completed');
    expect(soapField(body, 'ID'), 'the hub echoed the existing mid').toBe(mid);

    const [row] = db.query(
      `SELECT (SELECT COUNT(*) FROM ci_batches WHERE assen = ${Db.q(assen)}),
              (SELECT patient_name FROM ci_batches WHERE mid = ${Number(mid)}),
              (SELECT COUNT(*) FROM ci_reports WHERE patient_id = ${Db.q(assen)} AND estatus = 'C'),
              (SELECT COUNT(*) FROM ci_batches
                WHERE patient_name = ${Db.q(`${replayLast}, Synthetic`)})`,
    );
    expect(row[0], 'the order was not re-inserted').toBe('1');
    expect(row[1], 'the existing batch was not overwritten').toBe(`${patientLast}, Synthetic`);
    expect(row[2], 'the completed report is still estatus=C').toBe('1');
    expect(row[3], 'the replayed payload wrote no batch of its own').toBe('0');
  });

  test('S-HUB-05 (pcr) a replayed order does not overwrite a completed report @full', async ({
    request, db, tenant, runKey,
  }) => {
    // The pcr order is created through the HUB'S OWN put_pcr, not through
    // pcr_req_form: the tenant fixture's persona (leadlabsecman) does not hold
    // form_pcr on this instance, and the machine channel needs no WP role at
    // all. This first call therefore doubles as the positive control for
    // S-HUB-03/04 -- it is the same handler with a valid key, and it DOES
    // write, so "zero rows" above is a real constraint and not a vacuous one.
    const key = `e2ehub5pcr${runKey}`;
    grantKey(db, key, tenant.labId, '');

    const assen = probeAssen(tenant.prefix, runKey, 'H5pcr');
    const patientLast = `Hubfivepcr${runKey}`;
    const created = await soap(request, 'put_pcr', { xml: reqXml(assen, patientLast), key });
    expect(soapField(created, 'status'), `hub answered: ${created.slice(0, 400)}`).toBe('OK');

    const [[rows, labId]] = db.query(
      `SELECT COUNT(*), MAX(lab_id) FROM ci_batches_pcr WHERE assen = ${Db.q(assen)}`,
    );
    expect(rows, 'an authorised put_pcr writes the order').toBe('1');
    // TENANCY (D172): the key's wsdl_lab_ID is what routes the order.
    expect(labId, `ci_batches_pcr.lab_id for ${assen}`).toBe(tenant.labId);

    // Now result it, and replay the same accession with a different patient.
    db.query(`INSERT INTO ci_reports_pcr (patient_id, estatus) VALUES (${Db.q(assen)}, 'C')`);
    const replayLast = `Hubfivepcrredo${runKey}`;
    const body = await soap(request, 'put_pcr', { xml: reqXml(assen, replayLast), key });

    expect(soapField(body, 'status')).toBe('OK');
    expect(soapField(body, 'msg'), 'the hub skipped the re-import').toBe('Req completed');

    const [row] = db.query(
      `SELECT (SELECT COUNT(*) FROM ci_batches_pcr WHERE assen = ${Db.q(assen)}),
              (SELECT patient_name FROM ci_batches_pcr WHERE assen = ${Db.q(assen)}),
              (SELECT COUNT(*) FROM ci_reports_pcr
                WHERE patient_id = ${Db.q(assen)} AND estatus = 'C'),
              (SELECT COUNT(*) FROM ci_batches_pcr
                WHERE patient_name = ${Db.q(`${replayLast}, Synthetic`)})`,
    );
    expect(row[0], 'the order was not re-inserted').toBe('1');
    expect(row[1], 'the existing batch was not overwritten').toBe(`${patientLast}, Synthetic`);
    expect(row[2], 'the completed report is still estatus=C').toBe('1');
    expect(row[3], 'the replayed payload wrote no batch of its own').toBe('0');
  });

  test('S-HUB-06 control: a lab-scoped key is accepted by get_req_by_assen @full', async ({
    request, db, tenant, runKey,
  }) => {
    // The honesty guard on the expected-failure below: it proves the key this
    // spec mints is ACCEPTED (not WRONG_KEY, not DENY) and that the operation
    // reports count=0 when there is genuinely nothing to return. Without it, an
    // expected-failure could be "passing" because the setup broke.
    const key = `e2ehub6c${runKey}`;
    grantKey(db, key, tenant.labId, '');
    const missing = probeAssen('ZZZ', runKey, 'H6C');
    expect(db.scalar(`SELECT COUNT(*) FROM ci_batches WHERE assen = ${Db.q(missing)}`)).toBe('0');

    const body = await soap(request, 'get_req_by_assen', { key, accessions: missing });
    expect(soapMapField(body, 'status'), `hub answered: ${body.slice(0, 400)}`).toBe('OK');
    expect(soapMapField(body, 'count')).toBe('0');
  });

  test("S-HUB-06 a key scoped to another lab must not pull this lab's order @full", async ({
    request, db, tenant, runKey, seedRequisition,
  }) => {
    // EXPECTED FAILURE, and the catalog row is wrong, not the app-under-test.
    //
    // 10-scenarios.md:72 asserts "Key A's pull for an assen belonging to lab B
    // returns no order" against get_req_by_assen. The handler
    // (ci_data_work_server.php:1294-1341) builds exactly one predicate --
    // `ba.assen in ('…')` -- and never reads wsdl_lab_ID or
    // ci_wsdl_key_clinics. Measured: a key with wsdl_lab_ID=10 pulled a
    // requisition belonging to lab_ID=7, count=1. get_results_data_by_assen
    // (:2229-2343) is the same. Only the DATE-keyed pulls scope: get_results
    // (:1508-1511) adds `and ba.lab_id = {$lab_id}` and the clinic list.
    //
    // Asserted as the catalog states it, marked as failing, per the D105/D175
    // rule: where the app does not enforce, the test is an expected failure --
    // never "the action succeeds". It turns red the day scoping is added.

    const f = infra(db);
    const seeded = await seedRequisition(`Hubsix${runKey}`);
    expect(seeded.status).toBe(200);
    const { assen } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    const otherLab = db.scalar(
      `SELECT lab_ID FROM ci_labs
        WHERE lab_dba_init REGEXP '^LA[A-P]$' AND lab_ID <> ${Db.q(tenant.labId)}
        ORDER BY lab_dba_init LIMIT 1`,
    );
    expect(otherLab, 'a second pool lab to scope the foreign key to').not.toBe('');

    const key = `e2ehub6${runKey}`;
    grantKey(db, key, otherLab, '');

    const body = await soap(request, 'get_req_by_assen', { key, accessions: assen });
    expect(soapMapField(body, 'status'), `hub answered: ${body.slice(0, 400)}`).toBe('OK');
    expect(
      soapMapField(body, 'count'),
      `a key scoped to lab ${otherLab} pulled ${assen}, which belongs to lab ${tenant.labId}`,
    ).toBe('0');
  });
});

// ===========================================================================
// S-HUB-08 -- the wsdl_type gate, covered as a FAMILY
// ===========================================================================

/**
 * ONE RULE, EIGHTEEN COPIES, ONE OF THEM COVERED.
 *
 * S-HUB-04 above pins `_put_req`'s copy of the `wsdl_type` allow-list
 * (`ci_data_work_server.php:3180`). The same idiom --
 * `!in_array($keyobj['wsdl_type'], array('', ...))` -> `status DENY` --
 * is written EIGHTEEN times in that one class:
 *
 *   :158 :554 :866 :1310 :1369 :1497 :1741 :1880 :2246
 *   :3115 :3180 :3283 :3456 :4073 :4290 :4707 :5044 :5449
 *
 * Neutering any of the other seventeen changed no test. Two of them were
 * mutated to `false` and both survived the whole suite: `get_req_by_assen`
 * (:1310) and `put_result_tox` (:158) -- one read that returns requisitions
 * and one write that imports results.
 *
 * WHY A TABLE AND NOT SEVENTEEN HAND-WRITTEN TESTS. The gates differ only in
 * their allow-list and their operation's arguments, so the loop below is the
 * SHAPE the rule has: adding a copy to the register is one row, and a row that
 * is not registered is not covered. This is the same construction S-HUB-03/04
 * already use over FAMILY.
 *
 * WHY NOT ALL EIGHTEEN ROWS. Because the DENY branch is not the interesting
 * half for most of them, and pinning a branch the shipped configuration never
 * reaches would be sixteen tests that pass for a reason no operator meets. The
 * admin UI's own type list (`pages/admin_ci_wsdl_keys.php:225`) opens with
 * `'' => 'All'` -- the FIRST and default-selected option -- and four in-app
 * writers mint `wsdl_type => ''` outright (`parts/lab/edit_lab_ajax.php:850`,
 * `:1144`, `:1592`, `parts/lab/report_to_state.php:117`). A default key
 * therefore passes all eighteen gates, which is the posture the ACCEPT half of
 * every row below measures. The remaining twelve gates are recorded here and
 * deliberately not driven:
 *
 *   :554 put_result_blood      :866 put_result_pcr      :1369 get_tox
 *   :1497 get_results          :1741 get_results_today  :4073 get_pdf_results
 *   :4707 get_antigen_result   :5044 get_blood_covid_result
 *   :3283 _get_result          :3456 _get_result_data   :5449 _get_result_combined
 *   :3180 _put_req  -- COVERED, by S-HUB-04 above.
 *
 * The three `_get_result*` helpers carry a SECOND term (`&& $btype !=
 * $keyobj['wsdl_type']`) and need a two-dimensional row this table does not
 * have. `get_results`, `get_results_today`, `get_pdf_results` and
 * `_get_result*` all call `trpdd_export_results_pdf()` and base64 the file into
 * the response (`:1561`, `:1807`, `:3362`, `:4192`, `:5649`), so driving them
 * on a shared stack renders PDFs for every report the key can reach -- which is
 * why the lab-scoped read row below is `get_results_data` (`:1880`), the one
 * lab-scoping reader that does no PDF work.
 *
 * EVERY ROW IS DRIVEN TWICE WITH THE SAME ARGUMENTS, and that is the whole
 * design. A DENY assertion on its own is satisfied by an operation that is
 * broken for everybody, by a malformed envelope, or by a key the handler never
 * found. The second call changes ONE variable -- the key's `wsdl_type`, from a
 * restricted value to the shipped default -- and must NOT be denied. So the
 * refusal is attributable to the gate and to nothing else.
 *
 * THE RESTRICTED TYPES ARE ONES AN ADMINISTRATOR CAN ACTUALLY PICK. Every
 * value below appears in `pages/admin_ci_wsdl_keys.php:225-246`; `get_orders`,
 * which S-HUB-04 uses, does not. The scenario is therefore a real one: a key
 * an operator restricted to "Only GET Req by Accession" must not import
 * results, and one restricted to "Only PCR PowerBI Results" must not pull
 * requisitions.
 */
interface GateRow {
  /** SOAP operation name. */
  op: string;
  /** `ci_data_work_server.php` line of the gate this row covers. */
  gate: string;
  /** The allow-list the gate carries, quoted from source. */
  allow: string;
  /** A type the admin UI offers that is NOT in `allow`. */
  restricted: string;
  /** Arguments IN THE ORDER THE PHP SIGNATURE DECLARES THEM (rpc/encoded). */
  args: (key: string, assen: string) => Record<string, string>;
  /** Set where an accepted call could write; the table is asserted untouched. */
  writesTo?: string;
}

/** A date range far enough in the past that every read row matches nothing. */
const DEAD_RANGE = { from: '2000-01-01 00:00:00', to: '2000-01-02 00:00:00' };

const WSDL_TYPE_GATES: GateRow[] = [
  {
    op: 'get_req_by_assen', gate: ':1310', allow: "'', 'getrba'", restricted: 'powerbi',
    args: (key, assen) => ({ key, accessions: assen }),
  },
  {
    op: 'put_result_tox', gate: ':158', allow: "'', 'put_result_tox', 'tox'", restricted: 'getrba',
    args: (key, assen) => ({ xml: `<xml><assen>${assen}</assen><estatus>C</estatus></xml>`, key }),
    writesTo: 'ci_reports',
  },
  {
    op: 'get_results_data_by_assen', gate: ':2246',
    allow: "'', 'get_results_data_by_assen', 'pcr', 'blood', 'req', 'tox'", restricted: 'getrba',
    args: (key, assen) => ({ key, assen }),
  },
  {
    op: 'get_orders', gate: ':3115', allow: "'', 'get_orders'", restricted: 'getrba',
    args: (key) => ({ key, from_ID: '2147483000', limit: '1' }),
  },
  {
    op: 'get_results_data', gate: ':1880', allow: "'', 'get_results_data'", restricted: 'getrba',
    args: (key) => ({
      key, limit: '1', datetime: DEAD_RANGE.from, datetime_to: DEAD_RANGE.to, page: '1',
    }),
  },
  {
    op: 'get_covid_result', gate: ':4290', allow: "'', 'covid', 'pcr', 'acovid'", restricted: 'getrba',
    args: (key) => ({
      key, datetime_from: DEAD_RANGE.from, datetime_to: DEAD_RANGE.to, limit: '1', page: '1',
    }),
  },
];

/**
 * `status` out of either response shape.
 *
 * The handlers are not consistent: `get_req_by_assen` returns a PHP array,
 * which the SOAP layer encodes as a SOAP-ENC Map, while the `objToXML`
 * operations answer a string of XML. Reading only one shape would make a row
 * silently assert `''` against `''`.
 */
const statusOf = (body: string) => soapField(body, 'status') || soapMapField(body, 'status');
const msgOf = (body: string) => soapField(body, 'msg') || soapMapField(body, 'msg');

test.describe('S-HUB-08 wsdl_type gate family @hub @full', () => {
  for (const [i, g] of WSDL_TYPE_GATES.entries()) {
    test(`S-HUB-08 (${g.op}) a key restricted to '${g.restricted}' is refused, a default key is not`, async ({
      request, db, tenant, runKey, seedRequisition,
    }) => {
      // A REAL accession, created through the app's own saver, so the read rows
      // are asking for something that exists and the write row is aimed at a
      // report a successful call would genuinely change. `probeAssen` would
      // make every read trivially empty and the DENY vacuous.
      const f = infra(db);
      // The surname is keyed on runKey + the ROW INDEX, not on the operation
      // name. Two earlier shapes both broke `oneBatch`'s row-exactness (D146):
      // `Hubeight<op><runKey>`.slice(0,30) truncated the run key off the long
      // names, so the key was constant across runs; and a 9-character slice of
      // the operation name collides -- `get_results_data` and
      // `get_results_data_by_assen` both reduce to `getresult`.
      const seeded = await seedRequisition(`Hub8${runKey}g${i}`);
      expect(seeded.status, 'the tox saver answers 200').toBe(200);
      const { assen } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

      const tag = `${runKey}${g.op}`.replace(/[^a-z0-9]/gi, '');
      const restrictedKey = `e2ehub8r${tag}`;
      const defaultKey = `e2ehub8d${tag}`;
      grantKey(db, restrictedKey, tenant.labId, g.restricted);
      grantKey(db, defaultKey, tenant.labId, '');

      // Both keys exist and differ in exactly one column -- so "the handler
      // never found the key" cannot explain the refusal below.
      expect(
        db.query(
          `SELECT wsdl_key, wsdl_type FROM ci_wsdl_keys
            WHERE wsdl_key IN (${Db.q(restrictedKey)}, ${Db.q(defaultKey)}) ORDER BY wsdl_key`,
        ),
        `both keys are minted against lab ${tenant.labId}`,
      ).toEqual([[defaultKey, ''], [restrictedKey, g.restricted]]);

      const denied = await soap(request, g.op, g.args(restrictedKey, assen));
      const allowed = await soap(request, g.op, g.args(defaultKey, assen));

      // THE GATE. `ci_data_work_server.php${g.gate}` permits (${g.allow}).
      expect(
        statusOf(denied),
        `a wsdl_key restricted to '${g.restricted}' reached ${g.op} `
        + `(gate ci_data_work_server.php${g.gate}, allows ${g.allow}): ${denied.slice(0, 400)}`,
      ).toBe('DENY');
      expect(msgOf(denied), `${g.op} refusal message`).toBe('Not allow');

      // THE CONTROL, and the reason the line above is not vacuous: same
      // operation, same arguments, same lab, key type '' instead of
      // '${g.restricted}'.
      expect(
        statusOf(allowed),
        `the shipped default key ('' = All) must not be denied ${g.op}: ${allowed.slice(0, 400)}`,
      ).not.toBe('DENY');

      // NO PHI CAME BACK on the refused call. The patient key is what the
      // operation would have returned had the gate let it through.
      expect(
        denied.includes(seeded.patientName),
        `the refused ${g.op} call returned ${seeded.patientName}`,
      ).toBe(false);

      if (g.writesTo) {
        // D226: the write row's real consequence -- the refused import must not
        // have created the report its payload asked for.
        expect(
          db.scalar(`SELECT COUNT(*) FROM ${g.writesTo} WHERE patient_id = ${Db.q(assen)}`),
          `the refused ${g.op} wrote a ${g.writesTo} row for ${assen}`,
        ).toBe('0');
      }
    });
  }

  /**
   * THE READ ROW'S OTHER HALF, and the only place in this family where the
   * accepted call is asserted to RETURN something.
   *
   * S-HUB-08's ACCEPT assertions are all "not DENY", which is exactly as strong
   * as the gate under test and no stronger. This test says what the gate is
   * protecting: with the default key, `get_req_by_assen` hands back the
   * requisition -- so the DENY row above withheld a real requisition from a
   * restricted key, rather than an empty result set from a broken operation.
   */
  test('S-HUB-08 control: the default key does pull the requisition get_req_by_assen refused @full', async ({
    request, db, tenant, runKey, seedRequisition,
  }) => {
    const f = infra(db);
    const seeded = await seedRequisition(`Hubeightctl${runKey}`);
    expect(seeded.status, 'the tox saver answers 200').toBe(200);
    const { assen } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

    const key = `e2ehub8c${runKey}`;
    grantKey(db, key, tenant.labId, '');

    const body = await soap(request, 'get_req_by_assen', { key, accessions: assen });
    expect(soapMapField(body, 'status'), `hub answered: ${body.slice(0, 400)}`).toBe('OK');
    expect(soapMapField(body, 'count'), `get_req_by_assen returned ${assen}`).toBe('1');
    expect(body.includes(seeded.patientName), 'and the requisition carries the patient').toBe(true);
  });
});

// ===========================================================================
// A key with NO LAB is a key with no tenancy
// ===========================================================================

/**
 * `pages/admin_ci_wsdl_keys.php:361` renders the Lab picker as
 * `<option value="">Select Lab</option>` with NO `required` attribute -- unlike
 * the client form at `admin_ci_webservice_client.php:504`, which has one. So an
 * administrator who saves a key without touching that select mints
 * `ci_wsdl_keys.wsdl_lab_ID = 0`.
 *
 * Every lab-scoped reader then writes its predicate CONDITIONALLY:
 *
 *   ci_data_work_server.php:1893   if ($lab_id > 0) { $where .= " and ba.lab_id = {$lab_id} "; }
 *
 * -- and `getClinicByKey()` contributes nothing either when the key has no
 * `ci_wsdl_key_clinics` rows (`:1887`, `count($cli_ids) > 0`). A lab-less,
 * clinic-less key therefore carries NO tenancy predicate at all and reads every
 * lab's released results through one anonymous SOAP call.
 *
 * The same shape as `TRPDD_SAME_LAB`: the scoping is written as an enhancement
 * on top of an unscoped default, so the failure of the enhancement is total
 * exposure rather than an empty result set.
 */
/** A pool lab that is NOT this spec's tenant -- the same probe S-HUB-06 uses. */
function otherPoolLab(db: Db, labId: string): string {
  const other = db.scalar(
    `SELECT lab_ID FROM ci_labs
      WHERE lab_dba_init REGEXP '^LA[A-P]$' AND lab_ID <> ${Db.q(labId)}
      ORDER BY lab_dba_init LIMIT 1`,
  );
  expect(other, 'a second pool lab to scope the foreign key to').not.toBe('');
  return other;
}

/**
 * One RELEASED tox specimen in this spec's own lab, which is the only thing
 * `get_results_data` will return (`WHERE re.estatus='C'` plus the approve_date
 * range).
 *
 * The requisition goes through the app's own saver; the release is written
 * directly, exactly as S-HUB-05 above writes its "already resulted"
 * precondition and as S-STA-03 backdates approve_date. It is the PRECONDITION,
 * not the outcome -- the outcome under test is which key can read it.
 */
async function releasedForKeyRead(
  db: Db,
  seedRequisition: (patientLast: string) => Promise<{ status: number; patientName: string }>,
  tenant: { labId: string; prefix: string },
  runKey: string,
  tag: string,
): Promise<{ assen: string; day: string; patientName: string }> {
  const seeded = await seedRequisition(`Hubten${tag}${runKey}`);
  expect(seeded.status, 'the tox saver answers 200').toBe(200);
  const [row] = db.query(
    `SELECT assen, lab_ID FROM ci_batches WHERE patient_name = ${Db.q(seeded.patientName)}`,
  );
  expect(row, `exactly one ci_batches row for ${seeded.patientName}`).toBeTruthy();
  const [assen, labId] = row;
  expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
  // TENANCY (D172): the row this test is about is in this spec's lab, so a
  // foreign key reading it is genuinely cross-tenant.
  expect(labId, `ci_batches.lab_ID for ${assen}`).toBe(tenant.labId);

  db.query(
    `INSERT INTO ci_reports (patient_id, estatus, approve_date, patient_name)
     VALUES (${Db.q(assen)}, 'C', NOW(), ${Db.q(seeded.patientName)})`,
  );
  const [[day, estatus]] = db.query(
    `SELECT DATE(approve_date), estatus FROM ci_reports WHERE patient_id = ${Db.q(assen)}`,
  );
  expect(estatus, `precondition: ${assen} is released`).toBe('C');
  return { assen, day, patientName: seeded.patientName };
}

/** `get_results_data($key, $limit, $datetime, $datetime_to, $page)`, one day wide. */
const readArgs = (key: string, day: string) => ({
  key, limit: '200', datetime: `${day} 00:00:00`, datetime_to: `${day} 23:59:59`, page: '1',
});

test.describe('wsdl key tenancy @hub @full @mutates', () => {
  /**
   * THE CONTROL, in its own test so that nothing here can be absorbed by the
   * expected failure below (a control that fails inside a `fail()` body is
   * indistinguishable from the pinned failure).
   *
   * It establishes three things the pin depends on: the released report is
   * visible to `get_results_data` at all, a key scoped to THIS lab sees it, and
   * a key scoped to ANOTHER lab does not. Without the third, "a lab-less key
   * sees it" would prove nothing about scoping.
   */
  test('a wsdl key scoped to one lab reads that lab and not another @full', async ({
    request, db, tenant, runKey, seedRequisition,
  }) => {
    const s = await releasedForKeyRead(db, seedRequisition, tenant, runKey, 'ctl');
    const otherLab = otherPoolLab(db, tenant.labId);

    const own = `e2ehubtenown${runKey}`;
    const foreign = `e2ehubtenfor${runKey}`;
    grantKey(db, own, tenant.labId, '');
    grantKey(db, foreign, otherLab, '');

    const mine = await soap(request, 'get_results_data', readArgs(own, s.day));
    expect(statusOf(mine), `hub answered: ${mine.slice(0, 400)}`).toBe('OK');
    expect(
      mine.includes(s.assen),
      `a key scoped to lab ${tenant.labId} must read its own released result ${s.assen}`,
    ).toBe(true);

    const theirs = await soap(request, 'get_results_data', readArgs(foreign, s.day));
    expect(statusOf(theirs), `hub answered: ${theirs.slice(0, 400)}`).toBe('OK');
    expect(
      theirs.includes(s.assen),
      `a key scoped to lab ${otherLab} pulled ${s.assen}, which belongs to lab ${tenant.labId}`,
    ).toBe(false);
  });

  /**
   * EXPECTED FAILURE (D105/D175): the app does not enforce this, so the test
   * states the DENIAL and is marked failing. It turns red -- "expected to fail,
   * but passed" -- the day `:1893` stops making the lab predicate conditional,
   * or the day the admin form stops offering a lab-less key.
   *
   * `test.fail()` is INSIDE the body, and every precondition and control sits
   * OUTSIDE it: the specimen is seeded and its release asserted before the
   * marker, and the scoping control is a separate test above.
   */
  test('a wsdl key with no lab must not read another lab\'s released results @full', async ({
    request, db, tenant, runKey, seedRequisition,
  }) => {
    const s = await releasedForKeyRead(db, seedRequisition, tenant, runKey, 'pin');

    const labless = `e2ehubtennone${runKey}`;
    grantKey(db, labless, '0', '');
    expect(
      db.scalar(`SELECT wsdl_lab_ID FROM ci_wsdl_keys WHERE wsdl_key = ${Db.q(labless)}`),
      'precondition: the key carries no lab, exactly as the admin form saves it',
    ).toBe('0');
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_wsdl_key_clinics WHERE wsdl_ID =
                 (SELECT wsdl_ID FROM ci_wsdl_keys WHERE wsdl_key = ${Db.q(labless)})`),
      'precondition: and no clinic grants either, so no predicate can come from there',
    ).toBe('0');

    const body = await soap(request, 'get_results_data', readArgs(labless, s.day));
    expect(statusOf(body), `hub answered: ${body.slice(0, 400)}`).toBe('OK');

    expect(
      body.includes(s.assen),
      `a ci_wsdl_keys row with wsdl_lab_ID=0 -- which pages/admin_ci_wsdl_keys.php:361 mints by `
      + `default -- pulled ${s.assen} from lab ${tenant.labId}: ci_data_work_server.php:1893 only `
      + 'adds the lab predicate when $lab_id > 0',
    ).toBe(false);
  });
});

test.describe('hub topology @hub', () => {
  test('the satellite site cannot serve the hub WSDL endpoint @full', async ({ request }) => {
    // The executable evidence behind the three fixme()s below.
    const hub = await request.get('/wsdl?wsdl');
    const hubBody = await hub.text();
    expect(hub.status(), 'blog 1 serves the hub contract').toBe(200);
    expect(hubBody, 'the SOAP contract').toContain('<wsdl:definitions');
    expect(hubBody, 'the order-push operation').toContain('operation name="put_req"');
    expect(hubBody, 'the endpoint is this instance').toContain('location="http://localhost/wsdl"');

    // ci-data-works-arc/inc/init.php:2 needs REQUEST_URI to START with /wsdl.
    const sat = await request.get('/patients/wsdl?wsdl');
    const satBody = await sat.text();
    expect(sat.status(), 'blog 2 has no SOAP endpoint -- it is a WordPress 404').toBe(404);
    expect(satBody.includes('<wsdl:definitions'), 'blog 2 serves no SOAP contract').toBe(false);
  });
});

/**
 * Not reachable on this stack. Each reason is a property of the app or of the
 * containment boundary, not a gap in the harness -- see the header.
 */
test.fixme(
  'S-HUB-01 satellite pushes locally-accessioned orders to the hub @full @hub',
  () => {
    // The hub-side outcome (`ci_batches` row on the hub for the pushed assen)
    // cannot be distinguished from the satellite's own row: both sites share
    // the unprefixed ci_* tables, and blog 2 cannot mount /wsdl at all.
    // A real hub means egress to imt.ignitelabconnect.com, denied by design.
  },
);

test.fixme(
  'S-HUB-02 only released results travel to the hub @full @hub',
  () => {
    // Same shared-table and no-second-endpoint problem as S-HUB-01, and the
    // post-push assertion is hub-side `ci_reports`/`ci_results` rows, which on
    // this instance ARE the satellite's own rows.
  },
);

test.fixme(
  'S-HUB-07 the satellite ABX tables track the hub @nightly @hub',
  () => {
    // The hub half does not exist in this repo. The pull is
    // CI_Lab_Client_Api::request() -> HUB_API_URL . 'check_abx_version'
    // (ci-lab-client/api/ci_lab_client.php:52-57, :720-744); ci-lab-client
    // registers only its own inbound '/lab_api/' route (inc/init.php:2). No
    // plugin here serves '/labs_api/', so there is nothing local to pull from.
  },
);
