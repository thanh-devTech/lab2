<?php
if(!class_exists('CI_Api_Provista'))
{
	class CI_Api_Provista {

		var $url        = 'https://tmitintegrations.digitalrequisitions.com/';
		var $options    = array();
		var $token      = '';
		var $token_optk = 'ci_provista_token_st';


		public $api_id      = -23;

		public $panels      = array();

		public $log         = '';
		public $log_s_file  = null;

		public $has_more    = false;

		public function __construct($options) {
			$this->options = $options;
		}

		public function setReSourceLog($s){
			$this->log_s_file = $s;
		}

		public function log($msg)
		{
			$msg=date_i18n('Y-m-d H:i:s').'=> '.$msg. "\n";
			if($this->log_s_file)
			{
				return fwrite($this->log_s_file,$msg);
			}else{
				$this->log.= $msg;
			}
		}


		public function GetToken()
		{
			$url = $this->url.'OAuthentication/GetToken';
			$data = array(
				'grant_type'    => 'password',
				'username'      => $this->options['username'],
				'password'      => $this->options['password'],
			);
			$rs = wp_remote_post($url,array('body'=>$data));


			if(!is_wp_error($rs))
			{
				$json = json_decode($rs['body'],true);

				if($json && isset($json['access_token']))
				{
					$json['expires_at'] = time() + $json['expires_in'];
					update_option($this->token_optk, $json);
					return $json;
				}
			}
			return false;
		}

		public function parse_panels($str)
		{
			global $wpdb;
			if(empty($this->panels))
			{
				$this->panels = array();
				$rs = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups");
				foreach($rs as $r)
				{
					$this->panels[$r->tg_ID] = $r->tg_name;
				}
			}
			$panels = array();
			$tests = explode(',',$str);
			foreach($tests as $test)
			{
				if(stripos($test,'group_')!==false)
				{
					$group_id = str_ireplace('group_','',$test);
					if(isset($this->panels[$group_id])){
						$panels[] = array(
							'PanelCode' => $this->panels[$group_id]
						);
					}
				}
			}
			return $panels;
		}

		public function get_gender($short)
		{
			$genders = array(
				'M' => 'Male',
				'F' => 'Female',
				'O' => 'Other'
			);
			return $genders[$short];
		}

		public function InsertOrder($data)
		{
			$url = $this->url.'api/Provista/InsertOrder';

			if(!is_array($data['dagnostic_codes1']))
			{
				$data['dagnostic_codes1'] = explode(',',$data['dagnostic_codes1']);
			}

			if(empty($data['spec_received']) || (date('Y',strtotime($data['spec_received'])) < date('Y',strtotime($data['batch_created']))))
			{
				$data['spec_received'] = $data['doc'];
			}

			if(empty($data['ins_dob']) || $data['ins_dob']=='0000-00-00'){
				$data['ins_dob'] = $data['pa_dob'];
			}
			if($data['insurance']=='No Insurance - Cash' || $data['insurance']=='Client bill' || empty($data['insurance'])){
				$data['insurance'] = 'Self Pay/Non-Insured';
			}else
			{
				$data['insurance'] = 'Insurance';
			}
			if(empty($data['ins_phone']))
			{
				$data['ins_phone'] = $data['pa_phone'];
			}

			if(empty($data['pa_email']))
			{
				$data['pa_email'] = 'patient_'.$data['pa_ID'].'@'.$this->options['domain'];
			}

			if($data['type_of_specimen']=='Nasopharyngeal Swab'){
				$data['type_of_specimen'] = 'Nasopharyngeal';
			}else if($data['type_of_specimen']=='Nasal Swab')
			{
				$data['type_of_specimen'] = 'Saliva';
			}

			$Ethnicitis = array(
				'F' => 'African American',
				'H' => 'Hispanic',
				'N' => 'Not Hispanic or Latino',
				'A' => 'Asian',
				'U' => 'Other'
			);

			$ethnicity = isset($Ethnicitis[$data['pa_ethnicity']])? $Ethnicitis[$data['pa_ethnicity']]:'Other';


			//$panels = $this->parse_panels($data['panel_tests_requested']);
			$panels = array(array('panelCode'=>'Coronavirus 2019-nCoV (COVID-19)'));

			$json = array(
				"orderType" => "N",//N or U
				"ICD10Codes" => $data['dagnostic_codes1'],
				"facilityInformation" => array(
					"FacilityIdentifier" => $this->options['facility']
				),
				"orderingProviderInformation" => array(
					"providerNPI" => $data['doc_npi']
				),
				"externalOrderId" => $data['mid'],
				"externalPatientId" => $data['pa_ID'],
				"CollectorInformation" => array(
					"collectorNPI" => ''
				),
				"PanelMapping" => $panels,
				"SpecimenInformation" => array(
					"CollectionDateTime" => $data['doc'],
					"CollectionTime" => date('H:i:s', strtotime($data['doc'])),
					"DateReceived" => $data['spec_received'],
					"TypeofPCRSpecimen" => $data['type_of_specimen'],
					"AccessionNo" => $data['assen']
				),
				"patientInformation" => array(
					"patientFirstName" => $data['pa_first'],
					"patientLastName"  => $data['pa_last'],
					"dob" => $data['pa_dob'],
					"gender" => $this->get_gender($data['pa_gender']),
					"IsPregnant" => "no",
					"ethnicity" => $ethnicity,
					"address1" => $data['pa_address'],
					"address2" => "",
					"email" => $data['pa_email'].'',
					"city" => $data['pa_city'],
					"state" => $data['pa_state'],
					"zipCode" => $data['pa_zip'],
					"phoneNo" => $data['pa_phone'],
					"OptIn" => "YES",
					"County" => "USA"
				),
				"InsuranceType" => array(
					array(
						"PaymentMethod" => $data['insurance'],
						"InsuranceInformation" => array(
							"RelationshipToInsured" => $data['ins_relation'],
							"PrimaryInsuranceProvider" => $data['ins_company'],
							"SubscriberName" => $data['ins_last'].' '.$data['ins_first'],
							"PolicyHolderDOB" => $data['ins_dob'],
							"PrimaryPolicyID" => $data['ins_member_id'],
							"GroupID" => $data['ins_group_id'],
							"InsurancePhoneNo" => str_replace('-','',$data['ins_phone'])
						) )

				) );


			$request_arg = array(
				'headers'     => array(
					'Authorization' => 'Bearer '.$this->token,
					'Content-Type' => 'application/x-www-form-urlencoded'
				),
				'body'        => json_encode($json),
				'method'      => 'POST',
				'data_format' => 'body',
				'timeout'     => 30,
			);
			//$url = 'https://api.webhue.net/raw.php';
			$rs = wp_remote_post($url,$request_arg);


			if(!is_wp_error($rs))
			{
				$json_res = json_decode($rs['body'],true);
				if(isset($_GET['act']) && $_GET['act']=='test_send_order'){
					var_dump($json);
					echo '<br><br>';
					var_dump($json_res);
					echo '<br><br>';
				}

				return $json_res;

			}else{
				var_dump($rs);
			}
			return false;
		}

		public function test()
		{
			$rs = $this->GetToken();
			var_dump($rs);
		}

		public function authenticate()
		{
			$json = get_option($this->token_optk,array());

			if(is_array($json) && $json['expires_at'] > time() + 500)
			{
				$this->token = $json['access_token'];

				return $this->token;
			}

			return $this->GetToken();
		}

		public function sync_reqs($limit = 1,$limit_run_time=0)
		{
			global $wpdb;

			if(empty($this->token) || !$this->authenticate())
			{
				return false;
			}

			$where = "";

			$date_timenow = date_i18n('Y-m-d H:i:s');
			$start_time = time();


			if ( ! empty( $this->options['start_date'] ) ) {
				$where .= " and ba.batch_created >= '{$this->options['start_date']}' ";
			}else{
				$limitdate = date('Y-m-d', strtotime($date_timenow) -86400);
				$where .= " and ba.batch_created >= '{$limitdate}' ";
			}

			$where_lc = array();
			if($this->options['ref_lab_ID']>0)
			{
				$where_lc[] = " ba.ref_lab_ID = {$this->options['ref_lab_ID']} ";
			}

			if(is_array($this->options['cli_IDs']) && count($this->options['cli_IDs'])>0)
			{
				$cli_ids_str = implode(',',$this->options['cli_IDs']);
				$where_lc[] = " ba.cli_ID in ({$cli_ids_str})";
			}
			if(count($where_lc)>0)
			{
				$where.= " and (".implode(' or ',$where_lc).")";
			}
			$where.= " and ba.cli_ID>0 and ba.doc_ID >0 and ba.patient_id > 0 ";
			$where.= " and ba.doc <= '{$date_timenow}' ";
			//$where.= " and ba.spec_received > '{$limit_received}' ";


			$btype = 'pcr';
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$covid_group_id = $wpdb->get_var("select tg_ID from ci_test_groups 
							where tg_type='{$btype}'
							and tg_is_covid = 1");
			$where.= " and FIND_IN_SET('GROUP_{$covid_group_id}', panel_tests_requested ) ";

			$sql = "select ba.*,
				cli.*,
				doc.*,
				pa.*,
				ins.*,
				api.mo_ID,
				'{$btype}' as btype
                    
            from {$batch_tbl} ba
            JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            JOIn ci_patients pa ON pa.pa_ID = ba.patient_id
            JOIn ci_doc doc ON doc.doc_ID = ba.doc_ID
            JOIn ci_clinic cli ON cli.cID = ba.cli_ID
            JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
            left join ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = {$this->api_id}
                                        and api.mo_btype = '{$btype}'
             where (api.mo_ID is null or api.mo_status = 0)
             {$where}
             order by mid asc
			 limit {$limit}";

			if(isset($_GET['debug'])){
				var_dump($sql);
			}

			$rows = $wpdb->get_results($sql, ARRAY_A);

			if(count($rows)>= $limit-1)
			{
				$this->has_more = true;
			}
			$sentcount = 0;
			foreach($rows as $row)
			{
				if($limit_run_time>0 && time() > $start_time + $limit_run_time)
				{
					$this->has_more = true;
					break;
				}

				$rs_arr = $this->InsertOrder($row);

				if(is_array($rs_arr))
				{
					$logarr = array(
						'mo_file_id'    => $this->api_id,
						'mo_mid'        => $row['mid'],
						'mo_btype'     => $row['btype'],
						'mo_control_ID' => date_i18n('Y-m-d H:i:s')
					);

					if($rs_arr['Status']=='success'){
						$this->log($row['assen'].' #'.$row['mid']." => sent");
						$logarr['mo_status'] = 1;
						$logarr['mo_test_ID'] = $rs_arr['OrderId'];
					}else{

						if(stripos($rs_arr['Message'],'already exists')!==false)
						{
							$logarr['mo_status'] = 1;
							$this->log( $row['assen'].' #'.$row['mid']." => exist ");
						}else{
							$this->log( $row['assen'].' #'.$row['mid']." => {$rs_arr['Message']} ");
						}

					}

					if($logarr['mo_status'])
					{
						$sentcount++;
					}

					$logarr['other_info'] = json_encode($rs_arr);

					if($row['mo_ID']>0)
					{
						$wpdb->update('ci_sync_api_orders',$logarr,array('mo_ID'=>$row['mo_ID']));

					}else{
						$wpdb->insert('ci_sync_api_orders',$logarr);
					}
				}


			}

			return $sentcount;

		}
	}
}