<?php
include_once('ci_hl7.php');

class CI_Api_Hl7_Athena_WEB_API extends CI_Hl7{
	const  SANBOX_URL   = 'https://api.preview.platform.athenahealth.com';
	const  LIVE_URL     = 'https://api.platform.athenahealth.com';
	private $baseurl    = '';
	public $last_url    = '';
	private $token_file = '';
	private $accessToken= '';
	private $options    = array();
	private $headers    = array();

	public $debug       = false;
	public $log_orders  ='';
	public $log_results ='';
	public $api_id      = -4;

	public function __construct($options) {

		global $wpdb;

		$this->options = $options;

		if(isset($options['is_product']) && $options['is_product']){
			$this->baseurl = self::LIVE_URL;
			$this->token_file = WP_CONTENT_DIR.'/log/athena_api.log';
		}else{
			$this->is_Test = true;
			$this->baseurl = self::SANBOX_URL;
			//$this->baseurl = 'https://api.webhue.net/raw.php?';
			$this->token_file = WP_CONTENT_DIR.'/log/athena_api_sb.log';
		}

		if(file_exists($this->token_file))
		{
			$fmtime = filemtime($this->token_file);
			if($fmtime > time() - 3000)
			{
				$this->accessToken = file_get_contents($this->token_file);
			}
		}

		if(empty($this->accessToken))
		{
			$this->token();
		}

		if(isset($_GET['debugraw'])){
			$this->baseurl = 'https://api.webhue.net/raw.php?';
		}

		$this->options['covid_group_id'] = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where (tg_is_covid=1 or tg_name ='nCoV-2019') 
			                and tg_type='pcr'");


		$this->options['clinic_name'] = '';
		if(isset($this->options['cli_id']) && $this->options['cli_id']>0)
		{
			$this->options['clinic_name'] = $wpdb->get_var( "select cli_clinic from ci_clinic where cID = '{$this->options['cli_id']}' " );
		}


		$this->options['doctor_name'] = '';
		if(isset($this->options['doc_id']) && $this->options['doc_id']>0)
		{
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->options['doc_id']}");
			$this->options['doctor_name'] = $doctor->doc_last.', '.$doctor->doc_first;
		}

	}

	protected function getContentTypeForParams($params) {
		if(!empty($params))
		{
			$params = array();
		}
		$type = '';
		if (array_key_exists('json', $params)) {
			$type=  'application/json';
			if(isset($params['files'])){
				$type = 'multipart/form-data';
			}
		}
		else if(isset($params['content-type']))
		{
			$type = $params['content-type'];
			unset($params['content-type']);
		}
		return $type;
	}

	protected function getBodyForParams($params) {

		if(!is_array($params))
		{
			$params = array();
		}
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
		}else if(isset($params['content-type']))
		{
			$params = $this->prepareBody($params['content-type'],$params);
		}
		return $params;
	}

	protected function prepareBody($contentType,$body) {

		if(is_array($body))
		{
			unset($body['content-type']);
		}

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
		$verb = strtoupper($verb);

		$this->last_url = $this->baseurl.$url;
		$ch = curl_init($this->last_url );

		if(!empty($this->accessToken))
		{
			$this->headers = array('Authorization: Bearer ' . $this->accessToken);
		}else if(isset($this->options['clientid']) && isset($this->options['secret'])){
			curl_setopt($ch, CURLOPT_USERPWD, $this->options['clientid'].':'.$this->options['secret']);
		}


		if($verb=='PUT'){
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			//curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $this->getBodyForParams( $params ) );

			$content_type = $this->getContentTypeForParams( $params );
			if ( ! empty( $content_type ) ) {
				$this->headers[] = 'Content-Type: ' . $content_type;
			}
		}
		else if ($verb == 'POST') {

			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $this->getBodyForParams( $params ) );

			$content_type = $this->getContentTypeForParams( $params );
			if ( ! empty( $content_type ) ) {
				$this->headers[] = 'Content-Type: ' . $content_type;
			}
		}else if($verb=='DELETE')
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		}else{
			$this->headers[] = 'Content-Type: ' . $this->getContentTypeForParams($params);
		}
		$this->headers[] = 'accept: application/json';


		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers );

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responseBody = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);


		if(isset($_GET['debug3']) || isset($_GET['debugraw'])){
			var_dump($url);
			var_dump($responseBody);
		}


		$responseData = json_decode($responseBody, true);
		return $responseData;
	}


	public function token()
	{
		$this->accessToken = '';

		$rs = $this->apiCall('post','/oauth2/v1/token',
			'grant_type=client_credentials&scope=athena/service/Athenanet.MDP.*'
		);


		if(isset($rs['access_token']))
		{
			$this->accessToken = $rs['access_token'];
			file_put_contents($this->token_file,$this->accessToken);
		}

		return $rs;
	}

	public function get_subscription_events()
	{
		$rs = $this->apiCall('get','/v1/'.$this->options['practiceid'].'/orders/signedoff/subscription/events' );
		return $rs;
	}

	public function order_signedoff_subscription($departmentids = array(),$eventname='')
	{
		$content = 'departmentids='.implode(',',$departmentids);
		$content.= '&eventname='.$eventname;
		$rs = $this->apiCall('POST','/v1/'.$this->options['practiceid'].'/orders/signedoff/subscription',$content );
		if(is_array($rs) && isset($rs['success']) && $rs['success']){
			return true;
		}
		return false;
	}

	public function subscribe()
	{
		$rs = $this->apiCall('post','/v1/'.$this->options['practiceid'].'/orders/signedoff/subscription',
			''
		);
		return $rs;
	}

	public function delete_subscription()
	{
		$rs = $this->apiCall('delete','/v1/'.$this->options['practiceid'].'/orders/signedoff/subscription' );
		return $rs;
	}

	public function order_signedoff()
	{
		$rs = $this->apiCall('get','/v1/'.$this->options['practiceid'].'/orders/signedoff' );
		return $rs;
	}


	public function orderable($searchvalue)
	{
		$rs = $this->apiCall('get','/v1/'.$this->options['practiceid'].'/reference/order/lab?searchvalue='.$searchvalue );
		return $rs;
	}



	public function departments()
	{
		$rs = $this->apiCall('get','/v1/'.$this->options['practiceid'].'/departments' );
		return $rs;
	}


	public function patients()
	{
		$rs = $this->apiCall('get','/v1/'.$this->options['practiceid'].'/patients' );
		return $rs;
	}

	public function get_patient($patientid)
	{
		$queries = 'showfullssn=true&showinsurance=true';
		$rs = $this->apiCall('get','/v1/'.$this->options['practiceid'].'/patients/'.$patientid.'?'.$queries);
		if(is_array($rs))
		{
			$rs= $rs[0];
		}
		return $rs;
	}

	public function patients_documents_labresult($patientid,$departmentid,$encounterid)
	{
		$queries = 'departmentid='.$departmentid;
		$queries.= '&encounterid='.$encounterid;
		$rs = $this->apiCall('get','/v1/'.$this->options['practiceid'].'/patients/'.$patientid.'/documents/labresult?'.$queries);

		return $rs;
	}
	public function documents_labresult_dataentrycompleted($document_id)
	{
		$url = '/v1/'.$this->options['practiceid'].'/documents/labresult/'.$document_id .'/dataentrycompleted';

		$rs = $this->apiCall('PUT',$url,array('actionnote'=>'','content-type'=>'application/x-www-form-urlencoded'));

		$this->log_results.= "\ndocuments_labresult_dataentrycompleted: \n";
		$this->log_results.= "{$url} \n";
		$this->log_results.= 'response: '.json_encode($rs)."\n\n";
		return $rs;
	}

	public function labresult($patientid,
			$labresultid,
			$orderingprovider,
			$departmentid,
			$documentid,
			$documenttypeid,
			$analyteid,
			$doc,
			$received_date,
			$report_date,
			$accession,
			$pos_neg,
			$pdf_file = '',
			$auto_close = '',
			$debug_type=''
		)
	{
		$received_date = date('Y-m-d\TH:i:s',strtotime($received_date));
		$report_date = date('Y-m-d\TH:i:s',strtotime($report_date));
		$observationdate = date('Y-m-d',strtotime($doc));
		$observationtime = date('H:i',strtotime($doc));

		$priority = null;
		if($pos_neg=='Detected' || $pos_neg=='Positive'){
			$priority = 1;
			$abnormalflag = 'ABNORMAL';
			$pos_neg = 'Positive';
		}else{
			$abnormalflag = 'NORMAL';
			$pos_neg = 'Negative';
		}
		$analytename = !empty($this->options['analytename'])? $this->options['analytename']: 'covid-19';

		$analytes = array(
			array(
				'value' => $pos_neg,
				'abnormalflag' => $abnormalflag,
				'resultstatus' => 'Final',
				'analytename' => $analytename,
				'analyteid' => $analyteid,
				//"note" => "this is a note",
			)
		);
		$data = array(
			'content-type'=>'application/x-www-form-urlencoded',
			'accessionid' => $accession,
			'departmentid' => $departmentid,
			'tietoorderid' => $documentid,
			'reportstatus' => 'Final',
			'resultstatus' => 'Final',
			'observationdate' => $observationdate,
			'observationtime' => $observationtime,
			'specimenreceiveddatetime' => $received_date,
			'specimenreporteddatetime' => $report_date,
			'interpretation' => $pos_neg,
			'analytes' => json_encode($analytes)
		);

		if($priority==1)
		{
			$data['priority'] = $priority;
		}

		if(!empty($documenttypeid))
		{
			$data['documenttypeid'] = $documenttypeid;
		}
		if($orderingprovider>0)
		{
			$data['providerid'] = intval($orderingprovider);
		}


		if(!empty($pdf_file) && file_exists($pdf_file))
		{
			$data['attachmenttype'] = 'PDF';
			$data['attachmentcontents'] = base64_encode(file_get_contents($pdf_file));
		}

		$url = '/v1/'.$this->options['practiceid'].'/patients/'.$patientid.'/documents/labresult';

		$http_method = 'POST';

		if(is_bool($auto_close))
		{
			$data['autoclose'] = $auto_close;
		}
		else if(!empty($labresultid))
		{
			$url.='/'.$labresultid;
			$http_method = 'PUT';

			unset($data['departmentid']);
		}

		$rs = $this->apiCall($http_method,$url,$data );

		$this->log_results.= $http_method.' '.$this->last_url."\n";

		unset($data['content-type']);
		if(isset($data['attachmentcontents']))
		{
			$data['attachmentcontentslength'] = strlen($data['attachmentcontents']);
			$data['attachmentcontents'] = $pdf_file;
		}

		$this->log_results.= json_encode($data)."\n\n";
		$this->log_results.= 'response:'. json_encode($rs)."\n\n\n";

		return $rs;
	}

	public function get_result($patientid,$resultid)
	{
		$url = '/v1/'.$this->options['practiceid'].'/patients/'.$patientid.'/documents/labresult/'.$resultid;
		$rs = $this->apiCall('GET',$url );
		//$this->log_results.= 'GET '.$this->last_url."\n";
		//$this->log_results.= 'response:'. json_encode($rs)."\n\n\n";

		return $rs;
	}

	public function sync_reqs()
	{
		$rs = $this->order_signedoff();
		$count = 0;
		if($rs['orders']){
			foreach($rs['orders'] as $order)
			{
				$this->import_req($order);
				$count++;
			}
		}else{
			$this->log_orders.= json_encode($rs);
		}
		return $count;
	}


	public function import_req($order)
	{
		global $wpdb;
		$mid = 0;
		$priority_hours  = 0;
		$batch_tbl = 'ci_batches_pcr';



		if(stripos($order['status'],'closed')!==false){
			//$this->log_orders.= "order closed\n";
			return false;
		}

		if(!empty($this->options['order_description']) && stripos($order['description'],$this->options['order_description'])===false){

			$this->log_orders.= "order not match description:{$order['description']}\n";
			return false;
		}

		$this->log_orders.= json_encode($order)."\n\n";

		$order_id   = $order['documentid'];
		$encounterid = $order['encounterid'];
		$opid       = $order['patientid'];
		$departmentid = $order['departmentid'];

		$doctoruser     = trim($order['orderingprovider']);

		$doctor_id = $this->options['doc_id'];
		$doctor_name = $this->options['doctor_name'];


		$clinic_id = $this->options['cli_id'];
		$clinic_name = $this->options['clinic_name'];


		if($departmentid)
		{
			$clinic = $wpdb->get_row( "select cID, cli_clinic 
							from ci_clinic 
							where cli_athena_departmentid = '{$departmentid}' " );

			if(!$clinic)
			{
				$clinic = $wpdb->get_row( "select cID, cli_clinic 
							from ci_clinic 
							where cli_athena_departmentid2 = '{$departmentid}' " );
			}
			if($clinic)
			{
				$clinic_id = $clinic->cID;
				$clinic_name = $clinic->cli_clinic;
			}
		}

		if(!empty($doctoruser))
		{
			$doctor = $wpdb->get_row("select * from ci_doc 
								where doc_athena_username = '{$doctoruser}'
								OR FIND_IN_SET('{$doctoruser}',REPLACE(REPLACE(doc_athena_username,', ',','),' ,',',') )
								");


			if($doctor)
			{
				$doctor_id = $doctor->doc_ID;
				$doctor_name = $doctor->doc_last.', '.$doctor->doc_first;;
			}
		}




		$pa = $this->get_patient($opid);


		if(!$pa)
		{
			$this->log_orders.= "get patient error\n";
			return false;
		}else{
			$this->log_orders.= "patientdata:\n".  json_encode($pa)."\n\n";
		}

		$doc = date('Y-m-d H:i:s',strtotime($order['dateordered']));


		$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$order_id}'
							      and mo_file_id = '{$this->api_id}' 
							      and mo_btype = 'pcr' ");
		$mid = $orderdb && $orderdb->mo_mid> 0? $orderdb->mo_mid : 0;
		$bat = null;
		if($mid>0)
		{
			$bat = $wpdb->get_row( "select * from ci_batches_pcr where mid = {$mid} " );
		}

		if(!$bat)
		{
			$assen = tr_get_new_assen();

		}else{
			$assen = $bat->assen;
		}

		$this->log_orders.= "assen: {$assen} \n";

		$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;

		$pa_last = $pa['lastname'];
		$pa_first = $pa['firstname'];
		$dob = date('Y-m-d',strtotime($pa['dob']));

		$gender = stripos($pa['sex'],'u')!==false? 'U':'M';
		$gender = stripos($pa['sex'],'F')!==false? 'F':$gender;

		$address = $pa['address1'];
		$city = $pa['city'];
		$state = $pa['state'];
		$zip = $pa['zip'];
		$phone = !empty($pa['mobilephone'])? $pa['mobilephone']: $pa['contactmobilephone'];
		$email = $pa['email'];
		$ssn = $pa['ssn'];

		if ( $patientid == 0 ) {

			$patientid = $wpdb->get_var( "select pa_ID from ci_patients where pa_first='{$pa_first}'
				                and pa_last='{$pa_last}' and pa_dob='{$dob}' and pa_city = '{$city}' " );
			$patient = array(
				'pa_first'  => $pa_first,
				'pa_last'   => $pa_last,
				'pa_dob'    => $dob,
				'pa_gender' => $gender,
				'pa_phone'  => $phone,
				'pa_address'    => $address,
				'pa_city'    => $city,
				'pa_state'    => $state,
				'pa_zip'    => $zip,
				'pa_email'  => $email,
			);
			if(!empty($ssn))
			{
				$patient['pa_ssn'] = $ssn;
			}
			if(!empty($opid))
			{
				$patient['pa_mrn'] = $opid;
			}

			//fix phone
			if(isset($patient['pa_phone'])){
				$patient['pa_phone'] = str_replace(array(' ','(',')','-','.'),'',$patient['pa_phone']);
			}
			if ( $patientid == 0 ) {

				$wpdb->insert( 'ci_patients', $patient );
				$patientid = $wpdb->insert_id;
			}else{
				foreach($patient as $k => $vl)
				{
					if(empty($vl))
					{
						unset($patient[$k]);
					}
				}
				$wpdb->update('ci_patients',$patient,array('pa_ID'=>$patientid));
			}
		}


		$ins_first = $pa['insurances'][0]['insuredfirstname'];
		$ins_last = $pa['insurances'][0]['insuredlastname'];
		$insurance = $pa['insurances'][0]['insuranceplanname'];
		$ins_address = $pa['insurances'][0]['insurancepackageaddress1'];
		$ins_city = $pa['insurances'][0]['insurancepackagecity'];
		$ins_state = $pa['insurances'][0]['insurancepackagestate'];
		$ins_zip = $pa['insurances'][0]['insurancepackagezip'];
		$ins_phone = $pa['insurances'][0]['insurancephone'];
		$ins_dob = $pa['insurances'][0]['insureddob'];
		$ins_group_id = '';
		$ins_authorization_id = '';

		$ins_id = $bat && $bat->insurance1 > 0? $bat->insurance1 : 0;
		if($ins_id==0)
		{
			if(empty($insurance))
			{
				$ins_first = $pa_first;
				$ins_last = $pa_last;
				$insurance = 'No Insurance - Cash';
			}
			$ins_id = $wpdb->get_var("select ins_ID from ci_insurances 
								where ins_first='{$ins_first}' and ins_last = '{$ins_last}' 
								and ins_company='{$insurance}' and pa_ID = {$patientid} ");
			$ins_arr = array(
				'ins_first' => $ins_first,
				'ins_last' => $ins_last,
				'ins_relation' => 'Self',
				'ins_company' => $insurance.'',
				'ins_address' => $ins_address.'',
				'ins_city' => $ins_city.'',
				'ins_state' => $ins_state.'',
				'ins_zip' => $ins_zip.'',
				'ins_phone'=> $ins_phone.'',
				'ins_group_id' => $ins_group_id.'',
				'ins_authorization_id' => $ins_authorization_id.'',
				'ins_dob' => $ins_dob.'',
				'pa_ID' => $patientid.'',

			);
			if($ins_id==0)
			{
				$wpdb->insert('ci_insurances',$ins_arr);
				$ins_id = $wpdb->insert_id;
				if($this->debug)
				{
					$this->log_orders.= "Added new Insurance: {$insurance} => {$ins_id}\n";
				}
			}
		}


		$tests_requested = 'GROUP_'.$this->options['covid_group_id'];
		$source = $this->options['specimen'];
		if(empty($source))
		{
			$source = 'Nasal Swab';
		}

		$dagnostic_codes = '';
		if(empty($dagnostic_codes) || strpos($dagnostic_codes,'.')===false)
		{
			$dagnostic_codes = 'Z20.828';
		}


		$patient_name = $pa_last . ', ' . $pa_first;
		$data         = array(
			'assen'                => $assen,
			'patient_id'           => $patientid,
			'patient_name'         => $patient_name,
			'lab_ID'               => $this->options['lab_id'],
			'doc_ID'               => intval( $doctor_id ),
			'cli_ID'               => intval( $clinic_id),
			'doc'                  => $doc,
			'dob'                  => $dob,
			'requesting_physician' => $doctor_name.'',
			'requesting_practice'  => $clinic_name,
			'type_of_specimen'     => $source,
			'dagnostic_codes1'     => $dagnostic_codes,
			'panel_tests_requested' => $tests_requested,
			'insurance'             => $insurance.'',
			'insurance1'             => intval($ins_id),
			//'oldreq'                 => $ref_assen.'',
			'patient_signature'      => 'onfile',
			'paid'                  => $this->api_id,
		);
		if($priority_hours>0)
		{
			$data['priority_hours'] = $priority_hours;
		}


		if ( $bat ) {
			$mid = $bat->mid;

			if($bat->cli_ID>0)
			{
				//unset($data['cli_ID']);
				//unset($data['requesting_practice']);
			}
			if($bat->doc_ID>0 && empty($doctor_id))
			{
				unset($data['doc_ID']);
				unset($data['requesting_physician']);
			}
			foreach($data as $k => $vl)
			{
				if(empty($vl))
				{
					unset($data[$k]);
				}
			}

			$wpdb->update( $batch_tbl, $data, array( 'mid' => $bat->mid ) );
		} else {
			$data['batch_created'] = date_i18n('Y-m-d H:i:s');

			$wpdb->insert( $batch_tbl, $data );
			$mid = $wpdb->insert_id;

			if($this->debug)
			{
				$this->log_orders.= "Added req {$assen} => id: {$mid} \n";
				if($mid==0)
				{
					$this->log_orders.= "err: ".$wpdb->last_error."\n";
				}
			}
		}
		if($mid==0)return false;

		$moreinfo = array(
			'clinicalordertypeid' => $order['clinicalordertypeid'],
			'status' => $order['status'],
			'documentationonly' => $order['documentationonly'],
			'assigneduser' => $order['assigneduser'],
			'orderingprovider' => $order['orderingprovider'],
			'primaryproviderid' => $pa['primaryproviderid'],
			'class' => $order['class'],
			'description' => $order['description'],
		);

		$update = array(
			'mo_file_id'    => $this->api_id,
			'mo_mid'        => $mid,
			'mo_test_ID'    => $order_id,
			'mo_control_ID'    => $encounterid,
			'mo_patient_ID'    => $opid,
			'mo_peg_ID'    => $departmentid,
			'other_info'    => json_encode($moreinfo),
			'mo_btype'      => 'pcr',
			'mo_set_ID'     => date_i18n('Y-m-d H:i:s')
		);

		if($orderdb && $orderdb->mo_ID>0)
		{
			$r = $wpdb->update('ci_sync_api_orders',$update,array('mo_ID'=>$orderdb->mo_ID));
		}else {
			$r = $wpdb->insert( 'ci_sync_api_orders', $update );
		}

		if($r===false)
		{
			$this->log_orders.= $wpdb->last_error;
			return false;
		}


		return $mid > 0;
	}

	public function sync_reports($over_id=0,$limit=100)
	{
		global $wpdb;
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';


		$pos_neg = array(
			'NEG' => 'Negative',
			'POS' => 'Positive',
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
					ba.doc,
					ba.spec_received,
					
					re.approve_date,
					re.created_date,
					re.instrument,
					
					mo.mo_ID,
					mo.mo_set_ID,
					mo.mo_test_ID,
					mo.mo_control_ID,
					mo.mo_patient_ID,
					mo.mo_peg_ID,
					mo.other_info
					
				FROM {$batch_tbl} ba
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid and mo.mo_file_id='{$this->api_id}'
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				
				where re.estatus = 'C'
				";
		if($over_id>0)
		{
			$sql.= " and re.id = {$over_id} ";
		}else{
			$sql.= " and mo.mo_status = 0 ";
		}
		$sql.= " limit {$limit} ";

		$rs = $wpdb->get_results($sql);

		if($this->debug && count($rs)==0)
		{
			if(!empty($wpdb->last_error))
			{
				$this->log_results.= "error: ".$wpdb->last_error;
			}
			$this->log_results.= "Not found results!";
		}

		$mo_ids = array();

		foreach($rs as $r)
		{
			$other_info = json_decode($r->other_info, true);

			$arr = trpdd_cal_ncovid19(array(),$r->id,$r->instrument,$r->created_date);
			if(empty($arr['pos-neg']) || $arr['pos-neg']=='PEN' || $arr['pos-neg']=='INV'){
				continue;
			}

			if(empty($r->spec_received))
			{
				$r->spec_received = $r->doc;
			}

			$file_path = trpdd_export_results_pdf($r->id,'',true,'pcr');


			//get labresultid

			if(empty($other_info['labresultid']))
			{
				$lrs = $this->patients_documents_labresult($r->mo_patient_ID,$r->mo_peg_ID,$r->mo_control_ID);
				if(is_array($lrs) && count($lrs['labresults'])>0)
				{

					foreach($lrs['labresults'] as $lr)
					{
						if($r->assen == $lr['externalaccessionid'] || $r->mo_test_ID==$lr['tietoorderid']){
							$other_info['labresultid'] = $lr['labresultid'];
							break;
						}
					}
					if(empty($other_info['labresultid']))
					{
						$other_info['labresultid'] = $lr['labresultid'];
					}
				}
			}

			//get $other_info['analyteid']
			if(empty($other_info['analyteid']) && !empty($other_info['labresultid']))
			{
				$rs1 = $this->get_result($r->mo_patient_ID,$other_info['labresultid']);
				if($rs1 && is_array($rs1))
				{
					foreach($rs1 as $rr1)
					{
						$analyteid  = $rr1['observations'][0]['analyteid'];
						if(!empty($analyteid))
						{
							$other_info['analyteid'] = $analyteid;
							break;
						}
					}
				}
			}
			if(empty($other_info['analyteid'])){
				$this->log_results.= "empty analyteid for {$r->assen} \n";

				$wpdb->update('ci_sync_api_orders',array(
					'mo_status' => 3,
				),array('mo_ID'=> $r->mo_ID));
				continue;
			}

			//send first one
			$rsp = $this->labresult($r->mo_patient_ID,
				$other_info['labresultid'],
				$other_info['orderingprovider'],
				$r->mo_peg_ID,
				$r->mo_test_ID,
				$other_info['clinicalordertypeid'],
				$other_info['analyteid'],
				$r->doc,
				$r->spec_received,
				$r->approve_date,
				$r->assen,
				$pos_neg[$arr['pos-neg']],
				null,
				''
				);


			if($other_info['labresultid']>0 && is_array($rsp) && $rsp[0]['labresultid']==$other_info['labresultid'])
			{
				$rsp['success'] = true;
			}

			if(is_array($rsp) && $rsp['success'])
			{
				$mo_ids[] = $r->mo_ID ;
				if(isset($rsp['labresultid']))
				{
					$other_info['labresultid'] = $rsp['labresultid'];
				}

				if($other_info['labresultid']>0)
				{
					$rs2= $this->documents_labresult_dataentrycompleted($other_info['labresultid']);
				}

				//send second with file pdf
				$this->labresult($r->mo_patient_ID,
					null,
					$other_info['orderingprovider'],
					$r->mo_peg_ID,
					$r->mo_test_ID,
					$other_info['clinicalordertypeid'],
					$other_info['analyteid'],
					$r->doc,
					$r->spec_received,
					$r->approve_date,
					$r->assen,
					$pos_neg[$arr['pos-neg']],
					$file_path,
					true);
				//$arr['pos-neg']!='POS'

				$wpdb->update('ci_sync_api_orders',array(
					'mo_status' => 1,
					'other_info' => json_encode($other_info),
				),array('mo_ID'=> $r->mo_ID));
			}else{
				$wpdb->update('ci_sync_api_orders',array(
					'mo_status' => 4,
				),array('mo_ID'=> $r->mo_ID));
			}

			if($this->is_Test)
			{
				$this->get_result($r->mo_patient_ID,$other_info['labresultid']);
			}

		}


		return count($mo_ids);

	}
}