import * as path from 'node:path';
import { Db } from './db';
import { STATE_DIR } from '../playwright.config';

export interface Tenant {
  /** ci_labs.lab_ID */
  labId: string;
  /** ci_labs.lab_dba_init -- the 3-char accession prefix, e.g. 'LAC' */
  prefix: string;
  /** wp_users.user_login of the leadlabsecman persona bound to this lab */
  login: string;
  /** storageState JSON for that persona */
  statePath: string;
}

/**
 * ALLOCATE a lab from the baked pool. Never create one.
 *
 * This is the R7/D161 fix, and D161 is the worst bug the design had because it
 * failed GREEN. A lab created per test vanishes at the next reset.sh;
 * CI_Base_Lab::get_user_lab_ids() then hits its empty branch and PERMANENTLY
 * delete_user_meta('_lab_ids'); the persona silently widens to every active lab
 * (demonstrated: get_user_lab_ids() -> ["1","4","6"]); and tests keep passing
 * because they now see MORE data. sql/25-lab-pool.sql puts the labs in the
 * baseline instead, so that branch is never reached.
 *
 * The prefix is exactly 3 characters (D163): replace_assen_by_lab() hardcodes
 * substr($assen, 3) and replace_assen_by_reflab() hardcodes /^[a-zA-Z]{3}/.
 * An 8-char prefix produced 'm9q2k7x32k7x31234567' and ERROR 1406.
 *
 * One lab per worker, and one PERSONA per lab: the binding is user meta, so a
 * shared persona would have two workers racing _lab_id and each asserting
 * against the other's tenant.
 */
/**
 * D239 -- WHY A SPEC MUST NAME ITS OWN LAB.
 *
 * This used to key on workerIndex alone. The lab is the isolation unit, but the
 * worker is not the spec: under `--workers=1` every spec got LAA, and in a full
 * parallel run any spec using the default fixture rotated onto whichever lab a
 * worker happened to hold -- including one another spec had pinned.
 *
 * Two real flakes came from exactly that, and neither was a bug in the test
 * that failed:
 *   - S-REV-08 failed in ~2 of 3 full-suite runs because a sibling worker's
 *     afterAll globbed `rm -rf .../lac*` mid-assertion.
 *   - S-REV-02 then failed intermittently because a concurrent listing render
 *     on the SAME lab auto-approves reports for rejected statuses (D034/D225),
 *     flipping a batch that test asserts is untouched.
 *
 * Fixing those tests individually is whack-a-mole; the shared lab is the cause.
 * There are 8 pool labs and 8 spec files, so every spec gets one outright --
 * see SPEC_LAB below. Deliberately NOT hashed: with 8 slots and 8 specs a hash
 * collides more often than not, and a collision here is a silent flake.
 * E2E_LAB=LAC overrides for debugging a single spec.
 */
export const SPEC_LAB = {
  ord: 0,  // LAA
  tst: 1,  // LAB
  rev: 2,  // LAC
  por: 3,  // LAD
  acc: 4,  // LAE
  sta: 5,  // LAF
  bil: 6,  // LAG
  hub: 7,  // LAH
  // wave 2 (D262)
  dst: 8,  // LAI -- result distribution
  doc: 9,  // LAJ -- clinic provider / ordering physician
  col: 10, // LAK -- collector / phlebotomist
  ofc: 11, // LAL -- office manager / multi-site manager
  adm: 12, // LAM -- laboratory administrator
  pcr: 13, // LAN -- PCR verifier
  // LAO (14) is the FOREIGN lab the cross-tenant tests reach for -- it belongs
  // to no spec on purpose, so a specimen seeded into it can contaminate
  // nobody. rev.spec.ts:65 names it SPARE_POOL_INDEX.
  serial: 15, // LAP -- the `serial` project (workers:1, after `main`)
} as const;

let poolCache: string[][] | null = null;

/**
 * Resolve the tenant for a SPEC FILE, not a worker (D239). Cached, so this
 * costs one query per process rather than one per test.
 */
export function tenantForSpec(db: Db, specFile: string): Tenant {
  const base = specFile.replace(/\\/g, '/').split('/').pop()!.replace(/\.spec\.ts$/, '');
  const idx = (SPEC_LAB as Record<string, number>)[base];
  if (idx === undefined) {
    // A new spec with no SPEC_LAB entry falls back to LAA. Loud on purpose:
    // silently sharing a lab is what caused the S-REV-02/08 flakes.
    console.warn(
      `[tenant] ${base}.spec.ts has no SPEC_LAB entry -- falling back to pool index 0 (LAA). ` +
      `Add it to SPEC_LAB in fixtures/tenant.ts to give it its own lab.`,
    );
  }
  return allocateTenant(db, idx ?? 0);
}

export function allocateTenant(db: Db, workerIndex: number): Tenant {
  const pool = poolCache ?? db.query(
    `SELECT l.lab_ID, l.lab_dba_init, u.user_login
       FROM ci_labs l
       JOIN wp_usermeta m ON m.meta_key='_lab_id' AND m.meta_value = l.lab_ID
       JOIN wp_users    u ON u.ID = m.user_id AND u.user_login LIKE 'e2e\\_lead%'
      WHERE l.lab_dba_init REGEXP '^LA[A-P]$'
      ORDER BY l.lab_dba_init`,
  );
  if (pool.length === 0) {
    throw new Error(
      'tenant pool is empty: run env/seed.sh (sql/25-lab-pool.sql + the e2e_lead* personas)',
    );
  }
  poolCache = pool;
  const forced = process.env.E2E_LAB;
  let row = pool[workerIndex % pool.length];
  if (forced) {
    const hit = pool.find((r) => String(r[1]).toUpperCase() === forced.toUpperCase());
    if (!hit) {
      throw new Error(
        `E2E_LAB=${forced} is not in the pool (${pool.map((r) => r[1]).join(', ')})`,
      );
    }
    row = hit;
  }
  const [labId, prefix, login] = row;
  return { labId, prefix, login, statePath: path.join(STATE_DIR, `${login}.json`) };
}
