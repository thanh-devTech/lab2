<?php
include_once('ci_hl7.php');

class MySoapClient extends SoapClient
{


	public function __doRequest($xml, $location, $action, $version, $one_way = 0)
	{
		//echo '<textarea style="width:100%;height:200px;">'.$xml.'</textarea>';
		//$xml = str_replace('ns1:', '', $xml);
		//$xml = str_replace(':ns1', '', $xml);
		//$xml = str_replace('env:', 'soap12:', $xml);
		//$xml = str_replace(':env=', ':soap12=', $xml);
		//$xml = str_replace('<soap12:Envelope', '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ', $xml);
		//echo '<textarea style="width:100%;height:200px;">'.$xml.'</textarea>';
		$xml2 = parent::__doRequest($xml, $location, $action, $version, $one_way);
		//echo '<textarea style="width:100%;height:200px;">'.$xml2.'</textarea>';


		$xml2 = str_replace("¨", '', $xml2);
		$xml2 = str_replace("", '', $xml2);
		//$xml2 = str_replace("",'',$xml2);
		$xml2 = str_replace('&#x11;', '', $xml2);

		//echo '<textarea style="width:100%;height:200px;">'.$xml2.'</textarea>';

		return $xml2;
	}
}

class SubmitResults
{
	public $LIS_Key;
	public $results = array();
}

class ResultMessage
{
	public $MessageID;
	public $Message;
}


class AcknowledgeOrderReceipt
{
	public $LIS_Key;
	public $Acks = array();
}

class MessageAck
{
	public $MessageID;
	public $MessageAccepted;
	public $TransactionDetail;
}

class CI_Api_ELINK extends CI_Hl7
{

	var $wsdl = 'https://www.mylabsync.com/WebServices/eLink_LIS/eLink_LIS.asmx?wsdl';
	var $key = '';
	var $options = array();
	public $log = '';
	public $log_dir = '';
	public $debug = false;
	public $api_id = -2;
	var $covid_test;
	public $last_error = '';
	protected  $client = null;
	public $is_test = false;


	var $default_clinic_name = '';
	var $default_doctor_name = '';
	var $inow = 0;
	var $group_covid_id = 0;
	var $success_ids = array();
	var $clinics = array();

	public function __construct($options)
	{
		global $wpdb;

		$this->key = isset($options['key']) ? $options['key'] : '';

		$this->options = $options;
		$this->covid_test = trpdd_get_pcr_covid_test();
		$this->log_dir = WP_CONTENT_DIR . '/log/elink';
		if (!file_exists($this->log_dir)) {
			wp_mkdir_p($this->log_dir);
		}

		if (!class_exists('SoapClient')) {
			echo 'SoapClient not exist';
			return false;
		}

		$this->default_clinic_name = '';
		if ($this->options['cli_id'] > 0) {
			$this->default_clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$this->options['cli_id']}' ");
		}

		$this->default_doctor_name = '';
		if ($this->options['doc_id'] > 0) {
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->options['doc_id']}");
			$this->default_doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
		}


		$context = stream_context_create([
			'ssl' => [
				// set some SSL/TLS specific options
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			]
		]);

		for ($i = 0; $i <= 3; $i++) {
			try {
				$this->client = new MySoapClient($this->wsdl, array(
					'features' => SOAP_WAIT_ONE_WAY_CALLS,
					'exceptions' => 1,
					'trace' => 1,
					'soap_version' => SOAP_1_2,
					'cache_wsdl' => WSDL_CACHE_NONE,
					'stream_context' => $context,
					'classmap' => array(
						'SubmitResults' => SubmitResults::class,
						'AcknowledgeOrderReceipt' => AcknowledgeOrderReceipt::class
					)

				));
				$this->last_error = '';
				break;
			} catch (Exception $e) {
				$this->last_error = $e->getMessage();
				sleep(3);
			}
			if ($this->client) {
				break;
			}
		}
	}

	public function AcknowledgeOrderReceipt($msg_ids = array())
	{
		$acks = array();
		foreach ($msg_ids as $id => $MessageAccepted) {
			$MessageAck = new MessageAck();
			$MessageAck->MessageID = $id;
			$MessageAck->MessageAccepted = $MessageAccepted;
			$MessageAck->TransactionDetail = '';
			$acks[] = $MessageAck;
		}
		$obj = array();
		$obj['LIS_Key'] = $this->key;
		$obj['Acks'] = $acks;


		$rs = $this->client->AcknowledgeOrderReceipt($obj);
		return $rs;
	}

	public function RequestOrders($max = 50, $useack = true)
	{
		$obj = array();
		$obj['LIS_Key'] = $this->key;
		$obj['MaxOrders'] = $max;
		$obj['UseAck'] = $useack;


		$rs = $this->client->RequestOrders($obj);
		return $rs;
	}

	public function SubmitResults($results)
	{
		$obj = new SubmitResults();
		$obj->LIS_Key = $this->key;
		$obj->results = $results;

		$rs = $this->client->SubmitResults($obj);

		if (!$rs) {
			$this->log .= "response empty\n";
			return false;
		}
		if ($rs->SubmitResultsResult->ResultResponseCode == -1) {
			$this->log .= "response ResultResponseCode = -1\n";
			return false;
		}

		$this->log .= "response:" . json_encode($rs) . "\n";

		return true;
	}
	/*
	public function reimport_reqs()
	{
		global $wpdb;
		$sql = "select mo_ID, 
						pa.pa_phone, 
						pa.pa_email, 
					pa_ID, 
					pa_ssn, 
					pa_mrn
		            from ci_sync_api_orders po
				join ci_batches_pcr ba ON po.mo_mid = ba.mid
				join ci_patients pa ON pa.pa_ID = ba.patient_id
				order by mo_ID desc 
				";
		$rs = $wpdb->get_results($sql);
		$count = 0;
		foreach($rs as $r)
		{
			$filepath = $this->log_dir.'/'. $r->mo_ID.'.hl7';
			if(!file_exists($filepath))continue;
			$arr = $this->hl7_to_array(file_get_contents($filepath));
			$phone = $r->pa_phone;
			$email = $r->pa_email;

			$ssn          = trim($arr['PID']['PID.19']['PID.19.1']);
			$mrn           = $arr['PID']['PID.3']['PID.3.1'];
			$ssn2 = str_replace('0','',$ssn);
			if(empty($ssn2) || stripos($ssn,'n/a')!==false){
				$ssn = '';
			}
			if($arr['OBX'] )
			{
				foreach($arr['OBX'] as $obx)
				{
					if(empty($phone) && stripos($obx['OBX.3']['OBX.3.2'],'phone')!==false)
					{
						$phone = $obx['OBX.5']['OBX.5.1'];
					}
					else if((empty($email) || strpos($email,'@')===false)
					        && stripos($obx['OBX.3']['OBX.3.2'],'email')!==false)
					{
						$email = $obx['OBX.5']['OBX.5.1'];
					}
				}

			}
			$update = array();

			if(empty($r->pa_ssn) && !empty($ssn) && $ssn != $r->pa_ssn)
			{
				$update['pa_ssn'] = $ssn;
			}
			if(empty($r->pa_mrn) && !empty($mrn) && $mrn!=$r->pa_mrn)
			{
				$update['pa_mrn'] = $mrn;
			}

			if(!empty($phone) && $phone !=$r->pa_phone){
				$update['pa_phone'] = $phone;
			}

			if(!empty($email) && strpos($email,'@')>0 && $r->pa_email != $email)
			{
				$update['pa_email'] = $email;
			}

			if(!empty($update))
			{
				$wpdb->update('ci_patients',$update,array('pa_ID'=>$r->pa_ID));
				$count++;
			}

		}
		return $count;
	}

	public function fix_patients()
	{
		global $wpdb;
		$sql = "select mo_ID,
						ba.mid
		            from ci_sync_api_orders po
				join ci_batches_pcr ba ON po.mo_mid = ba.mid
				where ba.patient_id = 0
				order by mo_ID desc 
				";
		$rs = $wpdb->get_results($sql);
		$count = 0;
		foreach($rs as $r)
		{
			$mid = $r->mid;
			$filepath = $this->log_dir.'/'. $r->mo_ID.'.hl7';
			if(!file_exists($filepath))continue;
			$arr = $this->hl7_to_array(file_get_contents($filepath));

			$hl7_patient_id= $arr['PID']['PID.2']['PID.2.1'];
			$mrn           = $arr['PID']['PID.3']['PID.3.1'];
			$other_info['PID3']= $arr['PID']['PID.3']['PID.3.1'];
			$other_info['PID4']= $arr['PID']['PID.4']['PID.4.1'];
			$pa_last      = trim($arr['PID']['PID.5']['PID.5.1']);
			$pa_first     = trim($arr['PID']['PID.5']['PID.5.2']);
			$dob          = $arr['PID']['PID.7']['PID.7.1'];
			$gender       = $arr['PID']['PID.8']['PID.8.1'];
			$race_code       = $arr['PID']['PID.10']['PID.10.1'];
			$address      = trim($arr['PID']['PID.11']['PID.11.1'].' '.$arr['PID']['PID.11']['PID.11.2']);
			$city         = $arr['PID']['PID.11']['PID.11.3'];
			$state        = $arr['PID']['PID.11']['PID.11.4'];
			$zip          = trim($arr['PID']['PID.11']['PID.11.5']);
			$phone        = trim($arr['PID']['PID.13']['PID.13.1']);
			$email        = trim($arr['PID']['PID.13']['PID.13.2']);
			$ssn          = trim($arr['PID']['PID.19']['PID.19.1']);

			$ssn2 = str_replace('0','',$ssn);
			if(empty($ssn2) || stripos($ssn,'n/a')!==false){
				$ssn = '';
			}


			if(stripos($phone,'n/a')!==false || stripos($phone,'na')!==false){
				$phone = '';
			}
			if($arr['OBX'] )
			{
				foreach($arr['OBX'] as $obx)
				{
					if(empty($phone) && stripos($obx['OBX.3']['OBX.3.2'],'phone')!==false)
					{
						$phone = $obx['OBX.5']['OBX.5.1'];
					}
					else if(stripos($obx['OBX.3']['OBX.3.2'],'email')!==false)
					{
						$email = $obx['OBX.5']['OBX.5.1'];
					}
				}

			}

			if(strpos($email,'@')===false)
			{
				$email = '';
			}
			$email = strtolower($email);
			$email = substr($email,0,200);

			$phone = preg_replace("/[^0-9\+\-]+/",'',$phone);
			$phone = substr($phone,0,18);
			$address = substr($address,0,150);
			$city = substr($city,0,100);
			$state = substr($state,0,4);
			$zip = substr($zip,0,12);

			if(empty($pa_last) && empty($pa_first))continue;


			$dob    = date( 'Y-m-d', strtotime( $dob ) );

			$gender = stripos( $gender, 'u' ) !==false ? 'U' : $gender;
			$gender = stripos( $gender, 'f' ) !==false ? 'F' : 'M';


			$patientid = 0;
			if ( $patientid == 0 ) {

				$patientid = $wpdb->get_var( $wpdb->prepare(
					"select pa_ID from ci_patients where pa_first = %s
				                and pa_last=%s and pa_dob=%s and pa_city = %s " ,
					$pa_first,$pa_last,$dob ,$city));
				$patient = array(
					'pa_first'  => $pa_first,
					'pa_last'   => $pa_last,
					'pa_dob'    => $dob.'',
					'pa_gender' => $gender.'',
					'pa_phone'  => $phone.'',
					'pa_address'    => $address.'',
					'pa_city'    => $city.'',
					'pa_state'    => $state.'',
					'pa_zip'    => $zip.'',
					'pa_email'  => $email.'',
					'pa_ssn'    => $ssn.'',
					'pa_mrn'    => $mrn.'',
				);
				if(!empty($hl7_patient_id))
				{
					$patient['pa_mrn'] = $hl7_patient_id;
				}

				//fix phone
				if(isset($patient['pa_phone'])){
					$patient['pa_phone'] = str_replace(array(' ','(',')','-','.'),'',$patient['pa_phone']);
				}
				foreach($patient as $k => $vl)
				{
					if(empty($vl))
					{
						unset($patient[$k]);
					}
				}
				if ( $patientid == 0 ) {

					$wpdb->insert( 'ci_patients', $patient );
					$patientid = $wpdb->insert_id;
				}else{
					$wpdb->update('ci_patients',$patient,array('pa_ID'=>$patientid));
				}
			}
			$update = array(
				'patient_id'=>$patientid
			);


			if(!empty($update))
			{
				$wpdb->update('ci_batches_pcr',$update,array('mid'=>$mid));
				$count++;
			}

		}
		return $count;
	}
	*/

	public function  import_reqs($max = 50, $useack = true)
	{
		global $wpdb;
		if (!$this->client) {
			return false;
		}

		$rs = $this->RequestOrders($max, $useack);


		$this->success_ids = array();

		$orders = $rs->RequestOrdersResult->Orders;

		if (!is_array($orders) && isset($orders->OrderMessage) && is_array($orders->OrderMessage)) {
			$orders = $orders->OrderMessage;
		}
		$this->inow = 1;
		foreach ($orders as $order) {
			$msg_id = $order->MessageID;
			$msg = $order->Message;

			if (empty($msg_id)) {
				$this->log .= "empty msg_id\n";
				continue;
			}

			$succ = $this->import_req_file($msg_id, $msg);
		}

		if (count($this->success_ids) > 0 && $useack) {
			$rs = $this->AcknowledgeOrderReceipt($this->success_ids);
		}
		return count($this->success_ids);
	}

	public function import_req_file($msg_id, $msg)
	{
		global $wpdb;

		if (empty($msg)) {
			return false;
		}

		$btype = 'pcr';
		$batch_tbl = 'ci_batches_pcr';


		if (empty($this->clinics)) {
			$this->group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups 
			where (tg_is_covid=1 or tg_name ='nCoV-2019') 
			and tg_type='pcr'");


			$clis = $wpdb->get_results("select cID, cli_clinic, 
			cli_elink_id, cli_elink_id2 
			from ci_clinic where cli_elink_id !=''");
			$this->clinics = array();
			foreach ($clis as $cli) {
				if (!empty($cli->cli_elink_id)) {
					$this->clinics[$cli->cli_elink_id] = $cli;
				}
				if (!empty($cli->cli_elink_id2)) {
					$this->clinics[$cli->cli_elink_id2] = $cli;
				}
			}
		}



		$other_info = array();

		$arr = $this->hl7_to_array($msg, array('OBR'));

		$mid = 0;

		$control_id   = $arr['MSH']['MSH.10']['MSH.10.1'];
		if (empty($msg_id)) {
			$msg_id = $control_id;
		}

		$hl7_set_ID   = $arr['PID']['PID.1']['PID.1.1'];
		$hl7_patient_id = $arr['PID']['PID.2']['PID.2.1'];
		$mrn           = $arr['PID']['PID.3']['PID.3.1'];
		$other_info['PID3'] = $arr['PID']['PID.3']['PID.3.1'];
		$other_info['PID4'] = $arr['PID']['PID.4']['PID.4.1'];

		if (is_array($arr['PID']['PID.5']['PID.5.1'])) {
			$arr['PID']['PID.5']['PID.5.1'] = implode(' ', $arr['PID']['PID.5']['PID.5.1']);
		}
		if (is_array($arr['PID']['PID.5']['PID.5.2'])) {
			$arr['PID']['PID.5']['PID.5.2'] = implode(' ', $arr['PID']['PID.5']['PID.5.2']);
		}
		$pa_last      = trim($arr['PID']['PID.5']['PID.5.1']);
		$pa_first     = trim($arr['PID']['PID.5']['PID.5.2']);
		$dob          = $arr['PID']['PID.7']['PID.7.1'];
		$gender       = $arr['PID']['PID.8']['PID.8.1'];
		$race_code       = $arr['PID']['PID.10']['PID.10.1'];
		$address      = trim($arr['PID']['PID.11']['PID.11.1'] . ' ' . $arr['PID']['PID.11']['PID.11.2']);
		$city         = $arr['PID']['PID.11']['PID.11.3'];
		$state        = $arr['PID']['PID.11']['PID.11.4'];
		$zip          = trim($arr['PID']['PID.11']['PID.11.5']);
		$ethnicity       = $arr['PID']['PID.22']['PID.22.1'];


		$ssn          = trim($arr['PID']['PID.19']['PID.19.1']);

		$homephone    = '';
		$phone        = '';
		$email        = '';
		if (is_array($arr['PID']['PID.13']['PID.13.1'])) {
			foreach ($arr['PID']['PID.13'] as $arr13) {
				if ($arr13[2] == 'CP') {
					$phone = $arr13[0];
				} else if ($arr13[2] == 'PH') {
					$homephone = $arr13[0];
				} else if ($arr13[2] == 'NET') {
					$email = $arr13[3];
				}
			}
			if (empty($phone)) {
				$phone = $homephone;
			}
		} else {
			$phone        = trim($arr['PID']['PID.13']['PID.13.1']);
			$email        = trim($arr['PID']['PID.13']['PID.13.2']);
		}

		$ssn2 = str_replace('0', '', $ssn);
		if (empty($ssn2) || stripos($ssn, 'n/a') !== false) {
			$ssn = '';
		}



		$insurance      = $arr['IN1']['IN1.4']['IN1.4.1'];
		$ins_address    = $arr['IN1']['IN1.5']['IN1.5.1'];
		$ins_city       = $arr['IN1']['IN1.5']['IN1.5.3'];
		$ins_state      = $arr['IN1']['IN1.5']['IN1.5.4'];
		$ins_zip        = $arr['IN1']['IN1.5']['IN1.5.5'];
		$ins_phone      = $arr['IN1']['IN1.7']['IN1.7.1'];
		$ins_group_id   = $arr['IN1']['IN1.8']['IN1.8.1'];
		$ins_group_name   = $arr['IN1']['IN1.9']['IN1.9.1'];
		$ins_last       = $arr['IN1']['IN1.16']['IN1.16.1'];
		$ins_first      = $arr['IN1']['IN1.16']['IN1.16.2'];
		$ins_dob        = $arr['IN1']['IN1.18']['IN1.18.1'];
		$ins_member_id = $arr['IN1']['IN1.36']['IN1.36.1'];
		$ins_authorization_id = ''; //$arr['IN1']['IN1.36']['IN1.36.1'];

		$order_id       = $arr['ORC']['ORC.2']['ORC.2.1'];

		$doc_npi          = $arr['ORC']['ORC.12']['ORC.12.1'];
		$doc_last         = $arr['ORC']['ORC.12']['ORC.12.2'];
		$doc_first         = $arr['ORC']['ORC.12']['ORC.12.3'];


		$peg_id          = '';
		$oldreq          = '';
		$doc				= '';

		$priority_hours  = 0;
		$other_info['OBR_code'] = array();
		$other_info['OBR_names'] = array();
		foreach ($arr['OBR'] as $obr) {

			$peg_id         = $obr['OBR.13']['OBR.13.1'];
			$oldreq        	= $obr['OBR.2']['OBR.2.1'];
			$doc            = $obr['OBR.7']['OBR.7.1'];

			$other_info['OBR41'] = trim($obr['OBR.4']['OBR.4.1']);
			$other_info['OBR42'] = trim($obr['OBR.4']['OBR.4.2']);
			$other_info['OBR7'] = trim($obr['OBR.7']['OBR.7.1']);

			$other_info['OBR_code'][] = $other_info['OBR41'];
			$other_info['OBR_names'][$other_info['OBR41']] = $other_info['OBR42'];

			$other_info['OBR16.1'] = trim($obr['OBR.16']['OBR.16.1']);
			$other_info['OBR16.2'] = trim($obr['OBR.16']['OBR.16.2']);
			$other_info['OBR16.3'] = trim($obr['OBR.16']['OBR.16.3']);

			if (stripos($obr['OBR.15']['OBR.15.1'], '12h') !== false) {
				$priority_hours = 12;
			} else if (stripos($obr['OBR.15']['OBR.15.1'], '24h') !== false) {
				$priority_hours = 24;
			}
		}


		
		$dagnostic_codes = array();
		if (isset($arr['DG1'])) {
			foreach ($arr['DG1'] as $dg1) {
				if (!empty($dg1['DG1.3']['DG1.3.1'])) {
					$dagnostic_codes[] = $dg1['DG1.3']['DG1.3.1'];
				}
			}
		}

		$dagnostic_codes = implode(',', $dagnostic_codes);


		if (empty($doc) && isset($arr['ORC']['ORC.9']['ORC.9.1'])) {
			$doc            = $arr['ORC']['ORC.9']['ORC.9.1'];
		}

		if (empty($peg_id)) {
			$peg_id = $oldreq;
		}
		$oldreq = substr($oldreq, 3, 7);


		if (stripos($phone, 'n/a') !== false || stripos($phone, 'na') !== false || strlen($phone) > 11) {
			$phone = '';
		}
		if (isset($arr['OBX']) && $arr['OBX']) {
			foreach ($arr['OBX'] as $obx) {
				if (empty($phone) && stripos($obx['OBX.3']['OBX.3.2'], 'phone') !== false) {
					$phone = $obx['OBX.5']['OBX.5.1'];
				} else if (stripos($obx['OBX.3']['OBX.3.2'], 'email') !== false) {
					$email = $obx['OBX.5']['OBX.5.1'];
				}
				if ($obx['OBX.5']['OBX.5.1'] == 12 || $obx['OBX.5']['OBX.5.1'] == 24) {
					$priority_hours = $obx['OBX.5']['OBX.5.1'];
				}
			}
		}

		if (strpos($email, '@') === false) {
			$email = '';
		}
		$email = strtolower($email);
		$email = substr($email, 0, 200);

		$phone = preg_replace("/[^0-9\+\-]+/", '', $phone);
		$phone = substr($phone, 0, 18);
		$address = substr($address, 0, 150);
		$city = substr($city, 0, 100);
		$state = substr($state, 0, 4);
		$zip = substr($zip, 0, 12);

		if (empty($pa_last) && empty($pa_first)) {
			return false;
		};


		$other_info['NTE'] = isset($arr['NTE']['NTE.3']['NTE.3.1']) ? trim($arr['NTE']['NTE.3']['NTE.3.1']) : '';
		$other_info['ORC_ID'] = $order_id;
		$other_info['MSH4'] = trim($arr['MSH']['MSH.4']['MSH.4.1']);
		$other_info['MSH5'] = trim($arr['MSH']['MSH.5']['MSH.5.1']);
		$other_info['MSH6'] = trim($arr['MSH']['MSH.6']['MSH.6.1']);



		$lab_id = $this->options['lab_id'];
		$cli_id = $this->options['cli_id'];
		$doc_id = $this->options['doc_id'];
		$clinic_name = $this->default_clinic_name;
		$doctor_name = $this->default_doctor_name;

		$lab_key = '';

		if (isset($this->clinics[$other_info['MSH4']])) {
			$cli = $this->clinics[$other_info['MSH4']];
			$cli_id = $cli->cID;
			$clinic_name = $cli->cli_clinic;
		} else if ($this->options['ma_elinkid']) {
			return false;
		}

		if ($this->options['ma_msh4']) {
			$match = false;
			if (!empty($this->options['msh4']) && strtoupper($this->options['msh4']) == strtoupper($other_info['MSH6'])) {
				$match = true;
			}

			if (!$match && $this->options['msh4'] == 'WELLNOWNYLAB' && $other_info['MSH6'] == 'WELLNOWLABNY') {
				$match = true;
			}

			if (!$match && isset($this->options['msh6list'])) {
				foreach ($this->options['msh6list'] as $im => $msh6m) {
					if (!empty($msh6m) && strtoupper($other_info['MSH6']) == strtoupper($msh6m)) {
						$match = true;
						if (isset($this->options['lab_idlist'][$im]) && $this->options['lab_idlist'][$im] > 0) {
							$lab_id = $this->options['lab_idlist'][$im];
							$lab_key = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$lab_id} ");
						}
					}
				}
			}

			if (!$match) {
				echo 'nomatch:';
				echo 'msh4:';
				echo ($this->options['msh4']);
				echo 'msh6:';
				echo ($other_info['MSH6']);
				echo '<br><br>';
				echo $msg;
				echo '<br><br>';
				return false;
			}
		}


		$dob    = date('Y-m-d', strtotime($dob));

		if (empty($doc)) {
			$doc = date_i18n('Y-m-d H:i:s');
		}
		$doc    = date('Y-m-d H:i:s', strtotime($doc));

		$gender = stripos($gender, 'u') !== false ? 'U' : $gender;
		$gender = stripos($gender, 'f') !== false ? 'F' : 'M';

		$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
						where mo_test_ID='{$msg_id}' and mo_peg_ID = '{$peg_id}'
							  and mo_file_id = '{$this->api_id}' ");

		$mid = $orderdb && $orderdb->mo_mid > 0 ? $orderdb->mo_mid : 0;
		$bat = null;
		if ($mid > 0) {
			$bat = $wpdb->get_row("select * from ci_batches_pcr where mid = {$mid} ");
		}

		if (!$bat) {
			$assen = '';
			if (empty($assen)) {
				$assen = tr_get_new_assen('pcr', false, $lab_key);
			} else {
				//check assen exist
				$bat = $wpdb->get_row("select * from ci_batches_pcr where assen = '{$assen}'");
			}

			if (empty($assen)) {
				return false;
			}
		} else {
			$assen = $bat->assen;
		}
		$this->log .= ($this->inow++) . '. order ' . $msg_id . ' => assen:' . $assen . " ";

		$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;
		if ($patientid == 0) {

			$sql_patient = $wpdb->prepare(
				"select pa_ID from ci_patients 
							where pa_first = %s
							and pa_last=%s 
							and pa_dob=%s 
							and pa_address = %s ",
				$pa_first,
				$pa_last,
				$dob,
				$address
			);
			if (!empty($phone)) {
				$sql_patient .= " and pa_phone = '{$phone}'";
			}

			$patientid = $wpdb->get_var($sql_patient);
			$patient = array(
				'pa_first'  => $pa_first,
				'pa_last'   => $pa_last,
				'pa_dob'    => $dob . '',
				'pa_gender' => $gender . '',
				'pa_phone'  => $phone . '',
				'pa_address'    => $address . '',
				'pa_city'    => $city . '',
				'pa_state'    => $state . '',
				'pa_zip'    => $zip . '',
				'pa_email'  => $email . '',
				'pa_ssn'    => $ssn . '',
				'pa_mrn'    => $mrn . '',
			);
			if (!empty($race_code)) {
				if (stripos($race_code, 'white') !== false) {
					$race_code = '2106-3';
				} else if (stripos($race_code, 'American Indian') !== false) {
					$race_code = '1002-5';
				} else if (stripos($race_code, 'Asian') !== false) {
					$race_code = '2028-9';
				} else if (stripos($race_code, 'Black') !== false) {
					$race_code = '2054-5';
				} else if (stripos($race_code, 'Hawaiian') !== false) {
					$race_code = '2076-8';
				} else if (stripos($race_code, 'Other') !== false) {
					$race_code = '2131-1';
				} else {
					$race_code = '';
				}

				$patient['pa_race'] = substr($race_code, 0, 10);
			}

			if (!empty($ethnicity)) {
				if (stripos($ethnicity, 'Not Hispanic or Latino') !== false) {
					$ethnicity = 'N';
				} else if (stripos($ethnicity, 'Hispanic or Latino') !== false) {
					$ethnicity = 'H';
				} else if (stripos($ethnicity, 'Not Hispanic') !== false) {
					$ethnicity = 'N';
				} else if (stripos($ethnicity, 'Hispanic') !== false) {
					$ethnicity = 'H';
				} else {
					$ethnicity = 'U';
				}
				$patient['pa_ethnicity'] = substr($ethnicity, 0, 10);
			}

			if (!empty($hl7_patient_id)) {
				$patient['pa_mrn'] = $hl7_patient_id;
			}
			foreach ($patient as $k => $vl) {
				if (empty($vl)) {
					unset($patient[$k]);
				}
			}
			if ($patientid == 0) {

				$wpdb->insert('ci_patients', $patient);
				$patientid = $wpdb->insert_id;
			} else {
				$wpdb->update('ci_patients', $patient, array('pa_ID' => $patientid));
			}
		}

		$ins_id = $bat && $bat->insurance1 > 0 ? $bat->insurance1 : 0;
		if ($ins_id == 0) {
			if (empty($ins_first)) {
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
				'ins_company' => $insurance . '',
				'ins_address' => $ins_address . '',
				'ins_city' => $ins_city . '',
				'ins_state' => $ins_state . '',
				'ins_zip' => $ins_zip . '',
				'ins_phone' => $ins_phone . '',
				'ins_member_id' => $ins_member_id . '',
				'ins_group_id' => $ins_group_id . '',
				'ins_authorization_id' => $ins_authorization_id . '',
				'ins_dob' => $ins_dob . '',
				'pa_ID' => $patientid . '',

			);
			if ($ins_id == 0) {
				$wpdb->insert('ci_insurances', $ins_arr);
				$ins_id = $wpdb->insert_id;
				if ($this->debug) {
					$this->log .= " => Added new Insurance: {$insurance} => {$ins_id} ";
				}
			}
		}





		if (!empty($doc_first) && !empty($doc_last) && !empty($doc_npi)) {
			$doc_id = $wpdb->get_var($wpdb->prepare("select doc_ID from ci_doc where  
									 doc_first=%s  
										and doc_last=%s 
									  and doc_npi = %s ", $doc_first, $doc_last, $doc_npi));

			if ($doc_id == 0) {
				$doc_id = $wpdb->get_var("select doc_ID from ci_doc where doc_npi = '{$doc_npi}'");
			}
			if ($doc_id == 0) {
				$wpdb->insert('ci_doc', array('doc_first' => $doc_first, 'doc_last' => $doc_last, 'doc_npi' => $doc_npi));
				$doc_id = $wpdb->insert_id;
				if ($this->debug) {
					$this->log .= " => Added new doctor: {$doc_first} {$doc_last}";
				}
			}
			$doctor_name = $doc_last . ', ' . $doc_first;
		}


		if ($doc_id > 0 && $cli_id > 0) {
			$existdc = $wpdb->get_var("select doc_ID from ci_clinic_doc 
								where doc_ID = {$doc_id}
								and cli_ID = {$cli_id} ");
			if (!$existdc) {
				$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $cli_id));
			}
		}


		$tests_requested = array();
		foreach ($other_info['OBR_code'] as $i => $bcode) {
			if (!empty($bcode)) {
				//check group first
				$group = $wpdb->get_row("select * from ci_test_groups 
				where ( tg_cpt_code='{$bcode}' or tg_short_name = '{$bcode}' )
					and tg_type = '{$btype}' ");
				echo $wpdb->last_error;

				if ($group) {
					if (!in_array('GROUP_' . $group->tg_ID, $tests_requested)) {
						$tests_requested[] = 'GROUP_' . $group->tg_ID;
					}

					$testarr = $wpdb->get_results("select te.te_ID, g.tg_ID, g.tg_only_group
					from ci_test_panels te
					JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
					where  (g.tg_ID = {$group->tg_ID} or g.tg_parent = {$group->tg_ID} )
						and te_type = 'pcr' 
					group by te.te_ID
					", ARRAY_A);
					echo $wpdb->last_error;
					//($testarr);


					foreach ($testarr as $t) {
						$tests_requested[] = $t['te_ID'];
					}
				}
			} else {
				unset($other_info['OBR_code'][$i]);
			}
		}


		if (count($tests_requested) == 0) {
			$tests_requested[] = 'GROUP_' . $this->group_covid_id;
		}
		//var_dump($tests_requested);
		$tests_requested = implode(',', $tests_requested);

		$source = 'Nasal Swab';
		if (empty($dagnostic_codes)) {
			$dagnostic_codes = 'Z20.828';
		}



		$patient_name = $pa_last . ', ' . $pa_first;
		$data         = array(
			'assen'                => $assen,
			'patient_id'           => $patientid,
			'patient_name'         => $patient_name,
			'lab_ID'               => $lab_id,
			'doc_ID'               => intval($doc_id),
			'cli_ID'               => intval($cli_id),
			'doc'                  => $doc,
			'dob'                  => $dob,
			'requesting_physician' => $doctor_name . '',
			'requesting_practice'  => $clinic_name . '',
			'type_of_specimen'     => $source,
			'dagnostic_codes1'     => $dagnostic_codes,
			'panel_tests_requested' => $tests_requested,
			'insurance'             => $insurance . '',
			'insurance1'             => intval($ins_id),
			'oldreq'                 => $oldreq . '',
			'patient_signature'      => 'onfile',
			'paid'                  => $this->api_id,

		);
		if (!empty($other_info['NTE'])) {
			$data['notes'] = $other_info['NTE'];
		}
		if (!empty($priority_hours)) {
			$data['priority_hours'] = $priority_hours;
		}

		//$data['batch_id'] = date('Ymd',strtotime($data['doc'])).'-1';

		if ($bat) {
			$mid = $bat->mid;

			if ($bat->cli_ID > 0) {
				unset($data['cli_ID']);
				unset($data['requesting_practice']);
			}
			if ($bat->doc_ID > 0) {
				unset($data['doc_ID']);
				unset($data['requesting_physician']);
			}
			foreach ($data as $k => $vl) {
				if (empty($vl)) {
					unset($data[$k]);
				}
			}

			$wpdb->update($batch_tbl, $data, array('mid' => $bat->mid));
		} else {
			$data['batch_created'] = date_i18n('Y-m-d H:i:s');

			$wpdb->insert($batch_tbl, $data);
			$mid = $wpdb->insert_id;


			if ($mid == 0) {
				$this->log .= " => err: " . $wpdb->last_error . " ";
			} else {
				$this->log .= " => Added req id: {$mid} ";
			}
		}
		$this->log .= "\n";

		if ($mid == 0) {

			return false;
		}

		var_dump($mid);
		var_dump($assen);
		$other_info['dt'] = date_i18n('Y-m-d H:i:s');

		$update = array(
			'mo_file_id'    => $this->api_id,
			'mo_mid'        => $mid,
			'mo_control_ID' => $control_id . '',
			'mo_test_ID'    => $msg_id . '',
			'mo_set_ID'     => $hl7_set_ID . '',
			'mo_patient_ID' => $hl7_patient_id . '',
			'mo_peg_ID' => $peg_id . '',
			'other_info' => json_encode($other_info) . '',
		);

		if ($orderdb && $orderdb->mo_ID > 0) {
			$mo_ID = $orderdb->mo_ID;
			$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $orderdb->mo_ID));
		} else {
			$r = $wpdb->insert('ci_sync_api_orders', $update);
			$mo_ID = $wpdb->insert_id;
		}

		if ($mo_ID > 0) {
			$this->success_ids[$msg_id] = true;
			//log msg
			$filepath = $this->log_dir . '/' . $mo_ID . '.hl7';
			file_put_contents($filepath, $msg);
		}
		if ($r === false) {
			$this->log .= $wpdb->last_error;
		}
		return true;
	}

	public function sync_reports($limit = 10, $over_id = 0)
	{
		global $wpdb;

		if (!$this->client) {
			return false;
		}
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';



		$sql = "select 
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
					
					re.id,
					re.approve_date,
					re.instrument,
					re.created_date,
					
					ba.mid,
					ba.batch_id,
					ba.assen,
					ba.patient_name,
					ba.doc,
					ba.spec_received,
					ba.batch_status,
					ba.panel_tests_requested,
					ba.type_of_specimen,

					mo.mo_ID,
					mo.mo_set_ID,
					mo.mo_test_ID,
					mo.mo_control_ID,
					mo.mo_patient_ID,
					mo.mo_peg_ID,
					mo.other_info,
					mo.mo_status,
					
					lab.lab_name,
					lab.lab_clia,
					lab.lab_address,
					lab.lab_city,
					lab.lab_state,
					lab.lab_zip,
					
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
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				where re.estatus = 'C' 
				and mo.mo_file_id = {$this->api_id}
				";
		if ($over_id > 0) {
			$sql .= " and re.id = {$over_id} ";
		} else {
			$limit_date = date('Y-m-d', time() - 86400 * 20);
			$sql .= " and (mo.mo_status = 0 or mo.mo_status = 2) ";
			$sql .= " and ba.doc > '{$limit_date}' ";
		}
		$sql .= " limit {$limit} ";

		//$this->log.= $sql."\n\n";

		$rs = $wpdb->get_results($sql);



		if ($this->debug && count($rs) == 0) {
			if (!empty($wpdb->last_error)) {
				$this->log .= "error: " . $wpdb->last_error;
			}
			$this->log .= "Not found results!";
		}

		$data = array();
		$mo_ids = array();
		$inum = 1;
		foreach ($rs as $r) {
			try {
				$msg = $this->build_msg($r);
				if (!$msg) continue;

				$data[] = $msg;
				$mo_ids[] = $r->mo_ID;

				$this->log .= ($inum++) . '. report assen:' . $r->assen . "\n";
			} catch (Exception $e) {
				$this->log .= 'error: ' . $e->getMessage();
			}
		}

		if (count($data) == 0) {
			return 0;
		}

		if (isset($_REQUEST['act']) && $_REQUEST['act'] == 'rebuild') {
			var_dump($data);
			exit;
		}
		try {

			$rs = $this->SubmitResults($data);

			if ($rs) {
				$mo_id_str = implode(',', $mo_ids);
				$wpdb->query("update ci_sync_api_orders set mo_status = 1 where mo_ID in ({$mo_id_str})");
			} else {
				var_dump($rs);
			}
		} catch (Error $e) {
			var_dump($e);
		}
		return count($data);
	}

	public function build_msg($r)
	{
		global $wpdb;

		$btype = 'pcr';

		$pos_neg = array(
			'' => '',
			'NEG' => 'Negative',
			'POS' => 'Positive',
			'POL' => 'Positive',
			'POH' => 'Positive',
			'POM' => 'Positive',
			'PEN' => 'Pending',
			'RER' => 'ReRun',
			'INC' => 'Inconclusive',
			'PRP' => 'Positive',
			'INV' => 'Invalid',
			'NGE' => 'Negative',
			'REJ' => 'Rejected'
		);

		if (!isset($r->other_info)) {
			$other_info = array(
				'ORC_ID' => 33,
			);
		} else {
			$other_info = json_decode($r->other_info, true);
			if (!isset($other_info['PID3'])) {
				$other_info['PID3'] = '123456';
			}
			if (!isset($other_info['PID4'])) {
				$other_info['PID4'] = 'INSURANCE';
			}
		}

		$resend 	= $r->mo_status > 0;
		$assen 		= $r->assen;
		$batch_status = $r->batch_status;



		if (empty($this->options['msh4'])) {
			$this->options['msh4'] = $other_info['MSH6'];
		}

		$r->result_status = 'F';
		if ($resend) {
			$r->result_status = 'C';
		}
		$test_id = !empty($this->covid_test['te_loinc_code']) ? $this->covid_test['te_loinc_code'] : '94500-6';
		$test_name = !empty($this->covid_test['te_name']) ? $this->covid_test['te_name'] : 'nCoV-2019';

		$hl7_arr = array();
		$hl7_arr['MSH'] = $this->getMSH($r->mo_control_ID, $other_info['MSH6'], 'igniteLIS', 'ORU', 'R01', 'ORU_R01', 12, 'LABSOFT', $other_info['MSH4']);

		$hl7_arr['PID'] = $this->getPID(
			$r->mo_set_ID,
			$r->mo_patient_ID,
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
			$r->pa_email,
			$other_info['PID3'],
			'',
			$other_info['PID4']
		);

		$hl7_arr['ORC'] = $this->getORC(
			$other_info['ORC_ID'],
			$assen,
			$r->doc,
			$r->lab_name,
			$r->doc_npi,
			$r->doc_first,
			$r->doc_last
		);

		if (isset($this->options['nte']) && $this->options['nte']) {
			$hl7_arr['NTE'] = $this->getNTE('Corrected result date');
		}

		$bcodes = isset($other_info['OBR_code']) && count($other_info['OBR_code']) > 0 ? $other_info['OBR_code'] : array();
		$bnames = isset($other_info['OBR_names']) && count($other_info['OBR_names']) > 0 ? $other_info['OBR_names'] : array();

		$group_ids = array();
		$test_ids = array();
		$has_groupcode = false;
		$test_groups = array();
		$reflex_pass = array();
		$group_ids_ordered = array();

		if (count($bcodes) > 0) {

			foreach (explode(',', $r->panel_tests_requested) as $tid) {
				if (stripos($tid, 'group_') !== false) {
					$group_id = str_ireplace('GROUP_', '', $tid);
					if ($group_id > 0 && !in_array($group_id, $group_ids)) {
						$group_ids[] = $group_id;
						$group_ids_ordered[] = $group_id;
					}
				} else if ($tid > 0 && !in_array($tid, $test_ids)) {
					$test_ids[] = $tid;
				}
			}

			$drug_groups = tr_pdd_get_drug_groups('pcr', $group_ids, true);
			foreach ($drug_groups as $groupid => $groupdata) {

				if (!empty($groupdata['reflex_ID'])) {
					foreach ($groupdata['reflex_ID'] as $rlid) {
						if ($rlid > 0 && !in_array($rlid, $group_ids_ordered)) {
							$reflex_pass[$rlid] = false;
						}
					}
				}
			}

			foreach ($bcodes as $bcode) {


				$group = $wpdb->get_row("select * from ci_test_groups 
					where ( tg_cpt_code='{$bcode}' or tg_short_name = '{$bcode}' )
					and tg_type = '{$btype}' ");

				if ($group) {

					$bname = isset($bnames[$bcode]) ? $bnames[$bcode] : $group->tg_name;

					$groups_ids = $wpdb->get_col("select tg_ID from ci_test_groups
					where tg_ID = {$group->tg_ID} or tg_parent = {$group->tg_ID}");

					$groups_ids_str = implode(',', $groups_ids);
					$test_ids_str = implode(',', $test_ids);


					if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {

						if (!isset($test_groups[$bcode])) {
							$test_groups[$bcode] = array(
								'name' => $bname,
								'tests' => array()
							);

							$test_groups[$bcode]['tests'][] = array(
								'test_id' => $bcode,
								'test_name' => $bname,
								'pos-neg' => 'REJ'
							);
						}
					} else {
						if (!isset($test_groups[$bcode])) {
							$test_groups[$bcode] = array(
								'name' => $bname,
								'tests' => array()
							);
						}

						$where_gt = " and g.tg_ID in ({$groups_ids_str}) ";
						if (!$group->tg_only_group) {
							$where_gt .= " and te.te_ID in ({$test_ids_str}) ";
							var_dump($test_ids);
							echo '<br><br>';
						}

						$sql1 = "select 
						te.*,
						g.tg_ID, 
						g.tg_only_group,
						g.tg_is_covid,
						g.tg_name,
						g.tg_reflex_group_ID,
						g.tg_antibiotic,
						rs.*
						from ci_test_panels te
						JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
						JOIN ci_results_pcr rs ON (rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2) 
									and rs.report_id = {$r->id} and (rs.group_id = 0 or rs.group_id is null or rs.group_id  = te.te_group_id)
						where te_type = 'pcr' 
						{$where_gt}
						and te_in_req = 1
						group by te.te_ID ";

						$tests_rs = $wpdb->get_results($sql1, ARRAY_A);

						//var_dump($tests_rs);echo '<br><br>';
						if (count($tests_rs) > 0) {

							foreach ($tests_rs as $ti => $t) {

								$t['test_name'] = $t['te_name'];

								if ($t['tg_is_covid']) {
									$arr = trpdd_cal_ncovid19(array('te_name' => $t['te_name']), $r->id, $r->instrument, $r->created_date);
									$t['pos-neg'] = $arr['pos-neg'];
								} else if (empty($t['pos-neg'])) {
									$t['drug'] = $t['te_name'];
									$t['type_of_specimen'] = $r->type_of_specimen;
									$arr = tr_pdd_parse_pcr_test($t, $r->instrument, $t['tg_ID'], $r->created_date);
									$t['pos-neg'] = $arr['pos-neg'];
								}

								if (empty($t['pos-neg'])) {
									continue;
								}

								if (in_array($t['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVL', 'PVH'))) {
									$listreflexids = explode(',', $t['tg_reflex_group_ID']);
									foreach ($listreflexids as $reflexid) {
										if ($reflexid > 0  && isset($reflex_pass[$reflexid])) {
											$reflex_pass[$reflexid] = true;
											echo 'reflex pass by: ' . $t['te_name'] . '<br>';
										}
									}
								}


								$test_groups[$bcode]['tests'][] = array(
									'test_id' => $t['te_loinc_code'],
									'test_name' => $t['test_name'],
									'pos-neg' => $t['pos-neg'],
									'group_id' => $t['tg_ID'],
									'antibiotic' => $t['tg_antibiotic']
								);
							}
						}
					}
				}
			}
		}

		//remove $reflex_pass
		var_dump($reflex_pass);
		echo '<br>';
		if (count($reflex_pass) > 0) {
			foreach ($reflex_pass as $gid => $refpass) {
				if (!$refpass) {
					foreach ($test_groups as $bcode => $barr) {
						foreach ($barr['tests'] as $inum => $tarr) {
							if ($tarr['group_id'] == $gid && $tarr['antibiotic']) {

								unset($test_groups[$bcode]['tests'][$inum]);
								echo 'remove reflex: ' . $tarr['test_name'] . '<br>';
							}
						}
					}
				}
			}
		}


		foreach ($test_groups as $groupcode => $garr) {

			if (empty($garr['tests'])) {
				continue;
			}
			$has_groupcode = true;
			$hl7_arr[] = $this->getOBR(
				$other_info['ORC_ID'],
				$assen,
				$r->lab_name,
				$r->doc_npi,
				$r->doc_first,
				$r->doc_last,
				$groupcode,
				$garr['name'],
				$r->approve_date,
				$r->result_status,
				$r->mo_peg_ID,
				$r->doc,
				$r->spec_received
			);

			foreach ($garr['tests'] as $tarr) {
				$hl7_arr[] = $this->getOBX(
					$r->lab_clia,
					$r->lab_name,
					$tarr['test_id'],
					$tarr['test_name'],
					$pos_neg[$tarr['pos-neg']],
					$r->result_status,
					$r->approve_date,
					$r->lab_address,
					$r->lab_city,
					$r->lab_state,
					$r->lab_zip
				);
			}
		}

		if (!$has_groupcode) {

			if (isset($r->pos_neg)) {
				$arr = array('pos-neg' => $r->pos_neg);
			} else {
				$arr = trpdd_cal_ncovid19(array(), $r->id, $r->instrument, $r->created_date);
			}

			if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
				$arr['pos-neg'] = 'REJ';
			} else if (empty($arr['pos-neg']) && $arr['pos-neg'] == 'PEN') {
				return false;
			}

			$hl7_arr['OBR'] = $this->getOBR(
				$other_info['ORC_ID'],
				$assen,
				$r->lab_name,
				$r->doc_npi,
				$r->doc_first,
				$r->doc_last,
				$test_id,
				$test_name,
				$r->approve_date,
				$r->result_status,
				$r->mo_peg_ID,
				$r->doc,
				$r->spec_received
			);

			$hl7_arr['OBX'] = $this->getOBX(
				$r->lab_clia,
				$r->lab_name,
				$test_id,
				$test_name,
				$pos_neg[$arr['pos-neg']],
				$r->result_status,
				$r->approve_date,
				$r->lab_address,
				$r->lab_city,
				$r->lab_state,
				$r->lab_zip
			);
		}


		if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
			$nte_msg = 'Rejected';
			$batch_statuses = tr_pdd_get_batch_statuses(false, true, true, $btype);
			if (isset($batch_statuses[$batch_status])) {
				$nte_msg = $batch_statuses[$batch_status];
			}
			$hl7_arr['NTE'] = $this->getNTE($nte_msg);
		}

		$hl7_data = $this->array_to_hl7($hl7_arr);

		$ResultMessage = new ResultMessage();
		$ResultMessage->MessageID = $r->mo_test_ID;
		$ResultMessage->Message = $hl7_data;

		$logged = file_put_contents($this->log_dir . '/' . $r->mo_ID . '_rs.hl7', $hl7_data);


		return $ResultMessage;
	}
}
