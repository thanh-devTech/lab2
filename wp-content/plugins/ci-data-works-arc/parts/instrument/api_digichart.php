<?php
global $wpdb, $current_role;
//12-10-2024

$log_dir_results 		= WP_CONTENT_DIR . '/log/digichart_results/';
$log_dir_orders 		= WP_CONTENT_DIR . '/log/digichart_orders/';

if (!file_exists($log_dir_results)) {
	wp_mkdir_p($log_dir_results);
}
if (!file_exists($log_dir_orders)) {
	wp_mkdir_p($log_dir_orders);
}

include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . 'api/ci_api_digichart.php');

$api_key = 'api_digichart';


if (isset($_POST['options'])) {
	$options = $_POST['options'];
	if (empty($options['cron'])) {
		$options['cron'] = 10;
	}
	$key_api=$define_apis[$api_key]['st'];
    $old_options = get_option($key_api, array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_'.$key_api, $bkdata);
	update_option($define_apis[$api_key]['st'], $options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option($define_apis[$api_key]['st'], array());
	//setup cron
	wp_clear_scheduled_hook($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));

	if ($options['enable']) {
		if (empty($options['cron'])) {
			$options['cron'] = 5;
		}
		wp_schedule_event(time() + 1, $options['cron'] . 'min', $define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
	}
}
$options = get_option($define_apis[$api_key]['st'], array());

if (empty($options['cron'])) {
	$options['cron'] = 10;
}


try {
	$client = new CI_Api_digichart();
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}

if ($act == 'test_connect') {
	try {
		if ($client->connect()) {
			Tr_Session_Class::add('Connected Successfully!');
		} else {
			Tr_Session_Class::add_error('Cant Connect');
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_report') {
	try {
		$client->debug = true;
		$client->connect();
		echo 'logged in<br>';
		$client->sync_reports();
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_upload') {
	try {
		$client->connect();
		echo 'connected<br>';
		$mid = isset($_GET['mid']) ? $_GET['mid'] : 0;

		$client->send_results('pcr', $mid, 1);
		echo 'Uploaded<br>';

		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'log') {

	$joins = '';
	$fields = '';
	$where = " o.mo_file_id='{$client->api_id}' ";
	if (isset($_GET['debug'])) {
		$joins .= " join ci_patients pa ON pa.pa_ID = ba.patient_id ";
		$joins .= " join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
		$fields .= ", pa.pa_first, pa.pa_last, ins.ins_first, ins.ins_last, ins.ins_ID ";
	}
	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s) ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%',
			$_REQUEST['s']
		);
	}

	if ($client->options['ref_lab_id'] > 0) {
		$where .= " and ba.ref_lab_ID = {$client->options['ref_lab_id']} ";
	}
	if (!empty($client->options['start_date'])) {
		$where .= " and re.approve_date >= '{$client->options['start_date']}' ";
	}

	$row_per_page = 200;
	if (isset($_GET['get_codes'])) {
		$row_per_page = 9999999999;
	}
	$pagination = new Tr_Pagination_class2($row_per_page);

	$rejected_status = tr_pdd_get_batch_statuses(false, true, true, 'pcr');


	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_pcr ba
									left join ci_reports_pcr re ON re.patient_id = ba.assen
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select ba.mid,
								ba.oldreq,
                                ba.assen,
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                ba.dagnostic_codes1,
                                ba.batch_status,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                cli.*,
                                o.*
                                {$fields}
                            from ci_batches_pcr ba
                            left join ci_clinic cli ON cli.cID = ba.cli_ID
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            {$joins}
                            where {$where} 
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");

	ob_start();

?>
	<div>
		<input type="search" id="assen_search" value="<?php echo $_REQUEST['s'] ?>" placeholder="Accession or patient name">
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:60px">#</th>
				<th style="width:50px">Order#</th>
				<th style="width:120px">Accession</th>
				<th>Patient</th>
				<th>Clinic</th>
				<th>Doctor</th>
				<th style="width:180px">Sent</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$codes = array();
			foreach ($rows as $row) {
				$other_info 		= !empty($row->other_info) ? json_decode($row->other_info, true) : array();
				$sent_datetime 		= implode('<br>', isset($other_info['sent']) ? $other_info['sent'] : array());
				$receved_datetime	= implode('<br>', isset($other_info['received']) ? $other_info['received'] : array());

				$class = 'childp';
				if (isset($rejected_status[$row->batch_status])) {
					$class .= ' rejected';
				}
			?>
				<tr class="<?php echo $class ?>" data-id="<?php echo $row->id ?>" data-mid="<?php echo $row->mid ?>">
					<td><?php echo $row->mid ?></td>
					<td><?php echo $row->oldreq ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid ?>&"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->patient_name ?></td>
					<td><?php echo $row->requesting_practice ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td><?php echo $sent_datetime ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewrshl7', 'mid' => $row->mid, 'moid' => $row->mo_ID), false) . '">View Hl7</a>';
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
} else if ($act == 'rebuild' || $act == 'resend') {

	$btype =  'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$dbfile = $wpdb->get_row("select o.*,
		                        ba.assen,
		                        ba.mid,
								re.id
		                     from ci_sync_api_orders o 
		                      join {$batch_tbl} ba ON ba.mid = o.mo_mid 
							  join {$report_tbl} re On re.patient_id = ba.assen
		                     where o.mo_btype = '{$btype}' and mo_ID = {$_REQUEST['moid']}");


	try {
		ob_start();
		$client->debug = true;
		if ($act == 'resend') {
			$client->connect();
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
		Tr_Session_Class::show();
		$hl7_parser = new CI_HL7_Parser();
		$row = $wpdb->get_row("select o.*,
		                        ba.assen,
								ba.mid
		                     from ci_sync_api_orders o 
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid and o.mo_btype='pcr'
		                     where o.mo_ID = {$_REQUEST['moid']}");




		$filename = ($row->assen) . '.hl7';
		//var_dump($rs_file);
		echo 'OUT file:' . $filename;
		$reimportlink = Tr_Base_Class_V6::link(array('act' => 'rebuild'), false);
		echo '   <a href="' . $reimportlink . '">rebuild</a> - ';
		if (file_exists($log_dir_results . $filename)) {

			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'resend'), false);
			echo '   <a href="' . $reimportlink . '">resend</a>';
			$hl7 = file_get_contents($log_dir_results . $filename);
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
} else if ($act == 'list_ids') {

	$list = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cast(cli_digichart_id as unsigned) asc");
	echo $wpdb->last_error;
	?>
	<table id="idstbl" class="posts wp-list-table widefat fixed hover_tbl">
		<thead>
			<tr>
				<th style="width:30px">#</th>
				<th style="width:35%">Clinic</th>
				<th>id</th>
				<th>Address</th>
				<th>City</th>
				<th>State</th>
				<th>Zip</th>
			</tr>
		</thead>
		<?php
		foreach ($list as $row) {
		?>
			<tr class="datarow" data-id="<?php echo $row->cID ?>">
				<td><?php echo $row->cID ?></td>
				<td><?php echo $row->cli_clinic ?></td>
				<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="cli_digichart_id" value="<?php echo $row->cli_digichart_id ?>" /></td>
				<td><?php echo $row->cli_street ?></td>
				<td><?php echo $row->cli_city ?></td>
				<td><?php echo $row->cli_state ?></td>
				<td><?php echo $row->cli_zip ?></td>
			</tr>
		<?php
		}
		?>
	</table>
<?php
	exit;
} else if ($act == 'save_ids') {
	$return = array('success' => 1, 'msg' => '');
	ob_clean();
	$id = $_REQUEST['id'];
	$col = trim($_REQUEST['col']);
	$vl = trim($_REQUEST['val']);

	if (stripos($col, 'tg_') !== false) {
		$rs = $wpdb->update('ci_test_groups', array($col => $vl), array('tg_ID' => $id));
	} else if (stripos($col, 'te_') !== false) {
		$rs = $wpdb->update('ci_test_panels', array($col => $vl), array('te_ID' => $id));
	} else {
		$rs = $wpdb->update('ci_clinic', array($col => $vl), array('cID' => $id));
	}

	if ($rs === false) {
		$return['success'] = 0;
		$return['msg'] = 'Error. Please try again';
	}
	wp_send_json($return);
	exit;
} else if ($act == 'testcode') {
	$btype = isset($_GET['btype']) ? $_GET['btype'] : 'pcr';

	$groups = $wpdb->get_results("select * from ci_test_groups where tg_type = '{$btype}' 
	and tg_show_req = 1  and tg_parent = 0
	order by tg_position asc");

	$morestkey = $btype . '_pdf';
?>


	<label><input type="checkbox" class="changemorest" data-key="<?php echo $morestkey ?>" value="1" <?php checked($options[$morestkey]) ?> /> PDF Attach </label>

	<table id="idstbl" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:40px">Group</th>
				<th>Test Name</th>
				<?php if ($btype == 'tox') {
					echo '<th>Urine Code</th>';
					echo '<th>Urine Send</th>';
					echo '<th>Oral Code</th>';
					echo '<th>Oral Send</th>';
				} else {
					echo '<th>Order Code</th>';
					echo '<th>Result Code</th>';
				}
				?>
			</tr>
		</thead>
		<tbody>
			<?php

			foreach ($groups as $group) {
				$rows = $wpdb->get_results("select te.*
                	from ci_test_panels te
					join ci_test_groups tg ON tg.tg_ID = te.te_group_id
                    where te.te_type = '{$btype}' 
					and (tg.tg_ID = {$group->tg_ID} or tg.tg_parent = {$group->tg_ID})
                    order by te_position asc");
				if (count($rows) == 0) continue;
			?>
				<tr data-id="<?php echo $group->tg_ID ?>">
					<td colspan="2"><b><?php echo $group->tg_name ?></b></td>
					<td><input type="text" class="editfield" data-k="tg_digichart_order" value="<?php echo $group->tg_digichart_order ?>" style="width:200px" /></td>
					<td><input type="text" class="editfield" data-k="tg_digichart_result" value="<?php echo $group->tg_digichart_result ?>" style="width:200px" /></td>
				</tr>
				<?php
				foreach ($rows as $row) {
				?>
					<tr data-id="<?php echo $row->te_ID ?>">
						<td style="width:40px"></td>
						<td><?php echo $row->te_name ?></td>
						<?php if ($btype == 'tox') { ?>
							<td><input type="text" class="editfield" data-k="te_digichart" value="<?php echo $row->te_digichart ?>" style="width:200px" /></td>
							<td><input type="checkbox" class="editfield" data-k="te_digichart_enable" value="1" <?php checked($row->te_digichart_enable) ?> /></td>
							<td><input type="text" class="editfield" data-k="te_digichart_oral" value="<?php echo $row->te_digichart_oral ?>" style="width:200px" /></td>
							<td><input type="checkbox" class="editfield" data-k="te_digichart_oral_enable" value="1" <?php checked($row->te_digichart_oral_enable) ?> /></td>
						<?php } else { ?>
							<td><input type="text" class="editfield" data-k="te_digichart_order" value="<?php echo $row->te_digichart_order ?>" style="width:200px" /></td>
							<td><input type="text" class="editfield" data-k="te_digichart_result" value="<?php echo $row->te_digichart_result ?>" style="width:200px" /></td>
						<?php } ?>
					</tr>
			<?php
				}
			}
			?>
		</tbody>
	</table>

	<form method="post" enctype="multipart/form-data">
		<br>
		<label>Import file:</label>
		<input type="file" name="importfile" />
		<input type="submit" class="button" value="Import" />
		<input type="hidden" name="act" value="import_testcode" />
		<input type="hidden" name="btype" value="<?php echo $btype ?>" />
	</form>
<?php
	exit;
} else if ($act == 'import_testcode') {
	if (!empty($_FILES['importfile']['tmp_name'])) {
		$btype = $_REQUEST['btype'];
		$data = trpdd_get_data($_FILES['importfile']['tmp_name'], 'csv', array('code', array('testname', 'test name')), true);

		foreach ($data['data'] as $row) {

			$testname = trpdd_get_string_exist($row, array('testname', 'test name'));
			$code     = trpdd_get_string_exist($row, array('code'));


			$tids = $wpdb->get_col($wpdb->prepare("select te_ID from ci_test_panels te
            where te_name = %s
            and te_type = '{$btype}' ", $testname));



			if (count($tids) > 0) {
				foreach ($tids as $tid) {
					$rsupdate = $wpdb->update(
						'ci_test_panels',
						array('te_digichart' => $code),
						array('te_ID' => $tid)
					);
					$count_imported++;
				}
			} else {
				Tr_Session_Class::add_error('Not found: ' . $testname);
			}

			if (strtoupper($testname) == 'LITHIUM') {
				var_dump($tids);
				var_dump($rsupdate);
			}
		}
		Tr_Session_Class::add("Count Imported: " . $count_imported);

		Tr_Session_Class::add("Imported Successfully!");
	}
} else if ($act == 'change_options') {
	$return = array('status' => 'ok');
	$key = $_REQUEST['okey'];
	$vl = $_REQUEST['vl'];

	if (!empty($options)) {
		$options[$key] = $vl;
		update_option('tr_hl7_sftp_digichart_st', $options);
	}

	$return['o'] = $options;

	wp_send_json($return);
}




$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$doctors = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        doc.doc_npi,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);
$labs = CI_Base_Lab::get_labs();
$reflabs = CI_Base_Lab::get_ref_labs();
?>

<div class="wrap">
	<h2>Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="frow">
			<div class="col2">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : 0) ?> />
				</div>
				<div class="row">
					<label>Lab</label>
					<select name="options[lab_id]" required class="njs_select2">
						<?php
						foreach ($labs as $lab) {
							$selected = isset($options['lab_id']) && $lab->lab_ID == $options['lab_id'] ? 'selected' : '';
							echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
						}
						?>
					</select>
				</div>
				<div class="row">
					<label>Ref Lab</label>
					<select name="options[ref_lab_id]" class="njs_select2">
						<?php
						echo '<option value=""></option>';
						foreach ($reflabs as $lab) {
							$selected = isset($options['ref_lab_id']) && $lab->lab_ID == $options['ref_lab_id'] ? 'selected' : '';
							echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
						}
						?>
					</select>
				</div>


				<div class="row">
					<label>Host</label>
					<input type="text" name="options[host]" value="<?php echo isset($options['host']) ? $options['host'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Port</label>
					<input type="text" name="options[port]" value="<?php echo isset($options['port']) ? $options['port'] : '22' ?>" required />
				</div>
				<div class="row">
					<label>Start Date</label>
					<input type="text" class="hasdate" name="options[start_date]" value="<?php echo $options['start_date'] ?>" required />
				</div>
				<div class="row">
					<label>Schedule</label>
					<select name="options[cron]">
						<?php
						foreach (array(1, 2, 5, 10, 30) as $m) {
							$selected = $m == $options['cron'] ? 'selected' : '';
							echo '<option value="' . $m . '" ' . $selected . '>each ' . $m . ' min(s)</option>';
						}
						?>
					</select>
				</div>
			</div>
			<div class="col2">
				<label>Notes</label>
				<textarea name="options[notes]" style="height:300px"><?php echo $options['notes'] ?></textarea>
			</div>
		</div>

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>">Test Connect</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>">Test Upload File</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report')) ?>">Test Sync Reports</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200">Log</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php echo '/wp-content/log/ci_api_digichart.txt' ?>">Log File</a>
		</div>

		<?php
		$today = date_i18n('Y-m-d');
		$sql = "select count(*) as count_reqs,
				SUM(IF(re.estatus = 'C',1,0)) as count_results,
				SUM(IF(o.mo_sent=1,1,0)) as count_sent
				from ci_batches_pcr ba
				left join ci_reports_pcr re On re.patient_id = ba.assen	
				left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
							and mo_file_id = {$client->api_id}
				where ba.batch_id > 0 
				";
		if ($client->options['ref_lab_id'] > 0) {
			$sql .= " and ba.ref_lab_ID = {$client->options['ref_lab_id']} ";
		}
		if (!empty($client->options['start_date'])) {
			$sql .= " and re.approve_date >= '{$client->options['start_date']}' ";
		}
		$count = $wpdb->get_row($sql);



		echo '<br>';
		echo 'Total Req: ' . $count->count_reqs . '<br>';
		echo 'Total Result: ' . $count->count_results . '<br>';
		echo 'Total Sent: ' . $count->count_sent . '<br>';



		echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

		$next_cron = wp_next_scheduled($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
		$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;


		$last_cron = file_exists(WP_CONTENT_DIR . '/log/ci_' . $api_key . '.txt') ? filemtime(WP_CONTENT_DIR . '/log/ci_' . $api_key . '.txt') : 0;
		if ($last_cron >  $next_cron - 60 * $options['cron']) {
			$last_cron = $next_cron - 60 * $options['cron'];
		}
		if ($last_cron) {
			echo 'Last Cron: ' . date('Y-m-d H:i:s', $last_cron + $current_offset) . '<br>';
		}

		if ($next_cron) {
			echo 'Next Cron: ' . date('Y-m-d H:i:s', $next_cron + $current_offset) . '<br>';
		} else if (isset($options['active']) && $options['active']) {
			if (!isset($options['cron']) || empty($options['cron'])) {
				$options['cron'] = 10;
			}
			wp_clear_scheduled_hook($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
			wp_schedule_event(time() + 100, $options['cron'] . 'min', $define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
		}
		?>
	</form>
</div>
<style>
	.editfield {
		max-width: 95%;
	}

	table tr.rejected {
		background-color: #f9d6d6;
	}
</style>

<script>
	var $doctors = <?php echo json_encode($doctors) ?>;
	var $selected_doc = <?php echo intval(isset($options['doc_id']) ? $options['doc_id'] : 0) ?>;
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
					var $docnpi = $doctor.doc_npi ? '(' + $doctor.doc_npi + ')' : '';
					$html += '<option value="' + $doctor.doc_ID + '" ' + $selected + '>' + $doctor.doc_first + ' ' + $doctor.doc_last + $docnpi + '</option>';
				}
			}
			$('[name="options[doc_id]"]').html($html);


		}).change();
		$('body').on('click', 'a.show_popup', function() {
			if ($(this).hasClass('seach_assen')) return;
			var $url = $(this).attr('href');
			if ($(this).hasClass('resend')) return;
			//tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 300);
			return false;
		}).on('click', 'a.resend', function() {
			if (!confirm('Do you want resend?')) return false;
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
					mid: $mid,
				},
				type: 'post',
				dataType: 'json',
				success: function($rs) {
					$this.removeClass('sending');
					if ($rs.status == 'ok') {

						alert($rs.msg);
					} else {
						alert($rs.msg);
					}
				}
			});
			return false;
		}).on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + $s);
			}, 500);
			return false;
		}).on('change', '#ma_ids_cb', function() {
			if ($(this).is(':checked')) {
				$('#clinic_div').hide();
				$('#doctor_div').hide();
			} else {
				$('#clinic_div').show();
				$('#doctor_div').show();
			}
		}).on('keypress', '#idstbl input.editfield', function(e) {
			var code = e.keyCode || e.which;
			if (code == 13) {
				save_ids_data($(this));
				return false;
			}
		}).on('change', '#idstbl input.editfield', function() {
			save_ids_data($(this));
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

		$('#ma_ids_cb').change();
	})
</script>
