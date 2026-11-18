<?php
global $wpdb, $current_role;

$export_func    = $_REQUEST['export_func'] ?? '';
$export_type    = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : 'csv';

$rows = [];

$datetimenow = date_i18n('Y-m-d H:i:s');
$past_24h = date('Y-m-d H:00:00', strtotime($datetimenow) - 3600 * 24);

$from_date = date_i18n('Y-m-d');
if (isset($_GET['date'])) {
    $from_date = $_GET['date'];
}
$to_date = $from_date . ' 23:59:59';
$today_batch_id = date('Ymd', strtotime($from_date));
$archive_date = tr_get_option('ci_pdf_data', 'archive_date', '');
$days_of_operation_lab = CI_Base_Lab::get_lab_operations();
$ci_all_btypes = tr_pdd_get_batch_types();
$selected_btype = isset($_REQUEST['btype']) ? trim((string) $_REQUEST['btype']) : '';
if ($selected_btype !== '' && !in_array($selected_btype, $ci_all_btypes, true)) {
    $selected_btype = '';
}

function get_panels_and_tests($ci_all_btypes)
{
    global $wpdb;

    $all_panels = [];
    $all_tests = [];

    foreach ($ci_all_btypes as $btype) {
        // Get test panels for tox type
        if ($btype == 'tox') {
            $sql_tests = "SELECT te_group_id, te_name FROM ci_test_panels WHERE te_type = '{$btype}'";

            if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
                $cache = new Tr_Cache_Query();
                $rs = $cache->get_results($sql_tests, OBJECT, 300);
            } else {
                $rs = $wpdb->get_results($sql_tests);
            }

            foreach ($rs as $r) {
                $all_tests[$btype][strtoupper($r->te_name)] = $r->te_group_id;
            }
        }
        if ($btype == 'study') {
            $sql_study = "SELECT ID as tg_ID, abrev as tg_name FROM ci_study_tests ORDER BY ID ASC";

            if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
                $cache = new Tr_Cache_Query();
                $rs = $cache->get_results($sql_study, OBJECT, 300);
            } else {
                $rs = $wpdb->get_results($sql_study);
            }

            foreach ($rs as $r) {
                mt_srand((int) $r->tg_ID);
                $random_color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
                mt_srand();
                $key = $btype . '_' . $r->tg_ID;
                $all_panels[$key] = [
                    'name'       => trim($r->tg_name),
                    'label_name' => '',
                    'bgcolor'    => $random_color
                ];
            }
            continue;
        }

        // Get test groups
        $sql_groups = "
                SELECT tg_ID, tg_name, tg_bgcolor, tg_label_name
                FROM ci_test_groups
                WHERE tg_type = '{$btype}'
                AND tg_parent = 0
                AND tg_show_req = 1
                ORDER BY tg_position ASC
            ";

        if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
            $cache = new Tr_Cache_Query();
            $rs = $cache->get_results($sql_groups, OBJECT, 300);
        } else {
            $rs = $wpdb->get_results($sql_groups);
        }

        foreach ($rs as $r) {
            $label_name = isset($r->tg_label_name) ? trim($r->tg_label_name) : '';
            $key = $btype . '_' . $r->tg_ID;
            $all_panels[$key] = [
                'name'     => trim(str_replace('(new)', '', $r->tg_name)),
                'label_name' => $label_name,
                'bgcolor'  => $r->tg_bgcolor ?: '#999999'
            ];
        }
    }

    return ['panels' => $all_panels, 'tests' => $all_tests];
}

function extract_group_ids($panel_tests_requested, $btype, $all_tests)
{
    $groupsids = [];

    foreach (explode(',', $panel_tests_requested) as $t) {
        $t = trim($t);

        if (stripos($t, 'GROUP_') !== false) {
            $rawgid = str_replace('GROUP_', '', $t);
            $gid = $btype . '_' . $rawgid;
            if (!in_array($gid, $groupsids)) {
                $groupsids[] = $gid;
            }
        } elseif ($btype == 'tox' && isset($all_tests['tox'][strtoupper($t)])) {
            $rawgid = $all_tests['tox'][strtoupper($t)];
            $gid = $btype . '_' . $rawgid;
            if (!in_array($gid, $groupsids)) {
                $groupsids[] = $gid;
            }
        } elseif ($btype == 'study' && is_numeric($t)) {
            $gid = $btype . '_' . $t;
            if (!in_array($gid, $groupsids)) {
                $groupsids[] = $gid;
            }
        }
    }

    return $groupsids;
}

function build_distributed_percentages($panel_stats, $value_key, $column_total)
{
    $percentages = [];
    $column_total = (float) $column_total;
    if ($column_total <= 0) {
        foreach ($panel_stats as $pid => $p) {
            $percentages[$pid] = 0;
        }
        return $percentages;
    }

    $remainders = [];
    $sum_base = 0;
    foreach ($panel_stats as $pid => $p) {
        $value = (float) ($p[$value_key] ?? 0);
        $exact = ($value / $column_total) * 100;
        $base = (int) floor($exact);
        $percentages[$pid] = $base;
        $remainders[$pid] = $exact - $base;
        $sum_base += $base;
    }

    $remaining = 100 - $sum_base;
    if ($remaining > 0) {
        arsort($remainders, SORT_NUMERIC);
        $ordered_ids = array_keys($remainders);
        $count_ids = count($ordered_ids);
        $cursor = 0;
        while ($remaining > 0 && $count_ids > 0) {
            $pid = $ordered_ids[$cursor % $count_ids];
            $percentages[$pid]++;
            $remaining--;
            $cursor++;
        }
    } elseif ($remaining < 0) {
        asort($remainders, SORT_NUMERIC);
        $ordered_ids = array_keys($remainders);
        $count_ids = count($ordered_ids);
        $cursor = 0;
        $max_iterations = max(1, $count_ids * 200);
        $iterations = 0;
        while ($remaining < 0 && $count_ids > 0 && $iterations < $max_iterations) {
            $pid = $ordered_ids[$cursor % $count_ids];
            if (($percentages[$pid] ?? 0) > 0) {
                $percentages[$pid]--;
                $remaining++;
            }
            $cursor++;
            $iterations++;
        }
    }

    return $percentages;
}
$panelData = get_panels_and_tests($ci_all_btypes);

$all_panels = $panelData['panels'];
$all_tests  = $panelData['tests'];

$query_btypes = ($selected_btype !== '') ? [$selected_btype] : $ci_all_btypes;
foreach ($query_btypes as $btype) {
    $batch_tbl  = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $where_inbound = "ba.batch_id = 0 and ba.batch_status = ''";
    if (!empty($archive_date)) {
        $where_inbound .= " and ba.doc > '{$archive_date}' ";
    }
    $bids = [$today_batch_id];
    if (tr_get_option('ci_pdf_data', 'multi_batch_id')) {
        for ($i = 1; $i <= 10; $i++) {
            $bids[] = $today_batch_id . '-' . $i;
        }
    }
    $bids_str = implode("','", $bids);
    $where_today = "ba.batch_id in ('{$bids_str}')";

    $sql = "SELECT ba.mid, ba.batch_id, ba.batch_status, ba.panel_tests_requested,
        ba.doc, ba.spec_received, ba.lab_ID,
        '{$btype}' as btype,
        re.estatus as estatus
    FROM {$batch_tbl} ba
    LEFT JOIN (
        SELECT patient_id,
               MAX(CASE WHEN estatus = 'C' THEN 'C' ELSE '' END) AS estatus
        FROM {$report_tbl}
        GROUP BY patient_id
    ) re
        ON re.patient_id = ba.assen
    WHERE ba.is_saved = 0 AND (({$where_inbound}) OR ({$where_today}))
    ORDER BY ba.doc ASC";

    $result = $wpdb->get_results($sql, ARRAY_A);

    if (!empty($result)) {
        $rows = array_merge($rows, $result);
    }
}

foreach ($rows as $index => $row) {
    $groupsids = extract_group_ids(
        $row['panel_tests_requested'],
        $row['btype'],
        $all_tests
    );

    $groupsids = array_unique($groupsids);
    sort($groupsids);

    $panel_names = [];
    $panel_colors = [];
    $panel_label_names = [];

    foreach ($groupsids as $gid) {
        if (isset($all_panels[$gid])) {
            $panel_names[]  = $all_panels[$gid]['name'];
            $panel_label_names[]  = $all_panels[$gid]['label_name'] ?? '';
            $panel_colors[] = $all_panels[$gid]['bgcolor'];
        }
    }

    $row['panels_id']        = implode(',', $groupsids);
    $row['panels_name']      = implode(', ', $panel_names);
    $row['panels_label_name'] = implode(', ', $panel_label_names);
    $row['panels_bgcolor']   = implode(', ', $panel_colors);

    $rows[$index] = $row;
}

$panel_stats = [];
$btype_totals = [];
foreach ($ci_all_btypes as $bt) {
    $btype_totals[$bt] = [
        'inbound' => 0,
        'received_total' => 0,
    ];
}
$processing_statuses = ['plated'];
$total_current_inbound = 0;
$total_current_inbound_all = 0;
$total_current_received = 0;
$unknown_panel_id = '__unknown__';
$unknown_panel_name = 'Unknown/No Panel';
$unknown_panel_color = '#9CA3AF';
$received_statuses = ['received', 'in_processing', 'completed'];
$status_totals = [
    'received' => 0,
    'in_processing' => 0,
    'completed' => 0,
];

foreach ($rows as $row) {

    $is_inbound = ((int) $row['batch_id'] === 0 && (string) $row['batch_status'] === '');
    if ($is_inbound) {
        $total_current_inbound_all++;
    }

    $panel_ids    = [];
    $panel_names  = [];
    $panel_label_names = [];
    $panel_colors = [];
    if (!empty($row['panels_id'])) {
        $panel_ids    = explode(',', $row['panels_id']);
        $panel_names  = explode(', ', $row['panels_name']);
        $panel_label_names  = explode(', ', $row['panels_label_name']);
        $panel_colors = explode(', ', $row['panels_bgcolor']);
    }

    // xác định trạng thái batch (align inbound logic with top_boxes)
    $status = 'inbound';
    if ($is_inbound) {
        if (!empty($row['doc']) && $row['doc'] >= $not_received_cutoff) {
            $completed_time = date_i18n('Y-m-d H:i:s');
            $age_in_hours = floor(CI_Base_Lab::calculate_business_hours($row['doc'], $completed_time, (int) $row['lab_ID'], $days_of_operation_lab));
            if ($age_in_hours >= 24) {
                $status = 'not_received';
            }
        }
    } else {
        if (!empty($row['estatus']) && $row['estatus'] === 'C') {
            $status = 'completed';
        } elseif (!empty($row['batch_status']) && in_array($row['batch_status'], $processing_statuses, true)) {
            $status = 'in_processing';
        } else {
            $status = 'received';
        }
    }

    if ($status === 'inbound') {
        $total_current_inbound++;
        if (!empty($row['btype']) && isset($btype_totals[$row['btype']])) {
            $btype_totals[$row['btype']]['inbound']++;
        }
    } elseif (isset($status_totals[$status])) {
        $status_totals[$status]++;
        $total_current_received++;
        if (!empty($row['btype']) && isset($btype_totals[$row['btype']])) {
            $btype_totals[$row['btype']]['received_total']++;
        }
    }

    $primary_panel = null;
    if (!empty($panel_ids)) {
        foreach ($panel_ids as $k => $pid) {
            $pid = trim($pid);
            $panel_name = trim($panel_names[$k] ?? '');
            if ($pid === '' || $panel_name === '') {
                continue;
            }
            $primary_panel = [
                'id' => $pid,
                'name' => $panel_name,
                'label_name' => trim($panel_label_names[$k] ?? ''),
                'display_name' => '',
                'bgcolor' => $panel_colors[$k] ?? '#999999',
            ];
            $primary_panel['display_name'] = ($primary_panel['label_name'] !== '')
                ? $primary_panel['label_name']
                : $primary_panel['name'];
            break;
        }
    }

    if ($primary_panel === null) {
        // "Unknown/No Panel" is a received-only bucket.
        if (!in_array($status, $received_statuses, true)) {
            continue;
        }
        if (!isset($panel_stats[$unknown_panel_id])) {
            $panel_stats[$unknown_panel_id] = [
                'panel_id'     => $unknown_panel_id,
                'panel_name'   => $unknown_panel_name,
                'panel_label_name' => '',
                'panel_display_name' => $unknown_panel_name,
                'bgcolor'      => $unknown_panel_color,
                'total'        => 0,
                'inbound'      => 0,
                'received_total' => 0,
                'received'     => 0,
                'in_processing' => 0,
                'completed'    => 0,
                'not_received' => 0,
            ];
        }
        $panel_stats[$unknown_panel_id]['total']++;
        $panel_stats[$unknown_panel_id][$status]++;
        $panel_stats[$unknown_panel_id]['received_total']++;
    } else {
        $pid = $primary_panel['id'];
        if (!isset($panel_stats[$pid])) {
            $panel_stats[$pid] = [
                'panel_id'    => $pid,
                'panel_name'  => $primary_panel['name'],
                'panel_label_name' => $primary_panel['label_name'],
                'panel_display_name' => $primary_panel['display_name'],
                'bgcolor'     => $primary_panel['bgcolor'],
                'total'        => 0,
                'inbound'      => 0,
                'received_total' => 0,
                'received'     => 0,
                'in_processing' => 0,
                'completed'    => 0,
                'not_received' => 0,
            ];
        }
        if (empty($panel_stats[$pid]['panel_label_name']) && $primary_panel['label_name'] !== '') {
            $panel_stats[$pid]['panel_label_name'] = $primary_panel['label_name'];
            $panel_stats[$pid]['panel_display_name'] = $primary_panel['label_name'];
        }

        $panel_stats[$pid]['total']++;
        $panel_stats[$pid][$status]++;
        if (in_array($status, $received_statuses, true)) {
            $panel_stats[$pid]['received_total']++;
        }
    }
}

$total_current_inbound = $total_current_inbound_all;

$system_totals = [
    'inbound'      => (int) $total_current_inbound,
    'received_total' => (int) $total_current_received,
    'received'     => (int) $status_totals['received'],
    'in_processing' => (int) $status_totals['in_processing'],
    'completed'    => (int) $status_totals['completed'],
    'total'        => (int) ($total_current_inbound + $total_current_received),
    'received_display' => (int) ($status_totals['in_processing'] + $status_totals['completed']),
];

$unknown_total_count = isset($panel_stats[$unknown_panel_id])
    ? (int) ($panel_stats[$unknown_panel_id]['total'] ?? 0)
    : 0;
$include_unknown_panel = $unknown_total_count > 0;

if (!$include_unknown_panel && isset($panel_stats[$unknown_panel_id])) {
    unset($panel_stats[$unknown_panel_id]);
}

if ($include_unknown_panel && isset($panel_stats[$unknown_panel_id])) {
    $unknown = $panel_stats[$unknown_panel_id];
    unset($panel_stats[$unknown_panel_id]);
    $panel_stats[$unknown_panel_id] = $unknown;
}

$inbound_percentages = build_distributed_percentages($panel_stats, 'inbound', $system_totals['inbound']);
$received_percentages = build_distributed_percentages($panel_stats, 'received_total', $system_totals['received_total']);
$in_process_percentages = build_distributed_percentages($panel_stats, 'in_processing', $system_totals['in_processing']);
$completed_percentages = build_distributed_percentages($panel_stats, 'completed', $system_totals['completed']);

if ($export_func == 'load_data') {
    $btype_options = [];
    foreach ($ci_all_btypes as $bt) {
        $has_chart_data = ((int) ($btype_totals[$bt]['inbound'] ?? 0) + (int) ($btype_totals[$bt]['received_total'] ?? 0)) > 0;
        if (!$has_chart_data) {
            continue;
        }
        $btype_options[] = [
            'value' => $bt,
            'label' => tr_pdd_get_typename($bt),
            'inbound' => (int) ($btype_totals[$bt]['inbound'] ?? 0),
            'received_total' => (int) ($btype_totals[$bt]['received_total'] ?? 0),
            'in_process' => (int) ($btype_totals[$bt]['in_processing'] ?? 0),
            'completed' => (int) ($btype_totals[$bt]['completed'] ?? 0),
        ];
    }

    $results = [];
    $results['panel_stats'] = $panel_stats;
    $results['system_totals'] = $system_totals;
    $results['total_current_inbound'] = $total_current_inbound;
    $results['inbound_percentages'] = $inbound_percentages;
    $results['received_percentages'] = $received_percentages;
    $results['btypes'] = array_values($ci_all_btypes);
    $results['btype_options'] = $btype_options;
    $results['selected_btype'] = $selected_btype;
    echo json_encode($results);
    exit;
}

if ($export_func == 'export_report') {
    trpdd_log_user(get_current_user_id(), 'download_report', 'Current Queue Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    if ($export_type === 'pdf') {
        ob_start();
        $outfile = generate_report_filename('DASH', 'CurrentQueue', $from_date, $to_date, 'pdf');

        require TRCPDD_PATH . 'parts/dashboard/current_queue_by_panel_pdf.php';
        $html = ob_get_clean();
        trpdd_build_pdf($html, [
            'size'        => 'letter',
            'orientation' => 'portrait',
            'filename'    => $outfile,
            'download'  => 1,
            'compress' => 1
        ]);
        exit;
    }

    if ($export_type === 'csv') {
        $generated_on = date("l, F d, Y \\a\\t h:i A");

        $outfile = generate_report_filename('DASH', 'CurrentQueue', $from_date, $to_date, 'csv');
        tr_pdd_header('csv', $outfile);
        $f = fopen('php://output', 'w');

        // ==== Executive Summary ====
        fputcsv($f, ["Generated on:" . date_i18n("l, F d, Y \\a\\t h:i A")]);
        fputcsv($f, [""]);
        fputcsv($f, ["Panel", "Inbound", '%', "Received", '%', "In Process", '%', "Completed", '%']);
        foreach ($panel_stats as $p) {
            $received_display = $p['received_total'];
            $inbound_pct = (int) ($inbound_percentages[$p['panel_id']] ?? 0);
            $received_pct = (int) ($received_percentages[$p['panel_id']] ?? 0);
            $in_process_pct = (int) ($in_process_percentages[$p['panel_id']] ?? 0);
            $completed_pct = (int) ($completed_percentages[$p['panel_id']] ?? 0);
            $panel_export_name = !empty($p['panel_display_name']) ? $p['panel_display_name'] : $p['panel_name'];
            fputcsv($f, [
                $panel_export_name,
                $p['inbound'],
                $inbound_pct . '%',
                $received_display,
                $received_pct . '%',
                $p['in_processing'],
                $in_process_pct . '%',
                $p['completed'],
                $completed_pct . '%'
            ]);
        }

        $system_inbound_pct = ((int) $system_totals['inbound'] > 0) ? 100 : 0;
        $system_received_pct = ((int) $system_totals['received_total'] > 0) ? 100 : 0;
        $system_in_process_pct = ((int) $system_totals['in_processing'] > 0) ? 100 : 0;
        $system_completed_pct = ((int) $system_totals['completed'] > 0) ? 100 : 0;
        fputcsv($f, [
            'System Totals',
            $system_totals['inbound'],
            $system_inbound_pct . '%',
            $system_totals['received_total'],
            $system_received_pct . '%',
            $system_totals['in_processing'],
            $system_in_process_pct . '%',
            $system_totals['completed'],
            $system_completed_pct . '%'
        ]);
        fclose($f);
        exit;
    }
}
