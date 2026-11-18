<?php
global $wpdb, $current_role;

$log_dir = WP_CONTENT_DIR . '/log/experity/';
$resultlog = $log_dir;
$log_dir_orders = WP_CONTENT_DIR . '/log/experity_orders/';
include(TRCPDD_PATH . 'api/ci_api_sftp_experity.php');


$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'3' => 'Inconclusive',
	'4' => 'Inconclusive',
);

if (isset($_POST['options'])) {
	$options = $_POST['options'];
	if (empty($options['cron'])) {
		$options['cron'] = 10;
	}

	$old_options = get_option('tr_sftp_experity_st', array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_tr_sftp_experity_st', $bkdata);
	update_option('tr_sftp_experity_st', $options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_sftp_experity_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_sftp_experity');

	if ($options['enable']) {
		wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_api_sftp_experity');
	}

	//check fields
	include(TRCPDD_PATH . 'inc/install_options.php');
}
$options = get_option('tr_sftp_experity_st', array());

if (empty($options['cron'])) {
	$options['cron'] = 10;
}


if (empty($options['start_date'])) {


	$start_date = $wpdb->get_var("select min(batch_created)
                            from ci_batches_pcr ba
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='{$client->log_file_slug}'
							");
	if (!empty($start_date)) {
		$options['start_date'] = date('Y-m-d', strtotime($start_date));
	} else {
		$options['start_date']  = date('Y-m-d', time() - 86400 * 7);
	}

	update_option('tr_sftp_experity_st', $options);
}


try {
	$client = new CI_Api_sftp_experity($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}

if ($act == 'test_connect') {
	try {

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';
		if (!empty($_GET['pathdelete'])) {

			$rss = $client->delete($_GET['pathdelete']);
			$path = dirname($_GET['pathdelete']);
		}
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
				if (isset($_REQUEST['delete'])) {
					$client->delete($path . $file);
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
		echo '<a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'pathdelete' => $_REQUEST['path']), false) . '" class="show_popup">Delete</a>';
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
		echo 'logged in<br>';

		if (isset($_REQUEST['reset'])) {
			$archive_folder = $client->order_folder . '/archive/';
			$files = $client->scan_dir($archive_folder);
			var_export($files);
			foreach ($files as $file) {
				$client->movefile($archive_folder . $file, $client->order_folder . '/' . $file);
			}
		}

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
		$client->login();
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
} else if ($act == 'clean_archive') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		$client->clean_archive(30);
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_upload') {
	try {
		$file_path = $client->result_folder . '/test.txt';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		echo 'Uploading file : test.txt<br>';
		$tmp_path = tempnam('/tmp', 'test');
		file_put_contents($tmp_path, 'test content');

		$client->uploadFile($tmp_path, $file_path);
		echo 'Uploaded<br>';
		$files = $client->scan_dir($client->result_folder);
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

	$joins = '';
	$fields = '';
	$where = " fi.m_type='experity'  ";
	$where .= " and o.mo_file_id > 0 and o.mo_btype='pcr' ";
	$where .= " and ba.batch_created >= '{$options['start_date']}' ";
	if (isset($_GET['debug'])) {
		$joins .= " join ci_patients pa ON pa.pa_ID = ba.patient_id ";
		$joins .= " join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
		$fields .= ", pa.pa_first, pa.pa_last, ins.ins_first, ins.ins_last, ins.ins_ID ";
	}
	if (!empty($_REQUEST['s'])) {

		if (stripos($_REQUEST['s'], 'rejected') !== false) {
			$where .= " and (ba.batch_status like '%rejected%' or ba.batch_status like 're\_%') ";
		} else if (stripos($_REQUEST['s'], 'ref') !== false) {
			$where .= " and ba.ref_lab_ID > 0 ";
			if (stripos($_REQUEST['s'], 'mako') !== false) {
				$where .= " and ba.result_pdf like '%mako%' ";
			}
		} else {
			$where .= $wpdb->prepare(
				" and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s) ",
				$_REQUEST['s'] . '%',
				'%' . $_REQUEST['s'] . '%',
				$_REQUEST['s']
			);
		}
	}

	$row_per_page = 200;
	if (isset($_GET['get_codes'])) {
		$row_per_page = 9999999999;
	}
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_pcr ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid
	                                join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                ba.paid,
                                ba.dagnostic_codes1,
                                re.covid_pos,
                                re.id,
                                re.estatus,
								re.has_pos,
                                cli.*,
                                o.mo_status,
                                o.mo_ID,
                                o.other_info,
                                o.mo_test_ID,
                                o.mo_sent_dt,
                                fi.m_ID,
                                fi.m_time,
								fi.m_file
                                {$fields}
                            from ci_sync_api_orders o 
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            join ci_batches_pcr ba ON o.mo_mid = ba.mid
                            left join ci_clinic cli ON cli.cID = ba.cli_ID
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            {$joins}
                            where {$where} 
                            order by fi.m_ID desc, mo_ID desc
                            limit {$offset},{$row_per_page}");

	ob_start();

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
				<th>Order#</th>
				<th>Accession</th>
				<th>Patient</th>
				<th>Clinic</th>
				<th>ID</th>
				<th>Doctor</th>
				<th style="width:60px">Resulted</th>
				<th style="width:70px">Status</th>
				<th style="width:50px;">Sent</th>
				<th>Date/time</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$codes = array();
			foreach ($rows as $row) {

				if ($row->estatus != 'C') {
					//$wpdb->update('ci_batches_pcr', array('batch_status' => ''), array('mid' => $row->mid));
				}

				$other_info = json_decode($row->other_info, true);
				$code_ordered = $other_info['MSH4'];
				$sent_time = $row->mo_sent_dt;

				$styles = '';
				if ($code_ordered != $row->cli_experity_id) {
					$styles .= 'style="color:red"';
					if (!in_array($code_ordered, $codes)) {
						$codes[] = $code_ordered;
					}
				}


				if (isset($_GET['fixcodes'])) {
					$local_file = $log_dir_orders . basename($row->m_file);
					$datafile = file_get_contents($local_file);
					preg_match_all("/DG1\|[1-9]+\|\|([^\^]+)\^/", $datafile, $matches);
					if (is_array($matches[1]) && count($matches[1]) > 0) {
						$codes_str = implode(',', $matches[1]);
						if (!empty($codes_str)) {
							$wpdb->update('ci_batches_pcr', array('dagnostic_codes1' => $codes_str), array('mid' => $row->mid));
						}
					}

					if (isset($_GET['get_codes'])) {
						continue;
					}
				}

				if ($row->mo_status == 1 && $row->has_pos && isset($_GET['resendwrongobx8'])) {
					$logfile = $log_dir . '/' . basename($row->m_file);
					$datafile = file_get_contents($logfile);
					if (
						stripos($datafile, '|low|') !== false
						|| stripos($datafile, '|medium|') !== false
						||  stripos($datafile, '|high|') !== false
						||  stripos($datafile, '|Very Low|') !== false
						||  stripos($datafile, '|Very High|') !== false
					) {
						$wpdb->update('ci_sync_api_orders', array('mo_status' => 2), array('mo_ID' => $row->mo_ID));
						echo $row->assen . '<br>';
					}
				}


				if (empty($row->paid) && $row->paid != $client->api_id) {
					$wpdb->update('ci_batches_pcr', array('paid' => $client->api_id), array('mid' => $row->mid));
				}
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>" data-mid="<?php echo $row->mid ?>">
					<td><?php echo $row->mid ?></td>
					<td><?php echo $row->mo_test_ID ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid ?>&"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->patient_name ?></td>
					<td><?php echo $row->requesting_practice ?></td>
					<td <?php echo $styles ?>><?php echo $other_info['MSH4'] ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td><a href="?page=pcr_results&act=edit&id=<?php echo $row->id;
																if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
							<?php echo $row->estatus == 'C' ? 'Yes' : (isset($_GET['debug']) ? '.' : '') ?>
						</a>
					</td>
					<td>
						<?php
						if ($row->has_pos) {
							echo 'A';
						}
						if (!is_null($row->covid_pos)) {
							echo $covid_status[$row->covid_pos];
						}
						?>
					</td>
					<td title="<?php echo $sent_time ?>"><a class='resend' href="#"><?php echo $row->mo_status == 1 ? 'Yes' : '' ?></a></td>
					<td><?php echo date('Y-m-d H:i:s', $row->m_time) ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->m_ID, 'moid' => $row->mo_ID), false) . '">View Hl7</a>';
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
} else if ($act == 'send_result') {
	$return = array('status' => 'ok', 'msg' => '');

	try {
		$id = $_REQUEST['id'];
		$mid = $_REQUEST['mid'];
		$client->debug = true;
		$client->connect();
		if ($client->login()) {
			$wpdb->query("update ci_sync_api_orders set mo_status = 0 
					where mo_mid = {$mid} and mo_btype = 'pcr' ");
			$rs = $client->sync_reports($id);
			if (!$rs) {
				$return['status'] = 'error';
			} else {
				$files = $client->scan_dir($client->result_folder);
				$client->log .= "\n" . 'List files in Results folder:' . "\n";
				foreach ($files as $file) {
					$client->log .= $file . "\n";
				}
			}
			$return['msg'] = '' . $client->log;
		} else {
			$return['status'] = 'error';
			$return['msg'] = 'error login';
		}
	} catch (Exception $e) {
		$return['status'] = 'error';
		$return['msg'] = $e->getMessage();
	}
	wp_send_json($return);
	exit;
} else if ($act == 'rebuild' || $act == 'resend') {

	$fid = $_REQUEST['fid'];
	$moid = $_REQUEST['moid'];
	$btype =  'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$dbfile = $wpdb->get_row("select f.*,
		                        ba.assen,
								re.id
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID and o.mo_btype = '{$btype}'
		                      join {$batch_tbl} ba ON ba.mid = o.mo_mid 
							  join {$report_tbl} re On re.patient_id = ba.assen
		                     where m_ID = {$fid} and mo_ID = {$moid} ");

	try {
		ob_start();
		$client->debug = true;
		$client->connect();
		$client->login();

		$client->sync_reports($dbfile->id);
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
		$fid = $_REQUEST['fid'];
		$moid = $_REQUEST['moid'];
		$row = $wpdb->get_row("select f.*,o.mo_test_ID,
		                        ba.assen
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
		                     where m_ID = {$fid} 
							 	and mo_ID = {$moid}");

		$local_file = $log_dir_orders . basename($row->m_file);
		if (file_exists($local_file)) {
			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
			echo 'order file.:' . $local_file;
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

			$file_path = $client->order_folder . '/archive/' . basename($row->m_file);
			$client->connect();
			if ($client->login()) {
				$tmp_path = tempnam('/tmp', 'test');
				try {
					$client->receiveFile($file_path, $tmp_path);
				} catch (Exception $e) {
				}
				echo 'order file';
				$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
				echo 'order file.:' . $local_file;
				$hl7 = file_get_contents($tmp_path);
				echo '<textarea style="width:2000px;height:300px">';
				echo esc_textarea($hl7);
				echo '</textarea>';
				if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
					$parsed = $hl7_parser->parse_hl7($hl7);
					if (!empty($parsed) && !isset($parsed['ERROR'])) {
						echo $hl7_parser->render_html($parsed);
					}
				}
				unlink($tmp_path);
			} else {
				echo 'login fail for order';
			}
		}

		$rs_file = basename($row->m_file);
		if (isset($options['splitbypanel']) && $options['splitbypanel']) {
			$arrksplit 		= explode('_', $row->mo_test_ID);
			if (isset($arrksplit[1]) && $arrksplit[1] > 0) {
				$rs_file = str_replace('.', '_' . $arrksplit[1] . '.', $rs_file);
			}
		}
		echo 'output file:' . $rs_file;
		if (file_exists($log_dir . $rs_file)) {
			$outcontent = file_get_contents($log_dir . $rs_file);

			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'rebuild'), false);
			echo '   <a href="' . $reimportlink . '">rebuild</a> - ';
			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'resend'), false);
			echo '   <a href="' . $reimportlink . '">resend</a>';

			if (stripos($outcontent, 'Base64') !== false) {
				$pdf_link = Tr_Base_Class_V6::link(array('act' => 'viewpdf', 'file' => $rs_file), false);
				echo '   <a href="' . $pdf_link . '">view pdf</a>';
			}
			echo '<textarea style="width:2000px;height:300px">';
			echo esc_textarea($outcontent);
			echo '</textarea>';
			if (!empty($outcontent) && strpos(ltrim($outcontent), 'MSH|') === 0) {
				$parsed = $hl7_parser->parse_hl7($outcontent);
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

	$options['useid2'] = isset($options['useid2']) && $options['useid2'];

	$list = $wpdb->get_results("select cli.*, mt.meta_value as cli_experity_pdf_meta
	from ci_clinic cli
	left join ci_clinic_meta mt ON mt.cID = cli.cID and mt.meta_key='cli_experity_pdf_meta'
	where cli_status = 1 order by cast(cli_experity_id as unsigned) asc");
	?>
	<table id="idstbl" class="posts wp-list-table widefat fixed hover_tbl">
		<thead>
			<tr>
				<th style="width:30px">#</th>
				<th style="width:35%">Clinic</th>
				<th>ID</th>
				<?php if ($options['useid2']) { ?>
					<th>ID 2</th>
				<?php } ?>
				<th>PDF</th>
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
				<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="cli_experity_id" value="<?php echo $row->cli_experity_id ?>" /></td>
				<?php if ($options['useid2']) { ?>
					<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="cli_experity_id2" value="<?php echo $row->cli_experity_id2 ?>" /></td>
				<?php } ?>
				<td><input type="checkbox" class="editfield" data-id="<?php echo $row->cID ?>" data-k="cli_experity_pdf_meta" value="1" <?php echo checked($row->cli_experity_pdf_meta) ?> /></td>
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
} else if ($act == 'reimport') {

	$dbfile = $wpdb->get_row("select f.*,
		                        ba.assen
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
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
}
if (!empty($act)) {
	include(trpdd_get_file_path('instrument', 'sftp_actions'));
}

//var_dump(file_get_contents('https://api.webhue.net/raw.php'));

$client_ip = file_get_contents('https://lis-demo.com/lab_api/get_ip');
$client_ip = json_decode($client_ip, true);

if (is_array($client_ip) && isset($client_ip['ip'])) {
	$client_ip = $client_ip['ip'];
} else {
	$client_ip = $_SERVER['SERVER_ADDR'];
}

$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$doctors = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        doc.doc_npi,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);


?>

<div class="wrap">
	<h2>Experity sFtp Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="row">
			<label for="for_enable">Enable </label>
			<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : 0) ?> />
		</div>
		<?php /*
		<div class="row">
			<label for="splitbypanel">Split by panels </label>
			<input type="checkbox" id="splitbypanel" value="1" name="options[splitbypanel]" <?php checked(isset($options['splitbypanel']) ? $options['splitbypanel'] : 0) ?> />
		</div>
		*/ ?>
		<div class="row">
			<label for="ntepanel">send NTE by panel </label>
			<input type="checkbox" id="ntepanel" value="1" name="options[ntepanel]" <?php checked(isset($options['ntepanel']) ? $options['ntepanel'] : 0) ?> />
		</div>
		<div class="row">
			<label for="obx8hlow">Send PDFs </label>
			<input type="checkbox" id="obx8hlow" value="1" name="options[send_pdf]" <?php checked(isset($options['send_pdf']) ? $options['send_pdf'] : 0) ?> />
		</div>
		<div class="row">
			<label for="useid2">Use ID 2 </label>
			<input type="checkbox" id="useid2" value="1" name="options[useid2]" <?php checked(isset($options['useid2']) ? $options['useid2'] : 0) ?> />
		</div>
		<div class="row">
			<label for="obx8hlow">Send Low/High in obx8 </label>
			<input type="checkbox" id="obx8hlow" value="1" name="options[obx8hlow]" <?php checked(isset($options['obx8hlow']) ? $options['obx8hlow'] : 0) ?> />
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
			<label>Default Clinic</label>
			<select name="options[cli_id]" class="njs_select2">
				<option value="">Select</option>
				<?php
				foreach ($clinics as $clinic) {
					$selected = isset($options['cli_id']) && $clinic->cID == $options['cli_id'] ? 'selected' : '';
					echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
				}
				?>
			</select>
		</div>
		<div class="row">
			<label>Default Doctor</label>
			<select name="options[doc_id]" class="njs_select2"></select>
		</div>

		<div class="row">
			<label>Client IP</label>
			<input type="text" value="<?php echo $client_ip ?>" readonly />
		</div>
		<div class="row">
			<label>Host</label>
			<input type="text" name="options[host]" value="<?php echo isset($options['host']) ? $options['host'] : '' ?>" required />
		</div>
		<div class="row">
			<label>Username</label>
			<input type="text" name="options[username]" value="<?php echo isset($options['username']) ? $options['username'] : '' ?>" required />
		</div>
		<div class="row">
			<label>Password</label>
			<input type="password" name="options[password]" value="<?php echo isset($options['password']) ? $options['password'] : '' ?>" required />
		</div>
		<div class="row">
			<label>OBX.23</label>
			<input type="text" name="options[obx23]" value="<?php echo isset($options['obx23']) ? $options['obx23'] : '' ?>" />
		</div>
		<div class="row">
			<label>NTE Text</label>
			<input type="text" name="options[nte_text]" value="<?php echo isset($options['nte_text']) ? $options['nte_text'] : '' ?>" />
		</div>
		<div class="row">
			<label>Orders Folder</label>
			<input type="text" name="options[orders_folder]" value="<?php echo isset($options['orders_folder']) ? $options['orders_folder'] : '' ?>" required />
		</div>
		<div class="row">
			<label>Results Folder</label>
			<input type="text" name="options[results_folder]" value="<?php echo isset($options['results_folder']) ? $options['results_folder'] : '' ?>" required />
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

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>">Test Connect</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>">Test Upload Result</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>">Test get Orders</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report')) ?>">Test send Reports</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'clean_archive')) ?>">Clean Archive</a> &nbsp;&nbsp;
			<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_ids', 'trajax' => 1)) ?>&width=1000">Clinic IDs</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200">Log</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php echo '/wp-content/log/log_api_sftp_experity.txt' ?>">Log File</a>
		</div>

		<?php
		$today = date_i18n('Y-m-d');
		if (isset($_GET['today'])) {
			$today = $_GET['today'];
		}
		$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent,
                            SUM(IF(o.mo_status=2,1,0)) as count_resend
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='{$client->log_file_slug}'
							and ba.batch_created>='{$options['start_date']}' 
                            ");

		if (isset($_GET['debug'])) {
			var_dump($wpdb->last_query);
			echo '<br><br>';
		}
		$reqs_today = $wpdb->get_var("select count(*) as count_reqs
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='{$client->log_file_slug}'
                            and ba.batch_created>='{$today}' 
                            and ba.batch_created <= '{$today} 23:59:59'
                            ");

		if (isset($_GET['debug'])) {
			var_dump($wpdb->last_query);
			echo '<br><br>';
		}

		$count_today = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='{$client->log_file_slug}' 
                            and re.approve_date >='{$today}' 
                            and re.approve_date  <= '{$today} 23:59:59'
                            ");

		if (isset($_GET['debug'])) {
			var_dump($wpdb->last_query);
			echo '<br><br>';
		}

		echo '<br>';
		echo 'Total Reqs: ' . $count->count_reqs . '<br>';
		echo 'Total Results: ' . $count->count_results . '<br>';
		echo 'Total Sent: ' . $count->count_sent . '<br>';
		echo 'Need Resend: ' . $count->count_resend . '<br>';

		echo 'Today Reqs: ' . $reqs_today . '<br>';
		echo 'Today Results: ' . $count_today->count_results . '<br>';
		echo 'Today Sent: ' . $count_today->count_sent . '<br>';

		if ($count->count_sent < $count->count_results) {
			$limit_approvedate = date('Y-m-d', time() - 86400 * 7);
			$missing = $wpdb->get_col("select ba.assen
                            from ci_batches_pcr ba
                            join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
                            jOIN ci_clinic cli ON cli.cID = ba.cli_ID
                            where fi.m_type='{$client->log_file_slug}' 
                            and re.id is not null 
							and re.approve_date > '{$limit_approvedate}'
                            and o.mo_status= 0 ");
			echo 'Left send: ' . implode(', ', $missing) . '<br>';
		}
		echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

		$next_cron = wp_next_scheduled('cron_api_sftp_experity');
		$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;


		$last_cron = file_exists(WP_CONTENT_DIR . '/log/log_api_sftp_experity.txt') ? filemtime(WP_CONTENT_DIR . '/log/log_api_sftp_experity.txt') : 0;
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
			wp_clear_scheduled_hook('cron_api_sftp_experity');
			wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_api_sftp_experity');
		}
		?>
	</form>
</div>
<style>
	.editfield {
		max-width: 95%;
	}
</style>

<script>
	var $doctors = <?php echo json_encode($doctors) ?>;
	var $selected_doc = <?php echo intval(isset($options['doc_id']) ? $options['doc_id'] : 0) ?>;
	jQuery(function($) {
		function save_ids_data($this) {
			var $pr = $this.parent();
			var $tr = $pr.parent();
			var $vl = $this.attr('type') == 'checkbox' ? ($this.is(':checked') ? 1 : 0) : $this.val();
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