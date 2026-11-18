import { dexec } from './container';

export interface Intercept {
  url: string;
  method: string;
  action: 'stubbed' | 'refused' | 'observed_only';
  t: number;
}

/**
 * Read side of the mu-plugin boundary stub (D066/D116).
 *
 * SHAPING ONLY. This can see nothing but calls that pass through
 * WP_Http::request(); 43 of the 68 integrations in 13-boundaries.md use raw
 * curl_init / SoapClient / ssh2_* / file_get_contents('http…') and never touch
 * it. The `internal: true` network is the boundary (D014/D015/D129); the log
 * below is how a test asserts WHICH call the app tried to make.
 */
export class StubHttp {
  /** Every wp_remote_* call the filter has seen since the last clear(). */
  intercepts(): Intercept[] {
    let raw = '';
    try {
      raw = dexec('wordpress', 'cat', '/tmp/e2e-http-intercepts.log');
    } catch {
      return []; // no calls yet -> no file
    }
    return raw
      .split('\n')
      .filter(Boolean)
      .map((l) => JSON.parse(l) as Intercept);
  }

  clear(): void {
    dexec('wordpress', 'sh', '-c', ': > /tmp/e2e-http-intercepts.log');
  }
}
