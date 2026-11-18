import { dexec } from './container';

/**
 * Database access for assertions.
 *
 * NOT mysql2 from the host. `db` sits on a network marked `internal: true`, and
 * Docker SILENTLY DROPS a `ports:` publish on such a network -- tested, D164
 * confirming D154. There is no host-side socket that preserves containment, so
 * every query goes through the container (see fixtures/container.ts). Cost:
 * median 131 ms per exec, which is why a spec should ask for everything it
 * needs in ONE select rather than three (see expectBatch, which does exactly
 * that).
 */
export class Db {
  /** Rows as arrays of column strings. Headerless, tab-separated, NULL -> ''. */
  query(sql: string): string[][] {
    const out = dexec('db', 'mariadb', '-uroot', '-proot', 'lab', '-N', '-B', '-e', sql)
      .replace(/\n$/, '');
    return out === '' ? [] : out.split('\n').map((r) => r.split('\t').map((c) => (c === 'NULL' ? '' : c)));
  }

  /** First column of the first row, or ''. */
  scalar(sql: string): string {
    const rows = this.query(sql);
    return rows.length ? rows[0][0] : '';
  }

  /** Escape a value for single-quoted SQL. Test data only; no user input here. */
  static q(v: string | number): string {
    return `'${String(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
  }
}
