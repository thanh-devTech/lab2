<?php

$chart2 = new CI_Chart(array(
    'charttype' => 'column',
    'isStacked' => true,
    'class' => 'centerchar',
    'height' => 266,
    'colors' => array('#70d4d6', '#ff5f43')
));
$chart2->setName_Unit('Month', 'Negative', 'Positive');


$fieldpositive = 're.has_uti > 0';
if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $fieldpositive .= ' or re.has_pos > 0';
}


// --- Chart 2: luôn cố định 6 tháng gần nhất --- 
$thismonthstamp = strtotime(date_i18n('Y-m-01'));
for ($i = 5; $i >= 0; $i--) {
    $mfrom      = date('Y-m-01', strtotime("-{$i} months", $thismonthstamp));
    $mto        = date('Y-m-t', strtotime($mfrom));
    $monthname  = date('M', strtotime($mfrom));

    $sql2       = " SELECT 
        COUNT(*) AS countreqs, 
        SUM(is_positive) AS count_positive 
        FROM ( 
                SELECT IF(re.estatus='C' AND ({$fieldpositive}), 1, 0) AS is_positive 
                FROM ci_batches_pcr ba JOIN ci_reports_pcr re ON re.patient_id = ba.assen 
                WHERE {$where} 
                AND " . trpdd_abx_query_date_range($mfrom, $mto) . "
                
        ) AS rs ";

    $rs2            = $wpdb->get_row($sql2);
    $numtestsofm_m    = intval($rs2->countreqs);
    $pos            = intval($rs2->count_positive);
    $neg            = $numtestsofm_m - $pos;
    $pos_rate       = $numtestsofm_m > 0 ? round($pos / $numtestsofm_m * 100, 1) : 0;
    $neg_rate       = $numtestsofm_m > 0 ? round($neg / $numtestsofm_m * 100, 1) : 0;

    // Thêm dữ liệu vào chart2 
    $chart2->addData($monthname, $neg, $pos, $numtestsofm_m, $pos_rate, $neg_rate);
    $chart2->addMonthData(
        $monthname,
        $pos,
        $neg,
        $pos_rate,
        $neg_rate,
        $numtestsofm_m
    );
    if (isset($_GET['debug2'])) {
        var_dump($sql2);
    }
}
