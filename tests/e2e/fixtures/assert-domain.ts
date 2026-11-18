import { expect } from '@playwright/test';
import { Db } from './db';
import type { Tenant } from './tenant';
import type { SeedRequisitionResult } from './seed-domain';

/**
 * The assertion standard (D140/D146/R10).
 *
 * What is BANNED here and why:
 *  - `expect(res.status()).toBeLessThan(400)` -- a login redirect is 302 and
 *    passes it. The prototype's h8 probe was eleven copies of that (D141).
 *  - AUTO_INCREMENT deltas -- a replayed form_unique_id makes the saver return
 *    at HTTP 200 having written nothing, and the counter still moved (D142).
 *    The invariant is ROWS GAINED > 0.
 *  - `WHERE patient_name LIKE '%key%'` -- another test's row satisfies it.
 *    Exact match on the per-test key only.
 *  - Asserting the catalog columns while ignoring lab_id -- routing every
 *    requisition to the wrong lab stayed GREEN through the whole Gate-D
 *    injection set (D160/D161). Tenancy is asserted, never assumed.
 *
 * One SELECT, not five: each `docker compose exec` costs ~131 ms (D164).
 */
export function expectBatch(
  db: Db,
  seeded: SeedRequisitionResult,
  tenant: Tenant,
  opts: {
    testGroupId: string;
    /**
     * Expected `ci_batches.batch_status`. Defaults to `''` -- a NEW requisition
     * is unreceived. D221: this used to be hard-coded, which made expectBatch
     * unusable for any flow past ordering; every caller past the bench passes
     * its own value.
     */
    batchStatus?: string;
    /**
     * What `panel_tests_requested` must contain. Defaults to
     * `group_<testGroupId>`, which is what the HTTP fixture posts.
     *
     * A UI-DRIVEN ORDER STORES SOMETHING ELSE: the panel checkbox on the
     * requisition form is `name="bat[panel_tests_requested][]"` with
     * `value="<?php echo $key ?>"` where `$key = strtoupper($r->key)`
     * (`parts/tox/req_form.php:1382,1422` at the pin), so the column holds the
     * UPPERCASED ANALYTE NAME -- measured `E2E ANALYTE ONE`, not
     * `group_<tg_ID>`. Defaulting silently would make the screen-driven test
     * assert a string no screen can produce.
     */
    panelContains?: string;
  },
): { assen: string; mid: string } {
  const patientName = seeded.patientName;

  // D142/D221: rows GAINED, never the AUTO_INCREMENT delta -- and gained ON
  // THIS TEST'S KEY, never a global table count. The global count was racy
  // under parallelism: a sibling worker's save satisfied the invariant that is
  // supposed to prove OUR save happened.
  expect(
    seeded.keyRowsAfter - seeded.keyRowsBefore,
    `no ci_batches row for ${patientName} (HTTP ${seeded.status}). The saver returns 200 and ` +
      'writes nothing when form_unique_id is replayed (D142) or a written column is missing (D100).',
  ).toBeGreaterThan(0);

  const rows = db.query(
    `SELECT mid, assen, batch_type, batch_status, lab, lab_id, panel_tests_requested
       FROM ci_batches WHERE patient_name = ${Db.q(patientName)}`,
  );
  expect(rows.length, `expected exactly one ci_batches row for ${patientName}`).toBe(1);
  const [mid, assen, batchType, batchStatus, lab, labId, panels] = rows[0];

  // D160: the app writes assen='' at HTTP 200 when the minter fails. Empty is
  // the failure signal, so "non-empty" is the assertion, not "exists".
  expect(assen, 'accession is empty -- tr_get_new_assen() returned nothing').not.toBe('');
  const dupes = db.scalar(`SELECT COUNT(*) FROM ci_batches WHERE assen = ${Db.q(assen)}`);
  expect(dupes, `accession ${assen} is not unique`).toBe('1');

  expect(batchType, 'batch_type').toBe('E');            // 'M' only when batch_short=1
  expect(batchStatus, 'batch_status').toBe(opts.batchStatus ?? '');
  expect(panels, 'panel_tests_requested')
    .toContain(opts.panelContains ?? `group_${opts.testGroupId}`);

  // TENANCY, asserted (R10/D161/D172).
  expect(labId, `ci_batches.lab_id: requisition routed to lab ${labId}, tenant is ${tenant.labId}`)
    .toBe(tenant.labId);
  expect(lab, 'ci_batches.lab').toBe(tenant.prefix);
  expect(assen.slice(0, 3), `accession prefix of ${assen}`).toBe(tenant.prefix);

  return { assen, mid };
}

/**
 * A SCOPE CLAUSE, ASSERTED SO IT CANNOT PASS OVER AN EMPTY SET (D277).
 *
 * THE FAILURE THIS PREVENTS, which is not hypothetical. `ofc.spec.ts:397-408`
 * carries it in prose: with `ci_clinic_manager` empty, the office-manager
 * clause degrades to `and ba.cli_ID in (  )`, the query errors, the render is
 * empty, and `expect(body).not.toContain(foreignAccession)` PASSES -- proving
 * nothing, while hiding every mutation in the handler. The same shape appears
 * whenever the persona is denied, the slug is wrong, the day filter excludes
 * everything, or the request omits a parameter the handler needs. An absence
 * assertion is only evidence when something WAS present to exclude.
 *
 * So this refuses to assert absence on its own. Three things, always:
 *   1. the WIDENED read shows the foreign marker -- this render can see it at
 *      all, so absence below is attributable to the scope and to nothing else;
 *   2. the SCOPED read still shows the persona's OWN marker -- non-empty
 *      baseline, so absence is not "the screen showed nothing";
 *   3. the scoped read does NOT show the foreign marker -- the actual claim.
 *
 * `widened` is whatever removes ONE variable and nothing else: the same request
 * with the scope column cleared (`doc.spec.ts:918-931` clears `_lab_ids`), the
 * permissive persona, or the seed's own twin fetched under an admin read. It is
 * NOT a different screen -- a widened read from another page proves the marker
 * exists, not that THIS render can show it.
 *
 * Pass the RENDERED body, or the list of names/accessions parsed from it, after
 * a fresh `goto`/`reload` (D386). Both reads must come from the same entry
 * point.
 */
export function expectScopeExcludes(o: {
  /** The clause under test, quoted: `ci_load_test_results.php:126 and ba.doc_ID = $doctor_id`. */
  arm: string;
  /** What the user is looking at: 'the provider's day list'. */
  observed: string;
  /** The render with the scope widened by ONE variable. */
  widened: string | string[];
  /** The render under the scope. */
  scoped: string | string[];
  /** A marker the scoped read MUST still show -- the persona's own row. */
  own: string;
  /** A marker the scoped read must NOT show -- the twin, differing in one scoped column. */
  foreign: string;
}): void {
  const has = (h: string | string[], n: string) =>
    Array.isArray(h) ? h.some((x) => String(x).includes(n)) : h.includes(n);

  // D397: a marker compared against itself proves nothing, and accession
  // prefixes make containment aliasing real -- 'LAL1234' contains 'LAL123', so
  // a twin numbered next to its sibling would report present when it is not.
  expect(o.own, `${o.arm}: own and foreign markers are the same string`).not.toBe(o.foreign);
  expect(
    o.own.includes(o.foreign) || o.foreign.includes(o.own),
    `${o.arm}: marker '${o.own}' and '${o.foreign}' alias under toContain -- pick markers `
      + 'where neither is a substring of the other, or the absence half is not decidable',
  ).toBe(false);

  expect(
    has(o.widened, o.foreign),
    `${o.arm}: ${o.observed} does not show ${o.foreign} even with the scope WIDENED, so the `
      + 'absence below would be vacuous -- an empty render, a denial, or the wrong entry '
      + 'point, not scoping (D277)',
  ).toBe(true);

  expect(
    has(o.scoped, o.own),
    `${o.arm}: ${o.observed} is missing the reader's OWN row ${o.own} -- the scoped render is `
      + 'starved, so it excludes everything and proves nothing (D277)',
  ).toBe(true);

  expect(
    has(o.scoped, o.foreign),
    `${o.arm}: ${o.observed} leaked ${o.foreign} -- the twin differs from ${o.own} only in the `
      + 'column this clause scopes on, so the clause is not being applied',
  ).toBe(false);
}
