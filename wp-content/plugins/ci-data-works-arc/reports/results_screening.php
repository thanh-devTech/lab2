<?php
global $have_urine, $have_screening, $wpdb;

$screenings = tr_pdd_get_result_validity(1, $report['batch_created'], $report['mid']);
$urv_group_test_IDs = array();
$screening_reflex_medications = isset($report['medications']) ? $report['medications'] : (isset($report['current_medication']) ? $report['current_medication'] : '');
$has_screening_reflex = count(trpdd_get_tests_reflex_by_screening($report['mid'], $report['cli_ID'], $report['batch_created'], $screening_reflex_medications)) > 0;
if (count($screenings) > 0 && $report['matrix'] != "oral") {
	$show_validity = false;
	$urine_settings = get_option('ci_urine_validity_settings', array());

	$rs_groups = $wpdb->get_results("select 
	                    tg_ID as ID, 
	                    tg_name as name,
	                    tg_comments as items 
	            from ci_test_groups 
	            where tg_type = 'urv'");

	$urv_groups = array();
	foreach ($rs_groups as $g) {
		$items_ids = $wpdb->get_col("select u_ID from ci_urine_validity 
                    where u_showinreq = 1 and u_ID in({$g->items})");

		$urv_groups[$g->ID] = array(
			'name' => $g->name,
			'items' => $items_ids,
			'all' => explode(',', $g->items)
		);
	}
	$selected_ids = array();

	$colcount = 5;

	if ($urine_settings['hide_cutoff_screening']) {
		$colcount--;
	}
	if (!$urine_settings['hide_tbl_border']) {
		$colcount--;
	}

	ob_start();
?>
	<table>
		<tr>
			<td class="dontbreak marginbottom" style="padding:0">
				<table class="<?php if (!$urine_settings['hide_tbl_border']) echo 'tdborder' ?> small_table ">
					<tr class="header_tbl">
						<th colspan="<?php echo $colcount - 1 ?>" class="header_black">
							<h4>DRUG SCREENING</h4>
						</th>
						<td class="center" style="border:none">
							<?php if (tr_get_option('ci_result_settings_tox', 'pdf_show_temp_after_panel') && !empty($report['temperature'])) {
								echo 'Temperature: ' . htmlentities2($report['temperature']);
							} ?>
						</td>
					</tr>
					<tr class="title_tbl">
						<th style="width:20%">
							Test
						</th>

						<th style="width:10%">
							<?php if (!$urine_settings['hide_value_screening']) : ?>
								Value
							<?php endif; ?>
						</th>

						<th style="width:10%">
							Result
						</th>
						<?php if (!$urine_settings['hide_cutoff_screening']) { ?>
							<th style="width:10%">
								<?php echo $urine_settings['hide_value_screening'] ? ' Reference Range ' : 'Cutoff Level (ng/mL)' ?>
							</th>
						<?php } ?>
						<th>
							Comments
						</th>
					</tr>

					<?php

					foreach ($screenings as $arr) {
						$key = $arr['u_ID'];

						if (!$arr['checked']) {
							continue;
						}
						$selected_ids[] = $arr['u_ID'];

						foreach ($urv_groups as $gid => $garr) {
							if (!in_array($arr['u_ID'], $garr['items'])) {
								unset($urv_groups[$gid]);
							}
						}


						$arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

						if (isset($arr['readonly']) && $arr['readonly'] && !empty($arr['outcome'])) {
							//dont change pos/neg
						} else if ($arr['outcome'] != 'CAN') {
							if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
								$arr['outcome'] = 'POS';
							} else if (!empty($arr['conc']) || $arr['conc'] === '0') {
								$arr['outcome'] = 'NEG';
							}

							if ($arr['u_neg_pos']) {
								//if (empty($arr['outcome'])) $arr['outcome'] = 'NEG';

								if (!empty($arr['conc']) && $arr['conc'] < $arr['u_low']) {
									$arr['conc'] = 'Low';
								} else if (!empty($arr['conc']) && $arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0) {
									$arr['conc'] = 'High';
								} else {
									//$arr['conc'] = '';
								}
							} else if (!empty($arr['conc']) && $arr['conc'] < $arr['u_low']) {
								$arr['conc'] = '<' . $arr['u_low'];
							} else if (!empty($arr['conc']) && $arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0) {
								$arr['conc'] = '>' . $arr['u_high'];
							}


							if (empty($arr['u_range'])) {
								$arr['u_range'] = $arr['u_low'] . ' - ' . $arr['u_high'];
							}
							if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
								$arr['outcome'] = 'POS';
							} else if (!empty($arr['conc']) || $arr['conc'] === '0') {
								$arr['outcome'] = 'NEG';
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

						if ($arr['outcome'] == 'POS') {
							$arr['outcome'] = '<b class="red">POS</b>';
						}
						if (strtolower($arr['comments']) == 'description') {
							$arr['comments'] = '';
						}

						if (isset($_REQUEST['pos_results']) && $arr['outcome'] == 'NEG') {
							continue;
						}

						if (empty($arr['outcome']) && empty($arr['conc'])) {
							continue;
						}

						$show_validity = true;
					?>
						<tr class="screening_row">
							<td>
								<?php echo htmlentities2($arr['u_name']); ?>
							</td>

							<td>

							</td>

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
							<?php if (!$urine_settings['hide_cutoff_screening']) { ?>
								<td class="center">
									<?php echo htmlentities2($arr['u_range']) ?>
								</td>
							<?php } ?>
							<td>
								<?php echo nl2br(htmlentities2($arr['comments'])) ?>
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

	if ($has_screening_reflex) {
		$report['only_screening'] = false;
	}

	foreach ($urv_groups as $gid => $garr) {
		foreach ($garr['items'] as $tid) {
			if (!in_array($tid, $selected_ids)) {
				unset($urv_groups[$gid]);
				break;
			}
		}
	}

	if (count($urv_groups) == 1) {
		foreach ($urv_groups as $gid => $garr) {
			$content = str_replace('>DRUG SCREENING<', '>' . $garr['name'] . '<', $content);
			$urv_group_test_IDs = $garr['all'];
		}
	}
	if ($show_validity) {

		$have_screening = true;
		$have_urine = true;
		if (isset($return_screening_content) && $return_screening_content) {
			return $content;
		}
		echo $content;
	}
}
