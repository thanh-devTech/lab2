<?php
require_once  'restful_api.php';

add_filter('https_ssl_verify', '__return_false');


class CI_Json_Api extends restful_api
{

	public $api_id = -88;
	public $key_data = null;
	public $log = '';

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
		global $wpdb;

		if ($this->endpoint == 'request_info' && $this->request_api_key == 'viewinfo') {
			return true;
		}

		$this->key_data = $wpdb->get_row("select * from ci_wsdl_keys 
							where wsdl_key = '{$this->request_api_key}'
							and wsdl_type like 'json%' ");
		if (! $this->key_data) {
			$this->response(405, array('status' => 'error', 'message' => 'wrong key'));
			exit;
		}

		return true;
	}

	protected function get_all_tests($btype)
	{
		global $wpdb;
		$all_tests = array();
		$groups = array();
		$rs = $wpdb->get_results("select tg_ID,
		tg_cpt_code, 
		tg_name,
		tg_parent 
		from ci_test_groups where tg_type = '{$btype}' 
		order by tg_parent asc");
		foreach ($rs as $r) {
			if ($r->tg_parent > 0) {
				$groups[$r->tg_ID] = $groups[$r->tg_parent];
			} else {
				$groups[$r->tg_ID] = $r;

				$all_tests['GROUP_' . $r->tg_ID] =  array(
					'code' => $r->tg_cpt_code,
					'name' => $r->tg_name,
					'group' => $r->tg_name,
				);
			}
		}


		$rs = $wpdb->get_results("select te_ID,
		te_abrev,
		te_name,
		te_group_id
		from ci_test_panels where te_type = '{$btype}' ");
		foreach ($rs as $r) {

			$all_tests[$r->te_ID] =  array(
				'code' => $r->te_abrev,
				'name' => $r->te_name,
				'group' => $groups[$r->te_group_id]->tg_name,
			);
		}


		return $all_tests;
	}

	protected function get_ip()
	{
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = trim($ips[0]);
			if (!empty($ip)) return $ip;
		}

		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	protected function log($type, $key_id, $assen, $status, $xml, $rawin = '', $out = '')
	{
		global $wpdb;
		$at = date_i18n('Y-m-d H:i:s');

		if (empty($rawin)) {
			$rawin = file_get_contents("php://input");
			$rawin = substr($rawin, 0, 500);
		}

		if (is_array($xml)) {
			$xml = json_encode($xml);
		}
		$xml = substr($xml, 0, 500);
		if (is_array($out)) {
			$out = json_encode($out);
		}

		$rs = $wpdb->insert(
			'ci_wsdl_log',
			array(
				'wl_ID' => $key_id,
				'wl_type' => $type,
				'wl_key' => $assen,
				'wl_count' => 1,
				'wl_status' => $status,
				'wl_data' => $xml,
				'wl_datain' => $rawin,
				'wl_dataout' => $out,
				'wl_at' => $at,
				'wl_ip' => $this->get_ip()
			)
		);
		$this->log .= $wpdb->last_error;
		return $rs;
	}



	/**
	 * GET orders
	 *
	 *
	 * - Requests:
	 *      + date: DOC , default: current date (format:yyyy-mm-dd)
	 *      + limit: limit samples, default: 50
	 *
	 * - Response: 
	 * {
	 * "status":"OK",
	 * "total":1,
	 * "orders":[{
			"system" : "igniteLIS",
			"sending_software" : "igniteLIS",
			"location" :  "Lab",
			"accession" : "Accession",
			"type_of_specimen" : "Specimens",
			"doc"        : "2025-01-01",
			"clinic"     : "Clinic name",
			"doctor_first_name"     : "doctor first name",
			"doctor_last_name"     : "doctor last name",
			"doctor_npi"     : "doctor npi",
			"patient"    : {
				"first_name" : "first name",
				"last_name"  : "last name",
				"gender"     : "gender",
				"dob"        : "1987-01-01",
				"address"    : "address",
				"city"       : "city",
				"state"      : "MI",
				"zip"        : "12345",
			},
			"tests"      : [{
				"code" => "test code",
				"name" => "test name",
				"group" => "panel name",
			}]
	 * }]
	 * }
	 */
	public function request_get_orders()
	{
		global $wpdb;

		$return = array('status' => 'OK', 'number' => 0, 'total' => 0, 'orders' => array());

		$date      = isset($this->params['date']) && !empty($this->params['date']) ? $this->params['date'] : date_i18n('Y-m-d');
		$page      = isset($this->params['page']) && $this->params['page'] > 0 ? $this->params['page'] : 1;
		$limit      = isset($this->params['limit']) && $this->params['limit'] > 0 ? $this->params['limit'] : 50;
		if ($limit > 500) {
			$limit = 500;
		}
		if ($page > 1) {
		}

		$start_date = $this->key_data->wsdl_start;

		$cli_IDs = $wpdb->get_col("select cli_ID from ci_wsdl_key_clinics where wsdl_ID = {$this->key_data->wsdl_ID}");

		$btypes = tr_pdd_get_batch_types();
		if ($this->key_data->wsdl_type != 'json') {
			$btype = str_replace('json_', '', $this->key_data->wsdl_type);
			$btypes = array($btype);
		}

		$return['btypes'] = $btypes;


		$where = "";
		if (!empty($date)) {
			$where .= " and ba.doc >= '{$date}' and ba.doc <='{$date} 23:59:59' ";
		} else if (! empty($start_date)) {
			$where .= " and ba.doc >= '{$start_date}' ";
		}

		if (count($cli_IDs) == 1 && $cli_IDs[0] == -1) {
			$cli_IDs = array();
		}

		if (count($cli_IDs) > 0) {
			$cli_IDs_str = implode(',', $cli_IDs);
			$where .= " and ba.cli_ID in ({$cli_IDs_str}) ";
		}

		if ($this->key_data->wsdl_ref_lab_ID > 0) {
			$where .= " and ba.ref_lab_ID  =  {$this->key_data->wsdl_ref_lab_ID} ";
		} else if ($this->key_data->wsdl_ref_lab_ID == -1) {
			$where .= " and ba.ref_lab_ID  > 0 ";
		}

		foreach ($btypes as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$all_tests = $this->get_all_tests($btype);

			$sql = "select ba.mid,
                    ba.assen,
                    ba.type_of_specimen,
                    ba.doc,
                    ba.requesting_practice,
                    ba.panel_tests_requested,
                    ba.requesting_physician,
                    pa.pa_first,
                    pa.pa_last,
                    pa.pa_gender,
                    pa.pa_dob,
                    pa.pa_address,
                    pa.pa_city,
                    pa.pa_state,
                    pa.pa_zip,
                    lab.lab_dba,
                    lab.lab_billing_account,
                    doc.doc_first,
                    doc.doc_last,
                    doc.doc_npi
                    
            from {$batch_tbl} ba
            JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            JOIn ci_patients pa ON pa.pa_ID = ba.patient_id
            JOIn ci_doc doc ON doc.doc_ID = ba.doc_ID
            JOIn ci_clinic cli ON cli.cID = ba.cli_ID
             where 1 {$where}
             order by mid asc
			 limit {$limit}";

			$sql_total = "select count(*)
            from {$batch_tbl} ba
            JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            JOIn ci_patients pa ON pa.pa_ID = ba.patient_id
            JOIn ci_doc doc ON doc.doc_ID = ba.doc_ID
            JOIn ci_clinic cli ON cli.cID = ba.cli_ID
             where 1 {$where}";


			$total = $wpdb->get_var($sql_total);
			$return['total'] += $total;

			$rs = $wpdb->get_results($sql);

			if (!empty($wpdb->last_error)) {
				$return['error'] = $wpdb->last_error;
			}
			//$return['query'] = $sql;

			$return['number'] += count($rs);


			foreach ($rs as $r) {
				$patientdata        = array(
					'first_name' => $r->pa_first,
					'last_name'  => $r->pa_last,
					'gender'        => $r->pa_gender,
					'dob'        => $r->pa_dob,
					'address'    => $r->pa_address,
					'city'       => $r->pa_city,
					'state'      => $r->pa_state,
					'zip'        => $r->pa_zip,
				);
				$tests              = array();
				foreach (explode(',', $r->panel_tests_requested) as $t) {
					if (isset($all_tests[$t])) {
						$tests[] = $all_tests[$t];
					}
				}

				$rowdata            = array(
					'system' => 'igniteLIS',
					'sending_software' => 'igniteLIS',
					'location' => $r->lab_dba,
					'accession' => $r->assen,
					'type_of_specimen' => $r->type_of_specimen,
					'doc'       => $r->doc,
					'clinic'    => $r->requesting_practice,
					'doctor_first_name'    => $r->doc_first,
					'doctor_last_name'    => $r->doc_last,
					'doctor_npi'    => $r->doc_npi,
					'patient'   => $patientdata,
					'tests'     => $tests
				);
				$return['orders'][] = $rowdata;
			}
		}



		$this->log('get_orders', $this->key_data->wsdl_ID, date_i18n('Y-m-d H:i:s'), $return['status'], json_encode($return));

		$this->response(200, $return);
	}

	/**
	 * Put Order
	 *
	 * - Put JSON in body (PUT method):
	 *
	 * 
	 {
	 	"type": "pcr",
		"accession_number": "HLX001922 or empty",
		"ref_accession": "REF001922",
		"date_of_collection": "2015-12-23 11:00:00",
		"spec_received": "2015-12-23 18:00:00",
		"clinic": "Ergo Sum Health",
		"doctor": "Sayeed,Faisal a.",
		"doctor_npi": "1234567890",
		"doctor_phone": "410-676-1463",
		"collector": "Michelle Waldman",
		"col_phone": "410-676-1463",
		"primary_dagnostic_code": "CYP2C19",
		"type_of_specimen": "Blood",
		"panel_tests_requested": "Amylase,B12,CEA,FSH,Albumin",
		"notes": "notes here",
		"billing_notes": "notes here",
		"patient_signature": "base64 image",
		"provider_signature": "base64 image",
		"ins_card_front": "base64 image",
		"ins_card_back": "base64 image",
		"patient_id_front": "base64 image",
		"patient_id_back": "base64 image",
		"patient": {
			"pa_patient_id": "patient ID",
			"pa_first": "Genese",
			"pa_last": "Mackie",
			"pa_middle": "A",
			"pa_dob": "1960-04-09",
			"pa_phone": "Phone number",
			"pa_gender": "M or F",
			"pa_address": "Address",
			"pa_city": "City",
			"pa_state": "State Code",
			"pa_zip": "Zip code",
			"pa_ssn": "ssn",
			"pa_mrn": "Medical Record Number",
			"pa_apt": "apt",
			"pa_passport": "Passport number"
		},
		"primary_insurance": {
			"ins_first": "first name",
			"ins_last": "last name",
			"ins_company": "Medicare",
			"ins_relation": "Self (or) Parent (or) Spouse",
			"ins_dob": "1987-01-01",
			"ins_email": "email@domain.com",
			"ins_address": "Address",
			"ins_city": "City",
			"ins_state": "State code",
			"ins_zip": "Zip code",
			"ins_phone": "phone number",
			"ins_member_id": "member id",
			"ins_group_id": "Group id"
		},
		"second_insurance": {
			"ins_first": "first name",
			"ins_last": "last name",
			"ins_company": "Medicare",
			"ins_relation": "Self (or) Parent (or) Spouse",
			"ins_dob": "1987-01-01",
			"ins_email": "email@domain.com",
			"ins_address": "Address",
			"ins_city": "City",
			"ins_state": "State code",
			"ins_zip": "Zip code",
			"ins_phone": "phone number",
			"ins_member_id": "member id",
			"ins_group_id": "Group id"
		}
	}

	 * - Response:  
	 * {
	 		"status": "OK",
	 * }
	 *
	 */

	public function request_put_order()
	{
		global $wsdl_request_data;
		$return		= array('status' => 'OK');

		$btype 		= $this->params['type'];
		$wsdl_request_data = $this->params;
		$data		= (object)$this->params;

		if (is_array($data->patient)) {
			$data->patient = (object) $data->patient;
		}
		if (is_array($data->primary_insurance)) {
			$data->primary_insurance = (object) $data->primary_insurance;
		}
		if (is_array($data->second_insurance)) {
			$data->second_insurance = (object) $data->second_insurance;
		}

		$xml = new CI_Data_Work_Xml();

		$lab_id = $this->key_data->wsdl_lab_ID;
		$ref_lab_id = $this->key_data->wsdl_ref_lab_ID;

		$return = $xml->import_req(
			$data,
			array(
				'btype' => $btype,
				'lab_id' => $lab_id,
				'ref_lab_id' => $ref_lab_id,
				'return' => $return,
			)
		);

		//log
		$xml->_log('put_' . $btype, $this->key_data->wsdl_ID, $return['assen'], $return['status'], $data, $return);

		$this->response(200, $return);
	}


	/**
	 * Put result PDF
	 *
	 * - Put JSON in body (PUT method):
	 *
	 * {"accession":"Accession here","pdf_data":"Base64 encoded","error_code":0,"error_message":""}
	 * 
	 * 
	 * - Response:  
	 * {
	 		"status": "OK",
	 * }
	 *
	 */
	public function request_put_result_pdf()
	{
		global $wpdb;

		$return         = array('status' => 'success');

		$assen        	= $this->params['accession'];
		$pdf_arr     	= $this->params['pdf_data'];
		$errormsg		= isset($this->params['error_details']) ? $this->params['error_details'] : '';
		$error_code		= isset($this->params['error_code']) ? $this->params['error_code'] : '';
		$error_message		= isset($this->params['error_message']) ? $this->params['error_message'] : '';
		$haspdf		 	= false;
		$updatebatdata 	= array();

		if (empty($error_message)) {
			$error_message = $errormsg;
		}


		if (!is_array($pdf_arr) && !empty($pdf_arr)) {
			$pdf_arr = array();
			$pdf_arr[] = array('report_file' => $this->params['pdf_data']);
			$this->params['pdf_data'] = substr($this->params['pdf_data'], 0, 50);
			for ($i = 1; $i <= 10; $i++) {
				if (isset($this->params['pdf_data' . $i])) {
					$pdf_arr[] = array('report_file' => $this->params['pdf_data' . $i]);
					$this->params['pdf_data' . $i] = substr($this->params['pdf_data' . $i], 0, 50);
				}
			}
		} else {
			$this->params['pdf_data'] = '';
		}

		//$return['pdf_arr'] = $pdf_arr;

		if (!empty($this->files)) {
			foreach ($this->files as $filearr) {
				$pdf_arr[] = array('file' => $filearr['tmp_name']);
				if (!isset($this->params['files'])) {
					$this->params['files'] = array();
				}
				$this->params['files'][] = $filearr['name'];
			}
		}

		if (empty($assen)) {
			$return['status'] = 'error';
			$return['message'] = 'No accession number';
		} else {
			$bat = null;
			$btype = '';
			foreach (tr_pdd_get_batch_types() as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$bat = $wpdb->get_row("select * from {$batch_tbl} where assen = '{$assen}'");
				if (!empty($bat)) {
					break;
				}
			}

			if (!empty($bat) || empty($pdf_arr)) {
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);
				$report_id = $wpdb->get_var("select id from {$report_tbl} where patient_id='{$assen}' ");
				if ($report_id == 0) {
					$wpdb->insert($report_tbl, array(
						'patient_id'   => $assen,
						//'estatus'      => 'C',
						'created_date' => date_i18n('Y-m-d'),
						'approve_date' => date_i18n('Y-m-d H:i:s'),
						'created'      => date_i18n('Y-m-d H:i:s'),
					));
					$report_id = $wpdb->insert_id;
				}
				$dir = tr_get_result_upload_dir();
				if (!file_exists($dir['path'])) {
					wp_mkdir_p($dir['path']);
				}

				$ext = 'pdf';
				$num = 1;
				$files = array();
				if (!empty($bat->result_pdf)) {
					$files = explode(',', $bat->result_pdf);
				}

				if (count($pdf_arr) >= 2) {
					$new_files = array();
					foreach ($files as $f) {
						if (stripos($f, 'aegis') !== false) {
							$new_files[] = $f;
						}
					}
					$files = $new_files;
				}

				foreach ($pdf_arr as $arr1) {
					if (isset($arr1['Report_file'])) {
						$pdf_data = $arr1['Report_file'];
					} else if (isset($arr1['report_file'])) {
						$pdf_data = $arr1['report_file'];
					} else if (isset($arr1['file'])) {
						$pdf_data = file_get_contents($arr1['file']);
					} else if (is_string($arr1)) {
						$pdf_data = $arr1;
					}

					if (strlen($pdf_data) < 10) {
						//$return['pdf_data_length'] = strlen($pdf_data);
						continue;
					}
					$pdf_data = base64_decode($pdf_data);

					$fname 		= $assen . '.' . $ext;
					$filepath 	= $dir['path'] . '/' . $fname;

					//check exist files
					if (count($files) > 0) {
						$md5new = md5($pdf_data);
						$samedata = false;
						foreach ($files as $fold) {
							$md5old = md5_file($dir['path'] . '/' . $fold);

							if ($md5old == $md5new) {
								$samedata = true;
								break;
							}
						}

						if ($samedata) {
							$haspdf = true;
							continue;
						}
					}

					while (file_exists($filepath)) {
						$fname = $assen . '.' . $num . '.' . $ext;
						$filepath = $dir['path'] . '/' . $fname;
						$num++;
					}

					if (file_put_contents($filepath, $pdf_data)) {
						//update db
						$files[] = $fname;
						$haspdf = true;
					} else {
						$return['status'] = 'error';
						$return['message'] = 'error when saving file';
					}
				}

				if (!empty($files)) {
					$updatebatdata['result_pdf'] = implode(',', $files);
				}
			} else {
				$return['status'] = 'error';
				$return['message'] = 'Not found accession';
			}
		}

		if (!$haspdf && $return['status'] != 'error') {
			$return['status'] = 'error';
			$return['message'] = 'No PDF data';
		}

		if (!empty($error_message)) {
			$updatebatdata['internal_notes'] = 'Receive Error: ' . $error_code . ' ' . $error_message;
			$return['processing'] = $error_code . ' ' . $error_message;
		} else if (!$haspdf) {
			$updatebatdata['internal_notes'] = "Receive Error: No PDF File";
			$return['processing'] =  'No Error Specified';
		} else if ($bat && stripos($bat->internal_notes, 'Receive Error:') !== false) {
			$updatebatdata['internal_notes'] = "";
		}

		if (!empty($updatebatdata) && $bat) {
			$wpdb->update($batch_tbl, $updatebatdata, array('mid' => $bat->mid));
		}






		$this->log('put_result_pdf', $this->key_data->wsdl_ID, $assen, $return['status'], $this->params, '', json_encode($return));

		///*
		ob_start();
		var_dump($_POST);
		var_dump($_FILES);
		$this->log;

		$logdata = ob_get_contents();
		file_put_contents(WP_CONTENT_DIR . '/log.txt', $logdata);
		//*/

		$this->response(200, $return);
	}


	/**
	 * Put result
	 *
	 * - Put JSON in body (PUT method):
	 *
	 * {"results":[{"accession":"Accession here","testname":"test name 1","value":number}, {"accession":"Accession here","testname":"test name 2","value":number}]}
	 
	 
	 * - Response:  
	 * {
	 		"status": "OK",
	 * }
	 *
	 */
	public function request_put_results()
	{
		global $wpdb;

		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';
		$result_tbl = 'ci_results_pcr';

		$return         = array('status' => 'success');

		$results        = $this->params['results'];


		foreach ($results as $row) {

			$assen 		= $row['accession'];
			$testname 	= $row['testname'];
			$conc		= $row['value'];

			if (empty($assen) || empty($testname)) {
				continue;
			}


			$mid = $wpdb->get_var("select mid from {$batch_tbl} where assen = '{$assen}'");
			if ($mid > 0) {

				$report_id = $wpdb->get_var("select id from {$report_tbl} where patient_id='{$assen}' ");
				if ($report_id == 0) {
					$wpdb->insert($report_tbl, array(
						'patient_id'   => $assen,
						//'estatus'      => 'C',
						'created_date' => date_i18n('Y-m-d'),
						'approve_date' => date_i18n('Y-m-d H:i:s'),
						'created'      => date_i18n('Y-m-d H:i:s'),
					));
					$report_id = $wpdb->insert_id;
				}

				if ($report_id > 0) {
					$result_id = $wpdb->get_var("select id from {$result_tbl} where report_id = {$report_id}
													and drug = '{$testname}' ");
					if ($result_id > 0) {
						$wpdb->update($result_tbl, array('conc' => $conc), array('id' => $result_id));
					} else {
						$wpdb->insert($result_tbl, array(
							'report_id' => $report_id,
							'drug'      => $testname,
							'conc'   => $conc
						));
					}
				}
			}
		}

		$this->log('put_results', $this->key_data->wsdl_ID, date_i18n('Y-m-d H:i:s'), $return['status'], '', '', json_encode($return));

		$this->response(200, $return);
	}

	/**
	 * Get Clinics
	 *
	 * - Request: - code: Referencing lab 3 letter code.
	 *
	 * - Response:
	 * {
	 *		"status" : "ok",
	 *		"total"	: 1,
	 *		"clinics":[{
	 *			"ID"	: 123,
	 *			"name	: "abc",
	 *			"address": " address",
	 *			"city"	: "city",
	 *			"state" : "state",
	 *			"zip"	: "zip code",
	 *			"phone_number" : "phone number",
	 *			"fax_number"	: "fax number"
	 *		}]
	 * }
	 *
	 */
	public function request_clinics()
	{
		global $wpdb;

		$return = array('status' => 'ok', 'total' => 0, 'clinics' => array());

		$code      = isset($this->params['code']) && !empty($this->params['code']) ? $this->params['code'] : '';
		$where = '';
		if (!empty($code)) {
			$ref_lab_ID = $wpdb->get_var("select lab_ID from ci_labs where lab_dba_init = '{$code}' ");

			if ($ref_lab_ID > 0) {
				$where .= " and cli_ref_lab_ID = {$ref_lab_ID} ";
			}
		}


		$clinics = $wpdb->get_results("select 
			cID as ID,
			cli_clinic as name,
			cli_street as address,
			cli_city as city,
			cli_state as state,
			cli_zip as zip,
			`cli_om-phone` as phone_number,
			`cli_om-fax` as fax_number
			from ci_clinic 
			where 1 {$where}", ARRAY_A);
		$return['total'] = count($clinics);

		foreach ($clinics as $cli) {
			$cli['doctors'] = $wpdb->get_results("select doc.doc_first as first_name,
				doc.doc_last as last_name,
				doc.doc_npi as npi
				from ci_doc doc
				JOIN ci_clinic_doc as cd ON cd.doc_ID = doc.doc_ID
				where cd.cli_ID = {$cli['ID']} ");

			$return['clinics'][] = $cli;
		}
		$this->response(200, $return);
	}
}
