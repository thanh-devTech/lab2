<?php
global $wpdb, $current_role;
$user_id = get_current_user_id();
$medis = tr_pdd_get_report_medis();

$code = reset($medis);
$code = ($_REQUEST['med']) ? $_REQUEST['med'] : $code;
$pre = $_REQUEST['pre'];
$month_time = strtotime(date('Y-m-01'));
$year = date_i18n('Y');
$from_date = date('Y-m-01', strtotime('-11 months', $month_time));
$to_date = date_i18n('Y-m-t');
$start_time = microtime(true);

$googledata = array();
$months = array();



if (($_REQUEST['cg']) > 0) {
    $cli_ids  = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 and cli_group_ID = {$_REQUEST['cg']}");
    $cli_ids = implode(',', $cli_ids);
} else if ($_REQUEST['cid'] > 0) {
    $cli_ids = $_REQUEST['cid'];
    if (empty($cli_ids)) {
        return;
    }
} else if (tr_current_role_in(array('office_manager', 'multi_site_manager', 'clinic'))) {
    $cid = get_user_meta($user_id, '_clinic_id', true);
    $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
    $cli_ids = implode(',', $cli_ids);
} else {
    $cli_ids = $_REQUEST['cid'];
    if (empty($cli_ids)) {
        return;
    }
}
$cli_ids = trim($cli_ids);
if (empty($cli_ids)) {
    $cli_ids = 0;
}
$where = '';
$where_ba = '';
if ($cli_ids !== 'all') {
    $where_ba .= " and ba.cli_ID in ({$cli_ids}) ";
}

if (!empty($pre)) {
    if ($pre == 'w_o') {
        $where_ba .= " and ba.current_medication not like '%Vivitrol%' ";
    } else if ($pre == 'just_vivitrol') {
        $where_ba .= " and ba.current_medication like '%Vivitrol%' ";
    }
}


$where .= " and re.estatus='C'";
$where .= " and re.has_{$code} = 1 ";

$sql_all = "select DATE_FORMAT(ba.doc,'%Y-%m') AS mon_doc, 
                    count(re.id) as c, 
                    sum(re.has_{$code}_pos) as pos_count
                from ci_reports re
                join (select ba.assen,ba.doc from ci_batches ba where ba.doc >='{$from_date}' and ba.doc <= '{$to_date}' {$where_ba} ) 
                        as ba ON ba.assen = re.patient_id
                    where 1 {$where} 
                    group by mon_doc ";
$rs_all = $wpdb->get_results($sql_all, ARRAY_A);



$data = array();
foreach ($rs_all as $r) {
    $data[$r['mon_doc']]['POS'] = $r['pos_count'];
    $data[$r['mon_doc']]['NEG'] = max(0, $r['c'] - $r['pos_count']);
}

if (isset($_REQUEST['debug2'])) {
    echo $sql_all;
    echo '<br><br>';
    var_dump($data);
}

$googledata[] = array('Month', 'POS', 'NEG', 'POS', 'NEG');
for ($i = 1; $i <= 12; $i++) {
    $month = date('Y-m', strtotime('-' . (12 - $i) . ' months', $month_time));
    $months[] = $month;

    $pos = intval($data[$month]['POS']);
    $neg = intval($data[$month]['NEG']);

    $googledata[] = array($month, $pos, $neg, $pos, $neg);
}
$run_time = microtime(true) - $start_time;
//echo $run_time;

global $google_chart_load;
$google_chart_load[] = 'drawChart_report_year';
?>
<div>
    <div id="chart_report_12months" style="width:100%;height:500px"></div>
</div>
<script type="text/javascript">
    function drawChart_report_year() {

        var data = google.visualization.arrayToDataTable(<?php echo json_encode($googledata) ?>);

        var options = {
            legend: {
                position: 'top',
                maxLines: 8
            },
            seriesType: 'bars',
            series: {
                2: {
                    type: 'line'
                },
                3: {
                    type: 'line'
                }
            },
            colors: ['red', 'blue', 'orange', 'green'],
        };

        var chart = new google.visualization.ComboChart(document.getElementById('chart_report_12months'));
        chart.draw(data, options);
    }
    <?php if (isset($_REQUEST['tr_action'])): ?>
        drawChart_report_year();
    <?php endif; ?>
</script>