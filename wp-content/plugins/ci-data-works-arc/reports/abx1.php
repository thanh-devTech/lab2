<?php
if (isset($_GET['debugabx']) && isset($_COOKIE['trquery_monitor'])) {
	echo '<br>file: abx1.php<br>';
}
if (!isset($abx)) {
	$abx = new CI_ABX();
}
if (count($abx->getSelectedBacterias()) == 0) {
	return;
}
?>

<div class="abxstyle1">
	<table style="width:100%;background:#eee;margin-bottom:10px" class="topabxtbl">
		<tr>
			<td style="width:42px;padding-top:15px;text-align:right">
				<img src="<?php echo $asset_url . 'reports/abx/bacteria.png' ?>" style="width:36px" />
			</td>
			<td style="padding: 8px;width:180px;position:relative">
				<div style="text-transform: uppercase;padding-bottom:8px;">PATHOGENS DETECTED <?php if (!empty($abx->pathogen_detected_notes)) echo '*' ?></div>
				<ul style="color: #000;list-style:none;padding-left:0">
					<?php
					$note_num = 1;
					$bac_notes = array();
					foreach ($abx->getSelectedBacterias() as $bat) {
						if ($bat->is_hide) {
							if ($abx->debug) {
								//echo 'exclude '.$bat->pathogen_name.' by hide';
							}
							continue;
						}

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

						echo '<li style="font-size:11px;line-height:13px">' . $bac_name . '</li>';
					}
					?>
				</ul>
				<?php

				if (!empty($abx->pathogen_detected_notes)) {
					echo '<div class="pathogens_notes shownoteinbottom">';
					echo  '*' . $abx->pathogen_detected_notes;
					echo '</div>';
				}
				?>
			</td>
			<td style="width:42px;border-left:5px solid #fff;padding-top:15px;text-align:right">
				<img src="<?php echo $asset_url . 'reports/abx/markers.png' ?>" style="width:36px" />
			</td>
			<td style="padding:8px 4px 8px 8px">

				<div style="text-transform: uppercase;padding-bottom:8px;">RESISTANCE DETECTED</div>
				<?php
				$has_resistance_detected = false;
				$abxclasses = $abx->getAbxClasses();
				if (!empty($abxclasses)) {
					$abxclassnames = array();
					foreach ($abxclasses as $arrc) {
						$abxclassnames[] = $arrc['name'];
					}
					echo '<div style="font-size:11px;line-height:13px">';
					echo '<strong>ABX Classes: </strong>';
					echo implode(', ', $abxclassnames);
					echo '</div>';
					$has_resistance_detected = true;
				}

				$markernames = $abx->getMarkerNames();
				if (!empty($markernames)) {
					$markernamesarr = array();
					foreach ($markernames as $arrc) {
						$markernamesarr[] = $arrc['name'];
					}
					echo '<div style="font-size:11px;line-height:13px">';
					echo '<strong>RM: </strong>' . implode(', ', $markernamesarr);
					echo '</div>';
					$has_resistance_detected = true;
				}

				if (isset($_GET['debugorphan'])) {
					$abx->has_Orphan = true;
				}
				if (!$has_resistance_detected) {
					echo '<div>None</div>';
				}

				?>

			</td>
			<td style="width:42px;border-left:4px solid #fff;padding-top:15px;text-align:right">
				<img src="<?php echo $asset_url . 'reports/abx/antibiotics.png' ?>" style="width:36px" />
			</td>
			<td style="padding:8px;width:180px;">

				<div style="text-transform: uppercase;padding-bottom:8px">Top Algorithmic Matches</div>
				<ul style="list-style:none;padding-left:0">
					<?php
					$has_pair_oral = false;
					$note_num = 1;
					$abx_notes = array();
					foreach ($abx->listABXMatches() as  $arr) {
						if (isset($arr['is_pair']) && $arr['is_pair'] && $arr['both_oral']) {
							$has_pair_oral = true;
							$arr['name'] .= '**';
						}
						if (isset($arr['notes']) && !empty($arr['notes'])) {

							$numexist = array_search($arr['notes'], $abx_notes);
							if ($numexist > 0) {
								$numnow = $numexist;
							} else {
								$numnow = $note_num++;
							}

							$arr['name'] .= '<span class="sup">' . $numnow . '</span>';
							$abx_notes[$numnow] = $arr['notes'];
						}
						echo '<li >';
						echo '<span class="bold" style="font-size:11px;line-height:13px">' . $arr['name'] . '</span>';
						if (!empty($arr['dose'])) {
							echo ' <span class="smalltext1">(' . $arr['dose'] . ')</span>';
						}
						echo  '</li>';
					}

					if (isset($_GET['debutabxnotes'])) {
						$abx_notes[1] = 'text here note abc';
					}

					if ($has_pair_oral) {
						$abx_notes[1000] = '** Paired for max. oral coverage.';
					}
					?>
				</ul>
			</td>
		</tr>
		<?php if (!empty($bac_notes) || $abx->has_Orphan || !empty($abx_notes)) { ?>
			<tr>
				<td></td>
				<td style="vertical-align: bottom;">
					<?php
					if (!empty($bac_notes)) {
						echo '<div class="legend_notes">';
						foreach ($bac_notes as $num => $note) {
							$notecontent = $num . '. ' . $note;
							echo '<div class="note_item">' . $notecontent . '</div>';
						}
						echo '</div>';
					} ?>
				</td>
				<td style="width:42px;border-left:4px solid #fff;padding-top:15px;text-align:right"></td>
				<td style="vertical-align: bottom;">
					<?php
					if ($abx->has_Orphan) {
					?>
						<div class="legend_notes">
							<div class="note_item">* Orphan marker/abx class</div>
						</div>
					<?php } ?>
				</td>
				<td style="width:42px;border-left:4px solid #fff;padding-top:15px;text-align:right"></td>
				<td style="vertical-align: bottom;">
					<?php
					if (!empty($abx_notes)) {
						echo '<div class="legend_notes">';
						foreach ($abx_notes as $num => $note) {
							echo '<div class="note_item">' . ($num < 1000 ? $num . '. ' : '') . $note . '</div>';
						}
						echo '</div>';
					}

					?>
				</td>
			</tr>
		<?php } ?>
	</table>

	<?php include('abx_main_table.php') ?>
</div>