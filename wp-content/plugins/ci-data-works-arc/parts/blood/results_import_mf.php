<?php
include_once(TRCPDD_PATH . 'inc/functions_rack_plate.php');
require_once(TRCPDD_PATH . 'inc/PHPExcel/IOFactory.php');


$mf_fields = array('i12' => 1, 'j12' => 100, 'k12' => 2, 'l12' => 200, 'm12' => 3, 'n12' => 300);
$vmax_fields = array('f9' => 'Hb', 'k3' => 'Zero (undiluted)');

$files      = isset($files) && count($files) > 0 ? $files : $_SESSION['files_blood_import'];
$startime   = microtime(true);

if (!function_exists('trpdd_key_by_num')) {
    function trpdd_key_by_num($num)
    {
        $num = intval($num);
        if ($num == 8) $num = 7;
        if ($num == 10) $num = 8;
        $skey = chr(98 + $num);
        return $skey;
    }
    function calculateZ($A, $B, $cell)
    {
        $Z = pow(($cell['c46'] - ($A * 1 / (1 + $B))), 2) +
            pow(($cell['d46'] - ($A * 2 / (2 + $B))), 2) +
            pow(($cell['f46'] - ($A * 4 / (4 + $B))), 2) +
            pow(($cell['h46'] - ($A * 6 / (6 + $B))), 2) +
            pow(($cell['i46'] - ($A * 8 / (8 + $B))), 2) +
            pow(($cell['j46'] - ($A * 10 / (10 + $B))), 2);
        return $Z;
    }
}

$assen_splitchars = array(' ', '.', '-', '_');

foreach ($files as $filename => $meta) {
    $filepath = $log_dir . $filename;

    $filesize = filesize($filepath);

    $assen = $filename;
    $assen = str_replace('redo.', '.', $assen);
    foreach ($assen_splitchars as $aschar) {
        if (strpos($assen, $aschar) > 0) {
            list($assen) = explode($aschar, $assen);
        }
    }
    $assen = trim($assen);
    $metadata = array();

    $report = $wpdb->get_row("select * from ci_reports_blood where patient_id = '{$assen}'");
    $report_id = $report && $report->id > 0 ? $report->id : 0;

    if ($report_id > 0) {
        $metadata = $wpdb->get_var("select comments from ci_results_blood 
        where report_id = '{$report_id}'
        and drug = '_mfdata' ");

        $metadata = json_decode($metadata, true);
        foreach ($metadata as $k => $vl) {
            if ($k == 'import_type' || $vl === '') continue;
            $metadata[$k] = floatval(str_replace(',', '.', $vl));
        }
    }


    $metadata2 = $wpdb->get_var("select rf_meta_data from ci_reports_files where rf_ID = {$meta['file_id']}");
    if (!empty($metadata2)) {
        $metadata2 = json_decode($metadata2, true);

        foreach ($metadata2 as $k => $vl) {
            if ($k == 'import_type' || $vl === '') continue;
            $metadata[$k] = floatval(str_replace(',', '.', $vl));
        }
    }



    if (isset($_REQUEST['debug1'])) {
        $metadata['excl_tube'] = $_GET['excl'];
    }

    //check meta
    $missing_meta = false;
    foreach ($mf_fields as $mfk => $mfvl) {
        if (!isset($metadata[$mfk]) || $metadata[$mfk] === '') {
            $missing_meta = true;
            break;
        }
    }

    foreach ($vmax_fields as $mfk => $mfvl) {
        if (!isset($metadata[$mfk]) || $metadata[$mfk] === '') {
            $missing_meta = true;
            break;
        }
    }


    if (!$report_id) {
        $report_data = array(
            'patient_id' => $assen,
            'batch_id' => 0,
            'created' => date_i18n('Y-m-d H:i:s'),
            'created_date' => date_i18n('Y-m-d'),
            'lab_id' => CI_Base_Lab::get_current_lab_id(),
        );

        $wpdb->insert('ci_reports_blood', $report_data);
        $report_id = $wpdb->insert_id;
    }

    Tr_Session_Class::add("Imported: {$assen}");

    if (!$missing_meta) {
        $filetype = PHPExcel_IOFactory::identify($filepath);
        $objReader = PHPExcel_IOFactory::createReader($filetype);
        $objPHPExcel = $objReader->load($filepath);
        $cell = $metadata;

        $cell['b18'] = 0.12;
        $cell['e49'] = 3;
        $cell['j49'] = 10;
        $cell['f10'] = 0.5;


        foreach ($objPHPExcel->getAllSheets() as $sheet) {
            $sheet_title = $sheet->getTitle();
            $skey = '';
            if (stripos($sheet_title, 'zero') !== false) {
                $skey = 'b';
            } else if (stripos($sheet_title, 'min') !== false) {
                $skey = str_replace('min', '', $sheet_title);
                $skey = trpdd_key_by_num($skey);
            }
            if (!empty($skey)) {
                $cell[$skey . '23'] = $sheet->getCell('AG3')->getCalculatedValue();
                $cell[$skey . '24'] = $sheet->getCell('AG4')->getCalculatedValue();
                $cell[$skey . '25'] = $sheet->getCell('AG5')->getCalculatedValue();

                $cell[$skey . '31'] = $cell[$skey . '23'] / $cell['b18'];
                $cell[$skey . '32'] = $cell[$skey . '24'] / $cell['b18'];
                $cell[$skey . '33'] = $cell[$skey . '25'] / $cell['b18'];
            }
        }

        $cell['f11'] = $cell['f9'] / $cell['f10'];
        $cell['k4']  = $cell['k3'] / $cell['f11'];


        $cell['j36'] = ($cell['i12'] - $cell['k4']) / ($cell['j12'] - $cell['k4']);
        $cell['j37'] = ($cell['k12'] - $cell['k4']) / ($cell['l12'] - $cell['k4']);
        $cell['j38'] = ($cell['m12'] - $cell['k4']) / ($cell['n12'] - $cell['k4']);

        $cell['b36'] = ($cell['k4'] - $cell['k4']) / $cell['j12'];
        $cell['b37'] = ($cell['k4'] - $cell['k4']) / $cell['l12'];
        $cell['b38'] = ($cell['k4'] - $cell['k4']) / $cell['n12'];

        $cell['i13'] = ($cell['j36'] - $cell['b36']) / ($cell['j31'] - $cell['b31']);
        $cell['k13'] = ($cell['j37'] - $cell['b37']) / ($cell['j32'] - $cell['b32']);
        $cell['m13'] = ($cell['j38'] - $cell['b38']) / ($cell['j33'] - $cell['b33']);

        foreach (array(1, 2, 4, 6, 8) as $num) {
            $skey = trpdd_key_by_num($num);
            $cell[$skey . '36'] = $cell['b36'] + ($cell[$skey . '31'] - $cell['b31']) * $cell['i13'];
            $cell[$skey . '37'] = $cell['b37'] + ($cell[$skey . '32'] - $cell['b32']) * $cell['k13'];
            $cell[$skey . '38'] = $cell['b38'] + ($cell[$skey . '33'] - $cell['b33']) * $cell['m13'];
        }
        if(isset($_GET['debugbg']))
        {
            $metadata['excl_tube'] = '';
        }
        $check_auto_exclude = !$metadata['excl_tube'];
        
        $background = array();
        $row45s = array();
        $has_auto_exclude = false;


        foreach (array(0, 1, 2, 4, 6, 8, 10) as $num) {
            $skey = trpdd_key_by_num($num);
            $cell[$skey . '41'] = $cell[$skey . '36'] * (1 - $cell['f10'] * 3 / 100 * (1 - $cell[$skey . '36']));
            $cell[$skey . '42'] = $cell[$skey . '37'] * (1 - $cell['f10'] * 3 / 100 * (1 - $cell[$skey . '37']));
            $cell[$skey . '43'] = $cell[$skey . '38'] * (1 - $cell['f10'] * 3 / 100 * (1 - $cell[$skey . '38']));

            $arrav = array();
            if (!isset($metadata['excl_tube']) || $metadata['excl_tube'] != 1) {
                $arrav[] = $cell[$skey . '41'];
            }
            if (!isset($metadata['excl_tube']) || $metadata['excl_tube'] != 2) {
                $arrav[] = $cell[$skey . '42'];
            }
            if (!isset($metadata['excl_tube']) || $metadata['excl_tube'] != 3) {
                $arrav[] = $cell[$skey . '43'];
            }

            $cell[$skey . '46'] = trpdd_cal_avegare($arrav);
            $cell[$skey . '44'] = trpdd_stats_standard_deviation($arrav);

            $cell[$skey . '45'] = $cell[$skey . '46'] != 0 ? $cell[$skey . '44'] / $cell[$skey . '46'] : 0;
            if ($num > 0) {
                $row45s[] = $cell[$skey . '45'];
            }

            if ( $check_auto_exclude && $num > 0) {

                $cell[$skey . '47'] = $cell[$skey . '46'] - $cell[$skey . '44'];
                $cell[$skey . '48'] = $cell[$skey . '46'] + $cell[$skey . '44'];

                if (
                    round($cell[$skey . '41'], 2) < round($cell[$skey . '47'], 2)
                    || round($cell[$skey . '41'], 2) > round($cell[$skey . '48'], 2)
                ) {
                    $background[41][$skey] = true;
                }

                if (
                    round($cell[$skey . '42'], 2) < round($cell[$skey . '47'], 2)
                    || round($cell[$skey . '42'], 2) > round($cell[$skey . '48'], 2)
                ) {
                    $background[42][$skey] = true;
                }
                if (
                    round($cell[$skey . '43'], 2) < round($cell[$skey . '47'], 2)
                    || round($cell[$skey . '43'], 2) > round($cell[$skey . '48'], 2)
                ) {
                    $background[43][$skey] = true;
                }
            }
        }

        $cell['k45'] = trpdd_cal_avegare($row45s);

        //start check exclude
        if (isset($_GET['debugbg'])) {
            var_dump($background);
            $kc = 'i';
            echo '<br>';
            for($i1 = 41;$i1<=48;$i1++)
            {
                echo $kc.$i1.'=';
                echo round($cell[$kc.$i1],2);
                echo '<br>';
            }
            exit;
        }

        if ($check_auto_exclude) {
            if (count($background[41]) >= 3) {
                $metadata['excl_tube'] = 1;
                $has_auto_exclude = true;
            } else if (count($background[42]) >= 3) {
                $metadata['excl_tube'] = 2;
                $has_auto_exclude = true;
            } else if (count($background[43]) >= 3) {
                $metadata['excl_tube'] = 3;
                $has_auto_exclude = true;
            }

            

            //check no exclusion
            if ($cell['k45'] < 0.1) {
                $metadata['excl_tube'] = 0;
            }else{
                if (count($background[41]) == 3 && (count($background[42]) + count($background[43])) == 1) {
                    $metadata['excl_tube'] = 0;
                    $has_auto_exclude = true;
                } else if (count($background[42]) == 3 && (count($background[41]) + count($background[43])) == 1) {
                    $metadata['excl_tube'] = 0;
                    $has_auto_exclude = true;
                } else if (count($background[43]) == 3 && (count($background[41]) + count($background[42])) == 1) {
                    $metadata['excl_tube'] = 0;
                    $has_auto_exclude = true;
                } else if (count($background[41]) == 4 && (count($background[42]) + count($background[43])) == 2) {
                    $metadata['excl_tube'] = 0;
                    $has_auto_exclude = true;
                } else if (count($background[42]) == 4 && (count($background[41]) + count($background[43])) == 2) {
                    $metadata['excl_tube'] = 0;
                    $has_auto_exclude = true;
                } else if (count($background[43]) == 4 && (count($background[41]) + count($background[42])) == 2) {
                    $metadata['excl_tube'] = 0;
                    $has_auto_exclude = true;
                }
            }

            $metadata['auto_excl_tube'] = $metadata['excl_tube'];
        }

        if ($cell['k45'] > 0.1) {
            if (count($background[41]) == 3 && (count($background[42]) + count($background[43])) == 1) {
                $metadata['need_review'] = 1;
            } else if (count($background[42]) == 3 && (count($background[41]) + count($background[43])) == 1) {
                $metadata['need_review'] = 1;
            } else if (count($background[43]) == 3 && (count($background[41]) + count($background[42])) == 1) {
                $metadata['need_review'] = 1;
            } else if (count($background[41]) == 4 && (count($background[42]) + count($background[43])) == 2) {
                $metadata['need_review'] = 1;
            } else if (count($background[42]) == 4 && (count($background[41]) + count($background[43])) == 2) {
                $metadata['need_review'] = 1;
            } else if (count($background[43]) == 4 && (count($background[41]) + count($background[42])) == 2) {
                $metadata['need_review'] = 1;
            }

            if ($metadata['need_review']) {
                Tr_Session_Class::add_error(" LIS should suggest reviewing the data in excel and deciding if excluding a tube or not!");
            }
        }



        if ($has_auto_exclude) {
            foreach (array(0, 1, 2, 4, 6, 8, 10) as $num) {
                $skey = trpdd_key_by_num($num);
                $arrav = array();
                if (!isset($metadata['excl_tube']) || $metadata['excl_tube'] != 1) {
                    $arrav[] = $cell[$skey . '41'];
                }
                if (!isset($metadata['excl_tube']) || $metadata['excl_tube'] != 2) {
                    $arrav[] = $cell[$skey . '42'];
                }
                if (!isset($metadata['excl_tube']) || $metadata['excl_tube'] != 3) {
                    $arrav[] = $cell[$skey . '43'];
                }

                $cell[$skey . '46'] = trpdd_cal_avegare($arrav);
            }
        }




        $cell['d51'] = 0.0;
        $cell['d52'] = 0.0;
        $cell['d53'] = 0.0;
        $cell['d54'] = 31776.989;

        $minZ = INF;
        $minA = 0.000;
        $minB = 0.000;

        //Z = (0.23 - (A*1/(1+B))^2 +  (0.4 - (A*2/(2+B) ) )^2 + (0.63- (A*4/(4+B)) )^2 + (0.77- (A*6/(6+B) ) )^2 + (0.85- (A*8/(8+B) ) )^2 + (0.91- ( A*10/(10+B) ) )^2

        $start_A = 0.000;
        $end_A = 8;
        $start_B = 0.000;
        $end_B = 13;

        for ($A = 0.0; $A <= 8; $A += 0.1) {
            for ($B = 0.0; $B <= 13; $B += 0.1) {
                $Z = calculateZ($A, $B, $cell);
                if ($Z < $minZ) {
                    $minZ = $Z;
                    $minA = $A;
                    $minB = $B;
                }
            }
        }
        $start_A = round($minA - 0.6, 1);
        $end_A = round($minA + 0.6, 1);
        $start_B = round($minB - 0.6, 1);
        $end_B = round($minB + 0.6, 1);

        if (isset($_GET['debug1'])) {

            echo 'time: ' . (microtime(true) - $startime);
            echo '<br>';
            echo 'A: ' . $minA;
            echo '<br>';
            echo 'B: ' . $minB;
            echo '<br>';
            echo '<br>';
            echo 'Z: ' . $minZ;
            echo '<br>';
        }



        for ($A = $start_A; $A <= $minB; $A += 0.001) {
            for ($B = $start_B; $B <= $end_B; $B += 0.001) {
                $Z = calculateZ($A, $B, $cell);
                if ($Z < $minZ) {
                    $minZ = $Z;
                    $minA = $A;
                    $minB = $B;
                }
            }
        }

        $cell['d51'] = $minA;
        $cell['d52'] = $minB;
        $cell['minz'] = $minZ;


        //----

        $cell['c58'] = $cell['d51'] * (-$cell['d52'] * log($cell['d52'] + $cell['e49']) + $cell['d52'] * log($cell['d52']) + $cell['e49']) + $cell['d53'] * (-$cell['d54'] * log($cell['d54'] + $cell['e49']) + $cell['d54'] * log($cell['d54']) + $cell['e49']);
        $cell['g58'] = $cell['d51'] * (-$cell['d52'] * log($cell['d52'] + $cell['j49']) + $cell['d52'] * log($cell['d52']) + $cell['j49']) + $cell['d53'] * (-$cell['d54'] * log($cell['d54'] + $cell['j49']) + $cell['d54'] * log($cell['d54']) + $cell['j49']);


        $aveg12 = trpdd_cal_avegare(array($cell['j12'], $cell['l12'], $cell['n12']));
        $cell['k5'] = $cell['k4'] / $aveg12;


        //    var_dump($cell);

        //end 
        $results_arr = array();
        $results_arr['MFI-3'] = number_format(round($cell['c58'], 2), 2);
        $results_arr['MFI-10'] = number_format(round($cell['g58'], 2), 2);
        $pre_hemo = round($cell['k5'] * 100, 2);
        if ($pre_hemo > 0 && $pre_hemo < 0.02) {
            $pre_hemo = '< 0.02';
        }
        $results_arr['PREEXISTING HEMOLYSIS'] = $cell['l5'] = $pre_hemo . '%';


        if (isset($_REQUEST['debug1'])) {

            echo 'A:' . $start_A . ' - ' . $end_A;
            echo '<br>';
            echo 'B:' . $start_B . ' - ' . $end_B;
            echo '<br>';


            echo 'MFI-3: ';
            var_dump($cell['c58']);
            echo '<br>';
            echo 'MFI-10: ';
            var_dump($cell['g58']);
            echo '<br>';
            echo 'HEMOLYSIS: ';
            var_dump($cell['k5']);
            echo '<br>';
            echo '<br><br>';
            echo 'A: ' . $minA;
            echo '<br>';
            echo 'B: ' . $minB;
            echo '<br>';
            echo 'Z: ' . $minZ;
            echo '<br><br>';
            echo 'time: ' . (microtime(true) - $startime);
            echo '<br><br>';
            echo 'Sample: ' . $assen . '<br>';

            $sheet = $objPHPExcel->getSheet(1);
            $exist_arr['MFI-3'] = $sheet->getCell('C58')->getCalculatedValue();
            $exist_arr['MFI-10'] = $sheet->getCell('G58')->getCalculatedValue();
            $exist_arr['PREEXISTING HEMOLYSIS'] = $sheet->getCell('L5')->getCalculatedValue();

            $dbdata = array();
            foreach ($exist_arr as $k => $vl) {
                $dbvl = $wpdb->get_var("select conc from ci_results_blood where report_id = {$report_id}
            and drug = '{$k}' ");
                echo $k . ' => xls: ' . round($vl, 2);
                echo ' =>igniteLIS: ' . $results_arr[$k];

                echo ' =>dbvl: ' . $dbvl . '<br>';
            }
            echo '<br><br>';
            var_dump($exist_arr);
            echo '<br><br>';



            echo '<pre>';
            var_export($cell);
            echo "\n\n\n";
            var_dump($background);
            echo "\n\n\n";
            var_dump($metadata);
            echo '<pre>';
            echo '<br><br>';

            $results_arr  = array(); //disable update
            exit;
        }


        $has_rerun = ($cell['k45'] >= 0.15);

        $metadata['rerunby'] = $has_rerun ? 'Rerun by CV > 15%' : '';

        foreach ($results_arr as $drug => $vl) {
            $vl = substr($vl, 0, 10);

            Tr_Session_Class::add("{$drug}: {$vl}");

            $rs_id = $wpdb->get_var("select id from ci_results_blood 
					where report_id = {$report_id} and drug = '{$drug}'");
            $rsdata = array(
                'report_id' => $report_id,
                'drug' => $drug,
                'conc' => $vl,
            );
            if ($drug == 'MFI-3' && $vl > 0 && $vl < 0.4) {
                $rsdata['pos-neg'] = 'REJ';
                $rsdata['comments'] = 'Rejected – Indeterminate results, suggest recollection';
                $has_rerun = true;
                $metadata['rerunby'] = 'Rerun - MFI-3';
            } else if ($drug == 'MFI-10' && $vl > 0 && $vl < 4.0) {
                $rsdata['pos-neg'] = 'REJ';
                $rsdata['comments'] = 'Rejected – Indeterminate results, suggest recollection';
                $has_rerun = true;
                $metadata['rerunby'] = 'Rerun - MFI-10';
            } else if (in_array($drug, array('MFI-3', 'MFI-10')) && ($aveg12 < 0.4 || $aveg12 > 0.6)) {
                $rsdata['pos-neg'] = 'REJ';
                $rsdata['comments'] = 'Rejected – Indeterminate results, suggest recollection';
                $has_rerun = true;
                $metadata['rerunby'] = 'Rerun - high nanodrop dilution';
            }
            if ($rs_id > 0) {
                $wpdb->update('ci_results_blood', $rsdata, array('id' => $rs_id));
            } else {
                $wpdb->insert('ci_results_blood', $rsdata);
            }
        }

        //update report;
        $update_data = array();
        if ($has_rerun) {

            Tr_Session_Class::add_error($metadata['rerunby']);
            $update_data['covid_pos'] = -2;

            //check second rerun then reject
            if ($report && $report->covid_pos == -2) {

                if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'reimport_mf') {
                    //dontupdate
                } else if ($metadata['filesize'] > 0 && $filesize > 0 && $metadata['filesize'] != $filesize) {
                    $rejected = 'Rejected - Indeterminate result, suggest recollection';
                    $rejected_code = $wpdb->get_var("select s_key from ci_batches_status 
                    where s_text = '{$rejected}' and btype='blood' ");
                    if (empty($rejected_code)) {
                        $rejected_code = 're_rirsr';
                        $wpdb->insert('ci_batches_status', array('s_key' => $rejected_code, 's_text' => $rejected, 'btype' => 'blood'));
                    } else {
                        $rejected_code = 're_' . $rejected_code;
                    }
                    $wpdb->update('ci_batches_blood', array('batch_status' => $rejected_code), array('assen' => $assen));
                    Tr_Session_Class::add("Second Rerun -> Rejected");
                }
            } else {
                $update_data['is_pending'] = 1;
            }
        } else if ($report && $report->covid_pos == -2) {
            $update_data['covid_pos'] = 0;
        }

        if (count($update_data) > 0) {

            $rsud = $wpdb->update('ci_reports_blood', $update_data, array('id' => $report_id));
            if ($rsud === false) {
                Tr_Session_Class::add_error("error while update rerun");
            }
        }
    } else {

        //alert for missing data

    }

    //update meta

    $metadata['f10'] = $cell['f10'];
    $metadata['d51'] = $cell['d51'];
    $metadata['d52'] = $cell['d52'];
    $metadata['d53'] = $cell['d53'];
    $metadata['d54'] = $cell['d54'];
    $metadata['k45'] = $cell['k45'];

    $metadata['file'] = $filename;
    $metadata['filesize'] = $filesize;


    $meid = $wpdb->get_var("select id from ci_results_blood 
        where report_id = {$report_id}
        and drug = '_mfdata' ");
    unset($metadata['import_type']);
    $mfmeta = array(
        'report_id' => $report_id,
        'drug' => '_mfdata',
        'has_order' => '0',
        'comments' => json_encode($metadata)
    );
    if ($meid > 0) {
        $wpdb->update('ci_results_blood', $mfmeta, array('id' => $meid));
    } else {
        $wpdb->insert('ci_results_blood', $mfmeta);
    }
}
