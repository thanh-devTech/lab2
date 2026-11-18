<?php

include_once('ci_hl7.php');

/*
demo sftp://demo@lis-demo.com /boycodon123
*/

class CI_Api_Base_sFtpHl7 extends CI_Hl7
{
	protected $conn;
	protected $sftp;
	public $order_log_dir 		= WP_CONTENT_DIR . '/log/sftphl7_orders/';
	public $results_log_dir 	= WP_CONTENT_DIR . '/log/sftphl7_results/';
	public $results_in_folder 	= WP_CONTENT_DIR . '/log/sftphl7_results/';
	public $archive_dir = '/archive/';
	public $options;
	public $debug 		= false;
	public $log 		= '';
	public $api_id 		= 0;
	public $log_file_slug = 'sftphl7';
	public $api_key 	= '';

	public $enable_send_orders = false;
	public $enable_send_results = false;
	public $enable_receive_orders = false;
	public $enable_receive_results = false;
	public $enable_revese_result = false;
	public $send_all_resutls = false;
	public $send_result_ct = false;
	public $send_result_range = false;
	public $dont_send_pdf_no_pos = false;
	public $send_when_meta = '';

	public $sent_field = 'mo_status';

	public $send_all_panels = false;

	public $send_all_results = false;
	public $send_result_not_final = false;
	public $folder_by_panel = false;
	public $folder_by_btype = false;

	public $result_mic 		= false;

	public $test_code_order = '';
	public $test_code_result = '';
	public $group_code_order = '';
	public $group_code_result = '';
	public $clinic_fieldname = '';
	public $clinic_send_result_field = '';
	public $lab_msh5 		= '';
	public $lab_msh6 		= '';
	public $msh5_by_reflab 	= false;

	public $all_btypes = array();

	public function __construct($options)
	{

		$this->options = $options;

		if (empty($this->all_btypes)) {
			$this->all_btypes = tr_pdd_get_batch_types();
		}
	}

	function createSftpUser($username, $password)
	{

		$home = "/home/$username";

		// Create user without shell
		exec("sudo useradd -m -d $home -s /usr/sbin/nologin $username");

		// Set password
		exec("echo \"$username:$password\" | sudo chpasswd");

		// Join the sftp group
		exec("sudo usermod -aG sftpusers $username");

		// Fix chroot permissions
		exec("sudo chown root:root $home");
		exec("sudo chmod 755 $home");

		// Create writable subfolder
		exec("sudo mkdir -p $home/files");
		exec("sudo chown $username:$username $home/files");

		// Reload SFTP-only SSH service
		exec("sudo systemctl reload sshd24");

		return true;
	}

	public function cron()
	{
		$this->connect();
		$this->login();

		if ($this->enable_send_orders) {
			foreach ($this->all_btypes as $btype) {
				try {
					$this->log .= "send orders for btype: {$btype} \n";
					$this->send_orders($btype, 0, 10);
				} catch (Exception $e) {
					$this->log .= "error: " . $e->getMessage() . "\n";
				}
			}
		}

		if ($this->enable_receive_orders) {
			try {
				$this->log .= "receive orders \n";

				$this->receive_orders();
			} catch (Exception $e) {
				$this->log .= "error: " . $e->getMessage() . "\n";
			}
		}

		if ($this->enable_send_results) {
			foreach ($this->all_btypes as $btype) {
				try {
					$this->log .= "send results for btype: {$btype} \n";
					$this->send_results($btype);
				} catch (Exception $e) {
					$this->log .= "error: " . $e->getMessage() . "\n";
				}
			}
		}

		if ($this->enable_receive_results) {
			$this->log .= "receive results \n";
			try {
				$this->receive_results();
			} catch (Exception $e) {
				$this->log .= "error: " . $e->getMessage() . "\n";
			}
		}
	}

	public function sync_reqs()
	{
		if ($this->enable_send_orders) {
			foreach ($this->all_btypes as $btype) {
				$this->send_orders($btype);
			}
		} else if ($this->enable_receive_orders) {
			$this->receive_orders();
		}
	}

	public function sync_reports()
	{
		if ($this->enable_send_results) {
			foreach ($this->all_btypes as $btype) {
				$this->send_results($btype);
			}
		} else if ($this->enable_receive_results) {
			$this->receive_results();
		}
	}

	public function rebuild($btype, $mid)
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

		//echo 'Connecting to: ' . $this->options['host'] . ':' . $this->options['port'];

		$this->conn = ssh2_connect($this->options['host'], $this->options['port']);


		if (!$this->conn) {
			throw new Exception('Unable to connect: ' . $this->options['host'] . ':' . $this->options['port']);
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

	public function clean_archive($days = 30, $limit = 1000)
	{
		$limit_datetime = time() - $days * 86400;

		$files = $this->scan_dir($this->archive_dir);
		$this->log .= "count files: " . count($files) . "\n";
		$count_deleted = 0;
		try {
			foreach ($files as $file) {
				$file_path = $this->archive_dir . $file;
				if ($this->getFileTime($file_path) < $limit_datetime) {
					$this->delete($file_path);
					$count_deleted++;
				}
			}
		} catch (Exception $e) {
			$this->log .= "error: " . $e->getMessage() . "\n";
		}
		$this->log .= "count deleted: " . $count_deleted . "\n";
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

		if (isset($this->options['ref_lab_ids']) && count($this->options['ref_lab_ids']) > 0) {
			$this->options['ref_lab_ids_str'] = implode(', ', $this->options['ref_lab_ids']);
			$where .= " and ba.ref_lab_ID in( {$this->options['ref_lab_ids_str']} )";
		} else if (isset($this->options['ref_lab_id']) && $this->options['ref_lab_id'] > 0) {
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
			echo 'countrows: ' . count($rows);
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
			$msh3 = $this->lab_msh5;
			if ($this->msh5_by_reflab && $r->ref_lab_ID > 0) {
				$msh3 = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$r->ref_lab_ID} ");
			}
			if ($msh3 == 'MAK' && $this->lab_msh5 == 'Mako') {
				$msh3 = 'MAKO';
			}

			if (!empty($this->clinic_fieldname)) {
				$msh4 = $r->{$this->clinic_fieldname};
			}

			$hl7_arr = array();
			$hl7_arr['MSH'] = $this->getMSH(
				$control_id,
				$msh4,
				$msh3,
				'ORM',
				'O01',
				'',
			);
			if (!empty($r->pa_middle)) {
				$r->pa_first .= '^' . $r->pa_middle;
			}
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

			$has_obr = false;

			foreach ($group_ids as $gid) {
				$group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$gid}");


				$group_code = $group->tg_cpt_code;

				if (!empty($this->group_code_order)) {
					$group_code2 = $group->{$this->group_code_order};
					if (!empty($group_code2)) {
						$group_code = $group_code2;
					}
				}

				if (empty($group_code)) {
					echo 'empty group code for ' . $group->tg_name . '<br>';
					continue;
				}

				if ($this->enable_receive_results) {
					$r->mo_peg_ID = '';
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
				$has_obr = true;
			}

			if (!$has_obr) {
				echo $r->assen . ' no obr<br>';
				return false;
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

			if (!isset($this->options['orders_folder'])) {
				$this->options['orders_folder'] = $this->options['out_folder'];
			}

			$remote_file = $this->options['orders_folder'] . '/' . $filename;
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


	public function receive_orders()
	{
		global $wpdb;

		if (!isset($this->options['orders_folder'])) {
			$this->options['orders_folder'] = isset($this->options['in_folder']) ? $this->options['in_folder'] : $this->options['order_folder'];
		}


		$files = $this->scan_dir($this->options['orders_folder']);
		$count_files = 0;

		if (count($files) == 0) {
			$this->log .= "No files in Orders folder!";
		}

		if ($this->debug) {
			var_dump($files);
		}
		foreach ($files as $file) {
			$file_path = $this->options['orders_folder'] . '/' . $file;
			if (stripos($file, '.hl7') > 0 || stripos($file, '.dat') > 0 || stripos($file, '.txt') > 0 || stripos($file, '.json') > 0) {
				$filetime = $this->getFileTime($file_path);

				$logfile = $this->order_log_dir . basename($file_path);
				if (file_exists($logfile) && $filetime > filemtime($logfile)) {
					unlink($logfile);
				}

				$file_arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => $this->log_file_slug
				);

				$parr = $this->parse_file($file_path);
				if (!$parr) {
					continue;
				}

				if (isset($this->options['clinicid_range']) && !empty($this->options['clinicid_range'])) {
					$clinicid = trim($parr['MSH']['MSH.4']['MSH.4.1']);
					list($idfrom, $idto) = explode('-', $this->options['clinicid_range']);
					if ($clinicid < $idfrom || $clinicid > $idto) {
						continue;
					}
				}

				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='{$this->log_file_slug}'");
				if ($dbfile) {
					if ($filetime <= $dbfile->m_time) {
						//$this->delete($file_path);
						//$this->movefile($file_path, $archive_dir.$file);
						continue;
					}
				} else {
					$wpdb->insert('ci_sync_api_files', $file_arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}'  and m_type='{$this->log_file_slug}'");
				}


				$done = $this->import_req_file($dbfile, $parr);

				//move file to archive
				if ($done && !$this->is_Test) {
					try {
						$this->delete($file_path);
					} catch (Exception $e) {
						$this->log .= "error delete file: " . $e->getMessage() . "\n";
					}
					//$this->movefile($file_path, $archive_dir.$file);
				}
				$count_files++;
			}
		}

		if ($this->debug && $count_files == 0) {
			$this->log .= " No file need sync\n";
		}
	}

	public function fix_import($file_path, $field, $mid, $btype)
	{
		global $wpdb;

		$arr = $this->parse_file($file_path);
		if (!$arr) {
			return false;
		}

		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$batch = $wpdb->get_row("select * from {$batch_tbl} where mid = {$mid}");
		if (!$batch) return;

		if ($field == 'gender') {
			$gender       = $arr['PID']['PID.8']['PID.8.1'];

			if (!empty($gender)) {
				$wpdb->update('ci_patients', array('pa_gender' => $gender), array('pa_ID' => $batch->patient_id));
			}
		}
	}

	public function parse_file($file_path)
	{
		if ($this->debug) {
			$this->log .= "file: " . $file_path . "\n";
		}


		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		if (!$stream) {
			$this->log .= "error open file: " . $file_path . "\n";
			return false;
		}
		$contents = '';
		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents .= $buffer;
		}

		if (empty($contents)) {
			return false;
		}

		//log file
		if (!file_exists($this->order_log_dir)) {
			mkdir($this->order_log_dir);
		}

		$logfile = $this->order_log_dir . basename($file_path);
		if (!file_exists($logfile)) {
			file_put_contents($logfile, $contents);
		}



		$arr = $this->hl7_to_array($contents, array('OBR', 'DG1'));

		return $arr;
	}


	public function import_req_file($dbfile, $arr)
	{
		global $wpdb;
		$mid = 0;

		$btype 		= 'pcr';
		$batch_tbl 	= 'ci_batches_pcr';
		$other_info = array();

		if (is_string($arr)) {
			$arr = $this->parse_file($arr);
		}

		if (!$arr) {
			return false;
		}


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
		if ($this->options['cli_id'] > 0) {
			$default_clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$this->options['cli_id']}' ");
		}

		$default_doctor_name = '';
		if ($this->options['doc_id'] > 0) {
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->options['doc_id']}");
			$default_doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
		}



		$priority_hours  = 0;

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
		$pa_middle     = isset($arr['PID']['PID.5']['PID.5.3']) ? trim($arr['PID']['PID.5']['PID.5.3']) : '';
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



		$insurance      = isset($arr['IN1']['IN1.4']['IN1.4.1']) ? $arr['IN1']['IN1.4']['IN1.4.1'] : '';
		$ins_address    = isset($arr['IN1']['IN1.5']['IN1.5.1']) ? $arr['IN1']['IN1.5']['IN1.5.1'] : '';
		$ins_city       = isset($arr['IN1']['IN1.5']['IN1.5.3']) ? $arr['IN1']['IN1.5']['IN1.5.3'] : '';
		$ins_state      = isset($arr['IN1']['IN1.5']['IN1.5.4']) ? $arr['IN1']['IN1.5']['IN1.5.4'] : '';
		$ins_zip        = isset($arr['IN1']['IN1.5']['IN1.5.5']) ?  $arr['IN1']['IN1.5']['IN1.5.5'] : '';
		$ins_phone      = isset($arr['IN1']['IN1.7']['IN1.7.1']) ?  $arr['IN1']['IN1.7']['IN1.7.1'] : '';
		$ins_group_id   = isset($arr['IN1']['IN1.8']['IN1.8.1']) ? $arr['IN1']['IN1.8']['IN1.8.1'] : '';
		$ins_group_name = isset($arr['IN1']['IN1.9']['IN1.9.1']) ? $arr['IN1']['IN1.9']['IN1.9.1'] : '';
		$ins_first      = isset($arr['IN1']['IN1.16']['IN1.16.1']) ? $arr['IN1']['IN1.16']['IN1.16.1'] : '';
		$ins_last       = isset($arr['IN1']['IN1.16']['IN1.16.2']) ? $arr['IN1']['IN1.16']['IN1.16.2'] : '';
		$ins_dob        = isset($arr['IN1']['IN1.18']['IN1.18.1']) ? $arr['IN1']['IN1.18']['IN1.18.1'] : '';
		$ins_member_id  = isset($arr['IN1']['IN1.36']['IN1.36.1']) ? $arr['IN1']['IN1.36']['IN1.36.1'] : '';
		$ins_authorization_id = ''; //$arr['IN1']['IN1.36']['IN1.36.1'];

		$order_id       = $arr['ORC']['ORC.2']['ORC.2.1'];
		$doc            = $arr['ORC']['ORC.9']['ORC.9.1'];
		$doc_npi        = $arr['ORC']['ORC.12']['ORC.12.1'];
		$doc_last       = $arr['ORC']['ORC.12']['ORC.12.2'];
		$doc_first      = $arr['ORC']['ORC.12']['ORC.12.3'];

		$doc_obr		= '';
		$doc_msh		= $arr['MSH']['MSH.7']['MSH.7.1'];

		$peg_id          = isset($arr['OBR']['OBR.13']['OBR.13.1']) ? $arr['OBR']['OBR.13']['OBR.13.1'] : '';
		$old_reg        = isset($arr['OBR']['OBR.2']['OBR.2.1']) ? $arr['OBR']['OBR.2']['OBR.2.1'] : '';


		$type_of_specimens = array();


		$other_info['OBR_code'] = array();
		$other_info['OBR_names'] = array();
		foreach ($arr['OBR'] as $obr) {
			$peg_id          = $obr['OBR.13']['OBR.13.1'];
			$old_reg        = $obr['OBR.2']['OBR.2.1'];

			$other_info['OBR41'] = trim($obr['OBR.4']['OBR.4.1']);
			$other_info['OBR42'] = trim($obr['OBR.4']['OBR.4.2']);
			$other_info['OBR7'] = trim($obr['OBR.7']['OBR.7.1']);

			$other_info['OBR_code'][] = $other_info['OBR41'];
			$other_info['OBR_names'][$other_info['OBR41']] = $other_info['OBR42'];

			$other_info['OBR16.1'] = trim($obr['OBR.16']['OBR.16.1']);
			$other_info['OBR16.2'] = trim($obr['OBR.16']['OBR.16.2']);
			$other_info['OBR16.3'] = trim($obr['OBR.16']['OBR.16.3']);

			if (stripos($obr['OBR.15']['OBR.15.1'], '12h') !== false) {
				$priority_hours = 12;
			} else if (stripos($obr['OBR.15']['OBR.15.1'], '24h') !== false) {
				$priority_hours = 24;
			}

			//check doc

			if (isset($obr['OBR.7']['OBR.7.1']) && empty($doc_obr)) {
				$doc_obr = $obr['OBR.7']['OBR.7.1'];
			}
		}


		if (stripos($phone, 'n/a') !== false || stripos($phone, 'na') !== false || strlen($phone) > 18) {
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


		$other_info['PV1.3.4'] = isset($arr['PV1']['PV1.3']['PV1.3.4']) ? trim($arr['PV1']['PV1.3']['PV1.3.4']) : '';
		if (!empty($other_info['PV1.3.4']) && stripos($other_info['PV1.3.4'], '&') !== false) {
			list($other_info['PV1.3.4_name'], $other_info['PV1.3.4']) = explode('&', $other_info['PV1.3.4']);
		}



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

		$lab_id = $this->options['lab_id'] ?? CI_Base_Lab::get_current_lab_id();
		$cli_id = $this->options['cli_id'] ?? 0;
		$doc_id = $this->options['doc_id'] ?? 0;
		$clinic_name = $default_clinic_name;
		$doctor_name = $default_doctor_name;

		if (!empty($other_info['MSH4']) && isset($clinics[$other_info['MSH4']])) {
			$cli = $clinics[$other_info['MSH4']];
			$cli_id = $cli->cID;
			$clinic_name = $cli->cli_clinic;
			echo 'clinic found: ' . $clinic_name . ' by msh4: ' . $other_info['MSH4'] . '<br>';
		} else if (!empty($other_info['PV1.3.4']) && isset($clinics[$other_info['PV1.3.4']])) {
			$cli = $clinics[$other_info['PV1.3.4']];
			$cli_id = $cli->cID;
			$clinic_name = $cli->cli_clinic;
			echo 'clinic found: ' . $clinic_name . ' by pv1.3.4: ' . $other_info['PV1.3.4'] . '<br>';
		}


		if (empty($cli_id)) {
			$this->log .= " empty cli_id";
			return false;
		}


		if (empty($order_id)) {
			$this->log .= " empty order_id";
			return false;
		}


		$dob    = date('Y-m-d', strtotime($dob));

		if (!empty($doc_obr)) {
			$doc_obr    = date('Y-m-d H:i:s', strtotime($doc_obr));
			if (substr($doc_obr, 0, 4) > 2000) {
				$doc = $doc_obr;
			}
		}

		if (empty($doc) && !empty($doc_msh)) {
			$doc_msh    = date('Y-m-d H:i:s', strtotime($doc_msh));
			if (substr($doc_msh, 0, 4) > 2000) {
				$doc = $doc_msh;
			}
		}

		if (empty($doc)) {
			$doc = date_i18n('Y-m-d H:i:s');
		}
		$doc    = date('Y-m-d H:i:s', strtotime($doc));
		$gender = stripos($gender, 'u') !== false ? 'U' : $gender;
		$gender = stripos($gender, 'f') !== false ? 'F' : 'M';


		//check btypes and groups
		$tests_requested = array();
		$type_arr_str = '';
		if (count($this->all_btypes) > 0) {
			$type_arr_str = implode("','", $this->all_btypes);
		}

		$bbtypes = array();
		$bcodebytypes = array();
		$bnamebytypes = array();



		foreach ($other_info['OBR_code'] as $i => $bcode) {

			if (!empty($bcode)) {

				$sqlgroup = "select * from ci_test_groups 
					where ( tg_cpt_code='{$bcode}' or tg_short_name = '{$bcode}' or tg_name = '{$bcode}') ";
				if (!empty($type_arr_str)) {
					$sqlgroup .= " and tg_type in ('{$type_arr_str}') ";
				}
				$group = $wpdb->get_row($sqlgroup);


				if ($group) {

					if (!in_array($group->tg_type, $bbtypes)) {
						$bbtypes[] = $group->tg_type;
						if (!isset($tests_requested[$group->tg_type])) {
							$tests_requested[$group->tg_type] = array();
							$bcodebytypes[$group->tg_type] = array();
						}
					}
					$bcodebytypes[$group->tg_type][] = $bcode;
					$bnamebytypes[$group->tg_type][$bcode] = $other_info['OBR_names'][$bcode];


					if (!in_array('GROUP_' . $group->tg_ID, $tests_requested[$group->tg_type])) {
						$tests_requested[$group->tg_type][] = 'GROUP_' . $group->tg_ID;
					}

					$testarr = $wpdb->get_results("select te.te_ID, g.tg_ID, g.tg_only_group
						from ci_test_panels te
						JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
						where  (g.tg_ID = {$group->tg_ID} or g.tg_parent = {$group->tg_ID} )
						group by te.te_ID
						");
					echo $wpdb->last_error;


					foreach ($testarr as $t) {
						$tests_requested[$group->tg_type][] = $t->te_ID;
					}
				} else {
					//check tests
					$listbcyodes = array($bcode);
					$subbcodes = explode(',', $bcode);
					$listbcyodes = array_merge($listbcyodes, $subbcodes);
					foreach ($listbcyodes as $numbc => $subbcode) {
						$subbcode = trim($subbcode);
						if (empty($subbcode)) continue;

						$testob = $wpdb->get_row("select te.te_ID, te.te_type, g.tg_ID, g.tg_only_group
							from ci_test_panels te
							JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
							where  (te.te_name = '{$subbcode}' or te.te_abrev = '{$subbcode}' or te.te_abrev2 = '{$subbcode}' or te.te_loinc_code = '{$subbcode}')
							and te.te_in_req = 1 
							and ( 
								(g.tg_parent = 0 and g.tg_show_req = 1 )
								or (g.tg_parent > 0 and g.tg_parent in (select g2.tg_ID from ci_test_groups g2 where g2.tg_show_req = 1) ) 
								)
							group by te.te_ID
							order by g.tg_reflex_group_ID asc, te.te_ID asc
							limit 1
							");
						echo $wpdb->last_error;

						if (!empty($testob)) {
							if (!in_array($testob->te_type, $bbtypes)) {
								$bbtypes[] = $testob->te_type;
								if (!isset($tests_requested[$testob->te_type])) {
									$tests_requested[$testob->te_type] = array();
									$bcodebytypes[$testob->te_type] = array();
								}
							}
							$bcodebytypes[$testob->te_type][] = $bcode;
							$bnamebytypes[$testob->te_type][$bcode] = $other_info['OBR_names'][$bcode];


							if (!in_array('GROUP_' . $testob->tg_ID, $tests_requested[$testob->te_type])) {
								$tests_requested[$testob->te_type][] = 'GROUP_' . $testob->tg_ID;
							}

							if (!in_array($testob->te_ID, $tests_requested[$testob->te_type])) {
								$tests_requested[$testob->te_type][] = $testob->te_ID;
							}
							if ($numbc == 0) {
								break; //found full code
							}
						} else {
							echo "\n no test for {$subbcode}\n";
						}
					}
				}
			} else {
				unset($other_info['OBR_code'][$i]);
			}
		}


		if (empty($bbtypes)) {
			//$bbtypes[] = $btype;
			return false;
		} else if (!in_array('pcr', $bbtypes) && isset($_GET['act']) && $_GET['act'] == 'reimport') {
			$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
						where mo_test_ID='{$order_id}' and mo_btype = 'pcr'
					  	and mo_file_id = '{$dbfile->m_ID}' ");
			if ($orderdb) {
				$mid = $orderdb && $orderdb->mo_mid > 0 ? $orderdb->mo_mid : 0;
				$wpdb->delete('ci_sync_api_orders', array('mo_ID' => $orderdb->mo_ID));
				$wpdb->delete('ci_batches_pcr', array('mid' => $mid));
			}
		}

		foreach ($bbtypes as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);

			$other_info['OBR_code'] = $bcodebytypes[$btype];
			$other_info['OBR_names'] = $bnamebytypes[$btype];

			$tests_requested_str = implode(',', isset($tests_requested[$btype]) ? $tests_requested[$btype] : array());


			$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$order_id}' and mo_btype = '{$btype}'
							      and mo_file_id = '{$dbfile->m_ID}' ");
			$mid = $orderdb && $orderdb->mo_mid > 0 ? $orderdb->mo_mid : 0;
			$bat = null;
			if ($mid > 0) {
				$bat = $wpdb->get_row("select * from {$batch_tbl} where mid = {$mid} ");
			} else {
				$bat = $wpdb->get_row("select * from {$batch_tbl} where oldreq = '{$old_reg}' ");
			}

			if (!$bat) {
				$assen = tr_get_new_assen();

				if (empty($assen)) {
					continue;
				}
			} else {
				$assen = $bat->assen;
			}
			$this->log .= "\n" . $btype . ' order assen:' . $assen . "\n";

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
					'pa_middle'   => $pa_middle,
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


			$type_of_specimen_str = implode(',', $type_of_specimens);

			$dagnostic_codes = array();
			if (isset($arr['DG1'])) {
				foreach ($arr['DG1'] as $dg1) {
					if (!empty($dg1['DG1.3']['DG1.3.1'])) {
						$dagnostic_codes[] = $dg1['DG1.3']['DG1.3.1'];
					}
				}
			}

			$dagnostic_codes = implode(',', $dagnostic_codes);

			if (empty($dagnostic_codes)) {
				$dagnostic_codes = 'Z20.828';
			}

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
				'panel_tests_requested' => $tests_requested_str,
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
				'mo_btype'        => $btype,
				'mo_control_ID' => $control_id . '',
				'mo_test_ID'    => $order_id . '',
				'mo_set_ID'     => $hl7_set_ID . '',
				'mo_patient_ID' => $hl7_patient_id . '',
				'mo_peg_ID' 	=> $peg_id . '',
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

	public function send_results($btype = 'pcr', $mid = 0, $limit = 0)
	{
		global $wpdb;

		echo "send_results for btype: {$btype}, mid: {$mid}, limit: {$limit} \n";

		$sub_folder = '';

		$results_folder = $this->options['results_folder'] ?? '';
		if (!isset($this->options['results_folder']) || empty($this->options['results_folder'])) {
			$results_folder = $this->options['out_folder'];
		}

		if ($this->folder_by_btype && isset($this->options['type_folder_' . $btype])) {
			$sub_folder = $this->options['type_folder_' . $btype];
		}



		echo $btype . "\n\n<br>";

		if ($limit <= 0) $limit = 50;

		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$result_tbl = tr_pdd_get_table_result_by_btype($btype);

		$is_rebuild = isset($_GET['act']) && $_GET['act'] == 'rebuild' ? true : false;
		if (!file_exists($this->results_log_dir)) {
			mkdir($this->results_log_dir);
		}

		$pos_neg = array(
			'' => '',
			'NEG' => 'Negative',
			'POS' => 'Positive',
			'POL' => 'Positive',
			'POH' => 'Positive',
			'POM' => 'Positive',
			'PEN' => 'Pending',
			'RER' => 'ReRun',
			'INC' => 'Inconclusive',
			'PRP' => 'Positive',
			'INV' => 'Invalid',
			'NGE' => 'Negative',
			'REJ' => 'Rejected'
		);


		$limit_date = date('Y-m-d', strtotime('-6 days'));
		if (!$this->options['enable']) {
			$limit_date = date('Y-m-d', strtotime('-60 days'));
			$limit = 1;
		}

		if (!empty($this->options['start_date'])) {
			$limit_date = $this->options['start_date'];
		}




		$clids = array();
		if (!empty($this->clinic_send_result_field)) {
			$clids = $wpdb->get_col("select cID from ci_clinic_meta where meta_key='{$this->clinic_send_result_field}' and meta_value='1' ");
		}

		$select_fields 	= '';
		$joins 			= '';
		$where 			= '';


		if (!empty($this->clinic_fieldname)) {
			$select_fields .= ", cli.{$this->clinic_fieldname} as clinic_code ";
		}


		if (!$this->send_all_results) {
			$select_fields .=  ' ,mf.m_ID, mf.m_file ';
			$joins .= "  left JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid and mo.mo_btype= '{$btype}' 
			and (mo.mo_file_id >0 or mo.mo_file_id = {$this->api_id}) ";
			$joins .= " left JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='{$this->log_file_slug}' ";
		} else {
			$joins .= "  left JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid and mo.mo_btype= '{$btype}' and mo.mo_file_id ={$this->api_id} ";
		}

		if (!$is_rebuild) {
			$where .= " and re.estatus = 'C'";
			if ($this->send_result_not_final) {
				$where .= " and (re.final_results is null or re.final_results != 1) ";
			}
		}

		if ($mid > 0) {
			$where .= " and ba.mid = {$mid} ";
			if (!$this->send_all_results) {
				$where .= " and (mf.m_ID = mo.mo_file_id or mo.mo_file_id ={$this->api_id} )";
			}
		} else {

			if (!empty($clids)) {
				$clids_str = implode(',', $clids);

				if (!$this->send_all_results) {
					$where .= " and (
						(ba.cli_ID in ({$clids_str}) 
							and (mo.mo_file_id is null or mo.mo_file_id ={$this->api_id})  
							and (mo.{$this->sent_field} in (0,2) or mo.{$this->sent_field} is null ) )
						or (mf.m_ID = mo.mo_file_id and mo.mo_file_id>0 and mo.{$this->sent_field} in (0,2) )
					) ";
					$where .= " and (mf.m_ID = mo.mo_file_id or mo.mo_file_id ={$this->api_id} or mo.mo_ID is null ) ";
				} else {
					$where .= " and (
						(ba.cli_ID in ({$clids_str}) 
							and (mo.mo_file_id is null or mo.mo_file_id ={$this->api_id})  
							and (mo.{$this->sent_field} in (0,2) or mo.{$this->sent_field} is null ) )
						or (mo.{$this->sent_field} in (0,2) )
					) ";
				}
			} else {
				$where .= " and (mo.{$this->sent_field} is null or mo.{$this->sent_field} in (0,2) )"; //2 need resend
				if (!$this->send_all_results) {
					$where .= " and (mf.m_ID = mo.mo_file_id or mo.mo_file_id ={$this->api_id} )";
				}
			}
			$where .= " and (re.approve_date >= '{$limit_date}' or mo.{$this->sent_field} = 2) ";
		}

		if (!empty($this->send_when_meta)) {
			$joins .= " JOIN {$batch_tbl}_meta mw ON mw.mid = ba.mid and mw.meta_key='{$this->send_when_meta}' ";
		}



		$sql = "select re.id,
				ba.mid,
				ba.batch_id,
				ba.assen,
				ba.patient_name,
				ba.doc,
				ba.spec_received,
				ba.panel_tests_requested,
				ba.batch_status,
				ba.notes_report,
				
				pa.pa_ID,
				pa.pa_first,
				pa.pa_last,
				pa.pa_middle,
				pa.pa_address,
				pa.pa_city,
				pa.pa_state,
				pa.pa_zip,
				pa.pa_dob,
				pa.pa_ssn,
				pa.pa_mrn,
				pa.pa_gender,
				pa.pa_phone,
				pa.pa_email,
				
				re.approve_date,
				re.created_date,
				re.instrument,
				re.final_results,
				
				mo.mo_ID,
				mo.mo_set_ID,
				mo.mo_status,
				mo.mo_sent,
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

				reflab.lab_dba_init as reflab_dba_init,
				reflab.lab_dba as reflab_dba,
				reflab.lab_name as reflab_name,
				reflab.lab_clia	as reflab_clia,
				reflab.lab_address as reflab_address,
				reflab.lab_city as reflab_city,
				reflab.lab_state as reflab_state,
				lab.lab_zip as reflab_zip,
				
				cli.cli_clinic,
				cli.cli_street,
				cli.cli_city,
				cli.cli_state,
				cli.cli_zip,
				cli.`cli_om-phone` as cli_phone,
				
				doc.doc_first,
				doc.doc_last,
				doc.doc_npi,
				ins.*
				{$select_fields}
				
			FROM {$batch_tbl} ba
			JOIN {$report_tbl} re ON re.patient_id = ba.assen
			JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
			{$joins}
			LEFT JOIN ci_labs reflab ON reflab.lab_ID = ba.ref_lab_id
			jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
			jOIN ci_clinic cli ON cli.cID = ba.cli_ID
			left JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
            left JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
			where 1 {$where}
				group by ba.mid
				limit {$limit} 
			";


		$rs = $wpdb->get_results($sql);


		if (count($rs) == 0) {
			if (!empty($wpdb->last_error)) {
				$this->log .= $btype . " error: " . $wpdb->last_error;
			}
			$this->log .= $btype . ": Not found results! \n";
		}


		if (isset($_GET['debug'])) {
			echo '<br>';
			echo nl2br($wpdb->last_query);
			echo '<br>';
		}

		echo 'count:' . count($rs) . '<br>';

		foreach ($rs as $r) {

			try {

				$other_info = json_decode($r->other_info, true);

				$lab_location = $r->lab_name;
				$lab_location .= '^' . $r->lab_address;
				$lab_location .= '^' . '';
				$lab_location .= '^' . $r->lab_city;
				$lab_location .= '^' . $r->lab_state;
				$lab_location .= '^' . $r->lab_zip . '^';

				$ref_lab_location  = $r->reflab_name;
				$ref_lab_location .= '^' . $r->reflab_address;
				$ref_lab_location .= '^' . '';
				$ref_lab_location .= '^' . $r->reflab_city;
				$ref_lab_location .= '^' . $r->reflab_state;
				$ref_lab_location .= '^' . $r->reflab_zip . '^';

				$filename = basename($r->m_file);
				//$filename = str_replace(array('{','}'),'',$filename);
				$filename = str_replace('.json', '.hl7', $filename);
				if (empty($filename)) {
					$filename = $r->assen . '.hl7';
				}
				$hl7_file = $this->results_log_dir . $filename;


				$assen 		= $r->assen;
				$batch_status = $r->batch_status;

				$r->result_status = 'F';
				if ($r->result_status == 'F' && $r->final_results == '2') {
					$r->result_status = 'P';
				} else if ($r->final_results == '3') {
					$r->result_status = 'C';
				}

				if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
					$r->result_status = 'X';
				}

				if ($r->mo_peg_ID == 'LTC4, 5, 6') {
					//$r->mo_peg_ID = 'LTC6';
					//$r->result_status = 'F';
				}


				if (empty($r->lab_dba)) {
					$r->lab_dba = $r->lab_name;
				}

				if (empty($r->spec_received)) {
					$r->spec_received = $r->doc;
				}
				$hl7_arr 	= array();
				$pdfs 		= array();


				if (!isset($other_info['MSH4']) || empty($other_info['MSH4'])) {
					$other_info['MSH4'] = $r->clinic_code;
				}

				if (!isset($other_info['MSH5']) || empty($other_info['MSH5'])) {
					$other_info['MSH5'] = $this->lab_msh5;
				}

				if (!isset($other_info['MSH6']) || empty($other_info['MSH6'])) {
					$other_info['MSH6'] = $this->lab_msh6;
				}


				$hl7_arr['MSH'] = $this->getMSH($r->mo_control_ID, $other_info['MSH6'], $other_info['MSH5'], 'ORU', 'R01', 'ORU_R01', 15, $other_info['MSH3'], $other_info['MSH4']);

				if (!empty($r->pa_middle)) {
					$r->pa_first .= '^' . $r->pa_middle;
				}
				if (empty($r->mo_set_ID)) {
					$r->mo_set_ID = $r->pa_ID;
				}
				if (empty($r->mo_patient_ID)) {
					$r->mo_patient_ID = $r->pa_mrn;
				}
				if (empty($r->mo_test_ID)) {
					$r->mo_test_ID = $assen;
				}
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
					$r->pa_ID
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

				if (!empty($r->dagnostic_codes1)) {
					$hl7_arr['DG1'] = $this->getDG1($r->dagnostic_codes1, tr_pdd_get_dagnostic_codes($btype));
				}


				$hl7_arr['ORC'] = $this->getORC(
					$r->mo_test_ID,
					$assen,
					$r->doc,
					$r->lab_name,
					$r->doc_npi,
					$r->doc_first,
					$r->doc_last
				);

				$modbs = array();
				if (empty($r->m_ID)) {

					if (empty($r->mo_ID)) {
						$wpdb->insert('ci_sync_api_orders', array(
							'mo_file_id'    => $this->api_id,
							'mo_mid'        => $r->mid,
							'mo_btype'      => $btype,
							'mo_set_ID'	    => $r->mo_set_ID,
							'mo_patient_ID'	    => $r->mo_patient_ID,
							'mo_test_ID'	    => $r->mo_test_ID,
						));
						$r->mo_ID = $wpdb->insert_id;
						if (empty($r->mo_ID)) {
							echo $wpdb->last_error;
							continue;
						}
					}
					$modbs = $wpdb->get_results("select * from ci_sync_api_orders where mo_ID = {$r->mo_ID}");
				} else {
					$modbs = $wpdb->get_results("select * from ci_sync_api_orders where mo_file_id = {$r->m_ID}");
				}



				$mo_orders 		= array();
				$test_groups = array();
				$pass_all_obr = true;
				$has_all_pos = false;
				$has_results = false;
				foreach ($modbs as $modb) {
					$subbtype 		= $modb->mo_btype;
					$mid		= $modb->mo_mid;
					$batch_tbl 	= tr_pdd_get_table_by_btype($subbtype);
					$report_tbl = tr_pdd_get_table_report_by_btype($subbtype);
					$result_tbl = tr_pdd_get_table_result_by_btype($subbtype);

					$report 	= tr_pdd_get_report_by_id(0, $subbtype, $mid);
					if (is_null($report)) {
						continue;
					}

					if (empty($report['assen'])) {
						continue;
					}
					if ($report['estatus'] != 'C' && !$is_rebuild) {
						$pass_all_obr = false;
						echo $report['assen'] . ': not complete by not approved<br>';
						continue;
					}

					var_dump($report['assen']);
					echo '<br>';

					$mo_orders[$modb->mo_ID] = $modb;
					$anti_rs = array();


					$other_info = json_decode($modb->other_info, true);

					$has_pos = false;
					$testids = array();
					$bcodes = isset($other_info['OBR_code']) && count($other_info['OBR_code']) > 0 ? $other_info['OBR_code'] : array();
					$bnames = isset($other_info['OBR_names']) && count($other_info['OBR_names']) > 0 ? $other_info['OBR_names'] : array();

					if ($this->send_all_panels || $this->send_all_results || $modb->mo_file_id < 0) {
						foreach (explode(',', $report['panel_tests_requested']) as $tid) {
							if (stripos($tid, 'GROUP_') !== false) {
								$gid = str_replace('GROUP_', '', $tid);

								$group = $wpdb->get_row("select * from ci_test_groups 
								where tg_ID = {$gid} and tg_type = '{$subbtype}' ");
								if ($group && $group->tg_parent == 0) {
									if (!empty($group->tg_cpt_code) && in_array($group->tg_cpt_code, $bcodes)) {
										continue;
									} else if (!empty($group->tg_short_name) && in_array($group->tg_short_name, $bcodes)) {
										continue;
									} else if (!empty($group->tg_name) && in_array($group->tg_name, $bcodes)) {
										continue;
									}
									$bcodes[] = $group->tg_name;


									if ($this->folder_by_panel && isset($this->options['panel_folder_' . $gid])) {
										$sub_folder = $this->options['panel_folder_' . $gid];
									}
								}
							} else {
								$testids[] = $tid;
							}
						}
					}

					$testids_str = implode(',', $testids);

					if (count($bcodes) > 0) {

						$added_test_ids 		= array();
						$added_adding_test_ids 	= array();
						$adding_tests 			= array();
						$count_bcode 			= count($bcodes);
						$reflex_pass   			= array();

						$drug_groups = tr_pdd_get_drug_groups($btype, array(), true);
						foreach ($drug_groups as $groupid => $groupdata) {

							if (!empty($groupdata['reflex_ID'])) {
								foreach ($groupdata['reflex_ID'] as $rlid) {
									if ($rlid > 0) {
										$reflex_pass[$rlid] = false;
									}
								}
							}
						}


						for ($ibc = 0; $ibc < $count_bcode; $ibc++) {
							$bcode = $bcodes[$ibc];
							echo 'bcode:' . $bcode . '<br>';
							if (stripos($bcode, 'group_id_') !== false) {
								$gid 	= str_replace('group_id_', '', $bcode);
								$bcode 	= $wpdb->get_var("select tg_name from ci_test_groups where tg_ID = {$gid}");
								echo 'new code:';
								var_dump($bcode);
								echo '<br>';
							}

							if (empty($bcode)) {
								continue;
							}

							$group = $wpdb->get_row("select * from ci_test_groups 
							where ( tg_cpt_code='{$bcode}' or tg_short_name = '{$bcode}' or tg_name = '{$bcode}')
							and tg_type = '{$subbtype}' ");

							$only_tests_ids = array();
							if (!$group) {

								$listbcyodes = array($bcode);
								$subbcodes = explode(',', $bcode);
								$listbcyodes = array_merge($listbcyodes, $subbcodes);
								foreach ($listbcyodes as $numbc => $subbcode) {
									$subbcode = trim($subbcode);
									if (empty($subbcode)) continue;

									$testob = $wpdb->get_row("select te.te_ID, 
									te.te_type, 
									te.te_name,
									te.te_type_id,
									g.tg_ID, 
									g.tg_name,
									g.tg_cpt_code,
									g.tg_parent,
									st.ts_name
									from ci_test_panels te
									JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
        							left join ci_test_specimens st on te.te_type_id = st.ts_ID and st.ts_type='{$subbtype}'
									where  (te.te_name = '{$subbcode}' or te.te_abrev = '{$subbcode}' 
											or te.te_abrev2 = '{$subbcode}' or te.te_loinc_code = '{$subbcode}' )
									and te.te_in_req = 1 
									and te.te_ID in ({$testids_str})
									and te.te_type = '{$subbtype}'
									and ( 
										(g.tg_parent = 0 and g.tg_show_req = 1 )
										or (g.tg_parent > 0 and g.tg_parent in (select g2.tg_ID from ci_test_groups g2 where g2.tg_show_req = 1) ) 
										)
									group by te.te_ID
									order by g.tg_reflex_group_ID asc, te.te_ID asc
									limit 1
									");

									if ($testob) {
										$only_tests_ids[] = $testob->te_ID;
										$gid = $testob->tg_parent > 0 ? $testob->tg_parent : $testob->tg_ID;
										$group = $wpdb->get_row("select * from ci_test_groups 
											where tg_ID = {$gid} and tg_type = '{$subbtype}' ");

										//get adding hide tests
										if (tr_get_option('ci_pdf_data', 'api_all_tests_same_code') && !empty($testob->ts_name)) {
											$ttype_ids = $wpdb->get_col("select ts_ID from ci_test_specimens where ts_name like '%{$testob->ts_name}%' and ts_type='{$subbtype}' ");
											$ttype_ids_str = implode(',', $ttype_ids);
											$addingtests = $wpdb->get_results("select te.te_ID, 
											te.te_name,
											te.te_type, 
											te.te_type_id, 
											g.tg_ID, 
											g.tg_name,
											g.tg_cpt_code,
											g.tg_parent
											from ci_test_panels te
											JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
											where te.te_in_req = 1 
											and te.te_ID != {$testob->te_ID}
											and te.te_name = '{$testob->te_name}'
											and te.te_type_id in ({$ttype_ids_str})
											and te.te_loinc_code = '{$subbcode}'
											and te.te_type = '{$subbtype}'
											and g.tg_only_group = 1 
											and g.tg_show_req = 1 ");
											echo '<br>-adding:';
											echo $wpdb->last_error;

											foreach ($addingtests as $at) {
												$at->main_ID = $testob->te_ID;
												$at->bcode = $bcode;
												if (in_array($at->te_ID, $added_adding_test_ids)) {
													continue;
												}
												$added_adding_test_ids[] = $at->te_ID;
												$adding_tests[] = $at;
											}
										}

										if ($numbc == 0) {
											break; //found full code
										}
									}
								}
							}

							if ($is_rebuild) {
								echo '<br><br>';
								var_dump($only_tests_ids);
								echo '<br><br>';
								//	var_dump($gid);
								///var_dump($group);
								//echo '<br><br>';
							}


							if ($group) {

								$group->other_options = json_decode($group->tg_other_options, true);
								$group_show_conc = false;
								if ($subbtype == 'blood') {
									$group_show_conc = tr_get_option('ci_pdf_data', 'blood_pdf_show_conc');
								}
								if (isset($group->other_options['show_value_in_result']) && $group->other_options['show_value_in_result']) {
									$group_show_conc = true;
								}
								if (isset($group->hide_ct) && $group->hide_ct) {
									$group_show_conc = false;
								}


								$bname = isset($bnames[$bcode]) ? $bnames[$bcode] : $group->tg_name;

								$groups_ids = $wpdb->get_col("select tg_ID from ci_test_groups
								where tg_ID = {$group->tg_ID} or tg_parent = {$group->tg_ID}");

								$groups_ids_str = implode(',', $groups_ids);


								if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {

									if (!isset($test_groups[$bcode])) {
										$test_groups[$bcode] = array(
											'name' => $bname,
											'tests' => array()
										);

										$test_groups[$bcode]['tests'][] = array(
											'test_id' => $bcode,
											'test_name' => $bname,
											'pos-neg' => 'REJ'
										);
									}
								} else {
									if (!isset($test_groups[$bcode])) {
										$test_groups[$bcode] = array(
											'name' => $bname,
											'assen' => $report['assen'],
											'tests' => array()
										);
									}

									$leftjoin = '';
									$wherers = '';
									if ($subbtype == 'blood') {
										$leftjoin = 'LEFT';
										$wherers = " and (rs.id > 0 or (rs.id is null and te.te_calculation!='' and te.te_calculation is not null) ) ";
									}

									if (!empty($only_tests_ids)) {
										$only_tests_ids_str = implode(',', $only_tests_ids);
										$wherers .= " and te.te_ID in ({$only_tests_ids_str}) ";
									}


									$sql1 = "select 
									te.*,
									g.tg_ID, 
									g.tg_only_group,
									g.tg_is_covid,
									g.tg_name,
									rs.*
									from ci_test_panels te
									JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
									{$leftjoin} JOIN {$result_tbl} rs ON (rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2) 
												and rs.report_id = {$report['id']} and (rs.group_id = 0 or rs.group_id is null or rs.group_id  = te.te_group_id)
									where te_type = '{$subbtype}' 
									and te.te_in_req = 1 
									and g.tg_ID in ({$groups_ids_str})
									{$wherers}
									group by te.te_ID ";

									$tests_rs = $wpdb->get_results($sql1, ARRAY_A);
									echo $bname . '-count testrs:' . count($tests_rs);
									echo '<br><br>';


									if (count($tests_rs) > 0) {

										global $all_res;
										$all_res = array();
										foreach ($tests_rs as $arr) {
											$arr['drug'] = strtoupper($arr['te_name']);
											$all_res[$arr['te_ID']] = $arr;
										}

										foreach ($tests_rs as $ti => $t) {

											$t['test_name'] = $t['te_name'];

											if ($t['tg_is_covid'] && $subbtype == 'pcr') {
												$arr = trpdd_cal_ncovid19(array('te_name' => $t['te_name']), $report['id'], $report['instrument'], $report['created_date']);
												$t['pos-neg'] = $arr['pos-neg'];
												$t['conc'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];
											} else {
												$t['drug'] = $t['te_name'];
												if ($subbtype == 'blood') {
													$arr = tr_pdd_parse_blood_test($t,  $report['instrument'], $t['tg_ID'], $r);

													if ($group_show_conc) {
														$arr['flag'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];
													}
												} else {
													$t['type_of_specimen'] = $report['type_of_specimen'];
													$arr = tr_pdd_parse_pcr_test($t,  $report['instrument'], $t['tg_ID'], $report['created_date']);
												}
												$t['pos-neg'] = $arr['pos-neg'];
												$t['conc'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];
												$t['flag'] = $arr['flag'];
												$t['range'] = $arr['range'];
												$t['units'] = $arr['te_units'];


												if (in_array($t['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVM', 'PVH'))) {
													$listreflexids = explode(',', $group->tg_reflex_group_ID);
													foreach ($listreflexids as $reflexid) {
														if ($reflexid > 0) {
															if (isset($reflex_pass[$reflexid])) {
																$reflex_pass[$reflexid] = true;
															}
															$reflxid =  'group_id_' . $reflexid;
															if (!in_array($reflxid, $bcodes)) {
																$bcodes[] = $reflxid;
																$count_bcode++;
															}
														}
													}
												}
											}

											if (empty($t['pos-neg']) || $t['te_is_control'] == 1) {
												continue;
											}



											$test_groups[$bcode]['tests'][$t['te_ID']] = array(
												'test_id' => $t['te_loinc_code'],
												'tg_ID' => $t['tg_ID'],
												'test_name' => $t['test_name'],
												'pos-neg' => $t['pos-neg'],
												'flag'  => $t['flag'],
												'range'  => $t['range'],
												'units'  => $t['units'],
												'conc'  => $t['conc'],
											);

											$added_test_ids[] = $t['te_ID'];

											if (in_array($t['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVM', 'PVH'))) {
												$has_pos = true;
												$has_all_pos = true;
											}
											$has_results = true;
										}
									} else {
										//		var_dump($sql1);
									}
								}



								if ($subbtype == 'blood' && $this->result_mic) {
									//check mic
									$anti_rs = $wpdb->get_results("select ra.*,
									ta.te_name as ta_name,
									te.te_name
									from ci_results_antibiotics ra
									join ci_test_panels te ON te.te_ID = ra.te_ID
									join ci_test_panels ta ON ta.te_ID = ra.ta_ID and ta.te_type='cuant'
									where ra.report_id = {$report['id']} 
										and ra.btype = 'blood'
									order by ta_name asc , te_name asc");

									$labresults = $wpdb->get_results("select ra.*,te.te_name
                                            from ci_results_labresults ra
                                            join ci_test_panels te ON te.te_ID = ra.te_ID
                                            where report_id = {$report['id']}
                                                order by te_name asc ");


									if (count($anti_rs) > 0) {
										$anti_data = array();
										foreach ($labresults as $rlr) {
											$anti_data[$rlr->te_name] = $rlr->result;
										}
										$addednte = array();

										$test_groups['630-4'] = array(
											'name' => 'Urine Culture',
											'assen' => $report['assen'],
											'tests' => array()
										);



										foreach ($anti_rs as $rati) {

											$rs_vl = $anti_data[$rati->te_name];
											$nteanti = '';
											if (!empty($rs_vl) && !isset($addednte[$rati->te_name])) {
												$nteanti  = $rs_vl . ' ' . $rati->te_name;
												$addednte[$rati->te_name] = true;

												$test_groups['630-4']['tests'][] = array(
													'test_id' => '630-4',
													'test_name' => 'Urine Culture',
													'pos-neg' =>  $rati->result,
													'flag'  => $rati->te_name,
													'obx8'  => $rati->result,
													'obx3' => '^^LN^630-4^Urine Culture^L',
													'nte' => $nteanti,
													'conc'  => $rati->conc,
												);
											}
											$test_groups['630-4']['tests'][] = array(
												'test_id' => $rati->te_name,
												'test_name' => $rati->ta_name,
												'pos-neg' =>  $rati->result,
												'flag'  => $rati->te_name,
												'obx8'  => $rati->result,
												'conc'  => $rati->conc,
											);
										}
									}
								}
							}
						}

						if (count($adding_tests) > 0) {
							//add hide tests with same code
							foreach ($adding_tests as $at) {
								if (in_array($at->te_ID, $added_test_ids)) {
									//continue;
								}
								$mtest = $at->main_ID;
								$testarr = array();
								foreach ($test_groups as $tgcode => $tgarr) {
									if (isset($tgarr['tests'][$mtest])) {
										$testarr = $tgarr['tests'][$mtest];
										break;
									}
								}
								echo 'adding test:';
								var_dump($mtest);
								var_dump($testarr);
								echo '<br>';

								$bcode = $at->tg_name;
								if (!empty($at->tg_cpt_code)) {
									$bcode = $at->tg_cpt_code;
								}
								if (!isset($test_groups[$bcode])) {
									$test_groups[$bcode] = array(
										'name' => $at->tg_name,
										'tests' => array()
									);
								}


								$test_groups[$bcode]['tests'][] = $testarr;

								if (in_array($testarr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVM', 'PVH'))) {
									$has_pos = true;
									$has_all_pos = true;
								}
								$has_results = true;
							}
						}
					}

					unset($_GET['debug']);
					unset($_REQUEST['debug']);
					unset($_GET['tr_debug']);
					unset($_REQUEST['tr_debug']);

					if ($this->dont_send_pdf_no_pos && !$has_pos) {
						echo 'no pos skip pdf<br>';
					} else {
						$pdf_file  = trpdd_export_results_pdf($report['id'], '', false, $subbtype);
						if ($pdf_file) {
							$pdfs[] = $pdf_file;
						}
					}
				}

				if (isset($this->options['onlysendpos']) && $this->options['onlysendpos']) {

					if (in_array('Pharmacogenomics', $bcodes) && count($pdfs) > 0 && $btype == 'gene') {
						//still send result
					} else if (!$has_all_pos) {
						echo 'no pos skip<br>';

						foreach ($mo_orders as $mo_ID => $mo_order) {


							$wpdb->update('ci_sync_api_orders', array(
								$this->sent_field    => -1,
								'other_info'    => json_encode($other_info)
							), array(
								'mo_ID'         => $mo_ID
							));
						}
						continue;
					}
				}


				if (!$pass_all_obr || empty($modbs)) {
					echo '<br>not complete by blank<br>';
					continue;
				}


				//var_dump($test_groups);

				foreach ($test_groups as $groupcode => $garr) {

					if (empty($garr['tests'])) {
						continue;
					}

					$gassen = isset($garr['assen']) ? $garr['assen'] : $assen;
					$hl7_arr[] = $this->getOBR(
						$r->mo_test_ID,
						$gassen,
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
						'X',
						$lab_location
					);

					foreach ($garr['tests'] as $tarr) {

						//check reflex
						if (isset($reflex_pass[$tarr['tg_ID']]) && !$reflex_pass[$tarr['tg_ID']]) {
							continue;
						}

						if (!isset($tarr['flag']) || empty($tarr['flag'])) {
							$tarr['flag'] = $pos_neg[$tarr['pos-neg']];
						}
						$obx8 = in_array($tarr['pos-neg'], array('POS')) ? 'A' : 'N';
						if ($tarr['pos-neg'] == 'PCL') {
							$obx8 = 'LL';
						} else if ($tarr['pos-neg'] == 'PCH') {
							$obx8 = 'HH';
						} else if ($tarr['pos-neg'] == 'POH') {
							$obx8 = 'H';
						} else if ($tarr['pos-neg'] == 'POL') {
							$obx8 = 'L';
						}

						if (isset($tarr['obx8']) && !empty($tarr['obx8'])) {
							$obx8 = $tarr['obx8'];
						}

						$optobx = array(
							'obx6' => $tarr['units'],
							'obx7' => $tarr['range'],
							'obx8' => $obx8,
						);

						if ($this->send_result_ct) {
							$optobx['obx5'] = $tarr['conc'];
						}
						if (isset($tarr['obx3']) && !empty($tarr['obx3'])) {
							$optobx['obx3'] = $tarr['obx3'];
						}

						$hl7_arr[] = $this->getOBX(
							$r->lab_clia,
							$r->lab_name,
							$tarr['test_id'],
							$tarr['test_name'],
							$tarr['flag'],
							$r->result_status,
							$r->approve_date,
							$r->lab_address,
							$r->lab_city,
							$r->lab_state,
							$r->lab_zip,
							'',
							$optobx
						);


						if (isset($tarr['nte']) && !empty($tarr['nte'])) {
							$hl7_arr[] = $this->getNTE($tarr['nte']);
						}
					}
				}



				if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
					$nte_msg = 'Rejected';
					$batch_statuses = tr_pdd_get_batch_statuses(false, true, true, $btype);
					if (isset($batch_statuses[$batch_status])) {
						$nte_msg = $batch_statuses[$batch_status];
					}
					$hl7_arr['NTE'] = $this->getNTE($nte_msg);
				} else if (!empty($r->notes_report)) {
					if (!empty($r->reflab_name)) {
						$r->notes_report .= '^' . $ref_lab_location;
					}
					$hl7_arr['NTE'] = $this->getNTE($r->notes_report);
				} else {
					if (!empty($r->reflab_name)) {

						$hl7_arr['NTE'] = $this->getNTE('^' . $ref_lab_location);
					}
				}




				foreach ($pdfs as $ipdf => $pdf_file) {
					if ($this->pdf_format['OBX.3.1'] == 'assen') {
						$this->pdf_format['OBX.3.1'] = $r->assen;
					}
					$pdfkey = $ipdf > 0 ? 'pdf' . ($ipdf + 1) : 'pdf';
					$pdf_desc = $ipdf > 0 ? 'PDF' . ($ipdf + 1) : 'PDF';
					$hl7_arr[] = $this->getOBX(
						$r->lab_clia,
						$r->lab_name,
						$pdfkey,
						$pdf_desc,
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

				if (!empty($this->options['send_req_pdf'])) {
					$current_get = $_GET;
					$current_request = $_REQUEST;
					$req_pdf_file = trpdd_get_req_pdf($r->mid, $btype);
					$_GET = $current_get;
					$_REQUEST = $current_request;
					if ($req_pdf_file && file_exists($req_pdf_file)) {
						$pdf_format = $this->pdf_format;
						$this->pdf_format['OBX.3.1'] = $r->assen;
						$this->pdf_format['OBX.3.2'] = 'RequisitionForm';
						$this->pdf_format['OBX.5.1'] = '';
						$this->pdf_format['OBX.5.2'] = 'application/pdf';
						$this->pdf_format['OBX.5.3'] = 'content';
						unset($this->pdf_format['OBX.5.4']);

						$req_obx = $this->getOBX(
							$r->lab_clia,
							$r->lab_name,
							$r->assen,
							'RequisitionForm',
							'',
							'IF',
							'',
							'',
							'',
							'',
							'',
							$req_pdf_file
						);
						$req_obx['OBX.5'] = array(
							'OBX.5.1' => '',
							'OBX.5.2' => 'application/pdf',
							'OBX.5.3' => 'Base64',
							'OBX.5.4' => base64_encode(file_get_contents($req_pdf_file)),
						);
						$hl7_arr[] = $req_obx;
						$this->pdf_format = $pdf_format;
					}
				}



				$hl7_data = $this->array_to_hl7($hl7_arr);


				if (!isset($_GET['act']) || $_GET['act'] != 'reimport') {
					file_put_contents($hl7_file, $hl7_data);
				}



				if ($is_rebuild) {
					var_dump($hl7_file);
					continue;
				}



				if (!empty($sub_folder)) {
					$results_folder .= '/' . $sub_folder;
				}

				$local_file = $hl7_file;
				$remote_file = $results_folder . '/' . basename($hl7_file);
				$remote_file = str_replace('//', '/', $remote_file);

				if (isset($_GET['trajax'])) {
					var_dump($remote_file);
					echo '<textarea style="width:100%;height:800px">';
					var_dump($hl7_data);
					echo '</textarea>';
				}

				if (isset($_GET['debug2'])) {
					exit;
				}

				try {
					$this->uploadFile($local_file, $remote_file);
					if ($this->debug) {
						$this->log .= "uploaded: {$remote_file} \n";
					}

					$datetime = date_i18n('Y-m-d H:i:s');

					foreach ($mo_orders as $mo_ID => $mo_order) {
						$other_info = !empty($mo_order->other_info) ? json_decode($mo_order->other_info, true) : array();
						if (!isset($other_info['sent'])) {
							$other_info['sent'] = array();
						}
						$other_info['sent'][] = $datetime;

						$wpdb->update('ci_sync_api_orders', array(
							$this->sent_field    => 1,
							'mo_sent_dt'    => $datetime,
							'other_info'    => json_encode($other_info)
						), array(
							'mo_ID'         => $mo_ID
						));
						echo $wpdb->last_error;
						echo '<br>';
					}
				} catch (Exception $e) {
					var_dump($e);
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		}


		return true;
	}

	public function receive_results()
	{
		$results_folder = isset($this->options['results_folder']) ? $this->options['results_folder'] : $this->options['result_folder'];
		if (!isset($this->options['results_folder']) || empty($this->options['results_folder'])) {
			$results_folder = $this->options['in_folder'];
		}

		$results_folder = rtrim($results_folder, '/');
		$count_files = 0;
		if ($this->folder_by_btype) {
			foreach ($this->all_btypes as $btype) {
				$folder = $this->options['type_folder_' . $btype];
				$count_files += $this->receive_results_by_folder($results_folder . '/' . $folder, $btype);
			}
		} else {
			$count_files = $this->receive_results_by_folder($results_folder);
		}



		return $count_files;
	}

	public function receive_results_by_folder($folder, $btype = '')
	{
		$this->log .= "Read folder: {$folder} ";
		$notfoundfolder = $folder . '/notfound';
		$files = $this->scan_dir($folder);
		$count_files = 0;

		if (count($files) == 0) {
			$this->log .= "No files!";
		}


		$count = 0;
		foreach ($files as $file) {
			$file_path = $folder . '/' . $file;
			if (stripos($file, '.') > 0) {
				$done = $this->import_result_file($file_path, $btype);
				$count++;
				//move file to archive
				if ($done) {
					$this->delete($file_path);
				} else {
					$this->movefile($file_path, $notfoundfolder . '/' . $file);
				}

				$count_files++;
			}
		}

		if ($this->debug && $count_files = 0) {
			$this->log .= " No file need sync\n";
		}
		$this->log .= '<br>';
		return $count_files;
	}

	function import_result_file($file_path, $base_btype = '')
	{
		global $wpdb;
		$mid = 0;
		if ($this->debug) {
			$this->log .= "file: " . $file_path . "\n";
		}

		$filename	= basename($file_path);
		$path 		= $this->getpath($file_path);
		$stream 	= @fopen($path, 'r');
		$contents 	= '';
		$modata 	= null;
		$other_info = array();

		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents .= $buffer;
		}
		if (strlen($contents) < 10) {

			if ($this->debug) {
				$this->log .= '=>file is empty' . "\n";
			}
			return false;
		}

		if (stripos($file_path, 'wp-content') === false) {
			if (!file_exists($this->results_in_folder)) {
				mkdir($this->results_in_folder);
			}
			file_put_contents($this->results_in_folder . basename($file_path), $contents);
		}


		$packages = explode('MSH|', $contents);


		foreach ($packages as $package) {
			$package = trim($package);
			if (empty($package)) {

				continue;
			}
			$package 	= 'MSH|' . $package;

			$arr = $this->hl7_to_array($package, array('OBR', 'OBX'));

			$order_id 	= $arr['ORC']['ORC.3']['ORC.3.1'];
			$assen 		= $arr['ORC']['ORC.2']['ORC.2.1'];
			if (empty($assen)) {
				foreach ($arr['OBR'] as $obr) {
					$assen = $obr['OBR.2']['OBR.2.1'];
					if (!empty($assen)) {
						break;
					}
				}
			}

			$btype 			= $base_btype;
			$batch_tbl 		= '';
			$bat 			= null;

			if (stripos($assen, 'MAK') == 0 && strlen($assen) < 10) {
				$assen = 'MAK012' . str_replace('MAK', '', $assen);
			} else if (strlen($assen) == 4) {
				$assen = 'MAK012' . $assen;
			}

			if (!empty($assen)) {
				foreach ($this->all_btypes as $bt) {
					$batch_tbl = tr_pdd_get_table_by_btype($bt);

					$sqlassen = "select * from {$batch_tbl} where assen = '{$assen}' ";
					if (substr($assen, 0, 3) == 'HUC') {
						$assen2 = str_replace('HUC', 'MAK', $assen);
						$sqlassen .= " or assen = '{$assen2}' ";
					}
					$bat = $wpdb->get_row($sqlassen);



					if ($bat) {
						if (empty($base_btype)) {
							$btype = $bt;
						}

						$assen = $bat->assen;
						break;
					}
				}
			}

			//get btype


			$orderrawid = isset($arr['ORC']['ORC.2']['ORC.2.1']) ? $arr['ORC']['ORC.2']['ORC.2.1'] : '';

			if (!$bat && $orderrawid > 0) {
				if (empty($base_btype)) {
					$btype = 'pcr';
				}

				$orderid = substr($orderrawid, 0, strlen($orderrawid) - 2);
				$typenum = str_replace($orderid, '', $orderrawid);
				if ($typenum == '01') {
					$btype = 'tox';
				} else if ($typenum == '02') {
					$btype = 'blood';
				} else if ($typenum == '03') {
					$btype = 'pcr';
				} else {
					$orderid = $orderrawid;
				}

				$batch_tbl = tr_pdd_get_table_by_btype($bt);

				$bat = $wpdb->get_row("select * from {$batch_tbl} where mid = '{$orderid}' ");
				if ($bat) {
					$assen = $bat->assen;
				}
			}



			if (!$bat) {
				if ($this->debug) {
					$this->log .= " not found\n";
				}
				return false;
			}

			$this->log .= "mid: {$bat->mid} \n";
			$this->log .= "assen: {$bat->assen} \n";

			$mid 		= $bat->mid;

			$report_tbl = tr_pdd_get_table_report_by_btype($btype);
			$result_tbl = tr_pdd_get_table_result_by_btype($btype);

			$report = $wpdb->get_row("select * from {$report_tbl} where patient_id='{$assen}' ");
			if (!$report) {
				$wpdb->insert($report_tbl, array(
					'patient_id' => $assen,
					'author_id' => 0,
					'created' => date_i18n('Y-m-d H:i:s'),
					'created_date' => date_i18n('Y-m-d'),
					'instrument' => $this->api_id,
				));
				$report = $wpdb->get_row("select * from {$report_tbl} where patient_id='{$assen}' ");
			} else if ($this->enable_revese_result) {
				$wpdb->update($report_tbl, array(
					'estatus' => 'M',
				), array(
					'id' => $report->id
				));
				$report->estatus = 'M';
			}

			if (!$report) {
				continue;
			}

			$modata = $wpdb->get_row("select * 
			from ci_sync_api_orders 
			where mo_file_id= '{$this->api_id}' 
			and mo_mid = {$mid} 
			and mo_btype = '{$btype}' ");

			$other_info = $modata && !empty($modata->other_info) ? json_decode($modata->other_info, true) : array();
			if (!isset($other_info['received'])) {
				$other_info['received']  = array();
			}

			$test_field = 'te_abrev';
			if (!empty($this->test_code_result)) {
				$test_field = $this->test_code_result;
			}

			foreach ($arr['OBX'] as $obx) {
				$testcode = $obx['OBX.3']['OBX.3.1'];
				$testname = isset($obx['OBX.3']['OBX.3.2']) ? $obx['OBX.3']['OBX.3.2'] : '';
				if (empty($testname)) {
					$testname = 'notfound';
				}
				if (stripos($testname, 'RequisitionForm') !== false) {
					continue;
				}


				if (stripos($testcode, 'pdf') !== false || stripos($testname, 'pdf') !== false || stripos($testname, 'Sample Results Document') !== false) {

					$pdfcode = isset($obx['OBX.5']['OBX.5.5']) ? $obx['OBX.5']['OBX.5.5'] : $obx['OBX.5']['OBX.5.3'];
					$pdfcode = base64_decode($pdfcode);
					$this->log .= "pdf size: " . strlen($pdfcode) . "\n";
					$dir = tr_get_result_upload_dir();
					if (!file_exists($dir['path'])) {
						wp_mkdir_p($dir['path']);
					}
					$filenum = 0;
					foreach ($other_info['received'] as $ftime => $fname) {
						if (stripos($fname, '.') === false) {
							unset($other_info['received'][$ftime]);
							continue;
						}
						if ($fname != $filename) {
							$filenum++;
						} else {
							break;
						}
					}
					if (isset($this->options['override_pdf']) && $this->options['override_pdf']) {
						$filenum = 0;
					}
					$filenamepdf 	= strtolower($assen) . '_' . $this->log_file_slug . ($filenum > 0 ? '_' . $filenum : '') . '.pdf';
					$pdf_path 		= $dir['path'] . '/' . $filenamepdf;

					if (strlen($pdfcode) > 1000) {
						$rsputfile = file_put_contents($pdf_path, $pdfcode);
						$bat->result_pdf = strtolower($bat->result_pdf);
						$result_pdfs = !empty($bat->result_pdf) ? explode(',', $bat->result_pdf) : array();
						if (!in_array($filenamepdf, $result_pdfs)) {
							$result_pdfs[] = $filenamepdf;
						}
						$this->log .= $filenamepdf . ($rsputfile ? ' (success)' : ' (failed)') . "\n";



						$wpdb->update($batch_tbl, array('result_pdf' => implode(',', $result_pdfs)), array('mid' => $bat->mid));
					}

					continue;
				}
				$drug = '';
				if (!empty($testcode)) {
					$drug = $wpdb->get_var("select te_name from ci_test_panels 
					where te_type = '{$btype}'  and te_in_req = 1
					and {$test_field}='{$testcode}' ");
				}

				if (empty($drug)) {
					$drug = $wpdb->get_var("select te_name from ci_test_panels 
					where te_type = '{$btype}'  and te_in_req = 1
					and te_loinc_code='{$testcode}' ");
				}


				if (empty($drug)) {

					if ($test_field != 'te_abrev' && !empty($testname)) {
						$drug = $wpdb->get_var("select te_name from ci_test_panels 
						where te_type = '{$btype}' and te_in_req = 1
									and (te_abrev='{$testcode}'
									 or te_abrev='{$testname}' 
									 or te_name='{$testname}') ");
					}
					var_dump($testcode);
					var_dump($testname);
					var_dump($drug);
					echo '<br>';


					if (empty($drug) && !empty($testname)) {
						$testnamearr = array($testname);
						if (stripos($testname, ' ') > 0) {
							$arr2 = explode(' ', $testname, 2);
							$testnamearr[] = strlen($arr2[0]) > 5 ? trim($arr2[0]) : '';
							$testnamearr[] = trim($arr2[1]);
						}
						$testname_str = implode("','", $testnamearr);
						$drug = $wpdb->get_var("select te_name 
						from ci_test_panels 
							where te_type = '{$btype}' and te_in_req = 1 
							and (te_name in( '{$testname_str}') or te_abrev in( '{$testname_str}') )");
					}
					if (empty($drug)) {
						$drug = $testcode;
					}
				}

				$conc 		= $obx['OBX.9']['OBX.9.1'];
				$posneg 	= $obx['OBX.5']['OBX.5.1'];
				$posnegkey 	= $obx['OBX.8']['OBX.8.1'];

				if ($posneg == 'Negative' || stripos($posneg, 'Not Detected') !== false) {
					$posnegkey = 'NEG';
				} else if (stripos($posneg, 'Invalid') !== false) {
					$posnegkey = 'INV';
				} else if (stripos($posnegkey, 'Medium') !== false) {
					$posnegkey = 'POM';
				} else if (stripos($posnegkey, 'Low') !== false || stripos($posneg, ' low') > 0) {
					$posnegkey = 'POL';
				} else if (stripos($posnegkey, 'High') !== false || stripos($posneg, ' high') > 0) {
					$posnegkey = 'POH';
				} else if ($posnegkey == 'N') {
					$posnegkey = 'NEG';
				} else if ($posnegkey == 'A') {
					$posnegkey = 'POS';
				} else if ($posneg == 'Positive' || stripos($posneg, 'Detected') !== false) {
					$posnegkey = 'POS';
				}

				if (empty($posnegkey) && empty($posneg) && empty($conc)) {
					continue;
				}

				$this->log .= "{$testcode}: {$testname}=> {$drug} : {$conc} : {$posneg} : {$posnegkey}\n";
				$update = array(
					'conc' => $conc,
				);
				if (empty($conc)) {
					$update = array(
						'pos-neg' => $posnegkey
					);
				}

				$exist = $wpdb->get_row("select * from {$result_tbl} 
				where report_id = {$report->id}
				and drug = '{$drug}' ");

				if ($drug != $testcode && !$exist) {
					$exist = $wpdb->get_row("select * from {$result_tbl} 
					where report_id = {$report->id}
					and drug = '{$testcode}' ");
				}


				if ($exist) {
					$update['drug'] = $drug;
					$wpdb->update($result_tbl, $update, array('id' => $exist->id));
				} else {
					$update['report_id'] = $report->id;
					$update['drug'] = $drug;
					$wpdb->insert($result_tbl, $update);
				}
			}
		}

		$update = array(
			'mo_file_id'    => $this->api_id,
			'mo_mid'        => $mid,
			'mo_btype' 		=> $btype,
			'mo_status'		=> 1,
			'mo_peg_ID' 	=> $filename,
			'mo_test_ID' 	=> $order_id,
		);

		$other_info['received'][date_i18n('Y-m-d H:i:s')] = $filename;

		$update['other_info'] 	= json_encode($other_info);

		if ($modata->mo_ID > 0) {

			$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $modata->mo_ID));
		} else {

			$r = $wpdb->insert('ci_sync_api_orders', $update);
		}

		if ($r === false) {
			$this->log .= $wpdb->last_error;
			return false;
		}

		return $mid > 0;
	}

	public function settingFields()
	{
		return array(
			'host' => array('type' => 'text', 'label' => 'Host'),
			'port' => array('type' => 'text', 'label' => 'Port'),
			'username' => array('type' => 'text', 'label' => 'Username'),
			'password' => array('type' => 'text', 'label' => 'Password'),
			'orders_folder' => array('type' => 'text', 'label' => 'Order Folder'),
			'results_folder' => array('type' => 'text', 'label' => 'Result Folder'),
			'start_date' => array('type' => 'date', 'label' => 'Start Date'),
			'cron' => array('type' => 'select', 'options' => array(30 => 'each 30mins', 10 => 'each 10mins', 5 => 'each 5mins', 2 => 'each 2mins'), 'label' => 'Schedule'),
		);
	}

	public function getClinicOptions()
	{
		global $wpdb;
		$clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic order by cli_clinic asc");
		$options = array('' => 'Select');
		foreach ($clinics as $clinic) {
			$options[$clinic->cID] = $clinic->cli_clinic;
		}
		return $options;
	}

	public function showSettingsFields($options)
	{
		foreach ($this->settingFields() as $fk => $arr) {
?>
			<div class="row">
				<label><?php echo $arr['label'] ?></label>
				<?php
				if ($arr['type'] == 'select') {
					echo '<select name="options[' . $fk . ']">';
					foreach ($arr['options'] as $mk => $mvl) {
						$selected = isset($options[$fk]) && $mk == $options[$fk] ? 'selected' : '';
						echo '<option value="' . $mk . '" ' . $selected . '>' . $mvl . '</option>';
					}
					echo '</select>';
				} else if ($arr['type'] == 'checkbox') { ?>
					<input type="checkbox" name="options[<?php echo $fk ?>]" value="1" <?php checked(isset($options[$fk]) ? $options[$fk] : 0) ?> />
				<?php } else { ?>
					<input type="<?php echo $arr['type'] ?>" name="options[<?php echo $fk ?>]" value="<?php echo $options[$fk] ?? '' ?>" />
				<?php } ?>
			</div>
<?php
		}
	}

	public function settingActions()
	{
		return array();
	}

	public function setting_count()
	{
		global $wpdb;
		echo '<table style="min-width:300px">';
		echo '<tr><th>Type</th><th>Reqs</th><th>Results</th><th>Sent</th><th></th></tr>';
		foreach ($this->all_btypes as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$joins = '';
			if (!empty($this->send_when_meta)) {
				$joins .= " JOIN {$batch_tbl}_meta mw ON mw.mid = ba.mid and mw.meta_key='{$this->send_when_meta}' ";
			}


			$sql_count = " select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_sent=1,1,0)) as count_sent
                            from {$batch_tbl} ba
							{$joins}
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}' and o.mo_file_id = {$this->api_id}
                            where 1 ";
			if (!empty($this->options['start_date'])) {
				$sql_count .= " and ba.doc >= '{$this->options['start_date']}' ";
			}

			if (isset($_GET['debug'])) {
				echo $sql_count;
			}
			$count = $wpdb->get_row($sql_count);

			if ($count->count_sent > $count->count_results) {
				$count->count_results = $count->count_sent;
			}
			echo '<tr><th>' . $btype . '</th><td class="center">' . $count->count_reqs . '</td>';
			echo '<td class="center">' . $count->count_results . '</td>';
			echo '<td class="center">' . $count->count_sent . '</td>';
			echo '<td><a class="thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'btype' => $btype, 'debug' => isset($_GET['debug']) ? 1 : '', 'width' => 1000), false) . '">Log</a>';
			echo '</tr>';
		}
		echo '</table>';
	}
}
