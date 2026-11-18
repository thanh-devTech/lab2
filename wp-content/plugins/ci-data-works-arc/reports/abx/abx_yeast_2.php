<?php

if (count($yeast_batches) > 0) {
	if (isset($_GET['debugabx']) && isset($_COOKIE['trquery_monitor'])) {
		echo '<br>file: abx_yeast_2.php<br>';
	}
	$has_abx_minus_icon = false;
	$countsti = count($sti_bacterias);
	if ($countsti > 0 && $countsti < 3) {
		echo '<div class="break"></div>';
	}

	require_once((defined('TRHUB_PATH') ? TRHUB_PATH : TRCPDD_PATH) . 'inc/jpgraph442/jpgraph.php');
	require_once((defined('TRHUB_PATH') ? TRHUB_PATH : TRCPDD_PATH) . 'inc/jpgraph442/jpgraph_pie.php');




	$min_piesize 		= 26;
	$piesize 			= 35;
	$loadkey 			= '';

	$yeast_risk 		= $abx->getPanel_Risk('yeast');

	$chart_total_load 	= $abx->yeast_total_load;
	if (tr_get_option('ci_pdf_data', $abx->parent_type == 'bv' ? 'new_abx_piefactor10_bv' : 'new_abx_piefactor10')) {
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

	$textbyconditions = array();

	$textbyconditions[] = $abx->getTextbyCondition('yeast', null, $abx->yeast_max_risk, $yeast_risk, null);

	$abx_maker_notes = $abx->get_Maker_Notes();


	try {
		$listcolors = array(
			'candida dubliniensis' => '#d4b462',
			'candida spp.' => '#f5df5c',
			'candida albicans' => '#cdd041',
			'candida lusitaniae' => '#9b9d45',
			'candida tropicalis' => '#b68bb6',
			'candida parapsilosis' => '#8a768a',
			'candida krusei' => '#eb8080',
			'candida glabrata' => '#ec6861',
			'candida auris' => '#E06234',
			'epidermophyton floccosum' => '#96B4B7',
			'trichophyton rubrum' => '#CAAAAA',
			'epicoccum spp.' => '#7aead1',
			'pneumocystis jirovecii' => '#E99330',
		);
		$chartvalue  	= array();
		$colors 		= array();
		$clabels 		= array();

		$total_vl    	= $abx->get_BV_Sum('yeast');

		foreach ($yeast_batches as $bat) {
			$bat->name = trim($bat->name);
			$pvl = $bat->ra / $total_vl * 100;
			if ($pvl > 0 && $pvl < 0.55) {
				$pvl = 0.55;
			}
			$chartvalue[$bat->ID] = $pvl;

			$colors[$bat->ID] 	= isset($bat->color) ? $bat->color : '';
			if (empty($colors[$bat->ID])) {
				$colors[$bat->ID] 	= isset($listcolors[strtolower($bat->name)]) ? $listcolors[strtolower($bat->name)] : '#f5df5c';
			}
			$clabels[$bat->ID] 	= '';
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





	$line_organisms_height = 30;
	if ($abx->show_percent_bacteria) {
		$line_organisms_height = 50;
	}
	$organisms_height = count($yeast_batches) * $line_organisms_height;
	$break_before_pathogentbl = $organisms_height > 200;
	if ($organisms_height > 350) {
		echo '<div>';
	} else {
		echo '<div class="dontbreakinside">'; //always one block

	}
?>

	<table class="reporttblstyle2" style="margin-bottom:10px;">
		<tr class="header">
			<td class="ttwicon" colspan="2"><?php echo (!empty($abx->yeast_uti_wound)) ? 'FUNGI' : 'VULVOVAGINAL CANDIDIASIS (VVC)'; ?></td>
		</tr>
		<tr>
			<td style="width:50%;" class="nopadding coli_1">
				<table>
					<tr class="header1">
						<td class="coli_1">Detected Organisms:</td>
						<td class="coli_2 center">LOAD + COMPOSITION*</td>
					</tr>
					<tr>
						<td class="nopadding coli_1">
							<table>
								<?php
								$ib = 0;
								foreach ($yeast_batches as $bat) {
								?>
									<tr class="header2">
										<td class="listitems" colspan="2">
											<?php
											echo '<span>' . $bat->name . '</span>';
											if ($abx->show_percent_bacteria) {
												echo ' <span class="batpercent">' . number_format($bat->ra_percent, 2) . '%</span>';
											}
											?>
											<span style="background:<?php echo $colors[$bat->ID] ?>" class="abxroundcic abxround_lb fright"></span>
										</td>
									</tr>
									<?php
									if (isset($abx->report['BioFilm']) && $abx->report['BioFilm'] && isset($abx->report['biofilm_yeasts'][$bat->ID])) {
										$synbat = $abx->report['biofilm_yeasts'][$bat->ID];
									?>
										<tr class="headerline">
											<td>DETECTED LEVEL</td>
											<td class="center"><?php echo $abx->bac_text_level($bat->lmh); ?></td>
										</tr>
										<tr class="headerline">
											<td>PATHOGEN %</td>
											<td class="center"><?php echo number_format($bat->load_percent, 2) . '%'; ?></td>
										</tr>
										<tr class="headerline rowbg_grey">
											<td>BIOFILM RISK</td>
											<td class="center"><?php echo $abx->getBat_Biofilm_risk_text($synbat); ?></td>
										</tr>
										<?php if ($abx->report['Biofilm_core'] >= 2) { ?>
											<tr class="headerline rowbg_grey">
												<td>SYNERGY PAIRS</td>
												<td class="center"><?php echo $synbat->synergypar_name ?></td>
											</tr>
										<?php } ?>
								<?php
									}
								}
								?>
							</table>

						</td>
						<td class="coli_2 nopadding center piechartarea">
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

						</td>
					</tr>
				</table>

			</td>
			<td class="paddingabx">
				<?php
				$abxtabledata 		= $abx->bv_get_abxs('yeast');
				$abx_groups			= $abx->yeast_uti_wound_abx_groups;

				if (empty($abx_groups)) {
					$abx_groups = array(
						array(
							'abxs' => $abxtabledata
						)
					);
				} else {
					//get 
					$textbyconditions = array();
				}

				foreach ($abx_groups as $abxarr) {
					$abxtabledata 	= is_array($abxarr['abxs']) ? $abxarr['abxs'] : array();
					$summary_txt 	= '';
					$bacicons 		= '';
					if (isset($abxarr['name'])) {
						foreach ($abxarr['bacids'] as $bid) {
							$bacicons .= '<span class="abxroundcic abxround_lb " style="background:' . $colors[$bid] . '"></span>';
						}
						$summary_txt = ' <span class="abxbatname">' . $bacicons . $abxarr['name'] . '</span>';
						//get $textbyconditions
						$conditext = $abx->getTextbyCondition('yeast', null, $abxarr['max_risk'], $yeast_risk, null);
						$conditext['name'] =  $bacicons . $abxarr['name'];
						$textbyconditions[] = $conditext;
					} else if (isset($sumaly['yeast'])) {
						$summary_txt = '<span class="bvrisktext">[' . $sumaly['yeast']['risktext'] . ' - ' . $sumaly['yeast']['comment'] . ']</span>';
					}
					echo '<div class="header1 ">' . (!empty($abx->yeast_uti_wound) ? '' : 'VVC ') . 'Treatment Options:' . $summary_txt . '</div>';

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
									$front_abx_name = '';
									$addingabxtext = '';
									$abxcolor = $abx->get_bv_color($r->abx ?? '');
									if (isset($r->abx)) {
										//check fda
										if (stripos($r->fda, 'yeast') !== false) {
											$cdcfile 	= 'cdc.png';
											$cdc_class 	= 'abxfda';
											if (stripos($r->fda, '2') !== false) {
												$cdcfile 	= 'cdc2.png';
												$cdc_class 	.= ' abxfda2';
											}
											$addingabxtext .= '<img src="' . $asset_url . 'reports/abx/' . $cdcfile . '" class="abxcdcimg"/>';
											$addingabxtext .=  ' <span class="' . $cdc_class . '">CDC</span>';
										}

										if ($abxcolor > 0) {
											$addingabxtext .=  '<span class="abxdenote abxdenote_' . $abxcolor . '"></span>';
										}

										$r->types = $abx->getAbxtypes($r->abx);

										$addingabxtext .= '<span class="fright righticons">';

										if (isset($r->excluded) && $r->excluded) {
											$has_abx_minus_icon = true;
											$addingabxtext .= '<img src="' . $asset_url . 'reports/abx/_icon.png" class="minus_icon frontabx"/>';
										}

										if ($r->types && $abx->countBVType > 0) {
											$addingabxtext .= '<span class=" abxiconh1">';

											foreach ($r->types as $tki) {
												$addingabxtext .= '<span class="abxroundcic abxround_' . $tki . ' "></span>';
											}
											$addingabxtext .= '</span>';
										}

										$addingabxtext .= '</span>';
									}

									$tr .= '<td class="' . $colclass1 . '">' . $front_abx_name . ($r->abx ?? '') . $addingabxtext . '</td>';
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
				}


				echo $abx_maker_notes;
				$abx_maker_notes = '';
				?>
			</td>
		</tr>

		<?php

		foreach ($textbyconditions as $textbycondition) {

			if (isset($textbycondition['name'])) {
				echo '<tr class="header2">';
				echo '<td class="header">INFECTION NOTES: ' . $textbycondition['name'] . '</td>';
				echo '<td class="header">TREATMENT NOTES: ' . $textbycondition['name'] . '</td>';
				echo '</tr>';
			}
			echo '<tr>';
			echo '<td>';


			echo '<div class="healthy_text">';
			echo  $textbycondition['desc_1'];
			echo '</div>';
			echo '</td>';

			echo '<td class="treatmentcontent">';



			echo '<table class="noborder"><tr><td class="thawngabx" style="width:10px">!</td>';
			echo '<td>' . $textbycondition['desc_2'] . '</td>';
			echo '</tr></table>';
			echo '</td>';
			echo '</tr>';
		}

		?>

		<tr class="bvbottom">
			<td>
				<div class="legend_text">
					<?php
					if (in_array($abx->yeast_uti_wound, array('uti', 'wound', 'ei', 'nail')) || $abx->type == 'v') {
						//echo '*Fungal load alone cannot reliably differentiate infection from colonization. Data should be interpreted within clinical context when evaluating treatment options.';
						echo '*Estimated, not a precise measurement. Fungal load alone does not reliably distinguish infection from colonization; interpret within clinical context. For educational purpose only.';
					} else {
						echo 'The estimate is based solely on algorithmic analysis of PCR results and should not be the only factor considered in making treatment decisions.';
					}
					?>
				</div>
			</td>
			<td class="treatmentcontent">
				<?php if ($report['pa_gender'] == 'F' && isset($report['has_pregnancy']) && $report['has_pregnancy']) { ?>
					<div class="legenditem">
						<img src="<?php echo $asset_url ?>reports/abx/icon_Pregnancy.png" class="checkmarkdose" />
						Generally considered safe during pregnancy (category A/B).
					</div>
				<?php } ?>
				<?php if (empty($abx->yeast_uti_wound)) { ?>
					<div class="legenditem">
						<img src="<?php echo $asset_url ?>reports/abx/cdc.png" class="checkmarkdose" />
						CDC recommended treatment.
					</div>
				<?php } ?>
				<?php if ($has_abx_minus_icon) { ?>
					<div class="legenditem">
						<img src="<?php echo $asset_url ?>reports/abx/_icon.png" class="checkmarkdose" />
						Excluded: Predominant BV pathogens are typically &lt;80% susceptible.
					</div>
				<?php } ?>

			</td>
		</tr>
	</table>
<?php

	if ($break_before_pathogentbl) {
		echo '</div>';
	}

	if (!empty($abx->yeast_uti_wound)) {
		$pathogen_rows 	= array();
		$irow  			= 0;
		foreach ($yeast_batches as $bat) {
			if (!isset($pathogen_rows[$irow])) {
				$pathogen_rows[$irow] = array();
			}

			$pathogen_rows[$irow][] = $bat;
			if (count($pathogen_rows[$irow]) >= 2) {
				$irow++;
			}
		}
		foreach ($pathogen_rows as $rownum => $row) {

			$styletbl = 'style="margin-bottom:4px"';
			if ($rownum == count($pathogen_rows) - 1) {
				$styletbl = 'style="margin-bottom:10px"';
			}
			echo '<table class="reporttblstyle2 pathogentbl" ' . $styletbl . '>' . "\n";
			$rowkeys = array('header2', 'content2');

			foreach ($rowkeys as $rk) {

				echo '<tr class="' . $rk . '">' . "\n";

				foreach ($row as $bat) {

					echo '<td ' . (count($row) == 1 ? 'colspan=2' : ' class="w50"') . '>' . "\n";
					if ($rk == 'header2') {
						$batitle = $bat->name;
						echo $batitle;
					} else if ($rk == 'content2') {
						echo '<div class="pathogenicity_desc">' . $bat->description2_yeast . '</div>' . "\n";
					} else if ($rk == 'footertext') {
						$bacteria_r_text = $short_pathogencities[ceil($bat->bmrisk / 2) - 1];
						if (!empty($bacteria_r_text)) {
							echo '<span class="squareicon background_' . $bat->risk_color . '"></span> <span>PATHOGENICITY: ' . $bacteria_r_text . '</span>';
						}
					}
					echo '</td>' . "\n";
				}

				echo '</tr>' . "\n";
			}
			echo '</table>' . "\n";
		}
	}

	if (!$break_before_pathogentbl) {
		echo '</div>'; //always one block
	}
}
