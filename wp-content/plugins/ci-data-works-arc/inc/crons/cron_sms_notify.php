<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

include(TRCPDD_PATH . 'api/bandwidth/ci_sms.php');

$log_file = WP_CONTENT_DIR . '/log/log_sms_clinic.txt';

if (!file_exists($log_file)) {
	touch($log_file, time() - 100);
}
if (time() - @filemtime($log_file) < 60 && !isset($_GET['testcron'])) {
	echo 'sms cli out time';
	return;
}

$config = get_option('ci_sms_notification_st', array());

if (isset($config['enable']) && $config['enable']) {

	if (!isset($config['btypes']) || !is_array($config['btypes']) || empty($config['btypes'])) {
		$config['btypes'] = array('pcr');
	}

	$currentdt = date_i18n('Y-m-d H:i:s');
	$currenttime = strtotime($currentdt);

	$f_log = fopen($log_file, 'a+');
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => start ' . "\n");


	$limit_approve_date = date('Y-m-d H:i:s', strtotime(date_i18n('Y-m-d H:i:s')) - 3600 * 12);

	$clinic_ids = $wpdb->get_col("select cID
	                            from ci_clinic 
	                            where cli_sms_number !='' 
	                            and cli_sms_number is not null
	                            and cli_status = 1");

	if (count($clinic_ids) > 0) {

		$clinic_ids_str = implode(',', $clinic_ids);

		foreach ($config['btypes'] as $btype) {
			$df_message = $config['clinic_msg'];
			if (empty($df_message)) {
				$df_message = 'The Results are available now';
			}
			$df_message = str_replace('[batch_type]', strtoupper($btype), $df_message);

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$sql = "select 
			ba.batch_id, 
			ba.cli_ID, 
			upload_files,
			GROUP_CONCAT(ba.assen) as assens
		        from {$batch_tbl} ba
			JOIN {$report_tbl} re ON re.patient_id = ba.assen
			WHERE re.estatus='C' 
				and ba.cli_ID in ({$clinic_ids_str})
				and re.approve_date >= '{$limit_approve_date}'
			GROUP by ba.cli_ID, batch_id, upload_files
			";
			$results = $wpdb->get_results($sql);

			if (isset($_GET['testcron'])) {
				var_dump($sql);
			}
			if (count($results) == 0) {
				fwrite($f_log, $btype . ' => Count=0' . "\n");
				echo 'count=0';
				continue;
			} else {
				fwrite($f_log, $btype . ' => Count=' . count($results) . "\n");
			}

			$sms = new CI_Sms($config);

			$sent_cli_ids = array();

			foreach ($results as $row) {
				$row->sent_key = md5($row->batch_id . $row->upload_files);

				if (in_array($row->cli_ID, $sent_cli_ids)) {
					echo 'in sent ids';
					var_dump($sent_cli_ids);
					echo '<br>';
					fwrite($f_log, 'Sent before' . "\n");
					continue;
				}

				$last_sent = $wpdb->get_var(
					"select created 
								from ci_sent_notify_clinic
									where btype='{$btype}' 
									and cli_ID = {$row->cli_ID}
									and sent_key = '{$row->sent_key}'
									"
				);

				if (!$last_sent) {
					$last_sent = $wpdb->get_var(
						"select created 
								from ci_sent_notify_clinic
									where btype='{$btype}' 
									and cli_ID = {$row->cli_ID}
									order by ID desc
									limit 1
									"
					);
					if ($currenttime - strtotime($last_sent) > 60 * 60) {//1hr
						$last_sent = false;
					}
				}

				if ($last_sent) {
					echo 'last sent: ' . $last_sent;
					echo '<br>';
					echo $row->sent_key;
					var_dump($row);
					echo '<br>';

					fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => last sent#:' . $row->cli_ID . " =>phone: {$phone} =>bid: {$row->batch_id} {$btype}=> {$row->upload_files} => {$row->sent_key}\n\n");


					continue;
				}

				$clinic = $wpdb->get_row("select cli_clinic, cli_sms_number
									from ci_clinic 
									where cID = {$row->cli_ID} ");

				$phone 		= $clinic->cli_sms_number;
				$message 	= $df_message;
				$message	= str_replace('[assens]', $row->assens, $message);
				$message	= str_replace('[assen]', $row->assens, $message);

				if ($phone == 'test' || $phone == '00000000') {
					$sent_sms = wp_mail('pntrinh29@gmail.com', 'Test sms: ' . $phone, $message);
				} else {
					$sent_sms = $sms->send_sms($phone, $message, '', '', 'cl');
				}


				if ($sent_sms) {
					$sent_cli_ids[] = $row->cli_ID;

					$wpdb->insert('ci_sent_notify_clinic', array(
						'cli_ID' => $row->cli_ID,
						'sent_key' => $row->sent_key,
						'btype' => $btype,
						'created' => date_i18n('Y-m-d H:i:s')
					));

					fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => sent to clinic#:' . $row->cli_ID . " ({$row->sent_key}) =>phone: {$phone} {$message}=>bid: {$row->batch_id} {$btype}=> {$row->upload_files}\n\n");
				} else {
					fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => send fail clinic#:' . $row->cli_ID . " ({$row->sent_key}) =>phone: {$phone} {$message}=>bid; {$row->batch_id} {$btype}=> {$row->upload_files}\n\n");
				}
			}
		}
	}

	//fwrite($f_log, date_i18n('Y-m-d H:i:s').' => end '."\n\n\n");

	fclose($f_log);
}
