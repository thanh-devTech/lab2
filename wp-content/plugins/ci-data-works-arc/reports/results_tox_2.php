<?php
global $wpdb, $ci_cutoffs, $report;

$btype  = 'tox';
$id     = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid    = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;
$sum    = true; //now live sum
$can_show_lcms = isset($can_show_lcms) ? $can_show_lcms : true;

$settings                   = get_option('ci_result_settings_tox', array());
$hide_comments_col 			= isset($settings['pdf_hide_comments']) && $settings['pdf_hide_comments'];
$hide_detectionwindow_col 	= isset($settings['pdf_hide_detectionwindow']) && $settings['pdf_hide_detectionwindow'];

$colwidths = array('21%', '6%', '6%', '6%', '6%', '6%', '8%', '8%', '38%');

if (isset($_REQUEST['confirmation_pending'])) {
	$sum = false;
}

$upload_dir = tr_wp_upload_dir();
$logo_dir   = $upload_dir['basedir'] . '/logos/';
$logo_url   = $upload_dir['baseurl'] . '/logos/';

if (!$report) {
	$report = tr_pdd_get_report_by_id($id, $btype);
	if ($id == 0) {
		$id = $report['id'];
	}
}


$drug_pairs                 = tr_pdd_get_drug_pairs();
$in_panel_tests_requested   = array();
$panel_batch                = '';

//parse panels
$panel_batch = $report['custom_profile'];
if (empty($panel_batch)) {
	list($panel_batch) = explode(' ', $report['panel_tests_requested']);
}
$panel_batch    = strtolower($panel_batch);
$all_res        = array();
$drug_groups    = tr_pdd_get_drug_groups($btype);

$group_Illicits = array();
foreach ($drug_groups as $groupid => $groupdata) {
	if (!$groupdata['show']) continue;
	if (stripos($groupdata['name'], 'Illicits') !== false) {
		$group_Illicits = $groupdata;
		break;
	}
}

$methamphetamine = $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $id . "' ", ARRAY_A);
$ci_cutoffs     = tr_pdd_get_cutoffs($btype, $report['created_date']);

$lcms_tests     = tr_pdd_get_profiles_tests_requesteds('tox', true, false, $report['doc']);

if (!empty($report['panel_tests_requested']) && !empty($lcms_tests)) {
	$all_results_q = $wpdb->get_results("
	SELECT rs.* , 
	te.te_name,
	te.te_abrev,
	te.te_ID
	FROM ci_results rs 
	JOIN ci_test_panels te ON (te.te_name = rs.drug or te.te_abrev = rs.drug) 
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


$methamphetamine    = $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $id . "' ", ARRAY_A);
$ci_cutoffs         = tr_pdd_get_cutoffs($btype, $report['created_date']);

$exclude_tests      = tr_pdd_get_test_outdate($report['doc']);
$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($report['panel_tests_requested'], $drug_pairs);
$medications        = tr_pdd_parse_medications($report['medications'], $drug_pairs);
$has_Methamphetamine = $has_Methamphetamine_dl = stripos($report['panel_tests_requested'], 'Methamphetamine d/l Isomers') !== false;
$no_collection_date = (empty($report['collection_date']) || $report['collection_date'] == '0000-00-00' || $report['collection_date'] == '0000-00-00 00:00:00') && $report['doc'] == '0000-00-00';
$notes              = get_option('ci_notes_result');

$text_PRELIMINARY   = tr_pdd_get_result_status($report['final_results'], true);

$pdf_notes 				= trpdd_get_pdf_note($report, 'tox');



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
<html class="style2">

<head>
	<meta charset="UTF-8" />
	<meta id="final" content="<?php echo $report['final_results'] ?>" />
	<title>Result</title>
	<style>
		@page {
			margin: 1cm 1cm <?php echo $footer_height ?>cm 1cm;
		}

		body {
			margin-top: 5.6cm;
			margin-bottom: <?php echo $margin_bottom ?>cm;
		}

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

		table .font_red td.comment {
			color: #000;
		}

		table .font_bold td {
			font-weight: bold
		}

		table .font_blue td.comment {
			color: #000
		}

		table .font_bold td.comment {
			font-weight: normal
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

		table th small,
		table td small {
			color: #555;
			font-size: 7pt;
		}

		.small_padding_table th,
		.small_padding_table td {
			padding: 2px;
			color: #000;
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
			border-bottom: 0.1pt solid #aaa;
		}

		#footer {
			bottom: 5px;
			border-top: 0.1pt solid #aaa;
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
	</style>
</head>

<body>

	<div id="header" class="style2">
		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:20%;height:80px;" valign="top">
					<?php
					$main_logo = $report['lab_logo'];

					$logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

					$has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
					if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
						$main_logo = $logo_second;
					}


					$logo_path = $logo_dir . $main_logo;
					if (file_exists($logo_path)) {
						$size = getimagesize($logo_path);
						if ($size[0] > $size[1]) {
					?>
							<img border="0" style="max-width: 400px; height: auto;max-height:80px" src="<?php echo $logo_dir . $main_logo ?>" />
						<?php
						} else {
						?>
							<img border="0" style="max-height: 80px; width: auto;" src="<?php echo $logo_dir . $main_logo ?>" />
					<?php
						}
					}
					?>

				</td>
				<td>
				</td>
				<td style="width:60%;vertical-align: top;min-width: 300px;padding-right:0">
					<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<table class="border" style="width:100%;">
						<tr>
							<td style="padding:10px;">
								<?php if (!empty($report['header_lab_address'])) : ?>
									<div><strong><?php echo $report['header_lab_address'] ?></strong></div>
								<?php endif; ?>
								<table class="noborder">
									<tr>
										<td style="padding:0">
											<div>Phone: <?php echo $report['header_lab_phone'] ?></div>
											<div>CLIA # <?php echo $report['header_lab_clia'] ?></div>
											<?php

											?>
										</td>
										<td style="padding:0">
										</td>
									</tr>
								</table>

							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<table class="border tdborder small_padding_table">
			<tr>
				<th style="width:22%">Patient Name</th>
				<td style="width:28%"><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
				<th style="width:22%">Physician/Provider</th>
				<td style="width:28%"><?php echo htmlspecialchars($report['physician']) ?></td>
			</tr>
			<tr>
				<th>Accession</th>
				<td><?php echo htmlspecialchars($report['assen']) ?></td>
				<th>Patient Code</th>
				<td><?php echo htmlspecialchars($report['pa_code']) ?></td>
			</tr>
			<tr>
				<th>Patient DOB</th>
				<td>
					<?php
					if (empty($report['patient_dob']) || $report['patient_dob'] == '00000' || $report['patient_dob'] == '0000-00-00' || $report['patient_dob'] == '00/00/0000') {
						echo 'Not Provided';
					} else {
						echo tr_pdd_display_date($report['patient_dob'], 'm/d/Y');
					}
					?>
				</td>
				<th>Clinic</th>
				<td><?php echo $report['requesting_practice'] ?></td>
			</tr>
			<tr>
				<th>Results Date</th>
				<td>
					<?php
					if (empty($report['approve_date']) || $report['approve_date'] == '0000-00-00' || $report['approve_date'] == '0000-00-00 00:00:00' || $report['approve_date'] == '00/00/0000') {
						echo 'Not Provided';
					} else {
						echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i');
					}
					?>
				</td>
				<th>Sample Type</th>
				<td><?php echo htmlspecialchars($report['sample_type']) ?></td>
			</tr>
			<tr>
				<th>Collection Date</th>
				<td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
				<th>Received in Lab</th>
				<td><?php echo tr_pdd_display_date($report['received_in_lab'], 'm/d/Y H:i') ?></td>
			</tr>
			<tr>
				<th>Prescribed Medications</th>
				<td colspan="3"><?php echo (!empty($report['medications'])) ? htmlspecialchars(str_replace(',', ', ', $report['medications'])) : "No reported medications" ?></td>
			</tr>
		</table>
	</div>
	<?php
	if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
		echo $footer_html;
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
		?>

		<?php
		if ($sum && !$report['only_screening'] && $can_show_lcms) {
			ob_start(); //for sum

			if (!function_exists('trppp_add_sum_desc')) {
				function trppp_add_sum_desc()
				{
		?>
					<div>
						<p style="font-size:11px;position:fixed;bottom:40px;">
							Summary data is contingent upon prescribed medication being entered at time of order entry. Summary is not intended to substitute for the review of the full report.
						</p>
					</div>
					<div class="break"></div>
			<?php
				}
			}

			$has_sum = false;
			$count_pixcel_table_sum = 40; //2 line header
			$comment_line = 82;
			$max_page_height = 866;
			//width 850px

			$titles = array(
				'red' => 'Not Prescribed – Detected',
				'blue' => 'Prescribed – Detected',
				'normal' => 'Prescribed – Not Detected',
				'Illicits' => 'Illicits - Detected'
			);

			?>
			<h4>Report Summary</h4>
			<table class=" tdborder small_table marginbottom">
				<tr class="title_tbl">
					<th style="width:<?php echo $colwidths[0] ?>">Test</th>
					<th style="width:<?php echo $colwidths[1] ?>" class="center">POCT<br><small>Pos/Neg</small></th>
					<th style="width:<?php echo $colwidths[2] ?>" class="center">IA<br><small>Pos/Neg</small></th>
					<th style="width:<?php echo $colwidths[3] ?>" class="center">LCMS<br><small>Pos/Neg</small></th>
					<th style="width:<?php echo $colwidths[4] ?>" class="center">LCMS<br><small>(ng/mL)</small></th>
					<th style="width:<?php echo $colwidths[5] ?>" class="center">Cutoff<br><small>(ng/mL)</small></th>
					<th style="width:<?php echo $colwidths[6] ?>" class="center">Crea Adj.<br><small>Value</small></th>
					<th style="width:<?php echo $colwidths[7] ?>" class="center">Detection Window</th>
					<th style="width:<?php echo $colwidths[8] ?>">Comments</th>
				</tr>
				<?php

				if (is_array($all_res) && count($all_res) > 0) {

					$summary_all = array('red' => array(), 'blue' => array(), 'normal' => array(), 'Illicits' => array());
					foreach ($all_res as $arr) {
						$arr['drug'] = strtoupper($arr['drug']);

						$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

						if (stripos($arr['drug'], 'Ethanol*') !== false) {
							$ethanol = $arr;
							continue;
						}

						if ($arr['exclude']) {
							continue;
						}
						if ($arr['pos-neg'] != 'POS' && !$arr['is_red'] && !$arr['is_blue']) {
							continue;
						}

						if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
							continue;
						}

						$k = $arr['red_color'] ? 'red' : 'normal';
						$k = $arr['is_blue'] ? 'blue' : $k;
						if ($k == 'blue' && $arr['pos-neg'] == 'NEG') {
							$k = 'normal'; //Not Detected
						}
						//find group Illicits

						if (isset($group_Illicits['drugs']) && is_array($group_Illicits['drugs']) && in_array($arr['drug'], $group_Illicits['drugs'])) {
							$k = 'Illicits';
						}
						$summary_all[$k][] = $arr;
					}

					if (isset($_REQUEST['de'])) {
						var_dump($summary_all);
					}




					$all_sum = array();
					if (count($summary_all['red']) > 0) {
						$all_sum = array_merge($all_sum, array(array('drug' => '', 'type' => 'red')));
						$all_sum = array_merge($all_sum, $summary_all['red']);
					} else {
						$all_sum = array_merge($all_sum, array(array('drug' => '', 'type' => 'red')));
					}

					if (count($summary_all['blue']) > 0) {
						$all_sum = array_merge($all_sum, array(array('drug' => '', 'type' => 'blue')));
						$all_sum = array_merge($all_sum, $summary_all['blue']);
					} else {
						$all_sum = array_merge($all_sum, array(array('drug' => '', 'type' => 'blue')));
					}

					if (count($summary_all['normal']) > 0) {
						$all_sum = array_merge($all_sum, array(array('drug' => '', 'type' => 'normal')));
						$all_sum = array_merge($all_sum, $summary_all['normal']);
					} else {
						$all_sum = array_merge($all_sum, array(array('drug' => '', 'type' => 'normal')));
					}

					if (count($summary_all['Illicits']) > 0) {
						$all_sum = array_merge($all_sum, array(array('drug' => '', 'type' => 'Illicits')));
						$all_sum = array_merge($all_sum, $summary_all['Illicits']);
					}

					//----------------------show

					foreach ($all_sum as $arr) {
						$by_title = 0;
						$arr['comments'] = trim($arr['comments']);
						$cline = max(ceil(strlen($arr['comments']) / $comment_line), 1);
						$nlcount = max(0, substr_count($arr['comments'], "\n"));
						$cline += $nlcount;
						$height_row = $cline * 22;
						if (empty($arr['drug'])) {
							$by_title = 40;
						}
						$count_pixcel_table_sum += $height_row;

						if ($count_pixcel_table_sum + $by_title > $max_page_height) {
							echo '</table>';
							// echo $count_pixcel_table_sum;
							trppp_add_sum_desc();
							echo '<table class=" tdborder small_table">';
							$count_pixcel_table_sum = $height_row;
						}
						$has_sum = true;
						if (empty($arr['drug'])) {
							//if($count_pixcel_table_sum != $height_row) {
				?>
							<tr>
								<td colspan="9" style="font-weight:bold;border:0;padding-top:5px;"><?php echo $titles[$arr['type']] ?></td>
							</tr>
						<?php
							//}
						} else {
						?>
							<tr class="<?php if ($arr['red_color']) echo 'font_red' ?> <?php if ($arr['is_blue']) echo ' font_blue' ?>">
								<td style="width:<?php echo $colwidths[0] ?>"><?php echo htmlspecialchars($arr['drug']) ?></td>
								<td style="width:<?php echo $colwidths[1] ?>" class="center"><?php echo htmlspecialchars($arr['poct']) ?></td>
								<td style="width:<?php echo $colwidths[2] ?>" class="center"><?php echo htmlspecialchars($arr['ia']) ?></td>
								<td class="center" style="width:<?php echo $colwidths[3] ?> <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo ';font-weight:600' ?>"><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
								<td style="width:<?php echo $colwidths[4] ?>" class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
								<td style="width:<?php echo $colwidths[5] ?>" class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
								<td style="width:<?php echo $colwidths[6] ?>" class="center"><?php echo htmlspecialchars($arr['crea_adj']) ?></td>
								<td style="width:<?php echo $colwidths[7] ?>"><?php echo $arr['detection'] ?></td>
								<td style="width:<?php echo $colwidths[8] ?>" class="comment"><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
							</tr>
				<?php
						}
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

					$arr['comments'] = trim($arr['comments']);
					$cline = max(ceil(strlen($arr['comments']) / $comment_line), 1);
					$nlcount = max(0, substr_count($arr['comments'], "\n"));
					$cline += $nlcount + 1; //+1 for header
					$height_row = $cline * 22;
					$count_pixcel_table_sum += $height_row;
					if ($count_pixcel_table_sum > $max_page_height) {
						echo '</table>';
						// echo $count_pixcel_table_sum;
						trppp_add_sum_desc();
						echo '<table class="border tdborder small_table">';
						$count_pixcel_table_sum = $height_row;
					}
					$has_sum = true;
				?>
					<tr>
						<th colspan="9">Alcohol</th>
					</tr>
					<tr>
						<td style="width:<?php echo $colwidths[0] ?>"><?php echo htmlspecialchars($arr['drug']) ?></td>
						<td style="width:<?php echo $colwidths[1] ?>" class="center"><?php echo htmlspecialchars($arr['poct']) ?></td>
						<td style="width:<?php echo $colwidths[2] ?>" class="center"><?php echo htmlspecialchars($arr['ia']) ?></td>
						<td class="center" style="width:<?php echo $colwidths[3] ?> <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo ';font-weight:600' ?>"><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
						<td style="width:<?php echo $colwidths[4] ?>" class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
						<td style="width:<?php echo $colwidths[5] ?>" class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
						<td style="width:<?php echo $colwidths[6] ?>" class="center"><?php echo htmlspecialchars($arr['crea_adj']) ?></td>
						<td style="width:<?php echo $colwidths[7] ?>"><?php echo $arr['detection'] ?></td>
						<td style="width:<?php echo $colwidths[8] ?>" class="comment"><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
					</tr>
				<?php
				endif;
				?>
			</table>
			<?php
			if (!$has_Methamphetamine_dl && empty($methamphetamine['d']) && empty($methamphetamine['l'])) {
				$has_Methamphetamine = false;
			}
			if ($has_Methamphetamine  && $report['matrix'] != 'oral') :


				$methamphetamine['comments'] = trim($methamphetamine['comments']);
				$cline = max(ceil(strlen($methamphetamine['comments']) / $comment_line), 1);
				$nlcount = max(0, substr_count($methamphetamine['comments'], "\n"));
				$cline += $nlcount + 2; //+1 for header
				$count_pixcel_table_sum += max(88, $cline * 22);
				if ($count_pixcel_table_sum > $max_page_height) {
					// echo $count_pixcel_table_sum;
					trppp_add_sum_desc();
					$count_pixcel_table_sum = 0;
				}
				$has_sum = true;
			?>
				<?php include(TRCPDD_PATH . 'reports/methamphetamine_dl.php') ?>
			<?php endif; ?>

		<?php
			trppp_add_sum_desc();

			$sum_content = ob_get_clean();

			if ($has_sum) {
				echo $sum_content;
			}
		}
		?>

		<?php
		//include urine validate & screening
		$have_urine = false;
		$have_screening = false;
		include(TRCPDD_PATH . 'reports/results_urine.php');
		include(TRCPDD_PATH . 'reports/results_screening.php');


		ob_start();
		$has_tox_result = false;

		if (!empty($report['panel_tests_requested']) && !empty($lcms_tests) && !$report['only_screening'] && $can_show_lcms) {


			$columnc = 9;
			if ($hide_detectionwindow_col) $columnc--;
			if ($hide_comments_col) $columnc--;
		?>

			<table class="border tdborder small_table marginbottom">
				<tr class="title_tbl">
					<th style="width:<?php echo $colwidths[0] ?>">Test</th>
					<th style="width:<?php echo $colwidths[1] ?>" class="center">POCT<br><small>Pos/Neg</small></th>
					<th style="width:<?php echo $colwidths[2] ?>" class="center">IA<br><small>Pos/Neg</small></th>
					<th style="width:<?php echo $colwidths[3] ?>" class="center">LCMS<br><small>Pos/Neg</small></th>
					<th style="width:<?php echo $colwidths[4] ?>" class="center">LCMS<br><small>(ng/mL)</small></th>
					<th style="width:<?php echo $colwidths[5] ?>" class="center">Cutoff<br><small>(ng/mL)</small></th>
					<th style="width:<?php echo $colwidths[6] ?>" class="center">Crea Adj.<br><small>Value</small></th>
					<?php if (!$hide_detectionwindow_col) { ?>
						<th style="width:<?php echo $colwidths[7] ?>" class="center">Detection Window</th>
					<?php } ?>
					<?php if (!$hide_comments_col) { ?>
						<th style="width:<?php echo $colwidths[8] ?>">Comments</th>
					<?php } ?>
				</tr>
				<?php

				foreach ($drug_groups as $groupid => $groupdata) {

					if (!$groupdata['show']) continue;


					$found = 0;
					$drugs = $groupdata['drugs'];
					foreach ($drugs as $d) {
						if (isset($all_res[$d])) $found++;
					}
					if (empty($found)) continue;


					ob_start();
					foreach ($drugs as $d) {
						$arr = $all_res[$d];
						if (!empty($arr)) {
							unset($all_res[$d]);

							$arr['group'] = isset($groupdata['name']) ? $groupdata['name'] : '';
							$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);
							if (stripos($arr['drug'], 'Ethanol*') !== false) {
								$ethanol = $arr;
								continue;
							}
							if ($arr['exclude']) {
								continue;
							}
							if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
								continue;
							}
				?>
							<tr class="<?php if ($arr['red_color']) echo 'font_red' ?> <?php if ($arr['is_blue']) echo ' font_blue' ?>">
								<td style="width:<?php echo $colwidths[0] ?>"><?php echo htmlspecialchars($arr['drug']) ?></td>
								<td style="width:<?php echo $colwidths[1] ?>" class="center"><?php echo htmlspecialchars($arr['poct']) ?></td>
								<td style="width:<?php echo $colwidths[2] ?>" class="center"><?php echo htmlspecialchars($arr['ia']) ?></td>
								<td class="center" style="width:<?php echo $colwidths[3] ?> <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo ';font-weight:600' ?>"><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
								<td style="width:<?php echo $colwidths[4] ?>" class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
								<td style="width:<?php echo $colwidths[5] ?>" class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
								<td style="width:<?php echo $colwidths[6] ?>" class="center"><?php echo htmlspecialchars($arr['crea_adj']) ?></td>
								<?php if (!$hide_detectionwindow_col) { ?>
									<td style="width:<?php echo $colwidths[7] ?>"><?php echo $arr['detection'] ?></td>
								<?php } ?>
								<?php if (!$hide_comments_col) { ?>
									<td style="width:<?php echo $colwidths[8] ?>" class="comment"><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
								<?php } ?>
							</tr>
						<?php
						}
					}
					$drug_content = ob_get_clean();
					if (!empty($drug_content)) {
						?>
						<tr>
							<th colspan="<?php echo $columnc ?>"><?php echo htmlspecialchars($groupdata['name']) ?></th>
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

						if (stripos($arr['drug'], 'Ethanol*') !== false) {
							$ethanol = $arr;
							continue;
						}
						if ($arr['exclude']) {
							continue;
						}
						if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
							continue;
						}

					?>
						<tr class="<?php if ($arr['red_color']) echo 'font_red' ?> <?php if ($arr['is_blue']) echo ' font_blue' ?>">
							<td style="width:<?php echo $colwidths[0] ?>"><?php echo htmlspecialchars($arr['drug']) ?></td>
							<td style="width:<?php echo $colwidths[1] ?>" class="center"><?php echo htmlspecialchars($arr['poct']) ?></td>
							<td style="width:<?php echo $colwidths[2] ?>" class="center"><?php echo htmlspecialchars($arr['ia']) ?></td>
							<td class="center" style="width:<?php echo $colwidths[3] ?> <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo ';font-weight:600' ?>"><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
							<td style="width:<?php echo $colwidths[4] ?>" class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
							<td style="width:<?php echo $colwidths[5] ?>" class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
							<td style="width:<?php echo $colwidths[6] ?>" class="center"><?php echo htmlspecialchars($arr['crea_adj']) ?></td>
							<?php if (!$hide_detectionwindow_col) { ?>
								<td style="width:<?php echo $colwidths[7] ?>"><?php echo $arr['detection'] ?></td>
							<?php } ?>
							<?php if (!$hide_comments_col) { ?>
								<td style="width:<?php echo $colwidths[8] ?>" class="comment"><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
							<?php } ?>
						</tr>
					<?php
					}
					$other_content = ob_get_clean();
					if (!empty($other_content)) {
					?>
						<tr>
							<th colspan="<?php echo $columnc ?>">Other</th>
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
						<th colspan="<?php echo $columnc ?>">Alcohol</th>
					</tr>
					<tr>
						<td style="width:<?php echo $colwidths[0] ?>"><?php echo htmlspecialchars($arr['drug']) ?></td>
						<td style="width:<?php echo $colwidths[1] ?>" class="center"><?php echo htmlspecialchars($arr['poct']) ?></td>
						<td style="width:<?php echo $colwidths[2] ?>" class="center"><?php echo htmlspecialchars($arr['ia']) ?></td>
						<td class="center" style="width:<?php echo $colwidths[3] ?> <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo ';font-weight:600' ?>"><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
						<td style="width:<?php echo $colwidths[4] ?>" class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
						<td style="width:<?php echo $colwidths[5] ?>" class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
						<td style="width:<?php echo $colwidths[6] ?>" class="center"><?php echo htmlspecialchars($arr['crea_adj']) ?></td>
						<?php if (!$hide_detectionwindow_col) { ?>
							<td style="width:<?php echo $colwidths[7] ?>"><?php echo $arr['detection'] ?></td>
						<?php } ?>
						<?php if (!$hide_comments_col) { ?>
							<td style="width:<?php echo $colwidths[8] ?>" class="comment"><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
						<?php } ?>
					</tr>
				<?php
				endif;
				?>
			</table>


			<?php include(TRCPDD_PATH . 'reports/methamphetamine_dl.php') ?>

	<?php
		}

		$notes = get_option('ci_notes_result');
		if ($report['show_comment1']) {
			echo '<div><p>';
			echo nl2br(stripslashes($notes[1]));
			echo '</p></div>';
		}
		if ($report['show_comment2']) {
			echo '<div><p>';
			echo nl2br(stripslashes($notes[2]));
			echo '</p></div>';
		}

		$content_tox = ob_get_clean();
		if ($has_tox_result) {
			echo $content_tox;
		}
	} //end if rejected

	include('_approved_by.php');

	if (!empty($report['notes_report'])) {
		echo '<div style="padding-top:15px">';
		echo '<div style="font-weight:bold;padding:0;line-height:22px;">Notes</div>';
		echo nl2br(stripslashes($report['notes_report']));
		echo '</div>';
	}

	if ($has_tox_result && !empty($pdf_notes)) {
		echo '<div class="bottom_notes">';
		echo nl2br(stripslashes(($pdf_notes)));
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