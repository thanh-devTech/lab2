<?php
class CI_Hl7{
	const VT = 0x0B;
	const FS = 0x1C;
	const CR = 0x0D;
	var $is_Test = false;
	var $version = '2.5.1';

	function hl7_to_array($string){

		$multis = array('OBX');

		$string = str_replace("\r\n","\n",$string);
		$string = str_replace("\n","\r",$string);
		$segs = explode("\r",$string);

		$out = array();

		//get delimiting characters

		if (substr($segs[0],0,3) != 'MSH') {

			$out['ERROR'][0] = 'Invalid HL7 Message.';
			$out['ERROR'][1] = 'Must start with MSH';

			return $out;
		}

		$delbarpos = strpos($segs[0],'|',4);

		$delchar = substr($segs[0],4,($delbarpos - 4));
		$out['FLD_SEP'] = substr($delchar,0,1);
		$out['SFLD_SEP'] = substr($delchar,1,1);
		$out['REP_SEP'] = substr($delchar,2,1);
		$out['ESC_CHAR'] = substr($delchar,3,1);

		foreach($segs as $fseg) {

			$fseg = trim($fseg);
			if(empty($fseg))
			{
				continue;
			}
			$segments = explode('|',$fseg);

			$segname = $segments[0];
			$i = 0;
			$ic = isset($out[$segname])? count($out[$segname]) : 0;
			foreach ($segments as $k => $seg) {

				$j=1;
				$sf = explode($out['FLD_SEP'],$seg);
				foreach($sf as $f) {
					if(in_array($segname, $multis))
					{
						$out[$segname][$ic][$segname.'.'.$i][$segname.'.'.$i.'.'.$j] = $f;
					}
					else{
						$out[$segname][$segname.'.'.$i][$segname.'.'.$i.'.'.$j] = $f;
					}

					$j++;
				}
				$i++;

				if($k==0 && $segname=='MSH')
				{
					$out[$segname][$segname.'.'.$i][$segname.'.'.$i.'.1'] = '|';
					$i++;
				}
			}
		}


		return $out;
	}

	function array_to_hl7($arr)
	{
		$FLD_SEP = isset($arr['FLD_SEP']) && !empty($arr['FLD_SEP'])? $arr['FLD_SEP'] : '^';
		$hl7 = array();
		foreach($arr as $segname => $segments)
		{
			if(isset($segments[0])){
				foreach($segments as $seg)
				{
					foreach($seg as $i => $item)
					{
						if(is_array($item))
						{
							$item = implode($FLD_SEP, $item);
						}
						if($i==$segname.'.0')
						{
							unset($seg[$i]);
							continue;
						}
						$seg[$i] = $item;
					}
					$hl7[] = $segname.'|'.implode('|',$seg);
				}
			}else{
				foreach($segments as $i => $item)
				{
					if(is_array($item))
					{
						$item = implode($FLD_SEP, $item);
					}
					if(($segname=='MSH' && $item=='|') || $i==$segname.'.0'){
						unset($segments[$i]);
						continue;
					}
					$segments[$i] = $item;
				}
				$hl7[] = $segname.'|'. implode('|',$segments);
			}

		}
		$hl7 = implode("\r", $hl7);
		return $hl7;
	}

	public function add_Missing($arr,$name,$max)
	{
		$default = array();
		for($i=0;$i<=$max;$i++)
		{
			$key = $name.'.'.$i;
			$default[$key] = array(
				$key.'.1' => ($i==0?$name:'')
			);
		}

		foreach($arr as $k =>$vl)
		{
			$default[$k] = $vl;
		}
		return $default;
	}


	public function formatdate($date,$format='YmdHis')
	{
		if(!empty($date))
		{
			$date = date($format,strtotime($date));
		}

		return $date;
	}


	public function getMSH($control_ID,$labClia,$labName,$msgType='ORU',$msgTypeid='R01', $msgTypeC='ORU_R01',$max=15,
		$msh5='',$msh6='',$msh15='',$msh16='',$msh17='')
	{
		if(empty($msh6))
		{
			$msh6 = 'igniteLIS';
		}
		if(empty($msh5))
		{
			$msh5 ='igniteLIS';
		}
		$datetime = date_i18n('YmdHis');
		$arr = array (

			'MSH.1' =>
				array (
					'MSH.1.1' => '|',
				),
			'MSH.2' =>
				array (
					'MSH.2.1' => '',
					'MSH.2.2' => '~\\&',
				),
			'MSH.3' =>
				array (
					'MSH.3.1' => $labName,
				),
			'MSH.4' =>
				array (
					'MSH.4.1' => $labClia,
				),
			'MSH.5' =>
				array (
					'MSH.5.1' => $msh5,
				),
			'MSH.6' =>
				array (
					'MSH.6.1' => $msh6,
				),
			'MSH.7' =>
				array (
					'MSH.7.1' => $datetime,
				),

			'MSH.9' =>
				array (
					'MSH.9.1' => $msgType,
					'MSH.9.2' => $msgTypeid,
					'MSH.9.3' => $msgTypeC,
				),
			'MSH.10' =>
				array (
					'MSH.10.1' => $control_ID,
				),
			'MSH.11' =>
				array (
					'MSH.11.1' => $this->is_Test? 'T':'P',
				),
			'MSH.12' =>
				array (
					'MSH.12.1' => $this->version,
				),
			'MSH.15' =>
				array (
					'MSH.15.1' => $msh15,
				),
		);
		if(!empty($msh16))
		{
			$arr['MSH.16']= array(
				'MSH.16.1' => $msh16
			);
		}
		if(!empty($msh17))
		{
			$arr['MSH.17']= array(
				'MSH.17.1' => $msh17
			);
		}

		return $this->add_Missing($arr,'MSH',$max>0? $max: 15);
	}

	public function getPID($setid,$paID,$mrn,$first_name,$last_name,$ssn,$gender,$dob,$address,
		$city,$state,$zip,$phone,$email,$race_code='',$insu='')
	{
		$phone = str_replace('-','',$phone);
		$dob = str_replace('-','',$dob);

		$arr = array (
			'PID.1' =>
				array (
					'PID.1.1' => $setid,
				),
			'PID.2' =>
				array (
					'PID.2.1' => $paID,
				),
			'PID.3' =>
				array (
					'PID.3.1' => $mrn,
				),
			'PID.4' =>
				array (
					'PID.4.1' => $insu,
				),
			'PID.5' =>
				array (
					'PID.5.1' => $last_name,
					'PID.5.2' => $first_name,
				),
			'PID.7' =>
				array (
					'PID.7.1' => $dob,
				),
			'PID.8' =>
				array (
					'PID.8.1' => $gender,
				),
			'PID.11' =>
				array (
					'PID.11.1' => $address,
					'PID.11.2' => $city,
					'PID.11.3' => $state,
					'PID.11.4' => $zip,
				),
			'PID.13' =>
				array (
					'PID.13.1' => $phone,
					'PID.13.2' => 'PRN',
					'PID.13.3' => 'PH',
					'PID.13.4' => $email,
				),
			'PID.19' =>
				array(
					'PID.19.1' => $ssn,
				),
			'PID.22' =>
				array (
					'PID.22.1' => 'U',
				),
			'PID.30' =>
				array (
					'PID.30.1' => 'N',
				),
		);
		return $this->add_Missing($arr,'PID',30);
	}

	public function getORC($order_number,$accession,$doc,$labname,$doctor_npi,$doctor_first,$doctor_last)
	{
		$doc = $this->formatdate($doc);

		$arr = array (
			'ORC.1' =>
				array (
					'ORC.1.1' => 'RE',
				),
			'ORC.2' =>
				array (
					'ORC.2.1' => $order_number,
				),
			'ORC.3' =>
				array (
					'ORC.3.1' => $accession,
					'ORC.3.2' => $labname,
				),
			'ORC.7' =>
				array (
					'ORC.7.1' => '',
					'ORC.7.2' => '',
					'ORC.7.3' => '',
					'ORC.7.4' => '',
					'ORC.7.5' => '',
					'ORC.7.6' => 'R',
				),
			'ORC.9' =>
				array (
					'ORC.9.1' => $doc,
				),
			'ORC.12' =>
				array (
					'ORC.12.1' => $doctor_npi,
					'ORC.12.2' => $doctor_first,
					'ORC.12.3' => $doctor_last,
				),
		);
		return $this->add_Missing($arr,'ORC',12);
	}

	public function getNTE($code)
	{
		$arr = array (
			'NTE.1' =>
				array (
					'NTE.1.1' => '1',
				),
			'NTE.2' =>
				array (
					'NTE.2.1' => 'P',
				),
			'NTE.3' =>
				array (
					'NTE.3.1' => $code,
				),
		);
		return $this->add_Missing($arr,'NTE',3);
	}

	public function getOBR($order_number, $accession,$lab_name,$doctor_npi,$doctor_first,$doctor_last,
		$test_id,$test_name,$resultdate,$result_status,$id13,$doc='',$received='')
	{
		$doc = $this->formatdate($doc);
		$received = $this->formatdate($received);
		$resultdate = $this->formatdate($resultdate);

		$arr = array (
			'OBR.1' =>
				array (
					'OBR.1.1' => '1',
				),
			'OBR.2' =>
				array (
					'OBR.2.1' => $order_number,
				),
			'OBR.3' =>
				array (
					'OBR.3.1' => $accession,
					'OBR.3.2' => $lab_name,
				),
			'OBR.4' =>
				array (
					'OBR.4.1' => $test_id,
					'OBR.4.2' => $test_name,
					'OBR.4.3' => 'X',
				),
			'OBR.7' =>
				array (
					'OBR.7.1' => $doc,
				),
			'OBR.11' =>
				array (
					'OBR.11.1' => 'N',
				),
			'OBR.13' =>
				array (
					'OBR.13.1' => $id13,
				),
			'OBR.14' =>
				array (
					'OBR.14.1' => $received,
				),
			'OBR.16' =>
				array (
					'OBR.16.1' => $doctor_npi,
					'OBR.16.2' => $doctor_first,
					'OBR.16.3' => $doctor_last,
				),
			'OBR.22' =>
				array (
					'OBR.22.1' => $resultdate,
				),
			'OBR.25' =>
				array (
					'OBR.25.1' => $result_status,
				),
		);
		return $this->add_Missing($arr,'OBR',25);
	}


	public function getOBX($lab_clia,$lab_name,$loinc_code,$loinc_desc,$pos_neg,$result_status,$result_date='',
		$lab_address='',$lab_city='',$lab_state='',$lab_zip='')
	{
		$result_date = $this->formatdate($result_date);

		$obx8 = stripos($pos_neg,'pos')!==false
		        || strtolower($pos_neg)=='detected'
		        || strtolower($pos_neg)=='abnormal'? 'A':'N';

		$arr = array (
			'OBX.1' =>
				array (
					'OBX.1.1' => '1',
				),
			'OBX.2' =>
				array (
					'OBX.2.1' => 'ST',
				),
			'OBX.3' =>
				array (
					'OBX.3.1' => $loinc_code,
					'OBX.3.2' => $loinc_desc,
					'OBX.3.3' => 'LN',
				),
			'OBX.5' =>
				array (
					'OBX.5.1' => $pos_neg,
				),
			'OBX.8' =>
				array (
					'OBX.8.1' => $obx8,
				),
			'OBX.11' =>
				array (
					'OBX.11.1' => $result_status,
				),
			'OBX.14' =>
				array (
					'OBX.14.1' => $result_date,
				),
			'OBX.15' =>
				array (
					'OBX.15.1' => $lab_clia,
					'OBX.15.2' => $lab_name,
					'OBX.15.3' => 'CLIA',
					'OBX.15.4' => $lab_address,
					'OBX.15.5' => $lab_city,
					'OBX.15.6' => $lab_state,
					'OBX.15.7' => $lab_zip,
				),

		);

		return $this->add_Missing($arr,'OBX',15);
	}


	public function getSPM($id,$specimen_id,$specimen_type,$doc)
	{
		$doc = $this->formatdate($doc);
		$arr = array (
			'SPM.1' =>
				array (
					'SPM.1.1' => $id,
				),
			'SPM.2' =>
				array (
					'SPM.2.1' => $specimen_id,
				),

			'SPM.4' =>
				array (
					'SPM.4.1' => $specimen_type,
				),
			'SPM.17' =>
				array (
					'SPM.17.1' => $doc,
				),
			'SPM.18' =>
				array (
					'SPM.18.1' => $doc,
				),
		);
		return $this->add_Missing($arr,'SPM',18);
	}

	public function getEVN($id,$doc)
	{
		$doc = $this->formatdate($doc);
		$arr = array (
			'EVN.1' =>
				array (
					'EVN.1.1' => $id,
				),
			'EVN.2' =>
				array (
					'EVN.2.1' => $doc,
				),

		);
		return $this->add_Missing($arr,'EVN',5);
	}

	public function getPV1($id,$code,$location,$doc_id, $doc_first, $doc_last)
	{
		$arr = array (
			'PV1.1' =>
				array (
					'PV1.1.1' => $id,
				),
			'PV1.2' =>
				array (
					'PV1.2.1' => $code,
				),
			'PV1.3' =>
				array (
					'PV1.3.1' => '',
					'PV1.3.2' => '',
					'PV1.3.3' => '',
					'PV1.3.4' => $location,
				),
			'PV1.7' =>
				array (
					'PV1.7.1' => $doc_id,
					'PV1.7.2' => $doc_last,
					'PV1.7.3' => $doc_first,
				),

		);
		return $this->add_Missing($arr,'PV1',40);
	}

	public function getIN1($id,$plan_id,$mnemonic,$ins_name,
		$ins_address, $ins_city, $ins_state, $ins_zip,$ins_phone,
		$plan_type='P', $ins_first, $ins_last, $ins_Relationship='01',$member_id)
	{
		$arr = array (
			'IN1.1' =>
				array (
					'IN1.1.1' => $id,
				),
			'IN1.2' =>
				array (
					'IN1.2.1' => $plan_id,
				),
			'IN1.3' =>
				array (
					'IN1.3.1' => $mnemonic,
				),
			'IN1.4' =>
				array (
					'IN1.4.1' => $ins_name,
				),
			'IN1.5' =>
				array (
					'IN1.5.1' => $ins_address,
					'PV1.5.2' => $ins_city,
					'PV1.5.3' => $ins_state,
					'PV1.5.4' => $ins_zip,
				),
			'IN1.7' =>
				array (
					'IN1.7.1' => $ins_phone,
				),
			'IN1.15' =>
				array (
					'IN1.15.1' => $plan_type,
				),
			'IN1.16' =>
				array (
					'IN1.16.1' => $ins_last,
					'IN1.16.2' => $ins_first,
				),
			'IN1.17' =>
				array (
					'IN1.17.1' => $ins_Relationship,
				),

			'IN1.36' =>
				array (
					'IN1.36.1' => $member_id,
				),

		);
		return $this->add_Missing($arr,'IN1',41);
	}
}