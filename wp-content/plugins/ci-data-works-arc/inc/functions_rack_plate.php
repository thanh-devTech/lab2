<?php
function trpdd_sd_square($x, $mean)
{
	return pow($x - $mean, 2);
}
function trpdd_stats_standard_deviation($array)
{
	foreach ($array as $k => $vl) {
		if ($vl === 'NA') {
			unset($array[$k]);
		}
	}
	return sqrt(array_sum(array_map("trpdd_sd_square", $array, array_fill(0, count($array), (array_sum($array) / count($array))))) / (count($array) - 1));
}

function trpdd_cal_avegare($arr)
{
	foreach ($arr as $k => $vl) {
		if ($vl === 'NA') {
			unset($arr[$k]);
		}
	}
	return array_sum($arr) / count($arr);
}

function trpdd_get_file_export($pl_IDs, $com_IDs = '', $type)
{
	global $wpdb;
	if (isset($com_IDs[0]) && $com_IDs[0] != '') {
		$pl_combine = $wpdb->get_row("select *
                from ci_plates_combine
                where ID ='{$com_IDs[0]}'");

		$plates = $wpdb->get_results("select pl_ID,pl_number  from ci_plates 
		                    where pl_combine_ID = {$com_IDs[0]}
		                    order by pl_number asc", ARRAY_A);
		$ids = array();
		$plnums = array();
		foreach ($plates as $plate) {
			$ids[] = $plate['pl_ID'];
			$plnums[] = $plate['pl_number'];
		}
		$plateString = implode(",", $ids);
		$plateFile = implode(" ", $plnums);

		$racksRaw = $wpdb->get_results("select pl_rack_ID
                from ci_plates pl
                where pl_ID in ({$plateString})
                order by pl_number asc");

		$racks = array();
		foreach ($racksRaw as $rack) {
			$racks[] = $rack->pl_rack_ID;
		}

		$rackString = implode(" ", $racks);
		$filename = $pl_combine->pl_today . ' Plate ' . $plateFile . ' Rack ' . $rackString . ' ' . $type . ' export.csv';
	} else {

		$sql = "select pl.pl_number,
				pl.pl_today,
                ra.re_number,
                ra.re_subtitle,
                ra.re_rack_subtype
                from ci_plates pl
                left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
                where pl_ID ='{$pl_IDs[0]}'
                ";
		$result = $wpdb->get_row($sql);

		$typefinal = 'Elution Plate';
		if (stripos($type, 'Final Plate') !== false) {
			list($typefinal, $type) = explode('-', $type);
		}
		$filename = $result->pl_today . ' ' . $typefinal . ' ' . $result->pl_number . ' Rack ' . $result->re_number . ' ' . $result->re_subtitle . ' ' . $type . ' export.csv';
	}

	return $filename;
}
function trpdd_get_assens_plate($plate_array, $is_combine = false, $assens = array())
{
	global $wpdb, $df_batches, $ci_lab_count_plate_batches;

	if (!is_array($plate_array)) {
		$plate_array = (array)$plate_array;
	}

	$otherdata      = !empty($plate_array['other_data']) ? json_decode($plate_array['other_data'], true)  : array();
	$rack_ABC           = tr_get_option('ci_pdf_data', 'rack_ABC');
	$is_384 			= isset($plate_array['is_384']) && $plate_array['is_384'];
	$is_96 				= isset($plate_array['is_96']) && $plate_array['is_96'];
	$plate_array['is_wide'] = isset($plate_array['is_wide']) ? $plate_array['is_wide'] : false;

	$plate_controls = array(
		'neg' => 'Covid-19 Negative IC',
		'pos' => 'Covid-19 Positive IC'
	);


	$rack_max6_fun      = tr_get_option('ci_pdf_data', 'rack_max6_fun');
	$rack_uti_type      = tr_get_option('ci_pdf_data', 'rack_uti_type');

	$config_plate = trpdd_plate_map_config($plate_array['re_is_uti_panel']);


	if ($plate_array['re_is_uti_panel'] == -300) {
		$plate_array['pl_ntc_position'] = 0;
		$plate_array['pl_ntc2_position'] = 0;
	} else if ($plate_array['re_is_uti_panel'] == -1) {
		//Respiratory Light
		$plate_array['pl_ntc_position'] = 0;
		$plate_array['pl_ntc2_position'] = 0;
	} else if ($plate_array['re_is_uti_panel'] == -3) {
		//Fungal
		$plate_array['pl_ntc_position'] = 0;
		$plate_array['pl_ntc2_position'] = 0;
	} else if ($plate_array['re_is_uti_panel'] == -5) {
		//GI
		$plate_controls = array(
			'neg' => 'NC',
			'pos' => 'PC'
		);

		$plate_array['pl_ntc_position'] = 0;
		$plate_array['pl_ntc2_position'] = 0;
		for ($i = 1; $i <= 22; $i++) {
			if ($i % 2 == 0) continue;
			$pos = $i + 336;
			$num = floor(($i + 1) / 2);
			$char = chr((($i + 1) % 2) + 65);
			if ($i == 24) {
				$num .= ' RPP';
			}
			$assens[$pos] = array(
				'assen' => 'POS ' . $char . $num,
				'class' => 'positive center bold',
			);
			$pos = $i + 360;
			$assens[$pos] = array(
				'assen' => 'NEG ' . $char . $num,
				'class' => 'negative center bold',
			);
		}
	} else if ($rack_uti_type == 3 && $plate_array['re_is_uti_panel'] == 1) {
		$plate_array['pl_ntc_position'] = 0;
		$plate_array['pl_ntc2_position'] = 0;
	} else if ($rack_uti_type == 14 && $plate_array['re_is_uti_panel'] == 1) {
		$plate_array['pl_ntc_position'] = 0;
		$plate_array['pl_ntc2_position'] = 0;
		for ($i = 1; $i <= 24; $i++) {
			$pos = $i + 336;
			$num = floor(($i + 1) / 2);
			$char = chr((($i + 1) % 2) + 65);
			if ($i == 24) {
				$num .= ' RPP';
			}
			$assens[$pos] = array(
				'assen' => 'POS ' . $char . $num,
				'class' => 'positive center bold',
			);
			$pos = $i + 360;
			$assens[$pos] = array(
				'assen' => 'NEG ' . $char . $num,
				'class' => 'negative center bold',
			);
		}
	} else if ($rack_uti_type == 30 && $plate_array['re_is_uti_panel'] == 1) {
		$assens[87] = array(
			'assen' => 'NC ',
			'class' => 'negative',
		);

		$assens[88] = array(
			'assen' => 'PC',
			'class' => 'positive',
		);

		$assens[91] = array(
			'assen' => 'NC ',
			'class' => 'negative',
		);

		$assens[92] = array(
			'assen' => 'PC',
			'class' => 'positive',
		);

		$assens[95] = array(
			'assen' => 'NC ',
			'class' => 'negative',
		);

		$assens[96] = array(
			'assen' => 'PC',
			'class' => 'positive',
		);
	} else if ($plate_array['re_is_uti_panel'] == 15) {
		for ($i1 = 1; $i1 <= 6; $i1++) {
			for ($j1 = 1; $j1 <= 4; $j1++) {
				$pos1 = ($j1 - 1) * 24 + $i1;
				$assens[$pos1] = array(
					'assen' => 'POS QC',
					'class' => 'positive',
				);
			}
			for ($j1 = 5; $j1 <= 9; $j1++) {
				$pos1 = ($j1 - 1) * 24 + $i1;
				$assens[$pos1] = array(
					'assen' => 'NEG QC',
					'class' => 'negative',
				);
			}
		}
	}

	///------

	if ($plate_array['re_is_uti_panel'] == 1) {
		$plate_controls = array(
			'neg' => 'NC',
			'pos' => 'PC'
		);
	}


	$more_q_field = '';

	if (in_array($plate_array['re_is_uti_panel'], array('-300', '21', '22', '99'))) {
		$more_q_field .= ', rb.subtype ';
	}

	$child_sql = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$child_sql[] = "(select rb.mid,
								ba.assen,
		                        ba.patient_name,
		                        ba.dob,
                                rb.position, 
                                rb.combine_position,
                                rb.ID, 
                                rb.btype,
                                rb.created,
                                rb.iparent,
                                rb.is_rerun,
								rb.rb_ID
                                {$more_q_field}
                            from ci_plates_batches rb
                            LEFT JOIN {$batch_tbl} ba ON rb.mid=ba.mid 
                        	where rb.pl_ID = '{$plate_array['pl_ID']}' 
							AND ( ba.mid > 0 or rb.mid <0)
							and rb.btype='{$btype}') ";
	}

	$child_sql = implode(' UNION ALL ', $child_sql);

	$sql = "select * from ({$child_sql}) as ball
	            order by ball.ID asc";

	if (isset($plate_array['sub_number']) && $plate_array['sub_number'] > 0) {
		$limfr = $plate_array['subwellsplate'] * ($plate_array['sub_number'] - 1);
		$sql .= " limit {$limfr},{$plate_array['subwellsplate']}";
	}
	$batches = $wpdb->get_results($sql);

	$ci_lab_count_plate_batches = count($batches);

	if (empty($batches) && is_array($df_batches) && !empty($df_batches)) {
		$batches = $df_batches;
	}


	$posids 		= array();
	$count_group_21 = array();
	$last_pos 		= 0;
	$last_com_pos 	= 0;
	$group_row 		= 0;
	$rownum 		= 8;
	$columnnum 		= 12;
	$minu_pos		= 0;
	$last_batch_pos	= 0;
	$posntc_arr 	= array();
	$platetype_current_pos 	= array();

	if ($plate_array['re_is_uti_panel'] == 21) {
		foreach ($batches as $batch) {
			$count_group_21[$batch->combine_position]++;
		}
	}


	if (!empty($config_plate)) {
		$plate_controls = array();


		//checkbuild for adding96
		if (isset($config_plate['type']) && $config_plate['type'] == 96 && isset($plate_array['sub_number']) && $plate_array['sub_number'] > 0) {
			$config_plate = array_merge($config_plate, $config_plate['add96map']);
		}

		$posntc_arr = array($config_plate['ntc'], $config_plate['nec'], $config_plate['pos'], $config_plate['blank']);
		sort($posntc_arr);

		if (isset($plate_array['sub_number'])) {
			$config_plate['rack'] = $config_plate['type'];
			if ($plate_array['sub_number'] > 1) {
				$minu_pos = ($plate_array['sub_number'] - 1) * $plate_array['subwellsplate'];
			}
			if (isset($config_plate['subnextwellbycol'])) {
				$config_plate['nextwellbycol'] = $config_plate['subnextwellbycol'];
			}
		}


		if ($config_plate['type'] == 384 && !$is_96) {
			$is_384 = true;
			$rownum 	= 16;
			$columnnum 	= 24;
		}

		if (isset($plate_array['is_add384']) && $plate_array['is_add384']) {
			$is_384  = false;
			$plate_array['is_384'] = false;
			$is_96 = true;
			$plate_array['is_wide'] = $config_plate['is_wide'];
		}
	}

	foreach ($batches as $batch) {

		if (isset($batch->subtype) && $batch->subtype == 'ctrl') {
			$batch->assen = 'Controls';
		} else if ($batch->mid < 0) {
			$batch->assen = 'Placeholder';

			if ($plate_array['re_is_uti_panel'] == 80) {
				if ($batch->mid == -11) {
					$batch->assen = 'MOL00001001';
				} else if ($batch->mid == -12) {
					$batch->assen = 'MOL00001002';
				}
			}
		}
		$batch->other_data = array();
		if ($batch->btype == 'study') {
			//get other data from rack
			$batch->other_data = $wpdb->get_var("select other_data from ci_rack_batches where ID = {$batch->rb_ID}");

			$batch->other_data = json_decode($batch->other_data, true);
			if (!is_array($batch->other_data)) $batch->other_data = array();
			$batch->other_data['vp_type_name'] = '';
			$vp_typeid = isset($batch->other_data['vp_type']) ? $batch->other_data['vp_type'] : 0;
			if ($vp_typeid > 0) {
				$testkey = $batch->subtype == 'V' ? 'vcam' : 'psel';

				$batch->other_data['vp_type_name'] = $wpdb->get_var("select abrev from ci_study_tests 
							where abrev like '%{$testkey}%'
							and ID in ({$vp_typeid})");
			}
		}

		$arr = array(
			'mid'		=> $batch->mid,
			'assen'     => $batch->assen,
			'btype'     => $batch->btype,
			'iparent'     => $batch->iparent,
			'class'     => '',
			'is_rerun'  => $batch->is_rerun,
			'pass' 		=> null,
			'combine_position'  => $batch->combine_position,
			'other_data' => $batch->other_data,
			'childs'    => array()
		);

		if ($plate_array['re_is_uti_panel'] == 82) {
			//check result pass
			$passpn = $wpdb->get_var("select `pos-neg` as pass from ci_results_req rs
			JOIN ci_reports_req re ON re.id = rs.report_id
			where re.patient_id = '{$batch->assen}'
			and drug = '_dx11_{$plate_array['pl_ID']}' ");
			if (!empty($passpn)) {
				$arr['pass'] = $passpn == 'POS';

				$passpn2 = $wpdb->get_row("select `pos-neg` as pass,comments from ci_results_req rs
				JOIN ci_reports_req re ON re.id = rs.report_id
				where re.patient_id = '{$batch->assen}'
				and drug = '_dx11_pass_{$plate_array['pl_ID']}' ");
				if ($passpn2 && $passpn2->pass == 'POS') {
					$arr['pass_qc1'] = true;
					$arr['comment_pass_qc1'] = $passpn2->comments;
				}
			}



			if (isset($plate_array['sub_number']) && $plate_array['sub_number'] > 0) {
				//ddPCR
				if (isset($arr['pass_qc1'])) {
					//no thing
				} else if (!$arr['pass']) {
					$minu_pos++;
					continue;
				}
			} else if ($arr['pass'] === false) {
				$arr['class'] = 'dx11_fail'; //HpaII
			}
		}

		if (isset($batch->subtype)) {
			$arr['subtype'] = $batch->subtype;
		}
		if ($plate_array['re_is_uti_panel'] == 21) {
			$arr['count_group'] = $count_group_21[$batch->combine_position];
		}

		$pos = $is_combine ? $batch->combine_position : $batch->position;
		$last_batch_pos = $pos;


		if (!empty($config_plate)) {


			$pos -= $minu_pos;

			//2 panels in one plate
			if (isset($config_plate['parts']) && count($config_plate['parts']) > 0) {
				if (!isset($platetype_current_pos[$batch->subtype])) {
					$platetype_current_pos[$batch->subtype] = 1;
				}

				$pos = (intval($batch->subtype) * 14) + $platetype_current_pos[$batch->subtype]++;
			}

			$layout_wells = trpdd_plate_patient_block_wells($config_plate, $pos);
			if (!empty($layout_wells)) {
				foreach ($layout_wells as $layout_pos) {
					$assens[$layout_pos] = $arr;
				}
				$last_pos = end($layout_wells);
				continue;
			}

			if (!empty($config_plate['sample_blocks'])) {
				$layout_wells = array();
				foreach ($config_plate['sample_blocks'] as $sample_block) {
					$start = isset($sample_block['start']) ? intval($sample_block['start']) : 0;
					$end = isset($sample_block['end']) ? intval($sample_block['end']) : 0;
					if ($pos < $start || $pos > $end) {
						continue;
					}

					$col_offset = isset($sample_block['col_offset']) ? intval($sample_block['col_offset']) : 0;
					$col = $pos + $col_offset;
					$row = intval($sample_block['row']);
					for ($target_i = 0; $target_i < intval($config_plate['wellperpatient']); $target_i++) {
						$layout_wells[] = (($row + $target_i - 1) * 24) + $col;
					}
					break;
				}

				if (!empty($layout_wells)) {
					foreach ($layout_wells as $layout_pos) {
						$assens[$layout_pos] = $arr;
					}
					$last_pos = end($layout_wells);
					continue;
				}
			}

			if (isset($config_plate['precol']) && $config_plate['precol'] > 0) {
				$pos += $config_plate['precol'];
			}

			if (isset($_GET['debug1']) && $pos == 1) {
				echo '<div class="clear"></div><br><br><br><br>';
			}

			foreach ($posntc_arr as $ppn) {
				if ($ppn > 0) {

					if ($pos >= $ppn) {
						if (isset($_GET['debug1']) && $pos == $ppn) {
							echo '=> adding pos: ' . $pos . '<br>';
						}
						$pos++;
					}
				}
			}



			if ((isset($config_plate['nextwellbycol']) && $config_plate['nextwellbycol'] > 0)
				|| (isset($config_plate['nextwellbyrow']) && $config_plate['nextwellbyrow'] > 0)
			) {
				$rpos 		= $pos;
				if ($rack_ABC && (!isset($config_plate['rackabc']) || (isset($config_plate['rackabc']) && $config_plate['rackabc']))) {
					$max_fill_rows 	= isset($config_plate['max_fill_rows']) && $config_plate['max_fill_rows'] > 0 ? $config_plate['max_fill_rows'] : $rownum;

					$nexbyrow	 	= $config_plate['nextwellbyrow'] + (isset($config_plate['nextblankbyrow']) ? $config_plate['nextblankbyrow'] : 0);

					if ($nexbyrow == 0) $nexbyrow = 1;

					$nexbycol	 = $config_plate['nextwellbycol'] + (isset($config_plate['nextblankbycol']) ? $config_plate['nextblankbycol'] : 0);
					$addingbycol = floor(($pos - 1) / ($max_fill_rows / $nexbyrow)) * $rownum * ($nexbycol - 1);
					$addingbyrow = ($pos - 1) * $nexbyrow + 1;

					if ($max_fill_rows < $rownum) {
						$addingbycol = floor(($pos - 1) / ($max_fill_rows / $nexbyrow)) * $rownum * ($nexbycol);
						$addingbyrow = (($pos - 1) % ($max_fill_rows / $nexbyrow)) * $nexbyrow + 1;
					}

					$posabc 	 = $addingbycol + $addingbyrow;
					$posabc1 	 = $posabc;

					if (isset($config_plate['odd_even']) && $config_plate['odd_even']) {

						$posabc = (($posabc - 1) % ($max_fill_rows / 2)) * 2  + floor(($posabc - 1) % 16 / 8)  + (floor(($posabc - 1) / 16) * 16) + 1;
					}

					$rpos = trpdd_pos_abc2aaa($posabc, $is_384 ? 384 : 96);

					if (isset($_GET['debug1'])) {
						echo 'pos: ' . $pos . ' , $posabc1:' . $posabc1 . ', $addingbycol: ' . $addingbycol . ', $addingbyrow: ' . $addingbyrow . ', $posabc:' . $posabc . '=> $rpos:' . $rpos;
					}
				} else {
					$rpos = ($rpos - 1) * $config_plate['nextwellbycol'] + 1 + (((floor((($rpos - 1) * $config_plate['nextwellbycol']) / $columnnum)) * floor($config_plate['nextwellbyrow'] / 2)) * $columnnum);

					if (isset($_GET['debug1'])) {
						echo 'pos.' . $pos . ': ' . $pos . ': ' . $rpos;
					}
				}

				$plate_array['is_wide'] = isset($plate_array['is_wide']) ? $plate_array['is_wide'] : false;
				if ($rack_ABC && !$plate_array['is_wide'] && $is_96) {
					$rpos = (($rpos - 1) % 12) * 8 + floor(($rpos - 1) / 12) + 1;
				}

				if (isset($_GET['debug1'])) {
					echo ' =>finalpos ' . $rpos . '<br>';
				}

				$pos = $rpos;
			}
		} else if ($rack_uti_type == 14 && $plate_array['re_is_uti_panel'] == 1) {
			$pos = (($batch->position - 1) * 24) + 1;
		} else if ($plate_array['re_is_uti_panel'] == 17 && $is_384) {
			//not now
		} else if ($plate_array['re_is_uti_panel'] == 16) {

			if ($rack_ABC) {
				$pos = ($pos - 1) * 8 + 1;
			}
		} else if ($plate_array['re_is_uti_panel'] == 17) {
			//rpp new
			if ($plate_array['pl_blank1_text'] == 'plateA') {
				if ($pos >= 38) $pos++;
				if ($pos >= 42) $pos++;
				if ($pos >= 46) $pos++;
			}
			$pos = ($pos - 1) * 2 + 1;
		} else if ($plate_array['re_is_uti_panel'] == 18) {
			//uti-wound-88

			if ($plate_array['pl_blank1_text'] == 'plateEX') {
				$pos = $pos  + floor(($pos - 1) / 11);
			} else if ($plate_array['is_wide']) {
				$row = floor(($pos - 1) / 11);
				$pos = $pos + $row * 12 + $row;
			} else {
				$row = floor(($pos - 1) / 11);
				$pos = $pos - $row * 9;

				$addn = (($pos - 1 - ($row * 2)) * 7);
				$pos = $pos + $addn;
			}
		} else if ($plate_array['re_is_uti_panel'] == 14) {
			if ($rack_ABC) {
				if ($pos == 5) {
					$pos = 33;
				} else if ($pos == 9) {
					$pos = 65;
				}
			}
		} else if ($plate_array['re_is_uti_panel'] == 30) { //rpp12
			$plate_controls = array();
			$arr['class'] = 'cell_bg_p' . $pos;
			$pos = $pos + ($pos - 1);
		} else if ($plate_array['re_is_uti_panel'] == 31) { //uti16
			$plate_controls = array();
			$arr['class'] = 'cell_bg_p' . $pos;
			if ($pos <= 8) {
				$pos = $pos + ($pos - 1) * 2;
			} else {
				$pos = $pos + ($pos - 1) * 2 + 168;
			}
		} else if ($plate_array['re_is_uti_panel'] == 33) { //rpp3

			$plate_controls = array();
			if ($is_combine) {
				$arr['class'] = 'cell_bg_p' . (floor(($pos - 1) / 2) + 1) . ' cpos_' . $pos;
			} else {
				$pos = 1 + ($pos - 1) * 32;
			}
		} else if ($plate_array['re_is_uti_panel'] == 34) { //uti4
			$plate_controls = array();
			if ($is_combine) {
				$blockpos = $pos >= 193 ? floor(($pos - 193) / 3) + 9 : floor(($pos - 1) / 3) + 1;
				$arr['class'] = 'cell_bg_p' . $blockpos . ' cpos_' . $pos;
			} else {
				$pos = 1 + ($pos - 1) * 24;
			}
		} else if ($plate_array['re_is_uti_panel'] == 35) { //uti96
			$plate_controls = array();
			if ($is_combine) {
				$pos2 = $pos % 384;
				$blockpos = $pos2 >= 193 ? floor(($pos2 - 193) / 3) + 9 : floor(($pos2 - 1) / 3) + 1;
				$arr['class'] = 'cell_bg_p' . $blockpos . ' cpos_' . $pos2;
			}
		} else if ($plate_array['re_is_uti_panel'] == -300 && $last_pos > 0) { //uti384

			$pos = $last_pos + 24;
			if ($pos >= 361) {
				$pos = $last_pos - 359;
			}
		} else if ($plate_array['re_is_uti_panel'] == 36) {
			$nblock = floor(($pos - 1) / 6);
			$pos = $pos + $nblock * 2;
		} else if ($plate_array['re_is_uti_panel'] == 38) {
			$nblock = floor(($pos - 1) / 12);
			$pos = $pos + $nblock * 12;
		} else if ($plate_array['re_is_uti_panel'] == 40) {
			$nblock = floor(($pos - 1) / 4);
			$pos = $pos * 2 - 1 + $nblock * 40;
		} else if ($plate_array['re_is_uti_panel'] == 41) {

			$pos = ($pos - 1) * 8 + 1;
		} else if ($plate_array['re_is_uti_panel'] == 42) {

			if ($pos == 2) $pos = 33;
			else if ($pos == 3) $pos = 65;
		} else if ($plate_array['re_is_uti_panel'] == 43) {

			$pos = ($pos - 1) * 2 + 1;
		} else if ($plate_array['re_is_uti_panel'] == 44) {

			$pos = ($pos - 1) * 12 + 1;
		} else if ($plate_array['re_is_uti_panel'] == 50) {

			$pos = ($pos % 2 == 0 ? 5 : 1) + (ceil($pos / 2) - 1) * 24;
		} else if (in_array($plate_array['re_is_uti_panel'], array(51))) {
			$pos = ($pos - 1) * 16 + 1;
			$plate_controls = array();
		} else if (in_array($plate_array['re_is_uti_panel'], array(52, 53))) {

			$plate_controls = array();
		}

		///add more cell

		if ($batch->iparent > 0) {
			$parent_pos = $posids[$batch->iparent];
			$assens[$parent_pos]['assen'] .= ',' . $batch->assen;
			$assens[$parent_pos]['childs'][] = $arr;
		} else {
			$assens[$pos] = $arr;
			$posids[$batch->ID] = $pos;
		}

		$last_pos = $pos;


		if (!empty($config_plate) && isset($config_plate['nextwellbycol']) && $config_plate['nextwellbycol'] > 1) {
			for ($i2 = 0; $i2 < $config_plate['nextwellbycol']; $i2++) {

				if (isset($config_plate['blankcols']) && in_array($i2 + 1, $config_plate['blankcols'])) {
					continue;
				}
				$newpos1 = $pos + $i2;
				if ($rack_ABC && $config_plate['rack'] == 384) {
					$posabc = trpdd_pos_aaa2abc($pos, 384);
					$posabc = $posabc + $i2 * 16;
					$newpos1 = trpdd_pos_abc2aaa($posabc, 384);
				} else if ($rack_ABC && !$plate_array['is_wide'] && $config_plate['type'] != 384) {
					$newpos1 = $pos + $i2 * 8;
				}
				$assens[$newpos1] = $arr;

				if ($config_plate['nextwellbyrow'] > 1) {
					for ($i3 = 0; $i3 < $config_plate['nextwellbyrow']; $i3++) {
						$newpos2 = $pos + $i2 + 12;

						if ($rack_ABC && !$plate_array['is_wide']) {
							$newpos2 = $newpos1 + $i3 + 1;
							if ($config_plate['rackabc'] && $config_plate['type'] == 96) {
								$newpos2 = trpdd_pos_abc2aaa($newpos1, 96) + 12 * $i3;
								$newpos2 = trpdd_pos_aaa2abc($newpos2, $config_plate['type']);
							} else if ($rack_ABC && $config_plate['type'] == 384 && $config_plate['rack'] == 384) {
								$newpos2 = $newpos1 + $i3 * 24;
							}
						} else if ($rack_ABC && $config_plate['type'] == 96) {
							$newpos2 = $newpos1 + 12 * $i3;
						}

						$assens[$newpos2] = $arr;
					}
				}
			}
		} else if (!empty($config_plate) && isset($config_plate['nextwellbyrow']) && $config_plate['nextwellbyrow'] > 1) {

			for ($i2 = 0; $i2 < $config_plate['nextwellbyrow']; $i2++) {

				$newpos2 = $pos + $i2 * 12;

				if ($rack_ABC && $config_plate['rack'] == 384) {
					$posabc = trpdd_pos_aaa2abc($pos, 384);
					$posabc = $posabc + $i2;
					$newpos2 = trpdd_pos_abc2aaa($posabc, 384);
				} else if ($rack_ABC && $config_plate['rackabc'] && $config_plate['type'] == 96) {
					$newpos2 = $pos + $i2;
					$config_plate['is_wide'] = isset($config_plate['is_wide']) ? $config_plate['is_wide'] : false;
					if (isset($plate_array['is_wide']) && $plate_array['is_wide'] && !$config_plate['is_wide']) {
						$newpos2 = trpdd_pos_aaa2abc($pos, 96) + $i2;

						$newpos2 = trpdd_pos_abc2aaa($newpos2, 96);
					}
				} else if ($rack_ABC && !$plate_array['is_wide']) {
					$newpos2 = $pos + $i2 + 1;
				}

				$assens[$newpos2] = $arr;
			}
		} else if (!empty($config_plate) && isset($config_plate['wellperpatient']) && $config_plate['wellperpatient'] > 0 && !$is_96) {
			for ($i2 = 0; $i2 < $config_plate['wellperpatient']; $i2++) {
				if (isset($config_plate['nextwellbyrow_odd_even']) && $config_plate['nextwellbyrow_odd_even']) {

					$newposabc = $posabc + $i2 * 2;
					$newpos = trpdd_pos_abc2aaa($newposabc, $is_384 ? 384 : 96);

					$assens[$newpos] = $arr;
				} else {
					if (isset($config_plate['blankcols']) && in_array($i2 + 1, $config_plate['blankcols'])) {
						continue;
					}
					$assens[$pos + $i2] = $arr;
				}
			}
		} else if ($plate_array['re_is_uti_panel'] == 10) {
			//style 10
			for ($i2 = 1; $i2 < 21; $i2++) {
				$assens[$pos + $i2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 11) {
			//style 11
			for ($i2 = 1; $i2 < 11; $i2++) {
				$assens[$pos + $i2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 12) {
			//style 12
			for ($i2 = 1; $i2 < 3; $i2++) {
				$assens[$pos + $i2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 13) {
			//style 13
			for ($i2 = 1; $i2 < 20; $i2++) {
				$assens[$pos + $i2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 14) {
			//style 14
			$pos2 = $pos;

			$arr_pos = array(
				'assen' => 'Positive Control',
				'class' => 'positive'
			);
			$arr_neg = array(
				'assen' => 'Negative Control',
				'class' => 'negative'
			);

			for ($i2 = 1; $i2 <= 31; $i2++) {
				$pos2++;

				$arr_add = $arr;

				if ($rack_ABC) {
					if ($i2 == 29) {
						continue; //blank
					} else if ($i2 == 30) {
						$arr_add = $arr_pos;
					} else if ($i2 == 31) {
						$arr_add = $arr_neg;
					}

					$assens[$pos2] = $arr_add;
				} else {
					if ($pos2 % 4 == 1) {
						$pos2 += 8;
					}

					if ($i2 == 23) {
						continue; //blank
					} else if ($i2 == 27) {
						$arr_add = $arr_pos;
					} else if ($i2 == 31) {
						$arr_add = $arr_neg;
					}

					$assens[$pos2] = $arr_add;
				}
			}
		} else if ($plate_array['re_is_uti_panel'] == 15) {
			//style 15
			for ($i2 = 1; $i2 < 6; $i2++) {
				$assens[$pos + $i2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 16) {
			$assens[$pos + ($rack_ABC ? 8 : 1)] = $arr;
			$assens[$pos + ($rack_ABC ? 9 : 13)] = $arr;
			for ($i2 = 1; $i2 <= 7; $i2++) {
				$assens[$pos + ($rack_ABC ? $i2 : ($i2 * 12))] = $arr;
			}

			$assens[$pos + ($rack_ABC ? 14 : 73)]['assen'] = 'Positive Control';
			$assens[$pos + ($rack_ABC ? 14 : 73)]['class'] = 'positive';

			$assens[$pos + ($rack_ABC ? 15 : 85)]['assen'] = 'Negative Control';
			$assens[$pos + ($rack_ABC ? 15 : 85)]['class'] = 'negative';
		} else if ($plate_array['re_is_uti_panel'] == 17 && $is_384) {
			$assens[$pos + 24] = $arr;
		} else if ($plate_array['re_is_uti_panel'] == 17) {
			$assens[$pos + 1] = $arr;
		} else if ($plate_array['re_is_uti_panel'] == 18 && $is_384) {
			$assens[$pos + 24] = $arr;
		} else if ($plate_array['re_is_uti_panel'] == 18) {
			if ($plate_array['pl_blank1_text'] == 'plateEX') {
				if ($pos % 12 == 11) {
					$ntc_pos = ($pos + 1) % 24 == 12 ? 'NTC' : 'POS';
					$assens[$pos + 1] = array(
						'assen' => $ntc_pos,
						'class' => $ntc_pos ? 'positive' : 'negative'
					);
				}
			} else if ($plate_array['is_wide']) {
				$assens[$pos + 12] = $arr;
			} else {
				$assens[$pos + 1] = $arr;
			}
		} else if ($rack_uti_type == 14 && $plate_array['re_is_uti_panel'] == 1) {
			for ($i2 = 1; $i2 < 24; $i2++) {
				if ($i2 == 23 && !empty($arr['assen'])) {
					$arr['assen'] .= ' RPP';
				}
				$assens[$pos + $i2] = $arr;
			}
		} else if ($rack_max6_fun && $plate_array['re_is_uti_panel'] == -3) {
			//RPP
			for ($i2 = 1; $i2 < 12; $i2++) {
				$assens[$pos + $i2] = $arr;
			}
			$assens[$pos + 14]['assen'] = 'PC';
			$assens[$pos + 14]['class'] = 'positive';

			$assens[$pos + 15]['assen'] = 'NC';
			$assens[$pos + 15]['class'] = 'negative';
		} else if ($plate_array['re_is_uti_panel'] == -5) {
			//GI
			for ($i2 = 1; $i2 <= 10; $i2++) {
				$assens[$pos + ($i2 * 2)] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == -2) {
			//RPP
			for ($i2 = 1; $i2 < 6; $i2++) {
				$assens[$pos + ($i2 * 8)] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == -1) {
			//Respiratory Light
			for ($i2 = 1; $i2 < 8; $i2++) {
				$assens[$pos + $i2] = $arr;
			}
			$assens[$pos + 4]['assen'] = '';
			$assens[$pos + 5]['assen'] = 'RT Control';
			$assens[$pos + 5]['class'] = 'rtcontrol';
			$assens[$pos + 6]['assen'] = 'Positive Control';
			$assens[$pos + 6]['class'] = 'positive';
			$assens[$pos + 7]['assen'] = 'Negative Control';
			$assens[$pos + 7]['class'] = 'negative';
		} else if ($rack_uti_type == 3 && $plate_array['re_is_uti_panel'] == 1) {
			for ($i2 = 1; $i2 < 32; $i2++) {
				if ($i2 == 22) {
					$assens[$pos + $i2] = array(
						'assen' => 'Positive Control',
						'class' => 'positive',
					);
				} else if ($i2 == 23) {
					$assens[$pos + $i2] = array(
						'assen' => 'Negative Control',
						'class' => 'negative',
					);
				} else {
					$assens[$pos + $i2] = $arr;
				}
			}
		} else if ($rack_uti_type == 30 && $plate_array['re_is_uti_panel'] == 1) {
			for ($i2 = 1; $i2 <= 2; $i2++) {
				$assens[$pos + ($i2 * 4)] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 21) {
			$subkey = $plate_array['re_rack_subtype'];
			if (empty($subkey)) {
				$subkey = preg_replace("/[^VPTM]/", '', $plate_array['re_subtitle']);
			}

			$max_well = 3;
			$max_num = 23;
			if (in_array($subkey, array('M', 'V', 'P'))) {
				$max_well = 6;
				$max_num = 11;
			}

			$num_add_to_s = 0;
			if ($max_well == 3) {
				$num_add_to_s = ($arr['count_group'] - 1) * 6 + 3;
			} else {
				$num_add_to_s = ($arr['count_group'] - 1) * 3;
			}

			if (isset($_GET['debug'])) {
				var_dump($arr['count_group']);
			}

			for ($i2 = 0; $i2 <= $max_num; $i2++) {

				$pos_now 	= $pos + $i2;

				if (in_array($subkey, array('VT', 'PT'))) {
					if ($i2 >= 12) {
						$pos_now += 24;
					}
				}
				//cal S
				$c = ($i2) % 12 + 1;

				if ($c == 1) {
					if ($arr['combine_position'] != $last_com_pos) {
						$group_row = 0;
					}
					$last_com_pos = $arr['combine_position'];
					$group_row++;

					if (in_array($subkey, array('VT', 'PT')) && $i2 >= 12) {
						$group_row--;
					}
				}

				$group_rowa12 = $group_row;
				if (in_array($subkey, array('VT', 'PT')) && $i2 >= 12) {
					$group_rowa12 += 3;
				}

				$s_num = $c + $num_add_to_s * floor(($c - 1) / 3) + ($group_rowa12 - 1) * 3;
				$arr['s_num'] = $s_num;
				//end cal S
				//Cal Chanel
				$r = ceil($pos_now / 12);
				$chanel = ($r + ($c - 1) * 2);
				$arr['chanel'] = $chanel;
				//end call chanel
				$arr['num_of_chanel'] = $pos_now % 3;
				if ($arr['num_of_chanel'] == 0) {
					$arr['num_of_chanel'] = 3;
				}
				$assens[$pos_now] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 30 || ($is_combine && $plate_array['re_is_uti_panel'] == 33)) //rpp12
		{
			for ($i2 = 1; $i2 <= 16; $i2++) {
				$pos1 = $pos + ($i2 - 1) * 24;
				$pos2 = $pos1 + 1;
				$assens[$pos1] = $arr;
				$assens[$pos2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 31 || ($is_combine && in_array($plate_array['re_is_uti_panel'], array(34, 35)))) //uti16
		{
			for ($i2 = 1; $i2 <= 8; $i2++) {
				$pos1 = $pos + ($i2 - 1) * 24;
				$assens[$pos1] = $arr;
				$assens[$pos1 + 1] = $arr;
				$assens[$pos1 + 2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 33) //rpp3
		{
			for ($i2 = 1; $i2 < 32; $i2++) {
				$pos1 = $pos + $i2;
				$assens[$pos1] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 34) //uti4
		{
			for ($i2 = 1; $i2 < 24; $i2++) {
				$pos1 = $pos + $i2;
				$assens[$pos1] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 37) //uti4
		{
			for ($i2 = 1; $i2 < 12; $i2++) {
				$pos1 = $pos + $i2 * 8;
				$assens[$pos1] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 38) {
			$assens[$pos + 12] = $arr;
		} else if ($plate_array['re_is_uti_panel'] == 40) {
			for ($i2 = 0; $i2 <= 6; $i2++) {
				$pos1 = $pos + ($i2 * 8);
				$assens[$pos1] = $arr;
				$assens[$pos1 + 1] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 41) {
			for ($i2 = 0; $i2 <= 7; $i2++) {

				$assens[$pos + $i2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 42) {
			//style 14
			$pos2 = $pos;

			$arr_endo = array(
				'assen' => 'Endogenous Control',
				'class' => 'endogenous'
			);
			$arr_pos = array(
				'assen' => 'Positive Control',
				'class' => 'positive'
			);
			$arr_neg = array(
				'assen' => 'Negative Control',
				'class' => 'negative'
			);

			for ($i2 = 1; $i2 <= 32; $i2++) {
				$pos2++;

				$arr_add = $arr;

				if ($rack_ABC) {
					if ($i2 == 21) {
						$arr_add = $arr_endo;
					} else if ($i2 == 22) {
						$arr_add = $arr_pos;
					} else if ($i2 == 23) {
						$arr_add = $arr_neg;
					}

					$assens[$pos2] = $arr_add;
				} else {
					if ($pos2 % 4 == 1) {
						$pos2 += 8;
					}

					if ($i2 == 23) {
						$arr_add = $arr_endo;
					} else if ($i2 == 27) {
						$arr_add = $arr_pos;
					} else if ($i2 == 31) {
						$arr_add = $arr_neg;
					}

					$assens[$pos2] = $arr_add;
				}
			}
		} else if ($plate_array['re_is_uti_panel'] == 43) {
			$assens[$pos + 1] = $arr;
		} else if ($plate_array['re_is_uti_panel'] == 44) {
			for ($i2 = 1; $i2 < 12; $i2++) {
				$assens[$pos + $i2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == -300) //uti4
		{
			$addnum = 16;
			if ($batch->subtype == 'sti') {
				$addnum = 6;
			} else if ($batch->subtype == 'rpp') {
				$addnum = 10;
			} else if ($batch->subtype == 'ctrl') {
				$addnum = 2;
			}
			$pos1 = $pos;
			for ($i2 = 1; $i2 < $addnum; $i2++) {
				if ($pos1 + 24 > 384) {
					$pos1 = $pos1 - 359;
				} else {
					$pos1 = $pos1 + 24;
				}


				if ($batch->subtype == 'ctrl') {
					$arr['assen'] = 'PC';
					$arr['class'] = 'positive';
					$assens[$pos] = $arr;
					$arr['assen'] =  'NC';
					$arr['class'] = 'negative';
				}
				$assens[$pos1] = $arr;
				$last_pos = $pos1;
			}
			if (isset($_GET['debug'])) {
				var_dump($pos);
				var_dump($last_pos);
				var_dump($arr);
				echo '<br><br>';
			}
		} else if ($plate_array['re_is_uti_panel'] == 45) {
			for ($i2 = 0; $i2 < 12; $i2++) {

				$assens[$pos + $i2 * 8] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 46) {
			for ($i2 = 0; $i2 < 3; $i2++) {

				$assens[$pos + $i2 * 8] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 47) {
			for ($i2 = 0; $i2 < 2; $i2++) {

				$assens[$pos + $i2 * 8] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 48) {
			for ($i2 = 0; $i2 < 6; $i2++) {
				$assens[$pos + $i2 * 8] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 50) {
			$assens[$pos + 1] = $arr;
		} else if (in_array($plate_array['re_is_uti_panel'], array(51))) {
			for ($i2 = 0; $i2 < 8; $i2++) {
				$pos2 = $rack_ABC ? $pos + 8 + $i2 : $pos + $i2;

				$assens[$pos + $i2] = $arr;
				$assens[$pos2] = $arr;
			}
			$plate_array['pl_ntc_position'] = count($batches) * 16 + 1;
			$plate_array['pl_ntc2_position'] = count($batches) * 16 + 16 + 1;
		} else if (in_array($plate_array['re_is_uti_panel'], array(51, 52, 53))) {
			for ($i2 = 0; $i2 < 6; $i2++) {
				$pos2 = $rack_ABC ? $pos + ($i2 * 8) : $pos + $i2;
				$assens[$pos2] = $arr;
			}
			$plate_array['pl_ntc_position'] = count($batches) + 1;
			$plate_array['pl_ntc2_position'] = count($batches) + 2;
		}
	}

	if ($is_combine) {
		if ($plate_array['pl_ntc_combine_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_ntc_text']) ? $plate_array['pl_ntc_text'] : 'Covid-19 Negative IC',
			);
			if (isset($plate_array['num']) && $plate_array['num'] > 0) {
				$arr['assen'] .= '-' . $plate_array['num'];
			}
			$assens[$plate_array['pl_ntc_combine_position']] = $arr;

			if ($plate_array['re_is_uti_panel'] == 35) {
				$pos2 = $plate_array['pl_ntc_combine_position'] % 384;
				$blockpos = $pos2 >= 193 ? floor(($pos2 - 193) / 3) + 9 : floor(($pos2 - 1) / 3) + 1;
				$arr['class'] = 'cell_bg_p' . $blockpos . ' cpos_' . $pos2;

				for ($i2 = 1; $i2 <= 8; $i2++) {
					$pos1 = $plate_array['pl_ntc_combine_position'] + ($i2 - 1) * 24;
					$assens[$pos1] = $arr;
					$assens[$pos1 + 1] = $arr;
					$assens[$pos1 + 2] = $arr;
				}
			}
		}

		if ($plate_array['pl_ntc2_combine_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_ntc2_text']) ? $plate_array['pl_ntc2_text'] : 'Covid-19 Positive IC',
			);
			if (isset($plate_array['num']) && $plate_array['num'] > 0) {
				$arr['assen'] .= '-' . $plate_array['num'];
			}
			$assens[$plate_array['pl_ntc2_combine_position']] = $arr;
		}
		if ($plate_array['pl_ntc3_combine_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_ntc3_text']) ? $plate_array['pl_ntc3_text'] : 'Covid-19 Negative IC',
			);
			$assens[$plate_array['pl_ntc3_combine_position']] = $arr;
		}
		if ($plate_array['pl_ntc4_combine_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_ntc4_text']) ? $plate_array['pl_ntc4_text'] : 'Covid-19 Positive IC',
			);
			$assens[$plate_array['pl_ntc4_combine_position']] = $arr;
		}

		if ($plate_array['pl_blank1_combine_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_blank1_text']) ? $plate_array['pl_blank1_text'] : 'Blank 1',
			);
			$assens[$plate_array['pl_blank1_combine_position']] = $arr;
		}
		if ($plate_array['pl_blank2_combine_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_blank2_text']) ? $plate_array['pl_blank2_text'] : 'Blank 2',
			);
			$assens[$plate_array['pl_blank2_combine_position']] = $arr;
		}
	} else {

		//ntc pos nec

		$adding_ntc_count 		= 0;
		$adding_ntc_count_row 	= 0;

		$map_ntcpos = array(
			'pl_ntc_position' => array('ntc', 'negative', 1),
			'pl_ntc2_position' => array('pos', 'positive', 2),
			'pl_ntc3_position' => array('nec', '', 3),
			'pl_blank_position' => array('blank', '', 4),
		);

		if (!empty($config_plate)) {


			$plate_array['pl_ntc_text'] 	= isset($config_plate['ntc_text']) ? $config_plate['ntc_text'] : 'NTC';
			$plate_array['pl_ntc2_text'] 	= isset($config_plate['pos_text']) ? $config_plate['pos_text'] : 'POS';
			$plate_array['pl_ntc3_text'] 	= isset($config_plate['nec_text']) ? $config_plate['nec_text'] : 'NEC';
			$plate_array['pl_blank_text'] 	= isset($config_plate['blank_text']) ? $config_plate['blank_text'] : '';


			foreach ($map_ntcpos as $mk => $cfkarr) {

				$cfk = $cfkarr[0];
				if ($config_plate[$cfk] < 0) {
					$config_plate[$cfk]  = $last_batch_pos - $config_plate[$cfk];
				}
				if ($config_plate[$cfk] > 0) {
					$plate_array[$mk] = $config_plate[$cfk];

					if ($rack_ABC && (!isset($config_plate['rackabc']) || (isset($config_plate['rackabc']) && $config_plate['rackabc']))) {
						$nexbyrow	 = $config_plate['nextwellbyrow'] + (isset($config_plate['nextblankbyrow']) ? $config_plate['nextblankbyrow'] : 0);
						if ($nexbyrow == 0) $nexbyrow = 1;
						$nexbycol	 = $config_plate['nextwellbycol'] + (isset($config_plate['nextblankbycol']) ? $config_plate['nextblankbycol'] : 0);
						$addingbycol = floor(($plate_array[$mk] - 1) / ($rownum / $nexbyrow)) * $rownum * ($nexbycol - 1);
						$addingbyrow = ($plate_array[$mk] - 1) * $nexbyrow + 1;

						$plate_array[$mk] = trpdd_pos_abc2aaa($addingbycol + $addingbyrow, $is_384 ? 384 : 96);
					} else {
						$plate_array[$mk] = ($plate_array[$mk] - 1) * $config_plate['nextwellbycol'] + 1 + (((floor((($plate_array[$mk] - 1) * $config_plate['nextwellbycol']) / $columnnum)) * floor($config_plate['nextwellbyrow'] / 2)) * $columnnum);
					}

					if ($rack_ABC && !$plate_array['is_wide'] && $is_96) {
						$plate_array[$mk] = (($plate_array[$mk] - 1) % 12) * 8 + floor(($plate_array[$mk] - 1) / 12) + 1;
					}

					$adding_ntc_count 		= isset($config_plate['controlcols']) && $config_plate['controlcols'] > 0 ? $config_plate['controlcols'] : $config_plate['nextwellbycol'];
					$adding_ntc_count_row 	= $config_plate['nextwellbyrow'];

					if (isset($_GET['debug1'])) {
						echo $cfk . '=>' . $config_plate[$cfk] . '=>' . $plate_array[$mk] . '<br>';
					}
				} else if (isset($config_plate[$cfk . 'fix']) && !empty($config_plate[$cfk . 'fix'])) {
					if (!is_array($config_plate[$cfk . 'fix'])) {
						$config_plate[$cfk . 'fix'] = array($config_plate[$cfk . 'fix']);
					}
					foreach ($config_plate[$cfk . 'fix'] as $fpos) {

						if ($rack_ABC && !$plate_array['is_wide'] && $config_plate['type'] != 384) {
							$fpos = trpdd_pos_aaa2abc($fpos, $config_plate['type']);
						}

						$arr = array(
							'ntc' => $cfkarr[2],
							'assen' => $plate_array['pl_ntc' . ($cfkarr[2] > 1 ?  $cfkarr[2] : '') . '_text'],
							'class' => $cfkarr[1]
						);


						$assens[$fpos] = $arr;
					}
				}
			}

			if (!empty($config_plate['control_blocks']) && !empty($config_plate['wellperpatient'])) {
				$control_map = array(
					'pos' => array(2, 'positive'),
					'ntc' => array(1, 'negative'),
					'nec' => array(3, ''),
					'blank' => array(4, ''),
				);

				foreach ($config_plate['control_blocks'] as $control_block) {
					$control_type = isset($control_block['type']) ? $control_block['type'] : '';
					if (!isset($control_map[$control_type])) {
						continue;
					}

					$text_key = $control_type . '_text';
					$control_text = isset($control_block['text']) ? $control_block['text'] : (isset($config_plate[$text_key]) ? $config_plate[$text_key] : '');
					$class = isset($control_block['class']) ? $control_block['class'] : $control_map[$control_type][1];
					$ntc_id = $control_map[$control_type][0];

					$columns = isset($control_block['columns']) ? $control_block['columns'] : array();
					if (!is_array($columns)) {
						$columns = array($columns);
					}

					foreach ($columns as $control_col) {
						for ($target_i = 0; $target_i < $config_plate['wellperpatient']; $target_i++) {
							$control_pos = (($control_block['row'] + $target_i - 1) * 24) + $control_col;
							$assens[$control_pos] = array(
								'ntc' => $ntc_id,
								'assen' => $control_text,
								'class' => $class
							);
						}
					}
				}
			}
		}

		if ($plate_array['pl_ntc_position'] > 0) {
			$arr = array(
				'ntc' 	=> 1,
				'assen' => !empty($plate_array['pl_ntc_text']) ? ($plate_array['pl_ntc_text']) : $plate_controls['neg'],
				'class' => 'negative'
			);
			if (empty($arr['assen']) || $arr['assen'] == ' ') {
				$arr['class'] = '';
				unset($arr['ntc']);
			}
			$assens[$plate_array['pl_ntc_position']] = $arr;
		}

		if ($plate_array['pl_ntc2_position'] > 0) {
			$arr = array(
				'ntc' 	=> 2,
				'assen' => !empty($plate_array['pl_ntc2_text']) ? trim($plate_array['pl_ntc2_text']) : $plate_controls['pos'],
				'class' => 'positive'
			);
			if (empty($arr['assen']) || $arr['assen'] == ' ') {
				$arr['class'] = '';
				unset($arr['ntc']);
			}
			$assens[$plate_array['pl_ntc2_position']] = $arr;
		}

		if ($plate_array['pl_ntc3_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_ntc3_text']) ? trim($plate_array['pl_ntc3_text']) : $plate_controls['neg'],
				'class' => 'negative'
			);
			if (empty($arr['assen']) || $arr['assen'] == ' ') {
				$arr['class'] = '';
			}
			$assens[$plate_array['pl_ntc3_position']] = $arr;
		}

		if ($plate_array['pl_ntc4_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_ntc4_text']) ? trim($plate_array['pl_ntc4_text']) : $plate_controls['pos'],
				'class' => 'negative'
			);
			if (empty($arr['assen']) || $arr['assen'] == ' ') {
				$arr['class'] = '';
			}
			$assens[$plate_array['pl_ntc4_position']] = $arr;
		}


		//adding ntc
		if ($plate_array['re_is_uti_panel'] == -2) {
			//RPP
			for ($i2 = 1; $i2 < 6; $i2++) {
				if ($plate_array['pl_ntc_position'] > 0) {
					$assens[$plate_array['pl_ntc_position'] + ($i2 * 8)] = $assens[$plate_array['pl_ntc_position']];
				}
				if ($plate_array['pl_ntc2_position'] > 0) {
					$assens[$plate_array['pl_ntc2_position'] + ($i2 * 8)] = $assens[$plate_array['pl_ntc2_position']];
				}
			}
		} else if ($plate_array['re_is_uti_panel'] == 10) {
			//style 10
			$adding_ntc_count = 21;
		} else if ($plate_array['re_is_uti_panel'] == 11) {
			//style 11
			$adding_ntc_count = 11;
		} else if ($plate_array['re_is_uti_panel'] == 12) {
			//style 12
			$adding_ntc_count = 3;
		} else if ($plate_array['re_is_uti_panel'] == 13) {
			//style 13
			$adding_ntc_count = 20;
		} else if ($plate_array['re_is_uti_panel'] == 17 && $plate_array['pl_blank1_text'] == 'plateA') {
			//style rpp new
			$assens[$plate_array['pl_ntc_position'] + 1] = $assens[$plate_array['pl_ntc_position']];
			$assens[$plate_array['pl_ntc2_position'] + 1] = $assens[$plate_array['pl_ntc2_position']];
			$assens[$plate_array['pl_ntc3_position'] + 1] = $assens[$plate_array['pl_ntc3_position']];
		} else if ($plate_array['re_is_uti_panel'] == 17 && $is_384) {
			$assens[$plate_array['pl_ntc_position'] + 24] = $assens[$plate_array['pl_ntc_position']];
			$assens[$plate_array['pl_ntc2_position'] + 24] = $assens[$plate_array['pl_ntc2_position']];
			$assens[$plate_array['pl_ntc3_position'] + 24] = $assens[$plate_array['pl_ntc3_position']];
		} else if ($plate_array['re_is_uti_panel'] == 18) {
			if ($plate_array['pl_blank1_text'] != 'plateEX') {
				//plate A,B,C,D
				for ($intc = 0; $intc < 8; $intc++) {
					$assntc = $intc % 4 < 2 ? 'NTC' : 'POS';
					$posntcp = 89 + $intc;
					if ($plate_array['is_wide']) {
						$posntcp = 12 + $intc * 12;
					}
					$assens[$posntcp] = array(
						'assen' => $assntc,
						'class' => $assntc == 'POS' ? 'positive' : 'negative'
					);
				}
			}
		} else if ($plate_array['re_is_uti_panel'] == 36) {

			$nblock = ceil($pos / 8);

			for ($i = 1; $i <= $nblock; $i++) {
				$nec_pos = $i * 8 - 1;
				if ($nec_pos > $pos + 1) {
					$nec_pos = $pos + 1;
				}
				$assens[$nec_pos] = array(
					'assen' => 'NEC',
					'class' =>  'negative'
				);
				$assens[$nec_pos + 1] = array(
					'assen' => 'POS',
					'class' => 'positive'
				);
			}
		} else if ($plate_array['re_is_uti_panel'] == 37) {
			$adding_ntc_count_row = 12;
		} else if ($plate_array['re_is_uti_panel'] == 38) {
			$assens[$plate_array['pl_ntc_position'] + 12] = $assens[$plate_array['pl_ntc_position']];
			$assens[$plate_array['pl_ntc2_position'] + 12] = $assens[$plate_array['pl_ntc2_position']];
		} else if ($plate_array['re_is_uti_panel'] == 40) {
			$pos = 55;

			for ($i2 = 0; $i2 <= 6; $i2++) {
				$pos1 = $pos + ($i2 * 8);
				$arr = array(
					'assen' => 'POS1',
					'class' => 'positive'
				);
				$assens[$pos1] = $arr;
				$assens[$pos1 + 1] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 41) {

			$arr = array(
				'assen' => 'PC',
				'class' => 'positive'
			);
			for ($i2 = 0; $i2 <= 7; $i2++) {
				$assens[89 + $i2] = $arr;
			}
		} else if ($plate_array['re_is_uti_panel'] == 43) {

			$adding_ntc_count = 2;
		} else if ($plate_array['re_is_uti_panel'] == 44) {

			$adding_ntc_count = 12;
		} else if ($plate_array['re_is_uti_panel'] == 45) {

			$adding_ntc_count_row = 12;
		} else if ($plate_array['re_is_uti_panel'] == 46) {

			$adding_ntc_count_row = 3;
		} else if ($plate_array['re_is_uti_panel'] == 47) {

			$adding_ntc_count_row = 2;
		} else if ($plate_array['re_is_uti_panel'] == 48) {

			$adding_ntc_count_row = 6;
		} else if ($plate_array['re_is_uti_panel'] == 50) {

			$adding_ntc_count = 2;
		} else if (in_array($plate_array['re_is_uti_panel'], array(52, 53))) {

			$adding_ntc_count_row = 6;
		} else if (in_array($plate_array['re_is_uti_panel'], array(51))) {

			for ($i2 = 0; $i2 < 8; $i2++) {
				$pos2 = $rack_ABC ? $plate_array['pl_ntc_position'] + 8 + $i2 : $plate_array['pl_ntc_position'] + $i2;
				$assens[$plate_array['pl_ntc_position'] + $i2] = $assens[$plate_array['pl_ntc_position']];
				$assens[$pos2] = $assens[$plate_array['pl_ntc_position']];

				$pos2 = $rack_ABC ? $plate_array['pl_ntc2_position'] + 8 + $i2 : $plate_array['pl_ntc2_position'] + $i2;
				$assens[$plate_array['pl_ntc2_position'] + $i2] = $assens[$plate_array['pl_ntc2_position']];
				$assens[$pos2] = $assens[$plate_array['pl_ntc2_position']];
			}
		}

		if ($adding_ntc_count_row > 0 && $adding_ntc_count == 0) {
			$adding_ntc_count = 1;
		}
		if ($adding_ntc_count > 0) {
			for ($i2 = 0; $i2 < $adding_ntc_count; $i2++) {
				$addpos1 = $i2;

				if (isset($config_plate['blankcols']) && in_array($i2 + 1, $config_plate['blankcols'])) {
					continue;
				}

				if ($rack_ABC && !$plate_array['is_wide'] && $config_plate['type'] == 96) {
					$addpos1 = $i2 * 8;
				}
				foreach ($map_ntcpos as $mk => $cfk) {
					if (isset($plate_array[$mk]) && $plate_array[$mk] > 0) {
						$assens[$plate_array[$mk] + $addpos1] = $assens[$plate_array[$mk]];
					}
				}

				if ($adding_ntc_count_row > 0) {
					for ($i3 = 1; $i3 < $adding_ntc_count_row; $i3++) {
						$addpos2 = $addpos1 + $i3 * ($config_plate['type'] == 384 ? 24 : 8);
						if ($rack_ABC && !$plate_array['is_wide'] && $config_plate['type'] == 96) {
							$addpos2 = $addpos1  + $i3;
						} else if ($rack_ABC && $plate_array['is_wide'] && $config_plate['type'] == 96) {
							$addpos2 = $addpos1 + $i3 * 12;
						}

						foreach ($map_ntcpos as $mk => $cfk) {
							if ($plate_array[$mk] > 0) {
								$assens[$plate_array[$mk] + $addpos2] = $assens[$plate_array[$mk]];
							}
						}
					}
				}
			}
		}

		if ($plate_array['pl_blank1_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_blank1_text']) ? $plate_array['pl_blank1_text'] : 'Blank 1',
			);
			$assens[$plate_array['pl_blank1_position']] = $arr;
		}

		if ($plate_array['pl_blank2_position'] > 0) {
			$arr = array(
				'assen' => !empty($plate_array['pl_blank2_text']) ? $plate_array['pl_blank2_text'] : 'Blank 2',
			);
			$assens[$plate_array['pl_blank2_position']] = $arr;
		}
	}

	if (isset($plate_array['is_add384'])) {
		//convert to 384
		return trpdd_96wide_2_384($assens, $config_plate['add384map']);
	}


	return $assens;
}

function trpdd_pos_aaa2abc($num, $type = 96)
{
	$numcol = $type == 96 ? 12 : 24;
	$numrow = $type == 96 ? 8 : 16;
	$row = ceil($num / $numcol);
	$col = (($num - 1) % $numcol) + 1;

	$num = $row + ($col - 1) * $numrow;
	return $num;
}

function trpdd_pos_abc2aaa($num, $type = 96)
{
	$numcol = $type == 96 ? 12 : 24;
	$numrow = $type == 96 ? 8 : 16;
	$row = ($num - 1) % $numrow;
	$col = ceil($num / $numrow);
	$num = $col + ($row) * $numcol;
	return $num;
}

function trpdd_96wide_2_384($assens, $config_plate)
{
	$config_plate['blank_cols'] = intval($config_plate['blank_cols']) + 1;

	$newassens = array();
	foreach ($assens as $pos => $arr) {
		$posabc = trpdd_pos_aaa2abc($pos, 96);

		if (isset($config_plate['odd_even']) && $config_plate['odd_even']) {

			$posabc = ($posabc - 1) % 8 + ($posabc - 1) % 8 + 1 + floor(($posabc - 1) % 16 / 8) + floor(($posabc - 1) / 16) * 16 * $config_plate['blank_cols'];
		}

		if ($config_plate['precol']) {
			$posabc += 16 * $config_plate['precol'];
		}

		$pos 		= trpdd_pos_abc2aaa($posabc, 384);
		$newassens[$pos] = $arr;
		$addingcol 	= is_array($config_plate['nextwellbycol']) ? $config_plate['nextwellbycol'] : array();
		if (!is_array($config_plate['nextwellbycol'])) {
			for ($i1 = 2; $i1 <= $config_plate['nextwellbycol']; $i1++) {
				$addingcol[] = $i1;
			}
		}

		if (count($addingcol) > 0) {
			foreach ($addingcol as $icol) {
				$posabcadd = $posabc + (16 * ($icol - 1));
				$pos = trpdd_pos_abc2aaa($posabcadd, 384);
				$newassens[$pos] = $arr;
			}
		}
	}

	return $newassens;
}
function trpdd_s_get_384_position($combinesort, $start_plate_pos, $position)
{
	switch ($combinesort) {
		case 33:
			$current_pos = $start_plate_pos + ($position - 1) * 2 + 1;
			break;
		case 34:
			$current_pos = $start_plate_pos + ($position - 1) * 3 + 1;
			break;
		case 35:

			$current_pos = $start_plate_pos + floor(($position - 1) / 8) * 168 + ($position - 1) * 3 + 1;
			break;
		case 3:
			$current_pos = $start_plate_pos + $position;
			break;
		case 2:
			$row = ($position - 1) % 8;
			$col = floor(($position - 1) / 8) * 2 + 1;
			$current_pos = $start_plate_pos + ($row * 24) + $col;

			break;
		default:
			$num = ($position - 1) % 12 + 1;
			$current_pos = $start_plate_pos + $num + (12 * 4 * floor(($position - 1) / 12));

			break;
	}
	return $current_pos;
}


function tr_rack_well_by_position($position, $re_is_uti_panel)
{
	global $rack_ABC, $rack_uti_type, $rack_16;

	$rownum = 8;
	$columnnum = 12;
	if ($re_is_uti_panel == 32) {
		$position += 3;
		$rownum = 16;
		$columnnum = 24;
	}

	$config_plate = trpdd_plate_map_config($re_is_uti_panel);


	if (!empty($config_plate)) {
		$layout_wells = trpdd_plate_patient_block_wells($config_plate, $position);
		if (!empty($layout_wells)) {
			$first_pos = $layout_wells[0];
			$columnnum = isset($config_plate['type']) && intval($config_plate['type']) == 384 ? 24 : 12;
			$num12 = ($first_pos - 1) % $columnnum + 1;
			return chr(floor(($first_pos - 1) / $columnnum) + 65) . $num12;
		}

		if (!empty($config_plate['sample_blocks'])) {
			$layout_wells = array();
			foreach ($config_plate['sample_blocks'] as $sample_block) {
				$start = isset($sample_block['start']) ? intval($sample_block['start']) : 0;
				$end = isset($sample_block['end']) ? intval($sample_block['end']) : 0;
				if ($position < $start || $position > $end) {
					continue;
				}

				$col_offset = isset($sample_block['col_offset']) ? intval($sample_block['col_offset']) : 0;
				$col = $position + $col_offset;
				$row = intval($sample_block['row']);
				for ($target_i = 0; $target_i < intval($config_plate['wellperpatient']); $target_i++) {
					$layout_wells[] = (($row + $target_i - 1) * 24) + $col;
				}
				break;
			}

			if (!empty($layout_wells)) {
				$first_pos = $layout_wells[0];
				$num12 = ($first_pos - 1) % 24 + 1;
				return chr(floor(($first_pos - 1) / 24) + 65) . $num12;
			}
		}

		$posntc_arr = array($config_plate['ntc'], $config_plate['nec'], $config_plate['pos'], $config_plate['blank']);
		sort($posntc_arr);

		if ($config_plate['type'] == 384 && $config_plate['rack'] != 96) {
			$rownum 	= 16;
			$columnnum 	= 24;
		}

		foreach ($posntc_arr as $ppn) {
			if ($ppn > 0) {

				if ($rack_ABC && (!isset($config_plate['rackabc']) || (isset($config_plate['rackabc']) && $config_plate['rackabc']))) {
					$ppn  = (($ppn - 1) % $columnnum) * $rownum + floor(($ppn - 1) / $columnnum) + 1;
					if ($config_plate['nextwellbyrow'] > 1) {
						$ppn = ceil($ppn / $config_plate['nextwellbyrow']);
					}
				}
				if ($position >= $ppn) {
					if (isset($_GET['debug1']) && $position == $ppn) {
						echo '=> adding pos: ' . $position . '<br>';
					}
					$position++;
				}
			}
		}

		if ((isset($config_plate['nextwellbycol']) && $config_plate['nextwellbycol'] > 0)
			|| (isset($config_plate['nextwellbyrow']) && $config_plate['nextwellbyrow'] > 0)
		) {
			$rpos 		= $position;
			$next_group = 0;
			$loopcount 	= 0;
			do {
				if ($rack_ABC && (!isset($config_plate['rackabc']) || (isset($config_plate['rackabc']) && $config_plate['rackabc']))) {
					$rpos = ($rpos - 1) * $columnnum * $config_plate['nextwellbyrow'] + 1 + ($next_group * $config_plate['nextwellbycol']);
				} else {
					$rpos = ($rpos - 1) * $config_plate['nextwellbycol'] + 1 + (((floor((($rpos - 1) * $config_plate['nextwellbycol']) / $columnnum)) * floor($config_plate['nextwellbyrow'] / 2)) * $columnnum);
				}

				if (isset($_GET['debug1'])) {
					echo '=> pos' . $position . ': ' . $rpos;
				}

				$rowpos = floor(($rpos - 1) / $columnnum) + 1;

				if (isset($_GET['debug1'])) {
					echo '=> row: ' . $rowpos;
				}

				if (!$rack_ABC) {
					break;
				}
				if ($rowpos > $rownum) {
					if ($rack_ABC && (!isset($config_plate['rackabc']) || (isset($config_plate['rackabc']) && $config_plate['rackabc']))) {
						$nump1 = $rownum / $config_plate['nextwellbyrow'];
						if ($nump1 < 1) $nump1 = 1;
						$rpos 		= $position % $nump1;
						if ($rpos == 0) {
							$rpos = ($rownum / $config_plate['nextwellbyrow']);
						}
					} else {
						$rpos 		= $position - ($rownum / $config_plate['nextwellbyrow']);
					}



					$next_group = floor($rowpos / $rownum);

					if ($rowpos % $rownum == 0) {
						$next_group--;
					}

					if (isset($_GET['debug1'])) {
						echo '=> nextpos: ' . $rpos;
						echo '=> $next_group: ' . $next_group;
					}
				}
				$loopcount++;
				if ($loopcount > 3) break;
			} while ($rowpos > $rownum);

			$num12 = ($rpos - 1) % $columnnum + 1;

			$welld = chr($rowpos + 64) . $num12;


			if (isset($_GET['debug1'])) {
				echo '=> $rpos: ' . $rpos;
				echo '=> col: ' . $num12;
				echo '=> well: ' . $welld . '<br>';
			}
		} else if ($rack_ABC) {
			$num12 = floor(($position - 1) / $rownum) + 1;
			$welld = chr(floor(($position - 1) % $rownum) + 65) . $num12;
		} else {
			$num12 = ($position - 1) % $columnnum + 1;
			$welld = chr(floor(($position - 1) / $columnnum) + 65) . $num12;
		}
	} else if (in_array($re_is_uti_panel, array(40))) {
		$num12 = floor(($position - 1) / $rownum) + 1;
		$welld = chr(floor(($position - 1) % $rownum) + 65) . $num12;
	} else if (in_array($re_is_uti_panel, array(38))) {
		$num12 = ($position - 1) % $columnnum + 1;
		$welld = chr(floor(($position - 1) / $columnnum) + 65) . $num12;
	} else if (in_array($re_is_uti_panel, array(10, 11, 12, 13))) {
		$welld = chr($position + 64) . '1';
	} else if ($re_is_uti_panel == 18) {
		$position = $position + floor(($position - 1) / 11);
		$num12 = ($position - 1) % 12 + 1;
		$welld = chr(floor(($position - 1) / 12) + 65) . $num12;
	} else if ($re_is_uti_panel == 14) {
		if ($position == 2) $position = 5;
		else if ($position == 3) $position = 9;
		$welld = 'A' . $position;
	} else if ($re_is_uti_panel == 15) {
		if ($position < 9) {
			$position += 8;
			$welld = chr($position + 64) . '1';
		} else {
			$position -= 8;
			$welld = chr($position + 64) . '13';
		}
	} else if ($re_is_uti_panel == 16) {
		$welld = 'A' . (($position - 1) * 2 + 1);
	} else if ($re_is_uti_panel == 30) {
		$welld = 'A' . (($position - 1) * 2 + 1);
	} else if ($re_is_uti_panel == 31) {
		if ($position <= 8) {
			$welld = 'A' . (($position - 1) * 3 + 1);
		} else {
			$welld = 'I' . (($position - 9) * 3 + 1);
		}
	} else if ($re_is_uti_panel == -1) {
		//Respiratory Light
		$welld = 'A' . $position;
	} else if ($re_is_uti_panel == -2) {
		//RPP
		$num12 = floor(($position - 1) / 8) + 1;
		if ($num12 > 1) $num12 = 7;
		$welld = chr(floor(($position - 1) % 8) + 65) . $num12;
	} else if ($re_is_uti_panel == 43) {
		//RPP
		$num12 = ($position - 1) % 24 + 1;
		$welld = chr(floor(($position - 1) / 24) + 65) . $num12;
	} else if ($re_is_uti_panel == 44) {
		//RPP
		$num12 = ($position - 1) % 24 + 1;
		$welld = chr(floor(($position - 1) / 24) + 65) . $num12;
	} else if ($re_is_uti_panel == -3) {
		//Fungal
		$position = (($position - 1) * 16) + 1;
		$num12 = floor(($position - 1) / 8) + 1;
		$welld = chr(floor(($position - 1) % 8) + 65) . $num12;
	} else if ($re_is_uti_panel == -5) {
		//GI
		$position = floor(($position - 1) / 2) * 24 + (($position - 1) % 2) + 1;

		$num12 = ($position - 1) % 24 + 1;
		$welld = chr(floor(($position - 1) / 24) + 65) . $num12;
	} else if ($rack_uti_type == 14 && $re_is_uti_panel == 1) {
		$num12 = floor(($position - 1) / 14) + 1;
		$welld = chr(floor(($position - 1) % 14) + 65) . $num12;
	} else if ($rack_uti_type == 30 && $re_is_uti_panel == 1) {
		$position = floor(($position - 1) / 4) * 12 + (($position - 1) % 4) + 1;

		$num12 = ($position - 1) % 12 + 1;
		$welld = chr(floor(($position - 1) / 12) + 65) . $num12;
	} else if ($rack_16) {
		$num12 = floor(($position - 1) / 8) + 1;
		if ($num12 > 1) $num12 = 7;
		$welld = chr(floor(($position - 1) % 8) + 65) . $num12;
	} else if ($rack_ABC) {
		$num12 = floor(($position - 1) / $rownum) + 1;
		$welld = chr(floor(($position - 1) % $rownum) + 65) . $num12;
	} else {
		$num12 = ($position - 1) % $columnnum + 1;
		$welld = chr(floor(($position - 1) / $columnnum) + 65) . $num12;
	}
	return $welld;
}

function trpdd_get_rack_panel_type($panel_id, $panel_style = 1)
{
	global $wpdb;
	$is_lowzero = false;
	$panel_name = '';

	$panel_id = intval($panel_id);

	if ($panel_id < 0) {
		if ($panel_id == -400) {
			return 35;
		} else if ($panel_id <= -300) {
			return $panel_id;
		}
		$is_lowzero = true;
		$panel_id = $panel_id * -1;
	}

	if ($panel_id > 0) {
		$panel = $wpdb->get_row("select * 
	                                    from ci_test_groups 
                                        where tg_ID = {$panel_id} ");
		if ($panel) {
			$panel_name = $panel->tg_name;
			if ($panel_style == 2 && $panel->tg_plate_style2 > 0) {
				return $panel->tg_plate_style2;
			} else if ($panel->tg_plate_style > 0) {
				return $panel->tg_plate_style;
			}
		}
	}

	$typeid = 0;

	if (stripos($panel_name, 'uti') !== false || stripos($panel_name, 'wound') !== false) {
		$typeid = 1;
		if ($is_lowzero) {
			$typeid = -4; //abx
		}
	} else if (stripos($panel_name, 'Respiratory Light') !== false) {
		$typeid = -1;
	} else if (stripos($panel_name, 'rpp') !== false) {
		$typeid = -2;
	} else if (stripos($panel_name, 'fungal') !== false) {
		$typeid = -3;
	} else if (stripos($panel_name, 'gi') === 0) {
		$typeid = -5; //GI
	}

	return $typeid;
}

function trpdd_rerun_reason_fa($assen)
{
	global $wpdb;
	$report_id = $wpdb->get_var("select id from ci_reports_blood where patient_id = '{$assen}' ");
	if ($report_id > 0) {
		$rs = $wpdb->get_results("select drug, comments 
		from ci_results_blood 
		where report_id = {$report_id} and drug in( 'VCAM1', 'PSEL' ) ");
		foreach ($rs as $r) {
			if (stripos($r->comments, 'Re-Run') !== false) {
				return $r->comments;
			}
		}
	}

	return '';
}

function trpdd_rerun_reason_mf($assen)
{
	//test

	return '';
}

function trppd_get_drug_by_well($assen, $well, $btype)
{
	global $wpdb, $tr_list_drugs_by_well;
	if (!is_array($tr_list_drugs_by_well)) $tr_list_drugs_by_well = array();
	if (isset($tr_list_drugs_by_well[$assen])) {
		return $tr_list_drugs_by_well[$assen][$well];
	}

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$plate = $wpdb->get_row("select pl.*,ra.* 
	from ci_plates pl 
	JOIN ci_racks ra ON ra.re_ID = pl.pl_rack_ID
	JOIN ci_plates_batches pb ON pl.pl_ID = pb.pl_ID
	join {$batch_tbl} ba ON ba.mid = pb.mid 
	where ba.assen = '{$assen}' 
	and pb.btype = '{$btype}'
	and ra.re_is_uti_panel in (30,31,33,34)
	order by pl.pl_ID desc 
	limit 1", ARRAY_A);


	if ($plate) {
		$assens     = trpdd_get_assens_plate($plate);

		$panel_id 	= $plate['re_panel_id'];
		$childs_ids = $wpdb->get_col("select tg_ID from ci_test_groups where tg_parent = '{$panel_id}'");
		$panel_ids 	= $childs_ids;
		$panel_ids[] = $panel_id;
		$panel_ids 	= implode(',', $panel_ids);
		$rstgs = $wpdb->get_results("select te_name,te_abrev, te_abrev2 , te_well_numbers
												from ci_test_panels 
												where te_group_id in( {$panel_ids}) 
												and (te_abrev2 like '%well%'
													or (te_well_numbers !='' and te_well_numbers is not null ) ) ");
		foreach ($rstgs as $rstg) {
			$wells = !empty($rstg->te_well_numbers) ? $rstg->te_well_numbers : $rstg->te_abrev2;
			$abrvs = explode(',', $wells);
			foreach ($abrvs as $abrv) {
				if (stripos($abrv, '_') === false && !empty($rstg->te_abrev)) {
					$rstg->te_name = $rstg->te_abrev;
				}
				if (stripos($abrv, 'well') === false) {
					$abrv = 'well_' . $abrv;
				}
				$target_names[strtolower($abrv)] = $rstg->te_name;
			}
		}

		for ($t_pos = 1; $t_pos <= 384; $t_pos++) {
			$arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');

			if (empty($arr['assen'])) continue;

			$row_char = chr(floor(($t_pos - 1) / 24) + 65);
			$column = ($t_pos - 1) % 24 + 1;

			if ($plate['re_is_uti_panel'] == 30) {
				$numt = floor(($t_pos - 1) / 24) + 1;
				if ($t_pos % 2 == 0) $numt += 16;
			} else if ($plate['re_is_uti_panel'] == 31) {
				$t_pos2 = $t_pos < 193 ? $t_pos : $t_pos - 192;
				$numt = floor(($t_pos2 - 1) / 24) + 1;
				if ($t_pos2 % 3 == 2) $numt += 8;
				if ($t_pos2 % 3 == 0) $numt += 16;
			}

			$wellname = 'well_' . ($numt < 10 ? '0' . $numt : $numt);
			if (!isset($target_names[$wellname])) {
				$wellname = 'well_' . $numt;
			}
			$target_name = isset($target_names[$wellname]) && !empty($arr['assen']) ? $target_names[$wellname] : '';

			$tr_list_drugs_by_well[$arr['assen']][$row_char . $column] = $target_name;
		}
	}

	return $tr_list_drugs_by_well[$assen][$well];
}


function trrack_display_samples($re_ID)
{
	global $wpdb, $second_ntc_pos, $first_ntc, $ntc3_pos, $ntc4_pos;
	global $rack_ABC;
	global $allow_rack4s;
	global $rack_uti_type;
	global $rack_max6_fun;

	$rack = $wpdb->get_row("select * from ci_racks where re_ID = '{$re_ID}' ");
	if (!$rack) {
		return array(
			'html' => '',
			'count' => 0,
			'array' => array()
		);
	}

	$more_q_field = '';
	if ($allow_rack4s) {
		$more_q_field .= ', iparent ';
	}

	$child_sql = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$child_sql[] = "(select rb.mid,
		                        ba.assen,
		                        ba.patient_name,
		                        ba.dob,
		                        ba.panel_tests_requested,
                                rb.ID, 
                                rb.btype,
                                rb.created,
                                rb.is_rerun,
								rb.subtype,
								rb.other_data
                                {$more_q_field}
                            from  ci_rack_batches rb
							LEFT JOIN {$batch_tbl} ba ON rb.mid=ba.mid 
                                where rb.re_ID = '{$re_ID}' AND rb.btype='{$btype}'
								AND ( ba.mid > 0 or rb.mid <0) ) ";
	}

	$child_sql = implode(' UNION ALL ', $child_sql);

	$sql = "select * from ({$child_sql}) as ball
                                order by ball.ID asc";
	$rs = $wpdb->get_results($sql);
	if (!empty($wpdb->last_error) && stripos($wpdb->last_error, 'is_rerun') > 0) {
		trpdd_plugin_activate();
		$data = trrack_display_samples($re_ID);
		echo $data['html'];
		return $data['html'];
	}
	$batches = array();
	if (count($rs) == 0 && $re_ID > 0) {
		$wpdb->query("delete from ci_rack_batches where re_ID = {$re_ID}");
	} else {
		foreach ($rs as $i => $batch) {
			if (isset($batch->iparent) && $batch->iparent > 0) {
				if (!isset($batches[$batch->iparent]->childs)) {
					$batches[$batch->iparent]->childs = array();
				}
				$batches[$batch->iparent]->childs[] = $batch;
			} else {
				$batches[$batch->ID] = $batch;
			}
		}
	}

	$j = 1;
	$i = 0;

	ob_start();
	$array = array();

	if (in_array($rack->re_is_uti_panel, array(45, 46, 47, 48, 49))) {
		$batch = new stdClass();
		$batch->assen = 'NEC';
		$batch->well = 'A1';
		trrack_display_sample_row($batch);
	}

	$config_plate = trpdd_plate_map_config($rack->re_is_uti_panel);
	if (!empty($config_plate) && isset($config_plate['wellperpatient'])) {
		$first_ntc 	= 0;
		$second_ntc_pos = 0;
		$ntc3_pos 	= 0;
		$ntc4_pos 	= 0;
	}

	foreach ($batches as $batch) {
		$i++;

		if (!empty($config_plate) && isset($config_plate['wellperpatient'])) {
			$j = $i;
		} else if ($rack->re_is_uti_panel == 20) {
			if ($j == 1 || $j == 2) $j++;
			if ($j == 1 || $j == 2) $j++;
		} else if ($rack->re_is_uti_panel == 21 || $rack->re_is_uti_panel == 22) {
			//blood FA / MF
			$j = $i;

			if ($i == 2) $j = 25;
			else if ($i == 3) $j = 49;
		} else if (in_array($rack->re_is_uti_panel, array(-4, 10, 11, 12, 13, 14, 15, 16, 18, 30, 31, 32, 33, 34, -300, 35, 36, 37, 38, 40, 41))) {
			//style ...
			$j = $i;
		} else if ($rack->re_is_uti_panel == 17) {
			//Rpp new
			if ($j == 74 || $j == 82 || $j == 90) $j++;
		} else if ($rack->re_is_uti_panel == 42) {
			$j = $i;

			if ($i == 2) $j = 33;
			else if ($i == 3) $j = 65;
		} else if ($rack->re_is_uti_panel == 43) {
			$j = ($i - 1) * 2 + 1;
		} else if ($rack->re_is_uti_panel == 44) {
			$j = ($i - 1) * 12 + 1;
		} else if (in_array($rack->re_is_uti_panel, array(45, 46, 47, 48, 49))) {
			if ($j <= 1) $j = 2;
		} else if (in_array($rack->re_is_uti_panel, array(50))) {
			$j =  ($i % 2 == 0 ? 5 : 1) + (ceil($i / 2) - 1) * 12;
		} else if ($rack->re_is_uti_panel == -1) {
			//Respiratory Light
			$j = $i;
		} else if ($rack_max6_fun && $rack->re_is_uti_panel == -3) {
			//Fungal
			$j = $rack_ABC ? (($i - 1) * 16) + 1 : $i;
		} else if ($rack_uti_type == 3 && $rack->re_is_uti_panel == 1) {
			if ($i == 2) {
				$j = $rack_ABC ? 33 : 5;
			} else if ($i == 3) {
				$j = $rack_ABC ? 65 : 9;
			}
		} else {
			if ($j == $first_ntc || $j == $second_ntc_pos || $j == $ntc3_pos || $j == $ntc4_pos) $j++;
			if ($j == $first_ntc || $j == $second_ntc_pos || $j == $ntc3_pos || $j == $ntc4_pos) $j++;
			if ($j == $first_ntc || $j == $second_ntc_pos || $j == $ntc3_pos || $j == $ntc4_pos) $j++;
			if ($j == $first_ntc || $j == $second_ntc_pos || $j == $ntc3_pos || $j == $ntc4_pos) $j++;
		}

		$welld = tr_rack_well_by_position($j, $rack->re_is_uti_panel);

		$array[$welld] = $batch->assen;

		if (!$rack_uti_type == 3 || !$rack || $rack->re_is_uti_panel != 1) {
			$j++;
		}

		if (!empty($batch->dob)) {
			$batch->dob = tr_pdd_display_date($batch->dob);
		}
		$positiontext = '';
		if ($batch->subtype == 'ctrl') {
			$batch->assen = 'Controls';
		} else if ($batch->mid < 0) {
			$batch->assen = 'Placeholder';
			if ($rack->re_is_uti_panel == 80) {
				if ($batch->mid == -11) {
					$batch->assen = 'MOL00001001';
					$welld = 'O13';
					$positiontext = 'PC';
				} else if ($batch->mid == -12) {
					$batch->assen = 'MOL00001002';
					$welld = 'P13';
					$positiontext = 'NC';
				}
			}
		}


		$batch->other_data 	= !empty($batch->other_data) ? json_decode($batch->other_data, true) : array();
		$batch->position 	= !empty($positiontext) ? $positiontext : $i;
		$batch->well		= $welld;
		trrack_display_sample_row($batch);
	}
	$html = ob_get_clean();
	return array(
		'html' => $html,
		'count' => count($batches),
		'array' => $array
	);
}

function trrack_display_sample_row($batch)
{
	global $wpdb;
	global $allow_rack4s, $number_p_well;
	global $all_groups;
	$all_tests = array();

	$tr_class = ' childp ';
	$tr_class .= ' assen_' . $batch->assen;
	$tr_class .= ' rbid_' . $batch->ID;

	if (empty($all_groups)) {
		$ts = $wpdb->get_results("select tg_ID, 
                                tg_name, 
                                tg_label_name,
                                tg_notestcheckbox 
                                from ci_test_groups");
		$all_groups = array();
		foreach ($ts as $t) {
			$all_groups[$t->tg_ID] = $t;
		}
	}


	$panels_print = array();
	$panels_ordered = array();
	if (in_array($batch->btype, array('pcr', 'blood'))) {
		foreach (explode(',', $batch->panel_tests_requested) as $t) {
			if (stripos($t, 'GROUP_') !== false) {
				$group_id = str_replace('GROUP_', '', $t);
				if (isset($all_groups[$group_id])) {
					$panels_ordered[] = $all_groups[$group_id]->tg_name;

					if (!empty($all_groups[$group_id]->tg_label_name)) {
						$panels_print[] = $all_groups[$group_id]->tg_label_name;
					} else if (isset($all_groups[$group_id]->tg_notestcheckbox) && $all_groups[$group_id]->tg_notestcheckbox) {
						foreach ($all_tests as $test) {
							if ($test->te_group_id == $group_id && !empty($test->te_label_barcode)) {
								$panels_print[] = $test->te_label_barcode;
							}
						}
					}
				}
			} else if ($t > 0 && isset($all_tests[$t])) {
				if (isset($all_tests[$t]->te_label_barcode) && !empty($all_tests[$t]->te_label_barcode)) {
					$panels_print[] = $all_tests[$t]->te_label_barcode;
				}
			}
		}
	}

	$panels_print_str = implode('|', $panels_print);
	if ($batch->btype == 'blood') {
		$panels_print_str = '';
	}
?>
	<tr data-rbid="<?php echo $batch->ID ?>" class="<?php echo $tr_class; ?>" data-subtype="<?php echo $batch->subtype ?>">
		<td><?php if ($batch->mid > 0) echo '<input type="checkbox" value="' . $batch->mid . '" />' ?></td>
		<td><span class="position"><?php echo $batch->position ?></span></td>
		<?php if (!$allow_rack4s) { ?>
			<td class="assen assennum_1" data-mid="<?php echo $batch->mid ?>" data-dob="<?php echo $batch->dob ?>" data-panels_print="<?php echo $panels_print_str ?>" data-patient_name="<?php echo $batch->patient_name ?>"><?php echo $batch->assen ?></td>
		<?php } ?>
		<td>
			<?php
			if (in_array($batch->subtype, array('V', 'P'))) {
				echo '<span>' . $batch->subtype . '</span>';
				//show dropdown for study
				if ($batch->btype == 'study') {

					$testkey = $batch->subtype == 'V' ? 'vcam' : 'psel';

					$vptypes = $wpdb->get_results("select * from ci_study_tests 
						where abrev like '%{$testkey}%'
						and ID in ({$batch->panel_tests_requested})");

					echo '<select data-fname="vp_type" style="margin-left:20px;width:180px">';
					echo '<option value="">Select</option>';
					foreach ($vptypes as $testarr) {
						$selected = $testarr->ID == $batch->other_data['vp_type'] ? 'selected' : '';
						echo '<option value="' . $testarr->ID . '" ' . $selected . '>' . $testarr->abrev . '</option>';
					}
					echo '</select>';
				} else {
					if ($batch->subtype == 'V') {
						echo '<span style="margin-left:20px">FA VCAM-1</span>';
					} else {
						echo '<span style="margin-left:20px">FA PSEL</span>';
					}
				}
			} else if (!tr_get_option('ci_pdf_data', 'rack_hide_well')) {
				echo $batch->well;
			}
			$color = 'green';
			if ($batch->is_rerun == 2) {
				$color = 'red';
			}
			if ($batch->is_rerun) echo '<span class="scircle ' . $color . 'circle" title="Rerun : ' . (isset($batch->reason_rerun) ? $batch->reason_rerun : '') . '">R</span>';


			?>
		</td>
		<?php if ($allow_rack4s) { ?>
			<td class="assen assennum_1" data-mid="<?php echo $batch->mid ?>" data-dob="<?php echo $batch->dob ?>" data-panels_print="<?php echo $panels_print_str ?>" data-patient_name="<?php echo $batch->patient_name ?>"><?php echo $batch->assen ?></td>
			<?php
			$maxia = $number_p_well > 0 ? $number_p_well - 2 : 2;

			for ($ia = 0; $ia <= $maxia; $ia++) {
				$childbatch = isset($batch->childs[$ia]) ? $batch->childs[$ia] : new stdClass();
				$childassen = '';
				if ($childbatch && isset($childbatch->assen)) {
					$childassen = $childbatch->assen;
				}
				if (!empty($childbatch->dob)) {
					$childbatch->dob = tr_pdd_display_date($childbatch->dob);
				}
			?>
				<td class="assen assennum_<?php echo $ia + 2 ?>" data-mid="<?php echo isset($childbatch->mid) ? $childbatch->mid : '' ?>" data-dob="<?php echo isset($childbatch->dob) ? $childbatch->dob : '' ?>" data-panels_print="<?php echo $panels_print_str ?>" data-patient_name="<?php echo isset($childbatch->patient_name) ? $childbatch->patient_name : '' ?>"><?php echo $childassen ?></td>
			<?php } ?>
		<?php } ?>

	</tr>
<?php
}

function trpdd_plate_map_config($num)
{
	$arr	= array();
	$file 	= TRCPDD_PATH . 'plate_maps/' . $num . '.json';
	if (file_exists($file)) {
		$arr = json_decode(file_get_contents($file), true);
		if (isset($config_plate['nextwellbyrow']) && $config_plate['nextwellbyrow'] = 0) {
			$config_plate['nextwellbyrow'] = 1;
		}
	}
	return $arr;
}

function trpdd_plate_map_columns($columns)
{
	$mapped_columns = array();
	if (!is_array($columns)) {
		$columns = array($columns);
	}

	foreach ($columns as $column) {
		if (is_string($column) && strpos($column, '-') !== false) {
			list($start, $end) = array_map('intval', explode('-', $column, 2));
			if ($start > 0 && $end >= $start) {
				for ($i = $start; $i <= $end; $i++) {
					$mapped_columns[] = $i;
				}
			}
		} else {
			$column = intval($column);
			if ($column > 0) {
				$mapped_columns[] = $column;
			}
		}
	}

	return $mapped_columns;
}

function trpdd_plate_patient_block_wells($config_plate, $position)
{
	if (empty($config_plate['patient_blocks']) || empty($position)) {
		return array();
	}

	$type = isset($config_plate['type']) ? intval($config_plate['type']) : 384;
	$column_count = $type == 96 ? 12 : 24;
	$row_count = $type == 96 ? 8 : 16;

	foreach ($config_plate['patient_blocks'] as $block) {
		$start = isset($block['start']) ? intval($block['start']) : 0;
		$end = isset($block['end']) ? intval($block['end']) : 0;
		if ($position < $start || $position > $end || empty($block['column_blocks'])) {
			continue;
		}

		$patient_columns = $block['column_blocks'];
		$patients_per_row = !empty($block['patients_per_row']) ? intval($block['patients_per_row']) : count($patient_columns);
		if ($patients_per_row <= 0) {
			continue;
		}

		$relative_position = $position - $start;
		if (!empty($block['patient_order']) && $block['patient_order'] == 'by_column_block') {
			$total_patients = $end >= $start ? $end - $start + 1 : 0;
			$patients_per_column_block = (int) ceil($total_patients / $patients_per_row);
			if ($patients_per_column_block <= 0) {
				continue;
			}
			$row_offset = $relative_position % $patients_per_column_block;
			$block_index = (int) floor($relative_position / $patients_per_column_block);
		} else {
			$row_offset = floor($relative_position / $patients_per_row);
			$block_index = $relative_position % $patients_per_row;
		}

		$row = (isset($block['row']) ? intval($block['row']) : 1) + $row_offset;
		if ($row < 1 || $row > $row_count) {
			continue;
		}

		if (!isset($patient_columns[$block_index])) {
			continue;
		}

		$layout_wells = array();
		foreach (trpdd_plate_map_columns($patient_columns[$block_index]) as $col) {
			if ($col > $column_count) {
				continue;
			}
			$layout_wells[] = (($row - 1) * $column_count) + $col;
		}

		return $layout_wells;
	}

	return array();
}

function trpdd_plate_layout_target_index($config_plate, $physical_pos)
{
	if (empty($config_plate['wellperpatient'])) {
		return 0;
	}

	$column_count = isset($config_plate['type']) && intval($config_plate['type']) == 96 ? 12 : 24;
	$row = floor(($physical_pos - 1) / $column_count) + 1;
	$col = (($physical_pos - 1) % $column_count) + 1;
	if (!empty($config_plate['target_columns']) && isset($config_plate['target_columns'][$col])) {
		return intval($config_plate['target_columns'][$col]);
	}
	if (!empty($config_plate['patient_blocks'])) {
		foreach ($config_plate['patient_blocks'] as $block) {
			if (empty($block['column_blocks'])) {
				continue;
			}

			$patients_per_row = !empty($block['patients_per_row']) ? intval($block['patients_per_row']) : count($block['column_blocks']);
			if ($patients_per_row <= 0) {
				continue;
			}

			$start = isset($block['start']) ? intval($block['start']) : 1;
			$end = isset($block['end']) ? intval($block['end']) : $start;
			$row_start = isset($block['row']) ? intval($block['row']) : 1;
			$row_end = $row_start + (int) ceil(($end - $start + 1) / $patients_per_row) - 1;
			if ($row < $row_start || $row > $row_end) {
				continue;
			}

			foreach ($block['column_blocks'] as $column_block) {
				$columns = trpdd_plate_map_columns($column_block);
				$target_index = array_search($col, $columns);
				if ($target_index !== false) {
					return $target_index + 1;
				}
			}
		}
	}

	$blocks = array();
	if (!empty($config_plate['sample_blocks'])) {
		$blocks = array_merge($blocks, $config_plate['sample_blocks']);
	}
	if (!empty($config_plate['control_blocks'])) {
		$blocks = array_merge($blocks, $config_plate['control_blocks']);
	}

	foreach ($blocks as $block) {
		if (empty($block['row'])) {
			continue;
		}

		$row_start = intval($block['row']);
		$row_end = $row_start + intval($config_plate['wellperpatient']) - 1;
		if ($row < $row_start || $row > $row_end) {
			continue;
		}

		if (isset($block['columns'])) {
			$columns = is_array($block['columns']) ? $block['columns'] : array($block['columns']);
			if (!in_array($col, array_map('intval', $columns))) {
				continue;
			}
		} else {
			$start = isset($block['start']) ? intval($block['start']) : 0;
			$end = isset($block['end']) ? intval($block['end']) : 0;
			$col_offset = isset($block['col_offset']) ? intval($block['col_offset']) : 0;
			$col_start = $start + $col_offset;
			$col_end = $end + $col_offset;
			if ($col < $col_start || $col > $col_end) {
				continue;
			}
		}

		return ($row - $row_start) + 1;
	}

	return 0;
}
