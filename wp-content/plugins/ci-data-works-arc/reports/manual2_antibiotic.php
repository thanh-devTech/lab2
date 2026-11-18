<?php


$has_report 	= true;


$anti_data 		= array();
$anti_all 		= array();
$anti_groups 	= array();
$bacterias 		= array();
$bacterias_fungi 		= array();
$markers 		= array();
$count_mycoplasma = 0;
$group_tt 		= 'Real-Time PCR Urinary Tract Infection Results';
$groupparentdata = array();
$report['group_ids'] = array();

foreach ($drug_groups as $groupid => $groupdata) {



	$has_pos = false;
	$is_antibiotic_resistance_group = $groupdata['antibiotic'] || stripos($groupdata['name'], 'Antibiotic Resistance') !== false;
	$group_types = array();
	if (isset($groupdata['types']) && is_array($groupdata['types']) && count($groupdata['types']) > 0) {
		foreach ($checked_types as $checked_type) {
			if (isset($groupdata['types'][$checked_type])) {
				$group_types[] = $groupdata['types'][$checked_type];
			}
		}
	}
	$groupdata['sample_type'] = implode(', ', $group_types);

	$te_IDs = isset($groupdata['te_IDs']) && is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();

	if ($groupdata['name'] == 'Other') {
		foreach ($all_res as $te_ID => $arr) {
			if (!isset($arr['showed'])) {
				$te_IDs[] = $te_ID;
			}
		}
	} else {
		if ($is_antibiotic_resistance_group) {
			foreach ($all_res as $te_ID => $arr) {
				if (isset($arr['antibiotic']) && $arr['antibiotic']) {
					$te_IDs[] = $te_ID;
				}
			}
		}
	}

	if (isset($groupdata['other_options']['mantibiotic']) && $groupdata['other_options']['mantibiotic']) {
		$has_manual2_antibiotic = true;
		$report['add_style'] = '';
		if ($groupdata['parent'] > 0) {
			$has_manual2_antibiotic = $groupdata['parent'];
		} else {
			$has_manual2_antibiotic = $groupid;
		}
	}

	$groupdata['tests'] = array();


	foreach ($te_IDs as $te_ID) {
		$arr = isset($all_res[$te_ID]) ? $all_res[$te_ID] : array();
		if (!empty($arr)) {
			$arr['showed'] = true;
			$arr['type_of_specimen'] = $report['type_of_specimen'];
			$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);
			$all_res[$te_ID] = $arr;

			if (isset($results_bygroup[$groupdata['id']][$arr['drug']])) {
				$arr = $results_bygroup[$groupdata['id']][$arr['drug']];
				$arr['type_of_specimen'] = $report['type_of_specimen'];
				$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);
			}

			if (!$is_antibiotic_resistance_group && isset($arr['antibiotic']) && $arr['antibiotic']) {
				continue;
			}

			if (empty($arr['te_abrev'])) {
				$arr['te_abrev'] = $arr['te_name'];
			}

			$groupdata['tests'][$arr['drug']] = $arr;
			if (isset($_GET['debug32'])) {
				var_dump($arr['pos-neg']);
			}

			if (in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVH', 'PVL'))) {
				$has_pos = true;
				if ($is_antibiotic_resistance_group) {
					$marker = $arr;

					$markers[$arr['te_abrev']] = $arr;
				} else if (
					stripos($groupdata['name'], 'FUNGI') !== false
					|| stripos($groupdata['name'], 'fungal') !== false
				) {
					$bacterias_fungi[$arr['te_abrev']] = $arr;
				} else if (
					stripos($groupdata['name'], 'Gram Negative') !== false
					|| stripos($groupdata['name'], 'Gram Positive') !== false
					|| stripos($groupdata['name'], 'No Gram') !== false
					|| stripos($groupdata['name'], 'Pathogens') !== false
					|| stripos($groupdata['name'], 'Bacteria') !== false
					|| stripos($groupdata['name'], 'PARASITE') !== false
				) {
					$bacterias[$arr['te_abrev']] = $arr;
				}
			}
		}
	}


	$drug_groups[$groupid] = $groupdata;

	$report['group_ids'][] = $groupid;


	if ($groupdata['parent'] == 0) {
		$groupparentdata = $groupdata;

		$group_tt = $groupdata['name'];
		if (stripos($groupdata['name'], 'panel') === false) {
			$group_tt = $groupdata['name'] . ' Results';
		}
		if (stripos($groupdata['name'], 'antigen') !== false && count($groupdata['tests']) < 2) {
			$group_tt = $groupdata['name'];
		} else if (stripos($groupdata['name'], ', pcr') > 0) {
			$group_tt = $groupdata['name'];
		}
	}
}

$preliminary_final = '';
if ($report['final_results'] == 2) {
	$preliminary_final = 'Preliminary';
} else if ($report['final_results'] == 1) {
	$preliminary_final = 'Final';
}


$rotate_margin_left = -5;
$rotate_margin_top = 150;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
	$rotate_margin_top = 80;
	$rotate_margin_left = 5;
}
?>


<style>
	.tbl3icons ul li {
		font-size: 9px;
	}

	span.antivlbox {
		padding-right: 15px;
		font-size: 9px;
	}

	span.antivlbox.lastbox {
		padding-right: 0;
	}

	span.antivl {
		display: inline-block;
		width: 16px;
		height: 16px;
		line-height: 13px;
		text-align: center;
		font-size: 11px;
	}

	.antibiotic_tbl td.vl_R,
	.antibiotic_tbl td.vlmix_X,
	span.vl_R {
		background: #ff3d35 !important;
		color: #fff !important;
	}

	.antibiotic_tbl td.vl_S,
	span.vl_S {
		background: green !important;
		color: #fff !important;
	}

	.antibiotic_tbl td.vl_I,
	.antibiotic_tbl td.vlmix_V,
	span.vl_I {
		background: yellow !important;
		color: #000 !important;
	}

	.antibiotic_tbl td.vl_NG,
	span.vl_NG {
		background: pink !important;
		color: #000 !important;
	}

	.antibiotic_tbl td.vl_H,
	span.vl_H,
	.antibiotic_tbl td.vl_P,
	span.vl_P {
		background: grey !important;
		color: #fff !important;
	}

	body table th.rotate {
		text-align: left !important;
		position: relative;
		height: 140px;
		vertical-align: bottom;
		padding: 0;
	}

	body table th.rotate.labrsrotate {
		height: auto;
	}

	.rotate span {
		display: none;
	}

	body .rotate:after {
		left: 50%;
		font-weight: normal;
		white-space: nowrap;
		font-size: 10px;
		margin-left: <?php echo $rotate_margin_left ?>px;
		margin-top: <?php echo $rotate_margin_top ?>px;
		content: attr(title);
		position: absolute;
		-moz-transform: translateX(-50%) translateY(-50%) rotate(-90deg);
		-webkit-transform: translateX(-50%) translateY(-50%) rotate(-90deg);
		transform: translateX(-50%) translateY(-50%) rotate(-90deg);
	}

	body .rotate.labrsrotate:after {
		margin-top: 185px;
	}

	.antibiotic_tbl td.valuec {
		font-size: 9px;
		vertical-align: middle;
		height: 16px;
		line-height: 16px;
		padding: 0;
		letter-spacing: -2px;
	}

	.line_bottom td,
	.line_bottom th {
		border-bottom: 1px solid #ddd;
	}

	th.tbottom {
		vertical-align: bottom;
	}

	.final_comment {
		padding: 10px;
		background: #F8F8FF;
		font-size: 9px;
	}

	.final_comment_line {
		margin-bottom: 5px;
	}

	.final_comment p {
		margin: 0;
	}

	body .rotate:after {
		bottom: 0;
		transform-origin: left bottom;
		transform: rotate(-75deg);
	}
</style>

<table class="noboder small_table">
	<tr class="headernoborder">
		<th style="width:20%;border:none"></th>
		<th colspan="4" class="center " style="border:none;font-size:14px;">
			<?php echo htmlspecialchars($group_tt) ?>
		</th>
		<th style="width:20%;border:none">
			&nbsp;
		</th>
	</tr>

</table>

<table class="noboder small_table">
	<tr class="header_tbl">
		<th colspan=" 2">TEST REPORTS</th>
	</tr>
	<tr>
		<th class=" title_tbl" style="height:20px">Culture <?php echo $preliminary_final; ?> Results</th>
		<th class=" title_tbl" style="height:20px">Gram Stain Result</th>
	</tr>
	<tr class="headernoborder">
		<td style="width:50%">
			<?php
			$quantitation = $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_quantitation'");
			if (!empty($quantitation)) {
				echo '<div style="font-size:10px"><b>Quantitation</b>: ' . $quantitation . '</div>';
			}
			echo $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_pqog_cmm'");
			?>
		</td>
		<td style="width:50%">
			<ul style="color: #E8642B;font-size:10px;;padding-left:10px;margin:0">
				<?php
				$gram_stain = $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_gram_stains'");
				$gram_stain_other = $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_gram_other'");
				$gram_stain = explode('|', $gram_stain);
				foreach ($gram_stain as $gs) {
					if (!empty($gs)) {
						echo '<li>' . $gs . '.</li>';
					}
				}
				if (!empty($gram_stain_other)) {
					$gram_stain_other = explode("\n", $gram_stain_other);
					foreach ($gram_stain_other as $gs) {
						if (!empty($gs)) {
							echo '<li>' . $gs . '.</li>';
						}
					}
				}
				?>
			</ul>
		</td>
	</tr>
</table>
<br>
<table class="noboder small_table">
	<tr class="header_tbl">
		<th colspan="2">Molecular/PCR Confirmation Results</th>
	</tr>
	<tr>
		<th class=" title_tbl" style="height:20px;width:50%">Detected Pathogens</th>
		<th class=" title_tbl" style="height:20px;width:50%">Detected Resistance Markers</th>
	</tr>
	<tr class="headernoborder">
		<td style="width:50%">
			<ul style="color: #E8642B;font-size:10px;;padding-left:10px;margin:0">
				<?php

				foreach ($bacterias as $arr) {
					echo '<li>' . $arr['te_name'] . '</li>';
				}
				foreach ($bacterias_fungi as $arr) {
					echo '<li>' . $arr['te_name'] . '</li>';
				}
				if (empty($bacterias) && empty($bacterias_fungi)) {
					echo 'No Target Detected on PCR';
				}
				?>
			</ul>
		</td>
		<td style="width:50%">
			<?php
			if (!empty($markers)) {

				echo '<ul style="color: #E8642B;font-size:10px;;padding-left:10px;margin:0">';
				foreach ($markers as $item) {
					echo '<li>' . $item['te_name'] . '</li>';
				}
				echo '</ul>';
			} else {
				echo '<div>No Resistance Detected on PCR</div>';
			}
			?>
		</td>
	</tr>
</table>


<?php
$repdb = $wpdb->get_row("select show3pathogen_note from ci_reports_pcr where id = {$report['id']}");
$show3pathogen_note = false;
if ($repdb) {
	$show3pathogen_note = $repdb->show3pathogen_note;
}

if ($show3pathogen_note) {
	echo '<br><table class="tdborder small_table antibiotic_tbl">';
	echo '<tr class=" header_tbl autoh"><th >Culture and Antibiotic Susceptibility Results</th></tr>';
	if ($show3pathogen_note) {
		echo '<tr><td style="padding:10px">Mixed Culture - 3 or more organisms were detected. Probable contamination of urine specimen.</td></tr>';
	}

	echo '</table>';

	$need_break_summary_page = true;
	$has_report = true;
} else {

	$anti_data = array();
	$anti_all = array();
	$anti_icons = array();

	$where_child = array(" ta_groups = '' ", " ta_groups is null ", " find_in_set({$has_manual2_antibiotic}, ta_groups) ");
	foreach ($report['group_ids'] as $gid) {
		if ($gid == $has_manual2_antibiotic || $gid == 0) continue;
		$where_child[] = 'find_in_set(' . intval($gid) . ', ta_groups)';
	}

	$where_anti = " and (" . implode(' or ', $where_child) . ")";

	$anti_rs = $wpdb->get_results("select ra.*,
	                            ta.ta_name,
								ta.ta_group,
	                            te.te_name,
								te.te_abrev,
								IF(te.te_type = 'pats', te.te_groups_pathogen, te.te_group_id) as te_group_id,
								te.te_type
	                    from ci_results_antibiotics ra
	                     join ci_test_panels te ON te.te_ID = ra.te_ID
	                     join ci_test_antibiotics ta ON ta.ID = ra.ta_ID
	                    where ra.report_id = {$id} 
	                        and ra.btype = 'pcr'
							{$where_anti}
	                        order by ta_name asc , te_name asc");

	if (isset($_GET['debug1'])) {
		var_dump($wpdb->last_query);
	}

	$labresults = $wpdb->get_results("select ra.*,te.te_name
                                            from ci_results_labresults ra
                                            join ci_test_panels te ON te.te_ID = ra.te_ID
                                            where report_id = {$id}
                                                order by te_name asc ");






	foreach ($anti_rs as $r) {
		if (!isset($anti_data[$r->te_name])) {
			$anti_data[$r->te_name] = array(
				'labresult' => '',
				'rs' => array(),
			);
		}
		$anti_data[$r->te_name]['rs'][$r->ta_name] = $r->result;

		$anti_all[$r->ta_name] = $r->ta_name;
		$anti_icons[$r->ta_name] = $r->ta_group;
	}

	if (isset($_GET['abc'])) {
		var_dump($anti_data);
	}


	foreach ($labresults as $r) {
		$anti_data[$r->te_name]['labresult'] = $r->result;
	}

	if ($anti_data) {
		$sort_order = array('S', 'I', 'R');
		$row_counts = array();
		foreach ($anti_data as $tename => $arr) {
			$row_counts[$tename] = array('S' => 0, 'I' => 0, 'R' => 0);
			foreach ($arr['rs'] as $result) {
				if (isset($row_counts[$tename][$result])) {
					$row_counts[$tename][$result]++;
				}
			}
		}

		$col_counts = array();
		foreach ($anti_all as $tname) {
			$col_counts[$tname] = array('S' => 0, 'I' => 0, 'R' => 0);
			foreach ($anti_data as $tename => $arr) {
				$result = isset($arr['rs'][$tname]) ? $arr['rs'][$tname] : ' ';
				if (isset($col_counts[$tname][$result])) {
					$col_counts[$tname][$result]++;
				}
			}
		}

		uksort($anti_data, function ($left, $right) use ($row_counts, $sort_order) {
			foreach ($sort_order as $code) {
				$diff = ($row_counts[$right][$code] ?? 0) <=> ($row_counts[$left][$code] ?? 0);
				if ($diff !== 0) {
					return $diff;
				}
			}

			return strcasecmp($left, $right);
		});

		uksort($anti_all, function ($left, $right) use ($col_counts, $sort_order) {
			foreach ($sort_order as $code) {
				$diff = ($col_counts[$right][$code] ?? 0) <=> ($col_counts[$left][$code] ?? 0);
				if ($diff !== 0) {
					return $diff;
				}
			}

			return strcasecmp($left, $right);
		});
	}


	if ($anti_data) {
		//limit $anti_all to 15
		$anti_all = array_slice($anti_all, 0, 16);

		$count_anti    = count($anti_all);

		echo '<br><table style="border:0"><tr><td style="border:0;padding:0;page-break-inside:avoid">';
		echo '<table class=" small_table antibiotic_tbl">';
		echo '<tr class="header_tbl autoh">';
		echo '<th colspan="' . ($count_anti + 2) . '" class="upper">Antibiotic Susceptibility Pattern Summary</th></tr>';
		echo '<tr>';
		echo '<th colspan="' . ($count_anti + 2) . '" class="center title_tbl" style="height:20px">Antibiotics</th></tr>';

		echo '<tr>';
		echo '<th></th>';
		echo '<th></th>';
		foreach ($anti_all as $tname) {
			echo '<th class="rotate " title="' . $tname . '"><span>' . $tname . '</span></th>';
		}
		echo '</tr>';

		echo '<tr class="line_bottom">';
		echo '<th class=" tbottom">Pathogens</th>';
		echo '<th class="center tbottom">QTY</th>';
		$icon_map = array(
			'PO' => TRCPDD_PATH . '/reports/abx/pill.png',
			'IM' => TRCPDD_PATH . '/reports/abx/syringe.png',
			'IV' => TRCPDD_PATH . '/reports/abx/IV.png',
		);
		foreach ($anti_all as $tname) {
			$anti_icon = isset($anti_icons[$tname])  ? $anti_icons[$tname] : '';
			if (!empty($anti_icon) && isset($icon_map[$anti_icon])) {

				$anti_icon = '<img src="' . $icon_map[$anti_icon] . '" style="width:15px"/>';
			}
			echo '<th class="center " title="' . $tname . '">' . $anti_icon . '</th>';
		}
		echo '</tr>';


		foreach ($anti_data as $tename => $arr) {
			echo '<tr class="line_bottom">';
			if ($tename == 'MIC') {
				echo '<td colspan=2 class="right">' . $tename . '</td>';
			} else {
				echo '<td>' . $tename . '</td>';
				echo '<td class="center">' . htmlentities($arr['labresult']) . '</td>';
			}

			foreach ($anti_all as $tname) {
				$vl = isset($arr['rs'][$tname]) ? $arr['rs'][$tname] : ' ';
				echo '<td class="center vl_' . $vl . '">' . htmlentities($vl) . '</td>';
			}
			echo '</tr>';
		}



		echo '</table>';
		echo '</td></tr></table>';

		echo '<table style="border:none;width:100%;margin:5px auto"><tr>';
		echo '<td style="font-size:9px">';
		echo '<span class="antivlbox"><span class="antivl vl_S">S</span> - Sensitive</span>';
		echo '<span class="antivlbox"><span class="antivl vl_R">R</span> - Resistant</span>';
		echo '<span class="antivlbox "><span class="antivl vl_I">I</span> - Intermediate</span>';

		echo '</td>';
		echo '<td style="font-size:9px;text-align:right">';
		echo '<span class="antivlbox"><img src="' . TRCPDD_PATH . '/reports/abx/pill.png" style="width:15px"/> - by mouth</span>';
		echo '<span class="antivlbox"><img src="' . TRCPDD_PATH . '/reports/abx/syringe.png" style="width:15px"/> - Intramuscular</span>';
		echo '<span class="antivlbox lastbox"><img src="' . TRCPDD_PATH . '/reports/abx/IV.png" style="width:15px"/> - Intravenous</span>';
		echo '</td>';
		echo '</tr></table>';


		$report['final_results'] = intval($report['final_results']);

		$comment_preliminary1 = $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_preliminary1'");
		$comment_preliminary2 = $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_preliminary2'");
		$comment_final = $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_final'");

		$count_pre = $wpdb->get_var("select count(*) from ci_approve_logs where report_id = {$id} and btype='pcr' and final_status='2'");

		if ($report['final_results'] === 1 && !empty($comment_final)) {
			$final_date = $wpdb->get_var("select created_at from ci_approve_logs where report_id = {$id} and btype='pcr' and final_status='1' order by id desc limit 1");
			$final_date = !empty($final_date) ? date('m/d/Y', strtotime($final_date)) : '';
			echo '<div style="height:10px"></div>';
			echo '<table class="tdborder small_table ">';
			echo '<tr class="header_tbl autoh"><th class="upper">Final Report (' . $final_date . ')</th></tr>';
			echo '</table>';
			echo '<div class="final_comment">';
			echo '<div class="final_comment_line">' . nl2br($comment_final) . '</div>';
			echo '</div>';
		}

		if ($count_pre > 0 && !empty($comment_preliminary1)) {
			$pre1_date = $wpdb->get_var("select created_at from ci_approve_logs where report_id = {$id} and btype='pcr' and final_status='2' order by id asc limit 1");
			$pre2_date = $wpdb->get_var("select created_at from ci_approve_logs where report_id = {$id} and btype='pcr' and final_status='2' order by id desc limit 1");

			$pre1_date = !empty($pre1_date) ? date('m/d/Y', strtotime($pre1_date)) : '';
			$pre2_date = !empty($pre2_date) ? date('m/d/Y', strtotime($pre2_date)) : '';

			echo '<div style="height:10px"></div>';
			echo '<table class="tdborder small_table ">';
			echo '<tr class="header_tbl autoh"><th class="upper">CULTURE CONFIRMATION  PRELIMINARY RESULTS </th></tr>';
			echo '</table>';
			echo '<div class="final_comment"><table>';
			if ($count_pre > 1 && !empty($comment_preliminary2)) {
				echo '<tr><td><b>Preliminary Report 2 (' . $pre2_date . '):</b></td><td>' . nl2br($comment_preliminary2) . '</td></tr>';
			}
			echo '<tr><td style="width: 200px;"><b>Preliminary Report 1 (' . $pre1_date . '):</b></td><td>' . nl2br($comment_preliminary1) . '</td></tr>';

			echo '</table></div>';
		}





		$need_break_summary_page = true;
		$has_report = true;
	}
}
