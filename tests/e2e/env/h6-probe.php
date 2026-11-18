<?php
/**
 * H6 probe. Drives two REAL integration call sites from 13-boundaries.md:
 *   #2  hub IP echo   -> wp_remote_get()  (catchable by pre_http_request)
 *   #39 Labelary ZPL  -> raw curl_init()  (NOT catchable)
 * Run: wp eval-file h6-probe.php <remote|curl|mail>
 */
$mode = isset($args[0]) ? $args[0] : 'remote';

if ($mode === 'remote') {
    // Exactly what parts/instrument/ins_roche_c311.php:4 does before calling it at :16.
    include_once(TRCPDD_PATH . 'inc/functions_instrument.php');
    // Under Apache this is set by the SAPI; wp-cli has no SERVER_ADDR, so fill it
    // in to make the fallback branch visible. Nothing else about the path changes.
    if (empty($_SERVER['SERVER_ADDR'])) { $_SERVER['SERVER_ADDR'] = '172.24.0.99'; }
    $t0 = microtime(true);
    $ip = trpdd_get_ip();                       // inc/functions_instrument.php:206-222
    printf("A) trpdd_get_ip() [integration #2, wp_remote_get] -> %s   in %.3fs\n",
           var_export($ip, true), microtime(true) - $t0);
    printf("   SERVER_ADDR fallback would have been: %s\n", var_export($_SERVER['SERVER_ADDR'], true));
    exit(0);
}

if ($mode === 'curl') {
    $t0 = microtime(true);
    ob_start();
    // ci-lab-client/pages/admin_test.php:4 -> new CI_Zpl()
    include_once(WP_PLUGIN_DIR . '/ci-lab-client/api/ci_zpl.php');
    new CI_Zpl();                               // ci-lab-client/api/ci_zpl.php:8-24 (raw curl_init)
    $out = ob_get_clean();
    printf("B) new CI_Zpl() [integration #39, raw curl_init] -> %s   in %.3fs\n",
           var_export(substr(trim($out), 0, 160), true), microtime(true) - $t0);
    exit(0);
}

if ($mode === 'mail') {
    $ok = wp_mail('e2e-inbox@example.invalid', 'E2E boundary probe',
                  "Synthetic message from the H6 probe.\nNo PHI.\n");
    printf("C) wp_mail() -> %s\n", var_export($ok, true));
    global $phpmailer;
    if (isset($phpmailer)) {
        printf("   transport: Mailer=%s Host=%s Port=%s\n",
               $phpmailer->Mailer, $phpmailer->Host, $phpmailer->Port);
    }
    exit(0);
}
