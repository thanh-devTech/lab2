# Dependencies

## WordPress core (not committed)

WordPress core is **not** stored in this repo. It is pinned at **6.8.5** and installed/refreshed with:

```bash
scripts/install-wp-core.sh
# equivalently, with WP-CLI:
#   wp core download --version=6.8.5 --skip-content --force
```

This populates `wp-admin/`, `wp-includes/`, and the root `wp-*.php` (all **gitignored**), and
leaves `wp-config.php` and `wp-content/` untouched. Run it after cloning and as part of deploy.

- The pinned version lives in `scripts/install-wp-core.sh` (`WP_VERSION`).
- **Upgrade WordPress:** bump `WP_VERSION`, run the script, test.
- Integrity is verifiable against `https://api.wordpress.org/core/checksums/1.0/?version=<v>&locale=en_US`.

## Plugin dependencies

External libraries used by the `wp-content/plugins/ci-*` plugins are managed per-plugin with
Composer — see each plugin's `composer.json` and `DEPENDENCIES.md`. Their `vendor/` dirs are
gitignored; run `composer install` in each.
