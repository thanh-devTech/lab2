<?php
$score = isset($_GET['score']) ? $_GET['score'] : $blood_HbA['conc'];
$score_dis = isset($blood_HbA['conc_dis']) && !empty($blood_HbA['conc_dis']) ? $blood_HbA['conc_dis'] : $score;

$low = floatval(str_replace('<', '', $blood_HbA['te_medium']));
$high = floatval(str_replace('>', '', $blood_HbA['te_critical_high']));
$min	= isset($blood_HbA['te_ll']) ? floatval($blood_HbA['te_ll']) : 0;
$max	= isset($blood_HbA['te_ul']) ? floatval($blood_HbA['te_ul']) : 0;

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



if ($is_sum_page) {
?>
	<div class="scoreheader">Hemoglobin A1c</div>
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
							<div class="buttontest">HbA1c RESULT</div>
						</td>
						<td class="resultvalue">
							<?php echo $score_dis . ' ' . $blood_HbA['te_units'] ?>
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
								<div class="rangecolor ccyellow"></div>
							</td>
							<td>
								<div class="rangecolor ccred"></div>
							</td>
						</tr>
						<tr>
							<td>Normal ( &lt; 5.7%)</td>
							<td>At Risk ( 5.7 - 6.4%)</td>
							<td>High ( &gt; 6.4% )</td>
						</tr>
					</table>
				</div>
				<div class="rangereftext">Reference Ranges</div>
				<div class="rangevalue">Normal: &lt;5.7%, At-Risk: 5.7-6.4%, High: &gt;6.4%</div>
			</div>

			<?php
			if ($is_sum_page) {
			?>
			</td>
		</tr>
	</table>
<?php
			}
