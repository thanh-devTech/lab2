import { execFileSync } from 'node:child_process';
import * as path from 'node:path';

/**
 * THE SAFETY GATE, on the local path.
 *
 * `npx playwright test --project=main --no-deps` schedules zero setup projects
 * and exits 0, so wiring containment checks into the `setup` project leaves a
 * one-flag bypass (B-R1/D148). globalSetup has no such flag: it runs before
 * every project on every invocation.
 *
 * In CI this is redundant with the dedicated blocking `safety-gate.sh` step
 * (D149/D173) and is skipped, because there the gate must fail the RUN, and a
 * globalSetup failure inside a `continue-on-error: true` test step would not.
 */
export default function globalSetup(): void {
  if (process.env.CI) {
    console.log('globalSetup: CI detected -- safety gate runs as its own blocking workflow step');
    return;
  }
  const env = path.join(__dirname, 'env');
  console.log('globalSetup: safety gate...');
  execFileSync('./safety-gate.sh', { cwd: env, stdio: 'inherit' });
}
