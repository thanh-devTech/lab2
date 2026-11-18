<?php

if (!function_exists('trcppr_is_new_style_enabled')) {
	function trcppr_is_new_style_enabled()
	{
		$pa_options = get_option('trpatient_settings', array());
		return !empty($pa_options['new_style']);
	}
}

function tr_patient_get_phone_arr($phone)
{
	$phone = str_replace(array('+1', '(', ')', '-', ' '), '', $phone);
	$phones = array();
	if (strlen($phone) > 5 && !in_array($phone, array('0000000', '00000000', '000000000', '0000000000'))) {
		$phones[] = $phone;
		$phones[] = '+1' . $phone;
		$phones[] = str_replace('+1', '', $phone);
		$phones[] = preg_replace("/(\d{3})(\d{3})(\d{4})/", '$1-$2-$3', $phone);
		$phones[] = preg_replace("/(\d{3})(\d{3})(\d{4})/", '($1) $2 - $3', $phone);
	}
	return $phones;
}


function tr_patient_get_kids($patient_id = 0, $user_id = 0)
{
	global $wpdb;
	static $tr_checked_kids;

	$pa_options = get_option('trpatient_settings', array());
	if (!isset($pa_options['disable_18kid'])) {
		$pa_options['disable_18kid'] = false;
	}

	if ($user_id == 0) {
		$user_id = get_current_user_id();
	}
	$kids = get_user_meta($user_id, '_patient_kids', true);
	if (! is_array($kids)) {
		$kids = array();
	}

	if ($patient_id == 0) {
		$patient_id = tr_patient_get_id_patient($user_id);
	}
	$phone = get_user_meta($user_id, 'phone', true);
	$main_dob = get_user_meta($user_id, '_dob', true);

	$phone = str_replace(array('+1', '(', ')', ' ', '-'), '', $phone);

	if (strlen($phone) > 8 && strpos($phone, '000000') === false) {
		$main_patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patient_id} ");
		if (empty($main_dob)) {
			$main_dob = $main_patient->pa_dob;
		}

		$phones   = array($phone);
		$phones[] = '+1' . $phone;
		$phones[] = preg_replace("/(\d{3})(\d{3})(\d{4})/", '$1-$2-$3', $phone);
		$phones[] = preg_replace("/(\d{3})(\d{3})(\d{4})/", '($1) $2 - $3', $phone);
		$phones   = implode("','", $phones);

		$patients = $wpdb->get_results("select *
                            from ci_patients 
                            where pa_phone in ('{$phones}') 
                            and pa_ID != {$patient_id} ");



		if (! $tr_checked_kids && count($patients) > 0 && (count($kids) == 0 || $main_dob != $main_patient->pa_dob)) {
			if ($main_dob != $main_patient->pa_dob) {
				foreach ($patients as $ii => $patient) {
					if (!empty($patient->pa_dob) && $patient->pa_dob == $main_dob) {
						//need correct this patient
						$patient_id = $patient->pa_ID;
						$updatearr = array('patient_id' => $patient_id);
						update_user_meta($user_id, 'first_name', $patient->pa_first);
						update_user_meta($user_id, 'last_name', $patient->pa_last);
						update_user_meta($user_id, 'birthday', $patient->pa_dob);
						if (!empty($patient->pa_email) && is_email($patient->pa_email)) {
							$updatearr['user_email'] = $patient->pa_email;
						}
						$wpdb->update($wpdb->users, $updatearr, array('ID' => $user_id));

						unset($patients[$ii]);
						$patients[] = $main_patient;

						break;
					}
				}
			}
			/*
			foreach ( $patients as $patient ) {
				if ( $patient->pa_ID != $patient_id
					 && ( $patient->pa_last == $main_patient->pa_last  )
					 && ( $patient->pa_first !== $main_patient->pa_first  )
				) {
					if($pa_options['disable_18kid'])
					{

					}
					$kids[ $patient->pa_ID ] = array(
						'first_name' => $patient->pa_first,
						'last_name'  => $patient->pa_last,
						'birthday'   => $patient->pa_dob,
					);
				}
			}*/

			if (count($kids) > 0) {
				update_user_meta($user_id, '_patient_kids', $kids);
			}

			$tr_checked_kids = true;
		}
	}

	return $kids;
}


function tr_patient_get_id_patient($user_id)
{
	global $wpdb;
	$patient_id = $wpdb->get_var("select patient_id from {$wpdb->users} where ID = {$user_id}");
	if ($patient_id == 0) {
		$patient_id = get_user_meta($user_id, '_patient_id', true);
	}
	$main_role 		= get_user_meta($user_id, 'wp_capabilities', true);
	$current_user 	= get_user_by('id', $user_id);
	$is_patient_role = false;
	if ($current_user && isset($current_user->roles) && isset($current_user->roles[0])) {
		$is_patient_role = $current_user->roles[0] == 'patient';
	}


	if (is_array($main_role) && !empty($main_role[0])) {
		$is_patient_role = false;
	}

	if ($patient_id > 0 && $is_patient_role) {
		$user_phone = get_user_meta($user_id, 'phone', true);
		$user_phone = str_replace(array('(', ')', '+1', '+', '.', '-', ' '), '', $user_phone);

		if (!empty($user_phone)) {
			$patient_phone = $wpdb->get_var("select pa_phone from ci_patients where pa_ID = {$patient_id} ");
			if (!empty($patient_phone)) {
				$patient_phone = str_replace(array('(', ')', '+1', '+', '.', '-', ' '), '', $patient_phone);

				if ($patient_phone != $user_phone && strpos($patient_phone, '000000') === false) {
					//search new patient id
					$phones = implode("','", tr_patient_get_phone_arr($user_phone));
					$patients = array();
					if (!empty($phones)) {
						$patients = $wpdb->get_results("select * from ci_patients where pa_phone in ('{$phones}') ");
					}


					if (count($patients) == 1) {
						$patient_id = $patients[0]->pa_ID;
						$wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
					}
				}
			}
		}
	}

	return $patient_id;
}


function tr_patient_location_by_url($loc_key)
{
	global $trr_current_location;
	if (empty($trr_current_location)) {
		$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form']) ? $_REQUEST['custom_form'] : '';

		if (stripos($_SERVER['REQUEST_URI'], 'concierge') !== false) {
			$custom_form = 'concierge';
		}
		if (stripos($_SERVER['REQUEST_URI'], 'canada') !== false) {
			$custom_form = 'canada';
		}

		$form_key = 'trpatient_product_settings';
		if (!empty($custom_form)) {
			$form_key = 'trpatient_' . $custom_form . '_settings';
		}

		$form_options = get_option($form_key, array());
		$loc_key = strtolower($loc_key);
		foreach ($form_options['locations'] as $arr) {
			if (strtolower($arr['url']) == $loc_key) {
				$trr_current_location = $arr;
				break;
			}
		}
	}
	return $trr_current_location;
}


function trpatient_get_Lat_LNG($my_address)
{
	global $tr_lat_lng_error;
	if (!is_array($tr_lat_lng_error)) {
		$tr_lat_lng_error = array();
	}

	$pa_options = get_option('trpatient_settings', array());
	$keys = array();
	$keys[] = 'AIzaSyCSsqCod6i_Bj4MwMRduis43ckbw8VgQhE';
	if (!empty($pa_options['gg_map_key'])) {
		$keys[] = $pa_options['gg_map_key'];
	}

	$keys[] = 'AIzaSyDbkxGUVm1UqzzYWBGTG-Iil3p8h5hotxA';
	$response = null;
	$output = null;
	foreach ($keys as $key) {
		$url        = "https://maps.google.com/maps/api/geocode/json?key={$key}&address=";

		$url_query  = str_replace(array(' ', '#'), '%20', $my_address) . "&sensor=false";
		$headers = array('referer' => get_bloginfo('url') . '/');
		$url = $url . $url_query;

		$response = wp_remote_get($url, array('timeout' => 10, 'headers' => $headers));
		$geocode    = !is_object($response['body']) ? $response['body'] : null;
		$output     = @json_decode($geocode);

		if (
			is_wp_error($response)
			|| stripos($geocode, 'Sorry...') !== false
			|| stripos($geocode, 'REQUEST_DENIED') !== false
			|| $output->status == 'OVER_QUERY_LIMIT'
			|| $output->status == 'REQUEST_DENIED'
			|| $output == null
		) {
			//error
			$tr_lat_lng_error[$my_address] = 'error: ' . $response['body'] . ' by key:' . $key;

			$output = null;
		} else {
			$tr_lat_lng_error[$my_address] = '';
			break;
		}
	}

	if ($output == null) {
		$tr_lat_lng_error[$my_address] = $response['body'];
		$url2 = 'http://ngoctrinh.net/getlatlng/get.php?call=true&address=' . urlencode($my_address);

		$response = wp_remote_get($url2, array('timeout' => 5));
		$geocode    = !is_wp_error($response) ? $response['body'] : '';
		$output     = @json_decode(trim($geocode));
	}
	$array = false;
	if (is_object(@$output->results[0]->geometry->location)) {

		$array = array();
		$array['lat'] = $output->results[0]->geometry->location->lat;
		$array['lng'] = $output->results[0]->geometry->location->lng;

		$location = $output->results[0]->address_components;
		$array['address_country'] = $location[6]->short_name;
		$array['address_state'] = $location[5]->short_name;
		$array['address_zip'] = $location[7]->short_name;
		$array['address_city'] = $location[3]->short_name;
		$array['address_street'] = $location[0]->short_name . ' ' . $location[1]->short_name;
	}
	return $array;
}


function tr_get_key_hourmin($hour, $min = '')
{
	if ($min == 'auto') {
		$amp = stripos($hour, 'pm') > 0 ? 'pm' : 'am';
		$hour = str_ireplace(array('am', 'pm'), '', $hour);
		list($hour, $min) = explode(':', $hour);
		if ($amp == 'pm' && $hour < 12) {
			$hour += 12;
		}
	}
	$hour = intval($hour);
	$min = intval($min);
	$hour = ($hour < 10) ? '0' . $hour : $hour;
	$min = ($min < 10) ? '0' . $min : $min;
	return $hour . ':' . $min;
}

function tr_display_hourmin($hourmin)
{
	list($hour, $min) = explode(':', $hourmin);
	$hour = intval($hour);
	$min = intval($min);
	$apm = ($hour < 12 || $hour == 24) ? 'AM' : 'PM';
	if (stripos($hourmin, 'pm') > 0) {
		$apm = 'PM';
	}
	$hour = ($hour > 12) ? $hour - 12 : $hour;
	$hourmin = tr_get_key_hourmin($hour, $min) . ' ' . $apm;

	return $hourmin;
}

function tr_min2hourmin($num)
{
	$num = intval($num);
	$hours = ($num / 60);
	$rhours = floor($hours);
	$minutes = ($hours - $rhours) * 60;
	$rminutes = round($minutes);
	return ($rhours < 10 ? '0' . $rhours : $rhours) . ":" . ($rminutes < 10 ? '0' . $rminutes : $rminutes);
}

function tr_hourmin2min($str)
{
	$arr = explode(':', $str);
	return intval($arr[0]) * 60 + intval($arr[1]);
}

function tr_patient_dec2hex36($number)
{
	$hexvalues = array(
		'0',
		'1',
		'2',
		'3',
		'4',
		'5',
		'6',
		'7',
		'8',
		'9',
		'a',
		'b',
		'c',
		'd',
		'e',
		'f',
		'g',
		'h',
		'i',
		'j',
		'k',
		'l',
		'm',
		'n',
		'o',
		'p',
		'q',
		'r',
		's',
		't',
		'u',
		'v',
		'w',
		'x',
		'y',
		'z'
	);

	$hexval = '';
	while ($number != '0') {
		$hexval = $hexvalues[bcmod($number, '36')] . $hexval;
		$number = bcdiv($number, '36', 0);
	}
	return $hexval;
}

function tr_patient_hex2dec36($number)
{
	$decvalues = array(
		'0' => '0',
		'1' => '1',
		'2' => '2',
		'3' => '3',
		'4' => '4',
		'5' => '5',
		'6' => '6',
		'7' => '7',
		'8' => '8',
		'9' => '9',
		'a' => '10',
		'b' => '11',
		'c' => '12',
		'd' => '13',
		'e' => '14',
		'f' => '15',
		'g' => '16',
		'h' => '17',
		'i' => '18',
		'j' => '19',
		'k' => '20',
		'l' => '21',
		'm' => '22',
		'n' => '23',
		'o' => '24',
		'p' => '25',
		'q' => '26',
		'r' => '27',
		's' => '28',
		't' => '29',
		'u' => '30',
		'v' => '31',
		'w' => '32',
		'x' => '33',
		'y' => '34',
		'z' => '35',

	);
	$decval = 0;
	$number = strrev($number);
	for ($i = 0; $i < strlen($number); $i++) {
		$decval = bcadd(bcmul(bcpow('36', $i, 0), $decvalues[$number[$i]]), $decval);
	}
	return $decval;
}

function tr_patient_encode_id_btype($id, $btype)
{
	$keyid = tr_patient_dec2hex36($id);
	$lengthbtype = strlen($btype);
	$lengthcode = 34 - strlen($keyid . $btype);
	$code = md5($keyid . $id . $btype);
	$code = substr($code, 0, $lengthcode);
	return $code . $keyid . $btype . tr_patient_dec2hex36($lengthcode) . tr_patient_dec2hex36($lengthbtype);
}

function tr_patient_decode_id_btype($keystr)
{
	$arr = array('id' => 0, 'btype' => '');
	$lengthbtype = tr_patient_hex2dec36(substr($keystr, -1, 1));
	$lengthcode = tr_patient_hex2dec36(substr($keystr, -2, 1));
	$lengthkey = strlen($keystr) - $lengthcode - $lengthbtype - 2;

	$code = substr($keystr, 0, $lengthcode);
	$keyid = substr($keystr, $lengthcode, $lengthkey);
	$btype = substr($keystr, $lengthcode + $lengthkey, $lengthbtype);
	$id = tr_patient_hex2dec36($keyid);

	$codenew = md5($keyid . $id . $btype);
	$codenew = substr($codenew, 0, $lengthcode);

	if ($codenew == $code) {
		$arr['btype'] = $btype;
		$arr['id'] = $id;
	}
	return $arr;
}

function tr_patient_qr_by_report($id, $btype, $width = 300, $border = 0)
{
	$key = tr_patient_encode_id_btype($id, $btype);
	$url = get_bloginfo('url') . '/qrsc/' . $key;
	if (!function_exists('trpdd_get_qr_code')) {
		include_once(TRCPPR_PATH . 'inc/functions_qr.php');
	}
	$qr = trpdd_get_qr_code($url, $width, 'H', 15, $border);
	return $qr;
}

function tr_patient_qr_url_report($id, $btype)
{
	$key = tr_patient_encode_id_btype($id, $btype);
	$url = get_bloginfo('url') . '/qr/' . $key;
	return $url;
}

function tr_patient_get_form_options()
{
	global $tr_form_options;

	if (isset($tr_form_options) && is_array($tr_form_options)) {
		return $tr_form_options;
	}

	if (!session_id()) {
		session_start();
	}

	if (!isset($_REQUEST['custom_form']) && isset($_SESSION['trp_custom_form'])) {
		$_REQUEST['custom_form'] = $_SESSION['trp_custom_form'];
	} else if (isset($_REQUEST['custom_form'])) {
		$_SESSION['trp_custom_form'] = $_REQUEST['custom_form'];
	}

	$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form']) ? $_REQUEST['custom_form'] : '';
	if (isset($_REQUEST['is_concierge']) && $_REQUEST['is_concierge']) {
		$custom_form = 'concierge';
	} else if (isset($_REQUEST['is_canada']) && $_REQUEST['is_canada']) {
		$custom_form = 'canada';
	} else if (isset($_REQUEST['is_meijer']) && $_REQUEST['is_meijer']) {
		$custom_form = 'meijer';
	}

	$form_key = 'trpatient_product_settings';
	if (!empty($custom_form)) {
		$form_key = 'trpatient_' . $custom_form . '_settings';
	}

	$tr_form_options = get_option($form_key, array());

	return $tr_form_options;
}


function trpa_patient_code_exist($code)
{
	global $wpdb;
	return $wpdb->get_var("select pa_ID from ci_patients where pa_code = BINARY '{$code}' ");
}


function trpa_accession_code_exist($code)
{
	global $wpdb;
	$id = 0;
	if (function_exists('tr_pdd_get_batch_types')) {
		//search assen
		foreach (tr_pdd_get_batch_types() as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$id = $wpdb->get_var("select ba.patient_id from {$batch_tbl} ba
			                    JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
								where ba.assen = '{$code}'
								");
			if ($id > 0) {
				break;
			}
		}
	}
	return $id;
}

function trpa_patient_id_from_code($code, $pa_first, $pa_last, $pa_dob)
{
	global $wpdb;
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	$code = trim($code);
	$where_patient = '';

	if ($pa_last !== false || empty($code)) {
		$where_patient .= " and pa_last = '{$pa_last}' ";
	}

	if ($pa_dob !== false || empty($code)) {
		if (strpos($pa_dob, '/') > 0) {
			$pa_dob = date('Y-m-d', strtotime($pa_dob));
		}
		$where_patient .= "and pa_dob = '{$pa_dob}' ";
	}


	if (empty($code) || ($pa_first !== false && (!isset($ci_patient_options['disable_vfn']) || !$ci_patient_options['disable_vfn']))) {
		$where_patient .= " and pa_first = '{$pa_first}' ";
	}

	$id = $wpdb->get_var("select pa_ID from ci_patients 
								where pa_code = BINARY '{$code}' 
								{$where_patient} ");

	if ($id == 0 && $ci_patient_options['disable_fmas']) {
		$id = $wpdb->get_var("select pa_ID from ci_patients 
								where 1 {$where_patient} ");
	}

	if ($id == 0 && function_exists('tr_pdd_get_batch_types')) {
		//search assen
		foreach (tr_pdd_get_batch_types() as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$sql_2 = "select ba.patient_id from {$batch_tbl} ba
			                    JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
								where ba.assen = '{$code}' 
								{$where_patient} ";


			$id = $wpdb->get_var($sql_2);
			if (isset($_GET['debug'])) {
				var_dump($wpdb->last_query);
				var_dump($wpdb->last_error);
			}
			if ($id > 0) {
				break;
			}
		}
	}


	if ($id == 0 && function_exists('trpdd_patient_decode')) {
		//decode here
		//$id = trpdd_patient_decode($code);
	}

	return $id;
}


function trpa_set_patient_code($user_id, $code, $pa_first, $pa_last, $pa_dob)
{
	global $wpdb;
	$user = wp_get_current_user();

	$code = trim($code);
	update_user_meta($user_id, '_patient_code', $code);
	$patient_id = trpa_patient_id_from_code($code, $pa_first, $pa_last, $pa_dob);
	if ($patient_id > 0) {
		if (isset($user->roles[0]) && $user->roles[0] == 'administrator') {
			update_user_meta($user_id, '_patient_id', $patient_id);
			$wpdb->update($wpdb->users, array('patient_id' => 0), array('ID' => $user_id));
		} else {
			$wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
		}
	}
	return $patient_id;
}

function trpa_get_pa_settings($key)
{
	static $pa_options;
	if (!is_array($pa_options)) {
		$pa_options = get_option('trpatient_settings', array());
	}
	return isset($pa_options[$key]) ? $pa_options[$key] : null;
}

function trpa_get_form_settings($key)
{
	static $form_options;
	if (!is_array($form_options)) {
		$form_options = tr_patient_get_form_options();
	}
	return isset($form_options[$key]) ? $form_options[$key] : null;
}

function trpa_get_results_url()
{
	global $curent_role;
	$url = admin_url('admin.php?page=ci_patient_results');
	if (trpa_get_pa_settings('new_style') && $curent_role == 'patient') {
		$url = home_url(PATIENT_URL_RESULTS);
	}
	return $url;
}

//end file
