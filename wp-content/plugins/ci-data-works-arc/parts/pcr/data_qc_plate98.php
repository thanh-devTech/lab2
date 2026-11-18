<?php
global $wpdb;
include_once(TRCPDD_PATH . 'inc/functions_rack_plate.php');
$bygroup 		= false;
$main_group_id  = 0;
$all_tests		= array();
$ctab 			= isset($_GET['ctab']) ? $_GET['ctab'] : '';
$covid_tests = trpdd_get_covid_tests('IC');
$tests = array(
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
	$bygroup 	= true;
	$main_group_id = str_ireplace('g_', '', $ctab);
	$rs = $wpdb->get_results("select te.* from ci_test_panels te
	JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
	where tg_ID = {$main_group_id} or tg_parent = {$main_group_id} ", ARRAY_A);

	foreach ($rs as $r) {
		$r['te_name'] = strtoupper($r['te_name']);
		$all_tests[$r['te_name']] = $r;
		if (!empty($r['te_abrev'])) {
			$r['te_abrev'] = strtoupper($r['te_abrev']);
			$all_tests[$r['te_abrev']] = $r;
		}
	}
}

$qc_barcodes     	= array();
if (tr_get_option('ci_result_settings_pcr', 'e_qc')) {
	$basecode = tr_get_option('ci_pdf_data', 'barcode_key');
	$options = get_option('tr_pcr_qc_st', array());
	for ($i = 1001; $i <= 1033; $i++) {
		$qcbarcode = $basecode . '0000' . $i;
		if (isset($options[$qcbarcode]) && !empty($options[$qcbarcode])) {
			$qc_barcodes[$qcbarcode] = $options[$qcbarcode];
		}
	}
}


$drugs = implode("','", $tests);

$filename 	= isset($_REQUEST['filename']) ? $_REQUEST['filename'] : '';
$ids 		= explode(',', $_REQUEST['ids']);
$pl_ID 		= $_REQUEST['pl_id'];
$filedata 	= array();
$where 		= '';

if (!empty($filename)) {
	$filepath 	= WP_CONTENT_DIR . '/log/results_pcr/' . $filename;
	if (file_exists($filepath)) {
		$rsf 	= trpdd_get_data($filepath, null, null, true);

		foreach ($rsf['data'] as $r) {
			$assen 	= trpdd_get_string_exist($r, array('sample', 'sample name', 'barcode', 'accession'));

			if (isset($qc_barcodes[$assen])) {
				$assen = $qc_barcodes[$assen];
				if (stripos($assen, 'pos') !== false) {
					$assen = 'pos';
				} else if (stripos($assen, 'neg') !== false) {
					$assen = 'neg';
				}
			} else if (stripos($assen, 'pos') === 0) {
				$assen = 'pos';
			} else if (stripos($assen, 'neg') === 0) {
				$assen = 'neg';
			}


			$Fluor 	= trpdd_get_string_exist($r, array('fluor'));
			$well 	= trpdd_get_string_exist($r, array('well'));
			$target = trpdd_get_string_exist($r, array('target'));
			$conc 	= trpdd_get_string_exist($r, array('cq', 'ct', 'target name'));
			$Fluor 	= strtolower($Fluor);
			if (stripos($Fluor, 'Texas') !== false) {
				$Fluor = 'texas';
			}
			$target	= strtoupper($target);
			$testdb = isset($all_tests[$target]) ? $all_tests[$target] : array();
			$testdb['drug'] = $target;
			$testdb['conc'] = $conc;
			$testdb['pos-neg'] = $pos_neg;
			$testdb['cutoff'] = 35;

			$testdb = tr_pdd_parse_pcr_test($testdb, '', 0);

			if (!isset($filedata[strtoupper($assen) . '-' . $Fluor])) {
				$filedata[strtoupper($assen) . '-' . $Fluor] = array();
			}
			$filedata[strtoupper($assen) . '-' . $Fluor][] = $testdb;
		}
	}
}


if ($pl_ID > 0) {
	$where = "  pl.pl_ID = {$pl_ID} ";
} else if (!empty($_REQUEST['ids'])) {
	$sql_more = "select re.id from ci_reports_pcr re
                                join ci_batches_pcr ba ON ba.assen = re.patient_id 
                                join ci_results_pcr rs On rs.report_id = re.id and rs.drug = '{$covid_tests[0]}'
                                where FIND_IN_SET('{$filename}',re.upload_files)  ";
	$more_ids = $wpdb->get_col($sql_more);

	$ids = array_merge($ids, $more_ids);
	$ids = implode(',', $ids);
	$where .= "  re.id in ({$ids}) ";
} else if (!empty($filename)) {
	$fileobj = $wpdb->get_row("select * from ci_reports_files where rf_file = '{$filename}' and rf_btype='pcr' ");

	$assens = explode(',', $fileobj->rf_assen_list);
	$assens_str = implode("','", $assens);
	$where .= "  ba.assen in ('{$assens_str}') ";
} else if (!empty($batch_id)) {
	$where .= "  ba.batch_id = '{$batch_id}' ";
}


$sql = "select 
		ba.mid,
		ba.assen,
		ba.doc,
		re.id,
		re.instrument,
		re.created_date,
		re.created,
		pl.pl_ID,
		rb.position,
		ra.re_is_uti_panel
		
    from ci_batches_pcr ba
    join ci_reports_pcr re ON re.patient_id = ba.assen
    left JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='pcr'
    left JOIN ci_plates pl ON pl.pl_ID = rb.pl_ID
    left JOIN ci_racks ra ON pl.pl_rack_ID = ra.re_ID
    where {$where}  
    group by ba.mid
    ";

$rows = $wpdb->get_results($sql, ARRAY_A);

if (empty($rows)) {
	echo 'Not found';
	echo $wpdb->last_error;
	return;
}


$default_arr = trpdd_get_pcr_covid_test();
$default_arr['drug'] = ($default_arr['te_name']);
$default_arr['list'] = array();

$plates = array();
foreach ($rows as $row) {
	$row['result'] = array();
	$row['has_covid'] = false;
	$arr_report = $default_arr;

	if (empty($row['created_date'])) {
		$row['created_date'] = $row['created'];
	}

	if (!isset($plates[$row['pl_ID']])) {
		$platetype = 96;
		$items = array();

		$config_plate = trpdd_plate_map_config($row['re_is_uti_panel']);

		if (is_array($config_plate) && $config_plate['type'] == 384) {

			$items = trpdd_get_assens_plate($row);
			foreach ($items as $pos => $aarr) {
				$items[$pos] = $aarr;
			}
			$platetype = 384;
		}
		$plates[$row['pl_ID']] = array(
			'name' => '',
			'type' => $platetype,
			'items' => $items,
			'config_plate' => $config_plate
		);

		//check pos/neg
		$itestnums = array('POS' => 0, 'NEG' => 0);
		foreach ($plates[$row['pl_ID']]['items'] as $pos => $ass) {

			$asnum = $ass['assen'];
			$posneg = stripos($asnum, 'pos') === 0 || stripos($asnum, 'PC') === 0 ? 'POS' : '';
			if (stripos($asnum, 'neg') === 0 || stripos($asnum, 'NC') === 0) {
				$posneg = 'NEG';
			}
			if (in_array($posneg, array('POS', 'NEG'))) {

				$ass['result'] = array();
				foreach ($tests as $tk => $tvl) {
					$ass['result'][$tk] = $filedata[$posneg . '-' . $tk][$itestnums[$posneg]];
				}
				$itestnums[$posneg]++;

				$plates[$row['pl_ID']]['items'][$pos] = $ass;
			}
		}
	}


	if ($plates[$row['pl_ID']]['type'] == '384') {
		$itestnum = 0;
		foreach ($plates[$row['pl_ID']]['items'] as $pos => $ass) {
			if ($ass['assen'] == $row['assen']) {
				$row['result'] = array();
				//echo $row['assen'];
				foreach ($tests as $tk => $tvl) {
					$row['result'][$tk] = $filedata[$row['assen'] . '-' . $tk][$itestnum];
					//var_dump($row['result'][$tk]['pos-neg']);echo '<br>';
				}
				//echo '<br><br>';
				$itestnum++;

				$plates[$row['pl_ID']]['items'][$pos] = $row;
			}
		}
	} else {

		$rs = $wpdb->get_results(
			"select * from ci_results_pcr 
					where report_id = {$row['id']} 
					and drug in ('{$drugs}')",
			ARRAY_A
		);


		foreach ($rs as $arr) {
			$drug = strtoupper($arr['drug']);
			$arr_report['list'][$drug] = $arr;

			if (in_array($drug, $covid_tests)) {
				$row['has_covid'] = true;
			}

			$row['result'][$drug] = $arr;
		}
		if ($row['has_covid']) {
			$arr_report = trpdd_cal_ncovid19($arr_report, $row['id'], $row['instrument'], $row['created_date']);

			foreach ($row['result'] as $drug => $arr) {
				$row['result'][$drug]['cutoff'] = $arr_report['cutoff'];
				$row['result'][$drug]['pos-neg'] = $arr_report[$drug];
			}
		}


		$plates[$row['pl_ID']]['items'][$row['position']] = $row;
	}
}

?>
<div class="data_qc_plate" id="plate_<?php echo $time ?>">
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
	foreach ($plates as $plate) {

		$maxcol  = 12;
		$maxrow = 8;
		if ($plate['type'] == 384) {
			$maxcol = 24;
			$maxrow = 16;
		}
	?>
		<div class="plateblock">
			<div class="ccontent">
				<table class="platetbl98 platetblsmall">
					<thead>
						<tr>
							<?php
							echo '<th></th>';
							for ($c = 1; $c <= $maxcol; $c++) {
								echo '<th>' . $c . '</th>';
							}
							?>
						</tr>
					</thead>
					<?php
					$t_pos = 0;
					for ($r = 1; $r <= $maxrow; $r++) {
						$char = chr($r + 832);
						echo '<tr>';
						echo '<td class="char">' . $char . '</td>';
						for ($c = 1; $c <= $maxcol; $c++) {
							if ($plate['type'] == 384) {
								$t_pos++;
							} else {
								$t_pos = trpdd_well_to_pos($char . $c);
							}

							$color = '';
							$o_color = '';
							$assen = '';
							$titleo = array();
							$atts = array();
							if (isset($plate['items'][$t_pos])) {
								$item = $plate['items'][$t_pos];
								$assen = $item['assen'];

								foreach ($tests as $t => $txt) {
									$cutoff = $item['result'][$t]['cutoff'] > 0 ? $item['result'][$t]['cutoff'] : 34;
									$rota = floatval($item['result'][$t]['conc']) / $cutoff;
									if ($rota > 1) $rota = 1;
									if ($t == strtoupper($covid_tests[1]) && $item['result'][$t]['conc'] == 'NaN') {
										$rota = 1;
									}
									$color = trpdd_pick_hex('#ff4747', '#b0f05c', $rota);


									if ($t == strtoupper($covid_tests[0]) || $t == 'fam') {
										if (in_array($item['result'][$t]['pos-neg'], array('POS', 'POH', 'POL', 'POM'))) {
											$o_color = 'red';
										} else if ($item['result'][$t]['pos-neg'] == 'NEG') {
											$o_color = 'green';
											$color = 'green';
										}
									}
									$atts[$t] = $color;
									$titleo[] = $txt . ': ' . $item['result'][$t]['conc'];
								}
							}
							$atts = json_encode($atts);
							$titleo = implode(' | ', $titleo);

							echo '<td title="' . $t_pos . '" data-colors="' . esc_attr($atts) . '" !important">';
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
	<?php
	}
	?>
</div>
<script>
	jQuery('#plate_<?php echo $time ?> select.test_filter').change();
</script>