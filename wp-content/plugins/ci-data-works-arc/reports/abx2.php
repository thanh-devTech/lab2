<?php
if (isset($_GET['debugabx']) && isset($_COOKIE['trquery_monitor'])) {
	echo '<br>file: abx2.php<br>';
}
if (!isset($abx)) {
	$abx = new CI_ABX();
}

if (count($abx->getSelectedBacterias()) == 0) {
	return;
}
$use_piechart = (isset($use_piechart) && $use_piechart) || tr_get_option('ci_pdf_data', 'new_abx_pie');

if ($use_piechart) {
	$abx->sort_Markers();
}

$markernames 	= $abx->getMarkerNames();
$abxclasses 	= $abx->getAbxClasses();

$has_pair 		= false;
$has_abxnotes 	= false;
$has_pediatric_comment 	= false;

if (isset($_GET['debugorphan'])) {
	$abx->has_Orphan = true;
}

$typename = $abx->get_typename();
if ($typename == 'Wound') {
	$typename = 'Localized SSTI';
} else if ($typename != 'cUTI') {
	$typename = 'Simple ' . $typename;
}

$headertext2 = 'Criteria: ' . $typename;
if ($abx->Pediatric_ranges && !empty($abx->age_ranges)) {
	$headertext2 .= ', Pediatric ' . $abx->age_ranges;
}
if ($abx->PrioritizeOral) {
	$headertext2 .= ', Prioritize Oral Options';
}

if ($abx->hasExcludeDetectedLow) {
	$headertext2 .= ', Exclude Detected-Low Pathogen';
}

if ($abx->hasExcludeRiskLow) {
	$headertext2 .= ', Exclude Low-Risk Pathogen';
}

if (!empty($abx->selectedState)) {
	$headertext2 .= ', Est. Res. for ' . $abx->selectedState;
}


/*
$pathogencities = array(
	'Low: Common commensal or contaminant. Treat only if symptomatic. If more pathogenic bacteria is present, it may be excluded from algorithmic results (shaded column).',
	'Medium-low: Likely to cause uncomplicated infections.',
	'Medium-high: May cause complicated or harder-to-treat infections.',
	'High: Likely to cause complicated or difficult-to-treat infections due to multiple resistances.',
	'Critical: Likely to present MDR with few treatment options. For complicated infections, <b>infectious disease consultation is recommended.</b>'
);
*/
$short_pathogencities = array('Low', 'Medium-low', 'Medium-high', 'High', 'Critical');

$abxtabledata = array();
foreach ($abx->listABXMatches() as  $arr) {

	$ES = array();
	$arrabx1 = $abx->result_abx[$arr['ID']];
	$arrabx2 = isset($arr['ID2']) ? $abx->result_abx[$arr['ID2']] : array();

	foreach ($abx->getSelectedBacterias() as $bat) {
		if ($bat->is_hide || $bat->isexclude) continue;
		$esb = isset($arrabx1['scores'][$bat->ID]) ? $arrabx1['scores'][$bat->ID]['ES'] : 0;
		if (isset($arr['ID2'])) {
			$esb = max($esb, $arrabx2['scores'][$bat->ID]['ES']);
		}
		$ES[] = $esb;
		if ($bat->lmh == 'H') {
			$ES[] = $esb;
			$ES[] = $esb; //2 times by H
		} else if ($bat->lmh == 'M') {
			$ES[] = $esb;
		}
	}

	$arr['ES'] 			= array_sum($ES) / count($ES);
	$arr['iconcode'] 	= $arrabx1['iconcode'];
	$arr['iconcode2'] 	= '';

	$arr['abx_risk'] 	= $arrabx1['abx_risk'];
	$arr['C'] 			= $arrabx1['C'];
	$arr['S'] 			= $arrabx1['S']; //AS


	if (isset($arr['ID2'])) {
		$arr['iconcode2'] 	= $arrabx2['iconcode'];
		$arr['abx_risk'] 	= max($arrabx1['abx_risk'], $arrabx2['abx_risk']);
		$arr['C'] 			= max($arrabx1['C'], $arrabx2['C']);
		$arr['S'] 			= max($arrabx1['S'], $arrabx2['S']);
		$has_pair 			= true;
	}

	if (!empty($arr['notes'])) {
		$has_abxnotes = true;
	}

	if (!empty($arr['pediatric_comment'])) {
		$has_pediatric_comment = true;
	}


	$abxtabledata[] = $arr;
	if (count($abxtabledata) >= 4) break;
}

if (count($abx->getSelectedBacterias()) == 1) {
	foreach ($abx->getSelectedBacterias() as $bat) {
		if ($bat->risk == $abx->bat_max_risk() || $bat->risk > 10) {
			$abxtabledata = array();
		}
	}
}

if (count($abxtabledata) < 4) {
	$cb = count($abxtabledata);
	for ($i = 0; $i < 4 - $cb; $i++) {
		$abxtabledata[] = array(
			'name' => 'NULL'
		);
	}
}


$pathogen_rows 	= array();
$irow 			= 0;
$ICS1 			= 0;
$ICS2 			= 0;
$count_bacs		= 0;
$max_ICS2		= 0;

foreach ($abx->getSelectedBacterias() as $bat) {
	if (isset($bat->is_hide) && $bat->is_hide) continue;

	if (!$bat->isexclude) {
		$risk 		= $abx->get_bat_risk($bat->ID, 'ics');
		$ICS1 		= max($ICS1, $risk['number']);
		$max_ICS2 	= max($max_ICS2, $risk['mmaxrisk']);

		$bat->bmrisk 	= $risk['number'];
		$bat->risk_color = $risk['color'];

		$count_bacs++;
	}


	if (!isset($pathogen_rows[$irow])) {
		$pathogen_rows[$irow] = array();
	}

	$pathogen_rows[$irow][] = $bat;
	if (count($pathogen_rows[$irow]) >= 2) {
		$irow++;
	}
}

if ($count_bacs == 2) {
	$ICS1 = max($ICS1, 3);
} else if ($count_bacs == 3) {
	$ICS1 = max($ICS1, 4);
} else if ($count_bacs == 4) {
	$ICS1 = max($ICS1, 5);
} else if ($count_bacs >= 5) {
	$ICS1 = max($ICS1, 6);
}

$ICS2 = min($ICS1, $max_ICS2 > 0 ? $max_ICS2 : $ICS1);

if ($ICS1 > 10) $ICS1 = 10;
if ($ICS2 > 10) $ICS2 = 10;

if ($abx->debug) {
	$abx->log .= "ICS1= {$ICS1} <br>";
	$abx->log .= "ICS2= {$ICS2} (using)<br>";
}


$ICS1 = $ICS2;

if ($abx->Biofilm_Risk_infection > 4 && $abx->Biofilm_Risk_infection <= 6) {
	$ICS1 = max($ICS1, 5); //min=5
	if ($abx->debug) {
		$abx->log .= " new ICS1= {$ICS1}  by Biofilm_Risk_infection = {$abx->Biofilm_Risk_infection}<br>";
	}
} else if ($abx->Biofilm_Risk_infection > 6 && $abx->Biofilm_Risk_infection <= 8) {
	$ICS1 = max($ICS1, 6); //min=6
	if ($abx->debug) {
		$abx->log .= " new ICS1= {$ICS1}  by Biofilm_Risk_infection = {$abx->Biofilm_Risk_infection}<br>";
	}
} else if ($abx->Biofilm_Risk_infection > 8 && $abx->Biofilm_Risk_infection <= 12) {
	$ICS1 = max($ICS1, 7); //min=7
	if ($abx->debug) {
		$abx->log .= " new ICS1= {$ICS1}  by Biofilm_Risk_infection = {$abx->Biofilm_Risk_infection}<br>";
	}
}


$bacteria_text = $abx->getEstimated_Inf_Comp_Text($ICS1);
$virus_comment = $abx->get_Virus_comments();

if ($count_bacs > 1 && $abx->Biofilm_Risk_infection == 0 && empty($virus_comment)) {
	$bacteria_text .= ' The presence of multiple pathogens may complicate this infection and limit therapeutic choices.';
}
if ($abx->has_Orphan) {
	$bacteria_text = str_replace(':', '*:', $bacteria_text);

	$bacteria_text .= '<div class="smalltext1" style="margin-top:5px;">* Resistance due to orphan markers not included in the estimate.</div>';
}




echo '<div class="abxstyle2">';

if ($use_piechart) {

	require_once((defined('TRHUB_PATH') ? TRHUB_PATH : TRCPDD_PATH) . 'inc/jpgraph442/jpgraph.php');
	require_once((defined('TRHUB_PATH') ? TRHUB_PATH : TRCPDD_PATH) . 'inc/jpgraph442/jpgraph_pie.php');


	$bats 		= $abx->getSelectedBacterias();
	uasort($bats, function ($a, $b) {
		return $a->load > $b->load ? -1 : 1;
	});

	$healthchartimage 	= '';
	$total_load			= 0;
	$total_ra			= 0;
	$chartvalue  		= array();
	$chart_labes  		= array();
	$chart_colors  		= array();
	//$colors 			= array('#9ccb40', '#ebeb31', '#ec8b1b', '#f04e12', '#dddfff');
	$colors 			= array('#f04e12', '#eb8a19', '#fbb925', '#bb4e90', '#cf6ba7', '#8b5ea5', '#9086cf', '#c94d2b', '#f47774', '#797b44', '#797b44');
	$inum 				= 0;
	foreach ($bats as $bat) {
		if ($bat->is_hide || $bat->lmh === 'D') continue;


		$total_load += $bat->load;

		$total_ra += $bat->ra;
		$chartvalue[$inum] = $bat->load; //changed from ra to load 18/12/24
		$chart_labes[$inum] = '';
		$chart_colors[$inum] = $colors[$inum];
		$inum++;
	}

	if (count($chartvalue) > 0) {
		foreach ($chartvalue as $i => $vl) {
			$newvl = $total_load > 0 ? $vl * 100 / $total_load : 0;
			if ($newvl < 0.55) {
				$newvl = 0.55;
			}
			$chartvalue[$i] = $newvl;
		}

		$piesize 			= 35;
		$chart_total_load = $total_load;
		if (tr_get_option('ci_pdf_data', 'new_abx_piefactor10')) {
			$chart_total_load = $chart_total_load / 10;
		}
		if ($chart_total_load < 1000) {
			//very low
			$piesize = $chart_total_load * 34 / 1000;
		} else if ($chart_total_load < 10000) {
			//low
			$piesize = (($chart_total_load - 1000) / 10000) * 30  + 36;
		} else if ($chart_total_load < 100000) {
			//med
			$piesize = (($chart_total_load - 10000) / 100000) * 30  + 65;
		} else if ($chart_total_load < 1000000) {
			//high
			$piesize = (($chart_total_load - 100000) / 1000000) * 29  + 94;
		} else {
			//very high
			$piesize = (($chart_total_load - 1000000) / 10000000) * 29  + 122;
		}
		if ($piesize < 14) {
			$piesize = 14;
		} else if ($piesize > 150) {
			$piesize = 150;
		}
		$piesize = number_format($piesize, 2);

		if (isset($_GET['debugabx2'])) {
			var_dump($chart_total_load);
			var_dump($piesize);
			$piesize = 90;
		}

		try {
			$graph = new PieGraph(300, 300);
			$graph->SetMarginColor('#f6f6f6');
			// Set A title for the plot
			$graph->SetBox(false);
			// Create
			$p1 = new PiePlot($chartvalue);
			$p1->SetSize($piesize); //150  121  93 65  35

			$p1->SetLabels($chart_labes);
			$graph->Add($p1);
			//$p1->ShowBorder();
			//$p1->SetColor('black');
			$p1->SetCenter(0.5, 0.5);
			$p1->SetSliceColors($colors);
			$img = $graph->Stroke(_IMG_HANDLER);
			ob_start();
			imagepng($img);
			$healthchartimage = ob_get_contents();
			ob_end_clean();
		} catch (Exception $e) {
		}
	}




?>
	<link href="<?php echo $asset_url . 'css/abx_pie.css' ?>" rel='stylesheet' />
	<table class="reporttblstyle2 toptable3bpx" style="margin-bottom:10px;">
		<tr class="header1">
			<td style="width:25%">DETECTED:</td>
			<td style="width:25%" class="center">BACTERIAL LOAD + COMPOSITION</td>
			<td style="width:50%" class="center">ESTIMATED INFECTION COMPLEXITY</td>
		</tr>
		<tr>
			<td class="nopadding detectedblock">
				<div class="header1">PATHOGENS<?php if (!empty($abx->pathogen_detected_notes)) echo '*' ?></div>
				<div class="top_pathogens top_pathogens_bac">
					<?php
					$inum 		= 0;
					$note_num 	= 1;
					$bac_notes 	= array();
					$has_detected_D = false;
					foreach ($bats as $bat) {
						if (!$bat->is_hide) {
							$bac_name = $bat->pathogen_name;

							echo '<div class="item">';
							echo $bac_name;
							if ($bat->lmh !== 'D') {
								echo ' <span class="abxroundcic fright" style="background:' . $chart_colors[$inum] . '"></span>';
								$inum++;
							} else {
								echo '*';
								$has_detected_D = true;
							}
							echo ' </div>';
						}
					}

					if (!empty($abx->pathogen_detected_notes)) {
						echo '<div class="pathogens_notes shownoteinbottom">';
						echo  '*' . $abx->pathogen_detected_notes;
						echo '</div>';
					}

					if ($has_detected_D) {
						echo '<div class="pathogens_notes shownoteinbottom">';
						echo  '*Qualitative detection, no load available';
						echo '</div>';
					}

					?>
				</div>
				<?php if ($abx->enable_specimen_respiratory_algo && count($abx->virus_detected_list) > 0) { ?>
					<div class="header1" style="margin-top:10px;">VIRUSES:</div>
					<div class="top_pathogens top_pathogens_bac">
						<?php
						foreach ($abx->virus_detected_list as $vrs) {
							echo '<div class="item">' . $vrs['name'] . '</div>';
						}
						?>
					</div>
				<?php
				} ?>

				<div class="header1">RESISTANCE MARKERS</div>
				<div class="top_pathogens top_pathogens_mkr p2cols">
					<?php
					if (count($markernames) > 0) {
						foreach ($markernames as $mkrarr) {
							echo '<div class="item">' . $mkrarr['name'] . '</div>';
						}
					} else if ($abx->hasTestMakers) {
						echo '<div class="item">None Detected</div>';
					} else {
						echo '<div class="item">None Tested</div>';
					}


					if ($abx->has_Orphan) {
					?>
						<div class="legend_notes">
							<div class="note_item">* Orphan marker/abx class</div>
						</div>
					<?php }
					?>
				</div>
			</td>
			<td class="nopadding center">
				<?php include('abx/pie_load.php'); ?>
			</td>
			<td class="nopadding biofilmareatd">
				<div class="center">
					<div class="arrowdown2wrap" style="width:320px;">
						<div class="arrow-down2" style="margin-left:<?php echo ($ICS1 - 1) * 32.8 ?>px">
							<img src="<?php echo $asset_url . 'reports/abx/arrowdown2.png' ?>" />
						</div>
					</div>
					<img class="infectionslide" src="<?php echo $asset_url . 'reports/abx/infection.png' ?>" style="width:340px" />
					<table class="infection_tbl" style="width:300px;margin:0 auto">
						<tr>
							<td style="width:56px">LOW</td>
							<td style="width:68px">MEDIUM</td>
							<td style="width:75px">MED-HIGH</td>
							<td style="width:55px">HIGH</td>
							<td style="text-align: right">CRITICAL</td>
						</tr>
					</table>
				</div>
				<?php
				echo '<div class="infectionlevel">' . $bacteria_text . '</div>';
				echo $virus_comment;

				if ($abx->Biofilm_Risk_infection !== null) {
					echo '<div class="biofilmspace"></div>';
				}
				?>
			</td>
		</tr>
		<?php

		if ($abx->Biofilm_Risk_infection !== null) {
			echo '<tr class="biofilmrow"><td></td><td></td><td>';
			$bioicon = $asset_url . 'reports/abx/biofilm_' . $abx->Biofilm_Icon . '.png';
			echo '<div class="biofilm_risk"><img class="biofilmicon" src="' . $bioicon . '"/> <span class="biofilmtxt">' . $abx->Biofilm_Text . ' BIOFILM RISK</span></div>';
			echo '</td></tr>';
		}
		?>
	</table>
	<?php
	if (!empty($abxclasses)) {

	?>
		<table class="reporttblstyle2 abxclassestbl" style="margin-bottom: 10px;">
			<tr class="header3">
				<td colspan="4">
					DETECTED RESISTANCE: ABX CLASSES
					<?php if ($abx->has_marker_Q) { ?>
						<span class="abxcllmh"><span class="abxlmh_high"></span> HIGH</span>
						<span class="abxcllmh"><span class="abxlmh_medium"></span> MEDIUM</span>
						<span class="abxcllmh"><span class="abxlmh_low"></span> LOW</span>
					<?php } else { ?>
						<span class="abxcllmh"><span class="abxlmh_high"></span> DUE TO PAIRED MARKERS</span>

					<?php } ?>
					<span class="abxcllmh"><span class="abxlmh_orphan"></span> DUE TO ORPHAN MARKER (RM not associated with detected pathogens)</span>
				</td>
			</tr>
			<?php
			echo '<tr>';
			$ipos = 0;
			foreach ($abxclasses as $clarr) {
				if ($ipos == 4) {
					echo '</tr><tr>';
					$ipos = 0;
				}
				$ipos++;
				$abxcclass = '';
				if ($clarr['lmh'] > 0) {
					$abxcclass = 'abxlmh_' .  str_replace(array(' ', 'v.'), '', strtolower($abx->lmh_num2text($clarr['lmh'])));
				} else if ($clarr['isorphan']) {
					$abxcclass = 'abxlmh_orphan';
				} else if (!$abx->has_marker_Q) {
					$abxcclass = 'abxlmh_high';
				}

				echo '<td class="item center ' . $abxcclass . '">' . $clarr['name'] . ' </td>';
			}
			for ($iad = 0; $iad < 4 - $ipos; $iad++) {
				echo '<td></td>';
			}
			echo '</tr>';
			?>
		</table>
	<?php
	}
	?>
<?php
} else {
?>

	<table class="reporttblstyle2 toptable3bpx" style="margin-bottom:10px;">
		<tr>
			<td style="width:50%;padding:5px 2px;vertical-align:top">
				<table>
					<tr>
						<td style="width:40px;text-align:center;border:none;">
							<div style="padding-bottom:8px;padding-top:1px">PATHOGENS <?php if (!empty($abx->pathogen_detected_notes)) echo '*' ?></div>
							<img src="<?php echo $asset_url . 'reports/abx/bacteria.png' ?>" style="width:36px" />
						</td>
						<td style="padding: 0 8px;width:180px;border:none;vertical-align:top">
							<div class="top_pathogens top_pathogens_bac">
								<?php
								$note_num = 1;
								$bac_notes = array();
								foreach ($abx->getSelectedBacterias() as $bat) {
									if ($bat->is_hide) continue;

									$bac_name = $bat->pathogen_name;
									if (isset($bat->notes) && !empty($bat->notes)) {

										$numexist = array_search($bat->notes, $bac_notes);
										if ($numexist > 0) {
											$numnow = $numexist;
										} else {
											$numnow = $note_num++;
										}
										$bac_name .= '<span class="sup">' . $numnow . '</span>';
										$bac_notes[$numnow] = $bat->notes;
									}

									echo '<div class="item">' . $bac_name . '</div>';
								}
								?>
							</div>
							<?php
							if (!empty($bac_notes)) {
								echo '<div class="legend_notes">';
								foreach ($bac_notes as $num => $note) {
									$notecontent = $num . '. ' . $note;
									echo '<div class="note_item">' . $notecontent . '</div>';
								}
								echo '</div>';
							}

							if (!empty($abx->pathogen_detected_notes)) {
								echo '<div class="pathogens_notes shownoteinbottom">';
								echo  '*' . $abx->pathogen_detected_notes;
								echo '</div>';
							}

							?>

						</td>
					</tr>
				</table>
			</td>
			<td rowspan="2" style="padding:0;">
				<div class="center headerestimated">ESTIMATED INFECTION COMPLEXITY</div>
				<?php if (!$abx->hide_infection_complexity) { ?>
					<div class="center">
						<div style="width:320px;margin:0 auto">
							<div class="arrow-down" style="margin-left:<?php echo ($ICS1 - 1) * 32.8 ?>px"></div>
						</div>
						<img src="<?php echo $asset_url . 'reports/abx/infection.png' ?>" style="width:340px" />
						<table class="infection_tbl" style="width:300px;margin:0 auto">
							<tr>
								<td style="width:56px">LOW</td>
								<td style="width:68px">MEDIUM</td>
								<td style="width:75px">MED-HIGH</td>
								<td style="width:55px">HIGH</td>
								<td style="text-align: right">CRITICAL</td>
							</tr>
						</table>
					</div>
					<div class="infectionlevel"><?php echo $bacteria_text ?></div>
				<?php } else {
				?>
					<div class="center hide_infection_complexity">
						<img src="<?php echo $asset_url . 'reports/abx/prohibition.png' ?>" />
						<div class="hide_infection_complexity_text">This metric has been excluded from this report.</div>
					</div>
				<?php
				} ?>
			</td>
		</tr>
		<tr>
			<td style="padding:5px 2px">
				<table>
					<tr>
						<td style="width:40px;text-align:center;border:none;vertical-align:top">
							<div style="padding-bottom:8px;padding-top:1px">RESISTANCE</div>
							<img src="<?php echo $asset_url . 'reports/abx/markers.png' ?>" style="width:36px" />
						</td>
						<td style="padding: 0 8px;width:180px;border:none;vertical-align:top">
							<div style="color: #000;">
								<?php
								$has_rm = false;

								if (!empty($abxclasses)) {
									$abxclassnames = array();
									foreach ($abxclasses as $arrc) {
										$abxclassnames[] = $arrc['name'];
									}
									echo '<div style="font-size:11px;line-height:13px">';
									echo '<strong class="textgrey">ABX Classes: </strong>' . implode(', ', $abxclassnames);
									echo '</div>';
									$has_rm = true;
								}


								if (!empty($markernames)) {
									$markernamesarr = array();
									foreach ($markernames as $arrc) {
										$markernamesarr[] = $arrc['name'];
									}
									echo '<div style="font-size:11px;line-height:13px">';
									echo '<strong class="textgrey">RM: </strong>' . implode(', ', $markernamesarr);
									echo '</div>';
									$has_rm = true;
								}

								if (!$has_rm) {
									if ($abx->hasTestMakers) {
										echo '<div style="font-size:11px;line-height:13px">None Detected</div>';
									} else {
										echo '<div style="font-size:11px;line-height:13px">None Tested</div>';
									}
								}

								if (isset($_GET['debugorphan'])) {
									$abx->has_Orphan = true;
								}

								?>
							</div>
							<?php
							if ($abx->has_Orphan) {
							?>
								<div class="legend_notes" style="padding-top:8px">
									<div class="note_item">* Orphan marker/abx class</div>
								</div>
							<?php } ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
<?php } ?>


<table class="reporttblstyle2" style="width:100%;margin:0">
	<tr class="header">
		<td style="border:none;vertical-align:middle;text-transform:uppercase;padding:3px 10px">
			<?php echo tr_get_option('ci_pdf_data', 'new_abx_change_treatment_title') ? 'Treatment Options' : 'Top Algorithmic Antibiotic Options' ?>
		</td>
		<td style="border:none;font-size:9px;vertical-align:middle;text-align:right;padding:3px 10px"><?php echo $headertext2 ?></td>
	</tr>
</table>

<table class="abxtoptbl abxestimatedtbl <?php if ($abx->Biofilm_Risk_infection > 4) echo 'hasbaicon' ?>" style="margin-bottom:10px">
	<?php

	$rowskey = array('header', 'estimated', 'chart', 'dose', 'dose2', 'notes', 'pediatric_comment', 'icon', 'trimester', 'pregnancy_note');

	if ($abx->show_pregnancy) {
		$rowskey = array('header', 'estimated', 'chart', 'dose', 'dose2',  'notes', 'trimester', 'pregnancy_note', 'pediatric_comment', 'icon');
	}
	foreach ($rowskey as $k) {
		if ($k == 'dose2' && !$has_pair) {
			continue;
		} else if ($k == 'notes' && !$has_abxnotes) {
			continue;
		} else if ($k == 'pediatric_comment' && !$has_pediatric_comment) {
			continue;
		} else if (in_array($k, array('trimester', 'pregnancy_note')) && !$abx->show_pregnancy) {
			continue;
		}
		echo '<tr class="' . $k . '">' . "\n";
		foreach ($abxtabledata as $arr) {

			if (isset($arr['is_pair']) && $arr['is_pair'] && $arr['both_oral']) {
				$arr['brand_name'] .= '*';
				$arr['dose1']  = ucwords(strtolower($arr['brand_name1'])) . ': ' . $arr['dose1'];
				$arr['dose2']  = ucwords(strtolower($arr['brand_name2'])) . ': ' . $arr['dose2'];
			}

			echo '<td>';
			switch ($k) {
				case 'header':
					echo '<span class="headertext">' . $arr['name'] . '</span>';
					if ($abx->Biofilm_Risk_infection > 4) {
						$ba_icon = '-';
						if ($arr['BA'] == 1) {
							$ba_icon = '±';
						} else if ($arr['BA'] == 2) {
							$ba_icon = '+';
						}
						echo '<span class="baicon">ba' . $ba_icon . '</span>';
					}
					break;

				case 'estimated':
					echo 'Estimated Infection Susceptibility';
					break;

				case 'chart':

					$ro = - (50 - $arr['ES']) * 90 / 50;


					echo '<div class="gaugediv" title="' . $arr['ES'] . '">' . "\n";
					echo '<img class="gauge" src="' . $asset_url . 'reports/abx/abx-activity-gauge.png' . '">' . "\n";
					echo '<img class="gauge-indicator" style="transform: rotate(' . $ro . 'deg);" src="' . $asset_url . 'reports/abx/gauge-indicator.png' . '" >' . "\n";
					echo '</div>' . "\n";
					echo '<div style="margin-top:10px">' . ucwords(strtolower($arr['brand_name'])) . '</div>' . "\n";
					if (isset($arr['is_pair']) && $arr['is_pair'] && $arr['both_oral']) {
						//echo '<div class="pairedtext smalltext1">* Paired for max. oral coverage.</div>' . "\n";
					}
					break;
				case 'dose':
					if (!empty($arr['dose1'])) {
						echo ' <div class="smalltext2">' . $arr['dose1'] . '</div>' . "\n";
					}
					break;
				case 'dose2':
					if (!empty($arr['dose2'])) {
						echo ' <div class="smalltext2">' . $arr['dose2'] . '</div>' . "\n";
					}
					break;
				case 'pediatric_comment':
					echo $arr['pediatric_comment'];
					break;
				case 'icon':

					$has_pill = $arr['iconcode'] == 'pill' || (isset($arr['iconcode2']) && $arr['iconcode2'] == 'pill');
					$has_syringe = $arr['iconcode'] == 'syringe' || (isset($arr['iconcode2']) && $arr['iconcode2'] == 'syringe');

					echo '<span class="groupicons psicons">' . "\n";
					echo '<img class="iconpill ' . ($has_pill ? 'active' : 'notactive') . '" src="' . $asset_url . 'reports/abx/pill.png" />' . "\n";
					echo '<img class="iconpill ' . ($has_syringe ? 'active' : 'notactive') . '" src="' . $asset_url . 'reports/abx/syringe.png" />' . "\n";
					echo '</span>' . "\n";

					echo '<span class="groupicons aricons">' . "\n";
					for ($i = 1; $i <= 5; $i++) {
						echo '<span class="charicon ' . ($arr['abx_risk'] >= $i ? 'active' : 'notactive') . '">R</span>' . "\n";
					}
					echo '</span>' . "\n";

					echo '<span class="groupicons dlicons">' . "\n";
					for ($i = 1; $i <= 5; $i++) {
						echo '<span class="charicon ' . ($arr['C'] >= $i ? 'active' : 'notactive') . '">$</span>' . "\n";
					}
					echo '</span>' . "\n";

					echo '<span class="groupicons qicons">' . "\n";
					for ($i = 1; $i <= 5; $i++) {
						echo '<span class="charicon ' . ($arr['S'] >= $i ? 'active' : 'notactive') . '"><img src="' . $asset_url . 'reports/abx/diamond.png" style="width:9px;margin-top:5px" /></span>' . "\n";
					}
					echo '</span>' . "\n";
					break;

				case 'trimester':

					foreach (array('1', '2', '3', 'l') as $trk) {
						echo '<span class="trimester_item trimester_' . $arr['trimester_' . $trk]  . '">' . $trk . '</span>' . "\n";
					}

					break;

				case 'pregnancy_note':
					echo $arr['pregnancy_note'];
					break;

				case 'notes':
					if (isset($arr['notes']) && !empty($arr['notes'])) {
						echo $arr['notes'];
					}
					break;
			}

			echo '</td>' . "\n";
		}
		echo '</tr>' . "\n";
	}

	echo '<tr><td colspan="4" class="legendicons">';

	if ($abx->Biofilm_Risk_infection > 4) {
		echo '<span class=" psicons">';
		echo '<span class="baicon">ba</span> Biofilm Activity';
		echo '</span>';
		echo '<span class="spliticon">|</span>';
	}
	echo '<span class=" psicons">';
	echo '<img class="iconpill" src="' . $asset_url . 'reports/abx/pill.png" />';
	echo ' Oral Admin';
	echo '</span>';

	echo '<span class="spliticon">|</span>';

	echo '<span class=" psicons">';
	echo '<img class="iconpill" src="' . $asset_url . 'reports/abx/syringe.png" />';
	echo ' IV/IM Admin';
	echo '</span>';

	echo '<span class="spliticon">|</span>';

	echo '<span class=" psicons">';
	echo 'R - ';
	echo ' Adv. Effects Risk';
	echo '</span>';

	echo '<span class="spliticon">|</span>';

	echo '<span class=" psicons">';
	echo '$ - ';
	echo ' Cost Level Est';
	echo '</span>';

	echo '<span class="spliticon">|</span>';

	echo '<span class=" psicons">';
	echo '<img src="' . $asset_url . 'reports/abx/diamond.png" style="width:9px;margin-top:5px" />';
	echo ' Stewardship Rank';
	echo '</span>';

	echo '<span class="spliticon">|</span>';

	echo '<span>* Paired for Max. Oral Coverage</span>';

	echo '</td></tr>';
	?>

</table>

<div class="quantitynotes">
	<?php
	if ($abx->Quantitative) {
		echo '<span class="red">PLEASE NOTE:</span> Detection of bacterial DNA does not always indicate an active infection requiring treatment. Top Options may not cover all detected pathogens when exclusion rules apply. Algorithmic recommendations are based on configured criteria and may not account for patient-specific factors, such as infection site, severity, allergies, or comorbidities. Clinical judgment is essential in determining the appropriateness of treatment. Please refer to the Infection Antibiogram for additional context.';
	} else {
		echo '<span class="red">PLEASE NOTE:</span> The presence of bacterial DNA does not always indicate an active infection requiring treatment. In addition, the selection algorithm addresses all detected pathogens equally when bacterial load is not provided, which may result in over-targeting the infection. Overuse of antibiotics can lead to resistance and other adverse effects. Clinical judgment is essential in determining the appropriateness of treatment. Please refer to the Personalized Antibiogram for alternative options.';
	}
	?>
</div>

<?php if ($count_bacs > 0) {
	echo '<div class="dontbreakinside">';
?>
	<table class="reporttblstyle2">
		<tr class="header">
			<td colspan="2" style="border:none">PATHOGENS DETECTED: <?php echo '<span class="pathogens_notes">' . $abx->pathogen_detected_notes . '</span>' ?></td>
		</tr>
	</table>
<?php
	foreach ($pathogen_rows as $rownum => $row) {

		$styletbl = 'style="margin-bottom:4px"';
		if ($rownum == count($pathogen_rows) - 1) {
			$styletbl = 'style="margin-bottom:10px"';
		}
		echo '<table class="reporttblstyle2 pathogentbl" ' . $styletbl . '>' . "\n";
		$rowkeys = array('header2', 'content', 'footertext');

		foreach ($rowkeys as $rk) {

			echo '<tr class="' . $rk . '">' . "\n";

			foreach ($row as $bat) {

				echo '<td ' . (count($row) == 1 ? 'colspan=2' : '') . '>' . "\n";
				if ($rk == 'header2') {
					$batitle = $bat->pathogen_name;

					if (count($bat->markers) > 0) {
						$batitle .= ' <span class="addingrm">+</span> RM: ' . implode(', ', $bat->markers);
					}
					echo $batitle;
				} else if ($rk == 'content') {
					echo '<div class="pathogenicity_desc">' . $bat->description2 . '</div>' . "\n";
				} else if ($rk == 'footertext') {
					if (!isset($bat->bmrisk)) {
						$brisk 		= $abx->get_bat_risk($bat->ID, 'ics');
						$bat->bmrisk = $brisk['number'];
						$bat->risk_color = $brisk['color'];
					}
					$bacteria_r_text = $short_pathogencities[ceil($bat->bmrisk / 2) - 1];
					if (!empty($bacteria_r_text)) {
						echo '<span class="squareicon background_' . $bat->risk_color . '"></span> <span>PATHOGENICITY: ' . $bacteria_r_text . '</span>';
					}
				}
				echo '</td>' . "\n";
			}

			if (count($row) == 1) {
				//echo '<td></td>' . "\n";
			}

			echo '</tr>' . "\n";
		}
		echo '</table>' . "\n";

		if ($rownum == 0) {
			echo '</div>';
		}
	}
}
/*
if (count($pathogen_rows) == 1) {
	echo '<div class="center overarrowdown">
	<div class="arrowtext">PERSONALIZED INFECTION ANTIBIOGRAM</div>
	<img class="center" src="' . $asset_url . 'reports/abx/arrowdown.png"/>
	</div>';
}
*/

echo '<div class="overtableabxmain">';
?>
<table class="reporttblstyle2">
	<tr class="header">
		<td colspan="2" style="border:none;text-transform:uppercase;padding:3px 10px">PERSONALIZED INFECTION ANTIBIOGRAM</td>
	</tr>
</table>
<?php
include('abx_main_table.php');
echo '</div>'; //overtableabxmain

echo '</div>';//<div class="abxstyle2">