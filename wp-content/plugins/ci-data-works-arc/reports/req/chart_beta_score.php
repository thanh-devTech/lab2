<?php
$score = isset($_GET['score']) ? $_GET['score'] : $pcr_beta_score['conc'];
$score_dis = isset($pcr_beta_score['conc_dis']) && !empty($pcr_beta_score['conc_dis']) ? $pcr_beta_score['conc_dis'] : $score;

$cutoff = floatval(str_replace(array('>', '<', '='), '', $pcr_beta_score['te_upper_limit']));

if ($score < 0) $score = 0;
$dotleft = ($score - $cutoff) * 16.08 + 204;
if ($score > $cutoff) {
	$dotleft += 15;
}
if ($dotleft < 0) $dotleft = 0;
if ($dotleft > 418) $dotleft = 418;

if ($is_sum_page) {
?>
	<div class="scoreheader">Beta Score</div>
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
							<div class="buttontest">Beta Test RESULT</div>
						</td>
						<td class="resultvalue">
							<?php echo $score_dis ?> % UnMethylated Insulin
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
						</tr>
						<tr>
							<td>Normal (&lt;<?php echo $cutoff ?>)</td>
							<td>Elevated (&gt;<?php echo $cutoff ?>)</td>
						</tr>
					</table>
				</div>
				<div class="rangereftext">Reference Ranges</div>
				<div class="rangevalue">Normal: &lt;<?php echo $cutoff ?>% , Elevated: <?php echo $cutoff ?>% or above</div>
			</div>
			<?php
			if ($is_sum_page) {
			?>
			</td>
		</tr>
	</table>
<?php
			}
