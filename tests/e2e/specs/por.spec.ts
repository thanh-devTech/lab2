import { test, expect, request as playwrightRequest } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';
import { test as e2e } from '../fixtures/test';
import { expectBatch } from '../fixtures/assert-domain';
import { infra } from '../fixtures/seed-domain';
import { mailSubjectsTo, runCron, wpEval } from '../fixtures/container';
import { Db } from '../fixtures/db';
import type { Tenant } from '../fixtures/tenant';
import { STATE_DIR } from '../playwright.config';
import * as path from 'node:path';

/**
 * POR -- patient portal, plus the two ORD scenarios the patient owns.
 *
 * Persona: Patient. There is NO LIS role for it: `install_roles.php:541` does
 * `remove_role('patient')` and WooCommerce owns the slug. What a patient IS, on
 * this build, is a `wp_users` row whose non-core `patient_id` column points at a
 * `ci_patients.pa_ID`, holding a blog-2 capability entry. Every capability claim
 * below is read from the RUNNING instance (D178) -- `wp_2_capabilities` in
 * `wp_usermeta`, never `install_roles.php`.
 *
 * Tenant lab: LAD (pool index 3). The `tenant` worker fixture normally hands out
 * `pool[workerIndex]`; this file pins index 3 so it never collides with a spec
 * running on another lab.
 *
 * Not testable on this build, each with the measurement that closes it:
 *   S-ORD-04 / S-ORD-05 / S-POR-06 -- WooCommerce is not vendored at all (D047
 *     understates it: not just the gateway). The zero-total path it points at,
 *     `ci_patients/inc/init.php:571-578`, is a `woocommerce_checkout_*` filter,
 *     and the AJAX entry `tr_act=ci_checkout_tests` dies at
 *     `ci_checkout_tests.php:5` on `wc_maybe_define_constant()`. Measured: HTTP
 *     500, `PHP Fatal error: Uncaught Error: Call to undefined function
 *     wc_maybe_define_constant()`.
 *   S-POR-02 -- D109.
 *
 * The catalog's `#place_order2` is not selected anywhere here (D048), and no
 * `waitForResponse` predicate is needed: every portal entry under test is a
 * plain form POST or GET, not a `trajax=1&act=` fragment (D166/D050).
 */

const PORTAL = '/patients';

// The pool is ordered LAA..LAH; index 3 is LAD, with persona e2e_lead3.
const por = e2e;

interface LinkedPatient {
  last: string;
  email: string;
  phone: string;
  assen: string;
  mid: string;
  paId: string;
  userId: string;
  registerStatus: number;
  registerLocation: string;
}

/**
 * A portal patient, built the way the product builds one:
 *   1. staff requisition through the app's own tox saver (the `seedRequisition`
 *      fixture) -- this is what mints the accession AND inserts the
 *      `ci_patients` master row the portal later binds to;
 *   2. the custom register form at `wp-login.php?action=register` on blog 2,
 *      which resolves the Patient Code to that master row.
 *
 * Step 2 is S-POR-03's subject; S-POR-04, S-POR-05 and S-POR-07 consume it as a
 * precondition rather than hand-forging a `wp_users.patient_id` link.
 */
async function linkPatient(
  seedRequisition: (last: string) => Promise<any>,
  db: Db,
  tenant: Tenant,
  anon: APIRequestContext,
  last: string,
): Promise<LinkedPatient> {
  const f = infra(db);
  const seeded = await seedRequisition(last);
  expect(seeded.status, 'the tox saver answers 200 on the requisition POST').toBe(200);
  const { assen, mid } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });

  const paId = db.scalar(`SELECT patient_id FROM ci_batches WHERE mid = ${Db.q(mid)}`);
  expect(paId, 'the saver linked the batch to a ci_patients row').not.toBe('0');

  // Obviously synthetic throughout: the DOB is the fixture's 1950-01-01, the
  // domain is .invalid, and the number follows seed.php's 000-000-000X family.
  // That family is load-bearing, not decorative: notify_sms_results.php:332
  // treats any number containing six zeros as a placeholder and skips the SMS
  // leg. Give it a dialable-looking number instead and the leg proceeds into
  // Otpa_Bandwidth_Sms_Gateway, which FATALS -- `getMessaging() on null` at
  // class-otpa-bandwidth-sms-gateway.php:659, because the plugin's `vendor/`
  // is not shipped. Measured. The email leg (line 295) runs first either way.
  const email = `${last.toLowerCase()}@example.invalid`;
  const phone = '0000000199';

  const res = await anon.post(`${PORTAL}/wp-login.php?action=register`, {
    maxRedirects: 0,
    form: {
      user_email: email,
      patient_code: assen,          // resolves via the batch `assen` branch
      first_name: 'Synthetic',
      last_name: last,
      birthday: '1950-01-01',
      phone,
      'wp-submit': 'Register',
    },
  });

  const userId = db.scalar(`SELECT ID FROM wp_users WHERE user_email = ${Db.q(email)}`);
  return {
    last, email, phone, assen, mid, paId, userId,
    registerStatus: res.status(),
    registerLocation: res.headers()['location'] ?? '',
  };
}

/** Approved report for one accession -- S-POR-04's and S-POR-07's precondition. */
function insertApprovedReport(db: Db, p: LinkedPatient, tenant: Tenant): void {
  db.query(
    `INSERT INTO ci_reports (batch_id, patient_name, patient_id, estatus, approve_date,
                             created_date, lab_id, final_results)
     VALUES (${Db.q(p.mid)}, ${Db.q(`${p.last}, Synthetic`)}, ${Db.q(p.assen)},
             'C', NOW(), CURDATE(), ${Db.q(tenant.labId)}, 1)`,
  );
}

// ---------------------------------------------------------------- S-POR-01

por.describe('S-POR-01 @por', () => {
  por('the web portal login channel is phone-only and issues no bearer token', async (
    { db, tenant, page }, testInfo,
  ) => {
    const baseURL = testInfo.project.use.baseURL!;
    const anon = await playwrightRequest.newContext({ baseURL });
    const patient = await playwrightRequest.newContext({
      baseURL, storageState: path.join(STATE_DIR, 'e2e_patient.json'),
    });

    // 1. THE CHANNEL, AS THE PATIENT MEETS IT.
    //
    //    D388 -- this SUPERSEDES the three raw-HTML regexes it replaces (D382
    //    does not apply: same GET, same handler, no orphaned caller). The
    //    regexes could not tell a login screen from a stack trace: a PHP fatal
    //    inside the OTP template answers 200 with no `type="password"` in it
    //    either, so the load-bearing assertion was passing on the ABSENCE of a
    //    string in a body nothing had checked was a page. The rendered facts
    //    below are positive first and negative second.
    //
    //    The built-in `page` is ANONYMOUS -- every project sets
    //    `storageState: undefined` (fixtures/test.ts) -- which is what a
    //    patient arriving at the portal actually is. Measured on this stack:
    //    this page carries NO meta-refresh, so D392's bound does not apply here
    //    (unlike `/patients/` itself, see 2 below).
    await page.goto(`${PORTAL}/otpa/passwordless-login`);
    await expect(
      page.getByRole('heading', { name: 'Sign In to Your Patient Portal', exact: true }),
      'the patient is looking at the portal sign-in screen',
    ).toBeVisible();
    // The ONE credential the screen asks for. `getByLabel('Phone Number')`
    // resolves 0 -- the "Phone Number" text is a sibling <span>, not a
    // label[for] -- and the native `#otpa_id` is hidden behind the widget
    // (measured `isVisible() === false`), so the box the patient can actually
    // type into is addressed by the placeholder that names it.
    await expect(
      page.getByRole('textbox', { name: 'Enter your phone number' }),
      'the only credential the portal asks for is a phone number',
    ).toBeVisible();
    await expect(
      page.getByRole('button', { name: 'Send Verification Code' }),
      'and the way in is a code sent to that phone',
    ).toBeVisible();
    // Now the negative, on a page just proven to have rendered.
    await expect(
      page.locator('input[type="password"]'),
      'a password box on the passwordless channel would be a second, weaker way in',
    ).toHaveCount(0);

    // 2. A portal session. Minted, not driven through the OTP round trip: a full
    //    round trip cannot complete here because OTP issuance dies in the
    //    gateway -- measured, POST /patients/otpa-api/otp answers
    //    {"code":"OTPA_LOAD_LIBRARY_FAILED"} for a known patient phone, the
    //    Bandwidth SDK not being vendored behind the egress boundary. D030
    //    records that the passwordless flow's ONLY session artefact is the same
    //    core `wp_set_auth_cookie()` the mint calls
    //    (`class-otpa-passwordless.php:176`), so the cookie under test is the
    //    artefact the OTP flow would have produced.
    //
    //    Anonymous vs authenticated is asserted BOTH ways, so "logged in" is a
    //    falsifiable claim and not a page that happens to contain a name.
    //
    //    DELIBERATELY NOT CONVERTED, and the measurement is why -- NOT a
    //    product defect, a THEME one, so no card. Blog 2 runs
    //    `wp-content/themes/blankforlab`, whose entire `footer.php` line 1 is
    //    `<meta http-equiv="refresh" content="0;url=/" />`. Every front-end
    //    portal page therefore ships a ZERO-second self-redirect off the portal
    //    and onto blog 1, for the anonymous and the authenticated visitor alike
    //    -- nothing to do with D392's three-second denial fuse. Rendered, the
    //    authenticated portal home holds no portal content at all: the whole
    //    body is core's admin bar ("Skip to toolbar / About igniteLIS / My Sites
    //    / New Lab / Howdy, Testpatient Alpha / Search"), and it is gone before
    //    a bounded assertion can be trusted to have graded it rather than the
    //    page after it. The name asserted here lives in that admin bar's
    //    `span.display-name`, so what the claim is worth is "core knows who
    //    holds the cookie" -- true, and read most reliably off the response body
    //    BEFORE the client-side bounce, which is what a request context does and
    //    a browser cannot.
    const anonHome = await (await anon.get(`${PORTAL}/`)).text();
    const authedHome = await (await patient.get(`${PORTAL}/`)).text();
    expect(anonHome.includes('Testpatient Alpha'), 'anonymous portal shows no patient').toBe(false);
    expect(authedHome, 'cookie session is the linked patient').toContain('Testpatient Alpha');

    // 3. The catalog's assertion: the web channel mints no bearer token. The
    //    app-api channel is the one that writes ci_user_tokens, and it is dead
    //    on this build (D109), so this is scoped to the portal user's id rather
    //    than to a table-wide count another spec could disturb.
    const uid = db.scalar("SELECT ID FROM wp_users WHERE user_login = 'e2e_patient'");
    expect(uid, 'the seeded portal user exists').not.toBe('');
    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_user_tokens WHERE user_id = ${Db.q(uid)}`),
      'a web-portal login must not create a ci_user_tokens row',
    ).toBe('0');

    console.log(`S-POR-01 lab=${tenant.prefix}(${tenant.labId}) portal_uid=${uid}`);
    await anon.dispose();
    await patient.dispose();
  });
});

// ---------------------------------------------------------------- S-POR-02

test.fixme(
  'S-POR-02 @por mobile-app bearer login -- BLOCKED by D109: every /app_api route ' +
  'fatals on an unguarded wc_load_cart() with no WooCommerce, and reaching the route ' +
  'needs ci_patients active on blog 1, which disables the LIS admin',
  () => {},
);

// ---------------------------------------------------------------- S-POR-03

por.describe('S-POR-03 @por', () => {
  por('registration binds the WordPress account to the correct patient master row', async (
    { seedRequisition, db, tenant, runKey }, testInfo,
  ) => {
    const baseURL = testInfo.project.use.baseURL!;
    const anon = await playwrightRequest.newContext({ baseURL });
    const last = `Porsyn03${runKey}`;

    const p = await linkPatient(seedRequisition, db, tenant, anon, last);

    // Not `toBeLessThan(400)` (D140): the register handler answers 302 on BOTH
    // success and several failure paths, so the destination is the assertion.
    expect(p.registerStatus, 'register POST status').toBe(302);
    expect(p.registerLocation, 'register redirect target')
      .toContain('wp-login.php?checkemail=registered');

    // One SELECT, not five (D164). Capabilities come from the live row (D178).
    const [row] = db.query(
      `SELECT u.ID, u.patient_id,
              (SELECT meta_value FROM wp_usermeta
                WHERE user_id = u.ID AND meta_key = 'wp_2_capabilities'),
              (SELECT meta_value FROM wp_usermeta
                WHERE user_id = u.ID AND meta_key = 'phone'),
              pa.pa_phone, pa.pa_email, pa.pa_first, pa.pa_last
         FROM wp_users u
         JOIN ci_patients pa ON pa.pa_ID = u.patient_id
        WHERE u.user_email = ${Db.q(p.email)}`,
    );
    expect(row, `no wp_users row joined to a patient for ${p.email}`).toBeTruthy();
    const [uid, patientId, caps, phoneMeta, paPhone, paEmail, paFirst, paLast] = row;

    expect(patientId, 'wp_users.patient_id points at the matched ci_patients row').toBe(p.paId);
    expect(paLast, 'bound to the right master row').toBe(last);
    expect(paFirst, 'bound to the right master row').toBe('Synthetic');
    // The `patient` role slug survives on blog 2 even though install_roles.php:541
    // removes it: WP_User::add_role writes the cap key without consulting WP_Roles.
    expect(caps, 'promoted to the patient role on the portal blog').toContain('"patient"');
    expect(phoneMeta, 'phone usermeta -- the notification cron joins on it').toBe(p.phone);
    // Back-fill: the saver left both columns NULL, registration fills them.
    expect(paPhone, 'ci_patients.pa_phone back-filled').toBe(p.phone);
    expect(paEmail, 'ci_patients.pa_email back-filled').toBe(p.email);

    // The set-password mail. Reaches mailhog only because the mu-plugin removed
    // ci-lab-client's pre_wp_mail hub relay (D117).
    const subjects = mailSubjectsTo(p.email);
    expect(subjects.length, `mailhog messages to ${p.email}`).toBe(1);
    expect(subjects[0], 'set-password mail').toContain('Login Details');

    console.log(`S-POR-03 lab=${tenant.prefix} assen=${p.assen} pa_ID=${p.paId} uid=${uid}`);
    await anon.dispose();
  });
});

// --------------------------------------------------------------- S-POR-03u

por.describe('S-POR-03u @por @mutates', () => {
  /**
   * S-POR-03u -- REGISTRATION AS THE PATIENT PERFORMS IT.
   *
   * D382: this ADDS to S-POR-03, it does not replace it. `linkPatient()` is
   * S-POR-04/05/07's precondition and stays exactly as it is; what follows is a
   * different POST to the same handler, and the difference is measured on the
   * pinned form (`ci_patients` 75ce2a83, `inc/login.php` -- byte-identical at
   * the pin, the only file that moved in that submodule is
   * `pages/registration_tests.php`):
   *
   *   - the form posts `address`, `city` and `zip`. The fixture posts NONE of
   *     the three, and `trcppr_register_new_user()` (:499-511) writes each one
   *     to `wp_usermeta`. Three rows no test in this suite has ever asserted,
   *     and the only way to create them is to fill this screen.
   *   - the form's submit is `wp-submit=Sign In`; the fixture posts
   *     `wp-submit=Register`. Different string, same button name.
   *   - `birthday` is a HIDDEN input driven by three <select>s (`.month`,
   *     `.day`, `.year` inside `.date-dropdowns`). The fixture types the ISO
   *     date straight in. Measured: the widget composes exactly `1950-01-01`,
   *     so this test asserts the widget agrees with the fixture rather than
   *     assuming it.
   *
   * D386 -- the read-back is a SERVER RENDER by construction: `#registerform`
   * is a full-page POST to `wp-login.php?action=register`, so the completion
   * screen asserted below IS core's own response to the registration. Nothing
   * here is DOM a click handler wrote.
   *
   * D396 -- this cannot race its HTTP twin. The twin registers
   * `Porsyn03<runKey>`; this one registers `Porsyn03u<runKey>`, a different
   * string, and every read is an exact match on that key or on the address
   * derived from it. Neither touches a shared table without a key.
   */
  por('a patient registers on the portal form and lands on the account the lab filed', async (
    { seedRequisition, db, tenant, runKey, page },
  ) => {
    const f = infra(db);
    const last = `Porsyn03u${runKey}`;
    const email = `${last.toLowerCase()}@example.invalid`;
    // The same 000-000-000X placeholder family as linkPatient(), and for the
    // same load-bearing reason (see its comment): a dialable-looking number
    // sends the SMS leg into an un-vendored gateway that fatals.
    const phone = '0000000198';

    // PRECONDITION. The lab files the specimen first -- registration resolves a
    // Patient Code against a row that already exists, so there is nothing to
    // register against until this runs. Seeded over HTTP on purpose: the staff
    // requisition is S-ORD-01u's subject, not this test's.
    const seeded = await seedRequisition(last);
    expect(seeded.status, 'the tox saver answers 200 on the requisition POST').toBe(200);
    const { assen } = expectBatch(db, seeded, tenant, { testGroupId: f.testGroupId });
    const paId = db.scalar(`SELECT patient_id FROM ci_batches WHERE assen = ${Db.q(assen)}`);
    expect(paId, 'the saver linked the batch to a ci_patients row').not.toBe('0');
    expect(
      db.scalar(`SELECT COUNT(*) FROM wp_users WHERE user_email = ${Db.q(email)}`),
      'no portal account for this patient before the patient registers',
    ).toBe('0');

    // ------------------------------------------------------------ the screen
    // The built-in `page` is ANONYMOUS (fixtures/test.ts: every project sets
    // `storageState: undefined`), which is what someone registering IS.
    await page.goto(`${PORTAL}/wp-login.php?action=register`);
    await expect(
      page.getByText('Register For This Site', { exact: true }),
      'the patient is looking at the portal registration screen',
    ).toBeVisible();

    // `user_email` is the one control addressed by `name=` rather than by role:
    // it is the ONLY field carrying a `label[for]`, that label's text is EMPTY,
    // and an empty label wins over the placeholder in the accessible-name
    // computation -- measured, `getByRole('textbox', { name: 'Email' })`
    // resolves 0 while every other box resolves 1 off its `title`. D383
    // fallback 3.
    await page.locator('input[name="user_email"]').fill(email);
    await page.getByRole('textbox', { name: 'Phone Number' }).fill(phone);
    await page.getByRole('textbox', { name: 'Patient Code' }).fill(assen);
    await page.getByRole('textbox', { name: 'First Name' }).fill('Synthetic');
    await page.getByRole('textbox', { name: 'Last Name' }).fill(last);

    // BIRTHDATE. Three unlabelled <select>s whose first option is their own
    // name ("Month"/"Day"/"Year"), so `getByRole('combobox', { name })`
    // resolves 0 for all three; the `.month`/`.day`/`.year` classes are what
    // the widget's OWN script keys on to compose the hidden `birthday` field
    // (D383 fallback 4). The year list floors at 1905, so wave 0.4's
    // 1950-01-01 is reachable where the old 1900-01-01 was not.
    const dob = page.locator('.date-dropdowns');
    await dob.locator('select.month').selectOption('01');
    await dob.locator('select.day').selectOption('01');
    await dob.locator('select.year').selectOption('1950');
    await expect(
      page.locator('input[name="birthday"]'),
      'the three dropdowns compose the ISO date the master row carries',
    ).toHaveValue('1950-01-01');

    // The block the fixture cannot send.
    await page.getByRole('textbox', { name: 'Address' }).fill('5-Test-Way');
    await page.getByRole('textbox', { name: 'City' }).fill('Testville');
    await page.getByRole('textbox', { name: 'Zip' }).fill('60601');

    await page.getByRole('button', { name: 'Sign In' }).click();
    await page.waitForLoadState('load');

    // THE OUTCOME THE PATIENT IS SHOWN. Core's own completion screen, rendered
    // by the POST response. The failure it distinguishes is the one that
    // matters: an unresolvable Patient Code re-renders THIS SAME form carrying
    // "<b>Error</b>: Please correct Patient Code!" (`inc/login.php:446`) and no
    // completion message, at the same URL and the same HTTP 200 -- so the
    // status code decides nothing here (D185) and the twin's Location header is
    // not what a person sees.
    await expect(
      page.getByText('Registration complete. Please check your email, then visit the login page.'),
      'the patient is told the account was created',
    ).toBeVisible();
    await expect(
      page.getByRole('textbox', { name: 'Patient Code' }),
      'the registration form is gone -- this is the completion screen, not a re-render with errors',
    ).toHaveCount(0);

    // ---------------------------------------------------------------- the DB
    // One SELECT, not eight (D164). The three address rows are the columns only
    // this screen can write.
    const [row] = db.query(
      `SELECT u.ID, u.patient_id,
              (SELECT meta_value FROM wp_usermeta
                WHERE user_id = u.ID AND meta_key = 'wp_2_capabilities'),
              (SELECT meta_value FROM wp_usermeta
                WHERE user_id = u.ID AND meta_key = 'phone'),
              (SELECT meta_value FROM wp_usermeta
                WHERE user_id = u.ID AND meta_key = 'address'),
              (SELECT meta_value FROM wp_usermeta
                WHERE user_id = u.ID AND meta_key = 'city'),
              (SELECT meta_value FROM wp_usermeta
                WHERE user_id = u.ID AND meta_key = 'zip'),
              pa.pa_last, pa.pa_phone, pa.pa_email
         FROM wp_users u
         JOIN ci_patients pa ON pa.pa_ID = u.patient_id
        WHERE u.user_email = ${Db.q(email)}`,
    );
    expect(row, `no wp_users row joined to a patient for ${email}`).toBeTruthy();
    const [uid, patientId, caps, phoneMeta, addr, city, zip, paLast, paPhone, paEmail] = row;

    // The binding. The Patient Code the patient typed is an accession; what it
    // must resolve to is the master row the SAVER created, not merely some row
    // with the right surname.
    expect(patientId, 'the account is bound to the ci_patients row the lab filed').toBe(paId);
    expect(paLast, 'and that row is this patient').toBe(last);
    expect(caps, 'the account can sign in to the portal blog as a patient').toContain('"patient"');
    expect(phoneMeta, 'the number the results cron joins on').toBe(phone);

    // THE ADDRESS BLOCK -- three wp_usermeta rows that exist only because a
    // browser filled this form. The HTTP twin posts none of the three, so this
    // is the assertion D382 exists for.
    expect([addr, city, zip], 'the address the patient typed is kept on the account')
      .toEqual(['5-Test-Way', 'Testville', '60601']);

    // Back-fill: the saver left both columns NULL, registration fills them.
    expect(paPhone, 'ci_patients.pa_phone back-filled from the form').toBe(phone);
    expect(paEmail, 'ci_patients.pa_email back-filled from the form').toBe(email);

    const subjects = mailSubjectsTo(email);
    expect(subjects.length, `mailhog messages to ${email}`).toBe(1);
    expect(subjects[0], 'the set-password mail the completion screen promised')
      .toContain('Login Details');

    console.log(`S-POR-03u lab=${tenant.prefix} assen=${assen} pa_ID=${paId} uid=${uid}`);
  });
});

// ---------------------------------------------------------------- S-POR-04

por.describe('S-POR-04 @por', () => {
  por('the results-notification cron tells the patient once, and only once', async (
    { seedRequisition, db, tenant, runKey }, testInfo,
  ) => {
    const baseURL = testInfo.project.use.baseURL!;
    const anon = await playwrightRequest.newContext({ baseURL });
    const portalUrl = `${baseURL}${PORTAL}/`;
    const last = `Porsyn04${runKey}`;

    const p = await linkPatient(seedRequisition, db, tenant, anon, last);
    insertApprovedReport(db, p, tenant);

    // notify_sms_results.php:22 refuses to run within 1200 s of its last start.
    // Clearing the throttle is what makes the SECOND run below a real test of
    // the ci_sent_notify ledger rather than a test of the throttle -- exactly the
    // D140 "passes for the wrong reason" shape.
    const clearThrottle = () =>
      db.query("DELETE FROM wp_2_options WHERE option_name = 'tr_sms_results_sending'");

    clearThrottle();
    runCron('cron_notify_sms', { url: portalUrl });

    const ledger = () => db.query(
      `SELECT send_type FROM ci_sent_notify WHERE mid = ${Db.q(p.mid)} AND btype = 'tox'`,
    ).map((r) => r[0]).sort();

    // 'rse' (email) is the leg that can fire here: the SMS leg calls the same
    // Bandwidth gateway whose library fails to load, so send_sms() returns false
    // and writes no 'rs' row. The catalog allows either.
    expect(ledger(), 'one ci_sent_notify row after the first cron run').toEqual(['rse']);

    const subjects = mailSubjectsTo(p.email);
    expect(subjects, 'results mail delivered to the patient')
      .toContain('Your test results are now available!');

    clearThrottle();
    runCron('cron_notify_sms', { url: portalUrl });
    expect(ledger(), 'a second cron run adds no second row -- ci_sent_notify is the dedup ledger')
      .toEqual(['rse']);
    expect(mailSubjectsTo(p.email).length, 'and sends no second mail').toBe(subjects.length);

    console.log(`S-POR-04 lab=${tenant.prefix} assen=${p.assen} mid=${p.mid}`);
    await anon.dispose();
  });
});

// ---------------------------------------------------------------- S-POR-05

por.describe('S-POR-05 @por', () => {
  /**
   * EXPECTED FAILURE, not a skip: the cron runs, resolves the batch to the
   * portal user and reaches its INSERT, but writes to
   * `{$wpdb->prefix}ci_user_notifications` (`cron_check_user_notifications.php:53`)
   * while `ci_patients/inc/install.php:65` creates the table UNPREFIXED. On blog
   * 2 the prefix is `wp_2_`, so every insert targets a table that does not exist
   * and the row is silently lost -- while `last_approve_log_id` still advances,
   * so the approval is consumed and can never be retried.
   *
   * Measured: `ci_user_notifications` 0 rows before and after,
   * `last_approve_log_id` 58 -> 59, and `SHOW TABLES LIKE '%user_notifications%'`
   * returns only the unprefixed name.
   *
   * Written as the catalog's assertion under `test.fail()` so it turns RED the
   * day the prefix is fixed, instead of quietly encoding the bug as correct.
   */
  por('an approved batch produces an in-app notification for the patient', async (
    { seedRequisition, db, tenant, runKey }, testInfo,
  ) => {
    const baseURL = testInfo.project.use.baseURL!;
    const anon = await playwrightRequest.newContext({ baseURL });
    const last = `Porsyn05${runKey}`;

    const p = await linkPatient(seedRequisition, db, tenant, anon, last);

    // The cron's trigger is an approval entry in the staff audit log.
    db.query(
      `INSERT INTO ci_log_users (user_id, user_action, mid, btype, created, lab_id, assen)
       VALUES (${Db.q(p.userId)}, 'approve_results', ${Db.q(p.mid)}, 'tox', NOW(),
               ${Db.q(tenant.labId)}, ${Db.q(p.assen)})`,
    );
    runCron('cron_check_user_notifications', { url: `${baseURL}${PORTAL}/` });

    expect(
      db.scalar(`SELECT COUNT(*) FROM ci_user_notifications WHERE user_id = ${Db.q(p.userId)}`),
      'one ci_user_notifications row for the patient (flow-patient-portal-and-app.md:105)',
    ).toBe('1');

    await anon.dispose();
  });
});

// ---------------------------------------------------------------- S-POR-06

test.fixme(
  'S-POR-06 @por slot booking decrements availability -- BLOCKED: ci_scheduling.count_req is ' +
  'written in exactly one place, add_covid_sample.php:935-936, reachable only from the ' +
  'WooCommerce order path, and no page carries the ci_registration_tests shortcode (D049)',
  () => {},
);

// ---------------------------------------------------------------- S-POR-07

por.describe('S-POR-07 @por', () => {
  por('one patient cannot fetch another patient\'s result PDF', async (
    { seedRequisition, db, tenant, runKey }, testInfo,
  ) => {
    const baseURL = testInfo.project.use.baseURL!;
    const anon = await playwrightRequest.newContext({ baseURL });
    // Patient A owns the session; patient B owns the report.
    const patientA = await playwrightRequest.newContext({
      baseURL, storageState: path.join(STATE_DIR, 'e2e_patient.json'),
    });
    const uidA = db.scalar("SELECT ID FROM wp_users WHERE user_login = 'e2e_patient'");
    const b = await linkPatient(seedRequisition, db, tenant, anon, `Porsyn07${runKey}`);
    insertApprovedReport(db, b, tenant);
    expect(b.userId, 'patient B has its own wp_users row').not.toBe(uidA);

    // The gate is `ci_patients/inc/init.php:76-98`: it splits the filename on
    // '_' and compares segment [2] with the CURRENT user id. Filename-based, not
    // row-based -- flagged weak in flow-result-distribution.md:244 and asserted
    // here as the behaviour that actually ships.
    const pdfUrl = (ownerUid: string) =>
      `${PORTAL}/?tr_action=patient_result_pdf&file=rs_MTQ_${ownerUid}.pdf`;

    // "Data effect: none" -- snapshot B's report row, byte for byte, around both
    // requests. Scoped to B's accession, so a concurrent spec cannot move it.
    const reportRow = () => db.query(
      `SELECT id, estatus, approve_date, final_results, lab_id
         FROM ci_reports WHERE patient_id = ${Db.q(b.assen)} ORDER BY id`,
    );
    const before = reportRow();
    expect(before.length, 'patient B has exactly one report').toBe(1);

    const denied = await patientA.get(pdfUrl(b.userId), { maxRedirects: 0 });
    const allowed = await patientA.get(pdfUrl(uidA), { maxRedirects: 0 });

    // Both answers are 302, so the status code proves nothing (D140). The
    // destination is the outcome: the login page, or the signed lab-blog URL.
    expect(denied.status(), 'cross-patient request is a redirect, not a document').toBe(302);
    expect(denied.headers()['location'], 'patient A is bounced to login, not handed B\'s PDF')
      .toContain('/patients/wp-login.php');
    expect(denied.headers()['location'], 'and no signed PDF link is minted')
      .not.toContain('ci_results_pdf');

    expect(allowed.headers()['location'], 'patient A DOES get a signed link for its own file')
      .toContain('tr_action=ci_results_pdf');
    expect(allowed.headers()['location'], 'signed link carries an auth token').toContain('auth=');

    expect(reportRow(), 'ci_reports is unchanged by either request').toEqual(before);

    console.log(`S-POR-07 lab=${tenant.prefix} A=${uidA} B=${b.userId} assen=${b.assen}`);
    await anon.dispose();
    await patientA.dispose();
  });
});

// ------------------------------------------------- D108, the identity hazard

por.describe('mutation-on-GET reaches authorization state @por @mutates', () => {
  /**
   * D108. One `GET /patients/wp-admin/` grants the VISITING user the `patient`
   * role and creates blog membership (`ci_patients/inc/admin.php:890-906`), so a
   * test that merely browses the portal silently changes a persona's identity.
   *
   * Asserted deliberately rather than tripped over: the precondition (the
   * persona has no blog-2 role) is established first, so the grant is the only
   * thing the GET could have done. The persona is this file's own tenant
   * persona, so the identity that moves is one no other spec is scoped to.
   */
  por('one GET of the portal admin grants the visitor the patient role', async (
    { db, tenant }, testInfo,
  ) => {
    const baseURL = testInfo.project.use.baseURL!;
    const uid = db.scalar(`SELECT ID FROM wp_users WHERE user_login = ${Db.q(tenant.login)}`);
    expect(uid, 'tenant persona exists').not.toBe('');

    const caps = () =>
      db.scalar(`SELECT meta_value FROM wp_usermeta
                  WHERE user_id = ${Db.q(uid)} AND meta_key = 'wp_2_capabilities'`);

    // The grant fires only when the visitor has NO role on the portal blog, so
    // clear whatever a previous run of THIS test left behind.
    db.query(`DELETE FROM wp_usermeta
               WHERE user_id = ${Db.q(uid)} AND meta_key = 'wp_2_capabilities'`);
    expect(caps(), 'precondition: no portal-blog role').toBe('');
    // The LIS role on blog 1 is untouched and stays untouched.
    const blog1Before = db.scalar(`SELECT meta_value FROM wp_usermeta
                                    WHERE user_id = ${Db.q(uid)} AND meta_key = 'wp_capabilities'`);

    const ctx = await playwrightRequest.newContext({ baseURL, storageState: tenant.statePath });
    const res = await ctx.get(`${PORTAL}/wp-admin/`, { maxRedirects: 0 });

    // admin.php:897 redirects to the same URL with a cache-buster after granting.
    expect(res.status(), 'the portal admin bounces the freshly-granted visitor').toBe(302);
    expect(res.headers()['location'], 'the ?v= cache-buster from admin.php:897').toContain('v=');

    expect(caps(), `a single GET granted ${tenant.login} a portal-blog role`)
      .toContain('"patient"');
    expect(db.scalar(`SELECT meta_value FROM wp_usermeta
                       WHERE user_id = ${Db.q(uid)} AND meta_key = 'wp_capabilities'`),
      'the blog-1 LIS role is not rewritten').toBe(blog1Before);

    console.log(`D108 ${tenant.login}(${uid}) blog2 caps -> ${caps()}`);
    await ctx.dispose();
  });
});

// ------------------------------------------------------- S-ORD-04 / S-ORD-05

test.fixme(
  'S-ORD-04 @ord @por paid portal order becomes one batch per (test x patient) -- BLOCKED: ' +
  'WooCommerce is not vendored, so the zero-total path of D047 does not exist. Measured: ' +
  'POST /patients/ with tr_act=ci_checkout_tests -> HTTP 500, "Call to undefined function ' +
  'wc_maybe_define_constant()" at ci_checkout_tests.php:5',
  () => {},
);

test.fixme(
  'S-ORD-05 @ord @por a second order-status transition does not duplicate batches -- BLOCKED: ' +
  'its precondition is a completed S-ORD-04 (an order carrying _accessions meta), and both ' +
  'woocommerce_order_status_* hooks it rides are WooCommerce actions that never fire here',
  () => {},
);

// ------------------------------------------------ OTP passwordless backdoor

por.describe('OTP passwordless backdoor @por @mutates', () => {
  /**
   * The passwordless OTP endpoint accepts the hardcoded code `7112` (when
   * `tr_debug2` is present) for ANY patient, from an ANONYMOUS caller, and mints
   * a full WordPress auth session for that patient.
   *
   *   - `class-otpa.php:736` -- `$input_code === '7112' && isset($_REQUEST['tr_debug2'])`
   *     short-circuits the real code comparison and returns true.
   *   - It is reachable anonymously because the nonce guard at
   *     `class-otpa.php:288` reads `if (empty($nonce) && ! wp_verify_nonce(...))`
   *     -- an `&&` where an `||` was meant, so any NON-empty nonce satisfies it
   *     without a valid nonce.
   *   - On success `class-otpa-passwordless.php:176` calls `wp_set_auth_cookie()`
   *     for the user the attacker-supplied `payload[identifier]` (a phone
   *     number, the active bandwidth gateway's `identifier_meta`) resolves to.
   *
   * CORRECT behaviour, asserted here: an anonymous caller presenting `7112`
   * gets NO auth cookie and NO server session. The product grants both, so this
   * test fails today; `por.fail()` records that and turns the run RED the day
   * the backdoor and the `&&` bug are fixed. Never assert the takeover as
   * correct -- that would bake the CVE in as expected behaviour.
   *
   * Every fact is a REQUEST + a DB read (D386): the `wordpress_logged_in`
   * Set-Cookie the anonymous caller receives, AND a fresh `session_tokens`
   * usermeta row on the victim -- a bare 200 with `{"success":true}` writes
   * neither, so neither can be faked by the handler answering OK.
   */
  por('an anonymous caller cannot mint a patient session with the 7112 code', async (
    { db }, testInfo,
  ) => {
    const baseURL = testInfo.project.use.baseURL!;

    // A throwaway patient. Phone derived from the new user id so it is UNIQUE:
    // the gateway's WP_User_Query must resolve exactly one user, or the session
    // it mints is not provably this one's (find_user, class-otpa.php:939).
    const created = wpEval(
      '$id=wp_insert_user(["user_login"=>"e2e_otpbd_" . wp_generate_password(8,false),'
      + '"user_pass"=>wp_generate_password(),"role"=>"subscriber"]);'
      + '$ph="9" . str_pad($id,9,"0",STR_PAD_LEFT);'
      + 'update_user_meta($id,"phone",$ph);'
      + '$o=["id"=>$id,"phone"=>$ph];',
    );
    const uid = String(created.id);
    const phone = String(created.phone);
    expect(Number(uid), 'the throwaway patient was created').toBeGreaterThan(0);

    const sessionTokens = () => db.scalar(
      `SELECT meta_value FROM wp_usermeta WHERE user_id = ${Db.q(uid)} AND meta_key = 'session_tokens'`,
    );
    expect(sessionTokens(), 'precondition: the fresh patient has no server session').toBe('');

    const loggedInCookie = (res: import('@playwright/test').APIResponse) =>
      res.headersArray()
        .filter((h) => h.name.toLowerCase() === 'set-cookie')
        .some((h) => h.value.startsWith('wordpress_logged_in'));

    const post = (code: string, extra: Record<string, string> = {}) => {
      const c = anon; // captured below
      return c.post(`${PORTAL}/otpa-api/otp`, {
        maxRedirects: 0,
        form: {
          nonce: 'any-nonstring',            // non-empty: the && bug passes it
          otpType: 'passwordless',
          handler: 'verify_otp_code',
          'payload[identifier]': phone,
          'payload[code]': code,
          ...extra,
        },
      });
    };

    const anon = await playwrightRequest.newContext({ baseURL });

    // Non-vacuity control on the SAME endpoint and SAME victim: a WRONG code is
    // correctly refused -- no auth cookie, no session. This proves the endpoint
    // CAN say no and the backdoor assertion below is a real bypass.
    const wrong = await post('9999', { tr_debug2: '1' });
    const wrongCookie = loggedInCookie(wrong);
    const tokensAfterWrong = sessionTokens();

    // THE BACKDOOR: 7112 with tr_debug2, anonymous.
    const forged = await post('7112', { tr_debug2: '1' });
    const forgedCookie = loggedInCookie(forged);
    const tokensAfterForged = sessionTokens();

    await anon.dispose();

    // Clean up the throwaway user BEFORE asserting, so the (expected) throw
    // below cannot leave it behind.
    wpEval(
      'if(!function_exists("wpmu_delete_user")) require_once(ABSPATH."wp-admin/includes/ms.php");'
      + `wpmu_delete_user(${Number(uid)}); $o=["deleted"=>true];`,
    );

    // Controls pass today (endpoint refuses a bad code).
    expect(wrongCookie, 'a wrong code mints no logged-in cookie').toBe(false);
    expect(tokensAfterWrong, 'a wrong code writes no server session').toBe('');

    // CORRECT behaviour -- fails today, so the takeover is pinned, not encoded.
    expect(
      forgedCookie,
      'an anonymous 7112 request was handed a wordpress_logged_in cookie for the patient '
        + '(class-otpa.php:736 backdoor + :288 && nonce bug) -- anyone can log in as any patient',
    ).toBe(false);
    expect(
      tokensAfterForged,
      'and the server minted a real session_tokens row for that patient from an anonymous POST',
    ).toBe('');

    console.log(`OTP-7112 uid=${uid} forgedCookie=${forgedCookie} tokensAfter=${tokensAfterForged !== ''}`);
  });
});

// ------------------------------------------------ anonymous forged result PDF

por.describe('anonymous forged result PDF @por @mutates', () => {
  /**
   * The FRONT-END handler `ci_results_pdf` (`inc/actions.php:10`) is hooked on
   * `init` with no `is_user_logged_in()` gate, and its authorisation check
   * (`inc/actions.php:34`) compares the attacker-supplied `auth` token against
   * the attacker-supplied `file` name -- no HMAC, no session. `trpdd_patient_encode`
   * is a reversible base-36 of `report_id + 10000000` and report ids are
   * sequential, so a valid token can be forged for any released report.
   *
   * CORRECT behaviour, asserted here: an anonymous forged request does NOT
   * return a PDF. The product returns `application/pdf` with a `%PDF-` body, so
   * this fails today; `por.fail()` reds the run when the endpoint gains a real
   * signature/session check. This is DISTINCT from S-POR-07, which covers the
   * portal `patient_result_pdf` handler behind a session -- this one needs no
   * cookie at all.
   */
  por('an anonymous forged token cannot download a released result PDF', async (
    { seedRequisition, db, tenant, runKey }, testInfo,
  ) => {
    const baseURL = testInfo.project.use.baseURL!;
    const anon = await playwrightRequest.newContext({ baseURL });

    const p = await linkPatient(seedRequisition, db, tenant, anon, `Porforge${runKey}`);
    insertApprovedReport(db, p, tenant);
    const reportId = db.scalar(`SELECT id FROM ci_reports WHERE patient_id = ${Db.q(p.assen)}`);
    expect(reportId, 'the approved report exists to be stolen').not.toBe('');

    // Forge exactly what actions.php:34 accepts: id(idt) === id(auth), uid
    // matching in file and auth, ftime within the hour.
    const enc = String(wpEval(`$o=["e"=>trpdd_patient_encode(${Number(reportId)})];`).e);
    const uid = '4242';
    const auth = Buffer.from(`${Math.floor(Date.now() / 1000)}_${uid}_${reportId}`).toString('base64');
    const file = `tox_${enc}_${uid}.pdf`;

    const forged = await anon.get(
      `/?tr_action=ci_results_pdf&auth=${encodeURIComponent(auth)}&file=${encodeURIComponent(file)}`,
      { maxRedirects: 0 },
    );
    const forgedBody = await forged.body();

    // Non-vacuity control: SAME endpoint, SAME file, MALFORMED auth is refused
    // with the plaintext expiry notice -- the check runs, so the forge is a real
    // bypass and not a dead route.
    const bad = await anon.get(
      `/?tr_action=ci_results_pdf&auth=YmFk&file=${encodeURIComponent(file)}`,
      { maxRedirects: 0 },
    );
    const badBody = await bad.body();

    await anon.dispose();

    // Matched as a PATTERN, not as the literal `file is expiry` inc/actions.php:34 emits
    // today. This control runs INSIDE a por.fail() body, so if a fix reworded the refusal
    // this assertion would fail, be absorbed as the expected failure, and the pin below
    // would never run -- green over a fixed product.
    expect(badBody.toString('utf8'), 'a malformed token is refused in plain text')
      .toMatch(/expir|invalid|denied|not allowed/i);
    expect(bad.headers()['content-type'] ?? '', 'and that refusal is not a document')
      .not.toContain('application/pdf');

    // CORRECT behaviour is refusal; the product serves the PDF, so this throws
    // today and is pinned, never asserted as correct.
    const forgedCt = forged.headers()['content-type'] ?? '';
    const servedPdf = forgedCt.includes('application/pdf')
      && forgedBody.subarray(0, 5).toString('latin1') === '%PDF-';
    expect(
      servedPdf,
      `an anonymous forged token was served a ${forgedBody.length}-byte %PDF result document `
        + '(inc/actions.php:34 compares two caller-controlled values, no HMAC/session)',
    ).toBe(false);

    console.log(`FORGE-PDF assen=${p.assen} rid=${reportId} enc=${enc} servedPdf=${servedPdf}`);
  });
});
