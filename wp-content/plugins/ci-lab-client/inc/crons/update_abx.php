<?php

if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $log, $wpdb, $ci_update_log;
$ci_update_log = '';
echo 'update abx<br>';
$log_file 	= WP_CONTENT_DIR . '/log/log_syncabx.txt';

if (!tr_get_option('ci_pdf_data', 'new_abx') && !isset($_GET['trajax'])) {
	echo 'Not enable abx';
	return;
}

$last_run = get_option('_ci_last_update_abx', 0);
if ($last_run > time()) {

	$seconds = 400 + filemtime($log_file) - time();
	echo 'Updating in background. Please try again after ' . $seconds . 's';
	$f 			= fopen($log_file, 'a+');
	fwrite($f, date_i18n('Y-m-d H:i:s') . '. Updating in background Please try again after ' . $seconds . "s\n");
	return;
}

update_option('_ci_last_update_abx', time() + 400, false);


$f 			= fopen($log_file, 'a+');
$start_time_tt = microtime(true);
$startby = '';
if (isset($_GET['trajax'])) {
	$user = wp_get_current_user();
	$startby = 'by hand in lab';
	$startby .= ' user: ' . $user->user_login;
	$startby .= ' ip: ' . $_SERVER['REMOTE_ADDR'];
} else {
	$startby = 'by LabConnect';
}

fwrite($f, date_i18n('Y-m-d H:i:s') . '. start update ' . $startby . "\n");

include_once(TRLCN_PATH . '/api/ci_lab_client.php');
$client = new CI_Lab_Client_Api(true);

$client->check_abx_version();
echo 'parse data:';

$abx_version 	= get_option('ci_abx_table_versions', array());
if (!isset($abx_version['lastversions'])) {
	$abx_version = array('tables' => array(), 'lastversions' => array(), 'version' => 0);
}
$named_version = $abx_version['named_version'] ?? '';

$limit_time = 400;
$start_time = time();

$done_count = 0;

fwrite($f, date_i18n('Y-m-d H:i:s') . '. count ' . count($abx_version['lastversions']) . "\n");

if (count($abx_version['lastversions']) == 0) {

	$client->check_abx_version();

	$nextcron = wp_next_scheduled('cron_update_abx');
	if ($nextcron == 0) {
		//wp_schedule_single_event(time() + 30, 'cron_update_abx');
	}

	echo 'cant load data';
	touch($log_file, time() - 800);
	return;
}
update_option('ci_abx_current_named_version', $named_version, false);

$i = 0;
foreach ($abx_version['lastversions'] as $table => $lastversion) {
	$i++;

	if (empty($table)) {
		unset($abx_version['lastversions'][$table]);
		continue;
	} else if (stripos($table, 'ci_abx_results_scores_') !== false) {
		continue;
	}
	$cversion = isset($abx_version['tables'][$table])  ? $abx_version['tables'][$table] : 0;

	$space = str_repeat(' ', 35 - strlen($table));
	$logrow = date_i18n('Y-m-d H:i:s') . " {$i}. {$table} {$space} => {$cversion} => {$lastversion}";
	$log .= $logrow;
	fwrite($f, $logrow);

	if (!empty($named_version) || $cversion != $lastversion) {
		$runstart = microtime(true);
		$client->logcount = 0;
		update_option('ci_abx_updating_data', time(), false);

		$updrs = $client->update_abx_table($table, $named_version);
		if ($updrs === 0) {
			$abx_version['tables'][$table] = 0;
			$logrow = " => not exist\n";
			$log .= $logrow . "\n";
			unset($abx_version['tables'][$table]);
			unset($abx_version['lastversions'][$table]);
			fwrite($f, $logrow);
			update_option('ci_abx_updating_data', 0, false);
			continue;
		} else if ($updrs) {
			$abx_version 	= get_option('ci_abx_table_versions', array());

			$logrow = " => done";
			$log .= $logrow;
			fwrite($f, $logrow);
			$done_count++;
		} else {
			$abx_version['tables'][$table] = 0;
			$logrow = " => error";
			$log .= $logrow;
			fwrite($f, $logrow);
		}
		update_option('ci_abx_updating_data', 0, false);


		$logrow = " => rows: {$client->logcount}\n";
		$log .= $logrow;
		fwrite($f, $logrow);

		fwrite($f, " => " . (microtime(true) - $runstart) . " \n");
	} else {
		$done_count++;

		$logrow = " => last version\n";
		$log .= $logrow;
		fwrite($f, $logrow);
	}

	if (time() - $start_time > $limit_time) {
		fwrite($f, date_i18n('Y-m-d H:i:s') . '. break ' . $table . "\n");
		break;
	}
}

update_option('ci_abx_updating_data', 0, false);

fwrite($f, date_i18n('Y-m-d H:i:s') . '. done: ' . $done_count . "\n");


if (count($abx_version['lastversions']) == $done_count) {
	fwrite($f, date_i18n('Y-m-d H:i:s') . '. last version => ' . $abx_version['version'] . "\n");
	update_option('ci_abx_last_version', $abx_version['version']);

	wp_clear_scheduled_hook('cron_update_abx');
	if (!empty($named_version)) {
		update_option('ci_abx_current_named_version', $named_version, false);
		unset($abx_version['named_version']);
		update_option('ci_abx_table_versions', $abx_version, false);
	} else {
		update_option('ci_abx_current_named_version', '', false);
	}
} else {
	$nextcron = wp_next_scheduled('cron_update_abx');
	if ($nextcron == 0) {

		fwrite($f, date_i18n('Y-m-d H:i:s') . '. update fail' . "\n");
	}
}
update_option('ci_abx_last_change', date_i18n('YmdHis'));
fwrite($f, date_i18n('Y-m-d H:i:s') . '. run time: ' . (microtime(true) - $start_time_tt) . "\n\n\n\n\n\n");

update_option('ci_abx_table_versions', $abx_version, false);
touch($log_file, time() - 800);
if ($f) {
	fclose($f);
}


if (file_exists($log_file) && filesize($log_file) > 500000) {
	file_put_contents($log_file, substr(file_get_contents($log_file), 400000));
}
touch($log_file, time() - 800);


update_option('_ci_last_update_abx', time(), false);
