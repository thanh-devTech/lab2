<?php
global $wpdb;
add_thickbox();

$user_id = get_current_user_id();
$user = wp_get_current_user();

if (isset($_POST['patient_phone'])) {
	$phone = $_POST['patient_phone'];
	$phones = implode("','", tr_patient_get_phone_arr($phone));
	$patients = array();
	if (!empty($phones)) {
		$patients = $wpdb->get_results("select * from ci_patients 
		where pa_phone in ('{$phones}')
		group by pa_first, pa_last, pa_dob");
	}



	if (count($patients) > 1) {
		Tr_Session_Class::add_error('Found that more than one patient is using this phone number!');
		$exist = array();
		foreach ($patients as $i => $patient) {
			$key1 = strtolower($patient->pa_last . '_' . $patient->pa_first . '_' . $patient->pa_dob);
			$key2 = strtolower($patient->pa_first . '_' . $patient->pa_last . '_' . $patient->pa_dob);

			if (isset($exist[$key1]) || isset($exist[$key2])) {
				unset($patients[$i]);
				continue;
			}
			Tr_Session_Class::add_error('Patient Name: ' . $patient->pa_last . ', ' . $patient->pa_first . ', dob: ' . $patient->pa_dob);

			$exist[$key1] = true;
			$exist[$key2] = true;
		}
	}



	if (count($patients) == 1) {
		ob_start();
		Tr_Session_Class::show();
		ob_clean();

		foreach ($patients as $patient) {
			$patient_id = $patients[0]->pa_ID;

			if ($user->roles[0] == 'administrator') {
				update_user_meta($user_id, '_patient_id', $patient_id);
				$wpdb->update($wpdb->users, array('patient_id' => ''), array('ID' => $user_id));
			} else {
				$wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
			}
			$patient_name = $patient->pa_last . ', ' . $patient->pa_first;

			$address = $patient->pa_address . ' ' . $patient->pa_city . ', ' . $patient->pa_state . ' ' . $patient->pa_zip;
			$patient_name .= '=> address: ' . $address;
			Tr_Session_Class::add('Found patient: ' . $patient_name);
			Tr_Session_Class::add('Reloading...');
			echo '<meta http-equiv="refresh" content="1">';
		}
	} else if (count($patients) == 0) {
		Tr_Session_Class::add_error('Not found');
	}
} else if (isset($_POST['patient_code'])) {
	$rs = trpa_set_patient_code($user_id, $_POST['patient_code'], false, false, false);

	if (!$rs) {
		Tr_Session_Class::add_error('Not found');
	} else {
		echo '<meta http-equiv="refresh" content="1">';
	}
} else if (isset($_REQUEST['reset_patient'])) {
	delete_user_meta($user_id, '_patient_code');
	$wpdb->update($wpdb->users, array('patient_id' => ''), array('ID' => $user_id));
} else if (isset($_REQUEST['reset_patient_all'])) {
	$wpdb->query("delete from {$wpdb->usermeta} where meta_key='_patient_id' ");
}

$patient_id = tr_patient_get_id_patient($user_id);


if (empty($patient_id) && $user->roles[0] != 'administrator') {
	$patient_code = get_user_meta(get_current_user_id(), '_patient_code', true);
	$patient_id = '';
	if (!empty($patient_code)) {
		$patient_id = trpa_patient_id_from_code($patient_code, false, false, false);
		if ($patient_id > 0) {
			$wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));

			//wp_redirect($_SERVER['REQUEST_URI']);
			//echo '<meta http-equiv="refresh" content="1">';
		}
	}


	//find email
	$patient_id = $wpdb->get_var("select pa_ID from ci_patients where pa_email='{$user->user_email}'");
	$phone = get_user_meta($user_id, 'phone', true);
	if ($patient_id == 0 && !empty($phone)) {
		//find phone
		//$patient_id = $wpdb->get_var("select pa_ID from ci_patients where pa_phone='{$phone}'");
	}

	if ($patient_id > 0) {
		$wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
		wp_redirect($_SERVER['REQUEST_URI']);
		echo '<meta http-equiv="refresh" content="1">';
	}
}

$patient = $wpdb->get_row("select * from ci_patients where pa_ID = '{$patient_id}' ", ARRAY_A);
if (!$patient) {
	$patient_id = 0;
	if (!empty($patient_code)) {
		Tr_Session_Class::add_error("Please correct your name, dob or patient code!");
	}
} else {
	$update = array();
	if (empty($patient['pa_phone']) || $patient['pa_phone'] == '000-000-0000') {
		$update['pa_phone'] = get_user_meta($user_id, 'phone', true);
		if (empty($update['pa_phone'])) {
			unset($update['pa_phone']);
		}
	}
	if (empty($patient['pa_email']) || $patient['pa_email'] == '0@0') {
		$update['pa_email'] = $user->user_email;
	}

	if (!empty($update)) {
		$wpdb->update('ci_patients', $update, array('pa_ID' => $patient_id));
	}
}
wp_enqueue_style('data-admin', TRCPDD_URL . 'css/admin.min.css', array(), TRCPDD_VS);
?>

<h2></h2>

<?php Tr_Session_Class::show() ?>

<div class="frow" style="width:400px;max-width: 100%;margin:20px auto">
	<div style="border:2px dashed #ddd;padding:20px;text-align: center">
		<form method="post">
			<p>You must enter Your Code: </p>
			<div class="frow">
				<label>Patient Code Or Accession Number <a class="itip thickbox" href="#TB_inline?width=600&height=200&inlineId=patientcodeinfo"></a></label>
				<br>
				<input type="text" name="patient_code" value="" style="width:200px" required />

				<input type="submit" class="button-primary" value="Update" />
			</div>
		</form>
	</div>

	<?php
	if ($user->roles[0] == 'administrator') {
	?>
		<br><br>
		<div style="border:2px dashed #ddd;padding:20px;text-align: center">
			<form method="post">
				<h2>For Administrator</h2>
				<div class="frow">
					<label>Patient Phone number :</label>
					<br>
					<input type="text" name="patient_phone" value="<?php echo $_REQUEST['patient_phone'] ?>" style="width:200px" required />

					<input type="submit" class="button-primary" value="Update" />
				</div>
			</form>
		</div>
	<?php
	}
	?>


	<div id="patientcodeinfo" style="display: none;">
		<p>Please ask your doctor for the Patient Code from one of your test results demographic block.</p>
	</div>
</div>