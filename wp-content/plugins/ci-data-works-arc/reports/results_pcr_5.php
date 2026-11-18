<?php
/*
Name: Mic style 2
*/
global $wpdb, $ci_cutoffs, $report;
global $pdf_header_text;

$show_summary = false;

if (!$report) {
	$report = tr_pdd_get_report_by_id($id, 'pcr', $mid);
}

$report['add_style'] = '';
if (!$report || empty($report['assen'])) {
	return;
}
if ($id == 0) {
	$id = $report['id'];
}

$pdf_notes = trpdd_get_pdf_note($report, 'pcr');

$show_dagnostic_code  = !empty($report['dagnostic_codes1']) && tr_get_option('ci_pdf_data', 'pcrpdf_show_diagnostic');


/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */


$footer_height = 1.4;
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
		if ($footer_height < 2) {
			$footer_height = 2.2;
		}
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


$margin_top = 5.2;
$report_st_top = tr_get_option('ci_pdf_data', 'report_st_top');
if (isset($_GET['html'])) {
	var_dump($found_root_groups);
}
if (count($found_root_groups) >= 2) {
	$report_st_top = false;
}
if ($report_st_top && strlen($report['sample_type']) > 20) {
	$margin_top = 5.4;
}
if (empty($text_PRELIMINARY)) {
	$margin_top = 5.2;
}
if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) {
	$margin_top += 0.5;
}
if (tr_get_option('ci_pdf_data', 'pcr_patient_fvaccine') && !empty($report['pa_fully_vaccinated'])) {
	$margin_top += 0.5;
}
if (!empty($report['header_lab_second_line_hpdf']) && !empty($report['header_lab_clia']) && !empty($report['header_lab_phone']) && !tr_get_option('ci_pdf_data', 'report_hilaphone')) {
	$margin_top += 0.2;
}
if ($show_dagnostic_code && $margin_top <= 6) {
	$margin_top += 0.3;
}

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
			border: 1px solid #888;
			border-spacing: 0px;
			width: 100%;
		}

		table.tdborder td,
		table.tdborder th {
			border: 1px solid #888;
		}

		table.border2.border.tdborder {
			border-top: 1px solid #888;
			border-left: 1px solid #888;
			border-right: 2px solid #888;
			border-bottom: 2px solid #888
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
			padding-top: 0px;
			padding-bottom: 5px
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

		.center_pre {
			text-align: center;
			color: red;
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
			font-size: 10px;
			padding: 2px;
		}

		.small_padding_table th,
		.small_padding_table td {
			padding: 1px 2px;
			color: #000;
		}

		.address_right div {
			font-size: 10px;
			line-height: 9px;
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

		<?php if (!isset($_REQUEST['debug'])) : ?>#header,
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
			height: 16px;
			color: #fff;
		}

		.title_tbl th {
			text-align: center;
			border-bottom: 1px solid #ddd;
			height: 14px;
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


		body tr.thicklineabove td {
			border-top: 2px solid #000;
		}

		.controls sup,
		.resultcol sup {
			font-size: 9px;
			font-weight: normal;
		}

		table.comment_next_row tr td {
			border-color: #ddd;
			border-left: 0;
			border-right: 0;
		}

		table.comment_next_row tr th {
			border: none;
		}

		table tr.comment_at_bottom td {
			border-bottom: 0;
		}

		table.comment_next_row td.pos_neg {
			font-weight: bold;
		}

		table.comment_next_row td.conc {
			background: #fbfbfb;
		}

		table.border.comment_next_row {
			border: none;
		}

		.small_padding_table h5 {
			margin-top: 3px;
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
					<h2 style="color:<?php echo $report['color'] ?>;font-size:18px;line-height:16px;margin:0;font-weight: normal;text-align: center"><?php echo 'Molecular & Microbiology Report' ?></h2>
					<?php if (tr_get_option('ci_pdf_data', 'centerfinalreport')) { ?>
						<div class="center_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php } ?>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">
					<?php if (!tr_get_option('ci_pdf_data', 'centerfinalreport')) { ?>
						<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php } ?>
					<?php if (!empty($pdf_header_text)) {
						echo apply_filters('the_content', $pdf_header_text);
					} else { ?>
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
					}
					?>
				</td>
			</tr>
		</table>
		<table class="small_padding_table" style="border-top:3px solid <?php echo $report['color'] ?>;border-bottom:3px solid <?php echo $report['color'] ?>">
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

					</table>
				</td>
				<td style="border-right:1px solid #ddd;padding-left:10px;">
					<table class="noboder small_table">
						<tr>
							<td style="width:20%">Name:</td>
							<td><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
						</tr>
						<tr>
							<td>DOB:</td>
							<td>
								<?php echo tr_pdd_display_date($report['patient_dob']) ?>
								<span style="padding-left:30px">Gender:</span>
								<span><?php echo htmlspecialchars($report['pa_gender']) ?></span>
							</td>
						</tr>
						<?php if (!$hide_patient_phone) { ?>
							<tr>
								<td>Phone:</td>
								<td><?php echo $report['pa_phone'] ?></td>
							</tr>
						<?php } ?>
						<?php if (!$hide_patient_address) { ?>
							<tr>
								<td>Address:</td>
								<td class="smalltext"><?php echo $patient_adress ?></td>
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
								<td><?php echo htmlspecialchars($report['pa_passport']) ?></td>
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
					<table class="noboder small_table" style="margin-bottom:4px;">
						<tr>
							<td style="width:42%">Accession:</td>
							<td><?php echo $report['assen'] ?></td>
						</tr>
						<tr>
							<td>Date Collected:</td>
							<td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<tr>
							<td>Date Received:</td>
							<td><?php echo tr_pdd_display_date($report['spec_received'], 'm/d/Y H:i', false, true) ?></td>
						</tr>
						<tr>
							<td>Date Reported:</td>
							<td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<?php
						if ($report_st_top) {
						?>
							<tr>
								<td>Specimen Type:</td>
								<td style="font-size:8px"><?php echo $report['sample_type'] ?></td>
							</tr>
						<?php
						}
						?>
						<?php
						if ($show_dagnostic_code) {
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
		$has_report = true;

		$has_manual2_antibiotic = true;

		include('pcr_main.php');
	} //end if rejected


	if (!empty($notes_pos_neg)) {
		echo '<div class="bottom_notes">';
		echo trpdd_filter_comments_in_pdf($notes_pos_neg);
		echo '</div>';
	}

	if (!empty($report['notes_report'])) {
		echo '<div style="padding-top:8px;font-size:10px;line-height:12px" class="notes_report">';
		//echo '<span style="font-weight:bold;padding:0;line-height:12px;">Notes: </span>';
		echo trpdd_filter_comments_in_pdf($report['notes_report']);
		echo '</div>';
	}

	if (!empty($notes['result_imagenote'])) {

		echo '<div style="text-align:center">';
		echo '<img src="' . $logo_dir . '/' . $notes['result_imagenote'] . '" style="width:auto;margin:0 auto;height:auto"/>';
		echo '</div>';
	}

	if (!isset($notes['onlyscovid'])) {
		$notes['onlyscovid'] = 0;
	}
	if (!empty($notes['result_image']) && (!@$notes['onlyscovid'] || $has_covid)) {

		echo '<div class="break"></div>';
		echo '<div style="background-color: white; z-index: 2;position:fixed;height:1200px">';
		echo '<img src="' . $logo_dir . '/' . $notes['result_image'] . '" style="width:100%;margin:0 auto;height:auto"/>';
		echo '</div>';
	}
	if (isset($_REQUEST['html'])) {
		echo stripslashes($footer_html);
	}


	?>

</body>

</html>
