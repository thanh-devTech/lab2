<?php

if ($act == 'test_connect') {
	try {

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';

		$client->connect();
		$client->login();

		if (!empty($_GET['pathdelete'])) {

			try {
				$pathdelete = $_GET['pathdelete'];


				if (strpos($pathdelete, '.') > 0) {
					$rss = $client->delete($pathdelete);
				} else {

					if (stripos($pathdelete, 'archive') !== false) {
						$files = $client->scan_dir($pathdelete);
						foreach ($files as $file) {
							$filepath2 = $pathdelete . '/' . $file;
							$rss = $client->delete($filepath2);
						}
					}

					$rss = $client->removeDir($pathdelete);
				}

				$path = dirname($_GET['pathdelete']);
			} catch (Exception $e1) {
				echo $e1->getMessage();
			}
		}

		$files = $client->scan_dir($path);
		if ($path != '/') {
			$path .= '/';
		}

		$path = rtrim($path, '/');
		if (!isset($options['orders_folder']) && isset($options['order_folder'])) {
			$options['orders_folder'] = $options['order_folder'];
		}
		if (!isset($options['results_folder']) && isset($options['result_folder'])) {
			$options['results_folder'] = $options['result_folder'];
		}

		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($path)), false) . '" class="show_popup button">Up</a> ';
		echo ' - <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($path)), false) . '" class="show_popup button">Reload</a>';
		if (isset($options['out_folder']) && !empty($options['out_folder'])) {
			echo ' - <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($options['out_folder'])), false) . '" class="show_popup button">Out-folder</a>';
		}
		if (isset($options['in_folder']) && !empty($options['in_folder'])) {
			echo ' - <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($options['in_folder'])), false) . '" class="show_popup button">In-folder</a>';
		}
		if (isset($options['orders_folder']) && !empty($options['orders_folder'])) {
			echo ' - <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($options['orders_folder'])), false) . '" class="show_popup button">Orders-folder</a>';
		}
		if (isset($options['results_folder']) && !empty($options['results_folder'])) {
			echo ' - <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($options['results_folder'])), false) . '" class="show_popup button">Reports-folder</a>';
		}
		echo '<br>';
		echo '<br>Path: ' . $path . '<br>';
		if (empty($files)) {
			echo '<p class="center">Not found</p>';
		}
		echo '<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">';
		foreach ($files as $file) {
			echo '<tr>';
			$filepath = $path . '/' . $file;
			if (strpos($file, '.') > 0) {
				$filetime = $client->getFileTime($filepath);

				echo '<td><a href="' . Tr_Base_Class_V6::link(array('act' => 'readfile', 'path' => $filepath), false) . '" class="show_popup">' . $file . '</a></td>';
				echo '<td>' . date('Y-m-d H:i:s', $filetime) . '</td>';
				echo '<td><a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => $path, 'pathdelete' => $filepath), false) . '" class="show_popup delete">delete</a></td>';
			} else {
				echo '<td><a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => $path  . '/' . $file), false) . '" class="show_popup">' . $file . '</a></td>';
				echo '<td></td>';
				if (isset($_GET['debug'])) {
					echo '<td><a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => $path, 'pathdelete' => $filepath), false) . '" class="show_popup delete">delete</a></td>';
				}
			}


			echo '</tr>';
		}
		echo '</table>';
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
		echo ' - <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'pathdelete' => $_REQUEST['path']), false) . '" class="show_popup">Delete</a>';
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
} else if ($act == 'test_sync_orders') {
	try {

		$client->debug = true;
		if (method_exists($client, 'connect')) {
			$client->connect();
			$client->login();
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
} else if ($act == 'test_send_reports') {
	try {
		$assen = isset($_REQUEST['assen']) ? $_REQUEST['assen'] : '';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		foreach ($client->all_btypes as $btype) {
			$client->send_results($btype);
		}
		echo str_replace("\n", '<br>', $client->log);
		$files = $client->scan_dir($options['out_folder']);
		echo '<br>List files in Results folder:<br>';
		foreach ($files as $file) {
			echo '<li>' . $file . '</li>';
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_receive_reports') {
	try {
		$assen = isset($_REQUEST['assen']) ? $_REQUEST['assen'] : '';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		$client->receive_results();
		echo str_replace("\n", '<br>', $client->log);
		$files = $client->scan_dir($options['in_folder']);
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
		$result_folder = isset($options['results_folder']) ? $options['results_folder'] : $options['out_folder'];

		$file_path = $result_folder . '/test.txt';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		echo 'Uploading file : test.txt<br>';
		$tmp_path = tempnam('/tmp', 'test');
		file_put_contents($tmp_path, 'test content');

		$client->uploadFile($tmp_path, $file_path);
		echo 'Uploaded<br>';
		$files = $client->scan_dir($result_folder);
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

	$log_type 		= isset($client->log_type) ? $client->log_type : 'hl7';
	$btype 		=  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl 	= tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$clids = array();
	if (!empty($client->clinic_send_result_field)) {
		$clids = $wpdb->get_col("select cID from ci_clinic_meta where meta_key='{$client->clinic_send_result_field}' and meta_value='1' ");
	}
	$joins = '';
	$fields = '';
	$where = " (fi.m_type='{$client->log_file_slug}' or o.mo_file_id = {$client->api_id})";

	if (!empty($client->send_when_meta)) {
		$joins .= " JOIN {$batch_tbl}_meta mw ON mw.mid = ba.mid and mw.meta_key='{$client->send_when_meta}' ";
	}

	if (!empty($client->options['start_date'])) {
		$where .= " and re.approve_date >= '{$client->options['start_date']}' ";
	}


	if (count($clids) > 0) {
		$clids_str = implode(',', $clids);
		$where = " (
				fi.m_type='{$client->log_file_slug}' 
				or 
					( ba.cli_ID in ({$clids_str}) and fi.m_ID is null and o.mo_file_id ={$client->api_id} )
				)";
	}
	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s or o.mo_peg_ID = %s) ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%',
			$_REQUEST['s'],
			$_REQUEST['s']
		);
	}




	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from {$batch_tbl} ba
                            		left join {$report_tbl} re ON re.patient_id = ba.assen
	                                left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
									left join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
									{$joins}
	                                where {$where}"));
	echo $wpdb->last_error;
	$offset = $pagination->offset();


	$rows 		= $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                re.id,
                                re.estatus,
                                re.approve_date,
								o.*,
                                fi.m_ID,
                                fi.m_time
                            from {$batch_tbl} ba
                            left join {$report_tbl} re ON re.patient_id = ba.assen
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
                            left join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
							{$joins}
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");
	//echo $wpdb->last_query;
	echo $wpdb->last_error;

	if (isset($_GET['debug'])) {
		echo $wpdb->last_query;
	}

	ob_start();

	$enable_received = false;
	$sent_field = 'mo_status';
	if (isset($client->sent_field) && !empty($client->sent_field)) {
		$sent_field = $client->sent_field;
	}
	if (isset($client->enable_receive_orders) && $client->enable_receive_orders) {
		$enable_received = true;
	} else if (isset($client->enable_receive_results) && $client->enable_receive_results) {
		$enable_received = true;
	}
?>

	<div class="tablenav">
		<div style="float:left">
			<input type="search" id="assen_search" value="<?php echo $_REQUEST['s'] ?>" placeholder="Accession or patient name">
			<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
		</div>
		<?php if ($pagination) echo $pagination->pagination() ?>
	</div>

	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:80px">#</th>
				<th>Accession</th>
				<th>Patient</th>
				<th>Clinic</th>
				<th>Doctor</th>
				<th style="width:80px">Status</th>
				<th style="width:80px;">Sent</th>
				<?php if ($enable_received) {
					echo '<th style="width:80px;">Received</th>';
				} ?>
				<th style="width:80px;">DOC</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {
				$row->other_info = !empty($row->other_info) ? json_decode($row->other_info, true) : array();
				$sent = '';
				$sent_arr = array();
				if ($client->enable_send_results) {

					if (!empty($row->mo_sent_dt)) {
						$sent_arr[$row->mo_sent_dt] = $row->mo_sent_dt;
						$last_approved = $wpdb->get_var("select created from ci_log_users 
						where mid = {$row->mid} and btype = '{$btype}' 
						and user_action like '%approve%' 
						order by created desc limit 1");

						if ($client->send_result_not_final && intval($row->final_results) == 1) {
							//dont resend
						} else if (strtotime($row->mo_sent_dt) <= strtotime($last_approved) && $row->estatus == 'C') {
							$sent_arr['resend'] = ' <span style="color:red">need resend</span>';
							if ($row->{$sent_field} != 2) {
								$wpdb->update('ci_sync_api_orders', array($sent_field => 2), array('mo_ID' => $row->mo_ID));
							}
						}
					}
				} else {
					$sent =  $row->{$sent_field} == 1 ? 'Yes' : '';
				}

				if (isset($row->other_info['sent']) && is_array($row->other_info['sent'])) {
					foreach ($row->other_info['sent'] as $sdate) {
						if (is_array($sdate)) {
							$sdate = $sdate['date'];
						}
						$sent_arr[$sdate] = $sdate;
					}
				}
				if (count($sent_arr) > 0) {
					$sent = '<div class="small"><li>' . implode('</li><li>', $sent_arr) . '</li></div>';
				}

				if (empty($sent) && $row->mo_sent == -1) {

					$sent = '<div class="small"><li>Skipped</li></div>';
				}

				if (!isset($row->other_info['received']) || !is_array($row->other_info['received'])) {
					$row->other_info['received'] = array();
				}

				if ($enable_received) {
					if (empty($row->m_time)) {
						$row->m_time = $wpdb->get_var("select m_time 
						from ci_sync_api_files f 
						JOIN ci_sync_api_orders o ON o.mo_file_id = f.m_ID
						where o.mo_mid = {$row->mid} 
						and f.m_type='{$client->log_file_slug}' 
						and o.mo_btype='{$btype}'
						limit 1");
						echo $wpdb->last_error;
					}
					if ($row->m_time > 0) {
						$row->other_info['received'][] = date('Y-m-d H:i:s', $row->m_time);
					}
				}
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
					<td><?php echo $row->mo_test_ID ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $btype . '_' . $row->mid ?>&short=1"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->patient_name ?></td>
					<td><?php echo $row->requesting_practice ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td><a href="?page=<?php echo $btype == 'tox' ? 'ci_resutls' : $btype . '_results' ?>&act=edit&id=<?php echo $row->id;
																														if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
							<?php echo empty($row->estatus) && isset($_GET['debug']) ? '.' : $row->estatus  ?>
						</a>
						<div class="small"><?php if (in_array($row->estatus, array('M', 'C'))) echo date('Y-m-d H:i:s', strtotime($row->approve_date)) ?></div>
					</td>
					<td><?php echo $sent ?></td>
					<?php if ($enable_received) {
						echo '<td>';
						foreach ((array)$row->other_info['received'] as $rdate => $rfile) {
							if (strpos($rdate, ':') === false) {
								$rdate = $rfile;
							}
							echo '<div class="small">' . $rdate . '</div>';
						}

						echo '</td>';
					} ?>
					<td class="small2"><?php echo date('Y-m-d H:i:s', strtotime($row->doc)) ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V4::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->m_ID, 'btype' => $btype, 'moid' => $row->mo_ID), false) . '">' . ($log_type == 'json' ? 'View JSON' : 'View Hl7') . '</a>';
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

	if (isset($_GET['result'])) {
		$moid = isset($_GET['moid']) ? $_GET['moid'] : 0;
		$row = $wpdb->get_row("select * from ci_sync_api_orders where mo_ID = {$moid} ");
		if ($row) {
			$filename = basename($row->mo_peg_ID);
			$file_path = (isset($result_in_log) ? $result_in_log : $resultlog) . $filename;
			if (!file_exists($file_path)) {
				$file_path = $resultlog . $filename;
			}
			var_dump($file_path);
			if (file_exists($file_path) && filesize($file_path) > 0) {
				$client->import_result_file($file_path, $row->mo_btype);
				echo nl2br($client->log);
			} else {
				echo 'file not found';
			}
		} else {
			echo 'not found';
		}
	} else {
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
	}


	exit;
} else if ($act == 'rebuild' || $act == 'resend') {

	$fid = isset($_REQUEST['fid']) ? $_REQUEST['fid'] : 0;
	$moid = isset($_REQUEST['moid']) ? $_REQUEST['moid'] : 0;
	$btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$sql = "select o.*,
			ba.assen,
			ba.mid,
			re.id
			from ci_sync_api_orders o 
			left join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
			join {$batch_tbl} ba ON ba.mid = o.mo_mid 
			join {$report_tbl} re On re.patient_id = ba.assen
			where o.mo_btype = '{$btype}' ";
	if ($fid == 0) {
		$sql .= " and mo_ID = {$moid} ";
	} else {
		$sql .= " and m_ID = {$fid} ";
	}
	$dbfile = $wpdb->get_row($sql);
	echo $wpdb->last_error;

	try {

		ob_start();
		if ($dbfile) {
			$client->debug = true;
			if ($act == 'resend') {
				$client->connect();
				$client->login();
			}

			$client->rebuild($btype, $dbfile->mid);
			echo  str_replace("\n", '<br>', $client->log);
		} else {
			echo 'not found';
		}

		$log = ob_get_clean();
		Tr_Session_Class::add($log);
	} catch (Exception $e) {

		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	wp_redirect(Tr_Base_Class_V6::link(array('act' => 'viewrshl7'), false));
	exit;
} else if ($act == 'viewrshl7') {
	try {
		$hl7_parser = new CI_HL7_Parser();

		$fid 	= isset($_REQUEST['fid']) ? $_REQUEST['fid'] : 0;
		$moid 	= isset($_REQUEST['moid']) ? $_REQUEST['moid'] : 0;
		$btype 	=  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';

		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$row = $wpdb->get_row("select ba.mid,ba.assen, o.*
			from {$batch_tbl} ba
			join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
			where mo_ID = {$moid} ");

		if (!$row) {
			echo 'not found';
			exit;
		}


		if (isset($client->log_type) && $client->log_type == 'json') {
			$log_dir = WP_CONTENT_DIR . '/log/' . $client->log_dir;
			echo '<pre>';
			echo file_get_contents($log_dir . '/' . $row->assen . '.json');
			echo '</pre>';
			echo '<h5>Response:</h5>';
			echo '<pre>';
			echo $row->other_info;
			echo '</pre>';
			$json = json_decode($row->other_info, true);

			if (is_array($json) && isset($json['sent']) && is_array($json['sent'])) {
				foreach ($json['sent'] as $strow) {
					echo '<h5>Files ' . $strow['date'] . ':</h5>';
					foreach ($strow['file_ids'] as $file_id) {
						$filerow = $wpdb->get_row("select * from ci_reports_files where rf_ID = {$file_id}");

						if ($filerow) {
							$fileurl = '/wp-content/log/results_' . $btype . '/' . $filerow->rf_file;
							echo '<a href="' . $fileurl . '" target="_blank">' . $filerow->rf_file . '</a><br>';
						}
					}
				}
			}


			exit;
		}


		if ($fid > 0) {
			$filerow 	= $wpdb->get_row("select * from ci_sync_api_files where m_ID = {$fid}");
			$row->m_file = $filerow->m_file;


			$filename = basename($row->m_file);
			$local_file = $log_dir . $filename;
			if (!empty($filename) && file_exists($local_file)) {
				$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);

				echo 'In file.:' . $filename;
				echo '   <a href="' . $reimportlink . '">reimport</a> ';
				echo ' - <a target="_blank" href="?page=ci_batches_view&act=edit&id=' . $btype . '_' . $row->mid . '&short=1">Req</a> ';



				$hl7 = file_get_contents($local_file);
				echo '<textarea style="width:2000px;height:300px">';
				echo esc_textarea(substr($hl7, 0, 10000));
				echo '</textarea>';
				if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
					$parsed = $hl7_parser->parse_hl7($hl7);
					if (!empty($parsed) && !isset($parsed['ERROR'])) {
						echo $hl7_parser->render_html($parsed);
					}
				}
			}
		} else {


			//check in file
			if (!empty($row->mo_peg_ID) && stripos($row->mo_peg_ID, '.') > 0) {
				$filename = basename($row->mo_peg_ID);
				$local_file = (isset($result_in_log) ? $result_in_log : $resultlog) . $filename;
				if (!file_exists($local_file)) {
					$local_file = $resultlog . $filename;
				}
				if (!empty($filename) && file_exists($local_file)) {
					$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport', 'result' => true), false);
					echo 'In file.:' . $local_file;
					echo '   <a href="' . $reimportlink . '">reimport</a>';
					echo ' -  <a href="' . Tr_Base_Class_V6::link(array('act' => 'viewpdf', 'infile' => true), false) . '" target=_blank>view pdf</a>';
					$hl7 = file_get_contents($local_file);
					echo '<textarea style="width:2000px;height:300px">';
					echo esc_textarea(substr($hl7, 0, 10000));
					echo '</textarea>';
					if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
						$parsed = $hl7_parser->parse_hl7($hl7);
						if (!empty($parsed) && !isset($parsed['ERROR'])) {
							echo $hl7_parser->render_html($parsed);
						}
					}
				}
			}

			$row->m_file = $row->assen . '.hl7';
		}


		echo 'output:';
		echo '   <a href="' .  Tr_Base_Class_V6::link(array('act' => 'rebuild'), false) . '">rebuild</a> - ';
		if (file_exists($resultlog . basename($row->m_file))) {

			echo  $resultlog . basename($row->m_file);
			echo '   <a href="' .  Tr_Base_Class_V6::link(array('act' => 'resend'), false) . '">resend</a>';
			echo ' -  <a href="' . Tr_Base_Class_V6::link(array('act' => 'viewpdf'), false) . '" target=_blank>view pdf</a>';
			echo ' -  <a href="' . Tr_Base_Class_V6::link(array('act' => 'viewreqpdf'), false) . '" target=_blank>view req pdf</a>';
			$hl7 = file_get_contents($resultlog . basename($row->m_file));
			echo '<textarea style="width:2000px;height:300px">';
			echo esc_textarea(substr($hl7, 0, 10000));
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
} else if ($act == 'viewreqpdf') {
	$fid = isset($_REQUEST['fid']) ? $_REQUEST['fid'] : 0;
	$moid = isset($_REQUEST['moid']) ? $_REQUEST['moid'] : 0;
	$btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	if ($fid > 0 && $moid > 0) {
		$row = $wpdb->get_row("select ba.assen, ba.mid, o.*, fi.m_file
			from {$batch_tbl} ba
			join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
			join ci_sync_api_files fi ON fi.m_ID = {$fid}
			where mo_ID = {$moid} ");
	} else if ($fid > 0) {
		$row = $wpdb->get_row("select * from ci_sync_api_files where m_ID = {$fid}");
	} else {
		$row = $wpdb->get_row("select ba.assen, ba.mid, o.*
			from {$batch_tbl} ba
			join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
			where mo_ID = {$moid} ");

		$row->m_file = $row->assen . '.hl7';

		if (isset($_REQUEST['infile'])) {
			$row->m_file = $row->mo_peg_ID;
		}
	}

	$base64code = '';
	if ($row) {
		$rs_file = basename($row->m_file);
		if (file_exists($resultlog . $rs_file)) {
			$hl7 = file_get_contents($resultlog . $rs_file);
			$arr = $client->hl7_to_array($hl7, array('OBR'));

			foreach ((array)$arr['OBX'] as $obx) {
				$obx_code = isset($obx['OBX.3']['OBX.3.1']) ? strtolower($obx['OBX.3']['OBX.3.1']) : '';
				$obx_desc = isset($obx['OBX.3']['OBX.3.2']) ? strtolower($obx['OBX.3']['OBX.3.2']) : '';
				if (
					strpos($obx_code, 'requisition') === false
					&& strpos($obx_code, 'regusition') === false
					&& strpos($obx_desc, 'requisition') === false
				) {
					continue;
				}

				if (isset($obx['OBX.5']['OBX.5.4']) && !empty($obx['OBX.5']['OBX.5.4'])) {
					$base64code = $obx['OBX.5']['OBX.5.4'];
				} else if (isset($obx['OBX.5']['OBX.5.3']) && !empty($obx['OBX.5']['OBX.5.3'])) {
					$base64code = $obx['OBX.5']['OBX.5.3'];
				} else if (isset($obx['OBX.5']['OBX.5.5']) && !empty($obx['OBX.5']['OBX.5.5'])) {
					$base64code = $obx['OBX.5']['OBX.5.5'];
				}
				break;
			}
		}

		if (!empty($base64code)) {
			header("Content-type:application/pdf");
			echo base64_decode($base64code);
		} else if (!empty($row->mid)) {
			$current_get = $_GET;
			$current_request = $_REQUEST;
			$pdf_file = trpdd_get_req_pdf($row->mid, $btype);
			$_GET = $current_get;
			$_REQUEST = $current_request;

			if ($pdf_file && file_exists($pdf_file)) {
				header("Content-type:application/pdf");
				readfile($pdf_file);
			}
		}
	}

	exit;
} else if ($act == 'viewpdf') {
	$fid = isset($_REQUEST['fid']) ? $_REQUEST['fid'] : 0;
	$moid = isset($_REQUEST['moid']) ? $_REQUEST['moid'] : 0;
	$btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	if ($fid > 0) {
		$row = $wpdb->get_row("select * from ci_sync_api_files where m_ID = {$fid}");
	} else {
		$row = $wpdb->get_row("select ba.assen, o.*
			from {$batch_tbl} ba
			join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
			where mo_ID = {$moid} ");

		$row->m_file = $row->assen . '.hl7';

		if (isset($_REQUEST['infile'])) {
			$row->m_file = $row->mo_peg_ID;
		}
	}

	$rs_file = basename($row->m_file);
	//var_dump($rs_file);
	if (file_exists($resultlog . $rs_file)) {
		$hl7 = file_get_contents($resultlog . $rs_file);
		$arr = $client->hl7_to_array($hl7, array('OBR'));

		$base64code = '';
		foreach ($arr['OBX'] as $obx) {

			if (strtolower($obx['OBX.3']['OBX.3.1']) == 'pdf' || stripos($obx['OBX.3']['OBX.3.2'], 'pdf') !== false) {

				$base64code = $obx['OBX.5']['OBX.5.5'];
				if ($obx['OBX.5']['OBX.5.2'] == 'Base64') {
					$base64code = $obx['OBX.5']['OBX.5.3'];
				}
				break;
			} else if ($obx['OBX.5']['OBX.5.2'] == 'Base64' && !empty($obx['OBX.5']['OBX.5.3'])) {
				$base64code = $obx['OBX.5']['OBX.5.3'];
				break;
			}
		}

		header("Content-type:application/pdf");
		echo base64_decode($base64code);
	}

	exit;
} else if ($act == 'install') {

	$dir 		= str_replace('/usr/local/deployments', '/mnt/data/deployments', $dir);
	$order_dir 	= str_replace('/usr/local/deployments', '/mnt/data/deployments', $order_dir);
	$result_dir = str_replace('/usr/local/deployments', '/mnt/data/deployments', $result_dir);
	$order_log_dir = str_replace('/usr/local/deployments', '/mnt/data/deployments', $order_log_dir);


	?>
	<h2>Install</h2>

	<textarea style="width:100%;height:500px">
		<?php

		$username = !empty($options['username']) ? $options['username'] : $client->log_file_slug;

		$password = $options['password'];
		if (empty($password)) {
			$password = wp_generate_password(12, false);
		}


		$home = !empty($dir) ? $dir : dirname(ABSPATH) . '/' . $options['username'] . '_sftp';
		$homematch = !empty($dir) ? dirname($dir) . '/%u_sftp' : dirname(ABSPATH) . '/%u_sftp';

		if (stripos(ABSPATH, 'bitnami') > 0) {

			echo "\n";
			echo ("sudo useradd -m -d $home -s /usr/sbin/nologin $username" . "\n");
			echo ("echo \"$username:$password\" | sudo chpasswd" . "\n");
			echo ("sudo groupadd sftpusers" . "\n");
			echo ("sudo usermod -aG sftpusers $username" . "\n");

			// Fix chroot permissions
			echo ("sudo chown root:root $home" . "\n");
			echo ("sudo chmod 755 $home" . "\n");

			// Create writable subfolder
			echo ("sudo mkdir -p $home/orders" . "\n");
			echo ("sudo mkdir -p $home/results" . "\n");
			echo ("sudo chown $username:$username $home/orders" . "\n");
			echo ("sudo chown $username:$username $home/results" . "\n");

			echo "\nadd this end of file: /etc/ssh/sshd_config:\n";
			echo "Match Group sftpusers\n";
			echo "ChrootDirectory /opt/bitnami/%u_sftp\n";
			echo "ForceCommand internal-sftp\n\n";
			echo "PasswordAuthentication yes\n\n";
			echo "AllowUsers *\n\n";
			echo "Test before restart: \n";
			echo "sudo sshd -t\n";

			echo "sudo systemctl restart sshd\n";
		} else if (!empty($dir)) {

			echo "\n";
			echo ("sudo useradd -m -d $home -s /usr/sbin/nologin $username" . "\n");
			echo ("echo \"$username:$password\" | sudo chpasswd" . "\n");
			echo ("sudo groupadd sftpusers" . "\n");
			echo ("sudo usermod -aG sftpusers $username" . "\n");

			// Fix chroot permissions
			echo ("sudo chown root:root $home" . "\n");
			echo ("sudo chmod 755 $home" . "\n");


			// Create writable subfolder
			echo ("sudo mkdir -p $home/orders" . "\n");
			echo ("sudo mkdir -p $home/results" . "\n");
			echo ("sudo chown $username:$username $home/orders" . "\n");
			echo ("sudo chown $username:$username $home/results" . "\n");

			echo "\nadd this end of file: /etc/ssh/sshd_config:\n";
			echo "Match Group sftpusers\n";
			echo "ChrootDirectory {$homematch}\n";
			echo "ForceCommand internal-sftp\n\n";
			echo "PasswordAuthentication yes\n\n";
			echo "AllowUsers *\n\n";
			echo "Test before restart: \n";
			echo "sudo sshd -t\n";
			echo "sudo systemctl restart sshd\n";
		} else {
			echo 'Please set deployment dir in options tab';
		}
		?>

</textarea>
<?php
	exit;
} else if ($act == 'list_ids') {

	$clinic_fid = $client->clinic_fieldname;
	$checkboxfid = !empty($client->clinic_send_result_field) ? $client->clinic_send_result_field  : '';

	$fields = '';
	$joins = '';
	if (!empty($checkboxfid)) {
		$joins = " left join ci_clinic_meta mt ON mt.cID = cli.cID and mt.meta_key='{$checkboxfid}' ";
		$fields = ", mt.meta_value as {$checkboxfid} ";
	}
	$sql = "select cli.*
	{$fields}
	from ci_clinic cli
	{$joins}
	where cli.cli_status = 1 
	order by cast(cli.{$clinic_fid} as unsigned) asc";

	$list = $wpdb->get_results($sql);
	if (!empty($wpdb->last_error)) {
		$wpdb->query("ALTER TABLE `ci_clinic` ADD {$clinic_fid} text;");
		$list = $wpdb->get_results($sql);
	}
	$maxid = 0;
	if (isset($options['clinicid_range']) && !empty($options['clinicid_range'])) {
		list($min, $max) = explode('-', $options['clinicid_range']);
		$min = intval($min);
		$maxid = $wpdb->get_var("select max({$clinic_fid}) from ci_clinic ");
		if ($maxid < $min) {
			$maxid = $min - 1;
		}
	}
	echo $wpdb->last_error;
?>
	<select class="sselect23" id="select_add_clinic">
		<?php
		echo '<option value="">Select Clinic</option>';
		foreach ($list as $row) {
			if (!empty($row->{$clinic_fid})) continue;
			echo '<option value="' . $row->cID . '">' . $row->cli_clinic . '</option>';
		}
		?>
	</select>
	<table id="idstbl" class="posts wp-list-table widefat fixed hover_tbl">
		<thead>
			<tr>
				<th style="width:30px">#</th>
				<th style="width:35%">Clinic</th>
				<th style="width:100px">ID</th>
				<?php if (!empty($checkboxfid)) { ?>
					<th style="width:80px">Send Result</th>
				<?php } ?>
				<th>Address</th>
				<th>City</th>
				<th>State</th>
				<th>Zip</th>
			</tr>
		</thead>
		<?php
		foreach ($list as $row) {
			/*
			if (!is_null($maxid) && empty($row->{$clinic_fid})) {
				$maxid++;
				$row->{$clinic_fid} = $maxid;
				$wpdb->update('ci_clinic', array($clinic_fid => $row->{$clinic_fid}), array('cID' => $row->cID));
			}
				*/
		?>
			<tr class="datarow <?php if (empty($row->{$clinic_fid})) echo 'hide' ?>" data-id="<?php echo $row->cID ?>">
				<td><?php echo $row->cID ?></td>
				<td><?php echo $row->cli_clinic ?></td>
				<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="<?php echo $clinic_fid ?>" value="<?php echo $row->{$clinic_fid} ?>" style="width:90%" /></td>
				<?php if (!empty($checkboxfid)) { ?>
					<td><input type="checkbox" class="editfield" data-id="<?php echo $row->cID ?>" data-k="<?php echo $checkboxfid ?>" value="1" <?php echo checked($row->{$checkboxfid}) ?> /></td>
				<?php } ?>
				<td><?php echo $row->cli_street ?></td>
				<td><?php echo $row->cli_city ?></td>
				<td><?php echo $row->cli_state ?></td>
				<td><?php echo $row->cli_zip ?></td>
			</tr>
		<?php
		}
		?>
	</table>
	<script>
		jQuery(function($) {
			$('.sselect2').select2();
			$('#select_add_clinic').on('change', function() {
				var $vl = $(this).val();
				if ($vl != '') {
					$('tr.datarow[data-id="' + $vl + '"]').removeClass('hide');
					$('option[value="' + $vl + '"]', $(this)).remove();
					$(this).val('').change();
				}

				return false;
			})
		})
	</script>
<?php
	exit;
} else if ($act == 'save_ids') {

	ob_clean();

	$return = array('success' => 1, 'msg' => '');
	$id 	= $_REQUEST['id'];
	$col 	= trim($_REQUEST['col']);
	$vl 	= trim($_REQUEST['val']);

	if (stripos($col, 'tg_') !== false) {
		$rs = $wpdb->update('ci_test_groups', array($col => $vl), array('tg_ID' => $id));
	} else if (stripos($col, 'te_') !== false) {
		$rs = $wpdb->update('ci_test_panels', array($col => $vl), array('te_ID' => $id));
	} else if (stripos($col, '_meta') !== false) {
		$existid = $wpdb->get_var("select id from ci_clinic_meta where cID = {$id} and meta_key='{$col}' ");
		if ($existid > 0) {
			$rs = $wpdb->update('ci_clinic_meta', array('meta_value' => $vl), array('cID' => $id, 'meta_key' => $col));
		} else {
			$rs = $wpdb->insert('ci_clinic_meta', array('meta_value' => $vl, 'cID' => $id, 'meta_key' => $col));
		}
	} else {
		$rs = $wpdb->update('ci_clinic', array($col => $vl), array('cID' => $id));
	}

	if ($rs === false) {
		$return['success'] = 0;
		$return['msg'] = 'Error. Please try again';
	}
	wp_send_json($return);
	exit;
} else if ($act == 'search_dup_results') {
	$list = array();
	$files = scandir($log_dir_results);
	foreach ($files as $file) {
		if (strpos($file, '.hl7') > 0) {
			$filepath = $log_dir_results . $file;
			$fimtime = filemtime($filepath);
			//ignore files older than 30 days
			if ($fimtime < (time() - 30 * 24 * 3600)) {
				continue;
			}
			$hl7 = file_get_contents($filepath);
			$arr = $client->hl7_to_array($hl7, array('OBR'));
			$assen 		= $arr['ORC']['ORC.2']['ORC.2.1'];
			if (empty($assen)) {
				foreach ($arr['OBR'] as $obr) {
					$assen = $obr['OBR.2']['OBR.2.1'];
					if (!empty($assen)) {
						break;
					}
				}
			}
			if (strlen($assen) >= 10) {
				if (!isset($list[$assen])) {
					$list[$assen] = array();
				}
				$list[$assen][] = $file;
			}
		}
	}
	foreach ($list as $assen => $files) {
		if (count($files) > 1) {
			echo '<strong>Accession: ' . $assen . '</strong><br>';
			$dbfile 		= null;
			$other_info 	= array();
			$imported_files = array();
			if (isset($_GET['reimport'])) {
				$dbfile = $wpdb->get_row("select o.*,
		                        ba.assen
		                     from ci_sync_api_orders o 
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid and o.mo_btype = 'pcr'
		                     where ba.assen = '" . esc_sql($assen) . "'
							 and o.mo_file_id = {$client->api_id} 
		                     order by o.mo_ID desc limit 1 ");
				$other_info = !empty($dbfile->other_info) ? json_decode($dbfile->other_info, true) : array();
				foreach ($other_info['received'] as $fname => $dt) {
					if (stripos($fname, '.') !== false) {
						$imported_files[] = $fname;
					}
				}
			}

			foreach ($files as $file) {
				echo $file . '<br>';
				$file_path = $log_dir_results . $file;
				if (isset($_GET['reimport']) && count($imported_files) <= 1) {
					$client->import_result_file($file_path);
				}
			}
			echo '<br>';
		}
	}
	echo nl2br($client->log);
	exit;
} else if ($act == 'put_demo') {
	$cache_file = WP_CONTENT_DIR . '/cache/' . $client->log_file_slug . '_demo.hl7';
	$demo_file = TRCPDD_PATH . '/parts/demo/demo_order.hl7';
	if (isset($_POST['hl7'])) {
		$hl7 = stripslashes($_POST['hl7']);
		file_put_contents($cache_file, $hl7);
		try {
			$client->debug = true;
			$client->connect();
			$client->login();
			echo 'logged in<br>';
			echo 'Uploading demo file<br>';
			$file_path = $options['in_folder'] . '/demo_order.hl7';
			$client->uploadFile($cache_file, $file_path);
			echo 'Uploaded<br>';
			echo str_replace("\n", '<br>', $client->log);
		} catch (Exception $e) {
			echo 'Error: ' . $e->getMessage();
		}
		exit;
	}
	if (file_exists($cache_file)) {
		$demo_file = $cache_file;
	}
?>
	<div id="outputdemoup"></div>
	<form method="post" id="demo_form">
		<input type="hidden" name="act" value="put_demo">
		<input type="hidden" name="trajax" value="put_demo">
		<textarea name="hl7" style="width:100%;height:300px"><?php echo file_get_contents($demo_file) ?></textarea>
		<button class="button" type="submit">Upload</button>
	</form>

	<script>
		jQuery(function($) {
			$('form#demo_form').on('submit', function() {
				var hl7 = $('textarea[name="hl7"]').val();
				if (hl7.length < 10) {
					alert('Please input hl7 content');
					return false;
				}

				$.post('', $(this).serialize(), function(res) {
					$('#outputdemoup').html(res);
				});
				return false;

			})
		})
	</script>
<?php
	exit;
}
