<?php
if (isset($_GET['debugabx']) && isset($_COOKIE['trquery_monitor'])) {
	echo '<br>file: abx3.php<br>';
}
if (!isset($abx)) {
	$abx = new CI_ABX_BV();
}

$abx_row_keys 	= array('header', 'dose1', 'dose2', 'dose3');

$bv_benneficials 	= $abx->getBV_Bacterias('lb', true);
$bv_pathgoneics 	= $abx->getBV_Bacterias('bvo', true);
$lbi_bacterias		= $abx->getBV_Bacterias('lbi', true);
$op_bacterias		= $abx->getBV_Bacterias('op', true);
$av_pathgoneics 	= $abx->getBV_Bacterias('av', true);

$sti_bacterias 		= $abx->getBV_Bacterias('sti');
$yeast_batches 		= $abx->getBV_Bacterias('yeast');



$has_BV = !empty($bv_pathgoneics) || !empty($op_bacterias);
$has_AV = !empty($av_pathgoneics);

if (!$has_BV && !$has_AV && !empty($lbi_bacterias)) {
	$has_BV = true;
}

if ($has_BV || $has_AV || !empty($bv_benneficials)) {

	require_once((defined('TRHUB_PATH') ? TRHUB_PATH : TRCPDD_PATH) . 'inc/jpgraph442/jpgraph.php');
	require_once((defined('TRHUB_PATH') ? TRHUB_PATH : TRCPDD_PATH) . 'inc/jpgraph442/jpgraph_pie.php');


	$by_BVrisk			= true;
	$bvavtype			= $has_BV && $has_AV ? 'bav' : ($has_BV ? 'bv' : 'av');
	$BV_risk 			= $abx->getBV_Risk();
	$LB_score			= $abx->getBV_Lactobacillus_Percent();

	$lb_sum				= $abx->get_BV_Sum('lb');
	$lbi_sum			= $abx->get_BV_Sum('lbi');
	$bv_sum				= $abx->get_BV_Sum('bv');
	$op_sum				= $abx->get_BV_Sum('op');
	$av_sum				= $abx->get_BV_Sum('av');
	$ratio				= $av_sum > 0 ? $bv_sum / $av_sum : 0;

	$total_load			= $abx->get_BV_total_load();

	$cond2 			= $bvavtype == 'bav' ? $ratio : $abx->BVhasOther($bvavtype);




	$min_piesize 		= 26;
	$piesize 			= 35;
	$loadkey 			= '';

	$chart_total_load = $total_load;
	if (tr_get_option('ci_pdf_data', 'new_abx_piefactor10')) {
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

		$textbycondition = $abx->getTextbyCondition($bvavtype, $LB_score_condition, $cond2, null, $loadkey);
	} else {

		$textbycondition = $abx->getTextbyCondition($bvavtype, $LB_score_condition, $cond2, $BV_risk, $loadkey);
	}


	$healthy_text 	= $textbycondition['desc_1'];
	$Treatment_text = $textbycondition['desc_2'];



	try {
		$total_vl    = $lb_sum +  $lbi_sum + $op_sum + $av_sum + $bv_sum;
		$lb_percent = $lb_sum / $total_vl * 100;
		$lbi_percent = $lbi_sum / $total_vl * 100;
		$op_percent = $op_sum / $total_vl * 100;
		$av_percent = $av_sum / $total_vl * 100;
		$bv_percent = $bv_sum / $total_vl * 100;

		if ($lb_percent > 0 && $lb_percent < 0.55) {
			$lb_percent = 0.55;
		}
		if ($lbi_percent > 0 && $lbi_percent < 0.55) {
			$lbi_percent = 0.55;
		}
		if ($op_percent > 0 && $op_percent < 0.55) {
			$op_percent = 0.55;
		}

		if ($has_AV && $av_percent > 0 && $av_percent < 0.55) {
			$av_percent = 0.55;
		}
		if ($has_BV && $bv_percent > 0 && $bv_percent < 0.55) {
			$bv_percent = 0.55;
		}


		$chartvalue  = array(
			$lb_percent
		);
		$colors = array('#75bf42');

		if (count($lbi_bacterias) > 0) {
			$chartvalue[] = $lbi_percent;
			$colors[] = '#bbd63f';
		}

		if (count($op_bacterias) > 0) {
			$chartvalue[] = $op_percent;
			$colors[] = '#ffcf29';
		}

		if ($has_AV) {
			$chartvalue[] = $av_percent;
			$colors[] = '#ec8b1a';
		}
		if ($has_BV) {
			$chartvalue[] = $bv_percent;
			$colors[] = '#f04e13';
		}



		$graph = new PieGraph(300, 300);
		$graph->SetMarginColor('#f6f6f6');
		// Set A title for the plot
		$graph->SetBox(false);
		// Create
		$p1 = new PiePlot($chartvalue);
		$p1->SetSize($piesize); //150  121  93 65  35

		$p1->SetLabels(array('', '', '', ''));
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
		$abx->log .= '<br>' . ($bvavtype == 'bav' ? 'AV/BV' : ($has_AV ? 'AV' : 'BV')) . ' Description ID=' . $textbycondition['ID'];
		$abx->log .= '<br>L=' . $BV_risk;
		$abx->log .= '<br>ratio=' . $ratio;
	}

	$main_title = 'BACTERIAL VAGINOSIS + AEROBIC VAGINITIS';
	if (!$has_AV) $main_title = 'BACTERIAL VAGINOSIS';
	else if (!$has_BV) $main_title = 'AEROBIC VAGINITIS';

	$bvav_organistext 	= ($bvavtype == 'bav' ? 'AV/BV' : ($has_AV ? 'AV' : 'BV')) . ' Detected ORGANISMS:';
	$risktitle 			= $bvavtype == 'bav' ? 'Vaginal Health Estimate*' : ($has_AV ? 'AV' : 'BV') . ' Risk Estimate*';

?>
	<table class="reporttblstyle2" style="margin-bottom:10px;">
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
									<td>LB BENEFICIAL: <span class="abxroundcic abxround_lb fright"></span></td>
								</tr>
								<tr>
									<td class="listitems">
										<?php echo count($bv_benneficials) > 0 ? implode('<br>', $bv_benneficials) : 'None' ?>
									</td>
								</tr>
								<?php if (count($lbi_bacterias) > 0) { ?>
									<tr class="header2">
										<td>LB INTERMEDIATE: <span class="abxroundcic abxround_lbi fright"></span></td>
									</tr>
									<tr>
										<td class="listitems">
											<?php echo count($lbi_bacterias) > 0 ? implode('<br>', $lbi_bacterias) : 'None' ?>
										</td>
									</tr>
								<?php } ?>

								<?php if (count($op_bacterias) > 0) { ?>
									<tr class="header2">
										<td>OPPORTUNISTIC: <span class="abxroundcic abxround_op fright"></span></td>
									</tr>
									<tr>
										<td class="listitems">
											<?php echo implode('<br>', $op_bacterias) ?>
										</td>
									</tr>
								<?php } ?>

								<?php if ($has_AV) { ?>
									<tr class="header2">
										<td>AV PATHOGENS: <span class="abxroundcic abxround_av fright"></span></td>
									</tr>
									<tr>
										<td class="listitems">
											<?php echo count($av_pathgoneics) > 0 ? implode('<br>', $av_pathgoneics) : 'None' ?>
										</td>
									</tr>
								<?php } ?>
								<?php if ($has_BV) { ?>
									<tr class="header2">
										<td>BV PATHOGENS: <span class="abxroundcic abxround_bv fright"></span></td>
									</tr>
									<tr>
										<td class="listitems">
											<?php echo count($bv_pathgoneics) > 0 ? implode('<br>', $bv_pathgoneics) : 'None' ?>
										</td>
									</tr>
								<?php } ?>
							</table>

						</td>
						<td class="coli_2 nopadding center">
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
							<table class="charticonstbl">
								<tr>
									<td><span class="abxroundcic abxround_lb"></span>LB</td>
									<?php if (count($lbi_bacterias) > 0) { ?>
										<td><span class="abxroundcic abxround_lbi"></span>LBI</td>
									<?php } ?>
									<?php if (count($op_bacterias) > 0) { ?>
										<td><span class="abxroundcic abxround_op"></span>OP</td>
									<?php } ?>
									<?php if ($has_AV) { ?>
										<td><span class="abxroundcic abxround_av"></span>AV</td>
									<?php } ?>
									<?php if ($has_BV) { ?>
										<td><span class="abxroundcic abxround_bv"></span>BV</td>
									<?php } ?>
								</tr>
							</table>
						</td>
					</tr>
				</table>
				<?php
				if ($bvavtype == 'bav') {
					include('abx/abx3_healthy_bar.php');
				}
				?>
			</td>
			<td class="paddingabx">
				<?php
				if ($has_BV) {
					echo '<div class="header1">BV - Treatment Options:</div>';

					$abxtabledata 		= $abx->bv_get_abxs('bv');
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

								if ($k == 'header') {
									$abxcolor = $abx->get_bv_color($r->abx);
									if ($abxcolor > 0) {
										$r->abx .=  '<span class="abxdenote abxdenote_' . $abxcolor . '"></span>';
									}
									$tr .= '<td class="coli_' . $icount . ' ">' . $r->abx . '</td>';
									$hasvl = true;
								} else {
									$vl = isset($r->{$k}) ? $r->{$k}  : '';
									if (!empty($vl)) {
										$hasvl = true;
									}
									$checkmarkkey = str_replace('dose', 'dose_check_', $k);
									if (isset($r->{$checkmarkkey}) && $r->{$checkmarkkey} === '1') {
										$vl .= '<img src="' . $asset_url . 'reports/abx/icon_Pregnancy.png" class="checkmarkdose"/>';
									}
									$tr .= '<td class="coli_' . $icount . '">' . $vl . '</td>';
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
				}


				if ($has_AV) {
					echo '<div class="header1">AV - Treatment Options:</div>';

					$abxtabledata 		= $abx->bv_get_abxs('av');
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

								if ($k == 'header') {
									$abxcolor = $abx->get_bv_color($r->abx);
									if ($abxcolor > 0) {
										$r->abx .=  '<span class="abxdenote abxdenote_' . $abxcolor . '"></span>';
									}
									$tr .= '<td class="coli_' . $icount . ' ">' . $r->abx . '</td>';
									$hasvl = true;
								} else {
									$vl = isset($r->{$k}) ? $r->{$k}  : '';
									if (!empty($vl)) {
										$hasvl = true;
									}
									$checkmarkkey = str_replace('dose', 'dose_check_', $k);
									if (isset($r->{$checkmarkkey}) && $r->{$checkmarkkey} === '1') {
										$vl .= '<img src="' . $asset_url . 'reports/abx/icon_Pregnancy.png" class="checkmarkdose"/>';
									}
									$tr .= '<td class="coli_' . $icount . '">' . $vl . '</td>';
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
				}

				if ($bvavtype != 'bav') {
					echo '<table class="noborder mgtop15"><tr><td class="thawngabx">!</td>';
					echo '<td>' . $Treatment_text . '</td>';
					echo '</tr></table>';
				}
				?>
			</td>
		</tr>

		<tr>
			<?php
			if ($bvavtype != 'bav') {
				echo '<td class="nopadding">';
				include('abx/abx3_healthy_bar.php');
				echo '</td>';
				echo '<td class="nopadding">';
				echo '<div class="header2 bvriskheader mgb10">&nbsp;</div>';
				echo '<div class="healthy_text">';
				echo $healthy_text;
				echo '</div>';
				echo '</td>';
			} else {
				echo '<td>';
				echo '<div class="healthy_text">';
				echo $healthy_text;
				echo '</div>';
				echo '</td>';

				echo '<td class="treatmentcontent">';
				echo '<table class="noborder"><tr><td class="thawngabx">!</td>';
				echo '<td>' . $Treatment_text . '</td>';
				echo '</tr></table></td>';
			}
			?>
		</tr>

		<tr class="bvbottom">
			<td>
				<div class="legend_text">
					*The <?php echo $bvavtype == 'av' ? 'AV' : 'BV' ?> Risk Estimate is based solely on the detected organisms and does not account for other relevant clinical factors.
				</div>
			</td>
			<td class="treatmentcontent">
				<img src="<?php echo $asset_url ?>reports/abx/icon_Pregnancy.png" class="checkmarkdose" />
				Safe to use during pregnancy (category A or B).
			</td>
		</tr>

	</table>
<?php
}

if (count($sti_bacterias) > 0) {
?>
	<table class="reporttblstyle2 separate_rowtbl" style="margin-bottom:5px;">
		<tr class="header">
			<td class="ttwicon colr1" colspan="2">STIs</td>
		</tr>
		<?php
		$ib = 0;
		foreach ($sti_bacterias as $bat) {
			$ib++;
		?>
			<tr>
				<td class="nopadding colr1" style="width:50%;">
					<?php if ($ib == 1) { ?>
						<div class="header1">STIs - Detected ORGANISMS:</div>
					<?php } ?>
					<div class="batheader"><?php echo $bat->name ?></div>
					<div class="batdesc"><?php echo $bat->description2 ?></div>
				</td>
				<td class="paddingabx colr2">
					<?php if ($ib == 1) { ?>
						<div class="header1">STIs - Treatment Options:</div>
					<?php
					}
					$abx_html_rows = array();
					for ($nofrow = 1; $nofrow <= ceil(count($bat->abxs) / 2); $nofrow++) {
						$fromi = ($nofrow - 1) * 2;
						$to = $fromi + 1;
						foreach ($abx_row_keys as $k) {
							$hasvl = false;
							$tr = '<tr class="' . $k . '">' . "\n";
							$icount = 0;
							for ($i1 = $fromi; $i1 <= $to; $i1++) {
								$icount++;
								$r = isset($bat->abxs[$i1]) ? $bat->abxs[$i1] : new stdClass();

								if ($k == 'header') {
									$abxcolor = $abx->get_bv_color($r->abx);

									if ($abxcolor > 0) {
										$r->abx .=  '<span class="abxdenote abxdenote_' . $abxcolor . '"></span>';
									}
									$tr .= '<td class="coli_' . $icount . '">' . $r->abx . '</td>';
									$hasvl = true;
								} else {
									$vl = isset($r->{$k}) ? $r->{$k}  : '';
									if (!empty($vl)) {
										$hasvl = true;
									}
									$checkmarkkey = str_replace('dose', 'dose_check_', $k);
									if (isset($r->{$checkmarkkey}) && $r->{$checkmarkkey} === '1') {
										$vl .= '<img src="' . $asset_url . 'reports/abx/icon_Pregnancy.png" class="checkmarkdose"/>';
									}
									$tr .= '<td class="coli_' . $icount . '">' . $vl . '</td>';
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
					?>
				</td>
			</tr>
		<?php
		}
		?>
	</table>
<?php
}


if (count($yeast_batches) > 0) {
	$countsti = count($sti_bacterias);
	if ($countsti > 0 && $countsti < 3) {
		echo '<div class="break"></div>';
	}

	$yeast_abxs 	= $abx->bv_get_abxs('yeast');
?>

	<table class="reporttblstyle2 separate_rowtbl">
		<tr class="header">
			<td class="ttwicon " colspan="2">CANDIDA VAGINITIS</td>
		</tr>
		<tr>
			<td class="nopadding colr1" style="width:50%;">
				<div class="header1">CV Detected ORGANISMS:</div>
				<div class="batheader">
					<?php
					echo '<table>';
					echo '<tr>';
					echo '<td>';
					echo '<ul>';
					$addednewcol = false;
					$i2b = 0;
					foreach ($yeast_batches as $bat) {
						if (!$addednewcol && $i2b >= count($yeast_batches) / 2) {
							$addednewcol = true;
							echo '</ul>';
							echo '</td><td><ul>';
						}
						echo '<li>' . $bat->name . '</li>';

						$i2b++;
					}
					echo '</ul>';
					echo '</td>';
					echo '</tr>';
					echo '</table>';
					?>
				</div>
				<div class="batdesc">
					<ul>
						<li>Candida vaginitis accounts for up to 30% of vaginitis infections and often co-occurs with bacterial vaginosis (BV). Risk factors include recent antibiotic or corticosteroid use, uncontrolled diabetes, and immunocompromised states.</li>
						<li>70-90% of strains in typical patients are C. albicans. Treatment involves oral fluconazole (150 mg, single dose) or topical azole therapy applied intravaginally for 7 days. For recurrent infections, a longer duration (7-14 days) of topical therapy and a 3-dose regimen of fluconazole (on days 1, 4, and 7) are recommended.</li>
						<li>Some species (C. glabrata, C. Krusei) are resistant to azoles, necessitating alternative treatment approaches.</li>
					</ul>
					<?php //echo $bat->description2 
					?>
				</div>
			</td>
			<td class="paddingabx colr2">
				<div class="header1">CV - Treatment Options:</div>
				<?php
				$abx_html_rows = array();
				for ($nofrow = 1; $nofrow <= ceil(count($yeast_abxs) / 2); $nofrow++) {
					$fromi = ($nofrow - 1) * 2;
					$to = $fromi + 1;
					foreach ($abx_row_keys as $k) {
						$hasvl = false;
						$tr = '<tr class="' . $k . '">' . "\n";
						$icount = 0;
						for ($i1 = $fromi; $i1 <= $to; $i1++) {
							$icount++;
							$r = isset($yeast_abxs[$i1]) ? $yeast_abxs[$i1] : new stdClass();

							if ($k == 'header') {
								$abxcolor = $abx->get_bv_color($r->abx);
								if ($abxcolor > 0) {
									$r->abx .=  '<span class="abxdenote abxdenote_' . $abxcolor . '"></span>';
								}

								$tr .= '<td class="coli_' . $icount . '">' . $r->abx . '</td>';
								$hasvl = true;
							} else {
								$vl = isset($r->{$k}) ? $r->{$k}  : '';
								if (!empty($vl)) {
									$hasvl = true;
								}
								$checkmarkkey = str_replace('dose', 'dose_check_', $k);
								if (isset($r->{$checkmarkkey}) && $r->{$checkmarkkey} === '1') {
									$vl .= '<img src="' . $asset_url . 'reports/abx/icon_Pregnancy.png" class="checkmarkdose"/>';
								}
								$tr .= '<td class="coli_' . $icount . '">' . $vl . '</td>';
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
				?>
			</td>
		</tr>

	</table>
<?php
}
