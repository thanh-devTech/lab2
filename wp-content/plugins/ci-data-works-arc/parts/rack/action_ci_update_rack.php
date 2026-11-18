<?php

$return = array('status' => 'ok', 'newdata' => array());

$data 	= $_REQUEST['data'];


if(isset($data['re_rack_type']) && count($data)<2){
	
	wp_send_json($return);
}

$data['re_panel_id'] = isset($data['re_panel_id']) ?  $data['re_panel_id']: 0;
$data['re_barcode'] = isset($data['re_barcode']) ? trim($data['re_barcode']) : '';
$data['re_panel_style'] = 1;


if (strpos($data['re_panel_id'], '_') > 0) {
	list($data['re_panel_id'], $data['re_panel_style']) = explode('_', $data['re_panel_id']);
}
$data['re_panel_id'] = intval($data['re_panel_id']);
if ($data['re_panel_id'] != 0) {
	$data['re_is_uti_panel'] = trpdd_get_rack_panel_type($data['re_panel_id'], $data['re_panel_style']);

	$return['re_is_uti_panel'] = $data['re_is_uti_panel'];
} else {
	$return['re_is_uti_panel'] = 'none';
}

$rack = false;
$has_subtitle = false;
$has_priority = false;

if (isset($data['re_ID']) && $data['re_ID'] > 0) {
	$rack = $wpdb->get_row("select * from ci_racks where re_ID = '{$data['re_ID']}' ");

	if ($rack) {
		$rack->re_subtitle = trim($rack->re_subtitle);
		if (!empty($rack->re_subtitle)) {
			$has_subtitle = true;
		}

		if (!empty($rack->re_priority)) {
			$has_priority = true;
		}

		//update type in plate
		if (isset($data['re_is_uti_panel']) && !empty($data['re_is_uti_panel']) && $data['re_is_uti_panel'] != $rack->re_is_uti_panel) {
		}
	}
}


if ($plate_coating && !empty($data['re_barcode']) && (!$has_subtitle || !$has_priority)) {
	$coating = $wpdb->get_row("select * from ci_plate_coating where barcode = '{$data['re_barcode']}' ");

	if ($coating) {

		$year             = date_i18n('y');
		if ($year == 24) {
			$year = 'T24';
		}
		$cache_key         = 'ci_plate_coating_numbers_' . $year;
		$cache_numbers = get_option($cache_key, array());

		if (!$has_subtitle) {
			$pretitle = $coating->type;
			if ($coating->type == 'vcam') {
				$pretitle = 'V';
			} else if ($coating->type == 'psel') {
				$pretitle = 'P';
			} else if ($coating->type == 'mixed') {
				$pretitle = 'M';
			} else if ($coating->type == 'VPV') {
				$pretitle = 'VP';
			}
			$pretitle     = strtoupper($pretitle);

			$number     = '';

			if (!isset($cache_numbers[$pretitle]) || empty($cache_numbers[$pretitle])) {
				//find last number
				$arrnum = $wpdb->get_row("
					select cast(replace(replace(re_subtitle,'-{$year}',''),'{$pretitle}','') as UNSIGNED) as number,
						re_subtitle
					from ci_racks 
					where re_subtitle REGEXP '{$pretitle}[0-9]+' or re_subtitle REGEXP '{$pretitle}[0-9]+-[0-9]+' 
					order by number desc", ARRAY_A);

				$number = intval($arrnum['number']);
				$yearoldarr = explode('-', $arrnum['re_subtitle']);
				$yearold = isset($yearoldarr[1]) && $yearoldarr[1] > 0 ? $yearoldarr[1] : $year;
				if ($yearold != $year) {
					$number = 0;
				}

				$cache_numbers[$pretitle] = $number;
			} else {
				$number = $cache_numbers[$pretitle];
			}


			$number++;
			$number = sprintf("%03d", $number);
			$return['number'] = $number;
			$subt = $pretitle . $number . '-' . $year;
			//check exist
			for ($ice = 0; $ice < 1000; $ice++) {
				$subt = $pretitle . $number . '-' . $year;

				$subexists = $wpdb->get_var("select count(*) from ci_racks
					where re_subtitle = '{$subt}' ");
				if (!$subexists) {
					break;
				} else {
					$number++;
				}
			}
			$cache_numbers[$pretitle] = $number;
			update_option($cache_key, $cache_numbers);

			$return['newdata']['re_subtitle'] = $subt;
			$data['re_subtitle'] = $subt;
			$data['re_rack_subtype'] = $pretitle;
		}

		if (!$has_priority) {
			$return['newdata']['re_priority'] = $coating->service_date;
			$data['re_priority'] = $return['newdata']['re_priority'];
		}
	}
}




if (!isset($data['re_ID']) || empty($data['re_ID'])) {
	if (empty($data['re_today'])) {
		$data['re_today'] = date_i18n('Y-m-d');
	}

	if (empty($data['re_number'])) {
		$data['re_number'] = $wpdb->get_var("select max(re_number) from ci_racks where re_today = '{$data['re_today']}' ") + 1;
	}

	$data['re_created'] = date_i18n('Y-m-d H:i:s');
	$data['re_author_id'] = $user_id;

	$wpdb->insert('ci_racks', $data);
	$return['re_ID'] = $wpdb->insert_id;
} else {
	unset($data['re_number']);

	if (isset($data['re_kind']) && $rack->re_kind != $data['re_kind']) {
		$countb = $wpdb->get_var("select count(*) from ci_rack_batches where re_ID = {$data['re_ID']}");
		$return['$countb'] = $countb;
		if ($countb > 0) {
			$return['status'] = 'error_kind';
			$return['kind'] = $rack->re_kind;
			$return['html'] = 'Cant change Kind when Rack not empy!';
			wp_send_json($return);
		}
	}

	if (empty($rack->re_today)) {
		$data['re_today'] = date_i18n('Y-m-d');
	}
	if (empty($rack->re_number)) {
		$data['re_number'] = $wpdb->get_var("select max(re_number) from ci_racks where re_today = '{$data['re_today']}' ") + 1;
	}

	$rs = $wpdb->update('ci_racks', $data, array('re_ID' => $data['re_ID']));
}


wp_send_json($return);
