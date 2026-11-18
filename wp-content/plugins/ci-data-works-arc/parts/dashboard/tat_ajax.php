<?php
global $wpdb, $current_role;
$user_id    = get_current_user_id();
$today      = date_i18n('Y-m-d');

$past_14days    = date_i18n('Y-m-d', strtotime($today) - 13 * 86400);
$selDay			= $_REQUEST['sel_day_tat'] ? $_REQUEST['sel_day_tat'] : '14_days';
$from       	= $past_14days;
$to         	= $today;
$tat_type		= $_REQUEST['tat'] ? $_REQUEST['tat'] : 'tat';
$btype      	= $_REQUEST['btype'] ? $_REQUEST['btype'] : 'tox';
$panel_id   	= $_REQUEST['panel_id'] ? $_REQUEST['panel_id'] : 0;
$panel_name   	= isset($_REQUEST['panel_name']) ? $_REQUEST['panel_name'] : 'All Panels';
$cid_label		= isset($_REQUEST['cid_label']) ? $_REQUEST['cid_label'] : 'All Clinics';
$export     	= isset($_REQUEST['export']);
$export_type    = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : 'csv';

if ($selDay == '14_days') {
	$from  = date_i18n('Y-m-d', strtotime(date_i18n('Y-m-d')) - 13 * 86400);
} elseif ($selDay == '30_days') {
	$from  = date_i18n('Y-m-d', strtotime(date_i18n('Y-m-d')) - 29 * 86400);
} elseif ($selDay == '90_days') {
	$from  = date_i18n('Y-m-d', strtotime(date_i18n('Y-m-d')) - 89 * 86400);
} elseif ($selDay == 'customer_range') {
	$from = $_REQUEST['from'] ?? $today;
	$to   = $_REQUEST['to'] ?? $today;
}


$panels     	= array();
$panels_report  = array();
$results_report = [];
$all_labs     	= array();
$average_hours 	= 0;

$rs = $wpdb->get_results("select * from ci_labs");
foreach ($rs as $r) {
	$all_labs[$r->lab_ID] = $r;
}

$days_of_operation_lab = CI_Base_Lab::get_lab_operations();

if ($export) {
	if ($panel_id > 0) {
		if ($btype === 'study') {
			$panel_name_export = $wpdb->get_var("SELECT abrev FROM ci_study_tests WHERE ID = {$panel_id}");
		} else {
			$panel_name_export = $wpdb->get_var("SELECT REPLACE(tg_name,'(new)','') FROM ci_test_groups WHERE tg_ID = {$panel_id}");
		}
		$panels = array($panel_id => $panel_name_export ?: 'Selected Panel');
	} else {
		$panels[0] = 'Total Panels';
		if ($btype === 'study') {
			$rs = $wpdb->get_results("SELECT ID as tg_ID, abrev as tg_name, 'study' as tg_type 
                FROM ci_study_tests 
                ORDER BY ID ASC");
		} else {
			$rs = $wpdb->get_results("SELECT tg_ID, REPLACE(tg_name,'(new)','') as tg_name, tg_type 
                FROM ci_test_groups 
                WHERE tg_parent=0 AND tg_show_req=1 AND tg_type='{$btype}'
                ORDER BY tg_position ASC");
		}
		foreach ($rs as $r) {
			$panels[$r->tg_ID] = $r->tg_name;
		}
	}
} else {
	$panels = array($panel_id => '');
}
$diff_days = floor((strtotime($to) - strtotime($from)) / 86400) + 1;
$total_hours    = 0;
$count_date     = 0;
$googledata     = array();
$googledata[]   = array('Date', 'Hours');

$cli_ids        = '';
$where_global   = ' and ' . tr_pdd_where_batch_rejected(false, true, $btype, array('deleted', 'archived', 'cancelled'));

if (isset($_REQUEST['cg']) && $_REQUEST['cg'] > 0) {
	$cli_ids  = $wpdb->get_col("select cID from ci_clinic where cli_group_ID = {$_REQUEST['cg']}");
	$cli_ids = implode(',', $cli_ids);
} else if (isset($_REQUEST['cid']) && $_REQUEST['cid'] > 0) {
	$cli_ids = isset($_REQUEST['cid']) ? $_REQUEST['cid'] : '';
	if (empty($cli_ids)) {
		return;
	}
} else if (tr_current_role_in(array('office_manager', 'multi_site_manager', 'clinic'))) {

	$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
	if ($current_clinic > 0) {
		$cli_ids = array($current_clinic);
	} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
		$cid = get_user_meta($user_id, '_clinic_id', true);
		$cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
		$cli_ids = implode(',', $cli_ids);
	}
} else {
	$cli_ids = isset($_REQUEST['cid']) ? $_REQUEST['cid'] : '';
}

if (empty($cli_ids) && tr_current_role_in(array('lab_rep', 'lab_rep_w2'))) {
	$rep_id = get_user_meta($user_id, '_rep_id', true);
	$rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
	$cli_ids = $wpdb->get_col("select cli.cID from ci_clinic cli where `cli_rep-ID`={$rep_id}");
	$cli_ids = implode(',', $cli_ids);
}

if (!empty($cli_ids)) {
	if (is_array($cli_ids)) {
		$cli_ids = implode(',', $cli_ids);
	}
	$where_global .= " and ba.cli_ID in ({$cli_ids}) ";
} else {
	//echo 'No Clinic selected';
	// return;
}



$batch_tbl  = tr_pdd_get_table_by_btype($btype);
$report_tbl = tr_pdd_get_table_report_by_btype($btype);

$tat_chart_data = [
	'labels' => [],
	'datasets' => [
		[
			'label' => "C2R",
			'data' => [],
			'backgroundColor' => "#ff0000"
		],
		[
			'label' => "R2R",
			'data' => [],
			'backgroundColor' => "#f59e0b"
		]
	]
];

$data_all_panels = array();

foreach ($panels as $p_id => $p_name) {

	$total_hours    = 0;
	$count_date     = 0;
	$dates          = array();

	$where = $where_global;
	if ($p_id > 0) {
		if ($btype == 'tox') {
			$tests = $wpdb->get_col("select te_name from ci_test_panels where te_group_id = {$p_id}");
			$where_tests = [];
			foreach ($tests as $t) {
				$where_tests[] = "find_in_set('{$t}', ba.panel_tests_requested)";
			}
			if (!empty($where_tests)) {
				$where .= " AND ( " . implode(' OR ', $where_tests) . " ) ";
			}
		} elseif ($btype == 'study') {
			$where .= " and find_in_set('{$p_id}', ba.panel_tests_requested)";
		} else {
			$where .= " and find_in_set('GROUP_{$p_id}', ba.panel_tests_requested)";
		}
	}

	$where_batch_id = tr_pdd_build_batch_id_where($from, $to);

	$sql = "select re.id,
				ba.batch_id,
                ba.doc, 
				ba.lab_ID,
                ba.ref_lab_ID, 
				ba.batch_status,
				ba.archived_date,
                ba.spec_received,
                re.approve_date 
            from {$batch_tbl} ba
            left JOIN {$report_tbl} re ON re.patient_id = ba.assen  and  re.estatus='C'
            where {$where_batch_id}
            {$where}
            order by batch_id asc";

	$results = $wpdb->get_results($sql);

	if (isset($_GET['debug'])) {
		var_dump($sql);
		echo '<br>';
		echo $wpdb->last_error;
	}


	foreach ($results as $row) {
		$recei_time = $row->spec_received;

		$c2r_hours 	= CI_Base_Lab::calculate_business_hours($row->doc, $recei_time, $row->lab_ID, $days_of_operation_lab);
		$r2r_hours 	= CI_Base_Lab::calculate_business_hours($recei_time, $row->approve_date, $row->lab_ID, $days_of_operation_lab);

		$date = substr($row->batch_id, 0, 4) . '-' . substr($row->batch_id, 4, 2) . '-' . substr($row->batch_id, 6, 2);

		if (isset($_REQUEST['debug'])) {
			echo $date . '=>' . $row->doc . '  => ' . $recei_time . '  => ' . $row->approve_date . ' => c2r:' . $c2r_hours . ' => r2r: ' . $r2r_hours;
			echo '<br>';
		}


		if (!isset($dates[$date])) {
			$dates[$date] = array(
				'c2r' => array(),
				'r2r' => array()
			);
		}

		if (!isset($data_all_panels[$date])) {
			$data_all_panels[$date] = array(
				'c2r' => array(),
				'r2r' => array()
			);
		}

		if ($c2r_hours > 0) {
			$dates[$date]['c2r'][] = $c2r_hours;

			$data_all_panels[$date]['c2r'][] = $c2r_hours;
		}

		if ($r2r_hours > 0) {
			$dates[$date]['r2r'][] = $r2r_hours;

			$data_all_panels[$date]['r2r'][] = $r2r_hours;
		}
	}

	foreach ($dates as $date => $arr) {
		$c2r_hours = count($arr['c2r']) > 0 ? array_sum($arr['c2r']) / count($arr['c2r']) : 0;
		$r2r_hours = count($arr['r2r']) > 0 ? array_sum($arr['r2r']) / count($arr['r2r']) : 0;

		$c2r_hours = round($c2r_hours, 1);
		$r2r_hours = round($r2r_hours, 1);

		//$tat_chart_data['labels'][] = date('m/d', strtotime($date));
		//$tat_chart_data['datasets'][0]['data'][] = $c2r_hours;
		//$tat_chart_data['datasets'][1]['data'][] = $r2r_hours;

		$count_date++;

		$total_hours += $c2r_hours + $r2r_hours;
	}


	if ($count_date == 0 && !$export) {
		echo '
		<p style="width: 100%;text-align: center;padding-bottom: 50px;">
            <svg style="display: unset;" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" aria-hidden="true" role="img">
                <ellipse cx="12" cy="5" rx="7" ry="2.5" fill="none" stroke="#6B7280" stroke-width="1.5" />
                <path d="M5 5v6c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5V5" fill="none" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <ellipse cx="12" cy="13.5" rx="7" ry="2.5" fill="none" stroke="#6B7280" stroke-width="1.5" opacity="0.9" />
                <line x1="6" y1="18" x2="18" y2="6" stroke="#6B7280" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <br />
            No Data
        </p>';
		exit;
	}


	if ($count_date > 0) {
		$average_hours = round($total_hours / $count_date, 1);
	} else {
		$average_hours = 0;
	}

	if ($average_hours > 0) {
		$exportdata[$p_name] = $average_hours;
	}
}

$current_date = strtotime($from);
$end_date     = strtotime($to);

while ($current_date <= $end_date) {
	$date = date_i18n('Y-m-d', $current_date);
	$arr  = isset($data_all_panels[$date]) ? $data_all_panels[$date] : array('c2r' => array(), 'r2r' => array());

	$c2r_hours = count($arr['c2r']) > 0 ? array_sum($arr['c2r']) / count($arr['c2r']) : 0;
	$r2r_hours = count($arr['r2r']) > 0 ? array_sum($arr['r2r']) / count($arr['r2r']) : 0;

	$c2r_hours = round($c2r_hours, 1);
	$r2r_hours = round($r2r_hours, 1);

	$tat_chart_data['labels'][] = date('m/d', $current_date);
	$tat_chart_data['datasets'][0]['data'][] = $c2r_hours;
	$tat_chart_data['datasets'][1]['data'][] = $r2r_hours;

	$current_date = strtotime('+1 day', $current_date);
}

function calculate_avg_hours_range($wpdb, $batch_tbl, $report_tbl, $from, $to, $where, $days_of_operation_lab)
{
	$where_batch_id = tr_pdd_build_batch_id_where($from, $to);
	$sql = "
        SELECT re.id, ba.doc, ba.batch_id, ba.lab_ID, ba.batch_status, ba.archived_date, ba.ref_lab_ID, ba.spec_received, re.approve_date
        FROM {$batch_tbl} ba
        LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen AND re.estatus = 'C'
        WHERE {$where_batch_id}
         {$where}
    ";
	$results = $wpdb->get_results($sql);

	$dates = [];
	foreach ($results as $row) {
		$recei_time   = $row->spec_received;
		if ($row->batch_status == 'archived' || stripos($row->batch_status, 're') === 0) {
			$recei_time = $row->archived_date;
		}

		$c2r_hours 	= CI_Base_Lab::calculate_business_hours($row->doc, $recei_time, $row->lab_ID, $days_of_operation_lab);
		$r2r_hours 	= CI_Base_Lab::calculate_business_hours($recei_time, $row->approve_date, $row->lab_ID, $days_of_operation_lab);

		$date = date_i18n('Y-m-d', strtotime($row->batch_id));

		if (!isset($dates[$date])) $dates[$date] = ['c2r' => [], 'r2r' => []];

		if ($c2r_hours > 0) {
			$dates[$date]['c2r'][] = $c2r_hours;
		}

		if ($r2r_hours > 0) {
			$dates[$date]['r2r'][] = $r2r_hours;
		}
	}

	$avg_c2r = $avg_r2r = 0;
	$count = count($dates);
	if ($count > 0) {
		foreach ($dates as $arr) {
			if (count($arr['c2r']) > 0) $avg_c2r += array_sum($arr['c2r']) / count($arr['c2r']);
			if (count($arr['r2r']) > 0) $avg_r2r += array_sum($arr['r2r']) / count($arr['r2r']);
		}
		$avg_c2r = round($avg_c2r / $count, 1);
		$avg_r2r = round($avg_r2r / $count, 1);
	}
	$tat = round($avg_c2r + $avg_r2r, 1);

	return ['tat' => $tat, 'c2r' => $avg_c2r, 'r2r' => $avg_r2r];
}

function compare_hours_with_color($current, $previous, $tagTime = 'period')
{
	$diff = round($current - $previous, 1);
	if ($diff > 0) {
		return [
			'text'  => "↑ {$diff}h from last {$tagTime}",
			'color' => "red"
		];
	} elseif ($diff < 0) {
		return [
			'text'  => "↓ " . abs($diff) . "h from last {$tagTime}",
			'color' => "green"
		];
	} else {
		return [
			'text'  => "No change",
			'color' => "gray"
		];
	}
}

function tr_calc_panel_tat($wpdb, $btype, $panel_id, $from, $to, $where_global = '', $days_of_operation_lab)
{
	$batch_tbl  = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$where_panel = $where_global;

	if ($panel_id > 0) {
		if ($btype == 'tox') {
			$tests = $wpdb->get_col("select te_name from ci_test_panels where te_group_id = {$panel_id}");

			$where_tests = array();
			foreach ($tests as $t) {
				$where_tests[] = "  find_in_set('{$t}', ba.panel_tests_requested) ";
			}
			$where_panel .= " AND ( " . implode(' OR ', $where_tests) . " ) ";
		} elseif ($btype == 'study') {
			$where_panel .= " AND find_in_set('{$panel_id}', ba.panel_tests_requested)";
		} else {
			$where_panel .= " AND find_in_set('GROUP_{$panel_id}', ba.panel_tests_requested) ";
		}
	}
	$where_batch_id = tr_pdd_build_batch_id_where($from, $to);

	$sql = "
        SELECT ba.doc, ba.lab_ID, ba.batch_status, ba.archived_date, ba.spec_received, re.approve_date
        FROM {$batch_tbl} ba
        JOIN {$report_tbl} re ON re.patient_id = ba.assen
        WHERE {$where_batch_id}
          AND re.estatus = 'C'
          {$where_panel}
        ORDER BY ba.doc ASC
    ";

	$rows = $wpdb->get_results($sql);

	$samples = count($rows);
	if ($samples == 0) {
		return ['tat' => 0, 'c2r' => 0, 'r2r' => 0, 'samples' => 0, 'target' => 36, 'target_display' => 36];
	}

	$c2r_total = $r2r_total = $target_total = 0;
	$targets = array();
	foreach ($rows as $r) {
		$recei_time   = $r->spec_received;
		if ($r->batch_status == 'archived' || stripos($r->batch_status, 're') === 0) {
			$recei_time = $r->archived_date;
		}
		$c2r_total 	+= CI_Base_Lab::calculate_business_hours($r->doc, $recei_time, $r->lab_ID, $days_of_operation_lab);
		$r2r_total 	+= CI_Base_Lab::calculate_business_hours($recei_time, $r->approve_date, $r->lab_ID, $days_of_operation_lab);
		$target_hours = CI_Base_Lab::get_target_tat_hours($r->lab_ID, $r->doc, $days_of_operation_lab);
		$target_total += $target_hours;
		$targets[$target_hours] = $target_hours;
	}

	$avg_c2r = round($c2r_total / $samples, 1);
	$avg_r2r = round($r2r_total / $samples, 1);
	$avg_target = round($target_total / $samples, 1);
	sort($targets, SORT_NUMERIC);
	$target_display = count($targets) > 1 ? reset($targets) . '-' . end($targets) : reset($targets);

	return [
		'tat'     => round($avg_c2r + $avg_r2r, 1),
		'c2r'     => $avg_c2r,
		'r2r'     => $avg_r2r,
		'samples' => $samples,
		'target'  => $avg_target,
		'target_display' => $target_display
	];
}

$where_with_panel = $where_global;

if ($panel_id > 0) {
	if ($btype == 'tox') {
		$tests = $wpdb->get_col("select te_name from ci_test_panels where te_group_id = {$panel_id}");
		$where_tests = [];
		foreach ($tests as $t) {
			$where_tests[] = "find_in_set('{$t}', ba.panel_tests_requested)";
		}
		if (!empty($where_tests)) {
			$where_with_panel .= " AND ( " . implode(' OR ', $where_tests) . " ) ";
		}
	} elseif ($btype == 'study') {
		$where_with_panel .= " and find_in_set('{$panel_id}', ba.panel_tests_requested)";
	} else {
		$where_with_panel .= " and find_in_set('GROUP_{$panel_id}', ba.panel_tests_requested)";
	}
}
// Current period = toàn bộ khoảng user chọn
$current_period = calculate_avg_hours_range($wpdb, $batch_tbl, $report_tbl,  $from, $to, $where_with_panel, $days_of_operation_lab);

// 7 ngày gần nhất trong khoảng
$from7 = strtotime($to) - 6 * 86400;
if (strtotime($from) > $from7) $from7 = strtotime($from);
$avg_7days = calculate_avg_hours_range(
	$wpdb,
	$batch_tbl,
	$report_tbl,
	date_i18n('Y-m-d', $from7),
	$to,
	$where_with_panel,
	$days_of_operation_lab
);

// 14 ngày gần nhất trong khoảng
$from14 = strtotime($to) - 13 * 86400;
if (strtotime($from) > $from14) $from14 = strtotime($from);
$avg_14days = calculate_avg_hours_range(
	$wpdb,
	$batch_tbl,
	$report_tbl,
	date_i18n('Y-m-d', $from14),
	$to,
	$where_with_panel,
	$days_of_operation_lab
);

// Tuần trước (7 ngày trước giai đoạn 7 ngày hiện tại)
$last_week_from = strtotime($to) - 13 * 86400;
$last_week_to   = strtotime($to) - 7 * 86400;
if ($last_week_from < strtotime($from)) $last_week_from = strtotime($from);
if ($last_week_to   < strtotime($from)) $last_week_to   = strtotime($from);

$last_week = calculate_avg_hours_range(
	$wpdb,
	$batch_tbl,
	$report_tbl,
	date_i18n('Y-m-d', $last_week_from),
	date_i18n('Y-m-d', $last_week_to),
	$where_with_panel,
	$days_of_operation_lab
);

$chart_summary = [];
foreach (['TAT', 'C2R', 'R2R'] as $type) {
	$key = strtolower($type);

	$chart_summary[$type] = [
		'current' => [
			'value'   => $current_period[$key],
			'compare' => compare_hours_with_color($current_period[$key], $last_week[$key])['text'],
			'color'   => compare_hours_with_color($current_period[$key], $last_week[$key])['color']
		],
		'average_7days' => [
			'value'   => $avg_7days[$key],
			'compare' => compare_hours_with_color($avg_7days[$key], $last_week[$key], '7 days')['text'],
			'color'   => compare_hours_with_color($avg_7days[$key], $last_week[$key])['color']
		],
		'average_14days' => [
			'value'   => $avg_14days[$key],
			'compare' => compare_hours_with_color($avg_14days[$key], $last_week[$key], '14 days')['text'],
			'color'   => compare_hours_with_color($avg_14days[$key], $last_week[$key])['color']
		]
	];
}

$tat_key = strtoupper($tat_type);
$obj_tat = $chart_summary[$tat_key] ?? $chart_summary['TAT'];

if ($btype === 'study') {
	$r_report = $wpdb->get_results("
		SELECT ID as tg_ID,
		abrev as tg_name,
		'study' as tg_type
		FROM ci_study_tests
		ORDER BY ID ASC
	");
} else {
	$r_report = $wpdb->get_results("
		SELECT tg_ID, 
		REPLACE(tg_name,'(new)','') as tg_name,
		 tg_type 
		FROM ci_test_groups 
		WHERE tg_parent = 0 AND tg_show_req = 1 and tg_type = '{$btype}'
		ORDER BY tg_position ASC
	");
}
foreach ($r_report as $rp) {
	$panels_report[] = $rp;
}
$results_report = [];
foreach ($panels_report as $p) {
	$res = tr_calc_panel_tat($wpdb, $p->tg_type, $p->tg_ID, $from, $to, $where_global, $days_of_operation_lab);

	if ($res['samples'] == 0) continue; // bỏ panel không có mẫu

	$target = $res['target'] > 0 ? $res['target'] : 36;
	$target_display = isset($res['target_display']) ? $res['target_display'] : (abs($target - round($target)) < 0.01 ? (int) round($target) : $target);
	$diff   = $res['tat'] - $target;
	if ($diff == 0) {
		$performance = "On target";
	} elseif ($diff > 0) {
		$performance = round(($diff / $target) * 100, 1) . "% over target";
	} else {
		$performance = round((abs($diff) / $target) * 100, 1) . "% under target";
	}

	$results_report[] = [
		'panel' => $p->tg_name,
		'data'  => [
			'tat'         => $res['tat'],
			'c2r'         => $res['c2r'],
			'r2r'         => $res['r2r'],
			'samples'     => $res['samples'],
			'target'      => $target_display,
			'performance' => $performance,
			'compare' 	  => $diff > 0 ? 'red' : 'green'
		]
	];
}

if ($export) {
	trpdd_log_user(get_current_user_id(), 'download_report', 'TaT Dashboard Report', '', '', '', '', 'Report_' . date_i18n('mdY'));


	if ($export_type === 'csv') {
		$filename = generate_report_filename('DASH', 'TAT', $from, $to, 'csv');
		tr_pdd_header('csv', $filename);
		$f = fopen('php://output', 'w');

		// ===== HEADER INFO =====
		fputcsv($f, [$labname . " - TAT Export"]);
		fputcsv($f, ["Generated on:", date_i18n("m/d/Y h:i:s A")]);
		fputcsv($f, []);
		fputcsv($f, ["Filter Settings"]);
		fputcsv($f, ["Date Range", $from . " to " . $to]);
		fputcsv($f, ["TAT Metric", $tat_key]);
		fputcsv($f, ["Test Type", strtoupper($btype)]);
		fputcsv($f, ["Panels", $panel_name]);
		fputcsv($f, ["Clinic", $cid_label]);
		fputcsv($f, []);

		// ===== SUMMARY METRICS =====
		fputcsv($f, ["Summary Metrics (Selected filters apply)"]);
		fputcsv($f, ["Metric", "Value", "Trend"]);
		fputcsv($f, ["Selected Period Average", $obj_tat['current']['value'] . " hours", $obj_tat['current']['compare']]);
		fputcsv($f, ["14 Day Average", $obj_tat['average_14days']['value'] . " hours", $obj_tat['average_14days']['compare']]);
		fputcsv($f, ["7 Day Average", $obj_tat['average_7days']['value'] . " hours", $obj_tat['average_7days']['compare']]);
		fputcsv($f, []);

		// ===== PANEL TURNAROUND TIMES =====
		fputcsv($f, ["Panel Turnaround Times"]);
		fputcsv($f, ["Panel Name", "TAT (hours)", "C2R (hours)", "R2R (hours)", "Target (hours)", "Performance", "Sample Count"]);
		foreach ($results_report as $row) {
			fputcsv($f, [
				$row['panel'],
				$row['data']['tat'],
				$row['data']['c2r'],
				$row['data']['r2r'],
				$row['data']['target'],
				$row['data']['performance'],
				$row['data']['samples']
			]);
		}
		fputcsv($f, []);

		// ===== 14-DAY TREND DATA =====
		fputcsv($f, [$diff_days . "-Day Trend Data (Selected filters apply)"]);
		fputcsv($f, ["Date", "Collection to Received (hours)", "Received to Results (hours)", "Total TAT (hours)"]);

		foreach ($tat_chart_data['labels'] as $idx => $label) {
			$c2r = $tat_chart_data['datasets'][0]['data'][$idx];
			$r2r = $tat_chart_data['datasets'][1]['data'][$idx];
			$tat = round($c2r + $r2r, 1);
			fputcsv($f, [$label, $c2r, $r2r, $tat]);
		}


		fclose($f);
		exit;
	}

	if ($export_type === 'pdf') {
		ob_start();
		require TRCPDD_PATH . 'parts/dashboard/tat_export_template_pfd.php';
		$html = ob_get_clean();
		$outfile = generate_report_filename('DASH', 'TAT', $from, $to, 'pdf');
		trpdd_build_pdf($html, [
			'size'        => 'letter',
			'orientation' => 'portrait',
			'filename'    => $outfile,
			'download'  => 1,
			'compress' => 1
		]);
		exit;
	}
}

?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
	<div class="p-3 bg-gray-50 rounded-lg border">
		<div class="text-xs text-gray-600 mb-1">Selected Period Average</div>
		<div class="text-xl font-bold" id="t_average_hours"><?php echo $obj_tat["current"]["value"]; ?> hours</div>
		<div class="text-xs text-<?php echo $obj_tat["current"]["color"]; ?>-600 mt-1" id="t_average_hours_compare"><?php echo $obj_tat["current"]["compare"]; ?></div>
	</div>
	<div class="p-3 bg-gray-50 rounded-lg border">
		<div class="text-xs text-gray-600 mb-1">14 Day Average</div>
		<div class="text-xl font-bold" id="t_average_14hours"><?php echo $obj_tat["average_14days"]["value"]; ?> hours</div>
		<div class="text-xs text-<?php echo $obj_tat["average_14days"]["color"]; ?>-600 mt-1" id="t_average_14hours_compare"><?php echo $obj_tat["average_14days"]["compare"]; ?></div>
	</div>
	<div class="p-3 bg-gray-50 rounded-lg border">
		<div class="text-xs text-gray-600 mb-1">7 Day Average</div>
		<div class="text-xl font-bold" id="t_average_7days"><?php echo $obj_tat["average_7days"]["value"]; ?> hours</div>
		<div class="text-xs text-<?php echo $obj_tat["average_7days"]["color"]; ?>-600 mt-1" id="t_average_7days_compare"><?php echo $obj_tat["average_7days"]["compare"]; ?></div>
	</div>
</div>

<!---Bengin Report--->
<div id="report_analysis" style="display:none" class="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
	<h4 style="margin-bottom: 13px;margin-top: 0px;" class="font-semibold text-base mb-4 text-slate-800">Live TAT Analysis Report</h4>
	<div class="mb-4 p-3 bg-white rounded-lg border">
		<h5 class="font-medium text-sm mb-2" style="margin-top: 6px;margin-bottom: 6px;">Current Analysis Settings</h5>
		<div class="grid grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
			<div><span class="font-medium">Date Range:</span>
				<div class="text-gray-600"><?php echo $from; ?> to <?php echo $to; ?></div>
			</div>
			<div>
				<span class="font-medium">TAT Type:</span>
				<div class="text-gray-600"><?php echo strtoupper($tat_type); ?> - Total Turnaround Time</div>
			</div>
			<div><span class="font-medium">Test Type:</span>
				<div class="text-gray-600"><?php echo strtoupper($btype); ?></div>
			</div>
			<div><span class="font-medium">Panels:</span>
				<div class="text-gray-600">All Panels</div>
			</div>
			<div>
				<span class="font-medium">Clinic:</span>
				<div class="text-gray-600"><?php echo $cid_label; ?></div>
			</div>
		</div>
	</div>
	<div class="mb-4">
		<h5 class="font-medium text-sm mb-2">Panel Performance vs Targets</h5>
		<div class="bg-white rounded-lg border overflow-hidden">
			<table class="w-full text-sm">
				<thead class="bg-gray-50">
					<tr>
						<th class="text-left p-3 font-medium">Panel</th>
						<th class="text-center p-3 font-medium">TAT</th>
						<th class="text-center p-3 font-medium">C2R</th>
						<th class="text-center p-3 font-medium">R2R</th>
						<th class="text-center p-3 font-medium">Target</th>
						<th class="text-center p-3 font-medium">Performance</th>
						<th class="text-center p-3 font-medium">Samples</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($results_report as $row): ?>
						<tr>
							<td class="px-2 py-1 border"><?php echo esc_html($row['panel']) ?></td>
							<td class="px-2 py-1 border"><?php echo $row['data']['tat'] ?>h</td>
							<td class="px-2 py-1 border"><?php echo $row['data']['c2r'] ?>h</td>
							<td class="px-2 py-1 border"><?php echo $row['data']['r2r'] ?>h</td>
							<td class="px-2 py-1 border"><?php echo $row['data']['target'] ?>h</td>
							<td class="px-2 py-1 border" style="font-weight: 600;"><?php echo $row['data']['performance'] ?></td>
							<td class="px-2 py-1 border"><?php echo $row['data']['samples'] ?></td>
						</tr>
					<?php endforeach ?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="text-xs text-slate-600 bg-slate-100 p-2 rounded">💡 This report updates automatically when you change filters above. Use the Export button for PDF/CSV versions.</div>
</div>
<!---End Report--->
<div>
	<div class=mb-3>
		<h4 class="font-medium text-sm mb-2"><?php echo $diff_days ?>-Day Average Trend</h4>
		<div class="flex items-center gap-6 text-xs text-gray-600">
			<div class="flex items-center gap-2" id="tag_c2r">
				<div class="w-3 h-3 rounded-sm bg-red-600"></div><span>C2R - Collection to Received</span>
			</div>
			<div class="flex items-center gap-2" id="tag_r2r">
				<div class="w-3 h-3 rounded-sm bg-orange-400"></div><span>R2R - Received to Results</span>
			</div>
		</div>
	</div>
</div>
<div id="custom-tooltip-tat" class="recharts-tooltip-wrapper"></div>
<div style="height:300px; width:100%; margin:3px 3px">
	<canvas id="tat_chart">Loading</canvas>
</div>
<script>
	var tooltipTat = document.getElementById('custom-tooltip-tat');
	var ctx_tat_chart = document.getElementById("tat_chart");
	var dataChart = <?php echo json_encode($tat_chart_data); ?>;
	var chart_summary = <?php echo json_encode($chart_summary); ?>;
	var tat_type = '<?php echo $tat_type; ?>';
	var tat_chart = new Chart(ctx_tat_chart, {
		type: "bar",
		data: dataChart,
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				tooltip: {
					mode: "index",
					intersect: false,
					enabled: false,
					external: function(context) {
						//let elementTag = document.getElementById('tat_chart');
						//let y = elementTag.getBoundingClientRect().top + window.scrollY;
						let tooltipModel = context.tooltip;

						if (tooltipModel.opacity === 0) {
							tooltipTat.classList.remove("active");
							return;
						} else {
							tooltipTat.classList.add("active");
						}
						let dataPoints = tooltipModel.dataPoints;

						let total = 0;
						dataPoints.forEach(dp => total += dp.raw || 0);
						let formattedTotal = parseFloat(total.toFixed(2));

						let innerHtml = `
							<div class="font-medium" style="font-weight:bold; margin-bottom:4px;">
							${dataPoints[0].label} - TAT Breakdown
							</div>
						`;

						dataPoints.forEach(dp => {
							let color = dp.element?.options?.backgroundColor || dp.dataset.backgroundColor;
							innerHtml += `
							<div class="text-sm">
								<span style="color:${color}; font-size:12px;">●</span> ${dp.dataset.label}: ${parseFloat((dp.raw || 0).toFixed(2))}h
							</div>
							`;
						});

						innerHtml += `
							<hr style="margin:4px 0; border:0; border-top:1px solid #ddd;">
							<div><strong>TAT (Total): ${formattedTotal}h</strong></div>
						`;
						tooltipTat.innerHTML = innerHtml;

						let index = tooltipModel.dataPoints[0].dataIndex; // vị trí cột

						// điều chỉnh caretX dựa vào index
						let offsetX = 0;
						if (index > 6) {
							offsetX = -180; // dịch trái
						} else {
							offsetX = 60; // dịch phải
						}

						let x = tooltipModel.caretX + offsetX;
						let y = tooltipModel.caretY;

						//let tooltipEl = document.getElementById("tat_chart_tooltip");
						tooltipTat.style.left = x + "px";
						//tooltipTat.style.top = (y+180) + "px";
						tooltipTat.style.display = "block";
					}
				},
				legend: {
					display: false
				}
			},
			scales: {
				x: {
					stacked: true
				},
				y: {
					stacked: true,
					beginAtZero: true
				}
			}
		}
	});

	function changeTatType(value) {
		tat_chart.data.datasets.forEach((ds, i) => {
			if (value === "tat") {
				tat_chart.setDatasetVisibility(i, true);
			} else if (value === "c2r") {
				tat_chart.setDatasetVisibility(i, ds.label === "C2R");
			} else if (value === "r2r") {
				tat_chart.setDatasetVisibility(i, ds.label === "R2R");
			}
		});

		var tagC2R = document.getElementById("tag_c2r");
		var tagR2R = document.getElementById("tag_r2r");

		if (value === "tat") {
			tagC2R?.classList.remove("hidden");
			tagR2R?.classList.remove("hidden");
		} else if (value === "c2r") {
			tagC2R?.classList.remove("hidden");
			tagR2R?.classList.add("hidden");
		} else if (value === "r2r") {
			tagC2R?.classList.add("hidden");
			tagR2R?.classList.remove("hidden");
		}

		var objTimeCur = chart_summary[value.toUpperCase()];
		tat_chart.update();
		document.getElementById("t_average_hours").innerText = objTimeCur.current.value + " hours";
		document.getElementById("t_average_hours_compare").innerText = objTimeCur.current.compare;
		document.getElementById("t_average_hours_compare").className = "text-xs mt-1 text-" + objTimeCur.current.color + "-600";

		// 14 Day Average
		document.getElementById("t_average_14hours").innerText = objTimeCur.average_14days.value + " hours";
		document.getElementById("t_average_14hours_compare").innerText = objTimeCur.average_14days.compare;
		document.getElementById("t_average_14hours_compare").className = "text-xs mt-1 text-" + objTimeCur.average_14days.color + "-600";

		// 7 Day Average
		document.getElementById("t_average_7days").innerText = objTimeCur.average_7days.value + " hours";
		document.getElementById("t_average_7days_compare").innerText = objTimeCur.average_7days.compare;
		document.getElementById("t_average_7days_compare").className = "text-xs mt-1 text-" + objTimeCur.average_7days.color + "-600";
	}

	changeTatType(tat_type);
	if (isShowReport) {
		$("#report_analysis").show();
	} else {
		$("#report_analysis").hide();
	}

	jQuery(function($) {
		$("#tat").change(function() {
			var val_sel = $(this).val();
			changeTatType(val_sel);
			if (isShowReport && val_sel != "tat") tr_load_turnaround();
		});
	});
</script>