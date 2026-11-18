<?php
$slide_score 	= $BV_risk;
if ($slide_score > 100) $slide_score = 100;
$slideleft 		= (10 - (100 - $slide_score) / 10) * 34 - 10;
?>
<div class="center healthy_bar">
	<div class="indicator_wrap">
		<div class="indicator" title="<?php echo $slide_score ?>" style="margin-left:<?php echo $slideleft ?>px">
			<img src="<?php echo $asset_url . 'reports/abx/indicator.png' ?>" />
		</div>
	</div>
	<table class="bv_bar_tbl">
		<tr>
			<td class="bg1"></td>
			<td class="bg2"></td>
			<td class="bg3"></td>
			<td class="bg4"></td>
			<td class="bg5"></td>
			<td class="bg6"></td>
			<td class="bg7"></td>
			<td class="bg8"></td>
			<td class="bg9"></td>
			<td class="bg10"></td>
			<td class="bg11"></td>
			<td class="bg12"></td>
			<td class="bg13"></td>
			<td class="bg14"></td>
		</tr>
		<tr>
			<td colspan="7" style="text-align: left;"><?php echo $abx->bvavTypeKey == 'bav' ? 'HEALTHY' : 'LOW' ?></td>
			<td colspan="7" style="text-align: right;"><?php echo $abx->bvavTypeKey == 'bav' ? 'DISRUPTED' : 'HIGH' ?></td>
		</tr>
	</table>

</div>