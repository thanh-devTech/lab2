<?php
include_once('ci_hl7.php');

class CI_Api_Hl7_Athena_Sftp2 extends CI_Hl7
{
	private  $host = 'sftp.athenahealth.com';
	private  $port = 22;

	protected $conn;
	protected $sftp;
	public $lab_id;
	public $clinic_id;
	public $doc_id;

	public $debug = false;
	public $log = '';
	public $api_id = 'hl7_athena2';
	public $api_num = -31;
	public $options = array();
	public $accounts = array('adt' => 'ADT', 'dft' => 'DFT', 'siu' => 'SIU');

	public $logdir = WP_CONTENT_DIR . '/log/athena_sftp2/';

	public function __construct($options = array())
	{

		if (empty($options)) {
			$options = get_option('tr_hl7_athena_sftp2_st');
		}
		$this->options = $options;

		$this->lab_id = $options['lab_id'];
		$this->clinic_id = $options['cli_id'];
		$this->doc_id = $options['doc_id'];

		if (!empty($options['host'])) {
			$this->host = $options['host'];
		}
		if (!empty($options['port'])) {
			$this->port = $options['port'];
		}

		if (!file_exists($this->logdir)) {
			wp_mkdir_p($this->logdir);
		}

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

	public function getfolder($account)
	{
		if (empty($account)) {
			$account = array_keys($this->accounts)[0];
		}
		return $this->options[$account . '_folder'];
	}

	public function login($account)
	{
		if (empty($account)) {
			$account = array_keys($this->accounts)[0];
		}
		$username = $this->options[$account . '_username'];
		$password = $this->options[$account . '_password'];
		$this->log .= 'login: ' . $username . "\n";
		$result = ssh2_auth_password($this->conn, $username, $password);

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


	public function cron()
	{
		$this->connect();
		$this->login('adt');
		$this->send_new_patients();
	}


	public function send_new_patients($assen = '')
	{
		global $wpdb;


		$sql = "select  
		ba.mid,
		ba.lab,
		ba.doc,
		ba.assen,
		ba.patient_name,
		ba.requesting_practice,
		ba.requesting_physician,
		ba.pverify_status,
		ba.claim_id,
		ba.result_pdf,
		ba.insurance,
		ba.paid,
		ba.billing_notes,
		ba.priority_hours,
		ba.panel_tests_requested,
		ba.dagnostic_codes1 as dagnostic_codes,
		
		lab.lab_name,
		lab.lab_state,
		
		doc.doc_ID,
		doc.doc_npi,
		doc.doc_pecos,
		doc.doc_first,
		doc.doc_last,
		
		pa.*,
		
		ins.ins_first,
		ins.ins_last,
		ins.ins_company,
		ins.ins_address,
		ins.ins_city,
		ins.ins_state,
		ins.ins_zip,
		ins.ins_phone,
		ins.ins_dob,
		ins.ins_member_id,
		ins.ins_group_id,
		ins.ins_relation,
		ins.ins_mnemonic,
		ins.ins_fin_mnemonic

		from ci_batches_pcr ba
		JOIN ci_labs lab On lab.lab_ID = ba.lab_id
		JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
		left join ci_doc doc ON doc.doc_ID = ba.doc_ID
		left join ci_clinic cli ON cli.cID = ba.cli_ID
		left join ci_insurances ins ON ba.insurance1 = ins.ins_ID 
		left join ci_sync_api_orders o ON o.mo_mid = ba.mid 
				and o.mo_btype = 'pcr' 
				and o.mo_file_id='{$this->api_num}' 
		where ba.cli_ID = {$this->clinic_id}
		and batch_id >0
		
		";

		if (!empty($assen)) {
			$sql .= " and ba.assen = '{$assen}' ";
		} else {
			$sql .= " and o.mo_ID is null ";
		}
		$sql .= "  limit 1";

		$rows 		= $wpdb->get_results($sql);
		//var_dump($rows);exit;
		echo ($wpdb->last_error);

		$files = array();
		$count_sent = 0;
		foreach ($rows as $r) {

			$hl7_arr = array();
			$control_id = time();
			$hl7_arr['MSH'] = $this->getMSH(
				$control_id,
				$r->lab_name,
				'IgniteLIS',
				'ADT',
				'A28',
				'',
				12,
				'ATHENA',
				'99999^MA - athenahealth Testing',
			);
			$hl7_arr['EVN'] = $this->getEVN('A28', '', 'username');

			//format phone
			if (!empty($r->pa_phone)) {
				$phone1 = substr($r->pa_phone, 0, 3);
				$phone2 = substr($r->pa_phone, 3, 3);
				$phone3 = substr($r->pa_phone, 6);
				$r->pa_phone = '(' . $phone1 . ')' . $phone2 . '-' . $phone3;
			}
			$hl7_arr['PID'] = $this->getPID(
				1,
				$r->patient_id,
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
				$r->pa_mrn
			);

			$hl7_arr['PV1'] = $this->getPV1($r->doc_npi, $r->doc_first, $r->doc_last, $r->assen);
			$hl7_arr['GT1'] = $this->getGT1(
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
				$r->pa_mrn
			);


			$hl7_data = $this->array_to_hl7($hl7_arr);


			$filename = $r->assen . '.hl7';
			$local_file = $this->logdir . '/' . $filename;
			$files[$r->mid] = $local_file;

			//echo '<textarea style="width:1000px;height:500px">';
			//echo ($hl7_data);
			//echo '</textarea>';
			//exit;
			file_put_contents($local_file, $hl7_data);

			if (isset($_GET['act2']) && $_GET['act2'] == 'rebuildadt') {
				return $local_file;
			}

			try {

				$remote_file = $this->getfolder('adt') . '/' . $filename;

				$this->uploadFile($local_file, $remote_file);
				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
				}


				$update = array(
					'mo_file_id'    => $this->api_num,
					'mo_mid'        => $r->mid,
					'mo_btype'      => 'pcr',
					'mo_peg_ID'      => date_i18n('Y-m-d H:i:s'),
				);
				//var_dump($update);

				$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_mid='{$r->mid}' and mo_btype = 'pcr'
							and mo_file_id = {$this->api_num} ");

				if ($orderdb && $orderdb->mo_ID > 0) {
					$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $orderdb->mo_ID));
				} else {
					$update['mo_status'] = 1;
					$r = $wpdb->insert('ci_sync_api_orders', $update);
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		}

		return $files;
	}

	public function send_update_patients($assen = '')
	{
		global $wpdb;


		$sql = "select  
		ba.mid,
		ba.lab,
		ba.doc,
		ba.assen,
		ba.patient_name,
		ba.requesting_practice,
		ba.requesting_physician,
		ba.pverify_status,
		ba.claim_id,
		ba.result_pdf,
		ba.insurance,
		ba.paid,
		ba.billing_notes,
		ba.priority_hours,
		ba.panel_tests_requested,
		ba.dagnostic_codes1 as dagnostic_codes,
		
		lab.lab_name,
		lab.lab_state,
		
		doc.doc_ID,
		doc.doc_npi,
		doc.doc_pecos,
		doc.doc_first,
		doc.doc_last,
		
		pa.*,
		
		ins.ins_first,
		ins.ins_last,
		ins.ins_company,
		ins.ins_address,
		ins.ins_city,
		ins.ins_state,
		ins.ins_zip,
		ins.ins_phone,
		ins.ins_dob,
		ins.ins_member_id,
		ins.ins_group_id,
		ins.ins_relation,
		ins.ins_mnemonic,
		ins.ins_fin_mnemonic,
		o.mo_ID

		from ci_batches_pcr ba
		JOIN ci_labs lab On lab.lab_ID = ba.lab_id
		JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
		left join ci_doc doc ON doc.doc_ID = ba.doc_ID
		left join ci_clinic cli ON cli.cID = ba.cli_ID
		left join ci_insurances ins ON ba.insurance1 = ins.ins_ID 
		join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = 'pcr'
		where ba.cli_ID = {$this->clinic_id} 
		and o.mo_file_id='{$this->api_num}' 
		and batch_id >0
		and o.mo_ID > 0
		";

		if (!empty($assen)) {
			$sql .= " and ba.assen = '{$assen}' ";
		} else {
			$sql .= " and o.mo_status < 2 ";
		}
		$sql .= "  limit 1";

		$rows 		= $wpdb->get_results($sql);
		//var_dump($rows);exit;
		echo ($wpdb->last_error);

		$files = array();
		$count_sent = 0;
		foreach ($rows as $r) {

			$hl7_arr = array();
			$control_id = time();
			$hl7_arr['MSH'] = $this->getMSH(
				$control_id,
				$r->lab_name,
				'IgniteLIS',
				'ADT',
				'A31',
				'',
				12,
				'ATHENA',
				'99999^MA - athenahealth Testing',
			);
			$hl7_arr['EVN'] = $this->getEVN('A28', '', 'username');

			//format phone
			if (!empty($r->pa_phone)) {
				$phone1 = substr($r->pa_phone, 0, 3);
				$phone2 = substr($r->pa_phone, 3, 3);
				$phone3 = substr($r->pa_phone, 6);
				$r->pa_phone = '(' . $phone1 . ')' . $phone2 . '-' . $phone3;
			}
			$hl7_arr['PID'] = $this->getPID(
				1,
				$r->patient_id,
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
				$r->pa_mrn
			);

			$hl7_arr['PV1'] = $this->getPV1($r->doc_npi, $r->doc_first, $r->doc_last, $r->assen);
			$hl7_arr['GT1'] = $this->getGT1(
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
				$r->pa_mrn
			);


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
				$r->ins_group_id,
			);


			$hl7_data = $this->array_to_hl7($hl7_arr);


			$filename = $r->assen . '.hl7';
			$local_file = $this->logdir . '/update_' . $filename;
			$files[$r->mid] = $local_file;

			//echo '<textarea style="width:1000px;height:500px">';
			//echo ($hl7_data);
			//echo '</textarea>';
			//exit;
			file_put_contents($local_file, $hl7_data);

			if (isset($_GET['act2']) && $_GET['act2'] == 'rebuildadtupdate') {
				return $local_file;
			}

			try {

				$remote_file = $this->getfolder('adt') . '/' . $filename;

				$this->uploadFile($local_file, $remote_file);
				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
				}


				$update = array(
					'mo_status'        => 2,
				);

				$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $r->mo_ID));
			} catch (Exception $e) {
				var_dump($e);
			}
		}

		return $files;
	}



	public function send_charges($assen = '')
	{
		global $wpdb;
		$dagnodescs = tr_pdd_get_dagnostic_codes('pcr');
		$pos_neg = array(
			'' => '',
			'NEG' => 'Not Detected',
			'POS' => 'Detected',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'PRP' => 'Positive',
			'INV' => 'Invalid',
			'NGE' => 'Negative',
		);

		$sql = "select  
		ba.mid,
		ba.lab,
		ba.doc,
		ba.assen,
		ba.patient_name,
		ba.requesting_practice,
		ba.requesting_physician,
		ba.pverify_status,
		ba.claim_id,
		ba.result_pdf,
		ba.insurance,
		ba.paid,
		ba.cli_ID,
		ba.billing_notes,
		ba.priority_hours,
		ba.panel_tests_requested,
		ba.dagnostic_codes1 as dagnostic_codes,
		
		lab.lab_name,
		lab.lab_state,
		
		doc.doc_ID,
		doc.doc_npi,
		doc.doc_pecos,
		doc.doc_first,
		doc.doc_last,
		
		pa.*,
		
		ins.ins_first,
		ins.ins_last,
		ins.ins_company,
		ins.ins_address,
		ins.ins_city,
		ins.ins_state,
		ins.ins_zip,
		ins.ins_phone,
		ins.ins_dob,
		ins.ins_member_id,
		ins.ins_group_id,
		ins.ins_relation,
		ins.ins_mnemonic,
		ins.ins_fin_mnemonic,

		re.id,
		re.instrument

		from ci_batches_pcr ba
		JOIN ci_reports_pcr re ON re.patient_id = ba.assen
		JOIN ci_labs lab On lab.lab_ID = ba.lab_id
		JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
		left join ci_doc doc ON doc.doc_ID = ba.doc_ID
		left join ci_clinic cli ON cli.cID = ba.cli_ID
		left join ci_insurances ins ON ba.insurance1 = ins.ins_ID 
		left join ci_sync_api_orders o ON o.mo_mid = ba.mid 
				and o.mo_btype = 'pcr' 
				and o.mo_file_id='{$this->api_num}' 
		where ba.cli_ID = {$this->clinic_id}
		and ba.batch_id >0
		and re.estatus = 'C'
		";

		if (!empty($assen)) {
			$sql .= " and ba.assen = '{$assen}' ";
		} else {
			$sql .= " and (o.mo_test_ID is null or o.mo_test_ID !=1) ";
		}
		$sql .= "  limit 10";

		$rows 		= $wpdb->get_results($sql);
		//var_dump($rows);
		echo ($wpdb->last_error);
		///echo ($wpdb->last_query);
		//exit;
		if (count($rows) == 0) {
			echo 'not found';
		}

		$files = array();
		$count_sent = 0;
		foreach ($rows as $r) {

			$hl7_arr = array();
			$control_id = time();
			$hl7_arr['MSH'] = $this->getMSH(
				$control_id,
				$r->lab_name,
				'IgniteLIS',
				'DFT',
				'P03',
				'',
				12,
				'ATHENA',
				'99999^MA - athenahealth Testing',
			);
			$hl7_arr['EVN'] = $this->getEVN('DFT', 'Rea', 'User');

			//format phone
			if (!empty($r->pa_phone)) {
				$phone1 = substr($r->pa_phone, 0, 3);
				$phone2 = substr($r->pa_phone, 3, 3);
				$phone3 = substr($r->pa_phone, 6);
				$r->pa_phone = '(' . $phone1 . ')' . $phone2 . '-' . $phone3;
			}

			$apid = $r->mid;
			$hl7_arr['PID'] = $this->getPID(
				1,
				$r->patient_id,
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
				'',
				'',
				$apid
			);

			$hl7_arr['PV1'] = $this->getPV1($r->doc_npi, $r->doc_first, $r->doc_last, $r->assen);

			$hl7_arr['OBX'] = array();
			$test_ids = array();
			foreach (explode(',', $r->panel_tests_requested) as $tid) {
				if ($tid > 0) {
					$test_ids[] = $tid;
				}
			}

			$results = tr_pdd_get_results_by_testsid($r->id, 'pcr', $test_ids, array(), $r->instrument, true);

			foreach ($results as $arr) {
				$hl7_arr['OBX'][] = $this->getOBX(
					$r->lab_clia,
					$r->lab_name,
					$arr['te_abrev'],
					$arr['te_name'],
					$pos_neg[$arr['pos-neg']],
					$r->result_status,
					'',
					'',
					'',
					'',
					'',
				);
			}
			$r->btype = 'pcr';
			global $ci_dax_codes;

			$cpt_codes_arr = tr_billing_get_cpt_code($r);
			if (!is_array($ci_dax_codes)) $ci_dax_codes = array();
			//var_dump($ci_dax_codes);
			$dax_codes = implode(',', $ci_dax_codes);
			if (!is_array($cpt_codes_arr)) {
				$cpt_codes_arr = explode(',', $cpt_codes_arr);
			}
			if (empty($cpt_codes_arr)) {
				$cpt_codes_arr = array('');
			}
			//var_dump($ci_dax_codes);

			foreach ($cpt_codes_arr as $bcode) {
				$daxcode = $ci_dax_codes[$bcode];
				$hl7_arr[] = $this->getFT1(
					$r->doc,
					$r->dagnostic_codes,
					$bcode,
					1,
					173, //Rendering Provider
					119, //Department
					$daxcode
				);
			}



			$dagcodes_arr = array();
			foreach (explode(',', $r->dagnostic_codes) as $code) {
				$desc_code = $dagnodescs[$code];
				$desc_code = str_replace($code, '', $desc_code);
				$desc_code = trim($desc_code, '\- ');
				$dagcodes_arr[] = $code . '^' . $desc_code;
			}

			$hl7_arr['DG1'] = $this->getDG1($dagcodes_arr);




			$hl7_data = $this->array_to_hl7($hl7_arr);


			$filename = $r->assen . '_dft.hl7';

			$local_file = $this->logdir . '/' . $filename;
			$files[$r->mid] = $local_file;

			//echo '<textarea style="width:1000px;height:500px">';
			//echo ($hl7_data);
			//echo '</textarea>';
			//exit;
			file_put_contents($local_file, $hl7_data);

			if (isset($_GET['act2']) && $_GET['act2'] == 'rebuild_dft') {
				return $local_file;
			}

			try {
				$filename = $r->assen . '.hl7';
				$remote_file = $this->getfolder('dft') . '/' . $filename;

				$this->uploadFile($local_file, $remote_file);
				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
				}


				$update = array(
					'mo_file_id'    => $this->api_num,
					'mo_mid'        => $r->mid,
					'mo_btype'        => 'pcr',
					'mo_test_ID'        => '1',
					'mo_set_ID'        => date_i18n('Y-m-d H:i:s'),
				);
				//var_dump($update);

				$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_mid='{$r->mid}' and mo_btype = 'pcr'
							and mo_file_id = {$this->api_num} ");

				if ($orderdb && $orderdb->mo_ID > 0) {
					$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $orderdb->mo_ID));
				} else {
					$r = $wpdb->insert('ci_sync_api_orders', $update);
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		}

		return $files;
	}



	public function sync_siu()
	{
		global $wpdb;

		$dir =  $this->getfolder('siu');
		$old_archive_dir = $dir . '/archive/';
		$archive_dir = '/archive/';

		$files = $this->scan_dir($dir);
		$count_files = 0;

		ssh2_sftp_rename($this->sftp, $old_archive_dir, $archive_dir);

		ssh2_sftp_mkdir($this->sftp, $archive_dir);

		if (count($files) == 0) {
			$this->log .= "No files in Orders folder!";
		}

		if ($this->debug) {
			var_dump($files);
		}
		foreach ($files as $file) {
			$file_path = $dir . '/' . $file;
			if (stripos($file, '.') > 0) {
				$filetime = $this->getFileTime($file_path);
				$arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => $this->api_id
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='{$this->api_id}'");
				if ($dbfile) {
					if ($filetime <= $dbfile->m_time) {
						//$this->movefile($file_path, $archive_dir.$file);
						//continue;
					}
				} else {
					$wpdb->insert('ci_sync_api_files', $arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}'  and m_type='{$this->api_id}'");
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
		return $count_files;
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

		$groups = array();
		$rs = $wpdb->get_results("select * from ci_test_groups");
		foreach ($rs as $r) {
			$other = json_decode($r->tg_other_options, true);
			$code = isset($other['athena']) ? strtoupper(trim($other['athena'])) : '';

			if (!empty($code)) {
				$groups[$code] = $r->tg_ID;
			}
		}

		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		$contents = '';

		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents .= $buffer;
		}

		$hl7_dir = WP_CONTENT_DIR . '/log/athena_orders/';
		if (!file_exists($hl7_dir)) {
			mkdir($hl7_dir);
		}

		file_put_contents($hl7_dir . basename($file_path), $contents);

		$packages = explode('MSH|', $contents);


		$clinic_name = '';
		if ($this->clinic_id > 0) {
			$clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$this->clinic_id}' ");
		}

		$default_doctor_name = '';
		if ($this->doc_id > 0) {
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->doc_id}");
			$default_doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
		}

		foreach ($packages as $package) {
			$other_info = array();
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
			$doc            = $arr['ORC']['ORC.9']['ORC.9.1'];
			$doc_npi        = $arr['ORC']['ORC.12']['ORC.12.1'];
			$doc_first         = $arr['ORC']['ORC.12']['ORC.12.2'];
			$doc_last         = $arr['ORC']['ORC.12']['ORC.12.3'];

			if (empty($order_id)) {
				$order_id = $hl7_patient_id;
			}


			if (empty($doc)) {
				$doc = $arr['OBR']['OBR.6']['OBR.6.1'];
			}

			$other_info['ORC12.1'] = $arr['ORC']['ORC.12']['ORC.12.1'];
			$other_info['ORC12.2'] = $arr['ORC']['ORC.12']['ORC.12.2'];
			$other_info['ORC12.3'] = $arr['ORC']['ORC.12']['ORC.12.3'];

			$other_info['OBR16.1'] = $arr['OBR']['OBR.16']['OBR.16.1'];
			$other_info['OBR16.2'] = $arr['OBR']['OBR.16']['OBR.16.2'];
			$other_info['OBR16.3'] = $arr['OBR']['OBR.16']['OBR.16.3'];

			$peg_id          = trim($arr['OBR']['OBR.13']['OBR.13.1']);
			$obr_code        = $arr['OBR']['OBR.2']['OBR.2.1'];
			$obr3            = $arr['OBR']['OBR.3']['OBR.3.1'];

			if (empty($peg_id)) {
				$peg_id = $obr_code;
			}
			if (stripos($peg_id, ':') > 0) {
				list($t, $peg_id) = explode(':', $peg_id);
				$peg_id = trim($peg_id);
			}
			$peg_id = substr($peg_id, 0, 30);

			if (stripos($arr['OBR']['OBR.15']['OBR.15.1'], '12h') !== false) {
				$priority_hours = 12;
			} else if (stripos($arr['OBR']['OBR.15']['OBR.15.1'], '24h') !== false) {
				$priority_hours = 24;
			}
			if ($arr['OBX']) {
				foreach ($arr['OBX'] as $obx) {
					if ($obx['OBX.5']['OBX.5.1'] == 12 || $obx['OBX.5']['OBX.5.1'] == 24) {
						$priority_hours = $obx['OBX.5']['OBX.5.1'];
					}
				}
			}

			$other_info['OBR_CODE'] = $obr_code;
			$other_info['OBR_3'] = $obr3;
			$other_info['NTE'] = trim($arr['NTE']['NTE.3']['NTE.3.1']);
			$other_info['MSH4'] = trim($arr['MSH']['MSH.4']['MSH.4.1'] . '^' . $arr['MSH']['MSH.4']['MSH.4.2']);
			$other_info['MSH5'] = trim($arr['MSH']['MSH.5']['MSH.5.1']);
			$other_info['MSH6'] = trim($arr['MSH']['MSH.6']['MSH.6.1'] . '^' . $arr['MSH']['MSH.6']['MSH.6.2']);
			$other_info['PID3'] = trim($arr['PID']['PID.3']['PID.3.1']);
			$other_info['PID10'] = trim($arr['PID']['PID.10']['PID.10.1']);
			$other_info['OBR41'] = trim($arr['OBR']['OBR.4']['OBR.4.1']);
			$other_info['OBR42'] = trim($arr['OBR']['OBR.4']['OBR.4.2']);
			$other_info['OBR7'] = trim($arr['OBR']['OBR.7']['OBR.7.1']);
			$other_info['PV17'] = trim($arr['PV1']['PV1.7']['PV1.7.1']);
			$other_info['OBX31'] = trim($arr['OBX']['OBX.3']['OBX.3.1']);
			$other_info['OBX32'] = trim($arr['OBX']['OBX.3']['OBX.3.2']);
			$other_info['OBX51'] = trim($arr['OBX']['OBX.5']['OBX.5.1']);


			$ref_assen = !empty($obr3) ? $obr3 : $peg_id;
			preg_match("/^([0-9]+)/", $ref_assen, $ref_matches);
			if (isset($ref_matches[1]) && !empty($ref_matches[1])) {
				$ref_assen = $ref_matches[1];
			}

			if (empty($order_id)) {
				$this->log .= " empty orderid\n";
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
				if (stripos($other_info['NTE'], 'rpb') === 0) {
					$assen = $other_info['NTE'];
				} else if (isset($arr['NTE'])) {
					$assen = $this->findinarr($arr['NTE'], 'rpb');
				}
				if (isset($arr['IN1']) && empty($assen)) {
					$assen = $this->findinarr($arr['IN1'], 'rpb');
				}

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

			$this->log .= "assen: {$assen} \n";

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

			$doc_id = $this->doc_id;
			$doctor_name = $default_doctor_name;
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
					if ($this->debug) {
						$this->log .= "Added new doctor: {$doc_first} {$doc_last}\n";
					}
				}
				$doctor_name = $doc_last . ', ' . $doc_first;
			}



			if ($doc_id > 0 && $this->clinic_id > 0) {
				$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $this->clinic_id));
			}


			$tests_requested = 'GROUP_' . $group_covid_id;
			$source = 'Nasal Swab';

			$group_code = isset($arr['OBR']['OBR.4']['OBR.4.1']) ? strtoupper(trim($arr['OBR']['OBR.4']['OBR.4.1'])) : '';
			if (!empty($group_code) && isset($groups[$group_code])) {
				$tests_requested = $this->get_tests_by_group($groups[$group_code]);
				$other_info['panel_id'] = $groups[$group_code];
				$source = $this->get_type_by_group($groups[$group_code]);
			}


			$dagnostic_codes = array();
			if (isset($arr['DG1'])) {
				foreach ($arr['DG1'] as $dg1) {
					if (!empty($dg1['DG1.3']['DG1.3.1'])) {
						$dagnostic_codes[] = $dg1['DG1.3']['DG1.3.1'];
					}
				}
			}

			$dagnostic_codes = implode(',', $dagnostic_codes);

			$tests_requested = '';

			$patient_name = $pa_last . ', ' . $pa_first;
			$data         = array(
				'assen'                => $assen,
				'patient_id'           => $patientid,
				'patient_name'         => $patient_name,
				'lab_ID'               => $this->lab_id,
				'doc_ID'               => intval($doc_id),
				'cli_ID'               => intval($this->clinic_id),
				'doc'                  => $doc,
				'dob'                  => $dob,
				'requesting_physician' => $doctor_name . '',
				'requesting_practice'  => $clinic_name . '',
				'type_of_specimen'     => $source,
				'dagnostic_codes1'     => $dagnostic_codes,
				'panel_tests_requested' => $tests_requested,
				'insurance'             => $insurance . '',
				'insurance1'             => intval($ins_id),
				'notes'                 => $obr3 . '',
				'oldreq'                 => $ref_assen . '',
				'patient_signature'      => 'onfile',
				'paid'                   => $this->api_num,
			);
			if ($priority_hours > 0) {
				$data['priority_hours'] = $priority_hours;
			}

			//$data['batch_id'] = date('Ymd',strtotime($data['doc'])).'-1';

			if ($bat) {
				$mid = $bat->mid;
				//$_GET['updateclinic'] = $_GET['updatedoctor'] = true;
				if ($bat->cli_ID > 0 && !isset($_GET['updateclinic'])) {
					unset($data['cli_ID']);
					unset($data['requesting_practice']);
				}
				if ($bat->doc_ID > 0 && !isset($_GET['updatedoctor'])) {
					unset($data['doc_ID']);
					unset($data['requesting_physician']);
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
				'mo_set_ID'     => substr($hl7_set_ID, 0, 30),
				'mo_patient_ID' => substr($hl7_patient_id, 0, 30),
				'mo_peg_ID' => substr($peg_id, 0, 30),
				'other_info' => json_encode($other_info) . '',
			);
			//var_dump($update);

			if ($orderdb && $orderdb->mo_ID > 0) {
				$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $orderdb->mo_ID));
			} else {
				$r = $wpdb->insert('ci_sync_api_orders', $update);
			}
			var_dump($r);
			if ($r === false) {
				$this->log .= $wpdb->last_error;
				return false;
			}
		}

		return $mid > 0;
	}

	function get_tests_by_group($groupid)
	{
		global $wpdb;
		$groupsids = $wpdb->get_col("select tg_ID from ci_test_groups
		where tg_parent = {$groupid}");
		$groupsids[] = $groupid;
		$groupsids_str = implode(',', $groupsids);
		$testids = $wpdb->get_col("select te_ID from ci_test_panels
		where te_group_id in ({$groupsids_str}) 
		and te_in_req = 1");

		return 'GROUP_' . $groupid . ',' . implode(',', $testids);
	}

	function get_type_by_group($groupid)
	{
		global $wpdb;
		$source = 'Nasal Swab';
		$rs = $wpdb->get_var("select ts_name from ci_test_specimens 
			where ts_group_id = {$groupid}
			order by ts_ID asc
			limit 1");

		if (!empty($rs)) {
			$source = $rs;
		}

		return $source;
	}
}
