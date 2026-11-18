<?php
global $wpdb, $current_role;
$user_id    = get_current_user_id();
$today      = Tr_Base_Class_V4::date('Y-m-d');
$past_30days = date('Y-m-d', strtotime('today - 30 days'));
$from       = $_REQUEST['from'] ? $_REQUEST['from'] : $past_30days;
$to         = $_REQUEST['to'] ? $_REQUEST['to'] : $today;
$type       = $_REQUEST['type'] ? $_REQUEST['type'] : 'doc';
$btype      = $_REQUEST['btype'] ? $_REQUEST['btype'] : 'tox';
$panel_id   = $_REQUEST['panel_id'] ? $_REQUEST['panel_id'] : 0;
$slid        = isset($_REQUEST['lid']) ? $_REQUEST['lid'] : '';
$export     = isset($_REQUEST['export']);

$panels     = array();
$all_labs     = array();
$totalsbylabs = array();
$total_main_lab = 0;


$rs = $wpdb->get_results("select * from ci_labs");
foreach ($rs as $r) {
    $all_labs[$r->lab_ID] = $r;
}


if ($export) {
    $panels[0] = 'Total Panels';
    $panel_id = 0;
    $rs = $wpdb->get_results("select tg_ID, tg_name, tg_type 
    from ci_test_groups 
    where tg_parent=0 
    and tg_show_req =1
    and tg_type = '{$btype}'
    order by tg_position asc");
    foreach ($rs as $r) {
        $panels[$r->tg_ID] = $r->tg_name;
    }
} else {
    $panels = array($panel_id => '');
}
$total_samples = 0;
$total_hours    = 0;
$count_date     = 0;
$googledata     = array();
$googledata[]   = array('Date', 'Hours');
$field          = $type == 'doc' ? 'ba.doc' : 'ba.spec_received';
$field_name     = $type == 'doc' ? 'doc' : 'spec_received';

$cli_ids        = '';
$where_global   = '';

if (isset($_REQUEST['cg']) && $_REQUEST['cg'] > 0) {
    $cli_ids  = $wpdb->get_col("select cID from ci_clinic where cli_group_ID = {$_REQUEST['cg']}");
    $cli_ids = implode(',', $cli_ids);
} else if (isset($_REQUEST['cid']) && $_REQUEST['cid'] > 0) {
    $cli_ids = isset($_REQUEST['cid']) ? $_REQUEST['cid'] : '';
    if (empty($cli_ids)) {
        return;
    }
} else if (tr_current_role_in(array('office_manager', 'clinic'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $cli_ids = array($current_clinic);
    } else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
        $cid = get_user_meta($user_id, '_clinic_id', true);
        $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
        $cli_ids = implode(',', $cli_ids);
    }
} else if (tr_current_role_in(array('multi_site_manager'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $cli_ids = array($current_clinic);
    } else  if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
        //can see all clinics
    } else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
        $cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
        if (!is_array($cli_ids) || empty($cli_ids)) {
            $cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
            update_user_meta($user_id, '_ms_clinic_ids', $cli_ids);
        }
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

if ($slid == 'all') {
} else if ($slid == 'main') {
    $where_global .= " and ba.ref_lab_ID = 0 ";
} else if ($slid > 0) {
    $where_global .= " and ba.ref_lab_ID = {$slid} ";
}

$batch_tbl  = tr_pdd_get_table_by_btype($btype);
$report_tbl = tr_pdd_get_table_report_by_btype($btype);


foreach ($panels as $panel_id => $panel_name) {

    $total_hours    = 0;
    $count_date     = 0;
    $dates          = array();

    $where = $where_global;
    if ($panel_id > 0) {
        $where .= " and find_in_set('GROUP_{$panel_id}', ba.panel_tests_requested  )";
    } else if ($btype == 'pcr') {
        //remove antigen and rapid test
        $groups = $wpdb->get_col("select tg_ID from ci_test_groups 
                            where (tg_name like '%antigen%' or tg_name like '%rapid%') 
                            and tg_show_req = 1");
        if (count($groups) > 0) {
            foreach ($groups as $gid) {
                $where .= " and not find_in_set('GROUP_{$gid}', ba.panel_tests_requested  )";
            }
        }
    }


    $sql = "select re.id,
                ba.doc, 
                ba.ref_lab_ID, 
                ba.spec_received,
                re.approve_date 
            from {$batch_tbl} ba
            JOIN {$report_tbl} re ON re.patient_id = ba.assen
            where {$field}>='{$from}' and {$field} <= '{$to} 23:59:59' and  re.estatus='C'
            {$where}
            order by {$field} asc";

    if (isset($_GET['debug'])) {
        var_dump($sql);
        echo '<br>';
    }


    $results = $wpdb->get_results($sql);


    foreach ($results as $row) {

        $doc_time   = strtotime($row->{$field_name});
        $approve_time = strtotime($row->approve_date);


        $secs       = $approve_time - $doc_time;
        $dow_doc    = date('N', $doc_time);
        $dow_app    = date('N', $approve_time);
        $hours      = round($secs / 3600, 1);

        if ($dow_app < $dow_doc && $hours > 24) {
            $hours -= 24;
        }

        if ($hours < 3) {
            //$hours = 12;
        }
        if ($hours < 0) {
            $hours = 12;
        }
        $date = date('Y-m-d', $doc_time);

        if (isset($_REQUEST['debug'])) {
            echo $date . '=>' . $row->doc, '  => ', $row->approve_date, ' => ', $hours;
            echo '<br>';
        }




        $dates[$date][] = $hours;

        $total_hours += $hours;
        $count_date++;
        $total_samples++;

        if ($row->ref_lab_ID > 0) {
            $totalsbylabs[$row->ref_lab_ID]++;
        } else {
            $total_main_lab++;
        }
    }

    foreach ($dates as $date => $arr) {
        $hours = array_sum($arr) / count($arr);
        $hours = round($hours, 1);

        $googledata[] = array($date, $hours);
    }

    if (isset($_GET['debug'])) {
        var_dump($googledata);
    }


    if ($count_date == 0 && !$export) {
        echo 'Not found';
        exit;
    }


    if ($count_date > 0) {
        $average = round($total_hours / $count_date, 1);
    } else {
        $average = '';
    }

    if ($average > 0) {
        $exportdata[$panel_name] = $average;
    }
}

if ($export) {
    $lab_id = CI_Base_Lab::get_current_lab_id();
    $labname = $wpdb->get_var("select lab_name from ci_labs where lab_ID = {$lab_id} ");
    $filename  = $labname . ' TAT Report ' . $from . '-' . $to . '.csv';
    tr_pdd_header('csv', $filename);
    $f = fopen('php://output', 'w');

    foreach ($exportdata as $pname => $vl) {
        if ($vl == 0) continue;

        fputcsv($f, array($pname . ' TAT', $vl));
    }
    exit;
}

global $google_chart_load;
$google_chart_load[] = 'drawChart_report_turnaround';

$colors = array(
    'tox' => 'blue',
    'blood' => 'red',
    'pcr' => 'orange',
    'req' => 'blue',
    'gene' => 'blue'
);
$color = $colors[$btype];

$options = array(
    'bar' => array('groupWidth' => '75%'),
    'colors' => array($color)
);

echo '<div class="turnaroundbox">';
echo '<span class="boxitem">Turnaround Average: ' . $average . '</span>';
if (tr_get_option('ci_pdf_data', 'total_turnaroundave')) {
    echo ' <span class="boxitem">Total Samples: ' . $total_samples . '</span>';
    foreach ($totalsbylabs as $lid => $vl) {
        echo ' <span class="boxitem">Ref ' . $all_labs[$lid]->lab_dba_init . ': ' . $vl . '</span>';
    }

    echo ' <span class="boxitem">Main Lab: ' . $total_main_lab . '</span>';
}
echo '</div>';
?>

<div id="turnaround_report_chart" style="height:500px;width:100%"></div>

<script type="text/javascript">
    function drawChart_report_turnaround() {

        var data = google.visualization.arrayToDataTable(<?php echo json_encode($googledata) ?>);

        var options = <?php echo json_encode($options) ?>;

        var chart = new google.visualization.ColumnChart(document.getElementById('turnaround_report_chart'));
        chart.draw(data, options);
    }
    <?php if (isset($_REQUEST['tr_action'])) : ?>
        drawChart_report_turnaround();
    <?php endif; ?>
</script>

<?php
exit;
