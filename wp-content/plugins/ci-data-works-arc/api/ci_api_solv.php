<?php

class CI_Api_Solv{

	protected $lab_id = 0;
	protected $clinic_id = 0;
	protected $clinics = array();
	protected $doc_id = 0;
	public $debug = false;
	public $log='';
	public $api_id = -5;

	public function __construct($lab_id,$clinic_id=0,$doc_id=0) {
		global $wpdb;

		$this->lab_id = $lab_id;
		$this->clinic_id = $clinic_id;
		$this->doc_id = $doc_id;
		$this->clinics = array();
		$rs = $wpdb->get_results("select cID, cli_clinic,cli_solv_hash 
						from ci_clinic where cli_solv_hash!='' and cli_solv_hash is not null");
		foreach($rs as $r)
		{
			$key = trim($r->cli_solv_hash);
			if(empty($key))continue;
			$this->clinics[$key] = $r;
		}

	}

	public function sync_reqs($folder_path)
	{
		global $wpdb;

		if($this->debug )
		{
			$this->log.= " list file:\n";
		}
		$archive_path = dirname($folder_path).'/archive';

		if(!file_exists($archive_path))
		{
			wp_mkdir_p($archive_path);
		}

		$files = scandir($folder_path);
		$count_files = 0;

		foreach($files as $raw_filename)
		{
			$filename = $raw_filename;

			$file_path = $folder_path.'/'.$filename;
			if(stripos($filename,'.csv')>0)
			{
				//check file exist in archive

				$archive_file_path = $archive_path.'/'.$filename;
				$last_archive_file = '';
				$num = 1;

				while(file_exists($archive_file_path))
				{
					$last_archive_file = $archive_file_path;

					$filename = $num.'_'.$raw_filename;

					$archive_file_path = $archive_path.'/'.$filename;

					$num++;
				}

				$filesize = filesize($file_path);
				$filetime = filemtime($file_path);

				if(file_exists($last_archive_file) && $filesize == filesize($last_archive_file))
				{
					if(file_get_contents($file_path) == file_get_contents($last_archive_file))
					{
						$filename = basename($last_archive_file);
						$archive_file_path = $last_archive_file;
					}
				}


				if($filesize > 10)
				{
					$arr = array(
						'm_file' => $filename,
						'm_time' => $filetime,
						'm_type' => 'solv'
					);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$filename}' and m_type='solv'");
					if($dbfile)
					{
						if($filetime <= $dbfile->m_time)
						{
							//continue;
						}
					}else{
						$wpdb->insert('ci_sync_api_files',$arr);
						$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$filename}'  and m_type='solv'");
					}
					$this->import_req_file($dbfile,$file_path);
					$count_files++;

					if(!empty($archive_file_path))
					{
						rename($file_path, $archive_file_path);

					}

				}



			}
		}

		if($this->debug )
		{
			$this->log.= "count: ".$count_files."\n";
		}
		if($this->debug && $count_files== 0)
		{
			$this->log.= " No file for sync\n";
		}
	}

	public function import_req_file($dbfile,$file_path)
	{
		global $wpdb;

		if(filesize($file_path)<10)
		{
			return false;
		}

		$this->log.= "file: ".$file_path."\n";

		$filename = basename($file_path);

		$ars = explode('_',str_replace('.csv','',$filename));
		$location_code = $ars[count($ars)-1];

		$default_clinic_name = '';
		if($this->clinic_id>0)
		{
			$default_clinic_name = $wpdb->get_var( "select cli_clinic from ci_clinic where cID = '{$this->clinic_id}' " );
		}

		$default_doctor_name = '';
		if($this->doc_id>0)
		{
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->doc_id}");
			$default_doctor_name = $doctor->doc_last.', '.$doctor->doc_first;
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
		$covid_group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_is_covid=1 and tg_type='pcr' ");

		$antigen_group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_short_name = 'Antigen' ");
		if($antigen_group_id==0)
		{
			$antigen_group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_name like '%antigen%' ");
		}

		$data = trpdd_get_data($file_path,null,array(),true);

		foreach($data['data'] as $row)
		{
			$pa_first = trpdd_get_string_exist($row,array('patient first name','first name'));
			$pa_last = trpdd_get_string_exist($row,array('patient last name','last name'));
			$gender = trpdd_get_string_exist($row,array('birth sex','gender','sex','patient gender','gender identity'));
			$dob = trpdd_get_string_exist($row,array('date of birth','dob','date of birth_2','dob_2'));
			$phone = trpdd_get_string_exist($row,array('mobile','phone','phone_2'));
			$email = trpdd_get_string_exist($row,array('email','e-mail','email_2'));
			$address = trpdd_get_string_exist($row,array('address street','address'));
			$city = trpdd_get_string_exist($row,array('address city','city'));
			$state = trpdd_get_string_exist($row,array('address state','state'));
			$zip_code = trpdd_get_string_exist($row,array('address zip','zip'));
			$ssn = trpdd_get_string_exist($row,array('ssn'));
			$mrn = trpdd_get_string_exist($row,array('mrn'));
			$status = trpdd_get_string_exist($row,array('status'));

			if(stripos($status,'cancel')!==false)
			{
				continue;
			}


			if(!empty($dob))
			{
				$dob    = date( 'Y-m-d', strtotime( $dob ) );
			}

			if(!empty($gender))
			{
				$gender = stripos( $gender, 'u' ) !==false ? 'U' : $gender;
				$gender = stripos( $gender, 'f' ) !==false ? 'F' : 'M';
			}else{
				$gender = '';
			}

			$patientid = 0;
			$insurance_id = 0;

			$insurance = array();
			$insurance['ins_last'] = $pa_last;
			$insurance['ins_first'] = $pa_first;
			$insurance['ins_company'] = trpdd_get_string_exist($row,array('booking insurance carrier','insurance'));
			$insurance['ins_member_id'] = trpdd_get_string_exist($row,array('booking insurance member id','insurance member id'));
			$insurance['ins_relation'] = trpdd_get_string_exist($row, array('patient\'s relationship to insured'));
			if(empty($insurance['ins_relation'])){
				$insurance['ins_relation'] = 'Self';
			}

			if(empty($insurance['ins_company']))
			{
				$insurance['ins_company'] = 'No Insurance - Cash';
			}


			$req_code = trpdd_get_string_exist($row,array('booking id hash'));

			$doc = trpdd_get_string_exist($row,array('date','doc'));
			$appointment_time = trpdd_get_string_exist($row,array('appointment time online','appointment time (online)'));
			$received_date = trpdd_get_string_exist($row,array('date','doc'));
			$source = 'Nasal Swab';
			if(!empty($appointment_time))
			{
				$doc.=' '.$appointment_time;
			}
			$doc    = date( 'Y-m-d H:i:s', strtotime( $doc ) );



			$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$req_code}'
							      and mo_file_id = '{$dbfile->m_ID}' ");

			$mid = $orderdb && $orderdb->mo_mid> 0? $orderdb->mo_mid : 0;

			$bat = false;
			if($mid>0)
			{
				$bat = $wpdb->get_row( "select * from ci_batches_pcr where mid = {$mid} " );
			}else if(!empty($req_code)){
				$bat = $wpdb->get_row( "select * from ci_batches_pcr where oldreq = '{$req_code}' " );
			}


			if($bat)
			{
				$assen = $bat->assen;
			}else{
				$assen  = tr_get_new_assen();
			}

			$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : $patientid;
			if ( $patientid == 0 ) {

				$patientid = $wpdb->get_var( "select pa_ID from ci_patients 
								where pa_first='{$pa_first}'
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
						'pa_mrn' => $mrn
					);

					//fix phone
					if(isset($patient['pa_phone'])){
						$patient['pa_phone'] = str_replace(array(' ','(',')','-','.'),'',$patient['pa_phone']);
					}

					$wpdb->insert( 'ci_patients', $patient );
					$patientid = $wpdb->insert_id;
				}
			}


			$doc_id = $this->doc_id;
			$doctor_name = $default_doctor_name;

			$cli_id = $this->clinic_id;
			$clinic_name = $default_clinic_name;

			//check clinic by $location_code
			$clinicn = trpdd_get_string_exist($row, array('clinic name'));
			if(!empty($clinicn) && !isset($this->clinics[$location_code]) ){
				$location_code = $clinicn;
			}

			if(!empty($location_code) && isset($this->clinics[$location_code])){
				$clinic = $this->clinics[$location_code];
				$cli_id = $clinic->cID;
				$clinic_name = $clinic->cli_clinic;

				if($this->clinic_id!=$cli_id)
				{
					//find doctor
					$doctor = $wpdb->get_row("select doc.* from ci_doc doc
									join ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
									where cd.cli_ID = {$cli_id} limit 1");
					if($doctor)
					{
						$doc_id = $doctor->doc_ID;
						$doctor_name = $doctor->doc_last.', '.$doctor->doc_first;
					}
				}
			}



			$panel_tests_requested = 'GROUP_'.$covid_group_id;

			$reason = trpdd_get_string_exist($row,array('reason for visit','reason for visit_2'));
			if(stripos($reason,'antigen')!==false && $antigen_group_id>0)
			{
				$panel_tests_requested = 'GROUP_'.$antigen_group_id;
			}


			$insurance_id = $bat && $bat->insurance1>0? $bat->insurance1 : $insurance_id;

			if($insurance_id==0)
			{
				$wpdb->insert('ci_insurances',$insurance);
				$insurance_id = $wpdb->insert_id;
			}


			$dia_code = 'Z20.828';

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
				'requesting_physician' => $doctor_name,
				'requesting_practice'  => $clinic_name,
				'type_of_specimen'     => $source,
				'dagnostic_codes1'      => $dia_code,
				'insurance'             => $insurance['ins_company'],
				'insurance1'            => intval($insurance_id),
				'panel_tests_requested' => $panel_tests_requested,
				'oldreq'            => $req_code,
				'notes'             => $reason,
				'paid'              => $this->api_id,
			);


			if ( $bat ) {
				$mid = $bat->mid;
				$wpdb->update( $batch_tbl, $data, array( 'mid' => $bat->mid ) );

				$this->log.= "Updated req {$assen} => id: {$mid} \n";
			} else {
				$data['batch_created'] = date_i18n('Y-m-d H:i:s');
				$wpdb->insert( $batch_tbl, $data );
				$mid = $wpdb->insert_id;

				$this->log.= "Added req {$assen} => id: {$mid} \n";
			}

			if($mid==0){
				$this->log.= "Error \n";
				return false;
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