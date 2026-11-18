<?php
global $wpdb;

require_once(TRCPDD_PATH . 'inc/jpgraph442/jpgraph.php');
require_once(TRCPDD_PATH . 'inc/jpgraph442/jpgraph_pie.php');
require_once(TRCPDD_PATH . 'inc/jpgraph442/jpgraph_bar.php');
$upload_dir             = tr_wp_upload_dir();
$logo_dir               = $upload_dir['basedir'] . '/logos/';
$logo_url               = $upload_dir['baseurl'] . '/logos/';
$hmlnames = array('L' => 'Low', 'M' => 'Medium', 'H' => 'High');

$pdf_title = 'Health and Wellness Review';

$clinic_name = 'All';
if ($cli_ID > 0) {
	$clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = {$cli_ID} ");
}

$date_range = tr_pdd_display_date($from_date) . ' - ' . tr_pdd_display_date($to_date);

$where = " re.estatus = 'C' ";
$where .= " and re.approve_date >= '{$from_date}' and re.approve_date <= '{$to_date} 23:59:59' ";
if ($cli_ID > 0) {
	$where .= " and ba.cli_ID = {$cli_ID} ";
}


$meta_keys = array(
	'wellness_score',
	'heart_disease_risk',
	'type2_diabetes_risk',
	'treatments',
	'symptoms',
	'tobacco',
	'alcohol',
	'exercise',
	'Pressure1',
	'Pressure1_2',
	'Pressure2',
	'Pressure2_2',
);

$adding_fields = '';
$adding_join = '';
foreach ($meta_keys as $k) {
	$tblname = $k . 'tbl';
	$adding_fields .= ', ' . $tblname . '.meta_value as ' . $k;
	$adding_join .= " LEFT JOIN ci_batches_blood_meta {$tblname} ON {$tblname}.mid = ba.mid and {$tblname}.meta_key = '{$k}' ";
}

$sql = "select ba.mid,
pa_weight,
pa_height,
pa_dob
{$adding_fields}
from ci_batches_blood ba
JOIN ci_reports_blood re ON re.patient_id = ba.assen
JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
{$adding_join}
WHERE {$where}
";
$rs = $wpdb->get_results($sql);
echo $wpdb->last_error;
$total = count($rs);
if ($total == 0) {
	echo 'No Result';
	return;
}


$report = array();
$report['add_style'] = true;
$report['wellness_score'] = array('A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0);
$report['heart_disease'] = array('L' => 0, 'M' => 0, 'H' => 0);
$report['type2_disease'] = array('L' => 0, 'M' => 0, 'H' => 0);
$report['treatments'] = array();
$report['symptoms'] = array();
$report['tobacco'] = 0;
$report['alcohol'] = 0;
$report['exercise'] = 0;
$report['blood_pressure'] = array(
	'Optimal <120/80' => 0,
	'Normal 120-129/80-84' => 0,
	'High-Normal 130-139/85-89' => 0,
	'High >140/90' => 0,
);
$report['BodyMassIndex'] = array(
	'Underweight <18.5' => 0,
	'Healthy Weight 18.5 - 24.9' => 0,
	'Overweight 25 - 29.9' => 0,
	'Obese >30' => 0,
);

foreach ($rs as $r) {
	if (isset($report['wellness_score'][$r->wellness_score])) {
		$report['wellness_score'][$r->wellness_score]++;
	}
	if (isset($report['heart_disease'][$r->heart_disease_risk])) {
		$report['heart_disease'][$r->heart_disease_risk]++;
	}

	if (isset($report['type2_disease'][$r->type2_diabetes_risk])) {
		$report['type2_disease'][$r->type2_diabetes_risk]++;
	}


	$r->treatments = maybe_unserialize($r->treatments);
	foreach ($r->treatments as $tre) {
		$report['treatments'][$tre]++;
	}
	$r->symptoms = maybe_unserialize($r->symptoms);
	foreach ($r->symptoms as $tre) {
		$report['symptoms'][$tre]++;
	}

	if (strtolower($r->tobacco) == 'yes') {
		$report['tobacco']++;
	}

	if (strtolower($r->alcohol) == 'yes') {
		$report['alcohol']++;
	}

	if (strtolower($r->exercise) == 'yes') {
		$report['exercise']++;
	}


	$Pressureavg = round(($r->Pressure1 + $r->Pressure2) / 2, 0);
	$Pressureavg2 = round(($r->Pressure1_2 + $r->Pressure2_2) / 2, 0);



	if ($Pressureavg > 0) {
		$bpkey = '';
		if ($Pressureavg < 120 && $Pressureavg2 < 80) {
			$bpkey = 'Optimal <120/80';
		} else if ($Pressureavg >= 120 && $Pressureavg <= 129 && $Pressureavg2 >= 80 && $Pressureavg2 <= 84) {
			$bpkey = 'Normal 120-129/80-84';
		} else if ($Pressureavg >= 130 && $Pressureavg <= 139 && $Pressureavg2 >= 85 && $Pressureavg2 <= 89) {
			$bpkey = 'High-Normal 130-139/85-89';
		} else if ($Pressureavg >= 140 && $Pressureavg2 >= 90) {
			$bpkey = 'High >140/90';
		}
		if (!empty($bpkey)) {
			$report['blood_pressure'][$bpkey]++;
		}
	}


	$r->bmi = trpdd_get_bmi($r->pa_height, $r->pa_weight);

	if ($r->bmi > 0) {
		$bmikey = '';
		if ($r->bmi < 18.5) {
			$bmikey = 'Underweight <18.5';
		} else if ($r->bmi < 25) {
			$bmikey = 'Healthy Weight 18.5 - 24.9';
		} else if ($r->bmi < 30) {
			$bmikey = 'Overweight 25 - 29.9';
		} else if ($r->bmi >= 30) {
			$bmikey = 'Obese >30';
		}

		$report['BodyMassIndex'][$bmikey]++;
	}
}



//health score
$healthchartimage = '';
try {
	$chartvalue = array();
	$chartlabels = array();
	$colors = array();
	$colorsarr = array('A' => '#00b050', 'B' => '#ffff00', 'C' => '#ffa909', 'D' => '#DA1431', 'E' => '#BA55D3');
	foreach ($report['wellness_score'] as $k => $vl) {
		if ($vl > 0) {
			$chartvalue[] = $vl;
			$chartlabels[] = $k;
			$colors[] = $colorsarr[$k];
		}
	}

	if (count($chartvalue) > 0) {
		$graph = new PieGraph(300, 250);
		// Set A title for the plot
		$graph->SetBox(true);
		// Create
		$p1 = new PiePlot($chartvalue);
		$p1->SetLegends($chartlabels);
		$graph->Add($p1);
		$p1->ShowBorder();
		$p1->SetColor('black');
		$p1->SetSliceColors($colors);
		$img = $graph->Stroke(_IMG_HANDLER);
		ob_start();
		imagepng($img);
		$healthchartimage = ob_get_contents();
		ob_end_clean();
	}
} catch (Exception $e) {
}



//Heart Disease
$HeartDiseasechartimage = '';
try {
	$chartvalue = array();
	$chartlabels = array();
	$colors = array();
	$colorsarr = array('L' => '#00b050', 'M' => '#ffff00', 'H' => '#DA1431');

	foreach ($report['heart_disease'] as $k => $vl) {
		if ($vl > 0) {
			$chartvalue[] = $vl;
			$chartlabels[] = $hmlnames[$k];
			$colors[] = $colorsarr[$k];
		}
	}
	if (count($chartvalue) > 0) {
		$graph = new PieGraph(300, 250);
		// Set A title for the plot
		$graph->SetBox(true);
		// Create
		$p1 = new PiePlot($chartvalue);
		$p1->SetLegends($chartlabels);
		$graph->Add($p1);
		$p1->ShowBorder();
		$p1->SetColor('black');
		$p1->SetSliceColors($colors);
		$img = $graph->Stroke(_IMG_HANDLER);
		ob_start();
		imagepng($img);
		$HeartDiseasechartimage = ob_get_contents();
		ob_end_clean();
	}
} catch (Exception $e) {
}





//type2 Disease
$type2_diseasechartimage = '';
try {
	$chartvalue = array();
	$chartlabels = array();
	$colors = array();
	$colorsarr = array('L' => '#00b050', 'M' => '#ffff00', 'H' => '#DA1431');

	foreach ($report['type2_disease'] as $k => $vl) {
		if ($vl > 0) {
			$chartvalue[] = $vl;
			$chartlabels[] = $hmlnames[$k];
			$colors[] = $colorsarr[$k];
		}
	}

	if (count($chartvalue) > 0) {
		$graph = new PieGraph(300, 250);
		// Set A title for the plot
		$graph->SetBox(true);
		// Create
		$p1 = new PiePlot($chartvalue);
		$p1->SetLegends($chartlabels);
		$graph->Add($p1);
		$p1->ShowBorder();
		$p1->SetColor('black');
		$p1->SetSliceColors($colors);
		$img = $graph->Stroke(_IMG_HANDLER);
		ob_start();
		imagepng($img);
		$type2_diseasechartimage = ob_get_contents();
		ob_end_clean();
	}
} catch (Exception $e) {
}







$current_treatments_image = '';
try {
	$chartvalue = array();
	$chartlabels = array();

	foreach ($report['treatments'] as $k => $vl) {
		if ($vl > 0) {
			$chartvalue[] = $vl;
			$chartlabels[] = ucfirst($k);
		}
	}

	if (count($chartvalue) > 0) {
		// Create the graph. These two calls are always required
		$graph = new Graph(740, 240, 'auto');
		$graph->SetScale("textlin");
		$theme_class = new UniversalTheme;
		$graph->SetTheme($theme_class);
		$graph->SetBox(false);
		$graph->ygrid->SetFill(false);
		$graph->xaxis->SetTickLabels($chartlabels);
		$graph->xaxis->SetLabelMargin(0);
		$graph->xaxis->SetLabelAngle(26);
		$graph->SetMargin(70, 10, 10, 100);
		$graph->yaxis->HideLine(false);
		$graph->yaxis->HideTicks(false, false);
		$bplot = new BarPlot($chartvalue);
		$bplot->SetWidth(count($chartvalue) / 25);
		$graph->Add($bplot);
		$bplot->SetColor("white");
		$bplot->SetFillColor('#ffff00');
		$img = $graph->Stroke(_IMG_HANDLER);
		ob_start();
		imagepng($img);
		$current_treatments_image = ob_get_contents();
		ob_end_clean();
	}
} catch (Exception $e) {
}

$current_symptoms_image = '';
try {
	$chartvalue = array();
	$chartlabels = array();

	foreach ($report['symptoms'] as $k => $vl) {
		if ($vl > 0) {
			$chartvalue[] = $vl;
			$chartlabels[] = ucfirst($k);
		}
	}

	if (count($chartvalue) > 0) {
		// Create the graph. These two calls are always required
		$graph = new Graph(740, 240, 'auto');
		$graph->SetScale("textlin");
		$theme_class = new UniversalTheme;
		$graph->SetTheme($theme_class);
		$graph->SetBox(false);
		$graph->ygrid->SetFill(false);
		$graph->yscale->ticks->SupressZeroLabel(false);
		$graph->xaxis->SetTickLabels($chartlabels);
		$graph->xaxis->SetLabelMargin(0);
		$graph->xaxis->SetLabelAngle(26);
		$graph->SetMargin(70, 10, 10, 100);
		$graph->yaxis->HideLine(false);
		$graph->yaxis->HideTicks(false, false);
		$bplot = new BarPlot($chartvalue);
		$bplot->SetWidth(count($chartvalue) / 25);
		$graph->Add($bplot);
		$bplot->SetColor("white");
		$bplot->SetFillColor('#ff0000');

		$img = $graph->Stroke(_IMG_HANDLER);
		ob_start();
		imagepng($img);
		$current_symptoms_image = ob_get_contents();
		ob_end_clean();
	}
} catch (Exception $e) {
}





$blood_pressure_image = '';
try {
	$chartvalue = array();
	$chartlabels = array();

	$colors = array();
	$colorsarr = array(
		'Optimal <120/80' => '#00b050',
		'Normal 120-129/80-84' => '#92d050',
		'High-Normal 130-139/85-89' => '#ffff00',
		'High >140/90' => '#ff0000',
	);
	foreach ($report['blood_pressure'] as $k => $vl) {
		$chartvalue[] = $vl;
		$chartlabels[] = $k;
		$colors[] = $colorsarr[$k];
	}

	if (count($chartvalue) > 0) {
		// Create the graph. These two calls are always required
		$graph = new Graph(740, 250, 'auto');
		$graph->SetScale("textlin");
		$theme_class = new UniversalTheme;
		$graph->SetTheme($theme_class);
		$graph->SetBox(false);
		$graph->ygrid->SetFill(false);
		$graph->xaxis->SetTickLabels($chartlabels);
		$graph->yaxis->HideLine(false);
		$graph->yaxis->HideTicks(false, false);
		$bplot = new BarPlot($chartvalue);
		$bplot->SetWidth(count($chartvalue) / 25);
		$graph->Add($bplot);
		$bplot->SetColor("white");
		$bplot->SetFillColor($colors);
		$img = $graph->Stroke(_IMG_HANDLER);
		ob_start();
		imagepng($img);
		$blood_pressure_image = ob_get_contents();
		ob_end_clean();
	}
} catch (Exception $e) {
}




$body_max_indeximage = '';
try {
	$chartvalue = array();
	$chartlabels = array();
	$colors = array();
	$colorsarr = array(
		'Underweight <18.5' => '#00b050',
		'Healthy Weight 18.5 - 24.9' => '#92d050',
		'Overweight 25 - 29.9' => '#ffff00',
		'Obese >30' => '#ff0000'
	);
	foreach ($report['BodyMassIndex'] as $k => $vl) {
		$chartvalue[] = $vl;
		$chartlabels[] = $k;
		$colors[] = $colorsarr[$k];
	}

	if (count($chartvalue) > 0) {
		// Create the graph. These two calls are always required
		$graph = new Graph(740, 250, 'auto');
		$graph->SetScale("textlin");
		$theme_class = new UniversalTheme;
		$graph->SetTheme($theme_class);
		$graph->SetBox(false);
		$graph->ygrid->SetFill(false);
		$graph->xaxis->SetTickLabels($chartlabels);
		$graph->yaxis->HideLine(false);
		$graph->yaxis->HideTicks(false, false);
		$bplot = new BarPlot($chartvalue);
		$bplot->SetWidth(count($chartvalue) / 25);
		$graph->Add($bplot);
		$bplot->SetColor("white");
		$bplot->SetFillColor($colors);
		$img = $graph->Stroke(_IMG_HANDLER);
		ob_start();
		imagepng($img);
		$body_max_indeximage = ob_get_contents();
		ob_end_clean();
	}
} catch (Exception $e) {
}



$footer_height = '0.5';
$margin_bottom = '0';
?>
<style>
	<?php if (!empty($filename)) { ?>@page {
		margin: 1cm 1cm <?php echo $footer_height ?>cm 1cm;
	}

	body {
		margin-top: 3.2cm;
		margin-bottom: <?php echo $margin_bottom ?>cm;
		font-size: 9pt;
		color: #000;
	}

	<?php } ?>table {
		width: 100%;
	}

	.charttbl th {
		width: 33.3%;
		font-size: 14px;
	}

	.charttbl img {
		max-width: 100%;
		height: auto;
	}

	.clientname td {
		padding-top: 15px;
		font-size: 14px;
		text-align: center;
	}

	.lifestylesum {
		width: 80%;
		margin: 0 auto;
	}

	.lifestylesum th {
		vertical-align: middle;
		font-size: 14px;
		font-weight: normal;
	}

	.lifestylesum td {
		width: 80px;
		background: yellow;
		text-align: center;
		font-size: 20px;
		line-height: 30px;
	}

	h3.center {
		margin-bottom: 0.5em;
		margin-top: 0.5em;
	}

	h5.center {
		margin-bottom: 2px;
		font-size: 14px;
	}
</style>
<?php if (!empty($filename)) { ?>
	<div id="header" style="border-bottom:2px solid <?php echo $report['color'] ?>">
		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:34%;height:60px;" valign="top">
					<?php
					$lab = CI_Base_Lab::get_current_lab();
					$address = trpdd_format_address($lab['lab_address'], $lab['lab_city'], $lab['lab_state'], $lab['lab_zip'], true);

					$logo_main = $lab['lab_logo'];

					if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
						$size = getimagesize($logo_dir . $logo_main);
						$max_width = 250;
						$max_height = ($size[0] > $size[1]) ? 70 : 88;

						echo '<img border="0" style="max-width: ' . $max_width . 'px; height: auto;max-height:' . $max_height . 'px;margin-top:-5px" src="' . $logo_dir . $logo_main . '" />';
					}

					?>

				</td>
				<td style="width:38%;text-align: center">
					<h2 style="color:<?php echo $report['color'] ?>;font-size:18px;font-weight: normal;"><?php echo $pdf_title ?></h2>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">
					<div><?php echo $lab['lab_name'] ?></div>
					<div><?php echo $report['header_lab_address'] ?></div>
					<?php if (!tr_get_option('ci_pdf_data', 'report_hilaphone') && !empty($lab['lab_phone'])) { ?>
						<div>Phone: <?php echo $lab['lab_phone'] ?></div>
					<?php } ?>
					<?php if (!empty($lab['lab_clia'])) { ?>
						<div>CLIA ID: <?php echo $lab['lab_clia'] ?></div>
					<?php } ?>
				</td>
			</tr>
		</table>

		<table class="clientname">
			<tr>
				<td><label>Client: </label><?php echo $clinic_name ?></td>
				<td><label>Date(s): </label><?php echo $date_range ?></td>
				<td><label>Employees: </label><?php echo $total ?></td>
			</tr>
		</table>
	</div>
<?php } else {
?>
	<table class="clientname" style="max-width:900px;margin:10px auto">
		<tr>
			<td><label>Client: </label><?php echo $clinic_name ?></td>
			<td><label>Date(s): </label><?php echo $date_range ?></td>
			<td><label>Employees: </label><?php echo $total ?></td>
		</tr>
	</table>
<?php
} ?>

<div class="wrappdf" style="background:#fff;max-width:900px;margin:0px auto;">
	<table class="charttbl">
		<tr>
			<th>Overall Health Score</th>
			<th>Heart Disease Risk</th>
			<th>Type 2 Diabetes Risk</th>
		</tr>
		<tr>
			<td>
				<img src="data:image/png;base64,<?php echo (base64_encode($healthchartimage)); ?>" />
			</td>
			<td>
				<img src="data:image/png;base64,<?php echo (base64_encode($HeartDiseasechartimage)); ?>" />
			</td>
			<td>
				<img src="data:image/png;base64,<?php echo (base64_encode($type2_diseasechartimage)); ?>" />
			</td>
		</tr>
	</table>

	<h3 class="center">Current Treatments and Symptoms Summary</h3>
	<h5 class="center">Current Treatments</h5>
	<div class="center"><img src="data:image/png;base64,<?php echo (base64_encode($current_treatments_image)); ?>" /></div>

	<h5 class="center" style="margin-top:20px">Current Symptoms</h5>
	<div class="center"><img src="data:image/png;base64,<?php echo (base64_encode($current_symptoms_image)); ?>" /></div>

	<div class="break"></div>
	<h3 class="center">Lifestyle Summary</h3>

	<table class="lifestylesum">
		<tr>
			<th>Percentage reporting frequent use of Tobacco</th>
			<td><?php echo number_format($report['tobacco'] / $total * 100, 1) . '%' ?></td>
		</tr>
		<tr>
			<th>Percentage reporting frequent Alcohol consumption</th>
			<td><?php echo number_format($report['alcohol'] / $total * 100, 1) . '%' ?></td>
		</tr>
		<tr>
			<th>Percentage reporting optimal weekly minutes of exercise</th>
			<td><?php echo number_format($report['exercise'] / $total * 100, 1) . '%' ?></td>
		</tr>
	</table>
	<br><br>
	<h3 class="center">Vitals & Body Mass Index Summary</h3>
	<h5 class="center">Blood Pressure</h5>
	<div class="center"><img src="data:image/png;base64,<?php echo (base64_encode($blood_pressure_image)); ?>" /></div>
	<br>
	<h5 class="center">Body Mass Index</h5>
	<div class="center"><img src="data:image/png;base64,<?php echo (base64_encode($body_max_indeximage)); ?>" /></div>
</div>