<?php
global $wpdb, $current_role;

$log_dir = WP_CONTENT_DIR . '/log/evident/';
include(TRCPDD_PATH . 'api/ci_api_evident.php');

$log_dir_orders = $log_dir . 'orders/';


if (isset($_POST['options'])) {
	$options = $_POST['options'];
	$options['enable'] = !empty($options['enable']) ? 1 : 0;
	$old_options = get_option('tr_evident_st', array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_tr_evident_st', $bkdata);

	update_option('tr_evident_st', $_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_evident_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_evident');

	if ($options['enable']) {
		wp_schedule_event(time() + 1, '10min', 'cron_api_evident');
	}
}
$options = array_merge(array(
	'enable' => 0,
	'lab_id' => 0,
	'clinic_id' => '',
	'doc_id' => '',
	'testname' => '',
	'host' => '',
	'username' => '',
	'password' => '',
	'order_folder' => '',
	'result_folder' => '',
	'notes' => '',
), get_option('tr_evident_st', array()));



$dir        = dirname(ABSPATH) . '/evident';
$dir = str_replace('/usr/local/', '/mnt/data/', $dir);
$order_dir  = $dir . $options['order_folder'];
$result_dir  = $dir . $options['result_folder'];
$order_log_dir = $dir . '/archive/';

try {
	$client = new CI_Api_Evident($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}

if (isset($_REQUEST['set_report'])) {
	$wpdb->update('ci_reports_pcr', array('patient_id' => $_REQUEST['set_report']), array('id' => 2457));
}

if ($act == 'test_connect') {
	try {
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';
		if (!empty($_GET['pathdelete'])) {

			$client->delete($_GET['pathdelete']);
			$path = dirname($_GET['pathdelete']);
		}


		$files = $client->scan_dir($path);
		if ($path != '/') {
			$path .= '/';
		}
		$path = str_replace('//', '/', $path);

		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($path)), false) . '" class="show_popup button">Up</a> ';
		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($path)), false) . '" class="show_popup button">Reload</a>';
		echo '<br>';
		echo '<br>List files: ' . $path . '<br>';
		foreach ($files as $file) {
			if ($file == '${filename}') {
				$client->delete($path . $file);
			} else if (strpos($file, '.') > 0) {
				if (isset($_REQUEST['delete'])) {
					$client->delete($path . $file);
				}
				if (stripos($file, '.pdf') > 0) {
					echo '<li><a target=_blank href="' . Tr_Base_Class_V6::link(array('act' => 'readfile', 'trajax' => 1, 'path' => $path . $file), false) . '">' . $file . '</a></li>';
				} else {
					echo '<li><a  href="' . Tr_Base_Class_V6::link(array('act' => 'readfile', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a></li>';
				}
			} else {
				echo '<li><a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a></li>';
			}
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	echo 'count: ' . count($files);
	Tr_Session_Class::show();
	exit;
} else if ($act == 'readfile') {
	try {
		$log = '';
		$client->connect();
		$client->login();
		$log .= 'logged in<br>';

		$tmp_path = tempnam('/tmp', 'test');
		$client->receiveFile($_REQUEST['path'], $tmp_path);
		if (stripos($_REQUEST['path'], '.pdf') > 0) {
			header('Content-Type: application/pdf');
			readfile($tmp_path);
			unlink($tmp_path);
			exit;
		}

		$log .= '<a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($_REQUEST['path'])), false) . '" class="show_popup">Up</a>';
		$log .= '<br>';
		$log .= 'reading file ' . $_REQUEST['path'] . '<br><br>';
		$log .= '<a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'pathdelete' => $_REQUEST['path']), false) . '" class="show_popup">Delete</a>';
		$log .= '<textarea style="width:100%;height:400px">';
		$log .= file_get_contents($tmp_path);
		$log .= '</textarea>';
		echo $log;
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
		$files = $client->scan_dir($options['result_folder']);
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
		$file_path = $options['result_folder'] . '/test.txt';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		echo 'Uploading file : test.txt<br>';
		$tmp_path = tempnam('/tmp', 'test');
		file_put_contents($tmp_path, 'test content');

		$client->uploadFile($tmp_path, $file_path);
		echo 'Uploaded<br>';
		$files = $client->scan_dir($options['result_folder']);
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
	$where = " fi.m_type='evident' ";
	if (isset($_GET['debug'])) {
		$joins .= " join ci_patients pa ON pa.pa_ID = ba.patient_id ";
		$joins .= " join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
		$fields .= ", pa.pa_first, pa.pa_last, ins.ins_first, ins.ins_last, ins.ins_ID ";
	}
	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (ba.assen like %s or ba.patient_name like %s) ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%'
		);
	}

	$row_per_page = 200;

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
		ba.dagnostic_codes1,
		re.covid_pos,
		re.id,
		re.estatus,
		cli.*,
		o.mo_status,
		o.mo_ID,
		o.other_info,
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
	order by mo_ID desc
	limit {$offset},{$row_per_page}");
	//var_dump($sql);
	//var_dump($wpdb->last_error);

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
				<th>Accession</th>
				<th>Patient</th>
				<th>Clinic</th>
				<th>ID</th>
				<th>Doctor</th>
				<th style="width:60px">Resulted</th>
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
				if ($code_ordered != $row->cli_experity_id) {
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
				<tr class="childp" data-mid="<?php echo $row->mid ?>" data-id="<?php echo $row->id ?>">
					<td><?php echo $row->mid ?></td>
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
			$wpdb->query("update ci_sync_api_orders o 
							set mo_status = 0 
							where o.mo_mid = {$mid}  ");
			$rs = $client->sync_reports($id);
			if (!$rs) {
				$return['status'] = 'error';
			} else {
				$files = $client->scan_dir($options['result_folder']);
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
		                        ba.assen,
								re.id
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
							  left join ci_reports_pcr re ON re.patient_id = ba.assen
		                     where m_ID = {$_REQUEST['fid']}");


		if ($_GET['act2'] == 'resend') {
			$client->debug = true;
			$client->connect();
			$client->login($options['results_username'], $options['results_password']);
			echo 'logged in<br>';
			$client->sync_reports($row->id);
			echo str_replace("\n", '<br>', $client->log);
			$files = $client->scan_dir($options['result_folder']);
			echo '<br>List files in Results folder:<br>';
			foreach ($files as $file) {
				echo '<li>' . $file . '</li>';
			}
			exit;
		} else if ($_GET['act2'] == 'rebuild' && $row->id > 0) {
			$client->sync_reports($row->id);
			echo str_replace("\n", '<br>', $client->log);
			echo 'builded';
		}

		$local_file = $log_dir_orders . basename($row->m_file);
		if (file_exists($local_file)) {

			$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
			echo 'order file.: ' . basename($local_file);
			echo ' <a href="' . $reimportlink . '">reimport</a>';
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

			$file_path = $options['order_folder'] . '/archive/' . basename($row->m_file);
			$client->connect();
			if ($client->login()) {
				$tmp_path = tempnam('/tmp', 'test');
				try {
					$client->receiveFile($file_path, $tmp_path);
				} catch (Exception $e) {
				}
				echo 'order file.:' . basename($local_file);
				$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
				echo ' <a href="' . $reimportlink . '">reimport</a>';
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

		if (file_exists($log_dir . $rs_file)) {

			$linkbuild = Tr_Base_Class_V6::link(array('act2' => 'rebuild'), false);
			$linksend = Tr_Base_Class_V6::link(array('act2' => 'resend'), false);
			echo 'output file:' . $rs_file;
			echo ' <a  href="' . $linkbuild . '">build</a> ';
			echo ' <a  href="' . $linksend . '">send</a> ';
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
} else if ($act == 'reimport') {

	try {
		$client->connect();
		$client->login();

		$dbfile = $wpdb->get_row("select f.*,
		                        ba.assen
		                     from ci_sync_api_files f
		                      join ci_sync_api_orders o ON o.mo_file_id = f.m_ID
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
		                     where m_ID = {$_REQUEST['fid']}");

		$file_path = '/archive/' . basename($dbfile->m_file);
		$client->import_req_file($dbfile, $file_path);
		echo 'done';
	} catch (Exception $e) {
		echo $e;
	}
	echo $client->log;

	exit;
}
if ($act == 'install') {

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

groupadd evident
useradd -g evident -d <?php echo $dir ?> -s /sbin/nologin evident
passwd evident
chmod 0755 <?php echo $dir ?>


chown -R root:root <?php echo $dir ?>

change file: /etc/ssh/sshd_config:

comment this line: Subsystem	sftp	/usr/libexec/openssh/sftp-server

and add this to end:

Match Group evident
ChrootDirectory <?php echo $dir ?>

ForceCommand internal-sftp
PasswordAuthentication yes
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
$docs = $wpdb->get_results("select * from ci_doc where doc_first!='' order by doc_first asc");
?>

<div class="wrap">
	<h2>Evident Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="frow">
			<div class="col2">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable']) ?> />
				</div>
				<div class="row">
					<label>Lab</label>
					<select name="options[lab_id]" required class="njs_select2">
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
					<select name="options[clinic_id]" class="njs_select2">
						<option value="">Select</option>
						<?php
						foreach ($clinics as $clinic) {
							$selected = $clinic->cID == $options['clinic_id'] ? 'selected' : '';
							echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
						}
						?>
					</select>
				</div>
				<div class="row">
					<label>Doctor</label>
					<select name="options[doc_id]" class="njs_select2">
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
					<label>Test Name</label>
					<select name="options[testname]">
						<option value="">By Name</option>
						<?php
						foreach (array('abrev' => 'Abbreviation 1', 'abrev2' => 'Abbreviation 2') as $k => $txt) {
							$selected = $k == $options['testname'] ? 'selected' : '';
							echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
						}
						?>
					</select>
				</div>

				<div class="row">
					<label>Host</label>
					<input type="text" name="options[host]" value="<?php echo $options['host'] ?>" required />
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
					<label>Orders Folder</label>
					<input type="text" name="options[order_folder]" value="<?php echo $options['order_folder'] ?>" required />
				</div>
				<div class="row">
					<label>Results Folder</label>
					<input type="text" name="options[result_folder]" value="<?php echo $options['result_folder'] ?>" required />
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
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>">Upload Test File</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>">Test Sync Orders</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report')) ?>">Test Sync Reports</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'width' => '1000')) ?>">Log</a> &nbsp;&nbsp;
			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install')); ?>&width=1000">Install</a> &nbsp;&nbsp;
			<?php } ?>

		</div>
		<div class="row">
			<?php
			$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='evident'");
			echo '<br>';
			echo 'Reqs: ' . $count->count_reqs . '<br>';
			echo 'Results: ' . $count->count_results . '<br>';
			echo 'Sent: ' . $count->count_sent . '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled('cron_api_evident');
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = file_exists(WP_CONTENT_DIR . '/log/log_api_evident.txt') ? filemtime(WP_CONTENT_DIR . '/log/log_api_evident.txt') : '';
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
			var $url = $(this).attr('href');
			tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 500);
			return false;
		}).on('click', 'a.resend', function() {
			if (!confirm('Do you want send?')) return false;
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
		})
	})
</script>