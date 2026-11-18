<?php
global $wpdb;
//$return;
$maxnumperplate = 5; //45

if (in_array($rack->re_is_uti_panel, array(46))) {
	$maxnumperplate = 29;
}
else if (in_array($rack->re_is_uti_panel, array(47, 48))) {
	$maxnumperplate = 13;
} else if ($rack->re_is_uti_panel == 49) {
	$maxnumperplate = 45;
}
$count_plates = ceil(count($batches) / $maxnumperplate);

$sql_plates = "select pl_ID from ci_plates where pl_rack_ID = '{$re_ID}' order by pl_ID asc";

$plate_IDs   = $wpdb->get_col($sql_plates);

$arr = array();


if (in_array($rack->re_is_uti_panel, array(45, 46, 47, 48, 49))) {
	$arr['pl_ntc_position'] = $first_ntc = 2;
	$arr['pl_ntc2_position'] = $second_ntc_pos = 1;
	$arr['pl_ntc3_position'] = $ntc3_pos = 3;
	$arr['pl_ntc_text'] = 'NTC';
	$arr['pl_ntc2_text'] = 'PTC';
	$arr['pl_ntc3_text'] = 'NEC';
}

$currentbpos = -1;

for ($ipl = 0; $ipl < $count_plates; $ipl++) {
	$plate_ID = isset($plate_IDs[$ipl]) ? $plate_IDs[$ipl] : 0;

	if ($plate_ID == 0) {
		$pl_number = $wpdb->get_var("select max(pl_number) from ci_plates where pl_today ='{$rack->re_today}' ") + 1;

		$arr['pl_rack_ID'] = $re_ID;
		$arr['pl_today']  = $rack->re_today;
		$arr['pl_number'] = $pl_number;
		$arr['created']   = $datetime_now;
		$arr['created_by'] = $user_id;

		$wpdb->insert('ci_plates', $arr);
		$plate_ID = $wpdb->insert_id;
	}

	$exist_rows_rs = $wpdb->get_results("
            select ID,
            mid,
            combine_position,
            iparent,
            subtype
			from ci_plates_batches 
			where pl_ID = {$plate_ID}");
	$exist_rows = array();
	$exist_com_positions = array();

	foreach ($exist_rows_rs as $erow) {
		if (empty($erow->iparent)) $erow->iparent = 0;
		$rowkey = $erow->mid . '_' . $erow->iparent . '_' . $erow->subtype;
		$exist_rows[$rowkey] = $erow;
		if (!isset($exist_com_positions[$erow->combine_position])) {
			$exist_com_positions[$erow->combine_position] = 0;
		}
		$exist_com_positions[$erow->combine_position]++;
	}


	$parentplb  = array();
	$position = 1;
	$numbat = 0;
	if (in_array($rack->re_is_uti_panel, array(45, 46, 47, 48, 49))) {
		$position = 4;
	}
	foreach ($batches as $ib => $batch) {

		if ($ib <= $currentbpos) continue;
		if ($numbat >= $maxnumperplate) break;

		if ($rack->re_is_uti_panel == 46) {
			if ($position == 9) $position = 25;
			if ($position == 33) $position = 49;
			if ($position == 57) $position = 73;
		} else if ($rack->re_is_uti_panel == 47) {
			if ($position == 9) $position = 17;
		} else if ($rack->re_is_uti_panel == 48) {
			if ($position == 9) $position = 49;
		}


		if ($position == $second_ntc_pos || $position == $first_ntc || $position == $ntc3_pos) {
			$position++;
		}
		if ($position == $second_ntc_pos || $position == $first_ntc || $position == $ntc3_pos) {
			$position++;
		}


		$rowkey = $batch->mid . '_' . $batch->iparent . '_' . $batch->subtype;
		$rowpb = isset($exist_rows[$rowkey]) ? $exist_rows[$rowkey] : false;
		$pbid = $rowpb && $rowpb->ID > 0 ? $rowpb->ID : 0;


		if ($pbid > 0) {
			$updatedata = array(
				'position' => $batch->iparent > 0 ? $position - 1 : $position
			);
			if (isset($batch->subtype) && !empty($batch->subtype)) {
				$updatedata['subtype'] = $batch->subtype;
			}
			if (isset($batch->is_rerun)) {
				$updatedata['is_rerun'] = $batch->is_rerun;
			}
			$wpdb->update('ci_plates_batches', $updatedata, array('ID' => $pbid));
		} else {
			$insertdata = array(
				'pl_ID'      => $plate_ID,
				'mid'        => $batch->mid,
				'btype'      => $batch->btype,
				'position'   => $batch->iparent > 0 ? $position - 1 : $position,
				'created'    => $datetime_now,
				'created_by' => $user_id,
			);

			if (isset($batch->subtype) && !empty($batch->subtype)) {
				$insertdata['subtype'] = $batch->subtype;
			}
			if (isset($batch->is_rerun)) {
				$insertdata['is_rerun'] = $batch->is_rerun;
			}

			if (isset($batch->iparent)) {
				if ($batch->iparent > 0) {
					$insertdata['iparent'] = $parentplb[$batch->iparent];
				} else if ($batch->iparent < 0) {
					$insertdata['iparent'] = $batch->iparent;
				}
			}

			if (isset($batch->is_racks4) && $batch->is_racks4 > 0) {
				$insertdata['is_racks4'] = 1;
			}


			$rsi = $wpdb->insert('ci_plates_batches', $insertdata);
			if ($rsi === false) {
				$return['errors'][] = $wpdb->last_error;
			}
			$pbid = $wpdb->insert_id;
		}

		$parentplb[$batch->ID] = $pbid;

		$return['ids'][] = $pbid;

		if ($batch->iparent <= 0) {
			$position++;
		}
		$currentbpos = $ib;
		$numbat++;
	}


	//remove out
	$notinids = implode(',', $parentplb);
	$wpdb->query("DELETE FROM ci_plates_batches where pl_ID = {$plate_ID}
 	and ID not in ({$notinids})");
}
