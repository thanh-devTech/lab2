<?php
$btype = 'pcr';

if ($patient_id > 0 && !empty($from_date)) {


	//$wpdb->query("SET SESSION group_concat_max_len = 1000000;");
	$where = " ba.patient_id  = '{$patient_id}' 
               and ba.doc >= '{$from_date}' 
               and ba.doc <= '{$to_date} 23:59:59' ";

	if ($current_role == 'doctor') {
		$doc_id = get_user_meta($user_id, '_doc_id', true);
		$where .= " and ba.doc_ID = {$doc_id}";
	} else if ($current_role == 'clinic') {
		$cli_id = get_user_meta($user_id, '_clinic_id', true);
		$where .= " and ba.cli_ID = {$cli_id}";
	} else if ($current_role == 'office_manager' || $current_role == 'clinic') {

		$cid = get_user_meta($user_id, '_clinic_id', true);
		$clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
		$clinics = implode(',', $clinics);
		$where .= " and ba.cli_ID in ({$clinics}) ";
	}
	$all_dates = $wpdb->get_results("
            select re.id, 
            re.created_date,
            re.approve_date,
            re.instrument,
            date_format(ba.doc,'%m-%d-%y') AS date_month,
            ba.assen,
			ba.panel_tests_requested
            from ci_reports_pcr re
            JOIN ci_batches_pcr ba ON ba.assen = re.patient_id
            where {$where} order by ba.doc asc");
	$tests = array();
	foreach ($all_dates as $i => $d) {


		$tests_ids = array();
		$groups_ids = array();
		foreach (explode(',', $d->panel_tests_requested) as $tid) {
			if (stripos($tid, 'GROUP_') !== false) {
				$groups_ids[] = str_ireplace('GROUP_', '', $tid);
			} else {
				$tests_ids[] = $tid;
			}
		}

		$test_ids_str = implode(',', $tests_ids);
		$group_ids_str = implode(',', $groups_ids);
		if (empty($test_ids_str)) $test_ids_str = -1;
		if (empty($group_ids_str)) $group_ids_str = -1;

		$all_res = $wpdb->get_results("SELECT * 
		FROM ci_results_pcr rs
		JOIN ci_test_panels te ON (te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug)
		WHERE report_id='" . $d->id . "'
		and te.te_is_control = 0
		and (te.te_ID in ({$test_ids_str}) or te.te_group_ID in ({$group_ids_str}))
		group by rs.id ", ARRAY_A);


		foreach ($all_res as $arr) {
			$arr['drug'] = strtoupper($arr['drug']);
			$arr['type_of_specimen'] = $r->type_of_specimen;
			$arr = tr_pdd_parse_pcr_test($arr, $d->instrument, $arr['te_group_id'], $d->created_date);
			if ($arr['exclude']) {
				continue;
			}
			if (!isset($tests[$arr['drug']])) {
				$tests[$arr['drug']] = array();
			}

			$tests[$arr['drug']][$d->id] = $arr;
		}
	}

	if (isset($_REQUEST['trajax']) && stripos($_REQUEST['trajax'], 'export') !== false) {
		ob_clean();
		$filename = 'export.csv';
		header("Content-disposition: attachment; filename={$filename}");
		header("Content-Type: text/csv");

		$f 		= fopen('php://output', 'w');
		$headers = array('Test');
		$barcode_row = array('Barcode');
		$pnotes_row = array('Physician Notes');
		$reportnotes_row = array('Report Notes');
		foreach ($all_dates as $d) {
			$headers[] = $d->date_month;
			$barcode_row[] = $d->assen;
			$pnotes_row[] = $d->notes;
			$reportnotes_row[] = $d->notes_report;
		}
		fputcsv($f, $headers);
		fputcsv($f, $barcode_row);

		foreach ($tests as $drug => $data) {
			$row = array(
				$drug,
			);
			foreach ($all_dates as $d) {
				$row[] = $data[$d->id]['conc'];
			}
			fputcsv($f, $row);
		}
		fputcsv($f, $pnotes_row);
		fputcsv($f, $reportnotes_row);

		exit;
	}

?>
	<div class="wraptbl">
		<table class="wp-list-table widefat striped posts blue_tbl hover_tbl border_tbl">
			<thead>
				<tr>
					<th>Test</th>
					<?php

					foreach ($all_dates as $i => $d) {
					?>
						<th class="center" title="<?php echo $d->id ?>"><?php echo $d->date_month ?></th>
					<?php
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
							<td class="center"><?php echo $d->assen ?></td>
						<?php
						}
						?>
					</tr>

					<?php
					foreach ($tests as $drug => $data) {
						ob_start();
						$json 		= array(array('Date', 'Cons', 'Cons', 'Cons'));
						$novalue 	= array();
						$inovalue 	= 0;
						$test_cutoffs = array();
						$test_limits = array();

						foreach ($all_dates as $i => $d) {
							$arr 	= $data[$d->id];


							if ($arr['te_is_detected'] > 0) {
								$arr['conc'] = in_array($arr['pos-neg'], array('POL', 'POM', 'POH', 'PVH', 'PVL')) ? 100 : 0;
							}

							if ($arr['conc'] !== '') {
								$conc = str_replace(',', '', $arr['conc']);
								if ($arr['pos-neg'] == 'POH') {
									$conc = $arr['low'];
									if (!in_array($arr['low'], $test_limits) && $arr['low'] > 0) {
										$test_limits[] = $arr['low'];
									}
								}


								$json[$i + 1] = array($d->date_month, floatval($conc), floatval($conc), floatval($conc));


								if (!isset($novalue[$inovalue]['last']) && isset($novalue[$inovalue]['first']))
									$novalue[$inovalue]['last'] = floatval($conc);
								$inovalue++;
							} else {
								$json[$i + 1] = array($d->date_month, 0, null, null);

								$novalue[$inovalue]['vl'][] = $i + 1;
								if (!isset($novalue[$inovalue]['first']))
									$novalue[$inovalue]['first'] = 0;
							}

							if (in_array($arr['pos-neg'], array('POL', 'POM', 'POH', 'PVH', 'PVL'))) {
								$arr['pos-neg'] = 'POS';
							}

					?>
							<td class="center is_pos_<?php echo $arr['pos-neg'] ?>"><?php echo ($arr['te_is_detected'] > 0) ? $arr['flag'] : $arr['conc'] ?></td>
						<?php
						}

						foreach ($novalue as  $novl) {
							if (count($novl) == 0) continue;
							$min = min($novl['first'], $novl['last']);
							$max = max($novl['last'], $novl['first']);
							if ($min == 0) $min = $max;
							$stepvl = ($max - $min) / (count($novl['vl']) + 1);


							foreach ($novl['vl'] as $j => $pos) {
								$ii = ($novl['first'] < $novl['last']) ? $j + 1 : count($novl['vl']) - $j;
								$vl = $min + $stepvl * $ii;
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
							<td><a href="#" class="show_chart_trends" data-json="<?php echo esc_attr(json_encode($json)) ?>"><?php echo $drug ?></a>
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
