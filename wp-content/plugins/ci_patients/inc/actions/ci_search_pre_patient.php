<?php
global $wpdb;
$return = array('success' => 0, 'patient' => array());


$first_name     = trim($_REQUEST['fname']);
$last_name      = trim($_REQUEST['lname']);
$dob            = trim($_REQUEST['dob']);
$phone          = trim($_REQUEST['phone']);

$card_dir       = tr_get_ins_card_dir();
$phone          = str_replace(array('-', '.', '(', ')', ' '), '', $phone);
$phones         = tr_patient_get_phone_arr($phone);
$patient		= array();
if (!empty($phones)) {

	$phones         = implode("','", $phones);

	$sql = $wpdb->prepare(
		"select * from ci_patients 
			where (
					(pa_first = %s and pa_last = %s )
					or (pa_first = %s and pa_last = %s )
				  )
					and pa_dob = %s
					and pa_phone in ('{$phones}')
			order by pa_ID desc
			limit 1",
		$first_name,
		$last_name,
		$last_name,
		$first_name,
		$dob
	);

	$patient = $wpdb->get_row($sql, ARRAY_A);
}

if ($patient) {
	$return['success'] = 1;
	$return['patient'] = $patient;

	//search insurance & cards
	$last_batch = false;
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$last_batch = $wpdb->get_row("select insurance1,
						ins_card_front,
						ins_card_back,
						patient_id_front,
						patient_id_back
						from {$batch_tbl} 
						where patient_id = {$patient['pa_ID']} 
						order by mid desc ", ARRAY_A);
		if ($last_batch) {
			break;
		}
	}

	if ($last_batch) {
		$insurance = $wpdb->get_row("select ins_company,
						ins_member_id,
						ins_group_id
						from ci_insurances 
						where ins_ID = {$last_batch['insurance1']} ", ARRAY_A);

		$return['insurance'] = $insurance;
		$cards = array(
			'ins_card_front' => $last_batch['ins_card_front'],
			'ins_card_back' => $last_batch['ins_card_back'],
			'patient_id_front' => $last_batch['patient_id_front'],
			'patient_id_back' => $last_batch['patient_id_back'],
		);
		$_SESSION['upload_insurance_photos'] = $cards;
		$return['cards'] = array();
		foreach ($cards as $key => $vl) {
			if (!empty($vl)) {
				$return['cards'][$key] = $card_dir['url'] . '/' . $vl;
			}
		}
	}
}

wp_send_json($return);
