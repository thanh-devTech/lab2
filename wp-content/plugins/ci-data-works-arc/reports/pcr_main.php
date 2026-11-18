<?php
global $tr_reflex_hide_groups;

$npos = 1;
$need_break_page = false;

if (!$hide_controls || $always_show_controls) {
?>
	<table class="controls" style="margin-top:0;margin-bottom:2px;">
		<tr>
			<td style="width:32%;border:2px solid #000">
				<div style="display:inline-block;width:80px;border-bottom:1px solid #000;font-weight: bold;font-size:11px;">Controls</div>
				<table class="nopadding" style="line-height: 12px">
					<?php if ($remove_crossed == false) { ?>
						<tr>
							<td>Patient Extraction Control <sup><?php echo $npos ?></sup></td>
							<td class="right">PASS</td>
						</tr>
						<tr>
							<td>Endogenous Positive Control <sup><?php echo $npos++ ?></sup></td>
							<td class="right">PASS</td>
						</tr>
					<?php } ?>
					<tr>
						<td><?php if (!$hide_text_Pathogen) echo 'Pathogen' ?> Positive Control <sup><?php echo $npos++ ?></sup></td>
						<td class="right">PASS</td>
					</tr>
					<tr>
						<td><?php if (!$hide_text_Pathogen) echo 'Pathogen' ?> Negative Control <sup><?php echo $npos++ ?></sup></td>
						<td class="right">PASS</td>
					</tr>
				</table>
			</td>
			<td style="padding-left:20px;line-height: 16px;">
				<?php
				$npos = 1;
				if ($remove_crossed == false) {
				?>
					<div class="font8">(<?php echo $npos++ ?>) Endogenous control confirms sample collection,
						DNA/RNA extraction, and assay enzyme activity
					</div>
				<?php } ?>
				<div class="font8">(<?php echo $npos++ ?>) Positive control is synthetic inactive pathogen</div>
				<div class="font8">(<?php echo $npos++ ?>) Negative control contains primers, probe, and enzymes
					with no DNA/RNA template
				</div>
				<?php
				if ($show_controls_negative) {
					echo '<div class="font8">(' . ($npos++) . ') Reference Range is Not Detected</div>';
				}
				?>

				<?php if ($remove_crossed == false) { ?>
					<div class="font8">(<?php echo $npos++ ?>) A "Detected" result indicates the presence of a
						pathogen (99.99% confidence) above the assay cutoff.
					</div>
					<?php /*<div class="font8">Assay cutoff is represented by CFU (bacteria), PFU (viruses) or Copy Number
					(DNA)
				</div>*/ ?>
				<?php } ?>
			</td>
		</tr>
	</table>

	<?php
}


if (tr_get_option('ci_pdf_data', 'pdf_amended_results') && intval($report['final_results']) === 3) {
	echo '<h1 class="center amended_results red" style="font-size:14px">Amended Results</h1>';
}

//summary
if (count($found_root_groups) > 1 && !$has_covid && isset($show_summary) && $show_summary) {
	ob_start();

	foreach ($drug_groups as $groupid => $groupdata) {

		$hide_group_Cycle_Threshold = $hide_Cycle_Threshold;
		if ($groupdata['hide_ct']) {
			$hide_group_Cycle_Threshold = $groupdata['hide_ct'];
		}

		$detected_type = true;
		if ($groupdata['showed']) continue;

		ob_start();
		foreach ($groupdata['tests'] as $arr) {
			if (!$arr['detected_type']) {
				$detected_type = false;
			}
			$pos_neg_vls = $pos_neg;

			if ($arr['te_is_detected']) {
				$pos_neg_vls['POS'] = 'Detected';
				$pos_neg_vls['PVL'] = 'Detected-Very Low';
				$pos_neg_vls['POL'] = 'Detected-Low';
				$pos_neg_vls['POM'] = $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium';
				$pos_neg_vls['POH'] = 'Detected-High';
				$pos_neg_vls['PVH'] = 'Detected-Very High';
				$pos_neg_vls['NEG'] = 'Not Detected';
				$pos_neg_vls['IND'] = 'Indeterminate';
			}

			$pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
			if (isset($arr['flag_adding']) && !empty($arr['flag_adding'])) {
				$pos_neg_str .= '<div>' . $arr['flag_adding'] . '</div>';
			}


			if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVH', 'PVL'))) {
	?>
				<tr class="<?php echo $report['bold_text_positive'] ? 'rowbold' : 'rowred' ?>">
					<td>
						<?php
						echo htmlspecialchars($arr['drug']);
						if (!empty($arr['te_desc'])) {
							echo '<div class="istyle">' . $arr['te_desc'] . '</div>';
						}
						?>
					</td>
					<td class="center upper"><?php echo ($pos_neg_str) ?></td>
					<?php if (!$groupdata['is_covid'] && !$hide_group_Cycle_Threshold) { ?>
						<td class="center"><?php if ($groupdata['parent'] == 0) echo htmlspecialchars($arr['conc']) ?></td>
					<?php } ?>
					<td><?php echo trpdd_filter_comments_in_pdf($arr['comments']); ?></td>
				</tr>
			<?php
			}
			$has_report = true;
		}
		$drug_content = ob_get_clean();
		if (!empty($drug_content)) {
			$type_text = '';
			if ($arr['te_is_detected']) {
				$type_text = '(Low/Medium/High)';
			}

			if ($detected_type || strtoupper($groupdata['name']) == 'RESPIRATORY') {
				$type_text = 'reported as detected versus not detected';
			}
			?>
			<tr class="headernoborder">
				<th colspan="<?php echo $groupdata['is_covid'] || $hide_group_Cycle_Threshold ? 3 : 4 ?>" class="center upper" style="border:none;font-size:14px">
					SUMMARY <?php echo htmlspecialchars($groupdata['name'] . ($groupdata['parent'] > 0 || stripos($groupdata['name'], ', pcr') > 0 ? '' : ' Results')) ?>
					<?php if (!empty($groupdata['sample_type']) && !trpdd_pcr_show_specimen_source($report, $groupdata)) { ?>
						<span class="sample_type stype1"> Specimen: <?php echo $groupdata['sample_type'] ?></span>
					<?php }
					$locationpdf = trpdd_get_batch_meta($report['mid'], 'pcr', 'pdf_location');
					if (!empty($locationpdf)) {
						$locationlabel = tr_get_option('ci_pdf_data', 'report_loc_source') ? 'Specimen Source' : 'Location';
						echo '<div class="sample_type stype4">' . $locationlabel . ': ' . $locationpdf . '</div>';
					}
					if (isset($result_settings['pdf_obx_source']) && $result_settings['pdf_obx_source']) {
						$obx_source = trpdd_get_batch_meta($report['mid'], 'pcr', '_obx_source');
						if (!empty($obx_source)) {
							echo '<div class="sample_type stype4">Source: ' . $obx_source . '</div>';
						}
					}
					if (function_exists('trpdd_pcr_get_specimen_source')) {
						$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
						if (!empty($specimen_source)) {
							echo '<div class="sample_type stype4">Specimen Source: ' . $specimen_source . '</div>';
						}
					}
					if (tr_get_option('ci_req_settings_pcr', 'e_wound_sources')) {
						$w_source = trpdd_get_batch_meta($report['mid'], 'pcr', 'wound_sample_source');
						if (!empty($w_source)) {
							if (is_array($w_source)) {
								$w_source = implode(', ', $w_source);
							}
							echo '<div class="sample_type stype4">Source: ' . $w_source . '</div>';
						}
					}
					?>
				</th>
			</tr>
			<tr class="title_tbl">
				<th style="width:35%">Test Performed</th>
				<th class="center" style="width:20%">Lab Result
					<?php
					if (!$remove_crossed) {
						echo ' <sup>4</sup>';
					}

					if (!$groupdata['is_covid'] && $groupdata['parent'] == 0 && !$hide_group_Cycle_Threshold && !empty($type_text)) {
						echo '<div>' . $type_text . '</div>';
					}
					?>
				</th>
				<?php if (!$groupdata['is_covid'] && !$hide_group_Cycle_Threshold) { ?>
					<th class="center" style="width:20%">Cycle Threshold*</th>
				<?php } ?>
				<th class="center">Comments</th>
			</tr>
		<?php
			echo $drug_content;
		}
	}

	$summary = ob_get_clean();
	if ($summary) {
		?>
		<table class="tdborder small_table">
			<?php echo $summary; ?>
		</table>
		<div class="break"></div>
		<?php
	}
}

if (tr_get_option('ci_pdf_data', 'cli_new_abx') && isset($ci_abx_reports[$report['id']]) && $ci_abx_reports[$report['id']]) {
	$cli_otheropts = $wpdb->get_var("select cli_other_options from ci_clinic where cID = {$report['cli_ID']}");
	$cli_otheropts = json_decode($cli_otheropts, true);

	if (is_array($cli_otheropts) && $cli_otheropts['newabx']) {
		$has_manual2_antibiotic = false;
		$has_manual_antibiotic = false;
	}
}

if ($has_manual2_antibiotic && tr_get_option('ci_result_settings_pcr', 'e_mic2') == 2) {

	include('manual2_antibiotic.php');
} else if ($has_manual2_antibiotic && tr_get_option('ci_result_settings_pcr', 'e_mic2') == 1) {

	include('manual1_antibiotic.php');
} else  if ($has_manual_antibiotic) {
	include('manual_antibiotic.php');
}

if ($need_break_summary_page && $has_pcr_results) {
	echo '<div class="break"></div>';
}


foreach ($drug_groups as $groupid => $groupdata) {


	$has_subgroup 		= false;
	$hide_group_Cycle_Threshold = $hide_Cycle_Threshold;
	if ($groupdata['hide_ct']) {
		$hide_group_Cycle_Threshold = $groupdata['hide_ct'];
	}
	$show_group_range 		= isset($groupdata['other_options']['show_range']) && $groupdata['other_options']['show_range'];
	$show_value_in_result 	= isset($groupdata['other_options']['show_value_in_result']) && $groupdata['other_options']['show_value_in_result'];
	$show_units_column 		= isset($groupdata['other_options']['show_units_col']) && $groupdata['other_options']['show_units_col'];
	$percent_positive 		= isset($groupdata['other_options']['percent_positive']) && $groupdata['other_options']['percent_positive'];
	$hidecommentscol 		= isset($groupdata['other_options']['hide_comments']) && $groupdata['other_options']['hide_comments'];

	$count_row 			= 0;
	$detected_type 		= true;

	if (isset($groupdata['parent']) && $groupdata['parent'] > 0 && (!$groupdata['show'] || $groupdata['showed'])) continue;

	if (isset($_GET['debug2'])) {
		var_dump($groupdata);
		echo '<br><br>';
		echo 'subgroups:<br>';
		var_dump($groupdata['subgroups']);
		echo '<br><br>';
	}

	$subgroup_html = '';
	if (count($groupdata['subgroups']) > 0) {

		$inum = 0;
		$group_has_content = false;
		$htmlsubgroups 	= array(
			'col1' 		=> array(),
			'col2' 		=> array(),
			'normal' 	=> array(),
		);

		foreach ($groupdata['subgroups'] as $subid => $subname) {
			$subgroupdata = isset($drug_groups[$subid]) ? $drug_groups[$subid] : false;


			if (!$subgroupdata || count($subgroupdata['tests']) == 0) continue;

			if (isset($tr_reflex_hide_groups[$subid]) && $tr_reflex_hide_groups[$subid] === true) {
				if (isset($_GET['html'])) {
					echo 'skip group: ' . $groupdata['name'] . ' by reflex';
				}
				continue;
			}


			$sub_show_value_in_result 	= isset($subgroupdata['other_options']['show_value_in_result']) && $subgroupdata['other_options']['show_value_in_result'];
			$sub_show_range_in_result 	= isset($subgroupdata['other_options']['show_range']) && $subgroupdata['other_options']['show_range'];

			$is_UA_RESULTING = stripos($subgroupdata['name'], 'UA RESULTING') !== false;
			if ($is_UA_RESULTING) {
				$sub_show_value_in_result = true;
			}

			$is_split_tbl 	= false;
			$smalltbl_col 	= 0;
			$comment_below  = isset($subgroupdata['other_options']['comment_below']) && $subgroupdata['other_options']['comment_below'] ? true : false;
			if (isset($groupdata['other_options']['comment_below']) && $groupdata['other_options']['comment_below']) {
				$comment_below = true;
			}
			$is_smalltbl 	= in_array(strtoupper($subname), array('GRAM NEGATIVE', 'GRAM POSITIVE', 'NO GRAM STAIN', 'FUNGI', 'FUNGUS', 'ATYPICAL', 'VIRUSES', 'PROTOZOA', 'OTHER'));


			if (isset($subgroupdata['other_options']['pdf_small_tbl']) && $subgroupdata['other_options']['pdf_small_tbl'] == 3) {
				$smalltbl_col = 0;
				$is_smalltbl = false;
			} else if (isset($subgroupdata['other_options']['pdf_small_tbl']) && $subgroupdata['other_options']['pdf_small_tbl'] > 0) {
				$smalltbl_col = $subgroupdata['other_options']['pdf_small_tbl'];
				$is_smalltbl = true;
			}
			if (isset($subgroupdata['other_options']['split_table']) && $subgroupdata['other_options']['split_table']) {
				$is_smalltbl = false;
			}



			$table_class 	= ' is_smalltbl';
			$style 			= '';

			//start small tbl
			ob_start();

			if ($is_smalltbl && empty($smalltbl_col)) {
				$smalltbl_col = ($inum % 2) + 1;
				$inum++;
			}

			if (isset($subgroupdata['other_options']['split_table']) && $subgroupdata['other_options']['split_table']) {
				$is_split_tbl = true;
				$table_class = ' split_tbl ';
			}

			$cols_count = $is_smalltbl ? 2 : 3;

			$colwidths = array(
				1 => $is_smalltbl ? '70%' : '40%',
				2 => '30%',
			);

			if ($is_split_tbl) {
				$cols_count = 5;
				$colwidths = array(
					1 => '35%',
					2 => '15%',
				);
			}
			if ($sub_show_value_in_result) {
				$cols_count++;
				if ($is_split_tbl) {
					$cols_count++;
				}
				$colwidths[1] = $is_split_tbl ? '35%' : ($is_smalltbl ? '55%' : '40%');
				$colwidths[2] = $is_split_tbl ? '15%' : '18%';
			}

			if ($percent_positive) {
				$cols_count++;
				if ($is_split_tbl) {
					$cols_count++;
				}
			}

			if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
				$cols_count++;
				if ($is_split_tbl) {
					$cols_count++;
				}

				$colwidths[1] = $is_split_tbl ? '25%' : ($is_smalltbl ? '55%' : '38%');
				$colwidths[2] = $is_split_tbl ? '11%' : ($is_smalltbl ? '24%' : '16%');
			}

			$subname_tt = $subname;
			if (count($groupdata['subgroups']) == 1 && empty($groupdata['tests'])) {
				$subname_tt .= ' Results';
			}

			if (isset($subgroupdata['other_options']['panel_pdf_title']) && !empty($subgroupdata['other_options']['panel_pdf_title'])) {
				$subname_tt = $subgroupdata['other_options']['panel_pdf_title'];
			}


		?>
			<div style="<?php echo $style ?>">
				<table class="border tdborder small_table <?php echo $table_class ?>">
					<thead>
						<?php if (count($groupdata['subgroups']) > 1 || !empty($groupdata['tests'])) { ?>
							<tr class="header_tbl">
								<th colspan="<?php echo $cols_count ?>"><?php echo $subname_tt ?></th>
							</tr>
						<?php } ?>

						<tr class="title_tbl">
							<th style="width:<?php echo $colwidths[1] ?>"><?php echo stripos($subname, 'ANTIBIOTIC RESISTANCE') !== false ? 'Marker' : $group_title ?></th>

							<th class="resultcol" style="width:<?php echo $colwidths[2] ?>">Result<?php if (!$remove_crossed) echo ' <sup>4</sup>' ?></th>
							<?php

							if ($sub_show_value_in_result) {
								echo '<th style="width:' . $colwidths[2] . '">Flag</th>';
							}
							if (!$is_split_tbl && $percent_positive) {
								echo '<th style="width:10%">% Positive</th>';
							}

							if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
								echo '<th class="small" style="width:' . $colwidths[2] . '">Reference Range</th>';
							} else if ($groupdata['is_covid'] && tr_get_option('ci_pdf_data', 'pcrcovid_rangecl')) {
								echo '<th>Reference Range</th>';
							}
							?>
							<?php
							if ($is_split_tbl) {
								echo '<th class="space_cell" style="width:1.4% !important;border:none;background:none"></th>';
								echo '<th style="width:' . $colwidths[1] . '">' . (stripos($subname, 'ANTIBIOTIC RESISTANCE') !== false ? 'Marker' : $group_title) . '</th>';
								echo '<th class="resultcol" style="width:' . $colwidths[2] . '">Result ' . (!$remove_crossed ? ' <sup>4</sup>' : '') . '</th>';
								if ($sub_show_value_in_result) {
									echo '<th style="width:' . $colwidths[2] . '">Flag</th>';
								}
								if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
									echo '<th class="small" style="width:' . $colwidths[2] . '">Reference Range</th>';
								}
							} else if (!$is_smalltbl) {
								echo '<th>' . ($enable_utinrow ? '' : 'Comments') . '</th>';
							}
							?>
						</tr>
					</thead>
					<tbody>
						<?php
						if ($is_split_tbl) {
							//echo '<tr><td colspan=2 class="splittd" style="margin:0;border:0;padding:0"><table class="small_table split_firsttbl">';
							$tmp_tests = array();
							foreach ($subgroupdata['tests'] as $arr) {
								$tmp_tests[] = $arr;
							}
							$subgroupdata['tests'] = $tmp_tests;
							$has_close_row = false;

							foreach ($subgroupdata['tests'] as $isub => $arr) {

								$pos_neg_vls = $pos_neg;

								if ($arr['te_is_detected']) {
									$pos_neg_vls['POS'] = 'Detected';
									$pos_neg_vls['PVL'] = 'Detected-Very Low';
									$pos_neg_vls['POL'] = 'Detected-Low';
									$pos_neg_vls['POM'] = $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium';
									$pos_neg_vls['POH'] = 'Detected-High';
									$pos_neg_vls['PVH'] = 'Detected-Very High';
									$pos_neg_vls['NEG'] = 'Not Detected';
									$pos_neg_vls['IND'] = 'Indeterminate';
								}
								$count_row++;

								$pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
								if (isset($arr['flag_adding']) && !empty($arr['flag_adding'])) {
									$pos_neg_str .= '<div>' . $arr['flag_adding'] . '</div>';
								}

								$row_class 	 = in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVH', 'PVL')) ? ($report['bold_text_positive'] ? 'rowbold' : 'rowred') : '';
								if ($arr['pos-neg'] == 'NEG' && $report_green_neg) {
									$row_class = 'rowneg';
								}

								if ($isub % 2 == 0) {
									echo '<tr>';
									$has_close_row = false;
								}

								if ($arr['te_font_style'] == 1) {
									$arr['te_name'] = '<i>' . $arr['te_name'] . '</i>';
								} else if ($arr['te_font_style'] == 2) {
									$arr['te_name'] = '<b>' . $arr['te_name'] . '</b>';
								}

								echo '<td class="' . $row_class . '">' . $arr['te_name'] . '</td>';
								echo '<td class="center ' . $row_class . '" >' . ($pos_neg_str) . '</td>';
								if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
									echo '<td class="center">Not Detected</td>';
								}
								if ($isub % 2 == 1) {
									echo '</tr>';
									$has_close_row = true;
								} else {
									echo '<td class="space_cell"></td>';
								}


								$has_report = true;
							}

							if (!$has_close_row) {
								if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
									echo '<td></td>';
								}
								echo '<td></td><td></td></tr>';
							}
						} else {


							foreach ($subgroupdata['tests'] as $arr) {
								if ($arr['te_abrev'] == 'RppH' || (isset($arr['te_is_control']) && $arr['te_is_control'])) {
									continue;
								}

								$pos_neg_vls = $pos_neg;

								if ($arr['te_is_detected']) {
									$pos_neg_vls['POS'] = 'Detected';
									$pos_neg_vls['PVL'] = 'Detected-Very Low';
									$pos_neg_vls['POL'] = 'Detected-Low';
									$pos_neg_vls['POM'] = $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium';
									$pos_neg_vls['POH'] = 'Detected-High';
									$pos_neg_vls['PVH'] = 'Detected-Very High';
									$pos_neg_vls['NEG'] = 'Not Detected';
									$pos_neg_vls['IND'] = 'Indeterminate';
								}
								//var_dump($pos_neg_vls);
								$count_row++;

								$pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
								if (isset($arr['flag_adding']) && !empty($arr['flag_adding'])) {
									$pos_neg_str .= '<div>' . $arr['flag_adding'] . '</div>';
								}



								$row_class 	 = in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVH', 'PVL')) ? (isset($report['bold_text_positive']) && $report['bold_text_positive'] ? 'rowbold' : 'rowred') : '';
								if ($arr['pos-neg'] == 'NEG' && $report_green_neg) {
									$row_class = 'rowneg';
								}



								if ($arr['te_font_style'] == 1) {
									$arr['te_name'] = '<i>' . $arr['te_name'] . '</i>';
								} else if ($arr['te_font_style'] == 2) {
									$arr['te_name'] = '<b>' . $arr['te_name'] . '</b>';
								}

								echo '<tr class="' . $row_class . '">';
								echo '<td style="width:' . $colwidths[1] . '">' . $arr['te_name'] . '</td>';
								if ($sub_show_value_in_result) {
									echo '<td class="center" style="width:' . $colwidths[2] . '">' . $arr['conc'] . '</td>';
								}

								echo '<td class="center" style="width:' . $colwidths[2] . '">' . ($pos_neg_str) . '</td>';
								if ($percent_positive) {
									echo '<td class="center" style="width:10%">' . $arr['percent_positive'] . '</td>';
								}

								if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
									echo '<td class="center">Not Detected</td>';
								} else if ($groupdata['is_covid'] && tr_get_option('ci_pdf_data', 'pcrcovid_rangecl')) {
									echo '<td class="center">Negative</td>';
								}
								if (!$is_smalltbl) {
									echo '<td>' . trpdd_filter_comments_in_pdf($arr['comments']) . '</td>';
								}
								echo '</tr>';

								if ($comment_below && !empty($arr['comments'])) {
									echo '<tr class="rowcomments"><td colspan="' . $cols_count . '" >' . trpdd_filter_comments_in_pdf($arr['comments']) . '</td></tr>';
								}
								$has_report = true;
							}
						}

						?>
					</tbody>
				</table>
				<?php
				if (!empty($subgroupdata['comments'])) {
					$subgroupdata['comments'] = str_replace('[report_date]', date('m/d/Y h:iA', strtotime($report['approve_date'])), $subgroupdata['comments']);
					echo '<div style="padding-top:3px;" class="subgroupnotes">' . trpdd_filter_comments_in_pdf($subgroupdata['comments']) . '</div>';
				}
				?>
			</div>
		<?php
			$coltype = 'normal';
			if ($is_smalltbl) {
				$coltype = 'col' . $smalltbl_col;
			}

			$group_has_content = true;
			$htmlsubgroups[$coltype][] = ob_get_clean();
		} //end subs


		if (!$group_has_content && empty($groupdata['tests'])) {
			continue;
		}


		$group_tt = $groupdata['name'];
		if (stripos($groupdata['name'], 'panel') === false) {
			$group_tt = $groupdata['name'] . ' Results';
		}
		if (stripos($groupdata['name'], 'antigen') !== false && count($groupdata['tests']) < 2) {
			$group_tt = $groupdata['name'];
		} else if (stripos($groupdata['name'], ', pcr') > 0) {
			$group_tt = $groupdata['name'];
		}

		if (isset($groupdata['other_options']['panel_pdf_title']) && !empty($groupdata['other_options']['panel_pdf_title'])) {
			$group_tt = $groupdata['other_options']['panel_pdf_title'];
		}

		if (count($groupdata['subgroups']) == 1 && empty($groupdata['tests'])) {
			$group_tt = $subname_tt;
		}
		$has_subgroup = true;


		if (!empty($htmlsubgroups['col1']) || !empty($htmlsubgroups['col2'])) {
			$subgroup_html .= '<table class="wrapsmalltbl"><tr>';
			$subgroup_html .= '<td style="width:50%;padding-left:0">' . implode('<br style="line-height:4px;"/>', $htmlsubgroups['col1']) . '</td>';
			$subgroup_html .= '<td style="width:50%;padding-right:0">' . implode('<br style="line-height:4px;"/>', $htmlsubgroups['col2'])  . '</td>';
			$subgroup_html .= '</tr></table>';
			if ($enable_utinrow) {
				$subgroup_html .= '<br style="line-height:4px;"/>';
			}
		}

		$subgroup_html .= implode('<br style="line-height:4px;"/>', $htmlsubgroups['normal']);
		$subgroup_html .= '<div style="clear:both;margin-bottom:2px"></div>';
	}

	ob_start();

	$is_UA_RESULTING = stripos($groupdata['name'], 'UA RESULTING') !== false;
	$has_main_tests = false;


	foreach ($groupdata['tests'] as $arr) {

		if (isset($arr['te_is_control']) && $arr['te_is_control']) {
			continue;
		}
		if (isset($result_settings['disable_test_comments']) && $result_settings['disable_test_comments']) {
			$arr['comments'] = '';
		}
		if (!isset($arr['detected_type']) || !$arr['detected_type']) {
			$detected_type = false;
		}
		$pos_neg_vls = $pos_neg;
		if ($arr['te_is_detected'] == '2') {

			$pos_neg_vls['POL'] = 'Low';
			$pos_neg_vls['NEG'] = 'Normal';
			$pos_neg_vls['POH'] = 'High';
			unset($pos_neg_vls['POS']);
			if ($arr['pos-neg'] == 'POS') {
				$arr['pos-neg'] = 'POH';
			}
		} else if ($arr['te_is_detected'] == '1') {
			$pos_neg_vls['POS'] = 'Detected';
			$pos_neg_vls['PVL'] = 'Detected-Very Low';
			$pos_neg_vls['POL'] = 'Detected-Low';
			$pos_neg_vls['POM'] = $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium';
			$pos_neg_vls['POH'] = 'Detected-High';
			$pos_neg_vls['PVH'] = 'Detected-Very High';
			$pos_neg_vls['NEG'] = 'Not Detected';
		}
		$count_row++;
		$row_class = in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVH', 'PVL')) ? (isset($report['bold_text_positive']) && $report['bold_text_positive'] ? 'rowbold' : 'rowred') : '';
		if ($arr['pos-neg'] == 'NEG' && $report_green_neg) {
			$row_class = 'rowneg';
		}

		if ($arr['te_font_style'] == 1) {
			$arr['te_name'] = '<i>' . $arr['te_name'] . '</i>';
		} else if ($arr['te_font_style'] == 2) {
			$arr['te_name'] = '<b>' . $arr['te_name'] . '</b>';
		}

		$pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
		if (isset($arr['flag_adding']) && !empty($arr['flag_adding'])) {
			$pos_neg_str .= '<div>' . $arr['flag_adding'] . '</div>';
		}

		?>
		<tr class="<?php echo $row_class ?>">
			<?php
			if ($is_UA_RESULTING) {

			?>
				<td>
					<?php
					echo $arr['te_name'];
					if (!empty($arr['te_desc'])) {
						echo '<div class="istyle">' . $arr['te_desc'] . '</div>';
					}
					?>
				</td>
				<?php if (!$hide_group_Cycle_Threshold) { ?>
					<td class="center posneg"><?php echo ($pos_neg_str) ?></td>
				<?php } ?>
				<td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
				<?php if ($show_units_column) { ?>
					<td class="center"><?php echo htmlspecialchars($arr['te_units']) ?></td>
				<?php } ?>
				<td class="center"><?php echo $arr['range']; ?></td>
			<?php
			} else {
				echo '<td>';
				echo $arr['te_name'];
				if (!empty($arr['te_desc'])) {
					echo '<div class="istyle">' . $arr['te_desc'] . '</div>';
				}
				echo '</td>';

				if ($show_value_in_result) {
					echo '<td class="center  conc bg_col">' . $arr['conc'] . '</td>';
				} else {
					echo '<td class="center  posneg">' . ($pos_neg_str) . '</td>';
				}

				if (!$groupdata['is_covid'] && !$hide_group_Cycle_Threshold) {
					if (tr_get_option('ci_result_settings_pcr', 'enable_rs_ct_amp_cq') || (isset($groupdata['other_options']['is_rpp']) && $groupdata['other_options']['is_rpp'])) {
						echo '<td class="center">' . htmlspecialchars($arr['conc']) . '</td>';
						echo '<td class="center">' . htmlspecialchars($arr['amp_score']) . '</td>';
						echo '<td class="center">' . htmlspecialchars($arr['cq']) . '</td>';
					} else {
						echo '<td class="center">' . htmlspecialchars($arr['conc']) . '</td>';
					}
				}


				if ($show_units_column) {
					echo '<td class="center units">' . $arr['te_units'] . '</td>';
				}

				if ($show_value_in_result) {
					echo '<td class="center  posneg bg_col">' . ($pos_neg_str) . '</td>';
				}


				if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
					echo '<td class="center">Not Detected</td>';
				} else if ($show_group_range) {
					echo '<td class="center">' . $arr['range'] . '</td>';
				} else if ($groupdata['is_covid'] && tr_get_option('ci_pdf_data', 'pcrcovid_rangecl')) {
					echo '<td class="center">Negative</td>';
				}
				if (!$groupdata['comment_next_row'] && !$hidecommentscol) {
					echo '<td>' . trpdd_filter_comments_in_pdf($arr['comments']) . '</td>';
				}
			}
			?>
		</tr>
		<?php
		$has_report = true;
		$has_main_tests = true;
	}

	$drug_content = ob_get_clean();


	$group_tt = $groupdata['name'];
	if (stripos($groupdata['name'], 'panel') === false) {
		$group_tt = $groupdata['name'] . ' Results';
	}


	if (stripos($groupdata['name'], 'antigen') !== false && count($groupdata['tests']) < 2) {
		$group_tt = $groupdata['name'];
	} else if (stripos($groupdata['name'], ', pcr') > 0) {
		$group_tt = $groupdata['name'];
	} else if (isset($one_group_name) && !empty($one_group_name) && (stripos($one_group_name, 'Express 1hr PCR') !== false || stripos($one_group_name, 'Express PCR') !== false)) {
		$group_tt = 'SARS-COV-2 PCR NUCLEIC ACID';
	}
	if (isset($groupdata['other_options']['panel_pdf_title']) && !empty($groupdata['other_options']['panel_pdf_title'])) {
		$group_tt = $groupdata['other_options']['panel_pdf_title'];
	}

	$after_panel_tt = '';
	if (!tr_get_option('ci_pdf_data', 'report_st_top') && !empty($groupdata['sample_type']) && !trpdd_pcr_show_specimen_source($report, $groupdata)) {
		$after_panel_tt .= '<span class="sample_type stype2"> Specimen: ' . $groupdata['sample_type'] . '</span>';
	}
	$locationpdf = trpdd_get_batch_meta($report['mid'], 'pcr', 'pdf_location');
	if (!empty($locationpdf)) {
		$locationlabel = tr_get_option('ci_pdf_data', 'report_loc_source') ? 'Specimen Source' : 'Location';
		$after_panel_tt .= '<div class="sample_type stype4">' . $locationlabel . ': ' . $locationpdf . '</div> ';
	}
	if (isset($result_settings['pdf_obx_source']) && $result_settings['pdf_obx_source']) {
		$obx_source = trpdd_get_batch_meta($report['mid'], 'pcr', '_obx_source');
		if (!empty($obx_source)) {
			$after_panel_tt .= '<div class="sample_type stype4">Source: ' . $obx_source . '</div>';
		}
	}
	if (function_exists('trpdd_pcr_get_specimen_source')) {
		$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
		if (!empty($specimen_source)) {
			$after_panel_tt .= '<div class="sample_type stype4">Specimen Source: ' . $specimen_source . '</div>';
		}
	}
	if (tr_get_option('ci_req_settings_pcr', 'e_wound_sources')) {
		$w_source = trpdd_get_batch_meta($report['mid'], 'pcr', 'wound_sample_source');
		if (!empty($w_source)) {
			if (is_array($w_source)) {
				$w_source = implode(', ', $w_source);
			}
			$after_panel_tt .= '<div class="sample_type stype4">Source: ' . $w_source . '</div>';
		}
	}

	$meta_key = tr_get_option('ci_result_settings_pcr', 'wound_pdf_source');
	if (!empty($meta_key)) {
		$w_source = trpdd_get_batch_meta($report['mid'], 'pcr', $meta_key);
		if (!empty($w_source)) {
			$after_panel_tt .= '<div class="sample_type stype4">Source: ' . $w_source . '</div>';
		}
	}



	if (!empty($drug_content) || $has_subgroup) {

		if ($is_UA_RESULTING && !empty($pdf_notes)) {
			echo '<div>';
			echo trpdd_filter_comments_in_pdf($pdf_notes);
			echo '</div>';
			$pdf_notes = '';
		}



		$breakinside = '';
		if (($count_row > 0 && $count_row <= 5) || $is_UA_RESULTING) {
			$breakinside = 'avoid';
		}

		if ($need_break_page) {
			$need_break_page = false;
			echo '<div class="break"></div>';
		}

		echo '<div style="' . (!empty($breakinside) ? 'page-break-inside: ' . $breakinside : '') . ';padding-bottom:8px">';

		if ($has_subgroup) {
		?>
			<table class="noboder small_table" style="margin-top:5px">
				<tr class="headernoborder">
					<th style="width:<?php echo strlen($group_tt) > 33 ? '20%' : '30%' ?>;border:none"></th>
					<th colspan="4" class="center " style="border:none;font-size:14px;">
						<?php echo htmlspecialchars($group_tt) ?>
					</th>
					<th style="width:<?php echo strlen($group_tt) > 33 ? '20%' : '30%' ?>;border:none">
						<?php echo $after_panel_tt ?>
					</th>
				</tr>
			</table>
			<?php
		}

		if (!empty($drug_content)) {

			if ($is_UA_RESULTING) {
				$cols_num = 3;
				if ($show_units_column) {
					$cols_num++;
				}
				if (!$hide_group_Cycle_Threshold) {
					$cols_num++;
				}
			?>
				<table class="tdborder small_table">
					<tr class="header_tbl">
						<th colspan="<?php echo $cols_num ?>" class="center upper">
							<?php echo htmlspecialchars($group_tt) ?>
							<?php if (!empty($groupdata['sample_type']) && !trpdd_pcr_show_specimen_source($report, $groupdata)) { ?>
								<span class="sample_type stype3">Specimen: <?php echo $groupdata['sample_type'] ?></span>
							<?php }
							$locationpdf = trpdd_get_batch_meta($report['mid'], 'pcr', 'pdf_location');
							if (!empty($locationpdf)) {
								$locationlabel = tr_get_option('ci_pdf_data', 'report_loc_source') ? 'Specimen Source' : 'Location';
								echo '<div class="sample_type stype4">' . $locationlabel . ': ' . $locationpdf . '</div>';
							}

							if (isset($result_settings['pdf_obx_source']) && $result_settings['pdf_obx_source']) {
								$obx_source = trpdd_get_batch_meta($report['mid'], 'pcr', '_obx_source');
								if (!empty($obx_source)) {
									echo '<div class="sample_type stype4">Source: ' . $obx_source . '</div>';
								}
							}
							if (function_exists('trpdd_pcr_get_specimen_source')) {
								$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
								if (!empty($specimen_source)) {
									echo '<div class="sample_type stype4">Specimen Source: ' . $specimen_source . '</div>';
								}
							}
							if (tr_get_option('ci_req_settings_pcr', 'e_wound_sources')) {
								$w_source = trpdd_get_batch_meta($report['mid'], 'pcr', 'wound_sample_source');
								if (!empty($w_source)) {
									if (is_array($w_source)) {
										$w_source = implode(', ', $w_source);
									}
									echo '<div class="sample_type stype4">Source: ' . $w_source . '</div>';
								}
							}

							$meta_key = tr_get_option('ci_result_settings_pcr', 'wound_pdf_source');
							if (!empty($meta_key)) {
								$w_source = trpdd_get_batch_meta($report['mid'], 'pcr', $meta_key);
								if (!empty($w_source)) {
									echo '<div class="sample_type stype4">Source: ' . $w_source . '</div>';
								}
							}
							?>
						</th>
					</tr>
					<thead>
						<tr class="title_tbl">
							<th>Test</th>
							<?php if (!$hide_group_Cycle_Threshold) { ?>
								<th>Test Outcome</th>
							<?php } ?>
							<th>Measured Result</th>
							<?php if ($show_units_column) { ?>
								<th>Units</th>
							<?php } ?>
							<th>Reference Range</th>
						</tr>
					</thead>
				<?php
			} else {
				$cols_num = 4;

				if ($groupdata['is_covid'] || $hide_group_Cycle_Threshold) {
					$cols_num = 3;
				} else if ((tr_get_option('ci_result_settings_pcr', 'enable_rs_ct_amp_cq') || (isset($groupdata['other_options']['is_rpp']) && $groupdata['other_options']['is_rpp']))) {
					$cols_num = 6;
				}

				if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
					$cols_num++;
				} else if ($show_group_range) {
					$cols_num++;
				}
				if ($hidecommentscol) {
					$cols_num--;
				}
				echo '<table class="tdborder small_table ' . ($groupdata['comment_next_row'] ? 'comment_next_row' : '') . '" style="page-break-inside: auto;margin-top:' . (($has_subgroup) ? ':10px' : '5px') . '">';
				?>

					<thead>
						<tr class="header_tbl">
							<th colspan="<?php echo $cols_num ?>">
								<?php
								echo $group_tt;
								if (!$has_subgroup) {
									echo ' ' . $after_panel_tt;
								}
								?>
							</th>
						</tr>

						<tr class="title_tbl">
							<?php
							echo '<th style="width:35%">Test Performed</th>';
							echo '<th class="center resultcol" style="width:20%">Result ' . (!$remove_crossed ? ' <sup>4</sup>' : '') . '</th>';

							if (!$groupdata['is_covid'] && !$hide_group_Cycle_Threshold) {
								if (tr_get_option('ci_result_settings_pcr', 'enable_rs_ct_amp_cq') || (isset($groupdata['other_options']['is_rpp']) && $groupdata['other_options']['is_rpp'])) {
									echo '<th>Ct</th>';
									echo '<th>Amp Score</th>';
									echo '<th>Cq Confidence</th>';
								} else {
									echo '<th class="center" style="width:20%">Cycle Threshold*</th>';
								}
							}

							if ($show_units_column) {
								echo '<th>Units</th>';
							}

							if ($show_value_in_result) {
								echo '<th>Flag</th>';
							}

							if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
								echo '<th>Reference Range</th>';
							} else if ($show_group_range || ($groupdata['is_covid'] && tr_get_option('ci_pdf_data', 'pcrcovid_rangecl'))) {
								echo '<th>Reference Range</th>';
							}
							if (!$groupdata['comment_next_row'] && !$hidecommentscol) {
								echo '<th class="center">Comments</th>';
							}

							?>
						</tr>
					</thead>
		<?php
			}

			echo '<tbody>';
			echo $drug_content;
			echo '</tbody>';

			echo '</table>';
		}
		echo '</div>';
	}

	echo $subgroup_html;

	if ((!empty($drug_content) || $has_subgroup) && !empty($groupdata['comments'])) {
		$groupdata['comments'] = str_replace('[report_date]', date('m/d/Y h:iA', strtotime($report['approve_date'])), $groupdata['comments']);
		$can_show_group_comments = true;
		if (stripos($groupdata['comments'], '<enddate') !== false) {
			$dateend = explode('<enddate', $groupdata['comments'], 2);
			if (count($dateend) == 2) {
				$dateend = explode('>', $dateend[1]);
				$dateend = trim($dateend[0]);
				if (strtotime($dateend) > 1000 && strtotime($dateend) < strtotime($report['approve_date'])) {
					$can_show_group_comments = false;
				}
			}
		}
		if ($can_show_group_comments) {
			echo '<div class="group_comments" style="padding-top:5px;font-size:10px">' . trpdd_filter_comments_in_pdf($groupdata['comments']) . '</div>';
		}
	}

	if (isset($groupdata['other_options']['breakpage']) && $groupdata['other_options']['breakpage'] && ($has_main_tests || $has_subgroup)) {
		$need_break_page = true;
	}

	//end group
}




if (!empty($pdf_file_adding)) {
	$report['pdf2file'] = $pdf_file_adding;
}
if (isset($_GET['debug_note'])) {
	var_dump($pdf_file_adding);
}

if (!empty($pdf_notes)) {
	echo '<div class="bottom_notes pdf_notes">';
	echo trpdd_filter_comments_in_pdf($pdf_notes);
	echo '</div>';
	$pdf_notes = '';
}



if (tr_get_option('ci_result_settings_pcr', 'cli_pha_guid') && !empty($report['clinical_pharmacy_guidance'])) {
		?>
		<div style="page-break-inside:avoid;width:100%;margin-bottom:10px;margin-top:10px">
			<table class="border tdborder smallheader is_smalltbl">
				<tr class="header_tbl">
					<th class="center">
						<?php echo tr_get_option('ci_pdf_data', 'cliphagui_comment') ? 'Comments' : 'Clinical Pharmacy Guidance' ?>
					</th>
				</tr>

				<tr>
					<td>
						<div class="clinical_pharmacy_guidance">
							<?php echo $report['clinical_pharmacy_guidance']; ?>
						</div>
					</td>
				</tr>
			</table>
		</div>
	<?php
}
