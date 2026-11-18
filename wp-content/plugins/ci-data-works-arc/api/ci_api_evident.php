<?php
include_once('ci_hl7.php');

class CI_Api_Evident extends CI_Hl7
{
	protected $conn;
	protected $sftp;
	protected $options;

	public $debug = false;
	public $log = '';
	public $api_id = -22;

	public function __construct($options)
	{

		$this->options = $options;

		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
		}
	}

	public function connect()
	{

		$port = 22;
		$this->conn = @ssh2_connect($this->options['host'], $port);

		if (!$this->conn) {
			throw new Exception('Unable to connect');
		}
	}

	public function login()
	{
		$result = ssh2_auth_password($this->conn, $this->options['username'], $this->options['password']);

		if ($result === false) {
			throw new Exception('Login incorrect');
		}

		$this->sftp = @ssh2_sftp($this->conn);

		return $result;
	}

	public function getpath($path)
	{
		return "ssh2.sftp://{$this->sftp}{$path}";
	}

	public function scan_dir($path)
	{
		$dir = $this->getpath($path);
		$tempArray = array();
		$handle = opendir($dir);
		if ($handle) {
			while (false !== ($file = readdir($handle))) {
				if (substr("$file", 0, 1) != ".") {
					$tempArray[] = $file;
				}
			}
		}

		closedir($handle);
		return $tempArray;
	}

	public function receiveFile($remote_file, $local_path)
	{
		$path = $this->getpath($remote_file);
		$stream = @fopen($path, 'r');
		if (!$stream)
			throw new Exception("Could not open file: $remote_file");
		$f = fopen($local_path, 'w');
		if ($f) {
			while (($buffer = fgets($stream, 4096)) !== false) {
				fwrite($f, $buffer);
			}
		} else {
			throw new Exception("Could not open local file: $local_path");
		}
		@fclose($stream);
	}

	public function uploadFile($local_file, $remote_file)
	{
		$path = $this->getpath($remote_file);

		$stream = @fopen($path, 'w');
		if (!$stream)
			throw new Exception("Could not open file: $remote_file");
		$data_to_send = @file_get_contents($local_file);
		if ($data_to_send === false)
			throw new Exception("Could not open local file: $local_file.");
		if (@fwrite($stream, $data_to_send) === false)
			throw new Exception("Could not send data from file: $local_file.");
		@fclose($stream);
	}

	public function delete($remote_path)
	{
		$path = $this->getpath($remote_path);
		$rs  = unlink($path);
		if (!$rs)
			throw new Exception("Could not delete file: $remote_path");
	}

	public function movefile($file_path, $move_path)
	{
		try {
			return ssh2_sftp_rename($this->sftp, $file_path, $move_path);
		} catch (Exception $e) {
		}
		return false;
	}

	public function getFileSize($remote_file)
	{
		$path = $this->getpath($remote_file);
		return filesize($path);
	}

	public function getFileTime($remote_file)
	{
		$path = $this->getpath($remote_file);
		return filemtime($path);
	}

	public function sync_reqs()
	{
		global $wpdb;
		$folder_path = $this->options['order_folder'];

		$files = $this->scan_dir($folder_path);
		$count_files = 0;

		$archive_dir = '/archive/';

		ssh2_sftp_mkdir($this->sftp, $archive_dir);

		if (count($files) == 0) {
			$this->log .= "No files in Orders folder!";
		}

		if ($this->debug) {
			var_dump($files);
		}
		foreach ($files as $file) {
			$file_path = $folder_path . '/' . $file;
			list($basename, $ext) = explode('.', $file);
			$ext = strtolower($ext);
			if (in_array($ext, array('hl7', 'txt', 'dat'))) {
				$filetime = $this->getFileTime($file_path);
				$arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => 'evident'
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='evident'");
				if ($dbfile) {
					if ($filetime <= $dbfile->m_time) {
						//$this->movefile($file_path,$archive_dir.$file);
						//continue;
					}
				} else {
					$wpdb->insert('ci_sync_api_files', $arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}'  and m_type='evident'");
				}

				$done = $this->import_req_file($dbfile, $file_path);

				//move file to archive
				if ($done) {
					$this->movefile($file_path, $archive_dir . $file);
				}

				$count_files++;
			}
		}

		if ($this->debug && $count_files = 0) {
			$this->log .= " No file need sync\n";
		}
	}

	public function findinarr($arr, $need)
	{
		$found = false;
		if (is_array($arr)) {
			foreach ($arr as $vl) {
				if ($found = $this->findinarr($vl, $need)) {
					return $found;
				}
			}
		} else if (is_string($arr) && stripos($arr, $need) === 0) {
			$found = $arr;
		}

		return $found;
	}

	public function import_req_file($dbfile, $file_path)
	{
		global $wpdb;
		$mid = 0;
		if ($this->debug) {
			$this->log .= "file: " . $file_path . "\n";
		}
		$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where (tg_is_covid=1 or tg_name ='nCoV-2019') 
			                and tg_type='pcr'");
		$batch_tbl = 'ci_batches_pcr';

		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		$contents = '';
		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents .= $buffer;
		}

		$packages = explode('MSH|', $contents);


		$clinic_default_name = '';
		if ($this->options['clinic_id'] > 0) {
			$clinic_default_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$this->options['clinic_id']}' ");
		}

		$default_doctor_name = '';
		if ($this->options['doc_id'] > 0) {
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->options['doc_id']}");
			$default_doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
		}

		foreach ($packages as $package) {
			$package = trim($package);
			if (empty($package)) continue;
			$package = 'MSH|' . $package;

			$arr = $this->hl7_to_array($package);

			$other_info		= array();
			$control_id   = $arr['MSH']['MSH.10']['MSH.10.1'];
			$clinic   = $arr['MSH']['MSH.4']['MSH.4.1'];
			$hl7_set_ID   = $arr['PID']['PID.1']['PID.1.1'];
			$hl7_patient_id = $arr['PID']['PID.2']['PID.2.1'];
			$pa_last      = trim($arr['PID']['PID.5']['PID.5.1']);
			$pa_first     = trim($arr['PID']['PID.5']['PID.5.2']);
			$dob          = $arr['PID']['PID.7']['PID.7.1'];
			$gender       = $arr['PID']['PID.8']['PID.8.1'];
			$address      = trim($arr['PID']['PID.11']['PID.11.1'] . ' ' . $arr['PID']['PID.11']['PID.11.2']);
			$city         = $arr['PID']['PID.11']['PID.11.3'];
			$state        = $arr['PID']['PID.11']['PID.11.4'];
			$zip          = $arr['PID']['PID.11']['PID.11.5'];
			$phone        = $arr['PID']['PID.13']['PID.13.1'];
			$email        = $arr['PID']['PID.13']['PID.13.2'];

			$insurance      = $arr['IN1']['IN1.4']['IN1.4.1'];
			$ins_address    = $arr['IN1']['IN1.5']['IN1.5.1'];
			$ins_city       = $arr['IN1']['IN1.5']['IN1.5.3'];
			$ins_state      = $arr['IN1']['IN1.5']['IN1.5.4'];
			$ins_zip        = $arr['IN1']['IN1.5']['IN1.5.5'];
			$ins_phone      = $arr['IN1']['IN1.7']['IN1.7.1'];
			$ins_group_id   = $arr['IN1']['IN1.8']['IN1.8.1'];
			$ins_group_name   = $arr['IN1']['IN1.9']['IN1.9.1'];
			$ins_first      = $arr['IN1']['IN1.16']['IN1.16.1'];
			$ins_last       = $arr['IN1']['IN1.16']['IN1.16.2'];
			$ins_dob        = $arr['IN1']['IN1.18']['IN1.18.1'];

			$order_id       = $arr['ORC']['ORC.2']['ORC.2.1'];
			$doc            = $arr['ORC']['ORC.9']['ORC.9.1'];
			$doc_npi        = $arr['ORC']['ORC.12']['ORC.12.1'];
			$doc_last         = $arr['ORC']['ORC.12']['ORC.12.2'];
			$doc_first         = $arr['ORC']['ORC.12']['ORC.12.3'];

			$peg_id          = $arr['OBR']['OBR.13']['OBR.13.1'];
			$old_reg         = $arr['OBR']['OBR.2']['OBR.2.1'];
			$Diagnostic_code = $arr['DG1']['DG1.4']['DG1.4.1'];
			$other_info['OBR4'] = $arr['OBR']['OBR.4']['OBR.4.1'];
			$other_info['OBR4.2'] = $arr['OBR']['OBR.4']['OBR.4.2'];
			$other_info['PID18'] = $arr['PID']['PID.18']['PID.18.1'];

			$panelcode = $arr['OBR']['OBR.4']['OBR.4.1'];




			if (empty($order_id)) {
				$this->log .= " empty $order_id";
				return false;
			}


			$dob    = date('Y-m-d', strtotime($dob));

			if (empty($doc)) {
				$doc = date_i18n('Y-m-d H:i:s');
			}
			$doc    = date('Y-m-d H:i:s', strtotime($doc));

			$gender = stripos($gender, 'u') !== false ? 'U' : $gender;
			$gender = stripos($gender, 'f') !== false ? 'F' : 'M';

			$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$order_id}'
							      and mo_file_id = '{$dbfile->m_ID}' ");
			$mid = $orderdb && $orderdb->mo_mid > 0 ? $orderdb->mo_mid : 0;
			if ($mid > 0) {
				$bat = $wpdb->get_row("select * from ci_batches_pcr where mid = {$mid} ");
			} else {
				//$bat = $wpdb->get_row( "select * from ci_batches_pcr where oldreq = '{$old_reg}' " );
			}

			if (!$bat) {
				$assen = '';
				if (isset($arr['IN1'])) {
					$assen = $this->findinarr($arr['IN1'], 'rpb');
				}
				if (empty($assen)) {
					$assen = tr_get_new_assen();
				}

				if (empty($assen)) {
					continue;
				}
			} else {
				$assen = $bat->assen;
			}

			$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;
			if ($patientid == 0) {

				$patientid = $wpdb->get_var("select pa_ID from ci_patients where pa_first='{$pa_first}'
				                and pa_last='{$pa_last}' and pa_dob='{$dob}' and pa_city = '{$city}' ");
				$patient = array(
					'pa_first'  => $pa_first,
					'pa_last'   => $pa_last,
					'pa_dob'    => $dob,
					'pa_gender' => $gender,
					'pa_phone'  => $phone,
					'pa_address'    => $address,
					'pa_city'    => $city,
					'pa_state'    => $state,
					'pa_zip'    => $zip,
					'pa_email'  => $email,
				);
				if (!empty($hl7_patient_id)) {
					$patient['pa_mrn'] = $hl7_patient_id;
				}
				if ($patientid == 0) {

					$wpdb->insert('ci_patients', $patient);
					$patientid = $wpdb->insert_id;
				} else {
					foreach ($patient as $k => $vl) {
						if (empty($vl)) {
							unset($patient[$k]);
						}
					}
					$wpdb->update('ci_patients', $patient, array('pa_ID' => $patientid));
				}
			}

			$ins_id = $bat && $bat->insurance1 > 0 ? $bat->insurance1 : 0;
			if ($ins_id == 0) {
				if (empty($ins_first)) {
					$ins_first = $pa_first;
					$ins_last = $pa_last;
					$insurance = 'No Insurance - Cash';
				}
				$ins_id = $wpdb->get_var("select ins_ID from ci_insurances 
								where ins_first='{$ins_first}' and ins_last = '{$ins_last}' 
								and ins_company='{$insurance}' and pa_ID = {$patientid} ");
				$ins_arr = array(
					'ins_first' => $ins_first,
					'ins_last' => $ins_last,
					'ins_relation' => 'Self',
					'ins_company' => $insurance . '',
					'ins_address' => $ins_address . '',
					'ins_city' => $ins_city . '',
					'ins_state' => $ins_state . '',
					'ins_zip' => $ins_zip . '',
					'ins_phone' => $ins_phone . '',
					'ins_group_id' => $ins_group_id . '',
					'ins_dob' => $ins_dob . '',
					'pa_ID' => $patientid . '',
				);
				if ($ins_id == 0) {
					$wpdb->insert('ci_insurances', $ins_arr);
					$ins_id = $wpdb->insert_id;
					if ($this->debug) {
						$this->log .= "Added new Insurance: {$insurance} => {$ins_id}\n";
					}
				}
			}

			$doc_id = $this->options['doc_id'];
			$doctor_name = '';


			if (!empty($doc_first) && !empty($doc_last)) {
				$sql_doctor = "select doc_ID from ci_doc where  
				doc_first='{$doc_first}' and doc_last='{$doc_last}' ";
				if (!empty($doc_npi)) {
					$sql_doctor .= " and doc_npi= '{$doc_npi}' ";
				}
				$doc_id = $wpdb->get_var($sql_doctor);
				if ($doc_id == 0) {
					$wpdb->insert('ci_doc', array(
						'doc_first' => $doc_first,
						'doc_last' => $doc_last,
						'doc_npi' => $doc_npi,
					));
					$doc_id = $wpdb->insert_id;
					if ($this->debug) {
						$this->log .= "Added new doctor: {$doc_first} {$doc_last}\n";
					}
				}
				$doctor_name = $doc_last . ', ' . $doc_first;
			}
			if (empty($doctor_name)) {
				$doc_id = $this->options['doc_id'];
				$doctor_name = $default_doctor_name;
			}

			$clinic_id = $this->options['clinic_id'];
			$clinic_name = $clinic_default_name;
			if (empty($this->options['clinic_id']) && !empty($clinic)) {
				$clinic_id2 = $wpdb->get_var("select cID from ci_clinic where cli_clinic = '{$clinic}' ");

				if ($clinic_id2 > 0) {

					$clinic_id = $clinic_id2;
					$clinic_name = $clinic;
				}
			}


			if ($doc_id > 0 && $clinic_id > 0) {
				$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $clinic_id));
			}


			$tests_requested_arr[] = 'GROUP_' . $group_covid_id;
			$source = 'Nasopharyngeal Swab';

			if (!empty($panelcode)) {
				$panel = $wpdb->get_row("select * from ci_test_groups where tg_short_name = '{$panelcode}' and tg_show_req = 1");
				if ($panel) {
					$tests_requested_arr = array();
					$tests_requested_arr[] = 'GROUP_' . $panel->tg_ID;

					$sqlt = "select te.te_ID, 
					te.te_type_id,
					g.tg_ID, 
					g.tg_only_group
					from ci_test_panels te
					JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
					where te_type = 'pcr' and te_in_req = 1
					and g.tg_ID = {$panel->tg_ID} or g.tg_parent = {$panel->tg_ID}
					group by te.te_ID
					";

					$testarr = $wpdb->get_results($sqlt, ARRAY_A);

					if (count($testarr) > 0) {

						foreach ($testarr as $t) {
							if (!in_array('GROUP_' . $t['tg_ID'], $tests_requested_arr)) {
								$tests_requested_arr[] = 'GROUP_' . $t['tg_ID'];
							}
							$tests_requested_arr[] = $t['te_ID'];
						}
					}
				}
			}

			$tests_requested = implode(',', $tests_requested_arr);

			$patient_name = $pa_last . ', ' . $pa_first;
			$data         = array(
				'assen'                => $assen,
				'patient_id'           => $patientid,
				'patient_name'         => $patient_name,
				'lab_ID'               => $this->options['lab_id'],
				'doc_ID'               => intval($doc_id),
				'cli_ID'               => intval($clinic_id),
				'doc'                  => $doc,
				'dob'                  => $dob,
				'requesting_physician' => $doctor_name . '',
				'requesting_practice'  => $clinic_name . '',
				'type_of_specimen'     => $source,
				'panel_tests_requested' => $tests_requested,
				'insurance'             => $insurance . '',
				'insurance1'             => intval($ins_id),
				'notes'                 => $peg_id . '',
				'oldreq'                 => $old_reg . '',
				'paid'                  => $this->api_id,
			);
			if (!empty($Diagnostic_code)) {
				$data['dagnostic_codes1'] = $Diagnostic_code;
			}

			//$data['batch_id'] = date('Ymd',strtotime($data['doc'])).'-1';

			if ($bat) {
				$mid = $bat->mid;
				$wpdb->update($batch_tbl, $data, array('mid' => $bat->mid));
			} else {

				$data['batch_created'] = date_i18n('Y-m-d H:i:s');

				$wpdb->insert($batch_tbl, $data);
				$mid = $wpdb->insert_id;

				if ($this->debug) {
					$this->log .= "Added req {$assen} => id: {$mid} \n";
					if ($mid == 0) {
						$this->log .= "err: " . $wpdb->last_error . "\n";
					}
				}
			}
			if ($mid == 0) return false;

			$update = array(
				'mo_file_id'    => $dbfile->m_ID,
				'mo_mid'        => $mid,
				'mo_control_ID' => $control_id . '',
				'mo_test_ID'    => $order_id . '',
				'mo_set_ID'     => $hl7_set_ID . '',
				'mo_patient_ID' => $hl7_patient_id . '',
				'mo_peg_ID' => $peg_id . '',
				'other_info' => json_encode($other_info) . '',
			);

			if ($orderdb && $orderdb->mo_ID > 0) {
				$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $orderdb->mo_ID));
			} else {
				$r = $wpdb->insert('ci_sync_api_orders', $update);
			}
			if ($r === false) {
				$this->log .= $wpdb->last_error;
				return false;
			}
		}

		return $mid > 0;
	}


	public function sync_reports($over_id = null, $limit = 10)
	{
		global $wpdb;
		$btype = 'pcr';
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';
		$result_tbl = 'ci_results_pcr';

		$results_path = $this->options['result_folder'];

		$hl7_dir = WP_CONTENT_DIR . '/log/evident/';
		if (!file_exists($hl7_dir)) {
			mkdir($hl7_dir);
		}
		$pos_neg = array(
			'' => '',
			'NEG' => 'Not Detected',
			'POS' => 'Detected',
			'PVL' => 'Detected-Very Low',
			'POL' => 'Detected-Low',
			'POM' => 'Detected-Medium',
			'POH' => 'Detected-High',
			'PVH' => 'Detected-Very High',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'PRP' => 'Positive',
			'INV' => 'Invalid',
			'NGE' => 'Negative',
		);

		$covid_test = trpdd_get_pcr_covid_test();

		$sql = "select re.id,
					ba.mid,
					ba.batch_id,
					ba.assen,
					ba.patient_name,
					ba.doc,
					ba.panel_tests_requested,
					
					pa.pa_ID,
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
					mf.m_ID,
					mf.m_file,
					lab.lab_name,
					lab.lab_clia,
					
					cli.cli_clinic,
					cli.cli_street,
					cli.cli_city,
					cli.cli_state,
					cli.cli_zip,
					cli.`cli_om-phone` as cli_phone,
					
					doc.doc_first,
					doc.doc_last,
					doc.doc_npi
					
				FROM {$batch_tbl} ba
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
				JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='evident'
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				where re.estatus = 'C'
				
				";
		if ($over_id > 0) {
			$sql .= " and re.id = {$over_id} ";
		} else {
			$sql .= " and mo.mo_status = 0 ";
		}
		$sql .= " limit {$limit}";
		$rs = $wpdb->get_results($sql);

		$report_files = array();

		if ($this->debug && count($rs) == 0) {
			if (!empty($wpdb->last_error)) {
				$this->log .= "error: " . $wpdb->last_error;
			}
			$this->log .= "Not found results!";
		}

		$mo_ids = array();

		foreach ($rs as $r) {
			$filename = basename($r->m_file);
			if (empty($filename)) {
				$filename = $r->assen . '.hl7';
			}
			$hl7_file = $hl7_dir . $filename;


			$assen = !empty($r->oldreq) ? $r->oldreq : $r->assen;
			if (empty($r->mo_control_ID)) {
				$r->mo_control_ID = preg_replace('/[\D]*/', '', $r->assen) . date('YmdHis');
			}
			if (empty($r->mo_set_ID)) {
				$r->mo_set_ID = '1';
			}
			if (empty($r->mo_patient_ID)) {
				$r->mo_patient_ID = $r->pa_ID;
			}

			if (empty($r->mo_test_ID)) {
				$r->mo_test_ID = $r->assen;
			}
			$other_info = array();
			if (isset($r->other_info)) {
				$other_info = json_decode($r->other_info, true);
			}




			$hl7_arr = array();
			$hl7_arr['MSH'] = $this->getMSH($r->mo_control_ID, $r->lab_clia, $r->lab_name, 'ORU', 'R01', 'ORU_R01');

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
				$r->pa_email,
				'',
				'',
				'',
				$other_info['PID18']
			);

			/*
			$hl7_arr['ORC'] = $this->getORC(
				$r->mo_test_ID,
				$assen,
				$r->doc,
				$r->lab_name,
				$r->doc_npi,
				$r->doc_first,
				$r->doc_last
			);
			*/

			/*
			$group_ids 	= array();
			$test_ids 	= array();

			foreach (explode(',', $r->panel_tests_requested) as $tid) {
				if (stripos($tid, 'group_') !== false) {
					$group_id = str_ireplace('GROUP_', '', $tid);
					if ($group_id > 0 && !in_array($group_id, $group_ids)) {
						$group_ids[] = $group_id;
					}
				} else if ($tid > 0 && !in_array($tid, $test_ids)) {
					$test_ids[] = $tid;
				}
			}
			$test_ids_str = implode(',', $test_ids);
			if (empty($test_ids_str)) {
				$test_ids_str = '-1';
			}

			$bcodes = isset($other_info['OBR_code']) && count($other_info['OBR_code']) > 0 ? $other_info['OBR_code'] : array();
			if (empty($bcodes) && !empty($other_info['OBR4'])) {
				$bcodes = array($other_info['OBR4']);
			}


			if (count($bcodes) > 0) {

				$exclude_parents = array(-1);
				foreach ($bcodes as $bcode) {

					$groupdata = $wpdb->get_row("select tg_ID ,tg_name, tg_parent
						from ci_test_groups
						where tg_short_name = '{$bcode}'
						and tg_type='{$btype}' ");

					if ($groupdata) {
						if ($groupdata->tg_parent > 0) {
							$exclude_parents[] = $groupdata->tg_ID;
						}


						if (!isset($test_groups[$bcode])) {
							$test_groups[$bcode] = array(
								'name' => $groupdata->tg_name,
								'tests' => array(),
								'exclude' => array(),
								'id' => $groupdata->tg_ID
							);
						}
					}
				}
				$exclude_parents = implode(',', $exclude_parents);

				foreach ($bcodes as $bcode) {

					$where = " ";
					$group_id = '-1';

					if (isset($test_groups[$bcode])) {
						$group_id = $test_groups[$bcode]['id'];


						$where = " and ( 
								(tg_parent = '{$group_id}'  and tg_ID not in ({$exclude_parents}) ) 
								or tg_ID = {$group_id} 
							)
							and ( te.te_ID in ({$test_ids_str}) or g.tg_only_group=1 or g.tg_notestcheckbox=1) ";
					}



					$tests_rs = $wpdb->get_results("select 
						te.te_ID, 
						te.te_name,
						te.te_abrev,
						te.te_abrev2,
						te.te_loinc_code,
						te.te_units,
						g.tg_ID, 
						g.tg_only_group,
						g.tg_short_name,
						g.tg_is_covid,
						g.tg_name,
						rs.*
						from ci_test_panels te
						JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
						JOIN {$result_tbl} rs ON (rs.drug = te.te_name or rs.drug=te.te_abrev) and rs.report_id = {$r->id}
						where te_type = '{$btype}' and te_in_req = 1
						{$where}
						group by te.te_ID ", ARRAY_A);

					if (count($tests_rs) > 0) {

						foreach ($tests_rs as $ti => $t) {

							$t['test_name'] = $t['te_name'];
							if ($this->options['testname'] == 'abrev') {
								$t['test_name'] = !empty($t['te_abrev']) ? $t['te_abrev'] : $t['te_name'];
							} else if ($this->options['testname'] == 'abrev2') {
								$t['test_name'] = !empty($t['te_abrev2']) ? $t['te_abrev2'] : $t['te_abrev'];
							} else if ($this->options['testname'] == 'loinc_code') {
								$t['test_name'] = !empty($t['te_loinc_code']) ? $t['te_loinc_code'] : $t['te_abrev'];
							}

							if (empty($t['test_name'])) {
								$t['test_name'] = $t['te_name'];
							}

							$t['drug'] = $t['te_name'];
							$t['type_of_specimen'] = $r->type_of_specimen;
							$t = tr_pdd_parse_pcr_test($t, $r->instrument, $t['tg_ID'], $r->created_date);

							if (empty($t['pos-neg']) || $t['pos-neg'] == 'PEN' || $t['pos-neg'] == 'CAN') {
								continue;
							}

							$bcode_group = $bcode;
							if ($t['tg_short_name'] == 'ABR' && $bcode != 'ABR') {
								$bcode_group = 'ABR';
							}

							if (!isset($test_groups[$bcode_group])) {
								$test_groups[$bcode_group] = array(
									'name' => $t['tg_name'],
									'tests' => array()
								);
							}

							$test_groups[$bcode_group]['tests'][] = array(
								'test_id' => $t['te_loinc_code'],
								'test_name' => $t['test_name'],
								'pos-neg' => $t['pos-neg'],
								'conc' => $t['conc'],
								'range' => $t['range'],
								'unit' => $t['te_units'],
							);
						}
					}
				}
			}


			foreach ($test_groups as $groupcode => $garr) {

				if (empty($garr['tests'])) continue;

				$hl7_arr[] = $this->getOBR(
					$r->mo_test_ID,
					$assen,
					$r->lab_name,
					$r->doc_npi,
					$r->doc_first,
					$r->doc_last,
					$groupcode,
					$garr['name'],
					$r->approve_date,
					$r->result_status,
					$r->mo_peg_ID,
					$r->doc,
					$r->spec_received
				);

				foreach ($garr['tests'] as $tarr) {

					$otherarr = array();


					$hl7_arr[] = $this->getOBX(
						$r->lab_clia,
						$r->lab_name,
						$tarr['test_id'],
						$tarr['test_name'],
						$pos_neg[$tarr['pos-neg']],
						$r->result_status,
						$r->approve_date,
						$r->lab_address,
						$r->lab_city,
						$r->lab_state,
						$r->lab_zip,
						'',
						$otherarr
					);
				}
			}
				*/


			$pdf_file  = trpdd_export_results_pdf($r->id, '', false, $btype);


			$hl7_arr[] = $this->getOBR(
				$r->mo_test_ID,
				$assen,
				$r->lab_name,
				$r->doc_npi,
				$r->doc_first,
				$r->doc_last,
				'CumulativePDF',
				'PDF Report',
				$r->approve_date,
				$r->result_status,
				$r->mo_peg_ID,
				$r->doc,
				$r->spec_received,
				'L'
			);

			$hl7_arr['OBX'][] = $this->getOBX(
				$r->lab_clia,
				$r->lab_name,
				'pdf',
				'PDF',
				'',
				$r->result_status,
				'',
				'',
				'',
				'',
				'',
				$pdf_file
			);

			$hl7_data = $this->array_to_hl7($hl7_arr);

			file_put_contents($hl7_file, $hl7_data);

			if (isset($_GET['act2']) && $_GET['act2'] == 'rebuild') {
				return;
			}

			$local_file = $hl7_file;
			$remote_file = $results_path . '/' . basename($hl7_file);
			$remote_file = str_replace('//', '/', $remote_file);
			try {
				$this->uploadFile($local_file, $remote_file);
				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
					/*
					$files = $this->scan_dir($results_path);
					
					foreach ($files as $file) {
						$this->log .= '<li>' . $file . '</li>';
					}*/
				}


				$wpdb->query("update ci_sync_api_orders set mo_status = 1 where mo_ID in ({$r->mo_ID}) and mo_btype='pcr' ");
			} catch (Exception $e) {
				var_dump($e);
			}
		}
	}
}
