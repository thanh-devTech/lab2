<?php
/*
    Name: Default
*/
global $wpdb, $ci_cutoffs, $report;
global $pdf_header_text;

$btype  = 'tox';
$id     = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid    = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;
$sum    = true; //now live sum



$pdf_title          = tr_get_option('ci_pdf_data', 'tox_pdf_title');
$hide_patient_phone = tr_get_option('ci_pdf_data', 'pdf_hide_phone');
$hide_patient_address = tr_get_option('ci_pdf_data', 'pdf_hide_address');
$can_show_lcms		= true;
$drug_pairs         = tr_pdd_get_drug_pairs();


$drug_groups = tr_pdd_get_drug_groups('tox');
$group_Illicits = array();
foreach ($drug_groups as $groupid => $groupdata) {
	if (!$groupdata['show']) continue;
	if (stripos($groupdata['name'], 'Illicits') !== false) {
		$group_Illicits = $groupdata;
		break;
	}
}


$in_panel_tests_requested   = array();
$panel_batch                = '';
$settings                   = get_option('ci_result_settings_tox', array());


if (isset($_REQUEST['confirmation_pending'])) {
	$sum = false;
}


if (empty($pdf_title)) {
	$pdf_title = 'Lab Report';
}

$hide_comments_col 			= isset($settings['pdf_hide_comments']) && $settings['pdf_hide_comments'];
$hide_detectionwindow_col 	= isset($settings['pdf_hide_detectionwindow']) && $settings['pdf_hide_detectionwindow'];


$upload_dir = tr_wp_upload_dir();
$logo_dir   = $upload_dir['basedir'] . '/logos/';
$logo_url   = $upload_dir['baseurl'] . '/logos/';
if ($id == 0) {
	$mid = isset($_REQUEST['mid']) ? $_REQUEST['mid'] : 0;
}

$report     = tr_pdd_get_report_by_id($id, $btype, $mid);
if ($id == 0) {
	$id = $report['id'];
}

$show_location		 	= tr_get_option('ci_pdf_data', 'req_patient_location') && !empty($report['pa_location']);
if (isset($settings['lcms_final']) && $settings['lcms_final']) {
	$passdate = true;
	if (isset($settings['lcms_final_date']) && !empty($settings['lcms_final_date'])) {
		$passdate = strtotime($settings['lcms_final_date']) <= strtotime($report['doc']);
	}

	if ($passdate && ($report['final_results'] == 2 || $report['final_results'] == 0)) {
		$can_show_lcms = false;
	}
}



$style_num    = tr_get_option('ci_pdf_data', 'tox_pdf_style', 1);
if ($report['cus_result_style'] > 1) {
	$style_num = $report['cus_result_style'];
}
if ($report['cli_result_style'] > 1) {
	$style_num = $report['cli_result_style'];
}

if (isset($_GET['style']) && $_GET['style'] > 0) {
	$style_num = $_GET['style'];
}

if (isset($_GET['html'])) {
	echo 'style: ' . $style_num . '<br>';
}


if ($style_num > 1) {
	include(TRCPDD_PATH . 'reports/results_tox_' . $style_num . '.php');
	return;
}




$patient_adress = trpdd_format_address($report['pa_address'], $report['pa_city'], $report['pa_state'], $report['pa_zip']);

$clinic_address = trpdd_format_address($report['cli_street'], $report['cli_city'], $report['cli_state'], $report['cli_zip']);
if ($report['cli_street'] == '.' || $report['cli_city'] == '.' || $report['cli_street'] == '-' || $report['cli_city'] == '-') {
	$clinic_address = trpdd_format_address($report['lab_address'], $report['lab_city'], $report['lab_state'], $report['lab_zip']);
}


//parse panels
$panel_batch = $report['custom_profile'];
if (empty($panel_batch)) {
	list($panel_batch) = explode(' ', $report['panel_tests_requested']);
}
$panel_batch    = strtolower($panel_batch);
$all_res        = array();


$ci_cutoffs     = tr_pdd_get_cutoffs($btype, $report['created_date']);

$lcms_tests     = tr_pdd_get_profiles_tests_requesteds('tox', true, false, $report['doc']);

if (!empty($report['panel_tests_requested']) && !empty($lcms_tests)) {
	$all_results_q = $wpdb->get_results("
	SELECT rs.* , 
	te.te_name,
	te.te_abrev,
	te.te_ID
	FROM ci_results rs 
	JOIN ci_test_panels te ON (te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug) 
								and te.te_type='tox' and te.te_in_req = 1
	WHERE rs.report_id='" . $id . "'
	group by rs.id ", ARRAY_A);

	foreach ($all_results_q as $arr) {
		$arr['drug'] = strtoupper($arr['drug']);
		$all_res[$arr['drug']] = $arr;

		if (!empty($arr['te_name'])) {
			$arr['drug'] = strtoupper($arr['te_name']);
			$all_res[$arr['drug']] = $arr;
		}
	}


	if (stripos($report['panel_tests_requested'], 'Ethanol*') !== false) {
		if (!isset($all_res['Ethanol*']) && !isset($all_res['ETHANOL*'])) {
			$all_res['zEthanol*'] = array('drug' => 'Ethanol*', 'id' => 'new', 'pos-neg' => 'NEG');
		}
	}
} else {
	$sum = false;
}


$exclude_tests          = tr_pdd_get_test_outdate($report['doc']);
$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($report['panel_tests_requested'], $drug_pairs);
$methamphetamine        = $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $id . "' ", ARRAY_A);
$medications            = tr_pdd_parse_medications($report['medications'], $drug_pairs);
$has_Methamphetamine_dl = stripos($report['panel_tests_requested'], 'Methamphetamine d/l') !== false;
$has_Methamphetamine    = false;
$no_collection_date     = (empty($report['collection_date']) || $report['collection_date'] == '0000-00-00' || $report['collection_date'] == '0000-00-00 00:00:00') && $report['doc'] == '0000-00-00';
$notes                  = get_option('ci_notes_result');

$text_PRELIMINARY       = tr_pdd_get_result_status($report['final_results'], true);


$pdf_notes 				= trpdd_get_pdf_note($report, 'tox');
if (empty($pdf_notes)) {
	$pdf_notes = trpdd_get_pdf_note($report, 'tox', true);
}



if (isset($_REQUEST['debug']) && isset($_REQUEST['html'])) {
	var_export($report);
	echo '<br><br>';
	echo 'Tests:';
	var_export($in_panel_tests_requested);
	echo '<br><br>';
	echo 'Medications:';
	var_export($medications);
	echo '<br><br>';
	echo '$drug_pairs:';
	var_export($drug_pairs);
	echo '<br><br>';
	echo '$all_res:';
	var_export($all_res);
	echo '<br><br>';
	echo '$panel_batch: ' . $panel_batch;
	echo '<br><br>';
	echo '$ci_cutoffs: ';
	var_dump($ci_cutoffs);
	echo '<br><br>';

	echo '$drug_groups: ';
	var_dump($drug_groups);
	echo '<br><br>';
}

/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */


$footer_height = 1.5;
$margin_bottom = 0;
$margin_top = 6;

if (strlen($report['medications']) > 300) {
	$margin_top += 1;
} else if (strlen($report['medications']) > 150) {
	$margin_top += 0.5;
}

ob_start();
?>

<div id="footer">
	<?php
	$footer_text = trpdd_get_footer_text($report, 'tox');

	$director_signature = '';
	if (!empty($report['lab_director_signature'])) {
		$sign_dir = tr_get_signature_dir();
		$director_signature_url = $sign_dir['path'] . '/' . $report['lab_director_signature'];
		$director_signature = '<img src="' . $director_signature_url . '" style="height:30px">';
	}

	$footer_text = str_replace('[signature]', $director_signature, $footer_text);

	$lines = 0;
	foreach (explode('<br', $footer_text) as $ftx) {
		$lines += ceil(strlen($ftx) / 150);
	}
	$footer_height = $lines * 0.45;
	echo $footer_text;
	?>
</div>

<?php
$footer_html = ob_get_clean();


if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
	$margin_bottom = $footer_height;
	$footer_height = 0.3;
}

?>
<!DOCTYPE html>
<html class="style1">

<head>
	<meta charset="UTF-8" />
	<meta id="final" content="<?php echo $report['final_results'] ?>" />
	<title><?php echo $report['assen'] ?></title>
	<style>
		@page {
			margin: 0.7cm 0.6cm <?php echo $footer_height ?>cm 0.6cm;
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

		.marginbottom {
			margin-bottom: 10px;
		}

		.fontNormal {
			font-family: 'Arial';
			font-size: 8pt;
			color: #000000;
		}

		.fontNormalRed {
			font-family: 'Arial';
			font-size: 8pt;
			font-weight: bold;
		}

		.fontSmall {
			font-family: 'Arial';
			font-size: 8pt;
			color: #000000;
		}

		.fontBold {
			font-family: 'Arial';
			font-size: 8pt;
			color: #000000;
			font-weight: bold;
		}

		.fontBoldSmall {
			font-family: 'Arial';
			font-size: 8pt;
			color: #000000;
			font-weight: bold;
		}

		.fontBoldWhite {
			font-family: 'Arial';
			font-size: 8pt;
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

		table .font_blue td {
			color: blue
		}

		h4,
		.h4 {
			font-size: 11px;
			margin: 0;
			padding: 0;
			line-height: 12px;
			font-weight: bold
		}

		.small_table th,
		.small_table td {
			font-size: 7pt;
			padding: 2px;
			color: #000;
		}

		h5 {
			font-weight: normal;
			font-size: 12px;
			color: <?php echo $report['color'] ?>;
			margin: 10px 0 0 0
		}

		.small_padding_table th,
		.small_padding_table td {
			padding: 2px;
			color: #000;
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

		#header {
			top: 0;
		}

		#footer {
			bottom: 5px;
			border-top: 0.1pt solid #aaa;
			line-height: 13px;
			padding-right: 35px;
		}

		#footer,
		#footer * {
			font-size: 10px;
		}

		.right_pre {
			float: right;
			margin-top: -15px;
			color: red;
			text-align: right;
			<?php echo $final_adding_font ?>
		}

		.blue {
			color: blue
		}

		.red {
			color: red
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

		.header_tbl th {
			text-transform: uppercase;
			background: #555;
			text-align: center;
			height: 18px;
			color: #fff;
		}

		<?php
		if (!isset($_REQUEST['html'])) {
		?>#header,
		#footer {
			position: fixed;
			left: 0;
			right: 0;
			color: #666;
			font-size: 0.9em;
		}

		<?php
		}
		if (tr_get_option('ci_pdf_data', 'pdf_bold_assen')) {
			echo '.assen_number{font-weight:bold;font-size:9pt !important}';
		}
		?>
	</style>
</head>

<body>

	<div id="header">

		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:25%;height:60px;" valign="top">
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
						<img border="0" alt="Logo" style="max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_dir . $logo_main ?>" />
					<?php
					}

					?>

				</td>
				<td style="width:44%;text-align: center">
					<h2 style="color:<?php echo $report['color'] ?>;font-size:17px;font-weight: normal;line-height: 18px;"><?php echo $pdf_title ?></h2>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">

					<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php if (!empty($pdf_header_text)) {
						echo apply_filters('the_content', $pdf_header_text);
					} else { ?>
						<div class="header_lab_name"><?php echo $report['header_lab_name'] ?></div>
						<div class="header_lab_address"><?php echo $report['header_lab_address'] ?></div>
					<?php
						if (!empty($report['header_lab_second_line_hpdf'])) {
							echo '<div class="header_lab_second_line_hpdf">' . $report['header_lab_second_line_hpdf'] . '</div>';
						}
						if (!empty($report['header_lab_phone']) && !tr_get_option('ci_pdf_data', 'report_hilaphone')) {
							echo '<div class="header_lab_phone">Phone: ' . $report['header_lab_phone'] . '</div>';
						}
						if (!empty($report['header_lab_clia'])) {
							echo '<div class="header_lab_clia">CLIA ID: ' . $report['header_lab_clia'] . '</div>';
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
					<table class="noboder small_table">
						<tr>
							<td style="width:20%">Name:</td>
							<td><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
						</tr>
						<tr>
							<td>DOB:</td>
							<td>
								<?php echo tr_pdd_display_date($report['patient_dob']) ?>
								<?php if (!$hide_patient_phone) { ?>
									<span style="padding-left:30px">Gender:</span>
									<span><?php echo htmlspecialchars($report['pa_gender']) ?></span>
								<?php } ?>
							</td>
						</tr>
						<?php if (!$hide_patient_phone) { ?>
							<tr>
								<td>Phone:</td>
								<td><?php echo $report['pa_phone'] ?></td>
							</tr>
						<?php } else {
						?>
							<tr>
								<td>Gender:</td>
								<td><?php echo htmlspecialchars($report['pa_gender']) ?></td>
							</tr>
						<?php
						} ?>
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
						<?php } ?>
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
							<td style="width:44%">Accession:</td>
							<td class="assen_number"><?php echo $report['assen'] ?></td>
						</tr>
						<tr>
							<td>Date Collected:</td>
							<td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<tr>
							<td>Date Received:</td>
							<td><?php echo tr_pdd_display_date($report['spec_received'], 'm/d/Y H:i') ?></td>
						</tr>
						<tr>
							<td>Date Reported:</td>
							<td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<?php
						if (!empty($report['dagnostic_codes1']) && tr_get_option('ci_pdf_data', 'pcrpdf_show_diagnostic')) {
						?>
							<tr>
								<td>Diagnostic code:</td>
								<td style="line-height:8px;<?php if (strlen($report['dagnostic_codes1']) > 30) echo 'font-size:9px' ?>">
									<?php echo str_replace(',', ', ', $report['dagnostic_codes1']) ?>
								</td>
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
						if (tr_get_option('ci_pdf_data', 'report_st_top')) {
						?>
							<tr>
								<td colspan="2" style="font-size:8px">Specimen: <?php echo $report['sample_type'] ?></td>
							</tr>
						<?php
						}
						?>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="3">
					<label>Medications: </label> <?php echo (!empty($report['medications'])) ? htmlspecialchars(str_replace(',', ', ', $report['medications'])) : "No reported medications" ?>
				</td>
			</tr>
		</table>

	</div>
	<?php

	if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
		echo stripslashes($footer_html);
	}


	if (
		!empty($lcms_tests)
		&& (($report['custom_profile'] == 'Use Custom Profile' && !$report['cus_ID'])
			|| count($in_panel_tests_requested) == 0
		)
		&& !$report['only_screening']
	) {
		if (!empty($report['result_pdf'])) {
			$has_tox_result = false;
			$has_report = false;
			return;
		}
	?>
		<h1 style="color: red;">No Custom Profile/No Tests Requested Otherwise</h1>
		<?php
	} else if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
		include('rejected_default.php');
	} else {

		if (isset($_REQUEST['d2'])) {
			$no_collection_date = true;
		}
		if ($no_collection_date) {
		?>
			<h1 style="color:red">*** Insufficient Information: No Date of Collection Indicated.</h1>
		<?php
		}

		if ($sum && !$report['only_screening'] && $can_show_lcms) {
			ob_start();
			$has_summary = false;
		?>
			<h4>Summary Report</h4>
			<h3>LCMS CONFIRMATION TEST</h3>

			<?php

			$summary_all = array(
				'blue' => array('title' => 'Prescribed – Detected', 'drugs' => array()),
				'normal' => array('title' => 'Prescribed – Not Detected', 'drugs' => array()),
				'red' => array('title' => 'Not Prescribed – Detected', 'drugs' => array()),
				'Illicits' => array('title' => 'Illicits - Detected', 'drugs' => array()),
			);

			$colwidths = array('18%', '18%', '10%', '10%', '44%');

			foreach ($all_res as $i => $arr) {

				$arr['drug'] = strtoupper($arr['drug']);

				$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

				if (stripos($arr['drug'], 'Ethanol*') !== false) {
					$ethanol = $arr;
					unset($all_res[$i]);
					continue;
				}

				if ($arr['exclude'] || in_array($arr['drug'], $exclude_tests)) {
					unset($all_res[$i]);
					continue;
				}
				$all_res[$i] = $arr;

				if ($arr['pos-neg'] != 'POS' && !$arr['is_red'] && !$arr['is_blue']) {
					continue;
				}

				$k = $arr['red_color'] ? 'red' : 'normal';
				$k = $arr['is_blue'] ? 'blue' : $k;
				if ($k == 'blue' && $arr['pos-neg'] == 'NEG') {
					$k = 'normal';
				}
				if (isset($group_Illicits['drugs']) && is_array($group_Illicits['drugs']) && in_array($arr['drug'], $group_Illicits['drugs'])) {
					$k = 'Illicits';
				}
				if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
					continue;
				}
				if (!isset($summary_all[$k])) continue;
				$summary_all[$k]['drugs'][] = $arr;
				//unset($all_res[$i]);
			}

			foreach ($summary_all as $k => $grouparr) {
				if (count($grouparr['drugs']) == 0 && $k == 'Illicits') continue;

			?>
				<table class="small_table tdborder marginbottom ">
					<tr class="header_tbl">
						<th colspan="3" class="header_black"><?php echo $grouparr['title'] ?></th>
						<th colspan="<?php echo $hide_comments_col ? 1 : 2 ?>" class="header_none"></th>
					</tr>
					<tr class="title_tbl">
						<th style="width:<?php echo $colwidths[0] ?>">Prescription</th>
						<th style="width:<?php echo $colwidths[1] ?>">Anticipated</th>
						<th style="width:<?php echo $colwidths[2] ?>" class="center">Detected(ng/mL)</th>
						<th style="width:<?php echo $colwidths[3] ?>" class="center">Cutoff(ng/mL)</th>
						<?php if (!$hide_comments_col) { ?>
							<th style="width:<?php echo $colwidths[4] ?>" class="center">Comments</th>
						<?php } ?>
					</tr>
					<?php
					foreach ($grouparr['drugs'] as $arr) {
						$has_summary = true;
					?>
						<tr class="<?php if ($arr['red_color']) echo 'font_red' ?> <?php if ($arr['is_blue']) echo ' font_blue' ?>">
							<td style="width:<?php echo $colwidths[0] ?>" class="bold"><?php echo htmlspecialchars($arr['med']) ?></td>
							<td style="width:<?php echo $colwidths[1] ?>" class="bold"><?php echo htmlspecialchars($arr['drug']) ?></td>
							<td style="width:<?php echo $colwidths[2] ?>" class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
							<td style="width:<?php echo $colwidths[3] ?>" class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
							<?php if (!$hide_comments_col) { ?>
								<td style="width:<?php echo $colwidths[4] ?>"><?php echo trpdd_filter_comments_in_pdf($arr['comments']) ?></td>
							<?php } ?>
						</tr>
					<?php
					}
					?>
				</table>
			<?php
			}
			?>
			<div class="break"></div>
			<?php
			$content_sum = ob_get_clean();
			if ($has_summary) {
				echo $content_sum;
			}
		}


		//include urine validate & screening
		$have_urine = false;
		$have_screening = false;
		include(TRCPDD_PATH . 'reports/results_screening.php');
		include(TRCPDD_PATH . 'reports/results_urine.php');



		ob_start();
		$has_tox_result = false;
		if (!empty($report['panel_tests_requested']) && !empty($lcms_tests) && !$report['only_screening'] && $can_show_lcms) {
			?>
			<h4 class="h4">LCMS Confirmation</h4>
			<table class="border tdborder small_table marginbottom">
				<?php
				$comments = "Comments";
				foreach ($drug_groups as $groupid => $groupdata) {
					if (!$groupdata['show']) continue;
					$found = 0;
					$drugs = array_map('strtoupper', $groupdata['drugs']);
					foreach ($drugs as $d) {
						if (isset($all_res[$d])) $found++;
					}
					if (empty($found)) continue;


					//$comments = "";
					ob_start();
					foreach ($drugs as $d) {
						$arr = $all_res[$d];
						if (!empty($arr)) {
							unset($all_res[$d]);
							$arr['group'] = $groupdata['name'];

							$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

							if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
								continue;
							}
							if ($arr['drug'] == 'METHAMPHETAMINE') {
								if ($arr['pos-neg'] == 'NEG') {
									$has_Methamphetamine = false;
								} else {
									$has_Methamphetamine = true;
								}
							}

							if (stripos($arr['drug'], 'Ethanol*') !== false) {
								$ethanol = $arr;
								continue;
							}
							if ($arr['exclude']) {
								continue;
							}
				?>
							<tr class="<?php if ($arr['is_blue']) echo 'in_medications' ?> <?php if ($arr['is_red']) echo 'bg_red' ?>">
								<td><?php echo htmlspecialchars($arr['drug']) ?></td>
								<td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
								<td class="center" <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
								<td class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
								<?php if (!$hide_detectionwindow_col) { ?>
									<td><?php echo ($arr['detection']) ?></td>
								<?php } ?>
								<?php if (!$hide_comments_col) { ?>
									<td><?php echo trpdd_filter_comments_in_pdf($arr['comments']) ?></td>
								<?php } ?>
							</tr>
						<?php
						}
					}
					$drug_content = ob_get_clean();
					if (!empty($drug_content)) {
						?>
						<tr class="title_tbl">
							<th style="width:20%"><?php echo htmlspecialchars($groupdata['name']) ?></th>
							<th style="width:10%" class="center">Conc <br> (ng/mL)</th>
							<th style="width:10%" class="center">Pos/Neg</th>
							<th style="width:10%" class="center">Cutoff<br> (ng/mL)</th>
							<?php if (!$hide_detectionwindow_col) { ?>
								<th style="width:10%" class="center">Detection Window</th>
							<?php } ?>
							<?php if (!$hide_comments_col) { ?>
								<th class="center"><?php echo htmlspecialchars($comments) ?></th>
							<?php } ?>
						</tr>
					<?php
						echo $drug_content;
						$has_tox_result = true;
					}
				}


				if (is_array($all_res) && count($all_res) > 0) {

					ksort($all_res);
					ob_start();
					foreach ($all_res as $arr) {

						$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

						if ($arr['drug'] == 'METHAMPHETAMINE') {
							if ($arr['pos-neg'] == 'NEG') {
								$has_Methamphetamine = false;
							} else {
								$has_Methamphetamine = true;
							}
						}
						if (stripos($arr['drug'], 'Ethanol*') !== false) {
							$ethanol = $arr;
							continue;
						}
						if ($arr['exclude']) {
							continue;
						}

					?>
						<tr class="<?php if ($arr['is_blue']) echo 'in_medications' ?> <?php if ($arr['is_red']) echo 'bg_red' ?>">
							<td><?php echo htmlspecialchars($arr['drug']) ?></td>
							<td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
							<td class="center" <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
							<td class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
							<?php if (!$hide_detectionwindow_col) { ?>
								<td><?php echo $arr['detection'] ?></td>
							<?php } ?>
							<?php if (!$hide_comments_col) { ?>
								<td><?php echo trpdd_filter_comments_in_pdf($arr['comments']) ?></td>
							<?php } ?>
						</tr>
					<?php
					}
					$other_content = ob_get_clean();
					if (!empty($other_content)) {
					?>
						<tr>
							<th>Other</th>
							<th class="center">Conc <br> (ng/mL)</th>
							<th class="center">Pos/Neg</th>
							<th class="center">Cutoff<br> (ng/mL)</th>
							<?php if (!$hide_detectionwindow_col) { ?>
								<th class="center">Detection Window</th>
							<?php } ?>
							<?php if (!$hide_comments_col) { ?>
								<th><?php echo $comments ?></th>
							<?php } ?>
						</tr>
				<?php
						echo $other_content;
						$has_tox_result = true;
					}
				}
				?>
				<?php
				if ($ethanol && $report['matrix'] != 'oral') :
					$arr = $ethanol;
					if (empty($arr['cutoff'])) {
						$arr['cutoff'] = '.010';
					}
					if (empty($arr['detection'])) {
						$arr['detection'] = '24 hours';
					}
					if (empty($arr['conc']) || $arr['conc'] == '<') {
						$arr['conc'] = '<' . $arr['cutoff'];
					}
				?>
					<tr>
						<th>Alcohol</th>
						<th class="center">Conc %</th>
						<th class="center">Pos/Neg</th>
						<th class="center">Cutoff %</th>
						<th class="center">Detection Window</th>
						<?php if (!$hide_comments_col) { ?>
							<th><?php echo $comments ?></th>
						<?php } ?>
					</tr>
					<tr class="<?php if ($arr['is_blue']) echo 'in_medications' ?> <?php if ($arr['is_red']) echo 'bg_red' ?>">
						<td><?php echo htmlspecialchars($arr['drug']) ?></td>
						<td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
						<td class="center"><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
						<td class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
						<td><?php echo $arr['detection'] ?></td>
						<?php if (!$hide_comments_col) { ?>
							<td><?php echo trpdd_filter_comments_in_pdf($arr['comments']) ?></td>
						<?php } ?>
					</tr>
				<?php
				endif;
				?>
			</table>


			<?php include(TRCPDD_PATH . 'reports/methamphetamine_dl.php') ?>

			<div style="text-align: right;margin-top:15px;">
				<img style="max-width: 100%;height:auto;" src="<?php echo TRCPDD_PATH ?>images/last-panel.png" />
			</div>


		<?php
		}

		$content_tox = ob_get_clean();
		if ($has_tox_result) {
			echo $content_tox;
		}

		if (!empty($notes['last_page_text'])) {
		?>
			<div style="page-break-inside: auto;">
				<table>
					<tr>
						<td>
							<?php echo trpdd_filter_comments_in_pdf($notes['last_page_text']) ?>
						</td>
					</tr>
				</table>
			</div>
	<?php
		}

		if ($report['show_comment1'] && !empty($notes[1])) {
			echo '<div><p>';
			echo trpdd_filter_comments_in_pdf($notes[1]);
			echo '</p></div>';
		}
		if ($report['show_comment2'] && !empty($notes[2])) {
			echo '<div><p>';
			echo trpdd_filter_comments_in_pdf($notes[2]);
			echo '</p></div>';
		}

		if (!$has_tox_result && !$have_urine && !$have_screening) {
			$has_report = empty($report['result_pdf']) ? true : false;
		}
	} //end if rejected

	include('_approved_by.php');

	if (!empty($report['notes_report'])) {
		echo '<div style="padding-top:15px">';
		echo '<div style="font-weight:bold;padding:0;line-height:22px;">Notes</div>';
		echo trpdd_filter_comments_in_pdf($report['notes_report']);
		echo '</div>';
	}
	if ($has_tox_result && !empty($pdf_notes)) {
		echo '<div class="bottom_notes">';
		echo trpdd_filter_comments_in_pdf($pdf_notes);
		echo '</div>';
	}


	if (isset($_REQUEST['html'])) {
		echo stripslashes($footer_html);
	}
	?>

	<?php include('_pdf_pagination.php'); ?>

</body>

</html>