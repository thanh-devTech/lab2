<?php

class CI_Data_Work_Xml
{

	var $xml;
	var $drug_groups_all;
	var $cutoff_all;
	var $all_groups = array();
	var $all_tests = array();

	public $default_cli_ID = 0;
	public $default_doc_ID = 0;

	public $isjsonwebhook = false;


	public function get_group_by_matrix($matrix)
	{
		global $wpdb;
		if (isset($this->drug_groups_all[$matrix])) {
			return $this->drug_groups_all[$matrix];
		}

		$drug_groups = array(
			"Natural and Semi-Synthetic Opioids" => array(),
			"Synthetic Opioids" => array(),
			"Benzodiazapines" => array(),
			"Stimulants" => array(),
			"Illicits" => array()
		);

		$groups_q = $wpdb->get_results("SELECT cmpd, `group` FROM ci_cutoffs WHERE `group`!='Other' and matrix='" . $matrix . "' AND active='1' ORDER BY cmpd ASC", ARRAY_A);
		foreach ($groups_q as $g) {
			$drug_groups[$g['cmpd']] = $g['group'];
		}
		$this->drug_groups_all[$matrix] = $drug_groups;
		return $drug_groups;
	}

	public function get_cutoff($matrix)
	{
		global $wpdb;
		if (isset($this->cutoff_all[$matrix])) {
			return $this->cutoff_all[$matrix];
		}

		$ci_cutoffs_q = $wpdb->get_results("SELECT * FROM ci_cutoffs WHERE matrix='" . $matrix . "' AND active='1'", ARRAY_A);
		$ci_cutoffs = array();
		if (count($ci_cutoffs_q) > 0) {
			foreach ($ci_cutoffs_q as $c) {
				$ci_cutoffs[$c['cmpd']] = (float)$c['cutoff'];
			}
		}
		$this->cutoff_all[$matrix] = $ci_cutoffs;
		return $ci_cutoffs;
	}

	function getClinicByKey($key)
	{
		global $wpdb;
		$sql = "select kc.cli_ID as cID from ci_wsdl_keys k
                                    JOIN ci_wsdl_key_clinics kc ON kc.wsdl_ID = k.wsdl_ID
                                    where k.wsdl_key = '{$key}'
                                    ";
		$cli_IDs = $wpdb->get_col($sql);

		if (!is_array($cli_IDs)) {
			$cli_IDs = array();
		}

		return $cli_IDs;
	}

	function getLabByKey($key)
	{
		global $wpdb;

		return $wpdb->get_var("select wsdl_lab_ID from ci_wsdl_keys where wsdl_key = '{$key}'");
	}

	function getRowKey($key)
	{
		global $wpdb;
		if (empty($key)) {
			return false;
		}
		return $wpdb->get_row("select * from ci_wsdl_keys where wsdl_key = '{$key}'", ARRAY_A);
	}

	function checkKey($key)
	{
		global $wpdb;
		if (empty($key)) {
			return false;
		}

		return $wpdb->get_var("select wsdl_ID from ci_wsdl_keys where wsdl_key = '{$key}'");
	}

	public function format_date($date)
	{
		if (strpos($date, '/') !== false) {
			list($date, $time) = explode(' ', $date);
			list($m, $d, $y) = explode('/', $date);
			$date = $y . '-' . $m . '-' . $d;
			if (!empty($time)) {
				$date .= ' ' . $time;
			}
		} else if (substr($date, 2, 1) == '-') {
			list($date, $time) = explode(' ', $date);
			list($m, $d, $y) = explode('-', $date);
			$date = $y . '-' . $m . '-' . $d;
			if (!empty($time)) {
				$date .= ' ' . $time;
			}
		}
		return $date;
	}

	public function get_ip()
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

	public function objToXML($obj, $root = '')
	{
		if ($this->isjsonwebhook) {
			return json_encode($obj);
		}

		$this->xml = new XmlWriter();
		$this->xml->openMemory();
		$this->xml->setIndent(0);

		$this->getObject2XML($this->xml, $obj, $root);
		$this->xml->endElement();
		$xml = $this->xml->outputMemory(true);
		$xml = preg_replace("/[\r\n]/", '', $xml);
		return $xml;
	}
	// Method to convert XML string into Object
	public function xmlToObj($xmlString)
	{
		if (is_array($xmlString)) {
			return (object)$xmlString;
		} else if (is_object($xmlString)) {
			return $xmlString;
		} else if ($this->isjsonwebhook) {
			return json_decode($xmlString);
		}

		$xmlString = trim($xmlString);
		$obj = simplexml_load_string($xmlString);
		if (!$obj && stripos($xmlString, 'encoding="utf-16"') > 0) {
			$xmlString = preg_replace("/\<\?xml[^\>]*\>/", '', $xmlString);
			$obj = simplexml_load_string($xmlString);
		}
		return $obj;
	}

	private function getObject2XML(XMLWriter $xml, $data, $root = '')
	{
		if (!empty($root)) {
			$xml->startElement($root);
		} else if ($root == 'xml') {
			$xml->startDocument('1.0');
		}
		foreach ($data as $key => $value) {
			if (is_object($value) && $value != null) {
				$xml->startElement($key);
				$this->getObject2XML($xml, $value);
				$xml->endElement();
				continue;
			} else if (is_array($value)) {
				$this->getArray2XML($xml, $key, $value);
			} else {
				$xml->writeElement($key, $value);
			}
		}
	}
	private function getArray2XML(XMLWriter $xml, $keyParent, $data)
	{
		foreach ($data as $key => $value) {
			if (is_string($value)) {
				$xml->writeElement($keyParent, $value);
				continue;
			}
			if (is_numeric($key)) {
				$xml->startElement($keyParent);
			}

			if (is_object($value)) {
				$this->getObject2XML($xml, $value);
			} else if (is_array($value)) {
				$this->getArray2XML($xml, $key, $value);
				continue;
			}

			if (is_numeric($key)) {
				$xml->endElement();
			}
		}
	}

	public function _log($type, $key_id, $assen, $status, $xml, $xmlout = '')
	{
		global $wpdb, $wsdl_request_data;
		$at = date_i18n('Y-m-d H:i:s');


		if (is_object($wsdl_request_data) || is_array($wsdl_request_data)) {
			$wsdl_request_data = json_encode($wsdl_request_data);
		}
		if (is_object($xml) || is_array($xml)) {
			$xml = json_encode((array)$xml);
		}

		if (is_object($xmlout) || is_array($xmlout)) {
			$xmlout = json_encode($xmlout);
		}

		$count = $wpdb->get_var("select wl_count from ci_wsdl_log where wl_ID={$key_id} and wl_type='{$type}' and wl_key='{$assen}' ");
		if ($count > 0 && !empty($assen)) {
			$rs = $wpdb->update(
				'ci_wsdl_log',
				array(
					'wl_count' => $count + 1,
					'wl_data' => $xml,
					'wl_datain' => $wsdl_request_data,
					'wl_dataout' => $xmlout,
					'wl_at' => $at,
					'wl_status' => $status,
				),
				array('wl_ID' => $key_id, 'wl_type' => $type, 'wl_key' => $assen)
			);
		} else {
			$rs = $wpdb->insert(
				'ci_wsdl_log',
				array(
					'wl_ID' => $key_id,
					'wl_type' => $type,
					'wl_key' => $assen,
					'wl_count' => 1,
					'wl_status' => $status,
					'wl_data' => $xml,
					'wl_datain' => $wsdl_request_data,
					'wl_dataout' => $xmlout,
					'wl_at' => $at,
					'wl_ip' => $this->get_ip()
				)
			);
		}
		return $rs;
	}

	public function get_signature_data($filename)
	{
		$sign_dir = tr_get_signature_dir();
		$filepath = $sign_dir['dir'] . '/' . $filename;
		if (file_exists($filepath)) {
			return tr_get_image_data($filepath);
		}
		return '';
	}

	public function get_card_data($filename)
	{
		$sign_dir = tr_get_ins_card_dir();
		$filepath = $sign_dir['dir'] . '/' . $filename;
		if (file_exists($filepath)) {
			return tr_get_image_data($filepath);
		}
		return '';
	}

	private function get_test_object($t, $btype)
	{
		global $wpdb;
		if (!isset($this->all_groups[$btype])) {
			$this->all_groups[$btype] = array();
			$rs = $wpdb->get_results("select tg_ID, tg_name, tg_short_name
		                from ci_test_groups 
		                where tg_type = '{$btype}' ");
			foreach ($rs as $r) {
				$this->all_groups[$btype][$r->tg_ID] = array(
					'name' => $r->tg_name,
					'short_name' => $r->tg_short_name,
					'type' => 'group',
				);
			}
		}
		if (!isset($this->all_tests[$btype])) {
			$this->all_tests[$btype] = array();

			$rs = $wpdb->get_results("select te_ID, te_name, te_abrev
		                from ci_test_panels 
		                where te_type = '{$btype}' ");
			foreach ($rs as $r) {
				$this->all_tests[$btype][$r->te_ID] = array(
					'name' => $r->te_name,
					'short_name' => $r->te_abrev,
					'type' => 'test',
				);
			}
		}

		if (stripos($t, 'group_') !== false) {
			$groupid = str_ireplace('group_', '', $t);
			if (isset($this->all_groups[$btype][$groupid])) {
				$t = $this->all_groups[$btype][$groupid];
			}
		} else if (isset($this->all_tests[$btype][$t])) {
			$t = $this->all_tests[$btype][$t];
		}
		return $t;
	}

	public function get_req_data($mid, $btype)
	{
		global $wpdb;

		$batch_tbl  = tr_pdd_get_table_by_btype($btype);

		$sql        = "select ba.* from {$batch_tbl} ba where ba.mid = {$mid}";
		$data       = $wpdb->get_row($sql);

		$remove_fields = array(
			'cli_ID',
			'doc_ID',
			'lab_id',
			'col_ID',
			'author_ID',
			'patient_id',
			'insurance1',
			'insurance2',
			'is_dna',
			'covid_question_id',
			'ref_from_lab_ID',
			'mid',
			'ref_lab_ID',
			'lab'
		);

		$remove_cli_fields = array(
			'cID',
			'cli_lab-ID',
			'cli_rep-ID',
			'cli_group_ID',
			'cli_marketing_group_ID',
			'cli_distributor_ID',
			'cli_om_user_ID',
			'cli_import_ID',
			'cli_elink_id',
			'cli_athena_departmentid',
			'cli_solv_hash',
			'cli_experity_id',
			'cli_elink_id2',
			'cli_sms_number',
			'cli_Elink_lab_ID',
			'cli_athena_departmentid2',
			'cli_Telcor_ID',
			'cli_faxres',
			'cli_portal',
			'cli_fax_dna',
			'cli_fax_blood',
			'cli_fax_pcr',
			'cli_ref_lab_ID',
			'cli_send_ocovid_sms'
		);

		$signatures = array('patient_signature', 'provider_signature');
		$cards = array('ins_card_front', 'ins_card_back', 'patient_id_front', 'patient_id_back');

		if ($data) {
			$data->panels = array();
			$panel_names = array();
			foreach (explode(',', $data->panel_tests_requested) as $t) {
				$tobj           = $this->get_test_object($t, $btype);
				$data->panels[] = $tobj;
				$panel_names[]  = $tobj['name'];
			}
			$data->panel_tests_requested = implode(',', $panel_names);

			$data->patient      = $wpdb->get_row("select * from ci_patients where pa_ID = '{$data->patient_id}' ");
			$data->primary_insurance = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$data->insurance1}' ");
			$data->second_insurance = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$data->insurance2}' ");
			$data->clinic_detail = $wpdb->get_row("select * from ci_clinic where cID = '{$data->cli_ID}' ");
			$data->doctor_detail = $wpdb->get_row("select * from ci_doc where doc_ID = '{$data->doc_ID}' ");
			$data->collector_detail    = $wpdb->get_row("select * from ci_collector where col_ID = '{$data->col_ID}' ");

			$data->clinic   = $data->requesting_practice;
			$data->doctor   = $data->requesting_physician;
			$data->primary_dagnostic_code = $data->dagnostic_codes1;
			$data->second_dagnostic_code = $data->dagnostic_codes2;
			$data->doctor_phone = $data->physician_phone;
			$data->date_of_collection = $data->doc;
			$data->medications = $data->current_medication;

			$data->collector = '';
			if ($data->collector_detail) {
				$data->collector = $data->collector_detail->col_last . ',' . $data->collector_detail->col_first;
			}

			foreach ($signatures as $sk) {
				if (!empty($data->{$sk})) {
					$data->{$sk} = $this->get_signature_data($data->{$sk});
				}
			}

			foreach ($cards as $sk) {
				if (!empty($data->{$sk})) {
					$data->{$sk} = $this->get_card_data($data->{$sk});
				}
			}

			if ($data->clinic_detail) {
				foreach ($remove_cli_fields as $fk) {
					unset($data->clinic_detail->{$fk});
				}
			}

			foreach ($remove_fields as $fk) {
				unset($data->{$fk});
			}
		}

		return $data;
	}

	public function import_req($data, $args = array())
	{
		global $wpdb;

		$return     = isset($args['return']) ? $args['return'] : array();
		$btype      = isset($args['btype']) ? $args['btype'] : 'tox';
		$lab_id     = isset($args['lab_id']) ? $args['lab_id'] : 0;
		$ref_lab_id = isset($args['ref_lab_id']) ? $args['ref_lab_id'] : 0;
		$paid_id	= isset($args['paid']) ? $args['paid'] : 0;

		if (empty($lab_id)) {
			$lab_id = $wpdb->get_var("select lab_ID from ci_labs order by lab_ID asc limit 1");
		}

		$dir = tr_get_result_upload_dir();

		$bat_keys = array(
			'lab' => 'lab',
			'position' => 'position',
			'batch_id' => 'batch_id',
			'requesting_practice' => 'clinic',
			'requesting_physician' => 'doctor',
			'physician_phone' => 'doctor_phone',
			'dagnostic_codes1' => 'primary_dagnostic_code',
			'dagnostic_codes2' => 'second_dagnostic_code',
			'type_of_specimen' => 'type_of_specimen',
			'doc'               => 'date_of_collection',
			'dob'               => 'dob',
			'spec_received'     => 'spec_received',
			'temperature'       => 'temperature',
			'actual_temperature' => 'actual_temperature',
			'current_medication' => 'medications', //medications
			'poc_perform'           => 'poc_perform',
			'medication'        => 'medication_or_drug',
			'custom_profile'    => 'custom_profile',
			'panel_tests_requested' => 'panel_tests_requested',
			'notes' => 'notes',
			'billing_notes' => 'billing_notes',
			'fasting' => 'fasting'
		);

		if ($btype == 'req') {
			$bat_keys['collection_agency_name'] = 'collection_agency_name';
			$bat_keys['collection_agency_address'] = 'collection_agency_address';
			$bat_keys['collection_agency_city'] = 'collection_agency_city';
			$bat_keys['collection_agency_state'] = 'collection_agency_state';
			$bat_keys['collection_agency_zip'] = 'collection_agency_zip';
			$bat_keys['collection_agency_phone'] = 'collection_agency_phone';
			$bat_keys['collection_datetime'] = 'collection_datetime';
			$bat_keys['shipment_picked_up_datetime'] = 'shipment_picked_up_datetime';
		}

		$bat = array();
		//set default
		$bat['batch_type'] = 'A';
		//$bat['batch_id'] = 0;

		if (isset($data->accession_number)) {
			$bat['assen'] = (string) $data->accession_number;
		}

		if (isset($data->ref_accession)) {
			$bat['oldreq'] = (string) $data->ref_accession;
		}

		if (empty($bat['assen']) && isset($data->assen)) {
			$bat['assen'] = (string) $data->assen;
		}
		$use_oldreq = false;
		if (empty($bat['assen']) && !empty($bat['oldreq'])) {
			$use_oldreq = true;
		}

		$bat['assen'] = preg_replace("/[^a-zA-Z0-9]/", '', $bat['assen']);

		$remote_ID  = isset($data->remote_ID) ? (int)$data->remote_ID : 0;

		//find clinic

		if (isset($data->requesting_practice) || isset($data->clinic)) {
			$clinic     = isset($data->requesting_practice) ? $data->requesting_practice : $data->clinic;
			$clinic     = trim($clinic);
			$sql        = $wpdb->prepare("select cID from ci_clinic 
									where cli_clinic = %s  
									order by cID asc limit 1", $clinic);
			$bat['cli_ID'] = $wpdb->get_var($sql);
		} else {
			$bat['cli_ID'] = $args['cli_ids'][0];
		}




		if (empty($bat['assen']) && !$use_oldreq) {
			$return['status'] = 'ERROR';
			$return['error_code'] = '09';
			$return['msg'] = 'Accession Number is empty';

			return $return;
		} else if (isset($args['cli_ids']) && count($args['cli_ids']) > 0 && !in_array($bat['cli_ID'], $args['cli_ids']) && !in_array(-1, $args['cli_ids'])) {
			$return['status'] = 'ERROR';
			$return['error_code'] = '10';
			$return['msg'] = 'Clinic not in list';
			return $return;
		} else {
			//find exists

			$table = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$batch_cols_rs = $wpdb->get_col('explain ' . $table);
			$batch_cols = array();
			foreach ($batch_cols_rs as $colk) {
				$batch_cols[strtolower($colk)] = $colk;
			}


			$bat_exists = null;
			if ($use_oldreq) {
				$bat_exists = $wpdb->get_row("select * from {$table} where oldreq = '{$bat['oldreq']}' ", ARRAY_A);
				if (!$bat_exists) {
					$bat['assen'] = tr_get_new_assen($btype);
				} else {
					$bat['assen'] = $bat_exists['assen'];
				}
			}

			if (!$bat_exists) {
				$bat_exists = $wpdb->get_row("select * from {$table} where assen = '{$bat['assen']}' ", ARRAY_A);
			}
			if (!$bat_exists && $remote_ID > 0) {
				$bat_exists = $wpdb->get_row("select * from {$table} where mid = '{$remote_ID}' ", ARRAY_A);
			}
			$mid = 0;
			$had_batch_id = false;
			if ($bat_exists && $bat_exists['mid'] > 0) {
				$mid =  $bat_exists['mid'];
				if (!empty($bat_exists['batch_id'])) {
					$had_batch_id = true;
				}

				$is_resend = isset($data->is_resend) ? (int)$data->is_resend : 0;
				//check rs
				$has_rs = $wpdb->get_var("select id from {$report_tbl} where patient_id = '{$bat['assen']}' and estatus = 'C' ");
				if ($has_rs && !$is_resend) {
					$return['ID'] = $mid;
					$return['assen'] = $bat['assen'];
					$return['msg'] = 'Req completed';

					return $return;
				}
			}


			//find lab
			if ($lab_id > 0) {
				$bat['lab_id'] = $lab_id;
				$data->lab = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$lab_id}");
			} else {
				$lab = (string) $data->lab;
				$lab = trim($lab);
				if (!empty($lab)) {
					$sql = $wpdb->prepare("select lab_ID from ci_labs where lab_name = %s or lab_dba = %s or lab_dba_init = %s", $lab, $lab, $lab);
					$bat['lab_id'] = $wpdb->get_var($sql);
				}

				if (empty($bat['lab_id'])) {
					$bat['lab_id'] = $wpdb->get_var("select lab_ID from ci_labs where lab_is_ref = 0 order by lab_ID asc");
				}
			}

			//ref lab
			if ($ref_lab_id > 0) {
				$bat['ref_lab_ID'] = $ref_lab_id;
				$return['ref_lab_ID'] = $ref_lab_id;
			} else if (isset($data->ref_lab) && !empty($data->ref_lab)) {
				$ref_lab = (string) $data->ref_lab;
				if (!empty($ref_lab)) {
					$ref_lab_id = $wpdb->get_var("select lab_ID from ci_labs 
				                where lab_dba_init = '{$ref_lab}'");
					if ($ref_lab_id > 0) {
						$bat['ref_lab_ID'] = $ref_lab_id;
					}
				}
				//$return['ref_lab'] = $ref_lab;
				//$return['ref_lab_ID'] = $ref_lab_id;
			} else {

				//$return['ref_lab'] = 'none';
			}

			//check collector
			if (isset($data->collector)) {
				$col = array();
				list($col_last, $col_first) = explode(',', (string)$data->collector);
				$col['col_last'] = (string)$col_last;
				$col['col_first'] = (string)$col_first;
				$col['col_phone'] = (string)$data->col_phone;

				if (!empty($col['col_last']) || !empty($col['col_phone'])) {
					//search col in db
					if ($bat_exists && $bat_exists['col_ID'] > 0) {
						$bat['col_ID'] = $bat_exists['col_ID'];
					} else {
						$sql = $wpdb->prepare(
							"select col_ID from ci_collector where col_first=%s and col_last=%s and col_phone=%s",
							$col['col_first'],
							$col['col_last'],
							$col['col_phone']
						);
						$bat['col_ID'] = $wpdb->get_var($sql);
					}

					if ($bat['col_ID'] == 0 && !empty($col['col_last'])) {
						//need insert col
						$rs_col = $wpdb->insert('ci_collector', $col);
						$bat['col_ID'] = $wpdb->insert_id;
						if (!$rs_col) {
							$log['col'] = $wpdb->last_error;
						}
					}
				}
			}

			//check clinic


			if (isset($data->clinic_detail)) {
				$clinic_keys = array(
					'cID',
					'cli_lab-ID',
					'cli_rep-ID',
					'cli_group_ID',
					'cli_marketing_group_ID',
					'cli_distributor_ID',
					'cli_import_ID',
					'cli_ref_lab_ID',
					'office_managers'
				);
				$update_clinic = array();
				foreach ((array) $data->clinic_detail as $k => $vl) {
					$vl = (string) $vl;
					$vl = trim($vl);
					if (!in_array($k, $clinic_keys) && !empty($vl)) {
						$update_clinic[$k] = $vl;
					}
				}
				if (empty($bat['cli_ID'])) {
					$bat['cli_ID'] = $wpdb->get_var("select cID from ci_clinic where cli_clinic='{$update_clinic['cli_clinic']}'");
				}
				if (!empty($update_clinic) && $bat['cli_ID'] > 0) {
					$update_clinic['cli_lab-ID'] = $bat['lab_id'];
					$wpdb->update('ci_clinic', $update_clinic, array('cID' => $bat['cli_ID']));
				}

				if (isset($data->office_managers)) {

					foreach ($data->office_managers as $ma) {
						$ma_name = (string)$ma->name;
						$ma_email = (string)$ma->email;

						if (empty($ma_name) && empty($ma_email)) {
							continue;
						}

						$exist = $wpdb->get_var("select ma_ID from ci_clinic_manager 
											where ma_cli_ID = {$bat['cli_ID']}
											and ma_manager = '{$ma_name}'
											and ma_email = '{$ma_email}' ");
						if (!$exist) {
							$wpdb->insert('ci_clinic_manager', array(
								'ma_cli_ID' => $bat['cli_ID'],
								'ma_manager' => $ma_name,
								'ma_email' => $ma_email,
							));
						}
					}
				}
			} else if (isset($data->clinic)) {
				if ($bat['cli_ID'] == 0 && !empty($clinic)) {
					$cli = array(
						'cli_clinic' => $clinic,
						'cli_lab-ID' => $bat['lab_id']
					);
					$rs_cli = $wpdb->insert('ci_clinic', $cli);
					$bat['cli_ID'] = $wpdb->insert_id;
					if (!$rs_cli) {
						$log['cli'] = $wpdb->last_error;
					}
				}
				if ($bat['col_ID'] > 0 && $bat['cli_ID'] > 0) {
					$wpdb->insert('ci_clinic_collector', array('col_ID' => $bat['col_ID'], 'cli_ID' => $bat['cli_ID']));
				}
			}

			//check doctor
			if (isset($data->doctor)) {
				$doctor = (string) $data->doctor;
				$doctor = trim($doctor);
				if (!empty($doctor)) {
					$doc = array();
					if (strpos($doctor, ',') > 0) {
						list($doc['doc_last'], $doc['doc_first']) = explode(',', $doctor);
					} else {
						list($doc['doc_first'], $doc['doc_last']) = explode(' ', $doctor);
					}
					$doc['doc_last'] = trim($doc['doc_last']);
					$doc['doc_first'] = trim($doc['doc_first']);
					$doc['doc_phone'] = (string) $data->doctor_phone;
					if (isset($data->doctor_npi)) {
						$doc['doc_npi'] = (string)$data->doctor_npi;
					}

					$doc['doc_phone'] = str_replace(')', '-', $doc['doc_phone']);
					$doc['doc_phone'] = str_replace(array('(', ')', ' '), '', $doc['doc_phone']);


					if (isset($data->doctor_detail)) {
						$doctor_keys_ex = array('doc_ID', 'cli_ID', 'doc_import_ID');
						foreach ((array) $data->doctor_detail as $k => $vl) {
							$vl = (string) $vl;
							$vl = trim($vl);
							if (!in_array($k, $doctor_keys_ex) && !empty($vl)) {
								$doc[$k] = $vl;
							}
						}
					}

					$sql = $wpdb->prepare("select doc.* 
                                    from ci_doc doc 
                                    join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID 
                                    where doc.doc_first = %s 
                                    and doc.doc_last=%s", $doc['doc_first'], $doc['doc_last']);

					if (isset($doc['doc_npi']) && !empty($doc['doc_npi'])) {
						$sql .= " and doc.doc_npi = '{$doc['doc_npi']}' ";
					}

					$doc_obj = $wpdb->get_row($sql);

					if (!$doc_obj) {
						$doc['cli_ID'] = $bat['cli_ID'];
						$rs_doc = $wpdb->insert('ci_doc', $doc);
						$bat['doc_ID'] = $wpdb->insert_id;

						if ($bat['doc_ID'] == 0) {
							$log['doc'] = $wpdb->last_error;
						} else {
							$wpdb->insert('ci_clinic_doc', array('doc_ID' => $bat['doc_ID'], 'cli_ID' => $bat['cli_ID']));
						}
					} else {
						$bat['doc_ID'] = $doc_obj->doc_ID;
					}
				}
			}

			if (empty($bat['cli_ID']) && $this->default_cli_ID > 0) {
				$bat['cli_ID'] = $this->default_cli_ID;
				$bat['requesting_practice'] = $wpdb->get_var("select cli_clinic from ci_clinic where cID = {$this->default_cli_ID}");
			}

			if (empty($bat['doc_ID']) && $this->default_doc_ID > 0) {
				$bat['doc_ID'] = $this->default_doc_ID;
				$doctor1 = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->default_doc_ID}");
				if ($doctor1) {
					$bat['requesting_physician'] = $doctor1->doc_first . ' ' . $doctor1->doc_last;
				}
			}


			//check patient
			if (isset($data->patient)) {
				$pa_keys = array(
					'pa_first',
					'pa_last',
					'pa_middle',
					'pa_dob',
					'pa_phone',
					'pa_gender',
					'pa_address',
					'pa_city',
					'pa_state',
					'pa_zip',
					'pa_ssn',
					'pa_apt',
					'pa_mrn',
					'pa_email',
					'pa_race',
					'pa_ethnicity'
				);

				$pa = array();
				foreach ((array) $data->patient as $k => $vl) {
					$vl = (string) $vl;
					$vl = trim($vl);
					if (in_array($k, $pa_keys) && !empty($vl)) {
						$pa[$k] = $vl;
					}
				}

				if (!isset($data->patient->pa_mrn) && isset($data->patient->med_number)) {
					$pa['pa_mrn'] = (string) $data->patient->med_number;
				}

				if (isset($pa['pa_dob'])) {
					$pa['pa_dob'] = $this->format_date($pa['pa_dob']);
				}
				if (isset($pa['pa_dob'])) {
					$bat['dob'] = $pa['pa_dob'];
				}

				$pa['pa_gender'] = in_array(strtolower($pa['pa_gender']), array('m', 'male')) ? 'M' : 'F';

				$sql = $wpdb->prepare("select * from ci_patients where pa_first=%s and pa_last = %s ", $pa['pa_first'], $pa['pa_last']);

				if (!empty($pa['pa_ssn'])) {
					$ssn2 = str_replace('-', '', $pa['pa_ssn']);
					$sql .= $wpdb->prepare(" and (pa_ssn = %s or pa_ssn = %s)", $pa['pa_ssn'], $ssn2);
				}
				if (!empty($pa['pa_dob'])) {
					$sql .= $wpdb->prepare(" and pa_dob=%s ", $pa['pa_dob']);
				}

				$patient = $wpdb->get_row($sql, ARRAY_A);
				if (!$patient) {

					$pa['pa_created'] = date_i18n('Y-m-d H:i:s');

					$rs_pa = $wpdb->insert('ci_patients', $pa);
					$bat['patient_id'] = $wpdb->insert_id;
					if ($bat['patient_id'] == 0) {
						$log['pa'] = $wpdb->last_error;
						$log['pa_data'] = $pa;
					}
				} else {
					$bat['patient_id'] = $patient['pa_ID'];
					foreach ($patient as $k => $vl) {
						if (!empty($pa[$k])) {
							$patient[$k] = $pa[$k];
						}
					}
					trpdd_update_patient($patient, $patient['pa_ID']);
				}
				$bat['patient_name'] = tr_trim_name($pa['pa_last'] . ', ' . $pa['pa_first']);
			}

			//check AOE Answers
			if (isset($data->aoe_answers)) {

				foreach ((array) $data->aoe_answers as $ansk => $anstxt) {
					$ansk = strtolower($ansk);
					if (isset($batch_cols[$ansk])) {
						$bat[$batch_cols[$ansk]] = $anstxt;
					}
				}
			}

			//check primary_insurance
			if (isset($data->primary_insurance)) {
				$ins_fields = $wpdb->get_col("show columns from `ci_insurances`");

				$ins = (array) $data->primary_insurance;
				foreach ($ins as $k => $vl) {
					$ins[$k] = (string) $vl;
					$ins[$k] = trim($ins[$k]);
				}

				if (empty($ins['ins_company'])) {
					$ins['ins_company'] = 'No Insurance - Cash';
				}
				$relations = array('self', 'parent', 'spouse');
				if (empty($ins['ins_relation']) || !in_array(strtolower($ins['ins_relation']), $relations)) {
					$ins['ins_relation'] = 'Self';
				}

				if ($bat_exists && $bat_exists['insurance1'] > 0) {
					$bat['insurance1'] = $bat_exists['insurance1'];
				} else {
					$sql = $wpdb->prepare(
						"select ins_ID from ci_insurances 
				            where ins_first=%s 
				            and ins_last = %s 
				            and ins_company=%s",
						$ins['ins_first'],
						$ins['ins_last'],
						$ins['ins_company']
					);
					$bat['insurance1'] = $wpdb->get_var($sql);
				}

				if (count($ins_fields) > 0) {
					foreach ($ins as $k => $vl) {
						if (!in_array($k, $ins_fields)) {
							unset($ins[$k]);
						}
					}
				}

				if ((!empty($ins['ins_last']) || !empty($ins['ins_company']))) {
					if ($bat['insurance1'] == 0) {
						$wpdb->insert('ci_insurances', $ins);
						$bat['insurance1'] = $wpdb->insert_id;
					} else {
						$wpdb->update('ci_insurances', $ins, array('ins_ID' => $bat['insurance1']));
					}
				}

				if (!empty($ins['ins_company'])) {
					$bat['insurance'] = $ins['ins_company'] . '';
				} else {
					$bat['insurance'] = (string) $data->insurance;
				}
			}

			//check second_insurance
			if (isset($data->second_insurance)) {
				$ins = (array) $data->second_insurance;
				foreach ($ins as $k => $vl) {
					$ins[$k] = (string) $vl;
				}

				if ($bat_exists && $bat_exists['insurance2'] > 0) {
					$bat['insurance2'] = $bat_exists['insurance2'];
				} else {
					$sql = $wpdb->prepare(
						"select ins_ID from ci_insurances where ins_first=%s and ins_last = %s and ins_company=%s",
						$ins['ins_first'],
						$ins['ins_last'],
						$ins['ins_company']
					);
					$bat['insurance2'] = $wpdb->get_var($sql);
				}

				if ($bat['insurance2'] == 0 && !empty($ins['ins_last'])) {
					$wpdb->insert('ci_insurances', $ins);
					$bat['insurance2'] = $wpdb->insert_id;
				}
			}


			if (isset($data->panel_tests_requested)) {
				$test_names = array();
				$main_names = array();
				$abrev_names = array();
				$main_namesby_groups = array();
				$abrev_namesby_groups = array();
				$all_groups = array();
				$all_group_ids = array();
				$test_in_groups = array();
				$group_parents = array();
				$rs = $wpdb->get_results("select tg_ID, 
												tg_name,
												tg_short_name,
												tg_cpt_code,
												tg_only_group,
												tg_parent
					                    from ci_test_groups 
					                    where tg_type='{$btype}'
					                    and (tg_show_req =1 
					                         or (tg_parent in (select tg_ID from ci_test_groups 
					                                where tg_type='{$btype}' and tg_parent = 0 and tg_show_req =1 )
					                            )
					                         ) 
										order by tg_parent asc");

				foreach ($rs as $r) {
					$r->tg_name = strtoupper($r->tg_name);

					$all_group_ids[] = $r->tg_ID;

					if ($r->tg_parent > 0) {
						$group_parents[$r->tg_ID] = $r->tg_parent;
					}

					if (isset($all_groups[$r->tg_name])) {
						continue;
					}
					$all_groups[$r->tg_name] = $r;
					if (!empty($r->tg_short_name)) {
						$r->tg_short_name = strtoupper($r->tg_short_name);
						$all_groups[$r->tg_short_name] = $r;
					}
					if (!empty($r->tg_cpt_code)) {
						$r->tg_cpt_code = strtoupper($r->tg_cpt_code);
						$all_groups[$r->tg_cpt_code] = $r;
					}
				}

				//$return['group_parents'] = json_encode($group_parents);


				$all_group_ids = implode(',', $all_group_ids);
				$tes = $wpdb->get_results("select te_ID,
								te_name, 
								te_abrev, 
								te_abrev2, 
								te_loinc_code, 
								te_group_id
								from ci_test_panels 
								where te_type ='{$btype}' 
								and te_group_id in ({$all_group_ids})
								and te_in_req = 1 ");


				foreach ($tes as $t) {
					$grid = isset($group_parents[$t->te_group_id]) ? $group_parents[$t->te_group_id] : $t->te_group_id;
					if (!isset($main_namesby_groups[$grid])) {
						$main_namesby_groups[$grid] = array();
					}
					if (!isset($abrev_namesby_groups[$grid])) {
						$abrev_namesby_groups[$grid] = array();
					}
					if (!isset($test_in_groups[$grid])) {
						$test_in_groups[$grid] = array();
					}


					$t->te_name = strtoupper($t->te_name);
					if (!empty($t->te_abrev)) {
						$t->te_abrev = strtoupper($t->te_abrev);
						$test_names[$t->te_abrev] = $t->te_name;

						$abrev_names[$t->te_ID] = $t->te_abrev;

						$abrev_namesby_groups[$grid][$t->te_ID] = $t->te_abrev;
					}

					if (!empty($t->te_abrev2)) {
						$t->te_abrev2 = strtoupper($t->te_abrev2);
						$test_names[$t->te_abrev2] = $t->te_name;

						$abrev_names[$t->te_ID] = $t->te_abrev2;

						$abrev_namesby_groups[$grid][$t->te_ID] = $t->te_abrev2;
					}

					if (!empty($t->te_loinc_code)) {
						$t->te_loinc_code = strtoupper($t->te_loinc_code);
						$test_names[$t->te_loinc_code] = $t->te_name;
					}
					$main_names[$t->te_ID] = $t->te_name;
					$main_namesby_groups[$grid][$t->te_ID] = $t->te_name;


					$test_in_groups[$grid][] = $t->te_ID;
				}


				$tests = explode(',', $data->panel_tests_requested);
				$abrevs = explode(',', isset($data->abrevs) ? $data->abrevs : '');

				//	$return['panel_tests_requested'] = json_encode($tests);
				//$return['abrevs'] = json_encode($abrevs);
				//	$return['main_namesby_groups'] = json_encode($main_namesby_groups);

				$new_tests = array();
				$current_group_id = 0;

				foreach ($tests as $tnum => $t) {
					$t = strtoupper(trim($t));
					if (empty($t)) continue;

					$abev = isset($abrevs[$tnum]) ? $abrevs[$tnum] : '';

					$in_mainname = in_array($t, $main_names);
					$maingroups = isset($main_namesby_groups[$current_group_id]) ? $main_namesby_groups[$current_group_id] : array();
					if ($in_mainname && $current_group_id > 0 && in_array($t, $maingroups)) {
						$in_mainname = true;
					}

					if (isset($test_names[$t]) && !empty($test_names[$t]) && !$in_mainname) {
						$t = $test_names[$t];
					}

					if ($btype == 'tox') {
						$tox_test_name = '';
						if (in_array($t, $test_names)) {
							$tox_test_name = $t;
						} else if (isset($test_names[$t])) {
							$tox_test_name = $test_names[$t];
						}
						if (!empty($tox_test_name) && !in_array($tox_test_name, $new_tests)) {
							//only for tox
							$new_tests[] = $tox_test_name;
						} else {
							$return['test_not_found'][] = $t;
						}
					} else {
						$tid = 0;
						if ($current_group_id > 0) {

							//$return['current_group_id'] = $current_group_id;
							$tid = array_search($t, $maingroups);


							if ($tid == 0 && !empty($abev)) {
								$maingroups = isset($abrev_namesby_groups[$current_group_id]) ? $abrev_namesby_groups[$current_group_id] : array();
								$tid = array_search($abev, $maingroups);
							}

							//$return['tid1'] = '('.$tid.')';
						}
						if ($tid == 0) {
							$tid = array_search($t, $main_names);
							if ($tid == 0 && !empty($abev)) {
								$tid = array_search($abev, $abrev_names);
							}
							//$return['tid2'] = '('.$tid.')';
						}

						if ($tid > 0 && !in_array($tid, $new_tests)) {
							$new_tests[] = $tid;
						}


						if (isset($all_groups[$t]) || (!empty($abev) && isset($all_groups[$abev]))) {
							$group = isset($all_groups[$t]) ? $all_groups[$t] : $all_groups[$abev];

							$current_group_id = $group->tg_ID;
							//$return['ccc'] = $current_group_id;

							$groupstr = 'GROUP_' . $group->tg_ID;
							if (!in_array($groupstr, $new_tests)) {
								$new_tests[] = $groupstr;
							}

							if ($group->tg_only_group) {
								$childgroups = $wpdb->get_col("select tg_ID from ci_test_groups
																where tg_parent = {$group->tg_ID}
																and tg_show_req = 1");
								if (count($childgroups) > 0) {
									foreach ($childgroups as $cgid) {
										$groupstr = 'GROUP_' . $cgid;
										if (!in_array($groupstr, $new_tests)) {
											$new_tests[] = $groupstr;
										}
									}
								}
								$ts = $wpdb->get_col("select te.te_ID 
														from ci_test_panels te
														join ci_test_groups g ON g.tg_ID = te.te_group_id
														where  
														te.te_in_req = 1
														and (g.tg_ID = {$group->tg_ID} or g.tg_parent = {$group->tg_ID})");
								if (count($ts) > 0) {
									$new_tests = array_merge($new_tests, $ts);
								}
							}
						}
					}
				}

				array_unique($new_tests);

				$data->panel_tests_requested = implode(',', $new_tests);
				//$return['tests'] = (string)$data->panel_tests_requested;
				//$return['testsarr'] = $new_tests;
			}


			//json medication_or_drug
			$data->medication_or_drug = (string) $data->medication_or_drug;
			$medication_or_drug = explode(',', $data->medication_or_drug);
			$new_medicationdrug = array();
			foreach ($medication_or_drug as $vl) {
				$vl = trim($vl);
				$checked = stripos($vl, '+') === 0 ? 1 : 0;
				$vl = substr($vl, 1);
				$new_medicationdrug[$vl] = $checked;
			}
			$data->medication_or_drug = maybe_serialize($new_medicationdrug);


			foreach ($bat_keys as $k => $vl) {
				if (isset($data->{$vl})) {
					$dt = (string)$data->{$vl};
					if ($k == 'current_medication') {
						$dt = str_replace(', ', ',', $dt);
					}
					if (!empty($dt) && empty($bat[$k])) {
						$bat[$k] = $dt;
					}
				} else if ($btype == 'req' && isset($data->logistics) && isset($data->logistics->{$vl})) {
					$dt = (string)$data->logistics->{$vl};
					if (!empty($dt) && empty($bat[$k])) {
						$bat[$k] = $dt;
					}
				}
			}

			if (isset($data->poc_performed) && (string)$data->poc_performed == '1') {
				$bat['poc_perform'] = '1';
			}


			//fix data

			if ($had_batch_id) {
				unset($bat['batch_id']);
				if ($bat_exists['position'] > 0) {
					unset($bat['position']);
				}
				if (!empty($bat_exists['spec_received']) && $bat_exists['spec_received'] != '0000-00-00 00:00:00') {
					unset($bat['spec_received']);
				}
			} else if (isset($bat['batch_id']) && $bat['batch_id'] > 0) {
				$wpdb->query("insert IGNORE into ci_batch_ids set batch_id = '{$bat['batch_id']}' ");
			}

			if (isset($bat['dob'])) {
				$bat['dob']             = $this->format_date($bat['dob']);
			}

			if (isset($bat['spec_received'])) {
				$bat['spec_received']   = $this->format_date($bat['spec_received']);
			}

			if (isset($bat['doc'])) {
				$bat['doc']             = $this->format_date($bat['doc']);
			} else if ($btype == 'req' && isset($bat['collection_datetime'])) {
				$bat['doc'] = $bat['collection_datetime'];
			}

			$bat['is_dna']          = tr_pdd_get_numtype($btype);

			if (isset($data->fasting)) {
				$fastingstr = (string)$data->fasting;
				$bat['fasting'] = stripos($fastingstr, 'yes') !== false || stripos($fastingstr, '1') !== false ? 1 : 0;
			}


			//end fix

			$signature_id = $bat['assen'];

			//parse images
			$patient_sign = tr_save_signature($signature_id . '_patient', (string) $data->patient_signature);

			if ($patient_sign) {
				$bat['patient_signature'] = $patient_sign;
			}

			$provider_sign = tr_save_signature($signature_id . '_provider', (string) $data->provider_signature);
			if ($provider_sign) {
				$bat['provider_signature'] = $provider_sign;
			}

			$ins_front = tr_save_card_file($signature_id . '_ins', (string) $data->ins_card_front);
			if ($ins_front) {
				$bat['ins_card_front'] = $ins_front;
			}

			$ins_back = tr_save_card_file($signature_id . '_ins_back', (string) $data->ins_card_back);
			if ($ins_back) {
				$bat['ins_card_back'] = $ins_back;
			}

			$id_front = tr_save_card_file($signature_id . '_id', (string) $data->patient_id_front);
			if ($id_front) {
				$bat['patient_id_front'] = $id_front;
			}

			$id_back = tr_save_card_file($signature_id . '_id_back', (string) $data->patient_id_back);
			if ($id_back) {
				$bat['patient_id_back'] = $id_back;
			}

			if ($paid_id < 0 && !isset($bat['paid'])) {
				$bat['paid'] = $paid_id;
			}

			$result_pdf = '';
			if (isset($data->result_pdf)) {
				$result_pdf = tr_save_result_upload($signature_id, (string) $data->result_pdf);
			}

			if ($result_pdf) {
				$bat['result_pdf'] = $result_pdf;
			} else if (isset($data->result_pdfs)) {
				$result_pdfs = array();
				foreach ((array) $data->result_pdfs as $vl) {

					if (!empty($vl)) {
						$result_pdf = basename($vl);
						$result_pdf_file = $dir['path'] . $result_pdf;
						$pdf_data = file_get_contents($vl);
						if (!empty($pdf_data) && file_put_contents($result_pdf_file, $pdf_data)) {
							$result_pdfs[] = $result_pdf;
						}
					}
				}
				$bat['result_pdf'] = implode(',', $result_pdfs);
			}

			if (isset($data->original_req)) {
				$original_req = (string)$data->original_req;
				if (!empty($original_req)) {
					$original_req_pdf = basename($original_req);
					$original_req_file = $dir['path'] . $original_req_pdf;
					$pdf_data = @file_get_contents($original_req);
					if (!empty($pdf_data) && file_put_contents($original_req_file, $pdf_data)) {
						$bat['original_req'] = $original_req_pdf;
					}
				}
			}



			//check exists assen
			if ($mid > 0) {
				$wpdb->update($table, $bat, array('mid' => $mid));
				$return['note'] = 'updated';

				trpdd_log_user(-24, 'apiserver_u', $bat['assen'], $mid, $btype);
			} else {

				if ($btype == 'tox' && empty($bat['custom_profile'])) {
					$bat['custom_profile'] = 'Do Not Use Custom Profile';
				}

				$bat['batch_created'] = date_i18n('Y-m-d H:i:s');

				$wpdb->insert($table, $bat);
				$mid = $wpdb->insert_id;

				if ($mid > 0) {
					trpdd_log_user(-24, 'apiserver_i', $bat['assen'], $mid, $btype);
				}
			}


			if ($mid == 0) {
				$return['status'] = 'ERROR';
				$return['error_code'] = '08';
				$return['msg'] = 'Error while add to db. plz let Admin know this error_code';
				$log['bat'] = $wpdb->last_error;
			} else {

				//scrreening test auto add RS
				//file_put_contents(WP_CONTENT_DIR . '/log.txt', print_r($data->screenings, true), FILE_APPEND);


				if (isset($args['dont_reflex']) && $args['dont_reflex']) {
					trpdd_update_batch_meta($mid, 'tox', '_dont_reflex', 1);
				}

				if (isset($args['readonly_screening']) && $args['readonly_screening']) {
					trpdd_update_batch_meta($mid, 'tox', '_readonly_screening', 1);
				}

				if ($btype == 'tox' && isset($data->screenings)) {

					$wpdb->query("update ci_result_test_speciments set checked = 0 where mid = {$mid} ");

					foreach ($data->screenings->test as $scr_arr) {
						$scr_arr = (array)$scr_arr;

						$testname = (string) $scr_arr['name'];
						$testname = trim($testname);
						$abreviation = (string) $scr_arr['abreviation'];
						$abreviation = trim($abreviation);
						$loinc_code = (string) $scr_arr['loinc_code'];
						$loinc_code = trim($loinc_code);




						$wherename = "u_name = '{$testname}' ";
						if (!empty($abreviation)) {
							$wherename .= " or u_abreviation = '{$abreviation}' ";
						}
						if (!empty($loinc_code)) {
							$wherename .= " or u_loinc_code = '{$loinc_code}' ";
						}

						$where_test_screening = " u_showinreq = 1 and (" . $wherename . ")";
						if (!isset($args['readonly_screening']) || !$args['readonly_screening']) {
							$where_test_screening .= " and u_status = 1 ";
						}

						$tid = $wpdb->get_var("select u_ID from ci_urine_validity where {$where_test_screening}");

						if ($tid == 0 && isset($args['readonly_screening']) && $args['readonly_screening']) {
							//add test if not exist and readonly_screening is on
							$wpdb->insert('ci_urine_validity', array(
								'u_name' => $testname,
								'u_abreviation' => $abreviation,
								'u_loinc_code' => $loinc_code,
								'u_status' => 0,
								'u_showinreq' => 1,
								'u_neg_pos' => 1
							));
							$tid = $wpdb->insert_id;
						}

						if ($tid > 0) {
							$exists = $wpdb->get_var("select mid from ci_result_test_speciments where mid = {$mid} and testid={$tid} ");

							$update = array(
								'mid' => $mid,
								'testid' => $tid,
								'checked' => 1
							);

							if (isset($scr_arr['conc'])) {
								$update['conc'] = $scr_arr['conc'];
							}
							if (isset($scr_arr['outcome'])) {
								$update['outcome'] = $scr_arr['outcome'];
							}
							if (isset($scr_arr['comments'])) {
								$update['comments'] = $scr_arr['comments'];
							}
							if ($exists) {
								$wpdb->update('ci_result_test_speciments', $update, array('mid' => $mid, 'testid' => $tid));
							} else {
								$wpdb->insert('ci_result_test_speciments', $update);
							}
						}

						$return['scr_testname'][] = $testname . ' / ' . $abreviation . ' (ID: ' . $tid . ') ' . ($tid > 0 ? 'added' : 'not found');
					}
				}
				$return['ID'] = $mid;
				$return['assen'] = $bat['assen'];
			}
		}

		if (isset($return['test_not_found'])) {
			$return['test_not_found'] = implode(', ', $return['test_not_found']);
		}
		return $return;
	}
}
