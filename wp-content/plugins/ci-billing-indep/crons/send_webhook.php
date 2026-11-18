<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
global $wsdl_apis_info;
global $flog;

$send_multiple = false;


function tr_send_webhoook($rowdata)
{
	global $wsdl_apis_info;
	global $flog;

	$jsondata = json_encode($rowdata);

	$headers = array('Content-Type' => 'application/json; charset=utf-8');
	if (!empty($wsdl_apis_info['billing_webhook_header'])) {
		foreach (explode("\n", $wsdl_apis_info['billing_webhook_header']) as $hdl) {
			$hdl = trim($hdl);
			$arrk = explode(':', $hdl);
			if (isset($arrk[1])) {
				$headers[$arrk[0]] = $arrk[1];
			}
		}
	}
	//$wsdl_apis_info['billing_webhook'] = 'https://api.webhue.net/raw.php';

	$response = wp_remote_post($wsdl_apis_info['billing_webhook'], array(
		'headers'     => $headers,
		'body'        => $jsondata,
		'method'      => 'POST',
		'data_format' => 'body',
	));

	if (isset($_GET['debug'])) {
		var_dump($response);
	}
	$response_body = !is_wp_error($response) ? $response['body'] : 'error';

	if ($flog) {
		fwrite($flog, date_i18n('Y-m-d H:i:s') . "\n");
		fwrite($flog, $wsdl_apis_info['billing_webhook'] . "\n\n");
		fwrite($flog, $jsondata . "\n\n");
		fwrite($flog, $response_body . "\n\n\n\n");
	}


	return $response_body;
}

//$id,$btype;
$wsdl_apis_info = get_option('tr_wsdl_apis_info_sts', array());
if (!isset($wsdl_apis_info['billing_webhook']) || empty($wsdl_apis_info['billing_webhook'])) {
	echo 'no webhook url';
	return;
}

$logfile 	= WP_CONTENT_DIR . '/log/billing_webhook.txt';
$flog 		= fopen($logfile, 'a+');


$limitdate 	= isset($_GET['limitdate']) ? $_GET['limitdate'] : date_i18n('Y-m-d', time() - 86400 * 3);
$limitdate = '2025-01-01';

$btypes 	= array($btype);
if (empty($id)) {
	$btypes = tr_pdd_get_batch_types();
}

$limit 		= 10;
$settings       = tr_billing_get_settings();
$races = array(
	'' => '',
	'1002-5' => 'American Indian or Alaska  Native',
	'2028-9' => 'Asian',
	'2054-5' => 'Black / African American',
	'2076-8' => 'Native Hawaiian or Other  Pacific Islander',
	'2106-3' => 'White',
	'2131-1' => 'Other'
);
$Ethnicitis = array(
	''  => '',
	'H' => 'Hispanic or Latino',
	'N' => 'Not Hispanic or Latino',
	'U' => 'Unknown'
);


foreach ($btypes as $btype) {
	$groups             = array();
	$rs = $wpdb->get_results("select tg_ID, tg_name, 
								tg_notestcheckbox, tg_only_group, tg_is_covid
								from ci_test_groups 
								where tg_type in( '{$btype}')
								");
	foreach ($rs as $r) {
		$groups[$r->tg_ID] = $r->tg_name;
		if ($r->tg_notestcheckbox || $r->tg_only_group || $r->tg_is_covid == 1) {
			$groups_notestcb[$r->tg_ID] = array();
		}
		if ($r->tg_is_covid == 1) {
			$covid_group_ids[] = $r->tg_ID;
		}
	}

	$rs = $wpdb->get_results("select te_ID,te_name,te_abrev, te_abrev2, te_group_id
											from ci_test_panels 
											where te_type in( '{$btype}') and te_in_req = 1 and te_is_control = 0");
	$all_tests = array();
	foreach ($rs as $r) {
		$all_tests[$r->te_ID] = $r;

		if (isset($groups_notestcb[$r->te_group_id])) {
			$groups_notestcb[$r->te_group_id][] = $r->te_ID;
		}
	}
	$where = " and re.estatus= 'C' and  (ao.mo_ID is null or ao.mo_status !=1) ";

	$where .= " and " . tr_pdd_where_batch_rejected(false, true) . ' ';


	if ($id > 0) {
		$where .= " and re.id = {$id} ";
	} else {

		$where .= " and re.approve_date >= '{$limitdate}' ";
		$limit = isset($_GET['debug']) ? 1 : 10;
	}
	$where .= " and (ins.ins_company != '' and ins.ins_company != 'No Insurance - Cash' and ins.ins_company is not null)";


	$batch_tbl 	= tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$sql = "select 
				ba.dagnostic_codes1,
				ba.assen,
				ba.mid,
				ba.requesting_practice,
				ba.panel_tests_requested,
				ba.doc,
				ba.cli_ID,
				ba.insurance,
	
				ins.ins_member_id,
				ins.ins_group_id,
				ins.ins_relation,
				ins.ins_last,
				ins.ins_first,
				ins.ins_dob,
				ins.ins_address,
				ins.ins_city,
				ins.ins_state,
				ins.ins_zip,
	
				pal.payer_mnemonic,
	
				ins2.ins_company ins_company2,
				ins2.ins_member_id ins_member_id2,
				ins2.ins_group_id ins_group_id2,
				ins2.ins_relation ins_relation2,
				ins2.ins_last ins_last2,
				ins2.ins_first ins_first2,
				ins2.ins_dob ins_dob2,
				ins2.ins_address ins_address2,
				ins2.ins_city ins_city2,
				ins2.ins_state ins_state2,
				ins2.ins_zip ins_zip2,
	
				pal2.payer_mnemonic as payer_mnemonic2,
				
				pa.*,
				re.id,
				re.approve_date,
	
				cli.cli_facility_code,
				cli.cli_city,
				lab.lab_ID,
				lab.lab_name,
	
				doc.doc_last,
				doc.doc_first,
				doc.doc_npi,
				doc.doc_phone,
	
				'{$btype}' as btype,
				ao.mo_ID
		
			from {$batch_tbl} ba
			left join {$report_tbl} re ON  ba.assen = re.patient_id  
			join ci_patients pa ON pa.pa_ID = ba.patient_id
			join ci_doc doc ON doc.doc_ID = ba.doc_ID
			join ci_clinic cli On cli.cID = ba.cli_ID
			join ci_labs lab On lab.lab_ID = ba.lab_ID
			left join ci_insurances ins ON ins.ins_ID = ba.insurance1
			left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
			left join ci_payer_list pal ON pal.payer_name = ba.insurance
			left join ci_payer_list pal2 ON pal2.payer_name = ins2.ins_company
			LEFT JOIN ci_sync_api_orders ao ON ao.mo_btype = '{$btype}' 
						and ba.mid = ao.mo_mid 
						and ao.mo_file_id = -47
			where 1=1 {$where} 
			group by ba.mid 
			order by ba.mid desc
			limit {$limit}";


	$rs = $wpdb->get_results($sql);

	echo $wpdb->last_error . '<br>';
	echo 'count:' . count($rs) . '<br>';
	if (isset($_GET['debug'])) {
		echo $sql . '<br>';
	}


	$mids 		= array();
	$datarows 	= array();

	foreach ($rs as $i => $row) {
		$referring_provider_code = substr($row->doc_last, 0, 5) . substr($row->doc_first, 0, 1);
		$referring_provider_code = strtoupper($referring_provider_code);

		$cpt_code = tr_billing_get_cpt_code($row);
		//if (empty($cpt_code)) continue;

		if (empty($row->approve_date)) {
			$row->approve_date = date('Y-m-d', strtotime('+1day', strtotime($row->doc)));
		}

		$parent_name = '';
		$parent_dob = '';
		if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
			$parent_name = $row->ins_last . ', ' . $row->ins_first;
			$parent_dob = $row->ins_dob;

			if (!empty($parent_dob)) {
				$parent_dob = date('m/d/Y', strtotime($parent_dob));
			}
		}


		$testes_resulted = explode('|', strtoupper($row->testes_resulted));
		if (in_array($row->btype, array('pcr', 'blood'))) {
			$testids = explode(',', $row->panel_tests_requested);
			$panel = array();
			$groupids = array();
			foreach ($testids as $testid) {
				$testid = trim($testid);
				$testid = str_replace('+', '', $testid);
				if (stripos($testid, 'group_') !== false) {
					$group_id = str_ireplace('group_', '', $testid);
					if (!in_array($group_id, $groupids) && isset($groups[$group_id])) {
						$panel[] =  $groups[$group_id];
						$groupids[] = $group_id;
					}
				} else if (isset($all_tests[$testid])) {
					if (
						in_array(strtoupper($all_tests[$testid]->te_name), $testes_resulted)
						|| in_array(strtoupper($all_tests[$testid]->te_abrev), $testes_resulted)
						|| in_array(strtoupper($all_tests[$testid]->te_abrev2), $testes_resulted)
					) {
						$group_id = $all_tests[$testid]->te_group_id;
						if ($group_id > 0 && !in_array($group_id, $groupids) && isset($groups[$group_id])) {
							$panel[] = $groups[$group_id];
							$groupids[] = $group_id;
						}
						$panel[] = $all_tests[$testid]->te_name;
					}
				}
			}
			$panel = implode(', ', $panel);
		} else {
			$panel = $row->panel_tests_requested;
		}


		$rowdata = array(
			'order_number' 		=> $row->assen,
			'ignite_lis' 		=> $row->assen,
			'facility' 			=> $row->requesting_practice,
			'facility_code' 	=> $row->cli_facility_code,
			'facility_city' 	=> $row->cli_city,
			'clinic_city' 		=> $row->cli_city,

			'dagnostic_codes' 	=> $row->dagnostic_codes1,

			'patient_full_name' => $row->pa_last . ',' . $row->pa_first,
			'patient_first_name' => $row->pa_first,
			'patient_last_name' => $row->pa_last,
			'patient_address' 	=> $row->pa_address,
			'patient_city' 		=> $row->pa_city,
			'patient_state' 	=> $row->pa_state,
			'patient_zip' 		=> $row->pa_zip,
			'patient_ssn' 		=> $row->pa_ssn,
			'patient_dob' 		=> date('m/d/Y', strtotime($row->pa_dob)),
			'patient_mrn' 		=> $row->pa_mrn,
			'patient_gender' 	=> $row->pa_gender,
			'patient_phone' 	=> $row->pa_phone,
			'patient_code' 		=> $row->pa_code,
			'patient_email' 	=> $row->pa_email
		);

		$rowdata['patient_mrn'] = $row->pa_mrn;

		if (isset($settings['insurance_sresg']) && $settings['insurance_sresg']) {

			$rowdata['patient_race'] = isset($races[$row->pa_race]) ? $races[$row->pa_race] : '';
			$rowdata['patient_ethnicity'] = isset($Ethnicitis[$row->pa_ethnicity]) ? $Ethnicitis[$row->pa_ethnicity] : '';
			$rowdata['patient_sexual_oriention'] = $row->pa_sexual_oriention;
			$rowdata['patient_gender_identity'] = $row->pa_gender_identity;
		}
		$rowdata['referring_provider_code'] = $referring_provider_code;
		$rowdata['parent_spouse_name'] = $parent_name;
		$rowdata['parent_dob'] = $parent_dob;
		$rowdata['doctor_first'] = $row->doc_first;
		$rowdata['doctor_last'] = $row->doc_last;
		$rowdata['doctor_npi'] = $row->doc_npi;
		$rowdata['doctor_phone'] = $row->doc_phone;

		$rowdata['cpt_code'] = $cpt_code;
		$rowdata['dos'] = date('m/d/Y', strtotime($row->doc));
		$rowdata['collection'] = date('m/d/Y', strtotime($row->doc));
		$rowdata['received'] = $row->spec_received;
		$rowdata['result_date'] = date('m/d/Y', strtotime($row->approve_date));
		$rowdata['panels'] = $panel;


		$rowdata['insurance'] = $row->insurance;

		$rowdata['carrier_code2'] = $row->payer_mnemonic2;
		$rowdata['insurance_member_id_2'] = str_replace('-', '', $row->ins_member_id2);

		$rowdata['insurance_2'] = $row->ins_company2;
		$rowdata['carrier_code'] = $row->payer_mnemonic;
		$rowdata['member_id'] = str_replace('-', '', $row->ins_member_id);

		//ion
		$rowdata['Primary_Insured_Relationship_Code'] = $row->ins_relation;
		$rowdata['Primary_Insured_Relationship_Desc'] = '';
		$rowdata['Primary_Insured_Last_Name'] = $row->ins_last;
		$rowdata['Primary_Insured_First_Name'] = $row->ins_first;
		$rowdata['Primary_Insured_Middle_Name'] = '';
		$rowdata['Primary_Insured_Gender'] = $row->pa_gender;
		$rowdata['Primary_Insured_DOB'] = date('m/d/Y', strtotime($row->pa_dob));
		$rowdata['Primary_Insured_Address1'] = $row->ins_address;
		$rowdata['Primary_Insured_Address2'] = '';
		$rowdata['Primary_Insured_City'] = $row->ins_city;
		$rowdata['Primary_Insured_State'] = $row->ins_state;
		$rowdata['Primary_Insured_Zip'] = $row->ins_zip;

		//ion
		$rowdata['Secondary_Insured_Relationship_Code'] = $row->ins_relation2;
		$rowdata['Secondary_Insured_Relationship_Desc'] = '';
		$rowdata['Secondary_Insured_Last_Name'] = $row->ins_last2;
		$rowdata['Secondary_Insured_First_Name'] = $row->ins_first2;
		$rowdata['Secondary_Insured_Middle_Name'] = '';
		$rowdata['Secondary_Insured_Gender'] = !empty($row->ins_last2) ? $row->pa_gender : '';
		$rowdata['Secondary_Insured_DOB'] = !empty($row->ins_last2) ? date('m/d/Y', strtotime($row->pa_dob)) : '';
		$rowdata['Secondary_Insured_Address1'] = $row->ins_address2;
		$rowdata['Secondary_Insured_Address2'] = '';
		$rowdata['Secondary_Insured_City'] = $row->ins_city2;
		$rowdata['Secondary_Insured_State'] = $row->ins_state2;
		$rowdata['Secondary_Insured_Zip'] = $row->ins_zip2;

		$rowdata['Performing_Facility_ID'] = $row->lab_ID;
		$rowdata['Performing_Facility_Name'] = $row->lab_name;



		if (!$send_multiple) {
			$response_body = tr_send_webhoook($rowdata);

			if ($response_body != 'error') {
				$update = array(
					'mo_mid' => $row->mid,
					'mo_file_id' => -47,
					'mo_btype' => $btype,
					'mo_status' => 1,
					'mo_control_ID' => date_i18n('Y-m-d H:i:s'),
					'other_info' => $response_body
				);
				if ($row->mo_ID > 0) {
					$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $row->mo_ID));
				} else {
					$r = $wpdb->insert('ci_sync_api_orders', $update);
				}
				echo $wpdb->last_error;
			}



			$datarows = array();
		} else {
			$datarows[] = $rowdata;
			$mids[$row->mid] = $row->mo_ID;
		}
	}

	if ($send_multiple && count($datarows) > 0) {
		$response_body = tr_send_webhoook($datarows);

		if ($response_body != 'error') {
			foreach ($mids as $mid => $mo_ID) {
				$update = array(
					'mo_mid' => $mid,
					'mo_file_id' => -47,
					'mo_btype' => $btype,
					'mo_status' => 1,
					'mo_control_ID' => date_i18n('Y-m-d H:i:s'),
					'other_info' => $response_body
				);
				if ($mo_ID > 0) {
					$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $mo_ID));
				} else {
					$r = $wpdb->insert('ci_sync_api_orders', $update);
				}
				echo $wpdb->last_error;
			}
		}
	}
}

if ($flog) {
	fclose($flog);
}

if (filesize($logfile) > 9000000) {
	file_put_contents($logfile, substr(file_get_contents($logfile), 8000000));
}
