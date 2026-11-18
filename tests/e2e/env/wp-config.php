<?php
/**
 * E2E test wp-config. Bind-mounted over /var/www/html/wp-config.php at runtime.
 * The repo's own wp-config.php is never modified.
 */

if (function_exists('mysqli_report')) {
	mysqli_report(MYSQLI_REPORT_OFF);
}

define('DB_NAME',     getenv('WORDPRESS_DB_NAME')     ?: 'lab');
define('DB_USER',     getenv('WORDPRESS_DB_USER')     ?: 'lab');
define('DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: 'lab');
define('DB_HOST',     getenv('WORDPRESS_DB_HOST')     ?: 'db');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Fixed salts: reproducible cookies across rebuilds, so a minted Playwright
// storageState stays valid when the stack is recreated.
define('AUTH_KEY',         'e2e-auth-key-do-not-use-in-production-0001');
define('SECURE_AUTH_KEY',  'e2e-secure-auth-key-do-not-use-in-production-02');
define('LOGGED_IN_KEY',    'e2e-logged-in-key-do-not-use-in-production-0003');
define('NONCE_KEY',        'e2e-nonce-key-do-not-use-in-production-0004');
define('AUTH_SALT',        'e2e-auth-salt-do-not-use-in-production-0005');
define('SECURE_AUTH_SALT', 'e2e-secure-auth-salt-do-not-use-in-production-06');
define('LOGGED_IN_SALT',   'e2e-logged-in-salt-do-not-use-in-production-0007');
define('NONCE_SALT',       'e2e-nonce-salt-do-not-use-in-production-0008');

$table_prefix = 'wp_';

// --- Multisite: PATH-based (D024: wp_sitemeta.subdomain_install = '0') ---
define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('DOMAIN_CURRENT_SITE', getenv('E2E_DOMAIN') ?: 'localhost');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);
define('COOKIE_DOMAIN', false); // D029: host-only cookies

// --- Safety (D014-D018) ---
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true); // defence in depth only; the network is the boundary
define('WP_ACCESSIBLE_HOSTS', '');
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

// --- Test-only mu-plugins (H6 boundary stub) ---
// Kept OUT of the repo's wp-content/mu-plugins; the stub file re-includes the
// repo's own mu-plugins so nothing is lost by redirecting the directory.
define('WPMU_PLUGIN_DIR', __DIR__ . '/tests/e2e/env/mu-plugins');
define('WPMU_PLUGIN_URL', 'http://localhost/tests/e2e/env/mu-plugins');

// --- App constants (mirrors lab/wp-config.php) ---
// tox is commented out in the repo's own wp-config.php. The S-ORD scenario
// family is toxicology, and without tox here the app neither registers
// page=tox_req_form nor lets its installer maintain the tox batch table.
define('CI_ALL_BATCH_TYPES', array(
	0  => 'tox',
	2  => 'blood',
	16 => 'pcr',
));
define('RECOVERY_MODE_EMAIL', 'e2e@localhost');

// A4/R12: the plugin's own file-based query cache (tr_cache_query.php) serves
// reads up to 200 s stale, which turns a just-written row into an intermittently
// missing one. tr_cache_query.php:22 returns false before touching the cache
// file when this constant is set, so one define kills it at source. tmpfs does
// NOT solve this -- reset.sh does not recreate the container, so the tmpfs is
// never cleared mid-run.
define('RECACHEQUERIES', 1);

// --- Debug: log, never render (rendered notices would break DOM assertions) ---
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', '/tmp/wp-debug.log');
@ini_set('display_errors', '0');

define('FS_METHOD', 'direct');
define('WP_CACHE', false);

if (! defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
