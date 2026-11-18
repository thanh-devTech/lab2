import { test as setup, expect, request as playwrightRequest } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { STATE_DIR } from '../playwright.config';

const ENV_DIR = path.resolve(__dirname, '..', 'env');
// Playwright forbids one test file importing another, so this one-liner is
// duplicated in setup/teardown.ts rather than shared through a module.
const gitPorcelain = () =>
  execFileSync('git', ['status', '--porcelain'],
    { cwd: path.resolve(__dirname, '..', '..', '..'), encoding: 'utf8' });

/**
 * Personas the suite authenticates as. `blog` is the subsite whose profile.php
 * proves identity -- the patient persona is not a blog-1 member, so its
 * /wp-admin/ serves page=welcome with no login form and the naive "not on
 * wp-login.php" reading passes for a dead cookie (R14).
 */
const PERSONAS: { login: string; blog: string }[] = [
  { login: 'Administrat0r', blog: '' },
  { login: 'e2e_sec', blog: '' },
  { login: 'e2e_tech', blog: '' },
  { login: 'e2e_dir', blog: '' },
  { login: 'e2e_patient', blog: '/patients' },
  // ONE lead per pool lab, and the pool is LAA..LAP (sql/25-lab-pool.sql).
  // This said 8 while Makefile PERSONAS minted 16: e2e_lead8..15 -- the tenant
  // persona of every wave-2 spec, pcr.spec.ts included -- were minted but never
  // identity-checked, so a dead cookie on one of them passed this gate and
  // surfaced later as an unrelated assertion failure. Keep this in step with
  // PERSONAS in ../Makefile; the two lists must cover the same 29 logins.
  ...Array.from({ length: 16 }, (_, i) => ({ login: `e2e_lead${i}`, blog: '' })),
  // D204 secondary roles. All staff, so all on blog 1.
  ...['e2e_billing', 'e2e_billclin', 'e2e_doctor', 'e2e_collector',
      'e2e_officemgr', 'e2e_multisite', 'e2e_pcrverify', 'e2e_scientist']
    .map((login) => ({ login, blog: '' })),
];

setup('reset to baseline, then verify every persona identity', async ({ baseURL }) => {
  setup.setTimeout(120_000);

  // Reset FIRST. A guard that runs against the pre-reset database reads the
  // live session_tokens rather than the ones baseline.sql will restore, and
  // therefore cannot detect a stale-snapshot mint (R14).
  execFileSync('./reset.sh', { cwd: ENV_DIR, stdio: 'inherit' });

  // D128 baseline for the teardown check. A repo can be legitimately dirty when
  // a run starts (modified submodules, a work-in-progress edit); what must be
  // zero is the DELTA the suite adds.
  fs.mkdirSync(STATE_DIR, { recursive: true });
  fs.writeFileSync(path.join(STATE_DIR, 'git-baseline.txt'), gitPorcelain());

  const failures: string[] = [];
  for (const p of PERSONAS) {
    const statePath = path.join(STATE_DIR, `${p.login}.json`);
    if (!fs.existsSync(statePath)) {
      failures.push(`${p.login}: no storageState at ${statePath} -- run \`make mint\``);
      continue;
    }
    const ctx = await playwrightRequest.newContext({ baseURL, storageState: statePath });
    const res = await ctx.get(`${p.blog}/wp-admin/profile.php`);
    const body = await res.text();
    // A TRUE identity assertion. #user_login is the read-only login field core
    // renders on profile.php; it is present only for an authenticated user and
    // it names WHICH user, so a cookie belonging to someone else fails too.
    const m = body.match(/id="user_login"[^>]*value="([^"]*)"/);
    if (m?.[1] !== p.login) {
      failures.push(`${p.login}: profile.php#user_login = ${m?.[1] ?? '<absent>'} (HTTP ${res.status()})`);
    }
    await ctx.dispose();
  }

  expect(
    failures,
    'Persona identity guard failed. The usual cause is the D139 ordering invariant: the ' +
      'baseline was snapshotted BEFORE the personas were minted, so reset.sh restored empty ' +
      'wp_usermeta.session_tokens. Second cause: the baseline is more than 14 days old and the ' +
      'auth tokens hit their hard absolute expiry (D159). Both are fixed by `make e2e`.\n' +
      failures.join('\n'),
  ).toEqual([]);
});
