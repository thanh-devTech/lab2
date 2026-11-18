<?php
/*
 * Export pdf for Tox by Batch #
 */
global $wpdb, $current_role;

$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';

$user_id    = get_current_user_id();
$batch_id   = $_REQUEST['batch_id'];
$filename   = $batch_id . '.pdf';

$conditions     = trpdd_get_batch_conditions(0, $btype);
$where      = " ba.batch_id = '{$batch_id}' ";
$where      .= " and (re.id > 0 or ( re.id is null and " . tr_pdd_where_batch_rejected() . ") ) ";
$where .= $conditions['where'];

$batch_tbl = tr_pdd_get_table_by_btype($btype);
$report_tbl = tr_pdd_get_table_report_by_btype($btype);

$reports = $wpdb->get_results("select ba.mid,ba.assen
                                from {$batch_tbl} ba
                                left join {$report_tbl} re ON re.patient_id = ba.assen
                                where {$where}
                                ");

if (isset($_GET['debug'])) {
    var_dump($reports);
}

ob_start();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>Result</title>
    <style>
        @page {
            margin: 1cm;
        }

        .break {
            page-break-after: always;
            border: 0;
        }

        * {
            font-family: Arial;
            font-size: 8pt;
        }

        p {
            margin-top: 0;
            margin-bottom: 7px
        }

        a.NoteRef {
            text-decoration: none;
        }

        hr {
            height: 0;
            padding: 0;
            margin: 0.6em 0;
            border: 0;
            border-top: 0.1pt solid #aaa;
        }

        table {
            width: 100%;
            border-spacing: 0px;
            page-break-inside: auto;
            border-collapse: collapse;
        }

        table th {
            text-align: left;
            padding: 5px;
        }

        table td {
            padding: 5px;
            vertical-align: top
        }

        table.border {
            border: 1px solid #ddd;
            border-spacing: 0px;
            width: 100%;
        }

        table.border_top_bottom {
            border-top: 1px solid #555;
            border-bottom: 1px solid #555
        }

        table.tdborder td,
        table.tdborder th {
            border: 1px solid #ddd;
        }

        table.border2.border.tdborder {
            border-top: 1px solid #ddd;
            border-left: 1px solid #ddd;
            border-right: 2px solid #ddd;
            border-bottom: 2px solid #000
        }

        table.noborder td,
        table.noborder th {
            border: 0 !important
        }

        table tr.borderline td,
        table tr.borderline th {
            border-bottom: 1px solid #ddd;
            ;
        }
    </style>
</head>

<body>
    <table class="border">

        <tr class="borderline">
            <th style="width:15%">Sample</th>
            <th>Test</th>
            <th>Value</th>
            <th>Pos/Neg</th>
        </tr>

        <?php
        $list_assen = array();
        foreach ($reports as $report) {
            $mid = $report->mid;

            $validities = tr_pdd_get_result_validity(0, '', $mid);
            $count = 0;
            foreach ($validities as $arr) {
                $name = '';
                if (!in_array($report->assen, $list_assen)) {
                    $list_assen[] = $report->assen;
                    $name = $report->assen;
                }
                if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
                    $arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
                } else {
                    $arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
                }

        ?>
                <tr>
                    <td><?php echo $name ?></td>
                    <td><?php echo $arr['u_name'] ?></td>
                    <td><?php echo $arr['conc'] ?></td>
                    <td><?php echo $arr['outcome'] ?></td>
                </tr>
        <?php
                $count++;
            }
            if ($count == 0) {
                echo '<tr><td colspan="4">' . $report->assen . '</td> </tr>';
            }
            echo '<tr class="borderline"><td colspan="4"></td></tr>';
        }
        ?>
    </table>
</body>

</html>

<?php
$content = ob_get_clean();

if (isset($_GET['html'])) {
    echo $content;
    exit;
}


try {
    trpdd_build_pdf($content, array('filename' => $filename));
} catch (Exception $e) {
    Tr_Session_Class::add_error("Error while export PDF file. " . $e->getMessage());
}


exit;
