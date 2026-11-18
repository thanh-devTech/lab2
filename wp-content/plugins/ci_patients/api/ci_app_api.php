<?php
require_once  'restful_api.php';

add_filter('https_ssl_verify', '__return_false');

class CI_APP_Api extends restful_api
{
	var $_cookie = 'wp_woocommerce_session_' . COOKIEHASH;


	function __construct($pre_endpoint)
	{
		$this->hide_info = false;
		$this->enable_log = true;

		parent::__construct($pre_endpoint);
	}

	protected function check_key()
	{
		$key = "b0u20asjdkljqlhjseooo";
		if (empty($this->request_api_key) || $key != $this->request_api_key) {
			$this->response(405, array('status' => 'error', 'message' => 'wrong key'));
			exit;
		}
	}

	protected function _tostring($val)
	{
		if (is_null($val)) return '';
		return (string)$val;
	}

	protected function _card_url($url)
	{
		$dir 	 = tr_get_ins_card_dir();
		if (is_null($url) || empty($url)) return '';
		if (strpos($url, 'http') === 0) {
			return $url;
		}
		$url = $dir['url'] . '/' . $url . '?t=' . filemtime($dir['path'] . '/' . $url);
		return $url;
	}

	protected function _step_detail($step)
	{
		$steps = array();
		$steps[1] = array('title' => 'Testing Order Received', 'desc' => 'Test Requisition has been received!');
		$steps[2] = array('title' => 'Collected', 'desc' => 'Your sample it\'s on its way to the lab for processing...');
		$steps[3] = array('title' => 'Received', 'desc' => 'Your sample has been received by the lab...');
		$steps[4] = array('title' => 'Testing', 'desc' => 'Your sample is now being tested...');
		$steps[5] = array('title' => 'Completed', 'desc' => 'Your Results are now available!');
		return isset($steps[$step]) ? $steps[$step] : array('title' => '', 'desc' => '');
	}

	protected function _phone_exists($phone)
	{
		global $wpdb;
		$exists = false;
		$pa_ID = $wpdb->get_var($wpdb->prepare("select pa_ID from ci_patients where pa_phone=%s", $phone));
		$exists =  $pa_ID > 0;

		return $exists;
	}


	protected function getuserid_by_token($token)
	{
		global $wpdb;
		$user_id = $wpdb->get_var($wpdb->prepare("select user_id from ci_user_tokens where token=%s", $token));
		if ($user_id > 0) {
			$wpdb->update('ci_user_tokens', array(
				'last_active'   => date_i18n('Y-m-d H:i:s')
			), array('token' => $token));

			wp_set_current_user($user_id);
			wc_load_cart();
			wc_clear_notices();
			return $user_id;
		}
		return 0;
	}

	protected function _get_profile_data($patient_id)
	{
		global $wpdb;
		$patient =  $wpdb->get_row("select * from ci_patients where pa_ID='{$patient_id}'");
		$insurance = $wpdb->get_row("select * from ci_insurances where pa_ID='{$patient_id}' and ins_is_second = 0 order by ins_ID desc limit 1");


		return array(
			'pa_ID'    		=> $patient->pa_ID,
			'patientID'    		=> $patient->pa_code,
			'ins_ID'    	=> $insurance ? $insurance->ins_ID : 0,
			'email'   		 => $this->_tostring($patient->pa_email),
			'first_name' 	=> $this->_tostring($patient->pa_first),
			'last_name' 	=> $this->_tostring($patient->pa_last),
			'dob' 			=> $this->_tostring($patient->pa_dob),
			'gender' 		=> $this->_tostring($patient->pa_gender),
			'phone' 		=> $this->_tostring($patient->pa_phone),
			'address' 		=> $this->_tostring($patient->pa_address),
			'city' 			=> $this->_tostring($patient->pa_city),
			'state' 		=> $this->_tostring($patient->pa_state),
			'zip' 			=> $this->_tostring($patient->pa_zip),
			'passport_id' 	=> $this->_tostring($patient->pa_passport),
			'passport_front' 	=> $this->_card_url($patient->pa_passport_front),
			'driver_license_id' 	=> $this->_tostring($patient->pa_driver_license_id),
			'driver_license_front' 	=> $this->_card_url($patient->pa_driver_license_front),
			'driver_license_back' 	=> $this->_card_url($patient->pa_driver_license_back),
			'state_id' 		=> $this->_tostring($patient->pa_state_id),
			'state_front' 	=> $this->_card_url($patient->pa_state_front),
			'state_back' 	=> $this->_card_url($patient->pa_state_back),
			'insurance_provicer' => $insurance ? $this->_tostring($insurance->ins_company) : '',
			'insurance_number'   => $insurance ? $this->_tostring($insurance->ins_member_id) : '',
			'insurance_group'    => $insurance ? $this->_tostring($insurance->ins_group_id) : '',
			'insurance_card_front'    => $insurance ? $this->_card_url($insurance->ins_card_front) : '',
			'insurance_card_back'    => $insurance ? $this->_card_url($insurance->ins_card_back) : '',
		);
	}

	protected function _resize_image($path, $max_width, $max_height)
	{
		$image = wp_get_image_editor($path);
		if (! is_wp_error($image)) {
			$image->set_quality(70);
			if ($image->resize($max_width, $max_height, false)) {
				$resized = $image->save($path);

				if ($resized && isset($resized['path'])) {

					if ($resized['path'] != $path) {
						@unlink($path);
						rename($resized['path'], $path);
					}
					return true;
				}
			}
		}
		return false;
	}

	protected function _panel_by_id($panel_id)
	{
		global $wpdb;
		$panel = $wpdb->get_row("select * from ci_test_groups where tg_ID = '" . esc_sql($panel_id) . "'");
		return $panel;
	}


	protected function _cart_data(&$return)
	{
		WC()->cart->calculate_totals();

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			$product = $cart_item['data'];
			if ($product) {
				$return['items'][] = array(
					'cart_id'    => $cart_item_key,
					'product_id' => $product->get_id(),
					'name'       => $product->get_name(),
					'quantity'   => $cart_item['quantity'],
					'price'      => (float) $product->get_price(),
					'subtotal'   => (float) $cart_item['line_subtotal'],
					'total'      => (float) $cart_item['line_total']
				);
			}
		}


		$return['discounts'] = array();
		foreach (WC()->cart->get_applied_coupons() as $dcode) {
			$return['discounts'][] = $dcode;
		}

		$return['subtotal'] = (float) WC()->cart->get_subtotal();
		$return['discount'] = (float) WC()->cart->get_discount_total();
		$return['shipping'] = (float) WC()->cart->get_shipping_total();
		$return['tax']      = (float) WC()->cart->get_taxes_total();
		$return['total']    = (float) WC()->cart->get_total('number');
	}

	protected function add_accession_for_order($id_order, $user_id, $key_accession_product)
	{
		global $wpdb;

		$patient_id = tr_patient_get_id_patient($user_id);
		$patient    = $wpdb->get_row("SELECT * FROM ci_patients WHERE pa_ID='{$patient_id}'");
		$insurance  = $wpdb->get_row("SELECT * FROM ci_insurances WHERE pa_ID='{$patient_id}' AND ins_is_second = 0 ORDER BY ins_ID DESC LIMIT 1");

		$pa = [
			'pa_first'   => $patient->pa_first ?? '',
			'pa_last'    => $patient->pa_last ?? '',
			'pa_middle'  => $patient->pa_middle ?? '',
			'pa_dob'     => $patient->pa_dob ?? '',
			'pa_gender'  => $patient->pa_gender ?? '',
			'pa_phone'   => $patient->pa_phone ?? '',
			'pa_email'   => $patient->pa_email ?? '',
			'pa_address' => $patient->pa_address ?? '',
			'pa_city'    => $patient->pa_city ?? '',
			'pa_state'   => $patient->pa_state ?? '',
			'pa_zip'     => $patient->pa_zip ?? '',
			'pa_mrn'     => $patient->pa_mrn ?? '',
			'pa_ssn'     => $patient->pa_ssn ?? '',
			'pa_patient_id' => $patient->pa_patient_id ?? '',
			'pa_code'    => $patient->pa_code ?? '',
		];

		$ins1 = $insurance ? [
			'ins_first'          => $insurance->ins_first ?? '',
			'ins_last'           => $insurance->ins_last ?? '',
			'ins_relation'       => $insurance->ins_relation ?? '',
			'ins_company'        => $insurance->ins_company ?? '',
			'ins_payer_ID'       => $insurance->ins_payer_ID ?? '',
			'ins_address'        => $insurance->ins_address ?? '',
			'ins_city'           => $insurance->ins_city ?? '',
			'ins_state'          => $insurance->ins_state ?? '',
			'ins_zip'            => $insurance->ins_zip ?? '',
			'ins_phone'          => $insurance->ins_phone ?? '',
			'ins_email'          => $insurance->ins_email ?? '',
			'ins_dob'            => $insurance->ins_dob ?? '',
			'ins_member_id'      => $insurance->ins_member_id ?? '',
			'ins_group_id'       => $insurance->ins_group_id ?? '',
			'ins_authorization'  => $insurance->ins_authorization ?? '',
			'ins_authorization_id' => $insurance->ins_authorization_id ?? '',
			'ins_authorization_date' => $insurance->ins_authorization_date ?? '',
			'ins_mnemonic'       => $insurance->ins_mnemonic ?? '',
			'ins_is_second'      => $insurance->ins_is_second ?? 0,
		] : null;

		$tests = [];
		$keys  = is_array($key_accession_product) ? $key_accession_product : explode('|', $key_accession_product);
		foreach ($keys as $key) {
			if (empty($key)) continue;
			$parts = explode('|', $key);
			if (count($parts) == 2) {
				list($btype, $product_id) = $parts;
				$arr = explode('-', $btype);

				if ($btype == 'pcr_antigen') {
					$tests[] = "pcr|{$product_id}";
					$tests[] = "pcr-Antigen|{$product_id}";
				} elseif (count($arr) == 3) {
					$tests[] = $arr[0] . '-' . $arr[1] . '|' . $product_id;
					$tests[] = $arr[0] . '-' . $arr[2] . '|' . $product_id;
				} else {
					$tests[] = "{$btype}|{$product_id}";
				}
			} else {
				$tests[] = $key;
			}
		}

		$data = [
			'tests'                 => $tests,
			'bat'                   => [],
			'batch_meta'            => [],
			'pa'                    => $pa,
			'pa_muti'               => '',
			'ins1'                  => $ins1 ?? '',
			'ins1_muti'             => '',
			'no_insurance'          => $insurance ? '' : 1,
			'insu_photos'           => '',
			'location'              => '',
			'location_fields'       => '',
			'location_store_number' => '',
			'location_url'          => '',
			'parent'                => '',
			'specimen'              => '',
			'ref_cli_ID'            => '',
			'cli_ID'                => $patient->cli_ID ?? '',
			'doc_ID'                => $patient->doc_ID ?? '',
			'custom_form'           => '',
			'signature'             => '',
		];

		update_post_meta($id_order, '_restration_covid_testing', $data);
		return $data;
	}

	/**
	 * Create New account
	 * - Request: 
	 {
		"os" : "OS type",
		"deviceId" : "device ID",
		"profile": {
			"first_name" : "first name here", 
			"last_name" : "last name here",
			"dob" : "dob here",
			"gender" : "M or F",
			"phone" : "phone here",
			"email" : "email here", 
			"password" : "password here"
		}	
	}
	 * - Response: {status: "ok" or "error",message: "message here"}
	 */
	public function request_create_account()
	{
		global $wpdb;

		$return         = array(
			'status' => 'error',
			'message' => '',
		);

		$os       		= $this->params['os'];
		$deviceID       = $this->params['deviceId'];
		$profile	   	= $this->params['profile'];
		$email          = isset($profile['email']) ? trim($profile['email']) : '';
		$phone          = isset($profile['phone']) ? trim($profile['phone']) : '';
		$password       = isset($profile['password']) ? $profile['password'] : '';
		$first_name     = isset($profile['first_name']) ? trim($profile['first_name']) : '';
		$last_name      = isset($profile['last_name']) ? trim($profile['last_name']) : '';
		$dob            = isset($profile['dob']) ? trim($profile['dob']) : '';
		$gender         = isset($profile['gender']) ? trim($profile['gender']) : '';

		if (empty($email) || !is_email($email)) {
			$return['status']  = 'error';
			$return['message']  = 'Email is empty or invalid';
		} else if (email_exists($email)) {
			$return['status']  = 'error';
			$return['message']  = 'Email is already used by another account. Please use another email.';
		} else if (empty($password)) {
			$return['status']  = 'error';
			$return['message']  = 'Password is empty';
		} else if (empty($phone) || $this->_phone_exists($phone)) {
			$return['status']  = 'error';
			$return['message']  = 'Phone is empty';
		} else if (empty($first_name)) {
			$return['status']  = 'error';
			$return['message']  = 'First name is empty';
		} else if (empty($last_name)) {
			$return['status']  = 'error';
			$return['message']  = 'Last name is empty';
		} else {
			$username = $email;
			$user_id = wp_create_user($username, $password, $email);
			if (is_wp_error($user_id)) {
				$return['status']  = 'error';
				$return['message']  = 'Create account failed. ' . $user_id->get_error_message();
			} else {
				wp_update_user(array(
					'ID' => $user_id,
					'display_name' => $first_name . ' ' . $last_name,
					'nickname' => $first_name . ' ' . $last_name,
				));
				update_user_meta($user_id, 'first_name', $first_name);
				update_user_meta($user_id, 'last_name', $last_name);
				update_user_meta($user_id, 'otp_identifier', $phone);

				$patientdata = array(
					'pa_first'   => $first_name,
					'pa_last'    => $last_name,
					'pa_email' 	 => $email,
					'pa_phone' 	 => $phone,
					'pa_dob' 	 => $dob,
					'pa_created' => date_i18n('Y-m-d H:i:s')
				);

				if (!empty($gender) && in_array($gender, array('M', 'F', 'U'))) {
					$patientdata['pa_gender'] = $gender;
				}
				$wpdb->insert('ci_patients', $patientdata);
				$patient_id = $wpdb->insert_id;
				$wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));

				$return['status']   = 'ok';
				$return['message']  = 'Create account successful. Please login to continue.';
			}
		}

		$this->response(200, $return);
	}

	/**
	 * Login
	 * - Request: {os:"OS type",deviceId:"device ID",email:"email here", password:"password here"}
	 * - Response: {status: "ok" or "error",token:"token here", message: "message here", info:{user info here}}
	 */
	public function request_login()
	{
		global $wpdb;

		$return         = array(
			'status' => 'error',
			'token' => '',
			'message' => 'Login failed',
			'info' => array()
		);
		$email          = $this->params['email'];
		$password       = $this->params['password'];
		$os       		= $this->params['os'];
		$deviceID       = $this->params['deviceId'];
		$ip             = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = trim($ips[0]);
		}
		$ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
		$rate_key = 'ci_app_login_rl_' . md5(strtolower($ip . '|' . $email));
		$rate_data = get_transient($rate_key);
		if (!is_array($rate_data)) {
			$rate_data = array(
				'count' => 0,
				'reset' => time() + 15 * MINUTE_IN_SECONDS,
			);
		}

		if ($rate_data['count'] >= 5) {
			$return['message'] = 'Too many failed login attempts. Please try again later.';
			$this->response(200, $return);
			exit;
		}

		if (!empty($email) && !empty($password)) {
			$username = $email;
			if (is_email($email)) {
				$usernametmp = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM $wpdb->users WHERE user_email = %s", $email));
				if ((!empty($usernametmp))) {
					$username = $usernametmp;
				}
			}
			$user = wp_signon([
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => true,
			], false);

			if (is_wp_error($user)) {
				$rate_data['count']++;
				set_transient($rate_key, $rate_data, max(1, $rate_data['reset'] - time()));
				$return['status']  = 'error';
				$return['message']  = 'Login failed. Please check your email and password again.';
			} else {
				delete_transient($rate_key);
				$return['token']  = $wpdb->get_var("select token from ci_user_tokens 
				where user_id='{$user->ID}' 
				and deviceID='" . esc_sql($deviceID) . "'");
				if (empty($return['token'])) {
					$return['token']     = md5($user->ID . time() . $user->user_email);
					$wpdb->insert('ci_user_tokens', array(
						'user_id'   => $user->ID,
						'os'  		=> $os,
						'deviceID'  => $deviceID,
						'token'     => $return['token'],
						'created'   => date_i18n('Y-m-d H:i:s')
					));
				}

				$return['status']   = 'ok';
				$return['message']  = 'Login successful';
				$return['info']     = array(
					'ID'            => $user->ID,
					'user_email'    => $user->user_email,
					'display_name'  => $user->display_name,
					'user_nicename' => $user->user_nicename,
				);
			}
		} else {
			$return['status']  = 'error';
			$return['message']  = 'Email is empty';
		}

		$this->response(200, $return);
	}
	/**
	 * Logout
	 * - Request: {deviceId:"device ID",token:"token here"}
	 * - Response: {status: "ok" , message: "message here"}
	 */
	public function request_logout()
	{
		global $wpdb;

		$return         = array('status' => 'ok', 'message' => 'Logged out successful');
		$deviceID       = $this->params['deviceId'];
		$token       	= $this->params['token'];

		$wpdb->delete('ci_user_tokens', array(
			'deviceID'  => $deviceID,
			'token'     => $token
		));

		$this->response(200, $return);
	}

	/**
	 * Get Profile
	 * - Request: {deviceId:"device ID",token:"token here"}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here",
			"profile": {
				"patientID":"patient ID here",
				"email":"email here", 
				"first_name":"first name here", 
				"last_name":"last name here",
				"dob":"dob here",
				"gender":"F or M or U",
				"phone":"phone here",
				"address":"address here",
				"city":"city here",
				"state":"state here",
				"zip":"zip here",
				'passport_id' : "passport id here",
				'passport_front' : "passport front url here",
				'driver_license_id' : "driver license id here",
				'driver_license_front' : "driver license front url here",
				'driver_license_back': "driver license back url here",
				'state_id' : "state id here",
				'state_front' : "state front url here",
				'state_back' : "state back url here",
				"insurance_provicer": "insurance provicer here",
				"insurance_number": "insurance number here",
				"insurance_group": "insurance group here",
				"insurance_card_front" : "insurance card front url here",
				"insurance_card_back" : "insurance card back url here"
			}
	 * }
	 */
	public function request_profile()
	{
		global $wpdb;

		$return         = array('status' => 'ok', 'profile' => array());
		$deviceID       = $this->params['deviceId'];
		$token       	= $this->params['token'];

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {
			$patient_id 	= tr_patient_get_id_patient($user_id);

			if (empty($patient_id)) {

				$user = $wpdb->get_row("select u.*,
				uf.meta_value as first_name,
				ul.meta_value as last_name,
				up.meta_value as phone
				from $wpdb->users u
				LEFT JOIN {$wpdb->usermeta} uf ON (uf.user_id=u.ID AND uf.meta_key='first_name')
				LEFT JOIN {$wpdb->usermeta} ul ON (ul.user_id=u.ID AND ul.meta_key='last_name')
				LEFT JOIN {$wpdb->usermeta} up ON (up.user_id=u.ID AND up.meta_key='otp_identifier')
				 where ID='{$user_id}'");

				$wpdb->insert('ci_patients', array(
					'pa_first'   => $user->first_name,
					'pa_last'    => $user->last_name,
					'pa_email' 	 => $user->user_email,
					'pa_phone' 	 => $user->phone,
					'pa_created' => date_i18n('Y-m-d H:i:s')
				));
				$patient_id = $wpdb->insert_id;
				$wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
			}

			$return['profile']     = $this->_get_profile_data($patient_id);
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}


	/**
	 * Update Profile
	 * - Request: 
	 * {
	 "deviceId":"device ID",
	 "token":"token here",
	 "profile": {
				"email":"email here", 
				"first_name":"first name here", 
				"last_name":"last name here",
				"dob":"dob here",
				"gender":"F or M or U",
				"phone":"phone here",
				"address":"address here",
				"city":"city here",
				"state":"state here",
				"zip":"zip here",
				'passport_id' : "passport id here",
				'passport_front' : "passport front base64 here",
				'driver_license_id' : "driver license id here",
				'driver_license_front' : "driver license base64",
				'driver_license_back': "driver license back base64 here",
				'state_id' : "state id here",
				'state_front' : "state front base64 here",
				'state_back' : "state back base64 here",
				"insurance_provicer": "insurance provicer here",
				"insurance_number": "insurance number here",
				"insurance_group": "insurance group here",
				"insurance_card_front" : "insurance card front base64 here",
				"insurance_card_back" : "insurance card back base64 here"
			}
	 * }
	 * - Response: {status: "ok or error or token_invalid" , message: "message here", "profile": {profile here}}
	 */
	public function request_update_profile()
	{
		global $wpdb;

		$return         = array(
			'status' => 'ok',
			'message' => 'Profile updated successful',
			'profile' => array(),
			'error_fields' => array()
		);
		$deviceID       = $this->params['deviceId'];
		$token       	= $this->params['token'];


		$carddir 	 = tr_get_ins_card_dir();

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {
			$patient_id 	= tr_patient_get_id_patient($user_id);


			if ($patient_id > 0) {
				$patientkeys = array(
					'pa_first' 	=> 'first_name',
					'pa_last' 	=> 'last_name',
					'pa_email' 	=> 'email',
					'pa_phone' 	=> 'phone',
					'pa_dob' 	=> 'dob',
					'pa_gender' 	=> 'gender',
					'pa_address' 	=> 'address',
					'pa_city' 	=> 'city',
					'pa_state' 	=> 'state',
					'pa_zip' 	=> 'zip',
					'pa_passport' 	=> 'passport_id',
					'pa_passport_front' 	=> 'passport_front',
					'pa_driver_license_id' 	=> 'driver_license_id',
					'pa_driver_license_front' 	=> 'driver_license_front',
					'pa_driver_license_back' 	=> 'driver_license_back',
					'pa_state_id' 		=> 'state_id',
					'pa_state_front' 	=> 'state_front',
					'pa_state_back' 	=> 'state_back',
				);
				$patientdata = array();
				foreach ($patientkeys as $k => $v) {
					if (isset($this->params['profile'][$v]) && !empty($this->params['profile'][$v])) {

						if (in_array($k, array(
							'pa_passport_front',
							'pa_driver_license_front',
							'pa_driver_license_back',
							'pa_state_front',
							'pa_state_back'
						))) {
							$imgdata = $this->params['profile'][$v];
							if (empty($imgdata) || strpos($imgdata, 'http:') === 0) {
								continue;
							} else if ($imgdata == 'remove') {
								$vl = '';
							} else {
								$imgdata = str_replace('data:image/png;base64,', '', $imgdata);
								$imgdata = str_replace(' ', '+', $imgdata);
								$imgdata = base64_decode($imgdata);
								if (strlen($imgdata) < 1000) {
									continue;
								}

								$filename = $v . '_' . $patient_id   . '.png';
								$filefullpath = $carddir['path']  . '/' . $filename;
								file_put_contents($filefullpath, $imgdata);
								$this->_resize_image($filefullpath, 500, 400);
								$vl =  $filename;
							}
						} else {
							$vl =  $this->_tostring($this->params['profile'][$v]);
							$vl = trim($vl);
						}


						if ($k == 'pa_state') {
							$vl = substr($vl, 0, 2);
						} else if ($k == 'pa_phone' && !empty($vl)) {
							update_user_meta($user_id, 'otp_identifier', $vl);
						} else if ($k == 'pa_first' && !empty($vl)) {
							update_user_meta($user_id, 'first_name', $vl);
						} else if ($k == 'pa_last' && !empty($vl)) {
							update_user_meta($user_id, 'last_name', $vl);
						}

						$patientdata[$k] = $vl;

						$rs = $wpdb->update('ci_patients', array(
							$k => $vl
						), array('pa_ID' => $patient_id));


						if ($rs === false) {
							$return['status'] = 'error';
							$return['message'] = 'Update profile error';
							$return['error_fields'][] = $k;
						}
					}
				}


				//update Insurance
				$insurancekeys = array(
					'ins_company'    => 'insurance_provicer',
					'ins_member_id'  => 'insurance_number',
					'ins_group_id'   => 'insurance_group',
					"ins_card_front"    => 'insurance_card_front',
					"ins_card_back"    => 'insurance_card_back'
				);
				$insurancedata = array();
				foreach ($insurancekeys as $k => $v) {
					if (isset($this->params['profile'][$v]) && !empty($this->params['profile'][$v])) {
						if (in_array($k, array('ins_card_front', 'ins_card_back'))) {
							$imgdata = $this->params['profile'][$v];
							if (empty($imgdata) || stripos($imgdata, 'https:') === 0) {
								continue;
							} else if ($imgdata == 'remove') {
								$vl = '';
							} else {
								$imgdata = str_replace('data:image/png;base64,', '', $imgdata);
								$imgdata = str_replace(' ', '+', $imgdata);
								$imgdata = base64_decode($imgdata);
								if (strlen($imgdata) < 1000) {
									continue;
								}

								$filename = $v . '_' . $patient_id  . '.png';
								$filefullpath = $carddir['path'] . '/' . $filename;
								file_put_contents($filefullpath, $imgdata);
								$this->_resize_image($filefullpath, 500, 400);
								$vl =  $filename;
							}
						} else {
							$vl =  $this->_tostring($this->params['profile'][$v]);
							$vl = trim($vl);
						}

						$insurancedata[$k] = $vl;
					}
				}

				if (!empty($insurancedata)) {

					$insurance = $wpdb->get_row("select * from ci_insurances where pa_ID='{$patient_id}' 
									and ins_is_second = 0 order by ins_ID desc limit 1");

					if ($insurance) {
						if (empty($insurance->ins_first) && !empty($patientdata['pa_first'])) {
							$insurancedata['ins_first'] = $patientdata['pa_first'];
							$insurancedata['ins_last'] = $patientdata['pa_last'];
							$insurancedata['ins_relation'] = 'Self';
							$insurancedata['ins_dob'] = $patientdata['pa_dob'];
						}


						$rs = $wpdb->update('ci_insurances', $insurancedata, array('ins_ID' => $insurance->ins_ID));
						if ($rs === false) {
							$return['status'] = 'error';
							$return['message'] = 'Update profile error';
							$return['error'] =  $wpdb->last_error;
						}
					} else {
						$insurancedata['pa_ID'] = $patient_id;
						$insurancedata['ins_created'] = date_i18n('Y-m-d H:i:s');
						$rs = $wpdb->insert('ci_insurances', $insurancedata);
						if ($rs === false) {
							$return['status'] = 'error';
							$return['message'] = 'Update profile error';
							$return['error'] =  $wpdb->last_error;
						}
					}
				}

				if (!empty($patientdata)) {
					if (!empty($patientdata['pa_first'])) update_user_meta($user_id, 'billing_first_name', $patientdata['pa_first']);
					if (!empty($patientdata['pa_last'])) update_user_meta($user_id, 'billing_last_name', $patientdata['pa_last']);
					if (!empty($patientdata['pa_email'])) update_user_meta($user_id, 'billing_email', $patientdata['pa_email']);
					if (!empty($patientdata['pa_phone'])) update_user_meta($user_id, 'billing_phone', $patientdata['pa_phone']);
					if (!empty($patientdata['pa_address'])) update_user_meta($user_id, 'billing_address_1', $patientdata['pa_address']);
					if (!empty($patientdata['pa_city'])) update_user_meta($user_id, 'billing_city', $patientdata['pa_city']);
					if (!empty($patientdata['pa_state'])) update_user_meta($user_id, 'billing_state', $patientdata['pa_state']);
					if (!empty($patientdata['pa_zip'])) update_user_meta($user_id, 'billing_postcode', $patientdata['pa_zip']);
				}
			}
			$return['profile']     = $this->_get_profile_data($patient_id);
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * Get Reports
	 * - Request: {deviceId:"device ID",token:"token here",limit: 10, page:1}
	 * - Response: 
	 * {
	 		"status":"ok or error or token_invalid",
			"message":"message here",
			"total": total here,
			"page": 1,
			"total_page": 1,
			"limit": 10,
			"reports": [{},...]
	 * }
	 */
	public function request_reports()
	{
		global $wpdb;

		$return         = array(
			'status' 	=> 'ok',
			'message' 	=> '',
			'total' 	=> 0,
			'page' 		=> 1,
			'total_page' => 1,
			'limit' 	=> 10,
			'reports' 	=> array()
		);
		$deviceID       = isset($this->params['deviceId']) ? $this->params['deviceId'] : '';
		$token       	= $this->params['token'];

		$paged 			= isset($this->params['page']) ? (int)$this->params['page'] : 1;
		$limit 			= isset($this->params['limit']) ? (int)$this->params['limit'] : 10;

		if ($paged < 1) $paged = 1;
		if ($limit < 1) $limit = 10;
		$return['page'] = $paged;
		$return['limit'] = $limit;

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {

			$patient_id 	= tr_patient_get_id_patient($user_id);
			$where =	" ba.patient_id = '{$patient_id}' ";
			$where .= " and ba.batch_status !='archived' ";

			$sql_total = array();
			foreach (tr_pdd_get_batch_types() as $btype) {
				$table_batch = tr_pdd_get_table_by_btype($btype);
				$table_report = tr_pdd_get_table_report_by_btype($btype);

				$sql_total[] = "(select count(*) as count from {$table_batch} ba 
	                left join {$table_report} re ON re.patient_id = ba.assen
	                where {$where})";
			}
			$sql_total = implode(' UNION ALL ', $sql_total);
			$sql_total = "select sum(tt.count) from ({$sql_total}) tt";


			$offset 		= ($paged - 1) * $limit;
			$total 			= intval($wpdb->get_var($sql_total));

			$return['total_page'] = ceil($total / $limit);
			if ($return['total_page'] < 1) $return['total_page'] = 1;
			$return['total'] = $total;
			$return['page'] = $paged;



			$sql = array();
			foreach (tr_pdd_get_batch_types() as $btype) {

				$table_batch = tr_pdd_get_table_by_btype($btype);
				$table_report = tr_pdd_get_table_report_by_btype($btype);

				$sql[] = "(select 
                    ba.mid, 
                    ba.patient_name, 
                    ba.batch_id,
                    ba.assen,
                    ba.doc,
                    ba.spec_received,
                    ba.requesting_practice as clinic,
                    ba.requesting_physician as doctor,
                    ba.panel_tests_requested,
                    
					re.estatus,
					re.id,
					re.instrument,
                    re.approve_date,
                    '{$btype}' as btype
                    
                    from {$table_batch} ba
                    left join {$table_report} re On re.patient_id = ba.assen
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    where {$where}
                 )";
			}
			$sql = implode(' UNION ALL ', $sql);


			$sql = "select * from ({$sql}) tt 
			order by tt.doc desc
			limit {$offset},{$limit}
			";


			$rows = $wpdb->get_results($sql);
			foreach ($rows as $row) {

				$panels 	= array();
				$tests_ids  = array();
				$groups_ids = array();

				foreach (explode(',', $row->panel_tests_requested) as $t) {
					if (stripos($t, 'group') === false) {
						$tests_ids[] = (int)$t;
					} else {
						$group_id = str_ireplace('group_', '', $t);
						$groups_ids[] = (int)$group_id;
						$group = $this->_panel_by_id($group_id);
						if ($group) {
							$panels[] = $group->tg_name;
						}
					}
				}

				if (empty($panels) && $row->btype == 'tox') {
					$panels[] = 'Tox';
				}
				$step = 2;
				if ($row->estatus == 'C') {
					$step = 5;
				} else if ($row->id > 0) {
					$step = 4;
				} else if ($row->spec_received >= $row->doc) {
					$step = 3;
				}

				$steparr 		= $this->_step_detail($step);
				$status 		= $steparr['title'];
				$status_detail 	= $steparr['desc'];


				$return['reports'][] = array(
					'id' 			=> $row->btype . '_' . $this->_tostring($row->mid),
					'assen' 		=> $this->_tostring($row->assen),
					'doc' 			=> date('M d, Y', strtotime($row->doc)),
					'spec_received' => date('M d, Y', strtotime($row->spec_received)),
					'approve_date' 	=> date('M d, Y', strtotime($row->approve_date)),
					'clinic' 		=> $this->_tostring($row->clinic),
					'doctor' 		=> $this->_tostring($row->doctor),
					'btype' 		=> $this->_tostring($row->btype),
					'test_requested' => implode(', ', $panels),
					'status' 		=> $status,
					'status_detail' => $status_detail,
				);
			}
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * Get report_detail
	 * - Request: {deviceId:"device ID",token:"token here",id: "blood_1"}
	 * - Response: 
	 * {
	 		"status":"ok or error or token_invalid",
			"message":"message here",
			"detail": {}
	 * }
	 */
	public function request_report_detail()
	{
		global $wpdb;

		$return         = array(
			'status' 	=> 'ok',
			'message' 	=> '',
			'detail' 	=> array()
		);
		$deviceID       = isset($this->params['deviceId']) ? $this->params['deviceId'] : '';
		$token       	= $this->params['token'];
		$id 			= isset($this->params['id']) ? $this->params['id'] : 0;
		list($btype, $mid) = explode('_', $id);

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {

			$patient_id 	= tr_patient_get_id_patient($user_id);


			$lab_url = get_site_url(1);
			if (empty($lab_url) && defined('DOMAIN_CURRENT_SITE')) {
				$lab_url = 'https://' . DOMAIN_CURRENT_SITE;
			}



			$table_batch = tr_pdd_get_table_by_btype($btype);
			$table_report = tr_pdd_get_table_report_by_btype($btype);

			$where = " ba.mid = {$mid} and ba.patient_id = '{$patient_id}' ";

			$sql = "select 
                    ba.mid, 
                    ba.patient_name, 
                    ba.batch_id,
                    ba.assen,
                    ba.doc,
                    ba.spec_received,
                    ba.requesting_practice as clinic,
                    ba.requesting_physician as doctor,
                    ba.panel_tests_requested,
                    ba.notes_report,
                    
					re.estatus,
					re.id,
					re.instrument,
                    re.approve_date,
                    '{$btype}' as btype
                    
                    from {$table_batch} ba
                    left join {$table_report} re On re.patient_id = ba.assen
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    where {$where}
                 ";


			$row = $wpdb->get_row($sql);

			if ($row) {
				$pdf_url 	= '';
				$panels 	= array();
				$tests_ids  = array();
				$groups_ids = array();

				foreach (explode(',', $row->panel_tests_requested) as $t) {
					if (stripos($t, 'group') === false) {
						$tests_ids[] = (int)$t;
					} else {
						$group_id = str_ireplace('group_', '', $t);
						$groups_ids[] = (int)$group_id;
						$group = $this->_panel_by_id($group_id);
						if ($group) {
							$panels[] = $group->tg_name;
						}
					}
				}

				if (empty($panels) && $row->btype == 'tox') {
					$panels[] = 'Tox';
				}
				$step = 2;
				if ($row->estatus == 'C') {
					$step = 5;
				} else if ($row->id > 0) {
					$step = 4;
				} else if ($row->spec_received >= $row->doc) {
					$step = 3;
				}

				$steparr 		= $this->_step_detail($step);
				$status 		= $steparr['title'];
				$status_detail 	= $steparr['desc'];


				$results = array();
				if ($row->estatus == 'C' && function_exists('tr_pdd_get_results_by_testsid')) {
					if (function_exists('tr_pdd_get_results_by_testsid')) {
						$testresults = tr_pdd_get_results_by_testsid($row->id, $row->btype, $tests_ids, $groups_ids, $row->instrument, true);
						foreach ($testresults as $tid => $rarr) {
							if ($row->btype == 'pcr') {
								$rarr['range'] = '';
								$rarr['conc'] = $rarr['flag'];
							}
							$results[] = array(
								'name' 		=> $rarr['te_name'],
								'value' 	=> $rarr['conc'],
								'flag' 		=> $rarr['flag'],
								'comment' 	=> $rarr['comments'],
								'unit' 		=> $rarr['te_units'],
								'range'	 	=> trpdd_show_symbol($rarr['range']),
							);
						}
					}


					$file = $row->btype . '_' . trpdd_patient_encode($row->id) . '_' . $user_id . '.pdf';
					$auth = base64_encode(time() . '_' . $user_id . '_' . $row->id);
					$pdf_url = $lab_url . '?tr_action=ci_results_pdf&auth=' . $auth . '&file=' . $file;
				}

				$return['detail'] = array(
					'id' 			=> $row->btype . '_' . $this->_tostring($row->mid),
					'assen' 		=> $this->_tostring($row->assen),
					'doc' 			=> date('M d, Y', strtotime($row->doc)),
					'spec_received' => date('M d, Y', strtotime($row->spec_received)),
					'approve_date' 	=> date('M d, Y', strtotime($row->approve_date)),
					'clinic' 		=> $this->_tostring($row->clinic),
					'doctor' 		=> $this->_tostring($row->doctor),
					'btype' 		=> $this->_tostring($row->btype),
					'test_requested' => implode(', ', $panels),
					'status' 		=> $status,
					'status_detail' => $status_detail,
					'summary' 		=> '',
					'details' 		=> $row->notes_report,
					'results' 		=> $results,
					'pdf_url' 		=> $pdf_url,
				);
			} else {
				$return['status'] = 'error';
				$return['message'] = 'Report not found';
			}
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}



	/**
	 * Get Shop
	 * - Request: {deviceId:"device ID",token:"token here"}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here",
			"total": total here,
			"items": []
	 * }
	 */
	public function request_shop()
	{
		global $wpdb;

		$return         = array(
			'status' => 'ok',
			'message' => '',
			'total' => 0,
			'items' => array()
		);
		$deviceID       = $this->params['deviceId'];
		$token       	= $this->params['token'];

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {
			//$patient_id 	= tr_patient_get_id_patient($user_id);

			$form_key   = 'trpatient_product_settings';
			$form_options   = get_option($form_key, array());
			$pa_options     = get_option('trpatient_settings', array());

			include(TRCPPR_PATH . '/inc/load_products.php');

			foreach ($products_ids as $pid => $arr) {

				$return['items'][] = [
					'id' 		=> $arr['key'],
					'product_id' => $pid,
					'type' 		=> $arr['type'],
					'name' 		=> $arr['title'],
					'category' => 'Panel',
					'description' => !empty($arr['description']) ? $arr['description'] : $arr['header'],
					'originalPrice' => floatval($arr['originalPrice']),
					'salePrice' => floatval($arr['salePrice']),
					'duration' 	=> $arr['duration'],
					'badge' 	=> $arr['badge']
				];
			}



			$return['total'] = count($return['items']);
			if ($return['total'] == 0) {
				$return['status'] = 'error';
				$return['message'] = 'No products found';
			}
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * Get Product Detail
	 * - Request: {deviceId:"device ID",token:"token here", product_id: "product id here"}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here",
			"detail": {}
	 * }
	 */
	public function request_product_detail()
	{
		global $wpdb;

		$return         = array(
			'status' => 'ok',
			'message' => '',
			'detail' => array()
		);
		$deviceID       = $this->params['deviceId'];
		$token       	= $this->params['token'];
		$product_id 	= isset($this->params['product_id']) ? $this->params['product_id'] : '';

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {
			//$patient_id 	= tr_patient_get_id_patient($user_id);

			$form_key   = 'trpatient_product_settings';
			$form_options   = get_option($form_key, array());
			$pa_options     = get_option('trpatient_settings', array());

			include(TRCPPR_PATH . '/inc/load_products.php');

			foreach ($products_ids as $pid => $arr) {

				if ($pid == $product_id) {
					list($arr['type']) = explode('-', $arr['type']);
					$return['detail'] = [
						'id' 		=> $arr['key'],
						'product_id' => $pid,
						'type' 		=> $arr['type'],
						'name' 		=> $arr['title'],
						'category' => 'Panel',
						'description' => !empty($arr['description']) ? $arr['description'] : $arr['header'],
						'originalPrice' => floatval($arr['originalPrice']),
						'salePrice' => floatval($arr['salePrice']),
						'duration' 	=> $arr['duration'],
						'badge' 	=> $arr['badge']
					];
				}
			}



			if (empty($return['detail'])) {
				$return['status'] = 'error';
				$return['message'] = 'No products found';
			}
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * Get Cart
	 * - Request: {deviceId:"device ID",token:"token here"}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here",
			"items": [],
			"subtotal: 10,
			"discount": 1,
			"tax": 0,
			"total": 9
	 * }
	 */
	public function request_cart()
	{
		global $wpdb;

		$return = array(
			'status'  => 'ok',
			'message' => '',
			'items'   => array()
		);

		$deviceID = isset($this->params['deviceId']) ? sanitize_text_field($this->params['deviceId']) : '';
		$token    = isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';

		$user_id = $this->getuserid_by_token($token);

		if ($user_id > 0) {

			$this->_cart_data($return);
		} else {
			$return['status']  = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * Add to Cart
	 * - Request: {deviceId:"device ID",token:"token here", product_id: "product id", quantity: 1,key_accession_product:"key accession for product"}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here"
	 * }
	 */
	public function request_add_cart()
	{
		global $wpdb;

		$return = array(
			'status'  => 'ok',
			'message' => ''
		);

		$deviceID   = isset($this->params['deviceId']) ? sanitize_text_field($this->params['deviceId']) : '';
		$token      = isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$product_id = isset($this->params['product_id']) ? intval($this->params['product_id']) : 0;
		$quantity   = isset($this->params['quantity']) ? intval($this->params['quantity']) : 1;
		$key_accession_product	= isset($this->params['key_accession_product']) ? sanitize_text_field($this->params['key_accession_product']) : '';
		$user_id = $this->getuserid_by_token($token);

		if ($user_id > 0) {


			$product = wc_get_product($product_id);
			if (!$product) {
				$return['status']  = 'error';
				$return['message'] = 'Product not available.';
				return $this->response(200, $return);
			}

			WC()->cart->calculate_totals();

			//check cart exist
			$has_item_in_cart = false;
			foreach (WC()->cart->get_cart() as  $cart_item) {
				if ($cart_item['data']->get_id() == $product_id) {
					$has_item_in_cart =  true; // Product found
					break;
				}
			}

			if ($has_item_in_cart) {
				$return['status']  = 'error';
				$return['message'] = 'Product exist in cart.';
				return $this->response(200, $return);
			}

			$added = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), array('keyid' => $key_accession_product));

			if ($added) {
				WC()->cart->calculate_totals();
				$return['status']  = 'ok';
				$return['message'] = 'Item added to cart successfully.';
			} else {
				$return['status']  = 'error';
				$return['message'] = 'Failed to add item to cart.';
			}
			$this->_cart_data($return);
		} else {
			$return['status']  = 'token_invalid';
			$return['message'] = 'Token invalid';
		}


		return $this->response(200, $return);
	}



	/**
	 * remove from Cart
	 * - Request: {deviceId:"device ID",token:"token here", cart_id: "cart id"}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here"
	 * }
	 */
	public function request_remove_cart()
	{
		global $wpdb;

		$return = array(
			'status'  => 'ok',
			'message' => ''
		);

		$deviceID = isset($this->params['deviceId']) ? sanitize_text_field($this->params['deviceId']) : '';
		$token    = isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$cart_id  = isset($this->params['cart_id']) ? sanitize_text_field($this->params['cart_id']) : '';

		$user_id = $this->getuserid_by_token($token);

		if ($user_id > 0) {

			// Tính tổng giỏ hàng
			WC()->cart->calculate_totals();


			// Kiểm tra cart_id tồn tại trong giỏ
			if (WC()->cart && WC()->cart->get_cart_item($cart_id)) {
				WC()->cart->remove_cart_item($cart_id);
				WC()->cart->calculate_totals();

				$return['status']  = 'ok';
				$return['message'] = 'Item removed from cart successfully.';
			} else {
				$return['status']  = 'error';
				$return['message'] = 'Cart item not found.';
			}
			$this->_cart_data($return);
		} else {
			$return['status']  = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}


	/**
	 * clear Cart
	 * - Request: {deviceId:"device ID",token:"token here"}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here"
	 * }
	 */
	public function request_clear_cart()
	{
		global $wpdb;

		$return         = array(
			'status' => 'ok',
			'message' => '',
		);
		$deviceID       = $this->params['deviceId'];
		$token       	= $this->params['token'];

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {

			WC()->cart->calculate_totals();
			WC()->cart->empty_cart();
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * Get Notifications
	 * - Request: {deviceId:"device ID",token:"token here", limit: 10, page:1}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here",
			"total": total here,
			"page": 1,
			"total_page": 1,
			"notifications": []
	 * }
	 */
	public function request_notifications()
	{
		global $wpdb;

		$return         = array(
			'status' 	=> 'ok',
			'message' 	=> '',
			'total' 	=> 0,
			'page' 		=> 1,
			'total_page' => 1,
			'notifications' => array()
		);

		$token       	= $this->params['token'];
		$paged 			= isset($this->params['page']) ? (int)$this->params['page'] : 1;
		$limit 			= isset($this->params['limit']) ? (int)$this->params['limit'] : 10;

		if ($paged < 1) $paged = 1;
		if ($limit < 1) $limit = 1;

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {

			$where 			= " user_id = {$user_id} ";
			$sql_total 		= "select count(*) from ci_user_notifications where {$where}";


			$offset 		= ($paged - 1) * $limit;
			$total 			= intval($wpdb->get_var($sql_total));

			$return['total_page'] = ceil($total / $limit);
			if ($return['total_page'] < 1) $return['total_page'] = 1;
			$return['total'] = $total;
			$return['page'] = $paged;

			$return['notifications'] = $wpdb->get_results("select 
				ID as id,
				type,
				target,
				title,
				message,
				viewed,
				created
				from ci_user_notifications 
				where {$where}
				order by ID desc 
				limit {$offset},{$limit}
				", ARRAY_A);
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * set  Notification Viewed
	 * - Request: {deviceId:"device ID",token:"token here", id: 1}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here"
	 * }
	 */
	public function request_set_notification_viewed()
	{
		global $wpdb;

		$return         = array(
			'status' => 'ok',
			'message' => '',
		);

		$token       	= $this->params['token'];
		$id				= $this->params['id'];

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {
			//$patient_id 	= tr_patient_get_id_patient($user_id);
			$wherearr = array('user_id' => $user_id);
			if ($id > 0) {
				$wherearr['ID'] = $id;
			}
			$wpdb->update('ci_user_notifications', array('viewed' => 1), $wherearr);
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * add  Notification
	 * - Request: {deviceId:"device ID",token:"token here", "title": "", "message":""}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here"
	 * }
	 */
	public function request_add_notification()
	{
		global $wpdb;

		$return         = array(
			'status' => 'ok',
			'message' => '',
		);

		$token       	= $this->params['token'];
		$id				= $this->params['id'];
		$title			= $this->params['title'];
		$message		= $this->params['message'];

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {
			//$patient_id 	= tr_patient_get_id_patient($user_id);
			$wherearr = array('user_id' => $user_id);
			if ($id > 0) {
				$wherearr['ID'] = $id;
			}
			$wpdb->insert('ci_user_notifications', array(
				'user_id' => $user_id,
				'title' => $title,
				'message' => $message,
				'created' => date_i18n('Y-m-d H:i:s')
			));
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * remove  Notification
	 * - Request: {deviceId:"device ID",token:"token here", id: 1}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here"
	 * }
	 */
	public function request_remove_notification()
	{
		global $wpdb;

		$return         = array(
			'status' => 'ok',
			'message' => '',
		);

		$token       	= $this->params['token'];
		$id				= $this->params['id'];

		$user_id 		= $this->getuserid_by_token($token);
		if ($user_id > 0) {
			$wpdb->delete('ci_user_notifications', array('user_id' => $user_id, 'ID' => $id));
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}


	/**
	 * Load Checkout
	 * - Request: {deviceId:"device ID",token:"token here"}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here",
			"items": [],
			"subotal":100,
			"shipping":0,
			"tax":0,
			"discount":10,
			"total": 90,
			"payment_methods": [{id,name,image,fields}]
	 * }
	 */
	public function request_load_checkout()
	{
		global $wpdb;

		$return = array(
			'status'  => 'ok',
			'message' => '',
			'items'   => array(),
			'subtotal' => 0,
			'shipping' => 0,
			'tax'     => 0,
			'discount' => 0,
			'total'   => 0,
			'payment_methods' => array()
		);


		$token    = isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$user_id  = $this->getuserid_by_token($token);
		$is_stripe = false;
		if ($user_id > 0) {

			WC()->cart->calculate_totals();
			$this->_cart_data($return);

			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

			$payment_methods = array();

			if (!empty($available_gateways)) {
				foreach ($available_gateways as $key => $value) {
					if ($key == "stripe") $is_stripe = true;
					$enabled_app = get_option('ci_patients_' . $key . '_app', 'yes') === 'yes';
					if ($enabled_app) {
						$m = [];
						$m["id"]    = $key;
						$m["name"]  = ucfirst($key);
						$m["image"] = !empty($value->icon) ? $value->icon : '';
						$payment_methods[] = $m;
					}
				}
			}
			$return['payment_methods'] = $payment_methods;
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		if ($is_stripe) {
			$amount = intval(round(intval($return["total"]) * 100));
			$params = [
				'amount'   => $amount,
				'currency' => 'usd',
			];

			$response = WC_Stripe_API::request($params, 'payment_intents');
			$return['clientSecret'] = $response->client_secret;
		} else {
			$return['clientSecret'] = "";
		}
		$this->response(200, $return);
	}

	/**
	 * Pay Now
	 * - Request: {deviceId:"device ID",token:"token here",payment_method:"card"}
	 * - Response:
	 * {
	 *   "status":"ok or error or token_invalid",
	 *   "message":"message here",
	 *   "order_id": 123,
	 *   "redirect_url": "",
	 *   "total": 90
	 * }
	 */
	public function request_pay_now()
	{
		global $wpdb;

		$return = array(
			'status'  => 'ok',
			'message' => '',
			'order_id' => 0,
			'redirect_url' => '',
			'total'   => 0,
		);

		$token          	= isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$payment_method 	= isset($this->params['payment_method']) ? sanitize_text_field($this->params['payment_method']) : '';

		$user_id = $this->getuserid_by_token($token);

		if ($user_id > 0) {

			WC()->cart->calculate_totals();
			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

			if (WC()->cart->is_empty()) {
				$return['status']  = 'error';
				$return['message'] = 'Cart is empty';
			} else if (!isset($available_gateways[$payment_method])) {
				$return = [
					'status' => 'error',
					'message' => "Payment method $payment_method not available"
				];
			} else {
				$key_accession_product = array();
				$order = wc_create_order(array('customer_id' => $user_id));
				foreach (WC()->cart->get_cart() as $cart_item) {
					$order->add_product(
						$cart_item['data'],
						$cart_item['quantity'],
						array('variation' => $cart_item['variation'], 'totals' => $cart_item['line_total'])
					);
					$key_accession_product[] = $cart_item['keyid'];
				}


				$order->set_address(WC()->customer->get_billing(), 'billing');
				$order->set_payment_method($payment_method);
				$order->set_total(WC()->cart->total);
				$order->calculate_totals();
				$order->save();
				$this->add_accession_for_order($order->get_id(), $user_id, $key_accession_product);


				$gateway = $available_gateways[$payment_method];
				try {
					$order->calculate_totals();
					$order->save();

					$result_pay = $gateway->process_payment($order->get_id());
					//error_log("Process_payment result: " . print_r($result_pay, true));

					if (!empty($result_pay['result']) && $result_pay['result'] === 'success') {

						$rsi = trcpa_add_covid_order($order->get_id());

						$return = [
							'status'       => 'ok',
							'message'      => 'Payment Success!',
							'order_id'     => $order->get_id(),
							'total'        => $order->get_total(),
							'rsi'        => $rsi,
							'redirect_url' => isset($result_pay['redirect']) ? $result_pay['redirect'] : '',
						];
						WC()->cart->empty_cart();
					} else {
						$return = [
							'status'  => 'error',
							'message' => 'Payment failed',
							'data'    => $result_pay
						];
					}
				} catch (Exception $e) {
					$return = [
						'status'  => 'error',
						'message' => $e->getMessage()
					];
				}
			}
		} else {
			$return['status']  = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}


	/**
	 * Apply Discount Code
	 * - Request: 
	 {
		deviceId:"device ID",
		token:"token here", 
		discountCode: "code here"
	}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here"
			"items": [],
			"subotal":100,
			"shipping":0,
			"tax":0,
			"discount":10,
			"total": 90,
	 * }
	 */
	public function request_apply_discount()
	{
		global $wpdb;

		$return = array(
			'status'    => 'ok',
			'message'   => '',
			'items'     => array(),
			'subtotal'  => 0,
			'shipping'  => 0,
			'tax'       => 0,
			'discount'  => 0,
			'total'     => 0,
		);

		$deviceID     = isset($this->params['deviceId']) ? sanitize_text_field($this->params['deviceId']) : '';
		$token        = isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$discountCode = isset($this->params['discountCode']) ? sanitize_text_field($this->params['discountCode']) : '';
		$user_id      = $this->getuserid_by_token($token);

		if ($user_id > 0) {

			WC()->cart->calculate_totals();
			//coupon
			$code      = wc_format_coupon_code(wp_unslash($discountCode));
			$code      = trim($code);


			if (!wc_coupons_enabled()) {
				$return['status']  = 'error';
				$return['message'] = 'Coupon disabled.';
			} else if (WC()->cart->has_discount($code)) {
				$return['status']  = 'ok';
				$return['message'] = 'Coupon code "' . $code . '" already applied!';
			} else if (WC()->cart->apply_coupon($code)) {
				$return['status']  = 'ok';
				$return['message'] = 'Coupon applied successfully.';
			} else {
				$return['status']  = 'error';
				$return['message'] = 'Invalid or expired coupon code.';
			}
			$this->_cart_data($return);
		} else {
			$return['status']  = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * Apply Discount Code
	 * - Request: 
	 {
		deviceId:"device ID",
		token:"token here", 
		discountCode: "code here"
	}
	 * - Response: 
	 * {
			"status":"ok or error or token_invalid",
			"message":"message here"
			"items": [],
			"subotal":100,
			"shipping":0,
			"tax":0,
			"discount":10,
			"total": 90,
	 * }
	 */
	public function request_remove_discount()
	{
		global $wpdb;

		$return = array(
			'status'    => 'ok',
			'message'   => '',
			'items'     => array(),
			'subtotal'  => 0,
			'shipping'  => 0,
			'tax'       => 0,
			'discount'  => 0,
			'total'     => 0,
		);

		$deviceID     = isset($this->params['deviceId']) ? sanitize_text_field($this->params['deviceId']) : '';
		$token        = isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$discountCode = isset($this->params['discountCode']) ? sanitize_text_field($this->params['discountCode']) : '';
		$user_id      = $this->getuserid_by_token($token);

		if ($user_id > 0) {

			WC()->cart->calculate_totals();
			//coupon
			$code      = wc_format_coupon_code(wp_unslash($discountCode));
			$code      = trim($code);


			if (!wc_coupons_enabled()) {
				$return['status']  = 'error';
				$return['message'] = 'Coupon disabled.';
			} else if (!WC()->cart->has_discount($code)) {
				$return['status']  = 'notexist';
				$return['message'] = 'Coupon not exist.';
			} else if (WC()->cart->remove_coupon($code)) {
				$return['status']  = 'ok';
				$return['message'] = 'Coupon removed successfully.';
			} else {
				$return['status']  = 'error';
				$return['message'] = 'Invalid or expired coupon code.';
			}
			$this->_cart_data($return);
		} else {
			$return['status']  = 'token_invalid';
			$return['message'] = 'Token invalid';
		}

		$this->response(200, $return);
	}

	/**
	 * Get Orders
	 * - Request: {deviceId:"device ID",token:"token here",limit:"item per of page",page:"number page show"}
	 * - Response:
	 * [
	 *   {
	 *     "date": "2025-08-30 03:34:34",
	 *     "id": "order_68bfa04aa0ee9",
	 *     "items": [...],
	 *     "msg": "Order generated for demo",
	 *     "status": "Processing",
	 *     "total_item": 2,
	 *     "total_price": 447
	 *   }
	 * ]
	 */
	public function request_get_orders()
	{
		global $wpdb;

		$deviceID = isset($this->params['deviceId']) ? sanitize_text_field($this->params['deviceId']) : '';
		$token    = isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$limit    = isset($this->params['limit']) ? sanitize_text_field($this->params['limit']) : 10;
		$page     = isset($this->params['page']) ? sanitize_text_field($this->params['page']) : 1;
		$user_id  = $this->getuserid_by_token($token);

		$return = array();

		if ($user_id > 0) {
			$args = array(
				'type'        => 'shop_order',
				'limit'       => $limit,
				'paged'       => $page,
				'customer_id' => $user_id,
				'orderby'     => 'date',
				'order'       => 'DESC',
			);
			$orders = wc_get_orders($args);

			foreach ($orders as $order) {
				/** @var WC_Order $order */
				$items_data = array();
				foreach ($order->get_items() as $item_id => $item) {
					$product   = $item->get_product();
					$items_data[] = array(
						'product_id'   => $product ? $product->get_id() : 0,
						'name'         => $item->get_name(),
						'quantity'     => $item->get_quantity(),
						'subtotal'     => $item->get_subtotal(),
						'total'        => $item->get_total(),
						'image'        => $product ? wp_get_attachment_url($product->get_image_id()) : '',
					);
				}

				$last_item = end($items_data);
				$last_name = isset($last_item['name']) ? $last_item['name'] : '';

				$orders_result[] = array(
					'date'        => $order->get_date_created()->date('Y-m-d H:i:s'),
					'id'          => 'order_' . $order->get_id(),
					'items'       => $items_data,
					'msg'         => $order->get_customer_note() ?: 'Order generated for demo',
					'status'      => wc_get_order_status_name($order->get_status()),
					'total_item'  => $order->get_item_count(),
					'total_price' => (float) $order->get_total(),
					'name'		  => $last_name,
				);
			}
			$return["orders"] = $orders_result;
			$return["status"] = "ok";
		} else {
			$return = array(
				'status'  => 'token_invalid',
				'message' => 'Token invalid'
			);
		}

		$this->response(200, $return);
	}

	/**
	 * Create Order from Cart
	 * - Request: {deviceId:"device ID", token:"token here",key_accession_product="Product id Array type"}
	 * - Response:
	 * {
	 *   "status": "ok",
	 *   "order": {
	 *     "id": "order_1234",
	 *     "date": "2025-09-10 10:30:00",
	 *     "status": "Completed",
	 *     "total_item": 2,
	 *     "total_price": 299,
	 *     "items": [
	 *       {
	 *         "product_id": 123,
	 *         "name": "Name Product",
	 *         "quantity": 1,
	 *         "subtotal": 199,
	 *         "total": 199,
	 *         "image": "https://..."
	 *       },
	 *       ...
	 *     ]
	 *   }
	 * }
	 */
	public function request_create_order()
	{
		global $wpdb;

		$token    		= isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$user_id 		= $this->getuserid_by_token($token);

		if ($user_id > 0) {

			$cart = WC()->cart;
			$order = wc_create_order(array(
				'customer_id' => $user_id,
			));
			$key_accession_product = array();
			foreach ($cart->get_cart() as $cart_item) {
				$product  = $cart_item['data'];
				$quantity = $cart_item['quantity'];
				$key_accession_product[] = $cart_item['keyid'];
				$order->add_product($product, $quantity);
			}

			$order->calculate_totals();
			$customer = new WC_Customer($user_id);

			$order->set_address($customer->get_billing(), 'billing');
			$order->save();

			$this->add_accession_for_order($order->get_id(), $user_id, $key_accession_product);


			$order->update_status('processing', 'Payment successful via Stripe API');

			$items_data = array();
			foreach ($order->get_items() as $item_id => $item) {
				$product   = $item->get_product();
				$items_data[] = array(
					'product_id' => $product ? $product->get_id() : 0,
					'name'       => $item->get_name(),
					'quantity'   => $item->get_quantity(),
					'subtotal'   => $item->get_subtotal(),
					'total'      => $item->get_total(),
					'image'      => $product ? wp_get_attachment_url($product->get_image_id()) : '',
				);
			}

			$last_item = end($items_data);
			$last_name = isset($last_item['name']) ? $last_item['name'] : '';

			$return = array(
				'status' => 'ok',
				'order'  => array(
					'id'          => 'order_' . $order->get_id(),
					'date'        => $order->get_date_created()->date('Y-m-d H:i:s'),
					'status'      => wc_get_order_status_name($order->get_status()),
					'total_item'  => $order->get_item_count(),
					'total_price' => (float) $order->get_total(),
					'items'       => $items_data,
					'name'        => $last_name,
				)
			);
		} else {
			$return = array(
				'status'  => 'token_invalid',
				'message' => 'Token invalid'
			);
		}

		$this->response(200, $return);
	}

	/**
	 * Active Kit
	 * - Request: {
	 *     	token:"token here",
	 *     	kit_code:"Code kit",
	 *     	patient_id:"Patient Code",
	 *     	first_name:"Patient Name",
	 * 		last_name:"",
	 *     	phone:"Phone Number",
	 *     	email:"Email",
	 *     	address:"Address",
	 * 		address_2:"Address 2",
	 * 		city:"",
	 * 		state:"",
	 * 		zip:"",
	 *     	dob:"Date Of Birth",
	 *     	driver_licence_image:"File Image"
	 *   }
	 * - Response:
	 * {
	 *   "status": "ok",
	 *   "message": "Kit activated successfully",
	 *   "entry_id": 123
	 * }
	 */
	public function request_active_kit()
	{
		global $wpdb;

		$token     = isset($this->params['token']) ? sanitize_text_field($this->params['token']) : '';
		$kit_code  = isset($this->params['kit_code']) ? sanitize_text_field($this->params['kit_code']) : '';

		// meta fields
		$first_name = isset($this->params['first_name']) ? sanitize_text_field($this->params['first_name']) : '';
		$last_name  = isset($this->params['last_name']) ? sanitize_text_field($this->params['last_name']) : '';
		$phone      = isset($this->params['phone']) ? sanitize_text_field($this->params['phone']) : '';
		$email      = isset($this->params['email']) ? sanitize_email($this->params['email']) : '';
		$address_1  = isset($this->params['address']) ? sanitize_text_field($this->params['address']) : '';
		$address_2  = isset($this->params['address_2']) ? sanitize_text_field($this->params['address_2']) : '';
		$city       = isset($this->params['city']) ? sanitize_text_field($this->params['city']) : '';
		$state      = isset($this->params['state']) ? sanitize_text_field($this->params['state']) : '';
		$zip        = isset($this->params['zip']) ? sanitize_text_field($this->params['zip']) : '';
		$dob        = isset($this->params['dob']) ? sanitize_text_field($this->params['dob']) : '';
		$driver_licence_image = isset($this->params['driver_licence_image']) ? esc_url_raw($this->params['driver_licence_image']) : '';


		$pa_options = get_option('trpatient_settings', array());

		$form_id = isset($pa_options['fluent_form_id']) && $pa_options['fluent_form_id'] > 0 ? intval($pa_options['fluent_form_id']) : 4;
		$user_id = $this->getuserid_by_token($token);
		$return  = array();

		if ($user_id > 0 && $form_id > 0) {

			if (empty($first_name)) {
				$return['status']  = 'error';
				$return['message']  = 'First name is empty';
			} else if (empty($last_name)) {
				$return['status']  = 'error';
				$return['message']  = 'Last name is empty';
			} else if (empty($phone)) {
				$return['status']  = 'error';
				$return['message']  = 'Phone is empty';
			} else  if (!empty($email) && !is_email($email)) {
				$return['status']  = 'error';
				$return['message']  = 'Invalid email format.';
			} else if (empty($address_1)) {
				$return['status']  = 'error';
				$return['message']  = 'Address cannot be blank';
			} else if (empty($city)) {
				$return['status']  = 'error';
				$return['message']  = 'City is empty';
			} else if (empty($zip)) {
				$return['status']  = 'error';
				$return['message']  = 'Zip is empty';
			} else if (empty($dob)) {
				$return['status']  = 'error';
				$return['message']  = 'Date of birth not set!';
			} else {
				$driver_licence_url = '';

				if (strpos($driver_licence_image, 'http') === 0) {
					$driver_licence_url = $driver_licence_image;
				} else {
					$driver_licence_image = str_replace('data:image/png;base64,', '', $driver_licence_image);
					$driver_licence_image = str_replace(' ', '+', $driver_licence_image);
					$driver_licence_image = base64_decode($driver_licence_image);

					if ($driver_licence_image !== false && strlen($driver_licence_image) >= 1000) {

						$carddir    = tr_get_ins_card_dir();
						$patient_id = tr_patient_get_id_patient($user_id);

						$filename     = 'pa_driver_license_front_' . $patient_id . '.png';
						$filefullpath = $carddir['path']  . '/' . $filename;

						file_put_contents($filefullpath, $driver_licence_image);
						$this->_resize_image($filefullpath, 500, 400);

						$driver_licence_url = $carddir['url']  . '/' . $filename;
					}
				}
				$return['img_data'] = $driver_licence_url;


				$original = array(
					'__fluent_form_embded_post_id' => "266", // ID page embed form
					'_fluentform_4_fluentformnonce' => '9e5e335e45',
					'_wp_http_referer' => '/kit-verification/',

					'names' => array(
						'first_name' => $first_name,
						'last_name'  => $last_name
					),
					'phone'   => $phone,
					'email'   => $email,
					'address_1' => array(
						'address_line_1' => $address_1,
						'address_line_2' => $address_2,
						'city'           => $city,
						'state'          => $state,
						'zip'            => $zip
					),
					'datetime' => $dob,
					'terms-n-condition' => 'on',
					'hidden' => 'Kit Verification',
					'image-upload' => $driver_licence_url
				);

				try {
					$source_url = $kit_code;
					$table = $wpdb->prefix . "fluentform_submissions";
					$wpdb->insert(
						$table,
						array(
							'form_id'    => $form_id,
							'serial_number' => 345,
							'response'   => wp_json_encode($original),
							'source_url' => $source_url,
							'user_id'    => $user_id,
							'status'     => 'unread',
							'is_favourite' => 0,
							'browser'    => 'app',
							'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
							'created_at' => date_i18n('Y-m-d H:i:s'),
							'updated_at' => date_i18n('Y-m-d H:i:s'),
						)
					);
					$entry_id = $wpdb->insert_id;
					$return['status'] = 'ok';
					$return['message'] = 'Kit activated successfully';
					$return['entry_id'] = $entry_id;
				} catch (Exception $e) {
					$return['status'] = 'error';
					$return['message'] = $e->getMessage();
					throw $e;
				}
			}
		} else {
			$return['status'] = 'token_invalid';
			$return['message'] = 'Token invalid or form_id missing';
		}

		$this->response(200, $return);
	}
}
