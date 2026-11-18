<?php

global $wpdb, $current_role;

$user_id 	= get_current_user_id();
$_POST 		= tr_pdd_stripslashes_deep($_POST);

$cli 		= $_POST['cli'];
$docs 		= $_POST['doc'];
$rep	 	= $_POST['rep'];
$lab 		= $_POST['lab'];
$managers 	= isset($_POST['manager']) ? $_POST['manager'] : array();
$site_multi = isset($_POST['ms_manager']) ? $_POST['ms_manager'] : array();

if (isset($cli['cli_other_options'])) {
	$cli['cli_other_options'] = json_encode($cli['cli_other_options']);
}

//save file
if (isset($_FILES['paperwork']) && !empty($_FILES['paperwork']['name'])) {
	$ext = pathinfo($_FILES['paperwork']['name'], PATHINFO_EXTENSION);
	$filename = sanitize_title($cli['cli_clinic']) . '.' . $ext;
	$filepath = WP_CONTENT_DIR . '/paperwork/' . $filename;
	if (!in_array($ext, array('php', 'php5', 'exe', 'sh')) && move_uploaded_file($_FILES['paperwork']['tmp_name'], $filepath)) {
		$cli['paperwork'] = $filename;
	}
}

if ($lab['lab_ID'] == 0) {
	$lab['lab_ID'] = CI_Base_Lab::get_current_lab_id();
}

$cli['cli_clinic'] = trim($cli['cli_clinic']);
if (isset($cli['cli_in-net-list'])) {
	$cli['cli_in-net-list'] = implode('|', (array)$cli['cli_in-net-list']);
}
if (isset($cli['cli_payor-mix'])) {
	$cli['cli_payor-mix'] = implode('|', (array)$cli['cli_payor-mix']);
}

//clinic group
if (!empty($_POST['cli_new_group'])) {
	//create new clinic group
	$wpdb->insert('ci_clinician_groups', array('clig_name' => $_POST['cli_new_group']));
	$cli['cli_group_ID'] = $wpdb->insert_id;
}


//rep
$cli['cli_rep-ID'] = $rep['rep_ID'];
$cli['cli_lab-ID'] = $lab['lab_ID'];
//$cli['cli_lab-ID'] = $wpdb->get_var("select lab_ID from ci_lab_reps where rep_ID = {$rep['rep_ID']}");

if ($current_role == 'distributor') {
	$cli['cli_distributor_ID'] = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");
}


$cli_id = $cli['cID'];
$old_ref_lab_id = 0;
if (intval($cli_id) > 0) {
	$old_ref_lab_id = intval($wpdb->get_var("select cli_ref_lab_ID from ci_clinic where cID = {$cli_id}"));
}
$all_clinic_api_fields = tr_get_all_api_fields('cli_');
if (!empty($all_clinic_api_fields)) {
	$posted_clinic_api_fields = array_intersect(array_keys($cli), $all_clinic_api_fields);
	if (!empty($posted_clinic_api_fields)) {
		tr_ensure_clinic_api_columns($posted_clinic_api_fields);
	}
}

if (intval($cli['cID']) > 0) {
	$rs = $wpdb->update('ci_clinic', $cli, array('cID' => $cli['cID']));

	if ($rs !== false) {
		Tr_Session_Class::add('Update Account Successfully');

		$wpdb->update('ci_wsdl_batch', array('b_status' => 0), array('b_mid' => $cli['cID'], 'btype' => 'cli'));

		$nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
		if ($nextcron == 0 || $nextcron > time() + 360) {
			wp_schedule_single_event(time() + 60, 'ci_cron_sync_wsdl_client_req', array(0));
		}

		if (!empty($_POST['change_old_reqs_ref_lab']) && $old_ref_lab_id !== intval($cli['cli_ref_lab_ID'])) {
			$new_ref_lab_id = intval($cli['cli_ref_lab_ID']);
			$updated_reqs = 0;
			foreach (tr_pdd_get_batch_types() as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($batch_tbl))) != $batch_tbl) {
					continue;
				}
				$updated_reqs += intval($wpdb->update($batch_tbl, array('ref_lab_ID' => $new_ref_lab_id), array('cli_ID' => $cli_id)));
			}
			Tr_Session_Class::add('Updated Ref Lab for ' . $updated_reqs . ' old req(s).');
		}
	} else {
		Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
	}
} else {
	if (empty($cli['cli_sdate'])) {
		$cli['cli_sdate'] = Tr_Base_Class_V6::date('Y-m-d');
	}
	$blood_settings       = get_option('ci_req_settings_blood', array());
	if ($blood_settings['Phlebotomist']) {
		$max_num = $wpdb->get_var("select max(cli_Telcor_ID) from ci_clinic");
		if ($max_num > 0) {
			$cli['cli_Telcor_ID'] = $max_num + 1;
		}
	}

	$rs = $wpdb->insert('ci_clinic', $cli);
	if ($rs !== false) {
		$cli_id = $wpdb->insert_id;
		Tr_Session_Class::add('Inserted Account Successfully');
	} else {
		Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
	}
}

if ($cli_id > 0) {
	tr_check_and_create_clinic_user($cli_id, true);
	//doc
	$wpdb->query("delete from ci_clinic_doc where cli_ID = {$cli_id}");
	$doc_ids = array();
	foreach ($docs as $j => $doc) {
		$doc['cli_ID'] = $cli_id;
		if (empty($doc['doc_first'])) continue;

		$file = array(
			'name' => $_FILES['doc_signature']['name'][$j],
			'tmp_name' => $_FILES['doc_signature']['tmp_name'][$j],
			'type' => $_FILES['doc_signature']['type'][$j],
			'error' => $_FILES['doc_signature']['error'][$j],
		);
		$doc = tr_data_update_doc($doc, $file);

		$doc_ids[] = $doc['doc_ID'];
	}

	if (tr_get_option('ci_pdf_data', 'doc_o_cli')) {

		$removedocids = isset($_POST['remove_doc_ids']) ? $_POST['remove_doc_ids'] : '';
		$removedocids = explode(',', $removedocids);
		foreach ($removedocids as $rdocid) {
			if ($rdocid > 0 && !in_array($rdocid, $doc_ids)) {
				//remove this doctor
				$wpdb->delete('ci_doc', array('doc_ID' => $rdocid));
				$wpdb->delete('ci_clinic_doc', array('doc_ID' => $rdocid));
			}
		}
		//sync doc for all clinic
		update_option('ci_need_sync_doc_clinic', time(), 'no');
		//add cronjob
		$nextcron = wp_next_scheduled('ci_fix_db');
		if ($nextcron == 0 || $nextcron > time() + 300) {
			wp_schedule_single_event(time(), 'ci_fix_db', array('doctor'));
		}
	}
	//col
	$wpdb->delete('ci_clinic_collector', array('cli_ID' => $cli_id));

	foreach ($_REQUEST['cols'] as $col) {
		if (empty($col['col_first']) && empty($col['col_last'])) {
			continue;
		}

		if ($col['col_ID'] == 0) {
			if (!empty($col['col_email'])) {
				$col['col_ID'] = $wpdb->get_var("select col_ID from ci_collector where col_email = '{$col['col_email']}'");
			}
		}

		if ($col['col_ID'] > 0) {
			$wpdb->update('ci_collector', $col, array('col_ID' => $col['col_ID']));
			tr_pdd_create_user_collector($col, false);
		} else {
			$col['lab_ID'] = $cli['cli_lab-ID'];

			$wpdb->insert('ci_collector', $col);

			$col['col_ID'] = $wpdb->insert_id;
			tr_pdd_create_user_collector($col, true);
		}

		$wpdb->insert('ci_clinic_collector', array('col_ID' => $col['col_ID'], 'cli_ID' => $cli_id));
	}

	//manager
	$ma_ids = array(0);

	foreach ($managers as $ma) {
		$ma['ma_cli_ID'] = $cli_id;
		if (empty($ma['ma_manager'])) continue;

		if ($ma['ma_ID'] > 0) {
			$rs = $wpdb->update('ci_clinic_manager', $ma, array('ma_ID' => $ma['ma_ID']));
		} else {
			$rs = $wpdb->insert('ci_clinic_manager', $ma);
			$ma['ma_ID'] = $wpdb->insert_id;
		}

		if ($rs === false) {
			Tr_Session_Class::add_error('Error while update Office Manager: ' . $wpdb->last_error);
		}
		if ($ma['ma_ID'] > 0) {
			$user_id = tr_check_and_create_office_manager_user($cli_id, $ma);
			$ma_ids[] = $ma['ma_ID'];

			if (tr_get_option('ci_pdf_data', 'off_see_all')) {
				update_user_meta($user_id, '_only_one_clinic', $cli['cli_only_one']);
			}
		}
	}
	$ma_ids = implode(',', $ma_ids);
	$wpdb->query("delete from ci_clinic_manager where ma_cli_ID = {$cli_id} and ma_ID not in ({$ma_ids})");

}

//site multi
if (tr_get_option('ci_pdf_data', 'msm_in_setupf')) {
	$updated_users = array();

	foreach ($site_multi as $ma) {
		if (empty($ma['ma_email'])) continue;
		$ma['ma_cli_ID'] = $cli_id;

		$email   = trim($ma['ma_email']);
		$name    = trim($ma['ma_name']);
		$user_id = email_exists($email);

		if (!$user_id) {
			$pass = wp_generate_password(12, false);
			$userarr = array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'first_name'   => $name,
				'display_name' => $name,
				'role'         => 'multi_site_manager',
			);

			$user_id = wp_insert_user($userarr);

			if (is_wp_error($user_id)) {
				$user_id = 0;
			} else {
				update_user_meta($user_id, '_backup_role', '0');
			}
			$updated_users[] = $user_id;
		} else {
			$u = new WP_User($user_id);

			if (!in_array('multi_site_manager', (array) $u->roles)) {
				if (!get_user_meta($user_id, '_backup_role', true)) {
					update_user_meta($user_id, '_backup_role', $u->roles[0]);
				}
			}
		}

		if ($user_id > 0) {
			$user_u = new WP_User($user_id);
			$user_u->set_role('multi_site_manager');
			$updated_users[] = $user_id;

			//update clinic ids
			$ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
			if (!is_array($ms_cli_ids)) {
				$ms_cli_ids = array();
			}
			if (!in_array($cli_id, $ms_cli_ids)) {
				$ms_cli_ids[] = $cli_id;
				update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
			}
		}
	}

	$all_msm_users = get_users(array('role' => 'multi_site_manager'));

	foreach ($all_msm_users as $u) {
		$uid = $u->ID;

		if (!in_array($uid, $updated_users)) {

			//remove clinic ids
			$ms_cli_ids = get_user_meta($uid, '_ms_clinic_ids', true);
			if (is_array($ms_cli_ids) && in_array($cli_id, $ms_cli_ids)) {
				$ms_cli_ids = array_diff($ms_cli_ids, array($cli_id));
				update_user_meta($uid, '_ms_clinic_ids', $ms_cli_ids);
			}
			//restore role if no clinic assigned	
			if (empty($ms_cli_ids)) {
				$user = new WP_User($uid);
				$old_role = get_user_meta($uid, '_backup_role', true);

				$user->remove_role('multi_site_manager');

				if (!empty($old_role) && $old_role !== '0') {
					$user->set_role($old_role);
					delete_user_meta($uid, '_backup_role');
				} elseif ($old_role === '0') {
					$user->set_role('subscriber');
					delete_user_meta($uid, '_backup_role');
				}
			}
		}
	}
}


trpdd_log_form('ci_save_form_new_account_due');
