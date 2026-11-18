<div class="specimen_test">
	<div class="frow">
		<h4>Drug Screening</h4>
	</div>
	<div class="frow">
		<div class="col5">
			<strong>Test</strong>
		</div>

		<div class="col5">
			<strong>Value</strong>
		</div>

		<div class="col5">
			<strong>Test Outcome</strong>
		</div>
		<div class="col5">
			<strong>Reference Range</strong>
		</div>
		<div class="col5">
			<strong>Comments</strong>
		</div>
	</div>
	<div class="frow">
		<?php
		foreach ($screenings as $arr) {
			$key = $arr['u_ID'];

			$arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);


			if ($arr['checked']) {
				$has_screening = true;
			} else {
				continue;
			}

			if (isset($arr['readonly']) && $arr['readonly'] && !empty($arr['outcome'])) {
				//dont change pos/neg
			} else if ($arr['outcome'] != 'CAN') {
				if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
					$arr['outcome'] = 'POS';
				} else if (!empty($arr['conc']) || $arr['conc'] === '0') {
					$arr['outcome'] = 'NEG';
				}

				if (strtolower($arr['comments']) == 'description') {
					$arr['comments'] = '';
				}
			}
			if (($arr['outcome'] == 'POS' || $arr['outcome'] == 'ABNORMAL')) {
				$k = $arr['conc'] < $arr['u_low'] ? 'low' : 'high';
				$cm = $arr['u_comments'];
				if (stripos($cm, '||') > 0) {
					if (stripos($arr['comments'], ($k == 'low' ? 'high' : 'low')) === 0) {
						$arr['comments'] = '';
					}
					if (empty($arr['comments'])) {
						$cms = explode('||', $cm);
						$has_cm = false;
						foreach ($cms as $c) {
							$c = trim($c);
							if (stripos($c, $k) === 0) {
								$cm = $c;
								$has_cm = true;
								break;
							}
						}
						if (!$has_cm) {
							$cm = $arr['conc'] < $arr['u_low'] ? $cms[0] : $cms[1];
						}
					}
					$arr['comments'] = $cm;
				} else if (empty($arr['comments'])) {
					$arr['comments'] = $arr['u_comments'];
				}
			}
		?>
			<div class="frow specimen_row <?php if ($arr['outcome'] == 'POS') echo 'isred' ?>">
				<div class="col5">
					<?php echo htmlspecialchars($arr['u_name']) ?>
				</div>

				<div class="col5">
					<input type="text" data-min="<?php echo $arr['u_low'] ?>" data-max="<?php echo $arr['u_high'] ?>" class="speciment_value" name="data_specimen_screening[<?php echo $key ?>][conc]" value="<?php echo $arr['conc'] ?>" />
				</div>

				<div class="col5">
					<select name="data_specimen_screening[<?php echo $key ?>][outcome]" class="specimen_outcome" data-value="<?php echo $arr['outcome'] ?>" data-min="<?php echo $arr['u_low'] ?>" data-max="<?php echo $arr['u_high'] ?>" data-comments="<?php echo $arr['u_comments'] ?>" data-u_reflex="<?php echo $arr['u_reflex'] ?>" data-u_reflex_tests="<?php echo $arr['u_reflex_tests'] ?>" data-u_reflex_clinics="<?php echo $arr['u_reflex_clinics'] ?>">
						<?php
						$outcomes = array(
							'' => 'Select',
							'NEG' => 'NORMAL',
							'POS' => 'ABNORMAL',
							'CAN' => 'Canceled',
						);
						if ($arr['u_neg_pos']) {
							$outcomes = array(
								'' => 'Select',
								'NEG' => 'NEG',
								'POS' => 'POS',
								'CAN' => 'Canceled',
							);
						}
						foreach ($outcomes as $k => $vl) {
							$selected = $k == $arr['outcome'] ? 'selected' : '';
							echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
						}
						?>
					</select>
				</div>
				<div class="col5">
					<?php echo $arr['u_range'] ?>&nbsp;
				</div>
				<div class="col5">
					<textarea class="specimen_comments" name="data_specimen_screening[<?php echo $key ?>][comments]" style="height:50px;"><?php echo $arr['comments'] ?></textarea>
				</div>
			</div>
		<?php
		}
		?>
	</div>
</div>
