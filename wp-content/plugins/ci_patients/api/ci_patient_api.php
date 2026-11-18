<?php
require_once  'restful_api.php';

add_filter('https_ssl_verify', '__return_false');


class CI_Patient_Api extends restful_api
{

	function __construct($pre_endpoint)
	{
		//$this->hide_info = true;
		parent::__construct($pre_endpoint);
	}

	protected function formatdate($date)
	{
		if (strpos($date, '/') > 0) {
			$date = date('Y-m-d', strtotime($date));
		}
		return $date;
	}

	protected function check_key()
	{
		$options = get_option('trpatient_settings', array());
		$key = $options['sv_api_key'];
		if (empty($key) || empty($this->request_api_key) || $key != $this->request_api_key) {
			$this->response(405, array('status' => 'error', 'message' => 'wrong key'));
			exit;
		}
	}

	/**
	 * check Phone number exist
	 * - Requests:
	 *          + phone: phone number
	 * - return: json
	 *
	 * ex: /patientapi/phone_exist?key=abc&phone=02029292292
	 */
	public function request_phone_exist()
	{
		global $wpdb;

		$return         = array('status' => 'not_found');
		$phone          = $this->params['phone'];
		if (!empty($phone)) {
			//$return['phone']    = $phone;
			$phones             = tr_patient_get_phone_arr($phone);
			$exist 				= false;
			if (!empty($phones)) {
				$phones             = implode("','", $phones);
				$exist              = $wpdb->get_var("select count(*) from ci_patients 
										where pa_phone in ('{$phones}')");
			}

			if ($exist) {
				$return['status']   = 'found';
			}
		} else {
			$return['status']  = 'error';
			$return['message']  = 'Phone is empty';
		}

		$this->response(200, $return);
	}

	/**
	 * Verify the DOB of the caller
	 * - Requests:
	 *          + phone: phone number
	 * - return: json with dob
	 */
	public function request_verify_dob()
	{
		global $wpdb;

		$return     = array('status' => 'error', 'dob' => '');

		$phone      = $this->params['phone'];

		if (!empty($phone)) {
			$return['phone']    = $phone;
			$phones             = tr_patient_get_phone_arr($phone);
			$dob				= '';
			if (!empty($phones)) {
				$phones             = implode("','", $phones);
				$dob                = $wpdb->get_var("select pa_dob from ci_patients 
										where pa_phone in ('{$phones}') ");
			}


			$return['status']   = !empty($dob) ? 'found' : 'not_found';
			$return['dob']   = $dob;
		} else {
			$return['message'] = 'Please check fields';
		}

		$this->response(200, $return);
	}

	/**
	 * verify other family member records associated with the phone number
	 * - Requests:
	 *          + phone: phone number
	 * - return: json members
	 */
	public function request_verify_family()
	{
		global $wpdb;

		$return     = array('status' => '', 'members' => array());

		$phone      = $this->params['phone'];

		if (!empty($phone)) {
			$phones  = tr_patient_get_phone_arr($phone);
			$user_id = 0;
			if (!empty($phones)) {
				$phones_str             = implode("','", $phones);
				$user_id = $wpdb->get_var("select u.ID
										from {$wpdb->users} u
										JOIN ci_patients p ON p.pa_ID = u.patient_id
										where p.pa_phone in ('{$phones_str}') ");
			}

			if ($user_id > 0) {
				$kids = get_user_meta($user_id, '_patient_kids', true);
				foreach ($kids as $kid) {
					$return['members'][] = array(
						'first_name' => $kid['first_name'],
						'last_name' => $kid['last_name'],
						'dob' => $kid['birthday'],
					);
				}
				$return['status'] = 'found';
			} else {
				$return['status'] = 'not_found';
			}
		} else {
			$return['status'] = 'error';
			$return['message'] = 'Please check fields';
		}

		$this->response(200, $return);
	}

	/**
	 * Add Member family
	 * - Requests:
	 *          + phone: phone number
	 *          + dob: Member DOB
	 *          + first_name: First Name
	 *          + last_name: Last Name
	 *          + code: Patient Code or Accession Code
	 * - return: json
	 */
	public function request_add_member_family()
	{
		global $wpdb;

		if (! function_exists('get_user_by')) {
			require_once ABSPATH . WPINC . '/pluggable.php';
		}

		$return     = array('status' => 'fail', 'message' => 'Fail');

		$phone      = trim($this->params['phone']);

		$kid = array(
			'first_name' => $this->params['first_name'],
			'last_name' => $this->params['last_name'],
			'birthday' => $this->formatdate($this->params['dob']),
			'patient_code' => $this->params['code'],
		);

		if (!empty($phone) && !empty($kid['birthday']) && !empty($kid['first_name']) && !empty($kid['last_name']) && !empty($kid['patient_code'])) {
			$phones = tr_patient_get_phone_arr($phone);
			$user = null;
			if (!empty($phones)) {
				$phones_str = implode("','", $phones);

				$user = $wpdb->get_row("select u.ID,
												p.pa_ID as patient_id,
												p.pa_first,
												p.pa_last,
												p.pa_email
										from ci_patients p 
										left join {$wpdb->users} u ON (p.pa_ID = u.patient_id)
										where p.pa_phone in ('{$phones_str}') 
										");
			}


			if ($user) {
				if (empty($user->ID)) {
					if (empty($user->pa_email)) {
						$user->pa_email = $phone;
					}
					if (username_exists($user->pa_email)) {
						$user->pa_email = $phone . '@' . time();
					}
					$user->ID = wp_insert_user(array(
						'user_login' => $user->pa_email,
						'user_email' => $user->pa_email . '@' . time() . '.com',
						'first_name' => $user->pa_first,
						'last_name' => $user->pa_last,
						'role' => 'patient',
					));
					if ($user->ID > 0) {
						update_user_meta($user->ID, 'phone', $phone);
						$wpdb->update($wpdb->users, array('patient_id' => $user->patient_id), array('ID' => $user->ID));
					}
				}

				$patient_id = trpa_patient_id_from_code($kid['patient_code'], $kid['first_name'], $kid['last_name'], $kid['birthday']);
				if (empty($patient_id)) {
					$patient_id = trpa_patient_id_from_code($kid['patient_code'], $kid['last_name'], $kid['first_name'], $kid['birthday']);
					if ($patient_id > 0) {
						$lastname           = $kid['last_name'];
						$kid['last_name']   = $kid['first_name'];
						$kid['first_name']  = $lastname;
					}
				}

				if ($patient_id > 0) {
					$kids = get_user_meta($user->ID, '_patient_kids', true);
					if (!is_array($kids)) $kids = array();
					$kids[$patient_id] = $kid;
					update_user_meta($user->ID, '_patient_kids', $kids);

					$return['status'] = 'success';
					$return['message'] = 'Added Successfully!';
				} else {
					$return['message'] = 'Patient Not Found';
				}
			} else {
				$return['message'] = 'Phone not Found';
			}
		} else {
			$return['message'] = 'Please correct fields: phone, first_name, last_name, dob, code';
		}

		$this->response(200, $return);
	}

	/**
	 * Verify DOB of Family
	 * - Requests:
	 *          + phone: phone number
	 *          + first_name: First Name
	 *          + last_name: Last Name
	 * - return: json
	 */
	public function request_verify_dob_family()
	{
		global $wpdb;

		$return     = array('status' => 'not_found', 'dob' => '');

		$phone      = trim($this->params['phone']);
		$first_name = trim($this->params['first_name']);
		$last_name  = trim($this->params['last_name']);

		if (!empty($phone) && !empty($first_name) && !empty($last_name)) {
			$phones             = tr_patient_get_phone_arr($phone);
			$user = null;
			if (!empty($phones)) {
				$phones_str             = implode("','", $phones);

				$sql = "select u.ID, 
								p.pa_ID,
								if((p.pa_first='{$first_name}' and p.pa_last='{$last_name}') 
										or (p.pa_first='{$last_name}' and p.pa_last='{$first_name}'), p.pa_dob, '') as dob
								from ci_patients p 
								left join {$wpdb->users} u ON p.pa_ID = u.patient_id
								where p.pa_phone in ('{$phones}') ";

				$user = $wpdb->get_row($sql);
			}


			if ($user && !empty($user->dob)) {
				$return['status'] = 'found';
				$return['dob'] = $user->dob;
			} else if ($user && $user->ID > 0) {
				$kids = get_user_meta($user->ID, '_patient_kids', true);
				$kidsids = implode(',', array_keys($kids));

				$return['dob'] = $wpdb->get_var("select pa_dob from ci_patients
												where pa_ID in ({$kidsids})
												and (
													(pa_first = '{$first_name}' and pa_last = '{$last_name}')
												or (pa_first = '{$last_name}' and pa_last = '{$first_name}')
												 )
												");
				if (!empty($return['dob'])) {
					$return['status'] = 'found';
				}
			} else {
				$return['status'] = 'error';
				$return['message'] = 'Members not found';
			}
		} else {
			$return['status'] = 'error';
			$return['message'] = 'Please check fields';
		}

		$this->response(200, $return);
	}



	/**
	 * Trigger OTP to the phone number
	 * - Requests:
	 *          + phone: phone number
	 * - return: json
	 */
	public function request_trigger_otp()
	{
		global $wpdb, $tr_sms_gateway;

		$return     = array('status' => 'error');

		$phone      = $this->params['phone'];

		if (!is_object($tr_sms_gateway)) {
			$return['message'] = 'Sms Gateway error';
		} else if (!empty($phone)) {
			$phones             = tr_patient_get_phone_arr($phone);
			$exist = false;
			if (!empty($phones)) {
				$phones             = implode("','", $phones);

				$exist              = $wpdb->get_var("select count(*) from ci_patients 
										where pa_phone in ('{$phones}')");
			}


			if ($exist) {
				$otp_code = otpa_generate_otp_code(4, '0123456789');

				$loaded = $tr_sms_gateway->load_library();

				if ($loaded) {
					$init = $tr_sms_gateway->init_api();
				}
				$rs = $tr_sms_gateway->send_request($phone, $otp_code, 'api');
				if ($rs['status']) {
					$return['status'] = 'sent';
					$return['code'] = $otp_code;
					$return['message'] = 'Sent code to phone';
				} else {
					$return['message'] = 'Sms Gateway error.';
				}
			} else {
				$return['message'] = 'Phone not exist';
			}
		} else {
			$return['message'] = 'Phone is empty';
		}

		$this->response(200, $return);
	}



	/**
	 * Verify OTP for the caller
	 * - Requests:
	 *          + phone: phone number
	 *          + code: Code to verify
	 * - return: json
	 */
	public function request_verify_otp()
	{
		global $wpdb;

		$return     = array('status' => 'error');

		$phone      = trim($this->params['phone']);
		$code       = trim($this->params['code']);

		if (!empty($phone) || empty($code)) {
			$phones             = tr_patient_get_phone_arr($phone);
			$exist = false;
			if (!empty($phones)) {
				$phones             = implode("','", $phones);

				$exist              = $wpdb->get_var(
					"select count(*) 
										from ci_patients 
										where pa_phone in ('{$phones}')"
				);
			}


			if ($exist) {
				$limitdt = date('Y-m-d H:i:s', strtotime(date_i18n('Y-m-d H:i:s')) - 600);
				$correct = $wpdb->get_var(
					"select count(*) 
									from tr_log_sms 
									where sendtype='api' 
									and phone in ('{$phones}')
									and assen = '{$code}'
									and created > '{$limitdt}'
									order by created desc"
				);
				if ($correct) {
					$return['status'] = 'correct';
					$return['message'] = 'Code is correct';
				} else {
					$return['status'] = 'wrong';
					$return['message'] = 'Code is wrong';
				}
			} else {
				$return['message'] = 'Phone not exist';
			}
		} else {
			$return['message'] = 'Phone or Code empty';
		}

		$this->response(200, $return);
	}



	/**
	 * Retrieve patient PCR results [Positive or Negative]
	 * - Requests:
	 *          + phone: phone number
	 *          + accession: Accession number
	 * - return: json
	 */
	public function request_retrieve_pcr_result()
	{
		global $wpdb;

		$return     = array('status' => 'error');

		$phone      = $this->params['phone'];
		$assen      = $this->params['accession'];
		$btype      = 'pcr';

		if (!empty($phone) && !empty($assen)) {
			$phones             = tr_patient_get_phone_arr($phone);
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);
			$report = false;
			if (!empty($phones)) {
				$phones             = implode("','", $phones);



				$report = $wpdb->get_row("select pa.pa_ID,
													pa.pa_first,
													ba.patient_name,
													re.id,
													re.estatus,
													re.has_pos,
													re.covid_pos
										From {$batch_tbl} ba 
										JOIN {$report_tbl} re On re.patient_id = ba.assen
										JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
										where pa.pa_phone in ('{$phones}') 
										and ba.assen = '{$assen}' ", ARRAY_A);
			}




			if ($report) {
				$return['accession'] = $assen;

				if ($report['estatus'] == 'C') {
					$has_pos = $report['has_pos'] || $report['covid_pos'] === '1';

					$return['status'] = 'resulted';
					$return['result'] = $has_pos ? 'Positive' : 'Negative';
				} else {
					$return['status'] = 'pending';
					$return['message'] = 'Result is pending';
				}
			} else {
				$return['message'] = 'Phone or Accession Number not exist';
			}
		} else {
			$return['message'] = 'Phone or Accession is empty';
		}

		$this->response(200, $return);
	}



	/**
	 * Check if results are ready for Patient
	 * - Requests:
	 *          + phone: phone number
	 *          + dob: DOB
	 * - return: json
	 */
	public function request_check_results()
	{
		global $wpdb;

		$return     = array('status' => 'error');

		$phone      = $this->params['phone'];
		$dob        = $this->formatdate($this->params['dob']);


		if (!empty($phone)) {
			$phones             = tr_patient_get_phone_arr($phone);
			$patient_id = 0;
			if (!empty($phones)) {
				$phones             = implode("','", $phones);

				$patient_id = $wpdb->get_var(
					"select pa_ID
										From ci_patients  
										where pa_phone in ('{$phones}')
										 and pa_dob = '{$dob}' "
				);
			}


			if ($patient_id > 0) {
				$return['results'] = array();

				$child_sql = array();
				foreach (tr_pdd_get_batch_types() as $btype) {
					$batch_tbl = tr_pdd_get_table_by_btype($btype);
					$report_tbl = tr_pdd_get_table_report_by_btype($btype);

					$child_sql[] = "select ba.assen as accession, 
									re.approve_date as result_date
								from {$batch_tbl} ba
								JOIN {$report_tbl} re On re.patient_id = ba.assen 
								Where ba.patient_id = {$patient_id} 
								and re.estatus = 'C' ";
				}

				$child_sql = implode(' UNION ALL ', $child_sql);
				$sql = "select * from ({$child_sql}) rs order by result_date desc";

				$return['results'] = $wpdb->get_results($sql);
				if (isset($_GET['tr_debug']) && !empty($wpdb->last_error)) {
					$return['error'] = $wpdb->last_error;
				}

				$return['status'] = count($return['results']) > 0 ? 'found' : 'not found';
			} else {
				$return['message'] = 'Phone or Dob not match';
			}
			$return['checkdate'] = date_i18n('Y-m-d H:i:s');
		} else {
			$return['message'] = 'Phone is empty';
		}

		$this->response(200, $return);
	}


	/**
	 * add a passport number to the requisition/result
	 * - Requests:
	 *          + phone: phone number
	 *          + dob: DOB
	 *          + passport: Passport number
	 * - return: json
	 */
	public function request_add_passport()
	{
		global $wpdb;

		$return     = array('status' => 'error');

		$phone      = $this->params['phone'];
		$dob        = $this->formatdate($this->params['dob']);
		$passport   = $this->params['passport'];

		if (!empty($phone) && !empty($dob) && !empty($passport)) {
			$phones             = tr_patient_get_phone_arr($phone);
			$patient_id = 0;
			if (!empty($phones)) {
				$phones             = implode("','", $phones);

				$patient_id = $wpdb->get_var("select pa_ID 
										from ci_patients
										where pa_phone in ('{$phones}') 
										and pa_dob = '{$dob}' ");
			}


			if ($patient_id > 0) {
				$wpdb->update('ci_patients', array('pa_passport' => $passport), array('pa_ID' => $patient_id));
				$return['status'] = 'success';
				$return['message'] = 'Added Passport successfully';
			} else {
				$return['message'] = 'Phone or dob not match.';
			}
		} else {
			$return['message'] = 'Phone or DOB is empty';
		}

		$this->response(200, $return);
	}

	/**
	 * send to phone the QR code for travel related to the passport number if there is a passport number that exists in req.
	 * - Requests:
	 *          + phone: phone number
	 *          + accession: Accession number
	 * - return: json
	 */
	public function request_send_qr()
	{
		global $wpdb, $tr_sms_gateway;

		$return     = array('status' => 'error');

		$phone      = $this->params['phone'];
		$assen      = $this->params['accession'];

		if (!is_object($tr_sms_gateway)) {
			$return['error'] = 'MMS Gateway error';
		} else if (!empty($phone) && !empty($assen)) {
			$phones             = tr_patient_get_phone_arr($phone);
			$phones             = implode("','", $phones);

			$btype = '';
			$mid = 0;
			$batch_tbl = '';
			foreach (tr_pdd_get_batch_types() as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$mid = $wpdb->get_var("select mid from {$batch_tbl} where assen = '{$assen}'");
				if ($mid > 0) {
					break;
				}
			}

			if ($mid > 0) {
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);

				$report = $wpdb->get_row("select pa.pa_ID,
												pa.pa_first,
												ba.patient_name,
												re.id
			                        From {$batch_tbl} ba 
			                        JOIN {$report_tbl} re On re.patient_id = ba.assen
									JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
									where pa.pa_phone in ('{$phones}') 
									and ba.mid = {$mid} ", ARRAY_A);

				if ($report && $report['id'] > 0) {
					$loaded = $tr_sms_gateway->load_library();

					if ($loaded) {
						$tr_sms_gateway->init_api();
					}
					$btype = 'pcr';
					$url = tr_patient_qr_url_report($report['id'], $btype);

					$options = get_option('trpatient_settings', array());
					if (empty($options['mms_msg'])) {
						$options['mms_msg'] = 'Travel Testing Validation QR ';
						$options['mms_msg'] = 'Covid-19 RT-PCR Result for {first name} {current date}';
					}
					$mms_message = stripslashes($options['mms_msg']);
					$mms_message = str_replace('{test name}', $test_name, $mms_message);
					$mms_message = str_replace(array('{patient name}', '{patient_name}'), $report['patient_name'], $mms_message);
					$mms_message = str_replace(array('{accessions}', '(accession)', '{accession}'), $assen, $mms_message);
					$mms_message = str_replace('{first name}', $report['pa_first'], $mms_message);
					$mms_message = str_replace('{current date}', date_i18n('m/d/Y'), $mms_message);

					$sent = $tr_sms_gateway->send_sms($phone, $mms_message, $report['patient_name'], $assen, 'ms', $report['pa_ID'], array($url));

					if ($sent) {
						$return['status'] = 'success';
						$return['message'] = 'Sent QR to phone successfully!';
					} else {
						$return['message'] = 'MMS gateway error.';
					}
				} else {
					$return['message'] = 'Record not found';
				}
			} else {
				$return['message'] = 'Record not found';
			}
		} else {
			$return['message'] = 'Phone or Accession is empty';
		}

		$this->response(200, $return);
	}
}
