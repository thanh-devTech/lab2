import { test as base, expect, request as playwrightRequest } from '@playwright/test';
import type { APIRequestContext, BrowserContext, Page } from '@playwright/test';
import * as path from 'node:path';
import { STATE_DIR } from '../playwright.config';
import { Db } from './db';
import { runKey } from './run-key';
import { tenantForSpec, type Tenant } from './tenant';
import { seedRequisition, type FormValue, type SeedRequisitionResult } from './seed-domain';
import { StubHttp } from './stub-http';

/**
 * The single test object. There is no page-object-per-page, no re-implemented
 * `expect`, and no config framework: the whole first-party surface is this file
 * plus its six neighbours.
 */
type WorkerFx = {
  db: Db;
  runKey: string;
  stubHttp: StubHttp;
};

type TestFx = {
  /**
   * D239: tenant is TEST-scoped and resolved from the spec filename, not
   * worker-scoped. The lab is the isolation unit and a worker is not a spec.
   * tenantRequest follows it, because a worker-scoped fixture may not depend
   * on a test-scoped one.
   */
  tenant: Tenant;
  /** APIRequestContext authenticated as this spec's tenant persona. */
  tenantRequest: APIRequestContext;
  seedRequisition: (patientLast: string, overrides?: Record<string, FormValue>) => Promise<SeedRequisitionResult>;
  /**
   * D380: open an authenticated Chromium page for ANY minted persona.
   *
   * TEST-scoped, and the built-in `page`/`context` fixtures are never used:
   * all four projects set `use: { storageState: undefined }`, so a bare `page`
   * is ANONYMOUS and lands on wp-login.php -- which reads like a capability
   * bug rather than a wiring bug. Contexts are built from the already-minted
   * state files and disposed at test end; never drive the login form (R3/D152)
   * and never write `context.storageState()` back over a persona file (D139).
   *
   * Per-test disposal is not tidiness: the billing worklist keys on cookie
   * `active_billing` (admin_ci_billing_list.php:900,919) and the receiving
   * bench on a session-borne day, so a context shared across tests carries
   * that state into the next test.
   */
  pageAs: (login: string) => Promise<Page>;
  /** Chromium page authenticated as THIS spec's tenant persona (tenant.login). */
  authedPage: Page;
};

/**
 * D392 -- the denied admin page is on a THREE-SECOND FUSE, so every assertion
 * about a denial must be bounded INSIDE it.
 *
 * The plugin hooks core's own `admin_page_access_denied`
 * (`ci-data-works-arc/inc/admin.php:1989` at the pinned a7aa074d) and, ahead of
 * core's wp_die() body, `parts/global/admin_page_access_denied.php` echoes
 * `<meta http-equiv="refresh" content="3;...page=welcome">` (:37). Measured on
 * this stack 2026-08-25, one poll per second, for EVERY denied page-gate case
 * in the suite (tox_req_form, blood_results, pcr_results, tox_results,
 * ci_reports_state_report): t=0..2 denial 1 / #adminmenu 0; t=3 the refresh
 * fires; t=4+ the URL is page=welcome with denial 0 / #adminmenu 1.
 *
 * The suite's default 5 s expect timeout OUTLIVES the page under assertion, so
 * an unbounded polling assertion on a loaded CI worker can grade the WELCOME
 * page and report the wrong screen for an authz regression. Bound the denied
 * branch only -- the allowed screens carry no refresh, and `ci_reports_state_report`
 * self-navigates to its own filtered URL inside 1 s, which a tight bound would
 * make flaky for no gain.
 */
export const BEFORE_BOUNCE_MS = 2_000;

export const test = base.extend<TestFx, WorkerFx>({
  db: [async ({}, use) => { await use(new Db()); }, { scope: 'worker' }],

  runKey: [async ({}, use, workerInfo) => { await use(runKey(workerInfo.workerIndex)); },
    { scope: 'worker' }],

  // D239: keyed on the SPEC FILE, not the worker. The lab is the isolation
  // unit and a worker is not a spec -- sharing one caused two silent flakes
  // (S-REV-08 deleted mid-assertion by a sibling's cleanup; S-REV-02 flipped by
  // a concurrent listing render that auto-approves on GET). The pool query is
  // cached in tenant.ts, so test scope costs no extra round trips.
  tenant: async ({ db }, use, testInfo) => { await use(tenantForSpec(db, testInfo.file)); },

  stubHttp: [async ({}, use) => { await use(new StubHttp()); }, { scope: 'worker' }],

  tenantRequest: async ({ tenant }, use, testInfo) => {
    const ctx = await playwrightRequest.newContext({
      baseURL: (testInfo.project.use as { baseURL?: string }).baseURL ?? 'http://localhost',
      storageState: tenant.statePath,
    });
    await use(ctx);
    await ctx.dispose();
  },

  seedRequisition: async ({ tenantRequest, db, tenant }, use) => {
    await use((patientLast, overrides) => seedRequisition(tenantRequest, db, tenant, patientLast, overrides));
  },

  pageAs: async ({ browser }, use, testInfo) => {
    const baseURL = (testInfo.project.use as { baseURL?: string }).baseURL ?? 'http://localhost';
    const open: BrowserContext[] = [];
    await use(async (login: string) => {
      // A manually-created context inherits NOTHING from `use`, so baseURL and
      // viewport are passed explicitly. 1440x900 keeps #adminmenu out of core's
      // responsive fold (<960px) and keeps .row-actions hoverable.
      const ctx = await browser.newContext({
        baseURL,
        viewport: { width: 1440, height: 900 },
        storageState: path.join(STATE_DIR, `${login}.json`),
      });
      open.push(ctx);
      return ctx.newPage();
    });
    for (const ctx of open) await ctx.close();
  },

  authedPage: async ({ pageAs, tenant }, use) => {
    await use(await pageAs(tenant.login));
  },
});

export { expect };
