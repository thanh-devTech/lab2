<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
	trpdd_plugin_activate();
}

$log_file = WP_CONTENT_DIR . '/log/cron_fresh_apis.txt';
if (time() - filemtime($log_file) < 300 && !isset($_GET['testcron'])) {
	return;
}
touch($log_file);
$logf = fopen($log_file, 'a+');

//refresh fax
include(TRCPDD_PATH . 'api/ci_main_fax.php');
$fax = new CI_Main_Fax();
try {
	if ($fax->is_expired()) {
		$fax->refreshToken();
	}
} catch (Exception $e) {
}


//refersh box
try {

	$api = new CI_Box_Works();
	$options = $api->options;
	//refresh after a day
	if ($options['access_token'] && $options['time'] < time() - 86400) {
		$options['time'] = time();
		$api->update_options($options);
		$api->refresh();
	}
} catch (Exception $e) {
}




include_once(TRCPDD_PATH . 'inc/define_instruments.php');
include_once(TRCPDD_PATH . 'inc/functions_instrument.php');


foreach ($define_instruments as $k => $arr) {
	//auto start instrument
	if (tr_get_option('ci_pdf_data', $k)) {
		$options = get_option($arr['opt'], array());

		if (isset($_GET['debug'])) {
			var_dump($arr);
			echo '<br>';
			var_dump($options);
			echo '<br><br>';
		}


		if (isset($options['auto_start']) && $options['auto_start']) {

			if (!is_array($arr['file'])) {
				$arr['file'] = array('port' => $arr['file']);
			}
			foreach ($arr['file'] as $fk => $runfile) {

				$port = $options[$fk];

				$path = TRCPDD_PATH . 'run';
				$path = str_replace('//', '/', $path);

				$logpath = WP_CONTENT_DIR . '/log/' . $arr['dir'];

				$outfile    = $logpath . '/out_' . $port . '.txt';
				$error_file = $logpath . '/error_' . $port . '.txt';
				$pid_file   = $logpath . '/pid_' . $port . '.txt';

				//check log files
				if (file_exists($outfile) && filesize($outfile) > 10000000) {
					tr_pdd_keepLastLines($outfile, 500);
				}

				if (file_exists($error_file) && filesize($error_file) > 10000000) {
					tr_pdd_keepLastLines($error_file, 500);
				}

				trpdd_get_logfiles($logpath, $port);


				if (empty($port) || trpdd_port_opened($port)) {
					continue;
				}



				if (file_exists($pid_file)) {
					@unlink($pid_file);
				}
				$phpcmd     = isset($options['phpcmd']) ? $options['phpcmd'] : 'zts-php';
				$command = "nohup {$phpcmd} {$path}/{$runfile} port {$port} {$logpath} > {$outfile} 2> {$error_file} < /dev/null &";
				$rs      = exec($command, $output);
				if (isset($_GET['debug'])) {
					var_dump($command);
					echo '<br>';
					var_dump($rs);
					echo '<br>';
					var_dump($output);
					echo '<br><br>';
				}
				fwrite($logf, 'datetime: ' . date_i18n('Y-m-d H:i:s') . "\n");
				fwrite($logf, 'ins: ' . $k . "\n");
				fwrite($logf, 'who: ' . exec('who') . "\n");
				fwrite($logf, 'command: ' . $command . "\n");
				fwrite($logf, 'out: ' . implode("\n", $output) . "\n\n\n");
			}
		}
	}
}



include(TRCPDD_PATH . 'inc/define_apis.php');
foreach ($define_apis as $k => $arr) {
	if (tr_get_option('ci_pdf_data', $k)) {
		$options = get_option($arr['st'], array());
		if (isset($options[$arr['active']]) && $options[$arr['active']] && isset($arr['cron'])) {
			$next_cron = is_array($arr['cron']) ? wp_next_scheduled($arr['cron'][0], array($arr['cron'][1])) : wp_next_scheduled($arr['cron']);
			if (isset($_GET['debug'])) {
				var_dump($arr);
				echo '<br>';
				var_dump($options);
				echo '<br>';
				var_dump($next_cron);
				echo '<br><br>';
			}
			if (!$next_cron) {
				if (empty($options['cron'])) {
					$options['cron'] = 2;
				}

				if (is_array($arr['cron'])) {

					wp_clear_scheduled_hook($arr['cron'][0], array($arr['cron'][1]));
					wp_schedule_event(time() + 30, $options['cron'] . 'min', $arr['cron'][0], array($arr['cron'][1]));
				} else {
					wp_clear_scheduled_hook($arr['cron']);
					wp_schedule_event(time() + 30, $options['cron'] . 'min', $arr['cron']);
				}
			}
		}
	}
}

//export csv for fpt
if (tr_get_option('ci_pdf_data', 'e_export_csvsftp')) {
	$next_cron = wp_next_scheduled('cron_api_covid_csv_sftp');
	if ($next_cron == 0) {
		wp_clear_scheduled_hook('cron_api_covid_csv_sftp');
		$offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
		$time_mid_night = strtotime(date_i18n('Y-m-d 00:02:02'))  - $offset;
		wp_schedule_event($time_mid_night, 'hourly', 'cron_api_covid_csv_sftp');
	}
}

fclose($logf);
