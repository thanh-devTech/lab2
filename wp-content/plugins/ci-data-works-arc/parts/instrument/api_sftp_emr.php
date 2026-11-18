<?php
global $wpdb, $current_role;

$api_key 	= 'api_sftp_emr';

$public_file = WP_CONTENT_DIR . '/uploads/sftp_emr_pub.pub';
$private_file = WP_CONTENT_DIR . '/uploads/sftp_emr_ppk.ppk';
$log_dir_orders = WP_CONTENT_DIR . '/log/sftp_emr_orders/';
$log_dir_results = WP_CONTENT_DIR . '/log/sftp_emr_results/';

if (!file_exists($log_dir_orders)) {
	wp_mkdir_p($log_dir_orders);
}
if (!file_exists($log_dir_results)) {
	wp_mkdir_p($log_dir_results);
}

include(TRCPDD_PATH . 'api/ci_api_sftp_emr.php');

$covid_status = array(
	'NEG' => 'Negative',
	'POS' => 'Positive',
	'RER' => 'ReRun',
	'IND' => 'Indeterminate',
	'INC' => 'Inconclusive',
	'INV' => 'Invalid',
);


if (isset($_POST['options'])) {
	$options = $_POST['options'];
	$options['enable'] = !empty($options['enable']) ? 1 : 0;
	$options['disable_ssh_rsa'] = !empty($options['disable_ssh_rsa']) ? 1 : 0;
	$options['check_nte_clinic'] = !empty($options['check_nte_clinic']) ? 1 : 0;
	$options['alltypes'] = !empty($options['alltypes']) ? 1 : 0;
	if (empty($options['cron'])) {
		$options['cron'] = 10;
	}
	if (isset($_FILES['pub']) && !empty($_FILES['pub']['name'])) {

		if (move_uploaded_file($_FILES['pub']['tmp_name'], $public_file)) {
			$options['pub'] = basename($public_file);
		}
	}

	if (isset($_FILES['ppk']) && !empty($_FILES['ppk']['name'])) {

		if (move_uploaded_file($_FILES['ppk']['tmp_name'], $private_file)) {
			$options['ppk'] = basename($private_file);
			$fcontent = file_get_contents($private_file);
			if (stripos($fcontent, 'PuTTY') !== false) {
				//convert

				if (function_exists('trlnc_convert_key')) {
					$arr = trlnc_convert_key($fcontent);

					if (!empty($arr['pub'])) {
						$options['pub'] = basename($public_file);
						file_put_contents($public_file, $arr['pub']);
					}
					if (!empty($arr['ppk'])) {
						file_put_contents($private_file, $arr['ppk']);
					}
				}
			}
		}
	}

	$old_options = get_option('tr_sftp_emr_st', array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_tr_sftp_emr_st', $bkdata);

	update_option('tr_sftp_emr_st', $options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_sftp_emr_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_sftp_emr');

	if ($options['enable']) {
		wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_api_sftp_emr');
	}
}
$options = array_merge(array(
	'enable' => 0,
	'disable_ssh_rsa' => 1,
	'check_nte_clinic' => 0,
	'alltypes' => 0,
	'lab_id' => 0,
	'cli_id' => 0,
	'doc_id' => 0,
	'host' => '',
	'port' => 22,
	'username' => '',
	'password' => '',
	'order_folder' => '/',
	'result_folder' => '/',
	'pub' => '',
	'ppk' => '',
	'notes' => '',
	'cron' => 10,
), get_option('tr_sftp_emr_st', array()));




try {
	$client = new CI_Api_Sftp_EMR($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}




if ($act == 'test_sync') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$client->sync_reqs();
		echo str_replace("\n", '<br>', $client->log);
		echo "<br><br>done";
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_report') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login($options['results_username'], $options['results_password']);
		echo 'logged in<br>';
		$client->sync_reports();
		echo str_replace("\n", '<br>', $client->log);
		$files = $client->scan_dir($client->result_folder);
		echo '<br>List files in Results folder:<br>';
		foreach ($files as $file) {
			echo '<li>' . $file . '</li>';
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'log') {

	$row_per_page = 200;
	if (isset($_GET['get_codes'])) {
		$row_per_page = 9999999999;
	}
	$pagination = new Tr_Pagination_class2($row_per_page);
	$where = " fi.m_type='{$client->api_id}'  ";
	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s) ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%',
			$_REQUEST['s']
		);
	}
	if (!empty($_REQUEST['haspos'])) {
		if ($_REQUEST['haspos'] == 1) {
			$where .= " and re.has_pos = 1";
		} else if ($_REQUEST['haspos'] == 0) {
			$where .= " and re.has_pos = 0 ";
		}
	}
	$total = 0;
	$childs = array();
	$btypes = array('pcr');
	if ($options['alltypes']) {
		$btypes = tr_pdd_get_batch_types();
	}
	foreach ($btypes as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		$childs[] = "select ba.mid,
				ba.assen,
				ba.doc,
				ba.oldreq,
				re.covid_pos,
				re.id,
				re.estatus,
				re.instrument,
				re.created_date,
				pa.pa_first,
				pa.pa_last,
				o.mo_ID	,
				o.mo_status,
				fi.m_ID,
				fi.m_time,
				'{$btype}' as btype
			from ci_sync_api_files fi
			join ci_sync_api_orders o ON o.mo_file_id = fi.m_ID and o.mo_btype = '{$btype}'
			 join {$batch_tbl} ba ON o.mo_mid = ba.mid
			left join {$report_tbl} re ON re.patient_id = ba.assen
			left join ci_patients pa ON pa.pa_ID = ba.patient_id
			where {$where}
		";
		$total += $wpdb->get_var("select count(*) as c from {$batch_tbl} ba
			join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
			join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
			where {$where}");
	}

	$pagination->setTotal($total);
	$offset = $pagination->offset();

	$sql = implode(' UNION ALL ', $childs);
	$sql = "select * from ({$sql}) rs 
	order by mo_ID desc
	limit 500";
	$rows = $wpdb->get_results($sql);

	$statuses = array('' => 'All', '1' => 'Pos', '0' => 'Neg');
?>

	<div>
		<input type="search" id="assen_search" value="<?php echo $_REQUEST['s'] ?>" placeholder="Accession or patient name">
		<select id="haspos">
			<?php
			foreach ($statuses as $k => $v) {
				$selected = '';
				if (isset($_REQUEST['haspos']) && $_REQUEST['haspos'] === $k) {
					$selected = 'selected';
				}
				echo "<option value='{$k}' {$selected}>{$v}</option>";
			}
			?>
		</select>
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:40px"></th>
				<th style="width:140px">#</th>
				<th style="width:40px">Type</th>
				<th>Accession</th>
				<th>Patient</th>
				<th style="width:60px">Resulted</th>
				<th style="width:100px;">Sent</th>
				<th>Date/time</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php

			foreach ($rows as $i => $row) {


				if (isset($_REQUEST['debug2'])) {

					if (
						strtolower($row->pa_first) == strtolower($row->ins_last)
						&& strtolower($row->pa_last) == strtolower($row->ins_first)
					) {
						$wpdb->update('ci_insurances', array(
							'ins_first' => $row->ins_last,
							'ins_last' => $row->ins_first,
						), array('ins_ID' => $row->ins_ID));
					}
				}
				if ($row->mo_status == 1 && empty($row->mo_sent_dt)) {
					$ftime = filemtime($log_dir_results . $row->assen . '.hl7');
					if ($ftime) {
						$offset = get_option('gmt_offset') * 3600;
						$ftime = $ftime + $offset; //offset
						$wpdb->update('ci_sync_api_orders', array(
							'mo_sent_dt' => date('Y-m-d H:i:s', $ftime),
						), array('mo_ID' => $row->mo_ID));
						$row->mo_sent_dt = date('Y-m-d H:i:s', $ftime);
					}
				}
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>" data-mid="<?php echo $row->mid ?>">
					<td><?php echo $i + 1 ?></td>
					<td><?php echo $row->oldreq ?></td>
					<td><?php echo $row->btype ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $row->btype . '_' . $row->mid ?>&short=1"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->pa_last . ', ' . $row->pa_first ?></td>
					<td><a href="?page=<?php echo $row->btype . '_results' ?>&act=edit&id=<?php echo $row->id;
																							if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
							<?php echo $row->estatus == 'C' ? 'Yes' : '.' ?>
						</a>
					</td>
					<td><?php echo $row->mo_status == 1 ? (!empty($row->mo_sent_dt) ? $row->mo_sent_dt : 'Yes') : '' ?></td>
					<td><?php echo date('Y-m-d H:i:s', strtotime($row->doc)) ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V4::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->m_ID, 'btype' => $row->btype), false) . '">View Hl7</a>';
						?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
<?php
	exit;
} else if ($act == 'send_result') {
	$return = array('status' => 'ok', 'msg' => '');
	ob_start();

	try {
		$id = $_REQUEST['id'];
		$mid = $_REQUEST['mid'];
		$client->debug = true;
		$client->connect();
		if ($client->login($options['username'], $options['password'])) {
			$wpdb->query("update ci_sync_api_orders o set mo_status = 0 where
		                    o.mo_mid = {$mid}  ");
			$rs = $client->sync_reports($id);
			if (!$rs) {
				$return['status'] = 'error';
				$return['msg'] = 'error';
			}
		} else {
			$return['status'] = 'error';
		}
	} catch (Exception $e) {
		$return['status'] = 'error';
		$return['msg'] = $e->getMessage();
	}
	$return['debug'] = ob_get_clean();
	wp_send_json($return);
	exit;
} else if ($act == 'viewrshl7') {
	try {
		$hl7_parser = new CI_HL7_Parser();
		$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$row = $wpdb->get_row("select f.*,
							o.mo_btype,
							o.mo_mid,
							ba.assen,
							ba.mid
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID and o.mo_btype='{$btype}'
							  join {$batch_tbl} ba ON ba.mid = o.mo_mid 
		                     where m_ID = {$_REQUEST['fid']}");

		echo $wpdb->last_error;

		$local_file = $log_dir_orders . basename($row->m_file);
		if (file_exists($local_file)) {
			echo 'order file.:' . basename($row->m_file);
			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport', 'btype' => $btype), false);
			$req_link = '?page=ci_batches_view&act=edit&id=' . $btype . '_' . $row->mid;
			echo '  -  <a href="' . $reimportlink . '">reimport</a>';
			echo '  -  <a href="' . $req_link . '" target=blank> req</a>';
			$hl7 = file_get_contents($local_file);
			echo '<textarea style="width:2000px;height:300px">';
			echo esc_textarea($hl7);
			echo '</textarea>';
			if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
				$parsed = $hl7_parser->parse_hl7($hl7);
				if (!empty($parsed) && !isset($parsed['ERROR'])) {
					echo $hl7_parser->render_html($parsed);
				}
			}
		} else {

			$file_path = $client->order_folder . '/archive/' . basename($row->m_file);
			$client->connect();
			if ($client->login()) {

				try {
					$client->receiveFile($file_path, $local_file);
				} catch (Exception $e) {
				}
				echo 'order file';
				$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
				echo '   <a href="' . $reimportlink . '">reimport</a>';
				$hl7 = file_get_contents($local_file);
				echo '<textarea style="width:2000px;height:300px">';
				echo esc_textarea($hl7);
				echo '</textarea>';
				if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
					$parsed = $hl7_parser->parse_hl7($hl7);
					if (!empty($parsed) && !isset($parsed['ERROR'])) {
						echo $hl7_parser->render_html($parsed);
					}
				}
			} else {
				echo 'login fail for order';
			}
		}

		$rs_file = $row->assen . '.hl7';
		if (file_exists($log_dir_results . $rs_file)) {
			echo 'output file:' . $rs_file;
			$rebuildlink = Tr_Base_Class_V6::link(array('act' => 'rebuild'), false);
			echo '   <a href="' . $rebuildlink . '">rebuild</a>';

			$resendlink = Tr_Base_Class_V6::link(array('act' => 'resend'), false);
			echo '  - <a href="' . $resendlink . '">resend</a>';

			$pdf_link 	 = Tr_Base_Class_V6::link(array('act' => 'viewpdf'), false);
			echo ' -  <a href="' . $pdf_link . '" target=_blank>view pdf</a>';

			$hl7 = file_get_contents($log_dir_results . $rs_file);
			echo '<textarea style="width:2000px;height:300px">';
			echo esc_textarea($hl7);

			echo '</textarea>';
			if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
				$parsed = $hl7_parser->parse_hl7($hl7);
				if (!empty($parsed) && !isset($parsed['ERROR'])) {
					echo $hl7_parser->render_html($parsed);
				}
			}
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'viewpdf') {
	$btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$row = $wpdb->get_row("select f.*,
							o.mo_btype,
							o.mo_mid,
							ba.assen,
							ba.mid
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID and o.mo_btype='{$btype}'
							  join {$batch_tbl} ba ON ba.mid = o.mo_mid 
		                     where m_ID = {$_REQUEST['fid']}");
	$rs_file = $row->assen . '.hl7';
	//var_dump($log_dir_results . $rs_file);
	if (file_exists($log_dir_results . $rs_file)) {
		$hl7 = file_get_contents($log_dir_results . $rs_file);
		//echo $hl7;
		$arr = $client->hl7_to_array($hl7, array('OBR'));

		$base64code = '';
		foreach ($arr['OBX'] as $obx) {
			if ($obx['OBX.3']['OBX.3.1'] == 'pdf') {
				$base64code = $obx['OBX.5']['OBX.5.5'];
				break;
			}
		}
		header("Content-type:application/pdf");
		echo base64_decode($base64code);
	} else {
		echo $log_dir_results . $rs_file;
	}

	exit;
} else if ($act == 'viewkeys') {
	$key = $_REQUEST['key'];
	$keyfile = $options[$key];
	$fcontent = file_get_contents(WP_CONTENT_DIR . '/uploads/' . $keyfile);
	//$fcontent = str_replace(array("\r","\n"),array('[r]'."\r",'[n]'."\n"),$fcontent);

	echo '<form method="post" id="form_key">';
	echo '<textarea name="key_content" style="width:100%;height:300px">';
	echo $fcontent;
	echo '</textarea>';
	echo '<input type="submit" class="button" value="save">';
	echo '<input type="hidden" name="key" value="' . $key . '"/>';
	echo '<input type="hidden" name="act" value="save_key"/>';
	echo '</form>';

	exit;
} else if ($act == 'save_key') {
	$key = $_REQUEST['key'];

	$fcontent = tr_pdd_stripslashes_deep($_POST['key_content']);
	$fcontent = str_replace("\r\n", "\n", $fcontent);
	$fcontent = str_replace("\n\r", "\n", $fcontent);
	$fcontent = str_replace("\r", "\n", $fcontent);

	$pubcontent = $key == 'pub' ? $fcontent : '';
	$pricontent = $key == 'ppk' ? $fcontent : '';

	if (function_exists('trlnc_convert_key')) {
		$arr = trlnc_convert_key($fcontent);
		if (!empty($arr['pub'])) {
			$pubcontent = $arr['pub'];
		}
		if (!empty($arr['ppk'])) {
			$pricontent = $arr['ppk'];
		}
	}

	if (!empty($pubcontent)) {
		file_put_contents($public_file, $pubcontent);
	}
	if (!empty($pricontent)) {
		file_put_contents($private_file, $pricontent);
	}
	Tr_Session_Class::add('Saved File Successfully!');
} else if ($act == 'reimport') {

	$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$dbfile = $wpdb->get_row("select f.*,
		                        ba.assen
		                     from ci_sync_api_files f
		                      left join ci_sync_api_orders o ON o.mo_file_id = f.m_ID and o.mo_btype='{$btype}'
		                      left join {$batch_tbl} ba ON ba.mid = o.mo_mid
		                     where m_ID = {$_REQUEST['fid']}");

	if ($dbfile) {
		$file_path = $log_dir_orders . '/' . basename($dbfile->m_file);
		if (file_exists($file_path) && filesize($file_path) > 0) {
			$client->import_req_file($dbfile, $file_path);
			echo $client->log;
		} else {

			echo 'file not found';
		}
	} else {
		echo 'not found';
	}

	exit;
} else if ($act == 'rebuild' || $act == 'resend') {

	$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$dbfile = $wpdb->get_row("select f.*,
		                        ba.assen,
								re.id
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID and o.mo_btype = '{$btype}'
		                      join {$batch_tbl} ba ON ba.mid = o.mo_mid 
							  join {$report_tbl} re On re.patient_id = ba.assen
		                     where m_ID = {$_REQUEST['fid']}");

	try {
		$client->debug = true;
		if ($act == 'resend') {
			$client->connect();
			$client->login($options['results_username'], $options['results_password']);
			echo 'logged in<br>';
		}

		$client->sync_reports($btype . '_' . $dbfile->id);
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	//wp_redirect(Tr_Base_Class_V6::link(array('act' => 'viewrshl7'), false));
	exit;
} else if ($act == 'list_ids') {
	$list = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cli_clinic asc");
?>
	<table id="idstbl" class="posts wp-list-table widefat fixed hover_tbl">
		<thead>
			<tr>
				<th style="width:30px">#</th>
				<th>Clinic</th>
				<th style="width:300px">Code</th>
			</tr>
		</thead>
		<?php
		foreach ($list as $row) {
		?>
			<tr class="datarow" data-id="<?php echo $row->cID ?>">
				<td><?php echo $row->cID ?></td>
				<td><?php echo $row->cli_clinic ?></td>
				<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="cli_sftp_emr_code" value="<?php echo $row->cli_sftp_emr_code ?>" title="Enter to save" /></td>
			</tr>
		<?php
		}
		?>
	</table>
	<form method="post" enctype="multipart/form-data">
		<input type="file" name="importfile">
		<input type="hidden" name="act" value="import_codes" />
		<input type="submit" class="button" value="Import">
	</form>
<?php
	exit;
} else if ($act == 'import_codes') {
	if (!empty($_FILES['importfile']['tmp_name'])) {
		$btype = $_REQUEST['btype'];
		$data = trpdd_get_data($_FILES['importfile']['tmp_name'], 'csv', array('facility', array('code', 'facility code')), true);

		foreach ($data['data'] as $row) {

			$clinic = trpdd_get_string_exist($row, array('facility', 'name'));
			$code     = trpdd_get_string_exist($row, array('code', 'facility code'));


			$cid = $wpdb->get_var($wpdb->prepare("select cID from ci_clinic where cli_clinic = %s", $clinic));

			if ($cid == 0) {
				$clinic = str_replace('H2H of ', '', $clinic);
				$cid = $wpdb->get_var($wpdb->prepare("select cID from ci_clinic where cli_clinic = %s", $clinic));
			}
			if ($cid == 0) {
				$clinic = str_replace('Medcentris Of ', '', $clinic);
				$cid = $wpdb->get_var($wpdb->prepare("select cID from ci_clinic where cli_clinic = %s", $clinic));
			}
			if ($cid > 0) {
				$count_imported++;
				$wpdb->update('ci_clinic', array(
					'cli_sftp_emr_code' => $code,
				), array(
					'cID' => $cid
				));
			} else {
				Tr_Session_Class::add_error('Not found: ' . $clinic);
			}
		}
		Tr_Session_Class::add("Count Imported: " . $count_imported);

		Tr_Session_Class::add("Imported Successfully!");
	}
} else if ($act == 'save_ids') {
	$return = array('success' => 1, 'msg' => '');
	ob_clean();
	$id = $_REQUEST['id'];
	$col = trim($_REQUEST['col']);
	$vl = trim($_REQUEST['val']);

	$rs = $wpdb->update('ci_clinic', array($col => $vl), array('cID' => $id));

	if ($rs === false) {
		$return['success'] = 0;
		$return['msg'] = 'Error. Please try again';
	}
	wp_send_json($return);
}


if (!empty($act)) {
	include(trpdd_get_file_path('instrument', 'sftp_actions'));
}

$labs 		= $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics 	= $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$doctors 	= $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        doc.doc_npi,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);
?>

<div class="wrap">
	<h2>eClinicalWorks Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post" enctype="multipart/form-data">
		<div class="row">
			<div style="width:50%;float:left">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable']) ?> />
				</div>
				<div class="row">
					<label for="disable_ssh_rsa">Diable ssh_rsa </label>
					<input type="checkbox" id="disable_ssh_rsa" value="1" name="options[disable_ssh_rsa]" <?php checked($options['disable_ssh_rsa']) ?> />
				</div>
				<div class="row">
					<label>Lab</label>
					<select name="options[lab_id]" required>
						<?php
						foreach ($labs as $lab) {
							$selected = $lab->lab_ID == $options['lab_id'] ? 'selected' : '';
							echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
						}
						?>
					</select>
				</div>
				<div class="row">
					<label for="check_nte_clinic">&nbsp;</label>
					<input type="checkbox" id="check_nte_clinic" value="1" name="options[check_nte_clinic]" <?php checked(isset($options['check_nte_clinic']) ? $options['check_nte_clinic'] : false) ?> />
					Enable check clinic in NTE
				</div>
				<div class="row">
					<label for="checkbtyps">&nbsp;</label>
					<input type="checkbox" id="checkbtyps" value="1" name="options[alltypes]" <?php checked(isset($options['alltypes']) ? $options['alltypes'] : false) ?> />
					All Types
				</div>
				<div class="row">
					<label>Default Clinic</label>
					<select name="options[cli_id]">
						<option value="">Select</option>
						<?php
						foreach ($clinics as $clinic) {
							$selected = $clinic->cID == $options['cli_id'] ? 'selected' : '';
							echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
						}
						?>
					</select>
				</div>
				<div class="row">
					<label>Default Doctor</label>
					<select name="options[doc_id]"></select>
				</div>

				<div class="row">
					<label>Host</label>
					<input type="text" name="options[host]" value="<?php echo $options['host'] ?>" required />
				</div>

				<div class="row">
					<label>Port</label>
					<input type="number" name="options[port]" value="<?php echo $options['port'] ?>" required />
				</div>

				<div class="row">
					<label>Username</label>
					<input type="text" name="options[username]" value="<?php echo $options['username'] ?>" required />
				</div>
				<div class="row">
					<label>Password</label>
					<input type="password" name="options[password]" value="<?php echo $options['password'] ?>" />
				</div>

				<div class="row">
					<label>Order folder</label>
					<input type="text" name="options[order_folder]" value="<?php echo $options['order_folder'] ?>" required />
				</div>
				<div class="row">
					<label>Results folder</label>
					<input type="text" name="options[result_folder]" value="<?php echo $options['result_folder'] ?>" required />
				</div>

				<div class="row">
					<label>pub file</label>
					<input type="file" name="pub" />
					<input type="hidden" name="options[pub]" value="<?php echo $options['pub'] ?>" />
					<?php if (!empty($options['pub'])) {
						echo '<a class="keylink thickbox" href="' . Tr_Base_Class_V6::link(array('act' => 'viewkeys', 'key' => 'pub', 'trajax' => 1), false) . '">pub file</a>';
						echo ' - ';
						echo '<a class="removekey" href="#" data-key="pub">remove</a>';
					} ?>
				</div>
				<div class="row">
					<label>ppk file</label>
					<input type="file" name="ppk" />
					<input type="hidden" name="options[ppk]" value="<?php echo $options['ppk'] ?>" />
					<?php if (!empty($options['ppk'])) {
						echo '<a class="keylink thickbox" href="' . Tr_Base_Class_V6::link(array('act' => 'viewkeys', 'key' => 'ppk', 'trajax' => 1), false) . '">ppk file</a>';
						echo ' - ';
						echo '<a class="removekey" href="#" data-key="ppk">remove</a>';
					} ?>
				</div>

				<div class="row">
					<label>Schedule</label>
					<select name="options[cron]">
						<?php
						foreach (array(1, 2, 3, 5, 10, 30) as $m) {
							$selected = $m == $options['cron'] ? 'selected' : '';
							echo '<option value="' . $m . '" ' . $selected . '>each ' . $m . ' min(s)</option>';
						}
						?>
					</select>
				</div>
			</div>
			<div style="width:49%;float:right">
				<div class="frow">
					<label>Notes</label>
					<textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes']) ?></textarea>
				</div>
			</div>
		</div>


		<div class="frow row action_buttons">
			<input type="submit" value="Save" class="button-primary" />
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>">Test Connect</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'list_ids', 'width' => 1000)) ?>">Codes</a>

			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>">Test get Orders</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report', 'width' => 1200)) ?>">Test send Reports</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Log</a>
			<a class="button" target="_blank" href="<?php echo '/wp-content/log/log_api_sftp_emr.txt' ?>">Log File</a>

			<?php
			if (isset($_GET['debug'])) {
				echo '<a class="button thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload'), false) . '">Test Upload Result</a> ';
				echo '<a class="button thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'install'), false) . '">Install</a>';
			}
			echo '<br><br>';
			if (isset($options['alltypes']) && $options['alltypes']) {
				foreach (tr_pdd_get_batch_types() as $btype) {
					$batch_tbl = tr_pdd_get_table_by_btype($btype);
					$report_tbl = tr_pdd_get_table_report_by_btype($btype);

					$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.estatus='C',1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_sync_api_files fi
                            join ci_sync_api_orders o ON o.mo_file_id = fi.m_ID and o.mo_btype='{$btype}'
							JOIN {$batch_tbl} ba ON o.mo_mid = ba.mid
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
                            where fi.m_type='{$client->api_id}'");


					echo '<strong>' . $btype . '</strong><br>';
					echo 'Reqs: ' . $count->count_reqs . '<br>';
					echo 'Results: ' . $count->count_results . '<br>';
					echo 'Sent: ' . $count->count_sent . '<br>';
					echo '<br>';
				}
			} else {
				$btype = 'pcr';
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);
				$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.estatus='C',1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_sync_api_files fi
                            join ci_sync_api_orders o ON o.mo_file_id = fi.m_ID and o.mo_btype='{$btype}'
							JOIN {$batch_tbl} ba ON o.mo_mid = ba.mid
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
                            where fi.m_type='{$client->api_id}'");

				echo 'Reqs: ' . $count->count_reqs . '<br>';
				echo 'Results: ' . $count->count_results . '<br>';
				echo 'Sent: ' . $count->count_sent . '<br>';
			}

			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled('cron_api_sftp_emr');
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = file_exists(WP_CONTENT_DIR . '/log/log_api_sftp_emr.txt') ? filemtime(WP_CONTENT_DIR . '/log/log_api_sftp_emr.txt') : 0;
			if ($last_cron >  $next_cron - 60 * $options['cron']) {
				$last_cron = $next_cron - 60 * $options['cron'];
			}
			if ($last_cron) {
				echo 'Last Cron: ' . date('Y-m-d H:i:s', $last_cron + $current_offset) . '<br>';
			}
			if ($next_cron) {
				echo 'Next Cron: ' . date('Y-m-d H:i:s', $next_cron + $current_offset) . '<br>';
			}
			?>
		</div>
	</form>
</div>


<style>
	.editfield {
		max-width: 95%;
	}

	.checkboxes label {
		margin-right: 15px;
	}

	.action_buttons .button {
		margin-right: 5px;
		margin-left: 5px;
	}
</style>



<script>
	var $doctors = <?php echo json_encode($doctors) ?>;
	var $selected_doc = <?php echo intval($options['doc_id']) ?>;
	jQuery(function($) {
		function save_ids_data($this) {
			var $pr = $this.parent();
			var $tr = $pr.parent();
			var $vl = $this.val();
			var $col = $this.data('k');
			var $id = $tr.data('id');

			$pr.append('<div class="sloading"></div>');
			$.ajax({
				data: {
					act: 'save_ids',
					trajax: 1,
					col: $col,
					id: $id,
					val: $vl
				},
				type: 'post',
				dataType: 'json',
				success: function(rs) {
					if (rs.success) {

					} else {
						alert(rs.msg);
					}
					$('.sloading', $pr).remove();
				}
			});
		}

		$('[name="options[cli_id]"]').change(function() {
			var $cli_id = $(this).val();
			var $html = '<option value="">Select Doctor</option>';
			for (i in $doctors) {
				var $doctor = $doctors[i];
				if ($doctor.cli_ID == $cli_id) {
					var $selected = $doctor.doc_ID == $selected_doc ? 'selected' : '';
					$html += '<option value="' + $doctor.doc_ID + '" ' + $selected + '>' + $doctor.doc_first + ' ' + $doctor.doc_last + '</option>';
				}
			}
			$('[name="options[doc_id]"]').html($html);


		}).change();
		$('body').on('click', 'a.delete', function() {
			if (!confirm('Do you want delete?')) return false;
		}).on('click', 'a.show_popup', function() {
			var $url = $(this).attr('href');
			tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 500);
			return false;
		}).on('click', 'a.resend', function() {
			if ($(this).hasClass('sending')) return false;
			$(this).addClass('sending');
			var $this = $(this);
			var $pr = $(this).parents('tr');
			var $id = $pr.data('id');
			var $mid = $pr.data('mid');
			$.ajax({
				data: {
					trajax: 1,
					act: 'send_result',
					id: $id,
					mid: $mid
				},
				type: 'post',
				dataType: 'json',
				success: function($rs) {
					$this.removeClass('sending');
					if ($rs.status == 'ok') {
						alert('Sent successfully');
					} else {
						alert($rs.msg);
					}
				}
			});
			return false;
		}).on('click', '.removekey', function() {
			var $key = $(this).data('key');
			$('[name="options[' + $key + ']"]').val('');
			$(this).parent().find('.keylink').remove();
			return false;
		}).on('keypress', '#idstbl input.editfield', function(e) {
			var code = e.keyCode || e.which;
			if (code == 13) {
				save_ids_data($(this));
				return false;
			}
		}).on('change', '#idstbl input.editfield', function() {
			save_ids_data($(this));
		}).on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $haspos = $('#haspos').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + $s + '&haspos=' + $haspos);
			}, 500);
			return false;
		}).on('change', '.duplicate_ids', function() {
			var $has_duplicate = false;
			var $ice_value = $(this).val();
			var $cid = $(this).data('id');
			$('.duplicate_ids').each(function() {
				var $cid2 = $(this).data('id');
				if ($cid != $cid2 && $(this).val() == $ice_value) {
					$has_duplicate = true;
				}
			})
			if ($has_duplicate) {
				alert('Please input a unique code!');
				$(this).val('');
				$(this).focus();
			}
		})
	})
</script>