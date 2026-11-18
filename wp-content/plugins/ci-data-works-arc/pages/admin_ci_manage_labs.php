<?php
global $wpdb;
global $ci_all_btypes;
global $is_ci_admin;

$user = wp_get_current_user();
//check db version
$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
	wp_schedule_single_event(time(), 'ci_check_upload_apis');
	trpdd_plugin_activate();
}


include_once(TRCPDD_PATH . 'pages/formdata.php');

$subpage = isset($_REQUEST['subpage']) ? $_REQUEST['subpage'] : '';
$use_new_report_to_state = tr_get_option('ci_pdf_data', 'new_report_to_state_old_labs');
$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$state_lab_ajax_actions = array(
	'save_state_lab',
	'list_state_lab_backups',
	'restore_state_lab_backup',
	'delete_state_lab_backup',
	'import_state_lab_csv',
);

$sign_dir = tr_get_signature_dir();
$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos';
$logo_url = $upload_dir['baseurl'] . '/logos';

$keys_operation = [
	'lab_mon' => 'Mon',
	'lab_tue' => 'Tue',
	'lab_wed' => 'Wed',
	'lab_thu' => 'Thu',
	'lab_fri' => 'Fri',
	'lab_sat' => 'Sat',
	'lab_sun' => 'Sun',
];

if ($use_new_report_to_state && in_array($trajax, $state_lab_ajax_actions)) {
	include(trpdd_get_file_path('lab', 'edit_lab_ajax'));
	exit;
}

$tabs = array(
	'' => 'Labs',
	'report_to_state' => 'Report to state',
	'ref_labs' => 'Ref Labs',
);

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($tabs[$tab])) {
	$tab = key($tabs);
}
if (!isset($_REQUEST['trajax'])) {
?>
	<div class="wrap">
		<h2 class="nav-tab-wrapper">
			<?php

			foreach ($tabs as $k => $txt) {
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link  = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'date' => '', 'labid' => '', 'btype' => '', 'subpage' => '', 'gid' => ''), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
			}
			?>
		</h2>
	</div>
<?php
}

if ($tab == 'ref_labs' && !isset($_REQUEST['trajax'])) {
	include(trpdd_get_file_path('lab', 'ref_lab_list'));
	return;
} else if ($tab == 'report_to_state' && !isset($_REQUEST['trajax'])) {
	$slug_main = isset($_GET['page']) ? $_GET['page'] : 'ci_manage_labs';
	$state_lab_ajax_url = 'admin.php?page=' . $slug_main . '&tab=report_to_state';
	if ($use_new_report_to_state) {
		include(trpdd_get_file_path('lab', 'state_lab'));
	} else {
		include(trpdd_get_file_path('lab', 'report_to_state'));
	}
	return;
}

if (isset($_POST['tblci_labs'])) {
	$lab 		= tr_pdd_stripslashes_deep($_POST['tblci_labs']);
	$lab_id 	= $lab['lab_ID'];
	$lab_effective_date = isset($_POST['lab_effective_date']) ? sanitize_text_field($_POST['lab_effective_date']) : '';
	$lab['lab_email'] = trim($lab['lab_email']);
	$email 		= trim($lab['lab_email']);
	unset($lab['lab_ID']);
	$days_of_operation = [];
	$oldlab = array();
	$save_lab_history = false;
	foreach ($keys_operation as $key => $label) {
		$days_of_operation[$key] = $lab[$key];
		unset($lab[$key]);
	}
	$target_tat_options = array(12, 24, 36, 48, 60, 72);
	$days_of_operation['target_tat'] = isset($lab['target_tat']) && in_array(intval($lab['target_tat']), $target_tat_options) ? intval($lab['target_tat']) : 36;
	unset($lab['target_tat']);

	if (tr_get_option('ci_pdf_data', 'req_auto_rej_ins')) {
		$lab['lab_auto_rej_insurance'] = @json_encode($_POST['lab_auto_rej_insurance']);
	}


	if (!empty($lab['lab_address']) && empty($lab['lab_city'])) {
		$address_arr = tr_parse_address($lab['lab_address']);

		if (empty($lab['lab_city'])) {
			$lab['lab_address'] = $address_arr['address'];
			$lab['lab_city'] = $address_arr['city'];
		}
		if (empty($lab['lab_state'])) {
			$lab['lab_state'] = $address_arr['state'];
		}
		if (empty($lab['lab_zip'])) {
			$lab['lab_zip'] = $address_arr['zip'];
		}
		$_POST['tblci_labs'] = $lab;
	}

	if ($lab_id > 0) {
		$oldlab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id} ", ARRAY_A);
		if (!is_array($oldlab)) {
			$oldlab = array();
		}

		if (!isset($_GET['debug'])) {
			unset($lab['lab_dba_init']); //don't allow change lab_dba_init after created
		}

		$rs = $wpdb->update('ci_labs', $lab, array('lab_ID' => $lab_id));
		if ($rs === false) {
			Tr_Session_Class::add_error('Error. update Lab wrong to db!');
			echo $wpdb->last_error;
		} else {
			$save_lab_history = true;
			Tr_Session_Class::add('Updated Lab Successfully!');

			if ($lab_id == 1 && isset($lab['lab_dba_init']) && $oldlab['lab_dba_init'] != $lab['lab_dba_init']) {
				$opt = get_option('ci_pdf_data', array());
				$opt['barcode_key'] = $lab['lab_dba_init'];
				update_option('ci_pdf_data', $opt);
			}
		}
	} else {
		$wpdb->insert('ci_labs', $lab);
		$lab_id = $wpdb->insert_id;
		if ($lab_id > 0) {
			$save_lab_history = true;
			Tr_Session_Class::add('Created New Lab Successfully!');
		} else {
			Tr_Session_Class::add_error('Error. create Lab wrong to db!');
		}
		$_POST = array();
	}

	if ($lab_id > 0) {
		//check lab user role
		$user_id = $wpdb->get_var("select ID from {$wpdb->users} where user_email ='{$email}'");

		if (!$user_id && !empty($email)) {
			$pass = wp_generate_password(12, false);
			$userarr = array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass' => $pass,
				'first_name' => $lab['lab_name'],
				'display_name' => $lab['lab_name'],
				'role' => 'lab'
			);
			$user_id = wp_insert_user($userarr);

			if (!is_wp_error($user_id)) {
				$rs = trpdd_send_mail_create_user($user_id, $pass);

				Tr_Session_Class::add('Created User: ' . $email);
				if ($rs) {
					Tr_Session_Class::add('Sent email to ' . $email);
				} else {
					Tr_Session_Class::add_error('Error while send email to ' . $email);
				}
			} else {
				Tr_Session_Class::add_error($user_id->get_error_message());
			}
		}

		if (!is_wp_error($user_id) && $user_id > 0) {
			CI_Base_Lab::set_user_lab($user_id, $lab_id);

			do_action('tr_change_role', $user_id, 'lab');
		}

		$update_lab = array();

		//end check
		$logoname = 'lab_' . $lab_id;
		$logo = $this->uploadfile($_FILES['lab_logo'], $logoname, $logo_dir);
		if (!empty($logo)) {
			$update_lab['lab_logo'] = $logo;
			$lab['lab_logo'] = $logo;
		}

		$results_logoname = 'lab_results_' . $lab_id;
		$results_logo = $this->uploadfile($_FILES['lab_results_logo'], $results_logoname, $logo_dir);
		if (!empty($results_logo)) {
			$update_lab['lab_results_logo'] = $results_logo;
			$lab['lab_results_logo'] = $results_logo;
		}

		//upload lab_director_signature

		$director_signature_name = pathinfo(wp_unique_filename($sign_dir['path'], $lab_id . 'director.png'), PATHINFO_FILENAME);
		$director_sign = tr_save_signature($director_signature_name, $_POST['lab_director_signature'], $_FILES['lab_director_signature']);
		if ($director_sign) {
			$update_lab['lab_director_signature'] = $director_sign;
			$lab['lab_director_signature'] = $director_sign;
		}

		if (!empty($update_lab)) {
			$wpdb->update('ci_labs', $update_lab, array('lab_ID' => $lab_id));
		}

		if (empty($lab['lab_dba_init']) && !empty($oldlab['lab_dba_init'])) {
			$lab['lab_dba_init'] = $oldlab['lab_dba_init'];
		}

		if ($save_lab_history) {
			CI_Base_Lab::save_lab_field_history($lab_id, $oldlab, $lab, $lab_effective_date, get_current_user_id());
			trpdd_log_lab_change($oldlab ? 'update_lab' : 'add_lab', $lab_id, $lab, $oldlab);
		}
		$days_of_operation['lab_ID'] = $lab_id;
		$current_date = sanitize_text_field($_POST['start_date']);
		$old_operation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
		 FROM ci_lab_operation
		 WHERE lab_ID = %d
		 ORDER BY from_date DESC
		 LIMIT 1",
				$lab_id
			),
			ARRAY_A
		);
		$operations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
		 FROM ci_lab_operation
		 WHERE lab_ID = %d
		 ORDER BY from_date ASC",
				$lab_id
			),
			ARRAY_A
		);

		$prev_day = function ($date) {
			return date('Y-m-d', strtotime('-1 day', strtotime($date)));
		};

		if (empty($operations)) {
			// ===== CASE 1: chưa có operation nào =====
			$days_of_operation['from_date'] = $current_date;
			$days_of_operation['created']   = $current_date;

			$wpdb->insert('ci_lab_operation', $days_of_operation);
			trpdd_log_lab_change('add_lab_operation', $lab_id, $days_of_operation, array());
		} else {

			$inserted = false;
			$count = count($operations);

			foreach ($operations as $index => $op) {

				if ($op['from_date'] === $current_date) {
					$wpdb->update(
						'ci_lab_operation',
						$days_of_operation,
						['ID' => $op['ID']]
					);
					trpdd_log_lab_change('update_lab_operation', $lab_id, $days_of_operation, $op);
					$inserted = true;
					break;
				}

				// current_date nằm trước operation đầu tiên
				if ($index === 0 && $current_date < $op['from_date']) {
					$days_of_operation['from_date'] = $current_date;
					$days_of_operation['to_date']   = $prev_day($op['from_date']);
					$days_of_operation['created']   = $current_date;

					$wpdb->insert('ci_lab_operation', $days_of_operation);
					trpdd_log_lab_change('add_lab_operation', $lab_id, $days_of_operation, $old_operation);
					$inserted = true;
					break;
				}

				// current_date nằm giữa
				if (
					$index < $count - 1 &&
					$current_date > $op['from_date'] &&
					$current_date < $operations[$index + 1]['from_date']
				) {
					// Update operation trước
					$wpdb->update(
						'ci_lab_operation',
						['to_date' => $prev_day($current_date)],
						['ID' => $op['ID']]
					);

					// Insert operation mới
					$days_of_operation['from_date'] = $current_date;
					$days_of_operation['to_date']   = $prev_day($operations[$index + 1]['from_date']);
					$days_of_operation['created']   = $current_date;

					$wpdb->insert('ci_lab_operation', $days_of_operation);
					trpdd_log_lab_change('add_lab_operation', $lab_id, $days_of_operation, $old_operation);
					$inserted = true;
					break;
				}
			}

			// current_date lớn nhất (cuối list)
			if (!$inserted) {
				$last = end($operations);

				$wpdb->update(
					'ci_lab_operation',
					['to_date' => $prev_day($current_date)],
					['ID' => $last['ID']]
				);

				$days_of_operation['from_date'] = $current_date;
				$days_of_operation['created']   = $current_date;

				$wpdb->insert('ci_lab_operation', $days_of_operation);
				trpdd_log_lab_change('add_lab_operation', $lab_id, $days_of_operation, $old_operation);
			}
		}
		$days_of_operation = [];
	}
}

if (isset($_POST['trajax']) && $_POST['trajax'] === 'fetch_lab_operation') {
	$lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : '';

	if (empty($lab_id)) {
		wp_send_json([
			'success' => true,
			'data'    => [
				'labs'  => [],
			]
		]);
	}

	$sql = $wpdb->prepare("
        SELECT *
        FROM ci_lab_operation
		where lab_ID = {$lab_id}
        ORDER BY from_date DESC
    ");

	$labs = $wpdb->get_results($sql);

	wp_send_json([
		'success' => true,
		'data'    => [
			'labs'  => $labs,
		]
	]);
	exit;
}
if (isset($_POST['trajax']) && $_POST['trajax'] === 'update_lab_operation') {
	$lab_operation = isset($_POST['lab_operation']) ? $_POST['lab_operation'] : array();

	$lab_id = intval($lab_operation['ID']);
	unset($lab_operation['ID']);
	$old_operation = $wpdb->get_row($wpdb->prepare("SELECT * FROM ci_lab_operation WHERE ID = %d", $lab_id), ARRAY_A);

	$where = array('ID' => $lab_id);
	$result = $wpdb->update(
		'ci_lab_operation',
		$lab_operation,
		$where,
	);

	if ($result !== false) {
		$log_lab_id = isset($old_operation['lab_ID']) ? $old_operation['lab_ID'] : 0;
		trpdd_log_lab_change('update_lab_operation', $log_lab_id, $lab_operation, $old_operation);
		wp_send_json([
			'success' => true,
			'message' => 'Update successfully!',
			'data'    => $lab_operation
		]);
	} else {
		wp_send_json([
			'success' => false,
			'message' => 'Failed: ' . $wpdb->last_error
		]);
	}
	exit;
}
if (isset($_POST['trajax']) && $_POST['trajax'] === 'delete_lab_operation') {

	$lab_id = isset($_POST['lab_ID']) ? intval($_POST['lab_ID']) : 0;

	if ($lab_id <= 0) {
		wp_send_json([
			'success' => false,
			'message' => 'Invalid lab ID'
		]);
	}

	$old_operation = $wpdb->get_row($wpdb->prepare("SELECT * FROM ci_lab_operation WHERE ID = %d", $lab_id), ARRAY_A);
	$result = $wpdb->delete(
		'ci_lab_operation',
		['ID' => $lab_id],
		['%d']
	);

	if ($result !== false) {
		trpdd_log_user(get_current_user_id(), 'delete_lab_operation', $old_operation, isset($old_operation['lab_ID']) ? $old_operation['lab_ID'] : 0, 'lab', '', '', isset($old_operation['lab_ID']) ? $old_operation['lab_ID'] : '');
		wp_send_json([
			'success' => true,
			'message' => 'Deleted successfully!',
			'data'    => ['ID' => $lab_id]
		]);
	} else {
		wp_send_json([
			'success' => false,
			'message' => 'Failed: ' . $wpdb->last_error
		]);
	}
	exit;
}

if (isset($_POST['trajax']) && $_POST['trajax'] === 'list_lab_history') {
	if (!$is_ci_admin) {
		echo '<div class="notice notice-error" style="margin:0;">Access denied.</div>';
		exit;
	}

	$lab_id = isset($_POST['lab_ID']) ? intval($_POST['lab_ID']) : 0;
	$logs = $wpdb->get_results($wpdb->prepare(
		"SELECT l.*, u.display_name, u.user_email
		FROM ci_log_users l
		LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
		WHERE l.mid = %d
		AND l.btype = 'lab'
		AND l.user_action IN ('add_lab', 'update_lab', 'add_ref_lab', 'update_ref_lab', 'add_lab_operation', 'update_lab_operation', 'delete_lab_operation')
		AND l.data IS NOT NULL
		AND l.data <> ''
		ORDER BY l.created DESC, l.id DESC
		LIMIT 500",
		$lab_id
	));

	$action_labels = array(
		'add_lab' => 'Add Lab',
		'update_lab' => 'Update Lab',
		'add_ref_lab' => 'Add Ref Lab',
		'update_ref_lab' => 'Update Ref Lab',
		'add_lab_operation' => 'Add Lab Operation',
		'update_lab_operation' => 'Update Lab Operation',
		'delete_lab_operation' => 'Delete Lab Operation',
	);
	$field_labels = array(
		'lab_name' => 'Lab Name',
		'lab_dba' => 'Lab DBA',
		'lab_dba_init' => 'DBA INIT',
		'lab_email' => 'Email',
		'lab_phone' => 'Phone',
		'lab_address' => 'Address',
		'lab_city' => 'City',
		'lab_state' => 'State',
		'lab_zip' => 'Zip',
		'lab_clia' => 'CLIA',
		'lab_cola' => 'COLA',
		'lab_cap' => 'CAP',
		'lab_director' => 'Director',
		'lab_director_signature' => 'Director Signature',
		'lab_results_logo' => 'Results Logo',
		'lab_additional' => 'Additional Footer',
		'lab_status' => 'Enable Lab',
		'lab_mon' => 'Monday',
		'lab_tue' => 'Tuesday',
		'lab_wed' => 'Wednesday',
		'lab_thu' => 'Thursday',
		'lab_fri' => 'Friday',
		'lab_sat' => 'Saturday',
		'lab_sun' => 'Sunday',
		'target_tat' => 'Target TAT',
		'from_date' => 'From Date',
		'to_date' => 'To Date',
		'created' => 'Created',
	);
	$current_date = '';
	?>
		<div class="lab-history-wrap">
		<?php if (empty($logs)) { ?>
			<div class="lab-history-item">No changed data found.</div>
		<?php } ?>
		<?php foreach ($logs as $log) {
			$data = json_decode($log->data, true);
			if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data)) {
				continue;
			}
			$log_date = date_i18n('Y-m-d', strtotime($log->created));
			if ($log_date !== $current_date) {
				if ($current_date !== '') {
					echo '</div>';
				}
				$current_date = $log_date;
		?>
				<div class="lab-history-group">
					<div class="lab-history-date">
						<span class="lab-history-toggle">▼</span>
						<span><?php echo esc_html(date_i18n('M d, Y', strtotime($log->created))); ?></span>
					</div>
			<?php
			}
			$display_name = !empty($log->display_name) ? $log->display_name : $log->user_email;
			if (empty($display_name)) {
				$display_name = 'System';
			}
			$label = isset($action_labels[$log->user_action]) ? $action_labels[$log->user_action] : $log->user_action;
			?>
			<div class="lab-history-item">
				<div class="lab-history-meta">
					<span><?php echo esc_html(date_i18n('H:i:s', strtotime($log->created))); ?></span>
					<span class="lab-history-action"><?php echo esc_html($label); ?></span>
					<span><?php echo esc_html($display_name); ?></span>
					<?php if (!empty($log->ip)) { ?>
						<span><?php echo esc_html($log->ip); ?></span>
					<?php } ?>
				</div>
				<table class="lab-history-changes">
					<thead>
						<tr>
							<th>Field</th>
							<th>Old</th>
							<th>New</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($data as $field => $value) {
							if ($field === 'old') {
								continue;
							}
							$field_label = isset($field_labels[$field]) ? $field_labels[$field] : $field;
							if (is_array($value) && array_key_exists(0, $value) && array_key_exists(1, $value)) {
								$old_value = is_scalar($value[0]) ? $value[0] : wp_json_encode($value[0]);
								$new_value = is_scalar($value[1]) ? $value[1] : wp_json_encode($value[1]);
							} else {
								$old_value = '';
								$new_value = is_scalar($value) ? $value : wp_json_encode($value);
							}
							$old_value = trim(wp_strip_all_tags((string) $old_value));
							$new_value = trim(wp_strip_all_tags((string) $new_value));
						?>
							<tr>
								<th><?php echo esc_html($field_label); ?></th>
								<td><?php echo $old_value !== '' ? esc_html($old_value) : '<em>empty</em>'; ?></td>
								<td><?php echo $new_value !== '' ? esc_html($new_value) : '<em>empty</em>'; ?></td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
		<?php } ?>
		<?php if (!empty($logs)) { ?>
			</div>
		<?php } ?>
	</div>
<?php
	exit;
}



if ($subpage == 'footer') {
	include(TRCPDD_PATH . 'parts/lab/footer.php');
} else if ($act == 'edit' || $act == 'add') {

	$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

	if ($id > 0) {
		$data = $wpdb->get_row(
			$wpdb->prepare(
				"select l.*, clo.lab_mon, clo.lab_tue, clo.lab_wed, clo.lab_thu, clo.lab_fri, clo.lab_sat, clo.lab_sun, clo.target_tat
						from ci_labs l
						LEFT JOIN ci_lab_operation clo
						ON clo.id = (
							SELECT id
							FROM ci_lab_operation
							WHERE lab_ID = l.lab_ID
							ORDER BY from_date DESC
							LIMIT 1
						)
						where l.lab_ID = %d",
				$id
			),
			ARRAY_A
		);
		if (!array_key_exists('lab_mon', $data) || $data['lab_mon'] === null) {
			$data = array_merge(
				$data ?: [],
				[
					'lab_mon' => 1,
					'lab_tue' => 1,
					'lab_wed' => 1,
					'lab_thu' => 1,
					'lab_fri' => 1,
					'lab_sat' => 1,
					'lab_sun' => 1,
				]
			);
		}
		if (empty($data['target_tat'])) {
			$data['target_tat'] = 36;
		}
	} else {
		$data = array(
			'lab_status' => 1,
			'lab_mon' => 1,
			'lab_tue' => 1,
			'lab_wed' => 1,
			'lab_thu' => 1,
			'lab_fri' => 1,
			'lab_sat' => 1,
			'lab_sun' => 1,
			'target_tat' => 36,
		);
	}

	$insurance  = !empty($data['lab_auto_rej_insurance']) ? $data['lab_auto_rej_insurance'] : '[""]';
?>
	<div class="wrap">
		<h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Lab <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Labs</a> </h2>
		<?php Tr_Session_Class::show() ?>

		<?php $this->form(array('data' => $data, 'enctype' => 'multipart/form-data'), 'tblci_labs', 'div'); ?>

		<div class="frow blabel">
			<div class="col2">
				<?php
				$editable_lab_init = ($id > 0 && isset($_GET['debug'])) || ($id == 0); //only allow edit lab_dba_init when create new lab or admin user
				$this->input('lab_ID', array('type' => 'hidden'));
				$this->input('lab_name', array('label' => 'Lab Name', 'required' => true, 'attrs' => 'maxlength=75'));
				$this->input('lab_dba_init', array('label' => 'DBA INIT', 'required' => true, 'attrs' => 'maxlength=15 ' . (!$editable_lab_init ? 'readonly' : '')));
				$this->input('lab_address', array('label' => 'Address'));
				$this->input('lab_state', array('label' => 'State', 'attrs' => 'maxlength=10'));
				$this->input('lab_phone', array('label' => 'Phone'));
				$this->input('lab_email', array('label' => 'Email'));
				$this->input('lab_npi', array('label' => 'NPI', 'attrs' => 'maxlength=30'));
				$this->input('lab_tax_id', array('label' => 'Tax ID', 'attrs' => 'maxlength=30'));
				$this->input('lab_billing_account', array('label' => 'Billing Account'));
				$this->select('target_tat', array(12 => '12h', 24 => '24h', 36 => '36h', 48 => '48h', 60 => '60h', 72 => '72h'), array('label' => 'Target TAT'));
				if ($user->user_login == 'Administrat0r') {
					$this->input('lab_color', array('label' => 'Main Color', 'attrs' => 'maxlength=8'));
				}

				$this->input('lab_not_specimen', array('type' => 'checkbox', 'hide_label' => true, 'label' => 'Disable SPECIMEN VALIDITY TEST'));
				$this->input('lab_status', array('type' => 'checkbox', 'hide_label' => true, 'label' => 'Enable'));
				?>
			</div>
			<div class="col2">
				<?php
				$this->input('lab_dba', array('label' => 'Lab DBA', 'required' => true, 'attrs' => 'maxlength=75'));
				$this->input('lab_logo', array('label' => 'Logo', 'type' => 'file', 'path' => $logo_url));
				$this->input('lab_results_logo', array('label' => 'Results Logo', 'type' => 'file', 'path' => $logo_url));
				$this->input('lab_city', array('label' => 'City', 'attrs' => 'maxlength=100'));
				$this->input('lab_zip', array('label' => 'Zip', 'attrs' => 'maxlength=12'));
				$this->input('lab_fax', array('label' => 'Fax'));
				$this->input('lab_clia', array('label' => 'CLIA', 'attrs' => 'maxlength=30'));
				$this->input('lab_second_line_hpdf', array('label' => 'Text in PDF above CLIA'));
				$this->input('lab_cola', array('label' => 'COLA'));
				$this->input('lab_cap', array('label' => 'CAP'));
				$this->input('lab_specimen_fee', array('label' => 'Specimen Fee'));
				$this->input('lab_dna_fee', array('label' => 'DNA Fee'));
				if (function_exists('get_sites') && $GLOBALS['blog_id'] <= 1) {
					$lab_sites = array();
					$lab_sites[''] = 'Select Site';
					$sites = get_sites();

					foreach ($sites as $site) {
						//if($site['blog_id']==1)continue;
						if (stripos($site->domain, 'patients.') !== false) {
							continue;
						} else if (stripos($site->domain, 'results.') !== false) {
							continue;
						}
						$lab_sites[$site->blog_id] = '(#' . $site->blog_id . ') ' . $site->domain;
					}
					$this->select('lab_site', $lab_sites, array('label' => 'Lab Site'));
				} else if ($GLOBALS['blog_id'] == 1 && $id == 1) {
					$this->input('lab_site', array('type' => 'hidden', 'std' => 1));
				}

				?>
			</div>
		</div>
		<hr>
		<p style="margin: 10px 0;">Days of operation</p>
		<div class="frow">
			<?php
			foreach ($keys_operation as $key => $label) { ?>
				<div class="col12">
					<?php $this->input($key, array('type' => 'checkbox', 'hide_label' => true, 'label' => $label)); ?>
				</div>
			<?php
			}
			?>

			<?php
			if ($id > 0) {
			?>
				<div class="col12">
					<a class="button lab-operation-btn">Lab operation logs</a>
				</div>
			<?php
			}
			?>
		</div>
		<div class="frow">
			<div class="col4 edit-date-input">
				<label for="start-date">Start date</label>
				<input id="start-date" type="text" class="hasdate" name="start_date" placeholder="yyyy-mm-dd" value="<?php echo date_i18n('Y-m-d'); ?>" />
			</div>
		</div>
		<hr>
		<!-- <div class="frow">
			<div class="col2">
				<-?php
				if (in_array('tox', $ci_all_btypes)) {
					$this->input('lab_tox_cost', array('label' => 'Tox Cost'));
				}
				if (in_array('blood', $ci_all_btypes)) {
					$this->input('lab_blood_cost', array('label' => 'Blood Cost'));
				}
				?>
			</div>
			<div class="col2">
				<-?php
				if (in_array('dna', $ci_all_btypes)) {
					$this->input('lab_dna_cost', array('label' => 'PGx Cost'));
				}
				?>
			</div>
		</div> 
		<hr> -->
		<div class="frow">
			<div class="col2">
				<div class="frow">
					<?php $this->input('lab_director', array('label' => 'Director')); ?>
				</div>
				Director Signature:
				<br>
				<br>
				shortcode: [signature]
			</div>
			<div class="col2">
				<div class="card_file">
					<?php
					echo '<div class="signaturepad" style="' . (empty($data['lab_director_signature']) ? '' : 'display:none') . '">';
					echo tr_signature(array('name' => 'lab_director_signature', 'required' => false, 'clear' => false));
					echo '</div>';
					?>
					<a class="button selectfile" href="#">Choose Signature Image</a>
					<a class="button-primary remove" rel="lab_director_signature">Clear</a>
					<br>
					<span class="view"><?php
										if (!empty($data['lab_director_signature'])) {
											echo '<img src="' . $sign_dir['url'] . '/' . $data['lab_director_signature'] . '?' . time() . '" style="max-height:100px;background:#fff">';
										}
										?></span>
					<input type="file" name="lab_director_signature" accept="image/*" />
					<input type="hidden" name="tblci_labs[lab_director_signature]" value="<?php echo $data['lab_director_signature'] ?>" />
				</div>
			</div>

		</div>
		<hr>

		<?php
		if ($data['lab_ID'] > 0) {
			foreach (tr_pdd_get_batch_types() as $btype) {
		?>
				<div class="frow">
					<a href="<?php Tr_Base_Class_V6::link(array('subpage' => 'footer', 'btype' => $btype, 'id' => '', 'labid' => $data['lab_ID'])) ?>">
						<?php echo tr_pdd_get_typename($btype) . ' Result Footer' ?>
					</a>
				</div>
				<hr>
			<?php
			}
		}

		if (tr_get_option('ci_pdf_data', 'req_auto_rej_ins')) {
			?>

			<div class="frow">
				<h4>Auto Reject List</h4>
			</div>
			<div class="frow list_properties">
				<div class="col3">
					<ul id="list_auto_rej_insurance"></ul>
					<a class="addnew button" data-rel="auto_rej_insurance">+</a>
				</div>
			</div>
		<?php } ?>

		<input type="hidden" name="lab_effective_date" id="lab_effective_date" value="" />
		<div class="submit">
			<a class="button" href="<?php echo esc_url(Tr_Base_Class_V6::link(array('act' => '', 'id' => ''), false)); ?>">Cancel</a>
			<?php if ($is_ci_admin && $id > 0) { ?>
				<button type="button" class="button" id="btn_lab_history">History</button>
			<?php } ?>
			<input type="submit" class="button-primary" value="Save" />
		</div>
		<?php $this->endform(''); ?>
	</div>
	<div class="modal-overlay" id="lab-modal">
		<div class="modal-content">
			<div class="lab-logs-head-table">
				<h3 id="modal-title" class="m-0 text-xl">Lab operation</h3>
				<div type="button" class="modal-close" id="modal-close-btn">&times;</div>
			</div>
			<div class="modal-body" id="lab-table-container">
				<table class="labs-table">
					<thead>
						<tr>
							<th style="width: 10px;">ID</th>
							<th style="width: 10px;">Mon</th>
							<th style="width: 10px;">Tue</th>
							<th style="width: 10px;">Wed</th>
							<th style="width: 10px;">Thu</th>
							<th style="width: 10px;">Fri</th>
							<th style="width: 10px;">Sat</th>
							<th style="width: 10px;">Sun</th>
							<th style="width: 90px;">Target TAT</th>
							<th style="width: 100px;">Start date</th>
							<th style="width: 100px;">End date</th>
							<th style="width: 100px;">Created</th>
							<th style="width: 160px;">Actions</th>
						</tr>
					</thead>
					<tbody id="labs-tbody"></tbody>
				</table>

			</div>
		</div>
	</div>

	<script>
		jQuery(function($) {
			var $insurance = <?php echo $insurance ?>;
				const trackedLabFieldGroups = {
				address: {
					label: 'Address',
					fields: ['lab_address', 'lab_city', 'lab_state', 'lab_zip']
				},
				phone: {
					label: 'Phone',
					fields: ['lab_phone']
				},
				clia: {
					label: 'CLIA',
					fields: ['lab_clia']
				},
				cola: {
					label: 'COLA',
					fields: ['lab_cola']
				},
				cap: {
					label: 'CAP',
					fields: ['lab_cap']
				},
				medical_director: {
					label: 'Medical Director',
					fields: ['lab_director']
				},
				director_signature: {
					label: 'Director Signature',
					fields: ['lab_director_signature']
					}
				};
				const trackedLabFieldLabels = {
					lab_address: 'Address',
					lab_city: 'City',
					lab_state: 'State',
					lab_zip: 'Zip',
					lab_phone: 'Phone',
					lab_clia: 'CLIA',
					lab_cola: 'COLA',
					lab_cap: 'CAP',
					lab_director: 'Medical Director',
					lab_director_signature: 'Director Signature'
				};
			let originalTrackedLabValues = <?php echo wp_json_encode(array(
												'lab_address' => isset($data['lab_address']) ? $data['lab_address'] : '',
												'lab_city' => isset($data['lab_city']) ? $data['lab_city'] : '',
												'lab_state' => isset($data['lab_state']) ? $data['lab_state'] : '',
												'lab_zip' => isset($data['lab_zip']) ? $data['lab_zip'] : '',
												'lab_phone' => isset($data['lab_phone']) ? $data['lab_phone'] : '',
												'lab_clia' => isset($data['lab_clia']) ? $data['lab_clia'] : '',
												'lab_cola' => isset($data['lab_cola']) ? $data['lab_cola'] : '',
												'lab_cap' => isset($data['lab_cap']) ? $data['lab_cap'] : '',
												'lab_director' => isset($data['lab_director']) ? $data['lab_director'] : '',
												'lab_director_signature' => isset($data['lab_director_signature']) ? $data['lab_director_signature'] : '',
											)); ?>;
			const $labForm = $('form.need_validate').first();
			const $labEffectiveDate = $('#lab_effective_date');
			let allowLabFormSubmit = false;

			function normalizeTrackedLabValue(value) {
				return $.trim(value === undefined || value === null ? '' : String(value));
			}

			function isCanvasBlank(canvas) {
				if (!canvas) {
					return true;
				}

				const context = canvas.getContext('2d');
				const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;

				for (let i = 0; i < pixels.length; i += 4) {
					if (pixels[i + 3] !== 0) {
						return false;
					}
				}

				return true;
			}

			function getTrackedLabValues() {
				let values = {};

				$.each(trackedLabFieldGroups, function(groupKey, group) {
					$.each(group.fields, function(index, fieldKey) {
						values[fieldKey] = normalizeTrackedLabValue($('[name="tblci_labs[' + fieldKey + ']"]').val());
					});
				});

				let fileSig = document.querySelector('input[type=file][name=lab_director_signature]');
				let canvas = document.querySelector('.signaturepad canvas');
				if (fileSig && fileSig.files.length) {
					values.lab_director_signature = '__pending_upload__';
				} else if (canvas && !isCanvasBlank(canvas)) {
					values.lab_director_signature = '__pending_draw__';
				}

				return values;
			}

				function getChangedTrackedLabGroups() {
					const currentValues = getTrackedLabValues();
					const changedGroups = [];
					const changedFields = [];

					$.each(trackedLabFieldGroups, function(groupKey, group) {
						let isChanged = false;

						$.each(group.fields, function(index, fieldKey) {
							if (normalizeTrackedLabValue(originalTrackedLabValues[fieldKey]) !== currentValues[fieldKey]) {
								isChanged = true;
								changedFields.push({
									label: trackedLabFieldLabels[fieldKey] || fieldKey,
									oldValue: normalizeTrackedLabValue(originalTrackedLabValues[fieldKey]),
									newValue: currentValues[fieldKey]
								});
							}
						});

					if (isChanged) {
						changedGroups.push(group.label);
					}
				});

					return {
						labels: changedGroups,
						fields: changedFields
					};
				}

				function escapeLabChangeHtml(value) {
					return $('<div>').text(value === undefined || value === null || value === '' ? 'empty' : value).html();
				}

				function buildTrackedLabChangesHtml(changedFields) {
					let rows = '';

					$.each(changedFields, function(index, field) {
						rows += '<tr>' +
							'<th style="text-align:left;padding:6px;border-top:1px solid #eee;width:30%;">' + escapeLabChangeHtml(field.label) + '</th>' +
							'<td style="text-align:left;padding:6px;border-top:1px solid #eee;">' + escapeLabChangeHtml(field.oldValue) + '</td>' +
							'<td style="text-align:left;padding:6px;border-top:1px solid #eee;">' + escapeLabChangeHtml(field.newValue) + '</td>' +
							'</tr>';
					});

					return '<div style="font-size:14px;line-height:1.5;text-align:left;">' +
						'<div style="margin-bottom:8px;">Please choose the effective date for these changed fields:</div>' +
						'<table style="width:100%;border-collapse:collapse;font-size:12px;">' +
						'<thead><tr><th style="text-align:left;padding:6px;">Field</th><th style="text-align:left;padding:6px;">Old</th><th style="text-align:left;padding:6px;">New</th></tr></thead>' +
						'<tbody>' + rows + '</tbody></table></div>';
				}

				async function askTrackedLabEffectiveDate(trackedChanges) {
					const defaultDate = new Date().toISOString().split('T')[0];

					if (window.Swal && typeof Swal.fire === 'function') {
						const result = await Swal.fire({
							title: 'Effective date required',
							html: buildTrackedLabChangesHtml(trackedChanges.fields),
							input: 'date',
							inputValue: defaultDate,
							inputAttributes: {
								required: 'required'
							},
							showCancelButton: true,
							confirmButtonText: 'Continue',
							preConfirm: (value) => {
							if (!value) {
								Swal.showValidationMessage('Please choose an effective date.');
							}
							return value;
						}
					});

					if (!result.isConfirmed) {
						return '';
					}

					return result.value;
				}

					return window.prompt('Please choose the effective date for: ' + trackedChanges.labels.join(', '), defaultDate) || '';
				}

			function trpdd_setup_auto_complete() {
				$('.auto_auto_rej_insurance').autocomplete({
					source: ajaxurl + '?action=ajax&autocomplete=ins_company_auto',
					minLength: 1,
					select: function(event, ui) {
						if (ui.item) {}
					}
				});

			}

			function trpdd_add_new_list($vl, $key) {
				$vl = $('<li><input type="text" name="lab_' + $key + '[]" value="' + $vl + '" class="auto_' + $key + '"/></li>');
				$('#list_' + $key).append($vl);
				trpdd_setup_auto_complete();
			}
			for (i in $insurance) {
				trpdd_add_new_list($insurance[i], 'auto_rej_insurance');
			}

			$('.list_properties a.addnew').click(function() {
				trpdd_add_new_list('', $(this).data('rel'));
			})
			$('body').on('keypress', 'input#lab_dba_init', function(e) {
				var key = e.keyCode;
				if (key >= 48 && key <= 57) {
					e.preventDefault();
				}

			});

			function fetchLabs(page = 1) {

				$.ajax({
					type: 'POST',
					data: {
						trajax: 'fetch_lab_operation',
						lab_id: <?php echo $id; ?>,
					},
					success: function(res) {
						if (res.success) {
							updateTable(res.data.labs)
						}
					},
					error: function() {
						alert('Error loading data');
					}
				});
			}

			function updateTable(labs) {
				const tbody = $('#labs-tbody');
				tbody.empty();

				if (labs.length === 0) {
					tbody.append('<tr><td colspan="7" class="no-results">No results found.</td></tr>');
					return;
				}

				$.each(labs, function(index, lab) {
					const row = `
					<tr data-id="${lab.ID}">
						<td>${lab.ID || ''}</td>
						<td><input type="checkbox" name="lab_mon" ${lab.lab_mon == 1 ? 'checked' : ''}/></td>
						<td><input type="checkbox" name="lab_tue" ${lab.lab_tue == 1 ? 'checked' : ''}/></td>
						<td><input type="checkbox" name="lab_wed" ${lab.lab_wed == 1 ? 'checked' : ''}/></td>
						<td><input type="checkbox" name="lab_thu" ${lab.lab_thu == 1 ? 'checked' : ''}/></td>
						<td><input type="checkbox" name="lab_fri" ${lab.lab_fri == 1 ? 'checked' : ''}/></td>
						<td><input type="checkbox" name="lab_sat" ${lab.lab_sat == 1 ? 'checked' : ''}/></td>
						<td><input type="checkbox" name="lab_sun" ${lab.lab_sun == 1 ? 'checked' : ''}/></td>
						<td>
							<select name="target_tat">
								${[12, 24, 36, 48, 60, 72].map(function(hour) {
									return '<option value="' + hour + '" ' + (parseInt(lab.target_tat || 36, 10) === hour ? 'selected' : '') + '>' + hour + 'h</option>';
								}).join('')}
							</select>
						</td>
						<td><input type="text" class="hasdate" name="from_date" placeholder="yyyy-mm-dd" value="${lab.from_date || ''}" /></td>
						<td><input type="text" class="hasdate" name="to_date" placeholder="yyyy-mm-dd" value="${lab.to_date || ''}" /></td>
						<td><input type="text" class="hasdate pointer-events-none" name="created" placeholder="yyyy-mm-dd" value="${lab.created || ''}" /></td>
						<td>
							<button class="button update-lab-operation">Update</button>
							<button class="button delete-lab-operation">Delete</button>
						</td>
					</tr>
				`;
					tbody.append(row);
				});
				$('.update-lab-operation').off('click').on('click', function() {
					const $row = $(this).closest('tr');
					const labId = $row.data('id');

					const labData = {
						ID: labId,
						lab_mon: $row.find('input[name="lab_mon"]').is(':checked') ? 1 : 0,
						lab_tue: $row.find('input[name="lab_tue"]').is(':checked') ? 1 : 0,
						lab_wed: $row.find('input[name="lab_wed"]').is(':checked') ? 1 : 0,
						lab_thu: $row.find('input[name="lab_thu"]').is(':checked') ? 1 : 0,
						lab_fri: $row.find('input[name="lab_fri"]').is(':checked') ? 1 : 0,
						lab_sat: $row.find('input[name="lab_sat"]').is(':checked') ? 1 : 0,
						lab_sun: $row.find('input[name="lab_sun"]').is(':checked') ? 1 : 0,
						target_tat: $row.find('select[name="target_tat"]').val(),
						from_date: $row.find('input[name="from_date"]').val(),
						to_date: $row.find('input[name="to_date"]').val(),
						created: $row.find('input[name="created"]').val()
					};

					$.ajax({
						type: 'POST',
						data: {
							trajax: 'update_lab_operation',
							lab_operation: labData
						},
						success: function(res) {
							if (res.success) {
								alert('✅ ' + res.message);
							} else {
								alert('❌ ' + res.message);
							}
							location.reload();
						},
						error: function(e) {
							alert('Error updating.');
						}
					});
				});
				$('.delete-lab-operation').off('click').on('click', function() {
					const $row = $(this).closest('tr');
					const labId = $row.data('id');

					$.ajax({
						type: 'POST',
						data: {
							trajax: 'delete_lab_operation',
							lab_ID: labId
						},
						success: function(res) {
							if (res.success) {
								alert('✅ ' + res.message);
							} else {
								alert('❌ ' + res.message);
							}
							location.reload();
						},
						error: function(e) {
							alert('Error updating.');
						}
					});
				});
				$(".hasdate").datepicker({
					changeMonth: true,
					changeYear: true,
					numberOfMonths: 1,
					dateFormat: 'yy-mm-dd'
				});
			}

			function openModal($modal) {
				$modal.addClass('active');
			}

			function closeModal($modal) {
				$modal.removeClass('active');
			}

			function bindModalEvents($modal, closeCallback) {
				$modal.find('.modal-close, .modal-btn-cancel').off('click').on('click', function() {
					closeModal($modal);
				});

				$modal.off('click').on('click', function(e) {
					if (!$(e.target).closest('.modal-content').length) {
						closeModal($modal);
					}
				});

				$(document).off('keydown.modal-' + $modal.attr('id')).on('keydown.modal-' + $modal.attr('id'), function(e) {
					if (e.key === 'Escape' && $modal.hasClass('active')) {
						closeModal($modal);
					}
				});
			}
			const $labModal = $('#lab-modal')
			$('.lab-operation-btn').on('click', function() {
				fetchLabs();
				openModal($labModal);
			});
				bindModalEvents($labModal);

				$('#btn_lab_history').on('click', function() {
					$.post('admin.php?page=ci_manage_labs', {
						trajax: 'list_lab_history',
						lab_ID: '<?php echo intval($id); ?>'
					}, function(response) {
						if (window.Swal && typeof Swal.fire === 'function') {
							Swal.fire({
								title: 'Lab history',
								html: response,
								width: '90vw',
								showCloseButton: true,
								confirmButtonText: 'Close',
								didOpen: () => {
									$('.lab-history-date').off('click').on('click', function() {
										$(this).toggleClass('is-collapsed');
										$(this).closest('.lab-history-group').toggleClass('is-collapsed');
									});
								}
							});
						} else {
							alert($(response).text());
						}
					});
					return false;
				});

				$labForm.on('submit', async function(e) {
					const trackedChanges = getChangedTrackedLabGroups();

					if (allowLabFormSubmit || !trackedChanges.labels.length) {
						if (!trackedChanges.labels.length) {
							$labEffectiveDate.val('');
						}
						return true;
					}

					e.preventDefault();

					const effectiveDate = await askTrackedLabEffectiveDate(trackedChanges);
				if (!effectiveDate) {
					$labEffectiveDate.val('');
					return false;
				}

				$labEffectiveDate.val(effectiveDate);
				allowLabFormSubmit = true;
				$labForm.trigger('submit');
				return false;
			});

		})
	</script>
	<style>
		.modal-overlay {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.5);
			z-index: 100000;
			align-items: center;
			justify-content: center;
		}

		.modal-overlay.active {
			display: flex;
		}

		.labs-table {
			width: 100%;
			margin-top: 5px;
			border-collapse: collapse;
		}

		.labs-table th {
			background: #f5f5f5;
			padding: 12px;
			text-align: left;
			font-weight: 600;
			border-bottom: 2px solid #ddd;
		}

		.labs-table td {
			padding: 12px;
			border-bottom: 1px solid #eee;
		}

		.lab-logs-head-table {
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.modal-close {
			background: none;
			border: none;
			font-size: 28px;
			cursor: pointer;
			color: #999;
			padding: 0;
			width: 30px;
			height: 30px;
			line-height: 1;
			transition: color 0.3s;
		}

		.modal-content {
			background: white;
			width: 90%;
			padding: 30px;
			border-radius: 8px;
			max-width: 1200px;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
			animation: modalSlideIn 0.3s ease-out;
		}
	</style>
<?php
} else {
	if ($act == 'delete') {
		$lab_id = $_REQUEST['id'];
		$labdata = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);
		$wpdb->delete('ci_labs', array('lab_ID' => $lab_id));
		Tr_Session_Class::add('Deleted Lab #' . $lab_id);
		trpdd_log_user(get_current_user_id(), 'del_lab', json_encode($labdata), $lab_id, 'lab');
	}

	$labs = $wpdb->get_results("select lab.*,
		(select count(*) from ci_clinic cli where cli.`cli_lab-ID` = lab.lab_ID) as count_accounts
		from ci_labs lab
		where (lab.lab_is_ref=0 or lab_is_ref = 2)
		order by lab.lab_ID asc");
?>
	<div class="wrap">
		<h2>Labs <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a></h2>
		<?php Tr_Session_Class::show() ?>

		<div class="wraptbl">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead>
					<tr>
						<th style="width:50px">#</th>
						<th>Name</th>
						<th>DBA</th>
						<th style="width:80px">Init</th>
						<th style="width:100px">Specimen Fee</th>
						<th style="min-width: 200px;width:20%">Email</th>
						<th style="width:80px">Accounts</th>
						<th style="width:80px">Users</th>
						<th style="width:80px">Site#</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($labs as $i => $lab) {
						$lab->count_users = '';
					?>
						<tr>
							<td><?php echo $lab->lab_ID ?></td>
							<td><?php echo $lab->lab_name ?></td>
							<td><?php echo $lab->lab_dba ?></td>
							<td><?php echo $lab->lab_dba_init ?></td>
							<td><?php echo $lab->lab_specimen_fee ?></td>
							<td><?php echo $lab->lab_email ?></td>
							<td><?php echo $lab->count_accounts ?></td>
							<td><?php echo $lab->count_users ?></td>
							<td>
								<?php if ($lab->lab_site > 0) :
									$lab_url = trpdd_get_lab_url($lab->lab_site); ?>
									<a href="<?php echo 'http://' . $lab_url ?>" target="_blank">
										<?php echo $lab->lab_site ?></a>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $lab->lab_ID)) ?>">Edit</a>
								<?php if (isset($_GET['debug'])) { ?>-
								<a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $lab->lab_ID)) ?>">Delete</a>
							<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>

	<script>
		jQuery(function($) {
			$('a.delete').click(function() {
				if (confirm('Do you want delete?') == false) return false;
			})
		})
	</script>
<?php
}
?>
