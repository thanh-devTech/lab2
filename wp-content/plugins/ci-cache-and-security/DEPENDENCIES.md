# Dependencies

Bundled tooling is managed with **Composer** (incl. `composer/installers` for the WordPress plugin).

## Pulling the libraries

```bash
composer install
```

This installs `vendor/` **and** places the managed WordPress plugin under `plugins/` (both
**gitignored**). `plug.php` includes `plugins/query-monitor/query-monitor.php` — the same path
Composer installs to, so no code change is needed.

- **Source of truth:** `composer.json` + `composer.lock`.
- **PHP:** 8.1+ (CI matrix targets **8.2 and 8.4**).

## What's managed here

| Package | Replaces (untracked) | Notes |
|---------|----------------------|-------|
| `wpackagist-plugin/query-monitor` ^3.17 | bundled `plugins/query-monitor/` | `composer/installers` puts it at `plugins/query-monitor/` (currently 3.20.4). |
