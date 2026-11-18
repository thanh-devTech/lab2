<?php
global $pdf_header_text;
/*
template name: FA & MF
*/
if (!function_exists('tr_plot_callback')) {
	function tr_plot_callback($vl)
	{
		$w = '';
		if (stripos($vl, '0000033') > 0) {
			$w = 1;
		}

		return array($w, "", '');
	}
}
$bottom_comments = array();
$margin_top = 6;

if (strlen($report['requesting_practice']) > 35) {
	$margin_top += 0.5;
}
$medications = '';
if (tr_get_option('ci_pdf_data', 'blood_medication')) {
	$report['medication'] = str_ireplace(array('other:', 'other2:'), '', $report['medication']);
	$medications_arr = explode('|', $report['medication']);
	$medications = array();
	foreach ($medications_arr as $medication) {
		$marr = explode(';', $medication);
		$med = $marr[0];
		$start_date = (!empty($marr[1])) ? date('m/y', strtotime($marr[1])) : '';
		$end_date = (!empty($marr[2])) ? date('m/y', strtotime($marr[2])) : '';
		$daterange = '';
		if (!empty($start_date)) {
			$daterange = $start_date . (!empty($end_date) ? ' - ' . $end_date : '');
		}
		$medications[] = (!empty($daterange) ? $daterange . ' ' : '') . $med;
	}

	$medications = implode(', ', $medications);

	if (strlen($medications) > 157) {
		$margin_top += ceil(strlen($medications) / 157) * 0.32;
	}
}
$VCAM1 = 0;
$PSEL = 0;



$margin_bottom = 0;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
	$margin_bottom = $footer_height;
	$footer_height = 0.3;
}

?>

<!DOCTYPE html>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta id="final" content="<?php echo $report['final_results'] ?>" />
	<title>Result</title>
	<style>
		@page {
			margin: 0.5cm 0.5cm <?php echo $footer_height ?>cm 0.5cm;
		}

		body {
			margin-top: <?php echo $margin_top ?>cm;
			margin-bottom: <?php echo $margin_bottom ?>cm;
		}

		break,
		.break {
			page-break-after: always;
			border: 0;
		}

		* {
			font-family: 'Oswald', Arial, Helvetica, sans-serif;
			font-size: 8pt;
		}

		a.NoteRef {
			text-decoration: none;
		}

		.bold {
			font-weight: bold;
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

		table.border {
			border: 1px solid black;
			border-spacing: 0px;
			width: 100%;
		}

		table.bordertopbot {
			border-top: 2px solid #222;
			border-bottom: 1px solid #222
		}

		table.bordertop {
			border-top: 2px solid #222;
		}

		.borderout th {
			border: 1px solid #222;
			border-top-width: 2px
		}

		table.tdborder td,
		table.tdborder th {
			border: 1px solid black;
		}

		table.borderline td {
			border-bottom: 2px solid #D8D8D8
		}

		table.borderline tr.has_desc td {
			border: 0
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

		.marginbottom {
			margin-bottom: 10px;
		}

		.fontNormal {
			font-family: 'Arial';
			font-size: 9pt;
			color: #000000;
		}

		.fontNormalRed {
			font-family: 'Arial';
			font-size: 9pt;
			font-weight: bold;
		}

		.fontSmall {
			font-family: 'Arial';
			font-size: 9pt;
			color: #000000;
		}

		.fontBold {
			font-family: 'Arial';
			font-size: 9pt;
			color: #000000;
			font-weight: bold;
		}

		.fontBoldSmall {
			font-family: 'Arial';
			font-size: 9pt;
			color: #000000;
			font-weight: bold;
		}

		.fontBoldWhite {
			font-family: 'Arial';
			font-size: 9pt;
			color: #FFFFFF;
			font-weight: bold;
		}

		.fontHeader {
			font-family: 'Arial';
			font-size: 11pt;
			color: #000000;
			font-weight: bold;
		}

		.normal {
			margin-top: 2.5pt;
			margin-bottom: 2.5pt;
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
			float: right;
			margin-top: -15px;
			color: red;
			text-align: right;
			<?php echo $final_adding_font ?>
		}

		.has_flag td {
			font-weight: bold;
		}

		.has_flag .drug,
		.has_flag .conc {
			background: #eee
		}

		table .font_blue td,
		.blue {
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

		.group_name {
			font-weight: bold;
		}

		.group_name .normal {
			text-transform: none;
			font-weight: normal;
		}

		.grouppos {
			padding-left: 30px;
			font-size: 12px;
		}

		.small_table th,
		.small_table td {
			font-size: 8pt;
			padding: 2px;
			color: #000;
		}

		.small_padding_table th,
		.small_padding_table td {
			padding: 2px;
			color: #000;
		}

		.address_right div {
			font-size: 10px;
			line-height: 10px;
		}

		.fontDejavu {
			font-family: "DejaVu Sans";
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

		<?php if (!isset($_REQUEST['html'])) : ?>#header,
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

		#header h2 {
			margin: 0;
		}

		#footer {
			bottom: 0px;
			border-top: 0.1pt solid #aaa;
			line-height: 13px;
			padding-top: 5px;
			padding-right: 0px;
		}

		.drug_flag {
			color: blue;
		}

		.te_desc td {
			font-style: italic;
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

		.has_covid td {
			color: red;
		}

		.tblprevtest table td,
		.tblprevtest table td div {
			font-size: 9px;
			line-height: 10px;
			padding: 1px;
		}

		.header_tbl th {
			text-transform: uppercase;
			background: #555;
			text-align: center;
			height: 18px;
			color: #fff;
		}

		span.red,
		td.red {
			color: red;
		}

		.chart_icons {
			text-align: center;
			width: auto;
			margin: 10px auto;
		}

		.chart_icons .item {
			padding-right: 20px;
			color: #999;
		}

		.chart_icons .icon img {
			width: 12px;
			height: auto;
			display: inline;
		}

		.chart_icons .icon {
			width: 10px;
			text-align: right;
		}

		.notes_report p {
			margin: 0;
			padding: 0;

		}

		.notes_report br {
			line-height: 4px;
		}
	</style>
</head>

<body>



	<div id="header">
		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:35%;height:50px;" valign="top">
					<?php
					$logo_path = $logo_dir . $report['lab_logo'];
					$logo_main = $report['lab_logo'];


					$logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

					$has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
					if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
						$logo_main = $logo_second;
					}

					if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
						$size = getimagesize($logo_dir . $logo_main);
						$max_width = 250;
						$max_height = ($size[0] > $size[1]) ? 50 : 60;
					?>
						<img border="0" style="max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_dir . $logo_main ?>" />
					<?php
					}

					?>

				</td>
				<td style="width:30%;text-align: center">
					<h2 style="color:<?php echo $report['color'] ?>;font-size:18px;font-weight: normal;"><?php echo $pdf_title ?></h2>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">
					<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php if (!empty($pdf_header_text)) {
						echo apply_filters('the_content', $pdf_header_text);
					} else { ?>
						<div><?php echo $report['header_lab_name'] ?></div>
						<div><?php echo $report['header_lab_address'] ?></div>
						<div>Phone: <?php echo $report['header_lab_phone'] ?></div>
						<div>CLIA ID: <?php echo $report['header_lab_clia'] ?></div>
					<?php } ?>
				</td>
			</tr>
		</table>
		<table class="small_padding_table" style="border-top:4px solid <?php echo $report['color'] ?>;border-bottom:4px solid <?php echo $report['color'] ?>">
			<tr>
				<td style="width:32%">
					<h5>CLINIC INFORMATION</h5>
				</td>
				<td style="width:42%">
					<h5>PATIENT INFORMATION</h5>
				</td>
				<td>
					<h5>SPECIMEN INFORMATION</h5>
				</td>
			</tr>
			<tr>
				<td style="border-right:1px solid #ddd">
					<table class="noboder">
						<tr>
							<td style="width:20%">Name:</td>
							<td><?php echo $report['requesting_practice'] ?></td>
						</tr>
						<tr>
							<td>Address:</td>
							<td><?php echo $clinic_address ?></td>
						</tr>
						<?php if (!empty($report['cli_om-phone'])) { ?>
							<tr>
								<td>Phone:</td>
								<td><?php echo $report['cli_om-phone'] ?></td>
							</tr>
						<?php } ?>
						<tr>
							<td>Provider:</td>
							<td><?php echo htmlspecialchars($report['physician']) ?></td>
						</tr>

					</table>
				</td>
				<td style="border-right:1px solid #ddd;padding-left:5px;">
					<table class="noboder small_table">
						<tr>
							<td style="width:20%">Name:</td>
							<td><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
							<td style="width:70px">Gender:</td>
							<td style="width:30px"><?php echo htmlspecialchars($report['pa_gender']) ?></td>
						</tr>
						<tr>
							<td>DOB:</td>
							<td><?php echo tr_pdd_display_date($report['patient_dob']) ?></td>
							<td>Pain Score:</td>
							<td><?php if ($report['p_pain_score'] !== '') echo $report['p_pain_score'] . '/10' ?></td>
						</tr>
						<tr>
							<td>Phone:</td>
							<td><?php echo $report['pa_phone'] ?></td>
							<td>Fatigue Score:</td>
							<td><?php if ($report['p_fatigue_score'] !== '') echo $report['p_fatigue_score'] . '/10'; ?></td>
						</tr>

						<tr>
							<td>Address:</td>
							<td colspan="<?php echo $settings['show_genotype'] ? '1' : '3' ?>" class="smalltext"><?php echo $patient_adress ?></td>
							<?php
							if ($settings['show_genotype']) {
								echo '<td>' . ((!empty($report['p_hemoglobin'])) ? 'Hemoglobin:' : '') . '</td>';
								echo '<td>' . $report['p_hemoglobin'] . '</td>';
							}
							?>
						</tr>
						<?php if ($show_location && !empty($report['pa_location'])) { ?>
							<tr>
								<td>Location:</td>
								<td colspan="3"><?php echo htmlspecialchars($report['pa_location']) ?></td>
							</tr>
						<?php } else { ?>
							<tr>
								<td>ID:</td>
								<td colspan="3"><?php echo htmlspecialchars($report['pa_code']) ?></td>
								<?php
								if ($settings['show_genotype']) {
									echo '<td>' . ((!empty($report['pa_genotypes'])) ? 'Genotype:' : '') . '</td>';
									echo '<td>' . $req_settings['pa_genotypes'][$report['pa_genotypes']] . '</td>';
								} else {
									echo '<td>' . ((!empty($report['p_hemoglobin'])) ? 'Hemoglobin:' : '') . '</td>';
									echo '<td>' . $report['p_hemoglobin'] . '</td>';
								}
								?>
							</tr>
						<?php } ?>

						<?php if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) { ?>
							<tr>
								<td>Passport:</td>
								<td colspan="3"><?php echo htmlspecialchars($report['pa_passport']) ?></td>
							</tr>
						<?php } ?>
					</table>
				</td>
				<td style="padding-left:5px;">
					<table class="noboder">
						<tr>
							<td style="width:50%">Accession Number:</td>
							<td><?php echo $report['assen'] ?></td>
						</tr>
						<?php if (!empty($report['oldreq'])) { ?>
							<tr>
								<td>Ref ID:</td>
								<td><?php echo $report['oldreq'] ?></td>
							</tr>
						<?php } ?>
						<tr>
							<td>Date Collected:</td>
							<td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<tr>
							<td>Date Received:</td>
							<td><?php echo tr_pdd_display_date($report['received_in_lab'], 'm/d/Y H:i', false, true) ?></td>
						</tr>

						<tr>
							<td>Date Reported:</td>
							<td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<?php
						if (!empty($report['dagnostic_codes1']) && tr_get_option('ci_pdf_data', 'bloodpdf_show_diagnostic')) {
						?>
							<tr>
								<td>Diagnostic code:</td>
								<td><?php echo $report['dagnostic_codes1'] ?></td>
							</tr>
						<?php
						}
						$pr_code = tr_get_option('ci_pdf_data', 'blood_proceduralcode');
						if (!empty($pr_code)) {
							//86328, 36416 for Vir
						?>
							<tr>
								<td>Procedural codes:</td>
								<td><?php echo $pr_code ?></td>
							</tr>
						<?php
						}
						if (tr_get_option('ci_pdf_data', 'show_result_mrn')) {
						?>
							<tr>
								<td>MRN#:</td>
								<td><?php echo $report['pa_mrn'] ?></td>
							</tr>
						<?php
						}
						?>
					</table>
				</td>
			</tr>
			<?php
			if (!empty($medications)) {
			?>
				<tr>
					<td colspan="3" style="border-top:1px solid #ddd">
						Medications: <?php echo $medications ?></td>
				</tr>
			<?php
			}
			?>
		</table>

	</div>
	<?php
	ob_start();
	?>

	<div id="footer">
		<?php echo apply_filters('the_content', $footer_text); ?>
	</div>

	<?php
	$footer_html = ob_get_clean();

	if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
		echo stripslashes($footer_html);
	}


	if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
		include('rejected_default.php');
	} else {
		$tests_notes = array();

		$test_comments = array(
			'VCAM1' => '',
			'PSEL' => '',
			'MFI-3' => '',
			'MFI-10' => ''
		);

		$base_comments = array(
			'VCAM1' => array(
				'first' => 'FA-WB-VCAM adhesion represents adhesive properties of RBCs in whole blood. ',
				'fname' => 'An adhesion index of '
			),
			'PSEL' => array(
				'first' => 'FA-WB-Psel represents adhesive properties of whole blood to P-selectin. ',
				'fname' => 'An adhesion index of '
			),
			'MFI-3' => array(
				'first' => '',
				'fname' => 'The Mechanical Fragility index (MFI) at 3 min of '
			),
			'MFI-10' => array(
				'first' => '',
				'fname' => 'MFI at 10 min of '
			)
		);

		$chart_data = array();

		$has_box_1_tests = false;
		$box1_tests_content = '';

		foreach ($drug_groups as $groupid => $groupdata) {
			if (
				stripos($groupdata['name'], 'Whole Blood Adhesion Index') === false
				&& stripos($groupdata['name'], 'Mechanical Fragility') === false
				&& stripos($groupdata['name'], 'Fragility') === false
				&& stripos($groupdata['name'], 'Adhesion') === false
			) {
				continue;
			}
			$has_value = false;

			$showed_group = false;
			$count_group_empty = 0;
			$count_group_tests_showed = 0;

			ob_start();

			foreach ($groupdata['tests'] as $arr) {
				$class = '';
				$drug_key = strtoupper($arr['te_name']);

				if (empty($drug_key)) {
					continue;
				}

				if ($showed_group) {
					$class .= ' noborderrow ';
				}
				$arr['conc_dis'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];

				if ($arr['conc'] === '' && !in_array($arr['pos-neg'], array('CAN', 'REJ'))) {
					$can_show_group = false;
					if (stripos($arr['te_units'], 'percent') === false) {
						$count_group_empty++;
						continue;
					}
				}
				$count_group_tests_showed++;

				if ($drug_key == 'VCAM1') {
					$VCAM1 = $arr['conc_dis'];
				} else if ($drug_key == 'PSEL') {
					$PSEL = $arr['conc_dis'];
				}
	?>
				<tr class="<?php echo $class ?>">
					<?php if (!$showed_group) {
						echo '<td rowspan="#group_rowspan#" class="bold">' . $groupdata['name'] . '</td>';
						$showed_group = true;
					}
					?>
					<td class="drug"><?php echo $arr['te_name'] ?></td>
					<?php if (in_array($arr['pos-neg'], array('CAN', 'REJ'))) { ?>
						<td class="value bold red center"><?php echo $arr['flag'] ?></td>
						<td colspan="3"><?php echo htmlentities(trpdd_show_symbol($arr['comments'])) ?></td>
					<?php } else { ?>
						<td class="value bold center"><?php echo htmlentities($arr['conc_dis']) ?></td>
						<td class="center"><?php echo htmlentities(trpdd_show_symbol($arr['te_units'])) ?></td>
						<td class="center"><?php echo htmlentities(str_replace('=', '', $arr['te_cutoff'])) ?></td>
						<td class="center"><?php echo htmlentities(str_replace('=', '', $arr['te_upper_limit'])) ?></td>
					<?php } ?>

				</tr>
			<?php
				if ($arr['conc'] === '') {
					continue;
				}

				if (!is_array($chart_data[$drug_key])) $chart_data[$drug_key] = array();
				if (!is_array($chart_data[$drug_key]['dates'])) $chart_data[$drug_key]['dates'] = array();
				if (!is_array($chart_data[$drug_key]['conc'])) $chart_data[$drug_key]['conc'] = array();
				if (!is_array($chart_data[$drug_key]['p_condition'])) $chart_data[$drug_key]['p_condition'] = array();

				array_unshift($chart_data[$drug_key]['dates'], date('m/d/Y', strtotime($report['doc'])));
				array_unshift($chart_data[$drug_key]['conc'], $arr['conc']);
				array_unshift($chart_data[$drug_key]['p_condition'], $report['p_condition']);

				$chart_data[$drug_key]['unit'] = $arr['te_units'];
				$chart_data[$drug_key]['name'] = $arr['te_name'];
				$chart_data[$drug_key]['np'] = str_replace('=', '', $arr['te_cutoff']);
				$chart_data[$drug_key]['ss'] = $arr['te_upper_limit'];

				//check for comments
				if (stripos($arr['te_units'], 'percent') === false) {
					$np = 0;
					if (strpos($arr['te_cutoff'], '<=') !== false) {
						$vl = str_replace(array('<=', ' '), '', $arr['te_cutoff']);
						if ($arr['conc'] <= $vl) {
							//$np = -1;
						} else {
							$np = 1;
						}
					} else if (strpos($arr['te_cutoff'], '<') !== false) {
						$vl = str_replace(array('<', ' '), '', $arr['te_cutoff']);
						if ($arr['conc'] < $vl) {
							//$np = -1;
						} else {
							$np = 1;
						}
					} else {
						list($vl1, $vl2) = explode('-', $arr['te_cutoff']);
						if ($arr['conc'] < $vl1) {
							$np = -1;
						} else if ($arr['conc'] > $vl2) {
							$np = 1;
						}
					}

					$ss = 0;
					if (strpos($arr['te_upper_limit'], '<') !== false) {
						$vl = str_replace(array('<', ' '), '', $arr['te_upper_limit']);
						if ($arr['conc'] < $vl) {
							//$ss = -1;
						} else {
							$ss = 1;
						}
					} else {
						list($vl1, $vl2) = explode('-', $arr['te_upper_limit']);
						if ($arr['conc'] < $vl1) {
							$ss = -1;
						} else if ($arr['conc'] > $vl2) {
							$ss = 1;
						}
					}

					if ($np == 1 && $ss == -1) {
						//$np = 0;
					}

					$test_comments[$arr['te_name']] = $base_comments[$arr['te_name']]['first'];
					if ($np == -1) {
						$test_comments[$arr['te_name']] .= '<span class="red">' . $base_comments[$arr['te_name']]['fname'] . $arr['conc'] . ' is below the reference range for normal population </span>';
					} else if ($np == 0) {
						$test_comments[$arr['te_name']] .= $base_comments[$arr['te_name']]['fname'] . $arr['conc_dis'] . ' is within the reference range for normal population ';
					} else {
						$test_comments[$arr['te_name']] .= '<span class="red">' . $base_comments[$arr['te_name']]['fname'] . $arr['conc'] . ' is above the reference range for normal population </span>';
					}
					if ($ss == -1) {
						$test_comments[$arr['te_name']] .= 'and below the reference range for HbSS population.';
					} else if ($ss == 0) {
						$test_comments[$arr['te_name']] .= 'and within the reference range for HbSS population.';
					} else {
						$test_comments[$arr['te_name']] .= ' <span class="red">and above the reference range for HbSS population.</span>';
					}
				}

				if (stripos($arr['te_name'], 'Hemol') !== false) {
					$hemol_vl = str_replace(array(' ', '%', '<'), '', $arr['conc_dis']);
					if ($hemol_vl > 20) {
						$tests_notes[] = 'Note: A pre-existing hemolysis value of ' . $arr['conc_dis'] . ' was found on the sample, probably issues with sample collection, storage or shipping. Thus, the sample was rejected and no results are shown.';
					} else if ($hemol_vl > 1.8) {
						$tests_notes[] = 'Note: Pre-existing hemolysis is above the SS steady state reference range. Environmental factors can elevate preexisting hemolysis, including but not limited to storage temperatures above the recommended range, freezing the sample, direct contact of the sample tube with cold packs during storage or shipping. As a result, the preexisting hemolysis results should be interpreted with caution, and a repeat sample evaluation should be considered.';
					}
				}

				$has_value = true;
			}

			$group_content = ob_get_clean();
			if ($count_group_empty < 2) {
				$group_content = str_replace('#group_rowspan#', $count_group_tests_showed, $group_content);
				$box1_tests_content .= $group_content;
			}

			if (!$has_value) {
				if (stripos($groupdata['name'], 'Mechanical') !== false) {
					//$tests_notes[] = 'Note: Mechanical Fragility Assay failed due to a technical problem or to a sample issue. No data is shown.';
				} else if (stripos($groupdata['name'], 'Adhesion') !== false) {
					//$tests_notes[] = 'Note: Flow Adhesion Assays failed due to a technical problem or to a sample issue. No data is shown.';
				}
			}
			unset($drug_groups[$groupid]);
			$has_box_1_tests = true;
		}


		if ($has_box_1_tests) {
			?>
			<table class="small_table tdborder marginbottom">
				<thead>
					<tr class="header_tbl">
						<th colspan="4" style="text-align: left">Results</th>
						<th colspan="2" style="background:none;color:#000;border:0">Reference Ranges</th>
					</tr>
					<tr class="title_tbl">
						<th style="width:25%">Class</th>
						<th style="width:21%">Test</th>
						<th style="width:12%">Result</th>
						<th style="width:10%">Units</th>
						<th style="width:16%">Normal Population</th>
						<th>Steady State</th>
					</tr>
				</thead>
				<?php
				echo $box1_tests_content;
				?>
			</table>
			<?php
		}

		$showed_title_parents = array();

		foreach ($drug_groups as $groupid => $groupdata) {

			$has_test = false;
			$has_pos = false;

			ob_start();
			foreach ($groupdata['tests'] as $arr) {
				$class = '';
				$has_comments = !empty($arr['te_comments']);

				$arr['conc_dis'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];

				if (empty($arr['conc_dis']) && empty($arr['te_units'])) {
					$arr['conc_dis'] = 'Not Performed';
				}
				if (empty($arr['conc']) && empty($arr['pos-neg'])) {
					continue;
				}
			?>
				<tr class="<?php echo $class ?>">
					<td <?php if ($has_comments) echo 'rowspan="2"' ?> class="drug fontBold"><?php echo $arr['te_name'] ?></td>
					<td class="conc center fontDejavu"><?php echo $arr['conc_dis'] . (!empty($arr['te_units']) ? ' ' . trpdd_show_symbol($arr['te_units']) : '') ?></td>
					<td class=" fontDejavu"><?php echo $arr['comments'] ?></td>
					<td class="center fontDejavu"><?php echo $arr['te_desc'] ?></td>
				</tr>
				<?php
				if ($has_comments) {
				?>
					<tr>
						<td colspan="3"><?php echo trpdd_filter_comments_in_pdf($arr['te_comments']) ?></td>
					</tr>
				<?php
				}

				$has_test = true;
			}


			$content = ob_get_clean();

			if ($has_test) {
				$has_report = true;
				?>
				<table class="small_table tdborder marginbottom">
					<thead>
						<?php
						if ($groupdata['parent'] == 0 || ($groupdata['parent'] > 0 && !in_array($groupdata['parent'], $showed_title_parents))) {
							$parent_tt = $groupdata['name'];
							if ($groupdata['parent'] > 0) {
								$parent_tt = $drug_groups[$groupdata['parent']]['name'];
								$showed_title_parents[] = $groupdata['parent'];
							} else {
								$showed_title_parents[] = $groupdata['id'];
							}

						?>
							<tr class="headernoborder">
								<th colspan="4" class="group_name center" style="border:none;font-size:14px;padding-bottom:10px">
									<?php echo $parent_tt ?>
								</th>
							</tr>
						<?php
						}
						?>
						<tr class="header_tbl">
							<th colspan="3" style="text-align: left"><?php echo ($groupdata['parent'] > 0) ? $groupdata['name'] : 'Tests' ?></th>
							<th colspan="1" style="background:none;color:#000;border:0">Reference Ranges</th>
						</tr>
						<tr class="title_tbl">
							<th style="width:28%">Test Name</th>
							<th style="width:18%">Result</th>
							<th style="width:34%">Comment</th>
							<th style="width:20%">Interval</th>
						</tr>
					</thead>
					<?php echo $content; ?>
				</table>
				<?php
				if (!empty($groupdata['comments'])) {
					if (strpos($groupdata['comments'], 'atbottom') > 0) {
						$bottom_comments[] = $groupdata['comments'];
					} else {
						echo '<div>' . trpdd_filter_comments_in_pdf($groupdata['comments']) . '</div>';
					}
				}
			}
		}

		$history_content = '';
		if (!isset($report['cli_no_history']) || !$report['cli_no_history']) {
			$limit_date = date('Y-m-d', strtotime('-6months'));
			$rejected_status = tr_pdd_where_batch_rejected(true);

			$sql = "select re.id,
		            re.created_date, 
		            re.approve_date, 
		            re.instrument,
		            ba.mid,
		            ba.assen,
                    ba.panel_tests_requested,
                    ba.p_condition,
                    ba.doc,
                    ba.batch_status
            from ci_reports_blood re  
            JOIN ci_batches_blood ba ON ba.assen = re.patient_id
            where ba.patient_id = '{$report['pa_ID']}' 
            and ba.doc <= '{$report['doc']}' 
            and re.id != {$report['id']}
            and ba.cli_ID = '{$report['cli_ID']}'
            and estatus = 'C'
            Order by ba.doc desc 
            limit 30";
			$rs = $wpdb->get_results($sql, ARRAY_A);
			if (isset($_GET['debug'])) {
				var_dump($rs);
				var_dump($sql);
			}

			if ($rs) {
				$has_history = false;
				ob_start();
				?>
				<table class="small_table border marginbottom tblprevtest">
					<tr class="header_tbl">
						<th colspan="<?php echo count($chart_data) + 2 ?>">Historical Results</th>
					</tr>
					<tr class="title_tbl">
						<?php
						if (isset($_GET['html']) && isset($_GET['debug1'])) {
							echo '<td>assen</td>';
						}
						?>
						<th style="width:80px">Collected</th>
						<th style="width:60px">Resulted</th>
						<?php
						foreach ($chart_data as $testname => $arr) {
							echo '<th>' . $arr['name'] . '</th>';
						}
						?>
					</tr>
					<?php
					$count_old_rs = 0;
					foreach ($rs as $i => $row) {

						$sql       = "SELECT *
                        FROM ci_test_panels tp
                        left join ci_results_blood rs ON (tp.te_name = rs.drug or tp.te_abrev = rs.drug) and rs.report_id= {$row['id']}
                        WHERE  tp.te_type='blood' 
                        and ( 
                            (rs.id >0 and (tp.te_ID in ({$test_ids}) 
                                            or tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'blood' 
                                                                and (tg_only_group = 1 or  tg_manual_edit=1) {$where_group})) )
                            or 
                            (rs.id is null 
                                and tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'blood' and  tg_manual_edit=1 {$where_group}))
                            OR
                            (rs.id is null 
                                and tp.te_ID in ({$test_ids}) 
                                and tp.te_group_id in (select tg_ID from ci_test_groups where tg_type='blood' and tg_manual_edit=1) )
                        )
                        ";
						$date_test = date('Y-m-d', strtotime($row['created']));
						$sql       .= " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
						$sql       .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";

						$all_results_q = $wpdb->get_results($sql, ARRAY_A);

						$all_res       = array();
						$count_has_val = 0;
						foreach ($all_results_q as $arr) {
							$arr['drug']             = strtoupper($arr['te_name']);
							$all_res[$arr['drug']] = $arr;
							if ($arr['conc'] !== '' && $arr['conc'] !== null) {
								$count_has_val++;
							}
						}

						if ($row['batch_status'] == 're_inco') {
							//nothing
						} else if ($count_has_val == 0) {
							continue;
						} else if (isset($rejected_status[$row['batch_status']])) {
							continue;
						}
						$count_old_rs++;
						if ($count_old_rs > 18) {
							break;
						}

						echo '<tr>';
						if (isset($_GET['html']) && isset($_GET['debug1'])) {
							$his_url = Tr_Base_Class_V6::link(array('id' => '', 'assen' => $row['assen'], 'tr_action' => $_GET['tr_action']), false);
							echo '<td><a href="' . $his_url . '" target=_blank>' . $row['assen'] . '</a></td>';
						}
						echo '<td class="center">' . date('m/d/Y', strtotime($row['doc'])) . '</td>';
						echo '<td class="center">' . date('m/d/Y', strtotime($row['approve_date'])) . '</td>';


						$has_history = true;

						foreach ($chart_data as $testname => $arr2) {

							$drug_key = strtoupper($testname);
							$arr      = $all_res[$drug_key];
							$arr['conc_dis'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];
							if ($row['batch_status'] == 're_inco') {
								$arr['conc'] = 'Inconclusive';
								$arr['conc_dis'] = 'Inconclusive';
							}

							echo '<td class="center">' . htmlentities($arr['conc_dis']) . ' </td>';

							if (!is_array($chart_data[$drug_key])) {
								$chart_data[$drug_key] = array();
							}
							if (!is_array($chart_data[$drug_key]['dates'])) {
								$chart_data[$drug_key]['dates'] = array();
							}
							if (!is_array($chart_data[$drug_key]['conc'])) {
								$chart_data[$drug_key]['conc'] = array();
							}
							if (!is_array($chart_data[$drug_key]['p_condition'])) {
								$chart_data[$drug_key]['p_condition'] = array();
							}

							if ($arr['conc'] !== '' && $arr['conc'] !== null && $arr['conc'] != 'Inconclusive') {
								array_unshift($chart_data[$drug_key]['dates'], date('m/d/Y', strtotime($row['doc'])));
								array_unshift($chart_data[$drug_key]['conc'], $arr['conc_dis']);
								array_unshift($chart_data[$drug_key]['p_condition'], $row['p_condition']);
							}
						}
						echo '</tr>';
					}
					?>
				</table>
				<?php
				$history_content = ob_get_clean();
			}

			//show chart
			if (count($chart_data) > 0) {

				echo '<table style="width:100%"><tr>';
				$ic = 0;

				require_once(TRCPDD_PATH . 'inc/jpgraph/jpgraph.php');
				//require_once (TRCPDD_PATH.'inc/jpgraph/jpgraph_utils.inc.php');
				require_once(TRCPDD_PATH . 'inc/jpgraph/jpgraph_line.php');

				$condition_imgs                    = array();
				$condition_imgs['BASELINE']        = imagecreatefrompng(TRCPDD_PATH . 'images/square-green.png');
				$condition_imgs['THERAPY START']   = imagecreatefrompng(TRCPDD_PATH . 'images/circle-blue.png');
				$condition_imgs['MONITOR THERAPY'] = imagecreatefrompng(TRCPDD_PATH . 'images/star-blue.png');
				$condition_imgs['ACUTE CRISIS']    = imagecreatefrompng(TRCPDD_PATH . 'images/triangle-red.png');


				foreach ($chart_data as $drug => $arr) {
					if (!is_array($arr['dates']) || count($arr['dates']) == 0 || stripos($arr['unit'], 'percent') !== false) {
						continue;
					}
					if ($ic > 0 && $ic % 2 == 0) {
						echo '</tr><tr>';
					}
					$ic++;

					$arr['conc_dis'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];

					$datax = $arr['dates'];
					$datay = $arr['conc_dis'];
					if (count($datax) == 1) {
						$datax = array('', $datax[0], '');
						$datay = array('', $datay[0], '');
					}


					$datay = array_map('floatval', $datay);
					$maxvl = 0;
					foreach ($datay as $i => $vl) {
						if ($vl == 0) {
							if ($i == 0) {
								$datay[$i] = $datay[$i + 1];
							} else if ($i == count($datay) - 1) {
								$datay[$i] = $datay[$i - 1];
							} else {
								$datay[$i] = ($datay[$i - 1] + $datay[$i + 1]) / 2;
							}
							$datay[$i] = number_format($datay[$i], 2) . '0000033';
							$datay[$i] = str_replace(',', '', $datay[$i]);
						}
						if ($vl > $maxvl) {
							$maxvl = $vl;
						}
					}


					$startpos       = 40;
					$padding_left   = 40;
					$padding_bottom = 60;
					$angle          = 0;
					$icon_top       = 190;

					if (count($datay) > 10) {
						$angle          = 40;
						$padding_left   = 55;
						$padding_bottom = 69;
						$startpos       = 0;
						$icon_top       = 208;
					} else if (count($datay) > 6) {
						$angle        = 20;
						$padding_left = 55;
						$startpos     = 0;
						$icon_top     = 208;
					}
					$imagedata = '';
					try {
						//$theme_class=new UniversalTheme;

						$graph = new Graph(640, 220);
						$graph->title->Set($drug);
						$graph->SetScale("textlin");

						$graph->ygrid->SetFill(true, '#FFFFFF@0.5', '#FFFFFF@0.5');
						$graph->SetBackgroundGradient('#e06b69', '#e06b69', GRAD_HOR, BGRAD_PLOT);
						$graph->img->SetAntiAliasing(false);
						$graph->SetBox(false);
						$graph->img->SetMargin($padding_left, 30, 10, $padding_bottom);
						$graph->xaxis->SetTickLabels($datax);
						$graph->xaxis->SetLabelAngle($angle);




						//red data
						$arr['ss']    = str_replace(array('<', '>', '<=', ' '), '', $arr['ss']);
						$arr['minss'] = 0;
						if (strpos($arr['ss'], '-') > 0) {
							list($arr['minss'], $arr['ss']) = explode('-', $arr['ss']);
						}
						$red_num  = floatval($arr['ss']);
						$data_red = array();
						foreach ($datay as $item) {
							$data_red[] = $red_num;
						}


						//empty plot 0 and top
						$topnum = $red_num + $red_num / 10;
						if ($topnum > $maxvl) {
							$maxvl = $topnum;
						}
						$data_0 = array();
						$top_arr = array();

						foreach ($datay as $item) {
							$data_0[] = 0;
							$top_arr[] = $maxvl;
						}
						$l0plot = new LinePlot($data_0);
						$graph->Add($l0plot);
						$l0plot->SetWeight(0);

						//empty plot top
						$topplot = new LinePlot($top_arr);
						$graph->Add($topplot);
						$topplot->SetWeight(0);


						//fill red background
						$plot_red = new LinePlot($data_red);
						$graph->Add($plot_red);
						$plot_red->SetColor("#ecc783"); //orange
						$plot_red->SetFillGradient('#ecc783', '#ecc783');
						$plot_red->SetStepStyle();

						if ($arr['minss'] > 0) {
							$data_yellow = array();
							foreach ($datay as $item) {
								$data_yellow[] = $arr['minss'];
							}
							$plot_yellow = new LinePlot($data_yellow);
							$graph->Add($plot_yellow);
							$plot_yellow->SetColor("#efef7a"); //yellow
							$plot_yellow->SetFillGradient('#efef7a', '#efef7a');
							$plot_yellow->SetStepStyle();
						}

						//fill green bg
						$arr['np'] = str_replace(array('<', '<=', '>', ' '), '', $arr['np']);
						if (strpos($arr['np'], '-') > 0) {
							list($tmp, $arr['np']) = explode('-', $arr['np']);
						}
						$green_num  = floatval($arr['np']);
						$data_green = array();
						foreach ($datay as $item) {
							$data_green[] = $green_num;
						}

						//fill green bg
						$plot_green = new LinePlot($data_green);
						$graph->Add($plot_green);
						$plot_green->SetColor("#7ce27c"); //green
						$plot_green->SetFillGradient('#7ce27c', '#7ce27c');
						$plot_green->SetStepStyle();


						if (in_array($drug, array('MFI-3', 'MFI-10'))) {
							//fill blue low
							$data_blue = array();
							$blue_num = $drug == 'MFI-3' ? 0.59 : 4.99;
							foreach ($datay as $item) {
								$data_blue[] = $blue_num;
							}
							$plot_blue = new LinePlot($data_blue);
							$graph->Add($plot_blue);
							$plot_blue->SetColor("#35afe3"); //blue
							$plot_blue->SetFillGradient('#35afe3', '#35afe3');
							$plot_blue->SetStepStyle();
						}

						//line
						$l1plot = new LinePlot($datay);
						$graph->Add($l1plot);

						$l1plot->mark->SetType(MARK_FILLEDCIRCLE);
						$l1plot->mark->SetWidth(5);
						$l1plot->mark->SetFillColor("#4674BF");
						$l1plot->SetColor("#4674BF");
						$l1plot->SetWeight(4);

						$l1plot->mark->SetCallback("tr_plot_callback");



						$img = $graph->Stroke(_IMG_HANDLER);

						//fill condition
						$count = count($arr['p_condition']);

						foreach ($arr['p_condition'] as $ii => $cond) {


							if ($count == 1) {
								$left = $startpos + (600 - $padding_left) / 2;
							} else {
								$left = $startpos + ((600 - $padding_left) / ($count - 1)) * $ii;
							}
							if (!empty($cond)) {
								$src_im = $condition_imgs[strtoupper($cond)];
								if ($src_im) {
									imagecopy($img, $src_im, $left, $icon_top, 0, 0, 12, 12);
								}
							}
						}

						ob_start();
						imagejpeg($img);
						$imagedata = ob_get_clean();
					} catch (Exception $e) {
						//var_dump($e);
					}

					$has_report = true;
					$image_url = 'data:image/jpeg;base64,' . base64_encode($imagedata);
				?>
					<td style="width:50%">
						<img src="<?php echo $image_url ?>" width="380" height="120" style="margin-top:0px" />
					</td>
			<?php

				}
				echo '</tr></table>';

				echo '<table class="chart_icons"><tr>';
				echo '<td class="icon"><img src="' . (TRCPDD_PATH . 'images/square-green.png') . '"/></td><td class="item"> - Baseline</td>';
				echo '<td class="icon"><img src="' . (TRCPDD_PATH . 'images/circle-blue.png') . '"/></td><td class="item"> - Therapy Start</td>';
				echo '<td class="icon"><img src="' . (TRCPDD_PATH . 'images/star-blue.png') . '"/></td><td class="item"> - Monitor Therapy</td>';
				echo '<td class="icon"><img src="' . (TRCPDD_PATH . 'images/triangle-red.png') . '"/></td><td class="item"> - Acute Crisis</td>';
				echo '</tr></table>';
			}

			if ($has_history) {
				echo $history_content;
			}
		}


		foreach ($test_comments as $k => $cm) {
			if (!empty($cm)) {
				echo '<div>' . $cm . '</div>';
			}
		}

		if ($VCAM1 > 400 && $PSEL > 50) {
			echo '<div style="padding:10px 0">Note: CRITICAL RESULT: Data from the ELIPSIS study shows that patients with elevated whole blood adhesion indices to BOTH VCAM and P-selectin are at high risk for a future VOC. Do not hesitate to call the medical director directly if you have any questions at (313) 482-9435 ext 701</div>';
		}


		if (!empty($notes['style2_notes'])) {
			echo '<div style="font-size:9px;margin:5px 0;line-height:10px">';
			echo trpdd_filter_comments_in_pdf($notes['style2_notes']);
			echo '</div>';
		}


		if (!empty($tests_notes)) {
			?>
			<div style="margin:5px 0;font-style: italic;">
				<?php
				foreach ($tests_notes as $note) {
					echo '<div>' . trpdd_filter_comments_in_pdf($note) . '</div>';
				}
				?>
			</div>
	<?php
		}
	} //end if rejected

	include('_approved_by.php');


	if (!empty($report['notes_report'])) {
		echo '<div style="padding-top:6px" class="notes_report">';
		//echo 'Notes: ';
		echo trpdd_filter_comments_in_pdf($report['notes_report']);
		echo '</div>';
	}

	if (!empty($pdf_notes)) {
		echo '<div class="notes_report" >';
		echo trpdd_filter_comments_in_pdf($pdf_notes);
		echo '</div>';
	}

	if (!empty($notes['style2_notes_bottom'])) {
		echo '<div style="font-size:9px;margin:5px 0;line-height:10px">';
		echo trpdd_filter_comments_in_pdf($notes['style2_notes_bottom']);
		echo '</div>';
	}

	if (count($bottom_comments) > 0) {
		foreach ($bottom_comments as $cm) {
			echo '<div>' . trpdd_filter_comments_in_pdf($cm) . '</div>';
		}
	}

	if (isset($_REQUEST['html'])) {
		echo stripslashes($footer_html);
	}
	?>

	<?php
	if (!isset($add_page)) {
		include('_pdf_pagination.php');
	}
	?>

</body>

</html>