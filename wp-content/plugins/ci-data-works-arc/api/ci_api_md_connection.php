<?php
//for billing

include_once('ci_hl7.php');

class CI_Api_MD_Connection extends CI_Hl7
{
	protected $conn;
	protected $sftp;
	protected $options;

	public $mids = array();
	public $current_date;
	public $debug = false;
	public $log = '';
	public $api_id = -30;
	public $api_key = 'billmd';

	public $dir = WP_CONTENT_DIR . '/log/md_connection/';
	public $log_file = WP_CONTENT_DIR . '/log/log_api_md_connection.txt';

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

		$this->conn = @ssh2_connect($this->options['host'], $this->options['port']);

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
			throw new Exception("rev- Could not open local file: $local_path");
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
			throw new Exception("upload- Could not open local file: $local_file.");
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

	public function log($message)
	{
		$f_log = fopen($this->log_file, 'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => ' . $message . "\n");
		fclose($f_log);

		$this->log .= $message . "\n";
	}

	public function sync_in()
	{
		global $wpdb;
		$folder = $this->options['in_folder'];
		$old_archive_dir = $folder . '/archive/';
		$archive_dir = '/archive/';

		ssh2_sftp_rmdir($this->sftp, $old_archive_dir);
		ssh2_sftp_mkdir($this->sftp, $archive_dir);

		$files = $this->scan_dir($folder);

		foreach ($files as $file) {
			$file_path = $folder . '/' . $file;
			if (stripos($file, '.csv') > 0) {
				$filetime = $this->getFileTime($file_path);
				$arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => $this->api_key,
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='{$this->api_key}' ");
				if ($dbfile) {
					if ($filetime <= $dbfile->m_time) {
						$this->movefile($file_path, $archive_dir . $file);
						continue;
					}
				} else {
					$wpdb->insert('ci_sync_api_files', $arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='{$this->api_key}'");
				}

				$done = $this->import_file($dbfile, $file_path);

				if ($done) {
					$this->movefile($file_path, $archive_dir . $file);
				}
			}
		}
	}

	function import_file($dbfile, $file_path)
	{
		global $wpdb;

		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		$header = array();
		$log_file = $this->dir . basename($file_path);
		if (file_exists($log_file)) {
			unlink($log_file);
		}
		$logfile = fopen($log_file, 'a+');
		while ($row = fgetcsv($stream)) {
			fputcsv($logfile, $row);

			if (empty($header)) {
				foreach ($row as $pos => $k) {
					$header[strtolower(trim($k))] = $pos;
				}
				if ($this->debug) {
					$this->log .= "header:\n" . json_encode($header) . "\n";
				}
				continue;
			}

			if ($this->debug) {
				$this->log .= "row:\n" . json_encode($row) . "\n";
			}

			$AccessionNumber    = $row[$header['accessionnumber']];
			$ServiceDate        = $row[$header['servicedate']];
			$ClaimID         	= $row[$header['claimid']];
			$BilledAmount         	= $row[$header['billedamount']];
			$AdjustmentAmount       = $row[$header['adjustmentamount']];
			$ReceivedAmount         = $row[$header['receivedamount']];
			$BalanceAmount         	= $row[$header['balanceamount']];
			$ReceivedDate         	= $row[$header['receiveddate']];
			$ClaimStatus         	= $row[$header['claimstatus']];

			if (empty($AccessionNumber)) {
				continue;
			}

			$btype = 'pcr';
			$batch =  array();
			foreach ($this->options['btypes'] as $bt) {
				$table = tr_pdd_get_table_by_btype($bt);

				$batch = $wpdb->get_row("select * from {$table} where assen = '{$AccessionNumber}'", ARRAY_A);
				if ($batch) {
					$btype = $bt;
					break;
				}
			}


			$bill = $wpdb->get_row("select * from ci_batch_billings where assen = '{$AccessionNumber}' ");
			$billdata = array(
				'claim_no' => $ClaimID,
				'servdate' => $ServiceDate,
				'billed_amount' => $BilledAmount,
				'allowed_amount' => $AdjustmentAmount,
				'received_amount' => $ReceivedAmount,
				'balance' => $BalanceAmount,
				'date' => $ReceivedDate,
				'bill_status' => $ClaimStatus,
			);
			if (!$bill) {
				$qrs = $wpdb->insert('ci_batch_billings', $billdata);
				if ($qrs === false) {
					$this->log("Insert Error: " . $wpdb->last_error . " => " . json_encode($billdata));
				}
			} else {
				$qrs = $wpdb->update('ci_batch_billings', $billdata, array('id' => $bill->id));
				if ($qrs === false) {
					$this->log("Update Error: " . $wpdb->last_error . " => " . json_encode($billdata));
				}
			}

			$logarr = array(
				'mo_file_id' => $dbfile->m_ID,
				'mo_peg_ID' => $this->api_id,
				'mo_mid' => $batch['mid'],
				'mo_btype' => $btype,
				'mo_set_ID' => date_i18n('Y-m-d H:i:s')
			);
			$mo_ID = $wpdb->get_var("select mo_ID from ci_sync_api_orders 
					where mo_btype = '{$btype}' 
					and mo_peg_ID = {$this->api_id}
					and mo_mid = {$batch['mid']}");
			if ($mo_ID > 0) {
				$logarr = array(
					'mo_file_id' => $dbfile->m_ID,
					'mo_set_ID' => date_i18n('Y-m-d H:i:s')
				);
				$wpdb->update('ci_sync_api_orders', $logarr, array('mo_ID' => $mo_ID));
			} else {
				$wpdb->insert('ci_sync_api_orders', $logarr);
			}
		}
		@fclose($stream);
		@fclose($logfile);
		return true;
	}

	public function sync_out($args = array())
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

		$btypes = tr_pdd_get_batch_types();

		if (is_array($this->options['btypes']) && count($this->options['btypes']) > 0) {
			$btypes = $this->options['btypes'];
		}

		var_dump($btypes);
		echo '<br>';

		$count_file = 0;

		foreach ($btypes as $btype) {
			$this->mids = array();

			$local_files = $this->build_hl7($this->current_date, $btype, $args);

			if (count($local_files) > 0) {
				$count_file += count($local_files);
				if (isset($_GET['notupload'])) {
					continue;
				}


				foreach ($local_files as $local_file) {

					try {
						$filename = basename($local_file);

						$remote_file = $this->options['out_folder'] . '/' . $filename;
						if (isset($_REQUEST['testcron'])) {
							echo 'uploading file: ' . $remote_file . '<br>';
						}

						$this->uploadFile($local_file, $remote_file);
						$this->log("uploaded: {$remote_file} \n");

						//insert log db
						if (isset($this->mids[$filename]) && is_array($this->mids[$filename])) {
							foreach ($this->mids[$filename] as $mid) {
								$logarr = array(
									'mo_peg_ID' => $this->api_id,
									'mo_mid' => $mid,
									'mo_btype' => $btype,
									'mo_test_ID' => date_i18n('Y-m-d H:i:s'),
									'mo_control_ID' => $filename,
								);
								$mo_ID = $wpdb->get_var("select mo_ID from ci_sync_api_orders 
										where mo_btype = '{$btype}' 
										and mo_peg_ID = {$this->api_id}
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
						}
					} catch (Exception $e) {
						$this->log("sync error: " . $e->getMessage());
					}
				}
			}
		}


		return $count_file;
	}

	public function build_hl7($date, $btype, $args = array())
	{
		global $wpdb;

		$files = array();

		$file_rs = false;
		if ($this->options['fileby'] == 0) {
			$filename   = $date . '_' . $btype . '.hl7';
			$local_file = $this->dir . '/' . $filename;
			$files[] = $local_file;
			file_put_contents($local_file, '');
			$file_rs  = fopen($local_file, 'w');

			if (isset($_REQUEST['testcron'])) {
				echo 'file: ' . $filename . '<br>';
			}
		}




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


		$where .= " and re.estatus in ('C','F') ";

		if (isset($args['assens'])) {
			$assens_str = implode("','", $args['assens']);
			if (empty($assens_str)) $assens_str = 'abctest';
			$where .= " and ba.assen in ('{$assens_str}') ";
		} else {
			$where .= " and ((re.id > 0  and re.approve_date >= '{$date}' 
							and re.approve_date <= '{$date} 23:59:59') 
                or (re.id is null and ba.result_pdf!='' and ba.result_pdf is not null 
                            and  ba.batch_created >= '{$date}' 
                            and ba.batch_created <= '{$date} 23:59:59' ))";

			if (isset($_GET['only_missing']) || $this->options['fileby'] == 1) {
				$where .= " and ba.mid not in (
						select mo_mid from `ci_sync_api_orders` 
						where mo_peg_ID = {$this->api_id}  ) ";
			}
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
		foreach ($rows as $r) {
			$cpt_codes_arr = array();

			if (empty($r->panel_tests_requested)) {
				continue;
			}

			$assen = !empty($r->oldreq) ? $r->oldreq : $r->assen;

			$doctor_name = $r->doc_first . ' ' . $r->doc_last;

			if (strtoupper($r->pa_address) == 'N/A' || $r->pa_address == '0') {
				$r->pa_address = '';
			}
			if (strtoupper($r->pa_city) == 'N/A' || $r->pa_city == '0') {
				$r->pa_city = '';
			}
			if (strtoupper($r->pa_state) == 'N/A' || $r->pa_state == '0') {
				$r->pa_state = '';
			}
			if (strtoupper($r->pa_zip) == 'N/A' || $r->pa_zip == '0') {
				$r->pa_zip = '';
			}
			if ($r->pa_phone == '000-00-0000' || $r->pa_phone == '0000-0000' || $r->pa_phone == '0') {
				$r->pa_phone = '';
			}

			if (strtoupper($r->ins_address) == 'N/A' || $r->ins_address == '0') {
				$r->ins_address = '';
			}
			if ($r->ins_city == 'N/A' || $r->ins_city == '0') {
				$r->ins_city = '';
			}
			if ($r->ins_state == 'N/A' || $r->ins_state == '0') {
				$r->ins_state = '';
			}
			if ($r->ins_zip == 'N/A' || $r->ins_zip == '0') {
				$r->ins_zip = '';
			}
			if ($r->ins_phone == '000-00-0000' || $r->ins_phone == '0000-0000' || $r->ins_phone == '0') {
				$r->ins_phone = '';
			}

			if (strtoupper($r->ins_company) == 'N/A' || stripos($r->ins_company, 'No Insurance - Cash') !== false || stripos($r->ins_company, 'Client bill') !== false) {
				$r->ins_company = '';
			}


			if (strtoupper($r->ins_member_id) == 'N/A') {
				$r->ins_member_id = '';
			} else {
				$r->ins_member_id = str_replace(array('-', ' ', '_', '/'), '', $r->ins_member_id);
				$r->ins_member_id = trim($r->ins_member_id);
			}

			$r->withobj = true;
			$cpt_codes_arr = tr_billing_get_cpt_code($r);


			if (empty($cpt_codes_arr) && in_array($r->btype, array('pcr', 'blood'))) {
				$testids = explode(',', $r->panel_tests_requested);

				foreach ($testids as $testid) {
					$testid = trim($testid);
					$testid = str_replace('+', '', $testid);
					if (stripos($testid, 'group_') !== false) {
						$group_id = str_ireplace('group_', '', $testid);
						if ($btype == 'blood') {
							if (isset($all_tests['only_' . $group_id])) {
								$testobj = $all_tests['only_' . $group_id];

								if (!empty($testobj->te_abrev)) {
									$cpt_codes_arr[$testobj->te_abrev] = 1;
								}
							}
						}
					} else if (isset($all_tests[$testid])) {
						$group_id = $all_tests[$testid]->te_group_id;

						$testobj = $all_tests[$testid];
						$testname = $testobj->te_name;
						if ($btype == 'blood' && !empty($testobj->te_abrev)) {
							$cpt_codes_arr[$testobj->te_abrev] = 1;
						}
					}
				}
			}



			$hl7_arr = array();
			$hl7_arr['MSH'] = $this->getMSH(time() . rand(1000, 9999), $r->lab_clia, $r->lab_name, 'DFT', 'P03', '');

			$hl7_arr['PID'] = $this->getPID(
				1,
				$r->pa_ID,
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
				$r->assen
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
				$r->ins_group_id,
			);

			foreach ($cpt_codes_arr as $cpt => $qty) {
				$hl7_arr[] = $this->getFT1(
					$r->doc,
					$r->dagnostic_codes,
					$cpt,
					$qty
				);
			}



			$hl7_arr['DG1'] = $this->getDG1($r->dagnostic_codes);



			$hl7_data = $this->array_to_hl7($hl7_arr);

			//echo str_replace(array("\r","\n"),"<br>",$hl7_data);exit;

			if ($file_rs) {
				fputs($file_rs, $hl7_data . "\r\n");
			} else {
				$filename = $assen . '.hl7';
				$local_file = $this->dir . '/' . $filename;
				$files[] = $local_file;
				file_put_contents($local_file, $hl7_data);
			}

			if (!isset($this->mids[$filename])) {
				$this->mids[$filename] = array();
			}
			$this->mids[$filename][] = $r->mid;
		}

		if ($file_rs) {
			fclose($file_rs);
		}


		return $files;
	}
}
