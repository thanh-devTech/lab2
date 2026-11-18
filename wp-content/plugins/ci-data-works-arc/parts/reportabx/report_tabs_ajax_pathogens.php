<?php
$is_load_table = filter_var($_REQUEST['is_load_table'] ?? null, FILTER_VALIDATE_BOOLEAN);
$prevalence_option = sanitize_text_field($_REQUEST['prevalence'] ?? '0');

$chart3 = new CI_Chart(array(
    'width' => 500,
    'height' => 400,
    'pieSliceText' => 'value',
    'title' => ''
));
$chart3->setName_Unit('', '');
$chart3->showTotal(true);


$wherebygroups  = array();
$tests_colors   = array();

$fieldpositive = 're.has_uti > 0';
if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $fieldpositive .= ' or re.has_pos > 0';
}

foreach ($main_abx_group_gids as $mgid) {


    $sql_bacteria = "select 
        te.te_ID,
        te.te_name,
        te.te_abrev,
        te.te_color
        from ci_test_panels te
        join ci_test_groups g ON g.tg_ID = te.te_group_id
        left join ci_test_meta tm on tm.test_id = te.te_ID and tm.meta_key = 'bac_tests' and tm.meta_value !='' and tm.meta_value is not null
        where g.tg_antibiotic = 0  and g.tg_name not like '%Antibiotic%' 
        and te.te_is_control = 0
        and te.te_in_req = 1
        and g.tg_parent = {$mgid} 
        and ((g.tg_name like '%Bacteria%' or g.tg_name like '%Pathogen%' or g.tg_name like '%PARASITE%' or g.tg_name like '%Gram%') or tm.test_id is not null )";

    if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
        $sql_bacteria = "select 
            te.te_ID,
            te.te_name,
            te.te_abrev,
            te.te_color
        from ci_test_panels te
        join ci_test_groups g ON g.tg_ID = te.te_group_id
        where g.tg_antibiotic = 0  and g.tg_name not like '%Antibiotic%' 
        and te.te_is_control = 0
        and te.te_in_req = 1
        and g.tg_parent = {$mgid} 
        ";
    }
    $bacteria_tests = $wpdb->get_results($sql_bacteria);

    if (count($bacteria_tests) == 0) {
        //get in root folder
        $bacteria_tests = $wpdb->get_results(
            "select 
                te.te_ID,
                te.te_name,
                te.te_abrev,
                te.te_color 
            from ci_test_panels te
            JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
            where g.tg_antibiotic = 0 and g.tg_name not like '%Antibiotic%'
            and te.te_is_control = 0
            and te.te_in_req = 1
            and g.tg_ID in ({$mgid}) "
        );
    }

    $bacteria_test_IDs = array();
    foreach ($bacteria_tests as $bt) {
        $bacteria_test_IDs[] = $bt->te_ID;
        $color = trim((string)$bt->te_color);
        if ($color === '') {
            continue;
        }

        $nameKey  = strtoupper(trim($bt->te_name));
        $abrevKey = strtoupper(trim((string)$bt->te_abrev));

        if (!isset($tests_colors[$nameKey])) {
            $tests_colors[$nameKey] = $color;
        }

        if ($abrevKey !== '' && !isset($tests_colors[$abrevKey])) {
            $tests_colors[$abrevKey] = $color;
        }
    }

    if (count($bacteria_test_IDs) > 0) {
        $bacteria_test_IDs_str = implode(',', $bacteria_test_IDs);

        $wherebygroups[$mgid]  = ' ba.is_saved = 0 ' . $conditions['where'];

        if ($c_cid > 0) {
            $wherebygroups[$mgid] .= " AND ba.cli_ID = " . intval($c_cid);
        }

        $wherebygroups[$mgid] .= " and FIND_IN_SET('GROUP_{$mgid}', panel_tests_requested)  ";

        $wherebygroups[$mgid] .= " and te.te_ID in ({$bacteria_test_IDs_str}) ";
        $wherebygroups[$mgid] .= " and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') ";
        $wherebygroups[$mgid] .= " and rs.has_order = 1 ";
        $wherebygroups[$mgid] .= " and re.estatus = 'C' ";
        $wherebygroups[$mgid] .= " and ({$fieldpositive}) ";
    }
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
    $rs_pn = $cache->get_row($sql, OBJECT, 0, $cache_query_seconds);
} else {
    $rs_pn = $wpdb->get_row($sql, OBJECT);
}


//Data for chart
$childqrs = array();
if (count($wherebygroups) > 0) {
    foreach ($wherebygroups as $mgid => $cwhere) {
        $childqrs[] = " ( 
        select te.te_name,
        LOWER(te.te_abrev) as te_abrev,
        count(DISTINCT ba.mid) as count1
        from ci_test_panels te
        join ci_results_pcr rs ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
        join ci_reports_pcr re ON re.id = rs.report_id
        join ci_batches_pcr ba ON re.patient_id = ba.assen
        where {$cwhere}
        AND " . trpdd_abx_query_date_range($date_from, $date_to) . "
        group by te_name
       ) ";
    }

    $childqr_str = implode(' UNION ALL ', $childqrs);
    $sqlmk = "select lmk.te_name, 
        lmk.te_abrev,
        sum(lmk.count1) as count 
        from ({$childqr_str}) as lmk 
        group by te_name
        order by count desc, te_name asc";


    if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

        $cache = new Tr_Cache_Query('reportabx');
        $tests = $cache->get_results($sqlmk, OBJECT, $cache_query_seconds);
    } else {
        $tests = $wpdb->get_results($sqlmk);
    }
} else {
    $tests = [];
}


$total = 0;
$total_positive = $rs_pn->count_positive;
$total_sample = $rs_pn->countreqs;
$new_tests = array();

foreach ($tests as $m) {
    $total += $m->count;
    $m->te_abrev = strtolower($m->te_abrev);

    if (isset($abrevbynames[$m->te_abrev])) {
        $m->te_name = $abrevbynames[$m->te_abrev];
    } else if (!empty($m->te_abrev)) {
        $abrevbynames[$m->te_abrev] = $m->te_name;
    }

    if (isset($new_tests[$m->te_name])) {
        $m->count += $new_tests[$m->te_name]->count;
    }

    $new_tests[$m->te_name] = $m;
}

$chart3->setTotal($total);
$colors = [];
foreach ($new_tests as $m) {

    if (!isset($all_tests[$m->te_name])) {
        $color_row = $tests_colors[strtoupper($m->te_name)] ?? '';
        if (empty($color_row)) {
            $r = rand(10, 255);
            $g = rand(10, 255);
            $b = rand(10, 255);
            $color_row = "rgba($r, $g, $b, 1)";
        }

        $all_tests[$m->te_name] = array(
            'name' => $m->te_name,
            'sum' => 0,
            'color' => $color_row,
        );
    }

    $pc = $total > 0 ? round($m->count / $total * 100, 1) : 0;
    $pc_all = $total_sample > 0 ? round($m->count / $total_sample * 100, 1) : 0;
    $pc_pos = $total_positive > 0 ? round($m->count / $total_positive * 100, 1) : 0;
    $all_tests[$m->te_name]['sum'] += $pc;
    $chart3->addData($m->te_name, intval($m->count), $pc, $pc_all, $pc_pos, $total, $total_sample, $total_positive);

    $colors[] = $color_row;
}

$chart3->config['colors'] = $colors;

//Data for table
if (!$is_load_table) {
    $from_date = date_i18n('Y-m-01');
    $end_date = date_i18n('Y-m-t');

    $months = array();
    $all_tests = array();
    $abrevbynames = array();
    $pos_neg_6months = array();
    for ($i = 5; $i >= 0; $i--) {
        $mfrom = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
        $mto = date_i18n('Y-m-t', strtotime($mfrom));
        $monthkey = date('Y-m', strtotime($mfrom));
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
                AND " . trpdd_abx_query_date_range($mfrom, $mto) . "
        ) AS rs ";
        if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
            $cache = new Tr_Cache_Query('reportabx');
            $rs_pnm = $cache->get_row($sql, OBJECT, 0, $cache_query_seconds);
        } else {
            $rs_pnm = $wpdb->get_row($sql, OBJECT);
        }
        $pos_neg_6months[$monthkey] = array(
            'POS' => intval($rs_pnm->count_positive),
            'total' => intval($rs_pnm->countreqs),
        );
    }

    for ($i = 5; $i >= 0; $i--) {

        $mfrom = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
        $mto = date_i18n('Y-m-t', strtotime($mfrom));
        $monthkey = date('Y-m', strtotime($mfrom));
        $monthname = date('M', strtotime($mfrom));

        $months[$monthkey] = $monthname;

        $childqrs = array();
        if (count($wherebygroups) > 0) {
            foreach ($wherebygroups as $mgid => $cwhere) {
                $childqrs[] = " ( select te.te_name,
                LOWER(te.te_abrev) as te_abrev,
                count(DISTINCT ba.mid) as count1
                from ci_test_panels te
                join ci_results_pcr rs ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
                join ci_reports_pcr re ON re.id = rs.report_id
                join ci_batches_pcr ba ON re.patient_id = ba.assen
                where {$cwhere}
                AND " . trpdd_abx_query_date_range($mfrom, $mto) . "
                group by te_name
            ) ";
            }

            $childqr_str = implode(' UNION ALL ', $childqrs);
            $sqlmk = "select lmk.te_name,
                lmk.te_abrev,
                sum(lmk.count1) as count 
                from ({$childqr_str}) as lmk 
                group by te_name
                order by count desc, te_name asc";

            if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

                $cache = new Tr_Cache_Query('reportabx');
                $tests = $cache->get_results($sqlmk, OBJECT, $cache_query_seconds);
            } else {
                $tests = $wpdb->get_results($sqlmk);
            }
        } else {
            $tests = [];
        }

        $total = 0;
        $total_positive = $pos_neg_6months[$monthkey]['POS'];
        $total_sample = $pos_neg_6months[$monthkey]['total'];
        $new_tests = array();

        foreach ($tests as $m) {
            $total += $m->count;

            $m->te_abrev = strtolower($m->te_abrev);

            if (isset($abrevbynames[$m->te_abrev])) {
                $m->te_name = $abrevbynames[$m->te_abrev];
            } else if (!empty($m->te_abrev)) {
                $abrevbynames[$m->te_abrev] = $m->te_name;
            }

            if (isset($new_tests[$m->te_name])) {
                $m->count += $new_tests[$m->te_name]->count;
            }

            $new_tests[$m->te_name] = $m;
        }
        $month_charts = array(array('', ''));

        foreach ($new_tests as $m) {

            if (!isset($all_tests[$m->te_name])) {

                $color_row = $tests_colors[strtoupper($m->te_name)] ?? '';
                if (empty($color_row)) {
                    $r = rand(10, 255);
                    $g = rand(10, 255);
                    $b = rand(10, 255);
                    $color_row = "rgba($r, $g, $b, 1)";
                }

                $all_tests[$m->te_name] = array(
                    'name' => $m->te_name,
                    'sum' => 0,
                    'color' => $color_row,
                );
            }
            $pc = $total > 0 ? round($m->count / $total * 100, 1) : 0;
            $pc_all = $total_sample > 0 ? round($m->count / $total_sample * 100, 1) : 0;
            $pc_pos = $total_positive > 0 ? round($m->count / $total_positive * 100, 1) : 0;
            $all_tests[$m->te_name][$monthkey] = [
                'label' => $monthname,
                'value' => $pc,
                'value_all' => $pc_all,
                "value_pos" => $pc_pos,
                "count" => $m->count,
                "total" => $total,
                "total_sample" => $total_sample,
                'total_positive' => $total_positive
            ];
            $all_tests[$m->te_name]['sum'] += $pc;
            $month_charts[] = array($m->te_name, intval($m->count));
        }
    }

    //6 months table
    $table_pathogens = new CI_Report_Table();
    $table_pathogens->setHeader($months);
    $cur_monthkey = date_i18n('Y-m');
    uasort($all_tests, function ($a, $b) use ($cur_monthkey) {
        $val_a = $a[$cur_monthkey]['value'] ?? 0;
        $val_b = $b[$cur_monthkey]['value'] ?? 0;
        return $val_b <=> $val_a;
    });

    foreach ($all_tests as $arr) {
        $arr['testname'] = $arr['name'];
        foreach ($months as $mk => $mlabel) {
            if (!isset($arr[$mk])) {
                $arr[$mk] = array(
                    'label' => $mlabel,
                    'value' => '0',
                    'value_all' => '0',
                    'value_pos' => '0',
                    'count' => '0',
                    'total' => '0',
                    'total_sample' => '0',
                    'total_positive' => '0'
                );
            }
        }

        $table_pathogens->addRow($arr);
    }
}
