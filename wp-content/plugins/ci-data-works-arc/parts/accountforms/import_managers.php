<?php
global $wpdb;

$conditions = trpdd_get_batch_conditions(0);

$clinics = $wpdb->get_results("select * from ci_clinic 
            where {$conditions['where_clinic']}
            order by cli_clinic asc");



$clinics_arr = array();
$clinics_ids = array();
foreach ($clinics as $r) {
	$clinics_arr[strtolower($r->cli_clinic)] = $r->cID;
	$clinics_arr[$r->cID] = $r->cID;
	$clinics_ids[$r->cID] = $r->cID;
}

if (!empty($_FILES['file']['name'])) {
	//put file to log
	$logdir = WP_CONTENT_DIR . '/log/import_manager/';
	if (!file_exists($logdir)) {
		wp_mkdir_p($logdir);
	}
	$logfile = $logdir . 'import_manager_' . date('Ymd_His') . '.csv';
	move_uploaded_file($_FILES['file']['tmp_name'], $logfile);
	$data = trpdd_get_data($_FILES['file']['tmp_name'], '', array(), true);

	$count = 0;
	$cli_id = isset($_REQUEST['cID']) ? $_REQUEST['cID'] : 0;

	foreach ($data['data'] as $row) {
		$name 		= trpdd_get_string_exist($row, array('name', 'full name'));
		$email 		= trpdd_get_string_exist($row, array('email', 'email address', 'email/username'));
		$clinic 	= trpdd_get_string_exist($row, array('clinic', 'office', 'clinic name'));
		$clinic 	= trim($clinic);
		$email 		= str_replace(' ', '', $email);

		if (empty($email)) {
			Tr_Session_Class::add_error("Empty Email for {$name}");
			continue;
		}

		$cids = array();
		if ($cli_id === 'all' || (empty($clinic) && $cli_id == 0)) {
			$cids = $clinics_ids;
		} else if ($cli_id > 0) {
			$cids[] = $cli_id;
		} else if (!empty($clinic) && isset($clinics_arr[strtolower($clinic)])) {
			$cids[] = $clinics_arr[strtolower($clinic)];
		}

		$rso1 = 0;

		foreach ($cids as $cid) {
			$ma = array(
				'ma_email' => $email,
				'ma_cli_ID' => $cid,
				'ma_manager' => $name,
			);

			$ma['ma_ID'] = $wpdb->get_var("select ma_ID from ci_clinic_manager where ma_email = '{$ma['ma_email']}' and ma_cli_ID = {$cid}");
			if ($ma['ma_ID'] == 0) {
				$rs = $wpdb->insert('ci_clinic_manager', $ma);
				$ma['ma_ID'] = $wpdb->insert_id;
			}

			$user_id = tr_check_and_create_office_manager_user($cid, $ma);
			if ($user_id > 0) {
				$rso1 = 1;
			}
		}


		if ($rso1 > 0) {
			$count++;
		}
	}

	Tr_Session_Class::add('Imported : ' . $count);
}

if (isset($_GET['import_all_from_users'])) {
	$users = $wpdb->get_results("select * from {$wpdb->users} u
	JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = '{$wpdb->prefix}capabilities' AND m.meta_value LIKE '%office_manager%'
			where u.user_email NOT IN (select ma_email from ci_clinic_manager ) ");

	foreach ($users as $u) {
		$name = $u->display_name;
		$email = $u->user_email;
		foreach ($clinics_ids as $cid) {
			$ma = array(
				'ma_email' => $email,
				'ma_manager' => $name,
				'ma_cli_ID' => $cid,
				'ma_user_ID' => $u->ID,
			);
			$ma['ma_ID'] = $wpdb->get_var("select ma_ID from ci_clinic_manager where ma_email = '{$ma['ma_email']}' and ma_cli_ID = {$cid}");
			if ($ma['ma_ID'] == 0) {
				$rs = $wpdb->insert('ci_clinic_manager', $ma);
				$ma['ma_ID'] = $wpdb->insert_id;
			}
		}
	}
}


?>

<div class="wrap center">
	<h2>Import Office Managers</h2>
	<?php Tr_Session_Class::show(); ?>
	<div style="width:800px;margin:30px auto">
		<form method="post" enctype="multipart/form-data">

			<div class="frow">
				<label>Clinic</label>
				<select name="cID" class="js_select2" style="width:400px">
					<option value="">By File</option>
					<option value="all">All Clinics</option>
					<?php
					foreach ($clinics as $clinic) {
						echo '<option value="' . $clinic->cID . '">' . $clinic->cli_clinic . '</option>';
					}
					?>
				</select>
			</div>
			<div class="frow">
				<label>Csv file:</label>
				<input type="file" name="file" accept="text/csv" required="" />
			</div>

			<div class="frow">
				<input type="submit" class="button" />
			</div>

		</form>
		<br>
		<a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'demo_manager_csv', 'bytab' => 1)) ?>">Download Template Csv</a>
	</div>
</div>