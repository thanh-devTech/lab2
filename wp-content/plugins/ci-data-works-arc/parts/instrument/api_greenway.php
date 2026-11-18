<?php
global $wpdb, $current_role;

$log_dir 	= WP_CONTENT_DIR . '/log/greenway/';
$resultlog 	= WP_CONTENT_DIR . '/log/greenway_results/';


$dir = dirname(ABSPATH) . '/greenway_sftp/';
$order_dir = $dir . 'orders/';
$order_log_dir = $dir . 'archive/';
$result_dir = $dir . 'results/';

include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . 'api/ci_api_greenway.php');

$api_key = 'api_greenway';
$log_file = WP_CONTENT_DIR . '/log/ci_' . $api_key . '.txt';

$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'4' => 'Inconclusive'
);

if (isset($_POST['options'])) {

	$options = $_POST['options'];
	$options['enable'] = !empty($options['enable']) ? 1 : 0;
	$key_api = $define_apis[$api_key]['st'];
	$old_options = get_option($key_api, array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_' . $key_api, $bkdata);

	update_option($define_apis[$api_key]['st'], $_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option($define_apis[$api_key]['st'], array());


	//setup cron
	wp_clear_scheduled_hook($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));

	if ($options['enable']) {
		if (empty($options['cron'])) {
			$options['cron'] = 5;
		}
		wp_schedule_event(time() + 100, $options['cron'] . 'min', $define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
	}
}
$options = array_merge(array(
	'enable' => 0,
	'cron' => 5,
	'lab_id' => 0,
	'cli_id' => '',
	'doc_id' => '',
	'host' => '',
	'port' => '',
	'username' => '',
	'password' => '',
	'order_folder' => '',
	'result_folder' => '',
	'notes' => '',
), get_option($define_apis[$api_key]['st'], array()));




try {
	$client = new CI_Api_Greenway($options);
	$client->connect();
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}


if ($act == 'test_connect') {
	try {

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$files = $client->scan_dir($path);
		if ($path != '/') {
			$path .= '/';
		}

		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($path)), false) . '" class="show_popup button">Up</a> ';
		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($path)), false) . '" class="show_popup button">Reload</a>';
		echo '<br>';
		echo '<br>List files: ' . $path . '<br>';
		foreach ($files as $file) {
			if (strpos($file, '.') > 0) {
				if (stripos($path, 'archive') !== false) {
					//$client->delete($path.$file);
				}
				echo '<li><a href="' . Tr_Base_Class_V6::link(array('act' => 'readfile', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a></li>';
			} else {
				echo '<li><a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a></li>';
			}
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'readfile') {
	try {
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$tmp_path = tempnam('/tmp', 'test');
		$client->receiveFile($_REQUEST['path'], $tmp_path);
		echo '<a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($_REQUEST['path'])), false) . '" class="show_popup">Up</a>';
		echo '<br>';
		echo 'reading file ' . $_REQUEST['path'] . '<br><br>';
		echo '<textarea style="width:100%;height:400px">';
		echo file_get_contents($tmp_path);
		echo '</textarea>';
		unlink($tmp_path);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync') {
	try {

		$client->debug = true;
		$client->connect();
		$client->login();
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
		$assen = isset($_REQUEST['assen']) ? $_REQUEST['assen'] : '';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		$client->sync_reports($assen);
		echo str_replace("\n", '<br>', $client->log);
		$files = $client->scan_dir($options['results_folder']);
		echo '<br>List files in Results folder:<br>';
		foreach ($files as $file) {
			echo '<li>' . $file . '</li>';
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_upload') {
	try {
		$file_path = $options['results_folder'] . '/test.txt';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		echo 'Uploading file : test.txt<br>';
		$tmp_path = tempnam('/tmp', 'test');
		file_put_contents($tmp_path, 'test content');

		$client->uploadFile($tmp_path, $file_path);
		echo 'Uploaded<br>';
		$files = $client->scan_dir($options['results_folder']);
		echo '<br>List files in Results folder:<br>';
		foreach ($files as $file) {
			echo '<li>' . $file . '</li>';
		}
		echo 'Reading file:<br><br>';
		$client->receiveFile($file_path, $tmp_path);
		echo file_get_contents($tmp_path);
		echo '<br>';
		echo '<br>Deleting file:' . $file_path;
		$client->delete($file_path);
		echo '=>deleted';


		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'log') {

	$btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$where = " fi.m_type='{$client->log_file_slug}'  ";
	if (isset($_GET['debug'])) {
		$joins .= " join ci_patients pa ON pa.pa_ID = ba.patient_id ";
		$joins .= " join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
		$fields .= ", pa.pa_first, pa.pa_last, ins.ins_first, ins.ins_last, ins.ins_ID ";
	}
	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s or o.mo_patient_ID= %s) ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%',
			$_REQUEST['s'],
			$_REQUEST['s']
		);
	}

	if (isset($client->options['ref_lab_id']) && $client->options['ref_lab_id'] > 0) {
		$where .= " and ba.ref_lab_ID = {$client->options['ref_lab_id']} ";
	}
	if (isset($client->options['start_date']) && !empty($client->options['start_date'])) {
		$where .= " and re.approve_date >= '{$client->options['start_date']}' ";
	}


	$row_per_page = 200;
	if (isset($_GET['get_codes'])) {
		$row_per_page = 9999999999;
	}
	$pagination = new Tr_Pagination_class2($row_per_page);

	$rejected_status = tr_pdd_get_batch_statuses(false, true, true, 'pcr');


	$pagination->setTotal($wpdb->get_var("select count(*) as c from {$batch_tbl} ba
									left join {$report_tbl} re ON re.patient_id = ba.assen
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}'
									join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
	                                where {$where} "));
	$offset = $pagination->offset();



	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                ba.spec_received,
                                ba.batch_created,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                o.mo_status,
                                o.mo_patient_ID,
                                o.mo_sent_dt,
                                fi.m_ID,
                                fi.m_file,
                                fi.m_time
                            from {$batch_tbl} ba
                            left join {$report_tbl} re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");
?>
	<div>
		<input type="search" id="assen_search" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" placeholder="Accession or patient name">
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:60px">#</th>
				<th style="width:100px">Accession</th>
				<th>PID</th>
				<th>Patient</th>
				<th>Clinic</th>
				<th>Doctor</th>
				<th style="width:60px">Resulted</th>
				<th style="width:70px">Status</th>
				<th style="width:50px;">Sent</th>
				<th>Received</th>
				<th>DOC</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {

				if (isset($_GET['fix_gender'])) {
					$file_path = $log_dir . '/' . basename($row->m_file);
					$client->fix_import($file_path, 'gender', $row->mid, $btype);
				}
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
					<td><?php echo $row->mid ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $btype . '_' . $row->mid ?>"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->mo_patient_ID ?></td>
					<td><?php echo $row->patient_name ?></td>
					<td><?php echo $row->requesting_practice ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td><a href="?page=<?php echo 'ci_results_' . $btype ?>&act=edit&id=<?php echo $row->id;
																						if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
							<?php echo $row->estatus == 'C' ? 'Yes' : (isset($_GET['debug']) ? '.' : '') ?>
						</a>
					</td>
					<td>
						<?php
						if (!is_null($row->covid_pos)) {
							echo $covid_status[$row->covid_pos];
						}
						?>
					</td>
					<td title="<?php echo $row->mo_sent_dt ?>"><a class="resend" href="#"><?php echo $row->mo_status == 1 ? 'Yes' : '' ?></a></td>
					<td><?php echo $row->batch_created ?></td>
					<td><?php echo $row->doc ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V4::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->m_ID, 'btype' => $btype), false) . '">View Hl7</a>';
						?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>

	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<?php

	if (isset($_GET['get_codes'])) {
		echo implode(', ', $codes);
	}
	$content = ob_get_clean();
	if (isset($_GET['trajax'])) {
		$content = str_replace('class="', 'class="show_popup ', $content);
		$content = str_replace('class=\'', 'class=\'show_popup ', $content);
	}
	echo $content;
	exit;
} else if ($act == 'reimport') {

	$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_ID = {$_REQUEST['fid']}");
	echo $wpdb->last_error;

	if ($dbfile) {
		$file_path = $log_dir . '/' . basename($dbfile->m_file);
		var_dump($file_path);
		if (file_exists($file_path) && filesize($file_path) > 0) {
			$client->import_req_file($dbfile, $file_path);
			echo nl2br($client->log);
		} else {
			echo 'file not found';
		}
	} else {
		echo 'not found';
	}

	exit;
} else if ($act == 'rebuild' || $act == 'resend') {

	$btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$dbfile = $wpdb->get_row("select o.*,
		                        ba.assen,
		                        ba.mid,
								re.id
		                     from ci_sync_api_orders o 
                            	join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
		                      join {$batch_tbl} ba ON ba.mid = o.mo_mid 
							  join {$report_tbl} re On re.patient_id = ba.assen
		                     where o.mo_btype = '{$btype}' and m_ID = {$_REQUEST['fid']}");



	try {

		ob_start();
		$client->debug = true;
		if ($act == 'resend') {
			$client->connect();
			$client->login();
		}

		$client->rebuild($btype, $dbfile->mid);
		echo  str_replace("\n", '<br>', $client->log);
		$log = ob_get_clean();
		Tr_Session_Class::add($log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	wp_redirect(Tr_Base_Class_V6::link(array('act' => 'viewrshl7'), false));
	exit;
} else if ($act == 'viewrshl7') {
	try {
		$hl7_parser = new CI_HL7_Parser();

		$btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
		$row = $wpdb->get_row("select * from ci_sync_api_files where m_ID = {$_REQUEST['fid']}");


		$filename = basename($row->m_file);
		$local_file = $log_dir . $filename;
		if (!empty($filename) && file_exists($local_file)) {
			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
			echo 'In file.:' . $local_file;
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
		}



		if (file_exists($resultlog . basename($row->m_file))) {
			echo 'output file:';
			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'rebuild'), false);
			echo '   <a href="' . $reimportlink . '">rebuild</a> - ';

			$pdf_link 	 = Tr_Base_Class_V6::link(array('act' => 'viewpdf'), false);
			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'resend'), false);
			echo '   <a href="' . $reimportlink . '">resend</a>';
			echo ' -  <a href="' . $pdf_link . '" target=_blank>view pdf</a>';
			$hl7 = file_get_contents($resultlog . basename($row->m_file));
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
	$row = $wpdb->get_row("select * from ci_sync_api_files where m_ID = {$_REQUEST['fid']}");
	$rs_file = basename($row->m_file);
	//var_dump($rs_file);
	if (file_exists($resultlog . $rs_file)) {
		$hl7 = file_get_contents($resultlog . $rs_file);
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
	}

	exit;
}

if (!empty($act)) {
	include(trpdd_get_file_path('instrument', 'sftp_actions'));
}

$labs = $wpdb->get_results("select * from ci_labs where lab_is_ref=0 order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$docs = $wpdb->get_results("select * from ci_doc where doc_first!='' order by doc_first asc");
?>

<div class="wrap">
	<h2>Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div style="width:50%;float:left">
			<div class="row">
				<label for="for_enable">Enable </label>
				<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable']) ?> />
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
				<label>Clinic</label>
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
				<label>Doctor</label>
				<select name="options[doc_id]">
					<option value="">Select</option>
					<?php
					foreach ($docs as $doc) {
						$selected = $doc->doc_ID == $options['doc_id'] ? 'selected' : '';
						echo '<option value="' . $doc->doc_ID . '" ' . $selected . '>' . $doc->doc_first . ' ' . $doc->doc_last . ' - ' . $doc->doc_npi . '</option>';
					}
					?>
				</select>
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
				<input type="password" name="options[password]" value="<?php echo $options['password'] ?>" required />
			</div>

			<div class="row">
				<label>Order Folder</label>
				<input type="text" name="options[orders_folder]" value="<?php echo $options['orders_folder'] ?>" />
			</div>
			<div class="row">
				<label>Results Folder</label>
				<input type="text" name="options[results_folder]" value="<?php echo $options['results_folder'] ?>" />
			</div>
			<div class="row">
				<label>Start Date</label>
				<input type="text" class="hasdate" name="options[start_date]" value="<?php echo $options['start_date'] ?>" required />
			</div>
		</div>
		<div style="width:50%;float:right">
			<div class="frow">
				<label>Notes</label>
				<textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes']) ?></textarea>
			</div>
		</div>

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>">Test Connect</a> &nbsp;&nbsp;

			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>&width=1000">Test Sync Orders</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report')) ?>&width=1000">Test Sync Reports</a> &nbsp;&nbsp;

			<a class="button" target="_blank" href="/wp-content/log/<?php echo 'ci_' . $api_key ?>.txt">Log File</a> &nbsp;&nbsp;

			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>">Test Upload Result</a>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install')); ?>&width=1000">Install</a> &nbsp;&nbsp;
				<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'demofile')); ?>">Demo file</a> &nbsp;&nbsp;
			<?php } ?>

			<br><br>
			<?php

			echo '<table style="min-width:300px">';
			echo '<tr><th>Type</th><th>Reqs</th><th>Results</th><th>Sent</th><th></th></tr>';
			foreach ($client->all_btypes as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);

				$sql_count = " select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from {$batch_tbl} ba
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID and fi.m_type='{$client->log_file_slug}'
                            where 1 = 1 ";
				if (!empty($options['start_date'])) {
					$sql_count .= " and ba.doc >= '{$options['start_date']}' ";
				}

				$count = $wpdb->get_row($sql_count);

				if ($count->count_sent > $count->count_results) {
					$count->count_results = $count->count_sent;
				}
				echo '<tr><th>' . $btype . '</th><td class="center">' . $count->count_reqs . '</td>';
				echo '<td class="center">' . $count->count_results . '</td>';
				echo '<td class="center">' . $count->count_sent . '</td>';
				echo '<td><a class="thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'btype' => $btype, 'debug' => isset($_GET['debug']) ? 1 : '', 'width' => 1000), false) . '">Log</a>';
				echo '</tr>';
			}
			echo '</table>';



			echo '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = file_exists($log_file) ? filemtime($log_file) : 0;
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

<script>
	jQuery(function($) {
		$('body').on('click', 'a.show_popup', function() {
			if ($(this).hasClass('seach_assen')) return;
			var $url = $(this).attr('href');
			if ($(this).hasClass('resend')) return;
			//tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 300);
			return false;
		}).on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + encodeURIComponent($s));
			}, 500);
			return false;
		})
	})
</script>