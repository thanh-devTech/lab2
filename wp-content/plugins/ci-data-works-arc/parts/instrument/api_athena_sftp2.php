<?php
global $wpdb, $current_role;

$log_dir = WP_CONTENT_DIR . '/log/athena_sftp2/';

$log_dir_adt_files = WP_CONTENT_DIR . '/log/athena_sftp2/';


include(TRCPDD_PATH . 'api/ci_api_hl7_athena_sftp2.php');
$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'4' => 'Inconclusive'
);


if (isset($_POST['options'])) {
	$options=$_POST['options'];
    $old_options = get_option('tr_hl7_athena_sftp2_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_hl7_athena_sftp2_st', $bkdata);

	update_option('tr_hl7_athena_sftp2_st', $options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_hl7_athena_sftp2_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_all_api',array('CI_Api_Hl7_Athena_Sftp2'));

	if ($options['enable']) {
		wp_schedule_event(time() + 1, '10min', 'cron_all_api', array('CI_Api_Hl7_Athena_Sftp2'));
	}
}
$options = get_option('tr_hl7_athena_sftp2_st', array());
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
	$client = new CI_Api_Hl7_Athena_Sftp2($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}


if ($act == 'test_connect') {
	try {

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : $client->getfolder($_REQUEST['account']);
		$client->connect();
		$client->login($_REQUEST['account']);
		echo 'logged in<br>';


		$files = $client->scan_dir($path);
		if ($path != '/') {
			$path .= '/';
		}

		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($path)), false) . '" class="show_popup button">Up</a> ';
		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($path)), false) . '" class="show_popup button">Reload</a>';
		echo '<br>';
		echo '<br>List files: ' . $path . '<br>';
		echo 'count:' . count($files) . '<br>';
		foreach ($files as $file) {
			if (strpos($file, '.') > 0) {
				//$r = $client->delete($path.$file);
				//var_dump($r);
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
		$client->login($_REQUEST['account']);
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
} else if ($act == 'test_send_patient') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login('adt');
		echo 'logged in<br>';
		$client->send_new_patients();
		echo str_replace("\n", '<br>', $client->log);
		$files = $client->scan_dir($client->getfolder('adt'));
		echo '<br>List files:<br>';
		foreach ($files as $file) {
			echo '<li>' . $file . '</li>';
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_changes') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login('dft');
		echo 'logged in<br>';
		$client->send_charges();
		echo str_replace("\n", '<br>', $client->log);
		$files = $client->scan_dir($client->getfolder('dft'));
		echo '<br>List files:<br>';
		foreach ($files as $file) {
			echo '<li>' . $file . '</li>';
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_siu') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login('siu');
		echo 'logged in<br>';
		$client->sync_siu();
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_upload') {
	try {
		$file_path = $client->getfolder($_REQUEST['account']) . '/test.txt';
		$client->debug = true;
		$client->connect();
		$client->login($_REQUEST['account']);
		echo 'logged in<br>';
		echo 'Uploading file : test.txt<br>';
		$tmp_path = tempnam('/tmp', 'test');
		file_put_contents($tmp_path, 'test content');

		$client->uploadFile($tmp_path, $file_path);
		echo 'Uploaded<br>';
		$files = $client->scan_dir($client->getfolder($_REQUEST['account']));
		echo '<br>List files:<br>';
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

	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.doc,
                                re.id,
                                re.estatus,
                                o.mo_ID,
                                o.mo_status,
                                o.mo_test_ID,
                                o.mo_set_ID,
                                o.mo_peg_ID
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = 'pcr'
                            where o.mo_file_id='{$client->api_num}' 
                            order by mo_ID desc
                            limit 500");
?>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:60px">#</th>
				<th style="width:60px">#</th>
				<th>Accession</th>
				<th style="width:60px">Resulted</th>
				<th style="width:80px;">Sent ADT</th>
				<th style="width:150px;">Sent ADT Date</th>
				<th style="width:80px;">Sent DFT</th>
				<th style="width:150px;">Sent DFT Date</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php

			foreach ($rows as $i => $row) {
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>" data-mid="<?php echo $row->mid ?>">
					<td><?php echo $i + 1 ?></td>
					<td><?php echo $row->mid ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid ?>&short=1"><?php echo $row->assen ?></a></td>
					<td><a href="?page=pcr_results&act=edit&id=<?php echo $row->id;
																if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
							<?php echo $row->estatus == 'C' ? 'Yes' : '.' ?>
						</a>
					</td>
					<td><?php echo $row->mo_status > 0 ? 'yes' : '' ?></td>
					<td><?php echo $row->mo_peg_ID ?></td>
					<td><?php echo $row->mo_test_ID > 0 ? 'yes' : '' ?></td>
					<td><?php echo $row->mo_set_ID ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->mo_ID), false) . '">View Hl7</a>';
						?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
<?php
	exit;
} else if ($act == 'viewrshl7') {
	try {
		$hl7_parser = new CI_HL7_Parser();

		$row = $wpdb->get_row("select o.*,
		                        ba.assen
		                     from ci_sync_api_orders o 
		                      join ci_batches_pcr ba ON ba.mid = o.mo_mid
		                     where mo_ID = {$_REQUEST['fid']}");

		$baseurl = Tr_Base_Class_V6::link(array('act2' => ''), false);
		if ($_GET['act2'] == 'rebuildadt') {
			$client->send_new_patients($row->assen);
			//wp_redirect($baseurl);
			//exit;
		} else if ($_GET['act2'] == 'resend') {
			$client->debug = true;
			$client->connect();
			$client->login('adt');
			echo 'logged in<br>';
			$client->send_new_patients($row->assen);
			echo $client->log;
			$files = $client->scan_dir($client->getfolder('adt'));
			echo '<br>List files:<br>';
			foreach ($files as $file) {
				echo '<li>' . $file . '</li>';
			}
			exit;
		} else if ($_GET['act2'] == 'rebuildadtupdate') {
			$client->send_update_patients($row->assen);
		} else if ($_GET['act2'] == 'resendupdate') {
			$client->debug = true;
			$client->connect();
			$client->login('adt');
			echo 'logged in<br>';
			$client->send_update_patients($row->assen);
			echo $client->log;
			$files = $client->scan_dir($client->getfolder('adt'));
			echo '<br>List files:<br>';
			foreach ($files as $file) {
				echo '<li>' . $file . '</li>';
			}
			exit;
		} else if ($_GET['act2'] == 'rebuild_dft') {
			$client->send_charges($row->assen);
		} else if ($_GET['act2'] == 'resend_dft') {
			$client->debug = true;
			$client->connect();
			$client->login('dft');
			echo 'logged in<br>';
			$client->send_charges($row->assen);
			echo $client->log;
			$files = $client->scan_dir($client->getfolder('dft'));
			echo '<br>List files:<br>';
			foreach ($files as $file) {
				echo '<li>' . $file . '</li>';
			}
			exit;
		}

		$filename = $row->assen . '.hl7';
		$local_file = $log_dir_adt_files . $filename;
		if (file_exists($local_file)) {
			echo '<br>ADT new Patient.:' . $local_file;
			$linkbuild = Tr_Base_Class_V6::link(array('act2' => 'rebuildadt'), false);
			$linksend = Tr_Base_Class_V6::link(array('act2' => 'resend'), false);
			echo ' <a  href="' . $linkbuild . '">build</a> ';
			echo ' <a  href="' . $linksend . '">send</a> ';
			$hl7 = file_get_contents($local_file);
			echo '<textarea style="width:2000px;height:200px">';
			echo esc_textarea($hl7);
			echo '</textarea>';
			if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
				$parsed = $hl7_parser->parse_hl7($hl7);
				if (!empty($parsed) && !isset($parsed['ERROR'])) {
					echo $hl7_parser->render_html($parsed);
				}
			}
		} else {
			echo 'File not exist';
		}

		$local_file = $log_dir_adt_files . 'update_' . $filename;
		echo '<br>ADT Update Patient.:' . $local_file;
		$linkbuild = Tr_Base_Class_V6::link(array('act2' => 'rebuildadtupdate'), false);
		$linksend = Tr_Base_Class_V6::link(array('act2' => 'resendupdate'), false);
		echo ' <a  href="' . $linkbuild . '">build</a> ';
		
		if (file_exists($local_file)) {
			echo ' <a  href="' . $linksend . '">send</a> ';
			$hl7 = file_get_contents($local_file);
			echo '<textarea style="width:2000px;height:200px">';
			echo esc_textarea($hl7);
			echo '</textarea>';
			if (!empty($hl7) && strpos(ltrim($hl7), 'MSH|') === 0) {
				$parsed = $hl7_parser->parse_hl7($hl7);
				if (!empty($parsed) && !isset($parsed['ERROR'])) {
					echo $hl7_parser->render_html($parsed);
				}
			}
		}


		$local_file = $log_dir_adt_files  . $row->assen . '_dft.hl7';
		echo '<br>DFT file.:' . $local_file;
		$linkbuild = Tr_Base_Class_V6::link(array('act2' => 'rebuild_dft'), false);
		$linksend = Tr_Base_Class_V6::link(array('act2' => 'resend_dft'), false);
		echo ' <a  href="' . $linkbuild . '">build</a> ';
		
		if (file_exists($local_file)) {
			echo ' <a  href="' . $linksend . '">send</a> ';
			$hl7 = file_get_contents($local_file);
			echo '<textarea style="width:2000px;height:200px">';
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
	<h2>Athena sftp 2 Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="row">
			<div style="width:50%;float:left">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : '') ?> />
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
					<select name="options[cli_id]" required class="njs_select2">
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
					<label>Host</label>
					<input type="text" name="options[host]" value="<?php echo isset($options['host']) ? $options['host'] : '' ?>" />
				</div>
				<?php
				foreach ($client->accounts as $k => $txt) {
					$usernamekey = $k . '_username';
					$passwordkey = $k . '_password';
					$folderkey = $k . '_folder';
				?>
					<div class="row">
						---
					</div>



					<div class="row">
						<label><?php echo $txt ?> - Username</label>
						<input type="text" name="options[<?php echo $usernamekey?>]" value="<?php echo isset($options[$usernamekey]) ? $options[$usernamekey] : '' ?>" required />
					</div>
					<div class="row">
						<label><?php echo $txt ?> - Password</label>
						<input type="password" name="options[<?php echo $passwordkey?>]" value="<?php echo isset($options[$passwordkey]) ? $options[$passwordkey] : '' ?>" required />
					</div>
					<div class="row">
						<label><?php echo $txt ?> - Folder</label>
						<input type="text" name="options[<?php echo $folderkey?>]" value="<?php echo isset($options[$folderkey]) ? $options[$folderkey] : '' ?>" required />
					</div>

				<?php
				}
				?>



			</div>
			<div style="width:49%;float:right">
				<div class="frow">
					<label>Notes</label><br>
					<textarea name="options[notes]" style="height:300px;width:98%"><?php echo isset($options['notes']) ? esc_html($options['notes']) : '' ?></textarea>
				</div>
			</div>
		</div>


		<div class="frow row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<?php
			foreach($client->accounts as $k => $txt){?>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect','account'=>$k)) ?>&width=1000">Test <?php echo $txt?></a> &nbsp;&nbsp;
			<?php }?>
		
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_send_patient')) ?>&width=1000">Test send patient</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_changes')) ?>&width=1000">Test Sync Charges</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_siu')) ?>&width=1000">Test Sync SIU</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Log</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php echo '/wp-content/log/ci_api_hl7_athena_sftp2.txt' ?>">Log File</a> &nbsp;&nbsp;
			<?php
			$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_id is not null,1,0)) as count_sent,
                            SUM(IF(o.mo_test_ID=1,1,0)) as count_sent_dft
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_file_id='{$client->api_num}'
                            where ba.cli_ID = {$client->clinic_id}");
			echo '<br>';
			echo 'Reqs: ' . $count->count_reqs . '<br>';
			echo 'Results: ' . $count->count_results . '<br>';
			echo 'Sent ADT: ' . $count->count_sent . '<br>';
			echo 'Sent DFT: ' . $count->count_sent_dft . '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled('cron_api_hl7_athena_sftp2');
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = file_exists(WP_CONTENT_DIR . '/log/ci_api_hl7_athena_sftp2.txt') ? filemtime(WP_CONTENT_DIR . '/log/ci_api_hl7_athena_sftp2.txt') : '';
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
	var $doctors = <?php echo json_encode($doctors) ?>;
	var $selected_doc = <?php echo intval($options['doc_id']) ?>;
	jQuery(function($) {
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
		$('body').on('click', 'a.show_popup', function() {
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
		})
	})
</script>
