<?php
global $wpdb, $current_role;

$log_dir = WP_CONTENT_DIR . '/log/seacoast/';
$log_dir_orders = WP_CONTENT_DIR . '/log/seacoast_orders/';


if ($act == 'testcron') {
	echo 'testcron: ';
	define('DOING_CRON', true);
	include_once(TRCPDD_PATH . 'inc/crons.php');
	do_action('cron_all_api', 'CI_Api_sftp_Seacoast');
	exit;
}

$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'3' => 'Inconclusive',
	'4' => 'Inconclusive',
);

if (isset($_POST['options'])) {
	$options = $_POST['options'];
	$options['enable'] = !empty($options['enable']) ? 1 : 0;
	if (empty($options['cron'])) {
		$options['cron'] = 10;
	}
	
    $old_options = get_option('tr_sftp_seacoast_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_sftp_seacoast_st', $bkdata);

	update_option('tr_sftp_seacoast_st', $options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_sftp_seacoast_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_all_api', array('CI_Api_sftp_Seacoast'));

	if ($options['enable']) {
		wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_all_api', array('CI_Api_sftp_Seacoast'));
	}
}
$options = array_merge(array(
	'enable' => 0,
	'cron' => 10,
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
), get_option('tr_sftp_seacoast_st', array()));


$dir        = ABSPATH . 'seacoast';
$dir = str_replace('/usr/local/', '/mnt/data/', $dir);
$order_dir  = $dir . $options['order_folder'];
$result_dir  = $dir . $options['result_folder'];
$order_log_dir = $dir . '/archive/';



if (!file_exists($order_dir)) {
	wp_mkdir_p($order_dir);
}
if (!file_exists($result_dir)) {
	wp_mkdir_p($result_dir);
}
if (!file_exists($order_log_dir)) {
	wp_mkdir_p($order_log_dir);
}

try {
	include_once(TRCPDD_PATH . 'api/ci_api_sftp_seacoast.php');
	$client = new CI_Api_sftp_Seacoast($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}

if ($act == 'test_connect') {
	try {

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';

		$client->connect();
		$client->login();
		echo 'logged in<br>';

		if (!empty($_GET['pathdelete'])) {

			$rss = $client->delete($_GET['pathdelete']);
			$path = dirname($_GET['pathdelete']);
		}


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
	$where = " fi.m_type='seacoast' ";
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

	$row_per_page = 200;
	if (isset($_GET['get_codes'])) {
		$row_per_page = 9999999999;
	}
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(distinct(mid)) as c from ci_batches_pcr ba
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
                                ba.dagnostic_codes1,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                cli.*,
                                o.mo_status,
                                o.mo_ID,
                                o.other_info,
                                o.mo_test_ID,
                                fi.m_ID,
                                fi.m_time
                                {$fields}
                            from ci_batches_pcr ba
                            left join ci_clinic cli ON cli.cID = ba.cli_ID
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            {$joins}
                            where {$where} 
							group by ba.mid
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
				$other_info = json_decode($row->other_info, true);
				$code_ordered = $other_info['MSH4'];

				$styles = '';
				if ($code_ordered != $row->cli_seacoast_id) {
					$styles .= 'style="color:red"';
					if (!in_array($code_ordered, $codes)) {
						$codes[] = $code_ordered;
					}
				}
				if (isset($_GET['get_codes'])) {
					continue;
				}


				if ($row->paid != $client->api_id) {
					$wpdb->update('ci_batches_pcr', array('paid' => $client->api_id), array('mid' => $row->mid));
				}
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
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
						if (!is_null($row->covid_pos)) {
							echo $covid_status[$row->covid_pos];
						}
						?>
					</td>
					<td><a class='resend' href="#"><?php echo $row->mo_status == 1 ? 'Yes' : '' ?></a></td>
					<td><?php echo date('Y-m-d H:i:s', $row->m_time) ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->m_ID), false) . '">View Hl7</a>';
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
			$wpdb->query("update ci_sync_api_orders o set mo_status = 0 where
		                    o.mo_mid = {$mid}  ");
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
} else if ($act == 'viewrshl7') {
	try {
		$hl7_parser = new CI_HL7_Parser();
		$row = $wpdb->get_row("select f.*,
		                        ba.assen
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
		                     where m_ID = {$_REQUEST['fid']}");

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

		//$rs_file = $row->assen.'.hl7';
		$rs_file = basename($row->m_file);
		//var_dump($rs_file);
		if (file_exists($log_dir . $rs_file)) {
			echo 'output file:' . $rs_file;
			$rebuildlink = Tr_Base_Class_V6::link(array('act' => 'rebuild'), false);
			$pdf_link 	 = Tr_Base_Class_V6::link(array('act' => 'viewpdf'), false);
			echo '   <a href="' . $rebuildlink . '">rebuild</a>  ';
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
} else if ($act == 'list_ids') {

	$list = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cast(cli_seacoast_id as unsigned) asc");
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
				<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="cli_seacoast_id" value="<?php echo $row->cli_seacoast_id ?>" /></td>
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

	$rs = $wpdb->update('ci_clinic', array($col => $vl), array('cID' => $id));

	if ($rs === false) {
		$return['success'] = 0;
		$return['msg'] = 'Error. Please try again';
	}
	wp_send_json($return);
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
} else if ($act == 'rebuild') {

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
		                     where m_ID = {$_REQUEST['fid']}");

	try {

		$client->debug = true;
		$client->sync_reports($dbfile->id);
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	wp_redirect(Tr_Base_Class_V6::link(array('act' => 'viewrshl7'), false));
	exit;
} else if ($act == 'viewpdf') {
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
		                     where m_ID = {$_REQUEST['fid']}");
	$rs_file = basename($dbfile->m_file);
	//var_dump($rs_file);
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
} else if ($act == 'install') {



?>
	<h2>Install</h2>
	<li>Create sftp to folder: <?php echo $dir ?></li>
	<li>Create folders: <?php echo $order_dir ?> and <?php echo $order_log_dir ?></li>

	<textarea style="width:100%;height:500px">
mkdir <?php echo $dir ?>

mkdir <?php echo $order_dir ?>

mkdir <?php echo $result_dir ?>

mkdir <?php echo $order_log_dir ?>

chmod 0777 <?php echo $order_dir ?>

chmod 0777 <?php echo $result_dir ?>

chmod 0777 <?php echo $order_log_dir ?>

groupadd seacoast
useradd -g seacoast -d <?php echo $dir ?> -s /sbin/nologin seacoast
passwd seacoast
chmod 0755 <?php echo $dir ?>


chown -R root:root <?php echo $dir ?>

change file: /etc/ssh/sshd_config:

comment this line: Subsystem	sftp	/usr/libexec/openssh/sftp-server

and add this to end:

Match Group seacoast
ChrootDirectory <?php echo $dir ?>

ForceCommand internal-sftp
X11Forwarding no
AllowTcpForwarding no

then restart ssh: service sshd restart

fix issue:
chown root:nginx /mnt/data/vhosts
chmod 0755 /mnt/data/vhosts

</textarea>
<?php
	exit;
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
	<h2>Seacoast Settings</h2>
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
					<label>Host</label>
					<input type="text" name="options[host]" value="<?php echo isset($options['host']) ? $options['host'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Port</label>
					<input type="text" name="options[port]" value="<?php echo isset($options['port']) ? $options['port'] : '' ?>" required />
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
					<label>Orders Folder</label>
					<input type="text" name="options[order_folder]" value="<?php echo isset($options['order_folder']) ? $options['order_folder'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Results Folder</label>
					<input type="text" name="options[result_folder]" value="<?php echo isset($options['result_folder']) ? $options['result_folder'] : '' ?>" required />
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
			<div style="width:49%;float:right">
				<div class="frow">
					<label>Notes</label>
					<textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes']) ?></textarea>
				</div>
			</div>
		</div>

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>">Test Connect</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>">Test Upload Result</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>">Test Sync Orders</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report')) ?>">Test Sync Reports</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'clean_archive')) ?>">Clean Archive</a> &nbsp;&nbsp;
			<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_ids', 'trajax' => 1)) ?>&width=1000">Clinic IDs</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200">Log</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php echo '/wp-content/log/ci_api_sftp_seacoast.txt' ?>">Log File</a>
			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install')); ?>&width=1000">Install</a> &nbsp;&nbsp;
			<?php } ?>
		</div>

		<?php
		$today = date_i18n('Y-m-d');
		$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='{$client->log_file_slug}'
                            ");

		$reqs_today = $wpdb->get_var("select count(*) as count_reqs
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='{$client->log_file_slug}'
                            and ba.batch_created>='{$today}' 
                            and ba.batch_created <= '{$today} 23:59:59'
                            ");

		$count_today = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='{$client->log_file_slug}' 
                            and re.approve_date >='{$today}' 
                            and re.approve_date  <= '{$today} 23:59:59'
                            ");

		echo '<br>';
		echo 'Total Reqs: ' . $count->count_reqs . '<br>';
		echo 'Total Results: ' . $count->count_results . '<br>';
		echo 'Total Sent: ' . $count->count_sent . '<br>';

		echo 'Today Reqs: ' . $reqs_today . '<br>';
		echo 'Today Results: ' . $count_today->count_results . '<br>';
		echo 'Today Sent: ' . $count_today->count_sent . '<br>';

		if ($count->count_sent < $count->count_results) {
			$missing = $wpdb->get_col("select ba.assen
                            from ci_batches_pcr ba
                            join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
                            jOIN ci_clinic cli ON cli.cID = ba.cli_ID
                            where fi.m_type='{$client->log_file_slug}' 
                            and re.id is not null 
                            and o.mo_status= 0 ");
			echo 'Left send: ' . implode(', ', $missing) . '<br>';
		}
		echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

		$next_cron = wp_next_scheduled('cron_all_api', array('CI_Api_sftp_Seacoast'));
		$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;


		$last_cron = file_exists(WP_CONTENT_DIR . '/log/ci_api_sftp_seacoast.txt') ? filemtime(WP_CONTENT_DIR . '/log/ci_api_sftp_seacoast.txt') : 0;
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
			wp_clear_scheduled_hook('cron_all_api', array('CI_Api_sftp_Seacoast'));
			wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_all_api', array('CI_Api_sftp_Seacoast'));
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
			$.ajax({
				data: {
					trajax: 1,
					act: 'send_result',
					id: $id
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
