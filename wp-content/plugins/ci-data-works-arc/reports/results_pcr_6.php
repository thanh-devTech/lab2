<?php
/*
Name: CLINICAL PHARMACY GUIDANCE
*/
global $wpdb, $current_lab_id, $ci_cutoffs, $report;

$no_collection_date = (empty($report['collection_date']) || $report['collection_date'] == '0000-00-00' || $report['collection_date'] == '0000-00-00 00:00:00') && $report['doc'] == '0000-00-00';
$notes = get_option('ci_notes_result_pcr');

$text_PRELIMINARY = tr_pdd_get_result_status($report['final_results'], true);

if (isset($_REQUEST['debug']) && isset($_REQUEST['html'])) {
	var_export($report);
	echo '<br><br>';
	echo 'Tests:';
	var_export($in_panel_tests_requested);
	echo '<br><br>';
	echo '$all_res:';
	var_export($all_res);
	echo '<br><br>';
	echo '$group_ids: ';
	var_dump($group_ids);
	echo '<br><br>';
	echo '$drug_groups: ';
	var_dump($drug_groups);
	echo '<br><br>';
}


$footer_height = 1.4;
$margin_top = 5.2;
$show_notes_in_header = false;
if (!empty($report['notes_report']) && strlen($report['notes_report']) < 140) {
	$show_notes_in_header = true;
}
if ((strlen($patient_adress) > 32 || !empty($report['medical_director']) || !empty($report['lab_director'])) && $show_notes_in_header) {
	$margin_top = 5.4;
}

global $pdf_header_text;
$footer_text = trpdd_get_footer_text($report, 'pcr');
$margin_bottom = 0.3;
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
			font-size: 11px;
		}

		table .sample_type {
			font-size: 8pt;
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
			padding: 4px;
		}

		table td {
			padding: 2px;
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

		.red {
			color: red
		}

		table tr.headernoborder th {
			border: 3px solid #000;
			border-left: 0;
			border-right: 0;
			font-weight: 600;
			font-size: 12px;
			padding-top: 4px;
			padding-bottom: 4px
		}

		.disclaimer_div {
			padding-top: 8px;
		}

		table.noboder,
		table tr.noborder td,
		table tr.noborder th {
			border: none !important;
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
			line-height: 12px;
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
			line-height: 13px;
			margin: 0;
		}

		.header_tbl th {
			text-transform: uppercase;
			background: #555;
			text-align: center;
			height: 16px;
			color: #fff;
			font-size: 11px;
			padding: 1px;
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

		p {
			margin: 2px 0
		}

		.clinical_pharmacy_guidance,
		.clinical_pharmacy_guidance p,
		.clinical_pharmacy_guidance td {
			font-size: 8px;
			line-height: 12px;
		}

		table.tabledtline td {
			line-height: 11px;
			padding: 0px 2px;
		}

		table.tabledtline td.tname {
			height: 14px;
		}

		table .utf8,
		table.utf8 td {
			font-family: DejaVu Sans;
		}

		table.center td {
			text-align: center;
		}

		table.center td.left {
			text-align: left;
		}
	</style>
</head>

<body>

	<div id="header">
		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:30%;height:50px;" valign="top">
					<?php
					$logo_path = $logo_dir . $report['lab_logo'];
					$logo_main = $report['lab_logo'];


					$logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

					$has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
					if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
						$logo_main = $logo_second;
						$logo_second = '';
					}

					if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
						$size = getimagesize($logo_dir . $logo_main);
						$max_width = 250;
						$max_height = ($size[0] > $size[1]) ? 50 : 50;
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
				<td style="width:42%">
					<h2 style="color:<?php echo $report['color'] ?>;font-size:18px;font-weight: normal;text-align: center;margin-top:2px;"><?php echo $pdf_title ?></h2><?php if (tr_get_option('ci_pdf_data', 'centerfinalreport')) { ?>
						<div class="center_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php } ?>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">
					<?php if (!tr_get_option('ci_pdf_data', 'centerfinalreport')) { ?>
						<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php } ?>
					<?php
					if (!empty($pdf_header_text)) {
						echo apply_filters('the_content', $pdf_header_text);
					} else {
					?>
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
						if (!empty($report['medical_director'])) {
							echo '<div>Medical Director: ' . $report['medical_director'] . '</div>';
						} else if (!empty($report['lab_director'])) {
							echo '<div>Lab Director: ' . $report['lab_director'] . '</div>';
						}
						?>
					<?php
					}
					?>
				</td>
			</tr>
		</table>
		<div style="border-top:4px solid <?php echo $report['color'] ?>;border-bottom:4px solid <?php echo $report['color'] ?>">
			<table class="small_padding_table">
				<tr>
					<td style="width:32%">
						<h5>CLINIC INFORMATION</h5>
					</td>
					<td style="width:37%">
						<h5 style="padding-left:10px">PATIENT INFORMATION</h5>
					</td>
					<td>
						<h5 style="padding-left:10px">SPECIMEN INFORMATION</h5>
					</td>
				</tr>
				<tr>
					<td style="border-right:1px solid #ddd">
						<table class="noboder">
							<tr>
								<td style="width:23%">Name:</td>
								<td><?php echo $report['requesting_practice'] ?></td>
							</tr>
							<tr>
								<td>Address:</td>
								<td><?php echo $clinic_address ?></td>
							</tr>
							<tr>
								<td>Provider:</td>
								<td><?php echo htmlspecialchars($report['physician']) ?></td>
							</tr>

						</table>
					</td>
					<td style="border-right:1px solid #ddd;padding-left:10px;">
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
							<tr>
								<td>Phone:</td>
								<td colspan="3"><?php echo $report['pa_phone'] ?></td>
							</tr>
							<tr>
								<td>Address:</td>
								<td colspan="3" class="smalltext"><?php echo $patient_adress ?></td>
							</tr>
							<tr>
								<td>ID:</td>
								<td colspan="3"><?php echo htmlspecialchars($report['pa_code']) ?></td>
							</tr>
						</table>
					</td>
					<td style="padding-left:10px;">
						<table class="noboder">
							<tr>
								<td style="width:50%">Accession Number:</td>
								<td><?php echo $report['assen'] ?></td>
							</tr>
							<tr>
								<td>Date Collected:</td>
								<td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
							</tr>
							<tr>
								<td>Date Received:</td>
								<td><?php echo tr_pdd_display_date($report['received_in_lab']) ?></td>
							</tr>
							<?php /*
                    <tr>
                        <td>Date Accessioned:</td>
                        <td><?php echo tr_pdd_display_date($report['collection_date'])?></td>
                    </tr>
					 */ ?>
							<tr>
								<td>Date Reported:</td>
								<td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
							</tr>
							<?php if (tr_get_option('ci_pdf_data', 'report_st_top')) { ?>
								<tr>
									<td>Specimen Type:</td>
									<td><?php echo $report['sample_type'] ?></td>
								</tr>
							<?php } ?>
						</table>
					</td>
				</tr>
			</table>
		</div>
		<?php
		if ($show_notes_in_header) {
			echo '<div >';
			echo '<span style="font-weight:bold;padding:0;line-height:22px;">Notes: </span>';
			echo nl2br(stripslashes($report['notes_report']));
			echo '</div>';
		}
		?>
	</div>

	<?php

	ob_start();
	?>

	<div id="footer">
		<?php
		echo $footer_text;
		?>
	</div>

	<?php
	$footer_html = ob_get_clean();

	if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
		echo stripslashes($footer_html);
	}

	if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
		include('rejected_default.php');
	} else {

		$showed_pharmacy_guidance = false;

		if (isset($result_settings['start_rm_pha_guid']) && !empty($result_settings['start_rm_pha_guid']) && strtotime($result_settings['start_rm_pha_guid']) <= strtotime($report['approve_date'])) {
			$showed_pharmacy_guidance = true;
		}

		if (!isset($result_settings['cli_pha_guid']) || !$result_settings['cli_pha_guid']) {
			$showed_pharmacy_guidance = true;
		}

		if (isset($_GET['shownewabx'])) {
			$showed_pharmacy_guidance = true;
		}

		if (isset($_GET['debug32'])) {
			var_dump($drug_groups);
		}

		$showed_pgroupbefore = false;
		foreach ($drug_groups as $groupid => $groupdata) {

			if (empty($groupdata['name'])) {
				//  continue;
			}
			if ($groupdata['parent'] > 0) {
				continue;
			}

			if (isset($groupdata['other_options']['uav2']) && $groupdata['other_options']['uav2']) {
				include('pcr_uav2.php');
				continue;
			}


			$detected_pathogens    = array();
			$notdetected_pathogens = array();

			$detected_markers    = array();
			$notdetected_markers = array();

			$Indeterminate = array();
			$canceled_list      = array();

			if (isset($_GET['debug2'])) {
				var_dump($groupdata['name']);
				var_dump($groupdata['subgroups']);
				echo '<br><br>';
			}



			if (count($groupdata['subgroups']) == 0 && count($groupdata['tests']) > 0) {
				$groupdata['subgroups'] = array($groupdata['id'] => $groupdata['name']);
			} else if (count($groupdata['tests']) > 0) {
				//$groupdata['subgroups'][$groupdata['id']] = $groupdata['name'];
			}

			if (count($groupdata['subgroups']) > 0) {

				if ($showed_pgroupbefore) {
					echo '<div class="break"></div>';
				}
				$showed_pgroupbefore = true;
				foreach ($groupdata['subgroups'] as $subgroupid => $subgroupname) {
					$subgroupdata = $drug_groups[$subgroupid];

					foreach ($subgroupdata['tests'] as $arr) {
						$has_report            = true;
						if ($arr['pos-neg'] == 'IND' || $arr['pos-neg'] == 'CAN') {
							$Indeterminate[] = $arr;
						} else if (stripos($subgroupname, 'Antibiotic Resistance') !== false) {

							if ($arr['pos-neg'] == 'POS') {
								$detected_markers[] = $arr;
							} else if ($arr['pos-neg'] == 'CAN') {
								$canceled_list[] = $arr;
							} else {
								preg_match("/\(([^\)]+)\)/", $arr['te_name'], $matches);
								if (!empty($matches[1])) {
									$arr['te_name'] = trim($matches[1]);
								}
								$notdetected_markers[] = $arr['te_name'];
							}
						} else {
							if (stripos($subgroupname, 'gram') !== false) {
								$arr['gram'] = stripos($subgroupname, 'negative') !== false ? '-' : '+';
							} else if (stripos($subgroupname, 'Atypical') !== false) {
								$arr['gram'] = 'Atypical';
							} else {
								if (stripos($subgroupname, ' panel') !== false) {
									$subgroupname = str_ireplace(' panel', '', $subgroupname);
								}
								$arr['gram'] = $subgroupname;
							}

							if ($arr['pos-neg'] == 'POS') {
								if (isset($_GET['onlyone']) && count($detected_pathogens) == 1) {
									continue;
								}

								$detected_pathogens[] = $arr;
							} else if ($arr['pos-neg'] == 'CAN') {
								$canceled_list[] = $arr;
							} else {
								$notdetected_pathogens[] = $arr['te_name'];
							}
						}
					}
				}

				usort($detected_pathogens, function ($a, $b) {
					if ($b['conc'] == $a['conc']) return 0;
					return ($b['conc'] < $a['conc']) ? 1 : -1;
				});

				usort($detected_markers, function ($a, $b) {
					if ($b['conc'] == $a['conc']) return 0;
					return ($b['conc'] < $a['conc']) ? 1 : -1;
				});

	?>



				<table class="tdborder small_table">
					<tr class="headernoborder">
						<th colspan="4" class="center " style="border:none;font-size:14px;">
							<?php echo htmlspecialchars($groupdata['name'] . ' Results') ?>
							<?php if (!empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
								<span class="sample_type">Sample Type: <?php echo $groupdata['sample_type'] ?></span>
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
				</table>

				<?php
				if ($has_empty_reflex && stripos($groupdata['name'], 'uti') !== false) {
					echo '<p>';
					echo 'Antibiotic Resistance Gene testing cancelled due to negative and/or indeterminate results for the corresponding pathogen(s).';
					echo '</p>';
				}
				?>

				<table style="border:0">
					<tr>
						<td style="width:70%;border-top:0;padding-left:0;padding-top:0;vertical-align: top">
							<table class="tdborder tabledtline" style="border:0">
								<tr class="header_tbl">
									<th colspan="<?php echo !tr_get_option('ci_pdf_data', 'removePathogensarrow') ? 4 : 3 ?>" class="center" style="height: 24px;font-size:14px;background:#213963">** Detected Pathogens **</th>
								</tr>
								<?php if (count($detected_pathogens) > 0) { ?>
									<tr>
										<th style="width:50%" class="center">Bacterial Pathogens</th>
										<th style="width:14%" class="center">Result</th>
										<th style="width:18%" class="center">Classification</th>
										<?php if (!tr_get_option('ci_pdf_data', 'removePathogensarrow')) { ?>
											<th style="width:18%;padding:0" class="center">Observed Concentration*</th>
										<?php } ?>
									</tr>
									<?php
									$showed_arrow = false;
									$countdp      = count($detected_pathogens);
									foreach ($detected_pathogens as $arr) {
										$gram_group = 'gram (' . $arr['gram'] . ')';
										if (empty($arr['gram'])) {
											$gram_group = '';
										} else if (in_array(strtolower($arr['te_name']), array(
											'ureaplasma urealyticum',
											'mycoplasma hominis'
										))) {
											if ($arr['parent'] > 0) {
												$gram_group = 'Atypical';
											} else {
												$gram_group = 'Atypical';
											}
										} else if (strlen($arr['gram']) > 1) {
											$gram_group = $arr['gram'];
										}
										if (isset($_GET['debug'])) {
											$gram_group .= $arr['conc'];
										}
									?>
										<tr>
											<td class="red tname"><?php echo $arr['te_name'] ?></td>
											<td class="center red">Detected</td>
											<td class="center red"><?php echo $gram_group ?></td>
											<?php
											if (!$showed_arrow && !tr_get_option('ci_pdf_data', 'removePathogensarrow')) {
												$showed_arrow = true;
												$height = $countdp * 16;
											?>
												<td rowspan="<?php echo $countdp ?>" style="padding:0;overflow: hidden;height:<?php echo $height ?>px">
													<?php
													if ($countdp > 1) {

													?>
														<img style="width:40px;height:<?php echo $height ?>px;margin:0;margin-left:24px" src="<?php echo TRCPDD_PATH . 'images/' . ($countdp == 2 ? 'pcr4_arrow2.png' : 'pcr4_arrow.png') ?>" />
													<?php
													} else {
														echo '<div class="center">N/A</div>';
													}
													?>
												</td>
											<?php } ?>
										</tr>
									<?php
									}
								} else {
									?>
									<tr>
										<td colspan="4" style="font-weight: bold;font-size:12px;padding:4px">
											No pathogens detected that exceeded the threshold for positivity. See below for full list of pathogens and resistance genes tested.
										</td>
									</tr>
								<?php
								}
								?>
							</table>


						</td>
						<td></td>
						<td style="width:29%;vertical-align: top;padding:0">
							<?php if (count($detected_markers) > 0) { ?>
								<table class="tdborder">
									<tr class="header_tbl">
										<th class="center" style="height:24px;font-size:10px;background:#213963">Detected Antibiotic Resistance Genes</th>
									</tr>
									<tr>
										<td class="red">
											<?php foreach ($detected_markers as $arr) {
												echo '<div>' . $arr['te_name'] . '</div>';
												if (isset($_GET['debug'])) {
													echo  $arr['conc'];
												}
											} ?>
										</td>
									</tr>
								</table>
							<?php } ?>
						</td>
					</tr>
				</table>
				<?php if (!tr_get_option('ci_pdf_data', 'removePathogensarrow')) { ?>
					<p style="font-size:10px">*Pathogens are listed in order of high to low based on the observed cycle threshold (CT) value at the completion of testing. Please note that CT values are based on Polymerase Chain Reaction (PCR) which amplyfies nucleic acids irrespective of the biological status of the pathogen.</p>
				<?php } ?>


				<?php
				if (!$showed_pharmacy_guidance) {
					ob_start();
					$showed_pharmacy_guidance = true;

					$ionname = 'Ion Diagnostics';
					$labname = $wpdb->get_var("select lab_dba from ci_labs where lab_ID = {$current_lab_id}");

					$report['clinical_pharmacy_guidance'] = str_replace($ionname, $labname, $report['clinical_pharmacy_guidance']);

					/*
					global $has_table_data_pharmacy;
					$has_table_data_pharmacy = false;
					$newtxt = preg_replace_callback("/\<td\>([^<]*)\<\/td\>/", function ($matches) {
						global $has_table_data_pharmacy;
						if (strlen($matches[1]) > 2) {
							$has_table_data_pharmacy = true;
							return $matches[0];
						}
						$has_table_data_pharmacy = false;
						return '';
					}, $report['clinical_pharmacy_guidance']);

					if (!$has_table_data_pharmacy) {
						$newtxt = preg_replace('/\<table.*\<\/table\>/','',$newtxt);
					
						$report['clinical_pharmacy_guidance'] = $newtxt;
					}*/


				?>
					<div style="page-break-inside:avoid;width:100%;margin-bottom:10px;margin-top:10px">
						<table class="tdborder">
							<tr class="header_tbl">
								<th class="center" style="background:#315795;border-color:#315795">
									<?php echo tr_get_option('ci_pdf_data', 'cliphagui_comment') ? 'Comments' : 'Clinical Pharmacy Guidance' ?>
								</th>
							</tr>

							<tr>
								<td>
									<div class="clinical_pharmacy_guidance">
										<?php echo $report['clinical_pharmacy_guidance']; ?>
									</div>
									<?php
									if (
										!tr_get_option('ci_pdf_data', 'cliphagui_comment')
										&& stripos($report['clinical_pharmacy_guidance'], 'For additional questions') === false
										&& strtotime($report['doc']) < strtotime('2022-10-30')
									) {
									?>
										<div style="font-weight: bold;font-size:10px;margin-bottom:5px">
											For additional questions, consultation, or to discuss patient factors that may
											impact treatment, contact the clinical pharmacist at pharmconsult@iondiagnostics.com
											or 616-327-7800.
										</div>
									<?php
									}

									if (
										!tr_get_option('ci_pdf_data', 'cliphagui_comment')
										&& stripos($report['clinical_pharmacy_guidance'], 'DISCLAIMER:') === false
										&& strtotime($report['doc']) < strtotime('2022-10-30')
									) {
									?>
										<div style="font-size:10px!important;font-style: italic">DISCLAIMER: Recommendations are
											based solely on the pathogen and resistance results shown above, which represent a
											snapshot of the patient’s microbiota. Additional patient factors must be considered
											before prescribing treatment, including but not limited to: age, allergies,
											comorbidities, potential drug interactions, renal/hepatic function, and cost.
										</div>
									<?php
									}
									?>
								</td>
							</tr>

						</table>
					</div>
				<?php
					$pharmacy_guidance_html = ob_get_clean();
				}

				if (!$show_pharmacy_guidance_bottom) {
					echo $pharmacy_guidance_html;
				}
				?>

				<?php if (count($Indeterminate) > 0) { ?>
					<table>
						<tr>
							<td style="padding:0;">
								<table class="tdborder">
									<tr class="header_tbl">
										<th colspan="2" class="center" style="background:#4880db;border-color:#4880db">Indeterminate Results</th>
									</tr>
									<tr>
										<th style="width:29%">Pathogen or Marker</th>
										<th>Comments</th>
									</tr>
									<?php
									foreach ($Indeterminate as $arr) {
										if ($arr['pos-neg'] == 'CAN') {
											$arr['comments'] = 'Cancelled - ' . $arr['comments'];
										}
									?>
										<tr>
											<td><?php echo $arr['te_name'] ?></td>
											<td style="padding:2px;font-size:10px"><?php echo !empty($arr['comments']) ? $arr['comments'] : '&nbsp;' ?></td>
										</tr>
									<?php
									}
									?>
								</table>
							</td>
						</tr>
					</table>
				<?php } ?>


				<table style="margin-bottom:7px">
					<tr>
						<td style="padding:0">
							<table class="tdborder">
								<tr class="header_tbl">
									<th colspan="2" class="center" style="background:#5393fc;border-color:#5393fc">Pathogen <?php if (count($notdetected_markers) > 0) echo '& Resistance Genes' ?> Not Detected</th>
								</tr>
								<tr>
									<td colspan="2" style="padding:0">
										<table class="noboder">
											<?php
											if (count($notdetected_pathogens) > 0) {
												echo '<tr class="noborder"><td style="width:94px;vertical-align: top;padding-right:0">Pathogens:</td><td style="padding-left:0">' . implode(', ', $notdetected_pathogens) . '</td></tr>';
											}
											if (count($notdetected_markers) > 0) {
												echo '<tr class="noborder"><td style="vertical-align: top;padding-right:0">Resistance Genes:</td><td style="padding-left:0">' . implode(', ', $notdetected_markers) . '</th></tr>';
											}
											?>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<?php

				if (!empty($groupdata['comments'])) {
					$groupdata['comments'] = trim($groupdata['comments']);
					echo '<div style="margin-top:5px">' . nl2br($groupdata['comments']) . '</div>';
					if (stripos($groupdata['comments'], 'onlyshowthis') > 0) {
						$only_show_group_comment = true;
					}
				}
			} else {

				//normal

				$has_report            = true;

				if (empty($groupdata['tests'])) {
					continue;
				}
				$count_row = 0;
				ob_start();
				foreach ($groupdata['tests'] as $arr) {
					if (!$arr['detected_type']) {
						$detected_type = false;
					}
					$count_row++;
					$pos_neg_vls = $pos_neg_arr;
					if ($groupdata['is_covid']) {
						$pos_neg_vls['POS'] = 'Positive';
						$pos_neg_vls['NEG'] = 'Negative';
					}
					$pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
					if ($arr['conc'] == '100') {
						$arr['conc'] = '';
						$pos_neg_str = 'Inconclusive';
						if (empty($arr['comments'])) {
							$arr['comments'] = 'Unable to accurately quantify genetic material for the individual microorganism.';
						}
					}
				?>
					<tr class="<?php if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM'))) echo 'rowred' ?>">
						<td><?php echo htmlspecialchars($arr['drug']) ?></td>
						<td class="center upper"><?php echo htmlspecialchars($pos_neg_str) ?></td>
						<?php if (!$groupdata['is_covid'] && !$hidescresultpcr) { ?>
							<td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
						<?php } ?>
						<td <?php if ($groupdata['is_covid'] || $hidescresultpcr) echo 'colspan=2' ?>><?php echo nl2br($arr['comments']); ?></td>
					</tr>
				<?php
					$has_report = true;
				}
				$drug_content = ob_get_clean();
				if (!empty($drug_content)) {

					$type_text = '(Low/Medium/High)';

					$has_showed_panel = true;
					$panel_display = stripos($groupdata['name'], 'panel') > 0 ? $groupdata['name'] : $groupdata['name'] . ' Results';
				?>
					<div style="<?php if ($count_row > 0 && $count_row < 5) echo 'page-break-inside: avoid;' ?>padding-bottom:8px">
						<table class="tdborder small_table">
							<tr class="headernoborder">
								<th colspan="4" class="center" style="border:none;font-size:14px;">
									<?php echo htmlspecialchars($panel_display) ?>
									<?php if (!empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
										<span class="sample_type">Sample Type: <?php echo $groupdata['sample_type'] ?></span>
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
									<th style="width:35%"><?php echo $group_title ?></th>
									<th class="center" style="width:20%">Result
										<?php if (!$groupdata['is_covid'] && !$hidescresultpcr) {
											echo '(4)<br>' . $type_text;
										} ?>
									</th>
									<?php if (!$groupdata['is_covid'] && !$hidescresultpcr) { ?>
										<th class="center" style="width:20%">Cycle Threshold*</th>
									<?php } ?>
									<th <?php if ($groupdata['is_covid'] || $hidescresultpcr) echo 'colspan=2' ?> class="center">Comments</th>
								</tr>
							</thead>
							<?php
							echo $drug_content;
							?>

						</table>
						<?php
						if (!empty($groupdata['comments'])) {
							echo '<div>' . nl2br($groupdata['comments']) . '</div>';
							if (stripos($groupdata['comments'], 'onlyshowthis') > 0) {
								$only_show_group_comment = true;
							}
						}
						?>
					</div>
			<?php

				}
			}
		}

		if ($show_pharmacy_guidance_bottom) {
			echo '<br>';
			echo $pharmacy_guidance_html;
		}

		$pdf_notes = trpdd_get_pdf_note($report, 'pcr');


		if (!empty($pdf_notes)) {
			echo '<div  class="bottom_notes">';
			echo trpdd_filter_comments_in_pdf($pdf_notes);
			echo '</div>';
		}


		if (!empty($notes['notcovid']) && $except_covid) {
			echo '<div style="font-size:10px!important;" class="notcovid">';
			echo nl2br(stripslashes($notes['notcovid']));
			echo '</div>';
			?>
			<p style="font-size:10px!important;">Please note. Rare mutation(s) that could be linked to antibiotic resistance markers may not be detected. When the infection level at the time of sample collection is below the cutoff of the assay, the results may be determined as “Not Detected.” Thus, the results should be interpreted as a snapshot in time of patient microbiota. This test does not determine how pathogen(s) interact within the patient’s microbiota. Repeat testing and/or specimen collection may be required throughout treatment. Other genetic and non-genetic factors that are not tested by this assay can affect the management and sensitivity of drugs. Results should be reviewed by a physician to determine a proper treatment plan and/or drug regiment based upon other clinical findings and familial history for the administration of specific drug(s). Test results do not rule out the possibility that this individual could be a carrier of other pathogens not detected by this test.</p>
	<?php
		}
	} //end if rejected

	if (!empty($report['re_notes'])) {
		echo '<div style="margin-top:5px;margin-bottom:5px;">' . nl2br(stripslashes($report['re_notes'])) . '</div>';
	}


	if ($report['notes_report_dropdown'] > 0) {
		$newnote = $wpdb->get_var("select Description from ci_batch_report_comments where ID = {$report['notes_report_dropdown']}");
		echo '<div>';
		echo nl2br($newnote);
		echo '</div>';
	}
	?>

	<?php
	if (!empty($report['notes_report']) && !$show_notes_in_header) {
		echo '<div style="padding-top:10px" class="notes_report">';
		//echo '<span style="font-weight:bold;padding:0;line-height:22px;">Notes: </span>';
		echo nl2br(stripslashes($report['notes_report']));
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
