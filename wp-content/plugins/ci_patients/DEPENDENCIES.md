# Dependencies

PHP libraries are managed with **Composer**; the calendar UI is a vendored JS bundle.

## Pulling the libraries

```bash
composer install
```

Creates `vendor/` (**gitignored**). `plug.php` loads `vendor/autoload.php`.

- **Source of truth:** `composer.json` + `composer.lock`.
- **PHP:** 8.1+ (CI matrix targets **8.2 and 8.4**).

## What's managed here

| Library | Replaces (removed) | Notes |
|---------|--------------------|-------|
| `endroid/qr-code` ^5.1 | `inc/phpqrcode` | Legacy `QRcode::png()` via `inc/qrcode-compat.php` shim. |

## JS (not Composer)

- **FullCalendar v6.1.21** — `fullcalendar/index.global.min.js` (committed bundle). Upgraded from v5.3.2. Update by replacing that file from the FullCalendar v6 global build.
