import { randomBytes } from 'node:crypto';

/**
 * A key that is unique ACROSS runs, not merely within one (D136).
 *
 * The prototype's default was the literal 'local': unique per test, identical
 * per invocation, so a second `npx playwright test` against a database nobody
 * had reset collided with the rows the first run created. Proven: PW_RUN_ID=
 * proofA passed 24/24, the same id again failed 3, a fresh id passed 24/24.
 *
 * Playwright workers are separate processes, so each one evaluates this module
 * itself and gets its OWN value. That is deliberate -- the guarantee the suite
 * needs is "no two rows collide", and per-worker keys satisfy it more strongly
 * than one shared key would. Set PW_RUN_ID to pin the key across a whole run
 * (used by the fault-injection pass, which re-runs the same spec twice).
 */
export function runKey(workerIndex: number): string {
  const base = process.env.PW_RUN_ID ?? Date.now().toString(36) + randomBytes(3).toString('hex');
  return `${base}w${workerIndex}`;
}
