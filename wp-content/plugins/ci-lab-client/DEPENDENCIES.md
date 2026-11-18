# Dependencies

External PHP libraries are managed with **Composer** instead of being committed to the repo.

## Pulling the libraries

After cloning or pulling this plugin, run:

```bash
composer install
```

This creates `vendor/` (which is **gitignored** — never commit it). Without it the plugin's
managed libraries won't load.

- **Source of truth:** `composer.json` + `composer.lock` (commit both).
- **Update one library:** `composer update <vendor>/<package>` then test.
- **PHP:** 8.1+ (CI matrix targets **8.2 and 8.4**).

## What's managed here

| Library | Replaces | Notes |
|---------|----------|-------|
| `phpseclib/phpseclib` ^3 | vendored `phpseclib/` 1.x (removed) | Now namespaced `phpseclib3\`. Used in `inc/init.php` for PuTTY‑PPK → OpenSSH key conversion. |
