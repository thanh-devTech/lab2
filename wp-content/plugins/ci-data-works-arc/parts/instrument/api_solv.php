<?php
global $wpdb, $current_role;

include(TRCPDD_PATH . 'api/ci_api_solv.php');

$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'3' => 'Inconclusive',
	'4' => 'Inconclusive',
);

$dir        = dirname(ABSPATH) . '/solv/';
$order_dir  = $dir . 'orders/';
$order_log_dir = $dir . 'archive/';
$result_dir = $dir . 'results/';

$log_run_file = WP_CONTENT_DIR . '/log/log_api_solv_run.txt';

$wpdb->query("select cli_solv_hash from ci_clinic limit 1");
if (!empty($wpdb->last_error)) {
	$wpdb->query('alter table ci_clinic add `cli_solv_hash` varchar(15)');
}

if (isset($_POST['options'])) {
	$options = $_POST['options'];
	$options['enable'] = !empty($options['enable']) ? 1 : 0;
	if (empty($options['cron'])) {
		$options['cron'] = 5;
	}

	$old_options = get_option('tr_solv_st', array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_tr_solv_st', $bkdata);

	update_option('tr_solv_st', $options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_solv_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_solv');

	if ($options['enable']) {
		wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_api_solv');
		wp_mkdir_p($dir);
		wp_mkdir_p($order_dir);
		wp_mkdir_p($order_log_dir);
		wp_mkdir_p($result_dir);
	}
}
$options = get_option('tr_solv_st', array());

if (!isset($options['cron']) || empty($options['cron'])) {
	$options['cron'] = 5;
}
if (!isset($options['lab_id'])) {
	$options['lab_id'] = 0;
}

if (!isset($options['cli_id'])) {
	$options['cli_id'] = 0;
}

if (!isset($options['doc_id'])) {
	$options['doc_id'] = 0;
}
if (!isset($options['enable'])) {
	$options['enable'] = 0;
}
if (!isset($options['notes'])) {
	$options['notes'] = '';
}


$client = new CI_Api_Solv($options['lab_id'], $options['cli_id'], $options['doc_id']);
if ($act == 'test_sync') {
	try {

		$client->debug = true;
		$client->sync_reqs($order_dir);
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_report') {
	try {
		//$result_dir = WP_CONTENT_DIR;
		$client->debug = true;
		$client->sync_reports($result_dir);
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	echo 'ok';
	exit;
} else if ($act == 'log') {

	$joins = '';
	$fields = '';
	$where = " fi.m_type='solv'  ";

	if (!empty($_REQUEST['s'])) {
		$where .= " and (ba.assen = '{$_REQUEST['s']}' or oldreq = '{$_REQUEST['s']}' )";
	}
	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_pcr ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid
	                                join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
	                                where {$where}"));

	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select 
	                            ba.mid,
                                ba.assen,
                                ba.doc,
                                ba.oldreq,
                                ba.requesting_physician,
                                ba.requesting_practice,
                                ba.notes,
                                
                                pa.pa_ID,
                                pa.pa_first,
                                pa.pa_last,
                                pa.pa_dob,
                                pa.pa_gender,
                                pa.pa_address,
                                
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                o.mo_status,
                                fi.m_ID,
                                fi.m_time,
                                fi.m_file
                                {$fields}
                            from ci_batches_pcr ba
                            join ci_patients pa ON pa.pa_ID = ba.patient_id
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");



	ob_start();
?>
	<div>
		<input type="search" id="assen_search">
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:40px">#</th>
				<th style="width:80px">Accession</th>
				<th>Ref ID</th>
				<th>Patient</th>
				<th>DOB</th>
				<th>Gender</th>
				<th>Clinic</th>
				<th>Doctor</th>
				<th>DOC</th>
				<th style="width:60px">Resulted</th>
				<th style="width:50px">Status</th>
				<th>Date/time</th>
				<th style="width:30px;"></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {
				$file_path = dirname($order_dir) . '/archive/' . basename($row->m_file);

				if ((isset($_GET['fixdob']) && empty($row->pa_dob) || $row->pa_dob == '1970-01-01')
					|| (isset($_GET['fix_gender']))
				) {
					$data = trpdd_get_data($file_path, null, array(), true);
					foreach ($data['data'] as $rowdata) {
						$pa_first = trpdd_get_string_exist($rowdata, array('patient first name', 'first name'));
						$pa_last = trpdd_get_string_exist($rowdata, array('patient last name', 'last name'));
						$gender = trpdd_get_string_exist($rowdata, array('birth sex', 'gender', 'sex', 'patient gender', 'gender identity'));
						$dob = trpdd_get_string_exist($rowdata, array('date of birth', 'dob', 'date of birth_2', 'dob_2'));
						$phone = trpdd_get_string_exist($rowdata, array('mobile', 'phone', 'phone_2'));
						$email = trpdd_get_string_exist($rowdata, array('email', 'e-mail', 'email_2'));
						$reason = trpdd_get_string_exist($rowdata, array('reason for visit', 'reason for visit_2'));

						if (!empty($gender)) {
							$gender = stripos($gender, 'u') !== false ? 'U' : $gender;
							$gender = stripos($gender, 'f') !== false ? 'F' : 'M';
						} else {
							$gender = '';
						}

						if (!empty($dob)) {
							$dob    = date('Y-m-d', strtotime($dob));
						}

						if (
							strtolower($pa_first) == strtolower($row->pa_first)
							&& strtolower($pa_last) == strtolower($row->pa_last)
						) {
							$row->pa_dob = $dob . ' fixed';

							if (empty($row->notes)) {
								$update_bat = array(
									'notes' => $reason,
									'dob' => $dob
								);
								$wpdb->update('ci_batches_pcr', $update_bat, array('mid' => $row->mid));
								$row->pa_dob = $row->pa_dob . ' n';
							}

							$update_patient = array();
							if (isset($_GET['fix_gender']) &&  !empty($gender)) {
								$update_patient['pa_gender'] = $gender;
							}
							if (isset($_GET['fixemail']) && !empty($email)) {
								$update_patient['pa_email'] = $email;
							}
							if (isset($_GET['fixphone']) && !empty($phone)) {
								$update_patient['pa_phone'] = $phone;
							}

							if (isset($_GET['fixdob']) && !empty($dob)) {
								$update_patient['pa_dob'] = $dob;
							}

							if (!empty($update_patient)) {
								$wpdb->update('ci_patients', $update_patient, array('pa_ID' => $row->pa_ID));
								$row->pa_dob = $row->pa_dob . ' p';
							}

							break;
						}
					}
				}
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
					<td><?php echo $row->mid ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid ?>&short=1"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->oldreq ?></td>
					<td><?php echo $row->pa_last . ', ' . $row->pa_first ?></td>
					<td><?php echo $row->pa_dob ?></td>
					<td class="center"><?php echo $row->pa_gender ?></td>
					<td><?php echo $row->requesting_practice ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td><?php echo $row->doc ?></td>
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
					<td><?php echo date('Y-m-d H:i:s', $row->m_time) ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->m_ID), false) . '">file</a>';
						?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

	<style>
		.wp-list-table td {
			font-size: 11px;
		}
	</style>
	<?php
	$content = ob_get_clean();
	if (isset($_GET['trajax'])) {
		$content = str_replace('class="', 'class="show_popup ', $content);
		$content = str_replace('class=\'', 'class=\'show_popup ', $content);
	}
	echo $content;
	exit;
} else if ($act == 'viewrshl7') {
	try {
		$hl7_parser = new CI_HL7_Parser();
		$row = $wpdb->get_row("select * from ci_sync_api_files where m_ID = {$_REQUEST['fid']}");
		$file_path = dirname($order_dir) . '/archive/' . basename($row->m_file);

		if (file_exists($file_path)) {
			echo 'order file:' . $file_path;
			echo '<br>';
			$hl7 = file_get_contents($file_path);
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
} else if ($act == 'demofile') {
	ob_clean();
	$filename = 'solv_demo.csv';
	header("Content-disposition: attachment; filename={$filename}");
	header("Content-Type: text/csv");
	$output = fopen("php://output", 'w');
	$headers = array('Practice Name', 'Clinic Name', 'Date', 'Arrived Time', 'Discharged Time', 'Reserved Time', 'Appointment Time (online)', 'Patient First Name', 'Patient Last Name', 'Date Of Birth', 'Birth Sex', 'Email', 'Address Street', 'Address Street Secondary', 'Address City', 'Address State', 'Address Zip', 'Patient Notes', 'Patient Type', 'Transferred From', 'Here To Ready', 'Ready To Exam', 'Exam To Done', 'Door To Door', 'Mobile', 'Source', 'Status', 'Cancellation Reason', 'Reason For Visit', 'Type Of Online', 'Rating', 'Review', 'Booked By First Name', 'Booked By Last Name', 'Booking Id Hash', 'Booking Insurance Carrier', 'Booking Insurance Member Id', 'Booking Insurance Insurer Type');
	fputcsv($output, $headers);
	$data = array('Lab', 'Clinic', '2021-10-26', '', '', '4:58 PM', '4:00 PM', 'Test', 'Patient', '1997-04-23', '', 'testemail@ciwebstudio.com', 'Address street', '', 'City', 'MI', '12345', '', 'New', '', '0', '0', '0', '0', '(989) 111-0000', 'Solv', 'Reserved', '', 'Covid Testing', 'Solv app', '', '', '', '', 'kdQ6b9', '', '');
	fputcsv($output, $data);
	exit;
} else if ($act == 'list_hashcodes') {

	$list = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cast(cli_solv_hash as unsigned) asc");
	?>
	<table id="hashcodetbl" class="posts wp-list-table widefat fixed hover_tbl">
		<thead>
			<tr>
				<th style="width:30px">#</th>
				<th>Clinic</th>
				<th style="width:180px">Hash Code</th>
			</tr>
		</thead>
		<?php
		foreach ($list as $row) {
		?>
			<tr class="datarow" data-id="<?php echo $row->cID ?>">
				<td><?php echo $row->cID ?></td>
				<td><?php echo $row->cli_clinic ?></td>
				<td><input type="text" class="editfield" data-k="cli_solv_hash" value="<?php echo $row->cli_solv_hash ?>" /></td>
			</tr>
		<?php
		}
		?>
	</table>
<?php
	exit;
} else if ($act == 'save_hashcode') {
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
	<h2>solv Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="row">
			<div style="width:45%;float:left">
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
							$selected = $clinic->cID == $options['cli_id'] ? 'selected' : '';
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
					<textarea name="options[notes]" style="height:300px;width:100%"><?php echo esc_html($options['notes']) ?></textarea>
				</div>
			</div>
		</div>



		<div class="row">
			<input type="submit" value="Save" class="button-primary" />
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>&width=1000">Test Sync Orders</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Log</a> &nbsp;&nbsp;
			<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_hashcodes', 'trajax' => 1)) ?>">List Hash Code</a>
			<a class="button" href="/wp-content/log/log_api_solv.txt" target="_blank">Cron Log</a> &nbsp;&nbsp;
			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install')); ?>&width=1000">Install</a> &nbsp;&nbsp;
				<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'demofile')); ?>">Demo file</a> &nbsp;&nbsp;
			<?php } ?>
			<?php
			$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='solv'");
			echo '<br>';
			echo 'Reqs: ' . $count->count_reqs . '<br>';
			echo 'Results: ' . $count->count_results . '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled('cron_api_solv');

			$last_cron = file_exists($log_run_file) ? file_get_contents($log_run_file) : 0;
			if ($last_cron >  $next_cron - 60 * $options['cron']) {
				$last_cron = $next_cron - 60 * $options['cron'];
			}
			if ($last_cron > 0) {
				echo 'Last Cron: ' . get_date_from_gmt(date('Y-m-d H:i:s', $last_cron), 'Y-m-d H:i:s') . '<br>';
			}
			if ($next_cron) {
				echo 'Next Cron: ' . get_date_from_gmt(date('Y-m-d H:i:s', $next_cron), 'Y-m-d H:i:s') . '<br>';
			}

			?>
		</div>
	</form>
</div>

<script>
	var $doctors = <?php echo json_encode($doctors) ?>;
	var $selected_doc = <?php echo intval(isset($options['doc_id']) ? $options['doc_id'] : 0) ?>;
	jQuery(function($) {
		function save_hashcode_data($this) {
			var $pr = $this.parent();
			var $tr = $pr.parent();
			var $vl = $this.val();
			var $col = $this.data('k');
			var $id = $tr.data('id');

			$pr.append('<div class="sloading"></div>');
			$.ajax({
				data: {
					act: 'save_hashcode',
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
		$('body').on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + $s);
			}, 300);
			return false;
		}).on('click', 'a.show_popup', function() {
			if ($(this).hasClass('seach_assen')) return;
			var $url = $(this).attr('href');
			tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 500);
			return false;
		}).on('keypress', '#hashcodetbl input.editfield', function(e) {
			var code = e.keyCode || e.which;
			if (code == 13) {
				save_hashcode_data($(this));
				return false;
			}
		}).on('change', '#hashcodetbl input.editfield', function() {
			save_hashcode_data($(this));
		})
	})
</script>