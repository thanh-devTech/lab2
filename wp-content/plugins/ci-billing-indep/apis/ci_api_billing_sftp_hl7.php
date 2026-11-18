<?php
include('ci_hl7.php');

class CI_Api_Billing_Sftp_Hl7 extends CI_Hl7
{
	protected $conn;
	protected $sftp;
	protected $lab_id;
	protected $clinic_id;
	protected $doc_id;

	public $debug = false;
	public $log = '';
	public $api_id = -5;
	public $log_dir = WP_CONTENT_DIR . '/log/billing_sftp7/';

	public function __construct($lab_id, $clinic_id, $doc_id)
	{

		$this->lab_id = $lab_id;
		$this->clinic_id = $clinic_id;
		$this->doc_id = $doc_id;

		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
		}
	}

	public function connect($host, $port = 22)
	{

		$this->conn = @ssh2_connect($host, $port);

		if (!$this->conn) {
			throw new Exception('Unable to connect');
		}
	}

	public function login($username, $password)
	{
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

	public function mkdir($path)
	{
		return mkdir($this->getpath($path));
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



	public function sync_reports($results_path, $limit = 100)
	{
		global $wpdb;

		$sql_child = array();

		$where = " and re.estatus in ('C','F') ";

		$where .= "  ";

		//check exclude lab
		$exclude_labids = $wpdb->get_col("select lab_ID from ci_labs where lab_send_billing = 0");
		if (count($exclude_labids) > 0) {
			$exclude_labids_str = implode(',', $exclude_labids);
			$where .= " and ba.ref_lab_ID not in ({$exclude_labids_str}) ";
		}


		foreach (trbilling_req_types() as $t) {

			if ($t == 'study') continue;


			$batch_tbl = tr_pdd_get_table_by_btype($t);
			$report_tbl = tr_pdd_get_table_report_by_btype($t);

			$sql_child[] = "(select 
                        ba.dagnostic_codes1,
                        ba.assen,
                        ba.mid,
                        ba.insurance,
                        ba.panel_tests_requested,
                        ba.doc,
                        
                        doc.doc_ID,
                        doc.doc_last,
                        doc.doc_first,
                        
                        pal.payer_mnemonic,
                        
                        ins.ins_ID,
                        ins.ins_first,
                        ins.ins_last,
                        ins.ins_address,
                        ins.ins_city,
                        ins.ins_state,
                        ins.ins_zip,
                        ins.ins_phone,
                        ins.ins_member_id,
                        ins.ins_group_id,
                        ins.ins_mnemonic,
                        
                        ins2.ins_ID as ins_ID2,
                        ins2.ins_first as ins_first2,
                        ins2.ins_last as ins_last2,
                        ins2.ins_address as ins_address2,
                        ins2.ins_city as ins_city2,
                        ins2.ins_state as ins_state2,
                        ins2.ins_zip as ins_zip2,
                        ins2.ins_phone as ins_phone2,
                        ins2.ins_member_id as ins_member_id2,
                        ins2.ins_group_id as ins_group_id2,
                        ins2.ins_mnemonic as ins_mnemonic2,
                        
                        pa.pa_ID,
                        pa.pa_first,
                        pa.pa_last,
                        pa.pa_address,
                        pa.pa_city,
                        pa.pa_state,
                        pa.pa_zip,
                        pa.pa_ssn,
                        pa.pa_dob,
                        pa.pa_mrn,
                        pa.pa_gender,
                        pa.pa_phone,
                        re.id,
                        re.approve_date,
                        '{$t}' as btype,
                        cli.cli_facility_code,
                        
                        lab.lab_dba_init as lab_name
                
                    from {$batch_tbl} ba
                    left join {$report_tbl} re ON  ba.assen = re.patient_id  
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    join ci_clinic cli On cli.cID = ba.cli_ID
                    
                    left join ci_insurances ins ON ins.ins_ID = ba.insurance1
                    left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
                    left join ci_payer_list pal ON pal.payer_name = ba.insurance
                    left join ci_labs lab ON lab.lab_ID = ba.lab_ID or (ba.lab_ID is null and lab.lab_ID = 1)
                    
                    left JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
                                    and mo.mo_file_id = {$this->api_id}
                                    
                    where mo.mo_ID is null {$where} 
                    group by ba.mid 
                    order by ba.mid desc 
                    limit {$limit} ) ";
		}

		$sql_child = implode("\n UNION ALL \n", $sql_child);

		$sql = "select * from ( {$sql_child} ) as rs  limit {$limit}";

		$rs = $wpdb->get_results($sql);


		$files = array();
		foreach ($rs as $i => $row) {
			$referring_provider_code = substr($row->doc_last, 0, 5) . substr($row->doc_first, 0, 1);
			$referring_provider_code = strtoupper($referring_provider_code);

			$cpt_code = tr_billing_get_cpt_code($row);
			//if(empty($cpt_code))continue;

			if (empty($row->approve_date)) {
				$row->approve_date = date('Y-m-d', strtotime('+1day', strtotime($row->doc)));
			}
			$filepath = $this->log_dir . '/' . $row->assen . '.hl7';

			$msg = $this->build_msg($row);
			file_put_contents($filepath, $msg);
			$files[] = $filepath;

			if ($results_path !== false) {
				$remote_file = $results_path . '/' . basename($filepath);
				$u = $this->uploadFile($filepath, $remote_file);
				if ($u) {
					$wpdb->insert('ci_sync_api_orders', array(
						'mo_file_id' => $this->api_id,
						'mo_mid' => $row->mid,
						'mo_status' => '1',
					));
				}
			}
		}


		if ($results_path === false) {
			return $files;
		}
	}

	public function build_msg($r)
	{

		$control_id = md5($r->assen);
		if (empty($r->pa_mrn)) {
			$r->pa_mrn = $r->pa_ID;
		}
		if (empty($r->ins_mnemonic)) {
			$r->ins_mnemonic = $r->payer_mnemonic;
		}
		$r->ins_relation = '01';
		$r->ins_relation2 = '01';

		$hl7_arr = array();
		$hl7_arr['MSH'] = $this->getMSH(
			$control_id,
			'0101',
			'GENIUS',
			'ADT',
			'A08',
			'',
			17,
			'igniteLIS',
			$r->lab_name,
			'AL',
			'NE',
			'US'
		);

		$hl7_arr['EVN'] = $this->getEVN('A08', $r->doc);

		$hl7_arr['PID'] = $this->getPID(
			1,
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
			$r->pa_email
		);


		$hl7_arr['PV1'] = $this->getPV1('1', 'O', '0101', $r->doc_ID, $r->doc_first, $r->doc_last);

		$hl7_arr['IN1'] = array();
		$hl7_arr['IN1'][] = $this->getIN1(
			'1',
			'1',
			$r->ins_mnemonic,
			$r->insurance,
			$r->ins_address,
			$r->ins_city,
			$r->ins_state,
			$r->ins_zip,
			$r->ins_phone,
			'P',
			$r->ins_first,
			$r->ins_last,
			$r->ins_relation,
			$r->ins_member_id
		);

		if ($r->ins_ID2 > 0) {
			$hl7_arr['IN1'][] = $this->getIN1(
				'2',
				'2',
				$r->ins_mnemonic2,
				$r->insurance2,
				$r->ins_address2,
				$r->ins_city2,
				$r->ins_state2,
				$r->ins_zip2,
				$r->ins_phone2,
				'P',
				$r->ins_first2,
				$r->ins_last2,
				$r->ins_relation2,
				$r->ins_member_id2
			);
		}

		$hl7_data = $this->array_to_hl7($hl7_arr);

		return $hl7_data;
	}
}
