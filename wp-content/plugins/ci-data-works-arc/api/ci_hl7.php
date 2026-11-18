<?php
class CI_Hl7
{
	const VT = 0x0B;
	const FS = 0x1C;
	const CR = 0x0D;
	var $is_Test = false;
	var $version = '2.5.1';
	var $inumbers = array();
	var $obx_loinc_456 = false;
	var $pdf_format = array();

	function hl7_to_array($string, $multis = array())
	{

		if (!is_array($multis)) $multis = array();

		$multis[] = 'OBX';
		$multis[] = 'DG1';

		$string = str_replace("\r\n", "\n", $string);
		$string = str_replace("\n", "\r", $string);
		$segs = explode("\r", $string);

		$out = array();

		//get delimiting characters

		if (substr($segs[0], 0, 3) != 'MSH') {

			$out['ERROR'][0] = 'Invalid HL7 Message.';
			$out['ERROR'][1] = 'Must start with MSH';

			return $out;
		}

		$delbarpos = strpos($segs[0], '|', 4);

		$delchar = substr($segs[0], 4, ($delbarpos - 4));
		$out['FLD_SEP'] = substr($delchar, 0, 1);
		$out['SFLD_SEP'] = substr($delchar, 1, 1);
		$out['REP_SEP'] = substr($delchar, 2, 1);
		$out['ESC_CHAR'] = substr($delchar, 3, 1);



		foreach ($segs as $fseg) {

			$fseg = trim($fseg);
			if (empty($fseg)) {
				continue;
			}
			$segments = explode('|', $fseg);

			$segname = $segments[0];
			$dup_segname = $segname;
			if (!in_array($segname, $multis) && isset($out[$segname])) {

				$dup_segname = $segname . '_' . $segments[1];
			}
			$i = 0;
			$ic = isset($out[$segname]) ? count($out[$segname]) : 0;
			foreach ($segments as $k => $seg) {

				$j = 1;

				$sf = explode($out['FLD_SEP'], $seg);
				foreach ($sf as $f) {

					if (strpos($f, $out['SFLD_SEP']) > 0) {
						$f = explode($out['SFLD_SEP'], $f);
						$f = $f[0];
					}

					if (in_array($segname, $multis)) {
						$out[$segname][$ic][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
					} else {
						$out[$dup_segname][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
					}


					$j++;
				}


				$i++;

				if ($k == 0 && $segname == 'MSH') {
					$out[$segname][$segname . '.' . $i][$segname . '.' . $i . '.1'] = '|';
					$i++;
				}
			}
		}


		return $out;
	}

	function array_to_hl7($arr)
	{
		$obr_num = 1;
		$obx_num = 1;
		$FLD_SEP = isset($arr['FLD_SEP']) && !empty($arr['FLD_SEP']) ? $arr['FLD_SEP'] : '^';
		$hl7 = array();
		foreach ($arr as $segnamei => $segments) {

			$segname = $segnamei;
			if (isset($segments[0])) {
				foreach ($segments as $inum => $seg) {

					if (is_numeric($segname)) {
						$fkeys = array_keys($seg);
						$segname = substr($fkeys[0], 0, 3);
					}

					$seg[$segname . '.1'][$segname . '.1.1'] = $inum + 1;

					foreach ($seg as $i => $item) {
						if (is_array($item)) {

							$item = implode($FLD_SEP, $item);
						}
						if ($i == $segname . '.0') {
							unset($seg[$i]);
							continue;
						}

						$seg[$i] = $item;
					}
					$hl7[] = $segname . '|' . implode('|', $seg);
				}
			} else {
				if (is_numeric($segname)) {
					$fkeys = array_keys($segments);
					$segname = substr($fkeys[0], 0, 3);
				}
				if ($segname == 'OBR') {
					$segments[$segname . '.1'][$segname . '.1.1'] = $obr_num++;
					//$obx_num = 1;
				} else if ($segname == 'OBX') {
					$segments[$segname . '.1'][$segname . '.1.1'] = $obx_num++;
				}

				foreach ($segments as $i => $item) {
					if (is_array($item)) {
						$item = implode($FLD_SEP, $item);
					}
					if (($segname == 'MSH' && $item == '|') || $i == $segname . '.0') {
						unset($segments[$i]);
						continue;
					}
					$segments[$i] = $item;
				}
				$hl7[] = $segname . '|' . implode('|', $segments);
			}
		}
		$hl7 = implode("\r", $hl7);
		return $hl7;
	}

	public function add_Missing($arr, $name, $max)
	{
		$default = array();
		for ($i = 0; $i <= $max; $i++) {
			$key = $name . '.' . $i;
			$default[$key] = array(
				$key . '.1' => ($i == 0 ? $name : '')
			);
		}

		foreach ($arr as $k => $vl) {
			$default[$k] = $vl;
		}
		return $default;
	}


	public function formatdate($date, $format = 'YmdHis')
	{
		if (!empty($date)) {
			$date = date($format, strtotime($date));
		}

		return $date;
	}


	public function getMSH($control_ID, $labClia, $labName, $msgType = 'ORU', $msgTypeid = 'R01', $msgTypeC = 'ORU_R01', $max = 15, $msh5 = '', $msh6 = '', $msh15 = '')
	{
		$this->inumbers = array();

		if (empty($msh6)) {
			$msh6 = 'igniteLIS';
		}
		if (empty($msh5)) {
			$msh5 = 'igniteLIS';
		}
		$datetime = date_i18n('YmdHis');
		$arr = array(

			'MSH.1' =>
			array(
				'MSH.1.1' => '|',
			),
			'MSH.2' =>
			array(
				'MSH.2.1' => '',
				'MSH.2.2' => '~\\&',
			),
			'MSH.3' =>
			array(
				'MSH.3.1' => $labName,
			),
			'MSH.4' =>
			array(
				'MSH.4.1' => $labClia,
			),
			'MSH.5' =>
			array(
				'MSH.5.1' => $msh5,
			),
			'MSH.6' =>
			array(
				'MSH.6.1' => $msh6,
			),
			'MSH.7' =>
			array(
				'MSH.7.1' => $datetime,
			),

			'MSH.9' =>
			array(
				'MSH.9.1' => $msgType,
				'MSH.9.2' => $msgTypeid,
				'MSH.9.3' => $msgTypeC,
			),
			'MSH.10' =>
			array(
				'MSH.10.1' => $control_ID,
			),
			'MSH.11' =>
			array(
				'MSH.11.1' => $this->is_Test ? 'T' : 'P',
			),
			'MSH.12' =>
			array(
				'MSH.12.1' => $this->version,
			),
			'MSH.15' =>
			array(
				'MSH.15.1' => $msh15,
			),
		);

		return $this->add_Missing($arr, 'MSH', $max > 0 ? $max : 15);
	}

	public function getPID(
		$setid,
		$paID,
		$first_name,
		$last_name,
		$ssn,
		$gender,
		$dob,
		$address,
		$city,
		$state,
		$zip,
		$phone,
		$email,
		$mrn = '',
		$race_code = '',
		$insu = '',
		$apid = '',
		$customarr = array()
	) {
		if (strpos($phone, '(') === false) {
			$phone = str_replace('-', '', $phone);
		}

		$dob = str_replace('-', '', $dob);

		$arr = array(
			'PID.1' => array(
				'PID.1.1' => $setid,
			),

			'PID.2' => array(
				'PID.2.1' => $paID,
			),

			'PID.3' => array(
				'PID.3.1' => $mrn,
			),

			'PID.4' => array(
				'PID.4.1' => $insu,
			),

			'PID.5' => array(
				'PID.5.1' => $last_name,
				'PID.5.2' => $first_name,
			),

			'PID.7' => array(
				'PID.7.1' => $dob,
			),

			'PID.8' => array(
				'PID.8.1' => $gender,
			),

			'PID.10' => array(
				'PID.10.1' => $race_code,
			),

			'PID.11' => array(
				'PID.11.1' => $address,
				'PID.11.2' => '',
				'PID.11.3' => $city,
				'PID.11.4' => $state,
				'PID.11.5' => $zip,
			),

			'PID.13' => array(
				'PID.13.1' => $phone,
				'PID.13.2' => '',
				'PID.13.3' => $email,
				'PID.13.4' => '',
			),

			'PID.16' => array(
				'PID.16.1' => 'T',
				'PID.16.2' => 'Unreported',
				'PID.16.3' => 'HL70002',
			),

			'PID.18' => array(
				'PID.18.1' => $apid,
			),

			'PID.19' => array(
				'PID.19.1' => $ssn,
			),

			'PID.22' => array(
				'PID.22.1' => 'U',
			),

			'PID.30' => array(
				'PID.30.1' => 'N',
			),

		);
		foreach ($customarr as $col => $vl) {
			$arr[$col] = $vl;
		}
		return $this->add_Missing($arr, 'PID', 30);
	}

	public function getORC($order_number, $accession, $doc, $labname, $doctor_npi, $doctor_first, $doctor_last)
	{
		$doc = $this->formatdate($doc);

		$arr = array(
			'ORC.1' => array(
				'ORC.1.1' => 'RE',
			),
			'ORC.2' => array(
				'ORC.2.1' => $order_number,
			),
			'ORC.3' => array(
				'ORC.3.1' => $accession,
				'ORC.3.2' => $labname,
			),
			'ORC.7' => array(
				'ORC.7.1' => '',
				'ORC.7.2' => '',
				'ORC.7.3' => '',
				'ORC.7.4' => '',
				'ORC.7.5' => '',
				'ORC.7.6' => 'R',
			),
			'ORC.9' => array(
				'ORC.9.1' => $doc,
			),
			'ORC.12' => array(
				'ORC.12.1' => $doctor_npi,
				'ORC.12.2' => $doctor_last,
				'ORC.12.3' => $doctor_first,
				'ORC.12.4' => '',
				'ORC.12.5' => '',
				'ORC.12.6' => '',
				'ORC.12.7' => '',
				'ORC.12.8' => '',
				'ORC.12.9' => '',
				'ORC.12.10' => '',
				'ORC.12.11' => '',
				'ORC.12.12' => '',
				'ORC.12.13' => '',
				'ORC.12.14' => '',
				'ORC.12.15' => 'NPI',

			),
		);
		return $this->add_Missing($arr, 'ORC', 12);
	}

	public function getPV1($npi, $doctor_first, $doctor_last, $assen = '')
	{
		$max = 17;
		$arr = array(
			'PV1.1' => array(
				'PV1.1.1' => '1',
			),
			'PV1.7' => array(
				'PV1.7.1' => $npi,
				'PV1.7.2' => $doctor_last,
				'PV1.7.3' => $doctor_first,
			),
			'PV1.8' => array(
				'PV1.8.1' => $npi,
				'PV1.8.2' => $doctor_last,
				'PV1.8.3' => $doctor_first,
			),
			'PV1.17' => array(
				'PV1.17.1' => $npi,
				'PV1.17.2' => $doctor_last,
				'PV1.17.3' => $doctor_first,
			),
		);
		if (!empty($assen)) {
			$arr['PV1.19']['PV1.19.1'] = $assen;
			$max = 19;
		}
		return $this->add_Missing($arr, 'PV1', $max);
	}

	public function getIN1($id, $company, $address, $city, $state, $zip, $phone, $first_name, $last_name, $group_id = '')
	{
		$arr = array(
			'IN1.1' => array(
				'IN1.1.1' => '1',
			),
			'IN1.2' => array(
				'IN1.2.1' => $id,
			),
			'IN1.4' => array(
				'IN1.4.1' => $company,
			),
			'IN1.5' => array(
				'IN1.5.1' => $address,
				'IN1.5.2' => '',
				'IN1.5.3' => $city,
				'IN1.5.4' => $state,
				'IN1.5.5' => $zip,
			),
			'IN1.7' => array(
				'IN1.7.1' => $phone,
			),
			'IN1.8' => array(
				'IN1.8.1' => $group_id,
			),
			'IN1.16' => array(
				'IN1.16.1' => $last_name,
				'IN1.16.2' => $first_name,
			),
			'IN1.36' => array(
				'IN1.36.1' => $id,
			),
		);
		return $this->add_Missing($arr, 'IN1', 36);
	}

	public function getFT1($doc, $diagnosis_code, $cpt_code = '', $qty = 1, $doctor_name = '', $departmentid = '', $dax_codes = '')
	{
		if (!isset($this->inumbers['FT1'])) {
			$this->inumbers['FT1'] = 0;
		}
		$this->inumbers['FT1']++;

		$doc = date('Ymd', strtotime($doc));
		if (is_array($cpt_code)) {
			$cpt_code = implode('^', $cpt_code);
		}
		if (is_array($diagnosis_code)) {
			$diagnosis_code = implode('~', $diagnosis_code);
		} else {
			$diagnosis_code = str_replace(',', '~', $diagnosis_code);
		}
		$arr = array(
			'FT1.1' => array(
				'FT1.1.1' => $this->inumbers['FT1'],
			),
			'FT1.4' => array(
				'FT1.4.1' => $doc,
			),
			'FT1.5' => array(
				'FT1.5.1' => $doc,
			),
			'FT1.6' => array(
				'FT1.6.1' => 'CG',
			),
			'FT1.10' => array(
				'FT1.10.1' => $qty,
			),
			'FT1.16' => array(
				'FT1.16.1' => $departmentid,
			),
			'FT1.19' => array(
				'FT1.19.1' => $diagnosis_code,
			),
			'FT1.20' => array(
				'FT1.20.1' => $doctor_name,
			),
			'FT1.25' => array(
				'FT1.25.1' => $cpt_code,
			),
			'FT1.26' => array(
				'FT1.26.1' => $dax_codes,
			),
		);
		return $this->add_Missing($arr, 'FT1', 26);
	}

	public function getDG1($codes, $names = array())
	{
		if (!is_array($codes)) {
			$codes = explode(',', $codes);
		}

		$lists = array();
		foreach ($codes as $i => $code) {
			$codename = isset($names[$code]) ? $names[$code] : $code;
			$codename = trim(str_replace($code, '', $codename));

			if ($codename == 'Select') {
				continue;
			}
			$arr = array(
				'DG1.0' => array(
					'DG1.0.1' => 'DG1'
				),
				'DG1.1' => array(
					'DG1.1.1' => $i + 1,
				),
				'DG1.2' => array(
					'DG1.2.1' => '',
				),
				'DG1.3' => array(
					'DG1.3.1' => $code,
					'DG1.3.2' => $codename,
					'DG1.3.3' => 'I10',
				),
			);
			$lists[] = $this->add_Missing($arr, 'DG1', 4);
		}

		return $lists;
	}



	public function getNTE($code)
	{
		$code = str_replace(array("\n", "\r", '|'), array('\n', '', '\|'), $code);
		$arr = array(
			'NTE.1' => array(
				'NTE.1.1' => '1',
			),
			'NTE.2' => array(
				'NTE.2.1' => 'P',
			),
			'NTE.3' => array(
				'NTE.3.1' => $code,
			),
		);
		return $this->add_Missing($arr, 'NTE', 3);
	}

	public function getNT1($note)
	{
		$arr = array(
			'NT1.1' =>
			array(
				'NT1.1.1' => '1',
			),
			'NT1.2' =>
			array(
				'NT1.2.1' => '',
			),
			'NT1.3' =>
			array(
				'NT1.3.1' => $note,
			),
		);
		return $this->add_Missing($arr, 'NT1', 2);
	}

	public function getOBR(
		$order_number,
		$accession,
		$lab_name,
		$doctor_npi,
		$doctor_first,
		$doctor_last,
		$test_id,
		$test_name,
		$resultdate,
		$result_status,
		$id13,
		$doc = '',
		$received = '',
		$namecode = 'X',
		$obr20 = '',
		$customarr = array()
	) {
		$doc = $this->formatdate($doc);
		$received = $this->formatdate($received);
		$resultdate = $this->formatdate($resultdate);

		$obr11 = 'N';
		if ($result_status == 'X') {
			$obr11 = 'X';
		}

		$arr = array(
			'OBR.1' =>
			array(
				'OBR.1.1' => '1',
			),
			'OBR.2' =>
			array(
				'OBR.2.1' => $order_number,
			),
			'OBR.3' =>
			array(
				'OBR.3.1' => $accession,
				'OBR.3.2' => $lab_name,
			),
			'OBR.4' =>
			array(
				'OBR.4.1' => $test_id,
				'OBR.4.2' => $test_name,
				'OBR.4.3' => $namecode,
			),
			'OBR.7' =>
			array(
				'OBR.7.1' => $doc,
			),
			'OBR.11' =>
			array(
				'OBR.11.1' => $obr11,
			),
			'OBR.13' =>
			array(
				'OBR.13.1' => $id13,
			),
			'OBR.14' =>
			array(
				'OBR.14.1' => $received,
			),
			'OBR.16' =>
			array(
				'OBR.16.1' => $doctor_npi,
				'OBR.16.2' => $doctor_last,
				'OBR.16.3' => $doctor_first,
				'OBR.16.4' => '',
				'OBR.16.5' => '',
				'OBR.16.6' => '',
				'OBR.16.7' => '',
				'OBR.16.8' => '',
				'OBR.16.9' => '',
				'OBR.16.10' => '',
				'OBR.16.11' => '',
				'OBR.16.12' => '',
				'OBR.16.13' => 'NPI',
			),
			'OBR.20' =>
			array(
				'OBR.20.1' => $obr20,
			),
			'OBR.22' =>
			array(
				'OBR.22.1' => $resultdate,
			),
			'OBR.24' =>
			array(
				'OBR.24.1' => 'LAB',
			),
			'OBR.25' =>
			array(
				'OBR.25.1' => $result_status,
			),
		);

		if (empty($accession)) {
			unset($arr['OBR.3']['OBR.3.2']);
		}
		foreach ($customarr as $col => $vl) {
			$arr[$col] = $vl;
		}
		return $this->add_Missing($arr, 'OBR', 25);
	}


	public function getOBX(
		$lab_clia,
		$lab_name,
		$loinc_code,
		$loinc_desc,
		$pos_neg,
		$result_status,
		$result_date = '',
		$lab_address = '',
		$lab_city = '',
		$lab_state = '',
		$lab_zip = '',
		$pdf_file = '',
		$otherarr = array()
	) {
		$result_date = $this->formatdate($result_date);

		$obx5 = isset($otherarr['obx5']) ? $otherarr['obx5'] : $pos_neg;
		$obx6 = isset($otherarr['obx6']) ? $otherarr['obx6'] : '';
		$obx7 = isset($otherarr['obx7']) ? $otherarr['obx7'] : '';
		$obx2 = isset($otherarr['obx2']) ? $otherarr['obx2'] : 'ST';

		if (isset($otherarr['obx8'])) {
			$obx8 = $otherarr['obx8'];
		} else {
			$obx8 = stripos($pos_neg, 'pos') !== false
				|| stripos($pos_neg, 'high') !== false
				|| stripos($pos_neg, 'low') !== false
				|| stripos($pos_neg, 'medium') !== false
				|| strtolower($pos_neg) == 'detected'
				|| stripos($pos_neg, 'very') !== false
				|| strtolower($pos_neg) == 'abnormal'
				|| strtolower($pos_neg) == 'rejected' ? 'A' : 'N';
		}


		if (stripos($pos_neg, 'rejected') !== false) {
			$obx8 = 'U';
		}
		if (stripos($pos_neg, 'Indeterminate') !== false) {
			$obx8 = 'A';
		}

		$arr = array(
			'OBX.1' =>
			array(
				'OBX.1.1' => '1',
			),
			'OBX.2' => array(
				'OBX.2.1' => $obx2,
			),
			'OBX.3' =>
			array(
				'OBX.3.1' => $loinc_code,
				'OBX.3.2' => $loinc_desc,
				'OBX.3.3' => 'LN',
			),
			'OBX.5' => array(
				'OBX.5.1' => $obx5,
			),
			'OBX.6' => array(
				'OBX.6.1' => $obx6,
			),
			'OBX.7' => array(
				'OBX.7.1' => $obx7,
			),
			'OBX.8' => array(
				'OBX.8.1' => $obx8,
			),
			'OBX.11' => array(
				'OBX.11.1' => $result_status,
			),
			'OBX.14' => array(
				'OBX.14.1' => $result_date,
			),

		);

		if (!empty($pdf_file)) {
			$arr['OBX.2']['OBX.2.1'] = $this->pdf_format['OBX.2.1'] ?? 'ED';
			if (isset($this->pdf_format['OBX.3.1'])) {
				$arr['OBX.3']['OBX.3.1'] = $this->pdf_format['OBX.3.1'];
			}
			if (isset($this->pdf_format['OBX.3.2'])) {
				$arr['OBX.3']['OBX.3.2'] = $this->pdf_format['OBX.3.2'];
			}
			$arr['OBX.5']['OBX.5.1'] = $this->pdf_format['OBX.5.1'] ?? '';
			$arr['OBX.5']['OBX.5.2'] =  $this->pdf_format['OBX.5.2'] ?? 'PDF';
			$arr['OBX.5']['OBX.5.3'] = $this->pdf_format['OBX.5.3'] ?? '';
			$arr['OBX.5']['OBX.5.4'] = $this->pdf_format['OBX.5.4'] ?? 'Base64';

			if ($arr['OBX.5']['OBX.5.3'] == 'content') {
				unset($arr['OBX.5']['OBX.5.4']);
				$arr['OBX.5']['OBX.5.3'] = base64_encode(file_get_contents($pdf_file));
			} else {
				$arr['OBX.5']['OBX.5.5'] = base64_encode(file_get_contents($pdf_file));
			}
			unset($arr['OBX.3']['OBX.3.3']);
		} else  if ($this->obx_loinc_456) {
			$arr['OBX.3']['OBX.3.1'] = '';
			$arr['OBX.3']['OBX.3.2'] = '';
			$arr['OBX.3']['OBX.3.3'] = '';
			$arr['OBX.3']['OBX.3.4'] = $loinc_code;
			$arr['OBX.3']['OBX.3.5'] = $loinc_desc;
			$arr['OBX.3']['OBX.3.6'] = 'LN';
		}

		if (isset($otherarr['obx3'])) {
			$arr['OBX.3'] = $otherarr['obx3'];
		}
		$max = 14;
		if (isset($otherarr['obx23'])) {
			$arr['OBX.23'] = $otherarr['obx23'];
			$max = 23;
		}
		return $this->add_Missing($arr, 'OBX', $max);
	}


	public function getSPM($id, $specimen_id, $specimen_type, $doc)
	{
		$doc = $this->formatdate($doc);
		$arr = array(
			'SPM.1' =>
			array(
				'SPM.1.1' => $id,
			),
			'SPM.2' =>
			array(
				'SPM.2.1' => $specimen_id,
			),

			'SPM.4' =>
			array(
				'SPM.4.1' => $specimen_type,
			),
			'SPM.17' =>
			array(
				'SPM.17.1' => $doc,
			),
			'SPM.18' =>
			array(
				'SPM.18.1' => $doc,
			),
		);
		return $this->add_Missing($arr, 'SPM', 18);
	}


	public function getEVN($typc, $EventReasonCode, $OperatorID)
	{
		$datetime = date_i18n('YmdHis');
		$arr = array(
			'EVN.1' =>
			array(
				'EVN.1.1' => $typc,
			),
			'EVN.2' =>
			array(
				'EVN.2.1' => $datetime,
			),
			'EVN.4' =>
			array(
				'EVN.4.1' => $EventReasonCode,
			),
			'EVN.5' =>
			array(
				'EVN.5.1' => $OperatorID,
			),
		);
		return $this->add_Missing($arr, 'EVN', 5);
	}

	public function getGT1(
		$first_name,
		$last_name,
		$ssn,
		$gender,
		$dob,
		$address,
		$city,
		$state,
		$zip,
		$phone,
		$email
	) {

		$arr = array(
			'GT1.1' => array(
				'GT1.1.1' => 1,
			),
			'GT1.3' => array(
				'GT1.3.1' => $last_name,
				'GT1.3.1' => $first_name,
			),

			'GT1.5' => array(
				'GT1.5.1' => $address,
				'GT1.5.2' => '',
				'GT1.5.3' => $city,
				'GT1.5.4' => $state,
				'GT1.5.5' => $zip,
			),
			'GT1.6' => array(
				'GT1.6.1' => $phone,
			),
			'GT1.8' => array(
				'GT1.8.1' => $dob,
			),
			'GT1.11' => array(
				'GT1.11.1' => 'Self',
			),

		);
		return $this->add_Missing($arr, 'GT1', 11);
	}
}
