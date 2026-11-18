<?php
global $wpdb;

$bygroup 		= false;
$ctab 			= isset($_GET['ctab']) ? $_GET['ctab'] : '';
$covid_tests 	= trpdd_get_covid_tests('IC');
$tests 			= array(
	strtoupper($covid_tests[0]) => $covid_tests[0],
	strtoupper($covid_tests[1]) => $covid_tests[1],
);

if (!empty($ctab)) {
	$tests = array(
		'fam' => 'FAM',
		'cy5' => 'Cy5',
		'hex' => 'HEX',
		'texas' => 'Texas'
	);
	$bygroup = true;
	$covid_group_id = str_ireplace('g_', '', $ctab);
} else {
	$covid_group = $wpdb->get_row("select * from ci_test_groups where tg_is_covid=1 and tg_type='pcr' ");

	if (!$covid_group) {
		$covid_group = $wpdb->get_row("select * from ci_test_groups where tg_name like '%ncov%' and tg_type='pcr' ");
	}
	$covid_group_id = $covid_group->tg_ID;
}
$drugs = implode("','", $tests);

$filename = isset($_REQUEST['filename']) ? $_REQUEST['filename'] : '';
$ids = explode(',', $_REQUEST['ids']);
$batch_id = isset($_REQUEST['batch_id']) ? $_REQUEST['batch_id'] : '';

$where = " 1 ";
if (!empty($_REQUEST['ids'])) {
	$sql_more = "select re.id from ci_reports_pcr re
                                join ci_batches_pcr ba ON ba.assen = re.patient_id 
                                join ci_results_pcr rs On rs.report_id = re.id and rs.drug = '{$covid_tests[0]}'
                                where FIND_IN_SET('{$filename}',re.upload_files)  ";
	$more_ids = $wpdb->get_col($sql_more);

	$ids = array_merge($ids, $more_ids);
	$ids = implode(',', $ids);
	$where .= " and re.id in ({$ids}) ";
} else if (!empty($filename)) {
	$fileobj = $wpdb->get_row("select * from ci_reports_files where rf_file = '{$filename}' and rf_btype='pcr' ");

	$assens = explode(',', $fileobj->rf_assen_list);
	$assens_str = implode("','", $assens);
	$where .= " and ba.assen in ('{$assens_str}') ";
} else if (!empty($batch_id)) {
	$where .= " and ba.batch_id = '{$batch_id}' ";
}


$sql = "select 
		ba.mid,
		ba.assen,
		ba.doc,
		re.id,
		re.instrument,
		re.created_date,
		re.created,
		pl.pl_combine_ID,
		pl.pl_ID,
		rb.combine_position
		
    from ci_batches_pcr ba
    join ci_reports_pcr re ON re.patient_id = ba.assen
    left JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='pcr'
    left JOIN ci_plates pl ON pl.pl_ID = rb.pl_ID
    where {$where}  and pl_combine_ID>0
    group by ba.mid
    ";

$rows = $wpdb->get_results($sql);

if (empty($rows)) {

	include(trpdd_get_file_path('pcr', 'data_qc_plate98'));
	return;
}

$default_arr = trpdd_get_pcr_covid_test();
$default_arr['drug'] = ($default_arr['te_name']);
$default_arr['list'] = array();

$combines = array();
foreach ($rows as $row) {
	$row->result = array();
	$row->has_covid = false;
	$arr_report = $default_arr;

	if (empty($row->created_date)) {
		$row->created_date = $row->created;
	}


	$rs = $wpdb->get_results("select * from ci_results_pcr 
	            where report_id = {$row->id} 
	            and drug in ('{$drugs}')", ARRAY_A);


	foreach ($rs as $arr) {
		$drug = strtoupper($arr['drug']);
		$arr_report['list'][$drug] = $arr;

		if (in_array($drug, $covid_tests)) {
			$row->has_covid = true;
		}

		$row->result[$drug] = $arr;
	}

	if ($row->has_covid) {
		$arr_report = trpdd_cal_ncovid19($arr_report, $row->id, $row->instrument, $row->created_date);
		foreach ($row->result as $drug => $arr) {
			$row->result[$drug]['cutoff'] = $arr_report['cutoff'];
			$row->result[$drug]['pos-neg'] = $arr_report[$drug];
		}
	}

	if ($row->pl_combine_ID > 0) {
		if (!isset($combines[$row->pl_combine_ID])) {
			$combines[$row->pl_combine_ID] = array(
				'ID' => $row->pl_combine_ID,
				'items' => array()
			);
		}
		$combines[$row->pl_combine_ID]['items'][$row->combine_position] = $row;
	}
}



if (empty($combines)) {
	echo 'Not found';
	return;
}
$time = time();
?>
<div class="data_qc_plate" id="com_<?php echo $time ?>">
	<div class="center">
		<select class="test_filter" style="width:300px">
			<?php
			foreach ($tests as $t => $txt) {
				echo '<option value="' . $t . '">' . $txt . '</option>';
			}
			?>
		</select>
	</div>
	<?php
	foreach ($combines as $row) {
	?>
		<br>
		<div class="combinedblock " data-id="<?php echo $row['ID'] ?>">
			<div class="ccontent">
				<table class="platetbl384 platetblsmall">
					<thead>
						<tr>
							<?php
							echo '<th></th>';
							for ($c = 1; $c <= 24; $c++) {
								echo '<th>' . $c . '</th>';
							}
							?>
						</tr>
					</thead>
					<?php
					$t_pos = 0;

					for ($r = 1; $r <= 16; $r++) {
						$char = chr($r + 832);
						echo '<tr>';
						echo '<td class="char">' . $char . '</td>';
						for ($c = 1; $c <= 24; $c++) {
							$t_pos++;
							$color = '';
							$o_color = '';
							$assen = '';
							$titleo = array();
							$atts = array();
							$item = new stdClass();

							if (isset($row['items'][$t_pos])) {
								$item = $row['items'][$t_pos];
								$assen = $item->assen;

								foreach ($tests as $t => $txt) {
									$cutoff = $item->result[$t]['cutoff'] > 0 ? $item->result[$t]['cutoff'] : 34;
									$rota = floatval($item->result[$t]['conc']) / $cutoff;
									if ($rota > 1) $rota = 1;
									if ($t == strtoupper($covid_tests[1]) && $item->result[$t]['conc'] == 'NaN') {
										$rota = 1;
									}
									$color = trpdd_pick_hex('#ff4747', '#b0f05c', $rota);


									if ($t == strtoupper($covid_tests[0])) {
										if ($item->result[$t]['pos-neg'] == 'POS') {
											$o_color = 'red';
										} else if ($item->result[$t]['pos-neg'] == 'NEG') {
											$o_color = 'green';
											$color = 'green';
										}
									}

									$atts[$t] = $color;
									$titleo[] = $txt . ': ' . $item->result[$t]['conc'];
								}
							}
							$atts = json_encode($atts);
							$titleo = implode(' | ', $titleo);

							echo '<td data-colors="' . esc_attr($atts) . '" !important" data-pl_id="' . $item->pl_ID . '">';
							echo '<span class="tti">' . $char . $c . ': ' . $assen . '</span>';
							if (!empty($assen)) {
								echo '<span style="color:' . $o_color . '" title="' . $titleo . '">o</span>';
							}

							echo '</td>';
						}
						echo '</tr>';
					}

					?>
				</table>
			</div>
		</div>
		<script>
			jQuery('#com_<?php echo $time ?> select.test_filter').change();
		</script>
	<?php
	}
	?>
</div>