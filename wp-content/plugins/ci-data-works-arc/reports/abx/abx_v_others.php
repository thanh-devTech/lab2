<?php

$others_types = array(
	'sm' => 'Male STI',
	'sr' => 'Rectal STI',
	'sp' => 'Pharyngeal',
	'sl' => 'Skin Lesions',
	'es' => 'Eye STIs',
	'wv' => 'Wound Virus',
);

foreach ($others_types as $otype => $otype_tt) {

	$has_abx_minus_icon = false;
	$has_abx_add_icon = false;
	$report['has_pregnancy'] = false;
	$report['has_cdc_green'] = false;
	$report['has_cdc_yellow'] = false;

	$other_bacterias = $abx->getBV_Bacterias($otype);

	if (count($other_bacterias) > 0) {

		if (isset($_GET['debugabx']) && isset($_COOKIE['trquery_monitor'])) {
			echo '<br>file: abx_v_others.php<br>';
		}
?>
		<table class="reporttblstyle2 separate_rowtbl" style="margin-bottom:5px;">
			<tr class="header">
				<td class="ttwicon colr1" colspan="2"><?php echo $otype_tt ?></td>
			</tr>
			<?php
			$ib = 0;
			foreach ($other_bacterias as $bat) {
				$ib++;
			?>
				<tr>
					<td class="nopadding colr1" style="width:50%;">
						<?php if ($ib == 1) { ?>
							<div class="header1"><?php echo $otype_tt ?> - Detected Organisms:</div>
						<?php } ?>
						<div class="batheader"><?php echo $bat->name ?></div>
						<div class="batdesc"><?php echo $bat->description2 ?></div>
					</td>
					<td class="paddingabx colr2">
						<?php if ($ib == 1) { ?>
							<div class="header1"><?php echo $otype_tt ?> - Treatment Options:</div>
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
										$addingabxtext = '';
										//check fda
										if (stripos($r->fda, 'sti') !== false) {
											$cdcfile 	= 'cdc.png';
											$cdc_class 	= 'abxfda';
											if (stripos($r->fda, '2') !== false) {
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
										$tr .= '<td class="coli_' . $icount . '">' . $r->abx . $addingabxtext . '</td>';
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
			<tr class="bvbottom">
				<td>

				</td>
				<td class="treatmentcontent">
					<?php if ($report['pa_gender'] == 'F' && isset($report['has_pregnancy']) && $report['has_pregnancy']) { ?>
						<div class="legenditem">
							<img src="<?php echo $asset_url ?>reports/abx/icon_Pregnancy.png" class="checkmarkdose" />
							Generally considered safe during pregnancy (category A/B).
						</div>
					<?php } ?>

					<?php if ($report['has_cdc_green']) { ?>
						<div class="legenditem">
							<img src="<?php echo $asset_url ?>reports/abx/cdc.png" class="checkmarkdose" />
							CDC recommended treatment.
						</div>
					<?php } ?>

					<?php if ($report['has_cdc_yellow']) { ?>
						<div class="legenditem">
							<img src="<?php echo $asset_url ?>reports/abx/cdc2.png" class="checkmarkdose" />
							CDC alternative treatment.
						</div>
					<?php } ?>

					<?php if ($has_abx_minus_icon) { ?>
						<div class="legenditem">
							<img src="<?php echo $asset_url ?>reports/abx/_icon.png" class="checkmarkdose" />
							Excluded: Predominant BV pathogens are typically &lt;80% susceptible.
						</div>
					<?php } ?>
					<?php if ($has_abx_add_icon) { ?>
						<div class="legenditem">
							<img src="<?php echo $asset_url ?>reports/abx/plus_icon.png" class="checkmarkdose" />
							Included: Predominant BV pathogens are typically susceptible.
						</div>
					<?php } ?>
				</td>
			</tr>
		</table>
<?php
	}
}
