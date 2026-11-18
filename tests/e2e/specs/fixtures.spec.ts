import { test, expect } from '@playwright/test';
import { seedDays } from '../fixtures/batch-id';
import { expectScopeExcludes } from '../fixtures/assert-domain';

/**
 * THE FIXTURE MACHINERY'S OWN CHECK.
 *
 * `expectScopeExcludes` is the guard the scope tests lean on: it is what stops
 * an absence assertion passing over an empty render (D277). A guard nobody
 * checks is worse than no guard -- it is a green light nobody looks behind. So
 * the three ways it must RED are asserted here, by catching the throw.
 *
 * No stack, no database, no seed. Sibling of `serial.spec.ts:1`, which asserts
 * the project pairing for the same reason: infrastructure that is believed
 * rather than tested is how false-greens 1-8 all started.
 */
test.describe('fixture machinery', () => {
  const arm = 'test:0 fake clause';
  const base = { arm, observed: 'the list', own: 'OWN-1', foreign: 'FGN-2' };

  test('expectScopeExcludes passes when the clause really scopes', () => {
    expectScopeExcludes({ ...base, widened: ['OWN-1', 'FGN-2'], scoped: ['OWN-1'] });
    // Same claim against a rendered body rather than a parsed list.
    expectScopeExcludes({
      ...base,
      widened: '<td>OWN-1</td><td>FGN-2</td>',
      scoped: '<td>OWN-1</td>',
    });
  });

  test('it REFUSES an absence asserted over an empty render (D277)', () => {
    // The ofc.spec.ts:397 shape: the clause degraded to `in (  )`, the query
    // errored, nothing rendered. `not.toContain(foreign)` alone passes here.
    expect(() =>
      expectScopeExcludes({ ...base, widened: [], scoped: [] }),
    ).toThrow(/with the scope WIDENED/);
    // Widened can see it, but the scoped render is starved of the reader's own
    // row -- a denial body, a wrong slug, a day filter that excluded everything.
    expect(() =>
      expectScopeExcludes({ ...base, widened: ['OWN-1', 'FGN-2'], scoped: [] }),
    ).toThrow(/starved/);
  });

  test('it REDS on the leak it exists to catch', () => {
    expect(() =>
      expectScopeExcludes({ ...base, widened: ['OWN-1', 'FGN-2'], scoped: ['OWN-1', 'FGN-2'] }),
    ).toThrow(/leaked FGN-2/);
  });

  test('it REDS on markers that alias under toContain', () => {
    // 'LAL1234' contains 'LAL123': the twin would report present when absent.
    expect(() =>
      expectScopeExcludes({
        ...base, own: 'LAL123', foreign: 'LAL1234',
        widened: ['LAL123', 'LAL1234'], scoped: ['LAL123'],
      }),
    ).toThrow(/alias under toContain/);
    expect(() =>
      expectScopeExcludes({ ...base, own: 'X', foreign: 'X', widened: ['X'], scoped: ['X'] }),
    ).toThrow(/the same string/);
  });

  test('seedDays hands out distinct past days inside the 30-row page', () => {
    const day = seedDays();
    const got = [day(), day(), day(), day()];
    expect(new Set(got).size, 'two tests would land on one day').toBe(4);

    const midnight = new Date();
    midnight.setHours(0, 0, 0, 0);
    for (const s of got) {
      // The saver stores it verbatim (ci_save_form_requisition.php:63 only
      // auto-fills on empty/'auto'), and every day-grouped screen carries
      // `and ba.doc <= '<today> 23:59:59'` -- a future day is invisible.
      expect(s, `${s} is not the Y-m-d H:i:s the saver stores`)
        .toMatch(/^\d{4}-\d{2}-\d{2} 12:00:00$/);
      const d = new Date(s.replace(' ', 'T'));
      expect(d.getTime(), `${s} is not in the past`).toBeLessThan(midnight.getTime());
      const back = Math.round((midnight.getTime() - d.getTime()) / 86_400_000);
      // parts/tox/test_results.php:105 paginates the day list at 30 rows,
      // `bdoc desc`. Past that an allocated day falls off page 1.
      expect(back, `${s} is ${back} days back, off page 1 of the day list`)
        .toBeLessThanOrEqual(16);
    }
  });
});
