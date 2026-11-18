<?php
global $wpdb, $current_role, $ci_all_btypes;
$schedule_report = new CI_Report_Schedule('processed_samples', 'Processed Samples Report');
include_once(TRCPDD_PATH . 'pages/formdata.php');

$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : '';
$date_type = isset($_REQUEST['datetype']) ? $_REQUEST['datetype'] : 'received';
$month_range    = isset($_REQUEST['month_range']) ? $_REQUEST['month_range'] : 'last_0_month';
$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';
$is_ajax   = isset($_REQUEST['trajax']) && $_REQUEST['trajax'] !== 'export_report';
$trajax = $is_ajax ? ($_POST['trajax'] ?? '') : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$user_id = get_current_user_id();
$adding_tests_markers = array();
$clinics = array();
$clinicname = '';
if (empty($btype_selected) && !empty($ci_all_btypes)) {
	$btype_selected = reset($ci_all_btypes);
}

$ftimethismonth = strtotime(date_i18n('Y-m-01'));
$filter_date_select = [];
for ($i = 0; $i <= 5; $i++) {
	$filter_date_select[] = [
		'value' => 'last_' . $i . '_month',
		'text' => date_i18n('F Y', strtotime("first day of -$i month", $ftimethismonth))
	];
}
$filter_date_select[] = ['value' => 'custom', 'text' => 'Custom'];

$from_date = date_i18n('Y-m-01');
$to_date = date_i18n('Y-m-t');
$thismonthstamp = strtotime(date_i18n('Y-m-01'));
$mto_date = date_i18n('Y-m-t');
$mfrom_date = date('Y-m-01', strtotime('-12 months', $thismonthstamp));

switch ($month_range) {
	case 'last_0_month':
		$from_date = date_i18n('Y-m-01');
		$to_date = date_i18n('Y-m-t');
		break;

	case 'last_1_month':
	case 'last_2_month':
	case 'last_3_month':
	case 'last_4_month':
	case 'last_5_month':
		$offset = intval(str_replace(['last_', '_month'], '', $month_range));
		$from_date = date_i18n('Y-m-01', strtotime("first day of -$offset month", $ftimethismonth));
		$to_date = date_i18n('Y-m-t', strtotime("last day of -$offset month", $ftimethismonth));
		break;

	case 'custom_range':
	default:
		$from_date = isset($_REQUEST['from']) ? $_REQUEST['from'] : date_i18n('Y-m-01');
		$to_date = isset($_REQUEST['to']) ? $_REQUEST['to'] : date_i18n('Y-m-t');
		break;
}
$text_sel_date_rep = date_i18n('M j', strtotime($from_date)) . " - " . date_i18n('M j', strtotime($to_date));


$labname = CI_Base_Lab::getCurrentLabName();


$date_types = array('doc' => 'DOC', 'approve' => 'Approved Date', 'received' => 'Received', 'bid' => 'Batch#');


$conditions = trpdd_get_batch_conditions(0, $btype_selected);
$edit_time = $conditions['edit_time'];
$can_edit = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold = $conditions['can_hold_req'];
$where = ' is_saved = 0 ' . $conditions['where'];
$where_clinic = $conditions['where_clinic'];
if ($conditions['check_author']) {
	$where .= $return['where_author'];
}


$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic}");
foreach ($clinics_rs as $r) {
	$clinics[$r->cID] = $r;
}

$inactive_clinics = array();
$where_clinic_inactive = str_replace('cli_status = 1', 'cli_status = 0', $where_clinic);
$clinics_inactive_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic_inactive}");
foreach ($clinics_inactive_rs as $r) {
	$inactive_clinics[$r->cID] = $r;
}

if (!function_exists('trpdd_generate_random_color')) {
	function trpdd_generate_random_color(): string
	{
		return sprintf('#%06X', wp_rand(0, 0xFFFFFF));
	}
}

if (isset($_GET['debugconditions'])) {
	var_dump($conditions);
	var_dump($clinics);
	var_dump($inactive_clinics);
}

$batch_tbl = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);
$panel = array();
$submited = false;
$reports = array();
$reports_clinics = array();
$tests = array();


$all_panels = array();
$all_labs = array();

$all_tests = array();
if ($btype_selected == 'tox') {
	$rs = $wpdb->get_results("select te_group_id , te_name from ci_test_panels where te_type = '{$btype_selected}' ");
	foreach ($rs as $r) {
		$all_tests[strtoupper($r->te_name)] = $r->te_group_id;
	}
}
if ($btype_selected == 'study') {
	$rs = $wpdb->get_results("select ID as tg_ID, abrev as tg_name from ci_study_tests order by ID asc");
} else {
	$rs = $wpdb->get_results("select tg_ID, tg_name
				from ci_test_groups 
				where tg_type = '{$btype_selected}' 
				order by tg_position asc");
}


foreach ($rs as $r) {
	$all_panels[$r->tg_ID] = $r->tg_name;
}

$rs = $wpdb->get_results("select * from ci_labs");
foreach ($rs as $r) {
	$all_labs[$r->lab_ID] = $r;
}

$all_rejected_status = tr_pdd_where_batch_rejected(true);

$report_clids = array();
if ($trajax == 'loadTableData' || $trajax == 'loadData' || !empty($export_func)) {
	if (!empty($from_date)) {

		$coldate = $date_type == 'doc' ? 'ba.doc' : 're.approve_date';
		if ($date_type == 'received') {
			$coldate = 'ba.spec_received';
		}


		if ($date_type == 'bid') {
			$coldate = 'ba.batch_id';
			$where .= ' and ' . tr_pdd_build_batch_id_where($from_date, $to_date);
		} else {
			$where .= " and {$coldate} >= '{$from_date}' ";
			if (!empty($to_date)) {
				$where .= " and {$coldate} <= '{$to_date} 23:59:59' ";
			}
		}




		if ($cli_ID > 0) {
			$where .= " and ba.cli_ID = {$cli_ID} ";
		}

		$where .= " and re.estatus = 'C' ";

		$rerunfield = 're.covid_pos';
		if ($btype_selected == 'pcr') {
			$rerunfield .= ',re.rerun_count';
		}

		$sql_reports = "select 
					ba.panel_tests_requested,
					ba.cli_ID,
					ba.ref_lab_ID,
					ba.batch_status,
					re.id,
					re.estatus,
					{$rerunfield}
					from {$batch_tbl} ba
					join {$report_tbl} re ON re.patient_id = ba.assen
					where {$where}";
		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
			$cache = new Tr_Cache_Query('processample');
			$rows = $cache->get_results($sql_reports, OBJECT, 3600 * 2);
		} else {
			$rows = $wpdb->get_results($sql_reports);
		}

		if (isset($_GET['debug'])) {
			var_dump($sql_reports);
		}

		foreach ($rows as $row) {
			$groupsids = array();
			foreach (explode(',', $row->panel_tests_requested) as $t) {
				if (stripos($t, 'GROUP_') !== false) {
					$gid = str_replace('GROUP_', '', $t);
					if (!in_array($gid, $groupsids)) {
						$groupsids[] = $gid;
					}
				} else if ($btype_selected == 'tox' && isset($all_tests[strtoupper($t)])) {
					$gid = $all_tests[strtoupper($t)];

					if (!in_array($gid, $groupsids)) {
						$groupsids[] = $gid;
					}
				} else if ($btype_selected == 'study') {
					if (!in_array($t, $groupsids)) {
						$groupsids[] = $t;
					}
				}
			}


			foreach ($groupsids as $panelid) {

				$gid = $panelid;
				if ($cli_ID == -1) {
					$gid .= '_' . $row->cli_ID;
					$report_clids[$row->cli_ID] = $row->cli_ID;
				}
				if (!isset($reports[$gid])) {
					$reports[$gid] = array(
						'panel' => $all_panels[$panelid],
						'processed' => 0,
						'main' => 0,
						'ref' => 0,
						'refs' => array(),
						'rejected' => 0,
						'rerun' => 0,
					);
				}

				$reports[$gid]['processed']++;

				if ($row->ref_lab_ID > 0) {

					$reports[$gid]['ref']++;
					if (!isset($reports[$gid]['refs'][$row->ref_lab_ID])) {
						$reports[$gid]['refs'][$row->ref_lab_ID] = 0;
					}
					$reports[$gid]['refs'][$row->ref_lab_ID]++;
				} else {
					$reports[$gid]['main']++;
				}

				if (isset($all_rejected_status[$row->batch_status])) {
					$reports[$gid]['rejected']++;
					continue;
				}


				if (isset($row->rerun_count) && $row->rerun_count > 0) {
					$reports[$gid]['rerun']++;
				} else if (isset($row->covid_pos) && $row->covid_pos == -2) {
					$reports[$gid]['rerun']++;
				}
			}
		}
	}

	ksort($reports);
}

if ($trajax == 'loadChartData' || $trajax == 'loadData' || !empty($export_func)) {

	if ($btype_selected == 'study') {
		$rs = $wpdb->get_results("select ID as tg_ID, abrev as tg_name from ci_study_tests order by ID asc");
		$groups_mapping = array();
		foreach ($rs as $g) {
			$group_color = trpdd_generate_random_color();
			$groups_mapping[$g->tg_ID] = [
				'name' => $g->tg_name,
				'short_name' => $g->tg_name,
				'group_color' => $group_color,
			];
		}
		$main_groups = $rs;
	} else {
		$sql_groups = "
		select tg_ID, tg_name, tg_cpt_code, tg_label_name, tg_short_name, tg_bgcolor
		from ci_test_groups
		where tg_parent = 0 and tg_type = '{$btype_selected}' and tg_show_req = 1
		order by tg_position asc
		";
		$groups_mapping = array();
		$main_groups = $wpdb->get_results($sql_groups);

		foreach ($main_groups as $g) {
			$short_name = $g->tg_cpt_code
				?: $g->tg_label_name
				?: $g->tg_short_name
				?: $g->tg_name;
			$group_color = !empty($g->tg_bgcolor) ? $g->tg_bgcolor : trpdd_generate_random_color();
			$groups_mapping[$g->tg_ID] =
				[
					'name' => str_replace('(new)', '', $g->tg_name),
					'short_name' => $short_name,
					'group_color' => $group_color,
				];
		}
	}

	$conditions = trpdd_get_batch_conditions(0, $btype_selected);
	$fieldpositive = 're.has_pos > 0';
	$rejected_condition = tr_pdd_where_batch_rejected(false, false, '');
	$chart_line_data = [];
	$chart_volume_data = [
		[
			'label' => "Processed In-House",
			'data' => [],
			'backgroundColor' => "#10b981"
		],
		[
			'label' => "Referenced",
			'data' => [],
			'backgroundColor' => "#8b5cf6"
		],
		[
			'label' => "Rejected",
			'data' => [],
			'backgroundColor' => "#f59e0b"
		]
	];
	$volume_data = [];
	$total_rejected_12m = 0;
	$total_volume_12m = 0;

	$month_index = [];
	$months = [];

	$idx = 0;
	for ($i = 12; $i >= 0; $i--) {

		$key = date('Y-m', strtotime("-{$i} months", $thismonthstamp));

		$month_index[$key] = $idx;

		$months[] = [
			'label' => date('M', strtotime("-{$i} months", $thismonthstamp))
		];

		$idx++;
	}
	for ($k = 0; $k < 3; $k++) {
		if (empty($chart_volume_data[$k]['data'])) {
			$chart_volume_data[$k]['data'] = array_fill(0, 13, 0);
		}
	}

	$volume_rowmap = [];
	if ($btype_selected == 'tox') {
		$where_condition_vol = 'ba.is_saved = 0 ' . $conditions['where'];
		$sql_volume_tox = "
			SELECT 
				" . trpdd_abx_query_field_date('%Y-%m') . " AS month_label,
				COUNT(DISTINCT ba.assen) AS total,
				COUNT(DISTINCT CASE WHEN ba.ref_lab_ID > 0 THEN ba.assen END) AS count_ref,
				COUNT(DISTINCT CASE WHEN {$rejected_condition} THEN ba.assen END) AS count_rejected
			FROM {$batch_tbl} ba
			JOIN {$report_tbl} re ON re.patient_id = ba.assen
			WHERE " . trpdd_abx_query_date_range($mfrom_date, $mto_date) . "
				AND ({$where_condition_vol})
			GROUP BY month_label
			ORDER BY month_label ASC";
		$volume_rows = $wpdb->get_results($sql_volume_tox);
		foreach ($volume_rows as $r) {
			$volume_rowmap[$r->month_label] = $r;
		}
	}

	foreach ($groups_mapping as $gid => $g) {
		if ($btype_selected == 'tox') {
			$tests = $wpdb->get_col(
				"select te_name from ci_test_panels 
				where te_type = '{$btype_selected}'
				and te_group_id = {$gid}"
			);
			$where_tests = [];

			foreach ($tests as $test) {
				$test_name = strtoupper($test);
				$where_tests[] = "FIND_IN_SET('{$test_name}',panel_tests_requested)";
			}
			$where_condition = implode(' OR ', $where_tests);
		} else if ($btype_selected == 'study') {
			$where_condition = "FIND_IN_SET('{$gid}',panel_tests_requested)";
		} else {
			$where_condition = "FIND_IN_SET('GROUP_{$gid}',panel_tests_requested)";
		}
		$where_condition .= ' AND ba.is_saved = 0 ' . $conditions['where'];

		$sql_monthly_line = "
			SELECT 
				" . trpdd_abx_query_field_date('%Y-%m') . " AS month_label,
				COUNT(*) AS total,
				SUM(IF(re.estatus='C' AND ({$fieldpositive}), 1, 0)) AS total_positive,
				COUNT(CASE WHEN {$rejected_condition} THEN ba.assen END) AS count_rejected,
				COUNT(CASE WHEN ba.ref_lab_ID > 0 THEN ba.assen END) AS count_ref
			FROM {$batch_tbl} ba
			JOIN {$report_tbl} re ON re.patient_id = ba.assen
			WHERE " . trpdd_abx_query_date_range($mfrom_date, $mto_date) . "
				AND ({$where_condition})
			GROUP BY month_label
			ORDER BY month_label ASC";
		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
			$cache = new Tr_Cache_Query('processample');
			$rows = $cache->get_results($sql_monthly_line, OBJECT, 3600 * 2);
		} else {
			$rows = $wpdb->get_results($sql_monthly_line);
		}

		if (stripos($_SERVER['HTTP_REFERER'] ?? '', 'debug4') !== false) {
			var_dump($wpdb->last_error);
			var_dump($sql_monthly_line);
		}
		$rowmap = [];
		foreach ($rows as $r) {
			$rowmap[$r->month_label] = $r;
		}

		$dataset_values = array_fill(0, 13, 0);

		foreach ($month_index as $month_key => $idx) {

			if (!isset($rowmap[$month_key])) {
				continue;
			}

			$r = $rowmap[$month_key];

			$dataset_values[$idx] = (int)$r->total;

			if ($btype_selected !== 'tox') {
				$chart_volume_data[0]['data'][$idx] += ($r->total - $r->count_ref);
				$chart_volume_data[1]['data'][$idx] += $r->count_ref;
				$chart_volume_data[2]['data'][$idx] += $r->count_rejected;

				$isCurrentMonth = ($month_key === date('Y-m', $thismonthstamp));
				if ($r->total > 0 && !$isCurrentMonth) {
					$volume_data[$month_key] = ($volume_data[$month_key] ?? 0) + $r->total;
					$total_volume_12m += $r->total;
				}
				if (!$isCurrentMonth) {
					$total_rejected_12m += $r->count_rejected;
				}
			}
		}
		$group_color = !empty($g['group_color']) ? $g['group_color'] : trpdd_generate_random_color();
		$bk_color = $group_color . '30';

		$chart_line_data[] = [
			'label' => $g['short_name'] ?? 'Group ' . $gid,
			'full_name' => $g['name'] ?? $g['short_name'] ?? 'Group ' . $gid,
			'data' => $dataset_values,
			'fill' => false,
			'tension' => 0.3,
			'borderColor' => $group_color,
			'backgroundColor' => $group_color,
			'bk_color' => $bk_color
		];
	}
	if ($btype_selected == 'tox') {
		$total_volume_12m   = 0;
		$total_rejected_12m = 0;
		$volume_data        = [];
		foreach ($month_index as $month_key => $idx) {
			if (!isset($volume_rowmap[$month_key])) continue;
			$vr = $volume_rowmap[$month_key];
			$chart_volume_data[0]['data'][$idx] = (int)($vr->total - $vr->count_ref);
			$chart_volume_data[1]['data'][$idx] = (int)$vr->count_ref;
			$chart_volume_data[2]['data'][$idx] = (int)$vr->count_rejected;

			$isCurrentMonth = ($month_key === date('Y-m', $thismonthstamp));
			if ($vr->total > 0 && !$isCurrentMonth) {
				$volume_data[$month_key] = (int)$vr->total;
				$total_volume_12m       += $vr->total;
			}
			if (!$isCurrentMonth) {
				$total_rejected_12m += $vr->count_rejected;
			}
		}
	}

	$months = [];
	for ($i = 12; $i >= 0; $i--) {
		$months[] = [
			'label' => date('M', strtotime("-{$i} months", $thismonthstamp))
		];
	}
	foreach ($chart_volume_data as &$ds) {
		$ds['data'] = array_values($ds['data']);
	}
	unset($ds);

	foreach ($chart_line_data as &$ds) {
		$ds['data'] = array_values($ds['data']);
	}
	unset($ds);

	$chartDataLine = [
		'labels' => array_column($months, 'label'),
		'datasets' => $chart_line_data
	];
	$chartVolumeData = [
		'labels' => array_column($months, 'label'),
		'datasets' => $chart_volume_data
	];
	$avg_volume = $total_volume_12m > 0 ? round($total_volume_12m / 12) : 0;
	$avg_rejected_rate = $total_volume_12m > 0 ? round(($total_rejected_12m / $total_volume_12m) * 100, 2) : 0;
	$months_has_data = count($volume_data);
	$r2 = 0;
	if ($months_has_data > 0) {
		$forecast = 0;

		$sumX = 0;
		$sumY = 0;
		$sumXY = 0;
		$sumX2 = 0;
		$index_m = 1;
		foreach ($volume_data as $y) {
			$sumX += $index_m;
			$sumY += $y;
			$sumXY += $index_m * $y;
			$sumX2 += $index_m * $index_m;
			$index_m++;
		}

		$slope = ($months_has_data * $sumXY - $sumX * $sumY) / ($months_has_data * $sumX2 - $sumX * $sumX);

		$avgX = $sumX / $months_has_data;
		$avgY = $sumY / $months_has_data;

		$intercept = $avgY - $slope * $avgX;

		$forecast = $intercept + $slope * ($months_has_data + 1);

		$ssTot = 0;
		$ssRes = 0;

		$index_m = 1;
		foreach ($volume_data as $y) {

			$yPred = $intercept + $slope * $index_m;

			$ssTot += pow($y - $avgY, 2);
			$ssRes += pow($y - $yPred, 2);

			$index_m++;
		}

		$r2 = round((($ssTot == 0) ? 1 : 1 - ($ssRes / $ssTot)) * 100, 1);
	}
}

if ($btype_selected == 'tox') {
	$rejected_cond_tbl = tr_pdd_where_batch_rejected(false, false, '');
	$sql_total_tox = "SELECT 
        COUNT(DISTINCT ba.assen) AS total_processed,
        COUNT(DISTINCT CASE WHEN ba.ref_lab_ID = 0 THEN ba.assen END) AS total_main,
        COUNT(DISTINCT CASE WHEN {$rejected_cond_tbl} THEN ba.assen END) AS total_rejected
        FROM {$batch_tbl} ba
        JOIN {$report_tbl} re ON re.patient_id = ba.assen
        WHERE {$where}";
	$total_row = $wpdb->get_row($sql_total_tox);
	$total_top = [
		'processed'        => (int)$total_row->total_processed,
		'main'             => (int)$total_row->total_main,
		'rejected'         => (int)$total_row->total_rejected,
		'rejected_percent' => $total_row->total_processed > 0
			? number_format($total_row->total_rejected / $total_row->total_processed * 100, 2) . '%'
			: '0%'
	];
} else {
	$total_top = ['processed' => 0, 'main' => 0, 'rejected' => 0, 'rejected_percent' => '0%'];
	foreach ($reports as $row) {
		$total_top['processed'] += $row['processed'];
		$total_top['main']      += $row['main'];
		$total_top['rejected']  += $row['rejected'];
	}
	$total_top['rejected_percent'] = $total_top['processed'] > 0
		? number_format($total_top['rejected'] / $total_top['processed'] * 100, 2) . '%'
		: '0%';
}

if ($trajax == 'loadTableData') {


	echo json_encode([
		'reports'      => $reports,
		'total_top'    => $total_top,
		'report_clids' => $report_clids,
		'all_labs'     => $all_labs,
		'text_sel_date_rep' => $text_sel_date_rep,
	]);
	exit;
}
$months_12_text = date('M Y', strtotime($mfrom_date)) . ' - ' . date('M Y', strtotime($mto_date));
if ($trajax == 'loadChartData') {
	echo json_encode([
		'chartDataLine'   => $chartDataLine,
		'chartVolumeData' => $chartVolumeData,
		'avg_volume'      => $avg_volume,
		'avg_rejected_rate' => $avg_rejected_rate,
		'r2'              => $r2,
		'months_12_text' => $months_12_text,
	]);
	exit;
}

$schedule_report->ajax();

if ($export_func != "") {

	// ── Export: 12-Month Volume Trend

	if ($export_func == "export_volume_trend") {
		$generated_on = date_i18n("m/d/Y \\a\\t h:i A");
		$filename_base = generate_report_filename('RPT', 'VolumeTrend', date_i18n('Y-m-d'), "", $export_type);

		if ($export_type == 'csv') {
			if ($filepath_save_csv == '') {
				trpdd_log_user(get_current_user_id(), 'download_report', '12-Month Volume Trend CSV', '', '', '', '', 'Report_' . date_i18n('mdY'));
			}
			tr_pdd_header('text/csv', $filename_base);
			$f = $filepath_save_csv != '' ? fopen($filepath_save_csv, 'w') : fopen('php://output', 'w');

			fputcsv($f, ["12-Month Volume Trend", "(" . $months_12_text . ")"]);
			fputcsv($f, ["Generated on: $generated_on"]);
			fputcsv($f, [""]);
			fputcsv($f, ["Month", "Processed In-House", "Referenced", "Rejected", 'Rejected %']);

			foreach ($chartVolumeData['labels'] as $i => $label) {
				$inhouse  = $chartVolumeData['datasets'][0]['data'][$i] ?? 0;
				$ref      = $chartVolumeData['datasets'][1]['data'][$i] ?? 0;
				$rejected = $chartVolumeData['datasets'][2]['data'][$i] ?? 0;
				$total    = $inhouse + $ref + $rejected;
				$rej_pct  = $total > 0 ? number_format($rejected / $total * 100, 2) . '%' : '0.00%';
				fputcsv($f, [$label, $inhouse, $ref, $rejected, $rej_pct]);
			}

			if ($filepath_save_csv == '') {
				fclose($f);
				exit;
			}
			fclose($f);
		}

		if ($export_type == 'pdf' || $filepath_save_pdf != '') {
			if ($filepath_save_pdf == '') {
				trpdd_log_user(get_current_user_id(), 'download_report', '12-Month Volume Trend PDF', '', '', '', '', 'Report_' . date_i18n('mdY'));
			}
			ob_start();
			require TRCPDD_PATH . 'parts/reports/processed_samples_volume_trend_pdf.php';
			$html = ob_get_clean();

			if ($filepath_save_pdf != '') {
				trdomepdf_build_pdf($html, [
					'size' => 'letter',
					'orientation' => 'landscape',
					'filepath' => $filepath_save_pdf,
					'download' => 0,
					'compress' => 0,
					'dpi' => 110,
				]);
			} else {
				trpdd_build_pdf($html, [
					'size' => 'letter',
					'orientation' => 'landscape',
					'filename' => $filename_base,
					'download' => 1,
					'compress' => 1,
					'dpi' => 110,
				]);
				exit;
			}
		}
	} else if ($export_func == "export_panel_trend") {
		$generated_on = date_i18n("m/d/Y \\a\\t h:i A");
		$filename_base = generate_report_filename('RPT', 'PanelTrends', date_i18n('Y-m-d'), "", $export_type);

		if ($export_type == 'csv') {
			if ($filepath_save_csv == '') {
				trpdd_log_user(get_current_user_id(), 'download_report', 'Panel Volume Trends CSV', '', '', '', '', 'Report_' . date_i18n('mdY'));
			}
			tr_pdd_header('text/csv', $filename_base);
			$f = $filepath_save_csv != '' ? fopen($filepath_save_csv, 'w') : fopen('php://output', 'w');

			fputcsv($f, ["Panel Volume Trends"]);
			fputcsv($f, ["Generated on: $generated_on"]);
			fputcsv($f, [""]);
			fputcsv($f, array_merge(["Panel"], $chartDataLine['labels']));
			foreach ($chartDataLine['datasets'] as $ds) {
				fputcsv($f, array_merge([$ds['label']], array_values($ds['data'])));
			}

			if ($filepath_save_csv == '') {
				fclose($f);
				exit;
			}
			fclose($f);
		}

		if ($export_type == 'pdf' || $filepath_save_pdf != '') {
			if ($filepath_save_pdf == '') {
				trpdd_log_user(get_current_user_id(), 'download_report', 'Panel Volume Trends PDF', '', '', '', '', 'Report_' . date_i18n('mdY'));
			}
			ob_start();
			require TRCPDD_PATH . 'parts/reports/processed_samples_panel_trends_pdf.php';
			$html = ob_get_clean();

			if ($filepath_save_pdf != '') {
				trdomepdf_build_pdf($html, [
					'size' => 'letter',
					'orientation' => 'landscape',
					'filepath' => $filepath_save_pdf,
					'download' => 0,
					'compress' => 0,
					'dpi' => 110,
				]);
			} else {
				trpdd_build_pdf($html, [
					'size' => 'letter',
					'orientation' => 'landscape',
					'filename' => $filename_base,
					'download' => 1,
					'compress' => 1,
					'dpi' => 110,
				]);
				exit;
			}
		}
	} else {
		if ($filepath_save_csv == '') {
			trpdd_log_user(get_current_user_id(), 'download_report', 'Processed Sample Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
		}
		$generated_on = date_i18n("m/d/Y \\a\\t h:i A");
		$clinic_filter = '';
		if ($cli_ID == 0) {
			$clinic_filter = 'All Clinics';
		} else if ($cli_ID == -1) {
			$clinic_filter = 'All Clinics — Clinic Breakdown';
		} else {
			$clinic_filter = $clinics[$cli_ID]->cli_clinic;
		}

		$total_processed = 0;
		$summary_totals = [
			'processed' => 0,
			'main' => 0,
			'ref' => 0,
			'rejected' => 0,
			'rerun' => 0
		];
		foreach ($reports as $row) {
			$total_processed += $row['processed'];
			$summary_totals['processed'] += $row['processed'];
			$summary_totals['main'] += $row['main'];
			$summary_totals['ref'] += $row['ref'];
			$summary_totals['rejected'] += $row['rejected'];
			$summary_totals['rerun'] += $row['rerun'];
		}
		$summary_totals['rejected_percent'] = $total_processed > 0 ? number_format((($summary_totals['rejected']) / $total_processed) * 100, 1) . '%' : '0%';
		if ($export_type == "pdf" || $filepath_save_pdf != '') {
			$outfile = generate_report_filename('RPT', 'ProcessedSamples', date_i18n('Y-m-d'), "", 'pdf');
			$orientation = 'portrait';
			ob_start();
			require TRCPDD_PATH . 'parts/reports/processed_samples_pdf.php';
			$html = ob_get_clean();

			if ($filepath_save_pdf != '') {
				if (function_exists('trdomepdf_build_pdf')) {
					$result = trdomepdf_build_pdf($html, [
						'size' => 'letter',
						'orientation' => $orientation,
						'filepath' => $filepath_save_pdf,
						'download' => 0,
						'compress' => 0,
						'dpi' => 110,
					]);
					$pdf_out_file = basename($outfile);
				}
			} else {
				trpdd_build_pdf($html, [
					'size' => 'letter',
					'orientation' => $orientation,
					'filename' => $outfile,
					'download' => 1,
					'compress' => 1,
					'dpi' => 110,
				]);
				exit;
			}
		}
		if ($export_type == "csv" || $filepath_save_csv != '') {
			$filename = generate_report_filename('RPT', 'ProcessedSamples', date_i18n('Y-m-d'), "", 'csv');
			$headers = array('Panel', 'Total', '% of Total', 'In-House', 'Referenced', 'Rejected', 'Rejected %', 'Rerun Rate');
			tr_pdd_header('text/csv', $filename);
			if ($filepath_save_csv != '') {
				$f = fopen($filepath_save_csv, 'w');
				$pdf_out_file = basename($filename);
			} else {
				$f = fopen('php://output', 'w');
			}


			fputcsv($f, ["Processed Samples Report", "", "", "", "", "", ""]);
			fputcsv($f, ["Generated on: $generated_on"]);
			fputcsv($f, ["Report Parameters"]);
			fputcsv($f, ["Clinic:", $clinic_filter]);
			fputcsv($f, ["Count type:", isset($date_types[$date_type]) ? $date_types[$date_type] : 'Received']);
			fputcsv($f, ["Test Type:", tr_pdd_get_typename($btype_selected)]);
			fputcsv($f, ["Time Period:", $from_date . " to " .  $to_date]);
			fputcsv($f, [""]);

			fputcsv($f, ["Specimen Processing by Panel"]);
			fputcsv($f, $headers);

			if ($cli_ID == -1) {
				foreach ($report_clids as $cid) {
					$cli = isset($clinics[$cid]) ? $clinics[$cid] : $inactive_clinics[$cid];

					fputcsv($f, ["", "", "", $cli->cli_clinic, "", "", "", ""]);
					foreach ($reports as $gid => $row) {
						unset($row['refs']);
						list($panelid, $rcliid) = explode('_', $gid);
						if ($rcliid == $cid) {
							$row['percent'] = $total_processed > 0 ? number_format(($row['processed'] / $total_processed) * 100, 1) . '%' : '0%';
							$row['rejected_percent'] = $row['processed'] > 0 ? number_format(($row['rejected'] / $row['processed']) * 100, 2) . '%' : '0.00%';
							$output_row = array(
								$row['panel'],
								$row['processed'],
								$row['percent'],
								$row['main'],
								$row['ref'],
								$row['rejected'],
								$row['rejected_percent'],
								$row['rerun']
							);
							fputcsv($f, $output_row);
						}
					}
					fputcsv($f, array("", "", "", "", "", "", ""));
				}
			} else {
				foreach ($reports as $row) {
					unset($row['refs']);
					$row['percent'] = $total_processed > 0 ? number_format(($row['processed'] / $total_processed) * 100, 1) . '%' : '0%';
					$row['rejected_percent'] = $row['processed'] > 0 ? number_format(($row['rejected'] / $row['processed']) * 100, 2) . '%' : '0.00%';
					$output_row = array(
						$row['panel'],
						$row['processed'],
						$row['percent'],
						$row['main'],
						$row['ref'],
						$row['rejected'],
						$row['rejected_percent'],
						$row['rerun']
					);
					fputcsv($f, $output_row);
				}
			}
			fputcsv($f, [
				'All Panels',
				$summary_totals['processed'],
				'',
				$summary_totals['main'],
				$summary_totals['ref'],
				$summary_totals['rejected'],
				$summary_totals['rejected_percent'],
				$summary_totals['rerun']
			]);

			// 12-Month Volume Trend
			fputcsv($f, [""]);
			fputcsv($f, ["12-Month Volume Trend" . ' (' . $months_12_text . ')']);

			$volume_month_labels = $chartVolumeData['labels'];
			fputcsv($f, array_merge([''], $volume_month_labels));

			$total_row = array_fill(0, 12, 0);
			foreach ($chartVolumeData['datasets'] as $ds) {
				foreach ($ds['data'] as $idx => $val) {
					$total_row[$idx] += (int)$val;
				}
			}

			$volume_row_labels = ['Total', 'Processed In-House', 'Referenced', 'Rejected'];
			$rej_pct_row = ['Rejected %'];
			foreach ($total_row as $idx => $tot) {
				$rej = $chartVolumeData['datasets'][2]['data'][$idx] ?? 0;
				$rej_pct_row[] = $tot > 0 ? number_format($rej / $tot * 100, 2) . '%' : '0.00%';
			}
			$volume_rows_data  = [
				$total_row,
				$chartVolumeData['datasets'][0]['data'],
				$chartVolumeData['datasets'][1]['data'],
				$chartVolumeData['datasets'][2]['data'],
			];
			foreach ($volume_rows_data as $i => $row_data) {
				fputcsv($f, array_merge([$volume_row_labels[$i]], array_values($row_data)));
			}
			fputcsv($f, $rej_pct_row);

			// Panel Volume Trends
			fputcsv($f, [""]);
			fputcsv($f, ["Panel Volume Trends"]);

			fputcsv($f, array_merge(['Panel'], $chartDataLine['labels']));
			foreach ($chartDataLine['datasets'] as $ds) {
				fputcsv($f, array_merge([$ds['label']], array_values($ds['data'])));
			}

			if ($filepath_save_csv == '') exit;
		}
	}
}


if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
	return;
}


?>

<style>
	h1,
	h2,
	h3,
	h4,
	h5,
	h6 {
		font-weight: unset !important;
		font-size: inherit;
		margin-bottom: 6px !important;
	}

	h1,
	h4 {
		margin: unset;
	}

	h1 {
		margin-bottom: 2px;
	}

	h4 {
		margin-bottom: 5px;
	}

	h5 {
		font-weight: var(--font-weight-medium);
		margin: 0px;
		--tw-font-weight: var(--font-weight-medium);
		font-size: 13px;
		margin-bottom: 10px;
	}

	.text-primary {
		color: var(--primary);
	}

	ol {
		list-style-type: none;
		margin: 0px;
		margin-top: px;
		padding-top: 5px;
		padding-left: 5px;
	}

	ol li a {
		color: unset;
	}


	.text-2xl {
		font-size: var(--text-2xl);
		line-height: var(--tw-leading, var(--text-2xl--line-height));
	}

	tr.clinic_status_0 .clinic_name {
		color: red;
	}

	.font-medium {
		font-weight: 500;
	}
</style>
<?php $schedule_report->style(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
	var noDataPlugin = {
		id: 'noData',
		beforeDraw(chart, args, options) {
			const {
				ctx,
				data,
				chartArea
			} = chart;
			if (!chartArea) return;

			const hasData = data.datasets.some(ds => ds.data && ds.data.length > 0);

			if (!hasData) {
				const {
					top,
					left,
					width,
					height
				} = chartArea;

				ctx.save();
				ctx.clearRect(left, top, width, height);

				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.font = '16px sans-serif';
				ctx.fillStyle = '#999';
				ctx.fillText('No Data Available', left + width / 2, top + height / 2);
				ctx.restore();
			}
		}
	};
	var verticalLinePlugin = {
		id: 'verticalLine',
		afterDraw(chart) {
			if (chart.tooltip?._active?.length) {
				const ctx = chart.ctx;
				const activePoint = chart.tooltip._active[0];
				const x = activePoint.element.x;
				const topY = chart.scales.y.top;
				const bottomY = chart.scales.y.bottom;

				ctx.save();
				ctx.beginPath();
				ctx.moveTo(x, topY);
				ctx.lineTo(x, bottomY);
				ctx.lineWidth = 1;
				ctx.strokeStyle = '#aaa';
				ctx.stroke();
				ctx.restore();
			}
		}
	};
	var grid_x_y = {
		color: 'rgba(126, 126, 126, 0.05)',
		borderDash: [6, 3],
		borderDashOffset: 0,
		drawTicks: false,
		lineWidth: 1
	};
</script>
<div id="root" class="wrap">
	<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
		<div class="space-y-6">
			<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
				<div>
					<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
						Processed Samples</h1>
					<p class="text-muted-foreground mt-1">Processing results by panel and clinic</p>
				</div>
			</div>
			<div>
				<div class="space-y-5">
					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Total Processed</p>
										<p class="text-2xl font-bold" id="total_processed">---</p>
									</div>
									<div class="p-2.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
											<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
											<path d="M18 17V9"></path>
											<path d="M13 17V5"></path>
											<path d="M8 17v-3"></path>
										</svg></div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">In-House</p>
										<p class="text-2xl font-bold" id="total_main">---</p>
									</div>
									<div class="p-2.5 bg-green-50 rounded-lg">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical-icon lucide-flask-conical h-4 w-4 text-green-600">
											<path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2" />
											<path d="M6.453 15h11.094" />
											<path d="M8.5 2h7" />
										</svg>
									</div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Rejected</p>
										<p class="text-2xl font-bold" id="total_rejected">---</p>
									</div>
									<div class="p-2.5 bg-red-50 rounded-lg">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-x-icon lucide-file-x h-4 w-4 text-red-600">
											<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z" />
											<path d="M14 2v5a1 1 0 0 0 1 1h5" />
											<path d="m14.5 12.5-5 5" />
											<path d="m9.5 12.5 5 5" />
										</svg>
									</div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Rejection Rate</p>
										<p class="text-2xl font-bold" id="rejection_rate">---</p>
									</div>
									<div class="p-2.5 bg-orange-50 rounded-lg">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-percent-icon lucide-percent h-4 w-4 text-orange-600">
											<line x1="19" x2="5" y1="5" y2="19" />
											<circle cx="6.5" cy="6.5" r="2.5" />
											<circle cx="17.5" cy="17.5" r="2.5" />
										</svg>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
						<div data-slot="card-content" class="p-6">
							<div class="flex items-center justify-between gap-4">
								<div class="flex flex-wrap items-center gap-3">
									<div class="p-1.5 bg-primary/10 rounded-lg">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel h-4 w-4 text-primary" aria-hidden="true">
											<path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path>
										</svg>
									</div>
									<select id="month_range" name="month_range">
										<?php foreach ($filter_date_select as $item): ?>
											<option <?php if ($item['value'] == $month_range) { ?>selected<?php } ?>
												value="<?php echo $item['value']; ?>">
												<?php echo $item['text']; ?>
											</option>
										<?php endforeach; ?>
									</select>

									<select id="sel_datetype" name="datetype">
										<?php
										foreach ($date_types as $k => $txt) {
											$selected = $k == $date_type ? 'selected' : '';
											echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
										}
										?>
									</select>

									<select id="sel_clinic_rep" name="sel_clinic_rep">
										<option value="0">All Clinics</option>
										<option value="-1" <?php echo $cli_ID == -1 ? "selected" : ''; ?>>All Clinics — Clinic Breakdown</option>
										<?php foreach ($clinics_rs as $item): ?>
											<option value="<?php echo $item->cID; ?>" <?php if ($cli_ID == $item->cID) { ?>selected<?php } ?>>
												<?php echo $item->cli_clinic; ?>
											</option>
										<?php endforeach; ?>
									</select>

									<?php if (count($ci_all_btypes) > 1) { ?>
										<select name="btype" id="sel_btype" required>
											<?php
											foreach ($ci_all_btypes as $bt) {
												$selected = $btype_selected == $bt ? 'selected' : '';
												echo '<option value="' . $bt . '" ' . $selected . '>' . tr_pdd_get_typename($bt) . '</option>';
											}
											?>
										</select>
									<?php } ?>

									<button id="btn_day_customer" data-slot="popover-trigger"
										class="items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-8 px-2 text-sm inline-flex"
										title="Select custom date range" type="button" style="">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
											fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
											stroke-linejoin="round" class="lucide lucide-calendar h-3 w-3 mr-1"
											aria-hidden="true">
											<path d="M8 2v4"></path>
											<path d="M16 2v4"></path>
											<rect width="18" height="18" x="3" y="4" rx="2"></rect>
											<path d="M3 10h18"></path>
										</svg>
										<span class="txt_show_customer_day" data-slot="select-value"
											style="pointer-events:none"><?php echo date_i18n('M j', strtotime($from_date)) . " - " . date_i18n('M j', strtotime($to_date)); ?></span>
									</button>
								</div>
								<div class="flex flex-wrap items-center gap-3">
									<div style="position:relative;display:inline-block;">

										<button id="export_report" data-slot="dropdown-menu-trigger"
											class="inline-flex btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-primary hover:bg-primary/90"
											type="button" id="radix-:rkg:" aria-haspopup="menu" aria-expanded="false"
											data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
												viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
												stroke-linecap="round" stroke-linejoin="round"
												class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
												<path d="M12 15V3"></path>
												<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
												<path d="m7 10 5 5 5-5"></path>
											</svg>Export Report<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
												viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
												stroke-linecap="round" stroke-linejoin="round"
												class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
												<path d="m6 9 6 6 6-6"></path>
											</svg>
										</button>

										<div id="menu_export_report" class="menu-export" data-radix-popper-content-wrapper=""
											dir="ltr"
											style="display: none;position: absolute;left: 14px; top: 100%; margin-top: 4px; min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
											<div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
												data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
												aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
												class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
												tabindex="-1" data-orientation="vertical"
												style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
												<div export_func="export_report" export_type="pdf" role="menuitem"
													data-slot="dropdown-menu-item" data-variant="default"
													class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
													tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
														xmlns="http://www.w3.org/2000/svg" width="24" height="24"
														viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
														stroke-linecap="round" stroke-linejoin="round"
														class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
														<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
														</path>
														<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
														<path d="M10 9H8"></path>
														<path d="M16 13H8"></path>
														<path d="M16 17H8"></path>
													</svg>Export as PDF</div>
												<div export_func="export_report" export_type="csv" role="menuitem"
													data-slot="dropdown-menu-item" data-variant="default"
													class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
													tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
														xmlns="http://www.w3.org/2000/svg" width="24" height="24"
														viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
														stroke-linecap="round" stroke-linejoin="round"
														class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
														<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
														</path>
														<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
														<path d="M10 9H8"></path>
														<path d="M16 13H8"></path>
														<path d="M16 17H8"></path>
													</svg>Export as CSV</div>
											</div>
										</div>
									</div>

									<?php $schedule_report->btn_schedule(); ?>
								</div>
							</div>
						</div>
					</div>
					<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
						<div data-slot="card-header"
							class="@container/card-header px-6 pt-6">
							<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
								<div>
									<h4 data-slot="card-title" class="leading-none flex items-center gap-2">
										<div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
												width="24" height="24" viewBox="0 0 24 24" fill="none"
												stroke="currentColor" stroke-width="2" stroke-linecap="round"
												stroke-linejoin="round"
												class="lucide lucide-chart-column h-4 w-4 text-blue-600"
												aria-hidden="true">
												<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
												<path d="M18 17V9"></path>
												<path d="M13 17V5"></path>
												<path d="M8 17v-3"></path>
											</svg></div>Specimen Processing by Panel<span
											id="specimen_date_range" class="text-sm text-muted-foreground ml-2">(<?php echo $text_sel_date_rep; ?>)</span>
									</h4>
								</div>
							</div>
						</div>
						<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
							<div class="border rounded-lg overflow-hidden">
								<div class="overflow-x-auto">
									<table class="w-full text-sm" id="processed_table">
										<thead class="bg-gray-100 border-b">
											<tr>
												<th data-col="0" class="px-4 py-3 text-left font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Panel
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="1" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Total
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="2" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													% of Total
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="3" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													In-House
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="4" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Referenced
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="5" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Rejected
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="6" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Rejected %
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														% values
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="7" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Rerun Rate
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
											</tr>
										</thead>
										<tbody class="divide-y divide-gray-100" id="processed_tbody"></tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
					<?php include "processed_samples_chart.php"; ?>
					<div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
						<p class="text-xs text-gray-600 text-center">
							<strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
							igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	var month_range = '<?php echo $month_range; ?>';
	var from_date = '<?php echo $from_date; ?>';
	var to_date = '<?php echo $to_date; ?>';
	var cli_ID = '<?php echo $cli_ID; ?>';
	var btype = '<?php echo $btype_selected; ?>';
	var date_type = '<?php echo $date_type; ?>';
	var url_main_page = 'ci_reports_processed_samples';
	var all_clinics = <?php echo json_encode(
							array_map(
								function ($c) {
									return ['cli_clinic' => $c->cli_clinic, 'cli_status' => $c->cli_status];
								},
								$clinics + $inactive_clinics
							)
						); ?>;

	function sortProcessedTable(thEl) {
		const $th = $(thEl);
		const $table = $th.closest('table');
		const colIndex = parseInt($th.data('col'));
		const isAsc = !$th.data('asc');

		// Reset all icons
		$table.find('thead th .lucide-arrow-up-down').show().css('opacity', '0.4').addClass('inline');
		$table.find('thead th .lucide-arrow-up, thead th .lucide-arrow-down').removeClass('inline').hide();
		$th.find('.lucide-arrow-up-down').removeClass('inline').hide();

		// Show the active icon
		if (isAsc) {
			$th.find('.lucide-arrow-up').show().addClass('inline');
		} else {
			$th.find('.lucide-arrow-down').show().addClass('inline');
		}

		// Group detail rows by subgroup
		const subgroups = {};
		$table.find('tbody tr.processed-detail-row').each(function() {
			const sg = $(this).data('subgroup');
			if (!subgroups[sg]) subgroups[sg] = [];
			subgroups[sg].push($(this));
		});

		Object.keys(subgroups).forEach(sg => {
			const rows = subgroups[sg];

			rows.sort(function(a, b) {
				let valA, valB;

				switch (colIndex) {
					case 0: // Panel (text)
						valA = a.data('panel').toString().toLowerCase();
						valB = b.data('panel').toString().toLowerCase();
						break;
					case 1: // Processed
						valA = parseInt(a.data('processed')) || 0;
						valB = parseInt(b.data('processed')) || 0;
						break;
					case 2: // % (percent of total)
						valA = parseFloat(a.data('percent')) || 0;
						valB = parseFloat(b.data('percent')) || 0;
						break;
					case 3: // Lab (main)
						valA = parseInt(a.data('main')) || 0;
						valB = parseInt(b.data('main')) || 0;
						break;
					case 4: // Referenced
						valA = parseInt(a.data('ref')) || 0;
						valB = parseInt(b.data('ref')) || 0;
						break;
					case 5: // Rejected
						valA = parseInt(a.data('rejected')) || 0;
						valB = parseInt(b.data('rejected')) || 0;
						break;
					case 6: // Rejected %
						valA = parseFloat(a.data('rejected-pct')) || 0;
						valB = parseFloat(b.data('rejected-pct')) || 0;
						break;
					case 7: // Rerun
						valA = parseInt(a.data('rerun')) || 0;
						valB = parseInt(b.data('rerun')) || 0;
						break;
				}

				if (typeof valA === 'string') {
					return isAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
				}
				return isAsc ? (valA - valB) : (valB - valA);
			});

			// Re-insert sorted detail rows after their summary row
			const $summary = $table.find(`tr.processed-summary-row[data-subgroup="${sg}"]`);
			if ($summary.length) {
				let $insertAfter = $summary;
				rows.forEach(row => {
					$insertAfter.after(row);
					$insertAfter = row;
				});
			} else {
				// fallback: just append to tbody
				rows.forEach(row => {
					$table.find('tbody').append(row);
				});
			}
		});

		$th.data('asc', isAsc);
	}

	jQuery(function($) {
		window.$ = jQuery;

		$('.processed-table thead th[data-col]').on('click', function() {
			sortProcessedTable(this);
		});

		function renderTable(reports, report_clids, all_labs) {
			const $tbody = $('#processed_tbody');
			$tbody.empty();

			let grand_total = 0;
			for (const key in reports) {
				grand_total += reports[key].processed;
			}

			if (cli_ID == -1) {
				// All Clinics — Clinic Breakdown
				for (const cid in report_clids) {
					const cli = all_clinics[cid] || {
						cli_clinic: 'Unknown #' + cid,
						cli_status: 0
					};

					let trrows = '';
					const totals = {
						processed: 0,
						main: 0,
						ref: 0,
						rejected: 0,
						rerun: 0
					};

					for (const gid in reports) {
						const parts = gid.split('_');
						const panelid = parts[0];
						const rcliid = parts[1];

						if (rcliid != cid) continue;

						const row = reports[gid];
						const rejected_percent = row.processed > 0 ? (row.rejected / row.processed * 100) : 0;
						const percent_of_total = grand_total > 0 ? (row.processed / grand_total * 100) : 0;

						totals.processed += row.processed;
						totals.main += row.main;
						totals.ref += row.ref;
						totals.rejected += row.rejected;
						totals.rerun += row.rerun;

						const ref_by_labs = [];
						for (const lid in row.refs) {
							if (all_labs[lid]) {
								ref_by_labs.push(row.refs[lid] + all_labs[lid].lab_dba_init);
							}
						}

						trrows += `<tr class="hover:bg-gray-50 bg-gray-50 processed-detail-row"
							data-subgroup="${escHtml(String(cid))}"
							data-panel="${escHtml(row.panel)}"
							data-processed="${row.processed}"
							data-percent="${percent_of_total}"
							data-main="${row.main}"
							data-ref="${row.ref}"
							data-rejected="${row.rejected}"
							data-rejected-pct="${rejected_percent}"
							data-rerun="${row.rerun}">
							<td class="px-4 py-3 font-medium text-gray-900">${escHtml(row.panel)}</td>
							<td class="px-4 py-3 text-center font-medium text-gray-900">${row.processed}</td>
							<td class="px-4 py-3 text-center text-gray-900 font-medium">${percent_of_total.toFixed(1)}%</td>
							<td class="px-4 py-3 text-center text-green-600 font-medium">${row.main}</td>
							<td class="px-4 py-3 text-center text-gray-600">${row.ref}${ref_by_labs.length ? '&nbsp;&nbsp;' + ref_by_labs.join('&nbsp;&nbsp;') : ''}</td>
							<td class="px-4 py-3 text-center text-red-600 font-medium">${row.rejected}</td>
							<td class="px-4 py-3 text-center text-orange-600 font-medium">${rejected_percent.toFixed(2)}%</td>
							<td class="px-4 py-3 text-center text-orange-600 font-medium">${row.rerun}</td>
						</tr>`;
					}

					const tot_rej_pct = totals.processed > 0 ?
						(totals.rejected / totals.processed * 100).toFixed(2) + '%' :
						'0.00%';

					const status_class = cli.cli_status == 0 ? 'clinic_status_0' : '';

					$tbody.append(`
						<tr class="hover:bg-gray-50 bg-gray-50 processed-summary-row ${status_class}"
							data-subgroup="${escHtml(String(cid))}">
							<th class="clinic_name px-4 py-3 text-center">${escHtml(cli.cli_clinic)}</th>
							<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.processed}</td>
							<td class="px-4 py-3 text-center text-gray-900 font-medium"></td>
							<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.main}</td>
							<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.ref}</td>
							<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.rejected}</td>
							<td class="px-4 py-3 text-center font-medium text-gray-900">${tot_rej_pct}</td>
							<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.rerun}</td>
						</tr>
						${trrows}
					`);
				}

			} else {
				let trrows = '';
				const totals = {
					processed: 0,
					main: 0,
					ref: 0,
					rejected: 0,
					rerun: 0
				};
				const totalrefs = {};

				for (const gid in reports) {
					const row = reports[gid];
					const rejected_percent = row.processed > 0 ? (row.rejected / row.processed * 100) : 0;
					const percent_of_total = grand_total > 0 ? (row.processed / grand_total * 100) : 0;

					totals.processed += row.processed;
					totals.main += row.main;
					totals.ref += row.ref;
					totals.rejected += row.rejected;
					totals.rerun += row.rerun;

					const ref_by_labs = [];
					for (const lid in row.refs) {
						if (all_labs[lid]) {
							ref_by_labs.push(row.refs[lid] + all_labs[lid].lab_dba_init);
							totalrefs[lid] = (totalrefs[lid] || 0) + row.refs[lid];
						}
					}
					if (ref_by_labs.length === 0) ref_by_labs.push('0');

					trrows += `<tr class="childp hover:bg-gray-50 processed-detail-row"
						data-subgroup="0"
						data-panel="${escHtml(row.panel)}"
						data-processed="${row.processed}"
						data-percent="${percent_of_total}"
						data-main="${row.main}"
						data-ref="${row.ref}"
						data-rejected="${row.rejected}"
						data-rejected-pct="${rejected_percent}"
						data-rerun="${row.rerun}">
						<td class="px-4 py-3 font-medium text-gray-900">${escHtml(row.panel)}</td>
						<td class="px-4 py-3 text-center font-medium text-gray-900">${row.processed}</td>
						<td class="px-4 py-3 text-center text-gray-900 font-medium">${percent_of_total.toFixed(1)}%</td>
						<td class="px-4 py-3 text-center text-green-600 font-medium">${row.main}</td>
						<td class="px-4 py-3 text-center text-gray-600">${ref_by_labs.join('&nbsp;&nbsp;&nbsp;&nbsp;')}</td>
						<td class="px-4 py-3 text-center text-red-600 font-medium">${row.rejected}</td>
						<td class="px-4 py-3 text-center text-orange-600 font-medium">${rejected_percent.toFixed(2)}%</td>
						<td class="px-4 py-3 text-center text-gray-600">${row.rerun}</td>
					</tr>`;
				}

				const tot_rej_pct = totals.processed > 0 ?
					(totals.rejected / totals.processed * 100).toFixed(2) + '%' :
					'0.00%';

				const ref_by_labs_total = [];
				for (const lid in totalrefs) {
					if (all_labs[lid]) {
						ref_by_labs_total.push(totalrefs[lid] + all_labs[lid].lab_dba_init);
					}
				}

				const clinic_label = cli_ID > 0 ?
					(all_clinics[cli_ID]?.cli_clinic || 'Clinic #' + cli_ID) :
					'All Panels';

				$tbody.append(`
					<tr class="childp hover:bg-gray-50 bg-gray-50 processed-summary-row" data-subgroup="0">
						<th class="px-4 py-3 text-left font-medium text-gray-800">${escHtml(clinic_label)}</th>
						<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.processed}</td>
						<td class="px-4 py-3 text-center text-gray-900 font-medium"></td>
						<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.main}</td>
						<td class="px-4 py-3 text-center font-medium text-gray-900">${ref_by_labs_total.join('&nbsp;&nbsp;&nbsp;&nbsp;')}</td>
						<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.rejected}</td>
						<td class="px-4 py-3 text-center font-medium text-gray-900">${tot_rej_pct}</td>
						<td class="px-4 py-3 text-center font-medium text-gray-900">${totals.rerun}</td>
					</tr>
					${trrows}
				`);
			}
		}

		function escHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		function postData(extraParams) {
			return $.post("admin.php?page=" + url_main_page, Object.assign({
				month_range,
				from: from_date,
				to: to_date,
				cli_ID,
				btype,
				datetype: date_type
			}, extraParams));
		}

		function LoadTableData() {
			showLoading('total_processed');
			showLoading('total_main');
			showLoading('total_rejected');
			showLoading('rejection_rate');
			showLoading('processed_table');
			postData({
				trajax: 'loadTableData'
			}).done(function(response) {
				const res = JSON.parse(response);
				$('#total_processed').text(res.total_top.processed);
				$('#total_main').text(res.total_top.main);
				$('#total_rejected').text(res.total_top.rejected);
				$('#rejection_rate').text(res.total_top.rejected_percent);
				$('#specimen_date_range').text(`(${res.text_sel_date_rep})`);
				renderTable(res.reports, res.report_clids, res.all_labs);
				hideLoading('total_processed');
				hideLoading('total_main');
				hideLoading('total_rejected');
				hideLoading('rejection_rate');
				hideLoading('processed_table');
			});
		}

		function LoadChartData() {
			$('#chart_summary_current_list').empty();
			showLoading('panels_volume_trend');
			showLoading('months_volume_trend');
			postData({
				trajax: 'loadChartData'
			}).done(function(response) {
				const res = JSON.parse(response);
				res.chartDataLine.datasets.forEach((ds) => {
					const lastIdx = ds.data.length - 1;
					ds.segment = {
						borderColor: (ctx) => ctx.p1DataIndex === lastIdx ? 'transparent' : undefined,
						borderWidth: (ctx) => ctx.p1DataIndex === lastIdx ? 0 : undefined,
					};
				});
				chartLine.data = res.chartDataLine;
				chartLine.update();
				const $legend = $('#chartLine_legend').empty();
				res.chartDataLine.datasets.forEach((ds, i) => {
					const fullName = ds.full_name || ds.label || '';
					const shortName = ds.label || '';
					const color = ds.borderColor || ds.backgroundColor || '#888';

					$legend.append(`
						<div class="legend-item inline-flex items-center gap-1.5 cursor-pointer select-none"
							data-index="${i}">
							<span style="
								display:inline-block;
								width:10px; height:10px;
								border-radius:50%;
								background:${color};
								flex-shrink:0;
							"></span>
							<span class="text-sm text-gray-600 truncate max-w-[120px] tooltip-trigger"
								data-tooltip="${fullName}"
								style="max-width:120px">${shortName}</span>
						</div>
					`);
				});

				// Click to toggle dataset visibility (same logic as before)
				$(document).off('click.chartlinelegend').on('click.chartlinelegend', '#chartLine_legend .legend-item', function() {
					const i = parseInt($(this).data('index'));
					let onlyOneVisible = true;
					chartLine.data.datasets.forEach((ds, idx) => {
						if (idx !== i && chartLine.isDatasetVisible(idx)) {
							onlyOneVisible = false;
						}
					});
					chartLine.data.datasets.forEach((ds, idx) => {
						chartLine.setDatasetVisibility(idx, onlyOneVisible ? true : idx === i);
					});
					// Update opacity of legend items
					$('#chartLine_legend .legend-item').each(function() {
						const idx = parseInt($(this).data('index'));
						$(this).css('opacity', chartLine.isDatasetVisible(idx) ? 1 : 0.35);
					});
					chartLine.update();
				});
				volume_chart.data = res.chartVolumeData;
				volume_chart.update();
				$.each(res.chartDataLine.datasets, function(index, row) {
					const sample = row.data[12] ?? 0;
					const currentMonthLabel = res.chartDataLine.labels[12] ?? 'Current';
					const fullName = row.full_name || row.label || '';
					const displayName = fullName.length > 20 ? fullName.substring(0, 20) + '...' : fullName;
					$('#chart_summary_current_list').append(`
						<div class="text-center p-3 rounded-lg" style="background-color:${row.bk_color}">
							<div class="text-xl font-bold" style="color:${row.borderColor}">${sample}</div>
							<div class="text-xs text-gray-600 mt-1 tooltip-trigger" data-tooltip="${fullName}">${displayName} (${currentMonthLabel})</div>
						</div>
					`);
				});
				$('#months_12_text').text(res.months_12_text);
				$('#avg_volume').text(res.avg_volume);
				$('#avg_rejected').text(`${Number(res.avg_rejected_rate).toFixed(2)}%`);
				$('#r2').text(`${res.r2}%`);
				hideLoading('panels_volume_trend');
				hideLoading('months_volume_trend');
			});
		}

		LoadChartData();
		LoadTableData();
		if (month_range != "custom") $("#btn_day_customer").removeClass("inline-flex").hide();

		update_ui_select();

		$("#month_range").change(function() {
			month_range = $(this).val();
			if (month_range == "custom") {
				$("#btn_day_customer").addClass("inline-flex").show();
				$("#btn_day_customer").data('daterangepicker').show();
			} else {
				$("#btn_day_customer").removeClass("inline-flex").hide();
				LoadTableData();
			}
		});

		$(".btn-export").on("click", function(e) {
			e.stopPropagation();
			var id_btn = $(this).attr("id");
			$("#menu_" + id_btn).toggle();
		});

		$(document).on("click", function(e) {
			if (!$(e.target).closest(".custom-select").length) {
				$(".custom-select .options").hide();
			}

			if (!$(e.target).closest(".menu-export").length) {
				$(".menu-export").hide();
			}
		});

		$(".export_item").click(async function() {
			var export_func = $(this).attr("export_func");
			var export_type = $(this).attr("export_type");

			var $url = location.href + '&trajax=export_report&export_type=' + export_type;
			$url += '&export_func=' + export_func;
			$url += '&month_range=' + month_range;
			$url += '&from=' + from_date;
			$url += '&to=' + to_date;
			$url += '&cli_ID=' + cli_ID;
			$url += '&btype=' + btype;
			$url += '&datetype=' + date_type;
			showToast('info', 'Preparing your download...', 'Generating...', 4000);
			try {
				const response = await fetch($url);

				// 🔥 Lấy filename từ header
				const disposition = response.headers.get("Content-Disposition");
				let filename = "download." + export_type;

				if (disposition && disposition.includes("filename=")) {
					const match = disposition.match(/filename="?([^"]+)"?/);
					if (match && match[1]) filename = match[1];
				}

				const blob = await response.blob();

				const a = document.createElement("a");
				a.href = URL.createObjectURL(blob);
				a.download = filename; // giữ nguyên tên file
				a.click();

				showToast('success', 'Download ready!', 'Your file "' + filename +
					'" has been downloaded.', 4000);

			} catch (err) {
				showToast('error', 'Error', 'Download failed.', 4000);
			}
			return false;
		});

		$('#btn_day_customer').daterangepicker({
				opens: 'right'
			},
			function(start, end, label) {
				$('.txt_show_customer_day').html(start.format('MMM D') + ' - ' + end.format('MMM D'));
				from_date = start.format('YYYY-MM-DD');
				to_date = end.format('YYYY-MM-DD');
				LoadTableData();
			}
		);

		$("#sel_clinic_rep").change(function() {
			cli_ID = $(this).val();
			LoadTableData();
		});

		$("#sel_btype").change(function() {
			btype = $(this).val();
			LoadTableData();
			LoadChartData();
		});

		$("#sel_datetype").change(function() {
			date_type = $(this).val();
			LoadTableData();
		});

	});
</script>
<?php $schedule_report->js(); ?>