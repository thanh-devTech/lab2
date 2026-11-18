<?php
$chart = new CI_Chart(array(
    'title' => '',
    'colors' => array('#70d4d6', '#ff5f43'),
    'height' => 250,
));
$chart->setName_Unit('', '');
$chart->showTotal(true);


$fieldpositive = 're.has_uti > 0';
if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $fieldpositive .= ' or re.has_pos > 0';
}

$sql = "
SELECT 
    COUNT(*) AS countreqs,
    SUM(is_positive) AS count_positive
FROM (
    SELECT
        IF(re.estatus='C' AND ({$fieldpositive}), 1, 0) AS is_positive
    FROM ci_batches_pcr ba
    JOIN ci_reports_pcr re ON re.patient_id = ba.assen
    WHERE {$where}
        AND " . trpdd_abx_query_date_range($date_from, $date_to) . "
) AS rs
";



if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
    $cache = new Tr_Cache_Query('reportabx');
    $rs1 = $cache->get_row($sql, OBJECT, 0, $cache_query_seconds);
} else {
    $rs1 = $wpdb->get_row($sql, OBJECT);
}

$numtestsofm = intval($rs1->countreqs);
$pos = intval($rs1->count_positive);
$neg = $numtestsofm - $pos;
$total_p = $pos;
$total_n = $neg;

$pcneg = $numtestsofm > 0 ? number_format($neg / $numtestsofm * 100, 1) : 0;
$pcpos = $numtestsofm > 0 ? number_format($pos / $numtestsofm * 100, 1) : 0;

// Thêm dữ liệu vào chart1
$chart->addData($pcneg . '% Negative', $neg);
$chart->addData($pcpos . '% Positive', $pos);

$chart->setTotal($numtestsofm);

if (isset($_GET['debug'])) {
    var_dump($sql);
}
