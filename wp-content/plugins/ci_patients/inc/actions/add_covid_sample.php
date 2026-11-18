<?php
global $wpdb, $logs, $tr_sms_gateway, $current_lab_id;
global $add_pre_wp_mail;

$logs = array();

$has_batch = get_post_meta($order_id, '_accessions', true);
if (!isset($covid_testing) || empty($covid_testing)) {
	$covid_testing = get_post_meta($order_id, '_restration_covid_testing', true);
}

if ($order_id == 0 || !empty($has_batch) || !$covid_testing) {
	$return = 1;
	return 1;
}

$custom_form = isset($covid_testing['custom_form']) && !empty($covid_testing['custom_form']) ? $covid_testing['custom_form'] : '';
$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form']) ? $_REQUEST['custom_form'] : $custom_form;

if ((isset($_REQUEST['is_concierge']) && $_REQUEST['is_concierge'])
	|| (isset($covid_testing['is_concierge']) && $covid_testing['is_concierge'])
) {
	$custom_form = 'concierge';
}

if ((isset($_REQUEST['is_meijer']) && $_REQUEST['is_meijer'])
	|| (isset($covid_testing['is_meijer']) && $covid_testing['is_meijer'])
) {
	$custom_form = 'meijer';
}

if ((isset($_REQUEST['is_canada']) && $_REQUEST['is_canada'])
	|| (isset($covid_testing['is_canada']) && $covid_testing['is_canada'])
) {
	$custom_form = 'canada';
}


$form_key       = 'trpatient_product_settings';
$scheduling_tbl = 'ci_scheduling';

if (!empty($custom_form)) {
	$form_key = 'trpatient_' . $custom_form . '_settings';
	$scheduling_tbl = 'ci_scheduling_' . $custom_form;
	if ($custom_form == 'meijer') {
		$scheduling_tbl = '';
	}
}

$patient_portal_type = 'main';
if (!empty($custom_form)) {
	$patient_portal_type = $custom_form;
	$patient_portal_type = str_replace(array('cform', '_', ' '), '', $patient_portal_type);
	$patient_portal_type = substr($patient_portal_type, 0, 5);
}

$form_options   = get_option($form_key, array());
$pa_options     = get_option('trpatient_settings', array());

if (isset($pa_options['email_by_cron']) && $pa_options['email_by_cron'] && !$add_pre_wp_mail) {
	add_filter('pre_wp_mail', 'trpatient_pre_wp_mail_cron', 99, 2);
	$add_pre_wp_mail = true;
}

$web_url            = get_bloginfo('url');
$second_logo_html   = '';
$lab_name           = '';
$lab_short          = '';
$lab_id             = $current_lab_id;

if ($current_lab_id > 0) {
	$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$current_lab_id}");
	$lab_id = $lab->lab_ID;
	$lab_name = $lab->lab_name;
	$lab_short = $lab->lab_dba_init;
} else {
	$lab = $wpdb->get_row("select * from ci_labs order by lab_ID asc limit 1");
	$lab_id = $lab->lab_ID;
	$lab_name = $lab->lab_name;
	$lab_short = $lab->lab_dba_init;
}


$phone_num          = '';
$location_name      = '';
$location_address   = '';
$location_description = '';
$accessions         = array();

$sms_notify = $pa_options['sms_new_order_notify'];
if (!$sms_notify) {
	$sms_notify = $form_options['sms_notify'];
}



$main_user_id       = get_current_user_id();
$main_patient_id    = 0;

if ($main_user_id > 0) {
	$main_patient_id = tr_patient_get_id_patient($main_user_id);
}

$pa         = $covid_testing['pa'];
$pa_muti    = is_array($covid_testing['pa_muti']) ? $covid_testing['pa_muti'] : array();
$bat        = $covid_testing['bat'];
$batch_meta = $covid_testing['batch_meta'];
$ins1       = $covid_testing['ins1'];
$ins1_muti  = $covid_testing['ins1_muti'];
$tests      = $covid_testing['tests'];
$no_insurance = $covid_testing['no_insurance'];
$insu_photos = $covid_testing['insu_photos'];
$location   = $covid_testing['location'];
$signature  = $covid_testing['signature'];

$has_insurance = $ins1 && !empty($ins1['ins_company']) && !empty($ins1['ins_member_id']);

$default_type_of_specimen = 'Nasopharyngeal Swab';
if (isset($form_options['type_of_specimen']) && !empty($form_options['type_of_specimen'])) {
	$default_type_of_specimen = $form_options['type_of_specimen'];
}

if (!empty($covid_testing['specimen'])) {
	$default_type_of_specimen = $covid_testing['specimen'];
}

$dagnostic_codes_post = '';
if (isset($bat['dagnostic_codes']) && is_array($bat['dagnostic_codes'])) {
	$dagnostic_codes_post = implode(',', $bat['dagnostic_codes']);
}

if (empty($pa) || empty($tests)) {
	return;
}
$pa['pa_phone'] = str_replace(array('-', '.', '(', ')', ' '), '', $pa['pa_phone']);
$pa['pa_phone'] = trim($pa['pa_phone']);

if (strlen($pa['pa_phone']) == 11 && substr($pa['pa_phone'], 0, 1) === '1') {
	$pa['pa_phone'] = substr($pa['pa_phone'], 1);
} else if (strlen($pa['pa_phone']) == 12 && substr($pa['pa_phone'], 0, 2) === '+1') {
	$pa['pa_phone'] = substr($pa['pa_phone'], 2);
}

$pa['pa_last'] = ucfirst(trim($pa['pa_last']));
$pa['pa_first'] = ucfirst(trim($pa['pa_first']));

$email = $pa['pa_email'];
$phone = $pa['pa_phone'];

$user_id            = get_current_user_id();
$is_patient_role    = true;

//check email
try {
	if (is_email($email)) {
		if (email_exists($email)) {
			$user = get_user_by('email', $email);
			if ($user) {
				$user_phone = get_user_meta($user->ID, 'phone', true);
				if ($user_phone == $phone || $user_phone == '+1' . $phone) {
					$user_id = $user->ID;
				} else {
					$user_id = 0;
					wp_logout();
				}
			}
		}
	}

	if ($user_id == 0) {
		//get user_id by phone
		$user_id = $wpdb->get_var("select user_id from {$wpdb->usermeta} where
												meta_key = 'phone'
												and meta_value in ('{$phone}','+1{$phone}') ");
	}

	if ($user_id == 0) {
		//create user
		$pass = substr(md5(time()), 0, 8);
		$user_login = $email;
		if (email_exists($email) || !is_email($email)) {
			$email = $phone . '@';
			if (email_exists($email)) {
				$email = time() . $phone . '@';
			}
			$user_login = $phone;
		}

		if (username_exists($user_login)) {
			$user_login .= '1';
		}

		$userdata = array(
			'user_login' => $user_login,
			'user_pass' => $pass,
			'user_email' => $email,
			'role' => 'patient',
		);
		$user_id = wp_insert_user($userdata);
		if ($user_id > 0) {
			update_user_meta($user_id, 'phone', $pa['pa_phone']);
			update_user_meta($user_id, 'first_name', $pa['pa_first']);
			update_user_meta($user_id, 'last_name', $pa['pa_last']);
			update_user_meta($user_id, 'birthday', $pa['pa_dob']);
			update_user_meta($user_id, 'address', $pa['pa_address']);
			update_user_meta($user_id, 'city', $pa['pa_city']);
			update_user_meta($user_id, 'state', $pa['pa_state']);
			update_user_meta($user_id, 'zip', $pa['pa_zip']);

			if (!is_user_logged_in()) {
				wp_authenticate($user_login, $pass);
			}
		}
	}
} catch (Exception $e) {
}

if ($user_id > 0) {
	$current_user = get_user_by('id', $user_id);
	if (is_object($current_user) && isset($current_user->roles)) {
		$is_patient_role = $current_user->roles[0] == 'patient';
		$main_role = get_user_meta($user_id, 'wp_capabilities', true);
		if (is_array($main_role) && !empty($main_role[0])) {
			$is_patient_role = false;
		}
	}
}


$all_insurances = array($ins1);
$all_patients  = array($pa);
foreach ($pa_muti as $k => $pm) {
	$all_patients[] = $pm;
	$all_insurances[] = $ins1_muti[$k];
}

foreach ($all_patients as $i => $patientdata) {

	$patientdata['pa_phone'] = str_replace(array('-', '.', '(', ')', ' '), '', $patientdata['pa_phone']);
	$patientdata['pa_phone'] = trim($patientdata['pa_phone']);
	if (strlen($patientdata['pa_phone']) == 11 && substr($patientdata['pa_phone'], 0, 1) === '1') {
		$patientdata['pa_phone'] = substr($patientdata['pa_phone'], 1);
	} else if (strlen($patientdata['pa_phone']) == 12 && substr($patientdata['pa_phone'], 0, 2) === '+1') {
		$patientdata['pa_phone'] = substr($patientdata['pa_phone'], 2);
	}

	$patientdata['pa_ID'] = 0;
	if ($user_id > 0 && $i == 0) {
		$main_patient_id = tr_patient_get_id_patient($user_id);
		$patientdata['pa_ID'] = intval($main_patient_id);
	}

	try {
		//check names
		if ($patientdata['pa_ID'] > 0) {
			$patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patientdata['pa_ID']} ");

			//if patient different names then create new
			if (! $patient) {
				$patientdata['pa_ID'] = 0;
			} else if (
				strtolower($patient->pa_first) != strtolower($patientdata['pa_first'])
				|| strtolower($patient->pa_last) != strtolower($patientdata['pa_last'])
				||  $patient->pa_dob  !=  $patientdata['pa_dob']
			) {
				$patientdata['pa_ID'] = 0;
			}
		}

		if ($patientdata['pa_ID'] == 0 && ! empty($patientdata['pa_email'])) {
			//find $patientdata['pa_ID'] by email
			$sql_pa1 = $wpdb->prepare(
				"select pa_ID from ci_patients 
						where pa_first = %s 
						and pa_last = %s
						 and pa_dob= %s
						 and pa_email = %s
						 ",
				$patientdata['pa_first'],
				$patientdata['pa_last'],
				$patientdata['pa_dob'],
				$patientdata['pa_email']
			);
			$patientdata['pa_ID'] = $wpdb->get_var($sql_pa1);

			if ($user_id > 0 && $patientdata['pa_ID'] > 0 && $main_patient_id == 0 && $i == 0) {
				$old_pid = $wpdb->get_var("select patient_id from {$wpdb->users} where ID = {$user_id}");
				if ($old_pid == 0 && $is_patient_role) {
					$wpdb->update($wpdb->users, array('patient_id' => $patientdata['pa_ID']), array('ID' => $user_id));
				}
			}
		}

		if ($patientdata['pa_ID'] == 0 && ! empty($patientdata['pa_phone'])) {
			//find $patientdata['pa_ID'] by phone
			$phones = tr_patient_get_phone_arr($patientdata['pa_phone']);
			if (!empty($phones)) {
				$phones = implode("','", $phones);
				$sql_pa2 = $wpdb->prepare(
					"select pa_ID from ci_patients 
							where pa_first = %s 
							and pa_last = %s
							 and pa_dob= %s
							 and pa_phone in ('{$phones}')
							 ",
					$patientdata['pa_first'],
					$patientdata['pa_last'],
					$patientdata['pa_dob']

				);
				$patientdata['pa_ID'] = $wpdb->get_var($sql_pa2);
			}
		}
	} catch (Exception $e) {
	}

	$patientdata['pa_ID'] = intval($patientdata['pa_ID']);
	if ($patientdata['pa_ID'] > 0) {
		$logs[] = 'has patient # ' . $patientdata['pa_ID'];

		$rs = $wpdb->update('ci_patients', $patientdata, array('pa_ID' => $patientdata['pa_ID']));
		if ($rs === false) {
			$logs[] = 'update patient fail' . $wpdb->last_error;
		}
	} else {
		$patientdata['pa_created'] = date_i18n('Y-m-d H:i:s');
		unset($patientdata['pa_ID']);

		$rs = $wpdb->insert('ci_patients', $patientdata);
		if ($rs === false) {
			$logs[] = 'insert patient fail' . $wpdb->last_error;
		}
		$patientdata['pa_ID'] = $wpdb->insert_id;

		$pa_code = trpdd_patient_encode($patientdata['pa_ID']);

		$rs = $wpdb->update('ci_patients', array('pa_code' => $pa_code), array('pa_ID' => $patientdata['pa_ID']));
		if ($rs === false) {
			$logs[] = 'update patient code fail' . $wpdb->last_error;
		}
		if ($i == 0 && ($main_patient_id == 0 || $main_user_id != $user_id)) {
			$old_pid = $wpdb->get_var("select patient_id from {$wpdb->users} where ID = {$user_id}");
			if ($old_pid == 0 && $is_patient_role) {
				$wpdb->update($wpdb->users, array('patient_id' => $patientdata['pa_ID']), array('ID' => $user_id));
			}
			$main_patient_id = $patientdata['pa_ID'];
		}

		//add kids
		if (($main_user_id > 0 && $main_patient_id > 0 && $main_patient_id != $patientdata['pa_ID']) || $i > 0) {
			$u_main_id = $main_user_id > 0 ? $main_user_id : $user_id;
			$kids = get_user_meta($u_main_id, '_patient_kids', true);
			if (!is_array($kids)) {
				$kids = array();
			}
			if ($is_patient_role && !isset($kids[$patientdata['pa_ID']])) {
				$kids[$patientdata['pa_ID']] = array(
					'first_name' => $patientdata['pa_first'],
					'last_name' => $patientdata['pa_last'],
					'birthday' => $patientdata['pa_dob']
				);
				update_user_meta($u_main_id, '_patient_kids', $kids);
			}
		}
	}

	$patientdata['patient_name'] = $patientdata['pa_last'] . ', ' . $patientdata['pa_first'];
	$all_patients[$i] = $patientdata;

	$inst = $all_insurances[$i];
	if ($inst) {
		foreach ($inst as $k => $vl) {
			if (is_null($vl) || empty($vl)) {
				unset($inst[$k]);
			}
		}
		$inst['pa_ID'] = $patientdata['pa_ID'];
		$inst['ins_created'] = date_i18n('Y-m-d H:i:s');
		$rs = $wpdb->insert('ci_insurances', $inst);
		$inst['ins_ID'] = $wpdb->insert_id;
		if ($rs === false) {
			$logs[] = 'Update insurance fail: ' . $wpdb->last_error;
		}
	} else {
		$inst = array();
		$inst['ins_company'] = 'No Insurance - Cash';
		$inst['ins_first'] = $patientdata['pa_first'];
		$inst['ins_last'] = $patientdata['pa_last'];

		$inst['pa_ID'] = $patientdata['pa_ID'];
		$inst['ins_created'] = date_i18n('Y-m-d H:i:s');
		$rs = $wpdb->insert('ci_insurances', $inst);
		$inst['ins_ID'] = $wpdb->insert_id;
	}

	if ($no_insurance || empty($inst['ins_company'])) {
		$inst['ins_company'] = 'No Insurance - Cash';
	}
	$all_insurances[$i] = $inst;
}

$patient_dob = $pa['pa_dob'];
$patient_name = $pa['pa_last'] . ', ' . $pa['pa_first'];
$phone_num = str_replace(array('-', '(', ')', ' ', '.'), '', $pa['pa_phone']);

$doc = date_i18n('Y-m-d H:i:s');

if (isset($bat['doc']) && $bat['doc'] === 'today') {
	$bat['doc'] = date_i18n('Y-m-d 00:00:00');
}
if (!empty($bat) && !empty($bat['doc'])) {
	$bat['doc'] = strip_tags($bat['doc']);
	$bat['doc'] = str_replace(array('. ', '.'), '', $bat['doc']);
	$doc = date('Y-m-d H:i:s', strtotime($bat['doc']));
}

$adoc     = isset($bat['adoc']) && is_array($bat['adoc']) ? $bat['adoc'] : array();
$adockeys = isset($bat['adockeys']) && is_array($bat['adockeys']) ? $bat['adockeys'] : array();
$adding_doc = array();
foreach ($adoc as $dk => $dvl) {
	if (empty($dvl)) {
		continue;
	}
	$dvl = strip_tags($dvl);
	$dvl = str_replace(array('. ', '.'), '', $dvl);

	$arrks = explode('*', $adockeys[$dk]);
	//$dvl = date('Y-m-d H:i:s', strtotime($dvl));
	foreach ($arrks as $tk) {
		$adding_doc[$tk] = $dvl;
	}
	if (!isset($bat['doc'])) {
		$bat['doc'] = $dvl;
		$doc = date('Y-m-d H:i:s', strtotime($bat['doc']));
	}
}
unset($bat['adoc']);
unset($bat['adockeys']);



if (!empty($location)) {
	$get_cli_ID = $get_doc_ID = 0;
	foreach ($form_options['locations'] as $arr) {
		if ($arr['key'] == $location) {
			$location_name = $arr['name'];
			$location_address = $arr['address'];
			$location_description = $arr['desc'];
			if (!empty($arr['cli_ID'])) {
				$form_options['cli_ID'] = $arr['cli_ID'];
			}
			if (!empty($arr['doc_ID'])) {
				$form_options['doc_ID'] = $arr['doc_ID'];
			}
		}

		if (!empty($covid_testing['location_url']) && strtolower($covid_testing['location_url']) == strtolower($arr['url'])) {
			$get_cli_ID = $arr['cli_ID'];
			$get_doc_ID = $arr['doc_ID'];
		}
	}

	if ($get_cli_ID > 0) {
		$form_options['cli_ID'] = $get_cli_ID;
	}
	if ($get_doc_ID > 0) {
		$form_options['doc_ID'] = $get_doc_ID;
	}
}
$doc_ID = $cli_ID = 0;
$doctor_name = $clinic_name = '';
if (isset($covid_testing['cli_ID']) && $covid_testing['cli_ID'] > 0) {
	$form_options['cli_ID'] = $covid_testing['cli_ID'];
}
if (isset($covid_testing['doc_ID']) && $covid_testing['doc_ID'] > 0) {
	$form_options['doc_ID'] = $covid_testing['doc_ID'];
}
if (isset($form_options['doc_ID']) && $form_options['doc_ID'] > 0) {
	$doc_ID = $form_options['doc_ID'];
	$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$doc_ID}");
	$doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
}

if (isset($form_options['cli_ID']) && $form_options['cli_ID'] > 0) {
	$cli_ID = $form_options['cli_ID'];
	$clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = {$cli_ID} ");
}




$order = new WC_Order($order_id);
$is_paid = $order->is_paid();
$pm_method = $order->get_payment_method('value');
$total_price = $order->get_total();


$product_item_ids = array();
$product_names = array();
$product_prices = array();

foreach ($order->get_items() as $itemid => $item) {
	$pid = $item['product_id'];
	$product_item_ids[$pid] = $itemid;
	$product_names[$pid] = $item['name'];
	$product_prices[$pid] = $item['total'];
}
$batch_created_types = array();

//check commbo
$combo_adding_tests = array();
foreach ($tests as $ik => $key) {
	list($btype, $product_id, $pay_type, $adtype) = explode('|', $key);
	if ($btype == 'combo') {
		unset($tests[$ik]);
		//get all products in combo
		$rowopt             = array();
		$productsarr        = $form_options['products'];
		if ($pay_type == 'ins') {
			$productsarr        = $form_options['products_insurance'];
		} else if ($pay_type == 'noins') {
			$productsarr        = $form_options['products_noinsurance'];
		}
		if (is_array($productsarr) && count($productsarr) > 0) {

			$combotypes = array();
			foreach ($productsarr as $parr) {
				if ($parr['ID'] != $product_id) {
					list($basebt, $addingtestbt) = explode('-', $parr['type']);
					if (isset($combo_adding_tests[$basebt])) {
						$combo_adding_tests[$basebt] = array();
					}
					if (in_array($basebt, $combotypes)) {

						$combo_adding_tests[$basebt][] = $addingtestbt;
						continue;
					}
					$combotypes[] = $basebt;
					$tests[] = $parr['type'] . '|' . $product_id . '|' . $pay_type . '|' . $adtype;
				}
			}
		}
	}
}

foreach ($tests as $key) {
	list($btype, $product_id, $pay_type, $adtype) = explode('|', $key);
	if ($btype == 'pcr_antigen') continue;

	$order_item_id      = $product_item_ids[$product_id];
	$prdname            = $product_names[$product_id];
	$product_red_icon   = get_post_meta($product_id, '_red_paid', true);
	$paid_num           = 0;
	$selected_labid     = $lab_id;
	$selected_labshort  = $lab_short;

	$selected_cli_ID    = $cli_ID;
	$selected_cli_name  = $clinic_name;

	$selected_doc_ID    = $doc_ID;
	$selected_doc_name  = $doctor_name;

	$type_of_specimen = $default_type_of_specimen;

	$rowopt             = array();
	$productsarr        = $form_options['products'];
	if ($pay_type == 'ins') {
		$productsarr        = $form_options['products_insurance'];
	} else if ($pay_type == 'noins') {
		$productsarr        = $form_options['products_noinsurance'];
	}

	if (is_array($productsarr) && count($productsarr) > 0) {
		foreach ($productsarr as $parr) {
			if ($parr['ID'] == $product_id) {
				$can_use_slab = true;
				if ($parr['onlydiscountlab'] && $adtype != 'discount') {
					$can_use_slab = false;
				}

				if ($can_use_slab) {
					if ($parr['labid'] > 0) {
						$selected_labid    = $parr['labid'];
						$labslectedobj     = $wpdb->get_row("select * from ci_labs where lab_ID = {$selected_labid}");
						$selected_labshort = $labslectedobj->lab_dba_init;
						if (count($tests) == 1) {
							$lab_name = $labslectedobj->lab_name;
						}
						if ($selected_labid != $lab_id && !empty($labslectedobj->lab_logo)) {
							$second_logo_html = '<div><img src="' . $web_url . '/wp-content/uploads/logos/' . $labslectedobj->lab_logo . '"/></div>';
						}
					}
					if ($parr['cli_ID'] > 0) {
						$selected_cli_ID = $parr['cli_ID'];
						if ($selected_cli_ID != $cli_ID) {
							$selected_cli_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = {$selected_cli_ID} ");
						}
					}
					if ($parr['doc_ID'] > 0) {
						$selected_doc_ID = $parr['doc_ID'];
						if ($selected_doc_ID != $doc_ID) {
							$doctor            = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$selected_doc_ID}");
							$selected_doc_name = $doctor->doc_last . ', ' . $doctor->doc_first;
						}
					}
				}
				if (strlen($parr['type_specimen']) > 5) {
					$type_of_specimen = $parr['type_specimen'];
				}
				break;
			}
		}
	}

	$bat_doc            = $doc;
	if (isset($adding_doc[$key])) {
		$bat_doc = date('Y-m-d H:i:s', strtotime($adding_doc[$key]));
	}

	$product_paid = $is_paid && $product_prices[$product_id] > 0;

	if ($product_paid) {
		$paid_num = 1;

		if ($pm_method == 'cod') {
			$paid_num = 3;
		}

		if ($product_red_icon) {
			$paid_num += 1;
		}
	} else if (!$product_paid && $has_insurance) {
		$paid_num = -1;
	}

	$child_assens = array();

	$priority_hours = isset($bat['priority_hours']) ? $bat['priority_hours'] : 0;
	if ($product_id > 0) {
		$priority_hours = get_post_meta($product_id, '_priority_hours', true);
		if (!$priority_hours) {
			//check by product title
			$product_title = $wpdb->get_var("select post_title from {$wpdb->posts} where ID = {$product_id} ");
			$product_title = str_replace(' ', '', $product_title);
			if (
				strpos($product_title, '6-12') !== false
				|| stripos($product_title, '12hrs') !== false
				|| stripos($product_title, '15 min') !== false
				|| stripos($product_title, 'less than 1 hour') !== false
			) {
				$priority_hours = 12;
			} else if (
				strpos($product_title, '12-24') !== false
				|| stripos($product_title, '24hrs') !== false
			) {
				$priority_hours = 24;
			}
		}
	}
	$priority_hours = intval($priority_hours);

	foreach ($all_patients as $i => $patientdata) {

		$m_key = $i > 0 ? 'm_' . ($i + 1) : '';

		$cards = array(
			'ins_card_front' => 'ins_card_' . $m_key . 'front',
			'ins_card_back' => 'ins_card_' . $m_key . 'back',
			'patient_id_front' => 'patient_id_' . $m_key . 'front',
			'patient_id_back' => 'patient_id_' . $m_key . 'back'
		);

		$ins1 = $all_insurances[$i];

		//update ins_cares_act
		if (defined('TRPDD_INS_CARES_ACT') && TRPDD_INS_CARES_ACT && $pay_type == 'noins') {
			$wpdb->update('ci_insurances', array(
				'ins_cares_act' => '1',
				'ins_company' => '',
			), array('ins_ID' => $ins1['ins_ID']));

			$ins1['ins_company'] = 'No Insurance - CARES Act';
		}

		$mid 			= 0;
		$assen 			= '';
		$base_btype 	= '';
		$grouptestid 	= '';
		$dagnostic 		= '';
		$panels     	= array();
		$urv_tests  = array();

		list($base_btype, $grouptestid) = explode('-', $btype);
		if (stripos($btype, 'pcr') !== false) {
			$base_btype = 'pcr';
			$dagnostic 	= 'Z20.828';
		} else if (stripos($btype, 'blood') !== false) {
			$base_btype = 'blood';
			$dagnostic = 'Z20.822';
		} else if (stripos($btype, 'tox') !== false) {
			$base_btype = 'tox';
		}



		$batch_tbl = tr_pdd_get_table_by_btype($base_btype);
		$meta_table = $batch_tbl . 'meta';


		$grouptestids = array($grouptestid);
		if (isset($combo_adding_tests[$base_btype]) && is_array($combo_adding_tests[$base_btype]) && count($combo_adding_tests[$base_btype]) > 0) {
			$grouptestids = array_merge($grouptestids, $combo_adding_tests[$base_btype]);
		}

		foreach ($grouptestids as $grouptestid) {
			$test_id = 0;
			$ogroups		= array();
			if (stripos($grouptestid, 'GT_') !== false) {
				$test_id = str_ireplace('GT_', '', $grouptestid);
			} else if (stripos($btype, 'G_') !== false) {
				$group_id = str_ireplace('G_', '', $grouptestid);

				$sql = "select * from ci_test_groups where tg_type = '{$base_btype}' and tg_ID = '{$group_id}' ";
				if ($group_id == 'all') {
					$sql = "select * from ci_test_groups where tg_type = '{$base_btype}' and tg_parent=0 and tg_show_req = 1 ";
				}
				$ogroups = $wpdb->get_results($sql);
			} else if ($base_btype == 'pcr') {
				if ($btype == 'pcr-UTI') {
					$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'pcr' and (tg_name = 'UTI/Wound' or tg_short_name = 'UTI') ");
				} else if ($btype == 'pcr-Wound') {
					$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'pcr' and (tg_name like '%Wound Infection%' or tg_short_name = 'Wound') ");
				} else if ($btype == 'pcr-Antigen') {
					$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'pcr' and (tg_name like '%Antigen%' or tg_short_name = 'Antigen')");

					if ($type_of_specimen == $default_type_of_specimen) {
						$type_of_specimen = 'Nasal Swab';
					}
				} else if ($btype == 'pcr-Repiratory') {
					$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'pcr' and (t)g_name = 'Upper Respiratory' or tg_short_name = 'Respiratory' ) ");
				} else {
					$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'pcr' and ( tg_is_covid = 1 or tg_name = 'nCoV-2019') ");
				}
			} else if ($base_btype == 'blood') {
				if ($btype == 'blood-Antigen') {
					$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'blood' and tg_name = 'Antigen'");
					if (count($ogroups) == 0) {
						$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'blood' and tg_short_name = 'Antigen'");
						if (count($ogroups) == 0) {
							$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'blood' and tg_name like '%Antigen%'");
						}
					}
				} else {
					$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'blood' and tg_is_covid = 1");
					if (count($ogroups) == 0) {
						$ogroups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'blood'and tg_name like  'SARS-CoV-2 Rapid%'");
					}
				}
			}


			if ($test_id > 0) {
				$panels[] = $test_id;
			} else if (count($ogroups) > 0) {
				foreach ($ogroups as $group) {

					if ($base_btype == 'tox') {
						if (in_array($group->tg_type, array('urv', 'scr'))) {
							$urv_tests = explode(',', $group->tg_comments);
							continue;
						}
					} else {
						$panels[] = 'GROUP_' . $group->tg_ID;
					}
					if (! $group->tg_only_group) {
						$ts = $wpdb->get_col("select te_ID from ci_test_panels where te_group_id = {$group->tg_ID} and te_in_req = 1");
						if (count($ts) > 0) {
							$panels = array_merge($panels, $ts);
						}
					}
				}
			}
		}

		$panel_tests_requested = implode(',', $panels);

		$assen        = tr_get_new_assen($base_btype, true, $selected_labid != $lab_id ? $selected_labshort : '');
		$accessions[] = $assen;
		$child_assens[] = $assen;

		if (!empty($dagnostic_codes_post)) {
			$dagnostic = $dagnostic_codes_post;
		} else if (!empty($form_options['symptoms_noc'])) {
			$dagnostic = $form_options['symptoms_noc'];
		}


		$bat_data = array(
			'assen'                 => $assen,
			'patient_id'            => $patientdata['pa_ID'],
			'patient_name'          => $patientdata['patient_name'],
			'dob'                   => $patientdata['pa_dob'],
			'doc'                   => $bat_doc,
			'author_ID'             => intval($user_id),
			'insurance'             => $ins1['ins_company'],
			'insurance1'            => intval($ins1['ins_ID']),
			'panel_tests_requested' => $panel_tests_requested,
			'type_of_specimen'      => $type_of_specimen,
			'batch_created'         => date_i18n('Y-m-d H:i:s'),
			'dagnostic_codes1'      => $dagnostic,
			'billing_notes'         => 'Billed by Order #' . $order_id . '. ' . $prdname,
			'doc_ID'                => intval($selected_doc_ID),
			'cli_ID'                => intval($selected_cli_ID),
			'requesting_practice'   => $selected_cli_name . '',
			'requesting_physician'  => $selected_doc_name . '',
			'lab_id'                => $selected_labid,
			'lab'                   => substr($selected_labshort, 0, 15),
		);

		if ($group_id == 'orderable') {
			$bat_data['orderable_test'] = 1;
		}

		if ($paid_num !== 0) {
			$bat_data['paid'] = $paid_num;
		}

		if ($priority_hours > 0) {
			$bat_data['priority_hours'] = $priority_hours;
		}

		if ($covid_testing['ref_cli_ID'] > 0) {
			$bat_data['ref_cli_ID'] = $covid_testing['ref_cli_ID'];
		} else if ($form_options['refcli_ID'] > 0) {
			$bat_data['ref_cli_ID'] = $form_options['refcli_ID'];
		}

		if (!empty($bat['p_condition'])) {
			$bat_data['p_condition'] = $bat['p_condition'];
		}

		if (!empty($bat['company'])) {
			$bat_data['notes'] = 'Company/Group: ' . $bat['company'];
		}


		if (is_array($insu_photos)) {
			foreach ($cards as $ck => $cf) {
				if (! empty($insu_photos[$cf])) {
					$bat_data[$ck] = $insu_photos[$cf];
				}
			}
		}


		$bat_data['patient_signature'] = 'onfile';
		$rs                            = $wpdb->insert($batch_tbl, $bat_data);
		if ($rs === false) {
			$logs[] = "Insert {$base_btype} fail " . $wpdb->last_error;
		}

		$mid = $wpdb->insert_id;
		if (isset($_GET['fix_order'])) {
			var_dump($wpdb->last_error);
		}

		if ($mid > 0) {

			$wpdb->update($batch_tbl, array(
				'patient_portal_type' => $patient_portal_type
			), array('mid' => $mid));

			if ($i == 0) {
				update_post_meta($order_id, '_' . $base_btype . '_mid', $mid);
			}

			if ($base_btype == 'tox' && count($urv_tests) > 0) {
				foreach ($urv_tests as $urvid) {
					$wpdb->insert('ci_result_test_speciments', array(
						'mid' => $mid,
						'testid' => $urvid,
						'checked' => '1'
					));
				}
			}
		}


		if ($mid > 0 && !empty($batch_meta)) {
			foreach ($batch_meta as $mtk => $mtvl) {
				$mtvl = is_array($mtvl) ? maybe_serialize($mtvl) : $mtvl;
				$wpdb->insert($meta_table, array($mtk => $mtvl));
			}
		}

		//end btype create

		$batch_created_types[] = array(
			'btype' => $base_btype,
			'mid'   => $mid,
			'assen'   => $assen,
			'doc'   => $bat_doc,
			'product' => $prdname,
		);
	} //end all_patient foreach

	$child_assens = implode(',', $child_assens);
	if ($order_item_id > 0) {
		wc_update_order_item_meta($order_item_id, '_btype', $btype);
		wc_update_order_item_meta($order_item_id, 'Accession', $child_assens);
		if (! empty($bat_doc)) {
			$dt_orderitem = date('M d @ h:iA', strtotime($bat_doc));
			wc_update_order_item_meta($order_item_id, 'Date Time', $dt_orderitem);
		}
	}


	//update count req
	$batDate = date('Y-m-d', strtotime($bat_doc));
	$batTime = date('H:i', strtotime($bat_doc));

	if (!empty($scheduling_tbl)) {
		$count_sreq = count($all_patients);

		if (! empty($location)) {
			$max_col   = 'location_' . $location;
			$count_col = 'location_count_' . $location;

			$old_count = $wpdb->get_var("select {$count_col} from {$scheduling_tbl} where edate='{$batDate}' and etime='{$batTime}' ");
			$wpdb->update($scheduling_tbl, array($count_col => $old_count + $count_sreq), array(
				'edate' => $batDate,
				'etime' => $batTime
			));
		} else {
			$old_count = $wpdb->get_var("select count_req from {$scheduling_tbl} where edate='{$batDate}' and etime='{$batTime}' ");
			$wpdb->update($scheduling_tbl, array('count_req' => $old_count + $count_sreq), array(
				'edate' => $batDate,
				'etime' => $batTime
			));
		}
	}
} //end foreach $tests

$accessions = implode(', ', $accessions);

update_post_meta($order_id, '_accessions', $accessions);
update_post_meta($order_id, '_patient_id', $all_patients[0]['pa_ID']);
if (!empty($location)) {
	update_post_meta($order_id, '_location', $location);
}
if ($pa_options['sms_fl_name'] && strpos($patient_name, ',') > 0) {
	list($last_name, $first_name) = explode(',', $patient_name);
	$patient_name = trim($first_name) . ' ' . trim($last_name);
}



$df_sms_msg = $pa_options['sms_new_order_msg'];
if (empty($df_sms_msg)) {
	$df_sms_msg = 'Thank you and Welcome to {lab name} - Your Lab order is {accession}';
}
$df_sms_msg = str_replace(array('{lab}', '{lab name}'), $lab_name, $df_sms_msg);
$df_sms_msg = str_replace(array('{patient name}', '{patient_name}'), $patient_name, $df_sms_msg);
$df_sms_msg = str_replace('{location}', $location_name, $df_sms_msg);
$df_sms_msg = str_replace(array('{location_address}', '{location address}'), $location_address, $df_sms_msg);
$df_sms_msg = str_replace(array(
	'{description}',
	'{location_description}'
), $location_description, $df_sms_msg);


if ($sms_notify && $tr_sms_gateway && is_object($tr_sms_gateway) && strlen($phone_num) > 8) {
	try {
		$loaded = $tr_sms_gateway->load_library();

		if ($loaded) {
			$init = $tr_sms_gateway->init_api();
		}
		$count_sms = count($adding_doc) > 0 ? count($batch_created_types) : 1;

		for ($isms = 0; $isms < $count_sms; $isms++) {

			$sms_assen = $count_sms == 1 ? $accessions : $batch_created_types[$isms]['assen'];
			$sms_mid   = 0;
			$sms_btype = '';
			$sms_doc = '';
			if ($count_sms == 1) {
				foreach ($batch_created_types as $arr) {
					$sms_mid   = $arr['mid'];
					$sms_btype = $arr['btype'];
					$sms_doc = $arr['doc'];
				}
			} else {
				$sms_mid = $batch_created_types[$isms]['mid'];
				$sms_btype = $batch_created_types[$isms]['btype'];
				$sms_doc = $batch_created_types[$isms]['doc'];
			}


			$datetime = date('M d @ h:iA', strtotime($sms_doc));
			$datetime2 = date('m/d/Y', strtotime($sms_doc)) . ' at ' . date('H:i', strtotime($sms_doc));


			$sms_message = $df_sms_msg;

			$sms_message = str_replace(array('{accessions}', '(accession)', '{accession}'), $sms_assen, $sms_message);
			$sms_message = str_replace('{datetime}', $datetime, $sms_message);
			$sms_message = str_replace('{datetime2}', $datetime2, $sms_message);
			$sms_message = str_replace(array('@,', '@ ,', '@  ,'), '', $sms_message);


			$sent = $wpdb->get_var("select mid from ci_sent_notify where mid = {$sms_mid} 
									and btype = '{$sms_btype}'
									and send_type='new'");

			if (! $sent) {
				$rs = $tr_sms_gateway->send_sms($phone_num, $sms_message, $patient_name, $sms_assen, 'no', $main_patient_id);
				if ($rs) {
					if ($count_sms == 1) {
						foreach ($batch_created_types as $arr) {
							$wpdb->insert('ci_sent_notify', array(
								'mid'       => $arr['mid'],
								'btype'     => $arr['btype'],
								'send_type' => 'new',
								'created'   => date_i18n('Y-m-d H:i:s')
							));
						}
					} else {
						$wpdb->insert('ci_sent_notify', array(
							'mid'       => $sms_mid,
							'btype'     => $sms_btype,
							'send_type' => 'new',
							'created'   => date_i18n('Y-m-d H:i:s')
						));
					}
				}
			}
		}
	} catch (Exception $e) {
	}
}






//email to patient
if (!empty($form_options['new_order_email_msg']) && strlen($form_options['new_order_email_msg']) > 10 && !empty($email) && is_email($email)) {
	$email_msg = $form_options['new_order_email_msg'] . "\n";
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

	$from_name = get_bloginfo('name');
	$from_email = $pa_options['email_from'];
	if (!empty($from_email) && is_email($from_email)) {
		$headers .= 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
	}

	$tests_date_time = '';
	foreach ($batch_created_types as $arrd) {
		$doctime = strtotime($arrd['doc']);
		$dtimeformated = date('M d Y', $doctime) . ' at ' . date('h:i A', $doctime);
		$tests_date_time .= '<div><b>' . $arrd['product'] . '</b>: ' . $dtimeformated . '</div>';
	}

	$payment_status = '';
	if ($pm_method == 'cod') {
		$payment_status = 'Pay at the Lab';
	} else if ($is_paid && $total_price > 0) {
		$payment_status = '<span style="color:green">Paid</span>';
	} else {
		$payment_status = $pm_method;
	}



	$email_msg = str_replace(array('{lab}', '{lab name}'), $lab_name, $email_msg);
	$email_msg = str_replace(array('{second_logo}'), $second_logo_html, $email_msg);
	$email_msg = str_replace(array('{patient name}', '{patient_name}'), $patient_name, $email_msg);
	$email_msg = str_replace(array('{patient dob}', '{patient_dob}'), $patient_dob, $email_msg);
	$email_msg = str_replace(array('{patient email}', '{patient_email}'), $email, $email_msg);
	$email_msg = str_replace(array('{tests_date_time}'), $tests_date_time, $email_msg);
	$email_msg = str_replace(array('{accessions}', '(accession)', '{accession}'), $accessions, $email_msg);
	$email_msg = str_replace('{datetime}', $datetime, $email_msg);
	$email_msg = str_replace('{datetime2}', $datetime2, $email_msg);
	$email_msg = str_replace('{location}', $location_name, $email_msg);
	$email_msg = str_replace('{payment_status}', $payment_status, $email_msg);
	$email_msg = str_replace(array('{description}', '{location_description}'), $location_description, $email_msg);
	$email_msg = str_replace(array('{location_address}', '{location address}'), $location_address, $email_msg);
	$email_msg = str_replace(array('@,', '@ ,', '@  ,'), '', $email_msg);

	$rs = wp_mail($email, "Your Order from {$lab_name}", $email_msg, $headers);
	//wp_mail('pntrinh29@gmail.com',"Your Order from {$lab_name}",$email_msg,$headers);

} else if ($pa_options['email_new_notify'] && !empty($email) && is_email($email)) {

	$email_msg = $sms_message . "\n";
	$headers = '';

	$from_name = get_bloginfo('name');
	$from_email = $pa_options['email_from'];
	if (!empty($from_email) && is_email($from_email)) {
		$headers .= 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
	}

	$rs = wp_mail($email, "Your Order from {$lab_name}", $email_msg, $headers);
}

//email to admin
$admin_email_key = 'admin_email';
$admin_sms_key = 'admin_sms';
$admin_sms_msg_key = 'admin_sms_new_order_msg';
$admin_locations_key = 'admin_locations';

if (!empty($custom_form) && isset($pa_options['admin_email_' . $custom_form])) {
	$admin_email_key = 'admin_email_' . $custom_form;
	$admin_sms_key = 'admin_sms_' . $custom_form;
	$admin_sms_msg_key = 'admin_sms_new_order_msg_' . $custom_form;
	$admin_locations_key = 'admin_locations_' . $custom_form;
}


if (empty($pa_options[$admin_locations_key]) || in_array($location, $pa_options[$admin_locations_key])) {

	$datetime = date('M d @ h:iA', strtotime($doc));
	$datetime2 = date('m/d/Y', strtotime($doc)) . ' at ' . date('H:i', strtotime($doc));


	if (! empty($pa_options[$admin_email_key])) {

		$subject = "Drive-Thru Order {$datetime}";
		$message = "Accessions: {$accessions}\n";
		$message .= "Date-Time: {$datetime}\n";

		$location_fields = $covid_testing['location_fields'];
		if (is_array($location_fields) && !empty($location_fields['address'])) {
			$message .= "Location Address: {$location_fields['address']} \n";
			$message .= "Location City: {$location_fields['city']} \n";
			$message .= "Location State: {$location_fields['state']} \n";
			$message .= "Location Zip: {$location_fields['zip']} \n";
			$message .= "--------------------------- \n";
		} else if (isset($covid_testing['location_store_number']) && $covid_testing['location_store_number'] > 0) {
			$message .= "Location Store Number: {$covid_testing['location_store_number']} \n";
		}

		foreach ($all_patients as $i => $patientdata) {
			$message .= "Patient: {$patientdata['patient_name']}\n";
			$message .= "DOB: {$patientdata['pa_dob']}\n";
			$message .= "Gender: {$patientdata['pa_gender']}\n";
			$message .= "Phone: {$patientdata['pa_phone']}\n";
			$message .= "Email: {$patientdata['pa_email']}\n";
			if (!empty($patientdata['pa_passport'])) $message .= "Passport: {$patientdata['pa_passport']}\n";
			$message .= "Street Address: {$patientdata['pa_address']}\n";
			$message .= "City: {$patientdata['pa_city']}\n";
			$message .= "State: {$patientdata['pa_state']}\n";
			$message .= "Zip: {$patientdata['pa_zip']}\n";

			if ($all_insurances[$i]) {
				$ins1 = $all_insurances[$i];
				$message .= "\n";
				$message .= "Insurance Last Name: {$ins1['ins_last']}\n";
				$message .= "Insurance First Name: {$ins1['ins_first']}\n";
				$message .= "Relation to Patient: {$ins1['ins_relation']}\n";
				$message .= "Insurance Company Name: {$ins1['ins_company']}\n";
				$message .= "Member ID: {$ins1['ins_member_id']}\n";
				$message .= "Group #: {$ins1['ins_group_id']}\n";
			}
			$message .= "------------------------------------------\n";
		}

		if (! empty($location_name)) {
			$message .= "Location: {$location_name}\n";
		}
		if (! empty($location_address)) {
			$message .= "Location Address: {$location_address}\n";
		}

		$product_names = implode(', ', $product_names);
		$message       .= "Items: {$product_names}";

		$headers = '';

		$from_name = get_bloginfo('name');
		$from_email = $pa_options['email_from'];
		if (!empty($from_email) && is_email($from_email)) {
			$headers = 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
		}

		wp_mail($pa_options[$admin_email_key], $subject, $message, $headers);
	}

	//sms to admin
	if (
		$tr_sms_gateway && is_object($tr_sms_gateway)
		&& ! empty($pa_options[$admin_sms_key])
		&& strlen($pa_options[$admin_sms_key]) > 9
		&& ! empty($pa_options[$admin_sms_msg_key])
	) {

		try {
			$admin_sms_message = trim($pa_options[$admin_sms_msg_key]);
			if (empty($admin_sms_message)) {
				$admin_sms_message = 'New order -  appointment is {datetime} at {location} - order is {accession}';
			}

			$admin_sms_message = str_replace(array('{lab}', '{lab name}'), $lab_name, $admin_sms_message);
			$admin_sms_message = str_replace(array('{patient name}', '{patient_name}'), $patient_name, $admin_sms_message);
			$admin_sms_message = str_replace(array('{accessions}', '(accession)', '{accession}'), $accessions, $admin_sms_message);
			$admin_sms_message = str_replace('{datetime}', $datetime, $admin_sms_message);
			$admin_sms_message = str_replace('{datetime2}', $datetime2, $admin_sms_message);
			$admin_sms_message = str_replace('{location}', $location_name, $admin_sms_message);
			$admin_sms_message = str_replace(array('{description}', '{location_description}'), $location_description, $admin_sms_message);
			$admin_sms_message = str_replace(array('{location_address}', '{location address}'), $location_address, $admin_sms_message);
			$admin_sms_message = str_replace(array('@,', '@ ,', '@  ,'), '', $admin_sms_message);

			$loaded = $tr_sms_gateway->load_library();

			if ($loaded) {
				$init = $tr_sms_gateway->init_api();
			}

			$rs = $tr_sms_gateway->send_sms($pa_options[$admin_sms_key], $admin_sms_message, '', '', 'a');
		} catch (Exception $e) {
		}
	}
}



//end file