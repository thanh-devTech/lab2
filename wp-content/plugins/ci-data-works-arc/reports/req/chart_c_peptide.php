<?php
$score = isset($_GET['score']) ? $_GET['score'] : $blood_C_Peptide['conc']; //0.8-3.85
$score_dis = isset($blood_C_Peptide['conc_dis']) && !empty($blood_C_Peptide['conc_dis']) ? $blood_C_Peptide['conc_dis'] : $score;

$low 	= floatval(str_replace('<', '', $blood_C_Peptide['low']));
$high 	= floatval(str_replace('>', '', $blood_C_Peptide['high']));
$min	= isset($blood_C_Peptide['te_ll']) ? floatval($blood_C_Peptide['te_ll']) : 0;
$max	= isset($blood_C_Peptide['te_ul']) ? floatval($blood_C_Peptide['te_ul']) : 0;

if ($score < $low) {
	if ($score < $min) {
		$dotleft = 0;
		$score = '&lt;' . $min;
	} else {
		$dotleft = 139.3 * ($score - $min) / ($low - $min);
	}
} else if ($score > $high) {
	if ($score > $max && $max > 0) {
		$dotleft = 418;
		$score = '&gt;' . $max;
	} else {
		$dotleft = 139.3 * ($score - $high) / ($max - $high) + 278.6;
	}
} else {
	$dotleft = 139.3 * ($score - $low) / ($high - $low) + 139.3;
}


if ($dotleft < 0) $dotleft = 0;
if ($dotleft > 418) $dotleft = 418;

if (isset($_GET['debug'])) {
	var_dump($blood_C_Peptide);
}





if ($is_sum_page) {
?>
	<div class="scoreheader">C-Peptide</div>
		<table class="sumchartbox">
			<tr>
				<td class="scorenumber"><span><?php echo $score_dis?></span></td>
				<td class="mainchart">
			<?php
		}
			?>

			<div class="result_box">
				<table class="headercharttbl">
					<tr>
						<td class="hbuttontd">
							<div class="buttontest">C-Peptide RESULT</div>
						</td>
						<td class="resultvalue">
							<?php echo $score_dis . ' ' . $blood_C_Peptide['te_units'] ?>
						</td>
						<td class="hbuttontd"></td>
					</tr>
				</table>
				<div class="scorewrap_box">
					<div class="score_dot" style="margin-left: <?php echo $dotleft . 'px' ?>"></div>
					<table class="rangecolortbl">

						<tr>
							<td>
								<div class="rangecolor ccyellow"></div>
							</td>
							<td>
								<div class="rangecolor ccgreen"></div>
							</td>
							<td>
								<div class="rangecolor ccred"></div>
							</td>
						</tr>
						<tr>
							<td>Low ( <?php echo  '&lt;' . $low ?> )</td>
							<td>Normal (<?php echo $blood_C_Peptide['range'] ?>)</td>
							<td>High ( <?php echo '&gt;' . $high ?> )</td>
						</tr>
					</table>
				</div>
				<div class="rangereftext">Reference Ranges</div>
				<div class="rangevalue">
					Low: <?php echo '&lt;' . $low . ' ' . $blood_C_Peptide['te_units'] ?>,
					Normal: <?php echo $blood_C_Peptide['range'] . ' ' . $blood_C_Peptide['te_units'] ?>,
					High: <?php echo '&gt;' . $high . ' ' . $blood_C_Peptide['te_units'] ?>
				</div>
			</div>
			<?php
			if ($is_sum_page) {
			?>
			</td>
		</tr>
	</table>
<?php
			}
