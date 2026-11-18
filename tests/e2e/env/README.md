# E2E environment

Ephemeral, egress-denied LIS instance that mounts **this repo** as the WordPress root.

```sh
./up.sh --fresh   # destroy state, boot clean, verify   (one command, no manual steps)
./up.sh           # boot, reusing the existing DB volume
docker compose down -v
```

Then: <http://localhost/wp-admin/> — `Administrat0r` / `e2e-admin-pw`.

## Why it is shaped this way

| Piece | Reason |
|---|---|
| `networks.lis.internal: true` | **Safety boundary.** The app makes unsolicited outbound calls at plugin-load file scope and self-registers against the production hub (decisions D014–D018). Do not remove. |
| `proxy` service | Docker will not publish a host port from an `internal: true` network (measured: connection refused). The proxy is the only container on both networks; the app keeps no route off the host. |
| `wp-config.php` bind-mounted | The repo's own `wp-config.php` is never modified. |
| `apache-e2e.conf` | Multisite rewrites live in Apache config, so no `.htaccess` is added to the repo root. |
| `sql/20-domains.sql` | The seed carries `lab.mylab.com` / `patients.mylab.com`; multisite matches Host against `wp_blogs`, so they are rewritten to path-based `localhost` (D023, D024, D033). |
| `DISABLE_WP_CRON` | Several leak/destroy paths are cron-triggered, not UI-triggered (D018). |
| seed = `wp-content/default.sql` | The repo's own dump: full schema + reference data, **zero** patient/batch rows. Mounted read-only. |

`./egress-check.sh` and `./login-check.sh` are standalone and can be run against a
already-booted stack.

## Seeding and sessions (H2, H3)

```sh
./seed.sh                      # schema sync + fixtures + one tox requisition (~2.7 s)
./seed.sh --no-req             # fixtures only
./mint-session.sh e2e_sec "$E2E_STATE_DIR/e2e_sec.json"   # Playwright storageState, no login UI
cd .. && node env/verify-sessions.mjs             # drive the states in a real browser
```

Personas seeded: `e2e_sec` (lab_sec), `e2e_tech` (lab_tech), `e2e_dir` (administrator),
`e2e_patient` (portal, blog 2) - password `e2e-seed-pw`. All carry both `_lab_id` and
`_lab_ids` (D032).

### Reset (H5)

```sh
./reset.sh --snapshot   # capture the current DB as the baseline (once, right after minting)
./reset.sh              # restore it -- median 1.27 s, 8 reps
```

`reset.sh` does `DROP DATABASE` + `CREATE` + import, not a bare `mysql <` import. Measured:
a bare import erases D035's runtime `ALTER TABLE ADD COLUMN` but leaves behind any table
*created* after the snapshot; dropping the schema costs ~0.05 s and closes that. It restores
data, schema drift, `wp_users`/`wp_usermeta`/`wp_<N>_capabilities` and blog membership
(D108's role grant included). It does **not** touch `wp-content/uploads/` -- that is a bind
mount onto the real repo and survives even `docker compose down -v`.

### Order matters

```sh
./up.sh --fresh                                 # 19 s
./seed.sh                                       #  2.8 s
for u in Administrat0r e2e_sec e2e_tech e2e_dir e2e_patient; do
  ./mint-session.sh "$u" "$E2E_STATE_DIR/$u.json"     #  0.3 s each
done
./reset.sh --snapshot                           #  0.7 s
```

Minting **after** the snapshot produces state files that are already invalid: WP validates
the auth cookie against a session token in `wp_usermeta` and a fragment of the password
hash, and `--fresh` regenerates both. The failure mode is HTTP **200 on the login page**, so
`expect(status).toBe(200)` passes and the test fails later on a missing row.

### Parallel workers (H4)

`mint-session.sh` pins `PHPSESSID` (`E2E_PIN_PHPSESSID`, default
`e2eshared0000000000000000000000ab`) into every state file. This is load-bearing, not
cosmetic: `ci-data-works-arc/admin/init.php:2` calls `session_start()` on every admin
request and PHP's `files` handler locks per session id, which is the only thing serialising
concurrent writes. Without the pin, Playwright workers each get their own session and race
`tr_get_new_assen()` -- measured **18.3 % of requisitions silently lost at `--workers>=2`**,
HTTP 200 throughout. Recommended: `--workers=4` locally, `--workers=2` in CI.

### Boundary stub (H6)

`WPMU_PLUGIN_DIR` points at `mu-plugins/` here, not at `wp-content/mu-plugins`; the stub
re-includes the repo's own mu-plugins so nothing is lost. It filters `pre_http_request`
(fixtures in `fixtures/`, everything else refused, one JSON line per call to
`/tmp/e2e-http-intercepts.log`) and points `wp_mail()` at mailhog. It is **response shaping,
not containment**: 44 of 68 integrations use raw `curl_init`/`SoapClient`/`ssh2_*` and never
reach a WP filter. The `internal: true` network is the boundary.

`seed.sh` step 0 creates `/tmp/e2e-http-intercepts.log` **root-owned, 0666**, and the ownership is
the load-bearing half. Two processes share the file: the stub appends as `www-data` from inside a
request, and `fixtures/stub-http.ts` truncates it through `docker compose exec` as root. Both
opens carry `O_CREAT`, and in a sticky world-writable directory `fs.protected_regular` refuses
that open with `EACCES` unless the file belongs to the caller or to the directory's owner -- mode
and uid 0 are both irrelevant, the check runs ahead of any capability. `/tmp` is root-owned, so a
root-owned file satisfies both writers. It only ever worked on a Mac because Docker Desktop's
LinuxKit VM leaves `fs.protected_regular` at `0`. World-writable is fine here: a synthetic log of
attempted calls, on tmpfs, in a disposable egress-denied container with no PHI in it.

`wp_mail()` needs one more thing: `ci-lab-client/inc/init.php:143` hooks `pre_wp_mail` at
priority 1 and relays every message -- body and base64 attachments -- to the production hub
instead of SMTP. The stub removes that filter on `plugins_loaded`, or mailhog stays empty.

Three things that are load-bearing and easy to undo by accident:

- `CI_ALL_BATCH_TYPES` in `wp-config.php` includes `tox`. That list gates both admin page
  registration and which batch tables the plugin installer maintains. Remove `tox` and the
  whole S-ORD scenario family stops working.
- `seed.sh` step 1 runs `ci-data-works-arc/inc/install.php`. The `default.sql` snapshot is
  older than the plugin (no `ci_batches.is_saved`), so without it every requisition save
  fails silently with HTTP 200.
- `wp-config.php` is a single-file bind mount. Editing it needs
  `docker compose restart wordpress` or the container keeps reading a truncated copy.

If `./up.sh --fresh` hangs with no output, it is the Docker credential helper (D095):

```sh
export DOCKER_CONFIG=/path/to/a/config/dir/without/credsStore
```

## Not included

- No redis: nothing drops in an `object-cache.php`, so it was dead weight.
- No wkhtmltopdf / xdebug: not on the boot path.

(Mailhog *is* now wired: `mu-plugins/00-e2e-boundary-stub.php` sets PHPMailer to
`mailhog:1025`. Its 8025 port is **not** published to the host — read the inbox from inside
the network, e.g.
`docker compose exec -T wordpress curl -s http://mailhog:8025/api/v2/messages`.)
