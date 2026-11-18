# Dependencies

PHP libraries are managed with **Composer**.

## Pulling the libraries

```bash
composer install
```

Creates `vendor/` (**gitignored**).

- **Source of truth:** `composer.json` + `composer.lock`.
- **PHP:** 8.1+ (CI matrix targets **8.2 and 8.4**).

## What's managed here

| Library | Replaces (removed) | Notes |
|---------|--------------------|-------|
| `endroid/qr-code` ^5.1 | merged `phpqrcode.php` | Legacy `QRcode::png()` via `qrcode-compat.php` shim. |

## Bundled (not Composer-managed)

- `Image/` — `Image_Barcode2` (PEAR barcode library), used for 1D barcodes.
