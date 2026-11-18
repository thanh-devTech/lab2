<?php
/*
Name: Value with Line
*/
global $wpdb, $ci_cutoffs, $report;
global $pdf_header_text;

$margin_top = 5.8;
if (tr_get_option('ci_pdf_data', 'show_patient_passport')) {
	$margin_top = 6.3;
}

/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */


$footer_height = 1.5;
ob_start();
?>

<div id="footer">
	<?php
	$footer_text = trpdd_get_footer_text($report, 'pcr');

	$director_signature = '';
	if (!empty($report['lab_director_signature'])) {
		$sign_dir = tr_get_signature_dir();
		$director_signature_url = $sign_dir['path'] . '/' . $report['lab_director_signature'];
		$director_signature = '<img src="' . $director_signature_url . '" style="height:20px">';
	}
	$footer_text = str_replace('[signature]', $director_signature, $footer_text);

	if (preg_match("/height\s?:\s?([0-9]+)px/", $footer_text, $match_footer_height)) {

		$new_footer_height = ($match_footer_height[1] + 10) * 0.0261;
		if ($new_footer_height > $footer_height) {
			$footer_height = $new_footer_height;
		}
	}
	$footer_text = str_replace('<br>', '<br />', $footer_text);
	if ((strlen($footer_text) > 300 || substr_count($footer_text, '<br />') >= 3) && $footer_height < 3) {
		$footer_height += substr_count($footer_text, '<br />') * 0.4;
	}
	echo $footer_text;
	?>
</div>

<?php
$footer_html = ob_get_clean();


if (!function_exists('trshow_chartbar')) {
	function trshow_chartbar($arr, $width = 50)
	{
		$w1 = (($arr['low_2'] - $arr['low_1']) != 0) ? $width / ($arr['low_2'] - $arr['low_1']) : 0;
		$w2 = (($arr['medium_2'] - $arr['medium_1']) != 0) ? $width / ($arr['medium_2'] - $arr['medium_1']) : 0;

		$mleft = 0;
		if (in_array($arr['pos-neg'], array('POL', 'PVL'))) {
			$mleft = $width - (($arr['conc'] - $arr['low_1']) * $w1);
		} else if ($arr['pos-neg'] == 'POM') {
			$mleft = $width * 2 - (($arr['conc'] - $arr['medium_1']) * $w2);
		} else if (in_array($arr['pos-neg'], array('POH', 'PVH'))) {
			$mleft = $width * 2 + (($arr['medium_1'] - $arr['conc']) * $w2);

			if ($mleft > $width * 3) {
				$mleft = $width * 3;
			}
		}
?>
		<div style="height:31px;min-width:<?php echo $width * 3 ?>px;text-align: left;position: relative;">
			<div style="position:relative;">
				<div style="width:<?php echo $mleft + 26 ?>px;text-align: right;color:red;font-size:9px"><?php echo number_format($arr['conc'], 3) ?></div>

			</div>
			<div style="position:relative">
				<div style="width:<?php echo $mleft + 20 ?>px;text-align: right;height:5px;">
					<div style="float:right;border-left:5px solid transparent;border-right:5px solid transparent;border-top:5px solid #000;width:0;height:0;"></div>
				</div>

			</div>
			<div style="clear:both;margin-left:15px">
				<div style="width:<?php echo $width ?>px;height:6px;background-color:#e6e608;float:left;"></div>
				<div style="width:<?php echo $width ?>px;height:6px;background-color:orange;float:left"></div>
				<div style="width:<?php echo $width ?>px;height:6px;background-color:red;float:left"></div>
			</div>
			<div style="clear:both">
				<div class="center" style="display:inline-block;width:<?php echo $width - 8 ?>px;color:black;font-size:9px"><?php echo $arr['low_2'] ?></div>
				<div class="center" style="display:inline-block;width:<?php echo $width - 8 ?>px;color:black;font-size:9px"><?php echo $arr['low_1'] ?></div>
				<div class="center" style="display:inline-block;width:<?php echo $width ?>px;color:black;font-size:9px"><?php echo $arr['medium_1'] ?></div>
			</div>
		</div>
<?php
	}
}



$margin_bottom = 0;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
	$margin_bottom = $footer_height;
	$footer_height = 0;
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
			margin-top: <?php echo $margin_top ?>cm;
			margin-bottom: <?php echo $margin_bottom ?>cm;
		}

		break,
		.break {
			page-break-after: always;
			border: 0;
		}

		* {
			font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
			font-size: 10px;
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
			text-align: right;
			<?php echo $final_adding_font ?>
		}

		.blue {
			color: blue
		}

		.red,
		.rowred td,
		tr td.rowred {
			color: red
		}

		.small_table th,
		.small_table td {
			font-size: 8pt;
			padding: 2px;
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

		#footer {
			bottom: 5px;
			border-top: 0.1pt solid #aaa;
			padding-top: 5px;
		}

		#footer,
		#footer * {
			font-size: 10px;
			line-height: 12px;
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
			line-height: 9px;
		}

		.controls td td {
			line-height: 12px;
		}

		.controls sup,
		.resultcol sup {
			font-size: 9px;
			font-weight: normal;
		}

		table.split_tbl {
			border: none !important;
		}

		table.split_tbl .title_tbl th {
			border-bottom: none;
		}

		.space_cell {
			border-bottom: none !important;
			border-top: none !important;
		}

		.bottom_notes {
			padding-top: 8px;
		}

		.bottom_notes * {
			font-size: 8pt;
		}
	</style>
</head>

<body>

	<div id="header">
		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:28%;height:60px;" valign="top">
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
					}

					if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
						$size = getimagesize($logo_dir . $logo_main);
						$max_width = 200;
						$max_height = ($size[0] > $size[1]) ? 60 : 70;
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
				<td style="width:44%;text-align: center">
					<h2 style="color:<?php echo $report['color'] ?>;font-size:18px;font-weight: normal;"><?php echo $pdf_title ?></h2>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">
					<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php if (!empty($pdf_header_text)) {
						echo apply_filters('the_content', $pdf_header_text);
					} else { ?>
						<div><?php echo $report['header_lab_name'] ?></div>
						<div><?php echo $report['header_lab_address'] ?></div>
					<?php
						if (!empty($report['header_lab_phone']) && $report['header_lab_phone'] != '000-000-000') {
							echo '<div>Phone: ' . $report['header_lab_phone'] . '</div>';
						}
						if (!empty($report['header_lab_clia'])) {
							echo '<div>CLIA ID: ' . $report['header_lab_clia'] . '</div>';
						}
					}

					?>
				</td>
			</tr>
		</table>
		<table class="small_padding_table" style="border-top:4px solid <?php echo $report['color'] ?>;border-bottom:4px solid <?php echo $report['color'] ?>">
			<tr>
				<td style="width:30%">
					<h5>CLINIC INFORMATION</h5>
				</td>
				<td style="width:39%">
					<h5>PATIENT INFORMATION</h5>
				</td>
				<td>
					<h5>SPECIMEN INFORMATION</h5>
				</td>
			</tr>
			<tr>
				<td style="border-right:1px solid #ddd">
					<table class="noboder small_table">
						<tr>
							<td style="width:23%">Name:</td>
							<td><?php echo $report['requesting_practice'] ?></td>
						</tr>
						<tr>
							<td>Address:</td>
							<td><?php echo $clinic_address ?></td>
						</tr>
						<?php
						if (tr_get_option('ci_pdf_data', 'pdf_show_clinic_phone') && !empty($report['cli_om-phone'])) {
						?>
							<tr>
								<td>Phone:</td>
								<td><?php echo htmlspecialchars($report['cli_om-phone']) ?></td>
							</tr>
						<?php
						}
						?>
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
				<td style="border-right:1px solid #ddd;padding-left:10px;">
					<table class="noboder small_table patientblocktbl">
						<tr>
							<td style="width:20%">Name:</td>
							<td colspan="2" style="max-width:200px;word-wrap:break-word">
								<?php echo tr_pdd_capitalized($report['patient_name']); ?></td>
						</tr>
						<tr>
							<td>DOB:</td>
							<td colspan="<?php echo $hide_patient_phone ? 2 : 1; ?>">
								<?php echo tr_pdd_display_date($report['patient_dob']) ?>
							</td>
							<?php if (!$hide_patient_phone) { ?>
								<td>
									<span>Gender:</span>
									<span><?php echo htmlspecialchars($report['pa_gender']) ?></span>
								</td>
							<?php } ?>

						</tr>
						<?php if (!$hide_patient_phone) { ?>
							<tr>
								<td>Phone:</td>
								<td colspan="2">
									<?php echo $report['pa_phone'] ?>
								</td>
							</tr>
						<?php } else {
						?>
							<tr>
								<td>Gender:</td>
								<td colspan="2">
									<?php echo htmlspecialchars($report['pa_gender']) ?>
								</td>
							</tr>
						<?php
						} ?>
						<?php if (!$hide_patient_address) { ?>
							<tr>
								<td>Address:</td>
								<td colspan="2" class="smalltext"><?php echo $patient_adress ?></td>
							</tr>
						<?php } ?>
						<?php
						if (tr_get_option('ci_pdf_data', 'pcr_patient_fvaccine') && !empty($report['pa_fully_vaccinated'])) {
						?>
							<tr>
								<td colspan="2">Vaccination Status: <?php echo $report['pa_fully_vaccinated'] == 'yes' ? 'Yes' : 'No' ?></td>
							</tr>
						<?php
						}
						if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) { ?>
							<tr>
								<td>Passport:</td>
								<td colspan="2"><?php echo htmlspecialchars($report['pa_passport']) ?></td>
							</tr>
						<?php }

						if (tr_get_option('ci_pdf_data', 'show_pa_mrnasid') && !empty($report['pa_mrn'])) { ?>
							<tr>
								<td>Patient ID:</td>
								<td><?php echo htmlspecialchars($report['pa_mrn']) ?></td>
							</tr>
						<?php }
						?>
						<?php if ($show_location && !empty($report['pa_location'])) { ?>
							<tr>
								<td>Loc:</td>
								<td colspan="2"><?php echo htmlspecialchars($report['pa_location']) ?></td>
							</tr>
						<?php } ?>
					</table>
				</td>
				<td style="padding-left:10px;">
					<table class="noboder small_table" style="margin-bottom:1px;">
						<tr>
							<td style="width:50%">Accession:</td>
							<td><?php echo $report['assen'] ?></td>
						</tr>
						<?php
						if (!empty($report['oldreq']) && tr_get_option('ci_pdf_data', 'pcrpdf_show_ref')) {
						?>
							<tr>
								<td>Order:</td>
								<td class="oldreq">
									<?php echo $report['oldreq'] ?>
								</td>
							</tr>
						<?php
						}
						?>
						<tr>
							<td>Date Collected:</td>
							<td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<tr>
							<td>Date Received:</td>
							<td><?php echo tr_pdd_display_date($report['received_in_lab'], $showdatetime ? 'm/d/Y H:i' : 'm/d/Y', false, true) ?></td>
						</tr>
						<tr>
							<td>Date Reported:</td>
							<td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<?php
						if (tr_get_option('ci_pdf_data', 'report_st_top')) {
						?>
							<tr>
								<td>Specimen Type:</td>
								<td><?php echo $report['sample_type'] ?></td>
							</tr>
						<?php
						}
						?>
						<?php
						if (!empty($report['dagnostic_codes1']) && tr_get_option('ci_pdf_data', 'pcrpdf_show_diagnostic')) {
						?>
							<tr>
								<td>Diagnostic code:</td>
								<td><?php echo $report['dagnostic_codes1'] ?></td>
							</tr>
						<?php
						}

						if (!empty($pr_code) && $pr_code !== '0') {
							//87635, 99211 for Vir
						?>
							<tr>
								<td>Procedural codes:</td>
								<td><?php echo $pr_code ?></td>
							</tr>
						<?php
						}
						if (tr_get_option('ci_pdf_data', 'show_ehr')) {
						?>
							<tr>
								<td>EHR:</td>
								<td><?php echo $report['p_ehr'] ?></td>
							</tr>
						<?php
						}
						?>
					</table>
				</td>
			</tr>
		</table>

	</div>
	<?php
	if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
		echo stripslashes($footer_html);
	}

	if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
		include('rejected_default.php');
	} else {
		$npos = 1;
		if (!$hide_controls || $always_show_controls) {
	?>
			<table class="controls" style="margin-top:0;margin-bottom:15px">
				<tr>
					<td style="width:32%;border:2px solid #000">
						<div style="display:inline-block;width:80px;border-bottom:1px solid #000;font-weight: bold;font-size:14px;">Controls</div>
						<table class="nopadding" style="line-height: 12px">
							<?php if ($remove_crossed == false) { ?>
								<tr>
									<td>Patient Extraction Control <sup><?php echo $npos++ ?></sup></td>
									<td class="right">PASS</td>
								</tr>
								<tr>
									<td>Endogenous Positive Control <sup><?php echo $npos ?></sup></td>
									<td class="right">PASS</td>
								</tr>
							<?php } ?>
							<tr>
								<td>Pathogen Positive Control <sup><?php echo $npos++ ?></sup></td>
								<td class="right">PASS</td>
							</tr>
							<tr>
								<td>Pathogen Negative Control <sup><?php echo $npos++ ?></sup></td>
								<td class="right">PASS</td>
							</tr>
						</table>
					</td>
					<td style="padding-left:20px;line-height: 16px;">
						<?php
						$npos = 1;
						if ($remove_crossed == false) { ?>
							<div class="font8">(<?php echo $npos++ ?>) Endogenous control confirms sample collection, DNA/RNA extraction, and assay enzyme activity</div>
						<?php } ?>
						<div class="font8">(<?php echo $npos++ ?>) Positive control is synthetic inactive pathogen</div>
						<div class="font8">(<?php echo $npos++ ?>) Negative control contains primers, probe, and enzymes with no DNA/RNA template</div>
						<?php
						if ($show_controls_negative) {
							echo '<div class="font8">(' . ($npos++) . ') Reference Range is negative</div>';
						}
						?>
						<?php if ($remove_crossed == false) { ?>
							<div class="font8">(<?php echo $npos++ ?>) A "Detected" result indicates the presence of a pathogen (99.99% confidence) above the assay cutoff.</div>
							<div class="font8">Assay cutoff is represented by CFU (bacteria), PFU (viruses) or Copy Number (DNA)</div>
						<?php } ?>
					</td>
				</tr>
			</table>

			<?php
		}
		//summary
		if (count($found_root_groups) > 1) {
			ob_start();

			foreach ($drug_groups as $groupid => $groupdata) {
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
					}
					if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVL', 'PVH'))) {
			?>
						<tr class="rowred">
							<td><?php echo htmlspecialchars($arr['drug']) ?></td>
							<td class="center upper"><?php echo htmlspecialchars($pos_neg_vls[$arr['pos-neg']]) ?></td>
							<?php if (!$groupdata['is_covid'] && !$has_antigen) { ?>
								<td class="center">
									<?php
									if (in_array($arr['pos-neg'], array('POL', 'POH', 'POM', 'PVL', 'PVH'))) {
										trshow_chartbar($arr);
									} else {
										echo htmlspecialchars($arr['conc']);
									}
									?>
								</td>
							<?php } ?>
							<td><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
						</tr>
					<?php
					}
					$has_report = true;
				}
				$drug_content = ob_get_clean();
				if (!empty($drug_content)) {
					$type_text = '(Low/Medium/High)';
					if ($detected_type || strtoupper($groupdata['name']) == 'RESPIRATORY') {
						$type_text = 'reported as detected versus not detected';
					}

					$group_tt = $groupdata['name'];
					if (stripos($groupdata['name'], 'panel') === false) {
						$group_tt = $groupdata['name'] . ' Results';
					}
					?>
					<tr class="headernoborder">
						<th colspan="<?php echo $groupdata['is_covid'] || $has_antigen ? 3 : 4 ?>" class="center upper" style="border:none;font-size:14px">
							SUMMARY <?php echo htmlspecialchars($group_tt) ?>
							<?php if (!empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
								<span class="sample_type">Specimen: <?php echo $groupdata['sample_type'] ?></span>
							<?php } ?>
							<?php
							if (function_exists('trpdd_pcr_get_specimen_source')) {
								$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
								if (!empty($specimen_source)) {
									echo '<span class="sample_type" style="float:right;text-align:right">Specimen Source: ' . $specimen_source . '</span>';
								}
							}
							?>
						</th>
					</tr>
					<tr class="title_tbl">
						<th style="width:35%">Test Performed</th>
						<th class="center" style="width:18%">Lab Result
							<?php if (!$groupdata['is_covid'] && !$has_antigen) { ?>(4)<br><?php echo $type_text ?><?php } ?>
						</th>
						<?php if (!$groupdata['is_covid'] && !$has_antigen) { ?>
							<th class="center" style="width:25%">Cycle Threshold*</th>
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
				<table class="tdborder small_table valmiddle">
					<?php echo $summary; ?>
				</table>
				<div class="break"></div>
		<?php
			}
		}
		?>


		<?php
		$has_report = false;
		foreach ($drug_groups as $groupid => $groupdata) {
			$count_row = 0;
			$detected_type = true;
			if ($groupdata['parent'] > 0 && (!$groupdata['show'] || $groupdata['showed'])) continue;


			if (count($groupdata['subgroups']) > 0) {
				ob_start();

				$showed_smalltbl = false;
				$closed_smalltbl = false;
				$has_nograme = false;

				$icount = 0;
				$lastsmallcol = 0;


				foreach ($groupdata['subgroups'] as $subid => $subname) {
					$subgroupdata = isset($drug_groups[$subid]) ? $drug_groups[$subid] : false;

					if (!$subgroupdata || count($subgroupdata['tests']) == 0) continue;

					if (isset($tr_reflex_hide_groups[$subid]) && $tr_reflex_hide_groups[$subid] === true) {
						continue;
					}

					$is_UA_RESULTING = stripos($subgroupdata['name'], 'UA RESULTING') !== false;

					$is_split_tbl 	= false;
					$small_col 		= 0;
					$sub_show_value_in_result 	= isset($subgroupdata['other_options']['show_value_in_result']) && $subgroupdata['other_options']['show_value_in_result'];
					$sub_show_range_in_result 	= isset($subgroupdata['other_options']['show_range']) && $subgroupdata['other_options']['show_range'];

					if ($is_UA_RESULTING) {
						$sub_show_value_in_result = true;
					}

					$is_smalltbl = in_array(strtoupper($subname), array('GRAM NEGATIVE', 'GRAM POSITIVE', 'NO GRAM STAIN', 'FUNGI', 'ATYPICAL', 'PROTOZOA', 'OTHER'));
					if (isset($subgroupdata['other_options']['pdf_small_tbl']) && $subgroupdata['other_options']['pdf_small_tbl'] == 3) {
						$smalltbl_col = 0;
						$is_smalltbl = false;
					} else if (isset($subgroupdata['other_options']['pdf_small_tbl']) && $subgroupdata['other_options']['pdf_small_tbl'] > 0) {
						$small_col = $subgroupdata['other_options']['pdf_small_tbl'];
						$is_smalltbl = true;
					}
					if ($subgroupdata['other_options']['split_table']) {
						$is_smalltbl = false;
					}

					if (isset($_GET['debugsm'])) {
						var_dump($subname);
						var_dump($is_smalltbl);
						echo '<br><br>';
					}


					$table_class = ' is_smalltbl';
					$style = '';

					if ($is_smalltbl) {
						if (!$showed_smalltbl) {
							echo '<table><tr><td style="width:50%;padding-left:0">';
							$showed_smalltbl = true;
						}

						$icount++;


						if (($has_nograme && $icount == 3) || ($small_col == 1 && $icount > 1) || ($small_col > 0 && $lastsmallcol == $small_col)) {
							echo '<br style="line-height:4px;"/>';
						} else if ($icount == 2 && stripos($subname, 'no gram stain') !== false) {
							$has_nograme = true;
							$icount--;
							echo '<br style="line-height:4px;"/>';
						} else if ($icount == 2 || $small_col == 2) {
							echo '</td><td style="width:50%;padding-right:0">';
						} else if ($icount == 3 && count($groupdata['subgroups']) == 4) {
							echo '<br style="line-height:4px;"/>';
						} else if ($icount == 3) {
							echo '</tr><tr><td style="width:50%;padding-left:0">';
						} else if ($icount == 4) {
							echo '</td><td style="width:50%;padding-right:0">';
						} else if ($icount >= 5) {
							echo '<br style="line-height:4px;"/>';
						}
						//echo $icount;
						$lastsmallcol = $small_col;
					} else if ($showed_smalltbl) {
						echo '</td></tr></table>';
						if ($enable_utinrow) {
							echo '<br style="line-height:4px;"/>';
						}
						$closed_smalltbl = true;
						$showed_smalltbl = false;

						if ($subgroupdata['other_options']['split_table']) {
							$is_split_tbl = true;
							$table_class = ' split_tbl ';
						}
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
					if (count($groupdata['subgroups']) == 1) {
						$subname_tt .= ' Results';
					}
		?>
					<div style="<?php echo $style ?>">
						<table class="border tdborder small_table <?php echo $table_class ?>">
							<thead>
								<?php if (count($groupdata['subgroups']) > 1) { ?>
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
										}
										$count_row++;
										$pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
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
										echo '<td class="center ' . $row_class . '" >' . htmlspecialchars($pos_neg_str) . '</td>';
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
										if ($arr['te_abrev'] == 'RppH') {
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
										}
										//var_dump($pos_neg_vls);
										$count_row++;
										$pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
										$row_class 	 = in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVH', 'PVL')) ? ($report['bold_text_positive'] ? 'rowbold' : 'rowred') : '';
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

										echo '<td class="center" style="width:' . $colwidths[2] . '">' . htmlspecialchars($pos_neg_str) . '</td>';
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
				}
				if (isset($_GET['ddd33'])) {
					var_dump($showed_smalltbl);
					var_dump($closed_smalltbl);
				}
				if ($showed_smalltbl && !$closed_smalltbl) {
					echo '</td></tr></table>';
				}

				$subcontent = ob_get_clean();

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
				$has_subgroup = true;

				if ($need_break_page) {
					echo '<div class="break"></div>';
					$need_break_page = false;
				}

				?>

				<table class="noboder small_table">
					<tr class="headernoborder">
						<th style="width:<?php echo strlen($group_tt) > 33 ? '20%' : '30%' ?>;border:none"></th>
						<th colspan="4" class="center " style="border:none;font-size:14px;">
							<?php echo htmlspecialchars($group_tt) ?>
						</th>
						<th style="width:<?php echo strlen($group_tt) > 33 ? '20%' : '30%' ?>;border:none">
							<?php if (!tr_get_option('ci_pdf_data', 'report_st_top') && !empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
								<span class="sample_type">Specimen: <?php echo $groupdata['sample_type'] ?></span>
								<?php } ?>&nbsp;
							<?php
							if (function_exists('trpdd_pcr_get_specimen_source')) {
								$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
								if (!empty($specimen_source)) {
									echo '<span class="sample_type" style="float:right;text-align:right">Specimen Source: ' . $specimen_source . '</span>';
								}
							}
							?>
						</th>
					</tr>

				</table>
				<?php if ($enable_utinrow) {
					echo '<br>';
				}

				?>
				<?php
				echo $subcontent;
				?>
				<div style="clear:both;margin-bottom:2px"></div>

			<?php
			}

			ob_start();
			foreach ($groupdata['tests'] as $arr) {
				if (!$arr['detected_type']) {
					$detected_type = false;
				}


				if ($arr['antibiotic'] && $groupdata['name'] != 'Antibiotic Resistance') {
					if (!in_array($d, $antibiotic_drugs)) {
						$antibiotic_drugs[] = $d;
					}
					$all_res[$d] = $arr;
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
				}

				$count_row++;
			?>
				<tr class="<?php if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVL', 'PVH'))) echo 'rowred' ?>">
					<td><?php echo htmlspecialchars($arr['drug']) ?></td>
					<td class="center upper"><?php echo htmlspecialchars($pos_neg_vls[$arr['pos-neg']]) ?></td>
					<?php if (!$groupdata['is_covid'] && !$has_antigen) { ?>
						<td class="center">
							<?php
							if (in_array($arr['pos-neg'], array('POL', 'POH', 'POM', 'PVL', 'PVH'))) {
								trshow_chartbar($arr);
							} else {
								echo htmlspecialchars($arr['conc']);
							}
							?>
						</td>
					<?php } ?>
					<td><?php echo trpdd_filter_comments_in_pdf($arr['comments']) ?></td>
				</tr>
			<?php
				$has_report = true;
			}
			$drug_content = ob_get_clean();
			if (!empty($drug_content)) {
				$type_text = '(Low/Medium/High)';
				if ($detected_type || strtoupper($groupdata['name']) == 'RESPIRATORY') {
					$type_text = 'reported as detected versus not detected';
				}


				$group_tt = $groupdata['name'];
				if (stripos($groupdata['name'], 'panel') === false) {
					$group_tt = $groupdata['name'] . ' Results';
				}
			?>
				<div style="<?php if ($count_row > 0 && $count_row < 5) echo 'page-break-inside: avoid;' ?> padding-bottom:8px">
					<table class="tdborder small_table valmiddle">
						<tr class="headernoborder">
							<th colspan="<?php echo $groupdata['is_covid'] || $has_antigen ? 3 : 4 ?>" class="center upper" style="border:none;font-size:14px;">
								<?php echo htmlspecialchars($group_tt) ?>
								<?php if (!empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
									<span class="sample_type">Specimen: <?php echo $groupdata['sample_type'] ?></span>
								<?php } ?>
								<?php
								if (function_exists('trpdd_pcr_get_specimen_source')) {
								$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
									if (!empty($specimen_source)) {
										echo '<span class="sample_type" style="float:right;text-align:right">Specimen Source: ' . $specimen_source . '</span>';
									}
								}
								?>
							</th>
						</tr>
						<thead>
							<tr class="title_tbl">
								<th style="width:35%">Test Performed</th>
								<th class="center" style="width:18%">Lab Result
									<?php if (!$groupdata['is_covid'] && !$has_antigen) { ?>(4)<br><?php echo $type_text ?><?php } ?>
								</th>
								<?php if (!$groupdata['is_covid'] && !$has_antigen) { ?>
									<th class="center" style="width:25%">Cycle Threshold*</th>
								<?php } ?>
								<th class="center">Comments</th>
							</tr>
						</thead>
						<?php
						echo $drug_content;
						?>

					</table>
					<?php
					if (!empty($groupdata['comments'])) {
						echo '<div>' . trpdd_filter_comments_in_pdf($groupdata['comments']) . '</div>';
					}
					?>
				</div>
	<?php

			}

			if ((!empty($drug_content) || !empty($subcontent)) && !empty($groupdata['comments'])) {
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
		}

		$pdf_notes = trpdd_get_pdf_note($report, 'pcr');
		if (!empty($pdf_notes)) {
			echo '<div>';
			echo trpdd_filter_comments_in_pdf($pdf_notes);
			echo '</div>';
		}
	} //end if rejected

	include('_approved_by.php');

	if (!empty($notes_pos_neg)) {
		echo '<div>';
		echo trpdd_filter_comments_in_pdf($notes_pos_neg);
		echo '</div>';
	}


	if (!empty($report['notes_report'])) {
		echo '<div style="padding-top:6px" class="notes_report">';
		//echo '<span style="font-weight:bold;padding:0;line-height:22px;">Notes: </span>';
		echo trpdd_filter_comments_in_pdf($report['notes_report']);
		echo '</div>';
	}


	if (!empty($notes['result_image'])) {

		echo '<div class="break"></div>';
		echo '<div style="background-color: white; z-index: 2;position:fixed;height:1200px">';
		echo '<img src="' . $logo_dir . '/' . $notes['result_image'] . '" style="width:100%;margin:0 auto;height:auto"/>';
		echo '</div>';
	}
	?>


	<?php
	if (isset($_REQUEST['html'])) {
		echo stripslashes($footer_html);
	}
	?>

	<?php include('_pdf_pagination.php'); ?>

</body>

</html>
