<?php

$count_errors 		= isset($count_errors) ? $count_errors : 0;
$first_col_width 	= 140;
$last_col_width 	= 138;
$count_cols 		= $abx->getcountBacterias();

if ($abx->ShowColOrphan) {
	$count_cols += count($abx->getOrphanMarkers());
}
if ($count_cols == 1) {
	$first_col_width = 200;
	$last_col_width = 200;
} else if ($count_cols == 2) {
	$first_col_width = 180;
	$last_col_width = 180;
}
$adding_space = 20;
if ($abx->Biofilm_Risk_infection > 4) {
	$adding_space += 8;
}
if ($abx->show_pregnancy) {
	$adding_space += 50;
}
$batw = round((780 - $adding_space - $first_col_width - $last_col_width) / $count_cols, 1);
$hastbldata = false;
?>
<div class="overtableabxmain">
	<table class="reportabx_tbl">
		<thead>
			<tr class="pathogencity">
				<th class="noborder" style="width:8px;">&nbsp;</th>
				<th class="noborderleft" style="width:<?php echo $first_col_width ?>px;">Pathogenicity</th>
				<?php
				foreach ($abx->getSelectedBacterias() as $bat) {
					if ($bat->is_hide) continue;
					$risk = $abx->get_bat_risk($bat->ID, 'pathogenicity');
					echo '<th style="width:' . $batw . 'px" class="background_' . $risk['color'] . ' risknum_' . $risk['number'] . '">';
					//echo $risk['number'];
					echo '&nbsp;';
					echo '</th>';
				}
				if ($abx->ShowColOrphan) {
					foreach ($abx->getOrphanMarkers() as $marker) {
						echo '<th style="width:' . $batw . 'px" class="isexclude"></th>';
					}
				}
				if ($abx->Biofilm_Risk_infection > 4) {
					echo '<th class="bgbiofilmcolhd rotate" style="width:8px" rowspan=2 title="BioFilm"><span>BioFilm</span></th>';
				}

				if ($abx->show_pregnancy) {
					echo '<th class="bgpregnancy">Pregnancy</th>';
				}
				?>
				<th>&nbsp;</th>
			</tr>
			<tr class="abxheader">
				<th style="padding:0;width:8px;height:26px" class="rotate noborder ricktext" title="RISK"><span>RISK</span></th>
				<th class="center noborderleft">ANTIMICROBIALS</th>
				<?php
				foreach ($abx->getSelectedBacterias() as $bat) {

					if ($bat->is_hide) {
						continue;
					}
					if (($bat->isexclude)) {
						$bat->abrev .= ' <span style="font-size:8px;white-space: nowrap;">(-)</span>';
					}
					echo '<th class="center ' . ($bat->isexclude  ? 'isexclude' : '') . '">' . $bat->abrev . '</th>';
				}
				if ($abx->ShowColOrphan) {
					foreach ($abx->getOrphanMarkers() as $marker) {
						echo '<th class="center isexclude">' . $marker->name . '</th>';
					}
				}
				if ($abx->show_pregnancy) {
					echo '<th class="center hpregnancy">T1 T2 T3 L</th>';
				}
				?>
				<th>ALGORITHMIC RESULTS</th>
			</tr>
		</thead>
		<tbody>
			<?php
			if ($abx->Biofilm_Risk_infection !== null || $abx->percent_quantitative || $abx->percent_quantitative_new) {
			?>

				<tr class="rowbg_grey bgwhite">
					<td class="riskbg noborder"></td>
					<td class="center">DETECTED LEVEL</td>
					<?php
					foreach ($abx->getSelectedBacterias() as $bat) {
						if ($bat->is_hide) continue;
						echo '<td class="center">';
						echo $abx->bac_text_level($bat->lmh);
						echo '</td>';
					}
					if ($abx->ShowColOrphan) {
						foreach ($abx->getOrphanMarkers() as $marker) {
							echo '<td></td>';
						}
					}
					if ($abx->Biofilm_Risk_infection > 4) {
						echo '<td></td>';
					}

					if ($abx->show_pregnancy) {
						echo '<td></td>';
					}
					echo '<td></td>';
					?>
				</tr>
				<tr class="rowbg_grey bgwhite">
					<td class="riskbg noborder"></td>
					<td class="center">PATHOGEN %</td>
					<?php
					foreach ($abx->getSelectedBacterias() as $bat) {
						if ($bat->is_hide) continue;
						echo '<td class="center">';
						if ($bat->lmh === 'D') {
							echo '-';
						} else {
							echo number_format($bat->load_percent, 2) . '%';
						}
						echo '</td>';
					}
					if ($abx->ShowColOrphan) {
						foreach ($abx->getOrphanMarkers() as $marker) {
							echo '<td></td>';
						}
					}
					if ($abx->Biofilm_Risk_infection > 4) {
						echo '<td></td>';
					}

					if ($abx->show_pregnancy) {
						echo '<td></td>';
					}
					echo '<td></td>';
					?>
				</tr>
				<?php if ($abx->Biofilm_Risk_infection !== null) { ?>
					<tr class="rowbg_grey">
						<td class="riskbg noborder"></td>
						<td class="center">BIOFILM RISK</td>
						<?php
						foreach ($abx->getSelectedBacterias() as $bat) {
							if ($bat->is_hide) continue;
							echo '<td class="center">';
							echo $abx->getBat_Biofilm_risk_text($bat);
							echo '</td>';
						}
						if ($abx->ShowColOrphan) {
							foreach ($abx->getOrphanMarkers() as $marker) {
								echo '<td></td>';
							}
						}
						if ($abx->Biofilm_Risk_infection > 4) {
							echo '<td></td>';
						}

						if ($abx->show_pregnancy) {
							echo '<td></td>';
						}
						echo '<td></td>';
						?>
					</tr>
				<?php
				}
				if ($abx->Biofilm_core >= 2) { ?>
					<tr class="rowbg_grey">
						<td class="riskbg noborder"></td>
						<td class="center">SYNERGY PAIRS</td>
						<?php
						foreach ($abx->getSelectedBacterias() as $bat) {
							if ($bat->is_hide) continue;
							echo '<td class="center">';
							if ($bat->is_synergypair) {
								echo $bat->synergypar_name;
							} else {
								echo '--';
							}
							echo '</td>';
						}
						if ($abx->ShowColOrphan) {
							foreach ($abx->getOrphanMarkers() as $marker) {
								echo '<td></td>';
							}
						}
						if ($abx->Biofilm_Risk_infection > 4) {
							echo '<td></td>';
						}

						if ($abx->show_pregnancy) {
							echo '<td></td>';
						}
						echo '<td></td>';
						?>
					</tr>
				<?php
				}
				if ($abx->hasExcludeRiskLow) {
				?>
					<tr class="rowbg_grey bgyellow">
						<td class="riskbg noborder"></td>
						<td class="center">EXCLUDE CRITERIA</td>
						<?php
						foreach ($abx->getSelectedBacterias() as $bat) {
							if ($bat->is_hide) continue;
							echo '<td class="center">';
							$excludetext = $abx->getBat_Exclude_by_text($bat);
							echo !empty($excludetext) ? $excludetext : '--';
							echo '</td>';
						}
						if ($abx->ShowColOrphan) {
							foreach ($abx->getOrphanMarkers() as $marker) {
								echo '<td></td>';
							}
						}
						if ($abx->Biofilm_Risk_infection > 4) {
							echo '<td></td>';
						}

						if ($abx->show_pregnancy) {
							echo '<td></td>';
						}
						echo '<td></td>';
						?>
					</tr>
				<?php
				}
			} //end biofirlm or %quantilative

			$countrows = 0;
			foreach ($abx->resultAbxs() as  $antiID => $arr) {

				$countrows++;

				$thawng_icon = '';
				?>
				<tr class="rowbg_<?php echo $countrows % 2 ?>">
					<td class="riskbg noborder background_<?php echo $arr['risk_color'] ?>"></td>
					<td class="noborderleft">
						<?php
						if (!empty($arr['iconcode'])) {
							echo '<img class="iconpill" src="' . $asset_url . 'reports/abx/' . $arr['iconcode'] . '.png" />';
						}

						echo !empty($arr['abrev']) ? $arr['abrev'] : $arr['name'];
						if ($arr['diamond']) {
							echo ' <img src="' . $asset_url . 'reports/abx/diamond.png" style="width:9px;margin-top:5px" />';
						}
						?>
					</td>
					<?php
					$ibnum = 0;
					foreach ($abx->getSelectedBacterias() as $bat) {
						if ($bat->is_hide) continue;
						$ibnum++;
						echo '<td class="center vmiddle batrisk_' . $bat->risk . ' ' . ($bat->isexclude ? 'isexclude' : '') . '" >';

						if (!empty($arr['scores'][$bat->ID]['icon'])) {
							echo '<img class="abx_icon" src="' . $asset_url . 'reports/abx/' . $arr['scores'][$bat->ID]['icon'] . '.png" />';
						} else {
							$count_errors++;
							if (isset($_GET['debugerror'])) {
								var_dump($arr['scores'][$bat->ID]);
								$abx->log .= "<br>Error: No icon for bacteria: {$bat->name} <br>";
							}
						}

						if (isset($_GET['debugabx'])) {
							echo '<span title="ES-RS-score|RM=' . $arr['scores'][$bat->ID]['RM'] . '">' . $arr['scores'][$bat->ID]['ES'] . ' - ' . $arr['scores'][$bat->ID]['RS'] . ' - ' . $arr['scores'][$bat->ID]['abx_score'] . '</span>';
						}
						echo '</td>';
					}
					if ($abx->ShowColOrphan) {
						foreach ($abx->getOrphanMarkers() as $marker) {
							list($abxicon, $orpanscore) = $abx->getABXMarkerIcon($marker->ID, $antiID);

							echo '<td class="center vmiddle isexclude">';
							if (!empty($abxicon)) {
								echo '<img class="abx_icon" src="' . $asset_url . 'reports/abx/' . $abxicon . '.png" />';
								if ($thawng_icon === '.') {
									//dont show
								} else if ($orpanscore <= -1) {
									//$thawng_icon = '<span class="thawngicon">!</span>';
								} else {
									$thawng_icon = '<span class="thawngicongreen">!</span>';
								}
							}

							if (empty($abxicon) || $abxicon == 'minus_grey') {
								$thawng_icon = '.';
							}
							echo '</td>';
						}
					}

					if ($abx->Biofilm_Risk_infection > 4) {

						$ba_icon = '<span class="biominus">-</span>';
						$ba_bg = '';
						if ($arr['BA'] == 1) {
							$ba_icon = '±';
							$ba_bg .= ' biobg_yellow';
						} else if ($arr['BA'] == 2) {
							$ba_icon = '+';
							$ba_bg .= ' biobg_green';
						}
						echo '<td class="center bioficon ' . $ba_bg . '">' . $ba_icon . '</td>';
					}
					if ($abx->show_pregnancy) {
						echo '<td class="center">';
						foreach (array('1', '2', '3', 'l') as $trk) {
							echo '<span class="trimester_item trimester_' . $arr['trimester_' . $trk]  . '">' . $trk . '</span>' . "\n";
						}
						echo '</td>';
					}
					?>
					<td>
						<?php
						if (!$arr['exclude']) {
							if (!empty($arr['iconcode'])) {
								echo '<img class="iconpill" src="' . $asset_url . 'reports/abx/' . $arr['iconcode'] . '.png" />';
							}
							echo !empty($arr['abrev']) ? $arr['abrev'] : $arr['name'];
							echo $thawng_icon != '.' ? $thawng_icon : '';
							if (!empty($arr['numpairs'])) {
								foreach ($arr['numpairs'] as $numpair) {
									echo '<span class="pairicon pairicon_' . $numpair . '"></span>';
								}
							}
						}
						?>
					</td>
				</tr>
			<?php

				$hastbldata = true;
			}

			if (!$hastbldata) {
				$count_errors++;
				$abx->log .= "<br>Error: No data for abx tbl<br>";
				update_option('ci_abx_last_change', time());
			}
			?>
		</tbody>
	</table>
	<?php
	$ibnum = 0;
	foreach ($abx->getSelectedBacterias() as $bat) {
		if ($bat->is_hide) continue;
		$ibnum++;
		if ($bat->risk == $abx->bat_max_risk() || $bat->risk > 10) {
			$leftriskout = ($first_col_width + 8) + ($ibnum - 1) * $batw;

			echo '<div class="warningrisk6" style="width:' . $batw . 'px;left:' . $leftriskout . 'px">';
			echo '<br><img class="abx_icon" src="' . $asset_url . 'reports/abx/warning.png" /><br><br>';
			echo '<div class="center">ID Consult<br>Recommeded</div>';
			echo '</div>';
		}
	}
	?>
</div>
<div style="border-top:4px solid #ddd;height:0"></div>


<table class="legend" style="page-break-inside:avoid">
	<tr>
		<td>
			<span class="legenditem">
				<img class="abx_icon" src="<?php echo $asset_url . 'reports/abx/checkmark_dark_green.png' ?>" />
				<span class="legendtitle"> Recommended + Highly Active (ER*≤10%)</span>
			</span>
		</td>
		<td><span class="legenditem">
				<img class="abx_icon" src="<?php echo $asset_url . 'reports/abx/plus_dark_green.png' ?>" />
				<span class="legendtitle"> Highly Active (ER*≤10%)</span>
			</span></td>
		<td><span class="legenditem">
				<img class="abx_icon" src="<?php echo $asset_url . 'reports/abx/dna_orange.png' ?>" />
				<span class="legendtitle"> Moderately Active (ER* 20-40%)</span>
			</span></td>
		<td><span class="legenditem">
				<img class="abx_icon" src="<?php echo $asset_url . 'reports/abx/minus_grey.png' ?>" />
				<span class="legendtitle"> ER*>40% or N/A</span>
			</span></td>
	</tr>
	<tr>
		<td>
			<span class="legenditem">
				<img class="abx_icon" src="<?php echo $asset_url . 'reports/abx/checkmark_light_green.png' ?>" />
				<span class="legendtitle"> Recommended + Mostly Active (ER* 10-20%)</span>
			</span>
		</td>
		<td><span class="legenditem">
				<img class="abx_icon" src="<?php echo $asset_url . 'reports/abx/plus_light_green.png' ?>" />
				<span class="legendtitle"> Mostly Active (ER* 10-20%)</span>
			</span></td>
		<td><span class="legenditem">
				<img class="abx_icon" src="<?php echo $asset_url . 'reports/abx/dna_red.png' ?>" />
				<span class="legendtitle"> Resistance Detected</span>
			</span></td>
		<td>
			<?php if (!$abx->ShowColOrphan) { ?>
				<span class="legenditem legendtitle" style="padding-left:2px">(-) &nbsp;&nbsp;Pathogen excluded</span>
			<?php } ?>
		</td>
	</tr>
</table>



<div style="font-size:9px;color:#666">
	*Estimated resistance (ER) is based on local resistance data, adjusted to reflect current testing results. Factors considered include RM expression, resistance determinants specific to each organism, and the collective resistance attributable to undetected resistance markers (for a positive adjustment of susceptibility).
</div>

<?php

if ($abx->show_pregnancy) {
	echo '<div class="legend_pregnancy">';
	echo '<div class="legrow1"><span class="legtitle">Pregnancy & Lactation</span>';
	echo '<span class="legitem">1 → First Trimester</span>';
	echo '<span class="legitem">2 → Second Trimester</span>';
	echo '<span class="legitem">3 → Third Trimester</span>';
	echo '<span class="legitem">L → Lactation</span>';
	echo '</div>';
	echo '<div class="legrow2"><span class="legtitle2">Color Safety Key</span> ';
	echo '<span class="legkeyitem"><span class="trimester_item trimester_1"></span> Safe </span>';
	echo '<span class="legkeyitem"><span class="trimester_item trimester_2"></span> Use with Caution</span>';
	echo '<span class="legkeyitem"><span class="trimester_item trimester_3"></span> Avoid if alternatives exist</span>';
	echo '<span class="legkeyitem"><span class="trimester_item trimester_4"></span> Safety not established</span>';
	echo '</div>';
	echo '</div>';
}

if ($abx->ShowColOrphan) {
	$excludetext = !$abx->hasExcludeDetectedLow ? 'Risk-Low' : 'Detected-Low';
	echo '<div class="legendorphan"><span>(-) Pathogen excluded from results (' . $excludetext . ')</span>
	<span>(o) Orphan marker (not generally found in detected organisms)</span>
	<span>! Antibiotic covers orphan marker</span></div>';
}
?>

<table style="margin-top:5px">
	<tr>
		<td style="width:64%">
			<div class=" leitemtt">PATHOGENICITY: Bacteria + Detected Resistance Markers</div>
			<table class="leitemboxs">
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_grey"></div>
					</td>
					<td>Low: Common commensal or contaminant. Treat only if symptomatic. If more pathogenic bacteria is present, it may be excluded from algorithmic results (shaded column).</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_yellow_light"></div>
					</td>
					<td>Medium-low: Likely to cause uncomplicated infections.</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_yellow_dask"></div>
					</td>
					<td>Medium-high: May cause complicated or harder-to-treat infections.</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_orange"></div>
					</td>
					<td>High: Likely to cause complicated or difficult-to-treat infections due to multiple resistances.</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_red"></div>
					</td>
					<td>Critical: Likely to present MDR with few treatment options. For complicated infections, <b>infectious disease consultation is recommended.</b></td>
				</tr>

			</table>
		</td>
		<td></td>
		<td style="width:34%">
			<div class=" leitemtt">ANTIBIOTICS: Adverse Effects Risk</div>
			<table class="leitemboxs">
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_grey"></div>
					</td>
					<td>Low: Generally well-tolerated.</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_yellow_light"></div>
					</td>
					<td>Medium-low: Some risk of adverse effects</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_yellow_dask"></div>
					</td>
					<td>Medium-high: Elevated risk of adverse effects</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_orange"></div>
					</td>
					<td>High:Significant risk, may include FDA boxed warnings</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<div class="leitembox background_red"></div>
					</td>
					<td>Highest: Greatest risk of adverse effects</td>
				</tr>
				<tr>
					<td class="colleitembox">
						<img src="<?php echo $asset_url . 'reports/abx/diamond.png' ?>" style="width:9px;" />
					</td>
					<td>Precious Antibiotic: Use only if out of options</td>
				</tr>
			</table>
		</td>
	</tr>
</table>