<?php

//disable old abx all labs after 2025-04-01
if (strtotime($report['doc']) >= strtotime('2025-04-01')) {
	return;
}


if ($id == 0) {
	$id = $report['id'];
}



$patient_adress = trpdd_format_address($report['pa_address'], $report['pa_city'], $report['pa_state'], $report['pa_zip']);
$clinic_address = trpdd_format_address($report['cli_street'], $report['cli_city'], $report['cli_state'], $report['cli_zip']);


//parse panels


$except_covid = false;
$all_tests = array();
$rs = $wpdb->get_results("select * from ci_test_panels where te_type='pcr' ");
foreach ($rs as $r) {
	$r->te_name = strtoupper($r->te_name);
	$all_tests[$r->te_name] = $r;
}


$in_panel_tests_requested = array();
$group_ids = array();

foreach (explode(',', $report['panel_tests_requested']) as $t) {
	if (stripos($t, 'group') === false) {
		if ($t > 0) {
			$in_panel_tests_requested[] = $t;
		}
	} else {
		$group_ids[] = str_ireplace('group_', '', $t);
	}
}
$test_ids = implode(',', $in_panel_tests_requested);
if (empty($test_ids)) {
	$test_ids = '0';
}
$group_ids_str = implode(',', $group_ids);
$where_group = '';
if (count($group_ids) > 0) {
	$where_group .= " or ((tg_only_group = 1 or tg_notestcheckbox=1) and tg_ID in ({$group_ids_str}) )";
	$where_group .= " or ((tg_only_group = 1 or tg_notestcheckbox=1) and tg_parent in ({$group_ids_str}) )";
}




$drugkeys = array('te_abrev2', 'te_abrev', 'te_name');
$all_res = isset($pre_all_res) ? $pre_all_res : array();

foreach ($drugkeys as $drugkey) {
	$sql = "SELECT *
FROM ci_results_pcr rs
join ci_test_panels tp ON tp.te_type='pcr' and tp.{$drugkey} = rs.drug and rs.drug!=''
WHERE report_id='" . $id . "'
and (tp.te_ID in ({$test_ids})
or tp.te_group_id in (select tg_ID from ci_test_groups where tg_aw_results = 1 {$where_group})
)
";

	$date_test = date('Y-m-d', strtotime($report['created']));

	$sql .= " and (te_start <= '{$date_test}' or te_start is null or te_start='' )";
	$sql .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";

	$all_results_q = $wpdb->get_results($sql, ARRAY_A);

	foreach ($all_results_q as $arr) {
		if (isset($all_res[$arr['te_ID']])) {
			continue;
		}
		$arr['drug'] = strtoupper($arr['te_name']);
		$all_res[$arr['te_ID']] = $arr;

		if (!in_array($arr['te_group_id'], $group_ids)) {
			$group_ids[] = $arr['te_group_id'];
		}
	}
}


$rsg = $wpdb->get_results(
	"select * from ci_results_pcr where report_id = {$id}
	and drug like '%|%'",
	ARRAY_A
);
$results_bygroup = array();
foreach ($rsg as $arr) {
	$arr['drug'] = strtoupper($arr['drug']);

	list($gid, $dr) = explode('|', $arr['drug']);
	$arr['drug'] = $dr;
	$results_bygroup[$gid][$dr] = $arr;
}


$custom_added_Antibiotic = false;

$checked_types = explode(',', strtolower($report['sample_type']));
$drug_groups = tr_pdd_get_drug_groups('pcr', $group_ids, true);

//find Antibiotic Resistance
$antibiotic_resitance_id = 0;
foreach ($drug_groups as $groupid => $groupdata) {
	if ($groupdata['name'] == 'Antibiotic Resistance' || $groupdata['antibiotic']) {
		$antibiotic_resitance_id = $groupid;
		break;
	}
}
if ($antibiotic_resitance_id == 0) {
	$drug_groups['Antibiotic Resistance'] = array('name' => 'Antibiotic Resistance', 'drugs' => array());
}

$all_groups = array();
$markers = array();
$markers_inc = array();
$bacterias = array();


foreach ($drug_groups as $groupid => $groupdata) {
	if (isset($groupdata['id'])) {
		$all_groups[$groupdata['id']] = $groupdata['name'];
		$drug_groups[$groupid]['subgroups'] = array();
	}
}

//get abx group key
foreach ($drug_groups as $groupid => $groupdata) {
	if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {
		$groupparentname = $all_groups[$groupdata['parent']];
		$drug_groups[$groupdata['parent']]['subgroups'][$groupid] = $groupdata['name'];

		if (stripos($groupparentname, 'Wound') !== false) {
			$abx_groupk = 'wound';
		} else if (stripos($groupparentname, 'GI') !== false || stripos($groupparentname, 'Gastrointestinal') !== false) {
			$abx_groupk = 'gi';
		}
	} else if ($groupdata['name'] == 'Wound ABR') {
		$abx_groupk = 'wound';
	}
}


$bacteria_tbl = trpdd_get_bacteria_table($abx_groupk);
$abx_tbl = trpdd_get_abx_table($abx_groupk);

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

			$groupdata['tests'][$arr['drug']] = $arr;

			if (in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVL', 'PVH'))) {
				$has_pos = true;
				if ($is_antibiotic_resistance_group) {
					$marker = $arr;
					preg_match("/\(([^\)]+)\)/", $marker['te_name'], $matches);
					if (!empty($matches[1])) {
						$marker['te_abrev'] = trim($matches[1]);
					} else {
						$marker['te_abrev'] = trim($marker['te_name']);
					}

					$markerkey = strtolower($marker['te_abrev']);

					if ($markerkey == 'rpph') {
						continue;
					}
					if (isset($_GET['aaa'])) {
						var_dump($marker);
						echo '<br>';
						var_dump($markerkey);
						echo '<br>';
						echo '<br>';
						echo '<br>';
					}
					$marker['data'] = $wpdb->get_row("select * from {$abx_tbl} where Marker = '{$markerkey}'", ARRAY_A);
					if (!$marker['data']) {
						if ($markerkey == 'van groups') {
							$markerkey = 'vana';
						} else if ($markerkey == 'vre vana') {
							$markerkey = 'vana';
						} else if ($markerkey == 'vre vanb') {
							$markerkey = 'vanb';
						} else if ($markerkey == 'ctx groups') {
							$markerkey = 'ctx-m group 1';
						}
						$marker['data'] = $wpdb->get_row("select * from {$abx_tbl} where Marker = '{$markerkey}'", ARRAY_A);
					}
					$markers[$markerkey] = $marker;
				} else if (
					stripos($groupdata['name'], 'Gram Negative') !== false
					|| stripos($groupdata['name'], 'Gram Positive') !== false
					|| stripos($groupdata['name'], 'No Gram Stain') !== false
				) {
					$bacterias[] = $arr;
				}
			} else if ($arr['pos-neg'] == 'INC' && $is_antibiotic_resistance_group) {
				$marker = $arr;
				preg_match("/\(([^\)]+)\)/", $marker['te_name'], $matches);
				if (!empty($matches[1])) {
					$marker['te_abrev'] = trim($matches[1]);
				} else {
					$marker['te_abrev'] = trim($marker['te_name']);
				}
				$markerkey = strtolower($arr['te_abrev']);
				$marker['data'] = $wpdb->get_row("select * from {$abx_tbl} where Marker = '{$markerkey}'", ARRAY_A);
				$markers_inc[$markerkey] = $marker;
			}
		}
	}


	if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {
		$groupparentname = $all_groups[$groupdata['parent']];
		$drug_groups[$groupdata['parent']]['subgroups'][$groupid] = $groupdata['name'];

		if (stripos($groupparentname, 'Wound') !== false) {
			$abx_groupk = 'wound';
		} else if (stripos($groupparentname, 'GI') !== false || stripos($groupparentname, 'Gastrointestinal') !== false) {
			$abx_groupk = 'gi';
		}
	}

	$drug_groups[$groupid] = $groupdata;

	if (count($groupdata['tests']) > 0 && !empty($groupdata['logo'])) {
		$group_logo = $groupdata['logo'];
	}

	if (!empty($groupdata['pdf_title'])) {
		$pdf_title = $groupdata['pdf_title'];
		$pdf_title = str_ireplace(array(
			': Molecular PCR Summary',
			'Molecular PCR Summary',
		), '', $pdf_title);
	}
}





$pos_neg = array(
	'' => '',
	'NEG' => 'Not Detected',
	'POS' => 'Detected',
	'POL' => 'Detected-Low',
	'POM' => $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium',
	'POH' => 'Detected-High',
	'PEN' => 'Pending',
	'INC' => 'Inconclusive',
	'PRP' => 'Positive',
	'INV' => 'Invalid',
	'NGE' => 'Negative',
	'IND' => 'Indeterminate',
	'EQU' => 'Equivocal',
);

$no_collection_date = (empty($report['collection_date']) || $report['collection_date'] == '0000-00-00' || $report['collection_date'] == '0000-00-00 00:00:00') && $report['doc'] == '0000-00-00';
$notes = get_option('ci_notes_result_pcr');

$text_PRELIMINARY = tr_pdd_get_result_status($report['final_results'], true);
$final_adding_font = '';
if (isset($result_settings['final_bold']) && $result_settings['final_bold']) {
	$final_adding_font .= 'font-weight:bold;text-transform: uppercase;';
}

if (isset($_REQUEST['debug']) && isset($_REQUEST['html'])) {
	var_export($report);
	echo '<br><br>';
	echo 'Tests:';
	var_export($in_panel_tests_requested);
	echo '<br><br>';
	echo '$all_res:';
	var_export($all_res);
	echo '<br><br>';
	echo '$drug_groups: ';
	var_dump($drug_groups);
	echo '<br><br>';
	echo '$bacteria_tbl: ';
	var_dump($bacteria_tbl);
	echo '<br><br>';
	echo '$markers_inc: ';
	var_dump($markers_inc);
	echo '<br><br>';
}



/* =========================== */
/* == Begin creating html == */
/* =========================== */


$footer_height = 3.5;
ob_start();
?>
<div id="footer">
	<div style="padding-bottom:4px">
		<?php
		$abx_disclamer = tr_get_option('ci_result_settings_pcr', 'abx_disclamer');
		if (!empty($abx_disclamer)) {
			echo $abx_disclamer;
		} else { ?>
			<b>DISCLAMER: </b>
			Any information contained in this report does not in any way constitute a recommendation for treatment. The report is an algorithmic calculation based only on the antibiotics of choice for the pathogens and resistance markers presently identified. It does not take into consideration:  other possible pathogens or antibiotic resistance not presently tested, patient’s age, allergies, underlying medical conditions, possible drug interactions or antibiotic stewardship guidelines. USE FOR EDUCATIONAL PURPOSE ONLY!
		<?php } ?>
	</div>
	<div class="footer">
		<table>
			<tr>
				<td style="width:90px;padding-top:0">
					<img src="<?php echo TRCPDD_PATH . 'images/barcodeph.jpg' ?>" style="width:70px" />
				</td>
				<td style="line-height: 15px;padding-top:8px">

					Drug of choice information provided by<br>
					GlobalRPH. Find out more at<br>
					https://globalrph.com/drug-of-choice/
				</td>
				<td></td>
				<td style="width:60px">
					<img src="<?php echo TRCPDD_PATH . 'images/slogo.png' ?>" style="width:44px" />
				</td>
				<td style="line-height: 14px;padding-top:8px">
					POWERED BY IgniteLIS<br>
					Copyright @2021<br>
					ABX Report Version 1.22
				</td>
			</tr>
		</table>
	</div>
</div>

<?php
$footer_html = ob_get_clean();


$margin_bottom = 0;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
	$margin_bottom = $footer_height;
	$footer_height = 0.2;
}
?>
<!DOCTYPE html>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta id="final" content="<?php echo $report['final_results'] ?>" />
	<title><?php echo $report['assen'] ?></title>
	<style>
		@page {
			margin: 0.5cm 0.5cm <?php echo $footer_height ?>cm 0.5cm;
		}

		body {
			margin-top: 4.4cm;
			margin-bottom: <?php echo $margin_bottom ?>cm;
		}

		break,
		.break {
			page-break-after: always;
			border: 0;
		}

		* {
			font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
			font-size: 9pt;
			line-height: 10px;
		}

		.font8 {
			font-size: 8pt;
		}

		table .sample_type {
			font-size: 7pt;
			text-transform: none;
			font-weight: normal;
		}

		hr {
			height: 1px;
			padding: 0;
			margin: 1em 0;
			border: 0;
			border-top: 1px solid #CCC;
		}

		table {
			width: 100%;
			border-spacing: 0px;
			page-break-inside: auto;
			border-collapse: collapse;
		}

		table th {
			text-align: left;
			padding: 5px;
		}

		table td {
			padding: 5px;
			vertical-align: top
		}

		table.nopadding td {
			padding: 0
		}

		table.border {
			border: 1px solid black;
			border-spacing: 0px;
			width: 100%;
		}

		table.tdborder td,
		table.tdborder th {
			border: 1px solid black;
		}

		table.border2.border.tdborder {
			border-top: 1px solid black;
			border-left: 1px solid black;
			border-right: 2px solid black;
			border-bottom: 2px solid #000
		}

		table.noborder td,
		table.noborder th {
			border: 0 !important
		}

		.table {
			margin-top: 0pt;
			margin-bottom: 0pt;
		}

		.tableCenter {
			text-align: center;
			margin-top: 0pt;
			margin-bottom: 0pt;
		}

		.header {
			margin-top: 10pt;
			margin-bottom: 10pt;
		}

		.center {
			text-align: center
		}

		.upper {
			text-transform: uppercase;
		}

		.in_medications {
			background: #92ccff
		}

		.bg_red {
			background: #ff9495
		}

		table tr.headernoborder th {
			border: 3px solid #000;
			border-left: 0;
			border-right: 0;
			font-weight: bold;
			font-size: 12px;
			padding-top: 4px;
			padding-bottom: 4px
		}

		table.noboder,
		table tr.noborder td {
			border: none;
		}

		table .font_blue td {
			color: blue
		}

		h4,
		.h4 {
			font-size: 12px;
			margin: 0;
			padding: 0;
			line-height: 12px;
			font-weight: bold
		}

		h5 {
			font-weight: normal;
			font-size: 12px;
			color: <?php echo $report['color'] ?>;
			margin: 10px 0 0 0
		}

		.right {
			text-align: right;
		}

		.right_pre {
			margin-top: -3px;
			color: red;
			text-align: right
		}

		.center_pre {
			text-align: center;
			color: red;
		}

		.blue {
			color: blue
		}

		.red,
		.rowred td {
			color: red
		}

		.small_table th,
		.small_table td {
			font-size: 8pt;
			padding: 2px;
		}

		.small_padding_table th,
		.small_padding_table td {
			padding: 1px 2px;
			color: #000;
		}

		.address_right div {
			font-size: 10px;
			line-height: 10px;
		}

		table tr th.header_black {
			background: #444;
			color: #fff;
			padding: 4px;
		}

		table tr th.header_none {
			border-top: none;
			border-right: none
		}

		table.valmiddle td {
			vertical-align: middle;
		}

		<?php if (!isset($_REQUEST['html'])): ?>#header,
		#footer {
			position: fixed;
			left: 0;
			right: 0;
			color: #666;
			font-size: 0.9em;
		}

		<?php endif; ?>#header {
			top: 0;
		}

		#footer {
			bottom: 5px;

		}

		.footer {

			border-top: 3px solid <?php echo $report['color'] ?>;
		}

		#footer,
		#footer * {
			font-size: 10px;
			line-height: 10px;
			margin: 0;
		}

		.header_tbl th {
			text-transform: uppercase;
			background: #555;
			text-align: center;
			height: 18px;
			color: #fff;
		}

		.title_tbl th {
			text-align: center;
			border-bottom: 1px solid #ddd;
			height: 18px;
			background: #ddd;
			color: #000;
			padding-top: 0;
			padding-bottom: 0;
		}

		.smalltext {
			font-size: 9px;
		}

		ul {
			padding-left: 10px;
			margin-bottom: 0;
			margin-top: 0;
		}
	</style>
</head>

<body>
	<div id="header">
		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:23%;height:60px;" valign="top">
					<?php
					$logo_path = $logo_dir . $report['lab_logo'];
					$logo_main = $report['lab_logo'];
					if (!empty($notes['pdf_logo'])) {
						$logo_main = $notes['pdf_logo'];
					}
					if (!empty($group_logo)) {
						$logo_main = $group_logo;
					}


					$logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

					$has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
					if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
						$logo_main = $logo_second;
						$logo_second = '';
					}

					if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
						$size = getimagesize($logo_dir . $logo_main);
						$max_width = 250;
						$max_height = ($size[0] > $size[1]) ? 50 : 60;
						if ($has_logo2) {
							// $max_height-=30;
							$max_width = 150;
						}
					?>
						<img border="0" style="max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_dir . $logo_main ?>" />
					<?php
					}

					?>

				</td>
				<td style="width:46%;text-align: center">
					<h2 style="color:<?php echo $report['color'] ?>;font-size:18px;line-height:16px;margin:0;font-weight: normal;text-align: center"><?php echo $pdf_title . ': Results Analysis' ?></h2>
					<?php if (tr_get_option('ci_pdf_data', 'centerfinalreport')) { ?>
						<div class="center_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php } ?>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">
					<?php if (!tr_get_option('ci_pdf_data', 'centerfinalreport')) { ?>
						<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php } ?>
					<div><?php echo $report['header_lab_name'] ?></div>
					<div><?php echo $report['header_lab_address'] ?></div>
					<?php
					if (!empty($report['header_lab_second_line_hpdf'])) {
						echo '<div>' . $report['header_lab_second_line_hpdf'] . '</div>';
					}
					if (!empty($report['header_lab_phone']) && !tr_get_option('ci_pdf_data', 'report_hilaphone')) {
						echo '<div>Phone: ' . $report['header_lab_phone'] . '</div>';
					}
					if (!empty($report['header_lab_clia'])) {
						echo '<div>CLIA ID: ' . $report['header_lab_clia'] . '</div>';
					}
					?>
				</td>
			</tr>
		</table>
		<div style="border-top:4px solid <?php echo $report['color'] ?>;padding-bottom:15px;background:#eee">
			<table class="small_padding_table">
				<tr>
					<td style="width:32%">
						<h5>CLINIC INFORMATION</h5>
					</td>
					<td style="width:37%">
						<h5>PATIENT INFORMATION</h5>
					</td>
					<td>
						<h5>SPECIMEN INFORMATION</h5>
					</td>
				</tr>
				<tr>
					<td>
						<table class="noboder small_table">
							<tr>
								<td style="width:23%">Name:</td>
								<td><?php echo $report['requesting_practice'] ?></td>
							</tr>
							<tr>
								<td>Provider:</td>
								<td><?php echo htmlspecialchars($report['physician']) ?></td>
							</tr>
							<?php
							if (tr_get_option('ci_pdf_data', 'pdf_show_npi') && !empty($report['doc_npi'])) {
							?>
								<tr>
									<td>NPI:</td>
									<td><?php echo htmlspecialchars($report['doc_npi']) ?></td>
								</tr>
							<?php
							}
							?>
						</table>
					</td>
					<td>
						<table class="noboder small_table">
							<tr>
								<td style="width:20%">Name:</td>
								<td colspan="3"><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
							</tr>
							<tr>
								<td>DOB:</td>
								<td><?php echo tr_pdd_display_date($report['patient_dob']) ?></td>
								<td>Gender:</td>
								<td><?php echo htmlspecialchars($report['pa_gender']) ?></td>
							</tr>
						</table>
					</td>
					<td>
						<table class="noboder small_table">
							<tr>
								<td style="width:50%">Accession Number:</td>
								<td><?php echo $report['assen'] ?></td>
							</tr>
							<tr>
								<td>Date Reported:</td>
								<td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<?php
	if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
		echo stripslashes($footer_html);
	}

	if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
		$rejected = tr_pdd_get_batch_statuses($report['batch_status'], false, false, 'pcr');

	?>
		<br>
		<br><br><br><br>
		<h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
		<p style="text-align: center;"><?php echo $report['billing_notes'] ?></p>
		<p style="text-align: center;"><?php echo $report['notes_report'] ?></p>
		<?php
		$has_report = true;
	} else {
		$Mycoplasma_tests = array('ureaplasma urealyticum', 'mycoplasma hominis');
		if (tr_get_option('ci_result_settings_pcr', 'abx_not_group_urea')) {
			$Mycoplasma_tests = array();
		}
		$has_report 		= true;
		$treatment 			= array();
		$count_bacterias 	= 0;
		$count_bacterias_pn = array(0 => 0, 1 => 0);
		$all_Resistant 		= array();
		$Mycoplasma_treatment = array();
		$count_mycoplasma 	= 0;
		ob_start();
		foreach ($bacterias as $arr) {
			$ba = $wpdb->get_row("select * from {$bacteria_tbl} where Name = '{$arr['te_name']}' ", ARRAY_A);
			if (!$ba) {
				$ba = $wpdb->get_row("select * from {$bacteria_tbl} where Name = '{$arr['te_abrev']}' ", ARRAY_A);
				if (!$ba) {
					$ba = $wpdb->get_row("select * from {$bacteria_tbl} where Name = '{$arr['te_abrev2']}' ", ARRAY_A);
				}
			}
			if (!$ba) $ba = array();
			foreach ($ba as $k => $vl) {
				$ba[strtolower($k)] = $vl;
			}
			$has_style = true;

			$is_Mycoplasma = in_array(strtolower($arr['te_name']), $Mycoplasma_tests);
			if ($is_Mycoplasma) {
				$count_mycoplasma++;
			} else {
				$count_bacterias++;
			}

			$list_right = array();
			$pos_neg = isset($ba['Gram']) && $ba['Gram'] ? 1 : 0;
			$current_markernames = array();
			$current_markernames_inc = array();
			$Resistant = array();
			$Resistant_inc = array();
			$count_bacterias_pn[$pos_neg]++;

			foreach ($markers as $markerkey => $marker) {

				if (isset($ba[$markerkey])) {
					$ba[$markerkey] = trim($ba[$markerkey], ".\n\r");
				}


				if (! empty($ba[$markerkey])) {

					$list = explode('.', $ba[$markerkey]);
					foreach ($list as $l) {
						$l = trim($l);
						$l = trim($l, "\n\r");
						if (empty($l)) continue;
						$list_right[] = $l;
					}
					$current_markernames[] = $marker['te_name'];
				}

				$Resistant_arr = explode(',', $marker['data']['Resistant']);
				foreach ($Resistant_arr as $t) {
					$t = trim($t);
					$t = preg_replace('/[[:^print:]]/', '', $t);
					$rkey = strtolower($t);
					if (empty($rkey)) continue;

					$all_Resistant[$rkey] = $t;
					if (! empty($ba[$markerkey])) {
						$Resistant[$rkey] = $t;
					}
				}
			}

			foreach ($markers_inc as $markerkey => $marker) {
				if (! empty($ba[$markerkey])) {
					$current_markernames_inc[] = '<span style="color:blue">' . $marker['te_abrev'] . ' - Marker Inconclusive</span>';

					$Resistant_arr = explode(',', $marker['data']['Resistant']);

					foreach ($Resistant_arr as $t) {
						$t = trim($t);
						$t = preg_replace('/[[:^print:]]/', '', $t);
						$rkey = strtolower($t);
						if (empty($rkey)) continue;

						if (!isset($Resistant[$rkey])) {
							$Resistant_inc[$rkey] = $t;
						}
					}
				}
			}

			if (count($current_markernames) > 1) {
				$temp = $list_right;
				$left = array();
				$list_right = array();
				$count_keys = array();
				$group_list = array();
				foreach ($temp as $items) {
					if (isset($_GET['old'])) {
						$items = array($items);
					} else {
						$items = trim($items, ",\n\r");
						$items = explode(',', $items);
					}

					if (count($items) > 1) {
						$group_list[] = $items;
					}
					foreach ($items as $item) {
						$item = trim($item);
						$rrk = preg_replace('/[[:^print:]]/', '', $item);
						$rrk = strtolower($rrk);

						if (empty($item)) {
							continue;
						}

						$list_right[$rrk] = $item;
						if (!isset($count_keys[$rrk])) {
							$count_keys[$rrk] = 0;
						}
						$count_keys[$rrk]++;
					}
				}

				foreach ($count_keys as $rrk => $count) {
					if ($count < count($current_markernames)) {
						unset($list_right[$rrk]);
					}
				}

				foreach ($group_list as $items) {
					$items_in = array();
					$ig_keys = array();
					foreach ($items as $item) {
						if (in_array($item, $list_right)) {
							$ig_key = array_search($item, $list_right);
							$items_in[$ig_key] = $item;

							//unset($list_right[$ig_key]);
						}
					}

					if (count($items_in) > 1) {
						$has_move_g = false;
						foreach ($items_in as $ig_key => $item) {
							if ($has_move_g) {
								unset($list_right[$ig_key]);
							} else {
								$has_move_g = true;
								$list_right[$ig_key] = implode(',', $items_in);
							}
						}
					}
				}
				if (isset($_GET['debut22']) && stripos($arr['te_name'], 'Enterobacter aerogenes') !== false) {
					var_dump($list_right);
					echo '<br><br>';
					var_dump($count_keys);
				}
			}

		?>
			<tr>
				<td style="border-bottom:5px solid #ddd">
					<ul>
						<li style="font-weight: bold"><?php echo isset($arr['te_name']) ? $arr['te_name'] : '' ?></li>
					</ul>
					<p style="color:#777;"><?php echo $ba['Description'] ?></p>
					<?php if (count($current_markernames) > 0 || count($current_markernames_inc) > 0) { ?>
						<div style="color:#E8642B;">
							Resistance Markers:
							<?php
							$tmp_list = array_merge($current_markernames, $current_markernames_inc);
							echo htmlspecialchars(implode(', ', $tmp_list));
							?>
						</div>
						<?php if (!empty($Resistant)) { ?>
							<div style="background:#BC380C;padding:5px;line-height:16px;color:#fff;margin:4px 0">
								<?php echo implode(', ', $Resistant); ?>
							</div>
						<?php } ?>

						<?php if (!empty($Resistant_inc)) { ?>
							<div style="background:blue;padding:5px;line-height:16px;color:#fff;margin:4px 0">
								<?php echo 'Possible Resistance to: ' . implode(', ', $Resistant_inc); ?>
							</div>
						<?php } ?>

					<?php } ?>
				</td>
				<td style="padding-left:10px;border-left:5px solid #ddd;border-bottom:5px solid #ddd">
					<ul>
						<?php
						if (empty($list_right)) {
							$ba['base antibiotics'] = trim($ba['base antibiotics'], ".\n\r");
							$list_right = explode('.', $ba['base antibiotics']);
						}
						if (! empty($list_right)) {
							$l_position = 0;

							$exist_key_g = array();
							foreach ($list_right as $l) {
								$l = trim($l);
								if (strlen($l) <= 3) continue;
								echo '<li>';
								echo $l;
								echo '</li>';


								//list for ALGORITHMIC ABX MATCHES:
								$arrs = explode(',', $l);

								$ii = 0;
								foreach ($arrs as $l2) {
									$l2 = trim($l2);
									$k = strtolower($l2);
									$k = str_replace(' ', '', $k);
									$k = preg_replace('/[[:^print:]]/', '', $k);
									$exist_key_g[$k] = $k;
									if (!empty($k)) {
										$ii++;
										if ($is_Mycoplasma) {
											if (!isset($Mycoplasma_treatment[$k])) {
												$Mycoplasma_treatment[$k] = array(
													'name' => $l2,
													'count' => 1,
													'is_group' => $ii <= 1,
												);
											} else {
												$Mycoplasma_treatment[$k]['count'] += 1;
											}
										} else {
											$parent_key = '';
											$keychilds = array();
											$keys = array($k => $k);
											if (strpos($k, '+') > 0) {
												$parent_key = $k;
												//$keys = array();
												foreach (explode('+', $k) as $k2) {
													$k2 = trim($k2);
													if (!empty($k2)) {
														if (!isset($exist_key_g[$k2])) {
															$keys[$k2] = $k2;
														}

														$exist_key_g[$k2] = $k2;
														$keychilds[$k2] = $k2;
													}
												}
											}
											foreach ($keys as $k) {
												if (!isset($treatment[$k])) {
													$treatment[$k] = array(
														'key' => $k,
														'keychilds' => $keychilds,
														'keyparents' => array(),
														'name' => $l2,
														'count' => 1,
														'count_0' => 0,
														'count_1' => 0,
														'is_pos' => 0,
														'is_neg' => 0,
														'is_group' => $ii <= 1,
														'position' => $l_position++,
														'tests' => array($arr['te_name'])
													);
												} else {
													$treatment[$k]['count'] += 1;
													$treatment[$k]['tests'][] = $arr['te_name'];
												}

												if (!empty($parent_key) && $parent_key != $k) {
													$treatment[$k]['keyparents'][] = $parent_key;
												}

												if ($pos_neg) {
													$treatment[$k]['is_pos'] = 1;
													$treatment[$k]['count_1'] += 1;
												} else {
													$treatment[$k]['is_neg'] = 1;
													$treatment[$k]['count_0'] += 1;
												}
											}
										}
									}
								}
							}
						}
						?>
					</ul>

				</td>
			</tr>
		<?php
		}

		$table_content = ob_get_clean();

		if (isset($_GET['debug_abxm'])) {
			var_dump($count_bacterias);
			echo '$treatment<pre>';
			var_export($treatment);
			echo '</pre>';
			echo '<pre><br>: $Mycoplasma_treatment';
			var_export($Mycoplasma_treatment);
			echo '</pre>';
		}
		?>
		<table style="width:98%;margin-left:27px;margin-top:5px">

			<tr>
				<td style="width:33%">
					<div style="text-align: center;padding-bottom:10px">
						<img src="<?php echo TRCPDD_PATH . 'images/bacteria.png' ?>" style="width:90px;margin-right:20px" />
					</div>
				</td>
				<td style="width:34.5%">
					<div style="text-align: center;padding-bottom:10px">
						<img src="<?php echo TRCPDD_PATH . 'images/markers.png' ?>" style="width:90px;margin-right:20px" />
					</div>
				</td>
				<td>
					<div style="text-align: center;padding-bottom:10px">
						<img src="<?php echo TRCPDD_PATH . 'images/antibiotics.png' ?>" style="width:90px;margin-right:20px" />
					</div>
				</td>
			</tr>
			<tr>
				<td style="border-left:5px solid #ddd;padding-left:10px">
					<div>
						<div style="padding-bottom:8px;">PATHOGENS DETECTED</div>
						<ul style="color: #E8642B;">
							<?php
							foreach ($bacterias as $arr) {
								echo '<li>' . $arr['te_name'] . '</li>';
							}
							?>
						</ul>
					</div>
				</td>
				<td style="border-left:5px solid #ddd;padding-left:10px">
					<div>
						<div style="padding-bottom:8px;">RESISTANCE DETECTED TO:</div>

						<?php
						if (!empty($all_Resistant)) {

							echo '<ul style="color: #E8642B;">';
							foreach ($all_Resistant as $item) {
								echo '<li>' . $item . '</li>';
							}
							echo '</ul>';
						}

						if (!empty($markers_inc)) {
							echo '<ul style="color: blue;">';
							foreach ($markers_inc as $item) {
								echo '<li>' . $item['te_abrev'] . ' - Marker Inconclusive</li>';
							}
							echo '</ul>';
						}

						if (empty($all_Resistant) && empty($markers_inc)) {
							echo '<div>None</div>';
						}
						?>
					</div>
				</td>
				<td style="border-left:4px solid #ddd;padding-left:10px">
					<div>
						<div style="text-transform: uppercase;padding-bottom:8px">Algorithmic ABX Matches:</div>
						<ul>
							<?php

							$left = array();
							$list_same = array();
							$list_same_keys = array();
							$list_duplicates = array();

							foreach ($treatment as $arr) {
								//$pcn = $arr['count'] / $count_bacterias * 100;
								if ($arr['count'] == $count_bacterias) //|| ($count_bacterias>4 && $pcn>40)
								{
									if (isset($arr['keychilds']) && count($arr['keychilds']) > 0 && $arr['count'] >= 3) {

										foreach ($arr['keychilds'] as $kchild) {
											$list_same_keys[] = $kchild;
											if (isset($list_same[$kchild])) {
												unset($list_same[$kchild]);
											}
										}
									} else if (isset($arr['keyparents']) && count($arr['keyparents']) > 0) {

										foreach ($arr['keyparents'] as $kparent) {
											if (isset($treatment[$kparent]) && is_array($treatment[$kparent]['count']) && count($treatment[$kparent]['count']) >= 3) {
												$list_same_keys[] = $arr['key'];
												$list_same[$kparent] = $treatment[$kparent];
											}

											break;
										}
									}

									if (!in_array($arr['key'], $list_same_keys)) {
										$list_same[$arr['key']] = $arr;
									}
								}
							}

							if ($count_bacterias == 1) {
								foreach ($treatment as $arr) {
									if ($arr['is_group'] && !empty($arr['name']) && !in_array($arr['name'], $list_duplicates)) {
										echo '<li>' . $arr['name'] . '</li>';
										$list_duplicates[] = $arr['name'];
									}
								}
							} else if (count($list_same) > 0) {
								if (isset($_GET['debug1'])) {
									echo 'do list_same<br>';
									var_dump($list_same);
									echo '<br>';
								}
								usort($list_same, function ($a, $b) {
									return $b['count'] - $a['count'];
								});
								foreach ($list_same as $k => $arr) {
									if (empty($arr['name']) || in_array($arr['name'], $list_duplicates)) {
										continue;
									}
									echo '<li>' . $arr['name'] . '</li>';
									$list_duplicates[] = $arr['name'];
								}
							} else {
								if (isset($_GET['debug_abxm'])) {
									echo '<br>do pos-neg<br>';
								}

								$list_pos_neg = array(0 => array(), 1 => array());
								$same_pos_neg = array(0 => array(), 1 => array());

								foreach ($treatment as $arr) {
									if ($arr['is_pos'] && isset($arr['count_1'])) {
										if (
											$count_bacterias_pn[1] >= 2
											&& $arr['count_1'] == $count_bacterias_pn[1]
										) {
											$same_pos_neg[1][] = $arr;
										}

										$list_pos_neg[1][] = $arr;
									}

									if ($arr['is_neg'] && isset($arr['count_0'])) {
										if (
											$count_bacterias_pn[0] >= 2
											&& $arr['count_0'] == $count_bacterias_pn[0]
										) {
											$same_pos_neg[0][] = $arr;
										}

										$list_pos_neg[0][] = $arr;
									}
								}

								usort($list_pos_neg[0], function ($a, $b) {
									if ($b['count_0'] == $a['count_0']) {
										return $a['position'] - $b['position'];
									}
									return $b['count_0'] - $a['count_0'];
								});
								usort($list_pos_neg[1], function ($a, $b) {
									if (isset($b['count_0']) && isset($a['count_0']) && $b['count_0'] == $a['count_0']) {
										return $a['position'] - $b['position'];
									}
									return $b['count_1'] - $a['count_1'];
								});


								$same2group = array();
								$diable_show_same2group = false;

								if (!$diable_show_same2group) {
									if ($count_bacterias_pn[0] == 1) {
										foreach ($list_pos_neg[1] as $item) {
											foreach ($list_pos_neg[0] as $item2) {
												if (
													$item['key'] == $item2['key']
													&& $count_bacterias_pn[0] == $item2['count']
													&& $count_bacterias_pn[1] == $item['count']
												) {
													if (count($same2group) < 3) {
														$same2group[] = $item;
													}
												}
											}
										}
									} else if ($count_bacterias_pn[1] == 1) {
										foreach ($list_pos_neg[0] as $item) {
											foreach ($list_pos_neg[1] as $item2) {
												if (
													$item['key'] == $item2['key']
													&& $count_bacterias_pn[0] == $item['count']
													&& $count_bacterias_pn[1] == $item2['count']
												) {
													if (count($same2group) < 3) {
														$same2group[] = $item;
													}
												}
											}
										}
									}
								}


								if (isset($_GET['ddd'])) {
									$same2group = array();
								}
								if (isset($_GET['ddd2'])) {
									var_dump($list_pos_neg[0]);
									echo '<br><br><br><br>';
									var_dump($list_pos_neg[1]);
									echo '<br><br><br><br>';
									var_dump($count_bacterias_pn);
									echo '<br><br><br><br>';
									var_dump($same2group);
								}

								if (count($same2group) > 0) {
									if (isset($_GET['debug1'])) {
										echo 'do same2group<br>';
									}
									foreach ($same2group as $arr2) {
										if (empty($arr2['name']) || in_array($arr2['name'], $list_duplicates)) {
											continue;
										}
										echo '<li>' . $arr2['name'] . '</li>';
										$list_duplicates[] = $arr2['name'];
									}
								} else {
									if (isset($_GET['debug1'])) {
										echo 'do gram pos/neg<br>';
									}
									$show_gram_title = count($list_pos_neg[0]) > 0 &&  count($list_pos_neg[1]) > 0;
									if (count($list_pos_neg[0]) > 0) {
										$max_neg_match = 0;
										echo '<li style="font-weight:bold;list-style:none;margin-left:-12px">';
										echo $show_gram_title ? 'Gram Negative' : '';
										echo '</li>';
										if (count($same_pos_neg[0]) == 0) {
											$same_pos_neg[0] = array();
											$testexist = array();

											foreach ($list_pos_neg[0] as $arr) {
												if ($arr['count_0'] >= 1) {
													$same_pos_neg[0][] = $arr;
													$testexist = array_merge($testexist, $arr['tests']);
													if ($arr['count_0'] > $max_neg_match) {
														$max_neg_match = $arr['count_0'];
													}
												}
											}
											if (count($same_pos_neg[0]) < 3) {
												foreach ($list_pos_neg[0] as $arr) {
													if (!in_array($arr['tests'][0], $testexist)) {
														$same_pos_neg[0][] = $arr;
														$testexist = array_merge($testexist, $arr['tests']);
													}
												}
											}
										}
										$count_t = 0;
										foreach ($same_pos_neg[0] as $arr) {
											if (empty($arr['name']) || in_array($arr['name'], $list_duplicates)) {
												continue;
											}
											echo '<li>' . $arr['name'] . '</li>';
											$list_duplicates[] = $arr['name'];

											$count_t++;
											if ($count_t >= 3 && $arr['count_0'] < $max_neg_match) break;
										}
									}

									if (count($list_pos_neg[1]) > 0) {
										$max_pos_match = 0;
										echo '<li style="font-weight:bold;list-style:none;margin-left:-12px">';
										echo $show_gram_title ? 'Gram Positive' : '';
										echo '</li>';
										if (count($same_pos_neg[1]) == 0) {
											$same_pos_neg[1] = array();
											$testexist = array();
											foreach ($list_pos_neg[1] as $arr) {
												if ($arr['count_1'] >= 1) {
													$same_pos_neg[1][] = $arr;
													$testexist = array_merge($testexist, $arr['tests']);
													if ($arr['count_1'] > $max_pos_match) {
														$max_pos_match = $arr['count_1'];
													}
												}
											}
											if (count($same_pos_neg[1]) < 3) {
												foreach ($list_pos_neg[1] as $arr) {
													if (!in_array($arr['tests'][0], $testexist)) {
														$same_pos_neg[1][] = $arr;
														$testexist = array_merge($testexist, $arr['tests']);
													}
												}
											}
										}
										$count_t = 0;
										foreach ($same_pos_neg[1] as $arr) {
											if (empty($arr['name']) || in_array($arr['name'], $list_duplicates)) {
												continue;
											}
											echo '<li>' . $arr['name'] . '</li>';
											$list_duplicates[] = $arr['name'];

											$count_t++;
											if ($count_t >= 3 && $arr['count_1'] < $max_pos_match) break;
										}
									}
								}

								if (!empty($Mycoplasma_treatment)) {
									$myc_count = 0;
									echo '<li style="font-weight:bold;list-style:none;margin-left:-12px">Mycoplasma</li>';
									$list_same = array();
									foreach ($Mycoplasma_treatment as $arr) {
										if ($arr['count'] == $count_mycoplasma) {
											$list_same[] = $arr;
										}
									}

									if (count($list_same) > 0) {
										$Mycoplasma_treatment = $list_same;
									}
									foreach ($Mycoplasma_treatment as $k => $arr) {
										if (empty($arr['name'])) {
											continue;
										}
										echo '<li>' . $arr['name'] . '</li>';
										$myc_count++;
										if ($myc_count >= 3) break;
									}
								}
							}




							?>
						</ul>
					</div>
				</td>
			</tr>
		</table>

		<br>

		<table>
			<thead>
				<tr>
					<td style="width:69.5%;background:#eee">
						Pathogens Detected
					</td>
					<td style="padding-left:10px;background:#eee;border-left:5px solid #ddd">
						Possible Treatments:
					</td>
				</tr>
			</thead>
			<tbody>
				<?php echo $table_content; ?>
			</tbody>

		</table>
	<?php

		if (!empty($notes['abx_below'])) {
			$notes['abx_below'] = str_replace('[report_date]', date('m/d/Y h:iA', strtotime($report['approve_date'])), $notes['abx_below']);
			echo '<p>';
			echo nl2br(stripslashes($notes['abx_below']));
			echo '</p>';
		}
	} //end if rejected


	if (isset($_REQUEST['html'])) {
		echo stripslashes($footer_html);
	}

	if ($has_style) {
		$abx_type       = 1; //1: uti; 2: wound: 3: gi
		if ($abx_groupk == 'wound') {
			$abx_type = 2;
		} else if ($abx_groupk == 'gi') {
			$abx_type = 3;
		}
		$wpdb->update('ci_reports_pcr', array('has_uti' => $abx_type), array('id' => $report['id']));
	}
	?>

</body>

</html>