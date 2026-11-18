<?php
$btype = 'tox';
if ($patient_id > 0 && ! empty($from_date)) {


	$ci_cutoffs = tr_pdd_get_cutoffs($btype, $report['created_date']);

	//$wpdb->query("SET SESSION group_concat_max_len = 1000000;");
	$where = " ba.patient_id  = '{$patient_id}' 
               and ba.doc >= '{$from_date}' 
               and ba.doc <= '{$to_date} 23:59:59' ";

	if ($current_role == 'doctor') {
		$doc_id = get_user_meta($user_id, '_doc_id', true);
		$where  .= " and ba.doc_ID = {$doc_id}";
	} else if ($current_role == 'clinic') {
		$cli_id = get_user_meta($user_id, '_clinic_id', true);
		$where  .= " and ba.cli_ID = {$cli_id}";
	} else if ($current_role == 'office_manager' || $current_role == 'clinic') {

		$cid     = get_user_meta($user_id, '_clinic_id', true);
		$clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
		$clinics = implode(',', $clinics);
		$where   .= " and ba.cli_ID in ({$clinics}) ";
	}
	$all_dates = $wpdb->get_results("
            select re.id, 
            date_format(ba.doc,'%m-%d-%y') AS date_month,
            ba.assen
            from ci_reports re
            JOIN ci_batches ba ON ba.assen = re.patient_id
            where {$where} order by ba.doc asc");
	/**
	 * $sql = "select rs.drug,
	 * group_concat(date_format(ba.doc,'%m-%d')) AS dates,
	 * group_concat(rs.conc) AS concs
	 * from ci_reports re
	 * JOIN ci_batches ba ON ba.assen = re.patient_id
	 * join ci_results rs ON rs.report_id = re.id
	 * where {$where}
	 * group by rs.drug
	 * order by rs.drug, ba.doc asc";
	 * $tests = $wpdb->get_results($sql);
	 */


?>
	<div class="wraptbl">

		<table class="wp-list-table widefat striped posts blue_tbl hover_tbl border_tbl">
			<thead class="sticky">
				<tr>
					<th>Test</th>
					<?php
					$tests = array();
					foreach ($all_dates as $i => $d) {
					?>
						<th title="<?php echo $d->id ?>"><?php echo $d->date_month ?></th>
					<?php
						$id              = $d->id;
						$all_res         = array();
						$report          = tr_pdd_get_report_by_id($id);
						$all_results_q   = $wpdb->get_results("SELECT *,conc FROM ci_results WHERE report_id='" . $id . "' ", ARRAY_A);
						$methamphetamine = $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $id . "' ", ARRAY_A);

						foreach ($all_results_q as $arr) {
							$arr['drug']             = strtoupper($arr['drug']);
							$all_res[$arr['drug']] = $arr;
						}
						if (stripos(@$report['panel_tests_requested'], 'Ethanol') !== false) {
							if (! isset($all_res['Ethanol*']) && ! isset($all_res['ETHANOL*'])) {
								$all_res['zEthanol*'] = array(
									'drug'    => 'Ethanol*',
									'id'      => 'new',
									'pos-neg' => 'NEG'
								);
							}
						}
						$d->medications           = $report['medications'];
						$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($report['panel_tests_requested'], $drug_pairs);
						$medications              = tr_pdd_parse_medications($report['medications'], $drug_pairs);
						$has_Methamphetamine      = stripos($report['panel_tests_requested'], 'Methamphetamine d/l Isomers') !== false;

						foreach ($all_res as $drug => $arr) {
							$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);
							if ($arr['exclude']) {
								continue;
							}
							if (! isset($tests[$drug])) {
								$tests[$drug] = array();
							}

							$tests[$drug][$d->id] = $arr;
						}

						$all_dates[$i] = $d;
					}
					?>
				</tr>
			</thead>
			<tbody>
				<?php
				if (count($all_dates) > 0 || count($tests) > 0) {
				?>
					<tr>
						<td>Barcode</td>
						<?php
						foreach ($all_dates as $d) {
						?>
							<td><?php echo $d->assen ?></td>
						<?php
						}
						?>
					</tr>
					<tr>
						<td>Prescribed</td>
						<?php
						foreach ($all_dates as $d) {
						?>
							<td><?php echo str_replace(',', ', ', $d->medications) ?></td>
						<?php
						}
						?>
					</tr>
					<?php
					foreach ($tests as $drug => $data) {
						ob_start();
						$json         = array(array('Date', 'Cons', 'Cons', 'Cons', array('type' => 'string', 'role' => 'style')));
						$novalue      = array();
						$inovalue     = 0;
						$test_cutoffs = array();
						$test_limits  = array();

						foreach ($all_dates as $i => $d) {
							$arr = $data[$d->id];

							if (! empty($arr['conc'])) {
								$conc = str_replace(',', '', $arr['conc']);
								if (strpos($conc, '>') !== false) {
									$conc = $arr['limit'];
									if (! in_array($arr['limit'], $test_limits) && $arr['limit'] > 0) {
										$test_limits[] = $arr['limit'];
									}
								}

								if ($arr['conc'] == '<') {
									$arr['conc']    = '';
									$arr['pos-neg'] = 'NEG';
								}

								if (strpos($conc, '<') !== false) {
									$conc = $arr['cutoff'];
									if (! in_array($arr['cutoff'], $test_cutoffs) && $arr['cutoff'] > 0) {
										$test_cutoffs[] = $arr['cutoff'];
									}
									$json[$i + 1] = array(
										$d->date_month,
										floatval($conc),
										floatval($conc),
										floatval($conc),
										null
									);
								} else {
									$json[$i + 1] = array(
										$d->date_month,
										floatval($conc),
										floatval($conc),
										floatval($conc),
										null
									);
								}


								if (! isset($novalue[$inovalue]['last']) && isset($novalue[$inovalue]['first'])) {
									$novalue[$inovalue]['last'] = floatval($conc);
								}
								$inovalue++;
							} else {
								$json[$i + 1] = array($d->date_month, floatval($conc), null, null, null);

								$novalue[$inovalue]['vl'][] = $i + 1;
								if (! isset($novalue[$inovalue]['first'])) {
									$novalue[$inovalue]['first'] = $conc;
								}
							}



							$hasmedtext = '';
							if (isset($arr['is_blue']) && $arr['is_blue']) {
								$hasmedtext = 'med_';
								if ($arr['pos-neg'] == 'POS') {
									$json[$i + 1][4] = 'point {  fill-color: #0000ff; }';
								} else {
									$json[$i + 1][4] = 'point {  fill-color: #ff0000; }';
								}
							} else if ($arr['pos-neg'] == 'POS') {
								$json[$i + 1][4] = 'point {  fill-color: #ff0000; }';
							}


					?>
							<td class="is_pos_<?php echo $hasmedtext . $arr['pos-neg'] ?>"><?php echo $arr['conc'] ?></td>
						<?php
						}

						foreach ($novalue as $novl) {
							if (count($novl) == 0) {
								continue;
							}
							$min = min($novl['first'], $novl['last']);
							$max = max($novl['last'], $novl['first']);
							if ($min == 0) {
								$min = $max;
							}
							$stepvl = ($max - $min) / (count($novl['vl']) + 1);


							foreach ($novl['vl'] as $j => $pos) {
								$ii              = ($novl['first'] < $novl['last']) ? $j + 1 : count($novl['vl']) - $j;
								$vl              = $min + $stepvl * $ii;
								$json[$pos][1] = $vl;
							}
						}

						//add cutoff & limit lines
						foreach ($json as $k => $vl) {

							foreach ($test_cutoffs as $cut) {
								if ($k == 0) {
									$json[$k][] = 'Cutoff';
								} else {
									$json[$k][] = floatval($cut);
								}
							}
							foreach ($test_limits as $cut) {
								if ($k == 0) {
									$json[$k][] = 'Limit';
								} else {
									$json[$k][] = floatval($cut);
								}
							}
						}



						$cols = ob_get_clean();
						?>
						<tr class="childp">
							<td><a href="#" class="show_chart_trends"
									data-json="<?php echo esc_attr(json_encode($json)) ?>"><?php echo $drug ?></a>
							</td>
							<?php echo $cols ?>
						</tr>
				<?php
					}
				} else {
					echo '<tr><td><h4>No Results Found!</h4></td></tr>';
				}
				?>
			</tbody>
		</table>
	</div>

<?php
}
