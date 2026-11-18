<?php

class CI_Api_Meenta
{
	protected $conn;
	protected $sftp;
	protected $host = 'files.meenta.io';
	protected $port = 22;
	protected $lab_id;
	protected $clinic_id;
	protected $root_folder;

	public $debug = false;
	public $is_test = false;
	public $log='';
	public $api_id = -7;

	public function __construct($lab_id,$clinic_id,$root_folder) {

		$this->lab_id = $lab_id;
		$this->clinic_id = $clinic_id;
		$this->root_folder = $root_folder;

		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
		}
	}

	public function connect() {

		$this->conn = @ssh2_connect($this->host, $this->port);

		if (!$this->conn) {
			throw new Exception('Unable to connect');
		}
	}

	public function login($username,$password)
	{
		$result = ssh2_auth_password($this->conn,$username,$password);

		if ($result === false) {
			throw new Exception('Login incorrect');
		}
		$this->sftp = @ssh2_sftp($this->conn);

		return $result;
	}

	public function getpath($path)
	{
		$path = ($this->is_test? '/staging':'/production').$path;
		return "ssh2.sftp://{$this->sftp}/{$this->root_folder}{$path}";
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
		$contents = fread($stream, filesize($path));
		file_put_contents ($local_path, $contents);
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

	public function getFileSize($remote_file){
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

		$folder = '/orders';
		$files = $this->scan_dir($folder);

		foreach($files as $file)
		{
			$file_path = $folder.'/'.$file;
			if(stripos($file,'.csv')>0)
			{
				$filetime = $this->getFileTime($file_path);
				$arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => 'meenta',
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='meenta' ");
				if($dbfile)
				{
					if($filetime <= $dbfile->m_time)
					{
						continue;
					}
				}else{
					$wpdb->insert('ci_sync_api_files',$arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='meenta'");
				}

				$this->import_req_file($dbfile,$file_path);

			}
		}
	}

	public function import_req_file($dbfile,$file_path)
	{
		global $wpdb;

		if($this->debug)
		{
			$this->log.= "file: ".$file_path."\n";
		}
		$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where (tg_is_covid=1 or tg_name ='nCoV-2019') 
			                and tg_type='pcr'");
		$batch_tbl = 'ci_batches_pcr';

		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		$header = array();
		while($row = fgetcsv($stream)) {
			if ( empty( $header ) ) {
				foreach ( $row as $pos => $k ) {
					$header[ strtolower( trim( $k ) ) ] = $pos;
				}
				if ( $this->debug ) {
					$this->log .= "header:\n" . json_encode( $header ) . "\n";
				}
				continue;
			}

			if ( $this->debug ) {
				$this->log .= "row:\n" . json_encode( $row ) . "\n";
			}


			$mrn        = $row[ $header['test_id'] ];
			$name         = $row[ $header['name'] ];
			$dob          = $row[ $header['dob'] ];
			$doc          = $row[ $header['collection date'] ];
			$phone        = $row[ $header['phone'] ];
			$gender       = $row[ $header['gender'] ];
			$source       = $row[ $header['source'] ];
			$npi          = $row[ $header['npi number'] ];
			$doctor       = $row[ $header['auth. physician'] ];
			//$organization = $row[ $header['organization'] ];

			$dob    = date( 'Y-m-d', strtotime( $dob ) );
			$doc    = date( 'Y-m-d', strtotime( $doc ) );

			$gender = stripos( $gender, 'u' ) !==false ? 'U' : $gender;
			$gender = stripos( $gender, 'f' ) !==false ? 'F' : 'M';

			list( $pa_first, $pa_last ) = explode( ' ', $name );
			list( $doc_first, $doc_last ) = explode( ' ', $doctor );

			$mid = $wpdb->get_var("select mo_mid from ci_sync_api_orders 
									where mo_test_ID='{$mrn}' 
									and mo_file_id = '{$dbfile->m_ID}' ");
			if($mid>0)
			{
				$bat = $wpdb->get_row( "select mid from ci_batches_pcr where mid = {$mid} " );
			}else{
				$bat = false;
			}

			if(!$bat)
			{
				$assen = tr_get_new_assen();
				if(empty($assen))
				{
					continue;
				}
			}else{
				$assen = $bat->assen;
			}

			$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;
			if ( $patientid == 0 ) {

				$patientid = $wpdb->get_var( "select pa_ID from ci_patients where pa_first='{$pa_first}'
				                and pa_last='{$pa_last}' and pa_dob='{$dob}' and pa_mrn = '{$mrn}' " );
				$patient = array(
					'pa_first'  => $pa_first,
					'pa_last'   => $pa_last,
					'pa_dob'    => $dob,
					'pa_gender' => $gender,
					'pa_phone'  => $phone,
					'pa_mrn'    => $mrn
				);
				if ( $patientid == 0 ) {

					$wpdb->insert( 'ci_patients', $patient );
					$patientid = $wpdb->insert_id;
				}else{
					$wpdb->update('ci_patients',$patient,array('pa_ID'=>$patientid));
				}
			}


			$doc_id = $wpdb->get_var( "select doc_ID from ci_doc where doc_npi = '{$npi}'   
										and doc_first='{$doc_first}' and doc_last='{$doc_last}' " );


			$clinic_name = $wpdb->get_var( "select cli_clinic from ci_clinic where cID = '{$this->clinic_id}' " );

			if($doc_id>0)
			{
				$wpdb->insert('ci_clinic_doc',array('doc_ID'=>$doc_id,'cli_ID'=>$this->clinic_id));
			}


			$tests_requested = 'GROUP_'.$group_covid_id;
			$source = 'Nasopharyngeal Swab';

			$patient_name = $pa_last . ', ' . $pa_first;
			$data         = array(
				'assen'                => $assen,
				'patient_id'           => $patientid,
				'patient_name'         => $patient_name,
				'lab_ID'               => $this->lab_id,
				'doc_ID'               => intval( $doc_id ),
				'cli_ID'               => intval( $this->clinic_id ),
				'doc'                  => $doc,
				'dob'                  => $dob,
				'requesting_physician' => $doc_last . ', ' . $doc_first,
				'requesting_practice'  => $clinic_name,
				'type_of_specimen'     => $source,
				'panel_tests_requested' => $tests_requested,
				'insurance'             => 'No Insurance - Cash',
				'paid'                  => $this->api_id,
			);

			if ( $bat ) {
				$mid = $bat->mid;
				//$wpdb->update( $batch_tbl, $data, array( 'mid' => $bat->mid ) );
			} else {

				$data['batch_created'] = date_i18n('Y-m-d H:i:s');

				$wpdb->insert( $batch_tbl, $data );
				$mid = $wpdb->insert_id;
			}
			$wpdb->insert('ci_sync_api_orders',array(
				'mo_file_id'=>$dbfile->m_ID,
				'mo_mid' =>$mid
			));
		}
	}


	public function sync_reports()
	{
		global $wpdb;
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';

		$csv_dir = WP_CONTENT_DIR.'/meenta/';
		if(!file_exists($csv_dir))
		{
			mkdir($csv_dir);
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

		$sql = "select re.id,
					ba.mid,
					ba.batch_id,
					ba.assen,
					ba.patient_name,
					pa.pa_mrn,
					re.approve_date,
					re.created_date,
					re.instrument,
					mo.mo_ID,
					mf.m_ID,
					mf.m_file
				FROM {$batch_tbl} ba
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid and mo.mo_status = 0
				JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id and mf.m_type='meenta'
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				where re.estatus = 'C'
				";
		$rs = $wpdb->get_results($sql);

		$csv_files = array();

		foreach($rs as $r)
		{
			preg_match("/\_([0-9]+)\_/",$r->m_file,$matches);
			$numberid = $matches[1];
			$fname = trim($this->root_folder,'/');
			$filename = $r->batch_id.'_'.$numberid.'_'.$fname.'_ResultsManifest.csv';
			$csv_file = $csv_dir.$filename;


			if(!isset($csv_files[$csv_file]))
			{
				$csv_files[$csv_file] = fopen($csv_file,'a+');
				if(!file_exists($csv_file) || filesize($csv_file)==0)
				{
					fputcsv($csv_files[$csv_file],array('test_ID','OBX-3','OBX-19','firstName','lastName'));
				}
			}
			list($pa_last,$pa_first) = explode(', ',$r->patient_name);


			$arr = trpdd_cal_ncovid19(array(),$r->id,$r->instrument,$r->created_date);
			$csvdata = array(
				$r->pa_mrn,
				$pos_neg[$arr['pos-neg']],
				date('Y-m-d',strtotime($r->approve_date)),
				$pa_first,
				$pa_last,
			);
			fputcsv($csv_files[$csv_file],$csvdata);

			$pdf_file = trpdd_export_results_pdf($r->id,'',true,'pcr');
			$remote_file = '/results/processed/'.basename($pdf_file);


			try{
				$this->uploadFile($pdf_file,$remote_file);
				if($this->debug)
				{
					$this->log.= "uploaded: {$remote_file} \n";
				}
				//done
				$wpdb->update('ci_sync_api_orders',array('mo_status'=>1),array('mo_ID'=>$r->mo_ID));
			}catch (Exception $e)
			{
				var_dump($e);
			}
		}

		foreach($csv_files as $csv_file => $f)
		{
			fclose($f);
			$local_file = $csv_file;
			$remote_file = '/results/processed/'.basename($csv_file);
			try{
				$this->uploadFile($local_file,$remote_file);
				if($this->debug)
				{
					$this->log.= "uploaded: {$remote_file} \n";
				}
			}catch (Exception $e)
			{
				var_dump($e);
			}
		}

	}

}