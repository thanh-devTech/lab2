import { execFileSync, spawnSync } from 'node:child_process';
import * as path from 'node:path';

const ENV_DIR = path.resolve(__dirname, '..', 'env');

/**
 * Everything that has to run INSIDE the stack.
 *
 * There is no host-side shortcut for any of this: the `lis` network is
 * `internal: true`, and Docker SILENTLY DROPS a `ports:` publish on such a
 * network (D164 confirming D154). `db` has no host socket, mailhog has no host
 * port, and wp-cli only exists in the wordpress container. Cost is ~131 ms per
 * exec, so ask for everything in one call rather than three.
 *
 * Five copies of this `docker compose exec` boilerplate existed across
 * fixtures/db.ts, fixtures/stub-http.ts, por.spec.ts, sta.spec.ts and
 * bil.spec.ts before D238 pulled them here.
 */
export function dexec(service: string, ...cmd: string[]): string {
  return execFileSync('docker', ['compose', 'exec', '-T', service, ...cmd], {
    cwd: ENV_DIR,
    encoding: 'utf8',
    maxBuffer: 32 * 1024 * 1024,
  });
}

/** Marker prefix for wpEval's out-of-band channel. See the D203 note below. */
const MARK = 'E2EJSON';

export interface WpEvalOptions {
  /** Subsite to bootstrap. wp-cli needs one on multisite. */
  url?: string;
  /**
   * Define `DOING_CRON` BEFORE WordPress loads, via `--exec`.
   *
   * Load-bearing for cron work, not decoration: ci-data-works-arc includes
   * inc/crons.php only when DOING_CRON is already defined at plugin LOAD time
   * (plug.php:65), so defining it inside the eval body registers nothing and
   * every cron handler reads as absent. Measured: without --exec,
   * has_action('cron_api_billing_sftp') is false; with it, true.
   */
  docron?: boolean;
}

/**
 * Run PHP inside the running instance and read one JSON object back.
 *
 * D203 -- THE GOTCHA THIS EXISTS FOR. `wp eval` stdout is swallowed: something
 * in the plugin stack holds an output buffer open for the whole request, so
 * everything echoed from the eval body is discarded and stdout arrives as the
 * single character `1`. The way out is an out-of-band write to `php://stderr`
 * behind a marker -- stderr also carries this build's PHP 8 deprecation notices
 * from three unrelated eStore plugins, which is why the marker is needed and a
 * bare `tail -1` is not enough.
 *
 * `code` must assign the result to `$o`.
 */
export function wpEval(code: string, opts: WpEvalOptions = {}): Record<string, unknown> {
  const args = [
    'compose', 'exec', '-T', 'wordpress',
    'wp', '--allow-root', `--url=${opts.url ?? 'http://localhost'}`,
  ];
  if (opts.docron) args.push('--exec=define("DOING_CRON", true);');
  args.push(
    'eval',
    `${code} file_put_contents("php://stderr", ${JSON.stringify(MARK)} . json_encode($o) . "\\n");`,
  );

  const res = spawnSync('docker', args, {
    cwd: ENV_DIR,
    encoding: 'utf8',
    maxBuffer: 8 * 1024 * 1024,
  });
  const line = (res.stderr ?? '').split('\n').find((l) => l.startsWith(MARK));
  if (!line) {
    throw new Error(`wpEval produced no ${MARK} line.\nstdout: ${res.stdout}\nstderr: ${res.stderr}`);
  }
  return JSON.parse(line.slice(MARK.length));
}

/** Which of these hooks actually has a handler registered. */
export function cronHandlers(hooks: string[], opts: WpEvalOptions = {}): Record<string, boolean> {
  return wpEval(
    `$o=[];foreach(${JSON.stringify(hooks)} as $h){$o[$h]=(bool)has_action($h);}`,
    { docron: true, ...opts },
  ) as Record<string, boolean>;
}

/**
 * Fire ONE cron hook deterministically -- the D084 form.
 *
 * `DISABLE_WP_CRON` gates only `_wp_cron()` (core 6.8.2 cron.php:1015-1021), so
 * it does not block a direct `do_action` under DOING_CRON.
 *
 * NEVER `wp cron event run --due-now`. That fans the whole due queue out into
 * D017's partner-mutating SFTP -- `ci_api_sftp_emr.php:147` `ssh2_sftp_rename`
 * and `:178` `ssh2_sftp_rmdir` mutate a PARTNER's server, and
 * `ci_auto_upload_results` is scheduled daily and fires unprompted.
 */
export function runCron(hook: string, opts: WpEvalOptions = {}): void {
  wpEval(
    `$o=["fired"=>${JSON.stringify(hook)}];do_action(${JSON.stringify(hook)});`,
    { docron: true, ...opts },
  );
}

interface MailhogItem {
  Content: { Headers: Record<string, string[]> };
}

/**
 * Mailhog subjects for ONE recipient.
 *
 * Mail only reaches mailhog because the test mu-plugin removes ci-lab-client's
 * `pre_wp_mail` hub relay (D117). Mailhog has no host port (it is on the
 * `internal: true` network), so the read goes through the wordpress container.
 *
 * BY RECIPIENT, never a total count: mailhog is shared across the whole suite,
 * so a count assertion is a cross-spec race.
 */
export function mailSubjectsTo(addr: string): string[] {
  const raw = dexec(
    'wordpress', 'curl', '-s',
    `http://mailhog:8025/api/v2/search?kind=to&query=${encodeURIComponent(addr)}&limit=50`,
  );
  return (JSON.parse(raw).items as MailhogItem[]).map((i) => i.Content.Headers.Subject[0]);
}
