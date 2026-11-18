<?php
global $df_batches;
$df_batches = array();
$plates_384 = array();
$config_plate = trpdd_plate_map_config($k);

if ($config_plate['type'] == 96) {
	$plates_96_wide = array(array(
		're_is_uti_panel' => $k,
		're_subtitle' => '',
		'pl_ID' => '',
		'pl_number' => '1',
		're_number' => '1',
		'sub_number' => '0',
		'pl_ntc4_position' => '',
		'pl_blank_position' => '',
		'pl_blank1_position' => '',
		'pl_blank2_position' => '',
		'pl_ntc_position' => '',
		'pl_ntc2_position' => '',
		'pl_ntc3_position' => '',
		'pl_ntc4_text' => '',
	));
	for ($i = 1; $i <= $config_plate['max']; $i++) {
		$df_batches[] = (object) array(
			'mid' => $i,
			'assen' => 'patient ' . $i,
			'btype' => 'pcr',
			'iparent' => '0',
			'is_rerun' => '0',
			'combine_position' => '0',
			'ID' => $i,
			'position' => $i,
		);
	}
	include(trpdd_get_file_path('plate', 'view_96_wide'));
} else if ($config_plate['type'] == 384) {
	$plates_384 = array(array(
		're_is_uti_panel' => $k,
		're_subtitle' => '',
		'pl_ID' => '',
		'pl_number' => '1',
		're_number' => '1',
		'sub_number' => '0',
		'pl_ntc4_position' => '',
		'pl_blank_position' => '',
		'pl_blank1_position' => '',
		'pl_blank2_position' => '',
		'pl_ntc_position' => '',
		'pl_ntc2_position' => '',
		'pl_ntc3_position' => '',
		'pl_ntc4_text' => '',
	));
	for ($i = 1; $i <= $config_plate['max']; $i++) {

		if (isset($config_plate['parts']) && count($config_plate['parts']) > 0) {
			foreach ($config_plate['parts'] as $pid => $ptxt) {
				$nump = $i + ($config_plate['max'] * $pid);
				$df_batches[] = (object) array(
					'mid' 		=> $i . '-' . $pid,
					'assen' 	=> 'patient ' . $nump . '(' . $pid . ')',
					'btype' 	=> 'pcr',
					'iparent' 	=> '0',
					'is_rerun' 	=> '0',
					'combine_position' => '0',
					'ID' 		=> $i,
					'position' => $i + ($i * $pid),
					'subtype' => $pid,
				);
			}
		} else {
			$df_batches[] = (object) array(
				'mid' 		=> $i,
				'assen' 	=> 'patient ' . $i,
				'btype' 	=> 'pcr',
				'iparent' 	=> '0',
				'is_rerun' 	=> '0',
				'combine_position' => '0',
				'ID' 		=> $i,
				'position' 	=> $i,
			);
		}
	}
	include(trpdd_get_file_path('plate', 'view_384'));
}
