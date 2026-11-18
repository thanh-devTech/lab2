import { test as teardown, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { STATE_DIR } from '../playwright.config';

const ENV_DIR = path.resolve(__dirname, '..', 'env');
// Duplicated from setup/env.setup.ts: Playwright forbids test-file imports.
const gitPorcelain = () =>
  execFileSync('git', ['status', '--porcelain'],
    { cwd: path.resolve(__dirname, '..', '..', '..'), encoding: 'utf8' });

/**
 * Wired via `teardown: 'teardown'` on the `setup` project, NOT as a root
 * project. As a root project it ran FIRST, and `--grep @smoke` dropped it
 * entirely (B-R5/D151).
 *
 * It still does NOT run on Ctrl-C -- Playwright has no SIGINT teardown. The
 * Makefile's `trap env/down.sh EXIT INT TERM` covers that locally, and in CI
 * the runner is ephemeral so a cancelled job takes the containers with the VM.
 *
 * It deliberately does NOT `docker compose down`: `make e2e` owns the stack
 * lifecycle, and a developer running `npx playwright test` twice against a
 * running stack must not have it destroyed under them by the first run.
 */
teardown('restore the baseline and prove the suite added no git dirt', () => {
  execFileSync('./reset.sh', { cwd: ENV_DIR, stdio: 'inherit' });

  // D128: the webroot IS the git checkout, so anything the app writes under
  // wp-content lands in the working tree. wp-content/{cache,log,tmp} are tmpfs
  // (docker-compose.yml) precisely so this delta stays empty. If it does not,
  // the tmpfs list is missing a directory the app writes to.
  //
  // The DELTA against the baseline the setup project captured, not absolute
  // cleanliness: a repo may legitimately start dirty (modified submodules), and
  // failing on that would make this assertion untrue rather than strict.
  const basePath = path.join(STATE_DIR, 'git-baseline.txt');
  const before = new Set(
    (fs.existsSync(basePath) ? fs.readFileSync(basePath, 'utf8') : '').split('\n').filter(Boolean),
  );
  const added = gitPorcelain().split('\n').filter((l) => l && !before.has(l));

  expect(added, 'the suite added files to the git working tree').toEqual([]);
});
