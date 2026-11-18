<?php
/**
 * Mint a Playwright storageState for a WordPress user without touching the login UI.
 *
 * Run:  wp eval-file mint-session.php <user_login> [--skip-plugins --skip-themes --url=...]
 *
 * Method (D031): wp_set_auth_cookie() fires `set_auth_cookie` and `set_logged_in_cookie`
 * and only THEN consults the `send_auth_cookies` filter (pluggable.php:1104), so returning
 * false there suppresses the real setcookie() calls while both values have already been
 * captured. Nothing about the OTP flow is involved: the patient portal's passwordless login
 * ends in this same core call (class-otpa-passwordless.php:176).
 *
 * Emits Playwright storageState JSON on stdout.
 */

// NOTE: `wp eval-file` exposes its extra arguments as a LOCAL $args inside the
// included file, so `global $args` would blank it out.
$login = isset($args[0]) ? $args[0] : '';
$user  = get_user_by('login', $login);
if (!$user) {
    fwrite(STDERR, "no such user: {$login}\n");
    exit(1);
}

$host = parse_url(get_site_option('siteurl', home_url()), PHP_URL_HOST);
$host = getenv('E2E_COOKIE_DOMAIN') ?: ($host ?: 'localhost');

$captured = array();

add_action('set_auth_cookie', function ($cookie, $expire, $expiration, $uid, $scheme, $token) use (&$captured) {
    $name = ('secure_auth' === $scheme) ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
    // Core writes this one twice, at PLUGINS_COOKIE_PATH and ADMIN_COOKIE_PATH.
    $captured[] = array($name, $cookie, PLUGINS_COOKIE_PATH, $expire);
    if (ADMIN_COOKIE_PATH !== PLUGINS_COOKIE_PATH) {
        $captured[] = array($name, $cookie, ADMIN_COOKIE_PATH, $expire);
    }
}, 10, 6);

add_action('set_logged_in_cookie', function ($cookie, $expire, $expiration, $uid, $scheme, $token) use (&$captured) {
    $captured[] = array(LOGGED_IN_COOKIE, $cookie, COOKIEPATH, $expire);
    if (COOKIEPATH !== SITECOOKIEPATH) {
        $captured[] = array(LOGGED_IN_COOKIE, $cookie, SITECOOKIEPATH, $expire);
    }
}, 10, 6);

add_filter('send_auth_cookies', '__return_false');

wp_set_auth_cookie($user->ID, true);

if (empty($captured)) {
    fwrite(STDERR, "no cookies captured\n");
    exit(1);
}

$cookies = array();
$seen    = array();
foreach ($captured as $c) {
    list($name, $value, $path, $expire) = $c;
    $path = $path === '' ? '/' : $path;
    $key  = $name . '|' . $path;
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    $cookies[] = array(
        'name'     => $name,
        'value'    => $value,
        'domain'   => $host,
        'path'     => $path,
        'expires'  => (int) ($expire ?: time() + 86400),
        'httpOnly' => true,
        'secure'   => false,
        'sameSite' => 'Lax',
    );
}

// H4: the app calls session_start() on every admin request
// (ci-data-works-arc/admin/init.php:2) and PHP's `files` handler locks per
// session id, which is the only thing serialising concurrent writes. Playwright
// workers each get their own PHPSESSID unless one is pinned here -- measured
// 18.3 % silent row loss at --workers>=2 without it.
$pin = getenv('E2E_PIN_PHPSESSID');
if ($pin) {
    $cookies[] = array(
        'name' => 'PHPSESSID', 'value' => $pin, 'domain' => $host, 'path' => '/',
        'expires' => -1, 'httpOnly' => false, 'secure' => false, 'sameSite' => 'Lax',
    );
}

fwrite(STDERR, sprintf(
    "minted for %s (ID %d), %d cookie entries; COOKIEPATH=%s SITECOOKIEPATH=%s ADMIN_COOKIE_PATH=%s PLUGINS_COOKIE_PATH=%s COOKIE_DOMAIN=%s\n",
    $login, $user->ID, count($cookies),
    COOKIEPATH, SITECOOKIEPATH, ADMIN_COOKIE_PATH, PLUGINS_COOKIE_PATH,
    var_export(COOKIE_DOMAIN, true)
));

echo json_encode(array('cookies' => $cookies, 'origins' => array()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
