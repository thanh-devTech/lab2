<?php
global $wpdb;
global $tr_debug;

if (!session_id()) session_start();

$offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

$search_tests = array(
	'Methadone / EDDP',
	'Ecstasy (MDMA/MDEA/MDA)',
	'Lunesta',
	'Hydrocodone,Oxycodone',
	'Heroin (6-MAM)',
	'Cannabinoids',
	'Cocaine',
	'Phencyclidine (PCP)',
	'Bath Salts',
	'Synthetic Cannabinoids'
);
$replace_tests = array(
	'Methadone,EDDP',
	'MDMA,MDEA,MDA',
	'Zopiclone',
	'Hydrocodone,Hydromorphone,Oxycodone',
	'6-MAM',
	'THC-COOH',
	'BENZOYLECGONINE-COCAINE METABOLITE',
	'PCP',
	'Methylone,Mephedrone,MDPV',
	'JWH-018N-PentanoicAcid,JWH-018N-PentanoicAcid,JWH-073-4OHbutyl,JWH-073-4OHbutyl,JWH-073-N-Butanoic acid/JWH-018-5OHpentyl,JWH-073-N-Butanoic acid/JWH-018-5OHpentyl,JWH-018-4OHpentyl'
);

function tr_ipo_write_log($filename, $log)
{
	$string = date("Y-m-d H:i:s") . ": \n";
	$string .= $log;
	$string .= "\n\n";
	$f = fopen($filename, 'a+');
	fwrite($f, $string);
	fclose($f);
}

function tr_ipo_update_delete_clinic($cli_id, $old_id)
{
	global $wpdb;
	$cli_name = $wpdb->get_var("select cli_clinic from ci_clinic WHERE cID = {$cli_id}");
	$wpdb->update('ci_batches', array('cli_ID' => $cli_id, 'requesting_practice' => $cli_name), array('cli_ID' => $old_id));

	$wpdb->update('ci_doc', array('cli_ID' => $cli_id), array('cli_ID' => $old_id));
	$wpdb->update('ci_clinic_doc', array('cli_ID' => $cli_id), array('cli_ID' => $old_id));
	$wpdb->update('ci_clinic_collector', array('cli_ID' => $cli_id), array('cli_ID' => $old_id));
	$wpdb->update('ci_medical_numbers', array('med_cli_ID' => $cli_id), array('med_cli_ID' => $old_id));
	$wpdb->update('ci_custom_providers', array('cli_ID' => $cli_id), array('cli_ID' => $old_id));
	$wpdb->update('ci_igniting_qss', array('cli_ID' => $cli_id), array('cli_ID' => $old_id));
	$wpdb->update('ci_wsdl_key_clinics', array('cli_ID' => $cli_id), array('cli_ID' => $old_id));
	$wpdb->update('wp_wp_eStore_customer_tbl', array('cli_ID' => $cli_id), array('cli_ID' => $old_id));

	$wpdb->delete('ci_clinic', array('cID' => $old_id));
}
if ($_GET['t'] > 0) {
	echo date('Y-m-d H:i:s', time());
	echo '<br>';
	echo date_i18n('Y-m-d H:i:s');
	echo '<br>';
	echo date('Y-m-d H:i:s', $_GET['t'] - 3600 * 5);
	exit;
}


if (isset($_GET['add_diagnostic_codes'])) {
	if (!empty($_POST['codes'])) {
		$btype = $_POST['btype'];
		$codes = explode("\n", $_POST['codes']);
		$order = 0;
		foreach ($codes as $code) {
			$code = trim(trim($code));
			if (!empty($code)) {
				list($key) = explode(' ', $code);

				$order++;
				$data = array(
					'key' => $key,
					'desc' => $code,
					'iorder' => $order,
					'type' => $btype
				);
				$id = $wpdb->get_var("select ID from ci_dagnostic_codes where `key`='{$key}' and `type`='{$btype}'");

				if ($id > 0) {
					$wpdb->update('ci_dagnostic_codes', $data, array('ID' => $id));
				} else {
					$wpdb->insert('ci_dagnostic_codes', $data);
				}
			}
		}
	}
?>
	<form method="post">
		<select name="btype">
			<?php
			foreach (tr_pdd_get_batch_types() as $btype) {
				echo '<option value="' . $btype . '">' . $btype . '</option>';
			}
			?>
		</select>
		<br>
		<textarea style="width:100% ;height:300px" name="codes"></textarea>
		<input type="submit" />
	</form>
<?php
	echo 'ok';
	exit;
}


if (isset($_REQUEST['fix_report_files'])) {
	$btype = 'pcr';
	$log_dir = WP_CONTENT_DIR . '/log/results_pcr/';

	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$sql = "select * from ci_reports_files where rf_btype='{$btype}'
            and rf_assen_list is null
            order by rf_ID desc
        ";
	$rs = $wpdb->get_results($sql);

	foreach ($rs as $r) {
		$filename = $r->rf_file;

		$assens = $wpdb->get_col("select patient_id from {$report_tbl} where 
                    FIND_IN_SET('{$filename}',upload_files)");
		if (!empty($assens)) {
			$assens = implode(',', $assens);
			$update = array(
				'rf_assen_list' => $assens
			);
			if (empty($r->rf_upload_date)) {
				$filepath = $log_dir . '/' . $r->rf_file;
				if (file_exists($filepath)) {
					$filetime = filemtime($filepath);
					$update['rf_upload_date'] = date('Y-m-d', $filetime);
				} else {
					var_dump($filepath);
					echo 'empty updloa date';
					continue;
				}
			}
			// var_dump($assens);
			$wpdb->update('ci_reports_files', $update, array('rf_ID' => $r->rf_ID));
		}
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_pcr_tests'])) {
	$rs = $wpdb->get_results("select * from ci_batches_pcr where panel_tests_requested=''");
	foreach ($rs as $r) {
		$mid = $r->mid;
		$assen = $r->assen;
		echo $mid . '=>' . $assen . ':<br>';

		$logs = $wpdb->get_results("select data from ci_log_forms where form ='save_pcr' and data like '%{$assen}%' order by id desc limit 3");

		foreach ($logs as $log) {

			if (!empty($log->data)) {
				$data = json_decode($log->data, true);


				if (count($data['bat']['panel_tests_requested']) > 0) {
					$panel_tests_requested = implode(',', $data['bat']['panel_tests_requested']);

					if (!empty($panel_tests_requested)) {

						$wpdb->update(
							'ci_batches_pcr',
							array('panel_tests_requested' => $panel_tests_requested),
							array('mid' => $mid)
						);
						break;
					}
				}
			}
		}

		echo '<br>';
	}

	exit;
}

if (isset($_GET['checklogrest'])) {

	$logdir = WP_CONTENT_DIR . '/log/resapi/';

	$diff = array();
	$json1 = json_decode(file_get_contents($logdir . '20210127-10:41-2021-01-26.json'), true);
	$json2 = json_decode(file_get_contents($logdir . '20210127-15:08-2021-01-26.json'), true);

	$newjson = array();
	foreach ($json1['results'] as $row) {
		$newjson[$row['order_number']] = $row;
	}
	foreach ($json2['results'] as $row) {
		if (!isset($newjson[$row['order_number']])) {
			$diff[] = $row['order_number'];
		}
	}
	var_dump($diff);
	exit;
}


if (isset($_REQUEST['fix_user_actions'])) {
	$rs_acts = array('update', 'edit', 'create', 'insert');

	foreach (tr_pdd_get_batch_types() as $btype) {
		foreach ($rs_acts as $act) {
			$old_act = $act . '_' . $btype;
			$new_act = $act;

			$wpdb->query("update ci_log_users set user_action = '{$new_act}', btype = '{$btype}' where user_action = '{$old_act}'");

			$old_act = $act . '_' . $btype . '_short';
			$new_act = $act . '_short';

			$wpdb->query("update ci_log_users set user_action = '{$new_act}', btype = '{$btype}' where user_action = '{$old_act}'");
		}
	}

	$rs_acts = array(
		'editreverse',
		'editapprove',
		'edit',
		'delete',
		'uploadedpdf',
		'uploaded',
		'approve',
		'reverse',
		'removehold',
		'hold',
		'au680',
		'alinity',
		'uploaded_screeing'
	);

	foreach (tr_pdd_get_batch_types() as $btype) {
		foreach ($rs_acts as $act) {
			$old_act = $act . '_rs' . $btype;
			$new_act = $act . '_rs';

			$wpdb->query("update ci_log_users set user_action = '{$new_act}', btype = '{$btype}' where user_action = '{$old_act}'");
		}
	}

	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_pcr_startpos'])) {
	$date = isset($_REQUEST['date']) ? $_REQUEST['date'] : date_i18n('Y-m-d');

	$rs = $wpdb->get_results("select * from ci_receiving where re_today='{$date}' ");

	foreach ($rs as $r) {
		$update = array();

		$min_pos = $wpdb->get_var("select min(ba.position) from ci_batches_pcr ba
                        join ci_receiving_batches rb ON rb.mid = ba.mid and rb.btype='pcr' 
                        where rb.re_ID = {$r->re_ID} and ba.position>6000");

		var_dump($r);
		echo 'min: ' . $min_pos;
		var_dump($wpdb->last_error);
		echo '<br><br>';

		if ($min_pos > 6000) {
			$update['re_prev_pos_pcr'] = $min_pos - 1;
		}

		if (count($update) > 0) {
			$wpdb->update('ci_receiving', $update, array('re_ID' => $r->re_ID));
		}
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_rev'])) {
	$rs = $wpdb->get_results("select * from ci_receiving where re_today='2020-07-21' ");
	foreach ($rs as $r) {
		$update = array();
		/*
		$update['re_qty_urine'] = $wpdb->get_var("select count(*) from ci_batches ba join ci_receiving_batches rb
							on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} and type_of_specimen='Urine' and initials1!='jvhl'
							and rb.btype='tox' ");

		$update['re_qty_oral'] = $wpdb->get_var("select count(*) from ci_batches ba join ci_receiving_batches rb
							on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} and type_of_specimen='Oral'
							and rb.btype='tox' ");

		$update['re_qty_jvhl'] = $wpdb->get_var("select count(*) from ci_batches ba join ci_receiving_batches rb
							on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} and (type_of_specimen='jvhl' or initials1 = 'jvhl')
							and rb.btype='tox' ");

		$update['re_qty_blood'] = $wpdb->get_var("select count(*) from ci_receiving_batches
									where re_ID = {$r->re_ID} and btype='blood'");

		$update['re_qty_dna'] = $wpdb->get_var("select count(*) from ci_receiving_batches
									where re_ID = {$r->re_ID} and btype='dna'");

		$update['re_qty_pcr'] = $wpdb->get_var("select count(*) from ci_receiving_batches
									where re_ID = {$r->re_ID} and btype='pcr'");
		*/

		$update['re_prev_pos_urine'] = $wpdb->get_var("select min(ba.position) from ci_batches ba join ci_receiving_batches rb
                            on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} and type_of_specimen='Urine' 
                            and rb.btype='tox' ") - 1;

		$update['re_prev_pos_oral'] = $wpdb->get_var("select min(ba.position) from ci_batches ba join ci_receiving_batches rb
                            on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} and type_of_specimen='Oral' 
                            and rb.btype='tox' ") - 1;

		$update['re_prev_pos_jvhl'] = $wpdb->get_var("select min(ba.position) from ci_batches ba join ci_receiving_batches rb
                            on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} and type_of_specimen='jvhl' 
                            and rb.btype='tox' ") - 1;

		$update['re_prev_pos_blood'] = $wpdb->get_var("select min(ba.position) from ci_batches_blood ba join ci_receiving_batches rb
                            on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} 
                            and rb.btype='blood' ") - 1;

		$update['re_prev_pos_dna'] = $wpdb->get_var("select min(ba.position) from ci_batches_dna ba join ci_receiving_batches rb
                            on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} 
                            and rb.btype='dna' ") - 1;

		$update['re_prev_pos_pcr'] = $wpdb->get_var("select min(ba.position) from ci_batches_pcr ba join ci_receiving_batches rb
                            on ba.mid = rb.mid and rb.re_ID = {$r->re_ID} 
                            and rb.btype='pcr' ") - 1;



		$wpdb->update('ci_receiving', $update, array('re_ID' => $r->re_ID));
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fix_completed'])) {
	foreach (tr_pdd_get_batch_types() as $btype) {
		$table_batch = tr_pdd_get_table_by_btype($btype);
		$table_report = tr_pdd_get_table_report_by_btype($btype);

		$rs = $wpdb->get_results("select batch_id,ba.mid,ba.batch_status from {$table_batch} ba
                        where ba.batch_status !='completed' and " . tr_pdd_where_batch_rejected(false, true) . "
                        and ba.assen in (select patient_id from {$table_report} where estatus = 'C' )
                        order by mid desc
                        limit 1000");

		foreach ($rs as $r) {
			$wpdb->update($table_batch, array('batch_status' => 'completed'), array('mid' => $r->mid));
			var_dump($r);
		}
	}
	echo ' <meta http-equiv="refresh" content="0;">';
}

if (isset($_GET['fix_odercli'])) {

	$rs = $wpdb->get_results("select co_ID,co_txn_id from ci_customer_orders");
	foreach ($rs as $r) {
		$cli_id = $wpdb->get_var("select cli_ID from wp_wp_eStore_customer_tbl where txn_id = '{$r->co_txn_id}'");

		if ($cli_id > 0) {
			$wpdb->update('ci_customer_orders', array('cli_ID' => $cli_id), array('co_ID' => $r->co_ID));
		}
	}
	echo 'ok';
	exit;
}
if (isset($_GET['fixdocorder'])) {
	$rs = $wpdb->get_results("select doc_ID,cli_ID from `wp_wp_eStore_customer_tbl` group by doc_ID, cli_ID");
	foreach ($rs as $r) {
		$datetime = $wpdb->get_var("select datetime from wp_wp_eStore_customer_tbl 
                        where doc_ID = {$r->doc_ID} and cli_ID = {$r->cli_ID} 
                        order by datetime desc limit 1");

		$wpdb->update('ci_clinic_doc', array('date_order' => $datetime), array('doc_ID' => $r->doc_ID, 'cli_ID' => $r->cli_ID));
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fixcd'])) {
	$rs = $wpdb->get_results("select * from `ci_log_forms` where form in ('ci_save_form_new_account_setup','ci_save_form_new_account_due')  and (data like '%first\":\"Pending%')order by id asc limit 1000");
	foreach ($rs as $r) {
		$data = json_decode($r->data, true);
		$cli_id = $data['cli']['cID'];
	}
	echo 'ok';
	exit;
}
if (isset($_GET['logcd'])) {
	$rs = $wpdb->get_results("select * from `ci_log_forms` where form in ('ci_save_form_new_account_setup','ci_save_form_new_account_due')  
        and (data like '%ID\":\"405\"%' or data like '') order by id asc");
	$clinicids = array();
	foreach ($rs as $r) {
		$data = json_decode($r->data, true);
		$cli_id = $data['cli']['cID'];
		if (in_array($cli_id, $clinicids)) {
			continue;
		}
		if (empty($cli_id)) {
			$clinic_name = $data['cli']['cli_clinic'];
			$cli_id = $clinic_name;
		} else {
			$clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = {$cli_id} ");
		}

		$clinicids[] = $cli_id;
		if (empty($clinic_name)) continue;

		$useremail = $wpdb->get_var("select user_email from {$wpdb->users} where ID = {$r->author_id} ");
		echo $clinic_name . ',' . $r->created_date . ',' . $useremail . "\n";
	}
	exit;
}

if (isset($_GET['fix_log_faxed'])) {
	$row_per_page = 1000;

	$btype = 'pcr';
	$type_result = 'rs_efax';
	if (!empty($btype) && $btype != 'tox') {
		$type_result .= $btype;
	}
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$rs = $wpdb->get_col("select mid from `ci_box_upload` 
                where btype='{$btype}' and type='{$type_result}' and status = 1
                order by id desc
                    limit {$offset},{$row_per_page}");
	$ids = implode(',', $rs);

	$wpdb->query("update {$report_tbl} set faxed=1 where id in ({$ids}) ");
	var_dump($wpdb->last_error);
	var_dump($wpdb->last_query);
	$offset += $row_per_page;
	if (count($rs) > 0) {

		echo ' <meta http-equiv="refresh"
   content="0; url=?tr_action=script_import&fix_log_faxed&offset=' . $offset . '">';
	}
	echo 'ok';
	exit;
}

if (isset($_GET['add_log_astm'])) {
	$dir  = TRCPDD_PATH . 'run/log/';
	$h = opendir($dir);
	while ($f = readdir($h)) {
		$filepath = $dir . $f;
		if (stripos($f, 'log_8686') !== false) {
			$files[$f] = $filepath;
		}
	}

	krsort($files);

	$y = 0;
	foreach ($files as $f => $filepath) {
		$y++;
		echo $f;
		echo "<br>";
		if ($y <= $offset) continue;
		$offset = $y;

		$data = file_get_contents($filepath);
		$data = str_replace("\n", '', $data);
		$data = str_replace("<ETX>", "\n", $data);

		preg_match_all('/([0-9]{4}\-[0-9]{2}\-[0-9]{2}\s[0-9]{2}:[0-9]{2}:[0-9]{2})Rev\:<STX>D.*(HLX[0-9]*)\s/', $data, $matches);

		echo 'count: ' . count($matches[2]) . '<br>';
		foreach ($matches[2] as $i => $assen) {

			$datetime = $matches[1][$i];
			echo $assen . ':' . $datetime;

			$mid = $wpdb->get_var("select mid from ci_batches where assen = '{$assen}' ");
			echo '=>' . $mid;

			if ($mid > 0) {
				$has_log = $wpdb->get_var("select id from ci_log_users where user_id = -3 
                                                and user_action = 'instrument_rstox' 
                                                and mid = {$mid} ");
				echo '=>';
				var_dump($has_log);

				if (!$has_log) {
					$data = array(
						'user_id' => -3,
						'user_action' => 'instrument_rstox',
						'created' => $datetime,
						'mid' => $mid,
						'ip' => '96.64.0.94'
					);
					$rs = $wpdb->insert('ci_log_users', $data);
					if ($rs === false) {
						echo 'error: ' . $wpdb->last_error;
					} else {
						echo '=> ok';
					}
				}
			} else {
				echo " => not found";
			}

			echo "<br><br>";
		}

		//echo ' <meta http-equiv="refresh"
		// content="8; url=?tr_action=script_import&add_log_astm&offset='.$offset.'">';
		exit;
	}

	echo 'ok';
	exit;
}

if (isset($_GET['add_log_result'])) {

	$file_log = WP_CONTENT_DIR . '/log/log_fix_rs.json';
	$list_file = json_decode(file_get_contents($file_log), true);
	if (!is_array($list_file)) {
		$list_file = array();
	}

	$log_limit_time = time() - 86400 * 250;

	$dir = WP_CONTENT_DIR . '/log/results/';
	$h = opendir($dir);
	$ip = '000';
	$i = 0;
	$limit_file = 15;
	$count_file = 0;
	while ($f = readdir($h)) {
		$filepath = $dir . $f;
		$created_time = filemtime($filepath);
		$created = date('Y-m-d H:i:s', $created_time + $current_offset);

		if (!is_file($filepath) || $created_time < $log_limit_time) {
			continue;
		}


		$i++;
		if (in_array($f, $list_file)) {
			continue;
		}

		echo $i . '.' . $f;
		echo '<br>';


		$assen_list = array();
		$assen_col = false;

		if (stripos($f, '.csv') > 0) {
			$hf = fopen($filepath, 'r');
			while ($row = fgetcsv($hf)) {
				if ($assen_col === false) {
					if (!in_array('Sample Name', $row)) {
						continue;
					}
					$assen_col = array_search('Sample Name', $row);
					continue;
				}
				$assen = trim($row[$assen_col]);

				if (in_array($assen, $assen_list)) {
					continue;
				}
				$assen_list[] = $assen;

				$bat = $wpdb->get_row("select ba.mid,re.author_id 
                                        from ci_batches ba
                                        join ci_reports re ON re.patient_id = ba.assen
                                        where ba.assen = '{$assen}'");


				if ($bat) {
					trpdd_log_user($bat->author_id, 'uploaded_rstox', $f, $bat->mid, 'tox', $created, $ip, $assen);
				}
			}
		} else {

			require_once TRCPDD_PATH . 'inc/PHPExcel/IOFactory.php';

			$ftype = 'excel';
			$filetype = PHPExcel_IOFactory::identify($filepath);

			$objReader = PHPExcel_IOFactory::createReader($filetype);
			$objPHPExcel = $objReader->load($filepath);

			$sheet = $objPHPExcel->getSheet(0);
			$highestRow = $sheet->getHighestRow();
			$highestColumn = $sheet->getHighestColumn();


			for ($ir = 1; $ir <= $highestRow; $ir++) {
				$rowdt = $sheet->rangeToArray('A' . $ir . ':' . $highestColumn . $ir, NULL, TRUE, FALSE);
				$row = $rowdt[0];

				if ($assen_col === false) {
					if (!in_array('Sample Name', $row)) {
						continue;
					}
					$assen_col = array_search('Sample Name', $row);
					continue;
				}
				$assen = trim($row[$assen_col]);


				if (in_array($assen, $assen_list)) {
					continue;
				}
				$assen_list[] = $assen;

				$bat = $wpdb->get_row("select ba.mid,re.author_id 
                                        from ci_batches ba
                                        join ci_reports re ON re.patient_id = ba.assen
                                        where ba.assen = '{$assen}'");


				if ($bat) {
					trpdd_log_user($bat->author_id, 'uploaded_rstox', $f, $bat->mid, 'tox', $created, $ip, $assen);
				}
			}
		}

		$list_file[] = $f;

		file_put_contents($file_log, json_encode($list_file));
		$count_file++;

		if ($count_file > $limit_file) {
			echo ' <meta http-equiv="refresh" content="0">';
			exit;
		}
	}

	echo 'ok';
	exit;
}


if (isset($_GET['fix_custody'])) {
	$rs = $wpdb->get_results("select * from ci_log_users 
                where mid is null 
                and user_action like 'update\_pcr%' limit 10000");
	foreach ($rs as $r) {
		$mid = explode(':', $r->data);
		$mid = explode('|', $mid[1]);
		$mid = trim($mid[0]);

		if ($mid > 0) {
			$wpdb->update('ci_log_users', array('mid' => $mid), array('id' => $r->id));
		}
		echo $mid, '<br>';
	}
	echo 'ok';
	exit;
}
if (isset($_GET['fix_mrn'])) {

	$rs = $wpdb->get_results("select * from ci_medical_numbers");
	foreach ($rs as $r) {
		$update = array(
			'pa_mrn' => $r->med_number
		);
		$wpdb->update('ci_patients', $update, array('pa_ID' => $r->med_pa_ID));
	}
	echo count($rs);

	echo 'ok';
	exit;
}

if (isset($_GET['fix_pa_clinic_id'])) {

	$rs = $wpdb->get_results("select * from ci_patients where pa_clinic_ID is null");
	foreach ($rs as $r) {
		$cli_ID = $wpdb->get_var("select cli_ID from ci_batches where patient_id = {$r->pa_ID} order by doc desc");
		if ($cli_ID == 0) {
			$cli_ID = $wpdb->get_var("select cli_ID from ci_batches_pcr where patient_id = {$r->pa_ID} order by doc desc");
		}
		if ($cli_ID > 0) {
			$wpdb->update('ci_patients', array('pa_clinic_ID' => $cli_ID), array('pa_ID' => $r->pa_ID));
		}
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fix_txn_id'])) {

	$rs = $wpdb->get_results("select * from ci_customer_orders");
	foreach ($rs as $r) {
		$txn_id = $wpdb->get_var("select txn_id from wp_wp_eStore_customer_tbl where id = {$r->order_ID}");
		if (empty($txn_id)) {
			var_dump($wpdb->last_error);
			echo 'empty $txn_id: ' . $r->order_ID . '<br>';
		}
		$ru = $wpdb->update('ci_customer_orders', array('co_txn_id' => $txn_id), array('co_ID' => $r->co_ID));
		if ($ru === false) {
			echo 'error' . $wpdb->last_error;
		}
	}
	echo 'ok';
	exit;
}
if (isset($_GET['fix_mc_pcr'])) {

	$log_dir = ABSPATH . '/wp-content/log/results_pcr/';
	$all_instruments = $wpdb->get_col("select instrument from `ci_instruments` where btype='pcr' ");
	$all_instruments = array_map("strtoupper", $all_instruments);

	$_SESSION['currentid'] = 0;
	$current_id = $_SESSION['currentid'] > 0 ? $_SESSION['currentid'] : 0;

	$rs = $wpdb->get_results("select * from ci_reports_files 
                                    where rf_btype='pcr' and rf_ID > {$current_id}
                                   
                                    order by rf_ID asc 
                                    limit 200");
	$assen_list = array();
	echo count($rs);
	echo '<br>';
	foreach ($rs as $i => $r) {
		$filepath = $log_dir . $r->rf_file;
		$instrument = '';

		foreach ($all_instruments as $mc) {
			if (stripos($r->rf_file, $mc) > 0) {
				$instrument = substr(strtoupper($mc), 0, 15);
				break;
			}
		}

		$_SESSION['currentid'] = $r->rf_ID;
		echo $i, '=>', $r->rf_ID, '=>', $r->rf_file, '=>', $instrument;
		echo '<br>';

		if (empty($instrument)) {
			echo 'empty instrument<br>';
			continue;
		}


		$f = fopen($filepath, 'r');
		$start = false;
		while ($row = fgetcsv($f)) {
			if ($row[0] == 'Sample Name') {
				$start = true;
				continue;
			}

			if ($start) {
				$assen = $row[0];
				$drug = $row[1];

				$report_id = $assen_list[$assen];
				if (!$report_id) {
					$report_id = $wpdb->get_var("select id from ci_reports_pcr where patient_id = '{$assen}' ");
					$assen_list[$assen] = $report_id;
					if (!$report_id) {
						echo '<br>assen not found ' . $assen, '<br>';
						continue;
					}
				}

				$ru = $wpdb->update('ci_results_pcr', array('instrument' => $instrument), array('report_id' => $report_id, 'drug' => $drug));
				if ($ru === false) {
					echo $wpdb->last_error;
				}
			}
		}
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fix_urine_api'])) {

	$urine_test = $wpdb->get_results("select * from ci_urine_validity");
	$all_urine = array();
	foreach ($urine_test as $t) {
		if (!empty($t->u_abreviation)) {
			$t->u_abreviation = strtoupper($t->u_abreviation);
			$all_urine[$t->u_abreviation] = $t;
		}
		if (!empty($t->u_abreviation_mc6410)) {
			$t->u_abreviation_mc6410 = strtoupper($t->u_abreviation_mc6410);
			$all_urine[$t->u_abreviation_mc6410] = $t;
		}
		if (!empty($t->u_loinc_code)) {
			$t->u_loinc_code = strtoupper($t->u_loinc_code);
			$all_urine[$t->u_loinc_code] = $t;
		}
	}

	$rs = $wpdb->get_results("select * from `ci_wsdl_log`  where wl_type='put_result_tox' order by wl_at desc limit 1000");

	foreach ($rs as $r) {
		$xml = $r->wl_data;
		$data = simplexml_load_string($xml);
		$assen = (string) $data->assen;
		$batch = $wpdb->get_row("select * from ci_batches where assen = '{$assen}'", ARRAY_A);
		if ($batch) {
			//chemistry
			if (isset($data->chemistry) && count($data->chemistry->test) > 0) {

				foreach ($data->chemistry->test as $t) {
					$name = (string)$t->name;
					$conc = (string)$t->value;
					$conc = str_replace(' ', '', $conc);
					$conc = preg_replace("/[^0-9-.,]/", '', $conc);
					// $comments = (string)$t->comments;


					$name = strtoupper($name);

					$tid = $all_urine[$name];
					if ($tid) {
						//api_type = 1 : wsdl type

						$wpdb->query("INSERT INTO ci_result_test_speciments (mid,testid,conc,checked,api_type) 
									values ('{$batch['mid']}','{$tid->u_ID}','{$conc}',1,1) 
									ON DUPLICATE KEY UPDATE conc = '{$conc}' ");
					} else {
						$return['status'] = 'CHEMISTRY_NOTFOUND';
						$return['msg'] = 'Abreviation ' . $name . ' not exists';
					}
				}
			}
			//echo $batch['mid'];exit;
		}
	}
}

if (isset($_GET['import_urine_test'])) {


	$row_per_page = 1000;
	$offset = isset($_GET['offset']) ? $_GET['offset'] : 0;

	$urines = $wpdb->get_results("select * from ci_urine_validity where u_status = 1");
	$urine_tests_all = array();
	foreach ($urines as $u) {
		$key = strtoupper($u->u_name);
		$urine_tests_all[$key] = $u->u_ID;
	}

	$rs = $wpdb->get_results("select * from ci_result_specimens order by batch_mid desc
                limit {$offset},{$row_per_page}");

	foreach ($rs as $result) {
		$mid = $result->batch_mid;

		$result_specmens = json_decode($result->val, true);
		$result_screening = json_decode($result->screening, true);

		foreach ($result_specmens as $k => $vl) {
			$conc = $vl['value'];
			$comments = $vl['comments'];

			if (empty($conc) && $conc !== '0') continue;
			if (!is_integer($k)) {
				$k = strtoupper($k);
				$k = $urine_tests_all[$k];
			}

			$wpdb->query("INSERT INTO ci_result_test_speciments (mid,testid,conc,comments) 
									values ('{$mid}','{$k}','{$conc}','{$comments}') 
									ON DUPLICATE KEY UPDATE conc = '{$conc}', comments = '{$comments}' ");
		}

		foreach ($result_screening as $k => $vl) {
			$conc = $vl['value'];
			$comments = $vl['comments'];
			$checked = $vl['checked'];
			if (empty($conc) && $conc !== '0' && !$checked) continue;
			if (!is_integer($k)) {
				$k = strtoupper($k);
				$k = $urine_tests_all[$k];
			}

			if (empty($conc) && $conc !== '0') {
				$wpdb->query("INSERT INTO ci_result_test_speciments (mid,testid,comments,checked) 
									values ('{$mid}','{$k}','{$comments}','{$checked}') 
									ON DUPLICATE KEY UPDATE  checked = '{$checked}',
									 comments = '{$comments}' ");
			} else {
				$wpdb->query("INSERT INTO ci_result_test_speciments (mid,testid,conc,comments,checked) 
									values ('{$mid}','{$k}','{$conc}','{$comments}','{$checked}') 
									ON DUPLICATE KEY UPDATE conc = '{$conc}', checked = '{$checked}',
									 comments = '{$comments}' ");
			}
		}

		echo $mid . '<br>';
	}
	$offset += $row_per_page;
	echo ' <meta http-equiv="refresh"
   content="0; url=?tr_action=script_import&import_urine_test&offset=' . $offset . '">';
	echo 'ok';
	exit;
}




if (isset($_GET['test1'])) {
	$urine_test = $wpdb->get_results("select * from ci_urine_validity");
	$all_urine = array();
	foreach ($urine_test as $t) {
		if (!empty($t->u_abreviation)) {
			$t->u_abreviation = strtoupper($t->u_abreviation);
			$all_urine[$t->u_abreviation] = $t;
		}
		if (!empty($t->u_abreviation_mc6410)) {
			$t->u_abreviation_mc6410 = strtoupper($t->u_abreviation_mc6410);
			$all_urine[$t->u_abreviation_mc6410] = $t;
		}
		if (!empty($t->u_loinc_code)) {
			$t->u_loinc_code = strtoupper($t->u_loinc_code);
			$all_urine[$t->u_loinc_code] = $t;
		}
	}
	var_dump($all_urine);
	exit;
}

if (isset($_GET['checkdiagnostic'])) {

	$type = 'tox';
	include_once(TRCPDD_PATH . 'inc/form_define.php');
	$list = tr_pdd_get_dagnostic_codes($type);

	$count_array = array();
	$table = tr_pdd_get_table_by_btype($type);
	foreach ($list as $k => $vl) {
		if (empty($k)) continue;
		$count = $wpdb->get_var("select count(*) from {$table} where dagnostic_codes1 like '%{$k}%' ");

		$count_array[$k] = intval($count);
	}

	arsort($count_array);
	$order = 0;
	foreach ($count_array as $k => $count) {
		$order++;
		$data = array(
			'key' => $k,
			'desc' => $list[$k],
			'iorder' => $count,
			'type' => $type
		);
		$id = $wpdb->get_var("select ID from ci_dagnostic_codes where `key`='{$k}' and `type`='{$type}'");
		var_dump($id);
		if ($id > 0) {
			$wpdb->update('ci_dagnostic_codes', $data, array('ID' => $id));
		} else {
			$wpdb->insert('ci_dagnostic_codes', $data);
		}
	}

	echo 'ok';
	exit;
}


if (isset($_GET['fix_fill_form'])) {
	$rs = $wpdb->get_results("select * from ci_batches where short_form_by is null and batch_short=1 order by mid desc limit 1000");
	foreach ($rs as $r) {
		$sql = "select * from ci_log_forms where data like '%page=ci_batches_view%' and data like '%&act=edit&id={$r->mid}%' order by id desc limit 1";
		$d = $wpdb->get_row($sql);
		if ($d) {
			$user_id = $d->author_id;
			$date = $d->created_date;
			$wpdb->update('ci_batches', array('short_form_by' => $user_id, 'short_form_time' => $date), array('mid' => $r->mid));
			var_dump($user_id);
			var_dump($date);
		}
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fix_fill_form2'])) {
	$rs = $wpdb->get_results("select * from ci_batches where req_form_by is null and batch_short=1 order by mid desc limit 1000");
	foreach ($rs as $r) {
		$sql = "select * from ci_log_forms where data like '%page=form_requisition%' and data like '%&act=edit&id={$r->mid}%' order by id desc limit 1";
		$d = $wpdb->get_row($sql);
		if ($d) {
			$user_id = $d->author_id;
			$date = $d->created_date;
			$wpdb->update('ci_batches', array('req_form_by' => $user_id, 'req_form_time' => $date), array('mid' => $r->mid));
			var_dump($user_id);
			var_dump($date);
		}
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fix_pa_dob'])) {
	$rs = $wpdb->get_results("select * from `ci_patients` where pa_dob like '%-00' or pa_dob='2019-04-02' order by pa_ID desc limit 10");
	foreach ($rs as $r) {
		$where = " form = 'ci_save_form_requisition' ";
		$where .= $wpdb->prepare(" and data like %s ", "%\"pa_first\":\"{$r->pa_first}\"%");
		$where .= $wpdb->prepare(" and data like %s ", "%\"pa_last\":\"{$r->pa_last}\"%");
		$sql = "select * from ci_log_forms where {$where} order by id desc";
		var_dump($sql);
		$found = $wpdb->get_results($sql);
		foreach ($found as $f) {
			$f->data = strip_tags($f->data);
			$data = json_decode($f->data, true);

			$dob = $data['pa']['pa_dob'];
			$first = $data['pa']['pa_first'];
			$last = $data['pa']['pa_last'];

			if ($first != $r->pa_first || $last != $r->pa_last) {
				continue;
			}
			var_dump($dob);
			if ($dob == $r->pa_dob || $dob == '2019-04-02') {
				continue;
			}

			if (!empty($dob)) {
				$wpdb->update('ci_patients', array('pa_dob' => $dob), array('pa_ID' => $r->pa_ID));
				break;
			}
		}
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_bill_tests'])) {
	$rs = $wpdb->get_results("select * from ci_bill_drug_classes", ARRAY_A);
	foreach ($rs as $row) {
		$row_id = $row['dc_ID'];
		$tests = explode(',', $row['dc_tests']);
		$wpdb->delete('ci_bill_drug_classes_test', array('ct_dc_ID' => $row_id));
		foreach ($tests as $test) {
			$test = trim($test);
			if (empty($test)) continue;
			$wpdb->insert('ci_bill_drug_classes_test', array(
				'ct_test' => $test,
				'ct_dc_ID' => $row_id
			));
		}
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['status'])) {


	$keys = array(
		'max_file_uploads',
		'memory_limit',
		'upload_max_filesize',
		'max_execution_time',
		'max_input_vars'
	);
	foreach ($keys as $k) {
		echo '<br>';
		echo $k . ': ' . filter_var(ini_get($k), FILTER_SANITIZE_STRING);
	}

	exit;
}

if (isset($_GET['fix_doctor_comma'])) {
	$rs = $wpdb->get_results("select ba.mid,ba.requesting_physician, doc.doc_first, doc.doc_last
                    from ci_batches ba
                     join ci_doc doc ON doc.doc_ID = ba.doc_ID 
                     where ba.requesting_physician not like '%,%'
                      order by ba.mid desc limit 1000");
	$count = 0;
	foreach ($rs as $r) {
		$doctor = trim($r->doc_last . ', ' . $r->doc_first, ',');
		$doctor = trim($doctor);
		$doctor = trim($doctor, ',');
		$wpdb->update('ci_batches', array('requesting_physician' => $doctor), array('mid' => $r->mid));
		echo $r->mid . '. ' . $r->requesting_physician . ' => ' . $doctor . '<br>';
		$count++;
	}
	echo 'count: ' . $count;
	echo 'ok';
	exit;
}

if (isset($_GET['fix_user_role_dup'])) {
	$wpdb->query("SET SESSION group_concat_max_len = 1000000;");
	$rs = $wpdb->get_results("select *,count(*) as c,group_concat(umeta_id) as ids from {$wpdb->usermeta} group by user_id,meta_key order by c desc");
	foreach ($rs as $r) {
		if ($r->c < 2) continue;
		$ids = explode(',', $r->ids);
		var_dump($ids);
		exit;
	}
	exit;
}

if (isset($_GET['fix_b_assen01'])) {
	$results = $wpdb->get_results("select * from ci_batches where assen like '%-01' and batch_created>='2016-08-02' and batch_created<='2016-08-03 23:59:59'");
	foreach ($results as $row) {
		list($assen, $n) = explode('-', $row->assen);
		$wpdb->delete('ci_batches', array('assen' => $assen));
		$wpdb->update('ci_batches', array('assen' => $assen), array('mid' => $row->mid));
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fix_b_batch_id'])) {
	$sql = "select ba.*,re.id as report_id from ci_batches ba 
                join ci_reports re ON ba.assen = re.patient_id
                where ba.batch_id = 0  ";
	$results = $wpdb->get_results($sql);

	foreach ($results as $row) {
		$batch_id = date('Ymd', strtotime($row->batch_created));
		$update = array(
			'batch_id' => $batch_id
		);
		$wpdb->update('ci_batches', $update, array('mid' => $row->mid));

		$wpdb->update('ci_reports', $update, array('id' => $row->report_id));
		$count++;
	}
	echo 'Count: ' . $count;
	exit;
}

if (isset($_GET['fix_product_image'])) {
	$results = $wpdb->get_results("select * from wp_wp_eStore_tbl where thumbnail_url like '%b3diagnosticlaboratorytx%'");
	foreach ($results as $row) {
		$url = $row->thumbnail_url;
		$data = file_get_contents($url);
		list($u, $filedir) = explode('wp-content/', $url);
		$filen = $filedir;
		$dir = WP_CONTENT_DIR . '/' . dirname($filedir);
		wp_mkdir_p($dir);
		$filedir = WP_CONTENT_DIR . '/' . ($filedir);
		file_put_contents($filedir, $data);
		$url = WP_CONTENT_URL . '/' . $filen;
		$wpdb->update('wp_wp_eStore_tbl', array('thumbnail_url' => $url), array('id' => $row->id));

		$wpdb->insert('wp_wp_eStore_cat_prod_rel_tbl', array('cat_id' => '1', 'prod_id' => $row->id));
	}
	echo 33;
	exit;
}

if (isset($_REQUEST['fix_tecan_batch_id'])) {
	$rs = $wpdb->get_results("select ba.batch_id,te.te_batch_id,te.te_ID from ci_tecan te 
                  JOIN ci_batches ba ON ba.assen =te.te_assen and ba.batch_id!= te.te_batch_id
                  limit 1000");
	foreach ($rs as $r) {
		$wpdb->update('ci_tecan', array('te_batch_id' => $r->batch_id), array('te_ID' => $r->te_ID));
		echo $r->te_batch_id . '=>' . $r->batch_id . '<br>';
		$count++;
	}
	echo 'Count: ' . $count;
	exit;
}

if (isset($_REQUEST['fix_order_id'])) {

	$orders = $wpdb->get_results("select id,doc_ID,cli_ID,import_ID from wp_wp_eStore_customer_tbl 
                            where import_ID is null order by id desc limit 1000", ARRAY_A);
	foreach ($orders as $row) {
		$new = array();
		$cli_id = $wpdb->get_var("select cID from ci_clinic where cli_import_ID = {$row['cli_ID']}");
		if ($cli_id > 0) {
			$new['cli_ID'] = $cli_id;
		}

		$doc_id = $wpdb->get_var("select doc_ID from ci_doc where doc_import_ID = {$row['doc_ID']}");
		if ($doc_id > 0) {
			$new['doc_ID'] = $doc_id;
		}

		if (count($new) > 0) {
			$new['import_ID'] = 0;
			$wpdb->update('wp_wp_eStore_customer_tbl', $new, array('id' => $row['id']));
			$count++;
		}
	}
	echo 'Count: ' . $count;
	exit;
}

if (isset($_REQUEST['fix_instrument'])) {
	$rs = $wpdb->get_results("select * from ci_reports where instrument is null or instrument='' ");
	$instruments = array();
	foreach ($rs as $r) {
		$arr = array();
		$filename = $r->upload_files;
		if (empty($instruments[$filename])) {
			$fname = basename($filename);
			preg_match("/(MS[^\_\s]*)/", $fname, $mats);

			$instruments[$file] = $arr['instrument'] = $mats[1];
		} else {
			$arr['instrument'] = $instruments[$file];
		}

		$wpdb->update('ci_reports', $arr, array('id' => $r->id));
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_report_date'])) {
	$rs = $wpdb->get_results("select * from ci_reports where created_date is null and created >0  ");

	foreach ($rs as $r) {
		$arr = array(
			'created_date' => date('Y-m-d', $r->created),
		);
		$wpdb->update('ci_reports', $arr, array('id' => $r->id));
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_profile_duplicate'])) {
	$wpdb->query("SET SESSION group_concat_max_len = 1000000;");
	$results = $wpdb->get_results("select * , group_concat(cus_ID) as ids,count(*) as c from `ci_custom_providers` group by cus_import_ID order by c desc");
	foreach ($results as $row) {
		$ids = explode(',', $row->ids);

		//find
		foreach ($ids as $i => $id) {
			if ($i == 0) continue;

			echo 'D: ' . $id, ' => ' . $ids[0] . '<br>';

			$wpdb->delete("ci_custom_providers", array('cus_ID' => $id));
		}
		$count++;
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_collector_duplicate'])) {
	$wpdb->query("SET SESSION group_concat_max_len = 1000000;");
	$results = $wpdb->get_results("select * , group_concat(col_ID) as ids,count(*) as c from `ci_collector` group by col_import_ID order by c desc");
	foreach ($results as $row) {
		$ids = explode(',', $row->ids);

		//find
		foreach ($ids as $i => $id) {
			if ($i == 0) continue;

			echo 'D: ' . $id, ' => ' . $ids[0] . '<br>';

			$wpdb->delete("ci_collector", array('col_ID' => $id));
			$wpdb->update($wpdb->usermeta, array('meta_value' => $ids[0]), array('meta_key' => '_collector', 'meta_value' => $id));
			$wpdb->update('ci_batches', array('col_ID' => $ids[0]), array('col_ID' => $id));
			$wpdb->update('ci_clinic_collector', array('col_ID' => $ids[0]), array('col_ID' => $id));
			$wpdb->update('ci_rep_collector', array('col_ID' => $ids[0]), array('col_ID' => $id));
		}
		$count++;
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_collector_duplicate2'])) {
	$wpdb->query("SET SESSION group_concat_max_len = 1000000;");
	$results = $wpdb->get_results("select *,group_concat(col_ID) as ids, count(*) as c from `ci_collector` group by col_first,col_last,col_phone order by c desc");
	foreach ($results as $row) {
		$ids = explode(',', $row->ids);

		//find
		foreach ($ids as $i => $id) {
			if ($i == 0) continue;

			echo 'D: ' . $id, ' => ' . $ids[0] . '<br>';

			$wpdb->delete("ci_collector", array('col_ID' => $id));
			$wpdb->update($wpdb->usermeta, array('meta_value' => $ids[0]), array('meta_key' => '_collector', 'meta_value' => $id));
			$wpdb->update('ci_batches', array('col_ID' => $ids[0]), array('col_ID' => $id));
			$wpdb->update('ci_clinic_collector', array('col_ID' => $ids[0]), array('col_ID' => $id));
			$wpdb->update('ci_rep_collector', array('col_ID' => $ids[0]), array('col_ID' => $id));
		}
		$count++;
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['fix_user_groups_import'])) {

	$rs = $wpdb->get_results("select * from `ci_marketing_groups`");
	foreach ($rs as $r) {
		$gr_ID = $r->gr_ID;
		$admins = $wpdb->get_col("select gm_user_ID from `ci_user_group_members` WHERE gm_gr_ID = {$gr_ID} AND gm_type = 'A'");
		//  var_dump($admins);exit;
		$admins = implode(',', $admins);

		$members = array();
		$users = $wpdb->get_col("select gm_user_ID from `ci_user_group_members` WHERE gm_gr_ID = {$gr_ID} AND gm_type = 'M'");
		foreach ($users as $uid) {
			$rep_id = get_user_meta($uid, '_rep_id', true);
			$members[] = $rep_id;
			$wpdb->update('ci_lab_reps', array('rep_marketing_group_ID' => $gr_ID), array('rep_ID' => $rep_id));
		}
		$members = implode(',', $members);
		$wpdb->update('ci_marketing_groups', array('gr_admins' => $admins, 'gr_members' => $members), array('gr_ID' => $gr_ID));
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fix_cutoff_panel'])) {
	$cutoffs = $wpdb->get_results(" select * from `ci_cutoffs` where matrix = 'urine' ");
	foreach ($cutoffs as $c) {
		//group
		$gr_ID = $wpdb->get_var("select tg_ID from ci_test_groups WHERE tg_name = '{$c->group}'");
		if ($gr_ID == 0) {
			$wpdb->insert('ci_test_groups', array('tg_name' => $c->group));
			$gr_ID = $wpdb->insert_id;
		}

		$data = array(
			'te_name' => $c->cmpd,
			'te_cutoff' => $c->cutoff,
			'te_group_id' => $gr_ID
		);
		$te_ID = $wpdb->get_var("select tg_ID from ci_test_panels WHERE te_name = '{$c->cmpd}'");
		if ($te_ID == 0) {
			$wpdb->insert('ci_test_panels', $data);
			$t_id = $wpdb->insert_id;
			$wpdb->insert('ci_test_panels_cutoffs', array('cu_cutoff' => $c->cutoff, 'cu_ms' => 'MS1,MS2', 'cu_te_ID' => $t_id));
		} else {
			$wpdb->update('ci_test_panels', $data, array('te_ID' => $te_ID));
		}
	}
	echo 'ok';
	exit;
}

if (isset($_GET['fix_panel_newb3'])) {
	$arr = array();
	$arr['basic'] = 'Nordiazepam,Fentanyl,Norfentanyl,Hydroxyalprazolam,Alprazolam,Clonazepam,Diazepam,Flurazepam,Lorazepam,Temazepam,Oxazepam,Buprenorphine,Norbuprenorphine,Meperidine,Methadone,EDDP,Hydrocodone,Hydromorphone,Oxycodone,Oxymorphone,Codeine,Morphine,Carisoprodol,Meprobamate,BENZOYLECGONINE-COCAINE METABOLITE,MDA,MDEA,MDMA,6-MAM,Methamphetamine d/l Isomers,Methamphetamine,Amphetamine';
	$arr['standard'] = $arr['base'] . ',Tramadol,Pregabalin,Tapentadol,Amobarbital,Butalbital,Phenobarbital,Secobarbital,Cannabinoids,THC-COOH,THC,Fentanyl,Norfentanyl,Amobarbital Pentobarbital,Butabarbital';
	$arr['comprehensive'] = $arr['basic'] . ',Hydroxyalprazolam,Alprazolam,Tramadol,Amobarbital,Butalbital,Phenobarbital,Secobarbital,Cannabinoids,THC-COOH,THC,Flunitrazepam,Gabapentin Tapentadol,Methamphetamine d/l Isomers,Methamphetamine,Phencyclidine (PCP),PCP,Pregabalin,Fentanyl,Norfentanyl,Methylphenidate,Amobarbital Pentobarbital,Butabarbital';

	foreach ($arr as $k => $vl) {
		$tests = explode(',', $vl);
		foreach ($tests as $t) {
			$update = array('te_' . $k => 1);
			$wpdb->update('ci_test_panels', $update, array('te_name' => $t));
		}
	}
	echo 'ok';
	exit;
}

if (isset($_GET['insert_medications'])) {
	$current_medications = array(
		'Actiq',
		'Adapin',
		'Adderall',
		'Alprazolam',
		'Ambien',
		'Amitriptyline',
		'Amphetamine',
		'Amrix',
		'Ativan',
		'Aventyl',
		'Avinza',
		'Buprenex',
		'Buprenorphine',
		'Butalbital',
		'Butabarbital',
		'Butrans',
		'Carisoprodol',
		'Celexa',
		'Clonazepam',
		'Codeine',
		'Concerta',
		'Darvon',
		'Demerol',
		'Desipramine',
		'Dexedrine',
		'Diazepam',
		'Dilaudid',

		'Dolophine',
		'Doxepin',
		'Duragesic',
		'Elavil',
		'Endocet',
		'Exalgo',
		'Fentanyl',
		'Fioricet',
		'Fiorinal',
		'Flexeril',
		'Flurazepam',
		'Gabapentin',
		'Halcion',
		'Hydrocodone',
		'Hydromorphone',
		'Kadian',
		'Klonopin',
		'Lorazepam',
		'Lorcet/Lortab',
		'Zopiclone',
		'Lyrica',
		'Marinol',
		'Maxidone',
		'Marijuana',
		'Meperidine',
		'Methadone',
		'Methadose',
		'Midazolam',

		'Morphine Sulfate',
		'Morphine',
		'MS Contin',
		'MSIR',
		'Mysoline',
		'Naloxone',
		'Naltrexone',
		'Neurontin',
		'Norco',
		'Nortriptyline',
		'Nucynta',
		'Opana',
		'Oramorph',
		'Oxazepam',
		'Oxycodone',
		'Oxycontin',
		'Oxy IR',
		'Oxymorphone',
		'Pamelor',
		'Paxil',
		'Percocet',
		'Percodan',
		'Percolone',
		'Pertofrane',
		'Phenobarbital',
		'Pregabalin',
		'Prozac',

		'Restoril',
		'Ritalin',
		'Roxanol',
		'Roxicet',
		'Roxicodone',
		'Serax',
		'Soma',
		'Suboxone',
		'Subutex',
		'Tapentadol',
		'Temazepam',
		'Tramadol',
		'Tussionex',
		'Tylenol #3',
		'Tylenol #4',
		'Tylox',
		'Ultracet',
		'Ultram',
		'Valium',
		'Vicodin',
		'Vicoprofen',
		'Vyvanse',
		'Xanax',
		'Zohydro ER',
		'Zoloft',
		'Zolpidem',
		'Zydone',
		'Zubsolv',
		'Esgic',
		'Esgic+',
		'Methylphenidate',
		'Oxycod',
		'Librium',
		'Chlordiazepoxide',
		'Conzip',
		'Embeda',
		'Evekeo',
		'Phentermine',
		'Adipex-p',
		'Lunesta',
		'Gralise'
	);
	foreach ($current_medications as $drug) {
		$wpdb->insert('ci_medications', array('me_name' => $drug, 'me_show_req' => 1));
	}
	echo 'ok';
	exit;
}



if (isset($_REQUEST['fix_collection_reports'])) {

	$results = $wpdb->get_results("select * from ci_reports where collection_date = '0000-00-00'");
	$file_coll = array();
	$collection_date = '';
	foreach ($results as $re) {
		$report_file = $re->upload_files;
		if (!in_array($report_file, $file_coll)) {
			$collection_date = '';
			preg_match("/([0-9]{2})[\.|\_]([0-9]{2})[\.|\_]([0-9]{2,4})/", $report_file, $matches);
			if ($matches) {
				$collection_date = (strlen($matches[3]) == 2 ? '20' : '') . $matches[3] . '-' . $matches[1] . '-' . $matches[2];
			}
		}

		if (!empty($collection_date)) {
			$wpdb->update('ci_reports', array('collection_date' => $collection_date), array('id' => $re->id));
		}
		$count++;
	}
	echo $count;
	exit;
}

if (isset($_REQUEST['fix_batch_tecan'])) {

	$results = $wpdb->get_results("select te_ID,ba.batch_id,te.te_batch_id as tebid from `ci_tecan` te JOIN ci_batches ba on ba.assen = te.te_assen and ba.batch_id !=te.te_batch_id");
	foreach ($results as $re) {
		$tebid = explode('-', $re->tebid, 2);
		$batch_id = $re->batch_id . '-' . $tebid[1];
		$wpdb->update('ci_tecan', array('batch_id' => $batch_id), array('te_ID' => $re->te_ID));
		$count++;
	}
	echo $count;
	exit;
}

if (isset($_GET['pdf_path'])) {

	$sql = "SELECT re.*,
            lab.*,
            rl.lab_additional as rl_lab_additional,
            ba.*,
            cp.cus_ID,
            re.id
    FROM ci_reports re
    JOIN ci_batches ba ON ba.assen = re.patient_id
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    LEFT JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
WHERE re.id='" . $_GET['pdf_path'] . "' group by re.id limit 1";


	$report = $wpdb->get_row($sql, ARRAY_A);
	var_dump($report);

	$rs = trpdd_get_path_result_pdf($report);
	var_dump($rs);
	exit;
}


if (isset($_REQUEST['fix_batch_id_bytecan'])) {

	$results = $wpdb->get_results("select mid,te_ID,ba.batch_id,te.te_batch_id as tebid from `ci_tecan` te JOIN ci_batches ba on ba.assen = te.te_assen and ba.batch_id !=te.te_batch_id");
	foreach ($results as $re) {
		$tebid = explode('-', $re->tebid, 2);
		$batch_id = $re->batch_id . '-' . $tebid[1];
		$wpdb->update('ci_batches', array('batch_id' => $batch_id), array('mid' => $re->mid));
		echo $batch_id, '<br>';
		$count++;
	}
	echo $count;
	exit;
}

if (isset($_REQUEST['fix_batch_by_result'])) {

	$results = $wpdb->get_results("select re.batch_id,ba.mid from `ci_reports` re join ci_batches ba
            on ba.assen=re.patient_id  where ba.batch_id like '%-%' ");
	foreach ($results as $re) {
		$wpdb->update('ci_batches', array('batch_id' => $re->batch_id), array('mid' => $re->mid));
		$count++;
	}
	echo $count;
	exit;
}
if (isset($_REQUEST['fix_collection_report'])) {

	$results = $wpdb->get_results("select * from ci_reports where collection_date='' or collection_date is null", ARRAY_A);
	$count = 0;
	foreach ($results as $d) {
		$clt_date = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", '$2/$3/$1', $d['batch_id']);
		$update = array();
		//var_dump($d);var_dump($clt_date);exit;

		$update['collection_date'] = $clt_date;
		$rs = $wpdb->update('ci_reports', $update, array('id' => $d['id']));
		if ($rs === false) {
			var_dump($wpdb->last_error);
			exit;
		}

		$count++;
	}
	echo 'Count: ' . $count;
	exit;
}

if (isset($_REQUEST['fix_ci_results'])) {
	$wpdb->query("SET SESSION group_concat_max_len = 1000000;");

	$results = $wpdb->get_results("select * from (select *,count(*) as c from `ci_results` where drug !=''
    group by `report_id`,`drug`,`place`,analyte order by c desc limit 1000) as rs where rs.c > 1");

	$count = 0;
	foreach ($results as $row) {
		$ids = $wpdb->get_results("select id from ci_results where drug='{$row->drug}' and report_id = {$row->report_id} and place='{$row->place}' order by comments desc ,conc desc,id desc");

		foreach ($ids as $i => $id) {
			if ($i == 0) continue;

			$wpdb->delete("ci_results", array('id' => $id->id));
		}
		$count++;
	}
	echo 'Count: ' . $count;
	exit;
}

if (isset($_REQUEST['fix_duplicate_reports'])) {
	$wpdb->query("SET SESSION group_concat_max_len = 1000000;");

	$results = $wpdb->get_results("select * from (select count(*) as c,group_concat(id) as ids from `ci_reports` where patient_id!=''
     group by `batch_id`,`patient_id`,`lab_id` order by c desc,estatus desc, physician desc ,id desc) as rs where rs.c>1");

	$count = 0;
	foreach ($results as $row) {
		$ids = explode(',', $row->ids);

		//find
		foreach ($ids as $i => $id) {
			if ($i == 0) continue;

			echo 'D: ' . $id, ' => ' . $ids[0] . '<br>';

			$wpdb->delete("ci_reports", array('id' => $id));
		}
		$count++;
	}
	echo 'Count: ' . $count;
	exit;
}



if (isset($_REQUEST['delete_clinic_doc'])) {
	$docs = $wpdb->get_results("select cd.doc_ID from `ci_clinic_doc` cd left Join ci_doc doc on cd.doc_ID = doc.doc_ID where doc.doc_ID is null");

	foreach ($docs as $doc) {
		$wpdb->delete('ci_clinic_doc', array('doc_ID' => $doc->doc_ID));
	}
	echo count($docs);
	exit;
}
if (isset($_REQUEST['test_new_doc_email'])) {
	$tr_debug = true;
	$rs = tr_check_and_create_doc_user(2309, 1149, true, true);
	var_dump($rs);
	exit;
}

if (isset($_REQUEST['fix_results_table'])) {
	$results = $wpdb->get_results("select * from ci_results where drug='' and conc='' and analyte='' and prescrip='' and val!='' ");
	foreach ($results as $row) {
		$data = @json_decode($row->val, true);
		if (!is_array($data)) {
			continue;
		}
		if (count($data) > 7) {
			var_dump($data);
			exit;
		}
		$rs = $wpdb->update('ci_results', $data, array('id' => $row->id));
		if ($rs === false) {
			//var_dump($row);exit;
		}
		$count++;
	}
	echo $count;
	exit;
}

if (isset($_REQUEST['fix_tests_in_batches'])) {

	$results = $wpdb->get_results("select * from `ci_batches` where panel_tests_requested!=''");
	foreach ($results as $row) {

		$v2 = str_replace($search_tests, $replace_tests, $row->panel_tests_requested);

		if (!empty($v)) {
			$wpdb->update('ci_batches', array('panel_tests_requested' => $v2), array('mid' => $row->mid));
		}

		echo $row->mid, '=>', '<br>';
	}
	echo 'total: ' . count($results);
	exit;
}
if (isset($_REQUEST['fix_custom_providers'])) {

	$results = $wpdb->get_results("select * from `ci_custom_providers`");
	foreach ($results as $row) {
		$v = str_replace($search_tests, $replace_tests, $row->cus_individuals);

		if (!empty($v)) {
			$wpdb->update('ci_custom_providers', array('cus_individuals' => $v), array('cus_ID' => $row->cus_ID));
		}

		echo $row->cus_ID, '=>', '<br>';
	}
	echo 'total: ' . count($results);
	exit;
}
if (isset($_REQUEST['fix_panel_tests_requested'])) {
	$results = $wpdb->get_results("select mid,panel_tests_requested,panel_tests_requested,custom_profile from `ci_batches`
    where panel_tests_requested like '%+%'
    and custom_profile='Use Custom Profile'
    and is_dna = 0
    order by mid desc limit 100", ARRAY_A);
	$panel_tests_requesteds = tr_pdd_get_panel_tests_requesteds();
	foreach ($results as $bat) {
		if (strpos($bat['panel_tests_requested'], '+') > 0) {
			$bat['panel_tests_requested'] = explode('+', $bat['panel_tests_requested']);
			$bat['panel_tests_requested'] = end($bat['panel_tests_requested']);
		}
		$bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
		$bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);

		$new = array();
		foreach ($bat['panel_tests_requested'] as $k => $vl) {
			if (in_array($vl, $panel_tests_requesteds)) {
				$new[] = $vl;
			}
		}
		$new = implode(',', $new);
		$wpdb->update('ci_batches', array('panel_tests_requested' => $new), array('mid' => $bat['mid']));
		echo $bat['mid'], '=>' . $new;
		echo '<br>';
	}
} else if (isset($_REQUEST['test_mail'])) {

	global $debug_email;
	$debug_email = 2;
	$debug_email = 'pntrinh29@gmail.com';
	$user_id = 0;
	if ($_GET['id'] > 0) {
		$user_id = $_GET['id'];
	}
	if ($user_id == 0) exit;
	$pass = wp_generate_password(12, false);

	$rs = trpdd_send_mail_create_user($user_id, $pass);
	if ($rs && $debug_email != 'pntrinh29@gmail.com') {
		$pass_hash = wp_hash_password($pass);
		$wpdb->update($wpdb->users, array('user_pass' => $pass_hash), array('ID' => $user_id));
		echo 'Updated pass: ' . $pass;
	}
	var_dump($rs);
	var_dump($debug_email);
	exit;
} else if (isset($_REQUEST['test_box'])) {

	$box = new CI_Box_Works();
	$box->debug = true;

	try {
		$rs = $box->path_folder('/test/abc/dd/ee');
		var_dump($rs);
		//$rs = $box->create_folder('test');
		// var_dump($rs);
		//$rs = $box->collaborations_folder(5419653338);
		//var_dump($rs);
		// $rs = $box->create_folder('test2',5419653338);

	} catch (Exception $re) {
		var_dump($re);
	}
	var_dump($rs);
	exit;
} else if (isset($_REQUEST['list_dna'])) {
	$dir = WP_CONTENT_DIR . '/DNA/';
	$dirs = scandir($dir);
	sort($dirs);
	$total = 0;
	foreach ($dirs as $d) {
		if ($d == '..' || $d == '.') continue;
		$fs = scandir($dir . $d);
		$count = (count($fs) - 2);

		echo $d . ': ' . $count;
		$total += $count;
		echo '<br>';
	}
	echo 'total: ' . $total;
	exit;
} else if (isset($_REQUEST['fix_batch_pdf_db_file'])) {
	$dir = tr_get_ins_card_dir();
	$results = $wpdb->get_results("select * from ci_batches where is_dna=1 and ins_card_front!='' and ins_card_front not like '%.pdf%'");
	foreach ($results as $row) {
		$filename = $row->ins_card_front;
		$filepath = $dir['path'] . '/' . $filename;
		$size = getimagesize($filepath);
		$info =  pathinfo($filename);

		if (!$size && file_exists($filepath)) {
			$update = array();
			$newname = str_replace('.' . $info['extension'], '.pdf', $filename);
			$newpath = $dir['path'] . '/' . $newname;

			if (rename($filepath, $newpath)) {
				$update['ins_card_front'] = $newname;
				$wpdb->update('ci_batches', $update, array('mid' => $row->mid));
			}
			echo $row->mid, '-><br>';
		}
	}
	echo 'ok';
	exit;
}
if (isset($_REQUEST['fix_results_info'])) {
	$results = $wpdb->get_results("select * from ci_reports ", ARRAY_A);
	foreach ($results as $g) {
		$update = json_decode($g['info'], true);
		$wpdb->update('ci_reports', $update, array('id' => $g['id']));
	}
	echo 'ok';
	exit;
} else if (isset($_REQUEST['fix_batch_images'])) {
	$last_id = isset($_SESSION['last_mid']) ? $_SESSION['last_mid'] : 99999999999;
	$results = $wpdb->get_results("select * from `ci_batches` where mid < {$last_id} order by mid desc limit 11500");
	$card_dir = tr_get_ins_card_dir();
	foreach ($results as $p) {
		$update = array();
		$batch_key = $p->assen;
		$fname = $batch_key . '_ins.jpg';
		$count = 0;
		if (file_exists($card_dir['path'] . '/' . $fname)) {
			$update['ins_card_front'] = $fname;
			$count++;
		}

		$fname = $batch_key . '_ins_back.jpg';
		if (file_exists($card_dir['path'] . '/' . $fname)) {
			$update['ins_card_back'] = $fname;
			$count++;
		}

		$fname = $batch_key . '_id.jpg';
		if (file_exists($card_dir['path'] . '/' . $fname)) {
			$update['patient_id_front'] = $fname;
			$count++;
		}

		$fname = $batch_key . '_ins_back.jpg';
		if (file_exists($card_dir['path'] . '/' . $fname)) {
			$update['patient_id_back'] = $fname;
			$count++;
		}

		if (count($update) > 0) {
			$wpdb->update('ci_batches', $update, array('mid' => $p->mid));
			echo 'mid=>' . $p->mid . '=>' . $count . '<br>';
		}
		$last_id = $p->mid;
	}
	$_SESSION['last_mid'] = $last_id;
	echo '<br>count: ' . count($results);
	exit;
} else if (isset($_REQUEST['fix_batch_received'])) {
	$results = $wpdb->get_results("select * from `ci_batches` where batch_id>0 and doc = spec_received and batch_type='E'");
	$bs = array();
	$i = 0;
	foreach ($results as $row) {
		$batch_id = $row->batch_id;
		$received = $row->spec_received;
		if (!isset($bs[$batch_id])) {
			$n = $wpdb->get_row("select * from ci_batches where batch_id = '{$batch_id}' and spec_received !='{$received}' and batch_type='M' limit 1");
			$bs[$batch_id] = $new_received = $n->spec_received;
		} else {
			$new_received = $bs[$batch_id];
		}
		if (!empty($new_received)) {
			$wpdb->update('ci_batches', array('spec_received' => $new_received), array('mid' => $row->mid));
		}
		$i++;
		echo $i . '.' . $row->mid . '=>' . $row->spec_received . '=>' . $new_received . '<br>';
	}
	exit;
} else if (isset($_REQUEST['fix_collector_duplicate'])) {
	$results = $wpdb->get_results("select *,count(*) as c from `ci_collector` group by concat(col_first,col_last) order by c desc limit 40");
	foreach ($results as $row) {
		$sql = "select * from ci_collector where col_first=%s and col_last=%s and
                                    cli_ID = %s order by col_phone desc";
		$sql = $wpdb->prepare($sql, $row->col_first, $row->col_last, $row->cli_ID);
		$cols = $wpdb->get_results($sql);
		if (count($cols) <= 1) continue;

		$col0 = $cols[0];
		foreach ($cols as $i => $col) {
			if ($i == 0 || !empty($col->col_phone)) continue;
			$wpdb->update('ci_batches', array('col_ID' => $col0->col_ID), array('col_ID' => $col->col_ID));
			$wpdb->delete('ci_collector', array('col_ID' => $col->col_ID));
			echo $col->col_ID . '=>' . $col0->col_ID;
			echo '<br>';
		}
	}
	exit;
} else if (isset($_REQUEST['fix_batch_col'])) {
	$results = $wpdb->get_results("select * from `ci_log_forms` where form='ci_save_form_requisition'
    and data not like '%col_name\":\"\"%'
    and data not like '%batch_short\":\"1\"%' order by created desc");

	echo 'count: ' . count($results);
	foreach ($results as $r) {
		$data = json_decode($r->data, true);
		$assen = $data['bat']['assen'];
		$cli_ID = $data['bat']['cli_ID'];
		$col = $data['col'];
		$col['col_name'] = trim($col['col_name']);

		$batch = $wpdb->get_row("select * from ci_batches where assen = '{$assen}' and cli_ID = {$cli_ID} and col_ID =0 limit 1", ARRAY_A);

		if (!$batch) continue;

		if (!empty($col['col_name'])) {
			if ($col['col_ID'] == 0) {
				$col = $wpdb->get_row("select col_ID from ci_collector where col_first = '{$col['col_name']}' order by col_ID asc limit 1", ARRAY_A);
			}
			$wpdb->update('ci_batches', array('col_ID' => $col['col_ID']), array('mid' => $batch['mid']));
		}
	}
	exit;
} else if (isset($_REQUEST['fix_batch_ins2'])) {
	$results = $wpdb->get_results("select * from `ci_log_forms` where form='ci_save_form_requisition'
    and data not like '%ins2\":{\"ins_last\":\"\"%'
    and data not like '%batch_short\":\"1\"%' order by created desc");
	echo 'count: ' . count($results);
	foreach ($results as $r) {
		$data = json_decode($r->data, true);
		$assen = $data['bat']['assen'];
		$cli_ID = $data['bat']['cli_ID'];
		$ins2 = $data['ins2'];

		$batch = $wpdb->get_row("select * from ci_batches where assen = '{$assen}' and cli_ID = {$cli_ID} and insurance2 is null limit 1", ARRAY_A);

		if (!$batch) continue;

		if (!empty($ins2['ins_first']) || !empty($ins2['ins_last']) || !empty($ins2['ins_company'])) {
			$ins2['pa_ID'] = $batch['patient_id'];
			$ins2['lab_ID'] = $batch['lab_id'];
			$rs = $wpdb->insert('ci_insurances', $ins2);
			$insurance2_id = $wpdb->insert_id;
			$update['insurance2'] = $insurance2_id;
			$wpdb->update('ci_batches', $update, array('mid' => $batch['mid']));
		}
	}
	exit;
} else if (isset($_REQUEST['fix_batch_wrong'])) {
	$results = $wpdb->get_results("select ba.requesting_practice,ba.mid,ba.lab
                            from `ci_batches` ba
                             JOIN ci_clinic cli On ba.requesting_practice = cli.cli_clinic
                             where lab_id = 6 and cli_ID = 330");
	foreach ($results as $row) {
	}
} else if (isset($_REQUEST['fix_clinic_collector'])) {
	$results = $wpdb->get_results("select col_ID,cli_ID from ci_collector");
	echo 'All: ' . count($results);
	foreach ($results as $r) {
		$rs = $wpdb->insert('ci_clinic_collector', array('col_ID' => $r->col_ID, 'cli_ID' => $r->cli_ID));
		if ($rs) {
			$count++;
		}
	}
	echo 'added: ' . $count;
	exit;
} else if (isset($_REQUEST['fix_clinic_doc'])) {
	$results = $wpdb->get_results("select doc_ID,cli_ID from ci_doc");
	echo 'All: ' . count($results);
	foreach ($results as $r) {
		$rs = $wpdb->insert('ci_clinic_doc', array('doc_ID' => $r->doc_ID, 'cli_ID' => $r->cli_ID));
		if ($rs) {
			$count++;
		}
	}
	echo 'added: ' . $count;
	exit;
} else if (isset($_REQUEST['fix_empty_patient_id'])) {
	$results = $wpdb->get_results("select * from ci_batches ba JOIN ci_patients pa
     ON ba.dob = pa.pa_dob and ba.patient_name like concat('%',pa.pa_first,'%') and ba.patient_name like concat('%',pa.pa_last,'%')
     where ba.patient_id = 0 and ba.batch_id !='' and ba.batch_id!='0'
     limit 50");
} else if (isset($_REQUEST['fix_patient_wrong'])) {
	$results = $wpdb->get_results("select * from ci_patients pa
    left join ci_batches ba ON pa.pa_ID = ba.patient_id
    where ba.mid is null");
}

if (isset($_REQUEST['fix_clinic_sdate'])) {
	$c_cli_id = intval($_SESSION['cc_clinic_id']);
	$results = $wpdb->get_results("select cli.cID,bat.mid,bat.assen from ci_clinic cli
                            JOIN ci_batches bat ON bat.cli_ID = cli.cID
                            where cli.cli_sdate ='0000-00-00' and cli.cID > {$c_cli_id}
                            order by cli.cID,bat.mid limit 1000");
	$cli_id = 0;
	$done = 0;

	foreach ($results as $r) {
		$cli_id = $r->cID;
		if ($done == $cli_id) {
			continue;
		}

		$c_cli_id = $cli_id;



		$log = $wpdb->get_row("select data,created from ci_log_forms where data like '%\"{$r->assen}\"%' and form='ci_save_form_requisition' limit 1");
		if (!$log) {
			continue;
		}

		$sdate = date('Y-m-d', $log->created + $current_offset);

		$wpdb->update('ci_clinic', array('cli_sdate' => $sdate), array('cID' => $r->cID));

		echo 'CID: ' . $cli_id . ' =>';
		echo 'Date:' . $sdate;
		echo '<br>';
		$done = $cli_id;
	}

	$_SESSION['cc_clinic_id'] = $c_cli_id;
	exit;
} else if (isset($_REQUEST['fix_clinic_duplicate'])) {
	$results = $wpdb->get_results("select *,count(*) as c from `ci_clinic` group by cli_clinic,`cli_lab-ID` order by c desc");
	$log = '';
	foreach ($results as $row) {
		$sql = $wpdb->prepare("select * from ci_clinic where cli_clinic = %s order by cli_street desc, cli_city desc", $row->cli_clinic);
		$clis = $wpdb->get_results($sql);
		if (count($clis) <= 1) continue;
		$first = $clis[0];

		foreach ($clis as $i => $cli) {
			if ($i == 0) continue;
			$log .= 'deleted #' . $cli->cID . PHP_EOL;


			tr_ipo_update_delete_clinic($first->cID, $cli->cID);
		}

		$log .= 'moved to #' . $first->cID . PHP_EOL;
		// break;
	}
	echo str_replace(PHP_EOL, '<br>', $log);
	tr_ipo_write_log(WP_CONTENT_DIR . '/log_fix_clinic_duplicate.txt', $log);
	exit;
} else if (isset($_REQUEST['fix_clinic_duplicate2'])) {
	if (!empty($_POST['fromid']) && !empty($_POST['toid'])) {
		$fromid = $_POST['fromid'];
		$toid = $_POST['toid'];

		$cli_from = $wpdb->get_row("select * from ci_clinic where cID = {$fromid}");
		$cli_to = $wpdb->get_row("select * from ci_clinic where cID = {$toid}");
		if (!$cli_to || !$cli_from) {
			echo 'No cli to<br>';
		} else {


			tr_ipo_update_delete_clinic($cli_to->cID, $cli_from->cID);

			echo $cli_from->cID . '=>' . $cli_to->cID;
			echo '<br>';
		}
	}
?>
	<form method="post">
		Clinic From ID: <input type="text" name="fromid"><br>
		To ID: <input type="text" name="toid">
		<br>
		<input type="submit" value="Submit">
	</form>
<?php
	exit;
}

if (isset($_REQUEST['fix_current_medication_other'])) {

	$results = $wpdb->get_results("select current_medication,current_medication_other,mid from ci_batches
                        where current_medication_other !='' ");
	foreach ($results as $r) {

		$r->current_medication .= (empty($r->current_medication)) ? '' : ',';
		$r->current_medication .= $r->current_medication_other;
		$data = array(
			'current_medication' => $r->current_medication,
			'current_medication_other' => ''
		);
		$wpdb->update('ci_batches', $data, array('mid' => $r->mid));
		echo $r->mid;
		echo '<br>';
	}
	exit;
} else if (isset($_REQUEST['fix_current_medication_comma'])) {

	$results = $wpdb->get_results("select current_medication,mid from ci_batches
                        where current_medication like '%,' ");
	foreach ($results as $r) {
		$r->current_medication = tr_trim_name($r->current_medication);
		$data = array(
			'current_medication' => $r->current_medication,
		);
		$wpdb->update('ci_batches', $data, array('mid' => $r->mid));
	}
	exit;
} else if (isset($_REQUEST['fix_date_dob'])) {

	$results = $wpdb->get_results("select mid,assen,dob,doc from ci_batches where dob = '0000-00-00' and batch_type='E' ", ARRAY_A);
	foreach ($results as $rs) {
		$log = $wpdb->get_var("select data from `ci_log_forms` where
        data like '%{$rs['assen']}%'
        and data like '%{$rs['doc']}%'
        and form='ci_save_form_requisition' ");
		$log = json_decode($log, true);
		if (!$log) continue;

		$dob = $log['pa']['pa_dob'];
		if (empty($dob)) continue;
		$dob = tr_format_date_default($dob);

		$wpdb->update('ci_batches', array('dob' => $dob), array('mid' => $rs['mid']));
	}

	exit;
} else if (isset($_REQUEST['fix_doc_name'])) {
	$results = $wpdb->get_results("select * from ci_doc where doc_first like '% %' and doc_last=''", ARRAY_A);
	echo 'count: ' . count($results);
	echo '<br>';
	foreach ($results as $row) {
		$doc_specialty = '';
		if (strpos($row['doc_first'], ',') > 0) {
			list($doc_last, $doc_first) = explode(',', $row['doc_first'], 2);

			//check specialty
			$doc_first = trim($doc_first);
			$doc_last = trim($doc_last);
			if (strpos($doc_first, ' ') > 0) {
				list($doc_first, $doc_specialty) = explode(' ', $doc_first, 2);
			}
		} else {
			list($doc_first, $doc_last) = explode(' ', $row['doc_first'], 2);
			$doc_first = trim($doc_first);
			$doc_last = trim($doc_last);
			if (strpos($doc_last, ' ') > 0) {
				list($doc_last, $doc_specialty) = explode(' ', $doc_last, 2);
			}
		}

		$doc_first = ucwords(strtolower($doc_first));
		$doc_last = ucwords(strtolower($doc_last));

		$update = array(
			'doc_first' => $doc_first,
			'doc_last' => $doc_last
		);

		if (empty($row['doc_specialty'])) {
			$update['doc_specialty'] = $doc_specialty;
		}

		$wpdb->update('ci_doc', $update, array('doc_ID' => $row['doc_ID']));
		if ($row['doc_ID'] > 1) {
			$doc_name = tr_trim_name($doc_last . ', ' . $doc_first);
			$wpdb->update('ci_batches', array('requesting_physician' => $doc_name), array('doc_ID' => $row['doc_ID']));
		}


		echo $row['doc_first'] . '=> ' . $doc_last . ',' . $doc_first . '. spec: ' . $doc_specialty;
		echo '<br>';
	}
	exit;
} else if (isset($_REQUEST['fix_doc_name_cap'])) {
	$results = $wpdb->get_results("select * from ci_doc ", ARRAY_A);
	echo 'count: ' . count($results);
	echo '<br>';
	foreach ($results as $row) {

		$update = array(
			'doc_first' => trim(ucwords(strtolower($row['doc_first']))),
			'doc_last' => trim(ucwords(strtolower($row['doc_last'])))
		);
		$wpdb->update('ci_doc', $update, array('doc_ID' => $row['doc_ID']));
		if ($row['doc_ID'] > 1) {
			$doc_name = tr_trim_name($update['doc_last'] . ', ' . $update['doc_first']);
			$wpdb->update('ci_batches', array('requesting_physician' => $doc_name), array('doc_ID' => $row['doc_ID']));
		}
	}
	exit;
} else if (isset($_REQUEST['fix_ins_name_cap'])) {
	$results = $wpdb->get_results("select * from ci_insurances ", ARRAY_A);
	echo 'count: ' . count($results);
	echo '<br>';
	foreach ($results as $row) {

		$update = array(
			'ins_company' => trim(ucwords(strtolower($row['ins_company']))),
		);
		$wpdb->update('ci_insurances', $update, array('ins_ID' => $row['ins_ID']));
	}
	exit;
} else if (isset($_REQUEST['fix_batch_clinic_cap'])) {
	$results = $wpdb->get_results("select * from ci_batches ba
                                JOIN ci_clinic cli On cli.cID = ba.cli_ID and ba.requesting_practice = cli.cli_clinic
                                ", ARRAY_A);
	echo 'count: ' . count($results);
	echo '<br>';
	foreach ($results as $row) {

		$update = array(
			'ins_company' => trim(ucwords(strtolower($row['ins_company']))),
		);
		$wpdb->update('ci_insurances', $update, array('ins_ID' => $row['ins_ID']));
	}
	exit;
} else if (isset($_REQUEST['fix_doc_duplicate2'])) {
	if (!empty($_POST['docid']) && !empty($_POST['docto'])) {
		$docid_from = $_POST['docid'];
		$docid_to = $_POST['docto'];

		$doc_from = $wpdb->get_row("select * from ci_doc where doc_ID = {$docid_from}");
		$doc_to = $wpdb->get_row("select * from ci_doc where doc_ID = {$docid_to}");
		if (!$doc_to || !$doc_from) {
			echo 'No doc to<br>';
		} else {
			$wpdb->update('ci_batches', array('doc_ID' => $doc_to->doc_ID), array('doc_ID' => $doc_from->doc_ID));
			$wpdb->update('ci_custom_providers', array('doc_ID' => $doc_to->doc_ID), array('doc_ID' => $doc_from->doc_ID));
			$wpdb->update('ci_clinic_doc', array('doc_ID' => $doc_to->doc_ID), array('doc_ID' => $doc_from->doc_ID));

			$has_clinic_doc = $wpdb->get_var("select doc_ID from ci_clinic_doc where doc_ID ={$doc_to->doc_ID} and cli_ID = {$doc_from->cli_ID}");
			if (!$has_clinic_doc) {
				$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_to->doc_ID, 'cli_ID' => $doc_from->cli_ID));
				echo 'Added clinic_doc<br>';
			}

			$wpdb->delete('ci_doc', array('doc_ID' => $doc_from->doc_ID));
			echo $doc_from->doc_ID . '=>' . $doc_to->doc_ID;
			echo '<br>';
		}
	}
?>
	<form method="post">
		Doc From ID: <input type="text" name="docid"><br>
		To ID: <input type="text" name="docto">
		<br>
		<input type="submit" value="Submit">
	</form>
<?php
	exit;
} else if (isset($_REQUEST['import_doc'])) {
	$cli_id = $_REQUEST['clinic_id'];

	$file = @$_FILES['file'];
	if (!empty($file['tmp_name'])) {
		$f = fopen($file['tmp_name'], 'r');
		$i = 0;
		while ($row = fgetcsv($f)) {
			$i++;
			if ($i <= 1) continue;
			if (empty($row[0]) || empty($row[1])) continue;
			$data = array(
				'doc_first' => ucwords(strtolower($row[0])),
				'doc_last' => ucwords(strtolower($row[1])),
				'doc_address' => $row[2],
				'doc_city' => $row[3],
				'doc_state' => $row[4],
				'doc_zip' => $row[5],
				'doc_phone' => $row[6],
				'doc_npi' => $row[7],
				'doc_mnemonic' => $row[8]
			);

			echo $data['doc_last'] . ',' . $data['doc_first'];
			echo '=>';

			if ($cli_id > 0) {
				$data['cli_ID'] = $cli_id;
				$sql = "select count(*) as count from ci_doc where ((doc_first=%s and doc_last = %s) or (doc_last=%s and doc_first = %s)) and doc_npi = %s and cli_ID = %s";
				$sql = $wpdb->prepare($sql, $data['doc_first'], $data['doc_last'], $data['doc_first'], $data['doc_last'], $data['doc_npi'], $cli_id);
				$exists = $wpdb->get_var($sql);
				if ($exists == 0) {
					$wpdb->insert('ci_doc', $data);
					echo 'new: ' . $wpdb->insert_id;
				}
			} else {
				$sql = "select * from ci_doc where ((doc_first=%s and doc_last = %s) or (doc_last=%s and doc_first = %s)) and (doc_npi ='' or doc_mnemonic='') ";
				$sql = $wpdb->prepare($sql, $data['doc_first'], $data['doc_last'], $data['doc_first'], $data['doc_last']);
				$rows = $wpdb->get_results($sql);
				echo 'exist: ' . count($rows);
				foreach ($rows as $r) {
					$update = array(
						'doc_npi' => $data['doc_npi'],
						'doc_mnemonic' => $data['doc_mnemonic'],
						'doc_first' => $data['doc_first'],
						'doc_last' => $data['doc_last']
					);
					if ($r->doc_ID > 0) {
						$wpdb->update('ci_doc', $update, array('doc_ID' => $r->doc_ID));
					}
				}
			}



			echo '<br>';
		}
	}
} else if ($_REQUEST['import'] == 'insv') {
	$file = @$_FILES['file'];
	if (!empty($file['tmp_name'])) {
		$f = fopen($file['tmp_name'], 'r');
		$i = 0;
		while ($row = fgetcsv($f)) {
			$i++;
			if ($i <= 1) continue;
			if (empty($row[0]) || empty($row[1])) continue;
			$row[3] = strtolower($row[3]);

			if ($row[3] == 'yes') {
				$row[3] = 1;
			} else if ($row[3] == 'no') {
				$row[3] = 0;
			} else {
				$row[3] = '';
			}

			$row[4] = strtolower($row[4]);
			if ($row[4] == 'yes') {
				$row[4] = 1;
			} else if ($row[4] == 'no') {
				$row[4] = 0;
			} else {
				$row[4] = '';
			}

			$data = array(
				'equiv_mnemonic' => $row[0],
				'equiv_name' => $row[1],
				'equiv_financial_class' => $row[2],
				'equiv_in_network' => $row[3],
				'equiv_jvhl' => $row[4],
				'equiv_jvhl_code' => $row[5],
				'equiv_clms_ID' => $row[6],
			);

			$wpdb->insert('ci_ins_equiv', $data);
		}
	}
}

if ($_REQUEST['import'] == 'clinic') {
	$file = @$_FILES['file'];
	if (!empty($file['tmp_name'])) {
		$f = fopen($file['tmp_name'], 'r');
		$i = 0;
		while ($row = fgetcsv($f)) {
			$i++;
			if ($i <= 1) continue;
			if (empty($row[0]) || empty($row[1])) continue;

			$data = array(
				'equiv_mnemonic' => $row[0],
				'equiv_name' => $row[1],
				'equiv_financial_class' => $row[2],
				'equiv_in_network' => $row[3],
				'equiv_jvhl' => $row[4],
				'equiv_jvhl_code' => $row[5],
				'equiv_clms_ID' => $row[6],
			);

			$wpdb->insert('ci_clinic', $data);
		}
	}
} else if ($_REQUEST['import'] == 'compendium') {
	$file = @$_FILES['file'];
	if (!empty($file['tmp_name'])) {
		$f = fopen($file['tmp_name'], 'r');
		$i = 0;
		while ($row = fgetcsv($f)) {

			$data = array(
				'co_bill_code' => $row[0],
				'co_order_code' => $row[1],
				'co_order_name' => $row[2],
				'co_order_able' => $row[3],
				'co_loinc_code' => $row[4],
				'co_unit_measure' => $row[5],
				'co_cpt_code' => $row[6],
				'co_specimen_type' => $row[7],
				'co_utilization_rate' => $row[8],
				'co_method_name' => $row[9],
				'co_instrument_name' => $row[10],
			);

			$wpdb->insert('ci_compendiums', $data);
		}
	}
}

if ($_REQUEST['import'] == 'clinic') {
	$file = @$_FILES['file'];
	if (!empty($file['tmp_name'])) {
		$f = fopen($file['tmp_name'], 'r');
		$i = 0;
		$fields = $wpdb->get_col("DESCRIBE ci_clinic");
		$headers = array();
		$data = array();
		$import_clinic = get_option('tr_import_clinic_ids', array());
		while ($row = fgetcsv($f)) {

			if (empty($headers)) {
				$headers = $row;
			} else {
				$new = array();
				foreach ($headers as $ii => $k) {
					if (in_array($k, $fields)) {
						$new[$k] = $row[$ii];
					}
				}
				$data[] = $new;
			}
		}

		echo 'Total: ' . count($data) . '<br>';
		foreach ($data as $cli) {
			$oldid = $cli['cID'];
			unset($cli['cID']);
			$wpdb->insert('ci_clinic', $cli);
			$cli_id = $wpdb->insert_id;
			if ($cli_id > 0) {
				tr_check_and_create_clinic_user($cli_id, true);
				$succ++;
				$import_clinic[$oldid] = $cli_id;
			} else {
				echo 'Fail: ' . $cli['cli_clinic'] . '<br>';
				$fail++;
			}
		}
		echo 'Done: ' . $succ . '<br>';
		echo 'Fail: ' . $fail . '<br>';
		update_option('tr_import_clinic_ids', $import_clinic);
	}
}

if ($_REQUEST['import'] == 'doc') {
	$file = @$_FILES['file'];
	if (!empty($file['tmp_name'])) {
		$f = fopen($file['tmp_name'], 'r');
		$i = 0;
		$fields = $wpdb->get_col("DESCRIBE ci_doc");
		$fields[] = 'cIDs';
		$headers = array();
		$data = array();
		$import_clinic = get_option('tr_import_clinic_ids', array());

		$import_doc = get_option('tr_import_doc_ids', array());
		while ($row = fgetcsv($f)) {

			if (empty($headers)) {
				$headers = $row;
			} else {
				$new = array();
				foreach ($headers as $ii => $k) {
					if (in_array($k, $fields)) {
						$new[$k] = $row[$ii];
					}
				}
				$data[] = $new;
			}
		}

		echo 'Total: ' . count($data) . '<br>';

		foreach ($data as $doc) {
			$oldid = $doc['doc_ID'];
			$cli_ids = $doc['cIDs'];
			unset($doc['doc_ID']);
			unset($doc['cIDs']);
			$wpdb->insert('ci_doc', $doc);
			$doc_id = $wpdb->insert_id;
			if ($doc_id > 0) {
				$cli_ids = explode(',', $cli_ids);
				foreach ($cli_ids as $cli_id) {
					if (isset($import_clinic[$cli_id])) {
						$cli_id = $import_clinic[$cli_id];
					}
					$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $cli_id));
				}
				tr_check_and_create_doc_user($doc_id, $doc['cli_ID'], true);
				$succ++;
				$import_doc[$doc_id] = $oldid;
			} else {
				echo 'Fail: ' . $doc['doc_first'] . '<br>';
				$fail++;
			}
		}

		update_option('tr_import_doc_ids', $import_doc);
	}
}

if ($_REQUEST['import'] == 'rep') {
	$file = @$_FILES['file'];
	if (!empty($file['tmp_name'])) {
		$f = fopen($file['tmp_name'], 'r');
		$i = 0;
		$headers = array();
		$data = array();
		while ($row = fgetcsv($f)) {

			if (empty($headers)) {
				$headers = $row;
			} else {
				$new = array();
				foreach ($headers as $ii => $k) {
					$new[$k] = $row[$ii];
				}
				$data[] = $new;
			}
		}

		foreach ($data as $row) {
			$rep = array(
				'rep_ID' => $row['rep_ID'],
				'lab_ID' => $row['lab_ID'],
				'rep_first' => $row['rep_first'],
				'rep_last' => $row['rep_last'],
				'rep_middle' => $row['rep_middle'],
				'rep_address' => $row['rep_address'],
				'rep_city' => $row['rep_city'],
				'rep_state' => $row['rep_state'],
				'rep_zip' => $row['rep_zip'],
				'rep_phone' => $row['rep_phone'],
				'rep_cell' => $row['rep_cell'],
				'rep_email' => $row['rep_email'],
				'rep_comission' => $row['rep_comission'],
				'rep_sdate' => $row['rep_sdate'],
				'rep_edate' => $row['rep_edate'],
				'rep_is_dna' => $row['rep_is_dna'],
				'rep_user_ID' => $row['rep_user_ID'],
				'rep_tox' => $row['rep_is_tox'],
				'rep_blood' => $row['rep_is_blood'],
				'rep_allergy' => $row['rep_is_allergy']
			);
			$wpdb->insert('ci_lab_reps', $rep);
			$rep_id = $wpdb->insert_id;
			if ($rep_id > 0) {
				// Tr_Session_Class::add('Created New Rep Successfully!');
			} else {
				Tr_Session_Class::add_error('Error. create Rep wrong to db!' . $wpdb->last_error);
			}

			if ($rep_id > 0) {
				$email = $rep['rep_email'];
				//check lab_rep user role
				if (!empty($email) && is_email($email)) {
					$u = get_user_by('email', $email);
					if ($u) {
						$user_id = $u->ID;
						$fname = get_user_meta($user_id, 'first_name', true);
						$lname = get_user_meta($user_id, 'last_name', true);

						if (empty($fname)) {
							update_user_meta($user_id, 'first_name', $rep['rep_first']);
							$userarr = array(
								'ID' => $user_id,
								'first_name' => @$rep['rep_first'],
								'last_name' => @$rep['rep_last'],
								'repplay_name' => trim($rep['rep_first'] . ' ' . @$rep['rep_last']),
							);
							wp_update_user($userarr);
						}
						if (empty($lname)) {
							update_user_meta($user_id, 'last_name', $rep['rep_last']);
						}
					} else {
						//need create new
						$pass = wp_generate_password(12, false);
						$userarr = array(
							'user_login' => $email,
							'user_email' => $email,
							'user_pass' => $pass,
							'first_name' => $rep['rep_first'],
							'last_name' => $rep['rep_last'],
							'repplay_name' => $rep['rep_first'] . ' ' . $rep['rep_last'],
							'role' => 'lab_rep'
						);
						$user_id = wp_insert_user($userarr);
						if (!is_wp_error($user_id)) {
							$rs = trpdd_send_mail_create_user($user_id, $pass);

							Tr_Session_Class::add('Created User: ' . $email);
							if ($rs) {
								Tr_Session_Class::add('Sent email to ' . $email);
							} else {
								Tr_Session_Class::add_error('Error while send email to ' . $email);
							}
						} else {
							Tr_Session_Class::add_error($user_id->get_error_message());
						}
					}
				} else if (!empty($email) && !is_email($email)) {
					Tr_Session_Class::add_error("Please Check Email");
				}


				if (!is_wp_error($user_id) && $user_id > 0) {
					update_user_meta($user_id, '_rep_id', $rep_id);
					update_user_meta($user_id, '_tox', $rep['rep_tox']);
					update_user_meta($user_id, '_blood', $rep['rep_blood']);
					update_user_meta($user_id, '_dna', $rep['rep_dna']);
					update_user_meta($user_id, '_allergy', $rep['rep_allergy']);
					do_action('tr_change_role', $user_id, 'lab_rep');
				}

				//end check
			}
		}
	}
}


if ($_REQUEST['import'] == 'csv') {
	$file = @$_FILES['file2'];
	$tbl = $_POST['tbl'];
	if (!empty($file['tmp_name'])) {
		$fp = fopen($file['tmp_name'], 'r');
		var_dump($fp);

		$names = '';
		$count = 0;
		while ($row = fgetcsv($fp)) {

			unset($row[0]);
			if (empty($names)) {
				$names = $row;
			} else {
				$count++;
				$data = array();
				foreach ($names as $i => $k) {
					$data[$k] = $row[$i];
				}
				unset($data['cID']);
				unset($data['cli_user_group_ID']);
				unset($data['clig_payment_phone']);
				unset($data['clig_payment_email']);
				// echo $importSQL;exit;
				$rs = $wpdb->insert($tbl, $data);
				var_dump($wpdb->last_error);
				var_dump($rs);
			}
		}
		echo 'Count:' . $count;
	}
?>
	csv
	<form method="post" enctype="multipart/form-data">
		<input type="text" name="tbl" value="" />
		<input type="file" name="file2">
		<input type="submit">
	</form>
<?php
	exit;
}


?>
<form method="post" enctype="multipart/form-data">
	<input type="file" name="file">
	<input type="submit">
</form>