<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
$log_file = WP_CONTENT_DIR . '/log/lab_client.txt';

$ftime = filemtime($log_file);
if ($ftime > time() && !isset($_GET['resetkey']) && !isset($_GET['testcron'])) {
	return;
}

touch($log_file, time() + 1000);

$st = get_option('ci_lab_client_st', array());

//check cron sync hub covid


if (isset($st['hub_covid']) && $st['hub_covid']) {
	$next_cron_hub = wp_next_scheduled('cron_lab_client_hub_send_covid');
	if (!$next_cron_hub) {
		wp_clear_scheduled_hook('cron_lab_client_hub_send_covid');
		//wp_schedule_event(time()+80,'10min','cron_lab_client_hub_send_covid');
	}
} else {
	wp_clear_scheduled_hook('cron_lab_client_hub_send_covid');
}

if ($st['dbv'] != TRLCN_V) {
	include_once(TRLCN_PATH . 'inc/install.php');
	$st['dbv'] = TRLCN_V;
	update_option('ci_lab_client_st', $st, false);
}

include_once(TRLCN_PATH . '/api/ci_lab_client.php');
$client = new CI_Lab_Client_Api(true);

if (!isset($st['key']) || empty($st['key']) || $st['keytime'] < time() - 86400 * 7 || isset($_GET['resetkey'])) {
	$rs = $client->get_key();
	echo 'request key : ';
	var_dump($rs);
	if (!$rs) {
		return;
	}
}



$date = date_i18n('Y-m-d');
$hour = date_i18n('H');
if ($hour < 6) {
	$date = date('Y-m-d', strtotime($date) - 86400);
}
if (isset($_GET['date'])) {
	$date = sanitize_text_field($_GET['date']);
}

if (isset($_GET['start_date'])) {

	$start_date = sanitize_text_field($_GET['start_date']);
	$st['start_date'] = date('Y-m-d', strtotime($start_date));
	$st['currentdate'] = date('Y-m-d', strtotime($start_date));
}

$f_log = fopen($log_file, 'a+');
if ($f_log) {
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => send:  ' . $date . "\n");
}

if (isset($_GET['testcron'])) {
	echo  date_i18n('Y-m-d H:i:s') . ' => send:  ' . $date . ' <br>';
}
$rs = $client->send_statistics($date);
if ($f_log) {
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => " . ($rs !== false ? 'Success' : 'Error') . "\n");
}

//sync previous day too
$rs2 = $client->send_statistics(date('Y-m-d', strtotime($date) - 86400));
if ($f_log) {
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => send previous day:  " . date('Y-m-d', strtotime($date) - 86400) . " => " . ($rs2 !== false ? 'Success' : 'Error') . "\n");
}

if (isset($st['start_date']) && !empty($st['start_date']) && $st['currentdate'] < $date) {

	if (empty($st['currentdate'])) {
		$st['currentdate'] = $st['start_date'];
	}
	$need_add_single_scheduling = false;
	for ($i = 0; $i <= 70; $i++) {
		$cdate = date('Y-m-d', strtotime($st['currentdate']) + 86400);
		if ($cdate < $date) {
			if ($f_log) {
				fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => send:  ' . $cdate . "\n");
			}


			$rs = $client->send_statistics($cdate);
			if (isset($_GET['testcron'])) {
				var_dump($cdate);
				var_dump($rs);
				echo '<br><br><br>';
			}
			if ($rs !== false) {
				$st['currentdate'] = $cdate;
				update_option('ci_lab_client_st', $st, false);
			} else {
				if ($f_log) {
					fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => send error  ' . "\n");
				}
				break;
			}
			$need_add_single_scheduling = true;
		} else {
			$st['currentdate'] = $date;
			$st['start_date'] = '';
			update_option('ci_lab_client_st', $st, false);
			break;
		}
	}
	if ($need_add_single_scheduling) {
		wp_schedule_single_event(time() + 30, 'cron_lab_client');
	}
} else if (!empty($st['start_date'])) {
	$st['currentdate'] = $date;
	$st['start_date'] = '';
	update_option('ci_lab_client_st', $st, false);
}

if ($f_log) {
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => end ' . "\n\n\n");
	fclose($f_log);
}

if (filesize($log_file) > 500000) {
	$olddata = substr(file_get_contents($log_file), 390000);
	file_put_contents($log_file, $olddata);
}
