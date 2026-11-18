<?php

include_once('ci_hl7.php');

class CI_Api_Base_SocketHl7 extends CI_Hl7
{
	protected $conn;
	protected $sftp;
	public $order_log_dir 		= WP_CONTENT_DIR . '/log/sockethl7_orders/';
	public $results_log_dir 	= WP_CONTENT_DIR . '/log/sockethl7_results/';
	public $archive_dir = '/archive/';
	public $options;
	public $debug 		= false;
	public $log 		= '';
	public $api_id 		= 0;
	public $log_file_slug = 'sockethl7';
	public $api_key 	= '';

	public $enable_send_orders = false;
	public $enable_send_results = false;
	public $enable_receive_orders = false;
	public $enable_receive_results = false;

	public $test_code_order = '';
	public $test_code_result = '';
	public $group_code_order = '';
	public $group_code_result = '';
	public $clinic_fieldname = '';
	public $lab_msh3 	= '';
	public $lab_msh4 	= '';
	public $lab_msh5 	= '';
	public $lab_msh6 	= '';
	public $msh5_by_reflab = false;

	public $all_btypes = array();

	const VT = 0x0B;
	const FS = 0x1C;
	const CR = 0x0D;

	public function __construct($options)
	{

		$this->options = $options;

		if (empty($this->all_btypes)) {
			$this->all_btypes = tr_pdd_get_batch_types();
		}
	}

	public function cron()
	{
		$this->connect();

		if ($this->enable_send_orders) {
			$this->send_orders();
		} else if ($this->enable_receive_orders) {
			$this->receive_orders();
		}

		if ($this->enable_send_results) {
			$this->send_results();
		} else if ($this->enable_receive_results) {
			$this->receive_results();
		}
	}

	public function sync_reqs()
	{
		if ($this->enable_send_orders) {
			$this->send_orders();
		} else if ($this->enable_receive_orders) {
			$this->receive_orders();
		}
	}

	public function sync_reports()
	{
		if ($this->enable_send_results) {
			$this->send_results();
		} else if ($this->enable_receive_results) {
			$this->receive_results();
		}
	}

	public function rebuild($btype = 'pcr', $mid)
	{
		if ($this->enable_send_orders) {
			$this->send_orders($btype, $mid);
		} else if ($this->enable_send_results) {
			$this->send_results($btype, $mid);
		}
	}


	public function connect()
	{
		if (empty($this->options['port'])) {
			$this->options['port'] = 22;
		}
		$this->conn = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		$result = socket_connect($this->conn, $this->options['host'], $this->options['port']);

		if (!$result) {
			throw new Exception('Unable to connect');
		}
		return true;
	}

	public function uploadFile($local_file)
	{
		$output = file_get_contents($local_file);
		$output = chr(self::VT) . $output . chr(self::FS) . chr(self::CR);
		return socket_write($this->conn, $output);
	}

	public function get_message()
	{
		while (socket_recv($this->conn, $buf, 1, 0) !== false) {

			var_dump($buf);
			break;
		}
	}


	public function send_orders($btype = 'pcr', $mid = 0, $limit = 1)
	{
		global $wpdb;
		$date_timenow = date_i18n('Y-m-d H:i:s');
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$where = '';
		if ($this->options['cli_id'] > 0) {
			$where .= " and ba.cli_ID in ({$this->options['cli_id']})";
		}
		if ($mid > 0) {
			$where .= "  and ba.mid = {$mid} ";
		} else {
			$where .= " and ba.doc <= '{$date_timenow}' ";
			$where .= " and (api.mo_ID is null or api.mo_sent = 0) ";
		}

		if ($this->options['ref_lab_id'] > 0) {
			$where .= " and ba.ref_lab_ID = {$this->options['ref_lab_id']} ";
		}

		if (!empty($this->options['start_date'])) {
			$where .= " and ba.doc >= '{$this->options['start_date']}' ";
		}


		$where .= " and ba.batch_id > 0 and ba.cli_ID>0 and ba.doc_ID >0 and ba.patient_id > 0 ";

		$sql = "select ba.*,
				cli.*,
				doc.*,
				pa.*,
				ins.*,
				api.mo_ID,
				api.other_info,
				'{$btype}' as btype
                    
            from {$batch_tbl} ba
            JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            JOIn ci_patients pa ON pa.pa_ID = ba.patient_id
            JOIn ci_doc doc ON doc.doc_ID = ba.doc_ID
            JOIn ci_clinic cli ON cli.cID = ba.cli_ID
            left JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
            left join ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = '{$this->api_id}'
                                        and api.mo_btype = '{$btype}'
             where 1
             {$where}
             order by mid asc
			 limit {$limit}";



		$rows = $wpdb->get_results($sql);

		if (isset($_GET['debug'])) {

			echo $wpdb->last_error;
			echo count($rows);
		}

		foreach ($rows as $r) {

			$other_info = !empty($r->other_info) ? json_decode($r->other_info, true) : array();

			$btypenumber = '';
			if ($btype == 'tox') {
				$btypenumber = '01';
			} else if ($btype == 'blood') {
				$btypenumber = '02';
			}
			if ($btype == 'pcr') {
				$btypenumber = '03';
			}
			$orderid = $r->mid . $btypenumber;

			$control_id = time() . $orderid;

			$msh4 = $r->cli_ID;
			$msh5 = $this->lab_msh5;
			if ($this->msh5_by_reflab && $r->ref_lab_ID > 0) {
				$msh5 = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$r->ref_lab_ID} ");
			}
			if (!empty($this->clinic_fieldname)) {
				$msh4 = $r->{$this->clinic_fieldname};
			}

			$hl7_arr = array();
			$hl7_arr['MSH'] = $this->getMSH(
				$control_id,
				$msh4,
				$msh5,
				'ORM',
				'O01',
				'',
			);

			$hl7_arr['PID'] = $this->getPID(
				$r->pa_ID,
				$r->pa_mrn,
				$r->pa_first,
				$r->pa_last,
				$r->pa_ssn,
				$r->pa_gender,
				$r->pa_dob,
				$r->pa_address,
				$r->pa_city,
				$r->pa_state,
				$r->pa_zip,
				$r->pa_phone,
				$r->pa_email,
				''
			);

			$hl7_arr['PV1'] = $this->getPV1($r->doc_npi, $r->doc_first, $r->doc_last, $r->assen);

			$hl7_arr['IN1'] = $this->getIN1(
				$r->ins_member_id,
				$r->ins_company,
				$r->ins_address,
				$r->ins_city,
				$r->ins_state,
				$r->ins_zip,
				$r->ins_phone,
				$r->ins_first,
				$r->ins_last,
				$r->ins_group_id
			);

			$hl7_arr['DG1'] = $this->getDG1($r->dagnostic_codes1, tr_pdd_get_dagnostic_codes($btype));


			$hl7_arr['ORC'] = $this->getORC(
				'NW',
				$orderid,
				$r->assen,
				$r->doc,
				$r->lab_name,
				$r->doc_npi,
				$r->doc_first,
				$r->doc_last
			);

			$group_ids = array();
			$test_ids = array();

			foreach (explode(',', $r->panel_tests_requested) as $t) {
				if (stripos($t, 'group') === false) {
					if ($t > 0) {
						$test_ids[] = $t;
					}
				} else {
					$group_ids[] = str_ireplace('group_', '', $t);
				}
			}

			$test_ids_str = implode(',', $test_ids);
			if (empty($group_ids)) {
				$group_ids = $wpdb->get_col("select te_group_id from ci_test_panels where te_ID in ({$test_ids_str})");
			}

			foreach ($group_ids as $gid) {
				$group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$gid}");


				$group_code = $group->tg_cpt_code;

				if (!empty($this->group_code_order)) {
					$group_code = $group->{$this->group_code_order};
					if (empty($group_code)) {
						continue;
					}
				}


				$hl7_arr[] = $this->getOBR(
					$orderid,
					$r->assen,
					$r->lab_name,
					$r->doc_npi,
					$r->doc_first,
					$r->doc_last,
					$group_code,
					$group->tg_name,
					$r->approve_date,
					$r->result_status,
					$r->mo_peg_ID,
					$r->doc,
					$r->spec_received
				);
			}

			$hl7_data = $this->array_to_hl7($hl7_arr);



			$filename = strtolower($r->assen) . '.hl7';

			if (!file_exists($this->order_log_dir)) {
				wp_mkdir_p($this->order_log_dir);
			}
			$local_file = $this->order_log_dir . '/' . $filename;


			file_put_contents($local_file, $hl7_data);

			if (isset($_GET['act']) && $_GET['act'] == 'rebuild') {
				return true;
			}

			$remote_file = $this->options['orders_folder'] . '/' . $filename;
			$remote_file = str_replace('//', '/', $remote_file);
			try {
				$uprs = $this->uploadFile($local_file, $remote_file);
				if (!$uprs) {
					throw new Exception('Upload file failed');
				}
				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
				}

				if (!isset($other_info['sent'])) {
					$other_info['sent'] = array();
				}
				$other_info['sent'][] = date_i18n('Y-m-d H:i:s');

				$update = array(
					'mo_file_id'    => $this->api_id,
					'mo_mid'        => $r->mid,
					'mo_btype'		=> $btype,
					'mo_control_ID' => $control_id . '',
					'mo_sent' => 1,
					'other_info' => json_encode($other_info)
				);


				if ($r->mo_ID > 0) {
					$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $r->mo_ID));
				} else {
					$r = $wpdb->insert('ci_sync_api_orders', $update);
				}
			} catch (Exception $e) {
				var_dump($e);
				continue;
			}
		}
	}



	public function send_results($btype = 'pcr', $mid = 0, $limit = 100)
	{
		global $wpdb;

		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$result_tbl = tr_pdd_get_table_result_by_btype($btype);


		$pos_neg = array(
			'' => '',
			'NEG' => 'Not Detected',
			'POS' => 'Detected',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'PRP' => 'Positive',
			'INV' => 'Invalid',
			'NGE' => 'Negative',
			'REJ' => 'Rejected'
		);

		if (!file_exists($this->results_log_dir)) {
			mkdir($this->results_log_dir);
		}

		if (empty($limit)) $limit = 100;



		$select_fields = '';
		$joins = "";
		$where = "";
		if ($this->enable_receive_orders) {
			$select_fields = ', mf.m_ID,mf.m_file ';
			$joins = " 
			JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
			JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='{$this->log_file_slug}' ";
		} else {
			$joins = " LEFT JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid  and mo.mo_file_id = {$this->api_id}";
		}

		if (!empty($this->options['start_date'])) {
			$where .= " and re.approve_date >= '{$this->options['start_date']}' ";
		}


		$sql = "select re.id,
					ba.mid,
					ba.batch_id,
					ba.assen,
					ba.patient_name,
					ba.doc,
					ba.spec_received,
					ba.panel_tests_requested,
					ba.oldreq,
					ba.batch_status,
					
					pa.pa_first,
					pa.pa_last,
					pa.pa_address,
					pa.pa_city,
					pa.pa_state,
					pa.pa_zip,
					pa.pa_dob,
					pa.pa_ssn,
					pa.pa_gender,
					pa.pa_phone,
					pa.pa_email,
					
					re.approve_date,
					re.created_date,
					re.instrument,
					
					mo.mo_ID,
					mo.mo_set_ID,
					mo.mo_test_ID,
					mo.mo_control_ID,
					mo.mo_patient_ID,
					mo.mo_peg_ID,
					mo.other_info,

					

					lab.lab_dba_init,
					lab.lab_dba,
					lab.lab_name,
					lab.lab_clia,
					lab.lab_address,
					lab.lab_city,
					lab.lab_state,
					lab.lab_zip,
					
					cli.cli_clinic,
					cli.cli_street,
					cli.cli_city,
					cli.cli_state,
					cli.cli_zip,
					cli.`cli_om-phone` as cli_phone,
					
					doc.doc_first,
					doc.doc_last,
					doc.doc_npi

					{$select_fields}
					
				FROM {$batch_tbl} ba
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				left JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				{$joins}
				where re.estatus = 'C'
				{$where}
				";
		if ($mid > 0) {
			$sql .= " and ba.mid = {$mid} ";
		} else {
			$sql .= " and (mo.mo_sent in (0,2) or mo.mo_sent is null) ";
		}
		$sql .= " limit {$limit} ";
		$rs = $wpdb->get_results($sql);

		echo $wpdb->last_error;

		if ($this->debug && count($rs) == 0) {
			if (!empty($wpdb->last_error)) {
				$this->log .= "error: " . $wpdb->last_error;
			}

			$this->log .= "Not found results!";
		}

		$this->log .= "Found " . count($rs) . " results to send.\n";


		foreach ($rs as $r) {
			$other_info = json_decode($r->other_info, true);
			if (!isset($other_info['MSH4']) || empty($other_info['MSH4'])) {
				$other_info['MSH4'] = $this->lab_msh4;
			}

			if (!isset($other_info['MSH3']) || empty($other_info['MSH3'])) {
				$other_info['MSH3'] = $this->lab_msh3;
			}
			if (!isset($other_info['MSH5']) || empty($other_info['MSH5'])) {
				$other_info['MSH5'] = $this->lab_msh5;
			}

			$filename = basename($r->m_file);
			if (empty($filename)) {
				$filename = $r->assen . '.hl7';
			} else {
				$filename = str_replace('.json', '.hl7', $filename);
				if (empty($filename)) {
					$filename = $r->assen . '.hl7';
				}
			}

			$hl7_file = $this->results_log_dir . $filename;
			$batch_status = $r->batch_status;

			$r->result_status = 'F';
			if ($r->mo_ID > 0) {
				$r->result_status = 'C';
			}

			if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
				$r->result_status = 'X';
			}

			$assen 				= $r->assen;

			if (empty($r->mo_test_ID)) {
				$r->mo_test_ID = $r->oldreq;
			}
			$test_groups		= array();
			$group_ids 			= array();


			if (empty($r->lab_dba)) {
				$r->lab_dba = $r->lab_name;
			}

			if (empty($r->spec_received)) {
				$r->spec_received = $r->doc;
			}
			$hl7_arr = array();

			$hl7_arr['MSH'] = $this->getMSH($r->mo_control_ID, $other_info['MSH3'], $other_info['MSH4'], 'ORU', 'R01', 'ORU_R01', 15, $other_info['MSH5'], $this->lab_msh6);

			$hl7_arr['PID'] = $this->getPID(
				$r->mo_set_ID,
				$r->mo_patient_ID,
				$r->pa_first,
				$r->pa_last,
				$r->pa_ssn,
				$r->pa_gender,
				$r->pa_dob,
				$r->pa_address,
				$r->pa_city,
				$r->pa_state,
				$r->pa_zip,
				$r->pa_phone,
				$r->pa_email
			);

			$hl7_arr['ORC'] = $this->getORC(
				$r->mo_test_ID,
				$assen,
				$r->doc,
				$r->lab_name,
				$r->doc_npi,
				$r->doc_first,
				$r->doc_last
			);


			foreach (explode(',', $r->panel_tests_requested) as $tid) {
				if (stripos($tid, 'group_') !== false) {
					$group_id = str_ireplace('GROUP_', '', $tid);
					if ($group_id > 0 && !in_array($group_id, $group_ids)) {
						$group_ids[] = $group_id;
					}
				} else {
					$test_ids[] = $tid;
				}
			}
			$group_ids_str = implode(',', $group_ids);
			$rsgropups = $wpdb->get_results("select * from ci_test_groups where tg_ID in ({$group_ids_str}) ");


			foreach ($rsgropups as $group) {


				$groupcode = (!empty($group->tg_cpt_code) ? $group->tg_cpt_code : $group->tg_short_name);
				$hl7_arr[] = $this->getOBR(
					$r->mo_test_ID . '^LAB',
					$assen,
					$groupcode . '^LN', //$r->lab_name,
					$r->doc_npi,
					$r->doc_first,
					$r->doc_last,
					!empty($group->tg_cpt_code) ? $group->tg_cpt_code : $group->tg_short_name,
					$group->tg_name,
					$r->approve_date,
					$r->result_status,
					$r->mo_peg_ID,
					$r->doc,
					$r->spec_received
				);

				$group_testids = $wpdb->get_col("select te_ID from ci_test_panels te 
					JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
					where g.tg_ID = {$group->tg_ID} or g.tg_parent = {$group->tg_ID} ");

				if (!$group->tg_only_group) {
					foreach ($group_testids as $kt => $tid) {
						if (!in_array($tid, $test_ids)) {
							unset($group_testids[$kt]);
						}
					}
				}

				if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
					$hl7_arr['OBX'][] = $this->getOBX(
						$r->lab_clia,
						$r->lab_name,
						$groupcode,
						$groupcode,
						$pos_neg['REJ'],
						$r->result_status,
						'',
						'',
						'',
						'',
						'',
					);

					$nte_msg = 'Rejected';
					$batch_statuses = tr_pdd_get_batch_statuses(false, true, true, $btype);
					if (isset($batch_statuses[$batch_status])) {
						$nte_msg = $batch_statuses[$batch_status];
					}
					$hl7_arr['NTE'] = $this->getNTE($nte_msg);
				} else {

					$results = tr_pdd_get_results_by_testsid($r->id, 'pcr', $group_testids, array($group->tg_ID), $r->instrument, true);

					foreach ($results as $arr) {

						$hl7_arr['OBX'][] = $this->getOBX(
							$r->lab_clia,
							$r->lab_name,
							$arr['te_abrev'],
							$arr['te_name'],
							isset($pos_neg[$arr['pos-neg']]) ? $pos_neg[$arr['pos-neg']] : $arr['pos-neg'],
							$r->result_status,
							'',
							'',
							'',
							'',
							'',
						);
					}
				}
			}




			$hl7_data = $this->array_to_hl7($hl7_arr);



			file_put_contents($hl7_file, $hl7_data);


			if (isset($_GET['act']) && $_GET['act'] == 'rebuild') {
				var_dump($hl7_file);
				continue;
			}


			$local_file = $hl7_file;
			$remote_file = $this->options['results_folder'] . '/' . basename($hl7_file);
			$remote_file = str_replace('//', '/', $remote_file);
			try {
				$this->uploadFile($local_file, $remote_file);


				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
				}

				if (!isset($other_info['sent'])) {
					$other_info['sent'] = array();
				}
				$other_info['sent'][] = date_i18n('Y-m-d H:i:s');

				$update = array(
					'mo_file_id'    => $this->api_id,
					'mo_mid'        => $r->mid,
					'mo_btype'		=> $btype,
					'mo_sent' => 1,
					'mo_sent_dt' => date_i18n('Y-m-d H:i:s'),
					'mo_status' => 1,
					'other_info' => json_encode($other_info)
				);


				if ($r->mo_ID > 0) {
					$rso = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $r->mo_ID));
				} else {
					$rso = $wpdb->insert('ci_sync_api_orders', $update);
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		}


		return true;
	}
}
