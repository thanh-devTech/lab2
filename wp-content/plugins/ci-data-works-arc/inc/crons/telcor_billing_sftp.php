<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include(TRCPDD_PATH . 'api/ci_api_telcor_billing_sftp.php');

$options = get_option('tr_billing_sftp_st', array());


$log_file = WP_CONTENT_DIR . '/log/log_api_billing_sftp.txt';


$filetime = filemtime($log_file);

$timenow = time();
if ($timenow - $filetime < 300) {
	$next_cron = wp_next_scheduled('cron_api_billing_sftp');

	if (!$next_cron || $next_cron - 3600 > $timenow) {
		wp_clear_scheduled_hook('cron_api_billing_sftp', array($args1));
		wp_schedule_single_event(time() + 500, 'cron_api_billing_sftp', array($args1));
	} else {
		$f_log = fopen($log_file, 'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => timeout ' . "\n");
		fclose($f_log);
		touch($log_file, $filetime - 300);
	}

	return;
}



if ($options['enable']) {

	try {
		$filetime = time();

		$client = new CI_Api_Telcor_Billing_sftp($options);
		if (isset($_GET['testcron'])) {
			$client->debug = true;
		}

		$client->connect();
		$client->login();
		echo 'logged in<br>';
		$hour = date_i18n('H');

		$run_sync_pdf = false;
		if (isset($args1) && $args1 == 'pdfs') {
			$run_sync_pdf = true;
		}
		if ($run_sync_pdf) {
			$timeout = 200;

			$f_log = fopen($log_file, 'a+');
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => start pdf ' . "\n");
			fclose($f_log);

			wp_clear_scheduled_hook('cron_api_billing_sftp', array('pdfs'));
			wp_schedule_single_event(time() + $timeout + 200, 'cron_api_billing_sftp', array('pdfs'));

			if (!$client->sync_pdfs($timeout)) {
				wp_clear_scheduled_hook('cron_api_billing_sftp', array('pdfs'));
			} else {
				wp_clear_scheduled_hook('cron_api_billing_sftp', array('pdfs'));
				wp_schedule_single_event(time() + $timeout, 'cron_api_billing_sftp', array('pdfs'));
			}
		} else {
			$f_log = fopen($log_file, 'a+');
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => start ' . "\n");
			fclose($f_log);

			if ($client->sync()) {
				$options['last_date'] = $client->current_date;
				update_option('tr_billing_sftp_st', $options);
			}

			wp_clear_scheduled_hook('cron_api_billing_sftp', array('pdfs'));
			wp_schedule_single_event(time() + 3600, 'cron_api_billing_sftp', array('pdfs'));
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
		$f_log = fopen($log_file, 'a+');
		if ($f_log) {
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => ' . $e->getMessage() . "\n");
			fclose($f_log);
		}
	}

	if (file_exists($log_file)) {
		$f_log = fopen($log_file, 'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => end : ' . (time() - $timenow) . "s\n\n\n");
		fclose($f_log);

		if (filesize($log_file) > 100000) {
			file_put_contents($log_file, substr(file_get_contents($log_file), 90000));
		}
	}

	touch($log_file, $filetime - 300);
}
