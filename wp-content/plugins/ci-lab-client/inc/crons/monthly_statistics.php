<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
$log_dir = WP_CONTENT_DIR . '/log';
if (!file_exists($log_dir)) {
	wp_mkdir_p($log_dir);
}
$log_file = $log_dir . '/lab_client_monthly_statistics.txt';
if (!file_exists($log_file)) {
	touch($log_file);
}

if (filemtime($log_file) > time()) {
	return;
}
touch($log_file, time() + 1800);

include_once(TRLCN_PATH . '/api/ci_lab_client.php');
$client = new CI_Lab_Client_Api(true);
$st = get_option('ci_lab_client_st', array());
if (!isset($st['key']) || empty($st['key']) || !isset($st['keytime']) || $st['keytime'] < time() - 86400 * 7) {
	$client->get_key();
}

$offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
$local_now = current_time('timestamp');
$hook = current_filter();
$month_ts = $local_now;
if ($hook === 'cron_lab_client_end_month_statistics') {
	$month_ts = strtotime(date_i18n('Y-m-01', $local_now) . ' -1 day');
}

$from_date = date_i18n('Y-m-01', $month_ts);
$to_date = date_i18n('Y-m-t', $month_ts);
if ($hook === 'cron_lab_client_mid_month_statistics') {
	$to_date = date_i18n('Y-m-15', $month_ts);
}

$f_log = fopen($log_file, 'a+');
if ($f_log) {
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => start ' . $hook . ' from ' . $from_date . ' to ' . $to_date . "\n");
}

$start = strtotime($from_date);
$end = strtotime($to_date);
for ($date = $start; $date <= $end; $date = strtotime('+1 day', $date)) {
	$date_send = date_i18n('Y-m-d', $date);
	$rs = $client->send_statistics($date_send);
	if ($f_log) {
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => send: ' . $date_send . ' => ' . ($rs !== false ? 'Success' : 'Error') . "\n");
	}
}

if ($hook === 'cron_lab_client_mid_month_statistics' && !wp_next_scheduled($hook)) {
	$next_local = strtotime(date_i18n('Y-m-16 00:30:00', $local_now));
	if ($next_local <= $local_now) {
		$next_month = strtotime(date_i18n('Y-m-01', $local_now) . ' +1 month');
		$next_local = strtotime(date_i18n('Y-m-16 00:30:00', $next_month));
	}
	wp_schedule_single_event($next_local - $offset, $hook);
}
if ($hook === 'cron_lab_client_end_month_statistics' && !wp_next_scheduled($hook)) {
	$next_local = strtotime(date_i18n('Y-m-01 00:45:00', $local_now) . ' +1 month');
	if ($next_local <= $local_now) {
		$next_end_month = strtotime(date_i18n('Y-m-01', $local_now) . ' +2 months');
		$next_local = strtotime(date_i18n('Y-m-01 00:45:00', $next_end_month));
	}
	wp_schedule_single_event($next_local - $offset, $hook);
}

if ($f_log) {
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => end ' . $hook . "\n\n");
	fclose($f_log);
}

if (file_exists($log_file) && filesize($log_file) > 500000) {
	file_put_contents($log_file, substr(file_get_contents($log_file), 390000));
}
