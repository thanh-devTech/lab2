<?php

function tr_check_and_create_office_manager_user($cli_id, $ma)
{
	global $wpdb;
	$email = trim($ma['ma_email']);

	if (empty($email) || !is_email($email)) {
		return false;
	}
	$user_id = $wpdb->get_var("select ID from {$wpdb->users} where user_email = '{$email}' ");
	if (!$user_id) {
		$user_id = $wpdb->get_var("select ID from {$wpdb->users} where user_login = '{$email}' ");
		if ($user_id > 0) {
			$wpdb->update($wpdb->users, array('user_email' => $email), array('ID' => $user_id));
		}
	}

	if (isset($_GET['debug'])) {
		var_dump($email);
		var_dump($user_id);
	}

	$is_add_new = false;

	if (!$user_id && !empty($email)) {
		$pass = wp_generate_password(12, false);
		$userarr = array(
			'user_login' => $email,
			'user_email' => $email,
			'user_pass' => $pass,
			'first_name' => $ma['ma_manager'],
			'display_name' => $ma['ma_manager'],
			'role' => 'office_manager'
		);

		$user_id = wp_insert_user($userarr);
		if (!is_wp_error($user_id)) {
			//send email
			trpdd_send_mail_create_user($user_id, $pass);
		} else {
			$user_id = 0;
		}
		$is_add_new = true;
	}

	if ($user_id > 0) {
		$wpdb->update('ci_clinic_manager', array('ma_user_ID' => $user_id), array('ma_ID' => $ma['ma_ID']));
		$user = get_user_by('id', $user_id);
		if (isset($_GET['debug'])) {
			var_dump($user->roles);
		}
		if (empty($user->roles)) {
			$user->add_role('office_manager');
		} else if ($user->roles[0] == 'doctor') {
			$user->remove_role('doctor');
			$user->add_role('office_manager');
		}


		$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$cli_id} ", ARRAY_A);

		$types = tr_pdd_get_batch_types();
		foreach ($types as $k => $type) {
			if ($clinic['cli_' . $type]) {
				update_user_meta($user_id, '_' . $type, $clinic['cli_' . $type]);
			}
		}

		if ($is_add_new) {
			do_action('tr_change_role', $user_id, 'office_manager');
		}
	}
	return $user_id;
}

function tr_check_and_create_clinic_user($cli_id, $created = false)
{
	global $wpdb;
	$cli = $wpdb->get_row("select cli.*, lab.lab_dba as lab_name,lab.lab_ID
                        from ci_clinic cli
                        Left JOIn ci_labs lab ON cli.`cli_lab-ID` = lab.lab_ID
                        where cli.cID = {$cli_id}", ARRAY_A);
	$email = $cli['cli_om-email'];

	if ($created) {
		$nl = "<br>";
		if (function_exists('get_blog_option')) {
			$settings = get_blog_option(1, 'ci_data_work_net');
		} else {
			$settings = get_option('ci_pdf_data');
		}
		$lab_id = $cli['lab_ID'];
		$lab = $wpdb->get_row("select * from ci_labs where lab_ID = '{$lab_id}' ", ARRAY_A);

		$site_url = 'http://' . trpdd_get_lab_url($lab['lab_site']) . '/wp-admin';
		$blogname = $lab['lab_dba'];
		if (empty($blogname)) {
			$blogname = get_option('blogname');
		}
		$blogname = wp_specialchars_decode($blogname, ENT_QUOTES);


		if (is_email($lab['lab_email'])) {
			$headers = 'From: ' . $blogname . '<' . $lab['lab_email'] . '>' . "\r\n";
			$headers .= 'Reply-To: ' . $lab['lab_email'] . "\r\n";
		}
		$headers = '';
		$headers .= "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

		//$to = 'Michelle Sadler <MSadler@nshinc.com>';
		//$to = 'trinh<pntrinh87@gmail.com>';
		if (!empty($settings['email_clinic_notify'])) {
			$to = $settings['email_clinic_notify'];
			$subject = 'New Clinic added to Consolidated LIS';
			$keys = array();
			$message = 'Lab: "' . $cli['lab_name'] . '" new clinic added "' . $cli['cli_clinic'] . '"' . $nl;
			foreach ($keys as $k => $text) {
				$message .= $text . ': ' . $cli[$k] . $nl;
			}

			$rs = wp_mail($to, $subject, $message, $headers);
		}
	}
}


function tr_check_and_create_doc_user($doc_id, $cli_ID, $created = false)
{
	global $wpdb, $tr_debug;
	global $last_password;

	$last_password = '';
	$doc 		= $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_id}", ARRAY_A);
	$email 		= $doc['doc_email'];

	$user = !empty($email) ? get_user_by('email', $email) : null;

	$user_id = $wpdb->get_var("select um.user_id from {$wpdb->usermeta} um
    where um.meta_key='_doc_id' and um.meta_value={$doc_id} order by user_id desc");


	if (empty($email)) {
		if ($user_id > 0) {
			$wpdb->delete($wpdb->usermeta, array('meta_key' => '_doc_id', 'meta_value' => $doc_id, 'user_id' => $user_id));
		}
		return false;
	} else if (empty($user)) {
		$pass = wp_generate_password(12, false);
		$last_password = $pass;
		$userarr = array(
			'user_login' => $email,
			'user_email' => $email,
			'user_pass' => $pass,
			'first_name' => $doc['doc_first'],
			'last_name' => $doc['doc_last'],
			'display_name' => $doc['doc_first'] . ' ' .  $doc['doc_last'],
			'role' => 'doctor'
		);

		$user_id = wp_insert_user($userarr);

		if (!is_wp_error($user_id) && $user_id > 0) {
			trpdd_send_mail_create_user($user_id, $pass);
		}
	} else if ($user) {
		$user_id = $user->ID;
	}

	if (!is_wp_error($user_id) && $user_id > 0) {
		update_user_meta($user_id, '_doc_id', $doc['doc_ID']);
		try {
			$user = new WP_User($user_id);

			$user_role = ($user && isset($user->roles[0])) ? $user->roles[0] : '';
			if ($user_role == 'office_manager') {
				$exists = $wpdb->get_var("select count(*) from `ci_clinic_manager` where ma_email = '{$email}' ");
				if (!$exists) {
					$user_role  = '';
				}
			}


			if (empty($user_role)) {
				$user->add_role('doctor');
			}
		} catch (Exception $e) {
		}

		do_action('tr_change_role', $user_id, 'doctor');
	}

	//send email new doctor $doc['doc_npi'] &&
	if ($created) {
		$nl = "\n";
		if (function_exists('get_blog_option')) {
			$settings = get_blog_option(1, 'ci_data_work_net');
		} else {
			$settings = get_option('ci_pdf_data');
		}

		if (!empty($settings['email_doc_notify'])) {
			$to = $settings['email_doc_notify'];
			if ($tr_debug) {
				$to = 'pntrinh29@gmail.com';
			}
			$subject = 'Lab Added a New Provider - Please complete Custom Provider.';
			$keys = array(
				'doc_first' => 'First Name',
				'doc_last' => 'Last Name',
				'doc_email' => 'Email',
				'doc_address' => 'Address',
				'doc_city' => 'City',
				'doc_state' => 'State',
				'doc_zip' => 'Zip',
				'doc_phone' => 'Phone',
				'doc_cell' => 'Cell',
				'doc_specialty' => 'Specialty',
				'doc_npi' => 'NPI'
			);
			$message = '';
			foreach ($keys as $k => $text) {
				if (empty($doc[$k])) continue;
				$message .= $text . ': ' . $doc[$k] . $nl;
			}

			if ($cli_ID > 0) {
				$cli = $wpdb->get_row("select cli.cli_clinic,lab.lab_dba_init as lab from ci_clinic cli
                 JOIN ci_labs lab ON lab.lab_ID = cli.`cli_lab-ID` where cID = {$cli_ID} ");
				$message .= 'Lab: ' . $cli->lab . $nl;
				$message .= 'Clinic: ' . $cli->cli_clinic . $nl;
			}


			$rs = wp_mail($to, $subject, $message);
			if ($tr_debug) {
				var_dump($to);
				var_dump($subject);
				var_dump($message);
				var_dump($rs);
			}
		}
	}
	return $user_id;
}

function trpdd_send_mail_create_user($user_id, $plaintext_pass, $lab_id = 0, $other_info = '')
{
	global $wpdb, $debug_email;

	$user = get_userdata($user_id);


	if (empty($lab_id)) {
		$lab_id = CI_Base_Lab::get_user_lab_id($user_id);
	}

	$lab = $wpdb->get_row("select * from ci_labs where lab_ID = '{$lab_id}' ", ARRAY_A);

	$url = 'https://' . trpdd_get_lab_url($lab['lab_site']);
	if ($url == 'https://') {
		$url = get_bloginfo('home');
	}
	$site_url = $url;
	$blogname = $lab['lab_dba'];
	if (empty($blogname)) {
		$blogname = get_bloginfo('name');
	}
	$blogname = wp_specialchars_decode($blogname, ENT_QUOTES);

	$message  = sprintf(__('You have been added to our site "%s":'), $blogname) . "<br><br>";

	$message  .= sprintf(__('Username: %s'), $user->user_login) . "<br>";
	$message .= sprintf(__('Password: %s'), $plaintext_pass) . "<br>";
	$message .= $other_info . '<br>';
	$message .= $site_url . "<br><br>";
	$message .= $blogname;

	$headers = '';
	if (is_email($lab['lab_email'])) {
		$headers .= 'From: ' . $blogname . '<' . $lab['lab_email'] . '>' . "\r\n";
		$headers .= 'Reply-To: ' . $lab['lab_email'] . "\r\n";
	}

	$headers .= "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
	$to = $user->user_email;
	if (!empty($debug_email) && $debug_email != 2) {
		$to = $debug_email;
	}

	add_action('wp_mail_failed', 'trdebug_wp_mail_failed');
	$rs = wp_mail($to, sprintf(__('[%s] Your username and password'), $blogname), $message, $headers);
	$data = array(
		'plaintext' => $plaintext_pass,
		'lab_id' => $lab_id,
		'time' => time()
	);
	if ($debug_email == 2) {
		$debug_email = $to;
	}
	if ($rs) {
		$data['sent'] = 1;
	} else {
		$data['error'] = 1;
		mail($to, sprintf(__('[%s] Your username and password'), $blogname), $message, $headers);
	}
	update_user_meta($user->ID, '_send_mail_create_user', $data);
	return $rs;
}



function tr_dpp_get_users_in_group($user_id, $not_owners = false)
{
	global $wpdb, $blog_id;
	$user_ids = array();
	if ($not_owners == false) {
		$user_ids[] = $user_id;
	}
	$results = $wpdb->get_results("select * from ci_marketing_groups where gr_site_id = {$blog_id}
    and (gr_admins like '{$user_id},%' or gr_admins like '%,{$user_id}' or gr_admins like '%,{$user_id},%' or gr_admins = '{$user_id}')");

	if (isset($_REQUEST['debug'])) {
		echo 'Groups: ';
		var_dump($results);
	}

	foreach ($results as $row) {
		$admins = explode(',', $row->gr_admins);
		$members = $wpdb->get_col("select user_id from {$wpdb->usermeta} um JOIN ci_lab_reps rep ON um.meta_value = rep.rep_ID and meta_key = '_rep_id'
                                where rep.rep_marketing_group_ID = {$row->gr_ID}");
		if ($not_owners == false && !empty($row->gr_admins)) {
			$user_ids = array_merge($user_ids, $admins);
		}
		if (count($members) > 0) {
			$user_ids = array_merge($user_ids, $members);
		}
	}
	$user_ids = array_unique($user_ids);
	return $user_ids;
}

function tr_dpp_get_marketing_group_leader($user_id)
{
	global $wpdb, $blog_id;

	$group = $wpdb->get_row("select * from ci_marketing_groups where gr_site_id = {$blog_id}
    and (gr_admins like '{$user_id},%' or gr_admins like '%,{$user_id}' or gr_admins like '%,{$user_id},%' or gr_admins = '{$user_id}')");
	return $group;
}

function tr_dpp_get_marketing_group($user_id)
{
	global $wpdb, $blog_id;
	$rep_id = get_user_meta($user_id, '_rep_id', true);
	$group = $wpdb->get_row("select mg.* from ci_marketing_groups mg JOIN ci_lab_reps lr ON lr.rep_marketing_group_ID=mg.gr_ID where lr.rep_ID = {$rep_id}");
	return $group;
}


function tr_pdd_create_user_collector($col, $is_insert)
{
	global $wpdb;


	$email = $col['col_email'];
	$user_id = 0;

	//check email
	if (!empty($email) && is_email($email)) {
		$u = get_user_by('email', $email);
		if ($u) {
			$user_id = $u->ID;
			$fname = get_user_meta($user_id, 'first_name', true);
			$lname = get_user_meta($user_id, 'last_name', true);

			if (empty($fname)) {
				update_user_meta($user_id, 'first_name', $col['col_first']);
				$userarr = array(
					'ID' => $user_id,
					'first_name' => @$col['col_first'],
					'last_name' => @$col['col_last'],
					'display_name' => trim($col['col_first'] . ' ' . @$col['col_last']),
				);
				wp_update_user($userarr);
			}
			if (empty($lname)) {
				update_user_meta($user_id, 'last_name', $col['col_last']);
			}
		} else {
			//need create new
			$pass = wp_generate_password(12, false);
			$userarr = array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass' => $pass,
				'first_name' => $col['col_first'],
				'last_name' => @$col['col_last'],
				'display_name' => $col['col_first'] . ' ' . @$col['col_last'],
				'role' => 'collector'
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
	}

	if (!is_wp_error($user_id) && $user_id > 0) {

		if (tr_get_option('ci_req_settings_blood', 'Phlebotomist')) {
			if (isset($col['col_phlebotomist_code']) && !empty($col['col_phlebotomist_code'])) {
				update_user_meta($user_id, '_phlebotomist', true);
			} else {
				update_user_meta($user_id, '_phlebotomist', false);
			}
		}

		update_user_meta($user_id, '_collector_id', $col['col_ID']);
		update_user_meta($user_id, '_ref_tox', $col['col_ref_tox']);

		$types = tr_pdd_get_batch_types();
		foreach ($types as $k => $type) {
			update_user_meta($user_id, '_' . $type, $col['col_' . $type]);
		}


		do_action('tr_change_role', $user_id, 'collector');
	}
}


function trpdd_log_user($user_id, $action, $data = '', $mid = '', $btype = '', $created = '', $ip = '', $assen = '')
{
	global $wpdb;
	global $log_user_dups, $form_user_log_id;
	global $blog_id;

	if ($user_id == 0) {
		$user_id = get_current_user_id();
	}


	if (!isset($log_user_dups) || !is_array($log_user_dups)) {
		$log_user_dups = array();
	}
	if (isset($log_user_dups[$user_id . $action . $mid])) {
		return true;
	} else {
		$log_user_dups[$user_id . $action . $mid] = true;
	}

	if (empty($ip)) {
		$ip = $_SERVER['REMOTE_ADDR'];
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
	}

	if (is_array($data)) {
		if (isset($data['old'])) {
			$old            = is_array($data['old']) ? $data['old'] : array();
			$changed_arr    = array();
			foreach ($data as $k => $vl) {
				if (!in_array($k, array('old'))) {
					if (isset($old[$k])) {
						$old[$k] = str_replace(array('0000-00-00', '0000-00-00 00:00:00'), '', $old[$k]);

						if ($vl != $old[$k]  && !($old[$k] == '0' && $vl === '')) {
							$changed_arr[$k] = array($old[$k], $vl);
						}
					} else {
						$changed_arr[$k] = $vl;
					}
				}
			}
			if (empty($changed_arr)) {
				return false;
			}
			$data   = $changed_arr;
		}
		$data   = json_encode($data);
	}
	if (empty($created)) {
		$created = date_i18n('Y-m-d H:i:s');
	}
	$insert = array(
		'user_id' => $user_id,
		'lab_id' => $blog_id,
		'user_action' => $action,
		'data' => $data,
		'ip' => $ip,
		'mid' => $mid,
		'btype' => $btype,
		'created' => $created,
		'assen' => $assen
	);
	$wpdb->insert('ci_log_users', $insert);
	$form_user_log_id = $wpdb->insert_id;
	return $form_user_log_id;
}

function trpdd_log_lab_change($action, $lab_id, $new_lab, $old_lab = array(), $assen = '')
{
	if (!is_array($new_lab)) {
		$new_lab = array();
	}
	if (!is_array($old_lab)) {
		$old_lab = array();
	}

	$data = $new_lab;
	$data['old'] = $old_lab;

	if (empty($assen)) {
		if (!empty($new_lab['lab_dba_init'])) {
			$assen = $new_lab['lab_dba_init'];
		} else if (!empty($old_lab['lab_dba_init'])) {
			$assen = $old_lab['lab_dba_init'];
		}
	}

	return trpdd_log_user(get_current_user_id(), $action, $data, $lab_id, 'lab', '', '', $assen);
}


//end file