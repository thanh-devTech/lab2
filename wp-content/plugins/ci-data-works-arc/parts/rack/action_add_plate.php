<?php

$return     = array('status' => 'ok', 'message' => '');
$plate_ID    = 0;

$re_ID      = $_REQUEST['re_ID'];
$rack       = $wpdb->get_row("select * from ci_racks where re_ID = '{$re_ID}' ");
$wherebrack = '';
if ($rack->re_is_uti_panel == 80) {
	$wherebrack .= " and (mid > 0) ";
}
$batches    = $wpdb->get_results("select * from ci_rack_batches where re_ID = '{$re_ID}' {$wherebrack} order by ID asc");

if (in_array($rack->re_is_uti_panel, array(45, 46, 47, 48, 49))) {
	include(trpdd_get_file_path('rack', 'add_multi_plates'));
	wp_send_json($return);
	exit;
}

$config_plate = trpdd_plate_map_config($rack->re_is_uti_panel);


$sql_plate_id = "select pl_ID from ci_plates where pl_rack_ID = '{$re_ID}' ";

if (in_array($rack->re_is_uti_panel, array(17, 18))) {
	//rpp new
	$plate_ID   = $wpdb->get_var($sql_plate_id . " and pl_blank1_text = 'plateA' ");
}

if ($plate_ID == 0) {
	$plate_ID   = $wpdb->get_var($sql_plate_id);
}

if ($re_ID > 0 && count($batches) > 0) {
	$arr = array(
		'pl_ntc_position' => $second_ntc_pos,
		'pl_ntc2_position' => $first_ntc,
	);


	if (!empty($config_plate)) {
		$arr['pl_ntc_position'] = 0;
		$arr['pl_ntc2_position'] = 0;
		$arr['pl_ntc3_position'] = 0;
		$arr['pl_ntc4_position'] = 0;
	} else if ($rack->re_is_uti_panel == -2) {
		$arr['pl_ntc_text'] = 'Negative Control';
		$arr['pl_ntc2_text'] = 'Positive Control';
	} else if ($rack->re_is_uti_panel == -3 || $rack->re_is_uti_panel == -5) {
		$arr['pl_ntc_position'] = 0;
		$arr['pl_ntc2_position'] = 0;
	} else if ($rack->re_is_uti_panel == -4) {
		$arr['pl_ntc_text'] = 'Negative Control';
		$arr['pl_ntc2_text'] = 'Positive Control';
	} else if ($rack->re_is_uti_panel == 20) {
		$arr['pl_ntc_position'] = 1;
		$arr['pl_ntc2_position'] = 2;
		$arr['pl_ntc_text'] = 'Negative Control';
		$arr['pl_ntc2_text'] = 'Positive Control';
	} else if ($rack->re_is_uti_panel == 21 || $rack->re_is_uti_panel == 22) {
		//blood FA / MF
		$arr['pl_ntc_position'] = 0;
		$arr['pl_ntc2_position'] = 0;
		$arr['pl_ntc_text'] = '';
		$arr['pl_ntc2_text'] = '';
	} else if (
		$rack->re_is_uti_panel == 10
		|| $rack->re_is_uti_panel == 11
		|| $rack->re_is_uti_panel == 12
		|| $rack->re_is_uti_panel == 13
	) {
		//style 10 or 11 or
		$arr['pl_ntc_position'] = 241;
		$arr['pl_ntc2_position'] = 265;

		$arr['pl_ntc_text'] = 'NEG';
		$arr['pl_ntc2_text'] = 'POS';
	} else if ($rack->re_is_uti_panel == 14 || $rack->re_is_uti_panel == 15 || $rack->re_is_uti_panel == 16) {
		$arr['pl_ntc_position'] = 0;
		$arr['pl_ntc2_position'] = 0;

		$arr['pl_ntc_text'] = '';
		$arr['pl_ntc2_text'] = '';
	} else if ($rack->re_is_uti_panel == 17) {
		//Rpp Plate A
		$arr['pl_ntc_position'] = $first_ntc = 75;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 83;
		$arr['pl_ntc3_position'] = $ntc3_pos = 91;

		$arr['pl_ntc_text'] = 'NTC';
		$arr['pl_ntc2_text'] = 'B. pertussis';
		$arr['pl_ntc3_text'] = 'PLASMID';
		$arr['pl_blank1_text'] = 'plateA';
	} else if ($rack->re_is_uti_panel == 18) {
		//uti-wound-88patients Plate A
		$arr['pl_ntc_position'] = $first_ntc = 0;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 0;
		$arr['pl_ntc3_position'] = $ntc3_pos = 0;
		$arr['pl_blank1_text'] = 'plateA';
	} else if (in_array($rack->re_is_uti_panel, array(30, 31, 33, 34, 35, 36, 40, 41, 42, -300))) {
		//rpp12, uti16, rpp3, uti4
		$arr['pl_ntc_position'] = $first_ntc = 0;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 0;
		$arr['pl_ntc3_position'] = $ntc3_pos = 0;
		$arr['pl_blank1_text'] = '';
	} else if ($rack->re_is_uti_panel == 32) {
		//covid381
		$arr['pl_ntc_position'] = $first_ntc = 1;
		$arr['pl_ntc3_position'] = $ntc3_pos = 2;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 3;
		$arr['pl_ntc_text'] = 'NTC';
		$arr['pl_ntc3_text'] = 'Negative control';
		$arr['pl_ntc2_text'] = 'Positive Control';
	} else if ($rack->re_is_uti_panel == 37) {
		$arr['pl_ntc_position'] = $first_ntc = 7;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 8;
		$arr['pl_ntc_text'] = 'NEG';
		$arr['pl_ntc2_text'] = 'POS';
		$arr['pl_ntc3_position'] = $ntc3_pos = 0;
		$arr['pl_ntc3_text'] = '';
	} else if ($rack->re_is_uti_panel == 38) {
		$arr['pl_ntc_position'] = $first_ntc = 371;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 372;
		$arr['pl_ntc_text'] = 'NC';
		$arr['pl_ntc2_text'] = 'PC';
		$arr['pl_ntc3_position'] = $ntc3_pos = 0;
		$arr['pl_ntc3_text'] = '';
	} else if ($rack->re_is_uti_panel == 43) {
		$arr['pl_ntc_position'] = $first_ntc = 381;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 383;
		$arr['pl_ntc_text'] = 'NC';
		$arr['pl_ntc2_text'] = 'PC';
		$arr['pl_ntc3_position'] = $ntc3_pos = 0;
		$arr['pl_ntc3_text'] = '';
	} else if ($rack->re_is_uti_panel == 44) {

		$arr['pl_ntc_position'] = $first_ntc = 361;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 373;
		$arr['pl_ntc_text'] = 'PC';
		$arr['pl_ntc2_text'] = 'NC';
		$arr['pl_ntc3_position'] = $ntc3_pos = 0;
		$arr['pl_ntc3_text'] = '';
	} else if (in_array($rack->re_is_uti_panel, array(50))) {
		$arr['pl_ntc_position'] = $first_ntc = 47;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 383;
		$arr['pl_ntc3_position'] = $ntc3_pos = 23;
		$arr['pl_blank1_text'] = '';
		$arr['pl_ntc_text'] = 'NTC';
		$arr['pl_ntc2_text'] = 'POS';
		$arr['pl_ntc3_text'] = 'NEC';
	} else if (in_array($rack->re_is_uti_panel, array(51, 52, 53))) {
		$arr['pl_ntc_position'] = $first_ntc = 0;
		$arr['pl_ntc2_position'] = $second_ntc_pos = 0;
		$arr['pl_ntc3_position'] = $ntc3_pos = 0;
		$arr['pl_ntc4_position'] = $ntc4_pos = 0;
		$arr['pl_blank1_text'] = '';
		$arr['pl_ntc_text'] = 'NC';
		$arr['pl_ntc2_text'] = 'PC';
	} else if ($rack_16) {
		$arr['pl_ntc_text'] = 'HSC';
		$arr['pl_ntc2_text'] = 'NEC';
	} else {
		$arr['pl_ntc_text'] = '';
		$arr['pl_ntc2_text'] = '';
	}

	if ($blank1_pos > 0) {
		$arr['pl_blank1_position'] = $blank1_pos;
	}
	if ($blank2_pos > 0) {
		$arr['pl_blank2_position'] = $blank2_pos;
	}
	if ($ntc3_pos > 0 && !isset($arr['pl_ntc3_position'])) {
		$arr['pl_ntc3_position'] = $ntc3_pos;
	}
	if ($ntc4_pos > 0 && !isset($arr['pl_ntc4_position'])) {
		$arr['pl_ntc4_position'] = $ntc4_pos;
	}


	if ($plate_ID == 0) {
		$pl_number = $wpdb->get_var("select max(pl_number) from ci_plates where pl_today ='{$rack->re_today}' ") + 1;

		$arr['pl_rack_ID'] = $re_ID;
		$arr['pl_today']  = $rack->re_today;
		$arr['pl_number'] = $pl_number;
		$arr['created']   = $datetime_now;
		$arr['created_by'] = $user_id;

		$wpdb->insert('ci_plates', $arr);
		$plate_ID = $wpdb->insert_id;
	} else {

		if (!isset($arr['pl_ntc3_position'])) {
			$arr['pl_ntc3_position'] = '';
		}
		if (!isset($arr['pl_ntc4_position'])) {
			$arr['pl_ntc4_position'] = '';
		}
		$wpdb->update('ci_plates', $arr, array('pl_ID' => $plate_ID));
	}

	//add batches
	if ($plate_ID > 0) {
		$batches_more = array('EX' => array(), 'B' => array(), 'C' => array(), 'D' => array());
		$position = $first_ntc == 1 ? 2 : 1;

		if (!empty($config_plate)) {
			$position = 1;
		} else if ($rack->re_is_uti_panel == -3) {
			//Fungal
			$position = 1;
		} else if ($rack->re_is_uti_panel == -1) {
			//Respiratory Light
			$position = 1;
		} else if ($rack->re_is_uti_panel == -5) {
			//GI
			$position = 1;
		} else if (in_array($rack->re_is_uti_panel, array(20))) {
			$position = 3;
		} else if (in_array($rack->re_is_uti_panel, array(32))) {
			$position = 4;
		} else if (in_array($rack->re_is_uti_panel, array(10, 11, 12, 13, 14, 30, 31, 33, 34, -300, 50, 51, 52, 53))) {
			//style ...
			$position = 1;
		} else if ($rack_uti_type == 3 && $rack->re_is_uti_panel == 1) {
			$position = 1;
		} else if ($rack_uti_type == 14 && $rack->re_is_uti_panel == 1) {
			$position = 1;
		} else if ($rack_uti_type == 30 && $rack->re_is_uti_panel == 1) {
			$position = 1;
		}

		$return['cc'] = count($batches);

		$import_num = 0;
		if ($rack->re_is_uti_panel == 21 || $rack->re_is_uti_panel == 22) {
			//FA / MF
			$import_num = $wpdb->get_var("select max(combine_position) from ci_plates_batches
				where pl_ID = {$plate_ID}") + 1;
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
		$i          = 0;
		foreach ($batches as $batch) {
			$i++;


			if (!isset($batch->iparent)) {
				$batch->iparent = 0;
			}

			if ($rack->re_is_uti_panel == 17) {
				//plate B continue;
				if ($i == $first_ntc) $i++;
				if ($i == $second_ntc_pos) $i++;
				if ($i == $ntc3_pos) $i++;


				if (($i - 1) % 8 >= 4) {
					$batches_more['B'][] = $batch;
					continue;
				}
			} else if ($rack->re_is_uti_panel == 18) {
				$batches_more['EX'][] = $batch;

				if ($i > 44) {
					$batches_more['B'][] = $batch;
					continue;
				}
			}

			if (!empty($config_plate)) {
				$position = $i;
			} else if (
				$rack->re_is_uti_panel == 10
				|| $rack->re_is_uti_panel == 11
				|| $rack->re_is_uti_panel == 12
				|| $rack->re_is_uti_panel == 13
			) {
				$position = ($i - 1) * 24 + 1;
			} else if ($rack->re_is_uti_panel == 14) {
				$position = $i;
				if ($i == 2) $position = 5;
				else if ($i == 3) $position = 9;
			} else if ($rack->re_is_uti_panel == 15) {
				if ($i < 9) {
					$position = ($i + 7) * 24 + 1;
				} else {
					$position = ($i - 9) * 24 + 13;
				}
			} else if ($rack->re_is_uti_panel == 16) {
				$position = ($i - 1) * 2 + 1;
			} else if ($rack->re_is_uti_panel == 21 || $rack->re_is_uti_panel == 22) {
				//FA / MF
				$rowp = 24;
				$subkey = $rack->re_rack_subtype;
				if (empty($subkey)) {
					$subkey = preg_replace("/[^VPTM]/", '', $rack->re_subtitle);
				}

				if (in_array($subkey, array('M', 'V', 'P', 'VT', 'PT'))) {
					$rowp = 12;
				}
				$position = ($i - 1) * $rowp + 1;
			} else if ($rack->re_is_uti_panel == -2) {
				//RPP old
				$position = $i;
				if ($second_ntc_pos > 0) {
					$position++;
				}
				if ($first_ntc > 0) {
					$position++;
				}
				if ($position >= 9) {
					$position += 40;
				}
			} else if ($rack->re_is_uti_panel == -1) {
				//Respiratory Light
				$position = $rack_ABC ? (($i - 1) * 8) + 1 : $i;
			} else if ($rack_max6_fun && $rack->re_is_uti_panel == -3) {
				//RPP old
				$position = $rack_ABC ? (($i - 1) * 16) + 1 : $i;
			} else if ($rack->re_is_uti_panel == -5) {
				//GI
				$position = floor(($i - 1) / 2) * 24 + (($i - 1) % 2) + 1;
			} else if ($rack_uti_type == 3 && $rack->re_is_uti_panel == 1) {
				$position = $i;
				if ($i == 2) {
					$position = $rack_ABC ? 33 : 5;
				} else if ($i == 3) {
					$position = $rack_ABC ? 65 : 9;
				}
			} else if ($rack_uti_type == 14 && $rack->re_is_uti_panel == 1) {
				$position = $i;
			} else if ($rack_uti_type == 30 && $rack->re_is_uti_panel == 1) {
				$position = floor(($i - 1) / 4) * 12 + (($i - 1) % 4) + 1;
			} else if ($batch->iparent == 0) {
				if ($position == $second_ntc_pos || $position == $first_ntc) {
					$position++;
				}
				if ($position == $second_ntc_pos || $position == $first_ntc) {
					$position++;
				}
			}



			$rowkey = $batch->mid . '_' . $batch->iparent . '_' . $batch->subtype;
			$rowpb = isset($exist_rows[$rowkey]) ? $exist_rows[$rowkey] : false;
			$pbid = $rowpb && $rowpb->ID > 0 ? $rowpb->ID : 0;
			if ($pbid > 0) {
				$updatedata = array(
					'position' => $batch->iparent > 0 ? $position - 1 : $position
				);

				if ($rack->re_is_uti_panel == 21 || $rack->re_is_uti_panel == 22) {
					// FA / MF
					if (!isset($exist_com_positions[1]) && !isset($exist_com_positions[2])) {
						$updatedata['combine_position'] = 1;
					} else if (!isset($exist_com_positions[2])) {
						$updatedata['combine_position'] = $rowpb->combine_position > 2 ? 2 : 1;
					} else if (!isset($exist_com_positions[1])) {
						$updatedata['combine_position'] = $rowpb->combine_position - 1;
					}
				}

				if (isset($batch->subtype) && !empty($batch->subtype)) {
					$updatedata['subtype'] = $batch->subtype;
				}
				if (isset($batch->is_rerun)) {
					$updatedata['is_rerun'] = $batch->is_rerun;
				}
				$updatedata['rb_ID'] = $batch->ID;

				$wpdb->update('ci_plates_batches', $updatedata, array('ID' => $pbid));
			} else {

				$insertdata = array(
					'pl_ID'      => $plate_ID,
					'mid'        => $batch->mid,
					'btype'      => $batch->btype,
					'position'   => $batch->iparent > 0 ? $position - 1 : $position,
					'created'    => $datetime_now,
					'created_by' => $user_id,
					'rb_ID' => $batch->ID,
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

				if ($rack->re_is_uti_panel == 21 || $rack->re_is_uti_panel == 22) {
					// FA / MF
					$insertdata['combine_position'] = $import_num;
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

			//update batch_status
			if ($pbid > 0) {
				$batch_tbl = tr_pdd_get_table_by_btype($batch->btype);
				$wpdb->update($batch_tbl, array('batch_status' => 'plated'), array('mid' => $batch->mid));
			}
		}

		//remove out
		$notinids = implode(',', $parentplb);
		$wpdb->query("DELETE FROM ci_plates_batches where pl_ID = {$plate_ID}
			and ID not in ({$notinids})");


		if ($rack->re_is_uti_panel == 17) {

			//do plateB
			include(trpdd_get_file_path('rack', 'add_plate_b'));

			//do plate Accufill
			include(trpdd_get_file_path('rack', 'add_plate_accufill'));
		} else if ($rack->re_is_uti_panel == 18) {

			include(trpdd_get_file_path('rack', 'add_plate_extraction'));
			//do plateB
			include(trpdd_get_file_path('rack', 'add_plate_b'));

			//include(trpdd_get_file_path('rack', 'add_plate_accufill'));
		} else if ($rack->re_is_uti_panel == 35) {
			$wpdb->update('ci_plates', array(
				'pl_ntc_position' => count($batches) + 1,
				'pl_ntc_text' => 'Extraction Control',
			), array('pl_ID' => $plate_ID));
		}
	} else {
		$return['status'] = 'error';
	}
} else {
	$return['status'] = 'error';
	$return['message'] = 'This Rack is empty';
}

wp_send_json($return);
