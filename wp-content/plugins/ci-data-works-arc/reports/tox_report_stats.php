<?php
// $month , $cli_ID
global $wpdb;

$clinic         = $wpdb->get_var("select cli_clinic from ci_clinic where cID = {$cli_ID} ");
$tests_Illicits = $wpdb->get_col("select te_name from ci_test_panels t JOIN ci_test_groups g ON g.tg_ID = t.te_group_id and g.tg_name = 'Illicits'");
$tests_Illicits = implode("','", $tests_Illicits);


$where = " ba.cli_ID in ({$cli_ID}) and ba.doc >= '{$month}-01' and ba.doc <= '{$month}-31 23:59:59' ";
$where .= " ";

$rows = $wpdb->get_results("
                select ba.doc, 
                ba.patient_id, 
                ba.patient_name,
                re.id
                from ci_batches ba
                 left join ci_reports re ON re.patient_id = ba.assen
                where {$where} 
                order by ba.patient_name asc, ba.doc asc");
$submited = true;
list($year, $m) = explode('-', $month);
$report_title = $m . '/' . $year;


if (count($rows) > 0) {
    $hasreport = true;
    $patients = array();
    $dates = array();
    foreach ($rows as $row) {
        $date  = tr_pdd_display_date($row->doc, 'm/d/Y');
        if (!in_array($date, $dates)) {
            $dates[] = $date;
        }


        $patients[$row->patient_id]['name'] = tr_pdd_capitalized($row->patient_name);
        if (isset($patients[$row->patient_id][$date])) {
            $patients[$row->patient_id][$date . '/2'] = $row;
        } else {
            $patients[$row->patient_id][$date] = $row;
        }
    }
    sort($dates);
    $col_counts = array();
?>


    <link href="<?php echo TRCPDD_URL . '/css/awesome.min.css?' ?>" type='text/css' media='all' rel='stylesheet' />
    <style type="text/css">
        @font-face {
            font-family: "FontAwesomeRegular";
            font-weight: bold;
            font-style: normal;
            src: url("<?php echo TRCPDD_URL ?>css/webfonts/fa-solid-900.ttf") format("truetype");
        }

        .fa {
            display: inline;
            font-style: normal;
            font-variant: normal;
            font-weight: bold;
            font-size: 10px;
            font-family: FontAwesomeRegular;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: green;
        }

        .fa-flag:before {
            content: "\f024";
        }

        th.datev {
            font-size: 12px;
            line-height: 13px !important;
            font-weight: normal !important;
            text-align: center !important;
        }

        .flattd {
            text-align: center;
        }

        .flattd a {
            text-decoration: none;
        }

        .report_tbl {
            width: 100%;
            border: 1px solid #ddd;
            border-collapse: collapse;
            font-size: 12px;
        }

        .report_tbl .flag {
            color: green;
            font-size: 10px;
            -webkit-print-color-adjust: exact;
        }

        .report_tbl .flag.red,
        red {
            color: red;
            -webkit-print-color-adjust: exact;
        }

        .report_tbl td,
        .report_tbl th {
            border: 1px solid #ddd;
        }


        .report_tbl thead tr th {
            position: sticky;
            top: 60px;
            z-index: 3;
            background-color: #4f4f67;
        }

        @media print {
            .report_tbl thead tr {
                background: #eee;
                -webkit-print-color-adjust: exact;
                line-height: 30px;
            }
        }

        .dompdf .report_tbl thead tr {
            background: #eee;
            -webkit-print-color-adjust: exact;
            line-height: 30px;
        }
    </style>

    <div class="wrap_report">
        <h2 class="center">Toxicology Patient Statistics <?php echo $report_title ?></h2>
        <p class="center"><?php echo $clinic ?></p>
        <table class="posts report_tbl">
            <thead>
                <tr>
                    <th style="width:20px" title="<?php echo count($rows) ?>">#</th>
                    <th style="width:150px">Patient Name</th>
                    <?php
                    foreach ($dates as $d) {
                        $d = explode('/', $d);
                        echo '<th class="datev">' . $d[1] . '</th>';
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                $count_red = 0;
                $count_green = 0;
                foreach ($patients as $patient_id => $patient) {
                ?>
                    <tr class="childp">
                        <td><?php echo $i++ ?></td>
                        <td><?php echo $patient['name'] ?></td>
                        <?php
                        foreach ($dates as $d) {
                            $has_illicits = false;
                            if (isset($patient[$d])) {
                                $row = $patient[$d];
                                $class = 'red';
                                $link_pdf = '';
                                if ($row->id > 0) {
                                    $class = 'green';
                                    $has_illicits = $wpdb->get_var("select count(*) from ci_results 
                                            where report_id = {$row->id} 
                                            and `pos-neg` = 'POS'
                                            and drug in ('{$tests_Illicits}')");
                                    if ($has_illicits) {
                                        $class = 'red';
                                    }
                                    $link_pdf = '?tr_action=ci_results_pdf&id=' . $row->id . (isset($_GET['view']) ? '&view=1' : '');
                                }
                                if ($class == "red") {
                                    $count_red++;
                                } else {
                                    $count_green++;
                                }
                                $col_counts[$d]++;
                        ?>
                                <td class="flattd" title="<?php echo $d ?>">
                                    <div>
                                        <?php if (!empty($link_pdf)) echo '<a href="' . $link_pdf . '" target="_blank">'; ?>
                                        <span class="fa fa-flag flag <?php echo $class ?>"></span>
                                        <?php if (!empty($link_pdf)) echo '</a>'; ?>
                                        <?php if ($has_illicits) echo '<red>i</red>'; ?>
                                    </div>
                                    <?php
                                    if (isset($patient[$d . '/2'])) {
                                        $has_illicits = false;
                                        $class = 'red';
                                        $row = $patient[$d . '/2'];
                                        $link_pdf = '';
                                        if ($row->id > 0) {
                                            $class = 'green';
                                            $has_illicits = $wpdb->get_var("select count(*) from ci_results 
                                            where report_id = {$row->id} 
                                            and `pos-neg` = 'POS'
                                            and drug in ('{$tests_Illicits}')");
                                            if ($has_illicits) {
                                                $class = 'red';
                                            }
                                            $link_pdf = '?tr_action=ci_results_pdf&id=' . $row->id . (isset($_GET['view']) ? '&view=1' : '');
                                        }
                                        if ($class == "red") {
                                            $count_red++;
                                        } else {
                                            $count_green++;
                                        }
                                        $col_counts[$d]++
                                    ?>
                                        <div>
                                            <?php if (!empty($link_pdf)) echo '<a href="' . $link_pdf . '" target="_blank">'; ?>
                                            <span class="fa fa-flag flag <?php echo $class ?>"></span>
                                            <?php if (!empty($link_pdf)) echo '</a>'; ?>
                                            <?php if ($has_illicits) echo '<red>i</red>'; ?>
                                        </div>
                                    <?php
                                    }
                                    ?>
                                </td>
                        <?php
                            } else {
                                echo '<td></td>';
                            }
                        }
                        ?>

                    </tr>
                <?php
                }
                ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" style="text-align: center; font-weight: 600;">Daily Totals</td>
                    <?php foreach ($dates as $d): ?>
                        <td>
                            <div class="total-cell" style="text-align: center; font-weight: 600;">
                                <span><?php echo $col_counts[$d] ?></span>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>

    </div>

    <div class="notprint">
        <div>Green: <?php echo $count_green ?></div>
        <div>Red: <?php echo $count_red ?></div>
    </div>

<?php
} else {
    echo '<p class="center">Not found</p>';
}
