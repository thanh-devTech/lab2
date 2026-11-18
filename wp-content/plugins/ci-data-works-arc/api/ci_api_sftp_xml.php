<?php

class CI_Api_Sftp_Xml{
	protected $conn;
	protected $sftp;
	protected $lab_id;
	protected $clinic_id;
	protected $doc_id;
	protected $host;
	protected $port;
	protected $username;

	public $debug = false;
	public $log='';

	public function __construct($lab_id,$clinic_id=0,$doc_id=0) {

		$this->lab_id = $lab_id;
		$this->clinic_id = $clinic_id;
		$this->doc_id = $doc_id;

		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
		}
	}

	public function connect($host, $port = 22) {

		$this->host = $host;
		$this->port = $port;
		$this->conn = @ssh2_connect($host, $port);

		if (!$this->conn) {
			throw new Exception('Unable to connect');
		}
	}

	public function login($username,$password)
	{
		$this->username = $username;

		$result = ssh2_auth_password($this->conn,$username,$password);

		if ($result === false) {
			throw new Exception('Login incorrect');
		}

		$this->sftp = @ssh2_sftp($this->conn);

		return $result;
	}

	public function getpath($path)
	{
		if($this->host=='localhost'){
			$path = '/home/'.$this->username.$path;
		}
		return "ssh2.sftp://{$this->sftp}{$path}";
	}

	public function scan_dir($path)
	{
		$dir = $this->getpath($path);
		$tempArray = array();
		$handle = opendir($dir);
		if($handle)
		{
			while (false !== ($file = readdir($handle))) {
				if (substr("$file", 0, 1) != "."){
					$tempArray[]=$file;
				}
			}
		}

		closedir($handle);
		return $tempArray;
	}

	public function receiveFile($remote_file,$local_path)
	{
		$path = $this->getpath($remote_file);
		$stream = @fopen($path, 'r');
		if (! $stream)
			throw new Exception("Could not open file: $remote_file");
		$f = fopen($local_path,'w');
		if($f)
		{
			while (($buffer = fgets($stream, 4096)) !== false) {
				fwrite($f,$buffer);
			}
		}else{
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

	public function removeDir($path)
	{
		try {
			return ssh2_sftp_rmdir($this->sftp, $path);
		} catch (Exception $e) {
		}
		return false;
	}

	public function getFileSize($remote_file){
		$path = $this->getpath($remote_file);
		return filesize($path);
	}

	public function getFileTime($remote_file)
	{
		$path = $this->getpath($remote_file);
		return filemtime($path);
	}

	public function sync_reqs($folder_path)
	{
		global $wpdb;

		if($this->debug )
		{
			$this->log.= " list file:\n";
		}
		$files = $this->scan_dir($folder_path);
		$count_files = 0;
		if($this->debug )
		{
			$this->log.= "count: ".count($files)."\n";
		}
		foreach($files as $file)
		{
			$file_path = $folder_path.'/'.$file;
			if(stripos($file,'.xml')>0)
			{
				$filetime = $this->getFileTime($file_path);
				$arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => 'noah'
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='noah'");
				if($dbfile)
				{
					if($filetime <= $dbfile->m_time)
					{
						//continue;
					}
				}else{
					$wpdb->insert('ci_sync_api_files',$arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}'  and m_type='noah'");
				}

				$this->import_req_file($dbfile,$file_path);
				$count_files++;

			}
		}

		if($this->debug && $count_files= 0)
		{
			$this->log.= " No file need sync\n";
		}
	}

	public function import_req_file($dbfile,$file_path)
	{
		global $wpdb;

		if($this->debug)
		{
			$this->log.= "file: ".$file_path."\n";
		}


		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		$contents = '';
		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents.= $buffer;
		}

		$data = simplexml_load_file($contents);

		$clinic_name = '';
		if($this->clinic_id>0)
		{
			$clinic_name = $wpdb->get_var( "select cli_clinic from ci_clinic where cID = '{$this->clinic_id}' " );
		}

		$default_doctor_name = '';
		if($this->doc_id>0)
		{
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->doc_id}");
			$default_doctor_name = $doctor->doc_first.' '.$doctor->doc_last;
		}


		$batch_tbl = 'ci_batches_pcr';
		$tests = array();
		$rs = $wpdb->get_results("select * from ci_test_panels where te_type = 'pcr' ");
		foreach($rs as $r)
		{
			$tests[strtoupper($r->te_name)] = $r->te_ID;
		}
		$rs = $wpdb->get_results("select * from ci_test_groups where tg_type='pcr' ");
		foreach($rs as $r)
		{
			$tests[strtoupper($r->tg_name)] = 'GROUP_'.$r->tg_ID;
		}


		var_dump($data);
		foreach($data->PATIENT as $k => $paobj)
		{
			$pa_first = (string)$paobj->FIRST_NAME;
			$pa_last = (string)$paobj->LAST_NAME;
			$gender = (string) $paobj->GENDER;
			$dob = (string) $paobj->BIRTH_DATE;
			$phone = (string) $paobj->HOME_PHONE;
			$email = (string) $paobj->EMAIL_DEFAULT;
			$address = (string) $paobj->ADDRESS1;
			$city = (string) $paobj->CITY;
			$state = (string) $paobj->STATE;
			$zip_code = (string) $paobj->ZIP_CODE;
			$ssn = (string) $paobj->SSN;

			$dob    = date( 'Y-m-d', strtotime( $dob ) );

			$gender = stripos( $gender, 'u' ) !==false ? 'U' : $gender;
			$gender = stripos( $gender, 'f' ) !==false ? 'F' : 'M';

			$patientid = 0;
			$insurance_id = 0;

			$insurance = array();
			foreach($paobj->PATIENT_INSURANCES->PATIENT_INSURANCE as $ins)
			{
				$insurance['ins_last']  = (string) $ins->LAST_NAME;
				$insurance['ins_first']  = (string) $ins->FIRST_NAME;
				$insurance['ins_address']  = (string) $ins->INSURANCE_ADDRESS1;
				$insurance['ins_city']  = (string) $ins->INSURANCE_CITY;
				$insurance['ins_state']  = (string) $ins->INSURANCE_STATE;
				$insurance['ins_zip']  = (string) $ins->INSURANCE_ZIP_CODE;
				$insurance['ins_relation']  = (string) $ins->RELATIONSHIP_NAME;
				$insurance['ins_member_id']  = (string) $ins->PATIENT_INSURANCE_ID;
				$insurance['ins_group_id']  = (string) $ins->GROUP_NO;
				$insurance['ins_company']  = (string) $ins->INSURANCE_NAME;

				$insurance['ins_relation'] = ucfirst(strtolower($insurance['ins_relation']));
				if(empty($insurance['ins_relation']))
				{
					$insurance['ins_relation'] = 'Self';
				}
				break;
			}


			foreach($paobj->REQUISITIONS->REQUISITION as $REQUISITION)
			{

				$req_code = (string)$REQUISITION->REQUISITION_CODE;

				$doc = (string)$REQUISITION->COLLECT_DATE;
				$received_date = (string) $REQUISITION->RECEIVED_DATE;
				$source = '';
				$doc    = date( 'Y-m-d', strtotime( $doc ) );

				$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$req_code}'
							      and mo_file_id = '{$dbfile->m_ID}' ");
				$mid = $orderdb && $orderdb->mo_mid> 0? $orderdb->mo_mid : 0;
				if($mid>0)
				{
					$bat = $wpdb->get_row( "select mid from ci_batches_pcr where mid = {$mid} " );
				}else{
					$bat = $wpdb->get_row( "select mid from ci_batches_pcr where oldreq = '{$req_code}' " );
				}


				if($bat)
				{
					$assen = $bat->assen;
				}else{
					$assen  = tr_get_new_assen();
				}

				$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : $patientid;
				if ( $patientid == 0 ) {

					$patientid = $wpdb->get_var( "select pa_ID from ci_patients where pa_first='{$pa_first}'
				                and pa_last='{$pa_last}' and pa_dob='{$dob}' " );

					if ( $patientid == 0 ) {
						$patient = array(
							'pa_first'  => $pa_first,
							'pa_last'   => $pa_last,
							'pa_dob'    => $dob,
							'pa_gender' => $gender,
							'pa_phone'  => $phone,
							'pa_address' => $address,
							'pa_city' => $city,
							'pa_state' => $state,
							'pa_zip' => $zip_code,
							'pa_email' => $email,
							'pa_ssn' => $ssn,
							'pa_mrn' => $req_code
						);

						//fix phone
						if(isset($patient['pa_phone'])){
							$patient['pa_phone'] = str_replace(array(' ','(',')','-','.'),'',$patient['pa_phone']);
						}

						$wpdb->insert( 'ci_patients', $patient );
						$patientid = $wpdb->insert_id;
					}
				}

				$npi = '';
				$doc_first = '';
				$doc_last = '';
				foreach($paobj->DOCTOR_EID->DOCTOR as $docobj)
				{
					$npi = (string) $docobj->NPI;
					$doc_first = (string) $docobj->FIRST_NAME;
					$doc_last = (string) $docobj->LAST_NAME;
				}

				$doc_id = $wpdb->get_var( "select doc_ID from ci_doc where doc_npi = '{$npi}'   
										and doc_first='{$doc_first}' and doc_last='{$doc_last}' " );

				$clinic_name = $REQUISITION->LOCATION_EID->LOCATION->ORGANIZATION_NAME;

				$cli_id = $wpdb->get_var( "select cID from ci_clinic where cli_clinic = '{$clinic_name}' " );

				$req_code = array();
				foreach($REQUISITION->REQ_DIAGNOSES->REQ_DIAGNOSIS as $diag)
				{
					$req_code[] = (string)$diag->DIAGNOSIS_CODE;
				}
				$panel_tests_requested = array();
				foreach($REQUISITION->REQ_PANELS->REQ_PANEL as $pnel)
				{
					$panel = (string)$pnel->PANEL_ID->PANEL->PANEL_NAME;
					$panel = strtoupper(trim($panel));
					if(isset($tests[$panel])){
						$panel_tests_requested[] = $tests[$panel];
					}
				}
				$panel_tests_requested = implode(',',$panel_tests_requested);


				$insurance_id = $bat && $bat->insurance1>0? $bat->insurance1 : $insurance_id;

				if($insurance_id==0)
				{
					$wpdb->insert('ci_insurances',$insurance);
					$insurance_id = $wpdb->insert_id;
				}


				$req_code = implode(',',$req_code);

				$patient_name = $pa_last . ', ' . $pa_first;
				$data         = array(
					'assen'                => $assen,
					'patient_id'           => $patientid,
					'patient_name'         => $patient_name,
					'lab_ID'               => $this->lab_id,
					'doc_ID'               => intval( $doc_id ),
					'cli_ID'               => intval( $cli_id ),
					'doc'                  => $doc,
					'spec_received'        => $received_date,
					'dob'                  => $dob,
					'requesting_physician' => $doc_last . ', ' . $doc_first,
					'requesting_practice'  => $clinic_name,
					'type_of_specimen'     => $source,
					'dagnostic_codes1'      => $req_code,
					'insurance'             => $insurance['ins_company'],
					'insurance1'            => $insurance_id,
					'panel_tests_requested' => $panel_tests_requested
				);


				if ( $bat ) {
					$mid = $bat->mid;
					$wpdb->update( $batch_tbl, $data, array( 'mid' => $bat->mid ) );
				} else {
					$wpdb->insert( $batch_tbl, $data );
					$mid = $wpdb->insert_id;
				}

				$update = array(
					'mo_file_id'    => $dbfile->m_ID,
					'mo_mid'        => $mid,
					'mo_test_ID'        => $req_code,
				);
				if($orderdb && $orderdb->mo_ID>0)
				{
					$wpdb->update('ci_sync_api_orders',$update,array('mo_ID'=>$orderdb->mo_ID));
				}else {
					$wpdb->insert( 'ci_sync_api_orders', $update );
				}


			}
		}

	}


	public function sync_reports($results_path)
	{
		global $wpdb;
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';

		$hl7_dir = WP_CONTENT_DIR.'/sftp_hl7/';
		if(!file_exists($hl7_dir))
		{
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
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid and mo.mo_status = 0
				JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='noah'
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				where re.estatus = 'C'
				";
		$rs = $wpdb->get_results($sql);

		$report_files = array();

		if($this->debug && count($rs)==0)
		{
			if(!empty($wpdb->last_error))
			{
				$this->log.= "error: ".$wpdb->last_error;
			}
			$this->log.= "Not found results!";
		}
		
		$mo_ids = array();

		foreach($rs as $r)
		{
			$filename = basename($r->m_file);
			$hl7_file = $hl7_dir.$filename;
			
			if(!isset($report_files[$hl7_file]))
			{
				$report_files[$hl7_file] = fopen($hl7_file,'a+');
				$mo_ids[$hl7_file] = array();
			}


			$arr = trpdd_cal_ncovid19(array(),$r->id,$r->instrument,$r->created_date);
			$r->result_status = 'F';
			$test_id = !empty($covid_test['te_loinc_code'])? $covid_test['te_loinc_code'] : '94500-6';
			$test_name = !empty($covid_test['te_name'])? $covid_test['te_name'] : 'nCoV-2019';

			$hl7_arr = array();
			$hl7_arr['MSH'] = $this->getMSH($r->mo_control_ID,$r->lab_clia,$r->lab_name,'ORU','R01', 'ORU_R01');

			$hl7_arr['PID'] = $this->getPID($r->mo_set_ID,$r->mo_patient_ID,$r->pa_first,$r->pa_last,$r->pa_ssn,$r->pa_gender,$r->pa_dob,
				$r->pa_address,$r->pa_city,$r->pa_state,$r->pa_zip,$r->pa_phone, $r->pa_email);

			$hl7_arr['ORC'] = $this->getORC($r->mo_test_ID,$r->assen,$r->doc,$r->lab_name,
										$r->doc_npi,$r->doc_first,$r->doc_last);

			$hl7_arr['OBR'] = $this->getOBR($r->mo_test_ID,$r->assen,$r->lab_name,$r->doc_npi,$r->doc_first,$r->doc_last,
				$test_id,$test_name,$r->approve_date,$r->result_status,$r->mo_peg_ID);

			$hl7_arr['OBX'] = $this->getOBX($r->lab_clia,$r->lab_name,$test_id,$test_name,$pos_neg[$arr['pos-neg']],$r->result_status);

			$hl7_data = $this->array_to_hl7($hl7_arr);

			$old_data = file_get_contents($hl7_file);
			if(!empty($old_data))
			{
				if(stripos($old_data,$r->assen)>0)
				{
					ftruncate($report_files[$hl7_file], 0);
				}
			}

			fputs($report_files[$hl7_file],$hl7_data."\r\n");
			
			$mo_ids[$hl7_file][] = $r->mo_ID;
		}

		foreach($report_files as $hl7_file => $f)
		{
			fclose($f);
			//echo file_get_contents($hl7_file);exit;
			$local_file = $hl7_file;
			$remote_file = $results_path.'/'.basename($hl7_file);
			try{
				$this->uploadFile($local_file,$remote_file);
				if($this->debug)
				{
					$this->log.= "uploaded: {$remote_file} \n";
				}
				
				$mo_id_str = implode(',',$mo_ids[$hl7_file]);
				$wpdb->query("update ci_sync_api_orders set mo_status = 1 where mo_ID in ({$mo_id_str})");

				
			}catch (Exception $e)
			{
				var_dump($e);
			}
		}

	}
}