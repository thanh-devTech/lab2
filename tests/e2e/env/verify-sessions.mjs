// H3 verification: load each minted storageState into a REAL Chromium context and
// report what the browser actually reaches. Prints observations, not pass/fail, so
// the hypothesis is judged on evidence.
//
//   cd tests/e2e && node env/verify-sessions.mjs
//
// Prerequisite: ./env/seed.sh, then ./env/mint-session.sh <user> ../state/<user>.json
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const BASE = process.env.E2E_BASE_URL || 'http://localhost';
const STATE = new URL('../state/', import.meta.url).pathname;
const personas = ['Administrat0r', 'e2e_dir', 'e2e_sec', 'e2e_tech', 'e2e_patient'];

// D096: /wp-admin/ 302s to admin.php?page=welcome but still emits the dashboard body,
// so every check asserts on markup, never on the landing URL.
const screens = [
  ['blog1 dashboard', '/wp-admin/'],
  ['blog1 profile', '/wp-admin/profile.php'],
  ['blog2 dashboard', '/patients/wp-admin/'],
  ['blog2 front page', '/patients/'],
  ['LIS tox requisition (form_requisition)', '/wp-admin/admin.php?page=tox_req_form'],
  ['LIS pcr requisition (form_pcr)', '/wp-admin/admin.php?page=pcr_req_form'],
  ['LIS batches/inbound (ci_batches_view)', '/wp-admin/admin.php?page=ci_batches_view'],
  ['LIS tox results (ci_results)', '/wp-admin/admin.php?page=tox_results'],
  ['LIS short list (ci_short_list)', '/wp-admin/admin.php?page=ci_short_list'],
];

const read = (page, status) => page.evaluate((s) => ({
  status: s,
  url: location.href,
  adminBar: !!document.querySelector('#wpadminbar'),
  adminMenu: !!document.querySelector('#adminmenumain'),
  loginForm: !!document.querySelector('#loginform'),
  denied: /not allowed to access this page/i.test(document.body.innerText),
  who: (document.querySelector('#wp-admin-bar-my-account .ab-item')?.textContent || '').trim(),
  profileLogin: document.querySelector('#user_login')?.value ?? null,
  // data-mid is the one STABLE row selector in the app (D039/D065)
  mids: [...document.querySelectorAll('[data-mid]')].map((e) => e.getAttribute('data-mid')),
  menuItems: [...document.querySelectorAll('#adminmenu > li > a')]
    .map((a) => (a.textContent || '').replace(/\d+$/, '').trim()).filter(Boolean),
  bodyLen: document.body.innerHTML.length,
}), status);

const go = async (page, path) => {
  const res = await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
  return read(page, res?.status() ?? 0);
};

const browser = await chromium.launch();
const results = {};

console.log('=== 1. persona x screen matrix (real browser, minted storageState) ===');
for (const p of personas) {
  const ctx = await browser.newContext({
    storageState: JSON.parse(readFileSync(STATE + p + '.json', 'utf8')),
  });
  const page = await ctx.newPage();
  results[p] = {};
  console.log(`\n-- ${p} --`);
  for (const [label, path] of screens) {
    const r = await go(page, path);
    results[p][label] = r;
    console.log(`  ${label.padEnd(40)} HTTP ${String(r.status).padEnd(4)} bar=${r.adminBar ? 'Y' : 'n'} ` +
      `menu=${r.adminMenu ? 'Y' : 'n'} login=${r.loginForm ? 'Y' : 'n'} denied=${r.denied ? 'Y' : 'n'} ` +
      `bytes=${String(r.bodyLen).padStart(7)} who="${r.who}"`);
  }
  await ctx.close();
}

console.log('\n=== 2. anonymous control (no storageState) ===');
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  for (const [label, path] of screens) {
    const r = await go(page, path);
    console.log(`  ${label.padEnd(40)} HTTP ${String(r.status).padEnd(4)} login=${r.loginForm ? 'Y' : 'n'} url=${r.url}`);
  }
  await ctx.close();
}

console.log('\n=== 3. identity + tenancy (profile form + data-mid rows on Batches/Inbound) ===');
for (const p of personas) {
  const ctx = await browser.newContext({
    storageState: JSON.parse(readFileSync(STATE + p + '.json', 'utf8')),
  });
  const page = await ctx.newPage();
  const blog = p === 'e2e_patient' ? '/patients' : '';
  const id = await go(page, `${blog}/wp-admin/profile.php`);
  const bat = await go(page, '/wp-admin/admin.php?page=ci_batches_view');
  console.log(`  ${p.padEnd(15)} profile #user_login="${String(id.profileLogin).padEnd(14)}" ` +
    `batches denied=${bat.denied ? 'Y' : 'n'} data-mid=[${bat.mids.join(',')}]`);
  await ctx.close();
}

console.log('\n=== 4. admin menu diff (what lab_sec / lab_tech do NOT see) ===');
{
  const set = (p) => new Set(results[p]['blog1 dashboard'].menuItems);
  const a = set('Administrat0r'), s = set('e2e_sec'), t = set('e2e_tech');
  console.log(`  Administrat0r=${a.size}  e2e_sec=${s.size}  e2e_tech=${t.size} top-level items`);
  console.log(`  in admin, NOT in lab_sec : ${[...a].filter((x) => !s.has(x)).join(' | ')}`);
  console.log(`  in lab_sec, NOT in admin : ${[...s].filter((x) => !a.has(x)).join(' | ') || '(none)'}`);
  console.log(`  in admin, NOT in lab_tech: ${[...a].filter((x) => !t.has(x)).join(' | ')}`);
}

console.log('\n=== 5. D086: does ONE cookie set cover every subsite? ===');
{
  const full = JSON.parse(readFileSync(STATE + 'e2e_dir.json', 'utf8'));
  for (const c of full.cookies) console.log(`  minted: ${c.name}  domain=${c.domain}  path=${c.path}`);
  console.log(`  distinct hosts in storageState: ${new Set(full.cookies.map((c) => c.domain)).size}`);
  const minimal = { cookies: full.cookies.filter((c) => c.path === '/'), origins: [] };
  console.log(`  reduced to ${minimal.cookies.length} entries, all path="/", one host`);
  const ctx = await browser.newContext({ storageState: minimal });
  const page = await ctx.newPage();
  for (const u of ['/wp-admin/', '/patients/wp-admin/', '/patients/', '/wp-admin/admin.php?page=ci_batches_view']) {
    const r = await go(page, u);
    console.log(`    ${(BASE + u).padEnd(52)} HTTP ${r.status} ${r.loginForm ? 'LOGIN PAGE' : 'ok'} ` +
      `bar=${r.adminBar ? 'Y' : 'n'} who="${r.who}" mids=[${r.mids.join(',')}]`);
  }
  await ctx.close();
}

console.log('\n=== 6. cost: minted state vs driving wp-login.php ===');
{
  const state = JSON.parse(readFileSync(STATE + 'e2e_dir.json', 'utf8'));
  let t = Date.now();
  for (let i = 0; i < 5; i++) {
    const ctx = await browser.newContext({ storageState: state });
    const page = await ctx.newPage();
    const r = await go(page, '/wp-admin/');
    if (!r.adminBar || r.loginForm) throw new Error('minted context is not authenticated');
    await ctx.close();
  }
  console.log(`  storageState -> authenticated dashboard: ${((Date.now() - t) / 5).toFixed(0)} ms/context (5 runs)`);

  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  const captcha = await page.evaluate(() => {
    const el = document.querySelector('#captcha_code');
    return el ? { present: true, required: el.required } : { present: false };
  });
  console.log(`  wp-login.php #captcha_code present=${captcha.present} required=${captcha.required}`);
  await page.fill('#user_login', 'e2e_dir');
  await page.fill('#user_pass', process.env.E2E_SEED_PASSWORD || 'e2e-seed-pw');
  const noCaptcha = await Promise.all([
    page.waitForResponse((r) => r.url().includes('wp-login.php') && r.request().method() === 'POST',
      { timeout: 5000 }).catch(() => null),
    page.click('#wp-submit'),
  ]);
  console.log(`  submit with EMPTY captcha  -> POST made: ${noCaptcha[0] ? noCaptcha[0].status() : 'NO (browser blocked the required field)'}`);
  await page.fill('#captcha_code', 'xxxxx');
  const wrongCaptcha = await Promise.all([
    page.waitForResponse((r) => r.url().includes('wp-login.php') && r.request().method() === 'POST',
      { timeout: 15000 }).catch(() => null),
    page.click('#wp-submit'),
  ]);
  console.log(`  submit with WRONG captcha  -> POST made: ${wrongCaptcha[0] ? wrongCaptcha[0].status() : 'NO'}`);
  const after = await go(page, '/wp-admin/');
  console.log(`  authenticated after wrong captcha: ${after.adminBar && !after.loginForm}`);
  await ctx.close();
}

await browser.close();
