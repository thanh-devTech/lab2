<?php
global $wpdb, $current_role;
$schedule_report = new CI_Report_Schedule('positive_negative', 'Positive/Negative Report');
include_once(TRCPDD_PATH . 'pages/formdata.php');

$pos_neg_vls = array(
    '' => '',
    'POS' => 'Detected',
    'NEG' => 'Not-Detected',
    'POL' => 'Detected-Low',
    'POM' => 'Detected-Med',
    'POH' => 'Detected-High',
    'PVL' => 'Detected-Low',
    'PVH' => 'Detected-High'
);
$POS_CODES = array('POS', 'POH', 'POM', 'POL', 'PVL', 'PVH');

$now_ts = current_time('timestamp'); // timezone WP

$sel_month = !empty($_REQUEST['month'])
    ? $_REQUEST['month']
    : date_i18n('Y-m', current_time('timestamp'));

$from_date = date('Y-m-01', strtotime($sel_month));
$to_date   = date('Y-m-t',  strtotime($sel_month));

$filter_date_select = [];
for ($i = 0; $i < 6; $i++) {
    $ts    = strtotime("-{$i} months", current_time('timestamp'));
    $value = date('Y-m', $ts);
    $text  = date('F Y', $ts); // May 2026, April 2026, ...
    $filter_date_select[] = ['value' => $value, 'text' => $text];
}
$available_btypes = array_values(array_filter(
    tr_pdd_get_batch_types(),
    function ($b) {
        return !in_array($b, ['gene', 'study', 'dna']);
    }
));


$detected_only  = isset($_REQUEST['detected_only']) ? (int) $_REQUEST['detected_only'] : 1;
$trajax         = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$btype_selected = isset($_REQUEST['btype']) && !empty($_REQUEST['btype'])
    ? $_REQUEST['btype']
    : ($available_btypes[0] ?? 'pcr');

$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$sel_panel = isset($_REQUEST['panel_ID']) ? (array) $_REQUEST['panel_ID'] : [];
$cli_IDs = isset($_REQUEST['cli_ID']) ? (array) $_REQUEST['cli_ID'] : [];
$sel_test = isset($_REQUEST['sel_test']) ? (array) $_REQUEST['sel_test'] : [];


$sel_panel = array_filter($sel_panel, function ($v) {
    return $v !== '' && $v !== null && $v !== 'all';
});

$cli_IDs = array_filter($cli_IDs, function ($v) {
    return $v !== '' && $v !== null;
});

$sel_test = array_filter($sel_test, function ($v) {
    return $v !== '' && $v !== null;
});


$orderby = isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'doc';
if ($orderby == 'patient_name') {
    $orderby = 'ba.patient_name';
} else if ($orderby == 'age') {
    $orderby = 'pa.pa_dob';
}

if (empty($btype_selected)) {
    $btypes = tr_pdd_get_batch_types();
    $btype_selected = array_shift($btypes);
}


$clinics = array();

if (!function_exists('getClinicNameById')) {
    function getClinicNameById($clinics, $cID)
    {
        foreach ($clinics as $clinic) {
            if ((string)$clinic->cID === (string)$cID) {
                return $clinic->cli_clinic;
            }
        }
        return null; // Or 'N/A'
    }
}

if (!function_exists('filter_reports_php')) {
    function filter_reports_php(
        array $reports,
        array $sel_clinic = [],
        array $sel_panel = [],
        array $sel_test = [],
        bool $detected_only = false,
        array $POS_CODES = ['POS', 'POH', 'POM', 'POL', 'PVL', 'PVH']
    ): array {

        $filtered = [];

        foreach ($reports as $row) {

            $has_positive = false;
            $hasdata = empty($sel_test);
            $posmap = $row->posmap ?? [];

            if (!empty($sel_clinic) && !in_array('all', $sel_clinic, true)) {
                if (!in_array((string)$row->cli_ID, array_map('strval', $sel_clinic), true)) {
                    continue;
                }
            }

            if (!empty($sel_panel)) {
                if (empty(array_intersect(
                    array_map('strval', $row->panel_ids ?? []),
                    array_map('strval', $sel_panel)
                ))) {
                    continue;
                }
            }

            foreach ($sel_test as $tid) {

                $tids = explode('-', (string)$tid);
                $group_has_data = false;
                $group_has_positive = false;

                foreach ($tids as $tid_part) {
                    if (!empty($posmap[$tid_part])) {
                        $group_has_data = true;

                        if (in_array($posmap[$tid_part], $POS_CODES, true)) {
                            $group_has_positive = true;
                        }
                    }
                }

                if ($group_has_data) {
                    $hasdata = true;
                }
                if ($group_has_positive) {
                    $has_positive = true;
                    $row->is_pos = true;
                }
            }

            if ($detected_only && !$has_positive) {
                continue;
            }

            if (!$hasdata) {
                continue;
            }

            $filtered[] = $row;
        }

        return array_values($filtered);
    }
}

$user_id = get_current_user_id();
$labs = CI_Base_Lab::get_all_labs();
$ref_labs     = array_values(array_filter($labs, function ($l) {
    return !empty($l->lab_is_ref);
}));
$has_ref_labs = count($ref_labs) > 0 && count($labs) >= 2;
$sel_lab_ID  = isset($_REQUEST['lab_ID']) ? $_REQUEST['lab_ID'] : '';

$conditions = trpdd_get_batch_conditions(0);
$where = '1=1';
$where_clinic = $conditions['where_clinic'];

$clinics = $wpdb->get_results("SELECT * FROM ci_clinic WHERE {$where_clinic} ORDER BY cli_clinic ASC");


$test_type_str = "'{$btype_selected}'";

$rs_groups = $wpdb->get_results("
    SELECT tg_ID, 
    REPLACE(tg_name,'(new)','') as tg_name,
    tg_only_group
    FROM ci_test_groups
    WHERE tg_parent = 0
    AND tg_show_req = 1
    AND tg_type IN ({$test_type_str})
    ORDER BY tg_name ASC
");

$all_panels = array();
$group_tests = array();
foreach ($rs_groups as $i => $panel) {

    $rs_tests = $wpdb->get_results("select te.te_ID, 
        te.te_name,
        te.te_abrev
        from ci_test_panels te
        JOIN ci_test_groups g ON te.te_group_id = g.tg_ID
        where (te_group_id = {$panel->tg_ID} or g.tg_parent = {$panel->tg_ID})
        and te.te_in_req = 1 
        and te.te_is_control = 0
        order by te.te_name asc");

    foreach ($rs_tests as $t) {
        $tkey = strtolower($t->te_name);
        $t->is_ecoli = false;
        if (stripos($t->te_name, 'escherichia coli') !== false || stripos($t->te_abrev, 'E. coli') !== false) {
            $t->is_ecoli = true;
        }
        if (isset($group_tests[$tkey])) {
            $group_tests[$tkey]->te_IDs[] = $t->te_ID;
            $group_tests[$tkey]->tg_IDs[] = $panel->tg_ID;
        } else {
            $t->te_IDs = array($t->te_ID);
            $t->tg_IDs = array($panel->tg_ID);
            $group_tests[$tkey] = $t;
        }
    }

    if ($panel->tg_only_group) {
        $panel->testids = $wpdb->get_col("select te_ID from ci_test_panels where te_group_id = {$panel->tg_ID} and te_in_req = 1 and te_is_control = 0");
    }

    $all_panels[$panel->tg_ID] = $panel;
}
$reflex_target_groups = $wpdb->get_col(
    "SELECT DISTINCT tg_reflex_group_ID
    FROM ci_test_groups 
    WHERE tg_reflex_group_ID IS NOT NULL 
    AND tg_reflex_group_ID != ''
    AND tg_reflex_group_ID != '0'"
);

$groups_with_reflex = array();
foreach ($reflex_target_groups as $rg) {
    $reflex_to_groups = array_filter(array_map('trim', explode(',', $rg)));

    foreach ($reflex_to_groups as $target_gid) {
        $groups_with_reflex[$target_gid] = true;
    }
}
$reflex_test_ids = array();
if (!empty($groups_with_reflex)) {
    $reflex_group_ids_str = implode(',', array_keys($groups_with_reflex));
    $reflex_test_ids = $wpdb->get_col(
        "SELECT DISTINCT te_ID 
        FROM ci_test_panels te
        JOIN ci_test_groups g ON te.te_group_id = g.tg_ID
        WHERE (te.te_group_id IN ({$reflex_group_ids_str}) OR g.tg_parent IN ({$reflex_group_ids_str}))
        AND te.te_in_req = 1 
        AND te.te_is_control = 0"
    );
}


$all_tests = array();
$rs_tests = $wpdb->get_results("select * from ci_test_panels 
            where te_type IN ({$test_type_str}) and te_in_req = 1 and te_is_control = 0 ");
foreach ($rs_tests as $r) {
    $r->is_reflex_test = in_array($r->te_ID, $reflex_test_ids);
    $all_tests[$r->te_ID] = $r;
    $all_tests[strtolower($r->te_name)] = $r;
}

$order = isset($_REQUEST['order']) && $_REQUEST['order'] == 'asc' ? 'asc' : 'desc';

$headers_full = array(
    'doc'          => 'Collection',
    'assen'        => 'Accession',
    'patient_name' => 'Patient Name',
    'pa_mrn'       => 'MRN',
    'pa_dob'       => 'DOB',
    'age'          => 'Age',
    'pa_gender'    => 'Gender',
);

$headers = $headers_full;



$reports        = array();
$total_reports  = 0;
$child_sqls = [];
$list_medications = [];
if ($btype_selected == 'tox') {
    $list_medications = tr_pdd_get_current_medications();
}

if ($trajax == 'loadData' && !empty($_REQUEST['init_only'])) {
    $result = [
        'list_panels' => array_values($all_panels),
        'group_tests' => $group_tests,
        'is_tox' => ($btype_selected == 'tox'),
        'list_medications' => $list_medications,
    ];
    echo json_encode($result);
    exit;
}

if (!empty($export_func) || $trajax == 'loadData') {
    $batch_types = [$btype_selected];


    if ($from_date && $to_date) {
        $child_sql = array();

        foreach ($batch_types as $bt) {
            $batch_tbl = tr_pdd_get_table_by_btype($bt);
            $report_tbl = tr_pdd_get_table_report_by_btype($bt);
            $result_tbl = tr_pdd_get_table_result_by_btype($bt);

            $adding_tests = array();

            $where = trpdd_abx_query_date_range($from_date, $to_date);
            $where .= " and re.estatus = 'C' ";

            if (count($cli_IDs) > 0 && !in_array('all', $cli_IDs)) {
                $cli_IDstr = implode(',', $cli_IDs);
                $where .= " and ba.cli_ID in ({$cli_IDstr}) ";
            }
            if ($has_ref_labs && !empty($sel_lab_ID)) {
                $is_ref = stripos($sel_lab_ID, 'ref') !== false;
                $lid = str_replace('_ref', '', $sel_lab_ID);
                if ($is_ref) {
                    $where .= " and ( ba.ref_lab_ID = {$lid} ) ";
                } else {
                    $where .= " and (ba.lab_ID = {$lid} and ba.ref_lab_ID = 0 ) ";
                }
            }
            $sel_meds = isset($_REQUEST['meds']) ? (array)$_REQUEST['meds'] : [];
            $sel_meds = array_filter($sel_meds, function ($v) {
                return $v !== '' && $v !== null;
            });

            if ($bt == 'tox' && count($sel_meds) > 0) {
                $wheremeds = [];
                foreach ($sel_meds as $med) {
                    $med_escaped = esc_sql($med);
                    $wheremeds[] = " FIND_IN_SET('{$med_escaped}', ba.current_medication) ";
                }
                $where .= " AND (" . implode(' OR ', $wheremeds) . ") ";
            }
            if ($bt == 'pcr') {
                $has_uti_column = 're.has_uti';
            } else {
                $has_uti_column = 'NULL as has_uti';
            }
            $phase_join   = ($bt == 'tox') ? "LEFT JOIN {$batch_tbl}_meta me ON me.mid = ba.mid AND me.meta_key = 'treatment'" : '';
            $phase_column = ($bt == 'tox') ? "CONVERT(me.meta_value, SIGNED) as phase," : "NULL as phase,";

            $child_sql = "select 
                ba.cli_ID,
                ba.doc, 
                ba.assen, 
                ba.mid,
                ba.panel_tests_requested,
                pa.pa_first,
                pa.pa_last,
                pa.pa_dob,
                pa.pa_mrn,
                pa.pa_gender,
                re.id,
                re.has_pos,
                {$has_uti_column},
                {$phase_column}
                (   SELECT GROUP_CONCAT(
                    drug, '|',
                    conc, '|',
                    `pos-neg`
                    )
                    FROM {$result_tbl} where report_id = re.id group by report_id) as results,
                '{$bt}' as btype
                from {$batch_tbl} ba
                JOIN {$report_tbl} re ON re.patient_id = ba.assen and re.estatus = 'C'
                JOIN ci_patients pa ON pa.pa_ID = ba.patient_id 
                {$phase_join}
                where {$where}
                group by ba.mid
                ";

            $child_sqls[] = $child_sql;
        }

        $sql = "SELECT * FROM (" . implode(' UNION ALL ', $child_sqls) . ") as ba
        ORDER BY {$orderby} {$order}";

        $reports = $wpdb->get_results($sql);

        $positive_reports  = 0;
        $total_reports = 0;

        foreach ($reports as $i => $row) {

            $row->pos_neg_vls = $pos_neg_vls;

            if (in_array($row->btype, array('tox', 'blood'))) {
                $row->pos_neg_vls['POS'] = 'Positive';
                $row->pos_neg_vls['NEG'] = 'Negative';
            }

            $row->panels = array();
            $row->panel_ids = array();

            foreach ($headers as $k => $col) {
                if ($k == 'doc' || $k == 'pa_dob') {
                    $row->{$k} = date('m/d/Y', strtotime($row->{$k}));
                } else if ($k == 'age') {
                    $row->{$k} = trdd_dob2ages($row->pa_dob);
                } else if ($k == 'patient_name') {
                    $row->{$k} = $row->pa_last . ', ' . $row->pa_first;
                } else if ($k == 'phase') {
                    $row->{$k} = !empty($row->phase) ? $row->phase : '';
                }
            }

            $res = explode(',', $row->results);

            $resultarr = array();
            foreach ($res as $re) {
                if (empty($re)) {
                    if (isset($_GET['debug1'])) {
                        echo 'empty re1 <br>';
                    }
                    continue;
                }
                $a = explode('|', $re);
                $resultarr[strtolower($a[0])] = [
                    'conc'  => $a[1] ?? '',
                    'pos'   => $a[2] ?? ''
                ];
            }
            $resarr = array();
            $testids = array();
            $gid = '';
            foreach (explode(',', $row->panel_tests_requested) as $t) {
                if (stripos($t, 'GROUP_') !== false) {
                    //is group
                    $gid = str_ireplace('GROUP_', '', $t);
                    $group = $all_panels[$gid] ?? null;

                    if ($group) {
                        if ($group->tg_only_group) {
                            $testids = array_merge($testids, $group->testids);
                        }

                        $row->panel_ids[] = $gid;
                        $row->panels[] = $group->tg_name;
                    }
                } else {
                    $t = strtolower($t);
                    $testids[] = $t;
                }
            }

            $posmap = [];
            $row_detected_tests = [];
            foreach ($testids as $t) {
                $test_name = '';
                if (isset($all_tests[$t])) {

                    $testobj = $all_tests[$t];
                    $tk = strtolower($testobj->te_name);
                    $test_name = $testobj->te_name;
                    if ($testobj->is_reflex_test) {
                        if (empty($row->has_pos)) {
                            if ($row->btype == 'pcr') {
                                if (empty($row->has_uti)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }

                    if ($btype_selected != 'tox') {
                        $drugkey = $tk;
                        if (!isset($resultarr[$drugkey]) && !empty($testobj->te_abrev)) {
                            $drugkey = strtolower($testobj->te_abrev);
                        }
                        if (!isset($resultarr[$drugkey]) && !empty($testobj->te_abrev2)) {
                            $drugkey = strtolower($testobj->te_abrev2);
                        }
                    } else {
                        //tox
                        $drugkey = $t;
                    }
                } else {
                    if (isset($_GET['debug1'])) {
                        echo 'not found test ' . $t . ' <br>';
                    }
                    continue;
                }

                $rsarr = isset($resultarr[$drugkey]) ? $resultarr[$drugkey] : array();
                $pos_neg = '';
                $conc = '';
                if (empty($tk)) {
                    continue;
                } else if (!empty($rsarr) && !isset($resarr[$tk])) {
                    $pos_neg = $rsarr['pos']  ?? '';
                    $conc    = $rsarr['conc'] ?? '';

                    $resarr[$tk] = (isset($row->pos_neg_vls[$pos_neg]) ? $row->pos_neg_vls[$pos_neg] : $pos_neg);
                } else {
                    continue;
                }

                if ($btype_selected == 'pcr') {
                    $conc = $row->pos_neg_vls[$pos_neg];
                }
                $row->{'result_' . $tk} = $conc;
                $row->{'pos_neg_' . $tk} = $pos_neg;

                if (!empty($test_name)) {

                    $posmap[$t] = $pos_neg;
                    // mỗi report chỉ tính 1 lần / test
                    if (!isset($row_detected_tests[$test_name])) {
                        $row_detected_tests[$test_name] = $pos_neg;
                    }
                }
            }

            $row->tests = json_encode($resarr);
            $row->tests_id = $testids;


            // ===== tăng số report POS =====
            $total_reports++;
            if ($row->has_pos) {
                $positive_reports++;
            }

            $row->posmap = $posmap;
            $reports[$i] = $row;
        }
    }

    $positivity_rate = $total_reports > 0 ? number_format(($positive_reports / $total_reports) * 100, 2) : 0;

    $group_ids = [];
    foreach ($rs_groups as $g) {
        $group_ids[$g->tg_ID] = true;
    }

    //Table
    $table = '';
    foreach ($reports as $row) {


        $rowhtml = '';
        //data-has_pos="' . $row->has_pos . '"
        $rowhtml .= '<tr data-posmap="' . esc_attr(json_encode($row->posmap)) . '"  
                    data-tests="' . esc_attr(implode(',', $row->tests_id)) . '" 
                    data-phase="' . esc_attr($row->phase ?? '') . '" 
                    data-clinic="' . $row->cli_ID . '" 
                    data-panels="' . esc_attr(implode(',', $row->panel_ids)) . '">';
        $has_pos = false;
        foreach ($headers as $k => $col) {

            $class = in_array($k, array('age', 'pa_gender')) ? ' text-center ' : '';

            $rowhtml .= '<td class=" ' . $class . 'py-2 px-2 text-xs whitespace-nowrap">';
            $after_vl = '';
            if ($k == 'assen') {
                $rowhtml .= '<a href="?tr_action=ci_results_pdf&id=' . $row->id . '&btype=' . $row->btype . '&view=1" target=_blank>';
                $after_vl = '</a>';
            }
            $rowhtml .= $row->{$k};
            $rowhtml .= $after_vl;
            $rowhtml .= '</td>';
        }
        $rowhtml .= '<td  class="text-center">';

        $arr_id_panel = $row->panel_ids;
        foreach ($row->panels as $index => $pn) {
            $panel_id = $arr_id_panel[$index] ?? '';

            // chỉ hiển thị nếu panel_id tồn tại trong rs_groups
            if (!isset($group_ids[$panel_id])) {
                continue;
            }

            $rowhtml .= '<div class="panel_tag" data-panel_id="' . esc_attr($panel_id) . '" style="white-space: nowrap;">'
                . esc_html($pn)
                . '</div>';
        }

        $rowhtml .= '</td>';

        $rowhtml .= '</tr>';
        $pass_row = true;

        if ($pass_row) {
            $table .= $rowhtml;
        }
    }
    //End Table
}

$schedule_report->ajax();

if ($trajax == 'loadData') {
    $result = [
        'total_reports' => $total_reports,
        'positive_reports' => $positive_reports,
        'positivity_rate' => $positivity_rate,
        'table' => $table,
        'list_panels' => array_values($all_panels),
        'group_tests' => $group_tests,
        'is_tox' => ($btype_selected == 'tox'),
        'list_medications' => $list_medications,
    ];
    echo json_encode($result);
    exit;
}

if ($export_func != '') {
    $no_pii_request = isset($_REQUEST['no_pii']) ? (int) $_REQUEST['no_pii'] : 0;
    $export_no_pii  = (!empty($filepath_save_pdf) || !empty($filepath_save_csv))
        ? true
        : (bool) $no_pii_request;

    $pii_headers = ['patient_name', 'pa_mrn', 'pa_dob', 'age', 'pa_gender'];

    if ($filepath_save_csv == '' && $filepath_save_pdf == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Positive/Negative Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    }

    $reports = filter_reports_php($reports, $cli_IDs, $sel_panel, $sel_test, $detected_only, $POS_CODES);
    $total_reports = count($reports);
    $positive_reports = 0;
    $data_log = [
        'type' => 'Positive/Negative Report',
        'file_name' => '',
        'total_records' => $total_reports,
        'from_date' => $from_date,
        'to_date' => $to_date,
    ];
    foreach ($reports as $r) {
        if (isset($r->is_pos)) $positive_reports++;
    }
    $positivity_rate = $total_reports > 0 ? number_format(($positive_reports / $total_reports) * 100, 2) : 0;

    //Name for Filters Export
    $cur_name_btype = tr_pdd_get_typename($btype_selected);
    $cur_name_filter_panel = 'All Panels';
    $cur_name_clinic = 'All Clinics';
    $cur_name_test = 'All Targets';
    $cur_name_lab = 'All Labs';
    if ($has_ref_labs && !empty($sel_lab_ID)) {
        foreach ($labs as $l) {
            if ((string)$l->lab_ID === (string)$sel_lab_ID) {
                $cur_name_lab = $l->lab_name;
            }
        }
    }

    //Array name filters
    $names_test = array();

    if (!empty($sel_panel)) {
        $names = [];
        foreach ($sel_panel as $p) {
            foreach ($rs_groups as $r) {
                if ((string)$r->tg_ID === (string)$p) {
                    $names[] = $r->tg_name;
                }
            }
        }
        $cur_name_filter_panel = implode(', ', $names);
    }

    if (!empty($sel_test)) {
        $sel_test_ids = [];
        foreach ($sel_test as $item) {
            if (strpos($item, '-') !== false) {
                $sel_test_ids = array_merge($sel_test_ids, explode('-', $item));
            } else {
                $sel_test_ids[] = $item;
            }
        }
        $sel_test_ids = array_unique(array_map('strval', $sel_test_ids));

        $names_test = [];

        if (!empty($sel_test_ids)) {
            foreach ($group_tests as $t) {
                if (in_array((string)$t->te_ID, $sel_test_ids, true)) {
                    $names_test[] = $t->te_name;
                }
            }
        }
        $cur_name_test = implode(', ', $names_test);
    }

    if (count($cli_IDs) > 0) {
        $names = [];
        foreach ($cli_IDs as $c) {
            $names[] = getClinicNameById($clinics, $c);
        }
        $cur_name_clinic = implode(',', $names);
    }

    if ($export_type == "pdf" || $filepath_save_pdf != "") {
        $orientation = 'portrait';
        $generated_on = date_i18n("m/d/Y \\a\\t h:i A");
        $period_export = $to_date;
        ob_start();
        $outfile = generate_report_filename("RPT", 'PositiveNegative', $period_export, '', 'pdf');

        $sel_test_groups = [];

        foreach ($sel_test as $item) {
            $ids = array_map('strval', explode('-', (string)$item));
            $sel_test_groups[] = $ids;
        }

        $test_stats = [];
        if (!empty($sel_test)) {
            foreach ($reports as $row) {

                if (empty($row->posmap)) continue;

                foreach ($sel_test_groups as $group_ids) {

                    $group_has_data = false;
                    $group_tests_detected = [];

                    foreach ($group_ids as $tid) {
                        if (isset($row->posmap[$tid])) {
                            $group_has_data = true;

                            $pos_neg = $row->posmap[$tid];

                            if (isset($all_tests[$tid])) {
                                $test_name = $all_tests[$tid]->te_name;

                                $group_tests_detected[$test_name] = $pos_neg;
                            }
                        }
                    }

                    if (!$group_has_data) {
                        continue;
                    }

                    foreach ($group_tests_detected as $test_name => $pos_neg) {

                        if (!isset($test_stats[$test_name])) {
                            $test_stats[$test_name] = [
                                'name'     => $test_name,
                                'total'    => 0,
                                'detected' => 0
                            ];
                        }

                        $test_stats[$test_name]['total']++;

                        if (in_array($pos_neg, $POS_CODES, true)) {
                            $test_stats[$test_name]['detected']++;
                        }
                    }
                }
            }
        }
        require TRCPDD_PATH . 'parts/reports/positive_negative_report_pdf.php';
        $html = ob_get_clean();
        if ($filepath_save_pdf != '') {
            if (function_exists('trdomepdf_build_pdf')) {
                $result = trdomepdf_build_pdf($html, [
                    'size' => 'letter',
                    'orientation' => $orientation,
                    'filepath' => $filepath_save_pdf,
                    'download' => 0,
                    'compress' => 0,
                    'dpi' => 110
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
            $data_log['file_name'] = $outfile;
            trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
            exit;
        }
    }

    if ($export_type == "csv" || $filepath_save_csv != "") {

        function csv_safe($value)
        {
            if ($value === null) return '';

            $value = (string)$value;
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
            $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
            if (preg_match('/^[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
            return trim($value);
        }


        $generated_on = date_i18n("m/d/Y \\a\\t h:i A");
        $period_export =  $to_date;
        $outfile = generate_report_filename("RPT", 'PositiveNegative', $period_export, '', 'csv');
        tr_pdd_header('csv', $outfile);
        if ($filepath_save_csv != '')
            $f = fopen($filepath_save_csv, 'w');
        else
            $f = fopen('php://output', 'w');

        /* BOM */
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ["Generated on: - $generated_on"]);
        fputcsv($f, [" "]);
        fputcsv($f, ["Report Parameters"]);
        fputcsv($f, ["Date Range:", date_i18n('M d', strtotime($from_date)) . " - " . date_i18n('M d', strtotime($to_date))]);
        fputcsv($f, ["Test Type:", $cur_name_btype]);
        fputcsv($f, ["Panels:", $cur_name_filter_panel]);
        fputcsv($f, ["Clinic:", $cur_name_clinic]);
        if ($has_ref_labs) {
            fputcsv($f, ["Lab:", $cur_name_lab]);
        }
        fputcsv($f, ["Targets:", $cur_name_test]);
        if ($detected_only == 1)
            fputcsv($f, ["Show Detected Only:", "Yes"]);
        else
            fputcsv($f, ["Show Detected Only:", "No"]);
        fputcsv($f, [" "]);
        fputcsv($f, ["Summary"]);
        fputcsv($f, ["Total Specimens", $total_reports]);
        fputcsv($f, ["Positive Specimens", $positive_reports]);
        fputcsv($f, ["Positivity Rate", $positivity_rate . '%']);
        fputcsv($f, [" "]);
        fputcsv($f, ["Specimen Data"]);
        $h = ["Collection", "Accession"];
        if (!$export_no_pii) {
            $h[] = "Patient Name";
            $h[] = "MRN";
            $h[] = "DOB";
            $h[] = "Age";
            $h[] = "Gender";
        }
        $h[] = "Panel";
        $test_columns_ordered = [];
        foreach ($sel_test as $t) {
            if (strpos($t, '-') !== false) {
                $test_ids = explode('-', $t);
                $first_test_obj = $all_tests[$test_ids[0]] ?? null;
                if ($first_test_obj) {
                    $test_columns_ordered[] = csv_safe($first_test_obj->te_name);
                }
            } else {
                $test_obj = $all_tests[$t] ?? null;
                if ($test_obj) {
                    $test_columns_ordered[] = csv_safe($test_obj->te_name);
                }
            }
        }

        foreach ($test_columns_ordered as $test_name) {
            $h[] = $test_name;
        }
        if ($btype_selected == 'tox') {
            $h[] = "Phase";
        }
        fputcsv($f, $h);
        if ($export_no_pii) {
            $r_overAll = ["% Detected (Overall)", "", ""];
            $r_detected = ["Detected", "", ""];
        } else {
            $r_overAll = ["% Detected (Overall)", "", "", "", "", "", "", ""];
            $r_detected = ["Detected", "", "", "", "", "", "", ""];
        }




        $rows_buffer = [];
        $total_per_test = [];
        $pos_per_test = [];

        foreach ($sel_test as $t) {
            $total_per_test[$t] = 0;
            $pos_per_test[$t] = 0;
        }

        foreach ($reports as $row) {

            $row->pos_neg_vls = $pos_neg_vls;

            if (in_array($row->btype, array('tox', 'blood'))) {
                $row->pos_neg_vls['POS'] = 'Positive';
                $row->pos_neg_vls['NEG'] = 'Negative';
            }

            $has_row_data = false;

            $tests = [];
            if (!empty($row->tests)) {
                $tests = json_decode($row->tests, true);
                if (!is_array($tests)) $tests = [];
            }

            $r = [
                csv_safe($row->doc),
                csv_safe($row->assen),
            ];
            if (!$export_no_pii) {
                $p_name = csv_safe($row->pa_last) . ', ' . csv_safe($row->pa_first);
                $r[] = $p_name;
                $r[] = csv_safe($row->pa_mrn);
                $r[] = csv_safe($row->pa_dob);
                $r[] = csv_safe($row->age);
                $r[] = csv_safe($row->pa_gender);
            }
            if ($btype_selected == 'tox') {
                $r[] = csv_safe($row->phase);
            }
            $r[] = csv_safe(implode(', ', $row->panels));

            $posmap = $row->posmap;

            $is_has_pos = false;

            foreach ($sel_test as $t) {
                $sel_test_ids = [];
                $label = '';
                //is group
                if (strpos($t, '-') !== false) {
                    $sel_test_ids = array_merge($sel_test_ids, explode('-', $t));
                    foreach ($sel_test_ids as $tid) {
                        if (isset($posmap[$tid])) {

                            $pos_key = $posmap[$tid];
                            if (in_array($pos_key, $POS_CODES, true)) {
                                $is_has_pos = true;
                                $pos_per_test[$t]++;
                            }

                            if (!empty($pos_key)) {
                                $label = $row->pos_neg_vls[$pos_key];
                                $has_row_data = true;
                                $total_per_test[$t]++;
                                break;
                            }
                        }
                    }
                    if (trim($label) == '') $label = 'N/A';
                    $r[] = $label;
                } else {
                    if (isset($posmap[$t])) {

                        if (in_array($posmap[$t], $POS_CODES, true)) {
                            $is_has_pos = true;
                            $pos_per_test[$t]++;
                        }

                        $pos_key = $posmap[$t];
                        if (!empty($pos_key)) {
                            $label = $row->pos_neg_vls[$pos_key];
                            $has_row_data = true;
                            $total_per_test[$t]++;
                        }
                    }
                    if (trim($label) == '') $label = 'N/A';
                    $r[] = $label;
                }
            }

            if ($has_row_data) {
                if ($detected_only == 1) {
                    if ($is_has_pos) $rows_buffer[] = $r;
                } else {
                    $rows_buffer[] = $r;
                }
            }
        }

        foreach ($pos_per_test as $t => $pos_count) {
            $total_count = $total_per_test[$t] ?? 0;
            $rate = $total_count > 0 ? number_format(($pos_count / $total_count) * 100, 1) . '%' : '0%';
            $r_overAll[] = $rate;
            $r_detected[] = $pos_count;
        }

        fputcsv($f, $r_overAll);
        fputcsv($f, $r_detected);

        foreach ($rows_buffer as $r) {
            fputcsv($f, $r);
        }
        fclose($f);
        if ($filepath_save_csv == "") {
            $data_log['file_name'] = $outfile;
            trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
            exit;
        }
    }
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
    return;
}

if (!function_exists('icon_sort')) {
    function icon_sort()
    {
        $icon_down_up = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up-down inline h-3 w-3 ml-1 opacity-40" aria-hidden="true"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>';
        $icon_up = '<svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up h-3 w-3 ml-1 text-primary" aria-hidden="true"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>';
        $icon_down = '<svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-down h-3 w-3 ml-1 text-primary" aria-hidden="true"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>';
        return $icon_down_up . $icon_up . $icon_down;
    }
}
?>
<style>
    body .select2-container .select2-selection--single {
        height: 34px;
        min-height: 32px;
        border-radius: 4px;
    }

    .select2-container .select2-selection--multiple .select2-selection__rendered {
        display: block;
    }

    .widefat thead tr th {
        text-align: center;
    }

    table.resulttbl {
        width: 100%;
        margin: 0 auto;
    }

    table.resulttbl tr td {
        width: 33%;
    }


    table tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }

    table tbody tr:nth-child(even) {
        background-color: #f9fafb;
    }

    table tr th {
        white-space: nowrap;
    }

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

    .bg-gray-100 {
        background-color: #f6f3f4 !important;
    }
</style>
<?php $schedule_report->style(); ?>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
    <div class="space-y-6">
        <div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
            <nav aria-label="breadcrumb" data-slot="breadcrumb">
                <ol data-slot="breadcrumb-list" class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">

                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                        <a data-slot="breadcrumb-link"
                            class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                            href="?page=dashboard_main">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
                                <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                <path
                                    d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
                                </path>
                            </svg>
                            <span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span>
                        </a>
                    </li>

                    <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                        class="[&amp;&gt;svg]:size-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-chevron-right">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </li>

                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                        <a data-slot="breadcrumb-link"
                            class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                            href="?page=ci_reports_main">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
                                <path
                                    d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
                                </path>
                                <path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
                                <path d="M10 9H8"></path>
                                <path d="M16 13H8"></path>
                                <path d="M16 17H8"></path>
                            </svg>
                            <span class="truncate max-w-[120px] sm:max-w-none">Reports</span>
                        </a>
                    </li>

                    <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                        class="[&amp;&gt;svg]:size-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-chevron-right">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </li>

                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                        <span
                            data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
                            class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3" aria-hidden="true">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                <path d="M18 17V9"></path>
                                <path d="M13 17V5"></path>
                                <path d="M8 17v-3"></path>
                            </svg>
                            <span class="truncate max-w-[120px] sm:max-w-none">Positive/Negative Report</span>
                        </span>
                    </li>

                </ol>
            </nav>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                    Positive/Negative Report</h1>
                <p class="text-muted-foreground mt-1">Specimen-level detection across panels and targets</p>
            </div>
        </div>
        <div>
            <div class="space-y-5">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <div id="box_count_total_reports" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                            <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Total Specimens</h4>
                            <div class="p-1 md:p-1.5 rounded-lg bg-blue-50">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-test-tube h-3 w-3 md:h-4 md:w-4 text-blue-600" aria-hidden="true">
                                    <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                    <path d="M8.5 2h7"></path>
                                    <path d="M14.5 16h-5"></path>
                                </svg>
                            </div>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                            <p class="text-xs text-muted-foreground">
                                <span id="total_reports" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            </p>
                            <div class="text-xs text-muted-foreground/70 mt-0.5">Across selected filters</div>
                        </div>
                    </div>

                    <div id="box_count_positive_reports" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                            <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Positive Specimens</h4>
                            <div class="p-1 md:p-1.5 rounded-lg bg-orange-50">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-target h-3 w-3 md:h-4 md:w-4 text-orange-600" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <circle cx="12" cy="12" r="6"></circle>
                                    <circle cx="12" cy="12" r="2"></circle>
                                </svg>
                            </div>
                        </div>

                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                            <p class="text-xs text-muted-foreground">
                                <span id="positive_reports" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            </p>
                            <div class="text-xs text-muted-foreground/70 mt-0.5">≥1 positive result</div>
                        </div>
                    </div>

                    <div id="box_count_positivity_rate" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                            <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Positivity Rate</h4>
                            <div class="p-1 md:p-1.5 rounded-lg" style="background-color: rgb(254, 242, 242);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-column-increasing h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: rgb(216, 61, 0);">
                                    <path d="M5 21v-6"></path>
                                    <path d="M12 21V9"></path>
                                    <path d="M19 21V3"></path>
                                </svg>
                            </div>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                            <p class="text-xs text-muted-foreground">
                                <span id="positivity_rate" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            </p>
                            <div class="text-xs text-muted-foreground/70 mt-0.5">% specimens with positive findings</div>
                        </div>
                    </div>

                </div>

                <!---Filters!--->
                <div id="box_filters" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel h-4 w-4 text-primary" aria-hidden="true">
                                    <path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path>
                                </svg>
                                <h4 data-slot="card-title" class="leading-none">Filters</h4>
                            </div>

                            <div class="flex items-center gap-3">
                                <div style="position:relative;display:inline-block;">

                                    <button id="export_report" ; data-slot="dropdown-menu-trigger"
                                        class="inline-flex btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-primary hover:bg-primary/90"
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
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <select id="sel_month_report" name="month">
                                <?php foreach ($filter_date_select as $item): ?>
                                    <option value="<?php echo $item['value']; ?>"
                                        <?php echo $item['value'] === $sel_month ? 'selected' : ''; ?>>
                                        <?php echo $item['text']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select id="sel_test_type" name="btype">
                                <?php
                                foreach (tr_pdd_get_batch_types() as $bt) {
                                    if (in_array($bt, array('gene', 'study', 'dna')))
                                        continue;
                                    $selected = $bt == $btype_selected ? 'selected' : '';
                                    echo '<option value="' . $bt . '" ' . $selected . '>' . tr_pdd_get_typename($bt) . '</option>';
                                }
                                ?>
                            </select>

                            <select name="cli_ID[]" id="clinic_select" multiple style="width:300px" data-multi="1" data-placeholder="All Clinics">
                                <?php
                                echo '<option value="" selected>All Clinics</option>';
                                foreach ($clinics as $cli) {
                                    $selected = in_array($cli->cID, $cli_IDs) ? 'selected' : '';
                                    echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
                                }
                                ?>
                            </select>
                            <?php if ($has_ref_labs): ?>
                                <select name="lab_ID" id="lab_select" style="width:300px" data-placeholder="All Labs">
                                    <option value="0" selected>All Labs</option>
                                    <?php foreach ($labs as $lab):
                                        if ($lab->lab_is_ref == 1) {
                                            $lab->lab_ID .= '_ref';
                                        }
                                    ?>
                                        <option value="<?php echo $lab->lab_ID; ?>"
                                            <?php echo (string)$lab->lab_ID === (string)$sel_lab_ID ? 'selected' : ''; ?>>
                                            <?php echo esc_html($lab->lab_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <div id="box_medication_filter" class="min-w-[200px]" style="display:none;width:300px;">
                                <select id="medication_list" multiple name="meds[]"
                                    data-multi="1"
                                    data-placeholder="All Medications">
                                </select>
                            </div>

                            <div class="flex items-center gap-2">
                                <button id="switch_detected_only" onclick="switch_detected_only();return false;" class="cursor-pointer relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 bg-primary" role="switch" aria-checked="true">
                                    <span id="switch_detected_only_control" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform translate-x-6"></span>
                                </button>

                                <span class="text-sm text-gray-700">Detected only</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button id="switch_no_pii"
                                    onclick="switch_no_pii(); return false;"
                                    class="cursor-pointer relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 bg-primary"
                                    role="switch" aria-checked="true">
                                    <span id="switch_no_pii_control"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform translate-x-6">
                                    </span>
                                </button>
                                <span class="text-sm text-gray-700">No PII</span>
                            </div>
                            <div class="w-full"></div>
                            <div class="flex-1 min-w-[200px]">
                                <select id="list_panels" multiple name="panels[]" style="width:400px" data-multi="1" data-placeholder="Select Panels"></select>
                            </div>

                            <div class="flex-1 min-w-[200px]">
                                <select id="pathogen_list" multiple name="meds[]" data-multi="1" data-placeholder="Select Targets"></select>
                            </div>
                        </div>
                    </div>
                </div>

                <!---End Filters--->

                <div id="box_table" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Positive / Negative Breakdown</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="overflow-x-auto">
                            <table id="table_reports" class="w-full text-xs">
                                <thead>
                                    <tr id="table_reports_head" class="border-b bg-gray-50">
                                        <th class="text-left py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap cursor-pointer hover:bg-gray-100 align-top">
                                            <div class="flex items-start">
                                                <span>Collection</span>
                                                <?php echo icon_sort(); ?>
                                            </div>
                                        </th>
                                        <th class="text-left py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap cursor-pointer hover:bg-gray-100 align-top">
                                            <div class="flex items-start">
                                                <span>Accession</span>
                                                <?php echo icon_sort(); ?>
                                            </div>
                                        </th>
                                        <th class="text-left py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap cursor-pointer hover:bg-gray-100 align-top">
                                            <div class="flex items-start">
                                                <span>Patient Name</span>
                                                <?php echo icon_sort(); ?>
                                            </div>
                                        </th>
                                        <th class="text-left py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap cursor-pointer hover:bg-gray-100 align-top">
                                            <div class="flex items-start">
                                                <span>MRN</span>
                                                <?php echo icon_sort(); ?>
                                            </div>
                                        </th>
                                        <th class="text-left py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap cursor-pointer hover:bg-gray-100 align-top">
                                            <div class="flex items-start">
                                                <span>DOB</span>
                                                <?php echo icon_sort(); ?>
                                            </div>
                                        </th>
                                        <th class="text-center py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap cursor-pointer hover:bg-gray-100 align-top">
                                            <div class="flex items-start justify-center">
                                                <span>Age</span>
                                                <?php echo icon_sort(); ?>
                                            </div>
                                        </th>
                                        <th class="text-center py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap cursor-pointer hover:bg-gray-100 align-top">
                                            <div class="flex items-start justify-center"><span>Gender</span>
                                                <?php echo icon_sort(); ?>
                                            </div>
                                        </th>
                                        <th class="text-center py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap cursor-pointer hover:bg-gray-100 align-top">
                                            <div class="flex items-start justify-center">
                                                <span>Panel</span>
                                                <?php echo icon_sort(); ?>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="all_item_report">
                                    <tr class="bg-gray-100 font-semibold border-b-2 border-gray-300">
                                        <td class="py-2 px-2 text-xs" colspan="8"><span class="text-gray-700">% Detected (Overall)</span></td>
                                        <td class="py-2 px-2 text-xs whitespace-nowrap text-center"><span class="font-semibold text-gray-900">100.0%</span></td>
                                        <td class="py-2 px-2 text-xs whitespace-nowrap text-center"><span class="font-semibold text-gray-900">0.0%</span></td>
                                        <td class="py-2 px-2 text-xs whitespace-nowrap text-center"><span class="font-semibold text-gray-900">0.0%</span></td>
                                        <td class="py-2 px-2 text-xs whitespace-nowrap text-center"><span class="font-semibold text-gray-900">0.0%</span></td>
                                        <td class="py-2 px-2 text-xs whitespace-nowrap text-center"><span class="font-semibold text-gray-900">0.0%</span></td>
                                        <td class="py-2 px-2 text-xs whitespace-nowrap text-center"><span class="font-semibold text-gray-900">0.0%</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
                    <p class="text-xs text-gray-600 text-center"><strong>Ignite Laboratory Information System</strong><br>igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.</p>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    var sel_month = '<?php echo $sel_month; ?>';
    var sel_clinic = <?php echo json_encode($cli_IDs); ?>;
    var has_ref_labs = <?php echo $has_ref_labs ? 'true' : 'false'; ?>;
    var sel_lab_ID = '<?php echo $sel_lab_ID; ?>';
    var sel_panel = <?php echo json_encode($sel_panel); ?>;
    var sel_btype = '<?php echo $btype_selected ?>';
    var sel_test = <?php echo json_encode($sel_test); ?>;
    var url_main_page = 'ci_reports_positive_negative_report';
    var detected_only = !!Number('<?php echo $detected_only; ?>');
    let NO_PII = true;
    let HAS_PANEL_SELECTED = false;
    let HAS_TARGET_SELECTED = false;
    let IS_TOX = (sel_btype === 'tox');
    let sel_medication = [];

    let list_data_panels = null;
    let list_data_tests = null;
    let TEST_NAME_MAP = {};

    let SINGLE_TEST_IDS = [];
    const POS_CODES = ["POS", "POH", "POM", "POL", "PVL", "PVH"];
    const POS_NEG_LABEL = {
        POS: "Detected",
        POL: "Detected-Low",
        POM: "Detected-Med",
        POH: "Detected-High",
        PVL: "Detected-Low",
        PVH: "Detected-High",
        NEG: "Not-Detected",
        "": "Not-Detected"
    };
    const PII_COL_INDICES = [2, 3, 4, 5, 6];

    //Biến để lưu trạng thái các field
    let FILTER_STATE = {
        clinics: [],
        panels: [],
        tests: [],
        detected_only: true
    };


    function renderTestColumns_Filter() {
        console.log("Rendering test columns...", SINGLE_TEST_IDS);

        document.querySelectorAll(".th_head_customer, .td_test_customer")
            .forEach(el => el.remove());

        if (!SINGLE_TEST_IDS || SINGLE_TEST_IDS.length === 0) return;
        const SHOW_PHASE = IS_TOX;
        const headEl = document.getElementById("table_reports_head");

        let headerHTML = SINGLE_TEST_IDS.map(tid => {
            const label = TEST_NAME_MAP[tid] || tid;
            return `<th class="th_head_customer cursor-pointer text-left py-2 px-2 text-[10px] text-gray-600 whitespace-nowrap">
            ${label}
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up-down inline h-3 w-3 ml-1 opacity-40"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
            <svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up h-3 w-3 ml-1 text-primary"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>
            <svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-down h-3 w-3 ml-1 text-primary"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>
        </th>`;
        }).join('');

        if (SHOW_PHASE) {
            headerHTML += `<th class="th_head_customer text-left py-2 px-2 text-[10px] text-gray-600 tracking-wide whitespace-nowrap align-top">Phase</th>`;
        }

        headEl.insertAdjacentHTML('beforeend', headerHTML);

        const statsSingle = {};
        SINGLE_TEST_IDS.forEach(tid => {
            statsSingle[tid] = {
                detected: 0,
                total: 0
            };
        });

        const $percentRow = $("table tbody tr.percent_detected");
        const $countRow = $("table tbody tr.count_detected");

        let total_rows = 0;
        let positive_reports = 0;

        const allRows = document.querySelectorAll("table tbody tr:not(.headerrow)");
        const filterClinics = FILTER_STATE.clinics.length > 0;
        const filterPanels = FILTER_STATE.panels.length > 0;

        const rowCellsMap = new Map(); // el → html string
        const rowVisMap = new Map(); // el → boolean

        allRows.forEach(function(row) {
            const rowClinic = String(row.dataset.clinic || "");
            const rowPanels = String(row.dataset.panels || "").split(",");

            let visible = true;

            if (filterClinics) {
                visible = FILTER_STATE.clinics.includes(rowClinic);
            }
            if (visible && filterPanels) {
                visible = FILTER_STATE.panels.some(p => rowPanels.includes(String(p)));
            }

            let posmap = {};
            try {
                posmap = JSON.parse(row.getAttribute("data-posmap") || "{}");
            } catch (e) {}

            let has_positive = false;
            let hasdata = SINGLE_TEST_IDS.length === 0;

            let cellsHTML = '';

            if (visible) {
                SINGLE_TEST_IDS.forEach(tid => {
                    const tids = tid.split("-");
                    let pos = null;
                    tids.forEach(tid_part => {
                        if (posmap[tid_part] && posmap[tid_part] !== '') pos = posmap[tid_part];
                    });

                    const detected = POS_CODES.includes(pos);
                    if (pos !== null && pos !== '') {
                        statsSingle[tid].total++;
                        hasdata = true;
                    }
                    if (detected) {
                        statsSingle[tid].detected++;
                        has_positive = true;
                    }

                    const label = pos !== null ? (POS_NEG_LABEL[pos] || "Not-Detected") : "N/A";
                    const cls = detected ? "text-red-600 font-semibold" : "text-gray-400";
                    cellsHTML += `<td class="td_test_customer text-center text-xs ${cls}">${label}</td>`;
                });

                if (SHOW_PHASE) {
                    const phase = row.getAttribute("data-phase") || '';
                    cellsHTML += `<td class="td_test_customer text-center text-xs text-gray-600 py-2 px-2">${phase || '—'}</td>`;
                }

                if (hasdata) total_rows++;
                if (has_positive) positive_reports++;
            }

            const finalVisible = visible && hasdata && (!FILTER_STATE.detected_only || has_positive);

            rowCellsMap.set(row, cellsHTML);
            rowVisMap.set(row, finalVisible);
        });

        rowCellsMap.forEach((html, el) => {
            if (html) el.insertAdjacentHTML('beforeend', html);
            el.style.display = rowVisMap.get(el) ? '' : 'none';
        });

        let pctHTML = '';
        let countHTML = '';
        SINGLE_TEST_IDS.forEach(tid => {
            const s = statsSingle[tid];
            const pct = s.total ? ((s.detected / s.total) * 100).toFixed(1) : "0.0";
            pctHTML += `<td title="${s.total}" class="td_test_customer text-center text-xs font-semibold text-gray-600">${pct}%</td>`;
            countHTML += `<td class="td_test_customer text-center text-xs font-semibold text-gray-600">${s.detected}</td>`;
        });

        if (SHOW_PHASE) {
            pctHTML += `<td class="td_test_customer text-center text-xs text-gray-500">—</td>`;
            countHTML += `<td class="td_test_customer text-center text-xs text-gray-500">—</td>`;
        }

        $percentRow[0].insertAdjacentHTML('beforeend', pctHTML);
        $countRow[0].insertAdjacentHTML('beforeend', countHTML);

        const positivity_rate = total_rows > 0 ? (positive_reports / total_rows * 100) : 0;
        document.getElementById("total_reports").textContent = total_rows;
        document.getElementById("positive_reports").textContent = positive_reports;
        document.getElementById("positivity_rate").textContent = positivity_rate.toFixed(1) + "%";

        updateHeaderRowColspan();
        applyNoPII();
    }

    function ui_control_switch(id_control, on, tr_x = '6') {
        if (on) {
            $(id_control).removeClass("bg-gray-300").addClass("bg-primary");
            $(id_control + "_control").removeClass("translate-x-1").addClass("translate-x-" + tr_x);
        } else {
            $(id_control).removeClass("bg-primary").addClass("bg-gray-300");
            $(id_control + "_control").removeClass("translate-x-" + tr_x).addClass("translate-x-1");
        }
    }

    function switch_detected_only() {
        FILTER_STATE.detected_only = !FILTER_STATE.detected_only;
        if (FILTER_STATE.detected_only) detected_only = 1;
        else detected_only = 0;
        ui_control_switch('#switch_detected_only', FILTER_STATE.detected_only);
        renderTestColumns_Filter();
        update_sort_table();
    }

    function updateHeaderRowColspan() {
        const colspan = (NO_PII ? 3 : 8);
        $("table tbody tr.percent_detected td:first-child").attr("colspan", colspan);
        $("table tbody tr.count_detected td:first-child").attr("colspan", colspan);
    }

    function toggleToxUI(isTox) {
        if (isTox) {
            $("#box_medication_filter").show();
        } else {
            $("#box_medication_filter").hide();
            sel_medication = [];
            $("#medication_list").empty();
            refreshCustomSelect("medication_list");
        }
    }

    function applyNoPII() {
        PII_COL_INDICES.forEach(function(i) {
            const nth = i + 1; // CSS nth-child là 1-based
            if (NO_PII) {
                $('#table_reports thead tr th:nth-child(' + nth + ')').hide();
                $('#table_reports tbody tr:not(.headerrow) td:nth-child(' + nth + ')').hide();
            } else {
                $('#table_reports thead tr th:nth-child(' + nth + ')').show();
                $('#table_reports tbody tr:not(.headerrow) td:nth-child(' + nth + ')').show();
            }
        });
    }

    function showEmptyState() {
        $("#total_reports").html("--");
        $("#positive_reports").html("--");
        $("#positivity_rate").html("--");

        $("#all_item_report").html(`
            <tr>
                <td colspan="20" class="text-center py-12 text-gray-400 text-sm">
                    <div class="flex flex-col items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                            class="text-gray-300">
                            <path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/>
                        </svg>
                        <span>Please select a <strong>Panel</strong> and <strong>Target</strong> to load data.</span>
                    </div>
                </td>
            </tr>
        `);
        document.querySelectorAll(".th_head_customer")
            .forEach(el => el.remove());
    }

    function switch_no_pii() {
        NO_PII = !NO_PII;
        ui_control_switch('#switch_no_pii', NO_PII);
        updateHeaderRowColspan();
        applyNoPII();
    }

    function update_sort_table() {
        //Table
        $('#table_reports th').off("click").click(function() {
            var table = $(this).parents('table').eq(0);
            var tbody = table.children('tbody');

            var allRows = tbody.find('tr').toArray();

            //Giữ nguyên hàng đầu tiên
            var firstRow = allRows.shift();
            var secondrow = allRows.shift();

            var rows = allRows;
            var index = $(this).index();
            const currentSortColumn = $('#table_reports th').filter(function() {
                return $(this).data('is-sorting') === true;
            });

            var isSameColumn = currentSortColumn.length > 0 && currentSortColumn.index() === index;

            var asc = isSameColumn ? !$(this).data('asc') : true;

            $('#table_reports th').find(".icon_sort").css("color", "#898787ff");
            $('#table_reports th').find(".lucide-arrow-up-down").addClass("inline").show();
            $('#table_reports th').find(".lucide-arrow-up").removeClass("inline").hide();
            $('#table_reports th').find(".lucide-arrow-down").removeClass("inline").hide();

            $('#table_reports th').data('is-sorting', false);
            $(this).find(".lucide-arrow-up-down").removeClass("inline").hide();
            $(this).find(".icon_sort").css("color", "#313131ff");

            rows.sort(function(a, b) {
                var cellA = $(a).children('td').eq(index);
                var cellB = $(b).children('td').eq(index);

                var A = cellA.text().trim();
                var B = cellB.text().trim();

                //CỘT ĐẦU TIÊN: luôn alphabetically
                if (index === 1) {
                    return asc ?
                        B.localeCompare(A, undefined, {
                            sensitivity: 'base'
                        }) :
                        A.localeCompare(B, undefined, {
                            sensitivity: 'base'
                        });
                }

                //CÁC CỘT KHÁC: ưu tiên numeric nếu có
                const numA = parseFloat(A.replace(/,/g, ''));
                const numB = parseFloat(B.replace(/,/g, ''));

                if (!isNaN(numA) && !isNaN(numB)) {
                    return asc ? numA - numB : numB - numA;
                }

                return asc ?
                    A.localeCompare(B, undefined, {
                        sensitivity: 'base'
                    }) :
                    B.localeCompare(A, undefined, {
                        sensitivity: 'base'
                    });
            });

            //append lại: hàng đầu tiên + các hàng đã sort
            tbody.empty().append(firstRow).append(secondrow).append(rows);

            if (asc) {
                $(this).find(".lucide-arrow-down").addClass('inline').show();
            } else {
                $(this).find(".lucide-arrow-up").addClass('inline').show();
            }

            $(this).data('asc', asc);
            $(this).data('is-sorting', true);
        });
    }

    function collectTestsByPanels(testsData, selectedPanelIDs) {
        const map = {};

        Object.entries(testsData).forEach(([key, test]) => {
            if (selectedPanelIDs && selectedPanelIDs.length > 0) {
                let inpanel = false;
                selectedPanelIDs.forEach(pid => {
                    if (test.tg_IDs.map(String).includes(String(pid))) {
                        inpanel = true;
                    }
                });
                if (!inpanel) return;
            }
            const te_ID = test.te_IDs.join('-');
            if (!map[te_ID]) {
                map[te_ID] = Object.assign({}, test, {
                    te_ID
                });
            }
        });

        return Object.values(map);
    }

    function renderPathogenList() {
        const selectedPanels = ($('#list_panels').val() || []).filter(v => v !== '' && v !== 'all');
        const tests = collectTestsByPanels(list_data_tests, selectedPanels);

        const $pl = $('#pathogen_list');
        $pl.empty().append(
            $('<option>', {
                value: '',
                text: 'Select Targets'
            })
        ).append($('<option>', {
            value: 'all',
            text: 'All Targets'
        }));

        tests.sort((a, b) => a.te_name.localeCompare(b.te_name));

        tests.forEach(t => {
            TEST_NAME_MAP[t.te_ID] = t.te_name;
            $pl.append(
                $('<option>', {
                    value: t.te_ID,
                    text: t.te_name
                })
            );
        });

        refreshCustomSelect("pathogen_list");
        bindPathogenChange();
    }


    function LoadData($onlychangedate) {
        if (!HAS_PANEL_SELECTED || !HAS_TARGET_SELECTED) {
            showEmptyState();
            return;
        }

        showLoading("box_count_total_reports");
        showLoading("box_count_positive_reports");
        showLoading("box_count_positivity_rate");

        showLoading("box_filters");
        showLoading("box_table");

        $("#total_reports").html("--");
        $("#positive_reports").html("--");
        $("#positivity_rate").html("--");
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'loadData',
            cli_ID: sel_clinic,
            btype: sel_btype,
            lab_ID: sel_lab_ID,
            panel_ID: sel_panel,
            sel_test: SINGLE_TEST_IDS,
            detected_only: (detected_only ? 1 : 0),
            month: sel_month,
            meds: sel_medication,
        }, function(response) {
            hideLoading("box_count_total_reports");
            hideLoading("box_count_positive_reports");
            hideLoading("box_count_positivity_rate");
            hideLoading("box_filters");
            hideLoading("box_table");

            var res = JSON.parse(response);
            $("#total_reports").html(res.total_reports);
            $("#positive_reports").html(res.positive_reports);
            $("#positivity_rate").html(res.positivity_rate + "%");

            $("#all_item_report").empty();
            var rowAve = `
                    <tr class="percent_detected headerrow bg-gray-100 font-semibold border-b-2 border-gray-300">
                        <td class="py-2 px-2 text-xs" colspan="8">
                            <span class="text-gray-700">% Detected (Overall)</span>
                        </td>
                    </tr>
                `;
            $("#all_item_report").append(rowAve);
            var rowdetected = `
                    <tr class="count_detected headerrow bg-gray-100 font-semibold border-b-2 border-gray-300">
                        <td class="py-2 px-2 text-xs" colspan="8">
                            <span class="text-gray-700">Detected</span>
                        </td>
                    </tr>
                `;
            $("#all_item_report").append(rowdetected);

            $("#all_item_report").append(res.table);

            updateHeaderRowColspan();
            if ($onlychangedate) {
                renderTestColumns_Filter();
                update_sort_table();
                applyNoPII();
                return;
            }
            list_data_panels = res.list_panels;
            list_data_tests = res.group_tests;
            IS_TOX = res.is_tox || false;
            toggleToxUI(IS_TOX);
            renderTestColumns_Filter();
        });
    }

    function LoadPanels() {
        showLoading("box_filters");

        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'loadData',
            cli_ID: sel_clinic,
            btype: sel_btype,
            month: sel_month,
            meds: sel_medication,
            init_only: 1 // ← chỉ lấy panels/tests, không query data
        }, function(response) {
            hideLoading("box_filters");

            var res = JSON.parse(response);

            list_data_panels = res.list_panels;
            list_data_tests = res.group_tests;
            IS_TOX = res.is_tox || false;
            toggleToxUI(IS_TOX);

            if (IS_TOX && res.list_medications && res.list_medications.length > 0) {
                $("#medication_list").empty().append(
                    $('<option>', {
                        value: '',
                        text: 'All Medications'
                    })
                );
                res.list_medications.forEach(function(med) {
                    $("#medication_list").append(
                        $('<option>', {
                            value: med,
                            text: med
                        })
                    );
                });
                refreshCustomSelect("medication_list");
            }

            $("#list_panels").empty().append(
                $('<option>', {
                    value: '',
                    text: 'Select Panels'
                })
            ).append(
                $('<option>', {
                    value: 'all',
                    text: 'All Panels'
                })
            );
            $.each(res.list_panels, function(_, item) {
                $('#list_panels').append(
                    $('<option>', {
                        value: item.tg_ID,
                        text: item.tg_name
                    })
                );
            });
            refreshCustomSelect("list_panels");

            $('#pathogen_list').empty().append(
                $('<option>', {
                    value: '',
                    text: 'Select Target'
                })
            );
            refreshCustomSelect("pathogen_list");

            showEmptyState();
        });
    }

    function bindPathogenChange() {
        $("#pathogen_list").off("change").on("change", function() {
            const raw = $(this).val() || [];
            const selected = raw.map(String).filter(v => v !== '');

            if (selected.length === 0) {
                SINGLE_TEST_IDS = [];
                sel_test = [];
                HAS_TARGET_SELECTED = false;
                showEmptyState();
                return;
            }

            if (selected.includes('all')) {
                const selectedPanels = (sel_panel || []).filter(v => v !== '' && v !== 'all');
                const allTests = collectTestsByPanels(list_data_tests, selectedPanels);
                SINGLE_TEST_IDS = allTests.map(t => t.te_ID);
                sel_test = ['all'];
            } else {
                SINGLE_TEST_IDS = selected;
                sel_test = selected;
            }

            HAS_TARGET_SELECTED = true;
            LoadData(false);
        });
    }

    jQuery(function($) {
        window.$ = jQuery;
        LoadPanels();
        initCustomSelect("select");
        ui_control_switch('#switch_detected_only', detected_only);
        ui_control_switch('#switch_no_pii', NO_PII);
        applyNoPII();

        $("#sel_test_type").change(function() {
            sel_btype = $(this).val();
            IS_TOX = (sel_btype === 'tox');

            sel_medication = [];
            toggleToxUI(IS_TOX);

            HAS_PANEL_SELECTED = false;
            HAS_TARGET_SELECTED = false;
            SINGLE_TEST_IDS = [];
            sel_test = [];
            sel_panel = [];

            showEmptyState();
            LoadPanels();
            return false;
        });

        $("#list_panels").off("change").on("change", function() {
            const raw = ($(this).val() || []).filter(v => v !== '');
            const selected = raw.includes('all') ? [] : raw;
            FILTER_STATE.panels = selected;
            sel_panel = raw;
            HAS_PANEL_SELECTED = raw.length > 0;
            HAS_TARGET_SELECTED = false;
            SINGLE_TEST_IDS = [];
            sel_test = [];

            if (!HAS_PANEL_SELECTED) {
                $("#pathogen_list").off("change");
                $('#pathogen_list').empty().append($('<option>', {
                    value: '',
                    text: 'Select Target'
                }));
                refreshCustomSelect("pathogen_list");
                bindPathogenChange();
                showEmptyState();
                return;
            }
            const currentTargetVal = ($('#pathogen_list').val() || []).map(String).filter(v => v !== '');
            const wasAllTarget = currentTargetVal.includes('all') || sel_test.includes('all');

            renderPathogenList();

            if (wasAllTarget) {
                const selectedPanels = selected;
                const allTests = collectTestsByPanels(list_data_tests, selectedPanels);
                SINGLE_TEST_IDS = allTests.map(t => t.te_ID);
                sel_test = ['all'];
                HAS_TARGET_SELECTED = true;

                setTimeout(() => {
                    $('#pathogen_list').val(['all']);
                    refreshCustomSelect("pathogen_list");
                }, 0);

                LoadData(false);
            } else {
                SINGLE_TEST_IDS = [];
                sel_test = [];
                HAS_TARGET_SELECTED = false;
                showEmptyState();
            }
        });

        bindPathogenChange();

        $("#medication_list").on("change", function() {
            sel_medication = ($(this).val() || []).filter(v => v !== '');
            if (HAS_PANEL_SELECTED && HAS_TARGET_SELECTED) {
                LoadData(true);
            }
        });

        $("#clinic_select").on("change", function() {
            FILTER_STATE.clinics = $(this).val() || [];
            sel_clinic = FILTER_STATE.clinics;
            if (HAS_PANEL_SELECTED && HAS_TARGET_SELECTED) {
                LoadData(true);
            }
        });

        if (has_ref_labs) {
            $("#lab_select").on("change", function() {
                sel_lab_ID = $(this).val() || '';
                LoadData(false);
            });
        }

        $('#sel_month_report').on('change', function() {
            const val = $(this).val();
            sel_month = val;
            LoadData(true);
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
            $url += '&month=' + sel_month;
            $url += '&export_func=' + export_func;
            $url += '&btype=' + sel_btype;
            $url += '&no_pii=' + (NO_PII ? 1 : 0);
            if (sel_medication.length > 0) {
                sel_medication.forEach(v => {
                    $url += '&meds[]=' + encodeURIComponent(v);
                });
            }
            sel_clinic.forEach(v => {
                $url += '&cli_ID[]=' + encodeURIComponent(v);
            });
            $url += '&lab_ID=' + encodeURIComponent(sel_lab_ID);
            sel_panel.forEach(v => {
                $url += '&panel_ID[]=' + encodeURIComponent(v);
            });
            SINGLE_TEST_IDS.forEach(v => {
                $url += '&sel_test[]=' + encodeURIComponent(v);
            });
            $url += '&detected_only=' + (detected_only ? 1 : 0);
            var loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0, 'top_right');
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

                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }

                showToast('success', 'Download ready!', 'Your file "' + filename +
                    '" has been downloaded.', 4000, 'top_right');

            } catch (err) {
                showToast('error', 'Error', 'Download failed.', 4000, 'top_right');
            }
        });

    });
</script>
<?php $schedule_report->js(); ?>