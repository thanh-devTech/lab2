<?php

class CI_Api_Telcor_Billing_sftp
{
	protected $conn;
	protected $sftp;
	private  $host = '';
	private  $port = 22;
	public $folder = '/';
	public $clientbill_folder = '';
	public $req_folder = '';
	public $result_folder = '';
	public $phlebolog_folder = '';
	public $dir = WP_CONTENT_DIR . '/log/billing_sftp/';
	public $log_file = WP_CONTENT_DIR . '/log/log_api_billing_sftp.txt';
	protected $options;
	public $debug = false;
	public $log = '';
	public $api_id = -18;
	public $log_file_slug = 'billing_sftp';
	public $current_date = '';
	public $mids = array();

	public function __construct($options)
	{

		$this->options = $options;
		$this->host = isset($options['host']) ? $options['host'] : '';
		if (isset($options['port']) && $options['port'] > 0) {
			$this->port = $options['port'];
		}

		$this->folder           = isset($options['folder']) ? $options['folder'] : '';
		$this->clientbill_folder       = isset($options['clientbill_folder']) ? $options['clientbill_folder'] : '';
		$this->req_folder       = isset($options['req_folder']) ? $options['req_folder'] : '';
		$this->result_folder    = isset($options['result_folder']) ? $options['result_folder'] : '';
		$this->phlebolog_folder = isset($options['phlebolog_folder']) ? $options['phlebolog_folder'] : '';

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
		$remote_file = str_replace('//', '/', $remote_file);
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

	public function log($message)
	{
		$f_log = fopen($this->log_file, 'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => ' . $message . "\n");
		fclose($f_log);

		$this->log .= $message . "\n";
	}

	public function sync($args = array())
	{
		global $wpdb;

		$this->current_date = date_i18n('Y-m-d');
		$hour = date_i18n('H');
		if ($hour <= 17) {
			$this->current_date = date('Y-m-d', strtotime($this->current_date) - 86400);
		}
		if (isset($_REQUEST['date'])) {
			$this->current_date = $_REQUEST['date'];
		}

		$btypes = array('pcr');
		if (isset($_GET['only_blood'])) {
			$btypes = array();
		}
		if ($this->options['enable_blood'] && !isset($_GET['only_pcr'])) {
			$btypes[] = 'blood';
		}

		var_dump($btypes);
		echo '<br>';

		$files = array();
		foreach ($btypes as $btype) {
			$this->mids = array();

			$local_file = $this->build_csv($this->current_date, $btype, $args);

			if ($local_file) {
				$files[] = $local_file;
				if (isset($_GET['notupload'])) {
					continue;
				}

				$filename = basename($local_file);

				$remote_file = $this->folder . '/' . $filename;

				try {
					if (isset($_REQUEST['testcron'])) {
						echo 'uploading file: ' . $remote_file . '<br>';
					}

					$this->uploadFile($local_file, $remote_file);
					$this->log("uploaded: {$remote_file} \n");

					//insert log db
					foreach ($this->mids as $mid) {
						$logarr = array(
							'mo_file_id' => $this->api_id,
							'mo_mid' => $mid,
							'mo_btype' => $btype,
							'mo_test_ID' => date_i18n('Y-m-d H:i:s'),
							'mo_control_ID' => $filename,
						);
						$mo_ID = $wpdb->get_var("select mo_ID from ci_sync_api_orders 
									where mo_btype = '{$btype}' 
									and mo_file_id = {$this->api_id}
									and mo_mid = {$mid}");
						if ($mo_ID > 0) {
							$logarr = array(
								'mo_control_ID' => $filename,
							);
							$wpdb->update('ci_sync_api_orders', $logarr, array('mo_ID' => $mo_ID));
						} else {
							$wpdb->insert('ci_sync_api_orders', $logarr);
						}
					}
				} catch (Exception $e) {
					$this->log("sync error: " . $e->getMessage());
					return false;
				}
			}
		}

		if ($this->options['PhleboLog'] && !empty($this->phlebolog_folder)) {
			$local_file = $this->build_PhleboLog($this->current_date);
			if ($local_file) {
				$filename = basename($local_file);
				$remote_file = $this->phlebolog_folder . '/' . $filename;

				try {
					if (isset($_REQUEST['testcron'])) {
						echo 'uploading PhleboLog file: ' . $remote_file . '<br>';
					}

					$this->uploadFile($local_file, $remote_file);
					$this->log("uploaded PhleboLog: {$remote_file} \n");
				} catch (Exception $e) {
					$this->log("sync error: " . $e->getMessage());
					return false;
				}
			}
		}


		return $files;
	}

	public function build_csv($date, $btype, $args = array())
	{
		global $wpdb;

		$filename   = $date . ($btype != 'pcr' ? '_' . $btype : '') . '.csv';
		$local_file = $this->dir . '/' . $filename;

		$file       = fopen($local_file, 'w');

		if (isset($_REQUEST['testcron'])) {
			echo 'file: ' . $filename . '<br>';
		}

		$headers = array();
		for ($i = 1; $i <= 103; $i++) {
			$headers[] = 'Segment ' . $i;
		}
		fputcsv($file, $headers);
		$headers = array('Patient ID', 'Patient Name: Last Name', 'Patient Name: First Name', 'Patient Name: Middle Initial', 'Patient Name: Suffix', 'Date of Birth', 'Gender', 'Patient Address: Address1', 'Patient Address: Address2', 'Patient Address: City', 'Patient Address: State', 'Patient Address: Zip', 'Patient Address: Country', 'Patient Phone Number: Home', 'Patient Phone Number: Work', 'Attention Level Code 1', 'Patient Class', 'Financial Class', 'Guarantor Name: Last Name', 'Guarantor Name: First Name', 'Guarantor Name: Middle Initial', 'Guarantor Name: Suffix', 'Guarantor Address: Address1', 'Guarantor Address: Address2', 'Guarantor Address: City', 'Guarantor Address: State', 'Guarantor Address: Zip', 'Guarantor Address: Country', 'Guarantor Phone Number: Home', 'Guarantor Phone Number: Work', 'Guarantor Date of Birth', 'Guarantor Gender', 'Guarantor Relationship Code', 'Primary Insurance Company Code', 'Primary Insurance Company Name', 'Primary Insured Name:  Last Name', 'Primary Insured Name:  First Name', 'Primary Insured Name:  Middle Initial', 'Primary Insured Name: Suffix', 'Primary Insured Relationship to Patient Code', 'Primary Insured Date of Birth', 'Primary Insured Address: Address1', 'Primary Insured Address: Address2', 'Primary Insured Address: City', 'Primary Insured Address: State', 'Primary Insured Address: Zip', 'Primary Insured Address: Country', 'Primary Insured Policy Number', 'Primary Insured Gender', 'Primary Insured Phone Number: Home', 'Primary Insured Phone Number: Work', 'Client Code', 'Client Name', 'Service Date / Time', 'Transaction Type', 'Procedure Code', 'Procedure Description', 'Transaction Quantity', 'Company Code', 'Diagnosis Code', 'Rendering Provider: Code', 'Rendering Provider: Last Name', 'Rendering Provider: First Name', 'Performing Lab Code', 'Ordering Provider: Code', 'Ordering Provider: Last Name', 'Ordering Provider: First Name', 'Accession  Number', 'Admit Date', 'Primary Group Number', 'Secondary Insurance Company Code', 'Secondary Insurance Company Name', 'Secondary Insured Name:  Last Name', 'Secondary Insured Name:  First Name', 'Secondary Insured Name:  Middle Initial', 'Secondary Insured Name: Suffix', 'Secondary Insured Relationship to Patient Code', 'Secondary Insured Date of Birth', 'Secondary Insured Address: Address1', 'Secondary Insured Address: Address2', 'Secondary Insured Address: City', 'Secondary Insured Address: State', 'Secondary Insured Address: Zip', 'Secondary Insured Address: Country', 'Secondary Insured Policy Number', 'Secondary Insured Gender', 'Secondary Group Number', 'Prior Authorization', 'Patient ID 2', 'Patient ID 3', 'Attention Level Code 2', 'Attention Level Code 3', 'Txn Field1', 'Txn Field2', 'Txn Field3', 'Txn Field4', 'Txn Field5', 'Txn Field6', 'Txn Field7', 'Txn Field8', 'Modifier', 'Discharge Date/Time', 'Phlebotomist: Code');
		fputcsv($file, $headers);

		$headers = array('PID 3.01.00 Patient ID', 'PID - 05.01.00 Patient Name: Last Name', 'PID - 05.02.00 Patient Name: First Name', 'PID - 05.03.00 Patient Name: Middle Initial', 'PID 5.04.00 Patient Name: Suffix', 'PID - 07.00.00 Date of Birth', 'PID - 08.01.00 Gender', 'PID - 11.01.00 Patient Address: Address1', 'PID - 11.02.00 Patient Address: Address2', 'PID - 11.03.00 Patient Address: City', 'PID - 11.04.00 Patient Address: State', 'PID - 11.05.00 Patient Address: Zip', 'PID - 11.06.00 Patient Address: Country', 'PID 13.01.00 - Patient Phone Number: Home', 'PID 14.01.00 - Patient Phone Number: Work', 'PID 21.01.00 - Attention Level Code 1', 'PV1 2.01.00 - Patient Class', 'PV1 - 20.01.00 Financial Class', 'GT1 - 03.01.00 Guarantor Name: Last Name', 'GT1 - 03.02.00 Guarantor Name: First Name', 'GT1 - 03.03.00 Guarantor Name: Middle Initial', 'GT1 - 03.04.00 Guarantor Name: Suffix', 'GT1 - 05.01.00 Guarantor Address: Address1', 'GT1 - 05.02.00 Guarantor Address: Address2', 'GT1 - 05.03.00 Guarantor Address: City', 'GT1 - 05.04.00 Guarantor Address: State', 'GT1 - 05.05.00 Guarantor Address: Zip', 'GT1 - 05.06.00 Guarantor Address: Country', 'GT1 06.01.00 - Guarantor Phone Number: Home', 'GT1 07.01.00 - Guarantor Phone Number: Work', 'GT1 - 08.00.00 Guarantor Date of Birth', 'GT1 - 09.01.00 Guarantor Gender', 'GT1 - 11.01.00 Guarantor Relationship Code', 'IN1 - 03.01.00 Insurance Company Code', 'IN1 - 04.01.00 Insurance Company Name', 'IN1 - 16.01.00 Insured Name: Last Name', 'IN1 - 16.02.00 Insured Name: First Name', 'IN1 - 16.03.00 Insured Name: Middle Initial', 'IN1 - 16.04.00 Insured Name: Suffix', 'IN1 - 17.01.00 Insured Relationship to Patient: Code', 'IN1 - 18.00.00 Insured Date of Birth', 'IN1 - 19.01.00 Insured Address: Address1', 'IN1 - 19.02.00 Insured Address: Address2', 'IN1 - 19.03.00 Insured Address: City', 'IN1 - 19.04.00 Insured Address: State', 'IN1 - 19.05.00 Insured Address: Zip', 'IN1 - 19.06.00 Insured Address: Country', 'IN1 - 36.00.00 Insured Policy Number', 'IN1 - 43.01.00 Insured Gender', 'IN1 - 63.01.00 Insured Phone Number: Home', 'IN1 - 64.01.00 Insured Phone Number: Work', 'PV1 - 03.01.00 Client Code', 'PV1 - 03.02.00 Client Name', 'FT1 - 04.01.00 Service Date/Time', 'FT1 - 06.01.00 Transaction Type', 'FT1 - 07.01.00 Procedure Code', 'FT1 - 07.02.00 Procedure Description', 'FT1 - 10.00.00 Transaction Quantity', 'FT1 13.03 Company Code', 'FT1 - 19.00.00 Diagnosis Code', 'FT1 - 20.01.00 Rendering Provider: Code', 'FT1 - 20.02.00 Rendering Provider: Last Name', 'FT1 - 20.03.00 Rendering Provider: First Name', 'FT1 - 20.08.00 Performing Lab Code', 'FT1 - 21.01.00 Ordering Provider Code', 'FT1 - 21.01.00 Ordering Provider: Last Name', 'FT1 - 21.02.00 Ordering Provider: First Name', 'FT1 - 23.01.00 Accession Number', 'PV1 - 44.01.00 Admit Date/Time', 'IN1 - 08.01.00 Insured Group Number', 'IN1 - 03.01.00 Insurance Company Code', 'IN1 - 04.01.00 Insurance Company Name', 'IN1 - 16.01.00 Insured Name: Last Name', 'IN1 - 16.02.00 Insured Name: First Name', 'IN1 - 16.03.00 Insured Name: Middle Initial', 'IN1 - 16.04.00 Insured Name: Suffix', 'IN1 - 17.01.00 Insured Relationship to Patient: Code', 'IN1 - 18.00.00 Insured Date of Birth', 'IN1 - 19.01.00 Insured Address: Address1', 'IN1 - 19.02.00 Insured Address: Address2', 'IN1 - 19.03.00 Insured Address: City', 'IN1 - 19.04.00 Insured Address: State', 'IN1 - 19.05.00 Insured Address: Zip', 'IN1 - 19.06.00 Insured Address: Country', 'IN1 - 36.00.00 Insured Policy Number', 'IN1 - 43.01.00 Insured Gender', 'IN1 - 08.01.00 Insured Group Number', 'FT1 - 39.01.00 Prior Authorization', 'PID - 18.01.00 Patient ID 2', 'PV1 - 19.01.00 Patient ID 3', 'PID 21.02.00 - Attention Level Code 2', 'PID 21.03.00 - Attention Level Code 3', 'FT1 - 16.12.00 Txn Field1', 'FT1 - 16.13.00 Txn Field2', 'FT1 - 16.14.00 Txn Field3', 'FT1 - 16.15.00 Txn Field4', 'FT1 - 16.16.00 Txn Field5', 'FT1 - 16.17.00 Txn Field6', 'FT1 - 16.18.00 Txn Field7', 'FT1 - 16.19.00 Txn Field9', 'FT1 - 26.01.00 Modifier(s)', 'PV1 - 45.01.00 Discharge Date/Time', 'FT1 - 24.01.00 Phlebotomist: Code');
		fputcsv($file, $headers);


		$all_tests = array();
		$groups = array();
		$other_group_id = 0;

		$rs = $wpdb->get_results("select tg_ID, 
									tg_name, 
									tg_short_name,
									tg_only_group,
									tg_notestcheckbox
                            from ci_test_groups 
                            where tg_type='{$btype}'
                            ");
		foreach ($rs as $r) {
			$groups[$r->tg_ID] = !empty($r->tg_short_name) ? $r->tg_short_name : $r->tg_name;
			if ($r->tg_only_group || $r->tg_notestcheckbox) {
				$groups['only_' . $r->tg_ID] = $groups[$r->tg_ID];
			}
			if (strtolower($r->tg_name) == 'other') {
				//$other_group_id = $r->tg_ID;
			}
		}

		$sql_tests = "select te_ID,
							te_name,
							te_abrev, 
							te_abrev2, 
							te_group_id
                            from ci_test_panels 
                            where te_type='{$btype}' ";
		if ($btype == 'blood' && $other_group_id > 0) {
			$sql_tests .= " and te_group_id != {$other_group_id} ";
		}
		$rs = $wpdb->get_results($sql_tests);

		foreach ($rs as $r) {
			$all_tests[$r->te_ID] = $r;

			if (isset($groups['only_' . $r->te_group_id])) {
				$all_tests['only_' . $r->te_group_id] = $all_tests[$r->te_ID];
			}
		}





		$where = "";
		$where .= " and ((re.id > 0  and re.approve_date >= '{$date}' 
							and re.approve_date <= '{$date} 23:59:59') 
                or (re.id is null and ba.result_pdf!='' and ba.result_pdf is not null 
                            and  ba.batch_created >= '{$date}' 
                            and ba.batch_created <= '{$date} 23:59:59' ))";

		$where .= " and re.estatus in ('C','F') ";

		if (isset($_GET['only_missing'])) {
			$where .= " and ba.mid not in (select mo_mid from `ci_sync_api_orders` where mo_file_id = {$this->api_id} ) ";
		}

		if (isset($args['assens'])) {
			$assens_str = implode("','", $args['assens']);
			$where .= " and ba.assen in ('{$assens_str}') ";
		}


		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);


		$sql = "SELECT 
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
                    
                    cli.cID,
                    cli.cli_Telcor_ID,
                    re.id,
                    'C' as estatus,
                   
                    
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
                    
                    ins2.ins_first as ins2_first,
                    ins2.ins_last as ins2_last,
                    ins2.ins_company as ins2_company,
                    ins2.ins_address as ins2_address,
                    ins2.ins_city as ins2_city,
                    ins2.ins_state as ins2_state,
                    ins2.ins_zip as ins2_zip,
                    ins2.ins_phone as ins2_phone,
                    ins2.ins_dob as ins2_dob,
                    ins2.ins_member_id as ins2_member_id,
                    ins2.ins_group_id as ins2_group_id,
                    ins2.ins_relation as ins2_relation,
                    
                    payer.payer_fin_mnemonic,
                    col.col_phlebotomist_code,
                    
                    '{$btype}' as btype
            
                from {$batch_tbl} ba
                left join {$report_tbl} re ON  ba.assen = re.patient_id
                join ci_patients pa ON pa.pa_ID = ba.patient_id
                left join ci_labs lab ON lab.lab_ID = ba.lab_ID
                left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                left join ci_clinic cli ON cli.cID = ba.cli_ID
                left join ci_insurances ins ON ba.insurance1 = ins.ins_ID 
                left join ci_insurances ins2 ON ba.insurance2 = ins.ins_ID 
                left join ci_payer_list payer ON payer.payer_name = ins.ins_company 
                left join ci_collector col ON ba.col_ID = col.col_ID
                where 1=1 {$where} 
                group by ba.mid 
                order by ba.mid desc";


		$rows = $wpdb->get_results($sql);


		if (isset($_GET['only_missing']) && count($rows) == 0) {
			return false;
		}


		if (!empty($wpdb->last_error)) {
			$this->log($wpdb->last_error);
			echo $wpdb->last_error . "\n";
		} else {
			//var_dump($rows);
			$this->log('Count ' . $date . ' ' . $btype . ': ' . count($rows) . "\n");
		}
		$this->mids = array();
		foreach ($rows as $row) {
			$this->mids[] = $row->mid;

			$patient_id = '';
			if (!empty($row->pa_zip) && !empty($row->pa_address)) {
				$address = trim(str_replace(' ', '', strtoupper($row->pa_address)));
				$patient_id = substr($row->pa_zip, 0, 5) . '-' . substr($address, 0, 10);
			}
			$cpt_codes_arr = array();
			$panels_arr    = array();

			if (in_array($row->btype, array('pcr', 'blood'))) {
				$testids = explode(',', $row->panel_tests_requested);
				$groupids = array();
				foreach ($testids as $testid) {
					$testid = trim($testid);
					$testid = str_replace('+', '', $testid);
					if (stripos($testid, 'group_') !== false) {
						$group_id = str_ireplace('group_', '', $testid);
						if ($btype == 'blood') {
							if (isset($all_tests['only_' . $group_id])) {
								$testobj = $all_tests['only_' . $group_id];

								if (!empty($testobj->te_abrev)) {
									$cpt_codes_arr[$testobj->te_ID] = $testobj->te_abrev;
								}
								$panels_arr[$testobj->te_ID] = $testobj->te_name;
							}
						} else if (!in_array($group_id, $groupids) && isset($groups[$group_id])) {
							$panels_arr[$testid] = $groups[$group_id] . '*';
							$groupids[$testid] = $group_id;
						}
					} else if (isset($all_tests[$testid])) {
						$group_id = $all_tests[$testid]->te_group_id;
						if ($group_id > 0 && !in_array($group_id, $groupids) && isset($groups[$group_id]) && $btype != 'blood') {
							$panels_arr[] = $groups[$group_id] . '*';
							$groupids[] = $group_id;
						}
						$testobj = $all_tests[$testid];
						$testname = $testobj->te_name;
						if ($btype == 'blood' && !empty($testobj->te_abrev)) {
							$cpt_codes_arr[$testobj->te_ID] = $testobj->te_abrev;
						}
						$panels_arr[$testid] = $testname;
					}
				}
			} else {
				$panels_arr = explode(',', $row->panel_tests_requested);
			}

			$lab_code = $row->lab_state;
			if (empty($row->payer_fin_mnemonic)) {
				$row->payer_fin_mnemonic = $row->ins_fin_mnemonic;
				if (empty($row->payer_fin_mnemonic)) {
					$row->payer_fin_mnemonic = $row->ins_mnemonic;
				}
			}
			if ($row->lab_name == 'Qitek Labs of Oklahoma, LLC' && $btype == 'pcr') {
				$lab_code = 'MedUSA';
				$row->payer_fin_mnemonic = $row->ins_fin_mnemonic;
			}

			if ($btype == 'blood' && $row->lab_state == 'GA') {
				$row->lab_state = 'OK';
				$lab_code = 'LIFE';
			}

			if (strtoupper($row->pa_address) == 'N/A' || $row->pa_address == '0') {
				$row->pa_address = '';
			}
			if (strtoupper($row->pa_city) == 'N/A' || $row->pa_city == '0') {
				$row->pa_city = '';
			}
			if (strtoupper($row->pa_state) == 'N/A' || $row->pa_state == '0') {
				$row->pa_state = '';
			}
			if (strtoupper($row->pa_zip) == 'N/A' || $row->pa_zip == '0') {
				$row->pa_zip = '';
			}
			if ($row->pa_phone == '000-00-0000' || $row->pa_phone == '0000-0000' || $row->pa_phone == '0') {
				$row->pa_phone = '';
			}

			if (strtoupper($row->ins_address) == 'N/A' || $row->ins_address == '0') {
				$row->ins_address = '';
			}
			if ($row->ins_city == 'N/A' || $row->ins_city == '0') {
				$row->ins_city = '';
			}
			if ($row->ins_state == 'N/A' || $row->ins_state == '0') {
				$row->ins_state = '';
			}
			if ($row->ins_zip == 'N/A' || $row->ins_zip == '0') {
				$row->ins_zip = '';
			}
			if ($row->ins_phone == '000-00-0000' || $row->ins_phone == '0000-0000' || $row->ins_phone == '0') {
				$row->ins_phone = '';
			}

			if (strtoupper($row->ins_company) == 'N/A' || stripos($row->ins_company, 'No Insurance - Cash') !== false || stripos($row->ins_company, 'Client bill') !== false) {
				$row->ins_company = '';
			}


			if (strtoupper($row->ins_member_id) == 'N/A') {
				$row->ins_member_id = '';
			} else {
				$row->ins_member_id = str_replace(array('-', ' ', '_', '/'), '', $row->ins_member_id);
				$row->ins_member_id = trim($row->ins_member_id);
			}

			$panel = implode(', ', $panels_arr);

			if ($btype == 'pcr') {
				if (stripos($panel, 'UTI+ w/ABX') !== false) {
					$cpt_codes_arr[] = 'UTI+ w/ABX';
				} else if (stripos($panel, 'Wound w/ABX') !== false) {
					$cpt_codes_arr[] = 'Wound w/ABX';
				} else if (stripos($panel, 'SARS-CoV-2 PCR') !== false) {
					$cpt_codes_arr[] = 'SARS-CoV-2 PCR';
				}
			}

			$cpt_codes = implode(', ', $cpt_codes_arr);



			if ($row->ins_relation == 'Spouse' || $row->ins_relation == 'Self') {
				$row->guarantor_last = $row->pa_last;
				$row->guarantor_first = $row->pa_first;
				$row->guarantor_middle = $row->pa_middle;
				$row->guarantor_suffix = $row->pa_suffix;
				$row->guarantor_address = $row->pa_address;
				$row->guarantor_address2 = $row->pa_address2;
				$row->guarantor_city = $row->pa_city;
				$row->guarantor_state = $row->pa_state;
				$row->guarantor_zip = $row->pa_zip;
				$row->guarantor_phone = $row->pa_phone;
				$row->guarantor_work_phone = $row->pa_work_phone;
				$row->guarantor_dob = $row->pa_dob;
				$row->guarantor_gender = $row->pa_gender;

				$row->ins_last = $row->pa_last;
				$row->ins_first = $row->pa_first;
				$row->ins_middle = $row->pa_middle;
				$row->ins_suffix = $row->pa_suffix;
				$row->ins_address = $row->pa_address;
				$row->ins_address2 = $row->pa_address2;
				$row->ins_city = $row->pa_city;
				$row->ins_state = $row->pa_state;
				$row->ins_zip = $row->pa_zip;
				$row->ins_phone = $row->pa_phone;
				$row->ins_work_phone = $row->pa_work_phone;
				$row->ins_dob = $row->pa_dob;
				$row->ins_gender = $row->pa_gender;
			}

			$class = 'O';
			if ($btype == 'blood') {
				$class = $row->pa_phlebotomist_class;
			}

			if (empty($row->cli_Telcor_ID)) {
				$max_num = $wpdb->get_var("select max(cli_Telcor_ID) from ci_clinic");
				if ($max_num > 0) {
					$row->cli_Telcor_ID = $max_num + 1;
					$wpdb->update('ci_clinic', array('cli_Telcor_ID' => $row->cli_Telcor_ID), array('cID' => $row->cID));
				}
			}

			//fix date
			$row->pa_dob        = !empty($row->pa_dob) && $row->pa_dob != '0000-00-00' ? date('Ymd', strtotime($row->pa_dob)) : '';
			$row->guarantor_dob = !empty($row->guarantor_dob) && $row->guarantor_dob != '0000-00-00' ? date('Ymd', strtotime($row->guarantor_dob)) : '';
			$row->ins_dob       = !empty($row->ins_dob) && $row->ins_dob != '0000-00-00' ? date('Ymd', strtotime($row->ins_dob)) : '';
			$row->ins2_dob      = !empty($row->ins2_dob) && $row->ins2_dob != '0000-00-00' ? date('Ymd', strtotime($row->ins2_dob)) : '';
			$row->doc           = !empty($row->doc) && $row->doc != '0000-00-00' ? date('YmdHi', strtotime($row->doc)) : '';



			$rowdata = array();
			$rowdata[1] = $patient_id;
			$rowdata[2] = $row->pa_last;
			$rowdata[3] = $row->pa_first;
			$rowdata[4] = $row->pa_middle;
			$rowdata[5] = $row->pa_suffix; //5
			$rowdata[6] = $row->pa_dob;
			$rowdata[7] = $row->pa_gender;
			$rowdata[8] = $row->pa_address;
			$rowdata[9] = $row->pa_address2;
			$rowdata[10] = $row->pa_city; //10
			$rowdata[11] = $row->pa_state;
			$rowdata[12] = $row->pa_zip;
			$rowdata[13] = 'USA';
			$rowdata[14] = $row->pa_phone;
			$rowdata[15] = $row->pa_work_phone; //15
			$rowdata[16] = $row->pa_Attention;
			$rowdata[17] = $class; //Class
			$rowdata[18] = $row->insurance == 'No Insurance - Cash' || $row->insurance == "Client bill" ? 'Self' : 'Insurance';

			$rowdata[19] = $row->guarantor_last; //19
			$rowdata[20] = $row->guarantor_first; //20
			$rowdata[21] = $row->guarantor_middle;
			$rowdata[22] = $row->guarantor_suffix;
			$rowdata[23] = $row->guarantor_address;
			$rowdata[24] = $row->guarantor_address2;
			$rowdata[25] = $row->guarantor_city; //25
			$rowdata[26] = $row->guarantor_state;
			$rowdata[27] = $row->guarantor_zip;
			$rowdata[28] = 'USA';
			$rowdata[29] = $row->guarantor_phone;
			$rowdata[30] = $row->guarantor_work_phone; //30
			$rowdata[31] = $row->guarantor_dob;
			$rowdata[32] = $row->guarantor_gender;
			$rowdata[33] = 'SELF';

			$rowdata[34] = !empty($row->payer_fin_mnemonic) ? $row->payer_fin_mnemonic : $row->ins_company; //CODE //34
			$rowdata[35] = $row->ins_company;
			$rowdata[36] = $row->ins_last;
			$rowdata[37] = $row->ins_first;
			$rowdata[38] = $row->ins_middle;
			$rowdata[39] = $row->ins_suffix;
			$rowdata[40] = 'SELF'; //40
			$rowdata[41] = $row->ins_dob;
			$rowdata[42] = $row->ins_address;
			$rowdata[43] = $row->ins_address2;
			$rowdata[44] = $row->ins_city;
			$rowdata[45] = $row->ins_state; //45
			$rowdata[46] = $row->ins_zip;
			$rowdata[47] = 'USA'; //47
			$rowdata[48] = $row->ins_member_id;
			$rowdata[49] = $row->ins_gender;
			$rowdata[50] = $row->ins_phone; //50
			$rowdata[51] = $row->ins_work_phone;
			$rowdata[52] = $row->cli_Telcor_ID; //client code
			$rowdata[53] = $row->requesting_practice; //client name
			$rowdata[54] = $row->doc;
			$rowdata[55] = 'CH'; //55
			$rowdata[56] = $cpt_codes;
			$rowdata[57] = $panel;
			$rowdata[58] = '';
			$rowdata[59] = $row->lab_state; //Company Code
			$rowdata[60] = str_replace(',', '~', $row->dagnostic_codes); //60
			$rowdata[61] = '';
			$rowdata[62] = '';
			$rowdata[63] = '';
			$rowdata[64] = $lab_code; //Performing Lab Code
			$rowdata[65] = $row->doc_npi; //65
			$rowdata[66] = $row->doc_last;
			$rowdata[67] = $row->doc_first;
			$rowdata[68] = $row->assen;
			$rowdata[69] = '';
			$rowdata[70] = $row->ins_group_id; //70

			$rowdata[71] = $row->payer_fin_mnemonic2; //Secondary Insurance Company Code
			$rowdata[72] = $row->ins2_company;
			$rowdata[73] = $row->ins2_last;
			$rowdata[74] = $row->ins2_first;
			$rowdata[75] = $row->ins2_middle; //75
			$rowdata[76] = $row->ins2_suffix;
			$rowdata[77] = 'SELF';
			$rowdata[78] = $row->ins2_dob;
			$rowdata[79] = $row->ins2_address;
			$rowdata[80] = $row->ins2_address2; //80
			$rowdata[81] = $row->ins2_city;
			$rowdata[82] = $row->ins2_state;
			$rowdata[83] = $row->ins2_zip;
			$rowdata[84] = !empty($row->ins2_address) ? 'USA' : '';
			$rowdata[85] = ''; //85
			$rowdata[86] = '';
			$rowdata[87] = '';
			$rowdata[88] = '';
			$rowdata[89] = '';
			$rowdata[90] = ''; //90
			$rowdata[91] = '';
			$rowdata[92] = '';
			$rowdata[93] = '';
			$rowdata[94] = '';
			$rowdata[95] = ''; //95
			$rowdata[96] = '';
			$rowdata[97] = '';
			$rowdata[98] = '';
			$rowdata[99] = '';
			$rowdata[100] = ''; //100
			$rowdata[101] = '';
			$rowdata[102] = '';
			$rowdata[103] = $btype == 'blood' ? $row->col_phlebotomist_code : '';

			if ($btype == 'blood') {
				foreach ($panels_arr as $tid => $testname) {
					$rowdata[56] = isset($cpt_codes_arr[$tid]) ? $cpt_codes_arr[$tid] : ''; //56
					$rowdata[57] = $testname; //57
					fputcsv($file, $rowdata);
				}
			} else {
				fputcsv($file, $rowdata);
			}
		}
		fclose($file);

		return $local_file;
	}

	public function build_PhleboLog($date)
	{
		global $wpdb;

		$filename = $date . '_PhleboLog.csv';
		$local_file = $this->dir . '/' . $filename;


		$rows = $wpdb->get_results(
			"select col.col_phlebotomist_code,
							l.*
					from ci_phlebotomist_logs l 
					JOIN ci_collector col ON col.col_ID = l.collector_ID
					where l.service_date = '{$date}'
					",
			ARRAY_A
		);

		if (isset($_GET['only_missing'])) {
			return false;
		}

		$file = fopen($local_file, 'w');

		if (isset($_REQUEST['testcron'])) {
			echo 'file: ' . $filename . '<br>';
		}

		$headers = array(
			'log identifier',
			'company',
			'Phlebotomist',
			'Service Date',
			'Start Service Time',
			'End Service Time',
			'Start Mileage',
			'End Mileage',
			'Number of Stops',
			'Round Trip',
			'Total Patients/ Draws',
			'Category'
		);
		fputcsv($file, $headers);

		$settings_blood  = get_option('ci_req_settings_blood', array());
		$identifier = !empty($settings_blood['phle_indentifier']) ? $settings_blood['phle_indentifier'] : 'Qitek';
		$company = !empty($settings_blood['phle_company']) ? $settings_blood['phle_company'] : 'OK';

		foreach ($rows as $row) {
			$row['service_date']        = date('Ymd', strtotime($row['service_date']));
			$row['start_service_time']  = str_replace(':', '', $row['start_service_time']);
			$row['end_service_time']    = str_replace(':', '', $row['end_service_time']);

			$rowcsv = array(
				$identifier,
				$company,
				$row['col_phlebotomist_code'],
				$row['service_date'],
				$row['start_service_time'],
				$row['end_service_time'],
				$row['start_mileage'],
				$row['end_mileage'],
				$row['number_of_stops'],
				$row['round_trip'],
				$row['total_patients_draws'],
				$row['category'],
			);
			fputcsv($file, $rowcsv);


			$rowcsv = array(
				'Qitek',
				'OK',
				$row['col_phlebotomist_code'],
				$row['service_date'],
				$row['start_service_time'],
				$row['end_service_time'],
				$row['start_mileage_2'],
				$row['end_mileage_2'],
				$row['number_of_stops_2'],
				$row['round_trip_2'],
				$row['total_patients_draws_2'],
				$row['category_2'],
			);
			fputcsv($file, $rowcsv);
		}
		fclose($file);


		return $local_file;
	}

	public function sync_pdfs($limittime = 300, $limitrow = 100)
	{
		global $wpdb;

		if ($limittime <= 10 || $limittime > 500) {
			$limittime = 500;
		}
		if ($limitrow == 0) {
			$limitrow = 100;
		}
		$limit_datetime = date('Y-m-d H:i:s', strtotime(date_i18n('Y-m-d H:i:s')) - 86400 * 2);

		$rows = $wpdb->get_results("select * from ci_sync_api_orders where mo_file_id = {$this->api_id}
								and mo_status = 0
								and mo_test_ID > '{$limit_datetime}' 
								limit {$limitrow}");
		$count = 0;
		$timestart = time();

		foreach ($rows as $row) {
			$btype = $row->mo_btype;
			$mid = $row->mo_mid;

			if ($timestart + $limittime <= time()) {
				break;
			}
			touch($this->log_file);


			try {
				$table_batch = tr_pdd_get_table_by_btype($btype);
				$assen = $wpdb->get_var("select assen from {$table_batch} where mid = {$mid} ");
				if (empty($assen)) {
					$this->log("ID not exist: #{$mid} \n");
					continue;
				} else {
					$this->log("Uploading assen: {$assen}");
				}

				if (!empty($this->req_folder)) {
					$req_pdf = trpdd_get_req_pdf($mid, $btype);
					$remote_req_file = $this->req_folder . '/' . $assen . '.pdf';

					if (!$req_pdf || !file_exists($req_pdf)) {
						$this->log("error when create req file: {$remote_req_file} ");
						break;
					}

					if (isset($_REQUEST['testcron'])) {
						echo 'uploading req file: ' . $remote_req_file . '<br>';
					}

					$this->uploadFile($req_pdf, $remote_req_file);

					$this->log("uploaded: {$remote_req_file} ");
				}


				if (!empty($this->result_folder)) {
					$result_pdf = trpdd_export_results_pdf($mid, 'mid', false, $btype);

					$remote_result_file = $this->result_folder . '/' . $assen . '.pdf';

					if (empty($result_pdf) || !file_exists($result_pdf)) {
						$this->log("error when create result {$assen} file: {$remote_result_file} \n");
						break;
					}


					if (isset($_REQUEST['testcron'])) {
						echo 'uploading result  file: ' . $remote_result_file . '<br>';
					}

					$this->uploadFile($result_pdf, $remote_result_file);
					$this->log("uploaded: {$remote_result_file} \n");
				}

				$wpdb->update('ci_sync_api_orders', array(
					'mo_status' => 1,
					'mo_set_ID' => date_i18n('Y-m-d H:i:s'),
				), array('mo_ID' => $row->mo_ID));
				$count++;
			} catch (Exception $e) {
				var_dump($e);

				$this->log('Error pdf break: ' . $e->getMessage());

				break;
			}
		}

		$this->log("count pdf: " . $count);

		return count($rows) > 0;
	}
}
