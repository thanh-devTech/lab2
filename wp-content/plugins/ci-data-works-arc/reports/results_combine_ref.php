<?php
global $wpdb, $have_urine;

$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos/';
$logo_url = $upload_dir['baseurl'] . '/logos/';

$report = tr_pdd_get_report_by_id(null, 'tox', $report['mid']);




if (isset($_REQUEST['debug']) && isset($_REQUEST['html'])) {
    var_export($report);
    echo '<br><br>';
    echo 'Tests:';
    var_export($in_panel_tests_requested);
    echo '<br><br>';
    echo 'Medications:';
    var_export($medications);
    echo '<br><br>';
    echo '$drug_pairs:';
    var_export($drug_pairs);
    echo '<br><br>';
    echo '$all_res:';
    var_export($all_res);
    echo '<br><br>';
    echo '$panel_batch: ' . $panel_batch;
    echo '<br><br>';
    echo '$ci_cutoffs: ';
    var_dump($ci_cutoffs);
    echo '<br><br>';

    echo '$drug_groups: ';
    var_dump($drug_groups);
    echo '<br><br>';
}

if (isset($pdf_path)) {
    $report['acquisition_date'] = date_i18n("Y-m-d", filemtime($pdf_path));
}
$have_urine = false;

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>Result</title>
    <style>
        @page {
            margin: 1cm 1cm 2.5cm 1cm;
        }

        break,
        .break {
            page-break-after: always;
            border: 0;
        }

        * {
            font-family: Arial;
            font-size: 9pt;
        }

        a.NoteRef {
            text-decoration: none;
        }

        hr {
            height: 1px;
            padding: 0;
            margin: 1em 0;
            border: 0;
            border-top: 1px solid #CCC;
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
            border: 1px solid black;
            border-spacing: 0px;
            width: 100%;
        }

        table.tdborder td,
        table.tdborder th {
            border: 1px solid black;
        }

        table.border2.border.tdborder {
            border-top: 1px solid black;
            border-left: 1px solid black;
            border-right: 2px solid black;
            border-bottom: 2px solid #000
        }

        table.noborder td,
        table.noborder th {
            border: 0 !important
        }

        .marginbottom {
            margin-bottom: 10px;
        }

        .fontNormal {
            font-family: 'Arial';
            font-size: 9pt;
            color: #000000;
        }

        .fontNormalRed {
            font-family: 'Arial';
            font-size: 9pt;
            font-weight: bold;
        }

        .fontSmall {
            font-family: 'Arial';
            font-size: 9pt;
            color: #000000;
        }

        .fontBold {
            font-family: 'Arial';
            font-size: 9pt;
            color: #000000;
            font-weight: bold;
        }

        .fontBoldSmall {
            font-family: 'Arial';
            font-size: 9pt;
            color: #000000;
            font-weight: bold;
        }

        .fontBoldWhite {
            font-family: 'Arial';
            font-size: 9pt;
            color: #FFFFFF;
            font-weight: bold;
        }

        .fontHeader {
            font-family: 'Arial';
            font-size: 11pt;
            color: #000000;
            font-weight: bold;
        }

        .normal {
            margin-top: 2.5pt;
            margin-bottom: 2.5pt;
        }

        .table {
            margin-top: 0pt;
            margin-bottom: 0pt;
        }

        .tableCenter {
            text-align: center;
            margin-top: 0pt;
            margin-bottom: 0pt;
        }

        .header {
            margin-top: 10pt;
            margin-bottom: 10pt;
        }

        .center {
            text-align: center
        }


        h4,
        .h4 {
            font-size: 12px;
            margin: 0;
            padding: 0;
            line-height: 12px;
            font-weight: bold
        }

        .red {
            color: red
        }

        .small_table th,
        .small_table td {
            font-size: 8pt;
            padding: 2px;
            color: #000;
        }

        .small_padding_table th,
        .small_padding_table td {
            padding: 2px;
            color: #000;
        }

        table tr th.header_black {
            background: #444;
            color: #fff;
            padding: 4px;
        }

        table tr th.header_none {
            border-top: none;
            border-right: none
        }

        <?php if (!isset($_REQUEST['html'])): ?>#header,
        #footer {
            position: fixed;
            left: 0;
            right: 0;
            color: #666;
            font-size: 0.9em;
        }

        <?php endif; ?>#header {
            top: 0;
        }

        #footer {
            bottom: 5px;
            border-top: 0.1pt solid #aaa;
            line-height: 13px;
            padding-top: 5px;
        }
    </style>
</head>

<body>

    <div id="footer">
        <?php
        if (!empty($report['rl_lab_additional'])) {
            $footer_text = stripslashes($report['rl_lab_additional']);
            echo apply_filters('the_content', $footer_text);
        } else if (!empty($report['lab_results_footer'])) {
            $footer_text = stripslashes($report['lab_results_footer']);
            $footer_text = str_replace(array('[lab]', '{original_footer}'), array($report['lab_dba_init'], @$report['lab_additional']), $footer_text);
            echo $footer_text;
        }
        if (empty($footer_text)) echo apply_filters('the_content', @$report['lab_results_footer']);
        ?>
    </div>



    <div style="margin-bottom:15px">

        <table style="width: 100%;border:0;border-collapse:collapse">
            <tr>
                <td style="width:60%;height:100px;" valign="top">
                    <?php
                    $logo_path = $logo_dir . $report['lab_logo'];
                    $logo_main = $report['lab_logo'];


                    $logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

                    $has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
                    if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
                        $logo_main = '';
                    } else {
                        $has_logo2 = false;
                    }

                    if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
                        $size = getimagesize($logo_dir . $logo_main);
                        $max_width = 400;
                        $max_height = ($size[0] > $size[1]) ? 90 : 100;
                        if ($has_logo2) {
                            // $max_height-=30;
                            $max_width = 200;
                        }
                    ?>
                        <img border="0" style="max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_dir . $logo_main ?>" />
                    <?php
                    }
                    if ($has_logo2) {
                        $size = getimagesize($logo_dir . $logo_second);
                        $max_width = 200;
                        $max_height = ($size[0] > $size[1]) ? 90 : 100;
                        //echo '<br>';
                    ?>
                        <img border="0" style="margin-left:10px;max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_dir . $logo_second ?>" />
                    <?php
                    }

                    ?>

                </td>
                <td>
                </td>
                <td style="width:60%;vertical-align: top;min-width: 300px;padding-right:0;">
                    <table class="border" style="width:100%;">
                        <tr>
                            <td style="padding:10px;">
                                <?php if (!empty($report['header_lab_address'])): ?>
                                    <div><strong><?php echo $report['header_lab_address'] ?></strong></div>
                                <?php endif; ?>
                                <table class="noborder">
                                    <tr>
                                        <td>
                                            <div>Phone: <?php echo $report['header_lab_phone'] ?></div>
                                            <div>CLIA # <?php echo $report['header_lab_clia'] ?></div>
                                        </td>
                                        <td>
                                            <?php /*
                                        <div>Fax: <?php echo $report['lab_fax']?></div>
                                        <div>NPI # <?php echo $report['lab_npi']?></div>
                                        */ ?>
                                        </td>
                                    </tr>
                                </table>
                                <?php /**
                            <div><?php echo $report['lab_additional']?></div>
                                 *
                                 */ ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <table class="border tdborder small_padding_table">
            <tr>
                <th style="width:25%">Patient Name</th>
                <td style="width:25%"><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
                <th style="width:25%">Physician/Provider</th>
                <td style="width:25%"><?php echo htmlspecialchars($report['physician']) ?></td>
            </tr>
            <tr>
                <th>Patient ID</th>
                <td><?php echo htmlspecialchars($report['assen']) ?></td>
                <th>Lab</th>
                <td><?php echo htmlspecialchars($report['lab_dba_init']) ?></td>
            </tr>
            <tr>
                <th>Patient DOB</th>
                <td>
                    <?php
                    if (empty($report['patient_dob']) || $report['patient_dob'] == '00000' || $report['patient_dob'] == '0000-00-00' || $report['patient_dob'] == '00/00/0000') {
                        echo 'Not Provided';
                    } else {
                        echo tr_pdd_display_date($report['patient_dob']);
                    }
                    ?>
                </td>
                <th>Age</th>
                <td><?php echo trdd_dob2ages($report['patient_dob']) ?></td>
            </tr>
            <tr>
                <th>Results Date</th>
                <td>
                    <?php
                    if (empty($report['acquisition_date']) || $report['acquisition_date'] == '00000' || $report['acquisition_date'] == '0000-00-00' || $report['acquisition_date'] == '00/00/0000') {
                        echo 'Not Provided';
                    } else {
                        echo tr_pdd_display_date($report['acquisition_date']);
                    }
                    ?>
                </td>
                <th>Sample Type</th>
                <td><?php echo htmlspecialchars($report['sample_type']) ?></td>
            </tr>
            <tr>
                <th>Collection Date</th>
                <td><?php echo tr_pdd_display_date($report['collection_date']) ?></td>
                <th>Received in Lab</th>
                <td><?php echo tr_pdd_display_date($report['received_in_lab']) ?></td>
            </tr>
            <tr>
                <th>Medications</th>
                <td colspan="3"><?php echo (!empty($report['medications'])) ? htmlspecialchars(str_replace(',', ', ', $report['medications'])) : "No reported medications" ?></td>
            </tr>
        </table>
    </div>

    <?php
    //include urine validate & screening
    include(TRCPDD_PATH . 'reports/results_urine.php');
    include(TRCPDD_PATH . 'reports/results_screening.php');
    ?>
</body>

</html>