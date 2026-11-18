<?php


include_once('ci_hl7.php');

class CI_Api_Hl7_Ellkay
{
	public  $host = 'interface.ellkay.com';
	public  $port = 2002;
	public $order_folder = '/CLXHealth/Orders';
	public $result_folder = '/CLXHealth/Results';
	protected $conn;
	protected $sftp;
	protected $lab_id;
	protected $clinic_id;
	protected $doc_id;
	public $options;
	public $debug = false;
	public $log = '';
	public $api_id = -9;
	public $clinic_fieldname = '';
	public $clinic_send_result_field = '';

	const VT = 0x0B;
	const FS = 0x1C;
	const CR = 0x0D;
	var $is_Test = false;
	var $version = '2.5.1';

	public function __construct($options)
	{

		$this->options = $options;
		$this->lab_id = isset($options['lab_id']) ? $options['lab_id'] : 0;
		$this->clinic_id = isset($options['cli_id']) ? $options['cli_id'] : 0;
		$this->doc_id = isset($options['doc_id']) ? $options['doc_id'] : 0;
		$this->clinic_fieldname = 'cli_ellkay_id';
		$this->clinic_send_result_field = 'cli_ellkay_send_result';

		if (isset($options['host']) && !empty($options['host'])) {
			$this->host = $this->options['host'];
		}
		if (isset($options['port']) && !empty($options['port'])) {
			$this->port = $this->options['port'];
		}
		if (isset($options['order_folder']) && !empty($options['order_folder'])) {
			$this->order_folder = $this->options['order_folder'];
		}
		if (isset($options['result_folder']) && !empty($options['result_folder'])) {
			$this->result_folder = $this->options['result_folder'];
		}

		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
		}
	}

	function hl7_to_array($string)
	{

		$multis = array('OBX');

		$string = str_replace("\r\n", "\n", $string);
		$string = str_replace("\n", "\r", $string);
		$segs = explode("\r", $string);

		$out = array();

		//get delimiting characters

		if (substr($segs[0], 0, 3) != 'MSH') {

			$out['ERROR'][0] = 'Invalid HL7 Message.';
			$out['ERROR'][1] = 'Must start with MSH';

			return $out;
		}

		$delbarpos = strpos($segs[0], '|', 4);

		$delchar = substr($segs[0], 4, ($delbarpos - 4));
		$out['FLD_SEP'] = substr($delchar, 0, 1);
		$out['SFLD_SEP'] = substr($delchar, 1, 1);
		$out['REP_SEP'] = substr($delchar, 2, 1);
		$out['ESC_CHAR'] = substr($delchar, 3, 1);

		foreach ($segs as $fseg) {

			$fseg = trim($fseg);
			if (empty($fseg)) {
				continue;
			}
			$segments = explode('|', $fseg);

			$segname = $segments[0];
			$i = 0;
			$ic = isset($out[$segname]) ? count($out[$segname]) : 0;
			foreach ($segments as $k => $seg) {

				$j = 1;
				$sf = explode($out['FLD_SEP'], $seg);
				foreach ($sf as $f) {
					if (in_array($segname, $multis)) {
						$out[$segname][$ic][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
					} else {
						$out[$segname][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
					}

					$j++;
				}
				$i++;

				if ($k == 0 && $segname == 'MSH') {
					$out[$segname][$segname . '.' . $i][$segname . '.' . $i . '.1'] = '|';
					$i++;
				}
			}
		}


		return $out;
	}

	function array_to_hl7($arr)
	{
		$FLD_SEP = isset($arr['FLD_SEP']) && !empty($arr['FLD_SEP']) ? $arr['FLD_SEP'] : '^';
		$hl7 = array();
		foreach ($arr as $segname => $segments) {
			if (isset($segments[0])) {
				foreach ($segments as $seg) {
					foreach ($seg as $i => $item) {
						if (is_array($item)) {
							$item = implode($FLD_SEP, $item);
						}
						if ($i == $segname . '.0') {
							unset($seg[$i]);
							continue;
						}
						$seg[$i] = $item;
					}
					$hl7[] = implode('|', $seg);
				}
			} else {
				foreach ($segments as $i => $item) {
					if (is_array($item)) {
						$item = implode($FLD_SEP, $item);
					}
					if (($segname == 'MSH' && $item == '|') || $i == $segname . '.0') {
						unset($segments[$i]);
						continue;
					}
					$segments[$i] = $item;
				}
				$hl7[] = $segname . '|' . implode('|', $segments);
			}
		}
		$hl7 = implode("\r", $hl7);
		return $hl7;
	}

	public function add_Missing($arr, $name, $max)
	{
		$default = array();
		for ($i = 0; $i <= $max; $i++) {
			$key = $name . '.' . $i;
			$default[$key] = array(
				$key . '.1' => ($i == 0 ? $name : '')
			);
		}

		foreach ($arr as $k => $vl) {
			$default[$k] = $vl;
		}
		return $default;
	}


	public function formatdate($date, $format = 'YmdHis')
	{
		if (!empty($date)) {
			$date = date($format, strtotime($date));
		}

		return $date;
	}


	public function getMSH($control_ID, $labClia, $labName, $msgType = 'ORU', $msgTypeid = 'R01', $msgTypeC = 'ORU_R01', $max = 15, $msh5 = '', $msh6 = '', $msh15 = '')
	{
		if (empty($msh6)) {
			$msh6 = 'igniteLIS';
		}
		if (empty($msh5)) {
			$msh5 = 'igniteLIS';
		}
		$datetime = date_i18n('YmdHis');
		$arr = array(

			'MSH.1' =>
			array(
				'MSH.1.1' => '|',
			),
			'MSH.2' =>
			array(
				'MSH.2.1' => '',
				'MSH.2.2' => '~\\&',
			),
			'MSH.3' =>
			array(
				'MSH.3.1' => $labName,
			),
			'MSH.4' =>
			array(
				'MSH.4.1' => $labClia,
			),
			'MSH.5' =>
			array(
				'MSH.5.1' => $msh5,
			),
			'MSH.6' =>
			array(
				'MSH.6.1' => $msh6,
			),
			'MSH.7' =>
			array(
				'MSH.7.1' => $datetime,
			),

			'MSH.9' =>
			array(
				'MSH.9.1' => $msgType,
				'MSH.9.2' => $msgTypeid,
				'MSH.9.3' => $msgTypeC,
			),
			'MSH.10' =>
			array(
				'MSH.10.1' => $control_ID,
			),
			'MSH.11' =>
			array(
				'MSH.11.1' => $this->is_Test ? 'T' : 'P',
			),
			'MSH.12' =>
			array(
				'MSH.12.1' => $this->version,
			),
			'MSH.15' =>
			array(
				'MSH.15.1' => $msh15,
			),
		);

		return $this->add_Missing($arr, 'MSH', $max > 0 ? $max : 15);
	}

	public function getPID(
		$setid,
		$paID,
		$first_name,
		$last_name,
		$ssn,
		$gender,
		$dob,
		$address,
		$city,
		$state,
		$zip,
		$phone,
		$email,
		$mrn = '',
		$race_code = '',
		$insu = ''
	) {
		$phone = str_replace('-', '', $phone);
		$dob = str_replace('-', '', $dob);

		$arr = array(
			'PID.1' =>
			array(
				'PID.1.1' => $setid,
			),
			'PID.2' =>
			array(
				'PID.2.1' => $paID,
			),
			'PID.3' =>
			array(
				'PID.3.1' => $mrn,
			),
			'PID.4' =>
			array(
				'PID.4.1' => $insu,
			),
			'PID.5' =>
			array(
				'PID.5.1' => $last_name,
				'PID.5.2' => $first_name,
			),
			'PID.7' =>
			array(
				'PID.7.1' => $dob,
			),
			'PID.8' =>
			array(
				'PID.8.1' => $gender,
			),
			'PID.10' =>
			array(
				'PID.10.1' => $race_code,
			),
			'PID.11' =>
			array(
				'PID.11.1' => $address,
				'PID.11.2' => '',
				'PID.11.3' => $city,
				'PID.11.4' => $state,
				'PID.11.5' => $zip,
			),
			'PID.13' =>
			array(
				'PID.13.1' => $phone,
				'PID.13.2' => '',
				'PID.13.3' => '',
				'PID.13.4' => $email,
			),
			'PID.16' =>
			array(
				'PID.16.1' => 'T',
				'PID.16.2' => 'Unreported',
				'PID.16.3' => 'HL70002',
			),
			'PID.19' =>
			array(
				'PID.19.1' => $ssn,
			),
			'PID.22' =>
			array(
				'PID.22.1' => 'U',
			),
			'PID.30' =>
			array(
				'PID.30.1' => 'N',
			),
		);
		return $this->add_Missing($arr, 'PID', 30);
	}

	public function getORC($order_number, $accession, $doc, $labname, $doctor_npi, $doctor_first, $doctor_last, $order_status = 'CM')
	{
		if (empty($order_status)) {
			$order_status = 'CM';
		}
		$doc = $this->formatdate($doc);

		$arr = array(
			'ORC.1' =>
			array(
				'ORC.1.1' => 'RE',
			),
			'ORC.2' =>
			array(
				'ORC.2.1' => $order_number,
			),
			'ORC.3' =>
			array(
				'ORC.3.1' => $order_number,
			),

			'ORC.5' =>
			array(
				'ORC.5.1' => $order_status,
			),
			'ORC.7' =>
			array(
				'ORC.7.1' => '',
				'ORC.7.2' => '',
				'ORC.7.3' => '',
				'ORC.7.4' => '',
				'ORC.7.5' => '',
				'ORC.7.6' => 'R',
			),
			'ORC.9' =>
			array(
				'ORC.9.1' => $doc,
			),
			'ORC.12' =>
			array(
				'ORC.12.1' => $doctor_npi,
				'ORC.12.2' => $doctor_last,
				'ORC.12.3' => $doctor_first,
			),
		);
		return $this->add_Missing($arr, 'ORC', 12);
	}

	public function getNTE($code)
	{
		$arr = array(
			'NTE.1' =>
			array(
				'NTE.1.1' => '1',
			),
			'NTE.2' =>
			array(
				'NTE.2.1' => 'P',
			),
			'NTE.3' =>
			array(
				'NTE.3.1' => $code,
			),
		);
		return $this->add_Missing($arr, 'NTE', 3);
	}

	public function getOBR(
		$order_number,
		$accession,
		$lab_name,
		$doctor_npi,
		$doctor_first,
		$doctor_last,
		$test_id,
		$test_name,
		$resultdate,
		$result_status,
		$id13,
		$doc = '',
		$received = ''
	) {
		$doc = $this->formatdate($doc);
		$received = $this->formatdate($received);
		$resultdate = $this->formatdate($resultdate);

		$arr = array(
			'OBR.1' =>
			array(
				'OBR.1.1' => '1',
			),
			'OBR.2' =>
			array(
				'OBR.2.1' => $order_number,
			),
			'OBR.3' =>
			array(
				'OBR.3.1' => $accession,
			),
			'OBR.4' =>
			array(
				'OBR.4.1' => $test_id,
				'OBR.4.2' => $test_name,
				'OBR.4.3' => 'X',
			),
			'OBR.7' =>
			array(
				'OBR.7.1' => $doc,
			),
			'OBR.11' =>
			array(
				'OBR.11.1' => 'N',
			),
			'OBR.13' =>
			array(
				'OBR.13.1' => $id13,
			),
			'OBR.14' =>
			array(
				'OBR.14.1' => $received,
			),
			'OBR.16' =>
			array(
				'OBR.16.1' => $doctor_npi,
				'OBR.16.2' => $doctor_first,
				'OBR.16.3' => $doctor_last,
			),
			'OBR.22' =>
			array(
				'OBR.22.1' => $resultdate,
			),
			'OBR.24' =>
			array(
				'OBR.24.1' => 'LAB',
			),
			'OBR.25' =>
			array(
				'OBR.25.1' => $result_status,
			),
		);
		return $this->add_Missing($arr, 'OBR', 25);
	}


	public function getOBX(
		$lab_clia,
		$lab_name,
		$loinc_code,
		$loinc_desc,
		$pos_neg,
		$result_status,
		$result_date = '',
		$lab_address = '',
		$lab_city = '',
		$lab_state = '',
		$lab_zip = ''
	) {
		$result_date = $this->formatdate($result_date);

		$obx8 = stripos($pos_neg, 'pos') !== false
			|| strtolower($pos_neg) == 'detected'
			|| strtolower($pos_neg) == 'abnormal' ? 'A' : 'N';

		$arr = array(
			'OBX.1' =>
			array(
				'OBX.1.1' => '1',
			),
			'OBX.2' =>
			array(
				'OBX.2.1' => 'ST',
			),
			'OBX.3' =>
			array(
				'OBX.3.1' => $loinc_code,
				'OBX.3.2' => $loinc_desc,
				'OBX.3.3' => 'LN',
			),
			'OBX.5' =>
			array(
				'OBX.5.1' => $pos_neg,
			),
			'OBX.8' =>
			array(
				'OBX.8.1' => $obx8,
			),
			'OBX.11' =>
			array(
				'OBX.11.1' => $result_status,
			),
			'OBX.14' =>
			array(
				'OBX.14.1' => $result_date,
			),
			'OBX.15' =>
			array(
				'OBX.15.1' => $lab_clia,
				'OBX.15.2' => $lab_name,
				'OBX.15.3' => $lab_address,
				'OBX.15.4' => '',
				'OBX.15.5' => $lab_city,
				'OBX.15.6' => $lab_state,
				'OBX.15.7' => $lab_zip,
			),

		);

		return $this->add_Missing($arr, 'OBX', 15);
	}


	public function getSPM($id, $specimen_id, $specimen_type, $doc)
	{
		$doc = $this->formatdate($doc);
		$arr = array(
			'SPM.1' =>
			array(
				'SPM.1.1' => $id,
			),
			'SPM.2' =>
			array(
				'SPM.2.1' => $specimen_id,
			),

			'SPM.4' =>
			array(
				'SPM.4.1' => $specimen_type,
			),
			'SPM.17' =>
			array(
				'SPM.17.1' => $doc,
			),
			'SPM.18' =>
			array(
				'SPM.18.1' => $doc,
			),
		);
		return $this->add_Missing($arr, 'SPM', 18);
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
		if (! $stream)
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
		if (! $stream)
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
		if (! $rs)
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

		$files = $this->scan_dir($this->order_folder);
		$count_files = 0;

		$old_archive_dir = $this->order_folder . '/archive/';
		$archive_dir = '/archive/';

		ssh2_sftp_rmdir($this->sftp, $old_archive_dir);
		ssh2_sftp_mkdir($this->sftp, $archive_dir);

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
					'm_type' => 'hl7_ellkay'
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='hl7_ellkay'");
				if ($dbfile) {
					if ($filetime <= $dbfile->m_time) {
						//$this->movefile($file_path, $archive_dir.$file);
						//continue;
					}
				} else {
					$wpdb->insert('ci_sync_api_files', $arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}'  and m_type='hl7_ellkay'");
				}

				$done = $this->import_req_file($dbfile, $file_path);

				//move file to archive
				if ($done && !$this->is_Test) {
					$this->movefile($file_path, $archive_dir . $file);
				}

				$count_files++;
			} else {
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
		$other_info = array();
		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents .= $buffer;
		}

		$packages = explode('MSH|', $contents);

		$clinics = array();
		if (!empty($this->clinic_fieldname)) {
			$clis = $wpdb->get_results("select cID, cli_clinic, {$this->clinic_fieldname} 
			from ci_clinic 
			where {$this->clinic_fieldname} !=''");

			echo $wpdb->last_error;
			foreach ($clis as $cli) {
				$clinics[$cli->{$this->clinic_fieldname}] = $cli;
			}
		}

		$default_clinic_name = '';
		if ($this->clinic_id > 0) {
			$default_clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$this->clinic_id}' ");
		}

		$default_doctor_name = '';
		if ($this->doc_id > 0) {
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->doc_id}");
			$default_doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
		}

		foreach ($packages as $package) {
			$package = trim($package);
			if (empty($package)) continue;
			$package = 'MSH|' . $package;
			$priority_hours  = 0;

			$arr = $this->hl7_to_array($package);


			$control_id   = $arr['MSH']['MSH.10']['MSH.10.1'];
			$hl7_set_ID   = $arr['PID']['PID.1']['PID.1.1'];
			$hl7_patient_id = $arr['PID']['PID.3']['PID.3.1'];
			$pa_last      = trim($arr['PID']['PID.5']['PID.5.1']);
			$pa_first     = trim($arr['PID']['PID.5']['PID.5.2']);
			$dob          = $arr['PID']['PID.7']['PID.7.1'];
			$gender       = $arr['PID']['PID.8']['PID.8.1'];
			$address      = trim($arr['PID']['PID.11']['PID.11.1'] . ' ' . $arr['PID']['PID.11']['PID.11.2']);
			$city         = $arr['PID']['PID.11']['PID.11.3'];
			$state        = $arr['PID']['PID.11']['PID.11.4'];
			$zip          = $arr['PID']['PID.11']['PID.11.5'];
			$phone        = $arr['PID']['PID.13']['PID.13.1'];
			$email        = $arr['PID']['PID.13']['PID.13.4'];

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
			$ins_authorization_id = $arr['IN1']['IN1.36']['IN1.36.1'];

			$order_id       = $arr['ORC']['ORC.2']['ORC.2.1'];
			//$doc            = $arr['ORC']['ORC.9']['ORC.9.1'];
			$doc_npi        = $arr['ORC']['ORC.12']['ORC.12.1'];
			$doc_first      = $arr['ORC']['ORC.12']['ORC.12.3'];
			$doc_last       = $arr['ORC']['ORC.12']['ORC.12.2'];

			$peg_id          = trim($arr['OBR']['OBR.13']['OBR.13.1']);
			$obr_code        = $arr['OBR']['OBR.2']['OBR.2.1'];
			$doc            = $arr['OBR']['OBR.7']['OBR.7.1'];

			if (empty($peg_id)) {
				$peg_id = $obr_code;
			}


			$other_info['OBR_CODE'] = $obr_code;
			$other_info['NTE'] = trim($arr['NTE']['NTE.3']['NTE.3.1']);
			$other_info['MSH4'] = trim($arr['MSH']['MSH.4']['MSH.4.1']);
			$other_info['MSH5'] = trim($arr['MSH']['MSH.5']['MSH.5.1']);
			$other_info['MSH6'] = trim($arr['MSH']['MSH.6']['MSH.6.1']);
			$other_info['PID3'] = trim($arr['PID']['PID.3']['PID.3.1']);
			$other_info['PID10'] = trim($arr['PID']['PID.10']['PID.10.1']);
			$other_info['OBR41'] = trim($arr['OBR']['OBR.4']['OBR.4.1']);
			$other_info['OBR42'] = trim($arr['OBR']['OBR.4']['OBR.4.2']);
			$other_info['OBR7'] = trim($arr['OBR']['OBR.7']['OBR.7.1']);


			$other_info['ORC5.1'] = trim($arr['ORC']['ORC.5']['ORC.5.1']);
			$other_info['ORC12.1'] = trim($arr['ORC']['ORC.12']['ORC.12.1']);
			$other_info['ORC12.2'] = trim($arr['ORC']['ORC.12']['ORC.12.2']);
			$other_info['ORC12.3'] = trim($arr['ORC']['ORC.12']['ORC.12.3']);

			$other_info['OBR16.1'] = trim($arr['OBR']['OBR.16']['OBR.16.1']);
			$other_info['OBR16.2'] = trim($arr['OBR']['OBR.16']['OBR.16.2']);
			$other_info['OBR16.3'] = trim($arr['OBR']['OBR.16']['OBR.16.3']);

			$other_info['PV17'] = trim($arr['PV1']['PV1.7']['PV1.7.1']);
			$other_info['OBX31'] = trim($arr['OBX']['OBX.3']['OBX.3.1']);
			$other_info['OBX32'] = trim($arr['OBX']['OBX.3']['OBX.3.2']);
			$other_info['OBX51'] = trim($arr['OBX']['OBX.5']['OBX.5.1']);




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
			} else if (!empty($peg_id) && strlen($peg_id) > 6) {
				//$bat = $wpdb->get_row( "select * from ci_batches_pcr where oldreq = '{$peg_id}' " );
			}

			if (!$bat) {
				$assen = '';


				if (empty($assen)) {
					$assen = tr_get_new_assen();
				} else {
					//check assen exist
					$bat = $wpdb->get_row("select * from ci_batches_pcr where assen = '{$assen}'");
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

				//fix phone
				if (isset($patient['pa_phone'])) {
					$patient['pa_phone'] = str_replace(array(' ', '(', ')', '-', '.'), '', $patient['pa_phone']);
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
					'ins_authorization_id' => $ins_authorization_id . '',
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

			$clinic_name = $default_clinic_name;
			$cli_id = $this->clinic_id;

			if (isset($clinics[$other_info['MSH4']])) {
				$cli = $clinics[$other_info['MSH4']];
				$cli_id = $cli->cID;
				$clinic_name = $cli->cli_clinic;
			}

			$doc_id = $bat->doc_ID;
			$doctor_name = '';
			if ($doc_id == 0) {
				if (!empty($doc_first) && !empty($doc_last)) {
					$doc_id = $wpdb->get_var("select doc_ID from ci_doc where  
										 doc_first='{$doc_first}' and doc_last='{$doc_last}' 
										 and doc_npi = '{$doc_npi}' ");
					if ($doc_id == 0) {
						$wpdb->insert('ci_doc', array(
							'doc_first' => $doc_first,
							'doc_last' => $doc_last,
							'doc_npi' => $doc_npi,
						));
						$doc_id = $wpdb->insert_id;
						if ($this->debug) {
							$this->log .= "Added new doctor: {$doc_npi} - {$doc_first} {$doc_last}\n";
						}
					}
					$doctor_name = $doc_last . ', ' . $doc_first;
				} else {
					$doc_id = $this->doc_id;
					$doctor_name = $default_doctor_name;
				}
			} else {
				$doctor_name = $bat->requesting_physician;
			}


			if ($doc_id > 0 && $cli_id > 0) {
				$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $cli_id));
			}


			$tests_requested = 'GROUP_' . $group_covid_id;
			$source = 'Nasal Swab';
			$dagnostic_codes = 'Z20.828';

			$patient_name = $pa_last . ', ' . $pa_first;
			$data         = array(
				'assen'                => $assen,
				'patient_id'           => $patientid,
				'patient_name'         => $patient_name,
				'lab_ID'               => $this->lab_id,
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
				'oldreq'                 => $peg_id . '',
				'patient_signature'      => 'onfile',
				'paid'                   => $this->api_id,
			);
			if ($priority_hours > 0) {
				$data['priority_hours'] = $priority_hours;
			}



			if ($bat) {
				$mid = $bat->mid;

				if ($bat->cli_ID > 0) {
					unset($data['cli_ID']);
					unset($data['requesting_practice']);
				}
				if ($bat->doc_ID > 0) {
					//unset($data['doc_ID']);
					//unset($data['requesting_physician']);
				}
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
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';

		$hl7_dir = WP_CONTENT_DIR . '/log/hl7_ellkay/';
		if (!file_exists($hl7_dir)) {
			mkdir($hl7_dir);
		}
		$pos_neg = array(
			'' => '',
			'NEG' => 'Negative',
			'POS' => 'Positive',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'PRP' => 'Positive',
			'INV' => 'Invalid',
			'NGE' => 'Negative',
		);
		$select_fields = '';
		if (!empty($this->clinic_fieldname)) {
			$select_fields .= ", cli.{$this->clinic_fieldname} as clinic_code ";
		}

		$clids = array();
		if (!empty($this->clinic_send_result_field)) {
			$clids = $wpdb->get_col("select cID from ci_clinic_meta where meta_key='{$this->clinic_send_result_field}' and meta_value='1' ");
		}
		$where = '';
		if ($over_id > 0) {
			$where .= " and re.id = {$over_id} ";
		} else if (!empty($clids)) {

			$where .= " and ( mo.mo_status = 0 OR ( mo.mo_ID IS NULL and ba.cli_ID in (" . implode(',', $clids) . ") ) ) ";
		} else {
			$where .= " and mo.mo_status = 0 ";
		}


		$sql = "select re.id,
					ba.mid,
					ba.batch_id,
					ba.assen,
					ba.patient_name,
					ba.doc,
					ba.spec_received,
					
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
				JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID

				LEFT JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
				LEFT JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='hl7_ellkay'
				
				where re.estatus = 'C' {$where}
				";

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
			$other_info = json_decode($r->other_info, true);

			if (!empty($r->m_file)) {
				$filename = basename($r->m_file);
			} else {
				$filename = $r->assen . '.hl7';
			}
			$filename = str_replace('.json', '.hl7', $filename);
			$hl7_file = $hl7_dir . $filename;

			if (!isset($report_files[$hl7_file])) {
				$report_files[$hl7_file] = fopen($hl7_file, 'a+');
				$mo_ids[$hl7_file] = array();
			}
			$assen = !empty($r->oldreq) ? $r->oldreq : $r->assen;
			$nte_code = !empty($other_info['NTE']) ? $other_info['NTE'] : $assen;


			$arr = trpdd_cal_ncovid19(array(), $r->id, $r->instrument, $r->created_date);
			$r->result_status = 'F';
			$test_id = !empty($other_info['OBR41']) ? $other_info['OBR41'] : '94500-6';
			$test_name = !empty($other_info['OBR42']) ? $other_info['OBR42'] : 'nCoV-2019';
			if (!empty($this->options['obr41'])) {
				$test_id = $this->options['obr41'];
			}
			if (!empty($this->options['obr42'])) {
				$test_name = $this->options['obr42'];
			}
			if (!empty($r->clinic_code)) {
				$other_info['MSH4'] = $r->clinic_code;
			}

			$hl7_arr = array();
			$hl7_arr['MSH'] = $this->getMSH($r->mo_control_ID, $other_info['MSH6'], $r->lab_dba_init, 'ORU', 'R01', 'ORU_R01', 12, 'ELLKAY', $other_info['MSH4']);

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
				$r->pa_mrn,
				$other_info['PID10']
			);


			if (empty($other_info['ORC12.1'])) {
				$other_info['ORC12.1'] = $r->doc_npi;
				$other_info['ORC12.2'] = $r->doc_last;
				$other_info['ORC12.3'] = $r->doc_first;
			}
			$hl7_arr['ORC'] = $this->getORC(
				$r->mo_test_ID,
				$assen,
				$r->doc,
				$r->lab_dba_init,
				$other_info['ORC12.1'],
				$other_info['ORC12.3'],
				$other_info['ORC12.2'],
				$other_info['ORC5.1']
			);

			$hl7_arr['NTE'] = $this->getNTE($nte_code);

			$obr_code = !empty($other_info['OBR_CODE']) ? $other_info['OBR_CODE'] : $assen;

			if (empty($other_info['OBR16.1'])) {
				$other_info['OBR16.1'] = $r->doc_npi;
				$other_info['OBR16.2'] = $r->doc_last;
				$other_info['OBR16.3'] = $r->doc_first;
			}

			if (empty($r->spec_received)) {
				$r->spec_received = $r->doc;
			}

			$hl7_arr['OBR'] = $this->getOBR(
				$r->mo_test_ID,
				$obr_code,
				$r->lab_dba_init,
				$other_info['OBR16.1'],
				$other_info['OBR16.2'],
				$other_info['OBR16.3'],
				$test_id,
				$test_name,
				$r->approve_date,
				$r->result_status,
				$r->mo_peg_ID,
				$r->doc,
				$r->spec_received
			);

			$hl7_arr['OBX'] = $this->getOBX(
				$r->lab_clia,
				$r->lab_dba_init,
				$test_id,
				$test_name,
				$pos_neg[$arr['pos-neg']],
				$r->result_status,
				$r->approve_date,
				$r->lab_address,
				$r->lab_city,
				$r->lab_state,
				$r->lab_zip
			);

			$hl7_data = $this->array_to_hl7($hl7_arr);

			$old_data = file_get_contents($hl7_file);
			if (!empty($old_data)) {
				if (stripos($old_data, $assen) > 0 || stripos($old_data, $r->assen) > 0) {
					ftruncate($report_files[$hl7_file], 0);
				}
			}

			fputs($report_files[$hl7_file], $hl7_data . "\r\n");

			if (empty($r->mo_ID)) {
				//need insert
				$wpdb->insert('ci_sync_api_orders', array(
					'mo_file_id'    => $this->api_id,
					'mo_mid'        => $r->mid,
					'mo_control_ID' => $r->mo_control_ID . '',
					'mo_test_ID'    => $r->mo_test_ID . '',
					'mo_patient_ID' => $r->mo_patient_ID . '',
					'mo_peg_ID' => $r->mo_peg_ID . '',
					'other_info' => json_encode($other_info) . '',
					'mo_status' => 0,
				));
				$r->mo_ID = $wpdb->insert_id;
			}
			$mo_ids[$hl7_file][] = $r->mo_ID;
		}

		foreach ($report_files as $hl7_file => $f) {
			fclose($f);
			//echo file_get_contents($hl7_file);exit;
			$local_file = $hl7_file;
			$remote_file = $this->result_folder . '/' . basename($hl7_file);
			try {
				$this->uploadFile($local_file, $remote_file);
				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
				}

				if (!empty($mo_ids[$hl7_file])) {
					$mo_id_str = implode(',', $mo_ids[$hl7_file]);
					$wpdb->query("update ci_sync_api_orders set mo_status = 1 where mo_ID in ({$mo_id_str})");
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		}
	}
}
