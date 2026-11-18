<?php

$row['is_96'] = true;
$assens = trpdd_get_assens_plate($row);

$class_plate = 'color_' . $row['pl_number'];
if (isset($row['pl_combine_ID']) && $row['pl_combine_ID'] > 0) {
	//$class_plate.= ' combined ';
}
if ($row['pl_lock']) {
	//$class_plate.= ' locked ';
}
$num_cols = 12;
$num_rows = 8;

if (isset($row['sub_number']) && $row['sub_number'] > 0) {
	$exportblockid = 'num_' . $row['sub_number'];
}
if ($rack_uti_type == 14 && $row['re_is_uti_panel'] == 1) {
	$num_cols = 24;
	$num_rows = 16;
	$row['pl_ntc_position'] = 1000;
	$row['pl_ntc2_position'] = 1000;
}
?>

<div class="outerplate">
	<div class="plateblock <?php echo $class_plate ?>" data-id="<?php echo $row['pl_ID'] ?>">
		<span class="dashicons dashicons-flag flag-<?php echo $row['re_priority'] ?>"></span>
		<div class="header">
			<label style="float: left;"><input type="checkbox"></label>
			<a class="button printbtn1 small" style="float:left;margin-left:15px" href="#">Print</a>

			<div class="title" title="<?php echo $row['pl_today'] ?>"><?php echo $plate_name ?></div>
			<div class="stitle">Rack #<?php echo $row['re_number'] . ' - ' . $row['re_subtitle'] ?></div>

			<?php if ($rack_printht) { ?>
				<div class="onlyprint stitle" style="line-height: 30px">
					Hood #:___________ &nbsp;&nbsp;&nbsp;Tech. Initials: _____________
					Positives___________ 
					<br>
					Reruns___________
					Need changed to NaN___________
					Rejected___________
				</div>
			<?php } ?>
		</div>
		<div class="ccontent">
			<table class="platetbl">
				<thead>
					<tr>
						<?php
						echo '<th></th>';
						for ($c = 1; $c <= $num_cols; $c++) {
							echo '<th>' . $c . '</th>';
						}
						?>
					</tr>
				</thead>
				<?php
				for ($r = 1; $r <= $num_rows; $r++) {
					$char = chr($r + 64);
					echo '<tr>';
					echo '<th>' . $char . '</th>';
					for ($c = 1; $c <= $num_cols; $c++) {
						if ($rack_uti_type == 14 && $row['re_is_uti_panel'] == 1) {
							$t_pos = ($r - 1) * $num_cols + $c;
						} else if ($rack_16) {
							$t_pos = $r + (($c - 1) % 12) * 8;
							if ($t_pos >= 9 && $t_pos < 49) {
								$t_pos = -1;
							} else if ($t_pos >= 49 && $t_pos <= 56) {
								$t_pos -= 40;
							}
						} else if (!empty($config_plate) && $config_plate['type'] == 96) {
							$t_pos = ($r - 1) * 12 + $c;
						} else if ($rack_ABC) {
							$t_pos = $r + (($c - 1) % 12) * 8;
						} else {
							$t_pos = ($r - 1) * 12 + $c;
						}

						$class = '';
						if ($row['pl_ntc_position'] == $t_pos) {
							$class .= ' ntc1 ';
						} else if ($row['pl_ntc2_position'] == $t_pos) {
							$class .= ' ntc2 ';
						} else if ($row['pl_ntc3_position'] == $t_pos) {
							$class .= ' ntc1 ';
						} else if ($row['pl_ntc4_position'] == $t_pos) {
							$class .= ' ntc2 ';
						} else if ($row['pl_blank1_position'] == $t_pos) {
							$class .= ' blank1 ';
						} else if ($row['pl_blank2_position'] == $t_pos) {
							$class .= ' blank2 ';
						}
						$arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array();
						$assen = isset($arr['assen'])? $arr['assen'] : '';
						if (isset($arr['class'])) {
							$class .= ' ' . $arr['class'] . ' ';
						}
						if (strpos($assen, ',') !== false) {
							$assen = str_replace(',', '<br>', $assen);
						} else if (stripos($assen, 'POS ') !== false || stripos($assen, 'NEG ') !== false) {
							$assen = str_replace(' ', '<br>', $assen);
						} else if (stripos($class, 'ntc') === false) {
							preg_match("/([a-zA-Z]*)([0-9]*)/", $assen, $matches);
							if (!empty($matches[0])) {
								$assen = $matches[1] . '<br>' . $matches[2];
							}
						}

						echo '<td class="' . $class . '" title="' . $t_pos . '">';
						echo $assen;
						if (isset($arr['is_rerun']) && $arr['is_rerun']) echo '<span class="scircle greencircle fright sscircle" title="Rerun">R</span>';
						echo '</td>';
					}
					echo '</tr>';
				}

				?>
			</table>

		</div>

		<?php if ($rack_printht) { ?>
			<div class="onlyprint stitle" style="line-height: 30px">
				Reviewed/Released by: ___________________________
				&nbsp;&nbsp;&nbsp;
				2nd Reviewer: ________________________
			</div>
		<?php } ?>

	</div>
</div>