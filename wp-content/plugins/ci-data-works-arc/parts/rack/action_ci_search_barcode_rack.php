<?php

$return = array('status' => 'ok', 'batch' => array(), 'position' => '', 'use_rack4' => '', 'btype' => '', 'rbid' => '');

$return['first_ntc'] = $first_ntc;
$return['second_ntc_pos'] = $second_ntc_pos;
$return['html'] = '';

$code       = $_REQUEST['code'];
$parentdata = isset($_REQUEST['parentdata']) ? $_REQUEST['parentdata'] : array();
$duplicate  = isset($_REQUEST['duplicate']) ? $_REQUEST['duplicate'] : 0;
$key_pos    = trim($_REQUEST['btype']);
$pnid       = isset($_REQUEST['pnid']) && $_REQUEST['pnid'] > 0 ? $_REQUEST['pnid'] : 0;
$mixedvp    = isset($_REQUEST['mixedvp']) && $_REQUEST['mixedvp']  ? $_REQUEST['mixedvp'] : '';
$parts    	= isset($_REQUEST['parts']) && is_array($_REQUEST['parts']) ? $_REQUEST['parts'] : array();
$subtype    = '';
$use_rack4  = false;
$pass_duplicate  = false;
$check_duplicate_mixed  = false;

if (isset($parts['subtype'])) {
	$subtype = $parts['subtype'];
}
if ($key_pos == 'tox' && !isset($base_positions[$key_pos])) {
	$key_pos = 'urine';
}
$base_type  = isset($base_positions[$key_pos]) ? $base_positions[$key_pos] : array('btype' => $key_pos);
$btype      = $base_type['btype'];

$new_well     = isset($_REQUEST['new_well']) && $_REQUEST['new_well'];
$pass_add     = $duplicate;
$re_ID         = $parentdata['re_ID'];
$reason_rerun = isset($parentdata['reason_rerun']) ? $parentdata['reason_rerun'] : '';
unset($parentdata['reason_rerun']);

if ($re_ID == 0) {
	if (empty($parentdata['re_today'])) {
		$parentdata['re_today'] = date_i18n('Y-m-d');
	}
	if (empty($data['re_number'])) {
		$parentdata['re_number'] = $wpdb->get_var("select max(re_number) from ci_racks where re_today = '{$parentdata['re_today']}' ") + 1;
	}
	$parentdata['re_created'] = date_i18n('Y-m-d H:i:s');
	$parentdata['re_author_id'] = $user_id;
	$parentdata['re_rack_type'] = $key_pos;

	$parentdata['re_panel_style'] = 1;
	if (strpos($parentdata['re_panel_id'], '_') > 0) {
		list($parentdata['re_panel_id'], $parentdata['re_panel_style']) = explode('_', $parentdata['re_panel_id']);
	}

	if (isset($parentdata['re_panel_id']) && $parentdata['re_panel_id'] > 0) {
		$parentdata['re_is_uti_panel'] = trpdd_get_rack_panel_type($parentdata['re_panel_id'], $parentdata['re_panel_style']);
	}

	$wpdb->insert('ci_racks', $parentdata);
	$re_ID = $wpdb->insert_id;
	$return['re_ID'] = $re_ID;
} else {
	$rack       = $wpdb->get_row("select * from ci_racks where re_ID = '{$re_ID}' ");
	if ($rack->re_rack_type != $key_pos) {
		$wpdb->update('ci_racks', array('re_rack_type' => $key_pos), array('re_ID' => $re_ID));
	}
	$parentdata['re_subtitle'] = $rack->re_subtitle;
	$parentdata['re_barcode'] = $rack->re_barcode;
	$parentdata['re_rack_subtype'] = $rack->re_rack_subtype;
	if ($plate_coating && empty($parentdata['re_rack_subtype']) && !empty($rack->re_subtitle)) {
		$parentdata['re_rack_subtype'] = preg_replace("/[^VPTM]/", '', $rack->re_subtitle);
		if (!empty($parentdata['re_rack_subtype'])) {
			$wpdb->update('ci_racks', array('re_rack_subtype' => $parentdata['re_rack_subtype']), array('re_ID' => $re_ID));
		}
	}
}

if ($re_ID > 0) {


	if (in_array($code, array('MOL00001001', 'MOL00001002'))) {

		ob_start();
		$batch = new stdClass();
		$batch->mid = $code == 'MOL00001001' ? '-11' : '-12';
		$batch->well        = $code == 'MOL00001001' ? 'P13' : 'O13';
		$batch->assen        = $code;
		$batch->other_data  = array();
		$batch->btype       = $btype;
		$batch->position    = $code == 'MOL00001001' ? 'PC' : 'NC';
		$batch->subtype     = $subtype;
		$batch->reason_rerun = $reason_rerun;
		trrack_display_sample_row($batch);
		$insertdata = array(
			're_ID'      => $re_ID,
			'mid'        => $batch->mid,
			'btype'      => $btype,
			'created'    => $datetime_now,
			'created_by' => $user_id,
		);
		$wpdb->insert('ci_rack_batches', $insertdata);

		$return['html'] = ob_get_clean();
		wp_send_json($return);
		exit;
	}




	if ($pnid > 0) {
		$panel_name = $wpdb->get_var("select tg_name from ci_test_groups where tg_ID = {$pnid}");
		if (stripos($panel_name, 'uti') !== false) {
			$subtype = 'uti';
		} else if (stripos($panel_name, 'sti') !== false) {
			$subtype = 'sti';
		} else if (stripos($panel_name, 'rpp') !== false) {
			$subtype = 'rpp';
		} else {
			$subtype = substr($panel_name, 0, 3);
			$subtype = strtolower($subtype);
		}
	} else if (!empty($mixedvp) && $parentdata['re_rack_subtype'] == 'M') {
		$subtype    = $mixedvp;
		$countnum   = $wpdb->get_var("select count(*) from ci_rack_batches where re_ID = {$re_ID} ");
		$letters    = $wpdb->get_var("select letters from ci_plate_coating where barcode = '{$parentdata['re_barcode']}' ");

		$return['letters'] = $letters;
		$maximumnvp = false;

		if ($mixedvp == 'auto') {
			$subtype = substr($letters, $countnum, 1);
			if (empty($letters) || empty($subtype)) {
				$return['status']  = 'error';
				$return['message'] = 'You must select V or P';
				wp_send_json($return);
				exit;
			}
		} else if (!empty($letters)) {

			$subtypenow = substr($letters, $countnum, 1);
			//check letter first
			if (stripos(substr($letters, $countnum), $subtype) === false) {
				$maximumnvp = true;
			} else {
				while (!empty($subtypenow) && $subtypenow != $subtype) {

					$midb = $wpdb->get_var("select min(mid) 
                        from ci_rack_batches 
                        where mid < 0 
                        and re_ID = {$re_ID}
                        and btype = '{$btype}'") - 1;

					$wpdb->insert('ci_rack_batches', array(
						're_ID' => $re_ID,
						'btype' => $btype,
						'mid' => $midb,
						'subtype' => $subtypenow,
						'created' => $datetime_now,
						'created_by' => $user_id,
					));

					$countnum++;
					$subtypenow = substr($letters, $countnum, 1);
				}
			}
		}

		if ($maximumnvp) {
			$return['status']  = 'error';
			$return['message'] = 'You do not have anymore ' . $subtype . ' rows available on this plate';
			wp_send_json($return);
			exit;
		}

		if ($countnum > 0) {
			//check first duplicate
			$check_duplicate_mixed = true;
		}

		$return['subtype'] = $subtype;
	}


	if ($allow_rack4s) {
		if (isset($parentdata['re_kind'])) {
			$use_rack4 = $parentdata['re_kind'];
		} else {
			$use_rack4 = $wpdb->get_var("select re_kind from ci_racks where re_ID = '{$re_ID}' ");
		}
	}

	$parentdata['re_panel_style'] = 1;
	if (strpos($parentdata['re_panel_id'], '_') > 0) {
		list($parentdata['re_panel_id'], $parentdata['re_panel_style']) = explode('_', $parentdata['re_panel_id']);
	}

	if (!isset($parentdata['re_is_uti_panel'])) {
		$parentdata['re_is_uti_panel'] = trpdd_get_rack_panel_type($parentdata['re_panel_id'], $parentdata['re_panel_style']);
	}
	if ($parentdata['re_is_uti_panel'] == -1) {
		$use_rack4 = false; //Respiratory Light
	}

	$config_plate = trpdd_plate_map_config($parentdata['re_is_uti_panel']);

	$return['use_rack4'] = $use_rack4 ? 1 : 0;


	$batch_tbl  = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$return['btype'] = $btype;

	if (stripos($code, 'patientdemo_') !== false) {
		//check $exist
		$numbers = str_ireplace('patientdemo_', '', $code);
		list($start, $end) = explode('_', $numbers);
		if ($end > 0 && $end > $start) {
			for ($i = $start; $i <= $end; $i++) {
				$code2 = 'patient' . $i;
				$mid = $wpdb->get_var("select * from {$batch_tbl} where assen = '{$code2}'  ");
				if (!$mid) {
					$wpdb->insert($batch_tbl, array(
						'assen' => $code2,
						'is_saved' => 1
					));
					$mid = $wpdb->insert_id;
				}
				$datarow = array(
					're_ID' 	=> $re_ID,
					'mid'    	=> $mid,
					'btype' 	=> $btype,
					'created' 	=> $datetime_now,
					'created_by' => $user_id,
				);
				if (!empty($subtype)) {
					$datarow['subtype'] = $subtype;
				}
				$wpdb->insert('ci_rack_batches', $datarow);
			}
		}
	}

	if (stripos($code, 'placeholder') !== false) {
		$batch = new stdClass();
		$batch->mid = $wpdb->get_var("select min(mid) from ci_rack_batches 
			where mid < 0 
			and re_ID = {$re_ID}
			and btype = '{$btype}'") - 1;
		$batch->assen = 'Placeholder';
	} else {
		$batch = $wpdb->get_row("select * from {$batch_tbl} where assen = '{$code}' or oldreq = '{$code}'");
		if (!$batch && preg_match('/^\d+$/', $code) === 1) {
			$labinit = CI_Base_Lab::get_current_lab_init();
			$code = $labinit . $code;
			$batch = $wpdb->get_row("select * from {$batch_tbl} where assen='{$code}' order by mid desc limit 1");
		}
	}


	if ($batch && $batch->mid != 0) {

		$batch->is_rerun = 0;
		$parent_id = 0;

		$exist_reid = 0;
		if ($batch->mid > 0) {
			$sql_check_exist = "select re_ID from ci_rack_batches 
				where mid = {$batch->mid}
				and btype = '{$btype}'";
			if ($check_duplicate_mixed) {
				$sql_check_exist .= " and subtype='{$subtype}' ";
			}
			$exist_reid = $wpdb->get_var($sql_check_exist);

			if ($check_duplicate_mixed && $exist_reid > 0) {
				$maxnummixed = $btype == 'study' ? 6 : 2;

				$count_mixed = $wpdb->get_var("select count(*) from ci_rack_batches 
                    where mid = {$batch->mid}
                    and btype = '{$btype}'
                    and re_ID = {$re_ID}
                    and subtype='{$subtype}'");
				// $return['$count_mixed'] = $count_mixed;
				// $return['$maxnummixed'] = $maxnummixed;
				if ($count_mixed < $maxnummixed) {
					$pass_duplicate = true;
					if ($btype == 'study') {
						$exist_reid = 0;
						$parent_id = $wpdb->get_var("select min(iparent) from ci_rack_batches 
                            where mid = {$batch->mid}
                            and btype = '{$btype}'
                            and re_ID = {$re_ID}
                            and subtype='{$subtype}'") - 1;
					}
				}
			}
		}


		//check exist
		if (!$pass_duplicate && $subtype != 'M' && $exist_reid == $re_ID) {
			$return['status']  = 'error';
			$return['message'] = 'Duplicate Barcode';
			wp_send_json($return);
			exit;
		}


		if (tr_get_option('ci_pdf_data', 'rack_warerun') && !$pass_add && $btype != 'study') {
			$is_return = $wpdb->get_var("select covid_pos from {$report_tbl} where patient_id='{$code}' ") == 2;
			if ($is_return) {
				$return['status'] = 'rerun';
			}
		}

		if ($return['status'] == 'rerun') {
			$return['message'] = 'This sample is a ReRun - are you sure you want to rack it?';
			if ($parentdata['re_is_uti_panel'] == 21) {
				$return['ask_reason_rerun'] = true;
				$return['reason_rerun']     = trpdd_rerun_reason_fa($code);
				$return['list_reason']         = array(
					'High Variability',
					'Out of Range, High VCAM-1',
					'Out of Range, Low VCAM-1',
					'Out of Range, High P-selectin',
					'Channel failed >2'
				);
			} else if ($parentdata['re_is_uti_panel'] == 22) {
				$return['ask_reason_rerun'] = true;
				$return['list_reason'] = array(
					'High CV%',
					'Out of Range, Low MFI',
					'Out of Range, Nanodrop High',
					'Out of Range, Nanodrop Low',
					'Tube failed >1 (if 2 tubes failed for any reason)'
				);
			}
		} else if ($exist_reid > 0 && !$duplicate && $btype != 'study') {
			$return['message'] = 'Is this a sample Re-Run?';
			$return['status'] = 'duplicate';

			if ($parentdata['re_is_uti_panel'] == 21) {
				$return['ask_reason_rerun'] = true;
				$return['reason_rerun']     = trpdd_rerun_reason_fa($code);
				$return['list_reason']         = array(
					'High Variability',
					'Out of Range, High VCAM-1',
					'Out of Range, Low VCAM-1',
					'Out of Range, High P-selectin',
					'Channel failed >2'
				);
			} else if ($parentdata['re_is_uti_panel'] == 22) {
				$return['ask_reason_rerun'] = true;
				$return['list_reason'] = array(
					'High CV%',
					'Out of Range, Low MFI',
					'Out of Range, Nanodrop High',
					'Out of Range, Nanodrop Low',
					'Tube failed >1 (if 2 tubes failed for any reason)'
				);
			}
		} else {

			$adding_position = 1;
			$count_child = 0;
			$sql_count_samp = "select count(*) from ci_rack_batches where re_ID = {$re_ID}";
			if (!empty($config_plate) && isset($config_plate['parts'])) {
				$subtype = intval($subtype);
				$sql_count_samp .= " and subtype = {$subtype} ";
			}
			if ($use_rack4) {
				$sql_count_samp .= " and iparent = 0 ";
				//get last main
				$main = $wpdb->get_row("select ID,
                                                (select count(*) from ci_rack_batches c where c.iparent = m.ID) as count_child
                                                from ci_rack_batches m 
                                                where re_ID = {$re_ID} and iparent = 0 
                                                order by ID desc
                                                limit 1");
				if ($main && ($main->count_child < $number_p_well - 1 && !$new_well)) {
					$adding_position = 0;
					$parent_id = $main->ID;
					$count_child = $main->count_child;
				}
			}

			$count_samp = $wpdb->get_var($sql_count_samp);
			$return['errocount'] = $wpdb->last_error;
			$return['count_sql'] = $wpdb->last_query;

			if (!empty($config_plate)) {
				$max_well 	= $config_plate['max'];

				$first_ntc 	= 0;
				$second_ntc_pos = 0;
				$ntc3_pos 	= 0;
				$ntc4_pos 	= 0;
				$adding_position = isset($config_plate['addposition']) ? $config_plate['addposition'] : $adding_position;
			} else if (
				$parentdata['re_is_uti_panel'] == 10
				|| $parentdata['re_is_uti_panel'] == 11
				|| $parentdata['re_is_uti_panel'] == 12
				|| $parentdata['re_is_uti_panel'] == 13
			) {
				//style ...
				$max_well = 10;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 14 || $parentdata['re_is_uti_panel'] == 42) {
				$max_well = 3;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 15) {
				$max_well = 20;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 16) {
				$max_well = 6;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 17) {
				$max_well = 96;
				$first_ntc = 74;
				$second_ntc_pos = 82;
				$ntc3_pos = 90;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 18) {
				$max_well = 88;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 30) { //rpp12
				$max_well = 12;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 31) { //uti16
				$max_well = 16;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 32) { //covid
				$max_well = 381;
				$first_ntc = 1;
				$second_ntc_pos = 2;
				$ntc3_pos = 3;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 33) { //rpp3
				$max_well = 3;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 34) { //uti4
				$max_well = 4;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == -300) { //uti4
				$max_well = 62;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;

				$lastsubtype = $wpdb->get_var("select subtype from ci_rack_batches
					where re_ID = {$re_ID} order by ID desc limit 1");

				if (!empty($lastsubtype) && $lastsubtype != 'ctrl' && $lastsubtype != $subtype) {
					//insert control
					$midctrl = $wpdb->get_var("select min(mid) from ci_rack_batches 
						where mid < 0 and re_ID = {$re_ID} ") - 1;

					$wpdb->insert('ci_rack_batches', array(
						're_ID' => $re_ID,
						'btype' => $btype,
						'mid' => $midctrl,
						'subtype' => 'ctrl',
						'created' => $datetime_now,
						'created_by' => $user_id,
					));
					$count_samp++;
				}
			} else if ($parentdata['re_is_uti_panel'] == 35) { //uti/rpp/abx96
				$max_well = 95;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 36) { //uti/wound
				$max_well = 72;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 37) { //uti/wound
				$max_well = 6;
				$first_ntc = 7;
				$second_ntc_pos = 8;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 38) { //Mini Respiratory Panel
				$max_well = 180;
				$first_ntc = 371;
				$second_ntc_pos = 372;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 40) { //uti/wound
				$max_well = 7;
				$first_ntc = 8;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 41) { //rpp
				$max_well = 11;
				$first_ntc = 12;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 43) { //mini rpp
				$max_well = 190;
				$first_ntc = 381;
				$second_ntc_pos = 383;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == 44) {
				$max_well = 30;
				$first_ntc = 361;
				$second_ntc_pos = 373;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if (in_array($parentdata['re_is_uti_panel'], array(45, 46, 47, 48, 49))) {
				$max_well = 95;
				$first_ntc = 1;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if (in_array($parentdata['re_is_uti_panel'], array(50))) {
				$max_well = 32;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if (in_array($parentdata['re_is_uti_panel'], array(52, 53))) {
				$max_well = 6;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if (in_array($parentdata['re_is_uti_panel'], array(51))) {
				$max_well = 4;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			} else if ($parentdata['re_is_uti_panel'] == -1) //Respiratory Light
			{
				$return['count_samp'] = $count_samp;

				$max_well = 12;
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
				$adding_position = 1;
			} else if ($rack_max6_fun && $parentdata['re_is_uti_panel'] == -3) //fungal
			{
				$return['count_samp'] = $count_samp;
				$first_ntc = 1000;
				$second_ntc_pos = 1000;
				if ($count_samp >= 6) {
					$max_well = 6;
					$count_samp = 500;
				}
			} else if ($parentdata['re_is_uti_panel'] == -5) //GI
			{
				$return['count_samp'] = $count_samp;
				$first_ntc = 1000;
				$second_ntc_pos = 1000;
				$max_well = 28;
			} else if ($rack_uti_type == 3 && $parentdata['re_is_uti_panel'] == 1) {
				$return['count_samp'] = $count_samp;
				$first_ntc = 1000;
				$second_ntc_pos = 1000;

				if ($count_samp == 1) {
					$count_samp = $rack_ABC ? 32 : 4;
				} else if ($count_samp == 2) {
					$count_samp = $rack_ABC ? 64 : 8;
				} else if ($count_samp >= 3) {
					$max_well = 3;
					$count_samp = 500;
				}
			} else if ($rack_uti_type == 14 && $parentdata['re_is_uti_panel'] == 1) {
				$return['count_samp'] = $count_samp;
				$first_ntc = 1000;
				$second_ntc_pos = 1000;

				$max_well = 14;
			} else if ($rack_uti_type == 30 && $parentdata['re_is_uti_panel'] == 1) {
				$return['count_samp'] = $count_samp;
				$max_well = 30;
			} else if ($parentdata['re_is_uti_panel'] == -2) {
				//RPP old
				$max_well = 16;
			} else if ($parentdata['re_is_uti_panel'] == 21 || $parentdata['re_is_uti_panel'] == 22) {
				//blood FA / MF
				$max_well = 3;
				$subkey  = $parentdata['re_rack_subtype'];
				if (empty($subkey)) {
					$subkey = preg_replace("/[^VPTM]/", '', $parentdata['re_subtitle']);
					$parentdata['re_rack_subtype'] = $subkey;
				}

				if (in_array($subkey, array('M', 'V', 'P'))) {
					$max_well = 6;
				}
				$first_ntc = 0;
				$second_ntc_pos = 0;
				$ntc3_pos = 0;
				$ntc4_pos = 0;
			}

			$return['count_samp'] = $count_samp;
			$position = $position_outntc = $count_samp + $adding_position;


			if ($second_ntc_pos > $first_ntc) {
				if ($position >= $first_ntc) {
					$position++;
				}
				if ($position >= $second_ntc_pos) {
					$position++;
				}
			} else {
				if ($second_ntc_pos > 0 &&  $position >= $second_ntc_pos) {
					$position++;
				}
				if ($first_ntc > 0 &&  $position >= $first_ntc) {
					$position++;
				}
			}

			if ($ntc3_pos > 0 && $position >= $ntc3_pos) {
				$position++;
			}
			if ($ntc4_pos > 0 && $position >= $ntc4_pos) {
				$position++;
			}

			$return['first_ntc'] = $first_ntc;
			$return['second_ntc_pos'] = $second_ntc_pos;
			$return['ntc3_pos'] = $ntc3_pos;


			if ($position > $max_well) {
				$return['position'] = $position;
				$return['status']  = 'error';
				$return['message'] = 'Maximum (' . $max_well . ')';
				if (!empty($config_plate) && isset($config_plate['parts'])) {
					$subtype = intval($subtype);
					$return['message'] .= ' ' . $config_plate['parts'][$subtype];
				}
				wp_send_json($return);
			}

			$insertdata = array(
				're_ID'      => $re_ID,
				'mid'        => $batch->mid,
				'btype'      => $btype,
				'created'    => $datetime_now,
				'created_by' => $user_id,
			);
			if ($parent_id > 0) {
				$insertdata['iparent'] = $parent_id;
				$insertdata['is_racks4'] = 1;
				if ($count_child == 0) {
					$wpdb->update('ci_rack_batches', array('is_racks4' => 1), array('ID' => $parent_id));
				}
			} else if ($parent_id < 0) {
				$insertdata['iparent'] = $parent_id;
			}

			if ($pass_add) {
				$insertdata['is_rerun'] = 1;
				$batch->is_rerun        = 1;

				if (!empty($reason_rerun)) {
					$insertdata['reason_rerun'] = $reason_rerun;
				}
			}

			if (!empty($subtype)) {
				$insertdata['subtype'] = $subtype;
			}

			$wpdb->insert('ci_rack_batches', $insertdata);
			$rbid = $wpdb->insert_id;

			if ($rbid > 0) {
				$batch->ID          = $rbid;
				$return['rbid']     = $rbid;
				$return['position'] = $position;
				//update status to racked
				$update_batch = array();
				if (empty($batch->spec_received) || $batch->spec_received == '0000-00-00 00:00:00') {
					$update_batch['spec_received'] = $datetime_now;
				}
				if ($batch->batch_status == 'received' || empty($batch->batch_status)) {
					$update_batch['batch_status'] = 'racked';
				}

				if (!empty($update_batch)) {
					$wpdb->update($batch_tbl, $update_batch, array('mid' => $batch->mid));
				}

				if (!empty($batch->dob)) {
					$batch->dob = tr_pdd_display_date($batch->dob);
				}

				if ($parent_id > 0) {
					$return['add_barcode'] = $code;
					$return['mid'] = $batch->mid;
					$return['dob'] = $batch->dob;
					$return['patient_name'] = $batch->patient_name;
					$return['is_rerun'] = $batch->is_rerun ? 1 : 0;
					$return['add_to'] = $parent_id;
					$return['child_pos'] = $count_child + 2;
				} else {
					ob_start();

					$batch->well        = tr_rack_well_by_position($position, $parentdata['re_is_uti_panel']);
					$batch->other_data  = array();
					$batch->btype       = $btype;
					$batch->position    = $position_outntc;
					$batch->subtype     = $subtype;
					$batch->reason_rerun = $reason_rerun;
					trrack_display_sample_row($batch);

					$return['html'] = ob_get_clean();
				}
			} else {
				$return['error']   = $wpdb->last_error;
				$return['status']  = 'error';
				$return['message'] = 'Error. Please try again';
			}
		}
	} else {
		$return['status'] = 'error';
		$return['message'] = 'Barcode Not found';
	}
} else {
	$return['status'] = 'error';
	$return['message'] = 'Error. Please try again';
}

wp_send_json($return);
