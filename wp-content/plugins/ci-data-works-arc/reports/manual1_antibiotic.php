<?php
$has_manual2_antibiotic = 0;
$rotate_margin_left = -14;
$rotate_margin_top = 130;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
	$rotate_margin_top = 80;
	$rotate_margin_left = -2;
}


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
		if (isset($groupdata['other_options']['panel_pdf_title']) && !empty($groupdata['other_options']['panel_pdf_title'])) {
			$group_tt = $groupdata['other_options']['panel_pdf_title'];
		}

		if (count($groupdata['subgroups']) == 1) {
			$group_tt = $subname_tt;
		}
	}
}






$where_anti = "";

if ($has_manual2_antibiotic > 0) {
	$where_anti .= " and  (
		find_in_set({$has_manual2_antibiotic}, ta_groups) 
		or ta_groups = ''
		or ta_groups is null
		) ";

	$where_anti .= " and (ta_start_date='' or ta_start_date <= '{$report['created_date']}' )
					and (ta_end_date = '' or ta_end_date > '{$report['created_date']}' ) ";
}
$anti_rs = $wpdb->get_results("select an.ID,
an.ta_name,
an.ta_abrev,
an.ta_group,
an.ta_groups,
ra.result
from ci_test_antibiotics an
left join ci_results_antibiotics ra ON ra.ta_ID = an.ID
and ra.report_id = {$id} and ra.btype = 'pcr'
and ra.te_ID = -2
where ra.result !='' and ra.ta_ID is not null
{$where_anti}
order by ta_group desc, iorder asc");


if (isset($_GET['debug1'])) {
	var_dump($where_anti);
	var_dump($anti_rs);
}


foreach ($anti_rs as $r) {
	if (!isset($anti_data[$r->te_name])) {
		$anti_data[$r->te_name] = array(
			'labresult' => '',
			'rs' => array(),
		);
	}
	$anti_data[$r->te_name]['rs'][$r->ta_name] = $r->result;
	if (empty($r->ta_abrev)) {
		$r->ta_abrev = $r->ta_name;
	}
	$anti_all[$r->ta_abrev] = $r->ta_name;

	$r->groupkey = strtoupper(trim($r->ta_group));
	if (empty($r->groupkey)) {
		$r->groupkey = 'other';
	}
	if (!isset($anti_groups[$r->groupkey])) {
		$anti_groups[$r->groupkey] = array(
			'name' => $r->ta_group,
			'tests' => array()
		);
	}
	$anti_groups[$r->groupkey]['tests'][$r->ta_name] = $r->ta_name;
}

$pathogens_detected_text = 'PATHOGENS DETECTED';
if ($groupparentdata && $groupparentdata['name'] == 'Nail') {
	$pathogens_detected_text = 'FUNGAL PATHOGEN DETECTED';
	$bacterias = array();
}

if (isset($_GET['de'])) {
	var_dump($groupparentdata);
}

if (!$anti_data) {
	return;
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

<table style="width:99%;margin-left:10px;margin-top:6px" class="tbl3icons">

	<tr>
		<td style="width:31%">
			<div style="text-align: center;padding-bottom:1px">
				<img src="<?php echo TRCPDD_PATH . 'images/bacteria.png' ?>" style="width:60px;" />
			</div>
		</td>
		<td style="width:31%">
			<div style="text-align: center;padding-bottom:1px">
				<img src="<?php echo TRCPDD_PATH . 'images/markers.png' ?>" style="width:60px;" />
			</div>
		</td>
		<td>
			<div style="text-align: center;padding-bottom:1px">
				<img src="<?php echo TRCPDD_PATH . 'images/antibiotics2.png' ?>" style="width:60px;" />
			</div>
		</td>
	</tr>
	<tr>
		<td style="padding-left:10px;">
			<div>
				<div style="padding-bottom:4px;font-size:10px" class="center"><?php echo $pathogens_detected_text ?></div>
				<ul style="color: #E8642B;font-size:10px">
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
			</div>
		</td>
		<td style="border-left:5px solid #ddd;padding-left:10px">
			<div>
				<div style="padding-bottom:4px;font-size:10px;text-transform:uppercase" class="center">Resistance Gene Detection</div>

				<?php
				if (!empty($markers)) {

					echo '<ul style="color: #E8642B;font-size:10px">';
					foreach ($markers as $item) {
						echo '<li>' . $item['te_name'] . '</li>';
					}
					echo '</ul>';
				} else {
					echo '<div>No Resistance Detected on PCR</div>';
				}
				?>
			</div>
		</td>
		<td style="border-left:4px solid #ddd;padding-left:10px">
			<div>
				<div style="padding-bottom:4px;font-size:10px" class="center">QUANTIFICATION OF GROWTH</div>
				<?php
				$qog = $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_cmm'");
				echo '<div style="font-size:9px">';
				echo nl2br($qog);
				echo '</div>';
				?>
			</div>
		</td>
	</tr>
</table>
<br>


<?php


if ($anti_data) {
	$count_anti    = count($anti_all);
	$col_width 	= (100 / $count_anti) . '%';
	echo '<table style="border:0;"><tr><td style="border:0;padding:0;page-break-inside:avoid">';
	echo '<table class="tdborder small_table antibiotic_tbl">';
	echo '<tr class="header_tbl autoh">';
	echo '<th colspan="' . ($count_anti) . '" class="upper">Antibiotic Susceptibility Pattern Summary</th></tr>';
	echo '<tr>';
	if ($count_anti > 0) {
		echo '<th colspan="' . $count_anti . '" class="center title_tbl" style="height:18px;background:#F8F8FF">Antibiotics</th></tr>';
	}

	//group here
	if (count($anti_groups) > 1) {
		echo '<tr>';
		foreach ($anti_groups as $g => $arr) {
			$colspan = count($arr['tests']);
			echo '<th class="center" colspan="' . $colspan . '" style="font-size:10px">';
			echo $arr['name'];
			echo '</th>';
		}
		echo '</tr>';
	}

	echo '<tr>';
	foreach ($anti_all as $abrev => $tname) {

		echo '<th class="rotate " title="' . $tname . '" style="width:' . $col_width . '"><span>' . $tname . '</span></th>';
	}
	echo '</tr>';
	foreach ($anti_data as $tename => $arr) {
		echo '<tr>';
		foreach ($anti_all as $abrev => $tname) {
			$vl = isset($arr['rs'][$tname]) ? $arr['rs'][$tname] : ' &nbsp;';
			echo '<td class="center valuec vl_' . $vl . '">' . ($vl) . '</td>';
		}
		echo '</tr>';
	}



	echo '</table>';
	echo '</td></tr></table>';

	echo '<table style="border:none;width:100%;margin:5px auto"><tr>';
	echo '<td style="font-size:9px">';
	echo '<span class="antivlbox"><span class="antivl vl_S">S</span> - Sensitive</span>';
	echo '<span class="antivlbox"><span class="antivl vl_R">R</span> - Resistant</span>';
	echo '<span class="antivlbox" style="width:170px"><span class="antivl vl_P">P</span> - Partial Susceptibility</span>';
	echo '<span class="antivlbox "><span class="antivl vl_I">I</span> - Intermediate</span>';
	echo '<span class="antivlbox lastbox"><span class="antivl vl_NG">NG</span> - No growth</span>';
	echo '</td>';
	if (count($anti_groups) > 1) {
		echo '<td style="font-size:9px;text-align:right">';
		echo '<span class="antivlbox">PO - by mouth</span>';
		echo '<span class="antivlbox">IM - Intramuscular</span>';
		echo '<span class="antivlbox lastbox">IV - Intravenous</span>';
		echo '</td>';
	}
	echo '</tr></table>';


	$comment = $wpdb->get_var("select comments from ci_results_pcr where report_id = {$id} and drug='antibiotic_comments'");
	if (!empty($comment)) {
		echo '<div style="height:10px"></div>';
		echo '<table class="tdborder small_table ">';
		echo '<tr class="header_tbl autoh"><th class="upper">Result Comments</th></tr>';
		echo '</table>';
		echo '<div style="padding:10px;background:#F8F8FF;font-size:9px">' . nl2br($comment) . '</div>';
	}

	$need_break_summary_page = true;

	if (isset($notes['utinewtable'])) {
		echo '<div style="padding-top:8px;">' . nl2br($notes['utinewtable']) . '</div>';
	}
}

?>