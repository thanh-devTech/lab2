<?php
global $wpdb;
$table1config = array();

$numtestsofm = 6;
$pc_positive = 50;
$pc_negative = 50;
$monthname = date_i18n('M');
$monthfullname = date_i18n('F');

$from_date = date_i18n('Y-m-01');

$report_name = $tab_title . ' Testing in <span class="montname">' . $monthname . '</span> ' . (!empty($report_object) ? ' - ' . $report_object : '');
$where      = ' ba.is_saved = 0 ' . $conditions['where'];

$chart = new CI_Chart(array(
    'title' => '',
    'colors' => array('#2980b9', '#e74c3c'),
    'height' => 250,
));
$chart->setName_Unit('', '');
$chart->showTotal(true);


$chart2 = new CI_Chart(array(
    'charttype' => 'column',
    'isStacked' => true,
    'class' => 'centerchar',
    'height' => 266,
    'colors' => array('#2980b9', '#e74c3c')
));
$chart2->setName_Unit('Month', 'Negative', 'Positive');

echo '<div class="clear"></div>';
echo '<div class="postbox full" >';
echo '<div class="postbox_wrap">';

echo '<h2>' . $report_name . '</h2>';
echo '<div class="frow abx_numbers" style="margin-top:25px">';

$where_group = array();
foreach ($main_abx_group_gids as $gid) {
    $where_group[] = " FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)  ";
}
$where_group = implode(' or ', $where_group);

$fieldpositive = 're.has_uti > 0';
if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $fieldpositive .= ' or re.has_pos > 0';
}


for ($i = 5; $i >= 0; $i--) {

    $mfrom          = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
    $mto       = date_i18n('Y-m-t', strtotime($mfrom));
    $monthname      = date('M', strtotime($mfrom));
    $sql = "
    select count(*) as countreqs ,
    SUM(is_positive) as count_positive
    from 
    (
        select
            IF( re.estatus='C' and ({$fieldpositive}), 1,0) as is_positive
        from ci_batches_pcr ba
        JOIN ci_reports_pcr re ON re.patient_id = ba.assen
        where {$where}
            AND " . trpdd_abx_query_date_range($mfrom, $mto) . "
            and ({$where_group})
    ) as rs
    ";

    $rs1 = $wpdb->get_row($sql);

    $numtestsofm = intval($rs1->countreqs);
    $pos = intval($rs1->count_positive);
    $neg = $numtestsofm - $pos;

    $pcneg = $numtestsofm > 0 ? number_format($neg / $numtestsofm * 100, 1) : 0;
    $pcpos = $numtestsofm > 0 ? number_format($pos / $numtestsofm * 100, 1) : 0;

    if ($i == 0) {
        $chart->addData($pcneg . '% Negative', $neg);
        $chart->addData($pcpos . '% Positive', $pos);
    }
    $chart->setTotal($numtestsofm);
    $chart->addMonthData($monthname, array(array('', ''), array($pcneg . '% Negative', $neg), array($pcpos . '% Positive', $pos)), $numtestsofm);

    $chart2->addData($monthname, $neg, $pos);

    echo '<div class="bymonth bymonth_' . $monthname . '" ' . ($i != 0 ? 'style="display:none"' : '') . '>';
    echo '<span class="numberbox"><span class="dot"></span><span class="tnumber">' . $numtestsofm . '</span> total samples</span>';
    echo '<span class="numberbox"><span class="dot blue"></span><span class="tnumber">' . $neg . '</span> negative</span>';
    echo '<span class="numberbox"><span class="dot red"></span><span class="tnumber">' . $pos . '</span> positive</span>';

    if (isset($_GET['debug'])) {
        var_dump($sql);
    }
    echo '</div>';
}


echo '</div>';

echo '</div>';
echo '</div>';






//echo '<br>';
echo '<div class="postbox" >';
echo '<div class="postbox-header"><h2 class="hndle"><text>Samples tested in <text class="displaymonth" id="display_month1">' . $monthname . '</text></text>';
echo '<span id="selectmonth1" class="selectmonth">Select Month</span></h2></div>';
echo '<div class="postbox_wrap">';
echo '<h2 class="nomargin">Negative, positive tests as % of total</h2>';
$chart->display();
echo '</div>';
echo '</div>';





echo '<div class="postbox postbox2" >';
echo '<div class="postbox-header"><h2 class="hndle">Trends</h2></div>';
echo '<div class="postbox_wrap">';

$chart2->display();
echo '</div>';
echo '</div>';

$chart->setupMonthSelect('#selectmonth1', 'display_month1');
