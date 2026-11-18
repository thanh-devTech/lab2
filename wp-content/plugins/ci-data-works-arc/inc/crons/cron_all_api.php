<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
//$class
$log_cron1 = date_i18n('Y-m-d H:i:s') . "\n";
$log_cron1 .= 'class: ' . $class . "\n";
if (empty($class)) {
	return;
}
$parms = isset($parms) ? $parms : array();

if ($class == 'testcron') {
	$logfile = WP_CONTENT_DIR . '/log/log_testcron.txt';
	$f = fopen($logfile, 'a+');
	fwrite($f, date_i18n('Y-m-d H:i:s') . '=>' . (isset($_GET) ? 'get' : 'cron') . "\n");
	fclose($f);
	echo 'testcron';
	return;
}

if (!empty($module)) {
	$log_cron1 .= 'module: ' . $module . "\n";
}

touch(WP_CONTENT_DIR . '/log/cron_all_api.txt');

if (!empty($module)) {
	$class_filepath = trpdd_get_file_path($module, strtolower($class));
} else {
	$class_file = strtolower($class) . '.php';
	$class_filepath = TRCPDD_PATH . 'api/' . $class_file;
}

$log_cron1 .= '$class_filepath: ' . $class_filepath . "\n";
if (!file_exists($class_filepath)) {
	$log_cron1 .= "not found file : {$class}";
} else {

	include_once($class_filepath);

	$log_cron = true;
	$log_file = WP_CONTENT_DIR . '/log/' . strtolower($class) . '.txt';
	$log_time_file = WP_CONTENT_DIR . '/log/time_' . strtolower($class) . '.txt';

	//check time last run
	$last_run = file_exists($log_time_file) ? file_get_contents($log_time_file) : 0;
	if ($last_run > time() - 300 && !isset($_GET['testcron'])) {
		return;
	}
	touch($log_file);
	file_put_contents($log_time_file, time());


	try {
		$client = new $class();
		$options = $client->options;
	} catch (Exception $e) {
		$log_cron1 .= "error: " . $e;
		$options['enable'] = false;
	}

	$log_cron1 .= json_encode($options);

	if (isset($options['enable']) && $options['enable']) {

		$f_log = fopen($log_file, 'a+');
		if ($log_cron && $f_log) {
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => start ' . (isset($_GET['testcron']) ? 'testcron' : 'cron') . "\n");
		}

		try {
			$client->cron($parms);
			//$client->log .= "done cron\n";
		} catch (Exception $e) {
			$client->log .= "error: {$e->getMessage()}\n";
			$log_cron1 .= "error: {$e->getMessage()}\n";
		}

		if (isset($_GET['testcron'])) {
			echo 'Cron Log: <br>';
			echo nl2br($client->log);
		}

		if ($log_cron && $f_log) {
			if (!empty($client->log)) {
				fwrite($f_log, $client->log . "\n");
			}
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => end ' . "\n\n\n");
		}

		if ($f_log) {
			fclose($f_log);
		}

		if (filesize($log_file) > 1000000) {
			$olddata = file_get_contents($log_file);
			$olddata = substr($olddata, 900000);
			file_put_contents($log_file, $olddata);
		}
	} else if (isset($_GET['testcron'])) {
		echo 'Cron not enabled!';
	}

	file_put_contents($log_time_file, 0);
}
if (isset($_GET['cronname'])) {
	echo nl2br($log_cron1);
}
file_put_contents(WP_CONTENT_DIR . '/log/cron_all_api.txt', $log_cron1);
