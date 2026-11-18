<?php
global $pdf_header_text;

$note_title = '';

$bottom_comments = array();
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
			margin-top: 6cm;
			margin-bottom: <?php echo $margin_bottom ?>cm;
		}

		break {
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

		#footer {
			bottom: 5px;
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

		ul {
			margin-top: 2px;
			margin-bottom: 0px;
		}

		.header_tbl th {
			text-transform: uppercase;
			background: #555;
			text-align: center;
			height: 18px;
			color: #fff;
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
				<td style="width:34%;height:60px;" valign="top">
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
				<td style="width:38%;text-align: center">
					<h2 style="color:<?php echo $report['color'] ?>;font-size:18px;font-weight: normal;"><?php echo $pdf_title ?></h2>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">
					<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php if (!empty($pdf_header_text)) {
						echo apply_filters('the_content', $pdf_header_text);
					} else { ?>
						<div><?php echo $report['header_lab_name'] ?></div>
						<div><?php echo $report['header_lab_address'] ?></div>
						<?php if (!tr_get_option('ci_pdf_data', 'report_hilaphone')) { ?>
							<div>Phone: <?php echo $report['header_lab_phone'] ?></div>
						<?php } ?>
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
							<td style="width:60px">Gender:</td>
							<td style="width:20px"><?php echo htmlspecialchars($report['pa_gender']) ?></td>
						</tr>
						<tr>
							<td>Phone:</td>
							<td colspan="3"><?php echo $report['pa_phone'] ?></td>
						</tr>

						<tr>
							<td>Address:</td>
							<td colspan="3" class="smalltext"><?php echo $patient_adress ?></td>
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
							<td><?php echo tr_pdd_display_date($report['spec_received'], 'm/d/Y H:i', false, true) ?></td>
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
	?>


	<?php
	if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
		include('rejected_default.php');
	} else {

		$showed_title_parents = array();

		foreach ($drug_groups as $groupid => $groupdata) {

			$has_test = false;
			$has_pos = false;



			ob_start();

			foreach ($groupdata['tests'] as $arr) {
				$class = '';
				$has_comments = !empty($arr['te_comments']);
				if (empty($arr['conc']) && empty($arr['te_units'])) {
					$arr['conc'] = 'Not Performed';
				}
	?>
				<tr class="<?php echo $class ?>">
					<td <?php if ($has_comments) echo 'rowspan="2"' ?> class="drug fontBold"><?php echo $arr['te_name'] ?></td>
					<td class="conc center fontDejavu"><?php echo $arr['conc'] . (!empty($arr['te_units']) ? ' ' . trpdd_show_symbol($arr['te_units']) : '') ?></td>
					<td class=" fontDejavu"><?php echo trpdd_show_symbol($arr['comments']) ?></td>
					<td class="center fontDejavu"><?php echo trpdd_show_symbol($arr['te_desc']) ?></td>
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
				if (stripos($groupdata['name'], 'Hemoglobin Evaluation') !== false) {
					$groupdata['name'] = 'Hemoglobin Electrophoresis with Reflex to RBC Solubility';
					$note_title = 'Hemoglobin Evaluation';
				}
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
	} //end if rejected

	include('_approved_by.php');



	if (!empty($report['notes_report'])) {
		echo '<div style="padding-top:15px" class="notes_report">';

		if (!empty($note_title)) {
			echo '<b>' . $note_title . '</b><br>';
		}

		echo trpdd_filter_comments_in_pdf($report['notes_report']);
		echo '</div>';
	}

	if (!empty($pdf_notes)) {
		echo '<div class="notes_report" >';
		echo trpdd_filter_comments_in_pdf($pdf_notes);
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