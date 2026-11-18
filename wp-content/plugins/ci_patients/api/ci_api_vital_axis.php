<?php
class Ci_Api_VitalAxis{
	var $baseurl = 'https://vitalservice.vitalaxis.net/api/v1/covid/orders';
	var $accessToken;
	var $headers = array();
	var $is_test = false;
	var $config;
	public $log = '';

	var $api_id = -13;

	var $panel_groups = array();

	public function __construct($config) {
		global $wpdb;

		$this->config = $config;
		$this->accessToken = base64_encode($config['user_name'].':'.$config['pass']);

	}
	public function sendtoapi($reqs ,$options,$btype)
	{
		global $wpdb;

		if(count($reqs)==0)
		{
			return 0;
		}
		$races = array(
			'1002-5' => 'American Indian or Alaska Native',
			'2028-9' => 'Asian',
			'2054-5' => 'Black / African American',
			'2076-8' => 'Native Hawaiian or Other  Pacific Islander',
			'2106-3' => 'White',
			'2131-1'=>'Other'
		);
		$Ethnicitis = array(
			'H' => 'Hispanic or Latino',
			'N' => 'Not Hispanic or Latino',
			'U' => 'Unknown'
		);

		$orders = array();
		$assenlist = array();

		foreach($reqs as $data)
		{
			$order_code = $options['slug'];

			if(empty($order_code))
			{
				echo 'empty order code';
				continue;
			}

			$assenlist[$data['assen']]= array(
				'mid' => $data['mid'],
				'mo_ID' => $data['mo_ID'],
			);

			$data['pa_race'] = $races[$data['pa_race']];
			$data['pa_ethnicity'] = $Ethnicitis[$data['pa_ethnicity']];

			if(empty($data['pa_race']))
			{
				$data['pa_race'] = 'American Indian or Alaska Native';
			}

			if(empty($data['pa_ethnicity']))
			{
				$data['pa_ethnicity'] = 'Hispanic';
			}

			if($data['pa_dob']=='0000-00-00'){
				$data['pa_dob'] = '';
			}
			if($data['ins_dob']=='0000-00-00'){
				$data['ins_dob'] = '';
			}

			if($data['ins_relation']=='Self' || empty($data['ins_relation']))
			{
				$data['ins_gender'] = $data['pa_gender'];
				$data['ins_dob'] = $data['pa_dob'];
				$RelationshipCode = 18;
			}else{
				$data['ins_gender'] = '';
				$RelationshipCode = 1;
			}

			if($btype=='blood'){
				$data['type_of_specimen'] = 'Blood';
			}
			else if(empty($data['type_of_specimen']))
			{
				$data['type_of_specimen'] = 'Nasopharyngeal Swab';
			}

			$races = array(
				'1002-5' => 'American Indian or Alaska  Native',
				'2028-9' => 'Asian',
				'2054-5' => 'Black / African American',
				'2076-8' => 'Native Hawaiian or Other  Pacific Islander',
				'2106-3' => 'White',
				'2131-1' => 'Other'
			);
			$Ethnicitis = array(
                'H' => 'Hispanic or Latino',
                'N' => 'Not Hispanic or Latino',
                'U' => 'Unknown'
			);
			$data['pa_race']        = isset($races[$data['pa_race']])? $races[$data['pa_race']] : '';
			$data['pa_ethnicity']   = isset($Ethnicitis[$data['pa_ethnicity']])? $Ethnicitis[$data['pa_ethnicity']]: '';


			$orders[] = array(
				'Patient' => array(
					"MiddleInitial" => $data['pa_middle'],
					"LastName" => $data['pa_last'],
					"FirstName" => $data['pa_first'],
					"DateOfBirth" => $data['pa_dob'],
					"Gender" => $data['pa_gender'],
					"PatientID" => $data['pa_ID'],
					"Address1" => $data['pa_address'],
					"Address2" => "",
					"City" => $data['pa_city'],
					"County" => $data['pa_county'],
					"State" => $data['pa_state'],
					"Zip" => $data['pa_zip'],
					"HomePhone" => $data['pa_phone'],
					"WorkPhone" => "",
					"Email" => $data['pa_email'],
					"SSN" => $data['pa_ssn'],
					"Race" => $data['pa_race'],
					"Ethnicity" => $data['pa_ethnicity'],
					"GuarantorRelationship" => "",
					"GuarantorLastName" => "",
					"GuarantorFirstName" => "",
					"GuarantorMiddleInitial" => "",
					"GuarantorAddress1" => "",
					"GuarantorAddress2" => "",
					"GuarantorCity" => "",
					"GuarantorState" => "",
					"GuarantorZip" => "",
					"GuarantorHomePhone" => "",
					"GuarantorWorkPhone" => "",
					'AdditionalInfo' => array(
						array(
							"FirstTest" => "Yes",
							"EmployedinHealthcare" => "No",
							"SymptomaticasdefinedbyCDC" => "No",
							"IfYesDateofSymptomOnset" => "",
							"Hospitalized" => "No",
							"ICU" => "No",
							"Residentinacongregatecaresetting" => "No",
							"Pregnant" => "No",
							"PatientId" => $data['pa_passport'],
							"Vaccinated" => "Yes",
						)
					)
				),
				'SubscriberDetails' => array(
					array(
						"SubscriberType" => "Primary",
						"ResponsibilityCode" => "P",
						"RelationshipCode" => $RelationshipCode,
						"LastName" => $data['ins_last'],
						"FirstName" => $data['ins_first'],
						"MiddleInitial" => "",
						"Gender" => $data['ins_gender'],
						"DateOfBirth" => $data['ins_dob'],
						"PayerName" => $data['ins_company'],
						"Address1" => $data['ins_address'],
						"Address2" => "",
						"City" => $data['ins_city'],
						"State" => $data['ins_state'],
						"Zip" => $data['ins_zip'],
						"SubscriberPolicyNumber" => $data['ins_authorization_id'],
						"PPolicyNumber" => "",
						"GroupOrPlanNumber" => $data['ins_group_id'],
						"GroupOrPlanName" => ""
					),
					array(
						"SubscriberType" => "Second",
						"ResponsibilityCode" => "S",
						"RelationshipCode" => $data['ins2_relation'],
						"LastName" => $data['ins2_last'],
						"FirstName" => $data['ins2_first'],
						"MiddleInitial" => "",
						"Gender" => $data['ins2_gender'],
						"DateOfBirth" => $data['ins2_dob'],
						"PayerName" => $data['ins2_company'],
						"Address1" => $data['ins2_address'],
						"Address2" => "",
						"City" => $data['ins2_city'],
						"State" => $data['ins2_state'],
						"Zip" => $data['ins2_zip'],
						"SubscriberPolicyNumber" => $data['ins2_authorization_id'],
						"PPolicyNumber" => "",
						"GroupOrPlanNumber" => $data['ins2_group_id'],
					)
				),
				'OrderingProvider' => array(
					'Provider' => array(
						"Title" => "Dr",
						"LastName" => $data['doc_last'],
						"FirstName" => $data['doc_first'],
						"MiddleInitial" => "",
						"Address1" => $data['cli_street'],
						"Address2" => "",
						"City" => $data['cli_city'],
						"State" => $data['cli_state'],
						"Zip" => $data['cli_zip'],
						"Phone" => $data['cli_om-phone'],
						"NPI" => $data['doc_npi'],
						"UserID" => ""
					),
					'Location' => array(
						"Name" => $data['cli_clinic'],
						"Address1" => $data['cli_street'],
						"Address2" => "",
						"City" => $data['cli_city'],
						"State" => $data['cli_state'],
						"Zip" => $data['cli_zip'],
						"Phone" => $data['cli_om-phone'],
						"NPI" => $data['doc_npi'],
						"GUID" => $this->config['guid']
					)
				),
				'OrderDetails' => array(
					'Order' => array(
						"OrderNumber" => $data['assen'],
						"CollectionDate" => $data['doc'],
						"BillTo" => "",
						"ICDCodes" => "",
						"SpecialInstructions" => "",
						"SpecimenType" => $data['type_of_specimen'],
						'TestDetails' => array(
							array(
								'OrderCode' => $order_code
							)
						),
						'AdditionalInfo' => array(

						)
					)
				)
			);
		}

		$json = array(
			'Title' => 'COVID19 Order',
			'Default' => (object)array(),
			'MessageVersion' => '1.0',
			'MessageType' => 'Order',
			"ApplicationID" => $this->config['app_id'],
			"DestinationID" => $this->config['dest_id'],
			'Orders' => $orders
		);

		//echo '<br><br>';
		//var_dump($json);return;

		$rs = $this->apiCall('POST','',array('json'=>$json));

		file_put_contents(WP_CONTENT_DIR.'/log/vital_axis/'.date_i18n('Y-m-d His').'.json',json_encode($json));

		if(isset($_GET['debug'])){
			echo '<textarea style="width:100%;height:100px">'.json_encode($json).'</textarea>';
			echo '<textarea style="width:100%;height:100px">'.json_encode($rs).'</textarea>';
		}

		$count_success = 0;
		if(is_array($rs) && isset($rs['Response']))
		{
			foreach($rs['Response'] as $rp)
			{
				$assen = $rp['OrderNumber'];
				$status = strtolower($rp['Status']);
				$arr = $assenlist[$assen];
				if($status=='success'){


					if($arr)
					{
						$count_success++;

						//$batch_tbl = tr_pdd_get_table_by_btype($btype);
						//$wpdb->update($batch_tbl,array('batch_status'=>'archived'),array('mid'=>$arr['mid']));

						$this->log.="sent: ".$arr['mid'].' => '.$assen."\n";

						$update = array(
							'mo_file_id'    => $this->api_id,
							'mo_mid'        => $arr['mid'],
							'mo_btype'      => $btype,
							'mo_peg_ID'      => substr($order_code,0,30),
							'mo_status'     => 1,
							'mo_test_ID'    => substr($rs['TransactionID'],0,30),
							'mo_control_ID'    => substr($rs['MessageID'],0,30),
							'mo_set_ID'     => date_i18n('Y-m-d H:i:s')
						);

						if($arr['mo_ID']>0)
						{
							$r = $wpdb->update('ci_sync_api_orders',$update,array('mo_ID'=>$arr['mo_ID']));
						}else {
							$r = $wpdb->insert( 'ci_sync_api_orders', $update );
						}
					}
				}else{
					$this->log.="fail: ".$arr['mid'].' => '.$assen."\n";
				}
			}
		}
		return $count_success;
	}


	public function sync_reqs($limit = 1)
	{
		global $wpdb;
		if($limit>100)
		{
			$limit = 100;
		}

		$limitdate = date('Y-m-d H:i:s', strtotime(date_i18n('Y-m-d H:i:s')) - 25*86400);
		$count=0;
		foreach($this->config['products'] as $arr)
		{
			if(!is_array($arr['labs']))
			{
				$arr['labs'] = array();
			}

			list($btype,$panelid) = explode('|',$arr['panelid']);

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$patient_types = implode("','",$arr['forms']);
			if(empty($patient_types))
			{
				continue;
			}

			$sql = "select ba.*, 
							pa.*,
							cli.*,
							doc.*,
							ins.*,
							mo.mo_ID,
							'{$btype}' as btype
						From {$batch_tbl} ba
						left join {$report_tbl} re ON re.patient_id = ba.assen
						Join ci_patients pa ON pa.pa_ID = ba.patient_id
						JOIN ci_clinic cli ON cli.cID = ba.cli_ID
						JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
						left join ci_insurances ins ON ins.ins_ID = ba.insurance1
						left JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
								and mo.mo_btype ='{$btype}'
								and mo.mo_file_id = '{$this->api_id}'
						where ba.patient_portal_type in ('{$patient_types}') 
						and ( mo.mo_ID is null or mo.mo_status = 0)
						and FIND_IN_SET( 'GROUP_{$panelid}', ba.panel_tests_requested)
						and ba.doc >= '{$limitdate}'
						and (re.id is null or re.estatus!='C')
						
						";
			if(count($arr['labs'])>0)
			{
				$sql.= " and ba.lab_id in (". implode(',',$arr['labs']).") ";
			}
			$sql.= " limit {$limit} ";

			$orders = $wpdb->get_results($sql, ARRAY_A);

			if(count($orders)>0)
			{
				echo 'count: '.count($orders).'<br>';

				$count+= $this->sendtoapi($orders,$arr,$btype);
			}else if(isset($_REQUEST['debug'])){
				echo '<br>';
				echo 'empty: ';
				var_dump($patient_types);
				var_dump($btype);
				echo '<br>';
				var_dump($sql);
				var_dump($wpdb->last_error);
				echo '<br>';
				echo '<br>';
			}

			if(!empty($wpdb->last_error))
			{
				var_dump($wpdb->last_error);
			}
		}


		return $count;

	}

	function curl_custom_postfields(&$ch, array $json = array(), array $files = array()) {

		// invalid characters for "name" and "filename"
		static $disallow = array("\0",
						"\"",
						"\r",
						"\n");

		$body[] = implode("\r\n", array(
			"Content-Disposition: form-data; name=\"json\"",
			"Content-Type: application/json",
			"Content-Transfer-Encoding: 8bit",
			"",
			json_encode($json),
		));

		// build file parameters
		foreach ($files as $k => $v) {
			switch (true) {
				case false === $v = realpath(filter_var($v)):
				case !is_file($v):
				case !is_readable($v):
					continue; // or return false, throw new InvalidArgumentException
			}
			$data = file_get_contents($v);
			$v = call_user_func("end", explode(DIRECTORY_SEPARATOR, $v));
			$k = str_replace($disallow, "_", $k);
			$v = str_replace($disallow, "_", $v);
			$body[] = implode("\r\n", array(
				"Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$v}\"",
				"Content-Type: application/octet-stream",
				"Content-Transfer-Encoding: binary",
				"",
				$data,
			));
		}

		// generate safe boundary
		do {
			$boundary = "--" . md5(mt_rand() . microtime());
		} while (preg_grep("/{$boundary}/", $body));

		// add boundary for each parameters
		array_walk($body, function (&$part) use ($boundary) {
			$part = "--{$boundary}\r\n{$part}";
		});

		// add final boundary
		$body[] = "--{$boundary}--";
		$body[] = "";

		$this->headers = array_merge($this->headers,array(
			"Expect: 100-continue",
			"Content-Type: multipart/form-data; boundary={$boundary}", // change Content-Type
		));


		// set options
		return @curl_setopt_array($ch, array(
			CURLOPT_POST       => true,
			CURLOPT_POSTFIELDS => implode("\r\n", $body),
		));
	}

	protected function getContentTypeForParams($params) {
		$type = '';
		if (array_key_exists('json', $params)) {
			$type=  'application/json';
			if(isset($params['files'])){
				$type = 'multipart/form-data';
			}
		}
		return $type;
	}

	protected function getBodyForParams($params) {
		if (array_key_exists('json', $params)) {
			$json = $this->prepareBody('application/json', $params['json']);
			if(isset($params['files'])){
				$temp = tempnam("/tmp", "json");
				file_put_contents($temp,$json);
				$json = array(
					'json' => '@'.$temp.';type=application/json'
				);
				foreach($params['files'] as $i =>$file)
				{
					$json[$i]='@'.$file;
				}

			}

			return $json;
		}
		return '';
	}

	protected function prepareBody($contentType,$body) {
		if ($contentType == 'application/json') {
			if (is_array($body)) {
				$body = json_encode($body);
			}
		} elseif ($contentType =='application/x-www-form-urlencoded') {
			if (is_array($body)) {
				$body = http_build_query($body);
			}
		}
		return $body;
	}

	protected function apiCall($verb='', $url, $params=array()) {
		$url = str_replace(' ','%20',$url);

		$ch = curl_init($this->baseurl.$url);

		$this->headers = array('Authorization: Basic ' . $this->accessToken);

		if(isset($params['files'])){
			$this->curl_custom_postfields($ch, $params['json'], $params['files']);
		}
		else if (strtoupper($verb)=='POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getBodyForParams($params));
			$this->headers[] = 'Content-Type: ' . $this->getContentTypeForParams($params);
		}else{
			$this->headers[] = 'Content-Type: ' . $this->getContentTypeForParams($params);
		}


		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers );


		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responseBody = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if(isset($_GET['debug2'])){
			var_dump($this->headers);
			echo '<br>';
			var_dump($this->getBodyForParams($params));
			echo '<br>';
			var_dump($url);
			echo '<br>';
			var_dump($responseBody);
			echo '<br><br>';
		}

		$responseData = json_decode($responseBody, true);
		return $responseData;
	}



}