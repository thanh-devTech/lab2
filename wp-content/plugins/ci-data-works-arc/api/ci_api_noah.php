<?php

class CI_Api_Noah{

	protected $lab_id = 0;
	protected $clinic_id = 0;
	protected $doc_id = 0;
	public $debug = false;
	public $log='';
	var $xml;
	public $api_id = -6;

	public function __construct($lab_id,$clinic_id=0,$doc_id=0) {
		$this->lab_id = $lab_id;
		$this->clinic_id = $clinic_id;
		$this->doc_id = $doc_id;

	}



	public function objToXML($obj,$root='')
	{
		$this->xml = new XmlWriter();
		$this->xml->openMemory();
		$this->xml->setIndent(0);

		$this->getObject2XML($this->xml, $obj,$root);
		$this->xml->endElement();
		$xml = $this->xml->outputMemory(true);
		$xml = preg_replace("/[\r\n]/", '', $xml);
		return $xml;
	}

	private function getObject2XML(XMLWriter $xml, $data, $root='')
	{
		if(!empty($root))
		{
			$xml->startElement($root);
		}else if($root=='xml')
		{
			$xml->startDocument('1.0');
		}
		foreach ($data as $key => $value)
		{
			if (is_object($value) && $value!=null) {
				$xml->startElement($key);
				$this->getObject2XML($xml, $value);
				$xml->endElement();
				continue;
			}
			else if (is_array($value)) {
				$this->getArray2XML($xml, $key, $value);
			}
			else {
				$xml->writeElement($key, $value);
			}
		}
	}

	private function getArray2XML(XMLWriter $xml, $keyParent, $data)
	{
		foreach ($data as $key => $value)
		{
			if (is_string($value)) {
				$xml->writeElement($keyParent, $value);
				continue;
			}
			if (is_numeric($key)) {
				$xml->startElement($keyParent);
			}

			if (is_object($value)) {
				$this->getObject2XML($xml, $value);
			}
			else if (is_array($value)) {
				$this->getArray2XML($xml, $key, $value);
				continue;
			}

			if (is_numeric($key)) {
				$xml->endElement();
			}
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

		foreach($files as $file)
		{
			$file_path = $folder_path.'/'.$file;
			if(stripos($file,'.xml')>0 || stripos($file,'.ord')>0)
			{
				$filetime = filemtime($file_path);
				$arr = array(
					'm_file' => $file,
					'm_time' => $filetime,
					'm_type' => 'noah'
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file}' and m_type='noah'");
				if($dbfile)
				{
					if($filetime <= $dbfile->m_time)
					{
						continue;
					}
				}else{
					$wpdb->insert('ci_sync_api_files',$arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file}'  and m_type='noah'");
				}

				$this->import_req_file($dbfile,$file_path);
				$count_files++;

				if(!empty($archive_path))
				{
					$rs = rename($file_path, $archive_path.'/'.basename($file_path));

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

		if($this->debug)
		{
			$this->log.= "file: ".$file_path."\n";
		}

		$data = simplexml_load_file($file_path);

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



		foreach($data->PATIENT as $k => $paobj)
		{
			$pa_first = (string)$paobj->FIRST_NAME;
			$pa_last = (string)$paobj->LAST_NAME;
			$gender = (string) $paobj->GENDER;
			$dob = (string) $paobj->BIRTH_DATE;
			$phone = (string) $paobj->HOME_PHONE;
			$cellphone = (string) $paobj->CELL_PHONE;
			if(!empty($cellphone))
			{
				$phone = $cellphone;
			}
			$email = (string) $paobj->EMAIL_ADDRESS;
			$email2 = (string) $paobj->EMAIL_DEFAULT;
			if(empty($email))
			{
				$email = $email2;
			}
			$address = (string) $paobj->ADDRESS1;
			$city = (string) $paobj->CITY;
			$state = (string) $paobj->STATE;
			$zip_code = (string) $paobj->ZIP_CODE;
			$ssn = (string) $paobj->SSN;
			$mrn = (string) $paobj->PATIENT_CODE;


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

				break;
			}

			if(empty($insurance['ins_last']) || empty($insurance['ins_first']))
			{
				$insurance['ins_last'] = $pa_last;
				$insurance['ins_first'] = $pa_first;
			}
			if(empty($insurance['ins_relation']))
			{
				$insurance['ins_relation'] = 'Self';
			}
			if(empty($insurance['ins_company']))
			{
				$insurance['ins_company'] = 'No Insurance - Cash';
			}


			foreach($paobj->REQUISITIONS->REQUISITION as $REQUISITION)
			{

				$req_code = (string)$REQUISITION->REQUISITION_CODE;

				$doc = (string)$REQUISITION->COLLECT_DATE;
				$received_date = (string) $REQUISITION->RECEIVED_DATE;
				$source = 'Nasopharyngeal Swab';
				$doc    = date( 'Y-m-d', strtotime( $doc ) );

				$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$req_code}'
							      and mo_file_id = '{$dbfile->m_ID}' ");
				$mid = $orderdb && $orderdb->mo_mid> 0? $orderdb->mo_mid : 0;
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

				$doctor_name = $doc_last . ', ' . $doc_first;
				if($doc_id==0 && $this->doc_id>0)
				{
					$doc_id = $this->doc_id;
					$doctor_name = $default_doctor_name;

				}

				$clinic_name = $REQUISITION->LOCATION_EID->LOCATION->ORGANIZATION_NAME;

				$cli_id = $wpdb->get_var( "select cID from ci_clinic where cli_clinic = '{$clinic_name}' " );
				if($cli_id==0 && $this->clinic_id>0)
				{
					$cli_id = $this->clinic_id;
					$clinic_name = $default_clinic_name;
				}

				$dia_code = array();
				foreach($REQUISITION->REQ_DIAGNOSES->REQ_DIAGNOSIS as $diag)
				{
					$dia_code[] = (string)$diag->DIAGNOSIS_CODE;
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


				$dia_code = implode(',',$dia_code);

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
					'paid'              => $this->api_id,
				);


				if ( $bat ) {
					$mid = $bat->mid;
					$wpdb->update( $batch_tbl, $data, array( 'mid' => $bat->mid ) );

					if($this->debug)
					{
						$this->log.= "Updated req {$assen} => id: {$mid} \n";
					}
				} else {
					$data['batch_created'] = date_i18n('Y-m-d H:i:s');

					$wpdb->insert( $batch_tbl, $data );
					$mid = $wpdb->insert_id;

					if($this->debug)
					{
						$this->log.= "Added req {$assen} => id: {$mid} \n";
					}
				}

				if($mid==0)return false;

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
					pa.pa_ssn,
					pa.pa_mrn,
					
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
		//var_dump($rs);
		//var_dump($wpdb->last_error);

		$report_files = array();

		if($this->debug && count($rs)==0)
		{
			if(!empty($wpdb->last_error))
			{
				$this->log.= "error: ".$wpdb->last_error;
			}
			$this->log.= "Not found results!";
		}
		


		foreach($rs as $r)
		{
			$filename = $r->m_file;

			$arr = trpdd_cal_ncovid19(array(),$r->id,$r->instrument,$r->created_date);

			$result = array(
				'PATIENT' => (object)array(
						'PATIENT_CODE' => $r->pa_mrn,
						'LAST_NAME' => $r->pa_last,
						'FIRST_NAME' => $r->pa_first,
						'PATIENT_NAME' => $r->pa_last.', '.$r->pa_first,
						'ADDRESS1' => $r->pa_address,
						'CITY' => $r->pa_city,
						'STATE' => $r->pa_state,
						'ZIP_CODE' => $r->pa_zip,
						'BIRTH_DATE' => $r->pa_dob,
						'GENDER' => $r->pa_gender,
						'HOME_PHONE' => $r->pa_phone,
					),
				'REPORT' => (object)array(
					'REQUISITION_CODE' => $r->mo_test_ID,
					'REPORT_DATE' => $r->approve_date,
					'ACCESSION' => $r->assen,
					'TEST_NAME' => $covid_test['te_name'],
					'RESULT' => $pos_neg[$arr['pos-neg']]
				)
			);

			$xml = @$this->objToXML($result,'EXPORT_RESULT');

			$filepath = $results_path.'/'.$filename;
			$wf = file_put_contents($filepath,$xml);
			if($wf)
			{
				$wpdb->query("update ci_sync_api_orders set mo_status = 1 where mo_ID = {$r->mo_ID}");
			}
		}


	}
}