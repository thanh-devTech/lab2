<?php
require_once  'restful_api.php';

add_filter( 'https_ssl_verify', '__return_false' );


class Resful_Medhiwa_api extends restful_api {

	public $api_id = - 19;
	public $key_data = array();

	function __construct( $pre_endpoint ) {
		//$this->hide_info = true;
		if(!empty($pre_endpoint))
		{
			parent::__construct( $pre_endpoint );
		}
	}

	protected function formatdate( $date ) {
		if ( strpos( $date, '/' ) > 0 ) {
			$date = date( 'Y-m-d', strtotime( $date ) );
		}

		return $date;
	}

	protected function check_key() {
		global $wpdb;

		if ( $this->endpoint == 'request_info' && $this->request_api_key == 'viewinfo' ) {
			return true;
		}

		$this->key_data = $wpdb->get_row( "select * from ci_wsdl_keys 
							where wsdl_key = '{$this->request_api_key}'
							and wsdl_type='mha' " );
		if ( ! $this->key_data ) {
			$this->response( 405, array( 'status' => 'error', 'message' => 'wrong key' ) );
			exit;
		}

		return true;
	}

	protected function log($type,$key_id,$assen,$status,$xml)
	{
		global $wpdb;
		$at = date_i18n('Y-m-d H:i:s');

		$count = $wpdb->get_var("select wl_count from ci_wsdl_log where wl_ID={$key_id} 
						and wl_type='{$type}' 
						and wl_key='{$assen}' ");
		if($count > 0)
		{
			$rs = $wpdb->update('ci_wsdl_log',
				array(
					'wl_count'=>$count+1,
					'wl_data' => $xml,
					'wl_at' => $at,
					'wl_status'=>$status,
				),
				array('wl_ID'=>$key_id,'wl_type'=>$type,'wl_key'=>$assen));
		}else{
			$rs = $wpdb->insert('ci_wsdl_log',array(
					'wl_ID'=>$key_id,
					'wl_type'=>$type,
					'wl_key'=>$assen,
					'wl_count'=>1,
					'wl_status'=>$status,
					'wl_data' => $xml,
					'wl_at'=>$at,
					//'wl_ip' => $this->get_ip()
				)
			);
		}
		return $rs;
	}


	/**
	 * PUT order
	 *
	 * - Request Json:
	 * {
	"test_number": "$id",
	"specimen_code": "Accession Number",
	"first_name": "first_name",
	"last_name": "last_name",
	"date_of_birth": "dob",
	"address_street": "address",
	"address_city": "city",
	"address_state": "state",
	"address_postal_code": "zip",
	"phone_mobile": "0123456789",
	"aoe_current_symptoms": "symptoms",
	"aoe_past_symptoms": "past_symptoms",
	"test_type": "PCR",
	"ethnicity": "$ethnicity",
	"gender": "$gender",
	"created_datetime": "date(m/d/Y H:i)",
	"race": "$race",
	"organization_id": "org_id",
	"location_id": "location_id",
	"Doc_Last": "Diagnostics",
	"Doc_First": "Gentech",
	"Doc_NPI": "1518406636",
	"ICD10": "Z20.828",
	"ins_name": "ins_name",
	"ins_code": "ins_policy_num",
	"ins_group": "ins_group_num",
	"ins_subcriber": "ins_co",
	"ins_relation": "rel_to_insured",
	"ins_primary_first": "insPrimFirst",
	"ins_primary_last": "insPrimLast",
	"ins_primary_dob": "insDob",
	"result": ""
	}
	 *
	 * - return: json
	 */
	public function request_put_order() {
		global $wpdb;

		$return = array( 'status' => 'ok');

		//$return = $this->params;

		$lab_id = $this->key_data->wsdl_lab_ID;
		$btype = strtolower($this->params['test_type']);
		$setid = trim($this->params['test_number']);
		$oldreq = trim($this->params['specimen_code']);
		$doc = trim($this->params['created_datetime']);

		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$exist = $wpdb->get_var("select mid from {$batch_tbl} where oldreq = '{$oldreq}' ");
		if(empty($oldreq))
		{
			$return['status'] = 'error';
			$return['message'] = 'empty specimen_code';
		}
		else if($exist)
		{
			$return['status'] = 'exist';
		}else {

			$assen = tr_get_new_assen();
			$pa_first = trim($this->params['first_name']);
			$pa_last = trim($this->params['last_name']);
			$dob = trim($this->params['date_of_birth']);
			$gender = trim($this->params['gender']);
			$phone = trim($this->params['phone_mobile']);
			$address = trim($this->params['address_street']);
			$city = trim($this->params['address_city']);
			$state = trim($this->params['address_state']);
			$zip_code = trim($this->params['address_postal_code']);
			$ethnicity = trim($this->params['ethnicity']);
			$race = trim($this->params['race']);
			$ssn = '';
			$mrn = '';

			$dia_code = trim($this->params['ICD10']);


			//fix phone
			if(!empty($phone)){
				$phone = str_replace(array(' ','(',')','-','.'),'',$phone);
			}

			//doctor
			$doctor = array();
			$doctor['doc_last'] = trim($this->params['Doc_Last']);
			$doctor['doc_first'] = trim($this->params['Doc_First']);
			$doctor['doc_npi'] = trim($this->params['Doc_NPI']);

			$doctor_name = $doctor['doc_first'].' '.$doctor['doc_last'];

			$doctor_id = $wpdb->get_var($wpdb->prepare("select doc_ID from ci_doc 
									where doc_first = %s
									and doc_last = %s
									and doc_npi = %s", $doctor['doc_first'],$doctor['doc_last'],$doctor['doc_npi']));

			if($doctor_id==0)
			{
				$wpdb->insert('ci_doc',$doctor);
				$doctor_id = $wpdb->insert_id;
				if(!empty($wpdb->last_error) || $doctor_id==0){
					$return['derror'] = $wpdb->last_error;
				}
			}

			//clinic
			$clinic_name = '';
			$cli_id = $wpdb->get_var("select cli_ID as cID from ci_wsdl_key_clinics
                                    where wsdl_ID = '{$this->key_data->wsdl_ID}' limit 1");
			if($cli_id>0)
			{
				$clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = {$cli_id}");

				$wpdb->insert('ci_clinic_doc',array('doc_ID'=>$doctor_id,'cli_ID'=>$cli_id));
			}

			//patient
			$sql_patient = "select pa_ID from ci_patients 
								where pa_first='{$pa_first}'
				                and pa_last='{$pa_last}' and pa_dob='{$dob}' ";
			if(!empty($phone))
			{
				$sql_patient.= " and pa_phone = '{$phone}' ";
			}

			$patient = array(
				'pa_first'  => $pa_first,
				'pa_last'   => $pa_last,
				'pa_dob'    => $dob,
				'pa_gender' => strtoupper(substr($gender,0,1)),
				'pa_phone'  => $phone,
				'pa_address' => $address,
				'pa_city' => $city,
				'pa_state' => substr($state,0,2),
				'pa_zip' => substr($zip_code,0,12),
				//'pa_email' => $email,
				//'pa_ssn' => $ssn,
				//'pa_mrn' => $mrn,
				'pa_ethnicity' => substr($ethnicity,0,10),
				'pa_race' => substr($race,0,10),
			);
			$patientid = $wpdb->get_var( $sql_patient );

			if ( $patientid == 0 ) {
				$wpdb->insert( 'ci_patients', $patient );
				$patientid = $wpdb->insert_id;
				if(!empty($wpdb->last_error) || $patientid==0){
					$return['status'] = 'error';
					//$return['perror'] = $wpdb->last_error;
					$return['perror'] = 'Incorrect data for patient';
					//$return['lqperror'] = $wpdb->last_query;
					$return['pa'] = $patient;
					$this->response( 200, $return );
				}
			}else{
				$wpdb->update('ci_patients',$patient,array('pa_ID'=>$patientid));
			}


			//insurance

			$ins_name = trim($this->params['ins_name']);
			$ins_code = trim($this->params['ins_code']);
			$ins_group = trim($this->params['ins_group']);
			$ins_subcriber = trim($this->params['ins_subcriber']);
			$ins_relation = trim($this->params['ins_relation']);

			$ins_primary_first = trim($this->params['ins_primary_first']);
			$ins_primary_last= trim($this->params['ins_primary_last']);
			$ins_primary_dob = trim($this->params['ins_primary_dob']);

			$insurance = array(
				'ins_first' => $ins_primary_first,
				'ins_last' => $ins_primary_last,
				'ins_company' => $ins_name,
				'ins_dob' => $ins_primary_dob,
				'ins_group_id' => $ins_group,
				'ins_relation' => $ins_relation,
				'ins_member_id' => $ins_code,
				'ins_authorization_id' => $ins_subcriber,
			);

			$sql = $wpdb->prepare("select ins_ID from ci_insurances 
					where ins_first=%s 
							and ins_last = %s 
							and ins_company=%s",
				$ins['ins_first'],$ins['ins_last'],$ins['ins_company']);

			$insurance_id = $wpdb->get_var($sql);
			if($insurance_id==0 && (!empty($ins['ins_first']) || !empty($ins['ins_company'])))
			{
				$wpdb->insert('ci_insurances',$insurance);
				$insurance_id = $wpdb->insert_id;

				if($insurance_id==0){
					$return['status'] = 'error';
					//$return['perror'] = $wpdb->last_error;
					$return['perror'] = 'Incorrect data for Insurance';
					//$return['lqperror'] = $wpdb->last_query;
					$return['ins'] = $insurance;
					$this->response( 200, $return );
				}
			}


			$covid_group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_is_covid=1 and tg_type='pcr' ");
			$panel_tests_requested = 'GROUP_'.$covid_group_id;
			$source = 'Nasal Swab';

			$patient_name = $pa_last . ', ' . $pa_first;
			$data         = array(
				'assen'                 => $assen,
				'patient_id'            => $patientid,
				'patient_name'          => $patient_name,
				'lab_ID'                => $lab_id,
				'doc_ID'                => intval( $doctor_id ),
				'cli_ID'                => intval( $cli_id ),
				'doc'                   => $doc,
				'spec_received'         => $received_date,
				'dob'                   => $dob,
				'requesting_physician'  => $doctor_name,
				'requesting_practice'   => $clinic_name,
				'type_of_specimen'      => $source,
				'dagnostic_codes1'      => $dia_code,
				'insurance'             => $insurance['ins_company'],
				'insurance1'            => intval( $insurance_id ),
				'panel_tests_requested' => $panel_tests_requested,
				'oldreq'                => $oldreq,
				//'notes'                 => $reason,
				'paid'                  => $this->api_id,
			);


			$data['batch_created'] = date_i18n( 'Y-m-d H:i:s' );
			$wpdb->insert( $batch_tbl, $data );
			$mid = $wpdb->insert_id;

			$this->log .= "Added req {$assen} {$oldreq} => id: {$mid} \n";


			$this->log( 'put_order', $this->key_data->wsdl_ID, $assen, $return['status'], json_encode( $this->params ) );

			if($mid>0)
			{
				$return['id'] = $mid;

				$update = array(
					'mo_file_id'    => $this->api_id,
					'mo_mid'        => $mid,
					'mo_set_ID'        => $setid,
					'mo_test_ID'        => $oldreq,
					'mo_control_ID' => date_i18n('Y-m-d H:i:s'),
				);
				$wpdb->insert( 'ci_sync_api_orders', $update );

			}else{
				$return['status'] = 'error';
				$return['msg'] = $wpdb->last_error;
			}

		}
		$this->response( 200, $return );
	}



}