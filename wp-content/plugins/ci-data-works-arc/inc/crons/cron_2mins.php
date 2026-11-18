<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
$log_file = WP_CONTENT_DIR . '/log/cron_2mins.txt';
$logf = fopen($log_file, 'a+');
$filemtime = filemtime($log_file);
if ($filemtime + 200 > time()) {
    return;
}
touch($log_file);

$phpruntime = 0;
try {
    if (function_exists('exec')) {
        exec("systemctl status php-fpm", $out, $code);
        if (is_array($out)) {
            foreach ($out as $o) {
                if (stripos($o, 'since') > 0) {
                    list($ee, $datet) = explode('since ', $o);
                    list($datet) = explode(';', $datet);

                    $phpruntime = strtotime($datet);
                }
            }
        }
    }
} catch (Exception $e) {
}

if ($phpruntime > 0 && $phpruntime + 200 > time()) {
    $_GET['testcron'] = true;
    if ($logf) {
        fwrite($logf, date_i18n('Y-m-d H:i:s') . ': just start php' . "\n");
    }

    include(TRCPDD_PATH . 'inc/crons/fresh_apis.php');
}

if ($logf) {
    fclose($logf);
}
