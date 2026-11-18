<?php
include_once('ci_hl7.php');

class CI_Api_sftp_Allscripts extends CI_Hl7
{
	protected $conn;
	protected $sftp;
	private  $host = '';
	private  $port = 22;
	public $order_folder = '/Orders';
	public $result_folder = '/Results';
	public $order_log_dir = WP_CONTENT_DIR . '/log/allscripts_orders/';
	public $hl7_dir = WP_CONTENT_DIR . '/log/allscripts/';
	public $options;
	public $debug = false;
	public $log = '';
	public $api_id = -41;
	public $log_file_slug = 'allscripts';
	public $localdir = ABSPATH . 'allscripts';


	public function __construct()
	{
		$this->options = get_option('tr_sftp_allscripts_st', array());

		$this->host = isset($this->options['host']) ? $this->options['host'] : '';
		if (isset($this->options['port']) && $this->options['port'] > 0) {
			$this->port = $this->options['port'];
		}

		$this->order_folder = isset($this->options['order_folder']) ? $this->options['order_folder'] : '';
		$this->result_folder = isset($this->options['result_folder']) ? $this->options['result_folder'] : '';

		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
		}
	}

	public function cron()
	{

		$this->connect();
		$this->login();
		try {
			$this->sync_reqs();
		} catch (Exception $e) {
		}
		try {
			$this->sync_reports();
		} catch (Exception $e) {
		}
	}

	public function connect()
	{
		if ($this->host == 'localhost') {
			return true;
		}
		$this->conn  = ssh2_connect($this->host, $this->port);

		if (!$this->conn) {
			throw new Exception('Unable to connect: ' . $this->host . ':' . $this->port);
		}
	}


	public function login()
	{
		if ($this->host == 'localhost') {
			return true;
		}
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
		if ($this->host == 'localhost') {
			return $this->localdir . $path;
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
			closedir($handle);
		}


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
			if ($this->host == 'localhost') {
				return rename($this->localdir . $file_path, $this->localdir . $move_path);
			}
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


		if (count($files) == 0) {
			$this->log .= "No files in Orders folder!";
		}

		if ($this->debug) {
			var_dump($files);
		}
		foreach ($files as $file) {
			$file_path = $this->order_folder . '/' . $file;
			if (stripos($file, '.hl7') > 0 || stripos($file, '.txt') > 0 || stripos($file, '.json') > 0) {
				$filetime = $this->getFileTime($file_path);
				$arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => 'allscripts'
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='allscripts'");
				if ($dbfile) {
					if ($filetime <= $dbfile->m_time) {
						//$this->delete($file_path);
						//$this->movefile($file_path, $archive_dir.$file);
						//continue;
					}
				} else {
					$wpdb->insert('ci_sync_api_files', $arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}'  and m_type='allscripts'");
				}

				$done = $this->import_req_file($dbfile, $file_path);

				//move file to archive
				if ($done && !$this->is_Test) {
					$this->delete($file_path);
					//$this->movefile($file_path, $archive_dir.$file);
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
		}


		$packages = explode('MSH|', $contents);


		$clis = $wpdb->get_results("select cID, cli_clinic, cli_allscripts_id from ci_clinic where cli_allscripts_id !=''");
		$clinics = array();
		foreach ($clis as $cli) {
			$clinics[$cli->cli_allscripts_id] = $cli;
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

			$arr = $this->hl7_to_array($package, array('OBR'));

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

			$ssn2 = str_replace('0', '', $ssn);
			if (empty($ssn2) || stripos($ssn, 'n/a') !== false) {
				$ssn = '';
			}



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
			$ins_member_id = $arr['IN1']['IN1.36']['IN1.36.1'];
			$ins_authorization_id = ''; //$arr['IN1']['IN1.36']['IN1.36.1'];

			$order_id       = $arr['ORC']['ORC.2']['ORC.2.1'];
			$doc            = $arr['ORC']['ORC.9']['ORC.9.1'];
			$doc_npi        = $arr['ORC']['ORC.12']['ORC.12.1'];
			$doc_last         = $arr['ORC']['ORC.12']['ORC.12.2'];
			$doc_first         = $arr['ORC']['ORC.12']['ORC.12.3'];

			$peg_id          = isset($arr['OBR']['OBR.13']['OBR.13.1']) ? $arr['OBR']['OBR.13']['OBR.13.1'] : '';
			$old_reg        = isset($arr['OBR']['OBR.2']['OBR.2.1']) ? $arr['OBR']['OBR.2']['OBR.2.1'] : '';

			$other_info['OBR_code'] = array();
			foreach ($arr['OBR'] as $obr) {
				$peg_id          = $obr['OBR.13']['OBR.13.1'];
				$old_reg        = $obr['OBR.2']['OBR.2.1'];

				$other_info['OBR41'] = trim($obr['OBR.4']['OBR.4.1']);
				$other_info['OBR42'] = trim($obr['OBR.4']['OBR.4.2']);
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


			if ($arr['OBX']) {
				foreach ($arr['OBX'] as $obx) {
					if (empty($phone) && stripos($obx['OBX.3']['OBX.3.2'], 'phone') !== false) {
						$phone = $obx['OBX.5']['OBX.5.1'];
					} else if (stripos($obx['OBX.3']['OBX.3.2'], 'email') !== false) {
						$email = $obx['OBX.5']['OBX.5.1'];
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


			if (empty($order_id)) {
				$this->log .= " empty $order_id";
				return false;
			}


			if (!empty($dob)) {
				$dob    = date('Y-m-d', strtotime($dob));
			}


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
				$assen = tr_get_new_assen();

				if (empty($assen)) {
					continue;
				}
			} else {
				$assen = $bat->assen;
			}
			$this->log .= 'order assen:' . $assen . "\n";

			$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;
			if ($patientid == 0 || isset($_GET['act'])) {

				$patientid = $wpdb->get_var($wpdb->prepare(
					"select pa_ID from ci_patients where pa_first = %s
				                and pa_last=%s and pa_dob=%s and pa_city = %s ",
					$pa_first,
					$pa_last,
					$dob,
					$city
				));
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

					$wpdb->insert('ci_patients', $patient);
					$patientid = $wpdb->insert_id;
					echo $wpdb->last_error;
				} else {
					$wpdb->update('ci_patients', $patient, array('pa_ID' => $patientid));
					echo $wpdb->last_error;
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
					'ins_member_id' => $ins_member_id . '',
					'ins_group_id' => $ins_group_id . '',
					'ins_authorization_id' => $ins_authorization_id . '',
					'ins_dob' => $ins_dob . '',
					'pa_ID' => $patientid . '',

				);
				if ($ins_id == 0) {
					$wpdb->insert('ci_insurances', $ins_arr);
					$ins_id = $wpdb->insert_id;
					echo $wpdb->last_error;
					if ($this->debug) {
						$this->log .= "Added new Insurance: {$insurance} => {$ins_id}\n";
					}
				}
			}

			//insurance2
			$ins_id2 = 0;
			if (isset($arr['IN1_2'])) {
				$insurance2      = $arr['IN1_2']['IN1.4']['IN1.4.1'];
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

					);
					if ($ins_id2 == 0) {
						$wpdb->insert('ci_insurances', $ins_arr2);
						$ins_id2 = $wpdb->insert_id;
						if ($this->debug) {
							$this->log .= "Added new Insurance: {$insurance} => {$ins_id2}\n";
						}
					}
				}
			}






			if (!empty($doc_first) && !empty($doc_last) && !empty($doc_npi)) {
				$doc_id = $wpdb->get_var($wpdb->prepare("select doc_ID from ci_doc where  
										 doc_first=%s  
										    and doc_last=%s 
										  and doc_npi = %s ", $doc_first, $doc_last, $doc_npi));

				if ($doc_id == 0) {
					$doc_id = $wpdb->get_var("select doc_ID from ci_doc where doc_npi = '{$doc_npi}'");
				}
				if ($doc_id == 0) {
					$wpdb->insert('ci_doc', array('doc_first' => $doc_first, 'doc_last' => $doc_last, 'doc_npi' => $doc_npi));
					$doc_id = $wpdb->insert_id;
					if ($this->debug) {
						$this->log .= "Added new doctor: {$doc_first} {$doc_last}\n";
					}
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


			$source = $arr['OBR']['OBR.15']['OBR.15.1'];
			foreach ($arr['OBX'] as $obx) {
				if (stripos($obx['OBX.3']['OBX.3.2'], 'SOURCE') !== false && !empty($obx['OBX.5']['OBX.5.1'])) {
					$source = $obx['OBX.5']['OBX.5.1'];
				}
			}
			if (!empty($source)) {
				//get source from code
				$sourcenew = $wpdb->get_var("select ts_name from ci_test_specimens where ts_code = '{$source}' ");
				if (!empty($sourcenew)) {
					$source = $sourcenew;
				}
			}
			if (empty($dagnostic_codes)) {
				$dagnostic_codes = 'Z20.828';
			}
			$tests_requested = array();

			foreach ($other_info['OBR_code'] as $i => $bcode) {
				if (!empty($bcode)) {
					$testarr = $wpdb->get_results("select te.te_ID, g.tg_ID, g.tg_only_group
					from ci_test_panels te
					JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
					where (te_loinc_code='{$bcode}' or tg_short_name = '{$bcode}' or tg_cpt_code= '{$bcode}' )
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
					} else {
						unset($other_info['OBR_code'][$i]);
					}
				} else {
					unset($other_info['OBR_code'][$i]);
				}
			}


			if (count($tests_requested) == 0) {
				$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where (tg_is_covid=1 or tg_name ='nCoV-2019') and tg_show_req = 1
			                and tg_type='pcr' and (tg_date_end ='' or tg_date_end is null) ");
				if ($group_covid_id == 0) {
					$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where tg_show_req = 1
			                and tg_type='pcr' and (tg_date_end ='' or tg_date_end is null) 
							order by tg_position asc");
				}
				$tests_requested[] = 'GROUP_' . $group_covid_id;

				$tests = $wpdb->get_col("select te_ID from ci_test_panels te
				JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_id
				where tg_ID = {$group_covid_id} or tg_parent = {$group_covid_id}
				and te_type = 'pcr' and te.te_in_req = 1");

				$tests_requested = array_merge($tests_requested, $tests);
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
				'type_of_specimen'     => $source,
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
				$mo_ID = $wpdb->insert_id;
			}
			if ($r === false) {
				$this->log .= $wpdb->last_error;
				return false;
			}
		}

		return $mid > 0;
	}

	public function sync_reports($over_id = 0)
	{
		global $wpdb;
		$btype = 'pcr';
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';
		$result_tbl = 'ci_results_pcr';

		if (!file_exists($this->hl7_dir)) {
			mkdir($this->hl7_dir);
		}
		$pos_neg = array(
			'' => '',
			'NEG' => 'Not Detected',
			'POS' => 'Detected',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'PRP' => 'Detected',
			'INV' => 'Invalid',
			'NGE' => 'Not Detected',
			'POH' => 'Detected',
			'POL' => 'Detected',
			'POM' => 'Detected',
		);

		$sql = "select re.id,
					ba.mid,
					ba.batch_id,
					ba.assen,
					ba.patient_name,
					ba.doc,
					ba.spec_received,
					ba.panel_tests_requested,
					ba.type_of_specimen,
					
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
					
					doc.doc_first,
					doc.doc_last,
					doc.doc_npi
					
				FROM {$batch_tbl} ba
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
				JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='allscripts'
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				left JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				where re.estatus = 'C'
				";
		if ($over_id > 0) {
			$sql .= " and re.id = {$over_id} ";
		} else {
			$sql .= " and mo.mo_status = 0 ";
		}
		$rs = $wpdb->get_results($sql);


		if ($this->debug && count($rs) == 0) {
			if (!empty($wpdb->last_error)) {
				$this->log .= "error: " . $wpdb->last_error;
			}

			$this->log .= "Not found results!";
		}

		$mo_ids = array();

		foreach ($rs as $r) {
			$other_info = json_decode($r->other_info, true);

			if (empty($other_info['OBR_code']) && !empty($other_info['OBR41'])) {
				$other_info['OBR_code'] = array($other_info['OBR41']);
			}

			$filename = basename($r->m_file);
			//$filename = str_replace(array('{','}'),'',$filename);
			$filename = str_replace('.json', '.hl7', $filename);
			$hl7_file = $this->hl7_dir . $filename;

			$r->result_status = 'F';

			$assen 		= !empty($r->oldreq) ? $r->oldreq : $r->assen;
			$test_groups		= array();
			$group_ids = array();



			if (empty($r->lab_dba)) {
				$r->lab_dba = $r->lab_name;
			}

			if (empty($r->spec_received)) {
				$r->spec_received = $r->doc;
			}
			$hl7_arr = array();

			$hl7_arr['MSH'] = $this->getMSH($r->mo_control_ID, 'IGNITE', 'IGNITE', 'ORU', 'R01', 'ORU_R01', 15, $other_info['MSH3'], $other_info['MSH4']);

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
						where (tg_short_name = '{$bcode}' or tg_cpt_code = '{$bcode}')
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
						te.*,
						g.tg_ID, 
						g.tg_only_group,
						g.tg_short_name,
						g.tg_cpt_code,
						g.tg_is_covid,
						g.tg_name,
						rs.*
						from ci_test_panels te
						JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
						JOIN {$result_tbl} rs ON (rs.drug = te.te_name or rs.drug=te.te_abrev or rs.drug=te.te_abrev2) 
											and rs.report_id = {$r->id} and (rs.group_id = 0 or rs.group_id is null or rs.group_id  = te.te_group_id)
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


			$groupcode = '';
			$has_obr = false;
			foreach ($test_groups as $groupcode => $garr) {

				if (empty($garr['tests'])) continue;


				$has_obr = true;
				$hl7_arr[] = $this->getOBR(
					$r->mo_test_ID,
					'',
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


			$pdf_file  = trpdd_export_results_pdf($r->id, '', false, $btype);

			if (!$has_obr) {
				$hl7_arr[] = $this->getOBR(
					$r->mo_test_ID,
					$groupcode,
					$groupcode . '^L',
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
			}


			$hl7_arr[] = $this->getOBX(
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
				$pdf_file,
				array('obx2' => 'ED', 'obx5' => 'IGNITE')
			);



			$hl7_data = $this->array_to_hl7($hl7_arr);


			file_put_contents($hl7_file, $hl7_data);

			if (isset($_GET['act']) && $_GET['act'] == 'rebuild') {
				continue;
			}


			$local_file = $hl7_file;
			$remote_file = $this->result_folder . '/' . basename($hl7_file);
			$remote_file = str_replace('//', '/', $remote_file);
			try {
				$this->uploadFile($local_file, $remote_file);
				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
				}
				$wpdb->query("update ci_sync_api_orders set mo_status = 1 where mo_ID = {$r->mo_ID} ");
			} catch (Exception $e) {
				var_dump($e);
			}
		}


		return true;
	}

	public function clean_archive($days = 30, $limit = 15000)
	{
		$archive_dir = '/archive/';

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
