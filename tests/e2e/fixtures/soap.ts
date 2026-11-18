import { expect, type APIRequestContext } from '@playwright/test';

/**
 * The hub <-> satellite SOAP channel (D204/D238 -- this was stranded in
 * hub.spec.ts).
 *
 * A raw envelope rather than a SOAP library: the binding is `style="rpc"
 * use="encoded"` with namespace `http://localhost/wsdl?wsdl` (read off the
 * served WSDL), which is four lines of string, and adding a dependency for it
 * would be the only new third-party surface in the whole framework.
 *
 * The machine channel carries NO WP role (personas.md:236-237): a SOAP caller's
 * authority is a `ci_wsdl_keys` row, not a capability, so these calls are made
 * by an anonymous request context.
 */
export const WSDL_NS = 'http://localhost/wsdl?wsdl';

export const escXml = (s: string) =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export const unescXml = (s: string) =>
  s.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&amp;/g, '&');

/**
 * One rpc/encoded SOAP call to this instance's own hub endpoint.
 *
 * Returns the response with XML entities decoded ONCE, because the handler
 * answers a string parameter whose content is itself XML -- the payload arrives
 * as `&lt;xml&gt;&lt;status&gt;...`.
 */
export async function soap(
  request: APIRequestContext,
  op: string,
  args: Record<string, string>,
): Promise<string> {
  const parts = Object.entries(args)
    .map(([n, v]) => `<${n} xsi:type="xsd:string">${escXml(v)}</${n}>`)
    .join('');
  const res = await request.post('/wsdl', {
    headers: { 'Content-Type': 'text/xml; charset=utf-8', SOAPAction: `"${WSDL_NS}${op}"` },
    data:
      '<?xml version="1.0" encoding="UTF-8"?>' +
      '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"' +
      ` xmlns:ns1="${WSDL_NS}" xmlns:xsd="http://www.w3.org/2001/XMLSchema"` +
      ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' +
      ' SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">' +
      `<SOAP-ENV:Body><ns1:${op}>${parts}</ns1:${op}></SOAP-ENV:Body></SOAP-ENV:Envelope>`,
  });
  const raw = unescXml(await res.text());
  // Transport-level only. The BUSINESS outcome is always read out of the
  // payload and cross-checked against the database -- never
  // `toBeLessThan(400)`, which a login redirect would satisfy (D140).
  expect(res.status(), `SOAP transport for ${op}`).toBe(200);
  expect(raw.includes('Fault'), `SOAP fault from ${op}: ${raw.slice(0, 400)}`).toBe(false);
  return raw;
}

/** `<tag>value</tag>` out of the handler's own XML payload (put_* operations). */
export const soapField = (body: string, tag: string) =>
  (body.match(new RegExp(`<${tag}>([^<]*)</${tag}>`)) ?? [, ''])[1];

/** `<key>name</key><value>…</value>` out of a SOAP-ENC Map (get_req_by_assen). */
export const soapMapField = (body: string, name: string) =>
  (body.match(new RegExp(`<key[^>]*>${name}</key><value[^>]*>([^<]*)</value>`)) ?? [, ''])[1];
