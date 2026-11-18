<?php
global $current_role;
global $is_ci_admin;
global $wpdb;

$user_role = $profileuser->roles[0];

$user = wp_get_current_user();

if ($user->ID == $profileuser->ID) {
	return;
}


echo '<table class="form-table" id="lab_tbl">';

$labs = $wpdb->get_results("select * from ci_labs where lab_is_ref=0 order by lab_dba_init");
if (in_array($user_role, array('lab', 'lab_tech')) || count($labs) > 1) {
	$lab_ids = get_user_meta($profileuser->ID, '_lab_ids', true);

	if (!is_array($lab_ids)) {
		$lab_ids = !empty($lab_ids) ? explode(',', $lab_ids) : array();
	}


	echo '<tr><th>Lab</th><td>';
	echo '<select name="_lab_ids[]" class="js_select2" multiple>';
	foreach ($labs as $lab) {
		$selected = in_array($lab->lab_ID, $lab_ids) ? 'selected' : '';
		echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba . ' (' . $lab->lab_dba_init . ')</option>';
	}
	echo '</select>';
	echo ' <small> leave empty for all labs</small>';
	echo '<input type="hidden" name="has_lab_ids" value="1">';
	echo '</td></tr>';
}

if (in_array($user_role, array('ref_lab_tech', 'reflab_manager'))) {
	$ref_labs = CI_Base_Lab::get_ref_labs();
	$lab_id = get_user_meta($profileuser->ID, '_ref_lab_id', true);
	echo '<tr><th>Ref Lab</th><td><select name="_ref_lab_id"><option value="">Select</option>';
	foreach ($ref_labs as $lab) {
		$selected = ($lab->lab_ID == $lab_id) ? 'selected' : '';
		echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . ' (' . $lab->lab_dba_init . ')</option>';
	}
	echo '</select></td></tr>';
}

if ($user_role == 'lab_rep' || $user_role == 'lab_rep_w2') {
	$rep_id = get_user_meta($profileuser->ID, '_rep_id', true);
	$reps = $wpdb->get_results("select rep_ID,rep_first,rep_last from ci_lab_reps ");
	echo '<tr><th>Lab Rep</th><td><select name="_rep_id"><option value=""></option>';
	foreach ($reps as $rep) {
		$selected = ($rep->rep_ID == $rep_id) ? 'selected' : '';
		echo '<option value="' . $rep->rep_ID . '" ' . $selected . '>' . $rep->rep_first . ' ' . $rep->rep_last . '</option>';
	}
	if ($rep_id == 0) {
		echo '<option value="new">New</option>';
	}
	echo '</select></td></tr>';
} else if ($user_role == 'doctor') {
	// wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

	$doc_id = get_user_meta($profileuser->ID, '_doc_id', true);
	if ($doc_id == 0) {
		//find doctor
		$doc_id = $wpdb->get_var("select doc_ID from ci_doc where doc_email = '{$profileuser->user_email}' ");
		update_user_meta($profileuser->ID, '_doc_id', $doc_id);
	}

	$clinic_selected = $wpdb->get_col("select cli_ID from ci_clinic_doc where doc_ID = {$doc_id} ");
	$clinics = $wpdb->get_results("select cID,cli_clinic from ci_clinic ");
	echo '<tr><th>Clinic</th><td>
        <input type="hidden" name="has_doctor_cli_IDs" value="1"/>
        <select name="doctor_cli_IDs[]" multiple class="js_select2"><option value=""></option>';
	foreach ($clinics as $cli) {
		$selected = (in_array($cli->cID, $clinic_selected)) ? 'selected' : '';
		echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
	}
	echo '</select></td></tr>';
	$_upload_results = get_user_meta($profileuser->ID, '_upload_results', true);
	echo '<tr><th>Access Upload Results</th><td><input type="hidden" name="_upload_results" value="0"><input type="checkbox" name="_upload_results" value="1" ' . checked('1', $_upload_results, false) . '></td></tr>';
} else if (in_array($user_role, array('office_manager'))) {
	$clinic_id = get_user_meta($profileuser->ID, '_clinic_id', true);

	$cids = $wpdb->get_col("select cli.cID from ci_clinic cli 
	left JOIN ci_clinic_manager clim ON clim.ma_cli_ID = cli.cID 
	where clim.ma_user_ID={$profileuser->ID} or cli.cID = '{$clinic_id}'
	group by cli.cID");


	$clinics = $wpdb->get_results("select cID,cli_clinic from ci_clinic ");
	echo '<tr><th>Clinic</th><td>';
	echo '<input type="hidden" name="has_multi_cids" value="1"/>';
	echo '<select name="_clinic_ids[]" multiple class="js_select2"><option value=""></option>';
	foreach ($clinics as $cli) {
		$selected = in_array($cli->cID, $cids) ? 'selected' : '';
		echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
	}
	echo '</select></td></tr>';
	$_upload_results = get_user_meta($profileuser->ID, '_upload_results', true);
	echo '<tr><th>Access Upload Results</th><td><input type="hidden" name="_upload_results" value="0"><input type="checkbox" name="_upload_results" value="1" ' . checked('1', $_upload_results, false) . '></td></tr>';
} else if (in_array($user_role, array('multi_site_manager', 'clinic'))) {
	$clinic_id = get_user_meta($profileuser->ID, '_clinic_id', true);

	$clinics = $wpdb->get_results("select cID,cli_clinic from ci_clinic ");
	echo '<tr><th>Clinic</th><td>';
	echo '<select name="_clinic_id" ><option value=""></option>';
	foreach ($clinics as $cli) {
		$selected = $cli->cID == $clinic_id ? 'selected' : '';
		echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
	}
	echo '</select></td></tr>';
} else if ($user_role == 'collector') {
	$_collector_id = get_user_meta($profileuser->ID, '_collector_id', true);
	$cols = $wpdb->get_results("select * from `ci_collector`");
	echo '<tr><th>Collector</th><td><select name="_collector_id"><option value=""></option>';
	$_test_results = '0';
	foreach ($cols as $col) {
		$selected = ($col->col_ID == $_collector_id) ? 'selected' : '';
		if ($col->col_ID == $_collector_id) {
			$_test_results = $col->col_test_results;
		}
		echo '<option value="' . $col->col_ID . '" ' . $selected . ' data-tr="' . $col->col_test_results . '">' . $col->col_first . ' ' . $col->col_last . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>Access Test Results</th><td><input type="hidden" name="_test_results" value="0"><input type="checkbox" name="_test_results" value="1" ' . checked('1', $_test_results, false) . '></td></tr>';
	$_upload_results = get_user_meta($profileuser->ID, '_upload_results', true);
	echo '<tr><th>Access Upload Results</th><td><input type="hidden" name="_upload_results" value="0"><input type="checkbox" name="_upload_results" value="1" ' . checked('1', $_upload_results, false) . '></td></tr>';
?>
	<script>
		jQuery(function($) {
			$('[name="_collector_id"]').change(function() {
				var $checked = $('option:selected', $(this)).data('tr');
				if ($checked == '1') {
					$('[name="_test_results"]').prop('checked', true);
				} else {
					$('[name="_test_results"]').removeAttr('checked');
				}

			})
		})
	</script>
<?php

} else if ($user_role == 'lab_director') {
	$_access_all_site = get_user_meta($profileuser->ID, '_access_all_site', true);
	echo '<tr><th>Access All Sites</th><td><input type="hidden" name="_access_all_site" value="0"><input type="checkbox" name="_access_all_site" value="1" ' . checked('1', $_access_all_site, false) . '></td></tr>';
}

if ($is_ci_admin) {
	$hidepatientpdf = get_user_meta($profileuser->ID, '_hide_patientpdf', true);
	echo '<tr><th>Hide Patient in PDF</th><td><input type="hidden" name="_hide_patientpdf" value="0"><input type="checkbox" name="_hide_patientpdf" value="1" ' . checked('1', $hidepatientpdf, false) . '></td></tr>';
}

echo '<tr><th>Access</th><td class="lcheckboxes">';
$batch_types = tr_pdd_get_batch_types();
foreach ($batch_types as $num => $btype) {
	$name = tr_pdd_get_typename($btype);
	$key = '_' . $btype;
	$_tox = get_user_meta($profileuser->ID, $key, true);
	if ($_tox === '') {
		//check in rules
		$capkey = 'form_requisition';
		if ($btype != 'tox') {
			$capkey = 'form_' . $btype;
		}
		if (user_can($profileuser, $capkey)) {
			$_tox = 1;
		}
	}
	echo ' <label><input type="hidden" name="' . $key . '" value="0">
           <input type="checkbox" name="' . $key . '" value="1" ' . checked('1', $_tox, false) . '> ' . $name . '</label> ';
}
if (in_array('blood', $batch_types) && user_can($profileuser, 'ci_phlebotomist')) {
	$_phlebotomist = get_user_meta($profileuser->ID, '_phlebotomist', true);
	if ($_phlebotomist === '') {
		$_phlebotomist = 1;
	}
	echo ' <label><input type="hidden" name="_phlebotomist" value="0">
           <input type="checkbox" name="_phlebotomist" value="1" ' . checked('1', $_phlebotomist, false) . '> Phlebotomy</label> ';
}
do_action('ci_user_access_checkboxes', $profileuser);
echo '</td></tr>';
echo '</table>';
