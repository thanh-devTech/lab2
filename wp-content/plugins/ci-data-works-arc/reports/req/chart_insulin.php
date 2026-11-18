<?php
$score = isset($_GET['score']) ? $_GET['score'] : $blood_Insulin['conc'];
$score_dis = isset($blood_Insulin['conc_dis']) && !empty($blood_Insulin['conc_dis']) ? $blood_Insulin['conc_dis'] : $score;


$min	 = isset($blood_Insulin['te_ll']) ? floatval($blood_Insulin['te_ll']) : 0;
$max	 = isset($blood_Insulin['te_ul']) ? floatval($blood_Insulin['te_ul']) : 100;
$cutoff	 = isset($blood_Insulin['te_medium']) ? floatval(str_replace('<', '', $blood_Insulin['te_medium'])) : 0;

if ($score < $cutoff) {
	if ($score < $min) {
		$dotleft = 0;
		$score = '&lt;' . $min;
	} else {
		$dotleft = 209 * ($score - $min) / ($cutoff - $min);
	}
} else {
	if ($score > $max && $max > 0) {
		$dotleft = 418;
		$score = '&gt;' . $max;
	} else {
		$dotleft = 209 * ($score - $cutoff) / ($max - $cutoff) + 209;
	}
}

if ($dotleft < 0) $dotleft = 0;
if ($dotleft > 418) $dotleft = 418;

if ($is_sum_page) {
?>
	<div class="scoreheader">Insulin</div>
	<table class="sumchartbox">
		<tr>
			<td class="scorenumber"><span><?php echo $score_dis ?></span></td>
			<td class="mainchart">
			<?php
		}
			?>

			<div class="result_box">

				<table class="headercharttbl">
					<tr>
						<td class="hbuttontd">
							<div class="buttontest">Insulin RESULT</div>
						</td>
						<td class="resultvalue">
							<?php echo $score_dis . ' ' . $blood_Insulin['te_units'] ?>
						</td>
						<td class="hbuttontd"></td>
					</tr>
				</table>
				<div class="scorewrap_box">
					<div class="score_dot" style="margin-left: <?php echo $dotleft . 'px' ?>"></div>
					<table class="rangecolortbl">
						<tr>
							<td>
								<div class="rangecolor ccgreen"></div>
							</td>
							<td>
								<div class="rangecolor ccred"></div>
							</td>
						</tr>
						<tr>
							<td>Normal ( <?php echo '&lt;' . $blood_Insulin['range'] ?>)</td>
							<td>High ( <?php echo '&gt;' . $blood_Insulin['range'] ?> )</td>
						</tr>
					</table>
				</div>
				<div class="rangereftext">Reference Ranges</div>
				<div class="rangevalue">Normal: <?php echo '&lt;' . $blood_Insulin['range'] ?>uIU/mL, High: <?php echo '&gt;' . $blood_Insulin['range'] ?> uIU/mL</div>
			</div>
			<?php
			if ($is_sum_page) {
			?>
			</td>
		</tr>
	</table>
<?php
			}
