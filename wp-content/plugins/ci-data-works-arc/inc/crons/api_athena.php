<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include(TRCPDD_PATH . 'api/ci_api_hl7_athena.php');
$log_file_orders = WP_CONTENT_DIR . '/log/log_athena.txt';
$log_file_results = WP_CONTENT_DIR . '/log/log_athena_results.txt';

$options = get_option('tr_hl7_athena_st', array());

touch($log_file_orders);

$f_log_orders = fopen($log_file_orders, 'a+');
$f_log_results = fopen($log_file_results, 'a+');

try {
    $client = new CI_Api_Hl7_Athena($options);

    $client->connect();
    $client->login($options['username'], $options['password']);
    echo 'logged in<br>';
    $client->sync_reqs();

    if ($f_log_orders) {
        if (!empty($client->log)) {
            fwrite($f_log_orders, $client->log . "\n");
            fwrite($f_log_orders, date_i18n('Y-m-d H:i:s') . ' => end ' . "\n");
        }
    }

    $client->sync_reports();
} catch (Exception $e) {
    Tr_Session_Class::add_error($e->getMessage());
}



try {
    $client = new CI_Api_Hl7_Athena($options);

    $client->connect();
    $client->login($options['results_username'], $options['results_password']);
    echo 'logged in<br>';

    $client->sync_reports();

    if ($f_log_results) {
        if (!empty($client->log)) {
            fwrite($f_log_results, $client->log . "\n");
            fwrite($f_log_results, date_i18n('Y-m-d H:i:s') . ' => end ' . "\n");
        }
    }
} catch (Exception $e) {
    Tr_Session_Class::add_error($e->getMessage());
}



fclose($f_log_orders);
fclose($f_log_results);

if (filesize($log_file_orders) > 9000000) {
    file_put_contents($log_file_orders, substr(file_get_contents($log_file_orders), 8000000));
}

if (filesize($log_file_results) > 9000000) {
    file_put_contents($log_file_results, substr(file_get_contents($log_file_results), 8000000));
}
