<?php

function tr_pdd_qc_check_all($pl_ID)
{
	global $wpdb;
	$plate = $wpdb->get_row("select * from ci_plates where pl_ID = {$pl_ID} ", ARRAY_A);
	$otherdata      = json_decode($plate['other_data'], true);

	$pass_all_qc = array(
		2 => true,
		3 => true,
		4 => true,
		5 => true,
	);

	$otherdata['qc4'] = null;
	$otherdata['qc5'] = null;

	//check qc2
	$count_qc2_fail = $wpdb->get_var("select count(*) from ci_req_qx200 
	where plate_ID = {$pl_ID} and pass_qc2 = 0  and pass_qc2_hand = 0");

	$pass_all_qc[2] = $count_qc2_fail == 0;

	if ($pass_all_qc[2]) {
		//QC3 NTC
		$count_qc3_fail = $wpdb->get_var("select count(*) from ci_req_qx200 
		where plate_ID = {$pl_ID}
		and desc1 = 'NTC' 
		and pctype = 'M'
		and pass_qc3 = 0 
		and pass_qc3_hand = 0");
		$pass_all_qc[3] = $count_qc3_fail == 0;

		//qc3
		if ($pass_all_qc[3] == false) {
			tr_pdd_insert_rerun($pl_ID);
		} else {
			//QC4
			$pass_all_qc[4] = tr_pdd_qc_check_qc4($pl_ID);

			$otherdata['qc4'] = $pass_all_qc[4] ? 1 : 0;


			if ($pass_all_qc[4] || $otherdata['pass_qc4_hand']) {

				$pass_all_qc[5] = tr_pdd_qc_check_qc5($pl_ID);

				$otherdata['qc5'] = $pass_all_qc[5] ? 1 : 0;

				if ($pass_all_qc[5] == false) {
				}
			}
		}
	}

	//update $otherdata
	$wpdb->update('ci_plates', array('other_data' => json_encode($otherdata)), array('pl_ID' => $pl_ID));

	return $pass_all_qc;
}

function tr_pdd_qc_check_qc4($pl_ID)
{
	global $wpdb;

	$plate = $wpdb->get_row("select * from ci_plates where pl_ID = {$pl_ID} ", ARRAY_A);
	$otherdata      = json_decode($plate['other_data'], true);


	//GET PC data
	$pcdata = array();
	$rowdatas = array();
	$rs = $wpdb->get_results("select * from ci_req_qx200 
	where plate_ID = {$pl_ID}
	and desc1 = 'PC' ");
	foreach ($rs as $r) {
		$target_name =  $r->target == 2 ? 'HEX' : 'FAM';
		$pcdata[$r->desc2 . '_' . $target_name . '_' . $r->well] = $r->conc;
		$rowdatas[$r->desc2 . '_' . $target_name . '_' . $r->well] = $r;
	}

	/*Avg_MSRE+_Insulin = MEAN(PC_Data[MSRE+, FAM, A01, B01])
    CV_MSRE+_Insulin = (STDEV(PC_Data[MSRE+, FAM, A01, B01]) / Avg_MSRE+_Insulin) * 100
    Avg_MSRE-_Insulin = MEAN(PC_Data[MSRE-, FAM, C01, D01])
    CV_MSRE-_Insulin = (STDEV(PC_Data[MSRE-, FAM, C01, D01]) / Avg_MSRE-_Insulin) * 100
    */
	$avgs = array();
	$cvs = array();
	$stds = array();

	$avgs['MSRE+_Insulin'] = trpdd_average(array($pcdata['MSRE+_FAM_A1'], $pcdata['MSRE+_FAM_B1']));
	$cvs['MSRE+_Insulin'] = trpdd_stdev(array($pcdata['MSRE+_FAM_A1'], $pcdata['MSRE+_FAM_B1'])) / $avgs['MSRE+_Insulin'] * 100;
	$stds['MSRE+_Insulin'] = trpdd_stdev(array($pcdata['MSRE+_FAM_A1'], $pcdata['MSRE+_FAM_B1']));

	$avgs['MSRE-_Insulin'] = trpdd_average(array($pcdata['MSRE-_FAM_C1'], $pcdata['MSRE-_FAM_D1']));
	$cvs['MSRE-_Insulin'] = trpdd_stdev(array($pcdata['MSRE-_FAM_C1'], $pcdata['MSRE-_FAM_D1'])) / $avgs['MSRE-_Insulin'] * 100;
	$stds['MSRE-_Insulin'] = trpdd_stdev(array($pcdata['MSRE-_FAM_C1'], $pcdata['MSRE-_FAM_D1']));

	/*
    Avg_TTC5 = MEAN(PC_Data[MSRE+, HEX, A01, B01], PC_Data[MSRE-, HEX, C01, D01])
    CV_TTC5 = (STDEV(PC_Data[MSRE+, HEX, A01, B01], PC_Data[MSRE-, HEX, C01, D01]) / Avg_TTC5) * 100
    */
	$avgs['TTC5'] = trpdd_average(array($pcdata['MSRE+_HEX_A1'], $pcdata['MSRE+_HEX_B1'], $pcdata['MSRE-_HEX_C1'], $pcdata['MSRE-_HEX_D1']));
	$cvs['TTC5'] = trpdd_stdev(array($pcdata['MSRE+_HEX_A1'], $pcdata['MSRE+_HEX_B1'], $pcdata['MSRE-_HEX_C1'], $pcdata['MSRE-_HEX_D1'])) / $avgs['TTC5'] * 100;
	$stds['TTC5'] = trpdd_stdev(array($pcdata['MSRE+_HEX_A1'], $pcdata['MSRE+_HEX_B1'], $pcdata['MSRE-_HEX_C1'], $pcdata['MSRE-_HEX_D1']));

	/*
	Status_MSRE+_Insulin = IF (Avg_MSRE+_Insulin < (Historical_Data.Avg_MSRE+_Insulin - Historical_Data.Std_MSRE+_Insulin) 
	OR Avg_MSRE+_Insulin > (Historical_Data.Avg_MSRE+_Insulin + Historical_Data.Std_MSRE+_Insulin)) THEN
	"FAIL" ELSE "PASS"

	Status_MSRE-_Insulin = IF (Avg_MSRE-_Insulin < (Historical_Data.Avg_MSRE-_Insulin - Historical_Data.Std_MSRE-_Insulin) 
	OR Avg_MSRE-_Insulin > (Historical_Data.Avg_MSRE-_Insulin + Historical_Data.Std_MSRE-_Insulin)) THEN
	"FAIL" ELSE "PASS"

	Status_TTC5 = IF (Avg_TTC5 < (Historical_Data.Avg_TTC5 - Historical_Data.Std_TTC5) 
	OR Avg_TTC5 > (Historical_Data.Avg_TTC5 + Historical_Data.Std_TTC5)) THEN
	"FAIL" ELSE "PASS"
	*/

	$avg_stdev = tr_pdd_get_pc_historcal();

	$status['MSRE+_Insulin'] = ($avgs['MSRE+_Insulin'] < $avg_stdev['AVG_MSRE+_Insulin'] - $avg_stdev['STD_MSRE+_Insulin'])
		|| ($avgs['MSRE+_Insulin'] > $avg_stdev['AVG_MSRE+_Insulin'] + $avg_stdev['STD_MSRE+_Insulin'])
		? 0 : 1;

	$status['MSRE-_Insulin'] = ($avgs['MSRE-_Insulin'] < $avg_stdev['AVG_MSRE-_Insulin'] - $avg_stdev['STD_MSRE-_Insulin'])
		|| ($avgs['MSRE-_Insulin'] > $avg_stdev['AVG_MSRE-_Insulin'] + $avg_stdev['STD_MSRE-_Insulin'])
		? 0 : 1;

	$status['TTC5'] = ($avgs['TTC5'] < $avg_stdev['AVG_TTC5'] - $avg_stdev['STD_TTC5'])
		|| ($avgs['TTC5'] > $avg_stdev['AVG_TTC5'] + $avg_stdev['STD_TTC5'])
		? 0 : 1;

	$status['qc4'] = $status['MSRE+_Insulin'] && $status['MSRE-_Insulin'] && $status['TTC5'] ? 1 : 0;

	//insert Historical
	if ($status['qc4'] || $otherdata['pass_qc4_hand']) {
		$keys = array(
			'M1|PC|MSRE+|1',
			'M13|PC|MSRE-|1',
			'M1|PC|MSRE+|2',
			'M13|PC|MSRE-|2',
		);
		foreach ($keys as $key) {
			list($well, $desc1, $desc2, $target) = explode('|', $key);
			$target_name =  $target == 2 ? 'HEX' : 'FAM';
			$avgkey = $desc2 . '_Insulin';
			if ($target == 2) {
				$avgkey = $desc2 == 'MSRE-' ? 'none' : 'TTC5';
			}
			$avg = isset($avgs[$avgkey]) ? $avgs[$avgkey] : 0;
			$std = isset($stds[$avgkey]) ? $stds[$avgkey] : 0;
			$pass = isset($status[$avgkey]) ? $status[$avgkey] : 0;

			$conc 			= 0;
			$accepted_droplets = 0;
			$positives 		= 0;
			$negatives 		= 0;

			$rowdt = isset($rowdatas[$desc2 . '_' . $target_name . '_' . $well]) ? $rowdatas[$desc2 . '_' . $target_name . '_' . $well] : null;
			if ($rowdt) {
				$conc = $rowdt->conc;
				$accepted_droplets = $rowdt->accepted_droplets;
				$positives = $rowdt->positives;
				$negatives = $rowdt->negatives;
			}

			$existid = $wpdb->get_var("select ID from ci_req_qc_historical
			where plate_ID = {$pl_ID}
			and desc1 = '{$desc1}'
			and desc2 = '{$desc2}' 
			and target = {$target} ");

			$historical = array(
				'plate_ID' => $pl_ID,
				'desc1' => $desc1,
				'desc2' => $desc2,
				'well' => $well,
				'target' => $target,
				'conc' => $conc,
				'accepted_droplets' => $accepted_droplets,
				'positives' => substr($positives, 0, 15),
				'negatives' => substr($negatives, 0, 15),
				'avg' => $avg,
				'stdev' => $std,
				'status' => $pass,
			);
			if ($existid == 0) {
				$wpdb->insert('ci_req_qc_historical', $historical);
			} else {
				$wpdb->update('ci_req_qc_historical', $historical, array('ID' => $existid));
			}
		}

		tr_pdd_recal_pc_historical();
	}
	return $status['qc4'];
}

function tr_pdd_qc_check_qc5($pl_ID)
{
	global $wpdb;

	$plate = $wpdb->get_row("select * from ci_plates where pl_ID = {$pl_ID} ", ARRAY_A);
	$otherdata      = json_decode($plate['other_data'], true);
	//GET PC data
	$pcdata = array();
	$rowdatas = array();
	$rs = $wpdb->get_results("select * from ci_req_qx200 
	where plate_ID = {$pl_ID}
	and desc1 not in ('PC','NTC') ");
	$assens = array();
	foreach ($rs as $r) {
		$target_name =  $r->target == 2 ? 'HEX' : 'FAM';
		if ($r->pctype == 'P') {
			$pcdata[$r->desc2 . '_' . $target_name][] = $r->conc;
		} else {
			$rowdatas[$r->desc1 . '_' . $r->desc2 . '_' . $target_name] = $r;
			$assens[$r->desc1] = $r->desc1;
		}
	}

	/*
	Avg_MSRE+_TTC5 = MEAN(Patient_Data[MSRE+, HEX,A02:D12])
	CV_MSRE+_TTC5 = (STDEV(Patient_Data[MSRE+, HEX,A02:D12]) / Avg_MSRE+_TTC5) * 100
	Avg_MSRE-_TTC5 = MEAN(Patient_Data[MSRE-, HEX,E02:H12])
	CV_MSRE-_TTC5 = (STDEV(Patient_Data[MSRE-, HEX,E02:H12]) / Avg_MSRE-_TTC5) * 100
	CV_MSRE+_Insulin = (STDEV(Patient_Data[MSRE+, FAM,A02:D12]) / MEAN(Patient_Data[MSRE+, FAM, A02:D12])) * 100
	CV_MSRE-_Insulin = (STDEV(Patient_Data[MSRE-, FAM,E02:H12]) / MEAN(Patient_Data[MSRE-, FAM, E02:H12])) * 100
	*/
	$avgs  = array();
	$cvs  = array();
	$stds  = array();
	$avgs['MSRE+_TTC5'] = trpdd_average($pcdata['MSRE+_HEX']);
	$cvs['MSRE+_TTC5'] 	= trpdd_stdev($pcdata['MSRE+_HEX']) / $avgs['MSRE+_TTC5'] * 100;
	$stds['MSRE+_TTC5'] 	= trpdd_stdev($pcdata['MSRE+_HEX']);

	$avgs['MSRE-_TTC5'] = trpdd_average($pcdata['MSRE-_HEX']);
	$cvs['MSRE-_TTC5'] 	= trpdd_stdev($pcdata['MSRE-_HEX']) / $avgs['MSRE-_TTC5'] * 100;
	$stds['MSRE-_TTC5'] 	= trpdd_stdev($pcdata['MSRE-_HEX']);

	$cvs['MSRE+_Insulin'] 	= trpdd_stdev($pcdata['MSRE+_FAM']) / trpdd_average($pcdata['MSRE+_FAM']) * 100;
	$cvs['MSRE-_Insulin'] 	= trpdd_stdev($pcdata['MSRE-_FAM']) / trpdd_average($pcdata['MSRE-_FAM']) * 100;

	$pass = 1;
	$avg_stdev = tr_pdd_get_patient_historcal();


	if (
		$cvs['MSRE+_TTC5'] > 20 || $cvs['MSRE-_TTC5'] > 20 ||
		$cvs['MSRE+_Insulin'] > 20 || $cvs['MSRE-_Insulin'] > 20 ||
		$avgs['MSRE+_TTC5'] < ($avg_stdev['AVG_MSRE+_TTC5'] - $avg_stdev['STD_MSRE+_TTC5']) ||
		$avgs['MSRE+_TTC5'] > ($avg_stdev['AVG_MSRE+_TTC5'] + $avg_stdev['STD_MSRE+_TTC5']) ||
		$avgs['MSRE-_TTC5'] < ($avg_stdev['AVG_MSRE+_TTC5'] - $avg_stdev['STD_MSRE+_TTC5']) ||
		$avgs['MSRE-_TTC5'] > ($avg_stdev['AVG_MSRE+_TTC5'] + $avg_stdev['STD_MSRE+_TTC5'])
	) {
		$pass = 0;
	}

	if ($pass || $otherdata['pass_qc5_hand']) {

		//insert Historical
		foreach (array('MSRE+', 'MSRE-') as $desc2) {
			foreach ($assens as $desc1) {
				$target = 2;

				$conc 			= 0;
				$accepted_droplets = 0;
				$positives 		= 0;
				$negatives 		= 0;
				$well = '';

				$rkey = $desc1 . '_' . $desc2 . '_' . $target_name;
				$rowdt = isset($rowdatas[$rkey]) ? $rowdatas[$rkey] : null;
				if ($rowdt) {
					$well = $rowdt->well;
					$conc = $rowdt->conc;
					$accepted_droplets = $rowdt->accepted_droplets;
					$positives = $rowdt->positives;
					$negatives = $rowdt->negatives;
				}

				$avgkey = $well == 'M2' ? 'MSRE+_TTC5' : 'none';
				if ($well == 'M15') {
					$avgkey = 'MSRE-_TTC5';
				}


				$avg = isset($avgs[$avgkey]) ? $avgs[$avgkey] : 0;
				$std = isset($stds[$avgkey]) ? $stds[$avgkey] : 0;
				$pass = 1;

				$existid = $wpdb->get_var("select ID from ci_req_qc_historical
				where plate_ID = {$pl_ID}
				and desc1 = '{$desc1}'
				and desc2 = '{$desc2}' 
				and target = {$target} ");

				$historical = array(
					'plate_ID' => $pl_ID,
					'desc1' => $desc1,
					'desc2' => $desc2,
					'well' => $well,
					'target' => $target,
					'conc' => $conc,
					'accepted_droplets' => $accepted_droplets,
					'positives' => substr($positives, 0, 15),
					'negatives' => substr($negatives, 0, 15),
					'avg' => $avg,
					'stdev' => $std,
					'status' => $pass,
				);
				if ($existid == 0) {
					$wpdb->insert('ci_req_qc_historical', $historical);
				} else {
					$wpdb->update('ci_req_qc_historical', $historical, array('ID' => $existid));
				}
			}
		}
		tr_pdd_recal_patient_historical();
	}

	return $pass;
}

function tr_pdd_insert_rerun($pl_ID)
{
	global $wpdb;
	$plate = $wpdb->get_row("select * from ci_plates where pl_ID = {$pl_ID} ", ARRAY_A);
	$rack = $wpdb->get_row("select * from ci_racks where re_ID = {$plate['pl_rack_ID']} ", ARRAY_A);

	$mids = $wpdb->get_col("select mid from ci_rack_batches where re_ID = {$plate['pl_rack_ID']}  ");
	//check exist in other racks
	$new_rack_id = $wpdb->get_var("select ra.re_ID from ci_racks ra 
        JOIN ci_rack_batches rb ON rb.re_ID = ra.re_ID
        where rb.mid in (" . implode(',', $mids) . ") and rb.btype = 'req'
        and ra.re_ID > {$rack['re_ID']} 
        and ra.re_is_uti_panel = {$rack['re_is_uti_panel']} 
        limit 1");


	if (!$new_rack_id) {
		$new_rack = $rack;
		unset($new_rack['re_ID']);
		$new_rack['re_subtitle'] = 'NTC Rerun Rack #' . $rack['re_number'];
		$new_rack['re_number']  = $rack['re_number'] + 1;
		$new_rack['re_created'] = date_i18n('Y-m-d H:i:s');
		$wpdb->insert('ci_racks', $new_rack);

		$new_rack_id = $wpdb->insert_id;
	}

	if ($new_rack_id > 0) {
		foreach ($mids as $mid) {
			$rbid = $wpdb->get_var("select ID from ci_rack_batches 
                where re_ID = {$new_rack_id} 
                and mid = {$mid} 
                and btype = 'req' ");
			if ($rbid == 0) {
				$wpdb->insert('ci_rack_batches', array(
					're_ID' => $new_rack_id,
					'mid' => $mid,
					'btype' => 'req',
					'is_rerun' => 2,
					'reason_rerun' => 'qc3'
				));
			}
		}
	}
}

function tr_pdd_get_pc_historcal()
{
	global $wpdb;
	$avg_stdev = get_option('_tr_avg_stdev_dx200', array());

	if (empty($avg_stdev)) {
		$keys = array(
			'MSRE+|1',
			'MSRE-|1',
			'MSRE+|2',
		);
		$avg_stdev['AVG_MSRE+_Insulin'] = $avg_stdev['AVG_MSRE+_Insulin'] > 0 ? $avg_stdev['AVG_MSRE+_Insulin'] : 8.5;
		$avg_stdev['AVG_MSRE-_Insulin'] = $avg_stdev['AVG_MSRE-_Insulin'] > 0 ? $avg_stdev['AVG_MSRE-_Insulin'] : 142.59;
		$avg_stdev['AVG_TTC5'] 		= ($avg_stdev['AVG_TTC5'] > 0) ? $avg_stdev['AVG_TTC5'] : 165.34;

		$avg_stdev['STD_MSRE+_Insulin'] = $avg_stdev['STD_MSRE+_Insulin'] > 0 ? $avg_stdev['STD_MSRE+_Insulin'] : 0.3;
		$avg_stdev['STD_MSRE-_Insulin'] = $avg_stdev['STD_MSRE-_Insulin'] > 0 ? $avg_stdev['STD_MSRE-_Insulin'] : 5.29;
		$avg_stdev['STD_TTC5'] 		= ($avg_stdev['STD_TTC5'] > 0) ? $avg_stdev['STD_TTC5'] : 7.89;
		foreach ($keys as $key) {
			list($desc2, $target) = explode('|', $key);
			$targetname = $target == 1 ? 'Insulin' : 'TTC5';
			$key2 = $target == 2 ? 'TTC5' : $desc2 . '_' . $targetname;
			$pl_ID = -1;
			$desc1 = 'PC';
			$well = '';
			$avg = $avg_stdev['AVG_' . $key2];
			$std = $avg_stdev['STD_' . $key2];

			$existid = $wpdb->get_var("select ID from ci_req_qc_historical
				where plate_ID = {$pl_ID}
				and desc1 = '{$desc1}'
				and desc2 = '{$desc2}' 
				and target = {$target} ");

			$historical = array(
				'plate_ID' => $pl_ID,
				'desc1' => $desc1,
				'desc2' => $desc2,
				'well' => $well,
				'target' => $target,
				'avg' => $avg,
				'stdev' => $std,
				'status' => 1,
			);
			if ($existid == 0) {
				$wpdb->insert('ci_req_qc_historical', $historical);
			} else {
				$wpdb->update('ci_req_qc_historical', $historical, array('ID' => $existid));
			}
		}
	}

	return $avg_stdev;
}


function tr_pdd_recal_pc_historical()
{
	global $wpdb;
	update_option('_tr_avg_stdev_dx200', array());
	$avg_stdev = tr_pdd_get_pc_historcal();

	$keys = array(
		'MSRE+|1',
		'MSRE-|1',
		'MSRE+|2',
	);
	foreach ($keys as $key) {
		list($desc2, $target) = explode('|', $key);
		$targetname = $target == 1 ? 'Insulin' : 'TTC5';
		$key2 = $target == 2 ? 'TTC5' : $desc2 . '_' . $targetname;
		$avg = $wpdb->get_var("select AVG(`avg`) from ci_req_qc_historical
		where desc1 = 'PC' 
		and desc2 = '{$desc2}' 
		and target = {$target}
		and status = 1 ");
		if ($avg > 0) {
			$avg_stdev['AVG_' . $key2] = $avg;
		}

		$std = $wpdb->get_var("select AVG(`stdev`) from ci_req_qc_historical
		where desc1 = 'PC' 
		and desc2 = '{$desc2}' 
		and target = {$target}");
		if ($std > 0) {
			$avg_stdev['STD_' . $key2] = $std;
		}
	}

	update_option('_tr_avg_stdev_dx200', $avg_stdev);
}

function tr_pdd_get_patient_historcal()
{
	global $wpdb;
	$avg_stdev = get_option('_tr_avg_stdev_dx200_patient', array());

	if (empty($avg_stdev)) {
		$keys = array(
			'MSRE+|2',
			'MSRE-|2',
		);

		$avg_stdev['AVG_MSRE+_TTC5'] = $avg_stdev['AVG_MSRE+_TTC5'] > 0 ? $avg_stdev['AVG_MSRE+_TTC5'] : 19.75;
		$avg_stdev['AVG_MSRE-_TTC5'] = $avg_stdev['AVG_MSRE-_TTC5'] > 0 ? $avg_stdev['AVG_MSRE-_TTC5'] : 19.75;

		$avg_stdev['STD_MSRE+_TTC5'] = $avg_stdev['STD_MSRE+_TTC5'] > 0 ? $avg_stdev['STD_MSRE+_TTC5'] : 7.89;
		$avg_stdev['STD_MSRE-_TTC5'] = $avg_stdev['STD_MSRE-_TTC5'] > 0 ? $avg_stdev['STD_MSRE-_TTC5'] : 7.89;

		foreach ($keys as $key) {
			list($desc2, $target) = explode('|', $key);
			$targetname =  'TTC5';
			$key2 =  $desc2 . '_' . $targetname;
			$pl_ID = -2;
			$desc1 = 'Default';
			$well = '';
			$avg = $avg_stdev['AVG_' . $key2];
			$std = $avg_stdev['STD_' . $key2];

			if ($avg > 0) {
				$existid = $wpdb->get_var("select ID from ci_req_qc_historical
				where plate_ID = {$pl_ID}
				and desc1 = '{$desc1}'
				and desc2 = '{$desc2}' 
				and target = {$target} ");

				$historical = array(
					'plate_ID' => $pl_ID,
					'desc1' => $desc1,
					'desc2' => $desc2,
					'well' => $well,
					'target' => $target,
					'avg' => $avg,
					'stdev' => $std,
					'status' => 1,
				);
				if ($existid == 0) {
					$wpdb->insert('ci_req_qc_historical', $historical);
				} else {
					$wpdb->update('ci_req_qc_historical', $historical, array('ID' => $existid));
				}
			}
		}
	}


	return $avg_stdev;
}


function tr_pdd_recal_patient_historical()
{
	global $wpdb;
	update_option('_tr_avg_stdev_dx200_patient', array());
	$avg_stdev = tr_pdd_get_patient_historcal();

	$keys = array(
		'MSRE+|2',
		'MSRE-|2',
	);
	foreach ($keys as $key) {
		list($desc2, $target) = explode('|', $key);
		$targetname = 'TTC5';
		$key2 =  $desc2 . '_' . $targetname;
		$avg = $wpdb->get_var("select AVG(`avg`) from ci_req_qc_historical
		where desc1 not in ('PC')
		and well in ('M2','M15')
		and desc2 = '{$desc2}' 
		and target = {$target}
		and status = 1 ");
		
		if ($avg > 0) {
			$avg_stdev['AVG_' . $key2] = $avg;
		}

		$std = $wpdb->get_var("select AVG(`stdev`) from ci_req_qc_historical
		where desc1 not in ('PC')
		and well in ('M2','M15')
		and desc2 = '{$desc2}' 
		and target = {$target}");
		if ($std > 0) {
			$avg_stdev['STD_' . $key2] = $std;
		}
	}

	update_option('_tr_avg_stdev_dx200_patient', $avg_stdev);
}
