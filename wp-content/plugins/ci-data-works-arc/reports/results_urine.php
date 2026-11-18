<?php
global $have_urine;


if ($report['matrix'] != "oral" && $report['lab_not_specimen'] != 1) {

	ob_start();

	$show_validity  = false;
	$urine_settings = get_option('ci_urine_validity_settings', array());
	$col_count      = 5;
	if ($urine_settings['hide_value']) {
		$col_count--;
	}
	if ($urine_settings['hide_cutoff']) {
		$col_count--;
	}

	$table_border = '';
	if (!$urine_settings['hide_tbl_border']) {
		$table_border = 'tdborder';
	}
	if (isset($header_bg_tb) && $header_bg_tb) {
		$table_border = 'border';
	}

?>
	<table <?php if (isset($have_screening) && $have_screening) echo 'style="padding-top:10px"' ?>>
		<tr>
			<td class="dontbreak" style="padding:0">
				<table class="<?php echo $table_border ?>  marginbottom small_table dontbreak">
					<?php
					if (isset($header_bg_tb) && $header_bg_tb) {
						if (isset($urine_summary) && $urine_summary) {
					?>
							<tr class="header_tbl">
								<th colspan="<?php echo $col_count - 1 ?>" class="header_black">
									<h4>SPECIMEN VALIDITY TEST</h4>
								</th>
								<th class="header_none"></th>
							</tr>
						<?php
						} else {
						?>
							<tr class="header_tbl header_tbl2">
								<th style="border:0;padding:0" colspan="<?php echo $col_count ?>">
									<h4>SPECIMEN VALIDITY TEST</h4>
								</th>
							</tr>
						<?php
						}
						?>
						<tr class="title_tbl">
							<th style="width:20%">
								Test
							</th>
							<?php if (!$urine_settings['hide_value']) : ?>
								<th style="width:10%">
									Value
								</th>
							<?php endif; ?>
							<th style="width:10%">
								Test Outcome
							</th>
							<?php if (!$urine_settings['hide_cutoff']) { ?>
								<th style="width:10%">
									Reference Range
								</th>
							<?php } ?>
							<th>
								Comments
							</th>
						</tr>
					<?php
					} else {

					?>
						<tr class="header_tbl">
							<th colspan="<?php echo $col_count - 1 ?>" class="header_black">
								<h4>SPECIMEN VALIDITY TEST</h4>
							</th>

						</tr>

						<tr class="title_tbl">
							<th style="width:20%">
								Test
							</th>
							<?php if (!$urine_settings['hide_value']) : ?>
								<th style="width:10%">
									Value
								</th>
							<?php endif; ?>
							<th style="width:10%">
								Test Outcome
							</th>
							<?php if (!$urine_settings['hide_cutoff']) { ?>
								<th style="width:10%">
									Reference Range
								</th>
							<?php } ?>
							<th>
								Comments
							</th>
						</tr>
					<?php
					}
					$validities = tr_pdd_get_result_validity(0, $report['batch_created'], $report['mid']);

					if (!isset($notshowvdx) || !$notshowvdx) {
						$vdx = tr_pdd_get_screening_vdx($report['mid'], $report['batch_created'], $report['batch_id']);
					} else {
						$vdx = array();
					}


					foreach ($validities as $arr) {

						if (isset($urv_group_test_IDs) && count($urv_group_test_IDs) > 0) {
							if (!in_array($arr['u_ID'], $urv_group_test_IDs)) {
								continue;
							}
						}

						$arr = trpdd_parse_tox_urine_test($arr, $report);

						if (empty($arr['outcome'])) {
							continue;
						}

						$show_validity = true;
						if (isset($_REQUEST['pos_results']) && $arr['outcome'] == 'NEG') {
							continue;
						}

					?>
						<tr>
							<td>
								<?php echo htmlspecialchars($arr['u_name']); ?>
							</td>
							<?php if (!$urine_settings['hide_value']) : ?>
								<td class="center">
									<?php if (!isset($arr['u_hide_value']) || !$arr['u_hide_value']) echo htmlentities2($arr['conc']) ?>
								</td>
							<?php endif; ?>
							<td class="center">
								<?php
								if ($arr['u_neg_pos']) {
									if ($arr['outcome'] == 'CAN') {
										$arr['outcome'] = 'Canceled';
									}
									echo $arr['outcome'];
								} else {
									$pos_negvl = array(
										'NEG' => 'NORMAL',
										'POS' => 'ABNORMAL',
										'CAN' => 'Canceled'
									);
									echo $pos_negvl[$arr['outcome']];
								}
								?>
							</td>
							<?php if (!$urine_settings['hide_cutoff']) { ?>
								<td class="center">
									<?php echo htmlentities2($arr['u_range']) ?>
								</td>
							<?php } ?>
							<td>
								<?php echo nl2br(htmlspecialchars($arr['comments'])) ?>
							</td>
						</tr>
					<?php
					}

					if ($vdx && $vdx['resulted']) {
						$show_validity = true;
					?>
						<tr>
							<td>VDX Specimen Validity Test</td>
							<?php if (!$urine_settings['hide_value']) : ?>
								<td class="center"></td>
							<?php endif; ?>
							<td class="center">
								<?php
								if ($vdx['u_neg_pos']) {
									echo $vdx['outcome'];
								} else {
									$pos_negvl = array(
										'NEG' => 'NORMAL',
										'POS' => 'ABNORMAL'
									);
									echo $pos_negvl[$vdx['outcome']];
								}
								?>
							</td>
							<?php if (!$urine_settings['hide_cutoff']) { ?>
								<td class="center">
									<?php //var_dump($vdx); 
									?>
								</td>
							<?php } ?>
							<td>
								<?php echo nl2br($vdx['comments']) ?>
							</td>
						</tr>
					<?php
					}
					?>
				</table>

			</td>
		</tr>
	</table>

<?php
	$content = ob_get_clean();

	if ($show_validity) {
		$have_urine = true;
		if (isset($return_urine_content) && $return_urine_content) {
			return $content;
		}
		echo $content;
	} else {
		$etg_to_urine = false;
	}
} else {
	$etg_to_urine = false;
}

return '';
