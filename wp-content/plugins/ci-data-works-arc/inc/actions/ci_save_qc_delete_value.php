<?php
global $wpdb, $current_role;

$_POST  = tr_pdd_stripslashes_deep($_POST);
$ids    = $_POST['ids'];
$instrument = isset($_REQUEST['instrument']) ? $_REQUEST['instrument'] : '';
$ids    = explode(',', $ids);
$charts = array();

$lab_id = CI_Base_Lab::get_current_lab_id();


$btype      = isset($_POST['btype']) ? $_POST['btype'] : 'tox';

$report_tbl = 'ci_reports';
$result_tbl = 'ci_results';
$conc_field = 'conc';
$id_field   = 'id';

if ($btype != 'tox') {
    $conc_field = 'TestResult';
    $id_field   = 'ID';
    $report_tbl = 'ci_' . $btype . '_qc';
    $result_tbl = 'ci_' . $btype . '_qc_tests';
}

foreach ($ids as $id) {
    if (empty($id)) continue;

    $result = $wpdb->get_row("select * from {$result_tbl} where {$id_field} = {$id}");

    $rs = $wpdb->delete($result_tbl, array($id_field => $id));

    //get chart new
    $report_id = $btype == 'tox' ? $result->report_id : $result->QC_ID;
    $report     = $wpdb->get_row("select * from {$report_tbl} where {$id_field} = {$report_id}");

    $month      = date('Y-m', strtotime($btype == 'tox' ? $report->acquisition_date : $report->DateTime));
    $date_from  = $month . '-1';
    $date_to    = $month . '-31';

    if ($instrument == 'Thunderbolt') {
        $instrument = -33;
    }
    if (!empty($instrument)) {
        $where .= " and re.instrument = '{$instrument}' ";
    }


    if ($btype == 'tox') {
        $where      = " and (re.patient_name='LQC' or re.patient_name='HQC')";
        $user_id = get_current_user_id();
        if ($user_id > 0 && !tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'scientist', 'labmanager', 'lab_tech'))) {
            $where .= " and re.author_id = {$user_id} ";
        }
        $lab_id     = CI_Base_Lab::get_current_lab_id();
        if ($lab_id > 0) {
            $where .= " and re.lab_id = {$lab_id} ";
        } else {
            $where .= " and re.lab_id = 0 ";
        }
        $where .= " and re.acquisition_date >= '{$date_from}' and re.acquisition_date <= '{$date_to}' ";


        $k = stripos($report->patient_name, 'low') !== false || stripos($report->patient_name, 'lqc') !== false ? 'low' : 'high';
        $k = (stripos($report->patient_name, 'mid') !== false || stripos($report->patient_name, 'mqc') !== false) ? 'mid' : $k;

        if ($k == 'low') {
            $where .= " and (re.patient_name='LQC' )";
        } else if ($k == 'high') {
            $where .= " and (re.patient_name='HQC' )";
        } else if ($k == 'mid') {
            $where .= " and (re.patient_name='MQC' )";
        }




        $sql = "select re.*,rs.{$conc_field},rs.id as rs_id,re.acquisition_date as acquisition_date
        from {$report_tbl} re
        join {$result_tbl} rs ON re.id = rs.report_id and rs.drug='{$result->drug}'
        where re.sublab='{$report->sublab}' {$where} group by re.id

        order by re.acquisition_date asc";
    } else {

        $result->drug = $result->TestName;
        $report->sublab = '';

        $k = stripos($report->assen, 'low') !== false || stripos($report->assen, 'lqc') !== false || stripos($report->assen, 'qcl') !== false ? 'low' : 'high';
        $k = (stripos($report->assen, 'mid') !== false || stripos($report->assen, 'mqc') !== false || stripos($report->assen, 'qcm') !== false) ? 'mid' : $k;


        $where .= " and re.DateTime >= '{$date_from}' and re.DateTime <= '{$date_to} 23:59:59' ";

        $sql = "select re.*,rs.{$conc_field},rs.ID as rs_id,re.DateTime as acquisition_date
        from {$report_tbl} re
        join {$result_tbl} rs ON re.ID = rs.QC_ID and rs.TestName='{$result->TestName}'
        where 1 {$where} group by re.id

        order by re.DateTime asc";
    }



    //echo $sql;exit;

    $results = $wpdb->get_results($sql);


    $chart_data = array(
        'id' => sanitize_title($k . $report->sublab . $result->drug),
        'label' => 'Conc. ng/ml',
        'color' => $k == 'low' ? 'blue' : 'red',
        'rsid' => $id,
        'rsvl' => $value,
        'dates' => array()
    );
    foreach ($results as $r) {
        $date = isset($r->acquisition_date) ? $r->acquisition_date : $r->DateTime;
        if ($btype == 'req') {
            $date = date('Y-m-d H:i:s', strtotime($date));
        } else {
            $date = date('Y-m-d', strtotime($date));
        }
        if (in_array($date, $chart_data['dates'])) {
            continue;
        }
        if (in_array($date, $chart_data['dates'])) {
            continue;
        }
        $chart_data['dates'][] = $date;
        $chart_data['values'][] = floatval(number_format($r->conc, 3, '.', ''));
    }
    $charts[] = $chart_data;
}


$display_chart = isset($_REQUEST['display']) ? $_REQUEST['display'] : 'line';

wp_send_json(array('status' => 'ok', 'charts' => $charts, 'charttype' => $display_chart));
exit;
