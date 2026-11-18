# Flake quarantine register

`retries: 0` is deliberate. A Playwright retry re-POSTs the same
`form_unique_id`, which makes the requisition saver `return` at HTTP 200 having
written nothing (D142) — so a retry can convert a real failure into a green
no-op. A test that flakes gets quarantined and fixed, never retried.

## Policy

1. Tag the test `@quarantine`. It stops gating and runs nightly only.
2. Add a row below. Owner comes from `CODEOWNERS`, never from prose.
3. Past `expiry`, the owner is paged. A quarantine with no expiry is a deletion
   with extra steps.

## Tag inventory

`--grep @nightly` on a run where no test carries the tag exits
`Error: No tests found`, so every tag in use must be listed here.
`env/select-suite.sh` always unions `@smoke`, so an empty tier cannot happen in
CI (D079).

| Tag | Meaning |
|---|---|
| `@smoke` | The never-subsetted floor (11 scenarios at full build; 3 today). |
| `@full` | Pre-merge tier beyond smoke. |
| `@nightly` | Nightly only — includes the D146 fault-injection pass (R7/D155). |
| `@serial` | Runs in the `serial` project at `workers: 1`, after `main`. |
| `@stub` | Depends on `env/mu-plugins/00-e2e-boundary-stub.php` response shaping. |
| `@mutates` | Mutates global or persona state; belongs in `serial.spec.ts`. |
| `@quarantine` | Flaking. Must have a row below. |
| `@ord @acc @tst @rev @dst @bil @sta @por @hub @auth` | Flow tags, one per spec file; `select-suite.sh` emits these. |

## Register

| id | test | owner | opened | expiry |
|---|---|---|---|---|
| _(empty)_ | | | | |
