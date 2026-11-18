<?php
global $wpdb, $current_role;

$log_dir 	= dirname(ABSPATH) . '/ellkay_sftp';
$resultlog = WP_CONTENT_DIR . '/log/hl7_ellkay/';

include(TRCPDD_PATH . 'api/ci_api_hl7_ellkay.php');
$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'3' => 'Inconclusive',
	'4' => 'Inconclusive',
);


if (isset($_POST['options'])) {
    $options=$_POST['options'];
    $old_options = get_option('tr_hl7_ellkay_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_hl7_ellkay_st', $bkdata);

	update_option('tr_hl7_ellkay_st', $_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_hl7_ellkay_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_hl7_ellkay');
	wp_clear_scheduled_hook($define_apis['api_ellkay']['cron'][0], array($define_apis['api_ellkay']['cron'][1]));

	if ($options['enable']) {
		wp_schedule_event(time() + 1, '10min', $define_apis['api_ellkay']['cron'][0], array($define_apis['api_ellkay']['cron'][1]));
	}
}
$options = get_option('tr_hl7_ellkay_st', array());




try {
	$client = new CI_Api_Hl7_ellkay($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}


if ($act == 'test_sync') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		if (isset($_REQUEST['reset'])) {
			$archive_folder = $client->order_folder . '/archive/';
			$files = $client->scan_dir($archive_folder);

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
} else if ($act == 'send_result') {
	$return = array('status' => 'ok', 'msg' => '');

	try {
		$id = $_REQUEST['id'];
		$client->debug = true;
		$client->connect();
		if ($client->login()) {
			$client->sync_reports($id);
		}
	} catch (Exception $e) {
		$return['status'] = 'error';
		$return['msg'] = $e->getMessage();
	}
	wp_send_json($return);
	exit;
}

if (!empty($act)) {
	include(trpdd_get_file_path('instrument', 'sftp_actions'));
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
	<h2>ellkay Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div style="width:50%;float:left">
			<div class="row">
				<label for="for_enable">Enable </label>
				<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : 0) ?> />
			</div>
			<div class="row">
				<label>Lab</label>
				<select name="options[lab_id]" required>
					<?php
					foreach ($labs as $lab) {
						$selected = isset($options['lab_id']) && $lab->lab_ID == $options['lab_id'] ? 'selected' : '';
						echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
					}
					?>
				</select>
			</div>
			<div class="row">
				<label>Clinic</label>
				<select name="options[cli_id]" required>
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
				<select name="options[doc_id]" required></select>
			</div>
			<div class="row">
				<label>Username</label>
				<input type="text" name="options[host]" value="<?php echo isset($options['host']) ? $options['host'] : '' ?>" placeholder="Leave empty for default" />
			</div>
			<div class="row">
				<label>Port</label>
				<input type="text" name="options[port]" value="<?php echo isset($options['port']) ? $options['port'] : '' ?>" placeholder="Leave empty for default" />
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
				<label>Order folder</label>
				<input type="text" name="options[order_folder]" value="<?php echo isset($options['order_folder']) ? $options['order_folder'] : '' ?>" placeholder="Leave empty for default" />
			</div>
			<div class="row">
				<label>Result folder</label>
				<input type="text" name="options[result_folder]" value="<?php echo isset($options['result_folder']) ? $options['result_folder'] : '' ?>" placeholder="Leave empty for default" />
			</div>

			<div class="row">
				<label>OBR4.1</label>
				<input type="text" name="options[obr41]" value="<?php echo isset($options['obr41']) ? $options['obr41'] : '' ?>" />
			</div>
			<div class="row">
				<label>OBR4.2</label>
				<input type="text" name="options[obr42]" value="<?php echo isset($options['obr42']) ? $options['obr42'] : '' ?>" />
			</div>
		</div>
		<div style="width:50%;float:right">
			<div class="frow">
				<label>Notes</label>
				<textarea name="options[notes]" style="height:300px"><?php echo isset($options['notes']) ? esc_html($options['notes']) : '' ?></textarea>
			</div>
		</div>


		<div class="row frow">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>&width=1000">Test Connect</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>&width=1000">Test Upload Result</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>&width=1000">Test Sync Orders</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report')) ?>&width=1000">Test Sync Reports</a> &nbsp;&nbsp;
			<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_ids', 'trajax' => 1)) ?>&width=1000">Clinic IDs</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Log</a> &nbsp;&nbsp;


			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install')); ?>&width=1000">Install</a> &nbsp;&nbsp;
			<?php } ?>
			<?php
			$clids = $wpdb->get_col("select cID from ci_clinic_meta where meta_key='{$client->clinic_send_result_field}' and meta_value='1' ");

			if (empty($clids)) {
				$clids = [0];
			}
			$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(fi.m_type='hl7_ellkay',1,0)) as count_received,
                            SUM(IF(o.mo_status in (1,2) ,1,0)) as count_sent
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid
                           	left join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where (
							fi.m_type='hl7_ellkay' 
							or (fi.m_ID is null and ba.batch_id>0 and ba.cli_ID in (" . implode(',', $clids) . "))
							)");

			if (isset($_GET['debug'])) {
				echo '<pre>';
				print_r($wpdb->last_query);
				echo '</pre>';
			}
			echo '<br>';
			echo 'Reqs: ' . $count->count_reqs . '<br>';
			echo 'Results: ' . $count->count_results . '<br>';
			echo 'Received: ' . $count->count_received . '<br>';
			echo 'Sent: ' . $count->count_sent . '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled('cron_api_hl7_ellkay');
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = file_exists(WP_CONTENT_DIR . '/log/log_api_ellkay.txt') ? filemtime(WP_CONTENT_DIR . '/log/log_api_ellkay.txt') : 0;
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
						alert('Sent successfully');
					} else {
						alert($rs.msg);
					}
				}
			});
			return false;
		}).on('keypress', '#idstbl input.editfield', function(e) {
			var code = e.keyCode || e.which;
			if (code == 13) {
				save_ids_data($(this));
				return false;
			}
		}).on('change', '#idstbl input.editfield', function() {
			save_ids_data($(this));
		})
	})
</script>