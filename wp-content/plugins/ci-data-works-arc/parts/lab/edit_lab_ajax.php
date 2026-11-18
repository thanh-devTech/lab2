<?php
global $wpdb, $ci_all_btypes, $is_ci_admin;

$sign_dir = tr_get_signature_dir();
$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos';
$logo_url = $upload_dir['baseurl'] . '/logos/';

if ($trajax == 'reg_lab') {
	$lab 		= tr_pdd_stripslashes_deep($_POST['tblci_labs']);
	$lab_id 	= $lab['lab_ID'];
	$lab['lab_email'] = trim($lab['lab_email']);
	$email 		= trim($lab['lab_email']);
	$lab_effective_date = isset($_POST['lab_effective_date']) ? sanitize_text_field($_POST['lab_effective_date']) : '';

	if (isset($_POST['lab_additional'])) {
		$lab['lab_additional'] = tr_pdd_stripslashes_deep($_POST['lab_additional']);
	}

	if ($lab_id > 0 && empty($lab['lab_dba_init'])) {
		$lab['lab_dba_init'] = (string) $wpdb->get_var($wpdb->prepare(
			"SELECT lab_dba_init FROM ci_labs WHERE lab_ID = %d",
			$lab_id
		));
	}

	$lab['lab_pcr_panels'] = implode(',', isset($lab['lab_pcr_panels']) && is_array($lab['lab_pcr_panels']) ? $lab['lab_pcr_panels'] : array());
	$lab['lab_ref_test_type'] = implode(',', isset($lab['lab_ref_test_type']) && is_array($lab['lab_ref_test_type']) ? $lab['lab_ref_test_type'] : array());

	$lab['lab_insurance'] = @json_encode($_POST['lab_insurance']);
	$lab['lab_states'] = @json_encode($_POST['lab_states']);
	$lab['lab_exclusions'] = @json_encode($_POST['lab_exclusions']);

	$user = wp_get_current_user();
	//check db version
	$db_version = get_option('TRCPDD_VS');
	if ($db_version != TRCPDD_VS) {
		wp_schedule_single_event(time(), 'ci_check_upload_apis');
		trpdd_plugin_activate();
	}

	if (empty($lab['lab_name'])) {
		wp_send_json([
			'success' => false,
			'message' => 'Lab Name not Null!'
		]);
	}

	if (empty($lab['lab_dba_init'])) {
		wp_send_json([
			'success' => false,
			'message' => 'Lab DBA INIT not Null!'
		]);
	}

	function updateClinics($wpdb, $lab_id)
	{
		if (isset($_POST['clinics'])) {
			$wpdb->update('ci_clinic', ['cli_ref_lab_ID' => 0], ['cli_ref_lab_ID' => $lab_id]);
			foreach ($_POST['clinics'] as $cid) {
				$wpdb->update('ci_clinic', array('cli_ref_lab_ID' => $lab_id), array('cID' => $cid));
			}
		}
	}

	function UpdateOperation($wpdb, $lab_id, $days_of_operation, $effective_date = '')
	{

		$days_of_operation['lab_ID'] = $lab_id;

		$current_date = !empty($effective_date) && strtotime($effective_date) ? date_i18n("Y-m-d", strtotime($effective_date)) : date_i18n("Y-m-d");

		// Lấy toàn bộ operation theo timeline
		$prev_operation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
             FROM ci_lab_operation
             WHERE lab_ID = %d
             ORDER BY ID DESC 
			 LIMIT 1",
				$lab_id
			),
			ARRAY_A
		);

		if (empty($prev_operation)) {
			$operations = [];
			foreach ($days_of_operation as $dk => $dv) {
				$prev_operation[$dk] = 1;
			}
		}

		//check same option 
		$same_options = true;
		foreach ($days_of_operation as $dk => $dv) {

			if ($prev_operation[$dk] != $dv) {
				$same_options = false;
				break;
			}
		}

		if ($same_options) {
			return;
		}

		//update prev operation to_date

		if (isset($prev_operation['ID']) && $prev_operation['ID'] > 0) {
			if (!empty($prev_operation['from_date']) && $prev_operation['from_date'] >= $current_date) {
				$update_operation = $days_of_operation;
				$update_operation['from_date'] = $current_date;

				$wpdb->update(
					'ci_lab_operation',
					$update_operation,
					['ID' => $prev_operation['ID']]
				);
				trpdd_log_lab_change('update_lab_operation', $lab_id, $update_operation, $prev_operation);
				return;
			}
			$wpdb->update(
				'ci_lab_operation',
				['to_date' => date_i18n("Y-m-d", strtotime($current_date . ' -1 day'))],
				['ID' => $prev_operation['ID']]
			);
		}


		$days_of_operation['from_date'] = $current_date;
		$days_of_operation['created']   = $current_date;

		$wpdb->insert('ci_lab_operation', $days_of_operation);
		trpdd_log_lab_change('add_lab_operation', $lab_id, $days_of_operation, $prev_operation);
	}

	unset($lab['lab_ID']);
	$days_of_operation = [];
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

	if (!$is_ci_admin) {
		unset($lab['lab_status']);
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

		$update_lab = array();

		if (isset($_FILES['lab_logo'])) {
			//end check
			$logoname = 'lab_' . $lab_id;
			$logo = $this->uploadfile($_FILES['lab_logo'], $logoname, $logo_dir);
			if (!empty($logo)) {
				$lab['lab_logo'] = $logo;
				$update_lab['logo'] = $logo_url . '' . $logo;
			}
		}

		if (isset($_FILES['lab_results_logo'])) {
			$results_logoname = 'lab_results_' . $lab_id;
			$results_logo = $this->uploadfile($_FILES['lab_results_logo'], $results_logoname, $logo_dir);
			if (!empty($results_logo)) {
				$lab['lab_results_logo'] = $results_logo;
				$update_lab['results_logo'] = $logo_url . '' . $results_logo;
			}
		}

		//upload lab_director_signature
		$director_signature_name = pathinfo(wp_unique_filename($sign_dir['path'], $lab_id . 'director.png'), PATHINFO_FILENAME);
		$director_sign = tr_save_signature($director_signature_name, $_POST['lab_director_signature'], $_FILES['lab_director_signature']);
		if ($director_sign) {
			$lab['lab_director_signature'] = $director_sign;
			$update_lab['signature'] = $director_sign;
		}
		$rs = $wpdb->update('ci_labs', $lab, array('lab_ID' => $lab_id));

		if ($rs === false) {
			wp_send_json([
				'success' => false,
				'message' => 'Update error!',
				'error'   => $wpdb->last_error
			]);
		} elseif ($rs === 0) {
			updateClinics($wpdb, $lab_id);
			UpdateOperation($wpdb, $lab_id, $days_of_operation, $lab_effective_date);
			CI_Base_Lab::save_lab_field_history($lab_id, $oldlab, $lab, $lab_effective_date, get_current_user_id());
			trpdd_log_lab_change('update_lab', $lab_id, $lab, $oldlab);
			wp_send_json([
				'success' => true,
				'message' => 'No data changed',
				'is_add'  => false,
				'data_update' => $update_lab,
			]);
		} else {
			updateClinics($wpdb, $lab_id);
			UpdateOperation($wpdb, $lab_id, $days_of_operation, $lab_effective_date);
			CI_Base_Lab::save_lab_field_history($lab_id, $oldlab, $lab, $lab_effective_date, get_current_user_id());
			trpdd_log_lab_change('update_lab', $lab_id, $lab, $oldlab);
			wp_send_json([
				'success' => true,
				'message' => 'Update successfully!',
				'is_add'  => false,
				'data_update' => $update_lab,
			]);
		}
	} else {
		$oldlab = array();
		$wpdb->insert('ci_labs', $lab);
		$lab_id = $wpdb->insert_id;
		$update_lab = array();

		if (isset($_FILES['lab_logo'])) {
			//end check
			$logoname = 'lab_' . $lab_id;
			$logo = $this->uploadfile($_FILES['lab_logo'], $logoname, $logo_dir);
			if (!empty($logo)) {
				$lab['lab_logo'] = $logo;
				$update_lab['logo'] = $logo_url . '' . $logo;
			}
		}

		if (isset($_FILES['lab_results_logo'])) {
			$results_logoname = 'lab_results_' . $lab_id;
			$results_logo = $this->uploadfile($_FILES['lab_results_logo'], $results_logoname, $logo_dir);
			if (!empty($results_logo)) {
				$lab['lab_results_logo'] = $results_logo;
				$update_lab['results_logo'] = $logo_url . '' . $results_logo;
			}
		}

		//upload lab_director_signature
		$director_signature_name = pathinfo(wp_unique_filename($sign_dir['path'], $lab_id . 'director.png'), PATHINFO_FILENAME);
		$director_sign = tr_save_signature($director_signature_name, $_POST['lab_director_signature'], $_FILES['lab_director_signature']);
		if ($director_sign) {
			$lab['lab_director_signature'] = $director_sign;
			$update_lab['signature'] = $director_sign;
		}
		$rs = $wpdb->update('ci_labs', $lab, array('lab_ID' => $lab_id));
		updateClinics($wpdb, $lab_id);
		UpdateOperation($wpdb, $lab_id, $days_of_operation, $lab_effective_date);
		CI_Base_Lab::save_lab_field_history($lab_id, $oldlab, $lab, $lab_effective_date, get_current_user_id());
		trpdd_log_lab_change('add_lab', $lab_id, $lab, $oldlab);
		wp_send_json([
			'success' => true,
			'message' => 'Add successfully!',
			'data'    => $lab_id,
			'is_add' => true,
			'data_update' => $update_lab
		]);
	}
	exit;
}

if ($trajax === 'remove_logo') {
	$lab_id = intval($_POST['lab_ID']);

	$files = glob($logo_dir . '/lab_' . $lab_id . '.*');

	if (!empty($files)) {
		foreach ($files as $file) {
			unlink($file);
		}
		echo 'Remove Success';
	} else {
		echo 'No logo to remove';
	}
	exit;
}

if ($trajax === 'remove_results_logo') {
	$lab_id = intval($_POST['lab_ID']);
	$files = glob($logo_dir . '/lab_results_' . $lab_id . '.*');

	if (!empty($files)) {
		foreach ($files as $file) {
			unlink($file);
		}
	}

	$wpdb->update('ci_labs', array('lab_results_logo' => ''), array('lab_ID' => $lab_id));
	echo 'Remove Success';
	exit;
}

if ($trajax === 'list_operation_logs') {
	$lab_id = intval($_POST['lab_ID']);
	$sql = $wpdb->prepare("
        SELECT *
        FROM ci_lab_operation
		where lab_ID = {$lab_id}
        ORDER BY ID DESC
    ");

	$operation = $wpdb->get_results($sql);
?>
	<style>
		.custom-table {
			width: 100%;
			border-collapse: collapse;
			font-size: 13px;
		}

		.custom-table th,
		.custom-table td {
			padding: 2px;
			text-align: left;
		}

		.custom-table tbody tr:nth-child(odd) {
			background-color: #f9f9f9;
		}

		.custom-table tbody tr:nth-child(even) {
			background-color: #ffffff;
		}

		.custom-table tbody tr:hover {
			background-color: #d9dde2ff;
			transition: background-color 0.3s ease;
			cursor: pointer;
		}

		.custom-table th {
			background-color: #8e8e8eff;
			color: #fff;
		}
	</style>
	<table class="custom-table table table-sm table-striped table-hover">
		<thead>
			<tr>
				<th>ID</th>
				<th>Mon</th>
				<th>Tue</th>
				<th>Wed</th>
				<th>Thu</th>
				<th>Fri</th>
				<th>Sat</th>
				<th>Sun</th>
				<th>Target TAT</th>
				<th>From date</th>
				<th>To date</th>
				<th>Created</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody id="labs-tbody">
			<?php
			foreach ($operation as $item) {
			?>
				<tr data-id="<?php echo $item->ID; ?>">
					<td><?php echo $item->ID; ?></td>
					<td><input type="checkbox" name="lab_mon" <?php echo $item->lab_mon == 1 ? 'checked' : '' ?> /></td>
					<td><input type="checkbox" name="lab_tue" <?php echo $item->lab_tue == 1 ? 'checked' : '' ?> /></td>
					<td><input type="checkbox" name="lab_wed" <?php echo $item->lab_wed == 1 ? 'checked' : '' ?> /></td>
					<td><input type="checkbox" name="lab_thu" <?php echo $item->lab_thu == 1 ? 'checked' : '' ?> /></td>
					<td><input type="checkbox" name="lab_fri" <?php echo $item->lab_fri == 1 ? 'checked' : '' ?> /></td>
					<td><input type="checkbox" name="lab_sat" <?php echo $item->lab_sat == 1 ? 'checked' : '' ?> /></td>
					<td><input type="checkbox" name="lab_sun" <?php echo $item->lab_sun == 1 ? 'checked' : '' ?> /></td>
					<td>
						<select name="target_tat">
							<?php foreach (array(12, 24, 36, 48, 60, 72) as $target_tat) { ?>
								<option value="<?php echo $target_tat; ?>" <?php selected(intval($item->target_tat ?: 36), $target_tat); ?>><?php echo $target_tat; ?>h</option>
							<?php } ?>
						</select>
					</td>
					<td><input type="text" class="hasdate" name="from_date" placeholder="yyyy-mm-dd" value="<?php echo !empty($item->from_date) ? $item->from_date : ''; ?>"></td>
					<td><input type="text" class="hasdate" name="to_date" placeholder="yyyy-mm-dd" value="<?php echo !empty($item->to_date) ? $item->to_date : ''; ?>"></td>
					<td><input type="text" class="hasdate" name="created" placeholder="yyyy-mm-dd" value="<?php echo !empty($item->created) ? $item->created : ''; ?>"></td>
					<td>
						<button class="update-lab-operation cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5">
							<svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#878787">
								<g id="SVGRepo_bgCarrier" stroke-width="0"></g>
								<g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
								<g id="SVGRepo_iconCarrier">
									<path d="M13 3H7C5.89543 3 5 3.89543 5 5V10M13 3L19 9M13 3V8C13 8.55228 13.4477 9 14 9H19M19 9V19C19 20.1046 18.1046 21 17 21H10C7.79086 21 6 19.2091 6 17V17C6 14.7909 7.79086 13 10 13H13M13 13L10 10M13 13L10 16" stroke="#404040" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
								</g>
							</svg>
							Update
						</button>

						<button data-id_log="<?php echo $item->ID; ?>" class="delete-lab-operation cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5">
							<svg width="24px" height="14px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<g id="SVGRepo_bgCarrier" stroke-width="0"></g>
								<g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
								<g id="SVGRepo_iconCarrier">
									<path fill-rule="evenodd" clip-rule="evenodd" d="M6.30958 3.54424C7.06741 2.56989 8.23263 2 9.46699 2H20.9997C21.8359 2 22.6103 2.37473 23.1614 2.99465C23.709 3.61073 23.9997 4.42358 23.9997 5.25V18.75C23.9997 19.5764 23.709 20.3893 23.1614 21.0054C22.6103 21.6253 21.8359 22 20.9997 22H9.46699C8.23263 22 7.06741 21.4301 6.30958 20.4558L0.687897 13.2279C0.126171 12.5057 0.126169 11.4943 0.687897 10.7721L6.30958 3.54424ZM10.2498 7.04289C10.6403 6.65237 11.2734 6.65237 11.664 7.04289L14.4924 9.87132L17.3208 7.04289C17.7113 6.65237 18.3445 6.65237 18.735 7.04289L19.4421 7.75C19.8327 8.14052 19.8327 8.77369 19.4421 9.16421L16.6137 11.9926L19.4421 14.8211C19.8327 15.2116 19.8327 15.8448 19.4421 16.2353L18.735 16.9424C18.3445 17.3329 17.7113 17.3329 17.3208 16.9424L14.4924 14.114L11.664 16.9424C11.2734 17.3329 10.6403 17.3329 10.2498 16.9424L9.54265 16.2353C9.15212 15.8448 9.15212 15.2116 9.54265 14.8211L12.3711 11.9926L9.54265 9.16421C9.15212 8.77369 9.15212 8.14052 9.54265 7.75L10.2498 7.04289Z" fill="#000000"></path>
								</g>
							</svg>
							Delete
						</button>
					</td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
<?php
	exit;
}

if ($trajax == 'update_operation_logs') {
	$lab_operation = isset($_POST['lab_operation']) && is_array($_POST['lab_operation']) ? $_POST['lab_operation'] : array();
	$log_id = isset($lab_operation['ID']) ? intval($lab_operation['ID']) : 0;
	if ($log_id <= 0) {
		wp_send_json_error('Invalid log ID');
	}

	$old_operation = $wpdb->get_row($wpdb->prepare("SELECT * FROM ci_lab_operation WHERE ID = %d", $log_id), ARRAY_A);
	if (!$old_operation) {
		wp_send_json_error('Operation log not found');
	}

	$target_tat_options = array(12, 24, 36, 48, 60, 72);
	$update_operation = array();
	foreach ($keys_operation as $key => $label) {
		$update_operation[$key] = !empty($lab_operation[$key]) ? 1 : 0;
	}
	$update_operation['target_tat'] = isset($lab_operation['target_tat']) && in_array(intval($lab_operation['target_tat']), $target_tat_options) ? intval($lab_operation['target_tat']) : 36;
	$update_operation['from_date'] = isset($lab_operation['from_date']) ? sanitize_text_field($lab_operation['from_date']) : '';
	$update_operation['to_date'] = isset($lab_operation['to_date']) ? sanitize_text_field($lab_operation['to_date']) : '';

	$updated = $wpdb->update(
		'ci_lab_operation',
		$update_operation,
		array('ID' => $log_id)
	);

	if ($updated !== false) {
		trpdd_log_lab_change('update_lab_operation', isset($old_operation['lab_ID']) ? $old_operation['lab_ID'] : 0, $update_operation, $old_operation);
		wp_send_json_success('Update success');
	}

	wp_send_json_error('Update failed: ' . $wpdb->last_error);
}

if ($trajax === 'list_lab_history') {
	if (!$is_ci_admin) {
		echo '<div class="notice notice-error" style="margin:0;">Access denied.</div>';
		exit;
	}

	$lab_id = intval($_POST['lab_ID']);
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

if ($trajax == 'list_ref_lab_clinic') {

	$id = $_REQUEST['lab_ID'];
	$selected_clinics = [];

	if (!empty($id)) {
		$selected_clinics = $wpdb->get_col($wpdb->prepare("SELECT cID FROM ci_clinic WHERE cli_ref_lab_ID = %d", $id));
	}

	$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$id}");
	$clinics = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cli_clinic");
?>
	<div class="wrap">
		<h2>Clinics for <?php echo $lab->lab_dba_init ?>
			<a class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $id)) ?>">Edit Ref Lab</a>
			<a class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5" href="<?php Tr_Base_Class_V6::link(array('act' => 'list', 'id' => '')) ?>">All Ref Lab</a>
		</h2>
		<div method="post" style="margin-top:15px;">
			<label>Clinics</label>
			<select id="list_clinics" name="clinics[]" multiple class="js_select2" style="width:80%">
				<?php
				foreach ($clinics as $clinic) {
					$selected = $clinic->cli_ref_lab_ID == $id ? 'selected' : '';
					echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
				}
				?>
			</select>
		</div>
	</div>
<?php
	exit;
}

if ($trajax == 'list_ref_lab_fees') {
	$id = $_REQUEST['lab_ID'];
	$lab_fee = $wpdb->get_row("select * from ci_labs where lab_ID = {$id}");
	$labs = $wpdb->get_results("select lab.lab_ID,lab.lab_dba,lab.lab_dba_init,lrf.lab_specimen_fee
								from ci_labs lab
								left JOIN ci_lab_ref_fees lrf ON lrf.lab_ID = lab.lab_ID and lrf.ref_lab_ID = {$id}
								where (lab.lab_is_ref=0 or lab.lab_is_ref=2)
								order by lab_dba_init");
?>
	<div class="wrap">
		<h2>Fees for <?php echo $lab_fee->lab_dba_init ?></h2>
		<form id="frm_fees" method="post">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead>
					<tr>
						<th style="width:50px">#</th>
						<th>Lab</th>
						<th>INIT</th>
						<th>Fee</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($labs as $i => $lab) {
					?>
						<tr>
							<td><?php echo $i + 1 ?></td>
							<td><?php echo $lab->lab_dba ?></td>
							<td><?php echo $lab->lab_dba_init ?></td>
							<td><input type="text" name="fees[<?php echo $lab->lab_ID ?>]" value="<?php echo $lab->lab_specimen_fee ?>" /></td>
						</tr>
					<?php
					}
					?>
				</tbody>
			</table>
		</form>
	</div>
<?php
	exit;
}

if ($trajax == 'update_list_ref_lab_clinic') {
	$id = $_REQUEST['lab_ID'];
	$wpdb->update('ci_clinic', ['cli_ref_lab_ID' => 0], ['cli_ref_lab_ID' => $id]);
	if (isset($_POST['clinics'])) {
		foreach ($_POST['clinics'] as $cid) {
			$wpdb->update('ci_clinic', array('cli_ref_lab_ID' => $id), array('cID' => $cid));
		}
	}
	exit;
}

if ($trajax == 'update_list_ref_lab_fees') {
	$id = $_REQUEST['lab_ID'];

	if (isset($_POST['fees'])) {
		foreach ($_POST['fees'] as $lab_id => $fee) {
			$exists = $wpdb->get_var("select lab_ID from ci_lab_ref_fees where lab_ID = {$lab_id} and ref_lab_ID = {$id}");
			if ($exists > 0) {
				$data = array('lab_specimen_fee' => $fee);
				$where = array('lab_ID' => $lab_id, 'ref_lab_ID' => $id);
				$wpdb->update('ci_lab_ref_fees', $data, $where);
			} else {
				$data = array('lab_specimen_fee' => $fee, 'lab_ID' => $lab_id, 'ref_lab_ID' => $id);
				$wpdb->insert('ci_lab_ref_fees', $data);
			}
		}
	}
	exit;
}

if ($trajax == 'delete_operation_logs') {

	$log_id = intval($_POST['id_log']);
	if ($log_id <= 0) wp_send_json_error('Invalid log ID');

	$old_operation = $wpdb->get_row($wpdb->prepare("SELECT * FROM ci_lab_operation WHERE ID = %d", $log_id), ARRAY_A);
	$deleted = $wpdb->delete(
		'ci_lab_operation',
		['ID' => $log_id],
		['%d']
	);

	if ($deleted !== false) {
		trpdd_log_user(get_current_user_id(), 'delete_lab_operation', $old_operation, isset($old_operation['lab_ID']) ? $old_operation['lab_ID'] : 0, 'lab', '', '', isset($old_operation['lab_ID']) ? $old_operation['lab_ID'] : '');
		wp_send_json_success('Delete success');
	} else {
		wp_send_json_error('Delete failed');
	}

	exit;
}

if ($trajax == 'save_state_lab') {
	$groupbytestids = array();
	$groupcodebytestids = array();
	$covid_testsids = array();
	$btypes = array();
	$options = array();
	$backup_dir = WP_CONTENT_DIR . '/backups/report_to_state/';

	foreach (tr_pdd_get_batch_types() as $bt) {
		$btypes[] = esc_sql($bt);
	}

	if (!file_exists($backup_dir)) {
		wp_mkdir_p($backup_dir);
	}

	if (!empty($btypes)) {
		$btypes_str = "'" . implode("','", $btypes) . "'";
		$rsgroups = $wpdb->get_results("
			select tg_ID,
				tg_name,
				tg_cpt_code,
				tg_is_covid,
				tg_type
			from ci_test_groups
			where tg_show_req = 1
				and tg_parent = 0
				and tg_type in ({$btypes_str})
			order by tg_position asc
		", ARRAY_A);

		foreach ($rsgroups as $group) {
			$tests = $wpdb->get_results("
				select te_ID, te_name, te_loinc_code
				from ci_test_panels te
				JOIN ci_test_groups tg ON te.te_group_id = tg.tg_ID
				where tg.tg_ID = {$group['tg_ID']} or tg.tg_parent = {$group['tg_ID']}
			", ARRAY_A);

			foreach ($tests as $te) {
				$groupbytestids[$te['te_ID']] = $group['tg_name'];
				$groupcodebytestids[$te['te_ID']] = $group['tg_cpt_code'];
				if ($group['tg_is_covid']) {
					$covid_testsids[] = (string)$te['te_ID'];
				}
			}
		}
	}

	$old_opts = get_option('ci_send_hub_tests', array());
	$old_optswkeys = array();
	foreach ($old_opts as $old_opt) {
		if (empty($old_opt['tid']) || empty($old_opt['state'])) {
			continue;
		}
		$old_optswkeys[$old_opt['tid'] . '_' . $old_opt['state']] = $old_opt;
	}

	$backupfile = date_i18n('Y-m-dH_i_s') . '_report_to_state.bk';
	$current_user = wp_get_current_user();
	file_put_contents($backup_dir . $backupfile, wp_json_encode(array(
		'meta' => array(
			'created' => date_i18n('Y-m-d H:i:s'),
			'user_id' => get_current_user_id(),
			'user_name' => $current_user && !empty($current_user->display_name) ? $current_user->display_name : 'Unknown',
			'action' => 'save',
		),
		'options' => $old_opts,
	)));

	$raw_options = isset($_REQUEST['options']) && is_array($_REQUEST['options']) ? tr_pdd_stripslashes_deep($_REQUEST['options']) : array();
	foreach ($raw_options as $i => $arr) {
		if (!is_array($arr) || empty($arr['tid']) || empty($arr['state'])) {
			continue;
		}

		$arr['tid'] = (string)$arr['tid'];
		$arr['state'] = strtoupper(trim((string)$arr['state']));
		$arr['btype'] = !empty($arr['btype']) ? (string)$arr['btype'] : 'pcr';

		if (!isset($arr['cond_only_tids']) && isset($arr['cond_only_tid']) && $arr['cond_only_tid'] !== '') {
			$arr['cond_only_tids'] = array($arr['cond_only_tid']);
		}
		if (!isset($arr['cond_only_tids']) || !is_array($arr['cond_only_tids'])) {
			$arr['cond_only_tids'] = array();
		} else {
			$arr['cond_only_tids'] = array_values(array_filter(array_map('strval', $arr['cond_only_tids'])));
		}

		$opt_key = $arr['tid'] . '_' . $arr['state'];
		if (!isset($arr['status']) && isset($old_optswkeys[$opt_key]['status'])) {
			$arr['status'] = intval($old_optswkeys[$opt_key]['status']);
		}
		if (!isset($arr['status'])) {
			$arr['status'] = 0;
		}

		$arr['status'] = intval($arr['status']);
		$arr['group'] = isset($groupbytestids[$arr['tid']]) ? $groupbytestids[$arr['tid']] : '';
		$arr['group_code'] = isset($groupcodebytestids[$arr['tid']]) ? $groupcodebytestids[$arr['tid']] : '';
		$arr['iscovid'] = in_array($arr['tid'], $covid_testsids, true) ? 1 : 0;
		$options[$i] = $arr;
	}

	update_option('ci_send_hub_tests', $options);
	Tr_Session_Class::add("Configuration saved successfully.");

	if (function_exists('tr_client_send_hub')) {
		$url = get_bloginfo('url') . '/wsdl?wsdl';
		$key = '';
		$wsdl = $wpdb->get_row("select * from ci_wsdl_keys where wsdl_desc like '%Hub%'");
		if (!$wsdl) {
			$lab_id = CI_Base_Lab::get_lab_id_by_blog_id();
			$key = wp_generate_password(15);
			$wpdb->insert('ci_wsdl_keys', array(
				'wsdl_key' => $key,
				'wsdl_lab_ID' => $lab_id,
				'wsdl_type' => '',
				'wsdl_desc' => 'Reporting Hub',
			));
		} else {
			$key = $wsdl->wsdl_key;
		}

		include_once(TRCPDD_PATH . 'api/ci_api_loinc.php');
		$loincapi = new CI_Api_LOINC();

		foreach ($options as $tk => $arr) {
			if (!empty($arr['tid'])) {
				$test = $wpdb->get_row("select * from ci_test_panels where te_ID = {$arr['tid']} ");
				if ($test) {
					$arr['test_name'] = $test->te_name;
					$arr['loinc_code'] = $test->te_loinc_code;
					$arr['snomed_code'] = $test->te_snomed_code;

					if (!empty($arr['loinc_code'])) {
						$arr['loinc_short'] = trpdd_get_test_meta($arr['tid'], '_loinc_short', '');
						if (empty($arr['loinc_short'])) {
							$arr['loinc_short'] = $loincapi->getShortName($arr['loinc_code']);
							if (!empty($arr['loinc_short'])) {
								trpdd_update_test_meta($arr['tid'], '_loinc_short', $arr['loinc_short']);
							}
						}

						if (empty($arr['loinc_short'])) {
							Tr_Session_Class::add_error("Unable to validate LOINC code: {$arr['loinc_code']}");
						}
					}
				}
			}

			if (isset($arr['cond_only_tids']) && count($arr['cond_only_tids']) > 0) {
				$arr['cond_only_test_names'] = array();
				$arr['cond_only_loinc_codes'] = array();
				foreach ($arr['cond_only_tids'] as $onltid) {
					$test2 = $wpdb->get_row("select te_name, te_loinc_code from ci_test_panels where te_ID = {$onltid} ");
					if (!$test2) {
						continue;
					}
					$arr['cond_only_test_names'][$onltid] = $test2->te_name;
					$arr['cond_only_loinc_codes'][$onltid] = $test2->te_loinc_code;
				}
			}

			$options[$tk] = $arr;
		}

		$specimens = array();
		$group_specimens = array();
		$specs = $wpdb->get_results("
			select ts.ts_name, ts.ts_code , g.tg_name
			from ci_test_specimens ts
			left JOIN ci_test_groups g ON ts.ts_group_id = g.tg_ID
			where ts.ts_code !=''
		");
		foreach ($specs as $spec) {
			if (!empty($spec->ts_code)) {
				$specimens[$spec->ts_name] = $spec->ts_code;
			}
			if (!empty($spec->tg_name)) {
				$group_specimens[$spec->tg_name] = $spec->ts_name;
			}
		}

		$rs = tr_client_send_hub(array(
			'action' => 'report_tests',
			'tests' => $options,
			'specimens' => $specimens,
			'group_specs' => $group_specimens,
			'url' => $url,
			'key' => $key,
		));
		if (empty($rs) || !isset($rs['status']) || $rs['status'] != 'ok') {
			Tr_Session_Class::add_error("Error when send config to Hub");
		}

		if (isset($_GET['debug'])) {
			var_dump($options);
			var_dump($rs);
		}
	}

	ob_start();
	Tr_Session_Class::show();
	$messages = ob_get_clean();
	wp_send_json(array('success' => true, 'data' => $messages));
	exit;
}

if ($trajax == 'list_state_lab_backups') {
	global $is_ci_admin;

	if (intval($is_ci_admin) !== 1) {
		wp_send_json_error('Sorry, you are not allowed to access state lab history.');
	}

	$backup_dir = WP_CONTENT_DIR . '/backups/report_to_state/';
	$backup_root = wp_normalize_path(trailingslashit($backup_dir));
	$backups = array();

	if (file_exists($backup_dir)) {
		$files = glob($backup_dir . '*.bk');
		if (is_array($files)) {
			usort($files, function ($a, $b) {
				return filemtime($b) <=> filemtime($a);
			});

			foreach (array_slice($files, 0, 50) as $file_path) {
				$file_path = wp_normalize_path($file_path);
				if (strpos($file_path, $backup_root) !== 0 || !is_file($file_path)) {
					continue;
				}

				$backup_created = date_i18n('Y-m-d H:i:s', filemtime($file_path));
				$backup_user = 'Unknown';
				$backup_user_id = '';
				$raw_backup = file_get_contents($file_path);
				$decoded_backup = json_decode($raw_backup, true);
				if (is_array($decoded_backup) && isset($decoded_backup['meta']) && is_array($decoded_backup['meta'])) {
					if (!empty($decoded_backup['meta']['created'])) {
						$backup_created = $decoded_backup['meta']['created'];
					}
					if (!empty($decoded_backup['meta']['user_name'])) {
						$backup_user = $decoded_backup['meta']['user_name'];
					}
					if (isset($decoded_backup['meta']['user_id']) && $decoded_backup['meta']['user_id'] !== '') {
						$backup_user_id = (string)$decoded_backup['meta']['user_id'];
					}
				}

				$backups[] = array(
					'file' => basename($file_path),
					'created' => $backup_created,
					'user' => $backup_user,
					'user_id' => $backup_user_id,
					'size' => size_format(filesize($file_path)),
				);
			}
		}
	}

	wp_send_json(array(
		'success' => true,
		'data' => array(
			'backups' => $backups,
		),
	));
	exit;
}

if ($trajax == 'restore_state_lab_backup') {
	global $is_ci_admin;

	if (intval($is_ci_admin) !== 1) {
		wp_send_json_error('Sorry, you are not allowed to restore state lab backups.');
	}

	$backup_file = isset($_POST['backup_file']) ? sanitize_file_name(wp_unslash($_POST['backup_file'])) : '';
	if ($backup_file === '') {
		wp_send_json_error('Backup file is required.');
	}

	$backup_dir = WP_CONTENT_DIR . '/backups/report_to_state/';
	$backup_root = wp_normalize_path(trailingslashit($backup_dir));
	$backup_path = wp_normalize_path($backup_dir . $backup_file);

	if (strpos($backup_path, $backup_root) !== 0 || !file_exists($backup_path)) {
		wp_send_json_error('Backup file not found.');
	}

	$raw_backup = file_get_contents($backup_path);
	$decoded_backup = json_decode($raw_backup, true);

	if (!is_array($decoded_backup)) {
		wp_send_json_error('Backup file is invalid.');
	}

	if (isset($decoded_backup['options']) && is_array($decoded_backup['options'])) {
		$decoded_backup = $decoded_backup['options'];
	}

	$groupbytestids = array();
	$groupcodebytestids = array();
	$covid_testsids = array();
	$btypes = array();
	$options = array();

	foreach (tr_pdd_get_batch_types() as $bt) {
		$btypes[] = esc_sql($bt);
	}

	if (!empty($btypes)) {
		$btypes_str = "'" . implode("','", $btypes) . "'";
		$rsgroups = $wpdb->get_results("
			select tg_ID,
				tg_name,
				tg_cpt_code,
				tg_is_covid,
				tg_type
			from ci_test_groups
			where tg_show_req = 1
				and tg_parent = 0
				and tg_type in ({$btypes_str})
			order by tg_position asc
		", ARRAY_A);

		foreach ($rsgroups as $group) {
			$tests = $wpdb->get_results("
				select te_ID, te_name, te_loinc_code
				from ci_test_panels te
				JOIN ci_test_groups tg ON te.te_group_id = tg.tg_ID
				where tg.tg_ID = {$group['tg_ID']} or tg.tg_parent = {$group['tg_ID']}
			", ARRAY_A);

			foreach ($tests as $te) {
				$groupbytestids[$te['te_ID']] = $group['tg_name'];
				$groupcodebytestids[$te['te_ID']] = $group['tg_cpt_code'];
				if ($group['tg_is_covid']) {
					$covid_testsids[] = (string)$te['te_ID'];
				}
			}
		}
	}

	$old_opts = get_option('ci_send_hub_tests', array());
	$old_optswkeys = array();
	foreach ($old_opts as $old_opt) {
		if (empty($old_opt['tid']) || empty($old_opt['state'])) {
			continue;
		}
		$old_optswkeys[$old_opt['tid'] . '_' . $old_opt['state']] = $old_opt;
	}

	foreach ($decoded_backup as $i => $arr) {
		if (!is_array($arr) || empty($arr['tid']) || empty($arr['state'])) {
			continue;
		}

		$arr['tid'] = (string)$arr['tid'];
		$arr['state'] = strtoupper(trim((string)$arr['state']));
		$arr['btype'] = !empty($arr['btype']) ? (string)$arr['btype'] : 'pcr';

		if (!isset($arr['cond_only_tids']) && isset($arr['cond_only_tid']) && $arr['cond_only_tid'] !== '') {
			$arr['cond_only_tids'] = array($arr['cond_only_tid']);
		}
		if (!isset($arr['cond_only_tids']) || !is_array($arr['cond_only_tids'])) {
			$arr['cond_only_tids'] = array();
		} else {
			$arr['cond_only_tids'] = array_values(array_filter(array_map('strval', $arr['cond_only_tids'])));
		}

		$opt_key = $arr['tid'] . '_' . $arr['state'];
		if (!isset($arr['status']) && isset($old_optswkeys[$opt_key]['status'])) {
			$arr['status'] = intval($old_optswkeys[$opt_key]['status']);
		}
		if (!isset($arr['status'])) {
			$arr['status'] = 0;
		}

		$arr['status'] = intval($arr['status']);
		$arr['group'] = isset($groupbytestids[$arr['tid']]) ? $groupbytestids[$arr['tid']] : '';
		$arr['group_code'] = isset($groupcodebytestids[$arr['tid']]) ? $groupcodebytestids[$arr['tid']] : '';
		$arr['iscovid'] = in_array($arr['tid'], $covid_testsids, true) ? 1 : 0;
		$options[$i] = $arr;
	}

	$backupfile = date_i18n('Y-m-dH_i_s') . '_report_to_state.bk';
	$current_user = wp_get_current_user();
	file_put_contents($backup_dir . $backupfile, wp_json_encode(array(
		'meta' => array(
			'created' => date_i18n('Y-m-d H:i:s'),
			'user_id' => get_current_user_id(),
			'user_name' => $current_user && !empty($current_user->display_name) ? $current_user->display_name : 'Unknown',
			'action' => 'restore',
		),
		'options' => $old_opts,
	)));

	update_option('ci_send_hub_tests', $options);
	Tr_Session_Class::add("Restore backup successfully!");

	if (function_exists('tr_client_send_hub')) {
		$url = get_bloginfo('url') . '/wsdl?wsdl';
		$key = '';
		$wsdl = $wpdb->get_row("select * from ci_wsdl_keys where wsdl_desc like '%Hub%'");
		if (!$wsdl) {
			$lab_id = CI_Base_Lab::get_lab_id_by_blog_id();
			$key = wp_generate_password(15);
			$wpdb->insert('ci_wsdl_keys', array(
				'wsdl_key' => $key,
				'wsdl_lab_ID' => $lab_id,
				'wsdl_type' => '',
				'wsdl_desc' => 'Reporting Hub',
			));
		} else {
			$key = $wsdl->wsdl_key;
		}

		include_once(TRCPDD_PATH . 'api/ci_api_loinc.php');
		$loincapi = new CI_Api_LOINC();

		foreach ($options as $tk => $arr) {
			if (!empty($arr['tid'])) {
				$test = $wpdb->get_row("select * from ci_test_panels where te_ID = {$arr['tid']} ");
				if ($test) {
					$arr['test_name'] = $test->te_name;
					$arr['loinc_code'] = $test->te_loinc_code;
					$arr['snomed_code'] = $test->te_snomed_code;

					if (!empty($arr['loinc_code'])) {
						$arr['loinc_short'] = trpdd_get_test_meta($arr['tid'], '_loinc_short', '');
						if (empty($arr['loinc_short'])) {
							$arr['loinc_short'] = $loincapi->getShortName($arr['loinc_code']);
							if (!empty($arr['loinc_short'])) {
								trpdd_update_test_meta($arr['tid'], '_loinc_short', $arr['loinc_short']);
							}
						}

						if (empty($arr['loinc_short'])) {
							Tr_Session_Class::add_error("Unable to validate LOINC code: {$arr['loinc_code']}");
						}
					}
				}
			}

			if (isset($arr['cond_only_tids']) && count($arr['cond_only_tids']) > 0) {
				$arr['cond_only_test_names'] = array();
				$arr['cond_only_loinc_codes'] = array();
				foreach ($arr['cond_only_tids'] as $onltid) {
					$test2 = $wpdb->get_row("select te_name, te_loinc_code from ci_test_panels where te_ID = {$onltid} ");
					if (!$test2) {
						continue;
					}
					$arr['cond_only_test_names'][$onltid] = $test2->te_name;
					$arr['cond_only_loinc_codes'][$onltid] = $test2->te_loinc_code;
				}
			}

			$options[$tk] = $arr;
		}

		$specimens = array();
		$group_specimens = array();
		$specs = $wpdb->get_results("
			select ts.ts_name, ts.ts_code , g.tg_name
			from ci_test_specimens ts
			left JOIN ci_test_groups g ON ts.ts_group_id = g.tg_ID
			where ts.ts_code !=''
		");
		foreach ($specs as $spec) {
			if (!empty($spec->ts_code)) {
				$specimens[$spec->ts_name] = $spec->ts_code;
			}
			if (!empty($spec->tg_name)) {
				$group_specimens[$spec->tg_name] = $spec->ts_name;
			}
		}

		$rs = tr_client_send_hub(array(
			'action' => 'report_tests',
			'tests' => $options,
			'specimens' => $specimens,
			'group_specs' => $group_specimens,
			'url' => $url,
			'key' => $key,
		));
		if (empty($rs) || !isset($rs['status']) || $rs['status'] != 'ok') {
			Tr_Session_Class::add_error("Error when send config to Hub");
		}

		if (isset($_GET['debug'])) {
			var_dump($options);
			var_dump($rs);
		}
	}

	ob_start();
	Tr_Session_Class::show();
	$messages = ob_get_clean();
	wp_send_json(array('success' => true, 'data' => $messages));
	exit;
}

if ($trajax == 'delete_state_lab_backup') {
	global $is_ci_admin;

	if (intval($is_ci_admin) !== 1) {
		wp_send_json_error('Sorry, you are not allowed to delete state lab backups.');
	}

	$backup_file = isset($_POST['backup_file']) ? sanitize_file_name(wp_unslash($_POST['backup_file'])) : '';
	if ($backup_file === '') {
		wp_send_json_error('Backup file is required.');
	}

	$backup_dir = WP_CONTENT_DIR . '/backups/report_to_state/';
	$backup_root = wp_normalize_path(trailingslashit($backup_dir));
	$backup_path = wp_normalize_path($backup_dir . $backup_file);

	if (strpos($backup_path, $backup_root) !== 0 || !file_exists($backup_path)) {
		wp_send_json_error('Backup file not found.');
	}

	if (!@unlink($backup_path)) {
		wp_send_json_error('Delete backup failed.');
	}

	wp_send_json(array('success' => true, 'data' => 'Delete backup successfully!'));
	exit;
}

if ($trajax == 'import_state_lab_csv') {
	global $is_ci_admin;

	if (intval($is_ci_admin) !== 1) {
		wp_send_json_error('Sorry, you are not allowed to import state lab CSV.');
	}

	if (!isset($_FILES['import_file']) || empty($_FILES['import_file']['tmp_name'])) {
		wp_send_json_error('CSV file is required.');
	}

	$file_name = isset($_FILES['import_file']['name']) ? sanitize_file_name($_FILES['import_file']['name']) : '';
	$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
	if ($file_name === '' || !in_array($file_ext, array('csv', 'json', 'bk'), true)) {
		wp_send_json_error('Please upload a valid JSON, BK, or CSV file exported from Report to State.');
	}

	$groupbytestids = array();
	$groupcodebytestids = array();
	$covid_testsids = array();
	$test_ids_by_label = array();
	$state_codes_by_label = array();
	$status_codes_by_label = array(
		'not setup' => 0,
		'pending' => 1,
		'connected' => 2,
		'0' => 0,
		'1' => 1,
		'2' => 2,
	);
	$condition_codes_by_label = array(
		'both' => '0',
		'pos' => '1',
		'neg' => '2',
	);
	$btypes = array();
	$options = array();
	$rows = array();
	$errors = array();
	$row_number = 1;

	foreach (tr_pdd_get_batch_types() as $bt) {
		$btypes[] = esc_sql($bt);
	}

	if (!empty($btypes)) {
		$btypes_str = "'" . implode("','", $btypes) . "'";
		$rsgroups = $wpdb->get_results("
			select tg_ID,
				tg_name,
				tg_cpt_code,
				tg_is_covid,
				tg_type
			from ci_test_groups
			where tg_show_req = 1
				and tg_parent = 0
				and tg_type in ({$btypes_str})
			order by tg_position asc
		", ARRAY_A);

		foreach ($rsgroups as $group) {
			$tests = $wpdb->get_results("
				select te_ID, te_name, te_loinc_code
				from ci_test_panels te
				JOIN ci_test_groups tg ON te.te_group_id = tg.tg_ID
				where tg.tg_ID = {$group['tg_ID']} or tg.tg_parent = {$group['tg_ID']}
			", ARRAY_A);

			foreach ($tests as $te) {
				$test_id = (string)$te['te_ID'];
				$groupbytestids[$test_id] = $group['tg_name'];
				$groupcodebytestids[$test_id] = $group['tg_cpt_code'];
				if ($group['tg_is_covid']) {
					$covid_testsids[] = $test_id;
				}

				$full_label_key = strtolower(trim($te['te_name'] . ' (' . $te['te_loinc_code'] . ')'));
				if (!isset($test_ids_by_label[$full_label_key])) {
					$test_ids_by_label[$full_label_key] = $test_id;
				}

				$name_key = strtolower(trim($te['te_name']));
				if ($name_key !== '' && !isset($test_ids_by_label[$name_key])) {
					$test_ids_by_label[$name_key] = $test_id;
				}
			}
		}
	}

	foreach (tr_get_states() as $state_code => $state_name) {
		$state_code = (string)$state_code;
		$state_codes_by_label[strtolower(trim($state_code))] = $state_code;
		$state_codes_by_label[strtolower(trim($state_code . ' - ' . $state_name))] = $state_code;
	}

	if ($file_ext === 'json' || $file_ext === 'bk') {
		$raw_import = file_get_contents($_FILES['import_file']['tmp_name']);
		$decoded_import = json_decode($raw_import, true);

		if (!is_array($decoded_import)) {
			wp_send_json_error('JSON/BK file is invalid.');
		}

		if (isset($decoded_import['options']) && is_array($decoded_import['options'])) {
			$decoded_import = $decoded_import['options'];
		}

		foreach ($decoded_import as $i => $arr) {
			if (!is_array($arr) || empty($arr['tid']) || empty($arr['state'])) {
				continue;
			}

			if (!isset($arr['cond_only_tids']) && isset($arr['cond_only_tid']) && $arr['cond_only_tid'] !== '') {
				$arr['cond_only_tids'] = array($arr['cond_only_tid']);
			}

			if (!isset($arr['status']) || $arr['status'] === '' || !is_numeric($arr['status'])) {
				$status_label = strtolower(trim((string)(isset($arr['status']) ? $arr['status'] : '')));
				$arr['status'] = isset($status_codes_by_label[$status_label]) ? $status_codes_by_label[$status_label] : 0;
			}

			$rows[$i] = $arr;
		}

		if (empty($rows)) {
			wp_send_json_error('JSON/BK does not contain any valid config rows.');
		}
	} else {
		$handle = fopen($_FILES['import_file']['tmp_name'], 'r');
		if (!$handle) {
			wp_send_json_error('Unable to read uploaded CSV file.');
		}

		$header = fgetcsv($handle);
		if (!$header || !is_array($header)) {
			fclose($handle);
			wp_send_json_error('CSV file is empty.');
		}

		$header = array_map(function ($value) {
			$value = (string)$value;
			$value = str_replace("\xEF\xBB\xBF", '', $value);
			return trim($value, " \t\n\r\0\x0B\"");
		}, $header);
		$header_map = array_flip($header);
		$required_headers = array('Type', 'Test', 'Condition', 'Conditional Tests (CT)', 'CT Condition', 'Send CT', 'State', 'Status');

		foreach ($required_headers as $required_header) {
			if (!isset($header_map[$required_header])) {
				fclose($handle);
				wp_send_json_error('CSV format is invalid. Please use a file exported from Report to State.');
			}
		}

		while (($data = fgetcsv($handle)) !== false) {
			$row_number++;

			if (!is_array($data) || count(array_filter($data, function ($value) {
				return trim((string)$value) !== '';
			})) === 0) {
				continue;
			}

			$type = isset($data[$header_map['Type']]) ? trim((string)$data[$header_map['Type']]) : '';
			$test_label = isset($data[$header_map['Test']]) ? trim((string)$data[$header_map['Test']]) : '';
			$condition_label = isset($data[$header_map['Condition']]) ? trim((string)$data[$header_map['Condition']]) : '';
			$conditional_tests = isset($data[$header_map['Conditional Tests (CT)']]) ? trim((string)$data[$header_map['Conditional Tests (CT)']]) : '';
			$ct_condition_label = isset($data[$header_map['CT Condition']]) ? trim((string)$data[$header_map['CT Condition']]) : '';
			$send_cond_tests = isset($data[$header_map['Send CT']]) ? trim((string)$data[$header_map['Send CT']]) : '';
			$state_label = isset($data[$header_map['State']]) ? trim((string)$data[$header_map['State']]) : '';
			$status_label = isset($data[$header_map['Status']]) ? trim((string)$data[$header_map['Status']]) : '';

			$test_lookup = strtolower($test_label);
			$state_lookup = strtolower($state_label);
			$condition_lookup = strtolower($condition_label);
			$ct_condition_lookup = strtolower($ct_condition_label);
			$status_lookup = strtolower($status_label);

			$tid = isset($test_ids_by_label[$test_lookup]) ? $test_ids_by_label[$test_lookup] : '';
			$state_code = isset($state_codes_by_label[$state_lookup]) ? $state_codes_by_label[$state_lookup] : '';
			$cond_pos_neg = isset($condition_codes_by_label[$condition_lookup]) ? $condition_codes_by_label[$condition_lookup] : '';
			$cond_only_pos_neg = isset($condition_codes_by_label[$ct_condition_lookup]) ? $condition_codes_by_label[$ct_condition_lookup] : '';
			$status_code = isset($status_codes_by_label[$status_lookup]) ? $status_codes_by_label[$status_lookup] : 0;

			if ($tid === '') {
				$errors[] = "Row {$row_number}: Test '{$test_label}' was not found.";
				continue;
			}

			if ($state_code === '' && $state_label !== '') {
				$state_parts = explode(' - ', $state_label);
				$state_guess = strtoupper(trim((string)$state_parts[0]));
				if ($state_guess !== '') {
					$state_code = $state_guess;
				}
			}

			if ($state_code === '') {
				$errors[] = "Row {$row_number}: State '{$state_label}' is invalid.";
				continue;
			}

			if ($cond_pos_neg === '') {
				$errors[] = "Row {$row_number}: Condition '{$condition_label}' is invalid.";
				continue;
			}

			if ($cond_only_pos_neg === '') {
				$errors[] = "Row {$row_number}: CT Condition '{$ct_condition_label}' is invalid.";
				continue;
			}

			$cond_only_tids = array();
			if ($conditional_tests !== '') {
				$conditional_labels = array_filter(array_map('trim', explode(';', $conditional_tests)));
				foreach ($conditional_labels as $conditional_label) {
					$conditional_lookup = strtolower($conditional_label);
					if (!isset($test_ids_by_label[$conditional_lookup])) {
						$errors[] = "Row {$row_number}: Conditional Test '{$conditional_label}' was not found.";
						continue 2;
					}

					$conditional_tid = $test_ids_by_label[$conditional_lookup];
					if ($conditional_tid === $tid) {
						continue;
					}
					$cond_only_tids[] = $conditional_tid;
				}
			}

			$rows['k' . count($rows)] = array(
				'btype' => $type !== '' ? $type : 'pcr',
				'tid' => $tid,
				'cond_pos_neg' => $cond_pos_neg,
				'cond_only_tids' => array_values(array_unique($cond_only_tids)),
				'cond_only_pos_neg' => $cond_only_pos_neg,
				'send_cond_tests' => in_array(strtolower($send_cond_tests), array('yes', '1', 'true'), true) ? 1 : 0,
				'state' => $state_code,
				'status' => $status_code,
			);
		}

		fclose($handle);
	}

	if (!empty($errors)) {
		wp_send_json_error(implode('<br>', array_slice($errors, 0, 10)));
	}

	if (empty($rows)) {
		wp_send_json_error('Import file does not contain any valid rows to import.');
	}

	$old_opts = get_option('ci_send_hub_tests', array());
	$old_optswkeys = array();
	foreach ($old_opts as $old_opt) {
		if (empty($old_opt['tid']) || empty($old_opt['state'])) {
			continue;
		}
		$old_optswkeys[$old_opt['tid'] . '_' . $old_opt['state']] = $old_opt;
	}

	foreach ($rows as $i => $arr) {
		if (!is_array($arr) || empty($arr['tid']) || empty($arr['state'])) {
			continue;
		}

		$arr['tid'] = (string)$arr['tid'];
		$arr['state'] = strtoupper(trim((string)$arr['state']));
		$arr['btype'] = !empty($arr['btype']) ? (string)$arr['btype'] : 'pcr';

		if (!isset($arr['cond_only_tids']) && isset($arr['cond_only_tid']) && $arr['cond_only_tid'] !== '') {
			$arr['cond_only_tids'] = array($arr['cond_only_tid']);
		}
		if (!isset($arr['cond_only_tids']) || !is_array($arr['cond_only_tids'])) {
			$arr['cond_only_tids'] = array();
		} else {
			$arr['cond_only_tids'] = array_values(array_filter(array_map('strval', $arr['cond_only_tids'])));
		}

		$opt_key = $arr['tid'] . '_' . $arr['state'];
		if (!isset($arr['status']) && isset($old_optswkeys[$opt_key]['status'])) {
			$arr['status'] = intval($old_optswkeys[$opt_key]['status']);
		}
		if (!isset($arr['status']) || $arr['status'] === '' || !is_numeric($arr['status'])) {
			$status_label = strtolower(trim((string)(isset($arr['status']) ? $arr['status'] : '')));
			$arr['status'] = isset($status_codes_by_label[$status_label]) ? $status_codes_by_label[$status_label] : 0;
		}
		if (!isset($arr['status'])) {
			$arr['status'] = 0;
		}

		$arr['status'] = intval($arr['status']);
		$arr['group'] = isset($groupbytestids[$arr['tid']]) ? $groupbytestids[$arr['tid']] : '';
		$arr['group_code'] = isset($groupcodebytestids[$arr['tid']]) ? $groupcodebytestids[$arr['tid']] : '';
		$arr['iscovid'] = in_array($arr['tid'], $covid_testsids, true) ? 1 : 0;
		$options[$i] = $arr;
	}

	$backup_dir = WP_CONTENT_DIR . '/backups/report_to_state/';
	if (!file_exists($backup_dir)) {
		wp_mkdir_p($backup_dir);
	}
	$backupfile = date_i18n('Y-m-dH_i_s') . '_report_to_state.bk';
	$current_user = wp_get_current_user();
	file_put_contents($backup_dir . $backupfile, wp_json_encode(array(
		'meta' => array(
			'created' => date_i18n('Y-m-d H:i:s'),
			'user_id' => get_current_user_id(),
			'user_name' => $current_user && !empty($current_user->display_name) ? $current_user->display_name : 'Unknown',
			'action' => 'import',
		),
		'options' => $old_opts,
	)));

	update_option('ci_send_hub_tests', $options);
	Tr_Session_Class::add("Import CSV successfully!");

	if (function_exists('tr_client_send_hub')) {
		$url = get_bloginfo('url') . '/wsdl?wsdl';
		$key = '';
		$wsdl = $wpdb->get_row("select * from ci_wsdl_keys where wsdl_desc like '%Hub%'");
		if (!$wsdl) {
			$lab_id = CI_Base_Lab::get_lab_id_by_blog_id();
			$key = wp_generate_password(15);
			$wpdb->insert('ci_wsdl_keys', array(
				'wsdl_key' => $key,
				'wsdl_lab_ID' => $lab_id,
				'wsdl_type' => '',
				'wsdl_desc' => 'Reporting Hub',
			));
		} else {
			$key = $wsdl->wsdl_key;
		}

		include_once(TRCPDD_PATH . 'api/ci_api_loinc.php');
		$loincapi = new CI_Api_LOINC();

		foreach ($options as $tk => $arr) {
			if (!empty($arr['tid'])) {
				$test = $wpdb->get_row("select * from ci_test_panels where te_ID = {$arr['tid']} ");
				if ($test) {
					$arr['test_name'] = $test->te_name;
					$arr['loinc_code'] = $test->te_loinc_code;
					$arr['snomed_code'] = $test->te_snomed_code;

					if (!empty($arr['loinc_code'])) {
						$arr['loinc_short'] = trpdd_get_test_meta($arr['tid'], '_loinc_short', '');
						if (empty($arr['loinc_short'])) {
							$arr['loinc_short'] = $loincapi->getShortName($arr['loinc_code']);
							if (!empty($arr['loinc_short'])) {
								trpdd_update_test_meta($arr['tid'], '_loinc_short', $arr['loinc_short']);
							}
						}

						if (empty($arr['loinc_short'])) {
							Tr_Session_Class::add_error("Unable to validate LOINC code: {$arr['loinc_code']}");
						}
					}
				}
			}

			if (isset($arr['cond_only_tids']) && count($arr['cond_only_tids']) > 0) {
				$arr['cond_only_test_names'] = array();
				$arr['cond_only_loinc_codes'] = array();
				foreach ($arr['cond_only_tids'] as $onltid) {
					$test2 = $wpdb->get_row("select te_name, te_loinc_code from ci_test_panels where te_ID = {$onltid} ");
					if (!$test2) {
						continue;
					}
					$arr['cond_only_test_names'][$onltid] = $test2->te_name;
					$arr['cond_only_loinc_codes'][$onltid] = $test2->te_loinc_code;
				}
			}

			$options[$tk] = $arr;
		}

		$specimens = array();
		$group_specimens = array();
		$specs = $wpdb->get_results("
			select ts.ts_name, ts.ts_code , g.tg_name
			from ci_test_specimens ts
			left JOIN ci_test_groups g ON ts.ts_group_id = g.tg_ID
			where ts.ts_code !=''
		");
		foreach ($specs as $spec) {
			if (!empty($spec->ts_code)) {
				$specimens[$spec->ts_name] = $spec->ts_code;
			}
			if (!empty($spec->tg_name)) {
				$group_specimens[$spec->tg_name] = $spec->ts_name;
			}
		}

		$rs = tr_client_send_hub(array(
			'action' => 'report_tests',
			'tests' => $options,
			'specimens' => $specimens,
			'group_specs' => $group_specimens,
			'url' => $url,
			'key' => $key,
		));
		if (empty($rs) || !isset($rs['status']) || $rs['status'] != 'ok') {
			Tr_Session_Class::add_error("Error when send config to Hub");
		}

		if (isset($_GET['debug'])) {
			var_dump($options);
			var_dump($rs);
		}
	}

	ob_start();
	Tr_Session_Class::show();
	$messages = ob_get_clean();
	wp_send_json(array('success' => true, 'data' => $messages));
	exit;
}
if (isset($_POST['trajax']) && $_POST['trajax'] === 'save_clinic_support_settings') {
	$post = wp_unslash($_POST);
	$supply_items_raw = json_decode($post['supply_items'] ?? '[]', true);
	$supply_items = [];
	if (is_array($supply_items_raw)) {
		foreach ($supply_items_raw as $item) {
			$name = sanitize_text_field($item['name'] ?? '');
			if ($name === '') {
				continue;
			}
			$supply_items[] = [
				'name'    => $name,
				'max_qty' => max(1, intval($item['max_qty'] ?? 0)),
			];
		}
	}

	$settings = [
		'enabled'  => !empty($post['enabled']) ? 1 : 0,
		'phone'    => sanitize_text_field($post['phone'] ?? ''),
		'email'    => sanitize_email($post['email'] ?? ''),
		'hours'    => sanitize_textarea_field($post['hours'] ?? ''),
		'response' => sanitize_textarea_field($post['response'] ?? ''),
		'include'  => sanitize_textarea_field($post['include'] ?? ''),
		'supplies' => sanitize_textarea_field($post['supplies'] ?? ''),
		'supplies_enabled' => !empty($post['supplies_enabled']) ? 1 : 0,
		'supply_items' => $supply_items,
	];
	update_option('ci_clinic_support', $settings);
	wp_send_json([
		'success' => true,
		'data'    => $settings
	]);
	exit;
}
?>