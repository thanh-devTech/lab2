<?php
if (isset($_GET['debugabx']) && isset($_COOKIE['trquery_monitor'])) {
	echo '<br>file: abxv2.php<br>';
}
if (!isset($abx)) {
	$abx = new CI_ABX_BV();
}

$abx_row_keys 	= array('header', 'dose1', 'dose2', 'dose3');

$bv_benneficials 	= $abx->getBV_Bacterias('lb', true);
$bv_pathgoneics 	= $abx->getBV_Bacterias('bvo', true);
$lbi_bacterias		= $abx->getBV_Bacterias('lbi', true);
$ur_bacterias		= $abx->getBV_Bacterias('ur', true);
$av_pathgoneics 	= $abx->getBV_Bacterias('av', true);

$sti_bacterias 		= $abx->getBV_Bacterias('sti');
$yeast_batches 		= $abx->getBV_Bacterias('yeast');


$bvav_Types = $abx->getbvtypes();

echo '<div class="abxstylev2">';

if ($bvav_Types['bv'] || $bvav_Types['ur'] || $bvav_Types['av'] || !empty($bv_benneficials)) {

	$report['has_cdc_yellow'] = false;
	$report['has_cdc_green'] = false;

	require_once((defined('TRHUB_PATH') ? TRHUB_PATH : TRCPDD_PATH) . 'inc/jpgraph442/jpgraph.php');
	require_once((defined('TRHUB_PATH') ? TRHUB_PATH : TRCPDD_PATH) . 'inc/jpgraph442/jpgraph_pie.php');


	$by_BVrisk			= true;


	$BV_risk 			= $abx->getBV_Risk();
	$LB_score			= $abx->getBV_Lactobacillus_Percent();

	$lb_sum				= $abx->get_BV_Sum('lb');
	$lbi_sum			= $abx->get_BV_Sum('lbi');
	$bv_sum				= $abx->get_BV_Sum('bv');
	$ur_sum				= $abx->get_BV_Sum('ur');
	$av_sum				= $abx->get_BV_Sum('av');
	$ratio				= $av_sum > 0 ? $bv_sum / $av_sum : 0;

	$total_load			= $abx->get_BV_total_load();
	$sumaly 			= $abx->get_analysis_Summary();

	$cond2 			= $abx->bvavTypeKey == 'bav' ? $ratio : $abx->BVhasOther($abx->bvavTypeKey);

	if ($abx->before_panel_riskey == 'MED') {
		$BV_risk = 30;
	} else if ($abx->before_panel_riskey == 'HIGH') {
		$BV_risk = 60;
	}


	$min_piesize 		= 26;
	$piesize 			= 35;
	$loadkey 			= '';

	$chart_total_load = $total_load;
	if (tr_get_option('ci_pdf_data', 'new_abx_piefactor10_bv', false)) {
		$chart_total_load = $chart_total_load / 10;
	}

	if ($chart_total_load < 1000) {

		$piesize = $chart_total_load * 34 / 1000;
		$loadkey = 'vlow';
	} else if ($chart_total_load < 10000) {
		$piesize = (($chart_total_load - 1000) / 10000) * 30  + 36;
		$loadkey = 'low';
	} else if ($chart_total_load < 100000) {
		$piesize = (($chart_total_load - 10000) / 100000) * 30  + 65;
		$loadkey = 'med';
	} else if ($chart_total_load < 1000000) {
		$piesize = (($chart_total_load - 100000) / 1000000) * 29  + 94;
		$loadkey = 'high';
	} else {
		$piesize = (($chart_total_load - 1000000) / 10000000) * 29  + 122;
		$loadkey = 'vhigh';
	}
	if ($piesize < $min_piesize) {
		$piesize = $min_piesize;
	} else if ($piesize > 150) {
		$piesize = 150;
	}
	$piesize = number_format($piesize, 2);

	$LB_score_condition = $abx->hasLactobacillus() ? $LB_score : 'none';

	if (!$by_BVrisk) {
		$BV_risk = 100 - $LB_score;

		$textbycondition = $abx->getTextbyCondition($abx->bvavTypeKey, $LB_score_condition, $cond2, null, $loadkey);
	} else {
		//currently used
		$textbycondition = $abx->getTextbyCondition($abx->bvavTypeKey, $LB_score_condition, $cond2, $BV_risk, $loadkey);
	}

	$bv_comment = '';
	if ($abx->get_LB_total_load() > 100000000) {
		$bv_comment = '<br><b>Please Note:</b> Elevated Lactobacillus levels may suggest overgrowth. If clinical symptoms such as low vaginal pH (<3.8), vulvovaginal irritation, and yeast infection-like symptoms are present, Cytolytic Vaginosis could be considered. If indicated, alkaline sitz baths may be used to manage symptoms. ';
	}

	$healthy_text 	= $textbycondition['desc_1'];
	$Treatment_text = $bv_comment . $textbycondition['desc_2'];
	$Treatment_above_text = $abx->get_Type_Notes();

	try {
		$total_vl    = $lb_sum +  $lbi_sum + $ur_sum + $av_sum + $bv_sum;
		$lb_percent = $lb_sum / $total_vl * 100;
		$lbi_percent = $lbi_sum / $total_vl * 100;
		$ur_percent = $ur_sum / $total_vl * 100;
		$av_percent = $av_sum / $total_vl * 100;
		$bv_percent = $bv_sum / $total_vl * 100;

		if ($lb_percent > 0 && $lb_percent < 0.55) {
			$lb_percent = 0.55;
		}
		if ($lbi_percent > 0 && $lbi_percent < 0.55) {
			$lbi_percent = 0.55;
		}
		if ($ur_percent > 0 && $ur_percent < 0.55) {
			$ur_percent = 0.55;
		}

		if ($bvav_Types['av'] && $av_percent > 0 && $av_percent < 0.55) {
			$av_percent = 0.55;
		}
		if ($bvav_Types['bv'] && $bv_percent > 0 && $bv_percent < 0.55) {
			$bv_percent = 0.55;
		}


		$chartvalue  = array(
			$lb_percent
		);
		$colors = array('#75bf42');
		$clabels = array('');

		if (count($lbi_bacterias) > 0) {
			$chartvalue[] = $lbi_percent;
			$colors[] = '#bbd63f';
			$clabels[] = '';
		}

		if (count($ur_bacterias) > 0) {
			$chartvalue[] = $ur_percent;
			$colors[] = '#ffcf29';
			$clabels[] = '';
		}

		if ($bvav_Types['av']) {
			$chartvalue[] = $av_percent;
			$colors[] = '#ec8b1a';
			$clabels[] = '';
		}
		if ($bvav_Types['bv']) {
			$chartvalue[] = $bv_percent;
			$colors[] = '#f04e13';
			$clabels[] = '';
		}



		$graph = new PieGraph(300, 300);
		$graph->SetMarginColor('#f6f6f6');
		// Set A title for the plot
		$graph->SetBox(false);
		// Create
		$p1 = new PiePlot($chartvalue);
		$p1->SetSize($piesize); //150  121  93 65  35

		$p1->SetLabels($clabels);
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

	if (isset($_REQUEST['debugsumlac']) || $abx->debug) {
		$abx->log .= '<br>' . ($abx->bvavTypeKey == 'bav' ? 'AV/BV' : strtoupper($abx->bvavTypeKey)) . ' Description ID=' . $textbycondition['ID'];
		$abx->log .= '<br>L=' . $BV_risk;
		$abx->log .= '<br>ratio=' . $ratio . '<br>';
	}

	$main_title = 'BACTERIAL VAGINOSIS + AEROBIC VAGINITIS';
	if (!$bvav_Types['av']) $main_title = 'BACTERIAL VAGINOSIS';
	else if (!$bvav_Types['bv']) $main_title = 'AEROBIC VAGINITIS';

	$bvav_organistext 	= ' Detected Organisms:';
	$risktitle 			= $abx->bvavTypeKey == 'bav' ? 'Vaginal Health Estimate*' : strtoupper($abx->bvavTypeKey) . ' Risk Estimate*';

	if ($abx->bvavTypeKey == 'bav' && (!$bvav_Types['av'] || !$bvav_Types['bv'])) {
		$bvav_organistext = 'Detected Organisms:';
	}

	if ($abx->bvavTypeKey == 'ur') {
		$risktitle = 'UR Infection Risk Estimate';
		$bvav_organistext = 'Detected Organisms:';
	}


?>
	<table class="reporttblstyle2">
		<tr class="header">
			<td class="ttwicon" colspan="2"><?php echo $main_title ?></td>
		</tr>
		<tr>
			<td style="width:50%;" class="nopadding coli_1">
				<table>
					<tr class="header1">
						<td class="coli_1"><?php echo $bvav_organistext ?></td>
						<td class="coli_2 center">Infection 360</td>
					</tr>
					<tr>
						<td class="nopadding coli_1">
							<table>
								<tr class="header2">
									<td>LB BENEFICIAL: <img class="abxroundcic fright" src="<?php echo $asset_url . 'reports/abx/lb.png' ?>" /></td>
								</tr>
								<tr>
									<td class="listitems">
										<?php echo count($bv_benneficials) > 0 ? '<div>' . implode('</div><div>', $bv_benneficials) . '</div>' : $abx->getBV_None() ?>
									</td>
								</tr>
								<?php if (count($lbi_bacterias) > 0) { ?>
									<tr class="header2">
										<td>LB INTERMEDIATE: <img class="abxroundcic fright" src="<?php echo $asset_url . 'reports/abx/lbi.png' ?>" /></td>
									</tr>
									<tr>
										<td class="listitems">
											<?php echo count($lbi_bacterias) > 0 ? '<div>' . implode('</div><div>', $lbi_bacterias) . '</div>' : $abx->getBV_None() ?>
										</td>
									</tr>
								<?php } ?>

								<?php if (count($ur_bacterias) > 0) { ?>
									<tr class="header2">
										<td>UR OPPORTUNISTIC: <span class="abxroundcic abxround_ur fright"></span></td>
									</tr>
									<tr>
										<td class="listitems">
											<?php echo implode('<br>', $ur_bacterias) ?>
										</td>
									</tr>
								<?php } ?>

								<?php if ($bvav_Types['av']) { ?>
									<tr class="header2">
										<td>AV PATHOGENS: <span class="abxroundcic abxround_av fright"></span></td>
									</tr>
									<tr>
										<td class="listitems">
											<?php echo count($av_pathgoneics) > 0 ? '<div>' . implode('</div><div>', $av_pathgoneics) . '</div>' : $abx->getBV_None() ?>
										</td>
									</tr>
								<?php } ?>
								<?php if ($bvav_Types['bv']) { ?>
									<tr class="header2">
										<td>BV PATHOGENS: <span class="abxroundcic abxround_bv fright"></span></td>
									</tr>
									<tr>
										<td class="listitems">
											<?php echo count($bv_pathgoneics) > 0 ? '<div>' . implode('</div><div>', $bv_pathgoneics) . '</div>' : $abx->getBV_None() ?>
										</td>
									</tr>
								<?php } ?>
							</table>

						</td>
						<td class="coli_2 nopadding center piechartarea">
							<div class="header2 bacterialoadhead">BACTERIAL LOAD + COMPOSITION</div>
							<div class="wrap_infection">
								<div class="wrap_load_composition">
									<div class="load_composition very_high">
										<div class="text">VERY HIGH</div>
									</div>
									<div class="load_composition high">
										<div class="text">HIGH</div>
									</div>
									<div class="load_composition medium">
										<div class="text">MEDIUM</div>
									</div>
									<div class="load_composition low">
										<div class="text">LOW</div>
									</div>
									<div class="load_composition very_low">
										<div class="text">V.LOW</div>
									</div>
								</div>

								<div class="overimage"><img style="width:180px" src="data:image/png;base64,<?php echo (base64_encode($healthchartimage)); ?>" /></div>
							</div>
							<?php
							ob_start();
							?>
							<table class="charticonstbl">
								<tr>
									<td><img class="abxroundcic" src="<?php echo $asset_url . 'reports/abx/lb.png' ?>" />LB</td>
									<?php if (count($lbi_bacterias) > 0) { ?>
										<td><img class="abxroundcic" src="<?php echo $asset_url . 'reports/abx/lbi.png' ?>" />LBI</td>
									<?php } ?>
									<?php if (count($ur_bacterias) > 0) { ?>
										<td><span class="abxroundcic abxround_ur"></span>UR</td>
									<?php } ?>
									<?php if ($bvav_Types['av']) { ?>
										<td><span class="abxroundcic abxround_av"></span>AV</td>
									<?php } ?>
									<?php if ($bvav_Types['bv']) { ?>
										<td><span class="abxroundcic abxround_bv"></span>BV</td>
									<?php } ?>
								</tr>
							</table>
							<?php
							$bviconhtml = ob_get_clean();
							if (!$abx->RelativeLoad) {
								echo $bviconhtml;
							}
							?>
						</td>
					</tr>
					<?php if ($abx->RelativeLoad) {
					?>
						<tr class="trbatrelatives">
							<td class="coli_1 bordertop">
								<span class="batrelatives_text">Load relative to typical levels</span>
								<span class="batrelatives">
									<?php
									for ($i = 1; $i <= 5; $i++) {
										$bstyle = '';
										$bgcolor = '175,173,179';
										$bgtrpr = 1 - (5 - $i) * 0.175;
										$bgcolor = 'rgba(' . $bgcolor . ', ' . $bgtrpr . ')';
										$bstyle = ' style="background:' . $bgcolor . '"';
										echo '<span class="relateitem" ' . $bstyle . '></span>';
									}
									?>
								</span>
							</td>
							<td class="coli_2 bordertop center"><?php echo $bviconhtml ?></td>
						</tr>
					<?php
					}
					?>
				</table>
				<?php
				if ($abx->bvavTypeKey == 'bav') {
					echo '<div class="center header2 bvriskheader mgb10">' . $risktitle . '</div>';
					include('abx/abx3_healthy_bar.php');
				}
				?>
			</td>
			<td class="paddingabx">
				<?php
				$typeicon = '';

				$abxtabledata 		= $abx->bv_get_abxs('bav');

				if ($abx->hasTreatmentO('bv')) {
					$typeicon .= '&nbsp;&nbsp;&nbsp;<span class="abxroundcic abxround_bv"></span> BV';
				}
				if ($abx->hasTreatmentO('av')) {
					$typeicon .= '&nbsp;&nbsp;&nbsp;<span class="abxroundcic abxround_av"></span> AV';
				}
				if ($abx->hasTreatmentO('ur')) {
					$typeicon .= '&nbsp;&nbsp;&nbsp;<span class="abxroundcic abxround_ur"></span> UR';
				}

				echo '<div class="header1">Treatment Options <span class="bvkey">' . $typeicon . '</span></div>';


				$abx_html_rows = array();
				for ($nofrow = 1; $nofrow <= ceil(count($abxtabledata) / 2); $nofrow++) {
					$fromi = ($nofrow - 1) * 2;
					$to = $fromi + 1;
					foreach ($abx_row_keys as $k) {
						$hasvl = false;
						$tr = '<tr class="' . $k . '">' . "\n";
						$icount = 0;
						for ($i1 = $fromi; $i1 <= $to; $i1++) {
							$icount++;
							$r = isset($abxtabledata[$i1]) ? $abxtabledata[$i1] : new stdClass();
							$colclass1 = 'coli_' . $icount;
							if (isset($r->excluded) && $r->excluded) {
								$colclass1 .= ' excludedabx ';
							}
							if ($k == 'header') {

								$abxcolor = $abx->get_bv_color(isset($r->abx) ? $r->abx : '');
								$addingabxtext = '';
								//check fda
								$has_fda = false;
								if (isset($r->types) && is_array($r->types)) {
									foreach ($r->types as $tki) {
										if (stripos($r->fda, $tki) !== false) {
											$has_fda = true;
										}
									}
								}

								if ($has_fda) {
									$cdcfile 	= 'cdc.png';
									$cdc_class 	= 'abxfda';
									if (isset($r->fda) && stripos($r->fda, '2') !== false) {
										$cdcfile 	= 'cdc2.png';
										$cdc_class 	.= ' abxfda2';
										$report['has_cdc_yellow'] = true;
									} else {
										$report['has_cdc_green'] = true;
									}
									$addingabxtext .= '<img src="' . $asset_url . 'reports/abx/' . $cdcfile . '" class="abxcdcimg"/>';
									$addingabxtext .=  ' <span class="' . $cdc_class . '">CDC</span>';
								}



								if ($abxcolor > 0) {
									$addingabxtext .=  '<span class="abxdenote abxdenote_' . $abxcolor . '"></span>';
								}
								$addingabxtext .= '<span class="fright righticons">';

								if (isset($r->excluded) && $r->excluded) {
									$has_abx_minus_icon = true;

									$addingabxtext .= '<span class="wrminus_icon"><img src="' . $asset_url . 'reports/abx/_icon.png" class="minus_icon"/></span>';
								} else if (isset($r->types) && in_array('bv', $r->types) && !$has_fda) {

									$addingabxtext .= '<img src="' . $asset_url . 'reports/abx/plus_icon.png" class="minus_icon"/>';

									$has_abx_add_icon = true;
								}
								if (isset($r->types) && $r->types && $abx->countBVType > 0) {
									$addingabxtext .= '<span class=" abxiconh1">';
									foreach ($r->types as $tki) {
										$addingabxtext .= '<span class="abxroundcic abxround_' . $tki . ' "></span>';
									}
									$addingabxtext .= '</span>';
								}


								$addingabxtext .= '</span>';

								$tr .= '<td class="' . $colclass1 . '">'  . (isset($r->abx) ? $r->abx  : '') . $addingabxtext . '</td>';
								$hasvl = true;
							} else {
								$vl = isset($r->{$k}) ? $r->{$k}  : '';
								if (!empty($vl)) {
									$hasvl = true;
									$vl = htmlentities($vl);
								}
								$checkmarkkey = str_replace('dose', 'dose_check_', $k);
								if ($report['pa_gender'] == 'F' && isset($r->{$checkmarkkey}) && $r->{$checkmarkkey} === '1') {
									$vl .= '<img src="' . $asset_url . 'reports/abx/icon_Pregnancy.png" class="checkmarkdose"/>';
									$report['has_pregnancy'] = true;
								}
								$tr .= '<td class="' . $colclass1 . '">' . $vl . '</td>';
							}
						}
						$tr .= '</tr>';

						if ($hasvl) {
							$abx_html_rows[] = $tr;
						}
					}
				}
				if (count($abx_html_rows) > 0) {
					echo '<table class="abxtoptbl countrows_' . count($abx_html_rows) . '">';
					echo implode(' ', $abx_html_rows);
					echo '</table>';
				}

				if ($abx->bvavTypeKey != 'bav') {
					echo $Treatment_above_text;
					echo '<table class="noborder mgtb10"><tr><td class="thawngabx">!</td>';
					echo '<td>' . $Treatment_text . '</td>';
					echo '</tr></table>';
				} else {
					echo '<table class="abxtoptbl asummary"><tr class="header"><td colspan=5>Analysis Summary:</td></tr>';
					echo '<tr class="subheader"><td colspan=3 class="noborderleft" style="width:80%"></td><td class="center" style="width:20%">Non-pregnant</td><td class="center noborderright" style="width:20%">Pregnant</td></tr>';
					foreach ($sumaly as $tki => $arr) {
						echo '<tr>';
						echo '<td class="noborderleft" style="width:10%"><span class="abxroundcic abxround_' . $tki . ' "></span>' . strtoupper($tki) . '</td>';
						echo '<td style="width:18%">' . $arr['risktext'] . '</td>';
						echo '<td>' . $arr['comment'] . '</td>';
						echo '<td>' . $arr['non-pregnant'] . '</td>';
						echo '<td class="noborderright">' . $arr['pregnant'] . '</td>';
						echo '</tr>';
					}
					echo '</table>';
				}
				?>
			</td>
		</tr>
		<?php
		if ($abx->bvavTypeKey != 'bav') {
			echo '<tr class="header2">';
			echo '<td class="center bvriskheader">' . $risktitle . '</td>';
			echo '<td><div >&nbsp;</div></td>';
			echo '</tr>';

			echo '<tr>';
			echo '<td class="nopadding">';
			include('abx/abx3_healthy_bar.php');
			echo '</td>';
			echo '<td class=" valign_middle healthy_text">';
			echo $healthy_text;
			echo '</td>';
			echo '</tr>';
		} else {
			echo '<tr>';
			echo '<td>';
			echo '<div class="healthy_text">';
			echo $healthy_text;
			echo '</div>';
			echo '</td>';

			echo '<td class="treatmentcontent">';
			echo $Treatment_above_text;
			echo '<table class="noborder"><tr><td class="thawngabx">!</td>';
			echo '<td>' . $Treatment_text . '</td>';
			echo '</tr></table></td>';
			echo '</tr>';
		}
		?>
		<tr class="bvbottom">
			<td>
				<div class="legend_text">
					The estimate is based solely on algorithmic analysis of PCR results and should not be the only factor considered in making treatment decisions.
				</div>
			</td>
			<td class="treatmentcontent">
				<?php if ($report['pa_gender'] == 'F' && isset($report['has_pregnancy']) && $report['has_pregnancy']) { ?>
					<div class="legenditem">
						<img src="<?php echo $asset_url ?>reports/abx/icon_Pregnancy.png" class="checkmarkdose" />
						Generally considered safe during pregnancy (category A/B).
					</div>
				<?php } ?>

				<?php if (isset($report['has_cdc_green']) && $report['has_cdc_green']) { ?>
					<div class="legenditem">
						<img src="<?php echo $asset_url ?>reports/abx/cdc.png" class="checkmarkdose" />
						CDC recommended treatment.
					</div>
				<?php } ?>

				<?php if (isset($report['has_cdc_yellow']) && $report['has_cdc_yellow']) { ?>
					<div class="legenditem">
						<img src="<?php echo $asset_url ?>reports/abx/cdc2.png" class="checkmarkdose" />
						CDC alternative treatment.
					</div>
				<?php } ?>

				<?php if (isset($has_abx_minus_icon) && $has_abx_minus_icon) { ?>
					<div class="legenditem">
						<img src="<?php echo $asset_url ?>reports/abx/_icon.png" class="checkmarkdose" />
						Excluded: Predominant BV pathogens are typically &lt;80% susceptible.
					</div>
				<?php } ?>
				<?php if (isset($has_abx_add_icon) && $has_abx_add_icon) { ?>
					<div class="legenditem">
						<img src="<?php echo $asset_url ?>reports/abx/plus_icon.png" class="checkmarkdose" />
						Included: Predominant BV pathogens are typically susceptible.
					</div>
				<?php } ?>
			</td>
		</tr>

	</table>
<?php

	if ($abx->show_bv_desc) {
		include('abx/abx_bv_desc.php');
	}

	echo '<div class="margintop10"></div>';
}


if ($abx->separate_opportunistic) {
	include('abx/abx_opportunistic.php');
}

include('abx/abx_sti.php');

include('abx/abx_yeast_' . $abx->yeast_version . '.php');

include('abx/abx_v_others.php');

echo '</div>';//abxstylev2