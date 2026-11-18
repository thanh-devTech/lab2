<?php

if (count($yeast_batches) > 0) {
	if (isset($_GET['debugabx']) && isset($_COOKIE['trquery_monitor'])) {
		echo '<br>file: abx_yeast_1.php<br>';
	}

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
								$addingabxtext = '';
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
