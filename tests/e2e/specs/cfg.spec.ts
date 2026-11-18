import { test as e2e, expect } from '../fixtures/test';

/**
 * CFG -- the CI Data Works settings panel (`page=ci_pdf_data&pagetab=this`,
 * registered at `ci-data-works-arc/inc/admin.php:226-238`), and specifically
 * its Fax tab.
 *
 * WHY THIS FILE EXISTS AND WHY IT IS NOT IN adm.spec.ts. `adm.spec.ts` asserts
 * the page GATE (who reaches `page=ci_pdf_data`) and reads `ci_pdf_data` as a
 * shared network option; nothing in the suite had yet DRIVEN the panel itself.
 * DWA-E16 is a defect in the panel's own markup, so it needs a browser on that
 * screen.
 *
 * NOTHING HERE MUTATES. `ci_pdf_data` is ONE serialized option shared by every
 * blog (adm.spec.ts:63, :691-714, :778), and two sibling specs read gates out of
 * it while this file runs. Saving the panel would rewrite that blob for the
 * whole parallel run, so this spec never submits the form: it clicks the Fax tab
 * and reads. No `@mutates`, no tenant, no db.
 *
 * PERSONA. `Administrat0r`, and that is forced rather than chosen: the panel
 * hides `.efaxinfo_tab` -- the whole Fax section -- behind
 * `inc/ci_pdf_data-options.php:67`, `$user->user_login == 'Administrat0r'`.
 * That user_login gate is DWA-E17 and is filed separately; here it is only the
 * reason no lab administrator persona can reach the controls under test.
 */

const ADMIN = 'Administrat0r';

/** `pagetab=this` is the "Other Settings" tab (`inc/admin.php:194`), the one this file drives. */
const SETTINGS = '/wp-admin/admin.php?page=ci_pdf_data&pagetab=this';

/**
 * What `getByLabel(<text>)` actually reaches, as a string, so that "no control"
 * and "the wrong control" are two distinct readings rather than one.
 *
 * Returns the `name` attribute of the single control the label resolves to --
 * the name the browser SUBMITS, which is the only identity the option blob
 * shares with the form. Returns `<n> controls` when the label resolves to
 * anything other than exactly one element, so a failure message says whether
 * the label reached nothing or reached several.
 */
async function labelReaches(page: import('@playwright/test').Page, label: string): Promise<string> {
  const control = page.getByLabel(label, { exact: true });
  const n = await control.count();
  if (n !== 1) return `${n} controls`;
  return String(await control.first().getAttribute('name'));
}

/** Open the panel and bring the Fax section into view, as an administrator does. */
async function openFaxTab(page: import('@playwright/test').Page) {
  await page.goto(SETTINGS);
  // The in-page tab strip, not the WP nav: `<a class="nav_tab_link" href="#eFaxInfo_tab">Fax</a>`.
  await page.getByRole('link', { name: 'Fax', exact: true }).click();
  // The section is on screen before anything is counted. This also states the
  // sighted half of DWA-E16: the words ARE rendered.
  await expect(page.getByText('Auto Fax Time', { exact: true }),
    'the Fax section shows the bench an "Auto Fax Time" caption').toBeVisible();
  await expect(page.getByText('Fax API', { exact: true }),
    'the Fax section shows the bench a "Fax API" caption').toBeVisible();
}

e2e.describe('DWA-E16 -- Fax settings labels @cfg @a11y', () => {
  /**
   * THE POSITIVE CONTROL, and the only reason the pin below is not vacuous.
   *
   * `getByLabel` works on this panel, on this tab, in this browser context: the
   * checkbox two lines above the failing selects resolves by its visible caption
   * and answers with the name it submits. `show_field_end()`
   * (`admin/classes/tr_admin_page_class_v11.php:1571`) emits the same
   * `<label for=…>` for a checkbox that `show_field_begin()` emits for a select
   * -- but `show_field_checkbox` ALSO emits `id='auto_fax_approve_pcr'` on the
   * input, so the association closes.
   *
   * This is deliberately NOT inside the pinned test: an assertion that fails
   * inside a `fail()` body is indistinguishable from the pinned failure, so a
   * control placed there would prove nothing.
   */
  e2e('the Auto Fax when Approved PCR checkbox is reachable by its own caption', async ({ pageAs }) => {
    const page = await pageAs(ADMIN);
    await openFaxTab(page);

    expect(
      await labelReaches(page, 'Auto Fax when Approved PCR'),
      'a screen reader following the "Auto Fax when Approved PCR" label reaches the '
      + 'auto_fax_approve_pcr checkbox',
    ).toBe('auto_fax_approve_pcr');

    await expect(
      page.getByLabel('Auto Fax when Approved PCR', { exact: true }),
      'and the control that label names is on screen',
    ).toBeVisible();
  });

  /**
   * DWA-E16, PINNED AS AN EXPECTED FAILURE (D175).
   *
   * MEASURED at the submodule pin a7aa074d, 2026-08-25 -- §7.4 of the migration
   * plan rated this one "recon only, not re-confirmed", so it was re-measured
   * here before the test was written:
   *
   *     getByLabel('Auto Fax Time')              -> 0
   *     getByLabel('Fax API')                    -> 0
   *     getByLabel('Auto Fax when Approved PCR') -> 1  (name=auto_fax_approve_pcr)
   *
   * WHY. `show_field_begin()` (`tr_admin_page_class_v11.php:1551`) emits
   * `<label for='fax_time'>` for EVERY field type, but `show_field_select()`
   * (`:1827`) writes `id='…'` only inside its `multiple` branch. `fax_time` and
   * `fax_api` (`inc/ci_pdf_data-options.php:276`, `:283`) are single selects, so
   * they render `name='fax_time'` with NO id and the `for=` points at an element
   * that does not exist. Sighted users see the caption -- asserted in
   * `openFaxTab` -- and assistive technology reads an unlabelled combobox.
   *
   * NOT WORKED AROUND. A `locator('select[name="fax_time"]')` would find the
   * control and hide the entire defect, so the pin is written with the API that
   * the broken association actually breaks.
   *
   * THE ASSERTION IS STRICTER THAN "resolves to 1": it names the control the
   * label must reach. Moving the `id` onto the wrapper `<div class="field">`
   * would make `getByLabel` resolve 1 and still leave the combobox unlabelled;
   * that repair answers `null` here and stays failing.
   *
   * HOW IT FLIPS. Add `id='{$field['id']}'` to the non-multiple branch of
   * `show_field_select()` and this test reports "Expected to fail, but passed"
   * and goes RED -- which is the day this file should be told. Verified by
   * injecting exactly that change, 2026-08-25.
   *
   * `e2e.fail()` is INSIDE the body on purpose: at describe scope it would
   * annotate the control above and mask a real regression there.
   */
  e2e('the Auto Fax Time and Fax API captions are reachable as labels', async ({ pageAs }) => {
    const page = await pageAs(ADMIN);
    await openFaxTab(page);

    // One assertion carrying both fields, so the report shows what each label
    // reached rather than stopping at the first.
    expect(
      {
        'Auto Fax Time': await labelReaches(page, 'Auto Fax Time'),
        'Fax API': await labelReaches(page, 'Fax API'),
      },
      'the Fax panel prints both captions but their <label for=…> point at ids no element '
      + 'carries, so assistive technology reaches neither select -- and neither does getByLabel',
    ).toEqual({ 'Auto Fax Time': 'fax_time', 'Fax API': 'fax_api' });
  });
});
