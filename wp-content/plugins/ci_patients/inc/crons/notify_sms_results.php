<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb, $blog_id, $tr_sms_gateway, $current_lab_id;

$log_cron = true;
$sending = get_option('tr_sms_results_sending', 0);

$limit_per_run = 50;
$max_time_run = 900;

$limit_date = date_i18n('Y-m-d H:i:s');
$limit_date = date('Y-m-d H:i:s', strtotime($limit_date) - 3600 * 14);


$log_file = WP_CONTENT_DIR . '/log/log_cron_sms_results.txt';
if (isset($_GET['trcppr_cron_notify_sms'])) {
	$sending = 0;
}
if (time() - $sending <  $max_time_run + 300) {
	if ($log_cron) {
		$f_log = fopen($log_file, 'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => break: " . (time() - $sending) . "\n");
		fclose($f_log);
	}
	return false;
}
update_option('tr_sms_results_sending', time());

if ($log_cron) {
	$f_log = fopen($log_file, 'a+');
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => start ' . $limit_date . "\n");
	fclose($f_log);
}



if ($current_lab_id > 0) {
	$lab_name = $wpdb->get_var("select lab_name from ci_labs where lab_ID = {$current_lab_id}");
} else {
	$lab_name = $wpdb->get_var("select lab_name from ci_labs order by lab_ID asc limit 1");
}

$login_url = get_bloginfo('url') . '/wp-login.php';
$options = get_option('trpatient_settings', array());

$options['enabel_notify_mi'] = intval($options['enabel_notify_mi']);
if (empty($options['sms_msg'])) {
	$options['sms_msg'] = 'Your Test Results are now available please visit: ' . get_bloginfo('url');
}

if (empty($options['sms_covid_result_msg'])) {
	$options['sms_covid_result_msg'] = $options['sms_msg'];
}

if (empty($options['mms_msg'])) {
	$options['mms_msg'] = 'Travel Testing Validation QR
Covid-19 RT-PCR Result for {first name} {current date}';
}

$options['sms_msg'] = str_replace('{lab name}', $lab_name, $options['sms_msg']);
$options['sms_msg'] = str_replace('{login url}', $login_url, $options['sms_msg']);

$options['sms_covid_result_msg'] = str_replace('{lab name}', $lab_name, $options['sms_covid_result_msg']);
$options['sms_covid_result_msg'] = str_replace('{login url}', $login_url, $options['sms_covid_result_msg']);


$can_send_sms = function_exists('otpa_get_active_gateway_class_name')
	&& otpa_get_active_gateway_class_name() == 'Otpa_Bandwidth_Sms_Gateway';


if (!$options['sms_notify'] || !$can_send_sms || !is_object($tr_sms_gateway)) {
	echo 'not install notify sms';
	return;
}


$loaded = $tr_sms_gateway->load_library();

if ($loaded) {
	$init = $tr_sms_gateway->init_api();
}



$where = " and re.approve_date >= '{$limit_date}' ";
$where .= " and re.estatus = 'C' ";
$where .= " and sn.ID is null ";
$where .= " and pa.pa_phone !='' ";


//so.ID is sent order
if ($options['enabel_notify_mi'] === 2) { //by clinic

	$where_cli_covid = '';
	$cids_covid = $wpdb->get_col("select cID from ci_clinic where cli_send_ocovid_sms = 1");
	if (count($cids_covid) > 0) {
		$cids_covid_str = implode(',', $cids_covid);

		$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='pcr' 
                                and tg_is_covid = 1");
		$where_cli_covid .= " and (
									(
										FIND_IN_SET('GROUP_{$group_covid_id}',ba.panel_tests_requested)
								        and ba.cli_ID in ({$cids_covid_str})
								    )
								    OR ba.cli_ID not in ({$cids_covid_str})
								)";
	}

	$where .= " and (
			(ba.cli_ID in (select cID from ci_clinic where cli_send_result_sms = 1) {$where_cli_covid})
			or so.ID is not null
			) ";
} else if ($options['enabel_notify_mi'] === 1) {
	$where .= " and (
						ba.ref_lab_ID = 0 
						or ba.ref_lab_ID is null
					)";
} else if ($options['enabel_notify_mi'] == 0) {
	$where .= " and (
					up.user_id > 0
					or so.ID is not null
				)
			";
}

//get rejected status
$rejected_status = tr_pdd_get_batch_statuses(false, true, true, 'pcr');
$statusesrj = array();
foreach ($rejected_status as $k => $vl) {
	if (!in_array($k, array('re_srrir', 're_srr'))) {
		$statusesrj[] = $k;
	}
}
$rejected_status_str = implode("','", $statusesrj);
$where .= " and (ba.batch_status not in ('{$rejected_status_str}') ) ";


$sql = array();
foreach (tr_pdd_get_batch_types() as $btype) {
	$where_covid = '';
	$group_covid_id = 0;
	if ($btype == 'pcr') {
		$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='pcr' 
                                and tg_is_covid = 1");
	} else if ($btype == 'blood') {
		$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='blood' 
                                and tg_is_covid = 1");
	}

	if ($options['only_covid']) {
		if ($btype == 'pcr') {
			$where_covid .= " and FIND_IN_SET('GROUP_{$group_covid_id}',ba.panel_tests_requested) ";
		} else if ($btype == 'blood') {
			$where_covid .= " and FIND_IN_SET('GROUP_{$group_covid_id}',ba.panel_tests_requested) ";
		} else {
			continue;
		}
	}

	$table_batch = tr_pdd_get_table_by_btype($btype);
	$table_report = tr_pdd_get_table_report_by_btype($btype);
	$sql[] = "(select 
                ba.mid,
                ba.patient_name,
                ba.assen,
                FIND_IN_SET('GROUP_{$group_covid_id}',ba.panel_tests_requested) as is_covid,
                re.id,
                pa.pa_ID,
                pa.pa_phone,
                pa.pa_email,
                pa.pa_first,
                pa.pa_last,
                pa.pa_passport,
                up.user_id,
                up.meta_value as phone,
                '{$btype}' as btype
                
                from {$table_batch} ba
                join {$table_report} re On re.patient_id = ba.assen
                join ci_patients pa ON pa.pa_ID = ba.patient_id
                left join {$wpdb->users} um ON um.patient_id = ba.patient_id
                left join {$wpdb->usermeta} up ON um.ID = up.user_id and up.meta_key='phone'
                left join ci_sent_notify sn ON sn.mid = ba.mid and sn.btype='{$btype}' and sn.send_type = 'rs'
                left join ci_sent_notify so ON so.mid = ba.mid and so.btype='{$btype}' and so.send_type = 'new'
                where 1 {$where} {$where_covid}
                limit {$limit_per_run}
                )";
}

if (empty($sql)) {
	return;
}

$sql = implode(' UNION ALL ', $sql);

$sql = "select * from ({$sql}) tt 
	    order by tt.mid asc
	    limit {$limit_per_run}";


$start_time = time();

$start_query_t = microtime(true);

$results = $wpdb->get_results($sql);

$query_time = microtime(true) - $start_query_t;


if (!empty($wpdb->last_error)) {
	wp_mail('pntrinh29@gmail.com', $lab_name . ' - sql error', $wpdb->last_error . "\n" . $wpdb->last_query);
}
if (isset($_GET['debug'])) {
	var_dump($sql);
	echo '<br><br><br>';
	var_dump($wpdb->last_error);
	echo '<br><br><br>';
	var_dump($results);
	echo '<br><br><br>';
}


if ($log_cron) {
	$f_log = fopen($log_file, 'a+');
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => count:' . count($results) . ' ' . "\n");
	if (!empty($wpdb->last_error)) {
		fwrite($f_log, $wpdb->last_error . ' ' . "\n");
		fwrite($f_log, $sql . ' ' . "\n");
	}

	fclose($f_log);
}

$mids = array();
foreach ($results as $r) {
	//check sent again
	$sent = $wpdb->get_var("select ID from ci_sent_notify where mid = '{$r->mid}' 
						and btype='{$r->btype}' and send_type='rs'");
	if ($sent || in_array($r->mid, $mids)) {
		continue;
	}
	$mids[] = $r->mid;

	$test_name = strtoupper($r->btype);
	$patient_name = $r->patient_name;
	$patient_id = $r->pa_ID;
	$last_name = $r->pa_last;
	$first_name = $r->pa_first;
	if (empty($last_name)) {
		list($last_name, $first_name) = explode(',', $patient_name);
	}

	$r->pa_passport = str_replace(array('-', '.', ' '), '', $r->pa_passport);
	$has_passport = strlen($r->pa_passport) >= 8;
	if (str_replace('0', '', $r->pa_passport) === '') {
		$has_passport = false;
	}


	if ($options['sms_fl_name'] && strpos($patient_name, ',') > 0) {
		$patient_name = trim($first_name) . ' ' . trim($last_name);
	}
	$assen = $r->assen;
	$phone_num = trim($r->phone);

	if (strlen($r->pa_phone) > 8) {
		$phone_num = $r->pa_phone;

		if ($r->user_id > 0) {
			update_user_meta($r->user_id, 'phone', $r->pa_phone);
		}
	}
	$phone_num = trim($phone_num);
	$phone_num = str_replace(array('-', ' ', '.', '(', ')'), '', $phone_num);
	if (!empty($phone_num) && strpos($phone_num, '+') === false) {
		$phone_num = '+1' . $phone_num;
	}


	if ($r->is_covid > 0) {
		$sms_message = stripslashes($options['sms_covid_result_msg']);
	} else {
		$sms_message = stripslashes($options['sms_msg']);
	}

	$sms_message = str_replace('{test name}', $test_name, $sms_message);
	$sms_message = str_replace(array('{patient name}', '{patient_name}'), $patient_name, $sms_message);
	$sms_message = str_replace(array('{accessions}', '(accession)', '{accession}'), $assen, $sms_message);

	//notify email
	if ($options['email_result_notify'] && is_email($r->pa_email)) {
		$sent = $wpdb->get_var("select ID from ci_sent_notify 
				where mid = '{$r->mid}' and btype='{$r->btype}' and send_type='rse'");
		if (!$sent) {
			$headers = '';
			$headers .= "MIME-Version: 1.0\r\n";
			$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

			$from_name = get_bloginfo('name');
			$from_email = $options['email_from'];
			if (!empty($from_email) && is_email($from_email)) {
				$headers .= 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
			}

			$email_to = $r->pa_email;
			$email_msg = $sms_message . "<br>";
			$email_msg .= " Accession: " . $assen . "<br>";
			$attachments = array();

			if ($options['mms_notify'] && $has_passport) {
				$url = tr_patient_qr_url_report($r->id, $btype);
				$email_msg .= "<br><img src='{$url}' width=200 height=200 alt='QR Image'/>";
			}

			$rs = wp_mail($email_to, 'Your test results are now available!', $email_msg, $headers, $attachments);
			if ($rs) {
				$wpdb->insert('ci_sent_notify', array(
					'mid' => $r->mid,
					'btype' => $r->btype,
					'send_type' => 'rse',
					'created' => date_i18n('Y-m-d H:i:s')
				));
			}
		}
	}


	if (empty($phone_num) || strlen($phone_num) < 8 || strpos($phone_num, '000000') !== false) {
		if ($log_cron) {
			//$f_log = fopen($log_file,'a+');
			//fwrite($f_log, date_i18n('Y-m-d H:i:s')." => empty phone: ".$phone_num."\n");
			//fclose($f_log);
		}
		continue;
	}


	try {
		$sent_sms = false;
		$sent_mms = false;
		if (strpos($phone_num, '+100000') === 0 || strlen($phone_num) < 8) {
			continue;
		} else {

			$msg_type = 'nt';
			if (isset($options['mms_notify']) && $options['mms_notify'] && $has_passport) {

				$url = tr_patient_qr_url_report($r->id, $btype);

				$mms_message = $sms_message;

				$sms_message = stripslashes($options['mms_msg']);
				$sms_message = str_replace('{test name}', $test_name, $sms_message);
				$sms_message = str_replace(array('{patient name}', '{patient_name}'), $patient_name, $sms_message);
				$sms_message = str_replace(array('{accessions}', '(accession)', '{accession}'), $assen, $sms_message);
				$sms_message = str_replace('{first name}', $first_name, $sms_message);
				$sms_message = str_replace('{current date}', date_i18n('m/d/Y'), $sms_message);

				$sent_mms = $tr_sms_gateway->send_sms($phone_num, $mms_message, $patient_name, $assen, 'nt', $patient_id, array($url));
				$msg_type = 'ms';
			}

			$sent_sms = $tr_sms_gateway->send_sms($phone_num, $sms_message, $patient_name, $assen, $msg_type, $patient_id);
		}

		if ($sent_sms || $sent_mms) {
			$wpdb->insert('ci_sent_notify', array(
				'mid' => $r->mid,
				'btype' => $r->btype,
				'send_type' => 'rs',
				'created' => date_i18n('Y-m-d H:i:s')
			));
		}


		if ($log_cron) {
			$f_log = fopen($log_file, 'a+');
			$logmore = '';
			if (!$sent_sms) {
				$logmore .= " => send sms error: ";
				$logmore .= $tr_sms_gateway->last_error;
			}
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => assen: " . $assen . $logmore . "\n");

			fclose($f_log);
		}
	} catch (Exception $e) {
		sleep(20);

		if ($log_cron) {
			$f_log = fopen($log_file, 'a+');
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => error: " . $e->getMessage() . "\n");
			fclose($f_log);
		}
	}

	if (defined('DEMO_SITE') && DEMO_SITE) {
		$results = array();
		break;
	}

	if (time() - $start_time > $max_time_run) break;
	//sleep(1);

}

$breaktime = 0;
if (count($results) == 0) {
	$breaktime = time() - $max_time_run - 300;
}
update_option('tr_sms_results_sending', $breaktime);

if ($log_cron) {
	$f_log = fopen($log_file, 'a+');
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => end. qt: {$query_time}\n\n\n");
	fclose($f_log);

	if (filesize($log_file) > 100000) {
		$olddata = substr(file_get_contents($log_file), 90000);
		file_put_contents($log_file, $olddata);
	}
}
