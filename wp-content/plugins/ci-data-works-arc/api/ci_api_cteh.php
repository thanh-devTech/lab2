<?php
class CI_Api_CTEH
{

	var $baseurl = 'https://apps.carboncm.com/api/v2/';
	var $accessToken;
	var $headers = array();
	var $is_test = false;
	public $log = '';

	var $api_id = -3;

	public function __construct($key) {
		$this->accessToken = $key;
	}

	function curl_custom_postfields(&$ch, array $json = array(), array $files = array()) {

		// invalid characters for "name" and "filename"
		static $disallow = array("\0", "\"", "\r", "\n");

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
					continue 2; // or return false, throw new InvalidArgumentException
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
		$this->headers = array('Authorization: Bearer ' . $this->accessToken);

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
			var_dump($url);
			var_dump($responseBody);
		}

		$responseData = json_decode($responseBody, true);
		return $responseData;
	}

	public function describe()
	{
		$rs = $this->apiCall('','87/describe.json');
		return $rs;
	}

	public function lab_manifests()
	{
		$rs = $this->apiCall('','87/Lab Manifest/select.json');
		return $rs;
	}

	public function manifest_items($filter='IsNull([Status])')
	{
		$rs = $this->apiCall('','87/Lab Manifest Item/select.json?filter='.$filter);
		return $rs;
	}

	public function update_item_status($id,$status='RECEIVED')
	{

		$json = array();
		if(is_array($id))
		{
			foreach($id as $rid)
			{
				$json[] = array(
					'Id' => $rid,
					'status'=> $status
				);
			}
		}else{
			$json[] = array(
				'Id' => $id,
				'status'=> $status
			);
		}
		$json = json_encode($json);
		$rs = $this->apiCall('POST','87/Lab Manifest Item/update.json',array(
			'json' => $json
		));
		return $rs;
	}

	public function upload_result($id,$result_date,$result,$assen,$comment,$file)
	{
		$url = '87/result_incoming/create.json';
		if($this->is_test)
		{
			$url = '87/result_incoming_test/create.json';
		}
		$json = array(
			'InternalID' => $id,
			'FinalDate' => date('Y-m-d',strtotime($result_date)),
			'Result' => $result,
			'Accession' => $assen,
			'Comment' => $comment,
			'pdf_base64' => base64_encode(file_get_contents($file))
		);
		var_dump($json);
		$json = json_encode($json);

		$rs = $this->apiCall('POST',$url,array(
			'json' => $json
		));
		return $rs;
	}

	public function update_result($id,$result_date,$result,$assen,$comment,$file)
	{
		$url = '87/result_incoming/update.json';
		if($this->is_test)
		{
			$url = '87/result_incoming_test/update.json';
		}
		$json = array(
			'InternalID' => $id,
			'FinalDate' => date('Y-m-d',strtotime($result_date)),
			'Result' => $result,
			'Accession' => $assen,
			'Comment' => $comment,
			'pdf_base64' => base64_encode(file_get_contents($file))
		);
		$json = json_encode($json);

		$rs = $this->apiCall('POST',$url,array(
			'json' => $json
		));
		return $rs;
	}

	public function swap_type($filter='')
	{
		$url = '87/Swab Type/select.json';
		if(!empty($filter))
		{
			$url.='?filter='.$filter;
		}
		$rs = $this->apiCall('',$url);

		$arr = array();
		foreach($rs as $r)
		{
			$arr[$r['Id']] = $r['Name'];
		}
		return $arr;
	}

	public function transport_medium($id=''){
		$url = '87/Transport Medium/select.json';
		if($id>0)
		{
			$url.='?filter=[id]='.$id;
		}
		$rs = $this->apiCall('',$url);

		$arr = array();
		foreach($rs as $r)
		{
			$arr[$r['Id']] = $r['Name'];
		}
		return $arr;
	}

	function import_reqs($only_insert = true,$status ='IsNull([Status])')
	{
		global $wpdb;

		$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where (tg_is_covid=1 or tg_name ='nCoV-2019') 
			                and tg_type='pcr'");

		$options = get_option('_tr_cteh_settings',array());

		$all_swaptypes = $this->swap_type();
		$all_transport_mediums = $this->transport_medium();

		$all_states = tr_get_states();
		$cli_ID = $options['cli_id'];
		$doc_ID = $options['doc_id'];
		$clinic_name = '';
		$doctor_name = '';
		$default_type_of_specimen = 'Nasal Swab';
		$tests_requested = 'GROUP_'.$group_covid_id;
		$dagnostic_code = 'Z20.828';
		if($cli_ID>0)
		{
			$clinic_name = $wpdb->get_var( "select cli_clinic from ci_clinic where cID = '{$cli_ID}' " );
		}
		if($doc_ID>0)
		{
			$doctor = $wpdb->get_row( "select doc_first,doc_last from ci_doc where doc_ID = '{$doc_ID}' " );
			$doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
		}


		$rows = $this->manifest_items($status);
		$id_arr = array();
		foreach($rows as $row)
		{
			$id = $row['Id'];
			$Event = $row['Event'];
			$status = $row['Status'];
			$SpecimenID = $row['SpecimenID'];
			$btype = strtolower($row['Test Type']);

			$swap_type = $row['Swab Type'];
			$type_of_specimen = $default_type_of_specimen;
			if(isset($all_swaptypes[$swap_type])){
				$type_of_specimen = $all_swaptypes[$swap_type].' Swab';
			}
			$transport_mediums = '';
			if(isset($all_transport_mediums[$row['Transport Medium']])){
				$transport_mediums = $all_transport_mediums[$row['Transport Medium']];
			}

			if(empty($btype))
			{
				$btype = 'pcr';
			}
			$doc = date('Y-m-d', strtotime($row['Date of Test'])).' '.date('H:i:s', strtotime($row['Time of Test']));
			$dob = date('Y-m-d', strtotime($row['DOB']));

			$gender = stripos($row['Gender raw'],'U')!==false? 'U':'M';
			$gender = stripos($row['Gender raw'],'f')!==false? 'F':$gender;


			$table_batch = tr_pdd_get_table_by_btype($btype);

			$rowdb = $wpdb->get_row("select * from ci_sync_api_orders 
								where mo_file_id = '{$this->api_id}'
								and mo_set_ID = '{$btype}'
								and mo_test_ID = {$id} ");
			$mid = 0;
			$bat = false;
			if($rowdb)
			{
				$mid = $rowdb->mo_mid;
			}
			if($mid>0)
			{
				if($only_insert)
				{
					continue;
				}
				else{
					$bat = $wpdb->get_row("select * from {$table_batch} where mid = {$mid}");
				}
			}
			$assen = $bat && !empty($bat->assen)? $bat->assen : tr_get_new_assen($btype,false,'',$options['lab_id']);

			//repair state
			$pa_state = '';
			$state = (!empty($row['State'])? $row['State'] : $row['State raw']).'';
			foreach($all_states as $statek => $statetxt)
			{
				if(stripos($statetxt,$state)!==false || $statek==$state){
					$pa_state = $statek;
					break;
				}
			}
			$patient = array(
				'pa_first' => $row['First Name'],
				'pa_last' => $row['Last Name'],
				'pa_dob'  => $dob,
				'pa_gender' => $gender,
				'pa_address' => (!empty($row['Address'])? $row['Address'] : $row['Address raw']).'',
				'pa_city' => (!empty($row['City'])? $row['City'] : $row['City raw']).'',
				'pa_state' => $pa_state,
				'pa_zip' => (!empty($row['Zipcode'])? $row['Zipcode'] : $row['Zipcode raw']).'',
				'pa_email' => $row['Email'].'',
				'pa_phone' => (!empty($row['Phone'])? $row['Phone'] : $row['Phone raw']).'',
				'pa_mrn' => $row['MRN'].'',

			);

			//fix phone
			if(isset($patient['pa_phone'])){
				$patient['pa_phone'] = str_replace(array(' ','(',')','-','.'),'',$patient['pa_phone']);
			}


			$patient_name = $patient['pa_last'].', '.$patient['pa_first'];

			$sql_patient = "select pa_ID from ci_patients where pa_first='{$patient['pa_first']}'
										and pa_last = '{$patient['pa_last']}' 
										and pa_dob = '{$patient['pa_dob']}' ";
			if(!empty($patient['pa_phone'])){
				$phone1 = $patient['pa_phone'];
				$phone2 = substr($phone1,0,3).'-'.substr($phone1,3,3).'-'.substr($phone1,6);
				$sql_patient.= " and (pa_phone='{$phone1}' or pa_phone ='{$phone2}')";
			}
			if(!empty($patient['pa_email'])){
				$sql_patient.= " and pa_email = '{$patient['pa_email']}' ";
			}
			$patient_id = $wpdb->get_var($sql_patient);

			if($patient_id==0)
			{
				$wpdb->insert('ci_patients',$patient);

				$patient_id = $wpdb->insert_id;
			}else{
				$wpdb->update('ci_patients',$patient,array('pa_ID'=>$patient_id));
			}


			$ins_id = $bat && $bat->insurance1 > 0? $bat->insurance1 : 0;
			$insurance = 'No Insurance - Cash';
			if($ins_id==0)
			{
				$ins_first = $patient['pa_first'];
				$ins_last = $patient['pa_last'];

				$ins_id = $wpdb->get_var("select ins_ID from ci_insurances 
								where ins_first='{$ins_first}' and ins_last = '{$ins_last}' 
								and ins_company='{$insurance}' and pa_ID = {$patient_id} ");
				$ins_arr = array(
					'ins_first' => $ins_first,
					'ins_last' => $ins_last,
					'ins_relation' => 'Self',
					'ins_company' => $insurance.'',
					'pa_ID' => $patient_id.'',
				);
				if($ins_id==0)
				{
					$wpdb->insert('ci_insurances',$ins_arr);
					$ins_id = $wpdb->insert_id;
				}
			}


			$data = array(
				'assen' => $assen,
				'patient_name' => $patient_name,
				'patient_id' => $patient_id,
				'oldreq' => $SpecimenID,
				'doc' => $doc,
				'dob' => $dob,
				'lab_id' => $options['lab_id']>0? $options['lab_id']:1,
				'cli_ID' => $cli_ID.'',
				'doc_ID' => $doc_ID.'',
				'requesting_physician' => $doctor_name.'',
				'requesting_practice'  => $clinic_name.'',
				'type_of_specimen'     => $type_of_specimen.'',
				'panel_tests_requested' => $tests_requested.'',
				'dagnostic_codes1' => $dagnostic_code.'',

				'insurance'             => $insurance.'',
				'insurance1'             => intval($ins_id),
				'paid'                  => $this->api_id,

			);
			if($bat)
			{
				unset($data['batch_created']);
				$rs = $wpdb->update($table_batch,$data,array('mid'=>$mid));
			}else{
				$data['batch_created'] = date_i18n('Y-m-d H:i:s');
				$wpdb->insert($table_batch,$data);
				$mid = $wpdb->insert_id;
			}


			if($mid>0)
			{
				$logarr = array(
					'mo_file_id' => $this->api_id,
					'mo_mid' => $mid,
					'mo_test_ID' => $id,
					'mo_set_ID' => $btype,
					'mo_control_ID' => $Event
				);
				if($rowdb)
				{
					$wpdb->update('ci_sync_api_orders',$logarr,array('mo_ID'=>$rowdb->mo_ID));

				}else{
					$wpdb->insert('ci_sync_api_orders',$logarr);
				}
				$id_arr[] = $id;

			}

		}

		//update cteh status
		if(count($id_arr)>0)
		{
			$rs = $this->update_item_status($id_arr,'RECEIVED');
			var_dump($rs);
		}
		return count($id_arr);

	}

	function sync_reports()
	{
		global $wpdb;
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';

		$pos_neg = array(
			'' => '',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'POS' => 'Positive',
			'INV' => 'Invalid',
			'NEG' => 'Negative',
		);

		$covid_test = trpdd_get_pcr_covid_test();
		if($covid_test['te_is_detected'])
		{
			$pos_neg['POS'] = 'Detected';
			$pos_neg['NEG'] = 'Not Detected';
		}

		$sql = "select re.*,
					ba.*,
					pa.*,
					mo.mo_ID,
					mo.mo_set_ID,
					mo.mo_test_ID,
					mo.mo_control_ID,
					mo.mo_patient_ID,
					mo.mo_peg_ID,
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
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
							and mo.mo_status = 0 and mo.mo_file_id = '{$this->api_id}'
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				where re.estatus = 'C'
				";
		$rs = $wpdb->get_results($sql, ARRAY_A);

		$mo_ids = array();

		foreach($rs as $report)
		{
			if(empty($report['created_date']))
			{
				$report['created_date'] = $report['created'] ;
			}

			$arr = trpdd_cal_ncovid19(array(), $report['id'],$report['instrument'],$report['created_date']);
			list($path,$file) = trpdd_get_path_result_pdf($report,'pcr');


			$file_path = WP_CONTENT_DIR.'/results/'.$path.'/'.$file;
			if(!file_exists($file_path) || filemtime($file_path) < $report['modified'])
			{
				trpdd_export_results_pdf($report['id'],'',false,'pcr');
			}

			if(!file_exists($file_path))
			{
				continue;
			}

			//upload result
			$rs = $this->upload_result(
					$report['mo_control_ID'],
					$report['approve_date'],
					$pos_neg[$arr['pos-neg']],
					$report['assen'],
					$report['notes_report'],
					$file_path
				);
			if($this->is_test)
			{
				var_dump($rs);
			}
			if($rs)
			{
				if(($rs['status']==201 || $rs[0]['status']==201 ))
				{
					$mo_ids[] = $report['mo_ID'];
				}else if($rs[0]['status']==400 && $rs[0]['errors'][0]['error']==409){
					$mo_ids[] = $report['mo_ID'];
				}

			}
		}

		if(count($mo_ids)>0)
		{
			$mo_id_str = implode(',',$mo_ids);
			$wpdb->query("update ci_sync_api_orders set mo_status = 1 where mo_ID in ({$mo_id_str})");
		}
		return count($mo_ids);
	}

}