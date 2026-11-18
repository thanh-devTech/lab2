<?php
include_once('ci_hl7.php');

class CI_Api_sftp_experity extends CI_Hl7
{
	protected $conn;
	protected $sftp;
	private  $host = '';
	private  $port = 22;
	public $order_folder = '/Orders';
	public $result_folder = '/Results';
	public $order_log_dir = WP_CONTENT_DIR . '/log/experity_orders/';
	public $hl7_dir = WP_CONTENT_DIR . '/log/experity/';
	protected $options;
	public $debug = false;
	public $log = '';
	public $api_id = -11;
	public $log_file_slug = 'experity';

	public function __construct($options)
	{

		$this->options = $options;
		if (!isset($this->options['splitbypanel'])) {
			$this->options['splitbypanel'] = false;
		}
		$this->host = isset($options['host']) ? $options['host'] : '';
		if (isset($options['port']) && $options['port'] > 0) {
			$this->port = $options['port'];
		}

		$this->order_folder = isset($options['orders_folder']) ? $options['orders_folder'] : '';
		$this->result_folder = isset($options['results_folder']) ? $options['results_folder'] : '';

		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
		}
	}

	public function connect()
	{

		$this->conn = @ssh2_connect($this->host, $this->port);

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
		if (stripos($path, 'wp-content') !== false) {
			return $path;
		}
		return "ssh2.sftp://{$this->sftp}{$path}";
	}

	public function scan_dir($path = '')
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

	public function removeDir($path)
	{
		try {
			return ssh2_sftp_rmdir($this->sftp, $path);
		} catch (Exception $e) {
		}
		return false;
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

		$files = $this->scan_dir($this->order_folder);
		$count_files = 0;

		$old_archive_dir = $this->order_folder . '/archive/';

		ssh2_sftp_rmdir($this->sftp, $old_archive_dir);

		//ssh2_sftp_mkdir($this->sftp, $archive_dir);

		if (count($files) == 0) {
			$this->log .= "No files in Orders folder!";
		}


		foreach ($files as $file) {
			$file_path = $this->order_folder . '/' . $file;
			if (stripos($file, '.hl7') > 0 || stripos($file, '.txt') > 0 || stripos($file, '.json') > 0) {
				$filetime = $this->getFileTime($file_path);
				$arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => 'experity'
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='experity'");
				if ($dbfile) {
					if ($filetime <= $dbfile->m_time) {
						$this->delete($file_path);
						//$this->movefile($file_path, $archive_dir.$file);
						continue;
					}
				} else {
					$wpdb->insert('ci_sync_api_files', $arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}'  and m_type='experity'");
				}

				$done = $this->import_req_file($dbfile, $file_path);

				//move file to archive
				if ($done && !$this->is_Test) {
					$this->delete($file_path);
					//$this->movefile($file_path, $archive_dir.$file);
				} else {
					$wpdb->delete('ci_sync_api_files', array('m_ID' => $dbfile->m_ID));
				}

				$count_files++;
			}
		}

		if ($count_files == 0) {
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
		$this->log .= "file: " . $file_path . "\n";

		$batch_tbl = 'ci_batches_pcr';

		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		if (!$stream) {
			$this->log .= "error open file: " . $file_path . "\n";
			return false;
		}
		$contents = '';
		$other_info = array();
		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents .= $buffer;
		}

		//log file
		if (!file_exists($this->order_log_dir)) {
			mkdir($this->order_log_dir);
		}

		$logfile = $this->order_log_dir . basename($file_path);
		if (!file_exists($logfile)) {
			file_put_contents($logfile, $contents);
			touch($this->order_log_dir . '/log.txt');
		}


		$packages = explode('MSH|', $contents);

		$adddingf = '';
		if (isset($this->options['useid2'])  && $this->options['useid2']) {
			$adddingf .= ', cli_experity_id2';
		}

		$clis = $wpdb->get_results("select cID, 
		cli_clinic, 
		cli_experity_id
		{$adddingf}
		from ci_clinic where cli_experity_id !='' or cli_experity_id!='' ");
		$clinics = array();
		foreach ($clis as $cli) {
			if (!empty($cli->cli_experity_id)) {
				$clinics[$cli->cli_experity_id] = $cli;
			}
			if (isset($cli->cli_experity_id2) && !empty($cli->cli_experity_id2)) {
				$clinics[$cli->cli_experity_id2] = $cli;
			}
		}

		$default_clinic_name = '';
		if ($this->options['cli_id'] > 0) {
			$default_clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$this->options['cli_id']}' ");
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
			$priority_hours  = 0;

			$arr = $this->hl7_to_array($package, array('OBR', 'DG1'));

			if (isset($_GET['act'])) {
				//var_dump($arr);
				//exit;
			}


			$control_id   = $arr['MSH']['MSH.10']['MSH.10.1'];
			$hl7_set_ID   = $arr['PID']['PID.1']['PID.1.1'];
			$hl7_patient_id = $arr['PID']['PID.2']['PID.2.1'];
			$mrn           = $arr['PID']['PID.3']['PID.3.1'];
			$pa_last      = trim($arr['PID']['PID.5']['PID.5.1']);
			$pa_first     = trim($arr['PID']['PID.5']['PID.5.2']);
			$dob          = $arr['PID']['PID.7']['PID.7.1'];
			$gender       = $arr['PID']['PID.8']['PID.8.1'];
			$address      = trim($arr['PID']['PID.11']['PID.11.1'] . ' ' . $arr['PID']['PID.11']['PID.11.2']);
			$city         = $arr['PID']['PID.11']['PID.11.3'];
			$state        = $arr['PID']['PID.11']['PID.11.4'];
			$zip          = $arr['PID']['PID.11']['PID.11.5'];
			$phone        = isset($arr['PID']['PID.13']['PID.13.1']) ? $arr['PID']['PID.13']['PID.13.1'] : '';
			$email        = isset($arr['PID']['PID.13']['PID.13.2']) ? $arr['PID']['PID.13']['PID.13.2'] : '';
			$ssn          = isset($arr['PID']['PID.19']['PID.19.1']) ? trim($arr['PID']['PID.19']['PID.19.1']) : '';
			$race_code       = isset($arr['PID']['PID.10']['PID.10.1']) ? $arr['PID']['PID.10']['PID.10.1'] : '';
			$ethnicity    = isset($arr['PID']['PID.22']['PID.22.1']) ? $arr['PID']['PID.22']['PID.22.1'] : '';
			$ethnicity_name    = isset($arr['PID']['PID.22']['PID.22.2']) ? $arr['PID']['PID.22']['PID.22.2'] : '';

			$ssn2 = str_replace('0', '', $ssn);
			if (empty($ssn2) || stripos($ssn, 'n/a') !== false) {
				$ssn = '';
			}
			$dagnostic_codes = '';
			if (isset($arr['DG1']) && isset($arr['DG1.3'])) {
				$dagnostic_codes = $arr['DG1']['DG1.3']['DG1.3.1'];
			} else {
				$dagnostic_codes_arr = array();
				foreach ($arr['DG1'] as $dg1) {
					if (!empty($dg1['DG1.3']['DG1.3.1'])) {
						$dagnostic_codes_arr[] = $dg1['DG1.3']['DG1.3.1'];
					}
				}
				$dagnostic_codes = implode(',', $dagnostic_codes_arr);
			}



			$insurance      = $arr['IN1']['IN1.4']['IN1.4.1'];
			$ins_code     = isset($arr['IN1']['IN1.2']['IN1.2.1']) ? trim($arr['IN1']['IN1.2']['IN1.2.1']) : '';
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
			$ins_member_id = $arr['IN1']['IN1.36']['IN1.36.1'];
			$ins_authorization_id = ''; //$arr['IN1']['IN1.36']['IN1.36.1'];

			$order_id       = $arr['ORC']['ORC.2']['ORC.2.1'];
			$doc            = $arr['ORC']['ORC.9']['ORC.9.1'];
			$doc_npi        = $arr['ORC']['ORC.12']['ORC.12.1'];
			$doc_last         = $arr['ORC']['ORC.12']['ORC.12.2'];
			$doc_first         = $arr['ORC']['ORC.12']['ORC.12.3'];

			$peg_id          = isset($arr['OBR']['OBR.13']['OBR.13.1']) ? $arr['OBR']['OBR.13']['OBR.13.1'] : '';
			$old_reg        = isset($arr['OBR']['OBR.2']['OBR.2.1']) ? $arr['OBR']['OBR.2']['OBR.2.1'] : '';

			$type_of_specimens 		= array();
			$other_info['OBR_code'] = array();
			foreach ($arr['OBR'] as $obr) {
				$peg_id          = $obr['OBR.13']['OBR.13.1'];
				$old_reg        = $obr['OBR.2']['OBR.2.1'];

				$other_info['OBR41'] = trim($obr['OBR.4']['OBR.4.1']);
				$other_info['OBR7'] = trim($obr['OBR.7']['OBR.7.1']);

				$other_info['OBR_code'][] = $other_info['OBR41'];

				$other_info['OBR16.1'] = trim($obr['OBR.16']['OBR.16.1']);
				$other_info['OBR16.2'] = trim($obr['OBR.16']['OBR.16.2']);
				$other_info['OBR16.3'] = trim($obr['OBR.16']['OBR.16.3']);

				if (stripos($obr['OBR.15']['OBR.15.1'], '12h') !== false) {
					$priority_hours = 12;
				} else if (stripos($obr['OBR.15']['OBR.15.1'], '24h') !== false) {
					$priority_hours = 24;
				}
			}


			if (stripos($phone, 'n/a') !== false || stripos($phone, 'na') !== false || strlen($phone) > 11) {
				$phone = '';
			}


			if (isset($arr['OBX']) && is_array($arr['OBX'])) {
				foreach ($arr['OBX'] as $obx) {
					if (empty($phone) && stripos($obx['OBX.3']['OBX.3.2'], 'phone') !== false) {

						$phone = $obx['OBX.5']['OBX.5.1'];
					} else if (stripos($obx['OBX.3']['OBX.3.2'], 'email') !== false) {
						$email = $obx['OBX.5']['OBX.5.1'];
					} else if (stripos($obx['OBX.3']['OBX.3.2'], 'Specimen') !== false) {

						$specode = trim($obx['OBX.5']['OBX.5.1']);
						$specname = $specode;

						$spec = $wpdb->get_var("select ts_name from ci_test_specimens where ts_type ='pcr'
						and ts_name = '{$specname}' or ts_code = '{$specode}' ");
						if (empty($spec)) {
							if (stripos($specname, '-') > 0) {
								list($specname, $specode) = explode('-', $specname);
								$specname = trim($specname);
								$specode = trim($specode);
							}
							$spec = $wpdb->get_var("select ts_name from ci_test_specimens where ts_type ='pcr'
							and ts_name like '{$specname}%' or ts_code = '{$specode}' ");
						}
						if (empty($spec)) {
							$specodearr = explode(' ', $specname);
							$specname = $specodearr[0] . ' ' . $specodearr[1];
							$spec = $wpdb->get_var("select ts_name from ci_test_specimens where ts_type ='pcr'
							and ts_name like '{$specname}%' ");
						}
						if (empty($spec)) {
							$spec = $specname;
						}

						$type_of_specimens[] = $spec;
					}


					if ($obx['OBX.5']['OBX.5.1'] == 12 || $obx['OBX.5']['OBX.5.1'] == 24) {
						$priority_hours = $obx['OBX.5']['OBX.5.1'];
					}
				}
			}


			$other_info['NTE'] = isset($arr['NTE']['NTE.3']['NTE.3.1']) ? trim($arr['NTE']['NTE.3']['NTE.3.1']) : '';

			$other_info['MSH2'] = isset($arr['MSH']['MSH.2']['MSH.2.1']) && !is_array($arr['MSH']['MSH.2']['MSH.2.1']) ? trim($arr['MSH']['MSH.2']['MSH.2.1']) : '';
			$other_info['MSH3'] = trim($arr['MSH']['MSH.3']['MSH.3.1']);

			$other_info['MSH4'] = trim($arr['MSH']['MSH.4']['MSH.4.1']);
			$other_info['MSH5'] = trim($arr['MSH']['MSH.5']['MSH.5.1']);
			$other_info['MSH6'] = trim($arr['MSH']['MSH.6']['MSH.6.1']);
			$other_info['PID3'] = trim($arr['PID']['PID.3']['PID.3.1']);
			$other_info['PID10'] = trim($arr['PID']['PID.10']['PID.10.1']);



			$other_info['ORC12.1'] = trim($arr['ORC']['ORC.12']['ORC.12.1']);
			$other_info['ORC12.2'] = trim($arr['ORC']['ORC.12']['ORC.12.2']);
			$other_info['ORC12.3'] = trim($arr['ORC']['ORC.12']['ORC.12.3']);



			$other_info['PV17'] = isset($arr['PV1']['PV1.7']['PV1.7.1']) ? trim($arr['PV1']['PV1.7']['PV1.7.1']) : '';
			$other_info['OBX31'] = isset($arr['OBX']['OBX.3']['OBX.3.1']) ? trim($arr['OBX']['OBX.3']['OBX.3.1']) : '';
			$other_info['OBX32'] = isset($arr['OBX']['OBX.3']['OBX.3.2']) ? trim($arr['OBX']['OBX.3']['OBX.3.2']) : '';
			$other_info['OBX51'] = isset($arr['OBX']['OBX.5']['OBX.5.1']) ? trim($arr['OBX']['OBX.5']['OBX.5.1']) : '';

			if (strpos($email, '@') === false) {
				$email = '';
			}
			$email = strtolower($email);
			$email = substr($email, 0, 200);

			$phone = preg_replace("/[^0-9\+\-]+/", '', $phone);
			$phone = substr($phone, 0, 18);

			$lab_id = $this->options['lab_id'];
			$cli_id = $this->options['cli_id'];
			$doc_id = $this->options['doc_id'];
			$clinic_name = $default_clinic_name;
			$doctor_name = $default_doctor_name;

			if (isset($clinics[$other_info['MSH4']])) {
				$cli = $clinics[$other_info['MSH4']];
				$cli_id = $cli->cID;
				$clinic_name = $cli->cli_clinic;
			}

			if (empty($cli_id)) {
				return false;
			}


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
			$bat = null;
			if ($mid > 0) {
				$bat = $wpdb->get_row("select * from ci_batches_pcr where mid = {$mid} ");
			} else {
				$bat = $wpdb->get_row("select * from ci_batches_pcr where oldreq = '{$old_reg}' ");
			}

			if (!$bat) {
				$lab = null;
				if ($lab_id > 0) {
					$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id} ", ARRAY_A);
				}
				$assen = tr_get_new_assen('pcr', false,  '', $lab);

				if (empty($assen)) {
					echo 'empty assen';
					continue;
				}
			} else {
				$assen = $bat->assen;
			}


			$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;

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
				'pa_ssn'    => $ssn . '',
				'pa_mrn'    => $mrn . '',

			);

			if (!empty($race_code)) {
				if (stripos($race_code, 'white') !== false || $race_code == 'W') {
					$race_code = '2106-3';
				} else if (stripos($race_code, 'American Indian') !== false || $race_code == 'A') {
					$race_code = '1002-5';
				} else if (stripos($race_code, 'Asian') !== false) {
					$race_code = '2028-9';
				} else if (stripos($race_code, 'Black') !== false || $race_code == 'B') {
					$race_code = '2054-5';
				} else if (stripos($race_code, 'Hawaiian') !== false || $race_code == 'H') {
					$race_code = '2076-8';
				} else if (stripos($race_code, 'Other') !== false || $race_code == 'O') {
					$race_code = '2131-1';
				} else {
					$race_code = '';
				}

				$patient['pa_race'] = substr($race_code, 0, 10);
			}

			if (!empty($ethnicity) || !empty($ethnicity_name)) {
				if (stripos($ethnicity, 'Not Hispanic or Latino') !== false || stripos($ethnicity_name, 'Not Hispanic or Latino')) {
					$ethnicity = 'N';
				} else if (stripos($ethnicity, 'Not Hispanic') !== false || stripos($ethnicity_name, 'Not Hispanic') !== false) {
					$ethnicity = 'N';
				} else if ($ethnicity == '2186-5') {
					$ethnicity = 'N';
				} else if (stripos($ethnicity, 'Hispanic or Latino') !== false || stripos($ethnicity_name, 'Hispanic or Latino') !== false) {
					$ethnicity = 'H';
				} else if (stripos($ethnicity, 'Hispanic') !== false || stripos($ethnicity_name, 'Hispanic') !== false) {
					$ethnicity = 'H';
				} else {
					$ethnicity = 'U';
				}
				$patient['pa_ethnicity'] = substr($ethnicity, 0, 10);
			}

			if (!empty($hl7_patient_id)) {
				$patient['pa_mrn'] = $hl7_patient_id;
			}
			//fix phone
			if (isset($patient['pa_phone'])) {
				$patient['pa_phone'] = str_replace(array(' ', '(', ')', '-', '.'), '', $patient['pa_phone']);
			}

			foreach ($patient as $k => $vl) {
				if (empty($vl)) {
					unset($patient[$k]);
				}
			}


			if ($patientid == 0) {

				$patientid = $wpdb->get_var($wpdb->prepare(
					"select pa_ID from ci_patients where pa_first = %s
				                and pa_last=%s and pa_dob=%s and pa_city = %s ",
					$pa_first,
					$pa_last,
					$dob,
					$city
				));

				if ($patientid == 0) {

					$wpdb->insert('ci_patients', $patient);
					$patientid = $wpdb->insert_id;
				} else {
					$wpdb->update('ci_patients', $patient, array('pa_ID' => $patientid));
				}
			} else {
				$wpdb->update('ci_patients', $patient, array('pa_ID' => $patientid));
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
					'ins_member_id' => $ins_member_id . '',
					'ins_group_id' => $ins_group_id . '',
					'ins_authorization_id' => $ins_authorization_id . '',
					'ins_dob' => $ins_dob . '',
					'pa_ID' => $patientid . '',
					'ins_code'    => $ins_code . '',

				);
				if ($ins_id == 0) {
					$wpdb->insert('ci_insurances', $ins_arr);
					$ins_id = $wpdb->insert_id;
					$this->log .= "Added new Insurance: {$insurance} => {$ins_id}\n";
				}
			}

			//insurance2
			$ins_id2 = 0;
			if (isset($arr['IN1_2'])) {
				$insurance2      = $arr['IN1_2']['IN1.4']['IN1.4.1'];
				$ins_code2       = isset($arr['IN1_2']['IN1.2']['IN1.2.1']) ? trim($arr['IN1_2']['IN1.2']['IN1.2.1']) : '';
				$ins_address2    = $arr['IN1_2']['IN1.5']['IN1.5.1'];
				$ins_city2       = $arr['IN1_2']['IN1.5']['IN1.5.3'];
				$ins_state2      = $arr['IN1_2']['IN1.5']['IN1.5.4'];
				$ins_zip2       = $arr['IN1_2']['IN1.5']['IN1.5.5'];
				$ins_phone2     = $arr['IN1_2']['IN1.7']['IN1.7.1'];
				$ins_group_id2   = $arr['IN1_2']['IN1.8']['IN1.8.1'];
				$ins_group_name2   = $arr['IN1_2']['IN1.9']['IN1.9.1'];
				$ins_first2      = $arr['IN1_2']['IN1.16']['IN1.16.1'];
				$ins_last2       = $arr['IN1_2']['IN1.16']['IN1.16.2'];
				$ins_dob2        = $arr['IN1_2']['IN1.18']['IN1.18.1'];
				$ins_member_id2 = $arr['IN1_2']['IN1.36']['IN1.36.1'];
				$ins_authorization_id2 = ''; //$arr['IN1']['IN1.36']['IN1.36.1'];

				$ins_id2 = $bat && $bat->insurance2 > 0 ? $bat->insurance2 : 0;
				if ($ins_id2 == 0) {


					$ins_id2 = $wpdb->get_var("select ins_ID from ci_insurances 
								where ins_first='{$ins_first2}' and ins_last = '{$ins_last2}' 
								and ins_company='{$insurance2}' and pa_ID = {$patientid} ");


					$ins_arr2 = array(
						'ins_first' => $ins_first2,
						'ins_last' => $ins_last2,
						'ins_relation' => 'Self',
						'ins_company' => $insurance2 . '',
						'ins_address' => $ins_address2 . '',
						'ins_city' => $ins_city2 . '',
						'ins_state' => $ins_state2 . '',
						'ins_zip' => $ins_zip2 . '',
						'ins_phone' => $ins_phone2 . '',
						'ins_member_id' => $ins_member_id2 . '',
						'ins_group_id' => $ins_group_id2 . '',
						'ins_authorization_id' => $ins_authorization_id2 . '',
						'ins_dob' => $ins_dob2 . '',
						'pa_ID' => $patientid . '',
						'ins_code' => $ins_code2 . '',
					);
					if ($ins_id2 == 0) {
						$wpdb->insert('ci_insurances', $ins_arr2);
						$ins_id2 = $wpdb->insert_id;
						$this->log .= "Added new Insurance: {$insurance} => {$ins_id2}\n";
					}
				}
			}



			if (!empty($doc_first) && !empty($doc_last)) {
				$doc_first = ucwords(strtolower($doc_first));
				$doc_last = ucwords(strtolower($doc_last));

				$sql_doctor = $wpdb->prepare("select doc_ID from ci_doc where  
										 doc_first=%s  
										    and doc_last=%s 
										  and doc_npi = %s ", $doc_first, $doc_last, $doc_npi);
				$doc_id = $wpdb->get_var($sql_doctor);

				if ($doc_id == 0) {
					$doc_id = $wpdb->get_var("select doc_ID from ci_doc where doc_npi = '{$doc_npi}'");
				}
				if ($doc_id == 0) {
					$wpdb->insert('ci_doc', array('doc_first' => $doc_first, 'doc_last' => $doc_last, 'doc_npi' => $doc_npi));
					$doc_id = $wpdb->insert_id;
					$this->log .= "Added new doctor: {$doc_first} {$doc_last}\n";
				}
				$doctor_name = $doc_last . ', ' . $doc_first;
			}


			if ($doc_id > 0 && $cli_id > 0) {
				$existdc = $wpdb->get_var("select doc_ID from ci_clinic_doc 
									where doc_ID = {$doc_id}
									and cli_ID = {$cli_id} ");
				if (!$existdc) {
					$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $cli_id));
				}
			}

			$type_of_specimen_str = implode(',', $type_of_specimens);

			if (empty($dagnostic_codes)) {
				$dagnostic_codes = 'Z20.828';
			}
			$tests_requested = array();
			$groupbypanels	 = array();

			foreach ($other_info['OBR_code'] as $bcode) {

				if (!empty($bcode)) {

					if ($this->options['splitbypanel']) {
						$tests_requested = array();
					}

					//check group first
					$group = $wpdb->get_row("select * from ci_test_groups 
					where (tg_short_name = '{$bcode}' or tg_cpt_code = '{$bcode}')
					and tg_show_req = 1
					and tg_type = 'pcr' ");

					if ($group) {
						if (!in_array('GROUP_' . $group->tg_ID, $tests_requested)) {
							$tests_requested[] = 'GROUP_' .  $group->tg_ID;
						}

						if (!$group->tg_only_group && !$group->tg_notestcheckbox) {
							$testarr = $wpdb->get_results("select te.te_ID
							from ci_test_panels te
							JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
							where (tg_ID = {$group->tg_ID} or tg_parent = {$group->tg_ID})
							and te_type = 'pcr' 
							and te_in_req = 1
							group by te.te_ID
							", ARRAY_A);


							if (count($testarr) > 0) {
								foreach ($testarr as $t) {
									$tests_requested[] = $t['te_ID'];
								}
							}
						}
					} else {
						$testarr = $wpdb->get_results("select te.te_ID, g.tg_ID, g.tg_only_group
						from ci_test_panels te
						JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
						where (te_loinc_code='{$bcode}' )
						and te_type = 'pcr' 
						group by te.te_ID
						", ARRAY_A);


						if (count($testarr) > 0) {
							foreach ($testarr as $t) {
								if (!in_array('GROUP_' . $t['tg_ID'], $tests_requested)) {
									$tests_requested[] = 'GROUP_' . $t['tg_ID'];
								}
								$tests_requested[] = $t['te_ID'];
							}
						}
					}

					if ($this->options['splitbypanel']) {
						$groupbypanels[] = array(
							'panels' => implode(',', $tests_requested),
							'bcode' => $bcode,
						);
					}
				}
			}

			$tests_requested = implode(',', $tests_requested);


			$patient_name = $pa_last . ', ' . $pa_first;
			$data         = array(
				'assen'                => $assen,
				'patient_id'           => $patientid,
				'patient_name'         => $patient_name,
				'lab_ID'               => $lab_id,
				'doc_ID'               => intval($doc_id),
				'cli_ID'               => intval($cli_id),
				'doc'                  => $doc,
				'dob'                  => $dob,
				'requesting_physician' => $doctor_name . '',
				'requesting_practice'  => $clinic_name . '',
				'type_of_specimen'     => $type_of_specimen_str,
				'dagnostic_codes1'     => $dagnostic_codes,
				'panel_tests_requested' => $tests_requested,
				'insurance'             => $insurance . '',
				'insurance1'             => intval($ins_id),
				'notes'                 => $peg_id . '',
				'oldreq'                 => $old_reg . '',
				'paid'                  => $this->api_id,
			);
			if ($ins_id2 > 0) {
				$data['insurance2'] = $ins_id2;
			}
			if ($priority_hours > 0) {
				$data['priority_hours'] = $priority_hours;
			}

			if (empty($groupbypanels)) {
				$groupbypanels[] = array(
					'panels' => $tests_requested
				);
			}

			foreach ($groupbypanels as $inum => $panel) {
				$data['panel_tests_requested'] = $panel['panels'];

				if (isset($panel['bcode'])) {
					$other_info['OBR_code'] = array($panel['bcode']);
				}

				$neworder_id 	= $order_id;
				$newold_reg 	= $old_reg;


				if ($inum > 0) {

					$neworder_id = $order_id . '_' . $inum;
					$newold_reg = $old_reg . '_' . $inum;
					$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$neworder_id}'
							      and mo_file_id = '{$dbfile->m_ID}' ");
					$mid = $orderdb && $orderdb->mo_mid > 0 ? $orderdb->mo_mid : 0;
					$bat = null;
					if ($mid > 0) {
						$bat = $wpdb->get_row("select * from ci_batches_pcr where mid = {$mid} ");
					} else {
						$bat = $wpdb->get_row("select * from ci_batches_pcr where oldreq = '{$newold_reg}' ");
					}

					if (!$bat) {
						$assen = tr_get_new_assen();

						if (empty($assen)) {
							continue;
						}
					} else {
						$assen = $bat->assen;
					}
				}

				$data['assen'] = $assen;
				$data['oldreq'] = $newold_reg;

				$this->log .= 'order assen #' . $neworder_id . ':' . $assen . "<br>\n";

				if ($bat) {
					$mid = $bat->mid;
					foreach ($data as $k => $vl) {
						if (empty($vl)) {
							unset($data[$k]);
						}
					}

					$wpdb->update($batch_tbl, $data, array('mid' => $bat->mid));
				} else {
					$data['batch_created'] = date_i18n('Y-m-d H:i:s');

					$wpdb->insert($batch_tbl, $data);

					$mid = $wpdb->insert_id;

					$this->log .= "Added req {$assen} => id: {$mid} \n";
					if ($mid == 0) {
						$this->log .= "err: " . $wpdb->last_error . "\n";
					}
				}
				if ($mid == 0) {
					$this->log .= 'wrong mid<br>';
					return false;
				}

				$update = array(
					'mo_file_id'    => $dbfile->m_ID,
					'mo_mid'        => $mid,
					'mo_control_ID' => $control_id . '',
					'mo_test_ID'    => $neworder_id . '',
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
			}
		}

		return $mid > 0;
	}

	public function sync_reports($over_id = 0)
	{
		global $wpdb;
		$btype 		= 'pcr';
		$batch_tbl 	= 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';

		$canceled_status = '';
		$canceled_status_text = '';
		$canceled_statusrs = $wpdb->get_row("select s_key,s_text from ci_batches_status 
			where s_type = 'ca' and istatus = 1 and btype = 'pcr'
			order by iorder asc limit 1");
		if (!empty($canceled_statusrs)) {
			$canceled_status = 'ca_' . $canceled_statusrs->s_key;
			$canceled_status_text = $canceled_statusrs->s_text;
		}

		if (!file_exists($this->hl7_dir)) {
			mkdir($this->hl7_dir);
		}
		$pos_neg = array(
			'' => '',
			'NEG' => 'Not Detected',
			'POS' => 'Detected',
			'INC' => 'Inconclusive',
			'PRP' => 'Detected',
			'INV' => 'Invalid',
			'NGE' => 'Not Detected',
			'POL' => 'Detected-Low',
			'POM' => 'Detected-Medium',
			'POH' => 'Detected-High',
			'PVL' => 'Detected-Very Low',
			'PVH' => 'Detected-Very High',
		);

		$all_rejected_status = tr_pdd_get_batch_statuses(false, true, true, 'pcr');

		$sql = "select re.id,
					ba.mid,
					ba.batch_id,
					ba.ref_lab_ID,
					ba.assen,
					ba.patient_name,
					ba.doc,
					ba.spec_received,
					ba.batch_status,
					ba.notes_report,
					ba.panel_tests_requested,
					ba.result_pdf,
					
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
					cli.cID,
					
					doc.doc_first,
					doc.doc_last,
					doc.doc_npi
					
				FROM {$batch_tbl} ba
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
				JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='experity'
				left JOIN {$report_tbl} re ON re.patient_id = ba.assen
				
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				left JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				where 
				";
		if ($over_id > 0) {
			$sql .= "  re.id = {$over_id} ";
		} else {

			$limit_approve_date = date('Y-m-d', time() - 86400 * 17);
			$sql .= " mo.mo_status in (0,2) ";



			if (!empty($canceled_status)) {
				$sql .= " and (
							(re.estatus = 'C' and re.approve_date >='{$limit_approve_date}')
							or (ba.batch_status = '{$canceled_status}') 
							)";
			} else {
				$sql .= " and re.estatus = 'C' and re.approve_date >='{$limit_approve_date}'";
			}


			if (!empty($this->options['start_date'])) {
				$sql .= " and ba.batch_created >= '{$this->options['start_date']}' ";
			}
		}

		$rs = $wpdb->get_results($sql);

		$report_files = array();


		$this->log .= " count: " . count($rs) . '<br>';
		if (!empty($wpdb->last_error)) {
			$this->log .= "error: " . $wpdb->last_error;
		}

		$this->log .= "Not found results!";

		$mo_ids = array();

		foreach ($rs as $r) {


			$other_info = json_decode($r->other_info, true);


			$filename = basename($r->m_file);
			//$filename = str_replace(array('{','}'),'',$filename);
			$filename = str_replace('.json', '.hl7', $filename);

			if ($this->options['splitbypanel']) {
				$arrksplit 		= explode('_', $r->mo_test_ID);
				$r->mo_test_ID 	= $arrksplit[0];
				if (isset($arrksplit[1]) && $arrksplit[1] > 0) {
					$filename = str_replace('.', '_' . $arrksplit[1] . '.', $filename);
				}
			}


			$hl7_file = $this->hl7_dir . $filename;

			touch($this->hl7_dir . '/log.txt');


			if (!isset($report_files[$hl7_file])) {
				$report_files[$hl7_file] = fopen($hl7_file, 'a+');
				$mo_ids[$hl7_file] = array();
			}
			$r->result_status = 'F';

			if (!empty($canceled_status) && $r->batch_status == $canceled_status) {
				$r->result_status = 'X';
			} else if ($r->batch_status == 'rejected' || strpos($r->batch_status, 're_') === 0) {
				$r->result_status = 'X';
			}

			$assen 		= !empty($r->oldreq) ? $r->oldreq : $r->assen;

			$test_groups		= array();


			$bcodes = isset($other_info['OBR_code']) && count($other_info['OBR_code']) > 0 ? $other_info['OBR_code'] : array();
			if (empty($bcodes) && !empty($other_info['OBR41'])) {
				$bcodes = array($other_info['OBR41']);
			}

			if (count($bcodes) > 0) {

				$group_ids = array();
				$group_ids_ordered = array();
				$in_panel_tests_requested = array();
				$reflex_pass = array();

				foreach (explode(',', $r->panel_tests_requested) as $t) {
					if (stripos($t, 'group') === false) {
						if ($t > 0) {
							$in_panel_tests_requested[] = $t;
						}
					} else {
						$gid = str_ireplace('group_', '', $t);
						$group_ids[] = $gid;
						$group_ids_ordered[] = $gid;
						$bcodes[] = $t;
					}
				}

				$drug_groups = tr_pdd_get_drug_groups('pcr', $group_ids, true);
				foreach ($drug_groups as $groupid => $groupdata) {

					if (!empty($groupdata['reflex_ID'])) {
						foreach ($groupdata['reflex_ID'] as $rlid) {
							if ($rlid > 0 && !in_array($rlid, $group_ids_ordered)) {
								$reflex_pass[$rlid] = false;
							}
						}
					}
				}
				//	var_dump($bcodes);

				$has_empty = false;
				$has_result = false;
				$resulted_groups = array();
				$last_bcode = '';
				$has_group_in_order = false;
				$groupnamebycodes = array();
				foreach ($bcodes as $bcode) {

					//check group first
					if (stripos($bcode, 'group_') !== false) {
						if ($has_group_in_order && !$has_result && !$has_empty) {
							continue;
						}
						$groupid = str_ireplace('group_', '', $bcode);
						$group = $wpdb->get_row("select * from ci_test_groups 
						where tg_ID = {$groupid}
						and tg_show_req = 1
						and tg_type = 'pcr' ");
						$has_empty = false;
						$groupnamebycodes[$bcode] = $group->tg_name;
						$bcode = $last_bcode;
					} else {
						$group = $wpdb->get_row("select * from ci_test_groups 
						where (tg_short_name = '{$bcode}' or tg_cpt_code = '{$bcode}')
						and tg_show_req = 1
						and tg_type = 'pcr' ");
						$last_bcode = $bcode;

						$has_group_in_order = in_array($group->tg_ID, $group_ids_ordered);
						$groupnamebycodes[$bcode] = $group->tg_name;
					}
					$groupid = $group ? $group->tg_ID : -1;


					if (in_array($groupid, $resulted_groups)) {
						continue;
					}
					$resulted_groups[] = $groupid;

					$group_notes = '';
					if ($groupid > 0 && $this->options['ntepanel']) {
						$r->group_id = $groupid;
						$group_notes = trpdd_get_pdf_note($r, $btype, true);
					}

					if (!isset($test_groups[$bcode])) {
						$group_name = isset($groupnamebycodes[$bcode]) ? $groupnamebycodes[$bcode] : $group->tg_name;
						$test_groups[$bcode] = array(
							'name' => $group_name,
							'tests' => array(),
							'notes' => $group_notes,
						);
					}

					$tests_rs = $wpdb->get_results("select 
					te.*,
					g.tg_ID, 
					g.tg_only_group,
					g.tg_is_covid,
					g.tg_name,
					g.tg_reflex_group_ID,
					g.tg_antibiotic,
					rs.*
					from ci_test_panels te
					JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
					JOIN ci_results_pcr rs ON (rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2) 
											and rs.report_id = {$r->id} and (rs.group_id = 0 or rs.group_id is null or rs.group_id  = te.te_group_id)
					where (
						te_loinc_code in ('{$bcode}') or tg_ID in ({$groupid}) or tg_parent in ({$groupid}) 
						)
					and te_type = 'pcr' 
					and te_in_req = 1
					group by te.te_ID ", ARRAY_A);
					$this->log .= $wpdb->last_error;
					//echo $wpdb->last_query;echo '<br><br>';


					if (count($tests_rs) > 0) {

						foreach ($tests_rs as $ti => $t) {

							$t['test_name'] = $t['te_name'];

							if ($t['tg_is_covid']) {
								$arr = trpdd_cal_ncovid19(array('te_name' => $t['te_name']), $r->id, $r->instrument, $r->created_date);
								$t['pos-neg'] = $arr['pos-neg'];
							} else {
								$t['drug'] = $t['te_name'];
								$arr = tr_pdd_parse_pcr_test($t, $r->instrument, $t['tg_ID'], $r->created_date);
								$t['pos-neg'] = $arr['pos-neg'];
							}


							if (empty($t['pos-neg']) && empty($t['conc'])) {
								$has_empty = true;
								continue;
							}

							if (in_array($t['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVL', 'PVH'))) {
								$listreflexids = explode(',', $t['tg_reflex_group_ID']);
								foreach ($listreflexids as $reflexid) {
									if ($reflexid > 0  && isset($reflex_pass[$reflexid])) {
										$reflex_pass[$reflexid] = true;
										$this->log .= 'reflex pass by: ' . $t['te_name'] . '<br>';
									}
								}
							}



							if ($t['te_hide_pdf'] || $t['te_is_control']) {
								continue;
							}
							$testcode = $t['te_loinc_code'];
							$test_groups[$bcode]['tests'][] = array(
								'test_id' => $testcode,
								'test_name' => $t['test_name'],
								'pos-neg' => $t['pos-neg'],
								'group_id' => $t['tg_ID'],
								'antibiotic' => $t['tg_antibiotic']
							);
							$has_result = true;
						}
					}
				}
			}

			if (!empty($canceled_status) && $r->batch_status == $canceled_status) {
				$has_result = true;
			} else if (!empty($r->batch_status) && isset($all_rejected_status[$r->batch_status])) {
				$has_result = true;
			}




			//remove $reflex_pass
			//var_dump($reflex_pass);
			if (count($reflex_pass) > 0) {
				foreach ($reflex_pass as $gid => $refpass) {
					if (!$refpass) {
						foreach ($test_groups as $bcode => $barr) {
							foreach ($barr['tests'] as $inum => $tarr) {
								if ($tarr['group_id'] == $gid && $tarr['antibiotic']) {

									unset($test_groups[$bcode]['tests'][$inum]);
								}
							}
						}
					}
				}
			}

			if (!$has_result || empty($test_groups)) {
				$this->log .= 'result empty';
				unset($report_files[$hl7_file]);
				continue;
			}


			if (empty($r->lab_dba)) {
				$r->lab_dba = $r->lab_name;
			}

			if (empty($r->spec_received)) {
				$r->spec_received = $r->doc;
			}
			$hl7_arr = array();

			$hl7_arr['MSH'] = $this->getMSH($r->mo_control_ID, $other_info['MSH4'], $other_info['MSH5'], 'ORU', 'R01', 'ORU_R01', 15, $other_info['MSH3'], $other_info['MSH4']);

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
				'',
				array('PID.16' => '')
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


			if ($r->ref_lab_ID > 0) {
				$ref_lab = $wpdb->get_var("select lab_name from ci_labs where lab_ID = {$r->ref_lab_ID}");
				if (!empty($ref_lab)) {
					$hl7_arr[] = $this->getNTE('Referenced to ' . $ref_lab);
				}
			}


			$report_notes 	= $r->notes_report;
			$nte_notes 		= $report_notes;

			if (!empty($canceled_status) && $r->batch_status == $canceled_status) {
				$nte_notes = $canceled_status_text;
				$report_notes = $canceled_status_text;
			} else if (isset($all_rejected_status[$r->batch_status]) && !empty($nte_notes)) {
				$nte_notes = 'Rejected - ' . $nte_notes;
			}

			if (empty($report_notes) && isset($all_rejected_status[$r->batch_status])) {
				$report_notes 	= $all_rejected_status[$r->batch_status];
				$nte_notes 		= $all_rejected_status[$r->batch_status];
			}

			$adding_obx23 = $this->options['obx23'] ?? '';

			foreach ($test_groups as $groupcode => $garr) {

				if (!empty($canceled_status) && $r->batch_status == $canceled_status) {
					//nothing
				} else if (count($garr['tests']) == 0 && !isset($all_rejected_status[$r->batch_status])) {
					continue;
				}

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
					$r->spec_received,
					'',
					'',
					array('OBR.11' => '')
				);

				if (!empty($canceled_status) && $r->batch_status == $canceled_status) {
					$obxaddming = array(
						'obx5' => substr($report_notes, 0, 100),
						'obx8' => 'A',
					);
					if (!empty($adding_obx23)) {
						$obxaddming['obx23'] = $adding_obx23;
					}
					$hl7_arr[] = $this->getOBX(
						$r->lab_clia,
						$r->lab_name,
						'00000',
						'Cancelled',
						'',
						'F',
						$r->approve_date,
						$r->lab_address,
						$r->lab_city,
						$r->lab_state,
						$r->lab_zip,
						'',
						$obxaddming
					);

					continue;
				} else if (isset($all_rejected_status[$r->batch_status])) {
					$obxaddming = array(
						'obx5' => substr($report_notes, 0, 100),
						'obx8' => 'A',
					);
					if (!empty($adding_obx23)) {
						$obxaddming['obx23'] = $adding_obx23;
					}

					$hl7_arr[] = $this->getOBX(
						$r->lab_clia,
						$r->lab_name,
						$groupcode,
						'Rejected',
						'',
						$r->result_status,
						$r->approve_date,
						$r->lab_address,
						$r->lab_city,
						$r->lab_state,
						$r->lab_zip,
						'',
						$obxaddming
					);

					continue;
				}

				foreach ($garr['tests'] as $tarr) {
					$obx8 = $tarr['pos-neg'] == 'NEG' ? 'N' : 'A';

					if (isset($this->options['obx8hlow']) && $this->options['obx8hlow']) {
						if ($tarr['pos-neg'] == 'POL') {
							$obx8 = 'Low';
						} else if ($tarr['pos-neg'] == 'POH') {
							$obx8 = 'High';
						} else if ($tarr['pos-neg'] == 'POM') {
							$obx8 = 'Medium';
						}
					}


					$obxaddming = array(
						'obx8' => $obx8,
					);
					if (!empty($adding_obx23)) {
						$obxaddming['obx23'] = $adding_obx23;
					}

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
						$obxaddming
					);
				}

				if (!empty($garr['notes'])) {
					$hl7_arr[] = $this->getNTE($garr['notes']);
				}
			}
			$cli_experity_pdf = $wpdb->get_var("select meta_value from ci_clinic_meta where meta_key = 'cli_experity_pdf_meta' and cID = {$r->cID}");
			$send_pdf = isset($this->options['send_pdf']) ? $this->options['send_pdf'] : 0;
			if ($cli_experity_pdf || $send_pdf) {
				$this->pdf_format['OBX.5.1'] = 'PDF';
				$this->pdf_format['OBX.5.2'] = 'Base64';
				$this->pdf_format['OBX.5.3'] = 'content';
				$pdf_file  = trpdd_export_results_pdf($r->id, '', false, $btype);
				if ($pdf_file) {
					$hl7_arr[] = $this->getOBX(
						$r->lab_clia,
						$r->lab_name,
						'PDF',
						'PDF Report',
						'',
						$r->result_status,
						'',
						'',
						'',
						'',
						'',
						$pdf_file
					);
				}
			}

			if (!empty($canceled_status) && $r->batch_status == $canceled_status) {
				$hl7_arr[] = $this->getNTE($nte_notes);
			} else if (isset($all_rejected_status[$r->batch_status])) {

				$hl7_arr[] = $this->getNTE($nte_notes);
			} else if (!empty($r->notes_report)) {
				$hl7_arr[] = $this->getNTE($r->notes_report);
			}

			if (isset($this->options['nte_text'])) {
				$nte_text = trim($this->options['nte_text']);
				if (!empty($nte_text)) {
					$hl7_arr[] = $this->getNTE($nte_text);
				}
			}


			$hl7_data = $this->array_to_hl7($hl7_arr);


			$old_data = file_get_contents($hl7_file);
			if (!empty($old_data)) {
				if (stripos($old_data, $assen) > 0 || stripos($old_data, $r->assen) > 0) {
					ftruncate($report_files[$hl7_file], 0);
				}
			}

			fputs($report_files[$hl7_file], $hl7_data . "\r\n");

			$mo_ids[$hl7_file][] = $r->mo_ID;
		}

		if (isset($_GET['act']) && $_GET['act'] == 'rebuild') {
			return true;
		}

		foreach ($report_files as $hl7_file => $f) {
			fclose($f);
			//echo file_get_contents($hl7_file);exit;
			$local_file = $hl7_file;
			$remote_file = $this->result_folder . '/' . basename($hl7_file);
			$remote_file = str_replace('//', '/', $remote_file);
			if (filesize($local_file) < 10) {
				unlink($local_file);
				continue;
			}
			try {
				$this->uploadFile($local_file, $remote_file);
				$this->log .= "uploaded: {$remote_file} \n";
				$datet = date_i18n('Y-m-d H:i:s');
				$mo_id_str = implode(',', $mo_ids[$hl7_file]);
				var_dump($mo_id_str);
				$wpdb->query("update ci_sync_api_orders set mo_status = 1, mo_sent_dt = '{$datet}' where mo_ID in ({$mo_id_str})");
			} catch (Exception $e) {
				var_dump($e);
			}
		}
		return true;
	}

	public function clean_archive($days = 30, $limit = 15000)
	{
		$archive_dir =  '/archive/';

		$files = $this->scan_dir($archive_dir);
		$this->log .= "count files: " . count($files) . "\n";
		$count_deleted = 0;
		$count_file_pass = 0;
		try {
			foreach ($files as $file) {
				$file_path = $archive_dir . $file;
				$this->delete($file_path);
			}
		} catch (Exception $e) {
			$this->log .= "error: " . $e->getMessage() . "\n";
		}
		$this->log .= "count deleted: " . $count_deleted . "\n";
	}
}
