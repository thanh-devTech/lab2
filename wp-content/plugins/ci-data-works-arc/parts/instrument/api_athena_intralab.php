<?php
global $wpdb, $current_role;


include_once(TRCPDD_PATH . 'inc/define_apis.php');



$api_key = $subtab;
$api_arr = $define_apis[$api_key];


$log_dir = WP_CONTENT_DIR . '/log/athena_intralab/';
$log_dir_orders = WP_CONTENT_DIR . '/log/athena_intralab_orders/';

if (!file_exists($log_dir_orders)) {
	mkdir($log_dir_orders);
}
if (!file_exists($log_dir)) {
	mkdir($log_dir);
}


include(TRCPDD_PATH . 'api/ci_api_athena_intralab.php');
$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'4' => 'Inconclusive'
);



if (isset($_POST['options'])) {

	$key_api = $api_arr['st'];
	$options = $_POST['options'];
	$old_options = get_option($key_api, array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_' . $key_api, $bkdata);

	update_option($api_arr['st'], $_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option($api_arr['st'], array());
	//setup cron
	wp_clear_scheduled_hook($api_arr['cron'][0], array($api_arr['cron'][1]));

	if ($options['enable']) {
		if (empty($options['cron'])) {
			$options['cron'] = 5;
		}
		wp_schedule_event(time() + 100, $options['cron'] . 'min', $api_arr['cron'][0], array($api_arr['cron'][1]));
	}
}


$options = get_option($api_arr['st'], array());
if (!isset($options['lab_id'])) {
	$options['lab_id'] = 0;
}
if (!isset($options['cli_id'])) {
	$options['cli_id'] = 0;
}
if (!isset($options['doc_id'])) {
	$options['doc_id'] = 0;
}



try {
	$client = new CI_Api_Athena_Intralab();
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}

$username = isset($options['username']) ? $options['username'] : '';
$password = isset($options['password']) ? $options['password'] : '';
if (isset($_REQUEST['type']) && $_REQUEST['type'] == 'result') {
	$username = $options['results_username'];
	$password = $options['results_password'];
}

if ($act == 'test_connect') {
	try {

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';
		$path = rtrim($path, '/');
		if (empty($path)) {
			$path = '/';
		}
		$client->connect();

		$client->login($username, $password);
		echo 'logged in: ' . $username . '<br>';


		$files = $client->scan_dir($path);
		if ($path != '/') {
			$path .= '/';
		}

		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($path)), false) . '" class="show_popup inlineload button">Up</a> ';
		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($path)), false) . '" class="show_popup inlineload button">Reload</a>';
		echo '<br>';
		echo '<br>List files: ' . $path . '<br>';
		echo 'count:' . count($files) . '<br>';
		foreach ($files as $file) {
			if (strpos($file, '.') > 0) {
				if (isset($_GET['delete'])) {
					echo 'Deleting file: ' . $path . $file . ' ... ';
					$r = $client->delete($path . $file);
					var_dump($r);
					echo ' => deleted<br>';
				}
				//var_dump($r);
				$filetime = $client->getFileTime($path . $file);
				if ($filetime > 0) {
					$filetime = date('Y-m-d H:i:s', $filetime);
				}
				echo '<li><a href="' . Tr_Base_Class_V6::link(array('act' => 'readfile', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a>  ' . $filetime . '</li>';
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
		$client->login($username, $password);
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
		$client->login($options['username'], $options['password']);
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
} else if ($act == 'send_reports_by_date') {

	if (isset($_REQUEST['act2']) && $_REQUEST['act2'] == 'do_send') {
		$id = $_REQUEST['id'];
		$client->debug = true;
		$client->connect();
		$client->login($options['results_username'], $options['results_password']);
		$client->sync_reports($id);
		$return = array('status' => 'ok', 'html' => nl2br($client->log));
		wp_send_json($return);
	} else if (isset($_POST['report_date']) && !empty($_POST['report_date'])) {
		$return = array('status' => 'ok', 'count' => 0, 'html' => '');
		$date = sanitize_text_field($_POST['report_date']);
		ob_start();

		$rows = $wpdb->get_results("select f.*,
		                        ba.assen,
								ba.mid,
								re.id
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
							  left join ci_reports_pcr re ON re.patient_id = ba.assen
		                     where f.m_type = '{$client->api_id}'
		                       and DATE(re.approve_date) = '{$date}'
							 order by o.mo_ID asc
							 ");

		echo 'count: ' . count($rows) . '<br><br>';
		$return['count'] = count($rows);
		if (count($rows) > 0) {
			echo '<table id="reports_table_list" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">';
			foreach ($rows as $num => $row) {
				echo '<tr data-id="' . esc_html($row->id) . '">';
				echo '<td class="countnum">' . ($num + 1) . '</td>';
				echo '<td class="assen">' . esc_html($row->assen) . '</td>';
				echo '<td class="result"></td></tr>';
			}
			echo '</table>';
		}

		$return['html'] = ob_get_clean();
		wp_send_json($return);
	}

?>

	<div class="frow">
		<label>Date:</label>
		<input type="date" name="report_date" style="width:120px">
		<input type="button" id="do_send_reports" style="display: none;" class="button button-primary" value="Send Reports">
	</div>
	<div id="print_output"></div>

	<script>
		jQuery(document).ready(function($) {
			$('[name="report_date"]').on('change', function() {
				var date = $('input[name="report_date"]').val();
				$('#print_output').html('Loading...');
				$.ajax({
					data: {
						trajax: '1',
						'act': 'send_reports_by_date',
						'report_date': date
					},
					type: 'POST',
					dataType: 'json',
					success: function(res) {
						$('#print_output').html(res.html);
						if (res.count > 0) {
							$('#do_send_reports').show();
						} else {
							$('#do_send_reports').hide();
						}
					},
					error: function() {
						$('#print_output').html('Error loading data');
					}
				});
			});
			$('#do_send_reports').on('click', function() {
				if ($(this).val() == 'Sending...') return false;
				$(this).val('Sending...');
				$('#reports_table_list tr').each(function(index) {
					let $tr = $(this);
					let id = $tr.data('id');
					let $rstd = $tr.find('.result');
					$rstd.html('Sending...');
					var delay = 3000; // milliseconds
					setTimeout(function() {
						$.ajax({
							data: {
								trajax: '1',
								'act': 'send_reports_by_date',
								'act2': 'do_send',
								'id': id
							},
							type: 'POST',
							dataType: 'json',
							success: function(res) {
								$rstd.html('<div>' + res.html + '</div>');
							},
							error: function() {
								$rstd.html('Error. <a href="#">Retry</a>').find('a').on('click', function() {
									$tr.trigger('click');
									return false;
								});
							}
						});
					}, index * delay);
				});

				return false;
			});
		});
	</script>
<?php
	exit;
} else if ($act == 'test_upload') {
	try {
		$file_path = $client->result_folder . '/test.txt';
		$client->debug = true;
		$client->connect();
		$client->login($options['results_username'], $options['results_password']);
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
	ob_start();
	$where      = " fi.m_type='{$client->api_id}' ";
	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (
			ba.assen like %s
			or ba.patient_name like %s 
			or o.mo_test_ID =%s)  ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%',
			$_REQUEST['s']
		);
	}
	if (!empty($_REQUEST['status'])) {
		if ($_REQUEST['status'] == 'sent') {
			$where .= " and o.mo_sent = 1 ";
		} else if ($_REQUEST['status'] == 'received') {
			$where .= " and fi.m_time is not null ";
		} else if ($_REQUEST['status'] == 'resulted') {
			$where .= " and re.estatus='C' ";
		} else if ($_REQUEST['status'] == 'rejected') {
			$where .= " and (ba.batch_status='rejected' or ba.batch_status like 're_%') ";
		}
	}

	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(distinct(ba.mid)) as c 
	                                from ci_batches_pcr ba
                            		left join ci_reports_pcr re ON re.patient_id = ba.assen
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid
									join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.doc,
								ba.batch_status,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                o.mo_status,
                                o.mo_sent,
                                o.mo_sent_dt,
                                o.other_info,
                                o.mo_test_ID,
                                fi.m_ID,
                                fi.m_time,
								count(o.mo_ID) as countorders
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where  {$where}
							group by ba.mid
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");
	echo $wpdb->last_error;
?>
	<div id="formfilter" data-url="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">
		<input type="search" id="assen_search" value="<?php echo $_REQUEST['s'] ?>">

		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
		<select id="sendre_status">
			<?php
			foreach (array('' => 'All', 'sent' => 'Sent', 'received' => 'Received', 'resulted' => 'Resulted', 'rejected' => 'Rejected') as $k => $v) {
				$sel = (isset($_REQUEST['status']) && $_REQUEST['status'] == $k) ? 'selected' : '';
				echo "<option value='{$k}' {$sel}>{$v}</option>";
			}
			?>
		</select>
	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:60px">#</th>
				<th>Order#</th>
				<th>Accession</th>
				<th>Patient</th>
				<th style="width:60px">Resulted</th>
				<th style="width:50px;">Orders</th>
				<th style="width:100px;">Sent</th>
				<th style="width:100px">Received</th>
				<th style="width:70px"></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$update = array();
			if (isset($_REQUEST['debug'])) {
				if ($options['cli_id'] > 0) {
					$clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$options['cli_id']}' ");
					$update['cli_ID'] = $options['cli_id'];
					$update['requesting_practice'] = $clinic_name;
				}
				if ($options['doc_id'] > 0) {
					$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$options['doc_id']}");
					$default_doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
					//$update['doc_ID'] = $options['doc_id'];
					//$update['requesting_physician'] = $default_doctor_name;
				}
			}
			foreach ($rows as $i => $row) {

				$other_info = json_decode($row->other_info, true);
				if (isset($_REQUEST['updatedoctor'])) {
					$doc_first         = $other_info['ORC12.2'];
					$doc_last         = $other_info['ORC12.3'];

					if (!empty($doc_first) && !empty($doc_last)) {
						$doc_id = $wpdb->get_var("select doc_ID from ci_doc where  
									 doc_first='{$doc_first}' and doc_last='{$doc_last}' ");
						if ($doc_id == 0) {
							$wpdb->insert('ci_doc', array('doc_first' => $doc_first, 'doc_last' => $doc_last));
							$doc_id = $wpdb->insert_id;
						}
						$doctor_name = $doc_last . ', ' . $doc_first;
						$update['doc_ID'] = $doc_id;
						$update['requesting_physician'] = $doctor_name;
					}
				}
				if (isset($_REQUEST['debug'])) {
					$wpdb->update('ci_batches_pcr', $update, array('mid' => $row->mid));
				}
				$sent_datetimes = array();
				if (!empty($row->mo_sent_dt)) {
					$sent_datetimes[] = $row->mo_sent_dt;
				}

				if (isset($other_info['sent'])) {
					foreach ($other_info['sent'] as $sent) {
						if (!in_array($sent, $sent_datetimes)) {
							$sent_datetimes[] = $sent;
						}
					}
				}
				$sent_datetime = implode('<br>', $sent_datetimes);

			?>
				<tr class="childp" data-id="<?php echo $row->id ?>" data-mid="<?php echo $row->mid ?>">
					<td><?php echo $row->mid ?></td>
					<td><?php echo $row->mo_test_ID ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid ?>"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->patient_name ?></td>
					<td><a href="?page=pcr_results&act=edit&id=<?php echo $row->id;
																echo '&createnew=' . $row->mid ?>" target="_blank">
							<?php echo $row->estatus == 'C' ? 'Yes' : '.' ?>
						</a>
					</td>
					<th><?php echo $row->countorders ?></th>
					<td><?php echo $sent_datetime ?></td>
					<td><?php echo date('Y-m-d H:i:s', $row->m_time) ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewrshl7', 'mid' => $row->mid), false) . '">View Hl7</a>';
						if ($row->mo_status > 0 || $row->mo_sent > 0) {
							echo '<br><br><a class="resend" href="#" data-id="' . $row->id . '" data-mid="' . $row->mid . '">Resend</a>';
						}
						?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<?php
	$content = ob_get_clean();
	if (isset($_REQUEST['trajax'])) {
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
		if ($client->login($options['results_username'], $options['results_password'])) {
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
	wp_send_json($return);
	exit;
} else if ($act == 'viewrshl7') {
	try {
		$hl7_parser = new CI_HL7_Parser();
		$btype = 'pcr';
		$rows = $wpdb->get_results("select f.*,
		                        ba.assen,
								ba.mid,
								re.id
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
							  left join ci_reports_pcr re ON re.patient_id = ba.assen
		                     where ba.mid = {$_REQUEST['mid']}
							 order by o.mo_ID asc
							 ");

		$row = $rows[0];
		if ($_REQUEST['act2'] == 'resend') {

			$client->debug = true;
			$client->connect();
			$client->login($options['results_username'], $options['results_password']);
			echo 'logged in: ' . $options['results_username'] . '<br>';

			$client->sync_reports($row->id);
			echo str_replace("\n", '<br>', $client->log);
			$files = $client->scan_dir($client->result_folder);
			echo '<br>List files in Results folder:<br>';
			foreach ($files as $file) {
				echo '<li>' . $file . '</li>';
			}
			exit;
		} else if ($_REQUEST['act2'] == 'rebuild' && $row->id > 0) {

			$client->sync_reports($row->id);
			echo str_replace("\n", '<br>', $client->log);
			echo 'rebuilded success<br>';
		}


		foreach ($rows as $row) {
			$local_file = $log_dir_orders . basename($row->m_file);
			if (file_exists($local_file)) {
				$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport', 'fid' => $row->m_ID), false);
				$req_link = '?page=ci_batches_view&act=edit&id=' . $btype . '_' . $row->mid;
				echo 'order file.: ' . basename($local_file);
				echo ' <a href="' . $reimportlink . '">reimport</a>';
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
			}
		}



		$rs_file = $row->assen . '.hl7';
		if (file_exists($log_dir . $rs_file)) {
			echo '<br>';
			$linkbuild = Tr_Base_Class_V6::link(array('act2' => 'rebuild'), false);
			$linksend = Tr_Base_Class_V6::link(array('act2' => 'resend'), false);
			$pdf_link = Tr_Base_Class_V6::link(array('act' => 'viewpdf'), false);
			echo 'output file:' . $rs_file;
			echo ' <a  href="' . $linkbuild . '">rebuild</a> - ';
			echo ' <a  href="' . $linksend . '">send</a> ';

			echo ' -  <a href="' . $pdf_link . '" target=_blank>view pdf</a>';
			$hl7 = file_get_contents($log_dir . $rs_file);
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

	$btype = 'pcr';
	$row = $wpdb->get_row("select f.*,
		                        ba.assen,
								ba.mid,
								re.id
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
							  left join ci_reports_pcr re ON re.patient_id = ba.assen
		                     where mo_mid = {$_REQUEST['mid']}");

	$rs_file = $row->assen . '.hl7';

	if (file_exists($log_dir . $rs_file)) {
		$hl7 = file_get_contents($log_dir . $rs_file);

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
} else if ($act == 'codes') {
	$rows = $wpdb->get_results("select *
                from ci_test_groups 
                    where tg_type = 'pcr' and tg_show_req = 1
					order by tg_position asc");
	?>

	<table id="idstbl" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:20px">#</th>
				<th>Group</th>
				<th>Code</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {
				$other = json_decode($row->tg_other_options, true);
				$row->code = isset($other['athena_intralab']) ? $other['athena_intralab'] : '';

			?>
				<tr class="datarow" data-id="<?php echo $row->tg_ID ?>">
					<td><?php echo $row->tg_ID ?></td>
					<td><?php echo $row->tg_name ?></td>
					<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->tg_ID ?>" data-k="panel_other_athena_intralab" value="<?php echo $row->code ?>" style="width:200px" /></td>
				</tr>
			<?php
			}
			if (count($rows) == 0) {
				echo 'not found';
			}
			?>
		</tbody>
	</table>

	<br>


<?php
	exit;
} else if ($act == 'reimport') {


	$dbfile = $wpdb->get_row("select f.*
		                     from ci_sync_api_files f
		                     where m_ID = {$_REQUEST['fid']}");

	$local_file = $log_dir_orders . basename($dbfile->m_file);
	$client->import_req_file($dbfile, $local_file);
	echo nl2br($client->log);
	exit;
} else if ($act == 'list_ids') {

	$list = $wpdb->get_results("select * from ci_clinic 
		where cli_status = 1 
			order by cast(cli_athena_intralab_departmentid as unsigned) asc,
			cli_clinic asc");
	echo $wpdb->last_error;
?>
	<select class="sselect23" id="select_add_clinic">
		<?php
		echo '<option value="">Select Clinic</option>';
		foreach ($list as $row) {
			if (!empty($row->cli_athena_intralab_departmentid)) continue;
			echo '<option value="' . $row->cID . '">' . $row->cli_clinic . '</option>';
		}
		?>
	</select>

	<table id="idstbl" class="posts wp-list-table widefat fixed hover_tbl">
		<thead>
			<tr>
				<th style="width:50px">#</th>
				<th>Clinic</th>
				<th style="width:130px">ID</th>
				<th style="width:130px">Department ID</th>
			</tr>
		</thead>
		<?php
		foreach ($list as $row) {
		?>
			<tr class="datarow <?php if (empty($row->cli_athena_intralab_departmentid)) echo 'hide' ?>" data-id="<?php echo $row->cID ?>">
				<td><?php echo $row->cID ?></td>
				<td><?php echo $row->cli_clinic ?></td>
				<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="cli_athena_intralab_departmentid" value="<?php echo $row->cli_athena_intralab_departmentid ?>" style="width:100px" /></td>
				<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="cli_athena_intralab_departmentid2" value="<?php echo $row->cli_athena_intralab_departmentid2 ?>" style="width:100px" /></td>
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
				console.log($vl);
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
	$return = array('success' => 1, 'msg' => '');
	ob_clean();
	$id = $_REQUEST['id'];
	$col = trim($_REQUEST['col']);
	$vl = trim($_REQUEST['val']);

	if (stripos($col, 'panel_') === 0) {
		$col = substr($col, 6);
		if (stripos($col, 'other_') === 0) {

			$otheroptions = $wpdb->get_var("select tg_other_options from ci_test_groups where tg_ID = {$id} ");

			$otheroptions = json_decode($otheroptions, true);
			if (is_array($otheroptions) && !empty($otheroptions)) {
				$subcol = substr($col, 6);
				$otheroptions[$subcol] = $vl;
				$col = 'tg_other_options';
				$vl = json_encode($otheroptions);
			}
		}
		$rs = $wpdb->update('ci_test_groups', array($col => $vl), array('tg_ID' => $id));
	} else {
		$rs = $wpdb->update('ci_clinic', array($col => $vl), array('cID' => $id));
	}

	if ($rs === false) {
		$return['success'] = 0;
		$return['msg'] = 'Error. Please try again';
	}
	wp_send_json($return);
}




$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$doctors = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);
?>

<div class="wrap">
	<h2>Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="row">
			<div style="width:50%;float:left">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : '') ?> />
				</div>
				<div class="row">
					<label for="clinicpv1">Clinic ID in PV1.3 </label>
					<input type="checkbox" id="clinicpv1" value="1" name="options[clinicpv1]" <?php checked(isset($options['clinicpv1']) ? $options['clinicpv1'] : '') ?> />
				</div>
				<div class="row">
					<label for="mergeobr3">Merge 2 files to one by OBR3 </label>
					<input type="checkbox" id="mergeobr3" value="1" name="options[mergeobr3]" <?php checked(isset($options['mergeobr3']) ? $options['mergeobr3'] : '') ?> />
				</div>
				<div class="row">
					<label for="mergeobr2">Merge 2 files to one by OBR 2</label>
					<input type="checkbox" id="mergeobr2" value="1" name="options[mergeobr2]" <?php checked(isset($options['mergeobr2']) ? $options['mergeobr2'] : '') ?> />
				</div>
				<div class="row">
					<label for="sendpdf">Send PDF</label>
					<input type="checkbox" id="sendpdf" value="1" name="options[sendpdf]" <?php checked(isset($options['sendpdf']) ? $options['sendpdf'] : '') ?> />
				</div>
				<div class="row">
					<label for="sendpdf">Use CPT Code (in panel)</label>
					<input type="checkbox" id="sendpdf" value="1" name="options[use_cpt_code]" <?php checked(isset($options['use_cpt_code']) ? $options['use_cpt_code'] : '') ?> />
				</div>
				<div class="row">
					<label>Lab</label>
					<select name="options[lab_id]" require class="njs_select2" d>
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
					<label>Doctor</label>
					<select name="options[doc_id]"></select>
				</div>

				<div class="row">
					<label>MSH6 Code</label>
					<input type="text" name="options[msh6]" value="<?php echo isset($options['msh6']) ? $options['msh6'] : '19711' ?>" />
				</div>

				<div class="row">
					<label>NTE Message for igniteABX</label>
					<input type="text" name="options[nte_message]" value="<?php echo isset($options['nte_message']) ? $options['nte_message'] : '' ?>" />
				</div>

				<div class="row">
					<label for="disableobx8">Remove OBX.8 for igniteABX</label>
					<input type="checkbox" id="disableobx8" value="1" name="options[disableobx8]" <?php checked(isset($options['disableobx8']) ? $options['disableobx8'] : '') ?> />
				</div>

				<div class="row">
					---
				</div>

				<div class="row">
					<label>Host</label>
					<input type="text" name="options[host]" value="<?php echo isset($options['host']) ? $options['host'] : '' ?>" />
				</div>

				<div class="row">
					<label>Order - Username </label>
					<input type="text" name="options[username]" value="<?php echo isset($options['username']) ? $options['username'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Order - Password</label>
					<input type="password" name="options[password]" value="<?php echo isset($options['password']) ? $options['password'] : '' ?>" required />
				</div>
				<div class="row">
					---
				</div>

				<div class="row">
					<label>Results - Username </label>
					<input type="text" name="options[results_username]" value="<?php echo isset($options['results_username']) ? $options['results_username'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Results - Password</label>
					<input type="password" name="options[results_password]" value="<?php echo isset($options['results_password']) ? $options['results_password'] : '' ?>" required />
				</div>
			</div>
			<div style="width:49%;float:right">
				<div class="frow">
					<label>Notes</label>
					<textarea name="options[notes]" style="height:300px"><?php echo isset($options['notes']) ? esc_html($options['notes']) : '' ?></textarea>
				</div>
			</div>
		</div>


		<div class="frow row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>&width=1000">Test Connect Order</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect', 'type' => 'result')) ?>&width=1000">Test Connect Results</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'codes')) ?>&width=1000">Codes</a>
			<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_ids', 'trajax' => 1)) ?>">Clinic IDs</a> &nbsp;&nbsp;
			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>&width=1000">Test Upload Result</a>
			<?php } ?>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>&width=1000">Get Orders</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report')) ?>&width=1000">Send Reports</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'send_reports_by_date')) ?>&width=1000">Send Reports By Date</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'width' => 1200));
												if (isset($_REQUEST['debug'])) echo '&debug=1' ?>">Log</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php echo '/wp-content/log/ci_api_athena_intralab.txt' ?>">Log File</a> &nbsp;&nbsp;
			<?php
			$count = $wpdb->get_row("SELECT
								COUNT(*) AS count_reqs,

								SUM(EXISTS (
									SELECT 1
									FROM ci_reports_pcr r
									WHERE r.patient_id = ba.assen
									AND r.estatus = 'C'
								)) AS count_results,

								SUM(EXISTS (
									SELECT 1
									FROM ci_sync_api_orders o
									WHERE o.mo_mid = ba.mid
									AND o.mo_sent >= 1
								)) AS count_sent

							FROM ci_batches_pcr ba

							WHERE EXISTS (
								SELECT 1
								FROM ci_sync_api_orders o
								JOIN ci_sync_api_files fi
									ON fi.m_ID = o.mo_file_id
								AND fi.m_type = '{$client->api_id}'
								WHERE o.mo_mid = ba.mid
							);");
			echo '<br><br><br>';
			echo 'Reqs: ' . $count->count_reqs . '<br>';
			echo 'Results: ' . $count->count_results . '<br>';
			echo 'Sent: ' . $count->count_sent . '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled($api_arr['cron'][0], array($api_arr['cron'][1]));
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = file_exists(WP_CONTENT_DIR . '/log/ci_api_athena_intralab.txt') ? filemtime(WP_CONTENT_DIR . '/log/ci_api_athena_intralab.txt') : '';
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
	form .row label {
		width: 230px;
		text-align: right;
		padding-right: 10px;
	}

	input[type=checkbox],
	input[type=radio] {
		margin-top: 5px;
	}

	.resendinfo {
		background: #fff;
	}

	.resendinfo div {
		padding: 30px;
	}

	a.resend {
		color: red;
	}

	table .countnum {
		width: 40px;
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
		$('body').on('click', 'a.resend', function() {
			let $this = $(this);
			let $pr = $this.parents('tr');
			let $td = $this.parent();
			let $id = $pr.data('id');
			let $mid = $pr.data('mid');

			if ($this.hasClass('sending')) return false;
			$this.addClass('sending');
			$this.html('Sending...');

			let $count_col = $('td', $pr).length;

			$.ajax({
				data: {
					act: 'viewrshl7',
					act2: 'resend',
					trajax: 1,
					id: $id,
					mid: $mid
				},
				type: 'post',
				success: function(rs) {
					$this.removeClass('sending');
					$this.html('Resend');
					$pr.after('<tr><td class="resendinfo" colspan="' + $count_col + '"><div>' + rs + '</div></td></tr>');

				},
				error: function() {
					$this.removeClass('sending');
					$this.html('Resend');
					alert('Error. Please try again');
				}
			});
			return false;
		}).on('click', 'a.show_popup', function() {
			if ($(this).hasClass('seach_assen') || $(this).hasClass('resend')) return;
			var $url = $(this).attr('href');
			if ($(this).hasClass('inlineload') || $(this).parents('#TB_ajaxContent').length > 0) {
				$(this).html('Loading...');
				$.get($url, function(data) {
					$('#TB_ajaxContent').html(data);
				});
			} else {
				tb_remove();
				setTimeout(function() {
					tb_show('', $url);
				}, 500);
			}
			return false;
		}).on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + $s);
			}, 300);
			return false;
		}).on('change', 'select#sendre_status', function() {
			var $s = $('#sendre_status').val();
			var $url = $('#formfilter').data('url');
			setTimeout(function() {
				tb_show('', $url + '&status=' + $s);
			}, 300);
			return false;
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
	})
</script>